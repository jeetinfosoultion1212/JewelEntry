-- JewelEntryApp: Optimized Core Tables
-- This file contains optimized CREATE TABLE statements for core business tables with indexes and foreign keys

CREATE TABLE `firm` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `FirmName` VARCHAR(150) NOT NULL,
  `OwnerName` VARCHAR(100),
  `Email` VARCHAR(255),
  `PhoneNumber` VARCHAR(20),
  `Address` VARCHAR(255),
  `City` VARCHAR(100),
  `State` VARCHAR(100),
  `PostalCode` VARCHAR(20),
  `Country` VARCHAR(100) DEFAULT 'India',
  `PANNumber` VARCHAR(10),
  `GSTNumber` VARCHAR(15),
  `IsGSTRegistered` TINYINT(1) DEFAULT 0,
  `BISRegistrationNumber` VARCHAR(50),
  `BankAccountNumber` VARCHAR(20),
  `BankName` VARCHAR(100),
  `BankBranch` VARCHAR(100),
  `IFSCCode` VARCHAR(11),
  `AccountType` ENUM('Savings','Current') DEFAULT 'Current',
  `Logo` LONGBLOB,
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `Tagline` TEXT,
  `CreatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `current_subscription_id` INT,
  UNIQUE KEY `unique_email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `FirstName` VARCHAR(100) NOT NULL,
  `LastName` VARCHAR(100) NOT NULL,
  `Email` VARCHAR(50),
  `PhoneNumber` VARCHAR(20) NOT NULL,
  `Address` VARCHAR(255),
  `City` VARCHAR(100),
  `State` VARCHAR(100),
  `PostalCode` VARCHAR(20),
  `Country` VARCHAR(100) DEFAULT 'India',
  `DateOfBirth` DATE,
  `SpecialDay` DATE,
  `Gender` ENUM('Male','Female','Other'),
  `CustomerType` ENUM('Lead','Prospect','Customer','VIP') DEFAULT 'Lead',
  `PANNumber` VARCHAR(10),
  `AadhaarNumber` VARCHAR(12),
  `IsGSTRegistered` TINYINT(1) DEFAULT 0,
  `GSTNumber` VARCHAR(15),
  `CustomerImage` LONGBLOB,
  `CreatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_email` (`Email`),
  INDEX `idx_firm_id` (`firm_id`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `contact_info` VARCHAR(255),
  `email` VARCHAR(255),
  `address` TEXT,
  `state` VARCHAR(50),
  `phone` VARCHAR(50),
  `gst` VARCHAR(100),
  `payment_terms` VARCHAR(255),
  `notes` TEXT,
  `date_added` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_firm_id` (`firm_id`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `firm_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(50) NOT NULL,
  `Username` VARCHAR(100) NOT NULL,
  `Password` VARCHAR(255) NOT NULL,
  `FirmID` INT NOT NULL,
  `Email` VARCHAR(255),
  `PhoneNumber` VARCHAR(15),
  `Role` VARCHAR(50) DEFAULT 'Admin',
  `Status` VARCHAR(20) DEFAULT 'Active',
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `image_path` VARCHAR(255),
  `reset_token` VARCHAR(64),
  `token_expiry` DATETIME,
  `remember_token` VARCHAR(64),
  `token_expiration` DATETIME,
  UNIQUE KEY `unique_username` (`Username`),
  INDEX `idx_firm_id` (`FirmID`),
  FOREIGN KEY (`FirmID`) REFERENCES `firm`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `firm_configurations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `non_gst_bill_page_url` VARCHAR(255) DEFAULT 'thermal_invoice.php',
  `gst_bill_page_url` VARCHAR(255) DEFAULT 'thermal_invoice.php',
  `coupon_code_apply_enabled` TINYINT(1) DEFAULT 1,
  `schemes_enabled` TINYINT(1) DEFAULT 1,
  `gst_rate` DECIMAL(5,4) DEFAULT 0.0300,
  `loyalty_discount_percentage` DECIMAL(5,4) DEFAULT 0.0200,
  `welcome_coupon_enabled` TINYINT(1) DEFAULT 1,
  `welcome_coupon_code` VARCHAR(50) DEFAULT 'WELCOME10',
  `post_purchase_coupon_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `auto_scheme_entry` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_firm_id` (`firm_id`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `firm_subscriptions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `plan_id` INT NOT NULL,
  `start_date` DATETIME NOT NULL,
  `end_date` DATETIME NOT NULL,
  `trial_end_date` DATETIME,
  `is_trial` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `auto_renew` TINYINT(1) DEFAULT 1,
  `payment_method` VARCHAR(50),
  `last_payment_date` DATETIME,
  `next_billing_date` DATETIME,
  `notes` TEXT,
  INDEX `idx_firm_id` (`firm_id`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci; 
-- JewelEntryApp: Optimized Inventory & Stock Management Tables
-- This file contains optimized CREATE TABLE statements for inventory management with indexes and foreign keys

CREATE TABLE `inventory_metals` (
  `inventory_id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL DEFAULT 1,
  `material_type` VARCHAR(50) NOT NULL,
  `stock_name` VARCHAR(100) NOT NULL,
  `purity` DECIMAL(50,2) NOT NULL,
  `current_stock` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `remaining_stock` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `cost_price_per_gram` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `unit_measurement` VARCHAR(20) DEFAULT 'grams',
  `last_updated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `minimum_stock_level` DECIMAL(10,3) DEFAULT 0.000,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `source_type` TEXT NOT NULL,
  `source_id` INT DEFAULT NULL,
  `total_cost` FLOAT NOT NULL,
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_material_type` (`material_type`),
  INDEX `idx_purity` (`purity`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jewellery_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL DEFAULT 1,
  `product_id` VARCHAR(11) NOT NULL,
  `source_id` INT NOT NULL,
  `source_type` VARCHAR(50) NOT NULL,
  `jewelry_type` VARCHAR(50) NOT NULL,
  `product_name` VARCHAR(50) NOT NULL,
  `material_type` ENUM('Gold','Silver') NOT NULL,
  `purity` DECIMAL(10,2) DEFAULT NULL,
  `huid_code` VARCHAR(255) NOT NULL,
  `gross_weight` DECIMAL(10,3) NOT NULL,
  `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `less_weight` FLOAT NOT NULL,
  `net_weight` DECIMAL(10,3) NOT NULL,
  `stone_type` VARCHAR(50) DEFAULT 'None',
  `stone_weight` DECIMAL(10,3) DEFAULT 0.000,
  `stone_unit` VARCHAR(100) NOT NULL,
  `stone_color` VARCHAR(100) NOT NULL,
  `stone_clarity` VARCHAR(100) NOT NULL,
  `stone_quality` VARCHAR(50) NOT NULL DEFAULT 'none',
  `stone_price` DECIMAL(10,0) NOT NULL DEFAULT 0,
  `making_charge` DECIMAL(5,2) DEFAULT 0.00,
  `rate_per_gram` FLOAT NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `making_charge_type` VARCHAR(20) DEFAULT 'percentage',
  `wastage_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Available',
  `manufacturing_order_id` INT NOT NULL,
  `karigar_id` INT NOT NULL,
  `image_path` VARCHAR(500) NOT NULL,
  `supplier_id` INT NOT NULL,
  `Tray_no` VARCHAR(50) NOT NULL,
  `quantity` INT NOT NULL,
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_product_id` (`product_id`),
  INDEX `idx_material_type` (`material_type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_supplier_id` (`supplier_id`),
  INDEX `idx_karigar_id` (`karigar_id`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jewellery_stock_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `item_id` INT NOT NULL,
  `transaction_type` ENUM('IN','OUT','ADJUSTMENT','TRANSFER') NOT NULL,
  `quantity` DECIMAL(10,3) NOT NULL,
  `previous_stock` DECIMAL(10,3) NOT NULL,
  `new_stock` DECIMAL(10,3) NOT NULL,
  `reference_id` INT DEFAULT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `transaction_date` DATETIME NOT NULL,
  `created_by` INT NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_item_id` (`item_id`),
  INDEX `idx_transaction_type` (`transaction_type`),
  INDEX `idx_transaction_date` (`transaction_date`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `jewellery_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `firm_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `trays` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT DEFAULT NULL,
  `tray_number` VARCHAR(50) DEFAULT NULL,
  `tray_type` VARCHAR(50) DEFAULT NULL,
  `location` VARCHAR(100) DEFAULT NULL,
  `capacity` INT DEFAULT NULL,
  `current_items` INT DEFAULT 0,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active','inactive','maintenance') DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_tray_number` (`tray_number`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `metal_purchases` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `supplier_id` INT NOT NULL,
  `purchase_date` DATE NOT NULL,
  `invoice_number` VARCHAR(50) DEFAULT NULL,
  `material_type` VARCHAR(50) NOT NULL,
  `purity` DECIMAL(5,2) NOT NULL,
  `quantity` DECIMAL(10,3) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL,
  `payment_status` ENUM('pending','partial','completed') DEFAULT 'pending',
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `paid_amount` DECIMAL(12,2) DEFAULT 0.00,
  `due_amount` DECIMAL(12,2) GENERATED ALWAYS AS (`total_amount` - `paid_amount`) STORED,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_supplier_id` (`supplier_id`),
  INDEX `idx_purchase_date` (`purchase_date`),
  INDEX `idx_material_type` (`material_type`),
  INDEX `idx_payment_status` (`payment_status`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `firm_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 
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
-- JewelEntryApp: Optimized Sales & Order Management Tables
-- This file contains optimized CREATE TABLE statements for sales management with indexes and foreign keys

CREATE TABLE `jewellery_sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `sale_number` VARCHAR(50) NOT NULL,
  `customer_id` INT NOT NULL,
  `sale_date` DATETIME NOT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
  `grand_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(12,2) DEFAULT 0.00,
  `due_amount` DECIMAL(12,2) GENERATED ALWAYS AS (`grand_total` - `paid_amount`) STORED,
  `payment_status` ENUM('pending','partial','completed') DEFAULT 'pending',
  `sale_type` ENUM('retail','wholesale','exchange') DEFAULT 'retail',
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `invoice_number` VARCHAR(50) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_sale_number` (`sale_number`),
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_customer_id` (`customer_id`),
  INDEX `idx_sale_date` (`sale_date`),
  INDEX `idx_payment_status` (`payment_status`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customer`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `firm_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jewellery_sales_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `product_id` VARCHAR(11) NOT NULL,
  `product_name` VARCHAR(100) NOT NULL,
  `huid_code` VARCHAR(255) NOT NULL,
  `rate_24k` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `purity` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `purity_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `gross_weight` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `less_weight` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `net_weight` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `metal_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `stone_type` VARCHAR(50) DEFAULT 'None',
  `stone_weight` DECIMAL(10,3) DEFAULT 0.000,
  `stone_price` DECIMAL(10,2) DEFAULT 0.00,
  `making_type` ENUM('per_gram','percentage','fixed') DEFAULT 'percentage',
  `making_rate` DECIMAL(10,2) DEFAULT 0.00,
  `making_charges` DECIMAL(10,2) DEFAULT 0.00,
  `hm_charges` DECIMAL(10,2) DEFAULT 0.00,
  `other_charges` DECIMAL(10,2) DEFAULT 0.00,
  `total_charges` DECIMAL(12,2) DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_sale_id` (`sale_id`),
  INDEX `idx_product_id` (`product_id`),
  INDEX `idx_huid_code` (`huid_code`),
  INDEX `idx_purity` (`purity`),
  INDEX `idx_making_type` (`making_type`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`sale_id`) REFERENCES `jewellery_sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jewellery_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reference_id` INT DEFAULT NULL,
  `reference_type` ENUM('customer_order','sale','loan','due_invoice','karigar_salary','purchase','expense','liability_invoice') NOT NULL,
  `party_type` ENUM('customer','karigar','vendor','staff','other','supplier') DEFAULT NULL,
  `party_id` INT DEFAULT NULL,
  `sale_id` INT DEFAULT NULL,
  `payment_type` VARCHAR(50) DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_notes` VARCHAR(100) DEFAULT NULL,
  `reference_no` VARCHAR(50) NOT NULL,
  `remarks` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `transctions_type` ENUM('credit','debit') NOT NULL,
  `Firm_id` INT NOT NULL DEFAULT 1,
  INDEX `idx_reference_id` (`reference_id`),
  INDEX `idx_reference_type` (`reference_type`),
  INDEX `idx_party_id` (`party_id`),
  INDEX `idx_sale_id` (`sale_id`),
  INDEX `idx_firm_id` (`Firm_id`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`Firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sale_id`) REFERENCES `jewellery_sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jewellery_price_config` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `material_type` VARCHAR(50) NOT NULL,
  `purity` DECIMAL(5,2) NOT NULL,
  `rate_per_gram` DECIMAL(10,2) NOT NULL,
  `making_charge_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `making_charge_fixed` DECIMAL(10,2) DEFAULT 0.00,
  `wastage_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `is_active` TINYINT(1) DEFAULT 1,
  `effective_date` DATE NOT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_material_type` (`material_type`),
  INDEX `idx_purity` (`purity`),
  INDEX `idx_effective_date` (`effective_date`),
  INDEX `idx_is_active` (`is_active`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `firm_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jewellery_customer_order` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `customer_id` INT NOT NULL,
  `karigar_id` INT DEFAULT NULL,
  `order_number` VARCHAR(20) DEFAULT NULL,
  `order_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `expected_delivery_date` DATE DEFAULT NULL,
  `total_metal_amount` DECIMAL(10,2) DEFAULT NULL,
  `total_making_charges` DECIMAL(10,2) DEFAULT NULL,
  `total_stone_amount` DECIMAL(10,2) DEFAULT NULL,
  `grand_total` DECIMAL(10,2) DEFAULT NULL,
  `advance_amount` DECIMAL(10,2) DEFAULT NULL,
  `remaining_amount` DECIMAL(10,2) DEFAULT NULL,
  `payment_method` ENUM('cash','card','upi','bank_transfer') DEFAULT NULL,
  `payment_status` ENUM('pending','partial','completed') DEFAULT NULL,
  `order_status` ENUM('pending','in progress','ready','cancelled') DEFAULT NULL,
  `priority` ENUM('normal','high','urgent') DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_customer_id` (`customer_id`),
  INDEX `idx_karigar_id` (`karigar_id`),
  INDEX `idx_order_date` (`order_date`),
  INDEX `idx_order_status` (`order_status`),
  INDEX `idx_payment_status` (`payment_status`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customer`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jewellery_order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `karigar_id` INT NOT NULL,
  `firm_id` INT DEFAULT NULL,
  `order_id` INT DEFAULT NULL,
  `item_status` VARCHAR(50) NOT NULL,
  `item_name` VARCHAR(100) DEFAULT NULL,
  `product_type` VARCHAR(50) DEFAULT NULL,
  `design_reference` VARCHAR(100) DEFAULT NULL,
  `metal_type` VARCHAR(50) DEFAULT NULL,
  `purity` DECIMAL(5,2) DEFAULT NULL,
  `gross_weight` DECIMAL(10,3) DEFAULT NULL,
  `less_weight` DECIMAL(10,3) DEFAULT NULL,
  `net_weight` DECIMAL(10,3) DEFAULT NULL,
  `metal_amount` DECIMAL(10,2) DEFAULT NULL,
  `stone_type` VARCHAR(50) DEFAULT NULL,
  `stone_quality` VARCHAR(50) DEFAULT NULL,
  `stone_size` VARCHAR(50) DEFAULT NULL,
  `stone_quantity` INT DEFAULT NULL,
  `stone_weight` DECIMAL(10,3) DEFAULT NULL,
  `stone_unit` VARCHAR(10) DEFAULT NULL,
  `stone_price` DECIMAL(10,2) DEFAULT NULL,
  `stone_details` TEXT DEFAULT NULL,
  `making_type` ENUM('per_gram','percentage','fixed') DEFAULT NULL,
  `making_charge_input` DECIMAL(10,2) DEFAULT NULL,
  `making_charges` DECIMAL(10,2) DEFAULT NULL,
  `size_details` TEXT DEFAULT NULL,
  `design_customization` TEXT DEFAULT NULL,
  `total_estimate` DECIMAL(10,2) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reference_images` TEXT NOT NULL,
  `created_by` INT DEFAULT NULL,
  `updated_by` INT DEFAULT NULL,
  `issued_metal_type` VARCHAR(50) DEFAULT NULL,
  `issued_purity` DECIMAL(5,2) DEFAULT NULL,
  `issued_weight` DECIMAL(10,3) DEFAULT NULL,
  INDEX `idx_karigar_id` (`karigar_id`),
  INDEX `idx_firm_id` (`firm_id`),
  INDEX `idx_order_id` (`order_id`),
  INDEX `idx_item_status` (`item_status`),
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`firm_id`) REFERENCES `firm`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `jewellery_customer_order`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `firm_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 