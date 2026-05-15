<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id'])) {
    header('Location: ../pages/auth.php');
    exit;
}

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

$user_id = (int)$_SESSION['id'];

$seller_check = $db->prepare("SELECT id, shop_name, is_active FROM sellers WHERE user_id = ?");
$seller_check->bind_param('i', $user_id);
$seller_check->execute();
$seller_res = $seller_check->get_result();
$current_seller = $seller_res->fetch_assoc();

if (!$current_seller) {
    die("Доступ запрещен. Вы не зарегистрированы как продавец.");
}

if ((int)$current_seller['is_active'] !== 1) {
    die("Ваш магазин не активен. Обратитесь к администратору.");
}

$seller_id = (int)$current_seller['id'];
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    die('Некорректный ID товара.');
}

$product_stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
$product_stmt->bind_param('ii', $product_id, $seller_id);
$product_stmt->execute();
$product = $product_stmt->get_result()->fetch_assoc();
$product_stmt->close();

if (!$product) {
    die('Товар не найден или не принадлежит вашему магазину.');
}

$categories_data = [];
$cats_result = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
while ($cat = $cats_result->fetch_assoc()) {
    $categories_data[] = $cat;
}

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

$current_attributes = [];
$attr_stmt = $db->prepare("SELECT attribute_id, value_text, value_number, value_boolean FROM product_attributes WHERE product_id = ?");
$attr_stmt->bind_param('i', $product_id);
$attr_stmt->execute();
$attr_res = $attr_stmt->get_result();
while ($row = $attr_res->fetch_assoc()) {
    $current_attributes[$row['attribute_id']] = $row;
}
$attr_stmt->close();

