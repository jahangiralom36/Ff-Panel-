-- =====================================================================
-- FF PANEL SELL - DATABASE UPDATE (safe to run many times)
-- phpMyAdmin -> your database -> SQL tab -> paste all -> Go
-- Adds only missing tables / columns. Does NOT delete or change your data.
-- =====================================================================

-- ---------- helper: add a column only if it is missing ----------
DROP PROCEDURE IF EXISTS ff_add_col;
DELIMITER $$
CREATE PROCEDURE ff_add_col(IN tbl VARCHAR(64), IN col VARCHAR(64), IN def TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @ff_sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', def);
        PREPARE ff_stmt FROM @ff_sql;
        EXECUTE ff_stmt;
        DEALLOCATE PREPARE ff_stmt;
    END IF;
END$$
DELIMITER ;

-- ---------- product plan prices (customer + reseller) ----------
CALL ff_add_col('product_plans', 'user_price',                'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
CALL ff_add_col('product_plans', 'reseller_price',            'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
CALL ff_add_col('product_plans', 'regular_price',             'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
CALL ff_add_col('product_plans', 'discount_percent',          'DECIMAL(5,2) NOT NULL DEFAULT 0.00');
CALL ff_add_col('product_plans', 'reseller_discount_percent', 'DECIMAL(5,2) NOT NULL DEFAULT 0.00');
CALL ff_add_col('product_plans', 'is_out_of_stock',           'TINYINT(1) NOT NULL DEFAULT 0');

-- plans that never had a regular (original) customer price -> copy current price
UPDATE `product_plans` SET `regular_price` = `user_price` WHERE `regular_price` <= 0 AND `user_price` > 0;

-- ---------- product maintenance notice ----------
CALL ff_add_col('products', 'maintenance_message', 'TEXT NULL');

-- ---------- exact taka in transaction history ----------
CALL ff_add_col('transactions', 'amount_bdt', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00');

-- ---------- per-user custom prices (customer + reseller) ----------
CREATE TABLE IF NOT EXISTS `user_custom_prices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `plan_id` INT NOT NULL,
    `custom_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    UNIQUE KEY `idx_user_plan` (`user_id`, `plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- SkyPay payment logs + failed-try counter ----------
CREATE TABLE IF NOT EXISTS `skypay_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL DEFAULT 0,
    `trx_id` VARCHAR(120) NOT NULL,
    `typed_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `gateway_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL DEFAULT '',
    `raw_response` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`trx_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `trx_verify_attempts` (
    `trx_id` VARCHAR(120) NOT NULL PRIMARY KEY,
    `user_id` INT NOT NULL DEFAULT 0,
    `attempts` INT NOT NULL DEFAULT 0,
    `last_message` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS ff_add_col;
-- Done.
