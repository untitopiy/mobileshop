<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';

$specs_query = $db->query("
    SELECT DISTINCT screen_size, ram, storage, processor, os, battery_capacity
    FROM smartphone_specs
    ORDER BY ram DESC, storage DESC, battery_capacity DESC
");
$specs = [];

while ($row = $specs_query->fetch_assoc()) {
    foreach ($row as $key => $value) {
        if ($value !== null) {
            $specs[$key][] = $value;
        }
    }
}

foreach ($specs as &$values) {
    $values = array_unique($values);
    if (!empty($values) && is_numeric($values[array_key_first($values)])) {
        sort($values, SORT_NUMERIC);
    } else {
        sort($values);
    }
}
?>

<div class="container"><br>
    <h2>Рекомендация смартфонов по параметрам</h2><br>
    <form id="filter-form" class="filter-form">
        <div class="row">
            <div class="col-md-4">
                <label for="screen_size">Размер экрана:</label>
                <select name="screen_size" id="screen_size" class="form-control filter-input">
                    <option value="">Не важно</option>
                    <?php if (isset($specs['screen_size'])): ?>
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
                    <?php if (isset($specs['ram'])): ?>
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
                     <?php if (isset($specs['storage'])): ?>
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
                    <?php if (isset($specs['processor'])): ?>
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
                    <?php if (isset($specs['os'])): ?>
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
                    <?php if (isset($specs['battery_capacity'])): ?>
                        <?php foreach ($specs['battery_capacity'] as $value): ?>
                            <option value="<?= htmlspecialchars($value); ?>"><?= htmlspecialchars($value); ?> мАч</option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <button type="button" id="reset-filters" class="btn btn-sm btn-secondary mt-3">Очистить фильтр</button>
    </form>

    <h2 class="mt-4">Найденные смартфоны</h2><br>
    <div id="smartphone-list">
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function () {
    function loadSmartphones() {
        $.ajax({
            url: "filter.php",
            method: "POST",
            data: $("#filter-form").serialize(),
            success: function (data) {
                $("#smartphone-list").html(data);
            }
        });
    }

    $(".filter-input").change(function () {
        loadSmartphones();
    });

    $("#reset-filters").click(function () {
        $("#filter-form")[0].reset();
        loadSmartphones();
    });

    loadSmartphones();
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>