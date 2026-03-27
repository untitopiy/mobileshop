<?php
// Включение буферизации вывода и старт сессии
if (session_status() === PHP_SESSION_NONE) {
    ob_start();
    session_start();
}

// Определение корневого пути проекта
$project_folder = '/mobileshop/';
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . $project_folder;

// Подключение к базе данных и функциям
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/functions.php";

// Получение количества товаров в корзине
$cart_count = 0;
$tables_exist = false;

// Проверяем существование таблиц
$check_cart = $db->query("SHOW TABLES LIKE 'cart'");
$check_cart_items = $db->query("SHOW TABLES LIKE 'cart_items'");

if ($check_cart->num_rows > 0 && $check_cart_items->num_rows > 0) {
    $tables_exist = true;
}

if (isset($_SESSION['id']) && $tables_exist) {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(ci.quantity), 0) as total 
        FROM cart c
        LEFT JOIN cart_items ci ON ci.cart_id = c.id
        WHERE c.user_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['id']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $cart_count = $result['total'] ?? 0;
        $stmt->close();
    }
} else {
    $cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
}

$_SESSION['cart_count'] = $cart_count;

// ПОЛУЧЕНИЕ КАТЕГОРИЙ ДЛЯ КАТАЛОГА
$categories = [];
$cat_query = "SELECT id, name FROM categories ORDER BY name ASC";
$cat_result = $db->query($cat_query);
if ($cat_result && $cat_result->num_rows > 0) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Интернет магазин - ShopHub</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>inc/styles.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">

    <style>
        .catalog-container {
    position: relative; /* Чтобы меню позиционировалось относительно кнопки */
}

        .catalog-menu {
            display: none; /* Скрыто по умолчанию */
            position: absolute;
            top: 100%;
            left: 0;
            width: 260px;
            background: #fff;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-radius: 8px;
            padding: 15px 0;
            z-index: 2000;
        }

        .catalog-menu.active {
            display: block; /* Показывается, когда JS добавляет класс */
        }
        .catalog-list { list-style: none; padding: 0; margin: 0; }
        .catalog-list li { border-bottom: 1px solid #f1f1f1; }
        .catalog-list li:last-child { border-bottom: none; }
        .catalog-list a {
            display: block;
            padding: 10px 20px;
            color: #333;
            text-decoration: none;
            transition: background 0.2s;
        }
        .catalog-list a:hover { background: #f8f9fa; color: #0d6efd; }
    </style>
</head>
<body>

<!-- 🔥 ПУНКТ 3: Уведомление о синхронизации корзины -->
<?php if (isset($_SESSION['cart_merge_notice'])): ?>
    <div class="container mt-3">
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-shopping-cart me-2"></i>
            <?php 
            $notice = $_SESSION['cart_merge_notice'];
            $parts = [];
            if ($notice['added'] > 0) {
                $parts[] = "добавлено {$notice['added']} новых";
            }
            if ($notice['merged'] > 0) {
                $parts[] = "объединено {$notice['merged']} существующих";
            }
            echo "В вашу корзину " . implode(' и ', $parts) . " товаров из гостевой сессии (всего {$notice['total']})";
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php unset($_SESSION['cart_merge_notice']); ?>
<?php endif; ?>

<header class="main-header">
    <div class="container-fluid header-container" style="padding: 0 20px;">
        <div class="logo" onclick="location.href='<?php echo $base_url; ?>'" style="cursor: pointer;">SHOP<span>HUB</span></div>

        <div class="catalog-container">
    <button class="catalog-btn btn btn-dark" id="catalog-trigger" type="button">
        <i class="fas fa-bars"></i> Каталог
    </button>
    <div class="catalog-menu" id="catalog-menu">
        <ul class="catalog-list" style="list-style: none; padding: 0; margin: 0;">
            <li style="border-bottom: 1px solid #eee;">
                <a href="<?php echo $base_url; ?>index.php" class="dropdown-item py-2 px-3">
                    <i class="fas fa-th-large me-2"></i> Все товары
                </a>
            </li>
            
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="<?php echo $base_url; ?>catalog.php?category=<?php echo $cat['id']; ?>" class="dropdown-item py-2 px-3">
                            <i class="fas fa-chevron-right me-2" style="font-size: 0.8em; color: #ccc;"></i> 
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="px-3 py-2 text-muted">Категории не найдены</li>
            <?php endif; ?>
        </ul>
    </div>
</div>

        <div class="search-wrapper d-flex align-items-center flex-grow-1 ms-4 me-4" style="max-width: 100%;">
            <div style="position: relative; width: 100%;">
                <input type="text" id="global-search" class="form-control" placeholder="Поиск электроники..." autocomplete="off">
                <div id="search-results" class="search-results-dropdown"></div>
            </div>
            <button type="button" class="btn btn-primary ms-2"><i class="fas fa-search"></i></button>
        </div>

        <nav class="old-menu-wrapper d-none d-lg-block">
            <?php include_once __DIR__ . "/menu.php"; ?>
        </nav>
    </div>
</header>