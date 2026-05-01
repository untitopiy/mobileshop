<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/models/Review.php';

// Вывод сообщений об ошибках/успехе
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            ' . htmlspecialchars($_SESSION['error']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            ' . htmlspecialchars($_SESSION['success']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['success']);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container'><h2>Ошибка: Товар не найден.</h2></div>";
    require_once __DIR__ . '/inc/footer.php';
    exit;
}

$product_id = (int)$_GET['id'];

// Получаем информацию о товаре
$query = $db->prepare("
    SELECT p.id, p.name, p.sku, p.short_description, p.description, 
           p.brand, p.model, p.type, p.seller_id,
           s.shop_name as seller_name, s.rating as seller_rating,
           c.id as category_id, c.name as category_name,
           p.price, p.meta_title, p.meta_description, p.views_count, p.sales_count,
           p.rating as product_rating, p.reviews_count
    FROM products p
    LEFT JOIN sellers s ON s.id = p.seller_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.id = ? AND p.status = 'active'
");
$query->bind_param('i', $product_id);
$query->execute();
$product = $query->get_result()->fetch_assoc();

if (!$product) {
    echo "<div class='container'><h2>Ошибка: Товар не найден.</h2></div>";
    require_once __DIR__ . '/inc/footer.php';
    exit;
}

// Получаем варианты товара
$variations_query = $db->prepare("
    SELECT id, sku, price, old_price, quantity, image_url
    FROM product_variations
    WHERE product_id = ? AND quantity > 0
    ORDER BY price ASC
");
$variations_query->bind_param('i', $product_id);
$variations_query->execute();
$variations = $variations_query->get_result()->fetch_all(MYSQLI_ASSOC);

// --- РАСЧЕТ СКИДОК И ЦЕН ---
$current_time = date('Y-m-d H:i:s');
$promo_query = $db->prepare("
    SELECT prom.name, prom.type, prom.value, pp.discount_percent
    FROM promotions prom
    JOIN promotion_products pp ON pp.promotion_id = prom.id
    WHERE pp.product_id = ? 
    AND prom.is_active = 1 
    AND (prom.starts_at <= ? OR prom.starts_at IS NULL)
    AND (prom.expires_at >= ? OR prom.expires_at IS NULL)
    LIMIT 1
");
$promo_query->bind_param('iss', $product_id, $current_time, $current_time);
$promo_query->execute();
$promo_res = $promo_query->get_result()->fetch_assoc();

// Базовая цена (берем минимальную из вариаций или из товара)
$base_price = !empty($variations) ? (float)min(array_column($variations, 'price')) : (float)$product['price'];

$final_price = $base_price;
$old_price = null;

if ($promo_res) {
    $old_price = $base_price;
    $discount_amount = ($promo_res['discount_percent'] > 0) ? $promo_res['discount_percent'] : $promo_res['value'];
    
    if ($promo_res['type'] === 'percent' || $promo_res['discount_percent'] > 0) {
        $final_price = $base_price * (1 - ($discount_amount / 100));
    } else {
        $final_price = $base_price - $discount_amount;
    }
}

// Получаем изображения товара
$images_query = $db->prepare("
    SELECT image_url, is_primary, sort_order
    FROM product_images
    WHERE product_id = ?
    ORDER BY is_primary DESC, sort_order ASC
");
$images_query->bind_param('i', $product_id);
$images_query->execute();
$images = $images_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Получаем характеристики товара
$attributes_query = $db->prepare("
    SELECT a.name, a.type, a.unit, 
           pa.value_text, pa.value_number, pa.value_boolean
    FROM product_attributes pa
    JOIN attributes a ON a.id = pa.attribute_id
    WHERE pa.product_id = ? AND a.is_visible_on_product = 1
    ORDER BY a.sort_order
");
$attributes_query->bind_param('i', $product_id);
$attributes_query->execute();
$attributes = $attributes_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Получаем теги товара
$tags_query = $db->prepare("
    SELECT t.name, t.slug
    FROM tags t
    JOIN product_tags pt ON pt.tag_id = t.id
    WHERE pt.product_id = ?
");
$tags_query->bind_param('i', $product_id);
$tags_query->execute();
$tags = $tags_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Записываем просмотр товара
$session_id = session_id();
$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;

$check_view = $db->prepare("
    SELECT id FROM product_views 
    WHERE user_id = ? AND session_id = ? AND product_id = ? 
    AND DATE(viewed_at) = CURDATE()
");
$check_view->bind_param("isi", $user_id, $session_id, $product_id);
$check_view->execute();
$existing_view = $check_view->get_result()->fetch_assoc();

if (!$existing_view) {
    $insert_view = $db->prepare("
        INSERT INTO product_views (user_id, session_id, product_id, viewed_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $insert_view->bind_param("isi", $user_id, $session_id, $product_id);
    $insert_view->execute();
    
    $update_views = $db->prepare("UPDATE products SET views_count = views_count + 1 WHERE id = ?");
    $update_views->bind_param('i', $product_id);
    $update_views->execute();
}

// ============================================================
// БЛОК 1: "Недавно просмотренные" — из сессии/БД, исключая текущий
// ============================================================
$recently_viewed = [];
$rv_query = $db->prepare("
    SELECT DISTINCT pv.product_id,
           p.id, p.name, p.brand, p.model, p.price,
           COALESCE(
               (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
               (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1),
               'assets/no-image.png'
           ) AS image_url,
           COALESCE(MIN(pv2.price), p.price) as display_price,
           pp2.discount_percent as promo_discount
    FROM product_views pv
    JOIN products p ON p.id = pv.product_id
    LEFT JOIN product_variations pv2 ON pv2.product_id = p.id
    LEFT JOIN promotion_products pp2 ON pp2.product_id = p.id
    LEFT JOIN promotions prom2 ON prom2.id = pp2.promotion_id
        AND prom2.is_active = 1
        AND (prom2.starts_at <= NOW() OR prom2.starts_at IS NULL)
        AND (prom2.expires_at >= NOW() OR prom2.expires_at IS NULL)
    WHERE pv.session_id = ?
      AND pv.product_id != ?
      AND p.status = 'active'
    GROUP BY pv.product_id, p.id, p.name, p.brand, p.model, p.price, image_url, pp2.discount_percent
    ORDER BY MAX(pv.viewed_at) DESC
    LIMIT 5
");
if (!$rv_query) {
    error_log("rv_query: " . $db->error);
    $recently_viewed = [];
} else {
    $rv_query->bind_param('si', $session_id, $product_id);
    $rv_query->execute();
    $res = $rv_query->get_result();
    $recently_viewed = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// ============================================================
// БЛОК 2: "Похожие товары" — по категории + рейтингу (из старого product.php + улучшено)
// ============================================================
$similar_query = $db->prepare("
    SELECT p.id, p.name, p.brand, p.model, p.rating,
           COALESCE(MIN(pv.price), p.price) as display_price,
           COALESCE(
               (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
               (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1),
               'assets/no-image.png'
           ) AS image_url,
           CASE WHEN prom_p.discount_type = 'percent' THEN prom_p.discount_value ELSE NULL END as promo_discount,
           COUNT(DISTINCT pvw.id) as view_count
    FROM products p
    LEFT JOIN product_variations pv ON pv.product_id = p.id
    LEFT JOIN promotion_products pp_s ON pp_s.product_id = p.id
    LEFT JOIN promotions prom_p ON prom_p.id = pp_s.promotion_id
        AND prom_p.is_active = 1
        AND (prom_p.starts_at <= NOW() OR prom_p.starts_at IS NULL)
        AND (prom_p.expires_at >= NOW() OR prom_p.expires_at IS NULL)
    LEFT JOIN product_views pvw ON pvw.product_id = p.id
    WHERE p.category_id = ?
      AND p.id != ?
      AND p.status = 'active'
    GROUP BY p.id
    ORDER BY p.rating DESC, view_count DESC
    LIMIT 4
");
if (!$similar_query) {
    error_log("similar_query: " . $db->error);
    $similar_products = [];
} else {
    $similar_query->bind_param('ii', $product['category_id'], $product_id);
    $similar_query->execute();
    $res = $similar_query->get_result();
    $similar_products = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// Если в той же категории мало товаров — дополняем популярными из других категорий
if (count($similar_products) < 3) {
    $needed = 4 - count($similar_products);
    $existing_ids = array_merge([$product_id], array_column($similar_products, 'id'));
    $placeholders = implode(',', array_fill(0, count($existing_ids), '?'));
    $types_str = str_repeat('i', count($existing_ids));

    $fallback_query = $db->prepare("
        SELECT p.id, p.name, p.brand, p.model, p.rating,
               COALESCE(MIN(pv.price), p.price) as display_price,
               COALESCE(
                   (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
                   (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1),
                   'assets/no-image.png'
               ) AS image_url,
               CASE WHEN prom_p.discount_type = 'percent' THEN prom_p.discount_value ELSE NULL END as promo_discount
        FROM products p
        LEFT JOIN product_variations pv ON pv.product_id = p.id
        LEFT JOIN promotion_products pp_s ON pp_s.product_id = p.id
        LEFT JOIN promotions prom_p ON prom_p.id = pp_s.promotion_id
            AND prom_p.is_active = 1
            AND (prom_p.starts_at <= NOW() OR prom_p.starts_at IS NULL)
            AND (prom_p.expires_at >= NOW() OR prom_p.expires_at IS NULL)
        WHERE p.id NOT IN ($placeholders)
          AND p.status = 'active'
        GROUP BY p.id
        ORDER BY p.sales_count DESC, p.rating DESC
        LIMIT ?
    ");
    $params = array_merge($existing_ids, [$needed]);
    $types_str .= 'i';
    if (!$fallback_query) {
        error_log("fallback_query: " . $db->error);
    } else {
        $fallback_query->bind_param($types_str, ...$params);
        $fallback_query->execute();
        $res = $fallback_query->get_result();
        $fallback = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $similar_products = array_merge($similar_products, $fallback);
    }
}

// ============================================================
// БЛОК 3: "С этим также покупают" — co-purchase из order_items
// ============================================================
$also_bought_query = $db->prepare("
    SELECT p.id, p.name, p.brand, p.model,
           COALESCE(MIN(pv.price), p.price) as display_price,
           COALESCE(
               (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
               (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1),
               'assets/no-image.png'
           ) AS image_url,
           CASE WHEN prom_p.discount_type = 'percent' THEN prom_p.discount_value ELSE NULL END as promo_discount,
           COUNT(*) as together_count
    FROM order_items oi_other
    JOIN order_items oi_main ON oi_main.order_seller_id = oi_other.order_seller_id
        AND oi_main.product_id = ?
        AND oi_other.product_id != ?
    JOIN products p ON p.id = oi_other.product_id
    LEFT JOIN product_variations pv ON pv.product_id = p.id
    LEFT JOIN promotion_products pp_ab ON pp_ab.product_id = p.id
    LEFT JOIN promotions prom_p ON prom_p.id = pp_ab.promotion_id
        AND prom_p.is_active = 1
        AND (prom_p.starts_at <= NOW() OR prom_p.starts_at IS NULL)
        AND (prom_p.expires_at >= NOW() OR prom_p.expires_at IS NULL)
    WHERE p.status = 'active'
    GROUP BY p.id
    ORDER BY together_count DESC
    LIMIT 4
");
if (!$also_bought_query) {
    error_log("also_bought_query: " . $db->error);
    $also_bought = [];
} else {
    $also_bought_query->bind_param('ii', $product_id, $product_id);
    $also_bought_query->execute();
    $res = $also_bought_query->get_result();
    $also_bought = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// Получаем отзывы
$reviewModel = new Review($db);
$current_user_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;
$average_rating = $reviewModel->getAverageRating($product_id);
$reviews = $reviewModel->getProductReviews($product_id, $current_user_id);
$has_reviewed = $current_user_id ? $reviewModel->hasUserReviewed($product_id, $current_user_id) : false;

$has_variations = count($variations) > 1;

// Вспомогательная функция для карточки товара (мини-версия)
function renderProductCard(array $p, bool $show_promo = true): string {
    $price = (float)($p['display_price'] ?? $p['price']);
    $disc  = $show_promo ? (int)($p['promo_discount'] ?? 0) : 0;
    $final = $disc > 0 ? $price * (100 - $disc) / 100 : $price;
    $img   = htmlspecialchars($p['image_url'] ?? 'assets/no-image.png');
    $name  = htmlspecialchars($p['brand'] . ' ' . $p['name']);
    $id    = (int)$p['id'];

    $price_html = '';
    if ($disc > 0) {
        $price_html = '
            <div class="rec-price">
                <del class="text-muted small">' . number_format($price, 0, '.', ' ') . ' BYN.</del>
                <span class="text-danger fw-bold">' . number_format($final, 0, '.', ' ') . ' BYN.</span>
                <span class="badge bg-danger ms-1">-' . $disc . '%</span>
            </div>';
    } else {
        $price_html = '<div class="rec-price fw-bold">' . number_format($price, 0, '.', ' ') . ' BYN.</div>';
    }

    return '
    <div class="col">
        <div class="rec-card h-100" onclick="location.href=\'product.php?id=' . $id . '\'" role="button" tabindex="0">
            <div class="rec-img-wrap">
                <img src="' . $img . '" alt="' . $name . '" class="rec-img">
            </div>
            <div class="rec-body">
                <div class="rec-name">' . $name . '</div>
                ' . $price_html . '
            </div>
        </div>
    </div>';
}
?>

<div class="container product-container">
    <nav aria-label="breadcrumb" class="my-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
            <li class="breadcrumb-item"><a href="catalog.php?category=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($product['name']) ?></li>
        </ol>
    </nav>

    <div class="row product-wrapper mt-2">
        
        <div class="col-md-5 mb-4 product-gallery">
            <div class="main-image-container mb-3 shadow-sm">
                <?php if (!empty($images)): ?>
                    <img id="main-image" src="<?= htmlspecialchars($images[0]['image_url']); ?>" 
                         alt="<?= htmlspecialchars($product['name']); ?>" class="main-image">
                <?php else: ?>
                    <img src="assets/no-image.png" alt="Нет изображения" class="main-image">
                <?php endif; ?>
            </div>
            
            <?php if (!empty($images) && count($images) > 1): ?>
                <div class="thumbnail-gallery">
                    <?php foreach ($images as $img): ?>
                        <img src="<?= htmlspecialchars($img['image_url']); ?>" 
                             alt="Фото <?= htmlspecialchars($product['name']); ?>" 
                             onclick="changeImage(this)"
                             class="<?= $img['is_primary'] ? 'primary' : '' ?>">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-7 product-info">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h1><?= htmlspecialchars($product['brand'] . ' ' . $product['name']); ?></h1>
                    <?php if (!empty($tags)): ?>
                        <div class="mb-2">
                            <?php foreach ($tags as $tag): ?>
                                <span class="badge bg-info me-1">#<?= htmlspecialchars($tag['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <span class="badge bg-secondary">Артикул: <?= htmlspecialchars($product['sku'] ?? '') ?></span>
            </div>

            <div class="product-rating mb-3">
                <div class="d-flex align-items-center">
                    <div class="rating-stars me-2">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= round($product['product_rating'] ?? 0) ? 'filled' : '' ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <span class="me-2 fw-bold"><?= number_format($product['product_rating'] ?? 0, 1) ?></span>
                    <a href="#reviews" class="text-muted">(<?= $product['reviews_count'] ?> отзывов)</a>
                </div>
            </div>

            <?php if ($product['seller_id']): ?>
                <div class="seller-info mb-3 p-2 bg-light rounded border">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-store me-2 text-primary"></i>
                        <span>Продавец: <strong><?= htmlspecialchars($product['seller_name']) ?></strong></span>
                        <?php if ($product['seller_rating'] > 0): ?>
                            <span class="ms-3 bg-white px-2 py-1 rounded border">
                                <i class="fas fa-star text-warning"></i>
                                <?= number_format($product['seller_rating'], 1) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="product-price-section mb-4 shadow-sm">
                <?php if (isset($old_price) && $old_price > $final_price): ?>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="text-muted text-decoration-line-through fs-5" id="old-price-display">
                            <?= number_format($old_price, 0, '.', ' ') ?> BYN.
                        </span>
                        <span class="badge bg-danger">Акция <?= isset($promo_res['name']) ? htmlspecialchars($promo_res['name']) : '' ?></span>
                    </div>
                    <div class="text-danger fw-bold fs-1" id="current-price-display">
                        <?= number_format($final_price, 0, '.', ' ') ?> BYN.
                    </div>
                <?php else: ?>
                    <div class="text-dark fw-bold fs-1" id="current-price-display">
                        <?= number_format($base_price, 0, '.', ' ') ?> BYN.
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($product['short_description'])): ?>
                <div class="short-description mb-4">
                    <p class="text-secondary"><?= nl2br(htmlspecialchars($product['short_description'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($has_variations): ?>
                <div class="product-variations mb-4">
                    <h5 class="mb-3">Выберите вариант:</h5>
                    <div class="variations-list">
                        <?php foreach ($variations as $variation): ?>
                            <div class="variation-item rounded p-2 <?= $variation['quantity'] <= 0 ? 'out-of-stock' : '' ?>"
                                 data-price="<?= $variation['price'] ?>"
                                 data-variation-id="<?= $variation['id'] ?>"
                                 data-stock="<?= $variation['quantity'] ?>"
                                 onclick="selectVariation(this, <?= $variation['id'] ?>, <?= $variation['price'] ?>, <?= $variation['quantity'] ?>)">
                                <div class="d-flex flex-column">
                                    <strong><?= htmlspecialchars($variation['sku']) ?></strong>
                                    <span class="badge mt-1 <?= $variation['quantity'] > 0 ? 'bg-success' : 'bg-secondary' ?> align-self-start">
                                        <?= $variation['quantity'] > 0 ? 'В наличии: ' . $variation['quantity'] : 'Нет в наличии' ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Форма добавления в корзину -->
            <div class="cart-form mb-4" id="add-to-cart-form">
                <input type="hidden" id="product_id" value="<?= $product_id; ?>">
                <input type="hidden" id="selected_variation" value="<?= $variations[0]['id'] ?? '' ?>">
                
                <div class="row g-2 align-items-center">
                    <div class="col-auto">
                        <label for="quantity" class="col-form-label fw-bold">Количество:</label>
                    </div>
                    <div class="col-auto">
                        <input type="number" id="quantity" 
                               min="1" max="<?= $variations[0]['quantity'] ?? 0 ?>" 
                               value="1" class="form-control quantity-input form-control-lg" 
                               style="width: 100px;" 
                               <?= ($variations[0]['quantity'] ?? 0) <= 0 ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-auto flex-grow-1">
                        <button type="button" id="add-to-cart-btn" class="btn btn-primary btn-lg w-100 shadow-sm" 
                                onclick="addToCartFromProduct()"
                                <?= ($variations[0]['quantity'] ?? 0) <= 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-shopping-cart me-2"></i>Добавить в корзину
                        </button>
                    </div>
                </div>
            </div>

            <div class="product-actions mt-3 d-flex gap-2">
                <button class="btn btn-outline-danger flex-grow-1" onclick="toggleWishlist(<?= $product_id ?>)">
                    <i class="far fa-heart" id="wishlist-icon-<?= $product_id ?>"></i>
                    <span id="wishlist-text-<?= $product_id ?>">В избранное</span>
                </button>
                <button class="btn btn-outline-secondary flex-grow-1" onclick="toggleCompare(<?= $product_id ?>)">
                    <i class="fas fa-balance-scale"></i> Сравнить
                </button>
            </div>

            <div class="social-share mb-2 mt-4 text-end">
                <span class="me-2 text-muted small">Поделиться:</span>
                <a href="#" onclick="window.open('https://vk.com/share.php?url='+encodeURIComponent(window.location.href), '_blank')" class="btn btn-sm btn-outline-primary me-1"><i class="fab fa-vk"></i></a>
                <a href="#" onclick="window.open('https://t.me/share/url?url='+encodeURIComponent(window.location.href), '_blank')" class="btn btn-sm btn-outline-info me-1"><i class="fab fa-telegram"></i></a>
            </div>
        </div>
    </div>

    <div class="product-details mt-5">
        <ul class="nav nav-tabs" id="productTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab">Описание</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="specs-tab" data-bs-toggle="tab" data-bs-target="#specs" type="button" role="tab">Характеристики</button>
            </li>
        </ul>
        
        <div class="tab-content p-4 border border-top-0 rounded-bottom" id="productTabsContent">
            <div class="tab-pane fade show active" id="description" role="tabpanel">
                <?= nl2br(htmlspecialchars($product['description'] ?: 'Описание отсутствует')) ?>
            </div>
            
            <div class="tab-pane fade" id="specs" role="tabpanel">
                <?php if (!empty($attributes)): ?>
                    <table class="table table-striped table-hover">
                        <tbody>
                            <?php foreach ($attributes as $attr): ?>
                                 <tr>
                                    <th style="width: 40%" class="text-muted fw-normal"><?= htmlspecialchars($attr['name']) ?></th>
                                    <td class="fw-medium">
                                        <?php 
                                        if ($attr['value_text']) echo htmlspecialchars($attr['value_text']);
                                        elseif ($attr['value_number']) echo $attr['value_number'] . ' ' . $attr['unit'];
                                        elseif ($attr['value_boolean']) echo $attr['value_boolean'] ? 'Да' : 'Нет';
                                        ?>
                                    </td>
                                 </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted">Характеристики не указаны</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="reviews" class="reviews-section mt-5">
        <?php include __DIR__ . '/templates/reviews_template.php'; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- РЕК. БЛОК: "С этим также покупают"                          -->
<!-- ============================================================ -->
<?php if (!empty($also_bought)): ?>
<div class="container rec-section mt-5">
    <div class="rec-section-header">
        <div class="rec-section-icon"><i class="fas fa-shopping-basket"></i></div>
        <div>
            <h2 class="rec-section-title">С этим также покупают</h2>
            <p class="rec-section-sub">Товары, которые часто берут вместе с этим</p>
        </div>
    </div>
    <div class="row row-cols-2 row-cols-md-4 g-3">
        <?php foreach ($also_bought as $ab): ?>
            <?= renderProductCard($ab) ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- РЕК. БЛОК: "Похожие товары"                                  -->
<!-- ============================================================ -->
<?php if (!empty($similar_products)): ?>
<div class="container rec-section mt-4">
    <div class="rec-section-header">
        <div class="rec-section-icon"><i class="fas fa-layer-group"></i></div>
        <div>
            <h2 class="rec-section-title">Похожие товары</h2>
            <p class="rec-section-sub">Другие товары из той же категории</p>
        </div>
    </div>
    <div class="row row-cols-2 row-cols-md-4 g-3">
        <?php foreach ($similar_products as $sp): ?>
            <?= renderProductCard($sp) ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- РЕК. БЛОК: "Недавно просмотренные"                           -->
<!-- ============================================================ -->
<?php if (!empty($recently_viewed)): ?>
<div class="container rec-section mt-4 mb-5">
    <div class="rec-section-header">
        <div class="rec-section-icon"><i class="fas fa-history"></i></div>
        <div>
            <h2 class="rec-section-title">Недавно просмотренные</h2>
            <p class="rec-section-sub">Товары, которые вы смотрели ранее</p>
        </div>
    </div>
    <div class="row row-cols-2 row-cols-md-5 g-3">
        <?php foreach ($recently_viewed as $rv): ?>
            <?= renderProductCard($rv) ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<style>
/* ======================================================
   КАРТОЧКА ТОВАРА — основной блок
   ====================================================== */
.product-wrapper {
    background: #ffffff;
    border-radius: 16px;
    padding: 20px 0;
}

/* Галерея */
.main-image-container {
    border: 1px solid #f0f0f0;
    border-radius: 12px;
    padding: 15px;
    text-align: center;
    background: #fafafa;
    transition: transform 0.3s ease;
}
.main-image-container:hover { transform: scale(1.02); }
.main-image { width: 100%; max-height: 400px; object-fit: contain; }
.thumbnail-gallery {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding: 5px;
    scrollbar-width: thin;
}
.thumbnail-gallery img {
    width: 70px;
    height: 70px;
    object-fit: cover;
    cursor: pointer;
    border: 2px solid transparent;
    border-radius: 8px;
    opacity: 0.6;
    transition: all 0.2s ease-in-out;
}
.thumbnail-gallery img:hover,
.thumbnail-gallery img.primary {
    border-color: #0d6efd;
    opacity: 1;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(13, 110, 253, 0.2);
}

/* Блок цены */
.product-price-section {
    background: #f8f9fa;
    padding: 15px 20px;
    border-radius: 12px;
    border-left: 4px solid #0d6efd;
    display: inline-block;
    width: 100%;
}

/* Вариации */
.variations-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 10px;
}
.variation-item {
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid #dee2e6;
    background: #fff;
}
.variation-item:hover:not(.out-of-stock) {
    border-color: #adb5bd;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.08);
}
.variation-item.selected {
    border-color: #0d6efd !important;
    background-color: #f0f7ff !important;
    box-shadow: 0 0 0 1px #0d6efd;
}
.variation-item.out-of-stock {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f8f9fa;
}

/* Форма корзины */
.cart-form {
    padding: 20px 0;
    border-top: 1px dashed #e9ecef;
    border-bottom: 1px dashed #e9ecef;
}
.quantity-input { text-align: center; font-weight: bold; }
.product-actions .btn { border-radius: 8px; font-weight: 500; transition: all 0.2s; }

/* Вкладки */
.product-details .nav-tabs { border-bottom: 2px solid #e9ecef; }
.product-details .nav-link {
    font-weight: 600;
    color: #6c757d;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 12px 20px;
    margin-bottom: -2px;
}
.product-details .nav-link:hover { color: #0d6efd; border-color: transparent; }
.product-details .nav-link.active {
    color: #0d6efd;
    background-color: transparent;
    border-bottom-color: #0d6efd;
}
.product-details .tab-content {
    background: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    border-radius: 0 0 12px 12px;
    border: 1px solid #e9ecef;
    border-top: none;
}

/* Рейтинг */
.rating-area {
    display: flex !important;
    flex-direction: row-reverse !important;
    justify-content: flex-end;
    margin-bottom: 15px;
}
.rating-area input { display: none; }
.rating-area label { font-size: 25px; color: #ddd; cursor: pointer; margin-right: 5px; }
.rating-area label:hover,
.rating-area label:hover ~ label,
.rating-area input:checked ~ label { color: #ffc107; }
.rating-stars { color: #ddd; }
.rating-stars .star.filled { color: #ffc107; }

/* ======================================================
   БЛОКИ РЕКОМЕНДАЦИЙ
   ====================================================== */
.rec-section {
    border-top: 1px solid #f0f0f0;
    padding-top: 2rem;
}
.rec-section-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 1.25rem;
}
.rec-section-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #0d6efd 0%, #6ea8fe 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 18px;
    flex-shrink: 0;
}
.rec-section-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
    color: #1a1a2e;
}
.rec-section-sub {
    font-size: 0.82rem;
    color: #6c757d;
    margin: 2px 0 0;
}

/* Мини-карточка рекомендации */
.rec-card {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    background: #fff;
    cursor: pointer;
    transition: box-shadow 0.2s, transform 0.2s;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.rec-card:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.10);
    transform: translateY(-3px);
}
.rec-img-wrap {
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 140px;
    overflow: hidden;
}
.rec-img {
    max-height: 130px;
    max-width: 100%;
    object-fit: contain;
    transition: transform 0.3s;
}
.rec-card:hover .rec-img { transform: scale(1.05); }
.rec-body {
    padding: 10px 12px 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex-grow: 1;
}
.rec-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: #212529;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.rec-price { font-size: 0.88rem; margin-top: auto; }
</style>

<script>
function changeImage(img) {
    document.getElementById('main-image').src = img.src;
}

function selectVariation(element, variationId, basePrice, stock) {
    if (element.classList.contains('out-of-stock')) return;

    document.getElementById('selected_variation').value = variationId;
    
    const quantityInput = document.getElementById('quantity');
    const submitButton = document.getElementById('add-to-cart-btn');
    
    if (stock > 0) {
        quantityInput.max = stock;
        quantityInput.disabled = false;
        submitButton.disabled = false;
    } else {
        quantityInput.disabled = true;
        submitButton.disabled = true;
    }
    
    document.querySelectorAll('.variation-item').forEach(item => {
        item.classList.remove('selected', 'border-primary', 'bg-light');
    });
    element.classList.add('selected', 'border-primary', 'bg-light');

    const priceDisplay = document.getElementById('current-price-display');
    const oldPriceDisplay = document.getElementById('old-price-display');
    
    const hasPromo = <?= $promo_res ? 'true' : 'false' ?>;
    const promoType = '<?= $promo_res['type'] ?? 'percent' ?>';
    const promoValue = <?= $promo_res ? (($promo_res['discount_percent'] > 0) ? $promo_res['discount_percent'] : $promo_res['value']) : 0 ?>;

    let finalPrice = basePrice;
    let oldPrice = null;

    if (hasPromo) {
        oldPrice = basePrice;
        if (promoType === 'percent' || <?= ($promo_res['discount_percent'] ?? 0) > 0 ? 'true' : 'false' ?>) {
            finalPrice = basePrice * (1 - (promoValue / 100));
        } else {
            finalPrice = basePrice - promoValue;
        }
    }

    const formatter = new Intl.NumberFormat('ru-RU');
    
    if (priceDisplay) priceDisplay.textContent = formatter.format(Math.round(finalPrice)) + ' BYN.';
    if (oldPriceDisplay && oldPrice) oldPriceDisplay.textContent = formatter.format(Math.round(oldPrice)) + ' BYN.';
}

function addToCartFromProduct() {
    const productId = document.getElementById('product_id').value;
    const variationId = document.getElementById('selected_variation').value;
    const quantity = document.getElementById('quantity').value;
    
    const apiUrl = window.BASE_URL + 'pages/cart/Api_Cart.php?action=add';
    
    const params = new URLSearchParams();
    params.append('action', 'add');
    params.append('product_id', productId);
    params.append('quantity', quantity);
    if (variationId && variationId !== 'null') {
        params.append('variation_id', variationId);
    }
    
    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: params.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const cartCountEl = document.getElementById('cart-count');
            if (cartCountEl) cartCountEl.textContent = data.count;
            showToast(data.message || 'Товар добавлен в корзину');
        } else {
            showToast(data.message || 'Ошибка при добавлении', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Ошибка соединения', 'error');
    });
}

function showToast(message, type = 'success') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 9999;';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    const colors = { success: '#28a745', error: '#dc3545', warning: '#ffc107', info: '#17a2b8' };
    toast.style.cssText = `
        background-color: ${colors[type] || colors.success};
        color: ${type === 'warning' ? '#333' : 'white'};
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        animation: slideIn 0.3s ease;
        cursor: pointer;
    `;
    toast.innerHTML = `<div style="display: flex; align-items: center; gap: 10px;">
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span>${message}</span>
    </div>`;
    
    toast.addEventListener('click', () => toast.remove());
    toastContainer.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    const firstAvailable = document.querySelector('.variation-item:not(.out-of-stock)');
    if (firstAvailable) firstAvailable.click();
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>