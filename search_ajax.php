<?php
// search_ajax.php
require_once __DIR__ . "/inc/db.php";
require_once __DIR__ . "/inc/functions.php";

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    exit;
}

$search_term = "%$query%";

// Ищем в таблице products, так как она у тебя основная для всех типов товаров
$sql = "
    SELECT id, name, brand, price, 
           (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1) as image
    FROM products p
    WHERE (name LIKE ? OR brand LIKE ? OR sku LIKE ?) 
    AND status = 'active'
    LIMIT 5
";

$stmt = $db->prepare($sql);
$stmt->bind_param("sss", $search_term, $search_term, $search_term);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($results)) {
    echo '<div class="p-3 text-muted">Ничего не найдено</div>';
} else {
    foreach ($results as $item) {
        // Проверяем наличие картинки, если нет - ставим заглушку
        $image = !empty($item['image']) ? $item['image'] : 'assets/no-image.png';
        // Если у тебя цена в БД в копейках или требует форматирования:
        $price = number_format($item['price'], 2, '.', ' ') . ' BYN';

        echo "
        <a href='product.php?id={$item['id']}' class='search-result-item d-flex align-items-center p-2 text-decoration-none text-dark'>
            <img src='{$image}' style='width: 40px; height: 40px; object-fit: contain;' class='me-2'>
            <div>
                <div class='fw-bold' style='font-size: 14px;'>{$item['brand']} {$item['name']}</div>
                <div class='text-primary fw-semibold' style='font-size: 12px;'>{$price}</div>
            </div>
        </a>";
    }
}