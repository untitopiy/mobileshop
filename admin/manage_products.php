<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/header.php';

// Получаем фильтр из GET-параметра. Ожидаем массив значений: "all", "smartphones", "accessories"
$filter = isset($_GET['filter']) ? $_GET['filter'] : [];
if (!is_array($filter)) {
    $filter = [$filter];
}

// Определяем, что показывать
$show_all = false;
$show_smartphones = false;
$show_accessories = false;
if (in_array('all', $filter) || (in_array('smartphones', $filter) && in_array('accessories', $filter)) || empty($filter)) {
    $show_all = true;
} elseif (in_array('smartphones', $filter)) {
    $show_smartphones = true;
} elseif (in_array('accessories', $filter)) {
    $show_accessories = true;
}

// Настройка количества товаров на странице
$default_items_per_page = 15;
$items_per_page = isset($_GET['items_per_page']) && is_numeric($_GET['items_per_page']) ? (int)$_GET['items_per_page'] : $default_items_per_page;
if ($items_per_page < 1) {
    $items_per_page = $default_items_per_page;
}

// Пагинация
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $items_per_page;

// Формируем SQL-запрос в зависимости от выбранного фильтра
if ($show_all) {
    $query = "SELECT id, name, brand, model, price, 'smartphone' AS product_type FROM smartphones
              UNION ALL
              SELECT id, name, brand, model, price, 'accessory' AS product_type FROM accessories
              ORDER BY id DESC";
} elseif ($show_smartphones) {
    $query = "SELECT id, name, brand, model, price, 'smartphone' AS product_type FROM smartphones ORDER BY id DESC";
} elseif ($show_accessories) {
    $query = "SELECT id, name, brand, model, price, 'accessory' AS product_type FROM accessories ORDER BY id DESC";
}

// Применяем пагинацию: оборачиваем запрос в подзапрос
$paginated_query = "SELECT * FROM ( $query ) AS prod LIMIT $items_per_page OFFSET $offset";
$result = $db->query($paginated_query);

// Получаем общее количество товаров для подсчёта страниц
if ($show_all) {
    $count_query = "SELECT COUNT(*) AS count FROM (SELECT id FROM smartphones UNION ALL SELECT id FROM accessories) AS total";
} elseif ($show_smartphones) {
    $count_query = "SELECT COUNT(*) AS count FROM smartphones";
} elseif ($show_accessories) {
    $count_query = "SELECT COUNT(*) AS count FROM accessories";
}
$countResult = $db->query($count_query);
$total_products = $countResult->fetch_assoc()['count'];
$total_pages = ceil($total_products / $items_per_page);
?>

<div class="container my-5">


    <!-- Фильтр, выбор количества товаров на странице и кнопки добавления -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <form method="GET" class="d-flex align-items-center flex-wrap">
            <!-- Фильтры по типу товара -->
            <div class="form-check me-3">
                <input class="form-check-input" type="checkbox" name="filter[]" value="all" id="chkAll" <?= (in_array('all', $filter) || empty($filter)) ? 'checked' : '' ?>>
                <label class="form-check-label" for="chkAll">Все товары</label>
            </div>
            <div class="form-check me-3">
                <input class="form-check-input" type="checkbox" name="filter[]" value="accessories" id="chkAccessories" <?= (in_array('accessories', $filter) && !$show_all) ? 'checked' : '' ?>>
                <label class="form-check-label" for="chkAccessories">Аксессуары</label>
            </div>
            <div class="form-check me-3">
                <input class="form-check-input" type="checkbox" name="filter[]" value="smartphones" id="chkSmartphones" <?= (in_array('smartphones', $filter) && !$show_all) ? 'checked' : '' ?>>
                <label class="form-check-label" for="chkSmartphones">Смартфоны</label>
            </div>
            <!-- Выбор количества товаров на странице -->
            <div class="me-3">
                <label for="items_per_page" class="me-2">На странице:</label>
                <select name="items_per_page" id="items_per_page" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                    <?php foreach ([5, 10, 15, 20, 30] as $num): ?>
                        <option value="<?= $num ?>" <?= ($items_per_page == $num) ? 'selected' : '' ?>><?= $num ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-bottom me-2 mt-3">Применить</button>
</form>
<div class="">
    <a href="add_smartphone.php" class="btn btn-sm btn-bottom me-2">Добавить смартфон</a>
    <a href="add_accessory.php" class="btn btn-sm btn-bottom mt-3">Добавить аксессуар</a>
</div>

    </div>
    
    <!-- Таблица товаров -->
    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Название</th>
                <th>Бренд</th>
                <th>Модель</th>
                <th>Цена</th>
                <th>Тип товара</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($product = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $product['id'] ?></td>
                        <td><?= htmlspecialchars($product['name']) ?></td>
                        <td><?= htmlspecialchars($product['brand']) ?></td>
                        <td><?= htmlspecialchars($product['model']) ?></td>
                        <td><?= number_format($product['price'], 0, ',', ' ') ?> руб.</td>
                        <td>
                            <?php if ($product['product_type'] == 'smartphone'): ?>
                                <span class="badge bg-dark">Смартфон</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Аксессуар</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($product['product_type'] == 'smartphone'): ?>
                                <div class="btn-group" role="group">
                                    <a href="edit_smartphone.php?id=<?= $product['id'] ?>" class="btn btn-sm action-btn">Изменить</a>
                                    <a href="delete_smartphone.php?id=<?= $product['id'] ?>" class="btn btn-sm action-btn" onclick="return confirm('Удалить этот смартфон?');">Удалить</a>
                                    <a href="smartphone_specs.php?id=<?= $product['id'] ?>" class="btn btn-sm action-btn">Характеристики</a>
                                </div>
                            <?php else: ?>
                                <div class="btn-group" role="group">
                                    <a href="edit_accessory.php?id=<?= $product['id'] ?>" class="btn btn-sm action-btn">Изменить</a>
                                    <a href="delete_accessory.php?id=<?= $product['id'] ?>" class="btn btn-sm action-btn" onclick="return confirm('Удалить этот аксессуар?');">Удалить</a>
                                    <a href="accessory_supported.php?id=<?= $product['id'] ?>" class="btn btn-sm action-btn">Поддерживаемые устройства</a>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">Нет товаров для отображения.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Пагинация -->
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">«</a>
                </li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">»</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <!-- Нижняя панель навигации -->
    <div class="d-flex justify-content-start mt-4">
        <a href="index.php" class="btn btn-bottom">Админка</a>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
