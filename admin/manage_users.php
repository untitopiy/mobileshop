<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Параметры
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_admin = isset($_GET['is_admin']) ? $_GET['is_admin'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Пагинация
$items_per_page = isset($_GET['items_per_page']) && is_numeric($_GET['items_per_page']) ? (int)$_GET['items_per_page'] : 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

// WHERE
$where_conditions = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(u.login LIKE ? OR u.email LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= "ssss";
}

if ($filter_admin !== '') {
    $where_conditions[] = "u.is_admin = ?";
    $params[] = (int)$filter_admin;
    $types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Сортировка
$order_by = match($sort) {
    'name_asc' => "u.full_name ASC",
    'name_desc' => "u.full_name DESC",
    'login_asc' => "u.login ASC",
    'oldest' => "u.created_at ASC",
    'last_login' => "u.last_login DESC",
    default => "u.created_at DESC"
};

// Подсчёт
$count_sql = "SELECT COUNT(*) as count FROM users u WHERE $where_clause";
$count_stmt = $db->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_users / $items_per_page);

// Список пользователей
$sql = "
    SELECT 
        u.id, u.login, u.email, u.full_name, u.photo, u.birth_date,
        u.is_admin, u.created_at, u.is_subscribed, u.last_login,
        u.phone, u.phone_verified, u.email_verified, u.wishlist_count,
        (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as orders_count,
        (SELECT COUNT(*) FROM reviews WHERE user_id = u.id) as reviews_count
    FROM users u
    WHERE $where_clause
    ORDER BY $order_by
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
$users = $stmt->get_result();

// Статистика
$stats = $db->query("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_week,
        SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified_email
    FROM users
")->fetch_assoc();

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Управление пользователями</h1>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Назад</a>
    </div>

    <!-- Статистика -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h6>Всего пользователей</h6>
                    <h2><?= $stats['total_users'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h6>Администраторов</h6>
                    <h2><?= $stats['admin_count'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6>Активных (7 дней)</h6>
                    <h2><?= $stats['active_week'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6>Подтверждено email</h6>
                    <h2><?= $stats['verified_email'] ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Фильтры</h5></div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Поиск</label>
                    <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Логин, email, имя, телефон">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Роль</label>
                    <select name="is_admin" class="form-select">
                        <option value="">Все</option>
                        <option value="1" <?= $filter_admin === '1' ? 'selected' : '' ?>>Админ</option>
                        <option value="0" <?= $filter_admin === '0' ? 'selected' : '' ?>>Пользователь</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Сортировка</label>
                    <select name="sort" class="form-select">
                        <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Сначала новые</option>
                        <option value="oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>>Сначала старые</option>
                        <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>Имя А-Я</option>
                        <option value="last_login" <?= $sort == 'last_login' ? 'selected' : '' ?>>Последний вход</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">На странице</label>
                    <select name="items_per_page" class="form-select">
                        <?php foreach ([10, 20, 50, 100] as $num): ?>
                            <option value="<?= $num ?>" <?= $items_per_page == $num ? 'selected' : '' ?>><?= $num ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Применить</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Пользователь</th>
                            <th>Контакты</th>
                            <th>Роль</th>
                            <th>Верификация</th>
                            <th>Активность</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= $user['photo'] ? '../' . htmlspecialchars($user['photo']) : '../assets/default-profile.png' ?>" 
                                                 class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                            <div>
                                                <strong><?= htmlspecialchars($user['full_name'] ?: $user['login']) ?></strong><br>
                                                <small class="text-muted">@<?= htmlspecialchars($user['login']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small>
                                            <i class="fas fa-envelope text-muted"></i> <?= htmlspecialchars($user['email'] ?: '—') ?><br>
                                            <i class="fas fa-phone text-muted"></i> <?= htmlspecialchars($user['phone'] ?: '—') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="badge bg-danger">Админ</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Пользователь</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $user['email_verified'] ? 'success' : 'warning text-dark' ?>">
                                            Email <?= $user['email_verified'] ? '✓' : '✗' ?>
                                        </span><br>
                                        <span class="badge bg-<?= $user['phone_verified'] ? 'success' : 'warning text-dark' ?>">
                                            Телефон <?= $user['phone_verified'] ? '✓' : '✗' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            Рег: <?= $user['created_at'] ? date('d.m.Y', strtotime($user['created_at'])) : '—' ?><br>
                                            Вход: <?= $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Никогда' ?><br>
                                            <span class="text-muted">Заказов: <?= $user['orders_count'] ?></span>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-outline-primary" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-warning" 
                                                    onclick="toggleAdmin(<?= $user['id'] ?>, <?= $user['is_admin'] ?>)"
                                                    title="<?= $user['is_admin'] ? 'Снять админа' : 'Назначить админом' ?>">
                                                <i class="fas fa-user-shield"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['id']): ?>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['login'], ENT_QUOTES) ?>')"
                                                        title="Удалить">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-users fa-3x mb-3"></i><br>Пользователи не найдены
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
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"><i class="fas fa-angle-left"></i></a></li>
                        <?php endif; ?>
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"><i class="fas fa-angle-right"></i></a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Модалка удаления -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Удаление пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Удалить пользователя <strong id="delLogin"></strong>?<br>
                <small class="text-danger">Все данные будут удалены безвозвратно.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <a href="#" id="delLink" class="btn btn-danger">Удалить</a>
            </div>
        </div>
    </div>
</div>

<script>
function deleteUser(id, login) {
    document.getElementById('delLogin').textContent = login;
    document.getElementById('delLink').href = 'delete_user.php?id=' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
function toggleAdmin(id, isAdmin) {
    const action = isAdmin ? 'снять права администратора' : 'назначить администратором';
    if (confirm('Вы уверены, что хотите ' + action + '?')) {
        location.href = 'toggle_admin.php?id=' + id;
    }
}
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>