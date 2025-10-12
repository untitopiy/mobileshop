<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

// Проверка параметра id смартфона
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container'><h2>Ошибка: Неверный идентификатор смартфона.</h2></div>";
    exit;
}
$smartphone_id = (int)$_GET['id'];

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Основные данные
    $name        = trim($_POST['name']);
    $brand       = trim($_POST['brand']);
    $model       = trim($_POST['model']);
    $price       = floatval($_POST['price']);
    $stock       = intval($_POST['stock']);
    $description = trim($_POST['description']);
    
    // Характеристики
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
    
    // Начинаем транзакцию
    $db->begin_transaction();
    try {
        // Обновляем таблицу smartphones
        $stmt = $db->prepare("UPDATE smartphones SET name = ?, brand = ?, model = ?, price = ?, stock = ?, description = ? WHERE id = ?");
        $stmt->bind_param("sssdisi", $name, $brand, $model, $price, $stock, $description, $smartphone_id);
        $stmt->execute();
        $stmt->close();
        
        // Обновляем или вставляем характеристики
        $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM smartphone_specs WHERE smartphone_id = ?");
        $stmt->bind_param("i", $smartphone_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($res['cnt'] > 0) {
            $stmt = $db->prepare("UPDATE smartphone_specs SET screen_size = ?, resolution = ?, processor = ?, ram = ?, storage = ?, battery_capacity = ?, camera_main = ?, camera_front = ?, os = ?, color = ?, weight = ? WHERE smartphone_id = ?");
            $stmt->bind_param("dssiiissssdi", $screen_size, $resolution, $processor, $ram, $storage, $battery_capacity, $camera_main, $camera_front, $os, $color, $weight, $smartphone_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $db->prepare("INSERT INTO smartphone_specs (smartphone_id, screen_size, resolution, processor, ram, storage, battery_capacity, camera_main, camera_front, os, color, weight) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("idssiiissssd", $smartphone_id, $screen_size, $resolution, $processor, $ram, $storage, $battery_capacity, $camera_main, $camera_front, $os, $color, $weight);
            $stmt->execute();
            $stmt->close();
        }
        
        // Обработка загрузки изображения, если файл выбран
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Проверяем тип и размер файла
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 2 * 1024 * 1024; // 2 MB
            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                throw new Exception("Неверный формат изображения. Разрешены JPG, PNG, GIF.");
            }
            if ($_FILES['image']['size'] > $maxSize) {
                throw new Exception("Размер изображения не должен превышать 2 МБ.");
            }
            
            // Генерируем новое имя файла и сохраняем
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
            
            // Формируем путь для записи в БД с префиксом "uploads/"
            $image_path = "uploads/" . $newName;
            
            // Проверяем, существует ли уже запись в smartphone_images для этого смартфона
            $stmt = $db->prepare("SELECT id, image_url FROM smartphone_images WHERE smartphone_id = ? LIMIT 1");
            $stmt->bind_param("i", $smartphone_id);
            $stmt->execute();
            $imageRecord = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($imageRecord) {
                // Опционально: можно удалить старый файл, если требуется
                $oldPath = __DIR__ . '/../uploads/' . $imageRecord['image_url'];
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
                // Обновляем запись, сохраняем полный путь
                $stmt = $db->prepare("UPDATE smartphone_images SET image_url = ? WHERE id = ?");
                $stmt->bind_param("si", $image_path, $imageRecord['id']);
                $stmt->execute();
                $stmt->close();
            } else {
                // Вставляем новую запись, сохраняем полный путь
                $stmt = $db->prepare("INSERT INTO smartphone_images (smartphone_id, image_url) VALUES (?, ?)");
                $stmt->bind_param("is", $smartphone_id, $image_path);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $db->commit();
        echo "<div class='container'><div class='alert alert-success'>Смартфон успешно обновлен.</div></div>";
    } catch (Exception $e) {
        $db->rollback();
        echo "<div class='container'><div class='alert alert-danger'>Ошибка при обновлении смартфона: " . htmlspecialchars($e->getMessage()) . "</div></div>";
    }
}

// Получаем данные смартфона и его характеристики
$stmt = $db->prepare("SELECT s.id, s.name, s.brand, s.model, s.price, s.stock, s.description, 
                           sp.screen_size, sp.resolution, sp.processor, sp.ram, sp.storage, sp.battery_capacity, 
                           sp.camera_main, sp.camera_front, sp.os, sp.color, sp.weight
                      FROM smartphones s
                      LEFT JOIN smartphone_specs sp ON s.id = sp.smartphone_id
                      WHERE s.id = ?");
$stmt->bind_param("i", $smartphone_id);
$stmt->execute();
$smartphone = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$smartphone) {
    echo "<div class='container'><h2>Смартфон не найден.</h2></div>";
    exit;
}

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container my-5">
    <h1>Редактировать смартфон №<?= $smartphone['id'] ?></h1>
    <form method="POST" enctype="multipart/form-data" class="mb-4">
        <!-- Основные данные -->
        <div class="mb-3">
            <label for="name" class="form-label">Название:</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($smartphone['name']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="brand" class="form-label">Бренд:</label>
            <input type="text" class="form-control" id="brand" name="brand" value="<?= htmlspecialchars($smartphone['brand']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="model" class="form-label">Модель:</label>
            <input type="text" class="form-control" id="model" name="model" value="<?= htmlspecialchars($smartphone['model']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="price" class="form-label">Цена (руб.):</label>
            <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?= htmlspecialchars($smartphone['price']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="stock" class="form-label">Количество на складе:</label>
            <input type="number" class="form-control" id="stock" name="stock" value="<?= htmlspecialchars($smartphone['stock']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Описание:</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($smartphone['description']) ?></textarea>
        </div>
        
        <!-- Отображение текущего изображения -->
        <div class="mb-3">
            <label class="form-label">Текущее изображение:</label>
            <?php
            // Получаем изображение из smartphone_images
            $stmt = $db->prepare("SELECT image_url FROM smartphone_images WHERE smartphone_id = ? LIMIT 1");
            $stmt->bind_param("i", $smartphone_id);
            $stmt->execute();
            $imageData = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($imageData && !empty($imageData['image_url'])):
                // Формируем URL, используя $base_url и значение из БД, которое уже содержит "uploads/"
                $imgSrc = $base_url . htmlspecialchars($imageData['image_url']);
            ?>
                <div><img src="<?= $imgSrc ?>" alt="Изображение смартфона" style="max-width:200px; height:auto;"></div>
            <?php else: ?>
                <p>Изображение не загружено.</p>
            <?php endif; ?>
        </div>
        
        <!-- Поле для загрузки нового изображения -->
        <div class="mb-3">
            <label for="image" class="form-label">Загрузить новое изображение:</label>
            <input type="file" class="form-control" id="image" name="image" accept="image/*">
        </div>
        
        <!-- Характеристики -->
        <h4>Характеристики</h4>
        <div class="mb-3">
            <label for="screen_size" class="form-label">Размер экрана (дюймы):</label>
            <input type="number" step="0.1" class="form-control" id="screen_size" name="screen_size" value="<?= htmlspecialchars($smartphone['screen_size'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="resolution" class="form-label">Разрешение экрана:</label>
            <input type="text" class="form-control" id="resolution" name="resolution" value="<?= htmlspecialchars($smartphone['resolution'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="processor" class="form-label">Процессор:</label>
            <input type="text" class="form-control" id="processor" name="processor" value="<?= htmlspecialchars($smartphone['processor'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="ram" class="form-label">ОЗУ (ГБ):</label>
            <input type="number" class="form-control" id="ram" name="ram" value="<?= htmlspecialchars($smartphone['ram'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="storage" class="form-label">Объем памяти (ГБ):</label>
            <input type="number" class="form-control" id="storage" name="storage" value="<?= htmlspecialchars($smartphone['storage'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="battery_capacity" class="form-label">Емкость батареи (мАч):</label>
            <input type="number" class="form-control" id="battery_capacity" name="battery_capacity" value="<?= htmlspecialchars($smartphone['battery_capacity'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="camera_main" class="form-label">Основная камера:</label>
            <input type="text" class="form-control" id="camera_main" name="camera_main" value="<?= htmlspecialchars($smartphone['camera_main'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="camera_front" class="form-label">Фронтальная камера:</label>
            <input type="text" class="form-control" id="camera_front" name="camera_front" value="<?= htmlspecialchars($smartphone['camera_front'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="os" class="form-label">Операционная система:</label>
            <input type="text" class="form-control" id="os" name="os" value="<?= htmlspecialchars($smartphone['os'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="color" class="form-label">Цвет:</label>
            <input type="text" class="form-control" id="color" name="color" value="<?= htmlspecialchars($smartphone['color'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="weight" class="form-label">Вес (г):</label>
            <input type="number" step="0.1" class="form-control" id="weight" name="weight" value="<?= htmlspecialchars($smartphone['weight'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
    </form>
    
    <!-- Нижняя панель навигации -->
    <div class="d-flex justify-content-start mt-4">
        <a href="manage_products.php" class="btn btn-bottom me-2">Назад к товарам</a>
        <a href="index.php" class="btn btn-bottom">Админка</a>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
