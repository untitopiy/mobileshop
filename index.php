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

// Подсчет общего количества
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

// Запрос товаров
$sql = "
    SELECT
        p.id, p.name, p.brand, p.model, p.type,
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
        prom.name as promotion_name, prom.discount_type, prom.discount_value,
        c.name as category_name,
        p.sales_count,
        p.rating
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
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
$result = $stmt->get_result();

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

// Получаем хиты продаж
$bestsellers_query = $db->query("
    SELECT p.id, p.name, p.brand, p.model,
           COALESCE(MIN(pv.price), p.price) as price,
           COALESCE(
               (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
               (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1),
               'assets/no-image.png'
           ) as image_url,
           p.sales_count,
           COALESCE(SUM(pv.quantity), p.quantity) as total_stock
    FROM products p
    LEFT JOIN product_variations pv ON pv.product_id = p.id
    WHERE p.status = 'active'
    GROUP BY p.id
    ORDER BY p.sales_count DESC
    LIMIT 8
");
$bestsellers = $bestsellers_query->fetch_all(MYSQLI_ASSOC);

// Получаем первые вариации для каждого товара
$first_variations = [];
$var_query = $db->query("
    SELECT product_id, id as variation_id, quantity 
    FROM product_variations 
    WHERE (product_id, id) IN (
        SELECT product_id, MIN(id) 
        FROM product_variations 
        GROUP BY product_id
    )
");
while ($v = $var_query->fetch_assoc()) {
    $first_variations[$v['product_id']] = $v;
}
// --- РЕКОМЕНДАЦИИ "ВАМ МОЖЕТ ПОНРАВИТЬСЯ" ---
$recommended_for_you = [];
$session_id_idx = session_id();
$user_id_idx = isset($_SESSION['id']) ? (int)$_SESSION['id'] : null;

if ($user_id_idx) {
    // Для авторизованных: по истории просмотров (последние 30 дней) + покупок
    $rfy_query = $db->prepare("
        SELECT DISTINCT p.id, p.name, p.brand, p.model,
               COALESCE(MIN(pv_var.price), p.price) as display_price,
               COALESCE(
                   (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
                   (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1),
                   'assets/no-image.png'
               ) AS image_url,
               CASE WHEN prom_r.discount_type = 'percent' THEN prom_r.discount_value ELSE NULL END as promo_discount,
               p.rating,
               p.sales_count,
               'viewed' as source
        FROM product_views pvw
        JOIN products viewed_p ON viewed_p.id = pvw.product_id
        JOIN products p ON p.category_id = viewed_p.category_id
            AND p.id != pvw.product_id
            AND p.status = 'active'
        LEFT JOIN product_variations pv_var ON pv_var.product_id = p.id
        LEFT JOIN promotion_products pp_r ON pp_r.product_id = p.id
        LEFT JOIN promotions prom_r ON prom_r.id = pp_r.promotion_id
            AND prom_r.is_active = 1
            AND (prom_r.starts_at <= NOW() OR prom_r.starts_at IS NULL)
            AND (prom_r.expires_at >= NOW() OR prom_r.expires_at IS NULL)
        WHERE pvw.user_id = ?
          AND pvw.viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY p.id
        ORDER BY p.rating DESC, p.sales_count DESC
        LIMIT 6
    ");
    if (!$rfy_query) {
        error_log("RFY prepare error: " . $db->error);
        $recommended_for_you = [];
    } else {
        $rfy_query->bind_param('i', $user_id_idx);
        $rfy_query->execute();
        $res = $rfy_query->get_result();
        $recommended_for_you = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
}

// Для гостей / если авторизован, но нет истории — топ по популярности за неделю
if (empty($recommended_for_you)) {
    $rfy_fallback = $db->query("
        SELECT p.id, p.name, p.brand, p.model,
               COALESCE(MIN(pv_var.price), p.price) as display_price,
               COALESCE(
                   (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
                   (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1),
                   'assets/no-image.png'
               ) AS image_url,
               CASE WHEN prom_r.discount_type = 'percent' THEN prom_r.discount_value ELSE NULL END as promo_discount,
               COUNT(pvw.id) as view_count
        FROM products p
        LEFT JOIN product_variations pv_var ON pv_var.product_id = p.id
        LEFT JOIN product_views pvw ON pvw.product_id = p.id
            AND pvw.viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        LEFT JOIN promotion_products pp_r ON pp_r.product_id = p.id
        LEFT JOIN promotions prom_r ON prom_r.id = pp_r.promotion_id
            AND prom_r.is_active = 1
            AND (prom_r.starts_at <= NOW() OR prom_r.starts_at IS NULL)
            AND (prom_r.expires_at >= NOW() OR prom_r.expires_at IS NULL)
        WHERE p.status = 'active'
        GROUP BY p.id
        ORDER BY view_count DESC, p.rating DESC
        LIMIT 6
    ");
    if (!$rfy_fallback) {
        error_log("RFY fallback error: " . $db->error);
        $recommended_for_you = [];
    } else {
        $recommended_for_you = $rfy_fallback->fetch_all(MYSQLI_ASSOC);
    }
}


?>

<div class="breadcrumbs-container">
    <div class="container">
        <nav id="breadcrumbs" class="breadcrumbs">
            <span>Главная</span> <i class="fas fa-chevron-right separator"></i> <span class="current">Каталог</span>
        </nav>
    </div>
</div>

<section class="slider py-5" id="slider">
    <div class="container text-center">
        <h1 class="mb-3">Добро пожаловать в наш маркетплейс!</h1>
        <p>Мы предлагаем широкий выбор современных смартфонов и аксессуаров от проверенных продавцов.</p>
        <p class="mb-4">Наш умный фильтр и система рекомендаций помогут вам быстро подобрать идеальный товар по вашим параметрам.</p>
        <div class="button-group">
            <button class="btn btn-primary btn-lg me-2" id="scroll-to-catalog-btn">Смотреть каталог</button>
            <button class="btn btn-secondary btn-lg" onclick="location.href='main.php#filter'">Подбор по параметрам</button>
        </div>
    </div>
</section>

<main class="container py-5">
    <div class="row">
        <!-- Боковая панель с фильтрами -->
        <div class="col-md-3">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h5 class="mb-0">Фильтры</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="index.php" id="filter-form">
                        <div class="mb-3">
                            <label class="form-label">Поиск</label>
                            <input type="text" name="search" class="form-control" 
                                   value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Название, бренд...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Категория</label>
                            <select name="category" class="form-select">
                                <option value="0">Все категории</option>
                                <?php 
                                $categories_query->data_seek(0);
                                while ($cat = $categories_query->fetch_assoc()): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?> (<?= $cat['product_count'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

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
                        
                        <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">
                            <i class="fas fa-times me-2"></i>Сбросить фильтры
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <!-- Список товаров -->
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4" id="catalog-header">
                <h2>Наш ассортимент</h2>
                <span class="text-muted">Найдено: <?= $total_items ?> товаров</span>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <div class="row" id="products-grid">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                            $price_display = $row['min_price'] == $row['max_price']
                                ? formatPrice($row['min_price'])
                                : 'от ' . formatPrice($row['min_price']);

                            $has_promo = $row['discount_percent'] !== null && $row['discount_percent'] > 0;
                            $promo_price = $row['min_price'];

                            if ($row['discount_type'] === 'fixed' && $row['discount_value'] > 0) {
                                $promo_price = max(0, $row['min_price'] - $row['discount_value']);
                                $has_promo = true;
                            } else if ($has_promo) {
                                $promo_price = $row['min_price'] * (100 - $row['discount_percent']) / 100;
                            }
                            
                            $discount_text = '';
                            if ($has_promo) {
                                if ($row['discount_percent'] !== null && $row['discount_percent'] > 0) {
                                    $discount_text = '-' . $row['discount_percent'] . '%';
                                } elseif ($row['discount_type'] === 'fixed') {
                                    $discount_text = '- ' . formatPrice($row['discount_value']);
                                }
                            }

                            // Данные для корзины
                            $var_data = $first_variations[$row['id']] ?? null;
                            $has_stock = ($var_data['quantity'] ?? 0) > 0 || $row['total_stock'] > 0;
                            $variation_id = $var_data['variation_id'] ?? null;
                            $js_var_id = $variation_id ? (int)$variation_id : 'null';
                        ?>

                        <div class="col-md-4 mb-4">
                            <div class="product-card h-100" data-product-id="<?= $row['id'] ?>" onclick="location.href='product.php?id=<?= $row['id']; ?>'">
                                <div class="product-image-wrapper position-relative">
                                    <img src="<?= htmlspecialchars($row['image_url']); ?>" 
                                         alt="<?= htmlspecialchars($row['name']); ?>" 
                                         class="product-image">
                                    <?php if ($has_promo): ?>
                                        <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                                            <?= $discount_text ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="card-actions position-absolute top-0 start-0 m-2">
                                        <button class="action-btn" onclick="event.stopPropagation(); toggleAction('wishlist', <?= $row['id']; ?>)">
                                            <i class="far fa-heart" id="wishlist-icon-<?= $row['id']; ?>"></i>
                                        </button>
                                        <button class="action-btn" onclick="event.stopPropagation(); toggleAction('compare', <?= $row['id']; ?>)">
                                            <i class="fas fa-balance-scale" id="compare-icon-<?= $row['id']; ?>"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="product-info p-3">
                                    <h6 class="product-title"><?= htmlspecialchars($row['brand'] . ' ' . $row['name']); ?></h6>
                                    <p class="product-model text-muted small mb-2"><?= htmlspecialchars($row['model']); ?></p>
                                    
                                    <?php if ($row['rating'] > 0): ?>
                                        <div class="rating-stars small mb-2">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i <= round($row['rating']) ? '' : '-o' ?> text-warning"></i>
                                            <?php endfor; ?>
                                            <small class="text-muted">(<?= $row['sales_count'] ?>)</small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="product-price-block mb-2">
                                        <?php if ($has_promo): ?>
                                            <span class="current-price fw-bold text-danger">
                                                <?= formatPrice($promo_price); ?>
                                            </span>
                                            <span class="old-price ms-2">
                                                <del><?= formatPrice($row['min_price']); ?></del>
                                            </span>
                                        <?php else: ?>
                                            <span class="current-price fw-bold"><?= $price_display; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <p class="product-stock small <?= $row['total_stock'] > 0 ? 'text-success' : 'text-danger' ?>">
                                        <i class="fas <?= $row['total_stock'] > 0 ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                        <?= $row['total_stock'] > 0 ? 'В наличии' : 'Нет в наличии' ?>
                                    </p>
                                    
                                    <?php if (!empty($row['promotion_name'])): ?>
                                        <p class="promotion-label small text-primary mb-2">
                                            <i class="fas fa-tag"></i> <?= htmlspecialchars($row['promotion_name']) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($has_stock): ?>
                                        <button class="btn-add-cart w-100 mt-2" 
                                                onclick="event.stopPropagation(); addToCart(<?= $row['id'] ?>, <?= $js_var_id ?>, 1)">
                                            В корзину
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-add-cart w-100 mt-2" disabled style="background:#ccc; cursor:not-allowed;">
                                            Распродано
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center" id="pagination">
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
                    <a href="index.php" class="btn btn-primary">Сбросить фильтры</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php if (!empty($recommended_for_you)): ?>
<section class="rfy-section py-5" id="rfy-section">
    <canvas id="rfy-particles"></canvas>
    <div class="container">
        <div class="rfy-header mb-4">
            <div class="rfy-icon-wrap">
                <i class="fas fa-magic"></i>
            </div>
            <div>
                <h2 class="rfy-title">
                    <?= $user_id_idx ? 'Вам может понравиться' : 'Популярное сейчас' ?>
                </h2>
                <p class="rfy-sub">
                    <?= $user_id_idx
                        ? 'Подборка на основе ваших просмотров и покупок'
                        : 'Самые просматриваемые товары за последнюю неделю' ?>
                </p>
            </div>
        </div>

        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3">
            <?php foreach ($recommended_for_you as $rfy): ?>
                <?php
                    $rfy_price  = (float)$rfy['display_price'];
                    $rfy_disc   = (int)($rfy['promo_discount'] ?? 0);
                    $rfy_final  = $rfy_disc > 0 ? $rfy_price * (100 - $rfy_disc) / 100 : $rfy_price;
                    $rfy_img    = htmlspecialchars($rfy['image_url'] ?? 'assets/no-image.png');
                    $rfy_name   = htmlspecialchars($rfy['brand'] . ' ' . $rfy['name']);
                ?>
                <div class="col">
                    <div class="rfy-card h-100" onclick="location.href='product.php?id=<?= (int)$rfy['id'] ?>'" role="button">
                        <?php if ($rfy_disc > 0): ?>
                            <span class="rfy-badge">-<?= $rfy_disc ?>%</span>
                        <?php endif; ?>
                        <div class="rfy-img-wrap">
                            <img src="<?= $rfy_img ?>" alt="<?= $rfy_name ?>" class="rfy-img">
                        </div>
                        <div class="rfy-body">
                            <div class="rfy-name"><?= $rfy_name ?></div>
                            <div class="rfy-price-block">
                                <?php if ($rfy_disc > 0): ?>
                                    <del class="rfy-old-price"><?= number_format($rfy_price, 0, '.', ' ') ?> BYN</del>
                                    <span class="rfy-new-price text-danger"><?= number_format($rfy_final, 0, '.', ' ') ?> BYN</span>
                                <?php else: ?>
                                    <span class="rfy-new-price"><?= number_format($rfy_price, 0, '.', ' ') ?> BYN</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($rfy['rating']) && $rfy['rating'] > 0): ?>
                                <div class="rfy-rating">
                                    <?php for($si = 1; $si <= 5; $si++): ?>
                                        <i class="fas fa-star<?= $si <= round($rfy['rating']) ? '' : ' text-muted' ?>" style="font-size:10px; color:<?= $si <= round($rfy['rating']) ? '#ffc107' : '#ddd' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php if (!empty($bestsellers)): ?>
    <section class="bestsellers py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">Хиты продаж</h2>
            <div class="row">
                <?php 
                foreach ($bestsellers as $item): 
                    $bs_var = $first_variations[$item['id']] ?? null;
                    $bs_stock = ($bs_var['quantity'] ?? 0) > 0 || $item['total_stock'] > 0;
                    $bs_var_id = $bs_var['variation_id'] ?? null;
                    $bs_js_var_id = $bs_var_id ? (int)$bs_var_id : 'null';
                ?>
                    <div class="col-md-3 mb-4">
                        <div class="product-card h-100" data-product-id="<?= $item['id'] ?>" onclick="location.href='product.php?id=<?= $item['id']; ?>'">
                            <div class="product-image-wrapper position-relative">
                                <img src="<?= htmlspecialchars($item['image_url']); ?>" 
                                     alt="<?= htmlspecialchars($item['name']); ?>" 
                                     class="product-image">
                                <div class="card-actions position-absolute top-0 start-0 m-2">
                                    <button class="action-btn" 
                                            onclick="event.stopPropagation(); toggleAction('wishlist', <?= $item['id']; ?>, this)">
                                        <i class="far fa-heart" id="wishlist-icon-bs-<?= $item['id']; ?>"></i>
                                    </button>
                                    <button class="action-btn" 
                                            onclick="event.stopPropagation(); toggleAction('compare', <?= $item['id']; ?>, this)">
                                        <i class="fas fa-balance-scale" id="compare-icon-bs-<?= $item['id']; ?>"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="product-info p-3">
                                <h6 class="product-title"><?= htmlspecialchars($item['brand'] . ' ' . $item['name']); ?></h6>
                                <p class="product-model text-muted small mb-2"><?= htmlspecialchars($item['model']); ?></p>
                                
                                <div class="product-price-block mb-2">
                                    <span class="current-price fw-bold"><?= formatPrice($item['price']); ?></span>
                                </div>
                                
                                <div class="bestseller-badge mb-2">
                                    <i class="fas fa-fire text-warning"></i> <small>Хит продаж</small>
                                </div>
                                
                                <p class="product-stock small <?= $item['total_stock'] > 0 ? 'text-success' : 'text-danger' ?>">
                                    <i class="fas <?= $item['total_stock'] > 0 ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                    <?= $item['total_stock'] > 0 ? 'В наличии' : 'Нет в наличии' ?>
                                </p>
                                
                                <?php if ($bs_stock): ?>
                                    <button class="btn-add-cart w-100 mt-2" 
                                            onclick="event.stopPropagation(); addToCart(<?= $item['id'] ?>, <?= $bs_js_var_id ?>, 1)">
                                        В корзину
                                    </button>
                                <?php else: ?>
                                    <button class="btn-add-cart w-100 mt-2" disabled style="background:#ccc; cursor:not-allowed;">
                                        Распродано
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="advantages py-5" id="advantages">
    <div class="container">
        <h2 class="text-center mb-4">Преимущества работы с нами</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="advantage-item text-center p-4">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h3>Качество</h3>
                    <p>Мы гарантируем оригинальные устройства от проверенных продавцов.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="advantage-item text-center p-4">
                    <i class="fas fa-headset fa-3x text-primary mb-3"></i>
                    <h3>Поддержка</h3>
                    <p>Наши специалисты помогут вам выбрать лучший товар.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="advantage-item text-center p-4">
                    <i class="fas fa-handshake fa-3x text-warning mb-3"></i>
                    <h3>Гарантия</h3>
                    <p>Официальная гарантия и сервисное обслуживание.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<button class="scroll-up position-fixed bottom-0 end-0 m-4 btn btn-primary rounded-circle shadow" 
        onclick="window.scrollTo({top: 0, behavior: 'smooth'})" 
        style="width: 50px; height: 50px; z-index: 1000;">
    <i class="fas fa-arrow-up"></i>
</button>

<footer class="main-footer">
    <div class="container footer-grid">
        <div class="footer-section">
            <h4>SHOP<span>HUB</span></h4>
            <p>Ваш надежный гид в мире технологий. Только оригинальная продукция.</p>
        </div>
        <div class="footer-section">
            <h4>Покупателям</h4>
            <ul class="list-unstyled">
                <li onclick="location.href='index.php'">Каталог</li>
                <li onclick="location.href='pages/compare.php'">Сравнение</li>
                <li onclick="location.href='wishlist.php'">Избранное</li>
            </ul>
        </div>
        <div class="footer-section">
            <h4>Контакты</h4>
            <p><i class="fas fa-phone"></i> +375-44-72-40-483</p>
            <p><i class="fas fa-phone"></i> +375-33-33-62-836</p>
            <p><i class="fas fa-envelope"></i> support@shophub.by</p>
        </div>
    </div>
    <div class="footer-bottom">&copy; 2026 ShopHub Engine. Все права защищены.</div>
</footer>
<script src="inc/scripts.js"></script>

<!-- 🔥 ГАРАНТИРОВАННАЯ ИНИЦИАЛИЗАЦИЯ DROPDOWN (только для index.php) -->
<script>


// ========== ЧАСТИЦЫ
(function() {
    var canvas = document.getElementById('rfy-particles');
    if (!canvas) return;
    
    var ctx = canvas.getContext('2d');
    var particles = [];
    var mouse = { x: null, y: null, radius: 160 };
    var animationId = null;
    var isVisible = false;
    
    function resize() {
        var section = canvas.parentElement;
        canvas.width = section.offsetWidth;
        canvas.height = section.offsetHeight;
    }
    
    function createParticles() {
        particles = [];
        var count = Math.min(Math.floor((canvas.width * canvas.height) / 5000), 140);
        var colors = [
            'rgba(102, 126, 234,',
            'rgba(118, 75, 162,',
            'rgba(255, 193, 7,',
            'rgba(200, 200, 255,',
            'rgba(255, 255, 255,'
        ];
        
        for (var i = 0; i < count; i++) {
            var ox = Math.random() * canvas.width;
            var oy = Math.random() * canvas.height;
            particles.push({
                x: ox,
                y: oy,
                originX: ox,
                originY: oy,
                // УВЕЛИЧИЛИ начальную скорость и добавили гарантированный импульс
                vx: (Math.random() - 0.5) * 1.5,
                vy: (Math.random() - 0.5) * 1.5,
                size: Math.random() * 3 + 2,
                color: colors[Math.floor(Math.random() * colors.length)],
                alpha: Math.random() * 0.4 + 0.4,
                // Счётчик кадров без движения — для принудительного пинка
                idleFrames: 0
            });
        }
    }
    
    var section = document.getElementById('rfy-section');
    if (section) {
        section.addEventListener('mousemove', function(e) {
            var rect = canvas.getBoundingClientRect();
            mouse.x = e.clientX - rect.left;
            mouse.y = e.clientY - rect.top;
        });
        section.addEventListener('mouseleave', function() {
            mouse.x = null;
            mouse.y = null;
        });
    }
    
    function drawParticles() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        for (var i = 0; i < particles.length; i++) {
            var p = particles[i];
            
            // === ПРИТЯГИВАНИЕ К МЫШИ ===
            if (mouse.x != null) {
                var dx = mouse.x - p.x;
                var dy = mouse.y - p.y;
                var dist = Math.sqrt(dx * dx + dy * dy);
                
                if (dist < mouse.radius) {
                    var force = (mouse.radius - dist) / mouse.radius;
                    p.vx += dx * force * 0.025;
                    p.vy += dy * force * 0.025;
                }
            }
            
            // === ВОЗВРАТ НА ИСХОДНУЮ ПОЗИЦИЮ ===
            var homeDx = p.originX - p.x;
            var homeDy = p.originY - p.y;
            p.vx += homeDx * 0.002;
            p.vy += homeDy * 0.002;
            
            // === ТРЕНИЕ (адаптивное) ===
            // Если скорость очень мала — меньше трения, чтобы не застыть
            var speed = Math.sqrt(p.vx * p.vx + p.vy * p.vy);
            var friction = speed < 0.3 ? 0.99 : 0.96;
            p.vx *= friction;
            p.vy *= friction;
            
            // === ПРИНУДИТЕЛЬНЫЙ ПИНОК, ЕСЛИ ЗАСТЫЛА ===
            if (speed < 0.15) {
                p.idleFrames++;
                if (p.idleFrames > 60) {  // через 60 кадров (~1 сек)
                    p.vx += (Math.random() - 0.5) * 0.8;
                    p.vy += (Math.random() - 0.5) * 0.8;
                    p.idleFrames = 0;
                }
            } else {
                p.idleFrames = 0;
            }
            
            // === ДВИЖЕНИЕ ===
            p.x += p.vx;
            p.y += p.vy;
            
            // Отскок от стенок
            if (p.x < 0 || p.x > canvas.width) {
                p.vx *= -0.8;
                p.x = Math.max(0, Math.min(canvas.width, p.x));
            }
            if (p.y < 0 || p.y > canvas.height) {
                p.vy *= -0.8;
                p.y = Math.max(0, Math.min(canvas.height, p.y));
            }
            
            // Ограничение скорости
            if (speed > 5) {
                p.vx = (p.vx / speed) * 5;
                p.vy = (p.vy / speed) * 5;
            }
            
            // === РИСУЕМ КРУГЛУЮ ЧАСТИЦУ ===
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
            ctx.fillStyle = p.color + p.alpha + ')';
            ctx.shadowBlur = 12;
            ctx.shadowColor = p.color + '0.6)';
            ctx.fill();
        }
        ctx.shadowBlur = 0;
        
        // === СОЕДИНИТЕЛЬНЫЕ ЛИНИИ ===
        for (var i = 0; i < particles.length; i++) {
            for (var j = i + 1; j < particles.length; j++) {
                var dx = particles[i].x - particles[j].x;
                var dy = particles[i].y - particles[j].y;
                var dist = Math.sqrt(dx * dx + dy * dy);
                var maxDist = 140;
                
                if (dist < maxDist) {
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    var opacity = (1 - dist / maxDist) * 0.4;
                    ctx.strokeStyle = 'rgba(140, 170, 255, ' + opacity + ')';
                    ctx.lineWidth = 1.0;
                    ctx.stroke();
                }
            }
            
            // Линии к мыши
            if (mouse.x != null) {
                var dx = mouse.x - particles[i].x;
                var dy = mouse.y - particles[i].y;
                var dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < mouse.radius) {
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(mouse.x, mouse.y);
                    var opacity = (1 - dist / mouse.radius) * 0.6;
                    ctx.strokeStyle = 'rgba(255, 200, 50, ' + opacity + ')';
                    ctx.lineWidth = 1.5;
                    ctx.stroke();
                }
            }
        }
    }
    
    function animate() {
        if (!isVisible) return;
        drawParticles();
        animationId = requestAnimationFrame(animate);
    }
    
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            isVisible = entry.isIntersecting;
            if (isVisible && !animationId) {
                animate();
            } else if (!isVisible && animationId) {
                cancelAnimationFrame(animationId);
                animationId = null;
            }
        });
    }, { threshold: 0.1 });
    
    observer.observe(section);
    
    resize();
    createParticles();
    animate();
    
    window.addEventListener('resize', function() {
        resize();
        createParticles();
    });
})();

(function() {
    'use strict';
    
    console.log('🔧 Index: Проверка инициализации dropdown...');
    
    function initAllDropdowns() {
        if (typeof bootstrap === 'undefined') {
            console.error('❌ Bootstrap не загружен в index!');
            return;
        }
        
        var dropdownToggles = document.querySelectorAll('[data-bs-toggle="dropdown"]');
        console.log('📍 Index: Найдено dropdown toggles:', dropdownToggles.length);
        
        var initialized = 0;
        dropdownToggles.forEach(function(toggle, index) {
            try {
                var existing = bootstrap.Dropdown.getInstance(toggle);
                if (existing) {
                    console.log('✅ Dropdown #' + index + ' уже инициализирован');
                    initialized++;
                    return;
                }
                
                new bootstrap.Dropdown(toggle, {
                    autoClose: true,
                    boundary: 'window'
                });
                initialized++;
                console.log('✅ Dropdown #' + index + ' инициализирован в index');
            } catch (error) {
                console.error('❌ Ошибка инициализации dropdown #' + index + ':', error);
            }
        });
        
        console.log('🎉 Index: Инициализировано dropdown: ' + initialized + '/' + dropdownToggles.length);
    }
    
    // Запускаем несколько раз для гарантии
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllDropdowns);
    } else {
        initAllDropdowns();
    }
    setTimeout(initAllDropdowns, 100);
    setTimeout(initAllDropdowns, 500);
})();
</script>
<style>
/* Начало заднего фона для хитов продаж */
.bestsellers {
    background-color: #f8f9fa;
    background-image: radial-gradient(#dee2e6 1px, transparent 1px);
    background-size: 20px 20px;
}
/* Конец заднего фона */

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    font-size: 14px;
    color: #6c757d;
}

.action-btn:hover {
    transform: scale(1.15);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Активное состояние избранного — красное сердце */
.action-btn .fa-heart.fas,
#wishlist-icon-<?= $item['id'] ?>.fas {
    color: #dc3545 !important;
}

/* Активное состояние сравнения — жёлтая иконка */
.action-btn .fa-balance-scale.active,
#compare-icon-<?= $item['id'] ?>.active {
    color: #ffc107 !important;
}

