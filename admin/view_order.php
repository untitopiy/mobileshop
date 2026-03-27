<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Проверка ID заказа
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_orders.php");
    exit;
}
$order_id = (int)$_GET['id'];

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
        sm.name as shipping_name,
        pm.name as payment_name,
        u.id as user_id,
        u.full_name as user_name,
        u.email as user_email,
        u.phone as user_phone
    FROM orders o
    JOIN order_statuses os ON os.id = o.status_id
    JOIN users u ON u.id = o.user_id
    LEFT JOIN shipping_methods sm ON sm.id = o.shipping_method_id
    LEFT JOIN payment_methods pm ON pm.id = o.payment_method_id
    WHERE o.id = ?
");
$order_query->bind_param('i', $order_id);
$order_query->execute();
$order = $order_query->get_result()->fetch_assoc();

if (!$order) {
    header("Location: manage_orders.php");
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

// Получаем продавцов в заказе
$sellers_query = $db->prepare("
    SELECT 
        os.id as order_seller_id,
        os.seller_id,
        s.shop_name as seller_name,
        s.logo_url as seller_logo,
        os.subtotal as seller_subtotal,
        os.shipping_price as seller_shipping,
        os.total_price as seller_total,
        os.tracking_number,
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

// Получаем историю статусов
$history_query = $db->prepare("
    SELECT 
        osh.id,
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

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Заказ <?= htmlspecialchars($order['order_number']) ?></h1>
        <div>
            <a href="change_order_status.php?id=<?= $order_id ?>" class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> Изменить статус
            </a>
            <a href="manage_orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Назад
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Информация о заказе</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Дата:</strong> <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
                            <p><strong>Статус:</strong> 
                                <span class="badge" style="background-color: <?= $order['status_color'] ?>; color: white;">
                                    <?= htmlspecialchars($order['status_name']) ?>
                                </span>
                            </p>
                            <p><strong>Оплата:</strong> <?= htmlspecialchars($order['payment_name']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Покупатель:</strong> <?= htmlspecialchars($order['user_name']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($order['user_email']) ?></p>
                            <p><strong>Телефон:</strong> <?= htmlspecialchars($order['user_phone']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Состав заказа</h5></div>
                <div class="card-body">
                    <?php 
                    if ($sellers_result->num_rows > 0):
                        $is_first = true;
                        while ($seller = $sellers_result->fetch_assoc()): 
                    ?>
                        <div class="seller-block mb-4 <?= !$is_first ? 'border-top pt-3' : '' ?>">
                            <div class="d-flex align-items-center mb-3">
                                <h6 class="mb-0">Продавец: <strong><?= htmlspecialchars($seller['seller_name'] ?: 'Магазин') ?></strong></h6>
                                <span class="ms-3 badge" style="background-color: <?= $seller['seller_status_color'] ?>;">
                                    <?= htmlspecialchars($seller['seller_status_name']) ?>
                                </span>
                            </div>

                            <?php
                            // Получаем товары этого продавца
                            $items_query = $db->prepare("
                                SELECT oi.*, pv.image_url as variation_image,
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
                                <table class="table table-bordered">
                                    <thead class="table-light">
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
                                                        <?php 
                                                        $img = $item['variation_image'] ?: $item['product_image'];
                                                        if ($img): 
                                                        ?>
                                                            <img src="/mobileshop/<?= htmlspecialchars($img) ?>" class="me-2" style="width: 40px;">
                                                        <?php endif; ?>
                                                        <div>
                                                            <?= htmlspecialchars($item['product_name']) ?>
                                                            <?php if ($item['attributes_text']): ?>
                                                                <br><small class="text-muted"><?= htmlspecialchars($item['attributes_text']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center"><?= $item['quantity'] ?></td>
                                                <td class="text-end"><?= number_format($item['price'], 2) ?> руб.</td>
                                                <td class="text-end"><?= number_format($item['total_price'], 2) ?> руб.</td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php 
                        $is_first = false;
                        endwhile; 
                    else:
                        echo "<p class='text-center'>Данные о товарах отсутствуют.</p>";
                    endif; 
                    ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light"><h5 class="mb-0">Итого</h5></div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td>Сумма:</td>
                            <td class="text-end"><?= number_format($order['subtotal'], 2) ?> руб.</td>
                        </tr>
                        <tr>
                            <td>Доставка:</td>
                            <td class="text-end"><?= number_format($order['shipping_price'], 2) ?> руб.</td>
                        </tr>
                        <?php if ($order['discount_amount'] > 0): ?>
                            <tr class="text-danger">
                                <td>Скидка:</td>
                                <td class="text-end">-<?= number_format($order['discount_amount'], 2) ?> руб.</td>
                            </tr>
                        <?php endif; ?>
                        <tr class="border-top fw-bold fs-5">
                            <td>К оплате:</td>
                            <td class="text-end text-primary"><?= number_format($order['total_price'], 2) ?> руб.</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>