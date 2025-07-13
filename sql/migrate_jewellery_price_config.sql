-- Migration script for jewellery_price_config table
-- This script updates existing table structure without losing data

-- Check if table exists
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                     WHERE table_schema = DATABASE() 
                     AND table_name = 'jewellery_price_config');

-- If table doesn't exist, create it
SET @create_table = IF(@table_exists = 0, 
    'CREATE TABLE `jewellery_price_config` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `material_type` varchar(50) NOT NULL COMMENT "Gold, Silver, Platinum",
        `purity` varchar(20) NOT NULL COMMENT "99.99, 999.9, 95, etc.",
        `unit` varchar(20) NOT NULL DEFAULT "gram" COMMENT "gram, tola, vori, kg",
        `rate` decimal(10,2) NOT NULL COMMENT "Rate per unit",
        `effective_date` date NOT NULL COMMENT "Date when rate becomes effective",
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `firm_id` int(11) NOT NULL COMMENT "Foreign key to Firm table",
        PRIMARY KEY (`id`),
        KEY `idx_firm_id` (`firm_id`),
        KEY `idx_material_purity` (`material_type`, `purity`),
        KEY `idx_effective_date` (`effective_date`),
        KEY `idx_firm_material_purity` (`firm_id`, `material_type`, `purity`),
        CONSTRAINT `fk_jewellery_price_config_firm` FOREIGN KEY (`firm_id`) REFERENCES `Firm` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT="Jewellery price configuration per firm"',
    'SELECT "Table already exists" as status'
);

PREPARE stmt FROM @create_table;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- If table exists, add missing columns
SET @alter_table = IF(@table_exists = 1, 
    'ALTER TABLE `jewellery_price_config` 
     ADD COLUMN IF NOT EXISTS `firm_id` int(11) NOT NULL COMMENT "Foreign key to Firm table" AFTER `created_at`,
     ADD COLUMN IF NOT EXISTS `effective_date` date NOT NULL COMMENT "Date when rate becomes effective" AFTER `rate`,
     ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `effective_date`,
     MODIFY COLUMN `material_type` varchar(50) NOT NULL COMMENT "Gold, Silver, Platinum",
     MODIFY COLUMN `purity` varchar(20) NOT NULL COMMENT "99.99, 999.9, 95, etc.",
     MODIFY COLUMN `unit` varchar(20) NOT NULL DEFAULT "gram" COMMENT "gram, tola, vori, kg",
     MODIFY COLUMN `rate` decimal(10,2) NOT NULL COMMENT "Rate per unit"',
    'SELECT "Table created, no migration needed" as status'
);

PREPARE stmt FROM @alter_table;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes if they don't exist
CREATE INDEX IF NOT EXISTS `idx_firm_id` ON `jewellery_price_config` (`firm_id`);
CREATE INDEX IF NOT EXISTS `idx_material_purity` ON `jewellery_price_config` (`material_type`, `purity`);
CREATE INDEX IF NOT EXISTS `idx_effective_date` ON `jewellery_price_config` (`effective_date`);
CREATE INDEX IF NOT EXISTS `idx_firm_material_purity` ON `jewellery_price_config` (`firm_id`, `material_type`, `purity`);
CREATE INDEX IF NOT EXISTS `idx_jewellery_price_firm_date` ON `jewellery_price_config` (`firm_id`, `effective_date`);
CREATE INDEX IF NOT EXISTS `idx_jewellery_price_material` ON `jewellery_price_config` (`material_type`);
CREATE INDEX IF NOT EXISTS `idx_jewellery_price_date_range` ON `jewellery_price_config` (`effective_date`, `created_at`);

-- Add foreign key constraint if it doesn't exist
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.key_column_usage 
                  WHERE table_schema = DATABASE() 
                  AND table_name = 'jewellery_price_config' 
                  AND constraint_name = 'fk_jewellery_price_config_firm');

SET @add_fk = IF(@fk_exists = 0,
    'ALTER TABLE `jewellery_price_config` 
     ADD CONSTRAINT `fk_jewellery_price_config_firm` 
     FOREIGN KEY (`firm_id`) REFERENCES `Firm` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT "Foreign key already exists" as status'
);

PREPARE stmt FROM @add_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing records to have firm_id if missing
UPDATE `jewellery_price_config` SET `firm_id` = 1 WHERE `firm_id` IS NULL OR `firm_id` = 0;

-- Set effective_date to created_at if missing
UPDATE `jewellery_price_config` SET `effective_date` = DATE(`created_at`) WHERE `effective_date` IS NULL;

-- Verify the final table structure
DESCRIBE `jewellery_price_config`;

-- Show all indexes
SHOW INDEX FROM `jewellery_price_config`;

-- Show foreign key constraints
SELECT 
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'jewellery_price_config' 
AND REFERENCED_TABLE_NAME IS NOT NULL; 