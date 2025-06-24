-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 23, 2025 at 08:56 PM
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
-- Database: `jewelentrypro`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_transactions`
--

CREATE TABLE `account_transactions` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `transaction_type` enum('Sale','Purchase','Add Stock','Manufacturing','Expense','Adjustment') NOT NULL,
  `transaction_date` date NOT NULL DEFAULT curdate(),
  `reference_no` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `debit_credit` enum('Debit','Credit') NOT NULL,
  `payment_mode` varchar(50) DEFAULT NULL,
  `payment_status` enum('Paid','Unpaid','Partial') DEFAULT 'Paid',
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `due_amount` decimal(15,2) GENERATED ALWAYS AS (`amount` - `paid_amount`) STORED,
  `supplier_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `material_type` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `coupon_code` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_purchase_amount` decimal(10,2) DEFAULT NULL,
  `max_discount_amount` decimal(10,2) DEFAULT NULL COMMENT 'Max discount for percentage type coupons',
  `start_date` datetime DEFAULT NULL,
  `expiry_date` datetime DEFAULT NULL,
  `usage_limit_total` int(11) DEFAULT NULL COMMENT 'Total times this coupon can be used overall',
  `usage_limit_customer` int(11) DEFAULT 1 COMMENT 'Times one customer can use this coupon',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `gst_applicability` enum('any','gst_only','non_gst_only') NOT NULL DEFAULT 'any',
  `coupon_purpose` enum('general','welcome','post_purchase_reward') NOT NULL DEFAULT 'general' COMMENT 'Helps identify special coupons for automation',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores all coupon definitions and rules';

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `firm_id`, `coupon_code`, `description`, `discount_type`, `discount_value`, `min_purchase_amount`, `max_discount_amount`, `start_date`, `expiry_date`, `usage_limit_total`, `usage_limit_customer`, `is_active`, `gst_applicability`, `coupon_purpose`, `created_at`, `updated_at`) VALUES
(1, 41, 'WELCOME10', 'Welcome discount for new customers', 'percentage', 10.00, NULL, NULL, '2025-06-03 00:00:00', '2099-12-31 00:00:00', NULL, 1, 1, 'any', 'general', '2025-06-03 14:03:16', '2025-06-03 14:03:16'),
(2, 41, 'DIWALI2025_01', 'Happy Diwali! 10% off on your next purchase.', 'percentage', 10.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(3, 41, 'DIWALI2025_02', 'Brighten your next purchase with 10% off this Diwali!', 'percentage', 10.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(4, 41, 'DIWALI2025_03', 'Sparkle more! Get 10% off with Diwali Coupon.', 'percentage', 10.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(5, 41, 'DIWALI2025_04', 'Celebrate with 10% off your next jewelry.', 'percentage', 10.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(6, 41, 'EIDMUBARAK_05', 'Eid Mubarak! Enjoy 8% off your next purchase.', 'percentage', 8.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(7, 41, 'EIDMUBARAK_06', 'Special Eid treat: 8% off for you!', 'percentage', 8.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(8, 41, 'EIDMUBARAK_07', 'Shine bright this Eid with 8% discount.', 'percentage', 8.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(9, 41, 'XMASJOY2025_08', 'Merry Christmas! 12% off your festive buys.', 'percentage', 12.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(10, 41, 'XMASJOY2025_09', 'Ho Ho Ho! 12% discount for a joyful Christmas.', 'percentage', 12.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(11, 41, 'XMASJOY2025_10', 'Spread the cheer! 12% off this holiday season.', 'percentage', 12.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(12, 41, 'XMASJOY2025_11', 'Christmas magic! Get 12% off.', 'percentage', 12.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(13, 41, 'NEWYEAR2026_12', 'Happy New Year! Start fresh with 7% off.', 'percentage', 7.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(14, 41, 'NEWYEAR2026_13', 'New Year, New Sparkle! 7% discount for you.', 'percentage', 7.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(15, 41, 'NEWYEAR2026_14', 'Ring in the New Year with 7% off!', 'percentage', 7.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(16, 41, 'VALENTINE_15', 'Love is in the air! 9% off for Valentine\'s.', 'percentage', 9.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(17, 41, 'VALENTINE_16', 'Spread love with 9% off your next gift.', 'percentage', 9.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(18, 41, 'HOLI_17', 'Add colors to your joy! 5% off this Holi.', 'percentage', 5.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(19, 41, 'HOLI_18', 'A colorful treat: 5% off for Holi!', 'percentage', 5.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(20, 41, 'SPRING_19', 'Spring into savings! Get 6% off.', 'percentage', 6.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(21, 41, 'SUMMER_20', 'Summer special: 7% off your dazzling picks.', 'percentage', 7.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(22, 41, 'AUTUMN_21', 'Fall for these savings! 8% off for Autumn.', 'percentage', 8.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(23, 41, 'WINTER_22', 'Warm up with 9% off this Winter season.', 'percentage', 9.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(24, 41, 'ANNIVERSARY_23', 'Happy Anniversary! Enjoy 15% off.', 'percentage', 15.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(25, 41, 'BIRTHDAY_24', 'Birthday surprise! 10% off just for you.', 'percentage', 10.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(26, 41, 'FESTIVE_25', 'Festive cheer! Get 11% off.', 'percentage', 11.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(27, 41, 'FESTIVE_26', 'Extra festive discount: 11% off!', 'percentage', 11.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(28, 41, 'FESTIVE_27', 'Your special festive reward: 11% off.', 'percentage', 11.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(29, 41, 'FESTIVE_28', 'Joyful savings: 11% off for the festive season.', 'percentage', 11.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(30, 41, 'SPECIAL_29', 'A special thank you: 5% off!', 'percentage', 5.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(31, 41, 'SPECIAL_30', 'Your reward coupon: 5% off.', 'percentage', 5.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(32, 41, 'SPECIAL_31', 'Exclusive offer: 5% discount.', 'percentage', 5.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(33, 41, 'BONUS_32', 'Bonus savings! 7% off for being a valued customer.', 'percentage', 7.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(34, 41, 'BONUS_33', 'Your loyalty bonus: 7% off.', 'percentage', 7.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(35, 41, 'BONUS_34', 'Extra 7% off on your next purchase!', 'percentage', 7.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(36, 41, 'VIP_35', 'VIP treat: 15% off for our esteemed customers.', 'percentage', 15.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(37, 41, 'VIP_36', 'Thank you for being a VIP! Enjoy 15% off.', 'percentage', 15.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(38, 41, 'VIP_37', 'Your exclusive 15% VIP discount.', 'percentage', 15.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(39, 41, 'SEASONAL_38', 'Seasonal special: 8% off!', 'percentage', 8.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(40, 41, 'SEASONAL_39', 'Enjoy 8% off this season.', 'percentage', 8.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(41, 41, 'SEASONAL_40', 'Unlock 8% seasonal savings.', 'percentage', 8.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(42, 41, 'LIMITED_41', 'Limited time reward: 10% off.', 'percentage', 10.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(43, 41, 'LIMITED_42', 'Your special 10% limited reward.', 'percentage', 10.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(44, 41, 'THANKYOU_43', 'Thank you for your purchase! 6% off.', 'percentage', 6.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(45, 41, 'THANKYOU_44', 'Appreciation reward: 6% discount.', 'percentage', 6.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(46, 41, 'GIFT_45', 'A gift for you: 9% off your next purchase.', 'percentage', 9.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(47, 41, 'GIFT_46', 'Your special 9% discount gift.', 'percentage', 9.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(48, 41, 'CELEBRATE_47', 'Celebrate with us! 10% off.', 'percentage', 10.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(49, 41, 'CELEBRATE_48', 'Celebrate your next purchase with 10% off!', 'percentage', 10.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(50, 41, 'SHINE_49', 'Shine bright with 10% off.', 'percentage', 10.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09'),
(51, 41, 'SPARKLE_50', 'Add some sparkle! 10% discount awaits.', 'percentage', 10.00, NULL, NULL, '2024-06-15 00:00:00', '2030-12-31 23:59:59', NULL, 1, 1, 'any', 'post_purchase_reward', '2025-06-14 08:25:09', '2025-06-14 08:25:09');

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `FirstName` varchar(100) NOT NULL,
  `LastName` varchar(100) NOT NULL,
  `Email` varchar(50) NOT NULL,
  `PhoneNumber` varchar(20) NOT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `City` varchar(100) DEFAULT NULL,
  `State` varchar(100) DEFAULT NULL,
  `PostalCode` varchar(20) DEFAULT NULL,
  `Country` varchar(100) DEFAULT 'India',
  `DateOfBirth` date DEFAULT NULL,
  `SpecialDay` date NOT NULL,
  `Gender` enum('Male','Female','Other') DEFAULT NULL,
  `CustomerType` enum('Lead','Prospect','Customer','VIP') DEFAULT 'Lead',
  `PANNumber` varchar(10) DEFAULT NULL,
  `AadhaarNumber` varchar(12) DEFAULT NULL,
  `IsGSTRegistered` tinyint(1) DEFAULT 0,
  `GSTNumber` varchar(15) DEFAULT NULL,
  `CustomerImage` longblob DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `UpdatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`id`, `firm_id`, `FirstName`, `LastName`, `Email`, `PhoneNumber`, `Address`, `City`, `State`, `PostalCode`, `Country`, `DateOfBirth`, `SpecialDay`, `Gender`, `CustomerType`, `PANNumber`, `AadhaarNumber`, `IsGSTRegistered`, `GSTNumber`, `CustomerImage`, `CreatedAt`, `UpdatedAt`) VALUES
(1, 1, 'Jewel Entry ', 'Demo', 'demo@gmail.com', '9810359334', 'Lalgola', 'Murshidabad', 'West Bengle', '742148', 'India', '1991-02-25', '2025-05-31', 'Male', 'Lead', 'null', 'null', 0, 'null', 0x75706c6f6164732f637573746f6d65725f696d616765732f637573746f6d65725f363833383736333937393539342e6a7067, '2025-05-29 15:27:27', '2025-05-29 20:29:05'),
(2, 1, 'sunita', 'haldar', '', '9263926337', '', '', '', '', 'India', '2025-05-31', '0000-00-00', 'Female', 'Lead', '', '', 0, NULL, NULL, '2025-05-29 17:06:56', '2025-05-29 17:06:56'),
(3, 1, 'Demo', 'User3', '', '9814563298', '', '', '', '', 'India', '2025-05-30', '0000-00-00', 'Male', 'Lead', '', '', 0, NULL, NULL, '2025-05-29 18:17:35', '2025-05-29 18:17:35'),
(4, 41, 'First', 'Customer', 'Demo@gmail.com', '9810359334', '', 'Delhi', 'Delhi', '', 'India', '2003-02-25', '0000-00-00', 'Female', 'Lead', '', '', 0, NULL, NULL, '2025-05-30 19:50:15', '2025-05-30 20:28:07'),
(5, 41, 'customer', 'halder', '', '9891582245', '', '', '', '', 'India', '0000-00-00', '0000-00-00', '', 'Lead', '', '', 0, NULL, NULL, '2025-05-30 20:28:53', '2025-05-30 20:28:53'),
(37, 41, 'sunita', 'halder', '', '98653212', '', '', '', '', 'India', NULL, '0000-00-00', NULL, 'Lead', NULL, NULL, 0, '', NULL, '2025-06-03 18:01:48', '2025-06-03 18:01:48'),
(38, 41, 'customer', '1', '', '9810361236', '', '', '', '', 'India', NULL, '0000-00-00', NULL, 'Lead', NULL, NULL, 0, '', NULL, '2025-06-03 18:52:56', '2025-06-03 18:52:56'),
(47, 41, 'Rontik', 'Halder', '', '9891582241', '', '', '', '', 'India', NULL, '0000-00-00', NULL, 'Lead', NULL, NULL, 0, '', NULL, '2025-06-03 19:42:53', '2025-06-03 19:42:53'),
(48, 41, 'DEPAK', 'KUMAR', 'DEMO@GMAIL.COM', '9810359332', '', '', '', '', 'India', NULL, '0000-00-00', NULL, 'Lead', NULL, NULL, 0, '', NULL, '2025-06-14 12:56:42', '2025-06-14 12:56:42'),
(49, 41, 'rontik', '', '', '9810359331', '', '', '', '', 'India', NULL, '0000-00-00', NULL, 'Lead', NULL, NULL, 0, '', NULL, '2025-06-14 21:59:22', '2025-06-14 21:59:22'),
(50, 41, 'hhh', '', '', '9898653212', '', '', '', '', 'India', NULL, '0000-00-00', NULL, 'Lead', NULL, NULL, 0, '', NULL, '2025-06-14 22:02:55', '2025-06-14 22:02:55'),
(51, 44, 'demo', '', 'mahalaxmih@gmail.com', '7898652312', '', '', '', '', 'India', NULL, '0000-00-00', NULL, 'Lead', NULL, NULL, 0, '', NULL, '2025-06-22 14:12:10', '2025-06-22 14:12:10'),
(52, 44, 'SUNITA', 'HALDAR', '', '9263926337', '', '', '', '', 'India', NULL, '0000-00-00', NULL, 'Lead', NULL, NULL, 0, '', NULL, '2025-06-23 00:04:22', '2025-06-23 00:04:22');

-- --------------------------------------------------------

--
-- Table structure for table `customer_assigned_coupons`
--

CREATE TABLE `customer_assigned_coupons` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `coupon_id` int(11) NOT NULL,
  `times_used` int(11) NOT NULL DEFAULT 0,
  `assigned_date` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('available','used','expired_for_customer','revoked') NOT NULL DEFAULT 'available' COMMENT 'Status specific to this customer for this coupon',
  `last_used_date` datetime DEFAULT NULL,
  `related_sale_id` int(11) DEFAULT NULL COMMENT 'If coupon was earned from a specific sale (e.g., scratch card)',
  `firm_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links customers to coupons and tracks their usage';

--
-- Dumping data for table `customer_assigned_coupons`
--

INSERT INTO `customer_assigned_coupons` (`id`, `customer_id`, `coupon_id`, `times_used`, `assigned_date`, `status`, `last_used_date`, `related_sale_id`, `firm_id`) VALUES
(1, 44, 1, 0, '2025-06-03 14:08:05', 'available', NULL, NULL, 1),
(2, 45, 1, 0, '2025-06-03 14:08:38', 'available', NULL, NULL, 1),
(3, 37, 1, 1, '2025-06-03 14:09:39', 'available', '2025-06-14 06:54:38', NULL, 1),
(4, 47, 1, 1, '2025-06-03 14:12:53', 'available', '2025-06-14 07:13:34', NULL, 41),
(5, 48, 1, 1, '2025-06-14 07:26:42', 'used', '2025-06-14 13:23:27', NULL, 41),
(6, 37, 2, 0, '2025-06-14 10:32:26', 'available', NULL, 297, 41);

-- --------------------------------------------------------

--
-- Table structure for table `customer_gold_plans`
--

CREATE TABLE `customer_gold_plans` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `enrollment_date` date NOT NULL,
  `maturity_date` date NOT NULL,
  `current_status` enum('active','matured','closed','defaulted','cancelled') NOT NULL DEFAULT 'active',
  `total_amount_paid` decimal(12,2) DEFAULT 0.00,
  `total_gold_accrued` decimal(10,4) DEFAULT 0.0000,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_gold_plans`
--

INSERT INTO `customer_gold_plans` (`id`, `firm_id`, `customer_id`, `plan_id`, `enrollment_date`, `maturity_date`, `current_status`, `total_amount_paid`, `total_gold_accrued`, `notes`, `created_at`, `updated_at`) VALUES
(4, 44, 52, 8, '2025-06-22', '2026-06-22', 'active', 23000.00, 2.6474, '', '2025-06-22 18:51:37', '2025-06-22 18:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `customer_orders`
--

CREATE TABLE `customer_orders` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `karigar_id` int(11) DEFAULT NULL,
  `order_no` varchar(50) NOT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `design_reference` text DEFAULT NULL,
  `product_type` varchar(50) DEFAULT NULL,
  `gross_weight` decimal(10,3) DEFAULT NULL,
  `net_weight` decimal(10,3) DEFAULT NULL,
  `purity` varchar(10) DEFAULT NULL,
  `expected_delivery` date DEFAULT NULL,
  `advance_amount` decimal(10,2) DEFAULT 0.00,
  `advance_used` int(11) NOT NULL,
  `customer_orders` date NOT NULL,
  `total_estimated` decimal(10,2) DEFAULT NULL,
  `status` enum('Pending','In Progress','Ready','Delivered','Cancelled') DEFAULT 'Pending',
  `delivery_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `advance_used_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_orders`
--

INSERT INTO `customer_orders` (`id`, `firm_id`, `customer_id`, `karigar_id`, `order_no`, `item_name`, `design_reference`, `product_type`, `gross_weight`, `net_weight`, `purity`, `expected_delivery`, `advance_amount`, `advance_used`, `customer_orders`, `total_estimated`, `status`, `delivery_date`, `remarks`, `created_at`, `updated_at`, `advance_used_date`) VALUES
(1, 1, 18, 3, 'OD-001', 'JHUMKA', 'JHUMKA', 'EARING', 12.600, 12.600, '92.0', '2025-05-06', 50000.00, 50000, '0000-00-00', 169800.00, 'Delivered', '2025-05-07', NULL, '2025-05-05 08:03:15', '2025-05-20 08:09:22', '2025-05-05'),
(3, 1, 19, 2, 'OD-002', 'RING', 'NULL', 'RING', 5.900, 5.900, '92.00', '2025-05-20', 5000.00, 5000, '2025-05-01', 89000.00, 'Delivered', NULL, 'DFGF', '2025-05-20 07:47:42', '2025-05-20 08:09:31', '2025-05-20'),
(7, 1, 21, NULL, 'OD-003', 'RING', 'FDSFS', 'Necklace', 5.600, 5.000, '92.0', '2025-06-03', 5800.00, 0, '0000-00-00', 82000.00, 'Pending', NULL, '', '2025-05-20 11:21:16', '2025-05-20 11:21:16', '0000-00-00'),
(8, 1, 21, NULL, 'OD-003', 'RING', 'FDSFS', 'Necklace', 5.600, 5.000, '92.0', '2025-06-03', 5800.00, 0, '0000-00-00', 82000.00, 'Pending', NULL, '', '2025-05-20 11:21:17', '2025-05-20 11:21:17', '0000-00-00'),
(9, 1, 21, NULL, 'OD-003', 'RING', 'FDSFS', 'Necklace', 5.600, 5.000, '92.0', '2025-06-03', 5800.00, 0, '0000-00-00', 82000.00, 'Pending', NULL, '', '2025-05-20 11:21:21', '2025-05-20 11:21:21', '0000-00-00'),
(11, 1, 18, 1, 'OD-004', 'ring', 'dasd', 'Necklace', 5.900, 9.600, '92.0', '2025-06-03', 50000.00, 0, '0000-00-00', 100000.00, 'Pending', NULL, '', '2025-05-20 11:24:26', '2025-05-20 11:24:26', '0000-00-00'),
(12, 1, 26, 1, 'OD-005', 'SET', 'RANI HAR', 'Necklace', 10.690, 10.600, '92.0', '2025-06-03', 50000.00, 0, '0000-00-00', 65560.00, 'In Progress', NULL, '', '2025-05-20 11:28:50', '2025-05-20 11:35:16', '0000-00-00'),
(1, 1, 18, 3, 'OD-001', 'JHUMKA', 'JHUMKA', 'EARING', 12.600, 12.600, '92.0', '2025-05-06', 50000.00, 50000, '0000-00-00', 169800.00, 'Delivered', '2025-05-07', NULL, '2025-05-05 08:03:15', '2025-05-20 08:09:22', '2025-05-05'),
(3, 1, 19, 2, 'OD-002', 'RING', 'NULL', 'RING', 5.900, 5.900, '92.00', '2025-05-20', 5000.00, 5000, '2025-05-01', 89000.00, 'Delivered', NULL, 'DFGF', '2025-05-20 07:47:42', '2025-05-20 08:09:31', '2025-05-20'),
(7, 1, 21, NULL, 'OD-003', 'RING', 'FDSFS', 'Necklace', 5.600, 5.000, '92.0', '2025-06-03', 5800.00, 0, '0000-00-00', 82000.00, 'Pending', NULL, '', '2025-05-20 11:21:16', '2025-05-20 11:21:16', '0000-00-00'),
(8, 1, 21, NULL, 'OD-003', 'RING', 'FDSFS', 'Necklace', 5.600, 5.000, '92.0', '2025-06-03', 5800.00, 0, '0000-00-00', 82000.00, 'Pending', NULL, '', '2025-05-20 11:21:17', '2025-05-20 11:21:17', '0000-00-00'),
(9, 1, 21, NULL, 'OD-003', 'RING', 'FDSFS', 'Necklace', 5.600, 5.000, '92.0', '2025-06-03', 5800.00, 0, '0000-00-00', 82000.00, 'Pending', NULL, '', '2025-05-20 11:21:21', '2025-05-20 11:21:21', '0000-00-00'),
(11, 1, 18, 1, 'OD-004', 'ring', 'dasd', 'Necklace', 5.900, 9.600, '92.0', '2025-06-03', 50000.00, 0, '0000-00-00', 100000.00, 'Pending', NULL, '', '2025-05-20 11:24:26', '2025-05-20 11:24:26', '0000-00-00'),
(12, 1, 26, 1, 'OD-005', 'SET', 'RANI HAR', 'Necklace', 10.690, 10.600, '92.0', '2025-06-03', 50000.00, 0, '0000-00-00', 65560.00, 'In Progress', NULL, '', '2025-05-20 11:28:50', '2025-05-20 11:35:16', '0000-00-00'),
(1, 1, 18, 3, 'OD-001', 'JHUMKA', 'JHUMKA', 'EARING', 12.600, 12.600, '92.0', '2025-05-06', 50000.00, 50000, '0000-00-00', 169800.00, 'Delivered', '2025-05-07', NULL, '2025-05-05 08:03:15', '2025-05-20 08:09:22', '2025-05-05'),
(3, 1, 19, 2, 'OD-002', 'RING', 'NULL', 'RING', 5.900, 5.900, '92.00', '2025-05-20', 5000.00, 5000, '2025-05-01', 89000.00, 'Delivered', NULL, 'DFGF', '2025-05-20 07:47:42', '2025-05-20 08:09:31', '2025-05-20'),
(7, 1, 21, NULL, 'OD-003', 'RING', 'FDSFS', 'Necklace', 5.600, 5.000, '92.0', '2025-06-03', 5800.00, 0, '0000-00-00', 82000.00, 'Pending', NULL, '', '2025-05-20 11:21:16', '2025-05-20 11:21:16', '0000-00-00'),
(8, 1, 21, NULL, 'OD-003', 'RING', 'FDSFS', 'Necklace', 5.600, 5.000, '92.0', '2025-06-03', 5800.00, 0, '0000-00-00', 82000.00, 'Pending', NULL, '', '2025-05-20 11:21:17', '2025-05-20 11:21:17', '0000-00-00'),
(9, 1, 21, NULL, 'OD-003', 'RING', 'FDSFS', 'Necklace', 5.600, 5.000, '92.0', '2025-06-03', 5800.00, 0, '0000-00-00', 82000.00, 'Pending', NULL, '', '2025-05-20 11:21:21', '2025-05-20 11:21:21', '0000-00-00'),
(11, 1, 18, 1, 'OD-004', 'ring', 'dasd', 'Necklace', 5.900, 9.600, '92.0', '2025-06-03', 50000.00, 0, '0000-00-00', 100000.00, 'Pending', NULL, '', '2025-05-20 11:24:26', '2025-05-20 11:24:26', '0000-00-00'),
(12, 1, 26, 1, 'OD-005', 'SET', 'RANI HAR', 'Necklace', 10.690, 10.600, '92.0', '2025-06-03', 50000.00, 0, '0000-00-00', 65560.00, 'In Progress', NULL, '', '2025-05-20 11:28:50', '2025-05-20 11:35:16', '0000-00-00'),
(1, 1, 18, 3, 'OD-001', 'JHUMKA', 'JHUMKA', 'EARING', 12.600, 12.600, '92.0', '2025-05-06', 50000.00, 50000, '0000-00-00', 169800.00, 'Delivered', '2025-05-07', NULL, '2025-05-05 08:03:15', '2025-05-20 08:09:22', '2025-05-05'),
(3, 1, 19, 2, 'OD-002', 'RING', 'NULL', 'RING', 5.900, 5.900, '92.00', '2025-05-20', 5000.00, 5000, '2025-05-01', 89000.00, 'Delivered', NULL, 'DFGF', '2025-05-20 07:47:42', '2025-05-20 08:09:31', '2025-05-20'),
(7, 1, 21, NULL, 'OD-003', 'RING', 'FDSFS', 'Necklace', 5.600, 5.000, '92.0', '2025-06-03', 5800.00, 0, '0000-00-00', 82000.00, 'Pending', NULL, '', '2025-05-20 11:21:16', '2025-05-20 11:21:16', '0000-00-00'),
(8, 1, 21, NULL, 'OD-003', 'RING', 'FDSFS', 'Necklace', 5.600, 5.000, '92.0', '2025-06-03', 5800.00, 0, '0000-00-00', 82000.00, 'Pending', NULL, '', '2025-05-20 11:21:17', '2025-05-20 11:21:17', '0000-00-00'),
(9, 1, 21, NULL, 'OD-003', 'RING', 'FDSFS', 'Necklace', 5.600, 5.000, '92.0', '2025-06-03', 5800.00, 0, '0000-00-00', 82000.00, 'Pending', NULL, '', '2025-05-20 11:21:21', '2025-05-20 11:21:21', '0000-00-00'),
(11, 1, 18, 1, 'OD-004', 'ring', 'dasd', 'Necklace', 5.900, 9.600, '92.0', '2025-06-03', 50000.00, 0, '0000-00-00', 100000.00, 'Pending', NULL, '', '2025-05-20 11:24:26', '2025-05-20 11:24:26', '0000-00-00'),
(12, 1, 26, 1, 'OD-005', 'SET', 'RANI HAR', 'Necklace', 10.690, 10.600, '92.0', '2025-06-03', 50000.00, 0, '0000-00-00', 65560.00, 'In Progress', NULL, '', '2025-05-20 11:28:50', '2025-05-20 11:35:16', '0000-00-00');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `firm_id`, `category`, `amount`, `description`, `date`, `payment_method`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 44, 'HALLMARK CHARGE', 236.00, 'BILL NO MHC256', '2025-06-29', 'Cash', 26, '2025-06-22 09:10:07', '2025-06-22 09:10:07'),
(8, 44, 'HALLMARK CHARGE', 600.00, '', '2025-06-22', 'Cash', 26, '2025-06-22 09:26:07', '2025-06-22 09:26:07'),
(9, 44, 'HALLMARK CHARGE', 700.00, '', '2025-06-23', 'Cash', 26, '2025-06-22 09:28:31', '2025-06-22 09:28:31'),
(10, 44, 'HALLMARK CHARGE', 390.00, 'hallamrk', '2025-06-29', 'Cash', 26, '2025-06-22 09:34:13', '2025-06-22 09:34:13');

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `firm_id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 44, 'HALLMARK CHARGE', NULL, '2025-06-22 09:10:07', '2025-06-22 09:10:07');

