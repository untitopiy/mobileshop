<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Проверка ID товара
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    header("Location: manage_products.php");
    exit;
}

// Получаем данные товара
$product_query = $db->prepare("
    SELECT p.*, c.name as category_name, s.shop_name as seller_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN sellers s ON s.id = p.seller_id
    WHERE p.id = ?
");
$product_query->bind_param('i', $product_id);
$product_query->execute();
$product = $product_query->get_result()->fetch_assoc();

if (!$product) {
    header("Location: manage_products.php");
    exit;
}

// Получаем списки для выпадающих меню
$categories = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
$sellers = $db->query("SELECT id, shop_name FROM sellers WHERE is_active = 1 ORDER BY shop_name");

// Получаем атрибуты для текущей категории
$category_attributes = $db->prepare("
    SELECT a.id, a.name, a.slug, a.type, a.unit, ca.is_required
    FROM attributes a
    JOIN category_attributes ca ON ca.attribute_id = a.id
    WHERE ca.category_id = ?
    ORDER BY ca.sort_order
");
$category_attributes->bind_param('i', $product['category_id']);
$category_attributes->execute();
$attributes_list = $category_attributes->get_result();

// Получаем текущие значения атрибутов товара
$product_attributes = [];
$attr_query = $db->prepare("
    SELECT attribute_id, value_text, value_number, value_boolean
    FROM product_attributes
    WHERE product_id = ?
");
$attr_query->bind_param('i', $product_id);
$attr_query->execute();
$attr_result = $attr_query->get_result();
while ($attr = $attr_result->fetch_assoc()) {
    $product_attributes[$attr['attribute_id']] = $attr;
}

// Получаем все категории с их атрибутами для JavaScript
$all_categories_attrs = [];
$cat_attrs_query = $db->query("
    SELECT ca.category_id, a.id, a.name, a.slug, a.type, a.unit, ca.is_required, ca.sort_order
    FROM category_attributes ca
    JOIN attributes a ON a.id = ca.attribute_id
    ORDER BY ca.category_id, ca.sort_order
");
while ($row = $cat_attrs_query->fetch_assoc()) {
    if (!isset($all_categories_attrs[$row['category_id']])) {
        $all_categories_attrs[$row['category_id']] = [];
    }
    $all_categories_attrs[$row['category_id']][] = $row;
}

$all_categories_attrs_json = json_encode($all_categories_attrs);

// Обработка формы
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $name = trim($_POST['name']);
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $sku = trim($_POST['sku']);
    $barcode = trim($_POST['barcode']);
    $category_id = (int)$_POST['category_id'];
    $seller_id = !empty($_POST['seller_id']) ? (int)$_POST['seller_id'] : null;
    $type = $_POST['type'];
    $status = $_POST['status'];
    $short_description = trim($_POST['short_description']);
    $description = trim($_POST['description']);
    $base_price = (float)$_POST['base_price'];
    $quantity = (int)$_POST['quantity'];
    $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
    
    // Валидация
    if (empty($name)) $errors[] = "Название товара обязательно";
    if (empty($brand)) $errors[] = "Бренд обязателен";
    if (empty($model)) $errors[] = "Модель обязательна";
    if ($category_id <= 0) $errors[] = "Выберите категорию";
    if ($base_price <= 0) $errors[] = "Цена должна быть больше 0";
    
    if (empty($errors)) {
        $db->begin_transaction();
        
        try {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
            $slug = trim($slug, '-') . '-' . $product_id;
            
            $stmt = $db->prepare("
                UPDATE products SET
                    seller_id = ?, category_id = ?, name = ?, slug = ?, sku = ?, barcode = ?,
                    short_description = ?, description = ?, brand = ?, model = ?, price = ?,
                    quantity = ?, type = ?, status = ?, weight = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "iissssssssdisssdi",
                $seller_id, $category_id, $name, $slug, $sku, $barcode,
                $short_description, $description, $brand, $model, $base_price,
                $quantity, $type, $status, $weight, $product_id
            );
            $stmt->execute();
            $stmt->close();
            
            // Обновление атрибутов
            $db->query("DELETE FROM product_attributes WHERE product_id = $product_id");
            
            if (isset($_POST['attributes']) && is_array($_POST['attributes'])) {
                $attr_stmt = $db->prepare("
                    INSERT INTO product_attributes (product_id, attribute_id, value_text, value_number, value_boolean)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($_POST['attributes'] as $attr_id => $values) {
                    $attr_id = (int)$attr_id;
                    $value_text = isset($values['text']) ? trim($values['text']) : '';
                    $value_number = isset($values['number']) && $values['number'] !== '' ? (float)$values['number'] : null;
                    $value_boolean = isset($values['boolean']) ? 1 : 0;
                    
                    if (!empty($value_text) || $value_number !== null || $value_boolean) {
                        $attr_stmt->bind_param("iissi", $product_id, $attr_id, $value_text, $value_number, $value_boolean);
                        $attr_stmt->execute();
                    }
                }
                $attr_stmt->close();
            }
            
            $db->commit();
            $_SESSION['success'] = "Товар успешно обновлен";
            header("Location: edit_product.php?id=$product_id&updated=1");
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = "Ошибка при обновлении товара: " . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Редактирование товара #<?= $product_id ?></h1>
        <div>
            <a href="product_variations.php?id=<?= $product_id ?>" class="btn btn-info">
                <i class="fas fa-tags"></i> Управление вариантами
            </a>
            <a href="manage_products.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Назад
            </a>
        </div>
    </div>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Товар успешно обновлен
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="productForm">
        <div class="row">
            <div class="col-md-8">
                <!-- Основная информация -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Основная информация</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Название товара *</label>
                                <input type="text" name="name" class="form-control" 
                                       value="<?= htmlspecialchars($product['name']) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Бренд *</label>
                                <input type="text" name="brand" class="form-control" 
                                       value="<?= htmlspecialchars($product['brand']) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Модель *</label>
                                <input type="text" name="model" class="form-control" 
                                       value="<?= htmlspecialchars($product['model']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Артикул (SKU)</label>
                                <input type="text" name="sku" class="form-control" 
                                       value="<?= htmlspecialchars($product['sku'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Штрих-код</label>
                                <input type="text" name="barcode" class="form-control" 
                                       value="<?= htmlspecialchars($product['barcode'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Вес</label>
                                <div class="input-group">
                                    <input type="number" name="weight" step="0.01" class="form-control" 
                                           value="<?= htmlspecialchars($product['weight'] ?? '') ?>">
                                    <span class="input-group-text">кг</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Описание -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Описание</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Краткое описание</label>
                            <textarea name="short_description" class="form-control" rows="2"><?= htmlspecialchars($product['short_description'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Полное описание</label>
                            <textarea name="description" class="form-control" rows="5"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Динамические характеристики -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Характеристики</h5>
                    </div>
                    <div class="card-body" id="attributes-container">
                        <!-- Здесь будут динамически загружаться характеристики -->
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Категория и продавец -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Категория и продавец</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Категория *</label>
                            <select name="category_id" id="category_id" class="form-select" required>
                                <option value="">Выберите категорию</option>
                                <?php 
                                $categories->data_seek(0);
                                while ($cat = $categories->fetch_assoc()): 
                                ?>
                                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $product['category_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Продавец</label>
                            <select name="seller_id" class="form-select">
                                <option value="">— Администратор —</option>
                                <?php 
                                $sellers->data_seek(0);
                                while ($seller = $sellers->fetch_assoc()): 
                                ?>
                                    <option value="<?= $seller['id'] ?>" <?= $seller['id'] == $product['seller_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($seller['shop_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Настройки -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Настройки</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Тип товара</label>
                            <select name="type" class="form-select">
                                <option value="simple" <?= $product['type'] == 'simple' ? 'selected' : '' ?>>Простой товар</option>
                                <option value="variable" <?= $product['type'] == 'variable' ? 'selected' : '' ?>>С вариантами</option>
                                <option value="digital" <?= $product['type'] == 'digital' ? 'selected' : '' ?>>Цифровой товар</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Статус</label>
                            <select name="status" class="form-select">
                                <option value="draft" <?= $product['status'] == 'draft' ? 'selected' : '' ?>>Черновик</option>
                                <option value="active" <?= $product['status'] == 'active' ? 'selected' : '' ?>>Активный</option>
                                <option value="inactive" <?= $product['status'] == 'inactive' ? 'selected' : '' ?>>Неактивный</option>
                                <option value="pending" <?= $product['status'] == 'pending' ? 'selected' : '' ?>>На модерации</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Базовая цена *</label>
                            <input type="number" name="base_price" step="0.01" class="form-control" 
                                   value="<?= $product['price'] ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Количество *</label>
                            <input type="number" name="quantity" class="form-control" 
                                   value="<?= $product['quantity'] ?>" min="0">
                        </div>
                    </div>
                </div>

                <button type="submit" name="update_product" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i> Сохранить изменения
                </button>
            </div>
        </div>
    </form>
</div>

<script>
// Данные атрибутов по категориям
const allCategoriesAttributes = <?= $all_categories_attrs_json ?>;
const currentProductAttributes = <?= json_encode($product_attributes) ?>;

function renderAttributeField(attr, currentValue = null) {
    let html = '<div class="col-md-6 mb-3">';
    html += `<label class="form-label">${escapeHtml(attr.name)}`;
    if (attr.is_required) html += ' <span class="text-danger">*</span>';
    html += `</label>`;
    
    let valueText = '';
    let valueNumber = '';
    let valueBoolean = false;
    
    if (currentValue) {
        valueText = currentValue.value_text || '';
        valueNumber = currentValue.value_number || '';
        valueBoolean = currentValue.value_boolean || false;
    }
    
    switch (attr.type) {
        case 'text':
            html += `<input type="text" name="attributes[${attr.id}][text]" class="form-control" value="${escapeHtml(valueText)}" ${attr.is_required ? 'required' : ''}>`;
            break;
        case 'number':
            html += `<div class="input-group">`;
            html += `<input type="number" name="attributes[${attr.id}][number]" step="${attr.unit === 'ГБ' ? '1' : '0.01'}" class="form-control" value="${valueNumber}" ${attr.is_required ? 'required' : ''}>`;
            if (attr.unit) html += `<span class="input-group-text">${escapeHtml(attr.unit)}</span>`;
            html += `</div>`;
            break;
        case 'boolean':
            html += `<div class="form-check">`;
            html += `<input type="checkbox" name="attributes[${attr.id}][boolean]" class="form-check-input" value="1" ${valueBoolean ? 'checked' : ''}>`;
            html += `<label class="form-check-label">Да</label>`;
            html += `</div>`;
            break;
        default:
            html += `<input type="text" name="attributes[${attr.id}][text]" class="form-control" value="${escapeHtml(valueText)}" ${attr.is_required ? 'required' : ''}>`;
    }
    
    html += '</div>';
    return html;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function loadAttributes(categoryId) {
    const container = document.getElementById('attributes-container');
    
    if (!categoryId || !allCategoriesAttributes[categoryId]) {
        container.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> Выберите категорию для отображения характеристик</div>';
        return;
    }
    
    const attributes = allCategoriesAttributes[categoryId];
    if (!attributes || attributes.length === 0) {
        container.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Для выбранной категории нет характеристик</div>';
        return;
    }
    
    // Сортируем атрибуты по sort_order
    attributes.sort((a, b) => a.sort_order - b.sort_order);
    
    let html = '<div class="row">';
    attributes.forEach(attr => {
        const currentValue = currentProductAttributes[attr.id];
        html += renderAttributeField(attr, currentValue);
    });
    html += '</div>';
    
    container.innerHTML = html;
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    const categorySelect = document.getElementById('category_id');
    const currentCategoryId = <?= $product['category_id'] ?>;
    
    if (currentCategoryId) {
        loadAttributes(currentCategoryId);
    }
    
    categorySelect.addEventListener('change', function() {
        loadAttributes(parseInt(this.value));
    });
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>