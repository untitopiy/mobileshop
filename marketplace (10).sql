-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Мар 23 2026 г., 23:21
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `marketplace`
--

-- --------------------------------------------------------

--
-- Структура таблицы `attributes`
--

CREATE TABLE `attributes` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `type` enum('text','number','select','multiselect','boolean','color') NOT NULL DEFAULT 'text',
  `unit` varchar(50) DEFAULT NULL,
  `is_filterable` tinyint(1) NOT NULL DEFAULT 1,
  `is_visible_on_product` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `attributes`
--

INSERT INTO `attributes` (`id`, `name`, `slug`, `type`, `unit`, `is_filterable`, `is_visible_on_product`, `sort_order`, `created_at`) VALUES
(76, 'Модель процессора', 'nb_cpu_model', 'text', NULL, 1, 1, 20, '2026-03-21 09:50:12'),
(77, 'Количество ядер', 'nb_cpu_cores', 'number', 'шт', 1, 1, 21, '2026-03-21 09:50:12'),
(78, 'Частота процессора', 'nb_cpu_frequency', 'number', 'ГГц', 1, 1, 22, '2026-03-21 09:50:12'),
(79, 'Тип оперативной памяти', 'nb_ram_type', 'select', NULL, 1, 1, 23, '2026-03-21 09:50:12'),
(80, 'Объем оперативной памяти', 'nb_ram_size', 'number', 'ГБ', 1, 1, 24, '2026-03-21 09:50:12'),
(81, 'Тип накопителя', 'nb_storage_type', 'select', NULL, 1, 1, 25, '2026-03-21 09:50:12'),
(82, 'Объем SSD', 'nb_ssd_size', 'number', 'ГБ', 0, 1, 26, '2026-03-21 09:50:12'),
(83, 'Объем HDD', 'nb_hdd_size', 'number', 'ГБ', 0, 1, 27, '2026-03-21 09:50:12'),
(84, 'Видеокарта', 'nb_gpu', 'text', NULL, 1, 1, 28, '2026-03-21 09:50:12'),
(85, 'Тип видеокарты', 'nb_gpu_type', 'select', NULL, 1, 1, 29, '2026-03-21 09:50:12'),
(86, 'Диагональ экрана', 'nb_screen_size', 'number', 'дюйм', 1, 1, 30, '2026-03-21 09:50:12'),
(87, 'Тип матрицы', 'nb_screen_type', 'select', NULL, 1, 1, 31, '2026-03-21 09:50:12'),
(88, 'Разрешение экрана', 'nb_screen_resolution', 'select', NULL, 1, 1, 32, '2026-03-21 09:50:12'),
(89, 'Материал корпуса', 'nb_case_material', 'select', NULL, 1, 1, 33, '2026-03-21 09:50:12'),
(90, 'Операционная система', 'nb_os', 'select', NULL, 1, 1, 34, '2026-03-21 09:50:12'),
(91, 'Цвет', 'nb_color', 'select', NULL, 1, 1, 35, '2026-03-21 09:50:12'),
(92, 'Тип дисплея', 'sm_display_type', 'select', NULL, 1, 1, 12, '2026-03-21 09:50:46'),
(93, 'Частота обновления', 'sm_refresh_rate', 'number', 'Гц', 1, 1, 13, '2026-03-21 09:50:46'),
(94, 'Защита экрана', 'sm_screen_protection', 'select', NULL, 1, 1, 14, '2026-03-21 09:50:46'),
(95, 'Поддержка 5G', 'sm_support_5g', 'boolean', NULL, 1, 1, 15, '2026-03-21 09:50:46'),
(96, 'NFC', 'sm_nfc', 'boolean', NULL, 1, 1, 16, '2026-03-21 09:50:46'),
(97, 'Беспроводная зарядка', 'sm_wireless_charging', 'boolean', NULL, 1, 1, 17, '2026-03-21 09:50:46'),
(98, 'Быстрая зарядка', 'sm_fast_charging', 'number', 'Вт', 1, 1, 18, '2026-03-21 09:50:46'),
(99, 'Сканер отпечатка пальца', 'sm_fingerprint', 'boolean', NULL, 1, 1, 19, '2026-03-21 09:50:46'),
(100, 'Тип наушников', 'hp_type', 'select', NULL, 1, 1, 50, '2026-03-21 09:50:56'),
(101, 'Тип подключения', 'hp_connection', 'select', NULL, 1, 1, 51, '2026-03-21 09:50:56'),
(102, 'Частотный диапазон', 'hp_frequency', 'text', 'Гц-кГц', 1, 1, 52, '2026-03-21 09:50:56'),
(103, 'Сопротивление', 'hp_impedance', 'number', 'Ом', 1, 1, 53, '2026-03-21 09:50:56'),
(104, 'Чувствительность', 'hp_sensitivity', 'number', 'дБ', 1, 1, 54, '2026-03-21 09:50:56'),
(105, 'Время работы', 'hp_battery_life', 'number', 'ч', 1, 1, 55, '2026-03-21 09:50:56'),
(106, 'Наличие микрофона', 'hp_has_mic', 'boolean', NULL, 1, 1, 56, '2026-03-21 09:50:56'),
(107, 'Активное шумоподавление', 'hp_noise_cancelling', 'boolean', NULL, 1, 1, 57, '2026-03-21 09:50:56'),
(108, 'Цвет', 'hp_color', 'select', NULL, 1, 1, 58, '2026-03-21 09:50:56'),
(109, 'Вес', 'hp_weight', 'number', 'г', 0, 1, 59, '2026-03-21 09:50:56'),
(110, 'Мощность', 'ch_power', 'number', 'Вт', 1, 1, 80, '2026-03-21 09:51:05'),
(111, 'Количество портов', 'ch_ports_count', 'number', 'шт', 1, 1, 81, '2026-03-21 09:51:05'),
(112, 'Тип портов', 'ch_port_type', 'select', NULL, 1, 1, 82, '2026-03-21 09:51:05'),
(113, 'Быстрая зарядка', 'ch_fast_charging', 'boolean', NULL, 1, 1, 83, '2026-03-21 09:51:05'),
(114, 'Беспроводная зарядка', 'ch_wireless', 'boolean', NULL, 1, 1, 84, '2026-03-21 09:51:05'),
(115, 'Длина кабеля', 'ch_cable_length', 'number', 'м', 1, 1, 85, '2026-03-21 09:51:05'),
(116, 'Цвет', 'ch_color', 'select', NULL, 1, 1, 86, '2026-03-21 09:51:05'),
(117, 'Тип стекла', 'gl_type', 'select', NULL, 1, 1, 90, '2026-03-21 09:51:23'),
(118, 'Твердость', 'gl_hardness', 'select', NULL, 1, 1, 91, '2026-03-21 09:51:23'),
(119, 'Олеофобное покрытие', 'gl_oleophobic', 'boolean', NULL, 1, 1, 92, '2026-03-21 09:51:23'),
(120, 'Толщина', 'gl_thickness', 'number', 'мм', 1, 1, 93, '2026-03-21 09:51:23'),
(121, 'Совместимость с чехлами', 'gl_case_compatible', 'boolean', NULL, 1, 1, 94, '2026-03-21 09:51:23');

-- --------------------------------------------------------

--
-- Структура таблицы `attribute_values`
--

