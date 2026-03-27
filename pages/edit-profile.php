<?php
require_once __DIR__ . '/../inc/header.php';

// Проверка авторизации пользователя
if (!isset($_SESSION['id'])) {
    header('Location: auth.php');
    exit;
}

$user_id = $_SESSION['id'];

// Получение текущей информации о пользователе
$stmt = $db->prepare("SELECT login, email, full_name, phone, photo, birth_date, is_subscribed FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_full_name = trim($_POST['full_name']);
    $new_phone = trim($_POST['phone'] ?? '');
    $new_birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $new_is_subscribed = isset($_POST['is_subscribed']) ? 1 : 0;
    $new_photo = $user['photo']; // Если фото не меняется, оставить старое

    $errors = [];

    // Проверка формата ФИО
    if (!empty($new_full_name) && !preg_match('/^[А-Яа-яЁёA-Za-z\s\-]+$/u', $new_full_name)) {
        $errors[] = "ФИО может содержать только буквы, пробелы и дефисы.";
    }

    // Проверка телефона
    if (!empty($new_phone) && !preg_match('/^\+?[0-9]{10,15}$/', $new_phone)) {
        $errors[] = "Неверный формат телефона.";
    }

    // Проверка даты рождения
    if (!empty($new_birth_date)) {
        $current_date = date('Y-m-d');
        if ($new_birth_date >= $current_date) {
            $errors[] = "Дата рождения должна быть раньше текущей даты.";
        }
    }

    // Проверка и загрузка нового фото
    if (!empty($_FILES['photo']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 2 * 1024 * 1024; // 2 MB
        $upload_dir = __DIR__ . '/photo/';

        // Создаём папку, если её нет
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (!in_array($_FILES['photo']['type'], $allowed_types)) {
            $errors[] = "Разрешены только изображения форматов JPG, PNG, GIF, WEBP.";
        }

        if ($_FILES['photo']['size'] > $max_size) {
            $errors[] = "Размер файла не должен превышать 2 МБ.";
        }

        if (empty($errors)) {
            $new_photo_name = uniqid('photo_', true) . '.' . pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $new_photo_path = $upload_dir . $new_photo_name;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $new_photo_path)) {
                // Удаляем старое фото, если оно есть
                if ($user['photo'] && file_exists($upload_dir . $user['photo'])) {
                    unlink($upload_dir . $user['photo']);
                }
                $new_photo = $new_photo_name;
            } else {
                $errors[] = "Ошибка при загрузке файла.";
            }
        }
    }

    // Если нет ошибок, обновляем данные в базе
    if (empty($errors)) {
        $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, birth_date = ?, photo = ?, is_subscribed = ? WHERE id = ?");
        $stmt->bind_param('ssssii', $new_full_name, $new_phone, $new_birth_date, $new_photo, $new_is_subscribed, $user_id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Профиль успешно обновлен";
            header('Location: profile.php');
            exit;
        } else {
            $errors[] = "Ошибка при обновлении данных. Попробуйте позже.";
        }
    }
}
?>

<div class="edit-profile-container">
    <div class="edit-profile-card">
        <h2>Редактировать профиль</h2>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group mb-3">
                <label>Логин</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($user['login']); ?>" disabled readonly>
                <small class="text-muted">Логин нельзя изменить</small>
            </div>

            <div class="form-group mb-3">
                <label>Email</label>
                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']); ?>" disabled readonly>
                <small class="text-muted">Email нельзя изменить</small>
            </div>

            <div class="form-group mb-3">
                <label for="full_name">ФИО</label>
                <input type="text" id="full_name" name="full_name" class="form-control" 
                       value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" 
                       placeholder="Введите ваше полное имя">
            </div>

            <div class="form-group mb-3">
                <label for="phone">Телефон</label>
                <input type="tel" id="phone" name="phone" class="form-control" 
                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                       placeholder="+375XXXXXXXXX">
            </div>

            <div class="form-group mb-3">
                <label for="birth_date">Дата рождения</label>
                <input type="date" id="birth_date" name="birth_date" class="form-control" 
                       value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>">
            </div>

            <div class="form-group mb-3">
                <label for="photo">Фото профиля</label>
                <input type="file" id="photo" name="photo" class="form-control" accept="image/*">
                <?php if (!empty($user['photo'])): ?>
                    <div class="mt-2">
                        <p class="mb-1">Текущее фото:</p>
                        <img src="./photo/<?= htmlspecialchars($user['photo']); ?>" alt="Текущее фото" 
                             style="max-width: 100px; max-height: 100px; object-fit: cover;" class="rounded">
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group mb-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="is_subscribed" name="is_subscribed" 
                           <?= $user['is_subscribed'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_subscribed">
                        Получать новости и акции на email
                    </label>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                <a href="profile.php" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../inc/footer.php';
?>