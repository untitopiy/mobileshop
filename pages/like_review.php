<?php
session_start();
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/models/Review.php';

if (!isset($_SESSION['id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

if (!isset($_GET['review_id']) || !is_numeric($_GET['review_id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$review_id = (int)$_GET['review_id'];
$user_id = (int)$_SESSION['id'];

try {
    $reviewModel = new Review($db);
    $result = $reviewModel->incrementLikes($review_id);
    
    if ($result) {
        echo 'success';
    } else {
        echo 'error';
    }
} catch (Exception $e) {
    echo 'error';
}
?>