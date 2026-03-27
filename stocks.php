<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

$items_per_page = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;
$type = isset($_GET['type']) && in_array($_GET['type'], ['all', 'phones', 'accessories']) ? $_GET['type'] : 'all';

// Подсчет общего количества товаров со скидками
$count_conditions = ["prom.is_active = 1", "prom.starts_at <= NOW()", "(prom.expires_at >= NOW() OR prom.expires_at IS NULL)"];
$count_params = [];
$count_types = '';

if ($type !== 'all') {
    $category_condition = $type === 'phones' ? "c.id = 1" : "c.id != 1";
    $count_conditions[] = $category_condition;
}

$count_sql = "
    SELECT COUNT(DISTINCT p.id) as count
    FROM promotions prom
    JOIN promotion_products pp ON pp.promotion_id = prom.id
    JOIN products p ON p.id = pp.product_id
    JOIN categories c ON c.id = p.category_id
    WHERE " . implode(" AND ", $count_conditions) . "
";

$count_stmt = $db->prepare($count_sql);
if (!empty($count_params)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_items / $items_per_page);

// Получение товаров со скидками - ИСПРАВЛЕННЫЙ ЗАПРОС С ИЗОБРАЖЕНИЯМИ
$conditions = ["prom.is_active = 1", "prom.starts_at <= NOW()", "(prom.expires_at >= NOW() OR prom.expires_at IS NULL)"];
$params = [$items_per_page, $offset];
$types = "ii";

if ($type !== 'all') {
    $category_condition = $type === 'phones' ? "c.id = 1" : "c.id != 1";
    $conditions[] = $category_condition;
}

$sql = "
    SELECT 
        p.id,
        p.name,
        p.brand,
        p.model,
        c.name as category_name,
        COALESCE(MIN(pv.price), p.price) as min_price,
        COALESCE(SUM(pv.quantity), p.quantity) as total_stock,
        COALESCE(
            (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
            (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1),
            'assets/no-image.png'
        ) as image_url,
        prom.name as promotion_name,
        prom.discount_type,
        COALESCE(pp.discount_percent, prom.discount_value) as discount_value,
        prom.starts_at,
        prom.expires_at
    FROM promotions prom
    JOIN promotion_products pp ON pp.promotion_id = prom.id
    JOIN products p ON p.id = pp.product_id
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN product_variations pv ON pv.product_id = p.id
    WHERE " . implode(" AND ", $conditions) . "
    GROUP BY p.id, prom.id
    ORDER BY prom.starts_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result();
?>

<section class="promotions-section py-4">
    <div class="container">
        <div class="catalog-header mb-4">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h2>Акции и скидки</h2>
                </div>
                <div class="col-md-8">
                    <div class="category-switcher d-flex gap-2">
                        <a href="stocks.php?type=all&page=1" 
                           class="btn <?= $type === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                            Все товары
                        </a>
                        <a href="stocks.php?type=phones&page=1" 
                           class="btn <?= $type === 'phones' ? 'btn-primary' : 'btn-outline-primary' ?>">
                            Смартфоны
                        </a>
                        <a href="stocks.php?type=accessories&page=1" 
                           class="btn <?= $type === 'accessories' ? 'btn-primary' : 'btn-outline-primary' ?>">
                            Аксессуары
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($products->num_rows > 0): ?>
            <div class="row">
                <?php while ($product = $products->fetch_assoc()): 
                    $discount = $product['discount_value'];
                    $discount_type = $product['discount_type'];
                    
                    if ($discount_type === 'percent') {
                        $new_price = $product['min_price'] * (100 - $discount) / 100;
                        $discount_text = "-{$discount}%";
                    } else {
                        $new_price = max(0, $product['min_price'] - $discount);
                        $discount_text = "-" . formatPrice($discount);
                    }
                    
                    $days_left = $product['expires_at'] 
                        ? ceil((strtotime($product['expires_at']) - time()) / 86400)
                        : null;
                ?>
                    <div class="col-md-4 mb-4">
                        <div class="promotion-card h-100" onclick="window.location.href='product.php?id=<?= $product['id']; ?>'">
                            <div class="position-relative">
                                <div class="product-image-wrapper text-center p-3" style="height: 200px; background: #f8f9fa;">
                                    <img src="<?= htmlspecialchars($product['image_url']); ?>" 
                                         alt="<?= htmlspecialchars($product['name']); ?>" 
                                         class="img-fluid" style="max-height: 180px; object-fit: contain;">
                                </div>
                                
                                <!-- Бейдж скидки -->
                                <div class="position-absolute top-0 end-0 m-3">
                                    <span class="badge bg-danger fs-6 p-2">
                                        <?= $discount_text ?>
                                    </span>
                                </div>
                                
                                <!-- Таймер, если акция временная -->
                                <?php if ($days_left && $days_left <= 30): ?>
                                    <div class="position-absolute bottom-0 start-0 m-3">
                                        <span class="badge bg-warning text-dark">
                                            <i class="far fa-clock me-1"></i>
                                            <?= $days_left ?> дн.
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="promotion-info p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h5 class="mb-1"><?= htmlspecialchars($product['brand'] . ' ' . $product['name']); ?></h5>
                                        <p class="text-muted small mb-2"><?= htmlspecialchars($product['category_name']) ?></p>
                                    </div>
                                </div>
                                
                                <div class="price-block mb-2">
                                    <span class="current-price text-danger fw-bold fs-5">
                                        <?= formatPrice($new_price); ?>
                                    </span>
                                    <span class="old-price ms-2 text-muted">
                                        <del><?= formatPrice($product['min_price']); ?></del>
                                    </span>
                                </div>
                                
                                <p class="promotion-name text-primary small mb-2">
                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($product['promotion_name']) ?>
                                </p>
                                
                                <p class="stock-info small <?= $product['total_stock'] > 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $product['total_stock'] > 0 ? 'В наличии: ' . $product['total_stock'] . ' шт.' : 'Нет в наличии' ?>
                                </p>
                                
                                <?php if ($product['total_stock'] > 0): ?>
                                    <form method="POST" action="pages/cart.php" class="cart-form mt-2" onclick="event.stopPropagation();">
                                        <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
                                        <div class="d-flex gap-2">
                                            <input type="number" name="quantity" min="1" max="<?= $product['total_stock']; ?>" value="1"
                                                   class="form-control form-control-sm" style="width: 70px;">
                                            <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                                                В корзину
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Пагинация -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container mt-4">
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="stocks.php?type=<?= $type ?>&page=<?= $page - 1; ?>">«</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="stocks.php?type=<?= $type ?>&page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="stocks.php?type=<?= $type ?>&page=<?= $page + 1; ?>">»</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-info text-center py-5">
                <i class="fas fa-gift fa-3x mb-3"></i>
                <h4>В данный момент нет активных акций</h4>
                <p class="mb-0">Загляните позже, мы постоянно обновляем наши предложения!</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
.promotion-card {
    cursor: pointer;
    border: 1px solid #eee;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.promotion-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    border-color: #ddd;
}
.product-image-wrapper {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.promotion-info {
    flex-grow: 1;
}
.category-switcher .btn {
    margin-bottom: 5px;
}
</style>

<?php require_once __DIR__ . '/inc/footer.php'; ?>