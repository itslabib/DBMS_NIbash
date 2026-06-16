-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 15, 2026 at 07:13 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nibash`
--

-- --------------------------------------------------------

--
-- Table structure for table `apartments`
--

CREATE TABLE `apartments` (
  `id` int(11) NOT NULL,
  `building_id` int(11) DEFAULT NULL,
  `apt_number` varchar(100) DEFAULT NULL,
  `floor_number` int(11) DEFAULT NULL,
  `status` enum('available','occupied','maintenance','listed') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `apartments`
--

INSERT INTO `apartments` (`id`, `building_id`, `apt_number`, `floor_number`, `status`) VALUES
(1, 1, '2-A', 2, 'available'),
(2, 2, '4-A', 4, 'available'),
(3, 3, '3-A', 3, 'available'),
(4, 1, '2-B', NULL, 'occupied'),
(5, 1, '3-B', NULL, 'occupied'),
(6, 1, '3-C', NULL, 'occupied'),
(8, 1, '5-A', NULL, 'occupied'),
(13, 1, 'ABABIL-VILA-221935-41', 5, 'available'),
(14, 1, 'ABABIL-VILA-222032-55', 0, 'available'),
(15, 2, 'SUNRISE-VILA-222230-44', 4, 'available'),
(16, 4, '7-B', 7, 'available'),
(17, 4, '4-B', NULL, 'occupied');

-- --------------------------------------------------------

--
-- Table structure for table `apartment_assignments`
--

CREATE TABLE `apartment_assignments` (
  `id` int(11) NOT NULL,
  `apt_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `role` enum('owner','tenant') DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `monthly_rent` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `apartment_assignments`
--

