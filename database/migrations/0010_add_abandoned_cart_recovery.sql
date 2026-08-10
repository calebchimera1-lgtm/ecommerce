-- =====================================================================
-- Migration 0010 - abandoned cart recovery emails
--
-- `reminder_sent_at` tracks the last time a recovery email went out
-- for a cart, so the (cron-invoked) sender never emails the same idle
-- spell twice. It's compared against `carts.updated_at`, not just
-- checked for NULL: if the customer touches the cart again after a
-- reminder (adding/removing an item bumps updated_at), the cart
-- becomes eligible for a fresh reminder once it goes idle again.
--
-- Only needed if your database was seeded before this migration was
-- added. A fresh import of kymera_collection.sql already includes
-- all of this.
-- =====================================================================

USE `kymera_collection`;

ALTER TABLE `carts`
    ADD COLUMN `reminder_sent_at` TIMESTAMP NULL DEFAULT NULL AFTER `shipping_method_id`;

INSERT INTO `settings` (`setting_key`, `value`, `group`) VALUES
    ('abandoned_cart_threshold_hours', '24', 'marketing');
