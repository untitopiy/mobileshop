<?php 
if (session_status() === PHP_SESSION_NONE) session_start();
$is_authenticated = isset($_SESSION['id']);
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$is_admin = false;
if ($is_authenticated) {
    $stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $stmt->bind_result($is_admin);
    $stmt->fetch();
    $stmt->close();
}
?>
<nav>
    <ul class="nav">
        <li class="nav-item">
            <a class="nav-link <?= ($_SERVER['SCRIPT_NAME'] === $base_url . 'index.php') ? 'active' : '' ?>" href="<?= $base_url; ?>index.php">Главная</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($_SERVER['SCRIPT_NAME'] === $base_url . 'stocks.php') ? 'active' : '' ?>" href="<?= $base_url; ?>stocks.php">Акции</a>
        </li>
        <?php if ($is_authenticated): ?>
            <li class="nav-item">
                <a class="nav-link <?= ($_SERVER['SCRIPT_NAME'] === $base_url . 'pages/profile.php') ? 'active' : '' ?>" href="<?= $base_url; ?>pages/profile.php">Профиль</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($_SERVER['SCRIPT_NAME'] === $base_url . 'cart.php') ? 'active' : '' ?>" href="<?= $base_url; ?>cart.php">
                    Корзина<?php if ($cart_count > 0): ?><span class="cart-badge"><?= $cart_count; ?></span><?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($_SERVER['SCRIPT_NAME'] === $base_url . 'orders.php') ? 'active' : '' ?>" href="<?= $base_url; ?>orders.php">Мои заказы</a>
            </li>
            <?php if ($is_admin): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($_SERVER['SCRIPT_NAME'] === $base_url . 'admin/index.php') ? 'active' : '' ?>" href="<?= $base_url; ?>admin/index.php">Админ</a>
                </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" href="<?= $base_url; ?>pages/logout.php">Выход</a>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <a class="nav-link <?= ($_SERVER['SCRIPT_NAME'] === $base_url . 'pages/auth.php') ? 'active' : '' ?>" href="<?= $base_url; ?>pages/auth.php">Вход</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($_SERVER['SCRIPT_NAME'] === $base_url . 'pages/reg.php') ? 'active' : '' ?>" href="<?= $base_url; ?>pages/reg.php">Регистрация</a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
