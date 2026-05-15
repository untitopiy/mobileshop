<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Получаем статистику
$stats = [];

// Количество пользователей
$result = $db->query("SELECT COUNT(*) as count FROM users");
$stats['users'] = $result->fetch_assoc()['count'];

// Количество продавцов
$result = $db->query("SELECT COUNT(*) as count FROM sellers WHERE is_active = 1");
$stats['sellers'] = $result->fetch_assoc()['count'];

// Количество товаров
$result = $db->query("SELECT COUNT(*) as count FROM products WHERE status = 'active'");
$stats['products'] = $result->fetch_assoc()['count'];

// Количество заказов
$result = $db->query("SELECT COUNT(*) as count FROM orders");
$stats['orders'] = $result->fetch_assoc()['count'];

// Сумма продаж
$result = $db->query("SELECT SUM(total_price) as total FROM orders WHERE status_id IN (3,5,6)");
$stats['revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Заказы по статусам
$status_stats = $db->query("
    SELECT os.name, os.color, COUNT(*) as count
    FROM orders o
    JOIN order_statuses os ON os.id = o.status_id
    GROUP BY o.status_id
");

// Последние заказы
$recent_orders = $db->query("
    SELECT o.id, o.order_number, o.total_price, o.created_at,
           os.name as status_name, os.color as status_color,
           u.full_name as customer_name
    FROM orders o
    JOIN order_statuses os ON os.id = o.status_id
    JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
    LIMIT 10
");

// Топ товаров
$top_products = $db->query("
    SELECT p.id, p.name, p.brand, p.sales_count,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_url
    FROM products p
    WHERE p.status = 'active'
    ORDER BY p.sales_count DESC
    LIMIT 5
");

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-fluid admin-container" style="margin-top: 40px; margin-bottom: 40px;">
    <h1 class="mb-4">Панель администратора</h1>
    
    <!-- Статистика -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Пользователи</h6>
                            <h2 class="mb-0"><?= $stats['users'] ?></h2>
                        </div>
                        <i class="fas fa-users fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Продавцы</h6>
                            <h2 class="mb-0"><?= $stats['sellers'] ?></h2>
                        </div>
                        <i class="fas fa-store fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Товары</h6>
                            <h2 class="mb-0"><?= $stats['products'] ?></h2>
                        </div>
                        <i class="fas fa-box fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Заказы</h6>
                            <h2 class="mb-0"><?= $stats['orders'] ?></h2>
                        </div>
                        <i class="fas fa-shopping-cart fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Выручка</h5>
                </div>
                <div class="card-body">
                    <h3 class="text-success"><?= formatPrice($stats['revenue']) ?></h3>
                    <p class="text-muted mb-0">Общая сумма завершенных заказов</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Статусы заказов</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php while ($status = $status_stats->fetch_assoc()): ?>
                            <div class="col-6 mb-2">
                                <span class="badge" style="background-color: <?= $status['color'] ?>; color: white;">
                                    <?= htmlspecialchars($status['name']) ?>: <?= $status['count'] ?>
                                </span>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Меню администратора -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Управление</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="manage_products.php" class="btn btn-outline-primary w-100 py-3">
                                <i class="fas fa-box fa-2x mb-2"></i><br>
                                Товары
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="manage_orders.php" class="btn btn-outline-success w-100 py-3">
                                <i class="fas fa-shopping-cart fa-2x mb-2"></i><br>
                                Заказы
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="manage_sellers.php" class="btn btn-outline-info w-100 py-3">
                                <i class="fas fa-store fa-2x mb-2"></i><br>
                                Продавцы
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="manage_users.php" class="btn btn-outline-warning w-100 py-3">
                                <i class="fas fa-users fa-2x mb-2"></i><br>
                                Пользователи
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="manage_promotions.php" class="btn btn-outline-danger w-100 py-3">
                                <i class="fas fa-tag fa-2x mb-2"></i><br>
                                Акции
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="manage_coupons.php" class="btn btn-outline-secondary w-100 py-3">
                                <i class="fas fa-ticket-alt fa-2x mb-2"></i><br>
                                Купоны
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                        <a href="manage_categories.php" class="btn btn-outline-dark w-100 py-3">
    <i class="fas fa-folder fa-2x mb-2"></i><br>
    Категории
</a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="manage_newsletter.php" class="btn btn-outline-primary w-100 py-3">
                                <i class="fas fa-envelope fa-2x mb-2"></i><br>
                                Рассылка
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Последние заказы -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Последние заказы</h5>
                    <a href="manage_orders.php" class="btn btn-sm btn-primary">Все заказы</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>№ заказа</th>
                                    <th>Покупатель</th>
                                    <th>Сумма</th>
                                    <th>Статус</th>
                                    <th>Дата</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($order['order_number']) ?></td>
                                        <td><?= htmlspecialchars($order['customer_name'] ?: '—') ?></td>
                                        <td><?= formatPrice($order['total_price']) ?></td>
                                        <td>
                                            <span class="badge" style="background-color: <?= $order['status_color'] ?>; color: white;">
                                                <?= htmlspecialchars($order['status_name']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d.m.Y', strtotime($order['created_at'])) ?></td>
                                        <td>
                                            <a href="view_order.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Топ товаров -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Топ продаж</h5>
                </div>
                <div class="card-body">
                    <?php while ($product = $top_products->fetch_assoc()): ?>
                        <div class="d-flex align-items-center mb-3">
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="<?= $base_url . htmlspecialchars($product['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>"
                                     class="me-2" style="width: 40px; height: 40px; object-fit: contain;">
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <small class="d-block"><?= htmlspecialchars($product['brand'] . ' ' . $product['name']) ?></small>
                                <small class="text-muted">Продано: <?= $product['sales_count'] ?> шт.</small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.opacity-50 {
    opacity: 0.5;
}
.card .btn-outline-primary,
.card .btn-outline-success,
.card .btn-outline-info,
.card .btn-outline-warning,
.card .btn-outline-danger,
.card .btn-outline-secondary,
.card .btn-outline-dark {
    transition: all 0.3s;
}
.card .btn-outline-primary:hover {
    background-color: #0d6efd;
    color: white;
}
.card .btn-outline-success:hover {
    background-color: #198754;
    color: white;
}
/* и так далее для остальных цветов */
</style>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>