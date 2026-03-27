<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

// Получаем уникальные значения для фильтров из таблицы атрибутов
$specs = [];

// Получаем уникальные значения RAM
$ram_query = $db->query("
    SELECT DISTINCT pa.value_number as ram
    FROM product_attributes pa
    JOIN attributes a ON a.id = pa.attribute_id
    WHERE a.slug = 'ram' AND pa.value_number IS NOT NULL
    ORDER BY pa.value_number ASC
");
if ($ram_query) {
    while ($row = $ram_query->fetch_assoc()) {
        $specs['ram'][] = $row['ram'];
    }
}

// Получаем уникальные значения Storage
$storage_query = $db->query("
    SELECT DISTINCT pa.value_number as storage
    FROM product_attributes pa
    JOIN attributes a ON a.id = pa.attribute_id
    WHERE a.slug = 'storage' AND pa.value_number IS NOT NULL
    ORDER BY pa.value_number ASC
");
if ($storage_query) {
    while ($row = $storage_query->fetch_assoc()) {
        $specs['storage'][] = $row['storage'];
    }
}

// Получаем уникальные значения Battery Capacity
$battery_query = $db->query("
    SELECT DISTINCT pa.value_number as battery
    FROM product_attributes pa
    JOIN attributes a ON a.id = pa.attribute_id
    WHERE a.slug = 'battery' AND pa.value_number IS NOT NULL
    ORDER BY pa.value_number ASC
");
if ($battery_query) {
    while ($row = $battery_query->fetch_assoc()) {
        $specs['battery_capacity'][] = $row['battery'];
    }
}

// Получаем уникальные значения Screen Size
$screen_query = $db->query("
    SELECT DISTINCT pa.value_number as screen_size
    FROM product_attributes pa
    JOIN attributes a ON a.id = pa.attribute_id
    WHERE a.slug = 'screen_size' AND pa.value_number IS NOT NULL
    ORDER BY pa.value_number ASC
");
if ($screen_query) {
    while ($row = $screen_query->fetch_assoc()) {
        $specs['screen_size'][] = $row['screen_size'];
    }
}

// Получаем уникальные значения Processor
$processor_query = $db->query("
    SELECT DISTINCT pa.value_text as processor
    FROM product_attributes pa
    JOIN attributes a ON a.id = pa.attribute_id
    WHERE a.slug = 'processor' AND pa.value_text IS NOT NULL AND pa.value_text != ''
    ORDER BY pa.value_text ASC
");
if ($processor_query) {
    while ($row = $processor_query->fetch_assoc()) {
        $specs['processor'][] = $row['processor'];
    }
}

// Получаем уникальные значения OS
$os_query = $db->query("
    SELECT DISTINCT pa.value_text as os
    FROM product_attributes pa
    JOIN attributes a ON a.id = pa.attribute_id
    WHERE a.slug = 'os' AND pa.value_text IS NOT NULL AND pa.value_text != ''
    ORDER BY pa.value_text ASC
");
if ($os_query) {
    while ($row = $os_query->fetch_assoc()) {
        $specs['os'][] = $row['os'];
    }
}

// Получаем уникальные бренды
$brands_query = $db->query("
    SELECT DISTINCT brand
    FROM products
    WHERE status = 'active' AND brand IS NOT NULL AND brand != ''
    ORDER BY brand ASC
");
$brands = [];
if ($brands_query) {
    while ($row = $brands_query->fetch_assoc()) {
        $brands[] = $row['brand'];
    }
}

// Получаем диапазон цен
$price_range_query = $db->query("
    SELECT MIN(pv.price) as min_price, MAX(pv.price) as max_price
    FROM product_variations pv
    JOIN products p ON p.id = pv.product_id
    WHERE p.status = 'active'
");
$price_range = $price_range_query->fetch_assoc();
?>

<div class="container"><br>
    <h2>Подбор смартфонов по параметрам</h2><br>
    
    <form id="filter-form" class="filter-form">
        <div class="row">
            <div class="col-md-4">
                <label for="brand">Бренд:</label>
                <select name="brand" id="brand" class="form-control filter-input">
                    <option value="">Все бренды</option>
                    <?php if (!empty($brands)): ?>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= htmlspecialchars($brand); ?>"><?= htmlspecialchars($brand); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="min_price">Цена от:</label>
                <input type="number" name="min_price" id="min_price" class="form-control filter-input" 
                       placeholder="От" value="" 
                       min="<?= floor($price_range['min_price'] ?? 0) ?>"
                       max="<?= ceil($price_range['max_price'] ?? 100000) ?>">
            </div>
            <div class="col-md-4">
                <label for="max_price">Цена до:</label>
                <input type="number" name="max_price" id="max_price" class="form-control filter-input" 
                       placeholder="До" value=""
                       min="<?= floor($price_range['min_price'] ?? 0) ?>"
                       max="<?= ceil($price_range['max_price'] ?? 100000) ?>">
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-md-4">
                <label for="screen_size">Размер экрана (дюймы):</label>
                <select name="screen_size" id="screen_size" class="form-control filter-input">
                    <option value="">Не важно</option>
                    <?php if (!empty($specs['screen_size'])): ?>
                        <?php foreach ($specs['screen_size'] as $value): ?>
                            <option value="<?= htmlspecialchars($value); ?>"><?= htmlspecialchars($value); ?> дюймов</option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="ram">ОЗУ (RAM):</label>
                <select name="ram" id="ram" class="form-control filter-input">
                    <option value="">Не важно</option>
                    <?php if (!empty($specs['ram'])): ?>
                        <?php foreach ($specs['ram'] as $value): ?>
                            <option value="<?= htmlspecialchars($value); ?>"><?= htmlspecialchars($value); ?> ГБ</option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="storage">Объем хранилища:</label>
                <select name="storage" id="storage" class="form-control filter-input">
                    <option value="">Не важно</option>
                    <?php if (!empty($specs['storage'])): ?>
                        <?php foreach ($specs['storage'] as $value): ?>
                            <option value="<?= htmlspecialchars($value); ?>"><?= htmlspecialchars($value); ?> ГБ</option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-md-4">
                <label for="processor">Процессор:</label>
                <select name="processor" id="processor" class="form-control filter-input">
                    <option value="">Не важно</option>
                    <?php if (!empty($specs['processor'])): ?>
                        <?php foreach ($specs['processor'] as $value): ?>
                            <option value="<?= htmlspecialchars($value); ?>"><?= htmlspecialchars($value); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="os">Операционная система:</label>
                <select name="os" id="os" class="form-control filter-input">
                    <option value="">Не важно</option>
                    <?php if (!empty($specs['os'])): ?>
                        <?php foreach ($specs['os'] as $value): ?>
                            <option value="<?= htmlspecialchars($value); ?>"><?= htmlspecialchars($value); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="battery_capacity">Минимальная емкость батареи:</label>
                <select name="battery_capacity" id="battery_capacity" class="form-control filter-input">
                    <option value="">Не важно</option>
                    <?php if (!empty($specs['battery_capacity'])): ?>
                        <?php foreach ($specs['battery_capacity'] as $value): ?>
                            <option value="<?= htmlspecialchars($value); ?>">от <?= htmlspecialchars($value); ?> мАч</option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-12">
                <button type="button" id="reset-filters" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Очистить фильтр
                </button>
            </div>
        </div>
    </form>

    <h2 class="mt-4">Найденные смартфоны</h2><br>
    <div id="smartphone-list" class="row">
        <div class="col-12 text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function () {
    function loadSmartphones() {
        $("#smartphone-list").html('<div class="col-12 text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Загрузка...</span></div></div>');
        
        $.ajax({
            url: "filter.php",
            method: "POST",
            data: $("#filter-form").serialize(),
            success: function (data) {
                $("#smartphone-list").html(data);
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                $("#smartphone-list").html('<div class="col-12"><div class="alert alert-danger">Ошибка при загрузке данных</div></div>');
            }
        });
    }

    // Загружаем при изменении любого фильтра
    $(".filter-input").on('change keyup', function() {
        loadSmartphones();
    });

    // Кнопка сброса
    $("#reset-filters").click(function () {
        $("#filter-form")[0].reset();
        loadSmartphones();
    });

    // Первоначальная загрузка
    loadSmartphones();
});
</script>

<style>
.filter-form {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.filter-form label {
    font-weight: 600;
    margin-bottom: 5px;
    color: #495057;
}
.filter-input {
    border: 1px solid #ced4da;
    border-radius: 4px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.filter-input:focus {
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}
#reset-filters {
    padding: 8px 20px;
}
.spinner-border {
    width: 3rem;
    height: 3rem;
}
</style>

<?php require_once __DIR__ . '/inc/footer.php'; ?>