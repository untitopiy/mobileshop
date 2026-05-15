<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

// Проверяем, передан ли order_id
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header("Location: index.php");
    exit;
}

$order_id = (int)$_GET['order_id'];

// Для неавторизованных пользователей проверяем, что заказ их (по сессии)
// В реальном проекте здесь можно хранить номер заказа в сессии
if (!isset($_SESSION['id'])) {
    // Просто показываем информацию без привязки к пользователю
    $user_check = true;
} else {
    $user_id = $_SESSION['id'];
}

// Получаем информацию о заказе
$order_query = $db->prepare("
    SELECT 
        o.id,
        o.order_number,
        o.total_price,
        o.created_at,
        os.name as status_name,
        os.color as status_color,
        u.full_name,
        u.email
    FROM orders o
    JOIN order_statuses os ON os.id = o.status_id
    JOIN users u ON u.id = o.user_id
    WHERE o.id = ?" . (isset($user_id) ? " AND o.user_id = ?" : "")
);

if (isset($user_id)) {
    $order_query->bind_param('ii', $order_id, $user_id);
} else {
    $order_query->bind_param('i', $order_id);
}

$order_query->execute();
$order = $order_query->get_result()->fetch_assoc();

// Если заказ не найден, отправляем на главную
if (!$order) {
    header("Location: index.php");
    exit;
}

// Получаем товары в заказе
$items_query = $db->prepare("
    SELECT 
        oi.product_name,
        oi.quantity,
        oi.price,
        oi.total_price,
        p.id as product_id,
        (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_url
    FROM order_items oi
    JOIN order_sellers os ON os.id = oi.order_seller_id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE os.order_id = ?
    LIMIT 5
");
$items_query->bind_param('i', $order_id);
$items_query->execute();
$items = $items_query->get_result();

// Получаем адрес доставки
$address_query = $db->prepare("
    SELECT city, address
    FROM order_addresses
    WHERE order_id = ? AND type = 'shipping'
");
$address_query->bind_param('i', $order_id);
$address_query->execute();
$address = $address_query->get_result()->fetch_assoc();
?>

<div class="container success-container py-5">
    <div class="text-center mb-5">
        <div class="success-animation mb-4">
            <i class="fas fa-check-circle text-success" style="font-size: 80px;"></i>
        </div>
        <h1 class="display-4 mb-3">Спасибо за заказ!</h1>
        <p class="lead">Ваш заказ №<?= htmlspecialchars($order['order_number']) ?> успешно оформлен</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <!-- Карточка заказа -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-check-circle me-2"></i>Заказ подтвержден
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>Статус заказа:</strong><br>
                                <span class="badge fs-6" style="background-color: <?= $order['status_color'] ?>; color: white;">
                                    <?= htmlspecialchars($order['status_name']) ?>
                                </span>
                            </p>
                            <p class="mb-2">
                                <strong>Дата оформления:</strong><br>
                                <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>Сумма заказа:</strong><br>
                                <span class="text-primary fs-4"><?= formatPrice($order['total_price']) ?></span>
                            </p>
                            <?php if ($address): ?>
                                <p class="mb-2">
                                    <strong>Адрес доставки:</strong><br>
                                    <?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['address']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Товары -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-box me-2"></i>Состав заказа</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Товар</th>
                                    <th class="text-center">Кол-во</th>
                                    <th class="text-end">Цена</th>
                                    <th class="text-end">Сумма</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = $items->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($item['image_url'])): ?>
                                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                                         alt="<?= htmlspecialchars($item['product_name']) ?>"
                                                         class="me-2" style="width: 40px; height: 40px; object-fit: contain;">
                                                <?php endif; ?>
                                                <?php if (!empty($item['product_id'])): ?>
                                                    <a href="product.php?id=<?= $item['product_id'] ?>" class="text-decoration-none">
                                                        <?= htmlspecialchars($item['product_name']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($item['product_name']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center"><?= $item['quantity'] ?></td>
                                        <td class="text-end"><?= formatPrice($item['price']) ?></td>
                                        <td class="text-end"><?= formatPrice($item['total_price']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Информация и действия -->
            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Информация:</strong><br>
                        <small>
                            Мы отправили подтверждение на ваш email.<br>
                            Статус заказа можно отслеживать в личном кабинете.
                        </small>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <a href="orders.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-list me-2"></i>Мои заказы
                    </a>
                    <a href="index.php" class="btn btn-success">
                        <i class="fas fa-shopping-cart me-2"></i>Продолжить покупки
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.success-animation {
    animation: bounceIn 1s;
}

@keyframes bounceIn {
    0% {
        transform: scale(0.3);
        opacity: 0;
    }
    50% {
        transform: scale(1.05);
    }
    70% {
        transform: scale(0.9);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}
</style>

<?php require_once __DIR__ . '/inc/footer.php'; ?>