<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

// Проверка корректности параметра id смартфона
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container'><h2>Ошибка: Неверный идентификатор смартфона.</h2></div>";
    exit;
}
$smartphone_id = (int)$_GET['id'];

// Получаем базовую информацию о смартфоне
$stmt = $db->prepare("SELECT id, name, brand, model FROM smartphones WHERE id = ?");
$stmt->bind_param("i", $smartphone_id);
$stmt->execute();
$smartphone = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$smartphone) {
    echo "<div class='container'><h2>Смартфон не найден.</h2></div>";
    exit;
}

// Получаем технические характеристики смартфона
$stmt = $db->prepare("SELECT screen_size, resolution, processor, ram, storage, battery_capacity, camera_main, camera_front, os, color, weight FROM smartphone_specs WHERE smartphone_id = ?");
$stmt->bind_param("i", $smartphone_id);
$stmt->execute();
$specs = $stmt->get_result()->fetch_assoc();
$stmt->close();

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container my-5">

    
    <h1>Характеристики смартфона №<?= $smartphone['id'] ?>:<br>
        <?= htmlspecialchars($smartphone['brand'] . ' ' . $smartphone['name'] . ' ' . $smartphone['model']) ?>
    </h1>
    
    <?php if ($specs): ?>
        <div class="card shadow-sm mb-4 mt-4">
            <div class="card-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th>Размер экрана</th>
                            <td><?= htmlspecialchars($specs['screen_size']) ?> дюймов</td>
                        </tr>
                        <tr>
                            <th>Разрешение экрана</th>
                            <td><?= htmlspecialchars($specs['resolution']) ?></td>
                        </tr>
                        <tr>
                            <th>Процессор</th>
                            <td><?= htmlspecialchars($specs['processor']) ?></td>
                        </tr>
                        <tr>
                            <th>ОЗУ</th>
                            <td><?= htmlspecialchars($specs['ram']) ?> ГБ</td>
                        </tr>
                        <tr>
                            <th>Объем памяти</th>
                            <td><?= htmlspecialchars($specs['storage']) ?> ГБ</td>
                        </tr>
                        <tr>
                            <th>Емкость батареи</th>
                            <td><?= htmlspecialchars($specs['battery_capacity']) ?> мАч</td>
                        </tr>
                        <tr>
                            <th>Основная камера</th>
                            <td><?= htmlspecialchars($specs['camera_main']) ?></td>
                        </tr>
                        <tr>
                            <th>Фронтальная камера</th>
                            <td><?= htmlspecialchars($specs['camera_front']) ?></td>
                        </tr>
                        <tr>
                            <th>Операционная система</th>
                            <td><?= htmlspecialchars($specs['os']) ?></td>
                        </tr>
                        <tr>
                            <th>Цвет</th>
                            <td><?= htmlspecialchars($specs['color']) ?></td>
                        </tr>
                        <tr>
                            <th>Вес</th>
                            <td><?= htmlspecialchars($specs['weight']) ?> г</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info mt-4">Характеристики для данного смартфона не заданы.</div>
    <?php endif; ?>
    
    <!-- Нижняя панель навигации -->
    <div class="d-flex justify-content-start mt-4">
        <a href="manage_products.php" class="btn btn-bottom me-2">Назад к товарам</a>
        <a href="index.php" class="btn btn-bottom">Админка</a>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
