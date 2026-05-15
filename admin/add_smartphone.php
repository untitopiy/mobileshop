<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Считываем основные данные
    $name        = trim($_POST['name']);
    $brand       = trim($_POST['brand']);
    $model       = trim($_POST['model']);
    $price       = floatval($_POST['price']);
    $stock       = intval($_POST['stock']);
    $description = trim($_POST['description']);
    
    // Считываем характеристики
    $screen_size      = !empty($_POST['screen_size']) ? floatval($_POST['screen_size']) : null;
    $resolution       = trim($_POST['resolution'] ?? '');
    $processor        = trim($_POST['processor'] ?? '');
    $ram              = !empty($_POST['ram']) ? intval($_POST['ram']) : null;
    $storage          = !empty($_POST['storage']) ? intval($_POST['storage']) : null;
    $battery_capacity = !empty($_POST['battery_capacity']) ? intval($_POST['battery_capacity']) : null;
    $camera_main      = trim($_POST['camera_main'] ?? '');
    $camera_front     = trim($_POST['camera_front'] ?? '');
    $os               = trim($_POST['os'] ?? '');
    $color            = trim($_POST['color'] ?? '');
    $weight           = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    
    $db->begin_transaction();
    try {
        // Вставляем основной смартфон
        $stmt = $db->prepare("INSERT INTO smartphones (name, brand, model, price, stock, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdis", $name, $brand, $model, $price, $stock, $description);
        $stmt->execute();
        $smartphone_id = $stmt->insert_id;
        $stmt->close();
        
        // Вставляем характеристики
        $stmt = $db->prepare("INSERT INTO smartphone_specs (smartphone_id, screen_size, resolution, processor, ram, storage, battery_capacity, camera_main, camera_front, os, color, weight) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idssiiissssd", $smartphone_id, $screen_size, $resolution, $processor, $ram, $storage, $battery_capacity, $camera_main, $camera_front, $os, $color, $weight);
        $stmt->execute();
        $stmt->close();
        
        // Обработка загрузки изображения, если выбран файл
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 2 * 1024 * 1024; // 2 МБ
            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                throw new Exception("Неверный формат изображения. Разрешены JPG, PNG, GIF.");
            }
            if ($_FILES['image']['size'] > $maxSize) {
                throw new Exception("Размер изображения не должен превышать 2 МБ.");
            }
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $newName = uniqid('img_', true) . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $uploadPath = $uploadDir . $newName;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                throw new Exception("Ошибка при загрузке изображения.");
            }
            
            // Формируем путь для записи в БД: добавляем префикс "uploads/"
            $image_url = "uploads/" . $newName;
            
            // Вставляем запись в smartphone_images
            $stmt = $db->prepare("INSERT INTO smartphone_images (smartphone_id, image_url) VALUES (?, ?)");
            $stmt->bind_param("is", $smartphone_id, $image_url);
            $stmt->execute();
            $stmt->close();
        }
        
        $db->commit();
        header("Location: manage_products.php?message=smartphone_added");
        exit;
    } catch (Exception $e) {
        $db->rollback();
        echo "<div class='container'><div class='alert alert-danger'>Ошибка при добавлении смартфона: " . htmlspecialchars($e->getMessage()) . "</div></div>";
    }
}

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container my-5">

    <h1>Добавить смартфон</h1>
    <form method="POST" enctype="multipart/form-data" class="mb-4">
        <!-- Основные данные -->
        <div class="mb-3">
            <label for="name" class="form-label">Название:</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>
        <div class="mb-3">
            <label for="brand" class="form-label">Бренд:</label>
            <input type="text" class="form-control" id="brand" name="brand" required>
        </div>
        <div class="mb-3">
            <label for="model" class="form-label">Модель:</label>
            <input type="text" class="form-control" id="model" name="model" required>
        </div>
        <div class="mb-3">
            <label for="price" class="form-label">Цена (руб.):</label>
            <input type="number" step="0.01" class="form-control" id="price" name="price" required>
        </div>
        <div class="mb-3">
            <label for="stock" class="form-label">Количество на складе:</label>
            <input type="number" class="form-control" id="stock" name="stock" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Описание:</label>
            <textarea class="form-control" id="description" name="description" rows="4"></textarea>
        </div>
        
        <!-- Поле для загрузки изображения -->
        <div class="mb-3">
            <label for="image" class="form-label">Изображение смартфона:</label>
            <input type="file" class="form-control" id="image" name="image" accept="image/*">
        </div>
        
        <!-- Характеристики -->
        <h4>Характеристики</h4>
        <div class="mb-3">
            <label for="screen_size" class="form-label">Размер экрана (дюймы):</label>
            <input type="number" step="0.1" class="form-control" id="screen_size" name="screen_size">
        </div>
        <div class="mb-3">
            <label for="resolution" class="form-label">Разрешение экрана:</label>
            <input type="text" class="form-control" id="resolution" name="resolution">
        </div>
        <div class="mb-3">
            <label for="processor" class="form-label">Процессор:</label>
            <input type="text" class="form-control" id="processor" name="processor">
        </div>
        <div class="mb-3">
            <label for="ram" class="form-label">ОЗУ (ГБ):</label>
            <input type="number" class="form-control" id="ram" name="ram">
        </div>
        <div class="mb-3">
            <label for="storage" class="form-label">Объем памяти (ГБ):</label>
            <input type="number" class="form-control" id="storage" name="storage">
        </div>
        <div class="mb-3">
            <label for="battery_capacity" class="form-label">Емкость батареи (мАч):</label>
            <input type="number" class="form-control" id="battery_capacity" name="battery_capacity">
        </div>
        <div class="mb-3">
            <label for="camera_main" class="form-label">Основная камера:</label>
            <input type="text" class="form-control" id="camera_main" name="camera_main">
        </div>
        <div class="mb-3">
            <label for="camera_front" class="form-label">Фронтальная камера:</label>
            <input type="text" class="form-control" id="camera_front" name="camera_front">
        </div>
        <div class="mb-3">
            <label for="os" class="form-label">Операционная система:</label>
            <input type="text" class="form-control" id="os" name="os">
        </div>
        <div class="mb-3">
            <label for="color" class="form-label">Цвет:</label>
            <input type="text" class="form-control" id="color" name="color">
        </div>
        <div class="mb-3">
            <label for="weight" class="form-label">Вес (г):</label>
            <input type="number" step="0.1" class="form-control" id="weight" name="weight">
        </div>
        <button type="submit" class="btn btn-primary">Добавить смартфон</button>
    </form>
    
    <!-- Нижняя панель навигации -->
    <div class="d-flex justify-content-start mt-4">
        <a href="manage_products.php" class="btn btn-bottom me-2">Назад к товарам</a>
        <a href="index.php" class="btn btn-bottom">Админка</a>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
