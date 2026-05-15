<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isset($_GET['id'])) {
    header('Location: manage_users.php');
    exit;
}

$user_id = (int)$_GET['id'];
$errors = [];

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: manage_users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $birth_date = $_POST['birth_date'] ?: null;
    $is_subscribed = isset($_POST['is_subscribed']) ? 1 : 0;

    if (empty($email)) {
        $errors[] = "Email обязателен.";
    }

    if (empty($errors)) {
        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, birth_date = ?, is_subscribed = ? WHERE id = ?");
        $stmt->bind_param('ssssii', $full_name, $email, $phone, $birth_date, $is_subscribed, $user_id);
        $stmt->execute();

        $_SESSION['success'] = "Данные обновлены.";
        header('Location: manage_users.php');
        exit;
    }
}

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Редактирование пользователя</h1>
        <a href="manage_users.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Назад</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Логин</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['login']) ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Полное имя</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Телефон</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Дата рождения</label>
                            <input type="date" name="birth_date" class="form-control" value="<?= $user['birth_date'] ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Дата регистрации</label>
                            <input type="text" class="form-control" value="<?= $user['created_at'] ? date('d.m.Y H:i', strtotime($user['created_at'])) : '—' ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Последний вход</label>
                            <input type="text" class="form-control" value="<?= $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Никогда' ?>" disabled>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_subscribed" class="form-check-input" id="sub" <?= $user['is_subscribed'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="sub">Подписан на рассылку</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>