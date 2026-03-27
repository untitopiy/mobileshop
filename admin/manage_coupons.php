<?php
// 1. ИНИЦИАЛИЗАЦИЯ И СЕССИЯ
session_start();

// 2. ПОДКЛЮЧЕНИЕ БД И ФУНКЦИЙ (БЕЗ ВЫВОДА HTML)
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// 3. ПРОВЕРКА ПРАВ (Файл check_admin.php должен содержать только PHP и exit в конце при неудаче)
require_once __DIR__ . '/check_admin.php'; 

// 4. ОБРАБОТКА ДЕЙСТВИЙ (POST/GET) - ДО ВЫВОДА HTML
// Обработка удаления
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->query("DELETE FROM coupons WHERE id = $id");
    $_SESSION['success'] = "Купон удален";
    header("Location: manage_coupons.php");
    exit;
}

// Обработка изменения статуса
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    $db->query("UPDATE coupons SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = $id");
    $_SESSION['success'] = "Статус купона изменен";
    header("Location: manage_coupons.php");
    exit;
}

// Обработка добавления купона
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $code = strtoupper(trim($_POST['code']));
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];
    $discount_type = $_POST['discount_type'];
    $discount_value = (float)$_POST['discount_value'];
    $min_order_amount = !empty($_POST['min_order_amount']) ? (float)$_POST['min_order_amount'] : null;
    $max_discount_amount = !empty($_POST['max_discount_amount']) ? (float)$_POST['max_discount_amount'] : null;
    $usage_limit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
    $usage_limit_per_user = !empty($_POST['usage_limit_per_user']) ? (int)$_POST['usage_limit_per_user'] : 1;
    $starts_at = $_POST['starts_at'];
    $expires_at = $_POST['expires_at'];
    
    if (empty($code)) {
        $code = strtoupper(bin2hex(random_bytes(4)));
    }
    
    $check = $db->prepare("SELECT id FROM coupons WHERE code = ?");
    $check->bind_param('s', $code);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Купон с таким кодом уже существует";
    } else {
        $stmt = $db->prepare("
            INSERT INTO coupons (
                code, name, description, type, discount_type, discount_value,
                min_order_amount, max_discount_amount, usage_limit, usage_limit_per_user,
                starts_at, expires_at, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->bind_param(
            "sssssddiisss",
            $code, $name, $description, $type, $discount_type, $discount_value,
            $min_order_amount, $max_discount_amount, $usage_limit, $usage_limit_per_user,
            $starts_at, $expires_at
        );
        
        if ($stmt->execute()) {
            $coupon_id = $stmt->insert_id;
            
            // Привязка товаров
            if (isset($_POST['products']) && is_array($_POST['products'])) {
                $prod_stmt = $db->prepare("INSERT INTO coupon_products (coupon_id, product_id) VALUES (?, ?)");
                foreach ($_POST['products'] as $product_id) {
                    $prod_stmt->bind_param('ii', $coupon_id, $product_id);
                    $prod_stmt->execute();
                }
            }
            
            // Привязка категорий
            if (isset($_POST['categories']) && is_array($_POST['categories'])) {
                $cat_stmt = $db->prepare("INSERT INTO coupon_categories (coupon_id, category_id) VALUES (?, ?)");
                foreach ($_POST['categories'] as $category_id) {
                    $cat_stmt->bind_param('ii', $coupon_id, $category_id);
                    $cat_stmt->execute();
                }
            }
            $_SESSION['success'] = "Купон успешно создан";
        } else {
            $_SESSION['error'] = "Ошибка при создании купона";
        }
    }
    header("Location: manage_coupons.php");
    exit;
}

// 5. ПОДГОТОВКА ДАННЫХ ДЛЯ ОТОБРАЖЕНИЯ
$filter_status = $_GET['status'] ?? 'all';
$filter_type = $_GET['type'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$items_per_page = (isset($_GET['items_per_page']) && is_numeric($_GET['items_per_page'])) ? (int)$_GET['items_per_page'] : 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $items_per_page;

$where_conditions = ["1=1"];
$params = [];
$types = "";

if ($filter_status !== 'all') {
    $where_conditions[] = "c.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($filter_type !== 'all') {
    $where_conditions[] = "c.type = ?";
    $params[] = $filter_type;
    $types .= "s";
}
if (!empty($search)) {
    $where_conditions[] = "(c.code LIKE ? OR c.name LIKE ?)";
    $st = "%$search%";
    $params[] = $st; $params[] = $st;
    $types .= "ss";
}

$where_clause = implode(" AND ", $where_conditions);

// Считаем общее количество
$count_stmt = $db->prepare("SELECT COUNT(*) as count FROM coupons c WHERE $where_clause");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_coupons = $count_stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_coupons / $items_per_page);

// Получаем купоны
$sql = "SELECT c.*, COUNT(cu.id) as actual_usage 
        FROM coupons c 
        LEFT JOIN coupon_usage cu ON cu.coupon_id = c.id 
        WHERE $where_clause 
        GROUP BY c.id 
        ORDER BY c.created_at DESC 
        LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$final_params = $params;
$final_params[] = $items_per_page;
$final_params[] = $offset;
$stmt->bind_param($types . "ii", ...$final_params);
$stmt->execute();
$coupons = $stmt->get_result();

// Вспомогательные списки
$products_list = $db->query("SELECT id, CONCAT(brand, ' ', name) as title FROM products WHERE status = 'active' LIMIT 100");
$categories_list = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");

// 6. ВЫВОД (ТЕПЕРЬ МОЖНО ПОДКЛЮЧАТЬ HEADER)
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Управление купонами</h1>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCouponModal">
            <i class="fas fa-plus"></i> Создать купон
        </button>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Поиск</label>
                    <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Код или название">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Статус</label>
                    <select name="status" class="form-select">
                        <option value="all">Все</option>
                        <option value="active" <?= $filter_status == 'active' ? 'selected' : '' ?>>Активные</option>
                        <option value="inactive" <?= $filter_status == 'inactive' ? 'selected' : '' ?>>Неактивные</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Применить</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Код / Название</th>
                        <th>Скидка</th>
                        <th>Использовано</th>
                        <th>Срок действия</th>
                        <th>Статус</th>
                        <th class="text-end">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($c = $coupons->fetch_assoc()): 
                        $is_expired = strtotime($c['expires_at']) < time();
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($c['code']) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($c['name']) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-primary">
                                <?= $c['discount_value'] ?><?= ($c['discount_type'] == 'percent' ? '%' : ' руб.') ?>
                            </span>
                        </td>
                        <td><?= $c['actual_usage'] ?> / <?= $c['usage_limit'] ?: '∞' ?></td>
                        <td>
                            <small>С: <?= date('d.m.y', strtotime($c['starts_at'])) ?><br>До: <?= date('d.m.y', strtotime($c['expires_at'])) ?></small>
                        </td>
                        <td>
                            <?php if ($is_expired): ?>
                                <span class="badge bg-danger">Истек</span>
                            <?php else: ?>
                                <span class="badge bg-<?= $c['status'] == 'active' ? 'success' : 'secondary' ?>">
                                    <?= $c['status'] == 'active' ? 'Активен' : 'Пауза' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group">
                                <a href="?toggle_status=<?= $c['id'] ?>" class="btn btn-sm btn-outline-warning">
                                    <i class="fas <?= $c['status'] == 'active' ? 'fa-pause' : 'fa-play' ?>"></i>
                                </a>
                                <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Удалить?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addCouponModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Новый купон</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Код (пусто для авто)</label>
                    <input type="text" name="code" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Название *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Тип скидки</label>
                    <select name="discount_type" class="form-select">
                        <option value="percent">Процент %</option>
                        <option value="fixed">Фикс сумма</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Значение *</label>
                    <input type="number" name="discount_value" step="0.01" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Лимит (0 - безлим)</label>
                    <input type="number" name="usage_limit" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Дата начала</label>
                    <input type="date" name="starts_at" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Дата окончания</label>
                    <input type="date" name="expires_at" class="form-control" value="<?= date('Y-m-d', strtotime('+1 month')) ?>">
                </div>
                <input type="hidden" name="type" value="global">
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_coupon" class="btn btn-success">Создать</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>