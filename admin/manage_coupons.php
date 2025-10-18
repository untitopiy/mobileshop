 <?php
session_start();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/header.php';


if (!isset($_SESSION['id'])) {
    header("Location: ../pages/auth.php");
    exit;
}

$user_id = $_SESSION['id'];
$stmt = $db->prepare("SELECT is_admin FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($is_admin);
$stmt->fetch();
$stmt->close();

if (!$is_admin) {
    http_response_code(403);
    echo "<div class='container'><h2>Доступ запрещен</h2></div>";
    exit;
}

$message = '';

// POST обработка
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $coupon_id = (int)($_POST['coupon_id'] ?? 0);

    if ($_POST['action'] === 'delete') {
        $stmt = $db->prepare("DELETE FROM coupons WHERE id=?");
        $stmt->bind_param("i", $coupon_id);
        $stmt->execute();
        $stmt->close();
        $message = "Купон удалён.";
    }

    if ($_POST['action'] === 'edit') {
        $type = $_POST['type'];
        $value = (float)$_POST['value'];
        $scope = $_POST['scope'] ?? 'global';
        $user_id_coupon = $scope === 'personal' && !empty($_POST['user_id']) ? (int)$_POST['user_id'] : NULL;
        $product_id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : NULL;
        $expires_at = $_POST['expires_at'] ?? '';
        if ($expires_at === '' || $expires_at === '0000-00-00') $expires_at = date('Y-m-d', strtotime('+30 days'));

        $stmt = $db->prepare("UPDATE coupons SET discount_type=?, discount_value=?, user_id=?, product_id=?, expires_at=?, type=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("ssdissi", $type, $value, $user_id_coupon, $product_id, $expires_at, $scope, $coupon_id);
            $stmt->execute();
            $stmt->close();
            $message = "Купон обновлён.";
        }
    }
}

// Добавление купонов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $type = $_POST['type'];
    $value = (float)$_POST['value'];
    $scope = $_POST['scope'] ?? 'global';
    $user_id_coupon = $scope === 'personal' ? (int)($_POST['user_id'] ?? 0) : NULL;
    $product_id = isset($_POST['product_id']) && $_POST['product_id'] !== '' ? (int)$_POST['product_id'] : NULL;
    $expires_at = $_POST['expires_at'] ?? '';
    if ($expires_at === '' || $expires_at === '0000-00-00') $expires_at = date('Y-m-d', strtotime('+30 days'));
    $count = max(1, (int)($_POST['count'] ?? 1));

    for ($i = 0; $i < $count; $i++) {
        $code = strtoupper(bin2hex(random_bytes(4)));
        $stmt = $db->prepare("INSERT INTO coupons (code, type, discount_type, discount_value, user_id, product_id, expires_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
        if ($stmt) {
            $stmt->bind_param("sssdiis", $code, $scope, $type, $value, $user_id_coupon, $product_id, $expires_at);
            $stmt->execute();
            $stmt->close();
        }
    }
    $message = "Создано $count купон(ов).";
}

