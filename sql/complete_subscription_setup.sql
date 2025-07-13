-- Complete Subscription Setup for JewelEntry
-- This file includes table creation and subscription plans data

-- Create subscription_plans table if it doesn't exist
CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    duration_in_days INT NOT NULL,
    features TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create firm_subscriptions table if it doesn't exist
CREATE TABLE IF NOT EXISTS firm_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firm_id INT NOT NULL,
    plan_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_trial TINYINT(1) DEFAULT 0,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE
);

-- Clear existing plans (optional - remove if you want to keep existing data)
-- DELETE FROM subscription_plans;

-- Insert subscription plans
INSERT INTO subscription_plans (name, price, duration_in_days, features, is_active) VALUES
(
    'Trial',
    0,
    7,
    'Inventory Management, Sales Tracking, Customer Management, Basic Reports, Mobile App Access, QR Code Generation, Basic Support, Limited to 7 days',
    1
),
(
    'Basic Monthly',
    699,
    30,
    'Inventory Management, Sales Entry, Customer Management, Basic Reports, Mobile App Access, QR Code Generation, Basic Support, Catalog Management, Daily Book Reports, Hallmark Management, Stock Reports, Karigars Management',
    1
),
(
    'Basic Yearly',
    2999,
    365,
    'All Monthly Features, Gold Loan Management, Billing & Invoicing, Advanced Reports, Priority Support, Data Backup, Multi-staff Access, Expense Tracking, Lucky Draw Schemes, Customer Orders, GST Reports, Tray Management, Suppliers Management, Staff Management, Settings Management',
    1
),
(
    'Premium One Time',
    14999,
    36500,
    'All Yearly Features, Unlimited Inventory, Advanced Analytics, Custom Branding, API Access, Priority 24/7 Support, Data Migration, Staff Training, Custom Integrations, Advanced Security, Multi-location Support, Advanced Reporting, Customer Loyalty Programs, Automated Backups, White-label Options, Repairs Management, Advanced Billing Features',
    1
);

-- Add indexes for better performance
CREATE INDEX idx_firm_subscriptions_firm_id ON firm_subscriptions(firm_id);
CREATE INDEX idx_firm_subscriptions_plan_id ON firm_subscriptions(plan_id);
CREATE INDEX idx_firm_subscriptions_active ON firm_subscriptions(is_active);
CREATE INDEX idx_subscription_plans_active ON subscription_plans(is_active); 