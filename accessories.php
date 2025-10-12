<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';

$catQuery = $db->query("SELECT id, name FROM categories WHERE id <> 1 ORDER BY name");
$accessory_categories = [];
while ($cat = $catQuery->fetch_assoc()) {
    $accessory_categories[] = $cat;
}

$selected_category = isset($_GET['cat']) ? (int)$_GET['cat'] : (!empty($accessory_categories) ? $accessory_categories[0]['id'] : 0);

$items_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

$countQuery = $db->prepare("SELECT COUNT(*) AS count FROM accessories WHERE category_id = ?");
$countQuery->bind_param('i', $selected_category);
$countQuery->execute();
$countResult = $countQuery->get_result();
$total_items = $countResult->fetch_assoc()['count'];
$total_pages = ceil($total_items / $items_per_page);

$query = $db->prepare("
    SELECT 
        a.id, a.name, a.brand, a.model, a.price, a.stock, a.description,
        p.discount_percent
    FROM accessories a
    LEFT JOIN promotions p
        ON p.product_type = 'accessory'
        AND p.product_id = a.id
        AND p.is_active = 1
        AND p.start_date <= CURDATE()
        AND (p.end_date >= CURDATE() OR p.end_date IS NULL)
    WHERE a.category_id = ?
    LIMIT ? OFFSET ?
");
$query->bind_param('iii', $selected_category, $items_per_page, $offset);
$query->execute();
$result = $query->get_result();
?>

<section class="catalog" id="catalog">
    <div class="container">
        <div class="catalog-header">
            <div class="header-left">
                <h2>Аксессуары</h2>
            </div>
            <div class="header-right">
                <?php if (!empty($accessory_categories)): ?>
                    <div class="category-switcher">
                        <?php foreach ($accessory_categories as $cat): ?>
                            <a href="accessories.php?cat=<?= $cat['id']; ?>&page=1" class="category-link <?= ($cat['id'] == $selected_category) ? 'active-category' : ''; ?>">
                                <?= htmlspecialchars($cat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="catalog-items">
            <?php if ($result->num_rows > 0): ?>
                <div class="row">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                            $orig = (float)$row['price'];
                            $disc = $row['discount_percent'];
                            $hasPromo = $disc !== null;
                            $newPrice = $hasPromo ? $orig * (100 - $disc) / 100 : $orig;
                        ?>
                        <div class="col-md-4 mb-4">
                            <div class="accessory-card" onclick="window.location.href='accessory.php?id=<?= $row['id']; ?>'">
                                <?php if (isset($row['image_url']) && $row['image_url']): ?>
                                    <div class="accessory-image">
                                        <img src="<?= htmlspecialchars($row['image_url']); ?>" alt="<?= htmlspecialchars($row['brand'] . ' ' . $row['name']); ?>">
                                    </div>
                                <?php endif; ?>
                                <div class="accessory-info">
                                    <h3><?= htmlspecialchars($row['brand'] . ' ' . $row['name']); ?></h3>
                                    <p class="accessory-model"><?= htmlspecialchars($row['model']); ?></p>
                                    <?php if ($hasPromo): ?>
                                        <p class="accessory-price">
                                            <del><?= number_format($orig, 0, ',', ' '); ?> руб.</del>
                                            <?= number_format($newPrice, 0, ',', ' '); ?> руб.
                                            <span class="badge bg-danger">-<?= $disc; ?>%</span>
                                        </p>
                                    <?php else: ?>
                                        <p class="accessory-price"><?= number_format($orig, 0, ',', ' '); ?> руб.</p>
                                    <?php endif; ?>
                                    <p class="accessory-stock">В наличии: <?= $row['stock']; ?> шт.</p>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="no-items">Нет товаров в этой категории.</p>
            <?php endif; ?>
        </div>
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <nav>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="accessories.php?cat=<?= $selected_category; ?>&page=<?= $page - 1; ?>">«</a>
                            </li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="accessories.php?cat=<?= $selected_category; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="accessories.php?cat=<?= $selected_category; ?>&page=<?= $page + 1; ?>">»</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
