<?php
require_once __DIR__ . '/inc/header.php';

$items_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

$total_items_query = $db->query("SELECT COUNT(*) AS count FROM smartphones");
$total_items = $total_items_query->fetch_assoc()['count'];
$total_pages = ceil($total_items / $items_per_page);

$query = $db->prepare("
    SELECT
        s.id,
        s.name,
        s.brand,
        s.model,
        s.price,
        s.stock,
        si.image_url,
        p.discount_percent
    FROM smartphones s
    LEFT JOIN smartphone_images si ON s.id = si.smartphone_id
    LEFT JOIN promotions p
        ON p.product_type = 'smartphone'
        AND p.product_id = s.id
        AND p.is_active = 1
        AND p.start_date <= CURDATE()
        AND (p.end_date >= CURDATE() OR p.end_date IS NULL)
    GROUP BY s.id
    LIMIT ? OFFSET ?
");
$query->bind_param('ii', $items_per_page, $offset);
$query->execute();
$result = $query->get_result();
?>

<section class="slider" id="slider">
    <div class="container">
        <h1>Добро пожаловать в наш магазин смартфонов!</h1>
        <p>Мы предлагаем широкий выбор современных смартфонов от ведущих производителей.</p>
        <p>Наш умный фильтр и система рекомендаций помогут вам быстро подобрать идеальный смартфон по вашим параметрам. Выберите характеристики, которые вам важны, и мы покажем лучшие варианты!</p>
        <div class="button-group">
            <button class="btn" onclick="location.href='#features'">Смотреть каталог</button>
            <button class="btn btn-secondary" onclick="location.href='main.php#filter'">Подбор по параметрам</button>
        </div>
    </div>
</section>

<section class="features" id="features">
    <div class="container">
        <div class="row align-items-center mb-4">
            <div class="col-md-6">
                <h2>Наш ассортимент</h2>
            </div>
            <div class="col-md-6 text-end">
                <a href="accessories.php" class="btn btn-link">Аксессуары</a>
            </div>
        </div>
        <div class="row">
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                    $orig = (float)$row['price'];
                    $disc = $row['discount_percent'];
                    $hasPromo = $disc !== null;
                    $newPrice = $hasPromo ? $orig * (100 - $disc) / 100 : $orig;
                ?>
                <div class="feature-card" onclick="location.href='product.php?id=<?= $row['id']; ?>'">
                    <img src="<?= htmlspecialchars($row['image_url'] ?: 'uploads/no-image.jpg'); ?>"
                         alt="<?= htmlspecialchars($row['name']); ?>" class="product-image">
                    <h3><?= htmlspecialchars($row['brand'] . ' ' . $row['name']); ?></h3>
                    <p>Модель: <?= htmlspecialchars($row['model']); ?></p>
                    <?php if ($hasPromo): ?>
                        <p class="product-price">
                            <del><?= number_format($orig, 0, ',', ' '); ?> руб.</del>
                            <?= number_format($newPrice, 0, ',', ' '); ?> руб.
                            <span class="badge bg-danger">-<?= $disc; ?>%</span>
                        </p>
                    <?php else: ?>
                        <p class="product-price"><?= number_format($orig, 0, ',', ' '); ?> руб.</p>
                    <?php endif; ?>
                    <p>В наличии: <?= $row['stock']; ?> шт.</p>
                    <form method="POST" action="cart.php" class="cart-form">
                        <input type="hidden" name="product_id" value="<?= $row['id']; ?>">
                        <input type="number" name="quantity" min="1" max="<?= $row['stock']; ?>" value="1"
                               class="quantity-input">
                        <button type="submit" class="btn btn-sm btn-primary">В корзину</button>
                        <a href="product.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-secondary">Подробнее</a>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<div class="pagination-container">
    <nav>
        <ul class="pagination">
            <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $page - 1; ?>">«</a></li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?= $i; ?>"><?= $i; ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $page + 1; ?>">»</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</div>

<section class="advantages" id="advantages">
    <div class="container">
        <h2>Преимущества работы с нами</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="advantage-item">
                    <h3><i class="fas fa-check-circle"></i> Качество</h3>
                    <p>Мы гарантируем оригинальные устройства и высокую надежность.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="advantage-item">
                    <h3><i class="fas fa-headset"></i> Поддержка</h3>
                    <p>Наши специалисты помогут вам выбрать лучший смартфон.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="advantage-item">
                    <h3><i class="fas fa-handshake"></i> Гарантия</h3>
                    <p>Официальная гарантия и сервисное обслуживание.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="join-us" id="join-us">
    <div class="container">
        <h2>Присоединяйтесь к нам</h2>
        <p>Создайте аккаунт и получите доступ к эксклюзивным скидкам и предложениям!</p>
        <button class="btn" onclick="location.href='pages/reg.php'">Зарегистрироваться</button>
    </div>
</section>

<button class="scroll-up" onclick="scrollToSection('slider')">
    <i class="fas fa-arrow-up"></i>
</button>

<?php
require_once __DIR__ . '/inc/footer.php';
?>
