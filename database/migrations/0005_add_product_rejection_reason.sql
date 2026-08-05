-- =====================================================================
-- Migration 0005 - product listing rejection reason
--
-- Module 17 lets an admin reject a vendor's product listing, mirroring
-- how Module 16 lets an admin reject a vendor application with a
-- reason (`vendors.rejection_reason`). Products needed the same
-- column - a vendor whose listing is rejected should see why, not
-- just that it happened.
--
-- Only needed if your database was seeded before this migration was
-- added. A fresh import of kymera_collection.sql already includes
-- this column.
-- =====================================================================

USE `kymera_collection`;

ALTER TABLE `products`
    ADD COLUMN `rejection_reason` VARCHAR(255) NULL AFTER `approval_status`;
