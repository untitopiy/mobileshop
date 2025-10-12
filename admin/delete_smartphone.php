<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

// Проверяем переданный идентификатор смартфона
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container'><h2>Ошибка: Неверный идентификатор смартфона.</h2></div>";
    exit;
}
$smartphone_id = (int)$_GET['id'];

// Сохраняем остальные GET-параметры для перенаправления
$redirect_params = $_GET;
unset($redirect_params['id']);
$query_string = http_build_query($redirect_params);

// Начинаем транзакцию
$db->begin_transaction();

try {
    // Удаляем изображения для смартфона
    $stmt = $db->prepare("DELETE FROM smartphone_images WHERE smartphone_id = ?");
    $stmt->bind_param("i", $smartphone_id);
    $stmt->execute();
    $stmt->close();

    // Удаляем характеристики смартфона
    $stmt = $db->prepare("DELETE FROM smartphone_specs WHERE smartphone_id = ?");
    $stmt->bind_param("i", $smartphone_id);
    $stmt->execute();
    $stmt->close();

    // Удаляем записи, где смартфон используется как поддерживаемое устройство для аксессуаров
    $stmt = $db->prepare("DELETE FROM accessory_supported_smartphones WHERE smartphone_id = ?");
    $stmt->bind_param("i", $smartphone_id);
    $stmt->execute();
    $stmt->close();

    // Удаляем сам смартфон
    $stmt = $db->prepare("DELETE FROM smartphones WHERE id = ?");
    $stmt->bind_param("i", $smartphone_id);
    $stmt->execute();
    $stmt->close();

    $db->commit();

    // Формируем адрес перенаправления с сохранением GET-параметров
    $location = "manage_products.php";
    if (!empty($query_string)) {
        $location .= "?" . $query_string . "&message=smartphone_deleted";
    } else {
        $location .= "?message=smartphone_deleted";
    }
    header("Location: " . $location);
    exit;
} catch (Exception $e) {
    $db->rollback();
    echo "<div class='container'><div class='alert alert-danger'>Ошибка при удалении смартфона: " . htmlspecialchars($e->getMessage()) . "</div></div>";
}
?>