// Списки пользователей и товаров
$users = $db->query("SELECT id, login FROM users WHERE is_admin=0 ORDER BY login");
$products = $db->query("SELECT id, CONCAT(brand,' ',name,' ',model) AS title FROM smartphones 
                        UNION 
                        SELECT id, CONCAT(brand,' ',name,' ',model) FROM accessories ORDER BY title ASC");

// Пагинация
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$totalResult = $db->query("SELECT COUNT(*) AS total FROM coupons");
$totalRow = $totalResult->fetch_assoc();
$totalCoupons = (int)$totalRow['total'];
$totalPages = ceil($totalCoupons / $perPage);

$result = $db->query("
    SELECT c.*, u.login, p.title AS product_title 
    FROM coupons c 
    LEFT JOIN users u ON c.user_id=u.id 
    LEFT JOIN (
        SELECT id, CONCAT(brand,' ',name,' ',model) AS title FROM smartphones 
        UNION 
        SELECT id, CONCAT(brand,' ',name,' ',model) FROM accessories
    ) p ON c.product_id=p.id 
    ORDER BY c.created_at DESC
    LIMIT $perPage OFFSET $offset
");

?> 

<link rel="stylesheet" href="../inc/styles.css">

<div class="admin-wrapper">
    <h1 class="admin-title">Управление купонами</h1>
    <?php if($message): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" class="coupon-form card">
        <div class="form-grid">
            <div class="form-row">
                <label>Тип скидки:</label>
                <select name="type" class="input-select">
                    <option value="percent">Процент</option>
                    <option value="fixed">Фиксированная сумма</option>
                </select>
            </div>
            <div class="form-row">
                <label>Значение:</label>
                <input type="number" name="value" min="1" step="0.01" class="input-text">
            </div>
            <div class="form-row">
                <label>Тип купона:</label>
                <select name="scope" class="input-select">
                    <option value="global">Глобальный</option>
                    <option value="personal">Персональный</option>
                </select>
            </div>
            <div class="form-row">
                <label>Пользователь:</label>
                <select name="user_id" class="input-select">
                    <option value="">—Выбрать—</option>
                    <?php while($u = $users->fetch_assoc()): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['login']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Применить к товару:</label>
                <select name="product_id" class="input-select">
                    <option value="">—Все товары—</option>
                    <?php while($p = $products->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Срок действия:</label>
                <input type="date" name="expires_at" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" class="input-text">
            </div>
            <div class="form-row" style="grid-column: span 2;">
                <label>Количество:</label>
                <input type="number" name="count" min="1" value="1" class="input-text">
            </div>
        </div>
        <button type="submit" name="add_coupon" class="btn btn-primary full-width">Создать купон(ы)</button>
    </form>

    <h2 class="admin-subtitle">Список купонов</h2>
    <table class="table card">
        <thead>
            <tr>
                <th>Код</th><th>Тип</th><th>Значение</th><th>Пользователь</th>
                <th>Товар</th><th>Срок</th><th>Статус</th><th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <form method="post">
                    <td><?= htmlspecialchars($row['code']) ?></td>
                    <td>
                        <select name="type" class="input-select">
                            <option value="percent" <?= $row['discount_type']==='percent'?'selected':'' ?>>Процент</option>
                            <option value="fixed" <?= $row['discount_type']==='fixed'?'selected':'' ?>>Фиксированная сумма</option>
                        </select>
                    </td>
                    <td><input type="number" name="value" value="<?= $row['discount_value'] ?>" min="1" step="0.01" class="input-text" style="width:80px;"></td>
                    <td>
                        <select name="user_id" <?= $row['user_id'] ? '' : 'disabled' ?> class="input-select">
                            <option value="">—Выбрать—</option>
                            <?php
                            $users2 = $db->query("SELECT id, login FROM users WHERE is_admin=0 ORDER BY login");
                            while($u = $users2->fetch_assoc()):
                            ?>
                                <option value="<?= $u['id'] ?>" <?= $row['user_id']==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['login']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </td>
                    <td>
                        <select name="product_id" class="input-select">
                            <option value="">—Все товары—</option>
                            <?php
                            $products2 = $db->query("SELECT id, CONCAT(brand,' ',name,' ',model) AS title FROM smartphones UNION SELECT id, CONCAT(brand,' ',name,' ',model) FROM accessories ORDER BY title ASC");
                            while($p = $products2->fetch_assoc()):
                            ?>
                                <option value="<?= $p['id'] ?>" <?= $row['product_id']==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['title']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </td>
                    <td><input type="date" name="expires_at" value="<?= $row['expires_at'] ?>" class="input-text"></td>
                    <td class="<?= $row['status']==='active'?'status-active':'status-inactive' ?>"><?= ucfirst($row['status']) ?></td>
                    <td>
                        <input type="hidden" name="coupon_id" value="<?= $row['id'] ?>">
                        <button type="submit" name="action" value="edit" class="btn btn-secondary">Сохранить</button>
                        <button type="submit" name="action" value="delete" class="btn btn-danger" onclick="return confirm('Вы уверены?')">Удалить</button>
                    </td>
                </form>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<style>   .pagination-bar {
    display: flex; 
    justify-content: center;  
    gap: 5px;
    width: 100%;
     margin:auto, auto;
    flex-wrap: wrap; 
}  </style>
    <!-- Пагинация -->
   
    <div class="pagination-bar">
        <?php if($page > 1): ?>
            <a href="?page=<?= $page-1 ?>" class="page-btn">Предыдущая</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++): ?>
            <a href="?page=<?= $i ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if($page < $totalPages): ?>
            <a href="?page=<?= $page+1 ?>" class="page-btn">Следующая</a>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selects = document.querySelectorAll('table select[name="type"]');
    if (!selects.length) return;

    selects.forEach(select => {
        select.addEventListener('change', function(){
            const row = this.closest('tr');
            if (!row) return;
            const input = row.querySelector('input[name="value"]');
            if (!input) return;
            
            if (this.value === 'percent') {
                input.min = 1; input.max = 100; input.step = 1;
            } else {
                input.min = 1; input.max = 100000; input.step = 0.01;
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
