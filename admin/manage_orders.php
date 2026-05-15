<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/functions.php';

// Параметры фильтрации
$filter_status = isset($_GET['status']) ? (int)$_GET['status'] : 0;
$filter_seller = isset($_GET['seller']) ? (int)$_GET['seller'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Настройка пагинации
$items_per_page = isset($_GET['items_per_page']) && is_numeric($_GET['items_per_page']) ? (int)$_GET['items_per_page'] : 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

// Построение WHERE условий
$where_conditions = ["1=1"];
$params = [];
$types = "";

if ($filter_status > 0) {
    $where_conditions[] = "o.status_id = ?";
    $params[] = $filter_status;
    $types .= "i";
}

if (!empty($search)) {
    $where_conditions[] = "(o.order_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Подсчет общего количества заказов
$count_sql = "
    SELECT COUNT(DISTINCT o.id) as count
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE $where_clause
";
$count_stmt = $db->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_orders = $count_stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_orders / $items_per_page);

// Получение заказов
$sql = "
    SELECT 
        o.id,
        o.order_number,
        o.total_price,
        o.created_at,
        o.user_id,
        u.full_name as customer_name,
        u.email as customer_email,
        u.phone as customer_phone,
        os.name as status_name,
        os.color as status_color,
        os.slug as status_slug,
        COUNT(DISTINCT oseller.id) as sellers_count,
        COUNT(DISTINCT oi.id) as items_count
    FROM orders o
    JOIN users u ON u.id = o.user_id
    JOIN order_statuses os ON os.id = o.status_id
    LEFT JOIN order_sellers oseller ON oseller.order_id = o.id
    LEFT JOIN order_items oi ON oi.order_seller_id = oseller.id
    WHERE $where_clause
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $db->prepare($sql);
$params[] = $items_per_page;
$params[] = $offset;
$types .= "ii";

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result();

// Получение списка статусов для фильтра
$statuses = $db->query("SELECT id, name, color FROM order_statuses ORDER BY sort_order");

// Получение статистики
$stats_sql = "
    SELECT 
        COUNT(*) as total_orders,
        SUM(total_price) as total_revenue,
        AVG(total_price) as avg_order,
        COUNT(DISTINCT user_id) as unique_customers
    FROM orders
    WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
";
$stats = $db->query($stats_sql)->fetch_assoc();
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Управление заказами</h1>
        <a href="export_orders.php" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Экспорт
        </a>
    </div>

    <!-- Статистика -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h6 class="card-title">Заказов за 30 дней</h6>
                    <h3 class="mb-0"><?= $stats['total_orders'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6 class="card-title">Выручка за 30 дней</h6>
                    <h3 class="mb-0"><?= formatPrice($stats['total_revenue'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6 class="card-title">Средний чек</h6>
                    <h3 class="mb-0"><?= formatPrice($stats['avg_order'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h6 class="card-title">Уникальных покупателей</h6>
                    <h3 class="mb-0"><?= $stats['unique_customers'] ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Фильтры</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Статус</label>
                    <select name="status" class="form-select">
                        <option value="0">Все статусы</option>
                        <?php while ($status = $statuses->fetch_assoc()): ?>
                            <option value="<?= $status['id'] ?>" <?= $filter_status == $status['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Поиск</label>
                    <input type="text" name="search" class="form-control" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="№ заказа, имя, email">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Дата с</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Дата по</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">На странице</label>
                    <select name="items_per_page" class="form-select">
                        <?php foreach ([10, 20, 30, 50, 100] as $num): ?>
                            <option value="<?= $num ?>" <?= $items_per_page == $num ? 'selected' : '' ?>><?= $num ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Применить
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица заказов -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>№ заказа</th>
                            <th>Покупатель</th>
                            <th>Сумма</th>
                            <th>Товаров</th>
                            <th>Продавцов</th>
                            <th>Статус</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders->num_rows > 0): ?>
                            <?php while ($order = $orders->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($order['customer_name'] ?: '—') ?></strong>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($order['customer_email']) ?></small>
                                        <?php if (!empty($order['customer_phone'])): ?>
                                            <br><small><?= htmlspecialchars($order['customer_phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-primary"><?= formatPrice($order['total_price']) ?></strong>
                                    </td>
                                    <td class="text-center"><?= $order['items_count'] ?></td>
                                    <td class="text-center"><?= $order['sellers_count'] ?></td>
                                    <td>
                                        <span class="badge" style="background-color: <?= $order['status_color'] ?>; color: white;">
                                            <?= htmlspecialchars($order['status_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= date('d.m.Y', strtotime($order['created_at'])) ?>
                                        <br>
                                        <small class="text-muted"><?= date('H:i', strtotime($order['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view_order.php?id=<?= $order['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Просмотр">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_order.php?id=<?= $order['id'] ?>" 
                                               class="btn btn-sm btn-outline-secondary" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="change_order_status.php?id=<?= $order['id'] ?>" 
                                               class="btn btn-sm btn-outline-warning" title="Изменить статус">
                                                <i class="fas fa-sync-alt"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteOrder(<?= $order['id'] ?>, '<?= $order['order_number'] ?>')"
                                                    title="Удалить">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                    <p class="mb-0">Заказы не найдены</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Пагинация -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Модальное окно подтверждения удаления -->
<div class="modal fade" id="deleteOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Подтверждение удаления</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Вы уверены, что хотите удалить заказ <strong id="orderNumber"></strong>?</p>
                <p class="text-danger"><small>Это действие нельзя отменить.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <a href="#" id="confirmOrderDelete" class="btn btn-danger">Удалить</a>
            </div>
        </div>
    </div>
</div>

<script>
function deleteOrder(id, number) {
    document.getElementById('orderNumber').textContent = number;
    document.getElementById('confirmOrderDelete').href = 'delete_order.php?id=' + id;
    
    var modal = new bootstrap.Modal(document.getElementById('deleteOrderModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>