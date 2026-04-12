<?php
session_start();
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Инициализация CSRF-токена если его нет
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ========== ПОЛУЧЕНИЕ ID ДЛЯ СРАВНЕНИЯ ==========
$compare_ids = [];

// 1. Сначала проверяем GET параметры (самый приоритетный источник)
if (isset($_GET['ids']) && !empty($_GET['ids'])) {
    $compare_ids = explode(',', $_GET['ids']);
    $compare_ids = array_filter($compare_ids, 'is_numeric');
    $compare_ids = array_map('intval', $compare_ids);
    $compare_ids = array_unique($compare_ids);
    if (count($compare_ids) > 4) {
        $compare_ids = array_slice($compare_ids, 0, 4);
    }
    $_SESSION['compare_ids'] = $compare_ids;
}
// 2. Если нет GET, проверяем сессию
else if (isset($_SESSION['compare_ids']) && !empty($_SESSION['compare_ids'])) {
    $compare_ids = $_SESSION['compare_ids'];
}
// 3. Если пользователь авторизован, загружаем из базы
else if (isset($_SESSION['id'])) {
    $user_id = (int)$_SESSION['id'];
    $stmt = $db->prepare("SELECT product_id FROM `compare` WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $compare_ids[] = $row['product_id'];
    }
    $_SESSION['compare_ids'] = $compare_ids;
    $stmt->close();
}

// Ограничиваем 4 товарами
$compare_ids = array_slice($compare_ids, 0, 4);

// Если нет товаров, показываем сообщение
if (empty($compare_ids)) {
    echo '<div class="container py-5">';
    echo '<div class="alert alert-info text-center">';
    echo '<i class="fas fa-exchange-alt fa-3x mb-3"></i>';
    echo '<h4>Нет товаров для сравнения</h4>';
    echo '<p>Выберите товары на странице каталога или в карточке товара, чтобы добавить их в сравнение.</p>';
    echo '<a href="../index.php" class="btn btn-primary mt-3">Перейти в каталог</a>';
    echo '</div>';
    echo '</div>';
    require_once __DIR__ . '/../inc/footer.php';
    exit;
}

// ========== ПОЛУЧЕНИЕ ДАННЫХ О ТОВАРАХ ==========
$products = [];
$all_attributes = [];

$placeholders = implode(',', array_fill(0, count($compare_ids), '?'));
$types = str_repeat('i', count($compare_ids));

