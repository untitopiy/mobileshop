<?php
// Включение буферизации вывода и старт сессии
if (session_status() === PHP_SESSION_NONE) {
    ob_start();
    session_start();
}

// Определение корневого пути проекта
$project_folder = '/mobileshop/'; // Укажите путь к корневой папке проекта (например, /onlinementor/)
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . $project_folder;

// Подключение к базе данных
require_once __DIR__ . "/db.php";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Интернет магазин - MobShop</title>
    
    <!-- Подключение Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <!-- Подключение собственных стилей -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>inc/styles.css">
</head>
<body>
    <!-- Верхняя панель -->
    <header class="bg-light border-bottom py-3">
        <div class="container d-flex align-items-center justify-content-between">
            <!-- Логотип -->
            <a href="<?php echo $base_url; ?>" class="d-flex align-items-center text-decoration-none">
                <img src="<?php echo $base_url; ?>assets/logo.webp" alt="Логотип" style="height: 40px;" class="me-2">
                <span class="fs-4 text-dark">Интернет магазин - MobShop</span>
            </a>
            
            <!-- Меню -->
            <nav>
                <?php include_once __DIR__ . "/menu.php"; ?>
            </nav>
        </div>
    </header>
