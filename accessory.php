<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container'><h2>Ошибка: Товар не найден.</h2></div>";
    require_once __DIR__ . '/inc/footer.php';
    exit;
}

$accessory_id = (int)$_GET['id'];

// Получаем информацию об аксессуаре
$query = $db->prepare("
    SELECT p.id, p.name, p.brand, p.model, p.description,
           p.seller_id, s.shop_name as seller_name,
           c.name as category_name,
           MIN(pv.price) as min_price,
           MAX(pv.price) as max_price,
           SUM(pv.quantity) as total_stock,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_url
    FROM products p
    JOIN product_variations pv ON pv.product_id = p.id
    LEFT JOIN sellers s ON s.id = p.seller_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.id = ? AND p.status = 'active'
    GROUP BY p.id
");
$query->bind_param('i', $accessory_id);
$query->execute();
$accessory = $query->get_result()->fetch_assoc();

if (!$accessory) {
    echo "<div class='container'><h2>Ошибка: Товар не найден.</h2></div>";
    require_once __DIR__ . '/inc/footer.php';
    exit;
}

// Получаем акцию на товар
$promo_query = $db->prepare("
    SELECT pr.discount_percent, pr.name as promotion_name
    FROM promotions pr
    JOIN promotion_products pp ON pp.promotion_id = pr.id
    WHERE pp.product_id = ? 
      AND pr.is_active = 1
      AND pr.starts_at <= NOW()
      AND (pr.expires_at >= NOW() OR pr.expires_at IS NULL)
    LIMIT 1
");
$promo_query->bind_param('i', $accessory_id);
$promo_query->execute();
$promo = $promo_query->get_result()->fetch_assoc();

// Получаем изображения товара
$images_query = $db->prepare("
    SELECT image_url, is_primary
    FROM product_images
    WHERE product_id = ?
    ORDER BY is_primary DESC, sort_order ASC
");
$images_query->bind_param('i', $accessory_id);
$images_query->execute();
$images = $images_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Получаем характеристики
$attributes_query = $db->prepare("
    SELECT a.name, a.type, a.unit, 
           pa.value_text, pa.value_number
    FROM product_attributes pa
    JOIN attributes a ON a.id = pa.attribute_id
    WHERE pa.product_id = ? AND a.is_visible_on_product = 1
    ORDER BY a.sort_order
");
$attributes_query->bind_param('i', $accessory_id);
$attributes_query->execute();
$attributes = $attributes_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Получаем совместимые смартфоны (если есть связь через отдельную таблицу)
$smartphones_stmt = $db->prepare("
    SELECT s.id, s.brand, s.name as smartphone_name, s.model,
           (SELECT image_url FROM product_images WHERE product_id = s.id AND is_primary = 1 LIMIT 1) AS image_url
    FROM products s
    JOIN accessory_compatibility ac ON ac.smartphone_id = s.id
    WHERE ac.accessory_id = ? AND s.status = 'active'
    LIMIT 10
");
$smartphones_stmt->bind_param('i', $accessory_id);
$smartphones_stmt->execute();
$compatible_smartphones = $smartphones_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Записываем просмотр
$session_id = session_id();
$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;

$insert_view = $db->prepare("
    INSERT INTO product_views (user_id, session_id, product_id, viewed_at) 
    VALUES (?, ?, ?, NOW())
");
$insert_view->bind_param("isi", $user_id, $session_id, $accessory_id);
$insert_view->execute();

// Обновляем счетчик просмотров
$update_views = $db->prepare("
    UPDATE products SET views_count = views_count + 1 WHERE id = ?
");
$update_views->bind_param('i', $accessory_id);
$update_views->execute();

// Вычисляем цену с учетом акции
$has_promo = $promo !== null && $promo['discount_percent'] > 0;
$promo_price = $has_promo 
    ? $accessory['min_price'] * (100 - $promo['discount_percent']) / 100 
    : $accessory['min_price'];
?>

<div class="container accessory-page py-4">
    <!-- Хлебные крошки -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
            <li class="breadcrumb-item"><a href="accessories.php">Аксессуары</a></li>
            <li class="breadcrumb-item"><a href="accessories.php?cat=<?= $accessory['category_id'] ?? '' ?>">
                <?= htmlspecialchars($accessory['category_name'] ?? 'Аксессуары') ?>
            </a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($accessory['name']) ?></li>
        </ol>
    </nav>

    <div class="accessory-detail">
        <div class="row">
            <!-- Галерея изображений -->
            <div class="col-md-5">
                <div class="accessory-gallery mb-4">
                    <?php if (!empty($images)): ?>
                        <img id="main-image" src="<?= htmlspecialchars($images[0]['image_url']); ?>" 
                             alt="<?= htmlspecialchars($accessory['name']); ?>" 
                             class="img-fluid main-image mb-3 rounded">
                        
                        <?php if (count($images) > 1): ?>
                            <div class="thumbnail-gallery d-flex gap-2">
                                <?php foreach ($images as $img): ?>
                                    <img src="<?= htmlspecialchars($img['image_url']); ?>" 
                                         alt="Фото" 
                                         onclick="changeImage(this)"
                                         class="img-thumbnail <?= $img['is_primary'] ? 'border-primary' : '' ?>"
                                         style="width: 60px; height: 60px; object-fit: cover; cursor: pointer;">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <img src="assets/no-image.png" alt="Нет изображения" class="img-fluid main-image">
                    <?php endif; ?>
                </div>
            </div>

            <!-- Информация о товаре -->
            <div class="col-md-7">
                <div class="accessory-header mb-3">
                    <h1 class="mb-2"><?= htmlspecialchars($accessory['brand'] . ' ' . $accessory['name']); ?></h1>
                    <p class="text-muted">Модель: <?= htmlspecialchars($accessory['model']); ?></p>
                </div>

                <!-- Информация о продавце -->
                <?php if (!empty($accessory['seller_name'])): ?>
                <div class="seller-info mb-3 p-2 bg-light rounded">
                    <i class="fas fa-store me-2 text-primary"></i>
                    <span>Продавец: <strong><?= htmlspecialchars($accessory['seller_name']) ?></strong></span>
                </div>
                <?php endif; ?>

                <!-- Цена -->
                <div class="price-block mb-4">
                    <?php if ($has_promo): ?>
                        <div class="d-flex align-items-center">
                            <h2 class="text-danger mb-0 me-3"><?= formatPrice($promo_price); ?></h2>
                            <h5 class="text-muted mb-0"><del><?= formatPrice($accessory['min_price']); ?></del></h5>
                            <span class="badge bg-danger ms-3 fs-6">-<?= $promo['discount_percent'] ?>%</span>
                        </div>
                        <p class="text-success mt-2">
                            <i class="fas fa-tag"></i> <?= htmlspecialchars($promo['promotion_name'] ?? 'Акция') ?>
                        </p>
                    <?php else: ?>
                        <h2 class="text-primary mb-0"><?= formatPrice($accessory['min_price']); ?></h2>
                    <?php endif; ?>
                </div>

                <!-- Наличие -->
                <div class="stock-block mb-4">
                    <p class="mb-2">
                        <strong>Наличие:</strong>
                        <?php if ($accessory['total_stock'] > 0): ?>
                            <span class="text-success">
                                <i class="fas fa-check-circle"></i> В наличии (<?= $accessory['total_stock'] ?> шт.)
                            </span>
                        <?php else: ?>
                            <span class="text-danger">
                                <i class="fas fa-times-circle"></i> Нет в наличии
                            </span>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Описание -->
                <?php if (!empty($accessory['description'])): ?>
                <div class="description-block mb-4">
                    <h5>Описание</h5>
                    <p class="text-muted"><?= nl2br(htmlspecialchars($accessory['description'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- Форма добавления в корзину -->
                <?php if ($accessory['total_stock'] > 0): ?>
                <form method="POST" action="cart.php" class="cart-form mb-4">
                    <input type="hidden" name="product_id" value="<?= $accessory['id']; ?>">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <label for="quantity" class="col-form-label">Количество:</label>
                        </div>
                        <div class="col-auto">
                            <input type="number" id="quantity" name="quantity" 
                                   min="1" max="<?= $accessory['total_stock']; ?>" value="1" 
                                   class="form-control" style="width: 80px;">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-shopping-cart me-2"></i>Добавить в корзину
                            </button>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                    <button class="btn btn-secondary btn-lg" disabled>Нет в наличии</button>
                <?php endif; ?>

                <!-- Характеристики -->
                <?php if (!empty($attributes)): ?>
                <div class="specs-block mt-4">
                    <h5>Характеристики</h5>
                    <table class="table table-sm">
                        <tbody>
                            <?php foreach ($attributes as $attr): ?>
                                <tr>
                                    <th style="width: 40%"><?= htmlspecialchars($attr['name']) ?></th>
                                    <td>
                                        <?php 
                                        if ($attr['value_text']) echo htmlspecialchars($attr['value_text']);
                                        elseif ($attr['value_number']) echo $attr['value_number'] . ' ' . $attr['unit'];
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Совместимые смартфоны -->
    <?php if (!empty($compatible_smartphones)): ?>
    <div class="compatible-smartphones mt-5">
        <h2 class="mb-4">Подходит для следующих смартфонов</h2>
        <div class="row">
            <?php foreach ($compatible_smartphones as $smart): ?>
                <div class="col-md-3 mb-3">
                    <div class="compatible-card p-3 border rounded h-100" 
                         onclick="window.location.href='product.php?id=<?= $smart['id']; ?>'"
                         style="cursor: pointer;">
                        <div class="text-center mb-2">
                            <?php if (!empty($smart['image_url'])): ?>
                                <img src="<?= htmlspecialchars($smart['image_url']); ?>" 
                                     alt="<?= htmlspecialchars($smart['brand'] . ' ' . $smart['smartphone_name']); ?>"
                                     class="img-fluid" style="max-height: 80px;">
                            <?php else: ?>
                                <img src="assets/no-image.png" alt="Нет изображения" 
                                     class="img-fluid" style="max-height: 80px;">
                            <?php endif; ?>
                        </div>
                        <h6 class="text-center mb-1"><?= htmlspecialchars($smart['brand'] . ' ' . $smart['smartphone_name']); ?></h6>
                        <p class="text-muted text-center small"><?= htmlspecialchars($smart['model']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function changeImage(img) {
    document.getElementById('main-image').src = img.src;
    document.querySelectorAll('.thumbnail-gallery img').forEach(thumb => {
        thumb.classList.remove('border-primary');
    });
    img.classList.add('border-primary');
}
</script>

<style>
.main-image {
    max-height: 400px;
    width: 100%;
    object-fit: contain;
    background: #f8f9fa;
    padding: 10px;
}
.thumbnail-gallery img {
    border: 2px solid transparent;
}
.thumbnail-gallery img:hover {
    border-color: #0d6efd;
}
.compatible-card {
    transition: all 0.2s;
}
.compatible-card:hover {
    background: #f8f9fa;
    border-color: #0d6efd !important;
}
</style>

<?php require_once __DIR__ . '/inc/footer.php'; ?>