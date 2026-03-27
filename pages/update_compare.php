<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/db.php';

$response = ['success' => false];

// Проверка авторизации
$is_authenticated = isset($_SESSION['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Очистка всего сравнения
    if (isset($_POST['clear_all'])) {
        if ($is_authenticated) {
            $user_id = $_SESSION['id'];
            $db->query("DELETE FROM `compare` WHERE user_id = $user_id");
        }
        unset($_SESSION['compare_ids']);
        $response['success'] = true;
        $response['message'] = 'Сравнение очищено';
        $response['compare_ids'] = [];
        echo json_encode($response);
        exit;
    }
    
    // Удаление одного товара
    if (isset($_POST['remove'])) {
        $remove_id = (int)$_POST['remove'];
        if ($is_authenticated) {
            $user_id = $_SESSION['id'];
            $db->query("DELETE FROM `compare` WHERE user_id = $user_id AND product_id = $remove_id");
            // Получаем обновленный список
            $result = $db->query("SELECT product_id FROM `compare` WHERE user_id = $user_id ORDER BY created_at DESC");
            $ids = [];
            while ($row = $result->fetch_assoc()) {
                $ids[] = $row['product_id'];
            }
            $_SESSION['compare_ids'] = $ids;
            $response['compare_ids'] = $ids;
        } else {
            if (isset($_SESSION['compare_ids']) && is_array($_SESSION['compare_ids'])) {
                $_SESSION['compare_ids'] = array_diff($_SESSION['compare_ids'], [$remove_id]);
                $_SESSION['compare_ids'] = array_values($_SESSION['compare_ids']);
                $response['compare_ids'] = $_SESSION['compare_ids'];
            }
        }
        $response['success'] = true;
        $response['message'] = 'Товар удален из сравнения';
        echo json_encode($response);
        exit;
    }
    
    // Сохранение списка ID для сравнения
    if (isset($_POST['ids'])) {
        $ids = json_decode($_POST['ids'], true);
        if (is_array($ids)) {
            // Ограничиваем 4 товарами
            $ids = array_slice($ids, 0, 4);
            
            if ($is_authenticated) {
                $user_id = $_SESSION['id'];
                // Очищаем старые
                $db->query("DELETE FROM `compare` WHERE user_id = $user_id");
                // Добавляем новые
                foreach ($ids as $product_id) {
                    $product_id = (int)$product_id;
                    if ($product_id > 0) {
                        $db->query("INSERT INTO `compare` (user_id, product_id, created_at) VALUES ($user_id, $product_id, NOW())");
                    }
                }
            }
            $_SESSION['compare_ids'] = $ids;
            $response['success'] = true;
            $response['message'] = 'Список сравнения обновлен';
            $response['compare_ids'] = $ids;
        } else {
            $response['message'] = 'Неверный формат данных';
        }
        echo json_encode($response);
        exit;
    }
    
    // Получение списка ID из базы
    if (isset($_POST['get_ids'])) {
        if ($is_authenticated) {
            $user_id = $_SESSION['id'];
            $result = $db->query("SELECT product_id FROM `compare` WHERE user_id = $user_id ORDER BY created_at DESC");
            $ids = [];
            while ($row = $result->fetch_assoc()) {
                $ids[] = $row['product_id'];
            }
            $_SESSION['compare_ids'] = $ids;
            $response['compare_ids'] = $ids;
        } else {
            $response['compare_ids'] = isset($_SESSION['compare_ids']) ? $_SESSION['compare_ids'] : [];
        }
        $response['success'] = true;
        echo json_encode($response);
        exit;
    }
    
    // Добавление/удаление товара (toggle)
    if (isset($_POST['toggle'])) {
        $product_id = (int)$_POST['toggle'];
        
        if ($is_authenticated) {
            $user_id = $_SESSION['id'];
            // Проверяем, есть ли уже
            $check = $db->query("SELECT id FROM `compare` WHERE user_id = $user_id AND product_id = $product_id");
            
            if ($check->num_rows > 0) {
                // Удаляем
                $db->query("DELETE FROM `compare` WHERE user_id = $user_id AND product_id = $product_id");
                $action = 'removed';
                $message = 'Товар удален из сравнения';
            } else {
                // Проверяем лимит
                $count = $db->query("SELECT COUNT(*) as cnt FROM `compare` WHERE user_id = $user_id")->fetch_assoc()['cnt'];
                if ($count >= 4) {
                    $response['success'] = false;
                    $response['message'] = 'Можно сравнивать не более 4 товаров';
                    echo json_encode($response);
                    exit;
                }
                // Добавляем
                $db->query("INSERT INTO `compare` (user_id, product_id, created_at) VALUES ($user_id, $product_id, NOW())");
                $action = 'added';
                $message = 'Товар добавлен к сравнению';
            }
            
            // Получаем обновленный список
            $result = $db->query("SELECT product_id FROM `compare` WHERE user_id = $user_id ORDER BY created_at DESC");
            $ids = [];
            while ($row = $result->fetch_assoc()) {
                $ids[] = $row['product_id'];
            }
            $_SESSION['compare_ids'] = $ids;
            
            $response['success'] = true;
            $response['action'] = $action;
            $response['message'] = $message;
            $response['compare_ids'] = $ids;
        } else {
            // Не авторизован - работаем только с сессией
            $ids = isset($_SESSION['compare_ids']) ? $_SESSION['compare_ids'] : [];
            $index = array_search($product_id, $ids);
            
            if ($index === false) {
                if (count($ids) >= 4) {
                    $response['success'] = false;
                    $response['message'] = 'Можно сравнивать не более 4 товаров';
                    echo json_encode($response);
                    exit;
                }
                $ids[] = $product_id;
                $action = 'added';
                $message = 'Товар добавлен к сравнению';
            } else {
                unset($ids[$index]);
                $ids = array_values($ids);
                $action = 'removed';
                $message = 'Товар удален из сравнения';
            }
            $_SESSION['compare_ids'] = $ids;
            $response['success'] = true;
            $response['action'] = $action;
            $response['message'] = $message;
            $response['compare_ids'] = $ids;
        }
        echo json_encode($response);
        exit;
    }
    
    $response['message'] = 'Неизвестная команда';
    echo json_encode($response);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Метод не поддерживается']);
exit;