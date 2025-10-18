<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container'><h2>Ошибка: Неверный идентификатор заказа.</h2></div>";
    exit;
}
$order_id = (int)$_GET['id'];

// Обработка обновления данных доставки
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $payment_method = $_POST['payment_method'];

    // Обновляем данные доставки в таблице order_details
    $stmt = $db->prepare("UPDATE order_details SET full_name = ?, phone = ?, address = ?, payment_method = ? WHERE order_id = ?");
    $stmt->bind_param("ssssi", $full_name, $phone, $address, $payment_method, $order_id);
    if ($stmt->execute()) {
        echo "<div class='container'><div class='alert alert-success'>Заказ успешно обновлен.</div></div>";
    } else {
        echo "<div class='container'><div class='alert alert-danger'>Ошибка при обновлении заказа.</div></div>";
    }
    $stmt->close();
}

// Получаем текущие данные заказа
$stmt = $db->prepare("SELECT o.id, o.total_price, o.status, o.created_at, d.full_name, d.phone, d.address, d.payment_method
                      FROM orders o
                      LEFT JOIN order_details d ON o.id = d.order_id
                      WHERE o.id = ?");
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
    <h1>Редактировать заказ №<?= $order['id'] ?></h1>
    <form method="POST" class="mb-4">
        <div class="mb-3">
            <label for="full_name" class="form-label">Получатель (ФИО):</label>
            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($order['full_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="phone" class="form-label">Телефон:</label>
            <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($order['phone']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="address" class="form-label">Адрес:</label>
            <textarea class="form-control" id="address" name="address" required><?= htmlspecialchars($order['address']) ?></textarea>
        </div>
        <div class="mb-3">
            <label for="payment_method" class="form-label">Метод оплаты:</label>
            <select class="form-select" id="payment_method" name="payment_method">
                <option value="cash" <?= ($order['payment_method']=='cash') ? 'selected' : '' ?>>Наличные</option>
                <option value="card" <?= ($order['payment_method']=='card') ? 'selected' : '' ?>>Карта</option>
                <option value="online" <?= ($order['payment_method']=='online') ? 'selected' : '' ?>>Онлайн</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
    </form>
    
    <!-- Нижняя панель навигации -->
    <div class="d-flex justify-content-start mt-4">
        <a href="manage_orders.php" class="btn btn-bottom me-2">Назад к заказам</a>
        <a href="index.php" class="btn btn-bottom">Админка</a>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
