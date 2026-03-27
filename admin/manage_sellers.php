<?php
session_start();
require_once __DIR__ . '/../inc/db.php'; // Важно: БД должна быть подключена до обработки логики
require_once __DIR__ . '/check_admin.php';

// --- БЛОК ОБРАБОТКИ ДЕЙСТВИЙ (ПЕРЕНЕСЕН ВВЕРХ) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $seller_id = (int)$_POST['seller_id'];
    
    if ($_POST['action'] === 'toggle_active') {
        $stmt = $db->prepare("UPDATE sellers SET is_active = NOT is_active WHERE id = ?");
        $stmt->bind_param('i', $seller_id);
        $stmt->execute();
        $_SESSION['success'] = "Статус продавца изменен";
    }
    
    if ($_POST['action'] === 'toggle_verified') {
        $stmt = $db->prepare("UPDATE sellers SET is_verified = NOT is_verified WHERE id = ?");
        $stmt->bind_param('i', $seller_id);
        $stmt->execute();
        $_SESSION['success'] = "Статус верификации изменен";
    }
    
    if ($_POST['action'] === 'update_commission') {
        $commission = (float)$_POST['commission_rate'];
        $stmt = $db->prepare("UPDATE sellers SET commission_rate = ? WHERE id = ?");
        $stmt->bind_param('di', $commission, $seller_id);
        $stmt->execute();
        $_SESSION['success'] = "Комиссия обновлена";
    }
    
    // Перенаправление теперь сработает без ошибок
    header("Location: manage_sellers.php?" . http_build_query($_GET));
    exit;
}

// --- ТЕПЕРЬ МОЖНО ПОДКЛЮЧАТЬ HTML-ЗАГОЛОВОК ---
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/functions.php';

