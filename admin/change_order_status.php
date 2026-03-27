<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/functions.php'; // Функции и подключение к БД должны быть первыми

// 1. Проверка ID заказа (Логика до вывода HTML)
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_orders.php");
    exit;
}
$order_id = (int)$_GET['id'];

// 2. Получаем информацию о заказе для проверки его существования
$order_query = $db->prepare("
    SELECT o.id, o.order_number, o.status_id, os.name as current_status
    FROM orders o
    JOIN order_statuses os ON os.id = o.status_id
    WHERE o.id = ?
");
$order_query->bind_param('i', $order_id);
$order_query->execute();
$order = $order_query->get_result()->fetch_assoc();

if (!$order) {
    header("Location: manage_orders.php");
    exit;
}

// 3. ОБРАБОТКА ФОРМЫ (СТРОГО ДО require header.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status_id = (int)$_POST['status_id'];
    $comment = trim($_POST['comment']);
    $notify_customer = isset($_POST['notify_customer']);
    
    $db->begin_transaction();
    
    try {
        // Обновляем статус заказа
        $stmt = $db->prepare("UPDATE orders SET status_id = ? WHERE id = ?");
        $stmt->bind_param('ii', $new_status_id, $order_id);
        $stmt->execute();
        
        // Добавляем запись в историю
        $user_id = $_SESSION['id'];
        $history_stmt = $db->prepare("
            INSERT INTO order_status_history (order_id, status_id, comment, created_by, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $history_stmt->bind_param('iisi', $order_id, $new_status_id, $comment, $user_id);
        $history_stmt->execute();
        
        // Обновление статусов отдельных продавцов
        if (isset($_POST['seller_status']) && is_array($_POST['seller_status'])) {
            $seller_stmt = $db->prepare("UPDATE order_sellers SET status_id = ? WHERE id = ?");
            foreach ($_POST['seller_status'] as $s_id => $status) {
                $seller_status_id = (int)$status;
                $seller_stmt->bind_param('ii', $seller_status_id, $s_id);
                $seller_stmt->execute();
            }
        }
        
        // Обновление трек-номера
        if (!empty($_POST['tracking_number'])) {
            $tracking = trim($_POST['tracking_number']);
            $tracking_stmt = $db->prepare("UPDATE order_sellers SET tracking_number = ? WHERE order_id = ?");
            $tracking_stmt->bind_param('si', $tracking, $order_id);
            $tracking_stmt->execute();
        }
        
        $db->commit();
        
        if ($notify_customer) {
            // Код отправки уведомления
        }
        
        $_SESSION['success'] = "Статус заказа успешно обновлен";
        header("Location: view_order.php?id=$order_id");
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Ошибка при обновлении статуса: " . $e->getMessage();
    }
}

// 4. Подготовка данных для отображения (после обработки POST, но до вывода)
$statuses = $db->query("SELECT id, name, color, slug FROM order_statuses ORDER BY sort_order");
$sellers_stmt = $db->prepare("
    SELECT os.id as order_seller_id, s.shop_name as seller_name, os.status_id, ost.name as status_name
    FROM order_sellers os
    LEFT JOIN sellers s ON s.id = os.seller_id
    JOIN order_statuses ost ON ost.id = os.status_id
    WHERE os.order_id = ?
");
$sellers_stmt->bind_param('i', $order_id);
$sellers_stmt->execute();
$sellers_result = $sellers_stmt->get_result();

// 5. ТЕПЕРЬ ПОДКЛЮЧАЕМ ВИЗУАЛЬНУЮ ЧАСТЬ
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Изменение статуса заказа <?= htmlspecialchars($order['order_number']) ?></h1>
        <a href="view_order.php?id=<?= $order_id ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Назад
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Текущий статус заказа</label>
                            <div class="p-2 bg-light rounded">
                                <?= htmlspecialchars($order['current_status']) ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Новый статус заказа</label>
                            <select name="status_id" class="form-select" required>
                                <?php 
                                $statuses->data_seek(0);
                                while ($status = $statuses->fetch_assoc()): 
                                ?>
                                    <option value="<?= $status['id'] ?>" <?= $status['id'] == $order['status_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Комментарий к изменению</label>
                            <textarea name="comment" class="form-control" rows="3" placeholder="Причина изменения статуса"></textarea>
                        </div>

                        <?php if ($sellers_result->num_rows > 1): ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Статусы по продавцам</label>
                                <?php 
                                $sellers_result->data_seek(0);
                                while ($seller = $sellers_result->fetch_assoc()): 
                                ?>
                                    <div class="card mb-2">
                                        <div class="card-body">
                                            <h6><?= htmlspecialchars($seller['seller_name'] ?: 'Продавец') ?></h6>
                                            <select name="seller_status[<?= $seller['order_seller_id'] ?>]" class="form-select">
                                                <?php 
                                                $statuses->data_seek(0);
                                                while ($status = $statuses->fetch_assoc()): 
                                                ?>
                                                    <option value="<?= $status['id'] ?>" <?= $status['id'] == $seller['status_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($status['name']) ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Трек-номер (для отслеживания)</label>
                            <input type="text" name="tracking_number" class="form-control">
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="notify_customer" class="form-check-input" id="notify" checked>
                            <label class="form-check-label" for="notify">Уведомить покупателя</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Информация</h5></div>
                <div class="card-body">
                    <h6>Возможные статусы:</h6>
                    <ul class="list-unstyled">
                        <?php 
                        $statuses->data_seek(0);
                        while ($status = $statuses->fetch_assoc()): 
                        ?>
                            <li class="mb-2">
                                <span class="badge" style="background-color: <?= $status['color'] ?>; color: white;">
                                    <?= htmlspecialchars($status['name']) ?>
                                </span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>