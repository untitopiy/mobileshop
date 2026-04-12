<?php
/**
 * reorder.php - Повторение заказа
 * Перенаправляет товары из существующего заказа в корзину
 */
session_start();
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/pages/cart/CartHelper.php';
require_once __DIR__ . '/pages/cart/GuestCartService.php';

// Проверка авторизации
if (!isset($_SESSION['id'])) {
    header("Location: pages/auth.php");
    exit;
}

$user_id = $_SESSION['id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    $_SESSION['error'] = "Некорректный ID заказа";
    header("Location: orders.php");
    exit;
}

// Проверяем, что заказ принадлежит текущему пользователю
$check_query = $db->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
$check_query->bind_param('ii', $order_id, $user_id);
$check_query->execute();
$check_result = $check_query->get_result();

if ($check_result->num_rows === 0) {
    $_SESSION['error'] = "Заказ не найден или нет доступа";
    header("Location: orders.php");
    exit;
}

// Получаем товары из заказа
$items_query = $db->prepare("
    SELECT 
        oi.product_id,
        oi.variation_id,
        oi.quantity,
        oi.price
    FROM order_items oi
    JOIN order_sellers os ON os.id = oi.order_seller_id
    WHERE os.order_id = ?
");
$items_query->bind_param('i', $order_id);
$items_query->execute();
$items = $items_query->get_result();

if ($items->num_rows === 0) {
    $_SESSION['error'] = "В заказе нет товаров для повторения";
    header("Location: orders.php");
    exit;
}

// Инициализируем CartHelper
$cartHelper = new CartHelper($db);
$added_count = 0;
$failed_items = [];

// Добавляем товары в корзину
while ($item = $items->fetch_assoc()) {
    $result = $cartHelper->addItem(
        $user_id,
        $item['product_id'],
        $item['quantity'],
        $item['variation_id']
    );
    
    if ($result['success']) {
        $added_count++;
    } else {
        $failed_items[] = $result['message'];
    }
}

// Формируем сообщение
if ($added_count > 0) {
    $_SESSION['success'] = "В корзину добавлено {$added_count} товаров из заказа";
    
    if (!empty($failed_items)) {
        $_SESSION['warning'] = "Некоторые товары не добавлены: " . implode(', ', array_unique($failed_items));
    }
    
    // Перенаправляем в корзину
    header("Location: pages/cart/cart.php");
    exit;
} else {
    $_SESSION['error'] = "Не удалось добавить товары в корзину. Возможно, товары закончились или недоступны.";
    header("Location: orders.php");
    exit;
}