<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

// Функция для визуального перевода статусов
function translateStatus($status) {
    switch ($status) {
        case 'pending': 
            return '<span class="badge bg-secondary">Ожидание</span>';
        case 'processing': 
            return '<span class="badge bg-warning text-dark">В обработке</span>';
        case 'shipped': 
            return '<span class="badge bg-info">Отправлен</span>';
        case 'delivered': 
            return '<span class="badge bg-success">Доставлен</span>';
        case 'cancelled': 
            return '<span class="badge bg-danger">Отменён</span>';
        default: 
            return $status;
    }
}

// Настройки пагинации
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $items_per_page;

// Получаем общее количество заказов
$countResult = $db->query("SELECT COUNT(*) AS count FROM orders");
$total_orders = $countResult->fetch_assoc()['count'];
$total_pages = ceil($total_orders / $items_per_page);

// Получаем заказы с данными пользователя (для вывода ФИО)
$stmt = $db->prepare("SELECT o.id, o.user_id, o.total_price, o.status, o.created_at, u.full_name 
                      FROM orders o 
                      JOIN users u ON o.user_id = u.id 
                      ORDER BY o.created_at DESC 
                      LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $items_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container manage-orders-container my-5">

    <h1>Управление заказами</h1>
    <table class="table table-bordered table-striped mt-4">
        <thead class="table-light">
            <tr>
                <th>ID заказа</th>
                <th>Пользователь</th>
                <th>Сумма</th>
                <th>Статус</th>
                <th>Дата создания</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($order = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $order['id'] ?></td>
                    <td><?= htmlspecialchars($order['full_name']) ?></td>
                    <td><?= number_format($order['total_price'], 0, ',', ' ') ?> руб.</td>
                    <td><?= translateStatus($order['status']) ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                    <td>
                        <div class="btn-group" role="group">
                            <a href="view_order.php?id=<?= $order['id'] ?>" class="btn btn-sm action-btn">Отобразить</a>
                            <a href="edit_order.php?id=<?= $order['id'] ?>" class="btn btn-sm action-btn">Редактировать</a>
                            <a href="change_status.php?id=<?= $order['id'] ?>" class="btn btn-sm action-btn">Изменить статус</a>
                            <a href="delete_order.php?id=<?= $order['id'] ?>" class="btn btn-sm action-btn" onclick="return confirm('Вы уверены, что хотите удалить этот заказ?');">Удалить</a>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Пагинация -->
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page - 1 ?>">«</a>
                </li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page + 1 ?>">»</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <!-- Нижняя панель навигации -->
    <div class="d-flex justify-content-start mt-4">
        <a href="index.php" class="btn btn-bottom me-2">Админка</a>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
