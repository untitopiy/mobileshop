<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Проверка авторизации
if (!isset($_SESSION['id'])) {
    header('Location: ../pages/auth.php');
    exit;
}

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Проверка: является ли пользователь продавцом
$user_id = $_SESSION['id'];
$seller_check = $db->prepare("SELECT id, shop_name, is_active FROM sellers WHERE user_id = ?");
$seller_check->bind_param('i', $user_id);
$seller_check->execute();
$seller_res = $seller_check->get_result();
$current_seller = $seller_res->fetch_assoc();

if (!$current_seller) {
    die("Доступ запрещен. Вы не зарегистрированы как продавец.");
}

if ($current_seller['is_active'] != 1) {
    die("Ваш магазин не активен. Обратитесь к администратору.");
}

$seller_id = $current_seller['id'];
$seller_name = $current_seller['shop_name'];

// Получаем данные категорий
$categories_data = [];
$cats_result = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
while ($cat = $cats_result->fetch_assoc()) {
    $categories_data[] = $cat;
}

// Получаем все атрибуты
$all_attributes = $db->query("
    SELECT a.id, a.name, a.slug, a.type, a.unit, a.is_visible_on_product,
           ca.category_id, ca.is_required, ca.sort_order
    FROM attributes a
    INNER JOIN category_attributes ca ON ca.attribute_id = a.id
    WHERE a.slug LIKE '%_%' 
    ORDER BY ca.category_id, ca.sort_order
");

$attributes_by_category = [];
while ($attr = $all_attributes->fetch_assoc()) {
    $cat_id = $attr['category_id'];
    if (!isset($attributes_by_category[$cat_id])) {
        $attributes_by_category[$cat_id] = [];
    }
    $attributes_by_category[$cat_id][] = $attr;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $sku = trim($_POST['sku']);
    $barcode = trim($_POST['barcode']);
    $category_id = (int)$_POST['category_id'];
    $type = $_POST['type'];
    $status = $_POST['status'];
    $short_description = trim($_POST['short_description']);
    $description = trim($_POST['description']);
    $base_price = (float)$_POST['base_price'];
    $quantity = (int)$_POST['quantity'];
    $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
    
    if (empty($name)) $errors[] = "Название товара обязательно";
    if (empty($brand)) $errors[] = "Бренд обязателен";
    if (empty($model)) $errors[] = "Модель обязательна";
    if ($category_id <= 0) $errors[] = "Выберите категорию";
    if ($base_price <= 0) $errors[] = "Цена должна быть больше 0";
    
    if (empty($errors)) {
        $db->begin_transaction();
        
        try {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
            $slug = trim($slug, '-') . '-' . uniqid();
            
            $stmt = $db->prepare("
                INSERT INTO products (
                    seller_id, category_id, name, slug, sku, barcode,
                    short_description, description, brand, model, price, quantity,
                    type, status, weight, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param(
                "iissssssssdissd",
                $seller_id,
                $category_id,
                $name,
                $slug,
                $sku,
                $barcode,
                $short_description,
                $description,
                $brand,
                $model,
                $base_price,
                $quantity,
                $type,
                $status,
                $weight
            );
            $stmt->execute();
            $product_id = $stmt->insert_id;
            $stmt->close();
            
            // Сохраняем атрибуты
            if (isset($_POST['attributes']) && is_array($_POST['attributes'])) {
                $attr_stmt = $db->prepare("
                    INSERT INTO product_attributes (product_id, attribute_id, value_text, value_number, value_boolean)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($_POST['attributes'] as $attr_id => $values) {
                    $attr_id = (int)$attr_id;
                    $value_text = isset($values['text']) ? trim($values['text']) : '';
                    $value_number = isset($values['number']) && $values['number'] !== '' ? (float)$values['number'] : 0;
                    $value_boolean = isset($values['boolean']) ? 1 : 0;
                    
                    if (isset($values['ssd'])) $value_text = $values['ssd'];
                    if (isset($values['hdd'])) $value_text = $values['hdd'];
                    if (isset($values['integrated'])) $value_text = $values['integrated'];
                    if (isset($values['discrete'])) $value_text = $values['discrete'];
                    
                    if (!empty($value_text) || $value_number != 0 || $value_boolean) {
                        $p_id = $product_id;
                        $a_id = $attr_id;
                        $v_text = $value_text;
                        $v_num = $value_number;
                        $v_bool = $value_boolean;
                        
                        $params = array(&$p_id, &$a_id, &$v_text, &$v_num, &$v_bool);
                        $types = "iissi";
                        array_unshift($params, $types);
                        
                        call_user_func_array(array($attr_stmt, 'bind_param'), $params);
                        $attr_stmt->execute();
                    }
                }
                $attr_stmt->close();
            }
            
            // Обработка изображений
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                $upload_dir = __DIR__ . '/../uploads/products/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                $image_stmt = $db->prepare("INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)");
                $is_first = true;
                $sort = 0;
                
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) continue;
                    
                    $ext = pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
                    $new_name = uniqid('product_', true) . '.' . $ext;
                    $upload_path = $upload_dir . $new_name;
                    
                    if (move_uploaded_file($tmp_name, $upload_path)) {
                        $image_url = 'uploads/products/' . $new_name;
                        $img_product_id = $product_id;
                        $img_is_primary = $is_first ? 1 : 0;
                        $img_sort_order = $sort;
                        
                        $image_stmt->bind_param("issi", $img_product_id, $image_url, $img_is_primary, $img_sort_order);
                        $image_stmt->execute();
                        $is_first = false;
                        $sort++;
                    }
                }
                $image_stmt->close();
            }
            
            $db->commit();
            $_SESSION['success'] = "Товар успешно добавлен";
            header("Location: dashboard.php");
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = "Ошибка: " . $e->getMessage();
        }
    }
}

// Подключаем header ПОСЛЕ обработки POST
require_once __DIR__ . '/../inc/header.php';

$category_attributes_json = json_encode($attributes_by_category);

// Сохраняем выбранные значения для формы
$selected_category = isset($_POST['category_id']) ? $_POST['category_id'] : '';
$selected_type = isset($_POST['type']) ? $_POST['type'] : 'simple';
$selected_status = isset($_POST['status']) ? $_POST['status'] : 'active';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>Добавление товара</h1>
        <p class="text-muted mb-0">Магазин: <strong><?= htmlspecialchars($seller_name) ?></strong></p>
    </div>
    <div>
        <a href="my_products.php" class="btn btn-primary me-2">
            <i class="fas fa-box"></i> Мои товары
        </a>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-sync-alt"></i> Обновить
        </a>
    </div>
</div>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Основная информация</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Название *</label>
                                <input type="text" name="name" class="form-control" required value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Бренд *</label>
                                <input type="text" name="brand" class="form-control" required value="<?= isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : '' ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Модель *</label>
                                <input type="text" name="model" class="form-control" required value="<?= isset($_POST['model']) ? htmlspecialchars($_POST['model']) : '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Артикул (SKU)</label>
                                <input type="text" name="sku" class="form-control" value="<?= isset($_POST['sku']) ? htmlspecialchars($_POST['sku']) : '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Штрих-код</label>
                                <input type="text" name="barcode" class="form-control" value="<?= isset($_POST['barcode']) ? htmlspecialchars($_POST['barcode']) : '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Вес</label>
                                <div class="input-group">
                                    <input type="number" name="weight" step="0.01" class="form-control" value="<?= isset($_POST['weight']) ? htmlspecialchars($_POST['weight']) : '' ?>" min="0">
                                    <span class="input-group-text">кг</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Описание</h5></div>
                    <div class="card-body">
                        <textarea name="short_description" class="form-control mb-3" rows="2" placeholder="Краткое описание"><?= isset($_POST['short_description']) ? htmlspecialchars($_POST['short_description']) : '' ?></textarea>
                        <textarea name="description" class="form-control" rows="5" placeholder="Полное описание"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Характеристики</h5></div>
                    <div class="card-body" id="attributes-container">
                        <div class="alert alert-info">Выберите категорию</div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Изображения</h5></div>
                    <div class="card-body">
                        <input type="file" name="images[]" class="form-control mb-2" multiple accept="image/*">
                        <div id="imagePreview" class="row g-2"></div>
                        <small class="text-muted">Вы можете выбрать несколько изображений. Первое изображение будет основным.</small>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Категория</h5></div>
                    <div class="card-body">
                        <select name="category_id" id="category_id" class="form-select" required>
                            <option value="">Выберите категорию</option>
                            <?php foreach ($categories_data as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $selected_category == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-store"></i> 
                            <strong>Продавец:</strong> <?= htmlspecialchars($seller_name) ?>
                            <input type="hidden" name="seller_id" value="<?= $seller_id ?>">
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Настройки</h5></div>
                    <div class="card-body">
                        <label class="form-label">Тип товара</label>
                        <select name="type" class="form-select mb-3">
                            <option value="simple" <?= $selected_type == 'simple' ? 'selected' : '' ?>>Простой товар</option>
                            <option value="variable" <?= $selected_type == 'variable' ? 'selected' : '' ?>>С вариантами</option>
                            <option value="digital" <?= $selected_type == 'digital' ? 'selected' : '' ?>>Цифровой товар</option>
                        </select>
                        
                        <label class="form-label">Статус</label>
                        <select name="status" class="form-select mb-3">
                            <option value="draft" <?= $selected_status == 'draft' ? 'selected' : '' ?>>Черновик</option>
                            <option value="active" <?= $selected_status == 'active' ? 'selected' : '' ?>>Активный</option>
                            <option value="inactive" <?= $selected_status == 'inactive' ? 'selected' : '' ?>>Неактивный</option>
                        </select>
                        
                        <label class="form-label">Базовая цена *</label>
                        <input type="number" name="base_price" step="0.01" class="form-control mb-3" required value="<?= isset($_POST['base_price']) ? htmlspecialchars($_POST['base_price']) : '' ?>" min="0">
                        
                        <label class="form-label">Количество *</label>
                        <input type="number" name="quantity" class="form-control" value="<?= isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : '0' ?>" min="0">
                    </div>
                </div>

                <button type="submit" name="add_product" class="btn btn-success w-100">
                    <i class="fas fa-save"></i> Опубликовать товар
                </button>
            </div>
        </div>
    </form>
</div>

<script>
const categoryAttributes = <?= $category_attributes_json ?>;
const hiddenSlugs = ['screen_size', 'weight', 'weight_notebook', 'nb_ssd_size', 'nb_hdd_size', 'nb_gpu'];

async function loadSelectValues(attributeId, selectElement) {
    try {
        const res = await fetch(`/mobileshop/admin/get_attribute_values.php?attribute_id=${attributeId}`);
        const data = await res.json();
        if (data.values && data.values.length) {
            selectElement.innerHTML = '<option value="">Выберите</option>';
            data.values.forEach(v => { selectElement.innerHTML += `<option value="${escapeHtml(v.value)}">${escapeHtml(v.value)}</option>`; });
        } else {
            selectElement.innerHTML = '<option value="">Нет значений</option>';
        }
    } catch(e) { 
        console.error('Error loading values:', e);
        selectElement.innerHTML = '<option value="">Ошибка загрузки</option>'; 
    }
}

function addStorageFields(select, attrId) {
    const container = select.closest('.row');
    if (!container) return;
    document.querySelectorAll('[data-dependent="ssd"],[data-dependent="hdd"]').forEach(el => el.remove());
    
    const val = select.value;
    if (val === 'SSD') {
        container.insertAdjacentHTML('beforeend', `<div class="col-md-6 mb-3" data-dependent="ssd"><label class="form-label">Объем SSD <span class="text-danger">*</span></label><div class="input-group"><input type="number" name="attributes[${attrId}][ssd]" step="1" class="form-control" required min="0"><span class="input-group-text">ГБ</span></div></div>`);
    } else if (val === 'HDD') {
        container.insertAdjacentHTML('beforeend', `<div class="col-md-6 mb-3" data-dependent="hdd"><label class="form-label">Объем HDD <span class="text-danger">*</span></label><div class="input-group"><input type="number" name="attributes[${attrId}][hdd]" step="1" class="form-control" required min="0"><span class="input-group-text">ГБ</span></div></div>`);
    } else if (val === 'SSD+HDD') {
        container.insertAdjacentHTML('beforeend', `
            <div class="col-md-6 mb-3" data-dependent="ssd"><label class="form-label">Объем SSD <span class="text-danger">*</span></label><div class="input-group"><input type="number" name="attributes[${attrId}][ssd]" step="1" class="form-control" required min="0"><span class="input-group-text">ГБ</span></div></div>
            <div class="col-md-6 mb-3" data-dependent="hdd"><label class="form-label">Объем HDD <span class="text-danger">*</span></label><div class="input-group"><input type="number" name="attributes[${attrId}][hdd]" step="1" class="form-control" required min="0"><span class="input-group-text">ГБ</span></div></div>
        `);
    }
}

function addGpuField(select, attrId) {
    const container = select.closest('.row');
    if (!container) return;
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

function getStepForAttribute(attr) {
    if (attr.slug === 'nb_cpu_cores' || 
        attr.slug === 'nb_ram_size' || 
        attr.slug === 'nb_ssd_size' || 
        attr.slug === 'nb_hdd_size' ||
        attr.slug === 'ch_ports_count' ||
        attr.slug === 'hp_impedance' ||
        attr.slug === 'hp_sensitivity' ||
        attr.slug === 'sm_refresh_rate') {
        return '1';
    }
    if (attr.slug === 'hp_battery_life') {
        return '0.5';
    }
    return '0.1';
}

function getUnitDisplay(attr) {
    if (attr.slug === 'sm_fast_charging') {
        return 'Вт';
    }
    if (attr.unit && attr.unit !== '') {
        return attr.unit;
    }
    return '';
}

function renderAttr(attr) {
    if (hiddenSlugs.includes(attr.slug)) return '';
    
    let req = attr.is_required ? 'required' : '';
    let step = attr.type === 'number' ? getStepForAttribute(attr) : '';
    let unit = getUnitDisplay(attr);
    
    let html = `<div class="col-md-6 mb-3">`;
    html += `<label class="form-label">${escapeHtml(attr.name)}${attr.is_required ? ' <span class="text-danger" data-bs-toggle="tooltip" title="Обязательное поле">*</span>' : ''}</label>`;
    
    if (attr.type === 'number') {
        html += `<div class="input-group">`;
        html += `<input type="number" name="attributes[${attr.id}][number]" step="${step}" class="form-control" ${req} min="0">`;
        if (unit) {
            html += `<span class="input-group-text">${escapeHtml(unit)}</span>`;
        }
        html += `</div>`;
    } else if (attr.type === 'select') {
        html += `<select name="attributes[${attr.id}][text]" class="form-select" data-attr-id="${attr.id}" data-attr-slug="${attr.slug}" ${req}><option value="">Загрузка...</option></select>`;
    } else if (attr.type === 'boolean') {
        html += `<div class="form-check"><input type="checkbox" name="attributes[${attr.id}][boolean]" class="form-check-input" value="1"><label class="form-check-label">Да</label></div>`;
    } else {
        html += `<input type="text" name="attributes[${attr.id}][text]" class="form-control" ${req}>`;
    }
    html += '</div>';
    return html;
}

function escapeHtml(t) { 
    if(!t) return ''; 
    const d = document.createElement('div'); 
    d.textContent = t; 
    return d.innerHTML; 
}

document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    const categorySelect = document.getElementById('category_id');
    
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            const cid = parseInt(this.value);
            const cont = document.getElementById('attributes-container');
            if (!cid || !categoryAttributes[cid]) { 
                cont.innerHTML = '<div class="alert alert-info">Выберите категорию</div>'; 
                return; 
            }
            
            const attrs = categoryAttributes[cid].filter(a => !hiddenSlugs.includes(a.slug));
            attrs.sort((a,b) => a.sort_order - b.sort_order);
            let html = '<div class="row">';
            attrs.forEach(a => { html += renderAttr(a); });
            html += '</div>';
            cont.innerHTML = html;
            
            setTimeout(() => {
                document.querySelectorAll('select[data-attr-id]').forEach(s => loadSelectValues(s.dataset.attrId, s));
                
                const storageSel = document.querySelector('select[data-attr-slug="nb_storage_type"]');
                if(storageSel) {
                    const aid = storageSel.dataset.attrId;
                    storageSel.addEventListener('change', () => addStorageFields(storageSel, aid));
                    if(storageSel.value) addStorageFields(storageSel, aid);
                }
                
                const gpuTypeSel = document.querySelector('select[data-attr-slug="nb_gpu_type"]');
                if(gpuTypeSel) {
                    const aid = gpuTypeSel.dataset.attrId;
                    gpuTypeSel.addEventListener('change', () => addGpuField(gpuTypeSel, aid));
                    if(gpuTypeSel.value) addGpuField(gpuTypeSel, aid);
                }
                
                var newTooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                newTooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }, 100);
        });
        
        if (categorySelect.value) {
            categorySelect.dispatchEvent(new Event('change'));
        }
    }
    
    const imageInput = document.querySelector('input[name="images[]"]');
    if (imageInput) {
        imageInput.addEventListener('change', function() {
            const preview = document.getElementById('imagePreview');
            if (preview) {
                preview.innerHTML = '';
                for(let f of this.files) {
                    const r = new FileReader();
                    r.onload = e => { 
                        preview.innerHTML += `<div class="col-md-3 mb-2"><div class="card"><img src="${e.target.result}" class="card-img-top" style="height:100px;object-fit:cover;"></div></div>`; 
                    };
                    r.readAsDataURL(f);
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>