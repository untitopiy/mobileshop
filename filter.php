<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

// Получаем параметры фильтрации
$brand = $_POST['brand'] ?? null;
$min_price = $_POST['min_price'] ?? null;
$max_price = $_POST['max_price'] ?? null;
$ram = $_POST['ram'] ?? null;
$storage = $_POST['storage'] ?? null;
$processor = $_POST['processor'] ?? null;
$os = $_POST['os'] ?? null;
$battery_capacity = $_POST['battery_capacity'] ?? null;
$screen_size = $_POST['screen_size'] ?? null;
$category_id = $_POST['category_id'] ?? null;

$conditions = ["p.status = 'active'"];
$params = [];
$types = '';

if ($brand) {
    $conditions[] = "p.brand = ?";
    $params[] = $brand;
    $types .= 's';
}

if ($category_id) {
    $conditions[] = "p.category_id = ?";
    $params[] = $category_id;
    $types .= 'i';
}

if ($min_price !== null && $min_price !== '') {
    $conditions[] = "COALESCE(pv.price, p.price) >= ?";
    $params[] = $min_price;
    $types .= 'd';
}

if ($max_price !== null && $max_price !== '') {
    $conditions[] = "COALESCE(pv.price, p.price) <= ?";
    $params[] = $max_price;
    $types .= 'd';
}

// Фильтры по характеристикам (через атрибуты)
if ($ram) {
    $conditions[] = "EXISTS (
        SELECT 1 FROM product_attributes pa
        JOIN attributes a ON a.id = pa.attribute_id
        WHERE pa.product_id = p.id 
          AND a.slug = 'ram' 
          AND pa.value_number >= ?
    )";
    $params[] = $ram;
    $types .= 'i';
}

if ($storage) {
    $conditions[] = "EXISTS (
        SELECT 1 FROM product_attributes pa
        JOIN attributes a ON a.id = pa.attribute_id
        WHERE pa.product_id = p.id 
          AND a.slug = 'storage' 
          AND pa.value_number >= ?
    )";
    $params[] = $storage;
    $types .= 'i';
}

if ($battery_capacity) {
    $conditions[] = "EXISTS (
        SELECT 1 FROM product_attributes pa
        JOIN attributes a ON a.id = pa.attribute_id
        WHERE pa.product_id = p.id 
          AND a.slug = 'battery' 
          AND pa.value_number >= ?
    )";
    $params[] = $battery_capacity;
    $types .= 'i';
}

if ($screen_size) {
    $conditions[] = "EXISTS (
        SELECT 1 FROM product_attributes pa
        JOIN attributes a ON a.id = pa.attribute_id
        WHERE pa.product_id = p.id 
          AND a.slug = 'screen_size' 
          AND pa.value_number = ?
    )";
    $params[] = $screen_size;
    $types .= 'd';
}

if ($processor) {
    $conditions[] = "EXISTS (
        SELECT 1 FROM product_attributes pa
        JOIN attributes a ON a.id = pa.attribute_id
        WHERE pa.product_id = p.id 
          AND a.slug = 'processor' 
          AND pa.value_text LIKE ?
    )";
    $params[] = "%$processor%";
    $types .= 's';
}

if ($os) {
    $conditions[] = "EXISTS (
        SELECT 1 FROM product_attributes pa
        JOIN attributes a ON a.id = pa.attribute_id
        WHERE pa.product_id = p.id 
          AND a.slug = 'os' 
          AND pa.value_text LIKE ?
    )";
    $params[] = "%$os%";
    $types .= 's';
}

$where_clause = implode(" AND ", $conditions);

