<?php
require_once __DIR__ . '/inc/db.php';

$screen_size = $_POST['screen_size'] ?? null;
$ram = $_POST['ram'] ?? null;
$storage = $_POST['storage'] ?? null;
$processor = $_POST['processor'] ?? null;
$os = $_POST['os'] ?? null;
$battery_capacity = $_POST['battery_capacity'] ?? null;

$conditions = [];
$params = [];
$types = '';

if ($screen_size) {
    $conditions[] = "sp.screen_size = ?";
    $params[] = $screen_size;
    $types .= 'd';
}
if ($ram) {
    $conditions[] = "sp.ram = ?";
    $params[] = $ram;
    $types .= 'i';
}
if ($storage) {
    $conditions[] = "sp.storage = ?";
    $params[] = $storage;
    $types .= 'i';
}
if ($processor) {
    $conditions[] = "sp.processor = ?";
    $params[] = $processor;
    $types .= 's';
}
if ($os) {
    $conditions[] = "sp.os = ?";
    $params[] = $os;
    $types .= 's';
}
if ($battery_capacity) {
    $conditions[] = "sp.battery_capacity >= ?";
    $params[] = $battery_capacity;
    $types .= 'i';
}

$sql = "
    SELECT
        s.id,
        s.name,
        s.brand,
        s.model,
        s.price,
        s.stock,
        si.image_url,
        sp.screen_size,
        sp.ram,
        sp.storage,
        sp.processor,
        sp.battery_capacity,
        sp.os,
        p.discount_percent
    FROM smartphones s
    JOIN smartphone_specs sp ON s.id = sp.smartphone_id
    LEFT JOIN smartphone_images si ON s.id = si.smartphone_id
    LEFT JOIN promotions p
        ON p.product_type = 'smartphone'
        AND p.product_id = s.id
        AND p.is_active = 1
        AND p.start_date <= CURDATE()
        AND (p.end_date >= CURDATE() OR p.end_date IS NULL)
";

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY s.id ORDER BY sp.ram DESC, sp.battery_capacity DESC LIMIT 10";

$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo '<div class="row">';
    while ($row = $result->fetch_assoc()) {
        $orig = (float)$row['price'];
        $disc = $row['discount_percent'];
        $hasPromo = $disc !== null;
        $newPrice = $hasPromo ? $orig * (100 - $disc) / 100 : $orig;
        echo '<div class="col-md-3 mb-4">
                <div class="feature-card">
                    <div class="product-card-content" onclick="window.location.href=\'product.php?id='.$row['id'].'\'">
                        <img src="'.htmlspecialchars($row['image_url'] ?: 'uploads/no-image.jpg').'" alt="'.htmlspecialchars($row['name']).'" class="product-image">
                        <h3>'.htmlspecialchars($row['brand'].' '.$row['name']).'</h3>
                        <p>Модель: '.htmlspecialchars($row['model']).'</p>';
                        if ($hasPromo) {
                            echo '<p class="product-price">
                                <del>'.number_format($orig, 0, ',', ' ').' руб.</del>
                                '.number_format($newPrice, 0, ',', ' ').' руб.
                                <span class="badge bg-danger">-'.$disc.'%</span>
                            </p>';
                        } else {
                            echo '<p class="product-price">'.number_format($orig, 0, ',', ' ').' руб.</p>';
                        }
        echo '          <p>В наличии: '.$row['stock'].' шт.</p>
                    </div>

                    <form method="POST" action="cart.php" class="cart-form" onsubmit="event.preventDefault(); addToCart(this);" onclick="event.stopPropagation();">
                        <input type="hidden" name="product_id" value="'.$row['id'].'">
                        <input type="number" name="quantity" min="1" max="'.$row['stock'].'" value="1" class="quantity-input">
                        <button type="submit" class="btn btn-sm btn-primary">Добавить в корзину</button>
                    </form>
                </div>
              </div>';
    }
    echo '</div>';
} else {
    echo '<p>Нет подходящих смартфонов. Попробуйте изменить фильтры.</p>';
}
?>

<script>
function addToCart(form) {
    $.ajax({
        url: "cart.php",
        method: "POST",
        data: $(form).serialize(),
        success: function(response) {
            alert("Товар добавлен в корзину!");
        }
    });
}
</script>