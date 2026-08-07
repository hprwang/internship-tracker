-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: internship_tracker1
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
-- Table structure for table `internships`
--

DROP TABLE IF EXISTS `internships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('applied','interview','accepted','ongoing','completed','rejected','withdrawn') DEFAULT 'applied',
  `stipend` decimal(10,2) DEFAULT 0.00,
  `work_mode` enum('remote','onsite','hybrid') DEFAULT 'onsite',
  `supervisor_name` varchar(150) DEFAULT NULL,
  `supervisor_email` varchar(150) DEFAULT NULL,
  `offer_letter_path` varchar(255) DEFAULT NULL,
  `resume_path` varchar(255) DEFAULT NULL,
  `cover_letter_path` varchar(255) DEFAULT NULL,
  `transcripts_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`,`end_date`),
  CONSTRAINT `internships_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `internships_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internships`
--

LOCK TABLES `internships` WRITE;
/*!40000 ALTER TABLE `internships` DISABLE KEYS */;
INSERT INTO `internships` VALUES (1,6,2,'random','','2026-06-19','2026-06-26','ongoing',20000.00,'hybrid','','',NULL,NULL,NULL,NULL,'','2026-06-09 04:42:08','2026-06-22 14:51:32'),(2,7,2,'AI','','2026-06-03','2026-06-07','completed',20000.00,'onsite','','',NULL,NULL,NULL,NULL,'','2026-06-09 06:37:27','2026-06-22 14:51:23'),(6,8,3,'sgfhrth','ddcx','2026-06-10','2026-06-30','accepted',2000.00,'','','',NULL,NULL,NULL,NULL,'','2026-06-18 13:24:56','2026-06-22 14:48:54'),(9,8,2,'reddg','njnjnhnb','2026-06-23','2026-06-30','applied',20000.00,'onsite','','',NULL,'','','','','2026-06-23 02:01:51','2026-06-23 02:01:51'),(10,8,1,'ujjjuj','hhkj,gu','2026-06-30','2026-07-01','interview',7657.00,'remote','','',NULL,'','','','','2026-06-23 04:12:22','2026-06-23 04:12:22');
/*!40000 ALTER TABLE `internships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `progress_logs`
--

DROP TABLE IF EXISTS `progress_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `progress_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_id` int(11) NOT NULL,
  `week_number` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `tasks_completed` text DEFAULT NULL,
  `skills_learned` text DEFAULT NULL,
  `challenges` text DEFAULT NULL,
  `hours_worked` decimal(5,2) DEFAULT 0.00,
  `rating` tinyint(4) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_internship` (`internship_id`),
  CONSTRAINT `progress_logs_ibfk_1` FOREIGN KEY (`internship_id`) REFERENCES `internships` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `progress_logs`
--

LOCK TABLES `progress_logs` WRITE;
/*!40000 ALTER TABLE `progress_logs` DISABLE KEYS */;
INSERT INTO `progress_logs` VALUES (5,9,1,'2026-06-30','dfhbv','dgdgss','fsfsvc',200.00,3,'2026-06-23 03:10:30');
/*!40000 ALTER TABLE `progress_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_id` int(11) NOT NULL,
  `doc_type` enum('offer_letter','nda','report','certificate','other') DEFAULT 'other',
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `internship_id` (`internship_id`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`internship_id`) REFERENCES `internships` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `applications`
--

DROP TABLE IF EXISTS `applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `student_name` varchar(150) DEFAULT NULL,
  `student_email` varchar(150) DEFAULT NULL,
  `student_phone` varchar(50) DEFAULT NULL,
  `student_resume` text DEFAULT NULL,
  `cover_letter` text DEFAULT NULL,
  `status` enum('pending','accepted','rejected','under_review') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_internship` (`internship_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applications`
--

LOCK TABLES `applications` WRITE;
/*!40000 ALTER TABLE `applications` DISABLE KEYS */;
/*!40000 ALTER TABLE `applications` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-06 22:55:08
