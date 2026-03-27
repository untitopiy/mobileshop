<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    $_SESSION['error'] = "Неверный ID товара";
    header("Location: manage_products.php");
    exit;
}

$product = $db->query("SELECT name FROM products WHERE id = $product_id")->fetch_assoc();

if (!$product) {
    $_SESSION['error'] = "Товар не найден";
    header("Location: manage_products.php");
    exit;
}

// Удаляем изображения
$images = $db->query("SELECT image_url FROM product_images WHERE product_id = $product_id");
while ($img = $images->fetch_assoc()) {
    $file_path = __DIR__ . '/../' . $img['image_url'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// Удаляем изображения вариантов
$variations = $db->query("SELECT image_url FROM product_variations WHERE product_id = $product_id");
while ($var = $variations->fetch_assoc()) {
    if (!empty($var['image_url'])) {
        $file_path = __DIR__ . '/../' . $var['image_url'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
}

// Просто удаляем товар (связанные записи удалятся автоматически при ON DELETE CASCADE)
$db->query("DELETE FROM products WHERE id = $product_id");

if ($db->affected_rows > 0) {
    $_SESSION['success'] = "Товар \"" . htmlspecialchars($product['name']) . "\" успешно удален";
} else {
    $_SESSION['error'] = "Ошибка при удалении товара";
}

header("Location: manage_products.php");
exit;