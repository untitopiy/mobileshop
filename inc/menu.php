<?php 
// Убедитесь, что нет никакого вывода до этой строки
if (session_status() === PHP_SESSION_NONE) session_start();

// Объявляем глобальную переменную $db, которая определена в header.php
global $db;

// Проверяем, что $db существует и подключение активно
if (!isset($db) || !$db || $db->connect_error) {
    // Если БД не подключена, выводим заглушку и завершаем работу
    echo '<div class="alert alert-danger">Ошибка подключения к базе данных. Пожалуйста, проверьте настройки.</div>';
    return;
}

$is_authenticated = isset($_SESSION['id']);
$is_admin = false;
$is_seller = false;

if ($is_authenticated) {
    $stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['id']);
        $stmt->execute();
        $stmt->bind_result($is_admin);
        $stmt->fetch();
        $stmt->close();
    }
    
    $stmt = $db->prepare("SELECT id FROM sellers WHERE user_id = ? AND is_active = 1");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['id']);
        $stmt->execute();
        $stmt->store_result();
        $is_seller = $stmt->num_rows > 0;
        $stmt->close();
    }
}

// Счетчик корзины
$cart_count = 0;
if ($is_authenticated) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(ci.quantity), 0) FROM cart c JOIN cart_items ci ON ci.cart_id = c.id WHERE c.user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['id']);
        $stmt->execute();
        $stmt->bind_result($cart_count);
        $stmt->fetch();
        $stmt->close();
    }
} else {
    $cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
}

// Счетчик избранного
$wishlist_count = 0;
if ($is_authenticated) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['id']);
        $stmt->execute();
        $stmt->bind_result($wishlist_count);
        $stmt->fetch();
        $stmt->close();
    }
}

// Глобальная переменная $base_url должна быть определена в header.php
if (!isset($base_url)) {
    $base_url = '/mobileshop/';
}
?>
<div class="navigation-wrapper d-flex align-items-center justify-content-between w-100">
    <ul class="nav d-none d-xl-flex">
        <?php if ($is_seller): ?>
            <li class="nav-item">
                <a class="nav-link" href="<?= $base_url; ?>seller/dashboard.php">
                    <i class="fas fa-cash-register"></i> Кабинет продавца
                </a>
            </li>
        <?php endif; ?>

        <?php if ($is_admin): ?>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-danger fw-bold" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-tie"></i> Админ
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="<?= $base_url; ?>admin/index.php">Панель управления</a></li>
                    <li><a class="dropdown-item" href="<?= $base_url; ?>admin/manage_products.php">Товары</a></li>
                    <li><a class="dropdown-item" href="<?= $base_url; ?>admin/manage_sellers.php">Продавцы</a></li>
                    <li><a class="dropdown-item" href="<?= $base_url; ?>admin/manage_orders.php">Заказы</a></li>
                    <li><a class="dropdown-item" href="<?= $base_url; ?>admin/manage_coupons.php">Купоны</a></li>
                </ul>
            </li>
        <?php endif; ?>
    </ul>
    
    <nav class="user-nav d-flex align-items-center">
        <div class="nav-item" onclick="location.href='<?= $base_url; ?>orders.php'" title="Мои заказы">
            <i class="fas fa-shopping-bag"></i>
            <span>Мои заказы</span>
        </div>

        <div class="nav-item" onclick="location.href='<?= $base_url; ?>stocks.php'" title="Акции">
            <i class="fas fa-percentage"></i>
            <span>Акции</span>
        </div>

        <div class="nav-item" onclick="location.href='<?= $base_url; ?>pages/compare.php'" title="Сравнение">
            <i class="fas fa-balance-scale"></i>
            <span>Сравнение</span>
            <span class="badge" id="compare-count">0</span>
        </div>

        <div class="nav-item" onclick="location.href='<?= $base_url; ?>pages/wishlist.php'" title="Избранное">
            <i class="fas fa-heart"></i>
            <span>Избранное</span>
            <span class="badge" id="wishlist-count"><?= $wishlist_count; ?></span>
        </div>
        
        <div class="nav-item" onclick="location.href='<?= $base_url; ?>pages/cart/cart.php'" title="Корзина">
            <i class="fas fa-shopping-basket"></i>
            <span>Корзина</span>
            <span class="badge" id="cart-badge"><?= $cart_count; ?></span>
        </div>

        <?php if ($is_authenticated): ?>
            <div class="nav-item" onclick="location.href='<?= $base_url; ?>pages/profile.php'" title="Личный кабинет">
                <i class="fas fa-user-circle"></i>
                <span>Профиль</span>
            </div>
            <div class="nav-item" onclick="location.href='<?= $base_url; ?>pages/logout.php'" title="Выйти">
                <i class="fas fa-sign-out-alt"></i>
                <span>Выход</span>
            </div>
        <?php else: ?>
            <div class="nav-item" onclick="location.href='<?= $base_url; ?>pages/auth.php'" title="Войти">
                <i class="fas fa-sign-in-alt"></i>
                <span>Вход</span>
            </div>
            <div class="nav-item" onclick="location.href='<?= $base_url; ?>pages/reg.php'" title="Регистрация">
                <i class="fas fa-user-plus"></i>
                <span>Регистрация</span>
            </div>
        <?php endif; ?>
    </nav>
</div>