-- --------------------------------------------------------

--
-- Table structure for table `firm`
--

CREATE TABLE `firm` (
  `id` int(11) NOT NULL,
  `FirmName` varchar(150) NOT NULL,
  `OwnerName` varchar(100) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `PhoneNumber` varchar(20) DEFAULT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `City` varchar(100) DEFAULT NULL,
  `State` varchar(100) DEFAULT NULL,
  `PostalCode` varchar(20) DEFAULT NULL,
  `Country` varchar(100) DEFAULT 'India',
  `PANNumber` varchar(10) DEFAULT NULL,
  `GSTNumber` varchar(15) DEFAULT NULL,
  `IsGSTRegistered` tinyint(1) DEFAULT 0,
  `BISRegistrationNumber` varchar(50) DEFAULT NULL,
  `BankAccountNumber` varchar(20) DEFAULT NULL,
  `BankName` varchar(100) DEFAULT NULL,
  `BankBranch` varchar(100) DEFAULT NULL,
  `IFSCCode` varchar(11) DEFAULT NULL,
  `AccountType` enum('Savings','Current') DEFAULT 'Current',
  `Logo` longblob DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `Tagline` text NOT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `UpdatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `current_subscription_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `firm`
--

INSERT INTO `firm` (`id`, `FirmName`, `OwnerName`, `Email`, `PhoneNumber`, `Address`, `City`, `State`, `PostalCode`, `Country`, `PANNumber`, `GSTNumber`, `IsGSTRegistered`, `BISRegistrationNumber`, `BankAccountNumber`, `BankName`, `BankBranch`, `IFSCCode`, `AccountType`, `Logo`, `status`, `Tagline`, `CreatedAt`, `UpdatedAt`, `current_subscription_id`) VALUES
(1, 'Jewellers Wala', 'ProsenJit Halder', 'Jeetetchnoguide@gmail.com', '9810359334', 'Vani Vihar Uttam Nagar', 'New Delhi', 'Delhi', '110059', 'India', 'AJMPH8968B', '07AJMPH8968B1Z8', 1, '81956656', '91632200656', 'AXIS BANK', 'UTTAM NAAGR', 'UTB0056', 'Current', NULL, 'active', '', '2025-05-29 16:50:19', '2025-05-29 16:50:19', 1),
(36, 'hallmark jewellery', 'assff', 'demohu@gmail.com', '9810359331', NULL, NULL, NULL, NULL, 'India', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Current', NULL, 'active', 'Crafted with Love, Worn with Pride', '2025-05-30 17:00:58', '2025-05-30 17:28:25', 27),
(37, 'HallmarPro Jewellers', 'Abhinash Rout', 'We@gmail.com', '9856326598', NULL, NULL, NULL, NULL, 'India', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Current', NULL, 'active', '', '2025-05-30 18:21:19', '2025-05-30 18:21:19', 28),
(39, 'TEST JEWELLERS', 'Test User', 'Test@gmail.com', '9898562312', NULL, NULL, NULL, NULL, 'India', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Current', NULL, 'active', '', '2025-05-30 18:56:57', '2025-05-30 18:56:57', 30),
(40, 'TEST JEWELLERS', 'TEST 3', 'TESTY3@gmail.com', '9898652314', NULL, NULL, NULL, NULL, 'India', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Current', NULL, 'active', '', '2025-05-30 18:57:54', '2025-05-30 18:57:54', 31),
(41, 'True Gold Jewellers', 'Prosenjit Halder', '', '9810399589', '173 Vani Vihar Uttam Nagar', 'New Delhi', 'Delhi', '110059', 'India', '', '', 0, '', '', '', '', '', '', 0x75706c6f6164732f6669726d5f6c6f676f732f363833396235336238383235635f313734383631323431312e706e67, 'active', '', '2025-05-30 18:59:23', '2025-05-30 19:40:15', 32),
(42, 'zyx', 'prosenjit', 'demo123@gmail.com', '9810359335', NULL, NULL, NULL, NULL, 'India', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Current', NULL, 'active', '', '2025-06-16 02:13:12', '2025-06-16 02:13:12', 33),
(43, 'zyx', 'prosenjit', 'demo13@gmail.com', '9810359339', NULL, NULL, NULL, NULL, 'India', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Current', NULL, 'active', '', '2025-06-16 02:15:25', '2025-06-16 02:15:25', 34),
(44, 'KRISHNA JEWELLERS', 'SHIV KUMAR', 'JEETECHNOGUIDE@GMAIL.COM', '9810359334', 'VANI VIHAR UTTAM NAGAR', 'DELHI', 'DELHI', '07', 'India', '', '', 0, '', '', '', '', '', 'Current', 0x75706c6f6164732f6669726d5f6c6f676f732f363835376239376338303638645f313735303537393538302e6a7067, 'active', '', '2025-06-16 02:24:57', '2025-06-22 13:36:57', 35);

-- --------------------------------------------------------

--
-- Table structure for table `firm_configurations`
--

CREATE TABLE `firm_configurations` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `non_gst_bill_page_url` varchar(255) DEFAULT 'thermal_invoice.php',
  `gst_bill_page_url` varchar(255) DEFAULT 'thermal_invoice.php',
  `coupon_code_apply_enabled` tinyint(1) DEFAULT 1,
  `schemes_enabled` tinyint(1) DEFAULT 1,
  `gst_rate` decimal(5,4) DEFAULT 0.0300,
  `loyalty_discount_percentage` decimal(5,4) DEFAULT 0.0200,
  `welcome_coupon_enabled` tinyint(1) DEFAULT 1,
  `welcome_coupon_code` varchar(50) DEFAULT 'WELCOME10',
  `post_purchase_coupon_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `auto_scheme_entry` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `firm_configurations`
--

INSERT INTO `firm_configurations` (`id`, `firm_id`, `non_gst_bill_page_url`, `gst_bill_page_url`, `coupon_code_apply_enabled`, `schemes_enabled`, `gst_rate`, `loyalty_discount_percentage`, `welcome_coupon_enabled`, `welcome_coupon_code`, `post_purchase_coupon_enabled`, `auto_scheme_entry`, `created_at`, `updated_at`) VALUES
(1, 1, 'quations.php', 'invoice.php', 1, 1, 0.0300, 0.0200, 1, 'WELCOME10', 0, 1, '2025-05-29 11:54:29', '2025-05-29 11:54:29'),
(2, 36, 'thermal_invoice.php', 'thermal_invoice.php', 1, 1, 0.0300, 0.0200, 1, 'WELCOME10', 0, 1, '2025-05-30 11:30:58', '2025-05-30 11:30:58'),
(3, 37, 'thermal_invoice.php', 'thermal_invoice.php', 1, 1, 0.0300, 0.0200, 1, 'WELCOME10', 0, 1, '2025-05-30 12:51:19', '2025-05-30 12:51:19'),
(4, 39, 'thermal_invoice.php', 'thermal_invoice.php', 1, 1, 0.0300, 0.0200, 1, 'WELCOME10', 0, 1, '2025-05-30 13:26:57', '2025-05-30 13:26:57'),
(5, 40, 'thermal_invoice.php', 'thermal_invoice.php', 1, 1, 0.0300, 0.0200, 1, 'WELCOME10', 0, 1, '2025-05-30 13:27:54', '2025-05-30 13:27:54'),
(6, 41, 'thermal_invoice.php', 'thermal_invoice.php', 1, 1, 0.0300, 0.0200, 1, 'WELCOME10', 1, 1, '2025-05-30 13:29:23', '2025-06-14 10:31:43'),
(7, 42, 'thermal_invoice.php', 'thermal_invoice.php', 1, 1, 0.0300, 0.0200, 1, 'WELCOME10', 0, 1, '2025-06-15 20:43:13', '2025-06-15 20:43:13'),
(8, 43, 'thermal_invoice.php', 'thermal_invoice.php', 1, 1, 0.0300, 0.0200, 1, 'WELCOME10', 0, 1, '2025-06-15 20:45:25', '2025-06-15 20:45:25'),
(9, 44, 'thermal_invoice.php', 'thermal_invoice.php', 1, 1, 0.0300, 0.0200, 1, 'WELCOME10', 0, 1, '2025-06-15 20:54:57', '2025-06-15 20:54:57');

-- --------------------------------------------------------

--
-- Table structure for table `firm_subscriptions`
--

CREATE TABLE `firm_subscriptions` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `trial_end_date` datetime DEFAULT NULL,
  `is_trial` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `auto_renew` tinyint(1) DEFAULT 1,
  `payment_method` varchar(50) DEFAULT NULL,
  `last_payment_date` datetime DEFAULT NULL,
  `next_billing_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `firm_subscriptions`
--

INSERT INTO `firm_subscriptions` (`id`, `firm_id`, `plan_id`, `start_date`, `end_date`, `trial_end_date`, `is_trial`, `is_active`, `auto_renew`, `payment_method`, `last_payment_date`, `next_billing_date`, `notes`) VALUES
(1, 1, 1, '2025-05-29 13:14:11', '2025-05-31 16:44:11', '2025-05-31 16:44:11', 1, 1, 1, NULL, NULL, NULL, NULL),
(2, 11, 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', NULL, 0, 1, 1, NULL, NULL, NULL, NULL),
(3, 12, 1, '2025-05-30 12:23:07', '2025-06-06 12:23:07', '2025-06-06 12:23:07', 1, 1, 1, NULL, NULL, NULL, NULL),
(4, 13, 1, '2025-05-30 12:29:46', '2025-06-06 12:29:46', '2025-06-06 12:29:46', 1, 1, 1, NULL, NULL, NULL, NULL),
(5, 14, 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', NULL, 0, 1, 1, NULL, NULL, NULL, NULL),
(6, 15, 1, '2025-05-30 12:32:35', '2025-06-06 12:32:35', '2025-06-06 12:32:35', 1, 1, 1, NULL, NULL, NULL, NULL),
(7, 16, 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', NULL, 0, 1, 1, NULL, NULL, NULL, NULL),
(27, 36, 1, '2025-05-30 13:30:58', '2025-06-06 13:30:58', NULL, 1, 1, 0, NULL, NULL, NULL, NULL),
(28, 37, 1, '2025-05-30 14:51:19', '2025-06-06 14:51:19', NULL, 1, 1, 0, NULL, NULL, NULL, NULL),
(30, 39, 1, '2025-05-30 15:26:57', '2025-06-06 15:26:57', NULL, 1, 1, 0, NULL, NULL, NULL, NULL),
(31, 40, 1, '2025-05-30 15:27:54', '2025-06-06 15:27:54', NULL, 1, 1, 0, NULL, NULL, NULL, NULL),
(32, 41, 1, '2025-06-01 15:29:23', '2025-06-30 15:29:23', NULL, 0, 1, 0, 'UPI', '2025-06-01 14:28:43', '2025-07-01 14:28:43', 'dsf'),
(33, 42, 1, '2025-06-15 22:43:12', '2025-06-22 22:43:12', NULL, 1, 1, 0, NULL, NULL, NULL, NULL),
(34, 43, 1, '2025-06-15 22:45:25', '2025-06-22 22:45:25', NULL, 1, 1, 0, NULL, NULL, NULL, NULL),
(35, 44, 1, '2025-06-15 22:54:57', '2025-06-30 22:54:57', NULL, 1, 1, 0, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `firm_users`
--

CREATE TABLE `firm_users` (
  `id` int(11) NOT NULL,
  `Name` varchar(50) NOT NULL,
  `Username` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `FirmID` int(11) NOT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `PhoneNumber` varchar(15) DEFAULT NULL,
  `Role` varchar(50) DEFAULT 'Admin',
  `Status` varchar(20) DEFAULT 'Active',
  `CreatedAt` timestamp NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image_path` varchar(50) NOT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `remember_token` varchar(64) DEFAULT NULL,
  `token_expiration` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `firm_users`
--

INSERT INTO `firm_users` (`id`, `Name`, `Username`, `Password`, `FirmID`, `Email`, `PhoneNumber`, `Role`, `Status`, `CreatedAt`, `UpdatedAt`, `image_path`, `reset_token`, `token_expiry`, `remember_token`, `token_expiration`) VALUES
(1, 'Kousik', 'Truegoldadmin', '$2y$10$sbgXtXPLtYrwy0oS61mvbOYhkWu3Q998ofosdFQT41shFEk/1eGsS', 2, 'kousik@gmail.com', '9891588254', 'Super Admin', 'Active', '2025-05-30 09:31:33', '2025-05-30 09:33:00', '', NULL, NULL, NULL, NULL),
(16, 'assff', '9810359331', '$2y$10$lfkhGIM0WrcalLyEdOKX1eKY3AOImIRVO30n8naQJrFwgvnzJ0JD6', 36, 'demohu@gmail.com', '9810359331', 'Super Admin', 'Active', '2025-05-30 11:30:58', '2025-05-30 12:33:41', '', NULL, NULL, NULL, NULL),
(18, 'Test User', '9898562312', '$2y$10$e4UZ0Afruvj8yX9MI5E8CexCzxcYjNHBmFATjedOLSKzw4X1acy0K', 39, 'Test@gmail.com', '9898562312', 'Admin', 'Active', '2025-05-30 13:26:57', '2025-05-30 13:26:57', '', NULL, NULL, NULL, NULL),
(20, 'PROSNJIT HALDER', '9898562314', '$2y$10$tFOLyzoeCwFY8CK/Ykcu8O2sAGatkISGlMhCYBQkagnyOyySlcBfC', 41, 'mahalaxmihc@gmail.com', '9898562314', 'Super Admin', 'Active', '2025-05-30 13:29:23', '2025-06-14 16:40:54', 'uploads/staff_images/staff_6839c6d3f0a07.jpg', NULL, NULL, NULL, NULL),
(23, 'KISORE HALDER9', 'kisore9331', '$2y$10$hB/Xi7zImoZnFwRL8GrAIuMGBWfJfTrGHIYGUmb/B0y0vfsF7/Fqy', 41, 'DEMOYU@GMAIL.COM', '9810359331', 'Manager', 'Active', '2025-05-30 14:52:55', '2025-05-30 14:52:55', 'uploads/staff_images/staff_6839c647597e2.jpg', NULL, NULL, NULL, NULL),
(24, 'prosenjit', '9810359335', '$2y$10$gXOZ5Q1Bwx4V42uRKy/6T.iBJWGUeuaQpliSeBfhpA3Bp37xZYO.2', 42, 'demo123@gmail.com', '9810359335', 'Super Admin', 'Active', '2025-06-15 20:43:13', '2025-06-15 20:43:13', '', NULL, NULL, NULL, NULL),
(25, 'prosenjit', '9810359339', '$2y$10$9Qg.eGiScUGdSSdLMeRIyuacG49fgxNl7dXst8asLFvROTxj/3fXu', 43, 'demo13@gmail.com', '9810359339', 'Super Admin', 'Active', '2025-06-15 20:45:25', '2025-06-15 20:45:25', '', NULL, NULL, NULL, NULL),
(26, 'prosenjit hKSWE', '9810359334', '$2y$10$/gV3ZPDVSc/m4Td0.EmnvO4XJ69re00M1Y0jT4uutf0WggicdQZpC', 44, 'JEETECHNOGUIDE@GMAIL.COM', '9810359334', 'Super Admin', 'Active', '2025-06-15 20:54:57', '2025-06-22 08:05:49', '', NULL, NULL, 'a31d8a4d9afa4e00dbab84a8e337d4e4e5751b06e8b3540191fd8c2080ec3629', '2025-07-22 10:05:49');

-- --------------------------------------------------------

--
-- Table structure for table `gold_plan_installments`
--

CREATE TABLE `gold_plan_installments` (
  `id` int(11) NOT NULL,
  `customer_plan_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `gold_credited_g` decimal(10,4) DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gold_plan_installments`
--

INSERT INTO `gold_plan_installments` (`id`, `customer_plan_id`, `payment_date`, `amount_paid`, `gold_credited_g`, `receipt_number`, `payment_method`, `notes`, `created_by`, `created_at`) VALUES
(13, 4, '2025-06-22', 4000.00, 0.4604, 'PAY-20250622223418-52', 'Cash', '', 26, '2025-06-22 20:34:18'),
(14, 4, '2025-07-22', 0.00, 0.0000, '', '', '', 26, '2025-06-22 18:51:37'),
(15, 4, '2025-08-22', 500.00, 0.0576, 'PAY-20250622221214-52', 'Cash', '', 26, '2025-06-22 20:12:14'),
(16, 4, '2025-09-22', 5000.00, 0.5755, 'PAY-20250622221241-52', 'Cash', '', 26, '2025-06-22 20:12:41'),
(17, 4, '2025-10-22', 4000.00, 0.4604, 'PAY-20250622222300-52', 'Cash', '', 26, '2025-06-22 20:23:00'),
(18, 4, '2025-11-22', 0.00, 0.0000, '', '', '', 26, '2025-06-22 18:51:37'),
(19, 4, '2025-12-22', 0.00, 0.0000, '', '', '', 26, '2025-06-22 18:51:37'),
(20, 4, '2026-01-22', 0.00, 0.0000, '', '', '', 26, '2025-06-22 18:51:37'),
(21, 4, '2026-02-22', 0.00, 0.0000, '', '', '', 26, '2025-06-22 18:51:37'),
(22, 4, '2026-03-22', 0.00, 0.0000, '', '', '', 26, '2025-06-22 18:51:37'),
(23, 4, '2026-04-22', 0.00, 0.0000, '', '', '', 26, '2025-06-22 18:51:37'),
(24, 4, '2026-05-22', 0.00, 0.0000, '', '', '', 26, '2025-06-22 18:51:37');

-- --------------------------------------------------------

--
-- Table structure for table `gold_saving_plans`
--

CREATE TABLE `gold_saving_plans` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `plan_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_months` int(11) NOT NULL,
  `min_amount_per_installment` decimal(10,2) DEFAULT NULL,
  `installment_frequency` enum('daily','weekly','monthly','custom') NOT NULL,
  `bonus_percentage` decimal(5,2) DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `terms_conditions` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gold_saving_plans`
--

INSERT INTO `gold_saving_plans` (`id`, `firm_id`, `plan_name`, `description`, `duration_months`, `min_amount_per_installment`, `installment_frequency`, `bonus_percentage`, `status`, `terms_conditions`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 41, 'Swarna Lakshmi Plan', 'Pay 11/Get 12', 12, 3000.00, 'monthly', 8.33, 'active', 'Customer must pay all 11 installments to receive bonus. No late payment allowed.', 1, '2025-06-03 09:18:29', '2025-06-03 09:22:07'),
(2, 41, 'Dhan Varsha Plan', 'Save weekly for a year and earn bonus gold at end.', 12, 750.00, 'monthly', 10.00, 'inactive', 'Full bonus is only applicable if all 48 weekly installments are paid without fail.', 0, '2025-06-03 09:18:29', '2025-06-03 09:32:46'),
(3, 1, 'Akshaya Gold Plan', 'Flexible plan with quarterly deposits and festival bonus.', 9, 5000.00, '', 7.00, 'active', 'Installments must be paid before due date for eligibility of bonus.', 0, '2025-06-03 09:18:29', '2025-06-03 09:18:29'),
(4, 1, 'Smart Gold Saver', 'Short-term plan with monthly savings and small bonus.', 6, 2000.00, 'monthly', 5.00, 'inactive', 'Applicable only on purchase of jewellery over ₹25,000 at the end of term.', 0, '2025-06-03 09:18:29', '2025-06-03 09:18:29'),
(5, 44, 'DHAN LAXMI', 'NO', 12, 3000.00, 'monthly', 12.00, 'active', 'NONE', 26, '2025-06-22 14:14:50', '2025-06-22 14:24:16'),
(6, 44, 'DHAN BARSO', '', 12, 5000.00, 'monthly', 15.00, '', '', 26, '2025-06-22 14:24:55', '2025-06-22 14:24:55'),
(7, 44, 'LAMXMI BARSO', '', 12, 300.00, 'monthly', 12.00, '', '', 26, '2025-06-22 14:30:08', '2025-06-22 14:30:08'),
(8, 44, 'DHAN BARSO', '', 12, 5000.00, 'monthly', 15.00, 'active', '', 26, '2025-06-22 14:24:55', '2025-06-22 14:24:55');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_metals`
--

CREATE TABLE `inventory_metals` (
  `inventory_id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL DEFAULT 1,
  `material_type` varchar(50) NOT NULL,
  `stock_name` varchar(100) NOT NULL,
  `purity` decimal(50,2) NOT NULL,
  `current_stock` decimal(10,3) NOT NULL DEFAULT 0.000,
  `remaining_stock` decimal(10,3) NOT NULL DEFAULT 0.000,
  `cost_price_per_gram` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_measurement` varchar(20) DEFAULT 'grams',
  `last_updated` timestamp NULL DEFAULT current_timestamp(),
  `minimum_stock_level` decimal(10,3) DEFAULT 0.000,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source_type` text NOT NULL,
  `source_id` int(11) DEFAULT NULL,
  `total_cost` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_metals`
--

INSERT INTO `inventory_metals` (`inventory_id`, `firm_id`, `material_type`, `stock_name`, `purity`, `current_stock`, `remaining_stock`, `cost_price_per_gram`, `unit_measurement`, `last_updated`, `minimum_stock_level`, `created_at`, `updated_at`, `source_type`, `source_id`, `total_cost`) VALUES
(38, 1, 'Gold', '22K Hallmakrd Jewelry', 92.00, 97.600, 5.800, 0.00, 'grams', '2025-06-22 08:29:20', 0.000, '2025-06-01 08:14:08', '2025-06-22 08:29:20', 'purchase', 96, 0),
(39, 1, 'Gold', '22K Hallmakrd Jewelry', 92.00, 60.690, 40.690, 0.00, 'grams', '2025-06-22 08:32:32', 0.000, '2025-06-01 11:32:41', '2025-06-22 08:32:32', 'purchase', 97, 0),
(43, 1, 'Gold', '14k', 59.00, 23.600, 23.600, 0.00, 'grams', '2025-06-01 11:35:06', 0.000, '2025-06-01 11:35:06', '2025-06-01 11:35:06', 'purchase', 98, 0),
(46, 1, 'Silver', '75K JEWELELRY', 76.00, 109.600, 109.600, 0.00, 'grams', '2025-06-01 11:41:15', 0.000, '2025-06-01 11:41:15', '2025-06-01 11:41:15', 'purchase', 99, 0),
(47, 1, 'Gold', '75K JWELERY', 76.00, 500.600, 500.600, 0.00, 'grams', '2025-06-01 11:42:04', 0.000, '2025-06-01 11:42:04', '2025-06-01 11:42:04', 'purchase', 100, 0),
(48, 1, 'Gold', '833 jewellers', 84.00, 50.000, 50.000, 0.00, 'grams', '2025-06-01 11:47:36', 0.000, '2025-06-01 11:47:36', '2025-06-01 11:47:36', 'purchase', 101, 0),
(49, 1, 'Gold', '22K Hallmakrd Jewelry', 92.00, 10.000, 10.000, 0.00, 'grams', '2025-06-01 11:56:58', 0.000, '2025-06-01 11:56:58', '2025-06-01 11:56:58', 'purchase', 102, 0),
(50, 1, 'Gold', '22K Hallmakrd Jewelry', 92.00, 10.000, 10.000, 0.00, 'grams', '2025-06-01 11:57:10', 0.000, '2025-06-01 11:57:10', '2025-06-01 11:57:10', 'purchase', 103, 0),
(51, 1, 'Gold', '22K Hallmakrd Jewelry', 92.00, 10.000, 10.000, 0.00, 'grams', '2025-06-01 11:57:18', 0.000, '2025-06-01 11:57:18', '2025-06-01 11:57:18', 'purchase', 104, 0),
(53, 1, 'Gold', '22K Hallmakrd Jewelry', 92.00, 10.000, 10.000, 0.00, 'grams', '2025-06-01 11:58:19', 0.000, '2025-06-01 11:58:19', '2025-06-01 11:58:19', 'purchase', 105, 0),
(54, 1, 'Gold', '75K JWELERY', 76.00, 10.000, 10.000, 0.00, 'grams', '2025-06-01 11:59:23', 0.000, '2025-06-01 11:59:23', '2025-06-01 11:59:23', 'purchase', 106, 0),
(55, 1, 'Gold', '14k', 59.00, 9.990, 9.990, 0.00, 'grams', '2025-06-01 12:01:05', 0.000, '2025-06-01 12:01:05', '2025-06-01 12:01:05', 'purchase', 107, 0),
(56, 1, 'Gold', '833 jewellers', 84.00, 5.000, 5.000, 0.00, 'grams', '2025-06-01 12:10:18', 0.000, '2025-06-01 12:10:18', '2025-06-01 12:10:18', 'purchase', 108, 0),
(57, 1, 'Gold', '22K Hallmakrd Jewelry', 92.00, 10.000, 10.000, 0.00, 'grams', '2025-06-01 12:44:17', 0.000, '2025-06-01 12:44:17', '2025-06-01 12:44:17', 'purchase', 109, 0),
(58, 1, 'Gold', '22K Hallmakrd Jewelry', 92.00, 50.000, 50.000, 0.00, 'grams', '2025-06-01 13:41:55', 0.000, '2025-06-01 13:41:55', '2025-06-01 13:41:55', 'purchase', 110, 0),
(59, 1, 'Gold', '22K Jewellery', 92.00, 36.600, 36.600, 0.00, 'grams', '2025-06-01 14:04:00', 0.000, '2025-06-01 14:04:00', '2025-06-01 14:04:00', 'purchase', 111, 0),
(60, 41, 'Gold', '22k', 92.00, 30.000, 1.098, 0.00, 'grams', '2025-06-02 14:11:53', 0.000, '2025-06-01 14:05:49', '2025-06-02 14:11:53', 'purchase', 112, 0),
(61, 44, 'Gold', 'GOLD 22', 92.00, 20.660, 10.160, 0.00, 'grams', '2025-06-22 08:34:17', 0.000, '2025-06-22 08:11:17', '2025-06-22 08:34:17', 'purchase', 113, 0),
(62, 44, 'Gold', '14k', 59.00, 20.000, 20.000, 0.00, 'grams', '2025-06-22 09:38:03', 0.000, '2025-06-22 09:38:03', '2025-06-22 09:38:03', 'purchase', 114, 0),
(63, 44, 'Gold', '18k', 76.00, 70.600, 70.600, 0.00, 'grams', '2025-06-22 10:00:59', 0.000, '2025-06-22 10:00:59', '2025-06-22 10:00:59', 'purchase', 115, 0),
(64, 44, 'Gold', '20k', 84.00, 78.600, 78.600, 0.00, 'grams', '2025-06-22 10:02:24', 0.000, '2025-06-22 10:02:24', '2025-06-22 10:02:24', 'purchase', 116, 0),
(65, 44, 'Gold', 'gold bar', 99.99, 20.000, 20.000, 0.00, 'grams', '2025-06-22 10:07:37', 0.000, '2025-06-22 10:07:37', '2025-06-22 10:07:37', 'purchase', 117, 0),
(66, 44, 'Gold', 'GOLD 22', 92.00, 50.640, 50.640, 0.00, 'grams', '2025-06-22 11:24:28', 0.000, '2025-06-22 11:24:28', '2025-06-22 11:24:28', 'purchase', 118, 0),
(67, 44, 'Gold', 'GOLD 22', 92.00, 20.000, 20.000, 0.00, 'grams', '2025-06-22 12:32:50', 0.000, '2025-06-22 12:32:50', '2025-06-22 12:32:50', 'direct', 119, 0),
(68, 44, 'Gold', 'GOLD 22', 92.00, 50.000, 40.000, 0.00, 'grams', '2025-06-22 21:45:00', 0.000, '2025-06-22 12:33:15', '2025-06-22 21:45:00', 'purchase', 120, 0),
(69, 44, 'Gold', 'GOLD 22', 99.99, 10.000, 10.000, 0.00, 'grams', '2025-06-22 20:45:20', 0.000, '2025-06-22 20:45:20', '2025-06-22 20:45:20', 'purchase', 121, 0);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `transaction_type` enum('IN','OUT') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `transaction_date` datetime NOT NULL,
  `created_by` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_transactions`
--

INSERT INTO `inventory_transactions` (`id`, `inventory_id`, `transaction_type`, `quantity`, `reference_id`, `reference_type`, `transaction_date`, `created_by`, `firm_id`, `created_at`, `updated_at`) VALUES
(1, 59, '', 6.30, 300, 'Jewelry Item', '2025-05-10 12:55:07', 2, 1, '2025-05-10 12:55:07', '2025-05-10 12:55:07'),
(2, 59, '', 6.90, 301, 'Jewelry Item', '2025-05-10 13:36:51', 2, 1, '2025-05-10 13:36:51', '2025-05-10 13:36:51'),
(3, 59, '', 3.90, 302, 'Jewelry Item', '2025-05-10 13:44:58', 2, 1, '2025-05-10 13:44:58', '2025-05-10 13:44:58'),
(4, 59, '', 6.90, 303, 'Jewelry Item', '2025-05-10 14:06:51', 2, 1, '2025-05-10 14:06:51', '2025-05-10 14:06:51'),
(5, 59, '', 4.90, 304, 'Jewelry Item', '2025-05-10 14:07:28', 2, 1, '2025-05-10 14:07:28', '2025-05-10 14:07:28'),
(6, 59, '', 3.90, 305, 'Jewelry Item', '2025-05-10 14:08:16', 2, 1, '2025-05-10 14:08:16', '2025-05-10 14:08:16'),
(7, 59, '', 10.00, 306, 'Jewelry Item', '2025-05-10 14:09:05', 2, 1, '2025-05-10 14:09:05', '2025-05-10 14:09:05'),
(8, 59, '', 9.60, 307, 'Jewelry Item', '2025-05-11 06:55:16', 2, 1, '2025-05-11 06:55:16', '2025-05-11 06:55:16'),
(9, 60, '', 3.90, 309, 'Jewelry Item', '2025-05-11 07:45:49', 2, 1, '2025-05-11 07:45:49', '2025-05-11 07:45:49'),
(10, 59, '', 4.69, 319, 'Jewelry Item', '2025-05-11 13:09:06', 2, 1, '2025-05-11 13:09:06', '2025-05-11 13:09:06'),
(11, 59, '', 10.69, 320, 'Jewelry Item', '2025-05-11 13:10:49', 2, 1, '2025-05-11 13:10:49', '2025-05-11 13:10:49'),
(12, 59, '', 2.36, 321, 'Jewelry Item', '2025-05-11 13:13:48', 2, 1, '2025-05-11 13:13:48', '2025-05-11 13:13:48'),
(13, 59, '', 5.60, 331, 'Jewelry Item', '2025-05-12 12:52:32', 2, 1, '2025-05-12 12:52:32', '2025-05-12 12:52:32'),
(14, 59, '', 10.48, 332, 'Jewelry Item', '2025-05-12 13:15:49', 2, 1, '2025-05-12 13:15:49', '2025-05-12 13:15:49'),
(15, 59, '', 3.49, 333, 'Jewelry Item', '2025-05-12 13:23:32', 2, 1, '2025-05-12 13:23:32', '2025-05-12 13:23:32'),
(16, 65, '', 3.48, 335, 'Jewelry Item', '2025-05-15 13:49:12', 2, 1, '2025-05-15 13:49:12', '2025-05-15 13:49:12'),
(17, 65, '', 3.48, 336, 'Jewelry Item', '2025-05-15 13:49:16', 2, 1, '2025-05-15 13:49:16', '2025-05-15 13:49:16'),
(18, 65, '', 3.48, 337, 'Jewelry Item', '2025-05-15 13:49:17', 2, 1, '2025-05-15 13:49:17', '2025-05-15 13:49:17'),
(19, 65, '', 3.48, 338, 'Jewelry Item', '2025-05-15 13:49:17', 2, 1, '2025-05-15 13:49:17', '2025-05-15 13:49:17'),
(20, 65, '', 3.48, 339, 'Jewelry Item', '2025-05-15 13:49:17', 2, 1, '2025-05-15 13:49:17', '2025-05-15 13:49:17'),
(21, 65, '', 3.48, 340, 'Jewelry Item', '2025-05-15 13:49:17', 2, 1, '2025-05-15 13:49:17', '2025-05-15 13:49:17'),
(22, 65, '', 3.48, 341, 'Jewelry Item', '2025-05-15 13:49:18', 2, 1, '2025-05-15 13:49:18', '2025-05-15 13:49:18'),
(23, 65, '', 3.48, 342, 'Jewelry Item', '2025-05-15 13:49:18', 2, 1, '2025-05-15 13:49:18', '2025-05-15 13:49:18'),
(24, 65, '', 3.48, 343, 'Jewelry Item', '2025-05-15 13:49:18', 2, 1, '2025-05-15 13:49:18', '2025-05-15 13:49:18'),
(25, 65, '', 3.48, 344, 'Jewelry Item', '2025-05-15 13:49:18', 2, 1, '2025-05-15 13:49:18', '2025-05-15 13:49:18'),
(26, 65, '', 3.48, 345, 'Jewelry Item', '2025-05-15 13:49:21', 2, 1, '2025-05-15 13:49:21', '2025-05-15 13:49:21'),
(27, 65, '', 3.48, 346, 'Jewelry Item', '2025-05-15 13:49:21', 2, 1, '2025-05-15 13:49:21', '2025-05-15 13:49:21'),
(28, 65, '', 3.48, 347, 'Jewelry Item', '2025-05-15 13:49:21', 2, 1, '2025-05-15 13:49:21', '2025-05-15 13:49:21'),
(29, 65, '', 3.48, 348, 'Jewelry Item', '2025-05-15 13:49:21', 2, 1, '2025-05-15 13:49:21', '2025-05-15 13:49:21'),
(30, 65, '', 3.48, 349, 'Jewelry Item', '2025-05-15 13:49:22', 2, 1, '2025-05-15 13:49:22', '2025-05-15 13:49:22'),
(31, 65, '', 3.48, 350, 'Jewelry Item', '2025-05-15 13:49:22', 2, 1, '2025-05-15 13:49:22', '2025-05-15 13:49:22'),
(32, 65, '', 3.48, 351, 'Jewelry Item', '2025-05-15 13:49:22', 2, 1, '2025-05-15 13:49:22', '2025-05-15 13:49:22'),
(33, 65, '', 3.48, 352, 'Jewelry Item', '2025-05-15 13:49:22', 2, 1, '2025-05-15 13:49:22', '2025-05-15 13:49:22'),
(34, 65, '', 3.48, 353, 'Jewelry Item', '2025-05-15 13:49:22', 2, 1, '2025-05-15 13:49:22', '2025-05-15 13:49:22'),
(35, 65, '', 3.48, 354, 'Jewelry Item', '2025-05-15 13:49:22', 2, 1, '2025-05-15 13:49:22', '2025-05-15 13:49:22'),
(36, 65, '', 3.48, 355, 'Jewelry Item', '2025-05-15 13:49:25', 2, 1, '2025-05-15 13:49:25', '2025-05-15 13:49:25'),
(37, 65, '', 3.48, 356, 'Jewelry Item', '2025-05-15 13:49:25', 2, 1, '2025-05-15 13:49:25', '2025-05-15 13:49:25'),
(38, 65, '', 3.48, 357, 'Jewelry Item', '2025-05-15 13:49:25', 2, 1, '2025-05-15 13:49:25', '2025-05-15 13:49:25'),
(39, 65, '', 3.48, 358, 'Jewelry Item', '2025-05-15 13:49:26', 2, 1, '2025-05-15 13:49:26', '2025-05-15 13:49:26'),
(40, 65, '', 3.48, 359, 'Jewelry Item', '2025-05-15 13:49:26', 2, 1, '2025-05-15 13:49:26', '2025-05-15 13:49:26'),
(41, 65, '', 3.48, 360, 'Jewelry Item', '2025-05-15 13:49:29', 2, 1, '2025-05-15 13:49:29', '2025-05-15 13:49:29'),
(42, 65, '', 2.96, 361, 'Jewelry Item', '2025-05-15 13:51:45', 2, 1, '2025-05-15 13:51:45', '2025-05-15 13:51:45'),
(43, 65, '', 2.96, 362, 'Jewelry Item', '2025-05-15 13:51:50', 2, 1, '2025-05-15 13:51:50', '2025-05-15 13:51:50'),
(44, 65, '', 2.96, 363, 'Jewelry Item', '2025-05-15 13:51:51', 2, 1, '2025-05-15 13:51:51', '2025-05-15 13:51:51'),
(45, 59, '', 1.18, 372, 'Jewelry Item', '2025-05-15 13:54:13', 2, 1, '2025-05-15 13:54:13', '2025-05-15 13:54:13'),
(46, 59, '', 3.48, 373, 'Jewelry Item', '2025-05-15 14:34:59', 2, 1, '2025-05-15 14:34:59', '2025-05-15 14:34:59'),
(47, 59, '', 3.48, 374, 'Jewelry Item', '2025-05-15 14:35:03', 2, 1, '2025-05-15 14:35:03', '2025-05-15 14:35:03'),
(48, 59, '', 2.14, 377, 'Jewelry Item', '2025-05-16 06:38:31', 2, 1, '2025-05-16 06:38:31', '2025-05-16 06:38:31'),
(49, 65, '', 0.39, 379, 'Jewelry Item', '2025-05-16 14:05:48', 2, 1, '2025-05-16 14:05:48', '2025-05-16 14:05:48'),
(50, 64, '', 4.22, 381, 'Jewelry Item', '2025-05-17 08:29:25', 2, 1, '2025-05-17 08:29:25', '2025-05-17 08:29:25'),
(51, 64, '', 6.18, 383, 'Jewelry Item', '2025-05-18 07:01:57', 2, 1, '2025-05-18 07:01:57', '2025-05-18 07:01:57'),
(52, 64, '', 6.26, 384, 'Jewelry Item', '2025-05-18 07:28:29', 2, 1, '2025-05-18 07:28:29', '2025-05-18 07:28:29'),
(53, 64, '', 3.28, 386, 'Jewelry Item', '2025-05-18 07:30:00', 2, 1, '2025-05-18 07:30:00', '2025-05-18 07:30:00'),
(54, 62, '', 3.38, 388, 'Jewelry Item', '2025-05-19 06:32:03', 2, 1, '2025-05-19 06:32:03', '2025-05-19 06:32:03'),
(55, 62, '', 4.52, 389, 'Jewelry Item', '2025-05-19 07:11:51', 2, 1, '2025-05-19 07:11:51', '2025-05-19 07:11:51'),
(56, 62, '', 6.60, 390, 'Jewelry Item', '2025-05-19 07:13:31', 2, 1, '2025-05-19 07:13:31', '2025-05-19 07:13:31'),
(57, 63, '', 3.54, 400, 'Jewelry Item', '2025-05-19 12:59:43', 2, 1, '2025-05-19 12:59:43', '2025-05-19 12:59:43'),
(58, 63, '', 3.57, 401, 'Jewelry Item', '2025-05-19 13:31:15', 2, 1, '2025-05-19 13:31:15', '2025-05-19 13:31:15'),
(59, 63, '', 3.78, 402, 'Jewelry Item', '2025-05-19 14:03:12', 2, 1, '2025-05-19 14:03:12', '2025-05-19 14:03:12'),
(60, 63, '', 3.27, 403, 'Jewelry Item', '2025-05-19 14:10:50', 2, 1, '2025-05-19 14:10:50', '2025-05-19 14:10:50'),
(61, 63, '', 3.26, 404, 'Jewelry Item', '2025-05-20 22:10:15', 2, 1, '2025-05-20 22:10:15', '2025-05-20 22:10:15'),
(62, 62, '', 3.60, 435, 'Jewelry Item', '2025-05-26 20:53:07', 2, 1, '2025-05-26 20:53:07', '2025-05-26 20:53:07'),
(63, 62, '', 4.60, 436, 'Jewelry Item', '2025-05-26 20:53:57', 2, 1, '2025-05-26 20:53:57', '2025-05-26 20:53:57'),
(64, 81, '', 3.48, 437, 'Jewelry Item', '2025-05-27 10:08:24', 2, 1, '2025-05-27 10:08:24', '2025-05-27 10:08:24'),
(65, 85, '', 3.68, 438, 'Jewelry Item', '2025-05-27 10:38:33', 2, 1, '2025-05-27 10:38:33', '2025-05-27 10:38:33'),
(66, 85, '', 6.35, 439, 'Jewelry Item', '2025-05-27 13:46:11', 2, 1, '2025-05-27 13:46:11', '2025-05-27 13:46:11'),
(67, 85, '', 6.65, 440, 'Jewelry Item', '2025-05-27 13:53:01', 2, 1, '2025-05-27 13:53:01', '2025-05-27 13:53:01'),
(68, 85, '', 3.60, 441, 'Jewelry Item', '2025-05-27 18:27:09', 2, 1, '2025-05-27 18:27:09', '2025-05-27 18:27:09'),
(0, 85, '', 6.39, 0, 'Jewelry Item', '2025-05-29 18:16:47', 2, 1, '2025-05-29 18:16:47', '2025-05-29 18:16:47'),
(0, 60, '', 3.60, 0, 'Jewelry Item', '2025-06-02 12:06:49', 20, 41, '2025-06-02 12:06:49', '2025-06-02 12:06:49'),
(0, 60, '', 6.96, 442, 'Jewelry Item', '2025-06-02 12:07:46', 20, 41, '2025-06-02 12:07:46', '2025-06-02 12:07:46'),
(0, 60, '', 3.60, 443, 'Jewelry Item', '2025-06-02 16:16:21', 20, 41, '2025-06-02 16:16:21', '2025-06-02 16:16:21'),
(0, 60, '', 6.85, 444, 'Jewelry Item', '2025-06-02 17:20:05', 20, 41, '2025-06-02 17:20:05', '2025-06-02 17:20:05'),
(0, 60, '', 1.60, 445, 'Jewelry Item', '2025-06-02 18:53:46', 20, 41, '2025-06-02 18:53:46', '2025-06-02 18:53:46'),
(0, 60, '', 5.60, 447, 'Jewelry Item', '2025-06-02 18:57:55', 20, 41, '2025-06-02 18:57:55', '2025-06-02 18:57:55'),
(0, 60, '', 0.69, 448, 'Jewelry Item', '2025-06-02 19:41:53', 20, 41, '2025-06-02 19:41:53', '2025-06-02 19:41:53');

-- --------------------------------------------------------

--
-- Table structure for table `jewelentry_categories`
--

CREATE TABLE `jewelentry_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jewelentry_categories`
--

INSERT INTO `jewelentry_categories` (`id`, `name`, `parent_id`, `created_at`) VALUES
(1, 'Ring', NULL, '2025-05-12 18:48:56'),
(2, 'Necklace', NULL, '2025-05-12 18:48:56'),
(3, 'Bracelet', NULL, '2025-05-12 18:48:56'),
(4, 'Earring', NULL, '2025-05-12 18:48:56'),
(5, 'Pendant', NULL, '2025-05-12 18:48:56'),
(6, 'Bangle', NULL, '2025-05-12 18:48:56'),
(7, 'Chain', NULL, '2025-05-12 18:48:56'),
(8, 'Anklet', NULL, '2025-05-12 18:48:56');

-- --------------------------------------------------------

--
-- Table structure for table `jewelentry_category`
--

CREATE TABLE `jewelentry_category` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jewelentry_category`
--

INSERT INTO `jewelentry_category` (`id`, `name`, `parent_id`, `description`, `created_at`) VALUES
(1, 'Rings', NULL, 'Rings category', '2025-05-05 20:15:36'),
(2, 'Kada', NULL, 'Kada category', '2025-05-05 20:15:36'),
(3, 'Chains', NULL, 'Chains category', '2025-05-05 20:15:36'),
(4, 'Bracelets', NULL, 'Bracelets category', '2025-05-05 20:15:36'),
(5, 'Necklaces', NULL, 'Necklaces category', '2025-05-05 20:15:36'),
(6, 'Earrings', NULL, 'Earrings category', '2025-05-05 20:15:36'),
(7, 'Bangles', NULL, 'Bangles category', '2025-05-05 20:15:36'),
(8, 'Pendants', NULL, 'Pendants category', '2025-05-05 20:15:36'),
(9, 'Anklets', NULL, 'Anklets category', '2025-05-05 20:15:36'),
(10, 'Toe Rings', NULL, 'Toe Rings category', '2025-05-05 20:15:36'),
(11, 'Maang Tikka', NULL, 'Maang Tikka category', '2025-05-05 20:15:36'),
(12, 'Nose Pins', NULL, 'Nose Pins category', '2025-05-05 20:15:36'),
(13, 'Brooches', NULL, 'Brooches category', '2025-05-05 20:15:36'),
(14, 'Mangalsutra', NULL, 'Mangalsutra category', '2025-05-05 20:15:36'),
(15, 'Waistbands', NULL, 'Waistbands category', '2025-05-05 20:15:36'),
(16, 'Hair Accessories', NULL, 'Hair Accessories category', '2025-05-05 20:15:36'),
(17, 'Cufflinks', NULL, 'Cufflinks category', '2025-05-05 20:15:36'),
(18, 'Tie Pins', NULL, 'Tie Pins category', '2025-05-05 20:15:36'),
(19, 'Religious Items', NULL, 'Religious Items category', '2025-05-05 20:15:36'),
(20, 'Kids Jewellery', NULL, 'Kids Jewellery category', '2025-05-05 20:15:36'),
(21, 'Ladies Ring', 1, 'Subcategory of Rings', '2025-05-05 20:15:36'),
(22, 'Gents Ring', 1, 'Subcategory of Rings', '2025-05-05 20:15:36'),
(23, 'Diamond Ring', 1, 'Subcategory of Rings', '2025-05-05 20:15:36'),
(24, 'Engagement Ring', 1, 'Subcategory of Rings', '2025-05-05 20:15:36'),
(25, 'Wedding Ring', 1, 'Subcategory of Rings', '2025-05-05 20:15:36'),
(26, 'Cocktail Ring', 1, 'Subcategory of Rings', '2025-05-05 20:15:36'),
(27, 'Gents Kada', 2, 'Subcategory of Kada', '2025-05-05 20:15:36'),
(28, 'Ladies Kada', 2, 'Subcategory of Kada', '2025-05-05 20:15:36'),
(29, 'Gold Kada', 2, 'Subcategory of Kada', '2025-05-05 20:15:36'),
(30, 'Silver Kada', 2, 'Subcategory of Kada', '2025-05-05 20:15:36'),
(31, 'Gold Chain', 3, 'Subcategory of Chains', '2025-05-05 20:15:36'),
(32, 'Silver Chain', 3, 'Subcategory of Chains', '2025-05-05 20:15:36'),
(33, 'Nawababi Chain', 3, 'Subcategory of Chains', '2025-05-05 20:15:36'),
(34, 'Pata Chain', 3, 'Subcategory of Chains', '2025-05-05 20:15:36'),
(35, 'Box Chain', 3, 'Subcategory of Chains', '2025-05-05 20:15:36'),
(36, 'Rope Chain', 3, 'Subcategory of Chains', '2025-05-05 20:15:36'),
(37, 'Gents Bracelet', 4, 'Subcategory of Bracelets', '2025-05-05 20:15:36'),
(38, 'Ladies Bracelet', 4, 'Subcategory of Bracelets', '2025-05-05 20:15:36'),
(39, 'Diamond Bracelet', 4, 'Subcategory of Bracelets', '2025-05-05 20:15:36'),
(40, 'Cuff Bracelet', 4, 'Subcategory of Bracelets', '2025-05-05 20:15:36'),
(41, 'Gold Necklace', 5, 'Subcategory of Necklaces', '2025-05-05 20:15:36'),
(42, 'Diamond Necklace', 5, 'Subcategory of Necklaces', '2025-05-05 20:15:36'),
(43, 'Choker', 5, 'Subcategory of Necklaces', '2025-05-05 20:15:36'),
(44, 'Temple Necklace', 5, 'Subcategory of Necklaces', '2025-05-05 20:15:36'),
(45, 'Kundan Necklace', 5, 'Subcategory of Necklaces', '2025-05-05 20:15:36'),
(46, 'Stud Earrings', 6, 'Subcategory of Earrings', '2025-05-05 20:15:36'),
(47, 'Hoop Earrings', 6, 'Subcategory of Earrings', '2025-05-05 20:15:36'),
(48, 'Jhumka', 6, 'Subcategory of Earrings', '2025-05-05 20:15:36'),
(49, 'Drop Earrings', 6, 'Subcategory of Earrings', '2025-05-05 20:15:36'),
(50, 'Chandelier Earrings', 6, 'Subcategory of Earrings', '2025-05-05 20:15:36'),
(51, 'Gold Bangle', 7, 'Subcategory of Bangles', '2025-05-05 20:15:36'),
(52, 'Kangan', 7, 'Subcategory of Bangles', '2025-05-05 20:15:36'),
(53, 'Diamond Bangle', 7, 'Subcategory of Bangles', '2025-05-05 20:15:36'),
(54, 'Kids Bangle', 7, 'Subcategory of Bangles', '2025-05-05 20:15:36'),
(55, 'Antique Bangle', 7, 'Subcategory of Bangles', '2025-05-05 20:15:36'),
(56, 'Gold Pendant', 8, 'Subcategory of Pendants', '2025-05-05 20:15:36'),
(57, 'Diamond Pendant', 8, 'Subcategory of Pendants', '2025-05-05 20:15:36'),
(58, 'Religious Pendant', 8, 'Subcategory of Pendants', '2025-05-05 20:15:36'),
(59, 'Heart Pendant', 8, 'Subcategory of Pendants', '2025-05-05 20:15:36'),
(60, 'Photo Pendant', 8, 'Subcategory of Pendants', '2025-05-05 20:15:36'),
(61, 'Silver Anklet', 9, 'Subcategory of Anklets', '2025-05-05 20:15:36'),
(62, 'Gold Anklet', 9, 'Subcategory of Anklets', '2025-05-05 20:15:36'),
(63, 'Designer Anklet', 9, 'Subcategory of Anklets', '2025-05-05 20:15:36'),
(64, 'Beaded Anklet', 9, 'Subcategory of Anklets', '2025-05-05 20:15:36'),
(65, 'Traditional Toe Ring', 10, 'Subcategory of Toe Rings', '2025-05-05 20:15:36'),
(66, 'Modern Toe Ring', 10, 'Subcategory of Toe Rings', '2025-05-05 20:15:36'),
(67, 'Wedding Maang Tikka', 11, 'Subcategory of Maang Tikka', '2025-05-05 20:15:36'),
(68, 'Casual Maang Tikka', 11, 'Subcategory of Maang Tikka', '2025-05-05 20:15:36'),
(69, 'Kundan Tikka', 11, 'Subcategory of Maang Tikka', '2025-05-05 20:15:36'),
(70, 'Diamond Nose Pin', 12, 'Subcategory of Nose Pins', '2025-05-05 20:15:36'),
(71, 'Gold Nose Pin', 12, 'Subcategory of Nose Pins', '2025-05-05 20:15:36'),
(72, 'Nath', 12, 'Subcategory of Nose Pins', '2025-05-05 20:15:36'),
(73, 'Men Brooch', 13, 'Subcategory of Brooches', '2025-05-05 20:15:36'),
(74, 'Ladies Brooch', 13, 'Subcategory of Brooches', '2025-05-05 20:15:36'),
(75, 'Wedding Brooch', 13, 'Subcategory of Brooches', '2025-05-05 20:15:36'),
(76, 'Short Mangalsutra', 14, 'Subcategory of Mangalsutra', '2025-05-05 20:15:36'),
(77, 'Long Mangalsutra', 14, 'Subcategory of Mangalsutra', '2025-05-05 20:15:36'),
(78, 'Fancy Mangalsutra', 14, 'Subcategory of Mangalsutra', '2025-05-05 20:15:36'),
(79, 'Gold Waistband', 15, 'Subcategory of Waistbands', '2025-05-05 20:15:36'),
(80, 'Beaded Waistband', 15, 'Subcategory of Waistbands', '2025-05-05 20:15:36'),
(81, 'Antique Waistband', 15, 'Subcategory of Waistbands', '2025-05-05 20:15:36'),
(82, 'Hair Clips', 16, 'Subcategory of Hair Accessories', '2025-05-05 20:15:36'),
(83, 'Hair Pins', 16, 'Subcategory of Hair Accessories', '2025-05-05 20:15:36'),
(84, 'Hair Chains', 16, 'Subcategory of Hair Accessories', '2025-05-05 20:15:36'),
(85, 'Formal Cufflinks', 17, 'Subcategory of Cufflinks', '2025-05-05 20:15:36'),
(86, 'Casual Cufflinks', 17, 'Subcategory of Cufflinks', '2025-05-05 20:15:36'),
(87, 'Gold Cufflinks', 17, 'Subcategory of Cufflinks', '2025-05-05 20:15:36'),
(88, 'Gold Tie Pin', 18, 'Subcategory of Tie Pins', '2025-05-05 20:15:36'),
(89, 'Diamond Tie Pin', 18, 'Subcategory of Tie Pins', '2025-05-05 20:15:36'),
(90, 'Om Pendant', 19, 'Subcategory of Religious Items', '2025-05-05 20:15:36'),
(91, 'Cross Pendant', 19, 'Subcategory of Religious Items', '2025-05-05 20:15:36'),
(92, 'Allah Pendant', 19, 'Subcategory of Religious Items', '2025-05-05 20:15:36'),
(93, 'Kids Bracelet', 20, 'Subcategory of Kids Jewellery', '2025-05-05 20:15:36'),
(94, 'Kids Chain', 20, 'Subcategory of Kids Jewellery', '2025-05-05 20:15:36'),
(95, 'Kids Earrings', 20, 'Subcategory of Kids Jewellery', '2025-05-05 20:15:36'),
(97, 'TOPS', NULL, 'Auto-created for TOPS', '2025-05-08 13:09:11'),
(98, 'MIX ORNAMENT', NULL, 'Auto-created for MIX', '2025-05-08 13:18:54'),
(99, 'SEtS', NULL, 'Auto-created for set', '2025-05-08 21:46:29'),
(100, 'SET EARING', NULL, '', '2025-05-09 14:10:05'),
(101, 'Necklace', 100, '', '2025-05-09 14:10:14'),
(102, 'Rmg', 1, NULL, '2025-05-09 22:40:05'),
(103, '8399', 1, NULL, '2025-05-09 22:52:41'),
(104, 'Mang Tika', NULL, 'Auto-created for Mang Tika', '2025-05-11 06:55:16'),
(105, 'MURTI', NULL, 'Auto-created for MURTI', '2025-06-12 17:37:14'),
(106, 'coin', NULL, 'Auto-created for COIN', '2025-06-13 19:32:52');

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_categories`
--

CREATE TABLE `jewellery_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jewellery_categories`
--

INSERT INTO `jewellery_categories` (`id`, `name`, `description`) VALUES
(1, 'Necklace', 'Gold or diamond necklace items'),
(2, 'Ring', 'All types of rings including gold, diamond, and stone'),
(3, 'Bracelet', 'Bracelets made from various materials'),
(4, 'Earrings', 'Studs, danglers, and hoop earrings'),
(5, 'Pendant', 'Gold and diamond pendants'),
(6, 'Bangles', 'Traditional and modern bangles'),
(7, 'Chains', 'Gold chains of various types'),
(8, 'Nose Pin', 'Nose pins with and without stones'),
(9, 'Anklet', 'Anklets in silver, gold, and designer styles'),
(10, 'Necklaces', 'Beautiful necklace collection'),
(11, 'Rings', 'Elegant ring designs'),
(12, 'Earrings', 'Stunning earring collection'),
(13, 'Bracelets', 'Stylish bracelet designs'),
(14, 'Pendants', 'Attractive pendant collection');

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_customer_order`
--

CREATE TABLE `jewellery_customer_order` (
  `id` int(11) NOT NULL,
  `FirmID` int(11) NOT NULL,
  `order_number` varchar(20) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `karigar_id` int(11) DEFAULT NULL,
  `order_date` datetime DEFAULT current_timestamp(),
  `expected_delivery_date` date DEFAULT NULL,
  `total_metal_amount` decimal(10,2) DEFAULT NULL,
  `total_making_charges` decimal(10,2) DEFAULT NULL,
  `total_stone_amount` decimal(10,2) DEFAULT NULL,
  `grand_total` decimal(10,2) DEFAULT NULL,
  `advance_amount` decimal(10,2) DEFAULT NULL,
  `remaining_amount` decimal(10,2) DEFAULT NULL,
  `payment_method` enum('cash','card','upi','bank_transfer') DEFAULT NULL,
  `payment_status` enum('pending','partial','completed') DEFAULT NULL,
  `order_status` enum('pending','in progress','ready','cancelled') DEFAULT NULL,
  `priority` enum('normal','high','urgent') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jewellery_customer_order`
--

INSERT INTO `jewellery_customer_order` (`id`, `FirmID`, `order_number`, `customer_id`, `karigar_id`, `order_date`, `expected_delivery_date`, `total_metal_amount`, `total_making_charges`, `total_stone_amount`, `grand_total`, `advance_amount`, `remaining_amount`, `payment_method`, `payment_status`, `order_status`, `priority`, `notes`, `created_at`, `updated_at`) VALUES
(1, 41, 'ORD-2025061416374463', 37, 6, '2025-06-14 16:37:44', NULL, 32490.00, 36.00, 0.00, 32526.72, 3000.00, 29526.72, 'cash', 'partial', 'in progress', 'normal', '', '2025-06-14 16:37:44', '2025-06-14 18:40:58'),
(2, 41, 'ORD-2025061416443768', 37, 6, '2025-06-14 16:44:37', NULL, 27224.00, 36.00, 0.00, 27260.64, 0.00, 27260.64, 'cash', 'pending', 'ready', 'normal', '', '2025-06-14 16:44:37', '2025-06-14 19:22:21'),
(3, 41, 'ORD-2025061416453132', 48, 6, '2025-06-14 16:45:31', NULL, 36299.00, 40.00, 0.00, 36339.52, 5000.00, 31339.52, 'cash', 'partial', 'ready', 'high', '', '2025-06-14 16:45:31', '2025-06-14 19:22:16'),
(8, 41, 'ORD-2025061419403679', 47, 7, '2025-06-14 19:40:36', NULL, 41744.00, 1380.00, 0.00, 43124.45, 0.00, 43124.45, 'cash', 'pending', 'in progress', 'normal', '', '2025-06-14 19:40:36', '2025-06-14 20:05:07'),
(9, 41, 'ORD-2025061422040563', 50, 7, '2025-06-14 22:04:05', NULL, 90748.00, 0.00, 0.00, 90748.80, 0.00, 90748.80, '', 'pending', 'ready', 'high', '', '2025-06-14 22:04:05', '2025-06-15 02:13:34');

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_details`
--

CREATE TABLE `jewellery_details` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `supplier_id` varchar(50) DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_inventory`
--

CREATE TABLE `jewellery_inventory` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_items`
--

CREATE TABLE `jewellery_items` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL DEFAULT 1,
  `product_id` varchar(11) NOT NULL,
  `source_id` int(11) NOT NULL,
  `source_type` varchar(50) NOT NULL,
  `jewelry_type` varchar(50) NOT NULL,
  `product_name` varchar(50) NOT NULL,
  `material_type` enum('Gold','Silver') NOT NULL,
  `purity` decimal(10,2) DEFAULT NULL,
  `huid_code` varchar(255) NOT NULL,
  `gross_weight` decimal(10,3) NOT NULL,
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `less_weight` float NOT NULL,
  `net_weight` decimal(10,3) NOT NULL,
  `stone_type` varchar(50) DEFAULT 'None',
  `stone_weight` decimal(10,3) DEFAULT 0.000,
  `stone_unit` varchar(100) NOT NULL,
  `stone_color` varchar(100) NOT NULL,
  `stone_clarity` varchar(100) NOT NULL,
  `stone_quality` varchar(50) NOT NULL DEFAULT 'none',
  `stone_price` decimal(10,0) NOT NULL DEFAULT 0,
  `making_charge` decimal(5,2) DEFAULT 0.00,
  `rate_per_gram` float NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `making_charge_type` varchar(20) DEFAULT 'percentage',
  `wastage_percentage` decimal(5,2) DEFAULT 0.00,
  `status` varchar(50) NOT NULL DEFAULT 'Available',
  `manufacturing_order_id` int(11) NOT NULL,
  `karigar_id` int(11) NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `Tray_no` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jewellery_items`
--

INSERT INTO `jewellery_items` (`id`, `firm_id`, `product_id`, `source_id`, `source_type`, `jewelry_type`, `product_name`, `material_type`, `purity`, `huid_code`, `gross_weight`, `cost_price`, `less_weight`, `net_weight`, `stone_type`, `stone_weight`, `stone_unit`, `stone_color`, `stone_clarity`, `stone_quality`, `stone_price`, `making_charge`, `rate_per_gram`, `description`, `created_at`, `updated_at`, `making_charge_type`, `wastage_percentage`, `status`, `manufacturing_order_id`, `karigar_id`, `image_path`, `supplier_id`, `Tray_no`, `quantity`) VALUES
(443, 41, 'R001', 0, '', 'Rings', 'RING', 'Gold', 92.00, '0', 3.600, 0.00, 0, 3.600, 'None', 0.000, '', '', '', '', 0, 10.00, 0, '', '2025-06-02 10:46:21', '2025-06-13 16:36:44', 'percentage', 0.00, 'Sold', 0, 0, '', 1, '1', 1),
(444, 41, 'T001', 0, '', 'TOPS', 'tops', 'Gold', 92.00, '0', 6.960, 0.00, 0.108, 6.852, 'Diamond', 0.600, 'ratti', 'E', '', 'VVS', 8960, 10.00, 0, '', '2025-06-02 11:50:05', '2025-06-13 19:02:07', 'percentage', 0.00, 'Sold', 0, 0, '', 1, '1', 1),
(445, 41, 'E001', 0, '', 'Earrings', 'earings', 'Gold', 92.00, '0', 3.600, 0.00, 2, 1.600, 'None', 0.000, '', '', '', '', 0, 10.00, 0, '', '2025-06-02 13:23:46', '2025-06-13 18:59:41', 'percentage', 0.00, 'Sold', 0, 0, '', 1, '2', 1),
(447, 41, 'J001', 0, '', 'Jhumka', 'jhunka', 'Gold', 92.00, '0', 5.600, 0.00, 0, 5.600, 'None', 0.000, '', '', '', '', 0, 10.00, 0, '', '2025-06-02 13:27:55', '2025-06-02 13:27:55', 'percentage', 0.00, 'Available', 0, 0, '', 1, '2', 1),
(448, 41, 'T002', 0, '', 'TOPS', 'tops', 'Gold', 92.00, '0', 0.690, 0.00, 0, 0.690, 'None', 0.000, '', '', '', '', 0, 10.00, 0, '', '2025-06-02 14:11:53', '2025-06-13 19:10:14', 'percentage', 0.00, 'Sold', 0, 0, '', 1, '1', 1),
(449, 41, 'M001', 0, '', 'MURTI', 'MURTI', 'Silver', 925.00, '0', 56.600, 0.00, 0, 56.600, 'None', 0.000, '', '', '', '', 0, 10.00, 0, '', '2025-06-12 12:07:14', '2025-06-12 12:28:02', 'percentage', 0.00, 'Available', 0, 0, '', 0, '', 1),
(450, 41, 'C001', 0, '', 'coin', 'COIN', 'Silver', 999.00, '0', 10.000, 0.00, 0, 10.000, 'None', 0.000, '', '', '', '', 0, 10.00, 0, '', '2025-06-13 14:02:52', '2025-06-13 14:03:15', 'percentage', 0.00, 'Sold', 0, 0, '', 0, '', 1),
(451, 41, 'JW250614708', 0, '', 'Necklace', 'rin', 'Gold', 92.60, '0', 5.600, 0.00, 0, 5.600, 'None', 0.000, '', '', '', 'none', 0, 11.00, 0, NULL, '2025-06-13 19:19:07', '2025-06-13 19:19:14', 'percentage', 0.00, 'Sold', 0, 0, '', 1, '', 0),
(452, 41, 'JW250614259', 0, '', 'Necklace', 'neklace', 'Gold', 75.60, '0', 10.000, 0.00, 0, 10.000, 'None', 0.000, '', '', '', 'none', 0, 10.00, 0, NULL, '2025-06-14 01:12:04', '2025-06-14 01:12:20', 'percentage', 0.00, 'Sold', 0, 0, '', 1, '', 0),
(453, 41, 'JW250614290', 0, '', 'Ring', 'RIN', 'Gold', 92.00, '0', 10.000, 0.00, 0, 10.000, 'None', 0.000, '', '', '', 'none', 0, 15.00, 0, NULL, '2025-06-14 01:24:34', '2025-06-14 01:24:38', 'percentage', 0.00, 'Sold', 0, 0, '', 1, '', 0),
(454, 41, 'JW250614750', 0, '', 'Bracelet', 'braclet', 'Gold', 75.60, '0', 10.000, 0.00, 0, 10.000, 'None', 0.000, '', '', '', 'none', 0, 10.00, 0, NULL, '2025-06-14 01:43:30', '2025-06-14 01:43:34', 'per_gram', 0.00, 'Sold', 0, 0, '', 1, '', 0),
(455, 41, 'JW250614423', 0, '', 'Ring', 'RING', 'Gold', 92.00, '0', 10.000, 0.00, 0, 10.000, 'None', 0.000, '', '', '', 'none', 0, 300.00, 0, NULL, '2025-06-14 07:27:32', '2025-06-14 07:53:27', 'per_gram', 0.00, 'Sold', 0, 0, '', 1, '', 0),
(456, 41, 'JW250614360', 0, '', 'Ring', 'ring', 'Gold', 92.00, '0', 10.000, 0.00, 0, 10.000, 'None', 0.000, '', '', '', 'none', 0, 300.00, 0, NULL, '2025-06-14 10:31:08', '2025-06-14 10:31:15', 'per_gram', 0.00, 'Sold', 0, 0, '', 1, '', 0),
(457, 41, 'JW250614947', 0, '', 'Earring', 'earings', 'Gold', 76.00, '0', 3.900, 0.00, 0, 3.900, 'None', 0.000, '', '', '', 'none', 0, 300.00, 0, NULL, '2025-06-14 10:32:22', '2025-06-14 10:32:26', 'per_gram', 0.00, 'Sold', 0, 0, '', 1, '', 0),
(458, 44, 'E002', 0, '', 'Earrings', 'RING', 'Gold', 92.00, '0', 20.600, 0.00, 0, 20.600, 'None', 0.000, '', '', '', '', 0, 15.00, 0, '', '2025-06-22 08:11:43', '2025-06-22 08:42:19', 'percentage', 0.00, 'Sold', 0, 0, '', 16, '', 1),
(460, 44, 'E003', 0, '', 'Earrings', 'RING', 'Gold', 92.00, '0', 20.600, 0.00, 0, 20.600, 'None', 0.000, '', '', '', '', 0, 15.00, 0, '', '2025-06-22 08:12:11', '2025-06-22 09:14:11', 'percentage', 0.00, 'Sold', 0, 0, '', 16, '', 1),
(461, 44, 'R002', 0, '', 'Rings', 'RING', 'Gold', 92.00, '0', 20.600, 0.00, 0, 20.600, 'None', 0.000, '', '', '', '', 0, 0.00, 0, '', '2025-06-22 08:12:28', '2025-06-22 09:39:02', 'fixed', 0.00, 'Sold', 0, 0, '', 16, '', 1),
(462, 44, 'R003', 113, 'Purchase', 'Rings', 'RIN', 'Gold', 92.00, '0', 20.000, 0.00, 0, 20.000, 'None', 0.000, '', '', '', '', 0, 15.00, 0, '', '2025-06-22 08:24:29', '2025-06-22 09:41:23', 'fixed', 0.00, 'Sold', 0, 0, '', 16, '', 1),
(463, 44, 'R004', 113, 'Purchase', 'Rings', 'RING', 'Gold', 92.00, '0', 10.000, 0.00, 0, 10.000, 'None', 0.000, '', '', '', '', 0, 15.00, 0, '', '2025-06-22 08:29:20', '2025-06-22 09:44:09', 'fixed', 0.00, 'Sold', 0, 0, '', 16, '', 1),
(464, 44, 'R005', 113, 'Purchase', 'Rings', 'RIN', 'Gold', 92.00, '0', 20.000, 0.00, 0, 20.000, 'None', 0.000, '', '', '', '', 0, 15.00, 0, '', '2025-06-22 08:32:32', '2025-06-22 20:23:47', 'fixed', 0.00, 'Sold', 0, 0, '', 16, '', 1),
(465, 44, 'R006', 113, 'Purchase', 'Rings', 'RING', 'Gold', 92.00, '0', 10.500, 0.00, 0, 10.500, 'None', 0.000, '', '', '', '', 0, 15.00, 0, '', '2025-06-22 08:34:17', '2025-06-22 20:43:13', 'fixed', 0.00, 'Sold', 0, 0, '', 16, '', 1),
(466, 44, 'R007', 120, 'Purchase', 'Rings', 'RING', 'Gold', 92.00, '0', 10.000, 0.00, 0, 10.000, 'None', 0.000, '', '', '', '', 0, 15.00, 0, '', '2025-06-22 21:45:00', '2025-06-22 21:45:00', 'percentage', 0.00, 'Available', 0, 0, '', 17, '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_manufacturing_orders`
--

CREATE TABLE `jewellery_manufacturing_orders` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `karigar_id` int(11) NOT NULL,
  `order_no` varchar(20) DEFAULT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `design_reference` varchar(255) DEFAULT NULL,
  `product_type` enum('Ring','Necklace','Bracelet','Earring','Pendant','Bangle','Chain') DEFAULT NULL,
  `metal_type` enum('Gold','Silver','Platinum') DEFAULT 'Gold',
  `gross_weight` decimal(10,3) DEFAULT NULL,
  `net_weight` decimal(10,3) DEFAULT NULL,
  `purity` decimal(5,2) DEFAULT NULL,
  `estimated_metal_weight` decimal(10,3) DEFAULT NULL,
  `stone_type` varchar(50) DEFAULT NULL,
  `stone_quality` varchar(50) DEFAULT NULL,
  `stone_size` varchar(50) DEFAULT NULL,
  `stone_quantity` int(11) DEFAULT NULL,
  `stone_weight` decimal(10,3) DEFAULT NULL,
  `stone_details` text DEFAULT NULL,
  `making_charge_type` enum('per_gram','percentage','fixed') DEFAULT NULL,
  `making_charge` decimal(10,2) DEFAULT NULL,
  `size_details` varchar(100) DEFAULT NULL,
  `design_customization` text DEFAULT NULL,
  `reference_images` text DEFAULT NULL,
  `expected_delivery` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `priority_level` enum('Normal','High','Urgent') DEFAULT 'Normal',
  `advance_amount` decimal(10,2) DEFAULT 0.00,
  `advance_used` decimal(10,2) DEFAULT 0.00,
  `advance_used_date` datetime DEFAULT NULL,
  `total_estimated` decimal(10,2) DEFAULT NULL,
  `status` enum('Pending','In Progress','Ready','Delivered','Cancelled') DEFAULT 'Pending',
  `production_status` enum('Not Started','Design','Wax','Casting','Filing','Stone Setting','Polishing','QC','Complete') DEFAULT 'Not Started',
  `completion_percentage` int(11) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `quality_check_notes` text DEFAULT NULL,
  `delivery_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_order_items`
--

CREATE TABLE `jewellery_order_items` (
  `id` int(11) NOT NULL,
  `karigar_id` int(11) NOT NULL,
  `firm_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `item_status` varchar(50) NOT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `product_type` varchar(50) DEFAULT NULL,
  `design_reference` varchar(100) DEFAULT NULL,
  `metal_type` varchar(50) DEFAULT NULL,
  `purity` decimal(5,2) DEFAULT NULL,
  `gross_weight` decimal(10,3) DEFAULT NULL,
  `less_weight` decimal(10,3) DEFAULT NULL,
  `net_weight` decimal(10,3) DEFAULT NULL,
  `metal_amount` decimal(10,2) DEFAULT NULL,
  `stone_type` varchar(50) DEFAULT NULL,
  `stone_quality` varchar(50) DEFAULT NULL,
  `stone_size` varchar(50) DEFAULT NULL,
  `stone_quantity` int(11) DEFAULT NULL,
  `stone_weight` decimal(10,3) DEFAULT NULL,
  `stone_unit` varchar(10) DEFAULT NULL,
  `stone_price` decimal(10,2) DEFAULT NULL,
  `stone_details` text DEFAULT NULL,
  `making_type` enum('per_gram','percentage','fixed') DEFAULT NULL,
  `making_charge_input` decimal(10,2) DEFAULT NULL,
  `making_charges` decimal(10,2) DEFAULT NULL,
  `size_details` text DEFAULT NULL,
  `design_customization` text DEFAULT NULL,
  `total_estimate` decimal(10,2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reference_images` text NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `issued_metal_type` varchar(50) DEFAULT NULL,
  `issued_purity` decimal(5,2) DEFAULT NULL,
  `issued_weight` decimal(10,3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jewellery_order_items`
--

INSERT INTO `jewellery_order_items` (`id`, `karigar_id`, `firm_id`, `order_id`, `item_status`, `item_name`, `product_type`, `design_reference`, `metal_type`, `purity`, `gross_weight`, `less_weight`, `net_weight`, `metal_amount`, `stone_type`, `stone_quality`, `stone_size`, `stone_quantity`, `stone_weight`, `stone_unit`, `stone_price`, `stone_details`, `making_type`, `making_charge_input`, `making_charges`, `size_details`, `design_customization`, `total_estimate`, `created_at`, `updated_at`, `reference_images`, `created_by`, `updated_by`, `issued_metal_type`, `issued_purity`, `issued_weight`) VALUES
(1, 6, 41, 1, 'pending', 'ring', 'Ring', '', 'Gold', 92.00, 3.600, 0.000, 3.600, 32490.72, 'None', '', '0', 0, 0.000, '0', 0.00, '', '', 10.00, 36.00, '24', '', 32526.72, '2025-06-14 16:37:44', '2025-06-14 18:40:58', '', NULL, NULL, NULL, NULL, NULL),
(2, 6, 41, 2, 'pending', 'ring', 'Necklace', '', 'Gold', 92.00, 3.000, 0.000, 3.000, 27224.64, 'None', '', '0', 0, 0.000, '0', 0.00, '', '', 12.00, 36.00, '24', '', 27260.64, '2025-06-14 16:44:37', '2025-06-14 19:22:21', '', NULL, NULL, NULL, NULL, NULL),
(3, 6, 41, 3, 'in-progress', 'ring', 'Ring', 'df', 'Gold', 92.00, 4.000, 0.000, 4.000, 36299.52, 'None', '', '0', 0, 0.000, '0', 0.00, '', '', 10.00, 40.00, '24', '', 36339.52, '2025-06-14 16:45:31', '2025-06-14 19:22:16', '', NULL, NULL, NULL, NULL, NULL),
(8, 6, 41, 8, 'pending', 'RING', 'Ring', 'NONE', 'Gold', 92.00, 4.600, 0.000, 4.600, 41744.45, 'None', '', '0', 0, 0.000, '0', 0.00, '', '', 300.00, 1380.00, '0', '', 43124.45, '2025-06-14 19:40:36', '2025-06-14 20:05:07', '', NULL, NULL, NULL, NULL, NULL),
(9, 7, 41, 9, 'in-progress', 'ring', 'Ring', '', 'Gold', 92.00, 10.000, 0.000, 10.000, 90748.80, 'None', '', '0', 0, 0.000, '0', 0.00, '', '', 0.00, 0.00, '0', '', 90748.80, '2025-06-14 22:04:05', '2025-06-15 02:13:34', '', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_payments`
--

CREATE TABLE `jewellery_payments` (
  `id` int(11) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` enum('customer_order','sale','loan','due_invoice','karigar_salary','purchase','expense','liability_invoice') NOT NULL,
  `party_type` enum('customer','karigar','vendor','staff','other','supplier') DEFAULT NULL,
  `party_id` int(11) DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_notes` varchar(100) DEFAULT NULL,
  `reference_no` varchar(50) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `transctions_type` enum('credit','debit') NOT NULL,
  `Firm_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jewellery_payments`
--

INSERT INTO `jewellery_payments` (`id`, `reference_id`, `reference_type`, `party_type`, `party_id`, `sale_id`, `payment_type`, `amount`, `payment_notes`, `reference_no`, `remarks`, `created_at`, `transctions_type`, `Firm_id`) VALUES
(186, 283, 'sale', 'customer', 1, 283, 'cash', 20000.00, NULL, '', '', '2025-05-29 15:42:51', 'credit', 1),
(187, 284, 'sale', 'customer', 2, 284, 'cash', 35774.79, NULL, '', '', '2025-05-29 18:14:47', 'credit', 1),
(188, 285, 'sale', 'customer', 1, 285, 'cash', 50000.00, NULL, '', '', '2025-05-29 18:56:09', 'credit', 1),
(189, NULL, '', 'customer', 1, 0, '0', 9000.00, '', '', '', '2025-05-29 16:27:13', '', 1),
(190, NULL, '', 'customer', 1, 0, '0', 9000.00, '', '', '', '2025-05-29 16:27:49', '', 1),
(191, NULL, '', 'customer', 1, 0, '0', 9000.00, '', '', '', '2025-05-29 16:34:59', '', 1),
(192, NULL, '', 'customer', 1, 0, '0', 9000.00, NULL, '', '', '2025-05-29 16:37:38', '', 1),
(193, 283, '', 'customer', 1, 283, 'Cash', 4999.98, '', 'PAY-20250529165616-1', 'FIFO allocation for Sale #283', '2025-05-29 16:56:16', 'credit', 1),
(194, 283, '', 'customer', 1, 283, 'Cash', 12666.10, '', 'PAY-20250529165816-1', 'FIFO allocation for Sale #283', '2025-05-29 16:58:16', 'credit', 1),
(195, 93, 'purchase', 'supplier', 1, NULL, '0', 50000.00, '0', 'df', '', '2025-05-31 20:12:46', 'debit', 1),
(196, 94, 'purchase', 'supplier', 2, NULL, '0', 6000.00, '0', 'df', '', '2025-05-31 20:33:54', 'debit', 1),
(197, 286, 'sale', 'customer', 47, 286, 'cash', 1163.72, NULL, '', '', '2025-06-13 19:33:15', 'credit', 1),
(198, 287, 'sale', 'customer', 37, 287, 'cash', 35644.83, NULL, '', '', '2025-06-13 22:06:44', 'credit', 1),
(199, 288, 'sale', 'customer', 37, 288, 'cash', 16337.44, NULL, '', '', '2025-06-14 00:29:41', 'credit', 1),
(200, 289, 'sale', 'customer', 47, 289, 'cash', 76772.38, NULL, '', '', '2025-06-14 00:32:07', 'credit', 1),
(201, 290, 'sale', 'customer', 37, 290, 'cash', 7066.03, NULL, '', '', '2025-06-14 00:40:14', 'credit', 1),
(202, 291, 'sale', 'customer', 47, 291, 'cash', 56249.66, NULL, '', '', '2025-06-14 00:49:14', 'credit', 1),
(203, 292, 'sale', 'customer', 47, 292, 'cash', 83757.81, NULL, '', '', '2025-06-14 06:42:20', 'credit', 1),
(204, 293, 'sale', 'customer', 37, 293, 'cash', 103034.89, NULL, '', '', '2025-06-14 06:54:38', 'credit', 1),
(205, 294, 'sale', 'customer', 47, 294, 'cash', 74696.80, NULL, '', '', '2025-06-14 07:13:34', 'credit', 1),
(206, 295, 'sale', 'customer', 48, 295, 'cash', 93483.80, NULL, '', '', '2025-06-14 13:23:27', 'credit', 1),
(207, 296, 'sale', 'customer', 48, 296, 'cash', 96597.31, NULL, '', '', '2025-06-14 16:01:15', 'credit', 1),
(208, 297, 'sale', 'customer', 37, 297, 'cash', 31355.16, NULL, '', '', '2025-06-14 16:02:26', 'credit', 1),
(209, 298, 'sale', 'customer', 51, 298, 'cash', 213841.99, NULL, '', '', '2025-06-22 14:12:19', 'credit', 1),
(210, 299, 'sale', 'customer', 51, 299, 'cash', 217786.84, NULL, '', '', '2025-06-22 14:44:11', 'credit', 1),
(211, 9, 'expense', 'other', NULL, NULL, 'Cash', 700.00, '', '', '', '2025-06-22 14:58:31', 'debit', 44),
(212, 10, 'expense', 'other', NULL, NULL, 'Cash', 390.00, 'hallamrk', '', '', '2025-06-22 15:04:13', 'debit', 44),
(213, 300, 'sale', 'customer', 51, 300, 'cash', 50000.00, NULL, '', '', '2025-06-22 15:09:02', 'credit', 1),
(214, 300, 'sale', 'customer', 51, 300, 'upi', 100000.00, NULL, '', '', '2025-06-22 15:09:02', 'credit', 1),
(215, 301, 'sale', 'customer', 51, 301, 'cash', 50000.00, NULL, '', '', '2025-06-22 15:11:23', 'credit', 44),
(216, 301, 'sale', 'customer', 51, 301, 'card', 25000.00, NULL, '', '', '2025-06-22 15:11:23', 'credit', 44),
(217, 301, 'sale', 'customer', 51, 301, 'bank_transfer', 50000.00, NULL, '', '', '2025-06-22 15:11:23', 'credit', 44),
(218, 302, 'sale', 'customer', 51, 302, 'cash', 91967.20, NULL, '', '', '2025-06-22 15:14:09', 'credit', 44),
(219, 117, 'purchase', 'supplier', 17, 0, 'Bank', 50000.00, 'Purchase payment for invoice 741', '741', 'Auto-logged with stock addition', '2025-06-22 15:37:37', 'debit', 44),
(220, 118, 'purchase', 'supplier', 17, 0, 'Cash', 400000.00, 'Purchase payment for invoice 7878', '7878', 'Auto-logged with stock addition', '2025-06-22 16:54:28', 'debit', 44),
(221, 119, 'purchase', 'supplier', 1, 0, 'Direct', 181635.40, 'Purchase payment for invoice DIR-20250622143250', 'DIR-20250622143250', 'Auto-logged with stock addition', '2025-06-22 18:02:50', 'debit', 44),
(222, 120, 'purchase', 'supplier', 17, 0, 'Bank', 50000.00, 'Purchase payment for invoice 4', '4', 'Auto-logged with stock addition', '2025-06-22 18:03:15', 'debit', 44),
(223, 300, 'due_invoice', 'customer', 51, 300, 'Cash', 5000.00, NULL, 'PAY-20250622145827-51', 'FIFO allocation for Sale #300', '2025-06-22 14:58:27', 'credit', 44),
(224, 300, 'due_invoice', 'customer', 51, 300, 'Cash', 40000.00, NULL, 'PAY-20250622154343-51', 'FIFO allocation for Sale #300', '2025-06-22 15:43:43', 'credit', 44),
(225, 300, 'due_invoice', 'customer', 51, 300, 'Cash', 65.96, NULL, 'PAY-20250622154407-51', 'FIFO allocation for Sale #300', '2025-06-22 15:44:07', 'credit', 44),
(226, 301, 'due_invoice', 'customer', 51, 301, 'Cash', 58000.00, NULL, 'PAY-20250622154522-51', 'FIFO allocation for Sale #301', '2025-06-22 15:45:22', 'credit', 44),
(227, 1, '', 'customer', 51, 1, 'UPI', 5000.00, '', 'PAY-20250622165754-51', 'Scheme Installment Payment for DHAN BARSO', '2025-06-22 16:57:54', 'credit', 44),
(228, 3, '', 'customer', 52, 3, 'Cash', 5000.00, '', 'PAY-20250622203542-52', 'Scheme Installment Payment for DHAN BARSO', '2025-06-22 20:35:42', 'credit', 44),
(229, 3, '', 'customer', 52, 3, 'Cash', 5000.00, '', 'PAY-20250622204823-52', 'Scheme Installment Payment for DHAN BARSO', '2025-06-22 20:48:23', 'credit', 44),
(230, 4, '', 'customer', 52, 4, 'Cash', 5000.00, '', 'PAY-20250622210506-52', 'Scheme Installment Payment for DHAN BARSO', '2025-06-22 21:05:06', 'credit', 44),
(231, 4, '', 'customer', 52, 4, 'Cash', 5000.00, '', 'PAY-20250622220248-52', 'Scheme Installment Payment for DHAN BARSO', '2025-06-22 22:02:48', 'credit', 44),
(232, 4, '', 'customer', 52, 4, 'Cash', 4500.00, '', 'PAY-20250622220608-52', 'Scheme Installment Payment for DHAN BARSO', '2025-06-22 22:06:08', 'credit', 44),
(233, 4, '', 'customer', 52, 4, 'Cash', 500.00, '', 'PAY-20250622221214-52', 'Scheme Installment Payment for DHAN BARSO', '2025-06-22 22:12:14', 'credit', 44),
(234, 4, '', 'customer', 52, 4, 'Cash', 5000.00, '', 'PAY-20250622221241-52', 'Scheme Installment Payment for DHAN BARSO', '2025-06-22 22:12:41', 'credit', 44),
(235, NULL, '', 'customer', 52, 0, '0', 4.00, '', 'PAY-20250622222131-52', 'General payment - Loan EMI', '2025-06-22 22:21:31', '', 44),
(236, 4, '', 'customer', 52, 4, 'Cash', 4000.00, '', 'PAY-20250622222300-52', 'Scheme Installment Payment for DHAN BARSO', '2025-06-22 22:23:00', 'credit', 44),
(237, 303, 'due_invoice', 'customer', 52, 303, 'Cash', 5000.00, NULL, 'PAY-20250622222406-52', 'FIFO allocation for Sale #303', '2025-06-22 22:24:06', 'credit', 44),
(238, 4, '', 'customer', 52, 4, 'Cash', 4000.00, '', 'PAY-20250622223418-52', 'Scheme Installment Payment for DHAN BARSO', '2025-06-22 22:34:18', 'credit', 44),
(239, 303, 'due_invoice', 'customer', 52, 303, 'Cash', 500.00, NULL, 'PAY-20250623021154-52', 'FIFO allocation for Sale #303', '2025-06-23 02:11:54', 'credit', 44),
(240, 304, 'sale', 'customer', 52, 304, 'bank_transfer', 50000.00, NULL, '', '', '2025-06-23 02:13:13', 'credit', 44),
(241, 121, 'purchase', 'supplier', 18, 0, 'Bank', 50000.00, 'Purchase payment for invoice 32', '32', 'Auto-logged with stock addition', '2025-06-23 02:15:20', 'debit', 44);

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_payments_details`
--

CREATE TABLE `jewellery_payments_details` (
  `id` int(11) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` enum('customer_order','sale','loan','due_invoice','payment_receipt','advance') NOT NULL,
  `party_type` enum('customer','karigar','vendor','staff','others') NOT NULL,
  `party_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_notes` varchar(100) DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `transactions_type` enum('credit','debit') NOT NULL,
  `Firm_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jewellery_payments_details`
--

INSERT INTO `jewellery_payments_details` (`id`, `reference_id`, `reference_type`, `party_type`, `party_id`, `sale_id`, `payment_type`, `amount`, `payment_notes`, `reference_no`, `remarks`, `created_at`, `transactions_type`, `Firm_id`) VALUES
(1, 22, '', '', 39, 22, 'credit', 50000.00, NULL, '', '', '2025-05-20 21:46:40', 'credit', 1),
(2, 23, '', '', 26, 23, 'credit', 60000.00, NULL, '', '', '2025-05-20 21:56:03', 'credit', 1),
(3, 24, '', '', 26, 24, 'credit', 5000.00, NULL, '', '', '2025-05-20 22:00:35', 'credit', 1),
(4, 25, '', '', 26, 25, 'credit', 50000.00, NULL, '', '', '2025-05-21 18:49:46', 'credit', 1),
(5, 26, '', '', 26, 26, 'credit', 50000.00, NULL, '', '', '2025-05-21 18:49:46', 'credit', 1),
(6, 27, '', '', 39, 27, 'credit', 15000.00, NULL, '', '', '2025-05-22 07:29:14', 'credit', 1),
(7, 41, '', '', 26, 41, 'credit', 5000.00, NULL, '', '', '2025-05-22 09:57:20', 'credit', 1),
(8, 43, '', '', 19, 43, 'credit', 5000.00, NULL, '', '', '2025-05-22 10:01:39', 'credit', 1),
(9, 44, '', '', 37, 44, 'credit', 808.00, NULL, '', '', '2025-05-22 10:09:17', 'credit', 1),
(10, 45, '', '', 18, 45, 'credit', 5000.00, NULL, '', '', '2025-05-22 10:14:03', 'credit', 1),
(11, 46, '', '', 18, 46, 'credit', 5000.00, NULL, '', '', '2025-05-22 10:15:08', 'credit', 1),
(12, 47, '', '', 18, 47, 'credit', 5000.00, NULL, '', '', '2025-05-22 10:15:58', 'credit', 1),
(13, 48, '', '', 18, 48, 'credit', 5000.00, NULL, '', '', '2025-05-22 10:16:45', 'credit', 1),
(14, 49, '', '', 26, 49, 'credit', 65.00, NULL, '', '', '2025-05-22 10:18:09', 'credit', 1),
(15, 50, '', '', 26, 50, 'credit', 65.00, NULL, '', '', '2025-05-22 10:19:09', 'credit', 1),
(16, 51, '', '', 21, 51, 'credit', 6000.00, NULL, '', '', '2025-05-22 10:29:19', 'credit', 1),
(17, 52, '', '', 18, 52, 'credit', 5000.00, NULL, '', '', '2025-05-22 10:37:48', 'credit', 1),
(18, 53, '', '', 18, 53, 'credit', 9900.00, NULL, '', '', '2025-05-22 19:25:40', 'credit', 1),
(19, 54, '', '', 21, 54, 'credit', 5000.00, NULL, '', '', '2025-05-22 20:54:12', 'credit', 1),
(20, 55, '', '', 18, 55, 'credit', 422.00, NULL, '', '', '2025-05-22 21:35:37', 'credit', 1),
(21, 56, '', '', 26, 56, 'credit', 5000.00, NULL, '', '', '2025-05-22 21:42:39', 'credit', 1),
(22, 57, '', '', 18, 57, 'credit', 9000.00, NULL, '', '', '2025-05-22 21:47:39', 'credit', 1),
(23, 58, '', '', 18, 58, 'credit', 9000.00, NULL, '', '', '2025-05-22 21:48:04', 'credit', 1),
(24, 61, '', '', 21, 61, 'credit', 1000.00, NULL, '', '', '2025-05-22 22:30:44', 'credit', 1),
(25, 64, '', '', 21, 64, 'credit', 3000.00, NULL, '', '', '2025-05-23 12:32:48', 'credit', 1),
(26, 65, '', '', 26, 65, 'credit', 3000.00, NULL, '', '', '2025-05-23 14:45:36', 'credit', 1),
(27, 66, '', '', 19, 66, 'credit', 5000.00, NULL, '', '', '2025-05-25 06:57:48', 'credit', 1),
(28, 67, '', '', 26, 67, 'credit', 50000.00, NULL, '', '', '2025-05-25 08:38:23', 'credit', 1),
(29, 68, '', '', 26, 68, 'credit', 50000.00, NULL, '', '', '2025-05-25 08:38:29', 'credit', 1),
(30, 69, '', '', 26, 69, 'credit', 50000.00, NULL, '', '', '2025-05-25 08:38:32', 'credit', 1),
(31, 70, '', '', 26, 70, 'credit', 50000.00, NULL, '', '', '2025-05-25 08:38:48', 'credit', 1),
(32, 71, '', '', 46, 71, 'credit', 5000.00, NULL, '', '', '2025-05-25 08:40:49', 'credit', 1),
(33, 72, '', '', 47, 72, 'credit', 5000.00, NULL, '', '', '2025-05-25 08:50:59', 'credit', 1),
(0, 1, '', '', 37, 1, 'credit', 3000.00, NULL, '', '', '2025-06-14 16:37:44', 'credit', 41),
(0, 3, '', '', 48, 3, 'credit', 5000.00, NULL, '', '', '2025-06-14 16:45:31', 'credit', 41);

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_price_config`
--

CREATE TABLE `jewellery_price_config` (
  `id` int(11) NOT NULL,
  `material_type` varchar(50) NOT NULL,
  `purity` decimal(5,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `rate` decimal(12,2) NOT NULL,
  `effective_date` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `firm_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `jewellery_price_config`
--

INSERT INTO `jewellery_price_config` (`id`, `material_type`, `purity`, `unit`, `rate`, `effective_date`, `created_at`, `firm_id`) VALUES
(3, 'Gold', 99.99, 'gram', 9689.00, '2025-05-29', '2025-05-29 13:52:48', 1),
(4, 'Silver', 999.90, '10gram', 6989.00, '2025-05-29', '2025-05-29 13:53:09', 1),
(5, 'Gold', 99.99, 'gram', 9810.00, '2025-05-30', '2025-05-30 15:06:10', 2),
(6, 'Silver', 999.90, 'gram', 99.99, '2025-05-30', '2025-05-30 15:06:36', 2),
(7, 'Gold', 99.99, 'gram', 9864.00, '2025-06-01', '2025-05-30 19:49:11', 41),
(8, 'Silver', 999.90, 'gram', 99.63, '2025-06-01', '2025-05-30 19:49:17', 41),
(9, 'Gold', 99.99, 'gram', 9991.00, '2025-06-22', '2025-06-22 14:16:49', 44),
(10, 'Silver', 999.90, 'gram', 89.44, '2025-06-22', '2025-06-22 14:17:05', 44);

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_products`
--

CREATE TABLE `jewellery_products` (
  `id` int(11) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `huid_code` varchar(100) DEFAULT NULL,
  `material_type` enum('Gold','Diamond','Stone') NOT NULL,
  `purity` varchar(20) NOT NULL,
  `weight` decimal(10,3) DEFAULT NULL,
  `net_weight` decimal(10,3) DEFAULT NULL,
  `unit` varchar(20) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `stone_weight` decimal(10,3) DEFAULT NULL,
  `stone_type` varchar(50) DEFAULT NULL,
  `stone_unit` varchar(10) DEFAULT NULL,
  `making_charge` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_product_image`
--

CREATE TABLE `jewellery_product_image` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `firm_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jewellery_product_image`
--

INSERT INTO `jewellery_product_image` (`id`, `product_id`, `image_url`, `is_primary`, `created_at`, `firm_id`) VALUES
(1, 443, 'uploads/jewelry/R001_1748861181_0.jpg', 1, '2025-06-02 10:46:21', 41),
(2, 443, 'uploads/jewelry/R001_1748861181_1.jpg', 0, '2025-06-02 10:46:21', 41),
(3, 444, 'uploads/jewelry/T001_1748865005_0.jpg', 1, '2025-06-02 11:50:05', 1),
(4, 444, 'uploads/jewelry/T001_1748865005_1.jpg', 0, '2025-06-02 11:50:05', 1),
(5, 445, 'uploads/jewelry/E001_1748870626_0.jpg', 1, '2025-06-02 13:23:46', 1),
(6, 445, 'uploads/jewelry/E001_1748870626_1.jpg', 0, '2025-06-02 13:23:46', 1),
(7, 447, 'uploads/jewelry/J001_1748870875_0.jpg', 1, '2025-06-02 13:27:55', 1),
(8, 447, 'uploads/jewelry/J001_1748870875_1.jpg', 0, '2025-06-02 13:27:55', 1),
(9, 464, 'uploads/jewelry/R005_1750581152_0.jpg', 1, '2025-06-22 08:32:32', 1),
(10, 464, 'uploads/jewelry/R005_1750581152_1.jpg', 0, '2025-06-22 08:32:32', 1);

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_purchases`
--

CREATE TABLE `jewellery_purchases` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `invoice_number` varchar(255) DEFAULT NULL,
  `purchase_date` datetime DEFAULT NULL,
  `material_type` varchar(50) NOT NULL,
  `purity` int(11) NOT NULL,
  `total_items` int(11) DEFAULT NULL,
  `total_gross_weight` decimal(10,2) DEFAULT NULL,
  `total_net_weight` decimal(10,2) DEFAULT NULL,
  `rate_per_gram` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','partial','paid') DEFAULT 'pending',
  `updated_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `gst_applicable` varchar(50) NOT NULL,
  `gst_amount` int(11) NOT NULL,
  `payment_mode` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jewellery_purchases`
--

INSERT INTO `jewellery_purchases` (`id`, `firm_id`, `supplier_id`, `invoice_number`, `purchase_date`, `material_type`, `purity`, `total_items`, `total_gross_weight`, `total_net_weight`, `rate_per_gram`, `total_amount`, `paid_amount`, `status`, `updated_at`, `created_at`, `gst_applicable`, `gst_amount`, `payment_mode`) VALUES
(171, 0, 1, 'IN001', '2025-04-27 12:19:08', '', 0, 4, 37.85, 37.85, 9810.00, 0.00, 0.00, 'pending', '2025-04-27 12:36:09', '0000-00-00 00:00:00', '', 0, ''),
(172, 0, 1, 'IN002', '2025-04-27 14:32:03', '', 0, 2, 11.50, 11.50, 9810.00, 0.00, 0.00, 'pending', '2025-04-29 23:52:17', '0000-00-00 00:00:00', '', 0, ''),
(177, 1, 1, '6656', '2025-04-30 00:00:00', '', 0, 1, 5.60, 5.60, 9810.00, 54936.00, 50000.00, 'paid', '2025-04-30 22:17:00', '2025-04-30 22:17:00', '', 0, 'Cash'),
(178, 1, 1, '79', '2025-01-05 00:00:00', '', 0, 1, 12.60, 12.60, 9810.00, 123606.00, 7890.00, 'paid', '2025-04-30 22:18:10', '2025-04-30 22:18:10', '', 0, 'Cash'),
(179, 1, 1, '966', '2025-04-30 00:00:00', '', 0, 2, 13.57, 12.97, 9810.00, 137135.70, 90000.00, 'paid', '2025-04-30 22:40:28', '2025-04-30 22:40:28', '', 0, 'Cash'),
(180, 0, 1, '890', '2025-05-05 19:15:53', '', 0, 5, 27.10, 26.76, 9810.00, 0.00, 0.00, 'pending', '2025-05-09 07:42:20', '0000-00-00 00:00:00', '', 0, ''),
(181, 0, 1, '789', '2025-05-06 01:15:14', '', 0, 3, 15.00, 15.00, 9810.00, 0.00, 0.00, 'pending', '2025-05-06 17:54:22', '0000-00-00 00:00:00', '', 0, ''),
(182, 0, 1, '8999', '2025-05-06 19:06:47', '', 0, 2, 13.20, 13.20, 9810.00, 0.00, 0.00, 'pending', '2025-05-06 21:28:57', '0000-00-00 00:00:00', '', 0, ''),
(183, 0, 1, '7890', '2025-05-07 15:38:11', '', 0, 2, 10.80, 10.80, 9810.00, 0.00, 0.00, 'pending', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', 0, ''),
(184, 0, 1, 'Uo29', '2025-05-07 21:05:25', '', 0, 1, 5.30, 5.30, 9810.00, 0.00, 0.00, 'pending', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', 0, ''),
(185, 0, 1, '899', '2025-05-09 14:10:54', '', 0, 5, 31.58, 31.58, 9810.00, 0.00, 0.00, 'pending', '2025-05-13 02:01:38', '0000-00-00 00:00:00', '', 0, '');

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_purchase_invoices`
--

CREATE TABLE `jewellery_purchase_invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `supplier_id` varchar(255) DEFAULT NULL,
  `purchase_type` enum('Bulk','Finished') NOT NULL,
  `purchase_date` date NOT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `Material_type` varchar(50) NOT NULL,
  `Weight` float NOT NULL,
  `Rate` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_sales`
--

CREATE TABLE `jewellery_sales` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `sale_date` date NOT NULL,
  `total_metal_amount` decimal(10,2) DEFAULT 0.00,
  `total_stone_amount` decimal(10,2) DEFAULT 0.00,
  `total_making_charges` decimal(10,2) DEFAULT 0.00,
  `total_other_charges` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `urd_amount` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `gst_amount` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(10,2) NOT NULL,
  `total_paid_amount` decimal(10,2) DEFAULT 0.00,
  `advance_amount` int(11) NOT NULL,
  `due_amount` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('Paid','Unpaid','Partial') DEFAULT 'Unpaid',
  `payment_method` varchar(50) DEFAULT NULL,
  `is_gst_applicable` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) NOT NULL,
  `coupon_discount` decimal(10,2) DEFAULT 0.00,
  `loyalty_discount` decimal(10,2) DEFAULT 0.00,
  `manual_discount` decimal(10,2) DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `transaction_type` varchar(550) NOT NULL DEFAULT 'Sale'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jewellery_sales`
--

INSERT INTO `jewellery_sales` (`id`, `invoice_no`, `firm_id`, `customer_id`, `sale_date`, `total_metal_amount`, `total_stone_amount`, `total_making_charges`, `total_other_charges`, `discount`, `urd_amount`, `subtotal`, `gst_amount`, `grand_total`, `total_paid_amount`, `advance_amount`, `due_amount`, `payment_status`, `payment_method`, `is_gst_applicable`, `notes`, `created_at`, `updated_at`, `user_id`, `coupon_discount`, `loyalty_discount`, `manual_discount`, `coupon_code`, `transaction_type`) VALUES
(283, 'IN01', 1, 1, '2025-05-29', 33212.74, 0.00, 3321.27, 35.00, 0.00, 0.00, 36569.01, 1097.07, 37666.08, 20000.00, 0, 0.00, '', '[{\"type\":\"cash\",\"amount\":20000,\"reference\":null,\"o', 1, '', '2025-05-29 10:12:51', '2025-05-29 14:58:16', 1, 0.00, 0.00, 0.00, '', 'Sale'),
(284, 'NG01', 1, 2, '2025-05-29', 32490.72, 0.00, 3249.07, 35.00, 0.00, 0.00, 35774.79, 0.00, 35774.79, 35774.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":35774.79,\"reference\":null', 0, '', '2025-05-29 12:44:47', '2025-05-29 12:44:47', 1, 0.00, 0.00, 0.00, '', 'Sale'),
(285, 'IN02', 1, 1, '2025-05-29', 57671.03, 0.00, 5767.10, 35.00, 0.00, 0.00, 63473.13, 1904.19, 65377.32, 50000.00, 0, 15377.32, 'Partial', '[{\"type\":\"cash\",\"amount\":50000,\"reference\":null,\"o', 1, '', '2025-05-29 13:26:09', '2025-05-29 13:26:09', 1, 0.00, 0.00, 0.00, '', 'Sale'),
(286, 'IN03', 1, 47, '2025-06-13', 995.30, 0.00, 99.53, 35.00, 0.00, 0.00, 1129.83, 33.89, 1163.72, 1163.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":1163.72,\"reference\":null,', 1, '', '2025-06-13 14:03:15', '2025-06-13 14:03:15', 1, 0.00, 0.00, 0.00, '', 'Sale'),
(287, 'NG02', 1, 37, '2025-06-13', 32669.57, 0.00, 3266.96, 35.00, 3597.15, 0.00, 35644.83, 0.00, 35644.83, 35644.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":35644.83,\"reference\":null', 0, '', '2025-06-13 16:36:44', '2025-06-13 16:36:44', 1, 3597.15, 0.00, 0.00, 'WELCOME10', 'Sale'),
(288, 'IN04', 1, 37, '2025-06-14', 14519.81, 0.00, 1451.98, 35.00, 1600.68, 0.00, 15861.59, 475.85, 16337.44, 16337.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":16337.44,\"reference\":null', 1, '', '2025-06-13 18:59:41', '2025-06-13 18:59:41', 1, 1600.68, 0.00, 0.00, 'WELCOME10', 'Sale'),
(289, 'NG03', 1, 47, '2025-06-14', 62181.08, 8960.00, 6218.11, 35.00, 7739.42, 0.00, 76772.38, 0.00, 76772.38, 76772.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":76772.38,\"reference\":null', 0, '', '2025-06-13 19:02:07', '2025-06-13 19:02:07', 1, 7739.42, 0.00, 0.00, 'WELCOME10', 'Sale'),
(290, 'IN05', 1, 37, '2025-06-14', 6261.67, 0.00, 626.17, 35.00, 692.28, 0.00, 6860.22, 205.81, 7066.03, 7066.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":7066.03,\"reference\":null,', 1, '', '2025-06-13 19:10:14', '2025-06-13 19:10:14', 1, 692.28, 0.00, 0.00, 'WELCOME10', 'Sale'),
(291, 'NG04', 1, 47, '2025-06-14', 51150.74, 0.00, 5626.58, 35.00, 5681.23, 0.00, 56249.66, 0.00, 56249.66, 56249.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":56249.66,\"reference\":null', 0, '', '2025-06-13 19:19:14', '2025-06-13 19:19:14', 1, 5681.23, 0.00, 0.00, 'WELCOME10', 'Sale'),
(292, 'IN06', 1, 47, '2025-06-14', 74571.80, 0.00, 7457.18, 35.00, 8206.40, 0.00, 81318.26, 2439.55, 83757.81, 83757.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":83757.81,\"reference\":null', 1, '', '2025-06-14 01:12:20', '2025-06-14 01:12:20', 1, 8206.40, 0.00, 0.00, 'WELCOME10', 'Sale'),
(293, 'NG05', 41, 37, '2025-06-14', 90748.80, 0.00, 13612.32, 35.00, 10439.61, 0.00, 103034.89, 0.00, 103034.89, 103034.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":103034.89,\"reference\":nul', 0, '', '2025-06-14 01:24:38', '2025-06-14 01:24:38', 1, 10439.61, 0.00, 0.00, 'WELCOME10', 'Sale'),
(294, 'NG06', 41, 47, '2025-06-14', 74571.80, 0.00, 100.00, 35.00, 7470.68, 0.00, 74696.80, 0.00, 74696.80, 74696.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":74696.8,\"reference\":null,', 0, '', '2025-06-14 01:43:34', '2025-06-14 01:43:34', 1, 7470.68, 0.00, 0.00, 'WELCOME10', 'Sale'),
(295, 'NG07', 41, 48, '2025-06-14', 90748.80, 0.00, 3000.00, 35.00, 300.00, 0.00, 93483.80, 0.00, 93483.80, 93483.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":93483.8,\"reference\":null,', 0, '', '2025-06-14 07:53:27', '2025-06-14 07:53:27', 1, 300.00, 0.00, 0.00, 'WELCOME10', 'Sale'),
(296, 'IN07', 41, 48, '2025-06-14', 90748.80, 0.00, 3000.00, 35.00, 0.00, 0.00, 93783.80, 2813.51, 96597.31, 96597.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":96597.31,\"reference\":null', 1, '', '2025-06-14 10:31:15', '2025-06-14 10:31:15', 1, 0.00, 0.00, 0.00, '', 'Sale'),
(297, 'IN08', 41, 37, '2025-06-14', 29236.90, 0.00, 1170.00, 35.00, 0.00, 0.00, 30441.90, 913.26, 31355.16, 31355.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":31355.16,\"reference\":null', 1, '', '2025-06-14 10:32:26', '2025-06-14 10:32:26', 1, 0.00, 0.00, 0.00, '', 'Sale'),
(298, 'NG08', 44, 51, '2025-06-22', 185919.12, 0.00, 27887.87, 35.00, 0.00, 0.00, 213841.99, 0.00, 213841.99, 213841.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":213841.99,\"reference\":nul', 0, '', '2025-06-22 08:42:19', '2025-06-22 08:42:19', 43, 0.00, 0.00, 0.00, '', 'Sale'),
(299, 'NG09', 44, 51, '2025-06-22', 189349.43, 0.00, 28402.41, 35.00, 0.00, 0.00, 217786.84, 0.00, 217786.84, 217786.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":217786.84,\"reference\":nul', 0, '', '2025-06-22 09:14:11', '2025-06-22 09:14:11', 43, 0.00, 0.00, 0.00, '', 'Sale'),
(300, 'IN09', 44, 51, '2025-06-22', 189349.43, 0.00, 0.00, 35.00, 0.00, 0.00, 189384.43, 5681.53, 195065.96, 195065.96, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":50000,\"reference\":null,\"o', 1, '', '2025-06-22 09:39:02', '2025-06-22 13:44:07', 26, 0.00, 0.00, 0.00, '', 'Sale'),
(301, 'NG10', 44, 51, '2025-06-22', 183834.40, 0.00, 15.00, 35.00, 0.00, 0.00, 183884.40, 0.00, 183884.40, 183000.00, 0, 884.40, 'Partial', '[{\"type\":\"cash\",\"amount\":50000,\"reference\":null,\"o', 0, '', '2025-06-22 09:41:23', '2025-06-22 13:45:22', 26, 0.00, 0.00, 0.00, '', 'Sale'),
(302, 'NG11', 44, 51, '2025-06-22', 91917.20, 0.00, 15.00, 35.00, 0.00, 0.00, 91967.20, 0.00, 91967.20, 91967.00, 0, 0.00, 'Paid', '[{\"type\":\"cash\",\"amount\":91967.2,\"reference\":null,', 0, '', '2025-06-22 09:44:09', '2025-06-22 09:44:09', 26, 0.00, 0.00, 0.00, '', 'Sale'),
(303, 'NG12', 44, 52, '2025-06-23', 183834.40, 0.00, 15.00, 35.00, 0.00, 0.00, 183884.40, 0.00, 183884.40, 5500.00, 0, 178384.40, 'Partial', '[{\"type\":\"cash\",\"amount\":0,\"reference\":null,\"order', 0, '', '2025-06-22 20:23:47', '2025-06-22 20:41:54', 26, 0.00, 0.00, 0.00, '', 'Sale'),
(304, 'IN10', 44, 52, '2025-06-23', 96513.06, 0.00, 15.00, 35.00, 0.00, 0.00, 96563.06, 2896.89, 99459.95, 50000.00, 0, 49459.95, 'Partial', '[{\"type\":\"bank_transfer\",\"amount\":50000,\"reference', 1, '', '2025-06-22 20:43:13', '2025-06-22 20:43:13', 26, 0.00, 0.00, 0.00, '', 'Sale');

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_sales_items`
--

CREATE TABLE `jewellery_sales_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `huid_code` varchar(50) DEFAULT NULL,
  `rate_24k` decimal(10,2) DEFAULT 0.00,
  `purity` decimal(5,2) DEFAULT 0.00,
  `purity_rate` decimal(10,2) DEFAULT 0.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `metal_amount` decimal(10,2) DEFAULT 0.00,
  `stone_type` varchar(100) DEFAULT NULL,
  `stone_weight` decimal(10,3) DEFAULT 0.000,
  `stone_price` decimal(10,2) DEFAULT 0.00,
  `making_type` varchar(100) DEFAULT NULL,
  `making_rate` decimal(10,2) DEFAULT 0.00,
  `making_charges` decimal(10,2) DEFAULT 0.00,
  `hm_charges` decimal(10,2) DEFAULT 0.00,
  `other_charges` decimal(10,2) DEFAULT 0.00,
  `total_charges` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jewellery_sales_items`
--

INSERT INTO `jewellery_sales_items` (`id`, `sale_id`, `product_id`, `product_name`, `huid_code`, `rate_24k`, `purity`, `purity_rate`, `gross_weight`, `less_weight`, `net_weight`, `metal_amount`, `stone_type`, `stone_weight`, `stone_price`, `making_type`, `making_rate`, `making_charges`, `hm_charges`, `other_charges`, `total_charges`, `total`, `created_at`, `updated_at`) VALUES
(243, 283, 438, '1', '0', 9810.00, 92.00, 9025.20, 3.680, 0.000, 3.680, 33212.74, 'None', 0.000, 0.00, 'percentage', 10.00, 3321.27, 35.00, 0.00, 3356.27, 36569.01, '2025-05-29 10:12:51', '2025-05-29 10:12:51'),
(244, 284, 441, 'Ring', 'HUIO56', 9810.00, 92.00, 9025.20, 3.600, 0.000, 3.600, 32490.72, 'None', 0.000, 0.00, 'percentage', 10.00, 3249.07, 35.00, 0.00, 3284.07, 35774.79, '2025-05-29 12:44:47', '2025-05-29 12:44:47'),
(245, 285, 0, 'Earings', 'HUI', 9810.00, 92.00, 9025.20, 6.390, 0.000, 6.390, 57671.03, 'None', 0.000, 0.00, 'percentage', 10.00, 5767.10, 35.00, 0.00, 5802.10, 63473.13, '2025-05-29 13:26:09', '2025-05-29 13:26:09'),
(246, 286, 450, 'COIN', '0', 99.63, 999.00, 99.53, 10.000, 0.000, 10.000, 995.30, 'None', 0.000, 0.00, 'percentage', 10.00, 99.53, 35.00, 0.00, 134.53, 1129.83, '2025-06-13 14:03:15', '2025-06-13 14:03:15'),
(247, 287, 443, 'RING', '0', 9864.00, 92.00, 9074.88, 3.600, 0.000, 3.600, 32669.57, 'None', 0.000, 0.00, 'percentage', 10.00, 3266.96, 35.00, 0.00, 3301.96, 35971.53, '2025-06-13 16:36:44', '2025-06-13 16:36:44'),
(248, 288, 445, 'earings', '0', 9864.00, 92.00, 9074.88, 3.600, 2.000, 1.600, 14519.81, 'None', 0.000, 0.00, 'percentage', 10.00, 1451.98, 35.00, 0.00, 1486.98, 16006.79, '2025-06-13 18:59:41', '2025-06-13 18:59:41'),
(249, 289, 444, 'tops', '0', 9864.00, 92.00, 9074.88, 6.960, 0.108, 6.852, 62181.08, 'Diamond', 0.600, 8960.00, 'percentage', 10.00, 6218.11, 35.00, 0.00, 6253.11, 77394.19, '2025-06-13 19:02:07', '2025-06-13 19:02:07'),
(250, 290, 448, 'tops', '0', 9864.00, 92.00, 9074.88, 0.690, 0.000, 0.690, 6261.67, 'None', 0.000, 0.00, 'percentage', 10.00, 626.17, 35.00, 0.00, 661.17, 6922.84, '2025-06-13 19:10:14', '2025-06-13 19:10:14'),
(251, 291, 451, 'rin', '', 9864.00, 92.60, 9134.06, 5.600, 0.000, 5.600, 51150.74, 'None', 0.000, 0.00, 'percentage', 11.00, 5626.58, 35.00, 0.00, 5661.58, 56812.32, '2025-06-13 19:19:14', '2025-06-13 19:19:14'),
(252, 292, 452, 'neklace', '', 9864.00, 75.60, 7457.18, 10.000, 0.000, 10.000, 74571.80, 'None', 0.000, 0.00, 'percentage', 10.00, 7457.18, 35.00, 0.00, 7492.18, 82063.98, '2025-06-14 01:12:20', '2025-06-14 01:12:20'),
(253, 293, 453, 'RIN', '', 9864.00, 92.00, 9074.88, 10.000, 0.000, 10.000, 90748.80, 'None', 0.000, 0.00, 'percentage', 15.00, 13612.32, 35.00, 0.00, 13647.32, 104396.12, '2025-06-14 01:24:38', '2025-06-14 01:24:38'),
(254, 294, 454, 'braclet', '', 9864.00, 75.60, 7457.18, 10.000, 0.000, 10.000, 74571.80, 'None', 0.000, 0.00, 'per_gram', 10.00, 100.00, 35.00, 0.00, 135.00, 74706.80, '2025-06-14 01:43:34', '2025-06-14 01:43:34'),
(255, 295, 455, 'RING', '0', 9864.00, 92.00, 9074.88, 10.000, 0.000, 10.000, 90748.80, 'None', 0.000, 0.00, 'per_gram', 300.00, 3000.00, 35.00, 0.00, 3035.00, 93783.80, '2025-06-14 07:53:27', '2025-06-14 07:53:27'),
(256, 296, 456, 'ring', '', 9864.00, 92.00, 9074.88, 10.000, 0.000, 10.000, 90748.80, 'None', 0.000, 0.00, 'per_gram', 300.00, 3000.00, 35.00, 0.00, 3035.00, 93783.80, '2025-06-14 10:31:15', '2025-06-14 10:31:15'),
(257, 297, 457, 'earings', '', 9864.00, 76.00, 7496.64, 3.900, 0.000, 3.900, 29236.90, 'None', 0.000, 0.00, 'per_gram', 300.00, 1170.00, 35.00, 0.00, 1205.00, 30441.90, '2025-06-14 10:32:26', '2025-06-14 10:32:26'),
(258, 298, 458, 'RING', '0', 9810.00, 92.00, 9025.20, 20.600, 0.000, 20.600, 185919.12, 'None', 0.000, 0.00, 'percentage', 15.00, 27887.87, 35.00, 0.00, 27922.87, 213841.99, '2025-06-22 08:42:19', '2025-06-22 08:42:19'),
(259, 299, 460, 'RING', '0', 9991.00, 92.00, 9191.72, 20.600, 0.000, 20.600, 189349.43, 'None', 0.000, 0.00, 'percentage', 15.00, 28402.41, 35.00, 0.00, 28437.41, 217786.84, '2025-06-22 09:14:11', '2025-06-22 09:14:11'),
(260, 300, 461, 'RING', '0', 9991.00, 92.00, 9191.72, 20.600, 0.000, 20.600, 189349.43, 'None', 0.000, 0.00, 'fixed', 0.00, 0.00, 35.00, 0.00, 35.00, 189384.43, '2025-06-22 09:39:02', '2025-06-22 09:39:02'),
(261, 301, 462, 'RIN', '0', 9991.00, 92.00, 9191.72, 20.000, 0.000, 20.000, 183834.40, 'None', 0.000, 0.00, 'fixed', 15.00, 15.00, 35.00, 0.00, 50.00, 183884.40, '2025-06-22 09:41:23', '2025-06-22 09:41:23'),
(262, 302, 463, 'RING', '0', 9991.00, 92.00, 9191.72, 10.000, 0.000, 10.000, 91917.20, 'None', 0.000, 0.00, 'fixed', 15.00, 15.00, 35.00, 0.00, 50.00, 91967.20, '2025-06-22 09:44:09', '2025-06-22 09:44:09'),
(263, 303, 464, 'RIN', '0', 9991.00, 92.00, 9191.72, 20.000, 0.000, 20.000, 183834.40, 'None', 0.000, 0.00, 'fixed', 15.00, 15.00, 35.00, 0.00, 50.00, 183884.40, '2025-06-22 20:23:47', '2025-06-22 20:23:47'),
(264, 304, 465, 'RING', '0', 9991.00, 92.00, 9191.72, 10.500, 0.000, 10.500, 96513.06, 'None', 0.000, 0.00, 'fixed', 15.00, 15.00, 35.00, 0.00, 50.00, 96563.06, '2025-06-22 20:43:13', '2025-06-22 20:43:13');

-- --------------------------------------------------------

--
-- Table structure for table `jewellery_stock_log`
--

CREATE TABLE `jewellery_stock_log` (
  `log_id` bigint(20) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `material_type` varchar(50) NOT NULL,
  `stock_name` varchar(100) NOT NULL,
  `purity` decimal(5,2) NOT NULL,
  `transaction_type` enum('IN','OUT','ADJUST') NOT NULL,
  `quantity_before` decimal(10,3) NOT NULL,
  `quantity_change` decimal(10,3) NOT NULL,
  `quantity_after` decimal(10,3) NOT NULL,
  `reference_type` varchar(50) NOT NULL,
  `reference_id` varchar(50) NOT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jewellery_stock_log`
--

INSERT INTO `jewellery_stock_log` (`log_id`, `firm_id`, `inventory_id`, `material_type`, `stock_name`, `purity`, `transaction_type`, `quantity_before`, `quantity_change`, `quantity_after`, `reference_type`, `reference_id`, `transaction_date`, `user_id`, `notes`, `created_at`) VALUES
(41, 1, 38, 'Gold', '22K Hallmakrd Jewelry', 92.00, 'IN', 0.000, 97.600, 97.600, 'purchase', 'MHC01', '2025-06-01 13:44:08', 1, 'Initial stock entry via purchase', '2025-06-01 08:14:08'),
(42, 1, 39, 'Gold', '22K Hallmakrd Jewelry', 92.00, 'IN', 0.000, 60.690, 60.690, 'purchase', 'dfs', '2025-06-01 17:02:41', 1, 'Initial stock entry via purchase', '2025-06-01 11:32:41'),
(46, 1, 43, 'Gold', '14k', 59.00, 'IN', 0.000, 23.600, 23.600, 'purchase', '898', '2025-06-01 17:05:06', 1, 'Initial stock entry via purchase', '2025-06-01 11:35:06'),
(49, 1, 46, 'Silver', '75K JEWELELRY', 76.00, 'IN', 0.000, 109.600, 109.600, 'purchase', '632', '2025-06-01 17:11:15', 1, 'Initial stock entry via purchase', '2025-06-01 11:41:15'),
(50, 1, 47, 'Gold', '75K JWELERY', 76.00, 'IN', 0.000, 500.600, 500.600, 'purchase', 'MHC10', '2025-06-01 17:12:04', 1, 'Initial stock entry via purchase', '2025-06-01 11:42:04'),
(51, 1, 48, 'Gold', '833 jewellers', 84.00, 'IN', 0.000, 50.000, 50.000, 'purchase', 'd', '2025-06-01 17:17:36', 1, 'Initial stock entry via purchase', '2025-06-01 11:47:36'),
(52, 1, 49, 'Gold', '22K Hallmakrd Jewelry', 92.00, 'IN', 0.000, 10.000, 10.000, 'purchase', '12', '2025-06-01 17:26:58', NULL, 'Initial stock entry via purchase', '2025-06-01 11:56:58'),
(53, 1, 50, 'Gold', '22K Hallmakrd Jewelry', 92.00, 'IN', 0.000, 10.000, 10.000, 'purchase', '12', '2025-06-01 17:27:10', NULL, 'Initial stock entry via purchase', '2025-06-01 11:57:10'),
(54, 1, 51, 'Gold', '22K Hallmakrd Jewelry', 92.00, 'IN', 0.000, 10.000, 10.000, 'purchase', '12', '2025-06-01 17:27:18', NULL, 'Initial stock entry via purchase', '2025-06-01 11:57:18'),
(56, 1, 53, 'Gold', '22K Hallmakrd Jewelry', 92.00, 'IN', 0.000, 10.000, 10.000, 'purchase', '65', '2025-06-01 17:28:19', 1, 'Initial stock entry via purchase', '2025-06-01 11:58:19'),
(57, 1, 54, 'Gold', '75K JWELERY', 76.00, 'IN', 0.000, 10.000, 10.000, 'purchase', 'df', '2025-06-01 17:29:23', 1, 'Initial stock entry via purchase', '2025-06-01 11:59:23'),
(58, 1, 55, 'Gold', '14k', 59.00, 'IN', 0.000, 9.990, 9.990, 'purchase', '6', '2025-06-01 17:31:05', 1, 'Initial stock entry via purchase', '2025-06-01 12:01:05'),
(59, 1, 56, 'Gold', '833 jewellers', 84.00, 'IN', 0.000, 5.000, 5.000, 'purchase', 'mhc23', '2025-06-01 17:40:18', 1, 'Initial stock entry via purchase', '2025-06-01 12:10:18'),
(60, 1, 57, 'Gold', '22K Hallmakrd Jewelry', 92.00, 'IN', 0.000, 10.000, 10.000, 'purchase', 'mh10', '2025-06-01 18:14:17', 1, 'Initial stock entry via purchase', '2025-06-01 12:44:17'),
(61, 1, 58, 'Gold', '22K Hallmakrd Jewelry', 92.00, 'IN', 0.000, 50.000, 50.000, 'purchase', '5656', '2025-06-01 19:11:55', 1, 'Initial stock entry via purchase', '2025-06-01 13:41:55'),
(62, 1, 59, 'Gold', '22K Jewellery', 92.00, 'IN', 0.000, 36.600, 36.600, 'purchase', '123', '2025-06-01 19:34:00', 1, 'Initial stock entry via purchase', '2025-06-01 14:04:00'),
(63, 1, 60, 'Gold', '22k', 92.00, 'IN', 0.000, 30.000, 30.000, 'purchase', '2323', '2025-06-01 19:35:49', 1, 'Initial stock entry via purchase', '2025-06-01 14:05:49'),
(64, 44, 61, 'Gold', 'GOLD 22', 92.00, 'IN', 0.000, 20.660, 20.660, 'purchase', '45', '2025-06-22 13:41:17', 26, 'Initial stock entry via purchase', '2025-06-22 08:11:17'),
(65, 44, 38, 'Gold', '22K Hallmakrd Jewelry', 92.00, 'ADJUST', 97.600, 20.600, 77.000, 'Jewelry Item', '458', '2025-06-22 13:41:43', 26, 'Stock adjusted for Jewelry Item', '2025-06-22 08:11:43'),
(66, 44, 38, 'Gold', '22K Hallmakrd Jewelry', 92.00, 'ADJUST', 77.000, 20.600, 56.400, 'Jewelry Item', '460', '2025-06-22 13:42:11', 26, 'Stock adjusted for Jewelry Item', '2025-06-22 08:12:11'),
(67, 44, 38, 'Gold', '22K Hallmakrd Jewelry', 92.00, 'ADJUST', 56.400, 20.600, 35.800, 'Jewelry Item', '461', '2025-06-22 13:42:28', 26, 'Stock adjusted for Jewelry Item', '2025-06-22 08:12:28'),
(68, 44, 38, 'Gold', '22K Hallmakrd Jewelry', 92.00, 'ADJUST', 35.800, 20.000, 15.800, 'Jewelry Item', '462', '2025-06-22 13:54:29', 26, 'Stock adjusted for Jewelry Item', '2025-06-22 08:24:29'),
(69, 44, 38, 'Gold', '22K Hallmakrd Jewelry', 92.00, 'ADJUST', 15.800, 10.000, 5.800, 'Jewelry Item', '463', '2025-06-22 13:59:20', 26, 'Stock adjusted for Jewelry Item', '2025-06-22 08:29:20'),
(70, 44, 39, 'Gold', '22K Hallmakrd Jewelry', 92.00, 'ADJUST', 60.690, 20.000, 40.690, 'Jewelry Item', '464', '2025-06-22 14:02:32', 26, 'Stock adjusted for Jewelry Item', '2025-06-22 08:32:32'),
(71, 44, 61, 'Gold', 'GOLD 22', 92.00, 'ADJUST', 20.660, 10.500, 10.160, 'Jewelry Item', '465', '2025-06-22 14:04:17', 26, 'Stock adjusted for Jewelry Item (ID: 465)', '2025-06-22 08:34:17'),
(72, 44, 62, 'Gold', '14k', 59.00, 'IN', 0.000, 20.000, 20.000, 'purchase', '12', '2025-06-22 15:08:03', 26, 'Initial stock entry via purchase', '2025-06-22 09:38:03'),
(73, 44, 63, 'Gold', '18k', 76.00, 'IN', 0.000, 70.600, 70.600, 'purchase', '780', '2025-06-22 15:30:59', 26, 'Initial stock entry via purchase', '2025-06-22 10:00:59'),
(74, 44, 64, 'Gold', '20k', 84.00, 'IN', 0.000, 78.600, 78.600, 'purchase', '78', '2025-06-22 15:32:24', 26, 'Initial stock entry via purchase', '2025-06-22 10:02:24'),
(75, 44, 65, 'Gold', 'gold bar', 99.99, 'IN', 0.000, 20.000, 20.000, 'purchase', '741', '2025-06-22 15:37:37', 26, 'Initial stock entry via purchase', '2025-06-22 10:07:37'),
(76, 44, 66, 'Gold', 'GOLD 22', 92.00, 'IN', 0.000, 50.640, 50.640, 'purchase', '7878', '2025-06-22 16:54:28', 26, 'Initial stock entry via purchase', '2025-06-22 11:24:28'),
(77, 44, 67, 'Gold', 'GOLD 22', 92.00, 'IN', 0.000, 20.000, 20.000, 'direct', 'DIR-20250622143250', '2025-06-22 18:02:50', 26, 'Initial stock entry via direct', '2025-06-22 12:32:50'),
(78, 44, 68, 'Gold', 'GOLD 22', 92.00, 'IN', 0.000, 50.000, 50.000, 'purchase', '4', '2025-06-22 18:03:15', 26, 'Initial stock entry via purchase', '2025-06-22 12:33:15'),
(79, 44, 69, 'Gold', 'GOLD 22', 99.99, 'IN', 0.000, 10.000, 10.000, 'purchase', '32', '2025-06-23 02:15:20', 26, 'Initial stock entry via purchase', '2025-06-22 20:45:20'),
(80, 44, 68, 'Gold', 'GOLD 22', 92.00, 'ADJUST', 50.000, 10.000, 40.000, 'Jewelry Item', '466', '2025-06-23 03:15:00', 26, 'Stock adjusted for Jewelry Item (ID: 466)', '2025-06-22 21:45:00');

-- --------------------------------------------------------

--
-- Table structure for table `karigars`
--

CREATE TABLE `karigars` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `alternate_phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'India',
  `default_making_charge` decimal(12,2) DEFAULT 0.00,
  `charge_type` enum('PerGram','PerPiece','Fixed') DEFAULT 'PerGram',
  `gst_number` varchar(20) DEFAULT NULL,
  `pan_number` varchar(20) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `karigars`
--

INSERT INTO `karigars` (`id`, `firm_id`, `name`, `phone_number`, `alternate_phone`, `email`, `address_line1`, `address_line2`, `city`, `state`, `postal_code`, `country`, `default_making_charge`, `charge_type`, `gst_number`, `pan_number`, `status`, `created_at`, `updated_at`) VALUES
(0, 1, 'fdsf', '456456', '5454', 'dfsf@gmail.com', 'sadad', 'asdad', 'asd', 'asd', '454', 'India', 0.00, 'PerGram', NULL, NULL, 'Active', '2025-05-20 21:19:27', '2025-05-20 21:19:38'),
(1, 1, 'Rajesh Sornakar', '9876543210', '9123456789', 'rajesh.goldsmith@example.com', '25, M G Road', NULL, 'Surat', 'Gujarat', '395003', 'India', 150.00, 'PerGram', '24ABCDE1234F1Z2', 'ABCDE1234F', 'Active', '2025-04-23 16:40:30', '2025-04-23 16:40:30'),
(2, 1, 'Meena Patel', '9822001122', NULL, 'meenapatel@gmail.com', 'Flat 7, Diamond Residency', NULL, 'Mumbai', 'Maharashtra', '400001', 'India', 500.00, 'Fixed', NULL, 'AFHPP5566R', 'Active', '2025-04-23 16:40:30', '2025-04-23 16:40:30'),
(3, 1, 'Vijay Casting Works', '9011223344', '9011223355', 'vijaycasting@shopmail.com', 'Plot 12, GIDC Industrial Estate', NULL, 'Ahmedabad', 'Gujarat', '382445', 'India', 80.00, 'PerPiece', '27AAACV7890L1Z9', 'AAACV7890L', 'Active', '2025-04-23 16:40:30', '2025-04-23 16:40:30'),
(5, 1, 'prosen', '9891582296', '', '', '', '', '', '', '', 'India', 0.00, '', '', '', 'Active', '2025-05-23 13:02:48', '2025-05-23 13:02:48'),
(6, 41, 'sss', '989158296', '9810359', '', '', '', '', '', '', 'India', 0.00, '', '', '', 'Active', '2025-06-14 11:54:26', '2025-06-14 11:54:26'),
(7, 41, 'JAGANNATH PATRA', '9810359342', '', '', '', '', '', '', '', 'India', 0.00, '', '', '', 'Active', '2025-06-14 14:26:35', '2025-06-14 14:26:35');

-- --------------------------------------------------------

--
-- Table structure for table `karigar_ledger`
--

CREATE TABLE `karigar_ledger` (
  `id` int(11) NOT NULL,
  `karigar_id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `metal_type` varchar(50) NOT NULL,
  `purity` decimal(5,2) NOT NULL,
  `weight` decimal(10,3) NOT NULL,
  `transaction_type` enum('issue','return') NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `loan_date` date NOT NULL,
  `principal_amount` decimal(15,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL COMMENT 'Annual interest rate in %',
  `loan_term_months` int(11) NOT NULL,
  `maturity_date` date NOT NULL,
  `current_status` enum('active','closed','defaulted') DEFAULT 'active',
  `total_amount_paid` decimal(15,2) DEFAULT 0.00,
  `outstanding_amount` decimal(15,2) DEFAULT 0.00,
  `collateral_description` text DEFAULT NULL,
  `collateral_value` decimal(15,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `emi_amount` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loans`
--

INSERT INTO `loans` (`id`, `firm_id`, `customer_id`, `loan_date`, `principal_amount`, `interest_rate`, `loan_term_months`, `maturity_date`, `current_status`, `total_amount_paid`, `outstanding_amount`, `collateral_description`, `collateral_value`, `notes`, `created_by`, `created_at`, `updated_at`, `emi_amount`) VALUES
(1, 1, 1, '2025-05-29', 5000.00, 3.00, 12, '2025-11-30', 'active', 0.00, 5000.00, 'ageist chain', 56566.00, 'dfd', 1, '2025-05-29 10:48:39', '2025-05-29 10:48:39', 0),
(4, 44, 52, '2025-06-30', 30000.00, 3.00, 6, '2025-12-30', 'active', 0.00, 30000.00, 'Gold (5.00 g, 75.90% purity) - Rs.38450.00\\n', 38450.00, 'Loan created with 1 collateral items', 26, '2025-06-22 18:44:50', '2025-06-22 18:44:50', 5043.84);

-- --------------------------------------------------------

--
-- Table structure for table `loan_collateral_items`
--

CREATE TABLE `loan_collateral_items` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `material_type` varchar(100) NOT NULL,
  `purity` decimal(8,2) NOT NULL,
  `weight` decimal(12,3) NOT NULL,
  `rate_per_gram` decimal(12,2) NOT NULL,
  `calculated_value` decimal(12,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `loan_collateral_items`
--

INSERT INTO `loan_collateral_items` (`id`, `loan_id`, `material_type`, `purity`, `weight`, `rate_per_gram`, `calculated_value`, `description`, `image_path`, `created_at`) VALUES
(1, 4, 'Gold', 75.90, 5.000, 7690.00, 38450.00, '0', '../uploads/loans/loan_4_1750617890_captured.jpg', '2025-06-23 00:14:50');

-- --------------------------------------------------------

--
-- Table structure for table `loan_emi`
--

CREATE TABLE `loan_emi` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `emi_number` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `principal_component` decimal(12,2) NOT NULL,
  `interest_component` decimal(12,2) NOT NULL,
  `remaining_principal` decimal(12,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'PENDING',
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `loan_emi`
--

INSERT INTO `loan_emi` (`id`, `loan_id`, `emi_number`, `due_date`, `amount`, `principal_component`, `interest_component`, `remaining_principal`, `status`, `created_at`) VALUES
(7, 4, 1, '2025-06-30', 5043.84, 4968.84, 75.00, 25031.16, 'PENDING', '2025-06-23 00:14:50'),
(8, 4, 2, '2025-07-30', 5043.84, 4981.26, 62.58, 20049.90, 'PENDING', '2025-06-23 00:14:50'),
(9, 4, 3, '2025-08-30', 5043.84, 4993.72, 50.12, 15056.18, 'PENDING', '2025-06-23 00:14:50'),
(10, 4, 4, '2025-09-30', 5043.84, 5006.20, 37.64, 10049.98, 'PENDING', '2025-06-23 00:14:50'),
(11, 4, 5, '2025-10-30', 5043.84, 5018.72, 25.12, 5031.27, 'PENDING', '2025-06-23 00:14:50'),
(12, 4, 6, '2025-11-30', 5043.84, 5031.26, 12.58, 0.01, 'PENDING', '2025-06-23 00:14:50');

-- --------------------------------------------------------

--
-- Table structure for table `loan_emis`
--

CREATE TABLE `loan_emis` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('due','paid','late','waived') DEFAULT 'due',
  `paid_date` date DEFAULT NULL,
  `payment_mode` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `metal_purchases`
--

CREATE TABLE `metal_purchases` (
  `purchase_id` int(11) NOT NULL,
  `source_type` enum('Supplier','Customer') NOT NULL,
  `source_id` int(11) NOT NULL,
  `purchase_date` timestamp NULL DEFAULT current_timestamp(),
  `material_type` varchar(50) NOT NULL,
  `stock_name` varchar(100) NOT NULL,
  `purity` decimal(50,2) NOT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `rate_per_gram` decimal(12,2) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `payment_status` enum('Unpaid','Paid','Partial') DEFAULT 'Unpaid',
  `inventory_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `firm_id` int(11) NOT NULL,
  `weight` decimal(10,3) NOT NULL,
  `paid_amount` int(11) NOT NULL,
  `payment_mode` varchar(50) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `entry_type` varchar(50) NOT NULL DEFAULT 'purchase'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `metal_purchases`
--

INSERT INTO `metal_purchases` (`purchase_id`, `source_type`, `source_id`, `purchase_date`, `material_type`, `stock_name`, `purity`, `quantity`, `rate_per_gram`, `total_amount`, `transaction_reference`, `payment_status`, `inventory_id`, `created_at`, `updated_at`, `firm_id`, `weight`, `paid_amount`, `payment_mode`, `invoice_number`, `entry_type`) VALUES
(96, 'Supplier', 9, '2025-06-01 08:14:08', 'Gold', '22K Hallmakrd Jewelry', 92.00, 1.000, 9074.87, 885707.31, NULL, 'Unpaid', 38, '2025-06-01 08:14:08', '2025-06-01 08:14:08', 1, 97.600, 0, '', 'MHC01', 'purchase'),
(97, 'Supplier', 1, '2025-06-01 11:32:41', 'Gold', '22K Hallmakrd Jewelry', 92.00, 1.000, 9074.87, 550753.86, NULL, 'Partial', 39, '2025-06-01 11:32:41', '2025-06-01 11:32:41', 1, 60.690, 10, 'Bank', 'dfs', 'purchase'),
(98, 'Supplier', 2, '2025-06-01 11:35:06', 'Gold', '14k', 59.00, 1.000, 9863.00, 232766.80, NULL, 'Partial', 43, '2025-06-01 11:35:06', '2025-06-01 11:35:06', 1, 23.600, 10, 'Cash', '898', 'purchase'),
(99, 'Supplier', 2, '2025-06-01 11:41:15', 'Silver', '75K JEWELELRY', 76.00, 1.000, 9863.00, 1080984.80, NULL, '', 46, '2025-06-01 11:41:15', '2025-06-01 11:41:15', 1, 109.600, 0, '', '632', 'purchase'),
(100, 'Supplier', 8, '2025-06-01 11:42:04', 'Gold', '75K JWELERY', 76.00, 1.000, 9074.87, 4542879.92, NULL, '', 47, '2025-06-01 11:42:04', '2025-06-01 11:42:04', 1, 500.600, 0, '', 'MHC10', 'purchase'),
(101, 'Supplier', 2, '2025-06-01 11:47:36', 'Gold', '833 jewellers', 84.00, 1.000, 9810.00, 490500.00, NULL, 'Unpaid', 48, '2025-06-01 11:47:36', '2025-06-01 11:47:36', 1, 50.000, 0, '', 'd', 'purchase'),
(105, 'Supplier', 2, '2025-06-01 11:58:19', 'Gold', '22K Hallmakrd Jewelry', 92.00, 1.000, 3629.95, 36299.50, NULL, 'Unpaid', 53, '2025-06-01 11:58:19', '2025-06-01 11:58:19', 1, 10.000, 0, '', '65', 'purchase'),
(106, 'Supplier', 2, '2025-06-01 11:59:23', 'Gold', '75K JWELERY', 76.00, 1.000, 9074.87, 90748.70, NULL, 'Partial', 54, '2025-06-01 11:59:23', '2025-06-01 11:59:23', 1, 10.000, 68976, 'Cash', 'df', 'purchase'),
(107, 'Supplier', 2, '2025-06-01 12:01:05', 'Gold', '14k', 59.00, 1.000, 9863.00, 98531.37, NULL, 'Partial', 55, '2025-06-01 12:01:05', '2025-06-01 12:01:05', 1, 9.990, 58139, 'Cash', '6', 'purchase'),
(108, 'Supplier', 2, '2025-06-01 12:10:18', 'Gold', '833 jewellers', 84.00, 1.000, 5890.00, 29450.00, NULL, 'Unpaid', 56, '2025-06-01 12:10:18', '2025-06-01 12:10:18', 1, 5.000, 0, '', 'mhc23', 'purchase'),
(109, 'Supplier', 2, '2025-06-01 12:44:17', 'Gold', '22K Hallmakrd Jewelry', 92.00, 1.000, 9593.00, 95930.00, NULL, 'Paid', 57, '2025-06-01 12:44:17', '2025-06-01 12:44:17', 1, 10.000, 95930, 'Cash', 'mh10', 'purchase'),
(110, 'Supplier', 2, '2025-06-01 13:41:55', 'Gold', '22K Hallmakrd Jewelry', 92.00, 1.000, 7216.44, 360822.00, NULL, 'Unpaid', 58, '2025-06-01 13:41:55', '2025-06-01 13:41:55', 1, 50.000, 0, '', '5656', 'purchase'),
(111, 'Supplier', 14, '2025-06-01 14:04:00', 'Gold', '22K Jewellery', 92.00, 1.000, 9863.00, 360985.80, NULL, 'Unpaid', 59, '2025-06-01 14:04:00', '2025-06-01 14:04:00', 1, 36.600, 0, '', '123', 'purchase'),
(112, 'Supplier', 1, '2025-06-01 14:05:49', 'Gold', '22k', 92.00, 1.000, 800.00, 24000.00, NULL, 'Unpaid', 60, '2025-06-01 14:05:49', '2025-06-02 06:35:29', 41, 30.000, 0, '', '2323', 'purchase'),
(113, 'Supplier', 16, '2025-06-22 08:11:17', 'Gold', 'GOLD 22', 92.00, 1.000, 8970.90, 185338.79, NULL, 'Partial', 61, '2025-06-22 08:11:17', '2025-06-22 08:11:17', 44, 20.660, 50000, 'Cash', '45', 'purchase'),
(114, 'Supplier', 17, '2025-06-22 09:38:03', 'Gold', '14k', 59.00, 1.000, 5293.37, 105867.30, NULL, 'Partial', 62, '2025-06-22 09:38:03', '2025-06-22 09:38:03', 44, 20.000, 50000, 'Bank', '12', 'purchase'),
(115, 'Supplier', 17, '2025-06-22 10:00:59', 'Gold', '18k', 76.00, 1.000, 7593.92, 536130.75, NULL, 'Partial', 63, '2025-06-22 10:00:59', '2025-06-22 10:00:59', 44, 70.600, 8000, 'Cash', '780', 'purchase'),
(116, 'Supplier', 17, '2025-06-22 10:02:24', 'Gold', '20k', 84.00, 1.000, 8393.28, 659711.81, NULL, 'Partial', 64, '2025-06-22 10:02:24', '2025-06-22 10:02:24', 44, 78.600, 5000, 'Cash', '78', 'purchase'),
(117, 'Supplier', 17, '2025-06-22 10:07:37', 'Gold', 'gold bar', 99.99, 1.000, 9991.00, 199820.00, NULL, 'Partial', 65, '2025-06-22 10:07:37', '2025-06-22 10:07:37', 44, 20.000, 50000, 'Bank', '741', 'purchase'),
(118, 'Supplier', 17, '2025-06-22 11:24:28', 'Gold', 'GOLD 22', 92.00, 1.000, 9192.64, 465515.29, NULL, 'Partial', 66, '2025-06-22 11:24:28', '2025-06-22 11:24:28', 44, 50.640, 400000, 'Cash', '7878', 'purchase'),
(119, 'Supplier', 1, '2025-06-22 12:32:50', 'Gold', 'GOLD 22', 92.00, 1.000, 9081.77, 181635.40, NULL, 'Paid', 67, '2025-06-22 12:32:50', '2025-06-22 12:32:50', 44, 20.000, 181635, 'Direct', 'DIR-20250622143250', 'direct'),
(120, 'Supplier', 17, '2025-06-22 12:33:15', 'Gold', 'GOLD 22', 92.00, 1.000, 9192.64, 459632.00, NULL, 'Partial', 68, '2025-06-22 12:33:15', '2025-06-22 12:33:15', 44, 50.000, 50000, 'Bank', '4', 'purchase'),
(121, 'Supplier', 18, '2025-06-22 20:45:20', 'Gold', 'GOLD 22', 99.99, 1.000, 9991.00, 99910.00, NULL, 'Partial', 69, '2025-06-22 20:45:20', '2025-06-22 20:45:20', 44, 10.000, 50000, 'Bank', '32', 'purchase');

-- --------------------------------------------------------

--
-- Table structure for table `order_images`
--

CREATE TABLE `order_images` (
  `id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `order_images`
--

INSERT INTO `order_images` (`id`, `order_item_id`, `image_path`, `is_primary`, `created_at`) VALUES
(1, 8, 'uploads/orders/8_8_1749910236_0.jpg', 1, '2025-06-14 14:10:36'),
(2, 8, 'uploads/orders/8_8_1749910236_1.jpg', 0, '2025-06-14 14:10:36'),
(3, 8, 'uploads/orders/8_8_1749910236_2.jpg', 0, '2025-06-14 14:10:36');

-- --------------------------------------------------------

--
-- Table structure for table `schemes`
--

CREATE TABLE `schemes` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `scheme_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `scheme_type` enum('lucky_draw','contest','promotion') DEFAULT 'lucky_draw',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `draw_date` date DEFAULT NULL,
  `status` enum('draft','active','completed','cancelled') DEFAULT 'draft',
  `max_entries` int(11) DEFAULT 0 COMMENT '0 means unlimited',
  `min_purchase_amount` decimal(10,2) DEFAULT 0.00,
  `auto_entry_on_registration` tinyint(1) DEFAULT 0,
  `auto_entry_on_purchase` tinyint(1) DEFAULT 0,
  `entry_fee` decimal(10,2) DEFAULT 0.00,
  `terms_conditions` text DEFAULT NULL,
  `banner_image` varchar(500) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schemes`
--

INSERT INTO `schemes` (`id`, `firm_id`, `scheme_name`, `description`, `scheme_type`, `start_date`, `end_date`, `draw_date`, `status`, `max_entries`, `min_purchase_amount`, `auto_entry_on_registration`, `auto_entry_on_purchase`, `entry_fee`, `terms_conditions`, `banner_image`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 41, 'Dhanteras Dhamaka', 'Win gold coins and cashback on festive purchases!', 'lucky_draw', '2025-10-01', '2025-10-31', '2025-11-05', 'draft', 250, 10000.00, 0, 1, 0.00, 'Winners will be announced on draw date. No exchange on prizes.', 'dhanteras_banner.jpg', 1, '2025-06-03 09:09:46', '2025-06-03 09:32:10'),
(2, 41, 'Shopping Queen Draw', 'All women shoppers above ₹10,000 will be automatically enrolled to win a diamond ring or branded cosmetics.', 'lucky_draw', '2025-06-01', '2025-06-30', '2025-07-01', 'active', 150, 10000.00, 0, 1, 0.00, 'Offer valid only for female customers. Winner must present bill.', 'womens_day_offer.jpg', 1, '2025-06-03 09:11:02', '2025-06-03 09:12:19'),
(3, 41, 'Rakhi Special Lucky Draw', 'Make this Rakhi special with our exclusive lucky draw! Buy any Rakhi gift set to participate.', 'lucky_draw', '2025-07-01', '2025-07-31', NULL, 'draft', 0, 5000.00, 0, 1, 0.00, '1. Purchase any Rakhi gift set\\r\\n2. One entry per customer\\r\\n3. Winner will be selected randomly\\r\\n4. Valid only during Rakhi period', NULL, 0, '2025-06-03 10:12:37', '2025-06-03 10:12:37'),
(4, 44, 'Dhanteras Lucky Draw', 'Celebrate Dhanteras with our special lucky draw! Purchase jewelry worth â‚¹10,000 or more to get an entry.', 'lucky_draw', '2025-06-22', '2025-07-22', NULL, 'active', 0, 10000.00, 0, 1, 0.00, '1. Minimum purchase of â‚¹10,000 required\\r\\n2. One entry per customer\\r\\n3. Winner will be selected randomly\\r\\n4. Valid only during Dhanteras period', NULL, 0, '2025-06-22 09:43:43', '2025-06-22 09:43:43');

-- --------------------------------------------------------

--
-- Table structure for table `scheme_entries`
--

CREATE TABLE `scheme_entries` (
  `id` int(11) NOT NULL,
  `scheme_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `entry_date` datetime NOT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `purchase_amount` decimal(10,2) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `scheme_entries`
--

INSERT INTO `scheme_entries` (`id`, `scheme_id`, `customer_id`, `entry_date`, `status`, `purchase_amount`, `sale_id`, `created_at`) VALUES
(1, 2, 37, '2025-06-14 06:54:38', 'active', 103034.89, 293, '2025-06-14 01:24:38'),
(2, 2, 47, '2025-06-14 07:13:34', 'active', 74696.80, 294, '2025-06-14 01:43:34'),
(3, 2, 48, '2025-06-14 13:23:27', 'active', 93483.80, 295, '2025-06-14 07:53:27'),
(4, 4, 51, '2025-06-22 15:14:09', 'active', 91967.20, 302, '2025-06-22 09:44:09'),
(5, 4, 52, '2025-06-23 01:53:47', 'active', 183884.40, 303, '2025-06-22 20:23:47');

-- --------------------------------------------------------

--
-- Table structure for table `scheme_rewards`
--

CREATE TABLE `scheme_rewards` (
  `id` int(11) NOT NULL,
  `scheme_id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `rank` int(11) NOT NULL,
  `prize_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `scheme_rewards`
--

INSERT INTO `scheme_rewards` (`id`, `scheme_id`, `firm_id`, `rank`, `prize_name`, `quantity`, `description`, `created_at`) VALUES
(1, 3, 41, 1, '5 gm Gold Coin 22K', 1, '', '2025-06-03 10:12:37'),
(2, 4, 44, 1, 'coin 5gm', 1, '', '2025-06-22 09:43:43');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_in_days` int(11) NOT NULL,
  `features` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `name`, `price`, `duration_in_days`, `features`, `is_active`) VALUES
(1, 'Trial', 0.00, 7, 'Inventory, Sales, Customers, Limited Catalog, Limited Billing', 1),
(2, 'Basic', 299.00, 30, 'Inventory, Sales, Customers, Catalog, Billing, Repairs, Staff, Suppliers', 1),
(3, 'Standard', 6900.00, 365, 'Inventory, Sales, Customers, Catalog, Billing, Repairs, Analytics, Staff, Suppliers, Testing, Security, Loans', 1),
(4, 'Premium', 14990.00, 1095, 'Inventory, Sales, Customers, Catalog, Billing, Repairs, Analytics, Staff, Suppliers, Testing, Security, Loans, Bookings, Alerts, Settings', 1);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `state` varchar(50) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `gst` varchar(100) DEFAULT NULL,
  `payment_terms` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `firm_id`, `name`, `contact_info`, `email`, `address`, `state`, `phone`, `gst`, `payment_terms`, `notes`, `date_added`, `last_updated`) VALUES
(1, 41, 'Gupta Jewellers', '9891582296', 'demo@gmail.com', 'new deljh', '', '9810359334', 'null', 'cash', '', '2025-05-31 10:18:52', '2025-05-31 10:18:52'),
(2, 41, 'DEMO SUPPILER', '9865986532', '', '', '', '', '', '', '', '2025-05-31 12:35:25', '2025-05-31 12:35:25'),
(3, 41, 'fdfg', '56565', '', '', '', '56565656565', '', '', '', '2025-05-31 12:45:11', '2025-05-31 12:45:11'),
(5, 41, 'suppiler1', '', '', '', '', '', '', '', '', '2025-06-01 07:23:37', '2025-06-01 07:23:37'),
(6, 41, 'SUPPLIER 2', '9810359334', 'DEMOGMAIL@GMAIL.COM', 'VANI VIHAR', '', '98132256323', '07ABFG56981Z1', 'CASH', 'DFSF', '2025-06-01 07:25:26', '2025-06-01 07:25:26'),
(7, 41, 'SUPPLIER 3', '', '', '', '', '', '', '', '', '2025-06-01 07:28:34', '2025-06-01 07:28:34'),
(8, 41, 'sunderam chain', '', '', '', '', '', '', '', '', '2025-06-01 07:31:50', '2025-06-01 07:31:50'),
(9, 41, 'Sunderam chain', '', '', '', '', '', '', '', '', '2025-06-01 08:13:56', '2025-06-01 08:13:56'),
(10, 41, 'supplier 16', '', '', '', '', '', '', '', '', '2025-06-01 08:48:15', '2025-06-01 08:48:15'),
(11, 41, 'sdaf', '', '', '', '', '', '', '', '', '2025-06-01 09:16:04', '2025-06-01 09:16:04'),
(12, 1, 'sf dfsdf', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-06-01 13:21:24', '2025-06-01 13:21:24'),
(13, 1, 'prosen', NULL, NULL, NULL, 'DELHI', '9810359441', NULL, NULL, NULL, '2025-06-01 13:23:14', '2025-06-01 13:23:14'),
(14, 1, 'gopal halder', NULL, NULL, 'lalgola', 'west bengle', '9865326598', '1zdsf5656', NULL, NULL, '2025-06-01 13:25:33', '2025-06-01 13:25:33'),
(15, 1, 'gourav', NULL, NULL, '', '', '', '', NULL, NULL, '2025-06-01 13:30:49', '2025-06-01 13:30:49'),
(16, 44, 'DEMO', NULL, NULL, '', '', '', '', NULL, NULL, '2025-06-22 08:11:01', '2025-06-22 08:11:01'),
(17, 44, 'Kenisa', NULL, NULL, '', '', '', '', NULL, NULL, '2025-06-22 09:37:22', '2025-06-22 09:37:22'),
(18, 44, 'ronmtik', NULL, NULL, '', '', '', '', NULL, NULL, '2025-06-22 20:45:08', '2025-06-22 20:45:08');

-- --------------------------------------------------------

--
-- Table structure for table `trays`
--

CREATE TABLE `trays` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) DEFAULT NULL,
  `tray_number` varchar(50) DEFAULT NULL,
  `tray_type` varchar(50) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_assigned_coupons`
--
ALTER TABLE `customer_assigned_coupons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_gold_plans`
--
ALTER TABLE `customer_gold_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `firm_id` (`firm_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `firm_id` (`firm_id`);

--
-- Indexes for table `firm`
--
ALTER TABLE `firm`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `firm_configurations`
--
ALTER TABLE `firm_configurations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `firm_subscriptions`
--
ALTER TABLE `firm_subscriptions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `firm_users`
--
ALTER TABLE `firm_users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gold_plan_installments`
--
ALTER TABLE `gold_plan_installments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gold_saving_plans`
--
ALTER TABLE `gold_saving_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_metals`
--
ALTER TABLE `inventory_metals`
  ADD PRIMARY KEY (`inventory_id`);

--
-- Indexes for table `jewelentry_category`
--
ALTER TABLE `jewelentry_category`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jewellery_customer_order`
--
ALTER TABLE `jewellery_customer_order`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jewellery_items`
--
ALTER TABLE `jewellery_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jewellery_order_items`
--
ALTER TABLE `jewellery_order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jewellery_payments`
--
ALTER TABLE `jewellery_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jewellery_price_config`
--
ALTER TABLE `jewellery_price_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jewellery_product_image`
--
ALTER TABLE `jewellery_product_image`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jewellery_sales`
--
ALTER TABLE `jewellery_sales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jewellery_sales_items`
--
ALTER TABLE `jewellery_sales_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jewellery_stock_log`
--
ALTER TABLE `jewellery_stock_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_inventory` (`inventory_id`),
  ADD KEY `idx_material` (`material_type`),
  ADD KEY `idx_transaction` (`transaction_date`);

--
-- Indexes for table `karigars`
--
ALTER TABLE `karigars`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_karigor_firm` (`firm_id`);

--
-- Indexes for table `karigar_ledger`
--
ALTER TABLE `karigar_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `karigar_id` (`karigar_id`),
  ADD KEY `order_item_id` (`order_item_id`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `firm_id` (`firm_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `loan_collateral_items`
--
ALTER TABLE `loan_collateral_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`);

--
-- Indexes for table `loan_emi`
--
ALTER TABLE `loan_emi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`);

--
-- Indexes for table `loan_emis`
--
ALTER TABLE `loan_emis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `metal_purchases`
--
ALTER TABLE `metal_purchases`
  ADD PRIMARY KEY (`purchase_id`),
  ADD KEY `idx_source` (`source_type`,`source_id`),
  ADD KEY `idx_material` (`material_type`),
  ADD KEY `idx_purchase_date` (`purchase_date`);

--
-- Indexes for table `order_images`
--
ALTER TABLE `order_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_item_id` (`order_item_id`);

--
-- Indexes for table `schemes`
--
ALTER TABLE `schemes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `scheme_entries`
--
ALTER TABLE `scheme_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_customer_scheme` (`customer_id`,`scheme_id`),
  ADD KEY `scheme_id` (`scheme_id`),
  ADD KEY `sale_id` (`sale_id`);

--
-- Indexes for table `scheme_rewards`
--
ALTER TABLE `scheme_rewards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `scheme_id` (`scheme_id`),
  ADD KEY `firm_id` (`firm_id`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `firm_id` (`firm_id`);

--
-- Indexes for table `trays`
--
ALTER TABLE `trays`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `customer_assigned_coupons`
--
ALTER TABLE `customer_assigned_coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customer_gold_plans`
--
ALTER TABLE `customer_gold_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `firm`
--
ALTER TABLE `firm`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `firm_configurations`
--
ALTER TABLE `firm_configurations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `firm_subscriptions`
--
ALTER TABLE `firm_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `firm_users`
--
ALTER TABLE `firm_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `gold_plan_installments`
--
ALTER TABLE `gold_plan_installments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `gold_saving_plans`
--
ALTER TABLE `gold_saving_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `inventory_metals`
--
ALTER TABLE `inventory_metals`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `jewelentry_category`
--
ALTER TABLE `jewelentry_category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=210;

--
-- AUTO_INCREMENT for table `jewellery_customer_order`
--
ALTER TABLE `jewellery_customer_order`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `jewellery_items`
--
ALTER TABLE `jewellery_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=467;

--
-- AUTO_INCREMENT for table `jewellery_order_items`
--
ALTER TABLE `jewellery_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `jewellery_payments`
--
ALTER TABLE `jewellery_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=242;

--
-- AUTO_INCREMENT for table `jewellery_price_config`
--
ALTER TABLE `jewellery_price_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `jewellery_product_image`
--
ALTER TABLE `jewellery_product_image`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `jewellery_sales`
--
ALTER TABLE `jewellery_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=305;

--
-- AUTO_INCREMENT for table `jewellery_sales_items`
--
ALTER TABLE `jewellery_sales_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=265;

--
-- AUTO_INCREMENT for table `jewellery_stock_log`
--
ALTER TABLE `jewellery_stock_log`
  MODIFY `log_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `karigars`
--
ALTER TABLE `karigars`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `karigar_ledger`
--
ALTER TABLE `karigar_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `loan_collateral_items`
--
ALTER TABLE `loan_collateral_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `loan_emi`
--
ALTER TABLE `loan_emi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `loan_emis`
--
ALTER TABLE `loan_emis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `metal_purchases`
--
ALTER TABLE `metal_purchases`
  MODIFY `purchase_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=122;

--
-- AUTO_INCREMENT for table `order_images`
--
ALTER TABLE `order_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `schemes`
--
ALTER TABLE `schemes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `scheme_entries`
--
ALTER TABLE `scheme_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `scheme_rewards`
--
ALTER TABLE `scheme_rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `trays`
--
ALTER TABLE `trays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`firm_id`) REFERENCES `firm` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `firm_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `karigar_ledger`
--
ALTER TABLE `karigar_ledger`
  ADD CONSTRAINT `karigar_ledger_ibfk_1` FOREIGN KEY (`karigar_id`) REFERENCES `karigars` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `karigar_ledger_ibfk_2` FOREIGN KEY (`order_item_id`) REFERENCES `jewellery_order_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_collateral_items`
--
ALTER TABLE `loan_collateral_items`
  ADD CONSTRAINT `loan_collateral_items_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`);

--
-- Constraints for table `loan_emi`
--
ALTER TABLE `loan_emi`
  ADD CONSTRAINT `loan_emi_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`);

--
-- Constraints for table `order_images`
--
ALTER TABLE `order_images`
  ADD CONSTRAINT `order_images_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `jewellery_order_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scheme_entries`
--
ALTER TABLE `scheme_entries`
  ADD CONSTRAINT `scheme_entries_ibfk_1` FOREIGN KEY (`scheme_id`) REFERENCES `schemes` (`id`),
  ADD CONSTRAINT `scheme_entries_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`),
  ADD CONSTRAINT `scheme_entries_ibfk_3` FOREIGN KEY (`sale_id`) REFERENCES `jewellery_sales` (`id`);

--
-- Constraints for table `scheme_rewards`
--
ALTER TABLE `scheme_rewards`
  ADD CONSTRAINT `scheme_rewards_ibfk_1` FOREIGN KEY (`scheme_id`) REFERENCES `schemes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scheme_rewards_ibfk_2` FOREIGN KEY (`firm_id`) REFERENCES `firm` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
