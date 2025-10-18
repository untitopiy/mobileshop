<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';

// Проверка авторизации пользователя
if (!isset($_SESSION['id'])) {
    header("Location: pages/auth.php");
    exit;
}

$user_id = $_SESSION['id'];

// Если корзина пуста, перенаправляем на страницу корзины
if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

// Получаем товары из корзины
$cart_items = [];
$total_price = 0;
$promoStmt = $db->prepare("
    SELECT discount_percent
    FROM promotions
    WHERE product_type = ? AND product_id = ? AND is_active = 1
      AND start_date <= CURDATE() AND (end_date >= CURDATE() OR end_date IS NULL)
    LIMIT 1
");

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
        $orig = (float)$row['price'];
        $promoStmt->bind_param('si', $product_type, $product_id);
        $promoStmt->execute();
        $pr = $promoStmt->get_result()->fetch_assoc();
        if ($pr) {
            $disc = (int)$pr['discount_percent'];
            $row['price'] = $orig * (100 - $disc) / 100;
            $row['discount_percent'] = $disc;
        } else {
            $row['price'] = $orig;
            $row['discount_percent'] = 0;
        }
        $row['original_price'] = $orig;
        $row['quantity'] = $qty;
        $row['subtotal'] = $row['price'] * $qty;
        $row['product_type'] = $product_type;
        $cart_items[] = $row;
        $total_price += $row['subtotal'];
    }
}

