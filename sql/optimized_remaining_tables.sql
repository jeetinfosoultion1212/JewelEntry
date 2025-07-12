-- JewelEntryApp: Optimized Remaining Tables
-- This file contains optimized CREATE TABLE statements for all remaining tables with indexes and foreign keys

-- Karigar (Artisan) Management Tables
CREATE TABLE `karigars` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(15) NOT NULL,
  `address` TEXT,
  `specialization` VARCHAR(100) DEFAULT NULL,
  `experience_years` INT DEFAULT NULL,
  `daily_wage` DECIMAL(10,2) DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `bank_account` VARCHAR(50) DEFAULT NULL,
  `ifsc_code` VARCHAR(20) DEFAULT NULL,
  `status` ENUM('active','inactive','on_leave') DEFAULT 'active',
  `joining_date` DATE DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_specialization` (`specialization`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `karigar_ledger` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `karigar_id` INT NOT NULL,
  `firm_id` INT NOT NULL,
  `transaction_date` DATE NOT NULL,
  `transaction_type` ENUM('salary','advance','bonus','deduction','other') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `reference_id` INT DEFAULT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_karigar_id` (`karigar_id`),
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_transaction_date` (`transaction_date`),
  INDEX `idx_transaction_type` (`transaction_type`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`karigar_id`) REFERENCES `karigars`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `firm_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Loans & Financial Services Tables
