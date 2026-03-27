<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    $_SESSION['error'] = "ID товара не указан";
    header("Location: manage_products.php");
    exit;
}

// Получаем информацию о товаре (до header.php)
$product = $db->query("SELECT name, brand, model FROM products WHERE id = $product_id")->fetch_assoc();

if (!$product) {
    $_SESSION['error'] = "Товар не найден";
    header("Location: manage_products.php");
    exit;
}

// Обработка загрузки изображений
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_images'])) {
    $upload_dir = __DIR__ . '/../uploads/products/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Проверяем, есть ли уже изображения у товара
    $has_images = $db->query("SELECT id FROM product_images WHERE product_id = $product_id LIMIT 1")->num_rows > 0;
    
    $uploaded = 0;
    $errors = [];
    
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $image_stmt = $db->prepare("INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)");
        
        $is_first = !$has_images;
        $sort_result = $db->query("SELECT MAX(sort_order) as max_sort FROM product_images WHERE product_id = $product_id");
        $sort = $sort_result->fetch_assoc();
        $current_sort = ($sort['max_sort'] !== null) ? $sort['max_sort'] + 1 : 0;
        
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
                $errors[] = "Ошибка загрузки файла: " . $_FILES['images']['name'][$key];
                continue;
            }
            
            $ext = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($ext, $allowed)) {
                $errors[] = "Недопустимый формат: " . $_FILES['images']['name'][$key];
                continue;
            }
            
            $new_name = uniqid('product_', true) . '.' . $ext;
            $upload_path = $upload_dir . $new_name;
            
            if (move_uploaded_file($tmp_name, $upload_path)) {
                $image_url = 'uploads/products/' . $new_name;
                $is_primary = ($is_first && $uploaded === 0) ? 1 : 0;
                
                $img_product_id = $product_id;
                $img_url = $image_url;
                $img_is_primary = $is_primary;
                $img_sort_order = $current_sort + $uploaded;
                
                $image_stmt->bind_param("issi", $img_product_id, $img_url, $img_is_primary, $img_sort_order);
                $image_stmt->execute();
                $uploaded++;
            } else {
                $errors[] = "Ошибка сохранения: " . $_FILES['images']['name'][$key];
            }
        }
        $image_stmt->close();
    }
    
    if ($uploaded > 0) {
        $_SESSION['success'] = "Успешно загружено $uploaded изображений";
    }
    if (!empty($errors)) {
        $_SESSION['error'] = implode(", ", $errors);
    }
    
    header("Location: product_images.php?id=" . $product_id);
    exit;
}

// Обработка установки основного изображения
if (isset($_GET['set_primary']) && isset($_GET['image_id'])) {
    $image_id = (int)$_GET['image_id'];
    
    // Сбрасываем флаг is_primary для всех изображений товара
    $db->query("UPDATE product_images SET is_primary = 0 WHERE product_id = $product_id");
    
    // Устанавливаем новое основное изображение
    $db->query("UPDATE product_images SET is_primary = 1 WHERE id = $image_id AND product_id = $product_id");
    
    $_SESSION['success'] = "Основное изображение обновлено";
    header("Location: product_images.php?id=" . $product_id);
    exit;
}

