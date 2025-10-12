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
    </div>
</div>

<?php
require_once __DIR__ . '/../inc/footer.php';
?>