CREATE TABLE `attribute_values` (
  `id` int(11) NOT NULL,
  `attribute_id` int(11) NOT NULL,
  `value` varchar(255) NOT NULL,
  `swatch_value` varchar(255) DEFAULT NULL COMMENT 'Для цветов/изображений',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `attribute_values`
--

INSERT INTO `attribute_values` (`id`, `attribute_id`, `value`, `swatch_value`, `sort_order`, `created_at`) VALUES
(1, 81, 'SSD', NULL, 1, '2026-03-21 09:51:49'),
(2, 81, 'HDD', NULL, 2, '2026-03-21 09:51:49'),
(3, 81, 'SSD+HDD', NULL, 3, '2026-03-21 09:51:49'),
(4, 85, 'Встроенная', NULL, 1, '2026-03-21 09:51:49'),
(5, 85, 'Дискретная', NULL, 2, '2026-03-21 09:51:49'),
(6, 85, 'Встроенная+Дискретная', NULL, 3, '2026-03-21 09:51:49'),
(7, 87, 'IPS', NULL, 1, '2026-03-21 09:51:49'),
(8, 87, 'OLED', NULL, 2, '2026-03-21 09:51:49'),
(9, 87, 'AMOLED', NULL, 3, '2026-03-21 09:51:49'),
(10, 87, 'TN', NULL, 4, '2026-03-21 09:51:49'),
(11, 87, 'VA', NULL, 5, '2026-03-21 09:51:49'),
(12, 88, '1366x768', NULL, 1, '2026-03-21 09:51:50'),
(13, 88, '1920x1080 (Full HD)', NULL, 2, '2026-03-21 09:51:50'),
(14, 88, '2560x1440 (2K)', NULL, 3, '2026-03-21 09:51:50'),
(15, 88, '3840x2160 (4K)', NULL, 4, '2026-03-21 09:51:50'),
(16, 88, '2880x1800', NULL, 5, '2026-03-21 09:51:50'),
(17, 89, 'Алюминий', NULL, 1, '2026-03-21 09:51:50'),
(18, 89, 'Пластик', NULL, 2, '2026-03-21 09:51:50'),
(19, 89, 'Магниевый сплав', NULL, 3, '2026-03-21 09:51:50'),
(20, 89, 'Карбон', NULL, 4, '2026-03-21 09:51:50'),
(21, 89, 'Стекло', NULL, 5, '2026-03-21 09:51:50'),
(22, 90, 'Windows 10', NULL, 1, '2026-03-21 09:51:50'),
(23, 90, 'Windows 11', NULL, 2, '2026-03-21 09:51:50'),
(24, 90, 'Linux', NULL, 3, '2026-03-21 09:51:50'),
(25, 90, 'macOS', NULL, 4, '2026-03-21 09:51:50'),
(26, 90, 'Chrome OS', NULL, 5, '2026-03-21 09:51:50'),
(27, 91, 'Черный', NULL, 1, '2026-03-21 09:51:50'),
(28, 91, 'Белый', NULL, 2, '2026-03-21 09:51:50'),
(29, 91, 'Серебристый', NULL, 3, '2026-03-21 09:51:50'),
(30, 91, 'Золотой', NULL, 4, '2026-03-21 09:51:50'),
(31, 91, 'Синий', NULL, 5, '2026-03-21 09:51:50'),
(32, 91, 'Красный', NULL, 6, '2026-03-21 09:51:50'),
(33, 91, 'Зеленый', NULL, 7, '2026-03-21 09:51:50'),
(34, 79, 'DDR4', NULL, 1, '2026-03-21 11:07:12'),
(35, 79, 'DDR5', NULL, 2, '2026-03-21 11:07:12'),
(36, 79, 'LPDDR4', NULL, 3, '2026-03-21 11:07:12'),
(37, 79, 'LPDDR5', NULL, 4, '2026-03-21 11:07:12'),
(38, 112, 'USB-A', NULL, 1, '2026-03-21 18:45:14'),
(39, 112, 'USB-C', NULL, 2, '2026-03-21 18:45:14'),
(40, 112, 'USB-A + USB-C', NULL, 3, '2026-03-21 18:45:14'),
(41, 112, 'Lightning', NULL, 4, '2026-03-21 18:45:14'),
(42, 112, 'Micro-USB', NULL, 5, '2026-03-21 18:45:14'),
(43, 117, 'Закаленное стекло', NULL, 1, '2026-03-21 18:45:14'),
(44, 117, 'Гибкое стекло', NULL, 2, '2026-03-21 18:45:14'),
(45, 117, 'Керамическое стекло', NULL, 3, '2026-03-21 18:45:14'),
(46, 117, 'Сапфировое стекло', NULL, 4, '2026-03-21 18:45:14'),
(47, 117, 'Антибликовое стекло', NULL, 5, '2026-03-21 18:45:14'),
(48, 118, '9H', NULL, 1, '2026-03-21 18:45:14'),
(49, 118, '10H', NULL, 2, '2026-03-21 18:45:14'),
(50, 118, '8H', NULL, 3, '2026-03-21 18:45:14'),
(51, 100, 'Вкладыши', NULL, 1, '2026-03-21 18:45:14'),
(52, 100, 'Внутриканальные', NULL, 2, '2026-03-21 18:45:14'),
(53, 100, 'Накладные', NULL, 3, '2026-03-21 18:45:14'),
(54, 100, 'Полноразмерные', NULL, 4, '2026-03-21 18:45:14'),
(55, 100, 'Спортивные', NULL, 5, '2026-03-21 18:45:14'),
(56, 101, 'Проводные (3.5mm)', NULL, 1, '2026-03-21 18:45:14'),
(57, 101, 'Проводные (USB-C)', NULL, 2, '2026-03-21 18:45:14'),
(58, 101, 'Bluetooth', NULL, 3, '2026-03-21 18:45:14'),
(59, 101, 'Bluetooth 5.0', NULL, 4, '2026-03-21 18:45:14'),
(60, 101, 'Bluetooth 5.2', NULL, 5, '2026-03-21 18:45:14'),
(61, 101, 'Bluetooth 5.3', NULL, 6, '2026-03-21 18:45:14'),
(62, 92, 'IPS', NULL, 1, '2026-03-21 18:45:14'),
(63, 92, 'OLED', NULL, 2, '2026-03-21 18:45:14'),
(64, 92, 'AMOLED', NULL, 3, '2026-03-21 18:45:14'),
(65, 92, 'Super AMOLED', NULL, 4, '2026-03-21 18:45:14'),
(66, 92, 'Dynamic AMOLED', NULL, 5, '2026-03-21 18:45:14'),
(67, 92, 'LTPO OLED', NULL, 6, '2026-03-21 18:45:14'),
(68, 94, 'Gorilla Glass Victus', NULL, 1, '2026-03-21 18:45:14'),
(69, 94, 'Gorilla Glass Victus 2', NULL, 2, '2026-03-21 18:45:14'),
(70, 94, 'Gorilla Glass 5', NULL, 3, '2026-03-21 18:45:14'),
(71, 94, 'Ceramic Shield', NULL, 4, '2026-03-21 18:45:14'),
(72, 94, 'Dragontrail', NULL, 5, '2026-03-21 18:45:14'),
(73, 94, 'Закаленное стекло', NULL, 6, '2026-03-21 18:45:14'),
(74, 108, 'Черный', NULL, 1, '2026-03-21 18:59:33'),
(75, 108, 'Белый', NULL, 2, '2026-03-21 18:59:33'),
(76, 108, 'Красный', NULL, 3, '2026-03-21 18:59:33'),
(77, 108, 'Синий', NULL, 4, '2026-03-21 18:59:33'),
(78, 108, 'Розовый', NULL, 5, '2026-03-21 18:59:33'),
(79, 108, 'Зеленый', NULL, 6, '2026-03-21 18:59:33'),
(80, 108, 'Серый', NULL, 7, '2026-03-21 18:59:33'),
(81, 116, 'Черный', NULL, 1, '2026-03-21 18:59:33'),
(82, 116, 'Белый', NULL, 2, '2026-03-21 18:59:33'),
(83, 116, 'Серебристый', NULL, 3, '2026-03-21 18:59:33'),
(84, 116, 'Золотой', NULL, 4, '2026-03-21 18:59:33'),
(85, 116, 'Красный', NULL, 5, '2026-03-21 18:59:33'),
(86, 116, 'Синий', NULL, 6, '2026-03-21 18:59:33');

-- --------------------------------------------------------

--
-- Структура таблицы `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `session_id`, `created_at`, `updated_at`) VALUES
(1, 25, NULL, '2026-03-19 16:36:54', '2026-03-19 16:36:54'),
(2, 28, NULL, '2026-03-20 10:19:16', '2026-03-20 10:19:16'),
(3, 9, NULL, '2026-03-20 16:39:22', '2026-03-20 16:39:22');

-- --------------------------------------------------------

--
-- Структура таблицы `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variation_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL COMMENT 'Цена на момент добавления',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `cart_items`
--

INSERT INTO `cart_items` (`id`, `cart_id`, `product_id`, `variation_id`, `quantity`, `price`, `created_at`, `updated_at`) VALUES
(3, 2, 21, NULL, 1, 64.00, '2026-03-20 10:19:16', '2026-03-20 10:19:16');

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `image_url`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Смартфоны', 'smartfony', 'Мобильные телефоны и смартфоны', NULL, 0, 1, '2026-03-19 11:47:50', '2026-03-19 11:47:50'),
(2, NULL, 'Аксессуары', 'aksessuary', '0', NULL, 0, 1, '2026-03-19 11:47:50', '2026-03-21 20:54:42'),
(3, 2, 'Чехлы', 'chekhly', 'Защитные чехлы для телефонов', NULL, 0, 1, '2026-03-19 11:47:50', '2026-03-19 11:47:50'),
(4, 2, 'Защитные стекла', 'stekla', 'Защитные стекла и пленки', NULL, 0, 1, '2026-03-19 11:47:50', '2026-03-19 11:47:50'),
(5, 2, 'Зарядные устройства', 'zaryadki', 'Блоки питания и зарядки', NULL, 0, 1, '2026-03-19 11:47:50', '2026-03-19 11:47:50'),
(6, 2, 'Наушники', 'naushniki', 'Проводные и беспроводные наушники', NULL, 0, 1, '2026-03-19 11:47:50', '2026-03-19 11:47:50'),
(7, NULL, 'Ноутбуки', 'notebooks', NULL, NULL, 0, 1, '2026-03-21 09:16:28', '2026-03-21 09:16:28');

-- --------------------------------------------------------

--
-- Структура таблицы `category_attributes`
--

CREATE TABLE `category_attributes` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `attribute_id` int(11) NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Обязательное поле',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `category_attributes`
--

INSERT INTO `category_attributes` (`id`, `category_id`, `attribute_id`, `is_required`, `sort_order`, `created_at`) VALUES
(43, 7, 76, 1, 20, '2026-03-21 09:51:34'),
(44, 7, 77, 1, 21, '2026-03-21 09:51:34'),
(45, 7, 78, 0, 22, '2026-03-21 09:51:34'),
(46, 7, 79, 0, 23, '2026-03-21 09:51:34'),
(47, 7, 80, 1, 24, '2026-03-21 09:51:34'),
(48, 7, 81, 1, 25, '2026-03-21 09:51:34'),
(49, 7, 82, 0, 26, '2026-03-21 09:51:34'),
(50, 7, 83, 0, 27, '2026-03-21 09:51:34'),
(51, 7, 84, 1, 28, '2026-03-21 09:51:34'),
(52, 7, 85, 0, 29, '2026-03-21 09:51:34'),
(53, 7, 86, 0, 30, '2026-03-21 09:51:34'),
(54, 7, 87, 0, 31, '2026-03-21 09:51:34'),
(55, 7, 88, 1, 32, '2026-03-21 09:51:34'),
(56, 7, 89, 0, 33, '2026-03-21 09:51:34'),
(57, 7, 90, 1, 34, '2026-03-21 09:51:34'),
(58, 7, 91, 1, 35, '2026-03-21 09:51:34'),
(74, 1, 92, 1, 12, '2026-03-21 09:51:34'),
(75, 1, 93, 1, 13, '2026-03-21 09:51:34'),
(76, 1, 94, 0, 14, '2026-03-21 09:51:34'),
(77, 1, 95, 1, 15, '2026-03-21 09:51:34'),
(78, 1, 96, 0, 16, '2026-03-21 09:51:34'),
(79, 1, 97, 0, 17, '2026-03-21 09:51:34'),
(80, 1, 98, 0, 18, '2026-03-21 09:51:34'),
(81, 1, 99, 0, 19, '2026-03-21 09:51:34'),
(89, 6, 100, 1, 50, '2026-03-21 09:51:34'),
(90, 6, 101, 1, 51, '2026-03-21 09:51:34'),
(91, 6, 102, 0, 52, '2026-03-21 09:51:34'),
(92, 6, 103, 0, 53, '2026-03-21 09:51:34'),
(93, 6, 104, 0, 54, '2026-03-21 09:51:34'),
(94, 6, 105, 0, 55, '2026-03-21 09:51:34'),
(95, 6, 106, 0, 56, '2026-03-21 09:51:34'),
(96, 6, 107, 0, 57, '2026-03-21 09:51:34'),
(97, 6, 108, 0, 58, '2026-03-21 09:51:34'),
(98, 6, 109, 0, 59, '2026-03-21 09:51:34'),
(104, 5, 115, 0, 85, '2026-03-21 09:51:34'),
(105, 5, 116, 0, 86, '2026-03-21 09:51:34'),
(106, 5, 113, 0, 83, '2026-03-21 09:51:34'),
(107, 5, 112, 0, 82, '2026-03-21 09:51:34'),
(108, 5, 111, 1, 81, '2026-03-21 09:51:34'),
(109, 5, 110, 1, 80, '2026-03-21 09:51:34'),
(110, 5, 114, 0, 84, '2026-03-21 09:51:34'),
(111, 4, 121, 0, 94, '2026-03-21 09:51:34'),
(112, 4, 118, 1, 91, '2026-03-21 09:51:34'),
(113, 4, 119, 0, 92, '2026-03-21 09:51:34'),
(114, 4, 120, 0, 93, '2026-03-21 09:51:34'),
(115, 4, 117, 1, 90, '2026-03-21 09:51:34');

-- --------------------------------------------------------

--
-- Структура таблицы `chat_logs`
--

CREATE TABLE `chat_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_message` text NOT NULL,
  `bot_response` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `chat_logs`
--

INSERT INTO `chat_logs` (`id`, `user_id`, `user_message`, `bot_response`, `ip_address`, `created_at`) VALUES
(1, 9, 'Какие способы доставки?', 'Извините, я не понял вопрос. Попробуйте спросить по-другому.', '::1', '2026-03-21 21:57:10');

-- --------------------------------------------------------

--
-- Структура таблицы `compare`
--

CREATE TABLE `compare` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `compare`
--

INSERT INTO `compare` (`id`, `user_id`, `product_id`, `created_at`) VALUES
(64, 25, 61, '2026-03-22 14:59:18'),
(65, 25, 20, '2026-03-22 14:59:18'),
(66, 25, 35, '2026-03-22 14:59:18'),
(87, 9, 4, '2026-03-22 17:08:01'),
(88, 9, 63, '2026-03-22 17:08:02');

-- --------------------------------------------------------

--
-- Структура таблицы `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('global','personal','seller') NOT NULL DEFAULT 'global',
  `seller_id` int(11) DEFAULT NULL,
  `discount_type` enum('percent','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT NULL,
  `max_discount_amount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL COMMENT 'Общий лимит использований',
  `usage_limit_per_user` int(11) DEFAULT 1,
  `used_count` int(11) NOT NULL DEFAULT 0,
  `starts_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `name`, `description`, `type`, `seller_id`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount_amount`, `usage_limit`, `usage_limit_per_user`, `used_count`, `starts_at`, `expires_at`, `status`, `created_at`, `updated_at`) VALUES
(1, 'USER271EA14D31', 'Приветственный купон для User30', NULL, 'personal', NULL, 'percent', 10.00, NULL, NULL, NULL, 1, 0, '2026-03-20 09:51:13', '2026-04-19 09:51:13', 'active', '2026-03-20 08:51:13', '2026-03-20 08:51:13'),
(2, 'USER28C730643D', 'Приветственный купон для User31', NULL, 'personal', NULL, 'percent', 10.00, NULL, NULL, NULL, 1, 0, '2026-03-20 09:54:33', '2026-04-19 09:54:33', 'active', '2026-03-20 08:54:33', '2026-03-20 08:54:33'),
(3, 'QWESDFERGFDVD', 'Google Pixel 7', '', 'global', NULL, 'percent', 10.00, NULL, NULL, 2, 1, 0, '2026-03-22 00:00:00', '2026-04-22 00:00:00', 'active', '2026-03-22 15:01:11', '2026-03-22 15:01:11');

-- --------------------------------------------------------

--
-- Структура таблицы `coupon_categories`
--

