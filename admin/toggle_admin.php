<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

if (!isset($_GET['id'])) {
    header('Location: manage_users.php');
    exit;
}

$user_id = (int)$_GET['id'];

if ($user_id == $_SESSION['id']) {
    $_SESSION['error'] = "Нельзя изменить свою роль.";
    header('Location: manage_users.php');
    exit;
}

$stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result) {
    $new = $result['is_admin'] ? 0 : 1;
    $upd = $db->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
    $upd->bind_param('ii', $new, $user_id);
    $upd->execute();
    $_SESSION['success'] = $new ? "Назначен администратором." : "Права администратора сняты.";
}

header('Location: manage_users.php');
exit;