$current_images = [];
$img_stmt = $db->prepare("SELECT id, image_url, is_primary, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
$img_stmt->bind_param('i', $product_id);
$img_stmt->execute();
$img_res = $img_stmt->get_result();
while ($img = $img_res->fetch_assoc()) {
    $current_images[] = $img;
}
$img_stmt->close();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $name = trim($_POST['name'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $type = $_POST['type'] ?? 'simple';
    $status = $_POST['status'] ?? 'active';
    $short_description = trim($_POST['short_description'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $base_price = (float)($_POST['base_price'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $weight = isset($_POST['weight']) && $_POST['weight'] !== '' ? (float)$_POST['weight'] : null;

    if ($name === '') $errors[] = 'Название товара обязательно';
    if ($brand === '') $errors[] = 'Бренд обязателен';
    if ($model === '') $errors[] = 'Модель обязательна';
    if ($category_id <= 0) $errors[] = 'Выберите категорию';
    if ($base_price <= 0) $errors[] = 'Цена должна быть больше 0';
    if ($quantity < 0) $errors[] = 'Количество не может быть отрицательным';

    if (empty($errors)) {
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("UPDATE products SET
                category_id = ?,
                name = ?,
                sku = ?,
                barcode = ?,
                short_description = ?,
                description = ?,
                brand = ?,
                model = ?,
                price = ?,
                quantity = ?,
                type = ?,
                status = ?,
                weight = ?
                WHERE id = ? AND seller_id = ?");
            $stmt->bind_param(
                'isssssssdissdii',
                $category_id,
                $name,
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
                $weight,
                $product_id,
                $seller_id
            );
            $stmt->execute();
            $stmt->close();

            $db->query("DELETE FROM product_attributes WHERE product_id = " . (int)$product_id);

            if (isset($_POST['attributes']) && is_array($_POST['attributes'])) {
                $attr_insert = $db->prepare("INSERT INTO product_attributes (product_id, attribute_id, value_text, value_number, value_boolean) VALUES (?, ?, ?, ?, ?)");
                foreach ($_POST['attributes'] as $attr_id => $values) {
                    $attr_id = (int)$attr_id;
                    $value_text = isset($values['text']) ? trim($values['text']) : '';
                    $value_number = isset($values['number']) && $values['number'] !== '' ? (float)$values['number'] : 0;
                    $value_boolean = isset($values['boolean']) ? 1 : 0;

                    if (isset($values['ssd'])) $value_text = $values['ssd'];
                    if (isset($values['hdd'])) $value_text = $values['hdd'];
                    if (isset($values['integrated'])) $value_text = $values['integrated'];
                    if (isset($values['discrete'])) $value_text = $values['discrete'];

                    if ($value_text !== '' || $value_number != 0 || $value_boolean) {
                        $attr_insert->bind_param('iisdi', $product_id, $attr_id, $value_text, $value_number, $value_boolean);
                        $attr_insert->execute();
                    }
                }
                $attr_insert->close();
            }

            if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                $del_stmt = $db->prepare("SELECT id, image_url FROM product_images WHERE id = ? AND product_id = ?");
                $delete_stmt = $db->prepare("DELETE FROM product_images WHERE id = ? AND product_id = ?");
                foreach ($_POST['delete_images'] as $image_id) {
                    $image_id = (int)$image_id;
                    $del_stmt->bind_param('ii', $image_id, $product_id);
                    $del_stmt->execute();
                    $img_row = $del_stmt->get_result()->fetch_assoc();
                    if ($img_row) {
                        $file_path = dirname(__DIR__) . '/' . ltrim($img_row['image_url'], '/');
                        if (is_file($file_path)) {
                            @unlink($file_path);
                        }
                        $delete_stmt->bind_param('ii', $image_id, $product_id);
                        $delete_stmt->execute();
                    }
                }
                $del_stmt->close();
                $delete_stmt->close();
            }

            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                $upload_dir = dirname(__DIR__) . '/uploads/products/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $image_stmt = $db->prepare("INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)");

                $sort_res = $db->query("SELECT COALESCE(MAX(sort_order), -1) AS max_sort FROM product_images WHERE product_id = " . (int)$product_id);
                $sort_row = $sort_res->fetch_assoc();
                $sort = (int)$sort_row['max_sort'] + 1;

                $primary_exists_res = $db->query("SELECT COUNT(*) AS cnt FROM product_images WHERE product_id = " . (int)$product_id . " AND is_primary = 1");
                $primary_exists = ((int)$primary_exists_res->fetch_assoc()['cnt']) > 0;

                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) continue;
                    $ext = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'])) continue;
                    $new_name = uniqid('product_', true) . '.' . $ext;
                    $upload_path = $upload_dir . $new_name;
                    if (move_uploaded_file($tmp_name, $upload_path)) {
                        $image_url = 'uploads/products/' . $new_name;
                        $img_is_primary = $primary_exists ? 0 : 1;
                        $image_stmt->bind_param('issi', $product_id, $image_url, $img_is_primary, $sort);
                        $image_stmt->execute();
                        if (!$primary_exists) $primary_exists = true;
                        $sort++;
                    }
                }
                $image_stmt->close();
            }

            if (isset($_POST['primary_image']) && $_POST['primary_image'] !== '') {
                $primary_id = (int)$_POST['primary_image'];
                $db->query("UPDATE product_images SET is_primary = 0 WHERE product_id = " . (int)$product_id);
                $primary_stmt = $db->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?");
                $primary_stmt->bind_param('ii', $primary_id, $product_id);
                $primary_stmt->execute();
                $primary_stmt->close();
            } else {
                $check_primary = $db->query("SELECT COUNT(*) AS cnt FROM product_images WHERE product_id = " . (int)$product_id . " AND is_primary = 1");
                if ((int)$check_primary->fetch_assoc()['cnt'] === 0) {
                    $first_img = $db->query("SELECT id FROM product_images WHERE product_id = " . (int)$product_id . " ORDER BY sort_order ASC, id ASC LIMIT 1");
                    if ($row = $first_img->fetch_assoc()) {
                        $db->query("UPDATE product_images SET is_primary = 1 WHERE id = " . (int)$row['id']);
                    }
                }
            }

            $db->commit();
            $_SESSION['success'] = 'Товар успешно обновлен';
            header('Location: my_products.php');
            exit;
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Ошибка: ' . $e->getMessage();
        }
    }
}

$product_stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
$product_stmt->bind_param('ii', $product_id, $seller_id);
$product_stmt->execute();
$product = $product_stmt->get_result()->fetch_assoc();
$product_stmt->close();

$current_attributes = [];
$attr_stmt = $db->prepare("SELECT attribute_id, value_text, value_number, value_boolean FROM product_attributes WHERE product_id = ?");
$attr_stmt->bind_param('i', $product_id);
$attr_stmt->execute();
$attr_res = $attr_stmt->get_result();
while ($row = $attr_res->fetch_assoc()) {
    $current_attributes[$row['attribute_id']] = $row;
}
$attr_stmt->close();

$current_images = [];
$img_stmt = $db->prepare("SELECT id, image_url, is_primary, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
$img_stmt->bind_param('i', $product_id);
$img_stmt->execute();
$img_res = $img_stmt->get_result();
while ($img = $img_res->fetch_assoc()) {
    $current_images[] = $img;
}
$img_stmt->close();

require_once __DIR__ . '/../inc/header.php';
$category_attributes_json = json_encode($attributes_by_category, JSON_UNESCAPED_UNICODE);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Редактирование товара</h1>
            <p class="text-muted mb-0">Магазин: <strong><?= htmlspecialchars($current_seller['shop_name']) ?></strong></p>
        </div>
        <div>
            <a href="my_products.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> К списку товаров
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

    <form method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Основная информация</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Название товара</label>
                                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($product['name'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Бренд</label>
                                <input type="text" name="brand" class="form-control" required value="<?= htmlspecialchars($product['brand'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Модель</label>
                                <input type="text" name="model" class="form-control" required value="<?= htmlspecialchars($product['model'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">SKU</label>
                                <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($product['sku'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Штрихкод</label>
                                <input type="text" name="barcode" class="form-control" value="<?= htmlspecialchars($product['barcode'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Вес</label>
                                <div class="input-group">
                                    <input type="number" name="weight" step="0.01" class="form-control" min="0" value="<?= htmlspecialchars($product['weight'] ?? '') ?>">
                                    <span class="input-group-text">кг</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Описание</h5></div>
                    <div class="card-body">
                        <textarea name="short_description" class="form-control mb-3" rows="2" placeholder="Краткое описание"><?= htmlspecialchars($product['short_description'] ?? '') ?></textarea>
                        <textarea name="description" class="form-control" rows="5" placeholder="Полное описание"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Атрибуты</h5></div>
                    <div class="card-body" id="attributes-container">
                        <div class="alert alert-info">Выберите категорию, чтобы отредактировать атрибуты.</div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Изображения товара</h5></div>
                    <div class="card-body">
                        <?php if (!empty($current_images)): ?>
                            <div class="row g-3 mb-4">
                                <?php foreach ($current_images as $img): ?>
                                    <div class="col-md-4">
                                        <div class="card h-100">
                                            <img src="../<?= htmlspecialchars($img['image_url']) ?>" class="card-img-top" style="height:180px;object-fit:cover;" alt="Изображение товара">
                                            <div class="card-body">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="radio" name="primary_image" value="<?= (int)$img['id'] ?>" id="primary_<?= (int)$img['id'] ?>" <?= !empty($img['is_primary']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="primary_<?= (int)$img['id'] ?>">Главное изображение</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="delete_images[]" value="<?= (int)$img['id'] ?>" id="delete_<?= (int)$img['id'] ?>">
                                                    <label class="form-check-label text-danger" for="delete_<?= (int)$img['id'] ?>">Удалить</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <input type="file" name="images[]" class="form-control mb-2" multiple accept="image/*">
                        <div id="imagePreview" class="row g-2"></div>
                        <small class="text-muted">Можно добавить новые изображения. Поддерживаются JPG, PNG, WEBP, GIF, AVIF.</small>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Категория</h5></div>
                    <div class="card-body">
                        <select name="category_id" id="categoryid" class="form-select" required>
                            <option value="">Выберите категорию</option>
                            <?php foreach ($categories_data as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>" <?= ((int)$product['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="alert alert-info mt-3 mb-0">
                            <i class="fas fa-store"></i>
                            <strong>Продавец:</strong> <?= htmlspecialchars($current_seller['shop_name']) ?>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Параметры товара</h5></div>
                    <div class="card-body">
                        <label class="form-label">Тип товара</label>
                        <select name="type" class="form-select mb-3">
                            <option value="simple" <?= (($product['type'] ?? 'simple') === 'simple') ? 'selected' : '' ?>>Обычный</option>
                            <option value="variable" <?= (($product['type'] ?? '') === 'variable') ? 'selected' : '' ?>>Вариативный</option>
                            <option value="digital" <?= (($product['type'] ?? '') === 'digital') ? 'selected' : '' ?>>Цифровой</option>
                        </select>

                        <label class="form-label">Статус</label>
                        <select name="status" class="form-select mb-3">
                            <option value="draft" <?= (($product['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Черновик</option>
                            <option value="active" <?= (($product['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Активный</option>
                            <option value="inactive" <?= (($product['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Неактивный</option>
                        </select>

                        <label class="form-label">Цена</label>
                        <input type="number" name="base_price" step="0.01" class="form-control mb-3" required min="0.01" value="<?= htmlspecialchars($product['price'] ?? '') ?>">

                        <label class="form-label">Количество</label>
                        <input type="number" name="quantity" class="form-control" min="0" value="<?= htmlspecialchars($product['quantity'] ?? 0) ?>">
                    </div>
                </div>

                <button type="submit" name="update_product" class="btn btn-success w-100">
                    <i class="fas fa-save"></i> Сохранить изменения
                </button>
            </div>
        </div>
    </form>
</div>

<script>
const categoryAttributes = <?= $category_attributes_json ?>;
const currentAttributes = <?= json_encode($current_attributes, JSON_UNESCAPED_UNICODE) ?>;
const hiddenSlugs = ['screen_size', 'weight', 'weight_notebook', 'nb_ssd_size', 'nb_hdd_size', 'nb_gpu'];

async function loadSelectValues(attributeId, selectElement, selectedValue = '') {
    try {
        const res = await fetch('../admin/get_attribute_values.php?attribute_id=' + attributeId);
        const data = await res.json();
        if (data.values && data.values.length) {
            selectElement.innerHTML = '<option value="">Выберите...</option>';
            data.values.forEach(v => {
                const option = document.createElement('option');
                option.value = v.value;
                option.textContent = v.value;
                if (selectedValue && selectedValue === v.value) option.selected = true;
                selectElement.appendChild(option);
            });
        } else {
            selectElement.innerHTML = '<option value="">Нет значений</option>';
        }
    } catch (e) {
        console.error('Error loading values', e);
        selectElement.innerHTML = '<option value="">Ошибка загрузки</option>';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getStepForAttribute(attr) {
    if (['nb_cpu_cores','nb_ram_size','nb_ssd_size','nb_hdd_size','ch_ports_count','hp_impedance','hp_sensitivity','sm_refresh_rate'].includes(attr.slug)) return 1;
    if (attr.slug === 'hp_battery_life') return 0.5;
    return 0.1;
}

function getUnitDisplay(attr) {
    if (attr.slug === 'sm_fast_charging') return 'Вт';
    return attr.unit || '';
}

function addStorageFields(select, attrId) {
    const container = select.closest('.row');
    if (!container) return;
    container.querySelectorAll('[data-dependent="ssd"], [data-dependent="hdd"]').forEach(el => el.remove());
    const val = select.value;

    if (val === 'SSD' || val === 'SSD+HDD') {
        container.insertAdjacentHTML('beforeend', `
            <div class="col-md-6 mb-3" data-dependent="ssd">
                <label class="form-label">Объем SSD <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" name="attributes[${attrId}][ssd]" step="1" class="form-control" min="0">
                    <span class="input-group-text">ГБ</span>
                </div>
            </div>`);
    }
    if (val === 'HDD' || val === 'SSD+HDD') {
        container.insertAdjacentHTML('beforeend', `
            <div class="col-md-6 mb-3" data-dependent="hdd">
                <label class="form-label">Объем HDD <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" name="attributes[${attrId}][hdd]" step="1" class="form-control" min="0">
                    <span class="input-group-text">ГБ</span>
                </div>
            </div>`);
    }
}

function addGpuField(select, attrId) {
    const container = select.closest('.row');
    if (!container) return;
    container.querySelectorAll('[data-dependent="gpu-integrated"], [data-dependent="gpu-discrete"]').forEach(el => el.remove());
    const val = select.value;
    if (!val) return;

    if (val === 'Встроенная' || val === 'Встроенная+Дискретная') {
        container.insertAdjacentHTML('beforeend', `
            <div class="col-md-6 mb-3" data-dependent="gpu-integrated">
                <label class="form-label">Встроенная видеокарта <span class="text-danger">*</span></label>
                <input type="text" name="attributes[${attrId}][integrated]" class="form-control" placeholder="Intel Iris Xe">
            </div>`);
    }
    if (val === 'Дискретная' || val === 'Встроенная+Дискретная') {
        container.insertAdjacentHTML('beforeend', `
            <div class="col-md-6 mb-3" data-dependent="gpu-discrete">
                <label class="form-label">Дискретная видеокарта <span class="text-danger">*</span></label>
                <input type="text" name="attributes[${attrId}][discrete]" class="form-control" placeholder="NVIDIA GeForce RTX 3060">
            </div>`);
    }
}

function renderAttr(attr) {
    if (hiddenSlugs.includes(attr.slug)) return '';
    const current = currentAttributes[attr.id] || {};
    const req = attr.is_required ? 'required' : '';
    const step = attr.type === 'number' ? getStepForAttribute(attr) : '';
    const unit = getUnitDisplay(attr);
    let html = `<div class="col-md-6 mb-3">`;
    html += `<label class="form-label">${escapeHtml(attr.name)}${attr.is_required ? ' <span class="text-danger">*</span>' : ''}</label>`;

    if (attr.type === 'number') {
        html += `<div class="input-group"><input type="number" name="attributes[${attr.id}][number]" step="${step}" class="form-control" ${req} min="0" value="${current.value_number ?? ''}">`;
        if (unit) html += `<span class="input-group-text">${escapeHtml(unit)}</span>`;
        html += `</div>`;
    } else if (attr.type === 'select') {
        html += `<select name="attributes[${attr.id}][text]" class="form-select" data-attr-id="${attr.id}" data-attr-slug="${attr.slug}" data-selected="${escapeHtml(current.value_text ?? '')}" ${req}><option value="">Загрузка...</option></select>`;
    } else if (attr.type === 'boolean') {
        const checked = parseInt(current.value_boolean || 0) ? 'checked' : '';
        html += `<div class="form-check"><input type="checkbox" name="attributes[${attr.id}][boolean]" class="form-check-input" value="1" ${checked}><label class="form-check-label">Да</label></div>`;
    } else {
        html += `<input type="text" name="attributes[${attr.id}][text]" class="form-control" ${req} value="${escapeHtml(current.value_text ?? '')}">`;
    }

    html += `</div>`;
    return html;
}

document.addEventListener('DOMContentLoaded', function () {
    const categorySelect = document.getElementById('categoryid');
    const cont = document.getElementById('attributes-container');

    function renderCategoryAttributes(cid) {
        if (!cid || !categoryAttributes[cid]) {
            cont.innerHTML = '<div class="alert alert-info">Выберите категорию, чтобы отредактировать атрибуты.</div>';
            return;
        }

        const attrs = categoryAttributes[cid].filter(a => !hiddenSlugs.includes(a.slug));
        attrs.sort((a, b) => a.sort_order - b.sort_order);
        let html = '<div class="row">';
        attrs.forEach(a => html += renderAttr(a));
        html += '</div>';
        cont.innerHTML = html;

        setTimeout(() => {
            document.querySelectorAll('select[data-attr-id]').forEach(s => {
                const selected = s.dataset.selected || '';
                loadSelectValues(s.dataset.attrId, s, selected).then(() => {
                    if (s.dataset.attrSlug === 'nb_storage_type') {
                        s.addEventListener('change', () => addStorageFields(s, s.dataset.attrId));
                        if (s.value) addStorageFields(s, s.dataset.attrId);
                    }
                    if (s.dataset.attrSlug === 'nb_gpu_type') {
                        s.addEventListener('change', () => addGpuField(s, s.dataset.attrId));
                        if (s.value) addGpuField(s, s.dataset.attrId);
                    }
                });
            });
        }, 100);
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', function () {
            renderCategoryAttributes(parseInt(this.value, 10));
        });
        if (categorySelect.value) {
            renderCategoryAttributes(parseInt(categorySelect.value, 10));
        }
    }

    const imageInput = document.querySelector('input[name="images[]"]');
    if (imageInput) {
        imageInput.addEventListener('change', function () {
            const preview = document.getElementById('imagePreview');
            if (!preview) return;
            preview.innerHTML = '';
            for (let f of this.files) {
                const r = new FileReader();
                r.onload = e => {
                    preview.innerHTML += `<div class="col-md-3 mb-2"><div class="card"><img src="${e.target.result}" class="card-img-top" style="height:100px;object-fit:cover;"></div></div>`;
                };
                r.readAsDataURL(f);
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>