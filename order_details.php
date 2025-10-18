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

// Проверяем order_id
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    die("<p class='alert alert-danger'>Ошибка: Неверный номер заказа.</p>");
}

$order_id = (int)$_GET['order_id'];

// Получаем заказ и данные доставки
$order_query = $db->prepare("
    SELECT o.id, o.total_price, o.status, o.created_at, 
           d.full_name, d.phone, d.address, d.payment_method,
           od.discount_type, od.discount_value
    FROM orders o
    LEFT JOIN order_details d ON o.id = d.order_id
    LEFT JOIN order_discounts od ON o.id = od.order_id
    WHERE o.id = ? AND o.user_id = ?
");
$order_query->bind_param('ii', $order_id, $user_id);
$order_query->execute();
$order_result = $order_query->get_result();
$order = $order_result->fetch_assoc();

if (!$order) {
    die("<p class='alert alert-danger'>Ошибка: Заказ не найден.</p>");
}

// Получаем товары заказа
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
            WHEN oi.product_type = 'smartphone' THEN (
                SELECT image_url 
                FROM smartphone_images 
                WHERE smartphone_id = s.id 
                ORDER BY is_primary DESC 
                LIMIT 1
            )
            ELSE ''
        END AS image_url
    FROM order_items oi
    LEFT JOIN smartphones s ON (oi.product_type = 'smartphone' AND oi.product_id = s.id)
    LEFT JOIN accessories a ON (oi.product_type = 'accessory' AND oi.product_id = a.id)
    WHERE oi.order_id = ?
");
$order_items_query->bind_param('i', $order_id);
$order_items_query->execute();
$order_items_result = $order_items_query->get_result();
?>

<div class="container order-details-container">
    <h1>Детали заказа №<?= $order['id']; ?></h1>
    <p><strong>Дата заказа:</strong> <?= date('d.m.Y H:i', strtotime($order['created_at'])); ?></p>
    <p>
        <strong>Сумма:</strong> 
        <?php 
        $total = $order['total_price'];
         $discounted = $total;

        if (!empty($order['discount_value'])) {
            if ($order['discount_type'] === 'percent') {
                $discounted = $total * (1 - $order['discount_value']/100);
            } else { // fixed
                $discounted = max(0, $total - $order['discount_value']);
            }
            echo  number_format($total, 0, ',', ' ') . " руб. ";
           
        } else {
            echo number_format($total, 0, ',', ' ') . " руб.";
        }
        ?>
    </p>
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
                    <td>
                        <?php 
                        $item_price = $item['price'];
                        $item_discounted = $item_price;
                        if (!empty($order['discount_value'])) {
                            if ($order['discount_type'] === 'percent') {
                                $item_discounted = $item_price * (1 - $order['discount_value']/100);
                            } else {
                                $item_discounted = max(0, $item_price - $order['discount_value']);
                            }
                            echo "<del>" . number_format($item_price,0,',',' ') . "</del> ";
                            echo "<span class='text-success'>" . number_format($item_discounted,0,',',' ') . "</span>";
                        } else {
                            echo number_format($item_price,0,',',' ');
                        }
                        ?>
                    </td>
                    <td><?= $item['quantity']; ?></td>
                    <td>
                        <?= number_format($item['quantity'] * $item_discounted,0,',',' '); ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="cart-summary">
        <a href="orders.php" class="btn btn-primary">Вернуться к заказам</a>
    </div>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
