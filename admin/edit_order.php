<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/functions.php';

// Проверка ID заказа
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_orders.php");
    exit;
}
$order_id = (int)$_GET['id'];

// Получаем информацию о заказе
$order_query = $db->prepare("
    SELECT 
        o.id,
        o.order_number,
        o.user_id,
        o.shipping_method_id,
        o.payment_method_id,
        o.notes,
        o.created_at,
        u.full_name as customer_name,
        u.email as customer_email,
        u.phone as customer_phone
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.id = ?
");
$order_query->bind_param('i', $order_id);
$order_query->execute();
$order = $order_query->get_result()->fetch_assoc();

if (!$order) {
    header("Location: manage_orders.php");
    exit;
}

// Получаем адрес доставки
$address_query = $db->prepare("
    SELECT id, full_name, phone, email, country, city, address, postal_code
    FROM order_addresses
    WHERE order_id = ? AND type = 'shipping'
");
$address_query->bind_param('i', $order_id);
$address_query->execute();
$address = $address_query->get_result()->fetch_assoc();

// Получаем способы доставки и оплаты
$shipping_methods = $db->query("SELECT id, name, price FROM shipping_methods WHERE is_active = 1 ORDER BY sort_order");
$payment_methods = $db->query("SELECT id, name FROM payment_methods WHERE is_active = 1 ORDER BY sort_order");

// Получаем пользователей для выбора
$users = $db->query("SELECT id, full_name, email FROM users ORDER BY full_name LIMIT 100");

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $user_id = (int)$_POST['user_id'];
    $shipping_method_id = !empty($_POST['shipping_method_id']) ? (int)$_POST['shipping_method_id'] : null;
    $payment_method_id = !empty($_POST['payment_method_id']) ? (int)$_POST['payment_method_id'] : null;
    $notes = trim($_POST['notes']);
    
    // Данные адреса
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $country = trim($_POST['country']);
    $city = trim($_POST['city']);
    $address_text = trim($_POST['address']);
    $postal_code = trim($_POST['postal_code']);
    
    $db->begin_transaction();
    
    try {
        // Обновление заказа
        $stmt = $db->prepare("
            UPDATE orders SET 
                user_id = ?, shipping_method_id = ?, payment_method_id = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->bind_param('iissi', $user_id, $shipping_method_id, $payment_method_id, $notes, $order_id);
        $stmt->execute();
        
        // Обновление или создание адреса
        if ($address) {
            $stmt = $db->prepare("
                UPDATE order_addresses SET 
                    full_name = ?, phone = ?, email = ?, country = ?, city = ?, address = ?, postal_code = ?
                WHERE id = ?
            ");
            $stmt->bind_param('sssssssi', $full_name, $phone, $email, $country, $city, $address_text, $postal_code, $address['id']);
            $stmt->execute();
        } else {
            $stmt = $db->prepare("
                INSERT INTO order_addresses (order_id, type, full_name, phone, email, country, city, address, postal_code)
                VALUES (?, 'shipping', ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('isssssss', $order_id, $full_name, $phone, $email, $country, $city, $address_text, $postal_code);
            $stmt->execute();
        }
        
        $db->commit();
        $_SESSION['success'] = "Заказ успешно обновлен";
        header("Location: view_order.php?id=$order_id");
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Ошибка при обновлении заказа: " . $e->getMessage();
    }
}
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Редактирование заказа <?= htmlspecialchars($order['order_number']) ?></h1>
        <a href="view_order.php?id=<?= $order_id ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Назад
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Основная информация</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Покупатель</label>
                                <select name="user_id" class="form-select" required>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                        <option value="<?= $user['id'] ?>" <?= $user['id'] == $order['user_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($user['full_name'] ?: $user['email']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Способ доставки</label>
                                <select name="shipping_method_id" class="form-select">
                                    <option value="">— Не выбрано —</option>
                                    <?php while ($method = $shipping_methods->fetch_assoc()): ?>
                                        <option value="<?= $method['id'] ?>" <?= $method['id'] == $order['shipping_method_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($method['name']) ?> (<?= formatPrice($method['price']) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Способ оплаты</label>
                                <select name="payment_method_id" class="form-select">
                                    <option value="">— Не выбрано —</option>
                                    <?php while ($method = $payment_methods->fetch_assoc()): ?>
                                        <option value="<?= $method['id'] ?>" <?= $method['id'] == $order['payment_method_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($method['name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Комментарий к заказу</label>
                                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Адрес доставки</h5>
                </div>
                <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Получатель *</label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?= htmlspecialchars($address['full_name'] ?? $order['customer_name'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Телефон *</label>
                                <input type="text" name="phone" class="form-control" 
                                       value="<?= htmlspecialchars($address['phone'] ?? $order['customer_phone'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($address['email'] ?? $order['customer_email'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Страна</label>
                                <input type="text" name="country" class="form-control" 
                                       value="<?= htmlspecialchars($address['country'] ?? 'Беларусь') ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Город *</label>
                                <input type="text" name="city" class="form-control" 
                                       value="<?= htmlspecialchars($address['city'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Адрес *</label>
                                <input type="text" name="address" class="form-control" 
                                       value="<?= htmlspecialchars($address['address'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Индекс</label>
                                <input type="text" name="postal_code" class="form-control" 
                                       value="<?= htmlspecialchars($address['postal_code'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" name="update_order" class="btn btn-primary">
                        <i class="fas fa-save"></i> Сохранить изменения
                    </button>
                </div>
            </form>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Информация о заказе</h5>
                </div>
                <div class="card-body">
                    <p><strong>Номер заказа:</strong> <?= htmlspecialchars($order['order_number']) ?></p>
                    <p><strong>Дата создания:</strong> <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
                    <p><strong>Статус:</strong> можно изменить на <a href="change_order_status.php?id=<?= $order_id ?>">странице изменения статуса</a></p>
                    
                    <hr>
                    
                    <h6>Важно:</h6>
                    <ul class="small text-muted">
                        <li>Изменение покупателя может повлиять на историю заказов</li>
                        <li>Адрес доставки используется для формирования накладных</li>
                        <li>Для изменения состава заказа используйте соответствующую функцию</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>