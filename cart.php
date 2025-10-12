<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['quantity'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $product_type = isset($_POST['product_type']) ? $_POST['product_type'] : 'smartphone';
    $cart_key = $product_type . '_' . $product_id;
    if ($product_type === 'smartphone') {
        $query = $db->prepare("SELECT id, name, brand, price, stock FROM smartphones WHERE id = ?");
    } elseif ($product_type === 'accessory') {
        $query = $db->prepare("SELECT id, name, brand, price, stock FROM accessories WHERE id = ?");
    } else {
        $query = $db->prepare("SELECT id, name, brand, price, stock FROM smartphones WHERE id = ?");
    }
    $query->bind_param('i', $product_id);
    $query->execute();
    $product = $query->get_result()->fetch_assoc();
    if ($product && $quantity > 0 && $quantity <= $product['stock']) {
        if (isset($_SESSION['cart'][$cart_key])) {
            $_SESSION['cart'][$cart_key] += $quantity;
        } else {
            $_SESSION['cart'][$cart_key] = $quantity;
        }
    }
}

if (isset($_GET['remove'])) {
    unset($_SESSION['cart'][$_GET['remove']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $cart_key => $new_quantity) {
        $new_quantity = (int)$new_quantity;
        if ($new_quantity > 0) {
            $_SESSION['cart'][$cart_key] = $new_quantity;
        } else {
            unset($_SESSION['cart'][$cart_key]);
        }
    }
}

$cart_items = [];
$total_price = 0;
$promoStmt = $db->prepare("
    SELECT discount_percent
    FROM promotions
    WHERE product_type = ?
      AND product_id = ?
      AND is_active = 1
      AND start_date <= CURDATE()
      AND (end_date >= CURDATE() OR end_date IS NULL)
    LIMIT 1
");

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $cart_key => $qty) {
        if (strpos($cart_key, '_') === false) continue;
        list($product_type, $product_id) = explode('_', $cart_key, 2);
        $product_id = (int)$product_id;
        if ($product_type === 'smartphone') {
            $query = $db->prepare("
                SELECT id, name, brand, price, stock,
                       (SELECT image_url FROM smartphone_images WHERE smartphone_id = smartphones.id LIMIT 1) AS image_url
                FROM smartphones
                WHERE id = ?
            ");
        } elseif ($product_type === 'accessory') {
            $query = $db->prepare("
                SELECT id, name, brand, price, stock, '' AS image_url
                FROM accessories
                WHERE id = ?
            ");
        } else {
            continue;
        }
        $query->bind_param('i', $product_id);
        $query->execute();
        $row = $query->get_result()->fetch_assoc();
        if ($row) {
            $row['quantity'] = $qty;
            $orig = (float)$row['price'];
            $promoStmt->bind_param('si', $product_type, $product_id);
            $promoStmt->execute();
            $pr = $promoStmt->get_result()->fetch_assoc();
            if ($pr) {
                $disc = (int)$pr['discount_percent'];
                $row['discount_percent'] = $disc;
                $row['discounted_price'] = $orig * (100 - $disc) / 100;
                $row['subtotal'] = $row['discounted_price'] * $qty;
            } else {
                $row['discount_percent'] = 0;
                $row['discounted_price'] = $orig;
                $row['subtotal'] = $orig * $qty;
            }
            $row['original_price'] = $orig;
            $row['product_type'] = $product_type;
            $cart_items[] = $row;
            $total_price += $row['subtotal'];
        }
    }
}
?>
<div class="container cart-container">
    <h1>🛒 Ваша корзина</h1>
    <?php if (empty($cart_items)): ?>
        <p class="empty-cart">Корзина пуста.</p>
    <?php else: ?>
        <form method="POST">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Цена</th>
                        <th>Количество</th>
                        <th>Сумма</th>
                        <th>Удалить</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $item): ?>
                        <tr>
                            <td class="cart-item">
                                <?php if (!empty($item['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($item['image_url']); ?>" alt="<?= htmlspecialchars($item['name']); ?>">
                                <?php endif; ?>
                                <div><strong><?= htmlspecialchars($item['brand'] . ' ' . $item['name']); ?></strong></div>
                            </td>
                            <td>
                                <?php if ($item['discount_percent'] > 0): ?>
                                    <del><?= number_format($item['original_price'], 0, ',', ' '); ?> руб.</del><br>
                                    <?= number_format($item['discounted_price'], 0, ',', ' '); ?> руб.
                                    <span class="badge bg-danger">-<?= $item['discount_percent']; ?>%</span>
                                <?php else: ?>
                                    <?= number_format($item['original_price'], 0, ',', ' '); ?> руб.
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="number"
                                       name="quantity[<?= $item['product_type'] . '_' . $item['id']; ?>]"
                                       value="<?= $item['quantity']; ?>"
                                       min="1"
                                       max="<?= $item['stock']; ?>">
                            </td>
                            <td><?= number_format($item['subtotal'], 0, ',', ' '); ?> руб.</td>
                            <td>
                                <a href="cart.php?remove=<?= $item['product_type'] . '_' . $item['id']; ?>"
                                   class="btn btn-danger btn-sm">✖</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="cart-summary">
                <h2>Итого: <?= number_format($total_price, 0, ',', ' '); ?> руб.</h2>
                <button type="submit" name="update_cart"
                        class="btn btn-primary"
                        style="margin-right: 10px; margin-bottom: 10px;">
                    Обновить корзину
                </button>
                <a href="checkout.php" class="btn btn-success">Оформить заказ</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php if (!empty($cart_items)): ?>
<div class="container recommended-container">
    <h2 class="text-center">Это вам может понравиться</h2><br>
    <?php
    $cart_smartphones = [];
    foreach ($cart_items as $item) {
        if ($item['product_type'] === 'smartphone') {
            $cart_smartphones[] = $item['id'];
        }
    }
    if (!empty($cart_smartphones)):
        $ids = implode(',', $cart_smartphones);
        $recSmartResult = $db->query("
            SELECT s.id, s.name, s.brand, s.price, s.stock,
                   (SELECT image_url FROM smartphone_images WHERE smartphone_id = s.id LIMIT 1) AS image_url
            FROM smartphones s
            WHERE s.id NOT IN ($ids)
            ORDER BY RAND()
            LIMIT 3
        ");
        if ($recSmartResult->num_rows):
            echo '<h3 class="text-center">Похожие смартфоны</h3><br><div class="row justify-content-center">';
            while ($rec = $recSmartResult->fetch_assoc()):
                $type = 'smartphone';
                $promoStmt->bind_param('si', $type, $rec['id']);
                $promoStmt->execute();
                $pr = $promoStmt->get_result()->fetch_assoc();
                $orig = (float)$rec['price'];
                if ($pr):
                    $disc = (int)$pr['discount_percent'];
                    $new = $orig * (100 - $disc) / 100;
                    $priceHtml = "<del>".number_format($orig,0,',',' ')." руб.</del><br>"
                               .number_format($new,0,',',' ')." руб. "
                               ."<span class='badge bg-danger'>-{$disc}%</span>";
                else:
                    $priceHtml = number_format($orig,0,',',' ')." руб.";
                endif;
                ?>
                <div class="col-md-3 mb-4">
                    <div class="feature-card" onclick="window.location.href='product.php?id=<?= $rec['id']; ?>'">
                        <?php if (!empty($rec['image_url'])): ?>
                            <img src="<?= htmlspecialchars($rec['image_url']); ?>" class="product-image">
                        <?php endif; ?>
                        <h5><?= htmlspecialchars($rec['brand'] . ' ' . $rec['name']); ?></h5>
                        <p>Цена: <?= $priceHtml; ?></p>
                        <p>В наличии: <?= $rec['stock']; ?> шт.</p>
                    </div>
                </div>
            <?php
            endwhile;
            echo '</div>';
        endif;
    endif;

    if (!empty($cart_smartphones)):
        $ids = implode(',', $cart_smartphones);
        $recAccResult = $db->query("
            SELECT DISTINCT a.id, a.name, a.brand, a.price, a.stock
            FROM accessories a
            JOIN accessory_supported_smartphones ast ON a.id = ast.accessory_id
            WHERE ast.smartphone_id IN ($ids)
            ORDER BY RAND()
            LIMIT 3
        ");
        if ($recAccResult->num_rows):
            echo '<br><h3 class="text-center">Подходящие аксессуары для ваших смартфонов</h3><br><div class="row justify-content-center">';
            while ($rec = $recAccResult->fetch_assoc()):
                $type = 'accessory';
                $promoStmt->bind_param('si', $type, $rec['id']);
                $promoStmt->execute();
                $pr = $promoStmt->get_result()->fetch_assoc();
                $orig = (float)$rec['price'];
                if ($pr):
                    $disc = (int)$pr['discount_percent'];
                    $new = $orig * (100 - $disc) / 100;
                    $priceHtml = "<del>".number_format($orig,0,',',' ')." руб.</del><br>"
                               .number_format($new,0,',',' ')." руб. "
                               ."<span class='badge bg-danger'>-{$disc}%</span>";
                else:
                    $priceHtml = number_format($orig,0,',',' ')." руб.";
                endif;
                ?>
                <div class="col-md-3 mb-4">
                    <div class="feature-card" onclick="window.location.href='accessory.php?id=<?= $rec['id']; ?>'">
                        <h5><?= htmlspecialchars($rec['brand'] . ' ' . $rec['name']); ?></h5>
                        <p>Цена: <?= $priceHtml; ?></p>
                        <p>В наличии: <?= $rec['stock']; ?> шт.</p>
                    </div>
                </div>
            <?php
            endwhile;
            echo '</div>';
        endif;
    endif;
    ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
