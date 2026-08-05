-- =====================================================================
-- Migration 0002 - add received_quantity to purchase_order_items
--
-- purchase_orders.status has always included 'partially_received', but
-- Module 1's purchase_order_items had no per-line column to track how
-- much of each line had actually arrived - making that status
-- unreachable in a meaningful way. This adds the column needed for
-- Module 11's receiving workflow to support real partial receipts.
--
-- Only needed if your `purchase_order_items` table was created before
-- this migration was added. A fresh import of kymera_collection.sql
-- already includes this column - do not run this against a fresh
-- database, it will fail with "duplicate column".
-- =====================================================================

USE `kymera_collection`;

ALTER TABLE `purchase_order_items`
    ADD COLUMN `received_quantity` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `unit_cost`;
