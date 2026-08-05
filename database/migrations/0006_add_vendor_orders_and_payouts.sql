-- =====================================================================
-- Migration 0006 - vendor order splitting, commission & payouts
--
-- The last piece of the marketplace extension (Modules 15-18). When a
-- customer places an order containing items from one or more vendors,
-- this splits it into per-vendor sub-orders: `vendor_orders` (one row
-- per distinct vendor present in an order), each carrying that
-- vendor's slice of the subtotal, the commission the platform takes
-- (at the category's effective rate - Module 15's schema, finally
-- used), and what's owed to the vendor. `order_items.vendor_order_id`
-- links each line item to its vendor sub-order; NULL means the item
-- is platform-owned and stays under the parent order's own
-- status/fulfillment exactly as before this migration.
--
-- Payouts stay manual per Module 15's decision - `vendor_orders.payout_status`
-- is a ledger admin marks as paid after sending a vendor their money
-- outside the app, not an automated payment.
--
-- Only needed if your database was seeded before this migration was
-- added. A fresh import of kymera_collection.sql already includes
-- all of this.
-- =====================================================================

USE `kymera_collection`;

CREATE TABLE `vendor_orders` (
    `id`                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id`          BIGINT UNSIGNED NOT NULL,
    `vendor_id`         BIGINT UNSIGNED NOT NULL,
    `status`            ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'This vendor''s own fulfillment status for their slice of the order - independent of the parent order''s status and of any other vendor''s slice',
    `subtotal`          DECIMAL(12,2) NOT NULL COMMENT 'Sum of this vendor''s order_items subtotal within this order',
    `commission_amount` DECIMAL(12,2) NOT NULL COMMENT 'Platform''s cut, summed from each item''s category commission rate',
    `payout_amount`     DECIMAL(12,2) NOT NULL COMMENT 'subtotal - commission_amount; what the platform owes this vendor for this order',
    `payout_status`     ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
    `paid_at`           TIMESTAMP NULL DEFAULT NULL,
    `paid_by`           BIGINT UNSIGNED NULL COMMENT 'Admin who recorded the manual payout',
    `payout_reference`  VARCHAR(191) NULL COMMENT 'Admin''s free-text note on how/where the payout was sent',
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_vendor_orders_order_vendor` (`order_id`, `vendor_id`),
    KEY `idx_vendor_orders_vendor` (`vendor_id`),
    KEY `idx_vendor_orders_payout_status` (`payout_status`),
    CONSTRAINT `fk_vendor_orders_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vendor_orders_vendor`
        FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_vendor_orders_paid_by`
        FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `vendor_order_status_history` (
    `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `vendor_order_id`  BIGINT UNSIGNED NOT NULL,
    `status`           VARCHAR(30) NOT NULL,
    `note`             VARCHAR(255) NULL,
    `changed_by`       BIGINT UNSIGNED NULL COMMENT 'Vendor or staff user_id, NULL for system-generated changes',
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_vendor_order_status_history_vendor_order` (`vendor_order_id`),
    CONSTRAINT `fk_vendor_order_status_history_vendor_order`
        FOREIGN KEY (`vendor_order_id`) REFERENCES `vendor_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vendor_order_status_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE `order_items`
    ADD COLUMN `vendor_order_id`  BIGINT UNSIGNED NULL AFTER `product_attribute_id`,
    ADD COLUMN `commission_rate`   DECIMAL(5,2) NULL AFTER `subtotal`,
    ADD COLUMN `commission_amount` DECIMAL(12,2) NULL AFTER `commission_rate`,
    ADD KEY `idx_order_items_vendor_order` (`vendor_order_id`),
    ADD CONSTRAINT `fk_order_items_vendor_order`
        FOREIGN KEY (`vendor_order_id`) REFERENCES `vendor_orders` (`id`) ON DELETE SET NULL;
