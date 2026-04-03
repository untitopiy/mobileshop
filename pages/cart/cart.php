<?php
/**
 * mobileshop/pages/cart/cart.php
 * Точка входа для корзины
 */

session_start();

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