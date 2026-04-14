<?php
/**
 * mobileshop/pages/cart/cart.php
 * Точка входа для корзины
 */

session_start();

// ===== ИСПРАВЛЕНИЕ: Загружаем compare_ids из БД для авторизованных пользователей =====
// Это предотвращает обнуление счетчика сравнения при переходе в корзину
if (isset($_SESSION['id']) && !isset($_SESSION['compare_ids_loaded'])) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/mobileshop/inc/db.php';
    if (isset($db) && $db && !$db->connect_error) {
        $user_id = (int)$_SESSION['id'];
        $stmt = $db->prepare("SELECT product_id FROM `compare` WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $compare_ids = [];
        while ($row = $result->fetch_assoc()) {
            $compare_ids[] = $row['product_id'];
        }
        $_SESSION['compare_ids'] = $compare_ids;
        $_SESSION['compare_ids_loaded'] = true; // Флаг что загрузили
        $stmt->close();
    }
}
// Для гостей убеждаемся что compare_ids инициализирован как массив
if (!isset($_SESSION['compare_ids'])) {
    $_SESSION['compare_ids'] = [];
}

// Подключаем БД через абсолютный путь
require_once $_SERVER['DOCUMENT_ROOT'] . '/mobileshop/inc/db.php';

// Проверяем подключение к БД
if (!isset($db) || !$db || $db->connect_error) {
    die('Ошибка подключения к базе данных: ' . ($db->connect_error ?? 'Неизвестная ошибка'));
}

require_once __DIR__ . '/CartItemDTO.php';
require_once __DIR__ . '/GuestCartService.php';
require_once __DIR__ . '/CartHelper.php';
require_once __DIR__ . '/CartController.php';

$controller = new CartController($db);
$controller->handleRequest();