-- MySQL dump 10.13  Distrib 8.3.0, for macos14 (x86_64)
--
-- Host: 127.0.0.1    Database: aman_billing
-- ------------------------------------------------------
-- Server version	8.3.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */
;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */
;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */
;
/*!50503 SET NAMES utf8mb4 */
;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */
;
/*!40103 SET TIME_ZONE='+00:00' */
;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */
;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */
;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */
;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */
;

--
-- Table structure for table `areas`
--

DROP TABLE IF EXISTS `areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `areas` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `group_id` bigint unsigned DEFAULT NULL,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `area_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `areas_group_id_foreign` (`group_id`),
    CONSTRAINT `areas_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `mitras` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 13 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `areas`
--

LOCK TABLES `areas` WRITE;
/*!40000 ALTER TABLE `areas` DISABLE KEYS */
;
INSERT INTO
    `areas`
VALUES (
        1,
        NULL,
        'Tulungagung',
        '6',
        '2025-03-20 12:57:46',
        '2025-03-20 12:57:46'
    ),
    (
        8,
        NULL,
        'Malang Raya',
        '1',
        '2025-03-27 22:40:55',
        '2025-03-27 22:40:55'
    ),
    (
        9,
        NULL,
        'Trenggalek',
        '2',
        '2025-03-27 22:41:15',
        '2025-03-27 22:41:15'
    ),
    (
        10,
        NULL,
        'Sidoarjo',
        '3',
        '2025-03-27 22:41:26',
        '2025-03-27 22:41:26'
    ),
    (
        11,
        NULL,
        'Pasuruan',
        '4',
        '2025-03-27 22:41:39',
        '2025-03-27 22:41:39'
    ),
    (
        12,
        NULL,
        'Probolinggo',
        '5',
        '2025-03-27 22:41:48',
        '2025-03-27 22:41:48'
    );
/*!40000 ALTER TABLE `areas` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `cache` (
    `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
    `expiration` int NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */
