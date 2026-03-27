<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/functions.php';

// Обработка добавления категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $slug = !empty($_POST['slug']) ? trim($_POST['slug']) : preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
    $slug = trim($slug, '-');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $description = trim($_POST['description']);
    $sort_order = (int)$_POST['sort_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Проверка уникальности slug
    $check = $db->prepare("SELECT id FROM categories WHERE slug = ?");
    $check->bind_param('s', $slug);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Категория с таким URL уже существует";
    } else {
        $stmt = $db->prepare("
            INSERT INTO categories (parent_id, name, slug, description, sort_order, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param('issiii', $parent_id, $name, $slug, $description, $sort_order, $is_active);
        $stmt->execute();
        $_SESSION['success'] = "Категория успешно добавлена";
    }
    header("Location: manage_categories.php");
    exit;
}

// Обработка редактирования категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $slug = !empty($_POST['slug']) ? trim($_POST['slug']) : preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
    $slug = trim($slug, '-');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $description = trim($_POST['description']);
    $sort_order = (int)$_POST['sort_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Проверка уникальности slug (исключая текущую категорию)
    $check = $db->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
    $check->bind_param('si', $slug, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Категория с таким URL уже существует";
    } else {
        $stmt = $db->prepare("
            UPDATE categories SET
                parent_id = ?, name = ?, slug = ?, description = ?, sort_order = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->bind_param('issiiii', $parent_id, $name, $slug, $description, $sort_order, $is_active, $id);
        $stmt->execute();
        $_SESSION['success'] = "Категория успешно обновлена";
    }
    header("Location: manage_categories.php");
    exit;
}

// Обработка удаления категории
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Проверяем, есть ли подкатегории
    $check = $db->prepare("SELECT id FROM categories WHERE parent_id = ? LIMIT 1");
    $check->bind_param('i', $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Нельзя удалить категорию, у которой есть подкатегории";
    } else {
        // Проверяем, есть ли товары в этой категории
        $check_products = $db->prepare("SELECT id FROM products WHERE category_id = ? LIMIT 1");
        $check_products->bind_param('i', $id);
        $check_products->execute();
        if ($check_products->get_result()->num_rows > 0) {
            $_SESSION['error'] = "Нельзя удалить категорию, в которой есть товары";
        } else {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $_SESSION['success'] = "Категория успешно удалена";
        }
    }
    header("Location: manage_categories.php");
    exit;
}

// Обработка изменения статуса
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $db->query("UPDATE categories SET is_active = NOT is_active WHERE id = $id");
    $_SESSION['success'] = "Статус категории изменен";
    header("Location: manage_categories.php");
    exit;
}

// Получаем все категории для дерева
$categories = $db->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM products WHERE category_id = c.id) as products_count,
           (SELECT COUNT(*) FROM categories WHERE parent_id = c.id) as subcategories_count
    FROM categories c
    ORDER BY c.sort_order, c.name
");

// Получаем категории для выпадающего списка (только родительские)
$parent_categories = $db->query("
    SELECT id, name FROM categories 
    WHERE parent_id IS NULL 
    ORDER BY sort_order, name
");

// Получаем данные для редактирования, если есть GET параметр edit
$edit_category = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_query = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $edit_query->bind_param('i', $id);
    $edit_query->execute();
    $edit_category = $edit_query->get_result()->fetch_assoc();
}
?>

