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

// Готовим запрос для получения скидки
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

// Получаем список товаров из корзины
$cart_items = [];
$total_price = 0;
foreach ($_SESSION['cart'] as $cart_key => $qty) {
    if (strpos($cart_key, '_') === false) {
        continue;
    }
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
            $row['original_price'] = $orig;
            $row['price'] = $orig * (100 - $disc) / 100;
            $row['discount_percent'] = $disc;
        } else {
            $row['original_price'] = $orig;
            $row['price'] = $orig;
            $row['discount_percent'] = 0;
        }
        $row['quantity'] = $qty;
        $row['subtotal'] = $row['price'] * $qty;
        $row['product_type'] = $product_type;
        $cart_items[] = $row;
        $total_price += $row['subtotal'];
    }
}

// Обработка оформления заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $payment_method = $_POST['payment_method'];
    $errors = [];
    if (empty($full_name) || empty($phone) || empty($address)) {
        $errors[] = "Все поля обязательны для заполнения.";
    }
    if (!preg_match('/^\+?[0-9\s\-]+$/', $phone)) {
        $errors[] = "Некорректный номер телефона.";
    }
    if (!in_array($payment_method, ['cash', 'card', 'online'])) {
        $errors[] = "Некорректный метод оплаты.";
    }
    if (empty($errors)) {
        $order_query = $db->prepare("
            INSERT INTO orders (user_id, total_price, status, created_at) 
            VALUES (?, ?, 'pending', NOW())
        ");
        $order_query->bind_param("id", $user_id, $total_price);
        $order_query->execute();
        $order_id = $order_query->insert_id;
        $details_query = $db->prepare("
            INSERT INTO order_details (user_id, order_id, full_name, phone, address, payment_method) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $details_query->bind_param("iissss", $user_id, $order_id, $full_name, $phone, $address, $payment_method);
        $details_query->execute();
        $order_items_query = $db->prepare("
            INSERT INTO order_items (order_id, product_id, product_type, quantity, price) 
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($cart_items as $item) {
            $order_items_query->bind_param("iisid", $order_id, $item['id'], $item['product_type'], $item['quantity'], $item['price']);
            $order_items_query->execute();
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
            <input type="text" id="full_name" name="full_name" required>

            <label for="phone">Телефон:</label>
            <input type="text" id="phone" name="phone" required>

            <label for="address">Адрес доставки:</label>
            <textarea id="address" name="address" rows="3" required></textarea>

            <label for="payment_method">Метод оплаты:</label>
            <select id="payment_method" name="payment_method" required>
                <option value="cash">Наличные при получении</option>
                <option value="card">Оплата картой</option>
                <option value="online">Онлайн-оплата</option>
            </select>
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
                                <img src="<?= htmlspecialchars($item['image_url']); ?>" alt="<?= htmlspecialchars($item['name']); ?>">
                            <?php endif; ?>
                            <div>
                                <strong><?= htmlspecialchars($item['brand'] . ' ' . $item['name']); ?></strong>
                            </div>
                        </td>
                        <td>
                            <?php if ($item['discount_percent'] > 0): ?>
                                <del><?= number_format($item['original_price'], 0, ',', ' '); ?> руб.</del><br>
                                <?= number_format($item['price'], 0, ',', ' '); ?> руб.
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
            <button type="submit" name="checkout" class="btn btn-success">Подтвердить заказ</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
