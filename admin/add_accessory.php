<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']);
    $brand       = trim($_POST['brand']);
    $model       = trim($_POST['model']);
    $price       = floatval($_POST['price']);
    $stock       = intval($_POST['stock']);
    $description = trim($_POST['description']);
    $category_id = intval($_POST['category_id']);

    $stmt = $db->prepare("INSERT INTO accessories (category_id, name, brand, model, price, stock, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssdis", $category_id, $name, $brand, $model, $price, $stock, $description);
    if ($stmt->execute()) {
         header("Location: manage_products.php?message=accessory_added");
         exit;
    } else {
         echo "<div class='container'><div class='alert alert-danger'>Ошибка при добавлении аксессуара: " . htmlspecialchars($stmt->error) . "</div></div>";
    }
    $stmt->close();
}

// Получаем список категорий для аксессуаров (предполагается, что для смартфонов используется id = 1)
$catQuery = $db->query("SELECT id, name FROM categories WHERE id <> 1 ORDER BY name");
?>

<?php require_once __DIR__ . '/../inc/header.php'; ?>
<div class="container my-5">

    
    <h1>Добавить аксессуар</h1>
    <form method="POST" class="mb-4">
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
         <div class="mb-3">
             <label for="category_id" class="form-label">Категория:</label>
             <select name="category_id" id="category_id" class="form-select" required>
                 <?php while($cat = $catQuery->fetch_assoc()): ?>
                     <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                 <?php endwhile; ?>
             </select>
         </div>
         <button type="submit" class="btn btn-primary">Добавить аксессуар</button>
    </form>
    
    <!-- Нижняя панель навигации -->
    <div class="d-flex justify-content-start mt-4">
         <a href="manage_products.php" class="btn btn-bottom me-2">Назад к товарам</a>
         <a href="index.php" class="btn btn-bottom">Админка</a>
    </div>
</div>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>
