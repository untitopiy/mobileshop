<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container'><h2>Ошибка: Неверный идентификатор аксессуара.</h2></div>";
    exit;
}
$accessory_id = (int)$_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']);
    $brand       = trim($_POST['brand']);
    $model       = trim($_POST['model']);
    $price       = floatval($_POST['price']);
    $stock       = intval($_POST['stock']);
    $description = trim($_POST['description']);
    
    // Обновляем данные аксессуара
    $stmt = $db->prepare("UPDATE accessories SET name = ?, brand = ?, model = ?, price = ?, stock = ?, description = ? WHERE id = ?");
    $stmt->bind_param("sssdisi", $name, $brand, $model, $price, $stock, $description, $accessory_id);
    if ($stmt->execute()) {
        echo "<div class='container'><div class='alert alert-success'>Аксессуар успешно обновлен.</div></div>";
    } else {
        echo "<div class='container'><div class='alert alert-danger'>Ошибка при обновлении аксессуара.</div></div>";
    }
    $stmt->close();
}

// Получаем текущие данные аксессуара
$stmt = $db->prepare("SELECT id, name, brand, model, price, stock, description FROM accessories WHERE id = ?");
$stmt->bind_param("i", $accessory_id);
$stmt->execute();
$accessory = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$accessory) {
    echo "<div class='container'><h2>Аксессуар не найден.</h2></div>";
    exit;
}

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container my-5">
    <h1>Редактировать аксессуар №<?= $accessory['id'] ?></h1>
    <form method="POST" class="mb-4">
        <div class="mb-3">
            <label for="name" class="form-label">Название:</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($accessory['name']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="brand" class="form-label">Бренд:</label>
            <input type="text" class="form-control" id="brand" name="brand" value="<?= htmlspecialchars($accessory['brand']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="model" class="form-label">Модель:</label>
            <input type="text" class="form-control" id="model" name="model" value="<?= htmlspecialchars($accessory['model']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="price" class="form-label">Цена (руб.):</label>
            <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?= htmlspecialchars($accessory['price']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="stock" class="form-label">Количество на складе:</label>
            <input type="number" class="form-control" id="stock" name="stock" value="<?= htmlspecialchars($accessory['stock']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Описание:</label>
            <textarea class="form-control" id="description" name="description" rows="4" required><?= htmlspecialchars($accessory['description']) ?></textarea>
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
