<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container'><h2>Ошибка: Неверный идентификатор аксессуара.</h2></div>";
    exit;
}
$accessory_id = (int)$_GET['id'];

// Сохраняем остальные GET-параметры для перенаправления
$redirect_params = $_GET;
unset($redirect_params['id']); // убираем id, чтобы не дублировать его
$query_string = http_build_query($redirect_params);

// Начинаем транзакцию
$db->begin_transaction();

try {
    // Если у вас есть таблица для поддерживаемых устройств, удаляем связанные записи.
    $stmt = $db->prepare("DELETE FROM accessory_supported_smartphones WHERE accessory_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $accessory_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Удаляем аксессуар
    $stmt = $db->prepare("DELETE FROM accessories WHERE id = ?");
    $stmt->bind_param("i", $accessory_id);
    $stmt->execute();
    $stmt->close();

    $db->commit();

    // Перенаправляем с сохранением настроек (если они есть)
    $location = "manage_products.php";
    if (!empty($query_string)) {
        $location .= "?" . $query_string . "&message=accessory_deleted";
    } else {
        $location .= "?message=accessory_deleted";
    }
    header("Location: " . $location);
    exit;
} catch (Exception $e) {
    $db->rollback();
    echo "<div class='container'><div class='alert alert-danger'>Ошибка при удалении аксессуара: " . htmlspecialchars($e->getMessage()) . "</div></div>";
}
?>
