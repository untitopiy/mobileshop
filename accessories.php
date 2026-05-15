<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';

// Получаем категории аксессуаров (исключая смартфоны)
$catQuery = $db->query("
    SELECT id, name 
    FROM categories 
    WHERE id IN (
        SELECT DISTINCT category_id 
        FROM products 
        WHERE type IN ('simple', 'variable')
    )
    ORDER BY name
");
$accessory_categories = $catQuery->fetch_all(MYSQLI_ASSOC);

$selected_category = isset($_GET['cat']) ? (int)$_GET['cat'] : (!empty($accessory_categories) ? $accessory_categories[0]['id'] : 0);

$items_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Подсчет общего количества товаров в категории
$countQuery = $db->prepare("
    SELECT COUNT(DISTINCT p.id) as count
    FROM products p
    JOIN product_variations pv ON pv.product_id = p.id
    WHERE p.category_id = ? AND p.status = 'active'
");
$countQuery->bind_param('i', $selected_category);
$countQuery->execute();
$total_items = $countQuery->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_items / $items_per_page);

// Получение товаров с акциями
$query = $db->prepare("
    SELECT 
        p.id, p.name, p.brand, p.model, p.type,
        MIN(pv.price) as min_price,
        MAX(pv.price) as max_price,
        SUM(pv.quantity) as total_stock,
        (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_url,
        prom.discount_percent,
        prom.name as promotion_name
    FROM products p
    JOIN product_variations pv ON pv.product_id = p.id
    LEFT JOIN promotion_products pp ON pp.product_id = p.id
    LEFT JOIN promotions prom ON prom.id = pp.promotion_id 
        AND prom.is_active = 1 
        AND prom.starts_at <= NOW() 
        AND (prom.expires_at >= NOW() OR prom.expires_at IS NULL)
    WHERE p.category_id = ? AND p.status = 'active'
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$query->bind_param('iii', $selected_category, $items_per_page, $offset);
$query->execute();
$result = $query->get_result();
?>

<section class="catalog" id="catalog">
    <div class="container">
        <div class="catalog-header mb-4">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h2>Аксессуары</h2>
                </div>
                <div class="col-md-8">
                    <?php if (!empty($accessory_categories)): ?>
                        <div class="category-switcher d-flex flex-wrap gap-2">
                            <?php foreach ($accessory_categories as $cat): ?>
                                <a href="accessories.php?cat=<?= $cat['id']; ?>&page=1" 
                                   class="btn <?= ($cat['id'] == $selected_category) ? 'btn-primary' : 'btn-outline-primary' ?>">
                                    <?= htmlspecialchars($cat['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="catalog-items">
            <?php if ($result->num_rows > 0): ?>
                <div class="row">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                            $price_display = $row['min_price'] == $row['max_price'] 
                                ? formatPrice($row['min_price'])
                                : 'от ' . formatPrice($row['min_price']);
                            
                            $has_promo = $row['discount_percent'] !== null;
                            $promo_price = $has_promo 
                                ? $row['min_price'] * (100 - $row['discount_percent']) / 100 
                                : $row['min_price'];
                        ?>
                        <div class="col-md-4 mb-4">
                            <div class="accessory-card h-100" onclick="window.location.href='product.php?id=<?= $row['id']; ?>'">
                                <div class="accessory-image-wrapper text-center p-3">
                                    <?php if (!empty($row['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($row['image_url']); ?>" 
                                             alt="<?= htmlspecialchars($row['brand'] . ' ' . $row['name']); ?>"
                                             class="img-fluid" style="max-height: 150px; object-fit: contain;">
                                    <?php else: ?>
                                        <img src="assets/no-image.png" alt="Нет изображения" class="img-fluid" style="max-height: 150px;">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="accessory-info p-3">
                                    <h5 class="accessory-title"><?= htmlspecialchars($row['brand'] . ' ' . $row['name']); ?></h5>
                                    <p class="accessory-model text-muted small"><?= htmlspecialchars($row['model']); ?></p>
                                    
                                    <div class="price-block mb-2">
                                        <?php if ($has_promo): ?>
                                            <span class="current-price text-danger fw-bold">
                                                <?= formatPrice($promo_price); ?>
                                            </span>
                                            <span class="old-price ms-2">
                                                <del><?= formatPrice($row['min_price']); ?></del>
                                            </span>
                                            <span class="badge bg-danger ms-2">-<?= $row['discount_percent'] ?>%</span>
                                        <?php else: ?>
                                            <span class="current-price fw-bold"><?= $price_display; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <p class="accessory-stock small <?= $row['total_stock'] > 0 ? 'text-success' : 'text-danger' ?>">
                                        <i class="fas <?= $row['total_stock'] > 0 ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                        <?= $row['total_stock'] > 0 ? 'В наличии: ' . $row['total_stock'] . ' шт.' : 'Нет в наличии' ?>
                                    </p>
                                    
                                    <?php if (!empty($row['promotion_name'])): ?>
                                        <p class="promotion-label small text-primary">
                                            <i class="fas fa-tag"></i> <?= htmlspecialchars($row['promotion_name']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center py-5">
                    <i class="fas fa-info-circle fa-3x mb-3"></i>
                    <p class="mb-0">Нет товаров в этой категории.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Пагинация -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container mt-4">
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="accessories.php?cat=<?= $selected_category; ?>&page=<?= $page - 1; ?>">«</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="accessories.php?cat=<?= $selected_category; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="accessories.php?cat=<?= $selected_category; ?>&page=<?= $page + 1; ?>">»</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
.accessory-card {
    cursor: pointer;
    border: 1px solid #eee;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s;
}
.accessory-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-color: #ddd;
}
.accessory-image-wrapper {
    background: #f8f9fa;
    height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.category-switcher .btn {
    margin-bottom: 5px;
}
</style>

<?php require_once __DIR__ . '/inc/footer.php'; ?>