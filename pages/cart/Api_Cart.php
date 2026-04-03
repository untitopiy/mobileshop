<?php
/**
 * /mobileshop/pages/cart/Api_Cart.php
 * AJAX API для корзины
 */
session_start();

// Подключаем БД через абсолютный путь
require_once $_SERVER['DOCUMENT_ROOT'] . '/mobileshop/inc/db.php';

// Проверяем подключение к БД
if (!isset($db) || !$db || $db->connect_error) {
    jsonResponse(false, 'Ошибка подключения к базе данных');
    exit;
}

require_once __DIR__ . '/CartHelper.php';
require_once __DIR__ . '/GuestCartService.php';

// Отключаем вывод ошибок в JSON-ответ
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

// --- CSRF ЗАЩИТА ---
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $headers = getallheaders();
    $csrf_token = $_POST['csrf_token'] ?? $headers['X-CSRF-TOKEN'] ?? null;
    
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        jsonResponse(false, 'Недействительный CSRF-токен');
        exit;
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_SESSION['id'] ?? null;

try {
    $cartHelper = new CartHelper($db);
    $guestService = new GuestCartService($db, $_SESSION);
    
    switch ($action) {
        case 'add':
            handleAdd($db, $cartHelper, $guestService, $user_id);
            break;
        case 'count':
            handleCount($cartHelper, $guestService, $user_id);
            break;
        case 'get':
            handleGet($cartHelper, $guestService, $user_id);
            break;
        case 'remove':
            handleRemove($cartHelper, $guestService, $user_id);
            break;
        case 'update':
            handleUpdate($cartHelper, $guestService, $user_id);
            break;
        default:
            jsonResponse(false, 'Неизвестное действие: ' . $action);
    }
} catch (Exception $e) {
    error_log("Api_Cart error: " . $e->getMessage());
    jsonResponse(false, 'Ошибка сервера');
}

// ========== ОБРАБОТЧИКИ ==========

function handleAdd($db, CartHelper $cartHelper, GuestCartService $guestService, $user_id) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    $variation_id = !empty($_POST['variation_id']) && $_POST['variation_id'] !== 'null' 
        ? (int)$_POST['variation_id'] 
        : null;
    
    if ($product_id <= 0 || $quantity <= 0) {
        jsonResponse(false, 'Некорректные параметры товара');
        return;
    }

    $stock_info = getStockInfo($db, $product_id, $variation_id);
    if (!$stock_info || $stock_info['quantity'] <= 0) {
        jsonResponse(false, 'Товар недоступен или закончился на складе');
        return;
    }

    if ($user_id) {
        $result = $cartHelper->addItem($user_id, $product_id, $quantity, $variation_id);
        $count = $cartHelper->getCartCount($user_id);
    } else {
        $result = $guestService->addItem($product_id, $quantity, $variation_id);
        $count = $guestService->getCartCount();
    }
    
    jsonResponse($result['success'], $result['message'], ['count' => $count]);
}

function handleCount(CartHelper $cartHelper, GuestCartService $guestService, $user_id) {
    $count = $user_id ? $cartHelper->getCartCount($user_id) : $guestService->getCartCount();
    jsonResponse(true, 'OK', ['count' => $count]);
}

function handleGet(CartHelper $cartHelper, GuestCartService $guestService, $user_id) {
    $data = $user_id ? $cartHelper->getCartItems($user_id) : $guestService->getCartItems();
    jsonResponse(true, 'OK', $data);
}

function handleRemove(CartHelper $cartHelper, GuestCartService $guestService, $user_id) {
    $cart_key = $_POST['cart_key'] ?? '';
    
    if (!$user_id) {
        $result = $guestService->removeItem($cart_key);
        jsonResponse($result, 'Товар удален', ['count' => $guestService->getCartCount()]);
        return;
    }
    
    if (strpos($cart_key, '_') !== false) {
        list($pid, $vid) = explode('_', $cart_key);
        $cartHelper->removeItem($user_id, (int)$pid, (int)$vid);
    } else {
        $cartHelper->removeItem($user_id, (int)$cart_key);
    }
    
    jsonResponse(true, 'Товар удален', ['count' => $cartHelper->getCartCount($user_id)]);
}

function handleUpdate(CartHelper $cartHelper, GuestCartService $guestService, $user_id) {
    $updates = $_POST['updates'] ?? [];
    
    if (!$user_id) {
        foreach ($updates as $key => $qty) {
            $guestService->updateQuantity($key, (int)$qty);
        }
        jsonResponse(true, 'Обновлено', ['count' => $guestService->getCartCount()]);
        return;
    }
    
    foreach ($updates as $cart_key => $new_qty) {
        $cart_key = preg_replace('/[^0-9_]/', '', $cart_key);
        $new_qty = (int)$new_qty;
        
        if (strpos($cart_key, '_') !== false) {
            list($pid, $vid) = explode('_', $cart_key);
            $cartHelper->updateQuantity($user_id, (int)$pid, (int)$vid, $new_qty);
        } else {
            $cartHelper->updateQuantity($user_id, (int)$cart_key, null, $new_qty);
        }
    }
    
    jsonResponse(true, 'Обновлено', ['count' => $cartHelper->getCartCount($user_id)]);
}

// ========== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ==========

function getStockInfo($db, $product_id, $variation_id = null) {
    if ($variation_id) {
        $stmt = $db->prepare("SELECT quantity, price FROM product_variations WHERE id = ? AND product_id = ?");
        $stmt->bind_param('ii', $variation_id, $product_id);
    } else {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(quantity), 0) as quantity, 
                   COALESCE(MIN(price), 0) as price 
            FROM product_variations 
            WHERE product_id = ?
        ");
        $stmt->bind_param('i', $product_id);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

function jsonResponse($success, $message, $data = []) {
    $response = array_merge([
        'success' => $success,
        'message' => $message
    ], $data);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}