<div class="container-fluid admin-container my-5">
    <h1 class="mb-4">Управление категориями</h1>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Форма добавления/редактирования категории -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?= $edit_category ? 'Редактирование категории' : 'Добавление категории' ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php if ($edit_category): ?>
                            <input type="hidden" name="id" value="<?= $edit_category['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Название *</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?= $edit_category ? htmlspecialchars($edit_category['name']) : '' ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">URL (slug)</label>
                            <input type="text" name="slug" class="form-control" 
                                   value="<?= $edit_category ? htmlspecialchars($edit_category['slug']) : '' ?>"
                                   placeholder="Оставьте пустым для автоматической генерации">
                            <small class="text-muted">Будет использован в URL: /catalog.php?category=slug</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Родительская категория</label>
                            <select name="parent_id" class="form-select">
                                <option value="">— Корневая категория —</option>
                                <?php 
                                $parent_categories->data_seek(0);
                                while ($cat = $parent_categories->fetch_assoc()): 
                                ?>
                                    <option value="<?= $cat['id'] ?>" 
                                        <?= ($edit_category && $edit_category['parent_id'] == $cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Описание</label>
                            <textarea name="description" class="form-control" rows="3"><?= $edit_category ? htmlspecialchars($edit_category['description'] ?? '') : '' ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Порядок сортировки</label>
                            <input type="number" name="sort_order" class="form-control" 
                                   value="<?= $edit_category ? $edit_category['sort_order'] : '0' ?>">
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                                   <?= (!$edit_category || $edit_category['is_active']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">Активна</label>
                        </div>
                        
                        <button type="submit" name="<?= $edit_category ? 'edit_category' : 'add_category' ?>" 
                                class="btn btn-<?= $edit_category ? 'primary' : 'success' ?> w-100">
                            <?= $edit_category ? 'Сохранить изменения' : 'Добавить категорию' ?>
                        </button>
                        
                        <?php if ($edit_category): ?>
                            <a href="manage_categories.php" class="btn btn-secondary w-100 mt-2">
                                Отмена
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Список категорий -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Список категорий</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Название</th>
                                    <th>URL</th>
                                    <th>Родитель</th>
                                    <th>Товаров</th>
                                    <th>Подкатегорий</th>
                                    <th>Сортировка</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                function displayCategoryRow($cat, $db, $level = 0) {
                                    $prefix = str_repeat('— ', $level);
                                    ?>
                                    <tr>
                                        <td><?= $cat['id'] ?></td>
                                        <td>
                                            <?= $prefix ?><?= htmlspecialchars($cat['name']) ?>
                                        </td>
                                        <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                                        <td>
                                            <?php 
                                            if ($cat['parent_id']) {
                                                $parent = $db->query("SELECT name FROM categories WHERE id = {$cat['parent_id']}")->fetch_assoc();
                                                echo htmlspecialchars($parent['name'] ?? '');
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info"><?= $cat['products_count'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?= $cat['subcategories_count'] ?></span>
                                        </td>
                                        <td class="text-center"><?= $cat['sort_order'] ?></td>
                                        <td>
                                            <?php if ($cat['is_active']): ?>
                                                <span class="badge bg-success">Активна</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Неактивна</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="?edit=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-primary" title="Редактировать">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?toggle=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-warning" 
                                                   title="<?= $cat['is_active'] ? 'Деактивировать' : 'Активировать' ?>">
                                                    <i class="fas <?= $cat['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                                </a>
                                                <?php if ($cat['products_count'] == 0 && $cat['subcategories_count'] == 0): ?>
                                                    <a href="?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Удалить категорию?')" title="Удалить">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                    
                                    // Получаем подкатегории
                                    $subcats = $db->query("
                                        SELECT c.*, 
                                               (SELECT COUNT(*) FROM products WHERE category_id = c.id) as products_count,
                                               (SELECT COUNT(*) FROM categories WHERE parent_id = c.id) as subcategories_count
                                        FROM categories c
                                        WHERE parent_id = {$cat['id']}
                                        ORDER BY c.sort_order, c.name
                                    ");
                                    while ($subcat = $subcats->fetch_assoc()) {
                                        displayCategoryRow($subcat, $db, $level + 1);
                                    }
                                }
                                
                                // Выводим корневые категории
                                $categories->data_seek(0);
                                while ($cat = $categories->fetch_assoc()) {
                                    if ($cat['parent_id'] === null) {
                                        displayCategoryRow($cat, $db);
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.table td {
    vertical-align: middle;
}
.badge {
    font-size: 0.85em;
}
</style>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>