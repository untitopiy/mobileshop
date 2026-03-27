<?php
session_start();

// 1. Подключение зависимостей
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/check_admin.php'; 

// 2. Валидация ID продавца
if (!isset($_GET['seller_id']) || !is_numeric($_GET['seller_id'])) {
    header("Location: manage_sellers.php");
    exit;
}

$seller_id = (int)$_GET['seller_id'];

// 3. Получение данных о продавце (используем ваши таблицы sellers и users)
$seller_query = $db->prepare("
    SELECT s.*, u.full_name, u.email 
    FROM sellers s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.id = ?
");
$seller_query->bind_param('i', $seller_id);
$seller_query->execute();
$seller = $seller_query->get_result()->fetch_assoc();

if (!$seller) {
    die("Продавец не найден в базе данных.");
}

// 4. Получение списка транзакций из вашей таблицы `seller_transactions`
$query = "SELECT * FROM seller_transactions WHERE seller_id = ? ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$transactions = $stmt->get_result();

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="manage_sellers.php">Управление продавцами</a></li>
                    <li class="breadcrumb-item active">Финансовая история</li>
                </ol>
            </nav>
            <h1 class="h3">Транзакции: <?= htmlspecialchars($seller['shop_name'] ?: $seller['full_name']) ?></h1>
            <span class="badge bg-info text-dark">ID Продавца: #<?= $seller['id'] ?></span>
        </div>
        
        <div class="card border-primary">
            <div class="card-body py-2 px-4 text-center">
                <small class="text-muted d-block">Текущий баланс</small>
                <h3 class="text-primary mb-0"><?= number_format($seller['balance'], 2, '.', ' ') ?> руб.</h3>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0"><i class="fas fa-exchange-alt me-2 text-secondary"></i>История операций</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Дата</th>
                            <th>Тип</th>
                            <th>Сумма</th>
                            <th>Описание</th>
                            <th class="text-center">Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($transactions->num_rows > 0): ?>
                            <?php while ($t = $transactions->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 text-muted">#<?= $t['id'] ?></td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span><?= date('d.m.Y', strtotime($t['created_at'])) ?></span>
                                            <small class="text-muted"><?= date('H:i', strtotime($t['created_at'])) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        // Маппинг типов транзакций на основе вашей структуры ENUM/Varchar
                                        switch($t['type']) {
                                            case 'sale':
                                                echo '<span class="badge rounded-pill bg-success-subtle text-success border border-success">Продажа</span>';
                                                break;
                                            case 'payout':
                                                echo '<span class="badge rounded-pill bg-warning-subtle text-warning border border-warning">Выплата</span>';
                                                break;
                                            case 'refund':
                                                echo '<span class="badge rounded-pill bg-danger-subtle text-danger border border-danger">Возврат</span>';
                                                break;
                                            default:
                                                echo '<span class="badge rounded-pill bg-secondary">' . htmlspecialchars($t['type']) . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold <?= $t['amount'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= ($t['amount'] > 0 ? '+' : '') . number_format($t['amount'], 2, '.', ' ') ?> руб.
                                        </span>
                                    </td>
                                    <td style="max-width: 300px;">
                                        <div class="text-truncate" title="<?= htmlspecialchars($t['description']) ?>">
                                            <?= htmlspecialchars($t['description']) ?>
                                        </div>
                                        <?php if ($t['order_seller_id']): ?>
                                            <small class="d-block text-muted">Связанный заказ: #<?= $t['order_seller_id'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($t['status'] == 'completed'): ?>
                                            <i class="fas fa-check-circle text-success" title="Завершено"></i>
                                        <?php elseif ($t['status'] == 'pending'): ?>
                                            <i class="fas fa-clock text-warning" title="В ожидании"></i>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark"><?= $t['status'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-folder-open fa-3x mb-3 d-block"></i>
                                        История транзакций пуста
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="mt-4">
        <a href="manage_sellers.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Вернуться к списку продавцов
        </a>
    </div>
</div>

<style>
/* Стили для соответствия современному админ-интерфейсу */
.bg-success-subtle { background-color: #d1e7dd; }
.bg-warning-subtle { background-color: #fff3cd; }
.bg-danger-subtle { background-color: #f8d7da; }
.admin-container { max-width: 1200px; margin: 0 auto; }
.table th { font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
</style>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>