/* Анимация при клике */
.action-btn:active {
    transform: scale(0.9);
}

.rfy-section {
    background: linear-gradient(160deg, #f8f9ff 0%, #eef2ff 50%, #ffffff 100%);
    border-top: 1px solid #e0e4f0;
    border-bottom: 1px solid #e0e4f0;
    position: relative;
    overflow: hidden;
}
.rfy-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(102,126,234,0.06) 0%, transparent 70%);
    pointer-events: none;
}

.rfy-header {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-bottom: 28px;
    position: relative;
    z-index: 1;
}
.rfy-icon-wrap {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 22px;
    flex-shrink: 0;
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    position: relative;
}
.rfy-icon-wrap::after {
    content: '✨';
    position: absolute;
    top: -4px;
    right: -4px;
    font-size: 14px;
    animation: sparkle 2s ease-in-out infinite;
}
@keyframes sparkle {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
}

.rfy-title {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0;
    color: #1a1a2e;
    letter-spacing: -0.3px;
}
.rfy-sub {
    font-size: 0.9rem;
    color: #6c757d;
    margin: 4px 0 0;
    font-weight: 400;
}

/* Карточка */
.rfy-card {
    border: 1px solid #e9ecef;
    border-radius: 16px;
    background: #fff;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.rfy-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #667eea, #764ba2);
    opacity: 0;
    transition: opacity 0.3s;
}
.rfy-card:hover {
    box-shadow: 0 12px 32px rgba(102, 126, 234, 0.18);
    transform: translateY(-6px);
}
.rfy-card:hover::before {
    opacity: 1;
}