$query = $db->prepare("
    SELECT 
        p.id,
        p.name,
        p.brand,
        p.model,
        p.description,
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
        c.name as category_name
    FROM products p
    LEFT JOIN product_variations pv ON pv.product_id = p.id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.id IN ($placeholders) AND p.status = 'active'
    GROUP BY p.id
");

$query->bind_param($types, ...$compare_ids);
$query->execute();
$result = $query->get_result();

$product_ids_for_attrs = [];
while ($row = $result->fetch_assoc()) {
    $image_path = $row['image_url'];
    if (empty($image_path) || $image_path == 'assets/no-image.png') {
        $image_path = '/mobileshop/assets/no-image.png';
    } elseif (strpos($image_path, 'assets/') === 0 || strpos($image_path, 'uploads/') === 0) {
        $image_path = '/mobileshop/' . $image_path;
    } elseif (!strpos($image_path, '/')) {
        $image_path = '/mobileshop/uploads/' . $image_path;
    } else {
        $image_path = '/mobileshop/' . $image_path;
    }
    $row['image_url'] = $image_path;
    $products[$row['id']] = $row;
    $product_ids_for_attrs[] = $row['id'];
}
$query->close();

// Оптимизированный запрос атрибутов
if (!empty($product_ids_for_attrs)) {
    $attr_placeholders = implode(',', array_fill(0, count($product_ids_for_attrs), '?'));
    $attr_types = str_repeat('i', count($product_ids_for_attrs));
    
    $attr_query = $db->prepare("
        SELECT 
            pa.product_id,
            a.id, a.name, a.slug, a.type, a.unit,
            pa.value_text, pa.value_number, pa.value_boolean
        FROM product_attributes pa
        JOIN attributes a ON a.id = pa.attribute_id
        WHERE pa.product_id IN ($attr_placeholders) AND a.is_visible_on_product = 1
        ORDER BY a.sort_order
    ");
    
    $attr_query->bind_param($attr_types, ...$product_ids_for_attrs);
    $attr_query->execute();
    $attr_result = $attr_query->get_result();
    
    while ($attr = $attr_result->fetch_assoc()) {
        $value = '';
        if ($attr['value_text']) {
            $value = $attr['value_text'];
        } elseif ($attr['value_number']) {
            $value = $attr['value_number'] . ($attr['unit'] ? ' ' . $attr['unit'] : '');
        } elseif ($attr['value_boolean']) {
            $value = $attr['value_boolean'] ? 'Да' : 'Нет';
        }
        
        $products[$attr['product_id']]['attributes'][$attr['slug']] = [
            'name' => $attr['name'],
            'value' => $value,
            'unit' => $attr['unit']
        ];
        $all_attributes[$attr['slug']] = $attr['name'];
    }
    $attr_query->close();
}
?>

<div class="container compare-container py-4">
    <h1 class="mb-4">
        <i class="fas fa-exchange-alt me-2"></i>Сравнение товаров
        <small class="text-muted fs-6">(<span id="compare-count-display"><?= count($products) ?></span> товаров)</small>
    </h1>
    
    <div class="row mb-4">
        <div class="col-12">
            <div class="btn-group">
                <a href="../index.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Назад в каталог
                </a>
                <button class="btn btn-outline-danger" id="clear-compare-btn" onclick="clearAllComparePage()">
                    <i class="fas fa-trash"></i> Очистить сравнение
                </button>
            </div>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-bordered compare-table" id="compare-table">
            <thead class="table-light">
                <tr>
                    <th style="width: 200px;">Характеристика</th>
                    <?php foreach ($products as $product): ?>
                        <th class="text-center product-col" data-product-id="<?= $product['id'] ?>" style="min-width: 250px;">
                            <div class="position-relative">
                                <a href="../product.php?id=<?= $product['id'] ?>" class="text-decoration-none">
                                    <img src="<?= htmlspecialchars($product['image_url']); ?>" 
                                         alt="<?= htmlspecialchars($product['name']); ?>"
                                         class="img-fluid mb-2" style="max-height: 150px; object-fit: contain;">
                                    <h5 class="mt-2"><?= htmlspecialchars($product['brand'] . ' ' . $product['name']); ?></h5>
                                    <p class="text-muted small"><?= htmlspecialchars($product['model']); ?></p>
                                </a>
                                <button class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 remove-from-compare-btn"
                                        data-product-id="<?= $product['id'] ?>"
                                        onclick="removeFromComparePage(<?= $product['id'] ?>)"
                                        title="Удалить из сравнения"
                                        aria-label="Удалить <?= htmlspecialchars($product['name']) ?> из сравнения">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr class="price-row">
                    <td class="fw-bold">Цена</td>
                    <?php foreach ($products as $product): ?>
                        <td class="text-center product-col" data-product-id="<?= $product['id'] ?>">
                            <?php 
                            $price = $product['min_price'] == $product['max_price'] 
                                ? formatPrice($product['min_price'])
                                : 'от ' . formatPrice($product['min_price']) . ' до ' . formatPrice($product['max_price']);
                            ?>
                            <span class="fs-5 fw-bold text-primary"><?= $price ?></span>
                        </td>
                    <?php endforeach; ?>
                </tr>
                
                <tr class="stock-row">
                    <td class="fw-bold">Наличие</td>
                    <?php foreach ($products as $product): ?>
                        <td class="text-center product-col" data-product-id="<?= $product['id'] ?>">
                            <?php if ($product['total_stock'] > 0): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle"></i> В наличии (<?= $product['total_stock'] ?> шт.)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger">
                                    <i class="fas fa-times-circle"></i> Нет в наличии
                                </span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                
                <tr class="rating-row">
                    <td class="fw-bold">Рейтинг</td>
                    <?php foreach ($products as $product): ?>
                        <td class="text-center product-col" data-product-id="<?= $product['id'] ?>">
                            <div class="rating-stars" role="img" aria-label="Рейтинг <?= number_format($product['rating'], 1) ?> из 5">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= round($product['rating']) ? '' : '-o' ?> text-warning"></i>
                                <?php endfor; ?>
                                <span class="text-muted ms-1">(<?= number_format($product['rating'], 1) ?>)</span>
                            </div>
                            <div class="small text-muted">Продано: <?= $product['sales_count'] ?> шт.</div>
                        </td>
                    <?php endforeach; ?>
                </tr>
                
                <tr class="category-row">
                    <td class="fw-bold">Категория</td>
                    <?php foreach ($products as $product): ?>
                        <td class="text-center product-col" data-product-id="<?= $product['id'] ?>">
                            <?= htmlspecialchars($product['category_name']) ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                
                <?php foreach ($all_attributes as $slug => $name): ?>
                <tr class="attr-row">
                    <td class="fw-bold"><?= htmlspecialchars($name) ?></td>
                    <?php foreach ($products as $product): ?>
                        <td class="text-center product-col" data-product-id="<?= $product['id'] ?>">
                            <?php 
                            $value = isset($product['attributes'][$slug]['value']) && !empty($product['attributes'][$slug]['value'])
                                ? htmlspecialchars($product['attributes'][$slug]['value'])
                                : '<span class="text-muted">—</span>';
                            echo $value;
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                
                <tr class="desc-row">
                    <td class="fw-bold">Описание</td>
                    <?php foreach ($products as $product): ?>
                        <td class="small product-col" data-product-id="<?= $product['id'] ?>">
                            <?= nl2br(htmlspecialchars(substr($product['description'] ?? '', 0, 200))) ?>
                            <?php if (strlen($product['description'] ?? '') > 200): ?>
                                <a href="../product.php?id=<?= $product['id'] ?>" class="small">Подробнее</a>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                
                <tr class="actions-row">
                    <td class="fw-bold">Действия</td>
                    <?php foreach ($products as $product): ?>
                        <td class="text-center product-col" data-product-id="<?= $product['id'] ?>">
                            <?php if ($product['total_stock'] > 0): ?>
                                <!-- ИСПРАВЛЕНО: Добавлен CSRF-токен и исправлен путь -->
                                <form method="POST" action="/mobileshop/pages/cart/cart.php" class="d-inline add-to-cart-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <input type="number" name="quantity" value="1" min="1" max="<?= $product['total_stock'] ?>" 
                                           class="form-control form-control-sm d-inline-block w-auto me-1" style="width: 60px;"
                                           aria-label="Количество">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-shopping-cart"></i> В корзину
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">Нет в наличии</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>
    
    <?php if (count($products) < 4): ?>
        <div class="alert alert-info mt-4" id="add-more-alert">
            <i class="fas fa-info-circle me-2"></i>
            Вы можете добавить еще <?= 4 - count($products) ?> товара для сравнения.
            <a href="../index.php" class="alert-link">Выбрать товары</a>
        </div>
    <?php endif; ?>
    
    <!-- Шаблон для пустого состояния (скрыт по умолчанию) -->
    <div class="alert alert-info text-center" id="empty-compare-message" style="display: none;">
        <i class="fas fa-exchange-alt fa-3x mb-3"></i>
        <h4>Нет товаров для сравнения</h4>
        <p>Выберите товары на странице каталога или в карточке товара, чтобы добавить их в сравнение.</p>
        <a href="../index.php" class="btn btn-primary mt-3">Перейти в каталог</a>
    </div>
</div>

<style>
.compare-table th,
.compare-table td {
    vertical-align: middle;
    padding: 15px;
}
.compare-table img {
    max-width: 100%;
    height: auto;
    max-height: 150px;
    object-fit: contain;
}
.rating-stars {
    white-space: nowrap;
}
.rating-stars i {
    font-size: 14px;
}
.product-col {
    transition: all 0.3s ease;
}
.product-col.removing {
    opacity: 0;
    transform: scale(0.9);
}
@media print {
    .btn-group,
    .alert-info,
    .position-absolute {
        display: none !important;
    }
    .compare-table th,
    .compare-table td {
        border: 1px solid #000;
    }
}
</style>

<!-- JavaScript для страницы сравнения -->
<script>
/**
 * Удаляет товар из сравнения на странице compare.php
 * @param {number} productId - ID товара для удаления
 */
function removeFromComparePage(productId) {
    if (!confirm('Удалить товар из сравнения?')) {
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append('remove', productId);
    
    fetch(window.BASE_URL + 'pages/update_compare.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData.toString()
    })
    .then(response => {
        if (!response.ok) throw new Error('HTTP error: ' + response.status);
        return response.json();
    })
    .then(data => {
        console.log('Server response:', data);
        
        if (data.success) {
            // Анимированное удаление колонки
            const cols = document.querySelectorAll('.product-col[data-product-id="' + productId + '"]');
            cols.forEach(col => {
                col.classList.add('removing');
            });
            
            setTimeout(() => {
                cols.forEach(col => col.remove());
                
                // ИСПРАВЛЕНО: Обновляем URL без перезагрузки страницы
                updateCompareUrl(data.compare_ids);
                
                // Проверяем, остались ли товары
                const remainingCols = document.querySelectorAll('th.product-col');
                if (remainingCols.length === 0) {
                    // Показываем сообщение "пусто"
                    document.getElementById('compare-table').style.display = 'none';
                    document.getElementById('clear-compare-btn').style.display = 'none';
                    const addMoreAlert = document.getElementById('add-more-alert');
                    if (addMoreAlert) addMoreAlert.style.display = 'none';
                    document.getElementById('empty-compare-message').style.display = 'block';
                    
                    // Убираем параметр ids из URL полностью
                    history.replaceState(null, null, window.BASE_URL + 'pages/compare.php');
                } else {
                    // Обновляем счетчик в заголовке
                    const remainingCount = remainingCols.length;
                    document.getElementById('compare-count-display').textContent = remainingCount;
                    
                    // Обновляем сообщение "добавить еще"
                    const addMoreAlert = document.getElementById('add-more-alert');
                    if (addMoreAlert) {
                        const canAdd = 4 - remainingCount;
                        if (canAdd > 0) {
                            addMoreAlert.innerHTML = '<i class="fas fa-info-circle me-2"></i>Вы можете добавить еще ' + canAdd + ' товара для сравнения. <a href="../index.php" class="alert-link">Выбрать товары</a>';
                        } else {
                            addMoreAlert.style.display = 'none';
                        }
                    }
                }
            }, 300);
            
            // Обновляем localStorage и глобальный счетчик
            if (data.compare_ids) {
                localStorage.setItem('compareIds', JSON.stringify(data.compare_ids));
                const countBadge = document.getElementById('compare-count');
                if (countBadge) {
                    countBadge.textContent = data.compare_ids.length;
                }
            }
            
            showToast('Товар удален из сравнения', 'success');
        } else {
            showToast(data.message || 'Ошибка при удалении', 'error');
        }
    })
    .catch(error => {
        console.error('Error removing from compare:', error);
        showToast('Ошибка соединения с сервером', 'error');
    });
}

/**
 * Очищает весь список сравнения на странице compare.php
 */
function clearAllComparePage() {
    if (!confirm('Очистить весь список сравнения?')) {
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append('clear_all', '1');
    
    fetch(window.BASE_URL + 'pages/update_compare.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData.toString()
    })
    .then(response => {
        if (!response.ok) throw new Error('HTTP error: ' + response.status);
        return response.json();
    })
    .then(data => {
        console.log('Server response:', data);
        
        if (data.success) {
            // Показываем сообщение "пусто"
            document.getElementById('compare-table').style.display = 'none';
            document.getElementById('clear-compare-btn').style.display = 'none';
            const addMoreAlert = document.getElementById('add-more-alert');
            if (addMoreAlert) addMoreAlert.style.display = 'none';
            document.getElementById('empty-compare-message').style.display = 'block';
            
            // ИСПРАВЛЕНО: Убираем параметр ids из URL полностью
            history.replaceState(null, null, window.BASE_URL + 'pages/compare.php');
            
            // Очищаем localStorage
            localStorage.removeItem('compareIds');
            const countBadge = document.getElementById('compare-count');
            if (countBadge) countBadge.textContent = '0';
            
            showToast('Список сравнения очищен', 'success');
        } else {
            showToast(data.message || 'Ошибка при очистке', 'error');
        }
    })
    .catch(error => {
        console.error('Error clearing compare:', error);
        showToast('Ошибка соединения с сервером', 'error');
    });
}

/**
 * ИСПРАВЛЕНО: Обновляет URL страницы с актуальными ID сравнения
 * @param {Array} compareIds - массив ID товаров в сравнении
 */
function updateCompareUrl(compareIds) {
    if (!compareIds || compareIds.length === 0) {
        // Если товаров нет — убираем параметр ids
        history.replaceState(null, null, window.BASE_URL + 'pages/compare.php');
    } else {
        // Обновляем параметр ids в URL
        const newUrl = window.BASE_URL + 'pages/compare.php?ids=' + compareIds.join(',');
        history.replaceState({compareIds: compareIds}, null, newUrl);
    }
}

// Инициализация AJAX-форм добавления в корзину
document.addEventListener('DOMContentLoaded', function() {
    // Обрабатываем формы добавления в корзину через AJAX
    document.querySelectorAll('.add-to-cart-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            // Блокируем кнопку
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (response.redirected) {
                    // Если редирект на корзину — считаем успехом
                    showToast('Товар добавлен в корзину', 'success');
                    // Обновляем счетчик корзины
                    updateCartCount();
                    return null;
                }
                return response.text();
            })
            .then(data => {
                if (data !== null) {
                    // Проверяем, не содержит ли ответ ошибку CSRF
                    if (data.includes('CSRF') || data.includes('csrf')) {
                        showToast('Ошибка безопасности. Обновите страницу.', 'error');
                    } else {
                        showToast('Товар добавлен в корзину', 'success');
                        updateCartCount();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Ошибка при добавлении в корзину', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    });
});

// Функция обновления счетчика корзины
function updateCartCount() {
    fetch(window.BASE_URL + 'pages/cart/Api_Cart.php?action=count', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.count !== undefined) {
            const cartCount = document.getElementById('cart-count');
            if (cartCount) {
                cartCount.textContent = data.count;
            }
        }
    })
    .catch(err => console.error('Error updating cart count:', err));
}
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>