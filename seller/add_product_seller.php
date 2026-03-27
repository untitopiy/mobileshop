<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ПРОВЕРКА ДОСТУПА (Только для авторизованных продавцов)
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isset($_SESSION['id'])) {
    header('Location: ../pages/auth.php');
    exit;
}

$user_id = $_SESSION['id'];
$seller_check = $db->prepare("SELECT id, shop_name FROM sellers WHERE user_id = ? AND is_active = 1");
$seller_check->bind_param('i', $user_id);
$seller_check->execute();
$seller_res = $seller_check->get_result();
$current_seller = $seller_res->fetch_assoc();

if (!$current_seller) {
    die("Доступ запрещен. Вы не являетесь активным продавцом.");
}

$seller_id = $current_seller['id'];

// 2. ПОЛУЧЕНИЕ ДАННЫХ ДЛЯ ФОРМЫ
$categories = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");

// Собираем атрибуты для JS (как в вашем примере)
$all_attributes = $db->query("
    SELECT a.id, a.name, a.slug, a.type, a.unit, ca.category_id, ca.is_required
    FROM attributes a
    INNER JOIN category_attributes ca ON ca.attribute_id = a.id
    ORDER BY ca.sort_order
");

$attributes_by_category = [];
while ($attr = $all_attributes->fetch_assoc()) {
    $attributes_by_category[$attr['category_id']][] = $attr;
}

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container mt-5 admin-container">
    <div class="card shadow">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Добавить товар от «<?= htmlspecialchars($current_seller['shop_name']) ?>»</h4>
            <a href="dashboard.php" class="btn btn-light btn-sm">Назад в панель</a>
        </div>
        <div class="card-body">
            <form action="save_product_seller.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="seller_id" value="<?= $seller_id ?>">

                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Название товара *</label>
                            <input type="text" name="name" class="form-control" placeholder="Например: Смартфон Apple iPhone 15" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Бренд *</label>
                                <input type="text" name="brand" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Модель</label>
                                <input type="text" name="model" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Описание</label>
                            <textarea name="description" class="form-control" rows="5" placeholder="Подробное описание товара..."></textarea>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card bg-light p-3 mb-3">
                            <div class="mb-3">
                                <label class="form-label text-primary font-weight-bold">Категория *</label>
                                <select name="category_id" id="category_id" class="form-select" required>
                                    <option value="">-- Выберите --</option>
                                    <?php while($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Цена (BYN) *</label>
                                <input type="number" name="price" step="0.01" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Количество *</label>
                                <input type="number" name="quantity" class="form-control" value="1" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Главное изображение</label>
                            <input type="file" name="main_image" class="form-control" accept="image/*" required>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 border rounded bg-white">
                    <h5 class="border-bottom pb-2 mb-3">Характеристики категории</h5>
                    <div id="dynamic-attributes" class="row">
                        <div class="col-12 text-muted">Выберите категорию выше, чтобы заполнить спецификации.</div>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-success btn-lg px-5">Опубликовать товар</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Передаем массив атрибутов из PHP в JS
const attributesByCategory = <?= json_encode($attributes_by_category) ?>;

document.getElementById('category_id').addEventListener('change', function() {
    const catId = this.value;
    const container = document.getElementById('dynamic-attributes');
    container.innerHTML = '';

    if (catId && attributesByCategory[catId]) {
        attributesByCategory[catId].forEach(attr => {
            const col = document.createElement('div');
            col.className = 'col-md-6 mb-3';
            
            let inputHtml = '';
            if (attr.type === 'number') {
                inputHtml = `<input type="number" name="attrs[${attr.id}]" class="form-control" ${attr.is_required ? 'required' : ''}>`;
            } else {
                inputHtml = `<input type="text" name="attrs[${attr.id}]" class="form-control" ${attr.is_required ? 'required' : ''}>`;
            }

            col.innerHTML = `
                <label class="form-label small text-muted mb-1">${attr.name} ${attr.unit ? '('+attr.unit+')' : ''}</label>
                ${inputHtml}
            `;
            container.appendChild(col);
        });
    } else {
        container.innerHTML = '<div class="col-12 text-muted">Для этой категории нет дополнительных характеристик.</div>';
    }
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>  