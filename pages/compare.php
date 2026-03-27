<?php
session_start();
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// ========== ПОЛУЧЕНИЕ ID ДЛЯ СРАВНЕНИЯ ==========
$compare_ids = [];

// 1. Сначала проверяем GET параметры (самый приоритетный источник)
if (isset($_GET['ids']) && !empty($_GET['ids'])) {
    $compare_ids = explode(',', $_GET['ids']);
    $compare_ids = array_filter($compare_ids, 'is_numeric');
    $compare_ids = array_map('intval', $compare_ids);
    $compare_ids = array_unique($compare_ids);
    if (count($compare_ids) > 4) {
        $compare_ids = array_slice($compare_ids, 0, 4);
    }
    // Сохраняем в сессию
    $_SESSION['compare_ids'] = $compare_ids;
}
// 2. Если нет GET, проверяем сессию
else if (isset($_SESSION['compare_ids']) && !empty($_SESSION['compare_ids'])) {
    $compare_ids = $_SESSION['compare_ids'];
}
// 3. Если пользователь авторизован, загружаем из базы
else if (isset($_SESSION['id'])) {
    $user_id = $_SESSION['id'];
    $result = $db->query("SELECT product_id FROM `compare` WHERE user_id = $user_id ORDER BY created_at DESC");
    while ($row = $result->fetch_assoc()) {
        $compare_ids[] = $row['product_id'];
    }
    $_SESSION['compare_ids'] = $compare_ids;
}

// Ограничиваем 4 товарами
$compare_ids = array_slice($compare_ids, 0, 4);

// Если нет товаров, показываем сообщение
if (empty($compare_ids)) {
    echo '<div class="container py-5">';
    echo '<div class="alert alert-info text-center">';
    echo '<i class="fas fa-exchange-alt fa-3x mb-3"></i>';
    echo '<h4>Нет товаров для сравнения</h4>';
    echo '<p>Выберите товары на странице каталога или в карточке товара, чтобы добавить их в сравнение.</p>';
    echo '<a href="../index.php" class="btn btn-primary mt-3">Перейти в каталог</a>';
    echo '</div>';
    echo '</div>';
    require_once __DIR__ . '/../inc/footer.php';
    exit;
}

// ========== ПОЛУЧЕНИЕ ДАННЫХ О ТОВАРАХ ==========
$products = [];
$all_attributes = [];

$placeholders = implode(',', array_fill(0, count($compare_ids), '?'));
$types = str_repeat('i', count($compare_ids));

