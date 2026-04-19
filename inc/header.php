<?php
// Включение буферизации вывода и старт сессии
if (session_status() === PHP_SESSION_NONE) {
    ob_start();
    session_start();
}

// ===== Загружаем compare_ids из БД =====
function loadCompareIdsFromDb($db, $userId) {
    if (!isset($db) || $db->connect_error || !$userId) {
        return [];
    }
    
    $stmt = $db->prepare("SELECT product_id FROM `compare` WHERE user_id = ? ORDER BY created_at DESC");
    if (!$stmt) return [];
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int)$row['product_id'];
    }
    $stmt->close();
    return $ids;
}

// Определение корневого пути проекта
$project_folder = '/mobileshop/';
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . $project_folder;

// Подключение к базе данных и функциям
require_once $_SERVER['DOCUMENT_ROOT'] . '/mobileshop/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/mobileshop/inc/functions.php';

// Проверяем, что $db существует
if (!isset($db) || !$db || $db->connect_error) {
    $db_error = true;
    error_log("Ошибка подключения к БД в header.php");
} else {
    $db_error = false;
}

// Генерация CSRF-токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Загружаем compare_ids
if (isset($_SESSION['id']) && !$db_error) {
    if (!isset($_SESSION['compare_ids']) || !is_array($_SESSION['compare_ids'])) {
        $_SESSION['compare_ids'] = loadCompareIdsFromDb($db, $_SESSION['id']);
    }
} elseif (!isset($_SESSION['compare_ids'])) {
    $_SESSION['compare_ids'] = [];
}

// Получение количества товаров в корзине
$cart_count = 0;
if (!$db_error && isset($_SESSION['id'])) {
    $tables_exist = false;
    $check_cart = $db->query("SHOW TABLES LIKE 'cart'");
    $check_cart_items = $db->query("SHOW TABLES LIKE 'cart_items'");
    if ($check_cart && $check_cart_items && $check_cart->num_rows > 0 && $check_cart_items->num_rows > 0) {
        $tables_exist = true;
    }
    if ($tables_exist) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(ci.quantity), 0) as total FROM cart c LEFT JOIN cart_items ci ON ci.cart_id = c.id WHERE c.user_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $_SESSION['id']);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $cart_count = $result['total'] ?? 0;
            $stmt->close();
        }
    }
} else {
    $cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
}

$_SESSION['cart_count'] = $cart_count;
$compare_count = count($_SESSION['compare_ids'] ?? []);