INSERT INTO `apartment_assignments` (`id`, `apt_id`, `user_id`, `role`, `start_date`, `end_date`, `monthly_rent`, `is_active`) VALUES
(1, 1, 1, 'owner', '2026-06-09', NULL, NULL, 1),
(2, 2, 2, 'owner', '2026-06-09', NULL, NULL, 1),
(3, 3, 3, 'owner', '2026-06-09', NULL, NULL, 1),
(4, 4, 1, 'owner', '2026-06-09', NULL, NULL, 1),
(5, 4, 6, 'tenant', '2026-06-08', NULL, NULL, 1),
(6, 5, 1, 'owner', '2026-06-09', NULL, NULL, 1),
(7, 5, 7, 'tenant', '2026-06-08', NULL, NULL, 1),
(8, 6, 1, 'owner', '2026-06-09', NULL, NULL, 1),
(9, 6, 8, 'tenant', '2026-06-08', NULL, NULL, 1),
(10, 8, 1, 'owner', '2026-06-09', NULL, NULL, 1),
(11, 8, 9, 'tenant', '2026-06-08', NULL, NULL, 1),
(14, 13, 1, 'owner', NULL, NULL, NULL, 1),
(15, 14, 1, 'owner', NULL, NULL, NULL, 1),
(16, 15, 2, 'owner', NULL, NULL, NULL, 1),
(17, 16, 10, 'owner', '2026-06-10', NULL, NULL, 1),
(18, 17, 10, 'owner', '2026-06-10', NULL, NULL, 1),
(19, 17, 11, 'tenant', '2026-06-10', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` int(11) NOT NULL,
  `bill_number` varchar(50) DEFAULT NULL,
  `apt_id` int(11) DEFAULT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `month` varchar(20) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('Draft','Pending','Partially Paid','Paid','Overdue') DEFAULT 'Draft',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_amount` decimal(10,2) GENERATED ALWAYS AS (`subtotal` - `discount` + `tax`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`id`, `bill_number`, `apt_id`, `resident_id`, `month`, `year`, `issue_date`, `due_date`, `subtotal`, `discount`, `tax`, `paid_amount`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'INV-00001', 5, 7, 'June', 2026, '2026-06-08', '2026-06-14', 8000.00, 0.00, 0.00, 0.00, 'Overdue', '', 1, '2026-06-08 20:31:29', '2026-06-15 06:13:44'),
(2, 'INV-00002', 8, 9, 'June', 2026, '2026-06-08', '2026-06-14', 5000.00, 0.00, 0.00, 0.00, 'Overdue', '', 1, '2026-06-08 20:31:46', '2026-06-15 06:13:44'),
(4, 'INV-00004', 4, 6, 'June', 2026, '2026-06-09', '2026-06-15', 10000.00, 0.00, 0.00, 10000.00, 'Paid', '', 1, '2026-06-09 04:38:37', '2026-06-10 08:49:28'),
(6, 'INV-00006', 5, 7, 'June', 2026, '2026-06-09', '2026-06-15', 1000.00, 0.00, 0.00, 0.00, 'Pending', '', 1, '2026-06-09 17:08:23', '2026-06-09 17:08:23'),
(7, 'INV-00007', 4, 6, 'June', 2026, '2026-06-10', '2026-06-15', 17000.00, 0.00, 0.00, 17000.00, 'Paid', '', 1, '2026-06-10 13:54:01', '2026-06-10 09:55:43'),
(8, 'INV-00008', 4, 6, 'June', 2026, '2026-06-15', '2026-06-15', 24000.00, 0.00, 0.00, 0.00, 'Pending', '', 1, '2026-06-15 07:18:46', '2026-06-15 07:18:46');

-- --------------------------------------------------------

--
-- Table structure for table `bill_items`
--

CREATE TABLE `bill_items` (
  `id` int(11) NOT NULL,
  `bill_id` int(11) DEFAULT NULL,
  `utility_type_id` int(11) DEFAULT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `amount` decimal(10,2) DEFAULT NULL,
  `tax_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bill_items`
--

INSERT INTO `bill_items` (`id`, `bill_id`, `utility_type_id`, `item_name`, `quantity`, `unit_price`, `amount`, `tax_amount`) VALUES
(1, 1, 3, 'House Rent', 1.00, 7000.00, 7000.00, NULL),
(2, 1, 4, 'Gas Rent', 1.00, 500.00, 500.00, NULL),
(3, 1, 1, 'Water', 1.00, 500.00, 500.00, NULL),
(4, 2, 3, 'House Rent', 1.00, 5000.00, 5000.00, NULL),
(5, 2, NULL, 'Custom Item', 1.00, 0.00, 0.00, NULL),
(6, 2, NULL, 'Custom Item', 1.00, 0.00, 0.00, NULL),
(10, 4, 3, 'House Rent', 1.00, 10000.00, 10000.00, NULL),
(11, 4, NULL, 'Custom Item', 1.00, 0.00, 0.00, NULL),
(12, 4, NULL, 'Custom Item', 1.00, 0.00, 0.00, NULL),
(16, 6, 4, 'Gas Rent', 1.00, 1000.00, 1000.00, NULL),
(17, 6, NULL, 'Custom Item', 1.00, 0.00, 0.00, NULL),
(18, 6, NULL, 'Custom Item', 1.00, 0.00, 0.00, NULL),
(19, 7, 3, 'House rent', 1.00, 15000.00, 15000.00, NULL),
(20, 7, 1, 'Water bill', 1.00, 1000.00, 1000.00, NULL),
(21, 7, 6, 'Parking rent', 1.00, 1000.00, 1000.00, NULL),
(22, 8, 3, 'House rent', 1.00, 24000.00, 24000.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `buildings`
--

CREATE TABLE `buildings` (
  `id` int(11) NOT NULL,
  `building_number` varchar(100) NOT NULL,
  `building_name` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buildings`
--

INSERT INTO `buildings` (`id`, `building_number`, `building_name`, `address`, `area`, `created_at`, `latitude`, `longitude`) VALUES
(1, '101/A', 'Ababil Vila', 'Notunbazar, Dhaka', '', '2026-06-08 19:03:50', 23.79452780, 90.42615940),
(2, '102/A', 'Sunrise Vila', 'Shewrapara, Dhaka', '', '2026-06-08 19:07:58', 23.79096310, 90.37550870),
(3, '103/A', 'Sunshine Vila', 'Mirpur 11, Dhaka', '', '2026-06-08 19:11:59', NULL, NULL),
(4, '105/A', 'Flower nibash', 'Mirpur 11, Dhaka', '', '2026-06-10 06:13:05', 23.81909590, 90.36528090);

-- --------------------------------------------------------

--
-- Table structure for table `building_managers`
--

CREATE TABLE `building_managers` (
  `id` int(11) NOT NULL,
  `building_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('admin','moderator') DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `building_managers`
--

INSERT INTO `building_managers` (`id`, `building_id`, `user_id`, `role`, `created_at`) VALUES
(1, 1, 1, 'admin', '2026-06-08 19:03:50'),
(2, 2, 2, 'admin', '2026-06-08 19:07:58'),
(3, 3, 3, 'admin', '2026-06-08 19:11:59'),
(4, 4, 10, 'admin', '2026-06-10 06:13:05');

-- --------------------------------------------------------

--
-- Table structure for table `cctv_alerts`
--

CREATE TABLE `cctv_alerts` (
  `id` int(11) NOT NULL,
  `capture_id` int(11) NOT NULL,
  `building_id` int(11) DEFAULT NULL,
  `alert_type` enum('unknown_face','motion_detected','intruder_alert') DEFAULT 'unknown_face',
  `message` text NOT NULL,
  `is_sent` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cctv_alerts`
--

INSERT INTO `cctv_alerts` (`id`, `capture_id`, `building_id`, `alert_type`, `message`, `is_sent`, `sent_at`, `created_at`) VALUES
(25, 25, 1, 'motion_detected', 'Motion detected at Cam 2 (Balcony)', 0, NULL, '2026-06-14 13:32:27'),
(26, 26, 1, 'motion_detected', 'Motion detected at Cam 2 (Balcony)', 0, NULL, '2026-06-14 13:32:32'),
(27, 29, 1, 'unknown_face', 'Unknown person detected at Cam 2 (Balcony)', 0, NULL, '2026-06-14 13:32:48'),
(28, 30, 1, 'motion_detected', 'Motion detected at Cam 2 (Balcony)', 0, NULL, '2026-06-14 13:32:52'),
(29, 32, 1, 'motion_detected', 'Motion detected at Cam 2 (Balcony)', 0, NULL, '2026-06-14 13:33:02'),
(30, 34, 1, 'motion_detected', 'Motion detected at Cam 2 (Balcony)', 0, NULL, '2026-06-14 13:33:13'),
(31, 38, 1, 'motion_detected', 'Motion detected at Cam 2 (Balcony)', 0, NULL, '2026-06-14 13:33:34'),
(33, 40, 1, 'motion_detected', 'Motion detected at Cam 2 (Balcony)', 0, NULL, '2026-06-14 13:33:44');

-- --------------------------------------------------------

--
-- Table structure for table `cctv_captures`
--

CREATE TABLE `cctv_captures` (
  `id` int(11) NOT NULL,
  `camera_id` int(11) NOT NULL,
  `apt_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `detection_type` enum('motion','face','unknown','intruder') DEFAULT 'face',
  `matched_confidence` decimal(5,2) DEFAULT NULL,
  `captured_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_reviewed` tinyint(1) DEFAULT 0,
  `face_hash` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cctv_captures`
--

INSERT INTO `cctv_captures` (`id`, `camera_id`, `apt_id`, `user_id`, `image_path`, `detection_type`, `matched_confidence`, `captured_at`, `is_reviewed`, `face_hash`) VALUES
(25, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193227_286480.jpg', 'motion', NULL, '2026-06-14 13:32:27', 0, NULL),
(26, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193232_235157.jpg', 'motion', NULL, '2026-06-14 13:32:32', 0, NULL),
(27, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193237_780979.jpg', 'face', 100.00, '2026-06-14 13:32:37', 0, NULL),
(28, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193242_752774.jpg', 'face', 100.00, '2026-06-14 13:32:42', 0, NULL),
(29, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193248_423380.jpg', 'unknown', NULL, '2026-06-14 13:32:48', 0, NULL),
(30, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193252_630125.jpg', 'motion', NULL, '2026-06-14 13:32:52', 0, NULL),
(31, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193258_074509.jpg', 'face', 100.00, '2026-06-14 13:32:58', 0, NULL),
(32, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193302_925977.jpg', 'motion', NULL, '2026-06-14 13:33:02', 0, NULL),
(33, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193308_518748.jpg', 'face', 100.00, '2026-06-14 13:33:08', 0, NULL),
(34, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193313_503040.jpg', 'motion', NULL, '2026-06-14 13:33:13', 0, NULL),
(35, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193318_993235.jpg', 'face', 100.00, '2026-06-14 13:33:19', 0, NULL),
(37, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193329_397807.jpg', 'face', 100.00, '2026-06-14 13:33:29', 0, NULL),
(38, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193334_107835.jpg', 'motion', NULL, '2026-06-14 13:33:34', 0, NULL),
(40, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193344_966562.jpg', 'motion', NULL, '2026-06-14 13:33:44', 0, NULL),
(41, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193350_175008.jpg', 'face', 100.00, '2026-06-14 13:33:50', 0, NULL),
(42, 2, NULL, NULL, '/assets/cctv/captures/1/cam_2_20260614_193355_213685.jpg', 'face', 100.00, '2026-06-14 13:33:55', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cctv_devices`
--

CREATE TABLE `cctv_devices` (
  `id` int(11) NOT NULL,
  `camera_name` varchar(100) NOT NULL,
  `ip_address` varchar(255) NOT NULL,
  `location_description` text DEFAULT NULL,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `building_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cctv_devices`
--

INSERT INTO `cctv_devices` (`id`, `camera_name`, `ip_address`, `location_description`, `status`, `created_at`, `building_id`) VALUES
(2, 'Cam 2', 'http://192.168.0.103:8080/video', 'Balcony', 'active', '2026-06-14 13:32:21', 1);

-- --------------------------------------------------------

--
-- Table structure for table `community_categories`
--

CREATE TABLE `community_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `community_categories`
--

INSERT INTO `community_categories` (`id`, `category_name`) VALUES
(3, 'Announcement'),
(2, 'Complaint'),
(4, 'Event'),
(5, 'General'),
(7, 'Help Request'),
(6, 'Lost & Found'),
(8, 'Maintenance'),
(1, 'Notice');

-- --------------------------------------------------------

--
-- Table structure for table `community_comments`
--

CREATE TABLE `community_comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `apt_id` int(11) NOT NULL,
  `parent_comment_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `community_comments`
--

INSERT INTO `community_comments` (`id`, `post_id`, `user_id`, `apt_id`, `parent_comment_id`, `content`, `image_path`, `created_at`) VALUES
(1, 1, 8, 6, NULL, 'I am also new here', '', '2026-06-08 20:04:08'),
(2, 4, 1, 1, NULL, 'So? ', '', '2026-06-08 20:23:18');

-- --------------------------------------------------------

--
-- Table structure for table `community_posts`
--

CREATE TABLE `community_posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `apt_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `status` enum('published','archived','reported') DEFAULT 'published',
  `is_pinned` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `community_posts`
--

INSERT INTO `community_posts` (`id`, `user_id`, `apt_id`, `category_id`, `title`, `content`, `status`, `is_pinned`, `created_at`, `updated_at`) VALUES
(1, 6, 4, 5, 'Hello', 'Hello,\r\nI am new here. :&quot;)', 'published', 0, '2026-06-08 19:53:53', '2026-06-08 19:53:53'),
(2, 6, 4, 2, 'Lift', 'When the lift will fix? ', 'published', 0, '2026-06-08 19:54:12', '2026-06-08 19:54:12'),
(3, 8, 6, 7, 'Regarding INFO', 'What are the rules and regulation of this building? I didn&#039;t notice when the owner explain this', 'published', 0, '2026-06-08 20:04:48', '2026-06-08 20:04:48'),
(4, 8, 6, 1, 'Intro', 'Hello I am Rohan', 'published', 0, '2026-06-08 20:05:29', '2026-06-08 20:05:29'),
(5, 1, 1, 1, 'Welcome', 'Welcome everyone', 'published', 1, '2026-06-08 20:23:32', '2026-06-08 20:23:32'),
(6, 1, 1, 1, 'Warning', '@Md_Rohan do not time pass', 'published', 0, '2026-06-08 20:26:14', '2026-06-08 20:26:14');

-- --------------------------------------------------------

--
-- Table structure for table `community_post_images`
--

CREATE TABLE `community_post_images` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `community_post_images`
--

INSERT INTO `community_post_images` (`id`, `post_id`, `image_path`) VALUES
(1, 4, 'assets/uploads/community/1780949129_images2.png');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount_percent` int(11) NOT NULL CHECK (`discount_percent` between 1 and 100),
  `valid_until` datetime DEFAULT NULL,
  `max_uses` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `target_user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emergency_contacts`
--

CREATE TABLE `emergency_contacts` (
  `id` int(11) NOT NULL,
  `user_profile_id` int(11) DEFAULT NULL,
  `apt_id` int(11) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_type` enum('Global','Building','Personal') DEFAULT 'Global'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entry_logs`
--

CREATE TABLE `entry_logs` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `entry_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `exit_time` timestamp NULL DEFAULT NULL,
  `entry_method` enum('FaceScan','PlateRecognition') NOT NULL,
  `verification_score` decimal(5,2) DEFAULT NULL,
  `gate_terminal_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `entry_logs`
--

INSERT INTO `entry_logs` (`id`, `visit_id`, `entry_time`, `exit_time`, `entry_method`, `verification_score`, `gate_terminal_id`) VALUES
(1, 1, '2026-06-08 19:46:47', NULL, 'FaceScan', NULL, NULL),
(2, 1, '2026-06-08 19:46:59', NULL, 'FaceScan', NULL, NULL),
(3, 1, '2026-06-10 07:31:17', NULL, 'FaceScan', NULL, NULL),
(4, 1, '2026-06-15 07:30:06', NULL, 'FaceScan', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `family_members`
--

CREATE TABLE `family_members` (
  `id` int(11) NOT NULL,
  `primary_user_id` int(11) NOT NULL,
  `apt_id` int(11) NOT NULL,
  `member_name` varchar(100) NOT NULL,
  `relation` varchar(50) NOT NULL,
  `dob` date DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `phone_number` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guests`
--

CREATE TABLE `guests` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `nid_passport_no` varchar(50) DEFAULT NULL,
  `face_descriptor` text NOT NULL,
  `blacklisted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guests`
--

INSERT INTO `guests` (`id`, `full_name`, `phone_number`, `nid_passport_no`, `face_descriptor`, `blacklisted`, `created_at`) VALUES
(1, 'Sakin', '01824923164', NULL, '[-0.12170031666755676,0.05345975235104561,0.09514075517654419,-0.00915704295039177,-0.1457182914018631,-0.05690325051546097,-0.08727197349071503,-0.1578431874513626,0.1006351187825203,-0.1819346845149994,0.1409888118505478,-0.09978476911783218,-0.13700926303863525,-0.10282102227210999,-0.04687725007534027,0.1528497189283371,-0.11140628159046173,-0.16214178502559662,0.00929597020149231,-0.04745650291442871,0.07903444766998291,0.027335720136761665,-0.03708534687757492,0.035735126584768295,-0.07447966188192368,-0.38200679421424866,-0.08698277920484543,-0.10036248713731766,0.002149818930774927,-0.07491662353277206,-0.060938362032175064,0.026166103780269623,-0.15927612781524658,-0.03023420087993145,-0.014560983516275883,0.1318189650774002,0.008815488778054714,0.012923085130751133,0.0973401665687561,-0.04203934967517853,-0.2507399022579193,-0.03899846598505974,0.07886252552270889,0.1936725378036499,0.12134771794080734,0.14382393658161163,0.013133501634001732,-0.06595499813556671,0.07929778844118118,-0.18048930168151855,0.07570777088403702,0.07511217147111893,0.09612273424863815,0.03216936066746712,0.06456220149993896,-0.17877820134162903,0.01994304545223713,0.02985970303416252,-0.17045985162258148,0.06109031289815903,0.08120613545179367,-0.06287368386983871,-0.031242962926626205,0.03043980896472931,0.28163594007492065,0.13734863698482513,-0.0403970330953598,-0.1227276623249054,0.22113348543643951,-0.2160540074110031,-0.09572296589612961,0.03237041458487511,-0.06150716543197632,-0.14945033192634583,-0.28084254264831543,0.006004517897963524,0.42541322112083435,0.13702470064163208,-0.17468488216400146,-0.0012052655220031738,0.0036744808312505484,0.038064923137426376,0.18548895418643951,0.09148997068405151,-0.056830327957868576,0.04867294058203697,-0.08809104561805725,-0.006193977314978838,0.1880907565355301,-0.01001142431050539,-0.028708938509225845,0.16981922090053558,0.01789037138223648,0.0694110170006752,0.039126891642808914,0.009748438373208046,-0.033498797565698624,0.054102085530757904,-0.1258496791124344,-0.026248443871736526,0.10280866175889969,-0.014650796540081501,0.016918638721108437,0.13867990672588348,-0.175065279006958,0.12674236297607422,-0.0393383726477623,-0.025381404906511307,-0.017861753702163696,0.0697704628109932,-0.10352679342031479,-0.042439453303813934,0.12809288501739502,-0.19995801150798798,0.1840118020772934,0.19139395654201508,0.018274517729878426,0.17419296503067017,0.07968349754810333,0.07161158323287964,0.05556148290634155,-0.11004497855901718,-0.20294012129306793,-0.06796208024024963,0.085513636469841,-0.08825650811195374,0.05296556279063225,0.07095487415790558]', 0, '2026-06-08 19:44:10'),
(2, 'labib', '01238579123', '9348276923847634', '[-0.10438845306634903,0.09314804524183273,0.11192486435174942,-0.008626818656921387,0.05901983380317688,-0.024941500276327133,0.011887877248227596,-0.03840450942516327,0.15002115070819855,-0.07015208899974823,0.265870600938797,-0.031574130058288574,-0.1454208791255951,-0.17867518961429596,0.05471035838127136,0.12949399650096893,-0.14953136444091797,-0.08600844442844391,-0.12414644658565521,-0.12426792830228806,0.019915062934160233,0.015200424939393997,0.00548106525093317,0.0358780212700367,-0.1387270838022232,-0.4004424810409546,-0.0854329839348793,-0.14597609639167786,0.07484687119722366,-0.09600362926721573,0.006462492048740387,0.010043415240943432,-0.18057328462600708,-0.020384211093187332,-0.09392900764942169,0.04296785220503807,-0.020327629521489143,-0.06858144700527191,0.16498197615146637,0.03212786465883255,-0.10179012268781662,-0.05750514194369316,-0.0312519297003746,0.2436603456735611,0.14939089119434357,0.011488639749586582,0.05636380985379219,-0.0019616896752268076,0.0016493694856762886,-0.202574223279953,0.013704754412174225,0.13035154342651367,0.050599344074726105,0.09742779284715652,0.03580610454082489,-0.09681881219148636,-0.01538323238492012,-0.03301490098237991,-0.2004806250333786,0.05307920649647713,-0.023875124752521515,-0.09479153156280518,-0.09814329445362091,0.002891845302656293,0.24084192514419556,0.12554611265659332,-0.09667672216892242,-0.14349140226840973,0.17700856924057007,-0.14935606718063354,0.021232232451438904,0.07281113415956497,-0.09195595979690552,-0.15990903973579407,-0.2839697599411011,0.14466264843940735,0.3753930330276489,0.11168662458658218,-0.18397140502929688,-0.010991401970386505,-0.18243145942687988,-0.06232726201415062,0.06661300361156464,0.06270740926265717,-0.06302351504564285,0.025004737079143524,-0.11032427847385406,0.09477676451206207,0.12794198095798492,-0.027079105377197266,-0.04275434836745262,0.21538794040679932,-0.024804402142763138,0.020138897001743317,-0.007816895842552185,0.02840341068804264,-0.08526929467916489,0.049999114125967026,-0.04862118884921074,0.0019099429482594132,0.18089111149311066,-0.035242460668087006,0.03359248861670494,0.08888702839612961,-0.10364839434623718,0.13740897178649902,0.03230474144220352,-0.05194363370537758,0.07521018385887146,0.05383623391389847,-0.17443399131298065,-0.1354570984840393,0.17876160144805908,-0.18650655448436737,0.14330577850341797,0.19499820470809937,0.028142955154180527,0.14192871749401093,0.08968018740415573,0.1397814005613327,-0.03683993220329285,-0.040130045264959335,-0.14178310334682465,0.01953543908894062,0.01903410442173481,0.023781567811965942,-0.013773530721664429,0.021123070269823074]', 0, '2026-06-08 19:44:42'),
(3, 'Rohan', '01994523985', NULL, '[-0.11124572157859802,0.10935609042644501,0.03623413294553757,-0.061956897377967834,-0.04780219495296478,0.02179165929555893,-0.04635949432849884,-0.047095175832509995,0.1621789038181305,-0.15136727690696716,0.2156505286693573,-0.019540147855877876,-0.1668952852487564,-0.107106514275074,-0.05343266576528549,0.1277698129415512,-0.16245129704475403,-0.1343800276517868,-0.04502998664975166,-0.07722508907318115,0.013064590282738209,0.007767853792756796,0.021417388692498207,0.026744605973362923,-0.09278225898742676,-0.42747965455055237,-0.05372277647256851,-0.12309157848358154,-0.027749931439757347,-0.11047066003084183,-0.02941550500690937,0.0184685867279768,-0.1875382959842682,-0.02110661193728447,-0.029297109693288803,0.12120073288679123,-0.09465454518795013,-0.035555120557546616,0.18018652498722076,0.02535238116979599,-0.03962506353855133,-0.03644268959760666,0.01313867513090372,0.32473528385162354,0.17153093218803406,0.05612106993794441,0.016257209703326225,-0.02974548563361168,0.12305768579244614,-0.2553132474422455,0.08497769385576248,0.13847865164279938,0.032488975673913956,0.09062901139259338,0.07157818973064423,-0.06359636783599854,0.046272147446870804,0.10172197967767715,-0.16886505484580994,0.040890567004680634,-0.000057142147852573544,-0.034947533160448074,-0.06377745419740677,0.00025676641962490976,0.3182251453399658,0.15159161388874054,-0.08998378366231918,-0.06966271996498108,0.13998785614967346,-0.1297743320465088,-0.024307962507009506,-0.05619407445192337,-0.1312064230442047,-0.1857120841741562,-0.207576185464859,0.1091802716255188,0.3870947062969208,0.19103671610355377,-0.10747038573026657,0.045953039079904556,-0.05831474810838699,-0.05008452758193016,0.12403810769319534,0.07843024283647537,-0.10621020942926407,0.018169036135077477,-0.07610785961151123,0.12033583223819733,0.15330491960048676,0.01130370981991291,-0.07013338059186935,0.18996474146842957,-0.03494248539209366,0.03916485607624054,0.049978941679000854,-0.06584905833005905,-0.09040218591690063,0.028956126421689987,-0.17208707332611084,-0.034176528453826904,0.06199929118156433,-0.02808089181780815,-0.007254771422594786,0.09937193989753723,-0.12648293375968933,0.09167339652776718,-0.01972208358347416,-0.017619827762246132,0.019498106092214584,-0.0055862111039459705,-0.144881933927536,-0.08382043242454529,0.10462487488985062,-0.17707204818725586,0.16453950107097626,0.13710233569145203,0.07198277860879898,0.19713237881660461,0.11376885324716568,0.029291346669197083,0.04913199692964554,-0.031053204089403152,-0.17378856241703033,0.010194504633545876,0.07924060523509979,0.07315905392169952,0.07684239745140076,0.004804405849426985]', 0, '2026-06-08 20:08:17');

-- --------------------------------------------------------

--
-- Table structure for table `guest_vehicles`
--

CREATE TABLE `guest_vehicles` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `vehicle_type` varchar(20) DEFAULT NULL,
  `entry_photo_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 5, 'New Message', 'Suchak sent you a message.', 'messages/index.php?user_id=6', 0, '2026-06-08 20:01:03'),
(2, 6, 'New Reply', 'Md Rohan replied to your post: \"Hello\"', 'community_hub.php#post-1', 0, '2026-06-08 20:04:08'),
(3, 1, 'New Parking Request', 'A resident requested a parking spot.', 'parking/index.php', 0, '2026-06-08 20:09:16'),
(4, 8, 'New Message', 'Ahnaf Tajwar Suchak sent you a message.', 'messages/index.php?user_id=1', 0, '2026-06-08 20:23:06'),
(5, 8, 'New Reply', 'Ahnaf Tajwar Suchak replied to your post: \"Intro\"', 'community_hub.php#post-4', 0, '2026-06-08 20:23:18'),
(6, 8, 'You were mentioned', 'Ahnaf Tajwar Suchak mentioned you in the Community Hub.', 'community_hub.php#post-6', 0, '2026-06-08 20:26:14'),
(7, 7, 'New Invoice Issued', 'A new invoice (INV-00001) for June 2026 totaling $8,000.00 has been issued. Due on Jun 14, 2026.', 'resident/billing.php', 0, '2026-06-08 20:31:29'),
(8, 9, 'New Invoice Issued', 'A new invoice (INV-00002) for June 2026 totaling $5,000.00 has been issued. Due on Jun 14, 2026.', 'resident/billing.php', 0, '2026-06-08 20:31:46'),
(9, 6, 'New Invoice Issued', 'A new invoice (INV-00003) for June 2026 totaling $10,000.00 has been issued. Due on Jun 15, 2026.', 'resident/billing.php', 0, '2026-06-09 04:38:28'),
(10, 6, 'New Invoice Issued', 'A new invoice (INV-00004) for June 2026 totaling $10,000.00 has been issued. Due on Jun 15, 2026.', 'resident/billing.php', 0, '2026-06-09 04:38:37'),
(11, 1, 'Security Alert: Unknown Scan', 'An unidentified person attempted to scan their face at the gate.', 'owner/guest_entries.php', 0, '2026-06-09 05:16:21'),
(12, 2, 'Security Alert: Unknown Scan', 'An unidentified person attempted to scan their face at the gate.', 'owner/guest_entries.php', 0, '2026-06-09 05:16:35'),
(14, 7, 'New Invoice Issued', 'A new invoice (INV-00006) for June 2026 totaling $1,000.00 has been issued. Due on Jun 15, 2026.', 'resident/billing.php', 0, '2026-06-09 17:08:23'),
(15, 6, 'New Invoice Issued', 'A new invoice (INV-00007) for June 2026 totaling $17,000.00 has been issued. Due on Jun 15, 2026.', 'resident/billing.php', 0, '2026-06-10 13:54:01'),
(16, 1, 'New Parking Request', 'A resident requested a parking spot.', 'parking/index.php', 0, '2026-06-14 18:40:03'),
(17, 4, 'New Message', 'Ahnaf Tajwar Suchak sent you a message.', 'messages/index.php?user_id=1', 0, '2026-06-14 18:41:03'),
(18, 1, 'New Message', 'Ashfaq Ahmed Sakin sent you a message.', 'messages/index.php?user_id=4', 0, '2026-06-14 19:14:24'),
(19, 6, 'New Invoice Issued', 'A new invoice (INV-00008) for June 2026 totaling $24,000.00 has been issued. Due on Jun 15, 2026.', 'resident/billing.php', 0, '2026-06-15 07:18:46'),
(20, 1, 'Parking Request Approval Needed', 'Owner approved a request for your parking spot. Your permission is required.', 'parking/index.php', 0, '2026-06-15 07:21:56'),
(21, 1, 'New Parking Request', 'A resident requested a parking spot.', 'parking/index.php', 0, '2026-06-15 07:23:00'),
(22, 6, 'Parking Request Approved', 'Your parking request has been fully approved.', NULL, 0, '2026-06-15 07:23:18');

-- --------------------------------------------------------

--
-- Table structure for table `parking_details`
--

CREATE TABLE `parking_details` (
  `listing_id` int(11) NOT NULL,
  `vehicle_type` enum('car','motorbike') NOT NULL DEFAULT 'car',
  `parking_length` decimal(8,2) DEFAULT NULL,
  `parking_width` decimal(8,2) DEFAULT NULL,
  `measurement_unit` enum('feet','meter') NOT NULL DEFAULT 'feet'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parking_details`
--

INSERT INTO `parking_details` (`listing_id`, `vehicle_type`, `parking_length`, `parking_width`, `measurement_unit`) VALUES
(4, 'car', 12.00, 8.00, 'feet');

-- --------------------------------------------------------

--
-- Table structure for table `parking_requests`
--

CREATE TABLE `parking_requests` (
  `id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `target_resident_id` int(11) DEFAULT NULL,
  `building_id` int(11) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `license_plate` varchar(30) DEFAULT NULL,
  `purpose` text NOT NULL,
  `for_whom` varchar(100) NOT NULL,
  `status` enum('pending_owner','pending_resident','approved','rejected','cancelled') DEFAULT 'pending_owner',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `overstay_notified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parking_requests`
--

INSERT INTO `parking_requests` (`id`, `slot_id`, `requester_id`, `target_resident_id`, `building_id`, `start_time`, `end_time`, `license_plate`, `purpose`, `for_whom`, `status`, `created_at`, `overstay_notified`) VALUES
(1, 5, 8, NULL, 1, '2026-06-09 02:08:00', '2026-06-09 17:20:00', 'DHK ka 12-0010', 'Brother coming', 'Self', 'pending_owner', '2026-06-08 20:09:16', 0),
(2, 2, 6, 1, 1, '2026-06-15 00:39:00', '2026-06-15 13:40:00', 'DHK ka 12-0020', 'Test', 'Self', 'pending_resident', '2026-06-14 18:40:03', 0),
(3, 4, 6, NULL, 1, '2026-06-15 13:22:00', '2026-06-16 13:22:00', 'DHK KA 12-1000', 'TEST', 'Self', 'approved', '2026-06-15 07:23:00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `parking_slots`
--

CREATE TABLE `parking_slots` (
  `id` int(11) NOT NULL,
  `building_id` int(11) DEFAULT NULL,
  `slot_number` varchar(20) NOT NULL,
  `apt_id` int(11) DEFAULT NULL,
  `floor_level` varchar(10) DEFAULT NULL,
  `current_status` enum('Occupied','Vacant','Rented') DEFAULT 'Vacant',
  `license_plate` varchar(30) DEFAULT NULL,
  `temporary_name` varchar(50) DEFAULT NULL,
  `temporary_until` datetime DEFAULT NULL,
  `temporary_plate` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parking_slots`
--

INSERT INTO `parking_slots` (`id`, `building_id`, `slot_number`, `apt_id`, `floor_level`, `current_status`, `license_plate`, `temporary_name`, `temporary_until`, `temporary_plate`) VALUES
(1, 1, 'P-1', 4, NULL, 'Vacant', 'DHK ka 12-0001', NULL, NULL, NULL),
(2, 1, 'P-2', 5, NULL, 'Occupied', 'DHK ka 12-0002', NULL, NULL, NULL),
(3, 1, 'P-3', 6, NULL, 'Occupied', 'DHK ka 12-0003', NULL, NULL, NULL),
(4, 1, 'P-4', NULL, NULL, 'Vacant', NULL, '2-B', '2026-06-16 13:22:00', 'DHK KA 12-1000'),
(5, 1, 'P-5', NULL, NULL, 'Vacant', NULL, NULL, NULL, NULL),
(7, 2, 'P-6', NULL, NULL, 'Vacant', NULL, NULL, NULL, NULL),
(8, 2, 'P-7', NULL, NULL, 'Vacant', NULL, NULL, NULL, NULL),
(9, 2, 'P-8', NULL, NULL, 'Vacant', NULL, NULL, NULL, NULL),
(10, 4, 'P-1', 17, NULL, 'Occupied', 'DHK ka 12-1000', NULL, NULL, NULL),
(11, 4, 'P-2', NULL, NULL, 'Vacant', NULL, NULL, NULL, NULL),
(12, 4, 'P-3', NULL, NULL, 'Vacant', NULL, NULL, NULL, NULL),
(13, 4, 'P-4', NULL, NULL, 'Vacant', NULL, NULL, NULL, NULL),
(14, 1, 'P-6', NULL, NULL, 'Vacant', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `bill_id` int(11) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` varchar(20) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `bill_id`, `transaction_id`, `amount_paid`, `payment_method`, `payment_status`, `payment_date`) VALUES
(1, 4, 'SSLCZ_6a2921c7b527d', 10000.00, 'SSLCommerz - BKASH-BKash', 'Success', '2026-06-10 04:37:09'),
(2, 7, 'SSLCZ_6a296cd15f61a', 17000.00, 'SSLCommerz - NAGAD-Nagad', 'Success', '2026-06-10 09:55:43');

-- --------------------------------------------------------

--
-- Table structure for table `personal_messages`
--

CREATE TABLE `personal_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personal_messages`
--

INSERT INTO `personal_messages` (`id`, `sender_id`, `receiver_id`, `message`, `is_read`, `created_at`) VALUES
(1, 6, 5, 'hello are you free tomorrow? then i will book you', 0, '2026-06-08 20:01:03'),
(2, 1, 8, 'Hello, Ki obostha?', 0, '2026-06-08 20:23:06'),
(3, 1, 4, 'Hello, When you free?', 1, '2026-06-14 18:41:03'),
(4, 4, 1, 'dont know', 0, '2026-06-14 19:14:24');

-- --------------------------------------------------------

--
-- Table structure for table `provider_bookings`
--

CREATE TABLE `provider_bookings` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `time_slot` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('Booked','Completed','Cancelled') DEFAULT 'Booked',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `amount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provider_bookings`
--

INSERT INTO `provider_bookings` (`id`, `provider_id`, `resident_id`, `booking_date`, `time_slot`, `end_time`, `status`, `created_at`, `amount`) VALUES
(1, 1, 1, '2026-06-15', '13:45:00', '14:45:00', 'Booked', '2026-06-14 18:42:07', 0.00),
(2, 1, 1, '2026-06-15', '12:00:00', '13:00:00', 'Booked', '2026-06-14 18:59:26', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `provider_locations`
--

CREATE TABLE `provider_locations` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `address` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provider_locations`
--

INSERT INTO `provider_locations` (`id`, `provider_id`, `latitude`, `longitude`, `address`) VALUES
(1, 1, 23.79452780, 90.42615940, 'Secondary Location 1');

-- --------------------------------------------------------

--
-- Table structure for table `provider_reviews`
--

CREATE TABLE `provider_reviews` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provider_reviews`
--

INSERT INTO `provider_reviews` (`id`, `provider_id`, `resident_id`, `rating`, `review_text`, `created_at`) VALUES
(1, 2, 1, 4, 'Good', '2026-06-14 18:40:51'),
(2, 1, 1, 5, 'Bhalo', '2026-06-14 18:42:27');

-- --------------------------------------------------------

--
-- Table structure for table `provider_subscription_plans`
--

CREATE TABLE `provider_subscription_plans` (
  `id` int(11) NOT NULL,
  `duration_months` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `save_amount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provider_subscription_plans`
--

INSERT INTO `provider_subscription_plans` (`id`, `duration_months`, `plan_name`, `price`, `save_amount`) VALUES
(1, 1, '1 Month PRO', 500.00, 0.00),
(2, 6, '6 Months PRO', 2500.00, 500.00),
(3, 12, '1 Year PRO', 4500.00, 1500.00);

-- --------------------------------------------------------

--
-- Table structure for table `rental_images`
--

CREATE TABLE `rental_images` (
  `id` int(11) NOT NULL,
  `listing_id` int(11) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `image_category` enum('cover','img1','img2','img3') DEFAULT 'cover'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rental_images`
--

INSERT INTO `rental_images` (`id`, `listing_id`, `image_path`, `image_category`) VALUES
(1, 1, 'assets/uploads/rentals/1780947773_8256_cover.jpg', 'cover'),
(2, 1, 'assets/uploads/rentals/1780947773_2819_gallery.jpg', ''),
(3, 1, 'assets/uploads/rentals/1780947773_2298_gallery.jpg', ''),
(4, 1, 'assets/uploads/rentals/1780947773_1314_gallery.jpg', ''),
(5, 2, 'assets/uploads/rentals/1780949547_5496_cover.jpg', 'cover'),
(6, 2, 'assets/uploads/rentals/1780949547_2705_gallery.jpg', ''),
(7, 2, 'assets/uploads/rentals/1780949547_8362_gallery.jpg', ''),
(8, 3, 'assets/uploads/rentals/1780949975_4484_cover.jpg', 'cover'),
(9, 3, 'assets/uploads/rentals/1780949975_8292_gallery.jpg', ''),
(10, 3, 'assets/uploads/rentals/1780949975_4645_gallery.jpg', ''),
(11, 3, 'assets/uploads/rentals/1780949975_5874_gallery.jpg', ''),
(12, 4, 'assets/uploads/rentals/1780950032_1100_cover.jpg', 'cover'),
(13, 4, 'assets/uploads/rentals/1780950032_8284_gallery.jpg', ''),
(14, 4, 'assets/uploads/rentals/1780950032_3803_gallery.jpg', ''),
(15, 5, 'assets/uploads/rentals/1780950150_6945_cover.jpg', 'cover'),
(16, 5, 'assets/uploads/rentals/1780950150_6052_gallery.jpg', ''),
(17, 5, 'assets/uploads/rentals/1780950150_4424_gallery.jpg', ''),
(18, 5, 'assets/uploads/rentals/1780950150_2069_gallery.jpg', ''),
(19, 1, 'assets/uploads/rentals/1781082392_9400_gallery.jpg', ''),
(20, 1, 'assets/uploads/rentals/1781082392_4141_gallery.jpg', '');

-- --------------------------------------------------------

--
-- Table structure for table `rental_listings`
--

CREATE TABLE `rental_listings` (
  `id` int(11) NOT NULL,
  `apt_id` int(11) DEFAULT NULL,
  `building_id` int(11) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `custom_title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `rent_amount` decimal(8,2) DEFAULT NULL,
  `total_bedrooms` int(11) DEFAULT NULL,
  `floor_number` int(11) DEFAULT NULL,
  `washrooms` int(11) DEFAULT NULL,
  `balconies` int(11) DEFAULT NULL,
  `verification_doc_path` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rental_type` enum('house','parking') NOT NULL DEFAULT 'house'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rental_listings`
--

INSERT INTO `rental_listings` (`id`, `apt_id`, `building_id`, `owner_id`, `custom_title`, `description`, `rent_amount`, `total_bedrooms`, `floor_number`, `washrooms`, `balconies`, `verification_doc_path`, `is_verified`, `created_at`, `rental_type`) VALUES
(1, 4, 1, 6, 'Ababil Vila - 2-B', '1. Wifi\r\n2. Lift\r\n3. 24/7 electricity\r\n4. Generator', 7000.00, 1, 4, 1, 1, 'assets/uploads/verification/1780947773_verif_doc 4.jpg', 0, '2026-06-08 19:42:53', 'house'),
(2, 6, 1, 8, 'Ababil Vila - 3-C', '24/7 Water\r\nDon\'t except Hotel InterContinental type facilities in 4000 BDT\r\nJust be happy with 24/7 water', 4000.00, 1, 3, 1, 1, 'assets/uploads/verification/1780949547_verif_doc 2.png', 0, '2026-06-08 20:12:27', 'house'),
(3, 13, 1, 1, 'Ababil Vila - ABABIL-VILA-221935-41', 'CCTV\r\nLift\r\nStair', 22000.00, 3, 5, 2, 2, 'assets/uploads/verification/1780949975_verif_doc 1.jpg', 1, '2026-06-08 20:19:35', 'house'),
(4, 14, 1, 1, 'Ababil Vila - ABABIL-VILA-222032-55', 'Car parking space in corner side', 2000.00, 0, 5, 0, 0, 'assets/uploads/verification/1780950032_verif_doc 1.jpg', 1, '2026-06-08 20:20:32', 'parking'),
(5, 15, 2, 2, 'Sunrise Vila - SUNRISE-VILA-222230-44', 'South face', 35000.00, 3, 4, 3, 2, 'assets/uploads/verification/1780950150_verif_doc 2.png', 1, '2026-06-08 20:22:30', 'house');

-- --------------------------------------------------------

--
-- Table structure for table `resident_vehicles`
--

CREATE TABLE `resident_vehicles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `apt_id` int(11) NOT NULL,
  `plate_number` varchar(30) NOT NULL,
  `vehicle_model` varchar(50) DEFAULT NULL,
  `vehicle_color` varchar(20) DEFAULT NULL,
  `rfid_tag_no` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resident_vehicles`
--

INSERT INTO `resident_vehicles` (`id`, `user_id`, `apt_id`, `plate_number`, `vehicle_model`, `vehicle_color`, `rfid_tag_no`) VALUES
(1, 6, 4, 'DHK ka 12-0001', NULL, NULL, NULL),
(2, 7, 5, 'DHK ka 12-0002', NULL, NULL, NULL),
(3, 8, 6, 'DHK ka 12-0003', NULL, NULL, NULL),
(4, 11, 17, 'DHK ka 12-1000', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`) VALUES
(1, 'Owner'),
(2, 'Resident'),
(3, 'Admin'),
(4, 'provider');

-- --------------------------------------------------------

--
-- Table structure for table `service_categories`
--

CREATE TABLE `service_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `icon_name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_categories`
--

INSERT INTO `service_categories` (`id`, `category_name`, `icon_name`) VALUES
(1, 'Electrician', 'zap'),
(2, 'Plumber', 'droplet'),
(3, 'Cleaner', 'sparkles'),
(4, 'Internet Provider', 'wifi'),
(5, 'Security Guard', 'shield-check'),
(6, 'Maid / Housekeeper', 'home');

-- --------------------------------------------------------

--
-- Table structure for table `service_providers`
--

CREATE TABLE `service_providers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `building_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `pricing_details` text DEFAULT NULL,
  `nid_number` varchar(20) DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `rating` decimal(3,2) DEFAULT 5.00,
  `availability_schedule` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `address` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `default_pricing` decimal(10,2) DEFAULT NULL,
  `coverage_radius` int(11) DEFAULT 5,
  `is_subscribed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_providers`
--

INSERT INTO `service_providers` (`id`, `user_id`, `category_id`, `building_id`, `name`, `phone`, `email`, `website_url`, `pricing_details`, `nid_number`, `image_path`, `rating`, `availability_schedule`, `is_active`, `address`, `latitude`, `longitude`, `default_pricing`, `coverage_radius`, `is_subscribed`) VALUES
(1, 4, 1, NULL, 'Ashfaq Ahmed Sakin', '01985550673', 'suchak9931@gmail.com', NULL, NULL, '3957238957293', '6a2714888014b.jpg', 5.00, '08:00 AM - 04:00 PM', 1, 'mirpur 11, Notunbazar dhaka', 23.81909590, 90.36528090, 500.00, 2, 0),
(2, 5, 6, NULL, 'Mr pevekif330', '01728476872', 'pevekif330@fanchatu.com', NULL, NULL, '5893726758927', '6a2714f5c8b87.jpg', 4.00, '10:00 AM - 06:00 PM', 1, 'Notunbazar dhaka', 23.79452780, 90.42615940, 1000.00, 2, 0),
(5, NULL, 2, 1, 'Mr Plumber', '01988764352', 'l29ha@web-library.net', '', '', '', 'default_avatar.jpg', 5.00, '08:00 AM - 06:00 PM', 1, NULL, NULL, NULL, NULL, 5, 0),
(6, NULL, 4, 1, 'Mr Person', '01764323532', 'rivgh@web-library.net', 'https://dotinternetbd.com/', '30Mbps - 600\r\n40Mbps - 800\r\n60Mbps - 1000\r\n80Mbps - 1200', '', '6a2727b7a3b34.jpg', 5.00, '10:00 AM - 08:00 PM', 1, NULL, NULL, NULL, NULL, 5, 0);

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `issue_title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('Low','Medium','High','Emergency') DEFAULT 'Low',
  `status` enum('Pending','Assigned','In Progress','Completed','Cancelled') DEFAULT 'Pending',
  `assigned_provider_id` int(11) DEFAULT NULL,
  `rating` int(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_requests`
--

INSERT INTO `service_requests` (`id`, `user_id`, `owner_id`, `issue_title`, `description`, `priority`, `status`, `assigned_provider_id`, `rating`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 'AC', 'I am new here and I need to install my AC.. kindly provide me a good electrician as I am new here', 'High', 'Pending', NULL, NULL, '2026-06-08 20:01:51', '2026-06-08 20:01:51');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `subscriber_type` enum('building','provider') NOT NULL,
  `subscriber_id` int(11) NOT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `duration_months` int(11) DEFAULT 1,
  `status` enum('trial','pending','active','expired','suspended') DEFAULT 'pending',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `assigned_by_admin` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `tran_id` varchar(100) DEFAULT NULL,
  `payment_key` varchar(100) DEFAULT NULL,
  `payment_verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `subscriber_type`, `subscriber_id`, `plan_id`, `duration_months`, `status`, `started_at`, `expires_at`, `assigned_by_admin`, `notes`, `tran_id`, `payment_key`, `payment_verified_at`, `created_at`, `updated_at`) VALUES
(1, 'building', 1, 1, 1, 'trial', '2026-06-08 19:21:41', '2026-06-23 01:21:41', NULL, NULL, NULL, NULL, NULL, '2026-06-08 19:21:41', '2026-06-08 19:21:41'),
(2, 'building', 2, 1, 1, 'trial', '2026-06-08 19:22:01', '2026-06-23 01:22:01', NULL, NULL, NULL, NULL, NULL, '2026-06-08 19:22:01', '2026-06-08 19:22:01'),
(3, 'building', 4, 1, 1, 'trial', '2026-06-10 06:14:20', '2026-06-24 12:14:20', NULL, NULL, NULL, NULL, NULL, '2026-06-10 06:14:20', '2026-06-10 06:14:20');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL,
  `plan_name` varchar(50) NOT NULL,
  `price_monthly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_residents` int(11) NOT NULL DEFAULT 10,
  `max_cameras` int(11) NOT NULL DEFAULT 2,
  `has_cctv` tinyint(1) NOT NULL DEFAULT 0,
  `has_analytics` tinyint(1) NOT NULL DEFAULT 0,
  `has_ai_chatbot` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `plan_name`, `price_monthly`, `max_residents`, `max_cameras`, `has_cctv`, `has_analytics`, `has_ai_chatbot`, `description`, `is_active`, `created_at`) VALUES
(1, 'Free Trial', 0.00, 5, 0, 0, 0, 0, '14-day free trial. Up to 5 residents, no CCTV or analytics.', 1, '2026-05-15 11:21:22'),
(2, 'Basic', 999.00, 30, 2, 1, 0, 0, 'For small buildings. Up to 30 residents, 2 CCTV cameras.', 1, '2026-05-15 11:21:22'),
(3, 'Pro', 2999.00, 100, 8, 1, 1, 0, 'For growing buildings. Up to 100 residents, 8 cameras, analytics dashboard.', 1, '2026-05-15 11:21:22'),
(4, 'Enterprise', 7999.00, 999, 50, 1, 1, 1, 'Unlimited scale. 50 cameras, AI chatbot, full analytics, priority support.', 1, '2026-05-15 11:21:22');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_code` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role_id`, `status`, `is_verified`, `verification_code`, `created_at`) VALUES
(1, 'ahnaftajwarsuchak', 'ahnaftajwarsuchak@gmail.com', '$2y$10$f9Qg0BZh.UERIJGMaVp4XOIM4At.E8ccmy2KEXl1D/WXeKzhKPTLW', 1, 'active', 1, NULL, '2026-06-08 19:03:50'),
(2, 'laradif519', 'laradif519@dosbee.com', '$2y$10$oJActYgUAI./fHC6hTgqVeuozU7iFGPtZNRZsbiQcrfKbcchNYPXa', 1, 'active', 1, NULL, '2026-06-08 19:07:58'),
(3, 'sifac84554', 'sifac84554@aspensif.com', '$2y$10$BxDqMTqkVX9IphnA2A/6YeFVBa0VqceUwsbemw1GWsVNun2u02eCW', 1, 'inactive', 1, NULL, '2026-06-08 19:11:59'),
(4, 'suchak9931', 'suchak9931@gmail.com', '$2y$10$.Uw0aTyx2i29Bg5TD4ZSTuaN9sMef2CpN8kuc91hev1GAoJdV1Aye', 4, 'active', 1, NULL, '2026-06-08 19:14:16'),
(5, 'pevekif330', 'pevekif330@fanchatu.com', '$2y$10$2buVpHz3IdNw540YktK.5.2j6To0vrUkVyXpo2L5myv.yE6QaVz/G', 4, 'active', 1, NULL, '2026-06-08 19:16:05'),
(6, 'suchak0630', 'suchak0630@gmail.com', '$2y$10$X8nMUIsX/ij5aoT9XJwkveyjclgcpXvyfzU0NoinuCEPMllUDjEzu', 2, 'active', 1, NULL, '2026-06-08 19:25:49'),
(7, 'cehine2713', 'cehine2713@fixscal.com', '$2y$10$GmeWsSo6JYkazIZXAMZwa.s8.cKV4Zet02qvGXacjDxkg1ZU2rpWG', 2, 'active', 1, NULL, '2026-06-08 19:28:23'),
(8, 'rivgh', 'rivgh@web-library.net', '$2y$10$YljFwCrRsdX0rzMVHFgVfORylHq/vcZ5y7xlxLD3eBpzbDxwtAiIS', 2, 'active', 1, NULL, '2026-06-08 19:30:59'),
(9, 'yabar71683', 'yabar71683@fanchatu.com', '$2y$10$fCn9K3vl1A2wpHhOTzHJ9ecV.NgOfBZTttshXomwMvZG0rtlLMfbG', 2, 'active', 1, NULL, '2026-06-08 19:34:09'),
(10, 'rasib92862', 'rasib92862@dosbee.com', '$2y$10$bYDUkZGjMdDBWyqu5fl0J.zTLiCDO6r8okkL88DVC0M6XBFQXyksi', 1, 'active', 1, NULL, '2026-06-10 06:13:05'),
(11, 'nfi8f', 'nfi8f@web-library.net', '$2y$10$v2h9wkNIYP9G67nTwoozvO27Us1fTCxcwDFG9DuvoLxX49QcQbQ3m', 2, 'active', 1, NULL, '2026-06-10 06:17:24');

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(11) DEFAULT NULL,
  `nid` varchar(13) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `profile_image` varchar(255) NOT NULL,
  `face_descriptor` text DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `blood_group` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`id`, `user_id`, `full_name`, `phone`, `nid`, `dob`, `occupation`, `permanent_address`, `profile_image`, `face_descriptor`, `is_verified`, `blood_group`, `address`, `latitude`, `longitude`) VALUES
(1, 1, 'Ahnaf Tajwar Suchak', '01301085365', '8726487236547', '1999-07-01', 'Student', 'Notunbazar, Dhaka', '6a271216901a5.png', NULL, 0, NULL, NULL, NULL, NULL),
(2, 2, 'laradif519', '01823877498', '3246728945781', '1999-12-04', 'Job Holder', 'Shewrapara, Dhaka', '6a27130e4b4db.jpg', NULL, 0, NULL, NULL, NULL, NULL),
(3, 3, 'Md sifac', '01735726394', '9375428937593', '1996-06-10', 'Engineer', 'Mirpur 11, Dhaka', '6a2713ff4c16b.jpg', NULL, 0, NULL, NULL, NULL, NULL),
(4, 4, 'Ashfaq Ahmed Sakin', '01985550673', '3957238957293', NULL, NULL, NULL, '6a2714888014b.jpg', NULL, 0, NULL, 'mirpur 11, Notunbazar dhaka', 23.81909590, 90.36528090),
(5, 5, 'Mr pevekif330', '01728476872', '5893726758927', NULL, NULL, NULL, '6a2714f5c8b87.jpg', NULL, 0, NULL, 'Notunbazar dhaka', 23.79452780, 90.42615940),
(6, 6, 'Suchak', '01896092628', '3756298357283', '2005-03-01', 'Student', 'Ababil Vila, 101/A, Notunbazar, Dhaka', '6a27173d237e5.jpg', NULL, 0, NULL, 'Ababil Vila, 101/A, Notunbazar, Dhaka', NULL, NULL),
(7, 7, 'Md Labib Ahsan', '01824971238', '5237749284728', '2002-09-12', 'Engineer', 'Ababil Vila, 101/A, Notunbazar, Dhaka', '6a2717d7868de.jpg', NULL, 0, NULL, 'Ababil Vila, 101/A, Notunbazar, Dhaka', NULL, NULL),
(8, 8, 'Md Rohan', '01998326759', '5932875293875', '2006-06-05', 'Student', 'Ababil Vila, 101/A, Notunbazar, Dhaka', '6a271873a93d1.jpeg', NULL, 0, NULL, 'Ababil Vila, 101/A, Notunbazar, Dhaka', NULL, NULL),
(9, 9, 'Badhan Chowdhury', '01672876128', '3298279857239', '2006-06-03', 'Engineer', 'Ababil Vila, 101/A, Notunbazar, Dhaka', '6a271931d9e6a.jpg', NULL, 0, '', '', NULL, NULL),
(10, 10, 'Mr. Owner', '01735466756', '5364243634646', '1999-12-08', 'Student', 'Mirpur 11, Dhaka', '6a2900718ec23.png', NULL, 0, NULL, NULL, NULL, NULL),
(11, 11, 'Mr. resident', '01735354385', '9827359283475', '2006-06-01', 'Student', 'Flower nibash, 105/A, Mirpur 11, Dhaka', '6a290174da1a8.jpg', NULL, 0, NULL, 'Flower nibash, 105/A, Mirpur 11, Dhaka', NULL, NULL);

--
-- Triggers `user_profiles`
--
DELIMITER $$
CREATE TRIGGER `before_insert_user_profiles` BEFORE INSERT ON `user_profiles` FOR EACH ROW BEGIN
    IF NEW.phone IS NOT NULL AND NEW.phone NOT REGEXP '^[0-9]{11}$' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Phone number must be exactly 11 digits';
    END IF;
    
    IF NEW.nid IS NOT NULL AND NEW.nid NOT REGEXP '^[0-9]{13}$' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'NID must be exactly 13 digits';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_update_user_profiles` BEFORE UPDATE ON `user_profiles` FOR EACH ROW BEGIN
    IF NEW.phone IS NOT NULL AND NEW.phone NOT REGEXP '^[0-9]{11}$' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Phone number must be exactly 11 digits';
    END IF;
    
    IF NEW.nid IS NOT NULL AND NEW.nid NOT REGEXP '^[0-9]{13}$' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'NID must be exactly 13 digits';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `utility_types`
--

CREATE TABLE `utility_types` (
  `id` int(11) NOT NULL,
  `utility_name` varchar(50) DEFAULT NULL,
  `provider_api_url` varchar(255) DEFAULT NULL,
  `is_fixed_rate` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `utility_types`
--

INSERT INTO `utility_types` (`id`, `utility_name`, `provider_api_url`, `is_fixed_rate`) VALUES
(1, 'Water bill', NULL, NULL),
(2, 'Electricity bill', NULL, NULL),
(3, 'House rent', NULL, NULL),
(4, 'Gas bill', NULL, NULL),
(5, 'Other', NULL, NULL),
(6, 'Parking rent', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `visit_requests`
--

CREATE TABLE `visit_requests` (
  `id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `apt_id` int(11) NOT NULL,
  `purpose` varchar(150) DEFAULT NULL,
  `digital_pass_code` varchar(50) DEFAULT NULL,
  `status` enum('Pending','Approved','Cancelled','Expired') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visit_requests`
--

INSERT INTO `visit_requests` (`id`, `guest_id`, `resident_id`, `apt_id`, `purpose`, `digital_pass_code`, `status`) VALUES
(1, 1, 6, 4, 'Tutor', '6D5DCC9A', 'Approved'),
(2, 2, 6, 4, 'Maintenence', '75B4D520', 'Approved'),
(3, 3, 8, 6, 'Entry', '11853EB0', 'Approved');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `apartments`
--
ALTER TABLE `apartments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `building_apt` (`building_id`,`apt_number`),
  ADD KEY `building_id` (`building_id`);

--
-- Indexes for table `apartment_assignments`
--
ALTER TABLE `apartment_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `apt_id` (`apt_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `apt_id` (`apt_id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `utility_type_id` (`utility_type_id`);

--
-- Indexes for table `buildings`
--
ALTER TABLE `buildings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `building_managers`
--
ALTER TABLE `building_managers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `building_id` (`building_id`);

--
-- Indexes for table `cctv_alerts`
--
ALTER TABLE `cctv_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `capture_id` (`capture_id`);

--
-- Indexes for table `cctv_captures`
--
ALTER TABLE `cctv_captures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `camera_id` (`camera_id`),
  ADD KEY `apt_id` (`apt_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cctv_devices`
--
ALTER TABLE `cctv_devices`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `community_categories`
--
ALTER TABLE `community_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `community_comments`
--
ALTER TABLE `community_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `apt_id` (`apt_id`),
  ADD KEY `parent_comment_id` (`parent_comment_id`);

--
-- Indexes for table `community_posts`
--
ALTER TABLE `community_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `apt_id` (`apt_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `community_post_images`
--
ALTER TABLE `community_post_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `target_user_id` (`target_user_id`);

--
-- Indexes for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_profile_id` (`user_profile_id`),
  ADD KEY `apt_id` (`apt_id`);

--
-- Indexes for table `entry_logs`
--
ALTER TABLE `entry_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visit_id` (`visit_id`);

--
-- Indexes for table `family_members`
--
ALTER TABLE `family_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `primary_user_id` (`primary_user_id`),
  ADD KEY `apt_id` (`apt_id`);

--
-- Indexes for table `guests`
--
ALTER TABLE `guests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone_number` (`phone_number`);

--
-- Indexes for table `guest_vehicles`
--
ALTER TABLE `guest_vehicles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visit_id` (`visit_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `parking_details`
--
ALTER TABLE `parking_details`
  ADD PRIMARY KEY (`listing_id`);

--
-- Indexes for table `parking_requests`
--
ALTER TABLE `parking_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `slot_id` (`slot_id`),
  ADD KEY `requester_id` (`requester_id`),
  ADD KEY `target_resident_id` (`target_resident_id`),
  ADD KEY `parking_req_ibfk_bldg` (`building_id`);

--
-- Indexes for table `parking_slots`
--
ALTER TABLE `parking_slots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `building_slot` (`building_id`,`slot_number`),
  ADD KEY `apt_id` (`apt_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_id` (`bill_id`);

--
-- Indexes for table `personal_messages`
--
ALTER TABLE `personal_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `provider_bookings`
--
ALTER TABLE `provider_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `provider_locations`
--
ALTER TABLE `provider_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`);

--
-- Indexes for table `provider_reviews`
--
ALTER TABLE `provider_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `provider_subscription_plans`
--
ALTER TABLE `provider_subscription_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rental_images`
--
ALTER TABLE `rental_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `listing_id` (`listing_id`);

--
-- Indexes for table `rental_listings`
--
ALTER TABLE `rental_listings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `apt_id` (`apt_id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `resident_vehicles`
--
ALTER TABLE `resident_vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `apt_id` (`apt_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_categories`
--
ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_providers`
--
ALTER TABLE `service_providers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `owner_id` (`building_id`);

--
-- Indexes for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_provider_id` (`assigned_provider_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_subscriber` (`subscriber_type`,`subscriber_id`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `idx_login_email` (`email`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `idx_unique_phone` (`phone`),
  ADD UNIQUE KEY `idx_unique_nid` (`nid`);

--
-- Indexes for table `utility_types`
--
ALTER TABLE `utility_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `visit_requests`
--
ALTER TABLE `visit_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `digital_pass_code` (`digital_pass_code`),
  ADD KEY `guest_id` (`guest_id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `apt_id` (`apt_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `apartments`
--
ALTER TABLE `apartments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `apartment_assignments`
--
ALTER TABLE `apartment_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `bill_items`
--
ALTER TABLE `bill_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `buildings`
--
ALTER TABLE `buildings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `building_managers`
--
ALTER TABLE `building_managers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cctv_alerts`
--
ALTER TABLE `cctv_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `cctv_captures`
--
ALTER TABLE `cctv_captures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `cctv_devices`
--
ALTER TABLE `cctv_devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `community_categories`
--
ALTER TABLE `community_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `community_comments`
--
ALTER TABLE `community_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `community_posts`
--
ALTER TABLE `community_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `community_post_images`
--
ALTER TABLE `community_post_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entry_logs`
--
ALTER TABLE `entry_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `family_members`
--
ALTER TABLE `family_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guests`
--
ALTER TABLE `guests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `guest_vehicles`
--
ALTER TABLE `guest_vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `parking_requests`
--
ALTER TABLE `parking_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `parking_slots`
--
ALTER TABLE `parking_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `personal_messages`
--
ALTER TABLE `personal_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `provider_bookings`
--
ALTER TABLE `provider_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `provider_locations`
--
ALTER TABLE `provider_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `provider_reviews`
--
ALTER TABLE `provider_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `provider_subscription_plans`
--
ALTER TABLE `provider_subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rental_images`
--
ALTER TABLE `rental_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `rental_listings`
--
ALTER TABLE `rental_listings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `resident_vehicles`
--
ALTER TABLE `resident_vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `service_providers`
--
ALTER TABLE `service_providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `utility_types`
--
ALTER TABLE `utility_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `visit_requests`
--
ALTER TABLE `visit_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `apartments`
--
ALTER TABLE `apartments`
  ADD CONSTRAINT `apartments_ibfk_1` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `apartment_assignments`
--
ALTER TABLE `apartment_assignments`
  ADD CONSTRAINT `apartment_assignments_ibfk_1` FOREIGN KEY (`apt_id`) REFERENCES `apartments` (`id`),
  ADD CONSTRAINT `apartment_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`apt_id`) REFERENCES `apartments` (`id`),
  ADD CONSTRAINT `bills_ibfk_2` FOREIGN KEY (`resident_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD CONSTRAINT `bill_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bill_items_ibfk_2` FOREIGN KEY (`utility_type_id`) REFERENCES `utility_types` (`id`);

--
-- Constraints for table `building_managers`
--
ALTER TABLE `building_managers`
  ADD CONSTRAINT `building_managers_ibfk_1` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cctv_alerts`
--
ALTER TABLE `cctv_alerts`
  ADD CONSTRAINT `cctv_alerts_ibfk_1` FOREIGN KEY (`capture_id`) REFERENCES `cctv_captures` (`id`);

--
-- Constraints for table `cctv_captures`
--
ALTER TABLE `cctv_captures`
  ADD CONSTRAINT `cctv_captures_ibfk_1` FOREIGN KEY (`camera_id`) REFERENCES `cctv_devices` (`id`),
  ADD CONSTRAINT `cctv_captures_ibfk_3` FOREIGN KEY (`apt_id`) REFERENCES `apartments` (`id`),
  ADD CONSTRAINT `cctv_captures_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `community_comments`
--
ALTER TABLE `community_comments`
  ADD CONSTRAINT `community_comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `community_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `community_comments_ibfk_3` FOREIGN KEY (`apt_id`) REFERENCES `apartments` (`id`),
  ADD CONSTRAINT `community_comments_ibfk_4` FOREIGN KEY (`parent_comment_id`) REFERENCES `community_comments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_posts`
--
ALTER TABLE `community_posts`
  ADD CONSTRAINT `community_posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `community_posts_ibfk_2` FOREIGN KEY (`apt_id`) REFERENCES `apartments` (`id`),
  ADD CONSTRAINT `community_posts_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `community_categories` (`id`);

--
-- Constraints for table `community_post_images`
--
ALTER TABLE `community_post_images`
  ADD CONSTRAINT `fk_community_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coupons`
--
ALTER TABLE `coupons`
  ADD CONSTRAINT `coupons_ibfk_1` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  ADD CONSTRAINT `emergency_contacts_ibfk_1` FOREIGN KEY (`user_profile_id`) REFERENCES `user_profiles` (`id`),
  ADD CONSTRAINT `emergency_contacts_ibfk_2` FOREIGN KEY (`apt_id`) REFERENCES `apartments` (`id`);

--
-- Constraints for table `family_members`
--
ALTER TABLE `family_members`
  ADD CONSTRAINT `family_members_ibfk_1` FOREIGN KEY (`primary_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `family_members_ibfk_2` FOREIGN KEY (`apt_id`) REFERENCES `apartments` (`id`);

--
-- Constraints for table `guest_vehicles`
--
ALTER TABLE `guest_vehicles`
  ADD CONSTRAINT `guest_vehicles_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visit_requests` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parking_details`
--
ALTER TABLE `parking_details`
  ADD CONSTRAINT `fk_parking_listing` FOREIGN KEY (`listing_id`) REFERENCES `rental_listings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parking_requests`
--
ALTER TABLE `parking_requests`
  ADD CONSTRAINT `parking_req_ibfk_bldg` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `parking_requests_ibfk_1` FOREIGN KEY (`slot_id`) REFERENCES `parking_slots` (`id`),
  ADD CONSTRAINT `parking_requests_ibfk_2` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `parking_requests_ibfk_3` FOREIGN KEY (`target_resident_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `parking_slots`
--
ALTER TABLE `parking_slots`
  ADD CONSTRAINT `parking_slots_ibfk_1` FOREIGN KEY (`apt_id`) REFERENCES `apartments` (`id`),
  ADD CONSTRAINT `parking_slots_ibfk_bldg` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`);

--
-- Constraints for table `personal_messages`
--
ALTER TABLE `personal_messages`
  ADD CONSTRAINT `personal_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `personal_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_bookings`
--
ALTER TABLE `provider_bookings`
  ADD CONSTRAINT `provider_bookings_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`),
  ADD CONSTRAINT `provider_bookings_ibfk_2` FOREIGN KEY (`resident_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `provider_locations`
--
ALTER TABLE `provider_locations`
  ADD CONSTRAINT `provider_locations_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_reviews`
--
ALTER TABLE `provider_reviews`
  ADD CONSTRAINT `provider_reviews_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provider_reviews_ibfk_2` FOREIGN KEY (`resident_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rental_images`
--
ALTER TABLE `rental_images`
  ADD CONSTRAINT `rental_images_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `rental_listings` (`id`);

--
-- Constraints for table `rental_listings`
--
ALTER TABLE `rental_listings`
  ADD CONSTRAINT `rental_listings_ibfk_1` FOREIGN KEY (`apt_id`) REFERENCES `apartments` (`id`),
  ADD CONSTRAINT `rental_listings_ibfk_2` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `resident_vehicles`
--
ALTER TABLE `resident_vehicles`
  ADD CONSTRAINT `resident_vehicles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `resident_vehicles_ibfk_2` FOREIGN KEY (`apt_id`) REFERENCES `apartments` (`id`);

--
-- Constraints for table `service_providers`
--
ALTER TABLE `service_providers`
  ADD CONSTRAINT `service_providers_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`),
  ADD CONSTRAINT `service_providers_ibfk_2` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`);

--
-- Constraints for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD CONSTRAINT `service_requests_ibfk_1` FOREIGN KEY (`assigned_provider_id`) REFERENCES `service_providers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visit_requests`
--
ALTER TABLE `visit_requests`
  ADD CONSTRAINT `visit_requests_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`),
  ADD CONSTRAINT `visit_requests_ibfk_2` FOREIGN KEY (`resident_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `visit_requests_ibfk_3` FOREIGN KEY (`apt_id`) REFERENCES `apartments` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
