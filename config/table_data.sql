
CREATE TABLE `loan_emis` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `loan_id` INT(11) NOT NULL,                -- Reference to loans.id
  `customer_id` INT(11) NOT NULL,            -- Reference to customer.id
  `due_date` DATE NOT NULL,                  -- When this EMI is due
  `amount` DECIMAL(12,2) NOT NULL,           -- EMI amount
  `status` ENUM('due','paid','late','waived') DEFAULT 'due', -- Current status
  `paid_date` DATE DEFAULT NULL,             -- When paid (if paid)
  `payment_mode` VARCHAR(50) DEFAULT NULL,   -- Cash, UPI, etc.
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `loan_id` (`loan_id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


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



CREATE TABLE `customer` (
  `id` int(11) NOT NULL,
  `FirmID` int(11) NOT NULL,
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
(1, 39, 1, 0, '2025-05-25 14:22:39', 'available', NULL, NULL, 1),
(2, 39, 2, 0, '2025-05-25 14:33:08', 'available', NULL, NULL, 1);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `customer_gold_plans`
--

INSERT INTO `customer_gold_plans` (`id`, `firm_id`, `customer_id`, `plan_id`, `enrollment_date`, `maturity_date`, `current_status`, `total_amount_paid`, `total_gold_accrued`, `notes`, `created_at`, `updated_at`) VALUES
(2, 1, 18, 1, '2025-05-29', '2025-10-31', 'active', 78000.00, 0.0000, '45', '2025-05-28 23:15:59', '2025-05-28 23:15:59');

-- --------------------------------------------------------

--
-CREATE TABLE `schemes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
- Table structure for table `customer_orders`
--

--CREATE TABLE `scheme_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scheme_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `entry_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `entry_number` varchar(100) NOT NULL,
  `status` enum('valid','invalid','used','cancelled') DEFAULT 'valid',
  `purchase_amount` decimal(10,2) DEFAULT 0.00,
  `sale_id` int(11) DEFAULT NULL,
  `entry_method` enum('manual','registration','purchase') DEFAULT 'manual',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `scheme_id` (`scheme_id`),
  KEY `customer_id` (`customer_id`),
  KEY `sale_id` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firm_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `tax_id` varchar(100) DEFAULT NULL,
  `payment_terms` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `firm_id` (`firm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `firm`
--CREATE TABLE `loans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`id`),
  KEY `firm_id` (`firm_id`),
  KEY `customer_id` (`customer_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `UpdatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `current_subscription_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_in_days` int(11) NOT NULL,
  `features` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- DINSERT INTO `subscription_plans` (`id`, `name`, `price`, `duration_in_days`, `features`, `is_active`) VALUES
(1, 'Trial', 0.00, 7, 'Limited access, Email support only', 1),
(2, 'Basic', 499.00, 30, 'Inventory, Sales, Customers, Catalog, Billing', 1),
(3, 'Standard', 6900.00, 365, 'Inventory, Sales, Customers, Catalog, Billing, Analytics', 1),
(4, 'Premium', 24900.00, 1095, 'Inventory, Sales, Customers, Catalog, Billing, Analytics, Priority Support', 1);
umping
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
  `auto_scheme_entry` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `firm_configurations`
--

INSERT INTO `firm_configurations` (`id`, `firm_id`, `non_gst_bill_page_url`, `gst_bill_page_url`, `coupon_code_apply_enabled`, `schemes_enabled`, `gst_rate`, `loyalty_discount_percentage`, `welcome_coupon_enabled`, `welcome_coupon_code`, `auto_scheme_entry`, `created_at`, `updated_at`) VALUES
(1, 1, 'thermal_invoice.php', 'invoice.php', 1, 1, 0.0300, 0.0200, 1, 'WELCOME10', 1, '2025-05-25 13:32:24', '2025-05-25 14:06:22');

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
(1, 1, 1, '2025-05-28 19:42:36', '2025-06-07 23:12:37', '2025-06-27 23:12:37', 1, 1, 1, NULL, NULL, NULL, NULL);

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
  `image_path` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `firm_users`
--

INSERT INTO `firm_users` (`id`, `Name`, `Username`, `Password`, `FirmID`, `Email`, `PhoneNumber`, `Role`, `Status`, `CreatedAt`, `UpdatedAt`, `image_path`) VALUES
(1, 'Prosentji Halder', 'dmeouser', '$2y$10$KkxCtL0VTcmiCAaXi7Wbr.wp0PoSoLR3b/a42uW8YEIh4GCl7.qXm', 4, 'Jeettechnoguide@gmail.com', '9891582296', 'Super Admin', 'Active', '2025-04-21 21:00:48', '2025-05-02 21:48:22', ''),
(2, 'Prosenjit Halder', 'admin', '$2y$10$apaj5viKfHsIo5OW/9aFLOn7OSpJMGQFFwpaMTWd/WKvUDEbje8A.', 1, 'MAHALAXMIHC@GMAIL.COM', '09810359334', 'Super Admin', 'Active', '2025-04-22 06:21:56', '2025-05-02 21:48:30', '');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `gold_plan_installments`
--

INSERT INTO `gold_plan_installments` (`id`, `customer_plan_id`, `payment_date`, `amount_paid`, `gold_credited_g`, `receipt_number`, `payment_method`, `notes`, `created_by`, `created_at`) VALUES
(1, 2, '2025-05-29', 5000.00, 50.6000, '45\r\n\r\n4', 'Cash', 'czxc', 1, '2025-05-28 23:16:56');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `gold_saving_plans`
--

INSERT INTO `gold_saving_plans` (`id`, `firm_id`, `plan_name`, `description`, `duration_months`, `min_amount_per_installment`, `installment_frequency`, `bonus_percentage`, `status`, `terms_conditions`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, '11+1 Monthly Saver', 'Pay for 11 months, get 1 month bonus', 12, 1000.00, 'monthly', 8.33, 'active', NULL, NULL, '2025-05-28 23:14:30', '2025-05-28 23:14:30'),
(2, 1, '11+1 Monthly Saver', 'Pay for 11 months, get 1 month bonus', 12, 1000.00, 'monthly', 8.33, 'active', NULL, NULL, '2025-05-28 23:15:05', '2025-05-28 23:15:05');


--

CREATE TABLE `inventory_metals` (
  `inventory_id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL DEFAULT 1,
  `material_type` varchar(50) NOT NULL,
  `stock_name` varchar(100) NOT NULL,
  `purity` decimal(50,2) NOT NULL,
  `current_stock` decimal(10,3) NOT NULL DEFAULT 0.000,
  `remaining_stock` decimal(10,3) NOT NULL DEFAULT 0.000,
  `unit_measurement` varchar(20) DEFAULT 'grams',
  `last_updated` timestamp NULL DEFAULT current_timestamp(),
  `minimum_stock_level` decimal(10,3) DEFAULT 0.000,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source_type` text NOT NULL,
  `source_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `jewelentry_category` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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

CREATE TABLE `jewellery_items` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL DEFAULT 1,
  `product_id` varchar(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `jewelry_type` varchar(50) NOT NULL,
  `product_name` varchar(50) NOT NULL,
  `material_type` enum('Gold','Silver') NOT NULL,
  `purity` decimal(10,2) DEFAULT NULL,
  `huid_code` varchar(255) NOT NULL,
  `gross_weight` decimal(10,3) NOT NULL,
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


--

CREATE TABLE `jewellery_order_items` (
  `id` int(11) NOT NULL,
  `karigar_id` int(11) NOT NULL,
  `firm_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
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
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

--

CREATE TABLE `jewellery_payments` (
  `id` int(11) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` enum('customer_order','sale','loan','due_invoice','karigar_salary','purchase','expense','liability_invoice') NOT NULL,
  `party_type` enum('customer','karigar','vendor','staff','other') NOT NULL,
  `party_id` int(11) DEFAULT NULL,
  `sale_id` int(11) NOT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  ` payment_notes` varchar(100) DEFAULT NULL,
  `reference_no` varchar(50) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `transctions_type` enum('credit','debit') NOT NULL,
  `Firm_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `jewellery_product_image` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `firm_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



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


CREATE TABLE `jewellery_stock_log` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `material_type` varchar(50) NOT NULL,
  `stock_name` varchar(100) NOT NULL,
  `purity` varchar(20) DEFAULT NULL,
  `transaction_type` enum('IN','OUT') NOT NULL DEFAULT 'IN',
  `quantity_before` decimal(10,2) DEFAULT 0.00,
  `quantity_change` decimal(10,2) NOT NULL,
  `quantity_after` decimal(10,2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `jewellery_trays` (
  `id` int(11) NOT NULL,
  `tray_number` varchar(50) NOT NULL,
  `tray_type` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT 20,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `karigar_ledger` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `karigar_id` int(11) NOT NULL,
  `mo_id` int(11) NOT NULL,
  `txn_id` varchar(100) NOT NULL,
  `entry_type` varchar(50) NOT NULL,
  `metal_type` varchar(50) NOT NULL,
  `purity` varchar(50) NOT NULL,
  `weight` decimal(10,3) DEFAULT 0.000,
  `amount` decimal(10,2) DEFAULT 0.00,
  `narration` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

CREATE TABLE `loans` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `loan_date` date NOT NULL,
  `principal_amount` decimal(12,2) NOT NULL,
  `interest_rate` decimal(5,2) DEFAULT 0.00,
  `loan_term_months` int(11) DEFAULT NULL,
  `maturity_date` date DEFAULT NULL,
  `current_status` enum('active','paid','closed','defaulted') NOT NULL DEFAULT 'active',
  `total_amount_paid` decimal(12,2) DEFAULT 0.00,
  `outstanding_amount` decimal(12,2) NOT NULL,
  `collateral_description` text DEFAULT NULL,
  `collateral_value` decimal(12,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


-- Table structure for table `manufacturers`
-
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
  `payment_status` enum('Pending','Completed','Cancelled') DEFAULT 'Pending',
  `inventory_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `firm_id` int(11) NOT NULL,
  `weight` decimal(10,0) NOT NULL,
  `paid_amount` int(11) NOT NULL,
  `payment_mode` varchar(50) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `entry_type` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_images` (
  `id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--

CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `duration_in_days` int(11) NOT NULL,
  `features` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_info` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `date_added` datetime DEFAULT current_timestamp(),
  `last_updated` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--

CREATE TABLE `urd_items` (
  `id` int(11) NOT NULL,
  `firm_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `gross_weight` decimal(10,3) NOT NULL,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) NOT NULL,
  `purity` decimal(5,2) NOT NULL,
  `rate` decimal(10,2) NOT NULL,
  `fine_weight` decimal(10,3) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `image_data` longtext DEFAULT NULL,
  `received_date` date NOT NULL,
  `status` enum('recived','exchanged','Pending','In_Process','Refined','Converted','Sold') DEFAULT 'Pending',
  `process_type` enum('Pending','Refining','Direct_Convert','Scrap') DEFAULT NULL,
  `processed_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

