-- =====================================================================
-- Migration 0011 - multi-currency display + state-level tax rates
--
-- `currencies` is a small, manually-maintained rate table (exchange
-- rates relative to 1 USD) used only for storefront display
-- conversion - every order, payment, refund, and payout in this app
-- is still placed, charged, and recorded in USD (`orders.total`,
-- `vendor_orders.payout_amount`, etc. are untouched). Rates need
-- periodic manual updates; this module deliberately does not call out
-- to a live FX API (see docs/MODULE_27_MULTI_CURRENCY_REGIONAL_TAX.md).
--
-- `tax_rates.state` has existed since Module 1 but was never used in
-- a lookup - TaxRate::forAddress() (replacing forCountry()) now
-- prefers a state-specific match before falling back to the
-- country-level rate. Two example US state rows are seeded so the
-- new lookup path has real data to exercise.
--
-- Only needed if your database was seeded before this migration was
-- added. A fresh import of kymera_collection.sql already includes
-- all of this.
-- =====================================================================

USE `kymera_collection`;

CREATE TABLE `currencies` (
    `code`            CHAR(3) PRIMARY KEY COMMENT 'ISO 4217 code, e.g. USD',
    `name`            VARCHAR(50) NOT NULL,
    `symbol`          VARCHAR(5) NOT NULL,
    `exchange_rate`   DECIMAL(12,6) NOT NULL COMMENT 'Units of this currency per 1 USD - manually maintained, not live',
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `is_default`      TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `currencies` (`code`, `name`, `symbol`, `exchange_rate`, `is_active`, `is_default`) VALUES
    ('USD', 'US Dollar',        '$',    1.000000, 1, 1),
    ('KES', 'Kenyan Shilling',  'KSh ', 129.500000, 1, 0),
    ('GBP', 'British Pound',    '£',    0.780000, 1, 0),
    ('EUR', 'Euro',             '€',    0.920000, 1, 0);

INSERT INTO `tax_rates` (`name`, `rate`, `country`, `state`, `is_active`) VALUES
    ('California Sales Tax', 8.75, 'United States', 'California', 1),
    ('New York Sales Tax',   8.00, 'United States', 'New York',   1);
