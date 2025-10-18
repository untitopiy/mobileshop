<?php
require_once __DIR__ . '/../inc/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $full_name = trim($_POST['full_name']);
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $photo = null;
    $is_subscribed = isset($_POST['is_subscribed']) ? 1 : 0;
    $errors = []; // Массив для хранения ошибок

    // Проверка обязательных полей
    if (empty($login) || empty($password) || empty($confirm_password)) {
        $errors[] = "Пожалуйста, заполните обязательные поля.";
    }

    // Проверка формата логина
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $login)) {
        $errors[] = "Логин должен быть длиной от 3 до 20 символов и содержать только буквы, цифры и символ подчеркивания.";
    }
    // Проверка email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Неверный формат email.";
    }
    // Проверка пароля
    if (strlen($password) < 6) {
        $errors[] = "Пароль должен быть не менее 6 символов.";
    }

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = "Пароль должен содержать хотя бы одну заглавную букву и одну цифру.";
    }

    // Проверка совпадения паролей
    if ($password !== $confirm_password) {
        $errors[] = "Пароли не совпадают.";
    }

    // Проверка формата ФИО (опционально)
    if (!empty($full_name) && !preg_match('/^[А-Яа-яЁёA-Za-z\s\-]+$/u', $full_name)) {
        $errors[] = "ФИО может содержать только буквы, пробелы и дефисы.";
    }

    // Проверка даты рождения
    if (!empty($birth_date)) {
        $current_date = date('Y-m-d');
        if ($birth_date >= $current_date) {
            $errors[] = "Дата рождения должна быть раньше текущей даты.";
        }
    }

    // Проверка и загрузка фото профиля
    if (!empty($_FILES['photo']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2 MB
        $upload_dir = __DIR__ . '/photo/';

        if (!in_array($_FILES['photo']['type'], $allowed_types)) {
            $errors[] = "Разрешены только изображения форматов JPG, PNG, GIF.";
        }

        if ($_FILES['photo']['size'] > $max_size) {
            $errors[] = "Размер файла не должен превышать 2 МБ.";
        }

        if (empty($errors)) {
            $photo_name = uniqid('photo_', true) . '.' . pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo_path = $upload_dir . $photo_name;

            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                $errors[] = "Ошибка при загрузке файла.";
            } else {
                $photo = $photo_name;
            }
        }
    }
     // Проверка уникальности логина и email
     if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE login = ? OR email = ?");
        $stmt->bind_param('ss', $login, $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Логин или email уже используются.";
        }
        $stmt->close();
    }
    // Регистрация
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (login, email, password, full_name, birth_date, photo, is_subscribed) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssssi', $login, $email, $hashed_password, $full_name, $birth_date, $photo, $is_subscribed);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;

            // Генерация персонального купона
            $code = 'USER' . $user_id . strtoupper(bin2hex(random_bytes(4)));
            $expiry_date = date('Y-m-d', strtotime('+30 days'));
            $stmt_coupon = $db->prepare("
                INSERT INTO coupons (code, type, user_id, product_id, discount_type, discount_value, expires_at, status)
                VALUES (?, 'personal', ?, NULL, 'percent', 10.00, ?, 'active')
            ");
            $stmt_coupon->bind_param("sis", $code, $user_id, $expiry_date);
            $stmt_coupon->execute();
            $stmt_coupon->close();

            $stmt->close();
            header('Location: auth.php');
            exit;
        } else {
            $errors[] = "Ошибка при регистрации. Попробуйте позже.";
        }
    }
}
?>

<div class="auth-container">
    <div class="auth-card">
        <h2>Регистрация</h2>
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
                <label>Логин <span class="text-danger">*</span></label>
                <input type="text" name="login" value="<?= htmlspecialchars($login ?? '') ?>" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Email <span class="text-danger">*</span></label>
                <input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Пароль <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Повторите пароль <span class="text-danger">*</span></label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label>ФИО</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($full_name ?? '') ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Дата рождения</label>
                <input type="date" name="birth_date" value="<?= htmlspecialchars($birth_date ?? '') ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Фото профиля</label>
                <input type="file" name="photo" class="form-control">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_subscribed" <?= isset($is_subscribed) && $is_subscribed ? 'checked' : 'checked' ?>>
                    Получать новости и акции на email
                </label>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Зарегистрироваться</button>
            </div>
        </form>
        <div class="text-center mt-3">
            <a href="auth.php" class="text-secondary">Уже есть аккаунт? Войти</a>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../inc/footer.php';
?>
