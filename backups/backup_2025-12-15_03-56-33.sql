-- CONQUER Gym Database Backup
-- Generated: 2025-12-15 03:56:33
-- Database: conquer_gym

DROP TABLE IF EXISTS `bookings`;

CREATE TABLE `bookings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned DEFAULT NULL,
  `class_id` int(11) unsigned DEFAULT NULL,
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled','attended','no-show') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_bookings_user` (`user_id`,`status`),
  KEY `idx_bookings_class` (`class_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `bookings`
INSERT INTO `bookings` VALUES
('1','4','1','2025-12-13 13:47:43',NULL,'confirmed'),
('2','4','2','2025-12-13 13:47:43',NULL,'confirmed'),
('3','5','3','2025-12-13 13:47:43',NULL,'confirmed'),
('4','6','4','2025-12-13 13:47:43',NULL,'confirmed'),
('5','7','1','2025-12-13 23:56:27','','pending'),
('6','18','4','2025-12-14 16:32:07','','pending');

DROP TABLE IF EXISTS `classes`;

CREATE TABLE `classes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `class_name` varchar(100) NOT NULL,
  `trainer_id` int(11) unsigned DEFAULT NULL,
  `schedule` datetime NOT NULL,
  `duration_minutes` int(11) NOT NULL,
  `duration` varchar(20) DEFAULT NULL,
  `max_capacity` int(11) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `current_enrollment` int(11) DEFAULT 0,
  `class_type` varchar(50) DEFAULT NULL,
  `difficulty_level` enum('beginner','intermediate','advanced') DEFAULT NULL,
  `intensity_level` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive','cancelled') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `trainer_id` (`trainer_id`),
  KEY `idx_classes_schedule` (`schedule`),
  CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `classes`
INSERT INTO `classes` VALUES
('1','Morning Yoga','2','2025-12-14 13:47:43','60','60 min','20','Main Studio','15','yoga','beginner','beginner','Join our amazing Morning Yoga class!','active'),
('2','HIIT Blast','1','2025-12-15 13:47:43','45','45 min','15','Main Studio','12','hiit','intermediate','intermediate','Join our amazing HIIT Blast class!','active'),
('3','Strength Training','2','2025-12-16 13:47:43','60','60 min','10','Main Studio','8','strength','advanced','advanced','Join our amazing Strength Training class!','active'),
('4','Cardio Kickboxing','1','2025-12-17 13:47:43','50','50 min','25','Main Studio','20','cardio','intermediate','intermediate','Join our amazing Cardio Kickboxing class!','active'),
('5','CrossFit WOD','2','2025-12-18 13:47:43','60','60 min','15','Main Studio','10','crossfit','advanced','advanced','Join our amazing CrossFit WOD class!','active');

DROP TABLE IF EXISTS `contact_messages`;

CREATE TABLE `contact_messages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('new','read','replied','closed') DEFAULT 'new',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `contact_messages`
INSERT INTO `contact_messages` VALUES
('1','Alice Johnson','alice@email.com','555-0123','Membership Inquiry','I would like to know more about your family plans...','2025-12-13 13:47:43','read'),
('2','Michael Brown','michael@email.com','555-0124','Personal Training','Looking for a trainer specialized in weight loss...','2025-12-13 13:47:43','new'),
('3','Sarah Miller','sarah.m@email.com','555-0125','Class Schedule','When are your evening yoga classes?','2025-12-13 13:47:43','replied');

DROP TABLE IF EXISTS `equipment`;

CREATE TABLE `equipment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `equipment_name` varchar(100) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `status` enum('active','maintenance','retired') DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `equipment`
INSERT INTO `equipment` VALUES
('1','Treadmill Pro 5000','LifeFitness','2023-01-15','2023-12-01','2024-06-01','active','Cardio Zone'),
('2','Leg Press Machine','Hammer Strength','2022-05-20','2023-11-15','2024-05-15','active','Strength Area'),
('3','Multi-Station Gym','Cybex','2021-08-10','2023-10-30','2024-04-30','maintenance','Functional Zone'),
('4','Dumbbell Set (5-50kg)','Rogue','2023-03-05','2023-12-10','2024-06-10','active','Free Weights');

DROP TABLE IF EXISTS `gym_members`;

CREATE TABLE `gym_members` (
  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Name` varchar(100) NOT NULL,
  `Age` int(3) NOT NULL,
  `MembershipPlan` varchar(50) NOT NULL,
  `ContactNumber` varchar(15) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `JoinDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `MembershipStatus` enum('Active','Inactive','Suspended') DEFAULT 'Active',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Email` (`Email`),
  KEY `idx_members_email` (`Email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `gym_members`
INSERT INTO `gym_members` VALUES
('1','John Doe','28','Legend','555-0101','john@email.com','2025-12-13 13:47:43','Active'),
('2','Jane Smith','32','Champion','555-0102','jane@email.com','2025-12-13 13:47:43','Active'),
('3','Bob Wilson','45','Warrior','555-0103','bob@email.com','2025-12-13 13:47:43','Active'),
('4','Jireh Dominguez','25','legend','2349342','jireh@gmail.com','2025-12-13 13:49:03','Active'),
('5','Kokey','25','legend','1356345','kokey@1.com','2025-12-13 14:50:50','Active'),
('6','Loke','25','Legend','245345','loki@gmail.com','2025-12-14 08:46:51','Active'),
('7','Wowie','25','Warrior','245924','wowi@gmail.com','2025-12-14 08:55:24','Active'),
('8','Lomi','25','Champion','2304023','lomi@gmail.com','2025-12-14 08:56:25','Active'),
('9','Irene Marie','25','Legend','1395349','irene@gmail.com','2025-12-14 16:30:01','Active');

DROP TABLE IF EXISTS `payments`;

CREATE TABLE `payments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` enum('credit_card','debit_card','paypal','bank_transfer','cash') DEFAULT NULL,
  `status` enum('completed','pending','failed','refunded') DEFAULT NULL,
  `subscription_period` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payments_user` (`user_id`,`payment_date`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `payments`
INSERT INTO `payments` VALUES
('1','4','49.99','2025-12-13 13:47:43','credit_card','completed','Monthly'),
('2','5','79.99','2025-12-13 13:47:43','debit_card','completed','Monthly'),
('3','6','29.99','2025-12-13 13:47:43','paypal','completed','Monthly'),
('4','4','49.99','2025-12-13 13:47:43','credit_card','completed','Monthly'),
('5','5','79.99','2025-12-13 13:47:43','debit_card','completed','Monthly');

DROP TABLE IF EXISTS `success_stories`;

CREATE TABLE `success_stories` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `before_image` varchar(255) DEFAULT NULL,
  `after_image` varchar(255) DEFAULT NULL,
  `story_text` text NOT NULL,
  `weight_loss` decimal(5,2) DEFAULT NULL,
  `months_taken` int(3) DEFAULT NULL,
  `trainer_id` int(11) unsigned DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `trainer_id` (`trainer_id`),
  KEY `idx_stories_featured` (`is_featured`,`approved`),
  CONSTRAINT `success_stories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `success_stories_ibfk_2` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `success_stories`
INSERT INTO `success_stories` VALUES
('1','4','Lost 50lbs in 6 months!',NULL,NULL,'Thanks to CONQUER Gym and my amazing trainer Mark, I transformed my life...','50.50','6','2','0','2025-12-13 13:47:43','1'),
('2','5','From Couch to 5K in 3 months',NULL,NULL,'Sarah helped me build confidence and stamina I never knew I had...','30.20','3','3','0','2025-12-13 13:47:43','1'),
('3','6','Gained Strength, Lost Body Fat',NULL,NULL,'The combination of strength training and proper nutrition changed everything...','25.70','4','2','0','2025-12-13 13:47:43','1');

DROP TABLE IF EXISTS `trainers`;

CREATE TABLE `trainers` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned DEFAULT NULL,
  `specialty` varchar(100) NOT NULL,
  `certification` varchar(200) DEFAULT NULL,
  `years_experience` int(3) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `trainers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `trainers`
INSERT INTO `trainers` VALUES
('1','2','Strength & Conditioning','NASM Certified, CrossFit Level 2','10','Former professional athlete with 10+ years training experience','4.80','50'),
('2','3','Yoga & Mobility','RYT 500, ACE Certified','8','Specialized in yoga therapy and mobility training','4.90','45');

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `user_type` enum('member','trainer','admin') DEFAULT 'member',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `users`
INSERT INTO `users` VALUES
('1','admin','admin@conquergym.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Administrator','admin','2025-12-13 13:47:43','2025-12-15 08:53:23','1'),
('2','markj','mark@conquergym.com','$2y$10$Nl5S1QkNnwLmW/5e9bD0P.XeV1w.TP8h9KQJg6L8s7M4nA2zV3xY6u','Mark Johnson','trainer','2025-12-13 13:47:43',NULL,'1'),
('3','sarahc','sarah@conquergym.com','$2y$10$Nl5S1QkNnwLmW/5e9bD0P.XeV1w.TP8h9KQJg6L8s7M4nA2zV3xY6u','Sarah Chen','trainer','2025-12-13 13:47:43',NULL,'1'),
('4','john_doe','john@email.com','$2y$10$Nl5S1QkNnwLmW/5e9bD0P.XeV1w.TP8h9KQJg6L8s7M4nA2zV3xY6u','John Doe','member','2025-12-13 13:47:43',NULL,'1'),
('5','jane_smith','jane@email.com','$2y$10$Nl5S1QkNnwLmW/5e9bD0P.XeV1w.TP8h9KQJg6L8s7M4nA2zV3xY6u','Jane Smith','member','2025-12-13 13:47:43',NULL,'1'),
('6','bob_wilson','bob@email.com','$2y$10$Nl5S1QkNnwLmW/5e9bD0P.XeV1w.TP8h9KQJg6L8s7M4nA2zV3xY6u','Bob Wilson','member','2025-12-13 13:47:43',NULL,'1'),
('7','jireh','jireh@gmail.com','$2y$10$fNyYkhWkpVuE6ayvCYygFe/VW9IXhj1njBfeA0kz1gPBW96n7wv9q','Jireh Dominguez','member','2025-12-13 13:49:03','2025-12-14 16:41:12','1'),
('8','kokey','kokey@1.com','$2y$10$2mwCZzFPbXjAkc6MhyCmKumzQWicPhI79KLSXCbJpKiQEPT7Bgt6i','Kokey','member','2025-12-13 14:50:50','2025-12-13 14:57:24','1'),
('15','loke','loki@gmail.com','$2y$10$aeU82eWuLbzik8UfZM3RsOqjLbU3FYNbFwRMXD4/hTN.wshxMxPZK','Loke','member','2025-12-14 08:46:51','2025-12-14 08:51:03','1'),
('16','wowie','wowi@gmail.com','$2y$10$jyX4yhy8p4r1NuOuEfmHTOq3lg8Z2p5pJyFYgA/Wt8vaPZv9tY2T6','Wowie','member','2025-12-14 08:55:24',NULL,'1'),
('17','lomi','lomi@gmail.com','$2y$10$JtYlIiglSSWDc3BcA2f8yOcWEzPppa.1sjI7vx3cNV2ycQjeb4Ofu','Lomi','member','2025-12-14 08:56:25',NULL,'1'),
('18','irene','irene@gmail.com','$2y$10$vjCymCkX/DMmSzVg7Mgh5ugu0E4.pwAaaAzpsH1A4rW0EY/FwSfj.','Irene Marie','member','2025-12-14 16:30:01',NULL,'1');

