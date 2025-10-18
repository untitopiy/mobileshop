<?php
session_start();
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/models/Review.php';

if (!isset($_SESSION['id'])) {
    $_SESSION['error'] = 'Для оставления отзыва необходимо авторизоваться';
    header('Location: auth.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Неверный метод запроса';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit;
}

// Проверяем обязательные поля
$required_fields = ['smartphone_id', 'rating', 'title', 'comment'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $_SESSION['error'] = 'Заполните все обязательные поля';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }
}

$user_id = (int)$_SESSION['id'];
$smartphone_id = (int)$_POST['smartphone_id'];
$rating = (int)$_POST['rating'];
$title = trim($_POST['title']);
$comment = trim($_POST['comment']);
$pros = trim($_POST['pros'] ?? '');
$cons = trim($_POST['cons'] ?? '');
$is_verified = isset($_POST['is_verified']) ? 1 : 0;

// Валидация данных
if ($rating < 1 || $rating > 5) {
    $_SESSION['error'] = 'Рейтинг должен быть от 1 до 5';
    header('Location: product.php?id=' . $smartphone_id);
    exit;
}

if (mb_strlen($title) < 5 || mb_strlen($title) > 200) {
    $_SESSION['error'] = 'Заголовок должен быть от 5 до 200 символов';
    header('Location: product.php?id=' . $smartphone_id);
    exit;
}

if (mb_strlen($comment) < 10) {
    $_SESSION['error'] = 'Комментарий должен содержать не менее 10 символов';
    header('Location: product.php?id=' . $smartphone_id);
    exit;
}

try {
    $reviewModel = new Review($db);
    
    // Проверяем, не оставлял ли пользователь уже отзыв на этот товар
    if ($reviewModel->hasUserReviewed($smartphone_id, $user_id)) {
        $_SESSION['error'] = 'Вы уже оставляли отзыв на этот товар';
        header('Location: product.php?id=' . $smartphone_id);
        exit;
    }
    
    // Добавляем отзыв
    $result = $reviewModel->addReview([
        'user_id' => $user_id,
        'smartphone_id' => $smartphone_id,
        'rating' => $rating,
        'title' => $title,
        'comment' => $comment,
        'pros' => $pros,
        'cons' => $cons,
        'is_verified_purchase' => $is_verified
    ]);
    
    if ($result) {
        $_SESSION['success'] = 'Отзыв успешно добавлен и будет опубликован после проверки!';
    } else {
        $_SESSION['error'] = 'Произошла ошибка при добавлении отзыва';
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Произошла ошибка: ' . $e->getMessage();
}

header('Location: product.php?id=' . $smartphone_id);
exit;
?>