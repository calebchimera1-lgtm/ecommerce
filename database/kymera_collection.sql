-- =====================================================================
-- Kymera Collection - Luxury E-Commerce Platform
-- Full MySQL 8 database schema
--
-- Engine:    InnoDB (required for foreign keys + transactions)
-- Charset:   utf8mb4 / utf8mb4_unicode_ci (full Unicode incl. emoji)
--
-- Design notes:
--  - A single `users` table holds both customers and staff/admin
--    accounts. Which one a user is comes from `role_id` (FK to
--    `roles`), combined with the `roles` <-> `permissions` matrix.
--    This is the standard normalized RBAC pattern and satisfies the
--    "Admins" + "Role-Based Authentication" requirements without
--    duplicating an identical account table.
--  - Money columns use DECIMAL(12,2) - never FLOAT/DOUBLE - to avoid
--    floating point rounding errors in prices/totals.
--  - Every child table that references a parent uses an explicit FK
--    with an ON DELETE strategy chosen per relationship (CASCADE for
--    true ownership, RESTRICT/SET NULL where history must be kept).
--  - order_items/order_addresses store point-in-time snapshots (name,
--    sku, price, address) so historical orders stay accurate even if
--    the product, its price, or the customer's address changes later.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

CREATE DATABASE IF NOT EXISTS `kymera_collection`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `kymera_collection`;

-- =====================================================================
-- 1. RBAC: roles, permissions, role_permissions
-- =====================================================================

CREATE TABLE `roles` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(50)  NOT NULL,
    `slug`        VARCHAR(50)  NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB;

CREATE TABLE `permissions` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100) NOT NULL,
    `slug`        VARCHAR(100) NOT NULL,
    `module`      VARCHAR(50)  NOT NULL COMMENT 'e.g. products, orders, customers, reports',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_permissions_slug` (`slug`),
    KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB;

CREATE TABLE `role_permissions` (
    `role_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_role_permissions_role`
        FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_role_permissions_permission`
        FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 2. Users, addresses, auth support tables
-- =====================================================================

