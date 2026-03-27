<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

// Проверка авторизации
if (!isset($_SESSION['id'])) {
    header("Location: pages/auth.php");
    exit;
}

$user_id = $_SESSION['id'];



// Проверка order_id
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header("Location: orders.php");
    exit;
}

$order_id = (int)$_GET['order_id'];

// Получаем информацию о заказе
$order_query = $db->prepare("
    SELECT 
        o.id,
        o.order_number,
        o.subtotal,
        o.shipping_price,
        o.discount_amount,
        o.total_price,
        o.coupon_code,
        o.notes,
        o.created_at,
        os.name as status_name,
        os.color as status_color,
        os.slug as status_slug,
        sm.name as shipping_name,
        sm.price as shipping_method_price,
        pm.name as payment_name,
        pm.code as payment_code
    FROM orders o
    JOIN order_statuses os ON os.id = o.status_id
    LEFT JOIN shipping_methods sm ON sm.id = o.shipping_method_id
    LEFT JOIN payment_methods pm ON pm.id = o.payment_method_id
    WHERE o.id = ? AND o.user_id = ?
");
$order_query->bind_param('ii', $order_id, $user_id);
$order_query->execute();
$order = $order_query->get_result()->fetch_assoc();

if (!$order) {
    header("Location: orders.php");
    exit;
}

// Получаем адрес доставки
$address_query = $db->prepare("
    SELECT full_name, phone, email, country, city, address, postal_code
    FROM order_addresses
    WHERE order_id = ? AND type = 'shipping'
");
$address_query->bind_param('i', $order_id);
$address_query->execute();
$address = $address_query->get_result()->fetch_assoc();

// Получаем товары по продавцам
$sellers_query = $db->prepare("
    SELECT 
        os.id as order_seller_id,
        os.seller_id,
        s.shop_name as seller_name,
        s.logo_url as seller_logo,
        os.subtotal as seller_subtotal,
        os.shipping_price as seller_shipping,
        os.discount_amount as seller_discount,
        os.total_price as seller_total,
        os.tracking_number,
        os.shipped_at,
        os.delivered_at,
        ost.name as seller_status_name,
        ost.color as seller_status_color
    FROM order_sellers os
    LEFT JOIN sellers s ON s.id = os.seller_id
    JOIN order_statuses ost ON ost.id = os.status_id
    WHERE os.order_id = ?
");
$sellers_query->bind_param('i', $order_id);
$sellers_query->execute();
$sellers_result = $sellers_query->get_result();
// Преобразуем в массив, чтобы можно было использовать next()
$sellers = [];
while ($row = $sellers_result->fetch_assoc()) {
    $sellers[] = $row;
}

// Получаем историю статусов
$history_query = $db->prepare("
    SELECT 
        osh.status_id,
        os.name as status_name,
        os.color as status_color,
        osh.comment,
        osh.created_at,
        u.full_name as created_by_name
    FROM order_status_history osh
    JOIN order_statuses os ON os.id = osh.status_id
    LEFT JOIN users u ON u.id = osh.created_by
    WHERE osh.order_id = ?
    ORDER BY osh.created_at DESC
");
$history_query->bind_param('i', $order_id);
$history_query->execute();
$history = $history_query->get_result();
?>

<div class="container order-details-container py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="orders.php">Мои заказы</a></li>
            <li class="breadcrumb-item active">Заказ <?= htmlspecialchars($order['order_number']) ?></li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-receipt me-2"></i>Заказ <?= htmlspecialchars($order['order_number']) ?>
                    </h5>
                    <span class="badge fs-6" style="background-color: <?= $order['status_color'] ?>; color: white;">
                        <?= htmlspecialchars($order['status_name']) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>Дата заказа:</strong><br>
                                <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
                            </p>
                            <p class="mb-2">
                                <strong>Способ оплаты:</strong><br>
                                <?= htmlspecialchars($order['payment_name']) ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>Способ доставки:</strong><br>
                                <?= htmlspecialchars($order['shipping_name']) ?>
                            </p>
                            <?php if (!empty($order['notes'])): ?>
                                <p class="mb-2">
                                    <strong>Комментарий:</strong><br>
                                    <?= nl2br(htmlspecialchars($order['notes'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($address): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Адрес доставки</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong><?= htmlspecialchars($address['full_name']) ?></strong></p>
                    <p class="mb-1"><?= htmlspecialchars($address['phone']) ?></p>
                    <?php if (!empty($address['email'])): ?>
                        <p class="mb-1"><?= htmlspecialchars($address['email']) ?></p>
                    <?php endif; ?>
                    <p class="mb-0">
                        <?= htmlspecialchars($address['country']) ?>, г. <?= htmlspecialchars($address['city']) ?>,<br>
                        <?= htmlspecialchars($address['address']) ?>
                        <?php if (!empty($address['postal_code'])): ?>
                            <br>Индекс: <?= htmlspecialchars($address['postal_code']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-box me-2"></i>Состав заказа</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($sellers as $index => $seller): ?>
                        <div class="seller-block mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <?php if (!empty($seller['seller_logo'])): ?>
                                    <img src="<?= htmlspecialchars($seller['seller_logo']) ?>" 
                                         alt="<?= htmlspecialchars($seller['seller_name']) ?>"
                                         class="me-2" style="width: 30px; height: 30px; object-fit: contain;">
                                <?php else: ?>
                                    <i class="fas fa-store me-2 text-primary"></i>
                                <?php endif; ?>
                                <h6 class="mb-0">
                                    Продавец: <strong><?= htmlspecialchars($seller['seller_name'] ?: 'Магазин') ?></strong>
                                </h6>
                                <span class="ms-3 badge" style="background-color: <?= $seller['seller_status_color'] ?>; color: white;">
                                    <?= htmlspecialchars($seller['seller_status_name']) ?>
                                </span>
                            </div>

                            <?php
                            $items_query = $db->prepare("
                                SELECT 
                                    oi.product_id,
                                    oi.product_name,
                                    oi.product_sku,
                                    oi.quantity,
                                    oi.price,
                                    oi.total_price,
                                    pv.image_url as variation_image,
                                    (SELECT image_url FROM product_images WHERE product_id = oi.product_id AND is_primary = 1 LIMIT 1) as product_image
                                FROM order_items oi
                                LEFT JOIN product_variations pv ON pv.id = oi.variation_id
                                WHERE oi.order_seller_id = ?
                            ");
                            $items_query->bind_param('i', $seller['order_seller_id']);
                            $items_query->execute();
                            $items = $items_query->get_result();
                            ?>

                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Товар</th>
                                            <th>Артикул</th>
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
                                                        <?php 
                                                        $item_image = $item['variation_image'] ?: $item['product_image'];
                                                        if (!empty($item_image)): 
                                                        ?>
                                                            <img src="<?= htmlspecialchars($item_image) ?>" 
                                                                 alt="<?= htmlspecialchars($item['product_name']) ?>"
                                                                 class="me-2" style="width: 40px; height: 40px; object-fit: contain;">
                                                        <?php endif; ?>
                                                        <a href="product.php?id=<?= $item['product_id'] ?>" class="text-decoration-none">
                                                            <?= htmlspecialchars($item['product_name']) ?>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($item['product_sku'] ?: '-') ?></td>
                                                <td class="text-center"><?= $item['quantity'] ?></td>
                                                <td class="text-end"><?= formatPrice($item['price']) ?></td>
                                                <td class="text-end"><?= formatPrice($item['total_price']) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if (!empty($seller['tracking_number'])): ?>
                                <div class="alert alert-info mt-2">
                                    <i class="fas fa-truck me-2"></i>
                                    <strong>Трек-номер:</strong> <?= htmlspecialchars($seller['tracking_number']) ?>
                                    <?php if ($seller['shipped_at']): ?>
                                        <br><small>Отправлен: <?= date('d.m.Y H:i', strtotime($seller['shipped_at'])) ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($index < count($sellers) - 1): ?>
                                <hr class="my-3">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($history->num_rows > 0): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>История заказа</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php while ($event = $history->fetch_assoc()): ?>
                            <div class="timeline-item mb-3">
                                <div class="d-flex">
                                    <div class="timeline-icon me-3">
                                        <span class="badge" style="background-color: <?= $event['status_color'] ?>; width: 10px; height: 10px; display: block; border-radius: 50%;"></span>
                                    </div>
                                    <div class="timeline-content">
                                        <p class="mb-1">
                                            <strong><?= htmlspecialchars($event['status_name']) ?></strong>
                                            <small class="text-muted ms-2"><?= date('d.m.Y H:i', strtotime($event['created_at'])) ?></small>
                                        </p>
                                        <?php if (!empty($event['comment'])): ?>
                                            <p class="mb-0 text-muted"><?= nl2br(htmlspecialchars($event['comment'])) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($event['created_by_name'])): ?>
                                            <small class="text-muted">Автор: <?= htmlspecialchars($event['created_by_name']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h5 class="mb-0">Сумма заказа</h5>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-file-invoice me-2 text-muted"></i>Заказ №<?= htmlspecialchars($order['order_number']) ?>
                    </h1>
                    <button onclick="window.print()" class="btn btn-primary shadow-sm btn-sm">
                        <i class="fas fa-print fa-sm text-white-50 me-1"></i> Печать чека
                    </button>
                </div>


                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Товары:</span>
                        <span><?= formatPrice($order['subtotal']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Доставка:</span>
                        <span><?= formatPrice($order['shipping_price']) ?></span>
                    </div>
                    <?php if ($order['discount_amount'] > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>Скидка:</span>
                            <span>-<?= formatPrice($order['discount_amount']) ?></span>
                        </div>
                        <?php if (!empty($order['coupon_code'])): ?>
                            <p class="small text-muted mb-2">Купон: <?= htmlspecialchars($order['coupon_code']) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Итого:</strong>
                        <strong class="text-primary fs-5"><?= formatPrice($order['total_price']) ?></strong>
                    </div>

                    <?php if ($order['status_slug'] === 'pending'): ?>
                        <a href="cancel_order.php?order_id=<?= $order['id'] ?>" 
                           class="btn btn-outline-danger w-100 mb-2"
                           onclick="return confirm('Отменить заказ?')">
                            <i class="fas fa-times me-2"></i>Отменить заказ
                        </a>
                    <?php endif; ?>

                    <?php if ($order['status_slug'] === 'delivered'): ?>
                        <a href="add_review.php?order_id=<?= $order['id'] ?>" 
                           class="btn btn-outline-success w-100">
                            <i class="fas fa-star me-2"></i>Оставить отзыв
                        </a>
                    <?php endif; ?>

                    <a href="reorder.php?order_id=<?= $order['id'] ?>" 
                       class="btn btn-outline-primary w-100 mt-2">
                        <i class="fas fa-redo me-2"></i>Повторить заказ
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline-item {
    position: relative;
}
.timeline-icon {
    display: flex;
    align-items: center;
}
.timeline-content {
    flex: 1;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}
.timeline-item:last-child .timeline-content {
    border-bottom: none;
}
</style>

<?php require_once __DIR__ . '/inc/footer.php'; ?>