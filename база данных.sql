-- MySQL dump 10.13  Distrib 8.0.41, for Win64 (x86_64)
--
-- Host: mysql8.hostland.ru    Database: host1760793_mobileshop
-- ------------------------------------------------------
-- Server version	5.7.44-51-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accessories`
--

DROP TABLE IF EXISTS `accessories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accessories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `brand` varchar(100) NOT NULL,
  `model` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT '0',
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `fk_accessories_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accessories`
--

LOCK TABLES `accessories` WRITE;
/*!40000 ALTER TABLE `accessories` DISABLE KEYS */;
INSERT INTO `accessories` VALUES (1,2,'Силиконовый чехол для iPhone 15 Pro','Generic','CH-IP15P',199.00,50,'Защитный силиконовый чехол для iPhone 15 Pro.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(2,2,'Кожаный чехол для iPhone 15','PremiumCover','CC-IP15',299.00,30,'Кожаный чехол для iPhone 15.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(3,2,'Прозрачный чехол для Samsung Galaxy S23 Ultra','ClearCase','CC-S23U',149.00,40,'Прозрачный чехол для Samsung Galaxy S23 Ultra.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(4,2,'Бамбуковый чехол для Samsung Galaxy S23','EcoCase','BC-S23',179.00,35,'Бамбуковый чехол для Samsung Galaxy S23.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(5,2,'Чехол-накладка для Xiaomi 13 Pro','ProtectX','PC-M13P',129.00,60,'Чехол-накладка для Xiaomi 13 Pro.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(6,2,'Чехол-слайдер для Xiaomi Redmi Note 12 Pro','SliderCase','SC-RN12P',189.00,25,'Чехол-слайдер для Xiaomi Redmi Note 12 Pro.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(7,2,'Прочный чехол для Google Pixel 7 Pro','Duracase','DC-G7P',159.00,45,'Прочный чехол для Google Pixel 7 Pro.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(8,3,'Защитное стекло для iPhone 15 Pro','ScreenGuard','SG-IP15P',299.00,70,'Защитное стекло для iPhone 15 Pro.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(9,3,'Защитное стекло для iPhone 15','ScreenGuard','SG-IP15',279.00,65,'Защитное стекло для iPhone 15.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(10,3,'Защитное стекло для Samsung Galaxy S23 Ultra','UltraShield','US-S23U',329.00,55,'Защитное стекло для Samsung Galaxy S23 Ultra.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(11,3,'Защитное стекло для Samsung Galaxy S23','UltraShield','US-S23',289.00,60,'Защитное стекло для Samsung Galaxy S23.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(12,3,'Защитное стекло для Xiaomi 13 Pro','CrystalShield','CS-M13P',249.00,80,'Защитное стекло для Xiaomi 13 Pro.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(13,3,'Защитное стекло для Xiaomi Redmi Note 12 Pro','CrystalShield','CS-RN12P',199.00,90,'Защитное стекло для Xiaomi Redmi Note 12 Pro.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(14,4,'Быстрое зарядное устройство 20W для iPhone','FastCharge','FC-IP',799.00,40,'Быстрое зарядное устройство для iPhone.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(15,4,'Зарядное устройство 25W для Samsung','QuickCharge','QC-S23',899.00,35,'Зарядное устройство для Samsung Galaxy.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(16,4,'Беспроводное зарядное устройство для iPhone','WirelessPro','WP-IP',1299.00,30,'Беспроводное зарядное устройство для iPhone.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(17,4,'Сетевое зарядное устройство 30W для Xiaomi','ChargeMax','CM-M13P',699.00,45,'Сетевое зарядное устройство для Xiaomi.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(19,4,'Универсальное зарядное устройство 45W','UniversalCharge','UC45',1499.00,20,'Универсальное зарядное устройство 45W для разных устройств.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(20,5,'Беспроводные наушники True Wireless для iPhone','SoundAir','SA-IP',3999.00,25,'Беспроводные наушники True Wireless для iPhone.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(21,5,'Беспроводные наушники с шумоподавлением для Samsung','NoiseBlock','NB-S23',4999.00,20,'Беспроводные наушники с шумоподавлением для Samsung.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(22,5,'Проводные наушники для Xiaomi','ClearSound','CS-M13P',999.00,30,'Проводные наушники для Xiaomi.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(23,5,'Спортивные наушники для Google Pixel','SportBeats','SB-G7P',1499.00,35,'Спортивные наушники для Google Pixel.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(24,5,'Накладные наушники для OnePlus','OverEar','OE-OP11',1999.00,15,'Накладные наушники для OnePlus.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(25,5,'Беспроводные наушники-вкладыши для Realme','InEar','IE-GTN5',899.00,40,'Беспроводные наушники-вкладыши для Realme.','2025-02-15 18:01:31','2025-02-15 18:01:31'),(26,2,'Прозрачный силиконовый чехол для Google Pixel 7','ClearShield','CS-G7',149.00,30,'Легкий прозрачный силиконовый чехол для Google Pixel 7.','2025-02-15 18:02:13','2025-02-15 18:02:13'),(27,2,'Защитный чехол-накладка для Huawei P60 Pro','ShieldPro','SP-P60P',229.00,40,'Защитный чехол-накладка для Huawei P60 Pro.','2025-02-15 18:02:13','2025-02-15 18:02:13'),(28,3,'Защитное стекло для OnePlus 11','OneShield','OS-OP11',199.00,50,'Защитное стекло для OnePlus 11 с олеофобным покрытием.','2025-02-15 18:02:13','2025-02-15 18:02:13'),(29,3,'Защитное стекло для Realme GT Neo 5','CrystalGuard','CG-GTN5',179.00,45,'Защитное стекло для Realme GT Neo 5.','2025-02-15 18:02:13','2025-02-15 18:02:13'),(30,4,'Беспроводное зарядное устройство с креплением для Xiaomi Redmi Note 12 Pro','ChargeMate','CM-RN12P',1299.00,25,'Беспроводное зарядное устройство с магнитным креплением для Xiaomi Redmi Note 12 Pro.','2025-02-15 18:02:13','2025-02-15 18:02:13'),(31,4,'Сетевое зарядное устройство 20W для OnePlus 11','QuickPower','QP-OP11',799.00,35,'Сетевое зарядное устройство 20W для OnePlus 11.','2025-02-15 18:02:13','2025-02-15 18:02:13'),(32,5,'Беспроводные наушники-вкладыши для Huawei P60 Pro','SoundBeats','SB-P60P',899.00,40,'Беспроводные наушники-вкладыши для Huawei P60 Pro.','2025-02-15 18:02:13','2025-02-15 18:02:13'),(33,5,'Накладные наушники с микрофоном для Samsung Galaxy S23','VoiceClear','VC-S23',65.00,20,'Накладные наушники с микрофоном для Samsung Galaxy S23.','2025-02-15 18:02:13','2025-03-16 09:03:52'),(34,5,'Спортивные наушники для Xiaomi Redmi Note 12 Pro','SportSound','SS-RN12P',46.00,30,'Спортивные наушники для Xiaomi Redmi Note 12 Pro.','2025-02-15 18:02:13','2025-03-16 08:48:39'),(35,5,'Беспроводные наушники True Wireless для OnePlus 11','AirBeats','AB-OP11',144.00,15,'Беспроводные наушники True Wireless для OnePlus 11.','2025-02-15 18:02:13','2025-03-16 08:42:59');
/*!40000 ALTER TABLE `accessories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `accessory_supported_smartphones`
--

DROP TABLE IF EXISTS `accessory_supported_smartphones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accessory_supported_smartphones` (
  `accessory_id` int(11) NOT NULL,
  `smartphone_id` int(11) NOT NULL,
  PRIMARY KEY (`accessory_id`,`smartphone_id`),
  KEY `smartphone_id` (`smartphone_id`),
  CONSTRAINT `fk_accessory` FOREIGN KEY (`accessory_id`) REFERENCES `accessories` (`id`),
  CONSTRAINT `fk_smartphone` FOREIGN KEY (`smartphone_id`) REFERENCES `smartphones` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accessory_supported_smartphones`
--

LOCK TABLES `accessory_supported_smartphones` WRITE;
/*!40000 ALTER TABLE `accessory_supported_smartphones` DISABLE KEYS */;
INSERT INTO `accessory_supported_smartphones` VALUES (1,1),(8,1),(14,1),(20,1),(2,2),(9,2),(16,2),(3,3),(10,3),(15,3),(21,3),(4,4),(11,4),(33,4),(5,5),(12,5),(17,5),(19,5),(22,5),(6,6),(13,6),(30,6),(34,6),(7,7),(23,7),(26,8),(24,9),(28,9),(31,9),(35,9),(25,10),(29,10),(27,11),(32,11);
/*!40000 ALTER TABLE `accessory_supported_smartphones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart`
--

DROP TABLE IF EXISTS `cart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `smartphone_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `smartphone_id` (`smartphone_id`),
  CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`smartphone_id`) REFERENCES `smartphones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart`
--

LOCK TABLES `cart` WRITE;
/*!40000 ALTER TABLE `cart` DISABLE KEYS */;
/*!40000 ALTER TABLE `cart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (4,'Зарядные устройства'),(5,'Наушники'),(1,'Смартфоны'),(3,'Стекла'),(2,'Чехлы');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_details`
--

DROP TABLE IF EXISTS `order_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `payment_method` enum('cash','card','online') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_details_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_details`
--

LOCK TABLES `order_details` WRITE;
/*!40000 ALTER TABLE `order_details` DISABLE KEYS */;
INSERT INTO `order_details` VALUES (10,1,12,'890','890','890','cash','2025-02-15 18:27:41'),(12,1,14,'Aga','84563534','456456','cash','2025-02-22 18:28:01'),(14,3,16,'1234','234','2345','card','2025-02-26 17:04:41'),(15,5,17,'User','1234','2134','card','2025-03-10 19:50:01'),(16,7,18,'User','1234','134','card','2025-03-10 20:06:46'),(17,8,19,'678','678','678','cash','2025-04-22 10:33:05');
/*!40000 ALTER TABLE `order_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_type` varchar(20) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `smartphone_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (12,12,'smartphone',2,1,89999.00),(15,14,'smartphone',2,1,89999.00),(16,14,'accessory',15,1,899.00),(18,16,'smartphone',2,1,89999.00),(19,17,'smartphone',10,1,55999.00),(20,18,'smartphone',10,1,55999.00),(21,19,'smartphone',11,1,99999.00),(22,19,'smartphone',1,1,109999.00),(23,19,'accessory',17,1,629.10),(24,19,'smartphone',2,3,17999.80);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (12,1,94998.00,'pending','2025-02-15 18:27:41'),(14,1,90898.00,'pending','2025-02-22 18:28:01'),(16,3,89999.00,'pending','2025-02-26 17:04:41'),(17,5,55999.00,'cancelled','2025-03-10 19:50:01'),(18,7,55999.00,'shipped','2025-03-10 20:06:46'),(19,8,264626.50,'pending','2025-04-22 10:33:04');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_views`
--

DROP TABLE IF EXISTS `product_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) NOT NULL,
  `product_type` enum('smartphone','accessory') NOT NULL DEFAULT 'smartphone',
  `product_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `product_views_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_views`
--

LOCK TABLES `product_views` WRITE;
/*!40000 ALTER TABLE `product_views` DISABLE KEYS */;
INSERT INTO `product_views` VALUES (1,1,'6q4luairvpmead828b1pf7u230','smartphone',2,'2025-02-22 16:24:25'),(2,1,'6q4luairvpmead828b1pf7u230','smartphone',2,'2025-02-22 16:24:33'),(3,1,'6q4luairvpmead828b1pf7u230','smartphone',8,'2025-02-22 16:24:36'),(4,1,'6q4luairvpmead828b1pf7u230','smartphone',10,'2025-02-22 16:24:45'),(5,1,'6q4luairvpmead828b1pf7u230','smartphone',12,'2025-02-22 16:24:56'),(6,1,'6q4luairvpmead828b1pf7u230','smartphone',9,'2025-02-22 16:25:02'),(7,1,'6q4luairvpmead828b1pf7u230','smartphone',5,'2025-02-22 16:25:06'),(8,1,'6q4luairvpmead828b1pf7u230','smartphone',5,'2025-02-22 16:25:10'),(9,1,'6q4luairvpmead828b1pf7u230','smartphone',5,'2025-02-22 16:25:12'),(10,1,'6q4luairvpmead828b1pf7u230','smartphone',5,'2025-02-22 16:25:15'),(11,1,'6q4luairvpmead828b1pf7u230','smartphone',5,'2025-02-22 16:25:17'),(12,1,'6q4luairvpmead828b1pf7u230','smartphone',6,'2025-02-22 16:25:20'),(13,1,'6q4luairvpmead828b1pf7u230','smartphone',6,'2025-02-22 16:26:25'),(14,1,'6q4luairvpmead828b1pf7u230','smartphone',6,'2025-02-22 16:26:35'),(15,1,'6q4luairvpmead828b1pf7u230','smartphone',11,'2025-02-22 16:26:55'),(16,1,'6q4luairvpmead828b1pf7u230','smartphone',2,'2025-02-22 18:37:54'),(17,1,'6q4luairvpmead828b1pf7u230','smartphone',2,'2025-02-22 21:09:01'),(18,NULL,'8vjnmoq5n4c5bj9uhjlsi154p4','smartphone',2,'2025-02-24 18:02:41'),(19,NULL,'8vjnmoq5n4c5bj9uhjlsi154p4','smartphone',2,'2025-02-24 18:02:47'),(20,3,'8vjnmoq5n4c5bj9uhjlsi154p4','smartphone',10,'2025-02-26 16:56:56'),(21,3,'8vjnmoq5n4c5bj9uhjlsi154p4','smartphone',12,'2025-02-26 16:57:04'),(22,3,'8vjnmoq5n4c5bj9uhjlsi154p4','smartphone',2,'2025-02-26 16:58:34'),(23,4,'8vjnmoq5n4c5bj9uhjlsi154p4','smartphone',7,'2025-02-26 17:01:05'),(24,4,'8vjnmoq5n4c5bj9uhjlsi154p4','smartphone',7,'2025-02-26 17:01:09'),(25,4,'8vjnmoq5n4c5bj9uhjlsi154p4','smartphone',6,'2025-02-26 17:01:13'),(26,4,'8vjnmoq5n4c5bj9uhjlsi154p4','smartphone',3,'2025-02-26 17:01:32'),(27,4,'8vjnmoq5n4c5bj9uhjlsi154p4','smartphone',9,'2025-02-26 17:01:47'),(28,3,'8vjnmoq5n4c5bj9uhjlsi154p4','smartphone',2,'2025-02-26 17:04:19'),(29,4,'8vjnmoq5n4c5bj9uhjlsi154p4','smartphone',5,'2025-02-26 17:05:47'),(30,NULL,'gngdmbkuuu7rrgitophkg2f06d','smartphone',1,'2025-03-06 15:30:57'),(31,NULL,'gngdmbkuuu7rrgitophkg2f06d','smartphone',3,'2025-03-06 15:31:09'),(32,NULL,'s0ei0bhjot9f6immr3fhflivfc','smartphone',11,'2025-03-10 12:05:09'),(33,4,'db1bausei5mtu7kuut5dierede','smartphone',12,'2025-03-10 19:43:13'),(34,5,'db1bausei5mtu7kuut5dierede','smartphone',10,'2025-03-10 19:46:59'),(35,5,'db1bausei5mtu7kuut5dierede','smartphone',1,'2025-03-10 19:57:28'),(36,3,'db1bausei5mtu7kuut5dierede','smartphone',2,'2025-03-10 19:58:57'),(37,3,'db1bausei5mtu7kuut5dierede','smartphone',12,'2025-03-10 19:59:27'),(38,4,'db1bausei5mtu7kuut5dierede','smartphone',12,'2025-03-10 19:59:58'),(39,4,'db1bausei5mtu7kuut5dierede','smartphone',10,'2025-03-10 20:00:42'),(40,6,'db1bausei5mtu7kuut5dierede','smartphone',10,'2025-03-10 20:02:31'),(41,7,'db1bausei5mtu7kuut5dierede','smartphone',4,'2025-03-10 20:04:05'),(42,7,'db1bausei5mtu7kuut5dierede','smartphone',9,'2025-03-10 20:06:03'),(43,7,'db1bausei5mtu7kuut5dierede','smartphone',9,'2025-03-10 20:06:04'),(44,7,'db1bausei5mtu7kuut5dierede','smartphone',9,'2025-03-10 20:06:05'),(45,7,'db1bausei5mtu7kuut5dierede','smartphone',9,'2025-03-10 20:06:06'),(46,7,'db1bausei5mtu7kuut5dierede','smartphone',12,'2025-03-10 20:06:07'),(47,7,'db1bausei5mtu7kuut5dierede','smartphone',9,'2025-03-10 20:06:15'),(48,3,'db1bausei5mtu7kuut5dierede','smartphone',9,'2025-03-10 20:07:19'),(49,8,'cujqjved3dmuhgv0pblk3qiu8e','smartphone',15,'2025-03-10 22:26:28'),(50,8,'cujqjved3dmuhgv0pblk3qiu8e','smartphone',2,'2025-03-10 22:26:50'),(51,8,'eedhknb2sl4s17g5g0pemvtcsd','smartphone',11,'2025-04-22 08:42:37'),(52,8,'eedhknb2sl4s17g5g0pemvtcsd','smartphone',1,'2025-04-22 09:51:12'),(53,8,'eedhknb2sl4s17g5g0pemvtcsd','smartphone',1,'2025-04-22 09:52:06'),(54,8,'eedhknb2sl4s17g5g0pemvtcsd','smartphone',1,'2025-04-22 10:14:44'),(55,8,'eedhknb2sl4s17g5g0pemvtcsd','smartphone',1,'2025-04-22 10:14:54'),(56,8,'eedhknb2sl4s17g5g0pemvtcsd','smartphone',2,'2025-04-22 10:32:02'),(57,8,'eedhknb2sl4s17g5g0pemvtcsd','smartphone',2,'2025-04-22 10:33:36'),(58,8,'eedhknb2sl4s17g5g0pemvtcsd','smartphone',2,'2025-04-22 10:38:58');
/*!40000 ALTER TABLE `product_views` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotions`
--

DROP TABLE IF EXISTS `promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_type` enum('smartphone','accessory') NOT NULL,
  `product_id` int(11) NOT NULL,
  `discount_percent` tinyint(3) unsigned NOT NULL COMMENT 'Процент скидки (1–100)',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL COMMENT 'NULL = бессрочно',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_type`,`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotions`
--

LOCK TABLES `promotions` WRITE;
/*!40000 ALTER TABLE `promotions` DISABLE KEYS */;
INSERT INTO `promotions` VALUES (1,'accessory',2,15,'2025-04-22','2025-04-22',1),(4,'smartphone',2,80,'2025-04-22',NULL,1),(9,'accessory',17,10,'2025-04-22',NULL,1),(10,'accessory',1,20,'2025-04-22',NULL,1);
/*!40000 ALTER TABLE `promotions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `smartphone_images`
--

DROP TABLE IF EXISTS `smartphone_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `smartphone_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `smartphone_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `smartphone_id` (`smartphone_id`),
  CONSTRAINT `smartphone_images_ibfk_1` FOREIGN KEY (`smartphone_id`) REFERENCES `smartphones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `smartphone_images`
--

LOCK TABLES `smartphone_images` WRITE;
/*!40000 ALTER TABLE `smartphone_images` DISABLE KEYS */;
INSERT INTO `smartphone_images` VALUES (1,1,'uploads/1.jpg',0),(2,2,'uploads/2.jpg',0),(3,3,'uploads/3.jpg',0),(4,4,'uploads/4.jpg',0),(5,5,'uploads/5.jpg',0),(6,6,'uploads/6.jpg',0),(7,7,'uploads/7.jpg',0),(8,8,'uploads/8.jpg',0),(9,9,'uploads/9.jpg',0),(10,10,'uploads/10.jpg',0),(11,11,'uploads/11.jpg',0),(12,12,'uploads/12.jpg',0);
/*!40000 ALTER TABLE `smartphone_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `smartphone_specs`
--

DROP TABLE IF EXISTS `smartphone_specs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `smartphone_specs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `weight` decimal(5,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `smartphone_id` (`smartphone_id`),
  CONSTRAINT `smartphone_specs_ibfk_1` FOREIGN KEY (`smartphone_id`) REFERENCES `smartphones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `smartphone_specs`
--

LOCK TABLES `smartphone_specs` WRITE;
/*!40000 ALTER TABLE `smartphone_specs` DISABLE KEYS */;
INSERT INTO `smartphone_specs` VALUES (1,1,6.10,'2556x1179','Apple A17 Pro',8,256,3274,'48MP + 12MP + 12MP','12MP','iOS 17','Titan Gray',187.00),(2,2,6.10,'2556x1179','Apple A16 Bionic',6,128,3274,'48MP + 12MP','12MP','iOS 17','Blue',171.00),(3,3,6.80,'3088x1440','Snapdragon 8 Gen 2',12,512,5000,'200MP + 12MP + 10MP + 10MP','12MP','Android 13','Phantom Black',234.00),(4,4,6.10,'2340x1080','Snapdragon 8 Gen 2',8,256,3900,'50MP + 10MP + 12MP','12MP','Android 13','Lavender',168.00),(5,5,6.73,'3200x1440','Snapdragon 8 Gen 2',12,512,4820,'50MP + 50MP + 50MP','32MP','Android 13 (MIUI 14)','Black',229.00),(6,6,6.67,'2400x1080','MediaTek Dimensity 1080',8,256,5000,'108MP + 8MP + 2MP','16MP','Android 13 (MIUI 14)','White',201.00),(7,7,6.70,'3120x1440','Google Tensor G2',12,512,5000,'50MP + 48MP + 12MP','10.8MP','Android 13','Obsidian',212.00),(8,8,6.30,'2400x1080','Google Tensor G2',8,256,4355,'50MP + 12MP','10.8MP','Android 13','Lemongrass',197.00),(9,9,6.70,'3216x1440','Snapdragon 8 Gen 2',16,512,5000,'50MP + 48MP + 32MP','16MP','Android 13 (OxygenOS)','Emerald Green',205.00),(10,10,6.74,'2772x1240','Snapdragon 8+ Gen 1',16,512,4600,'50MP + 8MP + 2MP','16MP','Android 13 (Realme UI 4.0)','Purple',199.00),(11,11,6.67,'2700x1220','Snapdragon 8+ Gen 1',12,512,4815,'48MP + 13MP + 64MP','13MP','HarmonyOS 3.1','Black',200.00),(12,12,6.81,'2848x1312','Snapdragon 8 Gen 2',12,512,5100,'50MP + 50MP + 50MP','12MP','Android 13 (MagicOS 7.1)','Cyan',219.00);
/*!40000 ALTER TABLE `smartphone_specs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `smartphones`
--

DROP TABLE IF EXISTS `smartphones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `smartphones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `brand` varchar(100) NOT NULL,
  `model` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT '0',
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `category_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `smartphones`
--

LOCK TABLES `smartphones` WRITE;
/*!40000 ALTER TABLE `smartphones` DISABLE KEYS */;
INSERT INTO `smartphones` VALUES (1,'iPhone 15 Pro','Apple','A3103',109999.00,15,'Флагманский смартфон с чипом A17 Pro, OLED-дисплеем 6.1\" и тройной камерой.','2025-02-13 08:42:56','2025-02-15 17:55:40',1),(2,'iPhone 15','Apple','A3101',89999.00,20,'Модель с чипом A16 Bionic, OLED-дисплеем 6.1\" и двойной камерой.','2025-02-13 08:42:56','2025-02-15 17:55:40',1),(3,'Samsung Galaxy S23 Ultra','Samsung','S23U',119999.00,10,'Флагман Samsung с 200MP камерой, 6.8\" Dynamic AMOLED дисплеем и S Pen.','2025-02-13 08:42:56','2025-02-15 17:55:40',1),(4,'Samsung Galaxy S23','Samsung','S23',84999.00,25,'Компактный смартфон с чипом Snapdragon 8 Gen 2, AMOLED 6.1\" и 50MP камерой.','2025-02-13 08:42:56','2025-02-15 17:55:40',1),(5,'Xiaomi 13 Pro','Xiaomi','M13P',79999.00,18,'Флагман с камерой Leica, Snapdragon 8 Gen 2 и 6.73\" LTPO AMOLED дисплеем.','2025-02-13 08:42:56','2025-02-15 17:55:40',1),(6,'Xiaomi Redmi Note 12 Pro','Xiaomi','RN12P',34999.00,30,'Смартфон среднего класса с 108MP камерой, 6.67\" OLED и 5000mAh аккумулятором.','2025-02-13 08:42:56','2025-02-15 17:55:40',1),(7,'Google Pixel 7 Pro','Google','G7P',104999.00,12,'Флагман Google с Tensor G2, 50MP камерой и 6.7\" OLED дисплеем.','2025-02-13 08:42:56','2025-02-15 17:55:40',1),(8,'Google Pixel 7','Google','G7',2892.12,20,'Камерафон с 50MP сенсором, OLED 6.3\" дисплеем и чистым Android.','2025-02-13 08:42:56','2025-03-16 08:41:02',1),(9,'OnePlus 11','OnePlus','OP11',74999.00,17,'Флагман OnePlus с Snapdragon 8 Gen 2, 6.7\" AMOLED и 100W зарядкой.','2025-02-13 08:42:56','2025-02-15 17:55:40',1),(10,'Realme GT Neo 5','Realme','GTN5',55999.00,22,'Мощный смартфон с 144Hz дисплеем, Snapdragon 8+ Gen 1 и быстрой зарядкой 240W.','2025-02-13 08:42:56','2025-02-15 17:55:40',1),(11,'Huawei P60 Pro','Huawei','P60P',99999.00,14,'Флагман с продвинутой камерой XMAGE, OLED дисплеем и 88W зарядкой.','2025-02-13 08:42:56','2025-02-15 17:55:40',1),(12,'Honor Magic 5 Pro','Honor','M5P',94999.00,16,'Флагман с Snapdragon 8 Gen 2, 6.81\" LTPO OLED и 5100mAh аккумулятором.','2025-02-13 08:42:56','2025-02-15 17:55:40',1);
/*!40000 ALTER TABLE `smartphones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin','$2y$10$RmmQM2g8dJEgubWKpU6r0eq1OCowtDFnjsgFaf0TP9W9TMw7gHFnW','Админ','photo_67adbf0360d4f3.67806690.jpg','2020-02-14',0,'2025-02-13 09:44:32'),(2,'root','$2y$10$vNP5XX00wweotQrAydsRCOpfWfxvyK2hvrwFGB/VzZ9ju5dmgAXUG','root',NULL,'2001-10-27',0,'2025-02-26 16:54:52'),(3,'user','$2y$10$jSefnduMvscjq6oEAgCkCeWtS7QEM.Rdg6vzmBhBovWsV5Ck.wo3C','User',NULL,'2025-01-28',0,'2025-02-26 16:56:38'),(4,'user1','$2y$10$uqHaqf5o.x5NEajEr1XKyOjf2ekLLSBLQDNPKRnwUOLAttx6IvOhq','User',NULL,'2001-04-23',0,'2025-02-26 17:00:50'),(5,'user2','$2y$10$wxlXg03oKhkUeEIY8uByTerKGoCizl4MnuKlrviuRIiL0MoXmq91S','User',NULL,'2023-03-12',0,'2025-03-10 19:46:48'),(6,'User3','$2y$10$qo4lV6NjcVZVTTYuGAUkvOo0wy68llTYAgBAw2z/rFG87W8AZMUKa','User',NULL,'2021-03-12',0,'2025-03-10 20:01:47'),(7,'User4','$2y$10$2hl5UdC28fSQpAModQfzo.oIITeyQCK2uVjGxHG.3NXcnzYuRsyH6','User',NULL,'2023-01-12',0,'2025-03-10 20:03:24'),(8,'Admin2','$2y$10$WiayNosiok55DJyXkWbz4OKGn8URURqeVEWgF2CGFBDpPBNHdplGC','Админ','photo_67cf4e5dc73b65.79923781.jpg','2013-12-30',1,'2025-03-10 20:41:01');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-04-22 13:50:18
