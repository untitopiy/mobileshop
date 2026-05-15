<?php
session_start();
// Исправленные пути: выходим из admin/ в корень через ../
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/check_admin.php';

$seller_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Получаем данные продавца
$stmt = $db->prepare("SELECT s.*, u.full_name FROM sellers s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();

if (!$seller) {
    $_SESSION['error'] = "Продавец не найден";
    header("Location: manage_sellers.php");
    exit;
}

// Обработка обновления
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_name = trim($_POST['shop_name']);
    $status = $_POST['is_active'];

    $update = $db->prepare("UPDATE sellers SET shop_name = ?, is_active = ? WHERE id = ?");
    $update->bind_param('sii', $shop_name, $status, $seller_id);
    
    if ($update->execute()) {
        $_SESSION['success'] = "Данные продавца обновлены";
        header("Location: manage_sellers.php");
        exit;
    }
}

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container my-5">
    <h1>Редактировать продавца: <?= htmlspecialchars($seller['full_name']) ?></h1>
    <form method="POST" class="card shadow-sm p-4">
        <div class="mb-3">
            <label class="form-label">Название магазина</label>
            <input type="text" name="shop_name" class="form-control" value="<?= htmlspecialchars($seller['shop_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Статус</label>
            <select name="is_active" class="form-select">
                <option value="1" <?= $seller['is_active'] ? 'selected' : '' ?>>Активен</option>
                <option value="0" <?= !$seller['is_active'] ? 'selected' : '' ?>>Заблокирован</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
        <a href="manage_sellers.php" class="btn btn-link">Отмена</a>
    </form>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>