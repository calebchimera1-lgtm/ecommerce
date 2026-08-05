-- =====================================================================
-- Migration 0004 - vendor marketplace foundation
--
-- Adds the schema needed to turn Kymera Collection from a single-vendor
-- store into a marketplace: a `vendors` table (business profile +
-- approval workflow, one row per vendor user account), a `vendor` role,
-- `vendor_id`/`approval_status` on `products` (both nullable/defaulted
-- so every existing product stays exactly as-is - NULL vendor_id means
-- "sold by the platform directly", not "unassigned"), a per-category
-- commission rate, and a platform-wide default commission setting.
--
-- Only needed if your database was seeded before this migration was
-- added. A fresh import of kymera_collection.sql already includes all
-- of this - do not run this against a database seeded from the
-- current kymera_collection.sql, it will fail with "duplicate column"
-- or "duplicate entry".
-- =====================================================================

USE `kymera_collection`;

CREATE TABLE `vendors` (
    `id`                            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`                       BIGINT UNSIGNED NOT NULL,
    `store_name`                    VARCHAR(150) NOT NULL,
    `slug`                          VARCHAR(170) NOT NULL,
    `description`                   TEXT NULL,
    `logo`                          VARCHAR(255) NULL,
    `phone`                         VARCHAR(30)  NULL,
    `business_registration_number` VARCHAR(100) NULL,
    `payout_details`                TEXT NULL COMMENT 'Free-form bank/payout info - payouts are recorded manually, not routed automatically',
    `status`                        ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
    `rejection_reason`              VARCHAR(255) NULL,
    `approved_by`                   BIGINT UNSIGNED NULL,
    `approved_at`                   TIMESTAMP NULL DEFAULT NULL,
    `created_at`                    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_vendors_user` (`user_id`),
    UNIQUE KEY `uq_vendors_slug` (`slug`),
    KEY `idx_vendors_status` (`status`),
    CONSTRAINT `fk_vendors_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vendors_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE `categories`
    ADD COLUMN `commission_rate` DECIMAL(5,2) NULL COMMENT 'Percentage charged to vendors selling in this category; NULL = use the platform default_commission_rate setting' AFTER `description`;

ALTER TABLE `products`
    ADD COLUMN `vendor_id` BIGINT UNSIGNED NULL COMMENT 'NULL = sold by the platform directly, not vendor-owned' AFTER `supplier_id`,
    ADD COLUMN `approval_status` ENUM('approved','pending','rejected') NOT NULL DEFAULT 'approved' COMMENT 'Platform-owned products are always approved; only vendor-submitted listings go through pending/rejected' AFTER `is_active`,
    ADD KEY `idx_products_vendor` (`vendor_id`),
    ADD CONSTRAINT `fk_products_vendor`
        FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL;

INSERT INTO `roles` (`id`, `name`, `slug`, `description`) VALUES
    (5, 'Vendor', 'vendor', 'Third-party seller managing their own product catalog and orders');

INSERT INTO `permissions` (`name`, `slug`, `module`) VALUES
    ('Manage Vendors', 'vendors.manage', 'vendors');

-- Vendor approval is a Super-Admin-only decision, same trust tier as
-- users.manage/roles.manage/settings.manage/audit_logs.view - it's
-- who gets to sell on the platform at all, not day-to-day operations.
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions` WHERE `slug` = 'vendors.manage';

INSERT INTO `settings` (`setting_key`, `value`, `group`) VALUES
    ('default_commission_rate', '15.00', 'marketplace');