// Исправленный запрос с правильным получением изображений
$sql = "
    SELECT
        p.id,
        p.name,
        p.brand,
        p.model,
        COALESCE(MIN(pv.price), p.price) as min_price,
        COALESCE(MAX(pv.price), p.price) as max_price,
        COALESCE(SUM(pv.quantity), p.quantity) as total_stock,
        COALESCE(
            (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
            (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1),
            'assets/no-image.png'
        ) as image_url,
        CASE 
            WHEN prom.discount_type = 'percent' THEN prom.discount_value
            ELSE NULL
        END as discount_percent,
        prom.name as promotion_name,
        prom.discount_type,
        prom.discount_value
    FROM products p
    LEFT JOIN product_variations pv ON pv.product_id = p.id
    LEFT JOIN promotion_products pp ON pp.product_id = p.id
    LEFT JOIN promotions prom ON prom.id = pp.promotion_id 
        AND prom.is_active = 1 
        AND prom.starts_at <= NOW() 
        AND (prom.expires_at >= NOW() OR prom.expires_at IS NULL)
    WHERE $where_clause
    GROUP BY p.id
    ORDER BY p.rating DESC, p.sales_count DESC
    LIMIT 20
";

$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo '<div class="row">';
    while ($row = $result->fetch_assoc()) {
        $price_display = $row['min_price'] == $row['max_price'] 
            ? formatPrice($row['min_price'])
            : 'от ' . formatPrice($row['min_price']);
        
        // Проверяем наличие скидки
        $has_promo = false;
        $promo_price = $row['min_price'];
        $discount_text = '';
        
        if ($row['discount_percent'] !== null && $row['discount_percent'] > 0) {
            $has_promo = true;
            $promo_price = $row['min_price'] * (100 - $row['discount_percent']) / 100;
            $discount_text = '-' . $row['discount_percent'] . '%';
        } elseif ($row['discount_type'] === 'fixed' && $row['discount_value'] > 0) {
            $has_promo = true;
            $promo_price = max(0, $row['min_price'] - $row['discount_value']);
            $discount_text = '- ' . formatPrice($row['discount_value']);
        }
        ?>
        <div class="col-md-3 mb-4">
            <div class="feature-card h-100" onclick="window.location.href='product.php?id=<?= $row['id']; ?>'">
                <div class="product-image-wrapper text-center p-3" style="height: 200px; background: #f8f9fa;">
                    <img src="<?= htmlspecialchars($row['image_url']); ?>" 
                         alt="<?= htmlspecialchars($row['name']); ?>" 
                         class="img-fluid" style="max-height: 180px; object-fit: contain;">
                    <?php if ($has_promo): ?>
                        <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                            <?= $discount_text ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="product-info p-3">
                    <h5 class="product-title"><?= htmlspecialchars($row['brand'] . ' ' . $row['name']); ?></h5>
                    <p class="product-model text-muted small"><?= htmlspecialchars($row['model']); ?></p>
                    
                    <div class="product-price-block mb-2">
                        <?php if ($has_promo): ?>
                            <span class="current-price text-danger fw-bold">
                                <?= formatPrice($promo_price); ?>
                            </span>
                            <span class="old-price ms-2">
                                <del><?= formatPrice($row['min_price']); ?></del>
                            </span>
                        <?php else: ?>
                            <span class="current-price fw-bold"><?= $price_display; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <p class="product-stock small <?= $row['total_stock'] > 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $row['total_stock'] > 0 ? 'В наличии: ' . $row['total_stock'] . ' шт.' : 'Нет в наличии' ?>
                    </p>
                    
                    <?php if (!empty($row['promotion_name'])): ?>
                        <p class="promotion-label small text-primary mb-2">
                            <i class="fas fa-tag"></i> <?= htmlspecialchars($row['promotion_name']) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="card-footer bg-white border-top-0 p-3">
                    <form method="POST" action="pages/cart.php" class="cart-form" onclick="event.stopPropagation();">
                        <input type="hidden" name="product_id" value="<?= $row['id']; ?>">
                        <div class="d-flex gap-2">
                            <input type="number" name="quantity" min="1" max="<?= $row['total_stock']; ?>" value="1"
                                   class="form-control form-control-sm" style="width: 70px;" 
                                   <?= $row['total_stock'] <= 0 ? 'disabled' : '' ?>>
                            <button type="submit" class="btn btn-sm btn-primary flex-grow-1" 
                                    <?= $row['total_stock'] <= 0 ? 'disabled' : '' ?>>
                                В корзину
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    echo '</div>';
} else {
    echo '<div class="col-12">
            <div class="alert alert-info text-center py-5">
                <i class="fas fa-search fa-3x mb-3"></i>
                <h5>Нет подходящих товаров</h5>
                <p class="mb-0">Попробуйте изменить параметры фильтрации</p>
            </div>
          </div>';
}
?>

<style>
.feature-card {
    cursor: pointer;
    border: 1px solid #eee;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.feature-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.product-image-wrapper {
    position: relative;
    height: 200px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
}
.product-info {
    flex-grow: 1;
}
.card-footer {
    background: white;
    border-top: 1px solid #eee;
}
</style>

<script>
function addToCart(form) {
    event.preventDefault();
    
    fetch('pages/cart.php', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Товар добавлен в корзину!');
            // Обновляем счетчик корзины, если есть
            if (data.cart_count !== undefined) {
                const cartBadge = document.querySelector('.cart-badge');
                if (cartBadge) {
                    cartBadge.textContent = data.cart_count;
                }
            }
        } else {
            alert('Ошибка при добавлении в корзину');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ошибка при добавлении в корзину');
    });
}
</script>