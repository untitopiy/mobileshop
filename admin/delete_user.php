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
    $_SESSION['error'] = "Нельзя удалить самого себя.";
    header('Location: manage_users.php');
    exit;
}

$stmt = $db->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();

$_SESSION['success'] = "Пользователь удалён.";
header('Location: manage_users.php');
exit;