// Обработка оформления заказа
$errors = [];
$coupon_discount = 0;
$coupon_type = '';
$coupon_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $payment_method = $_POST['payment_method'];
    $coupon_code = trim($_POST['coupon_code'] ?? '');

    if (empty($full_name) || empty($phone) || empty($address)) {
        $errors[] = "Все поля обязательны для заполнения.";
    }
    if (!preg_match('/^\+?[0-9\s\-]+$/', $phone)) {
        $errors[] = "Некорректный номер телефона.";
    }
    if (!in_array($payment_method, ['cash', 'card', 'online'])) {
        $errors[] = "Некорректный метод оплаты.";
    }

    // Проверка купона
    if (!empty($coupon_code)) {
        $stmt_coupon = $db->prepare("
            SELECT id, discount_type, discount_value, expires_at, status
            FROM coupons
            WHERE (type='global' OR user_id=?) AND code=? LIMIT 1
        ");
        $stmt_coupon->bind_param("is", $user_id, $coupon_code);
        $stmt_coupon->execute();
        $result_coupon = $stmt_coupon->get_result()->fetch_assoc();
        $stmt_coupon->close();

        if ($result_coupon) {
            if ($result_coupon['status'] === 'active' && strtotime($result_coupon['expires_at']) >= time()) {
                $coupon_type = $result_coupon['discount_type'];
                $coupon_discount = (float)$result_coupon['discount_value'];
                $coupon_id = (int)$result_coupon['id']; // для вставки в order_discounts
            } else {
                $errors[] = "Купон недействителен или просрочен.";
            }
        } else {
            $errors[] = "Купон не найден.";
        }
    }

    if (empty($errors)) {
        $final_total = $total_price;
        if ($coupon_discount > 0) {
            if ($coupon_type === 'percent') {
                $final_total = $total_price * (100 - $coupon_discount) / 100;
            } else { // fixed
                $final_total = max(0, $total_price - $coupon_discount);
            }
        }

        // Вставка заказа
        $order_query = $db->prepare("
            INSERT INTO orders (user_id, total_price, status, created_at) 
            VALUES (?, ?, 'pending', NOW())
        ");
        $order_query->bind_param("id", $user_id, $final_total);
        $order_query->execute();
        $order_id = $order_query->insert_id;

        // Детали заказа
        $details_query = $db->prepare("
            INSERT INTO order_details (user_id, order_id, full_name, phone, address, payment_method) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $details_query->bind_param("iissss", $user_id, $order_id, $full_name, $phone, $address, $payment_method);
        $details_query->execute();

        // Товары заказа
        $order_items_query = $db->prepare("
            INSERT INTO order_items (order_id, product_id, product_type, quantity, price) 
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($cart_items as $item) {
            $order_items_query->bind_param("iisid", $order_id, $item['id'], $item['product_type'], $item['quantity'], $item['price']);
            $order_items_query->execute();
        }

        // Вставка скидки в order_discounts, если купон есть
        if ($coupon_id && $coupon_discount > 0) {
            $stmt_order_discount = $db->prepare("
                INSERT INTO order_discounts (order_id, coupon_id, discount_type, discount_value)
                VALUES (?, ?, ?, ?)
            ");
            $stmt_order_discount->bind_param("iisd", $order_id, $coupon_id, $coupon_type, $coupon_discount);
            $stmt_order_discount->execute();
            $stmt_order_discount->close();
        }

        // Отмечаем купон как использованный
        if ($coupon_id && $coupon_discount > 0) {
            $stmt_update = $db->prepare("UPDATE coupons SET status='used' WHERE id=?");
            $stmt_update->bind_param("i", $coupon_id);
            $stmt_update->execute();
            $stmt_update->close();
        }

        $_SESSION['cart'] = [];
        header("Location: success.php?order_id=$order_id");
        exit;
    }
}
?>

<div class="container checkout-container">
    <h1>Оформление заказа</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="checkout-form">
            <label for="full_name">ФИО:</label>
            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>

            <label for="phone">Телефон:</label>
            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>

            <label for="address">Адрес доставки:</label>
            <textarea id="address" name="address" rows="3" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>

            <label for="payment_method">Метод оплаты:</label>
            <select id="payment_method" name="payment_method" required>
                <option value="cash" <?= ($_POST['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Наличные при получении</option>
                <option value="card" <?= ($_POST['payment_method'] ?? '') === 'card' ? 'selected' : '' ?>>Оплата картой</option>
                <option value="online" <?= ($_POST['payment_method'] ?? '') === 'online' ? 'selected' : '' ?>>Онлайн-оплата</option>
            </select>

            <label for="coupon_code">Купон (если есть):</label>
            <input type="text" id="coupon_code" name="coupon_code" value="<?= htmlspecialchars($_POST['coupon_code'] ?? '') ?>">
        </div>

        <h2>Ваш заказ</h2>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Товар</th>
                    <th>Цена</th>
                    <th>Количество</th>
                    <th>Сумма</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cart_items as $item): ?>
                <tr>
                    <td class="cart-item">
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?= htmlspecialchars($item['image_url']); ?>" alt="<?= htmlspecialchars($item['name']); ?>" style="width:60px; height:60px; object-fit:cover; margin-right:10px;">
                        <?php endif; ?>
                        <div><strong><?= htmlspecialchars($item['brand'] . ' ' . $item['name']); ?></strong></div>
                    </td>
                    <td>
                        <?php if ($item['discount_percent'] > 0): ?>
                            <span style="text-decoration: line-through; color: #888;">
                                <?= number_format($item['original_price'], 0, ',', ' '); ?> руб.
                            </span><br>
                            <span style="color: #d9534f; font-weight:bold;">
                                <?= number_format($item['price'], 0, ',', ' '); ?> руб.
                            </span>
                            <span class="badge bg-danger">-<?= $item['discount_percent']; ?>%</span>
                        <?php else: ?>
                            <?= number_format($item['price'], 0, ',', ' '); ?> руб.
                        <?php endif; ?>
                    </td>
                    <td><?= $item['quantity']; ?></td>
                    <td><?= number_format($item['subtotal'], 0, ',', ' '); ?> руб.</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="cart-summary">
            <h2>Итого: <?= number_format($total_price, 0, ',', ' '); ?> руб.</h2>
            <?php if ($coupon_discount > 0): ?>
                <h3>Скидка купоном: <?= htmlspecialchars($coupon_discount); ?><?= $coupon_type==='percent' ? '%' : ' руб.'; ?><br>
                    Итого со скидкой: <?= number_format($final_total, 0, ',', ' '); ?> руб.
                </h3>
            <?php endif; ?>
            <button type="submit" name="checkout" class="btn btn-success">Подтвердить заказ</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
