<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

header('Content-Type: text/html; charset=utf-8');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
if (mb_strlen($query) < 2) {
    exit;
}

$searchTerm = '%' . $query . '%';

$sql = "
    SELECT 
        p.id,
        p.name,
        p.brand,
        p.model,
        p.sku,
        p.barcode,
        p.price,
        (
            SELECT pi.image_url
            FROM product_images pi
            WHERE pi.product_id = p.id
            ORDER BY pi.is_primary DESC, pi.id ASC
            LIMIT 1
        ) AS image
    FROM products p
    WHERE p.status = 'active'
      AND (
            p.name LIKE ?
         OR p.brand LIKE ?
         OR p.model LIKE ?
         OR p.sku LIKE ?
         OR p.barcode LIKE ?
         OR CONCAT_WS(' ', p.brand, p.name) LIKE ?
         OR CONCAT_WS(' ', p.name, p.brand) LIKE ?
         OR CONCAT_WS(' ', p.brand, p.model, p.name) LIKE ?
         OR CONCAT_WS(' ', p.name, p.model, p.brand) LIKE ?
      )
    ORDER BY p.name ASC
    LIMIT 10
";

$stmt = $db->prepare($sql);
$stmt->bind_param(
    "sssssssss",
    $searchTerm,
    $searchTerm,
    $searchTerm,
    $searchTerm,
    $searchTerm,
    $searchTerm,
    $searchTerm,
    $searchTerm,
    $searchTerm
);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($results)) {
    echo '<div class="p-3 text-muted">Ничего не найдено</div>';
    exit;
}

foreach ($results as $item) {
    $image = !empty($item['image']) ? '/mobileshop/' . ltrim($item['image'], '/') : '/mobileshop/assets/no-image.png';
    $price = number_format((float)$item['price'], 2, '.', ' ') . ' BYN';

    $title = trim(implode(' ', array_filter([
        $item['brand'] ?? '',
        $item['name'] ?? ''
    ])));

    $meta = trim(implode(' · ', array_filter([
        !empty($item['model']) ? 'Модель: ' . $item['model'] : '',
        !empty($item['sku']) ? 'Артикул: ' . $item['sku'] : ''
    ])));

    echo '
    <a href="/mobileshop/product.php?id=' . (int)$item['id'] . '" class="search-result-item d-flex align-items-center p-2 text-decoration-none text-dark">
        <img src="' . htmlspecialchars($image) . '" alt="' . htmlspecialchars($title) . '" style="width:40px; height:40px; object-fit:contain;" class="me-2">
        <div class="flex-grow-1">
            <div class="fw-bold" style="font-size:14px;">' . htmlspecialchars($title) . '</div>
            ' . ($meta !== '' ? '<div class="text-muted" style="font-size:12px;">' . htmlspecialchars($meta) . '</div>' : '') . '
            <div class="text-primary fw-semibold" style="font-size:12px;">' . $price . '</div>
        </div>
    </a>';
}
?>