// Обработка удаления изображения
if (isset($_GET['delete_image']) && isset($_GET['image_id'])) {
    $image_id = (int)$_GET['image_id'];
    
    // Получаем путь к файлу изображения
    $image = $db->query("SELECT image_url FROM product_images WHERE id = $image_id AND product_id = $product_id")->fetch_assoc();
    
    if ($image) {
        // Удаляем файл с диска
        $file_path = __DIR__ . '/../' . $image['image_url'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Удаляем запись из базы данных
        $db->query("DELETE FROM product_images WHERE id = $image_id AND product_id = $product_id");
        
        // Если удалили основное изображение, устанавливаем новое основное
        $has_primary = $db->query("SELECT id FROM product_images WHERE product_id = $product_id AND is_primary = 1")->num_rows;
        if ($has_primary == 0) {
            $db->query("UPDATE product_images SET is_primary = 1 WHERE product_id = $product_id ORDER BY sort_order ASC LIMIT 1");
        }
        
        $_SESSION['success'] = "Изображение удалено";
    }
    
    header("Location: product_images.php?id=" . $product_id);
    exit;
}

// Обработка сортировки изображений
if (isset($_POST['update_sort_order'])) {
    foreach ($_POST['sort_order'] as $image_id => $sort_order) {
        $image_id = (int)$image_id;
        $sort_order = (int)$sort_order;
        $db->query("UPDATE product_images SET sort_order = $sort_order WHERE id = $image_id AND product_id = $product_id");
    }
    $_SESSION['success'] = "Порядок изображений обновлен";
    header("Location: product_images.php?id=" . $product_id);
    exit;
}

// Получаем изображения товара (до header.php)
$images = $db->query("
    SELECT * FROM product_images 
    WHERE product_id = $product_id 
    ORDER BY is_primary DESC, sort_order ASC, id ASC
");

$image_count = $images->num_rows;

// Подключаем header ПОСЛЕ всей обработки данных
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="fas fa-images"></i> 
            Изображения товара: <?= htmlspecialchars($product['brand'] . ' ' . $product['name']) ?>
        </h1>
        <div class="d-flex gap-2">
            <a href="edit_product.php?id=<?= $product_id ?>" class="btn btn-primary" style="min-width: 180px;">
                <i class="fas fa-edit"></i> Редактировать товар
            </a>
            <a href="manage_products.php" class="btn btn-secondary" style="min-width: 150px;">
                <i class="fas fa-arrow-left"></i> Назад к списку
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-images"></i> Все изображения 
                        <span class="badge bg-secondary"><?= $image_count ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($image_count > 0): ?>
                        <form method="POST" class="mb-3">
                            <div class="row g-2">
                                <div class="col-md-8">
                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-info-circle"></i> 
                                        Измените порядок изображений и нажмите "Сохранить порядок"
                                    </div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button type="submit" name="update_sort_order" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Сохранить порядок
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row g-3 mt-2">
                                <?php while ($img = $images->fetch_assoc()): ?>
                                    <div class="col-md-4 col-lg-3" data-id="<?= $img['id'] ?>">
                                        <div class="card h-100">
                                            <div class="position-relative">
                                                <img src="/mobileshop/<?= $img['image_url'] ?>" 
                                                     class="card-img-top" 
                                                     style="height: 180px; object-fit: cover;"
                                                     alt="Изображение товара">
                                                <?php if ($img['is_primary']): ?>
                                                    <div class="position-absolute top-0 start-0 m-2">
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-star"></i> Основное
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-2">
                                                    <label class="form-label small">Порядок</label>
                                                    <input type="number" 
                                                           name="sort_order[<?= $img['id'] ?>]" 
                                                           value="<?= $img['sort_order'] ?>" 
                                                           class="form-control form-control-sm"
                                                           min="0" max="999">
                                                </div>
                                                <div class="d-flex justify-content-between gap-2">
                                                    <?php if (!$img['is_primary']): ?>
                                                        <a href="?id=<?= $product_id ?>&set_primary=1&image_id=<?= $img['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary w-100"
                                                           onclick="return confirm('Сделать это изображение основным?')">
                                                            <i class="fas fa-star"></i> Основное
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="?id=<?= $product_id ?>&delete_image=1&image_id=<?= $img['id'] ?>" 
                                                       class="btn btn-sm btn-outline-danger w-100"
                                                       onclick="return confirm('Удалить это изображение?')">
                                                        <i class="fas fa-trash"></i> Удалить
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info text-center py-5">
                            <i class="fas fa-image fa-3x mb-3"></i>
                            <p class="mb-0">У этого товара пока нет изображений.</p>
                            <p class="small">Загрузите изображения, чтобы сделать товар более привлекательным.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-upload"></i> Добавить изображения
                    </h5>
                </div>
                <div class="card-body">
                    <form action="product_images.php?id=<?= $product_id ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="upload_images" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Выберите изображения</label>
                            <input type="file" name="images[]" class="form-control" multiple accept="image/*" required>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Можно выбрать несколько изображений (JPG, PNG, GIF, WEBP).<br>
                                Первое изображение станет основным, если нет других.
                            </small>
                        </div>
                        
                        <div id="image-preview" class="row g-2 mb-3"></div>
                        
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-upload"></i> Загрузить изображения
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle"></i> Рекомендации
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success"></i> 
                            <strong>Основное изображение</strong> показывается в каталоге
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success"></i> 
                            Рекомендуемый размер: <strong>800x800 пикселей</strong>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success"></i> 
                            Максимальный размер файла: <strong>5 МБ</strong>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success"></i> 
                            Поддерживаемые форматы: <strong>JPG, PNG, GIF, WEBP</strong>
                        </li>
                        <li>
                            <i class="fas fa-check-circle text-success"></i> 
                            Первое загруженное изображение автоматически становится основным
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Предпросмотр изображений перед загрузкой
const imageInput = document.querySelector('input[name="images[]"]');
if (imageInput) {
    imageInput.addEventListener('change', function() {
        const preview = document.getElementById('image-preview');
        if (preview) {
            preview.innerHTML = '';
            
            Array.from(this.files).forEach((file, index) => {
                if (index >= 4) return; // Показываем не более 4 предпросмотров
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML += `
                        <div class="col-6 mb-2">
                            <div class="card">
                                <img src="${e.target.result}" class="card-img-top" style="height: 80px; object-fit: cover;">
                                <div class="card-body p-1 text-center">
                                    <small class="text-truncate d-block">${file.name.substring(0, 20)}</small>
                                </div>
                            </div>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            });
            
            if (this.files.length > 4) {
                preview.innerHTML += `<div class="col-12 text-center text-muted small">
                    <i class="fas fa-plus-circle"></i> и еще ${this.files.length - 4} файлов
                </div>`;
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>