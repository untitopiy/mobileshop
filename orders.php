<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';

// Проверка авторизации пользователя
if (!isset($_SESSION['id'])) {
    header("Location: auth.php");
    exit;
}

$user_id = $_SESSION['id'];

// Получаем список заказов пользователя
$orders_query = $db->prepare("
    SELECT id, total_price, status, created_at 
    FROM orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$orders_query->bind_param('i', $user_id);
$orders_query->execute();
$orders_result = $orders_query->get_result();

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
?>

<div class="container orders-container">
    <h1>📦 Мои заказы</h1>
    <?php if ($orders_result->num_rows === 0): ?>
        <p class="alert alert-info">Вы ещё не совершали заказов.</p>
    <?php else: ?>
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Номер заказа</th>
                    <th>Сумма</th>
                    <th>Статус</th>
                    <th>Дата</th>
                    <th>Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($order = $orders_result->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= $order['id']; ?></td>
                        <td><?= number_format($order['total_price'], 0, ',', ' '); ?> руб.</td>
                        <td><?= htmlspecialchars(translate_status($order['status'])); ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($order['created_at'])); ?></td>
                        <td>
                            <a href="order_details.php?order_id=<?= $order['id']; ?>" class="btn btn-sm btn-primary">Подробнее</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
