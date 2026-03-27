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

<?php if (!empty($bestsellers)): ?>
    <section class="bestsellers py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">Хиты продаж</h2>
            <div class="row">
                <?php foreach ($bestsellers as $item): 
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
                                    <button class="action-btn" onclick="event.stopPropagation(); toggleAction('wishlist', <?= $item['id']; ?>)">
                                        <i class="far fa-heart" id="wishlist-icon-<?= $item['id']; ?>"></i>
                                    </button>
                                    <button class="action-btn" onclick="event.stopPropagation(); toggleAction('compare', <?= $item['id']; ?>)">
                                        <i class="fas fa-balance-scale" id="compare-icon-<?= $item['id']; ?>"></i>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="inc/scripts.js"></script>

<script>
/**
 * 🔥 ИСПРАВЛЕННАЯ ФУНКЦИЯ: Добавление в корзину через API
 * Использует URLSearchParams вместо FormData для правильной кодировки
 */
function addToCart(productId, variationId = null, quantity = 1) {
    console.log('🛒 addToCart called:', {productId, variationId, quantity});
    
    // Формируем данные как URL-encoded строку
    const params = new URLSearchParams();
    params.append('product_id', productId);
    params.append('quantity', quantity);
    if (variationId && variationId !== 'null' && variationId !== '') {
        params.append('variation_id', variationId);
    }
    
    fetch('pages/cart/Api_Cart.php?action=add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params.toString()
    })
    .then(response => {
        console.log('📡 Response status:', response.status);
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('✅ Response data:', data);
        
        if (data.success) {
            // Обновляем счётчик корзины в шапке
            updateCartCountDisplay(data.count);
            showToast(data.message || 'Товар добавлен в корзину', 'success');
        } else {
            showToast(data.message || 'Ошибка при добавлении', 'error');
        }
    })
    .catch(error => {
        console.error('❌ Error:', error);
        showToast('Ошибка соединения с сервером', 'error');
    });
}

/**
 * 🔥 ОБНОВЛЁННАЯ ФУНКЦИЯ: Обновление отображения счётчика корзины
 */
function updateCartCountDisplay(count) {
    console.log('🔢 Updating cart count:', count);
    
    // Ищем все возможные элементы счётчика
    const selectors = ['#cart-count', '#cart-badge', '.cart-count'];
    
    selectors.forEach(selector => {
        const elements = document.querySelectorAll(selector);
        elements.forEach(el => {
            el.textContent = count;
            // Анимация
            el.style.transform = 'scale(1.3)';
            setTimeout(() => el.style.transform = 'scale(1)', 200);
        });
    });
}

/**
 * 🔥 ФУНКЦИЯ: Показ уведомлений (toast)
 */
function showToast(message, type = 'success') {
    // Удаляем старые тосты
    const oldToast = document.querySelector('.toast-notification');
    if (oldToast) oldToast.remove();
    
    const toast = document.createElement('div');
    toast.className = `toast-notification alert alert-${type === 'error' ? 'danger' : 'success'} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); animation: slideIn 0.3s ease;';
    toast.innerHTML = `
        <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'} me-2"></i>
        ${message}
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Плавная прокрутка к каталогу
document.getElementById('scroll-to-catalog-btn').addEventListener('click', function() {
    const catalogHeader = document.getElementById('catalog-header');
    if (catalogHeader) {
        const offset = 80;
        const elementPosition = catalogHeader.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - offset;
        
        window.scrollTo({
            top: offsetPosition,
            behavior: 'smooth'
        });
    }
});

// CSS анимация для toast
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
`;
document.head.appendChild(style);
</script>

<style>
.product-card {
    cursor: pointer;
    border: 1px solid #eee;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s;
    background: white;
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
.card-actions {
    display: flex;
    gap: 5px;
    z-index: 10;
}
.action-btn {
    background: white;
    border: none;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: all 0.2s;
}
.action-btn:hover {
    transform: scale(1.1);
}
.rating-stars i {
    font-size: 12px;
}
.btn-add-cart {
    background: #007bff;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 5px;
    font-size: 14px;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-add-cart:hover:not(:disabled) {
    background: #0056b3;
}
.btn-add-cart:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.sticky-top {
    z-index: 100;
}
</style>

<?php require_once __DIR__ . '/inc/footer.php'; ?>