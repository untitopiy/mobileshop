<?php
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/functions.php';

// Проверка авторизации пользователя
if (!isset($_SESSION['id'])) {
    header('Location: auth.php');
    exit;
}

$user_id = $_SESSION['id'];

// Получение информации о текущем пользователе
$stmt = $db->prepare("
    SELECT login, email, full_name, phone, photo, birth_date, 
           email_verified, phone_verified, is_subscribed, created_at 
    FROM users 
    WHERE id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Проверяем, является ли пользователь продавцом
$stmt = $db->prepare("
    SELECT id, shop_name, is_verified, logo_url 
    FROM sellers 
    WHERE user_id = ? AND is_active = 1
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$seller_result = $stmt->get_result();
$is_seller = $seller_result->num_rows > 0;
$seller = $seller_result->fetch_assoc();
$stmt->close();

// Получаем купоны, которые пользователь использовал
$stmt_used = $db->prepare("
    SELECT 
        c.code,
        c.name,
        c.discount_type,
        c.discount_value,
        c.expires_at,
        c.min_order_amount,
        c.max_discount_amount,
        cu.used_at,
        'used' as status
    FROM coupons c
    JOIN coupon_usage cu ON cu.coupon_id = c.id
    WHERE cu.user_id = ?
    ORDER BY cu.used_at DESC
");
$stmt_used->bind_param("i", $user_id);
$stmt_used->execute();
$used_coupons = $stmt_used->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_used->close();

// Получаем персональные купоны, назначенные пользователю (через поле user_id в coupons)
// Проверяем, существует ли поле user_id в таблице coupons
$check_column = $db->query("SHOW COLUMNS FROM coupons LIKE 'user_id'");
if ($check_column->num_rows > 0) {
    // Если поле существует, получаем купоны
    $stmt_personal = $db->prepare("
        SELECT 
            code,
            name,
            discount_type,
            discount_value,
            expires_at,
            min_order_amount,
            max_discount_amount,
            starts_at,
            'active' as status
        FROM coupons 
        WHERE user_id = ? 
          AND status = 'active' 
          AND expires_at >= CURDATE()
          AND starts_at <= NOW()
          AND id NOT IN (SELECT coupon_id FROM coupon_usage WHERE user_id = ?)
        ORDER BY expires_at ASC
    ");
    $stmt_personal->bind_param("ii", $user_id, $user_id);
    $stmt_personal->execute();
    $personal_coupons = $stmt_personal->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_personal->close();
} else {
    $personal_coupons = [];
}

// Получаем глобальные купоны, которые пользователь еще не использовал
$stmt_global = $db->prepare("
    SELECT 
        code,
        name,
        discount_type,
        discount_value,
        expires_at,
        min_order_amount,
        max_discount_amount,
        starts_at,
        'active' as status
    FROM coupons 
    WHERE type = 'global' 
      AND status = 'active' 
      AND expires_at >= CURDATE()
      AND starts_at <= NOW()
      AND id NOT IN (SELECT coupon_id FROM coupon_usage WHERE user_id = ?)
    ORDER BY expires_at ASC
    LIMIT 5
");
$stmt_global->bind_param("i", $user_id);
$stmt_global->execute();
$global_coupons = $stmt_global->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_global->close();

// Объединяем все активные купоны
$active_coupons = array_merge($personal_coupons, $global_coupons);

// Получаем последние заказы
$stmt_orders = $db->prepare("
    SELECT 
        o.id, 
        o.order_number, 
        o.total_price, 
        o.created_at,
        os.name as status_name, 
        os.color as status_color
    FROM orders o
    JOIN order_statuses os ON os.id = o.status_id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt_orders->bind_param("i", $user_id);
$stmt_orders->execute();
$recent_orders = $stmt_orders->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_orders->close();

// Получаем статистику пользователя
$stmt_stats = $db->prepare("
    SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(o.total_price), 0) as total_spent,
        COUNT(DISTINCT r.id) as total_reviews
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    LEFT JOIN reviews r ON r.user_id = u.id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt_stats->bind_param("i", $user_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();

// Если нет статистики, устанавливаем значения по умолчанию
if (!$stats) {
    $stats = [
        'total_orders' => 0,
        'total_spent' => 0,
        'total_reviews' => 0
    ];
}
?>

<div class="container profile-container py-4">
    <div class="row">
        <!-- Боковая панель с информацией пользователя -->
        <div class="col-md-4">
            <div class="card profile-card">
                <div class="card-body text-center">
                    <div class="profile-photo mb-3">
                        <?php if (!empty($user['photo'])): ?>
                            <img src="./photo/<?= htmlspecialchars($user['photo']); ?>" 
                                 alt="Фото профиля" 
                                 class="rounded-circle img-fluid" 
                                 style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <img src="../assets/default-profile.png" 
                                 alt="Фото профиля" 
                                 class="rounded-circle img-fluid" 
                                 style="width: 150px; height: 150px; object-fit: cover;">
                        <?php endif; ?>
                    </div>
                    
                    <h3><?= htmlspecialchars($user['full_name'] ?: $user['login']); ?></h3>
                    <p class="text-muted">@<?= htmlspecialchars($user['login']); ?></p>
                    
                    <ul class="list-group list-group-flush text-start mb-3">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Email</span>
                            <span>
                                <?= htmlspecialchars($user['email']); ?>
                                <?php if (!empty($user['email_verified'])): ?>
                                    <span class="badge bg-success" title="Email подтвержден">✓</span>
                                <?php else: ?>
                                    <span class="badge bg-warning" title="Email не подтвержден">!</span>
                                <?php endif; ?>
                            </span>
                        </li>
                        <?php if (!empty($user['phone'])): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Телефон</span>
                            <span>
                                <?= htmlspecialchars($user['phone']); ?>
                                <?php if (!empty($user['phone_verified'])): ?>
                                    <span class="badge bg-success" title="Телефон подтвержден">✓</span>
                                <?php endif; ?>
                            </span>
                        </li>
                        <?php endif; ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Дата рождения</span>
                            <span><?= $user['birth_date'] ? date('d.m.Y', strtotime($user['birth_date'])) : 'Не указана'; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>На сайте с</span>
                            <span><?= $user['created_at'] ? date('d.m.Y', strtotime($user['created_at'])) : 'Не указано'; ?></span>
                        </li>
                    </ul>

                    <?php if ($is_seller): ?>
                    <div class="seller-info p-3 bg-light rounded mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <?php if (!empty($seller['logo_url'])): ?>
                                <img src="<?= htmlspecialchars($seller['logo_url']) ?>" 
                                     alt="<?= htmlspecialchars($seller['shop_name']) ?>"
                                     class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-store fa-2x text-primary me-2"></i>
                            <?php endif; ?>
                            <h5 class="mb-0"><?= htmlspecialchars($seller['shop_name']); ?></h5>
                        </div>
                        <?php if (!empty($seller['is_verified'])): ?>
                            <span class="badge bg-success">Магазин подтвержден</span>
                        <?php endif; ?>
                        <a href="../seller/dashboard.php" class="btn btn-outline-primary btn-sm mt-2 w-100">
                            <i class="fas fa-store-alt me-2"></i>Кабинет продавца
                        </a>
                    </div>
                    <?php endif; ?>

                    <div class="profile-actions">
                        <a href="edit-profile.php" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-edit"></i> Редактировать профиль
                        </a>
                        <a href="logout.php" class="btn btn-danger w-100">
                            <i class="fas fa-sign-out-alt"></i> Выйти
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Основной контент -->
        <div class="col-md-8">
            <!-- Статистика -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="mb-0"><?= $stats['total_orders'] ?? 0; ?></h3>
                            <p class="text-muted mb-0">Всего заказов</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="mb-0"><?= $stats['total_spent'] ? formatPrice($stats['total_spent']) : '0 ₽'; ?></h3>
                            <p class="text-muted mb-0">Потрачено всего</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="mb-0"><?= $stats['total_reviews'] ?? 0; ?></h3>
                            <p class="text-muted mb-0">Отзывов оставлено</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Активные купоны -->
            <?php if (!empty($active_coupons)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-ticket-alt me-2 text-warning"></i>Доступные купоны
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($active_coupons as $coupon): 
                            $days_left = max(0, ceil((strtotime($coupon['expires_at']) - time()) / 86400));
                            $discount = $coupon['discount_type'] === 'percent' 
                                ? $coupon['discount_value'] . '%' 
                                : formatPrice($coupon['discount_value']);
                        ?>
                            <div class="col-md-6 mb-3">
                                <div class="coupon-card p-3 border rounded">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?= htmlspecialchars($coupon['name'] ?? 'Купон'); ?></h6>
                                        <span class="badge bg-primary">-<?= $discount; ?></span>
                                    </div>
                                    <p class="coupon-code mt-2 mb-1">
                                        <strong><?= htmlspecialchars($coupon['code']); ?></strong>
                                    </p>
                                    <?php if (!empty($coupon['min_order_amount'])): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-shopping-cart me-1"></i>
                                            Мин. заказ: <?= formatPrice($coupon['min_order_amount']); ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if (!empty($coupon['max_discount_amount'])): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-gift me-1"></i>
                                            Макс. скидка: <?= formatPrice($coupon['max_discount_amount']); ?>
                                        </small>
                                    <?php endif; ?>
                                    <small class="text-muted d-block">
                                        <i class="far fa-clock me-1"></i>
                                        Действует до: <?= date('d.m.Y', strtotime($coupon['expires_at'])) ?> 
                                        (<?= $days_left; ?> дн.)
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Использованные купоны -->
            <?php if (!empty($used_coupons)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2 text-secondary"></i>История использования купонов
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Код</th>
                                    <th>Скидка</th>
                                    <th>Дата использования</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($used_coupons as $coupon): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($coupon['code']) ?></code></td>
                                        <td>
                                            <?= $coupon['discount_type'] === 'percent' 
                                                ? $coupon['discount_value'] . '%' 
                                                : formatPrice($coupon['discount_value']) ?>
                                        </td>
                                        <td><?= date('d.m.Y H:i', strtotime($coupon['used_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Последние заказы -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-shopping-bag me-2 text-primary"></i>Последние заказы
                    </h5>
                    <a href="../orders.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-list"></i> Все заказы
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_orders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>№ заказа</th>
                                        <th>Дата</th>
                                        <th>Сумма</th>
                                        <th>Статус</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($order['order_number']); ?></strong>
                                        </td>
                                        <td><?= date('d.m.Y', strtotime($order['created_at'])); ?></td>
                                        <td><?= formatPrice($order['total_price']); ?></td>
                                        <td>
                                            <span class="badge" style="background-color: <?= $order['status_color']; ?>; color: white;">
                                                <?= htmlspecialchars($order['status_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../order_details.php?order_id=<?= $order['id']; ?>" 
                                               class="btn btn-sm btn-outline-secondary"
                                               title="Детали заказа">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">У вас еще нет заказов</p>
                            <a href="../index.php" class="btn btn-primary mt-3">
                                <i class="fas fa-shopping-cart me-2"></i>Начать покупки
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.coupon-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-left: 4px solid #0d6efd !important;
    transition: transform 0.2s, box-shadow 0.2s;
    height: 100%;
}
.coupon-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.coupon-code {
    font-family: 'Courier New', monospace;
    font-size: 1.1em;
    letter-spacing: 1px;
    background: white;
    padding: 5px;
    border-radius: 4px;
    text-align: center;
    border: 1px dashed #0d6efd;
}
.profile-card {
    position: sticky;
    top: 20px;
}
.profile-card .list-group-item {
    background: transparent;
    border-left: none;
    border-right: none;
    padding: 0.75rem 0;
}
</style>

<?php
require_once __DIR__ . '/../inc/footer.php';
?>