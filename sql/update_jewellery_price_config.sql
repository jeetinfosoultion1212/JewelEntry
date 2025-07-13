-- Update jewellery_price_config table structure
-- This script adds proper indexing and foreign key constraints

-- First, let's check if the table exists and create it if not
CREATE TABLE IF NOT EXISTS `jewellery_price_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_type` varchar(50) NOT NULL COMMENT 'Gold, Silver, Platinum',
  `purity` varchar(20) NOT NULL COMMENT '99.99, 999.9, 95, etc.',
  `unit` varchar(20) NOT NULL DEFAULT 'gram' COMMENT 'gram, tola, vori, kg',
  `rate` decimal(10,2) NOT NULL COMMENT 'Rate per unit',
  `effective_date` date NOT NULL COMMENT 'Date when rate becomes effective',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `firm_id` int(11) NOT NULL COMMENT 'Foreign key to Firm table',
  PRIMARY KEY (`id`),
  KEY `idx_firm_id` (`firm_id`),
  KEY `idx_material_purity` (`material_type`, `purity`),
  KEY `idx_effective_date` (`effective_date`),
  KEY `idx_firm_material_purity` (`firm_id`, `material_type`, `purity`),
  CONSTRAINT `fk_jewellery_price_config_firm` FOREIGN KEY (`firm_id`) REFERENCES `Firm` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jewellery price configuration per firm';

-- Add indexes for better performance
-- Index for firm-specific queries
CREATE INDEX IF NOT EXISTS `idx_jewellery_price_firm_date` ON `jewellery_price_config` (`firm_id`, `effective_date`);

-- Index for material type queries
CREATE INDEX IF NOT EXISTS `idx_jewellery_price_material` ON `jewellery_price_config` (`material_type`);

-- Index for date range queries
CREATE INDEX IF NOT EXISTS `idx_jewellery_price_date_range` ON `jewellery_price_config` (`effective_date`, `created_at`);

-- Insert sample data for testing (optional)
-- INSERT INTO `jewellery_price_config` (`material_type`, `purity`, `unit`, `rate`, `effective_date`, `firm_id`) VALUES
-- ('Gold', '99.99', 'gram', 6500.00, CURDATE(), 1),
-- ('Silver', '999.9', 'gram', 75.00, CURDATE(), 1),
-- ('Platinum', '95', 'gram', 3500.00, CURDATE(), 1);

-- Update existing records if any (optional - uncomment if needed)
-- UPDATE `jewellery_price_config` SET `firm_id` = 1 WHERE `firm_id` IS NULL OR `firm_id` = 0;

-- Add comments to table and columns for documentation
ALTER TABLE `jewellery_price_config` 
COMMENT = 'Jewellery price configuration per firm with effective dates';

-- Verify the table structure
DESCRIBE `jewellery_price_config`;

-- Show indexes
SHOW INDEX FROM `jewellery_price_config`; 