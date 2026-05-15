<?php
session_start();
require_once __DIR__ . '/../inc/db.php';

// Устанавливаем заголовок JSON в самом начале
header('Content-Type: application/json');

// 1. Проверка авторизации
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Необходимо авторизоваться']);
    exit;
}

// 2. Получаем параметры (поддерживаем и GET, и POST/JSON)
$data = json_decode(file_get_contents('php://input'), true);
$review_id = isset($_GET['review_id']) ? (int)$_GET['review_id'] : (isset($data['review_id']) ? (int)$data['review_id'] : 0);
$action = isset($_GET['action']) ? $_GET['action'] : (isset($data['action']) ? $data['action'] : 'helpful');
$user_id = (int)$_SESSION['id'];

if ($review_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Неверный ID отзыва']);
    exit;
}

// Определяем, с какой колонкой работаем
$is_helpful_val = ($action === 'helpful') ? 1 : 0;
$count_column = ($action === 'helpful') ? 'likes_count' : 'dislikes_count';

try {
    // 3. Проверяем, было ли уже действие от этого пользователя на этот отзыв
    $stmt = $db->prepare("SELECT id, is_helpful FROM review_helpful WHERE review_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $review_id, $user_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Если пользователь нажал ту же кнопку второй раз — удаляем голос (Toggle Off)
        if ($existing['is_helpful'] == $is_helpful_val) {
            $stmt = $db->prepare("DELETE FROM review_helpful WHERE id = ?");
            $stmt->bind_param('i', $existing['id']);
            $stmt->execute();
            $stmt->close();

            $db->query("UPDATE reviews SET $count_column = $count_column - 1 WHERE id = $review_id");
            $res_action = 'removed';
        } 
        else {
            // Если пользователь передумал (нажал дизлайк вместо лайка или наоборот)
            // 1. Убираем старый голос
            $old_column = ($existing['is_helpful'] == 1) ? 'likes_count' : 'dislikes_count';
            $db->query("UPDATE reviews SET $old_column = $old_column - 1 WHERE id = $review_id");

            // 2. Ставим новый голос
            $stmt = $db->prepare("UPDATE review_helpful SET is_helpful = ? WHERE id = ?");
            $stmt->bind_param('ii', $is_helpful_val, $existing['id']);
            $stmt->execute();
            $stmt->close();

            $db->query("UPDATE reviews SET $count_column = $count_column + 1 WHERE id = $review_id");
            $res_action = ($action === 'helpful') ? 'liked' : 'disliked';
        }
    } else {
        // 4. Совсем новый голос
        $stmt = $db->prepare("INSERT INTO review_helpful (review_id, user_id, is_helpful) VALUES (?, ?, ?)");
        $stmt->bind_param('iii', $review_id, $user_id, $is_helpful_val);
        $stmt->execute();
        $stmt->close();

        $db->query("UPDATE reviews SET $count_column = $count_column + 1 WHERE id = $review_id");
        $res_action = ($action === 'helpful') ? 'liked' : 'disliked';
    }

    // 5. Получаем свежие счетчики для обновления страницы без перезагрузки
    $result = $db->query("SELECT likes_count, dislikes_count FROM reviews WHERE id = $review_id");
    $counts = $result->fetch_assoc();

    echo json_encode([
        'status' => 'success',
        'action' => $res_action,
        'likes_count' => $counts['likes_count'],
        'dislikes_count' => $counts['dislikes_count']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}