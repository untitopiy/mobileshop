<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Получаем список пользователей, которые еще не являются продавцами
$users = $db->query("
    SELECT u.id, u.login, u.full_name, u.email 
    FROM users u
    LEFT JOIN sellers s ON s.user_id = u.id
    WHERE s.id IS NULL AND u.is_admin = 0
    ORDER BY u.full_name
");

// Сохраняем пользователей в массив, чтобы использовать после header.php
$users_list = [];
while ($user = $users->fetch_assoc()) {
    $users_list[] = $user;
}

// Обработка формы (ДО header.php)
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_seller'])) {
    $user_id = (int)$_POST['user_id'];
    $shop_name = trim($_POST['shop_name']);
    $shop_slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($shop_name));
    $shop_slug = trim($shop_slug, '-');
    $description = trim($_POST['description']);
    $contact_email = trim($_POST['contact_email']);
    $contact_phone = trim($_POST['contact_phone']);
    $address = trim($_POST['address']);
    $commission_rate = (float)$_POST['commission_rate'];
    $is_verified = isset($_POST['is_verified']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Валидация
    if (empty($shop_name)) $errors[] = "Название магазина обязательно";
    if (empty($user_id)) $errors[] = "Выберите пользователя";
    if (empty($contact_email)) $errors[] = "Контактный email обязателен";
    if (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) $errors[] = "Неверный формат email";
    if ($commission_rate < 0 || $commission_rate > 100) $errors[] = "Комиссия должна быть от 0 до 100";
    
    // Проверка уникальности slug
    $check = $db->prepare("SELECT id FROM sellers WHERE shop_slug = ?");
    $check->bind_param('s', $shop_slug);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $shop_slug .= '-' . uniqid();
    }
    
    // Загрузка логотипа
    $logo_url = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($_FILES['logo']['type'], $allowed)) {
            $errors[] = "Неверный формат логотипа. Разрешены JPG, PNG, GIF, WEBP";
        } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
            $errors[] = "Размер логотипа не должен превышать 2 МБ";
        } else {
            $upload_dir = __DIR__ . '/../uploads/sellers/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $new_name = 'seller_' . uniqid() . '.' . $ext;
            $upload_path = $upload_dir . $new_name;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                $logo_url = 'uploads/sellers/' . $new_name;
            } else {
                $errors[] = "Ошибка при загрузке логотипа";
            }
        }
    }
    
    // Загрузка баннера
    $banner_url = null;
    if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($_FILES['banner']['type'], $allowed)) {
            $errors[] = "Неверный формат баннера. Разрешены JPG, PNG, GIF, WEBP";
        } elseif ($_FILES['banner']['size'] > 5 * 1024 * 1024) {
            $errors[] = "Размер баннера не должен превышать 5 МБ";
        } else {
            $upload_dir = __DIR__ . '/../uploads/sellers/banners/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
            $new_name = 'banner_' . uniqid() . '.' . $ext;
            $upload_path = $upload_dir . $new_name;
            
            if (move_uploaded_file($_FILES['banner']['tmp_name'], $upload_path)) {
                $banner_url = 'uploads/sellers/banners/' . $new_name;
            } else {
                $errors[] = "Ошибка при загрузке баннера";
            }
        }
    }
    
    if (empty($errors)) {
        $stmt = $db->prepare("
            INSERT INTO sellers (
                user_id, shop_name, shop_slug, logo_url, banner_url, description,
                contact_email, contact_phone, address, commission_rate, is_verified, is_active,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            "issssssssdii",
            $user_id, $shop_name, $shop_slug, $logo_url, $banner_url, $description,
            $contact_email, $contact_phone, $address, $commission_rate, $is_verified, $is_active
        );
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Продавец успешно добавлен";
            header("Location: manage_sellers.php");
            exit;
        } else {
            $errors[] = "Ошибка при добавлении продавца: " . $db->error;
        }
    }
}

// Подключаем header ПОСЛЕ обработки POST
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-fluid admin-container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Добавление продавца</h1>
        <a href="manage_sellers.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Назад
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Информация о продавце</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Пользователь *</label>
                                <select name="user_id" class="form-select" required>
                                    <option value="">Выберите пользователя</option>
                                    <?php foreach ($users_list as $user): ?>
                                        <option value="<?= $user['id'] ?>" <?= (isset($_POST['user_id']) && $_POST['user_id'] == $user['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($user['full_name'] ?: $user['login']) ?> (<?= $user['email'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Название магазина *</label>
                                <input type="text" name="shop_name" class="form-control" required value="<?= isset($_POST['shop_name']) ? htmlspecialchars($_POST['shop_name']) : '' ?>">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Описание магазина</label>
                                <textarea name="description" class="form-control" rows="4"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Контактный email *</label>
                                <input type="email" name="contact_email" class="form-control" required value="<?= isset($_POST['contact_email']) ? htmlspecialchars($_POST['contact_email']) : '' ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Контактный телефон</label>
                                <input type="text" name="contact_phone" class="form-control" value="<?= isset($_POST['contact_phone']) ? htmlspecialchars($_POST['contact_phone']) : '' ?>">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Адрес магазина</label>
                                <textarea name="address" class="form-control" rows="2"><?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?></textarea>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Комиссия (%) *</label>
                                <input type="number" name="commission_rate" step="0.1" min="0" max="100" 
                                       class="form-control" value="<?= isset($_POST['commission_rate']) ? htmlspecialchars($_POST['commission_rate']) : '10' ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input type="checkbox" name="is_verified" class="form-check-input" id="is_verified" <?= isset($_POST['is_verified']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_verified">Верифицирован</label>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" <?= (!isset($_POST['is_active']) || $_POST['is_active'] == 'on') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">Активен</label>
                                </div>
                            </div>
                        </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Медиа</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Логотип магазина</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <small class="text-muted">Рекомендуемый размер: 200x200px, макс 2MB</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Баннер магазина</label>
                        <input type="file" name="banner" class="form-control" accept="image/*">
                        <small class="text-muted">Рекомендуемый размер: 1200x300px, макс 5MB</small>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" name="add_seller" class="btn btn-success w-100">
                            <i class="fas fa-save"></i> Создать продавца
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>