<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container'><h2>Ошибка: Неверный идентификатор заказа.</h2></div>";
    exit;
}
$order_id = (int)$_GET['id'];

// Начинаем транзакцию
$db->begin_transaction();

try {
    // Удаляем товары заказа
    $stmt = $db->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();

    // Удаляем данные доставки
    $stmt = $db->prepare("DELETE FROM order_details WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();

    // Удаляем сам заказ
    $stmt = $db->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();

    $db->commit();

    header("Location: manage_orders.php?message=deleted");
    exit;
} catch (Exception $e) {
    $db->rollback();
    echo "<div class='container'><div class='alert alert-danger'>Ошибка при удалении заказа.</div></div>";
}
?>
