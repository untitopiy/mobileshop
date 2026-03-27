<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

// Проверка авторизации пользователя
if (!isset($_SESSION['id'])) {
    header("Location: pages/auth.php");
    exit;
}

$user_id = $_SESSION['id'];

// Получаем список заказов пользователя с информацией о статусах
$orders_query = $db->prepare("
    SELECT 
        o.id,
        o.order_number,
        o.total_price,
        o.created_at,
        os.name as status_name,
        os.color as status_color,
        os.slug as status_slug,
        COUNT(oi.id) as items_count,
        s.shop_name as seller_name
    FROM orders o
    JOIN order_statuses os ON os.id = o.status_id
    LEFT JOIN order_sellers osellers ON osellers.order_id = o.id
    LEFT JOIN sellers s ON s.id = osellers.seller_id
    LEFT JOIN order_items oi ON oi.order_seller_id = osellers.id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$orders_query->bind_param('i', $user_id);
$orders_query->execute();
$orders_result = $orders_query->get_result();

// Получаем статистику заказов
$stats_query = $db->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN os.slug = 'delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN os.slug IN ('pending', 'processing') THEN 1 ELSE 0 END) as active,
        SUM(o.total_price) as total_spent
    FROM orders o
    JOIN order_statuses os ON os.id = o.status_id
    WHERE o.user_id = ?
");
$stats_query->bind_param('i', $user_id);
$stats_query->execute();
$stats = $stats_query->get_result()->fetch_assoc();
?>

<div class="container orders-container py-4">
    <h1 class="mb-4">
        <i class="fas fa-box me-2"></i>Мои заказы
    </h1>

    <!-- Статистика -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0"><?= $stats['total_orders'] ?? 0 ?></h3>
                    <p class="text-muted mb-0">Всего заказов</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0 text-success"><?= $stats['delivered'] ?? 0 ?></h3>
                    <p class="text-muted mb-0">Доставлено</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0 text-warning"><?= $stats['active'] ?? 0 ?></h3>
                    <p class="text-muted mb-0">В обработке</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0 text-primary"><?= $stats['total_spent'] ? formatPrice($stats['total_spent']) : '0 руб.' ?></h3>
                    <p class="text-muted mb-0">Потрачено всего</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($orders_result->num_rows === 0): ?>
        <div class="text-center py-5">
            <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
            <h3 class="text-muted">У вас пока нет заказов</h3>
            <p class="mb-4">Перейдите в каталог, чтобы выбрать товары</p>
            <a href="index.php" class="btn btn-primary">Перейти в каталог</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover orders-table">
                <thead class="table-light">
                    <tr>
                        <th>№ заказа</th>
                        <th>Дата</th>
                        <th>Товары</th>
                        <th>Продавец</th>
                        <th>Сумма</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $orders_result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                            </td>
                            <td>
                                <?= date('d.m.Y', strtotime($order['created_at'])) ?>
                                <br>
                                <small class="text-muted"><?= date('H:i', strtotime($order['created_at'])) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= $order['items_count'] ?> шт.</span>
                            </td>
                            <td>
                                <?php if (!empty($order['seller_name'])): ?>
                                    <span class="small">
                                        <i class="fas fa-store me-1 text-primary"></i>
                                        <?= htmlspecialchars($order['seller_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="text-primary"><?= formatPrice($order['total_price']) ?></strong>
                            </td>
                            <td>
                                <span class="badge" style="background-color: <?= $order['status_color'] ?>; color: white;">
                                    <?= htmlspecialchars($order['status_name']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="order_details.php?order_id=<?= $order['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i>Детали
                                </a>
                                <?php if ($order['status_slug'] === 'pending'): ?>
                                    <a href="cancel_order.php?order_id=<?= $order['id'] ?>" 
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Отменить заказ?')">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Информация о статусах -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Статусы заказов</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <span class="badge" style="background-color: #ffc107;">Ожидает оплаты</span>
                        <small class="d-block text-muted mt-1">Заказ создан, ожидает оплаты</small>
                    </div>
                    <div class="col-md-3">
                        <span class="badge" style="background-color: #0d6efd;">В обработке</span>
                        <small class="d-block text-muted mt-1">Заказ подтвержден, комплектуется</small>
                    </div>
                    <div class="col-md-3">
                        <span class="badge" style="background-color: #0dcaf0;">Отправлен</span>
                        <small class="d-block text-muted mt-1">Заказ передан в доставку</small>
                    </div>
                    <div class="col-md-3">
                        <span class="badge" style="background-color: #198754;">Доставлен</span>
                        <small class="d-block text-muted mt-1">Заказ получен</small>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.orders-table td {
    vertical-align: middle;
}
</style>

<?php require_once __DIR__ . '/inc/footer.php'; ?>