<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../inc/db.php';

if (!isset($_SESSION['id'])) {
    header("Location: " . 'http://' . $_SERVER['HTTP_HOST'] . "/mobileshop/pages/auth.php");
    exit;
}

$user_id = $_SESSION['id'];
$stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($is_admin);
$stmt->fetch();
$stmt->close();

if (!$is_admin) {
    header("HTTP/1.0 403 Forbidden");
    echo "<div class='container'><h2>Доступ запрещен. Вы не являетесь администратором.</h2></div>";
    exit;
}
?>
