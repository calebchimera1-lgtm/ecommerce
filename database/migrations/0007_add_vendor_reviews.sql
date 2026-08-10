-- =====================================================================
-- Migration 0007 - vendor ratings & reviews
--
-- Lets a customer rate a vendor (not just a product) after actually
-- receiving something from them - mirrors product_reviews' shape
-- exactly (rating/title/comment/is_approved moderation queue), scoped
-- to vendors instead of products, with one added purchase gate:
-- reviewing requires at least one delivered vendor_orders row with
-- that vendor (checked in application code via
-- VendorOrder::hasDeliveredOrderForUser(), not enforceable as a
-- table constraint).
--
-- Only needed if your database was seeded before this migration was
-- added. A fresh import of kymera_collection.sql already includes
-- this table.
-- =====================================================================

USE `kymera_collection`;

CREATE TABLE `vendor_reviews` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `vendor_id`       BIGINT UNSIGNED NOT NULL,
    `user_id`         BIGINT UNSIGNED NOT NULL,
    `vendor_order_id` BIGINT UNSIGNED NULL COMMENT 'The delivered vendor sub-order that earned the right to review - kept for traceability, not re-checked after the fact',
    `rating`          TINYINT UNSIGNED NOT NULL COMMENT '1-5',
    `title`           VARCHAR(191) NULL,
    `comment`         TEXT NULL,
    `is_approved`     TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_vendor_reviews_vendor_user` (`vendor_id`, `user_id`),
    KEY `idx_vendor_reviews_vendor` (`vendor_id`),
    CONSTRAINT `chk_vendor_reviews_rating` CHECK (`rating` BETWEEN 1 AND 5),
    CONSTRAINT `fk_vendor_reviews_vendor`
        FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vendor_reviews_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vendor_reviews_vendor_order`
        FOREIGN KEY (`vendor_order_id`) REFERENCES `vendor_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;
