<?php
session_start();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/mail.php';
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
$message_type = 'success'; // Для разных типов уведомлений

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_newsletter'])) {
    $subject = trim($_POST['subject']);
    $body = trim($_POST['message']);
    $recipient_type = $_POST['recipient_type'] ?? 'all';

    if ($subject === '' || $body === '') {
        $message = "Все поля обязательны!";
        $message_type = 'error';
    } else {
        $result = sendNewsletter($db, $subject, $body, $recipient_type);
        $message = $result;
        // Если sendNewsletter возвращает ошибку, можно определить тип
        if (stripos($result, 'ошибк') !== false || stripos($result, 'error') !== false) {
            $message_type = 'error';
        }
    }
}
?>

<link rel="stylesheet" href="../inc/styles.css">

<style>
    /* Локальные дополнения к стилям формы рассылки */
    .newsletter-form {
        max-width: 600px;
        width: 100%;
        margin: 0 auto;
    }
    
    .newsletter-form .form-row {
        margin-bottom: 20px;
    }
    
    .newsletter-form .form-row label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: #333;
    }
    
    .newsletter-form .input-text,
    .newsletter-form .input-textarea,
    .newsletter-form .input-select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 14px;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }
    
    .newsletter-form .input-text:focus,
    .newsletter-form .input-textarea:focus,
    .newsletter-form .input-select:focus {
        outline: none;
        border-color: #4a90d9;
    }
    
    .newsletter-form .input-textarea {
        resize: vertical;
        min-height: 120px;
        font-family: inherit;
    }
    
    .newsletter-form .btn-primary {
        margin-top: 10px;
    }
    
    .alert {
        padding: 12px 16px;
        border-radius: 6px;
        margin: 16px auto;
        max-width: 600px;
    }
    
    .alert.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .admin-title {
        text-align: center;
        margin-bottom: 24px;
    }
</style>

<div class="admin-wrapper">
    <h1 class="admin-title">Разослать новость</h1>

    <?php if(!empty($message)): ?>
        <div class="alert <?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" class="card newsletter-form">
        <div class="form-row">
            <label>Заголовок письма:</label>
            <input type="text" name="subject" class="input-text" required 
                   value="<?= isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : '' ?>">
        </div>
        <div class="form-row">
            <label>Содержание письма (HTML):</label>
            <textarea name="message" class="input-textarea" rows="8" required><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
        </div>
        <div class="form-row">
            <label>Кому отправить:</label>
            <select name="recipient_type" class="input-select">
                <option value="all" <?= (($_POST['recipient_type'] ?? 'all') === 'all') ? 'selected' : '' ?>>Все пользователи</option>
                <option value="with_orders" <?= (($_POST['recipient_type'] ?? '') === 'with_orders') ? 'selected' : '' ?>>С заказами</option>
                <option value="no_orders" <?= (($_POST['recipient_type'] ?? '') === 'no_orders') ? 'selected' : '' ?>>Без заказов</option>
                <option value="active_recent" <?= (($_POST['recipient_type'] ?? '') === 'active_recent') ? 'selected' : '' ?>>Активные за 30 дней</option>
                <option value="subscribed" <?= (($_POST['recipient_type'] ?? '') === 'subscribed') ? 'selected' : '' ?>>Подписанные на рассылку</option>
            </select>
        </div>
        <button type="submit" name="send_newsletter" class="btn btn-primary full-width">Отправить</button>
    </form>
</div>