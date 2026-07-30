-- =====================================================================
-- Migration 0001 - add coupon_id / shipping_method_id to carts
--
-- Only needed if your `carts` table was created before this migration
-- was added (i.e. you ran database/kymera_collection.sql before this
-- change landed in it). A fresh import of kymera_collection.sql
-- already includes these columns - do not run this against a fresh
-- database, it will fail with "duplicate column".
-- =====================================================================

USE `kymera_collection`;

ALTER TABLE `carts`
    ADD COLUMN `coupon_id` BIGINT UNSIGNED NULL COMMENT 'Coupon applied at the cart stage, before checkout exists' AFTER `session_id`,
    ADD COLUMN `shipping_method_id` INT UNSIGNED NULL COMMENT 'Shipping method chosen at the cart stage' AFTER `coupon_id`;

ALTER TABLE `carts`
    ADD CONSTRAINT `fk_carts_coupon`
        FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_carts_shipping_method`
        FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE SET NULL;
