<?php
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone'] ?? '');
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $photo = null;
    $is_subscribed = isset($_POST['is_subscribed']) ? 1 : 0;
    $errors = [];

    // Проверка обязательных полей
    if (empty($login) || empty($email) || empty($password) || empty($confirm_password)) {
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

    // Проверка телефона (опционально)
    if (!empty($phone) && !preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
        $errors[] = "Неверный формат телефона.";
    }

    // Проверка формата ФИО
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
        $verification_token = bin2hex(random_bytes(32));
        
        $stmt = $db->prepare("
            INSERT INTO users (
                login, email, password, full_name, phone, birth_date, photo, 
                is_subscribed, verification_token, email_verified, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->bind_param(
            'sssssssss', 
            $login, $email, $hashed_password, $full_name, $phone, $birth_date, 
            $photo, $is_subscribed, $verification_token
        );

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $stmt->close();

            // Создаем купон в таблице coupons
            $code = 'USER' . $user_id . strtoupper(bin2hex(random_bytes(4)));
            $expiry_date = date('Y-m-d H:i:s', strtotime('+30 days'));
            $start_date = date('Y-m-d H:i:s');
            $coupon_name = "Приветственный купон для {$login}";
            
            $stmt_coupon = $db->prepare("
                INSERT INTO coupons (
                    code, name, type, discount_type, discount_value, 
                    min_order_amount, starts_at, expires_at, status, created_at
                ) VALUES (
                    ?, ?, 'personal', 'percent', 10.00, 
                    NULL, ?, ?, 'active', NOW()
                )
            ");
            $stmt_coupon->bind_param("ssss", $code, $coupon_name, $start_date, $expiry_date);
            
            if ($stmt_coupon->execute()) {
                $coupon_id = $stmt_coupon->insert_id;
                
                // Проверяем, существует ли таблица user_coupons
                $table_check = $db->query("SHOW TABLES LIKE 'user_coupons'");
                if ($table_check->num_rows > 0) {
                    // Используем таблицу user_coupons
                    $user_coupon_stmt = $db->prepare("
                        INSERT INTO user_coupons (user_id, coupon_id, expires_at, assigned_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $user_coupon_stmt->bind_param("iis", $user_id, $coupon_id, $expiry_date);
                    $user_coupon_stmt->execute();
                    $user_coupon_stmt->close();
                } else {
                    // Если таблицы user_coupons нет, пробуем изменить coupon_usage
                    // Сначала проверяем, можно ли вставить NULL в order_id
                    try {
                        $usage_stmt = $db->prepare("
                            INSERT INTO coupon_usage (coupon_id, user_id, order_id, discount_amount, used_at)
                            VALUES (?, ?, NULL, 0, NULL)
                        ");
                        $usage_stmt->bind_param("ii", $coupon_id, $user_id);
                        $usage_stmt->execute();
                        $usage_stmt->close();
                    } catch (Exception $e) {
                        // Если не получается, создаем запись с временным order_id = 0
                        // (это не идеальное решение, лучше создать таблицу user_coupons)
                        error_log("Ошибка при создании связи купона: " . $e->getMessage());
                    }
                }
            }
            $stmt_coupon->close();

            $_SESSION['registration_success'] = true;
            header('Location: auth.php?registered=1');
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
                <small class="text-muted">На этот email будет отправлено письмо для подтверждения</small>
            </div>
            <div class="form-group">
                <label>Пароль <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" required>
                <small class="text-muted">Не менее 6 символов, минимум одна заглавная буква и одна цифра</small>
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
                <label>Телефон</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($phone ?? '') ?>" class="form-control" placeholder="+375XXXXXXXXX">
            </div>
            <div class="form-group">
                <label>Дата рождения</label>
                <input type="date" name="birth_date" value="<?= htmlspecialchars($birth_date ?? '') ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Фото профиля</label>
                <input type="file" name="photo" class="form-control" accept="image/*">
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

<style>
.auth-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 80vh;
    padding: 20px;
}
.auth-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
    padding: 30px;
    width: 100%;
    max-width: 500px;
}
.auth-card h2 {
    text-align: center;
    margin-bottom: 25px;
    color: #333;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #555;
}
.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}
.form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 5px rgba(0,123,255,0.3);
}
.btn-primary {
    width: 100%;
    padding: 10px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.3s;
}
.btn-primary:hover {
    background: #0056b3;
}
.text-danger {
    color: #dc3545;
}
.alert {
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 20px;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.alert ul {
    margin: 0;
    padding-left: 20px;
}
.text-secondary {
    color: #6c757d;
    text-decoration: none;
}
.text-secondary:hover {
    text-decoration: underline;
}
.text-muted {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
    display: block;
}
</style>

<?php
require_once __DIR__ . '/../inc/footer.php';
?>