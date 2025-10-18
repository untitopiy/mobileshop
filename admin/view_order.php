<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

// Функции визуального перевода
function translateStatus($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-secondary">Ожидание</span>';
        case 'processing':
            return '<span class="badge bg-warning text-dark">В обработке</span>';
        case 'shipped':
            return '<span class="badge bg-info">Отправлен</span>';
        case 'delivered':
            return '<span class="badge bg-success">Доставлен</span>';
        case 'cancelled':
            return '<span class="badge bg-danger">Отменён</span>';
        default:
            return $status;
    }
}

function translatePaymentMethod($method) {
    switch ($method) {
        case 'cash':
            return '<span class="badge bg-primary">Наличные</span>';
        case 'card':
            return '<span class="badge bg-info">Карта</span>';
        case 'online':
            return '<span class="badge bg-success">Онлайн</span>';
        default:
            return $method;
    }
}

function translateProductType($type) {
    switch ($type) {
        case 'smartphone':
            return '<span class="badge bg-dark">Смартфон</span>';
        case 'accessory':
            return '<span class="badge bg-secondary">Аксессуар</span>';
        default:
            return $type;
    }
}

// Обработка удаления товара из заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $del_product_type = $_POST['product_type'];
    $del_product_id   = (int)$_POST['product_id'];
    $del_order_id     = (int)$_POST['order_id'];
    
    $stmt = $db->prepare("DELETE FROM order_items WHERE order_id = ? AND product_type = ? AND product_id = ?");
    $stmt->bind_param("isi", $del_order_id, $del_product_type, $del_product_id);
    $stmt->execute();
    $stmt->close();
    
    // Перенаправляем для обновления страницы
    header("Location: view_order.php?id=" . $del_order_id);
    exit;
}

// Проверка идентификатора заказа
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container'><h2>Ошибка: Неверный идентификатор заказа.</h2></div>";
    exit;
}
$order_id = (int)$_GET['id'];

// Получение информации о заказе и деталях доставки
$stmt = $db->prepare("SELECT o.id, o.user_id, o.total_price, o.status, o.created_at, 
                           d.full_name, d.phone, d.address, d.payment_method 
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

// Получение списка товаров заказа
$stmt = $db->prepare("SELECT oi.quantity, oi.price, oi.product_type, oi.product_id,
       CASE 
            WHEN oi.product_type = 'smartphone' THEN s.name
            WHEN oi.product_type = 'accessory' THEN a.name
            ELSE 'Неизвестный товар'
       END AS product_name
FROM order_items oi
LEFT JOIN smartphones s ON (oi.product_type = 'smartphone' AND oi.product_id = s.id)
LEFT JOIN accessories a ON (oi.product_type = 'accessory' AND oi.product_id = a.id)
WHERE oi.order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result();
$stmt->close();

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container my-5">

    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Детали заказа №<?= $order['id'] ?></h1>
    </div>
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4 class="card-title mb-3">Информация о заказе</h4>
            <p><strong>Пользователь:</strong> <?= htmlspecialchars($order['full_name']) ?></p>
            <p><strong>Сумма:</strong> <?= number_format($order['total_price'], 0, ',', ' ') ?> руб.</p>
            <p><strong>Статус:</strong> <?= translateStatus($order['status']) ?></p>
            <p><strong>Дата создания:</strong> <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
            <?php if (!empty($order['phone'])): ?>
                <p><strong>Телефон:</strong> <?= htmlspecialchars($order['phone']) ?></p>
            <?php endif; ?>
            <?php if (!empty($order['address'])): ?>
                <p><strong>Адрес:</strong> <?= htmlspecialchars($order['address']) ?></p>
            <?php endif; ?>
            <?php if (!empty($order['payment_method'])): ?>
                <p><strong>Метод оплаты:</strong> <?= translatePaymentMethod($order['payment_method']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-body">
            <h4 class="card-title mb-3">Товары заказа</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Тип товара</th>
                            <th>Название</th>
                            <th>Количество</th>
                            <th>Цена за единицу</th>
                            <th>Сумма</th>
                            <th>Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($item = $order_items->fetch_assoc()): ?>
                            <tr>
                                <td><?= translateProductType($item['product_type']) ?></td>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td><?= number_format($item['price'], 0, ',', ' ') ?> руб.</td>
                                <td><?= number_format($item['price'] * $item['quantity'], 0, ',', ' ') ?> руб.</td>
                                <td>
                                    <!-- Форма для удаления товара из заказа -->
                                    <form method="POST" onsubmit="return confirm('Удалить этот товар из заказа?');">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <input type="hidden" name="product_type" value="<?= htmlspecialchars($item['product_type']) ?>">
                                        <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                        <button type="submit" name="delete_item" class="btn btn-bottom">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Нижняя панель маленьких кнопок (повтор для удобства) -->
    <div class="d-flex justify-content-start mt-4">
        <a href="manage_orders.php" class="btn btn-bottom me-2">Назад к заказам</a>
        <a href="index.php" class="btn btn-bottom">Админка</a>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