$query = $db->prepare("
    SELECT 
        p.id,
        p.name,
        p.brand,
        p.model,
        p.description,
        p.rating,
        p.sales_count,
        COALESCE(MIN(pv.price), p.price) as min_price,
        COALESCE(MAX(pv.price), p.price) as max_price,
        COALESCE(SUM(pv.quantity), p.quantity) as total_stock,
        COALESCE(
            (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
            (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1),
            'assets/no-image.png'
        ) as image_url,
        c.name as category_name
    FROM products p
    LEFT JOIN product_variations pv ON pv.product_id = p.id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.id IN ($placeholders) AND p.status = 'active'
    GROUP BY p.id
");

$query->bind_param($types, ...$compare_ids);
$query->execute();
$result = $query->get_result();

while ($row = $result->fetch_assoc()) {
    // Исправляем путь к изображению
    $image_path = $row['image_url'];
    if (empty($image_path) || $image_path == 'assets/no-image.png') {
        $image_path = '/mobileshop/assets/no-image.png';
    } elseif (strpos($image_path, 'assets/') === 0 || strpos($image_path, 'uploads/') === 0) {
        $image_path = '/mobileshop/' . $image_path;
    } elseif (!strpos($image_path, '/')) {
        $image_path = '/mobileshop/uploads/' . $image_path;
    } else {
        $image_path = '/mobileshop/' . $image_path;
    }
    $row['image_url'] = $image_path;
    $products[$row['id']] = $row;
    
    // Получаем атрибуты
    $attr_query = $db->prepare("
        SELECT a.id, a.name, a.slug, a.type, a.unit,
               pa.value_text, pa.value_number, pa.value_boolean
        FROM product_attributes pa
        JOIN attributes a ON a.id = pa.attribute_id
        WHERE pa.product_id = ? AND a.is_visible_on_product = 1
        ORDER BY a.sort_order
    ");
    $attr_query->bind_param('i', $row['id']);
    $attr_query->execute();
    $attr_result = $attr_query->get_result();
    
    while ($attr = $attr_result->fetch_assoc()) {
        $value = '';
        if ($attr['value_text']) {
            $value = $attr['value_text'];
        } elseif ($attr['value_number']) {
            $value = $attr['value_number'] . ($attr['unit'] ? ' ' . $attr['unit'] : '');
        } elseif ($attr['value_boolean']) {
            $value = $attr['value_boolean'] ? 'Да' : 'Нет';
        }
        
        $products[$row['id']]['attributes'][$attr['slug']] = [
            'name' => $attr['name'],
            'value' => $value,
            'unit' => $attr['unit']
        ];
        $all_attributes[$attr['slug']] = $attr['name'];
    }
    $attr_query->close();
}
?>

<div class="container compare-container py-4">
    <h1 class="mb-4">
        <i class="fas fa-exchange-alt me-2"></i>Сравнение товаров
        <small class="text-muted fs-6">(<?= count($products) ?> товаров)</small>
    </h1>
    
    <div class="row mb-4">
        <div class="col-12">
            <div class="btn-group">
                <a href="../index.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Назад в каталог
                </a>
                <button onclick="window.print()" class="btn btn-outline-secondary">
                    <i class="fas fa-print"></i> Печать
                </button>
                <button class="btn btn-outline-danger" id="clear-compare-btn" onclick="clearAllCompare()">
                    <i class="fas fa-trash"></i> Очистить сравнение
                </button>
            </div>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-bordered compare-table">
            <thead class="table-light">
                <tr>
                    <th style="width: 200px;">Характеристика</th>
                    <?php foreach ($products as $product): ?>
                        <th class="text-center" style="min-width: 250px;">
                            <div class="position-relative">
                                <a href="../product.php?id=<?= $product['id'] ?>" class="text-decoration-none">
                                    <img src="<?= htmlspecialchars($product['image_url']); ?>" 
                                         alt="<?= htmlspecialchars($product['name']); ?>"
                                         class="img-fluid mb-2" style="max-height: 150px; object-fit: contain;">
                                    <h5 class="mt-2"><?= htmlspecialchars($product['brand'] . ' ' . $product['name']); ?></h5>
                                    <p class="text-muted small"><?= htmlspecialchars($product['model']); ?></p>
                                </a>
                                <button class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 remove-from-compare"
                                        data-product-id="<?= $product['id'] ?>"
                                        onclick="removeFromCompare(<?= $product['id'] ?>)"
                                        title="Удалить из сравнения">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="fw-bold">Цена</td>
                    <?php foreach ($products as $product): ?>
                        <td class="text-center">
                            <?php 
                            $price = $product['min_price'] == $product['max_price'] 
                                ? formatPrice($product['min_price'])
                                : 'от ' . formatPrice($product['min_price']) . ' до ' . formatPrice($product['max_price']);
                            ?>
                            <span class="fs-5 fw-bold text-primary"><?= $price ?></span>
                        </td>
                    <?php endforeach; ?>
                </tr>
                
                <tr>
                    <td class="fw-bold">Наличие</td>
                    <?php foreach ($products as $product): ?>
                        <td class="text-center">
                            <?php if ($product['total_stock'] > 0): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle"></i> В наличии (<?= $product['total_stock'] ?> шт.)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger">
                                    <i class="fas fa-times-circle"></i> Нет в наличии
                                </span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                
                <tr>
                    <td class="fw-bold">Рейтинг</td>
                    <?php foreach ($products as $product): ?>
                        <td class="text-center">
                            <div class="rating-stars">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= round($product['rating']) ? '' : '-o' ?> text-warning"></i>
                                <?php endfor; ?>
                                <span class="text-muted ms-1">(<?= number_format($product['rating'], 1) ?>)</span>
                            </div>
                            <div class="small text-muted">Продано: <?= $product['sales_count'] ?> шт.</div>
                        </td>
                    <?php endforeach; ?>
                </tr>
                
                <tr>
                    <td class="fw-bold">Категория</td>
                    <?php foreach ($products as $product): ?>
                        <td class="text-center">
                            <?= htmlspecialchars($product['category_name']) ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                
                <?php foreach ($all_attributes as $slug => $name): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($name) ?></td>
                    <?php foreach ($products as $product): ?>
                        <td class="text-center">
                            <?php 
                            $value = isset($product['attributes'][$slug]['value']) && !empty($product['attributes'][$slug]['value'])
                                ? htmlspecialchars($product['attributes'][$slug]['value'])
                                : '<span class="text-muted">—</span>';
                            echo $value;
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                
                <tr>
                    <td class="fw-bold">Описание</td>
                    <?php foreach ($products as $product): ?>
                        <td class="small">
                            <?= nl2br(htmlspecialchars(substr($product['description'] ?? '', 0, 200))) ?>
                            <?php if (strlen($product['description'] ?? '') > 200): ?>
                                <a href="../product.php?id=<?= $product['id'] ?>" class="small">Подробнее</a>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                
                <tr>
                    <td class="fw-bold">Действия</td>
                    <?php foreach ($products as $product): ?>
                        <td class="text-center">
                            <?php if ($product['total_stock'] > 0): ?>
                                <form method="POST" action="../pages/cart.php" class="d-inline">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <input type="number" name="quantity" value="1" min="1" max="<?= $product['total_stock'] ?>" 
                                           class="form-control form-control-sm d-inline-block w-auto me-1" style="width: 60px;">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-shopping-cart"></i> В корзину
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">Нет в наличии</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>
    
    <?php if (count($products) < 4): ?>
        <div class="alert alert-info mt-4">
            <i class="fas fa-info-circle me-2"></i>
            Вы можете добавить еще <?= 4 - count($products) ?> товара для сравнения.
            <a href="../index.php" class="alert-link">Выбрать товары</a>
        </div>
    <?php endif; ?>
</div>

<style>
.compare-table th,
.compare-table td {
    vertical-align: middle;
    padding: 15px;
}
.compare-table img {
    max-width: 100%;
    height: auto;
    max-height: 150px;
    object-fit: contain;
}
.rating-stars {
    white-space: nowrap;
}
.rating-stars i {
    font-size: 14px;
}
@media print {
    .btn-group,
    .alert-info,
    .position-absolute {
        display: none !important;
    }
    .compare-table th,
    .compare-table td {
        border: 1px solid #000;
    }
}
</style>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>