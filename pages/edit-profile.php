<?php
require_once __DIR__ . '/../inc/header.php';

// Проверка авторизации пользователя
if (!isset($_SESSION['id'])) {
    header('Location: auth.php');
    exit;
}

$user_id = $_SESSION['id'];

// Получение текущей информации о пользователе
$stmt = $db->prepare("SELECT login, full_name, photo, birth_date FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($login, $full_name, $photo, $birth_date);
$stmt->fetch();
$stmt->close();

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_full_name = trim($_POST['full_name']);
    $new_birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $new_photo = $photo; // Если фото не меняется, оставить старое

    $errors = []; // Для хранения ошибок

    // Проверка формата ФИО
    if (!empty($new_full_name) && !preg_match('/^[А-Яа-яЁёA-Za-z\s\-]+$/u', $new_full_name)) {
        $errors[] = "ФИО может содержать только буквы, пробелы и дефисы.";
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
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2 MB
        $upload_dir = __DIR__ . '/photo/';

        // Создаём папку, если её нет
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (!in_array($_FILES['photo']['type'], $allowed_types)) {
            $errors[] = "Разрешены только изображения форматов JPG, PNG, GIF.";
        }

        if ($_FILES['photo']['size'] > $max_size) {
            $errors[] = "Размер файла не должен превышать 2 МБ.";
        }

        if (empty($errors)) {
            $new_photo_name = uniqid('photo_', true) . '.' . pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $new_photo_path = $upload_dir . $new_photo_name;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $new_photo_path)) {
                // Удаляем старое фото, если оно есть
                if ($photo && file_exists($upload_dir . $photo)) {
                    unlink($upload_dir . $photo);
                }
                $new_photo = $new_photo_name;
            } else {
                $errors[] = "Ошибка при загрузке файла.";
            }
        }
    }

    // Если нет ошибок, обновляем данные в базе
    if (empty($errors)) {
        $stmt = $db->prepare("UPDATE users SET full_name = ?, birth_date = ?, photo = ? WHERE id = ?");
        $stmt->bind_param('sssi', $new_full_name, $new_birth_date, $new_photo, $user_id);

        if ($stmt->execute()) {
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
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="full_name">ФИО</label>
                <input type="text" id="full_name" name="full_name" class="form-control" value="<?= htmlspecialchars($full_name) ?>" placeholder="Введите ваше имя">
            </div>
            <div class="form-group">
                <label for="birth_date">Дата рождения</label>
                <input type="date" id="birth_date" name="birth_date" class="form-control" value="<?= htmlspecialchars($birth_date) ?>">
            </div>
            <div class="form-group">
                <label for="photo">Фото профиля</label>
                <input type="file" id="photo" name="photo" class="form-control">
                <?php if (!empty($photo)): ?>
                    <img src="./photo/<?= htmlspecialchars($photo); ?>" alt="Текущее фото" class="current-photo">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Сохранить изменения</button>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../inc/footer.php';
?>
