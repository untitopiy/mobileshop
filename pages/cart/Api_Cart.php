<?php
/**
 * /mobileshop/pages/cart/Api_Cart.php
 * AJAX API для корзины - работает и для гостей, и для авторизованных
 */

session_start();
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/CartHelper.php';

// Отключаем вывод ошибок в JSON-ответ
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

// Получаем параметры
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_SESSION['id'] ?? null;

// Логирование для отладки
error_log("Api_Cart: action=$action, user_id=" . ($user_id ?? 'guest'));

try {
    switch ($action) {
        case 'add':
            handleAdd($db, $user_id);
            break;
            
        case 'count':
            handleCount($user_id);
            break;
            
        case 'get':
            handleGet($db, $user_id);
            break;
            
        case 'remove':
            handleRemove($db, $user_id);
            break;
            
        case 'update':
            handleUpdate($db, $user_id);
            break;
            
        default:
            jsonResponse(false, 'Неизвестное действие: ' . $action);
    }
} catch (Exception $e) {
    error_log("Api_Cart error: " . $e->getMessage());
    jsonResponse(false, 'Ошибка сервера');
}

// ========== ОБРАБОТЧИКИ ==========

function handleAdd($db, $user_id) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    $variation_id = !empty($_POST['variation_id']) && $_POST['variation_id'] !== 'null' 
        ? (int)$_POST['variation_id'] 
        : null;
    
    error_log("handleAdd: pid=$product_id, qty=$quantity, var=" . ($variation_id ?? 'null'));
    
    if ($product_id <= 0 || $quantity <= 0) {
        jsonResponse(false, 'Некорректные параметры товара');
        return;
    }
    
    // Проверяем существование товара и stock
    $stock_info = getStockInfo($db, $product_id, $variation_id);
    if (!$stock_info || $stock_info['quantity'] <= 0) {
        jsonResponse(false, 'Товар недоступен или закончился на складе');
        return;
    }
    
    if ($user_id) {
        // Авторизованный пользователь - в БД
        $cart = new CartHelper($db);
        $result = $cart->addItem($user_id, $product_id, $quantity, $variation_id);
        $count = $cart->getCartCount($user_id);
    } else {
        // Гость - в сессию
        $result = addToSessionCart($product_id, $quantity, $variation_id, $stock_info);
        $count = getSessionCartCount();
    }
    
    jsonResponse($result['success'], $result['message'], ['count' => $count]);
}

function handleCount($user_id) {
    if ($user_id) {
        $cart = new CartHelper($GLOBALS['db']);
        $count = $cart->getCartCount($user_id);
    } else {
        $count = getSessionCartCount();
    }
    
    jsonResponse(true, 'OK', ['count' => $count]);
}

function handleGet($db, $user_id) {
    if ($user_id) {
        $cart = new CartHelper($db);
        $data = $cart->getCartItems($user_id);
    } else {
        $data = getSessionCartItems($db);
    }
    
    jsonResponse(true, 'OK', $data);
}

function handleRemove($db, $user_id) {
    $cart_key = $_POST['cart_key'] ?? '';
    
    if (!$user_id) {
        if (isset($_SESSION['cart'][$cart_key])) {
            unset($_SESSION['cart'][$cart_key]);
        }
        jsonResponse(true, 'Товар удален', ['count' => getSessionCartCount()]);
        return;
    }
    
    $cart = new CartHelper($db);
    
    if (strpos($cart_key, '_') !== false) {
        list($pid, $vid) = explode('_', $cart_key);
        $cart->removeItem($user_id, (int)$pid, (int)$vid);
    } else {
        $cart->removeItem($user_id, (int)$cart_key);
    }
    
    jsonResponse(true, 'Товар удален', ['count' => $cart->getCartCount($user_id)]);
}

