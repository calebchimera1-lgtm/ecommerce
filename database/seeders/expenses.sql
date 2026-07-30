-- =====================================================================
-- Demo expenses for the admin dashboard's Expenses/Profit widgets.
-- Optional - run after database/kymera_collection.sql if you want the
-- dashboard to show non-zero expense/profit figures instead of zero.
-- Full expense entry/management is a later module (Reports); this is
-- read-only demo data, the same pattern Module 5 used for testimonials.
-- =====================================================================

USE `kymera_collection`;

INSERT INTO `expenses` (`category`, `description`, `amount`, `expense_date`) VALUES
    ('Marketing', 'Social media advertising campaign', 850.00, CURDATE()),
    ('Shipping', 'Courier partner monthly fee', 420.00, CURDATE()),
    ('Software', 'E-commerce platform hosting and tools', 199.00, CURDATE() - INTERVAL 3 DAY),
    ('Packaging', 'Branded boxes and tissue paper', 310.00, CURDATE() - INTERVAL 5 DAY),
    ('Marketing', 'Influencer partnership', 1200.00, CURDATE() - INTERVAL 10 DAY),
    ('Utilities', 'Warehouse electricity and internet', 260.00, CURDATE() - INTERVAL 15 DAY);
