<?php
session_start();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Проверка авторизации через AJAX
if (isset($_GET['check_auth'])) {
    header('Content-Type: application/json');
    echo json_encode(['authenticated' => isset($_SESSION['id'])]);
    exit;
}

// Получение количества избранного
if (isset($_GET['ajax_count'])) {
    header('Content-Type: application/json');
    $count = 0;
    if (isset($_SESSION['id'])) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
        $stmt->bind_param('i', $_SESSION['id']);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
    }
    echo json_encode(['count' => $count]);
    exit;
}

// Получение списка избранного пользователя
if (isset($_GET['ajax_list'])) {
    header('Content-Type: application/json');
    $wishlist = [];
    if (isset($_SESSION['id'])) {
        $stmt = $db->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
        $stmt->bind_param('i', $_SESSION['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $wishlist[] = $row['product_id'];
        }
        $stmt->close();
    }
    echo json_encode(['success' => true, 'wishlist' => $wishlist]);
    exit;
}

// Добавление/удаление из избранного
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_wishlist'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['id'])) {
        echo json_encode(['success' => false, 'message' => 'Необходимо авторизоваться']);
        exit;
    }
    
    $user_id = $_SESSION['id'];
    $product_id = (int)$_POST['product_id'];
    
    // Проверяем, есть ли уже в избранном
    $check = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check->bind_param('ii', $user_id, $product_id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();
    
    if ($exists) {
        $delete = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $delete->bind_param('ii', $user_id, $product_id);
        $delete->execute();
        $delete->close();
        
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Товар удален из избранного']);
        exit;
    } else {
        $insert = $db->prepare("INSERT INTO wishlist (user_id, product_id, created_at) VALUES (?, ?, NOW())");
        $insert->bind_param('ii', $user_id, $product_id);
        $insert->execute();
        $insert->close();
        
        echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Товар добавлен в избранное']);
        exit;
    }
}

// Если не AJAX запрос, показываем страницу избранного
if (!isset($_SESSION['id'])) {
    $_SESSION['redirect_after_login'] = 'wishlist.php';
    header("Location: auth.php");
    exit;
}

require_once __DIR__ . '/../inc/header.php';

$user_id = $_SESSION['id'];

