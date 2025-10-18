<?php
session_start(); // Сессия стартует сразу

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/header.php';

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
        $stmt = $db->prepare("SELECT id, password FROM users WHERE login = ?");
        $stmt->bind_param('s', $login);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $hashed_password);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                $_SESSION['id'] = $id;

                // Обновляем last_login
                $update = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update->bind_param('i', $id);
                $update->execute();
                $update->close();

                header('Location: ../main.php');
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
                <label for="login">Логин</label>
                <input type="text" id="login" name="login" class="form-control" placeholder="Введите логин" value="<?= htmlspecialchars($login) ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Введите пароль" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Войти</button>
            </div>
        </form>
        <div class="text-center mt-3">
            <a href="reg.php" class="text-secondary">Нет аккаунта? Зарегистрироваться</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
