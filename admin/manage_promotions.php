<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// --- ЛОГИКА ОБРАБОТКИ (ДО ВЫВОДА HTML) ---

// 1. Удаление акции
if (isset($_POST['delete_promotion'])) {
    $promotion_id = (int)$_POST['promotion_id'];
    $stmt = $db->prepare("DELETE FROM promotions WHERE id = ?");
    $stmt->bind_param('i', $promotion_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Акция успешно удалена";
    }
    header("Location: manage_promotions.php");
    exit;
}

// 2. Добавление акции
if (isset($_POST['add_promotion'])) {
    $name = trim($_POST['name']);
    $slug = trim($_POST['slug']) ?: generateSlug($name);
    $description = trim($_POST['description']);
    $type = $_POST['type'];
    $value = (float)$_POST['value'];
    $starts_at = !empty($_POST['starts_at']) ? $_POST['starts_at'] : null;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $stmt = $db->prepare("INSERT INTO promotions (name, slug, description, type, value, starts_at, expires_at, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssdssi', $name, $slug, $description, $type, $value, $starts_at, $expires_at, $is_active);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Акция успешно создана";
    }
    header("Location: manage_promotions.php");
    exit;
}

// 3. Редактирование акции (Здесь была ошибка в bind_param)
if (isset($_POST['edit_promotion'])) {
    $promotion_id = (int)$_POST['promotion_id'];
    $name = trim($_POST['name']);
    $slug = trim($_POST['slug']) ?: generateSlug($name);
    $description = trim($_POST['description']);
    $type = $_POST['type'];
    $value = (float)$_POST['value'];
    $starts_at = !empty($_POST['starts_at']) ? $_POST['starts_at'] : null;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // 9 параметров: name, slug, description, type, value, starts_at, expires_at, is_active, id
    $stmt = $db->prepare("UPDATE promotions SET name = ?, slug = ?, description = ?, type = ?, value = ?, starts_at = ?, expires_at = ?, is_active = ? WHERE id = ?");
    
    // Исправленная строка типов: s(name) s(slug) s(desc) s(type) d(value) s(starts) s(expires) i(is_active) i(id)
    $types = 'ssssdssii'; 
    $stmt->bind_param($types, $name, $slug, $description, $type, $value, $starts_at, $expires_at, $is_active, $promotion_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Акция успешно обновлена";
    }
    header("Location: manage_promotions.php");
    exit;
}

// --- ПОДГОТОВКА ДАННЫХ ДЛЯ ВЫВОДА ---

// Параметры фильтрации
$filter_status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where = ["1=1"];
$params = [];
$param_types = "";

if ($filter_status === 'active') {
    $where[] = "is_active = 1 AND (starts_at <= NOW() OR starts_at IS NULL) AND (expires_at >= NOW() OR expires_at IS NULL)";
} elseif ($filter_status === 'inactive') {
    $where[] = "is_active = 0";
}

if ($search) {
    $where[] = "name LIKE ?";
    $params[] = "%$search%";
    $param_types .= "s";
}

$query = "SELECT * FROM promotions WHERE " . implode(" AND ", $where) . " ORDER BY created_at DESC";
$stmt = $db->prepare($query);
if ($params) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$promotions = $stmt->get_result();

// Подключаем хедер только здесь
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Управление акциями</h1>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPromotionModal">
            <i class="fas fa-plus"></i> Создать акцию
        </button>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="show"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Поиск по названию..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="all" <?= $filter_status == 'all' ? 'selected' : '' ?>>Все статусы</option>
                        <option value="active" <?= $filter_status == 'active' ? 'selected' : '' ?>>Активные</option>
                        <option value="inactive" <?= $filter_status == 'inactive' ? 'selected' : '' ?>>Неактивные</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Фильтровать</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Название</th>
                        <th>Тип</th>
                        <th>Значение</th>
                        <th>Период действия</th>
                        <th>Статус</th>
                        <th class="text-end">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($promotions->num_rows > 0): ?>
                        <?php while ($promo = $promotions->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($promo['name']) ?></strong>
                                    <div class="small text-muted"><?= htmlspecialchars($promo['slug']) ?></div>
                                </td>
                                <td><?= $promo['type'] == 'percent' ? 'Процент' : 'Фикс. сумма' ?></td>
                                <td><?= $promo['value'] ?><?= $promo['type'] == 'percent' ? '%' : ' руб.' ?></td>
                                <td>
                                    <small>
                                        С: <?= $promo['starts_at'] ? date('d.m.y', strtotime($promo['starts_at'])) : '—' ?><br>
                                        По: <?= $promo['expires_at'] ? date('d.m.y', strtotime($promo['expires_at'])) : '—' ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($promo['is_active']): ?>
                                        <span class="badge bg-success">Активна</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Черновик</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPromo<?= $promo['id'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Удалить акцию?')">
                                        <input type="hidden" name="promotion_id" value="<?= $promo['id'] ?>">
                                        <button type="submit" name="delete_promotion" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editPromo<?= $promo['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <input type="hidden" name="promotion_id" value="<?= $promo['id'] ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Редактировать акцию</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Название</label>
                                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($promo['name']) ?>" required>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Тип</label>
                                                        <select name="type" class="form-select">
                                                            <option value="percent" <?= $promo['type'] == 'percent' ? 'selected' : '' ?>>Процент (%)</option>
                                                            <option value="fixed" <?= $promo['type'] == 'fixed' ? 'selected' : '' ?>>Фикс. сумма</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Значение</label>
                                                        <input type="number" step="0.01" name="value" class="form-control" value="<?= $promo['value'] ?>" required>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Дата начала</label>
                                                        <input type="datetime-local" name="starts_at" class="form-control" value="<?= $promo['starts_at'] ? date('Y-m-d\TH:i', strtotime($promo['starts_at'])) : '' ?>">
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Дата окончания</label>
                                                        <input type="datetime-local" name="expires_at" class="form-control" value="<?= $promo['expires_at'] ? date('Y-m-d\TH:i', strtotime($promo['expires_at'])) : '' ?>">
                                                    </div>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="is_active" <?= $promo['is_active'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label">Активна</label>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                                <button type="submit" name="edit_promotion" class="btn btn-primary">Сохранить</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4">Акций не найдено</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addPromotionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Новая акция</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Тип</label>
                            <select name="type" class="form-select">
                                <option value="percent">Процент (%)</option>
                                <option value="fixed">Фикс. сумма</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Значение</label>
                            <input type="number" step="0.01" name="value" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Дата начала</label>
                            <input type="datetime-local" name="starts_at" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Дата окончания</label>
                            <input type="datetime-local" name="expires_at" class="form-control">
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" checked>
                        <label class="form-check-label">Активна</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="add_promotion" class="btn btn-success">Создать</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>