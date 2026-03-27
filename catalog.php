<?php
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

// Параметры фильтрации
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Настройка пагинации
$items_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

// Построение WHERE условий
$where_conditions = ["p.status = 'active'"];
$params = [];
$types = "";

if ($category_id > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_id;
    $types .= "i";
}

if ($min_price > 0) {
    $where_conditions[] = "COALESCE(pv.price, p.price) >= ?";
    $params[] = $min_price;
    $types .= "d";
}

if ($max_price > 0) {
    $where_conditions[] = "COALESCE(pv.price, p.price) <= ?";
    $params[] = $max_price;
    $types .= "d";
}

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.brand LIKE ? OR p.model LIKE ? OR p.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

$where_clause = implode(" AND ", $where_conditions);

// Определение сортировки
$order_by = match($sort) {
    'price_asc' => "min_price ASC",
    'price_desc' => "min_price DESC",
    'name_asc' => "p.name ASC",
    'name_desc' => "p.name DESC",
    'popular' => "p.sales_count DESC",
    'rated' => "p.rating DESC",
    default => "p.created_at DESC"
};

// Подсчет общего количества - ИСПРАВЛЕНО на LEFT JOIN
$count_sql = "
    SELECT COUNT(DISTINCT p.id) as count
    FROM products p
    LEFT JOIN product_variations pv ON pv.product_id = p.id
    WHERE $where_clause
";
$count_stmt = $db->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_items = $count_result->fetch_assoc()['count'];
$total_pages = ceil($total_items / $items_per_page);

