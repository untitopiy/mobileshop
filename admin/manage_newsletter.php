<?php
session_start();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/mail.php';
require_once __DIR__ . '/../inc/header.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../pages/auth.php");
    exit;
}

// Проверка админа
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_newsletter'])) {
    $subject = trim($_POST['subject']);
    $body = trim($_POST['message']);
    $recipient_type = $_POST['recipient_type'] ?? 'all';

    if ($subject === '' || $body === '') {
        $message = "Все поля обязательны!";
    } else {
        $message = sendNewsletter($db, $subject, $body, $recipient_type);
    }
}
?>

<link rel="stylesheet" href="../inc/styles.css">
<div class="admin-wrapper">
    <h1 class="admin-title">Разослать новость</h1>

    <?php if(!empty($message)): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" class="card" style="max-width:600px;">
        <div class="form-row">
            <label>Заголовок письма:</label>
            <input type="text" name="subject" class="input-text" required>
        </div>
        <div class="form-row">
            <label>Содержание письма (HTML):</label>
            <textarea name="message" class="input-textarea" rows="8" required></textarea>
        </div>
        <div class="form-row">
            <label>Кому отправить:</label>
            <select name="recipient_type" class="input-select">
                <option value="all">Все пользователи</option>
                <option value="with_orders">С заказами</option>
                <option value="no_orders">Без заказов</option>
                <option value="active_recent">Активные за 30 дней</option>
                <option value="subscribed">Подписанные на рассылку</option>
            </select>
        </div>
        <button type="submit" name="send_newsletter" class="btn btn-primary full-width">Отправить</button>
    </form>
</div>