.rfy-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: linear-gradient(135deg, #ff6b6b, #ee5a5a);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 800;
    padding: 4px 10px;
    border-radius: 20px;
    z-index: 2;
    box-shadow: 0 2px 8px rgba(238, 90, 90, 0.35);
    animation: pulse-badge 2s ease-in-out infinite;
}
@keyframes pulse-badge {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.rfy-img-wrap {
    background: linear-gradient(180deg, #f8f9fa 0%, #f0f2f5 100%);
    height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}
.rfy-img-wrap::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 40px;
    background: linear-gradient(transparent, rgba(255,255,255,0.8));
    pointer-events: none;
}
.rfy-img {
    max-height: 120px;
    max-width: 90%;
    object-fit: contain;
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.rfy-card:hover .rfy-img { 
    transform: scale(1.1) rotate(-2deg); 
}

.rfy-body {
    padding: 14px 14px 16px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
    position: relative;
    z-index: 1;
}
.rfy-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: #212529;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.4em;
}
.rfy-price-block { 
    margin-top: auto; 
    padding-top: 6px;
    border-top: 1px dashed #e9ecef;
}
.rfy-old-price { 
    font-size: 0.75rem; 
    color: #adb5bd; 
    display: block;
    text-decoration: line-through;
}
.rfy-new-price { 
    font-size: 0.95rem; 
    font-weight: 800; 
}
.rfy-new-price.text-danger {
    background: linear-gradient(135deg, #ff6b6b, #ee5a5a);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.rfy-rating { 
    font-size: 10px; 
    margin-top: 4px; 
}

/* Кнопка "посмотреть" при наведении */
.rfy-card .rfy-view-btn {
    position: absolute;
    bottom: -40px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    border: none;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    transition: bottom 0.3s ease;
    white-space: nowrap;
    z-index: 3;
}
.rfy-card:hover .rfy-view-btn {
    bottom: 12px;
}

/* Адаптив */
@media (max-width: 768px) {
    .rfy-icon-wrap { width: 44px; height: 44px; font-size: 18px; }
    .rfy-title { font-size: 1.2rem; }
    .rfy-img-wrap { height: 110px; }
    .rfy-card:hover { transform: translateY(-3px); }
}

/* ====================================================
   БЛОК "ВАМ МОЖЕТ ПОНРАВИТЬСЯ" + ЧАСТИЦЫ
   ==================================================== */
.rfy-section {
    background: linear-gradient(160deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
    border-top: 1px solid rgba(102, 126, 234, 0.2);
    border-bottom: 1px solid rgba(102, 126, 234, 0.2);
    position: relative;
    overflow: hidden;
}
#rfy-particles {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
    pointer-events: none;
    filter: contrast(1.2) brightness(1.1);
}
.rfy-section .container {
    position: relative;
    z-index: 2;
}
.rfy-header {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-bottom: 28px;
    position: relative;
    z-index: 2;
}
.rfy-icon-wrap {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 22px;
    flex-shrink: 0;
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    position: relative;
    animation: pulse-glow 3s ease-in-out infinite;
}
@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4); }
    50% { box-shadow: 0 6px 30px rgba(102, 126, 234, 0.7), 0 0 60px rgba(102, 126, 234, 0.3); }
}
.rfy-title {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0;
    color: #fff;
    letter-spacing: -0.3px;
    text-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
}
.rfy-sub {
    font-size: 0.9rem;
    color: #a0a0c0;
    margin: 4px 0 0;
    font-weight: 400;
}

