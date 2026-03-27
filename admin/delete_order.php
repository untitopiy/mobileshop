<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_orders.php");
    exit;
}
$order_id = (int)$_GET['id'];

// Начинаем транзакцию
$db->begin_transaction();

try {
    // Получаем информацию о товарах для возврата на склад (опционально)
    $items_query = $db->prepare("
        SELECT oi.product_id, oi.variation_id, oi.quantity
        FROM order_items oi
        JOIN order_sellers os ON os.id = oi.order_seller_id
        WHERE os.order_id = ?
    ");
    $items_query->bind_param('i', $order_id);
    $items_query->execute();
    $items = $items_query->get_result();
    
    // Возвращаем товары на склад (если нужно)
    while ($item = $items->fetch_assoc()) {
        if ($item['variation_id']) {
            $update = $db->prepare("
                UPDATE product_variations SET quantity = quantity + ? WHERE id = ?
            ");
            $update->bind_param('ii', $item['quantity'], $item['variation_id']);
            $update->execute();
        } else {
            $update = $db->prepare("
                UPDATE product_variations SET quantity = quantity + ? 
                WHERE product_id = ? LIMIT 1
            ");
            $update->bind_param('ii', $item['quantity'], $item['product_id']);
            $update->execute();
        }
    }
    
    // Удаляем записи из order_status_history
    $db->query("DELETE FROM order_status_history WHERE order_id = $order_id");
    
    // Удаляем записи из order_addresses
    $db->query("DELETE FROM order_addresses WHERE order_id = $order_id");
    
    // Получаем ID записей order_sellers
    $sellers = $db->query("SELECT id FROM order_sellers WHERE order_id = $order_id");
    while ($seller = $sellers->fetch_assoc()) {
        // Удаляем товары для каждого продавца
        $db->query("DELETE FROM order_items WHERE order_seller_id = {$seller['id']}");
    }
    
    // Удаляем записи из order_sellers
    $db->query("DELETE FROM order_sellers WHERE order_id = $order_id");
    
    // Удаляем сам заказ
    $db->query("DELETE FROM orders WHERE id = $order_id");
    
    $db->commit();
    
    $_SESSION['success'] = "Заказ успешно удален";
    
} catch (Exception $e) {
    $db->rollback();
    $_SESSION['error'] = "Ошибка при удалении заказа: " . $e->getMessage();
}

header("Location: manage_orders.php");
exit;
?>