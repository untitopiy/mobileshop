<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Проверка авторизации
if (!isset($_SESSION['id'])) {
    header('Location: ../pages/auth.php');
    exit;
}

$user_id = $_SESSION['id'];

// Получаем данные продавца
$seller_check = $db->prepare("SELECT id, shop_name, is_active FROM sellers WHERE user_id = ?");
$seller_check->bind_param('i', $user_id);
$seller_check->execute();
$seller_res = $seller_check->get_result();
$current_seller = $seller_res->fetch_assoc();

if (!$current_seller) {
    die("Доступ запрещен. Вы не зарегистрированы как продавец.");
}

if ($current_seller['is_active'] != 1) {
    die("Ваш магазин не активен.");
}

$seller_id = $current_seller['id'];

// Получаем товары продавца с категориями
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.seller_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$products = $stmt->get_result();

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-fluid my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1>Мой ассортимент</h1>
            <p class="text-muted mb-0">Магазин: <strong><?= htmlspecialchars($current_seller['shop_name']) ?></strong></p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary me-2">
                <i class="fas fa-plus"></i> Добавить товар
            </a>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Назад
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php if ($products->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Фото</th>
                                <th>Название</th>
                                <th>Категория</th>
                                <th>Цена</th>
                                <th>Кол-во</th>
                                <th>Статус</th>
                                <th>Дата добавления</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product = $products->fetch_assoc()): 
                                // Получаем главное фото
                                $img_stmt = $db->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1");
                                $img_stmt->bind_param('i', $product['id']);
                                $img_stmt->execute();
                                $img_result = $img_stmt->get_result();
                                $image = $img_result->fetch_assoc();
                                $img_url = $image ? $image['image_url'] : 'assets/default-product.png';
                                
                                // Определяем класс статуса
                                $status_class = [
                                    'draft' => 'badge bg-secondary',
                                    'pending' => 'badge bg-warning text-dark',
                                    'active' => 'badge bg-success',
                                    'inactive' => 'badge bg-danger',
                                    'rejected' => 'badge bg-dark'
                                ][$product['status']] ?? 'badge bg-secondary';
                                
                                $status_text = [
                                    'draft' => 'Черновик',
                                    'pending' => 'На модерации',
                                    'active' => 'Активен',
                                    'inactive' => 'Неактивен',
                                    'rejected' => 'Отклонен'
                                ][$product['status']] ?? $product['status'];
                            ?>
                            <tr>
                                <td><?= $product['id'] ?></td>
                                <td>
                                    <img src="../<?= htmlspecialchars($img_url) ?>" alt="<?= htmlspecialchars($product['name']) ?>" 
                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($product['name']) ?></strong><br>
                                    <small class="text-muted">SKU: <?= htmlspecialchars($product['sku'] ?? 'Нет') ?></small>
                                </td>
                                <td><?= htmlspecialchars($product['category_name'] ?? 'Без категории') ?></td>
                                <td><?= number_format($product['price'], 2, '.', ' ') ?> BYN</td>
                                <td><?= $product['quantity'] ?></td>
                                <td><span class="<?= $status_class ?>"><?= $status_text ?></span></td>
                                <td><?= date('d.m.Y', strtotime($product['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../product.php?id=<?= $product['id'] ?>" class="btn btn-outline-primary" target="_blank" title="Просмотр">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn btn-outline-warning" title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" onclick="deleteProduct(<?= $product['id'] ?>)" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">У вас пока нет товаров</h4>
                    <p class="text-muted">Начните с добавления первого товара</p>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Добавить товар
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function deleteProduct(id) {
    if (confirm('Вы уверены, что хотите удалить этот товар?')) {
        window.location.href = 'delete_product.php?id=' + id;
    }
}
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>