/* Карточка */
.rfy-card {
    border: 1px solid rgba(102, 126, 234, 0.15);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.rfy-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #667eea, #764ba2);
    opacity: 0;
    transition: opacity 0.3s;
}
.rfy-card:hover {
    box-shadow: 0 12px 32px rgba(102, 126, 234, 0.25);
    transform: translateY(-6px);
    border-color: rgba(102, 126, 234, 0.4);
}
.rfy-card:hover::before {
    opacity: 1;
}

.rfy-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: linear-gradient(135deg, #ff6b6b, #ee5a5a);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 800;
    padding: 4px 10px;
    border-radius: 20px;
    z-index: 2;
    box-shadow: 0 2px 8px rgba(238, 90, 90, 0.35);
    animation: pulse-badge 2s ease-in-out infinite;
}
@keyframes pulse-badge {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.rfy-img-wrap {
    background: linear-gradient(180deg, #f8f9fa 0%, #f0f2f5 100%);
    height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}
.rfy-img {
    max-height: 120px;
    max-width: 90%;
    object-fit: contain;
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.rfy-card:hover .rfy-img { 
    transform: scale(1.1) rotate(-2deg); 
}

.rfy-body {
    padding: 14px 14px 16px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
    position: relative;
    z-index: 1;
}
.rfy-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: #212529;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.4em;
}
.rfy-price-block { 
    margin-top: auto; 
    padding-top: 6px;
    border-top: 1px dashed #e9ecef;
}
.rfy-old-price { 
    font-size: 0.75rem; 
    color: #adb5bd; 
    display: block;
    text-decoration: line-through;
}
.rfy-new-price { 
    font-size: 0.95rem; 
    font-weight: 800; 
}
.rfy-new-price.text-danger {
    background: linear-gradient(135deg, #ff6b6b, #ee5a5a);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.rfy-rating { 
    font-size: 10px; 
    margin-top: 4px; 
}

/* Адаптив */
@media (max-width: 768px) {
    .rfy-icon-wrap { width: 44px; height: 44px; font-size: 18px; }
    .rfy-title { font-size: 1.2rem; }
    .rfy-img-wrap { height: 110px; }
    .rfy-card:hover { transform: translateY(-3px); }
}

</style>
<?php endif; ?>
<?php require_once __DIR__ . '/inc/footer.php'; ?>