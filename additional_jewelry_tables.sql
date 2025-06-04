-- Additional Tables for Jewelry Management System

-- Karigars (Artisans) Management
CREATE TABLE `karigars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firm_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `daily_wage` decimal(10,2) DEFAULT 0.00,
  `advance_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `firm_phone` (`firm_id`, `phone`),
  KEY `firm_id` (`firm_id`),
  CONSTRAINT `fk_karigars_firm` FOREIGN KEY (`firm_id`) REFERENCES `firm` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `karigar_work_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firm_id` int(11) NOT NULL,
  `karigar_id` int(11) NOT NULL,
  `manufacturing_order_id` int(11) NOT NULL,
  `work_date` date NOT NULL,
  `work_hours` decimal(5,2) NOT NULL,
  `wage_amount` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `firm_id` (`firm_id`),
  KEY `karigar_id` (`karigar_id`),
  KEY `manufacturing_order_id` (`manufacturing_order_id`),
  CONSTRAINT `fk_work_log_firm` FOREIGN KEY (`firm_id`) REFERENCES `firm` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_work_log_karigar` FOREIGN KEY (`karigar_id`) REFERENCES `karigars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_work_log_manufacturing` FOREIGN KEY (`manufacturing_order_id`) REFERENCES `jewellery_manufacturing_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coupons Management
CREATE TABLE `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firm_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `type` enum('percentage','fixed') NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `min_purchase_amount` decimal(15,2) DEFAULT 0.00,
  `max_discount_amount` decimal(15,2) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `firm_code` (`firm_id`, `code`),
  KEY `firm_id` (`firm_id`),
  CONSTRAINT `fk_coupons_firm` FOREIGN KEY (`firm_id`) REFERENCES `firm` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_assigned_coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firm_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `coupon_id` int(11) NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `used_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_coupon` (`customer_id`, `coupon_id`),
  KEY `firm_id` (`firm_id`),
  KEY `coupon_id` (`coupon_id`),
  CONSTRAINT `fk_assigned_coupons_firm` FOREIGN KEY (`firm_id`) REFERENCES `firm` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assigned_coupons_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assigned_coupons_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Schemes Management
CREATE TABLE `schemes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firm_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('purchase','referral','loyalty') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `min_purchase_amount` decimal(15,2) DEFAULT 0.00,
  `reward_type` enum('discount','cashback','points') NOT NULL,
  `reward_value` decimal(10,2) NOT NULL,
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `firm_id` (`firm_id`),
  CONSTRAINT `fk_schemes_firm` FOREIGN KEY (`firm_id`) REFERENCES `firm` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `scheme_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firm_id` int(11) NOT NULL,
  `scheme_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `purchase_amount` decimal(15,2) NOT NULL,
  `reward_amount` decimal(15,2) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `firm_id` (`firm_id`),
  KEY `scheme_id` (`scheme_id`),
  KEY `customer_id` (`customer_id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `fk_scheme_entries_firm` FOREIGN KEY (`firm_id`) REFERENCES `firm` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scheme_entries_scheme` FOREIGN KEY (`scheme_id`) REFERENCES `schemes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scheme_entries_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scheme_entries_order` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `scheme_rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firm_id` int(11) NOT NULL,
  `scheme_entry_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `reward_amount` decimal(15,2) NOT NULL,
  `reward_type` enum('discount','cashback','points') NOT NULL,
  `status` enum('pending','issued','used','expired') NOT NULL DEFAULT 'pending',
  `valid_until` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `firm_id` (`firm_id`),
  KEY `scheme_entry_id` (`scheme_entry_id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `fk_scheme_rewards_firm` FOREIGN KEY (`firm_id`) REFERENCES `firm` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scheme_rewards_entry` FOREIGN KEY (`scheme_entry_id`) REFERENCES `scheme_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scheme_rewards_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for frequently queried columns
ALTER TABLE `karigar_work_log` ADD INDEX `idx_work_date` (`work_date`);
ALTER TABLE `coupons` ADD INDEX `idx_validity` (`start_date`, `end_date`);
ALTER TABLE `schemes` ADD INDEX `idx_validity` (`start_date`, `end_date`);
ALTER TABLE `scheme_rewards` ADD INDEX `idx_validity` (`valid_until`);

-- Add composite indexes for common query patterns
ALTER TABLE `karigar_work_log` ADD INDEX `idx_karigar_date` (`karigar_id`, `work_date`);
ALTER TABLE `customer_assigned_coupons` ADD INDEX `idx_customer_status` (`customer_id`, `is_used`);
ALTER TABLE `scheme_entries` ADD INDEX `idx_customer_scheme` (`customer_id`, `scheme_id`);
ALTER TABLE `scheme_rewards` ADD INDEX `idx_customer_status` (`customer_id`, `status`);

COMMIT; 