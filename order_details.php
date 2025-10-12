<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';

// Проверяем авторизацию
if (!isset($_SESSION['id'])) {
    header("Location: auth.php");
    exit;
}

$user_id = $_SESSION['id'];

// Проверяем, передан ли order_id и является ли числом
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    die("<p class='alert alert-danger'>Ошибка: Неверный номер заказа.</p>");
}

$order_id = (int)$_GET['order_id'];

// Получаем информацию о заказе
$order_query = $db->prepare("
    SELECT o.id, o.total_price, o.status, o.created_at, 
           d.full_name, d.phone, d.address, d.payment_method
    FROM orders o
    LEFT JOIN order_details d ON o.id = d.order_id
    WHERE o.id = ? AND o.user_id = ?
");
if (!$order_query) {
    die("<p class='alert alert-danger'>Ошибка SQL: " . $db->error . "</p>");
}
$order_query->bind_param('ii', $order_id, $user_id);
$order_query->execute();
$order_result = $order_query->get_result();
$order = $order_result->fetch_assoc();

// Если заказ не найден, выводим сообщение
if (!$order) {
    die("<p class='alert alert-danger'>Ошибка: Заказ не найден.</p>");
}

// Получаем список товаров в заказе с учетом типа продукта (smartphone или accessory)
$order_items_query = $db->prepare("
    SELECT 
        oi.quantity, 
        oi.price, 
        oi.product_type, 
        oi.product_id,
        CASE 
            WHEN oi.product_type = 'smartphone' THEN s.name
            WHEN oi.product_type = 'accessory' THEN a.name
            ELSE ''
        END AS name,
        CASE 
            WHEN oi.product_type = 'smartphone' THEN s.brand
            WHEN oi.product_type = 'accessory' THEN a.brand
            ELSE ''
        END AS brand,
        CASE 
            WHEN oi.product_type = 'smartphone' THEN (SELECT image_url FROM smartphone_images WHERE smartphone_id = s.id LIMIT 1)
            WHEN oi.product_type = 'accessory' THEN ''
            ELSE ''
        END AS image_url
    FROM order_items oi
    LEFT JOIN smartphones s ON (oi.product_type = 'smartphone' AND oi.product_id = s.id)
    LEFT JOIN accessories a ON (oi.product_type = 'accessory' AND oi.product_id = a.id)
    WHERE oi.order_id = ?
");
if (!$order_items_query) {
    die("<p class='alert alert-danger'>Ошибка SQL: " . $db->error . "</p>");
}
$order_items_query->bind_param('i', $order_id);
$order_items_query->execute();
$order_items_result = $order_items_query->get_result();
?>

<div class="container order-details-container">
    <h1>Детали заказа №<?= $order['id']; ?></h1>
    <p><strong>Дата заказа:</strong> <?= date('d.m.Y H:i', strtotime($order['created_at'])); ?></p>
    <p><strong>Сумма:</strong> <?= number_format($order['total_price'], 0, ',', ' '); ?> руб.</p>
    <p><strong>Статус:</strong> <?= htmlspecialchars($order['status']); ?></p>

    <?php if ($order['full_name']): ?>
        <p><strong>Получатель:</strong> <?= htmlspecialchars($order['full_name']); ?></p>
        <p><strong>Телефон:</strong> <?= htmlspecialchars($order['phone']); ?></p>
        <p><strong>Адрес:</strong> <?= htmlspecialchars($order['address']); ?></p>
        <p><strong>Оплата:</strong> <?= ($order['payment_method'] === 'cash' ? 'Наличные' : ($order['payment_method'] === 'card' ? 'Карта' : 'Онлайн-оплата')); ?></p>
    <?php else: ?>
        <p class="alert alert-warning">Нет данных о доставке.</p>
    <?php endif; ?>

    <h2>🛒 Список товаров</h2>
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
            <?php while ($item = $order_items_result->fetch_assoc()): ?>
                <tr>
                    <td class="cart-item">
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?= htmlspecialchars($item['image_url']); ?>" alt="<?= htmlspecialchars($item['name']); ?>">
                        <?php endif; ?>
                        <div>
                            <strong><?= htmlspecialchars($item['brand'] . ' ' . $item['name']); ?></strong>
                        </div>
                    </td>
                    <td><?= number_format($item['price'], 0, ',', ' '); ?> руб.</td>
                    <td><?= $item['quantity']; ?></td>
                    <td><?= number_format($item['price'] * $item['quantity'], 0, ',', ' '); ?> руб.</td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="cart-summary">
        <a href="orders.php" class="btn btn-primary">Вернуться к заказам</a>
    </div>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