// ПОЛУЧЕНИЕ КАТЕГОРИЙ
$categories = [];
if (!$db_error) {
    $cat_query = "SELECT id, name FROM categories ORDER BY name ASC";
    $cat_result = $db->query($cat_query);
    if ($cat_result && $cat_result->num_rows > 0) {
        while ($row = $cat_result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Интернет магазин - ShopHub</title>
    
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    
    <script>
        window.BASE_URL = '<?= $base_url ?>';
        window.INITIAL_COMPARE_IDS = <?= json_encode($_SESSION['compare_ids'] ?? []) ?>;
        window.INITIAL_COMPARE_COUNT = <?= $compare_count ?>;
    </script>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>inc/styles.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">

    <style>
        .catalog-container { position: relative; }
        .catalog-menu {
            display: none;
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
        .catalog-menu.active { display: block; }
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
        
        /* Dropdown меню админа */
        .dropdown-menu {
            z-index: 9999 !important;
        }
        .dropdown {
            position: relative !important;
        }
        .old-menu-wrapper {
            position: relative;
            z-index: 1000;
        }
        .navigation-wrapper {
            position: relative;
            z-index: 1001;
        }
        
        .cart-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #fff;
            text-decoration: none;
            padding: 8px 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 25px;
            transition: all 0.2s;
        }
        .cart-link:hover {
            background: rgba(255,255,255,0.2);
            color: #fff;
        }
        .cart-count {
            background: #ff4757;
            color: white;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 12px;
            min-width: 22px;
            text-align: center;
        }
    </style>
</head>
<body>

<?php if (isset($_SESSION['cart_merge_notice'])): ?>
    <div class="container mt-3">
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-shopping-cart me-2"></i>
            <?php 
            $notice = $_SESSION['cart_merge_notice'];
            $parts = [];
            if ($notice['added'] > 0) $parts[] = "добавлено {$notice['added']} новых";
            if ($notice['merged'] > 0) $parts[] = "объединено {$notice['merged']} существующих";
            echo "В вашу корзину " . implode(' и ', $parts) . " товаров из гостевой сессии (всего {$notice['total']})";
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php unset($_SESSION['cart_merge_notice']); ?>
<?php endif; ?>

<header class="main-header" style="position: relative; z-index: 1000;">
    <div class="container-fluid header-container" style="padding: 0 20px;">
        <div class="logo" onclick="location.href='<?php echo $base_url; ?>'" style="cursor: pointer;">SHOP<span>HUB</span></div>

        <div class="catalog-container">
            <button class="catalog-btn btn btn-dark" id="catalog-trigger" type="button">
                <i class="fas fa-bars"></i> Каталог
            </button>
            <div class="catalog-menu" id="catalog-menu">
                <ul class="catalog-list">
                    <li style="border-bottom: 1px solid #eee;">
                        <a href="<?php echo $base_url; ?>index.php">
                            <i class="fas fa-th-large me-2"></i> Все товары
                        </a>
                    </li>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <li>
                                <a href="<?php echo $base_url; ?>catalog.php?category=<?php echo $cat['id']; ?>">
                                    <i class="fas fa-chevron-right me-2"></i> 
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
            <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/mobileshop/inc/menu.php'; ?>
        </nav>
    </div>
</header>

<!-- 🔥 КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Bootstrap JS подключаем здесь для ВСЕХ страниц -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- 🔥 КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Инициализация dropdown с проверкой дублирования -->
<script>
// Глобальные переменные с проверкой существования
if (typeof window.API_CONFIG === 'undefined') {
    window.API_CONFIG = {
        cartBaseUrl: (window.BASE_URL || '/mobileshop/') + 'pages/cart/Api_Cart.php'
    };
}

// 🔥 ГЛОБАЛЬНАЯ ФУНКЦИЯ: Инициализация всех dropdown на странице
window.initBootstrapDropdowns = function() {
    'use strict';
    
    console.log('🔍 Проверка Bootstrap:', typeof bootstrap);
    
    if (typeof bootstrap === 'undefined') {
        console.error('❌ Bootstrap не загружен!');
        return false;
    }
    
    var dropdownToggles = document.querySelectorAll('[data-bs-toggle="dropdown"]');
    console.log('📍 Найдено dropdown toggles:', dropdownToggles.length);
    
    if (dropdownToggles.length === 0) {
        console.log('ℹ️ Нет dropdown для инициализации');
        return true;
    }
    
    var initialized = 0;
    
    dropdownToggles.forEach(function(toggle, index) {
        try {
            // Проверяем, не инициализирован ли уже
            var existing = bootstrap.Dropdown.getInstance(toggle);
            if (existing) {
                console.log('✅ Dropdown #' + index + ' уже инициализирован');
                initialized++;
                return;
            }
            
            // Создаем новый экземпляр
            var dropdown = new bootstrap.Dropdown(toggle, {
                autoClose: true,
                boundary: 'window'
            });
            
            console.log('✅ Dropdown #' + index + ' инициализирован:', toggle.id || toggle.textContent.trim());
            initialized++;
        } catch (error) {
            console.error('❌ Ошибка инициализации dropdown #' + index + ':', error);
        }
    });
    
    console.log('🎉 Инициализировано dropdown: ' + initialized + '/' + dropdownToggles.length);
    return initialized > 0;
};

// Запускаем инициализацию
(function init() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.initBootstrapDropdowns);
    } else {
        // DOM уже загружен, запускаем сразу
        window.initBootstrapDropdowns();
    }
})();
</script>