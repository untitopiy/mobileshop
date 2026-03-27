<?php
// Настройки подключения к базе данных
$host = "localhost";
$port = 3306;
$user = "root";
$password = ""; // Укажите свой пароль
$dbname = "marketplace";

// Подключение к базе данных
$db = new mysqli($host, $user, $password, $dbname, $port);

// Проверка подключения
if ($db->connect_error) {
    die("Ошибка подключения к базе данных: " . $db->connect_error);
}

// Установка кодировки UTF-8
if (!$db->set_charset("utf8mb4")) {
    die("Ошибка установки кодировки UTF-8: " . $db->error);
}

// Сообщение об успешном подключении (опционально для отладки)
// echo "Подключение к базе данных установлено";
?>