CREATE TABLE `coupon_categories` (
  `coupon_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `coupon_products`
--

CREATE TABLE `coupon_products` (
  `coupon_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `coupon_usage`
--

CREATE TABLE `coupon_usage` (
  `id` int(11) NOT NULL,
  `coupon_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `coupon_usage`
--

INSERT INTO `coupon_usage` (`id`, `coupon_id`, `user_id`, `order_id`, `discount_amount`, `used_at`) VALUES
(1, 3, 9, 36, 369.90, '2026-03-22 15:02:59');

-- --------------------------------------------------------

--
-- Структура таблицы `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `shipping_method_id` int(11) DEFAULT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `shipping_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL,
  `coupon_code` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `status_id`, `shipping_method_id`, `payment_method_id`, `subtotal`, `shipping_price`, `discount_amount`, `total_price`, `coupon_code`, `notes`, `admin_notes`, `created_at`, `updated_at`) VALUES
(20, 'ORD-20251015-20', 20, 1, 1, 1, 16199.82, 0.00, 0.00, 16199.82, NULL, NULL, NULL, '2025-10-15 18:16:15', '2025-10-15 18:16:15'),
(21, 'ORD-20251016-21', 20, 1, 1, 1, 119999.00, 0.00, 0.00, 119999.00, NULL, NULL, NULL, '2025-10-16 06:41:00', '2025-10-16 06:41:00'),
(27, 'ORD-20251016-27', 9, 1, 1, 1, 109999.00, 0.00, 0.00, 109999.00, NULL, NULL, NULL, '2025-10-16 15:52:07', '2025-10-16 15:52:07'),
(36, 'ORD-20260322-9-83595', 9, 1, 1, 1, 3699.00, 0.00, 369.90, 3329.10, 'QWESDFERGFDVD', 'FDSFSF', NULL, '2026-03-22 15:02:59', '2026-03-22 15:02:59');

-- --------------------------------------------------------

--
-- Структура таблицы `order_addresses`
--

CREATE TABLE `order_addresses` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `type` enum('shipping','billing') NOT NULL DEFAULT 'shipping',
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `country` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `order_addresses`
--

INSERT INTO `order_addresses` (`id`, `order_id`, `type`, `full_name`, `phone`, `email`, `country`, `city`, `address`, `postal_code`, `created_at`) VALUES
(1, 20, 'shipping', 'qeqeqwe', '+375333362836', 'dxddxd137@gmail.com', 'Беларусь', 'Минск', 'йуйуйцуйцуй', NULL, '2026-03-19 12:19:37'),
(2, 21, 'shipping', '23', '1233', 'dxddxd137@gmail.com', 'Беларусь', 'Минск', '12312', NULL, '2026-03-19 12:19:37'),
(3, 27, 'shipping', 'qeqeqwe', '+375333362836', 'mtest8557@gmail.com', 'Беларусь', 'Минск', 'asdadasdads', NULL, '2026-03-19 12:19:37'),
(6, 36, 'shipping', 'Qwerty', '4234423', 'mtest8557@gmail.com', 'Беларусь', '234', 'г. 234, ул. 234, д. 234, кв. 23, подъезд ыва, этаж 234', '', '2026-03-22 15:02:59');

-- --------------------------------------------------------

--
-- Структура таблицы `order_discounts`
--

CREATE TABLE `order_discounts` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `coupon_id` int(11) NOT NULL,
  `discount_type` enum('percent','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_seller_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variation_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_sku` varchar(100) DEFAULT NULL,
  `attributes_text` text DEFAULT NULL COMMENT 'Текстовое описание вариации',
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `order_items`
--

INSERT INTO `order_items` (`id`, `order_seller_id`, `product_id`, `variation_id`, `product_name`, `product_sku`, `attributes_text`, `quantity`, `price`, `total_price`, `created_at`) VALUES
(1, 1, 2, NULL, 'iPhone 15', 'SM-IP15-002', NULL, 1, 17999.80, 17999.80, '2026-03-19 12:19:29'),
(2, 2, 3, NULL, 'Samsung Galaxy S23 Ultra', 'SM-S23U-003', NULL, 1, 119999.00, 119999.00, '2026-03-19 12:19:29'),
(3, 3, 1, NULL, 'iPhone 15 Pro', 'SM-IP15PRO-001', NULL, 1, 109999.00, 109999.00, '2026-03-19 12:19:29'),
(22, 6, 47, NULL, 'SoundAir Беспроводные наушники-вкладыши для iPhone', '', NULL, 1, 550.00, 495.00, '2026-03-22 15:02:59'),
(23, 6, 7, 7, 'Google Google Pixel 7 Pro', 'SM-G7P-007-DEFAULT', NULL, 1, 3149.00, 2834.10, '2026-03-22 15:02:59');

-- --------------------------------------------------------

--
-- Структура таблицы `order_sellers`
--

CREATE TABLE `order_sellers` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `shipping_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL,
  `commission_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `seller_payout` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tracking_number` varchar(255) DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `order_sellers`
--

INSERT INTO `order_sellers` (`id`, `order_id`, `seller_id`, `status_id`, `subtotal`, `shipping_price`, `discount_amount`, `total_price`, `commission_amount`, `seller_payout`, `tracking_number`, `shipped_at`, `delivered_at`, `created_at`, `updated_at`) VALUES
(1, 20, 1, 1, 16199.82, 0.00, 0.00, 16199.82, 809.99, 15389.83, NULL, NULL, NULL, '2026-03-19 12:19:21', '2026-03-19 12:19:21'),
(2, 21, 1, 1, 119999.00, 0.00, 0.00, 119999.00, 5999.95, 113999.05, NULL, NULL, NULL, '2026-03-19 12:19:21', '2026-03-19 12:19:21'),
(3, 27, 1, 1, 109999.00, 0.00, 0.00, 109999.00, 5499.95, 104499.05, NULL, NULL, NULL, '2026-03-19 12:19:21', '2026-03-19 12:19:21'),
(6, 36, 1, 1, 3699.00, 0.00, 369.90, 3329.10, 332.91, 2996.19, NULL, NULL, NULL, '2026-03-22 15:02:59', '2026-03-22 15:02:59');

-- --------------------------------------------------------

--
-- Структура таблицы `order_statuses`
--

CREATE TABLE `order_statuses` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT 'gray',
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `order_statuses`
--

INSERT INTO `order_statuses` (`id`, `name`, `slug`, `color`, `sort_order`) VALUES
(1, 'Ожидает оплаты', 'pending', 'orange', 10),
(2, 'В обработке', 'processing', 'blue', 20),
(3, 'Подтвержден', 'confirmed', 'purple', 30),
(4, 'Собирается', 'assembling', 'indigo', 40),
(5, 'Передан в доставку', 'shipped', 'cyan', 50),
(6, 'Доставлен', 'delivered', 'green', 60),
(7, 'Отменен', 'cancelled', 'red', 70),
(8, 'Возврат', 'refunded', 'gray', 80);

-- --------------------------------------------------------

--
-- Структура таблицы `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_seller_id` int(11) DEFAULT NULL,
  `status_id` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `order_status_history`
--

INSERT INTO `order_status_history` (`id`, `order_id`, `order_seller_id`, `status_id`, `comment`, `created_by`, `created_at`) VALUES
(5, 36, NULL, 1, 'Заказ создан', 9, '2026-03-22 15:02:59');

-- --------------------------------------------------------

--
-- Структура таблицы `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `icon_url` varchar(255) DEFAULT NULL,
  `commission` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `name`, `code`, `description`, `icon_url`, `commission`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'Наличными при получении', 'cash', NULL, NULL, 0.00, 1, 10, '2026-03-18 08:08:44'),
(2, 'Банковской картой на сайте', 'card_online', NULL, NULL, 1.50, 1, 20, '2026-03-18 08:08:44'),
(3, 'Банковской картой при получении', 'card_courier', NULL, NULL, 0.00, 1, 30, '2026-03-18 08:08:44'),
(4, 'Электронные кошельки', 'electronic', NULL, NULL, 1.00, 1, 40, '2026-03-18 08:08:44'),
(5, 'Криптовалюта', 'crypto', NULL, NULL, 0.50, 1, 50, '2026-03-18 08:08:44');

-- --------------------------------------------------------

--
-- Структура таблицы `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL COMMENT 'Себестоимость для продавца',
  `quantity` int(11) NOT NULL DEFAULT 0,
  `min_quantity` int(11) NOT NULL DEFAULT 1 COMMENT 'Минимальное количество для заказа',
  `max_quantity` int(11) DEFAULT NULL COMMENT 'Максимальное количество для заказа',
  `unit` varchar(20) NOT NULL DEFAULT 'шт',
  `weight` decimal(10,2) DEFAULT NULL COMMENT 'Вес в кг',
  `length` decimal(10,2) DEFAULT NULL COMMENT 'Длина в см',
  `width` decimal(10,2) DEFAULT NULL COMMENT 'Ширина в см',
  `height` decimal(10,2) DEFAULT NULL COMMENT 'Высота в см',
  `type` enum('simple','variable','digital') NOT NULL DEFAULT 'simple' COMMENT 'Тип товара',
  `status` enum('draft','pending','active','inactive','rejected') NOT NULL DEFAULT 'draft',
  `approved_at` timestamp NULL DEFAULT NULL,
  `views_count` int(11) NOT NULL DEFAULT 0,
  `sales_count` int(11) NOT NULL DEFAULT 0,
  `rating` decimal(3,2) NOT NULL DEFAULT 0.00,
  `reviews_count` int(11) NOT NULL DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `products`
--

INSERT INTO `products` (`id`, `seller_id`, `category_id`, `name`, `slug`, `sku`, `barcode`, `short_description`, `description`, `brand`, `model`, `price`, `old_price`, `cost_price`, `quantity`, `min_quantity`, `max_quantity`, `unit`, `weight`, `length`, `width`, `height`, `type`, `status`, `approved_at`, `views_count`, `sales_count`, `rating`, `reviews_count`, `meta_title`, `meta_description`, `meta_keywords`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'iPhone 15 Pro', 'iphone-15-pro', 'SM-IP15PRO-001', NULL, NULL, 'Флагманский смартфон с чипом A17 Pro, OLED-дисплеем 6.1\" и тройной камерой.', 'Apple', 'A3103', 3299.00, NULL, NULL, 15, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 2, 1, 0.00, 0, NULL, NULL, NULL, '2025-02-13 05:42:56', '2026-03-22 18:06:23'),
(2, 1, 1, 'iPhone 15', 'iphone-15', 'SM-IP15-002', NULL, NULL, 'Модель с чипом A16 Bionic, OLED-дисплеем 6.1\" и двойной камерой.', 'Apple', 'A3101', 2699.00, NULL, NULL, 20, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 2, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-13 05:42:56', '2026-03-20 15:09:09'),
(3, 1, 1, 'Samsung Galaxy S23 Ultra', 'samsung-galaxy-s23-ultra', 'SM-S23U-003', NULL, NULL, 'Флагман Samsung с 200MP камерой, 6.8\" Dynamic AMOLED дисплеем и S Pen.', 'Samsung', 'S23U', 3599.00, NULL, NULL, 10, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 1, 1, 0.00, 0, NULL, NULL, NULL, '2025-02-13 05:42:56', '2026-03-20 17:53:25'),
(4, 1, 1, 'Samsung Galaxy S23', 'samsung-galaxy-s23', 'SM-S23-004', NULL, NULL, 'Компактный смартфон с чипом Snapdragon 8 Gen 2, AMOLED 6.1\" и 50MP камерой.', 'Samsung', 'S23', 2599.00, NULL, NULL, 25, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 1, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-13 05:42:56', '2026-03-22 16:36:18'),
(5, 1, 1, 'Xiaomi 13 Pro', 'xiaomi-13-pro', 'SM-X13P-005', NULL, NULL, 'Флагман с камерой Leica, Snapdragon 8 Gen 2 и 6.73\" LTPO AMOLED дисплеем.', 'Xiaomi', 'M13P', 2399.00, NULL, NULL, 18, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 1, 1, 0.00, 0, NULL, NULL, NULL, '2025-02-13 05:42:56', '2026-03-20 17:53:25'),
(6, 1, 1, 'Xiaomi Redmi Note 12 Pro', 'xiaomi-redmi-note-12-pro', 'SM-RN12P-006', NULL, NULL, 'Смартфон среднего класса с 108MP камерой, 6.67\" OLED и 5000mAh аккумулятором.', 'Xiaomi', 'RN12P', 1049.00, NULL, NULL, 30, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-13 05:42:56', '2025-10-18 12:33:27'),
(7, 1, 1, 'Google Pixel 7 Pro', 'google-pixel-7-pro', 'SM-G7P-007', NULL, NULL, 'Флагман Google с Tensor G2, 50MP камерой и 6.7\" OLED дисплеем.', 'Google', 'G7P', 3149.00, NULL, NULL, 12, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 2, 1, 0.00, 0, NULL, NULL, NULL, '2025-02-13 05:42:56', '2026-03-22 15:02:59'),
(8, 1, 1, 'Google Pixel 7', 'google-pixel-7', 'SM-G7-008', NULL, NULL, 'Камерафон с 50MP сенсором, OLED 6.3\" дисплеем и чистым Android.', 'Google', 'G7', 865.00, NULL, NULL, 20, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 1, 1, 0.00, 0, NULL, NULL, NULL, '2025-02-13 05:42:56', '2026-03-20 17:53:25'),
(9, 1, 1, 'OnePlus 11', 'oneplus-11', 'SM-OP11-009', NULL, NULL, 'Флагман OnePlus с Snapdragon 8 Gen 2, 6.7\" AMOLED и 100W зарядкой.', 'OnePlus', 'OP11', 2249.00, NULL, NULL, 17, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 1, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-13 05:42:56', '2026-03-20 15:38:31'),
(10, 1, 1, 'Realme GT Neo 5', 'realme-gt-neo-5', 'SM-GTN5-010', NULL, NULL, 'Мощный смартфон с 144Hz дисплеем, Snapdragon 8+ Gen 1 и быстрой зарядкой 240W.', 'Realme', 'GTN5', 1679.00, NULL, NULL, 22, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-13 05:42:56', '2025-10-18 12:33:27'),
(11, 1, 1, 'Huawei P60 Pro', 'huawei-p60-pro', 'SM-P60P-011', NULL, NULL, 'Флагман с продвинутой камерой XMAGE, OLED дисплеем и 88W зарядкой.', 'Huawei', 'P60P', 2999.00, NULL, NULL, 14, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-13 05:42:56', '2025-10-18 12:33:27'),
(12, 1, 1, 'Honor Magic 5 Pro', 'honor-magic-5-pro', 'SM-M5P-012', NULL, NULL, 'Флагман с Snapdragon 8 Gen 2, 6.81\" LTPO OLED и 5100mAh аккумулятором.', 'Honor', 'M5P', 2849.00, NULL, NULL, 16, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-13 05:42:56', '2025-10-18 12:33:27'),
(13, 1, 3, 'Силиконовый чехол для iPhone 15 Pro', 'silikonoviy-chehol-iphone-15-pro', 'CASE-SIL-IP15P-013', NULL, NULL, 'Защитный силиконовый чехол для iPhone 15 Pro.', 'Generic', 'CH-IP15P', 55.00, NULL, NULL, 50, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(14, 1, 3, 'Кожаный чехол для iPhone 15', 'kozhaniy-chehol-iphone-15', 'CASE-LEA-IP15-014', NULL, NULL, 'Кожаный чехол для iPhone 15.', 'PremiumCover', 'CC-IP15', 79.00, NULL, NULL, 30, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(15, 1, 3, 'Прозрачный чехол для Samsung Galaxy S23 Ultra', 'prozrachniy-chehol-samsung-s23-ultra', 'CASE-CLR-S23U-015', NULL, NULL, 'Прозрачный чехол для Samsung Galaxy S23 Ultra.', 'ClearCase', 'CC-S23U', 42.00, NULL, NULL, 40, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(16, 1, 3, 'Бамбуковый чехол для Samsung Galaxy S23', 'bambukoviy-chehol-samsung-s23', 'CASE-BAM-S23-016', NULL, NULL, 'Бамбуковый чехол для Samsung Galaxy S23.', 'EcoCase', 'BC-S23', 50.00, NULL, NULL, 35, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(17, 1, 3, 'Чехол-накладка для Xiaomi 13 Pro', 'chehol-nakladka-xiaomi-13-pro', 'CASE-OVL-X13P-017', NULL, NULL, 'Чехол-накладка для Xiaomi 13 Pro.', 'ProtectX', 'PC-M13P', 36.00, NULL, NULL, 60, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 1, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2026-03-20 15:10:09'),
(18, 1, 3, 'Чехол-слайдер для Xiaomi Redmi Note 12 Pro', 'chehol-slider-xiaomi-redmi-note-12-pro', 'CASE-SLD-RN12P-018', NULL, NULL, 'Чехол-слайдер для Xiaomi Redmi Note 12 Pro.', 'SliderCase', 'SC-RN12P', 52.00, NULL, NULL, 25, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 2, 1, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2026-03-21 14:26:26'),
(19, 1, 3, 'Прочный чехол для Google Pixel 7 Pro', 'prochniy-chehol-google-pixel-7-pro', 'CASE-HVY-G7P-019', NULL, NULL, 'Прочный чехол для Google Pixel 7 Pro.', 'Duracase', 'DC-G7P', 44.00, NULL, NULL, 45, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(20, 1, 3, 'Прозрачный силиконовый чехол для Google Pixel 7', 'prozrachniy-silikonoviy-chehol-google-pixel-7', 'CASE-CLR-SIL-G7-020', NULL, NULL, 'Легкий прозрачный силиконовый чехол для Google Pixel 7.', 'ClearShield', 'CS-G7', 42.00, NULL, NULL, 30, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 1, 2, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:02:13', '2026-03-20 17:53:25'),
(21, 1, 3, 'Защитный чехол-накладка для Huawei P60 Pro', 'zashchitniy-chehol-huawei-p60-pro', 'CASE-PRO-HP60P-021', NULL, NULL, 'Защитный чехол-накладка для Huawei P60 Pro.', 'ShieldPro', 'SP-P60P', 64.00, NULL, NULL, 40, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 1, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:02:13', '2026-03-20 10:19:57'),
(22, 1, 4, 'Защитное стекло для iPhone 15 Pro', 'zashchitnoe-steklo-iphone-15-pro', 'GLASS-IP15P-022', NULL, NULL, 'Защитное стекло для iPhone 15 Pro.', 'ScreenGuard', 'SG-IP15P', 79.00, NULL, NULL, 70, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 1, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2026-03-20 15:29:37'),
(23, 1, 4, 'Защитное стекло для iPhone 15', 'zashchitnoe-steklo-iphone-15', 'GLASS-IP15-023', NULL, NULL, 'Защитное стекло для iPhone 15.', 'ScreenGuard', 'SG-IP15', 74.00, NULL, NULL, 65, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(24, 1, 4, 'Защитное стекло для Samsung Galaxy S23 Ultra', 'zashchitnoe-steklo-samsung-s23-ultra', 'GLASS-S23U-024', NULL, NULL, 'Защитное стекло для Samsung Galaxy S23 Ultra.', 'UltraShield', 'US-S23U', 87.00, NULL, NULL, 55, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(25, 1, 4, 'Защитное стекло для Samsung Galaxy S23', 'zashchitnoe-steklo-samsung-s23', 'GLASS-S23-025', NULL, NULL, 'Защитное стекло для Samsung Galaxy S23.', 'UltraShield', 'US-S23', 76.00, NULL, NULL, 60, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(26, 1, 4, 'Защитное стекло для Xiaomi 13 Pro', 'zashchitnoe-steklo-xiaomi-13-pro', 'GLASS-X13P-026', NULL, NULL, 'Защитное стекло для Xiaomi 13 Pro.', 'CrystalShield', 'CS-M13P', 65.00, NULL, NULL, 80, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(27, 1, 4, 'Защитное стекло для Xiaomi Redmi Note 12 Pro', 'zashchitnoe-steklo-xiaomi-redmi-note-12-pro', 'GLASS-RN12P-027', NULL, NULL, 'Защитное стекло для Xiaomi Redmi Note 12 Pro.', 'CrystalShield', 'CS-RN12P', 52.00, NULL, NULL, 90, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(28, 1, 4, 'Защитное стекло для OnePlus 11', 'zashchitnoe-steklo-oneplus-11', 'GLASS-OP11-028', NULL, NULL, 'Защитное стекло для OnePlus 11 с олеофобным покрытием.', 'OneShield', 'OS-OP11', 55.00, NULL, NULL, 50, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 2, 2, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:02:13', '2026-03-20 19:15:15'),
(29, 1, 4, 'Защитное стекло для Realme GT Neo 5', 'zashchitnoe-steklo-realme-gt-neo-5', 'GLASS-GTN5-029', NULL, NULL, 'Защитное стекло для Realme GT Neo 5.', 'CrystalGuard', 'CG-GTN5', 50.00, NULL, NULL, 45, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 2, 1, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:02:13', '2026-03-20 19:20:21'),
(30, 1, 5, 'Быстрое зарядное устройство 20W для iPhone', 'bystroe-zaryadnoe-20w-iphone', 'CHGR-20W-IP-030', NULL, NULL, 'Быстрое зарядное устройство для iPhone.', 'FastCharge', 'FC-IP', 210.00, NULL, NULL, 40, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(31, 1, 5, 'Зарядное устройство 25W для Samsung', 'zaryadnoe-25w-samsung', 'CHGR-25W-SAM-031', NULL, NULL, 'Зарядное устройство для Samsung Galaxy.', 'QuickCharge', 'QC-S23', 236.00, NULL, NULL, 35, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 1, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2026-03-20 17:30:53'),
(32, 1, 5, 'Беспроводное зарядное устройство для iPhone', 'besprovodnoe-zaryadnoe-iphone', 'CHGR-WRLS-IP-032', NULL, NULL, 'Беспроводное зарядное устройство для iPhone.', 'WirelessPro', 'WP-IP', 342.00, NULL, NULL, 30, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(33, 1, 5, 'Сетевое зарядное устройство 30W для Xiaomi', 'setevoe-zaryadnoe-30w-xiaomi', 'CHGR-30W-XIAO-033', NULL, NULL, 'Сетевое зарядное устройство для Xiaomi.', 'ChargeMax', 'CM-M13P', 184.00, NULL, NULL, 45, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(34, 1, 5, 'Универсальное зарядное устройство 45W', 'universalnoe-zaryadnoe-45w', 'CHGR-45W-UNV-034', NULL, NULL, 'Универсальное зарядное устройство 45W для разных устройств.', 'UniversalCharge', 'UC45', 399.00, NULL, NULL, 20, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(35, 1, 5, 'Беспроводное зарядное устройство с креплением для Xiaomi Redmi Note 12 Pro', 'besprovodnoe-zaryadnoe-krepleniem-xiaomi-redmi-note-12-pro', 'CHGR-WRLS-MAG-RN12P-035', NULL, NULL, 'Беспроводное зарядное устройство с магнитным креплением для Xiaomi Redmi Note 12 Pro.', 'ChargeMate', 'CM-RN12P', 342.00, NULL, NULL, 25, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 3, 3, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:02:13', '2026-03-20 17:53:25'),
(37, 1, 6, 'Беспроводные наушники True Wireless для iPhone', 'besprovodnye-naushniki-true-wireless-iphone', 'HEAD-TWS-IP-037', NULL, NULL, 'Беспроводные наушники True Wireless для iPhone.', 'SoundAir', 'SA-IP', 1100.00, NULL, NULL, 25, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(38, 1, 6, 'Беспроводные наушники с шумоподавлением для Samsung', 'besprovodnye-naushniki-shumopodavleniem-samsung', 'HEAD-NC-SAM-038', NULL, NULL, 'Беспроводные наушники с шумоподавлением для Samsung.', 'NoiseBlock', 'NB-S23', 1375.00, NULL, NULL, 20, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(39, 1, 6, 'Проводные наушники для Xiaomi', 'provodnye-naushniki-xiaomi', 'HEAD-WRD-XIAO-039', NULL, NULL, 'Проводные наушники для Xiaomi.', 'ClearSound', 'CS-M13P', 275.00, NULL, NULL, 30, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(40, 1, 6, 'Спортивные наушники для Google Pixel', 'sportivnye-naushniki-google-pixel', 'HEAD-SPORT-GP-040', NULL, NULL, 'Спортивные наушники для Google Pixel.', 'SportBeats', 'SB-G7P', 410.00, NULL, NULL, 35, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(41, 1, 6, 'Накладные наушники для OnePlus', 'nakladnye-naushniki-oneplus', 'HEAD-OVR-OP11-041', NULL, NULL, 'Накладные наушники для OnePlus.', 'OverEar', 'OE-OP11', 545.00, NULL, NULL, 15, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(42, 1, 6, 'Беспроводные наушники-вкладыши для Realme', 'besprovodnye-naushniki-vkladyshi-realme', 'HEAD-TWS-RM-042', NULL, NULL, 'Беспроводные наушники-вкладыши для Realme.', 'InEar', 'IE-GTN5', 246.00, NULL, NULL, 40, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:01:31', '2025-10-18 12:37:08'),
(43, 1, 6, 'Беспроводные наушники-вкладыши для Huawei P60 Pro', 'besprovodnye-naushniki-vkladyshi-huawei-p60-pro', 'HEAD-TWS-HW-043', NULL, NULL, 'Беспроводные наушники-вкладыши для Huawei P60 Pro.', 'SoundBeats', 'SB-P60P', 246.00, NULL, NULL, 40, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 1, 2, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:02:13', '2026-03-20 17:53:25'),
(44, 1, 6, 'Накладные наушники с микрофоном для Samsung Galaxy S23', 'nakladnye-naushniki-mikrofonom-samsung-s23', 'HEAD-OVR-MIC-S23-044', NULL, NULL, 'Накладные наушники с микрофоном для Samsung Galaxy S23.', 'VoiceClear', 'VC-S23', 18.00, NULL, NULL, 20, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 1, 0, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:02:13', '2026-03-20 17:59:05'),
(45, 1, 6, 'Спортивные наушники для Xiaomi Redmi Note 12 Pro', 'sportivnye-naushniki-xiaomi-redmi-note-12-pro', 'HEAD-SPORT-RN12P-045', NULL, NULL, 'Спортивные наушники для Xiaomi Redmi Note 12 Pro.', 'SportSound', 'SS-RN12P', 13.00, NULL, NULL, 30, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 0, 1, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:02:13', '2026-03-20 17:53:25'),
(46, 1, 6, 'Беспроводные наушники True Wireless для OnePlus 11', 'besprovodnye-naushniki-true-wireless-oneplus-11', 'HEAD-TWS-OP11-046', NULL, NULL, 'Беспроводные наушники True Wireless для OnePlus 11.', 'AirBeats', 'AB-OP11', 41.00, NULL, NULL, 15, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 1, 1, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:02:13', '2026-03-20 19:17:45'),
(47, 1, 6, 'Беспроводные наушники-вкладыши для iPhone', 'besprovodnye-naushniki-vkladyshi-iphone', 'HEAD-TWS-IP-GEN-047', NULL, NULL, 'Беспроводные наушники-вкладыши для iPhone.', 'SoundAir', 'SA-IP-Lite', 550.00, NULL, NULL, 35, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'simple', 'active', NULL, 4, 2, 0.00, 0, NULL, NULL, NULL, '2025-02-15 15:02:13', '2026-03-22 15:02:59'),
(61, 2, 7, 'Ноутбук HONOR MagicBook X16 AMD 2025 GOH-X 5301APLL', 'honor-magicbook-x16-amd-2025-goh-x-5301apll-69bea2feaaa56', 'GOH-X 5301APLL', '', '16.0\" 1920 x 1200, IPS, 60 Гц, AMD Ryzen 5 6600H, 16 ГБ LPDDR5X, SSD 512 ГБ, видеокарта встроенная, Windows 11 Home, цвет крышки серый, аккумулятор 42 Вт·ч', '16.0\" 1920 x 1200, IPS, 60 Гц, AMD Ryzen 5 6600H, 16 ГБ LPDDR5X, SSD 512 ГБ, видеокарта встроенная, Windows 11 Home, цвет крышки серый, аккумулятор 42 Вт·ч', 'HONOR', 'MagicBook X16 AMD 2025 GOH-X 5301APLL', 50000.00, NULL, NULL, 1000, 1, NULL, 'шт', NULL, NULL, NULL, NULL, 'variable', 'active', NULL, 3, 0, 0.00, 0, NULL, NULL, NULL, '2026-03-21 13:54:06', '2026-03-22 15:02:03'),
(63, 1, 1, 'X8D 8GB/256GB LNA-LX2 (ЧЕРНЫЙ)', 'x8d-8gb-256gb-lna-lx2-69bf0b7676171', 'X8D 8GB/256GB LNA-LX2', '', 'Android, экран 6.3\" OLED (1220x2656) 120 Гц, Qualcomm Snapdragon 8 Elite Gen 5, ОЗУ 12 ГБ, память 512 ГБ, камера 50 Мп, аккумулятор 6330 мАч, моноблок, влагозащита IP68', 'Android, экран 6.3\" OLED (1220x2656) 120 Гц, Qualcomm Snapdragon 8 Elite Gen 5, ОЗУ 12 ГБ, память 512 ГБ, камера 50 Мп, аккумулятор 6330 мАч, моноблок, влагозащита IP68', 'HONOR', 'X8D 8GB/256GB LNA-LX2 (ЧЕРНЫЙ)', 1500.00, NULL, NULL, 1000000, 1, NULL, 'шт', 5.00, NULL, NULL, NULL, 'simple', 'active', NULL, 2, 0, 0.00, 0, NULL, NULL, NULL, '2026-03-21 21:19:50', '2026-03-22 15:01:52');

-- --------------------------------------------------------

--
-- Структура таблицы `product_attributes`
--

CREATE TABLE `product_attributes` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `attribute_id` int(11) NOT NULL,
  `value_text` text DEFAULT NULL,
  `value_number` decimal(10,2) DEFAULT NULL,
  `value_boolean` tinyint(1) DEFAULT NULL,
  `value_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `product_attributes`
--

INSERT INTO `product_attributes` (`id`, `product_id`, `attribute_id`, `value_text`, `value_number`, `value_boolean`, `value_date`, `created_at`) VALUES
(138, 61, 76, 'Intel Core i5 13420H', 0.00, 0, NULL, '2026-03-21 13:54:06'),
(139, 61, 77, '', 5.00, 0, NULL, '2026-03-21 13:54:06'),
(140, 61, 78, '', 3300.00, 0, NULL, '2026-03-21 13:54:06'),
(141, 61, 79, 'DDR4', 0.00, 0, NULL, '2026-03-21 13:54:06'),
(142, 61, 80, '', 16.00, 0, NULL, '2026-03-21 13:54:06'),
(143, 61, 81, '1000', 0.00, 0, NULL, '2026-03-21 13:54:06'),
(144, 61, 85, 'HD GRAFIKS', 0.00, 0, NULL, '2026-03-21 13:54:06'),
(145, 61, 86, '', 16.00, 0, NULL, '2026-03-21 13:54:06'),
(146, 61, 87, 'IPS', 0.00, 0, NULL, '2026-03-21 13:54:06'),
(147, 61, 88, '1920x1080 (Full HD)', 0.00, 0, NULL, '2026-03-21 13:54:06'),
(148, 61, 89, 'Алюминий', 0.00, 0, NULL, '2026-03-21 13:54:06'),
(149, 61, 90, 'Windows 10', 0.00, 0, NULL, '2026-03-21 13:54:06'),
(150, 61, 91, 'Черный', 0.00, 0, NULL, '2026-03-21 13:54:06'),
(151, 61, 76, 'Intel Core i5 13420H', 0.00, 0, NULL, '2026-03-21 15:11:37'),
(152, 61, 77, '', 6.00, 0, NULL, '2026-03-21 15:11:37'),
(153, 61, 78, '', 6.00, 0, NULL, '2026-03-21 15:11:37'),
(154, 61, 79, 'DDR5', 0.00, 0, NULL, '2026-03-21 15:11:37'),
(155, 61, 80, '', 6.00, 0, NULL, '2026-03-21 15:11:37'),
(156, 61, 81, '5555', 0.00, 0, NULL, '2026-03-21 15:11:37'),
(157, 61, 85, 'КККК', 0.00, 0, NULL, '2026-03-21 15:11:37'),
(158, 61, 86, '', 6.00, 0, NULL, '2026-03-21 15:11:37'),
(159, 61, 87, 'OLED', 0.00, 0, NULL, '2026-03-21 15:11:37'),
(160, 61, 88, '1366x768', 0.00, 0, NULL, '2026-03-21 15:11:37'),
(161, 61, 89, 'Алюминий', 0.00, 0, NULL, '2026-03-21 15:11:37'),
(162, 61, 90, 'Windows 10', 0.00, 0, NULL, '2026-03-21 15:11:37'),
(163, 61, 91, 'Золотой', 0.00, 0, NULL, '2026-03-21 15:11:37'),
(164, 61, 76, 'Intel Core i512450HX', 0.00, 0, NULL, '2026-03-21 15:15:46'),
(165, 61, 77, '', 8.00, 0, NULL, '2026-03-21 15:15:46'),
(166, 61, 78, '', 2400.00, 0, NULL, '2026-03-21 15:15:46'),
(167, 61, 79, 'DDR4', 0.00, 0, NULL, '2026-03-21 15:15:46'),
(168, 61, 80, '', 1111.00, 0, NULL, '2026-03-21 15:15:46'),
(169, 61, 81, '5555', 0.00, 0, NULL, '2026-03-21 15:15:46'),
(170, 61, 85, 'HD GRAFIKS', 0.00, 0, NULL, '2026-03-21 15:15:46'),
(171, 61, 86, '', 15.60, 0, NULL, '2026-03-21 15:15:46'),
(172, 61, 87, 'OLED', 0.00, 0, NULL, '2026-03-21 15:15:46'),
(173, 61, 88, '1920x1080 (Full HD)', 0.00, 0, NULL, '2026-03-21 15:15:46'),
(174, 61, 89, 'Магниевый сплав', 0.00, 0, NULL, '2026-03-21 15:15:46'),
(175, 61, 90, 'Windows 10', 0.00, 0, NULL, '2026-03-21 15:15:46'),
(176, 61, 91, 'Красный', 0.00, 0, NULL, '2026-03-21 15:15:46'),
(185, 61, 76, 'пвекпа', 0.00, 0, NULL, '2026-03-21 20:38:59'),
(186, 61, 77, '', 7777.00, 0, NULL, '2026-03-21 20:38:59'),
(187, 61, 78, '', 7777.00, 0, NULL, '2026-03-21 20:38:59'),
(188, 61, 79, 'DDR4', 0.00, 0, NULL, '2026-03-21 20:38:59'),
(189, 61, 80, '', 45455.00, 0, NULL, '2026-03-21 20:38:59'),
(190, 61, 81, '455454', 0.00, 0, NULL, '2026-03-21 20:38:59'),
(191, 61, 85, 'прппр', 0.00, 0, NULL, '2026-03-21 20:38:59'),
(192, 61, 86, '', 4455445.00, 0, NULL, '2026-03-21 20:38:59'),
(193, 61, 87, 'OLED', 0.00, 0, NULL, '2026-03-21 20:38:59'),
(194, 61, 88, '1366x768', 0.00, 0, NULL, '2026-03-21 20:38:59'),
(195, 61, 89, 'Алюминий', 0.00, 0, NULL, '2026-03-21 20:38:59'),
(196, 61, 90, 'Windows 10', 0.00, 0, NULL, '2026-03-21 20:38:59'),
(197, 61, 91, 'Золотой', 0.00, 0, NULL, '2026-03-21 20:38:59'),
(198, 63, 92, 'OLED', 0.00, 0, NULL, '2026-03-21 21:19:50'),
(199, 63, 93, '', 5555.00, 0, NULL, '2026-03-21 21:19:50'),
(200, 63, 94, 'Gorilla Glass Victus 2', 0.00, 0, NULL, '2026-03-21 21:19:50'),
(201, 63, 95, '', 0.00, 1, NULL, '2026-03-21 21:19:50'),
(202, 63, 96, '', 0.00, 1, NULL, '2026-03-21 21:19:50'),
(203, 63, 97, '', 0.00, 1, NULL, '2026-03-21 21:19:50'),
(204, 63, 98, '', 450.00, 0, NULL, '2026-03-21 21:19:50'),
(205, 63, 99, '', 0.00, 1, NULL, '2026-03-21 21:19:50');

-- --------------------------------------------------------

--
-- Структура таблицы `product_compatibility`
--

CREATE TABLE `product_compatibility` (
  `product_id` int(11) NOT NULL,
  `compatible_with_product_id` int(11) NOT NULL,
  `compatibility_type` enum('accessory_for','similar','recommended') DEFAULT 'accessory_for'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `product_compatibility`
--

INSERT INTO `product_compatibility` (`product_id`, `compatible_with_product_id`, `compatibility_type`) VALUES
(13, 1, 'accessory_for'),
(14, 2, 'accessory_for'),
(15, 3, 'accessory_for'),
(16, 4, 'accessory_for'),
(17, 5, 'accessory_for'),
(18, 6, 'accessory_for'),
(19, 7, 'accessory_for'),
(20, 8, 'accessory_for'),
(21, 11, 'accessory_for'),
(22, 1, 'accessory_for'),
(23, 2, 'accessory_for'),
(24, 3, 'accessory_for'),
(25, 4, 'accessory_for'),
(26, 5, 'accessory_for'),
(27, 6, 'accessory_for'),
(28, 9, 'accessory_for'),
(29, 10, 'accessory_for'),
(30, 1, 'accessory_for'),
(30, 2, 'accessory_for'),
(31, 3, 'accessory_for'),
(31, 4, 'accessory_for'),
(32, 1, 'accessory_for'),
(32, 2, 'accessory_for'),
(33, 5, 'accessory_for'),
(33, 6, 'accessory_for'),
(34, 5, 'accessory_for'),
(34, 9, 'accessory_for'),
(35, 6, 'accessory_for'),
(37, 1, 'accessory_for'),
(37, 2, 'accessory_for'),
(38, 3, 'accessory_for'),
(38, 4, 'accessory_for'),
(39, 5, 'accessory_for'),
(39, 6, 'accessory_for'),
(40, 7, 'accessory_for'),
(40, 8, 'accessory_for'),
(41, 9, 'accessory_for'),
(42, 10, 'accessory_for'),
(43, 11, 'accessory_for'),
(44, 3, 'accessory_for'),
(44, 4, 'accessory_for'),
(45, 5, 'accessory_for'),
(45, 6, 'accessory_for'),
(46, 9, 'accessory_for'),
(47, 1, 'accessory_for'),
(47, 2, 'accessory_for');

-- --------------------------------------------------------

--
-- Структура таблицы `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variation_id` int(11) DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `variation_id`, `image_url`, `sort_order`, `is_primary`, `created_at`) VALUES
(1, 1, NULL, 'uploads/1.jpg', 1, 0, '2026-03-19 12:24:53'),
(2, 2, NULL, 'uploads/2.jpg', 1, 0, '2026-03-19 12:24:53'),
(3, 3, NULL, 'uploads/3.jpg', 1, 0, '2026-03-19 12:24:53'),
(4, 4, NULL, 'uploads/4.jpg', 1, 0, '2026-03-19 12:24:53'),
(5, 5, NULL, 'uploads/5.jpg', 1, 0, '2026-03-19 12:24:53'),
(6, 6, NULL, 'uploads/6.jpg', 1, 0, '2026-03-19 12:24:53'),
(7, 7, NULL, 'uploads/7.jpg', 1, 0, '2026-03-19 12:24:53'),
(8, 8, NULL, 'uploads/8.jpg', 1, 0, '2026-03-19 12:24:53'),
(9, 9, NULL, 'uploads/9.jpg', 1, 0, '2026-03-19 12:24:53'),
(10, 10, NULL, 'uploads/10.jpg', 1, 0, '2026-03-19 12:24:53'),
(11, 11, NULL, 'uploads/11.jpg', 1, 0, '2026-03-19 12:24:53'),
(12, 12, NULL, 'uploads/12.jpg', 1, 0, '2026-03-19 12:24:53'),
(14, 61, NULL, 'uploads/products/product_69bea2feaef100.50620677.jpg', 0, 1, '2026-03-21 13:54:06'),
(16, 63, NULL, 'uploads/products/product_69bf0b767ba653.33723165.jpg', 0, 1, '2026-03-21 21:19:50');

-- --------------------------------------------------------

--
-- Структура таблицы `product_tags`
--

CREATE TABLE `product_tags` (
  `product_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `product_tags`
--

INSERT INTO `product_tags` (`product_id`, `tag_id`) VALUES
(1, 2),
(2, 2),
(2, 4),
(3, 2),
(4, 4),
(6, 4),
(8, 4),
(10, 1),
(10, 4),
(11, 1),
(12, 1),
(12, 4),
(13, 5),
(14, 5),
(15, 3),
(15, 5),
(16, 5),
(17, 3),
(17, 5),
(18, 5),
(19, 3),
(19, 5),
(20, 3),
(20, 5),
(21, 5),
(22, 5),
(23, 5),
(24, 5),
(25, 5),
(26, 5),
(27, 5),
(28, 5),
(29, 5),
(30, 5),
(31, 5),
(32, 5),
(33, 5),
(34, 5),
(35, 5),
(37, 5),
(38, 5),
(39, 5),
(40, 5),
(41, 5),
(42, 5),
(43, 5),
(44, 3),
(44, 5),
(45, 3),
(45, 5),
(46, 3),
(46, 5),
(47, 5);

-- --------------------------------------------------------

--
-- Структура таблицы `product_variations`
--

CREATE TABLE `product_variations` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `attributes_hash` varchar(32) NOT NULL COMMENT 'MD5 хеш комбинации атрибутов',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `product_variations`
--

INSERT INTO `product_variations` (`id`, `product_id`, `sku`, `price`, `old_price`, `quantity`, `image_url`, `attributes_hash`, `created_at`, `updated_at`) VALUES
(1, 1, 'SM-IP15PRO-001-DEFAULT', 3299.00, NULL, 15, NULL, '20810fab1f1f4fe229ede9665f5733d3', '2026-03-19 16:19:42', '2026-03-21 20:49:11'),
(2, 2, 'SM-IP15-002-DEFAULT', 2699.00, NULL, 20, NULL, '4d481c60c6d62b267bc9b62caeee12ae', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(3, 3, 'SM-S23U-003-DEFAULT', 3599.00, NULL, 10, NULL, 'eb12abe86237c47065e29fe1c9765210', '2026-03-19 16:19:42', '2026-03-21 20:49:11'),
(4, 4, 'SM-S23-004-DEFAULT', 2599.00, NULL, 25, NULL, 'ea3e35b7c807dde260336c47e386c797', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(5, 5, 'SM-X13P-005-DEFAULT', 2399.00, NULL, 18, NULL, '45e218f5af573c8a636e932664e80617', '2026-03-19 16:19:42', '2026-03-21 20:49:11'),
(6, 6, 'SM-RN12P-006-DEFAULT', 1049.00, NULL, 30, NULL, 'a6fd6e2e770f23e59d337cc927d907de', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(7, 7, 'SM-G7P-007-DEFAULT', 3149.00, NULL, 11, NULL, '30a3a0ae47a901a73f25d7b05c2347ec', '2026-03-19 16:19:42', '2026-03-22 15:02:59'),
(8, 8, 'SM-G7-008-DEFAULT', 865.00, NULL, 20, NULL, 'd33e0af19ab43a9bbd518eb5499ef24f', '2026-03-19 16:19:42', '2026-03-21 20:49:11'),
(9, 9, 'SM-OP11-009-DEFAULT', 2249.00, NULL, 17, NULL, '016b7cb99be9b0fc9d146288870ed407', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(10, 10, 'SM-GTN5-010-DEFAULT', 1679.00, NULL, 22, NULL, 'e6af9e0f25c0a6110b84d5158c974f60', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(11, 11, 'SM-P60P-011-DEFAULT', 2999.00, NULL, 14, NULL, '51a1cd2fe72cd5feff6f8f694e935216', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(12, 12, 'SM-M5P-012-DEFAULT', 2849.00, NULL, 16, NULL, '29f510bfec5bff3c5876afd43794cdd0', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(13, 13, 'CASE-SIL-IP15P-013-DEFAULT', 55.00, NULL, 50, NULL, '4a60e0825a9720ec1512931cbc9270d7', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(14, 14, 'CASE-LEA-IP15-014-DEFAULT', 79.00, NULL, 30, NULL, 'e5f498f1abde5fe2a3210c3d73397362', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(15, 15, 'CASE-CLR-S23U-015-DEFAULT', 42.00, NULL, 40, NULL, 'bfc700522c7d6a68b2fcff8736fab5f1', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(16, 16, 'CASE-BAM-S23-016-DEFAULT', 50.00, NULL, 35, NULL, '0949d37963d5834c5acd617ae6fafd68', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(17, 17, 'CASE-OVL-X13P-017-DEFAULT', 36.00, NULL, 60, NULL, '54be3f8d1ad0b097ecc38b34d36b441d', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(18, 18, 'CASE-SLD-RN12P-018-DEFAULT', 52.00, NULL, 25, NULL, 'c0ed24243f309ce2bf8d89472195e65e', '2026-03-19 16:19:42', '2026-03-21 20:49:11'),
(19, 19, 'CASE-HVY-G7P-019-DEFAULT', 44.00, NULL, 45, NULL, 'eb52287b2926b54ea0a86102da54f03a', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(20, 20, 'CASE-CLR-SIL-G7-020-DEFAULT', 42.00, NULL, 30, NULL, '03f502a9dd5c824443bfab3c95488f98', '2026-03-19 16:19:42', '2026-03-21 20:49:11'),
(21, 21, 'CASE-PRO-HP60P-021-DEFAULT', 64.00, NULL, 40, NULL, 'ce3ba934bd25aaa8ca4f1607d0ba0430', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(22, 22, 'GLASS-IP15P-022-DEFAULT', 79.00, NULL, 70, NULL, '499ec2850c9044c76ff595c0cb0883fb', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(23, 23, 'GLASS-IP15-023-DEFAULT', 74.00, NULL, 65, NULL, '9a885feab93d2d254c0b8ec908701bb8', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(24, 24, 'GLASS-S23U-024-DEFAULT', 87.00, NULL, 55, NULL, '43fb66465ca11af2419c41db4c64afd0', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(25, 25, 'GLASS-S23-025-DEFAULT', 76.00, NULL, 60, NULL, '4d2e9439d992e9932ee6cd5a68b8e35a', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(26, 26, 'GLASS-X13P-026-DEFAULT', 65.00, NULL, 80, NULL, '6651da0d7c44fea4ca1298464633ccc0', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(27, 27, 'GLASS-RN12P-027-DEFAULT', 52.00, NULL, 90, NULL, '28c414ccc833c460ee96e80b3362f662', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(28, 28, 'GLASS-OP11-028-DEFAULT', 55.00, NULL, 50, NULL, 'b1a7f194ddd5f732c3ad6fa8317b7f95', '2026-03-19 16:19:42', '2026-03-21 20:49:11'),
(29, 29, 'GLASS-GTN5-029-DEFAULT', 50.00, NULL, 45, NULL, '1a5077dcc1d78ca0322bf06f52e78f46', '2026-03-19 16:19:42', '2026-03-21 20:49:11'),
(30, 30, 'CHGR-20W-IP-030-DEFAULT', 210.00, NULL, 40, NULL, '91ab7cb7420b43cfbad5d0a0850495e6', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(31, 31, 'CHGR-25W-SAM-031-DEFAULT', 236.00, NULL, 35, NULL, '5240753008a1a199d32e5a9225dcd17e', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(32, 32, 'CHGR-WRLS-IP-032-DEFAULT', 342.00, NULL, 30, NULL, 'c77b972afb6c6ceabf5fd7f076e994b2', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(33, 33, 'CHGR-30W-XIAO-033-DEFAULT', 184.00, NULL, 45, NULL, 'a7f947517a7df56b8258f614e0e7117f', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(34, 34, 'CHGR-45W-UNV-034-DEFAULT', 399.00, NULL, 20, NULL, '609f42cee8c6c6125e4dfc728dfa883f', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(35, 35, 'CHGR-WRLS-MAG-RN12P-035-DEFAULT', 342.00, NULL, 25, NULL, '6caacad9895521628f6d029af11f89ec', '2026-03-19 16:19:42', '2026-03-21 20:49:11'),
(37, 37, 'HEAD-TWS-IP-037-DEFAULT', 1100.00, NULL, 25, NULL, 'f448d78b0d79a2d3a5a5d52584977383', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(38, 38, 'HEAD-NC-SAM-038-DEFAULT', 1375.00, NULL, 20, NULL, '8265878763dc8ae4254b96538907977a', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(39, 39, 'HEAD-WRD-XIAO-039-DEFAULT', 275.00, NULL, 30, NULL, 'fd2a1e0297f87186218aced0bf1d7c59', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(40, 40, 'HEAD-SPORT-GP-040-DEFAULT', 410.00, NULL, 35, NULL, '5e57515adf90cc9a7d7697466ca345a8', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(41, 41, 'HEAD-OVR-OP11-041-DEFAULT', 545.00, NULL, 15, NULL, '2bb27bf28735954a9774ac1af7b4b4ca', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(42, 42, 'HEAD-TWS-RM-042-DEFAULT', 246.00, NULL, 40, NULL, '6310d2ae277209f2a20b785e03b5038e', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(43, 43, 'HEAD-TWS-HW-043-DEFAULT', 246.00, NULL, 40, NULL, '36d331f3af0f8e5262046ab33ed08348', '2026-03-19 16:19:42', '2026-03-21 20:49:11'),
(44, 44, 'HEAD-OVR-MIC-S23-044-DEFAULT', 18.00, NULL, 20, NULL, '56c9d8e8f12400b1559d66f877ee6c01', '2026-03-19 16:19:42', '2026-03-19 16:19:42'),
(45, 45, 'HEAD-SPORT-RN12P-045-DEFAULT', 13.00, NULL, 30, NULL, '18726443863ad7bff8522d6b86fc8a76', '2026-03-19 16:19:42', '2026-03-21 20:49:11'),
(46, 46, 'HEAD-TWS-OP11-046-DEFAULT', 41.00, NULL, 15, NULL, 'b87fee1782f94fd5df6ab5f8089415ee', '2026-03-19 16:19:42', '2026-03-21 20:49:11'),
(47, 47, 'HEAD-TWS-IP-GEN-047-DEFAULT', 550.00, NULL, 34, NULL, '76ea700cee52d7b269ec726dbcf42adc', '2026-03-19 16:19:42', '2026-03-22 15:02:59'),
(48, 47, '111234', 565.00, NULL, 1000, NULL, 'cc9c0bb9c8692324f9fe9e47b0da6760', '2026-03-20 19:57:26', '2026-03-20 19:57:26'),
(49, 47, '1112345', 565.00, NULL, 1000, NULL, '727bf1f4358c7a836428d24500d25d48', '2026-03-20 19:57:57', '2026-03-20 19:57:57'),
(50, 47, '1112346', 565.00, 0.00, 1000, NULL, '684712afa13aa169dcd7bd685f0cdd41', '2026-03-20 20:00:51', '2026-03-20 20:00:51'),
(51, 47, '11123467', 565.00, 0.00, 1000, '', 'c41bf40cf21d4965465acc4cbd8b6c46', '2026-03-20 20:04:01', '2026-03-20 20:04:01'),
(52, 47, '111234671', 565.00, 0.00, 1000, '', '7786c7c11b0f0b6205921c2a19e6f1c2', '2026-03-20 20:06:02', '2026-03-20 20:06:02'),
(53, 47, '1112346711', 565.00, 0.00, 1000, '', '520ed43ac9e68cb2c28893e9fe9da662', '2026-03-20 20:06:15', '2026-03-20 20:06:15'),
(54, 61, 'MagicBook X16 AMD 2025 GOH-X 5301APLL1', 60000.00, 50000.00, 50, 'uploads/variations/var_61_69beb529b5ab6.jpg', 'fe75c98f1ebe2410cbbd88eaa830b45c', '2026-03-21 15:11:37', '2026-03-21 15:11:37'),
(55, 61, 'LENOVO LOQ 15IAX9E (83LK00CVUS)', 5000.00, 5000000.00, 70, 'uploads/variations/var_61_69beb62f83321.jpg', 'd93232b22713e940390de0bda6c7593f', '2026-03-21 15:15:46', '2026-03-21 15:15:59');

-- --------------------------------------------------------

--
-- Структура таблицы `product_views`
--

CREATE TABLE `product_views` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) NOT NULL,
  `product_type` enum('smartphone','accessory') NOT NULL DEFAULT 'smartphone',
  `product_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `product_views`
--

INSERT INTO `product_views` (`id`, `user_id`, `session_id`, `product_type`, `product_id`, `viewed_at`) VALUES
(1, NULL, '8vjnmoq5n4c5bj9uhjlsi154p4', 'smartphone', 2, '2025-02-24 15:02:41'),
(2, NULL, '8vjnmoq5n4c5bj9uhjlsi154p4', 'smartphone', 2, '2025-02-24 15:02:47'),
(3, NULL, 'gngdmbkuuu7rrgitophkg2f06d', 'smartphone', 1, '2025-03-06 12:30:57'),
(4, NULL, 'gngdmbkuuu7rrgitophkg2f06d', 'smartphone', 3, '2025-03-06 12:31:09'),
(5, 9, '3st44ogognv23o2m0dut5kc1pt', 'smartphone', 10, '2025-10-15 16:06:25'),
(6, 20, '3st44ogognv23o2m0dut5kc1pt', 'smartphone', 2, '2025-10-15 17:55:50'),
(7, 9, '6aqkr9a41266abv397j8sbifeh', 'smartphone', 10, '2025-10-18 14:34:55'),
(8, 25, 's1h7h29pkqpq3jac04egd1a9mr', 'smartphone', 10, '2025-10-19 12:14:09'),
(9, 25, '22aucdvdpicl3igtgdjr0d0kh0', 'smartphone', 43, '2026-03-19 16:33:16'),
(10, 25, '22aucdvdpicl3igtgdjr0d0kh0', 'smartphone', 35, '2026-03-19 16:38:00'),
(11, 25, '22aucdvdpicl3igtgdjr0d0kh0', 'smartphone', 36, '2026-03-19 16:38:03'),
(12, 28, 'm9dlimkpe83r2osjqumje0l9ii', 'smartphone', 2, '2026-03-20 08:55:11'),
(13, 28, 'm9dlimkpe83r2osjqumje0l9ii', 'smartphone', 36, '2026-03-20 09:01:09'),
(14, 28, 'm9dlimkpe83r2osjqumje0l9ii', 'smartphone', 7, '2026-03-20 09:05:41'),
(15, 28, 'm9dlimkpe83r2osjqumje0l9ii', 'smartphone', 21, '2026-03-20 10:19:57'),
(16, NULL, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 47, '2026-03-20 11:09:30'),
(17, NULL, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 35, '2026-03-20 11:17:34'),
(18, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 20, '2026-03-20 12:14:30'),
(19, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 18, '2026-03-20 12:57:44'),
(20, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 28, '2026-03-20 12:57:55'),
(21, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 35, '2026-03-20 12:58:12'),
(22, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 47, '2026-03-20 13:05:47'),
(23, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 2, '2026-03-20 15:09:09'),
(24, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 17, '2026-03-20 15:10:09'),
(25, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 3, '2026-03-20 15:20:56'),
(26, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 22, '2026-03-20 15:29:37'),
(27, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 9, '2026-03-20 15:38:31'),
(28, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 29, '2026-03-20 16:03:03'),
(29, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 1, '2026-03-20 16:11:59'),
(30, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 8, '2026-03-20 16:16:05'),
(31, 9, 'vai8p5idp30d3dkfeqht0l5l7d', 'smartphone', 7, '2026-03-20 16:39:19'),
(32, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 31, '2026-03-20 17:30:53'),
(33, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 5, '2026-03-20 17:52:57'),
(34, 25, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 44, '2026-03-20 17:59:05'),
(35, 9, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 47, '2026-03-20 18:16:01'),
(36, 9, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 28, '2026-03-20 19:15:15'),
(37, 9, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 46, '2026-03-20 19:17:45'),
(38, 9, 'glaa07sqqhvlnf450sd0ku83s1', 'smartphone', 29, '2026-03-20 19:20:21'),
(39, 9, 'pdnki7r5i8vgb2bq36ougpkc3c', 'smartphone', 61, '2026-03-21 14:20:15'),
(40, 9, 'pdnki7r5i8vgb2bq36ougpkc3c', 'smartphone', 18, '2026-03-21 14:26:26'),
(41, 25, '9dqp7vua6sp8qisruot93ms9v7', 'smartphone', 61, '2026-03-21 22:57:42'),
(42, 25, '9dqp7vua6sp8qisruot93ms9v7', 'smartphone', 63, '2026-03-21 22:58:02'),
(43, 9, '1iadb53um3b5h7e2povj54u4jn', 'smartphone', 63, '2026-03-22 15:01:52'),
(44, 9, '1iadb53um3b5h7e2povj54u4jn', 'smartphone', 61, '2026-03-22 15:02:03'),
(45, 9, '1iadb53um3b5h7e2povj54u4jn', 'smartphone', 47, '2026-03-22 15:02:12'),
(46, 9, '1iadb53um3b5h7e2povj54u4jn', 'smartphone', 4, '2026-03-22 16:36:17'),
(47, 9, '1iadb53um3b5h7e2povj54u4jn', 'smartphone', 1, '2026-03-22 18:06:23');

-- --------------------------------------------------------

--
-- Структура таблицы `promotions`
--

CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `banner_url` varchar(255) DEFAULT NULL,
  `discount_type` enum('percent','fixed') DEFAULT NULL,
  `discount_value` decimal(10,2) DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `promotions`
--

INSERT INTO `promotions` (`id`, `name`, `slug`, `description`, `type`, `value`, `banner_url`, `discount_type`, `discount_value`, `starts_at`, `expires_at`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 'Супер скидка на iPhone 15', 'super-skidka-iphone-15', 'Скидка 80% на iPhone 15', 'percent', 0.00, NULL, 'percent', 80.00, '2025-04-22 00:00:00', NULL, 1, '2026-03-19 12:24:53', '2026-03-19 12:24:53'),
(3, 'Скидка на зарядки Xiaomi', 'skidka-zaryadki-xiaomi', 'Скидка 10% на зарядные устройства Xiaomi', 'percent', 0.00, NULL, 'percent', 10.00, '2025-04-22 00:00:00', NULL, 1, '2026-03-19 12:24:53', '2026-03-19 12:24:53'),
(4, 'Скидка на чехлы Xiaomi', 'skidka-chekhly-xiaomi', 'Скидка 20% на чехлы Xiaomi', 'percent', 0.00, NULL, 'percent', 20.00, '2025-04-22 00:00:00', NULL, 1, '2026-03-19 12:24:53', '2026-03-19 12:24:53'),
(5, '1', '1', '111', 'percent', 0.00, NULL, '', 222.00, '2026-03-21 00:00:00', '2028-10-21 23:53:00', 1, '2026-03-21 20:53:43', '2026-03-21 20:53:43');

-- --------------------------------------------------------

--
-- Структура таблицы `promotion_products`
--

CREATE TABLE `promotion_products` (
  `promotion_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `discount_percent` tinyint(3) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `promotion_products`
--

INSERT INTO `promotion_products` (`promotion_id`, `product_id`, `discount_percent`) VALUES
(2, 2, 80),
(3, 33, 10),
(4, 17, 20),
(4, 18, 20),
(5, 12, 0),
(5, 13, 0),
(5, 19, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `recently_viewed`
--

CREATE TABLE `recently_viewed` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_item_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `title` varchar(200) DEFAULT NULL,
  `comment` text NOT NULL,
  `pros` text DEFAULT NULL,
  `cons` text DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Массив URL изображений' CHECK (json_valid(`images`)),
  `is_verified_purchase` tinyint(1) NOT NULL DEFAULT 0,
  `likes_count` int(11) NOT NULL DEFAULT 0,
  `dislikes_count` int(11) NOT NULL DEFAULT 0,
  `reports_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `review_helpful`
--

CREATE TABLE `review_helpful` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_helpful` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 - полезно, 0 - не полезно',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `review_replies`
--

CREATE TABLE `review_replies` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `sellers`
--

CREATE TABLE `sellers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shop_name` varchar(255) NOT NULL,
  `shop_slug` varchar(255) NOT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `banner_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `contact_email` varchar(255) NOT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `rating` decimal(3,2) NOT NULL DEFAULT 0.00,
  `reviews_count` int(11) NOT NULL DEFAULT 0,
  `products_count` int(11) NOT NULL DEFAULT 0,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 10.00 COMMENT 'Комиссия платформы %',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `sellers`
--

INSERT INTO `sellers` (`id`, `user_id`, `shop_name`, `shop_slug`, `logo_url`, `banner_url`, `description`, `contact_email`, `contact_phone`, `address`, `rating`, `reviews_count`, `products_count`, `is_verified`, `is_active`, `commission_rate`, `created_at`, `updated_at`) VALUES
(1, 9, 'Admin Shop', 'admin-shop', NULL, NULL, 'Официальный магазин администратора', '', NULL, NULL, 0.00, 0, 0, 1, 1, 5.00, '2026-03-19 11:47:26', '2026-03-21 21:02:06'),
(2, 20, 'User24 Store', 'user24-store', NULL, NULL, 'Магазин пользователя User24', 'dxddxd137@gmail.com', NULL, NULL, 0.00, 0, 0, 0, 1, 10.00, '2026-03-19 11:47:26', '2026-03-19 11:47:26'),
(3, 25, 'User28 Market', 'user28-market', NULL, NULL, 'Магазин пользователя User28', 'mtest8557@gmail.com', NULL, NULL, 0.00, 0, 0, 0, 1, 10.00, '2026-03-19 11:47:26', '2026-03-19 11:47:26'),
(4, 26, 'Admin3Shop', 'admin3shop', NULL, NULL, 'ggggg', 'fggd@gmail.com', '4355', 'erewrewewr', 0.00, 0, 0, 1, 1, 10.00, '2026-03-21 21:03:17', '2026-03-21 21:03:17'),
(5, 27, 'Admin3Shop', 'admin3shop-69bf0849d2b99', NULL, NULL, 'ggggg', 'fggd@gmail.com', '4355', 'erewrewewr', 0.00, 0, 0, 1, 1, 10.00, '2026-03-21 21:06:17', '2026-03-21 21:06:17');

-- --------------------------------------------------------

--
-- Структура таблицы `seller_transactions`
--

CREATE TABLE `seller_transactions` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `order_seller_id` int(11) DEFAULT NULL,
  `type` enum('sale','commission','withdrawal','refund','adjustment') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `balance_before` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `shipping_methods`
--

CREATE TABLE `shipping_methods` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `free_from` decimal(10,2) DEFAULT NULL,
  `estimated_days_min` int(11) DEFAULT NULL,
  `estimated_days_max` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `shipping_methods`
--

INSERT INTO `shipping_methods` (`id`, `name`, `code`, `description`, `price`, `free_from`, `estimated_days_min`, `estimated_days_max`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'Самовывоз', 'pickup', NULL, 0.00, NULL, 1, 1, 1, 10, '2026-03-18 08:08:44'),
(2, 'Курьером по городу', 'courier', NULL, 300.00, NULL, 1, 2, 1, 20, '2026-03-18 08:08:44'),
(3, 'Почта России', 'russian_post', NULL, 350.00, NULL, 3, 10, 1, 30, '2026-03-18 08:08:44'),
(4, 'СДЭК', 'cdek', NULL, 400.00, NULL, 2, 5, 1, 40, '2026-03-18 08:08:44'),
(5, 'DHL', 'dhl', NULL, 1000.00, NULL, 1, 3, 1, 50, '2026-03-18 08:08:44');

-- --------------------------------------------------------

--
-- Структура таблицы `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `tags`
--

INSERT INTO `tags` (`id`, `name`, `slug`, `created_at`) VALUES
(1, 'Новинка', 'new', '2026-03-19 16:32:02'),
(2, 'Хит продаж', 'bestseller', '2026-03-19 16:32:02'),
(3, 'Акция', 'sale', '2026-03-19 16:32:02'),
(4, 'Рекомендуем', 'recommended', '2026-03-19 16:32:02'),
(5, 'Подарок', 'gift', '2026-03-19 16:32:02'),
(6, 'Эксклюзив', 'exclusive', '2026-03-19 16:32:02');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `login` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_subscribed` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `phone_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `wishlist_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `login`, `email`, `password`, `full_name`, `photo`, `birth_date`, `is_admin`, `created_at`, `is_subscribed`, `last_login`, `phone`, `phone_verified`, `email_verified`, `verification_token`, `reset_token`, `reset_token_expires`, `wishlist_count`) VALUES
(9, 'Admin3', '', '$2y$10$8N..CwjS61AP7OgU2v7LeOxbcOAmvxsuhvpmUa4CtO326aSdrQM5u', 'Qwerty', NULL, '2001-05-23', 1, '2025-10-15 16:06:22', 1, '2026-03-22 17:59:41', NULL, 0, 1, NULL, NULL, NULL, 0),
(20, 'User24', 'dxddxd137@gmail.com', '$2y$10$wEFj7kz8TuvHDRvgURB8l.HEt21kJkhEULl69pTf56UhIr59vfqsW', 'qeqeqwe', NULL, '2001-04-23', 0, '2025-10-15 17:48:46', 1, NULL, NULL, 0, 1, NULL, NULL, NULL, 0),
(25, 'User28', 'mtest8557@gmail.com', '$2y$10$e.pcHucaFVp7jAQvZmCcnuV.Tui4mSNlZKjSnrWcmHhqBzwbgR3DO', 'User', NULL, '2000-08-23', 0, '2025-10-18 07:39:25', 1, '2026-03-22 16:51:48', NULL, 0, 1, NULL, NULL, NULL, 0),
(26, 'User29', '1223@gmail.com', '$2y$10$/7XXYTYwv2HWPYg1LJvJluLhu89w3AcrBrJSuHI4KRWbLBARg0XQG', 'User', NULL, '2001-09-23', 0, '2026-03-20 08:46:39', 1, NULL, '+375333368525', 0, 0, '44fb952cd27e81ffad84bfab21084d3a928a7b93adb49cab0d826244084db3c6', NULL, NULL, 0),
(27, 'User30', '12223@gmail.com', '$2y$10$qjh8ryxlhySfiQ/ZakiHouVH6GqCoaP3atJJDA79VCZIGVieiYKlq', 'User', NULL, '2001-09-23', 0, '2026-03-20 08:51:13', 1, NULL, '+375333368525', 0, 0, '226160e3469d4d21f97bfd672013bbf4d2e0ab9479668e05ee362770830a9e2c', NULL, NULL, 0),
(28, 'User31', '1221123@gmail.com', '$2y$10$ThE5mbb9/o83FJlCllNt.eNL/dnIF9U2ocbG4NTjoKdiS.wZ6B282', 'User', NULL, '2001-09-23', 0, '2026-03-20 08:54:33', 1, '2026-03-20 11:54:49', '+375333368525', 0, 0, '51f5a7d1272fd8f474d44116e48d6e079fce57799ba08bb5373dbdb7083485be', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `user_coupons`
--

CREATE TABLE `user_coupons` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `coupon_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_coupons`
--

INSERT INTO `user_coupons` (`id`, `user_id`, `coupon_id`, `assigned_at`, `expires_at`, `is_used`) VALUES
(1, 28, 2, '2026-03-20 08:54:33', '2026-04-19 09:54:33', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `wishlist`
--

INSERT INTO `wishlist` (`id`, `user_id`, `product_id`, `created_at`) VALUES
(22, 25, 43, '2026-03-20 17:53:58'),
(23, 25, 46, '2026-03-20 17:54:00'),
(24, 25, 1, '2026-03-20 17:57:56'),
(62, 9, 45, '2026-03-20 19:19:54'),
(64, 9, 28, '2026-03-20 19:20:01'),
(65, 9, 35, '2026-03-20 19:20:02'),
(66, 9, 43, '2026-03-20 19:20:03'),
(67, 9, 46, '2026-03-20 19:20:12'),
(68, 9, 29, '2026-03-20 19:20:23'),
(70, 9, 24, '2026-03-20 19:25:07'),
(72, 9, 21, '2026-03-21 08:59:19');

-- --------------------------------------------------------

--
-- Структура таблицы `withdrawal_requests`
--

CREATE TABLE `withdrawal_requests` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `bank_account` varchar(255) NOT NULL,
  `bank_holder` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','processing','completed','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `attributes`
--
ALTER TABLE `attributes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attributes_slug_unique` (`slug`);

--
-- Индексы таблицы `attribute_values`
--
ALTER TABLE `attribute_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attribute_values_attribute_id_value_unique` (`attribute_id`,`value`);

--
-- Индексы таблицы `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_cart_session_id` (`session_id`);

--
-- Индексы таблицы `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cart_items_cart_id_product_id_variation_id_unique` (`cart_id`,`product_id`,`variation_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `variation_id` (`variation_id`);

--
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categories_slug_unique` (`slug`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Индексы таблицы `category_attributes`
--
ALTER TABLE `category_attributes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_attribute_unique` (`category_id`,`attribute_id`),
  ADD KEY `attribute_id` (`attribute_id`);

--
-- Индексы таблицы `chat_logs`
--
ALTER TABLE `chat_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `compare`
--
ALTER TABLE `compare`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_product_unique` (`user_id`,`product_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coupons_code_unique` (`code`),
  ADD KEY `idx_coupons_status` (`status`),
  ADD KEY `idx_coupons_dates` (`starts_at`,`expires_at`),
  ADD KEY `idx_coupons_seller_id` (`seller_id`),
  ADD KEY `idx_coupons_code` (`code`);

--
-- Индексы таблицы `coupon_categories`
--
ALTER TABLE `coupon_categories`
  ADD PRIMARY KEY (`coupon_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Индексы таблицы `coupon_products`
--
ALTER TABLE `coupon_products`
  ADD PRIMARY KEY (`coupon_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `coupon_usage`
--
ALTER TABLE `coupon_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coupon_id` (`coupon_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Индексы таблицы `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orders_order_number_unique` (`order_number`),
  ADD KEY `shipping_method_id` (`shipping_method_id`),
  ADD KEY `payment_method_id` (`payment_method_id`),
  ADD KEY `idx_orders_user_id` (`user_id`),
  ADD KEY `idx_orders_status_id` (`status_id`),
  ADD KEY `idx_orders_created_at` (`created_at`);

--
-- Индексы таблицы `order_addresses`
--
ALTER TABLE `order_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Индексы таблицы `order_discounts`
--
ALTER TABLE `order_discounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `coupon_id` (`coupon_id`);

--
-- Индексы таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_seller_id` (`order_seller_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `variation_id` (`variation_id`);

--
-- Индексы таблицы `order_sellers`
--
ALTER TABLE `order_sellers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `idx_order_sellers_seller_id` (`seller_id`),
  ADD KEY `idx_order_sellers_status_id` (`status_id`);

--
-- Индексы таблицы `order_statuses`
--
ALTER TABLE `order_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_statuses_slug_unique` (`slug`);

--
-- Индексы таблицы `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `order_seller_id` (`order_seller_id`),
  ADD KEY `status_id` (`status_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Индексы таблицы `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_methods_code_unique` (`code`);

--
-- Индексы таблицы `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `products_slug_unique` (`slug`),
  ADD UNIQUE KEY `products_sku_unique` (`sku`),
  ADD KEY `idx_products_seller_id` (`seller_id`),
  ADD KEY `idx_products_category_id` (`category_id`),
  ADD KEY `idx_products_status` (`status`),
  ADD KEY `idx_products_price` (`price`),
  ADD KEY `idx_products_rating` (`rating`),
  ADD KEY `idx_products_created_at` (`created_at`);

--
-- Индексы таблицы `product_attributes`
--
ALTER TABLE `product_attributes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `attribute_id` (`attribute_id`);

--
-- Индексы таблицы `product_compatibility`
--
ALTER TABLE `product_compatibility`
  ADD PRIMARY KEY (`product_id`,`compatible_with_product_id`),
  ADD KEY `compatible_with_product_id` (`compatible_with_product_id`);

--
-- Индексы таблицы `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `variation_id` (`variation_id`);

--
-- Индексы таблицы `product_tags`
--
ALTER TABLE `product_tags`
  ADD PRIMARY KEY (`product_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Индексы таблицы `product_variations`
--
ALTER TABLE `product_variations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_variations_sku_unique` (`sku`),
  ADD KEY `idx_product_variations_product_id` (`product_id`);

--
-- Индексы таблицы `product_views`
--
ALTER TABLE `product_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_views_user_id` (`user_id`),
  ADD KEY `idx_product_views_session_id` (`session_id`),
  ADD KEY `idx_product_views_product` (`product_type`,`product_id`);

--
-- Индексы таблицы `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `promotions_slug_unique` (`slug`);

--
-- Индексы таблицы `promotion_products`
--
ALTER TABLE `promotion_products`
  ADD PRIMARY KEY (`promotion_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `recently_viewed`
--
ALTER TABLE `recently_viewed`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_recently_viewed_user_id` (`user_id`),
  ADD KEY `idx_recently_viewed_session_id` (`session_id`);

--
-- Индексы таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `idx_reviews_product_id` (`product_id`),
  ADD KEY `idx_reviews_user_id` (`user_id`),
  ADD KEY `idx_reviews_status` (`status`),
  ADD KEY `idx_reviews_rating` (`rating`),
  ADD KEY `idx_reviews_created_at` (`created_at`);

--
-- Индексы таблицы `review_helpful`
--
ALTER TABLE `review_helpful`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `review_helpful_review_id_user_id_unique` (`review_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `review_replies`
--
ALTER TABLE `review_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `review_id` (`review_id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Индексы таблицы `sellers`
--
ALTER TABLE `sellers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sellers_shop_slug_unique` (`shop_slug`),
  ADD KEY `idx_sellers_user_id` (`user_id`),
  ADD KEY `idx_sellers_rating` (`rating`);

--
-- Индексы таблицы `seller_transactions`
--
ALTER TABLE `seller_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_seller_id` (`order_seller_id`),
  ADD KEY `idx_seller_transactions_seller_id` (`seller_id`),
  ADD KEY `idx_seller_transactions_status` (`status`);

--
-- Индексы таблицы `shipping_methods`
--
ALTER TABLE `shipping_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shipping_methods_code_unique` (`code`);

--
-- Индексы таблицы `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tags_name_unique` (`name`),
  ADD UNIQUE KEY `tags_slug_unique` (`slug`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_login_unique` (`login`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Индексы таблицы `user_coupons`
--
ALTER TABLE `user_coupons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `coupon_id` (`coupon_id`);

--
-- Индексы таблицы `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wishlist_user_id_product_id_unique` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `attributes`
--
ALTER TABLE `attributes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=122;

--
-- AUTO_INCREMENT для таблицы `attribute_values`
--
ALTER TABLE `attribute_values`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT для таблицы `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `category_attributes`
--
ALTER TABLE `category_attributes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT для таблицы `chat_logs`
--
ALTER TABLE `chat_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `compare`
--
ALTER TABLE `compare`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT для таблицы `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `coupon_usage`
--
ALTER TABLE `coupon_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT для таблицы `order_addresses`
--
ALTER TABLE `order_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `order_discounts`
--
ALTER TABLE `order_discounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT для таблицы `order_sellers`
--
ALTER TABLE `order_sellers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `order_statuses`
--
ALTER TABLE `order_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT для таблицы `product_attributes`
--
ALTER TABLE `product_attributes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=206;

--
-- AUTO_INCREMENT для таблицы `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `product_variations`
--
ALTER TABLE `product_variations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT для таблицы `product_views`
--
ALTER TABLE `product_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT для таблицы `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `recently_viewed`
--
ALTER TABLE `recently_viewed`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `review_helpful`
--
ALTER TABLE `review_helpful`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `review_replies`
--
ALTER TABLE `review_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `sellers`
--
ALTER TABLE `sellers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `seller_transactions`
--
ALTER TABLE `seller_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `shipping_methods`
--
ALTER TABLE `shipping_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT для таблицы `user_coupons`
--
ALTER TABLE `user_coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT для таблицы `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `attribute_values`
--
ALTER TABLE `attribute_values`
  ADD CONSTRAINT `attribute_values_ibfk_1` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `cart` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_3` FOREIGN KEY (`variation_id`) REFERENCES `product_variations` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `category_attributes`
--
ALTER TABLE `category_attributes`
  ADD CONSTRAINT `category_attributes_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_attributes_ibfk_2` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `chat_logs`
--
ALTER TABLE `chat_logs`
  ADD CONSTRAINT `chat_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `compare`
--
ALTER TABLE `compare`
  ADD CONSTRAINT `compare_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `compare_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `coupons`
--
ALTER TABLE `coupons`
  ADD CONSTRAINT `coupons_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `coupon_categories`
--
ALTER TABLE `coupon_categories`
  ADD CONSTRAINT `coupon_categories_ibfk_1` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coupon_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `coupon_products`
--
ALTER TABLE `coupon_products`
  ADD CONSTRAINT `coupon_products_ibfk_1` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coupon_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `coupon_usage`
--
ALTER TABLE `coupon_usage`
  ADD CONSTRAINT `coupon_usage_ibfk_1` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coupon_usage_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coupon_usage_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `order_statuses` (`id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`),
  ADD CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`);

--
-- Ограничения внешнего ключа таблицы `order_addresses`
--
ALTER TABLE `order_addresses`
  ADD CONSTRAINT `order_addresses_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_discounts`
--
ALTER TABLE `order_discounts`
  ADD CONSTRAINT `order_discounts_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_discounts_ibfk_2` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_seller_id`) REFERENCES `order_sellers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`variation_id`) REFERENCES `product_variations` (`id`);

--
-- Ограничения внешнего ключа таблицы `order_sellers`
--
ALTER TABLE `order_sellers`
  ADD CONSTRAINT `order_sellers_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_sellers_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_sellers_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `order_statuses` (`id`);

--
-- Ограничения внешнего ключа таблицы `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_status_history_ibfk_2` FOREIGN KEY (`order_seller_id`) REFERENCES `order_sellers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_status_history_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `order_statuses` (`id`),
  ADD CONSTRAINT `order_status_history_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Ограничения внешнего ключа таблицы `product_attributes`
--
ALTER TABLE `product_attributes`
  ADD CONSTRAINT `product_attributes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_attributes_ibfk_2` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_compatibility`
--
ALTER TABLE `product_compatibility`
  ADD CONSTRAINT `product_compatibility_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_compatibility_ibfk_2` FOREIGN KEY (`compatible_with_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_images_ibfk_2` FOREIGN KEY (`variation_id`) REFERENCES `product_variations` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_tags`
--
ALTER TABLE `product_tags`
  ADD CONSTRAINT `product_tags_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_variations`
--
ALTER TABLE `product_variations`
  ADD CONSTRAINT `product_variations_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_views`
--
ALTER TABLE `product_views`
  ADD CONSTRAINT `product_views_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `promotion_products`
--
ALTER TABLE `promotion_products`
  ADD CONSTRAINT `promotion_products_ibfk_1` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `promotion_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `recently_viewed`
--
ALTER TABLE `recently_viewed`
  ADD CONSTRAINT `recently_viewed_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recently_viewed_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `review_helpful`
--
ALTER TABLE `review_helpful`
  ADD CONSTRAINT `review_helpful_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_helpful_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `review_replies`
--
ALTER TABLE `review_replies`
  ADD CONSTRAINT `review_replies_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_replies_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `sellers`
--
ALTER TABLE `sellers`
  ADD CONSTRAINT `sellers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `seller_transactions`
--
ALTER TABLE `seller_transactions`
  ADD CONSTRAINT `seller_transactions_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seller_transactions_ibfk_2` FOREIGN KEY (`order_seller_id`) REFERENCES `order_sellers` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `user_coupons`
--
ALTER TABLE `user_coupons`
  ADD CONSTRAINT `user_coupons_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_coupons_ibfk_2` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  ADD CONSTRAINT `withdrawal_requests_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `withdrawal_requests_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
