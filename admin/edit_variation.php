<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Проверка ID варианта
$variation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($variation_id <= 0) {
    header("Location: manage_products.php");
    exit;
}

// Получаем информацию о варианте
$variation_query = $db->prepare("
    SELECT v.*, p.id as product_id, p.name, p.brand, p.model, p.type
    FROM product_variations v
    JOIN products p ON p.id = v.product_id
    WHERE v.id = ?
");
$variation_query->bind_param('i', $variation_id);
$variation_query->execute();
$variation = $variation_query->get_result()->fetch_assoc();

if (!$variation) {
    header("Location: manage_products.php");
    exit;
}

// Получаем существующие SKU для проверки уникальности (исключая текущий)
$existing_skus = [];
$sku_query = $db->prepare("SELECT sku FROM product_variations WHERE product_id = ? AND id != ?");
$sku_query->bind_param('ii', $variation['product_id'], $variation_id);
$sku_query->execute();
$sku_result = $sku_query->get_result();
while ($row = $sku_result->fetch_assoc()) {
    $existing_skus[] = $row['sku'];
}
$sku_query->close();

// Обработка формы
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_variation'])) {
    $sku = trim($_POST['sku']);
    $price = (float)$_POST['price'];
    $old_price = !empty($_POST['old_price']) ? (float)$_POST['old_price'] : null;
    $quantity = (int)$_POST['quantity'];
    
    // Валидация
    if (empty($sku)) {
        $errors[] = "Артикул обязателен";
    }
    if ($price <= 0) {
        $errors[] = "Цена должна быть больше 0";
    }
    if ($quantity < 0) {
        $errors[] = "Количество не может быть отрицательным";
    }
    
    // Проверка уникальности SKU
    if (in_array($sku, $existing_skus)) {
        $errors[] = "Артикул '$sku' уже существует для этого товара";
    }
    
    // Загрузка нового изображения
    $image_url = $variation['image_url'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 2 * 1024 * 1024; // 2 MB
        
        if (!in_array($_FILES['image']['type'], $allowed)) {
            $errors[] = "Неверный формат изображения. Разрешены JPG, PNG, GIF, WEBP";
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = "Размер изображения не должен превышать 2 МБ";
        } else {
            $upload_dir = __DIR__ . '/../uploads/variations/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Удаляем старое изображение
            if ($image_url && file_exists(__DIR__ . '/../' . $image_url)) {
                unlink(__DIR__ . '/../' . $image_url);
            }
            
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $new_name = 'var_' . $variation['product_id'] . '_' . uniqid() . '.' . $ext;
            $upload_path = $upload_dir . $new_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image_url = 'uploads/variations/' . $new_name;
            } else {
                $errors[] = "Ошибка при загрузке изображения";
            }
        }
    }
    
    if (empty($errors)) {
        $stmt = $db->prepare("
            UPDATE product_variations SET 
                sku = ?, price = ?, old_price = ?, quantity = ?, image_url = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sddssi", $sku, $price, $old_price, $quantity, $image_url, $variation_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Вариант товара успешно обновлен";
            header("Location: product_variations.php?id=" . $variation['product_id']);
            exit;
        } else {
            $errors[] = "Ошибка при обновлении варианта: " . $db->error;
        }
        $stmt->close();
    }
}
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Редактирование варианта товара</h1>
        <div>
            <a href="product_variations.php?id=<?= $variation['product_id'] ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Назад к вариантам
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Информация о варианте</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Товар:</strong> <?= htmlspecialchars($variation['brand'] . ' ' . $variation['name'] . ' ' . $variation['model']) ?>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Артикул (SKU) *</label>
                                <input type="text" name="sku" class="form-control" required
                                       value="<?= htmlspecialchars($variation['sku']) ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Цена *</label>
                                <div class="input-group">
                                    <input type="number" name="price" step="0.01" class="form-control" required
                                           value="<?= $variation['price'] ?>">
                                    <span class="input-group-text">₽</span>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Старая цена</label>
                                <div class="input-group">
                                    <input type="number" name="old_price" step="0.01" class="form-control"
                                           value="<?= $variation['old_price'] ?>">
                                    <span class="input-group-text">₽</span>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Количество на складе *</label>
                                <input type="number" name="quantity" class="form-control" required
                                       value="<?= $variation['quantity'] ?>"
                                       min="0">
                            </div>
                            
                            <div class="col-md-8">
                                <label class="form-label">Изображение для варианта</label>
                                <?php if (!empty($variation['image_url'])): ?>
                                    <div class="mb-2">
                                        <img src="<?= $base_url . $variation['image_url'] ?>" 
                                             style="max-height: 100px; object-fit: contain;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="image" class="form-control" accept="image/*">
                                <small class="text-muted">Оставьте пустым, чтобы сохранить текущее изображение</small>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" name="edit_variation" class="btn btn-primary">
                                <i class="fas fa-save"></i> Сохранить изменения
                            </button>
                            <a href="product_variations.php?id=<?= $variation['product_id'] ?>" class="btn btn-secondary">
                                Отмена
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Информация</h5>
                </div>
                <div class="card-body">
                    <p><strong>Текущие параметры:</strong></p>
                    <ul class="list-unstyled">
                        <li><strong>ID варианта:</strong> <?= $variation['id'] ?></li>
                        <li><strong>Артикул:</strong> <code><?= htmlspecialchars($variation['sku']) ?></code></li>
                        <li><strong>Цена:</strong> <?= formatPrice($variation['price']) ?></li>
                        <li><strong>На складе:</strong> <?= $variation['quantity'] ?> шт.</li>
                        <li><strong>Создан:</strong> <?= date('d.m.Y H:i', strtotime($variation['created_at'])) ?></li>
                    </ul>
                    <hr>
                    <p class="small text-muted">
                        <i class="fas fa-info-circle"></i>
                        Изменение количества на складе влияет на доступность товара для покупателей.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>