<?php
// api_products.php - API для получения информации о товарах
session_start();
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Поиск товаров по названию или бренду
if ($action === 'search' && isset($_GET['q'])) {
    $query = trim($_GET['q']);
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => false, 'message' => 'Введите минимум 2 символа']);
        exit;
    }
    
    $search_term = "%$query%";
    $stmt = $db->prepare("
        SELECT id, name, brand, model, price, 
               COALESCE(
                   (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
                   'assets/no-image.png'
               ) as image_url,
               COALESCE(MIN(pv.price), p.price) as min_price,
               COALESCE(MAX(pv.price), p.price) as max_price
        FROM products p
        LEFT JOIN product_variations pv ON pv.product_id = p.id
        WHERE (p.name LIKE ? OR p.brand LIKE ? OR p.model LIKE ?) 
          AND p.status = 'active'
        GROUP BY p.id
        LIMIT ?
    ");
    $stmt->bind_param("sssi", $search_term, $search_term, $search_term, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $price_display = $row['min_price'] == $row['max_price'] 
            ? formatPrice($row['min_price'])
            : 'от ' . formatPrice($row['min_price']);
        
        $products[] = [
            'id' => $row['id'],
            'name' => $row['brand'] . ' ' . $row['name'],
            'model' => $row['model'],
            'price' => $price_display,
            'price_value' => (float)$row['min_price'],
            'image' => $row['image_url']
        ];
    }
    
    echo json_encode(['success' => true, 'products' => $products]);
    exit;
}

// Получение детальной информации о товаре для сравнения
if ($action === 'get_compare' && isset($_GET['ids'])) {
    $ids = explode(',', $_GET['ids']);
    $ids = array_filter($ids, 'is_numeric');
    $ids = array_map('intval', $ids);
    $ids = array_slice($ids, 0, 4);
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'Нет товаров для сравнения']);
        exit;
    }
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    
    $stmt = $db->prepare("
        SELECT 
            p.id, p.name, p.brand, p.model, p.description,
            p.rating, p.sales_count,
            COALESCE(MIN(pv.price), p.price) as min_price,
            COALESCE(MAX(pv.price), p.price) as max_price,
            COALESCE(SUM(pv.quantity), p.quantity) as total_stock,
            COALESCE(
                (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
                'assets/no-image.png'
            ) as image_url,
            c.name as category_name
        FROM products p
        LEFT JOIN product_variations pv ON pv.product_id = p.id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.id IN ($placeholders) AND p.status = 'active'
        GROUP BY p.id
    ");
    
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        // Получаем основные характеристики
        $attr_stmt = $db->prepare("
            SELECT a.name, a.slug, a.unit,
                   pa.value_text, pa.value_number, pa.value_boolean
            FROM product_attributes pa
            JOIN attributes a ON a.id = pa.attribute_id
            WHERE pa.product_id = ? AND a.is_visible_on_product = 1
            LIMIT 10
        ");
        $attr_stmt->bind_param('i', $row['id']);
        $attr_stmt->execute();
        $attrs = $attr_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $specs = [];
        foreach ($attrs as $attr) {
            if ($attr['value_text']) {
                $value = $attr['value_text'];
            } elseif ($attr['value_number']) {
                $value = $attr['value_number'] . ($attr['unit'] ? ' ' . $attr['unit'] : '');
            } elseif ($attr['value_boolean']) {
                $value = $attr['value_boolean'] ? 'Да' : 'Нет';
            } else {
                continue;
            }
            $specs[$attr['slug']] = $value;
        }
        
        $price_display = $row['min_price'] == $row['max_price'] 
            ? formatPrice($row['min_price'])
            : 'от ' . formatPrice($row['min_price']) . ' до ' . formatPrice($row['max_price']);
        
        $products[] = [
            'id' => $row['id'],
            'name' => $row['brand'] . ' ' . $row['name'],
            'model' => $row['model'],
            'price' => $price_display,
            'price_value' => (float)$row['min_price'],
            'rating' => (float)$row['rating'],
            'sales_count' => (int)$row['sales_count'],
            'stock' => (int)$row['total_stock'],
            'category' => $row['category_name'],
            'description' => substr($row['description'] ?? '', 0, 300),
            'specs' => $specs,
            'image' => $row['image_url'],
            'url' => '/mobileshop/product.php?id=' . $row['id']
        ];
    }
    
    echo json_encode(['success' => true, 'products' => $products]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
exit;   