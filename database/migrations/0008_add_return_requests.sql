-- =====================================================================
-- Migration 0008 - returns/refunds (RMA) workflow
--
-- A customer requests a return on specific items from a delivered
-- order (or a delivered vendor sub-order - checked per item, since
-- Module 18 already lets different vendors within one order reach
-- 'delivered' at different times); admin approves/rejects, then marks
-- it refunded. Mirrors the order_status_history / vendor_order_status_history
-- pattern already established for status trails.
--
-- Only needed if your database was seeded before this migration was
-- added. A fresh import of kymera_collection.sql already includes
-- all of this.
-- =====================================================================

USE `kymera_collection`;

CREATE TABLE `return_requests` (
    `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id`         BIGINT UNSIGNED NOT NULL,
    `user_id`          BIGINT UNSIGNED NOT NULL COMMENT 'The customer who requested the return',
    `status`           ENUM('pending','approved','rejected','refunded') NOT NULL DEFAULT 'pending',
    `reason`           VARCHAR(255) NOT NULL COMMENT 'Customer-stated reason for the return',
    `admin_note`       VARCHAR(255) NULL COMMENT 'Set on approve/reject/refund - required when rejecting',
    `refunded_amount`  DECIMAL(12,2) NULL,
    `refunded_at`      TIMESTAMP NULL DEFAULT NULL,
    `processed_by`     BIGINT UNSIGNED NULL COMMENT 'Admin who last actioned this request',
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_return_requests_order` (`order_id`),
    KEY `idx_return_requests_user` (`user_id`),
    KEY `idx_return_requests_status` (`status`),
    CONSTRAINT `fk_return_requests_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_return_requests_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_return_requests_processed_by`
        FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `return_request_items` (
    `id`                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `return_request_id`  BIGINT UNSIGNED NOT NULL,
    `order_item_id`      BIGINT UNSIGNED NOT NULL,
    `quantity`           INT UNSIGNED NOT NULL COMMENT 'Units being returned - may be less than the original order_items.quantity',
    `reason`             VARCHAR(255) NULL COMMENT 'Optional per-item reason (defective, wrong item, etc.)',
    `restocked`          TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_return_request_items_request` (`return_request_id`),
    KEY `idx_return_request_items_order_item` (`order_item_id`),
    CONSTRAINT `fk_return_request_items_request`
        FOREIGN KEY (`return_request_id`) REFERENCES `return_requests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_return_request_items_order_item`
        FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `return_request_status_history` (
    `id`                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `return_request_id`  BIGINT UNSIGNED NOT NULL,
    `status`             VARCHAR(30) NOT NULL,
    `note`               VARCHAR(255) NULL,
    `changed_by`         BIGINT UNSIGNED NULL COMMENT 'Customer or staff user_id, NULL for system-generated changes',
    `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_return_request_status_history_request` (`return_request_id`),
    CONSTRAINT `fk_return_request_status_history_request`
        FOREIGN KEY (`return_request_id`) REFERENCES `return_requests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_return_request_status_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO `settings` (`setting_key`, `value`, `group`) VALUES
    ('return_window_days', '14', 'orders');