// Получаем товары из избранного
$query = $db->prepare("
    SELECT 
        w.id as wishlist_id,
        w.created_at as added_at,
        p.id,
        p.name,
        p.brand,
        p.model,
        p.rating,
        p.sales_count,
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
    FROM wishlist w
    JOIN products p ON p.id = w.product_id
    LEFT JOIN product_variations pv ON pv.product_id = p.id
    LEFT JOIN promotion_products pp ON pp.product_id = p.id
    LEFT JOIN promotions prom ON prom.id = pp.promotion_id 
        AND prom.is_active = 1 
        AND prom.starts_at <= NOW() 
        AND (prom.expires_at >= NOW() OR prom.expires_at IS NULL)
    WHERE w.user_id = ? AND p.status = 'active'
    GROUP BY p.id
    ORDER BY w.created_at DESC
");
$query->bind_param('i', $user_id);
$query->execute();
$result = $query->get_result();

$wishlist_items = [];
while ($row = $result->fetch_assoc()) {
    $wishlist_items[] = $row;
}

$total_items = count($wishlist_items);
?>

<div class="container wishlist-container py-4">
    <h1 class="mb-4">
        <i class="fas fa-heart text-danger me-2"></i>Избранное
        <?php if ($total_items > 0): ?>
            <span class="badge bg-danger ms-2"><?= $total_items ?> товаров</span>
        <?php endif; ?>
    </h1>

    <?php if (empty($wishlist_items)): ?>
        <div class="text-center py-5">
            <i class="fas fa-heart-broken fa-4x text-muted mb-3"></i>
            <h3 class="text-muted">Избранное пусто</h3>
            <p class="mb-4">Добавляйте товары в избранное, чтобы не потерять понравившиеся позиции</p>
            <a href="../index.php" class="btn btn-primary">Перейти к покупкам</a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($wishlist_items as $item): ?>
                <?php
                    $price_display = $item['min_price'] == $item['max_price'] 
                        ? formatPrice($item['min_price'])
                        : 'от ' . formatPrice($item['min_price']);
                    
                    $has_promo = $item['discount_percent'] !== null && $item['discount_percent'] > 0;
                    $promo_price = $item['min_price'];
                    
                    if ($item['discount_type'] === 'fixed' && $item['discount_value'] > 0) {
                        $promo_price = max(0, $item['min_price'] - $item['discount_value']);
                        $has_promo = true;
                    } else if ($has_promo) {
                        $promo_price = $item['min_price'] * (100 - $item['discount_percent']) / 100;
                    }
                    
                    // Исправленный код для формирования правильного пути к изображению
                    $image_path = $item['image_url'];
                    
                    // Если путь пустой или содержит no-image.png
                    if (empty($image_path) || $image_path == 'assets/no-image.png') {
                        $image_path = '/mobileshop/assets/no-image.png';
                    }
                    // Если путь начинается с assets/
                    elseif (strpos($image_path, 'assets/') === 0) {
                        $image_path = '/mobileshop/' . $image_path;
                    }
                    // Если путь начинается с uploads/
                    elseif (strpos($image_path, 'uploads/') === 0) {
                        $image_path = '/mobileshop/' . $image_path;
                    }
                    // Если путь начинается с ../
                    elseif (strpos($image_path, '../') === 0) {
                        $image_path = str_replace('../', '/mobileshop/', $image_path);
                    }
                    // Если путь просто имя файла (1.jpg, 8.jpg и т.д.)
                    elseif (!strpos($image_path, '/')) {
                        $image_path = '/mobileshop/uploads/' . $image_path;
                    }
                    // В остальных случаях добавляем /mobileshop/ в начало
                    else {
                        $image_path = '/mobileshop/' . $image_path;
                    }
                ?>
                <div class="col-md-4 col-lg-3 mb-4">
                    <div class="wishlist-card h-100" onclick="location.href='../product.php?id=<?= $item['id']; ?>'">
                        <div class="position-relative">
                            <div class="product-image-wrapper text-center p-3" style="height: 200px; background: #f8f9fa;">
                                <img src="<?= htmlspecialchars($image_path); ?>" 
                                     alt="<?= htmlspecialchars($item['name']); ?>" 
                                     class="img-fluid" style="max-height: 180px; object-fit: contain;">
                            </div>
                            
                            <?php if ($has_promo): ?>
                                <div class="position-absolute top-0 end-0 m-3">
                                    <span class="badge bg-danger fs-6 p-2">
                                        <?php if ($item['discount_percent']): ?>
                                            -<?= $item['discount_percent'] ?>%
                                        <?php else: ?>
                                            Акция
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <button class="btn btn-sm btn-danger position-absolute top-0 start-0 m-2 remove-from-wishlist"
                                    data-product-id="<?= $item['id'] ?>"
                                    onclick="event.stopPropagation(); removeFromWishlist(<?= $item['id'] ?>)"
                                    style="border-radius: 50%; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        
                        <div class="card-body">
                            <h5 class="card-title mb-1"><?= htmlspecialchars($item['brand'] . ' ' . $item['name']); ?></h5>
                            <p class="text-muted small mb-2"><?= htmlspecialchars($item['model']) ?></p>
                            
                            <div class="price-block mb-2">
                                <?php if ($has_promo): ?>
                                    <span class="current-price text-danger fw-bold fs-5">
                                        <?= formatPrice($promo_price); ?>
                                    </span>
                                    <span class="old-price ms-2 text-muted">
                                        <del><?= formatPrice($item['min_price']); ?></del>
                                    </span>
                                <?php else: ?>
                                    <span class="fw-bold fs-5"><?= $price_display; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-meta small text-muted mb-2">
                                <span class="me-3">
                                    <i class="fas fa-star text-warning"></i>
                                    <?= number_format($item['rating'], 1) ?>
                                </span>
                                <span>
                                    <i class="fas fa-shopping-cart"></i>
                                    <?= $item['sales_count'] ?> продаж
                                </span>
                            </div>
                            
                            <p class="stock-info small <?= $item['total_stock'] > 0 ? 'text-success' : 'text-danger' ?> mb-2">
                                <i class="fas <?= $item['total_stock'] > 0 ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                <?= $item['total_stock'] > 0 ? 'В наличии: ' . $item['total_stock'] . ' шт.' : 'Нет в наличии' ?>
                            </p>
                            
                            <div class="d-flex gap-2 mt-2">
                                <form method="POST" action="cart.php" class="flex-grow-1" onclick="event.stopPropagation();">
                                    <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="btn btn-sm btn-primary w-100" 
                                            <?= $item['total_stock'] <= 0 ? 'disabled' : '' ?>>
                                        <i class="fas fa-shopping-cart me-1"></i> В корзину
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-transparent border-top-0 small text-muted">
                            <i class="far fa-clock me-1"></i>
                            Добавлено: <?= date('d.m.Y', strtotime($item['added_at'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-4">
            <button class="btn btn-outline-danger" onclick="clearAllWishlist()">
                <i class="fas fa-trash-alt me-2"></i>Очистить всё избранное
            </button>
        </div>
    <?php endif; ?>
</div>

<style>
.wishlist-card {
    cursor: pointer;
    border: 1px solid #eee;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s;
    background: white;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.wishlist-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.wishlist-card .card-body {
    flex-grow: 1;
}
.product-image-wrapper {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
}
.remove-from-wishlist {
    opacity: 0.8;
    transition: opacity 0.2s;
    z-index: 10;
}
.remove-from-wishlist:hover {
    opacity: 1;
    transform: scale(1.05);
}
</style>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>