// Параметры фильтрации (оставляем как было)
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_verified = isset($_GET['verified']) ? $_GET['verified'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Настройка пагинации
$items_per_page = isset($_GET['items_per_page']) && is_numeric($_GET['items_per_page']) ? (int)$_GET['items_per_page'] : 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

// Построение WHERE условий
$where_conditions = ["1=1"];
$params = [];
$types = "";

if ($filter_status !== 'all') {
    $where_conditions[] = "s.is_active = ?";
    $params[] = $filter_status === 'active' ? 1 : 0;
    $types .= "i";
}

if ($filter_verified !== 'all') {
    $where_conditions[] = "s.is_verified = ?";
    $params[] = $filter_verified === 'verified' ? 1 : 0;
    $types .= "i";
}

if (!empty($search)) {
    $where_conditions[] = "(s.shop_name LIKE ? OR s.shop_slug LIKE ? OR s.contact_email LIKE ? OR u.full_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

$where_clause = implode(" AND ", $where_conditions);

// Подсчет общего количества
$count_sql = "
    SELECT COUNT(*) as count
    FROM sellers s
    JOIN users u ON u.id = s.user_id
    WHERE $where_clause
";
$count_stmt = $db->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_sellers = $count_stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_sellers / $items_per_page);

// Получение продавцов
$sql = "
    SELECT 
        s.*,
        u.full_name as owner_name,
        u.email as owner_email,
        u.phone as owner_phone
    FROM sellers s
    JOIN users u ON u.id = s.user_id
    WHERE $where_clause
    ORDER BY s.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $db->prepare($sql);
$final_params = $params;
$final_params[] = $items_per_page;
$final_params[] = $offset;
$final_types = $types . "ii";

$stmt->bind_param($final_types, ...$final_params);
$stmt->execute();
$sellers = $stmt->get_result();

// Получение статистики
$stats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(is_active) as active,
        SUM(is_verified) as verified,
        AVG(rating) as avg_rating,
        SUM(products_count) as total_products,
        SUM(reviews_count) as total_reviews
    FROM sellers
")->fetch_assoc();
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Управление продавцами</h1>
        <a href="add_seller.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Добавить продавца
        </a>
    </div>

    <!-- Статистика -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h6 class="card-title">Всего</h6>
                    <h3 class="mb-0"><?= $stats['total'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6 class="card-title">Активных</h6>
                    <h3 class="mb-0"><?= $stats['active'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6 class="card-title">Верифицировано</h6>
                    <h3 class="mb-0"><?= $stats['verified'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h6 class="card-title">Ср. рейтинг</h6>
                    <h3 class="mb-0"><?= number_format($stats['avg_rating'] ?? 0, 1) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-white bg-secondary">
                <div class="card-body">
                    <h6 class="card-title">Товаров</h6>
                    <h3 class="mb-0"><?= $stats['total_products'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-white bg-dark">
                <div class="card-body">
                    <h6 class="card-title">Отзывов</h6>
                    <h3 class="mb-0"><?= $stats['total_reviews'] ?></h3>
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
                        <option value="all" <?= $filter_status == 'all' ? 'selected' : '' ?>>Все</option>
                        <option value="active" <?= $filter_status == 'active' ? 'selected' : '' ?>>Активные</option>
                        <option value="inactive" <?= $filter_status == 'inactive' ? 'selected' : '' ?>>Неактивные</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Верификация</label>
                    <select name="verified" class="form-select">
                        <option value="all" <?= $filter_verified == 'all' ? 'selected' : '' ?>>Все</option>
                        <option value="verified" <?= $filter_verified == 'verified' ? 'selected' : '' ?>>Верифицированные</option>
                        <option value="unverified" <?= $filter_verified == 'unverified' ? 'selected' : '' ?>>Неверифицированные</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Поиск</label>
                    <input type="text" name="search" class="form-control" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Название магазина, email, владелец...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">На странице</label>
                    <select name="items_per_page" class="form-select">
                        <?php foreach ([10, 20, 30, 50] as $num): ?>
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

    <!-- Таблица продавцов -->
    <div class="card">
        <div class="card-body">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Магазин</th>
                            <th>Владелец</th>
                            <th>Контакты</th>
                            <th>Рейтинг</th>
                            <th>Товары</th>
                            <th>Комиссия</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($sellers->num_rows > 0): ?>
                            <?php while ($seller = $sellers->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $seller['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($seller['logo_url'])): ?>
                                                <img src="<?= $base_url . htmlspecialchars($seller['logo_url']) ?>" 
                                                     alt="<?= htmlspecialchars($seller['shop_name']) ?>"
                                                     class="me-2" style="width: 40px; height: 40px; object-fit: contain;">
                                            <?php else: ?>
                                                <i class="fas fa-store me-2 fa-2x text-muted"></i>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= htmlspecialchars($seller['shop_name']) ?></strong>
                                                <br>
                                                <small class="text-muted">@<?= htmlspecialchars($seller['shop_slug']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($seller['owner_name'] ?: '—') ?>
                                        <br>
                                        <small class="text-muted">ID: <?= $seller['user_id'] ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($seller['contact_email']) ?>
                                        <?php if (!empty($seller['contact_phone'])): ?>
                                            <br><small><?= htmlspecialchars($seller['contact_phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="text-warning">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i <= round($seller['rating']) ? '' : '-o' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="text-muted"><?= number_format($seller['rating'], 1) ?> (<?= $seller['reviews_count'] ?>)</small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?= $seller['products_count'] ?></span>
                                    </td>
                                    <td>
                                        <form method="POST" class="commission-form" style="width: 80px;">
                                            <input type="hidden" name="seller_id" value="<?= $seller['id'] ?>">
                                            <input type="hidden" name="action" value="update_commission">
                                            <div class="input-group input-group-sm">
                                                <input type="number" name="commission_rate" 
                                                       value="<?= $seller['commission_rate'] ?>" 
                                                       step="0.1" min="0" max="100" 
                                                       class="form-control form-control-sm"
                                                       onchange="this.form.submit()">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </form>
                                    </td>
                                    <td>
                                        <?php if ($seller['is_verified']): ?>
                                            <span class="badge bg-success" title="Верифицирован">✓</span>
                                        <?php endif; ?>
                                        <?php if ($seller['is_active']): ?>
                                            <span class="badge bg-primary">Активен</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Неактивен</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="edit_seller.php?id=<?= $seller['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="seller_products.php?seller_id=<?= $seller['id'] ?>" 
                                               class="btn btn-sm btn-outline-info" title="Товары продавца">
                                                <i class="fas fa-box"></i>
                                            </a>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="seller_id" value="<?= $seller['id'] ?>">
                                                <input type="hidden" name="action" value="toggle_active">
                                                <button type="submit" class="btn btn-sm btn-outline-warning" 
                                                        title="<?= $seller['is_active'] ? 'Деактивировать' : 'Активировать' ?>">
                                                    <i class="fas <?= $seller['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="seller_id" value="<?= $seller['id'] ?>">
                                                <input type="hidden" name="action" value="toggle_verified">
                                                <button type="submit" class="btn btn-sm btn-outline-success" 
                                                        title="<?= $seller['is_verified'] ? 'Снять верификацию' : 'Верифицировать' ?>">
                                                    <i class="fas fa-certificate"></i>
                                                </button>
                                            </form>
                                            <a href="seller_transactions.php?seller_id=<?= $seller['id'] ?>" 
                                               class="btn btn-sm btn-outline-secondary" title="Транзакции">
                                                <i class="fas fa-money-bill"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="fas fa-store fa-3x text-muted mb-3"></i>
                                    <p class="mb-0">Продавцы не найдены</p>
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

<style>
.commission-form input {
    text-align: right;
    padding-right: 2px;
}
</style>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>