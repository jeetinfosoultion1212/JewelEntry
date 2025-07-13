-- Subscription Plans Data
-- Insert plans for JewelEntry application

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