<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';

// Функция для перевода статусов заказа
function translate_status($status) {
    $status_dict = [
        'pending'    => 'Ожидание',
        'processing' => 'В обработке',
        'shipped'    => 'Отправлен',
        'delivered'  => 'Доставлен',
        'cancelled'  => 'Отменён'
    ];
    return isset($status_dict[$status]) ? $status_dict[$status] : $status;
}

// Проверяем, передан ли order_id
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header("Location: index.php");
    exit;
}

$order_id = (int)$_GET['order_id'];

// Получаем информацию о заказе
$order_query = $db->prepare("
    SELECT o.id, o.total_price, o.status, o.created_at, u.full_name 
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$order_query->bind_param('i', $order_id);
$order_query->execute();
$order_result = $order_query->get_result();
$order = $order_result->fetch_assoc();

// Если заказ не найден, отправляем на главную
if (!$order) {
    header("Location: index.php");
    exit;
}

// Получаем список товаров в заказе с учетом типа товара
$order_items_query = $db->prepare("
    SELECT 
        oi.quantity, 
        oi.price, 
        oi.product_type, 
        oi.product_id,
        COALESCE(s.name, a.name) AS name,
        COALESCE(s.brand, a.brand) AS brand,
        CASE 
            WHEN oi.product_type = 'smartphone' THEN MIN(sm.image_url)
            WHEN oi.product_type = 'accessory' THEN ''
            ELSE ''
        END AS image_url
    FROM order_items oi
    LEFT JOIN smartphones s ON (oi.product_type = 'smartphone' AND oi.product_id = s.id)
    LEFT JOIN accessories a ON (oi.product_type = 'accessory' AND oi.product_id = a.id)
    LEFT JOIN smartphone_images sm ON s.id = sm.smartphone_id
    WHERE oi.order_id = ?
    GROUP BY oi.id, oi.quantity, oi.price, oi.product_type, oi.product_id, s.name, a.name, s.brand, a.brand
");
if (!$order_items_query) {
    die("Ошибка запроса для order_items: " . $db->error);
}
$order_items_query->bind_param('i', $order_id);
$order_items_query->execute();
$order_items_result = $order_items_query->get_result();
?>

<div class="container success-container">
    <h1>✅ Заказ успешно оформлен!</h1>
    <p>Спасибо, <strong><?= htmlspecialchars($order['full_name']); ?></strong>!</p>
    <p>Ваш заказ <strong>№<?= $order['id']; ?></strong> на сумму <strong><?= number_format($order['total_price'], 0, ',', ' '); ?> руб.</strong> был успешно оформлен.</p>
    <p>Статус заказа: <strong><?= htmlspecialchars(translate_status($order['status'])); ?></strong></p>
    <p>Дата оформления: <strong><?= date('d.m.Y H:i', strtotime($order['created_at'])); ?></strong></p>

    <h2>Ваши товары:</h2>
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
                        <div><strong><?= htmlspecialchars($item['brand'] . ' ' . $item['name']); ?></strong></div>
                    </td>
                    <td><?= number_format($item['price'], 0, ',', ' '); ?> руб.</td>
                    <td><?= $item['quantity']; ?></td>
                    <td><?= number_format($item['price'] * $item['quantity'], 0, ',', ' '); ?> руб.</td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="cart-summary">
        <a href="index.php" class="btn btn-primary">Вернуться в магазин</a>
    </div>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
