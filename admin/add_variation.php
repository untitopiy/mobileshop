<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Проверка ID товара
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($product_id <= 0) {
    header("Location: manage_products.php");
    exit;
}

// Получаем информацию о товаре
$product_query = $db->prepare("
    SELECT p.id, p.name, p.brand, p.model, p.type, p.category_id,
           p.price as base_price, p.quantity as base_quantity, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.id = ?
");
$product_query->bind_param('i', $product_id);
$product_query->execute();
$product = $product_query->get_result()->fetch_assoc();

if (!$product) {
    header("Location: manage_products.php");
    exit;
}

// Получаем все атрибуты для категории
$all_attributes = $db->query("
    SELECT a.id, a.name, a.slug, a.type, a.unit, a.is_visible_on_product,
           ca.category_id, ca.is_required, ca.sort_order
    FROM attributes a
    INNER JOIN category_attributes ca ON ca.attribute_id = a.id
    WHERE ca.category_id = " . $product['category_id'] . "
    ORDER BY ca.sort_order
");

$category_attributes = [];
while ($attr = $all_attributes->fetch_assoc()) {
    $category_attributes[] = $attr;
}

// Получаем существующие SKU
$existing_skus = [];
$sku_query = $db->prepare("SELECT sku FROM product_variations WHERE product_id = ?");
$sku_query->bind_param('i', $product_id);
$sku_query->execute();
$sku_result = $sku_query->get_result();
while ($row = $sku_result->fetch_assoc()) {
    $existing_skus[] = $row['sku'];
}
$sku_query->close();

// Обработка формы
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_variation'])) {
    $sku = trim($_POST['sku']);
    $price = (float)$_POST['price'];
    $old_price = !empty($_POST['old_price']) ? (float)$_POST['old_price'] : 0;
    $quantity = (int)$_POST['quantity'];
    
    // Получаем значения атрибутов
    $attribute_values = [];
    if (isset($_POST['attributes']) && is_array($_POST['attributes'])) {
        foreach ($_POST['attributes'] as $attr_id => $values) {
            $value_text = isset($values['text']) ? trim($values['text']) : '';
            $value_number = isset($values['number']) && $values['number'] !== '' ? (float)$values['number'] : 0;
            $value_boolean = isset($values['boolean']) ? 1 : 0;
            
            // Обработка специальных полей
            if (isset($values['ssd'])) $value_text = $values['ssd'];
            if (isset($values['hdd'])) $value_text = $values['hdd'];
            if (isset($values['integrated'])) $value_text = $values['integrated'];
            if (isset($values['discrete'])) $value_text = $values['discrete'];
            
            if (!empty($value_text) || $value_number != 0 || $value_boolean) {
                $attribute_values[$attr_id] = [
                    'text' => $value_text,
                    'number' => $value_number,
                    'boolean' => $value_boolean
                ];
            }
        }
    }
    
    // Валидация
    if (empty($sku)) $errors[] = "Артикул обязателен";
    if ($price <= 0) $errors[] = "Цена должна быть больше 0";
    if ($quantity < 0) $errors[] = "Количество не может быть отрицательным";
    
    // Проверка уникальности SKU
    if (in_array($sku, $existing_skus)) {
        $errors[] = "Артикул '$sku' уже существует для этого товара";
    }
    
    // Проверка обязательных атрибутов
    foreach ($category_attributes as $attr) {
        if ($attr['is_required']) {
            $has_value = false;
            
            // Специальная обработка для видеокарты
            if ($attr['slug'] === 'nb_gpu') {
                // Проверяем, есть ли значение из nb_gpu_type и соответствующих полей
                if (isset($_POST['attributes'])) {
                    foreach ($_POST['attributes'] as $attr_id => $values) {
                        // Проверяем поле nb_gpu_type
                        if (isset($values['text']) && !empty($values['text'])) {
                            $has_value = true;
                            break;
                        }
                        // Проверяем поля integrated и discrete
                        if ((isset($values['integrated']) && !empty($values['integrated'])) ||
                            (isset($values['discrete']) && !empty($values['discrete']))) {
                            $has_value = true;
                            break;
                        }
                    }
                }
            } 
            // Обычная обработка для остальных атрибутов
            else {
                if (isset($attribute_values[$attr['id']])) {
                    $val = $attribute_values[$attr['id']];
                    if (!empty($val['text']) || $val['number'] != 0 || $val['boolean']) {
                        $has_value = true;
                    }
                }
            }
            
            if (!$has_value) {
                $errors[] = "Поле '{$attr['name']}' обязательно для заполнения";
            }
        }
    }
    
    // Загрузка изображения
    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 2 * 1024 * 1024;
        
        if (!in_array($_FILES['image']['type'], $allowed)) {
            $errors[] = "Неверный формат изображения";
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = "Размер изображения не должен превышать 2 МБ";
        } else {
            $upload_dir = __DIR__ . '/../uploads/variations/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $new_name = 'var_' . $product_id . '_' . uniqid() . '.' . $ext;
            $upload_path = $upload_dir . $new_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image_url = 'uploads/variations/' . $new_name;
            } else {
                $errors[] = "Ошибка при загрузке изображения";
            }
        }
    }
    
    if (empty($errors)) {
        // Формируем хеш атрибутов
        $attributes_hash = md5(json_encode($attribute_values) . $product_id . $sku);
        
        $stmt = $db->prepare("
            INSERT INTO product_variations (
                product_id, sku, price, old_price, quantity, image_url, attributes_hash, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param("isddiss", $product_id, $sku, $price, $old_price, $quantity, $image_url, $attributes_hash);
        
        if ($stmt->execute()) {
            $variation_id = $stmt->insert_id;
            
            // Сохраняем значения атрибутов
            if (!empty($attribute_values)) {
                $attr_stmt = $db->prepare("
                    INSERT INTO product_attributes (product_id, attribute_id, value_text, value_number, value_boolean, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                foreach ($attribute_values as $attr_id => $values) {
                    $p_id = $product_id;
                    $a_id = $attr_id;
                    $v_text = $values['text'];
                    $v_num = $values['number'];
                    $v_bool = $values['boolean'];
                    
                    $params = array(&$p_id, &$a_id, &$v_text, &$v_num, &$v_bool);
                    $types = "iissi";
                    array_unshift($params, $types);
                    
                    call_user_func_array(array($attr_stmt, 'bind_param'), $params);
                    $attr_stmt->execute();
                }
                $attr_stmt->close();
            }
            
            $_SESSION['success'] = "Вариант товара успешно добавлен";
            header("Location: product_variations.php?id=$product_id");
            exit;
        } else {
            $errors[] = "Ошибка при добавлении варианта: " . $db->error;
        }
        $stmt->close();
    }
}

// Подключаем header ПОСЛЕ обработки POST
require_once __DIR__ . '/../inc/header.php';

$category_attributes_json = json_encode($category_attributes);
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Добавление варианта товара</h1>
        <div>
            <a href="product_variations.php?id=<?= $product_id ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Назад к вариантам
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Информация о варианте</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Товар:</strong> <?= htmlspecialchars($product['brand'] . ' ' . $product['name'] . ' ' . $product['model']) ?>
                        <br>
                        <strong>Категория:</strong> <?= htmlspecialchars($product['category_name']) ?>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Артикул (SKU) *</label>
                                <input type="text" name="sku" class="form-control" required
                                       value="<?= htmlspecialchars($_POST['sku'] ?? '') ?>"
                                       placeholder="Например: IP15-BLK-128">
                                <small class="text-muted">Уникальный артикул для этого варианта</small>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Цена *</label>
                                <div class="input-group">
                                    <input type="number" name="price" step="0.01" class="form-control" required
                                           value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                                    <span class="input-group-text">₽</span>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Старая цена</label>
                                <div class="input-group">
                                    <input type="number" name="old_price" step="0.01" class="form-control"
                                           value="<?= htmlspecialchars($_POST['old_price'] ?? '') ?>">
                                    <span class="input-group-text">₽</span>
                                </div>
                                <small class="text-muted">Для отображения скидки</small>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Количество на складе *</label>
                                <input type="number" name="quantity" class="form-control" required
                                       value="<?= htmlspecialchars($_POST['quantity'] ?? '0') ?>"
                                       min="0">
                            </div>
                            
                            <div class="col-md-8">
                                <label class="form-label">Изображение для варианта</label>
                                <input type="file" name="image" class="form-control" accept="image/*">
                                <small class="text-muted">Необязательно. Если не указано, будет использовано основное изображение товара</small>
                            </div>
                        </div>
                        
                        <?php if (!empty($category_attributes)): ?>
                        <hr class="my-4">
                        <h5 class="mb-3">Характеристики товара</h5>
                        <div class="row" id="attributes-container">
                            <?php 
                            $hiddenSlugs = ['screen_size', 'weight', 'weight_notebook', 'nb_ssd_size', 'nb_hdd_size', 'nb_gpu'];
                            
                            foreach ($category_attributes as $attr): 
                                if (in_array($attr['slug'], $hiddenSlugs)) continue;
                                
                                $req = $attr['is_required'] ? 'required' : '';
                                $attrName = htmlspecialchars($attr['name']);
                                $attrUnit = $attr['unit'] ? htmlspecialchars($attr['unit']) : '';
                                $attrSlug = $attr['slug'];
                                $attrId = $attr['id'];
                                
                                echo '<div class="col-md-6 mb-3">';
                                echo '<label class="form-label">' . $attrName;
                                if ($attr['is_required']) {
                                    echo ' <span class="text-danger">*</span>';
                                }
                                echo '</label>';
                                
                                if ($attr['type'] === 'number') {
                                    $step = (strpos($attr['slug'], 'cores') !== false) ? '1' : (($attr['unit'] === 'ГБ') ? '1' : '0.01');
                                    echo '<div class="input-group">';
                                    echo '<input type="number" name="attributes[' . $attrId . '][number]" step="' . $step . '" class="form-control" ' . $req . '>';
                                    if ($attrUnit) {
                                        echo '<span class="input-group-text">' . $attrUnit . '</span>';
                                    }
                                    echo '</div>';
                                } 
                                else if ($attr['type'] === 'select') {
                                    // Получаем значения для select
                                    $values_result = $db->query("SELECT value FROM attribute_values WHERE attribute_id = {$attrId} ORDER BY sort_order");
                                    echo '<select name="attributes[' . $attrId . '][text]" class="form-select" ' . $req . ' data-attr-id="' . $attrId . '" data-attr-slug="' . $attrSlug . '">';
                                    echo '<option value="">Выберите</option>';
                                    while ($val = $values_result->fetch_assoc()) {
                                        $valValue = htmlspecialchars($val['value']);
                                        $selected = (isset($_POST['attributes'][$attrId]['text']) && $_POST['attributes'][$attrId]['text'] == $val['value']) ? 'selected' : '';
                                        echo '<option value="' . $valValue . '" ' . $selected . '>' . $valValue . '</option>';
                                    }
                                    echo '</select>';
                                } 
                                else if ($attr['type'] === 'boolean') {
                                    $checked = (isset($_POST['attributes'][$attrId]['boolean']) && $_POST['attributes'][$attrId]['boolean'] == 1) ? 'checked' : '';
                                    echo '<div class="form-check">';
                                    echo '<input type="checkbox" name="attributes[' . $attrId . '][boolean]" class="form-check-input" value="1" ' . $checked . '>';
                                    echo '<label class="form-check-label">Да</label>';
                                    echo '</div>';
                                } 
                                else {
                                    $value = isset($_POST['attributes'][$attrId]['text']) ? htmlspecialchars($_POST['attributes'][$attrId]['text']) : '';
                                    echo '<input type="text" name="attributes[' . $attrId . '][text]" class="form-control" ' . $req . ' value="' . $value . '">';
                                }
                                
                                echo '</div>';
                            endforeach; 
                            ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" name="add_variation" class="btn btn-primary">
                                <i class="fas fa-save"></i> Добавить вариант
                            </button>
                            <a href="product_variations.php?id=<?= $product_id ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Отмена
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Что такое варианты?</h5>
                </div>
                <div class="card-body">
                    <p>Варианты товара позволяют:</p>
                    <ul class="mb-0">
                        <li><i class="fas fa-tag text-primary me-2"></i>Указывать разные цены для разных цветов/размеров</li>
                        <li><i class="fas fa-boxes text-success me-2"></i>Отслеживать остатки по каждому варианту отдельно</li>
                        <li><i class="fas fa-barcode text-info me-2"></i>Добавлять уникальные артикулы</li>
                        <li><i class="fas fa-image text-warning me-2"></i>Загружать отдельные изображения для вариантов</li>
                    </ul>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Существующие варианты</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($existing_skus)): ?>
                        <p class="text-muted mb-0">У этого товара пока нет вариантов</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($existing_skus as $sku): ?>
                                <li><code><?= htmlspecialchars($sku) ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Совет</h5>
                </div>
                <div class="card-body">
                    <p class="small mb-0">
                        Заполните характеристики для этого варианта, чтобы он отображался корректно 
                        в каталоге и фильтрах. Обязательные поля отмечены <span class="text-danger">*</span>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Функция для добавления полей объема SSD/HDD
function addStorageFields(select, attrId) {
    const container = select.closest('.row');
    if (!container) return;
    
    // Удаляем старые поля
    document.querySelectorAll('[data-dependent="ssd"],[data-dependent="hdd"]').forEach(el => el.remove());
    
    const val = select.value;
    if (val === 'SSD') {
        container.insertAdjacentHTML('beforeend', `<div class="col-md-6 mb-3" data-dependent="ssd"><label class="form-label">Объем SSD <span class="text-danger">*</span></label><div class="input-group"><input type="number" name="attributes[${attrId}][ssd]" step="1" class="form-control" required><span class="input-group-text">ГБ</span></div></div>`);
    } else if (val === 'HDD') {
        container.insertAdjacentHTML('beforeend', `<div class="col-md-6 mb-3" data-dependent="hdd"><label class="form-label">Объем HDD <span class="text-danger">*</span></label><div class="input-group"><input type="number" name="attributes[${attrId}][hdd]" step="1" class="form-control" required><span class="input-group-text">ГБ</span></div></div>`);
    } else if (val === 'SSD+HDD') {
        container.insertAdjacentHTML('beforeend', `
            <div class="col-md-6 mb-3" data-dependent="ssd"><label class="form-label">Объем SSD <span class="text-danger">*</span></label><div class="input-group"><input type="number" name="attributes[${attrId}][ssd]" step="1" class="form-control" required><span class="input-group-text">ГБ</span></div></div>
            <div class="col-md-6 mb-3" data-dependent="hdd"><label class="form-label">Объем HDD <span class="text-danger">*</span></label><div class="input-group"><input type="number" name="attributes[${attrId}][hdd]" step="1" class="form-control" required><span class="input-group-text">ГБ</span></div></div>
        `);
    }
}

// Функция для добавления полей видеокарты
function addGpuField(select, attrId) {
    const container = select.closest('.row');
    if (!container) return;
    
    // Удаляем старые поля
    document.querySelectorAll('[data-dependent="gpu-integrated"],[data-dependent="gpu-discrete"]').forEach(el => el.remove());
    
    const val = select.value;
    if (!val) return;
    
    if (val === 'Встроенная') {
        container.insertAdjacentHTML('beforeend', `<div class="col-md-6 mb-3" data-dependent="gpu-integrated"><label class="form-label">Встроенная видеокарта <span class="text-danger">*</span></label><input type="text" name="attributes[${attrId}][integrated]" class="form-control" placeholder="Intel UHD Graphics" required></div>`);
    } else if (val === 'Дискретная') {
        container.insertAdjacentHTML('beforeend', `<div class="col-md-6 mb-3" data-dependent="gpu-discrete"><label class="form-label">Дискретная видеокарта <span class="text-danger">*</span></label><input type="text" name="attributes[${attrId}][discrete]" class="form-control" placeholder="NVIDIA GeForce RTX 3060" required></div>`);
    } else if (val === 'Встроенная+Дискретная') {
        container.insertAdjacentHTML('beforeend', `
            <div class="col-md-6 mb-3" data-dependent="gpu-integrated"><label class="form-label">Встроенная видеокарта <span class="text-danger">*</span></label><input type="text" name="attributes[${attrId}][integrated]" class="form-control" placeholder="Intel Iris Xe" required></div>
            <div class="col-md-6 mb-3" data-dependent="gpu-discrete"><label class="form-label">Дискретная видеокарта <span class="text-danger">*</span></label><input type="text" name="attributes[${attrId}][discrete]" class="form-control" placeholder="NVIDIA GeForce RTX 3060" required></div>
        `);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Находим select для типа накопителя (nb_storage_type)
    const storageSelect = document.querySelector('select[data-attr-slug="nb_storage_type"]');
    if (storageSelect) {
        const attrId = storageSelect.getAttribute('data-attr-id');
        storageSelect.addEventListener('change', function() {
            addStorageFields(storageSelect, attrId);
        });
        // Если значение уже выбрано, добавляем поля
        if (storageSelect.value) {
            addStorageFields(storageSelect, attrId);
        }
    }
    
    // Находим select для типа видеокарты (nb_gpu_type)
    const gpuSelect = document.querySelector('select[data-attr-slug="nb_gpu_type"]');
    if (gpuSelect) {
        const attrId = gpuSelect.getAttribute('data-attr-id');
        gpuSelect.addEventListener('change', function() {
            addGpuField(gpuSelect, attrId);
        });
        // Если значение уже выбрано, добавляем поля
        if (gpuSelect.value) {
            addGpuField(gpuSelect, attrId);
        }
    }
});
</script>

<style>
#attributes-container .row {
    margin-left: -0.5rem;
    margin-right: -0.5rem;
}
#attributes-container .col-md-6 {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}
</style>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>