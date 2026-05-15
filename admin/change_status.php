<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container'><h2>Ошибка: Неверный идентификатор заказа.</h2></div>";
    exit;
}
$order_id = (int)$_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];
    // Допустимые статусы
    $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($status, $allowed)) {
        echo "<div class='container'><div class='alert alert-danger'>Недопустимый статус.</div></div>";
    } else {
        $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $order_id);
        if ($stmt->execute()) {
            echo "<div class='container'><div class='alert alert-success'>Статус заказа успешно обновлен.</div></div>";
        } else {
            echo "<div class='container'><div class='alert alert-danger'>Ошибка при обновлении статуса.</div></div>";
        }
        $stmt->close();
    }
}

// Получаем текущий статус заказа
$stmt = $db->prepare("SELECT id, status FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo "<div class='container'><h2>Заказ не найден.</h2></div>";
    exit;
}

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4">Изменить статус заказа №<?= $order['id'] ?></h1>
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="status" class="form-label">Статус заказа:</label>
                    <select class="form-select" id="status" name="status">
                        <option value="pending" <?= ($order['status'] == 'pending') ? 'selected' : '' ?>>Ожидание</option>
                        <option value="processing" <?= ($order['status'] == 'processing') ? 'selected' : '' ?>>В обработке</option>
                        <option value="shipped" <?= ($order['status'] == 'shipped') ? 'selected' : '' ?>>Отправлен</option>
                        <option value="delivered" <?= ($order['status'] == 'delivered') ? 'selected' : '' ?>>Доставлен</option>
                        <option value="cancelled" <?= ($order['status'] == 'cancelled') ? 'selected' : '' ?>>Отменён</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Изменить статус</button>
            </form>
        </div>
    </div>
    
    <!-- Нижняя панель навигации -->
    <div class="d-flex justify-content-start mt-4">
        <a href="manage_orders.php" class="btn btn-bottom me-2">Назад к заказам</a>
        <a href="index.php" class="btn btn-bottom">Админка</a>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
