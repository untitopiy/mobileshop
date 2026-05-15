<?php
session_start();
require_once __DIR__ . '/../inc/db.php';

if (!isset($_SESSION['id']) || !isset($_GET['id'])) {
    header('Location: my_products.php');
    exit;
}

$user_id = $_SESSION['id'];
$product_id = (int)$_GET['id'];

// Проверяем, что товар принадлежит этому продавцу
$stmt = $db->prepare("
    SELECT p.id FROM products p 
    JOIN sellers s ON p.seller_id = s.id 
    WHERE p.id = ? AND s.user_id = ?
");
$stmt->bind_param('ii', $product_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Удаляем товар (каскадно удалятся изображения и атрибуты если настроены FK)
    $db->query("DELETE FROM products WHERE id = $product_id");
    $_SESSION['success'] = "Товар успешно удален";
}

header('Location: my_products.php');
exit;