// Получение товаров
$sql = "
    SELECT
        p.id,
        p.name,
        p.brand,
        p.model,
        p.type,
        p.sales_count,
        p.rating,
        COALESCE(MIN(pv.price), p.price) as min_price,
        COALESCE(MAX(pv.price), p.price) as max_price,
        COALESCE(SUM(pv.quantity), p.quantity) as total_stock,
        COALESCE(
            (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
            (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1),
            'assets/no-image.png'
        ) as image_url,
        CASE 
            WHEN prom.discount_type = 'percent' THEN prom.discount_value
            ELSE NULL
        END as discount_percent,
        prom.name as promotion_name,
        prom.discount_type,
        prom.discount_value,
        c.name as category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN product_variations pv ON pv.product_id = p.id
    LEFT JOIN promotion_products pp ON pp.product_id = p.id
    LEFT JOIN promotions prom ON prom.id = pp.promotion_id 
        AND prom.is_active = 1 
        AND prom.starts_at <= NOW() 
        AND (prom.expires_at >= NOW() OR prom.expires_at IS NULL)
    WHERE $where_clause
    GROUP BY p.id
    ORDER BY $order_by
    LIMIT ? OFFSET ?
";

$stmt = $db->prepare($sql);
$params[] = $items_per_page;
$params[] = $offset;
$types .= "ii";

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// Получаем все категории для фильтра
$categories_query = $db->query("
    SELECT c.id, c.name, 
           (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 'active') as product_count
    FROM categories c
    WHERE c.is_active = 1
    ORDER BY c.name
");

// Получаем диапазон цен
$price_range_query = $db->query("
    SELECT MIN(COALESCE(pv.price, p.price)) as min_price, 
           MAX(COALESCE(pv.price, p.price)) as max_price
    FROM products p
    LEFT JOIN product_variations pv ON pv.product_id = p.id
    WHERE p.status = 'active'
");
$price_range = $price_range_query->fetch_assoc();
?>

<div class="container catalog-container py-4">
    <!-- Хлебные крошки -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
            <li class="breadcrumb-item active">Каталог товаров</li>
        </ol>
    </nav>

    <div class="row">
        <!-- Боковая панель с фильтрами -->
        <div class="col-md-3">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h5 class="mb-0">Фильтры</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="catalog.php" id="filter-form">
                        <!-- Поиск -->
                        <div class="mb-3">
                            <label class="form-label">Поиск</label>
                            <input type="text" name="search" class="form-control" 
                                   value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Название, бренд...">
                        </div>

                        <!-- Категории -->
                        <div class="mb-3">
                            <label class="form-label">Категория</label>
                            <select name="category" class="form-select">
                                <option value="0">Все категории</option>
                                <?php while ($cat = $categories_query->fetch_assoc()): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?> (<?= $cat['product_count'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Цена -->
                        <div class="mb-3">
                            <label class="form-label">Цена</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" name="min_price" class="form-control" 
                                           placeholder="От" value="<?= $min_price ?: '' ?>"
                                           min="<?= floor($price_range['min_price'] ?? 0) ?>">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="max_price" class="form-control" 
                                           placeholder="До" value="<?= $max_price ?: '' ?>"
                                           max="<?= ceil($price_range['max_price'] ?? 100000) ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Сортировка -->
                        <div class="mb-3">
                            <label class="form-label">Сортировка</label>
                            <select name="sort" class="form-select">
                                <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Сначала новые</option>
                                <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : '' ?>>Сначала дешевые</option>
                                <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>Сначала дорогие</option>
                                <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>По названию (А-Я)</option>
                                <option value="name_desc" <?= $sort == 'name_desc' ? 'selected' : '' ?>>По названию (Я-А)</option>
                                <option value="popular" <?= $sort == 'popular' ? 'selected' : '' ?>>Популярные</option>
                                <option value="rated" <?= $sort == 'rated' ? 'selected' : '' ?>>По рейтингу</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Применить фильтры
                        </button>
                        
                        <a href="catalog.php" class="btn btn-outline-secondary w-100 mt-2">
                            <i class="fas fa-times me-2"></i>Сбросить фильтры
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <!-- Список товаров -->
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Каталог товаров</h2>
                <span class="text-muted">Найдено: <?= $total_items ?> товаров</span>
            </div>

            <?php if ($products->num_rows > 0): ?>
                <div class="row">
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <?php
                            $price_display = $product['min_price'] == $product['max_price'] 
                                ? formatPrice($product['min_price'])
                                : 'от ' . formatPrice($product['min_price']);
                            
                            // Проверяем наличие скидки
                            $has_promo = false;
                            $promo_price = $product['min_price'];
                            $discount_text = '';
                            
                            if ($product['discount_percent'] !== null && $product['discount_percent'] > 0) {
                                $has_promo = true;
                                $promo_price = $product['min_price'] * (100 - $product['discount_percent']) / 100;
                                $discount_text = '-' . $product['discount_percent'] . '%';
                            } elseif ($product['discount_type'] === 'fixed' && $product['discount_value'] > 0) {
                                $has_promo = true;
                                $promo_price = max(0, $product['min_price'] - $product['discount_value']);
                                $discount_text = '- ' . formatPrice($product['discount_value']);
                            }
                        ?>
                        <div class="col-md-4 mb-4">
                            <div class="product-card h-100" onclick="location.href='product.php?id=<?= $product['id']; ?>'">
                                <div class="product-image-wrapper">
                                    <img src="<?= htmlspecialchars($product['image_url'] ?: 'assets/no-image.png'); ?>"
                                         alt="<?= htmlspecialchars($product['name']); ?>" class="product-image">
                                    <?php if ($has_promo): ?>
                                        <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                                            <?= $discount_text ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-info p-3">
                                    <h6 class="product-title"><?= htmlspecialchars($product['brand'] . ' ' . $product['name']); ?></h6>
                                    <p class="product-model text-muted small mb-2"><?= htmlspecialchars($product['model']); ?></p>
                                    
                                    <!-- Рейтинг -->
                                    <?php if ($product['rating'] > 0): ?>
                                        <div class="rating-stars small mb-2">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i <= round($product['rating']) ? '' : '-o' ?> text-warning"></i>
                                            <?php endfor; ?>
                                            <small class="text-muted">(<?= $product['sales_count'] ?>)</small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="product-price-block mb-2">
                                        <?php if ($has_promo): ?>
                                            <span class="current-price fw-bold text-danger">
                                                <?= formatPrice($promo_price); ?>
                                            </span>
                                            <span class="old-price ms-2">
                                                <del><?= formatPrice($product['min_price']); ?></del>
                                            </span>
                                        <?php else: ?>
                                            <span class="current-price fw-bold"><?= $price_display; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <p class="product-stock small <?= $product['total_stock'] > 0 ? 'text-success' : 'text-danger' ?>">
                                        <i class="fas <?= $product['total_stock'] > 0 ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                        <?= $product['total_stock'] > 0 ? 'В наличии' : 'Нет в наличии' ?>
                                    </p>
                                    
                                    <?php if (!empty($product['promotion_name'])): ?>
                                        <p class="promotion-label small text-primary mb-2">
                                            <i class="fas fa-tag"></i> <?= htmlspecialchars($product['promotion_name']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Пагинация -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5 bg-light rounded">
                    <i class="fas fa-search fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">Товары не найдены</h4>
                    <p class="mb-3">Попробуйте изменить параметры фильтрации</p>
                    <a href="catalog.php" class="btn btn-primary">Сбросить фильтры</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.product-card {
    cursor: pointer;
    border: 1px solid #eee;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s;
}
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    border-color: #ddd;
}
.product-image-wrapper {
    position: relative;
    height: 180px;
    overflow: hidden;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
}
.product-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
.rating-stars i {
    font-size: 12px;
}
.sticky-top {
    z-index: 100;
}
</style>

<?php require_once __DIR__ . '/inc/footer.php'; ?>