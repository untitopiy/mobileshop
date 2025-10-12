<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container'><h2>Ошибка: Товар не найден.</h2></div>";
    require_once __DIR__ . '/inc/footer.php';
    exit;
}

$product_id = (int)$_GET['id'];

$query = $db->prepare("
    SELECT s.id, s.name, s.brand, s.model, s.price, s.stock, s.description, 
           sp.screen_size, sp.resolution, sp.processor, sp.ram, sp.storage, sp.battery_capacity, 
           sp.camera_main, sp.camera_front, sp.os, sp.color, sp.weight 
    FROM smartphones s
    LEFT JOIN smartphone_specs sp ON s.id = sp.smartphone_id
    WHERE s.id = ?
");
$query->bind_param('i', $product_id);
$query->execute();
$product = $query->get_result()->fetch_assoc();

if (!$product) {
    echo "<div class='container'><h2>Ошибка: Товар не найден.</h2></div>";
    require_once __DIR__ . '/inc/footer.php';
    exit;
}

$image_query = $db->prepare("SELECT image_url FROM smartphone_images WHERE smartphone_id = ?");
$image_query->bind_param('i', $product_id);
$image_query->execute();
$image_result = $image_query->get_result();
$images = $image_result->fetch_all(MYSQLI_ASSOC);

$session_id = session_id();
$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;
$insert_view = $db->prepare("INSERT INTO product_views (user_id, session_id, product_type, product_id) VALUES (?, ?, 'smartphone', ?)");
$insert_view->bind_param("isi", $user_id, $session_id, $product_id);
$insert_view->execute();

$promo_stmt = $db->prepare("
    SELECT discount_percent
    FROM promotions
    WHERE product_type = 'smartphone'
      AND product_id = ?
      AND is_active = 1
      AND start_date <= CURDATE()
      AND (end_date >= CURDATE() OR end_date IS NULL)
    LIMIT 1
");
$promo_stmt->bind_param('i', $product_id);
$promo_stmt->execute();
$promo = $promo_stmt->get_result()->fetch_assoc();
?>
<div class="container product-container">
    <div class="product">
        <div class="product-gallery">
            <?php if (!empty($images)): ?>
                <img id="main-image" src="<?= htmlspecialchars($images[0]['image_url']); ?>" alt="<?= htmlspecialchars($product['name']); ?>">
                <div class="thumbnail-gallery">
                    <?php foreach ($images as $img): ?>
                        <img src="<?= htmlspecialchars($img['image_url']); ?>" alt="Фото <?= htmlspecialchars($product['name']); ?>" onclick="changeImage(this)">
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <img src="assets/no-image.png" alt="Нет изображения">
            <?php endif; ?>
        </div>
        <div class="product-info">
            <h1><?= htmlspecialchars($product['brand'] . ' ' . $product['name']); ?></h1>
            <?php if ($promo): 
                $orig = (float)$product['price'];
                $disc = (int)$promo['discount_percent'];
                $new = $orig * (100 - $disc) / 100;
            ?>
                <p class="product-price">
                    <del><?= number_format($orig, 0, ',', ' '); ?> руб.</del>
                    <?= number_format($new, 0, ',', ' '); ?> руб.
                    <span class="badge bg-danger">-<?= $disc; ?>%</span>
                </p>
            <?php else: ?>
                <p class="product-price"><?= number_format($product['price'], 0, ',', ' '); ?> руб.</p>
            <?php endif; ?>
            <p class="product-stock">В наличии: <?= $product['stock']; ?> шт.</p>
            <p class="product-description"><?= nl2br(htmlspecialchars($product['description'])); ?></p>
            <h3>Характеристики:</h3>
            <ul class="product-specs">
                <li><strong>Модель:</strong> <?= htmlspecialchars($product['model']); ?></li>
                <li><strong>Экран:</strong> <?= htmlspecialchars($product['screen_size']); ?>" (<?= htmlspecialchars($product['resolution']); ?>)</li>
                <li><strong>Процессор:</strong> <?= htmlspecialchars($product['processor']); ?></li>
                <li><strong>ОЗУ:</strong> <?= htmlspecialchars($product['ram']); ?> ГБ</li>
                <li><strong>Память:</strong> <?= htmlspecialchars($product['storage']); ?> ГБ</li>
                <li><strong>Аккумулятор:</strong> <?= htmlspecialchars($product['battery_capacity']); ?> мАч</li>
                <li><strong>Камера:</strong> <?= htmlspecialchars($product['camera_main']); ?></li>
                <li><strong>Фронтальная камера:</strong> <?= htmlspecialchars($product['camera_front']); ?></li>
                <li><strong>ОС:</strong> <?= htmlspecialchars($product['os']); ?></li>
                <li><strong>Цвет:</strong> <?= htmlspecialchars($product['color']); ?></li>
                <li><strong>Вес:</strong> <?= htmlspecialchars($product['weight']); ?> г</li>
            </ul>
            <form method="POST" action="cart.php" class="cart-form">
                <input type="hidden" name="product_id" value="<?= $product_id; ?>">
                <input type="number" name="quantity" min="1" max="<?= $product['stock']; ?>" value="1" class="quantity-input">
                <button type="submit" class="btn btn-primary">Добавить в корзину</button>
            </form>
        </div>
    </div>
</div>
<script>
function changeImage(img) {
    document.getElementById('main-image').src = img.src;
}
</script>
<?php
$rec_query = $db->prepare("
    SELECT a.id, a.category_id, a.name, a.brand, a.model, a.price, a.stock, a.description 
    FROM accessories a
    JOIN accessory_supported_smartphones ast ON a.id = ast.accessory_id
    WHERE ast.smartphone_id = ?
    ORDER BY RAND() 
    LIMIT 3
");
$rec_query->bind_param('i', $product_id);
$rec_query->execute();
$rec_result = $rec_query->get_result();

$promoAccStmt = $db->prepare("
    SELECT discount_percent
    FROM promotions
    WHERE product_type = 'accessory'
      AND product_id = ?
      AND is_active = 1
      AND start_date <= CURDATE()
      AND (end_date >= CURDATE() OR end_date IS NULL)
    LIMIT 1
");

if ($rec_result->num_rows > 0):
?>
    <div class="container recommended-container text-center">
        <h2>Рекомендуемые аксессуары</h2><br><br>
        <div class="row justify-content-center">
            <?php while($rec = $rec_result->fetch_assoc()): ?>
                <?php
                $promoAccStmt->bind_param('i', $rec['id']);
                $promoAccStmt->execute();
                $pa = $promoAccStmt->get_result()->fetch_assoc();
                ?>
                <div class="col-md-3 mb-4">
                    <div class="feature-card">
                        <h5><?= htmlspecialchars($rec['brand'] . ' ' . $rec['name']); ?></h5><br>
                        <p>Модель: <?= htmlspecialchars($rec['model']); ?></p>
                        <?php if ($pa):
                            $origA = (float)$rec['price'];
                            $discA = (int)$pa['discount_percent'];
                            $newA = $origA * (100 - $discA) / 100;
                        ?>
                            <p class="accessory-price">
                                <del><?= number_format($origA, 0, ',', ' '); ?> руб.</del>
                                <?= number_format($newA, 0, ',', ' '); ?> руб.
                                <span class="badge bg-danger">-<?= $discA; ?>%</span>
                            </p>
                        <?php else: ?>
                            <p>Цена: <?= number_format($rec['price'], 0, ',', ' '); ?> руб.</p>
                        <?php endif; ?>
                        <p>В наличии: <?= $rec['stock']; ?> шт.</p>
                        <form method="POST" action="cart.php" class="cart-form">
                            <input type="hidden" name="product_id" value="<?= $rec['id']; ?>">
                            <input type="hidden" name="product_type" value="accessory">
                            <input type="number" name="quantity" min="1" max="<?= $rec['stock']; ?>" value="1" class="quantity-input">
                            <button type="submit" class="btn btn-primary btn-sm">В корзину</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <br>
<?php
endif;

$recSmartQuery = $db->prepare("
    SELECT s.id, s.name, s.brand, s.model, s.price, s.stock,
           (SELECT image_url FROM smartphone_images WHERE smartphone_id = s.id LIMIT 1) AS image_url,
           COUNT(pv.id) AS view_count
    FROM product_views pv
    JOIN smartphones s ON s.id = pv.product_id
    WHERE pv.product_type = 'smartphone'
      AND pv.product_id != ?
      AND pv.session_id IN (
          SELECT session_id FROM product_views
          WHERE product_type = 'smartphone' AND product_id = ?
      )
    GROUP BY s.id
    ORDER BY view_count DESC
    LIMIT 3
");
$recSmartQuery->bind_param("ii", $product_id, $product_id);
$recSmartQuery->execute();
$recSmartResult = $recSmartQuery->get_result();

$promoPhoneStmt = $db->prepare("
    SELECT discount_percent
    FROM promotions
    WHERE product_type = 'smartphone'
      AND product_id = ?
      AND is_active = 1
      AND start_date <= CURDATE()
      AND (end_date >= CURDATE() OR end_date IS NULL)
    LIMIT 1
");

if ($recSmartResult->num_rows > 0):
?>
<div class="container recommended-container text-center">
    <h2>Похожие смартфоны</h2><br><br>
    <div class="row justify-content-center">
        <?php while($recSmart = $recSmartResult->fetch_assoc()): ?>
            <?php
            $promoPhoneStmt->bind_param('i', $recSmart['id']);
            $promoPhoneStmt->execute();
            $pp = $promoPhoneStmt->get_result()->fetch_assoc();
            ?>
            <div class="col-md-3 mb-4">
                <div class="feature-card" onclick="window.location.href='product.php?id=<?= $recSmart['id']; ?>'">
                    <?php if (!empty($recSmart['image_url'])): ?>
                        <img src="<?= htmlspecialchars($recSmart['image_url']); ?>" alt="<?= htmlspecialchars($recSmart['name']); ?>" class="product-image">
                    <?php else: ?>
                        <img src="assets/no-image.png" alt="Нет изображения" class="product-image">
                    <?php endif; ?>
                    <h5><?= htmlspecialchars($recSmart['brand'] . ' ' . $recSmart['name']); ?></h5>
                    <p>Модель: <?= htmlspecialchars($recSmart['model']); ?></p>
                    <?php if ($pp):
                        $origP = (float)$recSmart['price'];
                        $discP = (int)$pp['discount_percent'];
                        $newP = $origP * (100 - $discP) / 100;
                    ?>
                        <p class="product-price">
                            <del><?= number_format($origP, 0, ',', ' '); ?> руб.</del>
                            <?= number_format($newP, 0, ',', ' '); ?> руб.
                            <span class="badge bg-danger">-<?= $discP; ?>%</span>
                        </p>
                    <?php else: ?>
                        <p>Цена: <?= number_format($recSmart['price'], 0, ',', ' '); ?> руб.</p>
                    <?php endif; ?>
                    <p>В наличии: <?= $recSmart['stock']; ?> шт.</p>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>
<?php
endif;

require_once __DIR__ . '/inc/footer.php';
?>
