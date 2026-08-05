-- =====================================================================
-- Migration 0003 - add the expenses.manage permission
--
-- Module 9's dashboard read expenses.amount via a read-only Expense
-- model with no admin UI; Module 12 adds real expense CRUD, which
-- needs its own permission distinct from the existing 'reports.view'
-- (viewing reports and entering/editing financial expense records are
-- different levels of trust). Grants it to Super Admin (which already
-- gets every permission) and Manager (which already gets every
-- permission except users/roles/settings/audit_logs - the same
-- carve-out Module 1's seed used), matching how those two roles are
-- already defined rather than inventing a new rule.
--
-- Only needed if your `permissions` table was seeded before this
-- migration was added. A fresh import of kymera_collection.sql
-- already includes this permission - do not run this against a
-- database seeded from the current kymera_collection.sql, it will
-- fail with "duplicate entry" on the permission slug.
-- =====================================================================

USE `kymera_collection`;

INSERT INTO `permissions` (`name`, `slug`, `module`) VALUES
    ('Manage Expenses', 'expenses.manage', 'reports');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'expenses.manage'
WHERE r.`slug` IN ('super-admin', 'manager');
