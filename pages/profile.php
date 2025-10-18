<link rel="stylesheet" href="../inc/styles.css">
<?php
require_once __DIR__ . '/../inc/header.php';

// Проверка авторизации пользователя
if (!isset($_SESSION['id'])) {
    header('Location: auth.php');
    exit;
}

// Получение информации о текущем пользователе
$user_id = $_SESSION['id'];
$stmt = $db->prepare("SELECT login, full_name, photo, birth_date FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($login, $full_name, $photo, $birth_date);
$stmt->fetch();
$stmt->close();

// Получение только персональных активных купонов пользователя
$stmt_coupons = $db->prepare("
    SELECT code, discount_type, discount_value, status, expires_at 
    FROM coupons 
    WHERE user_id = ? 
      AND status = 'active' 
      AND expires_at >= CURDATE()
");
$stmt_coupons->bind_param("i", $user_id);
$stmt_coupons->execute();
$result_coupons = $stmt_coupons->get_result();
$coupons = $result_coupons->fetch_all(MYSQLI_ASSOC);
$stmt_coupons->close();
?>

<div class="profile-container">
    <div class="profile-card">
        <h2>Ваш профиль</h2>
        <div class="profile-photo">
            <?php if (!empty($photo)): ?>
                <img src="./photo/<?= htmlspecialchars($photo); ?>" alt="Фото профиля">
            <?php else: ?>
                <img src="../assets/default-profile.png" alt="Фото профиля">
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <p><strong>Логин:</strong> <?= htmlspecialchars($login); ?></p>
            <p><strong>ФИО:</strong> <?= htmlspecialchars($full_name) ?: 'Не указано'; ?></p>
            <p><strong>Дата рождения:</strong> <?= htmlspecialchars($birth_date) ?: 'Не указано'; ?></p>
        </div>
        <div class="profile-actions">
            <a href="edit-profile.php" class="btn btn-primary">Редактировать профиль</a>
            <a href="logout.php" class="btn btn-danger">Выйти</a>
        </div>

        <h3 class="mt-4">Мои купоны</h3>
        <div class="coupons-container">
            <?php if (!empty($coupons)): ?>
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>Код купона</th>
                    <th>Скидка</th>
                    <th>Осталось дней</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($coupons as $coupon):
                    $days_left = max(0, ceil((strtotime($coupon['expires_at']) - time()) / 86400));
                    $discount = $coupon['discount_type'] === 'percent' 
                        ? $coupon['discount_value'] . '%' 
                        : $coupon['discount_value'] . '₽';
                ?>
                    <tr>
                        <td><?= htmlspecialchars($coupon['code']); ?></td>
                        <td><?= $discount; ?></td>
                        <td><?= $days_left; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>Активных купонов нет.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../inc/footer.php';
?>
