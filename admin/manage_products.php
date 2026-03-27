<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/functions.php';

// Параметры фильтрации
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Настройка пагинации
$items_per_page = isset($_GET['items_per_page']) && is_numeric($_GET['items_per_page']) ? (int)$_GET['items_per_page'] : 15;
if ($items_per_page < 1) $items_per_page = 15;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

// Построение WHERE условий
$where_conditions = ["1=1"];
$params = [];
$types = "";

if ($filter_type !== 'all') {
    $where_conditions[] = "p.type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if ($filter_status !== 'all') {
    $where_conditions[] = "p.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_category > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $filter_category;
    $types .= "i";
}

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.brand LIKE ? OR p.model LIKE ? OR p.sku LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

$where_clause = implode(" AND ", $where_conditions);

// Подсчет общего количества
$count_sql = "
    SELECT COUNT(DISTINCT p.id) as count
    FROM products p
    WHERE $where_clause
";
$count_stmt = $db->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_products = $count_stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_products / $items_per_page);

// Получение товаров
$sql = "
    SELECT 
        p.id,
        p.name,
        p.brand,
        p.model,
        p.sku,
        p.type,
        p.status,
        p.price as base_price,
        p.quantity as total_stock,
        p.sales_count,
        p.views_count,
        p.created_at,
        c.name as category_name,
        s.shop_name as seller_name,
        MIN(pv.price) as min_price,
        MAX(pv.price) as max_price,
        SUM(pv.quantity) as variations_stock,
        (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_url
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN sellers s ON s.id = p.seller_id
    LEFT JOIN product_variations pv ON pv.product_id = p.id
    WHERE $where_clause
    GROUP BY p.id
    ORDER BY p.id DESC
    LIMIT ? OFFSET ?
";

$stmt = $db->prepare($sql);
$params[] = $items_per_page;
$params[] = $offset;
$types .= "ii";

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// Получение списка категорий для фильтра
$categories = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");

// Получение списка продавцов
$sellers = $db->query("SELECT id, shop_name FROM sellers WHERE is_active = 1 ORDER BY shop_name");
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Управление товарами</h1>
        <div>
            <a href="add_product.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Добавить товар
            </a>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Фильтры</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Тип товара</label>
                    <select name="type" class="form-select">
                        <option value="all" <?= $filter_type == 'all' ? 'selected' : '' ?>>Все</option>
                        <option value="simple" <?= $filter_type == 'simple' ? 'selected' : '' ?>>Простой</option>
                        <option value="variable" <?= $filter_type == 'variable' ? 'selected' : '' ?>>С вариантами</option>
                        <option value="digital" <?= $filter_type == 'digital' ? 'selected' : '' ?>>Цифровой</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Статус</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $filter_status == 'all' ? 'selected' : '' ?>>Все</option>
                        <option value="active" <?= $filter_status == 'active' ? 'selected' : '' ?>>Активные</option>
                        <option value="inactive" <?= $filter_status == 'inactive' ? 'selected' : '' ?>>Неактивные</option>
                        <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>На модерации</option>
                        <option value="draft" <?= $filter_status == 'draft' ? 'selected' : '' ?>>Черновики</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Категория</label>
                    <select name="category" class="form-select">
                        <option value="0">Все категории</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filter_category == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Поиск</label>
                    <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Название, бренд, артикул...">
                </div>
                <div class="col-md-1">
                    <label class="form-label">На странице</label>
                    <select name="items_per_page" class="form-select">
                        <?php foreach ([10, 15, 20, 30, 50] as $num): ?>
                            <option value="<?= $num ?>" <?= $items_per_page == $num ? 'selected' : '' ?>><?= $num ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Применить
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица товаров -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Изображение</th>
                            <th>Название</th>
                            <th>Категория</th>
                            <th>Продавец</th>
                            <th>Цена</th>
                            <th>Наличие</th>
                            <th>Продажи</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products->num_rows > 0): ?>
                            <?php while ($product = $products->fetch_assoc()): 
                                $price_display = $product['min_price'] == $product['max_price'] 
                                    ? formatPrice($product['min_price'] ?: $product['base_price'])
                                    : 'от ' . formatPrice($product['min_price']) . ' до ' . formatPrice($product['max_price']);
                                
                                $stock = $product['variations_stock'] ?: $product['total_stock'];
                            ?>
                                <tr>
                                    <td><?= $product['id'] ?></td>
                                    <td>
                                        <?php if (!empty($product['image_url'])): ?>
                                            <img src="<?= $base_url . htmlspecialchars($product['image_url']) ?>" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                                 style="width: 50px; height: 50px; object-fit: contain;">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($product['brand'] . ' ' . $product['name']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($product['model']) ?></small>
                                        <?php if (!empty($product['sku'])): ?>
                                            <br><small class="text-muted">Арт: <?= htmlspecialchars($product['sku']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($product['category_name'] ?: '—') ?></td>
                                    <td>
                                        <?php if (!empty($product['seller_name'])): ?>
                                            <small><?= htmlspecialchars($product['seller_name']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= $price_display ?></strong>
                                        <br>
                                        <small class="text-muted">Базовая: <?= formatPrice($product['base_price']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $stock > 0 ? 'bg-success' : 'bg-danger' ?>">
                                            <?= $stock > 0 ? $stock . ' шт.' : 'Нет' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= $product['sales_count'] ?></span>
                                        <br>
                                        <small class="text-muted">просм: <?= $product['views_count'] ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'active' => 'success',
                                            'inactive' => 'secondary',
                                            'pending' => 'warning',
                                            'draft' => 'light',
                                            'rejected' => 'danger'
                                        ];
                                        $color = $status_colors[$product['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>">
                                            <?= $product['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="edit_product.php?id=<?= $product['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="product_variations.php?id=<?= $product['id'] ?>" 
                                               class="btn btn-sm btn-outline-info" title="Варианты">
                                                <i class="fas fa-tags"></i>
                                            </a>
                                            <a href="product_images.php?id=<?= $product['id'] ?>" 
                                               class="btn btn-sm btn-outline-secondary" title="Изображения">
                                                <i class="fas fa-images"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteProduct(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>')"
                                                    title="Удалить">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <p class="mb-0">Товары не найдены</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Пагинация -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Модальное окно подтверждения удаления -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Подтверждение удаления</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Вы уверены, что хотите удалить товар <strong id="productName"></strong>?</p>
                <p class="text-danger"><small>Это действие нельзя отменить.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">Удалить</a>
            </div>
        </div>
    </div>
</div>

<script>
function deleteProduct(id, name) {
    // Останавливаем всплытие события, чтобы не вызывать другие обработчики
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    document.getElementById('productName').textContent = name;
    document.getElementById('confirmDelete').href = 'delete_product.php?id=' + id;
    
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>