<?php
// ===== ИСПРАВЛЕНИЕ: Проверяем, не запущена ли уже сессия =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../inc/db.php';

$response = ['success' => false];

// Проверка авторизации
$is_authenticated = isset($_SESSION['id']);

// ===== ИСПРАВЛЕНИЕ: Убеждаемся что compare_ids инициализирован =====
if (!isset($_SESSION['compare_ids'])) {
    $_SESSION['compare_ids'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Очистка всего сравнения
    if (isset($_POST['clear_all'])) {
        if ($is_authenticated) {
            $user_id = (int)$_SESSION['id'];
            // Используем подготовленный запрос для безопасности
            $stmt = $db->prepare("DELETE FROM `compare` WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();
        }
        $_SESSION['compare_ids'] = [];
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
            $user_id = (int)$_SESSION['id'];
            $stmt = $db->prepare("DELETE FROM `compare` WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param('ii', $user_id, $remove_id);
            $stmt->execute();
            $stmt->close();
            
            // Получаем обновленный список
            $stmt = $db->prepare("SELECT product_id FROM `compare` WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $ids = [];
            while ($row = $result->fetch_assoc()) {
                $ids[] = (int)$row['product_id'];
            }
            $_SESSION['compare_ids'] = $ids;
            $stmt->close();
            $response['compare_ids'] = $ids;
        } else {
            // Не авторизован - работаем только с сессией
            if (is_array($_SESSION['compare_ids'])) {
                $_SESSION['compare_ids'] = array_values(array_diff($_SESSION['compare_ids'], [$remove_id]));
            }
            $response['compare_ids'] = $_SESSION['compare_ids'];
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
            // Ограничиваем 4 товарами и фильтруем только положительные числа
            $ids = array_slice(array_filter(array_map('intval', $ids), function($id) { return $id > 0; }), 0, 4);
            
            if ($is_authenticated) {
                $user_id = (int)$_SESSION['id'];
                
                // Начинаем транзакцию для атомарности
                $db->begin_transaction();
                
                try {
                    // Очищаем старые записи
                    $stmt = $db->prepare("DELETE FROM `compare` WHERE user_id = ?");
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Добавляем новые записи
                    if (!empty($ids)) {
                        $stmt = $db->prepare("INSERT INTO `compare` (user_id, product_id, created_at) VALUES (?, ?, NOW())");
                        foreach ($ids as $product_id) {
                            $stmt->bind_param('ii', $user_id, $product_id);
                            $stmt->execute();
                        }
                        $stmt->close();
                    }
                    
                    $db->commit();
                } catch (Exception $e) {
                    $db->rollback();
                    $response['message'] = 'Ошибка базы данных: ' . $e->getMessage();
                    echo json_encode($response);
                    exit;
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
        $ids = [];
        
        if ($is_authenticated) {
            $user_id = (int)$_SESSION['id'];
            $stmt = $db->prepare("SELECT product_id FROM `compare` WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $ids[] = (int)$row['product_id'];
            }
            $stmt->close();
            
            // ===== ИСПРАВЛЕНИЕ: Синхронизируем сессию с БД только если есть данные =====
            // или если сессия пуста (чтобы не потерять данные при временных ошибках)
            if (!empty($ids) || empty($_SESSION['compare_ids'])) {
                $_SESSION['compare_ids'] = $ids;
            } else {
                // Если БД вернула пусто, но в сессии есть данные — используем сессию
                // (возможно, данные еще не синхронизированы)
                $ids = $_SESSION['compare_ids'];
            }
        } else {
            // Для гостей — только сессия
            $ids = $_SESSION['compare_ids'] ?? [];
        }
        
        $response['success'] = true;
        $response['compare_ids'] = $ids;
        echo json_encode($response);
        exit;
    }
    
    // Добавление/удаление товара (toggle)
    if (isset($_POST['toggle'])) {
        $product_id = (int)$_POST['toggle'];
        
        if ($product_id <= 0) {
            $response['message'] = 'Некорректный ID товара';
            echo json_encode($response);
            exit;
        }
        
        if ($is_authenticated) {
            $user_id = (int)$_SESSION['id'];
            
            // Проверяем, есть ли уже
            $stmt = $db->prepare("SELECT id FROM `compare` WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param('ii', $user_id, $product_id);
            $stmt->execute();
            $check = $stmt->get_result();
            $exists = $check->num_rows > 0;
            $stmt->close();
            
            if ($exists) {
                // Удаляем
                $stmt = $db->prepare("DELETE FROM `compare` WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param('ii', $user_id, $product_id);
                $stmt->execute();
                $stmt->close();
                $action = 'removed';
                $message = 'Товар удален из сравнения';
            } else {
                // Проверяем лимит
                $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM `compare` WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $count_result = $stmt->get_result()->fetch_assoc();
                $count = (int)$count_result['cnt'];
                $stmt->close();
                
                if ($count >= 4) {
                    $response['success'] = false;
                    $response['message'] = 'Можно сравнивать не более 4 товаров';
                    echo json_encode($response);
                    exit;
                }
                
                // Добавляем
                $stmt = $db->prepare("INSERT INTO `compare` (user_id, product_id, created_at) VALUES (?, ?, NOW())");
                $stmt->bind_param('ii', $user_id, $product_id);
                $stmt->execute();
                $stmt->close();
                $action = 'added';
                $message = 'Товар добавлен к сравнению';
            }
            
            // Получаем обновленный список
            $stmt = $db->prepare("SELECT product_id FROM `compare` WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $ids = [];
            while ($row = $result->fetch_assoc()) {
                $ids[] = (int)$row['product_id'];
            }
            $_SESSION['compare_ids'] = $ids;
            $stmt->close();
            
            $response['success'] = true;
            $response['action'] = $action;
            $response['message'] = $message;
            $response['compare_ids'] = $ids;
        } else {
            // Не авторизован - работаем только с сессией
            $ids = $_SESSION['compare_ids'] ?? [];
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

$response['message'] = 'Метод не поддерживается';
echo json_encode($response);
exit;