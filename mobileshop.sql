-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Окт 18 2025 г., 20:03
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
-- База данных: `mobileshop`
--

-- --------------------------------------------------------

--
-- Структура таблицы `accessories`
--

CREATE TABLE `accessories` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `brand` varchar(100) NOT NULL,
  `model` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `accessories`
--

INSERT INTO `accessories` (`id`, `category_id`, `name`, `brand`, `model`, `price`, `stock`, `description`, `created_at`, `updated_at`) VALUES
(1, 2, 'Силиконовый чехол для iPhone 15 Pro', 'Generic', 'CH-IP15P', 55.00, 50, 'Защитный силиконовый чехол для iPhone 15 Pro.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(2, 2, 'Кожаный чехол для iPhone 15', 'PremiumCover', 'CC-IP15', 79.00, 30, 'Кожаный чехол для iPhone 15.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(3, 2, 'Прозрачный чехол для Samsung Galaxy S23 Ultra', 'ClearCase', 'CC-S23U', 42.00, 40, 'Прозрачный чехол для Samsung Galaxy S23 Ultra.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(4, 2, 'Бамбуковый чехол для Samsung Galaxy S23', 'EcoCase', 'BC-S23', 50.00, 35, 'Бамбуковый чехол для Samsung Galaxy S23.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(5, 2, 'Чехол-накладка для Xiaomi 13 Pro', 'ProtectX', 'PC-M13P', 36.00, 60, 'Чехол-накладка для Xiaomi 13 Pro.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(6, 2, 'Чехол-слайдер для Xiaomi Redmi Note 12 Pro', 'SliderCase', 'SC-RN12P', 52.00, 25, 'Чехол-слайдер для Xiaomi Redmi Note 12 Pro.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(7, 2, 'Прочный чехол для Google Pixel 7 Pro', 'Duracase', 'DC-G7P', 44.00, 45, 'Прочный чехол для Google Pixel 7 Pro.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(8, 3, 'Защитное стекло для iPhone 15 Pro', 'ScreenGuard', 'SG-IP15P', 79.00, 70, 'Защитное стекло для iPhone 15 Pro.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(9, 3, 'Защитное стекло для iPhone 15', 'ScreenGuard', 'SG-IP15', 74.00, 65, 'Защитное стекло для iPhone 15.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(10, 3, 'Защитное стекло для Samsung Galaxy S23 Ultra', 'UltraShield', 'US-S23U', 87.00, 55, 'Защитное стекло для Samsung Galaxy S23 Ultra.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(11, 3, 'Защитное стекло для Samsung Galaxy S23', 'UltraShield', 'US-S23', 76.00, 60, 'Защитное стекло для Samsung Galaxy S23.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(12, 3, 'Защитное стекло для Xiaomi 13 Pro', 'CrystalShield', 'CS-M13P', 65.00, 80, 'Защитное стекло для Xiaomi 13 Pro.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(13, 3, 'Защитное стекло для Xiaomi Redmi Note 12 Pro', 'CrystalShield', 'CS-RN12P', 52.00, 90, 'Защитное стекло для Xiaomi Redmi Note 12 Pro.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(14, 4, 'Быстрое зарядное устройство 20W для iPhone', 'FastCharge', 'FC-IP', 210.00, 40, 'Быстрое зарядное устройство для iPhone.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(15, 4, 'Зарядное устройство 25W для Samsung', 'QuickCharge', 'QC-S23', 236.00, 35, 'Зарядное устройство для Samsung Galaxy.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(16, 4, 'Беспроводное зарядное устройство для iPhone', 'WirelessPro', 'WP-IP', 342.00, 30, 'Беспроводное зарядное устройство для iPhone.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(17, 4, 'Сетевое зарядное устройство 30W для Xiaomi', 'ChargeMax', 'CM-M13P', 184.00, 45, 'Сетевое зарядное устройство для Xiaomi.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(19, 4, 'Универсальное зарядное устройство 45W', 'UniversalCharge', 'UC45', 399.00, 20, 'Универсальное зарядное устройство 45W для разных устройств.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(20, 5, 'Беспроводные наушники True Wireless для iPhone', 'SoundAir', 'SA-IP', 1100.00, 25, 'Беспроводные наушники True Wireless для iPhone.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(21, 5, 'Беспроводные наушники с шумоподавлением для Samsung', 'NoiseBlock', 'NB-S23', 1375.00, 20, 'Беспроводные наушники с шумоподавлением для Samsung.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(22, 5, 'Проводные наушники для Xiaomi', 'ClearSound', 'CS-M13P', 275.00, 30, 'Проводные наушники для Xiaomi.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(23, 5, 'Спортивные наушники для Google Pixel', 'SportBeats', 'SB-G7P', 410.00, 35, 'Спортивные наушники для Google Pixel.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(24, 5, 'Накладные наушники для OnePlus', 'OverEar', 'OE-OP11', 545.00, 15, 'Накладные наушники для OnePlus.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(25, 5, 'Беспроводные наушники-вкладыши для Realme', 'InEar', 'IE-GTN5', 246.00, 40, 'Беспроводные наушники-вкладыши для Realme.', '2025-02-15 18:01:31', '2025-10-18 15:37:08'),
(26, 2, 'Прозрачный силиконовый чехол для Google Pixel 7', 'ClearShield', 'CS-G7', 42.00, 30, 'Легкий прозрачный силиконовый чехол для Google Pixel 7.', '2025-02-15 18:02:13', '2025-10-18 15:37:08'),
(27, 2, 'Защитный чехол-накладка для Huawei P60 Pro', 'ShieldPro', 'SP-P60P', 64.00, 40, 'Защитный чехол-накладка для Huawei P60 Pro.', '2025-02-15 18:02:13', '2025-10-18 15:37:08'),
(28, 3, 'Защитное стекло для OnePlus 11', 'OneShield', 'OS-OP11', 55.00, 50, 'Защитное стекло для OnePlus 11 с олеофобным покрытием.', '2025-02-15 18:02:13', '2025-10-18 15:37:08'),
(29, 3, 'Защитное стекло для Realme GT Neo 5', 'CrystalGuard', 'CG-GTN5', 50.00, 45, 'Защитное стекло для Realme GT Neo 5.', '2025-02-15 18:02:13', '2025-10-18 15:37:08'),
(30, 4, 'Беспроводное зарядное устройство с креплением для Xiaomi Redmi Note 12 Pro', 'ChargeMate', 'CM-RN12P', 342.00, 25, 'Беспроводное зарядное устройство с магнитным креплением для Xiaomi Redmi Note 12 Pro.', '2025-02-15 18:02:13', '2025-10-18 15:37:08'),
(31, 4, 'Сетевое зарядное устройство 20W для OnePlus 11', 'QuickPower', 'QP-OP11', 210.00, 35, 'Сетевое зарядное устройство 20W для OnePlus 11.', '2025-02-15 18:02:13', '2025-10-18 15:37:08'),
(32, 5, 'Беспроводные наушники-вкладыши для Huawei P60 Pro', 'SoundBeats', 'SB-P60P', 246.00, 40, 'Беспроводные наушники-вкладыши для Huawei P60 Pro.', '2025-02-15 18:02:13', '2025-10-18 15:37:08'),
(33, 5, 'Накладные наушники с микрофоном для Samsung Galaxy S23', 'VoiceClear', 'VC-S23', 18.00, 20, 'Накладные наушники с микрофоном для Samsung Galaxy S23.', '2025-02-15 18:02:13', '2025-10-18 15:37:08'),
(34, 5, 'Спортивные наушники для Xiaomi Redmi Note 12 Pro', 'SportSound', 'SS-RN12P', 13.00, 30, 'Спортивные наушники для Xiaomi Redmi Note 12 Pro.', '2025-02-15 18:02:13', '2025-10-18 15:37:08'),
(35, 5, 'Беспроводные наушники True Wireless для OnePlus 11', 'AirBeats', 'AB-OP11', 41.00, 15, 'Беспроводные наушники True Wireless для OnePlus 11.', '2025-02-15 18:02:13', '2025-10-18 15:37:08');

-- --------------------------------------------------------

--
-- Структура таблицы `accessory_supported_smartphones`
--

CREATE TABLE `accessory_supported_smartphones` (
  `accessory_id` int(11) NOT NULL,
  `smartphone_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `accessory_supported_smartphones`
--

INSERT INTO `accessory_supported_smartphones` (`accessory_id`, `smartphone_id`) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 4),
(5, 5),
(6, 6),
(7, 7),
(8, 1),
(9, 2),
(10, 3),
(11, 4),
(12, 5),
(13, 6),
(14, 1),
(15, 3),
(16, 2),
(17, 5),
(19, 5),
(20, 1),
(21, 3),
(22, 5),
(23, 7),
(24, 9),
(25, 10),
(26, 8),
(27, 11),
(28, 9),
(29, 10),
(30, 6),
(31, 9),
(32, 11),
(33, 4),
(34, 6),
(35, 9);

-- --------------------------------------------------------

--
-- Структура таблицы `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `smartphone_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(4, 'Зарядные устройства'),
(5, 'Наушники'),
(1, 'Смартфоны'),
(3, 'Стекла'),
(2, 'Чехлы');

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
(1, 9, 'как подобрать телефон?', 'Чтобы подобрать телефон:\n\n1. Определите бюджет\n2. Выберите нужный размер экрана\n3. Решите, какая камера важнее\n4. Определитесь с объемом памяти\n5. Посмотрите отзывы о моделях\n\nМогу помочь с конкретными критериями!', '::1', '2025-10-18 17:23:43');

-- --------------------------------------------------------

--
-- Структура таблицы `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `type` enum('global','personal') NOT NULL DEFAULT 'global',
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `discount_type` enum('percent','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `expires_at` date NOT NULL,
  `status` enum('active','used','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `type`, `user_id`, `product_id`, `discount_type`, `discount_value`, `expires_at`, `status`, `created_at`) VALUES
(18, 'F7DB856A', '', NULL, 1, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 15:47:20'),
(19, '6F84431F', '', NULL, 1, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:42:41'),
(20, '91D3F15F', '', NULL, 1, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:44:44'),
(21, 'CF4F7896', '', NULL, 1, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:56:15'),
(22, '090ED2C5', '', NULL, 1, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:56:51'),
(23, '89A6C2DB', '', NULL, 1, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:56:58'),
(24, '058DBCF0', '', NULL, 1, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:57:25'),
(25, 'BF9DD3AA', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:08'),
(26, '6DF80F2B', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(27, 'FD0BAC31', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(28, '1F077E40', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(29, 'A9861200', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(30, '9C5C699A', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(31, 'EABF45B0', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(32, '31911070', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(33, 'A87FA17D', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(34, '4EF7E24C', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(35, '4AC924D8', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(36, '82F5EF30', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(37, '2B5A427F', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(38, 'FBA9C770', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(39, 'EBB6ED7E', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(40, '2608A282', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(41, '1CBF1068', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(42, 'B160BEBB', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(43, 'ACC0DE79', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(44, 'FFF173F0', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(45, 'D66DF53C', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(46, '05C9AA2D', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(47, '069C7D61', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(48, '79F5ECF0', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(49, '90C11BB8', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(50, 'AD115D24', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(51, '2834D503', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(52, '95157178', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(53, 'DF869A39', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(54, '69E6C784', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 16:58:09'),
(55, '0C3C21E4', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(56, '8DA45E9F', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(57, '0F017081', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(58, '1D827739', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(59, '093C5DDC', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(60, '09490A4F', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(61, '46C65BB8', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(62, '6AC5967C', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(63, '4FB9D243', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(64, 'F515730C', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(65, 'F5325AD9', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(66, 'A2B239A1', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(67, '7F1A0FF9', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(68, 'A32E0954', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(69, '6DF676A5', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(70, '413FD2BF', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(71, 'D9AD0773', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(72, 'D53BE9B3', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(73, '594D628D', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(74, '8D9076A8', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(75, 'A6A2418C', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(76, '771AE875', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(77, 'EBE92E7B', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(78, '777AF616', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(79, '5EDA6336', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(80, '4D8A9410', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(81, 'A695CDC2', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(82, '2AABE630', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(83, '5E7E765B', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(84, '3CB09198', '', NULL, 7, 'percent', 0.00, '0000-00-00', 'active', '2025-10-12 17:57:32'),
(88, 'USER2025941EE4', 'personal', 20, NULL, 'percent', 10.00, '2025-11-14', 'used', '2025-10-15 20:48:46'),
(89, 'USER22D66997CF', 'personal', NULL, NULL, 'percent', 10.00, '2025-11-15', 'used', '2025-10-16 10:00:57'),
(90, 'USER23CCC5D6F0', 'personal', NULL, NULL, 'percent', 10.00, '2025-11-15', 'used', '2025-10-16 10:21:57'),
(91, 'USER244AB4C2E1', 'personal', NULL, NULL, 'percent', 10.00, '2025-11-15', 'used', '2025-10-16 10:45:21'),
(92, 'C2A1C345', 'global', NULL, NULL, 'percent', 0.00, '0000-00-00', 'active', '2025-10-16 11:23:45'),
(93, 'DE27A696', 'global', NULL, NULL, 'percent', 0.00, '0000-00-00', 'active', '2025-10-16 11:23:45'),
(94, '06B7E292', 'global', NULL, NULL, 'percent', 0.00, '0000-00-00', 'active', '2025-10-16 11:23:45'),
(95, 'E836F6E5', 'global', NULL, NULL, 'percent', 0.00, '0000-00-00', 'active', '2025-10-16 11:23:45'),
(96, '13310789', 'global', NULL, NULL, 'percent', 0.00, '0000-00-00', 'active', '2025-10-16 11:23:45'),
(97, '32F1F679', 'global', NULL, NULL, 'percent', 0.00, '0000-00-00', 'active', '2025-10-16 11:23:45'),
(98, '3D5F95B0', 'global', NULL, NULL, 'percent', 0.00, '0000-00-00', 'active', '2025-10-16 11:23:45'),
(99, 'BE6CEFA5', 'global', NULL, NULL, 'percent', 0.00, '0000-00-00', 'active', '2025-10-16 11:23:45'),
(100, '2621A41E', 'global', NULL, NULL, 'percent', 0.00, '0000-00-00', 'active', '2025-10-16 11:23:45'),
(101, '8C746541', 'global', NULL, NULL, 'percent', 0.00, '0000-00-00', 'active', '2025-10-16 11:23:45'),
(102, '53680F09', 'global', NULL, NULL, 'percent', 10.00, '2026-12-31', 'used', '2025-10-16 11:23:58'),
(103, 'FBB25ECF', 'global', NULL, 1, 'percent', 10.00, '0000-00-00', 'active', '2025-10-16 15:20:54'),
(104, '5C379135', 'global', NULL, NULL, 'percent', 10.00, '0000-00-00', 'active', '2025-10-16 15:43:08'),
(105, '13C8319D', 'global', NULL, NULL, 'percent', 1.00, '0000-00-00', 'active', '2025-10-16 15:44:26'),
(106, '742EE1EC', 'global', NULL, NULL, 'percent', 10.00, '0000-00-00', 'active', '2025-10-16 16:17:58'),
(107, '30A53151', 'global', NULL, NULL, 'percent', 0.00, '0000-00-00', 'active', '2025-10-16 16:19:38'),
(108, '9567CD63', 'global', NULL, NULL, 'percent', 0.00, '2025-11-15', 'active', '2025-10-16 16:48:56'),
(109, '67916154', 'global', NULL, NULL, 'percent', 10.00, '2025-11-15', 'active', '2025-10-16 16:49:18'),
(110, '5A5B2278', 'global', NULL, NULL, 'percent', 0.00, '2025-11-15', 'active', '2025-10-16 18:51:40'),
(111, 'USER259361B5FA', 'personal', 25, NULL, 'percent', 10.00, '2025-11-17', 'active', '2025-10-18 10:39:25');

-- --------------------------------------------------------

--
-- Структура таблицы `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_price`, `status`, `created_at`) VALUES
(20, 20, 16199.82, 'pending', '2025-10-15 21:16:15'),
(21, 20, 119999.00, 'pending', '2025-10-16 09:41:00'),
(27, 9, 109999.00, 'pending', '2025-10-16 18:52:07');

-- --------------------------------------------------------

--
-- Структура таблицы `order_details`
--

CREATE TABLE `order_details` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `payment_method` enum('cash','card','online') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `order_details`
--

INSERT INTO `order_details` (`id`, `user_id`, `order_id`, `full_name`, `phone`, `address`, `payment_method`, `created_at`) VALUES
(18, 20, 20, 'qeqeqwe', '+375333362836', 'йуйуйцуйцуй', 'cash', '2025-10-15 21:16:15'),
(19, 20, 21, '23', '1233', '12312', 'cash', '2025-10-16 09:41:00'),
(25, 9, 27, 'qeqeqwe', '+375333362836', 'asdadasdads', 'cash', '2025-10-16 18:52:07');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_type` varchar(20) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_type`, `product_id`, `quantity`, `price`) VALUES
(25, 20, 'smartphone', 2, 1, 17999.80),
(26, 21, 'smartphone', 3, 1, 119999.00),
(32, 27, 'smartphone', 1, 1, 109999.00);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `product_views`
--

INSERT INTO `product_views` (`id`, `user_id`, `session_id`, `product_type`, `product_id`, `viewed_at`) VALUES
(18, NULL, '8vjnmoq5n4c5bj9uhjlsi154p4', 'smartphone', 2, '2025-02-24 18:02:41'),
(19, NULL, '8vjnmoq5n4c5bj9uhjlsi154p4', 'smartphone', 2, '2025-02-24 18:02:47'),
(30, NULL, 'gngdmbkuuu7rrgitophkg2f06d', 'smartphone', 1, '2025-03-06 15:30:57'),
(31, NULL, 'gngdmbkuuu7rrgitophkg2f06d', 'smartphone', 3, '2025-03-06 15:31:09'),
(32, NULL, 's0ei0bhjot9f6immr3fhflivfc', 'smartphone', 11, '2025-03-10 12:05:09'),
(59, NULL, '3st44ogognv23o2m0dut5kc1pt', 'smartphone', 2, '2025-10-15 19:00:09'),
(60, 9, '3st44ogognv23o2m0dut5kc1pt', 'smartphone', 10, '2025-10-15 19:06:25'),
(61, 20, '3st44ogognv23o2m0dut5kc1pt', 'smartphone', 2, '2025-10-15 20:55:50'),
(62, 20, '3st44ogognv23o2m0dut5kc1pt', 'smartphone', 3, '2025-10-15 21:23:09'),
(64, 9, '6aqkr9a41266abv397j8sbifeh', 'smartphone', 2, '2025-10-18 17:08:31'),
(65, 9, '6aqkr9a41266abv397j8sbifeh', 'smartphone', 10, '2025-10-18 17:08:38'),
(66, 9, '6aqkr9a41266abv397j8sbifeh', 'smartphone', 10, '2025-10-18 17:11:30'),
(67, 9, '6aqkr9a41266abv397j8sbifeh', 'smartphone', 10, '2025-10-18 17:11:48'),
(68, 25, '6aqkr9a41266abv397j8sbifeh', 'smartphone', 10, '2025-10-18 17:18:24'),
(69, 9, 'dn91u0hfvi4si1lo6ebo5e29b1', 'smartphone', 3, '2025-10-18 17:19:53'),
(70, 9, 'dn91u0hfvi4si1lo6ebo5e29b1', 'smartphone', 1, '2025-10-18 17:26:24'),
(71, 9, 'dn91u0hfvi4si1lo6ebo5e29b1', 'smartphone', 1, '2025-10-18 17:26:56'),
(72, 9, 'dn91u0hfvi4si1lo6ebo5e29b1', 'smartphone', 1, '2025-10-18 17:27:46'),
(73, 9, 'dn91u0hfvi4si1lo6ebo5e29b1', 'smartphone', 6, '2025-10-18 17:27:52'),
(74, 9, 'dn91u0hfvi4si1lo6ebo5e29b1', 'smartphone', 6, '2025-10-18 17:28:00'),
(75, 9, 'dn91u0hfvi4si1lo6ebo5e29b1', 'smartphone', 6, '2025-10-18 17:28:31'),
(76, 9, 'ud1rl7a3g0b3nfdubns5m92pdd', 'smartphone', 10, '2025-10-18 17:34:40'),
(77, 9, 'ud1rl7a3g0b3nfdubns5m92pdd', 'smartphone', 10, '2025-10-18 17:34:55'),
(78, 9, 'ud1rl7a3g0b3nfdubns5m92pdd', 'smartphone', 11, '2025-10-18 17:35:32'),
(79, 9, 'ud1rl7a3g0b3nfdubns5m92pdd', 'smartphone', 11, '2025-10-18 17:35:48'),
(80, 9, 'ud1rl7a3g0b3nfdubns5m92pdd', 'smartphone', 11, '2025-10-18 17:53:20'),
(81, 9, 'ud1rl7a3g0b3nfdubns5m92pdd', 'smartphone', 11, '2025-10-18 17:59:04');

-- --------------------------------------------------------

--
-- Структура таблицы `promotions`
--

CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `product_type` enum('smartphone','accessory') NOT NULL,
  `product_id` int(11) NOT NULL,
  `discount_percent` tinyint(3) UNSIGNED NOT NULL COMMENT 'Процент скидки (1–100)',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL COMMENT 'NULL = бессрочно',
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `promotions`
--

INSERT INTO `promotions` (`id`, `product_type`, `product_id`, `discount_percent`, `start_date`, `end_date`, `is_active`) VALUES
(1, 'accessory', 2, 15, '2025-04-22', '2025-04-22', 1),
(4, 'smartphone', 2, 80, '2025-04-22', NULL, 1),
(9, 'accessory', 17, 10, '2025-04-22', NULL, 1),
(10, 'accessory', 1, 20, '2025-04-22', NULL, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `recommendations`
--

CREATE TABLE `recommendations` (
  `id` int(11) NOT NULL,
  `smartphone_id` int(11) NOT NULL,
  `recommended_smartphone_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `smartphone_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `title` varchar(200) DEFAULT NULL COMMENT 'Заголовок отзыва',
  `comment` text NOT NULL,
  `pros` text DEFAULT NULL COMMENT 'Достоинства',
  `cons` text DEFAULT NULL COMMENT 'Недостатки',
  `is_verified_purchase` tinyint(1) DEFAULT 0,
  `likes_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_approved` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `reviews`
--

INSERT INTO `reviews` (`id`, `smartphone_id`, `user_id`, `rating`, `title`, `comment`, `pros`, `cons`, `is_verified_purchase`, `likes_count`, `created_at`, `updated_at`, `is_approved`) VALUES
(1, 12, 10, 4, 'Круто', 'Хороший телефон за свои деньги', 'Железо', 'Камеры', 0, 0, '2025-10-15 21:32:13', '2025-10-15 21:32:13', 1),
(2, 10, 9, 4, '23423432', '342222222222222', '23444444444444444444', '234444444444444444444', 0, 0, '2025-10-18 17:34:55', '2025-10-18 17:34:55', 1),
(3, 11, 9, 4, 'sdddddddddddddd', 'sdddddddddddddddd', 'dsssssssssf', 'fffffffffffffffffffffffggggggggggggggg', 0, 0, '2025-10-18 17:35:48', '2025-10-18 17:35:48', 1);

-- --------------------------------------------------------

--
-- Структура таблицы `review_likes`
--

CREATE TABLE `review_likes` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_helpful` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `smartphones`
--

CREATE TABLE `smartphones` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `brand` varchar(100) NOT NULL,
  `model` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `smartphones`
--

INSERT INTO `smartphones` (`id`, `name`, `brand`, `model`, `price`, `stock`, `description`, `created_at`, `updated_at`, `category_id`) VALUES
(1, 'iPhone 15 Pro', 'Apple', 'A3103', 3299.00, 15, 'Флагманский смартфон с чипом A17 Pro, OLED-дисплеем 6.1\" и тройной камерой.', '2025-02-13 08:42:56', '2025-10-18 15:33:27', 1),
(2, 'iPhone 15', 'Apple', 'A3101', 2699.00, 20, 'Модель с чипом A16 Bionic, OLED-дисплеем 6.1\" и двойной камерой.', '2025-02-13 08:42:56', '2025-10-18 15:33:27', 1),
(3, 'Samsung Galaxy S23 Ultra', 'Samsung', 'S23U', 3599.00, 10, 'Флагман Samsung с 200MP камерой, 6.8\" Dynamic AMOLED дисплеем и S Pen.', '2025-02-13 08:42:56', '2025-10-18 15:33:27', 1),
(4, 'Samsung Galaxy S23', 'Samsung', 'S23', 2599.00, 25, 'Компактный смартфон с чипом Snapdragon 8 Gen 2, AMOLED 6.1\" и 50MP камерой.', '2025-02-13 08:42:56', '2025-10-18 15:33:27', 1),
(5, 'Xiaomi 13 Pro', 'Xiaomi', 'M13P', 2399.00, 18, 'Флагман с камерой Leica, Snapdragon 8 Gen 2 и 6.73\" LTPO AMOLED дисплеем.', '2025-02-13 08:42:56', '2025-10-18 15:33:27', 1),
(6, 'Xiaomi Redmi Note 12 Pro', 'Xiaomi', 'RN12P', 1049.00, 30, 'Смартфон среднего класса с 108MP камерой, 6.67\" OLED и 5000mAh аккумулятором.', '2025-02-13 08:42:56', '2025-10-18 15:33:27', 1),
(7, 'Google Pixel 7 Pro', 'Google', 'G7P', 3149.00, 12, 'Флагман Google с Tensor G2, 50MP камерой и 6.7\" OLED дисплеем.', '2025-02-13 08:42:56', '2025-10-18 15:33:27', 1),
(8, 'Google Pixel 7', 'Google', 'G7', 865.00, 20, 'Камерафон с 50MP сенсором, OLED 6.3\" дисплеем и чистым Android.', '2025-02-13 08:42:56', '2025-10-18 15:33:27', 1),
(9, 'OnePlus 11', 'OnePlus', 'OP11', 2249.00, 17, 'Флагман OnePlus с Snapdragon 8 Gen 2, 6.7\" AMOLED и 100W зарядкой.', '2025-02-13 08:42:56', '2025-10-18 15:33:27', 1),
(10, 'Realme GT Neo 5', 'Realme', 'GTN5', 1679.00, 22, 'Мощный смартфон с 144Hz дисплеем, Snapdragon 8+ Gen 1 и быстрой зарядкой 240W.', '2025-02-13 08:42:56', '2025-10-18 15:33:27', 1),
(11, 'Huawei P60 Pro', 'Huawei', 'P60P', 2999.00, 14, 'Флагман с продвинутой камерой XMAGE, OLED дисплеем и 88W зарядкой.', '2025-02-13 08:42:56', '2025-10-18 15:33:27', 1),
(12, 'Honor Magic 5 Pro', 'Honor', 'M5P', 2849.00, 16, 'Флагман с Snapdragon 8 Gen 2, 6.81\" LTPO OLED и 5100mAh аккумулятором.', '2025-02-13 08:42:56', '2025-10-18 15:33:27', 1);

-- --------------------------------------------------------

--
-- Структура таблицы `smartphone_images`
--

CREATE TABLE `smartphone_images` (
  `id` int(11) NOT NULL,
  `smartphone_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `smartphone_images`
--

INSERT INTO `smartphone_images` (`id`, `smartphone_id`, `image_url`, `is_primary`) VALUES
(1, 1, 'uploads/1.jpg', 0),
(2, 2, 'uploads/2.jpg', 0),
(3, 3, 'uploads/3.jpg', 0),
(4, 4, 'uploads/4.jpg', 0),
(5, 5, 'uploads/5.jpg', 0),
(6, 6, 'uploads/6.jpg', 0),
(7, 7, 'uploads/7.jpg', 0),
(8, 8, 'uploads/8.jpg', 0),
(9, 9, 'uploads/9.jpg', 0),
(10, 10, 'uploads/10.jpg', 0),
(11, 11, 'uploads/11.jpg', 0),
(12, 12, 'uploads/12.jpg', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `smartphone_specs`
--

CREATE TABLE `smartphone_specs` (
  `id` int(11) NOT NULL,
  `smartphone_id` int(11) NOT NULL,
  `screen_size` decimal(4,2) NOT NULL,
  `resolution` varchar(50) NOT NULL,
  `processor` varchar(255) NOT NULL,
  `ram` int(11) NOT NULL,
  `storage` int(11) NOT NULL,
  `battery_capacity` int(11) NOT NULL,
  `camera_main` varchar(255) NOT NULL,
  `camera_front` varchar(255) NOT NULL,
  `os` varchar(100) NOT NULL,
  `color` varchar(50) NOT NULL,
  `weight` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `smartphone_specs`
--

INSERT INTO `smartphone_specs` (`id`, `smartphone_id`, `screen_size`, `resolution`, `processor`, `ram`, `storage`, `battery_capacity`, `camera_main`, `camera_front`, `os`, `color`, `weight`) VALUES
(1, 1, 6.10, '2556x1179', 'Apple A17 Pro', 8, 256, 3274, '48MP + 12MP + 12MP', '12MP', 'iOS 17', 'Titan Gray', 187.00),
(2, 2, 6.10, '2556x1179', 'Apple A16 Bionic', 6, 128, 3274, '48MP + 12MP', '12MP', 'iOS 17', 'Blue', 171.00),
(3, 3, 6.80, '3088x1440', 'Snapdragon 8 Gen 2', 12, 512, 5000, '200MP + 12MP + 10MP + 10MP', '12MP', 'Android 13', 'Phantom Black', 234.00),
(4, 4, 6.10, '2340x1080', 'Snapdragon 8 Gen 2', 8, 256, 3900, '50MP + 10MP + 12MP', '12MP', 'Android 13', 'Lavender', 168.00),
(5, 5, 6.73, '3200x1440', 'Snapdragon 8 Gen 2', 12, 512, 4820, '50MP + 50MP + 50MP', '32MP', 'Android 13 (MIUI 14)', 'Black', 229.00),
(6, 6, 6.67, '2400x1080', 'MediaTek Dimensity 1080', 8, 256, 5000, '108MP + 8MP + 2MP', '16MP', 'Android 13 (MIUI 14)', 'White', 201.00),
(7, 7, 6.70, '3120x1440', 'Google Tensor G2', 12, 512, 5000, '50MP + 48MP + 12MP', '10.8MP', 'Android 13', 'Obsidian', 212.00),
(8, 8, 6.30, '2400x1080', 'Google Tensor G2', 8, 256, 4355, '50MP + 12MP', '10.8MP', 'Android 13', 'Lemongrass', 197.00),
(9, 9, 6.70, '3216x1440', 'Snapdragon 8 Gen 2', 16, 512, 5000, '50MP + 48MP + 32MP', '16MP', 'Android 13 (OxygenOS)', 'Emerald Green', 205.00),
(10, 10, 6.74, '2772x1240', 'Snapdragon 8+ Gen 1', 16, 512, 4600, '50MP + 8MP + 2MP', '16MP', 'Android 13 (Realme UI 4.0)', 'Purple', 199.00),
(11, 11, 6.67, '2700x1220', 'Snapdragon 8+ Gen 1', 12, 512, 4815, '48MP + 13MP + 64MP', '13MP', 'HarmonyOS 3.1', 'Black', 200.00),
(12, 12, 6.81, '2848x1312', 'Snapdragon 8 Gen 2', 12, 512, 5100, '50MP + 50MP + 50MP', '12MP', 'Android 13 (MagicOS 7.1)', 'Cyan', 219.00);

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
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `login`, `email`, `password`, `full_name`, `photo`, `birth_date`, `is_admin`, `created_at`, `is_subscribed`, `last_login`) VALUES
(9, 'Admin3', '', '$2y$10$8N..CwjS61AP7OgU2v7LeOxbcOAmvxsuhvpmUa4CtO326aSdrQM5u', 'Qwerty', NULL, '2001-05-23', 1, '2025-10-15 19:06:22', 1, '2025-10-18 20:34:36'),
(20, 'User24', 'dxddxd137@gmail.com', '$2y$10$wEFj7kz8TuvHDRvgURB8l.HEt21kJkhEULl69pTf56UhIr59vfqsW', 'qeqeqwe', NULL, '2001-04-23', 0, '2025-10-15 20:48:46', 1, NULL),
(25, 'User28', 'mtest8557@gmail.com', '$2y$10$e.pcHucaFVp7jAQvZmCcnuV.Tui4mSNlZKjSnrWcmHhqBzwbgR3DO', 'User', NULL, '2000-08-23', 0, '2025-10-18 10:39:25', 1, '2025-10-18 20:13:49');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `accessories`
--
ALTER TABLE `accessories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Индексы таблицы `accessory_supported_smartphones`
--
ALTER TABLE `accessory_supported_smartphones`
  ADD PRIMARY KEY (`accessory_id`,`smartphone_id`),
  ADD KEY `smartphone_id` (`smartphone_id`);

--
-- Индексы таблицы `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `smartphone_id` (`smartphone_id`);

--
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Индексы таблицы `chat_logs`
--
ALTER TABLE `chat_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
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
  ADD KEY `order_id` (`order_id`),
  ADD KEY `smartphone_id` (`product_id`);

--
-- Индексы таблицы `product_views`
--
ALTER TABLE `product_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Индексы таблицы `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_type`,`product_id`);

--
-- Индексы таблицы `recommendations`
--
ALTER TABLE `recommendations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `smartphone_id` (`smartphone_id`),
  ADD KEY `recommended_smartphone_id` (`recommended_smartphone_id`);

--
-- Индексы таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `smartphone_id` (`smartphone_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `review_likes`
--
ALTER TABLE `review_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_like` (`review_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `smartphones`
--
ALTER TABLE `smartphones`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `smartphone_images`
--
ALTER TABLE `smartphone_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `smartphone_id` (`smartphone_id`);

--
-- Индексы таблицы `smartphone_specs`
--
ALTER TABLE `smartphone_specs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `smartphone_id` (`smartphone_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `accessories`
--
ALTER TABLE `accessories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT для таблицы `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `chat_logs`
--
ALTER TABLE `chat_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT для таблицы `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT для таблицы `order_details`
--
ALTER TABLE `order_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT для таблицы `order_discounts`
--
ALTER TABLE `order_discounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT для таблицы `product_views`
--
ALTER TABLE `product_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT для таблицы `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `review_likes`
--
ALTER TABLE `review_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `smartphones`
--
ALTER TABLE `smartphones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT для таблицы `smartphone_images`
--
ALTER TABLE `smartphone_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT для таблицы `smartphone_specs`
--
ALTER TABLE `smartphone_specs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `accessories`
--
ALTER TABLE `accessories`
  ADD CONSTRAINT `fk_accessories_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Ограничения внешнего ключа таблицы `accessory_supported_smartphones`
--
ALTER TABLE `accessory_supported_smartphones`
  ADD CONSTRAINT `fk_accessory` FOREIGN KEY (`accessory_id`) REFERENCES `accessories` (`id`),
  ADD CONSTRAINT `fk_smartphone` FOREIGN KEY (`smartphone_id`) REFERENCES `smartphones` (`id`);

--
-- Ограничения внешнего ключа таблицы `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`smartphone_id`) REFERENCES `smartphones` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `chat_logs`
--
ALTER TABLE `chat_logs`
  ADD CONSTRAINT `chat_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `coupons`
--
ALTER TABLE `coupons`
  ADD CONSTRAINT `coupons_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `coupons_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `smartphones` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_details_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_views`
--
ALTER TABLE `product_views`
  ADD CONSTRAINT `product_views_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `review_likes`
--
ALTER TABLE `review_likes`
  ADD CONSTRAINT `review_likes_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `smartphone_images`
--
ALTER TABLE `smartphone_images`
  ADD CONSTRAINT `smartphone_images_ibfk_1` FOREIGN KEY (`smartphone_id`) REFERENCES `smartphones` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `smartphone_specs`
--
ALTER TABLE `smartphone_specs`
  ADD CONSTRAINT `smartphone_specs_ibfk_1` FOREIGN KEY (`smartphone_id`) REFERENCES `smartphones` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
