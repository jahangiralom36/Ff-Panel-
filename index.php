<?php
/**
 * ====================================================================
 * STOREFRONT & CLIENT CONSOLE — PRO HIGH SPEED EDITION (v23.5)
 * FEATURES:
 * - TURBO CACHED SCHEMA (PREVENTS SLOW PAGE LOAD)
 * - LIVE HISTORY (TOP BUYERS, RECENT ORDERS, FEEDBACKS) & REVIEW SYSTEM
 * - PRODUCT & SITE-WIDE MAINTENANCE MODES
 * - EXPANDED ACCOUNT DASHBOARD & DYNAMIC ANIMATIONS
 * - REAL GOOGLE OAUTH LOGIN (Requires setup in config.php)
 * - FIXED PRODUCT LOGO & ADDED DETAILS BOX IN MODAL
 * - NEW: WALLET, BDT, USDT CHECKOUT SYSTEM (GLITCH FIXED)
 * - NEW: MY KEY BUTTON FIX & ADVANCED RESET KEY SYSTEM
 * - NEW (v23.5): Clean banners, Payment Method Banners, Coins removed from UI
 * - NEW (v23.6): Fixed category order (NON ROOT, ROOT, I PHONE, PC, Other) + Product details collapsible box
 * ====================================================================
 */

// 1. HIGH-SPEED GZIP BUFFERING & OPTIMIZED COMPRESSION
if (!ob_start("ob_gzhandler")) {
    ob_start();
}

ini_set('display_errors', '0');
error_reporting(E_ALL);

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
// Always serve fresh page (admin price / plan changes show instantly, no stale browser/host cache)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("X-LiteSpeed-Cache-Control: no-cache");

require_once __DIR__ . '/config.php';

// --- GOOGLE OAUTH CONFIGURATION ---
// Add these to your config.php:
// define('GOOGLE_CLIENT_ID', 'your-client-id.apps.googleusercontent.com');
// define('GOOGLE_CLIENT_SECRET', 'your-client-secret');
// define('GOOGLE_REDIRECT_URI', SITE_URL . '/index.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Capture Referral Code (?ref=13183) into Session
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $_SESSION['auto_ref_code'] = preg_replace('/[^0-9]/', '', trim($_GET['ref']));
}

// Session Logout Handler
if (isset($_GET['logout']) || (isset($_GET['action']) && $_GET['action'] === 'logout')) {
    $_SESSION = [];
    if (!headers_sent()) setcookie('ff_auth', '', time() - 3600, '/');
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

// ====================================================================
// 2. ULTRA-FAST CACHED AUTO-HEALER (Runs schema check only once a day)
// ====================================================================
$schemaCheckLockFile = sys_get_temp_dir() . '/ff_schema_v235.lock';
if (!file_exists($schemaCheckLockFile) || (time() - filemtime($schemaCheckLockFile)) > 86400) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `system_settings` (
            `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
            `setting_value` TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `live_activity_stream` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_name` VARCHAR(100) NOT NULL,
            `action_type` VARCHAR(50) NOT NULL,
            `details` VARCHAR(255) NOT NULL,
            `amount_bdt` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_recent` (`created_at` DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `referrals` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `referrer_id` INT NOT NULL,
            `referred_user_id` INT NOT NULL,
            `reward_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `status` ENUM('credited', 'pending') DEFAULT 'credited',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_referrer` (`referrer_id`),
            INDEX `idx_referred` (`referred_user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `promo_codes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(50) NOT NULL UNIQUE,
            `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
            `max_uses` INT NOT NULL DEFAULT 100,
            `used_count` INT NOT NULL DEFAULT 0,
            `min_order_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `expires_at` DATETIME NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `payment_methods` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `method_key` VARCHAR(50) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `account_number` VARCHAR(100) NOT NULL,
            `account_type` VARCHAR(50) NOT NULL DEFAULT 'Personal',
            `logo_url` TEXT NULL,
            `banner_url` TEXT NULL,
            `qr_image_url` TEXT NULL,
            `instructions` TEXT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `allow_add_fund` TINYINT(1) NOT NULL DEFAULT 1,
            `allow_checkout` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `feedbacks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `rating` TINYINT NOT NULL DEFAULT 5,
            `comment` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(150) NOT NULL,
            `token` VARCHAR(100) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Schema Column Verification for users
        $userCols = $pdo->query("DESCRIBE `users`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('referral_code', $userCols)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `referral_code` VARCHAR(50) NULL, ADD INDEX (`referral_code`)");
        }
        if (!in_array('referred_by', $userCols)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `referred_by` INT NULL DEFAULT 0");
        }
        if (!in_array('referral_earnings', $userCols)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `referral_earnings` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        }
        if (!in_array('coins', $userCols)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `coins` INT NOT NULL DEFAULT 0");
        }
        if (!in_array('custom_bdt_rate', $userCols)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `custom_bdt_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        }
        if (!in_array('can_add_fund', $userCols)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `can_add_fund` TINYINT(1) NOT NULL DEFAULT 1");
        }
        if (!in_array('is_product_restricted', $userCols)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_product_restricted` TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('avatar_url', $userCols)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `avatar_url` VARCHAR(255) NULL");
        }
        if (!in_array('google_id', $userCols)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `google_id` VARCHAR(100) NULL");
        }

        // Schema Column Verification for products
        $prodCols = $pdo->query("DESCRIBE `products`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('is_deleted', $prodCols)) {
            $pdo->exec("ALTER TABLE `products` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('app_download_url', $prodCols)) {
            $pdo->exec("ALTER TABLE `products` ADD COLUMN `app_download_url` TEXT NULL");
        }
        if (!in_array('maintenance_message', $prodCols)) {
            $pdo->exec("ALTER TABLE `products` ADD COLUMN `maintenance_message` TEXT NULL");
        }

        @file_put_contents($schemaCheckLockFile, (string)time());
    } catch (Throwable $e) {}
}

// Helper: Record activity for Live Transactions stream
function recordLiveActivity(PDO $pdo, string $userName, string $actionType, string $details, float $amountBdt = 0.0): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO `live_activity_stream` (`user_name`, `action_type`, `details`, `amount_bdt`) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userName, $actionType, $details, $amountBdt]);
    } catch (Throwable $e) {}
}


// ====================================================================
// 2B. DIRECT-PAYMENT ORDER ENGINE (pending / late verify / admin approve)
//     Order details are saved BEFORE payment is confirmed, so key can be
//     delivered later (even if user left the page) and shown in a popup.
// ====================================================================
// If your admin panel ADDS the amount to the user's wallet when it approves
// a manual order, set this to true in config.php (define before require):
// the wallet credit is then netted out when the key is delivered.
if (!defined('FF_ADMIN_APPROVE_CREDITS_WALLET')) define('FF_ADMIN_APPROVE_CREDITS_WALLET', false);

function ffEnsureOrdersTable(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `pending_orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `plan_id` INT NOT NULL,
            `product_id` INT NOT NULL DEFAULT 0,
            `product_name` VARCHAR(255) NOT NULL,
            `quantity` INT NOT NULL DEFAULT 1,
            `method_key` VARCHAR(20) NOT NULL DEFAULT 'bdt',
            `method_name` VARCHAR(150) NOT NULL DEFAULT '',
            `trx_id` VARCHAR(120) NOT NULL,
            `sender_num` VARCHAR(100) NULL,
            `promo_code` VARCHAR(50) NULL,
            `promo_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `unit_price_usd` DECIMAL(12,4) NOT NULL DEFAULT 0,
            `total_usd` DECIMAL(12,4) NOT NULL DEFAULT 0,
            `paid_usd` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `total_bdt` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `reward_coins` INT NOT NULL DEFAULT 0,
            `source` ENUM('auto','manual') NOT NULL DEFAULT 'manual',
            `status` ENUM('waiting','processing','delivered','refunded','rejected') NOT NULL DEFAULT 'waiting',
            `keys_json` MEDIUMTEXT NULL,
            `result_message` VARCHAR(255) NULL,
            `seen` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `delivered_at` DATETIME NULL,
            UNIQUE KEY `uq_trx` (`trx_id`),
            INDEX `idx_user_status` (`user_id`, `status`, `seen`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Throwable $e) {}
    $done = true;
}

function ffCreateOrder(PDO $pdo, array $o): int {
    ffEnsureOrdersTable($pdo);
    $stmt = $pdo->prepare("INSERT INTO `pending_orders`
        (`user_id`,`plan_id`,`product_id`,`product_name`,`quantity`,`method_key`,`method_name`,`trx_id`,`sender_num`,`promo_code`,`promo_percent`,`unit_price_usd`,`total_usd`,`paid_usd`,`total_bdt`,`reward_coins`,`source`,`status`)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'waiting')");
    $stmt->execute([
        $o['user_id'], $o['plan_id'], $o['product_id'], $o['product_name'], $o['quantity'],
        $o['method_key'], $o['method_name'], $o['trx_id'], $o['sender_num'], $o['promo_code'] ?: null,
        $o['promo_percent'], $o['unit_price_usd'], $o['total_usd'], $o['paid_usd'], $o['total_bdt'],
        $o['reward_coins'], $o['source']
    ]);
    return (int)$pdo->lastInsertId();
}

// ====================================================================
// 2D. REFERRAL COINS (admin: Refer & Earn -> coin rules)
//     reward  rule = coins given to referrer per successful referral
//     convert rule = pack "N coins = T taka" users can cash into wallet
// ====================================================================
function ffEnsureRefCoinSchema(PDO $pdo): void {
    $lock = sys_get_temp_dir() . '/ff_schema_refcoin_v1.lock';
    if (file_exists($lock)) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `referral_coin_rules` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `rule_type` ENUM('reward','convert') NOT NULL DEFAULT 'reward',
            `title` VARCHAR(150) NOT NULL DEFAULT '',
            `coins` INT NOT NULL DEFAULT 0,
            `taka` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $refCols = $pdo->query("DESCRIBE `referrals`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('reward_coins', $refCols)) {
            $pdo->exec("ALTER TABLE `referrals` ADD COLUMN `reward_coins` INT NOT NULL DEFAULT 0");
        }
        $licCols = $pdo->query("DESCRIBE `licenses`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('status', $licCols)) {
            $pdo->exec("ALTER TABLE `licenses` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active'");
        }
        @file_put_contents($lock, (string)time());
    } catch (Throwable $e) {}
}
ffEnsureRefCoinSchema($pdo);

function ffRefCoinsPerReferral(PDO $pdo): int {
    try {
        return (int)($pdo->query("SELECT COALESCE(SUM(`coins`),0) FROM `referral_coin_rules` WHERE `rule_type` = 'reward' AND `is_active` = 1")->fetchColumn() ?: 0);
    } catch (Throwable $e) { return 0; }
}

// ====================================================================
// 2C. SPIN EARN ENGINE  (spend X BDT = 1 spin, optional referral spins,
//     prize = random BDT between admin min..max, credited to wallet)
// ====================================================================
function ffEnsureSpinTables(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `user_spin_wallet` (
            `user_id` INT NOT NULL PRIMARY KEY,
            `spins_available` INT NOT NULL DEFAULT 0,
            `spend_progress_bdt` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `ref_progress` INT NOT NULL DEFAULT 0,
            `total_earned` INT NOT NULL DEFAULT 0,
            `total_used` INT NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `user_spin_log` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `prize_bdt` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_day` (`user_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Throwable $e) {}
    $done = true;
}

function ffSpinConfig(array $settings): array {
    $min = max(1, (int)($settings['spin_prize_min'] ?? 1));
    $max = max($min, (int)($settings['spin_prize_max'] ?? 50));
    return [
        'enabled'    => (($settings['spin_wheel_enabled'] ?? '1') === '1'),
        'per_spin'   => max(1.0, (float)($settings['spin_spend_per_spin'] ?? 500)),
        'min'        => $min,
        'max'        => $max,
        'daily'      => max(1, (int)($settings['spin_daily_limit'] ?? 10)),
        'ref_on'     => (($settings['spin_refer_enabled'] ?? '0') === '1'),
        'ref_per'    => max(1, (int)($settings['spin_refer_per_spin'] ?? 1)),
    ];
}

// Call AFTER a purchase is committed (wallet / direct payment). Coins purchases do not count.
function ffSpinAddSpend(PDO $pdo, array $settings, int $userId, float $spentBdt): void {
    $cfg = ffSpinConfig($settings);
    if (!$cfg['enabled'] || $userId <= 0 || $spentBdt <= 0) return;
    try {
        ffEnsureSpinTables($pdo);
        $pdo->prepare("INSERT IGNORE INTO `user_spin_wallet` (`user_id`) VALUES (?)")->execute([$userId]);
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT `spend_progress_bdt` FROM `user_spin_wallet` WHERE `user_id` = ? FOR UPDATE");
        $st->execute([$userId]);
        $progress = (float)$st->fetchColumn() + $spentBdt;
        $earned   = (int)floor($progress / $cfg['per_spin']);
        $progress = $progress - ($earned * $cfg['per_spin']);
        $pdo->prepare("UPDATE `user_spin_wallet` SET `spend_progress_bdt` = ?, `spins_available` = `spins_available` + ?, `total_earned` = `total_earned` + ? WHERE `user_id` = ?")
            ->execute([round($progress, 2), $earned, $earned, $userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $e2) {}
    }
}

// Call when a referred user registers (only works if admin enabled referral spins)
function ffSpinAddReferral(PDO $pdo, array $settings, int $referrerId): void {
    $cfg = ffSpinConfig($settings);
    if (!$cfg['enabled'] || !$cfg['ref_on'] || $referrerId <= 0) return;
    try {
        ffEnsureSpinTables($pdo);
        $pdo->prepare("INSERT IGNORE INTO `user_spin_wallet` (`user_id`) VALUES (?)")->execute([$referrerId]);
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT `ref_progress` FROM `user_spin_wallet` WHERE `user_id` = ? FOR UPDATE");
        $st->execute([$referrerId]);
        $prog   = (int)$st->fetchColumn() + 1;
        $earned = intdiv($prog, $cfg['ref_per']);
        $prog   = $prog - ($earned * $cfg['ref_per']);
        $pdo->prepare("UPDATE `user_spin_wallet` SET `ref_progress` = ?, `spins_available` = `spins_available` + ?, `total_earned` = `total_earned` + ? WHERE `user_id` = ?")
            ->execute([$prog, $earned, $earned, $referrerId]);
        $pdo->commit();
    } catch (Throwable $e) {
        try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $e2) {}
    }
}

// Wheel slices shown to user (labels spread from min to max)
function ffSpinWheelLabels(array $cfg): array {
    $vals = [];
    $n = 8;
    for ($i = 0; $i < $n; $i++) {
        $vals[] = (int)round($cfg['min'] + (($cfg['max'] - $cfg['min']) * $i / ($n - 1)));
    }
    $vals = array_values(array_unique($vals));
    while (count($vals) < 4) { $vals = array_merge($vals, $vals); }
    $colors = ['#16a34a', '#15803d', '#22c55e', '#166534'];
    $out = [];
    foreach ($vals as $i => $v) {
        $out[] = ['id' => $i + 1, 'title' => '৳' . $v, 'bonus_amount' => $v, 'color' => $colors[$i % 4]];
    }
    return $out;
}

// Delivers keys for a saved order. Safe against double delivery (row is claimed first).
// Out of stock / no key  => money goes to user's wallet automatically.
function ffDeliverOrder(PDO $pdo, int $orderId): array {
    ffEnsureOrdersTable($pdo);

    $claim = $pdo->prepare("UPDATE `pending_orders` SET `status` = 'processing' WHERE `id` = ? AND `status` = 'waiting'");
    $claim->execute([$orderId]);
    if ($claim->rowCount() !== 1) {
        return ['status' => 'skipped', 'message' => 'Order already processed.'];
    }

    $stmtO = $pdo->prepare("SELECT * FROM `pending_orders` WHERE `id` = ? LIMIT 1");
    $stmtO->execute([$orderId]);
    $o = $stmtO->fetch(PDO::FETCH_ASSOC);

    $revert = function () use ($pdo, $orderId) {
        try { $pdo->prepare("UPDATE `pending_orders` SET `status` = 'waiting' WHERE `id` = ? AND `status` = 'processing'")->execute([$orderId]); } catch (Throwable $e) {}
    };

    if (!$o) return ['status' => 'error', 'message' => 'Order not found.'];

    $qty      = max(1, (int)$o['quantity']);
    $planId   = (int)$o['plan_id'];
    $userId   = (int)$o['user_id'];
    $isAuto   = ($o['source'] === 'auto');
    $payLabel = ($o['method_key'] === 'usdt') ? 'usdt' : 'bdt';

    try {
        $stmtPl = $pdo->prepare("SELECT `api_plan_id` FROM `product_plans` WHERE `id` = ? LIMIT 1");
        $stmtPl->execute([$planId]);
        $apiPlanId = (int)$stmtPl->fetchColumn();

        $keys  = [];
        $inTxn = false;

        // 1) Stock keys (locked inside transaction)
        $pdo->beginTransaction();
        $inTxn = true;
        $stmtStock = $pdo->prepare("SELECT `id`, `license_key` FROM `stock_keys` WHERE `plan_id` = ? AND `status` = 'available' ORDER BY `id` ASC LIMIT $qty FOR UPDATE");
        $stmtStock->execute([$planId]);
        $stockRows = $stmtStock->fetchAll(PDO::FETCH_ASSOC);

        if (count($stockRows) === $qty) {
            $inClause = implode(',', array_map('intval', array_column($stockRows, 'id')));
            $pdo->exec("UPDATE `stock_keys` SET `status` = 'used' WHERE `id` IN ($inClause)");
            $keys = array_column($stockRows, 'license_key');
        } else {
            // 2) External provider / key manager (outside lock)
            $pdo->rollBack();
            $inTxn = false;
            $targetApiId = $apiPlanId > 0 ? $apiPlanId : $planId;
            $apiResult   = generateFromExternalProvider($targetApiId, $qty);
            if (is_array($apiResult) && ($apiResult['status'] ?? '') === 'success' && !empty($apiResult['keys'])) {
                $keys = array_values((array)$apiResult['keys']);
            }
            $pdo->beginTransaction();
            $inTxn = true;
        }

        // 3) NO KEY AVAILABLE => refund to wallet
        if (empty($keys)) {
            $creditUsd = FF_ADMIN_APPROVE_CREDITS_WALLET && !$isAuto ? 0.00 : (float)$o['paid_usd'];
            if ($creditUsd > 0) {
                $pdo->prepare("UPDATE `users` SET `balance` = `balance` + ? WHERE `id` = ?")->execute([$creditUsd, $userId]);
                $refundTrx = $isAuto ? $o['trx_id'] : ('RFD-' . $o['trx_id']);
                $pdo->prepare("INSERT INTO `transactions` (`user_id`, `method`, `amount`, `bonus_amount`, `sender_num`, `trx_id`, `status`) VALUES (?, ?, ?, 0.00, ?, ?, 'approved')")
                    ->execute([$userId, $o['method_name'] . ' (Refund Out of Stock)', $creditUsd, $o['sender_num'] ?: 'Trx Verified', $refundTrx]);
            }
            $msg = 'Product is currently Out of Stock. ৳' . number_format((float)$o['total_bdt'], 0) . ' BDT has been credited to your Main Wallet.';
            $pdo->prepare("UPDATE `pending_orders` SET `status` = 'refunded', `result_message` = ?, `seen` = 0, `delivered_at` = NOW() WHERE `id` = ?")->execute([$msg, $orderId]);
            $pdo->commit();
            return ['status' => 'out_of_stock_refunded', 'message' => $msg];
        }

        // 4) DELIVER
        if ($isAuto) {
            // auto-verified direct payment: record the order transaction now
            $pdo->prepare("INSERT INTO `transactions` (`user_id`, `method`, `amount`, `bonus_amount`, `sender_num`, `trx_id`, `status`) VALUES (?, ?, ?, 0.00, ?, ?, 'approved')")
                ->execute([$userId, 'Direct Order: ' . $o['method_name'], (float)$o['paid_usd'], $o['sender_num'] ?: 'N/A', $o['trx_id']]);
            ffSetLastTrxBdt($pdo, (float)$o['total_bdt']);
        } elseif (FF_ADMIN_APPROVE_CREDITS_WALLET) {
            // admin approval credited the wallet -> net it out (only if the credit is really there)
            $pdo->prepare("UPDATE `users` SET `balance` = `balance` - ? WHERE `id` = ? AND `balance` >= ?")
                ->execute([(float)$o['paid_usd'], $userId, (float)$o['paid_usd']]);
        }

        if ((int)$o['reward_coins'] > 0) {
            $pdo->prepare("UPDATE `users` SET `coins` = `coins` + ? WHERE `id` = ?")->execute([(int)$o['reward_coins'], $userId]);
        }

        $stmtIns = $pdo->prepare("INSERT INTO `licenses` (`user_id`, `product_id`, `plan_id`, `license_key`, `validity`, `price`, `source`, `payment_type`, `promo_code`, `discount_applied`) VALUES (?, ?, ?, ?, ?, ?, 'MAIN', ?, ?, ?)");
        foreach ($keys as $k) {
            $stmtIns->execute([$userId, (int)$o['product_id'], $planId, (string)$k, $o['product_name'], (float)$o['unit_price_usd'], $payLabel, $o['promo_code'] ?: null, (float)$o['promo_percent']]);
        }

        $pdo->prepare("UPDATE `pending_orders` SET `status` = 'delivered', `keys_json` = ?, `result_message` = 'Payment verified & License delivered!', `seen` = 0, `delivered_at` = NOW() WHERE `id` = ?")
            ->execute([json_encode($keys, JSON_UNESCAPED_UNICODE), $orderId]);
        $pdo->commit();

        try {
            $stmtU = $pdo->prepare("SELECT `username` FROM `users` WHERE `id` = ? LIMIT 1");
            $stmtU->execute([$userId]);
            recordLiveActivity($pdo, (string)$stmtU->fetchColumn(), 'purchase', 'Purchased ' . $o['product_name'], (float)$o['total_bdt']);
        } catch (Throwable $e) {}

        ffSpinAddSpend($pdo, $GLOBALS['settings'] ?? [], $userId, (float)$o['total_bdt']);

        return [
            'status'       => 'success',
            'message'      => 'Payment verified & License delivered!',
            'keys'         => $keys,
            'product_name' => $o['product_name'],
            'payment_type' => $payLabel
        ];
    } catch (Throwable $e) {
        try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $e2) {}
        $revert();
        return ['status' => 'error', 'message' => 'Delivery failed: ' . $e->getMessage()];
    }
}

// ====================================================================
// 3. SYSTEM SETTINGS & DYNAMIC THEME
// ====================================================================
$defaultSupportLink = defined('SUPPORT_LINK') ? SUPPORT_LINK : 'https://t.me/MotherPanelsBoT';

$settings = [
    'site_name'                    => defined('SITE_NAME') ? SITE_NAME : 'FF PANEL SELL',
    'site_logo'                    => '',
    'site_tagline'                 => 'Auto Delivery in 0.1 Seconds',
    'theme_color'                  => '#15803d',
    'top_ticker_notice'            => 'অর্ডার করার ০০.১ সেকেন্ডেই অটো ডেলিভারি পেয়ে যাবেন!',
    'usdt_rate'                    => '125',
    'binance_market_rate'          => '125',
    'payment_banner_url'           => '',
    'app_download_url'             => '',
    'support_whatsapp'             => '',
    'support_telegram'             => '',
    'support_facebook'             => '',
    'support_channel'              => '',
    'auto_payment_on'              => '0',
    'skypay_base_url'              => 'https://core.skypaybd.top',
    'skypay_brand_key'             => '',
    'skypay_auto_verify'           => '0',
    'deposit_system_status'        => '1',
    'site_maintenance_mode'        => '0',
    'site_maintenance_msg'         => 'We are currently upgrading our systems. Please check back later.',
    'referral_reward_amount'       => '5.00',
    'referral_bdt_enabled'         => '1',
    'referral_coin_enabled'        => '1',
    'spin_wheel_enabled'           => '1',
    'spin_daily_limit'             => '10',
    'spin_offer_duration_hours'    => '24',
    'spin_spend_per_spin'          => '500',
    'spin_prize_min'               => '1',
    'spin_prize_max'               => '50',
    'spin_refer_enabled'           => '0',
    'spin_refer_per_spin'          => '1',
];

try {
    $dbSettings = $pdo->query("SELECT `setting_key`, `setting_value` FROM `system_settings`")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($dbSettings)) {
        $settings = array_merge($settings, $dbSettings);
    }
} catch (Throwable $e) {}

$primaryThemeColor     = !empty($settings['theme_color']) ? trim($settings['theme_color']) : '#15803d';
$isSpinWheelActive     = ($settings['spin_wheel_enabled'] ?? '1') === '1';
$isDepositOnline       = ($settings['deposit_system_status'] ?? '1') === '1';
$isSiteMaintenance     = ($settings['site_maintenance_mode'] ?? '0') === '1';
$siteMaintenanceMsg    = $settings['site_maintenance_msg'] ?? 'We are currently upgrading our systems. Please check back later.';
$storeTitleName        = !empty($settings['site_name']) ? $settings['site_name'] : 'FF PANEL SELL';
$storeLogoUrl          = !empty($settings['site_logo']) ? $settings['site_logo'] : '';
$adminRate             = max(1.0, (float)$settings['usdt_rate']);
$referralBonusAmount   = max(0.0, (float)($settings['referral_reward_amount'] ?? 5.00));
// Admin can switch BDT referral bonus OFF and keep ONLY the coin system ON (or both / neither)
$referralBdtOn         = (($settings['referral_bdt_enabled'] ?? '1') === '1') && $referralBonusAmount > 0;
$referralCoinOn        = (($settings['referral_coin_enabled'] ?? '1') === '1');
$effectiveTutorialLink = !empty($settings['support_telegram']) ? $settings['support_telegram'] : $defaultSupportLink;

// Check Active Support Channels
$activeSupportChannels = [];
if (!empty($settings['support_whatsapp'])) {
    $waNumber = preg_replace('/[^0-9]/', '', $settings['support_whatsapp']);
    $activeSupportChannels[] = [
        'name'  => 'WhatsApp Support',
        'icon'  => 'fa-brands fa-whatsapp text-emerald-500',
        'sub'   => 'Live Chat & Assistance',
        'url'   => 'https://wa.me/' . $waNumber
    ];
}
if (!empty($settings['support_telegram'])) {
    $activeSupportChannels[] = [
        'name'  => 'Telegram Support',
        'icon'  => 'fa-brands fa-telegram text-blue-500',
        'sub'   => 'Official Support Admin',
        'url'   => $settings['support_telegram']
    ];
}
if (!empty($settings['support_facebook'])) {
    $activeSupportChannels[] = [
        'name'  => 'Facebook Page',
        'icon'  => 'fa-brands fa-facebook-messenger text-blue-600',
        'sub'   => 'Official Page Inbox',
        'url'   => $settings['support_facebook']
    ];
}
if (!empty($settings['support_channel'])) {
    $activeSupportChannels[] = [
        'name'  => 'Community Channel',
        'icon'  => 'fa-solid fa-bullhorn text-amber-500',
        'sub'   => 'Updates & Giveaways',
        'url'   => $settings['support_channel']
    ];
}

// ====================================================================
// EXACT TAKA (BDT) RECORD FOR TRANSACTIONS (history shows the real paid taka)
// ====================================================================
function ffEnsureTrxBdtColumn(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $lock = sys_get_temp_dir() . '/ff_schema_trx_bdt_v1.lock';
    if (file_exists($lock)) return;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `transactions`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('amount_bdt', $cols, true)) {
            $pdo->exec("ALTER TABLE `transactions` ADD COLUMN `amount_bdt` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
        }
        @file_put_contents($lock, date('c'));
    } catch (Throwable $e) {}
}

// call right after an INSERT INTO `transactions`
function ffSetLastTrxBdt(PDO $pdo, float $bdt): void {
    try {
        $id = (int)$pdo->lastInsertId();
        if ($id > 0 && $bdt > 0) {
            $pdo->prepare("UPDATE `transactions` SET `amount_bdt` = ? WHERE `id` = ?")->execute([round($bdt, 2), $id]);
        }
    } catch (Throwable $e) {}
}

// ====================================================================
// SKYPAY RAW LOG (to check exactly what the gateway returned)
// ====================================================================
function ffSkypayLog(PDO $pdo, int $userId, string $trxId, float $typedBdt, array $res): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `skypay_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL DEFAULT 0,
            `trx_id` VARCHAR(120) NOT NULL,
            `typed_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `gateway_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT '',
            `raw_response` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`trx_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $pdo->prepare("INSERT INTO `skypay_logs` (`user_id`,`trx_id`,`typed_amount`,`gateway_amount`,`status`,`raw_response`) VALUES (?,?,?,?,?,?)")
            ->execute([$userId, strtoupper($trxId), $typedBdt, (float)($res['amount'] ?? 0), (string)($res['status'] ?? ''), mb_substr((string)($res['raw'] ?? ''), 0, 60000)]);
    } catch (Throwable $e) {}
}

// ====================================================================
// REMEMBER LOGIN (signed cookie, keeps user logged in for many hours)
// ====================================================================
if (!defined('FF_REMEMBER_HOURS')) define('FF_REMEMBER_HOURS', 24);

function ffRememberSecret(PDO $pdo): string {
    static $sec = null;
    if ($sec !== null) return $sec;
    try {
        $st = $pdo->prepare("SELECT `setting_value` FROM `system_settings` WHERE `setting_key` = 'remember_secret' LIMIT 1");
        $st->execute();
        $v = (string)$st->fetchColumn();
        if (strlen($v) < 32) {
            $v = bin2hex(random_bytes(32));
            $pdo->prepare("INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('remember_secret', ?) ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`")->execute([$v]);
            $st->execute();
            $saved = (string)$st->fetchColumn();
            if (strlen($saved) >= 32) $v = $saved;
        }
        $sec = $v;
    } catch (Throwable $e) {
        $sec = hash('sha256', __FILE__ . (defined('DB_PASS') ? DB_PASS : '') . (defined('DB_NAME') ? DB_NAME : ''));
    }
    return $sec;
}

function ffRememberSign(PDO $pdo, int $uid, int $exp, string $passHash): string {
    return hash_hmac('sha256', $uid . '|' . $exp . '|' . $passHash, ffRememberSecret($pdo));
}

function ffRememberSet(PDO $pdo, array $u): void {
    if (headers_sent()) return;
    $uid = (int)$u['id'];
    $exp = time() + FF_REMEMBER_HOURS * 3600;
    $val = $uid . '.' . $exp . '.' . ffRememberSign($pdo, $uid, $exp, (string)($u['password'] ?? ''));
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie('ff_auth', $val, ['expires' => $exp, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE['ff_auth'] = $val;
}

function ffRememberClear(): void {
    if (!headers_sent()) setcookie('ff_auth', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    unset($_COOKIE['ff_auth']);
}

// returns user id from a valid remember cookie, or 0
function ffRememberCheck(PDO $pdo): int {
    $c = (string)($_COOKIE['ff_auth'] ?? '');
    if (!preg_match('/^(\d+)\.(\d+)\.([a-f0-9]{64})$/', $c, $m)) return 0;
    $uid = (int)$m[1]; $exp = (int)$m[2];
    if ($uid <= 0 || $exp < time()) return 0;
    try {
        $st = $pdo->prepare("SELECT `password`, `status` FROM `users` WHERE `id` = ? LIMIT 1");
        $st->execute([$uid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || ($row['status'] ?? 'active') !== 'active') return 0;
        if (!hash_equals(ffRememberSign($pdo, $uid, $exp, (string)$row['password']), $m[3])) return 0;
        return $uid;
    } catch (Throwable $e) {
        return 0;
    }
}

// ====================================================================
// CUSTOMER PLAN PRICE: admin edits always apply. When a plan discount is on,
// user_price already holds the discounted price, so start from regular_price
// (the discount is applied once, later).
// ====================================================================
function ffCustomerBasePrice(array $pl): float {
    $user = (float)($pl['user_price'] ?? 0);
    $reg  = (float)($pl['regular_price'] ?? 0);
    $disc = (float)($pl['discount_percent'] ?? 0);
    return ($disc > 0 && $reg > 0) ? $reg : $user;
}

// ====================================================================
// TRX GUARD: rejected / already-used TrxID block + failed-verify attempt counter
// ====================================================================
if (!defined('FF_TRX_MAX_AUTO_TRIES')) define('FF_TRX_MAX_AUTO_TRIES', 2); // after 2 failed tries -> manual admin request

function ffTrxBlockedMessage(PDO $pdo, string $trxId): string {
    $t = strtoupper(trim($trxId));
    if ($t === '') return '';
    try {
        $st = $pdo->prepare("SELECT LOWER(`status`) FROM `transactions` WHERE UPPER(`trx_id`) IN (?, ?) ORDER BY `id` ASC");
        $st->execute([$t, 'RFD-' . $t]);
        $statuses = $st->fetchAll(PDO::FETCH_COLUMN);
        if ($statuses) {
            if (array_intersect($statuses, ['rejected', 'declined', 'cancelled', 'canceled', 'failed'])) {
                return 'This TrxID was rejected by admin and cannot be used again!';
            }
            if (array_intersect($statuses, ['pending', 'waiting'])) {
                return 'This TrxID is already submitted and waiting for admin approval!';
            }
            return 'This TrxID has already been used! Fund/Order already completed with this TrxID.';
        }
    } catch (Throwable $e) {}
    try {
        ffEnsureOrdersTable($pdo);
        $st = $pdo->prepare("SELECT LOWER(`status`) FROM `pending_orders` WHERE UPPER(`trx_id`) = ? LIMIT 1");
        $st->execute([$t]);
        $os = $st->fetchColumn();
        if ($os !== false) {
            if ($os === 'rejected') return 'This TrxID was rejected by admin and cannot be used again!';
            if (in_array($os, ['waiting', 'processing'], true)) return 'This TrxID is already submitted and waiting for approval!';
            return 'This TrxID has already been used! Fund/Order already completed with this TrxID.';
        }
    } catch (Throwable $e) {}
    return '';
}

function ffEnsureTrxAttemptsTable(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `trx_verify_attempts` (
            `trx_id` VARCHAR(120) NOT NULL PRIMARY KEY,
            `user_id` INT NOT NULL DEFAULT 0,
            `attempts` INT NOT NULL DEFAULT 0,
            `last_message` VARCHAR(255) NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Throwable $e) {}
    $done = true;
}

// Records one failed gateway verify for this TrxID and returns the total failed tries
function ffTrxAttemptFail(PDO $pdo, string $trxId, int $userId, string $msg): int {
    ffEnsureTrxAttemptsTable($pdo);
    $t = strtoupper(trim($trxId));
    try {
        $pdo->prepare("INSERT INTO `trx_verify_attempts` (`trx_id`, `user_id`, `attempts`, `last_message`) VALUES (?, ?, 1, ?)
                       ON DUPLICATE KEY UPDATE `attempts` = `attempts` + 1, `user_id` = VALUES(`user_id`), `last_message` = VALUES(`last_message`)")
            ->execute([$t, $userId, mb_substr($msg, 0, 250)]);
        $st = $pdo->prepare("SELECT `attempts` FROM `trx_verify_attempts` WHERE `trx_id` = ? LIMIT 1");
        $st->execute([$t]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 1;
    }
}

function ffTrxAttemptClear(PDO $pdo, string $trxId): void {
    try {
        ffEnsureTrxAttemptsTable($pdo);
        $pdo->prepare("DELETE FROM `trx_verify_attempts` WHERE `trx_id` = ?")->execute([strtoupper(trim($trxId))]);
    } catch (Throwable $e) {}
}

// ====================================================================
// 4. GATEWAY & EXTERNAL PROVIDER ENGINE
// ====================================================================
// ====================================================================
// SKYPAY BD (Headless API v2) — create session + verify by TrxID / Order ID
// Docs: https://github.com/SkyPayBD/Docs  (BRAND-KEY header)
// ====================================================================
if (!defined('SKYPAY_DEFAULT_BRAND_KEY')) define('SKYPAY_DEFAULT_BRAND_KEY', '9ymnjszyhG5awJbTgp');
if (!defined('SKYPAY_DEFAULT_BASE_URL'))  define('SKYPAY_DEFAULT_BASE_URL', 'https://core.skypaybd.top');

if (!function_exists('skypayConfig')) {
    function skypayConfig(array $s): array {
        $key = trim((string)($s['skypay_brand_key'] ?? ''));
        if ($key === '' || $key === '0') {
            $old = trim((string)($s['snzpay_license_key'] ?? '')); // migrate old saved key
            $key = ($old !== '' && $old !== '0') ? $old : SKYPAY_DEFAULT_BRAND_KEY;
        }
        $base = trim((string)($s['skypay_base_url'] ?? ''));
        if ($base === '' || $base === '0' || stripos($base, 'skypaybd') === false) $base = SKYPAY_DEFAULT_BASE_URL;
        $base = preg_replace('#/api/.*$#i', '', rtrim($base, '/'));
        return ['brand_key' => $key, 'base_url' => $base];
    }
}

if (!function_exists('skypayAutoOn')) {
    function skypayAutoOn(array $s): bool {
        return (($s['auto_payment_on'] ?? '0') === '1')
            || ((string)($s['skypay_auto_verify'] ?? '0') === '1')
            || ((string)($s['snzpay_auto_verify'] ?? '0') === '1'); // old saved setting
    }
}

if (!function_exists('skypayHttp')) {
    function skypayHttp(string $url, string $brandKey, array $payload): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'BRAND-KEY: ' . $brandKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode(is_string($raw) ? $raw : '', true);
        return ['http' => $code, 'err' => $err, 'json' => is_array($json) ? $json : null];
    }
}

if (!function_exists('skypayMethod')) {
    function skypayMethod(string $m): string {
        $m = strtolower($m);
        if (strpos($m, 'bep20') !== false || strpos($m, 'bsc') !== false) return 'bep20';
        if (strpos($m, 'binance') !== false || strpos($m, 'usdt') !== false) return 'binance';
        if (strpos($m, 'nagad') !== false)  return 'nagad';
        if (strpos($m, 'rocket') !== false) return 'rocket';
        if (strpos($m, 'upay') !== false)   return 'upay';
        if (strpos($m, 'qr') !== false)     return 'banglaqr';
        return 'bkash';
    }
}

if (!function_exists('skypayVerifyTrx')) {
    /**
     * Verify a customer TrxID / Order ID with SkyPay v2.
     * Returns ['status'=>'success','amount','sender','trx_id','message'] or
     *         ['status'=>'error','message', 'duplicate'?, 'server_error'?]
     */
    function skypayVerifyTrx(array $s, string $trxId, string $method, float $amountBdt, string $cusName = 'Customer', array $meta = []): array {
        $cfg = skypayConfig($s);
        $m   = skypayMethod($method);
        $trx = trim($trxId);
        $trx = ($m === 'bep20') ? strtolower($trx) : strtoupper($trx);

        if ($trx === '') return ['status' => 'error', 'message' => 'Please enter your TrxID / Order ID!'];
        if ($amountBdt <= 0) return ['status' => 'error', 'message' => 'Invalid payment amount!'];

        // 1) create payment session (amount is checked by SkyPay against the real payment)
        $c = skypayHttp($cfg['base_url'] . '/api/v2/payment/create', $cfg['brand_key'], [
            'cus_name'  => $cusName !== '' ? $cusName : 'Customer',
            'amount'    => round($amountBdt, 2),
            'meta_data' => (object)$meta,
        ]);
        if ($c['err'] !== '' || $c['json'] === null || $c['http'] >= 500) {
            return ['status' => 'error', 'server_error' => true, 'message' => 'Payment server is not responding right now. Please try again.'];
        }
        $sessionId = (string)($c['json']['id'] ?? ($c['json']['data']['id'] ?? ''));
        if (empty($c['json']['status']) || $sessionId === '') {
            return ['status' => 'error', 'server_error' => true, 'message' => 'Payment gateway: ' . ($c['json']['message'] ?? 'Could not start verification session.')];
        }

        // 2) verify (SMS can arrive 5-20s late -> retry "not found" a few times)
        $tries = in_array($m, ['bkash', 'nagad', 'rocket', 'upay', 'banglaqr'], true) ? 3 : 2;
        $v = null;
        for ($i = 1; $i <= $tries; $i++) {
            $v = skypayHttp($cfg['base_url'] . '/api/v2/payment/verify', $cfg['brand_key'], [
                'id'             => $sessionId,
                'method'         => $m,
                'transaction_id' => $trx,
            ]);
            $j = $v['json'];
            if ($v['err'] === '' && is_array($j) && $j['status'] === true) {
                $d  = is_array($j['data'] ?? null) ? $j['data'] : [];
                $st = strtoupper((string)($d['status'] ?? 'COMPLETED'));
                if ($st === 'COMPLETED' || $st === 'SUCCESS' || $st === 'PAID') {
                    // prefer the real received amount (from SMS) if SkyPay sends it
                    $paid = 0.0;
                    foreach (['received_amount', 'paid_amount', 'sms_amount', 'actual_amount', 'trx_amount', 'transaction_amount', 'amount'] as $ak) {
                        if (isset($d[$ak]) && is_numeric($d[$ak]) && (float)$d[$ak] > 0) { $paid = (float)$d[$ak]; break; }
                    }
                    foreach (['sms', 'sms_body', 'message_body', 'body', 'text'] as $sk) {
                        if (!empty($d[$sk]) && is_string($d[$sk]) && preg_match('/(?:Tk|BDT|৳)\s*([0-9][0-9,]*(?:\.[0-9]+)?)/iu', $d[$sk], $mm)) {
                            $smsAmt = (float)str_replace(',', '', $mm[1]);
                            if ($smsAmt > 0) { $paid = $smsAmt; break; }
                        }
                    }
                    return [
                        'status'  => 'success',
                        'raw'     => json_encode($j, JSON_UNESCAPED_UNICODE),
                        'amount'  => $paid > 0 ? $paid : $amountBdt,
                        'sender'  => (string)($d['sender'] ?? ($d['mobile_number'] ?? '')),
                        'trx_id'  => (string)($d['transaction_id'] ?? $trx),
                        'message' => (string)($j['message'] ?? 'Payment verified successfully.'),
                    ];
                }
                return ['status' => 'error', 'message' => 'Payment is ' . $st . '. Please try again later.'];
            }
            $retryable = ($v['err'] !== '' || $j === null || $v['http'] === 404 || $v['http'] >= 500);
            if (!$retryable || $i === $tries) break;
            sleep(5);
        }

        $j   = $v['json'];
        $msg = is_array($j) ? (string)($j['message'] ?? '') : '';
        $raw = is_array($j) ? json_encode($j, JSON_UNESCAPED_UNICODE) : ('HTTP ' . $v['http'] . ' ' . $v['err']);
        if ($v['err'] !== '' || $j === null || $v['http'] >= 500) {
            return ['status' => 'error', 'server_error' => true, 'raw' => $raw, 'message' => 'Payment server is not responding right now. Please try again.'];
        }
        if ($v['http'] === 400 && stripos($msg, 'already') !== false) {
            return ['status' => 'error', 'duplicate' => true, 'raw' => $raw, 'message' => 'This TrxID has already been used!'];
        }
        return ['status' => 'error', 'raw' => $raw, 'message' => $msg !== '' ? $msg : 'Transaction ID not found. Please check your TrxID and try again.'];
    }
}


if (!function_exists('generateFromExternalProvider')) {
    function generateFromExternalProvider($apiPlanId, $quantity = 1) {
        global $pdo;
        $planIdInt = (int)$apiPlanId;
        $qtyInt    = max(1, min(100, (int)$quantity));

        if (function_exists('km_generate_key')) {
            return km_generate_key($pdo, $planIdInt, $qtyInt);
        }
        return ['status' => 'error', 'message' => 'Key Manager engine function is missing in config.php'];
    }
}

// 5-Digit Referral Generator
function generateUniqueReferralCode(PDO $pdo): string {
    do {
        $code = (string)rand(10000, 99999);
        $stmt = $pdo->prepare("SELECT `id` FROM `users` WHERE `referral_code` = ? LIMIT 1");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

// ====================================================================
// 5. BULLETPROOF AUTH CONTROLLER & REAL GOOGLE OAUTH
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_action'])) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    if ($_POST['auth_action'] === 'request_password_reset') {
        $email = strtolower(trim($_POST['reset_email'] ?? ''));
        if (empty($email)) {
            echo json_encode(['status' => 'error', 'message' => 'Please enter your email address.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id FROM `users` WHERE `email` = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare("INSERT INTO `password_resets` (`email`, `token`) VALUES (?, ?)")->execute([$email, $token]);
                echo json_encode(['status' => 'success', 'message' => 'A password reset link has been sent to your Gmail! Please check your inbox.']);
            } else {
                echo json_encode(['status' => 'success', 'message' => 'If that email exists, a reset link has been sent.']);
            }
        } catch(Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => 'System error. Try again later.']);
        }
        exit;
    }

    if ($_POST['auth_action'] === 'user_login') {
        $loginInput = trim($_POST['login_identity'] ?? '');
        $password   = $_POST['login_password'] ?? '';

        if (empty($loginInput) || empty($password)) {
            $_SESSION['flash_toast'] = ['type' => 'error', 'msg' => 'Username/Email and Password are required!'];
            header('Location: index.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = ? OR `email` = ? LIMIT 1");
            $stmt->execute([$loginInput, $loginInput]);
            $rowUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($rowUser && (password_verify($password, $rowUser['password']) || $password === $rowUser['password'])) {
                if (($rowUser['status'] ?? 'active') !== 'active') {
                    $_SESSION['flash_toast'] = ['type' => 'error', 'msg' => 'Account is suspended! Contact support.'];
                    header('Location: index.php');
                    exit;
                }

                if (empty($rowUser['referral_code'])) {
                    $refCode = generateUniqueReferralCode($pdo);
                    try {
                        $pdo->prepare("UPDATE `users` SET `referral_code` = ? WHERE `id` = ?")->execute([$refCode, $rowUser['id']]);
                        $rowUser['referral_code'] = $refCode;
                    } catch (Throwable $e) {}
                }

                $_SESSION['auth_user_id']  = (int)$rowUser['id'];
                $_SESSION['user_id']       = (int)$rowUser['id'];
                $_SESSION['auth_username'] = $rowUser['username'];
                $_SESSION['flash_toast']   = ['type' => 'success', 'msg' => 'Welcome back, ' . htmlspecialchars($rowUser['username']) . '!'];
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['flash_toast'] = ['type' => 'error', 'msg' => 'Invalid username or password!'];
                header('Location: index.php');
                exit;
            }
        } catch (Throwable $e) {
            $_SESSION['flash_toast'] = ['type' => 'error', 'msg' => 'Login error: ' . $e->getMessage()];
            header('Location: index.php');
            exit;
        }
    }

    if ($_POST['auth_action'] === 'user_register') {
        $username   = trim($_POST['reg_username'] ?? '');
        $email      = strtolower(trim($_POST['reg_email'] ?? ''));
        $password   = $_POST['reg_password'] ?? '';
        $referInput = trim($_POST['reg_referral'] ?? '');

        if (empty($referInput) && !empty($_SESSION['auto_ref_code'])) {
            $referInput = $_SESSION['auto_ref_code'];
        }

        if (strlen($username) < 3) {
            $_SESSION['flash_toast'] = ['type' => 'error', 'msg' => 'Username must be at least 3 characters!'];
            header('Location: index.php');
            exit;
        }
        if (strlen($password) < 6) {
            $_SESSION['flash_toast'] = ['type' => 'error', 'msg' => 'Password must be at least 6 characters!'];
            header('Location: index.php');
            exit;
        }

        try {
            $stmtChk = $pdo->prepare("SELECT `id` FROM `users` WHERE `username` = ? LIMIT 1");
            $stmtChk->execute([$username]);
            if ($stmtChk->fetchColumn()) {
                $_SESSION['flash_toast'] = ['type' => 'error', 'msg' => 'Username is already taken!'];
                header('Location: index.php');
                exit;
            }

            if (!empty($email)) {
                $stmtChkE = $pdo->prepare("SELECT `id` FROM `users` WHERE `email` = ? LIMIT 1");
                $stmtChkE->execute([$email]);
                if ($stmtChkE->fetchColumn()) {
                    $_SESSION['flash_toast'] = ['type' => 'error', 'msg' => 'Email is already registered!'];
                    header('Location: index.php');
                    exit;
                }
            }

            $referrerId   = 0;
            $referrerName = '';
            if (!empty($referInput)) {
                $stmtRef = $pdo->prepare("SELECT `id`, `username` FROM `users` WHERE `referral_code` = ? LIMIT 1");
                $stmtRef->execute([$referInput]);
                $refUser = $stmtRef->fetch(PDO::FETCH_ASSOC);
                if ($refUser) {
                    $referrerId   = (int)$refUser['id'];
                    $referrerName = $refUser['username'];
                }
            }

            $myReferralCode = generateUniqueReferralCode($pdo);
            $hashedPass     = password_hash($password, PASSWORD_DEFAULT);

            $stmtIns = $pdo->prepare("INSERT INTO `users` (`username`, `email`, `password`, `role`, `balance`, `coins`, `referral_code`, `referred_by`, `status`, `created_at`) VALUES (?, ?, ?, 'customer', 0.00, 0, ?, ?, 'active', NOW())");
            $stmtIns->execute([$username, $email ?: null, $hashedPass, $myReferralCode, $referrerId]);
            $newId = (int)$pdo->lastInsertId();

            if ($referrerId > 0 && $referralBdtOn) {
                try {
                    $bonusInUsd = round($referralBonusAmount / $adminRate, 2);
                    $pdo->prepare("UPDATE `users` SET `balance` = `balance` + ?, `referral_earnings` = `referral_earnings` + ? WHERE `id` = ?")
                        ->execute([$bonusInUsd, $referralBonusAmount, $referrerId]);

                    $pdo->prepare("INSERT INTO `referrals` (`referrer_id`, `referred_user_id`, `reward_amount`, `status`) VALUES (?, ?, ?, 'credited')")
                        ->execute([$referrerId, $newId, $referralBonusAmount]);

                    $pdo->prepare("INSERT INTO `transactions` (`user_id`, `method`, `amount`, `bonus_amount`, `sender_num`, `trx_id`, `status`) VALUES (?, 'Referral Bonus', ?, 0.00, ?, 'REF-BONUS', 'approved')")
                        ->execute([$referrerId, $bonusInUsd, "Ref: " . $username]);

                    recordLiveActivity($pdo, $referrerName, 'referral', 'Referral Commission Earned', $referralBonusAmount);
                } catch (Throwable $e) {}
            }

            // Referral => free spin (only if admin turned it on in Spin Control)
            if ($referrerId > 0) {
                ffSpinAddReferral($pdo, $settings, (int)$referrerId);
            }

            // Referral => COINS (rules set in Admin > Refer & Earn)
            if ($referrerId > 0 && $referralCoinOn && ($settings['referral_system_status'] ?? '1') === '1') {
                try {
                    $coinReward = ffRefCoinsPerReferral($pdo);
                    if ($coinReward > 0) {
                        $pdo->prepare("UPDATE `users` SET `coins` = `coins` + ? WHERE `id` = ?")->execute([$coinReward, $referrerId]);
                        $upRef = $pdo->prepare("UPDATE `referrals` SET `reward_coins` = ? WHERE `referrer_id` = ? AND `referred_user_id` = ?");
                        $upRef->execute([$coinReward, $referrerId, $newId]);
                        if ($upRef->rowCount() === 0) {
                            $pdo->prepare("INSERT INTO `referrals` (`referrer_id`, `referred_user_id`, `reward_amount`, `status`, `reward_coins`) VALUES (?, ?, 0.00, 'credited', ?)")
                                ->execute([$referrerId, $newId, $coinReward]);
                        }
                        recordLiveActivity($pdo, $referrerName, 'referral', 'Referral Coins Earned: ' . $coinReward, 0);
                    }
                } catch (Throwable $e) {}
            }

            // Make sure the referral is always logged (even if BDT is OFF and no coin rule is active)
            if ($referrerId > 0) {
                try {
                    $stChk = $pdo->prepare("SELECT COUNT(*) FROM `referrals` WHERE `referrer_id` = ? AND `referred_user_id` = ?");
                    $stChk->execute([$referrerId, $newId]);
                    if ((int)$stChk->fetchColumn() === 0) {
                        $pdo->prepare("INSERT INTO `referrals` (`referrer_id`, `referred_user_id`, `reward_amount`, `status`) VALUES (?, ?, 0.00, 'credited')")
                            ->execute([$referrerId, $newId]);
                    }
                } catch (Throwable $e) {}
            }

            unset($_SESSION['auto_ref_code']);

            $_SESSION['auth_user_id']  = $newId;
            $_SESSION['user_id']       = $newId;
            $_SESSION['auth_username'] = $username;
            $_SESSION['flash_toast']   = ['type' => 'success', 'msg' => 'Account created! Your Refer Code: ' . $myReferralCode];
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $_SESSION['flash_toast'] = ['type' => 'error', 'msg' => 'Registration failed: ' . $e->getMessage()];
            header('Location: index.php');
            exit;
        }
    }

    if ($_POST['auth_action'] === 'update_profile_photo') {
        if (!isset($_SESSION['auth_user_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
            exit;
        }
        $userId = (int)$_SESSION['auth_user_id'];
        
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileInfo = pathinfo($_FILES['avatar']['name']);
            $ext = strtolower($fileInfo['extension'] ?? 'jpg');
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid image format. Only JPG, PNG, WEBP allowed.']);
                exit;
            }
            $newFileName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
                $dbPath = 'uploads/' . $newFileName;
                try {
                    $pdo->prepare("UPDATE `users` SET `avatar_url` = ? WHERE `id` = ?")->execute([$dbPath, $userId]);
                    echo json_encode(['status' => 'success', 'url' => $dbPath, 'message' => 'Profile picture updated!']);
                } catch (Throwable $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
            }
        }
        echo json_encode(['status' => 'error', 'message' => 'File upload failed.']);
        exit;
    }

    if ($_POST['auth_action'] === 'submit_feedback') {
        if (!isset($_SESSION['auth_user_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Please login to submit feedback.']);
            exit;
        }
        $userId = (int)$_SESSION['auth_user_id'];
        $productId = (int)($_POST['product_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 5);
        $comment = trim($_POST['comment'] ?? '');
        
        if ($productId <= 0 || empty($comment)) {
            echo json_encode(['status' => 'error', 'message' => 'Please select a product and write a comment.']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO `feedbacks` (`user_id`, `product_id`, `rating`, `comment`) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $productId, $rating, $comment]);
            echo json_encode(['status' => 'success', 'message' => 'Thank you! Your feedback has been submitted.']);
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to submit feedback.']);
        }
        exit;
    }
}

// GOOGLE OAUTH CALLBACK HANDLER (FIXED - READS FROM DATABASE)
if (isset($_GET['code']) && !isset($_GET['logout'])) {
    $clientId     = !empty($settings['google_client_id']) ? trim($settings['google_client_id']) : (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '');
    $clientSecret = !empty($settings['google_client_secret']) ? trim($settings['google_client_secret']) : (defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '');
    $redirectUri  = !empty($settings['google_redirect_uri']) ? trim($settings['google_redirect_uri']) : (defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : (defined('SITE_URL') ? SITE_URL . '/index.php' : ''));

    if ($clientId && $clientSecret) {
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postData = [
            'code'          => $_GET['code'],
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code'
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        $response = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode($response, true);

        if (isset($tokenData['access_token'])) {
            $userInfoUrl = 'https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $tokenData['access_token'];
            $ch = curl_init($userInfoUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $userInfoResponse = curl_exec($ch);
            curl_close($ch);

            $googleUser = json_decode($userInfoResponse, true);

            if (isset($googleUser['email'])) {
                $email = $googleUser['email'];
                $name = $googleUser['name'] ?? 'Google User';
                $googleId = $googleUser['id'];

                try {
                    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = ? OR `google_id` = ? LIMIT 1");
                    $stmt->execute([$email, $googleId]);
                    $rowUser = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$rowUser) {
                        $username = $name;
                        // Ensure unique username
                        $stmtChk = $pdo->prepare("SELECT id FROM `users` WHERE `username` = ?");
                        $stmtChk->execute([$username]);
                        if ($stmtChk->fetch()) {
                            $username = $username . rand(100, 999);
                        }

                        $myReferralCode = generateUniqueReferralCode($pdo);
                        $stmtIns = $pdo->prepare("INSERT INTO `users` (`username`, `email`, `password`, `role`, `balance`, `coins`, `referral_code`, `status`, `google_id`, `created_at`) VALUES (?, ?, 'OAUTH_USER', 'customer', 0.00, 0, ?, 'active', ?, NOW())");
                        $stmtIns->execute([$username, $email, $myReferralCode, $googleId]);
                        $userId = (int)$pdo->lastInsertId();
                        $rowUser = ['id' => $userId, 'username' => $username];
                    } else {
                        // Update google ID if missing
                        if (empty($rowUser['google_id'])) {
                            $pdo->prepare("UPDATE `users` SET `google_id` = ? WHERE `id` = ?")->execute([$googleId, $rowUser['id']]);
                        }
                    }

                    $_SESSION['auth_user_id']  = (int)$rowUser['id'];
                    $_SESSION['user_id']       = (int)$rowUser['id'];
                    $_SESSION['auth_username'] = $rowUser['username'];
                    $_SESSION['flash_toast']   = ['type' => 'success', 'msg' => 'Logged in with Google successfully!'];
                    header('Location: index.php');
                    exit;

                } catch (Throwable $e) {
                    $_SESSION['flash_toast'] = ['type' => 'error', 'msg' => 'Google login failed: ' . $e->getMessage()];
                    header('Location: index.php');
                    exit;
                }
            }
        }
    }
    $_SESSION['flash_toast'] = ['type' => 'error', 'msg' => 'Google authentication failed. Please try again.'];
    header('Location: index.php');
    exit;
}

// ====================================================================
// 6. USER CONTEXT RESOLUTION
// ====================================================================
$userId     = (int)($_SESSION['auth_user_id'] ?? $_SESSION['user_id'] ?? 0);
$user       = null;
$isLoggedIn = false;

ffEnsureTrxBdtColumn($pdo); // adds `amount_bdt` once (runs outside any DB transaction)

// session expired on the server? -> restore login from the signed remember cookie
if ($userId <= 0) {
    $rememberUid = ffRememberCheck($pdo);
    if ($rememberUid > 0) {
        $userId = $rememberUid;
        $_SESSION['auth_user_id'] = $rememberUid;
        $_SESSION['user_id']      = $rememberUid;
    } elseif (isset($_COOKIE['ff_auth'])) {
        ffRememberClear();
    }
}

if ($userId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$userId]);
        $fetchedUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fetchedUser && ($fetchedUser['status'] ?? 'active') === 'active') {
            if (empty($fetchedUser['referral_code'])) {
                $refCode = generateUniqueReferralCode($pdo);
                try {
                    $pdo->prepare("UPDATE `users` SET `referral_code` = ? WHERE `id` = ?")->execute([$refCode, $fetchedUser['id']]);
                    $fetchedUser['referral_code'] = $refCode;
                } catch (Throwable $e) {}
            }
            $user       = $fetchedUser;
            $isLoggedIn = true;
            if (empty($_SESSION['auth_username'])) $_SESSION['auth_username'] = $fetchedUser['username'] ?? '';
            // keep login alive: (re)issue remember cookie when missing, invalid, or older than 1 hour
            $rcParts = explode('.', (string)($_COOKIE['ff_auth'] ?? ''));
            if (count($rcParts) !== 3 || (int)$rcParts[0] !== (int)$fetchedUser['id'] || ((int)$rcParts[1] - time()) < (FF_REMEMBER_HOURS * 3600 - 3600)) {
                ffRememberSet($pdo, $fetchedUser);
            }
        } else {
            ffRememberClear();
            unset($_SESSION['auth_user_id'], $_SESSION['user_id'], $_SESSION['auth_username']);
            $userId = 0;
        }
    } catch (Throwable $e) {
        $userId = 0;
    }
}

// AJAX calls never write the session -> release the session lock now so parallel
// requests (polling, payment verify, key reset) don't wait for each other
if ((isset($_POST['ajax_action']) || isset($_GET['ajax_action'])) && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if (!$isLoggedIn || !$user) {
    $user = [
        'id'                     => 0,
        'username'               => 'Guest',
        'role'                   => 'customer',
        'balance'                => 0.00,
        'coins'                  => 0,
        'email'                  => '',
        'referral_code'          => '',
        'referral_earnings'      => 0.00,
        'can_add_fund'           => 1,
        'is_product_restricted'  => 0,
        'avatar_url'             => '',
        'created_at'             => date('Y-m-d H:i:s')
    ];
}

$rawRole          = strtolower(trim((string)($user['role'] ?? 'customer')));
$userRole         = ($rawRole === 'reseller') ? 'reseller' : 'customer';
$canAddFund       = ($isDepositOnline && (int)($user['can_add_fund'] ?? 1) === 1) ? 1 : 0;
$userCustomRate   = (float)($user['custom_bdt_rate'] ?? 0);
$effectiveBdtRate = ($userCustomRate > 0) ? $userCustomRate : $adminRate;
$userCoins        = (int)($user['coins'] ?? 0);
$userBalanceBdt   = round((float)$user['balance'] * $effectiveBdtRate, 0);
$userBalanceUsdt  = (float)$user['balance'];

// Reseller Specific Products & Custom Pricing
$userCustomPrices    = [];
$userAllowedProducts = [];

// Admin custom prices work for BOTH customers and resellers
if ($isLoggedIn) {
    try {
        $stmtUCP = $pdo->prepare("SELECT `plan_id`, `custom_price` FROM `user_custom_prices` WHERE `user_id` = ?");
        $stmtUCP->execute([$user['id']]);
        $userCustomPrices = $stmtUCP->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $e) {}
}

if ($isLoggedIn && $userRole === 'reseller') {
    try {
        if ((int)($user['is_product_restricted'] ?? 0) === 1) {
            $stmtUAP = $pdo->prepare("SELECT `product_id` FROM `user_allowed_products` WHERE `user_id` = ?");
            $stmtUAP->execute([$user['id']]);
            $rawAllowed = $stmtUAP->fetchAll(PDO::FETCH_COLUMN);
            $userAllowedProducts = array_filter(array_map('intval', $rawAllowed));
        }
    } catch (Throwable $e) {}
}

// ====================================================================
// 7. ALL AJAX HANDLERS (LIVE TRANSACTIONS, PROMO, CHECKOUT, ADD FUND)
// ====================================================================

// A. LIVE TRANSACTIONS DATA FEED
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_live_activities') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    $feed = [];
    try {
        $stmt = $pdo->query("SELECT `user_name`, `action_type`, `details`, `amount_bdt`, `created_at` 
                             FROM `live_activity_stream` 
                             ORDER BY `id` DESC LIMIT 15");
        $feed = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {}

    echo json_encode(['status' => 'success', 'data' => $feed]);
    exit;
}

// B. REAL-TIME PROMO CODE VALIDATION
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'apply_promo_code') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    $promoInput = strtoupper(trim($_POST['promo_code'] ?? ''));
    if (empty($promoInput)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter coupon code!']);
        exit;
    }

    try {
        $stmtP = $pdo->prepare("SELECT * FROM `promo_codes` WHERE UPPER(`code`) = ? AND `is_active` = 1 LIMIT 1");
        $stmtP->execute([$promoInput]);
        $promoRow = $stmtP->fetch(PDO::FETCH_ASSOC);

        if (!$promoRow) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid coupon code!']);
            exit;
        }

        if (!empty($promoRow['expires_at']) && strtotime($promoRow['expires_at']) < time()) {
            echo json_encode(['status' => 'error', 'message' => 'Promo code has expired!']);
            exit;
        }

        if ((int)$promoRow['used_count'] >= (int)$promoRow['max_uses']) {
            echo json_encode(['status' => 'error', 'message' => 'Promo usage limit exceeded!']);
            exit;
        }

        $discountPercent = (float)$promoRow['discount_percent'];
        echo json_encode([
            'status'           => 'success',
            'code'             => $promoRow['code'],
            'discount_percent' => $discountPercent,
            'message'          => "Promo applied! {$discountPercent}% discount activated."
        ]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'Promo validation error']);
        exit;
    }
}

// C0. PENDING ORDER POLLER (delivers approved orders + returns unseen results)
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'check_pending_orders') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    if (!$isLoggedIn || $user['id'] <= 0) {
        echo json_encode(['status' => 'error', 'require_login' => true]);
        exit;
    }
    ffEnsureOrdersTable($pdo);
    $uid = (int)$user['id'];

    try {
        // un-stick orders that crashed mid-delivery
        $pdo->prepare("UPDATE `pending_orders` SET `status` = 'waiting' WHERE `user_id` = ? AND `status` = 'processing' AND `updated_at` < (NOW() - INTERVAL 3 MINUTE)")->execute([$uid]);

        $stmtW = $pdo->prepare("SELECT * FROM `pending_orders` WHERE `user_id` = ? AND `status` = 'waiting' ORDER BY `id` ASC LIMIT 20");
        $stmtW->execute([$uid]);
        foreach ($stmtW->fetchAll(PDO::FETCH_ASSOC) as $w) {
            if ($w['source'] === 'auto') {
                // payment already verified earlier -> just retry delivery
                ffDeliverOrder($pdo, (int)$w['id']);
                continue;
            }
            // manual order: look at the admin's decision on the transaction
            $stmtT = $pdo->prepare("SELECT `status` FROM `transactions` WHERE `user_id` = ? AND UPPER(`trx_id`) = ? AND `method` NOT LIKE '%Refund%' ORDER BY `id` ASC LIMIT 1");
            $stmtT->execute([$uid, strtoupper($w['trx_id'])]);
            $trxStatus = $stmtT->fetchColumn();
            if ($trxStatus === false) continue;
            $trxStatus = strtolower((string)$trxStatus);

            if (in_array($trxStatus, ['approved', 'completed', 'success', 'paid'], true)) {
                ffDeliverOrder($pdo, (int)$w['id']);
            } elseif (in_array($trxStatus, ['rejected', 'declined', 'cancelled', 'canceled', 'failed'], true)) {
                $pdo->prepare("UPDATE `pending_orders` SET `status` = 'rejected', `result_message` = ?, `seen` = 0 WHERE `id` = ? AND `status` = 'waiting'")
                    ->execute(['Your payment was not approved by admin. Please contact support with your TrxID.', $w['id']]);
            }
        }

        $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM `pending_orders` WHERE `user_id` = ? AND `status` IN ('waiting','processing')");
        $stmtCnt->execute([$uid]);
        $waitingCount = (int)$stmtCnt->fetchColumn();

        $stmtU2 = $pdo->prepare("SELECT * FROM `pending_orders` WHERE `user_id` = ? AND `seen` = 0 AND `status` IN ('delivered','refunded','rejected') ORDER BY `id` ASC LIMIT 10");
        $stmtU2->execute([$uid]);
        $orders = [];
        foreach ($stmtU2->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $orders[] = [
                'id'           => (int)$r['id'],
                'state'        => $r['status'],
                'product_name' => $r['product_name'],
                'keys'         => $r['status'] === 'delivered' ? (json_decode($r['keys_json'] ?: '[]', true) ?: []) : [],
                'message'      => $r['result_message']
            ];
        }
        echo json_encode(['status' => 'success', 'waiting' => $waitingCount, 'orders' => $orders], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'Poll failed']);
    }
    exit;
}

if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'ack_order') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    if ($isLoggedIn && $user['id'] > 0) {
        ffEnsureOrdersTable($pdo);
        try {
            $pdo->prepare("UPDATE `pending_orders` SET `seen` = 1 WHERE `id` = ? AND `user_id` = ?")->execute([(int)($_POST['order_id'] ?? 0), (int)$user['id']]);
        } catch (Throwable $e) {}
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// C. CHECKOUT & AUTO KEY DELIVERY (WALLET, BDT, USDT)
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'verify_and_deliver') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    if (!$isLoggedIn || $user['id'] <= 0) {
        echo json_encode(['status' => 'error', 'require_login' => true, 'message' => 'Please login to complete purchase!']);
        exit;
    }

    if ($isSiteMaintenance) {
        echo json_encode(['status' => 'error', 'message' => 'Orders are temporarily paused for maintenance.']);
        exit;
    }

    $planId     = (int)($_POST['plan_id'] ?? 0);
    $quantity   = max(1, min(50, (int)($_POST['quantity'] ?? 1)));
    $methodKey  = strtolower(trim($_POST['method_key'] ?? 'wallet'));
    $gatewayKey = strtolower(trim($_POST['gateway_key'] ?? 'bkash'));
    $methodId   = (int)($_POST['method_id'] ?? 0);
    $trxId      = trim(htmlspecialchars(strtoupper($_POST['trx_id'] ?? '')));
    $senderNum  = trim(htmlspecialchars($_POST['sender'] ?? ''));
    $promoCode  = strtoupper(trim($_POST['promo_code'] ?? ''));

    if ($planId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product plan!']);
        exit;
    }

    if ($methodKey !== 'wallet' && (empty($trxId) || strlen($trxId) < 4)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid Transaction ID (TrxID)!']);
        exit;
    }

    if ($methodKey !== 'wallet') {
        $trxBlockMsg = ffTrxBlockedMessage($pdo, $trxId);
        if ($trxBlockMsg !== '') {
            echo json_encode(['status' => 'error', 'message' => $trxBlockMsg]);
            exit;
        }
        ffEnsureOrdersTable($pdo);
        $stmtChkOrd = $pdo->prepare("SELECT `id` FROM `pending_orders` WHERE UPPER(`trx_id`) = ? LIMIT 1");
        $stmtChkOrd->execute([$trxId]);
        if ($stmtChkOrd->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'This Transaction ID (TrxID) has already been used!']);
            exit;
        }
    }

    $methodRow = null;
    if ($methodId > 0 && $methodKey !== 'wallet') {
        $stmtM = $pdo->prepare("SELECT * FROM `payment_methods` WHERE `id` = ? AND `is_active` = 1 LIMIT 1");
        $stmtM->execute([$methodId]);
        $methodRow = $stmtM->fetch(PDO::FETCH_ASSOC);
    }
    
    $methodDisplayName = 'Wallet Balance';
    if ($methodKey === 'bdt') $methodDisplayName = $methodRow ? $methodRow['name'] : 'BDT Gateway';
    if ($methodKey === 'usdt') $methodDisplayName = 'Binance / USDT Gateway';

    $stmt = $pdo->prepare("SELECT p.id as real_product_id, p.name as product_name, p.is_active, p.logo_url, p.image_url,
                                  COALESCE(NULLIF(pl.plan_title, ''), NULLIF(pl.validity, ''), 'Standard Plan') as plan_title,
                                  pl.reseller_price, pl.user_price, pl.price, pl.regular_price,
                                  pl.reseller_discount_percent, COALESCE(pl.discount_percent, 0) as discount_percent,
                                  pl.api_plan_id, pl.reward_coins, pl.coin_price 
                           FROM `product_plans` pl 
                           JOIN `products` p ON pl.product_id = p.id 
                           WHERE pl.id = ? AND (p.is_deleted = 0 OR p.is_deleted IS NULL) LIMIT 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan || (int)($plan['is_active'] ?? 1) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'This plan is currently unavailable!']);
        exit;
    }

    $productFullName    = $plan['product_name'] . ' (' . $plan['plan_title'] . ')';
    $rewardCoinsPerItem = max(0, (int)($plan['reward_coins'] ?? 0));
    $totalRewardCoins   = $rewardCoinsPerItem * $quantity;

    $rawRetailPrice = (float)(ffCustomerBasePrice($plan) ?: (($plan['price'] ?? 0) ?: ($plan['reseller_price'] ?? 0)));
    $rawResellPrice = (float)(($plan['reseller_price'] ?? 0) ?: (($plan['price'] ?? 0) ?: ($plan['user_price'] ?? 0)));
    $basePrice      = ($userRole === 'reseller') ? ($rawResellPrice > 0 ? $rawResellPrice : $rawRetailPrice) : ($rawRetailPrice > 0 ? $rawRetailPrice : $rawResellPrice);

    $unitPrice = null;
    if (isset($userCustomPrices[$planId]) && (float)$userCustomPrices[$planId] > 0) {
        $unitPrice = (float)$userCustomPrices[$planId];
    } else {
        $explicitDiscount = ($userRole === 'reseller') 
            ? (float)($plan['reseller_discount_percent'] ?? 0) 
            : (float)($plan['discount_percent'] ?? 0);

        $unitPrice = ($explicitDiscount > 0) ? round($basePrice * (1 - ($explicitDiscount / 100)), 2) : $basePrice;
    }

    $promoDiscountPercent = 0.00;
    if (!empty($promoCode)) {
        $stmtP = $pdo->prepare("SELECT `discount_percent` FROM `promo_codes` WHERE UPPER(`code`) = ? AND `is_active` = 1 LIMIT 1");
        $stmtP->execute([$promoCode]);
        $promoDiscountPercent = (float)$stmtP->fetchColumn();
        if ($promoDiscountPercent > 0) {
            $unitPrice = round($unitPrice * (1 - ($promoDiscountPercent / 100)), 2);
            $pdo->prepare("UPDATE `promo_codes` SET `used_count` = `used_count` + 1 WHERE UPPER(`code`) = ?")->execute([$promoCode]);
        }
    }

    $totalCostUsd = $unitPrice * $quantity;
    $totalCostBdt = round($totalCostUsd * $effectiveBdtRate, 0);

    $verifiedAmountBdt = $totalCostBdt;

    // WALLET BALANCE CHECK
    if ($methodKey === 'wallet') {
        $stmtBal = $pdo->prepare("SELECT `balance` FROM `users` WHERE `id` = ? LIMIT 1");
        $stmtBal->execute([$user['id']]);
        $currentBalance = (float)$stmtBal->fetchColumn();

        if ($currentBalance < $totalCostUsd) {
            echo json_encode([
                'status'  => 'error', 
                'message' => 'Insufficient wallet balance! Required: ৳' . number_format($totalCostBdt, 0) . ' BDT.'
            ]);
            exit;
        }
    } else {
        $isAutoPaymentEnforced = skypayAutoOn($settings);
        
        if ($isAutoPaymentEnforced) {
            $gatewayMethod = ($methodKey === 'usdt') ? ('usdt ' . $gatewayKey) : $gatewayKey;
            $payRes = skypayVerifyTrx($settings, $trxId, $gatewayMethod, (float)$totalCostBdt, (string)($user['username'] ?? 'Customer'), ['user_id' => (int)$user['id'], 'type' => 'order', 'plan_id' => $planId]);
            ffSkypayLog($pdo, (int)$user['id'], $trxId, (float)$totalCostBdt, $payRes);
            // paid less than the price? -> do not deliver
            if ($payRes['status'] === 'success' && (float)($payRes['amount'] ?? 0) > 0 && (float)$payRes['amount'] + 0.5 < (float)$totalCostBdt) {
                $payRes = ['status' => 'error', 'message' => 'Paid amount ৳' . number_format((float)$payRes['amount'], 0) . ' is less than the price ৳' . number_format((float)$totalCostBdt, 0) . '. Please contact support.'];
            }
            
            if ($payRes['status'] === 'success') {
                ffTrxAttemptClear($pdo, $trxId);
                $verifiedAmountBdt = (float)($payRes['amount'] ?? $totalCostBdt);
                if (!empty($payRes['sender']) && empty($senderNum)) $senderNum = $payRes['sender'];
            } else {
                if (!empty($payRes['duplicate'])) {
                    echo json_encode(['status' => 'error', 'verification_failed' => true, 'message' => $payRes['message']]);
                    exit;
                }
                $failMsg  = $payRes['message'] ?? 'Transaction verification failed at gateway!';
                $failTry  = ffTrxAttemptFail($pdo, $trxId, (int)$user['id'], $failMsg);
                if ($failTry <= FF_TRX_MAX_AUTO_TRIES) {
                    echo json_encode([
                        'status'              => 'error',
                        'verification_failed' => true,
                        'message'             => $failMsg . ' (Try ' . $failTry . '/' . FF_TRX_MAX_AUTO_TRIES . ' — if it fails again, your TrxID will be sent to admin for manual approval.)'
                    ]);
                    exit;
                }
                // more than 2 failed tries -> send to admin panel as manual request
                $ffGoManual = true;
            }
        }
        if (!$isAutoPaymentEnforced || !empty($ffGoManual)) {
            if (!empty($ffGoManual)) ffTrxAttemptClear($pdo, $trxId);
            $amountInUsd = round($totalCostBdt / $effectiveBdtRate, 2);
            try {
                $pdo->prepare("INSERT INTO `transactions` (`user_id`, `method`, `amount`, `bonus_amount`, `sender_num`, `trx_id`, `status`) VALUES (?, ?, ?, 0.00, ?, ?, 'pending')")
                    ->execute([$user['id'], $methodDisplayName . ' (Manual Order)', $amountInUsd, $senderNum ?: 'N/A', $trxId]);
                ffSetLastTrxBdt($pdo, (float)$totalCostBdt);

                // Save full order so key can be auto-delivered the moment admin approves
                $manualOrderId = ffCreateOrder($pdo, [
                    'user_id' => (int)$user['id'], 'plan_id' => $planId, 'product_id' => (int)$plan['real_product_id'],
                    'product_name' => $productFullName, 'quantity' => $quantity,
                    'method_key' => ($methodKey === 'usdt' ? 'usdt' : 'bdt'), 'method_name' => $methodDisplayName,
                    'trx_id' => $trxId, 'sender_num' => $senderNum ?: 'N/A',
                    'promo_code' => $promoCode, 'promo_percent' => $promoDiscountPercent,
                    'unit_price_usd' => $unitPrice, 'total_usd' => $totalCostUsd, 'paid_usd' => $amountInUsd,
                    'total_bdt' => $totalCostBdt, 'reward_coins' => $totalRewardCoins, 'source' => 'manual'
                ]);

                recordLiveActivity($pdo, $user['username'], 'purchase', 'Purchased ' . $productFullName, $totalCostBdt);

                echo json_encode([
                    'status'        => 'pending_submitted',
                    'auto_approved' => false,
                    'message'       => (!empty($ffGoManual) ? 'Gateway could not verify your TrxID. Your order has been sent to admin for manual approval. ' : '') . 'Order submitted! As soon as admin confirms, your key will pop up here automatically (you can also leave this page - key will be saved in My Key).',
                    'order_id'      => $manualOrderId,
                    'product_name'  => $productFullName,
                    'amount'        => $totalCostBdt
                ]);
                exit;
            } catch (Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => 'Order error: ' . $e->getMessage()]);
                exit;
            }
        }
    }

    // DIRECT PAYMENT (BDT / USDT) VERIFIED => save order first, then deliver.
    // Order is saved BEFORE delivery so that even if the user closes the page / network drops,
    // the key popup is shown on next poll/page load. No key => money goes to wallet.
    if ($methodKey !== 'wallet') {
        try {
            $autoOrderId = ffCreateOrder($pdo, [
                'user_id' => (int)$user['id'], 'plan_id' => $planId, 'product_id' => (int)$plan['real_product_id'],
                'product_name' => $productFullName, 'quantity' => $quantity,
                'method_key' => ($methodKey === 'usdt' ? 'usdt' : 'bdt'), 'method_name' => $methodDisplayName,
                'trx_id' => $trxId, 'sender_num' => $senderNum ?: 'N/A',
                'promo_code' => $promoCode, 'promo_percent' => $promoDiscountPercent,
                'unit_price_usd' => $unitPrice, 'total_usd' => $totalCostUsd,
                'paid_usd' => round($verifiedAmountBdt / $effectiveBdtRate, 2),
                'total_bdt' => $verifiedAmountBdt, 'reward_coins' => $totalRewardCoins, 'source' => 'auto'
            ]);
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => 'This Transaction ID (TrxID) has already been used!']);
            exit;
        }
        $delRes = ffDeliverOrder($pdo, $autoOrderId);
        if (($delRes['status'] ?? '') === 'error') {
            // payment is verified & saved; poller will retry delivery automatically
            $delRes = ['status' => 'pending_submitted', 'message' => 'Payment verified! Your key is being prepared and will pop up automatically in a moment.', 'product_name' => $productFullName, 'amount' => $totalCostBdt];
        }
        $delRes['order_id'] = $autoOrderId;
        echo json_encode($delRes, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // GENERATE KEYS (STOCK OR API) - WALLET PURCHASE
    $generatedKeys = [];
    $cleanQty      = (int)$quantity;

    $stmtStock = $pdo->prepare("SELECT `id`, `license_key` FROM `stock_keys` WHERE `plan_id` = ? AND `status` = 'available' ORDER BY `id` ASC LIMIT $cleanQty");
    $stmtStock->execute([$planId]);
    $stockRows = $stmtStock ? $stmtStock->fetchAll(PDO::FETCH_ASSOC) : [];

    if (count($stockRows) === $cleanQty) {
        $stockIds = array_column($stockRows, 'id');
        $inClause = implode(',', array_map('intval', $stockIds));
        $pdo->exec("UPDATE `stock_keys` SET `status` = 'used' WHERE `id` IN ($inClause)");
        $generatedKeys = array_column($stockRows, 'license_key');
    } else {
        $apiPlanId   = (int)($plan['api_plan_id'] ?? 0);
        $targetApiId = $apiPlanId > 0 ? $apiPlanId : $planId;
        $apiResult   = generateFromExternalProvider($targetApiId, $quantity);

        if ($apiResult['status'] === 'success' && !empty($apiResult['keys'])) {
            $generatedKeys = (array)$apiResult['keys'];
        } else {
            if ($methodKey !== 'wallet') {
                $amountToCreditUsd = round($verifiedAmountBdt / $effectiveBdtRate, 2);
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE `users` SET `balance` = `balance` + ? WHERE `id` = ?")->execute([$amountToCreditUsd, $user['id']]);
                    $pdo->prepare("INSERT INTO `transactions` (`user_id`, `method`, `amount`, `bonus_amount`, `sender_num`, `trx_id`, `status`) VALUES (?, ?, ?, 0.00, ?, ?, 'approved')")
                        ->execute([$user['id'], $methodDisplayName . ' (Refund Out of Stock)', $amountToCreditUsd, $senderNum ?: 'Trx Verified', $trxId]);
                    $pdo->commit();

                    echo json_encode([
                        'status'  => 'out_of_stock_refunded',
                        'message' => 'Product is currently Out of Stock. ৳' . number_format($verifiedAmountBdt, 0) . ' BDT has been credited to your Main Wallet.'
                    ]);
                    exit;
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    echo json_encode(['status' => 'error', 'message' => 'Processing failed: ' . $e->getMessage()]);
                    exit;
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Out of stock! No balance was deducted.']);
                exit;
            }
        }
    }

    // FINAL DELIVERY (DIRECT PAYMENT WALLET GLITCH 100% FIXED)
    $pdo->beginTransaction();
    try {
        if ($methodKey === 'wallet') {
            // ১. ওয়ালেট দিয়ে কিনলে ব্যালেন্স মাইনাস হবে
            $stmtDeduct = $pdo->prepare("UPDATE `users` SET `balance` = `balance` - ?, `coins` = `coins` + ? WHERE `id` = ? AND `balance` >= ?");
            $stmtDeduct->execute([$totalCostUsd, $totalRewardCoins, $user['id'], $totalCostUsd]);
            
            if ($stmtDeduct->rowCount() === 0) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Insufficient balance during checkout!']);
                exit;
            }
            
            $pdo->prepare("INSERT INTO `transactions` (`user_id`, `method`, `amount`, `bonus_amount`, `sender_num`, `trx_id`, `status`) VALUES (?, 'Wallet Purchase', ?, 0.00, 'Wallet', ?, 'approved')")
                ->execute([$user['id'], $totalCostUsd, 'WLT-' . strtoupper(uniqid())]);
            ffSetLastTrxBdt($pdo, (float)$totalCostBdt);
        } else {
            // ২. ডাইরেক্ট পেমেন্ট: ওয়ালেটে ১ টাকাও যোগ হবে না, শুধু অর্ডার ট্রানজেকশন রেকর্ড হবে
            $amountInUsd = round($verifiedAmountBdt / $effectiveBdtRate, 2);
            $orderMethodTitle = 'Direct Order: ' . $methodDisplayName;
            $pdo->prepare("INSERT INTO `transactions` (`user_id`, `method`, `amount`, `bonus_amount`, `sender_num`, `trx_id`, `status`) VALUES (?, ?, ?, 0.00, ?, ?, 'approved')")
                ->execute([$user['id'], $orderMethodTitle, $amountInUsd, $senderNum ?: 'N/A', $trxId]);
            ffSetLastTrxBdt($pdo, (float)$verifiedAmountBdt);

            if ($totalRewardCoins > 0) {
                $pdo->prepare("UPDATE `users` SET `coins` = `coins` + ? WHERE `id` = ?")->execute([$totalRewardCoins, $user['id']]);
            }
        }

        // ৩. লাইসেন্স টেবিলে পেমেন্ট মেথডসহ সরাসরি সেভ (অ্যাডমিন প্যানেলে দেখার জন্য)
        $payLabel = ($methodKey === 'wallet') ? 'wallet' : (($methodKey === 'usdt') ? 'usdt' : 'bdt');
        $priceLogged = $unitPrice;

        $stmtInsert = $pdo->prepare("INSERT INTO `licenses` (`user_id`, `product_id`, `plan_id`, `license_key`, `validity`, `price`, `source`, `payment_type`, `promo_code`, `discount_applied`) VALUES (?, ?, ?, ?, ?, ?, 'MAIN', ?, ?, ?)");
        foreach ($generatedKeys as $key) {
            $stmtInsert->execute([$user['id'], (int)$plan['real_product_id'], $planId, (string)$key, $productFullName, $priceLogged, $payLabel, $promoCode ?: null, $promoDiscountPercent]);
        }

        $pdo->commit();

        recordLiveActivity($pdo, $user['username'], 'purchase', 'Purchased ' . $productFullName, $totalCostBdt);
        ffSpinAddSpend($pdo, $settings, (int)$user['id'], (float)$totalCostBdt);

        echo json_encode([
            'status'       => 'success',
            'message'      => 'Payment verified & License delivered!',
            'keys'         => $generatedKeys,
            'product_name' => $productFullName,
            'payment_type' => $payLabel
        ]);
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Delivery failed: ' . $e->getMessage()]);
        exit;
    }
}

// D. ADD FUND PROCESSOR (BDT, USDT LOGIC ONLY)
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'verify_add_fund') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    if (!$isLoggedIn || $user['id'] <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please login to deposit!']);
        exit;
    }

    $amountBdt  = (float)($_POST['amount'] ?? 0);
    $trxId      = strtoupper(trim($_POST['trx_id'] ?? ''));
    $methodKey  = strtolower(trim($_POST['method_key'] ?? 'bdt'));
    $gatewayKey = strtolower(trim($_POST['gateway_key'] ?? 'bkash'));
    $methodId   = (int)($_POST['method_id'] ?? 0);
    $senderNum  = trim($_POST['sender_num'] ?? '');

    if ($amountBdt <= 0 || empty($trxId) || strlen($trxId) < 4) {
        echo json_encode(['status' => 'error', 'message' => 'Valid Amount and TrxID are required!']);
        exit;
    }

    $trxBlockMsg = ffTrxBlockedMessage($pdo, $trxId);
    if ($trxBlockMsg !== '') {
        echo json_encode(['status' => 'error', 'message' => $trxBlockMsg]);
        exit;
    }

    $methodRow = null;
    if ($methodId > 0) {
        $stmtM = $pdo->prepare("SELECT * FROM `payment_methods` WHERE `id` = ? LIMIT 1");
        $stmtM->execute([$methodId]);
        $methodRow = $stmtM->fetch(PDO::FETCH_ASSOC);
    }
    
    $methodName = 'BDT Gateway';
    if ($methodKey === 'usdt') $methodName = 'Binance / USDT Gateway';
    elseif ($methodRow) $methodName = $methodRow['name'];

    $isAutoEnabled = skypayAutoOn($settings);

    if ($isAutoEnabled) {
        $gatewayMethod = ($methodKey === 'usdt') ? ('usdt ' . $gatewayKey) : $gatewayKey;
        $verRes = skypayVerifyTrx($settings, $trxId, $gatewayMethod, (float)$amountBdt, (string)($user['username'] ?? 'Customer'), ['user_id' => (int)$user['id'], 'type' => 'deposit']);
        
        ffSkypayLog($pdo, (int)$user['id'], $trxId, (float)$amountBdt, $verRes);
        if ($verRes['status'] === 'success') {
            ffTrxAttemptClear($pdo, $trxId);
            // credit only the real received taka (never more than what the user typed)
            $gwAmount  = (float)($verRes['amount'] ?? 0);
            $creditBdt = ($gwAmount > 0) ? min($gwAmount, (float)$amountBdt) : (float)$amountBdt;
            $creditUsd = round($creditBdt / $effectiveBdtRate, 2);

            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE `users` SET `balance` = `balance` + ? WHERE `id` = ?")->execute([$creditUsd, $user['id']]);
                $pdo->prepare("INSERT INTO `transactions` (`user_id`, `method`, `amount`, `bonus_amount`, `sender_num`, `trx_id`, `status`) VALUES (?, ?, ?, 0.00, ?, ?, 'approved')")
                    ->execute([$user['id'], $methodName, $creditUsd, $verRes['sender'] ?: 'Auto Verified', $trxId]);
                ffSetLastTrxBdt($pdo, (float)$creditBdt);
                $pdo->commit();

                recordLiveActivity($pdo, $user['username'], 'deposit', 'Added funds via ' . $methodName, $creditBdt);

                echo json_encode([
                    'status'  => 'success',
                    'amount'  => number_format($creditBdt, 0),
                    'message' => '৳' . number_format($creditBdt, 0) . ' BDT has been credited to your balance!'
                ]);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Database error during deposit.']);
                exit;
            }
        } else {
            if (!empty($verRes['duplicate'])) {
                echo json_encode(['status' => 'error', 'message' => $verRes['message']]);
                exit;
            }
            $failMsg = $verRes['message'] ?? 'Gateway verification failed!';
            $failTry = ffTrxAttemptFail($pdo, $trxId, (int)$user['id'], $failMsg);
            if ($failTry <= FF_TRX_MAX_AUTO_TRIES) {
                echo json_encode(['status' => 'error', 'message' => $failMsg . ' (Try ' . $failTry . '/' . FF_TRX_MAX_AUTO_TRIES . ' — if it fails again, your TrxID will be sent to admin for manual approval.)']);
                exit;
            }
            // more than 2 failed tries -> send to admin panel as manual deposit request
            $ffGoManual = true;
            ffTrxAttemptClear($pdo, $trxId);
        }
    }
    if (!$isAutoEnabled || !empty($ffGoManual)) {
        $amountUsd = round($amountBdt / $effectiveBdtRate, 2);
        try {
            $pdo->prepare("INSERT INTO `transactions` (`user_id`, `method`, `amount`, `bonus_amount`, `sender_num`, `trx_id`, `status`) VALUES (?, ?, ?, 0.00, ?, ?, 'pending')")
                ->execute([$user['id'], $methodName, $amountUsd, $senderNum ?: 'N/A', $trxId]);
            ffSetLastTrxBdt($pdo, (float)$amountBdt);

            recordLiveActivity($pdo, $user['username'], 'deposit', 'Deposit Request (' . $methodName . ')', $amountBdt);

            echo json_encode([
                'status'  => 'pending_submitted',
                'amount'  => number_format($amountBdt, 0),
                'message' => (!empty($ffGoManual) ? 'Gateway could not verify your TrxID. ' : '') . 'Deposit request of ৳' . number_format($amountBdt, 0) . ' submitted to admin for verification!'
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => 'Submission failed: ' . $e->getMessage()]);
            exit;
        }
    }
}

// ====================================================================
// 8. WALLET BALANCE & COINS PURCHASE HANDLER
// ====================================================================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'live_generate') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    if (!$isLoggedIn || $user['id'] <= 0) {
        echo json_encode(['status' => 'error', 'require_login' => true, 'message' => 'Please login to complete purchase!']);
        exit;
    }

    if ($isSiteMaintenance) {
        echo json_encode(['status' => 'error', 'message' => 'Store is currently under maintenance. Orders are paused.']);
        exit;
    }

    $planId      = (int)($_POST['plan_id'] ?? 0);
    $quantity    = max(1, min(50, (int)($_POST['quantity'] ?? 1)));
    $paymentType = trim($_POST['payment_type'] ?? 'balance');
    $promoCode   = strtoupper(trim($_POST['promo_code'] ?? ''));

    if ($planId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a valid plan!']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT p.id as real_product_id, p.name as product_name, p.is_active, 
                                  COALESCE(NULLIF(pl.plan_title, ''), NULLIF(pl.validity, ''), 'Standard Plan') as plan_title,
                                  pl.reseller_price, pl.user_price, pl.price, pl.regular_price,
                                  pl.reseller_discount_percent, COALESCE(pl.discount_percent, 0) as discount_percent,
                                  pl.api_plan_id, pl.reward_coins, pl.coin_price 
                           FROM `product_plans` pl 
                           JOIN `products` p ON pl.product_id = p.id 
                           WHERE pl.id = ? 
                             AND (p.is_deleted = 0 OR p.is_deleted IS NULL)
                           LIMIT 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan || (int)($plan['is_active'] ?? 1) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'This product plan is currently unavailable!']);
        exit;
    }

    $productFullName    = $plan['product_name'] . ' (' . $plan['plan_title'] . ')';
    $rewardCoinsPerItem = max(0, (int)($plan['reward_coins'] ?? 0));
    $coinPricePerItem   = max(0, (int)($plan['coin_price'] ?? 0));
    $totalRewardCoins   = $rewardCoinsPerItem * $quantity;

    $rawRetailPrice = (float)(ffCustomerBasePrice($plan) ?: (($plan['price'] ?? 0) ?: ($plan['reseller_price'] ?? 0)));
    $rawResellPrice = (float)(($plan['reseller_price'] ?? 0) ?: (($plan['price'] ?? 0) ?: ($plan['user_price'] ?? 0)));
    $basePrice      = ($userRole === 'reseller') ? ($rawResellPrice > 0 ? $rawResellPrice : $rawRetailPrice) : ($rawRetailPrice > 0 ? $rawRetailPrice : $rawResellPrice);

    $unitPrice = null;
    if (isset($userCustomPrices[$planId]) && (float)$userCustomPrices[$planId] > 0) {
        $unitPrice = (float)$userCustomPrices[$planId];
    } else {
        $explicitDiscount = ($userRole === 'reseller') 
            ? (float)($plan['reseller_discount_percent'] ?? 0) 
            : (float)($plan['discount_percent'] ?? 0);

        if ($explicitDiscount > 0) {
            $unitPrice = round($basePrice * (1 - ($explicitDiscount / 100)), 2);
        } else {
            $unitPrice = $basePrice;
        }
    }

    $promoDiscountPercent = 0.00;
    if (!empty($promoCode) && $paymentType !== 'coins') {
        $stmtP = $pdo->prepare("SELECT `discount_percent` FROM `promo_codes` WHERE UPPER(`code`) = ? AND `is_active` = 1 LIMIT 1");
        $stmtP->execute([$promoCode]);
        $promoDiscountPercent = (float)$stmtP->fetchColumn();
        if ($promoDiscountPercent > 0) {
            $unitPrice = round($unitPrice * (1 - ($promoDiscountPercent / 100)), 2);
            $pdo->prepare("UPDATE `promo_codes` SET `used_count` = `used_count` + 1 WHERE UPPER(`code`) = ?")->execute([$promoCode]);
        }
    }

    $totalCost = $unitPrice * $quantity;

    if ($paymentType === 'coins') {
        if ($coinPricePerItem <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'This plan cannot be purchased with coins!']);
            exit;
        }

        $totalCoinsNeeded = $coinPricePerItem * $quantity;
        $stmtUserCoins = $pdo->prepare("SELECT `coins` FROM `users` WHERE `id` = ? LIMIT 1");
        $stmtUserCoins->execute([$user['id']]);
        $currentCoins = (int)$stmtUserCoins->fetchColumn();

        if ($currentCoins < $totalCoinsNeeded) {
            echo json_encode([
                'status'  => 'error', 
                'message' => 'Insufficient coins! Required: ' . number_format($totalCoinsNeeded) . ' | You have: ' . number_format($currentCoins)
            ]);
            exit;
        }
    } else {
        $stmtBal = $pdo->prepare("SELECT `balance` FROM `users` WHERE `id` = ? LIMIT 1");
        $stmtBal->execute([$user['id']]);
        $currentBalance = (float)$stmtBal->fetchColumn();

        if ($currentBalance < $totalCost) {
            $costBdt   = round($totalCost * $effectiveBdtRate, 0);
            $walletBdt = round($currentBalance * $effectiveBdtRate, 0);
            echo json_encode([
                'status'               => 'error', 
                'insufficient_balance' => true,
                'message'              => 'Insufficient wallet balance! Required: ৳' . number_format($costBdt, 0) . ' BDT | Wallet: ৳' . number_format($walletBdt, 0) . ' BDT.'
            ]);
            exit;
        }
    }

    $generatedKeys = [];
    $cleanQty      = (int)$quantity;

    $stmtStock = $pdo->prepare("SELECT `id`, `license_key` FROM `stock_keys` WHERE `plan_id` = ? AND `status` = 'available' ORDER BY `id` ASC LIMIT $cleanQty");
    $stmtStock->execute([$planId]);
    $stockRows = $stmtStock ? $stmtStock->fetchAll(PDO::FETCH_ASSOC) : [];

    if (count($stockRows) === $cleanQty) {
        $stockIds = array_column($stockRows, 'id');
        $inClause = implode(',', array_map('intval', $stockIds));
        $pdo->exec("UPDATE `stock_keys` SET `status` = 'used' WHERE `id` IN ($inClause)");
        $generatedKeys = array_column($stockRows, 'license_key');
    } else {
        $apiPlanId   = (int)($plan['api_plan_id'] ?? 0);
        $targetApiId = $apiPlanId > 0 ? $apiPlanId : $planId;

        $apiResult = generateFromExternalProvider($targetApiId, $quantity);

        if ($apiResult['status'] === 'success' && !empty($apiResult['keys'])) {
            $generatedKeys = (array)$apiResult['keys'];
        } else {
            echo json_encode([
                'status'  => 'error', 
                'message' => 'Out of stock! This product is currently unavailable. No balance was deducted.'
            ]);
            exit;
        }
    }

    $pdo->beginTransaction();
    try {
        if ($paymentType === 'coins') {
            $stmtDeductCoins = $pdo->prepare("UPDATE `users` SET `coins` = `coins` - ? WHERE `id` = ? AND `coins` >= ?");
            $stmtDeductCoins->execute([$totalCoinsNeeded, $user['id'], $totalCoinsNeeded]);

            if ($stmtDeductCoins->rowCount() === 0) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Insufficient coins during checkout!']);
                exit;
            }
        } else {
            $stmtDeduct = $pdo->prepare("UPDATE `users` SET `balance` = `balance` - ?, `coins` = `coins` + ? WHERE `id` = ? AND `balance` >= ?");
            $stmtDeduct->execute([$totalCost, $totalRewardCoins, $user['id'], $totalCost]);

            if ($stmtDeduct->rowCount() === 0) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Insufficient balance during checkout!']);
                exit;
            }
        }

        $payLabel    = ($paymentType === 'coins') ? 'coins' : 'balance';
        $priceLogged = ($paymentType === 'coins') ? 0.00 : $unitPrice;

        $stmtInsert = $pdo->prepare("INSERT INTO `licenses` (`user_id`, `product_id`, `plan_id`, `license_key`, `validity`, `price`, `source`, `payment_type`, `promo_code`, `discount_applied`) VALUES (?, ?, ?, ?, ?, ?, 'MAIN', ?, ?, ?)");
        foreach ($generatedKeys as $key) {
            $stmtInsert->execute([$user['id'], (int)$plan['real_product_id'], $planId, (string)$key, $productFullName, $priceLogged, $payLabel, $promoCode ?: null, $promoDiscountPercent]);
        }

        $pdo->commit();

        $stmtNewBal = $pdo->prepare("SELECT `balance`, `coins` FROM `users` WHERE `id` = ?");
        $stmtNewBal->execute([$user['id']]);
        $refreshed = $stmtNewBal->fetch(PDO::FETCH_ASSOC);

        $newBalBdt   = round((float)$refreshed['balance'] * $effectiveBdtRate, 0);
        $newCoinsVal = (int)($refreshed['coins'] ?? 0);
        $totalBdtCost = round($totalCost * $effectiveBdtRate, 0);

        recordLiveActivity($pdo, $user['username'], 'purchase', 'Purchased ' . $productFullName, $totalBdtCost);
        if ($paymentType !== 'coins') {
            ffSpinAddSpend($pdo, $settings, (int)$user['id'], (float)$totalBdtCost);
        }

        echo json_encode([
            'status'            => 'success',
            'message'           => 'License generated successfully!',
            'keys'              => $generatedKeys,
            'product_name'      => $productFullName,
            'remaining_balance' => number_format($newBalBdt, 0),
            'remaining_coins'   => number_format($newCoinsVal),
            'reward_coins'      => $totalRewardCoins,
            'payment_type'      => $paymentType
        ]);
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Order failed: ' . $e->getMessage()]);
        exit;
    }
}

// ====================================================================
// 9. HWID RESET AJAX HANDLER (Requires Plan & Key)
// ====================================================================
// Supported HWID reset products (keymanager.shop api_id => label)
if (!function_exists('ffResetProducts')) {
    function ffResetProducts(): array {
        return [
            2  => 'DRIPCLIENT',
            4  => 'FLUORITE',
            9  => 'Haxxcker Reset',
            3  => 'HGCHEATS',
            11 => 'Silent Cheats APKMOD',
            12 => 'Silent Cheats Root',
            99 => 'Angry Mod (Pinxt)',
        ];
    }
}
// Detect reset api_id from product name (+ plan title for Silent Cheats)
if (!function_exists('ffResetApiIdFromName')) {
    function ffResetApiIdFromName(string $productName, string $extra = ''): int {
        $n = strtolower(preg_replace('/[^a-z0-9]/i', '', $productName));
        $f = strtolower(preg_replace('/[^a-z0-9]/i', '', $productName . ' ' . $extra));
        if (strpos($n, 'silent') !== false) {
            if (strpos($f, 'apk') !== false)  return 11;
            if (strpos($f, 'root') !== false) return 12;
            return 0;
        }
        if (strpos($n, 'angry') !== false || strpos($n, 'pinxt') !== false) return 99;
        if (strpos($n, 'drip') !== false)     return 2;
        if (strpos($n, 'fluorite') !== false) return 4;
        if (strpos($n, 'haxxcker') !== false || strpos($n, 'haxcker') !== false) return 9;
        if (strpos($n, 'hgcheat') !== false)  return 3;
        return 0;
    }
}

if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'reset_key') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    if (!$isLoggedIn || $user['id'] <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please login to reset your key!']);
        exit;
    }

    $rawKey     = trim($_POST['license_key'] ?? '');
    $resetApiId = (int)($_POST['api_id'] ?? 0);
    $resetList  = ffResetProducts();

    if ($rawKey === '' || !isset($resetList[$resetApiId])) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a product and enter a license key!']);
        exit;
    }

    // If this key belongs to the user's account and admin stopped it, block reset
    $keyRow = null;
    try {
        $stmtKey = $pdo->prepare("SELECT * FROM `licenses` WHERE `user_id` = ? AND `license_key` = ? LIMIT 1");
        $stmtKey->execute([$user['id'], $rawKey]);
        $keyRow = $stmtKey->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {}

    if ($keyRow && ($keyRow['status'] ?? 'active') === 'stopped') {
        echo json_encode(['status' => 'error', 'message' => 'This key has been stopped by admin. Please contact support.']);
        exit;
    }

    $resetParams = [
        'action'  => 'reset_key',
        'api_id'  => $resetApiId,
        'key'     => $rawKey,
    ];
    $resetParams['api_key'] = 'keymanager_live_0f18492c550d2e3a350c821604de9e7a';

    // anti-spam: one reset per key every 30 seconds (provider limit 100/day)
    $rsLock = sys_get_temp_dir() . '/ff_reset_' . md5($resetApiId . '|' . $rawKey) . '.lock';
    if (file_exists($rsLock) && (time() - (int)@filemtime($rsLock)) < 30) {
        echo json_encode(['status' => 'error', 'message' => 'Please wait 30 seconds before resetting this key again.']);
        exit;
    }
    @touch($rsLock);

    $resetUrl = 'https://keymanager.shop/api.php?' . http_build_query($resetParams);

    $ch = curl_init($resetUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $resetRaw  = curl_exec($ch);
    $resetErr  = curl_error($ch);
    curl_close($ch);

    if ($resetErr || $resetRaw === false || $resetRaw === '') {
        @unlink($rsLock);
        echo json_encode(['status' => 'error', 'message' => 'Reset server not responding. Please try again later.']);
        exit;
    }

    $resetRes = json_decode(trim((string)$resetRaw), true);
    $okWords  = ['success', 'ok', 'true', '1', 'done', 'reset', 'completed'];
    $resetOk  = false;
    $resetMsg = '';
    if (is_array($resetRes)) {
        foreach (['status', 'success', 'ok', 'result'] as $sk) {
            if (!array_key_exists($sk, $resetRes)) continue;
            $sv = $resetRes[$sk];
            if ($sv === true || $sv === 1 || (is_string($sv) && in_array(strtolower(trim($sv)), $okWords, true))) { $resetOk = true; break; }
            if ($sv === false || $sv === 0 || (is_string($sv) && in_array(strtolower(trim($sv)), ['error', 'failed', 'fail', 'false', '0'], true))) break;
        }
        if (!empty($resetRes['error'])) $resetOk = false;
        foreach (['message', 'msg', 'error', 'detail', 'data'] as $mk) {
            if (isset($resetRes[$mk]) && is_string($resetRes[$mk]) && trim($resetRes[$mk]) !== '') { $resetMsg = trim($resetRes[$mk]); break; }
        }
    } else {
        // plain text reply
        $txt = trim(strip_tags((string)$resetRaw));
        $resetOk  = (bool)preg_match('/\b(success|successfully|reset done|has been reset)\b/i', $txt) && !preg_match('/\b(error|fail|invalid|not found)\b/i', $txt);
        $resetMsg = mb_substr($txt, 0, 200);
    }

    if ($resetOk) {
        if ($keyRow) {
            try {
                $pdo->prepare("UPDATE `licenses` SET `updated_at` = NOW() WHERE `id` = ?")->execute([$keyRow['id']]);
            } catch (Throwable $e) {}
        }
        echo json_encode(['status' => 'success', 'message' => $resetMsg !== '' ? $resetMsg : 'Hardware ID successfully reset!']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => $resetMsg !== '' ? $resetMsg : 'Key reset failed. Please try again or contact support.']);
    exit;
}

// ====================================================================
// 9B. FETCH KEYS FOR A PLAN (for Reset Key auto-suggest)
// ====================================================================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'get_plan_keys') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    if (!$isLoggedIn || $user['id'] <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please login first.']);
        exit;
    }

    $planId = (int)($_POST['plan_id'] ?? 0);
    if ($planId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid plan.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT `license_key`, `validity`, `created_at` FROM `licenses` WHERE `user_id` = ? AND `plan_id` = ? ORDER BY `id` DESC LIMIT 50");
        $stmt->execute([$user['id'], $planId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'keys' => $rows]);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'Fetch failed.']);
    }
    exit;
}

// ====================================================================
// 10. SPIN & WIN WHEEL ENGINE
// ====================================================================

// ====================================================================
// PAYMENT METHODS (live feed - admin add/edit shows instantly on storefront)
// ====================================================================
function ffLoadPaymentMethods(PDO $pdo): array {
    $out = [];
    try {
        $st = $pdo->query("SELECT * FROM `payment_methods` WHERE `is_active` = 1 ORDER BY `sort_order` ASC, `id` ASC");
        $rows = ($st && is_object($st)) ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            $hay = strtolower(($r['name'] ?? '') . ' ' . ($r['method_key'] ?? '') . ' ' . ($r['account_type'] ?? ''));
            $isUsdt = (bool)preg_match('/binance|usdt|pay\s*id|trc20|bep20|crypto|tether/', $hay);
            $r['category']       = $isUsdt ? 'usdt' : 'bdt';
            $r['account_type']   = trim((string)($r['account_type'] ?? '')) !== '' ? trim($r['account_type']) : 'Personal';
            $r['allow_add_fund'] = isset($r['allow_add_fund']) ? (int)$r['allow_add_fund'] : 1;
            $r['allow_checkout'] = isset($r['allow_checkout']) ? (int)$r['allow_checkout'] : 1;
            foreach (['logo_url', 'banner_url', 'qr_image_url', 'instructions'] as $k) {
                $r[$k] = isset($r[$k]) && $r[$k] !== null ? (string)$r[$k] : '';
            }
            $out[] = $r;
        }
    } catch (Throwable $e) {}
    return $out;
}

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_payment_methods') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode(['status' => 'success', 'data' => ffLoadPaymentMethods($pdo)], JSON_UNESCAPED_UNICODE);
    exit;
}


// ====================================================================
// COIN DATA (live feed for profile / referral page - admin changes show instantly)
// ====================================================================
function ffCoinPayload(PDO $pdo, array $settings, int $userId, float $rate): array {
    $coinOn  = (($settings['referral_coin_enabled'] ?? '1') === '1');
    $bdtAmt  = max(0.0, (float)($settings['referral_reward_amount'] ?? 5.00));
    $bdtOn   = (($settings['referral_bdt_enabled'] ?? '1') === '1') && $bdtAmt > 0;
    $out = [
        'coin_on'    => $coinOn,
        'bdt_on'     => $bdtOn,
        'bdt_amount' => $bdtAmt,
        'program_on' => (($settings['referral_system_status'] ?? '1') === '1'),
        'coins'      => 0,
        'per_refer'  => 0,
        'best_rate'  => 0,
        'packs'      => [],
    ];
    try {
        if ($userId > 0) {
            $st = $pdo->prepare("SELECT `coins` FROM `users` WHERE `id` = ? LIMIT 1");
            $st->execute([$userId]);
            $out['coins'] = (int)$st->fetchColumn();
        }
        $out['per_refer'] = ffRefCoinsPerReferral($pdo);
        $rows = $pdo->query("SELECT `id`,`title`,`coins`,`taka` FROM `referral_coin_rules` WHERE `rule_type` = 'convert' AND `is_active` = 1 ORDER BY `coins` ASC, `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $c = (int)$r['coins']; $t = (float)$r['taka'];
            if ($c <= 0 || $t <= 0) continue;
            $per = $t / $c;
            if ($per > $out['best_rate']) $out['best_rate'] = round($per, 4);
            $out['packs'][] = [
                'id' => (int)$r['id'], 'title' => (string)$r['title'],
                'coins' => $c, 'taka' => $t, 'per_coin' => round($per, 4),
                'usd' => round($t / max(1.0, $rate), 2),
            ];
        }
    } catch (Throwable $e) {}
    return $out;
}

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_coin_data') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    // re-read settings fresh so admin toggles show instantly
    try {
        $fresh = $pdo->query("SELECT `setting_key`, `setting_value` FROM `system_settings`")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($fresh)) { $settings = array_merge($settings, $fresh); }
    } catch (Throwable $e) {}
    $uidNow = ($isLoggedIn && (int)$user['id'] > 0) ? (int)$user['id'] : 0;
    $pl = ffCoinPayload($pdo, $settings, $uidNow, (float)$effectiveBdtRate);
    $pl['status'] = 'success';
    echo json_encode($pl, JSON_UNESCAPED_UNICODE);
    exit;
}

// COIN -> WALLET CONVERSION (packs set in Admin > Refer & Earn)
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'convert_coins') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    if (!$isLoggedIn || $user['id'] <= 0) {
        echo json_encode(['status' => 'error', 'require_login' => true, 'message' => 'Please login first!']);
        exit;
    }

    $ruleId = (int)($_POST['rule_id'] ?? 0);
    try {
        $stSet = $pdo->query("SELECT `setting_value` FROM `system_settings` WHERE `setting_key` = 'referral_coin_enabled' LIMIT 1");
        $coinFlag = $stSet ? $stSet->fetchColumn() : false;
        if ($coinFlag !== false && (string)$coinFlag !== '1') {
            echo json_encode(['status' => 'error', 'message' => 'Coin system is currently turned off.']);
            exit;
        }
        $stR = $pdo->prepare("SELECT * FROM `referral_coin_rules` WHERE `id` = ? AND `rule_type` = 'convert' AND `is_active` = 1 LIMIT 1");
        $stR->execute([$ruleId]);
        $rule = $stR->fetch(PDO::FETCH_ASSOC);
        if (!$rule || (int)$rule['coins'] <= 0 || (float)$rule['taka'] <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'This conversion pack is not available.']);
            exit;
        }

        $needCoins = (int)$rule['coins'];
        $takaGet   = (float)$rule['taka'];
        $usdGet    = round($takaGet / $effectiveBdtRate, 4);
        $uid       = (int)$user['id'];

        $pdo->beginTransaction();
        $stU = $pdo->prepare("UPDATE `users` SET `coins` = `coins` - ?, `balance` = `balance` + ? WHERE `id` = ? AND `coins` >= ?");
        $stU->execute([$needCoins, $usdGet, $uid, $needCoins]);
        if ($stU->rowCount() !== 1) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Not enough coins! Required: ' . number_format($needCoins)]);
            exit;
        }
        $pdo->prepare("INSERT INTO `transactions` (`user_id`, `method`, `amount`, `bonus_amount`, `sender_num`, `trx_id`, `status`) VALUES (?, 'Coin Convert', ?, 0.00, ?, ?, 'approved')")
            ->execute([$uid, $usdGet, $needCoins . ' Coins', 'COIN-' . strtoupper(uniqid())]);
        $pdo->commit();

        $stNb = $pdo->prepare("SELECT `balance`, `coins` FROM `users` WHERE `id` = ? LIMIT 1");
        $stNb->execute([$uid]);
        $nb = $stNb->fetch(PDO::FETCH_ASSOC) ?: ['balance' => 0, 'coins' => 0];

        echo json_encode([
            'status'      => 'success',
            'message'     => $needCoins . ' coins → ৳' . number_format($takaGet, 0) . ' wallet এ যোগ হয়েছে!',
            'new_usd'     => round((float)$nb['balance'], 2),
            'new_bdt'     => round((float)$nb['balance'] * $effectiveBdtRate, 0),
            'new_coins'   => (int)$nb['coins']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $e2) {}
        echo json_encode(['status' => 'error', 'message' => 'Conversion failed, please try again.']);
        exit;
    }
}

if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'get_spin_data') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    try {
        $cfg = ffSpinConfig($settings);
        ffEnsureSpinTables($pdo);

        $spins = 0; $progress = 0.0; $refProg = 0; $dailyLeft = $cfg['daily'];
        if ($isLoggedIn) {
            $st = $pdo->prepare("SELECT `spins_available`, `spend_progress_bdt`, `ref_progress` FROM `user_spin_wallet` WHERE `user_id` = ? LIMIT 1");
            $st->execute([$user['id']]);
            $w = $st->fetch(PDO::FETCH_ASSOC);
            if ($w) {
                $spins    = (int)$w['spins_available'];
                $progress = (float)$w['spend_progress_bdt'];
                $refProg  = (int)$w['ref_progress'];
            }
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM `user_spin_log` WHERE `user_id` = ? AND DATE(`created_at`) = CURDATE()");
            $stmtCount->execute([$user['id']]);
            $dailyLeft = max(0, $cfg['daily'] - (int)$stmtCount->fetchColumn());
        }

        echo json_encode([
            'status'          => 'success',
            'enabled'         => $cfg['enabled'],
            'logged_in'       => $isLoggedIn,
            'segments'        => ffSpinWheelLabels($cfg),
            'remaining_spins' => $spins,
            'daily_left'      => $dailyLeft,
            'spend_per_spin'  => $cfg['per_spin'],
            'spend_progress'  => $progress,
            'prize_min'       => $cfg['min'],
            'prize_max'       => $cfg['max'],
            'refer_enabled'   => $cfg['ref_on'],
            'refer_per_spin'  => $cfg['ref_per'],
            'refer_progress'  => $refProg
        ]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'spin_wheel') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    if (!$isLoggedIn || $user['id'] <= 0) {
        echo json_encode(['status' => 'error', 'require_login' => true, 'message' => 'Please login to spin!']);
        exit;
    }

    $cfg = ffSpinConfig($settings);
    if (!$cfg['enabled']) {
        echo json_encode(['status' => 'error', 'message' => 'Lucky Spin is currently turned off.']);
        exit;
    }

    ffEnsureSpinTables($pdo);
    $uid = (int)$user['id'];

    try {
        $pdo->prepare("INSERT IGNORE INTO `user_spin_wallet` (`user_id`) VALUES (?)")->execute([$uid]);
        $pdo->beginTransaction();

        $st = $pdo->prepare("SELECT `spins_available` FROM `user_spin_wallet` WHERE `user_id` = ? FOR UPDATE");
        $st->execute([$uid]);
        $spinsLeft = (int)$st->fetchColumn();

        if ($spinsLeft <= 0) {
            $pdo->rollBack();
            $msg = 'আপনার কোনো স্পিন নেই! ৳' . number_format($cfg['per_spin'], 0) . ' টাকার প্রোডাক্ট কিনলে ১টি স্পিন পাবেন।';
            if ($cfg['ref_on']) {
                $msg .= ' অথবা ' . $cfg['ref_per'] . ' জনকে রেফার করুন।';
            }
            echo json_encode(['status' => 'error', 'no_spin' => true, 'message' => $msg]);
            exit;
        }

        $stDay = $pdo->prepare("SELECT COUNT(*) FROM `user_spin_log` WHERE `user_id` = ? AND DATE(`created_at`) = CURDATE()");
        $stDay->execute([$uid]);
        if ((int)$stDay->fetchColumn() >= $cfg['daily']) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'আজকের স্পিন লিমিট শেষ! কাল আবার চেষ্টা করুন।']);
            exit;
        }

        // Random prize between admin min..max (BDT)
        $prizeBdt = random_int($cfg['min'], $cfg['max']);
        $prizeUsd = round($prizeBdt / $effectiveBdtRate, 4);

        $pdo->prepare("UPDATE `user_spin_wallet` SET `spins_available` = `spins_available` - 1, `total_used` = `total_used` + 1 WHERE `user_id` = ?")->execute([$uid]);
        $pdo->prepare("UPDATE `users` SET `balance` = `balance` + ? WHERE `id` = ?")->execute([$prizeUsd, $uid]);
        $pdo->prepare("INSERT INTO `transactions` (`user_id`, `method`, `amount`, `bonus_amount`, `sender_num`, `trx_id`, `status`) VALUES (?, 'Spin Reward', ?, 0.00, 'Lucky Spin', ?, 'approved')")
            ->execute([$uid, $prizeUsd, 'SPIN-' . strtoupper(uniqid())]);
        $pdo->prepare("INSERT INTO `user_spin_log` (`user_id`, `prize_bdt`) VALUES (?, ?)")->execute([$uid, $prizeBdt]);

        $pdo->commit();

        // pick wheel slice nearest to the real prize
        $labels = ffSpinWheelLabels($cfg);
        $bestDiff = PHP_INT_MAX; $cands = [];
        foreach ($labels as $i => $l) {
            $d = abs((int)$l['bonus_amount'] - $prizeBdt);
            if ($d < $bestDiff) { $bestDiff = $d; $cands = [$i]; }
            elseif ($d === $bestDiff) { $cands[] = $i; }
        }
        $wonIndex = $cands[array_rand($cands)];

        $stB = $pdo->prepare("SELECT `balance` FROM `users` WHERE `id` = ? LIMIT 1");
        $stB->execute([$uid]);
        $newBalBdt = round((float)$stB->fetchColumn() * $effectiveBdtRate, 0);

        echo json_encode([
            'status'             => 'success',
            'winning_index'      => $wonIndex,
            'title'              => '৳' . $prizeBdt,
            'prize_bdt'          => $prizeBdt,
            'is_win'             => true,
            'spins_left'         => $spinsLeft - 1,
            'new_balance_bdt'    => $newBalBdt,
            'bengali_offer_text' => 'অভিনন্দন! আপনি ৳' . $prizeBdt . ' জিতেছেন — টাকা ওয়ালেটে যোগ হয়ে গেছে!'
        ]);
        exit;
    } catch (Throwable $e) {
        try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $e2) {}
        echo json_encode(['status' => 'error', 'message' => 'Spin failed, please try again.']);
        exit;
    }
}

// ====================================================================
// 11. PROFILE UPDATE ACTION
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!$isLoggedIn) {
        $_SESSION['flash_toast'] = ['type' => 'error', 'msg' => 'Please login to perform this action!'];
        header('Location: index.php');
        exit;
    }

    $_SESSION['active_page'] = 'profile';
    $newUsername = trim($_POST['username'] ?? '');
    $newEmail    = strtolower(trim($_POST['email'] ?? ''));
    $currPass    = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';

    if (password_verify($currPass, $user['password']) || $currPass === $user['password']) {
        if (!empty($newUsername) && strlen($newUsername) >= 3) {
            $stmtChk = $pdo->prepare("SELECT `id` FROM `users` WHERE `username` = ? AND `id` != ? LIMIT 1");
            $stmtChk->execute([$newUsername, $user['id']]);
            if ($stmtChk->fetchColumn()) {
                $_SESSION['flash_toast'] = ['type' => 'error', 'msg' => 'Username already taken!'];
                header('Location: index.php');
                exit;
            }

            $updateSql = "UPDATE `users` SET `username` = ?, `email` = ?";
            $params    = [$newUsername, $newEmail ?: null];

            if (!empty($newPass) && strlen($newPass) >= 6) {
                $updateSql .= ", `password` = ?";
                $params[]   = password_hash($newPass, PASSWORD_DEFAULT);
            }

            $updateSql .= " WHERE `id` = ?";
            $params[]   = $user['id'];

            $pdo->prepare($updateSql)->execute($params);

            $_SESSION['auth_username'] = $newUsername;
            $_SESSION['flash_toast']   = ['type' => 'success', 'msg' => 'Profile updated successfully!'];
        }
    } else {
        $_SESSION['flash_toast'] = ['type' => 'error', 'msg' => 'Current password incorrect!'];
    }
    header('Location: index.php');
    exit;
}

// ====================================================================
// 12. HIGH SPEED CATALOG ENGINE & DATA RETRIEVAL
// ====================================================================
$activeBanners = [];
try {
    $stmtB = $pdo->query("SELECT * FROM `banners` 
        WHERE (`is_active` = 1 OR `is_active` = '1' OR `is_active` IS NULL OR `status` = 'active' OR `status` = '1' OR `status` IS NULL) 
        ORDER BY `sort_order` ASC, `id` DESC");
    $activeBanners = ($stmtB && is_object($stmtB)) ? $stmtB->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {}

if (empty($activeBanners)) {
    $activeBanners = [
        [
            'id'          => 1,
            'image_url'   => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?w=1200&q=80',
            'target_link' => '#',
            'title'       => 'How to buy panel for Indian users'
        ]
    ];
}

$activePaymentMethods = ffLoadPaymentMethods($pdo);

// ============================================================
// FIXED CATEGORY ORDER (server-side sorted by fixed priority)
// ============================================================
$availableCategories = [];
try {
    $stmtCats = $pdo->query("SELECT DISTINCT `category` FROM `products` 
        WHERE (`is_active` = 1 OR `is_active` IS NULL) 
          AND (`is_deleted` = 0 OR `is_deleted` IS NULL) 
          AND `category` IS NOT NULL AND TRIM(`category`) != ''");
    $dbCats = $stmtCats ? $stmtCats->fetchAll(PDO::FETCH_COLUMN) : [];
    if (!empty($dbCats)) {
        $dbCats = array_unique(array_map('strtoupper', array_map('trim', $dbCats)));

        // Fixed display order: NON ROOT → ROOT → I PHONE → PC → Other
        $fixedOrder = ['NON ROOT', 'ROOT', 'I PHONE', 'PC', 'OTHER'];
        $sorted = [];
        foreach ($fixedOrder as $f) {
            foreach ($dbCats as $db) {
                if (strtoupper($db) === $f) {
                    $sorted[] = $db;
                    break;
                }
            }
        }
        // Append any leftover categories (unlikely but safe)
        foreach ($dbCats as $db) {
            if (!in_array($db, $sorted)) $sorted[] = $db;
        }
        $availableCategories = $sorted;
    }
} catch (Throwable $e) {}

$catalogProducts = [];
try {
    $sql = "SELECT p.id as product_id, p.name as product_name, p.image_url, p.logo_url, p.banner_url, 
                   p.download_link, p.setup_link, p.description, p.tags, p.category, p.is_pinned, p.is_active, 
                   p.app_download_url, p.maintenance_message,
                   pl.id as plan_id, 
                   COALESCE(NULLIF(pl.plan_title, ''), NULLIF(pl.validity, ''), 'Standard Plan') as validity, 
                   pl.reseller_price, pl.user_price, pl.price, pl.regular_price, pl.reseller_discount_percent, 
                   COALESCE(pl.discount_percent, 0) as discount_percent, pl.api_plan_id,
                   pl.reward_coins, pl.coin_price 
            FROM `products` p
            LEFT JOIN `product_plans` pl ON pl.product_id = p.id 
                 AND (pl.is_deleted = 0 OR pl.is_deleted IS NULL)
            WHERE (p.is_deleted = 0 OR p.is_deleted IS NULL OR p.is_deleted = '0')
              AND (p.status != 'deleted' OR p.status IS NULL)
            ORDER BY p.is_pinned DESC, p.sort_order ASC, p.id ASC, pl.user_price ASC, pl.price ASC, pl.id ASC";
    $stmtProds = $pdo->query($sql);
    $dbProducts = ($stmtProds && is_object($stmtProds)) ? $stmtProds->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach ($dbProducts as $dp) {
        $pId    = (int)$dp['product_id'];
        $planId = (int)($dp['plan_id'] ?? 0);

        if ($userRole === 'reseller' && (int)($user['is_product_restricted'] ?? 0) === 1 && !empty($userAllowedProducts)) {
            if (!in_array($pId, array_map('intval', $userAllowedProducts), true)) {
                continue;
            }
        }

        $pName = trim($dp['product_name']);
        if (empty($pName)) continue;

        $cat = !empty($dp['category']) ? strtoupper(trim($dp['category'])) : 'GENERAL';
        
        $isProdActive = (int)($dp['is_active'] ?? 1) === 1;
        $globalMaintRules = !empty($settings['prod_maintenance_default_rules']) ? $settings['prod_maintenance_default_rules'] : "⚠️ Rules & Conditions:\nএই প্রোডাক্টটির সার্ভার আপগ্রেড চলছে। সাময়িক অসুবিধার জন্য আমরা দুঃখিত। খুব শীঘ্রই সার্ভিস চালু করা হবে।";
        $prodMaintenanceMsg = !empty($dp['maintenance_message']) ? $dp['maintenance_message'] : $globalMaintRules;

        $rawRetailUSD = (float)(ffCustomerBasePrice($dp) ?: (($dp['price'] ?? 0) ?: ($dp['reseller_price'] ?? 0)));
        $rawResellUSD = (float)(($dp['reseller_price'] ?? 0) ?: (($dp['price'] ?? 0) ?: ($dp['user_price'] ?? 0)));

        $regularBaseUSD = ($userRole === 'reseller')
            ? (($rawResellUSD > 0) ? $rawResellUSD : $rawRetailUSD)
            : (($rawRetailUSD > 0) ? $rawRetailUSD : $rawResellUSD);

        $finalPrice = $regularBaseUSD;
        $originalPriceUSD = $regularBaseUSD;
        $planDiscount = 0;

        $explicitDiscount = ($userRole === 'reseller')
            ? (float)($dp['reseller_discount_percent'] ?? 0)
            : (float)($dp['discount_percent'] ?? 0);

        if ($planId > 0 && isset($userCustomPrices[$planId]) && (float)$userCustomPrices[$planId] > 0) {
            $finalPrice = (float)$userCustomPrices[$planId];
        } elseif ($explicitDiscount > 0) {
            $planDiscount = (int)round($explicitDiscount);
            $finalPrice   = round($regularBaseUSD * (1 - ($planDiscount / 100)), 2);
            $originalPriceUSD = $regularBaseUSD;
        }

        if (!isset($catalogProducts[$pId])) {
            $catalogProducts[$pId] = [
                'id'               => $pId,
                'name'             => $pName,
                'category'         => $cat,
                'is_pinned'        => (int)($dp['is_pinned'] ?? 0),
                'is_active'        => $isProdActive,
                'maintenance_msg'  => $prodMaintenanceMsg,
                'logo_url'         => !empty($dp['logo_url']) ? $dp['logo_url'] : (!empty($dp['image_url']) ? $dp['image_url'] : ''),
                'banner_url'       => $dp['banner_url'] ?: $dp['image_url'],
                'description'      => $dp['description'] ?: 'Auto Delivery in 0.1 Seconds.',
                'tags'             => $dp['tags'] ?: '#EXTERNAL #SAFE',
                'download_link'    => $dp['download_link'] ?: $effectiveTutorialLink,
                'setup_link'       => $dp['setup_link'] ?: $effectiveTutorialLink,
                'app_download_url' => $dp['app_download_url'] ?: '',
                'max_discount'     => 0,
                'plans'            => []
            ];
        }

        if ($planId > 0) {
            if ($planDiscount > $catalogProducts[$pId]['max_discount']) {
                $catalogProducts[$pId]['max_discount'] = $planDiscount;
            }

            $catalogProducts[$pId]['plans'][] = [
                'plan_id'          => $planId,
                'api_plan_id'      => (int)($dp['api_plan_id'] ?? 0),
                'validity'         => $dp['validity'] ?? 'Standard',
                'price'            => $finalPrice,
                'original_usd'     => round($originalPriceUSD, 2),
                'price_bdt'        => round($finalPrice * $effectiveBdtRate, 0),
                'original_bdt'     => round($originalPriceUSD * $effectiveBdtRate, 0),
                'discount_percent' => $planDiscount,
                'reward_coins'     => (int)($dp['reward_coins'] ?? 0),
                'coin_price'       => (int)($dp['coin_price'] ?? 0)
            ];
        }
    }

    foreach ($catalogProducts as &$cp) {
        if (!empty($cp['plans'])) {
            usort($cp['plans'], function($a, $b) {
                return (float)$a['price_bdt'] <=> (float)$b['price_bdt'];
            });
        }
    }
    unset($cp);
} catch (Throwable $e) {
    $catalogProducts = [];
}

// User Keys, Deposits & Referral Stats Fetch
$keys          = [];
$deposits      = [];
$userReferrals = [];

if ($isLoggedIn) {
    try {
        $stmtKeys = $pdo->prepare("SELECT * FROM `licenses` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 50");
        $stmtKeys->execute([$user['id']]);
        $keys = $stmtKeys->fetchAll(PDO::FETCH_ASSOC);

        $stmtTrx = $pdo->prepare("SELECT * FROM `transactions` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 20");
        $stmtTrx->execute([$user['id']]);
        $deposits = $stmtTrx->fetchAll(PDO::FETCH_ASSOC);

        $stmtRefs = $pdo->prepare("SELECT r.*, u.username as ref_username FROM `referrals` r JOIN `users` u ON r.referred_user_id = u.id WHERE r.referrer_id = ? ORDER BY r.id DESC");
        $stmtRefs->execute([$user['id']]);
        $userReferrals = $stmtRefs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

// Live History
$topBuyers = [];
$recentOrders = [];
$feedbacks = [];

try {
    $stmtTop = $pdo->query("SELECT u.username, u.avatar_url, SUM(t.amount) as total_spent 
                            FROM `transactions` t 
                            JOIN `users` u ON t.user_id = u.id 
                            WHERE t.status = 'approved' AND t.method NOT LIKE '%Referral%' AND t.method NOT LIKE '%Refund%' AND t.method NOT LIKE '%Spin%' AND t.method NOT LIKE '%Coin%'
                            GROUP BY t.user_id 
                            ORDER BY total_spent DESC LIMIT 3");
    $topBuyers = $stmtTop ? $stmtTop->fetchAll(PDO::FETCH_ASSOC) : [];

    $stmtRecent = $pdo->query("SELECT t.user_id, u.username, u.avatar_url, t.method, t.amount, t.created_at, l.validity as product_name
                               FROM `transactions` t
                               JOIN `users` u ON t.user_id = u.id
                               LEFT JOIN `licenses` l ON l.user_id = t.user_id AND l.created_at >= t.created_at
                               WHERE t.status = 'approved' AND t.method NOT LIKE '%Referral%' AND t.method NOT LIKE '%Refund%' AND t.method NOT LIKE '%Spin%' AND t.method NOT LIKE '%Coin%'
                               GROUP BY t.id
                               ORDER BY t.id DESC LIMIT 5");
    $recentOrders = $stmtRecent ? $stmtRecent->fetchAll(PDO::FETCH_ASSOC) : [];

    $stmtFb = $pdo->query("SELECT f.*, u.username, u.avatar_url, p.name as product_name
                           FROM `feedbacks` f
                           JOIN `users` u ON f.user_id = u.id
                           JOIN `products` p ON f.product_id = p.id
                           ORDER BY f.id DESC LIMIT 5");
    $feedbacks = $stmtFb ? $stmtFb->fetchAll(PDO::FETCH_ASSOC) : [];

} catch (Throwable $e) {}

$userNotifications = [];
if ($isLoggedIn) {
    try {
        $targetRole = ($userRole === 'reseller') ? 'resellers' : 'customers';
        $stmtNotif = $pdo->prepare("SELECT `id`, `title`, `message`, `created_at` FROM `system_notifications` 
            WHERE (`target_type` = 'all' OR `target_type` = ?) 
            ORDER BY `id` DESC LIMIT 25");
        $stmtNotif->execute([$targetRole]);
        $userNotifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}
$notifCount = count($userNotifications);

$toast = $_SESSION['flash_toast'] ?? null;
unset($_SESSION['flash_toast']);

// Referral coin rules (for Referral page)
$refCoinReward   = ffRefCoinsPerReferral($pdo);
$refConvertRules = [];
try {
    $refConvertRules = $pdo->query("SELECT * FROM `referral_coin_rules` WHERE `rule_type` = 'convert' AND `is_active` = 1 ORDER BY `coins` ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$userCoinBalance = 0;
if ($isLoggedIn) {
    try {
        $stC = $pdo->prepare("SELECT `coins` FROM `users` WHERE `id` = ? LIMIT 1");
        $stC->execute([$user['id']]);
        $userCoinBalance = (int)$stC->fetchColumn();
    } catch (Throwable $e) {}
}
$coinInitial = ffCoinPayload($pdo, $settings, $isLoggedIn ? (int)$user['id'] : 0, (float)$effectiveBdtRate);

// Build a map of reset api_id => user's keys (for reset auto-suggest chips)
$userKeysByPlan = [];
if ($isLoggedIn) {
    try {
        $stmtRk = $pdo->prepare("SELECT l.license_key, l.validity, COALESCE(p.name, '') AS product_name, COALESCE(pl.plan_title, '') AS plan_title
                                 FROM `licenses` l
                                 LEFT JOIN `product_plans` pl ON pl.id = l.plan_id
                                 LEFT JOIN `products` p ON p.id = pl.product_id
                                 WHERE l.user_id = ? ORDER BY l.id DESC LIMIT 100");
        $stmtRk->execute([$user['id']]);
        foreach ($stmtRk->fetchAll(PDO::FETCH_ASSOC) as $rk) {
            $rid = ffResetApiIdFromName((string)$rk['product_name'], $rk['plan_title'] . ' ' . $rk['validity']);
            if ($rid <= 0) continue;
            if (!isset($userKeysByPlan[$rid])) $userKeysByPlan[$rid] = [];
            if (count($userKeysByPlan[$rid]) < 50) {
                $userKeysByPlan[$rid][] = ['license_key' => $rk['license_key'], 'validity' => $rk['validity']];
            }
        }
    } catch (Throwable $e) {}
}

// Build Google Auth URL (FIXED - READS FROM DATABASE SETTINGS)
$googleClientId    = !empty($settings['google_client_id']) ? trim($settings['google_client_id']) : (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '');
$googleRedirectUri = !empty($settings['google_redirect_uri']) ? trim($settings['google_redirect_uri']) : (defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : (defined('SITE_URL') ? SITE_URL . '/index.php' : ''));
$googleAuthUrl = 'https://accounts.google.com/o/oauth2/auth?scope=' . urlencode('email profile') . '&redirect_uri=' . urlencode($googleRedirectUri) . '&response_type=code&client_id=' . urlencode($googleClientId) . '&access_type=online';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?= htmlspecialchars($primaryThemeColor) ?>">
    <title><?= htmlspecialchars($storeTitleName) ?> — Official Store</title>
    
    <script defer src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --app-theme-primary: <?= htmlspecialchars($primaryThemeColor) ?>;
            --app-btn-blue: #3b82f6;
            --app-body-bg: #f8fafc;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body { 
            background-color: var(--app-body-bg); 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            color: #0f172a; 
            overflow-x: hidden; 
            -webkit-tap-highlight-color: transparent;
        }

        .font-bengali { font-family: 'Hind Siliguri', sans-serif; }
        /* CONTINUOUS MARQUEE ANIMATION */
        @keyframes continuousMarquee {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }
        .marquee-track {
            display: inline-block;
            white-space: nowrap;
            padding-left: 100%;
            animation: continuousMarquee 15s linear infinite;
        }
        .marquee-track:hover {
            animation-play-state: paused;
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        .img-skeleton {
            background: linear-gradient(90deg, #1e293b 25%, #334155 50%, #1e293b 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .pill-header-green {
            background-color: var(--app-theme-primary);
            border-radius: 9999px;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            font-weight: 800;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }

        .pill-currency-btn {
            background: #ffffff;
            border: 1.5px solid var(--app-theme-primary);
            border-radius: 9999px;
            padding: 5px 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            font-size: 13px;
            color: var(--app-theme-primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            cursor: pointer;
        }

        .icon-circle-btn {
            width: 42px;
            height: 42px;
            border-radius: 9999px;
            background: var(--app-theme-primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
            transition: transform 0.15s ease;
        }
        .icon-circle-btn:active { transform: scale(0.92); }

        .notice-banner-pill {
            background: linear-gradient(90deg, var(--app-theme-primary) 0%, #16a34a 100%);
            border-radius: 9999px;
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
        }

        .cat-pill-white {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 14px;
            color: #0f172a;
            font-weight: 800;
            font-size: 13px;
            padding: 8px 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-transform: uppercase;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .cat-pill-white.active {
            background: var(--app-theme-primary);
            border-color: var(--app-theme-primary);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
        }

        .panel-product-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 20px;
            padding: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            animation: fadeInSlideUp 0.8s ease-out forwards;
            opacity: 0;
            transform: translateY(25px);
        }
        @keyframes fadeInSlideUp {
            to { opacity: 1; transform: translateY(0); }
        }
        .panel-product-card:hover {
            transform: translateY(-6px) scale(1.03);
            border-color: var(--app-theme-primary);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
        }
        
        .maintenance-badge {
            position: absolute;
            top: 0; left: 0; right: 0;
            background: rgba(239, 68, 68, 0.95);
            color: white;
            font-size: 10px;
            font-weight: 900;
            text-align: center;
            padding: 4px 0;
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
            z-index: 10;
            text-transform: uppercase;
            letter-spacing: 1px;
            backdrop-filter: blur(2px);
        }

        .btn-file-setup {
            background: var(--app-btn-blue);
            color: #ffffff;
            font-weight: 800;
            font-size: 11px;
            border-radius: 12px;
            padding: 6px 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 3px 10px rgba(59, 130, 246, 0.3);
            text-transform: uppercase;
            transition: transform 0.15s ease;
        }
        .btn-file-setup:active { transform: scale(0.95); }

        .price-badge-green {
            background: #f0fdf4;
            color: var(--app-theme-primary);
            border: 1.5px solid #86efac;
            font-weight: 900;
            font-size: 15px;
            padding: 4px 12px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .live-history-container {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 24px;
            padding: 16px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.04);
            position: relative;
            overflow: hidden;
            animation: fadeInSlideUp 1s ease-out forwards;
        }
        .live-tab-btn {
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .live-tab-btn.active {
            background: var(--app-theme-primary);
            color: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .live-tab-btn.inactive {
            color: #64748b;
            background: transparent;
        }
        .live-tab-btn.inactive:hover {
            background: #f1f5f9;
        }

        .live-trx-viewport {
            height: 195px;
            overflow: hidden;
            position: relative;
        }
        .live-trx-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideInUp 0.5s ease-out;
        }
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .floating-support-btn {
            position: fixed;
            bottom: 84px;
            right: 16px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--app-theme-primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            box-shadow: 0 6px 22px rgba(0, 0, 0, 0.25);
            z-index: 50;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        .floating-support-btn:active { transform: scale(0.9); }

        .support-channel-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
        }
        .support-channel-card:hover {
            border-color: var(--app-theme-primary);
            background: #f0fdf4;
            transform: translateY(-1px);
        }

        .bottom-nav-dock {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 28px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }
        .dock-nav-item {
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .dock-nav-item.active { 
            color: var(--app-theme-primary); 
        }
        .dock-active-circle {
            background: #f0fdf4;
            border: 1.5px solid #86efac;
            border-radius: 9999px;
            padding: 6px 14px;
            color: var(--app-theme-primary);
        }

        #userSideDrawer {
            position: fixed;
            inset: 0;
            z-index: 9999;
            pointer-events: none;
            transition: opacity 0.3s ease;
            opacity: 0;
        }
        #userSideDrawer.open {
            pointer-events: auto;
            opacity: 1;
        }
        .drawer-sheet {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 85%;
            max-width: 380px;
            background: #f1f5f9;
            transform: translateX(100%);
            transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            overflow-y: auto;
            box-shadow: -10px 0 30px rgba(0,0,0,0.15);
        }
        #userSideDrawer.open .drawer-sheet { transform: translateX(0); }

        .drawer-green-header {
            background: linear-gradient(135deg, var(--app-theme-primary) 0%, #16a34a 100%);
            padding: 30px 20px 24px 20px;
            position: relative;
            color: #ffffff;
            text-align: center;
        }

        .drawer-menu-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            font-weight: 800;
            font-size: 13px;
            color: #1e293b;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            transition: transform 0.2s ease, background-color 0.2s ease;
            cursor: pointer;
            text-decoration: none;
        }
        .drawer-menu-item:hover { background-color: #f8fafc; transform: scale(1.02); }
        .drawer-menu-item:active { transform: scale(0.98); }
        .drawer-menu-item.logout-item {
            color: #ef4444;
            border-color: #fee2e2;
        }

        .referral-card-pill {
            background: linear-gradient(135deg, var(--app-theme-primary) 0%, #16a34a 100%);
            border-radius: 20px;
            color: #ffffff;
            padding: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        #siteMaintenanceOverlay {
            position: fixed;
            inset: 0;
            z-index: 2147483647;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
            color: white;
        }

        .gateway-key-chip {
            display: inline-block;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #0f172a;
            font-family: monospace;
            font-size: 11px;
            padding: 6px 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            user-select: none;
        }
        .gateway-key-chip:hover {
            background: #e2e8f0;
            border-color: var(--app-theme-primary);
        }
        .gateway-key-chip.selected {
            background: var(--app-theme-primary);
            border-color: var(--app-theme-primary);
            color: #fff;
        }

        /* NEW: Product Details Preview inside Modal (2-line clamp + More button) */
        .prod-details-preview {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: pre-line;
            word-break: break-word;
        }
        /* PERFORMANCE: blur effects are very heavy on mobile GPUs -> disable for smooth scrolling */
        .backdrop-blur-sm, .backdrop-blur-md, .backdrop-blur, .backdrop-blur-lg { -webkit-backdrop-filter: none !important; backdrop-filter: none !important; }
        header.sticky { will-change: transform; transform: translateZ(0); }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; } }
    </style>
    <!-- Prebuilt Tailwind CSS (v3.4.17) - replaces the slow in-browser Tailwind CDN compiler -->
    <style id="tw-prebuilt">*,:after,:before{--tw-border-spacing-x:0;--tw-border-spacing-y:0;--tw-translate-x:0;--tw-translate-y:0;--tw-rotate:0;--tw-skew-x:0;--tw-skew-y:0;--tw-scale-x:1;--tw-scale-y:1;--tw-pan-x: ;--tw-pan-y: ;--tw-pinch-zoom: ;--tw-scroll-snap-strictness:proximity;--tw-gradient-from-position: ;--tw-gradient-via-position: ;--tw-gradient-to-position: ;--tw-ordinal: ;--tw-slashed-zero: ;--tw-numeric-figure: ;--tw-numeric-spacing: ;--tw-numeric-fraction: ;--tw-ring-inset: ;--tw-ring-offset-width:0px;--tw-ring-offset-color:#fff;--tw-ring-color:rgba(59,130,246,.5);--tw-ring-offset-shadow:0 0 #0000;--tw-ring-shadow:0 0 #0000;--tw-shadow:0 0 #0000;--tw-shadow-colored:0 0 #0000;--tw-blur: ;--tw-brightness: ;--tw-contrast: ;--tw-grayscale: ;--tw-hue-rotate: ;--tw-invert: ;--tw-saturate: ;--tw-sepia: ;--tw-drop-shadow: ;--tw-backdrop-blur: ;--tw-backdrop-brightness: ;--tw-backdrop-contrast: ;--tw-backdrop-grayscale: ;--tw-backdrop-hue-rotate: ;--tw-backdrop-invert: ;--tw-backdrop-opacity: ;--tw-backdrop-saturate: ;--tw-backdrop-sepia: ;--tw-contain-size: ;--tw-contain-layout: ;--tw-contain-paint: ;--tw-contain-style: }::backdrop{--tw-border-spacing-x:0;--tw-border-spacing-y:0;--tw-translate-x:0;--tw-translate-y:0;--tw-rotate:0;--tw-skew-x:0;--tw-skew-y:0;--tw-scale-x:1;--tw-scale-y:1;--tw-pan-x: ;--tw-pan-y: ;--tw-pinch-zoom: ;--tw-scroll-snap-strictness:proximity;--tw-gradient-from-position: ;--tw-gradient-via-position: ;--tw-gradient-to-position: ;--tw-ordinal: ;--tw-slashed-zero: ;--tw-numeric-figure: ;--tw-numeric-spacing: ;--tw-numeric-fraction: ;--tw-ring-inset: ;--tw-ring-offset-width:0px;--tw-ring-offset-color:#fff;--tw-ring-color:rgba(59,130,246,.5);--tw-ring-offset-shadow:0 0 #0000;--tw-ring-shadow:0 0 #0000;--tw-shadow:0 0 #0000;--tw-shadow-colored:0 0 #0000;--tw-blur: ;--tw-brightness: ;--tw-contrast: ;--tw-grayscale: ;--tw-hue-rotate: ;--tw-invert: ;--tw-saturate: ;--tw-sepia: ;--tw-drop-shadow: ;--tw-backdrop-blur: ;--tw-backdrop-brightness: ;--tw-backdrop-contrast: ;--tw-backdrop-grayscale: ;--tw-backdrop-hue-rotate: ;--tw-backdrop-invert: ;--tw-backdrop-opacity: ;--tw-backdrop-saturate: ;--tw-backdrop-sepia: ;--tw-contain-size: ;--tw-contain-layout: ;--tw-contain-paint: ;--tw-contain-style: }/*! tailwindcss v3.4.17 | MIT License | https://tailwindcss.com*/*,:after,:before{box-sizing:border-box;border:0 solid #e5e7eb}:after,:before{--tw-content:""}:host,html{line-height:1.5;-webkit-text-size-adjust:100%;-moz-tab-size:4;-o-tab-size:4;tab-size:4;font-family:ui-sans-serif,system-ui,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji;font-feature-settings:normal;font-variation-settings:normal;-webkit-tap-highlight-color:transparent}body{margin:0;line-height:inherit}hr{height:0;color:inherit;border-top-width:1px}abbr:where([title]){-webkit-text-decoration:underline dotted;text-decoration:underline dotted}h1,h2,h3,h4,h5,h6{font-size:inherit;font-weight:inherit}a{color:inherit;text-decoration:inherit}b,strong{font-weight:bolder}code,kbd,pre,samp{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,Liberation Mono,Courier New,monospace;font-feature-settings:normal;font-variation-settings:normal;font-size:1em}small{font-size:80%}sub,sup{font-size:75%;line-height:0;position:relative;vertical-align:baseline}sub{bottom:-.25em}sup{top:-.5em}table{text-indent:0;border-color:inherit;border-collapse:collapse}button,input,optgroup,select,textarea{font-family:inherit;font-feature-settings:inherit;font-variation-settings:inherit;font-size:100%;font-weight:inherit;line-height:inherit;letter-spacing:inherit;color:inherit;margin:0;padding:0}button,select{text-transform:none}button,input:where([type=button]),input:where([type=reset]),input:where([type=submit]){-webkit-appearance:button;background-color:transparent;background-image:none}:-moz-focusring{outline:auto}:-moz-ui-invalid{box-shadow:none}progress{vertical-align:baseline}::-webkit-inner-spin-button,::-webkit-outer-spin-button{height:auto}[type=search]{-webkit-appearance:textfield;outline-offset:-2px}::-webkit-search-decoration{-webkit-appearance:none}::-webkit-file-upload-button{-webkit-appearance:button;font:inherit}summary{display:list-item}blockquote,dd,dl,figure,h1,h2,h3,h4,h5,h6,hr,p,pre{margin:0}fieldset{margin:0}fieldset,legend{padding:0}menu,ol,ul{list-style:none;margin:0;padding:0}dialog{padding:0}textarea{resize:vertical}input::-moz-placeholder,textarea::-moz-placeholder{opacity:1;color:#9ca3af}input::placeholder,textarea::placeholder{opacity:1;color:#9ca3af}[role=button],button{cursor:pointer}:disabled{cursor:default}audio,canvas,embed,iframe,img,object,svg,video{display:block;vertical-align:middle}img,video{max-width:100%;height:auto}[hidden]:where(:not([hidden=until-found])){display:none}.\!container{width:100%!important}.container{width:100%}@media (min-width:640px){.\!container{max-width:640px!important}.container{max-width:640px}}@media (min-width:768px){.\!container{max-width:768px!important}.container{max-width:768px}}@media (min-width:1024px){.\!container{max-width:1024px!important}.container{max-width:1024px}}@media (min-width:1280px){.\!container{max-width:1280px!important}.container{max-width:1280px}}@media (min-width:1536px){.\!container{max-width:1536px!important}.container{max-width:1536px}}.pointer-events-none{pointer-events:none}.visible{visibility:visible}.static{position:static}.fixed{position:fixed}.absolute{position:absolute}.relative{position:relative}.sticky{position:sticky}.inset-0{inset:0}.-bottom-1{bottom:-.25rem}.-right-1{right:-.25rem}.-top-1{top:-.25rem}.bottom-0{bottom:0}.bottom-1{bottom:.25rem}.bottom-1\.5{bottom:.375rem}.bottom-2{bottom:.5rem}.bottom-2\.5{bottom:.625rem}.left-1{left:.25rem}.left-1\.5{left:.375rem}.left-1\/2{left:50%}.right-0{right:0}.right-3{right:.75rem}.right-3\.5{right:.875rem}.right-4{right:1rem}.top-0{top:0}.top-1{top:.25rem}.top-1\.5{top:.375rem}.top-3{top:.75rem}.top-3\.5{top:.875rem}.top-4{top:1rem}.z-10{z-index:10}.z-20{z-index:20}.z-40{z-index:40}.z-\[999999\]{z-index:999999}.z-\[99999\]{z-index:99999}.z-\[9999\]{z-index:9999}.z-\[999\]{z-index:999}.col-span-2{grid-column:span 2/span 2}.mx-1{margin-left:.25rem;margin-right:.25rem}.mx-auto{margin-left:auto;margin-right:auto}.my-1{margin-top:.25rem;margin-bottom:.25rem}.my-2{margin-top:.5rem;margin-bottom:.5rem}.my-auto{margin-top:auto;margin-bottom:auto}.-mr-10{margin-right:-2.5rem}.-mt-10{margin-top:-2.5rem}.mb-0{margin-bottom:0}.mb-0\.5{margin-bottom:.125rem}.mb-1{margin-bottom:.25rem}.mb-2{margin-bottom:.5rem}.mb-4{margin-bottom:1rem}.ml-1{margin-left:.25rem}.mr-1{margin-right:.25rem}.mr-2{margin-right:.5rem}.mt-0{margin-top:0}.mt-0\.5{margin-top:.125rem}.mt-1{margin-top:.25rem}.mt-1\.5{margin-top:.375rem}.mt-2{margin-top:.5rem}.mt-4{margin-top:1rem}.block{display:block}.inline-block{display:inline-block}.flex{display:flex}.inline-flex{display:inline-flex}.table{display:table}.grid{display:grid}.hidden{display:none}.h-0{height:0}.h-10{height:2.5rem}.h-12{height:3rem}.h-14{height:3.5rem}.h-16{height:4rem}.h-2{height:.5rem}.h-20{height:5rem}.h-28{height:7rem}.h-32{height:8rem}.h-36{height:9rem}.h-4{height:1rem}.h-40{height:10rem}.h-44{height:11rem}.h-5{height:1.25rem}.h-6{height:1.5rem}.h-7{height:1.75rem}.h-8{height:2rem}.h-9{height:2.25rem}.h-\[240px\]{height:240px}.h-full{height:100%}.max-h-32{max-height:8rem}.max-h-48{max-height:12rem}.max-h-56{max-height:14rem}.max-h-72{max-height:18rem}.max-h-\[60vh\]{max-height:60vh}.max-h-\[85vh\]{max-height:85vh}.min-h-screen{min-height:100vh}.w-0{width:0}.w-10{width:2.5rem}.w-12{width:3rem}.w-14{width:3.5rem}.w-16{width:4rem}.w-2{width:.5rem}.w-20{width:5rem}.w-32{width:8rem}.w-4{width:1rem}.w-40{width:10rem}.w-44{width:11rem}.w-5{width:1.25rem}.w-6{width:1.5rem}.w-7{width:1.75rem}.w-8{width:2rem}.w-9{width:2.25rem}.w-\[240px\]{width:240px}.w-\[90\%\]{width:90%}.w-\[94\%\]{width:94%}.w-full{width:100%}.min-w-0{min-width:0}.min-w-full{min-width:100%}.max-w-5xl{max-width:64rem}.max-w-\[120px\]{max-width:120px}.max-w-md{max-width:28rem}.max-w-sm{max-width:24rem}.max-w-xs{max-width:20rem}.flex-1{flex:1 1 0%}.flex-shrink-0{flex-shrink:0}.flex-grow{flex-grow:1}.-translate-x-1{--tw-translate-x:-0.25rem}.-translate-x-1,.-translate-x-1\/2{transform:translate(var(--tw-translate-x),var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y))}.-translate-x-1\/2{--tw-translate-x:-50%}.translate-y-0{--tw-translate-y:0px}.translate-y-0,.translate-y-\[-20px\]{transform:translate(var(--tw-translate-x),var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y))}.translate-y-\[-20px\]{--tw-translate-y:-20px}.scale-100{--tw-scale-x:1;--tw-scale-y:1}.scale-100,.scale-90{transform:translate(var(--tw-translate-x),var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y))}.scale-90{--tw-scale-x:.9;--tw-scale-y:.9}.transform{transform:translate(var(--tw-translate-x),var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y))}@keyframes bounce{0%,to{transform:translateY(-25%);animation-timing-function:cubic-bezier(.8,0,1,1)}50%{transform:none;animation-timing-function:cubic-bezier(0,0,.2,1)}}.animate-bounce{animation:bounce 1s infinite}@keyframes pulse{50%{opacity:.5}}.animate-pulse{animation:pulse 2s cubic-bezier(.4,0,.6,1) infinite}.cursor-not-allowed{cursor:not-allowed}.cursor-pointer{cursor:pointer}.select-none{-webkit-user-select:none;-moz-user-select:none;user-select:none}.select-all{-webkit-user-select:all;-moz-user-select:all;user-select:all}.appearance-none{-webkit-appearance:none;-moz-appearance:none;appearance:none}.grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}.grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}.flex-col{flex-direction:column}.flex-wrap{flex-wrap:wrap}.items-start{align-items:flex-start}.items-end{align-items:flex-end}.items-center{align-items:center}.items-baseline{align-items:baseline}.justify-center{justify-content:center}.justify-between{justify-content:space-between}.gap-1{gap:.25rem}.gap-1\.5{gap:.375rem}.gap-2{gap:.5rem}.gap-3{gap:.75rem}.space-x-1>:not([hidden])~:not([hidden]){--tw-space-x-reverse:0;margin-right:calc(.25rem*var(--tw-space-x-reverse));margin-left:calc(.25rem*(1 - var(--tw-space-x-reverse)))}.space-x-1\.5>:not([hidden])~:not([hidden]){--tw-space-x-reverse:0;margin-right:calc(.375rem*var(--tw-space-x-reverse));margin-left:calc(.375rem*(1 - var(--tw-space-x-reverse)))}.space-x-2>:not([hidden])~:not([hidden]){--tw-space-x-reverse:0;margin-right:calc(.5rem*var(--tw-space-x-reverse));margin-left:calc(.5rem*(1 - var(--tw-space-x-reverse)))}.space-x-2\.5>:not([hidden])~:not([hidden]){--tw-space-x-reverse:0;margin-right:calc(.625rem*var(--tw-space-x-reverse));margin-left:calc(.625rem*(1 - var(--tw-space-x-reverse)))}.space-x-3>:not([hidden])~:not([hidden]){--tw-space-x-reverse:0;margin-right:calc(.75rem*var(--tw-space-x-reverse));margin-left:calc(.75rem*(1 - var(--tw-space-x-reverse)))}.space-x-4>:not([hidden])~:not([hidden]){--tw-space-x-reverse:0;margin-right:calc(1rem*var(--tw-space-x-reverse));margin-left:calc(1rem*(1 - var(--tw-space-x-reverse)))}.space-y-0>:not([hidden])~:not([hidden]){--tw-space-y-reverse:0;margin-top:calc(0px*(1 - var(--tw-space-y-reverse)));margin-bottom:calc(0px*var(--tw-space-y-reverse))}.space-y-0\.5>:not([hidden])~:not([hidden]){--tw-space-y-reverse:0;margin-top:calc(.125rem*(1 - var(--tw-space-y-reverse)));margin-bottom:calc(.125rem*var(--tw-space-y-reverse))}.space-y-1>:not([hidden])~:not([hidden]){--tw-space-y-reverse:0;margin-top:calc(.25rem*(1 - var(--tw-space-y-reverse)));margin-bottom:calc(.25rem*var(--tw-space-y-reverse))}.space-y-1\.5>:not([hidden])~:not([hidden]){--tw-space-y-reverse:0;margin-top:calc(.375rem*(1 - var(--tw-space-y-reverse)));margin-bottom:calc(.375rem*var(--tw-space-y-reverse))}.space-y-2>:not([hidden])~:not([hidden]){--tw-space-y-reverse:0;margin-top:calc(.5rem*(1 - var(--tw-space-y-reverse)));margin-bottom:calc(.5rem*var(--tw-space-y-reverse))}.space-y-2\.5>:not([hidden])~:not([hidden]){--tw-space-y-reverse:0;margin-top:calc(.625rem*(1 - var(--tw-space-y-reverse)));margin-bottom:calc(.625rem*var(--tw-space-y-reverse))}.space-y-3>:not([hidden])~:not([hidden]){--tw-space-y-reverse:0;margin-top:calc(.75rem*(1 - var(--tw-space-y-reverse)));margin-bottom:calc(.75rem*var(--tw-space-y-reverse))}.space-y-3\.5>:not([hidden])~:not([hidden]){--tw-space-y-reverse:0;margin-top:calc(.875rem*(1 - var(--tw-space-y-reverse)));margin-bottom:calc(.875rem*var(--tw-space-y-reverse))}.space-y-4>:not([hidden])~:not([hidden]){--tw-space-y-reverse:0;margin-top:calc(1rem*(1 - var(--tw-space-y-reverse)));margin-bottom:calc(1rem*var(--tw-space-y-reverse))}.overflow-hidden{overflow:hidden}.overflow-x-auto{overflow-x:auto}.overflow-y-auto{overflow-y:auto}.truncate{overflow:hidden;text-overflow:ellipsis}.truncate,.whitespace-nowrap{white-space:nowrap}.whitespace-pre-line{white-space:pre-line}.break-words{overflow-wrap:break-word}.rounded{border-radius:.25rem}.rounded-2xl{border-radius:1rem}.rounded-3xl{border-radius:1.5rem}.rounded-\[24px\]{border-radius:24px}.rounded-\[28px\]{border-radius:28px}.rounded-full{border-radius:9999px}.rounded-lg{border-radius:.5rem}.rounded-md{border-radius:.375rem}.rounded-xl{border-radius:.75rem}.rounded-t-3xl{border-top-left-radius:1.5rem;border-top-right-radius:1.5rem}.border{border-width:1px}.border-2{border-width:2px}.border-4{border-width:4px}.border-b{border-bottom-width:1px}.border-l-\[9px\]{border-left-width:9px}.border-r-\[9px\]{border-right-width:9px}.border-t{border-top-width:1px}.border-t-\[16px\]{border-top-width:16px}.border-amber-200{--tw-border-opacity:1;border-color:rgb(253 230 138/var(--tw-border-opacity,1))}.border-blue-200{--tw-border-opacity:1;border-color:rgb(191 219 254/var(--tw-border-opacity,1))}.border-emerald-200{--tw-border-opacity:1;border-color:rgb(167 243 208/var(--tw-border-opacity,1))}.border-emerald-300{--tw-border-opacity:1;border-color:rgb(110 231 183/var(--tw-border-opacity,1))}.border-emerald-500{--tw-border-opacity:1;border-color:rgb(16 185 129/var(--tw-border-opacity,1))}.border-orange-300{--tw-border-opacity:1;border-color:rgb(253 186 116/var(--tw-border-opacity,1))}.border-rose-100{--tw-border-opacity:1;border-color:rgb(255 228 230/var(--tw-border-opacity,1))}.border-rose-200{--tw-border-opacity:1;border-color:rgb(254 205 211/var(--tw-border-opacity,1))}.border-rose-300{--tw-border-opacity:1;border-color:rgb(253 164 175/var(--tw-border-opacity,1))}.border-sky-200{--tw-border-opacity:1;border-color:rgb(186 230 253/var(--tw-border-opacity,1))}.border-slate-100{--tw-border-opacity:1;border-color:rgb(241 245 249/var(--tw-border-opacity,1))}.border-slate-200{--tw-border-opacity:1;border-color:rgb(226 232 240/var(--tw-border-opacity,1))}.border-slate-200\/80{border-color:rgba(226,232,240,.8)}.border-slate-300{--tw-border-opacity:1;border-color:rgb(203 213 225/var(--tw-border-opacity,1))}.border-violet-200{--tw-border-opacity:1;border-color:rgb(221 214 254/var(--tw-border-opacity,1))}.border-white{--tw-border-opacity:1;border-color:rgb(255 255 255/var(--tw-border-opacity,1))}.border-white\/10{border-color:hsla(0,0%,100%,.1)}.border-white\/20{border-color:hsla(0,0%,100%,.2)}.border-white\/30{border-color:hsla(0,0%,100%,.3)}.border-white\/40{border-color:hsla(0,0%,100%,.4)}.border-white\/50{border-color:hsla(0,0%,100%,.5)}.border-yellow-200{--tw-border-opacity:1;border-color:rgb(254 240 138/var(--tw-border-opacity,1))}.border-yellow-300{--tw-border-opacity:1;border-color:rgb(253 224 71/var(--tw-border-opacity,1))}.border-l-transparent{border-left-color:transparent}.border-r-transparent{border-right-color:transparent}.border-t-rose-500{--tw-border-opacity:1;border-top-color:rgb(244 63 94/var(--tw-border-opacity,1))}.bg-\[\#1a8a36\]{--tw-bg-opacity:1;background-color:rgb(26 138 54/var(--tw-bg-opacity,1))}.bg-amber-100{--tw-bg-opacity:1;background-color:rgb(254 243 199/var(--tw-bg-opacity,1))}.bg-amber-50{--tw-bg-opacity:1;background-color:rgb(255 251 235/var(--tw-bg-opacity,1))}.bg-amber-500{--tw-bg-opacity:1;background-color:rgb(245 158 11/var(--tw-bg-opacity,1))}.bg-black{--tw-bg-opacity:1;background-color:rgb(0 0 0/var(--tw-bg-opacity,1))}.bg-black\/20{background-color:rgba(0,0,0,.2)}.bg-black\/60{background-color:rgba(0,0,0,.6)}.bg-black\/70{background-color:rgba(0,0,0,.7)}.bg-black\/75{background-color:rgba(0,0,0,.75)}.bg-black\/80{background-color:rgba(0,0,0,.8)}.bg-blue-50{--tw-bg-opacity:1;background-color:rgb(239 246 255/var(--tw-bg-opacity,1))}.bg-emerald-100{--tw-bg-opacity:1;background-color:rgb(209 250 229/var(--tw-bg-opacity,1))}.bg-emerald-400{--tw-bg-opacity:1;background-color:rgb(52 211 153/var(--tw-bg-opacity,1))}.bg-emerald-50{--tw-bg-opacity:1;background-color:rgb(236 253 245/var(--tw-bg-opacity,1))}.bg-emerald-500{--tw-bg-opacity:1;background-color:rgb(16 185 129/var(--tw-bg-opacity,1))}.bg-emerald-600{--tw-bg-opacity:1;background-color:rgb(5 150 105/var(--tw-bg-opacity,1))}.bg-orange-100{--tw-bg-opacity:1;background-color:rgb(255 237 213/var(--tw-bg-opacity,1))}.bg-rose-100{--tw-bg-opacity:1;background-color:rgb(255 228 230/var(--tw-bg-opacity,1))}.bg-rose-50{--tw-bg-opacity:1;background-color:rgb(255 241 242/var(--tw-bg-opacity,1))}.bg-rose-50\/70{background-color:rgba(255,241,242,.7)}.bg-rose-500{--tw-bg-opacity:1;background-color:rgb(244 63 94/var(--tw-bg-opacity,1))}.bg-rose-600{--tw-bg-opacity:1;background-color:rgb(225 29 72/var(--tw-bg-opacity,1))}.bg-sky-100{--tw-bg-opacity:1;background-color:rgb(224 242 254/var(--tw-bg-opacity,1))}.bg-slate-100{--tw-bg-opacity:1;background-color:rgb(241 245 249/var(--tw-bg-opacity,1))}.bg-slate-200{--tw-bg-opacity:1;background-color:rgb(226 232 240/var(--tw-bg-opacity,1))}.bg-slate-50{--tw-bg-opacity:1;background-color:rgb(248 250 252/var(--tw-bg-opacity,1))}.bg-slate-800{--tw-bg-opacity:1;background-color:rgb(30 41 59/var(--tw-bg-opacity,1))}.bg-slate-900{--tw-bg-opacity:1;background-color:rgb(15 23 42/var(--tw-bg-opacity,1))}.bg-transparent{background-color:transparent}.bg-violet-100{--tw-bg-opacity:1;background-color:rgb(237 233 254/var(--tw-bg-opacity,1))}.bg-white{--tw-bg-opacity:1;background-color:rgb(255 255 255/var(--tw-bg-opacity,1))}.bg-white\/10{background-color:hsla(0,0%,100%,.1)}.bg-white\/20{background-color:hsla(0,0%,100%,.2)}.bg-white\/70{background-color:hsla(0,0%,100%,.7)}.bg-white\/95{background-color:hsla(0,0%,100%,.95)}.bg-yellow-100{--tw-bg-opacity:1;background-color:rgb(254 249 195/var(--tw-bg-opacity,1))}.bg-gradient-to-r{background-image:linear-gradient(to right,var(--tw-gradient-stops))}.bg-gradient-to-tr{background-image:linear-gradient(to top right,var(--tw-gradient-stops))}.from-sky-500{--tw-gradient-from:#0ea5e9 var(--tw-gradient-from-position);--tw-gradient-to:rgba(14,165,233,0) var(--tw-gradient-to-position);--tw-gradient-stops:var(--tw-gradient-from),var(--tw-gradient-to)}.from-sky-600{--tw-gradient-from:#0284c7 var(--tw-gradient-from-position);--tw-gradient-to:rgba(2,132,199,0) var(--tw-gradient-to-position);--tw-gradient-stops:var(--tw-gradient-from),var(--tw-gradient-to)}.from-slate-900{--tw-gradient-from:#0f172a var(--tw-gradient-from-position);--tw-gradient-to:rgba(15,23,42,0) var(--tw-gradient-to-position);--tw-gradient-stops:var(--tw-gradient-from),var(--tw-gradient-to)}.via-blue-600{--tw-gradient-to:rgba(37,99,235,0) var(--tw-gradient-to-position);--tw-gradient-stops:var(--tw-gradient-from),#2563eb var(--tw-gradient-via-position),var(--tw-gradient-to)}.to-blue-600{--tw-gradient-to:#2563eb var(--tw-gradient-to-position)}.to-emerald-950{--tw-gradient-to:#022c22 var(--tw-gradient-to-position)}.to-indigo-700{--tw-gradient-to:#4338ca var(--tw-gradient-to-position)}.object-contain{-o-object-fit:contain;object-fit:contain}.object-cover{-o-object-fit:cover;object-fit:cover}.p-0{padding:0}.p-1{padding:.25rem}.p-2{padding:.5rem}.p-2\.5{padding:.625rem}.p-3{padding:.75rem}.p-3\.5{padding:.875rem}.p-4{padding:1rem}.p-5{padding:1.25rem}.p-6{padding:1.5rem}.p-8{padding:2rem}.px-1{padding-left:.25rem;padding-right:.25rem}.px-1\.5{padding-left:.375rem;padding-right:.375rem}.px-2{padding-left:.5rem;padding-right:.5rem}.px-2\.5{padding-left:.625rem;padding-right:.625rem}.px-3{padding-left:.75rem;padding-right:.75rem}.px-3\.5{padding-left:.875rem;padding-right:.875rem}.px-4{padding-left:1rem;padding-right:1rem}.px-5{padding-left:1.25rem;padding-right:1.25rem}.px-6{padding-left:1.5rem;padding-right:1.5rem}.py-0{padding-top:0;padding-bottom:0}.py-0\.5{padding-top:.125rem;padding-bottom:.125rem}.py-1{padding-top:.25rem;padding-bottom:.25rem}.py-1\.5{padding-top:.375rem;padding-bottom:.375rem}.py-10{padding-top:2.5rem;padding-bottom:2.5rem}.py-2{padding-top:.5rem;padding-bottom:.5rem}.py-2\.5{padding-top:.625rem;padding-bottom:.625rem}.py-3{padding-top:.75rem;padding-bottom:.75rem}.py-3\.5{padding-top:.875rem;padding-bottom:.875rem}.py-4{padding-top:1rem;padding-bottom:1rem}.py-6{padding-top:1.5rem;padding-bottom:1.5rem}.py-8{padding-top:2rem;padding-bottom:2rem}.pb-1{padding-bottom:.25rem}.pb-2{padding-bottom:.5rem}.pb-24{padding-bottom:6rem}.pb-3{padding-bottom:.75rem}.pr-0{padding-right:0}.pr-0\.5{padding-right:.125rem}.pt-0{padding-top:0}.pt-0\.5{padding-top:.125rem}.pt-1{padding-top:.25rem}.pt-1\.5{padding-top:.375rem}.pt-2{padding-top:.5rem}.text-left{text-align:left}.text-center{text-align:center}.text-right{text-align:right}.font-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,Liberation Mono,Courier New,monospace}.font-sans{font-family:ui-sans-serif,system-ui,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji}.text-2xl{font-size:1.5rem;line-height:2rem}.text-3xl{font-size:1.875rem;line-height:2.25rem}.text-6xl{font-size:3.75rem;line-height:1}.text-\[10px\]{font-size:10px}.text-\[11px\]{font-size:11px}.text-\[12px\]{font-size:12px}.text-\[7px\]{font-size:7px}.text-\[8px\]{font-size:8px}.text-\[9px\]{font-size:9px}.text-base{font-size:1rem;line-height:1.5rem}.text-lg{font-size:1.125rem;line-height:1.75rem}.text-sm{font-size:.875rem;line-height:1.25rem}.text-xl{font-size:1.25rem;line-height:1.75rem}.text-xs{font-size:.75rem;line-height:1rem}.font-black{font-weight:900}.font-bold{font-weight:700}.font-extrabold{font-weight:800}.font-medium{font-weight:500}.uppercase{text-transform:uppercase}.leading-5{line-height:1.25rem}.leading-relaxed{line-height:1.625}.leading-snug{line-height:1.375}.leading-tight{line-height:1.25}.tracking-wide{letter-spacing:.025em}.tracking-wider{letter-spacing:.05em}.tracking-widest{letter-spacing:.1em}.text-\[\#1a8a36\]{--tw-text-opacity:1;color:rgb(26 138 54/var(--tw-text-opacity,1))}.text-amber-400{--tw-text-opacity:1;color:rgb(251 191 36/var(--tw-text-opacity,1))}.text-amber-500{--tw-text-opacity:1;color:rgb(245 158 11/var(--tw-text-opacity,1))}.text-amber-600{--tw-text-opacity:1;color:rgb(217 119 6/var(--tw-text-opacity,1))}.text-amber-700{--tw-text-opacity:1;color:rgb(180 83 9/var(--tw-text-opacity,1))}.text-blue-500{--tw-text-opacity:1;color:rgb(59 130 246/var(--tw-text-opacity,1))}.text-blue-600{--tw-text-opacity:1;color:rgb(37 99 235/var(--tw-text-opacity,1))}.text-emerald-100{--tw-text-opacity:1;color:rgb(209 250 229/var(--tw-text-opacity,1))}.text-emerald-200{--tw-text-opacity:1;color:rgb(167 243 208/var(--tw-text-opacity,1))}.text-emerald-300{--tw-text-opacity:1;color:rgb(110 231 183/var(--tw-text-opacity,1))}.text-emerald-400{--tw-text-opacity:1;color:rgb(52 211 153/var(--tw-text-opacity,1))}.text-emerald-500{--tw-text-opacity:1;color:rgb(16 185 129/var(--tw-text-opacity,1))}.text-emerald-600{--tw-text-opacity:1;color:rgb(5 150 105/var(--tw-text-opacity,1))}.text-emerald-700{--tw-text-opacity:1;color:rgb(4 120 87/var(--tw-text-opacity,1))}.text-emerald-800{--tw-text-opacity:1;color:rgb(6 95 70/var(--tw-text-opacity,1))}.text-orange-600{--tw-text-opacity:1;color:rgb(234 88 12/var(--tw-text-opacity,1))}.text-rose-500{--tw-text-opacity:1;color:rgb(244 63 94/var(--tw-text-opacity,1))}.text-rose-600{--tw-text-opacity:1;color:rgb(225 29 72/var(--tw-text-opacity,1))}.text-rose-700{--tw-text-opacity:1;color:rgb(190 18 60/var(--tw-text-opacity,1))}.text-sky-200{--tw-text-opacity:1;color:rgb(186 230 253/var(--tw-text-opacity,1))}.text-sky-600{--tw-text-opacity:1;color:rgb(2 132 199/var(--tw-text-opacity,1))}.text-sky-700{--tw-text-opacity:1;color:rgb(3 105 161/var(--tw-text-opacity,1))}.text-slate-300{--tw-text-opacity:1;color:rgb(203 213 225/var(--tw-text-opacity,1))}.text-slate-400{--tw-text-opacity:1;color:rgb(148 163 184/var(--tw-text-opacity,1))}.text-slate-500{--tw-text-opacity:1;color:rgb(100 116 139/var(--tw-text-opacity,1))}.text-slate-600{--tw-text-opacity:1;color:rgb(71 85 105/var(--tw-text-opacity,1))}.text-slate-700{--tw-text-opacity:1;color:rgb(51 65 85/var(--tw-text-opacity,1))}.text-slate-800{--tw-text-opacity:1;color:rgb(30 41 59/var(--tw-text-opacity,1))}.text-slate-900{--tw-text-opacity:1;color:rgb(15 23 42/var(--tw-text-opacity,1))}.text-violet-700{--tw-text-opacity:1;color:rgb(109 40 217/var(--tw-text-opacity,1))}.text-white{--tw-text-opacity:1;color:rgb(255 255 255/var(--tw-text-opacity,1))}.text-yellow-300{--tw-text-opacity:1;color:rgb(253 224 71/var(--tw-text-opacity,1))}.text-yellow-600{--tw-text-opacity:1;color:rgb(202 138 4/var(--tw-text-opacity,1))}.text-yellow-700{--tw-text-opacity:1;color:rgb(161 98 7/var(--tw-text-opacity,1))}.underline{text-decoration-line:underline}.line-through{text-decoration-line:line-through}.opacity-0{opacity:0}.opacity-100{opacity:1}.opacity-40{opacity:.4}.opacity-70{opacity:.7}.shadow{--tw-shadow:0 1px 3px 0 rgba(0,0,0,.1),0 1px 2px -1px rgba(0,0,0,.1);--tw-shadow-colored:0 1px 3px 0 var(--tw-shadow-color),0 1px 2px -1px var(--tw-shadow-color)}.shadow,.shadow-2xl{box-shadow:var(--tw-ring-offset-shadow,0 0 #0000),var(--tw-ring-shadow,0 0 #0000),var(--tw-shadow)}.shadow-2xl{--tw-shadow:0 25px 50px -12px rgba(0,0,0,.25);--tw-shadow-colored:0 25px 50px -12px var(--tw-shadow-color)}.shadow-inner{--tw-shadow:inset 0 2px 4px 0 rgba(0,0,0,.05);--tw-shadow-colored:inset 0 2px 4px 0 var(--tw-shadow-color)}.shadow-inner,.shadow-lg{box-shadow:var(--tw-ring-offset-shadow,0 0 #0000),var(--tw-ring-shadow,0 0 #0000),var(--tw-shadow)}.shadow-lg{--tw-shadow:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -4px rgba(0,0,0,.1);--tw-shadow-colored:0 10px 15px -3px var(--tw-shadow-color),0 4px 6px -4px var(--tw-shadow-color)}.shadow-md{--tw-shadow:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -2px rgba(0,0,0,.1);--tw-shadow-colored:0 4px 6px -1px var(--tw-shadow-color),0 2px 4px -2px var(--tw-shadow-color)}.shadow-md,.shadow-sm{box-shadow:var(--tw-ring-offset-shadow,0 0 #0000),var(--tw-ring-shadow,0 0 #0000),var(--tw-shadow)}.shadow-sm{--tw-shadow:0 1px 2px 0 rgba(0,0,0,.05);--tw-shadow-colored:0 1px 2px 0 var(--tw-shadow-color)}.shadow-xl{--tw-shadow:0 20px 25px -5px rgba(0,0,0,.1),0 8px 10px -6px rgba(0,0,0,.1);--tw-shadow-colored:0 20px 25px -5px var(--tw-shadow-color),0 8px 10px -6px var(--tw-shadow-color);box-shadow:var(--tw-ring-offset-shadow,0 0 #0000),var(--tw-ring-shadow,0 0 #0000),var(--tw-shadow)}.shadow-sky-500{--tw-shadow-color:#0ea5e9;--tw-shadow:var(--tw-shadow-colored)}.shadow-sky-500\/30{--tw-shadow-color:rgba(14,165,233,.3);--tw-shadow:var(--tw-shadow-colored)}.outline-none{outline:2px solid transparent;outline-offset:2px}.blur{--tw-blur:blur(8px)}.blur,.blur-2xl{filter:var(--tw-blur) var(--tw-brightness) var(--tw-contrast) var(--tw-grayscale) var(--tw-hue-rotate) var(--tw-invert) var(--tw-saturate) var(--tw-sepia) var(--tw-drop-shadow)}.blur-2xl{--tw-blur:blur(40px)}.drop-shadow{--tw-drop-shadow:drop-shadow(0 1px 2px rgba(0,0,0,.1)) drop-shadow(0 1px 1px rgba(0,0,0,.06))}.drop-shadow,.filter{filter:var(--tw-blur) var(--tw-brightness) var(--tw-contrast) var(--tw-grayscale) var(--tw-hue-rotate) var(--tw-invert) var(--tw-saturate) var(--tw-sepia) var(--tw-drop-shadow)}.backdrop-blur-lg{--tw-backdrop-blur:blur(16px)}.backdrop-blur-lg,.backdrop-blur-md{-webkit-backdrop-filter:var(--tw-backdrop-blur) var(--tw-backdrop-brightness) var(--tw-backdrop-contrast) var(--tw-backdrop-grayscale) var(--tw-backdrop-hue-rotate) var(--tw-backdrop-invert) var(--tw-backdrop-opacity) var(--tw-backdrop-saturate) var(--tw-backdrop-sepia);backdrop-filter:var(--tw-backdrop-blur) var(--tw-backdrop-brightness) var(--tw-backdrop-contrast) var(--tw-backdrop-grayscale) var(--tw-backdrop-hue-rotate) var(--tw-backdrop-invert) var(--tw-backdrop-opacity) var(--tw-backdrop-saturate) var(--tw-backdrop-sepia)}.backdrop-blur-md{--tw-backdrop-blur:blur(12px)}.backdrop-blur-sm{--tw-backdrop-blur:blur(4px)}.backdrop-blur-sm,.backdrop-filter{-webkit-backdrop-filter:var(--tw-backdrop-blur) var(--tw-backdrop-brightness) var(--tw-backdrop-contrast) var(--tw-backdrop-grayscale) var(--tw-backdrop-hue-rotate) var(--tw-backdrop-invert) var(--tw-backdrop-opacity) var(--tw-backdrop-saturate) var(--tw-backdrop-sepia);backdrop-filter:var(--tw-backdrop-blur) var(--tw-backdrop-brightness) var(--tw-backdrop-contrast) var(--tw-backdrop-grayscale) var(--tw-backdrop-hue-rotate) var(--tw-backdrop-invert) var(--tw-backdrop-opacity) var(--tw-backdrop-saturate) var(--tw-backdrop-sepia)}.transition{transition-property:color,background-color,border-color,text-decoration-color,fill,stroke,opacity,box-shadow,transform,filter,-webkit-backdrop-filter;transition-property:color,background-color,border-color,text-decoration-color,fill,stroke,opacity,box-shadow,transform,filter,backdrop-filter;transition-property:color,background-color,border-color,text-decoration-color,fill,stroke,opacity,box-shadow,transform,filter,backdrop-filter,-webkit-backdrop-filter;transition-timing-function:cubic-bezier(.4,0,.2,1);transition-duration:.15s}.transition-all{transition-property:all;transition-timing-function:cubic-bezier(.4,0,.2,1);transition-duration:.15s}.transition-transform{transition-property:transform;transition-timing-function:cubic-bezier(.4,0,.2,1);transition-duration:.15s}.duration-200{transition-duration:.2s}.duration-300{transition-duration:.3s}.duration-500{transition-duration:.5s}.ease-out{transition-timing-function:cubic-bezier(0,0,.2,1)}.hover\:scale-110:hover{--tw-scale-x:1.1;--tw-scale-y:1.1;transform:translate(var(--tw-translate-x),var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y))}.hover\:border-emerald-500:hover{--tw-border-opacity:1;border-color:rgb(16 185 129/var(--tw-border-opacity,1))}.hover\:bg-amber-600:hover{--tw-bg-opacity:1;background-color:rgb(217 119 6/var(--tw-bg-opacity,1))}.hover\:bg-black\/30:hover{background-color:rgba(0,0,0,.3)}.hover\:bg-blue-100:hover{--tw-bg-opacity:1;background-color:rgb(219 234 254/var(--tw-bg-opacity,1))}.hover\:bg-emerald-100:hover{--tw-bg-opacity:1;background-color:rgb(209 250 229/var(--tw-bg-opacity,1))}.hover\:bg-emerald-700:hover{--tw-bg-opacity:1;background-color:rgb(4 120 87/var(--tw-bg-opacity,1))}.hover\:bg-rose-100:hover{--tw-bg-opacity:1;background-color:rgb(255 228 230/var(--tw-bg-opacity,1))}.hover\:bg-slate-100:hover{--tw-bg-opacity:1;background-color:rgb(241 245 249/var(--tw-bg-opacity,1))}.hover\:bg-slate-200:hover{--tw-bg-opacity:1;background-color:rgb(226 232 240/var(--tw-bg-opacity,1))}.hover\:bg-slate-50:hover{--tw-bg-opacity:1;background-color:rgb(248 250 252/var(--tw-bg-opacity,1))}.hover\:from-sky-600:hover{--tw-gradient-from:#0284c7 var(--tw-gradient-from-position);--tw-gradient-to:rgba(2,132,199,0) var(--tw-gradient-to-position);--tw-gradient-stops:var(--tw-gradient-from),var(--tw-gradient-to)}.hover\:to-blue-700:hover{--tw-gradient-to:#1d4ed8 var(--tw-gradient-to-position)}.hover\:text-emerald-600:hover{--tw-text-opacity:1;color:rgb(5 150 105/var(--tw-text-opacity,1))}.hover\:text-emerald-900:hover{--tw-text-opacity:1;color:rgb(6 78 59/var(--tw-text-opacity,1))}.hover\:text-slate-800:hover{--tw-text-opacity:1;color:rgb(30 41 59/var(--tw-text-opacity,1))}.hover\:text-slate-900:hover{--tw-text-opacity:1;color:rgb(15 23 42/var(--tw-text-opacity,1))}.hover\:underline:hover{text-decoration-line:underline}.focus\:border-emerald-500:focus{--tw-border-opacity:1;border-color:rgb(16 185 129/var(--tw-border-opacity,1))}.focus\:border-emerald-600:focus{--tw-border-opacity:1;border-color:rgb(5 150 105/var(--tw-border-opacity,1))}.active\:scale-90:active{--tw-scale-x:.9;--tw-scale-y:.9}.active\:scale-90:active,.active\:scale-95:active{transform:translate(var(--tw-translate-x),var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y))}.active\:scale-95:active{--tw-scale-x:.95;--tw-scale-y:.95}@media (min-width:640px){.sm\:h-10{height:2.5rem}.sm\:h-36{height:9rem}.sm\:h-64{height:16rem}.sm\:w-10{width:2.5rem}.sm\:grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}.sm\:items-center{align-items:center}.sm\:gap-3{gap:.75rem}.sm\:rounded-3xl{border-radius:1.5rem}.sm\:p-4{padding:1rem}.sm\:p-5{padding:1.25rem}.sm\:p-6{padding:1.5rem}.sm\:px-3{padding-left:.75rem;padding-right:.75rem}.sm\:px-4{padding-left:1rem;padding-right:1rem}.sm\:text-base{font-size:1rem;line-height:1.5rem}.sm\:text-sm{font-size:.875rem;line-height:1.25rem}.sm\:text-xl{font-size:1.25rem;line-height:1.75rem}.sm\:text-xs{font-size:.75rem;line-height:1rem}}@media (min-width:768px){.md\:grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}}</style>
</head>
<body class="min-h-screen w-full flex flex-col justify-between relative pb-24<?= $isSiteMaintenance ? ' site-maint' : '' ?>">
    <?php if ($isSiteMaintenance): ?>
    <style>
        body.site-maint { overflow: hidden !important; height: 100vh; padding: 0 !important; }
        body.site-maint > *:not(#siteMaintenanceOverlay):not(script):not(style) { display: none !important; }
    </style>
    <?php endif; ?>

    <!-- SITE MAINTENANCE OVERLAY -->
    <?php if ($isSiteMaintenance): ?>
    <div id="siteMaintenanceOverlay">
        <div class="max-w-md space-y-4">
            <i class="fa-solid fa-screwdriver-wrench text-6xl text-amber-500 mb-4 animate-bounce"></i>
            <h1 class="text-2xl font-black uppercase tracking-widest text-white">Under Maintenance</h1>
            <p class="text-slate-300 font-bengali text-sm leading-relaxed"><?= nl2br(htmlspecialchars($siteMaintenanceMsg)) ?></p>
            <?php if($isLoggedIn && $userRole === 'admin'): ?>
                <a href="index.php?logout=1" class="inline-block mt-4 px-6 py-2 bg-rose-600 rounded-xl font-bold text-xs uppercase">Logout Admin</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 1. TOAST NOTIFICATION -->
    <div id="toastNotification" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-[999999] transition-all duration-300 pointer-events-none opacity-0 translate-y-[-20px] w-[90%] max-w-sm">
        <div id="toastInner" class="bg-white border-2 border-emerald-500 shadow-xl rounded-2xl p-3 flex items-center space-x-2.5 text-xs font-bold text-slate-800 backdrop-blur-md">
            <i id="toastIcon" class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
            <span id="toastMsg">Notification</span>
        </div>
    </div>

    <!-- 2. TOP STICKY HEADER -->
    <header class="w-full bg-white/95 backdrop-blur-md py-2.5 px-3 border-b border-slate-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-5xl mx-auto flex items-center justify-between gap-1.5">
            
            <div class="flex items-center space-x-1.5">
                <button type="button" onclick="goToPage('dashboard')" class="icon-circle-btn w-9 h-9 sm:w-10 sm:h-10 text-white" aria-label="Home">
                    <i class="fa-solid fa-house text-sm"></i>
                </button>
                
                <div onclick="goToPage('dashboard')" class="cursor-pointer pill-header-green text-xs sm:text-sm tracking-wider uppercase flex items-center gap-1.5 py-2 px-3 sm:px-4">
                    <?php if (!empty($storeLogoUrl)): ?>
                        <img src="<?= htmlspecialchars($storeLogoUrl) ?>" loading="lazy" decoding="async" class="w-5 h-5 rounded-full object-cover mr-1" alt="Logo">
                    <?php endif; ?>
                    <span><?= htmlspecialchars($storeTitleName) ?></span>
                </div>
            </div>

            <div class="flex items-center space-x-1.5">
                <button type="button" onclick="toggleCurrencySelector()" class="pill-currency-btn text-xs py-1.5 px-2.5 sm:px-3">
                    <span id="currencyFlagContainer" class="inline-block w-4 h-4 rounded-full overflow-hidden flex-shrink-0 shadow-sm border border-slate-200">
                        <svg viewBox="0 0 640 480" class="w-full h-full object-cover">
                            <rect width="640" height="480" fill="#006a4e"/>
                            <circle cx="280" cy="240" r="160" fill="#f42a41"/>
                        </svg>
                    </span>
                    <span id="currencyTextLabel" class="font-bold text-slate-800">BDT</span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-500"></i>
                </button>

                <button type="button" onclick="openNotificationsModal()" class="icon-circle-btn relative w-9 h-9 sm:w-10 sm:h-10 text-white" aria-label="Notifications">
                    <i class="fa-solid fa-bell text-sm"></i>
                    <?php if ($notifCount > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-rose-500 text-white font-bold text-[9px] flex items-center justify-center border-2 border-white shadow-sm">
                            <?= $notifCount > 9 ? '9+' : $notifCount ?>
                        </span>
                    <?php endif; ?>
                </button>

                <button type="button" onclick="toggleUserSideDrawer()" class="icon-circle-btn w-9 h-9 sm:w-10 sm:h-10 text-white" aria-label="Open Menu">
                    <i class="fa-solid fa-bars text-base"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- 3. TOP SCROLLING ANNOUNCEMENT TICKER (ALWAYS RUNNING) -->
    <div class="px-3 pt-2 max-w-5xl mx-auto w-full">
        <div class="notice-banner-pill px-3.5 py-2 flex items-center justify-between text-xs font-bengali font-bold shadow-md overflow-hidden">
            <div class="flex items-center space-x-2 overflow-hidden flex-grow mr-2">
                <i class="fa-solid fa-bullhorn text-sm flex-shrink-0 text-yellow-300"></i>
                <div class="overflow-hidden whitespace-nowrap w-full relative">
                    <span class="marquee-track tracking-wide text-[12px] sm:text-xs font-bold text-white">
                        <?= htmlspecialchars(!empty($settings['top_ticker_notice']) ? $settings['top_ticker_notice'] : 'অর্ডার করার ০০.১ সেকেন্ডেই অটো ডেলিভারি পেয়ে যাবেন!') ?>
                    </span>
                </div>
            </div>
            <button type="button" onclick="this.closest('.notice-banner-pill').parentElement.remove()" class="w-5 h-5 rounded-full bg-black/20 hover:bg-black/30 flex items-center justify-center text-[10px] text-white flex-shrink-0 active:scale-90 transition" title="Hide Announcement">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <!-- 4. MAIN VIEWPORT CONTAINER -->
    <main class="w-full flex-grow px-3 pt-2 max-w-5xl mx-auto space-y-3.5">

        <!-- =============================================================== -->
        <!-- 4A. STOREFRONT DASHBOARD TAB                                    -->
        <!-- =============================================================== -->
        <section id="page-dashboard" class="page-section w-full space-y-3.5">
            
            <?php if (!empty($activeBanners)): ?>
            <!-- CLEAN BANNER (No text, no play icon) -->
            <div class="relative w-full rounded-2xl overflow-hidden border border-slate-200 shadow-sm bg-slate-900 img-skeleton">
                <div id="carouselTrack" class="flex transition-transform duration-500 ease-out">
                    <?php foreach ($activeBanners as $idx => $bn): ?>
                        <div class="min-w-full h-44 sm:h-64 relative flex-shrink-0 cursor-pointer" onclick="window.open('<?= htmlspecialchars($bn['target_link'] ?: '#') ?>', '_blank')">
                            <img src="<?= htmlspecialchars($bn['image_url']) ?>" loading="lazy" decoding="async" alt="Banner" class="w-full h-full object-cover">
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex space-x-1.5 z-10">
                    <?php foreach ($activeBanners as $idx => $bn): ?>
                        <button type="button" onclick="goToSlide(<?= $idx ?>)" class="carousel-dot w-2 h-2 rounded-full transition-all <?= $idx === 0 ? 'bg-emerald-600 w-4 shadow-sm' : 'bg-white/70' ?>" aria-label="Slide <?= $idx + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($availableCategories) && !empty($catalogProducts)): 
                // ১ম লাইনে শুধু NON ROOT এবং ROOT থাকবে
                $primaryTarget = ['NON ROOT', 'ROOT'];
                $primaryCats = [];
                $extraCats   = [];

                // মেইন ক্যাটেগরি ফিল্টার
                foreach ($primaryTarget as $target) {
                    foreach ($availableCategories as $cItem) {
                        if (strtoupper(trim($cItem)) === $target) {
                            $primaryCats[] = $cItem;
                            break;
                        }
                    }
                }

                // বাকিগুলো (I PHONE, PC, OTHER ইত্যাদি) More এর ভেতরে যাবে
                foreach ($availableCategories as $cItem) {
                    $cUpper = strtoupper(trim($cItem));
                    if (!in_array($cUpper, $primaryTarget)) {
                        $extraCats[] = $cItem;
                    }
                }
            ?>
            <div class="space-y-2 font-sans my-1">
                <!-- ১ম লাইন: ALL, NON ROOT, ROOT এবং MORE বাটন -->
                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-1">
                    <button type="button" onclick="filterByCategory('ALL', this)" class="cat-pill-white active flex-shrink-0">ALL</button>
                    
                    <?php foreach ($primaryCats as $catItem): ?>
                        <button type="button" onclick="filterByCategory('<?= htmlspecialchars($catItem) ?>', this)" class="cat-pill-white flex-shrink-0">
                            <?= htmlspecialchars($catItem) ?>
                        </button>
                    <?php endforeach; ?>

                    <?php if (!empty($extraCats)): ?>
                        <!-- MORE ▾ বাটন -->
                        <button type="button" id="catMoreToggleBtn" onclick="toggleExtraCategories()" class="cat-pill-white flex-shrink-0 flex items-center gap-1.5 border-emerald-300 text-emerald-800 bg-emerald-50 hover:bg-emerald-100 transition shadow-sm font-black">
                            <span>MORE</span>
                            <i id="catMoreBtnIcon" class="fa-solid fa-chevron-down text-[9px] transition-transform duration-200"></i>
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (!empty($extraCats)): ?>
                    <!-- ২য় লাইন: MORE এ চাপ দিলে I PHONE, PC, OTHER বের হবে -->
                    <div id="extraCategoriesContainer" class="hidden flex items-center gap-1.5 flex-wrap pt-1.5 border-t border-slate-200/80 transition-all duration-300">
                        <?php foreach ($extraCats as $catItem): ?>
                            <button type="button" onclick="filterByCategory('<?= htmlspecialchars($catItem) ?>', this)" class="cat-pill-white flex-shrink-0">
                                <?= htmlspecialchars($catItem) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TOGGLE SCRIPT -->
            <script>
                function toggleExtraCategories() {
                    var container = document.getElementById('extraCategoriesContainer');
                    var icon = document.getElementById('catMoreBtnIcon');
                    var btn = document.getElementById('catMoreToggleBtn');
                    if (!container) return;

                    var isHidden = container.classList.contains('hidden');
                    if (isHidden) {
                        container.classList.remove('hidden');
                        if (icon) icon.className = 'fa-solid fa-chevron-up text-[9px]';
                        if (btn) {
                            btn.classList.add('border-emerald-500', 'bg-emerald-100');
                        }
                    } else {
                        container.classList.add('hidden');
                        if (icon) icon.className = 'fa-solid fa-chevron-down text-[9px]';
                        if (btn) {
                            btn.classList.remove('border-emerald-500', 'bg-emerald-100');
                        }
                    }
                }
            </script>
            <?php endif; ?>

            <?php if (empty($catalogProducts)): ?>
                <div class="bg-white rounded-2xl p-8 text-center space-y-2 border border-slate-200 shadow-sm">
                    <i class="fa-solid fa-box-open text-3xl text-slate-300 block"></i>
                    <p class="text-xs text-slate-500 font-bold">No products currently available on this store.</p>
                </div>
            <?php else: ?>
                <div id="productsGrid" class="grid grid-cols-3 sm:grid-cols-3 md:grid-cols-3 gap-2 sm:gap-3 pt-1">
                    <?php foreach ($catalogProducts as $prod): 
                        $planCount      = count($prod['plans']);
                        $minPriceBDT    = $planCount > 0 ? min(array_column($prod['plans'], 'price_bdt')) : 0;
                        $minPriceUSD    = $planCount > 0 ? min(array_column($prod['plans'], 'price')) : 0;
                        $bannerImg      = !empty($prod['banner_url']) ? $prod['banner_url'] : $prod['logo_url'];
                        $isProdMaint    = (int)($prod['is_active'] ?? 1) === 0;
                        $hasDiscount    = (int)($prod['max_discount'] ?? 0) > 0;
                    ?>
                        <div class="panel-product-card prod-item-box space-y-2" data-category="<?= htmlspecialchars($prod['category']) ?>">
                            
                            <?php if ($isProdMaint): ?>
                                <div class="maintenance-badge">Maintenance</div>
                            <?php endif; ?>

                            <div class="w-full h-28 sm:h-36 rounded-xl bg-slate-900 border border-slate-200 overflow-hidden relative flex items-center justify-center shadow-inner cursor-pointer img-skeleton" onclick="<?= $isProdMaint ? ('showProductMaintenancePopup(' . htmlspecialchars(json_encode((string)$prod['maintenance_msg'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) . ', ' . htmlspecialchars(json_encode((string)$prod['name'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) . ')') : "openProductModal({$prod['id']})" ?>">
                <?php if (!empty($bannerImg)): ?>
                    <img src="<?= htmlspecialchars($bannerImg) ?>" loading="lazy" decoding="async" alt="<?= htmlspecialchars($prod['name']) ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full bg-gradient-to-tr from-slate-900 to-emerald-950 flex flex-col items-center justify-center text-emerald-400 p-2 text-center">
                        <i class="fa-solid fa-shield-halved text-2xl mb-1"></i>
                        <span class="text-[9px] font-black text-white uppercase"><?= htmlspecialchars($prod['name']) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($hasDiscount): ?>
                    <span class="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded-lg text-[9px] font-black uppercase bg-rose-600 text-white shadow-md z-10">
                        -<?= (int)$prod['max_discount'] ?>%
                    </span>
                <?php endif; ?>

                <span class="absolute bottom-1.5 left-1.5 px-1.5 py-0.2 rounded text-[7px] font-black uppercase bg-black/75 text-emerald-300">
                    <?= htmlspecialchars($prod['category']) ?>
                </span>
            </div>

            <div class="text-center space-y-0.5 cursor-pointer" onclick="<?= $isProdMaint ? ('showProductMaintenancePopup(' . htmlspecialchars(json_encode((string)$prod['maintenance_msg'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) . ', ' . htmlspecialchars(json_encode((string)$prod['name'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) . ')') : "openProductModal({$prod['id']})" ?>">
                <h3 class="font-black text-slate-800 text-[11px] sm:text-xs truncate uppercase leading-tight">
                    <?= htmlspecialchars($prod['name']) ?>
                </h3>
                <p class="text-[9px] text-slate-500 font-bold truncate">
                    <?= htmlspecialchars($prod['tags'] ?: '#EXTERNAL #SAFE') ?>
                </p>
            </div>

                            <div class="flex justify-center pt-0.5">
                                <div class="price-badge-green text-xs sm:text-sm font-black">
                                    <span class="price-display" data-bdt="<?= (float)$minPriceBDT ?>" data-usdt="<?= round((float)$minPriceUSD, 2) ?>">
                                        <?= number_format((float)$minPriceBDT, 0) ?>৳
                                    </span>
                                </div>
                            </div>

                            <div class="pt-1">
                                <?php if ($isProdMaint): ?>
                                    <button type="button" onclick="showProductMaintenancePopup(<?= htmlspecialchars(json_encode((string)$prod['maintenance_msg'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode((string)$prod['name'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)" class="w-full py-1.5 rounded-xl bg-rose-100 border border-rose-300 text-rose-600 font-black text-[10px] uppercase shadow-sm active:scale-95 transition">
                                        Maintenance
                                    </button>
                                <?php else: ?>
                                    <button type="button" onclick="openProductModal(<?= $prod['id'] ?>)" class="btn-file-setup w-full">
                                        <i class="fa-solid fa-cart-shopping text-xs"></i> <span>BUY NOW</span>
                                    </button>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- LIVE HISTORY SYSTEM -->
            <div class="live-history-container mt-4">
                <div class="flex items-center justify-between overflow-x-auto no-scrollbar pb-3 border-b border-slate-100">
                    <div class="flex gap-1">
                        <button onclick="switchLiveTab('top_buyers', this)" class="live-tab-btn active">
                            <i class="fa-solid fa-trophy"></i> Top Buyers
                        </button>
                        <button onclick="switchLiveTab('recent_orders', this)" class="live-tab-btn inactive">
                            <i class="fa-solid fa-rocket"></i> Recent Orders
                        </button>
                        <button onclick="switchLiveTab('feedbacks', this)" class="live-tab-btn inactive">
                            <i class="fa-solid fa-comments"></i> Feedbacks <span class="w-2 h-2 rounded-full bg-emerald-400 ml-1 animate-pulse"></span>
                        </button>
                    </div>
                </div>

                <div class="live-trx-viewport pt-2">
                    
                    <div id="tabContent-top_buyers" class="live-tab-content space-y-2">
                        <?php if (empty($topBuyers)): ?>
                            <div class="text-center text-xs text-slate-400 py-6">No buyer data yet.</div>
                        <?php else: ?>
                            <?php foreach ($topBuyers as $index => $tb): 
                                $medalColor = 'bg-slate-100 text-slate-600';
                                if ($index === 0) $medalColor = 'bg-yellow-100 text-yellow-600 border border-yellow-300';
                                elseif ($index === 1) $medalColor = 'bg-slate-200 text-slate-700 border border-slate-300';
                                elseif ($index === 2) $medalColor = 'bg-orange-100 text-orange-600 border border-orange-300';
                            ?>
                                <div class="live-trx-card">
                                    <div class="flex items-center space-x-3">
                                        <div class="relative">
                                            <?php if (!empty($tb['avatar_url'])): ?>
                                                <img src="<?= htmlspecialchars($tb['avatar_url']) ?>" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm">
                                            <?php else: ?>
                                                <div class="w-10 h-10 rounded-full bg-slate-800 text-white flex items-center justify-center font-black text-sm shadow-inner">
                                                    <?= strtoupper(substr($tb['username'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full <?= $medalColor ?> flex items-center justify-center text-[10px] shadow-sm z-10">
                                                <i class="fa-solid fa-medal"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <strong class="text-sm text-slate-900 block font-black"><?= htmlspecialchars($tb['username']) ?></strong>
                                            <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md mt-0.5 inline-block">Top Member</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-sm font-black text-emerald-700 font-mono">
                                            <span class="price-display" data-bdt="<?= (float)$tb['total_spent'] * $effectiveBdtRate ?>" data-usdt="<?= (float)$tb['total_spent'] ?>">
                                                <?= number_format((float)$tb['total_spent'] * $effectiveBdtRate, 0) ?>৳
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div id="tabContent-recent_orders" class="live-tab-content space-y-2 hidden">
                        <?php if (empty($recentOrders)): ?>
                            <div class="text-center text-xs text-slate-400 py-6">No recent orders yet.</div>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $ro): ?>
                                <div class="live-trx-card">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs font-black shadow-inner flex-shrink-0">
                                            <i class="fa-solid fa-cart-shopping"></i>
                                        </div>
                                        <div>
                                            <strong class="text-xs text-slate-900 block truncate"><?= htmlspecialchars($ro['username'] ?: 'Guest') ?></strong>
                                            <span class="text-[10px] text-slate-500 font-sans truncate max-w-[120px] block"><?= htmlspecialchars($ro['product_name'] ?: $ro['method']) ?></span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-black text-emerald-600 font-mono">
                                            <span class="price-display" data-bdt="<?= (float)$ro['amount'] * $effectiveBdtRate ?>" data-usdt="<?= (float)$ro['amount'] ?>">
                                                <?= number_format((float)$ro['amount'] * $effectiveBdtRate, 0) ?>৳
                                            </span>
                                        </span>
                                        <span class="text-[9px] text-slate-400 block font-mono"><?= date('H:i', strtotime($ro['created_at'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div id="tabContent-feedbacks" class="live-tab-content space-y-2 hidden">
                        <?php if (empty($feedbacks)): ?>
                            <div class="text-center text-xs text-slate-400 py-6 flex flex-col items-center">
                                <i class="fa-regular fa-comment-dots text-2xl mb-2 text-slate-300"></i>
                                No feedbacks yet. Be the first to review!
                            </div>
                        <?php else: ?>
                            <?php foreach ($feedbacks as $fb): ?>
                                <div class="bg-slate-50 border border-slate-100 rounded-xl p-3 text-xs">
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="flex items-center space-x-2">
                                            <strong class="text-slate-800 font-bold"><?= htmlspecialchars($fb['username']) ?></strong>
                                            <span class="text-[9px] text-slate-400"><?= date('d M', strtotime($fb['created_at'])) ?></span>
                                        </div>
                                        <div class="text-amber-400 text-[10px]">
                                            <?php for($i=1; $i<=5; $i++) echo $i <= $fb['rating'] ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; ?>
                                        </div>
                                    </div>
                                    <p class="text-slate-600 font-sans text-[11px] leading-snug">"<?= htmlspecialchars($fb['comment']) ?>"</p>
                                    <span class="text-[9px] text-emerald-600 font-bold mt-1 block">Product: <?= htmlspecialchars($fb['product_name']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </section>

        <!-- =============================================================== -->
        <!-- 4B. REFER & EARN TAB                                            -->
        <!-- =============================================================== -->
        <section id="page-referral" class="page-section hidden w-full space-y-3.5">
            <?php if (!$isLoggedIn): ?>
                <div class="bg-white rounded-3xl p-8 text-center space-y-4 border border-slate-200 shadow-sm max-w-md mx-auto">
                    <div class="w-16 h-16 mx-auto rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl">
                        <i class="fa-solid fa-user-lock"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-base font-black text-slate-900 uppercase">Login Required</h3>
                        <p class="text-xs text-slate-500 font-bengali leading-relaxed">
                            রেফারেল প্রোগ্রাম দেখতে এবং আপনার নিজস্ব ৫-ডিজিটের কোড ও ইনভাইট লিংক পেতে অনুগ্রহ করে অ্যাকাউন্টে লগইন করুন!
                        </p>
                    </div>
                    <div class="flex justify-center gap-2 pt-2">
                        <button type="button" onclick="openAuthModal('login')" class="px-6 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-black uppercase shadow-md active:scale-95 transition">
                            Log In
                        </button>
                        <button type="button" onclick="openAuthModal('register')" class="px-6 py-2.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-black uppercase border border-slate-200 active:scale-95 transition">
                            Register
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="referral-card-pill space-y-3 text-center relative overflow-hidden">
                    <div class="w-14 h-14 mx-auto rounded-full bg-white/20 text-white flex items-center justify-center text-2xl shadow-inner border border-white/30">
                        <i class="fa-solid fa-gift"></i>
                    </div>
                    <div class="space-y-1">
                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-black/20 text-emerald-200 border border-white/20 font-mono">
                            REFERRAL COMMISSION PROGRAM
                        </span>
                        <h2 class="text-lg sm:text-xl font-black text-white uppercase pt-1">
                            Invite Friends &amp; Earn Money!
                        </h2>
                        <p class="text-xs text-emerald-100 max-w-sm mx-auto font-bengali">
                            <span id="refPageRewardText">
                            <?php if ($referralBdtOn && $referralCoinOn && $coinInitial['per_refer'] > 0): ?>
                                প্রতিটি সফল রেফারে আপনার ওয়ালেটে <b>৳<?= number_format($referralBonusAmount, 2) ?> টাকা</b> এবং <b><?= number_format($coinInitial['per_refer']) ?> কয়েন</b> ইনস্ট্যান্ট পেয়ে যাবেন!
                            <?php elseif ($referralBdtOn): ?>
                                প্রতিটি সফল রেফারে আপনার ওয়ালেটে <b>৳<?= number_format($referralBonusAmount, 2) ?> টাকা</b> বা পয়সা ইনস্ট্যান্ট ক্রেডিট পেয়ে যাবেন!
                            <?php elseif ($referralCoinOn && $coinInitial['per_refer'] > 0): ?>
                                প্রতিটি সফল রেফারে আপনি পাবেন <b><?= number_format($coinInitial['per_refer']) ?> কয়েন</b> — কয়েন কনভার্ট করে ওয়ালেটে টাকা নিতে পারবেন!
                            <?php else: ?>
                                বন্ধুদের ইনভাইট করুন এবং রেফারেল রিওয়ার্ড জিতুন!
                            <?php endif; ?>
                            </span>
                        </p>
                    </div>

                    <div class="bg-white/10 backdrop-blur-md p-3.5 rounded-2xl border border-white/20 max-w-sm mx-auto flex items-center justify-between">
                        <div class="text-left">
                            <span class="text-[9px] uppercase font-bold text-emerald-200 block">Your 5-Digit Referral Code:</span>
                            <strong id="myReferralCodeText" class="text-2xl font-black text-yellow-300 font-mono tracking-widest">
                                <?= htmlspecialchars($user['referral_code'] ?? '13183') ?>
                            </strong>
                        </div>
                        <button type="button" onclick="copyReferralCode()" class="px-4 py-2 rounded-xl bg-white text-emerald-800 font-black text-xs uppercase shadow hover:bg-slate-100 active:scale-95 transition">
                            <i class="fa-regular fa-copy mr-1"></i> Copy
                        </button>
                    </div>

                    <div class="bg-black/20 p-2.5 rounded-xl border border-white/10 max-w-sm mx-auto flex items-center justify-between text-xs">
                        <span id="myReferralLinkText" class="truncate font-mono text-emerald-100 text-[11px] mr-2">
                            <?= htmlspecialchars(SITE_URL . '?ref=' . ($user['referral_code'] ?? '13183')) ?>
                        </span>
                        <button type="button" onclick="copyReferralLink()" class="px-3 py-1 rounded-lg bg-emerald-500 text-slate-900 font-bold text-[10px] active:scale-95 transition flex-shrink-0">
                            Copy Link
                        </button>
                    </div>
                </div>

                <!-- REFERRAL COINS (live: admin changes show instantly) -->
                <div id="refCoinBox" class="bg-white rounded-2xl p-4 border border-amber-200 shadow-sm space-y-3 <?= $coinInitial['coin_on'] ? '' : 'hidden' ?>">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase block">Your Coins</span>
                            <strong class="text-2xl font-black text-amber-600 font-mono"><i class="fa-solid fa-coins text-lg mr-1"></i><span class="js-coin-balance"><?= number_format($userCoinBalance) ?></span></strong>
                        </div>
                        <span class="js-coin-perrefer px-3 py-1.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 text-[11px] font-black font-mono <?= $coinInitial['per_refer'] > 0 ? '' : 'hidden' ?>">
                            +<?= number_format($coinInitial['per_refer']) ?> coins / refer
                        </span>
                    </div>
                    <div class="space-y-1.5">
                        <span class="text-[10px] font-bold text-slate-500 uppercase block js-coin-packs-title">Convert coins to wallet money</span>
                        <div class="js-coin-packs space-y-1.5"></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-center">
                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-0.5">
                        <span class="text-xs font-bold text-slate-400 uppercase">Total Referred Users</span>
                        <strong class="text-xl font-black text-slate-900 font-mono"><?= count($userReferrals) ?></strong>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-0.5">
                        <span class="text-xs font-bold text-slate-400 uppercase">Total Referral Earned</span>
                        <strong class="text-xl font-black text-emerald-600 font-mono">৳<?= number_format((float)($user['referral_earnings'] ?? 0), 2) ?></strong>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm space-y-2">
                    <h3 class="text-xs font-bold text-slate-800 uppercase flex items-center gap-1.5 pb-2 border-b border-slate-100">
                        <i class="fa-solid fa-users text-emerald-600"></i> Joined With Your Referral
                    </h3>
                    <div class="space-y-1.5 max-h-48 overflow-y-auto no-scrollbar font-mono text-xs">
                        <?php if (empty($userReferrals)): ?>
                            <p class="text-xs text-slate-400 text-center py-4 font-sans">No friends joined using your code yet. Share your code now!</p>
                        <?php else: ?>
                            <?php foreach($userReferrals as $rf): ?>
                                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 flex items-center justify-between">
                                    <div>
                                        <strong class="text-slate-900 font-sans block text-xs"><?= htmlspecialchars($rf['ref_username']) ?></strong>
                                        <span class="text-[9px] text-slate-400"><?= $rf['created_at'] ?></span>
                                    </div>
                                    <span class="text-xs font-black text-emerald-600">+৳<?= number_format((float)$rf['reward_amount'], 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- =============================================================== -->
        <!-- 4C. MY ORDERS HISTORY TAB                                       -->
        <!-- =============================================================== -->
        <section id="page-history" class="page-section hidden w-full space-y-3 font-mono">
            <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 space-y-3 shadow-sm">
                <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2 font-sans">
                        <i class="fa-solid fa-bag-shopping text-emerald-600"></i> My Orders / Transactions
                    </h3>
                    <span class="text-xs text-emerald-700 font-bold bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                        Total: <?= count($deposits) ?>
                    </span>
                </div>

                <div class="space-y-2 font-sans">
                    <?php if (!$isLoggedIn): ?>
                        <div class="text-center py-10 space-y-2 text-xs">
                            <i class="fa-solid fa-lock text-3xl mb-1 text-slate-400 block"></i>
                            <p class="text-slate-600 font-bold">Please login to view your orders</p>
                            <button onclick="openAuthModal('login')" class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold shadow-md active:scale-95 transition">
                                Login to Account
                            </button>
                        </div>
                    <?php elseif (empty($deposits)): ?>
                        <div class="text-center py-10 text-slate-400 text-xs space-y-1">
                            <i class="fa-solid fa-receipt text-3xl mb-1 text-slate-300 block"></i>
                            <p>No transactions yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($deposits as $d): ?>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-1.5 text-xs shadow-sm">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <strong class="text-slate-900 text-xs block"><?= htmlspecialchars($d['method'] ?? 'Transaction') ?></strong>
                                        <span class="text-[10px] text-slate-400 font-mono"><?= $d['created_at'] ?></span>
                                    </div>
                                    <span class="text-[9px] uppercase font-bold px-2 py-0.5 rounded-md
                                        <?= ($d['status'] ?? '') === 'approved' ? 'bg-emerald-100 text-emerald-700' : (($d['status'] ?? '') === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') ?>">
                                        <?= htmlspecialchars($d['status'] ?? 'unknown') ?>
                                    </span>
                                </div>
                                <div class="flex justify-between items-center pt-1 border-t border-slate-200 text-[11px] font-mono">
                                    <span class="text-slate-500">TrxID: <b class="text-slate-700"><?= htmlspecialchars($d['trx_id'] ?? 'N/A') ?></b></span>
                                    <?php $hBdt = ((float)($d['amount_bdt'] ?? 0) > 0) ? (float)$d['amount_bdt'] : ((float)($d['amount'] ?? 0) * $effectiveBdtRate); ?>
                                    <span class="font-black text-slate-900 price-display" data-bdt="<?= round($hBdt, 0) ?>" data-usdt="<?= round((float)($d['amount'] ?? 0), 2) ?>"><?= number_format($hBdt, 0) ?>৳</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Feedback Submission Box -->
            <?php if ($isLoggedIn && !empty($keys)): ?>
            <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm space-y-3 font-sans">
                <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-star text-amber-500"></i> Leave a Feedback
                </h3>
                <form id="feedbackForm" onsubmit="submitFeedback(event)" class="space-y-2.5 text-xs">
                    <div>
                        <label class="text-[10px] text-slate-500 font-bold uppercase block mb-1">Select Product</label>
                        <select id="feedbackProductId" required class="w-full bg-slate-50 border border-slate-200 p-2 rounded-xl outline-none focus:border-emerald-500 font-sans">
                            <option value="">-- Choose purchased product --</option>
                            <?php 
                            $uniqueProdIds = [];
                            foreach($keys as $k) {
                                if(!in_array($k['product_id'], $uniqueProdIds)) {
                                    $uniqueProdIds[] = $k['product_id'];
                                    echo '<option value="'.$k['product_id'].'">'.htmlspecialchars($k['validity']).'</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] text-slate-500 font-bold uppercase block mb-1">Rating</label>
                        <div class="flex gap-2 text-2xl text-slate-300 cursor-pointer" id="starRatingContainer">
                            <i class="fa-solid fa-star" onclick="setRating(1)"></i>
                            <i class="fa-solid fa-star" onclick="setRating(2)"></i>
                            <i class="fa-solid fa-star" onclick="setRating(3)"></i>
                            <i class="fa-solid fa-star" onclick="setRating(4)"></i>
                            <i class="fa-solid fa-star" onclick="setRating(5)"></i>
                        </div>
                        <input type="hidden" id="feedbackRating" value="5">
                    </div>
                    <div>
                        <label class="text-[10px] text-slate-500 font-bold uppercase block mb-1">Your Review</label>
                        <textarea id="feedbackComment" required rows="2" placeholder="How was your experience?" class="w-full bg-slate-50 border border-slate-200 p-2 rounded-xl outline-none focus:border-emerald-500 font-sans"></textarea>
                    </div>
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 text-white font-bold text-[11px] uppercase shadow-md active:scale-95 transition">
                        Submit Feedback
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </section>

        <!-- =============================================================== -->
        <!-- 4C2. MY KEY TAB (FIXED — dedicated page for licenses)          -->
        <!-- =============================================================== -->
        <section id="page-mykey" class="page-section hidden w-full space-y-3 font-mono">
            <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 space-y-3 shadow-sm">
                <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2 font-sans">
                        <i class="fa-solid fa-table-cells-large text-emerald-600"></i> My License Keys
                    </h3>
                    <span class="text-xs text-emerald-700 font-bold bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                        Total: <?= count($keys) ?>
                    </span>
                </div>

                <div class="space-y-2 font-sans">
                    <?php if (!$isLoggedIn): ?>
                        <div class="text-center py-10 space-y-2 text-xs">
                            <i class="fa-solid fa-lock text-3xl mb-1 text-slate-400 block"></i>
                            <p class="text-slate-600 font-bold">Please login to view your keys</p>
                            <button onclick="openAuthModal('login')" class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold shadow-md active:scale-95 transition">
                                Login Now
                            </button>
                        </div>
                    <?php elseif (empty($keys)): ?>
                        <div class="text-center py-10 text-slate-400 text-xs space-y-1">
                            <i class="fa-solid fa-key text-3xl mb-1 text-slate-300 block"></i>
                            <p>No license keys yet. Buy your first plan!</p>
                            <button onclick="goToPage('dashboard')" class="mt-2 px-5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold shadow-md active:scale-95 transition">Browse Products</button>
                        </div>
                    <?php else: ?>
                        <?php foreach($keys as $k): ?>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-2 text-xs shadow-sm">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <strong class="text-slate-900 text-xs block"><?= htmlspecialchars($k['validity'] ?? 'Digital Key') ?>
                                            <?php if (($k['status'] ?? 'active') === 'stopped'): ?>
                                                <span class="ml-1 text-[9px] bg-rose-100 text-rose-600 px-2 py-0.5 rounded-md font-bold uppercase">STOPPED</span>
                                            <?php endif; ?>
                                        </strong>
                                        <span class="text-[10px] text-slate-400 font-mono"><?= $k['created_at'] ?></span>
                                    </div>
                                    <span class="text-[9px] text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-md font-bold uppercase">
                                        <?= htmlspecialchars($k['payment_type'] ?? 'balance') ?>
                                    </span>
                                </div>
                                <div class="bg-white p-2 rounded-lg border border-slate-200 flex justify-between items-center shadow-inner font-mono">
                                    <span class="text-emerald-700 font-bold select-all text-xs truncate mr-2"><?= htmlspecialchars($k['license_key']) ?></span>
                                    <button onclick="copyToClipboard('<?= htmlspecialchars($k['license_key']) ?>', 'License key')" class="px-2.5 py-1 rounded-md bg-emerald-600 text-white text-[10px] font-bold active:scale-95 transition flex-shrink-0">
                                        Copy
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 4D. HWID KEY RESET TAB (UPDATED: Plan → Key chips → Reset)     -->
        <!-- =============================================================== -->
        <section id="page-resetkey" class="page-section hidden w-full space-y-3 font-mono">
            <div class="bg-white rounded-2xl p-4 sm:p-5 space-y-3.5 border border-slate-200 shadow-sm">
                <div class="text-center pb-1">
                    <h2 class="text-sm font-black tracking-wider text-slate-900 uppercase flex items-center justify-center gap-2">
                        <i class="fa-solid fa-arrows-rotate text-emerald-600"></i> RESET LICENSE HARDWARE ID
                    </h2>
                    <p class="text-[11px] text-slate-500 mt-0.5 font-sans">Select product → enter your key → reset HWID</p>
                </div>

                <?php if (!$isLoggedIn): ?>
                    <div class="text-center py-10 space-y-2.5 text-xs font-sans">
                        <i class="fa-solid fa-lock text-3xl mb-1 text-slate-400 block"></i>
                        <p class="text-slate-600 font-bold">Please login to manage and reset your keys</p>
                        <button onclick="openAuthModal('login')" class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-black shadow-md active:scale-95 transition">
                            Login Now
                        </button>
                    </div>
                <?php else: ?>
                    <form id="liveResetForm" onsubmit="handleLiveKeyReset(event)" class="space-y-3 text-xs font-sans">

                        <!-- STEP 1: Product selector (7 supported reset products) -->
                        <div class="space-y-1">
                            <label class="text-[11px] font-bold text-slate-700 block">Step 1 — Select Product</label>
                            <select id="resetPlanSelect" onchange="onResetPlanChange(this.value)" required class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl outline-none focus:border-emerald-500 text-slate-900">
                                <option value="">-- Choose Product --</option>
                                <?php foreach (ffResetProducts() as $rId => $rName): ?>
                                    <option value="<?= (int)$rId ?>"><?= htmlspecialchars($rName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- STEP 2: Key input + auto-suggest chips -->
                        <div class="space-y-1.5">
                            <label class="text-[11px] font-bold text-slate-700 block">Step 2 — Choose / Enter License Key</label>
                            <input type="text" id="resetKeySearchInput" autocomplete="off" placeholder="Paste or select license key..." class="w-full bg-slate-50 border border-slate-200 text-xs text-slate-900 rounded-xl p-2.5 outline-none focus:border-emerald-500 font-mono shadow-inner">
                            
                            <!-- Auto-suggest chips (populated when plan selected) -->
                            <div id="resetKeyChipsWrap" class="hidden space-y-1">
                                <span class="text-[10px] text-slate-500 font-bold uppercase">Your keys for this plan (tap to select):</span>
                                <div id="resetKeyChips" class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto no-scrollbar pt-1"></div>
                            </div>
                        </div>

                        <!-- STEP 3: Reset button -->
                        <div class="space-y-1">
                            <label class="text-[11px] font-bold text-slate-700 block">Step 3 — Confirm</label>
                            <button type="submit" id="resetSubmitBtn" class="w-full py-3 rounded-xl bg-emerald-600 text-white font-black text-xs uppercase tracking-wider shadow-md hover:bg-emerald-700 active:scale-95 transition">
                                <i class="fa-solid fa-arrows-rotate mr-1"></i> Reset HWID Now
                            </button>
                        </div>
                    </form>
                    <div id="resetResultBox" class="hidden p-3 rounded-xl border text-xs leading-relaxed font-sans shadow-sm"></div>
                <?php endif; ?>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 4E. USER PROFILE & WALLET ACCOUNT TAB                           -->
        <!-- =============================================================== -->
        <section id="page-profile" class="page-section hidden w-full space-y-3">
            <?php if (!$isLoggedIn): ?>
                <div class="bg-white rounded-2xl p-8 text-center space-y-3 border border-slate-200 shadow-sm">
                    <div class="w-16 h-16 mx-auto rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl">
                        <i class="fa-solid fa-user-lock"></i>
                    </div>
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">Authentication Required</h3>
                    <p class="text-xs text-slate-500 font-sans">Please sign in or create an account to access profile and wallet history.</p>
                    <div class="flex justify-center gap-2 pt-2">
                        <button type="button" onclick="openAuthModal('login')" class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-black uppercase shadow-md active:scale-95 transition">Log In</button>
                        <button type="button" onclick="openAuthModal('register')" class="px-5 py-2.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-black uppercase border border-slate-200 active:scale-95 transition">Register</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-[#1a8a36] rounded-[24px] p-6 text-white relative overflow-hidden shadow-lg">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full blur-2xl -mr-10 -mt-10"></div>
                    
                    <div class="flex items-center space-x-4 relative z-10">
                        <div class="relative">
                            <div class="w-16 h-16 rounded-full bg-white flex items-center justify-center text-[#1a8a36] text-3xl shadow-inner overflow-hidden border-2 border-white/50">
                                <?php if (!empty($user['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($user['avatar_url']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fa-solid fa-circle-user"></i>
                                <?php endif; ?>
                            </div>
                            <label class="absolute bottom-0 right-0 w-6 h-6 bg-white rounded-full flex items-center justify-center text-[#1a8a36] cursor-pointer shadow-md border border-slate-200 hover:scale-110 transition">
                                <i class="fa-solid fa-camera text-[10px]"></i>
                                <input type="file" id="avatarUploadInput" class="hidden" accept="image/*" onchange="uploadAvatar(this)">
                            </label>
                        </div>
                        <div>
                            <h2 class="text-lg font-black tracking-wide">Hello, <?= htmlspecialchars($user['username']) ?></h2>
                            <p class="text-[11px] text-emerald-100 font-medium mt-0.5">Member since <?= date('M Y', strtotime($user['created_at'] ?? 'now')) ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-[24px] p-5 border border-slate-100 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">Available Balance</span>
                        <div class="flex items-baseline gap-1">
                            <span class="text-3xl font-black text-slate-900 font-sans" id="mainProfileBalance"><?= number_format($userBalanceBdt, 0) ?></span>
                            <span class="text-sm font-bold text-slate-500" id="mainProfileCurrencySymbol">৳</span>
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400">
                        <i class="fa-solid fa-rotate-right"></i>
                    </div>
                </div>


                <!-- COIN WALLET (profile) -->
                <div id="pfCoinCard" class="bg-white rounded-[24px] p-5 border border-amber-200 shadow-sm space-y-3 <?= $coinInitial['coin_on'] ? '' : 'hidden' ?>">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">My Coins</span>
                            <div class="flex items-baseline gap-1.5">
                                <i class="fa-solid fa-coins text-amber-500 text-xl"></i>
                                <span class="text-3xl font-black text-amber-600 font-sans js-coin-balance"><?= number_format($userCoinBalance) ?></span>
                            </div>
                        </div>
                        <button type="button" onclick="openCoinConvertModal()" class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-[11px] font-black uppercase shadow active:scale-95 transition flex items-center gap-1.5">
                            <i class="fa-solid fa-right-left"></i> Convert Coin
                        </button>
                    </div>
                    <div class="js-coin-rate-line flex flex-wrap items-center gap-2 text-[11px] font-bold"></div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-white rounded-[24px] p-5 border border-slate-100 shadow-sm text-center">
                        <span class="text-2xl font-black text-emerald-600 block"><?= count($keys) ?></span>
                        <span class="text-[10px] font-bold text-slate-400 uppercase mt-1 block">Total Orders</span>
                    </div>
                    <div class="bg-white rounded-[24px] p-5 border border-slate-100 shadow-sm text-center">
                        <span class="text-2xl font-black text-emerald-600 block">
                            <?php 
                                $totalSpentUsd = 0;
                                foreach($deposits as $d) {
                                    if($d['status'] === 'approved' && strpos($d['method'], 'Order') !== false) {
                                        $totalSpentUsd += $d['amount'];
                                    }
                                }
                            ?><span class="price-display" data-bdt="<?= round($totalSpentUsd * $effectiveBdtRate, 0) ?>" data-usdt="<?= round($totalSpentUsd, 2) ?>"><?= number_format($totalSpentUsd * $effectiveBdtRate, 0) ?>৳</span>
                        </span>
                        <span class="text-[10px] font-bold text-slate-400 uppercase mt-1 block">Total Spent</span>
                    </div>
                </div>

                <div class="bg-white rounded-[24px] p-5 border border-slate-100 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <i class="fa-solid fa-user-plus"></i>
                            </div>
                            <h3 class="text-sm font-black text-slate-800">Referral Program</h3>
                        </div>
                        <span id="pfRefBadge" class="px-3 py-1 bg-[#1a8a36] text-white text-[10px] font-bold rounded-full <?= ($referralBdtOn || ($referralCoinOn && $coinInitial['per_refer'] > 0)) ? '' : 'hidden' ?>">
                            <?php
                                $bd = [];
                                if ($referralBdtOn) $bd[] = '৳' . rtrim(rtrim(number_format($referralBonusAmount, 2), '0'), '.');
                                if ($referralCoinOn && $coinInitial['per_refer'] > 0) $bd[] = number_format($coinInitial['per_refer']) . ' Coin';
                                echo implode(' + ', $bd) . ' / refer';
                            ?>
                        </span>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <input type="text" readonly value="<?= htmlspecialchars(SITE_URL . '/register?ref=' . ($user['referral_code'] ?? '13183')) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-[11px] font-bold text-slate-700 outline-none">
                        <button onclick="copyReferralLink()" class="px-4 py-2.5 bg-[#1a8a36] text-white rounded-xl text-[11px] font-bold flex items-center gap-1.5 hover:bg-emerald-700 transition">
                            <i class="fa-regular fa-copy"></i> Copy
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-[24px] p-5 border border-slate-100 shadow-sm space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-slate-50 text-slate-600 flex items-center justify-center">
                                <i class="fa-solid fa-gear"></i>
                            </div>
                            <h3 class="text-sm font-black text-slate-800">Account Settings</h3>
                        </div>
                        <button onclick="openEditProfileModal()" class="text-slate-400 hover:text-emerald-600 transition">
                            <i class="fa-solid fa-chevron-right text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="pt-1 text-center">
                    <a href="index.php?logout=1" class="inline-flex items-center space-x-1.5 px-6 py-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 text-xs font-bold hover:bg-rose-100 transition active:scale-95 shadow-sm">
                        <i class="fa-solid fa-power-off"></i> <span>Log Out</span>
                    </a>
                </div>
            <?php endif; ?>
        </section>

        <!-- =============================================================== -->
        <!-- 4F. ADD FUND GATEWAY VIEW (BDT / USDT ONLY)                    -->
        <!-- =============================================================== -->
        <section id="page-addfund" class="page-section hidden w-full space-y-3">
            <div id="addFundMethodListContainer" class="bg-white rounded-2xl p-4 sm:p-5 space-y-3.5 border border-slate-200 shadow-sm">
                <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-1.5">
                            <i class="fa-solid fa-wallet text-emerald-600"></i> Deposit Balance Gateway
                        </h3>
                        <span class="text-[10px] text-slate-400">Select payment channel to add funds</span>
                    </div>
                </div>

                <div class="space-y-2 pt-1">
                    <!-- Option 1: BDT -->
                    <div onclick="openAddFundSubGateway('bdt')" class="bg-slate-50 border border-slate-200 hover:border-emerald-500 rounded-xl p-3 flex items-center justify-between cursor-pointer transition active:scale-98 shadow-sm">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-sm font-bold text-emerald-600 flex-shrink-0 shadow-sm">
                                <span class="text-lg">🇧🇩</span>
                            </div>
                            <div>
                                <strong class="text-slate-900 block text-xs">BDT Gateway</strong>
                                <span class="text-[10px] text-slate-500">bKash, Nagad, Rocket</span>
                            </div>
                        </div>
                        <span class="text-xs text-emerald-600 font-bold flex items-center gap-1">
                            <span>Deposit</span> <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </span>
                    </div>

                    <!-- Option 2: USDT -->
                    <div onclick="openAddFundSubGateway('usdt')" class="bg-slate-50 border border-slate-200 hover:border-emerald-500 rounded-xl p-3 flex items-center justify-between cursor-pointer transition active:scale-98 shadow-sm">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-sm font-bold text-amber-500 flex-shrink-0 shadow-sm">
                                <i class="fa-solid fa-coins"></i>
                            </div>
                            <div>
                                <strong class="text-slate-900 block text-xs">USDT Gateway</strong>
                                <span class="text-[10px] text-slate-500">Binance Pay / Crypto</span>
                            </div>
                        </div>
                        <span class="text-xs text-emerald-600 font-bold flex items-center gap-1">
                            <span>Deposit</span> <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </span>
                    </div>
                </div>
            </div>

            <!-- SUB-GATEWAY SELECTION -->
            <div id="addFundSubGatewayScreen" class="hidden bg-white rounded-2xl p-4 sm:p-5 space-y-3.5 border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between pb-1 border-b border-slate-100">
                    <button type="button" onclick="closeAddFundSubGateway()" class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 text-xs font-bold flex items-center gap-1 active:scale-95 transition">
                        <i class="fa-solid fa-arrow-left"></i> <span>Back</span>
                    </button>
                    <span id="addFundSubGatewayTitle" class="text-xs font-black text-emerald-700 uppercase">Select Method</span>
                </div>

                <div id="addFundSubGatewayList" class="space-y-2"></div>
            </div>

            <!-- DEDICATED ADD FUND PAYMENT SCREEN -->
            <div id="addFundDedicatedScreen" class="hidden bg-white rounded-2xl p-4 sm:p-5 space-y-3.5 border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between pb-1 border-b border-slate-100">
                    <button type="button" onclick="closeDedicatedAddFundScreen()" class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 text-xs font-bold flex items-center gap-1 active:scale-95 transition">
                        <i class="fa-solid fa-arrow-left"></i> <span>Back</span>
                    </button>
                    <span id="addFundDedicatedMethodTitle" class="text-xs font-black text-emerald-700 uppercase">Deposit</span>
                </div>

                <!-- BANNER (if admin configured one) -->
                <div id="addFundBannerContainer" class="hidden">
                    <img id="addFundBannerImg" src="" alt="Banner" class="w-full rounded-xl border border-slate-200 shadow-sm">
                </div>

                <div class="bg-emerald-50 border border-emerald-200 p-3 rounded-2xl space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-1.5 mb-0.5 flex-wrap">
                                <img id="addFundMethodLogo" src="" alt="" class="hidden w-5 h-5 rounded object-cover border border-slate-200 bg-white">
                                <span id="addFundTypeLabel" class="text-[10px] text-slate-500 uppercase">Account Number:</span>
                                <span id="addFundTypeBadge" class="hidden px-1.5 py-0.5 rounded text-[9px] font-black uppercase"></span>
                            </div>
                            <strong id="addFundNumberVal" class="text-slate-900 text-sm font-mono tracking-wider">01700000000</strong>
                        </div>
                        <button type="button" onclick="copyToClipboard(document.getElementById('addFundNumberVal').innerText, 'Account Number')" class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-[10px] font-bold active:scale-95 transition shadow-sm">
                            <i class="fa-regular fa-copy mr-1"></i> Copy
                        </button>
                    </div>
                </div>

                <div id="addFundInstructionsBox" class="hidden bg-slate-50 border border-slate-200 p-3 rounded-2xl text-[11px] text-slate-600 whitespace-pre-line leading-snug"></div>
                <div id="addFundQrContainer" class="hidden text-center">
                    <span class="text-[10px] text-slate-500 block uppercase mb-1">Scan QR to pay</span>
                    <img id="addFundQrImg" src="" alt="QR Code" class="mx-auto w-44 h-44 object-contain rounded-xl border border-slate-200 bg-white p-1">
                </div>

                <div class="space-y-1 text-xs">
                    <label class="text-[11px] font-bold text-slate-700 block">Amount to Deposit (BDT) <span class="text-rose-500">*</span></label>
                    <input type="number" step="any" id="addFundAmountInput" required placeholder="e.g. 500" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none focus:border-emerald-500 font-mono shadow-inner">
                </div>

                <div class="space-y-1 text-xs">
                    <label class="text-[11px] font-bold text-slate-700 block">Transaction ID (TrxID) <span class="text-rose-500">*</span></label>
                    <input type="text" id="addFundTrxIdInput" placeholder="TrxID / Order ID (e.g. 9A8B7C6D)" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 uppercase outline-none focus:border-emerald-500 font-mono shadow-inner">
                </div>

                <div id="addFundOrderError" class="hidden p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 text-[11px] font-bold text-center"></div>

                <button type="button" id="btnVerifyAddFund" onclick="submitAddFundVerification()" class="w-full py-3 rounded-xl bg-emerald-600 text-white font-black text-xs uppercase tracking-wider shadow-md hover:bg-emerald-700 active:scale-95 transition">
                    <i class="fa-solid fa-shield-check mr-1"></i> Verify &amp; Add Balance
                </button>
            </div>
        </section>

    </main>

    <!-- =============================================================== -->
    <!-- 5. FLOATING SUPPORT BUTTON & MODAL                             -->
    <!-- =============================================================== -->
    <div onclick="toggleMultiSupportModal()" class="floating-support-btn shadow-xl" title="24/7 Customer Support">
        <i class="fa-solid fa-headset"></i>
    </div>

    <div id="multiSupportModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9999] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-xs p-5 space-y-3.5 shadow-2xl border border-slate-100 text-center relative my-auto">
            <button type="button" onclick="toggleMultiSupportModal()" class="absolute top-4 right-4 w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs">
                <i class="fa-solid fa-xmark"></i>
            </button>
            
            <div class="space-y-1">
                <div class="w-12 h-12 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl shadow-inner">
                    <i class="fa-solid fa-headset"></i>
                </div>
                <h3 class="text-sm font-black text-slate-900 uppercase pt-1 font-sans">Customer Support</h3>
                <p class="text-[11px] text-slate-500 font-sans">Connect with us on your favorite channel</p>
            </div>

            <div class="space-y-2 pt-1 text-left">
                <?php if (empty($activeSupportChannels)): ?>
                    <p class="text-xs text-slate-400 text-center py-4 font-sans">Support channels will be available soon.</p>
                <?php else: ?>
                    <?php foreach ($activeSupportChannels as $ch): ?>
                        <a href="<?= htmlspecialchars($ch['url']) ?>" target="_blank" class="support-channel-card">
                            <div class="flex items-center space-x-2.5">
                                <span class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-base">
                                    <i class="<?= htmlspecialchars($ch['icon']) ?>"></i>
                                </span>
                                <div>
                                    <strong class="text-xs text-slate-900 block font-sans"><?= htmlspecialchars($ch['name']) ?></strong>
                                    <span class="text-[9px] text-slate-400 font-mono"><?= htmlspecialchars($ch['sub']) ?></span>
                                </div>
                            </div>
                            <i class="fa-solid fa-chevron-right text-xs text-slate-400"></i>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- =============================================================== -->
    <!-- 6. BOTTOM NAVIGATION DOCK (FIXED My Key)                       -->
    <!-- =============================================================== -->
    <nav class="fixed bottom-2.5 left-1/2 -translate-x-1/2 w-[94%] max-w-md z-40 bottom-nav-dock px-3 py-2 flex items-center justify-between font-sans">
        
        <button onclick="goToPage('dashboard')" id="bnav-dashboard" class="dock-nav-item active flex flex-col items-center flex-1 space-y-0.5">
            <div class="dock-icon-wrapper flex items-center justify-center dock-active-circle">
                <i class="fa-solid fa-house text-sm"></i>
            </div>
            <span class="text-[9px] font-bold mt-0.5">Home</span>
        </button>

        <button onclick="handleNavClick('addfund')" id="bnav-addfund" class="dock-nav-item flex flex-col items-center flex-1 space-y-0.5">
            <div class="dock-icon-wrapper flex items-center justify-center">
                <i class="fa-solid fa-wallet text-sm"></i>
            </div>
            <span class="text-[9px] font-bold mt-0.5">Add Fund</span>
        </button>

        <button onclick="handleNavClick('history')" id="bnav-history" class="dock-nav-item flex flex-col items-center flex-1 space-y-0.5">
            <div class="dock-icon-wrapper flex items-center justify-center">
                <i class="fa-solid fa-bag-shopping text-sm"></i>
            </div>
            <span class="text-[9px] font-bold mt-0.5">Order</span>
        </button>

        <!-- FIXED: My Key now opens dedicated page -->
        <button onclick="handleNavClick('mykey')" id="bnav-mykey" class="dock-nav-item flex flex-col items-center flex-1 space-y-0.5">
            <div class="dock-icon-wrapper flex items-center justify-center">
                <i class="fa-solid fa-table-cells-large text-sm"></i>
            </div>
            <span class="text-[9px] font-bold mt-0.5">My Key</span>
        </button>

        <button onclick="handleAccountNavClick()" id="bnav-profile" class="dock-nav-item flex flex-col items-center flex-1 space-y-0.5">
            <div class="dock-icon-wrapper flex items-center justify-center">
                <i class="fa-solid fa-circle-user text-sm"></i>
            </div>
            <span class="text-[9px] font-bold mt-0.5"><?= $isLoggedIn ? 'Account' : 'Login' ?></span>
        </button>
    </nav>

    <!-- =============================================================== -->
    <!-- 7. SIDE DRAWER MENU                                             -->
    <!-- =============================================================== -->
    <div id="userSideDrawer" onclick="if(event.target === this) toggleUserSideDrawer()">
        <div class="drawer-sheet flex flex-col justify-between">
            
            <div>
                <div class="drawer-green-header">
                    <button type="button" onclick="toggleUserSideDrawer()" class="absolute top-4 right-4 w-7 h-7 rounded-full bg-white/20 text-white flex items-center justify-center text-xs active:scale-90 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>

                    <div class="relative w-20 h-20 mx-auto mb-2">
                        <div class="w-full h-full rounded-full bg-white p-1 shadow-lg overflow-hidden flex items-center justify-center">
                            <?php if (!empty($user['avatar_url'])): ?>
                                <img src="<?= htmlspecialchars($user['avatar_url']) ?>" class="w-full h-full object-cover rounded-full" alt="User">
                            <?php else: ?>
                                <img src="https://api.dicebear.com/7.x/bottts/svg?seed=<?= urlencode($user['username']) ?>" class="w-full h-full object-cover rounded-full" alt="Avatar">
                            <?php endif; ?>
                        </div>
                        <label class="absolute bottom-0 right-0 w-6 h-6 rounded-full bg-slate-900 text-white flex items-center justify-center text-[10px] shadow border-2 border-white cursor-pointer hover:scale-110 transition">
                            <i class="fa-solid fa-camera"></i>
                            <input type="file" class="hidden" accept="image/*" onchange="uploadAvatar(this)">
                        </label>
                    </div>

                    <h3 class="text-sm sm:text-base font-extrabold uppercase">
                        Welcome back, <?= htmlspecialchars($user['username']) ?>! 👋
                    </h3>
                    <p class="text-[11px] text-emerald-100 font-sans truncate max-w-xs mx-auto mt-0.5">
                        <?= htmlspecialchars($user['email'] ?: 'No email linked') ?>
                    </p>
                </div>

                <div class="p-4 space-y-2.5">
                    
                    <div onclick="toggleUserSideDrawer(); goToPage('profile');" class="drawer-menu-item">
                        <i class="fa-solid fa-circle-user text-emerald-600 text-base"></i>
                        <span>MY ACCOUNT</span>
                    </div>

                    <div onclick="toggleUserSideDrawer(); goToPage('history');" class="drawer-menu-item">
                        <i class="fa-solid fa-bag-shopping text-emerald-600 text-base"></i>
                        <span>MY ORDERS</span>
                    </div>

                    <div onclick="toggleUserSideDrawer(); goToPage('mykey');" class="drawer-menu-item">
                        <i class="fa-solid fa-table-cells-large text-emerald-600 text-base"></i>
                        <span>MY CODES</span>
                    </div>

                    <div onclick="toggleUserSideDrawer(); goToPage('referral');" class="drawer-menu-item">
                        <i class="fa-solid fa-gift text-emerald-600 text-base"></i>
                        <span>REFER &amp; EARN</span>
                    </div>

                    <div onclick="toggleUserSideDrawer(); openSpinWheelModal();" class="drawer-menu-item">
                        <i class="fa-solid fa-dharmachakra text-emerald-600 text-base"></i>
                        <span>Spin &amp; Win</span>
                    </div>

                    <div onclick="toggleUserSideDrawer(); goToPage('addfund');" class="drawer-menu-item">
                        <i class="fa-solid fa-wallet text-emerald-600 text-base"></i>
                        <span>ADD FUND</span>
                    </div>

                    <div onclick="toggleUserSideDrawer(); goToPage('resetkey');" class="drawer-menu-item">
                        <i class="fa-solid fa-arrows-rotate text-emerald-600 text-base"></i>
                        <span>KEY RESET NOW</span>
                    </div>

                    <?php if ($isLoggedIn): ?>
                        <a href="index.php?logout=1" class="drawer-menu-item logout-item mt-2">
                            <i class="fa-solid fa-arrow-right-from-bracket text-rose-500 text-base"></i>
                            <span>LOGOUT</span>
                        </a>
                    <?php else: ?>
                        <div onclick="toggleUserSideDrawer(); openAuthModal('login');" class="drawer-menu-item">
                            <i class="fa-solid fa-arrow-right-to-bracket text-emerald-600 text-base"></i>
                            <span>LOGIN / REGISTER</span>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

            <div class="p-4 text-center text-[10px] text-slate-400 font-bold border-t border-slate-200">
                <?= htmlspecialchars($storeTitleName) ?> &bull; Client Console v23.6 PRO
            </div>

        </div>
    </div>

    <!-- =============================================================== -->
    <!-- 8. CURRENCY SELECTOR MODAL                                      -->
    <!-- =============================================================== -->
    <!-- =============================================================== -->
    <!-- COIN CONVERT MODAL (live packs from admin)                      -->
    <!-- =============================================================== -->
    <div id="coinConvertModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9999] hidden flex items-end sm:items-center justify-center p-0 sm:p-4">
        <div class="bg-white rounded-t-3xl sm:rounded-3xl w-full max-w-sm p-5 space-y-4 shadow-2xl border border-slate-100 max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-black text-slate-900 uppercase flex items-center gap-2"><i class="fa-solid fa-coins text-amber-500"></i> Convert Coin</h3>
                <button type="button" onclick="closeCoinConvertModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center"><i class="fa-solid fa-xmark text-xs"></i></button>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-3 flex items-center justify-between">
                <span class="text-[10px] font-bold text-amber-700 uppercase">Your Coins</span>
                <strong class="text-xl font-black text-amber-600 font-mono"><i class="fa-solid fa-coins mr-1"></i><span class="js-coin-balance">0</span></strong>
            </div>
            <div class="js-coin-rate-line flex flex-wrap items-center gap-2 text-[11px] font-bold"></div>
            <div class="space-y-2">
                <span class="text-[10px] font-bold text-slate-500 uppercase block">কত কয়েনে কত টাকা</span>
                <div class="js-coin-packs space-y-2"></div>
            </div>
        </div>
    </div>

    <div id="currencyModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[999] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-xs p-5 space-y-4 shadow-2xl border border-slate-100 text-center">
            <h3 class="text-sm font-black text-slate-900 uppercase">Select Currency</h3>
            <div class="space-y-2 text-left">
                <button type="button" onclick="setCurrency('BDT')" id="btnCurrencyBDT" class="w-full p-3 rounded-2xl border-2 border-emerald-500 bg-emerald-50 flex items-center justify-between font-bold text-xs">
                    <div class="flex items-center space-x-2.5">
                        <span class="w-5 h-5 rounded-full overflow-hidden shadow-sm inline-block">
                            <svg viewBox="0 0 640 480" class="w-full h-full object-cover"><rect width="640" height="480" fill="#006a4e"/><circle cx="280" cy="240" r="160" fill="#f42a41"/></svg>
                        </span>
                        <span>BDT (Bangladeshi Taka)</span>
                    </div>
                    <i class="fa-solid fa-circle-check text-emerald-600 check-bdt"></i>
                </button>
                <button type="button" onclick="setCurrency('USDT')" id="btnCurrencyUSDT" class="w-full p-3 rounded-2xl border border-slate-200 hover:border-emerald-500 bg-white flex items-center justify-between font-bold text-xs text-slate-700">
                    <div class="flex items-center space-x-2.5">
                        <span class="w-5 h-5 rounded-full overflow-hidden shadow-sm inline-block bg-slate-100 text-center text-[10px] leading-5">🇺🇸</span>
                        <span>USDT (Binance Pay)</span>
                    </div>
                </button>
            </div>
            <button type="button" onclick="toggleCurrencySelector()" class="w-full py-2.5 bg-slate-100 rounded-xl text-xs font-bold text-slate-600">Close</button>
        </div>
    </div>

    <!-- =============================================================== -->
    <!-- 9. NOTIFICATIONS MODAL                                          -->
    <!-- =============================================================== -->
    <div id="notificationsModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[999] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-sm p-5 space-y-3.5 shadow-2xl border border-slate-100">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                <div class="flex items-center space-x-2">
                    <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs font-bold">
                        <i class="fa-solid fa-bell"></i>
                    </span>
                    <h3 class="text-xs sm:text-sm font-black text-slate-900 uppercase">Notifications</h3>
                </div>
                <button type="button" onclick="closeNotificationsModal()" class="w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="space-y-2 max-h-72 overflow-y-auto no-scrollbar pr-0.5">
                <?php if (empty($userNotifications)): ?>
                    <div class="text-center py-8 text-slate-400 text-xs">No announcements right now.</div>
                <?php else: ?>
                    <?php foreach ($userNotifications as $nt): ?>
                        <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 space-y-1">
                            <strong class="text-xs text-slate-900 block"><?= htmlspecialchars($nt['title']) ?></strong>
                            <p class="text-[11px] text-slate-600 leading-snug"><?= nl2br(htmlspecialchars($nt['message'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- =============================================================== -->
    <!-- 10. PRODUCT PURCHASE MODAL (Wallet / BDT / USDT + Banner + Details)-->
    <!-- =============================================================== -->
    <div id="productPurchaseModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-[9999] hidden flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white rounded-[28px] w-full max-w-md p-5 space-y-4 shadow-2xl border border-slate-200 relative my-auto">
            
            <button type="button" onclick="closeProductPurchaseModal()" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:text-slate-800 flex items-center justify-center active:scale-90 transition">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>

            <div class="flex items-center space-x-3 pb-3 border-b border-slate-100">
                <div id="modalProdThumb" class="w-14 h-14 rounded-2xl bg-slate-900 text-emerald-400 flex items-center justify-center text-xl font-bold overflow-hidden shadow flex-shrink-0">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div class="overflow-hidden">
                    <h3 id="modalProdName" class="text-sm sm:text-base font-extrabold text-slate-900 truncate uppercase">Product Name</h3>
                    <span id="modalProdTag" class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md inline-block mt-0.5 uppercase tracking-wider">OFFICIAL PASS</span>
                </div>
            </div>

            <!-- Step 1: Plan, Promo & Details -->
            <div id="modalViewPlanSelect" class="space-y-3">
                
                <!-- Product Details Box (2-line preview + More button) -->
                <div class="space-y-1">
                    <label class="text-[11px] font-bold text-slate-700 uppercase flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info text-emerald-600"></i> Product Details
                    </label>
                    <div class="w-full bg-slate-50 border border-slate-200 text-xs text-slate-700 rounded-xl p-3 font-sans shadow-inner leading-relaxed">
                        <div id="modalProdDetailsPreview" class="prod-details-preview">Details loading...</div>
                        <button type="button" id="modalProdDetailsMoreBtn" onclick="openFullProductDetails()" class="mt-1.5 text-[10px] font-black uppercase text-emerald-700 hover:text-emerald-900 inline-flex items-center gap-1 underline">
                            <span>More</span> <i class="fa-solid fa-chevron-down text-[8px]"></i>
                        </button>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[11px] font-bold text-slate-700 uppercase">Select Validity Plan:</label>
                    <div class="relative w-full" style="position: relative;">
                        <select id="modalPlanSelect" onchange="onPlanSelectChange(this.value)" class="w-full bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 rounded-xl p-3 outline-none focus:border-emerald-500 appearance-none cursor-pointer" style="padding-right: 95px; -webkit-appearance: none; -moz-appearance: none; appearance: none;">
                        </select>
                        
                        <!-- BORDERED MORE ▾ BADGE (NO TEXT SELECTION / OPENS DROPDOWN DIRECTLY) -->
                        <div style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); pointer-events: none; user-select: none; -webkit-user-select: none; -webkit-touch-callout: none; display: flex; align-items: center; gap: 5px; background: #ecfdf5; color: #047857; border: 1.5px solid #10b981; padding: 5px 10px; border-radius: 9px; font-size: 11px; font-weight: 900; box-shadow: 0 1px 3px rgba(0,0,0,0.06); z-index: 5;">
                            <span style="letter-spacing: 0.5px;">MORE</span>
                            <i class="fa-solid fa-chevron-down" style="font-size: 9px;"></i>
                        </div>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[11px] font-bold text-slate-700 uppercase flex items-center justify-between">
                        <span>Have a Promo Code?</span>
                        <span id="promoStatusMessage" class="text-[10px] text-emerald-600 font-bold hidden">Code Applied!</span>
                    </label>
                    <div class="flex items-center space-x-1.5">
                        <input type="text" id="modalPromoInput" placeholder="Enter coupon (e.g. DISCOUNT10)" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-xs uppercase font-mono outline-none focus:border-emerald-500">
                        <button type="button" onclick="applyPromoCodeAjax()" class="px-4 py-2.5 rounded-xl bg-slate-900 text-white font-black text-xs uppercase active:scale-95 transition flex-shrink-0">
                            Apply
                        </button>
                    </div>
                </div>

                <div class="bg-emerald-50 border border-emerald-200 p-3 rounded-2xl space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-600 font-bold">Payable Amount:</span>
                        <div class="text-right">
                            <span id="planOrigPriceStrike" class="hidden text-xs text-slate-400 line-through mr-1 font-mono"></span>
                            <strong id="planCurrentPriceText" class="text-base font-black text-emerald-700 font-mono">0৳</strong>
                            <span id="promoDiscountBadge" class="hidden ml-1 px-1.5 py-0.5 rounded text-[8px] font-black uppercase bg-rose-600 text-white font-mono"></span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between bg-slate-50 border border-slate-200 p-2 rounded-xl">
                    <span class="text-xs font-bold text-slate-700 ml-1">Quantity:</span>
                    <div class="flex items-center space-x-2">
                        <button type="button" onclick="changePurchaseQty(-1)" class="w-8 h-8 rounded-lg bg-white border border-slate-200 font-black text-sm flex items-center justify-center shadow-sm active:scale-95">-</button>
                        <input type="number" id="purchaseQtyInput" value="1" min="1" max="50" readonly class="w-10 text-center font-black text-xs bg-transparent">
                        <button type="button" onclick="changePurchaseQty(1)" class="w-8 h-8 rounded-lg bg-white border border-slate-200 font-black text-sm flex items-center justify-center shadow-sm active:scale-95">+</button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs font-bold pt-1">
                    <a id="modalSetupLink" href="#" target="_blank" class="py-2.5 px-3 rounded-xl bg-blue-50 border border-blue-200 text-blue-600 hover:bg-blue-100 flex items-center justify-center gap-1.5 transition">
                        <i class="fa-solid fa-circle-play"></i> <span>Setup Video</span>
                    </a>
                    <a id="modalDownloadLink" href="#" target="_blank" class="py-2.5 px-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 hover:bg-emerald-100 flex items-center justify-center gap-1.5 transition">
                        <i class="fa-solid fa-download"></i> <span>Download File</span>
                    </a>
                </div>

                <button type="button" onclick="proceedToPaymentSelectScreen()" class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-wider flex items-center justify-center gap-2 shadow-lg active:scale-95 transition">
                    <span>Pay &amp; Get License</span> <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>

            <!-- Step 2: Payment Gateway -->
            <div id="modalViewPaymentSelect" class="hidden space-y-3">
                <div class="flex items-center justify-between pb-1 border-b border-slate-100">
                    <button type="button" onclick="goBackToPlanStep()" class="text-xs font-bold text-slate-500 flex items-center gap-1">
                        <i class="fa-solid fa-arrow-left"></i> <span>Back</span>
                    </button>
                    <span class="text-xs font-black text-slate-800 uppercase">Payment Verification</span>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-500 uppercase">Select Payment Option:</label>
                    <div class="grid grid-cols-3 gap-1.5 text-xs font-bold">
                        <button type="button" onclick="selectCheckoutType('wallet', this)" class="checkout-type-pill border-emerald-500 bg-emerald-50 text-emerald-800 p-2.5 rounded-xl border-2 text-center transition flex flex-col items-center">
                            <i class="fa-solid fa-wallet text-base mb-1"></i><span>Wallet</span>
                        </button>
                        <button type="button" onclick="selectCheckoutType('bdt', this)" class="checkout-type-pill border-slate-200 bg-white text-slate-700 p-2.5 rounded-xl border-2 text-center transition flex flex-col items-center">
                            <span class="text-base mb-1">🇧🇩</span><span>BDT</span>
                        </button>
                        <button type="button" onclick="selectCheckoutType('usdt', this)" class="checkout-type-pill border-slate-200 bg-white text-slate-700 p-2.5 rounded-xl border-2 text-center transition flex flex-col items-center">
                            <i class="fa-solid fa-coins text-base mb-1 text-amber-500"></i><span>USDT</span>
                        </button>
                    </div>
                </div>

                <div id="checkoutSectionWallet" class="space-y-3">
                    <div class="bg-slate-50 border border-slate-200 p-3 rounded-2xl space-y-1">
                        <span class="text-[10px] text-slate-500 block uppercase">Available Balance:</span>
                        <strong class="text-lg font-black text-slate-900 font-mono"><span id="walletCheckoutBalance"><?= number_format($userBalanceBdt, 0) ?></span><span id="walletCheckoutSymbol">৳</span></strong>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 p-3 rounded-2xl flex items-center justify-between">
                        <span class="text-[10px] text-amber-700 font-bold uppercase">Total to Pay</span>
                        <strong id="walletPayAmount" class="text-lg font-black text-amber-700 font-mono">৳0</strong>
                    </div>
                    <div id="walletCheckoutError" class="hidden p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 text-[11px] font-bold text-center"></div>
                    <button type="button" id="btnWalletCheckout" onclick="submitWalletCheckout()" class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-wider shadow-lg active:scale-95 transition">
                        <i class="fa-solid fa-shield-check mr-1"></i> Pay with Wallet
                    </button>
                </div>

                <div id="checkoutSectionGateway" class="hidden space-y-3">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase">Select Payment Channel:</label>
                        <div id="checkoutGatewayMethods" class="grid grid-cols-2 gap-1.5 text-xs font-bold"></div>
                    </div>

                    <!-- BANNER (if admin configured) -->
                    <div id="checkoutBannerContainer" class="hidden">
                        <img id="checkoutBannerImg" src="" alt="Banner" class="w-full rounded-xl border border-slate-200 shadow-sm">
                    </div>

                    <div class="bg-amber-50 border border-amber-200 p-3 rounded-2xl flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-amber-700 font-bold uppercase block">Amount to Pay</span>
                            <span id="checkoutPayHint" class="text-[10px] text-slate-500">এই পরিমাণ টাকা সেন্ড করুন</span>
                        </div>
                        <strong id="checkoutPayAmount" class="text-xl font-black text-amber-700 font-mono">৳0</strong>
                    </div>

                    <div class="bg-emerald-50 border border-emerald-200 p-3 rounded-2xl space-y-1.5 text-xs">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-1.5 mb-0.5 flex-wrap">
                                    <img id="checkoutMethodLogo" src="" alt="" class="hidden w-5 h-5 rounded object-cover border border-slate-200 bg-white">
                                    <span id="checkoutMethodTypeLabel" class="text-[10px] text-slate-500 uppercase">Account Number:</span>
                                    <span id="checkoutMethodTypeBadge" class="hidden px-1.5 py-0.5 rounded text-[9px] font-black uppercase"></span>
                                </div>
                                <strong id="checkoutMethodNumber" class="text-slate-900 text-sm font-mono tracking-wider">01700000000</strong>
                            </div>
                            <button type="button" onclick="copyToClipboard(document.getElementById('checkoutMethodNumber').innerText, 'Number')" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-[10px] font-bold active:scale-95 transition">
                                Copy
                            </button>
                        </div>
                        <p id="checkoutInstructions" class="text-[10px] text-slate-500 leading-tight whitespace-pre-line">Send money to this number, then enter your TrxID below for auto delivery.</p>
                        <div id="checkoutQrContainer" class="hidden pt-1 text-center">
                            <span class="text-[10px] text-slate-500 block uppercase mb-1">Scan QR to pay</span>
                            <img id="checkoutQrImg" src="" alt="QR Code" class="mx-auto w-40 h-40 object-contain rounded-xl border border-slate-200 bg-white p-1">
                        </div>
                    </div>

                    <div class="space-y-1 text-xs">
                        <label class="text-[11px] font-bold text-slate-700 block">Transaction ID (TrxID) <span class="text-rose-500">*</span></label>
                        <input type="text" id="checkoutTrxIdInput" placeholder="Enter TrxID / Order ID (e.g. 9B8C7A)" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 uppercase font-mono outline-none focus:border-emerald-500 shadow-inner">
                    </div>

                    <div id="checkoutOrderError" class="hidden p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 text-[11px] font-bold text-center"></div>

                    <button type="button" id="btnVerifyCheckout" onclick="submitCheckoutVerification()" class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-wider shadow-lg active:scale-95 transition">
                        <i class="fa-solid fa-shield-check mr-1"></i> Verify &amp; Deliver Key
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- =============================================================== -->
    <!-- 10B. FULL PRODUCT DETAILS MODAL (opened from "More" button)     -->
    <!-- =============================================================== -->
    <div id="fullProductDetailsModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-[99999] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-md p-5 space-y-3 shadow-2xl border border-slate-200 relative my-auto">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                <h3 class="text-sm font-black text-slate-900 uppercase flex items-center gap-2">
                    <i class="fa-solid fa-circle-info text-emerald-600"></i> Product Details
                </h3>
                <button type="button" onclick="closeModal('fullProductDetailsModal')" class="w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs active:scale-90 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div id="fullProductDetailsTitle" class="text-xs font-black text-emerald-700 uppercase truncate"></div>
            <div id="fullProductDetailsBody" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-700 font-sans leading-relaxed max-h-[60vh] overflow-y-auto whitespace-pre-line break-words"></div>
            <button type="button" onclick="closeModal('fullProductDetailsModal')" class="w-full py-2.5 rounded-xl bg-slate-900 text-white font-bold text-xs uppercase shadow-md active:scale-95 transition">
                Close
            </button>
        </div>
    </div>

    <!-- =============================================================== -->
    <!-- 11. KEY DELIVERY SUCCESS MODAL                                  -->
    <!-- =============================================================== -->
    <div id="keySuccessModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[99999] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-sm p-6 space-y-4 shadow-2xl border-2 border-emerald-500 text-center relative my-auto">
            <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-3xl shadow-inner">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            
            <div class="space-y-1">
                <h3 class="text-base font-black text-slate-900 uppercase">License Delivered!</h3>
                <p id="modalSuccessProductTitle" class="text-xs text-emerald-700 font-bold"></p>
            </div>

            <div id="modalKeysContainer" class="space-y-2 max-h-48 overflow-y-auto no-scrollbar font-mono text-xs"></div>

            <button type="button" onclick="closeKeySuccessModal()" class="w-full py-3 rounded-xl bg-slate-900 text-white font-bold text-xs uppercase shadow-md active:scale-95 transition">
                Done &bull; Thank You
            </button>
        </div>
    </div>

    <!-- =============================================================== -->
    <!-- 12. PAYMENT RESULT MODAL                                        -->
    <!-- =============================================================== -->
    <div id="paymentResultModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-[99999] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-sm p-6 space-y-4 shadow-2xl border border-slate-200 text-center relative">
            <div id="resultModalIconContainer" class="w-16 h-16 mx-auto rounded-full flex items-center justify-center text-3xl"></div>
            <h3 id="resultModalHeading" class="text-base font-black text-slate-900">Result</h3>
            <div id="resultModalBodyBox" class="p-3 bg-slate-50 rounded-2xl border border-slate-100 text-xs text-slate-700 font-bengali text-left"></div>
            <button type="button" onclick="closePaymentResultModal()" class="w-full py-3 rounded-xl bg-emerald-600 text-white font-bold text-xs uppercase shadow-md active:scale-95 transition">
                Close
            </button>
        </div>
    </div>

    <!-- =============================================================== -->
    <!-- 13. AUTH MODAL & FORGOT PASSWORD                                -->
    <!-- =============================================================== -->
    <div id="authModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-[9999] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-sm p-6 space-y-4 shadow-2xl border border-slate-200 relative my-auto">
            <button type="button" onclick="closeAuthModal()" class="absolute top-4 right-4 w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="text-center space-y-1">
                <?php 
                    $authLogo = !empty($settings['loading_logo']) ? $settings['loading_logo'] : (!empty($settings['site_logo']) ? $settings['site_logo'] : '');
                    if (!empty($authLogo)): 
                ?>
                    <div class="w-16 h-16 mx-auto rounded-2xl overflow-hidden border border-slate-200 shadow-md mb-2 flex items-center justify-center bg-white p-1">
                        <img src="<?= htmlspecialchars($authLogo) ?>" alt="Logo" class="w-full h-full object-cover rounded-xl">
                    </div>
                <?php endif; ?>
                <h3 class="text-base font-black text-slate-900 uppercase"><?= htmlspecialchars($storeTitleName) ?></h3>
                <p class="text-[11px] text-slate-500">Sign in to your client console</p>
            </div>

            <!-- REAL GOOGLE LOGIN BUTTON -->
            <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="w-full py-2.5 rounded-xl bg-white border border-slate-300 text-slate-700 font-bold text-xs flex items-center justify-center gap-2 shadow-sm hover:bg-slate-50 transition active:scale-95">
                <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="w-4 h-4" alt="Google">
                Continue with Google
            </a>

            <div class="flex items-center gap-2 py-1">
                <hr class="flex-grow border-slate-200">
                <span class="text-[10px] text-slate-400 font-bold uppercase">Or login manually</span>
                <hr class="flex-grow border-slate-200">
            </div>

            <div class="flex bg-slate-100 p-1 rounded-xl text-xs font-bold">
                <button type="button" id="tabAuthLogin" onclick="switchAuthTab('login')" class="flex-1 py-1.5 rounded-lg bg-emerald-600 text-white transition">Log In</button>
                <button type="button" id="tabAuthRegister" onclick="switchAuthTab('register')" class="flex-1 py-1.5 rounded-lg text-slate-600 transition">Register</button>
            </div>

            <form id="formAuthLogin" method="POST" class="space-y-3 text-xs">
                <input type="hidden" name="auth_action" value="user_login">
                <div>
                    <label class="text-[10px] font-bold text-slate-600 uppercase block mb-1">Username / Email</label>
                    <input type="text" name="login_identity" required placeholder="Username or Email" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl outline-none focus:border-emerald-500 font-mono">
                </div>
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="text-[10px] font-bold text-slate-600 uppercase">Password</label>
                        <button type="button" onclick="openForgotPasswordModal()" class="text-[10px] font-bold text-emerald-600 hover:underline">Forgot?</button>
                    </div>
                    <input type="password" name="login_password" required placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl outline-none focus:border-emerald-500 font-mono">
                </div>
                <button type="submit" class="w-full py-3 rounded-xl bg-emerald-600 text-white font-bold text-xs uppercase shadow-md active:scale-95 transition">
                    Sign In
                </button>
            </form>

            <form id="formAuthRegister" method="POST" class="hidden space-y-2 text-xs">
                <input type="hidden" name="auth_action" value="user_register">
                <div>
                    <label class="text-[10px] font-bold text-slate-600 uppercase block mb-0.5">Username</label>
                    <input type="text" name="reg_username" required placeholder="username" class="w-full bg-slate-50 border border-slate-200 p-2 rounded-xl outline-none focus:border-emerald-500 font-mono">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-600 uppercase block mb-0.5">Email (Optional)</label>
                    <input type="email" name="reg_email" placeholder="email@gmail.com" class="w-full bg-slate-50 border border-slate-200 p-2 rounded-xl outline-none focus:border-emerald-500 font-mono">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-600 uppercase block mb-0.5">Password</label>
                    <input type="password" name="reg_password" required placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 p-2 rounded-xl outline-none focus:border-emerald-500 font-mono">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-emerald-700 uppercase block mb-0.5">Referral Code (Optional e.g. 13183)</label>
                    <input type="text" id="regReferralInput" name="reg_referral" placeholder="13183" class="w-full bg-emerald-50 border border-emerald-300 p-2 rounded-xl outline-none focus:border-emerald-600 font-mono font-bold text-emerald-800">
                </div>
                <button type="submit" class="w-full py-3 rounded-xl bg-emerald-600 text-white font-bold text-xs uppercase shadow-md active:scale-95 transition mt-1">
                    Register Account
                </button>
            </form>
        </div>
    </div>

    <!-- FORGOT PASSWORD MODAL -->
    <div id="forgotPasswordModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-[99999] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-sm p-6 space-y-4 shadow-2xl border border-slate-200 relative my-auto text-center">
            <button type="button" onclick="closeModal('forgotPasswordModal')" class="absolute top-4 right-4 w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="w-12 h-12 mx-auto rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-key"></i>
            </div>
            <h3 class="text-base font-black text-slate-900 uppercase">Reset Password</h3>
            <p class="text-xs text-slate-500">Enter your registered Gmail. We will send a secure reset link to your inbox.</p>
            
            <form id="forgotPassForm" onsubmit="handleForgotPass(event)" class="space-y-3 text-left">
                <div>
                    <label class="text-[10px] font-bold text-slate-600 uppercase block mb-1">Gmail Address</label>
                    <input type="email" id="resetEmailInput" required placeholder="yourname@gmail.com" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl outline-none focus:border-emerald-500 font-mono">
                </div>
                <div id="forgotPassMsg" class="hidden text-xs font-bold text-center"></div>
                <button type="submit" class="w-full py-3 rounded-xl bg-slate-900 text-white font-bold text-xs uppercase shadow-md active:scale-95 transition">
                    Send Reset Link
                </button>
            </form>
        </div>
    </div>

    <!-- =============================================================== -->
    <!-- 14. SPIN & WIN MODAL                                            -->
    <!-- =============================================================== -->
    <div id="spinWheelModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[9999] hidden flex items-center justify-center p-3 select-none">
        <div class="bg-white rounded-3xl w-full max-w-xs p-5 space-y-3.5 shadow-2xl border border-slate-200 text-center relative my-auto">
            <button type="button" onclick="closeSpinWheelModal()" class="absolute top-4 right-4 w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs">
                <i class="fa-solid fa-xmark"></i>
            </button>
            
            <h3 class="text-sm font-black text-slate-900 uppercase">Lucky Spin &amp; Win</h3>

            <div class="flex items-center justify-center gap-2 text-[11px] font-bold">
                <span class="px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700">Spins: <b id="spinCountText">0</b></span>
                <span id="spinPrizeRangeText" class="px-2.5 py-1 rounded-full bg-slate-50 border border-slate-200 text-slate-600"></span>
            </div>
            
            <div class="relative w-[240px] h-[240px] mx-auto flex items-center justify-center my-2">
                <div class="absolute -top-1 left-1/2 -translate-x-1/2 z-10 w-0 h-0 border-l-[9px] border-r-[9px] border-t-[16px] border-l-transparent border-r-transparent border-t-rose-500 drop-shadow"></div>
                <canvas id="wheelCanvas" width="230" height="230" class="rounded-full shadow-inner border-4 border-emerald-500"></canvas>
                <div onclick="triggerWheelSpin()" class="absolute w-14 h-14 rounded-full bg-emerald-600 text-white font-black text-[11px] flex items-center justify-center border-4 border-white shadow-lg cursor-pointer active:scale-90 transition">
                    SPIN
                </div>
            </div>

            <div class="space-y-1.5 text-left">
                <div class="flex justify-between text-[10px] font-bold text-slate-500">
                    <span id="spinProgressLabel">Spend progress</span>
                    <span id="spinProgressValue"></span>
                </div>
                <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden">
                    <div id="spinProgressBar" class="h-full bg-emerald-500 rounded-full transition-all" style="width:0%"></div>
                </div>
            </div>
            <p id="spinHintText" class="text-[11px] text-slate-500 font-bengali"></p>
            <p id="spinReferText" class="hidden text-[11px] text-emerald-700 font-bold font-bengali"></p>
        </div>
    </div>

    <!-- =============================================================== -->
    <!-- 15. EDIT PROFILE MODAL                                          -->
    <!-- =============================================================== -->
    <div id="editProfileModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9999] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-sm p-5 space-y-3.5 shadow-2xl border border-slate-200 relative my-auto">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                <h3 class="text-xs sm:text-sm font-bold text-slate-800">Edit Profile Details</h3>
                <button onclick="closeModal('editProfileModal')" class="text-slate-400 p-1"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="space-y-2.5 text-xs">
                <input type="hidden" name="action" value="update_profile">
                <div>
                    <label class="text-[10px] text-slate-500 block">Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required class="w-full bg-slate-50 border border-slate-200 p-2 rounded-xl outline-none focus:border-emerald-500 font-mono">
                </div>
                <div>
                    <label class="text-[10px] text-slate-500 block">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 p-2 rounded-xl outline-none focus:border-emerald-500 font-mono">
                </div>
                <div>
                    <label class="text-[10px] text-slate-500 block">Current Password (Required)</label>
                    <input type="password" name="current_password" required placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 p-2 rounded-xl outline-none focus:border-emerald-500 font-mono">
                </div>
                <div>
                    <label class="text-[10px] text-slate-500 block">New Password (Optional)</label>
                    <input type="password" name="new_password" placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 p-2 rounded-xl outline-none focus:border-emerald-500 font-mono">
                </div>
                <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 text-white font-bold uppercase text-[11px] shadow-md active:scale-95 transition">
                    Save Changes
                </button>
            </form>
        </div>
    </div>

    <!-- =============================================================== -->
    <!-- 16. PRODUCT MAINTENANCE POPUP (DYNAMIC RULES & DETAILS)        -->
    <!-- =============================================================== -->
    <div id="maintenancePopup" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-[99999] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-sm p-5 sm:p-6 space-y-3.5 shadow-2xl border border-rose-200 text-center relative my-auto">
            
            <button type="button" onclick="closeModal('maintenancePopup')" class="absolute top-3.5 right-3.5 w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center text-xs active:scale-90 transition">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="w-14 h-14 mx-auto rounded-full bg-rose-100 text-rose-600 flex items-center justify-center text-2xl shadow-inner animate-pulse">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <div class="space-y-1">
                <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-50 text-rose-600 border border-rose-200 font-mono">
                    TEMPORARILY UNAVAILABLE
                </span>
                <h3 id="maintPopupProdName" class="text-sm sm:text-base font-black text-slate-900 uppercase truncate">
                    Product Maintenance
                </h3>
            </div>

            <!-- Custom Rules / Conditions Box -->
            <div class="space-y-1 text-left">
                <span class="text-[10px] font-bold text-slate-500 uppercase flex items-center gap-1">
                    <i class="fa-solid fa-clipboard-list text-rose-500"></i> Rules &amp; Information:
                </span>
                <div id="maintenancePopupText" class="w-full bg-rose-50/70 border border-rose-100 rounded-2xl p-3.5 text-xs text-slate-700 font-sans leading-relaxed whitespace-pre-line max-h-56 overflow-y-auto break-words shadow-inner">
                    This product is currently under maintenance. Please check back later.
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-2 pt-1 font-sans">
                <?php if (!empty($settings['support_telegram'])): ?>
                    <a href="<?= htmlspecialchars($settings['support_telegram']) ?>" target="_blank" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 text-white font-bold text-xs uppercase flex items-center justify-center gap-2 shadow active:scale-95 transition">
                        <i class="fa-brands fa-telegram"></i> <span>Contact Admin Support</span>
                    </a>
                <?php endif; ?>
                <button type="button" onclick="closeModal('maintenancePopup')" class="w-full py-2.5 rounded-xl bg-slate-900 text-white font-bold text-xs uppercase shadow-md active:scale-95 transition">
                    Close
                </button>
            </div>

        </div>
    </div>

    <!-- =============================================================== -->
    <!-- 17. CLIENT JAVASCRIPT ENGINE                                    -->
    <!-- =============================================================== -->
    <script>
        const allProductsCatalog      = <?= json_encode($catalogProducts, JSON_UNESCAPED_UNICODE) ?>;
        let allActivePaymentMethods = <?= json_encode($activePaymentMethods, JSON_UNESCAPED_UNICODE) ?>;
        const userKeysByPlan          = <?= json_encode($userKeysByPlan, JSON_UNESCAPED_UNICODE) ?>;
        const currentBdtRate          = <?= (float)$effectiveBdtRate ?>;
        let currentUserBalanceUsdt  = <?= (float)$userBalanceUsdt ?>;
        let currentUserBalanceBdt   = <?= (float)$userBalanceBdt ?>;
        const isUserLoggedIn          = <?= $isLoggedIn ? 'true' : 'false' ?>;
        const totalBannersCount       = <?= (int)count($activeBanners) ?>;
        
        let currentCurrency = 'BDT';

        let activeModalProduct        = null;
        let activeSelectedPlan        = null;
        let activeCheckoutType        = 'wallet';
        let activeCheckoutMethod      = allActivePaymentMethods.length > 0 ? allActivePaymentMethods[0] : null;
        let currentSlideIdx           = 0;
        let isWheelSpinning           = false;
        let spinSegments              = [];
        let appliedPromoDiscount      = 0.00;
        let appliedPromoCode          = '';

        // Drawer / Currency / Support
        function toggleUserSideDrawer() {
            document.getElementById('userSideDrawer').classList.toggle('open');
        }

        function toggleCurrencySelector() {
            document.getElementById('currencyModal').classList.toggle('hidden');
        }

        function toggleMultiSupportModal() {
            document.getElementById('multiSupportModal').classList.toggle('hidden');
        }

        function setCurrency(cur) {
            currentCurrency = cur;
            try { localStorage.setItem('selected_currency', cur); } catch (e) {}
            
            const btnBDT = document.getElementById('btnCurrencyBDT');
            const btnUSDT = document.getElementById('btnCurrencyUSDT');
            const textLabel = document.getElementById('currencyTextLabel');
            
            if (cur === 'BDT') {
                textLabel.innerText = 'BDT';
                btnBDT.className = 'w-full p-3 rounded-2xl border-2 border-emerald-500 bg-emerald-50 flex items-center justify-between font-bold text-xs';
                btnUSDT.className = 'w-full p-3 rounded-2xl border border-slate-200 hover:border-emerald-500 bg-white flex items-center justify-between font-bold text-xs text-slate-700';
            } else {
                textLabel.innerText = 'USDT';
                btnUSDT.className = 'w-full p-3 rounded-2xl border-2 border-emerald-500 bg-emerald-50 flex items-center justify-between font-bold text-xs';
                btnBDT.className = 'w-full p-3 rounded-2xl border border-slate-200 hover:border-emerald-500 bg-white flex items-center justify-between font-bold text-xs text-slate-700';
            }
            
            updateAllCurrencyDisplays();
            toggleCurrencySelector();
            showToast(`Currency set to ${cur}`, 'success');
        }

        function updateAllCurrencyDisplays() {
            document.querySelectorAll('.price-display').forEach(el => {
                const bdtVal = parseFloat(el.getAttribute('data-bdt'));
                const usdtVal = parseFloat(el.getAttribute('data-usdt'));
                
                if (currentCurrency === 'BDT') {
                    el.innerText = `${Math.round(bdtVal)}৳`;
                } else {
                    el.innerText = `${usdtVal.toFixed(2)}$`;
                }
            });

            const profileBalText = document.getElementById('mainProfileBalance');
            const profileSymText = document.getElementById('mainProfileCurrencySymbol');
            
            if (profileBalText && profileSymText) {
                if (currentCurrency === 'BDT') {
                    profileBalText.innerText = Math.round(currentUserBalanceBdt).toLocaleString();
                    profileSymText.innerText = '৳';
                } else {
                    profileBalText.innerText = currentUserBalanceUsdt.toFixed(2);
                    profileSymText.innerText = '$';
                }
            }

            const wcBal = document.getElementById('walletCheckoutBalance');
            const wcSym = document.getElementById('walletCheckoutSymbol');
            if (wcBal) {
                wcBal.textContent = currentCurrency === 'BDT' ? Math.round(currentUserBalanceBdt).toLocaleString() : Number(currentUserBalanceUsdt).toFixed(2);
                if (wcSym) wcSym.textContent = currentCurrency === 'BDT' ? '৳' : '$';
            }

            // plan dropdown texts in the open product modal
            const planSel = document.getElementById('modalPlanSelect');
            if (planSel && activeModalProduct && activeModalProduct.plans) {
                Array.from(planSel.options).forEach(opt => {
                    const pl = activeModalProduct.plans.find(p => String(p.plan_id) === String(opt.value));
                    if (pl) opt.innerText = `${pl.validity} — ${currentCurrency === 'BDT' ? Math.round(pl.price_bdt)+'৳' : Number(pl.price).toFixed(2)+'$'}` + (pl.discount_percent > 0 ? ` (-${pl.discount_percent}%)` : '');
                });
            }

            if (activeSelectedPlan) {
                updatePlanPriceDisplay(activeSelectedPlan);
            }
            try { updateCheckoutPayAmount(); } catch (e) {}
        }

        // page restored from back/forward cache -> reload so prices & balance are fresh
        window.addEventListener('pageshow', function (e) { if (e.persisted) window.location.reload(); });

        function openNotificationsModal() { document.getElementById('notificationsModal').classList.remove('hidden'); }
        function closeNotificationsModal() { document.getElementById('notificationsModal').classList.add('hidden'); }
        function openEditProfileModal() { document.getElementById('editProfileModal').classList.remove('hidden'); }
        function closeModal(id) { const el = document.getElementById(id); if (!el) return; el.classList.add('hidden'); el.style.display = ''; }

        function showProductMaintenancePopup(msg, prodName) {
            const nameEl = document.getElementById('maintPopupProdName');
            const textEl = document.getElementById('maintenancePopupText');
            
            if (nameEl) nameEl.innerText = prodName || 'Under Maintenance';
            if (textEl) {
                let cleanNotice = (msg && msg.toString().trim() !== '') ? msg.toString() : "⚠️ Rules & Conditions:\nআমাদের এই প্রোডাক্টটির কাজ চলছে। অতি শীঘ্রই প্রোডাক্টটি পুনরায় চালু করা হবে।";
                textEl.innerText = cleanNotice;
            }
            
            const popup = document.getElementById('maintenancePopup');
            if (popup) {
                popup.style.display = 'flex';
                popup.classList.remove('hidden');
            }
        }

        function handleAccountNavClick() {
            if (isUserLoggedIn) {
                goToPage('profile');
            } else {
                openAuthModal('login');
            }
        }

        function handleNavClick(pageId) {
            if (!isUserLoggedIn && (pageId === 'history' || pageId === 'resetkey' || pageId === 'mykey')) {
                openAuthModal('login');
                return;
            }
            goToPage(pageId);
        }

        function goToPage(pageId) {
            document.querySelectorAll('.page-section').forEach(p => p.classList.add('hidden'));
            const activeEl = document.getElementById('page-' + pageId);
            if (activeEl) activeEl.classList.remove('hidden');

            document.querySelectorAll('.dock-nav-item').forEach(btn => {
                btn.classList.remove('active');
                const box = btn.querySelector('.dock-icon-wrapper');
                if (box) box.classList.remove('dock-active-circle');
            });

            const curNav = document.getElementById('bnav-' + pageId);
            if (curNav) {
                curNav.classList.add('active');
                const box = curNav.querySelector('.dock-icon-wrapper');
                if (box) box.classList.add('dock-active-circle');
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Category Filter
        function filterByCategory(category, btn) {
            document.querySelectorAll('.cat-pill-white').forEach(el => el.classList.remove('active'));
            btn.classList.add('active');

            const cards = document.querySelectorAll('.prod-item-box');
            cards.forEach(c => {
                const cat = (c.getAttribute('data-category') || '').toUpperCase();
                if (category === 'ALL' || cat === category.toUpperCase()) {
                    c.classList.remove('hidden');
                    c.style.animation = 'none';
                    void c.offsetWidth;
                    c.style.animation = 'fadeInSlideUp 0.5s ease-out forwards';
                } else {
                    c.classList.add('hidden');
                }
            });
        }

        // Banner carousel
        function goToSlide(idx) {
            currentSlideIdx = idx;
            const track = document.getElementById('carouselTrack');
            if (track) track.style.transform = `translateX(-${currentSlideIdx * 100}%)`;

            document.querySelectorAll('.carousel-dot').forEach((dot, i) => {
                dot.className = (i === currentSlideIdx) 
                    ? 'carousel-dot w-4 h-2 rounded-full bg-emerald-600 transition-all'
                    : 'carousel-dot w-2 h-2 rounded-full bg-white/70 transition-all';
            });
        }

        // Live history tabs
        function switchLiveTab(tabId, btn) {
            document.querySelectorAll('.live-tab-btn').forEach(b => {
                b.classList.remove('active');
                b.classList.add('inactive');
            });
            btn.classList.remove('inactive');
            btn.classList.add('active');

            document.querySelectorAll('.live-tab-content').forEach(c => c.classList.add('hidden'));
            document.getElementById('tabContent-' + tabId).classList.remove('hidden');
        }

        // Promo code
        async function applyPromoCodeAjax() {
            const promoInput = document.getElementById('modalPromoInput').value.trim();
            const statusMsg  = document.getElementById('promoStatusMessage');

            if (!promoInput) {
                showToast('Please enter a coupon code!', 'error');
                return;
            }

            const fd = new FormData();
            fd.append('ajax_action', 'apply_promo_code');
            fd.append('promo_code', promoInput);

            try {
                const res = await fetch('index.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.status === 'success') {
                    appliedPromoDiscount = parseFloat(data.discount_percent);
                    appliedPromoCode     = data.code;

                    statusMsg.innerText = `${appliedPromoDiscount}% Discount Applied!`;
                    statusMsg.classList.remove('hidden');

                    const badge = document.getElementById('promoDiscountBadge');
                    badge.innerText = `-${appliedPromoDiscount}% PROMO`;
                    badge.classList.remove('hidden');

                    updatePlanPriceDisplay(activeSelectedPlan);
                    showToast(data.message, 'success');
                } else {
                    appliedPromoDiscount = 0.00;
                    appliedPromoCode     = '';
                    statusMsg.classList.add('hidden');
                    document.getElementById('promoDiscountBadge').classList.add('hidden');
                    updatePlanPriceDisplay(activeSelectedPlan);
                    showToast(data.message, 'error');
                }
            } catch(e) {
                showToast('Coupon check failed!', 'error');
            }
        }

        // Product Modal
        function openProductModal(productId) {
            const prod = allProductsCatalog[productId];
            if (!prod) return;
            
            // মেইনটেন্যান্স চেক ১০০% লক করা হলো
            if (!prod.is_active || prod.is_active === 0 || prod.is_active === '0' || prod.is_active === false) {
                showProductMaintenancePopup(prod.maintenance_msg || 'This product is currently under maintenance. Please check back later.', prod.name || '');
                return;
            }

            activeModalProduct   = prod;
            appliedPromoDiscount = 0.00;
            appliedPromoCode     = '';

            document.getElementById('modalProdName').innerText = prod.name;
            const tagEl = document.getElementById('modalProdTag');
            if (tagEl) tagEl.innerText = prod.tags || 'OFFICIAL PASS';
            
            // NEW: 2-line preview inside purchase modal
            document.getElementById('modalProdDetailsPreview').innerText = prod.description || 'No detailed description available for this product.';
            document.getElementById('modalProdDetailsMoreBtn').style.display = 'inline-flex';

            const thumbBox = document.getElementById('modalProdThumb');
            if (prod.logo_url) {
                thumbBox.innerHTML = `<img src="${prod.logo_url}" class="w-full h-full object-cover">`;
            } else {
                thumbBox.innerHTML = `<i class="fa-solid fa-shield-halved"></i>`;
            }

            document.getElementById('modalSetupLink').href      = prod.setup_link || '#';
            document.getElementById('modalDownloadLink').href   = prod.download_link || '#';
            document.getElementById('modalPromoInput').value    = '';
            document.getElementById('promoStatusMessage').classList.add('hidden');
            document.getElementById('promoDiscountBadge').classList.add('hidden');

            const selectEl = document.getElementById('modalPlanSelect');
            selectEl.innerHTML = '';

            if (prod.plans && prod.plans.length > 0) {
                prod.plans.forEach(pl => {
                    const opt = document.createElement('option');
                    opt.value = pl.plan_id;
                    opt.innerText = `${pl.validity} — ${currentCurrency === 'BDT' ? Math.round(pl.price_bdt)+'৳' : pl.price.toFixed(2)+'$'}` + (pl.discount_percent > 0 ? ` (-${pl.discount_percent}%)` : '');
                    selectEl.appendChild(opt);
                });
                activeSelectedPlan = prod.plans[0];
                updatePlanPriceDisplay(activeSelectedPlan);
            }

            document.getElementById('purchaseQtyInput').value = 1;
            goBackToPlanStep();
            document.getElementById('productPurchaseModal').classList.remove('hidden');
        }

        // NEW: open full product details modal
        function openFullProductDetails() {
            if (!activeModalProduct) return;
            document.getElementById('fullProductDetailsTitle').innerText = activeModalProduct.name;
            document.getElementById('fullProductDetailsBody').innerText = activeModalProduct.description || 'No detailed description available for this product.';
            document.getElementById('fullProductDetailsModal').classList.remove('hidden');
        }

        function closeProductPurchaseModal() {
            document.getElementById('productPurchaseModal').classList.add('hidden');
        }

        function onPlanSelectChange(planId) {
            if (!activeModalProduct) return;
            const found = activeModalProduct.plans.find(p => parseInt(p.plan_id) === parseInt(planId));
            if (found) {
                activeSelectedPlan = found;
                updatePlanPriceDisplay(found);
            }
        }

        function updatePlanPriceDisplay(plan) {
            if (!plan) return;
            const qty = parseInt(document.getElementById('purchaseQtyInput').value) || 1;
            
            const unitPriceBdt = parseFloat(plan.price_bdt) || 0;
            const unitPriceUsdt = parseFloat(plan.price) || 0;
            
            const origUnitPriceBdt = parseFloat(plan.original_bdt) || unitPriceBdt;
            const origUnitPriceUsdt = parseFloat(plan.original_usd) || unitPriceUsdt;

            let baseTotal = currentCurrency === 'BDT' ? unitPriceBdt * qty : unitPriceUsdt * qty;
            let origTotal = currentCurrency === 'BDT' ? origUnitPriceBdt * qty : origUnitPriceUsdt * qty;

            const strikeEl = document.getElementById('planOrigPriceStrike');
            const currentEl = document.getElementById('planCurrentPriceText');

            const sym = currentCurrency === 'BDT' ? '৳' : '$';

            if (appliedPromoDiscount > 0) {
                strikeEl.innerText = `${sym}${baseTotal.toFixed(currentCurrency === 'BDT' ? 0 : 2)}`;
                strikeEl.classList.remove('hidden');

                baseTotal = baseTotal * (1 - (appliedPromoDiscount / 100));
            } else if (origTotal > baseTotal) {
                strikeEl.innerText = `${sym}${origTotal.toFixed(currentCurrency === 'BDT' ? 0 : 2)}`;
                strikeEl.classList.remove('hidden');
            } else {
                strikeEl.classList.add('hidden');
            }

            currentEl.innerText = `${sym}${baseTotal.toFixed(currentCurrency === 'BDT' ? 0 : 2)}`;
        }

        function changePurchaseQty(val) {
            const input = document.getElementById('purchaseQtyInput');
            let current = parseInt(input.value) || 1;
            current = Math.max(1, Math.min(50, current + val));
            input.value = current;
            if (activeSelectedPlan) updatePlanPriceDisplay(activeSelectedPlan);
            updateCheckoutPayAmount();
        }

        function proceedToPaymentSelectScreen() {
            if (!isUserLoggedIn) {
                closeProductPurchaseModal();
                openAuthModal('login');
                showToast('Please login to purchase!', 'error');
                return;
            }

            document.getElementById('modalViewPlanSelect').classList.add('hidden');
            document.getElementById('modalViewPaymentSelect').classList.remove('hidden');
            
            // FIXED: Make sure wallet is selected and gateway list is cleared
            selectCheckoutType('wallet', document.querySelector('.checkout-type-pill'));
            updateCheckoutPayAmount();
            refreshPaymentMethods();
        }

        function goBackToPlanStep() {
            document.getElementById('modalViewPaymentSelect').classList.add('hidden');
            document.getElementById('modalViewPlanSelect').classList.remove('hidden');
            document.getElementById('checkoutOrderError').classList.add('hidden');
        }

        // FIXED: BDT/USDT glitch completely rewritten
        function selectCheckoutType(type, btn, keepMethodId) {
            activeCheckoutType = type;

            const pills = document.querySelectorAll('.checkout-type-pill');
            if (!btn) btn = pills[{ wallet: 0, bdt: 1, usdt: 2 }[type]] || null;

            // Reset all type pills
            pills.forEach(b => {
                b.className = 'checkout-type-pill border-slate-200 bg-white text-slate-700 p-2.5 rounded-xl border-2 text-center transition flex flex-col items-center';
            });
            if(btn) btn.className = 'checkout-type-pill border-emerald-500 bg-emerald-50 text-emerald-800 p-2.5 rounded-xl border-2 text-center transition flex flex-col items-center';

            updateCheckoutPayAmount();

            if (type === 'wallet') {
                document.getElementById('checkoutSectionWallet').classList.remove('hidden');
                document.getElementById('checkoutSectionGateway').classList.add('hidden');
                return;
            }

            // Gateway mode
            document.getElementById('checkoutSectionWallet').classList.add('hidden');
            document.getElementById('checkoutSectionGateway').classList.remove('hidden');

            const methodContainer = document.getElementById('checkoutGatewayMethods');
            methodContainer.innerHTML = ''; // Clear previous methods

            // Every active method added in admin (any name) is listed by its category
            const filteredMethods = allActivePaymentMethods.filter(m =>
                pmCategory(m) === type && parseInt(m.allow_checkout === undefined ? 1 : m.allow_checkout) !== 0
            );

            if (filteredMethods.length === 0) {
                methodContainer.innerHTML = `<div class="text-xs text-rose-500 text-center py-2 col-span-2">No ${type.toUpperCase()} gateway available.</div>`;
                return;
            }

            let selIdx = filteredMethods.findIndex(m => keepMethodId && parseInt(m.id) === parseInt(keepMethodId));
            if (selIdx < 0) selIdx = 0;

            filteredMethods.forEach((m, idx) => {
                const btnM = document.createElement('button');
                btnM.type = 'button';
                const base = 'checkout-gateway-method p-2 rounded-xl border-2 text-center transition flex items-center gap-2 justify-center ';
                btnM.className = base + (idx === selIdx ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-700');
                btnM.innerHTML = (m.logo_url ? `<img src="${escHtml(m.logo_url)}" alt="" class="w-6 h-6 rounded object-cover flex-shrink-0">` : '') +
                    `<span class="text-left leading-tight min-w-0"><span class="block text-xs font-bold truncate">${escHtml(m.name)}</span>` +
                    `<span class="block text-[9px] font-black uppercase opacity-70">${escHtml(m.account_type || 'Personal')}</span></span>`;
                btnM.onclick = () => selectSpecificGatewayMethod(m.id, btnM);
                methodContainer.appendChild(btnM);

                if (idx === selIdx) {
                    activeCheckoutMethod = m;
                    updateCheckoutMethodDisplay(m);
                }
            });
        }

        function selectSpecificGatewayMethod(methodId, btn) {
            document.querySelectorAll('.checkout-gateway-method').forEach(b => {
                b.className = 'checkout-gateway-method p-2 rounded-xl border-2 text-center transition flex items-center gap-2 justify-center border-slate-200 bg-white text-slate-700';
            });
            btn.className = 'checkout-gateway-method p-2 rounded-xl border-2 text-center transition flex items-center gap-2 justify-center border-emerald-500 bg-emerald-50 text-emerald-800';

            const method = allActivePaymentMethods.find(m => parseInt(m.id) === parseInt(methodId));
            if (method) {
                activeCheckoutMethod = method;
                updateCheckoutMethodDisplay(method);
            }
        }

        function updateCheckoutMethodDisplay(method) {
            if (!method) return;
            document.getElementById('checkoutMethodTypeLabel').innerText = `${method.name} ${method.account_type || 'Personal'} Account:`;
            setTypeBadge('checkoutMethodTypeBadge', method.account_type || 'Personal');
            document.getElementById('checkoutMethodTypeBadge').classList.remove('hidden');
            document.getElementById('checkoutMethodNumber').innerText    = method.account_number;

            const logo = document.getElementById('checkoutMethodLogo');
            if (logo) {
                if (method.logo_url) { logo.src = method.logo_url; logo.classList.remove('hidden'); } else { logo.classList.add('hidden'); }
            }

            setImgBox('checkoutBannerContainer', 'checkoutBannerImg', method.banner_url);
            setImgBox('checkoutQrContainer', 'checkoutQrImg', method.qr_image_url);

            const ins = document.getElementById('checkoutInstructions');
            if (ins) {
                ins.innerText = (method.instructions && method.instructions.trim() !== '')
                    ? method.instructions
                    : 'Send money to this number, then enter your TrxID below for auto delivery.';
            }
            updateCheckoutPayAmount();
        }

        async function submitWalletCheckout() {
            const errBox = document.getElementById('walletCheckoutError');
            errBox.classList.add('hidden');

            const btn = document.getElementById('btnWalletCheckout');
            btn.disabled = true;
            btn.innerText = 'Processing...';

            const formData = new FormData();
            formData.append('ajax_action', 'verify_and_deliver');
            formData.append('plan_id', activeSelectedPlan.plan_id);
            formData.append('quantity', document.getElementById('purchaseQtyInput').value);
            formData.append('method_key', 'wallet');
            formData.append('promo_code', appliedPromoCode);

            try {
                const res  = await fetch('index.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.status === 'success') {
                    closeProductPurchaseModal();
                    renderKeyDeliverySuccess(data);
                } else {
                    errBox.innerText = data.message || 'Payment failed!';
                    errBox.classList.remove('hidden');
                }
            } catch(e) {
                errBox.innerText = 'Network error!';
                errBox.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-shield-check mr-1"></i> Pay with Wallet';
            }
        }

        async function submitCheckoutVerification() {
            const trxId = document.getElementById('checkoutTrxIdInput').value.trim();
            const errBox = document.getElementById('checkoutOrderError');
            errBox.classList.add('hidden');

            if (!trxId || trxId.length < 4) {
                errBox.innerText = 'Please enter a valid Transaction ID!';
                errBox.classList.remove('hidden');
                return;
            }

            const btn = document.getElementById('btnVerifyCheckout');
            btn.disabled = true;
            btn.innerText = 'Verifying with Gateway...';

            const formData = new FormData();
            formData.append('ajax_action', 'verify_and_deliver');
            formData.append('plan_id', activeSelectedPlan.plan_id);
            formData.append('quantity', document.getElementById('purchaseQtyInput').value);
            formData.append('method_key', activeCheckoutType);
            formData.append('gateway_key', activeCheckoutMethod ? activeCheckoutMethod.method_key : 'bkash');
            formData.append('method_id', activeCheckoutMethod ? activeCheckoutMethod.id : 0);
            formData.append('trx_id', trxId);
            formData.append('promo_code', appliedPromoCode);

            try {
                const res  = await fetch('index.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.status === 'success') {
                    closeProductPurchaseModal();
                    renderKeyDeliverySuccess(data);
                } else if (data.status === 'pending_submitted') {
                    closeProductPurchaseModal();
                    showPaymentResultPopup('pending', data);
                    schedulePendingPoll(4000);
                } else if (data.status === 'out_of_stock_refunded') {
                    closeProductPurchaseModal();
                    if (data.order_id) { shownOrderIds.add(data.order_id); ackOrder(data.order_id); }
                    showPaymentResultPopup('refunded', data);
                } else {
                    errBox.innerText = data.message || 'Payment verification failed!';
                    errBox.classList.remove('hidden');
                }
            } catch(e) {
                errBox.innerText = 'Network error during payment verification!';
                errBox.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-shield-check mr-1"></i> Verify &amp; Deliver Key';
            }
        }

        // ---- Pending / late-verified order poller (key popup even after leaving page) ----
        let shownOrderIds    = new Set();
        let pendingPollTimer = null;

        async function ackOrder(id) {
            try {
                const f = new FormData();
                f.append('ajax_action', 'ack_order');
                f.append('order_id', id);
                await fetch('index.php', { method: 'POST', body: f });
            } catch (e) {}
        }

        function schedulePendingPoll(ms) {
            if (!isUserLoggedIn) return;
            clearTimeout(pendingPollTimer);
            pendingPollTimer = setTimeout(pollPendingOrders, ms);
        }

        async function pollPendingOrders() {
            let nextDelay = 45000;
            if (document.hidden) { schedulePendingPoll(15000); return; }
            try {
                const f = new FormData();
                f.append('ajax_action', 'check_pending_orders');
                const res  = await fetch('index.php', { method: 'POST', body: f });
                const data = await res.json();

                if (data.status === 'success') {
                    if (data.waiting > 0 || (data.orders && data.orders.length > 1)) nextDelay = 6000;

                    const keyModalOpen = !document.getElementById('keySuccessModal').classList.contains('hidden');
                    if (!keyModalOpen) {
                        for (const o of (data.orders || [])) {
                            if (shownOrderIds.has(o.id)) continue;
                            shownOrderIds.add(o.id);
                            try { closeProductPurchaseModal(); } catch (e) {}
                            if (o.state === 'delivered') {
                                renderKeyDeliverySuccess({ product_name: o.product_name, keys: o.keys, order_id: o.id });
                            } else {
                                showPaymentResultPopup(o.state === 'refunded' ? 'refunded' : 'rejected', { message: o.message });
                                ackOrder(o.id);
                            }
                            break;
                        }
                    }
                } else if (data.require_login) {
                    return;
                }
            } catch (e) {}
            schedulePendingPoll(nextDelay);
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (isUserLoggedIn) schedulePendingPoll(1200);
        });

        function renderKeyDeliverySuccess(data) {
            if (data.order_id) { shownOrderIds.add(data.order_id); ackOrder(data.order_id); }
            document.getElementById('modalSuccessProductTitle').innerText = data.product_name;
            const container = document.getElementById('modalKeysContainer');
            container.innerHTML = '';

            (data.keys || []).forEach(k => {
                const item = document.createElement('div');
                item.className = 'p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between';
                item.innerHTML = `<span class="font-bold text-slate-900 select-all">${k}</span>
                                  <button onclick="copyToClipboard('${k}', 'Key')" class="px-2 py-1 bg-emerald-600 text-white rounded text-[10px] font-bold">Copy</button>`;
                container.appendChild(item);
            });

            document.getElementById('keySuccessModal').classList.remove('hidden');
            if (typeof confetti === 'function') confetti({ particleCount: 80, spread: 70, origin: { y: 0.6 } });
        }

        function closeKeySuccessModal() {
            document.getElementById('keySuccessModal').classList.add('hidden');
            window.location.reload();
        }

        // Referral
        function copyReferralCode() {
            const el = document.getElementById('myReferralCodeText');
            if (!el) return;
            const code = el.innerText.trim();
            navigator.clipboard.writeText(code);
            showToast(`Referral Code ${code} copied!`, 'success');
        }


        // ============ PAYMENT METHODS (live from admin) ============
        function escHtml(t) {
            return String(t == null ? '' : t).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }
        function pmTypeBadgeClass(t) {
            const k = String(t || '').toLowerCase();
            if (k.indexOf('agent') > -1)    return 'px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-amber-100 text-amber-700 border border-amber-200';
            if (k.indexOf('merchant') > -1) return 'px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-violet-100 text-violet-700 border border-violet-200';
            if (k.indexOf('pay') > -1)      return 'px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-yellow-100 text-yellow-700 border border-yellow-200';
            return 'px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-sky-100 text-sky-700 border border-sky-200';
        }
        function pmCategory(m) {
            if (m && m.category) return m.category;
            const hay = ((m.name || '') + ' ' + (m.method_key || '') + ' ' + (m.account_type || '')).toLowerCase();
            return /binance|usdt|pay\s*id|trc20|bep20|crypto|tether/.test(hay) ? 'usdt' : 'bdt';
        }
        function pmLogoHtml(m, fallbackIcon, size) {
            size = size || 'w-8 h-8';
            if (m.logo_url) return `<img src="${escHtml(m.logo_url)}" alt="" class="${size} rounded-lg object-cover border border-slate-200 bg-white">`;
            return `<div class="${size} rounded-lg bg-white border border-slate-200 flex items-center justify-center text-xs font-bold text-emerald-600 shadow-sm">${fallbackIcon}</div>`;
        }
        function setImgBox(wrapId, imgId, url) {
            const w = document.getElementById(wrapId), i = document.getElementById(imgId);
            if (!w || !i) return;
            if (url) { i.src = url; w.classList.remove('hidden'); } else { w.classList.add('hidden'); }
        }
        function setTypeBadge(id, type) {
            const el = document.getElementById(id);
            if (!el) return;
            el.className = pmTypeBadgeClass(type);
            el.innerText = type || 'Personal';
        }
        function getPayableTotals() {
            if (!activeSelectedPlan) return { bdt: 0, usdt: 0 };
            const qty = parseInt(document.getElementById('purchaseQtyInput').value) || 1;
            const f = appliedPromoDiscount > 0 ? (1 - appliedPromoDiscount / 100) : 1;
            return {
                bdt:  Math.round((parseFloat(activeSelectedPlan.price_bdt) || 0) * qty * f),
                usdt: (parseFloat(activeSelectedPlan.price) || 0) * qty * f
            };
        }
        function updateCheckoutPayAmount() {
            const t = getPayableTotals();
            const w = document.getElementById('walletPayAmount');
            if (w) w.innerText = currentCurrency === 'BDT' ? ('৳' + t.bdt.toLocaleString()) : ('$' + t.usdt.toFixed(2));
            const g = document.getElementById('checkoutPayAmount');
            const h = document.getElementById('checkoutPayHint');
            if (g) {
                if (activeCheckoutType === 'usdt') {
                    g.innerText = '$' + t.usdt.toFixed(2) + ' USDT';
                    if (h) h.innerText = 'এই পরিমাণ USDT সেন্ড করুন';
                } else {
                    g.innerText = '৳' + t.bdt.toLocaleString();
                    if (h) h.innerText = 'এই পরিমাণ টাকা সেন্ড করুন';
                }
            }
        }

        let pmSignature = JSON.stringify(allActivePaymentMethods);
        async function refreshPaymentMethods() {
            if (document.hidden) return;
            try {
                const res  = await fetch('index.php?ajax_action=get_payment_methods&_=' + Date.now(), { cache: 'no-store' });
                const data = await res.json();
                if (!data || data.status !== 'success') return;
                const sig = JSON.stringify(data.data);
                if (sig === pmSignature) return;
                pmSignature = sig;
                allActivePaymentMethods = data.data;

                // re-render whatever is currently on screen
                const modal = document.getElementById('productPurchaseModal');
                const payStep = document.getElementById('modalViewPaymentSelect');
                if (modal && !modal.classList.contains('hidden') && payStep && !payStep.classList.contains('hidden') && activeCheckoutType !== 'wallet') {
                    selectCheckoutType(activeCheckoutType, null, activeCheckoutMethod ? activeCheckoutMethod.id : 0);
                }
                const sub = document.getElementById('addFundSubGatewayScreen');
                if (sub && !sub.classList.contains('hidden')) openAddFundSubGateway(currentAddFundType);
                const ded = document.getElementById('addFundDedicatedScreen');
                if (ded && !ded.classList.contains('hidden')) {
                    const m = allActivePaymentMethods.find(x => parseInt(x.id) === parseInt(currentAddFundMethodId));
                    if (m) fillAddFundMethodDisplay(m);
                }
            } catch (e) {}
        }
        setInterval(() => { if (!document.hidden) refreshPaymentMethods(); }, 30000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshPaymentMethods(); });

        // ============ COIN SYSTEM (live) ============
        let coinData = <?= json_encode($coinInitial, JSON_UNESCAPED_UNICODE) ?>;

        function fmtNum(n) { return Number(n || 0).toLocaleString('en-US'); }
        function fmtTk(n) {
            n = Number(n || 0);
            return '৳' + (Math.abs(n - Math.round(n)) < 0.005 ? Math.round(n).toLocaleString('en-US') : n.toFixed(2));
        }
        function fmtPer(n) {
            n = Number(n || 0);
            return '৳' + (n >= 1 ? n.toFixed(2).replace(/\.?0+$/, '') : n.toFixed(4).replace(/0+$/, '').replace(/\.$/, ''));
        }

        function renderCoinUI() {
            const d = coinData || {};
            const on = !!d.coin_on;

            // show / hide coin cards when admin turns the coin system on/off
            ['pfCoinCard', 'refCoinBox'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.toggle('hidden', !on);
            });
            if (!on) { closeCoinConvertModal(); }

            document.querySelectorAll('.js-coin-balance').forEach(el => el.textContent = fmtNum(d.coins));

            document.querySelectorAll('.js-coin-perrefer').forEach(el => {
                el.textContent = '+' + fmtNum(d.per_refer) + ' coins / refer';
                el.classList.toggle('hidden', !(d.per_refer > 0));
            });

            // rate line: "1 Coin = ৳X"
            document.querySelectorAll('.js-coin-rate-line').forEach(el => {
                if (d.packs && d.packs.length) {
                    el.innerHTML =
                        '<span class="px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-amber-700"><i class="fa-solid fa-coins mr-1"></i>1 Coin = ' + fmtPer(d.best_rate) + '</span>' +
                        (d.per_refer > 0 ? '<span class="px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700">+' + fmtNum(d.per_refer) + ' Coin / Refer</span>' : '');
                } else {
                    el.innerHTML = '<span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-500">Convert option is not available right now</span>' +
                        (d.per_refer > 0 ? '<span class="px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700">+' + fmtNum(d.per_refer) + ' Coin / Refer</span>' : '');
                }
            });

            // pack lists
            document.querySelectorAll('.js-coin-packs').forEach(box => {
                if (!d.packs || !d.packs.length) {
                    box.innerHTML = '<div class="text-center text-[11px] text-slate-400 py-3 font-sans">No conversion pack available yet.</div>';
                    return;
                }
                box.innerHTML = d.packs.map(p => {
                    const can = d.coins >= p.coins;
                    const need = p.coins - d.coins;
                    return '<div class="flex items-center justify-between bg-slate-50 border border-slate-100 rounded-xl p-3 gap-2">' +
                        '<div class="font-mono text-xs min-w-0">' +
                            '<div><strong class="text-slate-900">' + fmtNum(p.coins) + ' coins</strong>' +
                            '<span class="text-slate-400 mx-1">=</span>' +
                            '<strong class="text-emerald-700">' + fmtTk(p.taka) + '</strong></div>' +
                            '<span class="text-[10px] text-slate-400 block mt-0.5">1 coin = ' + fmtPer(p.per_coin) + (can ? '' : ' • আরও ' + fmtNum(need) + ' coin লাগবে') + '</span>' +
                        '</div>' +
                        '<button type="button" ' + (can ? '' : 'disabled ') + 'onclick="convertCoins(' + p.id + ',' + p.coins + ',' + p.taka + ')" ' +
                            'class="px-3 py-2 rounded-lg text-[10px] font-black uppercase transition active:scale-95 flex-shrink-0 ' +
                            (can ? 'bg-amber-500 text-white shadow' : 'bg-slate-200 text-slate-400 cursor-not-allowed') + '">Convert</button>' +
                    '</div>';
                }).join('');
            });

            // referral badge on profile + reward text
            const badge = document.getElementById('pfRefBadge');
            if (badge) {
                const parts = [];
                if (d.bdt_on) parts.push('৳' + Number(d.bdt_amount).toString());
                if (d.coin_on && d.per_refer > 0) parts.push(fmtNum(d.per_refer) + ' Coin');
                badge.textContent = parts.join(' + ') + ' / refer';
                badge.classList.toggle('hidden', parts.length === 0);
            }
            const txt = document.getElementById('refPageRewardText');
            if (txt) {
                if (d.bdt_on && d.coin_on && d.per_refer > 0) {
                    txt.innerHTML = 'প্রতিটি সফল রেফারে আপনার ওয়ালেটে <b>৳' + Number(d.bdt_amount).toFixed(2) + ' টাকা</b> এবং <b>' + fmtNum(d.per_refer) + ' কয়েন</b> ইনস্ট্যান্ট পেয়ে যাবেন!';
                } else if (d.bdt_on) {
                    txt.innerHTML = 'প্রতিটি সফল রেফারে আপনার ওয়ালেটে <b>৳' + Number(d.bdt_amount).toFixed(2) + ' টাকা</b> বা পয়সা ইনস্ট্যান্ট ক্রেডিট পেয়ে যাবেন!';
                } else if (d.coin_on && d.per_refer > 0) {
                    txt.innerHTML = 'প্রতিটি সফল রেফারে আপনি পাবেন <b>' + fmtNum(d.per_refer) + ' কয়েন</b> — কয়েন কনভার্ট করে ওয়ালেটে টাকা নিতে পারবেন!';
                } else {
                    txt.textContent = 'বন্ধুদের ইনভাইট করুন এবং রেফারেল রিওয়ার্ড জিতুন!';
                }
            }
        }

        async function refreshCoinData() {
            if (!isUserLoggedIn || document.hidden) return;
            try {
                const res = await fetch('index.php?ajax_action=get_coin_data&_=' + Date.now(), { cache: 'no-store' });
                const data = await res.json();
                if (data && data.status === 'success') { coinData = data; renderCoinUI(); }
            } catch (e) {}
        }

        function openCoinConvertModal() {
            refreshCoinData();
            renderCoinUI();
            const m = document.getElementById('coinConvertModal');
            if (m) m.classList.remove('hidden');
        }
        function closeCoinConvertModal() {
            const m = document.getElementById('coinConvertModal');
            if (m) m.classList.add('hidden');
        }

        async function convertCoins(ruleId, coins, taka) {
            if (!confirm(fmtNum(coins) + ' coins দিয়ে ' + fmtTk(taka) + ' wallet এ নিতে চান?')) return;
            const fd = new FormData();
            fd.append('ajax_action', 'convert_coins');
            fd.append('rule_id', ruleId);
            try {
                const res  = await fetch('index.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    if (typeof data.new_usd !== 'undefined') {
                        currentUserBalanceUsdt = Number(data.new_usd);
                        currentUserBalanceBdt  = Number(data.new_bdt);
                        updateAllCurrencyDisplays();
                        const wc = document.getElementById('walletCheckoutBalance');
                        if (wc) wc.textContent = Math.round(currentUserBalanceBdt).toLocaleString();
                        try { updateAllCurrencyDisplays(); } catch (e) {}
                    }
                    await refreshCoinData();
                } else {
                    showToast(data.message || 'Conversion failed!', 'error');
                    refreshCoinData();
                }
            } catch (e) { showToast('Network error!', 'error'); }
        }

        // live refresh: every 4s + when tab becomes visible again
        document.addEventListener('DOMContentLoaded', () => {
            renderCoinUI();
            if (isUserLoggedIn) {
                setInterval(() => { if (!document.hidden) refreshCoinData(); }, 20000);
                document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshCoinData(); });
            }
        });

        function copyReferralLink() {
            const el = document.getElementById('myReferralLinkText');
            if (!el) return;
            const link = el.innerText.trim() || '<?= SITE_URL . '?ref=' . ($user['referral_code'] ?? '13183') ?>';
            navigator.clipboard.writeText(link);
            showToast('Referral link copied to clipboard!', 'success');
        }

        // ADD FUND
        function openAddFundSubGateway(type) {
            currentAddFundType = type;
            document.getElementById('addFundMethodListContainer').classList.add('hidden');
            document.getElementById('addFundSubGatewayScreen').classList.remove('hidden');

            const titleEl = document.getElementById('addFundSubGatewayTitle');
            const listEl = document.getElementById('addFundSubGatewayList');
            listEl.innerHTML = '';

            titleEl.innerText = (type === 'usdt') ? 'Select USDT Method' : 'Select BDT Method';
            const fallbackIcon = (type === 'usdt')
                ? '<i class="fa-solid fa-coins text-amber-500"></i>'
                : '<i class="fa-solid fa-money-bill-wave"></i>';

            // Every active method added in admin (any name) shows up here by its category
            const list = allActivePaymentMethods.filter(m =>
                pmCategory(m) === type && parseInt(m.allow_add_fund === undefined ? 1 : m.allow_add_fund) !== 0
            );

            if (list.length === 0) {
                listEl.innerHTML = '<div class="text-xs text-rose-500 text-center py-4">' +
                    (type === 'usdt' ? 'Binance/USDT gateway not configured.' : 'No BDT gateway available.') + '</div>';
            } else {
                list.forEach(m => {
                    const div = document.createElement('div');
                    div.className = 'bg-slate-50 border border-slate-200 hover:border-emerald-500 rounded-xl p-3 flex items-center justify-between cursor-pointer transition active:scale-98 shadow-sm';
                    div.onclick = () => openDedicatedAddFundScreen(m.id, type);
                    div.innerHTML = `
                        <div class="flex items-center space-x-3">
                            ${pmLogoHtml(m, fallbackIcon)}
                            <div>
                                <strong class="text-slate-900 block text-xs">${escHtml(m.name)}</strong>
                                <span class="${pmTypeBadgeClass(m.account_type)} inline-block mt-0.5">${escHtml(m.account_type || 'Personal')}</span>
                            </div>
                        </div>
                        <span class="text-xs text-emerald-600 font-bold"><i class="fa-solid fa-chevron-right text-[10px]"></i></span>
                    `;
                    listEl.appendChild(div);
                });
            }
            refreshPaymentMethods();
        }

        function closeAddFundSubGateway() {
            document.getElementById('addFundSubGatewayScreen').classList.add('hidden');
            document.getElementById('addFundMethodListContainer').classList.remove('hidden');
        }

        let currentAddFundMethodId = 0;
        let currentAddFundType = 'bdt';

        function fillAddFundMethodDisplay(method) {
            document.getElementById('addFundDedicatedMethodTitle').innerText = `${method.name} Deposit`;
            document.getElementById('addFundTypeLabel').innerText = `${method.name} ${method.account_type || 'Personal'} Account Number:`;
            setTypeBadge('addFundTypeBadge', method.account_type || 'Personal');
            document.getElementById('addFundTypeBadge').classList.remove('hidden');
            document.getElementById('addFundNumberVal').innerText = method.account_number;

            const logo = document.getElementById('addFundMethodLogo');
            if (logo) {
                if (method.logo_url) { logo.src = method.logo_url; logo.classList.remove('hidden'); } else { logo.classList.add('hidden'); }
            }

            setImgBox('addFundBannerContainer', 'addFundBannerImg', method.banner_url);
            setImgBox('addFundQrContainer', 'addFundQrImg', method.qr_image_url);

            const insBox = document.getElementById('addFundInstructionsBox');
            if (insBox) {
                if (method.instructions && method.instructions.trim() !== '') {
                    insBox.innerText = method.instructions;
                    insBox.classList.remove('hidden');
                } else {
                    insBox.classList.add('hidden');
                }
            }
        }

        function openDedicatedAddFundScreen(methodId, type) {
            const method = allActivePaymentMethods.find(m => parseInt(m.id) === parseInt(methodId));
            if (!method) return;

            currentAddFundMethodId = methodId;
            currentAddFundType = type;

            fillAddFundMethodDisplay(method);
            document.getElementById('addFundAmountInput').value = '';
            document.getElementById('addFundTrxIdInput').value = '';
            document.getElementById('addFundOrderError').classList.add('hidden');

            document.getElementById('addFundSubGatewayScreen').classList.add('hidden');
            document.getElementById('addFundDedicatedScreen').classList.remove('hidden');
        }

        function closeDedicatedAddFundScreen() {
            document.getElementById('addFundDedicatedScreen').classList.add('hidden');
            document.getElementById('addFundMethodListContainer').classList.remove('hidden');
        }

        async function submitAddFundVerification() {
            const amount = parseFloat(document.getElementById('addFundAmountInput').value) || 0;
            const trxId  = document.getElementById('addFundTrxIdInput').value.trim();
            const errBox = document.getElementById('addFundOrderError');
            errBox.classList.add('hidden');

            if (amount <= 0 || !trxId) {
                errBox.innerText = 'Please enter valid Amount and TrxID!';
                errBox.classList.remove('hidden');
                return;
            }

            const btn = document.getElementById('btnVerifyAddFund');
            btn.disabled = true;
            btn.innerText = 'Verifying with Gateway...';

            const formData = new FormData();
            formData.append('ajax_action', 'verify_add_fund');
            formData.append('amount', amount);
            formData.append('trx_id', trxId);
            formData.append('method_key', currentAddFundType);
            const afMethod = allActivePaymentMethods.find(m => parseInt(m.id) === parseInt(currentAddFundMethodId));
            formData.append('gateway_key', afMethod ? afMethod.method_key : 'bkash');
            formData.append('method_id', currentAddFundMethodId);

            try {
                const res  = await fetch('index.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.status === 'success') {
                    showPaymentResultPopup('success', data);
                    closeDedicatedAddFundScreen();
                } else if (data.status === 'pending_submitted') {
                    showPaymentResultPopup('pending', data);
                    closeDedicatedAddFundScreen();
                } else {
                    errBox.innerText = data.message || 'Deposit verification failed!';
                    errBox.classList.remove('hidden');
                }
            } catch(e) {
                errBox.innerText = 'Connection error!';
                errBox.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerText = 'Verify & Add Balance';
            }
        }

        function showPaymentResultPopup(type, data) {
            const modal   = document.getElementById('paymentResultModal');
            const iconBox = document.getElementById('resultModalIconContainer');
            const heading = document.getElementById('resultModalHeading');
            const bodyBox = document.getElementById('resultModalBodyBox');

            if (type === 'success') {
                iconBox.className = "w-16 h-16 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-3xl";
                iconBox.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
                heading.innerText = 'ডিপোজিট সফল হয়েছে!';
                bodyBox.innerHTML = `মোট যুক্ত হয়েছে: <b>৳${data.amount} BDT</b>`;
                setTimeout(() => window.location.reload(), 2500);
            } else if (type === 'refunded') {
                iconBox.className = "w-16 h-16 mx-auto rounded-full bg-sky-100 text-sky-600 flex items-center justify-center text-3xl";
                iconBox.innerHTML = '<i class="fa-solid fa-wallet"></i>';
                heading.innerText = 'Out of Stock - টাকা ওয়ালেটে যুক্ত হয়েছে';
                bodyBox.innerHTML = data.message || 'আপনার টাকা ওয়ালেটে যুক্ত করা হয়েছে।';
                setTimeout(() => window.location.reload(), 5000);
            } else if (type === 'rejected') {
                iconBox.className = "w-16 h-16 mx-auto rounded-full bg-rose-100 text-rose-600 flex items-center justify-center text-3xl";
                iconBox.innerHTML = '<i class="fa-solid fa-circle-xmark"></i>';
                heading.innerText = 'পেমেন্ট অনুমোদিত হয়নি';
                bodyBox.innerHTML = data.message || 'Your payment was not approved.';
            } else {
                iconBox.className = "w-16 h-16 mx-auto rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-3xl";
                iconBox.innerHTML = '<i class="fa-solid fa-clock"></i>';
                heading.innerText = 'অপেক্ষারত (Pending)';
                bodyBox.innerHTML = data.message || 'আপনার ট্রানজেকশন রিকোয়েস্টটি পর্যালোচনায় আছে।';
            }
            modal.classList.remove('hidden');
        }

        function closePaymentResultModal() {
            document.getElementById('paymentResultModal').classList.add('hidden');
        }

        // RESET KEY (plan-bound key chips)
        function onResetPlanChange(planId) {
            const chipWrap = document.getElementById('resetKeyChipsWrap');
            const chipBox  = document.getElementById('resetKeyChips');
            chipBox.innerHTML = '';
            document.getElementById('resetKeySearchInput').value = '';
            document.getElementById('resetResultBox').classList.add('hidden');

            if (!planId || !userKeysByPlan[planId] || userKeysByPlan[planId].length === 0) {
                chipWrap.classList.add('hidden');
                return;
            }

            userKeysByPlan[planId].forEach(k => {
                const chip = document.createElement('span');
                chip.className = 'gateway-key-chip';
                chip.innerText = k.license_key;
                chip.title = k.validity || '';
                chip.onclick = () => {
                    document.getElementById('resetKeySearchInput').value = k.license_key;
                    document.querySelectorAll('.gateway-key-chip').forEach(c => c.classList.remove('selected'));
                    chip.classList.add('selected');
                };
                chipBox.appendChild(chip);
            });

            chipWrap.classList.remove('hidden');
        }

        async function handleLiveKeyReset(e) {
            e.preventDefault();
            const planId = document.getElementById('resetPlanSelect').value;
            const key = document.getElementById('resetKeySearchInput').value.trim();
            
            if (!planId || !key) {
                showToast('Please select a product and enter a key.', 'error');
                return;
            }

            const btn = document.getElementById('resetSubmitBtn');
            const box = document.getElementById('resetResultBox');
            const oldHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Resetting... please wait';
            box.classList.add('hidden');

            const fd = new FormData();
            fd.append('ajax_action', 'reset_key');
            fd.append('api_id', planId);
            fd.append('license_key', key);

            const showBox = (ok, msg) => {
                box.classList.remove('hidden');
                box.innerText = (ok ? '✅ ' : '❌ ') + msg;
                box.className = ok ? 'p-3 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 text-xs font-bold' : 'p-3 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 text-xs font-bold';
            };

            try {
                const ctrl = new AbortController();
                const tmo  = setTimeout(() => ctrl.abort(), 35000);
                const res  = await fetch('index.php', { method: 'POST', body: fd, signal: ctrl.signal });
                clearTimeout(tmo);
                const data = await res.json();
                showBox(data.status === 'success', data.message || (data.status === 'success' ? 'Hardware ID successfully reset!' : 'Key reset failed.'));
                showToast(data.message || 'Done', data.status === 'success' ? 'success' : 'error');
            } catch(err) {
                showBox(false, 'Network error or server timeout. Please try again.');
            }
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }

        // SPIN WHEEL
        function openSpinWheelModal() {
            document.getElementById('spinWheelModal').classList.remove('hidden');
            loadSpinWheelData();
        }

        function closeSpinWheelModal() {
            document.getElementById('spinWheelModal').classList.add('hidden');
            if (spinNeedsReload) { spinNeedsReload = false; window.location.reload(); }
        }

        let spinNeedsReload = false;
        let wheelRotation   = 0;
        let spinInfo        = null;

        async function loadSpinWheelData() {
            const fd = new FormData();
            fd.append('ajax_action', 'get_spin_data');
            try {
                const res  = await fetch('index.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.status === 'success') {
                    spinInfo     = data;
                    spinSegments = data.segments || [];
                    drawWheelCanvas(spinSegments);
                    renderSpinInfo(data);
                }
            } catch(e) {}
        }

        function renderSpinInfo(d) {
            document.getElementById('spinCountText').innerText = d.logged_in ? d.remaining_spins : 0;
            document.getElementById('spinPrizeRangeText').innerText = 'Win ৳' + d.prize_min + ' – ৳' + d.prize_max;

            const per  = Math.max(1, parseFloat(d.spend_per_spin) || 500);
            const prog = Math.min(per, parseFloat(d.spend_progress) || 0);
            document.getElementById('spinProgressLabel').innerText = 'Next spin: spend ৳' + Math.round(per);
            document.getElementById('spinProgressValue').innerText = '৳' + Math.round(prog) + ' / ৳' + Math.round(per);
            document.getElementById('spinProgressBar').style.width = Math.round((prog / per) * 100) + '%';

            const hint = document.getElementById('spinHintText');
            if (!d.enabled) {
                hint.innerText = 'Lucky Spin এখন বন্ধ আছে।';
            } else if (!d.logged_in) {
                hint.innerText = 'স্পিন করতে আগে লগইন করুন।';
            } else {
                hint.innerText = '৳' + Math.round(per) + ' টাকার প্রোডাক্ট কিনলেই ১টি স্পিন! জিতলে টাকা সরাসরি ওয়ালেটে।';
            }

            const ref = document.getElementById('spinReferText');
            if (d.refer_enabled) {
                ref.classList.remove('hidden');
                ref.innerText = d.refer_per_spin + ' জন রেফার = ১টি স্পিন (' + d.refer_progress + '/' + d.refer_per_spin + ')';
            } else {
                ref.classList.add('hidden');
            }
        }

        function drawWheelCanvas(segments) {
            const canvas = document.getElementById('wheelCanvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const total = segments.length;
            if (total === 0) return;

            const radius = canvas.width / 2;
            const step   = (2 * Math.PI) / total;
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            segments.forEach((seg, i) => {
                ctx.beginPath();
                ctx.moveTo(radius, radius);
                ctx.arc(radius, radius, radius - 2, i * step, (i + 1) * step);
                ctx.fillStyle = seg.color || (i % 2 === 0 ? '#15803d' : '#22c55e');
                ctx.fill();
                ctx.stroke();

                ctx.save();
                ctx.translate(radius, radius);
                ctx.rotate(i * step + step / 2);
                ctx.textAlign = 'right';
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 12px sans-serif';
                ctx.fillText(seg.title, radius - 15, 4);
                ctx.restore();
            });
        }

        async function triggerWheelSpin() {
            if (isWheelSpinning) return;

            if (!isUserLoggedIn) {
                showToast('স্পিন করতে আগে লগইন করুন!', 'error');
                closeSpinWheelModal();
                openAuthModal('login');
                return;
            }

            isWheelSpinning = true;

            const fd = new FormData();
            fd.append('ajax_action', 'spin_wheel');
            try {
                const res  = await fetch('index.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.status === 'success') {
                    const canvas = document.getElementById('wheelCanvas');
                    const n      = spinSegments.length || 1;
                    const step   = 360 / n;
                    // bring the winning slice centre under the top pointer (270deg)
                    const delta  = (((270 - (data.winning_index + 0.5) * step) % 360) + 360) % 360;
                    wheelRotation = (Math.ceil(wheelRotation / 360) * 360) + 1800 + delta;

                    canvas.style.transition = 'transform 4s cubic-bezier(0.12, 0.8, 0.15, 1)';
                    canvas.style.transform  = `rotate(${wheelRotation}deg)`;

                    setTimeout(() => {
                        isWheelSpinning = false;
                        spinNeedsReload = true;
                        showToast(data.bengali_offer_text || data.title, 'success');
                        if (typeof confetti === 'function') confetti({ particleCount: 90, spread: 70, origin: { y: 0.6 } });
                        loadSpinWheelData();
                    }, 4200);
                } else {
                    showToast(data.message || 'Spin failed!', 'error');
                    isWheelSpinning = false;
                    if (data.require_login) { closeSpinWheelModal(); openAuthModal('login'); }
                }
            } catch(e) { isWheelSpinning = false; }
        }

        // AUTH
        function openAuthModal(tab) {
            switchAuthTab(tab);
            document.getElementById('authModal').classList.remove('hidden');
        }

        function closeAuthModal() {
            document.getElementById('authModal').classList.add('hidden');
        }

        function switchAuthTab(tab) {
            const btnLog  = document.getElementById('tabAuthLogin');
            const btnReg  = document.getElementById('tabAuthRegister');
            const formLog = document.getElementById('formAuthLogin');
            const formReg = document.getElementById('formAuthRegister');

            if (tab === 'login') {
                btnLog.className  = 'flex-1 py-1.5 rounded-lg bg-emerald-600 text-white transition';
                btnReg.className  = 'flex-1 py-1.5 rounded-lg text-slate-600 transition';
                formLog.classList.remove('hidden');
                formReg.classList.add('hidden');
            } else {
                btnReg.className  = 'flex-1 py-1.5 rounded-lg bg-emerald-600 text-white transition';
                btnLog.className  = 'flex-1 py-1.5 rounded-lg text-slate-600 transition';
                formReg.classList.remove('hidden');
                formLog.classList.add('hidden');
            }
        }

        function openForgotPasswordModal() {
            closeAuthModal();
            document.getElementById('forgotPasswordModal').classList.remove('hidden');
        }

        async function handleForgotPass(e) {
            e.preventDefault();
            const email = document.getElementById('resetEmailInput').value;
            const msgBox = document.getElementById('forgotPassMsg');
            
            msgBox.classList.add('hidden');
            
            const fd = new FormData();
            fd.append('auth_action', 'request_password_reset');
            fd.append('reset_email', email);
            
            try {
                const res = await fetch('index.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                msgBox.classList.remove('hidden');
                if(data.status === 'success') {
                    msgBox.className = 'text-xs font-bold text-center text-emerald-600 block';
                    msgBox.innerText = data.message;
                    setTimeout(() => {
                        closeModal('forgotPasswordModal');
                        msgBox.classList.add('hidden');
                        msgBox.innerText = '';
                    }, 4000);
                } else {
                    msgBox.className = 'text-xs font-bold text-center text-rose-600 block';
                    msgBox.innerText = data.message;
                }
            } catch(err) {
                msgBox.classList.remove('hidden');
                msgBox.className = 'text-xs font-bold text-center text-rose-600 block';
                msgBox.innerText = 'Failed to send reset link.';
            }
        }

        // Avatar upload
        async function uploadAvatar(input) {
            if (!input.files || input.files.length === 0) return;
            const file = input.files[0];
            
            const fd = new FormData();
            fd.append('auth_action', 'update_profile_photo');
            fd.append('avatar', file);
            
            showToast('Uploading picture...', 'success');
            
            try {
                const res = await fetch('index.php', { method: 'POST', body: fd });
                const data = await res.json();
                if(data.status === 'success') {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            } catch(e) {
                showToast('Upload failed.', 'error');
            }
        }

        // Feedback
        let selectedRating = 5;
        function setRating(rating) {
            selectedRating = rating;
            document.getElementById('feedbackRating').value = rating;
            const stars = document.querySelectorAll('#starRatingContainer i');
            stars.forEach((star, index) => {
                if(index < rating) {
                    star.classList.remove('text-slate-300');
                    star.classList.add('text-amber-400');
                } else {
                    star.classList.add('text-slate-300');
                    star.classList.remove('text-amber-400');
                }
            });
        }
        
        document.addEventListener("DOMContentLoaded", () => {
            if(document.getElementById('starRatingContainer')) {
                setRating(5);
            }
        });

        async function submitFeedback(e) {
            e.preventDefault();
            const productId = document.getElementById('feedbackProductId').value;
            const comment = document.getElementById('feedbackComment').value;
            const rating = document.getElementById('feedbackRating').value;
            
            if(!productId || !comment) {
                showToast('Please fill all fields.', 'error');
                return;
            }
            
            const fd = new FormData();
            fd.append('auth_action', 'submit_feedback');
            fd.append('product_id', productId);
            fd.append('rating', rating);
            fd.append('comment', comment);
            
            try {
                const res = await fetch('index.php', { method: 'POST', body: fd });
                const data = await res.json();
                showToast(data.message, data.status === 'success' ? 'success' : 'error');
                if(data.status === 'success') {
                    document.getElementById('feedbackComment').value = '';
                    setTimeout(() => window.location.reload(), 1500);
                }
            } catch(err) {
                showToast('Submission failed.', 'error');
            }
        }

        // Toast & Clipboard
        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toastNotification');
            document.getElementById('toastMsg').innerText = msg;
            toast.classList.remove('opacity-0', 'translate-y-[-20px]');
            toast.classList.add('opacity-100', 'translate-y-0');
            setTimeout(() => {
                toast.classList.remove('opacity-100', 'translate-y-0');
                toast.classList.add('opacity-0', 'translate-y-[-20px]');
            }, 2600);
        }

        function copyToClipboard(text, label = 'Copied') {
            navigator.clipboard.writeText(text);
            showToast(`${label} copied!`, 'success');
        }

        // DOM ready
        window.addEventListener('DOMContentLoaded', () => {
            let savedCurrency = null;
            try { savedCurrency = localStorage.getItem('selected_currency'); } catch (e) {}
            if (savedCurrency && (savedCurrency === 'BDT' || savedCurrency === 'USDT')) {
                currentCurrency = savedCurrency;
                const textLabel = document.getElementById('currencyTextLabel');
                if (currentCurrency === 'BDT') {
                    textLabel.innerText = 'BDT';
                } else {
                    textLabel.innerText = 'USDT';
                }
            }
            updateAllCurrencyDisplays();

            document.querySelectorAll('img').forEach(img => {
                if (img.complete) {
                    img.parentElement?.classList.remove('img-skeleton');
                } else {
                    img.addEventListener('load', () => {
                        img.parentElement?.classList.remove('img-skeleton');
                    });
                }
            });

            const urlParams = new URLSearchParams(window.location.search);
            const refParam  = urlParams.get('ref');
            if (refParam) {
                const refInput = document.getElementById('regReferralInput');
                if (refInput) refInput.value = refParam.trim();
                openAuthModal('register');
                showToast(`Referral code ${refParam} applied!`, 'success');
            }

            if (totalBannersCount > 1) {
                setInterval(() => {
                    currentSlideIdx = (currentSlideIdx + 1) % totalBannersCount;
                    goToSlide(currentSlideIdx);
                }, 3800);
            }

            <?php if (!empty($toast)): ?>
                showToast("<?= addslashes($toast['msg']) ?>", "<?= $toast['type'] ?>");
            <?php endif; ?>
        });
    </script>
    <!-- =============================================================== -->
    <!-- DYNAMIC WELCOME POPUP (1-HOUR REPEAT & ADMIN CONNECTED)         -->
    <!-- =============================================================== -->
    <?php if (($settings['update_channel_popup_enabled'] ?? '0') === '1'): ?>
    <?php
        $popTitle = !empty($settings['update_channel_title']) ? $settings['update_channel_title'] : ('স্বাগতম ' . htmlspecialchars($storeTitleName) . '-এ! 🎉');
        $popMsg   = !empty($settings['update_channel_msg']) ? $settings['update_channel_msg'] : 'নতুন সব পাস, ফ্রি গিভঅ্যাওয়ে, আপডেট ও ইনস্ট্যান্ট সাপোর্টের জন্য এখনই আমাদের অফিসিয়াল টেলিগ্রাম চ্যানেলে জয়েন করুন!';
        $popTgUrl = !empty($settings['update_channel_link']) ? $settings['update_channel_link'] : (!empty($settings['support_telegram']) ? $settings['support_telegram'] : 'https://t.me/Ktoolsadminsaportteme');
        $popPhoto = !empty($settings['payment_banner_url']) ? $settings['payment_banner_url'] : 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=600&q=80';
    ?>
    <div id="welcomeTgModal" class="fixed inset-0 bg-black/80 backdrop-blur-md z-[999999] hidden flex items-center justify-center p-4 transition-all duration-300 opacity-0" style="display: none;">
        <div id="welcomeTgModalCard" class="bg-white rounded-3xl w-full max-w-sm p-5 space-y-3.5 shadow-2xl border border-slate-100 text-center relative my-auto transform scale-90 transition-all duration-300">
            
            <!-- Close Button -->
            <button type="button" onclick="closeWelcomeTgModal()" class="absolute top-3 right-3 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-900 flex items-center justify-center text-sm active:scale-90 transition z-20">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <!-- Top Banner Photo / Icon (Loaded from Admin Settings) -->
            <div class="relative w-full h-36 rounded-2xl overflow-hidden bg-gradient-to-tr from-sky-600 via-blue-600 to-indigo-700 flex items-center justify-center shadow-md">
                <img src="<?= htmlspecialchars($popPhoto) ?>" alt="Notice Banner" class="w-full h-full object-cover opacity-40 absolute inset-0">
                
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md border border-white/40 text-white flex items-center justify-center text-3xl shadow-lg animate-bounce">
                        <i class="fa-brands fa-telegram"></i>
                    </div>
                    <span class="mt-2 text-[10px] font-black uppercase tracking-widest text-sky-200 font-mono">OFFICIAL COMMUNITY</span>
                </div>
            </div>

            <!-- Details Text (Loaded from Admin Settings) -->
            <div class="space-y-1.5 pt-1">
                <h3 class="text-base font-black text-slate-900 uppercase">
                    <?= htmlspecialchars($popTitle) ?>
                </h3>
                <p class="text-xs text-slate-600 font-bengali leading-relaxed">
                    <?= nl2br(htmlspecialchars($popMsg)) ?>
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-2 pt-1 font-sans">
                <!-- Telegram Channel Link Button -->
                <a href="<?= htmlspecialchars($popTgUrl) ?>" target="_blank" onclick="closeWelcomeTgModal()" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 text-white font-black text-xs uppercase tracking-wider flex items-center justify-center gap-2 shadow-lg shadow-sky-500/30 active:scale-95 transition">
                    <i class="fa-brands fa-telegram text-base"></i> <span>Join Telegram Channel</span>
                </a>

                <!-- Continue to Website Button -->
                <button type="button" onclick="closeWelcomeTgModal()" class="w-full py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs uppercase active:scale-95 transition">
                    ওয়েবসাইটে প্রবেশ করুন
                </button>
            </div>

        </div>
    </div>

    <!-- REALTIME WELCOME POPUP SCRIPT (OPENS ON EVERY NEW VISIT) -->
    <script>
        function openWelcomeTgModal() {
            var modal = document.getElementById('welcomeTgModal');
            var card  = document.getElementById('welcomeTgModalCard');
            if (!modal || !card) return;

            modal.style.display = 'flex';
            modal.classList.remove('hidden');
            setTimeout(function() {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                card.classList.remove('scale-90');
                card.classList.add('scale-100');
            }, 50);
        }

        function closeWelcomeTgModal() {
            var modal = document.getElementById('welcomeTgModal');
            var card  = document.getElementById('welcomeTgModalCard');
            if (!modal || !card) return;

            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            card.classList.remove('scale-100');
            card.classList.add('scale-90');

            setTimeout(function() {
                modal.classList.add('hidden');
                modal.style.display = 'none';
            }, 300);

            // ব্রাউজার সম্পূর্ণ বন্ধ না করা পর্যন্ত এই সেশনে আর বিরক্ত করবে না
            try { sessionStorage.setItem('welcome_popup_session_done', '1'); } catch (e) {}
        }

        // সাইটে নতুন ভিজিট করলেই স্বয়ংক্রিয়ভাবে পপআপ চালু হবে
        function verifyAndTriggerWelcomePopup() {
            // পুরনো ১ ঘণ্টার লক ক্লিয়ার
            try { localStorage.removeItem('welcome_popup_last_shown'); } catch (e) {}

            var done = false;
            try { done = !!sessionStorage.getItem('welcome_popup_session_done'); } catch (e) {}
            if (!done) {
                setTimeout(openWelcomeTgModal, 600);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', verifyAndTriggerWelcomePopup);
        } else {
            verifyAndTriggerWelcomePopup();
        }
    </script>
    <?php endif; ?>
</body>
</html>