CREATE TABLE `loans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `customer_id` INT NOT NULL,
  `loan_number` VARCHAR(50) NOT NULL,
  `loan_amount` DECIMAL(12,2) NOT NULL,
  `interest_rate` DECIMAL(5,2) NOT NULL,
  `loan_term_months` INT NOT NULL,
  `emi_amount` DECIMAL(10,2) NOT NULL,
  `total_interest` DECIMAL(12,2) NOT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL,
  `disbursed_amount` DECIMAL(12,2) DEFAULT 0.00,
  `paid_amount` DECIMAL(12,2) DEFAULT 0.00,
  `due_amount` DECIMAL(12,2) GENERATED ALWAYS AS (`total_amount` - `paid_amount`) STORED,
  `loan_status` ENUM('pending','approved','disbursed','active','closed','defaulted') DEFAULT 'pending',
  `disbursement_date` DATE DEFAULT NULL,
  `start_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `collateral_value` DECIMAL(12,2) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_loan_number` (`loan_number`),
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_customer_id` (`customer_id`),
  INDEX `idx_loan_status` (`loan_status`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customer`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `firm_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `loan_collateral_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `loan_id` INT NOT NULL,
  `item_id` INT NOT NULL,
  `item_type` VARCHAR(50) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `estimated_value` DECIMAL(12,2) NOT NULL,
  `actual_value` DECIMAL(12,2) DEFAULT NULL,
  `status` ENUM('pledged','returned','sold') DEFAULT 'pledged',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_loan_id` (`loan_id`),
  INDEX `idx_item_id` (`item_id`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`loan_id`) REFERENCES `loans`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `jewellery_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `loan_emi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `loan_id` INT NOT NULL,
  `emi_number` INT NOT NULL,
  `due_date` DATE NOT NULL,
  `emi_amount` DECIMAL(10,2) NOT NULL,
  `principal_amount` DECIMAL(10,2) NOT NULL,
  `interest_amount` DECIMAL(10,2) NOT NULL,
  `paid_amount` DECIMAL(10,2) DEFAULT 0.00,
  `due_amount` DECIMAL(10,2) GENERATED ALWAYS AS (`emi_amount` - `paid_amount`) STORED,
  `payment_date` DATE DEFAULT NULL,
  `status` ENUM('pending','partial','paid','overdue') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_loan_id` (`loan_id`),
  INDEX `idx_due_date` (`due_date`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`loan_id`) REFERENCES `loans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `loan_emis` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `loan_id` INT NOT NULL,
  `emi_number` INT NOT NULL,
  `due_date` DATE NOT NULL,
  `emi_amount` DECIMAL(10,2) NOT NULL,
  `principal_amount` DECIMAL(10,2) NOT NULL,
  `interest_amount` DECIMAL(10,2) NOT NULL,
  `paid_amount` DECIMAL(10,2) DEFAULT 0.00,
  `due_amount` DECIMAL(10,2) GENERATED ALWAYS AS (`emi_amount` - `paid_amount`) STORED,
  `payment_date` DATE DEFAULT NULL,
  `status` ENUM('pending','partial','paid','overdue') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_loan_id` (`loan_id`),
  INDEX `idx_due_date` (`due_date`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`loan_id`) REFERENCES `loans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Schemes & Gold Plans Tables
CREATE TABLE `schemes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `scheme_name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `duration_months` INT NOT NULL,
  `min_amount` DECIMAL(10,2) DEFAULT NULL,
  `max_amount` DECIMAL(10,2) DEFAULT NULL,
  `interest_rate` DECIMAL(5,2) DEFAULT NULL,
  `bonus_percentage` DECIMAL(5,2) DEFAULT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `start_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `terms_conditions` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `firm_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `scheme_entries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `scheme_id` INT NOT NULL,
  `customer_id` INT NOT NULL,
  `firm_id` INT NOT NULL,
  `enrollment_date` DATE NOT NULL,
  `maturity_date` DATE NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `interest_earned` DECIMAL(10,2) DEFAULT 0.00,
  `bonus_amount` DECIMAL(10,2) DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL,
  `status` ENUM('active','matured','closed','defaulted') DEFAULT 'active',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_scheme_id` (`scheme_id`),
  INDEX `idx_customer_id` (`customer_id`),
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`scheme_id`) REFERENCES `schemes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customer`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `scheme_rewards` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `scheme_id` INT NOT NULL,
  `firm_id` INT NOT NULL,
  `rank` INT NOT NULL,
  `prize_name` VARCHAR(255) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_scheme_id` (`scheme_id`),
  INDEX `idx_firm_id` (`firm_id`),
  FOREIGN KEY (`scheme_id`) REFERENCES `schemes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `gold_saving_plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `plan_name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `duration_months` INT NOT NULL,
  `min_amount_per_installment` DECIMAL(10,2) DEFAULT NULL,
  `installment_frequency` ENUM('daily','weekly','monthly','custom') NOT NULL,
  `bonus_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `terms_conditions` TEXT DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_gold_plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `customer_id` INT NOT NULL,
  `plan_id` INT NOT NULL,
  `enrollment_date` DATE NOT NULL,
  `maturity_date` DATE NOT NULL,
  `current_status` ENUM('active','matured','closed','defaulted','cancelled') NOT NULL DEFAULT 'active',
  `total_amount_paid` DECIMAL(12,2) DEFAULT 0.00,
  `total_gold_accrued` DECIMAL(10,4) DEFAULT 0.0000,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_customer_id` (`customer_id`),
  INDEX `idx_plan_id` (`plan_id`),
  INDEX `idx_current_status` (`current_status`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customer`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`plan_id`) REFERENCES `gold_saving_plans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gold_plan_installments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_plan_id` INT NOT NULL,
  `payment_date` DATE NOT NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL,
  `gold_credited_g` DECIMAL(10,4) DEFAULT NULL,
  `receipt_number` VARCHAR(100) DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_customer_plan_id` (`customer_plan_id`),
  INDEX `idx_payment_date` (`payment_date`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`customer_plan_id`) REFERENCES `customer_gold_plans`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `firm_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Marketing & Promotions Tables
CREATE TABLE `coupons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `coupon_code` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `discount_type` ENUM('percentage','fixed') NOT NULL,
  `discount_value` DECIMAL(10,2) NOT NULL,
  `min_purchase_amount` DECIMAL(10,2) DEFAULT NULL,
  `max_discount_amount` DECIMAL(10,2) DEFAULT NULL,
  `start_date` DATETIME DEFAULT NULL,
  `expiry_date` DATETIME DEFAULT NULL,
  `usage_limit_total` INT DEFAULT NULL,
  `usage_limit_customer` INT DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `gst_applicability` ENUM('any','gst_only','non_gst_only') NOT NULL DEFAULT 'any',
  `coupon_purpose` ENUM('general','welcome','post_purchase_reward') NOT NULL DEFAULT 'general',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_coupon_code` (`coupon_code`),
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_is_active` (`is_active`),
  INDEX `idx_expiry_date` (`expiry_date`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_assigned_coupons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT NOT NULL,
  `coupon_id` INT NOT NULL,
  `times_used` INT NOT NULL DEFAULT 0,
  `assigned_date` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('available','used','expired_for_customer','revoked') NOT NULL DEFAULT 'available',
  `last_used_date` DATETIME DEFAULT NULL,
  `related_sale_id` INT DEFAULT NULL,
  `firm_id` INT NOT NULL DEFAULT 1,
  INDEX `idx_customer_id` (`customer_id`),
  INDEX `idx_coupon_id` (`coupon_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_firm_id` (`firm_id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customer`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`coupon_id`) REFERENCES `coupons`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Catalog Tables
CREATE TABLE `jewellery_product_image` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `image_type` VARCHAR(50) DEFAULT 'main',
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_product_id` (`product_id`),
  INDEX `idx_image_type` (`image_type`),
  INDEX `idx_sort_order` (`sort_order`),
  FOREIGN KEY (`product_id`) REFERENCES `jewellery_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jewelentry_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `parent_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_parent_id` (`parent_id`),
  FOREIGN KEY (`parent_id`) REFERENCES `jewelentry_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System & Configuration Tables
CREATE TABLE `subscription_plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `duration_in_days` INT NOT NULL,
  `features` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `date` DATE NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_category` (`category`),
  INDEX `idx_date` (`date`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `firm_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `expense_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_firm_id` (`firm_id`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `order_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `image_type` VARCHAR(50) DEFAULT 'reference',
  `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_order_id` (`order_id`),
  INDEX `idx_image_type` (`image_type`),
  FOREIGN KEY (`order_id`) REFERENCES `jewellery_customer_order`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 