;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `cache_locks` (
    `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `expiration` int NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */
;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `failed_jobs` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
    `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
    `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
    `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
    `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */
;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `global_settings`
--

DROP TABLE IF EXISTS `global_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `global_settings` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `group_id` bigint unsigned DEFAULT NULL,
    `isolir_mode` tinyint(1) NOT NULL DEFAULT '0',
    `xendit_balance` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
    `isolir_time` time DEFAULT '00:00:00',
    `invoice_generate_days` int DEFAULT '7',
    `notification_days` int DEFAULT '3',
    `isolir_after_exp` int DEFAULT '1',
    `due_date_pascabayar` int DEFAULT NULL,
    `footer` text COLLATE utf8mb4_unicode_ci,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `global_settings_group_id_foreign` (`group_id`),
    CONSTRAINT `global_settings_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `mitras` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 2 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `global_settings`
--

LOCK TABLES `global_settings` WRITE;
/*!40000 ALTER TABLE `global_settings` DISABLE KEYS */
;
INSERT INTO
    `global_settings`
VALUES (
        1,
        4,
        0,
        '0',
        '00:00:00',
        7,
        1,
        1,
        20,
        NULL,
        '2025-03-29 10:35:57',
        '2025-03-29 10:35:57'
    );
/*!40000 ALTER TABLE `global_settings` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `invoices` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `payer_id` int NOT NULL,
    `payment_method` enum(
        'bank_transfer',
        'cash',
        'payment_gateway'
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `pppoe_id` bigint unsigned DEFAULT NULL,
    `invoice_type` enum('C', 'P', 'H') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `invoice_date` date NOT NULL,
    `paid_at` date DEFAULT NULL,
    `due_date` date NOT NULL,
    `subs_period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `inv_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `payment_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `amount` int NOT NULL,
    `status` enum('paid', 'unpaid', 'pending') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `group_id` bigint unsigned DEFAULT NULL,
    `merged_from` bigint unsigned DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 11 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */
;
INSERT INTO
    `invoices`
VALUES (
        9,
        4,
        NULL,
        NULL,
        'P',
        '2025-03-30',
        NULL,
        '2025-03-31',
        'superadmin',
        'INVP6-2503001',
        'https://checkout-staging.xendit.co/web/67e8f6ccad5d4dad3a51088a',
        14400000,
        'unpaid',
        NULL,
        NULL,
        '2025-03-30 00:46:21',
        '2025-03-30 00:46:21'
    ),
    (
        10,
        5,
        'bank_transfer',
        NULL,
        'C',
        '2025-04-01',
        '2025-04-02',
        '2025-04-30',
        'superadmin',
        'INVC6-2504001',
        'https://checkout-staging.xendit.co/web/67ec74e5ea20c45dbcae68e4',
        100,
        'paid',
        NULL,
        NULL,
        '2025-04-01 16:21:10',
        '2025-04-02 04:33:59'
    );
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `job_batches` (
    `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `total_jobs` int NOT NULL,
    `pending_jobs` int NOT NULL,
    `failed_jobs` int NOT NULL,
    `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
    `options` mediumtext COLLATE utf8mb4_unicode_ci,
    `cancelled_at` int DEFAULT NULL,
    `created_at` int NOT NULL,
    `finished_at` int DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */
;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `jobs` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
    `attempts` tinyint unsigned NOT NULL,
    `reserved_at` int unsigned DEFAULT NULL,
    `available_at` int unsigned NOT NULL,
    `created_at` int unsigned NOT NULL,
    PRIMARY KEY (`id`),
    KEY `jobs_queue_index` (`queue`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */
;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `members` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `group_id` bigint unsigned NOT NULL,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `phone_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `nik` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `address` text COLLATE utf8mb4_unicode_ci,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `members_group_id_foreign` (`group_id`),
    CONSTRAINT `members_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `mitras` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 130 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `members`
--

LOCK TABLES `members` WRITE;
/*!40000 ALTER TABLE `members` DISABLE KEYS */
;
/*!40000 ALTER TABLE `members` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `migrations` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `batch` int NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 124 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */
;
INSERT INTO
    `migrations`
VALUES (
        19,
        '2025_02_23_020519_create_areas_table',
        1
    ),
    (
        29,
        '2025_02_19_001921_create_mitras_table',
        2
    ),
    (
        94,
        '0001_01_01_000001_create_cache_table',
        3
    ),
    (
        95,
        '0001_01_01_000002_create_jobs_table',
        3
    ),
    (
        96,
        '2025_02_23_232535_create_mitras_table',
        3
    ),
    (
        97,
        '2025_02_23_232536_create_users_table',
        3
    ),
    (
        98,
        '2025_02_24_061909_create_areas_table',
        3
    ),
    (
        99,
        '2025_02_24_070549_create_optical_dists_table',
        3
    ),
    (
        100,
        '2025_03_04_114019_create_profiles_table',
        3
    ),
    (
        101,
        '2025_03_05_011111_create_members_table',
        3
    ),
    (
        103,
        '2025_03_06_101656_create_vpn_users_table',
        3
    ),
    (
        104,
        '2025_03_06_211138_create_nas_table',
        3
    ),
    (
        105,
        '2025_03_09_041111_create_pppoe_accounts_table',
        3
    ),
    (
        106,
        '2025_03_20_100211_create_billing_mitras_table',
        3
    ),
    (
        107,
        '2025_03_20_205244_create_whatsapp_messages_table',
        4
    ),
    (
        111,
        '2025_03_23_112917_create_invoices_table',
        5
    ),
    (
        115,
        '2025_03_25_072805_create_payouts_table',
        6
    ),
    (
        116,
        '2025_03_28_053232_update_areas',
        7
    ),
    (
        117,
        '2025_03_28_163437_update_mitras',
        8
    ),
    (
        122,
        '2025_03_05_131813_create_global_settings_table',
        9
    ),
    (
        123,
        '2025_03_29_171505_add_column_to_mitras_table',
        10
    );
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `mitras`
--

DROP TABLE IF EXISTS `mitras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `mitras` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `segmentasi` enum('C', 'P') COLLATE utf8mb4_unicode_ci NOT NULL,
    `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `phone_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
    `nik` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `ktpImg` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `area_id` bigint unsigned NOT NULL,
    `pop_id` bigint unsigned NOT NULL,
    `capacity` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `price` int NOT NULL,
    `ppn` tinyint(1) NOT NULL DEFAULT '0',
    `bhpuso` tinyint(1) NOT NULL DEFAULT '0',
    `kso` tinyint(1) NOT NULL DEFAULT '0',
    `transmitter` enum(
        'Wireless',
        'Fiber Optic',
        'SFP',
        'SFP+'
    ) COLLATE utf8mb4_unicode_ci NOT NULL,
    `active_date` date NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    `nomor_pelanggan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 6 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `mitras`
--

LOCK TABLES `mitras` WRITE;
/*!40000 ALTER TABLE `mitras` DISABLE KEYS */
;
INSERT INTO
    `mitras`
VALUES (
        4,
        'Amanu Nur Abdillah',
        'P',
        'amanunur401@gmail.com',
        '085156589587',
        'Ds.Kendal, Kec. Gondang RT. 02 RW. 03',
        '3504034805680002',
        '',
        1,
        1,
        '800',
        14400000,
        0,
        0,
        0,
        'SFP',
        '2025-03-01',
        '2025-03-29 10:35:57',
        '2025-03-29 10:35:57',
        'AMAN-P6001'
    ),
    (
        5,
        'Amanu Nur Abdillah 1',
        'C',
        'amanunur4@gmail.com',
        '085156589123',
        'Ds.Kendal, Kec. Gondang RT. 02 RW. 03',
        '3504092403030001',
        '',
        1,
        1,
        '1',
        100,
        0,
        0,
        0,
        'Wireless',
        '2025-03-27',
        '2025-03-29 10:36:39',
        '2025-03-29 10:36:39',
        'AMAN-C6001'
    );
/*!40000 ALTER TABLE `mitras` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `nas`
--

DROP TABLE IF EXISTS `nas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `nas` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `group_id` bigint unsigned NOT NULL,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `ip_radius` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `ip_router` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `nas_group_id_foreign` (`group_id`),
    CONSTRAINT `nas_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `mitras` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 2 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `nas`
--

LOCK TABLES `nas` WRITE;
/*!40000 ALTER TABLE `nas` DISABLE KEYS */
;
/*!40000 ALTER TABLE `nas` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `optical_dists`
--

DROP TABLE IF EXISTS `optical_dists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `optical_dists` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `group_id` bigint unsigned DEFAULT NULL,
    `area_id` bigint unsigned NOT NULL,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `ip_public` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `capacity` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `device_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `lat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `lng` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `type` enum('ODP', 'ODC', 'POP') COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `optical_dists_group_id_foreign` (`group_id`),
    KEY `optical_dists_area_id_foreign` (`area_id`),
    CONSTRAINT `optical_dists_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `optical_dists_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `mitras` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 4 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `optical_dists`
--

LOCK TABLES `optical_dists` WRITE;
/*!40000 ALTER TABLE `optical_dists` DISABLE KEYS */
;
INSERT INTO
    `optical_dists`
VALUES (
        1,
        NULL,
        1,
        'SW-BANDUNG-02',
        '157.15.63.81',
        '12',
        'CCR 2004 12s+ 2xs',
        '-8.34725340301548',
        '112.00870513916016',
        'POP',
        '2025-03-20 13:01:29',
        '2025-03-24 15:49:31'
    );
/*!40000 ALTER TABLE `optical_dists` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `password_reset_tokens` (
    `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`email`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */
;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `payouts`
--

DROP TABLE IF EXISTS `payouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `payouts` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `payout_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `external_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `amount` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `status` enum(
        'PENDING',
        'SUCCESS',
        'CANCEL'
    ) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
    `group_id` bigint unsigned DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `payouts`
--

LOCK TABLES `payouts` WRITE;
/*!40000 ALTER TABLE `payouts` DISABLE KEYS */
;
/*!40000 ALTER TABLE `payouts` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `pppoe_accounts`
--

DROP TABLE IF EXISTS `pppoe_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `pppoe_accounts` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `member_id` bigint unsigned DEFAULT NULL,
    `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `internet_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `billing_active` tinyint(1) NOT NULL DEFAULT '1',
    `isolir` tinyint(1) NOT NULL DEFAULT '0',
    `billing_type` enum('prabayar', 'pascabayar') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `billing_period` enum(
        'fixed_date',
        'renewal',
        'billing_cycle'
    ) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `active_date` date DEFAULT NULL,
    `ppn` decimal(5, 2) DEFAULT NULL,
    `discount` int DEFAULT NULL,
    `group_id` bigint unsigned NOT NULL,
    `profile_id` bigint unsigned NOT NULL,
    `area_id` bigint unsigned DEFAULT NULL,
    `optical_id` bigint unsigned DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `pppoe_accounts_group_id_foreign` (`group_id`),
    CONSTRAINT `pppoe_accounts_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `mitras` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 11 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `pppoe_accounts`
--

LOCK TABLES `pppoe_accounts` WRITE;
/*!40000 ALTER TABLE `pppoe_accounts` DISABLE KEYS */
;
/*!40000 ALTER TABLE `pppoe_accounts` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `profiles`
--

DROP TABLE IF EXISTS `profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `profiles` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `group_id` bigint unsigned NOT NULL,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `price` int NOT NULL,
    `rate_rx` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
    `rate_tx` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
    `burst_rx` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
    `burst_tx` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
    `threshold_rx` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
    `threshold_tx` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
    `time_rx` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
    `time_tx` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
    `priority` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `profiles_group_id_foreign` (`group_id`),
    CONSTRAINT `profiles_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `mitras` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 3 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `profiles`
--

LOCK TABLES `profiles` WRITE;
/*!40000 ALTER TABLE `profiles` DISABLE KEYS */
;
/*!40000 ALTER TABLE `profiles` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `sessions` (
    `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `user_id` bigint unsigned DEFAULT NULL,
    `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `user_agent` text COLLATE utf8mb4_unicode_ci,
    `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
    `last_activity` int NOT NULL,
    PRIMARY KEY (`id`),
    KEY `sessions_user_id_index` (`user_id`),
    KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */
;
INSERT INTO
    `sessions`
VALUES (
        '0BtnpISU3so92Vu3MgdQgqOeXr0uen6CaYH36xWM',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZVVDM3EwZWQ4SlA3b0dHSzByMHlpVFB1aUw2b2tJc3BjQVhLeXI2diI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743549317
    ),
    (
        '0UCFLfLIb0OgQMIVUGv8nOqU08TllM80Raic8hTZ',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUFNSMVZ6a1ZSVkE2M3VVRWg1WU02WmVMeHdpQzNnOXR5WkJZWXFWMSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548214
    ),
    (
        '353cmPVTaicnekJ7885lS2zzek86VBYAVqRsowAE',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWEhaeXRFN2JRUWo0UEdRWGxNOXZIWFBhVTA3b3dUSzRRRDdDYkg0QSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743549134
    ),
    (
        '7J7sXwhFrhOXvgJMB7ue3LOR1LaXyN9oCl2brjzC',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiamdDQkpiNE5tWEhCclF6M3RYVFJTc0RBcU9vTlg0TmRWQUc5aTZ3NSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548793
    ),
    (
        'cN5ZO3QM1bPImYA8xIik1Vb6vKQKgWVlYRQC0SwZ',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiU1piSk12R3pTaW9aY2l3MlZXY3B1M0lGQnV2eVViRHZEdjlYOE9SQSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548556
    ),
    (
        'DjIfDnb1PlTRPMVeeXCr6Q2Zb8qTeRw2Z0Cb9eIx',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiY2lYWjR6SGo0dlNvS3dsbUpQekZ1NTJ3S29qbmpqeXdoUlhLZngzQiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743549274
    ),
    (
        'EoufkME0v7zr57Y95DAVizLKCWawXj95dLUwEPDc',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiajA5blVsZFR6QVRqbk01ZlNiVGdDTHQ4cE5CTjRzREYyVWtsbWMwNyI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548237
    ),
    (
        'FFCwXjE5sBGvlSIvDH0qCvGuhfQqn9lwU5yxaL2b',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiYWhXY3ZVUWt2UEdhNnpUV0xuT01LelBHbVR1cEEwQzRGVjIyMTVwZiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743549123
    ),
    (
        'FTjcDBjm0uM8khvsvw1J1i4IS58SMXsFvZ1cSYZ0',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMXBmNGRFMG1KenlzR0NkOERJZllHS3ZHQjVHUldaemEySUNmOHM0UCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743549639
    ),
    (
        'gdImtXG0SfMavq7lsLj7heOVMfe65DpIeFyeq1c5',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoidUNKV1NUMDBlMlJCWlpxYmthclcxN0NPaXRURHNCbHdISEpmS3V3cyI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548669
    ),
    (
        'HiV4Xc2ECeepN7YnLzJS6h2GtqVC40tsh0gyVaQ2',
        1,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiTGlEcm5OcjJPbGZMb1cwZFdzNHQwdTcwWXZ1REl6R1R1SGF5QnhLZiI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjMzOiJodHRwOi8vYW1hbi1iaWxpbmcudGVzdC9wcHAvcHBwb2UiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO30=',
        1743636641
    ),
    (
        'IOMOBRv9Z7Rx6po2yKzpL8Xs2JBb4VZIaasZd6Fi',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMXFZSTJBbFR5dmdpN1NoMkt2eDFOd0xUcVNrNTFkZTM0aXI2U3g3cSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743547736
    ),
    (
        'KAGHrWcBxWvMR71bkWTCFyQWy6MOPjC3xojSLFKK',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiYVVwU3phZE9aNWdLbUk3NXVVN0JwTWU0dVNuYWJseVowZkxiazdkWiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548454
    ),
    (
        'kcHAqguUiy0XlCHyPwJhhEpkS4PT6s1c7B0gSYim',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoia1FjMzh6M2pvMzNYaWkxRHlOU3F2WkQ3OUJUb2hkeVJyaWNqS0hGMCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548087
    ),
    (
        'L2tfI7XEc29dAq1lGHcz6tIJfhDn2HVJQdOzOwsB',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiRmVHNzlDN2xyS212N2dteDBpeGVUdU96TmY0SHIzeExHbDduTThvaiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548248
    ),
    (
        'LDCjq7WVLQiBFnhNJ5f346xBdGJTO0KAkA2HELhX',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNVVIZkZ4SUZCcnRMd2h1VzJkZHhFZ2Y1ZUFRdkJONWhVd0dTazByZCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548805
    ),
    (
        'lKnripDuyJ5GSGbTtzONqFld08G4IQ7y1s0W6FHT',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTUI0eDZRS0hCdHVsbFEwQTBMbzV4RWdUMFd3UjgwMzV6TGFuTU5yQiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548841
    ),
    (
        'lUSz4J8pSSFfa5pbxlXpUORrF9XvoetG9LzmAlh9',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSVI4SW9BM0pBVXR6cURDRWl6akNrUDNVbXRldG01QVdtN0VBajNWaCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743549023
    ),
    (
        'mvKoqJoBj5nAAN1kFnfKxixu73WkRVdO2osAUv4y',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoicmtHNkhINEttbVJtVW5wWlo2RFJHYUxkQThBT3lMUXFGNE54NmJXbiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548636
    ),
    (
        'NdpCZpgA1ENILt60pYWkL2cbMaoap6ObtEJU4Kq1',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTmhpclM2bEpjREIxbWRWOWdxajZPMmplQkdUWmVhTlBScHJnVlo3UyI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548135
    ),
    (
        'NKos99xgIZrbvojs40vLCtrv8jJsmnkBPx5M1vbv',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVzdXZ3YxNWxkQ1V4bm9KOGlGeXlvOEFRSWtLeXFqZFM1aU81czY2UCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548004
    ),
    (
        'PCfpP4oaOCAyGPJwli3gLXPj1il9d9E0r28Cw8AV',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTo0OntzOjY6Il90b2tlbiI7czo0MDoidHRkazJHb0ZWNHE5Q3dTWWE3VHlSNVpjUlNPc3V6N1VvdmhySXFKSCI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czozMzoiaHR0cDovL2FtYW4tYmlsaW5nLnRlc3QvcHBwL3BwcG9lIjt9czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L3BwcC9wcHBvZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',
        1743636573
    ),
    (
        'pPK0TLsXKvnbQZVNH2tGwL29HzphP2QhakGbtTGz',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoieDBRcHZQNUxkY3FxMmo1c2QxTzloOW5KektWdkdWQ1MzbGVtRFY1TiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548225
    ),
    (
        'rBUwfzxn2P8UfqibuSsfWzCAxFO6OSNv0W1kH269',
        1,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTo1OntzOjY6Il90b2tlbiI7czo0MDoicmpxSklOVkJGTVduUmxmNHRKYktCNDVKaUlPbE8xTDdPQ0FzR3VNbiI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjUzOiJodHRwOi8vYW1hbi1iaWxpbmcudGVzdC9iaWxsaW5nL2ludm9pY2UvSU5WQzYtMjUwNDAwMSI7fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7fQ==',
        1743593646
    ),
    (
        'UK69Ms8MMQHSMID89Pi3zcdPy3jFaxjxzAlaPPWV',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoielRDYkREcExaTGg5VUpVM0hCWHFHWDMzVHhxMWxFZWhVYjNMTU1iNyI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548742
    ),
    (
        'vPNp3yLgc6I2eirqJxwozJvTzvvLaqHeN3Fswuae',
        1,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTo0OntzOjY6Il90b2tlbiI7czo0MDoic3ZxUFVjejNBVVY3Zk1Xd29oWVUwSkttaFo1ZnlEeURrWXBYazBxSCI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzY6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvcGFpZCI7fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7fQ==',
        1743549807
    ),
    (
        'WWuqT4sWKrfdgvYIlzwqL2yrFCrgfEhM9epMetw4',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiOHNiWGNpemYycGN1clRuRlAxT1dKNWMzNjh1NlgzRHZtT0p3Qzh6UyI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743549092
    ),
    (
        'yckOl9UM2a0EsUiWMcdNvINtltFr3jBvXKXeFSnG',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiQ1k0OXA5UldxclJNd1dxdXFldzM4bkI0WUJnQnRuQ29UbnlhRFhRcCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548195
    ),
    (
        'yKVloL9xuG98G8LPTmbFCLgjVR4JX5o6JClBVGcB',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoic1psT1NaZWl4a2RORTNxMlNpVUNtRVlHMjBrQXk2NGRzTDF4dFlXSSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548575
    ),
    (
        'yWxUPwiHp1VwVDrLYHJ1gnlsfdRenUnzVPJPElJn',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNTZaaG1raFAzb1FHOTgxdDE4bUVUNVNNWkFrYlF3UHR2eE9aMWZBciI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743548997
    ),
    (
        'Z7L3vQbkb7W9D7Q5iORsayuBREmLFWRS8x3vZDxW',
        NULL,
        '127.0.0.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTEV2NFZBSkVpVnlQTDJQNFB1bHg2TDJSZlZ4Z3dRVXY5b3RPbkpwSSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly9hbWFuLWJpbGluZy50ZXN0L2JpbGxpbmcvaW52b2ljZS9JTlZQNi0yNTAzMDAxIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',
        1743549046
    );
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `users` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `role` enum(
        'superadmin',
        'mitra',
        'kasir',
        'teknisi',
        'admin'
    ) COLLATE utf8mb4_unicode_ci NOT NULL,
    `owner_type` enum('superadmin', 'mitra') COLLATE utf8mb4_unicode_ci NOT NULL,
    `group_id` bigint unsigned DEFAULT NULL,
    `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `phone_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_username_unique` (`username`),
    UNIQUE KEY `users_email_unique` (`email`),
    UNIQUE KEY `users_phone_number_unique` (`phone_number`),
    KEY `users_group_id_foreign` (`group_id`),
    CONSTRAINT `users_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `mitras` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 6 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */
;
INSERT INTO
    `users`
VALUES (
        1,
        'superadmin',
        'AMAN ADMINISTRATOR',
        'superadmin',
        'superadmin',
        NULL,
        'support@aman-isp.net',
        '628977624949',
        '$2y$12$U3lRQB5Pknx4Uij..lzyk.2U1hVu1gk0ynh6O1cgdJq8vc7xiFBoK',
        '5IOapTQ5EHD7s9mjoffobQnH3PN6K3Tvevfh616oezCyMrZuGu57rPs58E2o',
        '2025-03-20 12:34:20',
        '2025-03-20 12:45:04'
    ),
    (
        5,
        'amanunur401@gmail.com',
        'Amanu Nur Abdillah',
        'mitra',
        'superadmin',
        4,
        'amanunur401@gmail.com',
        '6285156589587',
        '$2y$12$SQCq9gSQfcWJjzXbmIL.4eRAeglFoyN2oKPcSueD8XJJTEUX5Bue6',
        NULL,
        '2025-03-29 10:35:57',
        '2025-03-29 10:35:57'
    );
/*!40000 ALTER TABLE `users` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `vpn_users`
--

DROP TABLE IF EXISTS `vpn_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `vpn_users` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `group_id` bigint unsigned NOT NULL,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `vpn_users_group_id_foreign` (`group_id`),
    CONSTRAINT `vpn_users_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `mitras` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 4 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `vpn_users`
--

LOCK TABLES `vpn_users` WRITE;
/*!40000 ALTER TABLE `vpn_users` DISABLE KEYS */
;
/*!40000 ALTER TABLE `vpn_users` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_messages`
--

DROP TABLE IF EXISTS `whatsapp_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!50503 SET character_set_client = utf8mb4 */
;
CREATE TABLE `whatsapp_messages` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `whatsapp_messages`
--

LOCK TABLES `whatsapp_messages` WRITE;
/*!40000 ALTER TABLE `whatsapp_messages` DISABLE KEYS */
;
/*!40000 ALTER TABLE `whatsapp_messages` ENABLE KEYS */
;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */
;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */
;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */
;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */
;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */
;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */
;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */
;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */
;

-- Dump completed on 2025-04-03  6:37:06