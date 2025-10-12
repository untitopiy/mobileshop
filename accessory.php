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
$query = $db->prepare("SELECT id, name, brand, model, price, stock, description FROM accessories WHERE id = ?");
$query->bind_param('i', $accessory_id);
$query->execute();
$accessory = $query->get_result()->fetch_assoc();
if (!$accessory) {
    echo "<div class='container'><h2>Ошибка: Товар не найден.</h2></div>";
    require_once __DIR__ . '/inc/footer.php';
    exit;
}
$promo_stmt = $db->prepare("
    SELECT discount_percent
    FROM promotions
    WHERE product_type = 'accessory'
      AND product_id = ?
      AND is_active = 1
      AND start_date <= CURDATE()
      AND (end_date >= CURDATE() OR end_date IS NULL)
    LIMIT 1
");
$promo_stmt->bind_param('i', $accessory_id);
$promo_stmt->execute();
$promo = $promo_stmt->get_result()->fetch_assoc();
$promo_stmt->close();
$smartphones_stmt = $db->prepare("
    SELECT s.id, s.brand, s.name, s.model, 
           (SELECT image_url FROM smartphone_images WHERE smartphone_id = s.id LIMIT 1) AS image_url
    FROM smartphones s 
    JOIN accessory_supported_smartphones ast ON s.id = ast.smartphone_id 
    WHERE ast.accessory_id = ?
");
$smartphones_stmt->bind_param('i', $accessory_id);
$smartphones_stmt->execute();
$smartphones_result = $smartphones_stmt->get_result();
$compatible_smartphones = [];
while ($row = $smartphones_result->fetch_assoc()) {
    $compatible_smartphones[] = $row;
}
?>
<div class="container accessory-page">
    <div class="accessory-detail">
        <div class="accessory-header">
            <h1><?= htmlspecialchars($accessory['brand'] . ' ' . $accessory['name']); ?></h1>
            <p class="accessory-model"><?= htmlspecialchars($accessory['model']); ?></p>
        </div>
        <div class="accessory-body">
            <div class="accessory-description">
                <?php if ($promo): ?>
                    <?php
                        $orig = (float)$accessory['price'];
                        $disc = (int)$promo['discount_percent'];
                        $new = $orig * (100 - $disc) / 100;
                    ?>
                    <p class="accessory-price">
                        <del><?= number_format($orig, 0, ',', ' '); ?> руб.</del>
                        <?= number_format($new, 0, ',', ' '); ?> руб.
                        <span class="badge bg-danger">−<?= $disc; ?>%</span>
                    </p>
                <?php else: ?>
                    <p class="accessory-price"><?= number_format($accessory['price'], 0, ',', ' '); ?> руб.</p>
                <?php endif; ?>
                <p class="accessory-stock">В наличии: <?= $accessory['stock']; ?> шт.</p>
                <p class="accessory-text"><?= nl2br(htmlspecialchars($accessory['description'])); ?></p>
            </div>
            <div class="accessory-cart">
                <form method="POST" action="cart.php" class="cart-form">
                    <input type="hidden" name="product_id" value="<?= $accessory['id']; ?>">
                    <input type="hidden" name="product_type" value="accessory">
                    <input type="number" name="quantity" min="1" max="<?= $accessory['stock']; ?>" value="1" class="quantity-input">
                    <button type="submit" class="btn btn-primary">Добавить в корзину</button>
                </form>
            </div>
        </div>
    </div>
    <div class="compatible-smartphones">
        <h2>Подходит для:</h2>
        <?php if (!empty($compatible_smartphones)): ?>
            <div class="horizontal-list">
                <?php foreach ($compatible_smartphones as $smart): ?>
                    <div class="smartphone-card" onclick="window.location.href='product.php?id=<?= $smart['id']; ?>'">
                        <?php if (!empty($smart['image_url'])): ?>
                            <img src="<?= htmlspecialchars($smart['image_url']); ?>" alt="<?= htmlspecialchars($smart['brand'] . ' ' . $smart['name']); ?>">
                        <?php else: ?>
                            <img src="assets/no-image.png" alt="Нет изображения">
                        <?php endif; ?>
                        <div class="card-info">
                            <h5><?= htmlspecialchars($smart['brand'] . ' ' . $smart['name']); ?></h5>
                            <p><?= htmlspecialchars($smart['model']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>Нет данных о совместимых устройствах.</p>
        <?php endif; ?>
    </div>
</div>
<script>
function changeImage(img) {
    document.getElementById('main-image').src = img.src;
}
</script>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
