<?php
// Функция для безопасного вывода данных
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Функция генерации случайного кода купона
function generateCouponCode($length = 8) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $coupon = '';
    for ($i = 0; $i < $length; $i++) {
        $coupon .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $coupon;
}

// НОВАЯ: Получение информации о продавце
function getSellerInfo($db, $seller_id) {
    $stmt = $db->prepare("SELECT id, shop_name, shop_slug, logo_url, rating, reviews_count 
                          FROM sellers WHERE id = ? AND is_active = 1");
    $stmt->bind_param('i', $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// НОВАЯ: Получение вариантов товара
function getProductVariations($db, $product_id) {
    $stmt = $db->prepare("SELECT id, sku, price, old_price, quantity, image_url 
                          FROM product_variations 
                          WHERE product_id = ? AND quantity > 0");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    return $stmt->get_result();
}

// НОВАЯ: Получение основной цены товара
function getProductPrice($db, $product_id) {
    $stmt = $db->prepare("SELECT MIN(price) as min_price, MAX(price) as max_price 
                          FROM product_variations 
                          WHERE product_id = ? AND quantity > 0");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['min_price'] == $result['max_price']) {
        return number_format($result['min_price'], 2, '.', ' ');
    }
    return number_format($result['min_price'], 2, '.', ' ') . ' - ' . 
           number_format($result['max_price'], 2, '.', ' ');
}

// НОВАЯ: Проверка наличия товара
function checkProductStock($db, $product_id, $variation_id = null) {
    if ($variation_id) {
        $stmt = $db->prepare("SELECT quantity FROM product_variations WHERE id = ?");
        $stmt->bind_param('i', $variation_id);
    } else {
        $stmt = $db->prepare("SELECT SUM(quantity) as quantity FROM product_variations WHERE product_id = ?");
        $stmt->bind_param('i', $product_id);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['quantity'] ?? 0;
}

// НОВАЯ: Получение статусов заказов
function getOrderStatuses($db) {
    $result = $db->query("SELECT id, name, slug, color FROM order_statuses ORDER BY sort_order");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// НОВАЯ: Получение способов доставки
function getShippingMethods($db) {
    $result = $db->query("SELECT id, name, price, free_from, estimated_days_min, estimated_days_max 
                          FROM shipping_methods WHERE is_active = 1 ORDER BY sort_order");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// НОВАЯ: Получение способов оплаты
function getPaymentMethods($db) {
    $result = $db->query("SELECT id, name, code, description, commission 
                          FROM payment_methods WHERE is_active = 1 ORDER BY sort_order");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// НОВАЯ: Форматирование цены
function formatPrice($price) {
    return number_format($price, 2, '.', ' ') . ' ₽';
}

// НОВАЯ: Получение атрибутов товара
function getProductAttributes($db, $product_id) {
    $stmt = $db->prepare("
        SELECT a.name, a.type, a.unit, pa.value_text, pa.value_number, pa.value_boolean
        FROM product_attributes pa
        JOIN attributes a ON a.id = pa.attribute_id
        WHERE pa.product_id = ? AND a.is_visible_on_product = 1
        ORDER BY a.sort_order
    ");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    return $stmt->get_result();
}

// НОВАЯ: Получение тегов товара
function getProductTags($db, $product_id) {
    $stmt = $db->prepare("
        SELECT t.name, t.slug
        FROM tags t
        JOIN product_tags pt ON pt.tag_id = t.id
        WHERE pt.product_id = ?
    ");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    return $stmt->get_result();
}
?>