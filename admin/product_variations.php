<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Проверка ID товара
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    header("Location: manage_products.php");
    exit;
}

// Получаем информацию о товаре
$product_query = $db->prepare("
    SELECT p.id, p.name, p.brand, p.model, p.type, p.price as base_price, p.quantity as base_quantity,
           c.name as category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.id = ?
");
$product_query->bind_param('i', $product_id);
$product_query->execute();
$product = $product_query->get_result()->fetch_assoc();

if (!$product) {
    header("Location: manage_products.php");
    exit;
}

// ========== ОБРАБОТКА УДАЛЕНИЯ ВАРИАНТА (ДО header.php) ==========
if (isset($_GET['delete_var']) && is_numeric($_GET['delete_var'])) {
    $var_id = (int)$_GET['delete_var'];
    
    // Получаем путь к изображению для удаления
    $img_query = $db->prepare("SELECT image_url FROM product_variations WHERE id = ? AND product_id = ?");
    $img_query->bind_param('ii', $var_id, $product_id);
    $img_query->execute();
    $img = $img_query->get_result()->fetch_assoc();
    
    if ($img && !empty($img['image_url'])) {
        $file_path = __DIR__ . '/../' . $img['image_url'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    $delete = $db->prepare("DELETE FROM product_variations WHERE id = ? AND product_id = ?");
    $delete->bind_param('ii', $var_id, $product_id);
    $delete->execute();
    
    $_SESSION['success'] = "Вариант удален";
    header("Location: product_variations.php?id=$product_id");
    exit;
}

// Получаем список вариантов товара
$variations_query = $db->prepare("
    SELECT id, sku, price, old_price, quantity, image_url, created_at, updated_at
    FROM product_variations
    WHERE product_id = ?
    ORDER BY price ASC
");
$variations_query->bind_param('i', $product_id);
$variations_query->execute();
$variations = $variations_query->get_result();

$total_stock = 0;
$price_min = null;
$price_max = null;
$variations_list = [];

while ($var = $variations->fetch_assoc()) {
    $total_stock += $var['quantity'];
    if ($price_min === null || $var['price'] < $price_min) $price_min = $var['price'];
    if ($price_max === null || $var['price'] > $price_max) $price_max = $var['price'];
    $variations_list[] = $var;
}

// Подключаем header ПОСЛЕ всей обработки
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Управление вариантами товара</h1>
        <div class="d-flex gap-2">
            <a href="add_variation.php?product_id=<?= $product_id ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> Добавить вариант
            </a>
            <a href="edit_product.php?id=<?= $product_id ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Назад к товару
            </a>
        </div>
    </div>

    <!-- Информация о товаре -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Информация о товаре</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Название:</strong> <?= htmlspecialchars($product['brand'] . ' ' . $product['name'] . ' ' . $product['model']) ?></p>
                    <p><strong>Категория:</strong> <?= htmlspecialchars($product['category_name']) ?></p>
                    <p><strong>Тип товара:</strong> <?= $product['type'] === 'variable' ? 'С вариантами' : 'Простой' ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Базовая цена:</strong> <?= formatPrice($product['base_price']) ?></p>
                    <p><strong>Базовое количество:</strong> <?= $product['base_quantity'] ?> шт.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Статистика вариантов -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h6 class="card-title">Всего вариантов</h6>
                    <h3 class="mb-0"><?= count($variations_list) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6 class="card-title">Общее количество</h6>
                    <h3 class="mb-0"><?= $total_stock ?> шт.</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6 class="card-title">Диапазон цен</h6>
                    <h3 class="mb-0">
                        <?php if ($price_min && $price_max): ?>
                            <?= formatPrice($price_min) ?> - <?= formatPrice($price_max) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Список вариантов -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Список вариантов</h5>
        </div>
        <div class="card-body">
            <?php if (empty($variations_list)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-3">У этого товара пока нет вариантов</p>
                    <a href="add_variation.php?product_id=<?= $product_id ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Добавить первый вариант
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Изображение</th>
                                <th>Артикул (SKU)</th>
                                <th>Цена</th>
                                <th>Старая цена</th>
                                <th>Количество</th>
                                <th>Дата создания</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($variations_list as $var): ?>
                                <tr>
                                    <td><?= $var['id'] ?></td>
                                    <td>
                                        <?php if (!empty($var['image_url'])): ?>
                                            <img src="<?= $base_url . htmlspecialchars($var['image_url']) ?>" 
                                                 alt="Вариант" style="width: 50px; height: 50px; object-fit: contain;">
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?= htmlspecialchars($var['sku']) ?></code>
                                    </td>
                                    <td class="fw-bold text-primary">
                                        <?= formatPrice($var['price']) ?>
                                    </td>
                                    <td>
                                        <?php if ($var['old_price']): ?>
                                            <span class="text-muted"><del><?= formatPrice($var['old_price']) ?></del></span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $var['quantity'] > 0 ? 'bg-success' : 'bg-danger' ?>">
                                            <?= $var['quantity'] ?> шт.
                                        </span>
                                    </td>
                                    <td>
                                        <?= date('d.m.Y H:i', strtotime($var['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="edit_variation.php?id=<?= $var['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="product_variations.php?id=<?= $product_id ?>&delete_var=<?= $var['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Удалить этот вариант?')"
                                               title="Удалить">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3"><strong>Итого:</strong></td>
                                <td colspan="2"><strong>Диапазон:</strong> <?= formatPrice($price_min) ?> - <?= formatPrice($price_max) ?></td>
                                <td><strong><?= $total_stock ?> шт.</strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Совет:</strong> Если товар имеет разные характеристики (цвет, размер, память), создайте отдельный вариант для каждой комбинации.
                    Покупатели смогут выбирать нужный вариант на странице товара.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.admin-container .card {
    margin-bottom: 1rem;
}
.table td {
    vertical-align: middle;
}
</style>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>