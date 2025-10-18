<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';

// Вывод сообщений об ошибках/успехе
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            ' . htmlspecialchars($_SESSION['error']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            ' . htmlspecialchars($_SESSION['success']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['success']);
}
// завершение вывода сообщений об ошибках/успехе

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container'><h2>Ошибка: Товар не найден.</h2></div>";
    require_once __DIR__ . '/inc/footer.php';
    exit;
}

$product_id = (int)$_GET['id'];

$query = $db->prepare("
    SELECT s.id, s.name, s.brand, s.model, s.price, s.stock, s.description, 
           sp.screen_size, sp.resolution, sp.processor, sp.ram, sp.storage, sp.battery_capacity, 
           sp.camera_main, sp.camera_front, sp.os, sp.color, sp.weight 
    FROM smartphones s
    LEFT JOIN smartphone_specs sp ON s.id = sp.smartphone_id
    WHERE s.id = ?
");
$query->bind_param('i', $product_id);
$query->execute();
$product = $query->get_result()->fetch_assoc();

if (!$product) {
    echo "<div class='container'><h2>Ошибка: Товар не найден.</h2></div>";
    require_once __DIR__ . '/inc/footer.php';
    exit;
}

$image_query = $db->prepare("SELECT image_url FROM smartphone_images WHERE smartphone_id = ?");
$image_query->bind_param('i', $product_id);
$image_query->execute();
$image_result = $image_query->get_result();
$images = $image_result->fetch_all(MYSQLI_ASSOC);

$session_id = session_id();
$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;
$insert_view = $db->prepare("INSERT INTO product_views (user_id, session_id, product_type, product_id) VALUES (?, ?, 'smartphone', ?)");
$insert_view->bind_param("isi", $user_id, $session_id, $product_id);
$insert_view->execute();

$promo_stmt = $db->prepare("
    SELECT discount_percent
    FROM promotions
    WHERE product_type = 'smartphone'
      AND product_id = ?
      AND is_active = 1
      AND start_date <= CURDATE()
      AND (end_date >= CURDATE() OR end_date IS NULL)
    LIMIT 1
");
$promo_stmt->bind_param('i', $product_id);
$promo_stmt->execute();
$promo = $promo_stmt->get_result()->fetch_assoc();
?>
<div class="container product-container">
    <div class="product">
        <div class="product-gallery">
            <?php if (!empty($images)): ?>
                <img id="main-image" src="<?= htmlspecialchars($images[0]['image_url']); ?>" alt="<?= htmlspecialchars($product['name']); ?>">
                <div class="thumbnail-gallery">
                    <?php foreach ($images as $img): ?>
                        <img src="<?= htmlspecialchars($img['image_url']); ?>" alt="Фото <?= htmlspecialchars($product['name']); ?>" onclick="changeImage(this)">
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <img src="assets/no-image.png" alt="Нет изображения">
            <?php endif; ?>
        </div>
        <div class="product-info">
            <h1><?= htmlspecialchars($product['brand'] . ' ' . $product['name']); ?></h1>
            <?php if ($promo): 
                $orig = (float)$product['price'];
                $disc = (int)$promo['discount_percent'];
                $new = $orig * (100 - $disc) / 100;
            ?>
                <p class="product-price">
                    <del><?= number_format($orig, 0, ',', ' '); ?> руб.</del>
                    <?= number_format($new, 0, ',', ' '); ?> руб.
                    <span class="badge bg-danger">-<?= $disc; ?>%</span>
                </p>
            <?php else: ?>
                <p class="product-price"><?= number_format($product['price'], 0, ',', ' '); ?> руб.</p>
            <?php endif; ?>
            <p class="product-stock">В наличии: <?= $product['stock']; ?> шт.</p>
            <p class="product-description"><?= nl2br(htmlspecialchars($product['description'])); ?></p>
            <h3>Характеристики:</h3>
            <ul class="product-specs">
                <li><strong>Модель:</strong> <?= htmlspecialchars($product['model']); ?></li>
                <li><strong>Экран:</strong> <?= htmlspecialchars($product['screen_size']); ?>" (<?= htmlspecialchars($product['resolution']); ?>)</li>
                <li><strong>Процессор:</strong> <?= htmlspecialchars($product['processor']); ?></li>
                <li><strong>ОЗУ:</strong> <?= htmlspecialchars($product['ram']); ?> ГБ</li>
                <li><strong>Память:</strong> <?= htmlspecialchars($product['storage']); ?> ГБ</li>
                <li><strong>Аккумулятор:</strong> <?= htmlspecialchars($product['battery_capacity']); ?> мАч</li>
                <li><strong>Камера:</strong> <?= htmlspecialchars($product['camera_main']); ?></li>
                <li><strong>Фронтальная камера:</strong> <?= htmlspecialchars($product['camera_front']); ?></li>
                <li><strong>ОС:</strong> <?= htmlspecialchars($product['os']); ?></li>
                <li><strong>Цвет:</strong> <?= htmlspecialchars($product['color']); ?></li>
                <li><strong>Вес:</strong> <?= htmlspecialchars($product['weight']); ?> г</li>
            </ul>
            <form method="POST" action="cart.php" class="cart-form">
                <input type="hidden" name="product_id" value="<?= $product_id; ?>">
                <input type="number" name="quantity" min="1" max="<?= $product['stock']; ?>" value="1" class="quantity-input">
                <button type="submit" class="btn btn-primary">Добавить в корзину</button>
            </form>
        </div>
    </div>
</div>
<script>
function changeImage(img) {
    document.getElementById('main-image').src = img.src;
}
</script>
<?php
$rec_query = $db->prepare("
    SELECT a.id, a.category_id, a.name, a.brand, a.model, a.price, a.stock, a.description 
    FROM accessories a
    JOIN accessory_supported_smartphones ast ON a.id = ast.accessory_id
    WHERE ast.smartphone_id = ?
    ORDER BY RAND() 
    LIMIT 3
");
$rec_query->bind_param('i', $product_id);
$rec_query->execute();
$rec_result = $rec_query->get_result();

$promoAccStmt = $db->prepare("
    SELECT discount_percent
    FROM promotions
    WHERE product_type = 'accessory'
      AND product_id = ?
      AND is_active = 1
      AND start_date <= CURDATE()
      AND (end_date >= CURDATE() OR end_date IS NULL)
    LIMIT 1
");

if ($rec_result->num_rows > 0):
?>
    <div class="container recommended-container text-center">
        <h2>Рекомендуемые аксессуары</h2><br><br>
        <div class="row justify-content-center">
            <?php while($rec = $rec_result->fetch_assoc()): ?>
                <?php
                $promoAccStmt->bind_param('i', $rec['id']);
                $promoAccStmt->execute();
                $pa = $promoAccStmt->get_result()->fetch_assoc();
                ?>
                <div class="col-md-3 mb-4">
                    <div class="feature-card">
                        <h5><?= htmlspecialchars($rec['brand'] . ' ' . $rec['name']); ?></h5><br>
                        <p>Модель: <?= htmlspecialchars($rec['model']); ?></p>
                        <?php if ($pa):
                            $origA = (float)$rec['price'];
                            $discA = (int)$pa['discount_percent'];
                            $newA = $origA * (100 - $discA) / 100;
                        ?>
                            <p class="accessory-price">
                                <del><?= number_format($origA, 0, ',', ' '); ?> руб.</del>
                                <?= number_format($newA, 0, ',', ' '); ?> руб.
                                <span class="badge bg-danger">-<?= $discA; ?>%</span>
                            </p>
                        <?php else: ?>
                            <p>Цена: <?= number_format($rec['price'], 0, ',', ' '); ?> руб.</p>
                        <?php endif; ?>
                        <p>В наличии: <?= $rec['stock']; ?> шт.</p>
                        <form method="POST" action="cart.php" class="cart-form">
                            <input type="hidden" name="product_id" value="<?= $rec['id']; ?>">
                            <input type="hidden" name="product_type" value="accessory">
                            <input type="number" name="quantity" min="1" max="<?= $rec['stock']; ?>" value="1" class="quantity-input">
                            <button type="submit" class="btn btn-primary btn-sm">В корзину</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <br>
<?php
endif;

$recSmartQuery = $db->prepare("
    SELECT s.id, s.name, s.brand, s.model, s.price, s.stock,
           (SELECT image_url FROM smartphone_images WHERE smartphone_id = s.id LIMIT 1) AS image_url,
           COUNT(pv.id) AS view_count
    FROM product_views pv
    JOIN smartphones s ON s.id = pv.product_id
    WHERE pv.product_type = 'smartphone'
      AND pv.product_id != ?
      AND pv.session_id IN (
          SELECT session_id FROM product_views
          WHERE product_type = 'smartphone' AND product_id = ?
      )
    GROUP BY s.id
    ORDER BY view_count DESC
    LIMIT 3
");
$recSmartQuery->bind_param("ii", $product_id, $product_id);
$recSmartQuery->execute();
$recSmartResult = $recSmartQuery->get_result();

$promoPhoneStmt = $db->prepare("
    SELECT discount_percent
    FROM promotions
    WHERE product_type = 'smartphone'
      AND product_id = ?
      AND is_active = 1
      AND start_date <= CURDATE()
      AND (end_date >= CURDATE() OR end_date IS NULL)
    LIMIT 1
");

if ($recSmartResult->num_rows > 0):
?>
<div class="container recommended-container text-center">
    <h2>Похожие смартфоны</h2><br><br>
    <div class="row justify-content-center">
        <?php while($recSmart = $recSmartResult->fetch_assoc()): ?>
            <?php
            $promoPhoneStmt->bind_param('i', $recSmart['id']);
            $promoPhoneStmt->execute();
            $pp = $promoPhoneStmt->get_result()->fetch_assoc();
            ?>
            <div class="col-md-3 mb-4">
                <div class="feature-card" onclick="window.location.href='product.php?id=<?= $recSmart['id']; ?>'">
                    <?php if (!empty($recSmart['image_url'])): ?>
                        <img src="<?= htmlspecialchars($recSmart['image_url']); ?>" alt="<?= htmlspecialchars($recSmart['name']); ?>" class="product-image">
                    <?php else: ?>
                        <img src="assets/no-image.png" alt="Нет изображения" class="product-image">
                    <?php endif; ?>
                    <h5><?= htmlspecialchars($recSmart['brand'] . ' ' . $recSmart['name']); ?></h5>
                    <p>Модель: <?= htmlspecialchars($recSmart['model']); ?></p>
                    <?php if ($pp):
                        $origP = (float)$recSmart['price'];
                        $discP = (int)$pp['discount_percent'];
                        $newP = $origP * (100 - $discP) / 100;
                    ?>
                        <p class="product-price">
                            <del><?= number_format($origP, 0, ',', ' '); ?> руб.</del>
                            <?= number_format($newP, 0, ',', ' '); ?> руб.
                            <span class="badge bg-danger">-<?= $discP; ?>%</span>
                        </p>
                    <?php else: ?>
                        <p>Цена: <?= number_format($recSmart['price'], 0, ',', ' '); ?> руб.</p>
                    <?php endif; ?>
                    <p>В наличии: <?= $recSmart['stock']; ?> шт.</p>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>
<?php
endif;

// Подключаем модель отзывов
require_once __DIR__ . '/models/Review.php';
$reviewModel = new Review($db);

// ИСПРАВЛЕНИЕ: используем $_SESSION['id'] вместо $_SESSION['user_id']
$current_user_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;

// Получаем данные об отзывах для этого смартфона
$average_rating = $reviewModel->getAverageRating($product_id);
$reviews = $reviewModel->getSmartphoneReviews($product_id);
$has_reviewed = $current_user_id ? $reviewModel->hasUserReviewed($product_id, $current_user_id) : false;
?>

<!-- Блок рейтинга и отзывов -->
<div class="reviews-section mt-5">
    <h3>Отзывы о товаре</h3>
    
    <!-- Общий рейтинг -->
    <div class="rating-summary mb-4 p-4 border rounded">
        <div class="row align-items-center">
            <div class="col-md-4 text-center">
                <div class="average-rating">
                    <h2 class="text-primary mb-0"><?= number_format($average_rating['avg_rating'] ?? 0, 1) ?></h2>
                    <div class="rating-stars mb-2">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= round($average_rating['avg_rating'] ?? 0) ? 'filled' : '' ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <span class="text-muted"><?= $average_rating['total_reviews'] ?? 0 ?> отзывов</span>
                </div>
            </div>
            <div class="col-md-8">
                <!-- Распределение по звездам -->
                <?php for($i = 5; $i >= 1; $i--): ?>
                    <?php 
                    $rating_count = $average_rating["rating_$i"] ?? 0; 
                    $total_reviews = $average_rating['total_reviews'] ?? 1;
                    $percentage = $total_reviews > 0 ? ($rating_count / $total_reviews) * 100 : 0;
                    ?>
                    <div class="rating-bar mb-2">
                        <div class="d-flex align-items-center">
                            <span class="me-2"><?= $i ?> ★</span>
                            <div class="progress flex-grow-1" style="height: 8px;">
                                <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                            </div>
                            <span class="ms-2 text-muted"><?= $rating_count ?></span>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Форма добавления отзыва -->
    <?php if($current_user_id && !$has_reviewed): ?>
    <div class="review-form mb-5">
        <h4>Оставить отзыв</h4>
        <form method="POST" action="add_review.php">
            <input type="hidden" name="smartphone_id" value="<?= $product_id ?>">
            <input type="hidden" name="user_id" value="<?= $current_user_id ?>">
            
            <!-- Оценка -->
            <div class="mb-3">
                <label class="form-label">Ваша оценка *</label>
                <div class="rating-input">
                    <?php for($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required>
                        <label for="star<?= $i ?>">★</label>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Заголовок -->
            <div class="mb-3">
                <label for="title" class="form-label">Заголовок отзыва *</label>
                <input type="text" class="form-control" id="title" name="title" required placeholder="Кратко опишите ваше впечатление">
            </div>
            
            <!-- Комментарий -->
            <div class="mb-3">
                <label for="comment" class="form-label">Подробный отзыв *</label>
                <textarea class="form-control" id="comment" name="comment" rows="4" required placeholder="Расскажите о вашем опыте использования товара"></textarea>
            </div>
            
            <!-- Плюсы и минусы -->
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="pros" class="form-label">Достоинства</label>
                        <textarea class="form-control" id="pros" name="pros" rows="3" placeholder="Что вам понравилось?"></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="cons" class="form-label">Недостатки</label>
                        <textarea class="form-control" id="cons" name="cons" rows="3" placeholder="Что можно улучшить?"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Подтверждение покупки -->
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="is_verified" name="is_verified" value="1">
                <label class="form-check-label" for="is_verified">Подтверждаю, что покупал этот товар</label>
            </div>
            
            <button type="submit" class="btn btn-primary">Опубликовать отзыв</button>
        </form>
    </div>
    <?php elseif(!$current_user_id): ?>
        <div class="alert alert-info">
            <a href="auth.php" class="alert-link">Войдите</a>, чтобы оставить отзыв
        </div>
    <?php endif; ?>

    <!-- Список отзывов -->
    <div class="reviews-list">
        <h4>Отзывы покупателей</h4>
        <?php if(empty($reviews)): ?>
            <p class="text-muted">Пока нет отзывов на этот товар. Будьте первым!</p>
        <?php else: ?>
            <?php foreach($reviews as $review): ?>
            <div class="review-item border-bottom pb-3 mb-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <strong><?= htmlspecialchars($review['full_name'] ?: $review['login']) ?></strong>
                        <?php if($review['is_verified_purchase']): ?>
                            <span class="badge bg-success ms-2">✓ Проверенная покупка</span>
                        <?php endif; ?>
                        <div class="rating-stars small">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?= $i <= $review['rating'] ? 'filled' : '' ?>">★</span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <small class="text-muted"><?= date('d.m.Y', strtotime($review['created_at'])) ?></small>
                </div>
                
                <h6 class="fw-bold"><?= htmlspecialchars($review['title']) ?></h6>
                <p class="mb-2"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                
                <?php if(!empty($review['pros']) || !empty($review['cons'])): ?>
                <div class="row mt-2">
                    <?php if(!empty($review['pros'])): ?>
                    <div class="col-md-6">
                        <div class="pros-box p-2 bg-success bg-opacity-10 rounded">
                            <strong>👍 Достоинства:</strong>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($review['pros'])) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($review['cons'])): ?>
                    <div class="col-md-6">
                        <div class="cons-box p-2 bg-danger bg-opacity-10 rounded">
                            <strong>👎 Недостатки:</strong>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($review['cons'])) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Блок лайков -->
                <div class="mt-2">
                    <small class="text-muted">
                        Отзыв полезен? 
                        <?php if($current_user_id): ?>
                            <a href="like_review.php?review_id=<?= $review['id'] ?>&helpful=1" class="text-decoration-none">Да</a> 
                            (<?= $review['likes_count'] ?>)
                        <?php else: ?>
                            <span>Да (<?= $review['likes_count'] ?>)</span>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.rating-stars .star {
    color: #ddd;
    font-size: 1.2em;
}

.rating-stars .star.filled {
    color: #ffc107;
}

.rating-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 5px;
}

.rating-input input {
    display: none;
}

.rating-input label {
    font-size: 1.5em;
    color: #ddd;
    cursor: pointer;
    transition: color 0.2s;
}

.rating-input input:checked ~ label,
.rating-input label:hover,
.rating-input label:hover ~ label {
    color: #ffc107;
}

.rating-stars.small .star {
    font-size: 1em;
}

.progress-bar {
    background-color: #28a745;
}
</style>

<?php
require_once __DIR__ . '/inc/footer.php';
?>