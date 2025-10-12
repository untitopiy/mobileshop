<?php
session_start();
require_once __DIR__ . '/../inc/db.php';
if (!isset($_SESSION['id'])) {
    header("Location: http://{$_SERVER['HTTP_HOST']}/mobileshop/pages/auth.php");
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
require_once __DIR__ . '/../inc/header.php';
?>
<div class="container admin-container" style="margin-top: 40px; margin-bottom: 40px;">
    <h1>Панель администратора</h1>
    <ul class="admin-menu">
        <li><a href="manage_products.php">Управление товарами</a></li>
        <li><a href="manage_orders.php">Управление заказами</a></li>
        <li><a href="manage_promotions.php">Управление акциями</a></li>
    </ul>
</div>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>