CREATE TABLE `users` (
    `id`                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid`                   CHAR(36)     NOT NULL,
    `role_id`                INT UNSIGNED NOT NULL,
    `first_name`             VARCHAR(100) NOT NULL,
    `last_name`              VARCHAR(100) NOT NULL,
    `email`                  VARCHAR(191) NOT NULL,
    `phone`                  VARCHAR(30)  NULL,
    `password_hash`          VARCHAR(255) NOT NULL,
    `avatar`                 VARCHAR(255) NULL,
    `status`                 ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
    `email_verified_at`      TIMESTAMP    NULL DEFAULT NULL,
    `email_verification_token` VARCHAR(100) NULL,
    `remember_token`         VARCHAR(100) NULL,
    `last_login_at`          TIMESTAMP    NULL DEFAULT NULL,
    `last_login_ip`          VARCHAR(45)  NULL,
    `created_at`             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`             TIMESTAMP    NULL DEFAULT NULL,
    UNIQUE KEY `uq_users_uuid` (`uuid`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role_id`),
    KEY `idx_users_status` (`status`),
    CONSTRAINT `fk_users_role`
        FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE `password_resets` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email`      VARCHAR(191) NOT NULL,
    `token`      VARCHAR(100) NOT NULL,
    `expires_at` TIMESTAMP    NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_password_resets_email` (`email`),
    UNIQUE KEY `uq_password_resets_token` (`token`)
) ENGINE=InnoDB;

CREATE TABLE `login_attempts` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `identifier`   VARCHAR(191) NOT NULL COMMENT 'email or IP address being rate limited',
    `ip_address`   VARCHAR(45)  NOT NULL,
    `successful`   TINYINT(1)   NOT NULL DEFAULT 0,
    `attempted_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_login_attempts_identifier` (`identifier`, `attempted_at`)
) ENGINE=InnoDB;

CREATE TABLE `user_addresses` (
    `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `type`             ENUM('billing','shipping') NOT NULL DEFAULT 'shipping',
    `full_name`        VARCHAR(150) NOT NULL,
    `phone`            VARCHAR(30)  NOT NULL,
    `address_line1`    VARCHAR(255) NOT NULL,
    `address_line2`    VARCHAR(255) NULL,
    `city`             VARCHAR(100) NOT NULL,
    `state`            VARCHAR(100) NULL,
    `postal_code`      VARCHAR(20)  NULL,
    `country`          VARCHAR(100) NOT NULL,
    `is_default`       TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_user_addresses_user` (`user_id`),
    CONSTRAINT `fk_user_addresses_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 3. Catalog: categories, brands, suppliers, products, images, variants
-- =====================================================================

CREATE TABLE `categories` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `parent_id`        INT UNSIGNED NULL,
    `name`             VARCHAR(150) NOT NULL,
    `slug`             VARCHAR(170) NOT NULL,
    `description`      TEXT NULL,
    `commission_rate`  DECIMAL(5,2) NULL COMMENT 'Percentage charged to vendors selling in this category; NULL = use the platform default_commission_rate setting',
    `image`            VARCHAR(255) NULL,
    `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`       INT          NOT NULL DEFAULT 0,
    `meta_title`       VARCHAR(191) NULL,
    `meta_description` VARCHAR(255) NULL,
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_categories_slug` (`slug`),
    KEY `idx_categories_parent` (`parent_id`),
    CONSTRAINT `fk_categories_parent`
        FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `brands` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`             VARCHAR(150) NOT NULL,
    `slug`             VARCHAR(170) NOT NULL,
    `logo`             VARCHAR(255) NULL,
    `description`      TEXT NULL,
    `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
    `meta_title`       VARCHAR(191) NULL,
    `meta_description` VARCHAR(255) NULL,
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_brands_slug` (`slug`)
) ENGINE=InnoDB;

CREATE TABLE `suppliers` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`           VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(150) NULL,
    `email`          VARCHAR(191) NULL,
    `phone`          VARCHAR(30)  NULL,
    `address`        VARCHAR(255) NULL,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================================
-- 2b. Marketplace: vendors (third-party sellers)
-- =====================================================================

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

CREATE TABLE `products` (
    `id`                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sku`               VARCHAR(64)  NOT NULL,
    `barcode`           VARCHAR(64)  NULL,
    `name`              VARCHAR(200) NOT NULL,
    `slug`              VARCHAR(220) NOT NULL,
    `category_id`       INT UNSIGNED NOT NULL,
    `brand_id`          INT UNSIGNED NULL,
    `supplier_id`       INT UNSIGNED NULL,
    `vendor_id`         BIGINT UNSIGNED NULL COMMENT 'NULL = sold by the platform directly, not vendor-owned',
    `short_description` VARCHAR(500) NULL,
    `description`       TEXT NULL,
    `specifications`    JSON NULL COMMENT 'Free-form key/value spec table, e.g. material, movement, fragrance notes',
    `price`             DECIMAL(12,2) NOT NULL,
    `sale_price`        DECIMAL(12,2) NULL,
    `cost_price`        DECIMAL(12,2) NULL COMMENT 'Wholesale/cost basis, used for profit reporting',
    `weight_grams`      INT UNSIGNED NULL,
    `stock_quantity`    INT NOT NULL DEFAULT 0,
    `stock_status`      ENUM('in_stock','out_of_stock','backorder') NOT NULL DEFAULT 'in_stock',
    `low_stock_threshold` INT UNSIGNED NOT NULL DEFAULT 5,
    `is_featured`       TINYINT(1)   NOT NULL DEFAULT 0,
    `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
    `approval_status`   ENUM('approved','pending','rejected') NOT NULL DEFAULT 'approved' COMMENT 'Platform-owned products are always approved; only vendor-submitted listings go through pending/rejected',
    `view_count`        INT UNSIGNED NOT NULL DEFAULT 0,
    `meta_title`        VARCHAR(191) NULL,
    `meta_description`  VARCHAR(255) NULL,
    `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        TIMESTAMP    NULL DEFAULT NULL,
    UNIQUE KEY `uq_products_sku` (`sku`),
    UNIQUE KEY `uq_products_slug` (`slug`),
    KEY `idx_products_category` (`category_id`),
    KEY `idx_products_brand` (`brand_id`),
    KEY `idx_products_supplier` (`supplier_id`),
    KEY `idx_products_vendor` (`vendor_id`),
    KEY `idx_products_active_featured` (`is_active`, `is_featured`),
    FULLTEXT KEY `ftx_products_name_description` (`name`, `short_description`, `description`),
    CONSTRAINT `fk_products_vendor`
        FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_products_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_products_brand`
        FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_products_supplier`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `product_images` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `alt_text`   VARCHAR(191) NULL,
    `is_primary` TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_product_images_product` (`product_id`),
    CONSTRAINT `fk_product_images_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Variant dimension (e.g. "Color" / "Red", "Size" / "42"). A product can
-- have any number of attribute rows; combinations are priced/stocked
-- independently via price_modifier + stock_quantity on each row.
CREATE TABLE `product_attributes` (
    `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id`       BIGINT UNSIGNED NOT NULL,
    `attribute_name`   VARCHAR(50)  NOT NULL COMMENT 'e.g. Color, Size',
    `attribute_value`  VARCHAR(100) NOT NULL COMMENT 'e.g. Black, EU 42',
    `price_modifier`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `stock_quantity`   INT NOT NULL DEFAULT 0,
    `sku_suffix`       VARCHAR(30) NULL,
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_product_attributes_product` (`product_id`),
    CONSTRAINT `fk_product_attributes_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `product_reviews` (
    `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id`     BIGINT UNSIGNED NOT NULL,
    `user_id`        BIGINT UNSIGNED NOT NULL,
    `order_item_id`  BIGINT UNSIGNED NULL COMMENT 'Ties review to a verified purchase when available',
    `rating`         TINYINT UNSIGNED NOT NULL COMMENT '1-5',
    `title`          VARCHAR(191) NULL,
    `comment`        TEXT NULL,
    `is_approved`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_product_reviews_product` (`product_id`),
    KEY `idx_product_reviews_user` (`user_id`),
    CONSTRAINT `chk_product_reviews_rating` CHECK (`rating` BETWEEN 1 AND 5),
    CONSTRAINT `fk_product_reviews_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_product_reviews_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `wishlists` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_wishlists_user_product` (`user_id`, `product_id`),
    CONSTRAINT `fk_wishlists_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wishlists_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 4. Cart
-- =====================================================================

CREATE TABLE `carts` (
    `id`                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`             BIGINT UNSIGNED NULL COMMENT 'NULL for guest carts identified by session_id',
    `session_id`          VARCHAR(100) NULL,
    `coupon_id`           BIGINT UNSIGNED NULL COMMENT 'Coupon applied at the cart stage, before checkout exists',
    `shipping_method_id`  INT UNSIGNED NULL COMMENT 'Shipping method chosen at the cart stage',
    `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_carts_user` (`user_id`),
    KEY `idx_carts_session` (`session_id`),
    CONSTRAINT `fk_carts_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_carts_coupon`
        FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_carts_shipping_method`
        FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `cart_items` (
    `id`                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cart_id`              BIGINT UNSIGNED NOT NULL,
    `product_id`           BIGINT UNSIGNED NOT NULL,
    `product_attribute_id` BIGINT UNSIGNED NULL,
    `quantity`             INT UNSIGNED NOT NULL DEFAULT 1,
    `price`                DECIMAL(12,2) NOT NULL COMMENT 'Unit price snapshot at time of adding to cart',
    `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_cart_items_cart` (`cart_id`),
    KEY `idx_cart_items_product` (`product_id`),
    CONSTRAINT `fk_cart_items_cart`
        FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cart_items_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cart_items_attribute`
        FOREIGN KEY (`product_attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 5. Coupons, shipping, tax
-- =====================================================================

CREATE TABLE `coupons` (
    `id`                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code`                VARCHAR(50)  NOT NULL,
    `type`                ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    `value`               DECIMAL(12,2) NOT NULL,
    `min_order_amount`    DECIMAL(12,2) NULL,
    `max_discount_amount` DECIMAL(12,2) NULL,
    `usage_limit`         INT UNSIGNED NULL COMMENT 'NULL = unlimited',
    `used_count`          INT UNSIGNED NOT NULL DEFAULT 0,
    `per_user_limit`      INT UNSIGNED NULL,
    `starts_at`           TIMESTAMP NULL,
    `expires_at`          TIMESTAMP NULL,
    `is_active`           TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_coupons_code` (`code`)
) ENGINE=InnoDB;

CREATE TABLE `coupon_usages` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `coupon_id`  BIGINT UNSIGNED NOT NULL,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `order_id`   BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_coupon_usages_coupon` (`coupon_id`),
    KEY `idx_coupon_usages_user` (`user_id`),
    CONSTRAINT `fk_coupon_usages_coupon`
        FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_coupon_usages_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `shipping_methods` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`            VARCHAR(100) NOT NULL,
    `description`     VARCHAR(255) NULL,
    `cost`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `estimated_days`  VARCHAR(30)  NULL COMMENT 'e.g. "3-5 business days"',
    `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `tax_rates` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100) NOT NULL,
    `rate`       DECIMAL(5,2) NOT NULL COMMENT 'Percentage, e.g. 16.00',
    `country`    VARCHAR(100) NOT NULL,
    `state`      VARCHAR(100) NULL,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================================
-- 6. Orders, order items, addresses, payments, tracking, returns
-- =====================================================================

CREATE TABLE `orders` (
    `id`                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_number`       VARCHAR(32)  NOT NULL COMMENT 'Human-facing reference, e.g. KYM-2026-000123',
    `user_id`            BIGINT UNSIGNED NOT NULL,
    `status`             ENUM('pending','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
    `subtotal`           DECIMAL(12,2) NOT NULL,
    `discount_amount`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `coupon_id`          BIGINT UNSIGNED NULL,
    `shipping_method_id` INT UNSIGNED NULL,
    `shipping_amount`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax_amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total`              DECIMAL(12,2) NOT NULL,
    `currency`           CHAR(3) NOT NULL DEFAULT 'USD',
    `payment_method`     VARCHAR(30) NOT NULL DEFAULT 'cod',
    `payment_status`     ENUM('unpaid','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
    `customer_notes`     VARCHAR(500) NULL,
    `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_orders_order_number` (`order_number`),
    KEY `idx_orders_user` (`user_id`),
    KEY `idx_orders_status` (`status`),
    KEY `idx_orders_created_at` (`created_at`),
    CONSTRAINT `fk_orders_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_coupon`
        FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_orders_shipping_method`
        FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Point-in-time snapshot of billing/shipping address for each order,
-- independent of the customer's saved address book.
CREATE TABLE `order_addresses` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id`      BIGINT UNSIGNED NOT NULL,
    `type`          ENUM('billing','shipping') NOT NULL,
    `full_name`     VARCHAR(150) NOT NULL,
    `phone`         VARCHAR(30)  NOT NULL,
    `address_line1` VARCHAR(255) NOT NULL,
    `address_line2` VARCHAR(255) NULL,
    `city`          VARCHAR(100) NOT NULL,
    `state`         VARCHAR(100) NULL,
    `postal_code`   VARCHAR(20)  NULL,
    `country`       VARCHAR(100) NOT NULL,
    UNIQUE KEY `uq_order_addresses_order_type` (`order_id`, `type`),
    CONSTRAINT `fk_order_addresses_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `order_items` (
    `id`                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id`             BIGINT UNSIGNED NOT NULL,
    `product_id`           BIGINT UNSIGNED NULL COMMENT 'Kept NULL-able so a deleted product does not erase order history',
    `product_attribute_id` BIGINT UNSIGNED NULL,
    `product_name`         VARCHAR(200) NOT NULL COMMENT 'Snapshot of product name at purchase time',
    `sku`                  VARCHAR(64)  NOT NULL,
    `price`                DECIMAL(12,2) NOT NULL COMMENT 'Unit price at purchase time',
    `quantity`             INT UNSIGNED NOT NULL,
    `subtotal`             DECIMAL(12,2) NOT NULL,
    `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_order_items_order` (`order_id`),
    KEY `idx_order_items_product` (`product_id`),
    CONSTRAINT `fk_order_items_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_items_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_order_items_attribute`
        FOREIGN KEY (`product_attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `order_status_history` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id`   BIGINT UNSIGNED NOT NULL,
    `status`     VARCHAR(30) NOT NULL,
    `note`       VARCHAR(255) NULL,
    `changed_by` BIGINT UNSIGNED NULL COMMENT 'Staff user_id, NULL for system-generated changes',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_order_status_history_order` (`order_id`),
    CONSTRAINT `fk_order_status_history_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_status_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Abstract payment record. `gateway` + `payload` let any future gateway
-- (Stripe, PayPal, M-Pesa, Visa/Mastercard via a processor) plug in
-- without schema changes - see app/Services/Payment for the interface.
CREATE TABLE `payments` (
    `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id`       BIGINT UNSIGNED NOT NULL,
    `gateway`        VARCHAR(30)  NOT NULL COMMENT 'stripe, paypal, mpesa, cod, ...',
    `transaction_id` VARCHAR(191) NULL,
    `amount`         DECIMAL(12,2) NOT NULL,
    `currency`       CHAR(3) NOT NULL DEFAULT 'USD',
    `status`         ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    `payload`        JSON NULL COMMENT 'Raw gateway response, for reconciliation/audit',
    `paid_at`        TIMESTAMP NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_payments_order` (`order_id`),
    KEY `idx_payments_gateway` (`gateway`),
    CONSTRAINT `fk_payments_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `shipments` (
    `id`                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id`            BIGINT UNSIGNED NOT NULL,
    `courier`             VARCHAR(100) NULL,
    `tracking_number`     VARCHAR(100) NULL,
    `label_url`           VARCHAR(255) NULL,
    `status`               ENUM('pending','in_transit','out_for_delivery','delivered','failed') NOT NULL DEFAULT 'pending',
    `estimated_delivery`  DATE NULL,
    `shipped_at`          TIMESTAMP NULL,
    `delivered_at`        TIMESTAMP NULL,
    `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_shipments_order` (`order_id`),
    CONSTRAINT `fk_shipments_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `product_returns` (
    `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_item_id`  BIGINT UNSIGNED NOT NULL,
    `user_id`        BIGINT UNSIGNED NOT NULL,
    `reason`         VARCHAR(255) NOT NULL,
    `status`         ENUM('requested','approved','rejected','refunded') NOT NULL DEFAULT 'requested',
    `refund_amount`  DECIMAL(12,2) NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_product_returns_order_item` (`order_item_id`),
    KEY `idx_product_returns_user` (`user_id`),
    CONSTRAINT `fk_product_returns_order_item`
        FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_product_returns_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 7. Inventory, purchasing, expenses
-- =====================================================================

CREATE TABLE `inventory_movements` (
    `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id`     BIGINT UNSIGNED NOT NULL,
    `type`           ENUM('in','out','adjustment','damaged','return') NOT NULL,
    `quantity`       INT NOT NULL COMMENT 'Positive for in, negative for out - signed delta applied to stock',
    `reference_type` VARCHAR(50) NULL COMMENT 'order, purchase_order, manual, return',
    `reference_id`   BIGINT UNSIGNED NULL,
    `note`           VARCHAR(255) NULL,
    `created_by`     BIGINT UNSIGNED NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_inventory_movements_product` (`product_id`),
    CONSTRAINT `fk_inventory_movements_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inventory_movements_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `purchase_orders` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `supplier_id`   INT UNSIGNED NOT NULL,
    `status`        ENUM('draft','ordered','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
    `total_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `ordered_at`    TIMESTAMP NULL,
    `expected_at`   DATE NULL,
    `received_at`   TIMESTAMP NULL,
    `created_by`    BIGINT UNSIGNED NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_purchase_orders_supplier` (`supplier_id`),
    CONSTRAINT `fk_purchase_orders_supplier`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_purchase_orders_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `purchase_order_items` (
    `id`                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `purchase_order_id` BIGINT UNSIGNED NOT NULL,
    `product_id`        BIGINT UNSIGNED NOT NULL,
    `quantity`          INT UNSIGNED NOT NULL,
    `unit_cost`         DECIMAL(12,2) NOT NULL,
    `received_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    KEY `idx_po_items_po` (`purchase_order_id`),
    KEY `idx_po_items_product` (`product_id`),
    CONSTRAINT `fk_po_items_po`
        FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_po_items_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `expenses` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category`     VARCHAR(100) NOT NULL,
    `description`  VARCHAR(255) NULL,
    `amount`       DECIMAL(12,2) NOT NULL,
    `expense_date` DATE NOT NULL,
    `created_by`   BIGINT UNSIGNED NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_expenses_date` (`expense_date`),
    CONSTRAINT `fk_expenses_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 8. Blog / content
-- =====================================================================

CREATE TABLE `blog_categories` (
    `id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL,
    UNIQUE KEY `uq_blog_categories_slug` (`slug`)
) ENGINE=InnoDB;

CREATE TABLE `blog_posts` (
    `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_id`      INT UNSIGNED NULL,
    `author_id`        BIGINT UNSIGNED NOT NULL,
    `title`            VARCHAR(200) NOT NULL,
    `slug`             VARCHAR(220) NOT NULL,
    `excerpt`          VARCHAR(500) NULL,
    `content`          LONGTEXT NOT NULL,
    `featured_image`   VARCHAR(255) NULL,
    `is_published`     TINYINT(1) NOT NULL DEFAULT 0,
    `published_at`     TIMESTAMP NULL,
    `meta_title`       VARCHAR(191) NULL,
    `meta_description` VARCHAR(255) NULL,
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_blog_posts_slug` (`slug`),
    KEY `idx_blog_posts_category` (`category_id`),
    CONSTRAINT `fk_blog_posts_category`
        FOREIGN KEY (`category_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_blog_posts_author`
        FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE `blog_comments` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `post_id`     BIGINT UNSIGNED NOT NULL,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `comment`     TEXT NOT NULL,
    `is_approved` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_blog_comments_post` (`post_id`),
    CONSTRAINT `fk_blog_comments_post`
        FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_blog_comments_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `testimonials` (
    `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_name`  VARCHAR(150) NOT NULL,
    `customer_photo` VARCHAR(255) NULL,
    `rating`         TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `message`        VARCHAR(500) NOT NULL,
    `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`     INT NOT NULL DEFAULT 0,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================================
-- 9. Settings, notifications, audit log, misc
-- =====================================================================

CREATE TABLE `settings` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL,
    `value`       TEXT NULL,
    `group`       VARCHAR(50)  NOT NULL DEFAULT 'general',
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB;

CREATE TABLE `notifications` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `type`       VARCHAR(50)  NOT NULL,
    `title`      VARCHAR(191) NOT NULL,
    `message`    VARCHAR(500) NULL,
    `data`       JSON NULL,
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_notifications_user` (`user_id`, `is_read`),
    CONSTRAINT `fk_notifications_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `audit_logs` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     BIGINT UNSIGNED NULL,
    `action`      VARCHAR(100) NOT NULL COMMENT 'e.g. product.updated, order.status_changed',
    `model`       VARCHAR(100) NULL,
    `model_id`    BIGINT UNSIGNED NULL,
    `old_values`  JSON NULL,
    `new_values`  JSON NULL,
    `ip_address`  VARCHAR(45) NULL,
    `user_agent`  VARCHAR(255) NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_audit_logs_user` (`user_id`),
    KEY `idx_audit_logs_model` (`model`, `model_id`),
    CONSTRAINT `fk_audit_logs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `newsletter_subscribers` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email`         VARCHAR(191) NOT NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `subscribed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_newsletter_email` (`email`)
) ENGINE=InnoDB;

CREATE TABLE `contact_messages` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(150) NOT NULL,
    `email`      VARCHAR(191) NOT NULL,
    `subject`    VARCHAR(191) NULL,
    `message`    TEXT NOT NULL,
    `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- 10. Seed data: roles, permissions, default admin, base settings
-- =====================================================================

INSERT INTO `roles` (`id`, `name`, `slug`, `description`) VALUES
    (1, 'Super Admin', 'super-admin', 'Full unrestricted access to every module'),
    (2, 'Manager',     'manager',     'Operational access: products, orders, inventory, reports'),
    (3, 'Support',     'support',     'Customer support access: orders, reviews, customers (read/write limited)'),
    (4, 'Customer',    'customer',    'Storefront shopper account'),
    (5, 'Vendor',      'vendor',      'Third-party seller managing their own product catalog and orders');

INSERT INTO `permissions` (`name`, `slug`, `module`) VALUES
    ('View Dashboard',        'dashboard.view',    'dashboard'),
    ('Manage Products',       'products.manage',   'products'),
    ('Manage Categories',     'categories.manage', 'categories'),
    ('Manage Brands',         'brands.manage',     'brands'),
    ('View Orders',           'orders.view',       'orders'),
    ('Manage Orders',         'orders.manage',     'orders'),
    ('Manage Customers',      'customers.manage',  'customers'),
    ('Manage Users',          'users.manage',      'users'),
    ('Manage Roles',          'roles.manage',      'roles'),
    ('Manage Reviews',        'reviews.manage',    'reviews'),
    ('Manage Coupons',        'coupons.manage',    'coupons'),
    ('Manage Inventory',      'inventory.manage',  'inventory'),
    ('Manage Suppliers',      'suppliers.manage',  'suppliers'),
    ('View Reports',          'reports.view',      'reports'),
    ('Manage Expenses',       'expenses.manage',   'reports'),
    ('Manage Blog',           'blog.manage',       'blog'),
    ('Manage Settings',       'settings.manage',   'settings'),
    ('View Audit Logs',       'audit_logs.view',   'audit_logs'),
    ('Manage Vendors',        'vendors.manage',    'vendors');

-- Super Admin gets every permission.
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Manager: everything except users/roles/settings/audit logs/vendors.
-- Vendor approval is a Super-Admin-only decision (same trust tier as
-- hiring staff), not day-to-day operations.
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions`
WHERE `slug` NOT IN ('users.manage', 'roles.manage', 'settings.manage', 'audit_logs.view', 'vendors.manage');

-- Support: dashboard, orders, customers, reviews only.
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions`
WHERE `slug` IN ('dashboard.view', 'orders.view', 'orders.manage', 'customers.manage', 'reviews.manage');

-- Default Super Admin account.
-- Email: admin@kymeracollection.com | Password: ChangeMe!123
-- Real Argon2id hash generated via PHP's password_hash() and verified
-- with password_verify() - CHANGE THIS PASSWORD IMMEDIATELY after first login.
INSERT INTO `users` (`uuid`, `role_id`, `first_name`, `last_name`, `email`, `password_hash`, `status`, `email_verified_at`)
VALUES (
    UUID(),
    1,
    'Kymera',
    'Admin',
    'admin@kymeracollection.com',
    '$argon2id$v=19$m=65536,t=4,p=1$cUt3TVZ3eVRIREM2VVJzRw$cTDwWbbzKuv6fHRTuUT2YC7MNqQ9MqrLdUWqg7nIIzo',
    'active',
    NOW()
);

INSERT INTO `shipping_methods` (`name`, `description`, `cost`, `estimated_days`) VALUES
    ('Standard Shipping', 'Reliable ground shipping', 9.99, '5-7 business days'),
    ('Express Shipping',  'Faster delivery', 24.99, '2-3 business days'),
    ('Next-Day Delivery', 'Order before 2pm for next-day delivery', 49.99, '1 business day'),
    ('In-Store Pickup',   'Collect from a Kymera Collection boutique', 0.00, 'Same day');

INSERT INTO `tax_rates` (`name`, `rate`, `country`, `state`, `is_active`) VALUES
    ('US Standard Sales Tax', 8.00, 'United States', NULL, 1),
    ('Kenya VAT',            16.00, 'Kenya',         NULL, 1),
    ('UK VAT',               20.00, 'United Kingdom', NULL, 1);

INSERT INTO `settings` (`setting_key`, `value`, `group`) VALUES
    ('site_name',        'Kymera Collection', 'general'),
    ('site_tagline',     'Luxury Redefined',  'general'),
    ('support_email',    'support@kymeracollection.com', 'general'),
    ('currency_default', 'USD', 'general'),
    ('free_shipping_threshold', '250.00', 'shipping'),
    ('default_commission_rate', '15.00', 'marketplace');

INSERT INTO `categories` (`name`, `slug`, `description`, `sort_order`) VALUES
    ('Fashion',     'fashion',     'Ready-to-wear apparel for every occasion', 1),
    ('Shoes',       'shoes',       'Designer footwear', 2),
    ('Watches',     'watches',     'Timepieces from renowned makers', 3),
    ('Perfumes',    'perfumes',    'Signature fragrances', 4),
    ('Jewelry',     'jewelry',     'Fine jewelry and statement pieces', 5),
    ('Bags',        'bags',        'Handbags, totes, and clutches', 6),
    ('Accessories', 'accessories', 'Belts, scarves, sunglasses, and more', 7);