function handleUpdate($db, $user_id) {
    $updates = $_POST['updates'] ?? [];
    
    if (!$user_id) {
        foreach ($updates as $key => $qty) {
            $key = preg_replace('/[^0-9_]/', '', $key);
            $qty = (int)$qty;
            if ($qty > 0) {
                $_SESSION['cart'][$key] = $qty;
            } else {
                unset($_SESSION['cart'][$key]);
            }
        }
        jsonResponse(true, 'Обновлено', ['count' => getSessionCartCount()]);
        return;
    }
    
    $cart = new CartHelper($db);
    foreach ($updates as $cart_key => $new_qty) {
        $cart_key = preg_replace('/[^0-9_]/', '', $cart_key);
        $new_qty = (int)$new_qty;
        
        if (strpos($cart_key, '_') !== false) {
            list($pid, $vid) = explode('_', $cart_key);
            $cart->updateQuantity($user_id, (int)$pid, (int)$vid, $new_qty);
        } else {
            $cart->updateQuantity($user_id, (int)$cart_key, null, $new_qty);
        }
    }
    
    jsonResponse(true, 'Обновлено', ['count' => $cart->getCartCount($user_id)]);
}

// ========== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ==========

function getStockInfo($db, $product_id, $variation_id = null) {
    if ($variation_id) {
        $stmt = $db->prepare("SELECT quantity, price FROM product_variations WHERE id = ? AND product_id = ?");
        $stmt->bind_param('ii', $variation_id, $product_id);
    } else {
        $stmt = $db->prepare("SELECT SUM(quantity) as quantity, MIN(price) as price FROM product_variations WHERE product_id = ?");
        $stmt->bind_param('i', $product_id);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

function addToSessionCart($product_id, $quantity, $variation_id, $stock_info) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $cart_key = $variation_id ? "{$product_id}_{$variation_id}" : (string)$product_id;
    $current_qty = $_SESSION['cart'][$cart_key] ?? 0;
    $new_qty = $current_qty + $quantity;
    
    // Проверяем лимит
    if ($new_qty > $stock_info['quantity']) {
        $new_qty = $stock_info['quantity'];
    }
    
    $_SESSION['cart'][$cart_key] = $new_qty;
    
    return [
        'success' => true,
        'message' => "Товар добавлен (всего: $new_qty шт.)"
    ];
}

function getSessionCartCount() {
    if (empty($_SESSION['cart'])) return 0;
    return array_sum($_SESSION['cart']);
}

function getSessionCartItems($db) {
    $items = [];
    $total = 0;
    $count = 0;
    
    if (empty($_SESSION['cart'])) {
        return ['items' => [], 'total_price' => 0, 'count' => 0];
    }
    
    foreach ($_SESSION['cart'] as $cart_key => $quantity) {
        // Парсим ключ
        if (strpos($cart_key, '_') !== false) {
            list($pid, $vid) = explode('_', $cart_key);
            $pid = (int)$pid;
            $vid = (int)$vid;
        } else {
            $pid = (int)$cart_key;
            $vid = null;
        }
        
        // Получаем данные товара
        $item = getProductDetails($db, $pid, $vid);
        if (!$item) continue;
        
        // Проверяем stock
        $actual_qty = min($quantity, $item['stock']);
        if ($actual_qty <= 0) {
            unset($_SESSION['cart'][$cart_key]);
            continue;
        }
        
        $subtotal = $item['price'] * $actual_qty;
        $items[] = [
            'cart_key' => $cart_key,
            'product_id' => $pid,
            'variation_id' => $vid,
            'quantity' => $actual_qty,
            'name' => $item['name'],
            'brand' => $item['brand'],
            'price' => $item['price'],
            'subtotal' => $subtotal,
            'image_url' => $item['image_url'],
            'stock' => $item['stock']
        ];
        
        $total += $subtotal;
        $count++;
    }
    
    return ['items' => $items, 'total_price' => $total, 'count' => $count];
}

function getProductDetails($db, $product_id, $variation_id = null) {
    $sql = "
        SELECT p.id, p.name, p.brand,
               COALESCE(pv.price, p.price) as price,
               COALESCE(pv.quantity, p.quantity, 0) as stock,
               (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1) as image_url
        FROM products p
        LEFT JOIN product_variations pv ON pv.id = ? AND pv.product_id = p.id
        WHERE p.id = ? AND p.status = 'active'
        LIMIT 1
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $variation_id, $product_id);
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