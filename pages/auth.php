<?php
// 1. Сначала ВСЯ логика PHP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/cart/CartHelper.php'; // 🔧 Подключаем CartHelper

// УДАЛЯЕМ ОТСЮДА ПОДКЛЮЧЕНИЕ ХЕДЕРА! Оно будет ниже.

$errors = [];
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $password = trim($_POST['password']);

    if (empty($login) || empty($password)) {
        $errors[] = "Все поля обязательны для заполнения.";
    }

    if (!empty($login) && !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $login)) {
        $errors[] = "Логин должен быть длиной от 3 до 20 символов и содержать только буквы, цифры и символ подчеркивания.";
    }

    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id, password, is_admin FROM users WHERE login = ?");
        $stmt->bind_param('s', $login);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $hashed_password, $is_admin);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                $_SESSION['id'] = $id;
                $_SESSION['is_admin'] = $is_admin;

                $update = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update->bind_param('i', $id);
                $update->execute();
                $update->close();

                // 🔥 ПУНКТ 3: Синхронизация корзины при авторизации
                if (!empty($_SESSION['cart'])) {
                    $cart = new CartHelper($db);
                    $result = $cart->mergeGuestCart($id, $_SESSION['cart']);
                    
                    // Формируем уведомление для пользователя
                    $total_items = $result['merged'] + $result['added'];
                    if ($total_items > 0) {
                        $_SESSION['cart_merge_notice'] = [
                            'merged' => $result['merged'],
                            'added' => $result['added'],
                            'total' => $total_items
                        ];
                    }
                    
                    // Очищаем гостевую корзину из сессии
                    unset($_SESSION['cart']);
                }

                // Теперь перенаправление сработает, так как HTML еще не отправлен!
                header('Location: ../index.php');
                exit;
            } else {
                $errors[] = "Неверный пароль.";
            }
        } else {
            $errors[] = "Пользователь с таким логином не найден.";
        }
        $stmt->close();
    }
}

// 2. ПОДКЛЮЧАЕМ ХЕДЕР ТОЛЬКО ТУТ (когда уверены, что редирект не нужен)
require_once __DIR__ . '/../inc/header.php'; 
?>

<div class="auth-container">
    <div class="auth-card">
        <h2>Авторизация</h2>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="login">Логин или Email</label>
                <input type="text" id="login" name="login" class="form-control" placeholder="Введите логин или email" value="<?= htmlspecialchars($login) ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Введите пароль" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Войти</button>
            </div>
            <div class="text-center">
                <a href="forgot-password.php" class="text-secondary">Забыли пароль?</a>
            </div>
        </form>
        <div class="text-center mt-3">
            <a href="reg.php" class="text-secondary">Нет аккаунта? Зарегистрироваться</a>
        </div>
    </div>
</div>

<style>
    /* Стили для страницы авторизации */
    body {
        background-color: #f4f7f6; /* Светлый фон для контраста с карточкой */
    }

    .auth-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 80vh; /* Центрируем по вертикали */
        padding: 20px;
    }

    .auth-card {
        background: #fff;
        width: 100%;
        max-width: 400px;
        padding: 40px;
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05); /* Мягкая тень */
    }

    .auth-card h2 {
        text-align: center;
        margin-bottom: 30px;
        font-weight: 700;
        color: #333;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #666;
        font-size: 14px;
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 16px;
        transition: all 0.3s ease;
        box-sizing: border-box; /* Чтобы padding не раздувал ширину */
    }

    .form-control:focus {
        border-color: #007bff;
        outline: none;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }

    .btn-primary {
        width: 100%;
        padding: 12px;
        background-color: #333; /* В стиле ShopHub */
        border: none;
        border-radius: 8px;
        color: white;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.3s ease;
    }

    .btn-primary:hover {
        background-color: #000;
    }

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .alert-danger {
        background-color: #fff5f5;
        color: #e53e3e;
        border: 1px solid #feb2b2;
    }

    .auth-card a {
        text-decoration: none;
        font-size: 14px;
        transition: color 0.2s;
    }

    .auth-card a:hover {
        color: #007bff !important;
    }

    .mt-3 { margin-top: 1rem; }
    .text-center { text-align: center; }
</style>

<div class="auth-container">
    <div class="auth-card">
        <h2>Вход</h2>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="login">Логин</label>
                <input type="text" id="login" name="login" class="form-control" placeholder="Ваш логин" value="<?= htmlspecialchars($login) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary">Войти в ShopHub</button>
            
            <div class="text-center mt-3">
                <a href="forgot-password.php" style="color: #999;">Забыли пароль?</a>
            </div>
        </form>

        <hr style="margin: 25px 0; border: 0; border-top: 1px solid #eee;">

        <div class="text-center">
            <span style="color: #666; font-size: 14px;">Впервые у нас?</span><br>
            <a href="reg.php" style="color: #333; font-weight: 600;">Создать аккаунт</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>