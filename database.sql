-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: innonce_outfits
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `campaigns`
--

DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title_en` varchar(255) NOT NULL,
  `title_sw` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `discount_type` varchar(50) DEFAULT NULL,
  `discount_value` decimal(12,2) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaigns`
--

LOCK TABLES `campaigns` WRITE;
/*!40000 ALTER TABLE `campaigns` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaigns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart`
--

DROP TABLE IF EXISTS `cart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `size` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_en` varchar(255) NOT NULL,
  `name_sw` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `description_sw` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Dresses','Magauni','dresses',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(2,'Evening Dresses','Magauni ya Sherehe','evening-dresses',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(3,'Abaya','Abaya','abaya',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(4,'T-Shirts','Fulana','t-shirts',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(5,'Blouses','Blauzi','blouses',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(6,'Body Suits','Body Suits','body-suits',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(7,'Tops','Tops','tops',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(8,'Jeans','Jeans','jeans',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(9,'Flare Jeans','Jeans Flare','flare-jeans',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(10,'Cargo Pants','Suruali za Cargo','cargo-pants',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(11,'Skirts','Sketi','skirts',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(12,'Official Trousers','Suruali za Ofisi','official-trousers',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(13,'Bwanga','Bwanga','bwanga',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(14,'Body Shapers','Body Shaper','body-shapers',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(15,'Two Pieces','Two Pieces','two-pieces',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(16,'Jumpsuits','Jamsuit','jumpsuits',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(17,'Sweaters','Masweta','sweaters',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(18,'Blazers','Blaza','blazers',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(19,'Jeans Jackets','Makoti ya Jeans','jeans-jackets',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(20,'Ponchos','Poncho','ponchos',NULL,NULL,NULL,NULL,1,'2026-06-16 14:12:07','2026-06-16 14:12:07'),(21,'Belts','Mikanda','belts',NULL,NULL,NULL,NULL,1,'2026-07-13 12:02:54','2026-07-13 12:02:54'),(22,'Three pieces','Pisi tatu','three-pieces',NULL,NULL,NULL,NULL,1,'2026-07-14 12:29:45','2026-07-14 12:29:45'),(23,'Coat','koti','coat',NULL,NULL,NULL,NULL,1,'2026-07-14 16:41:27','2026-07-14 16:41:27');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `reply` text DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts`
--

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `type` enum('percentage','fixed') DEFAULT 'percentage',
  `value` decimal(12,2) NOT NULL,
  `min_purchase` decimal(12,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loyalty_points`
--

DROP TABLE IF EXISTS `loyalty_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loyalty_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `points` int(11) DEFAULT 0,
  `action` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `loyalty_points_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loyalty_points`
--

LOCK TABLES `loyalty_points` WRITE;
/*!40000 ALTER TABLE `loyalty_points` DISABLE KEYS */;
/*!40000 ALTER TABLE `loyalty_points` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsletters`
--

DROP TABLE IF EXISTS `newsletters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `newsletters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsletters`
--

LOCK TABLES `newsletters` WRITE;
/*!40000 ALTER TABLE `newsletters` DISABLE KEYS */;
INSERT INTO `newsletters` VALUES (1,'mahuvechristian@gmail.com','2026-06-23 07:41:34');
/*!40000 ALTER TABLE `newsletters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `type` varchar(50) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (19,1,'Order #INV-2026-B77C8826','Payment received. Order is now processing.','order',1,'2026-07-15 16:56:37'),(23,22,'Order #INV-2026-B1E5EF26','Payment received. Order is now processing.','order',1,'2026-07-21 16:01:46'),(24,23,'Order #INV-2026-F5D37BB4','Payment received. Order is now processing.','order',1,'2026-07-21 18:20:49'),(25,23,'Order #INV-2026-F5D37BB4','Assigned to worker: JENIPHA MAHUVE','order',1,'2026-07-21 18:21:53'),(26,22,'Order #INV-2026-6EFD8DEF','Payment received. Order is now processing.','order',0,'2026-07-21 18:46:04'),(27,24,'New Order #INV-2026-6EFD8DEF','You have been assigned order #INV-2026-6EFD8DEF.','order',1,'2026-07-21 18:48:08'),(28,24,'Order #INV-2026-6EFD8DEF','Assigned to worker: JENIPHA MAHUVE','order',1,'2026-07-21 18:48:08'),(29,22,'Order #INV-2026-6EFD8DEF','Assigned to worker: JENIPHA MAHUVE','order',0,'2026-07-21 18:48:08'),(30,22,'Order #INV-2026-7004E380','Payment received. Order is now processing.','order',0,'2026-07-21 19:17:08'),(31,25,'New Order #INV-2026-7004E380','You have been assigned order #INV-2026-7004E380.','order',1,'2026-07-21 19:17:37'),(32,25,'Order #INV-2026-7004E380','Assigned to worker: DIANA MBILINYI','order',1,'2026-07-21 19:17:37'),(33,22,'Order #INV-2026-7004E380','Assigned to worker: DIANA MBILINYI','order',0,'2026-07-21 19:17:37');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `size` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (8,9,33,5,30000.00,150000.00,NULL,NULL,'2026-07-15 15:07:10'),(9,10,33,1,30000.00,30000.00,NULL,NULL,'2026-07-15 15:16:50'),(10,11,29,3,50000.00,150000.00,NULL,NULL,'2026-07-15 16:37:55'),(11,12,31,2,55000.00,110000.00,NULL,NULL,'2026-07-15 16:50:55'),(12,13,35,1,22000.00,22000.00,NULL,NULL,'2026-07-15 16:56:21'),(13,14,9,1,60000.00,60000.00,NULL,NULL,'2026-07-15 16:59:40'),(14,15,7,1,50000.00,50000.00,NULL,NULL,'2026-07-15 17:00:46'),(18,19,36,5,22000.00,110000.00,NULL,NULL,'2026-07-21 10:05:02'),(19,20,33,3,30000.00,90000.00,NULL,NULL,'2026-07-21 10:06:05'),(20,21,42,1,75000.00,75000.00,NULL,NULL,'2026-07-21 16:01:22'),(21,22,42,1,75000.00,75000.00,NULL,NULL,'2026-07-21 16:05:34'),(22,23,27,1,35000.00,35000.00,NULL,NULL,'2026-07-21 18:20:30'),(23,24,42,2,75000.00,150000.00,'S','Black','2026-07-21 18:45:45'),(24,25,2,1,12000.00,12000.00,NULL,NULL,'2026-07-21 19:16:40');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_trackings`
--

DROP TABLE IF EXISTS `order_trackings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_trackings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `status` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `order_trackings_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_trackings`
--

LOCK TABLES `order_trackings` WRITE;
/*!40000 ALTER TABLE `order_trackings` DISABLE KEYS */;
INSERT INTO `order_trackings` VALUES (12,9,'pending','Order placed successfully.','2026-07-15 15:07:10'),(13,10,'pending','Order placed successfully.','2026-07-15 15:16:50'),(14,10,'payment_received','Payment received via PawaPay.','2026-07-15 15:22:00'),(15,11,'pending','Order placed successfully.','2026-07-15 16:37:55'),(16,11,'payment_received','Payment received via card (Stakaba).','2026-07-15 16:47:26'),(17,12,'pending','Order placed successfully.','2026-07-15 16:50:55'),(18,12,'payment_received','Payment received via card (Stakaba).','2026-07-15 16:55:44'),(19,13,'pending','Order placed successfully.','2026-07-15 16:56:21'),(20,13,'payment_received','Payment received via PawaPay.','2026-07-15 16:56:37'),(21,14,'pending','Order placed successfully.','2026-07-15 16:59:40'),(22,15,'pending','Order placed successfully.','2026-07-15 17:00:46'),(29,19,'pending','Order placed successfully.','2026-07-21 10:05:02'),(30,20,'pending','Order placed successfully.','2026-07-21 10:06:05'),(31,21,'pending','Order placed successfully.','2026-07-21 16:01:22'),(32,21,'payment_received','Payment received via PawaPay.','2026-07-21 16:01:46'),(33,22,'pending','Order placed successfully.','2026-07-21 16:05:34'),(34,23,'pending','Order placed successfully.','2026-07-21 18:20:30'),(35,23,'payment_received','Payment received via PawaPay.','2026-07-21 18:20:49'),(36,24,'pending','Order placed successfully.','2026-07-21 18:45:45'),(37,24,'payment_received','Payment received via PawaPay.','2026-07-21 18:46:04'),(38,25,'pending','Order placed successfully.','2026-07-21 19:16:40'),(39,25,'payment_received','Payment received via PawaPay.','2026-07-21 19:17:08');
/*!40000 ALTER TABLE `order_trackings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `order_number` varchar(50) NOT NULL,
  `status` enum('pending','confirmed','processing','packed','shipped','delivered','cancelled') DEFAULT 'pending',
  `subtotal` decimal(12,2) NOT NULL,
  `tax` decimal(12,2) DEFAULT 0.00,
  `shipping` decimal(12,2) DEFAULT 0.00,
  `delivery_method` enum('delivery','pickup') NOT NULL DEFAULT 'delivery',
  `worker_id` int(11) DEFAULT NULL,
  `discount` decimal(12,2) DEFAULT 0.00,
  `volume_discount` decimal(12,2) DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('unpaid','paid','failed','refunded') DEFAULT 'unpaid',
  `currency` varchar(3) DEFAULT 'TZS',
  `shipping_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shipping_address`)),
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `billing_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`billing_address`)),
  `notes` text DEFAULT NULL,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `user_id` (`user_id`),
  KEY `idx_worker` (`worker_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (9,2,'JENIPHA MAHUVE','255616846079','INV-2026-DE9B6EA6','pending',150000.00,0.00,0.00,'pickup',NULL,0.00,3000.00,147000.00,'pawapay','unpaid','TZS','{\"country\":\"Tanzania\",\"region\":\"Dar es Salaam\",\"city\":\"DARES SALAAM\",\"street\":\"Dar es Salaam St\"}',NULL,NULL,NULL,NULL,NULL,0.00,'2026-07-15 15:07:10','2026-07-15 15:07:10'),(10,2,'JENIPHA MAHUVE','255616846079','INV-2026-0B8F61C6','processing',30000.00,0.00,0.00,'pickup',NULL,0.00,0.00,30000.00,'pawapay','paid','TZS','{\"country\":\"Tanzania\",\"region\":\"Dar es Salaam\",\"city\":\"DARES SALAAM\",\"street\":\"Dar es Salaam St\"}',NULL,NULL,NULL,NULL,NULL,0.00,'2026-07-15 15:16:50','2026-07-15 15:22:00'),(11,1,'CHRISS','+255747743367','INV-2026-7D3C1FA3','processing',150000.00,0.00,0.00,'pickup',NULL,0.00,3000.00,147000.00,'stakaba','paid','TZS','{\"country\":\"Tanzania\",\"region\":\"Dar es Salaam\",\"city\":\"DARES SALAAM\",\"street\":\"Dar es Salaam St\"}',NULL,NULL,NULL,NULL,NULL,0.00,'2026-07-15 16:37:55','2026-07-15 16:47:26'),(12,1,'CHRISS','+255712345678','INV-2026-2BAEF557','processing',110000.00,0.00,0.00,'pickup',NULL,0.00,0.00,110000.00,'stakaba','paid','TZS','{\"country\":\"Tanzania\",\"region\":\"Dar es Salaam\",\"city\":\"DARES SALAAM\",\"street\":\"Dar es Salaam St\"}',NULL,NULL,NULL,NULL,NULL,0.00,'2026-07-15 16:50:55','2026-07-15 16:55:44'),(13,1,'CHRISS','+255712345678','INV-2026-B77C8826','processing',22000.00,0.00,0.00,'pickup',NULL,0.00,0.00,22000.00,'pawapay','paid','TZS','{\"country\":\"Tanzania\",\"region\":\"Dar es Salaam\",\"city\":\"DARES SALAAM\",\"street\":\"Dar es Salaam St\"}',NULL,NULL,NULL,NULL,NULL,0.00,'2026-07-15 16:56:21','2026-07-15 16:56:37'),(14,1,'CHRISS','+255712345678','INV-2026-38E0468D','pending',60000.00,0.00,0.00,'pickup',NULL,0.00,0.00,60000.00,'stakaba','unpaid','TZS','{\"country\":\"Tanzania\",\"region\":\"Dar es Salaam\",\"city\":\"DARES SALAAM\",\"street\":\"Dar es Salaam St\"}',NULL,NULL,NULL,NULL,NULL,0.00,'2026-07-15 16:59:40','2026-07-15 16:59:40'),(15,1,'CHRISS','+255712345678','INV-2026-2B9E5DB6','pending',50000.00,0.00,0.00,'pickup',NULL,0.00,0.00,50000.00,'stakaba','unpaid','TZS','{\"country\":\"Tanzania\",\"region\":\"Dar es Salaam\",\"city\":\"DARES SALAAM\",\"street\":\"Dar es Salaam St\"}',NULL,NULL,NULL,NULL,NULL,0.00,'2026-07-15 17:00:46','2026-07-15 17:00:46'),(19,2,'JENIPHA MAHUVE','255616846079','INV-2026-0494687F','pending',110000.00,0.00,0.00,'pickup',NULL,0.00,2200.00,107800.00,'pawapay','unpaid','TZS','{\"country\":\"Tanzania\",\"region\":\"Dar es Salaam\",\"city\":\"DARES SALAAM\",\"street\":\"Dar es Salaam St\"}',NULL,NULL,NULL,NULL,NULL,0.00,'2026-07-21 10:05:02','2026-07-21 10:05:02'),(20,2,'JENIPHA MAHUVE','255616846079','INV-2026-B6EFE24B','pending',90000.00,0.00,0.00,'pickup',NULL,0.00,1800.00,88200.00,'pawapay','unpaid','TZS','{\"country\":\"Tanzania\",\"region\":\"Dar es Salaam\",\"city\":\"DARES SALAAM\",\"street\":\"Dar es Salaam St\"}',NULL,NULL,NULL,NULL,NULL,0.00,'2026-07-21 10:06:05','2026-07-21 10:06:05'),(21,22,'Christian Mahuve','255747743365','INV-2026-B1E5EF26','processing',75000.00,0.00,0.00,'pickup',NULL,0.00,0.00,75000.00,'pawapay','paid','TZS','{\"country\":\"Tanzania\",\"region\":\"Dar es Salaam\",\"city\":\"DAR ES SALAAM\",\"street\":\"DAR ES SALAAM\"}',NULL,NULL,NULL,NULL,NULL,0.00,'2026-07-21 16:01:22','2026-07-21 16:01:46'),(22,23,'Christian Ruhynucy','255616846079','INV-2026-1DBCA85D','pending',75000.00,0.00,30000.00,'delivery',NULL,0.00,0.00,105000.00,'stakaba','unpaid','TZS','{\"country\":\"Tanzania\",\"region\":\"Dar es Salaam\",\"city\":\"Dar es salaam\",\"street\":\"Gomz nature\"}',NULL,NULL,NULL,NULL,NULL,0.00,'2026-07-21 16:05:34','2026-07-21 16:05:34'),(23,23,'Christian Ruhynucy','255616846079','INV-2026-F5D37BB4','processing',35000.00,0.00,14000.00,'delivery',24,0.00,0.00,49000.00,'pawapay','paid','TZS','{\"country\":\"Tanzania\",\"region\":\"Dar es Salaam\",\"city\":\"Dar es salaam\",\"street\":\"Gomz nature\"}',-6.88907000,39.16085000,NULL,NULL,NULL,0.00,'2026-07-21 18:20:30','2026-07-21 18:21:53'),(24,22,'Christian Mahuve','255747743365','INV-2026-6EFD8DEF','processing',150000.00,0.00,30000.00,'delivery',24,0.00,0.00,180000.00,'pawapay','paid','TZS','{\"country\":\"Tanzania\",\"region\":\"Dar es Salaam\",\"city\":\"DAR ES SALAAM\",\"street\":\"DAR ES SALAAM\"}',-6.88907000,39.16087500,NULL,NULL,NULL,0.00,'2026-07-21 18:45:45','2026-07-21 18:48:08'),(25,22,'Christian Mahuve','255747743365','INV-2026-7004E380','processing',12000.00,0.00,4800.00,'delivery',25,0.00,0.00,16800.00,'pawapay','paid','TZS','{\"country\":\"Tanzania\",\"region\":\"Other\",\"city\":\"DODOMA\",\"street\":\"DODOMA\"}',-6.16200000,35.75200000,NULL,NULL,NULL,0.00,'2026-07-21 19:16:40','2026-07-21 19:17:37');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title_en` varchar(255) NOT NULL,
  `title_sw` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content_en` text DEFAULT NULL,
  `content_sw` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `otp` varchar(6) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES ('mahuvec@gmail.com','bc745d310aa76b5a1bc17b24b49c6ce6e0fc8f870621c5a03161a839fe5decd9','715066','2026-07-09 13:12:32');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_transactions`
--

DROP TABLE IF EXISTS `payment_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `reference` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'TZS',
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('pending','success','failed','cancelled') DEFAULT 'pending',
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `callback_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`callback_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `payment_transactions_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_transactions`
--

LOCK TABLES `payment_transactions` WRITE;
/*!40000 ALTER TABLE `payment_transactions` DISABLE KEYS */;
INSERT INTO `payment_transactions` VALUES (11,9,2,'pawapay',NULL,'PAY-20260715-3765B845F7',147000.00,'TZS','255616846079','failed',NULL,'[]',NULL,'2026-07-15 15:07:10','2026-07-15 15:07:21'),(12,9,2,'pawapay',NULL,'PAY-20260715-9947761E8B',147000.00,'TZS','255616846079','failed',NULL,'null',NULL,'2026-07-15 15:12:14','2026-07-15 15:12:17'),(13,9,2,'pawapay',NULL,'PAY-20260715-084E6C6947',147000.00,'TZS','255616846079','failed',NULL,'null',NULL,'2026-07-15 15:15:47','2026-07-15 15:15:49'),(14,10,2,'pawapay','8aaf663b-ee22-486e-818d-4539451f31e9','PAY-20260715-7991952E05',30000.00,'TZS','255616846079','success',NULL,'{\"checkoutId\":\"fdbd09de-322e-4bd0-b925-026f674efe52\",\"status\":\"ACCEPTED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/W8Yv4ylmaMGQVzVDYU\",\"created\":\"2026-07-15T15:16:50Z\",\"expiresAt\":\"2026-07-15T15:31:50Z\",\"checkoutCode\":\"W8Yv4ylmaMGQVzVDYU\"}','{\"checkoutId\":\"fdbd09de-322e-4bd0-b925-026f674efe52\",\"status\":\"COMPLETED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/W8Yv4ylmaMGQVzVDYU\",\"returnUrl\":\"http:\\/\\/localhost:8080\\/innonce-outfits\\/payment\\/pawapay_return.php\",\"returnMethod\":\"INSTANT\",\"countries\":[],\"expiresAfter\":15,\"amounts\":[{\"country\":\"TZA\",\"currency\":\"TZS\",\"amount\":\"30000\"}],\"clientReferenceId\":\"INV-2026-0B8F61C6\",\"created\":\"2026-07-15T15:16:50Z\",\"deposit\":{\"depositId\":\"8aaf663b-ee22-486e-818d-4539451f31e9\",\"status\":\"COMPLETED\",\"created\":\"2026-07-15T15:17:32Z\",\"providerTransactionId\":\"9e931f1b-d882-4f29-9667-740f146a56bd\",\"amount\":\"30000.00\",\"currency\":\"TZS\",\"country\":\"TZA\",\"payer\":{\"type\":\"MMO\",\"accountDetails\":{\"phoneNumber\":\"255616846079\",\"provider\":\"HALOTEL_TZA\"}},\"customerMessage\":\"INNOCE OUTFITS\",\"metadata\":{\"orderNumber\":\"INV-2026-0B8F61C6\",\"orderId\":\"10\"}},\"depositsHistory\":[{\"depositId\":\"8aaf663b-ee22-486e-818d-4539451f31e9\",\"status\":\"COMPLETED\",\"created\":\"2026-07-15T15:17:32Z\",\"providerTransactionId\":\"9e931f1b-d882-4f29-9667-740f146a56bd\",\"amount\":\"30000.00\",\"currency\":\"TZS\",\"country\":\"TZA\",\"payer\":{\"type\":\"MMO\",\"accountDetails\":{\"phoneNumber\":\"255616846079\",\"provider\":\"HALOTEL_TZA\"}},\"customerMessage\":\"INNOCE OUTFITS\",\"metadata\":{\"orderNumber\":\"INV-2026-0B8F61C6\",\"orderId\":\"10\"}}],\"metadata\":{\"orderNumber\":\"INV-2026-0B8F61C6\",\"orderId\":\"10\"},\"reason\":{\"en\":\"INNOCE OUTFITS\"},\"checkoutCode\":\"W8Yv4ylmaMGQVzVDYU\"}','2026-07-15 15:16:50','2026-07-15 15:22:00'),(15,11,1,'stakaba','STKCNFO2V7KPI04JP','PAY-20260715-C022F266E9',147000.00,'TZS','+255747743367','success',NULL,'{\"checkoutUrl\":\"https:\\/\\/app.stakaba.com\\/payments\\/return?reference=STKCNFO2V7KPI04JP&status=success&mode=sandbox\",\"internalReference\":\"STKCNFO2V7KPI04JP\"}','{\"note\":\"Manually verified from Stakaba dashboard\"}','2026-07-15 16:37:55','2026-07-15 16:47:26'),(16,12,1,'stakaba','STKCFVBJ2NR3PT1Y6','PAY-20260715-48EFC5E140',110000.00,'TZS','+255712345678','success',NULL,'{\"checkoutUrl\":\"https:\\/\\/app.stakaba.com\\/payments\\/return?reference=STKCFVBJ2NR3PT1Y6&status=success&mode=sandbox\",\"internalReference\":\"STKCFVBJ2NR3PT1Y6\"}','{\"note\":\"Verified via Stakaba sandbox\"}','2026-07-15 16:50:55','2026-07-15 16:55:44'),(17,13,1,'pawapay','54e444a1-29f6-4031-8dea-a5d138292a23','PAY-20260715-609652B98A',22000.00,'TZS','+255712345678','success',NULL,'{\"checkoutId\":\"f8cdd6fc-9e67-485a-8fa0-bbc96a989a42\",\"status\":\"ACCEPTED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/irr5xUxKp7iyIdOhgg\",\"created\":\"2026-07-15T16:56:22Z\",\"expiresAt\":\"2026-07-15T17:11:22Z\",\"checkoutCode\":\"irr5xUxKp7iyIdOhgg\"}','{\"checkoutId\":\"f8cdd6fc-9e67-485a-8fa0-bbc96a989a42\",\"status\":\"COMPLETED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/irr5xUxKp7iyIdOhgg\",\"returnUrl\":\"http:\\/\\/localhost:8080\\/innonce-outfits\\/payment\\/pawapay_return.php\",\"returnMethod\":\"INSTANT\",\"countries\":[],\"expiresAfter\":15,\"amounts\":[{\"country\":\"TZA\",\"currency\":\"TZS\",\"amount\":\"22000\"}],\"clientReferenceId\":\"INV-2026-B77C8826\",\"created\":\"2026-07-15T16:56:22Z\",\"deposit\":{\"depositId\":\"54e444a1-29f6-4031-8dea-a5d138292a23\",\"status\":\"COMPLETED\",\"created\":\"2026-07-15T16:56:35Z\",\"providerTransactionId\":\"airtel.money.id.kZlJAy6ssrkWXEXNhuyM\",\"amount\":\"22000.00\",\"currency\":\"TZS\",\"country\":\"TZA\",\"payer\":{\"type\":\"MMO\",\"accountDetails\":{\"phoneNumber\":\"255712345678\",\"provider\":\"AIRTEL_TZA\"}},\"customerMessage\":\"INNOCE OUTFITS\",\"metadata\":{\"orderNumber\":\"INV-2026-B77C8826\",\"orderId\":\"13\"}},\"depositsHistory\":[{\"depositId\":\"54e444a1-29f6-4031-8dea-a5d138292a23\",\"status\":\"COMPLETED\",\"created\":\"2026-07-15T16:56:35Z\",\"providerTransactionId\":\"airtel.money.id.kZlJAy6ssrkWXEXNhuyM\",\"amount\":\"22000.00\",\"currency\":\"TZS\",\"country\":\"TZA\",\"payer\":{\"type\":\"MMO\",\"accountDetails\":{\"phoneNumber\":\"255712345678\",\"provider\":\"AIRTEL_TZA\"}},\"customerMessage\":\"INNOCE OUTFITS\",\"metadata\":{\"orderNumber\":\"INV-2026-B77C8826\",\"orderId\":\"13\"}}],\"metadata\":{\"orderNumber\":\"INV-2026-B77C8826\",\"orderId\":\"13\"},\"reason\":{\"en\":\"INNOCE OUTFITS\"},\"checkoutCode\":\"irr5xUxKp7iyIdOhgg\"}','2026-07-15 16:56:21','2026-07-15 16:56:37'),(18,14,1,'stakaba','STKC61VV18SCTOUZJ','PAY-20260715-229F993FEC',60000.00,'TZS','+255712345678','pending',NULL,'{\"checkoutUrl\":\"https:\\/\\/app.stakaba.com\\/payments\\/return?reference=STKC61VV18SCTOUZJ&status=success&mode=sandbox\",\"internalReference\":\"STKC61VV18SCTOUZJ\"}',NULL,'2026-07-15 16:59:40','2026-07-15 16:59:41'),(19,15,1,'stakaba',NULL,'PAY-20260715-5630FF533D',50000.00,'TZS','+255712345678','failed',NULL,'[]',NULL,'2026-07-15 17:00:46','2026-07-15 17:01:16'),(20,15,1,'stakaba',NULL,'PAY-20260715-4A6F0C03A7',50000.00,'TZS','+255712345678','failed',NULL,'null',NULL,'2026-07-15 17:01:26','2026-07-15 17:01:39'),(21,15,1,'stakaba',NULL,'PAY-20260715-A6F9776FEF',50000.00,'TZS','+255712345678','failed',NULL,'null',NULL,'2026-07-15 17:01:47','2026-07-15 17:01:49'),(22,15,1,'stakaba',NULL,'PAY-20260715-EE90BFBEF6',50000.00,'TZS','+255712345678','failed',NULL,'null',NULL,'2026-07-15 17:01:59','2026-07-15 17:02:01'),(23,15,1,'stakaba','STKCOV2PM6KV7D5MG','PAY-20260715-769ACC487D',50000.00,'TZS','+255712345678','pending',NULL,'{\"checkoutUrl\":\"https:\\/\\/app.stakaba.com\\/payments\\/return?reference=STKCOV2PM6KV7D5MG&status=success&mode=sandbox\",\"internalReference\":\"STKCOV2PM6KV7D5MG\"}',NULL,'2026-07-15 17:11:56','2026-07-15 17:11:57'),(27,19,2,'pawapay','b297d2b2-4070-47be-8b4c-945b526f327d','PAY-20260721-9304BAA93E',107800.00,'TZS','255616846079','pending',NULL,'{\"checkoutId\":\"b297d2b2-4070-47be-8b4c-945b526f327d\",\"status\":\"ACCEPTED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/n9xlEUTbZSWXGNnl2f\",\"created\":\"2026-07-21T10:05:10Z\",\"expiresAt\":\"2026-07-21T10:20:10Z\",\"checkoutCode\":\"n9xlEUTbZSWXGNnl2f\"}',NULL,'2026-07-21 10:05:02','2026-07-21 10:05:14'),(28,20,2,'pawapay','38082fb6-94ef-41e4-94eb-dffe9e55366a','PAY-20260721-97BE7BDE02',88200.00,'TZS','255616846079','pending',NULL,'{\"checkoutId\":\"38082fb6-94ef-41e4-94eb-dffe9e55366a\",\"status\":\"ACCEPTED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/w1yHsvtuj1RF47osa3\",\"created\":\"2026-07-21T10:06:07Z\",\"expiresAt\":\"2026-07-21T10:21:07Z\",\"checkoutCode\":\"w1yHsvtuj1RF47osa3\"}',NULL,'2026-07-21 10:06:05','2026-07-21 10:06:10'),(29,20,2,'pawapay','93d6eddf-9fee-4b4a-9c7d-95f312522af9','PAY-20260721-0CC69F941E',88200.00,'TZS','255616846079','pending',NULL,'{\"checkoutId\":\"93d6eddf-9fee-4b4a-9c7d-95f312522af9\",\"status\":\"ACCEPTED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/mhlWwR5B6izBDYRMAT\",\"created\":\"2026-07-21T10:07:10Z\",\"expiresAt\":\"2026-07-21T10:22:10Z\",\"checkoutCode\":\"mhlWwR5B6izBDYRMAT\"}',NULL,'2026-07-21 10:07:12','2026-07-21 10:07:14'),(30,21,22,'pawapay','c9ea4517-c5a7-4a6c-873b-879db04f84d6','PAY-20260721-C3D147738D',75000.00,'TZS','255747743365','success',NULL,'{\"checkoutId\":\"b154f41d-fae4-4c0f-bc84-6bf9178e5ac8\",\"status\":\"ACCEPTED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/wQfoX0DTExtBJ2QCaL\",\"created\":\"2026-07-21T16:01:22Z\",\"expiresAt\":\"2026-07-21T16:16:22Z\",\"checkoutCode\":\"wQfoX0DTExtBJ2QCaL\"}','{\"checkoutId\":\"b154f41d-fae4-4c0f-bc84-6bf9178e5ac8\",\"status\":\"COMPLETED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/wQfoX0DTExtBJ2QCaL\",\"returnUrl\":\"http:\\/\\/localhost:8080\\/innonce-outfits\\/payment\\/pawapay_return.php\",\"returnMethod\":\"INSTANT\",\"countries\":[],\"expiresAfter\":15,\"amounts\":[{\"country\":\"TZA\",\"currency\":\"TZS\",\"amount\":\"75000\"}],\"clientReferenceId\":\"INV-2026-B1E5EF26\",\"created\":\"2026-07-21T16:01:22Z\",\"deposit\":{\"depositId\":\"c9ea4517-c5a7-4a6c-873b-879db04f84d6\",\"status\":\"COMPLETED\",\"created\":\"2026-07-21T16:01:40Z\",\"providerTransactionId\":\"3fbf2cef-ee46-45bc-88c9-77b0d890dce9\",\"amount\":\"75000.00\",\"currency\":\"TZS\",\"country\":\"TZA\",\"payer\":{\"type\":\"MMO\",\"accountDetails\":{\"phoneNumber\":\"255747743365\",\"provider\":\"VODACOM_TZA\"}},\"customerMessage\":\"INNOCE OUTFITS\",\"metadata\":{\"orderNumber\":\"INV-2026-B1E5EF26\",\"orderId\":\"21\"}},\"depositsHistory\":[{\"depositId\":\"c9ea4517-c5a7-4a6c-873b-879db04f84d6\",\"status\":\"COMPLETED\",\"created\":\"2026-07-21T16:01:40Z\",\"providerTransactionId\":\"3fbf2cef-ee46-45bc-88c9-77b0d890dce9\",\"amount\":\"75000.00\",\"currency\":\"TZS\",\"country\":\"TZA\",\"payer\":{\"type\":\"MMO\",\"accountDetails\":{\"phoneNumber\":\"255747743365\",\"provider\":\"VODACOM_TZA\"}},\"customerMessage\":\"INNOCE OUTFITS\",\"metadata\":{\"orderNumber\":\"INV-2026-B1E5EF26\",\"orderId\":\"21\"}}],\"metadata\":{\"orderNumber\":\"INV-2026-B1E5EF26\",\"orderId\":\"21\"},\"reason\":{\"en\":\"INNOCE OUTFITS\"},\"checkoutCode\":\"wQfoX0DTExtBJ2QCaL\"}','2026-07-21 16:01:22','2026-07-21 16:01:46'),(31,22,23,'stakaba','STKC1EKZMX3I0S3L0','PAY-20260721-9CB6C40998',105000.00,'TZS','255616846079','pending',NULL,'{\"checkoutUrl\":\"https:\\/\\/app.stakaba.com\\/payments\\/return?reference=STKC1EKZMX3I0S3L0&status=success&mode=sandbox\",\"internalReference\":\"STKC1EKZMX3I0S3L0\"}',NULL,'2026-07-21 16:05:34','2026-07-21 16:05:35'),(32,23,23,'pawapay','52ef0d58-a40b-4229-a4f0-c38542d771a6','PAY-20260721-D3A73DF332',49000.00,'TZS','255616846079','success',NULL,'{\"checkoutId\":\"4c29b3d0-49f5-495e-8162-9fc5d7dbd392\",\"status\":\"ACCEPTED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/L95kaQKVqcIprLcXHG\",\"created\":\"2026-07-21T18:20:30Z\",\"expiresAt\":\"2026-07-21T18:35:30Z\",\"checkoutCode\":\"L95kaQKVqcIprLcXHG\"}','{\"checkoutId\":\"4c29b3d0-49f5-495e-8162-9fc5d7dbd392\",\"status\":\"COMPLETED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/L95kaQKVqcIprLcXHG\",\"returnUrl\":\"http:\\/\\/localhost:8080\\/innonce-outfits\\/payment\\/pawapay_return.php\",\"returnMethod\":\"INSTANT\",\"countries\":[],\"expiresAfter\":15,\"amounts\":[{\"country\":\"TZA\",\"currency\":\"TZS\",\"amount\":\"49000\"}],\"clientReferenceId\":\"INV-2026-F5D37BB4\",\"created\":\"2026-07-21T18:20:30Z\",\"deposit\":{\"depositId\":\"52ef0d58-a40b-4229-a4f0-c38542d771a6\",\"status\":\"COMPLETED\",\"created\":\"2026-07-21T18:20:45Z\",\"providerTransactionId\":\"214c46aa-fd42-4de5-a5c6-776fb9a17c77\",\"amount\":\"49000.00\",\"currency\":\"TZS\",\"country\":\"TZA\",\"payer\":{\"type\":\"MMO\",\"accountDetails\":{\"phoneNumber\":\"2550616846078\",\"provider\":\"HALOTEL_TZA\"}},\"customerMessage\":\"INNOCE OUTFITS\",\"metadata\":{\"orderNumber\":\"INV-2026-F5D37BB4\",\"orderId\":\"23\"}},\"depositsHistory\":[{\"depositId\":\"52ef0d58-a40b-4229-a4f0-c38542d771a6\",\"status\":\"COMPLETED\",\"created\":\"2026-07-21T18:20:45Z\",\"providerTransactionId\":\"214c46aa-fd42-4de5-a5c6-776fb9a17c77\",\"amount\":\"49000.00\",\"currency\":\"TZS\",\"country\":\"TZA\",\"payer\":{\"type\":\"MMO\",\"accountDetails\":{\"phoneNumber\":\"2550616846078\",\"provider\":\"HALOTEL_TZA\"}},\"customerMessage\":\"INNOCE OUTFITS\",\"metadata\":{\"orderNumber\":\"INV-2026-F5D37BB4\",\"orderId\":\"23\"}}],\"metadata\":{\"orderNumber\":\"INV-2026-F5D37BB4\",\"orderId\":\"23\"},\"reason\":{\"en\":\"INNOCE OUTFITS\"},\"checkoutCode\":\"L95kaQKVqcIprLcXHG\"}','2026-07-21 18:20:30','2026-07-21 18:20:49'),(33,24,22,'pawapay','9b2ab614-5a36-459d-866d-135eedf5c010','PAY-20260721-8634AEFDCE',180000.00,'TZS','255747743365','success',NULL,'{\"checkoutId\":\"9c9d1971-7ee6-4634-9d39-63dd24e4baa0\",\"status\":\"ACCEPTED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/6Az1mA6To7uzVUAWFG\",\"created\":\"2026-07-21T18:45:45Z\",\"expiresAt\":\"2026-07-21T19:00:45Z\",\"checkoutCode\":\"6Az1mA6To7uzVUAWFG\"}','{\"checkoutId\":\"9c9d1971-7ee6-4634-9d39-63dd24e4baa0\",\"status\":\"COMPLETED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/6Az1mA6To7uzVUAWFG\",\"returnUrl\":\"http:\\/\\/localhost:8080\\/innonce-outfits\\/payment\\/pawapay_return.php\",\"returnMethod\":\"INSTANT\",\"countries\":[],\"expiresAfter\":15,\"amounts\":[{\"country\":\"TZA\",\"currency\":\"TZS\",\"amount\":\"180000\"}],\"clientReferenceId\":\"INV-2026-6EFD8DEF\",\"created\":\"2026-07-21T18:45:45Z\",\"deposit\":{\"depositId\":\"9b2ab614-5a36-459d-866d-135eedf5c010\",\"status\":\"COMPLETED\",\"created\":\"2026-07-21T18:46:01Z\",\"providerTransactionId\":\"7c2a8b74-c8c2-4f10-9f36-3e3a73a34470\",\"amount\":\"180000.00\",\"currency\":\"TZS\",\"country\":\"TZA\",\"payer\":{\"type\":\"MMO\",\"accountDetails\":{\"phoneNumber\":\"255747743367\",\"provider\":\"VODACOM_TZA\"}},\"customerMessage\":\"INNOCE OUTFITS\",\"metadata\":{\"orderNumber\":\"INV-2026-6EFD8DEF\",\"orderId\":\"24\"}},\"depositsHistory\":[{\"depositId\":\"9b2ab614-5a36-459d-866d-135eedf5c010\",\"status\":\"COMPLETED\",\"created\":\"2026-07-21T18:46:01Z\",\"providerTransactionId\":\"7c2a8b74-c8c2-4f10-9f36-3e3a73a34470\",\"amount\":\"180000.00\",\"currency\":\"TZS\",\"country\":\"TZA\",\"payer\":{\"type\":\"MMO\",\"accountDetails\":{\"phoneNumber\":\"255747743367\",\"provider\":\"VODACOM_TZA\"}},\"customerMessage\":\"INNOCE OUTFITS\",\"metadata\":{\"orderNumber\":\"INV-2026-6EFD8DEF\",\"orderId\":\"24\"}}],\"metadata\":{\"orderNumber\":\"INV-2026-6EFD8DEF\",\"orderId\":\"24\"},\"reason\":{\"en\":\"INNOCE OUTFITS\"},\"checkoutCode\":\"6Az1mA6To7uzVUAWFG\"}','2026-07-21 18:45:45','2026-07-21 18:46:04'),(34,25,22,'pawapay','f7c2e48f-0261-4433-9de9-b4acf70e0fc5','PAY-20260721-8F2EA6CD6B',16800.00,'TZS','255747743365','success',NULL,'{\"checkoutId\":\"62ef685d-280b-4028-8308-135ab4a6804d\",\"status\":\"ACCEPTED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/NIL6nRIhuPokbRnuR5\",\"created\":\"2026-07-21T19:16:40Z\",\"expiresAt\":\"2026-07-21T19:31:40Z\",\"checkoutCode\":\"NIL6nRIhuPokbRnuR5\"}','{\"checkoutId\":\"62ef685d-280b-4028-8308-135ab4a6804d\",\"status\":\"COMPLETED\",\"redirectUrl\":\"https:\\/\\/checkout.sandbox.pawapay.io\\/NIL6nRIhuPokbRnuR5\",\"returnUrl\":\"http:\\/\\/localhost:8080\\/innonce-outfits\\/payment\\/pawapay_return.php\",\"returnMethod\":\"INSTANT\",\"countries\":[],\"expiresAfter\":15,\"amounts\":[{\"country\":\"TZA\",\"currency\":\"TZS\",\"amount\":\"16800\"}],\"clientReferenceId\":\"INV-2026-7004E380\",\"created\":\"2026-07-21T19:16:40Z\",\"deposit\":{\"depositId\":\"f7c2e48f-0261-4433-9de9-b4acf70e0fc5\",\"status\":\"COMPLETED\",\"created\":\"2026-07-21T19:17:04Z\",\"providerTransactionId\":\"airtel.money.id.pFvkVYloowVljhP7yn1i\",\"amount\":\"16800.00\",\"currency\":\"TZS\",\"country\":\"TZA\",\"payer\":{\"type\":\"MMO\",\"accountDetails\":{\"phoneNumber\":\"2550756435785\",\"provider\":\"AIRTEL_TZA\"}},\"customerMessage\":\"INNOCE OUTFITS\",\"metadata\":{\"orderNumber\":\"INV-2026-7004E380\",\"orderId\":\"25\"}},\"depositsHistory\":[{\"depositId\":\"f7c2e48f-0261-4433-9de9-b4acf70e0fc5\",\"status\":\"COMPLETED\",\"created\":\"2026-07-21T19:17:04Z\",\"providerTransactionId\":\"airtel.money.id.pFvkVYloowVljhP7yn1i\",\"amount\":\"16800.00\",\"currency\":\"TZS\",\"country\":\"TZA\",\"payer\":{\"type\":\"MMO\",\"accountDetails\":{\"phoneNumber\":\"2550756435785\",\"provider\":\"AIRTEL_TZA\"}},\"customerMessage\":\"INNOCE OUTFITS\",\"metadata\":{\"orderNumber\":\"INV-2026-7004E380\",\"orderId\":\"25\"}}],\"metadata\":{\"orderNumber\":\"INV-2026-7004E380\",\"orderId\":\"25\"},\"reason\":{\"en\":\"INNOCE OUTFITS\"},\"checkoutCode\":\"NIL6nRIhuPokbRnuR5\"}','2026-07-21 19:16:40','2026-07-21 19:17:08');
/*!40000 ALTER TABLE `payment_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_hash` varchar(64) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
INSERT INTO `product_images` VALUES (2,2,'uploads/products/6a54d4cfcc654.jpeg',NULL,1,'2026-07-13 12:06:39'),(6,6,'uploads/products/6a5655df77592.jpg',NULL,1,'2026-07-14 15:29:35'),(7,7,'uploads/products/6a5657921daf6.jpg',NULL,1,'2026-07-14 15:36:50'),(8,8,'uploads/products/6a565af6ea807.jpg',NULL,1,'2026-07-14 15:51:18'),(9,9,'uploads/products/6a565b216d766.jpg',NULL,1,'2026-07-14 15:52:01'),(10,10,'uploads/products/6a565c2540ea3.jpg',NULL,1,'2026-07-14 15:56:21'),(11,13,'uploads/products/6a565cbac36b4.jpg',NULL,1,'2026-07-14 15:58:50'),(12,14,'uploads/products/6a565ccc5d4bd.jpg',NULL,1,'2026-07-14 15:59:08'),(13,11,'uploads/products/6a5660c5a6a5b.jpg',NULL,1,'2026-07-14 16:16:05'),(14,12,'uploads/products/6a5660faa5d7f.jpg',NULL,1,'2026-07-14 16:16:58'),(15,17,'uploads/products/6a56636574527.jpg',NULL,1,'2026-07-14 16:27:17'),(16,18,'uploads/products/6a56637ede912.jpg',NULL,1,'2026-07-14 16:27:42'),(17,21,'uploads/products/6a5663b0e964c.jpg',NULL,1,'2026-07-14 16:28:32'),(18,22,'uploads/products/6a5663c756032.jpg',NULL,1,'2026-07-14 16:28:55'),(19,23,'uploads/products/6a5663d78d3f3.jpg',NULL,1,'2026-07-14 16:29:11'),(20,27,'uploads/products/6a5664116893c.jpg',NULL,1,'2026-07-14 16:30:09'),(21,28,'uploads/products/6a5664205adf2.jpg',NULL,1,'2026-07-14 16:30:24'),(22,29,'uploads/products/6a56642d80cb4.jpg',NULL,1,'2026-07-14 16:30:37'),(23,31,'uploads/products/6a56646036fe3.jpg',NULL,1,'2026-07-14 16:31:28'),(24,32,'uploads/products/6a56647a6f8a7.jpg',NULL,1,'2026-07-14 16:31:54'),(25,33,'uploads/products/6a56648f74cfa.jpg',NULL,1,'2026-07-14 16:32:15'),(26,34,'uploads/products/6a56649c38941.jpg',NULL,1,'2026-07-14 16:32:28'),(27,35,'uploads/products/6a5664ab21cc7.jpg',NULL,1,'2026-07-14 16:32:43'),(28,36,'uploads/products/6a5664b798293.jpg',NULL,1,'2026-07-14 16:32:55'),(29,38,'uploads/products/6a5664f281dcd.jpg',NULL,1,'2026-07-14 16:33:54'),(32,42,'uploads/products/6a566a5a1ad0b.jpg','6214cb7771b9057f18ddc87d1e01d4dc',1,'2026-07-14 16:56:58');
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_reviews`
--

DROP TABLE IF EXISTS `product_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review` text DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_reviews`
--

LOCK TABLES `product_reviews` WRITE;
/*!40000 ALTER TABLE `product_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `name_en` varchar(255) NOT NULL,
  `name_sw` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description_en` text DEFAULT NULL,
  `description_sw` text DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `sku` varchar(255) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `discount_price` decimal(12,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `sizes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sizes`)),
  `colors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`colors`)),
  `gender` varchar(20) DEFAULT NULL,
  `video` varchar(255) DEFAULT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `new_arrival` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive','draft') DEFAULT 'active',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `sku` (`sku`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (2,21,'Belt','Mkanda','belt','A nice belt for your smart look','Mkanda mzuri kwa mwonekano nadhifu.','Bull','BEL-1783944399-0',15000.00,12000.00,50,NULL,'[\"Brown\",\"Black\"]',NULL,NULL,0,1,'active',NULL,'2026-07-13 12:06:39','2026-07-13 12:06:39'),(6,4,'Los Angeles Crop T-Shirt','T-Shirt ya Crop ya Los Angeles','los-angeles-crop-t-shirt','A vibrant lime green crop t-shirt with \'LOS ANGELES\' printed in black across the chest. Perfect for casual wear, this tee is designed to make a statement.','T-shirt ya lime green yenye maandishi ya \'LOS ANGELES\' kwa rangi nyeusi kwenye kifu. Inafaa kwa mavazi ya kawaida, t-shirt hii imeundwa ili kuonyesha mtindo.','LOS ANGELES','LOS-1784042975-0',25000.00,22000.00,10,'[\"S\",\"M\",\"L\",\"XL\"]','[\"Lime\",\"Green\",\"Yellow\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 15:29:35','2026-07-14 15:29:35'),(7,22,'Women\'s Tracksuit Set','Set ya Mavazi ya Wanawake','women-s-tracksuit-set','A stylish tracksuit set for women, consisting of a white jacket with red stripes and lettering, a white cropped t-shirt with \'FUEL\' print, and red sweatpants. Perfect for casual wear or athletic activities.','Set ya mavazi ya wanawake ya mtindo, inayojumuisha jaketi nyeupe na mistari nyekundu na herufi, t-shirt nyeupe iliyokatwa na uchapishaji wa \'FUEL\', na suruali nyekundu za jasho. Inafaa kwa mavazi ya kawaida au shughuli za riadha.','','WOM-1784043410-0',55000.00,50000.00,10,'[\"S\",\"M\",\"L\",\"XL\"]','[\"Red\",\"White\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 15:36:50','2026-07-14 17:05:58'),(8,1,'Grey Denim Dress','Gauni ya Denim Grey','grey-denim-dress','A stylish grey denim dress with short sleeves and a belted waist. Made from comfortable denim material, perfect for casual outings.','Gauni ya denim ya grey yenye mikono mifupi na kiunzi cha ukanda. Imetengenezwa kwa nyenzo za denim za starehe, zinafaa kwa matukio ya kawaida.','','GRE-1784044278-0',40000.00,35000.00,104,'[\"S\",\"M\",\"L\",\"XL\"]','[\"Grey\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 15:51:18','2026-07-14 17:05:58'),(9,2,'Pink Ruffled Evening Dress','Gauni la Jioni la Rangi ya Pinki na Ruffles','pink-ruffled-evening-dress','This stunning pink evening dress features a ruffled design and a V-neckline, perfect for making a statement at any formal event. The dress is knee-length and has a fitted silhouette, accentuating the wearer\'s curves.','Gauni hili la jioni la rangi ya pinki ni la kuvutia sana na lina muundo wa ruffles na shingo la V, linalofaa kuvaliwa kwenye matukio yoyote ya rasmi. Gauni ni la urefu wa goti na lina silhouette fupi, likisisitiza mikondo ya mwenzake.','','PIN-1784044321-0',60000.00,NULL,10,'[\"S\",\"M\",\"L\",\"XL\"]','[\"Pink\",\"Hot Pink\",\"Rose\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 15:52:01','2026-07-14 17:05:58'),(10,1,'Leopard Print Maxi Dress','Gauni ya Kubwa ya Chui','leopard-print-maxi-dress','This is a long-sleeved, floor-length maxi dress with a leopard print design. The dress appears to be made of a comfortable, flowing material and features a round neckline.','Hii ni gauni ndefu yenye mikono mirefu na muundo wa chui. Gauni inaonekana kuwa imetengenezwa kwa nyenzo laini na yenye mtiririko mzuri, na ina shingo la mviringo.','','LEO-1784044581-0',40000.00,35000.00,103,'[\"M\",\"L\",\"XL\"]','[\"Brown\",\"Black\",\"Tan\",\"Blue\",\"Royal Blue\",\"Sky Blue\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 15:56:21','2026-07-14 17:05:58'),(11,2,'Sequined Green Evening Dress','Gauni ya Jioni ya Kijani yenye Sequins','sequined-green-evening-dress','This long-sleeved evening dress features a shiny sequined pattern in green and gold tones. The dress is floor-length, fitted, and has a V-neckline. It\'s perfect for formal events and parties.','Gauni hii ya jioni yenye mikono mirefu ina muundo wa sequins unaoangaza katika rangi za kijani na dhahabu. Gauni ni ya urefu wa sakafu, inafaa, na ina mstari wa V kwenye shingo. Ni bora kwa matukio rasmi na vyama.','','SEQ-1784044586-0',80000.00,75000.00,105,'[\"S\",\"M\",\"L\",\"XL\"]','[\"Green\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 15:56:26','2026-07-14 17:05:58'),(12,2,'Sequined Evening Gown','Gauni ya Jioni ya Kuelewana','sequined-evening-gown','This stunning evening gown features a sequined design, off-the-shoulder neckline, and a high slit. Perfect for formal events and red-carpet appearances.','Gauni hii ya jioni ya kuvutia ina muundo wa kuelewana, shingo ya juu, na mgawanyiko wa juu. Inafaa kwa matukio rasmi na matukio ya red-carpet.','','SEQ-1784044602-0',80000.00,75000.00,10,'[\"S\",\"M\",\"L\",\"XL\"]','[\"Gold\",\"Brown\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 15:56:42','2026-07-14 17:05:58'),(13,1,'Pink Ruffled Midi Dress','Gauni ya Rangi ya Pinki yenye Ruffles','pink-ruffled-midi-dress','A stylish pink midi dress with ruffled sleeves and hem, perfect for casual or semi-formal occasions.','Gauni ya midi ya rangi ya pinki yenye sleeves na hem zenye ruffles, inafaa kwa matukio ya kawaida au ya nusu rasmi.','','PIN-1784044730-0',35000.00,32000.00,101,'[\"S\",\"M\",\"L\",\"XL\"]','[\"Pink\",\"Hot Pink\",\"Rose\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 15:58:50','2026-07-14 17:05:58'),(14,15,'Purple Top and Blue Skirt','Fulana ya Zambarau na Sketi ya Bluu','purple-top-and-blue-skirt','A stylish two-piece outfit consisting of a purple short-sleeved top with a decorative flower and a blue knee-length skirt with a buttoned front.','Ona ya kuvutia ya vipande viwili, kipochi cha rangi ya zambarau chenye mikono mifupi na ua la mapambo, na sketi ya rangi ya bluu ya goti yenye kofia za mbele.','','PUR-1784044748-0',40000.00,35000.00,10,'[\"S\",\"M\",\"L\"]','[\"Purple\",\"Blue\",\"Brown\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 15:59:08','2026-07-14 17:05:58'),(17,2,'Peach Lace Evening Gown','Gauni ya Jioni ya Peach Lace','peach-lace-evening-gown','This stunning evening gown is made of intricate peach lace, perfect for formal events. The long-sleeved dress features a V-neckline and a fitted silhouette, accentuating the wearer\'s curves.','Gauni hii ya jioni ni nzuri sana, imetengenezwa kwa lace ya peach, inafaa kwa matukio rasmi. Dress yenye mikono mirefu ina neckline ya V na silhouette fupi, kuzingatia mikondo ya mvaaji.','','PEA-1784046437-0',80000.00,75000.00,102,'[\"S\",\"M\",\"L\",\"XL\"]','[\"Peach\",\"Green\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:27:17','2026-07-14 17:05:58'),(18,15,'Pink Tracksuit','Seti ya Mavazi ya Riadha','pink-tracksuit','A pink tracksuit with black stripes and logo, made of comfortable material, perfect for casual wear.','Seti ya mavazi ya riadha ya rangi ya pinki yenye mistari nyeusi na nembo, imetengenezwa kwa nyenzo za kustarehesha, inafaa kwa kuvaa kawaida.','','PIN-1784046462-0',35000.00,32000.00,102,'[\"S\",\"M\",\"L\",\"XL\"]','[\"Pink\",\"Hot Pink\",\"Rose\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:27:42','2026-07-14 17:05:58'),(21,23,'Plaid Fur Collar Coat','Koti la Plaid na Kola ya Fur','plaid-fur-collar-coat','This long, plaid coat features a fur collar and button-front closure. It has a classic and stylish design, perfect for adding a touch of sophistication to any outfit.','Koti hili refu la plaid lina kola ya fur na vifungo mbele. Muundo wake ni wa classic na mtizamo, unafaa kuongeza mguso wa ubora kwa outfit yoyote.','','PLA-1784046512-0',80000.00,75000.00,104,'[\"M\",\"L\",\"XL\"]','[\"Black\",\"Green\",\"White\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:28:32','2026-07-14 17:05:58'),(22,1,'Denim Maxi Dress','Gauni ya Denim Ndefu','denim-maxi-dress','A stylish denim maxi dress with short sleeves and a button-front design. The dress features a classic collar and a belted waist for a flattering fit.','Gauni ya denim ndefu yenye mikono mifupi na kofia za kitanzi. Gauni hii ina kola ya kawaida na kiunzi cha mkufu kwa muonekano mzuri.','','DEN-1784046535-0',40000.00,35000.00,101,'[\"M\",\"L\",\"XL\"]','[\"Blue\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:28:55','2026-07-14 17:05:58'),(23,23,'Fur Collar Coat','Koti la Fur Collar','fur-collar-coat','This long coat features a fur collar and a wavy pattern. It is made of a warm and stylish material, perfect for cold weather.','Koti hili refu lina kola ya manyoya na muundo wa wavy. Limetengenezwa kwa nyenzo ya joto na mtindo, inayofaa kwa hali ya hewa baridi.','','FUR-1784046551-0',80000.00,75000.00,108,'[\"M\",\"L\",\"XL\"]','[\"Grey\",\"Brown\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:29:11','2026-07-14 17:05:58'),(27,1,'Rustic Button Down Midi Dress','Gauni ya Kufunga Mbele ya Kati','rustic-button-down-midi-dress','A midi dress with a button down front, short sleeves, and a wide belt. Made from a comfortable, textured fabric. Perfect for casual or semi-formal occasions.','Gauni ya urefu wa kati yenye kofani mbele, mikono mifupi, na ukanda mpana. Imetengenezwa kutoka kwa kitambaa cha kustarehesha. Inafaa kwa matukio ya kawaida au ya nusu rasmi.','','RUS-1784046609-0',45000.00,35000.00,101,'[\"M\",\"L\",\"XL\",\"XXL\"]','[\"Maroon\",\"Dark Red\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:30:09','2026-07-14 17:05:58'),(28,20,'Poncho','Poncho','poncho','This poncho is made of a soft, woven material with a black and white pattern. It features a V-neck design and white fringe along the bottom.','Poncho hii imetengenezwa kwa nyenzo laini, iliyosokotwa na muundo wa nyeusi na nyeupe. Ina muundo wa V-shingo na utepe mweupe chini.','','PON-1784046624-0',25000.00,20000.00,108,'[\"M\",\"L\",\"XL\"]','[\"Black\",\"White\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:30:24','2026-07-14 17:05:58'),(29,16,'Women\'s Navy Blue Tracksuit','Seti ya Mavazi ya Bluu ya Wanawake','women-s-navy-blue-tracksuit','This women\'s tracksuit is made of comfortable and stylish material, perfect for casual wear or athletic activities. The navy blue color with white stripes gives it a sleek and modern look.','Seti hii ya mavazi ya wanawake imetengenezwa kwa nyenzo za kustarehesha na za mtindo, zinafaa kwa mavazi ya kawaida au shughuli za riadha. Rangi ya bluu ya bahari yenye mistari nyeupe inampa mwonekano mzuri na wa kisasa.','','WOM-1784046637-0',55000.00,50000.00,101,'[\"S\",\"M\",\"L\",\"XL\"]','[\"Navy\",\"Blue\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:30:37','2026-07-14 17:05:58'),(31,1,'Royal Blue Bodycon Dress','Gauni ya Kifupi ya Rangi ya Bluu ya Kifalme','royal-blue-bodycon-dress','This royal blue bodycon dress features a round neckline, 3/4 puff sleeves, and a knee-length hem. The dress is adorned with floral embroidery on the chest and a decorative silver belt. Made from a stretchy material, it hugs the body for a sleek fit.','Gauni hii ya bluu ya kifalme ina neckline ya mviringo, sleeves 3/4 puff, na hem ya urefu wa goti. Gauni imepambwa kwa embroidery ya k胸 na ukanda wa fedha wa mapambo. Imetengenezwa kutoka kwa nyenzo za kunyoosha, inakumbatia mwili kwa mfitino.','','ROY-1784046688-0',60000.00,55000.00,105,'[\"S\",\"M\",\"L\"]','[\"Blue\",\"Royal Blue\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:31:28','2026-07-14 17:05:58'),(32,17,'Cable Knit Sweater','Koti la Knit ya Cable','cable-knit-sweater','A cream-colored cable knit sweater with a relaxed fit and V-neck design. Made from soft, high-quality material for a cozy feel.','Koti la knit la cable lenye rangi ya cream na muundo wa V-neck. Limeundwa kwa nyenzo laini na bora kwa hisia za kustarehesha.','','CAB-1784046714-0',35000.00,32000.00,101,'[\"S\",\"M\",\"L\",\"XL\"]','[\"Cream\",\"White\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:31:54','2026-07-14 17:05:58'),(33,15,'Adidas Tracksuit Set','Set ya Mavazi ya Adidas','adidas-tracksuit-set','A comfortable tracksuit set consisting of a t-shirt and matching pants, perfect for casual wear or athletic activities.','Set ya mavazi ya starehe, kipochi na suruali, bora kwa mavazi ya kawaida au shughuli za riadha.','Adidas','ADI-1784046735-0',35000.00,30000.00,101,'[\"XS\",\"S\",\"M\",\"L\",\"XL\",\"XXL\"]','[\"Brown\",\"Green\",\"Black\",\"White\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:32:15','2026-07-14 17:05:58'),(34,16,'Yellow Striped Jumpsuit','Jumpsuit ya Kipekee ya Koro','yellow-striped-jumpsuit-1','This stylish jumpsuit features a vibrant yellow and white striped pattern. It has a classic collar and short sleeves, with a relaxed fit and pockets for convenience.','Jumpsuit hii ya kuvutia ina muundo wa rangi ya njano na nyeupe. Ina kola ya kawaida na mikono mifupi, kwa muonekano tulivu na mifuko kwa urahisi.','','YEL-1784046748-0',45000.00,42000.00,10,'[\"M\",\"L\",\"XL\"]','[\"Yellow\",\"Gold\",\"Orange\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:32:28','2026-07-14 22:46:03'),(35,4,'Celine T-Shirt','T-Shati ya Celine','celine-t-shirt','A high-quality white T-shirt from the luxury brand Celine, made from comfortable materials and featuring a classic crew neck design with the brand\'s logo prominently displayed on the chest.','T-shati nyeupe ya ubora wa juu kutoka kwa chapa ya kifahari ya Celine, iliyotengenezwa kwa vifaa vizuri na kuonyesha muundo wa kawaida wa shingo ya crew na logo ya chapa iliyoonyeshwa kwenye kifua.','Celine','CEL-1784046763-0',25000.00,22000.00,156,'[\"XS\",\"S\",\"M\",\"L\",\"XL\"]','[\"White\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:32:43','2026-07-14 17:05:58'),(36,4,'Oversized Pink T-Shirt','Fulana Kubwa ya Rangi ya Pinki','oversized-pink-t-shirt','This is an oversized pink t-shirt with a graphic print on the front. It has a relaxed fit and is suitable for casual wear.','Hii ni fulana kubwa ya rangi ya pinki yenye uchapishaji wa picha mbele. Ina muonekano tulivu na yanafaa kwa kuvaa kawaida.','','OVE-1784046775-0',25000.00,22000.00,154,'[\"M\",\"L\",\"XL\"]','[\"Pink\",\"Hot Pink\",\"Rose\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:32:55','2026-07-14 17:05:58'),(38,15,'Striped Top and Skirt Set','Set ya Juu na Sketi zenye Mistari','striped-top-and-skirt-set','A stylish two-piece set consisting of a black and white striped top with a collar and short sleeves, paired with a knee-length black skirt with a decorative belt.','Seti ya mtindo wa vipande viwili inayojumuisha juu yenye mistari nyeusi na nyeupe yenye kola na mikono mifupi, iliyounganishwa na sketi nyeusi ya magoti yenye ukanda wa mapambo.','','STR-1784046834-0',40000.00,35000.00,109,'[\"M\",\"L\",\"XL\"]','[\"Black\",\"White\",\"Grey\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:33:54','2026-07-14 17:05:58'),(42,2,'Sequined Evening Dress','Gauni ya Sherehe ya Kujikidhi','sequined-evening-dress','This long-sleeved evening dress features a striking gold and black sequined pattern. The fitted silhouette and V-neckline create a sophisticated look, perfect for formal events.','Gauni hii ya mikono mirefu ya sherehe ina muundo wa kuvutia wa njano na nyeusi. Silhouette ya kutoshea na mstari wa V-neck huunda sura ya kisasa, bora kwa matukio rasmi.','','SEQ-1784048218-0',80000.00,75000.00,10,'[\"S\",\"M\",\"L\",\"XL\"]','[\"Gold\",\"Black\"]',NULL,NULL,0,1,'active',NULL,'2026-07-14 16:56:58','2026-07-14 17:05:58');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referrals`
--

DROP TABLE IF EXISTS `referrals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referrals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `referrer_id` int(11) NOT NULL,
  `referred_id` int(11) DEFAULT NULL,
  `code` varchar(50) NOT NULL,
  `status` enum('pending','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `referrer_id` (`referrer_id`),
  KEY `referred_id` (`referred_id`),
  CONSTRAINT `referrals_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referrals_ibfk_2` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referrals`
--

LOCK TABLES `referrals` WRITE;
/*!40000 ALTER TABLE `referrals` DISABLE KEYS */;
/*!40000 ALTER TABLE `referrals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'site_name','INNOCE OUTFITS','2026-06-16 14:12:07','2026-06-16 14:12:07'),(2,'currency','TZS','2026-06-16 14:12:07','2026-06-16 14:12:07'),(3,'tax_rate','0','2026-06-16 14:12:07','2026-07-13 12:15:53'),(4,'shipping_fee','5000','2026-06-16 14:12:07','2026-06-16 14:12:07'),(5,'free_shipping_min','850000','2026-06-16 14:12:07','2026-07-14 09:04:40'),(6,'default_payment','mpesa','2026-06-16 14:12:07','2026-06-16 14:12:07'),(7,'smtp_password','wykv xciq xqas ckmj','2026-07-09 12:11:08','2026-07-09 12:11:08'),(8,'smtp_username','mahuvechriss@gmail.com','2026-07-09 12:11:08','2026-07-09 12:11:08'),(9,'smtp_host','smtp.gmail.com','2026-07-09 12:11:08','2026-07-09 12:11:08'),(10,'smtp_encryption','tls','2026-07-09 12:11:08','2026-07-09 12:11:08'),(11,'smtp_port','587','2026-07-09 12:11:08','2026-07-09 12:11:08'),(12,'payment_api_key','','2026-07-10 10:26:47','2026-07-10 10:26:47'),(13,'payment_api_secret','','2026-07-10 10:26:47','2026-07-10 10:26:47'),(14,'beem_api_key','54581a6d9797dbc7','2026-07-10 10:26:47','2026-07-10 10:26:47'),(15,'beem_secret_key','ZmIwZjcxMTEyNGFhNmU2NTVhNjk3MGNiYTlkZGJmYjk3ZTc0OTM4YWI2OTk0MGY5ZWZkNDUwMjJiYTdiNWFlYw==','2026-07-10 10:26:47','2026-07-10 10:26:47'),(17,'azampay_client_id','805c33e4-c099-4037-bbfc-a90f4efc647f','2026-07-10 17:00:11','2026-07-10 17:00:11'),(18,'azampay_client_secret','ZCiY55qbYw2si/cnTYY3BQwYvnz1fn4vq8g3cs/GUc8BjsKX1IOkhHXEkmsGK4g0KtjWgBzdJVdhVhZ5uaN6DYJjKsgsjhD1a6Tvyq4T6mXscEhEE4Y82JYTIKR4Dg1BxdEEgN5eWj/cYni5HK6DLpoEnOrzMZWyyraL59InbmJtgFeQYM+BDsqcGf8EIwdZL0wETadTY0vQ0Q0mCOXGcfH2WXNp89l91dhATWZrs2Qv48Nj4ziegNqZ5WdnqWXshfuAs9jmkC9kQ7wqVAC+A4P3qnfCg9G8zDiiA/071GOtDBupYeyO+xszlgGFq6NLK5xr2hGSzClhJkuJaoxru5fo/V/BhaSwxnceTOuTV3+JCZJWBkHDqIgmjPH1PefjTcH7fVTrXAX/VgMn6GeGr+gTa8+q31iuxAnp4fZ7mhnHWl2reDXBOkN9eVIhqCOBMnkEdfD2gZlWbLEaCZv+u3WIKWs+UbD6LAjGvllH3iS1JFSFRa7A8ITIgLg4xZI8OAvc3Ddf/UoMu+IbVj7t6P2MXW9A/1c/M3nxn+RzpIeoKwpXUmzyCLEOCfc37GIlODnilrS1RSgK18jPhGK2KdHH1qySRH3MIs0dvDg+cL0Wlp31m7YwB5dVz5xaNALEVzXnM3HKfNSRpRX9+cE4sPZogVPI6QF0fycmkFCfPgs=','2026-07-10 17:00:11','2026-07-10 17:00:11'),(19,'azampay_environment','sandbox','2026-07-10 17:00:11','2026-07-10 17:00:11'),(23,'azampay_app_name','28e9be69-097f-46ab-88cb-9c8ed13d4136','2026-07-10 17:01:58','2026-07-10 17:01:58'),(24,'beem_payment_api_key','009a870af75d6717','2026-07-13 09:33:34','2026-07-13 09:33:34'),(25,'beem_payment_secret_key','MTBiOWU1NTEwNWJlN2I3MDY4MTliYTdlODNhODYwNzI1MzMzZTRkY2YzYjBjMDc5MjQxN2IzODE1NWE2NjQyNA==','2026-07-13 09:33:34','2026-07-13 09:33:34'),(26,'beem_reference_prefix','INNOCE','2026-07-13 09:33:34','2026-07-13 09:33:34'),(27,'beem_payment_environment','sandbox','2026-07-13 09:33:34','2026-07-13 09:33:34'),(28,'shipping_threshold','100000','2026-07-13 12:32:08','2026-07-13 12:32:08'),(29,'shipping_rate_default','40','2026-07-13 12:32:08','2026-07-13 12:32:52'),(30,'shipping_rate_reduced','20','2026-07-13 12:32:08','2026-07-13 12:32:52'),(31,'stakaba_api_key','sk_test_54eh0s-3H4z3lm4194wAIWBeupt0Cq8B','2026-07-15 16:26:55','2026-07-15 16:26:55');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `themes`
--

DROP TABLE IF EXISTS `themes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `themes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `preview_color` varchar(7) DEFAULT '#FF8C00',
  `is_active` tinyint(1) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0,
  `is_staging` tinyint(1) DEFAULT 0,
  `is_live` tinyint(1) DEFAULT 0,
  `auto_schedule` tinyint(1) DEFAULT 0,
  `scheduled_from` date DEFAULT NULL,
  `scheduled_to` date DEFAULT NULL,
  `css_variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`css_variables`)),
  `decorations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`decorations`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `themes`
--

LOCK TABLES `themes` WRITE;
/*!40000 ALTER TABLE `themes` DISABLE KEYS */;
INSERT INTO `themes` VALUES (1,'Default','default','Base fallback theme. Edit the Live theme to change what users see.','#ff8c00',0,1,0,0,0,NULL,NULL,'{}','{\"enabled\":true,\"snowflakes\":false,\"confetti\":false,\"particles\":\"snow\",\"particle_count\":50,\"badge_enabled\":false,\"badge_text_en\":\"\",\"badge_text_sw\":\"\",\"badge_icon\":\"\",\"quick_styles\":{\"bg_color\":\"#ffffff\",\"text_color\":\"#212529\",\"link_color\":\"#0011ff\",\"heading_color\":\"#1a1a2e\",\"btn_bg\":\"#ff8c00\",\"btn_text\":\"#ffffff\",\"navbar_bg\":\"#ff8c00\",\"card_bg\":\"#ffffff\",\"dark_bg_color\":\"#121212\",\"dark_text_color\":\"#f5f0eb\",\"dark_link_color\":\"#ff8c00\",\"dark_heading_color\":\"#f5f0eb\",\"dark_btn_bg\":\"#ff8c00\",\"dark_btn_text\":\"#ffffff\",\"dark_navbar_bg\":\"#121212\",\"dark_card_bg\":\"#1e1e1e\",\"border_radius\":\"\",\"font_size\":\"\"},\"custom_css\":\"\",\"custom_js\":\"\"}','2026-07-15 20:40:45','2026-07-17 14:41:41'),(9,'Testing','testing','Admin testing/preview theme. Changes here are only visible to you.','#523f28',0,0,1,0,0,NULL,NULL,'{\"--orange\":\"#1cb9d9\",\"--orange-light\":\"#bd0a72\",\"--orange-dark\":\"#cc7000\",\"--gold\":\"#ff8c00\",\"--gold-light\":\"#ffaa40\",\"--gold-dark\":\"#16e407\",\"--bg-body\":\"#fff5eb\",\"--bg-navbar\":\"#462907\",\"--bg-section-alt\":\"#e58b15\",\"--bg-card\":\"#ffffff\",\"--text-primary\":\"#121212\",\"--text-secondary\":\"#444444\",\"--text-on-orange\":\"#ffffff\",\"--border-light\":\"#e8d5c0\",\"--shadow-sm\":\"#000000\",\"--shadow-md\":\"#000000\",\"--shadow-lg\":\"#000000\",\"--shadow-gold\":\"#000000\",\"_dark\":{\"--orange\":\"#FF8C00\",\"--orange-light\":\"#FFAA40\",\"--orange-dark\":\"#CC7000\",\"--gold\":\"#FF8C00\",\"--gold-light\":\"#FFAA40\",\"--gold-dark\":\"#CC7000\",\"--bg-body\":\"#121212\",\"--bg-navbar\":\"#121212\",\"--bg-hero\":\"linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 50%, #0A0A0A 100%)\",\"--bg-section-alt\":\"#1A1A1A\",\"--bg-card\":\"#1E1E1E\",\"--text-primary\":\"#F5F0EB\",\"--text-secondary\":\"#BBB\",\"--text-on-orange\":\"#fff\",\"--border-light\":\"#333\",\"--shadow-sm\":\"0 2px 8px rgba(0,0,0,0.3)\",\"--shadow-md\":\"0 4px 20px rgba(0,0,0,0.4)\",\"--shadow-lg\":\"0 8px 32px rgba(0,0,0,0.5)\",\"--shadow-gold\":\"0 4px 20px rgba(255,140,0,0.25)\"}}','{\"enabled\":true,\"snowflakes\":false,\"confetti\":false,\"particles\":\"snow\",\"particle_count\":50,\"badge_enabled\":true,\"badge_text_en\":\"\",\"badge_text_sw\":\"\",\"badge_icon\":\"\",\"custom_css\":\"\",\"custom_js\":\"\"}','2026-07-16 17:55:00','2026-07-16 19:43:31'),(10,'Default (from Default)','default-from-default','Base fallback theme. Edit the Live theme to change what users see.','#f8727f',0,0,0,1,0,NULL,NULL,'{}','{\"enabled\":true,\"snowflakes\":false,\"confetti\":false,\"particles\":\"gold_dust\",\"particle_count\":20,\"badge_enabled\":false,\"badge_text_en\":\"\",\"badge_text_sw\":\"\",\"badge_icon\":\"\",\"quick_styles\":{\"bg_color\":\"#bb4444\",\"text_color\":\"#212529\",\"link_color\":\"#ff9d14\",\"heading_color\":\"#8af591\",\"btn_bg\":\"#ff8c00\",\"btn_text\":\"#ffffff\",\"navbar_bg\":\"#ca4949\",\"card_bg\":\"#eac17b\",\"dark_bg_color\":\"#121212\",\"dark_text_color\":\"#f5f0eb\",\"dark_link_color\":\"#ff9f1a\",\"dark_heading_color\":\"#f5f0eb\",\"dark_btn_bg\":\"#ff8c00\",\"dark_btn_text\":\"#ffffff\",\"dark_navbar_bg\":\"#121212\",\"dark_card_bg\":\"#1e1e1e\",\"border_radius\":\"\",\"font_size\":\"\"},\"custom_css\":\"\",\"custom_js\":\"\"}','2026-07-16 17:55:00','2026-07-19 16:59:41');
/*!40000 ALTER TABLE `themes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('customer','admin','worker') DEFAULT 'customer',
  `google_id` varchar(255) DEFAULT NULL,
  `language` varchar(5) DEFAULT 'en',
  `notify_email` tinyint(1) DEFAULT 1,
  `notify_sms` tinyint(1) DEFAULT 0,
  `notify_inapp` tinyint(1) DEFAULT 1,
  `profile_photo` varchar(255) DEFAULT NULL,
  `photo_align` varchar(50) DEFAULT 'center',
  `last_activity` datetime DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `idx_google_id` (`google_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'CHRISS','mahuvechristian@gmail.com','$2y$10$KKPLJlQ8KTB8uuZJtBvGzub0Pbn7wLY4hJlKf4s4WTqqgWTfl9/cO','+255712345678','admin',NULL,'en',1,1,1,'uploads/profiles/6a4fa16fc8864.jpg','50% 100%','2026-07-21 22:40:57',NULL,'2026-06-16 14:12:07','2026-07-21 19:40:57'),(2,'JENIPHA MAHUVE','mahuvec@gmail.com','$2y$10$pCjcYVS3/T4f51rulfrMhO32iCB9co4gaSCRq/Qls/Ytk/AaJ35ze','255616846079','customer',NULL,'en',1,1,1,'uploads/profiles/6a4f9b320ad3f.png','50% 0%','2026-07-21 13:10:40',NULL,'2026-07-09 12:25:40','2026-07-21 10:10:40'),(22,'Christian Mahuve','mahuvechriss@gmail.com','$2y$10$PKwcNnjw8ys3szDTpaNgveve5p.j3bG0yyjhJ25.Oxjjsl.y7yM9a','255747743365','customer','111152454490429833056','en',0,0,0,'https://lh3.googleusercontent.com/a/ACg8ocJJ8HFhB4g49RYMnLtV5zZKjxjO-JhAM4ouOQ4p2z-AcH229-c=s96-c','center','2026-07-21 22:40:40',NULL,'2026-07-21 15:52:16','2026-07-21 19:40:40'),(23,'Christian Ruhynucy','christianruphynucy@gmail.com','$2y$10$x2DXn2wQjczzGlPQ2O0Mu.Nb2EDIr3iNF1vvWhGIpj6Q/NBwzRIE6','255616846079','customer','111248993457250309589','en',1,1,1,'https://lh3.googleusercontent.com/a/ACg8ocJRIGp7GCw3FxomWINmbpv-xTRsRUhzqUfRntR2yv2CWilFKtY=s96-c','center','2026-07-21 22:14:05',NULL,'2026-07-21 16:04:50','2026-07-21 19:14:05'),(24,'JENIPHA MAHUVE','mahuvej@gmail.com','$2y$10$3.QNBRJJhbtrhpvi01ebB.AfRsfOzZh6z.rru3IughDpHQU4kvl7q','0747743361','worker',NULL,'en',1,0,1,NULL,'center','2026-07-21 22:40:44',NULL,'2026-07-21 18:11:17','2026-07-21 19:40:44'),(25,'DIANA MBILINYI','mahuved@gmail.com','$2y$10$uAvtSzUPD1P7uxKMYZp3teAFQGY3VPU6SPT40C2Uv01ZzyGtWYTO2','+255816675940','worker',NULL,'en',1,0,1,NULL,'center','2026-07-21 22:38:53',NULL,'2026-07-21 19:13:44','2026-07-21 19:38:53');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wishlists`
--

DROP TABLE IF EXISTS `wishlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wishlists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `wishlists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlists_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wishlists`
--

LOCK TABLES `wishlists` WRITE;
/*!40000 ALTER TABLE `wishlists` DISABLE KEYS */;
/*!40000 ALTER TABLE `wishlists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `worker_locations`
--

DROP TABLE IF EXISTS `worker_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `worker_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `worker_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_worker` (`worker_id`),
  CONSTRAINT `worker_locations_ibfk_1` FOREIGN KEY (`worker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `worker_locations`
--

LOCK TABLES `worker_locations` WRITE;
/*!40000 ALTER TABLE `worker_locations` DISABLE KEYS */;
INSERT INTO `worker_locations` VALUES (83,25,-6.88907000,39.16087500,'2026-07-21 19:38:38'),(88,24,-6.88916400,39.16083125,'2026-07-21 19:40:44');
/*!40000 ALTER TABLE `worker_locations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-21 22:41:07
