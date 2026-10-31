<?php
/**
 * ====================================================================
 * FF PANEL SELL — CENTRAL ADMIN CONSOLE (EDITION 23.0 PRO)
 * FEATURES: WEB LOGIN, PROMO/COUPON MANAGER, REFERRAL CONTROLLER,
 * DYNAMIC PAYMENT GATEWAYS, KEY MANAGER API & PREMIUM DYNAMIC UI
 * ADVANCED ZERO-CRASH DISCOUNT ENGINE & REAL-TIME PRODUCT PRICER
 * ====================================================================
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);

// PREVENT ANY CACHING — INSTANT REAL-TIME ADMIN UPDATES
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Media Storage Directory Provisioning
$prodUploadDir    = __DIR__ . '/uploads/products/';
$bannerUploadDir  = __DIR__ . '/uploads/banners/';
$siteUploadDir    = __DIR__ . '/uploads/site/';
$gatewayUploadDir = __DIR__ . '/uploads/gateways/';

if (!is_dir($prodUploadDir))    @mkdir($prodUploadDir, 0755, true);
if (!is_dir($bannerUploadDir))  @mkdir($bannerUploadDir, 0755, true);
if (!is_dir($siteUploadDir))    @mkdir($siteUploadDir, 0755, true);
if (!is_dir($gatewayUploadDir)) @mkdir($gatewayUploadDir, 0755, true);

// ====================================================================
// FIXED STORE CATEGORIES (USED BY INDEX FILTER)
// ====================================================================
$STORE_CATEGORIES = ['NON ROOT', 'ROOT', 'I PHONE', 'PC', 'Other'];

function normalize_store_category(string $cat, array $allowed): string {
    $c = strtoupper(trim($cat));
    $map = [
        'APKMOD'   => 'NON ROOT',
        'NON-ROOT' => 'NON ROOT',
        'NONROOT'  => 'NON ROOT',
        'IPHONE'   => 'I PHONE',
        'IOS'      => 'I PHONE',
        'WINDOWS'  => 'PC',
        'DESKTOP'  => 'PC',
        'OTHER'    => 'OTHER',
    ];
    if (isset($map[$c])) $c = $map[$c];
    foreach ($allowed as $a) {
        if (strtoupper($a) === $c) return $a;
    }
    return 'NON ROOT';
}

// ====================================================================
// CRITICAL ADMIN AUTO-HEALER (PERMANENTLY FIXES SQL ERRORS)
// ====================================================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `system_settings` (
        `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
        `setting_value` TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `promo_codes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(50) NOT NULL UNIQUE,
        `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
        `target_type` VARCHAR(20) NOT NULL DEFAULT 'all',
        `product_id` INT NOT NULL DEFAULT 0,
        `max_uses` INT NOT NULL DEFAULT 100,
        `used_count` INT NOT NULL DEFAULT 0,
        `min_order_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `expires_at` DATETIME NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_custom_prices` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `plan_id` INT NOT NULL,
        `custom_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        UNIQUE KEY `idx_user_plan` (`user_id`, `plan_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_allowed_products` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `product_id` INT NOT NULL,
        UNIQUE KEY `idx_user_prod` (`user_id`, `product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Repair promo_codes table
    $promoCols = $pdo->query("DESCRIBE `promo_codes`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('target_type', $promoCols)) {
        $pdo->exec("ALTER TABLE `promo_codes` ADD COLUMN `target_type` VARCHAR(20) NOT NULL DEFAULT 'all'");
    }
    if (!in_array('product_id', $promoCols)) {
        $pdo->exec("ALTER TABLE `promo_codes` ADD COLUMN `product_id` INT NOT NULL DEFAULT 0");
    }
    if (!in_array('max_uses', $promoCols)) {
        $pdo->exec("ALTER TABLE `promo_codes` ADD COLUMN `max_uses` INT NOT NULL DEFAULT 100");
    }
    if (!in_array('used_count', $promoCols)) {
        $pdo->exec("ALTER TABLE `promo_codes` ADD COLUMN `used_count` INT NOT NULL DEFAULT 0");
    }
    if (!in_array('min_order_amount', $promoCols)) {
        $pdo->exec("ALTER TABLE `promo_codes` ADD COLUMN `min_order_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }
    if (!in_array('is_active', $promoCols)) {
        $pdo->exec("ALTER TABLE `promo_codes` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1");
    }
    if (!in_array('expires_at', $promoCols)) {
        $pdo->exec("ALTER TABLE `promo_codes` ADD COLUMN `expires_at` DATETIME NULL");
    }

    // Repair users table
    $userCols = $pdo->query("DESCRIBE `users`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('discount_percent', $userCols)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00");
    }
    if (!in_array('extra_discount', $userCols)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `extra_discount` DECIMAL(5,2) NOT NULL DEFAULT 0.00");
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
    if (!in_array('referral_code', $userCols)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `referral_code` VARCHAR(50) NULL");
    }
    if (!in_array('referral_earnings', $userCols)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `referral_earnings` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }

    // Repair product_plans table
    $planCols = $pdo->query("DESCRIBE `product_plans`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('regular_price', $planCols)) {
        $pdo->exec("ALTER TABLE `product_plans` ADD COLUMN `regular_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        $pdo->exec("UPDATE `product_plans` SET `regular_price` = `user_price` WHERE `regular_price` <= 0");
    }
    if (!in_array('discount_percent', $planCols)) {
        $pdo->exec("ALTER TABLE `product_plans` ADD COLUMN `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00");
    }
    if (!in_array('reseller_discount_percent', $planCols)) {
        $pdo->exec("ALTER TABLE `product_plans` ADD COLUMN `reseller_discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00");
    }
    if (!in_array('is_out_of_stock', $planCols)) {
        $pdo->exec("ALTER TABLE `product_plans` ADD COLUMN `is_out_of_stock` TINYINT(1) NOT NULL DEFAULT 0");
    }

    // Repair products table for maintenance rules
    $prodCols = $pdo->query("DESCRIBE `products`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('maintenance_message', $prodCols)) {
        $pdo->exec("ALTER TABLE `products` ADD COLUMN `maintenance_message` TEXT NULL");
    }
} catch (Throwable $e) {}

// ====================================================================
// 1. WEB ADMIN LOGIN & LOGOUT CONTROLLER
// ====================================================================
$authError = null;

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_username']);
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_web_login') {
    $inputUser = trim($_POST['admin_username'] ?? '');
    $inputPass = $_POST['admin_password'] ?? '';

    $confUser = defined('ADMIN_USERNAME') ? ADMIN_USERNAME : 'admin';
    $confPass = defined('ADMIN_PASSWORD') ? ADMIN_PASSWORD : 'Admin@12345';

    if ($inputUser === $confUser && ($inputPass === $confPass || password_verify($inputPass, $confPass))) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username']  = $inputUser;
        header('Location: admin.php');
        exit;
    } else {
        $authError = "Invalid Username or Password!";
    }
}

// RENDER SECURE GREEN & WHITE ADMIN LOGIN PAGE IF NOT AUTHENTICATED
if (empty($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title>Admin Portal — <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'FF Panel Sell' ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        <style>
            body { background: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; color: #0f172a; }
            .login-card { animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
            @keyframes slideUpFade { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        </style>
    </head>
    <body class="min-h-screen flex items-center justify-center p-4">
        <div class="login-card bg-white border border-slate-200 rounded-3xl p-7 max-w-sm w-full space-y-5 shadow-2xl text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white flex items-center justify-center text-3xl shadow-lg border border-emerald-500/50">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            
            <div class="space-y-1">
                <h2 class="text-lg font-black text-slate-900 uppercase tracking-wider">
                    <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'FF Panel Sell' ?>
                </h2>
                <p class="text-xs text-slate-500 font-semibold">Sign in to Admin Dashboard</p>
            </div>

            <?php if ($authError): ?>
                <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 text-xs font-bold leading-tight">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?= htmlspecialchars($authError) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-3.5 text-left text-xs font-semibold">
                <input type="hidden" name="action" value="admin_web_login">
                <div>
                    <label class="text-[11px] font-bold text-slate-700 uppercase block mb-1">Username</label>
                    <input type="text" name="admin_username" required placeholder="admin" autocomplete="username" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 transition font-mono text-slate-900 shadow-inner">
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-700 uppercase block mb-1">Password</label>
                    <input type="password" name="admin_password" required placeholder="••••••••" autocomplete="current-password" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 transition font-mono text-slate-900 shadow-inner">
                </div>
                <button type="submit" class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold uppercase text-xs tracking-wider shadow-lg active:scale-95 transition mt-1">
                    Sign In to Console
                </button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ====================================================================
// 2. GLOBAL SYSTEM SETTINGS RESOLUTION
// ====================================================================
$settings = [
    'site_name'                    => defined('SITE_NAME') ? SITE_NAME : 'FF PANEL SELL',
    'site_logo'                    => '',
    'loading_logo'                 => '',
    'theme_color'                  => '#15803d',
    'usdt_rate'                    => '125',
    'binance_market_rate'          => '125',
    'bkash_number'                 => '01700000000',
    'nagad_number'                 => '01800000000',
    'rocket_number'                => '01900000000',
    'binance_id'                   => '12345678',
    'payment_banner_url'           => '',
    'app_download_url'             => '',
    'support_whatsapp'             => '',
    'support_telegram'             => '',
    'support_facebook'             => '',
    'support_channel'              => '',
    'referral_reward_amount'       => '5.00',
    'referral_system_status'       => '1',
    'referral_bdt_enabled'         => '1',
    'referral_coin_enabled'        => '1',
    'auto_payment_on'              => '0',
    'skypay_base_url'              => 'https://core.skypaybd.top',
    'skypay_brand_key'             => '',
    'skypay_auto_verify'           => '0',
    'deposit_system_status'        => '1',
    'site_maintenance_mode'        => '0',
    'site_maintenance_msg'         => 'System upgrade in progress.',
    'min_deposit_limit'            => '5.00',
    'max_deposit_limit'            => '500.00',
    'telegram_tutorial_link'       => defined('SUPPORT_LINK') ? SUPPORT_LINK : 'https://t.me/MotherPanelsBoT',
    'update_channel_popup_enabled' => '0',
    'update_channel_title'         => 'Join Community',
    'update_channel_msg'           => 'Stay updated with latest passes.',
    'update_channel_link'          => '',
    'spin_wheel_enabled'           => '1',
    'spin_cost'                    => '0.00',
    'spin_daily_limit'             => '10',
    'spin_offer_duration_hours'    => '24',
    'spin_spend_per_spin'          => '500',
    'spin_prize_min'               => '1',
    'spin_prize_max'               => '50',
    'spin_refer_enabled'           => '0',
    'spin_refer_per_spin'          => '1',
    'footer_copyright_text'        => '',
    'km_api_url'                   => defined('KM_DEFAULT_API_URL') ? KM_DEFAULT_API_URL : 'https://keymanager.shop/api.php',
    'km_api_key'                   => defined('KM_DEFAULT_API_KEY') ? KM_DEFAULT_API_KEY : '',
    'km_last_status'               => 'untested',
    'km_cached_balance'            => '$0.00 USD',
    'km_cached_balance_time'       => '0',
    'google_client_id'             => '',
    'google_client_secret'         => '',
    'google_redirect_uri'          => ''
];

try {
    $dbSettings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($dbSettings)) $settings = array_merge($settings, $dbSettings);
} catch (Throwable $e) {}

$effectiveBdtRate   = max(1.0, (float)($settings['usdt_rate'] ?? 125.0));
$primaryThemeColor  = !empty($settings['theme_color']) ? trim($settings['theme_color']) : '#15803d';
$storeTitleName     = !empty($settings['site_name']) ? $settings['site_name'] : 'FF PANEL SELL';

// ====================================================================
// 3. SKYPAY BD & KEY MANAGER CONNECTORS
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
                    $paid = (float)($d['amount'] ?? 0);
                    return [
                        'status'  => 'success',
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
        if ($v['err'] !== '' || $j === null || $v['http'] >= 500) {
            return ['status' => 'error', 'server_error' => true, 'message' => 'Payment server is not responding right now. Please try again.'];
        }
        if ($v['http'] === 400 && stripos($msg, 'already') !== false) {
            return ['status' => 'error', 'duplicate' => true, 'message' => 'This TrxID has already been used!'];
        }
        return ['status' => 'error', 'message' => $msg !== '' ? $msg : 'Transaction ID not found. Please check your TrxID and try again.'];
    }
}


function km_get_cached_provider_balance(PDO $pdo): string {
    global $settings;

    $now = time();
    $cachedTime = (int)($settings['km_cached_balance_time'] ?? 0);
    $cachedVal  = $settings['km_cached_balance'] ?? '$0.00 USD';

    if (($now - $cachedTime) < 90 && !empty($cachedVal) && $cachedVal !== 'API Error' && $cachedVal !== 'Connection Failed') {
        return $cachedVal;
    }

    $ch = curl_init();
    $kmUrl = trim($settings['km_api_url'] ?? '');
    $kmKey = trim($settings['km_api_key'] ?? '');

    if (empty($kmUrl) || empty($kmKey)) return '$0.00 USD';

    $target = rtrim($kmUrl, '/');
    if (stripos($target, '.php') === false) $target .= '/api.php';

    curl_setopt_array($ch, [
        CURLOPT_URL            => $target . '?action=balance&api_key=' . urlencode($kmKey),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($body ?: '', true);
    if ($data && isset($data['status']) && $data['status'] === 'success') {
        $formatted = '$' . number_format((float)($data['balance'] ?? 0), 2) . ' USD';
        try {
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('km_cached_balance', ?), ('km_cached_balance_time', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
                ->execute([$formatted, (string)$now]);
            $settings['km_cached_balance'] = $formatted;
            $settings['km_cached_balance_time'] = (string)$now;
        } catch (Throwable $e) {}
        return $formatted;
    }

    return $cachedVal ?: '$0.00 USD';
}

function fetchKeyManagerCatalog(PDO $pdo): ?array {
    global $settings;
    $kmUrl = trim($settings['km_api_url'] ?? '');
    $kmKey = trim($settings['km_api_key'] ?? '');

    if (empty($kmUrl) || empty($kmKey)) return null;

    $target = rtrim($kmUrl, '/');
    if (stripos($target, '.php') === false) $target .= '/api.php';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $target . '?action=products&api_key=' . urlencode($kmKey),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    $res = json_decode($body ?: '', true);
    if (isset($res['status']) && $res['status'] === 'success') {
        $raw = $res['products'] ?? ($res['data']['products'] ?? ($res['data'] ?? []));
        $normalizedPlans = [];

        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (!empty($item['plans']) && is_array($item['plans'])) {
                    $prodName = $item['product_name'] ?? ($item['name'] ?? 'License Product');
                    foreach ($item['plans'] as $pl) {
                        $normalizedPlans[] = [
                            'id'            => (int)($pl['plan_id'] ?? ($pl['id'] ?? 0)),
                            'product_id'    => (int)($item['product_id'] ?? 0),
                            'product_name'  => $prodName,
                            'plan_title'    => $pl['validity'] ?? ($pl['plan_title'] ?? '30 Days'),
                            'price'         => (float)($pl['price'] ?? ($pl['user_price'] ?? 5.00)),
                            'regular_price' => (float)($pl['regular_price'] ?? ($pl['price'] ?? 5.00)),
                            'discount'      => (int)($pl['discount_percent'] ?? 0)
                        ];
                    }
                } elseif (isset($item['id']) || isset($item['plan_id'])) {
                    $planId = (int)($item['plan_id'] ?? $item['id']);
                    $prodName = $item['product_name'] ?? ($item['name'] ?? 'License Product');
                    $planTitle = $item['validity'] ?? ($item['plan_title'] ?? ($item['name'] ?? 'Plan'));
                    $normalizedPlans[] = [
                        'id'            => $planId,
                        'product_id'    => (int)($item['product_id'] ?? 0),
                        'product_name'  => $prodName,
                        'plan_title'    => $planTitle,
                        'price'         => (float)($item['price'] ?? ($item['user_price'] ?? 5.00)),
                        'regular_price' => (float)($item['regular_price'] ?? ($item['price'] ?? 5.00)),
                        'discount'      => (int)($item['discount_percent'] ?? 0)
                    ];
                }
            }
            return $normalizedPlans;
        }
    }
    return null;
}

if (isset($_GET['action']) && $_GET['action'] === 'fetch_km_catalog') {
    header('Content-Type: application/json');
    $items = fetchKeyManagerCatalog($pdo);
    if (is_array($items) && !empty($items)) {
        echo json_encode(['success' => true, 'items' => $items]);
    } else {
        $err = empty($settings['km_api_key']) ? 'API Key is empty in Key Manager tab.' : 'Failed to retrieve catalog from Key Manager.';
        echo json_encode(['success' => false, 'error' => $err]);
    }
    exit;
}

// ====================================================================
// 3B. EXTRA SCHEMA (referral coins, key status) + KEY SEARCH / STOP / BAN AJAX
// ====================================================================
$ffAdminSchemaLock = sys_get_temp_dir() . '/ff_admin_schema_coin_v1.lock';
if (!file_exists($ffAdminSchemaLock)) {
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
        $rc = $pdo->query("DESCRIBE `referrals`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('reward_coins', $rc)) {
            $pdo->exec("ALTER TABLE `referrals` ADD COLUMN `reward_coins` INT NOT NULL DEFAULT 0");
        }
        $lc = $pdo->query("DESCRIBE `licenses`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('status', $lc)) {
            $pdo->exec("ALTER TABLE `licenses` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active'");
        }
        @file_put_contents($ffAdminSchemaLock, (string)time());
    } catch (Throwable $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action']) && in_array($_POST['admin_action'], ['search_keys', 'key_action'], true)) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    try {
        if ($_POST['admin_action'] === 'search_keys') {
            $q = trim($_POST['q'] ?? '');
            if (mb_strlen($q) < 2) {
                echo json_encode(['status' => 'error', 'message' => 'Type at least 2 characters.']);
                exit;
            }
            $like = '%' . $q . '%';
            $stmt = $pdo->prepare("SELECT l.id, l.user_id, l.license_key, l.validity, l.payment_type, l.created_at,
                                          COALESCE(l.status, 'active') AS key_status,
                                          u.username, u.email, COALESCE(u.status, 'active') AS user_status
                                   FROM licenses l
                                   LEFT JOIN users u ON u.id = l.user_id
                                   WHERE l.license_key LIKE ? OR u.email LIKE ? OR u.username LIKE ?
                                      OR l.user_id IN (SELECT user_id FROM transactions WHERE trx_id LIKE ?)
                                   ORDER BY l.id DESC LIMIT 40");
            $stmt->execute([$like, $like, $like, $like]);
            echo json_encode(['status' => 'success', 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // key_action
        $op = $_POST['op'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($op === 'stop_key' || $op === 'resume_key') {
            $stmt = $pdo->prepare("SELECT * FROM licenses WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $lic = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$lic) { echo json_encode(['status' => 'error', 'message' => 'Key not found.']); exit; }

            $newStatus = ($op === 'stop_key') ? 'stopped' : 'active';
            $pdo->prepare("UPDATE licenses SET status = ? WHERE id = ?")->execute([$newStatus, $id]);

            // Also stop on the Key Manager side if config.php provides such a function
            $remote = null;
            $fn = ($op === 'stop_key') ? 'km_stop_key' : 'km_resume_key';
            if (function_exists($fn)) {
                try {
                    $r = $fn($pdo, (string)$lic['license_key'], (int)($lic['plan_id'] ?? 0));
                    $remote = is_array($r) ? (($r['status'] ?? '') === 'success' || ($r['success'] ?? false) === true) : (bool)$r;
                } catch (Throwable $e) { $remote = false; }
            }
            echo json_encode(['status' => 'success', 'key_status' => $newStatus, 'remote' => $remote]);
            exit;
        }

        if ($op === 'ban_user' || $op === 'unban_user') {
            $newStatus = ($op === 'ban_user') ? 'banned' : 'active';
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
            echo json_encode(['status' => 'success', 'user_status' => $newStatus]);
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed: ' . $e->getMessage()]);
    }
    exit;
}

// ====================================================================
// 4. POST ACTIONS ROUTER (PRODUCTS, COUPONS, REFERRALS, PRICING)
// ====================================================================
$toast            = null;
$activeTab        = $_POST['active_tab'] ?? 'tab-overview';
$activeDiscSubTab = $_POST['disc_sub_tab'] ?? 'disc-view-products';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {
    $act       = $_POST['admin_action'];
    $activeTab = $_POST['active_tab'] ?? 'tab-overview';
    if (!empty($_POST['disc_sub_tab'])) {
        $activeDiscSubTab = $_POST['disc_sub_tab'];
    }

    // A. QUICK RATE & THEME COLOR
    if ($act === 'update_usdt_rate_quick') {
        $newRate = max(1.0, (float)($_POST['usdt_rate'] ?? 125));
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('usdt_rate', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$newRate, $newRate]);
        $settings['usdt_rate'] = (string)$newRate;
        $effectiveBdtRate = $newRate;
        $toast = ['type' => 'success', 'msg' => "Exchange Rate Set: 1 USD = $newRate BDT"];
    }

    if ($act === 'update_site_theme_color') {
        $newColor = trim($_POST['theme_color'] ?? '#15803d');
        if (!preg_match('/^#[a-f0-9]{6}$/i', $newColor) && !preg_match('/^#[a-f0-9]{3}$/i', $newColor)) {
            $newColor = '#15803d';
        }
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('theme_color', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$newColor, $newColor]);
        $settings['theme_color'] = $newColor;
        $primaryThemeColor = $newColor;
        $toast = ['type' => 'success', 'msg' => "Store theme color updated!"];
    }

    // B2. REFERRAL COIN RULES (add / delete)
    if ($act === 'add_ref_coin_rule') {
        $ruleType = ($_POST['rule_type'] ?? 'reward') === 'convert' ? 'convert' : 'reward';
        $coins    = max(1, (int)($_POST['coins'] ?? 0));
        $taka     = ($ruleType === 'convert') ? max(0.01, (float)($_POST['taka'] ?? 0)) : 0.00;
        $title    = trim($_POST['title'] ?? '');
        if ($title === '') {
            $title = ($ruleType === 'reward')
                ? number_format($coins) . ' Coins per Referral'
                : number_format($coins) . ' Coins = ৳' . number_format($taka, 0);
        }
        try {
            $pdo->prepare("INSERT INTO referral_coin_rules (rule_type, title, coins, taka, is_active) VALUES (?, ?, ?, ?, 1)")
                ->execute([$ruleType, $title, $coins, $taka]);
            $toast = ['type' => 'success', 'msg' => 'Coin rule added!'];
        } catch (Throwable $e) {
            $toast = ['type' => 'error', 'msg' => 'Error: ' . $e->getMessage()];
        }
    }

    if ($act === 'edit_ref_coin_rule') {
        $rid     = (int)($_POST['rule_id'] ?? 0);
        $coins   = max(1, (int)($_POST['coins'] ?? 0));
        $title   = trim($_POST['title'] ?? '');
        try {
            $stT = $pdo->prepare("SELECT rule_type FROM referral_coin_rules WHERE id = ? LIMIT 1");
            $stT->execute([$rid]);
            $rt = $stT->fetchColumn();
            if ($rt === false) {
                $toast = ['type' => 'error', 'msg' => 'Rule not found!'];
            } else {
                $taka = ($rt === 'convert') ? max(0.01, (float)($_POST['taka'] ?? 0)) : 0.00;
                if ($title === '') {
                    $title = ($rt === 'reward')
                        ? number_format($coins) . ' Coins per Referral'
                        : number_format($coins) . ' Coins = ৳' . number_format($taka, 0);
                }
                $pdo->prepare("UPDATE referral_coin_rules SET title = ?, coins = ?, taka = ? WHERE id = ?")
                    ->execute([$title, $coins, $taka, $rid]);
                $toast = ['type' => 'success', 'msg' => 'Coin rule updated! Users will see it instantly.'];
            }
        } catch (Throwable $e) {
            $toast = ['type' => 'error', 'msg' => 'Error: ' . $e->getMessage()];
        }
    }

    if ($act === 'toggle_ref_coin_rule') {
        $pdo->prepare("UPDATE referral_coin_rules SET is_active = 1 - is_active WHERE id = ?")->execute([(int)($_POST['rule_id'] ?? 0)]);
        $toast = ['type' => 'success', 'msg' => 'Coin rule updated!'];
    }

    if ($act === 'delete_ref_coin_rule') {
        $pdo->prepare("DELETE FROM referral_coin_rules WHERE id = ?")->execute([(int)($_POST['rule_id'] ?? 0)]);
        $toast = ['type' => 'success', 'msg' => 'Coin rule deleted!'];
    }

    // B. REFERRAL SYSTEM CONTROLLER
    if ($act === 'save_referral_settings') {
        $rewardAmt = max(0.0, (float)($_POST['referral_reward_amount'] ?? 5.00));
        $status    = isset($_POST['referral_system_status']) ? '1' : '0';
        $bdtOn     = isset($_POST['referral_bdt_enabled']) ? '1' : '0';
        $coinOn    = isset($_POST['referral_coin_enabled']) ? '1' : '0';

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['referral_reward_amount', (string)$rewardAmt, (string)$rewardAmt]);
        $stmt->execute(['referral_system_status', $status, $status]);
        $stmt->execute(['referral_bdt_enabled', $bdtOn, $bdtOn]);
        $stmt->execute(['referral_coin_enabled', $coinOn, $coinOn]);

        $settings['referral_reward_amount'] = (string)$rewardAmt;
        $settings['referral_system_status'] = $status;
        $settings['referral_bdt_enabled']   = $bdtOn;
        $settings['referral_coin_enabled']  = $coinOn;
        $toast = ['type' => 'success', 'msg' => "Referral saved! BDT bonus: " . ($bdtOn === '1' ? ('ON (৳' . number_format($rewardAmt, 2) . ')') : 'OFF') . " | Coin system: " . ($coinOn === '1' ? 'ON' : 'OFF')];
    }

    // C. COUPON / PROMO CODE MANAGEMENT (100% FIXED & BULLETPROOF)
    if ($act === 'save_promo_code') {
        // ১. ডাটাবেস টেবিল অটো-চেক ও মিসিং কলাম রিপেয়ার (কোনো SQL ক্র্যাশ হবে না)
        try {
            $cols = $pdo->query("DESCRIBE `promo_codes`")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('target_type', $cols)) $pdo->exec("ALTER TABLE `promo_codes` ADD COLUMN `target_type` VARCHAR(20) NOT NULL DEFAULT 'all'");
            if (!in_array('product_id', $cols)) $pdo->exec("ALTER TABLE `promo_codes` ADD COLUMN `product_id` INT NOT NULL DEFAULT 0");
            if (!in_array('max_uses', $cols)) $pdo->exec("ALTER TABLE `promo_codes` ADD COLUMN `max_uses` INT NOT NULL DEFAULT 100");
            if (!in_array('used_count', $cols)) $pdo->exec("ALTER TABLE `promo_codes` ADD COLUMN `used_count` INT NOT NULL DEFAULT 0");
            if (!in_array('is_active', $cols)) $pdo->exec("ALTER TABLE `promo_codes` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1");
            if (!in_array('expires_at', $cols)) $pdo->exec("ALTER TABLE `promo_codes` ADD COLUMN `expires_at` DATETIME NULL");
        } catch (Throwable $e) {}

        $promoId     = (int)($_POST['promo_id'] ?? 0);
        $code        = strtoupper(trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['code'] ?? '')));
        $discPercent = max(0.5, min(99.0, (float)($_POST['discount_percent'] ?? 10.0)));
        $targetType  = in_array($_POST['target_type'] ?? '', ['all', 'specific']) ? $_POST['target_type'] : 'all';
        $productId   = ($targetType === 'specific') ? (int)($_POST['product_id'] ?? 0) : 0;
        $maxUses     = max(1, (int)($_POST['max_uses'] ?? 100));
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        
        // ২. তারিখের ভ্যালিডেশন এবং MySQL ফরম্যাটে কনভার্ট
        $expiresAt = null;
        if (!empty($_POST['expires_at'])) {
            $timeParsed = strtotime($_POST['expires_at']);
            if ($timeParsed !== false) {
                $expiresAt = date('Y-m-d H:i:s', $timeParsed);
            }
        }

        if (empty($code)) {
            $toast = ['type' => 'error', 'msg' => "Coupon code cannot be empty!"];
        } else {
            try {
                if ($promoId > 0) {
                    // ডুপ্লিকেট কোড চেক (অন্য কোনো আইডিতে আছে কি না)
                    $chk = $pdo->prepare("SELECT id FROM promo_codes WHERE code = ? AND id != ? LIMIT 1");
                    $chk->execute([$code, $promoId]);
                    if ($chk->fetch()) {
                        $toast = ['type' => 'error', 'msg' => "Code '$code' already exists on another coupon!"];
                    } else {
                        $stmt = $pdo->prepare("UPDATE promo_codes SET code = ?, discount_percent = ?, target_type = ?, product_id = ?, max_uses = ?, is_active = ?, expires_at = ? WHERE id = ?");
                        $stmt->execute([$code, $discPercent, $targetType, $productId, $maxUses, $isActive, $expiresAt, $promoId]);
                        $toast = ['type' => 'success', 'msg' => "Promo Code '$code' updated successfully!"];
                    }
                } else {
                    // নতুন কোড ডুপ্লিকেট কি না চেক
                    $chk = $pdo->prepare("SELECT id FROM promo_codes WHERE code = ? LIMIT 1");
                    $chk->execute([$code]);
                    if ($chk->fetch()) {
                        $toast = ['type' => 'error', 'msg' => "Promo Code '$code' already exists! Use a different code."];
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO promo_codes (code, discount_percent, target_type, product_id, max_uses, is_active, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$code, $discPercent, $targetType, $productId, $maxUses, $isActive, $expiresAt]);
                        $toast = ['type' => 'success', 'msg' => "New Promo Code '$code' created with {$discPercent}% discount!"];
                    }
                }
            } catch (Throwable $e) {
                $toast = ['type' => 'error', 'msg' => "Database Error: " . $e->getMessage()];
            }
        }
    }

    if ($act === 'delete_promo_code') {
        $pId = (int)$_POST['promo_id'];
        try {
            $pdo->prepare("DELETE FROM promo_codes WHERE id = ?")->execute([$pId]);
            $toast = ['type' => 'success', 'msg' => "Coupon deleted!"];
        } catch (Throwable $e) {
            $toast = ['type' => 'error', 'msg' => "Failed to delete: " . $e->getMessage()];
        }
    }

    if ($act === 'toggle_promo_code') {
        $pId  = (int)$_POST['promo_id'];
        $curr = (int)$_POST['current_status'];
        $new  = ($curr === 1) ? 0 : 1;
        try {
            $pdo->prepare("UPDATE promo_codes SET is_active = ? WHERE id = ?")->execute([$new, $pId]);
            $toast = ['type' => 'success', 'msg' => "Coupon status toggled!"];
        } catch (Throwable $e) {
            $toast = ['type' => 'error', 'msg' => "Error: " . $e->getMessage()];
        }
    }

    // D. KEY MANAGER SYNC & CREDENTIALS
    if ($act === 'save_km_credentials') {
        $kmUrl = trim($_POST['km_api_url'] ?? '');
        $kmKey = trim($_POST['km_api_key'] ?? '');

        if (!empty($kmUrl) && !empty($kmKey)) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute(['km_api_url', $kmUrl, $kmUrl]);
            $stmt->execute(['km_api_key', $kmKey, $kmKey]);
            $settings['km_api_url'] = $kmUrl;
            $settings['km_api_key'] = $kmKey;

            $ch = curl_init();
            $target = rtrim($kmUrl, '/');
            if (stripos($target, '.php') === false) $target .= '/api.php';
            curl_setopt_array($ch, [
                CURLOPT_URL            => $target . '?action=balance&api_key=' . urlencode($kmKey),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            $testBody = curl_exec($ch);
            curl_close($ch);

            $testRes = json_decode($testBody ?: '', true);
            if (isset($testRes['status']) && $testRes['status'] === 'success') {
                $bal = number_format((float)($testRes['balance'] ?? 0), 2);
                $stmt->execute(['km_last_status', 'connected', 'connected']);
                $stmt->execute(['km_cached_balance', '$' . $bal . ' USD', '$' . $bal . ' USD']);
                $stmt->execute(['km_cached_balance_time', (string)time(), (string)time()]);
                $settings['km_last_status'] = 'connected';
                $settings['km_cached_balance'] = '$' . $bal . ' USD';
                $toast = ['type' => 'success', 'msg' => "Connected! Balance: $$bal USD"];
            } else {
                $errMsg = $testRes['message'] ?? 'Could not authenticate.';
                $stmt->execute(['km_last_status', 'failed', 'failed']);
                $settings['km_last_status'] = 'failed';
                $toast = ['type' => 'error', 'msg' => "Connection Failed: $errMsg"];
            }
        } else {
            $toast = ['type' => 'error', 'msg' => 'API URL and Secret Key are required!'];
        }
    }

    if ($act === 'test_km_connection') {
        $ch = curl_init();
        $kmUrl = trim($settings['km_api_url'] ?? '');
        $kmKey = trim($settings['km_api_key'] ?? '');
        $target = rtrim($kmUrl, '/');
        if (stripos($target, '.php') === false) $target .= '/api.php';

        curl_setopt_array($ch, [
            CURLOPT_URL            => $target . '?action=balance&api_key=' . urlencode($kmKey),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $testBody = curl_exec($ch);
        curl_close($ch);

        $testRes = json_decode($testBody ?: '', true);
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        if (isset($testRes['status']) && $testRes['status'] === 'success') {
            $bal = number_format((float)($testRes['balance'] ?? 0), 2);
            $stmt->execute(['km_last_status', 'connected', 'connected']);
            $stmt->execute(['km_cached_balance', '$' . $bal . ' USD', '$' . $bal . ' USD']);
            $stmt->execute(['km_cached_balance_time', (string)time(), (string)time()]);
            $settings['km_last_status'] = 'connected';
            $settings['km_cached_balance'] = '$' . $bal . ' USD';
            $toast = ['type' => 'success', 'msg' => "Operational! Balance: $$bal USD"];
        } else {
            $errMsg = $testRes['message'] ?? 'Connection error.';
            $stmt->execute(['km_last_status', 'failed', 'failed']);
            $settings['km_last_status'] = 'failed';
            $toast = ['type' => 'error', 'msg' => "Failed: $errMsg"];
        }
    }

    if ($act === 'sync_km_products') {
        $items = fetchKeyManagerCatalog($pdo);
        if (is_array($items) && !empty($items)) {
            $pdo->beginTransaction();
            $syncedCount = 0;
            try {
                foreach ($items as $p) {
                    $extId       = (int)$p['id'];
                    $productName = trim($p['product_name']);
                    $planTitle   = trim($p['plan_title']);
                    $basePrice   = (float)$p['price'];

                    $code = strtolower(preg_replace('/[^a-z0-9_]/i', '_', $productName));
                    $stmtProd = $pdo->prepare("SELECT id FROM products WHERE name = ? LIMIT 1");
                    $stmtProd->execute([$productName]);
                    $prodId = $stmtProd->fetchColumn();

                    if (!$prodId) {
                        $stmtIns = $pdo->prepare("INSERT INTO products (name, code, sort_order, is_active, category, download_link, setup_link, tags, description) VALUES (?, ?, 0, 1, 'NON ROOT', '', '', '#VERIFIED', 'Instant Delivery')");
                        $stmtIns->execute([$productName, $code]);
                        $prodId = (int)$pdo->lastInsertId();
                    }

                    $stmtPlan = $pdo->prepare("SELECT id FROM product_plans WHERE api_plan_id = ? LIMIT 1");
                    $stmtPlan->execute([$extId]);
                    $planExists = $stmtPlan->fetchColumn();

                    $resellerPrice = $basePrice;
                    $userPrice     = round($basePrice * 1.25, 2);

                    if (!$planExists) {
                        $stmtPlanIns = $pdo->prepare("INSERT INTO product_plans (product_id, duration_days, plan_title, reseller_price, user_price, regular_price, discount_percent, reseller_discount_percent, api_plan_id) VALUES (?, 30, ?, ?, ?, ?, 0.00, 20.00, ?)");
                        $stmtPlanIns->execute([$prodId, $planTitle, $resellerPrice, $userPrice, $userPrice, $extId]);
                    } else {
                        $stmtPlanUp = $pdo->prepare("UPDATE product_plans SET product_id = ?, plan_title = ?, reseller_price = ?, user_price = ?, regular_price = ? WHERE api_plan_id = ?");
                        $stmtPlanUp->execute([$prodId, $planTitle, $resellerPrice, $userPrice, $userPrice, $extId]);
                    }
                    $syncedCount++;
                }
                $pdo->commit();
                $toast = ['type' => 'success', 'msg' => "Synced $syncedCount plans successfully!"];
            } catch (Throwable $e) {
                $pdo->rollBack();
                $toast = ['type' => 'error', 'msg' => 'Sync Error: ' . $e->getMessage()];
            }
        } else {
            $toast = ['type' => 'error', 'msg' => 'Could not fetch catalog. Test connection!'];
        }
    }

    if ($act === 'sync_selected_km_products') {
        $selectedExtIds = $_POST['selected_api_ids'] ?? [];
        if (empty($selectedExtIds)) {
            $toast = ['type' => 'error', 'msg' => 'No plans selected!'];
        } else {
            $items = fetchKeyManagerCatalog($pdo);
            if (is_array($items) && !empty($items)) {
                $pdo->beginTransaction();
                $syncedCount = 0;
                try {
                    foreach ($items as $p) {
                        $extId = (int)$p['id'];
                        if (!in_array((string)$extId, $selectedExtIds)) continue;

                        $productName = trim($p['product_name']);
                        $planTitle   = trim($p['plan_title']);
                        $basePrice   = (float)$p['price'];

                        $code = strtolower(preg_replace('/[^a-z0-9_]/i', '_', $productName));
                        $stmtProd = $pdo->prepare("SELECT id FROM products WHERE name = ? LIMIT 1");
                        $stmtProd->execute([$productName]);
                        $prodId = $stmtProd->fetchColumn();

                        if (!$prodId) {
                            $stmtIns = $pdo->prepare("INSERT INTO products (name, code, sort_order, is_active, category, download_link, setup_link, tags, description) VALUES (?, ?, 0, 1, 'NON ROOT', '', '', '#VERIFIED', 'Instant Delivery')");
                            $stmtIns->execute([$productName, $code]);
                            $prodId = (int)$pdo->lastInsertId();
                        }

                        $stmtPlan = $pdo->prepare("SELECT id FROM product_plans WHERE api_plan_id = ? LIMIT 1");
                        $stmtPlan->execute([$extId]);
                        $planExists = $stmtPlan->fetchColumn();

                        $resellerPrice = $basePrice;
                        $userPrice     = round($basePrice * 1.25, 2);

                        if (!$planExists) {
                            $stmtPlanIns = $pdo->prepare("INSERT INTO product_plans (product_id, duration_days, plan_title, reseller_price, user_price, regular_price, discount_percent, reseller_discount_percent, api_plan_id) VALUES (?, 30, ?, ?, ?, ?, 0.00, 20.00, ?)");
                            $stmtPlanIns->execute([$prodId, $planTitle, $resellerPrice, $userPrice, $userPrice, $extId]);
                        } else {
                            $stmtPlanUp = $pdo->prepare("UPDATE product_plans SET product_id = ?, plan_title = ?, reseller_price = ?, user_price = ?, regular_price = ? WHERE api_plan_id = ?");
                            $stmtPlanUp->execute([$prodId, $planTitle, $resellerPrice, $userPrice, $userPrice, $extId]);
                        }
                        $syncedCount++;
                    }
                    $pdo->commit();
                    $toast = ['type' => 'success', 'msg' => "Imported $syncedCount plans!"];
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $toast = ['type' => 'error', 'msg' => 'Sync Error: ' . $e->getMessage()];
                }
            } else {
                $toast = ['type' => 'error', 'msg' => 'Empty catalog received.'];
            }
        }
    }

    if ($act === 'delete_all_products_confirmed') {
        try {
            $pdo->exec("DELETE FROM stock_keys");
            $pdo->exec("DELETE FROM product_plans");
            $pdo->exec("DELETE FROM products");
            $toast = ['type' => 'success', 'msg' => 'Catalog purged successfully!'];
        } catch (Throwable $e) {
            $toast = ['type' => 'error', 'msg' => 'Wipe failed: ' . $e->getMessage()];
        }
    }

    // E. PRODUCT EDITOR & PLANS (WITH STOCK-OUT & MAINTENANCE RULES)
    if ($act === 'edit_product_full') {
        $prodId         = (int)$_POST['product_id'];
        $name           = trim($_POST['name'] ?? '');
        $category       = normalize_store_category($_POST['category'] ?? 'NON ROOT', $STORE_CATEGORIES);
        $sortOrder      = (int)($_POST['sort_order'] ?? 0);
        $isPinned       = isset($_POST['is_pinned']) ? 1 : 0;
        $isActive       = isset($_POST['is_active']) ? 1 : 0;
        $downloadLink   = trim($_POST['download_link'] ?? '');
        $setupLink      = trim($_POST['setup_link'] ?? '');
        $appDownloadUrl = trim($_POST['app_download_url'] ?? '');
        $tags           = trim($_POST['tags'] ?? '');
        $description    = trim($_POST['description'] ?? '');
        $maintMessage   = trim($_POST['maintenance_message'] ?? '');

        $curr = $pdo->query("SELECT * FROM products WHERE id = $prodId")->fetch();
        $logoUrl   = $curr['logo_url'] ?? null;
        $bannerUrl = $curr['banner_url'] ?? ($curr['image_url'] ?? null);

        if (!empty($_FILES['product_logo']['name']) && $_FILES['product_logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['product_logo']['name'], PATHINFO_EXTENSION));
            $fileName = 'prod_logo_' . $prodId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['product_logo']['tmp_name'], $prodUploadDir . $fileName)) {
                $logoUrl = 'uploads/products/' . $fileName;
            }
        }
        if (!empty($_FILES['product_banner']['name']) && $_FILES['product_banner']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['product_banner']['name'], PATHINFO_EXTENSION));
            $fileName = 'prod_banner_' . $prodId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['product_banner']['tmp_name'], $prodUploadDir . $fileName)) {
                $bannerUrl = 'uploads/products/' . $fileName;
            }
        }

        $pdo->prepare("UPDATE products SET name = ?, sort_order = ?, image_url = ?, banner_url = ?, logo_url = ?, download_link = ?, setup_link = ?, app_download_url = ?, description = ?, tags = ?, category = ?, is_pinned = ?, is_active = ?, maintenance_message = ? WHERE id = ?")
            ->execute([$name, $sortOrder, $bannerUrl, $bannerUrl, $logoUrl, $downloadLink, $setupLink, $appDownloadUrl, $description, $tags, $category, $isPinned, $isActive, $maintMessage, $prodId]);

        if (!empty($_POST['plan_user_price']) && is_array($_POST['plan_user_price'])) {
            $upPlan = $pdo->prepare("UPDATE product_plans SET user_price = ?, regular_price = ?, reseller_price = ?, is_out_of_stock = ? WHERE id = ? AND product_id = ?");
            foreach ($_POST['plan_user_price'] as $planId => $uPrice) {
                $uP = max(0.01, (float)$uPrice);
                $rP = isset($_POST['plan_reseller_price'][$planId]) ? max(0.01, (float)$_POST['plan_reseller_price'][$planId]) : round($uP * 0.8, 2);
                
                $currPlan = $pdo->query("SELECT discount_percent, regular_price FROM product_plans WHERE id = " . (int)$planId)->fetch(PDO::FETCH_ASSOC);
                $dPercent = (float)($currPlan['discount_percent'] ?? 0);
                $newRegPrice = ($dPercent > 0 && (float)($currPlan['regular_price'] ?? 0) > 0) ? (float)$currPlan['regular_price'] : $uP;

                // নির্দিষ্ট প্ল্যান Stock Out চেক
                $isPlanStockOut = !empty($_POST['plan_out_of_stock'][$planId]) ? 1 : 0;

                $upPlan->execute([$uP, $newRegPrice, $rP, $isPlanStockOut, (int)$planId, $prodId]);
            }
        }
        $toast = ['type' => 'success', 'msg' => 'Product, Plans & Maintenance Rules updated!'];
    }

    if ($act === 'add_new_product') {
        $name           = trim($_POST['name'] ?? '');
        $category       = normalize_store_category($_POST['category'] ?? 'NON ROOT', $STORE_CATEGORIES);
        $sortOrder      = (int)($_POST['sort_order'] ?? 0);
        $downloadLink   = trim($_POST['download_link'] ?? '');
        $setupLink      = trim($_POST['setup_link'] ?? '');
        $appDownloadUrl = trim($_POST['app_download_url'] ?? '');
        $tags           = trim($_POST['tags'] ?? '');
        $description    = trim($_POST['description'] ?? '');
        $isActive       = isset($_POST['is_active']) ? 1 : 0;
        $isPinned       = isset($_POST['is_pinned']) ? 1 : 0;

        if (!empty($name)) {
            $code = strtolower(preg_replace('/[^a-z0-9_]/i', '_', $name));
            $logoUrl = null;
            $bannerUrl = null;

            if (!empty($_FILES['product_logo']['name']) && $_FILES['product_logo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['product_logo']['name'], PATHINFO_EXTENSION));
                $fileName = 'prod_logo_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['product_logo']['tmp_name'], $prodUploadDir . $fileName)) {
                    $logoUrl = 'uploads/products/' . $fileName;
                }
            }
            if (!empty($_FILES['product_banner']['name']) && $_FILES['product_banner']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['product_banner']['name'], PATHINFO_EXTENSION));
                $fileName = 'prod_banner_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['product_banner']['tmp_name'], $prodUploadDir . $fileName)) {
                    $bannerUrl = 'uploads/products/' . $fileName;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO products (name, code, category, sort_order, logo_url, banner_url, image_url, download_link, setup_link, app_download_url, description, tags, is_active, is_pinned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $code, $category, $sortOrder, $logoUrl, $bannerUrl, $bannerUrl, $downloadLink, $setupLink, $appDownloadUrl, $description, $tags, $isActive, $isPinned]);
            $toast = ['type' => 'success', 'msg' => "Product created with category: $category"];
        }
    }

    if ($act === 'delete_product_entire') {
        $prodId = (int)$_POST['product_id'];
        $pdo->prepare("DELETE FROM stock_keys WHERE product_id = ?")->execute([$prodId]);
        $pdo->prepare("DELETE FROM product_plans WHERE product_id = ?")->execute([$prodId]);
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$prodId]);
        $toast = ['type' => 'success', 'msg' => 'Product removed!'];
    }

    if ($act === 'add_single_plan') {
        $prodId      = (int)$_POST['product_id'];
        $title       = trim($_POST['plan_title'] ?? '1 Day');
        $uPrice      = (float)($_POST['user_price'] ?? 1.00);
        $rPrice      = (float)($_POST['reseller_price'] ?? round($uPrice * 0.8, 2));
        $apiId       = (int)($_POST['api_plan_id'] ?? 0);

        $pdo->prepare("INSERT INTO product_plans (product_id, duration_days, plan_title, reseller_price, user_price, regular_price, discount_percent, reseller_discount_percent, api_plan_id) VALUES (?, 1, ?, ?, ?, ?, 0.00, 0.00, ?)")
            ->execute([$prodId, $title, $rPrice, $uPrice, $uPrice, $apiId]);
        $toast = ['type' => 'success', 'msg' => 'Plan added!'];
    }

    // F. INVENTORY STOCK KEYS
    if ($act === 'save_stock_keys') {
        $productId = (int)$_POST['stock_product_id'];
        $planId    = (int)$_POST['stock_plan_id'];
        $rawKeys   = trim($_POST['bulk_keys'] ?? '');
        if ($productId > 0 && $planId > 0 && !empty($rawKeys)) {
            $lines = preg_split('/\r\n|\r|\n/', $rawKeys);
            $inserted = 0;
            $stmtStock = $pdo->prepare("INSERT INTO stock_keys (product_id, plan_id, license_key, status) VALUES (?, ?, ?, 'available')");
            foreach ($lines as $line) {
                $cleanKey = trim($line);
                if (!empty($cleanKey)) {
                    $stmtStock->execute([$productId, $planId, $cleanKey]);
                    $inserted++;
                }
            }
            $toast = ['type' => 'success', 'msg' => "Added $inserted keys!"];
        }
    }

    if ($act === 'delete_single_key') {
        $keyId = (int)$_POST['key_id'];
        $pdo->prepare("DELETE FROM stock_keys WHERE id = ?")->execute([$keyId]);
        $toast = ['type' => 'success', 'msg' => 'Key deleted!'];
    }

    // G. BULK DISCOUNTS FOR PRODUCTS & ROLES
    if ($act === 'apply_bulk_product_discount') {
        $discPercent = max(0.0, min(99.0, (float)($_POST['discount_percent'] ?? 0)));
        try {
            $plans = $pdo->query("SELECT id, regular_price, user_price FROM product_plans")->fetchAll(PDO::FETCH_ASSOC);
            $stmtUp = $pdo->prepare("UPDATE product_plans SET user_price = ?, regular_price = ?, discount_percent = ? WHERE id = ?");
            foreach ($plans as $pl) {
                $regular = (float)$pl['regular_price'] > 0 ? (float)$pl['regular_price'] : (float)$pl['user_price'];
                $newPrice = ($discPercent > 0) ? max(0.01, round($regular * (1 - ($discPercent / 100)), 2)) : $regular;
                $stmtUp->execute([$newPrice, $regular, $discPercent, (int)$pl['id']]);
            }
            $toast = ['type' => 'success', 'msg' => ($discPercent > 0) ? "Applied $discPercent% discount across all products!" : "All discounts reset!"];
        } catch (Throwable $e) {
            $toast = ['type' => 'error', 'msg' => $e->getMessage()];
        }
    }

    if ($act === 'reset_all_product_discounts') {
        try {
            $pdo->exec("UPDATE product_plans SET user_price = regular_price, discount_percent = 0.00 WHERE regular_price > 0");
            $toast = ['type' => 'success', 'msg' => "All product discounts reset to 0%!"];
        } catch (Throwable $e) {
            $toast = ['type' => 'error', 'msg' => $e->getMessage()];
        }
    }

    if ($act === 'apply_single_plan_discount') {
        $planId      = (int)($_POST['plan_id'] ?? 0);
        $discPercent = max(0.0, min(99.0, (float)($_POST['discount_percent'] ?? 0)));
        if ($planId > 0) {
            $pl = $pdo->query("SELECT id, regular_price, user_price FROM product_plans WHERE id = $planId")->fetch(PDO::FETCH_ASSOC);
            if ($pl) {
                $regular = (float)$pl['regular_price'] > 0 ? (float)$pl['regular_price'] : (float)$pl['user_price'];
                $newPrice = ($discPercent > 0) ? max(0.01, round($regular * (1 - ($discPercent / 100)), 2)) : $regular;
                $pdo->prepare("UPDATE product_plans SET user_price = ?, regular_price = ?, discount_percent = ? WHERE id = ?")
                    ->execute([$newPrice, $regular, $discPercent, $planId]);
                $toast = ['type' => 'success', 'msg' => "Plan discount updated to $discPercent%!"];
            }
        }
    }

    if ($act === 'apply_bulk_user_discount') {
        $targetRole  = in_array($_POST['target_role'] ?? '', ['customer', 'reseller']) ? $_POST['target_role'] : 'customer';
        $discPercent = max(0.0, min(99.0, (float)($_POST['discount_percent'] ?? 0)));
        try {
            if ($targetRole === 'customer') {
                $stmt = $pdo->prepare("UPDATE users SET discount_percent = ? WHERE LOWER(TRIM(COALESCE(role, 'customer'))) = 'customer' OR role IS NULL OR role = ''");
            } else {
                $stmt = $pdo->prepare("UPDATE users SET discount_percent = ? WHERE LOWER(TRIM(role)) = 'reseller'");
            }
            $stmt->execute([$discPercent]);
            $toast = ['type' => 'success', 'msg' => "Applied $discPercent% discount to all {$targetRole}s!"];
        } catch (Throwable $e) {
            $toast = ['type' => 'error', 'msg' => $e->getMessage()];
        }
    }

    if ($act === 'reset_all_user_discounts') {
        $targetRole = in_array($_POST['target_role'] ?? '', ['customer', 'reseller']) ? $_POST['target_role'] : 'customer';
        try {
            if ($targetRole === 'customer') {
                $pdo->exec("UPDATE users SET discount_percent = 0.00 WHERE LOWER(TRIM(COALESCE(role, 'customer'))) = 'customer' OR role IS NULL OR role = ''");
            } else {
                $pdo->exec("UPDATE users SET discount_percent = 0.00 WHERE LOWER(TRIM(role)) = 'reseller'");
            }
            $toast = ['type' => 'success', 'msg' => "All {$targetRole} discounts turned off!"];
        } catch (Throwable $e) {
            $toast = ['type' => 'error', 'msg' => $e->getMessage()];
        }
    }

    if ($act === 'apply_single_user_discount') {
        $targetUid   = (int)($_POST['target_user_id'] ?? 0);
        $discPercent = max(0.0, min(99.0, (float)($_POST['discount_percent'] ?? 0)));
        if ($targetUid > 0) {
            $pdo->prepare("UPDATE users SET discount_percent = ? WHERE id = ?")->execute([$discPercent, $targetUid]);
            $toast = ['type' => 'success', 'msg' => "User discount updated!"];
        }
    }

    if ($act === 'save_user_custom_pricing_and_access') {
        $userId = (int)$_POST['target_user_id'];
        $role   = in_array($_POST['user_role'] ?? '', ['customer', 'reseller']) ? $_POST['user_role'] : 'customer';
        $canAddFund = isset($_POST['can_add_fund']) ? 1 : 0;

        $pdo->prepare("UPDATE users SET role = ?, can_add_fund = ? WHERE id = ?")->execute([$role, $canAddFund, $userId]);

        $allowedProds = $_POST['allowed_products'] ?? [];
        $pdo->prepare("DELETE FROM user_allowed_products WHERE user_id = ?")->execute([$userId]);
        if (!empty($allowedProds)) {
            $insProd = $pdo->prepare("INSERT INTO user_allowed_products (user_id, product_id) VALUES (?, ?)");
            foreach ($allowedProds as $pId) {
                $insProd->execute([$userId, (int)$pId]);
            }
        }

        $customPrices = $_POST['custom_plan_price'] ?? [];
        $pdo->prepare("DELETE FROM user_custom_prices WHERE user_id = ?")->execute([$userId]);
        $insPrice = $pdo->prepare("INSERT INTO user_custom_prices (user_id, plan_id, custom_price) VALUES (?, ?, ?)");
        $savedCount = 0;
        foreach ($customPrices as $plId => $cPrice) {
            $cleanPrice = trim((string)$cPrice);
            if ($cleanPrice !== '' && is_numeric($cleanPrice)) {
                $fPrice = (float)$cleanPrice;
                if ($fPrice > 0) {
                    $insPrice->execute([$userId, (int)$plId, $fPrice]);
                    $savedCount++;
                }
            }
        }
        $toast = ['type' => 'success', 'msg' => "User settings and $savedCount custom prices saved!"];
    }

    // H. USERS FLOAT
    if ($act === 'modify_balance') {
        $userId = (int)$_POST['user_id'];
        $amount = (float)$_POST['amount'];
        if ($_POST['type'] === 'deduct') $amount = -$amount;
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $userId]);
        $toast = ['type' => 'success', 'msg' => "Wallet balance modified!"];
    }

    if ($act === 'toggle_ban') {
        $userId = (int)$_POST['user_id'];
        $newStatus = $_POST['status'] === 'active' ? 'banned' : 'active';
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $userId]);
        $toast = ['type' => 'success', 'msg' => "User status set to $newStatus!"];
    }

    // I. PAYMENT GATEWAY CHANNELS
    if ($act === 'save_payment_method') {
        $methodId      = (int)($_POST['method_id'] ?? 0);
        $name          = trim($_POST['name'] ?? '');
        $methodKey     = strtolower(preg_replace('/[^a-z0-9_]/i', '', trim($_POST['method_key'] ?? '')));
        $accountNumber = trim($_POST['account_number'] ?? '');
        $accountType   = trim($_POST['account_type'] ?? 'Personal');
        $instructions  = trim($_POST['instructions'] ?? '');
        $sortOrder     = (int)($_POST['sort_order'] ?? 0);
        $isActive      = isset($_POST['is_active']) ? 1 : 0;
        $allowAddFund  = isset($_POST['allow_add_fund']) ? 1 : 0;
        $allowCheckout = isset($_POST['allow_checkout']) ? 1 : 0;

        if (empty($name) || empty($accountNumber)) {
            $toast = ['type' => 'error', 'msg' => 'Method Name and Account Number are required!'];
        } else {
            if (empty($methodKey)) {
                $methodKey = strtolower(preg_replace('/[^a-z0-9_]/i', '', $name));
            }

            $existing = null;
            if ($methodId > 0) {
                $existing = $pdo->query("SELECT * FROM payment_methods WHERE id = $methodId")->fetch(PDO::FETCH_ASSOC);
            }

            $logoUrl   = $existing['logo_url'] ?? null;
            $bannerUrl = $existing['banner_url'] ?? null;
            $qrUrl     = $existing['qr_image_url'] ?? null;

            if (!empty($_FILES['logo_file']['name']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
                    $fileName = 'pm_logo_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $gatewayUploadDir . $fileName)) {
                        $logoUrl = 'uploads/gateways/' . $fileName;
                    }
                }
            }
            if (!empty($_FILES['banner_file']['name']) && $_FILES['banner_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['banner_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $fileName = 'pm_banner_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    if (move_uploaded_file($_FILES['banner_file']['tmp_name'], $gatewayUploadDir . $fileName)) {
                        $bannerUrl = 'uploads/gateways/' . $fileName;
                    }
                }
            }
            if (!empty($_FILES['qr_file']['name']) && $_FILES['qr_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['qr_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
                    $fileName = 'pm_qr_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    if (move_uploaded_file($_FILES['qr_file']['tmp_name'], $gatewayUploadDir . $fileName)) {
                        $qrUrl = 'uploads/gateways/' . $fileName;
                    }
                }
            }

            try {
                if ($methodId > 0) {
                    $stmt = $pdo->prepare("UPDATE payment_methods SET name = ?, method_key = ?, account_number = ?, account_type = ?, instructions = ?, logo_url = ?, banner_url = ?, qr_image_url = ?, is_active = ?, allow_add_fund = ?, allow_checkout = ?, sort_order = ? WHERE id = ?");
                    $stmt->execute([$name, $methodKey, $accountNumber, $accountType, $instructions, $logoUrl, $bannerUrl, $qrUrl, $isActive, $allowAddFund, $allowCheckout, $sortOrder, $methodId]);
                    $toast = ['type' => 'success', 'msg' => "$name updated successfully!"];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO payment_methods (name, method_key, account_number, account_type, instructions, logo_url, banner_url, qr_image_url, is_active, allow_add_fund, allow_checkout, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $methodKey, $accountNumber, $accountType, $instructions, $logoUrl, $bannerUrl, $qrUrl, $isActive, $allowAddFund, $allowCheckout, $sortOrder]);
                    $toast = ['type' => 'success', 'msg' => "Payment method $name created!"];
                }
            } catch (Throwable $e) {
                $toast = ['type' => 'error', 'msg' => 'Database error: ' . $e->getMessage()];
            }
        }
    }

    if ($act === 'delete_payment_method') {
        $methodId = (int)$_POST['method_id'];
        $pdo->prepare("DELETE FROM payment_methods WHERE id = ?")->execute([$methodId]);
        $toast = ['type' => 'success', 'msg' => 'Payment Method deleted!'];
    }

    if ($act === 'toggle_payment_method_status') {
        $methodId = (int)$_POST['method_id'];
        $curr     = (int)$_POST['current_status'];
        $new      = ($curr === 1) ? 0 : 1;
        $pdo->prepare("UPDATE payment_methods SET is_active = ? WHERE id = ?")->execute([$new, $methodId]);
        $toast = ['type' => 'success', 'msg' => "Gateway status set to " . ($new === 1 ? 'ONLINE' : 'OFFLINE')];
    }

    // J. SKYPAY BD RULES
    if ($act === 'save_skypay_rules') {
        $baseUrl    = trim($_POST['skypay_base_url'] ?? SKYPAY_DEFAULT_BASE_URL);
        $brandKey   = trim($_POST['skypay_brand_key'] ?? '');
        $autoVerify = isset($_POST['skypay_auto_verify']) ? '1' : '0';
        $autoPayOn  = isset($_POST['auto_payment_on']) ? '1' : '0';
        if ($baseUrl === '' || stripos($baseUrl, 'skypaybd') === false) $baseUrl = SKYPAY_DEFAULT_BASE_URL;
        $baseUrl = preg_replace('#/api/.*$#i', '', rtrim($baseUrl, '/'));

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['skypay_base_url', $baseUrl, $baseUrl]);
        $stmt->execute(['skypay_brand_key', $brandKey, $brandKey]);
        $stmt->execute(['skypay_auto_verify', $autoVerify, $autoVerify]);
        $stmt->execute(['auto_payment_on', $autoPayOn, $autoPayOn]);
        // remove old SNZPay settings
        try { $pdo->exec("DELETE FROM system_settings WHERE setting_key IN ('snzpay_api_url','snzpay_license_key','snzpay_auto_verify')"); } catch (Throwable $e) {}
        unset($settings['snzpay_api_url'], $settings['snzpay_license_key'], $settings['snzpay_auto_verify']);

        $settings['skypay_base_url']    = $baseUrl;
        $settings['skypay_brand_key']   = $brandKey;
        $settings['skypay_auto_verify'] = $autoVerify;
        $settings['auto_payment_on']    = $autoPayOn;

        $toast = ['type' => 'success', 'msg' => 'SkyPay BD configuration saved!'];
    }

    if ($act === 'test_skypay_connection') {
        $cfg = skypayConfig($settings);
        $t   = skypayHttp($cfg['base_url'] . '/api/v2/payment/create', $cfg['brand_key'], ['cus_name' => 'Admin Test', 'amount' => 10, 'meta_data' => (object)['test' => 1]]);
        if ($t['err'] !== '') {
            $toast = ['type' => 'error', 'msg' => 'SkyPay not reachable: ' . $t['err']];
        } elseif (is_array($t['json']) && !empty($t['json']['status']) && !empty($t['json']['id'])) {
            $toast = ['type' => 'success', 'msg' => 'SkyPay Connected! BRAND-KEY is valid (session ' . $t['json']['id'] . ').'];
        } else {
            $toast = ['type' => 'error', 'msg' => 'SkyPay Response (HTTP ' . $t['http'] . '): ' . (is_array($t['json']) ? ($t['json']['message'] ?? 'Unknown error') : 'Invalid response')];
        }
    }

    // K. DEPOSITS & TRANSACTIONS (FIXED: ORDERS WILL NEVER CREDIT WALLET)
    if ($act === 'manage_trx') {
        $trxId  = (int)$_POST['trx_db_id'];
        $status = $_POST['status'];
        $trx    = $pdo->query("SELECT * FROM transactions WHERE id = $trxId")->fetch();
        if ($trx && $trx['status'] === 'pending') {
            $pdo->prepare("UPDATE transactions SET status = ? WHERE id = ?")->execute([$status, $trxId]);
            if ($status === 'approved') {
                // চেক: এটি কি ডাইরেক্ট অর্ডার নাকি ওয়ালেট ডিপোজিট?
                $isOrder = (stripos($trx['method'], 'Order') !== false || stripos($trx['method'], 'Purchase') !== false);
                
                // শুধুমাত্র ডিপোজিট হলেই ওয়ালেটে টাকা ঢুকবে, অর্ডারে ১ পয়সাও ব্যালেন্স বাড়বে না!
                if (!$isOrder) {
                    $bonusFloat = 0.00;
                    if (!empty($trx['bonus_amount']) && (float)$trx['bonus_amount'] > 0) {
                        $bonusFloat = round((float)$trx['bonus_amount'] / $effectiveBdtRate, 2);
                    }
                    $totalCredit = (float)$trx['amount'] + $bonusFloat;
                    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$totalCredit, $trx['user_id']]);
                }
            }
            $toast = ['type' => 'success', 'msg' => "Transaction marked $status!"];
        }
    }

    // L. GATEWAY RULES & EXCHANGES
    if ($act === 'update_gateway_rules') {
        $keys = [
            'usdt_rate', 'binance_market_rate', 'binance_id', 'payment_banner_url',
            'deposit_system_status', 'min_deposit_limit', 'max_deposit_limit',
            'telegram_tutorial_link'
        ];

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($keys as $k) {
            $val = trim($_POST[$k] ?? '0');
            $stmt->execute([$k, $val, $val]);
            $settings[$k] = $val;
        }

        if (!empty($_FILES['payment_banner_file']['name']) && $_FILES['payment_banner_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['payment_banner_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $fileName = 'payment_banner_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['payment_banner_file']['tmp_name'], $bannerUploadDir . $fileName)) {
                    $bannerPath = 'uploads/banners/' . $fileName;
                    $stmt->execute(['payment_banner_url', $bannerPath, $bannerPath]);
                    $settings['payment_banner_url'] = $bannerPath;
                }
            }
        }
        $effectiveBdtRate = max(1.0, (float)($settings['usdt_rate'] ?? 125.0));
        $toast = ['type' => 'success', 'msg' => 'Gateway limits & exchange rates saved!'];
    }

    // M. DEPOSIT REWARDS & BONUSES
    if ($act === 'save_deposit_bonus') {
        $bonusId     = (int)($_POST['bonus_id'] ?? 0);
        $minDeposit  = max(1.0, (float)($_POST['min_deposit'] ?? 500));
        $bonusAmount = max(0.01, (float)($_POST['bonus_amount'] ?? 50));
        $bonusType   = in_array($_POST['bonus_type'] ?? '', ['fixed', 'percentage']) ? $_POST['bonus_type'] : 'fixed';
        $title       = trim($_POST['title'] ?? '');
        $claimLimit  = max(0, (int)($_POST['claim_limit'] ?? 0));
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if (empty($title)) {
            $title = number_format($minDeposit) . " BDT Deposit = " . number_format($bonusAmount) . ($bonusType === 'percentage' ? '%' : ' BDT') . " Bonus";
        }

        try {
            if ($bonusId > 0) {
                $stmt = $pdo->prepare("UPDATE deposit_bonuses SET min_deposit = ?, bonus_amount = ?, bonus_type = ?, title = ?, claim_limit = ?, sort_order = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$minDeposit, $bonusAmount, $bonusType, $title, $claimLimit, $sortOrder, $isActive, $bonusId]);
                $toast = ['type' => 'success', 'msg' => 'Deposit Reward tier updated!'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO deposit_bonuses (min_deposit, bonus_amount, bonus_type, title, claim_limit, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$minDeposit, $bonusAmount, $bonusType, $title, $claimLimit, $sortOrder, $isActive]);
                $toast = ['type' => 'success', 'msg' => 'Deposit Reward tier added!'];
            }
        } catch (Throwable $e) {
            $toast = ['type' => 'error', 'msg' => 'Error: ' . $e->getMessage()];
        }
    }

    if ($act === 'delete_deposit_bonus') {
        $bonusId = (int)$_POST['bonus_id'];
        $pdo->prepare("DELETE FROM deposit_bonuses WHERE id = ?")->execute([$bonusId]);
        $toast = ['type' => 'success', 'msg' => 'Deposit Reward tier deleted!'];
    }

    // N. SPIN WHEEL ENGINE
    if ($act === 'update_spin_wheel_settings') {
        $spinEnabled = isset($_POST['spin_wheel_enabled']) ? '1' : '0';
        $spinCost    = max(0.00, (float)($_POST['spin_cost'] ?? 0));
        $dailyLimit  = max(1, (int)($_POST['spin_daily_limit'] ?? 10));
        $expiryHours = max(1, (int)($_POST['spin_offer_duration_hours'] ?? 24));

        // NEW: Earn-Spin rules (spend X = 1 spin, random prize min..max, referral spins)
        $spendPerSpin = max(1, (float)($_POST['spin_spend_per_spin'] ?? 500));
        $prizeMin     = max(1, (int)($_POST['spin_prize_min'] ?? 1));
        $prizeMax     = max(1, (int)($_POST['spin_prize_max'] ?? 50));
        if ($prizeMax < $prizeMin) { $tmp = $prizeMin; $prizeMin = $prizeMax; $prizeMax = $tmp; }
        $referEnabled = isset($_POST['spin_refer_enabled']) ? '1' : '0';
        $referPerSpin = max(1, (int)($_POST['spin_refer_per_spin'] ?? 1));

        $saveArray = [
            'spin_wheel_enabled'        => $spinEnabled,
            'spin_cost'                 => (string)$spinCost,
            'spin_daily_limit'          => (string)$dailyLimit,
            'spin_offer_duration_hours' => (string)$expiryHours,
            'spin_spend_per_spin'       => (string)$spendPerSpin,
            'spin_prize_min'            => (string)$prizeMin,
            'spin_prize_max'            => (string)$prizeMax,
            'spin_refer_enabled'        => $referEnabled,
            'spin_refer_per_spin'       => (string)$referPerSpin
        ];

        $stmtSet = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($saveArray as $k => $v) {
            $stmtSet->execute([$k, $v, $v]);
            $settings[$k] = $v;
        }
        $toast = ['type' => 'success', 'msg' => 'Spin rules saved!'];
    }

    if ($act === 'save_spin_segment') {
        $segId       = (int)($_POST['segment_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $spendTarget = max(1, (float)($_POST['spend_target'] ?? 500));
        $bonusAmount = max(1, (float)($_POST['bonus_amount'] ?? 50));
        $probability = max(1, (int)($_POST['probability'] ?? 20));
        $color       = trim($_POST['color'] ?? '#16a34a');
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if (empty($title)) {
            $title = number_format($spendTarget) . " TK → " . number_format($bonusAmount) . " TK Bonus";
        }

        try {
            if ($segId > 0) {
                $stmt = $pdo->prepare("UPDATE spin_segments SET title = ?, spend_target = ?, bonus_amount = ?, probability = ?, color = ?, sort_order = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$title, $spendTarget, $bonusAmount, $probability, $color, $sortOrder, $isActive, $segId]);
                $toast = ['type' => 'success', 'msg' => 'Segment updated!'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO spin_segments (title, spend_target, bonus_amount, probability, color, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $spendTarget, $bonusAmount, $probability, $color, $sortOrder, $isActive]);
                $toast = ['type' => 'success', 'msg' => 'Segment added!'];
            }
        } catch (Throwable $e) {
            $toast = ['type' => 'error', 'msg' => 'Error: ' . $e->getMessage()];
        }
    }

    if ($act === 'delete_spin_segment') {
        $segId = (int)$_POST['segment_id'];
        $pdo->prepare("DELETE FROM spin_segments WHERE id = ?")->execute([$segId]);
        $toast = ['type' => 'success', 'msg' => 'Segment deleted!'];
    }

    // O. PUSH NOTIFICATIONS
    if ($act === 'send_push_notification') {
        $target = $_POST['target_type'] ?? 'all';
        $title  = trim($_POST['push_title'] ?? '');
        $msg    = trim($_POST['push_message'] ?? '');

        if (!empty($title) && !empty($msg)) {
            $pdo->prepare("INSERT INTO system_notifications (target_type, title, message) VALUES (?, ?, ?)")
                ->execute([$target, $title, $msg]);
            $toast = ['type' => 'success', 'msg' => "Notification broadcasted!"];
        }
    }

    if ($act === 'delete_push_notification') {
        $notifId = (int)($_POST['notif_id'] ?? 0);
        $pdo->prepare("DELETE FROM system_notifications WHERE id = ?")->execute([$notifId]);
        $toast = ['type' => 'success', 'msg' => "Notification deleted!"];
    }

    // P. SITE BRANDING & SUPPORT CHANNELS (UPDATED WITH POPUP, ANNOUNCEMENT & RULES)
    if ($act === 'update_branding_settings') {
        $newSiteName     = trim($_POST['site_name'] ?? '') ?: 'FF PANEL SELL';
        $appDownloadUrl  = trim($_POST['app_download_url'] ?? '');
        $supportWa       = trim($_POST['support_whatsapp'] ?? '');
        $supportTg       = trim($_POST['support_telegram'] ?? '');
        $supportFb       = trim($_POST['support_facebook'] ?? '');
        $supportCh       = trim($_POST['support_channel'] ?? '');
        $siteLogoPath    = $settings['site_logo'] ?? '';
        $loadingLogoPath = $settings['loading_logo'] ?? '';
        $gClientId       = trim($_POST['google_client_id'] ?? '');
        $gClientSecret   = trim($_POST['google_client_secret'] ?? '');
        $gRedirectUri    = trim($_POST['google_redirect_uri'] ?? '');

        // NEW: Announcement Bar Ticker, Welcome Popup & Product Maintenance Rules
        $topTickerNotice = trim($_POST['top_ticker_notice'] ?? 'অর্ডার করার ০০.১ সেকেন্ডেই অটো ডেলিভারি পেয়ে যাবেন!');
        $prodMaintRules  = trim($_POST['prod_maintenance_default_rules'] ?? '');
        $popupEnabled    = isset($_POST['update_channel_popup_enabled']) ? '1' : '0';
        $popupTitle      = trim($_POST['update_channel_title'] ?? 'স্বাগতম!');
        $popupMsg        = trim($_POST['update_channel_msg'] ?? '');
        $popupLink       = trim($_POST['update_channel_link'] ?? '');
        $maintMode       = isset($_POST['site_maintenance_mode']) ? '1' : '0';
        $maintMsg        = trim($_POST['site_maintenance_msg'] ?? 'System upgrade in progress.');
        $copyrightText   = trim($_POST['footer_copyright_text'] ?? '');

        if (!empty($_POST['delete_site_logo'])) $siteLogoPath = '';
        if (!empty($_POST['delete_loading_logo'])) $loadingLogoPath = '';

        if (!empty($_FILES['site_logo_file']['name']) && $_FILES['site_logo_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['site_logo_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'])) {
                $fileName = 'site_logo_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['site_logo_file']['tmp_name'], $siteUploadDir . $fileName)) {
                    $siteLogoPath = 'uploads/site/' . $fileName;
                }
            }
        }
        if (!empty($_FILES['loading_logo_file']['name']) && $_FILES['loading_logo_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['loading_logo_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'])) {
                $fileName = 'loading_logo_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['loading_logo_file']['tmp_name'], $siteUploadDir . $fileName)) {
                    $loadingLogoPath = 'uploads/site/' . $fileName;
                }
            }
        }

        $saveArray = [
            'site_name'                      => $newSiteName,
            'app_download_url'               => $appDownloadUrl,
            'support_whatsapp'               => $supportWa,
            'support_telegram'               => $supportTg,
            'support_facebook'               => $supportFb,
            'support_channel'                => $supportCh,
            'site_logo'                      => $siteLogoPath,
            'loading_logo'                   => $loadingLogoPath,
            'site_maintenance_mode'          => $maintMode,
            'site_maintenance_msg'           => $maintMsg,
            'top_ticker_notice'              => $topTickerNotice,
            'prod_maintenance_default_rules' => $prodMaintRules,
            'update_channel_popup_enabled'   => $popupEnabled,
            'update_channel_title'           => $popupTitle,
            'update_channel_msg'             => $popupMsg,
            'update_channel_link'            => $popupLink,
            'footer_copyright_text'          => $copyrightText,
            'google_client_id'               => $gClientId,
            'google_client_secret'           => $gClientSecret,
            'google_redirect_uri'            => $gRedirectUri
        ];

        $stmtSet = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($saveArray as $k => $v) {
            $stmtSet->execute([$k, $v, $v]);
            $settings[$k] = $v;
        }
        $toast = ['type' => 'success', 'msg' => 'Settings saved successfully!'];
    }

    if ($act === 'add_banner') {
        $targetLink = trim($_POST['target_link'] ?? '');
        $title      = trim($_POST['title'] ?? 'Promo Banner');
        $sort       = (int)($_POST['sort_order'] ?? 0);
        $bannerPath = null;

        if (!empty($_FILES['banner_photo']['name']) && $_FILES['banner_photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['banner_photo']['name'], PATHINFO_EXTENSION));
            $fileName = 'banner_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['banner_photo']['tmp_name'], $bannerUploadDir . $fileName)) {
                $bannerPath = 'uploads/banners/' . $fileName;
            }
        }
        if (!empty($bannerPath)) {
            $pdo->prepare("INSERT INTO banners (image_url, target_link, title, sort_order, is_active) VALUES (?, ?, ?, ?, 1)")
                ->execute([$bannerPath, $targetLink ?: null, $title, $sort]);
            $toast = ['type' => 'success', 'msg' => 'Banner added!'];
        }
    }

    if ($act === 'delete_banner') {
        $pdo->prepare("DELETE FROM banners WHERE id = ?")->execute([(int)$_POST['banner_id']]);
        $toast = ['type' => 'success', 'msg' => 'Banner deleted!'];
    }
}

// ====================================================================
// 5. CORE METRICS & ANALYTICS QUERIES
// ====================================================================
$totalUsers      = (int)($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0);
$totalCustomers  = (int)($pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' OR role IS NULL OR role = ''")->fetchColumn() ?: 0);
$totalResellers  = (int)($pdo->query("SELECT COUNT(*) FROM users WHERE role = 'reseller'")->fetchColumn() ?: 0);
$totalFunds      = (float)($pdo->query("SELECT SUM(balance) FROM users")->fetchColumn() ?: 0.00);

$totalLicenses   = (int)($pdo->query("SELECT COUNT(*) FROM licenses")->fetchColumn() ?: 0);
$todaySales      = (float)($pdo->query("SELECT SUM(price) FROM licenses WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 0.00);
$monthSales      = (float)($pdo->query("SELECT SUM(price) FROM licenses WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn() ?: 0.00);
$allTimeSales    = (float)($pdo->query("SELECT SUM(price) FROM licenses")->fetchColumn() ?: 0.00);

$totalStockKeys  = (int)($pdo->query("SELECT COUNT(*) FROM stock_keys WHERE status = 'available'")->fetchColumn() ?: 0);
$pendingTrxCount = (int)($pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn() ?: 0);

$usersList = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM licenses WHERE user_id = u.id) AS total_sold_keys FROM users u ORDER BY u.id DESC")->fetchAll(PDO::FETCH_ASSOC);

$productsData = [];
$allProductPlansFlat = [];
try {
    $productsQuery = $pdo->query("SELECT * FROM products ORDER BY sort_order ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($productsQuery as $pr) {
        $plansQuery = $pdo->prepare("SELECT p.*, (SELECT COUNT(*) FROM stock_keys s WHERE s.plan_id = p.id AND s.status = 'available') AS stock_count FROM product_plans p WHERE p.product_id = ? ORDER BY p.user_price ASC, p.id ASC");
        $plansQuery->execute([$pr['id']]);
        $pr['plans'] = $plansQuery->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pr['plans'] as $pl) {
            $pl['product_name'] = $pr['name'];
            $allProductPlansFlat[] = $pl;
        }
        $productsData[] = $pr;
    }
} catch (Throwable $e) {}

$kmImportedPlans = $pdo->query("
    SELECT pl.*, p.name as product_name 
    FROM product_plans pl 
    JOIN products p ON pl.product_id = p.id 
    WHERE pl.api_plan_id > 0 
    ORDER BY pl.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
$kmImportedCount = count($kmImportedPlans);

// Fetch Coupons / Promo Codes List
$promoCodesList = [];
try {
    $promoCodesList = $pdo->query("
        SELECT pc.*, p.name as specific_product_name 
        FROM promo_codes pc 
        LEFT JOIN products p ON pc.product_id = p.id 
        ORDER BY pc.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// Fetch Referrals Log
$referralsList = [];
$totalReferralPaid = 0.00;
try {
    $referralsList = $pdo->query("
        SELECT r.*, u1.username as referrer_username, u2.username as referred_username 
        FROM referrals r 
        JOIN users u1 ON r.referrer_id = u1.id 
        JOIN users u2 ON r.referred_user_id = u2.id 
        ORDER BY r.id DESC LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);
    $totalReferralPaid = (float)($pdo->query("SELECT SUM(reward_amount) FROM referrals WHERE status = 'credited'")->fetchColumn() ?: 0.00);
} catch (Throwable $e) {}

// Referral coin rules
$refCoinRules = [];
$totalCoinsGiven = 0;
try {
    $refCoinRules    = $pdo->query("SELECT * FROM referral_coin_rules ORDER BY rule_type ASC, coins ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $totalCoinsGiven = (int)($pdo->query("SELECT COALESCE(SUM(reward_coins),0) FROM referrals")->fetchColumn() ?: 0);
} catch (Throwable $e) {}

// Payment Methods & Rewards
$paymentMethodsList = $pdo->query("SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$spinSegments       = $pdo->query("SELECT * FROM spin_segments ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Earn-Spin stats (tables are created by index.php on first use)
$spinRecentWinners = [];
$spinStats = ['given' => 0, 'used' => 0, 'paid_bdt' => 0];
try {
    $spinRecentWinners = $pdo->query("SELECT l.prize_bdt, l.created_at, u.username FROM user_spin_log l JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $rowSt = $pdo->query("SELECT COALESCE(SUM(total_earned),0) AS g, COALESCE(SUM(total_used),0) AS u FROM user_spin_wallet")->fetch(PDO::FETCH_ASSOC);
    $spinStats['given'] = (int)($rowSt['g'] ?? 0);
    $spinStats['used']  = (int)($rowSt['u'] ?? 0);
    $spinStats['paid_bdt'] = (float)($pdo->query("SELECT COALESCE(SUM(prize_bdt),0) FROM user_spin_log")->fetchColumn() ?: 0);
} catch (Throwable $e) {}
$depositBonusesList = $pdo->query("SELECT * FROM deposit_bonuses ORDER BY sort_order ASC, min_deposit ASC")->fetchAll(PDO::FETCH_ASSOC);

$transactions       = $pdo->query("SELECT t.*, u.username, u.email FROM transactions t JOIN users u ON t.user_id = u.id ORDER BY t.id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
$sales              = $pdo->query("SELECT l.*, COALESCE(u.username, 'Direct / Guest') as username, u.email FROM licenses l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
$bannersList        = $pdo->query("SELECT * FROM banners ORDER BY sort_order ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$sentNotifications  = $pdo->query("SELECT * FROM system_notifications ORDER BY id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

$userCustomPricesMap = [];
$cPriceRows = $pdo->query("SELECT user_id, plan_id, custom_price FROM user_custom_prices")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cPriceRows as $cp) {
    $userCustomPricesMap[$cp['user_id']][$cp['plan_id']] = (float)$cp['custom_price'];
}

$userAllowedProdsMap = [];
$aProdRows = $pdo->query("SELECT user_id, product_id FROM user_allowed_products")->fetchAll(PDO::FETCH_ASSOC);
foreach ($aProdRows as $ap) {
    $userAllowedProdsMap[$ap['user_id']][] = (int)$ap['product_id'];
}

$providerBalance = km_get_cached_provider_balance($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Console — <?= htmlspecialchars($storeTitleName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --site-theme: <?= htmlspecialchars($primaryThemeColor) ?>;
            --site-theme-dark: #127c36;
        }
        body { 
            background-color: #f8fafc; 
            color: #0f172a; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
        }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        
        /* Smooth Premium Animations */
        @keyframes slideUpFade { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulseGlow { 0%, 100% { box-shadow: 0 0 15px rgba(21, 128, 61, 0.2); } 50% { box-shadow: 0 0 25px rgba(21, 128, 61, 0.4); } }

        .stat-card { 
            background: #ffffff; 
            border: 1px solid #e2e8f0; 
            border-radius: 20px; 
            padding: 1.25rem 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: slideUpFade 0.5s ease-out forwards;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.06);
            border-color: var(--site-theme);
        }
        
        .box-panel { 
            background: #ffffff; 
            border: 1px solid #e2e8f0; 
            border-radius: 22px; 
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            animation: slideUpFade 0.6s ease-out forwards;
        }
        
        .btn-theme { 
            background: var(--site-theme); 
            color: #ffffff; 
            font-weight: 800; 
            box-shadow: 0 4px 14px rgba(21, 128, 61, 0.25);
            transition: all 0.2s ease;
        }
        .btn-theme:hover { 
            opacity: 0.95; 
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(21, 128, 61, 0.35);
        }
        
        .btn-slate { background: #0f172a; color: #ffffff; font-weight: 800; }
        .btn-rose  { background: #ef4444; color: #ffffff; font-weight: 800; }

        .table-roomy {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-roomy th {
            padding: 1rem 1.25rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 1.5px solid #e2e8f0;
            white-space: nowrap;
        }
        .table-roomy td {
            padding: 1.15rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            white-space: nowrap;
            vertical-align: middle;
        }
        .table-roomy tr { transition: background-color 0.2s ease; }
        .table-roomy tr:hover td {
            background-color: #f8fafc;
        }

        .drawer-nav-btn {
            width: 100%; 
            display: flex; 
            align-items: center; 
            gap: 0.85rem; 
            padding: 0.8rem 1.1rem;
            border-radius: 16px; 
            color: #475569; 
            font-size: 0.82rem; 
            font-weight: 800; 
            transition: all 0.3s ease; 
            text-align: left; 
            border: 1px solid #f1f5f9;
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .drawer-nav-btn:hover { 
            background: #f8fafc; 
            color: var(--site-theme); 
            border-color: #e2e8f0;
            transform: translateX(3px);
        }
        .drawer-nav-btn.active {
            background: #f0fdf4;
            color: var(--site-theme); 
            border: 1.5px solid var(--site-theme);
            box-shadow: 0 4px 12px rgba(21, 128, 61, 0.15);
        }
        .drawer-nav-btn.active i { color: var(--site-theme) !important; }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: #f8fafc; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--site-theme); }

        /* Subtle 3-Section Dividers */
        .settings-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            position: relative;
        }
        .settings-section::before {
            content: '';
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 4px;
            background: var(--site-theme);
            border-top-left-radius: 20px;
            border-bottom-left-radius: 20px;
        }
    </style>
</head>
<body class="min-h-screen pb-16 antialiased">

    <!-- Toast Notification -->
    <div id="toastNotification" class="fixed top-5 left-1/2 transform -translate-x-1/2 z-[999999] transition-all duration-300 pointer-events-none opacity-0 translate-y-[-20px] w-[90%] max-w-md">
        <div id="toastInner" class="bg-white border-2 border-emerald-500 shadow-2xl rounded-2xl p-4 flex items-center space-x-3 text-xs font-bold text-slate-800 backdrop-blur-md">
            <i id="toastIcon" class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
            <span id="toastMsg" class="font-mono truncate">Notification</span>
        </div>
    </div>

    <!-- Drawer Overlay -->
    <div id="drawerOverlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden" onclick="toggleAdminDrawer()"></div>

    <!-- Sidebar Drawer Navigation -->
    <aside id="adminDrawer" class="fixed top-0 right-0 w-[310px] h-full bg-[#f8fafc] border-l border-slate-200 z-50 transform translate-x-full transition-transform duration-300 flex flex-col justify-between p-5 overflow-y-auto shadow-2xl">
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                <div class="flex items-center space-x-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white flex items-center justify-center font-black shadow-md border border-emerald-500/50">
                        <i class="fa-solid fa-shield-halved text-base"></i>
                    </div>
                    <div>
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider"><?= htmlspecialchars($storeTitleName) ?></h3>
                        <span class="text-[9px] text-emerald-600 font-bold uppercase tracking-widest font-mono">ADMIN CONSOLE</span>
                    </div>
                </div>
                <button type="button" onclick="toggleAdminDrawer()" class="w-8 h-8 rounded-full bg-white border border-slate-200 text-slate-500 hover:text-slate-900 flex items-center justify-center transition active:scale-95 shadow-sm">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- Navigation Buttons -->
            <nav class="space-y-1.5 font-sans">
                <button id="btn-nav-tab-overview" onclick="switchAdminTab('tab-overview', 'Dashboard Overview', this)" class="drawer-nav-btn active">
                    <i class="fa-solid fa-chart-pie w-4 text-emerald-600"></i><span>Dashboard</span>
                </button>
                
                <button id="btn-nav-tab-products" onclick="switchAdminTab('tab-products', 'Products Catalog', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-boxes-stacked w-4 text-emerald-600"></i><span>Products Catalog</span>
                </button>

                <button id="btn-nav-tab-coupons" onclick="switchAdminTab('tab-coupons', 'Coupons & Promo Codes', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-ticket w-4 text-emerald-600"></i><span>Coupons / Promo</span>
                </button>

                <button id="btn-nav-tab-referral" onclick="switchAdminTab('tab-referral', 'Referral Commission Rules', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-gift w-4 text-emerald-600"></i><span>Refer &amp; Earn</span>
                </button>

                <button id="btn-nav-tab-discounts" onclick="switchAdminTab('tab-discounts', 'Discount Management Hub', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-tags w-4 text-emerald-600"></i><span>Discount Hub</span>
                </button>

                <button id="btn-nav-tab-gateways" onclick="switchAdminTab('tab-gateways', 'Payment Gateway Rules & SkyPay', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-building-columns w-4 text-emerald-600"></i><span>Payment Gateways</span>
                </button>

                <button id="btn-nav-tab-connectkm" onclick="switchAdminTab('tab-connectkm', 'Key Manager Integration', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-bolt-lightning w-4 text-emerald-600"></i><span>Connect Key Manager</span>
                </button>

                <button id="btn-nav-tab-spinwheel" onclick="switchAdminTab('tab-spinwheel', 'Spin Control & Rewards', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-dharmachakra w-4 text-emerald-600"></i><span>Spin Control</span>
                </button>

                <button id="btn-nav-tab-keys" onclick="switchAdminTab('tab-keys', 'License Sales Feed', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-key w-4 text-emerald-600"></i><span>Keys &amp; Orders</span>
                </button>

                <button id="btn-nav-tab-revenue" onclick="switchAdminTab('tab-revenue', 'Revenue Report', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-file-invoice-dollar w-4 text-emerald-600"></i><span>Revenue Report</span>
                </button>

                <button id="btn-nav-tab-deposits" onclick="switchAdminTab('tab-deposits', 'Deposits & Rewards', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-wallet w-4 text-emerald-600"></i><span>Deposits (<?= $pendingTrxCount ?>)</span>
                </button>

                <button id="btn-nav-tab-customers" onclick="switchAdminTab('tab-customers', 'Customers Management', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-user-group w-4 text-emerald-600"></i><span>Customers</span>
                </button>

                <button id="btn-nav-tab-resellers" onclick="switchAdminTab('tab-resellers', 'Resellers Hub', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-users-gear w-4 text-emerald-600"></i><span>Resellers</span>
                </button>

                <button id="btn-nav-tab-settings" onclick="switchAdminTab('tab-settings', 'Site Settings & Branding', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-sliders w-4 text-emerald-600"></i><span>Site Settings</span>
                </button>

                <button id="btn-nav-tab-push" onclick="switchAdminTab('tab-push', 'Push Notifications', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-paper-plane w-4 text-emerald-600"></i><span>Push Notification</span>
                </button>

                <button id="btn-nav-tab-themecolor" onclick="switchAdminTab('tab-themecolor', 'Store Theme Color', this)" class="drawer-nav-btn">
                    <i class="fa-solid fa-palette w-4 text-emerald-600"></i><span>Theme Color</span>
                </button>
            </nav>
        </div>

        <!-- Terminate Session -->
        <div class="pt-3 border-t border-slate-200">
            <a href="admin.php?logout=1" class="w-full flex items-center justify-center space-x-2 py-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 hover:bg-rose-100 text-xs font-bold transition shadow-sm active:scale-95">
                <i class="fa-solid fa-power-off"></i><span>TERMINATE SESSION</span>
            </a>
        </div>
    </aside>

    <!-- Top Sticky Header Bar -->
    <header class="w-full bg-white/95 backdrop-blur-md border-b border-slate-200 px-4 py-3.5 sticky top-0 z-30 shadow-sm">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white flex items-center justify-center text-lg font-black shadow-md border border-emerald-600/50">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <h1 class="text-sm font-black text-slate-900 uppercase tracking-wider"><?= htmlspecialchars($storeTitleName) ?></h1>
                        <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest bg-emerald-50 text-emerald-700 border border-emerald-200 font-mono">
                            OFFICIAL CONSOLE
                        </span>
                    </div>
                    <span id="headerSectionTitle" class="text-[10px] text-emerald-700 font-bold block mt-0.5 font-mono">Overview</span>
                </div>
            </div>

            <div class="flex items-center space-x-2.5">
                <div class="hidden sm:flex items-center px-3.5 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono cursor-pointer hover:border-emerald-500 transition" onclick="switchAdminTab('tab-connectkm', 'Key Manager Integration', document.getElementById('btn-nav-tab-connectkm'))">
                    <span class="text-slate-500 mr-1.5 font-bold">KM WALLET:</span>
                    <strong class="text-emerald-700 font-black"><?= $providerBalance ?></strong>
                </div>
                <button type="button" onclick="toggleAdminDrawer()" class="w-10 h-10 flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow-md active:scale-95 transition">
                    <i class="fa-solid fa-bars text-base"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Viewport Container -->
    <main class="max-w-6xl mx-auto px-4 mt-6 space-y-6">

        <!-- =============================================================== -->
        <!-- 1. DASHBOARD OVERVIEW TAB                                       -->
        <!-- =============================================================== -->
        <section id="tab-overview" class="tab-section space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 font-mono">
                <div class="stat-card flex flex-col justify-between h-32">
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">TODAY REVENUE</span>
                    <h2 class="text-3xl font-black text-emerald-600 mt-1">$<?= number_format($todaySales, 2) ?></h2>
                    <span class="text-[10px] text-slate-500 font-sans">Live Completed Orders</span>
                </div>
                <div class="stat-card flex flex-col justify-between h-32">
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">TOTAL CUSTOMERS</span>
                    <h2 class="text-3xl font-black text-slate-800 mt-1"><?= $totalCustomers ?></h2>
                    <span class="text-[10px] text-slate-500 font-sans">Resellers: <?= $totalResellers ?></span>
                </div>
                <div class="stat-card flex flex-col justify-between h-32">
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">DELIVERED LICENSES</span>
                    <h2 class="text-3xl font-black text-slate-800 mt-1"><?= $totalLicenses ?></h2>
                    <span class="text-[10px] text-slate-500 font-sans">Available In Stock: <?= $totalStockKeys ?></span>
                </div>
                <div class="stat-card flex flex-col justify-between h-32">
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">PENDING DEPOSITS</span>
                    <h2 class="text-3xl font-black text-amber-500 mt-1"><?= $pendingTrxCount ?></h2>
                    <span class="text-[10px] text-slate-500 font-sans">Wallet Float: $<?= number_format($totalFunds, 2) ?></span>
                </div>
            </div>

            <!-- Quick Action Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="box-panel flex items-center justify-between gap-4 border-emerald-200 bg-emerald-50/50">
                    <div class="flex items-center space-x-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-xl shadow-md">
                            <i class="fa-solid fa-ticket"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-slate-900 uppercase tracking-wider font-mono">Coupons &amp; Promo Codes</h4>
                            <p class="text-xs text-slate-500 mt-0.5">Create discounts for all or specific products.</p>
                        </div>
                    </div>
                    <button type="button" onclick="switchAdminTab('tab-coupons', 'Coupons & Promo Codes', document.getElementById('btn-nav-tab-coupons'))" class="px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold uppercase tracking-wider shadow active:scale-95 transition">
                        Manage
                    </button>
                </div>

                <div class="box-panel flex items-center justify-between gap-4 border-slate-200 bg-white">
                    <div class="flex items-center space-x-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500 text-slate-950 flex items-center justify-center text-xl shadow-md">
                            <i class="fa-solid fa-gift"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-slate-900 uppercase tracking-wider font-mono">Referral Rewards</h4>
                            <p class="text-xs text-slate-500 mt-0.5">Set per-referral commission &amp; review logs.</p>
                        </div>
                    </div>
                    <button type="button" onclick="switchAdminTab('tab-referral', 'Referral Commission Rules', document.getElementById('btn-nav-tab-referral'))" class="px-4 py-2.5 rounded-xl bg-slate-900 text-white text-xs font-bold uppercase tracking-wider shadow active:scale-95 transition">
                        Configure
                    </button>
                </div>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 2. COUPONS & PROMO CODES MANAGEMENT TAB                         -->
        <!-- =============================================================== -->
        <section id="tab-coupons" class="tab-section hidden space-y-5 font-mono">
            <div class="box-panel p-5 space-y-5 border-emerald-200">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-ticket text-emerald-600"></i> COUPON &amp; PROMO CODE ENGINE
                        </h3>
                        <span class="text-[11px] text-slate-500 font-sans mt-0.5 block">
                            Create discount codes applicable to all products or a single specific product with usage limits and expiration date.
                        </span>
                    </div>
                </div>

                <!-- Coupon Creator Form -->
                <form method="POST" class="bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200 space-y-4 text-xs shadow-inner">
                    <input type="hidden" name="admin_action" value="save_promo_code">
                    <input type="hidden" name="active_tab" value="tab-coupons">
                    <input type="hidden" id="editPromoId" name="promo_id" value="0">

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Promo Code</label>
                            <input type="text" id="promoCodeInput" name="code" required placeholder="e.g. DISCOUNT20" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-900 font-black uppercase outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 transition">
                        </div>
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Discount Percentage (%)</label>
                            <input type="number" step="0.5" min="1" max="99" id="promoDiscountInput" name="discount_percent" value="10" required class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-emerald-700 font-black text-sm outline-none focus:border-emerald-600 transition">
                        </div>
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Applies To</label>
                            <select id="promoTargetTypeSelect" name="target_type" onchange="togglePromoProductDropdown(this.value)" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-800 font-bold outline-none focus:border-emerald-600 transition">
                                <option value="all">All Products</option>
                                <option value="specific">Specific Product Only</option>
                            </select>
                        </div>
                    </div>

                    <!-- Specific Product Picker -->
                    <div id="promoSpecificProductRow" class="hidden">
                        <label class="text-slate-700 block mb-1 font-bold">Select Target Product</label>
                        <select id="promoProductIdSelect" name="product_id" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-800 font-bold outline-none">
                            <option value="0">-- Select Specific Product --</option>
                            <?php foreach ($productsData as $pd): ?>
                                <option value="<?= $pd['id'] ?>"><?= htmlspecialchars($pd['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Usage Limit (Max Uses)</label>
                            <input type="number" min="1" id="promoMaxUsesInput" name="max_uses" value="100" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                        </div>
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Expiry Date &amp; Time (Optional)</label>
                            <input type="datetime-local" id="promoExpiresAtInput" name="expires_at" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-700 font-sans outline-none">
                        </div>
                        <div class="flex items-center space-x-2 pt-6">
                            <label class="flex items-center space-x-2 cursor-pointer font-bold text-xs text-slate-800">
                                <input type="checkbox" id="promoIsActiveInput" name="is_active" value="1" checked class="rounded scale-125 text-emerald-600">
                                <span>Active &amp; Available</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <button type="submit" id="btnSubmitPromo" class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold uppercase text-xs shadow transition active:scale-95">
                            Save Coupon
                        </button>
                        <button type="button" onclick="resetPromoForm()" class="px-4 py-3 rounded-xl bg-slate-200 text-slate-700 font-bold text-xs uppercase hover:bg-slate-300 transition">
                            Reset Form
                        </button>
                    </div>
                </form>

                <!-- Active Coupons Table -->
                <div class="space-y-3 pt-2">
                    <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider">
                        Active Coupons List (<?= count($promoCodesList) ?>)
                    </h4>

                    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                        <table class="table-roomy min-w-[780px] text-left text-xs font-mono">
                            <thead>
                                <tr>
                                    <th>COUPON CODE</th>
                                    <th>DISCOUNT</th>
                                    <th>APPLIES TO</th>
                                    <th>USAGE</th>
                                    <th>EXPIRY</th>
                                    <th>STATUS</th>
                                    <th class="text-right">ACTION</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-[11px]">
                                <?php if (empty($promoCodesList)): ?>
                                    <tr><td colspan="7" class="py-8 text-center text-slate-400 font-sans">No coupons created yet. Create one above.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($promoCodesList as $pc): 
                                        $isOnline = (int)($pc['is_active'] ?? 1) === 1;
                                        $isExpired = (!empty($pc['expires_at']) && strtotime($pc['expires_at']) < time());
                                    ?>
                                        <tr>
                                            <td>
                                                <strong class="font-black text-emerald-700 select-all text-xs tracking-wider"><?= htmlspecialchars($pc['code']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-black bg-rose-50 text-rose-600 border border-rose-200">
                                                    <?= (float)$pc['discount_percent'] ?>% OFF
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($pc['target_type'] === 'specific' && !empty($pc['specific_product_name'])): ?>
                                                    <span class="text-slate-800 font-bold font-sans"><?= htmlspecialchars($pc['specific_product_name']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-emerald-700 font-bold uppercase">All Products</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="font-bold text-slate-700"><?= (int)$pc['used_count'] ?> / <?= (int)$pc['max_uses'] ?></span>
                                            </td>
                                            <td>
                                                <?php if ($isExpired): ?>
                                                    <span class="text-rose-500 font-bold">Expired</span>
                                                <?php elseif (!empty($pc['expires_at'])): ?>
                                                    <span class="text-slate-500"><?= date('d M Y, h:i A', strtotime($pc['expires_at'])) ?></span>
                                                <?php else: ?>
                                                    <span class="text-slate-400">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="admin_action" value="toggle_promo_code">
                                                    <input type="hidden" name="active_tab" value="tab-coupons">
                                                    <input type="hidden" name="promo_id" value="<?= $pc['id'] ?>">
                                                    <input type="hidden" name="current_status" value="<?= $isOnline ? 1 : 0 ?>">
                                                    <button type="submit" class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase transition <?= $isOnline ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' ?>">
                                                        <?= $isOnline ? 'ACTIVE' : 'OFF' ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="text-right space-x-1.5">
                                                <button type="button" onclick='openEditPromoModal(<?= json_encode($pc) ?>)' class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-bold hover:bg-slate-200">
                                                    Edit
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Delete this coupon?');">
                                                    <input type="hidden" name="admin_action" value="delete_promo_code">
                                                    <input type="hidden" name="active_tab" value="tab-coupons">
                                                    <input type="hidden" name="promo_id" value="<?= $pc['id'] ?>">
                                                    <button type="submit" class="p-1 text-rose-500 hover:text-rose-700">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 3. REFERRAL PROGRAM MANAGEMENT TAB                              -->
        <!-- =============================================================== -->
        <section id="tab-referral" class="tab-section hidden space-y-5 font-mono">
            <div class="box-panel p-5 space-y-5 border-emerald-200">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-gift text-emerald-600"></i> REFERRAL COMMISSION RULES
                        </h3>
                        <span class="text-[11px] text-slate-500 font-sans mt-0.5 block">
                            Configure reward cash (BDT or poysa) credited automatically to user wallets per successful referral.
                        </span>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase <?= ($settings['referral_system_status'] ?? '1') === '1' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' ?>">
                        <?= ($settings['referral_system_status'] ?? '1') === '1' ? 'PROGRAM ONLINE' : 'PAUSED' ?>
                    </span>
                </div>

                <!-- Referral Settings Form -->
                <form method="POST" class="bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200 space-y-4 text-xs shadow-inner">
                    <input type="hidden" name="admin_action" value="save_referral_settings">
                    <input type="hidden" name="active_tab" value="tab-referral">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Reward Per Successful Referral (BDT / Poysa)</label>
                            <input type="number" step="0.5" min="0" name="referral_reward_amount" value="<?= htmlspecialchars($settings['referral_reward_amount'] ?? '5.00') ?>" required class="w-full bg-white border border-slate-200 p-3 rounded-xl text-emerald-700 font-black text-base outline-none focus:border-emerald-600">
                            <span class="text-[10px] text-slate-500 mt-1 block">Set amount in BDT (e.g. 5.00, 10.00, or 2.50). Credited to referrer upon user signup.</span>
                        </div>
                        <div class="bg-white p-3.5 rounded-xl border border-slate-200 flex items-center justify-between h-fit my-auto">
                            <div>
                                <span class="text-slate-900 font-bold block">Enable Referral Program</span>
                                <span class="text-[10px] text-slate-400 font-sans">Active referral rewards on registration</span>
                            </div>
                            <input type="checkbox" name="referral_system_status" value="1" <?= ($settings['referral_system_status'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded scale-125 text-emerald-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-white p-3.5 rounded-xl border border-slate-200 flex items-center justify-between">
                            <div>
                                <span class="text-slate-900 font-bold block">BDT Referral Bonus</span>
                                <span class="text-[10px] text-slate-400 font-sans">OFF করলে referrer কোনো টাকা পাবে না</span>
                            </div>
                            <input type="checkbox" name="referral_bdt_enabled" value="1" <?= ($settings['referral_bdt_enabled'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded scale-125 text-emerald-600">
                        </div>
                        <div class="bg-amber-50 p-3.5 rounded-xl border border-amber-200 flex items-center justify-between">
                            <div>
                                <span class="text-amber-800 font-bold block"><i class="fa-solid fa-coins mr-1"></i>Coin System</span>
                                <span class="text-[10px] text-amber-700/70 font-sans">ON করলে refer এ coin + user profile এ convert দেখাবে</span>
                            </div>
                            <input type="checkbox" name="referral_coin_enabled" value="1" <?= ($settings['referral_coin_enabled'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded scale-125 text-amber-500">
                        </div>
                    </div>
                    <p class="text-[10px] text-slate-500 font-sans">
                        শুধু Coin চালাতে চাইলে: <b>BDT Referral Bonus</b> বন্ধ + <b>Coin System</b> চালু রাখুন। Save করলেই ইউজারদের পেজে ইনস্ট্যান্ট আপডেট হবে।
                    </p>

                    <button type="submit" class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold uppercase text-xs shadow transition active:scale-95">
                        Save Referral Rules
                    </button>
                </form>

                <!-- REFERRAL COIN RULES -->
                <div class="bg-amber-50/60 border border-amber-200 rounded-2xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-black text-amber-800 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="fa-solid fa-coins"></i> REFERRAL COINS
                        </h4>
                        <span class="text-[10px] font-bold text-amber-700 bg-white border border-amber-200 px-2.5 py-1 rounded-full">Total coins given: <?= number_format($totalCoinsGiven) ?></span>
                    </div>
                    <p class="text-[11px] text-slate-600 font-sans leading-relaxed">
                        <b>Coins per Referral</b> = প্রতি সফল রেফারে referrer কত coin পাবে (একাধিক active rule থাকলে যোগ হবে)।<br>
                        <b>Convert Pack</b> = ইউজার কত coin দিয়ে কত টাকা wallet এ নিতে পারবে।
                    </p>

                    <form method="POST" class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 items-end text-xs">
                        <input type="hidden" name="admin_action" value="add_ref_coin_rule">
                        <input type="hidden" name="active_tab" value="tab-referral">
                        <div class="col-span-2 sm:col-span-1">
                            <label class="text-slate-700 block mb-1 font-bold">Type</label>
                            <select name="rule_type" id="refCoinRuleType" onchange="toggleRefCoinTaka()" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                                <option value="reward">Coins per Referral</option>
                                <option value="convert">Convert Pack</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Coins</label>
                            <input type="number" min="1" step="1" name="coins" required placeholder="10" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                        </div>
                        <div id="refCoinTakaWrap" class="hidden">
                            <label class="text-slate-700 block mb-1 font-bold">= Taka (৳)</label>
                            <input type="number" min="0.01" step="0.01" name="taka" id="refCoinTakaInput" placeholder="10" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                        </div>
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Title (optional)</label>
                            <input type="text" name="title" placeholder="auto" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none">
                        </div>
                        <button type="submit" class="py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-extrabold uppercase text-xs shadow transition">+ Add</button>
                    </form>

                    <div class="rounded-2xl border border-amber-100 bg-white divide-y divide-slate-100">
                        <?php if (empty($refCoinRules)): ?>
                            <div class="p-3 text-center text-[11px] text-slate-400">No coin rules yet. Add one above.</div>
                        <?php else: foreach ($refCoinRules as $cr): ?>
                            <div class="p-2.5 text-xs">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase <?= $cr['rule_type'] === 'reward' ? 'bg-emerald-50 text-emerald-700' : 'bg-sky-50 text-sky-700' ?>">
                                        <?= $cr['rule_type'] === 'reward' ? 'PER REFER' : 'CONVERT' ?>
                                    </span>
                                    <strong class="text-slate-900 font-sans ml-1"><?= htmlspecialchars($cr['title']) ?></strong>
                                    <span class="block text-[10px] text-slate-400 mt-0.5 font-mono">
                                        <?= number_format((int)$cr['coins']) ?> coins<?= $cr['rule_type'] === 'convert' ? ' = ৳' . number_format((float)$cr['taka'], 2) : '' ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <form method="POST">
                                        <input type="hidden" name="admin_action" value="toggle_ref_coin_rule">
                                        <input type="hidden" name="active_tab" value="tab-referral">
                                        <input type="hidden" name="rule_id" value="<?= (int)$cr['id'] ?>">
                                        <button type="submit" class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase <?= (int)$cr['is_active'] === 1 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-400 border border-slate-200' ?>">
                                            <?= (int)$cr['is_active'] === 1 ? 'ON' : 'OFF' ?>
                                        </button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Delete this coin rule?');">
                                        <input type="hidden" name="admin_action" value="delete_ref_coin_rule">
                                        <input type="hidden" name="active_tab" value="tab-referral">
                                        <input type="hidden" name="rule_id" value="<?= (int)$cr['id'] ?>">
                                        <button type="submit" class="text-rose-500 hover:text-rose-700 p-1.5"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <details class="mt-2">
                                <summary class="cursor-pointer text-[10px] font-bold text-amber-700 uppercase select-none"><i class="fa-solid fa-pen mr-1"></i>Edit</summary>
                                <form method="POST" class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-2 items-end">
                                    <input type="hidden" name="admin_action" value="edit_ref_coin_rule">
                                    <input type="hidden" name="active_tab" value="tab-referral">
                                    <input type="hidden" name="rule_id" value="<?= (int)$cr['id'] ?>">
                                    <div>
                                        <label class="text-slate-600 block mb-1 font-bold text-[10px]">Coins</label>
                                        <input type="number" min="1" step="1" name="coins" required value="<?= (int)$cr['coins'] ?>" class="w-full bg-white border border-slate-200 p-2 rounded-lg text-slate-900 font-bold outline-none">
                                    </div>
                                    <?php if ($cr['rule_type'] === 'convert'): ?>
                                    <div>
                                        <label class="text-slate-600 block mb-1 font-bold text-[10px]">= Taka (৳)</label>
                                        <input type="number" min="0.01" step="0.01" name="taka" required value="<?= htmlspecialchars((string)$cr['taka']) ?>" class="w-full bg-white border border-slate-200 p-2 rounded-lg text-slate-900 font-bold outline-none">
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <label class="text-slate-600 block mb-1 font-bold text-[10px]">Title</label>
                                        <input type="text" name="title" value="<?= htmlspecialchars($cr['title']) ?>" class="w-full bg-white border border-slate-200 p-2 rounded-lg text-slate-900 outline-none">
                                    </div>
                                    <button type="submit" class="py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold uppercase text-[10px] shadow">Save</button>
                                </form>
                            </details>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- Referral Summary Grid -->
                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-0.5">
                        <span class="text-[11px] font-bold text-slate-400 uppercase">Total Referral Invites</span>
                        <h3 class="text-2xl font-black text-slate-900"><?= count($referralsList) ?></h3>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-0.5">
                        <span class="text-[11px] font-bold text-slate-400 uppercase">Total Referral Bonuses Paid</span>
                        <h3 class="text-2xl font-black text-emerald-600">৳<?= number_format($totalReferralPaid, 2) ?></h3>
                    </div>
                </div>

                <!-- Referrals Log Table -->
                <div class="space-y-2 pt-2">
                    <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider">Recent Referral Logs</h4>
                    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                        <table class="table-roomy min-w-[700px] text-left text-xs font-mono">
                            <thead>
                                <tr>
                                    <th>REFERRER</th>
                                    <th>JOINED USER</th>
                                    <th>BONUS CREDITED</th>
                                    <th>COINS</th>
                                    <th>STATUS</th>
                                    <th class="text-right">DATE</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-[11px]">
                                <?php if (empty($referralsList)): ?>
                                    <tr><td colspan="6" class="py-8 text-center text-slate-400 font-sans">No referral transactions yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($referralsList as $rf): ?>
                                        <tr>
                                            <td>
                                                <strong class="text-slate-900 font-bold font-sans"><?= htmlspecialchars($rf['referrer_username']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="text-emerald-700 font-bold font-sans"><?= htmlspecialchars($rf['referred_username']) ?></span>
                                            </td>
                                            <td>
                                                <strong class="text-emerald-600">+৳<?= number_format((float)$rf['reward_amount'], 2) ?></strong>
                                            </td>
                                            <td>
                                                <strong class="text-amber-600">+<?= number_format((int)($rf['reward_coins'] ?? 0)) ?></strong>
                                            </td>
                                            <td>
                                                <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-50 text-emerald-700 uppercase">
                                                    <?= htmlspecialchars($rf['status']) ?>
                                                </span>
                                            </td>
                                            <td class="text-right text-slate-400">
                                                <?= $rf['created_at'] ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 4. PRODUCTS CATALOG TAB (SYNC, INVENTORY & PLANS)               -->
        <!-- =============================================================== -->
        <section id="tab-products" class="tab-section hidden space-y-5 font-mono">
            <div class="box-panel p-2 grid grid-cols-3 gap-2 text-xs font-bold font-mono">
                <button type="button" onclick="switchProductSubView('view-catalog')" id="pill-catalog" class="py-3 px-3 rounded-xl bg-emerald-600 text-white font-black flex items-center justify-center gap-2 shadow-sm transition">
                    <i class="fa-solid fa-boxes-stacked text-sm"></i><span>Catalog (<?= count($productsData) ?>)</span>
                </button>
                <button type="button" onclick="switchProductSubView('view-add')" id="pill-add" class="py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition">
                    <i class="fa-solid fa-plus text-sm"></i><span>+ Add Product</span>
                </button>
                <button type="button" onclick="switchProductSubView('view-stock')" id="pill-stock" class="py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition">
                    <i class="fa-solid fa-cubes-stacked text-amber-500 text-sm"></i><span>Stock Vault (<?= $totalStockKeys ?>)</span>
                </button>
            </div>

            <!-- Top Actions & Exchange Rate Box -->
            <div id="catalogActionsBox" class="box-panel p-4 flex flex-col sm:flex-row items-center justify-between gap-4 border-slate-200">
                <div class="flex items-center space-x-2.5 w-full sm:w-auto flex-wrap gap-y-2">
                    <form method="POST" class="inline">
                        <input type="hidden" name="admin_action" value="sync_km_products">
                        <input type="hidden" name="active_tab" value="tab-products">
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white uppercase text-xs font-bold flex items-center gap-2 shadow transition active:scale-95">
                            <i class="fa-solid fa-rotate text-xs"></i><span>Sync All (KM)</span>
                        </button>
                    </form>
                    <button type="button" onclick="openSelectSyncModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold uppercase text-xs flex items-center gap-2 border border-slate-200 shadow-sm transition active:scale-95">
                        <i class="fa-solid fa-list-check text-xs"></i><span>Select Items</span>
                    </button>
                    <button type="button" onclick="confirmDeleteAllProducts()" class="px-4 py-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 font-bold uppercase text-xs flex items-center gap-2 hover:bg-rose-100 shadow-sm transition active:scale-95">
                        <i class="fa-solid fa-trash text-xs"></i><span>Delete All</span>
                    </button>
                </div>

                <form method="POST" class="flex items-center space-x-2.5 bg-slate-50 px-3.5 py-2 rounded-xl border border-slate-200 w-full sm:w-auto justify-between shadow-inner">
                    <input type="hidden" name="admin_action" value="update_usdt_rate_quick">
                    <input type="hidden" name="active_tab" value="tab-products">
                    <span class="text-xs text-slate-500 font-bold font-mono">1 USD = ৳</span>
                    <input type="number" step="0.5" name="usdt_rate" value="<?= $effectiveBdtRate ?>" required class="w-16 bg-transparent text-emerald-700 font-mono font-black text-sm outline-none text-center">
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white font-black text-[11px] uppercase tracking-wider transition">
                        SET RATE
                    </button>
                </form>
            </div>

            <!-- View 1: Catalog List -->
            <div id="view-catalog" class="space-y-3.5">
                <?php if (empty($productsData)): ?>
                    <div class="box-panel p-10 text-center text-slate-400 text-xs space-y-2.5">
                        <i class="fa-solid fa-box-open text-3xl text-slate-300 block"></i>
                        <p class="font-sans">No products in catalog. Click <strong>Sync All</strong> or <strong>Select Items</strong> to import.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($productsData as $prod): 
                        $planCount = count($prod['plans']);
                        $isProdActive = (int)($prod['is_active'] ?? 1) === 1;
                    ?>
                        <div class="box-panel p-4 space-y-3 hover:border-emerald-300 transition">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3.5 overflow-hidden">
                                    <span class="w-8 h-8 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-xs text-slate-700 font-bold flex-shrink-0">
                                        #<?= $prod['sort_order'] ?>
                                    </span>
                                    <div class="overflow-hidden">
                                        <div class="flex items-center space-x-2.5">
                                            <h4 class="text-sm font-black text-slate-900 truncate"><?= htmlspecialchars($prod['name']) ?></h4>
                                            <button type="button" onclick="openProductEditor(<?= (int)$prod['id'] ?>)" class="text-slate-400 hover:text-emerald-600 p-1" title="Edit Product">
    <i class="fa-solid fa-pen-to-square text-xs"></i>
</button>
                                            <?php if (!$isProdActive): ?>
                                                <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase bg-rose-50 text-rose-600 border border-rose-200">OFFLINE</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-[11px] text-slate-500 font-sans block mt-0.5"><?= $planCount ?> Validity Plans Linked &bull; Category: <?= htmlspecialchars($prod['category'] ?: 'NON ROOT') ?></span>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-2 flex-shrink-0">
                                    <button type="button" onclick="openAddPlanModal(<?= $prod['id'] ?>, '<?= htmlspecialchars(addslashes($prod['name'])) ?>')" class="py-1.5 px-3 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold text-xs hover:bg-emerald-100 transition">
                                        + Plan
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Delete this product and its plans?');" class="inline">
                                        <input type="hidden" name="admin_action" value="delete_product_entire">
                                        <input type="hidden" name="active_tab" value="tab-products">
                                        <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
                                        <button type="submit" class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 border border-rose-200 text-xs hover:bg-rose-100 flex items-center justify-center transition">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                    <button type="button" onclick="togglePlanAccordion(<?= $prod['id'] ?>)" class="text-slate-400 hover:text-slate-700 p-1.5">
                                        <i id="accordion-arrow-<?= $prod['id'] ?>" class="fa-solid fa-chevron-down text-xs transition-transform duration-200"></i>
                                    </button>
                                </div>
                            </div>

                            <div id="plan-accordion-<?= $prod['id'] ?>" class="hidden pt-3 border-t border-slate-100 space-y-2">
                                <?php if ($planCount === 0): ?>
                                    <p class="text-xs text-slate-400 py-1.5 font-sans">No validity plans attached. Click "+ Plan" above.</p>
                                <?php else: ?>
                                    <?php foreach ($prod['plans'] as $pl): 
                                        $bdtP = round((float)$pl['user_price'] * $effectiveBdtRate, 0);
                                        $plDisc = (float)($pl['discount_percent'] ?? 0);
                                    ?>
                                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                                            <div>
                                                <strong class="text-slate-900"><?= htmlspecialchars($pl['plan_title']) ?></strong>
                                                <div class="flex items-center space-x-2 mt-1">
                                                    <span class="text-[10px] text-emerald-700 font-mono">KM ID: #<?= $pl['api_plan_id'] ?: 'None' ?></span>
                                                    <?php if ($plDisc > 0): ?>
                                                        <span class="text-[9px] px-1.5 py-0.2 rounded bg-rose-50 text-rose-600 font-bold border border-rose-200"><?= $plDisc ?>% OFF</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-emerald-700 font-bold text-xs">৳<?= number_format($bdtP, 0) ?> BDT</span>
                                                <span class="text-[10px] text-slate-400 block font-mono">($<?= number_format((float)$pl['user_price'], 2) ?> USD)</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- View 2: Add Product Form -->
            <div id="view-add" class="hidden box-panel p-5 space-y-4 font-mono text-xs">
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">+ Create Product</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="admin_action" value="add_new_product">
                    <input type="hidden" name="active_tab" value="tab-products">

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="text-slate-700 block mb-1 font-bold">Product Title</label>
                            <input type="text" name="name" required placeholder="e.g. VIP Mod Pass" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 transition font-sans">
                        </div>
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Category</label>
                            <select name="category" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 outline-none focus:border-emerald-600 font-bold">
                                <option value="NON ROOT" selected>NON ROOT</option>
                                <option value="ROOT">ROOT</option>
                                <option value="I PHONE">I PHONE</option>
                                <option value="PC">PC</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-emerald-700 block mb-1 font-bold">Logo File</label>
                            <input type="file" name="product_logo" accept="image/*" class="w-full text-slate-500 file:mr-2 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:bg-emerald-600 file:text-white">
                        </div>
                        <div>
                            <label class="text-emerald-700 block mb-1 font-bold">Banner File</label>
                            <input type="file" name="product_banner" accept="image/*" class="w-full text-slate-500 file:mr-2 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:bg-emerald-600 file:text-white">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <input type="url" name="download_link" placeholder="Download File URL" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-800 outline-none focus:border-emerald-600">
                        <input type="url" name="setup_link" placeholder="Setup Tutorial URL" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-800 outline-none focus:border-emerald-600">
                        <input type="url" name="app_download_url" placeholder="Direct App APK Download URL" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-emerald-700 outline-none focus:border-emerald-600 font-mono">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <input type="text" name="tags" placeholder="Tags: #SAFE, #INSTANT" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 outline-none focus:border-emerald-600">
                        </div>
                        <div>
                            <input type="number" name="sort_order" value="0" placeholder="Sort Order" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 outline-none focus:border-emerald-600 font-bold">
                        </div>
                    </div>

                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Description</label>
                        <textarea name="description" rows="2" placeholder="Short description shown on index page..." class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 outline-none font-sans focus:border-emerald-600"></textarea>
                    </div>

                    <div class="flex items-center space-x-6 pt-1">
                        <label class="flex items-center space-x-2 cursor-pointer text-slate-700 text-xs font-bold">
                            <input type="checkbox" name="is_active" value="1" checked class="rounded text-emerald-600 scale-110">
                            <span>Status: Online</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer text-slate-700 text-xs font-bold">
                            <input type="checkbox" name="is_pinned" value="1" class="rounded text-amber-500 scale-110">
                            <span>Pin to Top</span>
                        </label>
                    </div>

                    <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase tracking-wider text-xs shadow-md transition active:scale-95">
                        Create Product
                    </button>
                </form>
            </div>

            <!-- View 3: Stock Vault Form -->
            <div id="view-stock" class="hidden box-panel p-5 space-y-4 font-mono text-xs">
                <h3 class="text-xs font-black text-amber-600 uppercase tracking-wider">📦 Add Inventory Keys</h3>
                <form method="POST" class="space-y-3.5">
                    <input type="hidden" name="admin_action" value="save_stock_keys">
                    <input type="hidden" name="active_tab" value="tab-products">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <select name="stock_product_id" id="stockProdSelect" onchange="onStockProdChange()" required class="bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-800 outline-none focus:border-emerald-600 font-bold">
                            <option value="">-- Choose Product --</option>
                            <?php foreach ($productsData as $pd): ?>
                                <option value="<?= $pd['id'] ?>"><?= htmlspecialchars($pd['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="stock_plan_id" id="stockPlanSelect" required class="bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-800 outline-none focus:border-emerald-600 font-bold">
                            <option value="">-- Select Product First --</option>
                        </select>
                    </div>

                    <textarea name="bulk_keys" rows="6" placeholder="Paste keys here (one key per line)..." required class="w-full bg-slate-50 border border-slate-200 p-3.5 rounded-xl text-slate-900 outline-none font-mono text-xs leading-relaxed focus:border-emerald-600 shadow-inner"></textarea>
                    
                    <button type="submit" class="w-full py-3.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black rounded-xl uppercase tracking-wider text-xs shadow transition active:scale-95">
                        Save to Vault
                    </button>
                </form>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 5. DISCOUNT MANAGEMENT HUB (PRODUCTS / CUSTOMERS / RESELLERS)   -->
        <!-- =============================================================== -->
        <section id="tab-discounts" class="tab-section hidden space-y-5 font-mono">
            <div class="box-panel p-2 grid grid-cols-3 gap-2 text-xs font-bold font-mono">
                <button type="button" onclick="switchDiscountSubTab('disc-view-products')" id="pill-disc-prods" class="py-3 px-3 rounded-xl bg-emerald-600 text-white font-black flex items-center justify-center gap-2 shadow-sm transition">
                    <i class="fa-solid fa-boxes-stacked text-sm"></i><span>All Products</span>
                </button>
                <button type="button" onclick="switchDiscountSubTab('disc-view-customers')" id="pill-disc-custs" class="py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition">
                    <i class="fa-solid fa-user-group text-sm text-sky-600"></i><span>Customers</span>
                </button>
                <button type="button" onclick="switchDiscountSubTab('disc-view-resellers')" id="pill-disc-resells" class="py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition">
                    <i class="fa-solid fa-users-gear text-sm text-amber-600"></i><span>Resellers</span>
                </button>
            </div>

            <!-- Sub 1: All Products Discount -->
            <div id="disc-view-products" class="space-y-4">
                <div class="box-panel p-5 space-y-4 border-slate-200">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
                        <div>
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-boxes-stacked text-emerald-600"></i> GLOBAL PRODUCTS DISCOUNT CONTROL
                            </h3>
                            <span class="text-[11px] text-slate-500 font-sans mt-0.5 block">
                                Set store-wide discount across all product plans or reset all product discounts to 0%.
                            </span>
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
                        <span class="text-xs font-bold text-slate-700 uppercase block font-sans">⚡ Quick Action: All Products Together</span>
                        <div class="flex flex-col sm:flex-row items-center gap-2.5">
                            <form method="POST" class="flex items-center gap-2 w-full sm:w-auto">
                                <input type="hidden" name="admin_action" value="apply_bulk_product_discount">
                                <input type="hidden" name="active_tab" value="tab-discounts">
                                <input type="hidden" name="disc_sub_tab" value="disc-view-products">
                                <div class="flex items-center bg-white px-3 py-2 rounded-xl border border-slate-200">
                                    <span class="text-emerald-700 font-bold text-xs mr-2">% Discount:</span>
                                    <input type="number" step="0.5" min="0" max="99" name="discount_percent" required placeholder="e.g. 10" class="w-20 bg-transparent text-emerald-700 font-black text-sm outline-none">
                                </div>
                                <button type="submit" class="py-2.5 px-4 rounded-xl bg-emerald-600 text-white text-xs uppercase font-black whitespace-nowrap shadow">
                                    Apply To All Products
                                </button>
                            </form>

                            <form method="POST" onsubmit="return confirm('Turn off all product discounts and restore regular prices?');" class="w-full sm:w-auto">
                                <input type="hidden" name="admin_action" value="reset_all_product_discounts">
                                <input type="hidden" name="active_tab" value="tab-discounts">
                                <input type="hidden" name="disc_sub_tab" value="disc-view-products">
                                <button type="submit" class="w-full sm:w-auto py-2.5 px-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 hover:bg-rose-100 text-xs uppercase font-bold transition">
                                    <i class="fa-solid fa-power-off mr-1"></i> Reset All (0%)
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Search Filter for Product Plans -->
                    <div class="relative pt-1">
                        <input type="text" id="discPlanSearchInput" onkeyup="filterDiscProductPlans()" placeholder="Search product or plan title to set individual discount..." class="w-full bg-slate-50 border border-slate-200 text-xs text-slate-900 rounded-xl p-3 pl-10 outline-none focus:border-emerald-600">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none mt-0.5"></i>
                    </div>

                    <!-- Plans List Table -->
                    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                        <table class="table-roomy min-w-[750px] text-left text-xs font-mono">
                            <thead>
                                <tr>
                                    <th>PRODUCT &amp; PLAN</th>
                                    <th>REGULAR PRICE</th>
                                    <th>CURRENT DISCOUNT</th>
                                    <th>SALE PRICE</th>
                                    <th class="text-right">UPDATE DISCOUNT</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-[11px]" id="discProductPlansTableBody">
                                <?php if (empty($allProductPlansFlat)): ?>
                                    <tr><td colspan="5" class="py-8 text-center text-slate-400 font-sans">No products/plans registered in catalog.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($allProductPlansFlat as $pl): 
                                        $regP = (float)$pl['regular_price'] > 0 ? (float)$pl['regular_price'] : (float)$pl['user_price'];
                                        $curP = (float)$pl['user_price'];
                                        $dPercent = (float)($pl['discount_percent'] ?? 0);
                                        $bdtPrice = round($curP * $effectiveBdtRate, 0);
                                    ?>
                                        <tr class="disc-plan-row hover:bg-slate-50 transition" data-keywords="<?= strtolower(htmlspecialchars($pl['product_name'] . ' ' . $pl['plan_title'])) ?>">
                                            <td>
                                                <strong class="font-bold text-slate-900 font-sans text-xs block"><?= htmlspecialchars($pl['product_name']) ?></strong>
                                                <span class="text-emerald-700 text-[10px] block mt-0.5"><?= htmlspecialchars($pl['plan_title']) ?></span>
                                            </td>
                                            <td class="text-slate-500 font-bold">
                                                $<?= number_format($regP, 2) ?> USD
                                            </td>
                                            <td>
                                                <?php if ($dPercent > 0): ?>
                                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        <?= $dPercent ?>% OFF
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-slate-400 font-bold">0% (Regular)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong class="text-emerald-700 font-black text-xs">$<?= number_format($curP, 2) ?> USD</strong>
                                                <span class="text-[9px] text-slate-400 block font-mono">≈ ৳<?= number_format($bdtPrice, 0) ?> BDT</span>
                                            </td>
                                            <td class="text-right">
                                                <form method="POST" class="inline-flex items-center space-x-1.5">
                                                    <input type="hidden" name="admin_action" value="apply_single_plan_discount">
                                                    <input type="hidden" name="active_tab" value="tab-discounts">
                                                    <input type="hidden" name="disc_sub_tab" value="disc-view-products">
                                                    <input type="hidden" name="plan_id" value="<?= $pl['id'] ?>">
                                                    <input type="number" step="0.5" min="0" max="99" name="discount_percent" value="<?= $dPercent > 0 ? $dPercent : '' ?>" placeholder="0" class="w-14 bg-slate-50 border border-slate-200 p-1 rounded-lg text-slate-900 font-bold text-center text-xs outline-none">
                                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-[10px] uppercase font-bold">Save</button>
                                                    <?php if ($dPercent > 0): ?>
                                                        <button type="submit" onclick="this.form.discount_percent.value='0'" class="px-2 py-1 rounded-lg bg-rose-50 text-rose-600 border border-rose-200 text-[10px] font-bold" title="Reset discount">Off</button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sub 2: Customers Discount -->
            <div id="disc-view-customers" class="hidden space-y-4">
                <div class="box-panel p-5 space-y-4 border-slate-200">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
                        <div>
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-user-group text-sky-600"></i> CUSTOMERS DISCOUNT CONTROL
                            </h3>
                            <span class="text-[11px] text-slate-500 font-sans mt-0.5 block">
                                Set customer extra discounts that stack automatically with product plan discounts.
                            </span>
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
                        <span class="text-xs font-bold text-slate-700 uppercase block font-sans">⚡ Quick Action: All Customers Together</span>
                        <div class="flex flex-col sm:flex-row items-center gap-2.5">
                            <form method="POST" class="flex items-center gap-2 w-full sm:w-auto">
                                <input type="hidden" name="admin_action" value="apply_bulk_user_discount">
                                <input type="hidden" name="active_tab" value="tab-discounts">
                                <input type="hidden" name="disc_sub_tab" value="disc-view-customers">
                                <input type="hidden" name="target_role" value="customer">
                                <div class="flex items-center bg-white px-3 py-2 rounded-xl border border-slate-200">
                                    <span class="text-sky-700 font-bold text-xs mr-2">Extra % Disc:</span>
                                    <input type="number" step="0.5" min="0" max="99" name="discount_percent" required placeholder="e.g. 5" class="w-20 bg-transparent text-slate-900 font-black text-sm outline-none">
                                </div>
                                <button type="submit" class="py-2.5 px-4 rounded-xl bg-sky-600 text-white text-xs uppercase font-black whitespace-nowrap shadow">
                                    Apply To All Customers
                                </button>
                            </form>

                            <form method="POST" onsubmit="return confirm('Turn off all customer extra discounts?');" class="w-full sm:w-auto">
                                <input type="hidden" name="admin_action" value="reset_all_user_discounts">
                                <input type="hidden" name="active_tab" value="tab-discounts">
                                <input type="hidden" name="disc_sub_tab" value="disc-view-customers">
                                <input type="hidden" name="target_role" value="customer">
                                <button type="submit" class="w-full sm:w-auto py-2.5 px-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 hover:bg-rose-100 text-xs uppercase font-bold transition">
                                    <i class="fa-solid fa-power-off mr-1"></i> Reset All (0%)
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Customers List -->
                    <div class="space-y-2.5" id="discCustomerListContainer">
                        <?php foreach ($usersList as $u): 
                            $uRole = strtolower(trim((string)($u['role'] ?? 'customer')));
                            if ($uRole === 'reseller' || $uRole === 'admin') continue;
                            $uDisc = (float)($u['discount_percent'] ?? 0);
                        ?>
                            <div class="stat-card flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs disc-user-card"
                                 data-username="<?= strtolower(htmlspecialchars($u['username'] ?? '')) ?>"
                                 data-email="<?= strtolower(htmlspecialchars($u['email'] ?? '')) ?>">
                                <div>
                                    <div class="flex items-center space-x-2.5">
                                        <h4 class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($u['username']) ?></h4>
                                        <?php if ($uDisc > 0): ?>
                                            <span class="text-[9px] px-2 py-0.5 rounded font-black font-mono uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                +<?= $uDisc ?>% Stacked Disc
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[9px] px-2 py-0.5 rounded font-bold font-mono uppercase bg-slate-100 text-slate-500">
                                                Regular (0%)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-3 mt-1 font-mono text-[11px] text-slate-500">
                                        <span><?= htmlspecialchars($u['email'] ?: 'No Gmail') ?></span>
                                        <span>Wallet: <strong class="text-emerald-700">$<?= number_format((float)$u['balance'], 2) ?></strong></span>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-2 font-mono flex-shrink-0">
                                    <form method="POST" class="inline-flex items-center space-x-1.5">
                                        <input type="hidden" name="admin_action" value="apply_single_user_discount">
                                        <input type="hidden" name="active_tab" value="tab-discounts">
                                        <input type="hidden" name="disc_sub_tab" value="disc-view-customers">
                                        <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                        <input type="number" step="0.5" min="0" max="99" name="discount_percent" value="<?= $uDisc > 0 ? $uDisc : '' ?>" placeholder="0" class="w-14 bg-slate-50 border border-slate-200 p-1.5 rounded-lg text-slate-900 font-bold text-center text-xs outline-none">
                                        <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-sky-50 text-sky-700 border border-sky-200 font-bold hover:bg-sky-100 text-xs">Set %</button>
                                    </form>

                                    <button type="button" onclick="openUserAccessModal(<?= htmlspecialchars(json_encode($u)) ?>)" class="px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-bold transition">
                                        Plan Custom $
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Sub 3: Resellers Discount -->
            <div id="disc-view-resellers" class="hidden space-y-4">
                <div class="box-panel p-5 space-y-4 border-slate-200">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
                        <div>
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-users-gear text-amber-600"></i> RESELLERS DISCOUNT CONTROL
                            </h3>
                            <span class="text-[11px] text-slate-500 font-sans mt-0.5 block">
                                Set wholesale reseller discounts that stack with product base prices.
                            </span>
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
                        <span class="text-xs font-bold text-slate-700 uppercase block font-sans">⚡ Quick Action: All Resellers Together</span>
                        <div class="flex flex-col sm:flex-row items-center gap-2.5">
                            <form method="POST" class="flex items-center gap-2 w-full sm:w-auto">
                                <input type="hidden" name="admin_action" value="apply_bulk_user_discount">
                                <input type="hidden" name="active_tab" value="tab-discounts">
                                <input type="hidden" name="disc_sub_tab" value="disc-view-resellers">
                                <input type="hidden" name="target_role" value="reseller">
                                <div class="flex items-center bg-white px-3 py-2 rounded-xl border border-slate-200">
                                    <span class="text-amber-700 font-bold text-xs mr-2">Extra % Disc:</span>
                                    <input type="number" step="0.5" min="0" max="99" name="discount_percent" required placeholder="e.g. 10" class="w-20 bg-transparent text-slate-900 font-black text-sm outline-none">
                                </div>
                                <button type="submit" class="py-2.5 px-4 rounded-xl bg-amber-500 text-slate-950 text-xs uppercase font-black whitespace-nowrap shadow">
                                    Apply To All Resellers
                                </button>
                            </form>

                            <form method="POST" onsubmit="return confirm('Turn off all reseller extra discounts?');" class="w-full sm:w-auto">
                                <input type="hidden" name="admin_action" value="reset_all_user_discounts">
                                <input type="hidden" name="active_tab" value="tab-discounts">
                                <input type="hidden" name="disc_sub_tab" value="disc-view-resellers">
                                <input type="hidden" name="target_role" value="reseller">
                                <button type="submit" class="w-full sm:w-auto py-2.5 px-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 hover:bg-rose-100 text-xs uppercase font-bold transition">
                                    <i class="fa-solid fa-power-off mr-1"></i> Reset All (0%)
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Resellers List -->
                    <div class="space-y-2.5" id="discResellerListContainer">
                        <?php foreach ($usersList as $u): 
                            $uRole = strtolower(trim((string)($u['role'] ?? '')));
                            if ($uRole !== 'reseller') continue;
                            $uDisc = (float)($u['discount_percent'] ?? 0);
                        ?>
                            <div class="stat-card flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs disc-user-card"
                                 data-username="<?= strtolower(htmlspecialchars($u['username'] ?? '')) ?>"
                                 data-email="<?= strtolower(htmlspecialchars($u['email'] ?? '')) ?>">
                                <div>
                                    <div class="flex items-center space-x-2.5">
                                        <h4 class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($u['username']) ?></h4>
                                        <?php if ($uDisc > 0): ?>
                                            <span class="text-[9px] px-2 py-0.5 rounded font-black font-mono uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                +<?= $uDisc ?>% Stacked Disc
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[9px] px-2 py-0.5 rounded font-bold font-mono uppercase bg-slate-100 text-slate-500">
                                                Regular (0%)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-3 mt-1 font-mono text-[11px] text-slate-500">
                                        <span><?= htmlspecialchars($u['email'] ?: 'No Gmail') ?></span>
                                        <span>Wallet: <strong class="text-emerald-700">$<?= number_format((float)$u['balance'], 2) ?></strong></span>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-2 font-mono flex-shrink-0">
                                    <form method="POST" class="inline-flex items-center space-x-1.5">
                                        <input type="hidden" name="admin_action" value="apply_single_user_discount">
                                        <input type="hidden" name="active_tab" value="tab-discounts">
                                        <input type="hidden" name="disc_sub_tab" value="disc-view-resellers">
                                        <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                        <input type="number" step="0.5" min="0" max="99" name="discount_percent" value="<?= $uDisc > 0 ? $uDisc : '' ?>" placeholder="0" class="w-14 bg-slate-50 border border-slate-200 p-1.5 rounded-lg text-slate-900 font-bold text-center text-xs outline-none">
                                        <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 font-bold hover:bg-amber-100 text-xs">Set %</button>
                                    </form>

                                    <button type="button" onclick="openUserAccessModal(<?= htmlspecialchars(json_encode($u)) ?>)" class="px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-bold transition">
                                        Plan Custom $
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 6. PAYMENT GATEWAYS & SKYPAY RULES TAB                          -->
        <!-- =============================================================== -->
        <section id="tab-gateways" class="tab-section hidden space-y-5 font-mono">
            <div class="box-panel p-2 grid grid-cols-2 gap-2 text-xs font-bold font-mono">
                <button type="button" onclick="switchGatewaySubView('view-pm-methods')" id="pill-gw-methods" class="py-3 px-3 rounded-xl bg-emerald-600 text-white font-black flex items-center justify-center gap-2 shadow-sm transition">
                    <i class="fa-solid fa-money-bill-wave text-sm"></i>
                    <span>Channels (<?= count($paymentMethodsList) ?>)</span>
                </button>
                <button type="button" onclick="switchGatewaySubView('view-pm-rules')" id="pill-gw-rules" class="py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition">
                    <i class="fa-solid fa-sliders text-sm text-emerald-600"></i>
                    <span>Payment Set-up</span>
                </button>
            </div>

            <!-- SUB-VIEW 1: PAYMENT CHANNELS -->
            <div id="view-pm-methods" class="space-y-4">
                <div class="box-panel p-5 space-y-4 border-slate-200">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
                        <div>
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-building-columns text-emerald-600"></i> PAYMENT CHANNELS
                            </h3>
                            <span class="text-[11px] text-slate-500 font-sans mt-0.5 block">
                                Configure bKash, Nagad, Rocket, Binance, or custom payment accounts.
                            </span>
                        </div>
                        <button type="button" onclick="openAddPaymentMethodModal()" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs uppercase tracking-wider flex items-center gap-2 shadow transition active:scale-95">
                            <i class="fa-solid fa-plus text-xs"></i> <span>Add Channel</span>
                        </button>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                        <table class="table-roomy min-w-[850px] text-left text-xs font-mono">
                            <thead>
                                <tr>
                                    <th>METHOD</th>
                                    <th>ACCOUNT INFO</th>
                                    <th>MEDIA</th>
                                    <th>ADD FUND</th>
                                    <th>CHECKOUT</th>
                                    <th>STATUS</th>
                                    <th class="text-right">ACTION</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-[11px]">
                                <?php if (empty($paymentMethodsList)): ?>
                                    <tr>
                                        <td colspan="7" class="py-10 text-center text-slate-400 font-sans">
                                            No payment methods registered. Click "+ Add Channel" to create one.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($paymentMethodsList as $pm): 
                                        $hasLogo   = !empty($pm['logo_url']);
                                        $hasBanner = !empty($pm['banner_url']);
                                        $hasQr     = !empty($pm['qr_image_url']);
                                        $isOnline  = (int)($pm['is_active'] ?? 1) === 1;
                                    ?>
                                        <tr class="hover:bg-slate-50 transition">
                                            <td>
                                                <div class="flex items-center space-x-2.5">
                                                    <div class="w-8 h-8 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-700 overflow-hidden flex-shrink-0">
                                                        <?php if ($hasLogo): ?>
                                                            <img src="<?= htmlspecialchars($pm['logo_url']) ?>" class="w-full h-full object-cover" alt="Logo">
                                                        <?php else: ?>
                                                            <i class="fa-solid fa-money-bill-wave text-emerald-600 text-xs"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <strong class="font-bold text-slate-900 font-sans text-xs block"><?= htmlspecialchars($pm['name']) ?></strong>
                                                        <span class="text-[9px] text-slate-400 font-mono">Key: <?= htmlspecialchars($pm['method_key']) ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong class="text-slate-800 font-bold block text-xs tracking-wider select-all"><?= htmlspecialchars($pm['account_number']) ?></strong>
                                                <span class="text-[9px] text-emerald-700 uppercase font-mono"><?= htmlspecialchars($pm['account_type'] ?: 'Personal') ?></span>
                                            </td>
                                            <td>
                                                <div class="flex items-center space-x-1.5">
                                                    <?php if ($hasBanner): ?>
                                                        <span class="px-2 py-0.5 rounded text-[8px] font-bold bg-slate-100 text-slate-700">BANNER</span>
                                                    <?php endif; ?>
                                                    <?php if ($hasQr): ?>
                                                        <span class="px-2 py-0.5 rounded text-[8px] font-bold bg-emerald-50 text-emerald-700">QR</span>
                                                    <?php endif; ?>
                                                    <?php if (!$hasBanner && !$hasQr): ?>
                                                        <span class="text-slate-400 text-[10px]">None</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase <?= (int)($pm['allow_add_fund'] ?? 1) === 1 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600' ?>">
                                                    <?= (int)($pm['allow_add_fund'] ?? 1) === 1 ? 'YES' : 'NO' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase <?= (int)($pm['allow_checkout'] ?? 1) === 1 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600' ?>">
                                                    <?= (int)($pm['allow_checkout'] ?? 1) === 1 ? 'YES' : 'NO' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="admin_action" value="toggle_payment_method_status">
                                                    <input type="hidden" name="active_tab" value="tab-gateways">
                                                    <input type="hidden" name="method_id" value="<?= $pm['id'] ?>">
                                                    <input type="hidden" name="current_status" value="<?= $isOnline ? 1 : 0 ?>">
                                                    <button type="submit" class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase transition <?= $isOnline ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' ?>">
                                                        <?= $isOnline ? 'ONLINE' : 'OFFLINE' ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="text-right space-x-1.5">
                                                <button type="button" onclick='openEditPaymentMethodModal(<?= json_encode($pm) ?>)' class="px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold transition">
                                                    Edit
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Delete this payment method?');">
                                                    <input type="hidden" name="admin_action" value="delete_payment_method">
                                                    <input type="hidden" name="active_tab" value="tab-gateways">
                                                    <input type="hidden" name="method_id" value="<?= $pm['id'] ?>">
                                                    <button type="submit" class="p-1.5 text-rose-500 hover:text-rose-700 transition">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- SUB-VIEW 2: PAYMENT SET-UP (SKYPAY BD) -->
            <div id="view-pm-rules" class="hidden space-y-4">
                <div class="box-panel p-5 space-y-4 border-slate-200 bg-emerald-50/20">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <div>
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-bolt-lightning text-amber-500 text-sm"></i> SKYPAY BD AUTO-VERIFICATION
                            </h3>
                            <span class="text-[11px] text-slate-500 font-sans mt-0.5 block">
                                Connect SkyPay BD BRAND-KEY (API v2) — customer just enters TrxID / Order ID and it is verified instantly (bKash, Nagad, Rocket, Upay, Bangla QR, Binance, BEP20).
                            </span>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold uppercase <?= ($settings['auto_payment_on'] ?? '0') === '1' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                            <?= ($settings['auto_payment_on'] ?? '0') === '1' ? 'AUTO-PAY ACTIVE' : 'MANUAL APPROVAL' ?>
                        </span>
                    </div>

                    <form method="POST" class="bg-white p-4 rounded-2xl border border-slate-200 space-y-4 text-xs">
                        <input type="hidden" name="admin_action" value="save_skypay_rules">
                        <input type="hidden" name="active_tab" value="tab-gateways">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div>
                                <label class="text-slate-700 block mb-1 font-bold">SkyPay Base URL</label>
                                <input type="url" name="skypay_base_url" value="<?= htmlspecialchars(skypayConfig($settings)['base_url']) ?>" required class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 outline-none focus:border-emerald-600 font-mono text-xs">
                            </div>
                            <div>
                                <label class="text-slate-700 block mb-1 font-bold">SkyPay BRAND-KEY</label>
                                <input type="text" name="skypay_brand_key" value="<?= htmlspecialchars(skypayConfig($settings)['brand_key']) ?>" placeholder="Paste your active SkyPay BRAND-KEY" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 outline-none focus:border-emerald-600 font-mono text-xs font-bold">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 flex items-center justify-between">
                                <div>
                                    <span class="text-slate-900 font-bold text-xs block">Enable Auto-Payment Gateway</span>
                                    <span class="text-[10px] text-slate-500 font-sans">Deliver product &amp; balance instantly when TrxID matches</span>
                                </div>
                                <input type="checkbox" name="auto_payment_on" value="1" <?= ($settings['auto_payment_on'] ?? '0') === '1' ? 'checked' : '' ?> class="rounded text-emerald-600 scale-125">
                            </div>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 flex items-center justify-between">
                                <div>
                                    <span class="text-slate-900 font-bold text-xs block">Enforce Realtime Auto-Verification</span>
                                    <span class="text-[10px] text-slate-500 font-sans">Strictly reject invalid/duplicate TrxIDs directly</span>
                                </div>
                                <input type="checkbox" name="skypay_auto_verify" value="1" <?= ((string)($settings['skypay_auto_verify'] ?? '0') === '1' || (string)($settings['snzpay_auto_verify'] ?? '0') === '1') ? 'checked' : '' ?> class="rounded text-emerald-600 scale-125">
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center gap-2.5 pt-1">
                            <button type="submit" class="w-full sm:flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl uppercase text-xs font-extrabold shadow">
                                Save Gateway Configuration
                            </button>
                            <button type="submit" name="admin_action" value="test_skypay_connection" class="w-full sm:w-auto py-3 px-5 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 font-bold uppercase text-xs transition">
                                Test Ping API
                            </button>
                        </div>
                    </form>
                </div>

                <div class="box-panel p-5 space-y-4 border-slate-200">
                    <div class="pb-2 border-b border-slate-100">
                        <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-scale-balanced text-emerald-600"></i> LIMITS &amp; CURRENCY
                        </h3>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                        <input type="hidden" name="admin_action" value="update_gateway_rules">
                        <input type="hidden" name="active_tab" value="tab-gateways">

                        <div class="stat-card flex items-center justify-between">
                            <div>
                                <span class="text-slate-900 font-bold text-xs block">Deposit System Master Switch</span>
                                <span class="text-[10px] text-slate-500 font-sans">Open or close the entire Add Fund feature</span>
                            </div>
                            <select name="deposit_system_status" class="bg-slate-50 border border-slate-200 p-2 rounded-xl text-xs font-bold text-slate-800 outline-none">
                                <option value="1" <?= ($settings['deposit_system_status'] ?? '1') === '1' ? 'selected' : '' ?>>ONLINE (OPEN)</option>
                                <option value="0" <?= ($settings['deposit_system_status'] ?? '1') === '0' ? 'selected' : '' ?>>OFFLINE (PAUSED)</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                            <div>
                                <label class="text-slate-700 block mb-1 font-bold">1 USD = BDT (Store Exchange Rate)</label>
                                <input type="number" step="0.5" name="usdt_rate" value="<?= htmlspecialchars($settings['usdt_rate']) ?>" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 font-bold outline-none">
                            </div>
                            <div>
                                <label class="text-slate-700 block mb-1 font-bold">Binance Market Rate</label>
                                <input type="number" step="0.5" name="binance_market_rate" value="<?= htmlspecialchars($settings['binance_market_rate'] ?? '125') ?>" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 font-bold outline-none">
                            </div>
                            <div>
                                <label class="text-slate-700 block mb-1 font-bold">Binance Pay ID / UID</label>
                                <input type="text" name="binance_id" value="<?= htmlspecialchars($settings['binance_id'] ?? '') ?>" placeholder="12345678" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 outline-none font-bold">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div>
                                <label class="text-slate-700 block mb-1 font-bold">Min Deposit Limit (BDT / USD)</label>
                                <input type="number" step="0.01" name="min_deposit_limit" value="<?= htmlspecialchars($settings['min_deposit_limit']) ?>" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 font-bold outline-none">
                            </div>
                            <div>
                                <label class="text-slate-700 block mb-1 font-bold">Max Deposit Limit (BDT / USD)</label>
                                <input type="number" step="0.01" name="max_deposit_limit" value="<?= htmlspecialchars($settings['max_deposit_limit']) ?>" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 font-bold outline-none">
                            </div>
                        </div>

                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
                            <label class="text-slate-700 block font-bold">Global Payment Banner Image</label>
                            <?php if (!empty($settings['payment_banner_url'])): ?>
                                <div class="w-full h-24 rounded-xl overflow-hidden border border-slate-200 relative mb-2">
                                    <img src="<?= htmlspecialchars($settings['payment_banner_url']) ?>" class="w-full h-full object-cover" alt="Banner">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="payment_banner_file" accept="image/*" class="w-full text-slate-500 file:mr-2 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:bg-emerald-600 file:text-white">
                        </div>

                        <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase tracking-wider text-xs shadow-md">
                            Save Limits &amp; Exchange Rates
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 7. CONNECT KEY MANAGER HUB                                      -->
        <!-- =============================================================== -->
        <section id="tab-connectkm" class="tab-section hidden space-y-5 font-mono">
            <div class="box-panel p-5 space-y-5 border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-bolt-lightning text-amber-500"></i> KEY MANAGER INTEGRATION
                    </h3>
                    <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase flex items-center gap-2 <?= ($settings['km_last_status'] ?? '') === 'connected' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' ?>">
                        <span class="w-2 h-2 rounded-full <?= ($settings['km_last_status'] ?? '') === 'connected' ? 'bg-emerald-500' : 'bg-rose-500' ?>"></span>
                        <span><?= strtoupper($settings['km_last_status'] ?? 'UNTESTED') ?></span>
                    </span>
                </div>

                <form method="POST" class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3.5 text-xs">
                    <input type="hidden" name="admin_action" value="save_km_credentials">
                    <input type="hidden" name="active_tab" value="tab-connectkm">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">API Endpoint URL</label>
                            <input type="url" name="km_api_url" value="<?= htmlspecialchars($settings['km_api_url'] ?? '') ?>" placeholder="https://keymanager.shop/api.php" required class="w-full bg-white border border-slate-200 p-3 rounded-xl text-slate-900 outline-none focus:border-emerald-600 font-mono">
                        </div>
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Secret API Token</label>
                            <div class="relative">
                                <input type="password" id="kmApiKeyInput" name="km_api_key" value="<?= htmlspecialchars($settings['km_api_key'] ?? '') ?>" placeholder="keymanager_live_..." required class="w-full bg-white border border-slate-200 p-3 pr-11 rounded-xl text-slate-900 font-mono outline-none focus:border-emerald-600">
                                <button type="button" onclick="toggleKmKeyVisibility()" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700">
                                    <i id="kmKeyEyeIcon" class="fa-solid fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase text-xs shadow transition">
                        Save &amp; Connect Key Manager
                    </button>
                </form>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 text-xs">
                    <div class="bg-white p-4 rounded-2xl border border-slate-200 flex flex-col justify-between shadow-sm">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">PROVIDER BALANCE</span>
                        <h3 class="text-2xl font-black text-emerald-600 mt-1"><?= $providerBalance ?></h3>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200 flex flex-col justify-between shadow-sm">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">LINKED PLANS</span>
                        <h3 class="text-2xl font-black text-slate-900 mt-1"><?= $kmImportedCount ?> Plans</h3>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200 flex items-center gap-2.5 shadow-sm">
                        <form method="POST" class="flex-1">
                            <input type="hidden" name="admin_action" value="test_km_connection">
                            <input type="hidden" name="active_tab" value="tab-connectkm">
                            <button type="submit" class="w-full py-2.5 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 text-[11px] font-bold uppercase hover:bg-amber-100 transition">
                                Test Ping
                            </button>
                        </form>
                        <form method="POST" class="flex-1">
                            <input type="hidden" name="admin_action" value="sync_km_products">
                            <input type="hidden" name="active_tab" value="tab-connectkm">
                            <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-[11px] uppercase transition shadow hover:bg-emerald-700">
                                Sync All
                            </button>
                        </form>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 space-y-3">
                    <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider">
                        Linked Plans (<?= $kmImportedCount ?>)
                    </h4>

                    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                        <table class="table-roomy min-w-[750px] text-left text-xs font-mono">
                            <thead>
                                <tr>
                                    <th>PLAN ID</th>
                                    <th>PRODUCT NAME</th>
                                    <th>VALIDITY</th>
                                    <th>WHOLESALE</th>
                                    <th>RETAIL</th>
                                    <th class="text-right">STATUS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-[11px]">
                                <?php if (empty($kmImportedPlans)): ?>
                                    <tr><td colspan="6" class="py-8 text-center text-slate-400 font-sans">No plans linked. Connect API and click Sync All.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($kmImportedPlans as $kp): 
                                        $costBdt = round((float)$kp['reseller_price'] * $effectiveBdtRate, 0);
                                        $userBdt = round((float)$kp['user_price'] * $effectiveBdtRate, 0);
                                    ?>
                                        <tr>
                                            <td class="text-slate-800 font-bold">#<?= $kp['api_plan_id'] ?></td>
                                            <td class="font-bold text-slate-900 font-sans"><?= htmlspecialchars($kp['product_name']) ?></td>
                                            <td class="text-slate-600"><?= htmlspecialchars($kp['plan_title']) ?></td>
                                            <td class="text-emerald-700 font-bold">
                                                $<?= number_format((float)$kp['reseller_price'], 2) ?>
                                                <span class="text-[9px] text-slate-400 block font-normal">(৳<?= $costBdt ?>)</span>
                                            </td>
                                            <td class="text-slate-800 font-bold">
                                                $<?= number_format((float)$kp['user_price'], 2) ?>
                                                <span class="text-[9px] text-slate-400 block font-normal">(৳<?= $userBdt ?>)</span>
                                            </td>
                                            <td class="text-right">
                                                <span class="px-2.5 py-1 rounded-lg text-[9px] font-bold bg-emerald-50 text-emerald-700">LINKED</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 8. SPIN CONTROL TAB                                             -->
        <!-- =============================================================== -->
        <section id="tab-spinwheel" class="tab-section hidden space-y-5 font-mono">
            <div class="box-panel p-5 space-y-5 border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-dharmachakra text-emerald-600"></i> LUCKY SPIN ENGINE
                    </h3>
                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase <?= ($settings['spin_wheel_enabled'] ?? '1') === '1' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' ?>">
                        <?= ($settings['spin_wheel_enabled'] ?? '1') === '1' ? 'ONLINE' : 'HIDDEN' ?>
                    </span>
                </div>

                <form method="POST" class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-4 text-xs">
                    <input type="hidden" name="admin_action" value="update_spin_wheel_settings">
                    <input type="hidden" name="active_tab" value="tab-spinwheel">

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3.5">
                        <div class="bg-white p-3 rounded-xl border border-slate-200 flex items-center justify-between">
                            <span class="text-slate-700 font-bold text-xs">Visibility</span>
                            <input type="checkbox" name="spin_wheel_enabled" value="1" <?= ($settings['spin_wheel_enabled'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded text-emerald-600 scale-125">
                        </div>

                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Spin Cost ($)</label>
                            <input type="number" step="0.01" name="spin_cost" value="<?= htmlspecialchars($settings['spin_cost'] ?? '0.00') ?>" placeholder="0.00" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                        </div>

                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Daily Limit / User</label>
                            <input type="number" name="spin_daily_limit" value="<?= htmlspecialchars($settings['spin_daily_limit'] ?? '10') ?>" min="1" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                        </div>

                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Expiry (Hours)</label>
                            <input type="number" name="spin_offer_duration_hours" value="<?= htmlspecialchars($settings['spin_offer_duration_hours'] ?? '24') ?>" min="1" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                        </div>
                    </div>

                    <!-- EARN-SPIN RULES -->
                    <div class="bg-emerald-50/60 border border-emerald-200 rounded-2xl p-3.5 space-y-3">
                        <div class="text-[11px] font-black text-emerald-800 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="fa-solid fa-bolt"></i> Earn Spin Rules
                        </div>
                        <p class="text-[11px] text-slate-600 font-sans leading-relaxed">
                            ইউজার যত টাকা খরচ করবে (ওয়ালেট / ডাইরেক্ট পেমেন্ট), নিচের টার্গেট পূর্ণ হলেই ১টি স্পিন পাবে।
                            স্পিনে Min–Max এর মধ্যে যেকোনো একটি amount জিতবে এবং সাথে সাথে ওয়ালেটে যোগ হবে।
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                            <div>
                                <label class="text-slate-700 block mb-1 font-bold">১ স্পিনের জন্য খরচ (BDT)</label>
                                <input type="number" step="1" min="1" name="spin_spend_per_spin" value="<?= htmlspecialchars($settings['spin_spend_per_spin'] ?? '500') ?>" placeholder="500" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                            </div>
                            <div>
                                <label class="text-slate-700 block mb-1 font-bold">Prize Min (BDT)</label>
                                <input type="number" step="1" min="1" name="spin_prize_min" value="<?= htmlspecialchars($settings['spin_prize_min'] ?? '1') ?>" placeholder="1" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                            </div>
                            <div>
                                <label class="text-slate-700 block mb-1 font-bold">Prize Max (BDT)</label>
                                <input type="number" step="1" min="1" name="spin_prize_max" value="<?= htmlspecialchars($settings['spin_prize_max'] ?? '50') ?>" placeholder="50" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div class="bg-white p-3 rounded-xl border border-slate-200 flex items-center justify-between">
                                <span class="text-slate-700 font-bold text-xs">Referral দিয়েও স্পিন</span>
                                <input type="checkbox" name="spin_refer_enabled" value="1" <?= ($settings['spin_refer_enabled'] ?? '0') === '1' ? 'checked' : '' ?> class="rounded text-emerald-600 scale-125">
                            </div>
                            <div>
                                <label class="text-slate-700 block mb-1 font-bold">কতজন রেফার = ১ স্পিন</label>
                                <input type="number" step="1" min="1" name="spin_refer_per_spin" value="<?= htmlspecialchars($settings['spin_refer_per_spin'] ?? '1') ?>" placeholder="1" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="bg-white border border-slate-200 rounded-xl p-2">
                                <span class="block text-[10px] text-slate-500 uppercase font-bold">Spins Given</span>
                                <strong class="text-sm text-slate-900"><?= number_format($spinStats['given']) ?></strong>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-xl p-2">
                                <span class="block text-[10px] text-slate-500 uppercase font-bold">Spins Used</span>
                                <strong class="text-sm text-slate-900"><?= number_format($spinStats['used']) ?></strong>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-xl p-2">
                                <span class="block text-[10px] text-slate-500 uppercase font-bold">Total Paid</span>
                                <strong class="text-sm text-emerald-700">৳<?= number_format($spinStats['paid_bdt'], 0) ?></strong>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase text-xs shadow transition">
                        Save Spin Settings
                    </button>
                </form>

                <div class="space-y-2 pt-1">
                    <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider">RECENT SPIN WINNERS</h4>
                    <div class="rounded-2xl border border-slate-200 bg-white divide-y divide-slate-100">
                        <?php if (empty($spinRecentWinners)): ?>
                            <div class="p-3 text-center text-[11px] text-slate-400">No spin winners yet.</div>
                        <?php else: foreach ($spinRecentWinners as $sw): ?>
                            <div class="p-2.5 flex items-center justify-between text-xs">
                                <div>
                                    <strong class="text-slate-900 font-sans"><?= htmlspecialchars($sw['username']) ?></strong>
                                    <span class="block text-[10px] text-slate-400"><?= htmlspecialchars($sw['created_at']) ?></span>
                                </div>
                                <span class="font-black text-emerald-700">+৳<?= number_format((float)$sw['prize_bdt'], 0) ?></span>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <div class="space-y-3.5 pt-1">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider">PRIZE SEGMENTS <span class="text-[9px] text-slate-400 normal-case font-bold">(old offer system)</span></h4>
                        <button type="button" onclick="openAddSpinSegmentModal()" class="px-3.5 py-2 rounded-xl bg-emerald-600 text-white font-bold text-xs uppercase shadow transition active:scale-95">
                            + Add Segment
                        </button>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                        <table class="table-roomy min-w-[750px] text-left text-xs font-mono">
                            <thead>
                                <tr>
                                    <th>COLOR</th>
                                    <th>TITLE</th>
                                    <th>TARGET</th>
                                    <th>BONUS</th>
                                    <th>CHANCE</th>
                                    <th>STATUS</th>
                                    <th class="text-right">ACTION</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($spinSegments as $seg): ?>
                                    <tr>
                                        <td>
                                            <div class="w-5 h-5 rounded-full border border-slate-200" style="background-color: <?= htmlspecialchars($seg['color']) ?>;"></div>
                                        </td>
                                        <td class="font-bold text-slate-900 font-sans"><?= htmlspecialchars($seg['title']) ?></td>
                                        <td class="text-slate-700">৳<?= number_format((float)$seg['spend_target'], 0) ?></td>
                                        <td class="text-emerald-700 font-bold">৳<?= number_format((float)$seg['bonus_amount'], 0) ?></td>
                                        <td class="text-slate-900 font-bold"><?= (int)$seg['probability'] ?>%</td>
                                        <td>
                                            <span class="text-[10px] font-bold uppercase <?= (int)$seg['is_active'] === 1 ? 'text-emerald-700' : 'text-slate-400' ?>">
                                                <?= (int)$seg['is_active'] === 1 ? 'ACTIVE' : 'OFF' ?>
                                            </span>
                                        </td>
                                        <td class="text-right space-x-2">
                                            <button type="button" onclick='openEditSpinSegmentModal(<?= json_encode($seg) ?>)' class="text-slate-500 hover:text-slate-900 p-1">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('Delete segment?');">
                                                <input type="hidden" name="admin_action" value="delete_spin_segment">
                                                <input type="hidden" name="active_tab" value="tab-spinwheel">
                                                <input type="hidden" name="segment_id" value="<?= $seg['id'] ?>">
                                                <button type="submit" class="text-rose-500 hover:text-rose-700 p-1"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 9. KEYS & ORDERS FEED TAB                                       -->
        <!-- =============================================================== -->
        <section id="tab-keys" class="tab-section hidden space-y-5 font-mono">
            <div class="box-panel p-5 space-y-4 border-slate-200">
                <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-receipt text-emerald-600"></i> ORDERS &amp; LICENSE KEYS FEED
                    </h3>
                    <span class="text-xs text-slate-500 font-bold">Total: <?= count($sales) ?></span>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                    <table class="table-roomy min-w-[780px] text-left text-xs font-mono">
                        <thead>
                            <tr>
                                <th>TIME</th>
                                <th>BUYER</th>
                                <th>PLAN</th>
                                <th>LICENSE KEY</th>
                                <th class="text-right">PAYMENT</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-[11px]">
                            <?php if (empty($sales)): ?>
                                <tr><td colspan="5" class="py-8 text-center text-slate-400 font-sans">No licenses issued yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($sales as $s): ?>
                                    <tr>
                                        <td class="text-slate-400 text-xs"><?= $s['created_at'] ?></td>
                                        <td class="font-bold text-slate-900 font-sans text-xs"><?= htmlspecialchars($s['username']) ?></td>
                                        <td class="text-slate-600"><?= htmlspecialchars($s['validity'] ?? '') ?></td>
                                        <td class="text-emerald-700 select-all font-bold tracking-wider"><?= $s['license_key'] ?>
                                            <?php if (($s['status'] ?? 'active') === 'stopped'): ?>
                                                <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-600 uppercase">STOPPED</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right">
                                            <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-slate-100 text-slate-700 uppercase">
                                                <?= htmlspecialchars($s['payment_type'] ?? 'balance') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 10. REVENUE REPORT TAB                                          -->
        <!-- =============================================================== -->
        <section id="tab-revenue" class="tab-section hidden space-y-5 font-mono">
            <div class="box-panel p-5 space-y-5 border-slate-200">
                <div class="pb-2 border-b border-slate-100">
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice-dollar text-emerald-600"></i> FINANCIAL TELEMETRY
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="stat-card flex flex-col justify-between h-32 border-emerald-200">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">TODAY REVENUE</span>
                        <h2 class="text-3xl font-black text-emerald-600 mt-1">$<?= number_format($todaySales, 2) ?></h2>
                        <span class="text-[10px] text-slate-500 font-sans">Completed Sales Today</span>
                    </div>

                    <div class="stat-card flex flex-col justify-between h-32 border-slate-200">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">THIS MONTH REVENUE</span>
                        <h2 class="text-3xl font-black text-slate-800 mt-1">$<?= number_format($monthSales, 2) ?></h2>
                        <span class="text-[10px] text-slate-500 font-sans">Monthly Rolling Cycle</span>
                    </div>

                    <div class="stat-card flex flex-col justify-between h-32 border-slate-200">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">ALL TIME REVENUE</span>
                        <h2 class="text-3xl font-black text-slate-900 mt-1">$<?= number_format($allTimeSales, 2) ?></h2>
                        <span class="text-[10px] text-slate-500 font-sans">Gross Platform Settlement</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 11. DEPOSITS & REWARDS TAB                                      -->
        <!-- =============================================================== -->
        <section id="tab-deposits" class="tab-section hidden space-y-5 font-mono">
            <div class="box-panel p-2 grid grid-cols-2 gap-2 text-xs font-bold">
                <button type="button" onclick="switchDepositSubView('dep-verify-sec')" id="pill-dep-verify" class="py-3 px-3 rounded-xl bg-emerald-600 text-white font-black flex items-center justify-center gap-2 shadow-sm transition">
                    <i class="fa-solid fa-receipt text-sm"></i>
                    <span>VERIFY DEPOSITS</span>
                </button>
                <button type="button" onclick="switchDepositSubView('dep-rewards-sec')" id="pill-dep-rewards" class="py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition">
                    <i class="fa-solid fa-gift text-sm text-amber-500"></i>
                    <span>REWARD TIERS</span>
                </button>
            </div>

            <!-- Verify Section -->
            <div id="dep-verify-sec" class="box-panel p-5 space-y-4 border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-wallet text-emerald-600"></i> VERIFY CUSTOMER DEPOSITS
                        </h3>
                        <span class="text-[11px] text-slate-500 font-sans mt-0.5 block">Review deposits, approve or reject manual submissions.</span>
                    </div>
                    <span class="px-3 py-1 rounded-full text-[11px] font-bold uppercase bg-amber-50 text-amber-700 border border-amber-200 font-mono">
                        QUEUE: <?= $pendingTrxCount ?> PENDING
                    </span>
                </div>

                <?php
                    $trxCounts = ['all' => count($transactions), 'pending' => 0, 'approved' => 0, 'rejected' => 0];
                    foreach ($transactions as $tc) {
                        $k2 = strtolower((string)$tc['status']);
                        if (isset($trxCounts[$k2])) $trxCounts[$k2]++;
                    }
                ?>
                <!-- HISTORY FILTER + SEARCH -->
                <div class="space-y-2.5">
                    <div class="grid grid-cols-4 gap-1.5 text-[10px] font-black uppercase" id="trxFilterBar">
                        <button type="button" data-f="all" onclick="setTrxFilter('all')" class="trx-filter-btn py-2 rounded-xl border bg-slate-900 text-white border-slate-900">All <span class="opacity-70">(<?= $trxCounts['all'] ?>)</span></button>
                        <button type="button" data-f="pending" onclick="setTrxFilter('pending')" class="trx-filter-btn py-2 rounded-xl border bg-white text-amber-700 border-amber-200">Pending <span class="opacity-70">(<?= $trxCounts['pending'] ?>)</span></button>
                        <button type="button" data-f="approved" onclick="setTrxFilter('approved')" class="trx-filter-btn py-2 rounded-xl border bg-white text-emerald-700 border-emerald-200">Approved <span class="opacity-70">(<?= $trxCounts['approved'] ?>)</span></button>
                        <button type="button" data-f="rejected" onclick="setTrxFilter('rejected')" class="trx-filter-btn py-2 rounded-xl border bg-white text-rose-600 border-rose-200">Rejected <span class="opacity-70">(<?= $trxCounts['rejected'] ?>)</span></button>
                    </div>

                    <div class="flex gap-2">
                        <input type="text" id="trxSearchInput" oninput="applyTrxFilters()" onkeydown="if(event.key==='Enter'){event.preventDefault();runKeySearch();}"
                               placeholder="Search Key / TrxID / Email / Username..."
                               class="flex-1 bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-xs text-slate-900 outline-none focus:border-emerald-500 font-mono">
                        <button type="button" onclick="runKeySearch()" class="px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase shadow active:scale-95 transition">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </div>
                    <p class="text-[10px] text-slate-400 font-sans">Type to filter the list instantly. Press Enter / search button to look up <b>license keys</b> &amp; users (stop key / ban account).</p>

                    <div id="keySearchPanel" class="hidden rounded-2xl border border-emerald-200 bg-emerald-50/40 p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-black text-emerald-800 uppercase">Key &amp; User Results</span>
                            <button type="button" onclick="document.getElementById('keySearchPanel').classList.add('hidden')" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <div id="keySearchResults" class="space-y-2 max-h-96 overflow-y-auto"></div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                    <table class="table-roomy min-w-[850px] text-left text-xs font-mono">
                        <thead>
                            <tr>
                                <th>USER</th>
                                <th>METHOD</th>
                                <th>AMOUNT</th>
                                <th>BONUS (BDT)</th>
                                <th>SENDER</th>
                                <th>TRX ID</th>
                                <th class="text-right">ACTION</th>
                            </tr>
                        </thead>
                        <tbody id="trxTbody" class="divide-y divide-slate-100 text-[11px]">
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="7" class="py-10 text-center text-slate-400 font-sans">
                                        No deposit transactions recorded yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $t): 
                                    $bonusVal = (float)($t['bonus_amount'] ?? 0);
                                    $trxSearchBlob = strtolower(($t['username'] ?? '') . ' ' . ($t['email'] ?? '') . ' ' . ($t['trx_id'] ?? '') . ' ' . ($t['method'] ?? '') . ' ' . ($t['sender_num'] ?? '') . ' ' . ($t['amount'] ?? ''));
                                ?>
                                    <tr class="hover:bg-slate-50 transition trx-row" data-status="<?= htmlspecialchars(strtolower((string)$t['status'])) ?>" data-search="<?= htmlspecialchars($trxSearchBlob) ?>">
                                        <td>
                                            <strong class="font-bold text-slate-900 font-sans text-xs block"><?= htmlspecialchars($t['username']) ?></strong>
                                            <?php if (!empty($t['email'])): ?>
                                                <span class="text-[10px] text-slate-400 font-mono block mt-0.5"><?= htmlspecialchars($t['email']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-800">
                                                <?= htmlspecialchars($t['method']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-emerald-700 font-black text-xs">$<?= number_format((float)$t['amount'], 2) ?> USD</strong>
                                        </td>
                                        <td>
                                            <?php if ($bonusVal > 0): ?>
                                                <span class="text-amber-600 font-bold font-mono">+৳<?= number_format($bonusVal, 0) ?></span>
                                            <?php else: ?>
                                                <span class="text-slate-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-slate-600">
                                            <?= htmlspecialchars((string)($t['sender_num'] ?? 'N/A')) ?>
                                        </td>
                                        <td>
                                            <span class="text-slate-800 font-mono font-bold select-all tracking-wider text-xs">
                                                <?= htmlspecialchars($t['trx_id']) ?>
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <?php if ($t['status'] === 'pending'): ?>
                                                <form method="POST" class="inline flex items-center justify-end space-x-1.5">
                                                    <input type="hidden" name="admin_action" value="manage_trx">
                                                    <input type="hidden" name="active_tab" value="tab-deposits">
                                                    <input type="hidden" name="trx_db_id" value="<?= $t['id'] ?>">
                                                    <button type="submit" name="status" value="approved" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[10px] uppercase transition shadow">
                                                        Approve
                                                    </button>
                                                    <button type="submit" name="status" value="rejected" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-bold text-[10px] uppercase transition shadow">
                                                        Reject
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="px-3 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider inline-block <?= $t['status'] === 'approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' ?>">
                                                    <?= strtoupper($t['status']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bonus Tiers Section -->
            <div id="dep-rewards-sec" class="hidden box-panel p-5 space-y-4 border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-gift text-amber-500"></i> DEPOSIT REWARD TIERS
                        </h3>
                        <span class="text-[11px] text-slate-500 font-sans mt-0.5 block">Configure automatic cashbacks when users deposit balance.</span>
                    </div>
                    <button type="button" onclick="openAddDepositBonusModal()" class="px-3.5 py-2 rounded-xl bg-emerald-600 text-white font-bold text-xs uppercase shadow transition active:scale-95">
                        + Add Reward Tier
                    </button>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                    <table class="table-roomy min-w-[780px] text-left text-xs font-mono">
                        <thead>
                            <tr>
                                <th>TITLE</th>
                                <th>MIN DEPOSIT (BDT)</th>
                                <th>BONUS CASH</th>
                                <th>TYPE</th>
                                <th>CLAIM LIMIT</th>
                                <th>STATUS</th>
                                <th class="text-right">ACTION</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-[11px]">
                            <?php if (empty($depositBonusesList)): ?>
                                <tr>
                                    <td colspan="7" class="py-10 text-center text-slate-400 font-sans">
                                        No deposit reward tiers configured yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($depositBonusesList as $b): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td>
                                            <strong class="font-bold text-slate-900 font-sans text-xs"><?= htmlspecialchars($b['title']) ?></strong>
                                        </td>
                                        <td class="text-slate-800 font-bold">
                                            ৳<?= number_format((float)$b['min_deposit'], 0) ?>
                                        </td>
                                        <td class="text-emerald-700 font-black">
                                            +৳<?= number_format((float)$b['bonus_amount'], 0) ?>
                                        </td>
                                        <td>
                                            <span class="px-2 py-0.5 rounded text-[9px] uppercase font-bold bg-slate-100 text-slate-600">
                                                <?= strtoupper($b['bonus_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ((int)$b['claim_limit'] === 0): ?>
                                                <span class="text-emerald-700 font-bold">♾️ Unlimited</span>
                                            <?php else: ?>
                                                <span class="text-amber-600 font-bold"><?= (int)$b['claim_limit'] ?> Time(s)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-[10px] font-bold uppercase <?= (int)$b['is_active'] === 1 ? 'text-emerald-700' : 'text-slate-400' ?>">
                                                <?= (int)$b['is_active'] === 1 ? 'ACTIVE' : 'OFF' ?>
                                            </span>
                                        </td>
                                        <td class="text-right space-x-2">
                                            <button type="button" onclick='openEditDepositBonusModal(<?= json_encode($b) ?>)' class="text-slate-500 hover:text-slate-900 p-1">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('Delete reward tier?');">
                                                <input type="hidden" name="admin_action" value="delete_deposit_bonus">
                                                <input type="hidden" name="active_tab" value="tab-deposits">
                                                <input type="hidden" name="bonus_id" value="<?= $b['id'] ?>">
                                                <button type="submit" class="text-rose-500 hover:text-rose-700 p-1"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 12. CUSTOMERS DIRECTORY TAB                                     -->
        <!-- =============================================================== -->
        <section id="tab-customers" class="tab-section hidden space-y-5">
            <div class="box-panel p-5 space-y-4 border-slate-200">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 gap-3 font-mono">
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-user-group text-emerald-600"></i> CUSTOMERS DIRECTORY (<?= $totalCustomers ?>)
                        </h3>
                        <span class="text-[11px] text-slate-500 font-sans mt-0.5 block">Direct registered customers. Manage float &amp; permissions.</span>
                    </div>
                </div>

                <div class="relative">
                    <input type="text" id="customerSearchInput" onkeyup="filterUserList('customer')" placeholder="Search by Username or Email..." class="w-full bg-slate-50 border border-slate-200 text-xs text-slate-900 rounded-xl p-3 pl-10 outline-none focus:border-emerald-600 font-mono">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                </div>

                <div class="space-y-2.5" id="customerListContainer">
                    <?php foreach ($usersList as $u): 
                        $uRole = strtolower(trim((string)($u['role'] ?? 'customer')));
                        if ($uRole === 'reseller' || $uRole === 'admin') continue;
                    ?>
                        <div class="stat-card flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs user-item-card"
                             data-username="<?= strtolower(htmlspecialchars($u['username'] ?? '')) ?>"
                             data-email="<?= strtolower(htmlspecialchars($u['email'] ?? '')) ?>">
                            <div>
                                <div class="flex items-center space-x-2.5">
                                    <h4 class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($u['username']) ?></h4>
                                    <span class="text-[9px] px-2 py-0.5 rounded font-bold font-mono uppercase <?= ($u['status'] ?? 'active') === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' ?>">
                                        <?= $u['status'] ?? 'active' ?>
                                    </span>
                                    <?php if ((float)($u['discount_percent'] ?? 0) > 0): ?>
                                        <span class="text-[9px] px-2 py-0.5 rounded font-bold font-mono uppercase bg-rose-50 text-rose-600 border border-rose-200">
                                            +<?= (float)$u['discount_percent'] ?>% Extra Disc
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center space-x-3 mt-1 font-mono text-[11px] text-slate-500">
                                    <span><?= htmlspecialchars($u['email'] ?: 'No Email') ?></span>
                                    <span>Wallet: <strong class="text-emerald-700">$<?= number_format((float)$u['balance'], 2) ?></strong></span>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2 font-mono flex-shrink-0">
                                <button type="button" onclick="openBalanceModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>', '<?= htmlspecialchars(addslashes($u['email'] ?? '')) ?>', <?= (float)$u['balance'] ?>, '<?= $u['status'] ?? 'active' ?>')" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition">
                                    +/- Float
                                </button>
                                <button type="button" onclick="openUserAccessModal(<?= htmlspecialchars(json_encode($u)) ?>)" class="px-3 py-2 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 text-xs font-bold transition">
                                    Wholesale &amp; Custom $
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 13. RESELLERS DIRECTORY TAB                                     -->
        <!-- =============================================================== -->
        <section id="tab-resellers" class="tab-section hidden space-y-5">
            <div class="box-panel p-5 space-y-4 border-slate-200">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 gap-3 font-mono">
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-users-gear text-emerald-600"></i> RESELLERS DIRECTORY (<?= $totalResellers ?>)
                        </h3>
                        <span class="text-[11px] text-slate-500 font-sans mt-0.5 block">Wholesale resellers with custom pricing and permissions.</span>
                    </div>
                </div>

                <div class="relative">
                    <input type="text" id="resellerSearchInput" onkeyup="filterUserList('reseller')" placeholder="Search Resellers by Username or Email..." class="w-full bg-slate-50 border border-slate-200 text-xs text-slate-900 rounded-xl p-3 pl-10 outline-none focus:border-emerald-600 font-mono">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                </div>

                <div class="space-y-2.5" id="resellerListContainer">
                    <?php foreach ($usersList as $u): 
                        $uRole = strtolower(trim((string)($u['role'] ?? '')));
                        if ($uRole !== 'reseller') continue; 
                    ?>
                        <div class="stat-card flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs user-item-card"
                             data-username="<?= strtolower(htmlspecialchars($u['username'] ?? '')) ?>"
                             data-email="<?= strtolower(htmlspecialchars($u['email'] ?? '')) ?>">
                            <div>
                                <div class="flex items-center space-x-2.5">
                                    <h4 class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($u['username']) ?></h4>
                                    <span class="text-[9px] px-2 py-0.5 rounded font-bold font-mono uppercase <?= ($u['status'] ?? 'active') === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' ?>">
                                        <?= $u['status'] ?? 'active' ?>
                                    </span>
                                </div>
                                <div class="flex items-center space-x-3 mt-1 font-mono text-[11px] text-slate-500">
                                    <span><?= htmlspecialchars($u['email'] ?: 'No Email') ?></span>
                                    <span>Wallet: <strong class="text-emerald-700">$<?= number_format((float)$u['balance'], 2) ?></strong></span>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2 font-mono flex-shrink-0">
                                <button type="button" onclick="openBalanceModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>', '<?= htmlspecialchars(addslashes($u['email'] ?? '')) ?>', <?= (float)$u['balance'] ?>, '<?= $u['status'] ?? 'active' ?>')" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition">
                                    +/- Float
                                </button>
                                <button type="button" onclick="openUserAccessModal(<?= htmlspecialchars(json_encode($u)) ?>)" class="px-3 py-2 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 text-xs font-bold transition">
                                    Wholesale &amp; Access
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 14. SITE SETTINGS & BRANDING TAB (TABBED INTERFACE)             -->
        <!-- =============================================================== -->
        <section id="tab-settings" class="tab-section hidden space-y-4">
            
            <!-- SUB-SECTION BUTTONS (HORIZONTAL NAVIGATION) -->
            <div class="box-panel p-2 flex items-center gap-2 overflow-x-auto no-scrollbar font-mono text-xs">
                <button type="button" id="btn-set-branding" onclick="switchSettingsSubTab('set-view-branding', this)" class="set-sub-pill py-2.5 px-3.5 rounded-xl bg-emerald-600 text-white font-black flex-shrink-0 flex items-center gap-1.5 transition shadow-sm">
                    <i class="fa-solid fa-paintbrush"></i> <span>Branding</span>
                </button>
                <button type="button" id="btn-set-popup" onclick="switchSettingsSubTab('set-view-popup', this)" class="set-sub-pill py-2.5 px-3.5 rounded-xl bg-slate-100 text-slate-700 hover:text-slate-900 font-bold flex-shrink-0 flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-bell text-amber-500"></i> <span>Welcome Pop-up</span>
                </button>
                <button type="button" id="btn-set-notice" onclick="switchSettingsSubTab('set-view-notice', this)" class="set-sub-pill py-2.5 px-3.5 rounded-xl bg-slate-100 text-slate-700 hover:text-slate-900 font-bold flex-shrink-0 flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-bullhorn text-sky-500"></i> <span>Notice &amp; Rules</span>
                </button>
                <button type="button" id="btn-set-support" onclick="switchSettingsSubTab('set-view-support', this)" class="set-sub-pill py-2.5 px-3.5 rounded-xl bg-slate-100 text-slate-700 hover:text-slate-900 font-bold flex-shrink-0 flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-headset text-emerald-600"></i> <span>Support Links</span>
                </button>
                <button type="button" id="btn-set-oauth" onclick="switchSettingsSubTab('set-view-oauth', this)" class="set-sub-pill py-2.5 px-3.5 rounded-xl bg-slate-100 text-slate-700 hover:text-slate-900 font-bold flex-shrink-0 flex items-center gap-1.5 transition">
                    <i class="fa-brands fa-google text-red-500"></i> <span>Google OAuth</span>
                </button>
                <button type="button" id="btn-set-banners" onclick="switchSettingsSubTab('set-view-banners', this)" class="set-sub-pill py-2.5 px-3.5 rounded-xl bg-slate-100 text-slate-700 hover:text-slate-900 font-bold flex-shrink-0 flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-images text-indigo-500"></i> <span>Banners</span>
                </button>
            </div>

            <!-- MASTER SETTINGS FORM -->
            <form method="POST" enctype="multipart/form-data" class="space-y-4 text-xs font-mono">
                <input type="hidden" name="admin_action" value="update_branding_settings">
                <input type="hidden" name="active_tab" value="tab-settings">

                <!-- 1. BRANDING SUB-PAGE -->
                <div id="set-view-branding" class="settings-sub-view box-panel p-5 space-y-4 border-slate-200">
                    <div class="pb-2 border-b border-slate-100">
                        <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-paintbrush text-emerald-600"></i> General &amp; Branding
                        </h4>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Store Name</label>
                            <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name']) ?>" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 outline-none font-bold text-xs focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">App APK Download URL</label>
                            <input type="url" name="app_download_url" value="<?= htmlspecialchars($settings['app_download_url'] ?? '') ?>" placeholder="https://example.com/app.apk" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 font-mono text-xs outline-none focus:border-emerald-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200 space-y-2">
                            <label class="text-slate-700 block font-bold">Site Header Logo</label>
                            <?php if (!empty($settings['site_logo'])): ?>
                                <div class="flex items-center space-x-2 pb-1">
                                    <img src="<?= htmlspecialchars($settings['site_logo']) ?>" class="w-8 h-8 rounded-lg object-cover border border-slate-200">
                                    <label class="text-[10px] text-rose-500 cursor-pointer flex items-center space-x-1">
                                        <input type="checkbox" name="delete_site_logo" value="1">
                                        <span>Remove Logo</span>
                                    </label>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="site_logo_file" accept="image/*" class="w-full text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:bg-emerald-600 file:text-white">
                        </div>

                        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200 space-y-2">
                            <label class="text-slate-700 block font-bold">Center Loading Logo</label>
                            <?php if (!empty($settings['loading_logo'])): ?>
                                <div class="flex items-center space-x-2 pb-1">
                                    <img src="<?= htmlspecialchars($settings['loading_logo']) ?>" class="w-8 h-8 rounded-lg object-cover border border-slate-200">
                                    <label class="text-[10px] text-rose-500 cursor-pointer flex items-center space-x-1">
                                        <input type="checkbox" name="delete_loading_logo" value="1">
                                        <span>Remove Logo</span>
                                    </label>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="loading_logo_file" accept="image/*" class="w-full text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:bg-emerald-600 file:text-white">
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase text-xs shadow-md transition active:scale-95">
                        Save Branding Settings
                    </button>
                </div>

                <!-- 2. WELCOME POP-UP SUB-PAGE -->
                <div id="set-view-popup" class="settings-sub-view hidden box-panel p-5 space-y-4 border-slate-200">
                    <div class="pb-2 border-b border-slate-100 flex items-center justify-between">
                        <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-bell text-amber-500"></i> Welcome Pop-up Control
                        </h4>
                        <label class="flex items-center space-x-2 cursor-pointer text-xs font-bold text-slate-800">
                            <input type="checkbox" name="update_channel_popup_enabled" value="1" <?= ($settings['update_channel_popup_enabled'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded scale-125 text-emerald-600">
                            <span>Enable Pop-up</span>
                        </label>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Pop-up Heading / Title</label>
                            <input type="text" name="update_channel_title" value="<?= htmlspecialchars($settings['update_channel_title'] ?? 'স্বাগতম FF PANEL SELL-এ! 🎉') ?>" placeholder="স্বাগতম..." class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 outline-none font-bold text-xs focus:border-emerald-600">
                        </div>

                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Pop-up Details Message / Rules</label>
                            <textarea name="update_channel_msg" rows="3" placeholder="নতুন সব পাস, গিভঅ্যাওয়ে ও সাপোর্টের জন্য..." class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 outline-none font-sans text-xs focus:border-emerald-600"><?= htmlspecialchars($settings['update_channel_msg'] ?? 'নতুন সব পাস, ফ্রি গিভঅ্যাওয়ে, আপডেট ও ইনস্ট্যান্ট সাপোর্টের জন্য এখনই আমাদের অফিসিয়াল টেলিগ্রাম চ্যানেলে জয়েন করুন!') ?></textarea>
                        </div>

                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Telegram Channel Link</label>
                            <input type="url" name="update_channel_link" value="<?= htmlspecialchars($settings['update_channel_link'] ?? 'https://t.me/Ktoolsadminsaportteme') ?>" placeholder="https://t.me/yourchannel" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 font-mono text-xs outline-none focus:border-emerald-600">
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase text-xs shadow-md transition active:scale-95">
                        Save Pop-up Settings
                    </button>
                </div>

                <!-- 3. NOTICE & RULES SUB-PAGE (ANNOUNCEMENT + PRODUCT RULES) -->
                <div id="set-view-notice" class="settings-sub-view hidden box-panel p-5 space-y-4 border-slate-200">
                    <div class="pb-2 border-b border-slate-100">
                        <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-bullhorn text-sky-500"></i> Announcement Bar &amp; Maintenance Rules
                        </h4>
                    </div>

                    <div class="space-y-4">
                        <!-- ANNOUNCEMENT BAR TICKER -->
                        <div class="bg-emerald-50/50 p-4 rounded-2xl border border-emerald-200 space-y-2">
                            <label class="text-emerald-900 block font-bold text-xs flex items-center gap-1.5">
                                <i class="fa-solid fa-bullhorn text-yellow-500"></i> Top Announcement Bar Text (চলমান নোটিশ)
                            </label>
                            <input type="text" name="top_ticker_notice" value="<?= htmlspecialchars($settings['top_ticker_notice'] ?? 'অর্ডার করার ০০.১ সেকেন্ডেই অটো ডেলিভারি পেয়ে যাবেন!') ?>" placeholder="অর্ডার করার ০০.১ সেকেন্ডেই অটো ডেলিভারি..." class="w-full bg-white border border-slate-200 p-3 rounded-xl text-slate-900 font-sans text-xs outline-none focus:border-emerald-600">
                            <span class="text-[10px] text-slate-500 font-sans block">ওয়েবসাইটের একদম উপরে যে সবুজ নোটিশ বারটি সার্বক্ষণিক চলবে, তা এখানে লিখুন।</span>
                        </div>

                        <!-- PRODUCT MAINTENANCE DEFAULT RULES -->
                        <div class="bg-rose-50/50 p-4 rounded-2xl border border-rose-200 space-y-2">
                            <label class="text-rose-900 block font-bold text-xs flex items-center gap-1.5">
                                <i class="fa-solid fa-clipboard-list text-rose-500"></i> Product Maintenance Rules &amp; Notice (পপআপের রুলস)
                            </label>
                            <textarea name="prod_maintenance_default_rules" rows="4" placeholder="⚠️ Rules & Conditions:&#10;গ্রাহকদের অবগতির জন্য জানানো যাচ্ছে..." class="w-full bg-white border border-slate-200 p-3 rounded-xl text-slate-900 font-sans text-xs outline-none focus:border-rose-500"><?= htmlspecialchars($settings['prod_maintenance_default_rules'] ?? "⚠️ Rules & Conditions:\nএই প্রোডাক্টটির সার্ভার আপগ্রেড চলছে। সাময়িক অসুবিধার জন্য আমরা দুঃখিত। খুব শীঘ্রই সার্ভিস চালু করা হবে।") ?></textarea>
                            <span class="text-[10px] text-slate-500 font-sans block">যেসব প্রোডাক্টে Maintenance অন থাকবে, কাস্টমার সেগুলোতে চাপ দিলে এই রুলসগুলো পপআপে ভেসে উঠবে।</span>
                        </div>

                        <!-- SITE-WIDE MAINTENANCE -->
                        <div class="p-4 rounded-2xl border border-amber-200 bg-amber-50/60 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-slate-900 font-bold text-xs block">Site-Wide Maintenance Mode</span>
                                    <span class="text-[10px] text-slate-500 font-sans">সম্পূর্ণ ওয়েবসাইট বন্ধ করে আন্ডার মেইনটেন্যান্স করে রাখা।</span>
                                </div>
                                <input type="checkbox" name="site_maintenance_mode" value="1" <?= ($settings['site_maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?> class="rounded text-amber-500 scale-125">
                            </div>
                            <input type="text" name="site_maintenance_msg" value="<?= htmlspecialchars($settings['site_maintenance_msg'] ?? 'System upgrade in progress.') ?>" class="w-full bg-white border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none font-sans text-xs">
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase text-xs shadow-md transition active:scale-95">
                        Save Notice &amp; Rules
                    </button>
                </div>

                <!-- 4. SUPPORT LINKS SUB-PAGE -->
                <div id="set-view-support" class="settings-sub-view hidden box-panel p-5 space-y-4 border-slate-200">
                    <div class="pb-2 border-b border-slate-100">
                        <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-headset text-emerald-600"></i> Support &amp; Contact Channels
                        </h4>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">WhatsApp Number</label>
                            <input type="text" name="support_whatsapp" value="<?= htmlspecialchars($settings['support_whatsapp'] ?? '') ?>" placeholder="+8801829301851" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Telegram Support Link</label>
                            <input type="url" name="support_telegram" value="<?= htmlspecialchars($settings['support_telegram'] ?? '') ?>" placeholder="https://t.me/..." class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Facebook Messenger Link</label>
                            <input type="url" name="support_facebook" value="<?= htmlspecialchars($settings['support_facebook'] ?? '') ?>" placeholder="https://m.me/..." class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none focus:border-emerald-600">
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase text-xs shadow-md transition active:scale-95">
                        Save Support Channels
                    </button>
                </div>

                <!-- 5. GOOGLE OAUTH SUB-PAGE -->
                <div id="set-view-oauth" class="settings-sub-view hidden box-panel p-5 space-y-4 border-slate-200">
                    <div class="pb-2 border-b border-slate-100">
                        <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-brands fa-google text-emerald-600"></i> Google OAuth Integration
                        </h4>
                    </div>

                    <div class="grid grid-cols-1 gap-3">
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Google Client ID</label>
                            <input type="text" name="google_client_id" value="<?= htmlspecialchars($settings['google_client_id'] ?? '') ?>" placeholder="295135077225-...apps.googleusercontent.com" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none font-mono text-xs focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Google Client Secret</label>
                            <input type="text" name="google_client_secret" value="<?= htmlspecialchars($settings['google_client_secret'] ?? '') ?>" placeholder="GOCSPX-..." class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none font-mono text-xs focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="text-slate-700 block mb-1 font-bold">Google Redirect URI</label>
                            <input type="url" name="google_redirect_uri" value="<?= htmlspecialchars($settings['google_redirect_uri'] ?? '') ?>" placeholder="https://ffpanelsell.shop" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none font-mono text-xs focus:border-emerald-600">
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase text-xs shadow-md transition active:scale-95">
                        Save OAuth Settings
                    </button>
                </div>
            </form>

            <!-- 6. CAROUSEL BANNERS SUB-PAGE -->
            <div id="set-view-banners" class="settings-sub-view hidden box-panel p-5 space-y-4 border-slate-200 font-mono">
                <div class="pb-2 border-b border-slate-100">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">STORE CAROUSEL BANNERS</h4>
                </div>

                <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                    <input type="hidden" name="admin_action" value="add_banner">
                    <input type="hidden" name="active_tab" value="tab-settings">
                    <input type="file" name="banner_photo" accept="image/*" required class="text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:bg-emerald-600 file:text-white">
                    <input type="url" name="target_link" placeholder="Target Link (Optional)" class="bg-slate-50 border border-slate-200 p-2 rounded-xl text-slate-900 outline-none">
                    <button type="submit" class="py-2.5 bg-slate-900 text-white font-bold rounded-xl uppercase shadow active:scale-95 transition">+ Upload Banner</button>
                </form>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                    <?php foreach ($bannersList as $bn): ?>
                        <div class="relative bg-slate-100 rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                            <img src="<?= htmlspecialchars($bn['image_url']) ?>" class="w-full h-20 object-cover">
                            <form method="POST" class="absolute top-2 right-2" onsubmit="return confirm('Delete banner?');">
                                <input type="hidden" name="admin_action" value="delete_banner">
                                <input type="hidden" name="active_tab" value="tab-settings">
                                <input type="hidden" name="banner_id" value="<?= $bn['id'] ?>">
                                <button type="submit" class="bg-rose-600 text-white w-7 h-7 rounded-lg text-xs flex items-center justify-center shadow"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- JAVASCRIPT SUB-TAB SWITCHER -->
            <script>
                function switchSettingsSubTab(viewId, btn) {
                    document.querySelectorAll('.settings-sub-view').forEach(v => v.classList.add('hidden'));
                    document.querySelectorAll('.set-sub-pill').forEach(b => {
                        b.className = 'set-sub-pill py-2.5 px-3.5 rounded-xl bg-slate-100 text-slate-700 hover:text-slate-900 font-bold flex-shrink-0 flex items-center gap-1.5 transition';
                    });
                    
                    const target = document.getElementById(viewId);
                    if (target) target.classList.remove('hidden');
                    if (btn) btn.className = 'set-sub-pill py-2.5 px-3.5 rounded-xl bg-emerald-600 text-white font-black flex-shrink-0 flex items-center gap-1.5 transition shadow-sm';
                    
                    sessionStorage.setItem('admin_settings_active_subview', viewId);
                }

                document.addEventListener('DOMContentLoaded', () => {
                    const savedView = sessionStorage.getItem('admin_settings_active_subview') || 'set-view-branding';
                    const targetBtn = document.querySelector(`[onclick*="${savedView}"]`);
                    switchSettingsSubTab(savedView, targetBtn);
                });
            </script>
        </section>

        <!-- =============================================================== -->
        <!-- 15. PUSH NOTIFICATIONS TAB                                      -->
        <!-- =============================================================== -->
        <section id="tab-push" class="tab-section hidden space-y-5">
            <div class="box-panel p-5 space-y-4 border-slate-200">
                <div class="pb-3 border-b border-slate-100">
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider font-mono flex items-center gap-2">
                        <i class="fa-solid fa-paper-plane text-emerald-600"></i> BROADCAST ANNOUNCEMENTS
                    </h3>
                </div>

                <form method="POST" class="space-y-3.5 text-xs font-mono">
                    <input type="hidden" name="admin_action" value="send_push_notification">
                    <input type="hidden" name="active_tab" value="tab-push">

                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Target Audience</label>
                        <select name="target_type" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-800 outline-none focus:border-emerald-600">
                            <option value="all">Broadcast to All Users</option>
                            <option value="customers">Customers Only</option>
                            <option value="resellers">Resellers Only</option>
                        </select>
                    </div>

                    <div>
                        <input type="text" name="push_title" placeholder="Announcement Title" required class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 outline-none font-bold focus:border-emerald-600">
                    </div>

                    <div>
                        <textarea name="push_message" rows="3" placeholder="Message content..." required class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-900 outline-none font-sans focus:border-emerald-600"></textarea>
                    </div>

                    <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase tracking-wider text-xs shadow transition">
                        Broadcast Notification
                    </button>
                </form>

                <div class="space-y-3 pt-3 border-t border-slate-100 font-mono">
                    <h4 class="text-xs font-bold text-slate-400 uppercase">RECENT BROADCASTS:</h4>
                    <div class="space-y-2.5 max-h-64 overflow-y-auto">
                        <?php if (empty($sentNotifications)): ?>
                            <p class="text-center py-6 text-slate-400 text-xs font-sans">No broadcasts sent yet.</p>
                        <?php else: ?>
                            <?php foreach ($sentNotifications as $nt): ?>
                                <div class="stat-card flex items-start justify-between gap-3 text-xs">
                                    <div class="space-y-1">
                                        <strong class="text-slate-900 font-bold block"><?= htmlspecialchars($nt['title']) ?></strong>
                                        <p class="text-slate-600 text-[11px] font-sans"><?= htmlspecialchars($nt['message']) ?></p>
                                        <span class="text-[9px] text-slate-400 block"><?= $nt['created_at'] ?></span>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Delete notification?');">
                                        <input type="hidden" name="admin_action" value="delete_push_notification">
                                        <input type="hidden" name="active_tab" value="tab-push">
                                        <input type="hidden" name="notif_id" value="<?= $nt['id'] ?>">
                                        <button type="submit" class="text-rose-500 hover:text-rose-700 p-1.5">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- =============================================================== -->
        <!-- 16. THEME COLOR CUSTOMIZER TAB                                  -->
        <!-- =============================================================== -->
        <section id="tab-themecolor" class="tab-section hidden space-y-5 font-mono">
            <div class="box-panel p-5 space-y-5 border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-palette text-emerald-600"></i> STOREFRONT THEME COLOR
                        </h3>
                        <span class="text-[11px] text-slate-500 font-sans mt-0.5 block">
                            Change the primary brand accent color across your store.
                        </span>
                    </div>
                    <span class="w-7 h-7 rounded-xl border border-slate-300 shadow" style="background-color: <?= htmlspecialchars($primaryThemeColor) ?>;"></span>
                </div>

                <form method="POST" class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-4 text-xs">
                    <input type="hidden" name="admin_action" value="update_site_theme_color">
                    <input type="hidden" name="active_tab" value="tab-themecolor">

                    <div>
                        <label class="text-slate-700 block mb-2 font-bold">Pick Primary Accent Color (HEX)</label>
                        <div class="flex items-center space-x-3">
                            <input type="color" id="themeColorPicker" value="<?= htmlspecialchars($primaryThemeColor) ?>" onchange="document.getElementById('themeColorInput').value = this.value" class="w-14 h-12 bg-transparent border border-slate-200 rounded-xl cursor-pointer">
                            <input type="text" id="themeColorInput" name="theme_color" value="<?= htmlspecialchars($primaryThemeColor) ?>" oninput="document.getElementById('themeColorPicker').value = this.value" placeholder="#15803d" required class="w-40 bg-white border border-slate-200 p-3 rounded-xl text-slate-900 font-mono font-bold text-sm outline-none focus:border-emerald-600">
                        </div>
                    </div>

                    <div class="space-y-1.5 pt-1">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Palette Presets:</span>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" onclick="setThemePreset('#15803d')" class="px-3.5 py-1.5 rounded-lg text-white font-black text-xs" style="background:#15803d;">Emerald Green</button>
                            <button type="button" onclick="setThemePreset('#00f5ff')" class="px-3.5 py-1.5 rounded-lg text-slate-950 font-black text-xs" style="background:#00f5ff;">Cyan Blue</button>
                            <button type="button" onclick="setThemePreset('#8b5cf6')" class="px-3.5 py-1.5 rounded-lg text-white font-black text-xs" style="background:#8b5cf6;">Royal Violet</button>
                            <button type="button" onclick="setThemePreset('#f59e0b')" class="px-3.5 py-1.5 rounded-lg text-slate-950 font-black text-xs" style="background:#f59e0b;">Amber Gold</button>
                            <button type="button" onclick="setThemePreset('#ef4444')" class="px-3.5 py-1.5 rounded-lg text-white font-black text-xs" style="background:#ef4444;">Crimson Red</button>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase tracking-wider text-xs shadow transition mt-2">
                        Save Theme Color
                    </button>
                </form>
            </div>
        </section>

    </main>

    <!-- =============================================================== -->
    <!-- ALL MODAL WINDOWS                                               -->
    <!-- =============================================================== -->

    <!-- MODAL: ADD / EDIT PAYMENT METHOD -->
    <div id="paymentMethodModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 font-mono overflow-y-auto">
        <div class="box-panel p-5 w-full max-w-lg space-y-4 border-slate-200 my-6 text-xs">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                <h3 class="text-xs font-bold text-slate-900 uppercase flex items-center gap-1.5">
                    <i class="fa-solid fa-money-bill-wave text-emerald-600"></i>
                    <span id="paymentMethodModalTitle">CONFIGURE PAYMENT CHANNEL</span>
                </h3>
                <button type="button" onclick="closeModal('paymentMethodModal')" class="text-slate-400 hover:text-slate-700 p-1">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-3.5">
                <input type="hidden" name="admin_action" value="save_payment_method">
                <input type="hidden" name="active_tab" value="tab-gateways">
                <input type="hidden" id="editPmId" name="method_id" value="0">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Display Name</label>
                        <input type="text" id="editPmName" name="name" required placeholder="e.g. bKash" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none focus:border-emerald-600 font-bold">
                    </div>
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Method Key</label>
                        <input type="text" id="editPmKey" name="method_key" placeholder="bkash / nagad / rocket" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none uppercase font-bold">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Account Number</label>
                        <input type="text" id="editPmNumber" name="account_number" required placeholder="01700000000" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none focus:border-emerald-600 font-mono font-bold">
                    </div>
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Account Type</label>
                        <select id="editPmType" name="account_type" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                            <option value="Personal">Personal</option>
                            <option value="Agent">Agent</option>
                            <option value="Merchant">Merchant</option>
                            <option value="Pay ID">Pay ID (Binance)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="text-slate-700 block mb-1 font-bold">Payment Instructions</label>
                    <textarea id="editPmInstructions" name="instructions" rows="2" placeholder="Send money to the number above, then copy & paste TrxID below." class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none font-sans text-xs"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 bg-slate-50 p-3 rounded-2xl border border-slate-200">
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold text-[10px]">Small Logo</label>
                        <input type="file" name="logo_file" accept="image/*" class="w-full text-slate-500 file:mr-1 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[9px] file:bg-emerald-600 file:text-white">
                    </div>
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold text-[10px]">Payment Banner</label>
                        <input type="file" name="banner_file" accept="image/*" class="w-full text-slate-500 file:mr-1 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[9px] file:bg-emerald-600 file:text-white">
                    </div>
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold text-[10px]">QR Code Image</label>
                        <input type="file" name="qr_file" accept="image/*" class="w-full text-slate-500 file:mr-1 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[9px] file:bg-emerald-600 file:text-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 pt-1 border-t border-slate-100 items-center">
                    <label class="flex items-center space-x-1.5 cursor-pointer text-slate-800 font-bold text-[11px]">
                        <input type="checkbox" id="editPmIsActive" name="is_active" value="1" checked class="rounded scale-110 text-emerald-600">
                        <span>Active</span>
                    </label>
                    <label class="flex items-center space-x-1.5 cursor-pointer text-slate-800 font-bold text-[11px]">
                        <input type="checkbox" id="editPmAllowAddFund" name="allow_add_fund" value="1" checked class="rounded scale-110 text-emerald-600">
                        <span>Add Fund</span>
                    </label>
                    <label class="flex items-center space-x-1.5 cursor-pointer text-slate-800 font-bold text-[11px]">
                        <input type="checkbox" id="editPmAllowCheckout" name="allow_checkout" value="1" checked class="rounded scale-110 text-emerald-600">
                        <span>Checkout</span>
                    </label>
                    <div class="flex items-center space-x-1 justify-end">
                        <span class="text-slate-500 text-[10px]">Sort:</span>
                        <input type="number" id="editPmSort" name="sort_order" value="0" class="w-12 bg-slate-50 border border-slate-200 p-1 rounded-lg text-slate-900 font-mono text-center text-xs">
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl uppercase text-xs font-bold shadow">
                    Save Payment Channel
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL: SELECT SYNC (KEY MANAGER) -->
    <div id="selectSyncModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 font-mono">
        <div class="box-panel p-5 w-full max-w-md space-y-4 border-slate-200 max-h-[90vh] overflow-y-auto text-xs">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                <h3 class="text-xs font-black text-slate-900 uppercase flex items-center gap-1.5">
                    <i class="fa-solid fa-list-check text-emerald-600"></i> SELECT PRODUCTS TO IMPORT
                </h3>
                <button type="button" onclick="closeModal('selectSyncModal')" class="text-slate-400 hover:text-slate-700 p-1"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form method="POST" class="space-y-3.5">
                <input type="hidden" name="admin_action" value="sync_selected_km_products">
                <input type="hidden" name="active_tab" value="tab-products">

                <div class="flex justify-between items-center text-[11px]">
                    <span class="text-slate-500">Select plans from provider:</span>
                    <button type="button" onclick="toggleAllSyncCB(true)" class="text-emerald-700 underline font-bold">Select All</button>
                </div>

                <div id="selectSyncItemsContainer" class="space-y-2 max-h-64 overflow-y-auto bg-slate-50 p-3 rounded-2xl border border-slate-200">
                    <p class="text-center text-slate-500 py-6"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Fetching from Key Manager...</p>
                </div>

                <div class="grid grid-cols-2 gap-2.5 pt-1">
                    <button type="button" onclick="loadProviderCatalogForSelect()" class="py-2.5 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 font-bold uppercase text-[10px]">
                        Re-Fetch
                    </button>
                    <button type="submit" class="py-2.5 rounded-xl bg-emerald-600 text-white font-bold uppercase text-[10px]">
                        Import Selected
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: CONFIRM ALL DELETE -->
    <div id="allDeleteModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 font-mono">
        <div class="box-panel p-6 w-full max-w-sm space-y-4 text-center border-rose-200">
            <i class="fa-solid fa-triangle-exclamation text-3xl text-rose-500"></i>
            <h3 class="text-sm font-black text-slate-900 uppercase">Wipe Catalog?</h3>
            <p class="text-xs text-slate-500">All products and plans will be permanently purged from the database.</p>
            <form method="POST" class="space-y-2.5">
                <input type="hidden" name="admin_action" value="delete_all_products_confirmed">
                <input type="hidden" name="active_tab" value="tab-products">
                <button type="submit" class="w-full py-3 rounded-xl bg-rose-600 text-white font-bold uppercase text-xs">Delete All</button>
                <button type="button" onclick="closeModal('allDeleteModal')" class="w-full py-2.5 rounded-xl bg-slate-100 text-slate-600 text-xs font-bold">Cancel</button>
            </form>
        </div>
    </div>

    <!-- MODAL: PRODUCT EDITOR -->
    <div id="editProductModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 font-mono overflow-y-auto">
        <div class="box-panel p-5 w-full max-w-xl space-y-4 border-slate-200 my-6 text-xs">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                <h3 class="text-xs font-black text-slate-900 uppercase flex items-center gap-1.5">
                    <i class="fa-solid fa-pen text-emerald-600"></i> Edit Product &amp; Plans
                </h3>
                <button type="button" onclick="closeModal('editProductModal')" class="text-slate-400 hover:text-slate-700 p-1"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-3.5">
                <input type="hidden" name="admin_action" value="edit_product_full">
                <input type="hidden" name="active_tab" value="tab-products">
                <input type="hidden" id="editModalProdId" name="product_id">

                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                    <span class="text-slate-900 font-bold text-xs">Product Status</span>
                    <label class="flex items-center space-x-2 cursor-pointer font-bold text-xs">
                        <input type="checkbox" id="editModalIsActive" name="is_active" value="1" class="rounded scale-110 text-emerald-600">
                        <span id="editModalActiveText" class="text-emerald-700 font-mono">Online</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    <div class="sm:col-span-2">
                        <label class="text-slate-700 block mb-1">Product Title</label>
                        <input type="text" id="editModalName" name="name" required class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none font-bold">
                    </div>
                    <div>
                        <label class="text-slate-700 block mb-1">Category</label>
                        <select id="editModalCategory" name="category" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none font-bold">
                            <option value="NON ROOT">NON ROOT</option>
                            <option value="ROOT">ROOT</option>
                            <option value="I PHONE">I PHONE</option>
                            <option value="PC">PC</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div>
                        <label class="text-slate-700 block mb-1">Replace Logo</label>
                        <input type="file" name="product_logo" accept="image/*" class="w-full text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-[11px] file:bg-emerald-600 file:text-white">
                    </div>
                    <div>
                        <label class="text-slate-700 block mb-1">Replace Banner</label>
                        <input type="file" name="product_banner" accept="image/*" class="w-full text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-[11px] file:bg-emerald-600 file:text-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    <input type="url" id="editModalDownload" name="download_link" placeholder="Download Link" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none">
                    <input type="url" id="editModalSetup" name="setup_link" placeholder="Setup Tutorial Link" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none">
                    <input type="url" id="editModalAppDownload" name="app_download_url" placeholder="Direct APK URL" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none font-mono">
                </div>

                <div>
                    <input type="text" id="editModalTags" name="tags" placeholder="Tags: #SAFE, #INSTANT" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none">
                </div>

                <div>
                    <label class="text-slate-700 font-bold block mb-1">Product Description (বিবরণ)</label>
                    <textarea id="editModalDesc" name="description" rows="2" placeholder="Description..." class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none font-sans"></textarea>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-rose-600 uppercase block mb-1">Product Maintenance Notice &amp; Rules (মেইনটেন্যান্স রুলস)</label>
                    <textarea id="editModalMaintRules" name="maintenance_message" rows="3" placeholder="⚠️ Rules & Conditions:&#10;এই প্রোডাক্টটির সার্ভার আপগ্রেড চলছে..." class="w-full bg-rose-50/60 border border-rose-200 p-2.5 rounded-xl text-slate-900 outline-none font-sans text-xs focus:border-rose-500"></textarea>
                </div>

                <!-- Plans List in Editor (Removed Coins) -->
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <span class="text-slate-700 font-bold uppercase block text-xs">Plan Prices ($ USD):</span>
                    <div id="editModalPlansContainer" class="space-y-2 max-h-56 overflow-y-auto bg-slate-50 p-2.5 rounded-2xl border border-slate-200"></div>
                </div>

                <div class="flex items-center space-x-4 pt-2">
                    <label class="flex items-center space-x-2 cursor-pointer text-slate-700 text-xs font-bold">
                        <input type="checkbox" id="editModalIsPinned" name="is_pinned" value="1" class="rounded scale-110">
                        <span>Pin to Top</span>
                    </label>
                    <div class="flex-grow text-right">
                        <input type="number" id="editModalSortOrder" name="sort_order" class="w-16 bg-slate-50 border border-slate-200 p-2 rounded-xl text-slate-900 text-center font-bold" title="Sort Order">
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase text-xs shadow">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- MODAL: ADD SINGLE PLAN (Removed Coins) -->
    <div id="addPlanModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 font-mono">
        <div class="box-panel p-5 w-full max-w-sm space-y-3.5 border-slate-200 text-xs">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                <h3 class="text-xs font-black text-slate-900 uppercase">+ Attach Plan</h3>
                <button type="button" onclick="closeModal('addPlanModal')" class="text-slate-400 hover:text-slate-700 p-1"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="admin_action" value="add_single_plan">
                <input type="hidden" name="active_tab" value="tab-products">
                <input type="hidden" id="addPlanProdId" name="product_id">
                
                <div>
                    <label class="text-slate-700 block mb-1">Plan Title</label>
                    <input type="text" name="plan_title" required placeholder="e.g. 7 Days" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none">
                </div>
                
                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="text-slate-700 block mb-1">Price ($ USD)</label>
                        <input type="number" step="0.01" name="user_price" required placeholder="1.50" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                    </div>
                    <div>
                        <label class="text-slate-700 block mb-1">KM Plan ID</label>
                        <input type="number" name="api_plan_id" placeholder="0" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase text-xs shadow">Attach Plan</button>
            </form>
        </div>
    </div>

    <!-- MODAL: SPIN SEGMENT MODAL -->
    <div id="spinSegmentModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 font-mono">
        <div class="box-panel p-5 w-full max-w-md space-y-4 border-slate-200 text-xs">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                <h3 class="text-xs font-bold text-slate-900 uppercase flex items-center gap-2">
                    <i class="fa-solid fa-dharmachakra text-emerald-600"></i> <span id="spinSegmentModalTitle">SPIN SEGMENT</span>
                </h3>
                <button onclick="closeModal('spinSegmentModal')" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-base"></i></button>
            </div>

            <form method="POST" class="space-y-3.5">
                <input type="hidden" name="admin_action" value="save_spin_segment">
                <input type="hidden" name="active_tab" value="tab-spinwheel">
                <input type="hidden" id="editSegmentId" name="segment_id" value="0">

                <div>
                    <label class="text-slate-700 block mb-1 font-bold">Segment Title</label>
                    <input type="text" id="segTitleInput" name="title" placeholder="500 TK → 50 TK Bonus" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Spend Target (BDT)</label>
                        <input type="number" id="segTargetInput" name="spend_target" required placeholder="500" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                    </div>
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Bonus Amount (BDT)</label>
                        <input type="number" id="segBonusInput" name="bonus_amount" required placeholder="50" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Probability (%)</label>
                        <input type="number" id="segProbInput" name="probability" required placeholder="20" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                    </div>
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Color</label>
                        <input type="color" id="segColorInput" name="color" value="#16a34a" class="w-full h-11 bg-slate-50 border border-slate-200 p-1.5 rounded-xl cursor-pointer">
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase text-xs shadow">Save Segment</button>
            </form>
        </div>
    </div>

    <!-- MODAL: DEPOSIT BONUS / REWARD TIER MODAL -->
    <div id="depositBonusModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 font-mono">
        <div class="box-panel p-5 w-full max-w-md space-y-4 border-slate-200 text-xs">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                <h3 class="text-xs font-bold text-slate-900 uppercase flex items-center gap-2">
                    <i class="fa-solid fa-gift text-emerald-600"></i> <span id="depositBonusModalTitle">DEPOSIT REWARD TIER</span>
                </h3>
                <button onclick="closeModal('depositBonusModal')" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-base"></i></button>
            </div>

            <form method="POST" class="space-y-3.5">
                <input type="hidden" name="admin_action" value="save_deposit_bonus">
                <input type="hidden" name="active_tab" value="tab-deposits">
                <input type="hidden" id="editBonusId" name="bonus_id" value="0">

                <div>
                    <label class="text-slate-700 block mb-1 font-bold">Tier Title / Description</label>
                    <input type="text" id="bonusTitleInput" name="title" placeholder="e.g. 500 BDT Deposit Bonus" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Min Deposit (BDT)</label>
                        <input type="number" id="bonusMinDepositInput" name="min_deposit" required placeholder="500" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                    </div>
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Bonus Cash (BDT)</label>
                        <input type="number" step="0.01" id="bonusAmountInput" name="bonus_amount" required placeholder="50" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Bonus Type</label>
                        <select id="bonusTypeInput" name="bonus_type" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 outline-none font-bold">
                            <option value="fixed">Fixed BDT Amount</option>
                            <option value="percentage">Percentage (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Claim Limit / User</label>
                        <input type="number" id="bonusClaimLimitInput" name="claim_limit" value="0" min="0" placeholder="0 = Unlimited" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                    </div>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center space-x-2 text-slate-700 cursor-pointer">
                        <input type="checkbox" id="bonusIsActiveInput" name="is_active" value="1" checked class="rounded text-emerald-600 scale-125">
                        <span class="font-bold">Active / Enabled</span>
                    </label>
                    <div class="flex items-center space-x-1">
                        <span class="text-[10px] text-slate-400 font-bold">Sort:</span>
                        <input type="number" id="bonusSortOrderInput" name="sort_order" value="0" class="w-16 bg-slate-50 border border-slate-200 p-1.5 rounded-lg text-slate-900 font-mono text-center">
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase text-xs shadow">Save Reward Tier</button>
                </form>
            </div>
        </div>

    <!-- MODAL: USER ACCESS & CUSTOM PLAN PRICING -->
    <div id="userAccessModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 font-mono">
        <div class="box-panel p-5 w-full max-w-2xl space-y-4 border-slate-200 max-h-[92vh] overflow-y-auto text-xs">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-user-gear text-emerald-600 text-sm"></i>
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 uppercase"><span id="modalAccessUserName">User Settings</span></h3>
                        <span class="text-[10px] text-slate-400 font-sans block">Manage role &amp; wholesale plan prices</span>
                    </div>
                </div>
                <button onclick="closeModal('userAccessModal')" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-base"></i></button>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="admin_action" value="save_user_custom_pricing_and_access">
                <input type="hidden" id="modalAccessUserId" name="target_user_id">
                <input type="hidden" id="modalAccessActiveTab" name="active_tab" value="tab-customers">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-slate-700 block mb-1 font-bold">Account Role</label>
                        <select id="modalAccessUserRole" name="user_role" onchange="refreshUserCustomPricesList()" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none">
                            <option value="customer">Customer</option>
                            <option value="reseller">Reseller</option>
                        </select>
                    </div>

                    <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 flex items-center justify-between">
                        <div>
                            <span class="text-slate-900 block text-xs font-bold">Add Fund Button</span>
                            <span class="text-[9px] text-slate-500">Allow deposit screen</span>
                        </div>
                        <input type="checkbox" id="modalAccessCanAddFund" name="can_add_fund" value="1" class="rounded text-emerald-600 scale-125">
                    </div>
                </div>

                <div>
                    <label class="text-slate-700 block mb-1 font-bold">Product Permissions</label>
                    <div class="grid grid-cols-2 gap-2 bg-slate-50 p-3 rounded-2xl border border-slate-200 max-h-36 overflow-y-auto">
                        <?php foreach ($productsData as $pd): ?>
                            <label class="flex items-center space-x-2 text-[11px] text-slate-700 cursor-pointer">
                                <input type="checkbox" name="allowed_products[]" value="<?= $pd['id'] ?>" class="user-prod-checkbox rounded text-emerald-600 scale-110">
                                <span class="truncate"><?= htmlspecialchars($pd['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label class="text-emerald-700 block mb-1 font-bold">Product-Wise Plans Custom Pricing ($ USD)</label>
                    <div id="userCustomPlansListContainer" class="space-y-3 max-h-60 overflow-y-auto bg-slate-50 p-3 rounded-2xl border border-slate-200"></div>
                </div>

                <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl uppercase text-xs shadow">Save Permissions &amp; Pricing</button>
            </form>
        </div>
    </div>

    <!-- MODAL: MANAGE BALANCE -->
    <div id="balanceModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 font-mono">
        <div class="box-panel p-5 w-full max-w-sm space-y-4 border-slate-200 text-xs">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                <h3 class="text-xs font-bold text-slate-900 uppercase flex items-center gap-1.5">
                    <i class="fa-solid fa-user-gear text-emerald-600"></i> Manage Float
                </h3>
                <button type="button" onclick="closeModal('balanceModal')" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-1.5">
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Username:</span>
                    <strong id="balanceModalUsername" class="text-slate-900 text-xs"></strong>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Float:</span>
                    <strong id="balanceModalCurrent" class="text-emerald-700 font-black text-sm"></strong>
                </div>
            </div>

            <form method="POST" class="space-y-3">
                <input type="hidden" name="admin_action" value="modify_balance">
                <input type="hidden" id="balanceModalActiveTab" name="active_tab" value="tab-customers">
                <input type="hidden" id="balanceModalUserId" name="user_id">

                <div>
                    <label class="text-slate-700 block mb-1 font-bold">Wallet Amount ($ USD)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" placeholder="5.00" required class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-900 font-bold outline-none text-xs">
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <button type="submit" name="type" value="add" class="py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition">+ Add Float</button>
                    <button type="submit" name="type" value="deduct" class="py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold transition">- Deduct Float</button>
                </div>
            </form>

            <form method="POST" class="pt-3 border-t border-slate-100">
                <input type="hidden" name="admin_action" value="toggle_ban">
                <input type="hidden" id="banModalActiveTab" name="active_tab" value="tab-customers">
                <input type="hidden" id="banModalUserId" name="user_id">
                <input type="hidden" id="banModalStatus" name="status">
                <button type="submit" id="banModalBtn" class="w-full py-2.5 rounded-xl text-xs font-bold uppercase transition">Toggle Status</button>
            </form>
        </div>
    </div>

    <!-- =============================================================== -->
    <!-- COMPLETE JAVASCRIPT CONTROLLER ENGINE                           -->
    <!-- =============================================================== -->
    <script>
        const globalProducts = <?= json_encode($productsData) ?>;
        const currentBdtRate = <?= $effectiveBdtRate ?>;
        const userCustomPricesMap = <?= json_encode($userCustomPricesMap) ?>;
        const userAllowedProdsMap = <?= json_encode($userAllowedProdsMap) ?>;
        let currentActiveUser = null;

        function toggleAdminDrawer() {
            document.getElementById('adminDrawer').classList.toggle('translate-x-full');
            document.getElementById('drawerOverlay').classList.toggle('hidden');
        }

        function switchAdminTab(sectionId, titleName = '', btnEl = null) {
            document.querySelectorAll('.tab-section').forEach(sec => sec.classList.add('hidden'));
            const target = document.getElementById(sectionId);
            if (target) target.classList.remove('hidden');

            if (titleName) document.getElementById('headerSectionTitle').innerText = titleName;

            document.querySelectorAll('.drawer-nav-btn').forEach(btn => btn.classList.remove('active'));
            const activeBtn = btnEl || document.getElementById('btn-nav-' + sectionId);
            if (activeBtn) activeBtn.classList.add('active');

            sessionStorage.setItem('admin_active_tab', sectionId);
            sessionStorage.setItem('admin_active_title', titleName);

            if (!document.getElementById('adminDrawer').classList.contains('translate-x-full')) {
                toggleAdminDrawer();
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Coupon Form Helpers
        function togglePromoProductDropdown(val) {
            const row = document.getElementById('promoSpecificProductRow');
            if (val === 'specific') {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        }

        function openEditPromoModal(pc) {
            document.getElementById('editPromoId').value = pc.id;
            document.getElementById('promoCodeInput').value = pc.code;
            document.getElementById('promoDiscountInput').value = pc.discount_percent;
            document.getElementById('promoTargetTypeSelect').value = pc.target_type;
            togglePromoProductDropdown(pc.target_type);
            document.getElementById('promoProductIdSelect').value = pc.product_id || 0;
            document.getElementById('promoMaxUsesInput').value = pc.max_uses;
            if (pc.expires_at) {
                document.getElementById('promoExpiresAtInput').value = pc.expires_at.replace(' ', 'T').substring(0, 16);
            } else {
                document.getElementById('promoExpiresAtInput').value = '';
            }
            document.getElementById('promoIsActiveInput').checked = (parseInt(pc.is_active) === 1);
            document.getElementById('btnSubmitPromo').innerText = 'Update Coupon';
            window.scrollTo({ top: document.getElementById('promoCodeInput').offsetTop - 100, behavior: 'smooth' });
        }

        function resetPromoForm() {
            document.getElementById('editPromoId').value = '0';
            document.getElementById('promoCodeInput').value = '';
            document.getElementById('promoDiscountInput').value = '10';
            document.getElementById('promoTargetTypeSelect').value = 'all';
            togglePromoProductDropdown('all');
            document.getElementById('promoProductIdSelect').value = '0';
            document.getElementById('promoMaxUsesInput').value = '100';
            document.getElementById('promoExpiresAtInput').value = '';
            document.getElementById('promoIsActiveInput').checked = true;
            document.getElementById('btnSubmitPromo').innerText = 'Save Coupon';
        }

        // Gateways Sub View
        function switchGatewaySubView(viewId) {
            document.getElementById('view-pm-methods').classList.add('hidden');
            document.getElementById('view-pm-rules').classList.add('hidden');

            document.getElementById('pill-gw-methods').className = "py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition";
            document.getElementById('pill-gw-rules').className = "py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition";

            document.getElementById(viewId).classList.remove('hidden');

            if (viewId === 'view-pm-methods') {
                document.getElementById('pill-gw-methods').className = "py-3 px-3 rounded-xl bg-emerald-600 text-white font-black flex items-center justify-center gap-2 shadow-sm transition";
            } else {
                document.getElementById('pill-gw-rules').className = "py-3 px-3 rounded-xl bg-emerald-600 text-white font-black flex items-center justify-center gap-2 shadow-sm transition";
            }
        }

        function openAddPaymentMethodModal() {
            document.getElementById('paymentMethodModalTitle').innerText = 'ADD PAYMENT CHANNEL';
            document.getElementById('editPmId').value = '0';
            document.getElementById('editPmName').value = '';
            document.getElementById('editPmKey').value = '';
            document.getElementById('editPmNumber').value = '';
            document.getElementById('editPmType').value = 'Personal';
            document.getElementById('editPmInstructions').value = 'Send money to the number above, then copy & paste TrxID below.';
            document.getElementById('editPmIsActive').checked = true;
            document.getElementById('editPmAllowAddFund').checked = true;
            document.getElementById('editPmAllowCheckout').checked = true;
            document.getElementById('editPmSort').value = '0';
            document.getElementById('paymentMethodModal').classList.remove('hidden');
        }

        function openEditPaymentMethodModal(pm) {
            document.getElementById('paymentMethodModalTitle').innerText = 'EDIT PAYMENT CHANNEL: ' + (pm.name || '');
            document.getElementById('editPmId').value = pm.id || 0;
            document.getElementById('editPmName').value = pm.name || '';
            document.getElementById('editPmKey').value = pm.method_key || '';
            document.getElementById('editPmNumber').value = pm.account_number || '';
            document.getElementById('editPmType').value = pm.account_type || 'Personal';
            document.getElementById('editPmInstructions').value = pm.instructions || '';
            document.getElementById('editPmIsActive').checked = (parseInt(pm.is_active) === 1);
            document.getElementById('editPmAllowAddFund').checked = (parseInt(pm.allow_add_fund !== undefined ? pm.allow_add_fund : 1) === 1);
            document.getElementById('editPmAllowCheckout').checked = (parseInt(pm.allow_checkout !== undefined ? pm.allow_checkout : 1) === 1);
            document.getElementById('editPmSort').value = pm.sort_order || 0;
            document.getElementById('paymentMethodModal').classList.remove('hidden');
        }

        // Discount Hub Sub Tabs
        function switchDiscountSubTab(viewId) {
            document.getElementById('disc-view-products').classList.add('hidden');
            document.getElementById('disc-view-customers').classList.add('hidden');
            document.getElementById('disc-view-resellers').classList.add('hidden');

            document.getElementById('pill-disc-prods').className = "py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition";
            document.getElementById('pill-disc-custs').className = "py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition";
            document.getElementById('pill-disc-resells').className = "py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition";

            document.getElementById(viewId).classList.remove('hidden');

            if (viewId === 'disc-view-products') {
                document.getElementById('pill-disc-prods').className = "py-3 px-3 rounded-xl bg-emerald-600 text-white font-black flex items-center justify-center gap-2 shadow-sm transition";
            } else if (viewId === 'disc-view-customers') {
                document.getElementById('pill-disc-custs').className = "py-3 px-3 rounded-xl bg-emerald-600 text-white font-black flex items-center justify-center gap-2 shadow-sm transition";
            } else if (viewId === 'disc-view-resellers') {
                document.getElementById('pill-disc-resells').className = "py-3 px-3 rounded-xl bg-emerald-600 text-white font-black flex items-center justify-center gap-2 shadow-sm transition";
            }
            sessionStorage.setItem('disc_active_subtab', viewId);
        }

        function filterDiscProductPlans() {
            const query = document.getElementById('discPlanSearchInput').value.trim().toLowerCase();
            document.querySelectorAll('.disc-plan-row').forEach(r => {
                const kw = r.getAttribute('data-keywords') || '';
                if (kw.includes(query)) r.classList.remove('hidden');
                else r.classList.add('hidden');
            });
        }

        function switchProductSubView(viewId) {
            document.getElementById('view-catalog').classList.add('hidden');
            document.getElementById('view-add').classList.add('hidden');
            document.getElementById('view-stock').classList.add('hidden');

            document.getElementById('pill-catalog').className = "py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition";
            document.getElementById('pill-add').className = "py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition";
            document.getElementById('pill-stock').className = "py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition";

            document.getElementById(viewId).classList.remove('hidden');

            if (viewId === 'view-catalog') {
                document.getElementById('pill-catalog').className = "py-3 px-3 rounded-xl bg-emerald-600 text-white font-black flex items-center justify-center gap-2 shadow-sm transition";
                document.getElementById('catalogActionsBox').classList.remove('hidden');
            } else if (viewId === 'view-add') {
                document.getElementById('pill-add').className = "py-3 px-3 rounded-xl bg-emerald-600 text-white font-black flex items-center justify-center gap-2 shadow-sm transition";
                document.getElementById('catalogActionsBox').classList.add('hidden');
            } else if (viewId === 'view-stock') {
                document.getElementById('pill-stock').className = "py-3 px-3 rounded-xl bg-amber-500 text-slate-950 font-black flex items-center justify-center gap-2 shadow-sm transition";
                document.getElementById('catalogActionsBox').classList.add('hidden');
            }
        }

        // ---- Deposits history: filter + search + key lookup ----
        let trxActiveFilter = 'all';
        let keyResultsCache = [];

        function toggleRefCoinTaka() {
            const isConvert = document.getElementById('refCoinRuleType').value === 'convert';
            document.getElementById('refCoinTakaWrap').classList.toggle('hidden', !isConvert);
            document.getElementById('refCoinTakaInput').required = isConvert;
        }

        function setTrxFilter(f) {
            trxActiveFilter = f;
            document.querySelectorAll('.trx-filter-btn').forEach(b => {
                const on = b.dataset.f === f;
                b.classList.toggle('bg-slate-900', on);
                b.classList.toggle('text-white', on);
                b.classList.toggle('border-slate-900', on);
                b.classList.toggle('bg-white', !on);
            });
            applyTrxFilters();
        }

        function applyTrxFilters() {
            const q = (document.getElementById('trxSearchInput').value || '').trim().toLowerCase();
            let shown = 0;
            document.querySelectorAll('#trxTbody tr.trx-row').forEach(tr => {
                const okStatus = (trxActiveFilter === 'all') || tr.dataset.status === trxActiveFilter;
                const okSearch = !q || (tr.dataset.search || '').indexOf(q) !== -1;
                const show = okStatus && okSearch;
                tr.style.display = show ? '' : 'none';
                if (show) shown++;
            });
            let empty = document.getElementById('trxNoMatchRow');
            if (!empty) {
                empty = document.createElement('tr');
                empty.id = 'trxNoMatchRow';
                empty.innerHTML = '<td colspan="7" class="py-8 text-center text-slate-400 font-sans">No matching transactions.</td>';
                document.getElementById('trxTbody').appendChild(empty);
            }
            empty.style.display = (shown === 0 && document.querySelector('#trxTbody tr.trx-row')) ? '' : 'none';
        }

        function escHtml(v) {
            return String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        async function runKeySearch() {
            const q = (document.getElementById('trxSearchInput').value || '').trim();
            const panel = document.getElementById('keySearchPanel');
            const box = document.getElementById('keySearchResults');
            if (q.length < 2) { alert('Type at least 2 characters.'); return; }
            panel.classList.remove('hidden');
            box.innerHTML = '<p class="text-center text-[11px] text-slate-500 py-3"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Searching...</p>';

            const fd = new FormData();
            fd.append('admin_action', 'search_keys');
            fd.append('q', q);
            try {
                const res = await fetch('admin.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.status !== 'success') { box.innerHTML = '<p class="text-center text-[11px] text-rose-600 py-3">' + escHtml(data.message || 'Search failed') + '</p>'; return; }
                keyResultsCache = data.rows || [];
                renderKeyResults();
            } catch (e) {
                box.innerHTML = '<p class="text-center text-[11px] text-rose-600 py-3">Network error.</p>';
            }
        }

        function renderKeyResults() {
            const box = document.getElementById('keySearchResults');
            if (!keyResultsCache.length) {
                box.innerHTML = '<p class="text-center text-[11px] text-slate-500 py-3">No key / user found.</p>';
                return;
            }
            box.innerHTML = keyResultsCache.map((r, i) => {
                const stopped = r.key_status === 'stopped';
                const banned  = r.user_status === 'banned';
                return `
                <div class="bg-white border border-slate-200 rounded-xl p-2.5 space-y-1.5 text-[11px]">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-mono font-bold text-emerald-700 break-all select-all">${escHtml(r.license_key)}</div>
                            <div class="text-slate-500">${escHtml(r.validity || '')} • ${escHtml(r.payment_type || '')} • ${escHtml(r.created_at || '')}</div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase ${stopped ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-700'}">${stopped ? 'STOPPED' : 'ACTIVE'}</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-600">
                        <span><b class="text-slate-900">${escHtml(r.username || 'Guest')}</b> ${r.email ? '• ' + escHtml(r.email) : ''}</span>
                        ${banned ? '<span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-rose-100 text-rose-600">BANNED</span>' : ''}
                    </div>
                    <div class="flex gap-1.5 pt-0.5">
                        <button type="button" onclick="doKeyAction(${i}, '${stopped ? 'resume_key' : 'stop_key'}')" class="flex-1 py-1.5 rounded-lg text-[10px] font-black uppercase ${stopped ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white'}">${stopped ? 'Resume Key' : 'Stop Key'}</button>
                        ${r.user_id ? `<button type="button" onclick="doKeyAction(${i}, '${banned ? 'unban_user' : 'ban_user'}')" class="flex-1 py-1.5 rounded-lg text-[10px] font-black uppercase ${banned ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'}">${banned ? 'Unban Account' : 'Ban Account'}</button>` : ''}
                    </div>
                </div>`;
            }).join('');
        }

        async function doKeyAction(idx, op) {
            const r = keyResultsCache[idx];
            if (!r) return;
            const isUser = (op === 'ban_user' || op === 'unban_user');
            const label = { stop_key: 'STOP this key', resume_key: 'RESUME this key', ban_user: 'BAN this account', unban_user: 'UNBAN this account' }[op];
            if (!confirm('Confirm: ' + label + '?')) return;

            const fd = new FormData();
            fd.append('admin_action', 'key_action');
            fd.append('op', op);
            fd.append('id', isUser ? r.user_id : r.id);
            try {
                const res = await fetch('admin.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.status !== 'success') { alert(data.message || 'Failed'); return; }
                if (isUser) {
                    keyResultsCache.forEach(x => { if (x.user_id === r.user_id) x.user_status = data.user_status; });
                } else {
                    r.key_status = data.key_status;
                    if (data.remote === false) alert('Key marked in panel, but Key Manager remote stop failed.');
                }
                renderKeyResults();
            } catch (e) { alert('Network error.'); }
        }

        function switchDepositSubView(viewId) {
            document.getElementById('dep-verify-sec').classList.add('hidden');
            document.getElementById('dep-rewards-sec').classList.add('hidden');

            document.getElementById('pill-dep-verify').className = "py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition";
            document.getElementById('pill-dep-rewards').className = "py-3 px-3 rounded-xl text-slate-500 hover:text-slate-900 flex items-center justify-center gap-2 transition";

            document.getElementById(viewId).classList.remove('hidden');

            if (viewId === 'dep-verify-sec') {
                document.getElementById('pill-dep-verify').className = "py-3 px-3 rounded-xl bg-emerald-600 text-white font-black flex items-center justify-center gap-2 shadow-sm transition";
            } else {
                document.getElementById('pill-dep-rewards').className = "py-3 px-3 rounded-xl bg-amber-500 text-slate-950 font-black flex items-center justify-center gap-2 shadow-sm transition";
            }
        }

        function togglePlanAccordion(prodId) {
            const el = document.getElementById('plan-accordion-' + prodId);
            const arrow = document.getElementById('accordion-arrow-' + prodId);
            if (el) el.classList.toggle('hidden');
            if (arrow) arrow.classList.toggle('rotate-180');
        }

        function toggleKmKeyVisibility() {
            const input = document.getElementById('kmApiKeyInput');
            const icon = document.getElementById('kmKeyEyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fa-solid fa-eye-slash text-xs';
            } else {
                input.type = 'password';
                icon.className = 'fa-solid fa-eye text-xs';
            }
        }

        function openSelectSyncModal() {
            document.getElementById('selectSyncModal').classList.remove('hidden');
            loadProviderCatalogForSelect();
        }

        function loadProviderCatalogForSelect() {
            const container = document.getElementById('selectSyncItemsContainer');
            container.innerHTML = '<p class="text-center text-slate-500 py-6"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Fetching from Key Manager...</p>';

            fetch('admin.php?action=fetch_km_catalog')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.items && data.items.length > 0) {
                    container.innerHTML = '';
                    data.items.forEach(p => {
                        const itemTitle = p.product_name + ' (' + p.plan_title + ')';
                        const price = parseFloat(p.price || 0).toFixed(2);
                        const bdtPrice = Math.round(parseFloat(price) * currentBdtRate);

                        const row = document.createElement('label');
                        row.className = 'flex items-center justify-between p-3 rounded-xl bg-white hover:bg-slate-100 cursor-pointer text-xs transition border border-slate-200';
                        row.innerHTML = `
                            <div class="flex items-center space-x-2.5">
                                <input type="checkbox" name="selected_api_ids[]" value="${p.id}" class="sync-item-cb rounded text-emerald-600 scale-110">
                                <div>
                                    <strong class="text-slate-900">${itemTitle}</strong>
                                    <span class="text-[10px] text-slate-400 block font-mono">Plan ID: #${p.id}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-emerald-700 font-bold block">৳${bdtPrice}</span>
                                <span class="text-[9px] text-slate-400 font-mono">($${price} USD)</span>
                            </div>
                        `;
                        container.appendChild(row);
                    });
                } else {
                    container.innerHTML = `<div class="p-4 text-center text-rose-500 text-xs">${data.error || 'No items received.'}</div>`;
                }
            })
            .catch(() => {
                container.innerHTML = '<div class="p-4 text-center text-rose-500 text-xs">Failed to connect to Key Manager API.</div>';
            });
        }

        function toggleAllSyncCB(status) {
            document.querySelectorAll('.sync-item-cb').forEach(cb => cb.checked = status);
        }

        function confirmDeleteAllProducts() {
            document.getElementById('allDeleteModal').classList.remove('hidden');
        }

        function openProductEditor(prodInput) {
            let prod = null;
            if (typeof prodInput === 'object') {
                prod = prodInput;
            } else {
                prod = globalProducts.find(p => parseInt(p.id) === parseInt(prodInput));
            }

            if (!prod) {
                alert("Product data not found!");
                return;
            }

            document.getElementById('editModalProdId').value = prod.id;
            document.getElementById('editModalName').value = prod.name || '';
            document.getElementById('editModalSortOrder').value = prod.sort_order || 0;
            document.getElementById('editModalDownload').value = prod.download_link || '';
            document.getElementById('editModalSetup').value = prod.setup_link || '';
            document.getElementById('editModalAppDownload').value = prod.app_download_url || '';
            document.getElementById('editModalTags').value = prod.tags || '';
            document.getElementById('editModalDesc').value = prod.description || '';

            const maintInput = document.getElementById('editModalMaintRules');
            if (maintInput) maintInput.value = prod.maintenance_message || '';

            document.getElementById('editModalIsPinned').checked = (parseInt(prod.is_pinned) === 1);

            const allowedCategories = ['NON ROOT', 'ROOT', 'I PHONE', 'PC', 'Other'];
            let catVal = (prod.category || 'NON ROOT').trim();
            if (!allowedCategories.includes(catVal)) {
                catVal = 'NON ROOT';
            }
            document.getElementById('editModalCategory').value = catVal;

            const isActive = (parseInt(prod.is_active) === 1);
            const activeCb = document.getElementById('editModalIsActive');
            activeCb.checked = isActive;
            document.getElementById('editModalActiveText').innerText = isActive ? 'Online' : 'In Maintenance';

            activeCb.onchange = function() {
                document.getElementById('editModalActiveText').innerText = this.checked ? 'Online' : 'In Maintenance';
            };

            // PLANS LIST WITH STOCK OUT TOGGLE
            const plansBox = document.getElementById('editModalPlansContainer');
            plansBox.innerHTML = '';
            if (prod.plans && prod.plans.length > 0) {
                prod.plans.forEach(pl => {
                    const row = document.createElement('div');
                    row.className = "p-3 rounded-xl bg-white border border-slate-200 space-y-2 text-xs shadow-sm";
                    const bdt = Math.round(parseFloat(pl.user_price) * currentBdtRate);
                    const isStockOut = (parseInt(pl.is_out_of_stock) === 1);

                    row.innerHTML = `
                        <div class="flex items-center justify-between">
                            <div>
                                <strong class="text-slate-900 truncate text-xs block">${pl.plan_title}</strong>
                                <span class="text-[9px] text-slate-400 font-mono">KM ID #${pl.api_plan_id || 0}</span>
                            </div>
                            
                            <!-- STOCK OUT / IN TOGGLE -->
                            <label class="flex items-center space-x-1.5 cursor-pointer px-2.5 py-1 rounded-lg border text-[11px] font-black transition ${isStockOut ? 'bg-rose-50 border-rose-300 text-rose-600' : 'bg-emerald-50 border-emerald-300 text-emerald-700'}">
                                <input type="checkbox" name="plan_out_of_stock[${pl.id}]" value="1" ${isStockOut ? 'checked' : ''} onchange="this.parentElement.className = this.checked ? 'flex items-center space-x-1.5 cursor-pointer px-2.5 py-1 rounded-lg border text-[11px] font-black transition bg-rose-50 border-rose-300 text-rose-600' : 'flex items-center space-x-1.5 cursor-pointer px-2.5 py-1 rounded-lg border text-[11px] font-black transition bg-emerald-50 border-emerald-300 text-emerald-700'; this.nextElementSibling.innerText = this.checked ? 'Stock Out' : 'In Stock';" class="rounded scale-110">
                                <span>${isStockOut ? 'Stock Out' : 'In Stock'}</span>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 border-t border-slate-100">
                            <div>
                                <label class="text-[9px] text-slate-500 block font-bold">Price ($ USD)</label>
                                <input type="number" step="0.01" name="plan_user_price[${pl.id}]" value="${parseFloat(pl.user_price).toFixed(2)}" class="w-full bg-slate-50 border border-slate-200 p-1.5 rounded-lg text-emerald-700 font-mono font-bold text-xs outline-none">
                                <span class="text-[9px] text-slate-400 font-mono">≈ ৳${bdt} BDT</span>
                            </div>
                            <div>
                                <label class="text-[9px] text-slate-500 block font-bold">Wholesale Reseller Price</label>
                                <input type="number" step="0.01" name="plan_reseller_price[${pl.id}]" value="${parseFloat(pl.reseller_price || 0).toFixed(2)}" class="w-full bg-slate-50 border border-slate-200 p-1.5 rounded-lg text-slate-900 font-mono text-xs outline-none">
                            </div>
                        </div>
                    `;
                    plansBox.appendChild(row);
                });
            } else {
                plansBox.innerHTML = '<p class="text-slate-400 text-center py-3 text-xs">No plans attached.</p>';
            }

            document.getElementById('editProductModal').classList.remove('hidden');
        }

        function openAddPlanModal(prodId, prodName) {
            document.getElementById('addPlanProdId').value = prodId;
            document.getElementById('addPlanModal').classList.remove('hidden');
        }

        function onStockProdChange() {
            const prodId = parseInt(document.getElementById('stockProdSelect').value);
            const planSelect = document.getElementById('stockPlanSelect');
            planSelect.innerHTML = '<option value="">-- Choose Plan --</option>';

            const matched = globalProducts.find(p => parseInt(p.id) === prodId);
            if (matched && matched.plans) {
                matched.plans.forEach(pl => {
                    const opt = document.createElement('option');
                    opt.value = pl.id;
                    opt.innerText = `${pl.plan_title} (Stock: ${pl.stock_count || 0})`;
                    planSelect.appendChild(opt);
                });
            }
        }

        function filterUserList(role) {
            const inputId = (role === 'customer') ? 'customerSearchInput' : 'resellerSearchInput';
            const containerId = (role === 'customer') ? 'customerListContainer' : 'resellerListContainer';
            const val = document.getElementById(inputId).value.trim().toLowerCase();
            const cards = document.getElementById(containerId).querySelectorAll('.user-item-card');

            cards.forEach(c => {
                const u = c.getAttribute('data-username') || '';
                const e = c.getAttribute('data-email') || '';
                if (u.includes(val) || e.includes(val)) {
                    c.classList.remove('hidden');
                } else {
                    c.classList.add('hidden');
                }
            });
        }

        function openUserAccessModal(u) {
            currentActiveUser = u;
            document.getElementById('modalAccessUserId').value = u.id;
            document.getElementById('modalAccessUserName').innerText = u.username + " (ID: #" + u.id + ")";
            document.getElementById('modalAccessUserRole').value = u.role || 'customer';
            document.getElementById('modalAccessActiveTab').value = (u.role === 'reseller') ? 'tab-resellers' : 'tab-customers';
            document.getElementById('modalAccessCanAddFund').checked = (parseInt(u.can_add_fund !== undefined ? u.can_add_fund : 1) === 1);

            const allowed = userAllowedProdsMap[u.id] || [];
            document.querySelectorAll('.user-prod-checkbox').forEach(cb => {
                cb.checked = allowed.includes(parseInt(cb.value));
            });

            refreshUserCustomPricesList();
            document.getElementById('userAccessModal').classList.remove('hidden');
        }

        function refreshUserCustomPricesList() {
            if (!currentActiveUser) return;
            const u = currentActiveUser;
            const role = document.getElementById('modalAccessUserRole').value;
            const container = document.getElementById('userCustomPlansListContainer');
            container.innerHTML = '';

            const cPrices = userCustomPricesMap[u.id] || {};

            globalProducts.forEach(prod => {
                if (prod.plans && prod.plans.length > 0) {
                    const prodHeader = document.createElement('div');
                    prodHeader.className = "text-[10px] text-slate-700 font-bold uppercase tracking-wider pt-2 border-t border-slate-200 flex items-center justify-between";
                    prodHeader.innerHTML = `<span>${prod.name}</span><span class="text-[9px] text-slate-400">${prod.plans.length} Plans</span>`;
                    container.appendChild(prodHeader);

                    prod.plans.forEach(pl => {
                        const baseRegularPrice = (role === 'reseller' && parseFloat(pl.reseller_price) > 0) 
                                                    ? parseFloat(pl.reseller_price) 
                                                    : (parseFloat(pl.regular_price) > 0 ? parseFloat(pl.regular_price) : parseFloat(pl.user_price));
                        
                        const hasCustom = (cPrices[pl.id] !== undefined && cPrices[pl.id] !== null && cPrices[pl.id] !== '' && parseFloat(cPrices[pl.id]) > 0);
                        const curPrice = hasCustom ? parseFloat(cPrices[pl.id]) : baseRegularPrice;

                        const row = document.createElement('div');
                        row.className = "flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-2.5 rounded-xl bg-white border border-slate-200";
                        row.innerHTML = `
                            <div>
                                <span class="text-slate-900 font-bold text-xs block">${pl.plan_title}</span>
                                <span class="text-[10px] text-slate-400 font-mono">Regular: $${baseRegularPrice.toFixed(2)}</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="text-[10px] text-slate-500 font-bold">$ Price:</span>
                                <input type="number" step="0.01" min="0" name="custom_plan_price[${pl.id}]" 
                                       value="${hasCustom ? curPrice.toFixed(2) : ''}" 
                                       placeholder="${baseRegularPrice.toFixed(2)}" 
                                       class="w-20 bg-slate-50 border border-slate-200 p-1.5 rounded-lg text-emerald-700 font-bold text-right outline-none text-xs">
                            </div>
                        `;
                        container.appendChild(row);
                    });
                }
            });
        }

        function openBalanceModal(id, username, email, balance, status) {
            document.getElementById('balanceModalUserId').value = id;
            document.getElementById('banModalUserId').value = id;
            document.getElementById('banModalStatus').value = status;
            document.getElementById('balanceModalUsername').innerText = username;
            document.getElementById('balanceModalCurrent').innerText = '$' + parseFloat(balance).toFixed(2);
            
            const banBtn = document.getElementById('banModalBtn');
            if (status === 'active') {
                banBtn.className = "w-full py-2.5 rounded-xl text-xs font-bold uppercase transition bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100";
                banBtn.innerText = "Ban Account";
            } else {
                banBtn.className = "w-full py-2.5 rounded-xl text-xs font-bold uppercase transition bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100";
                banBtn.innerText = "Activate Account";
            }

            document.getElementById('balanceModal').classList.remove('hidden');
        }

        function openAddSpinSegmentModal() {
            document.getElementById('spinSegmentModalTitle').innerText = 'ADD SPIN SEGMENT';
            document.getElementById('editSegmentId').value = '0';
            document.getElementById('segTitleInput').value = '';
            document.getElementById('segTargetInput').value = '500';
            document.getElementById('segBonusInput').value = '50';
            document.getElementById('segProbInput').value = '20';
            document.getElementById('segColorInput').value = '#16a34a';
            document.getElementById('spinSegmentModal').classList.remove('hidden');
        }

        function openEditSpinSegmentModal(seg) {
            document.getElementById('spinSegmentModalTitle').innerText = 'EDIT SPIN SEGMENT';
            document.getElementById('editSegmentId').value = seg.id;
            document.getElementById('segTitleInput').value = seg.title || '';
            document.getElementById('segTargetInput').value = seg.spend_target;
            document.getElementById('segBonusInput').value = seg.bonus_amount;
            document.getElementById('segProbInput').value = seg.probability;
            document.getElementById('segColorInput').value = seg.color || '#16a34a';
            document.getElementById('spinSegmentModal').classList.remove('hidden');
        }

        function openAddDepositBonusModal() {
            document.getElementById('depositBonusModalTitle').innerText = 'ADD DEPOSIT REWARD TIER';
            document.getElementById('editBonusId').value = '0';
            document.getElementById('bonusTitleInput').value = '';
            document.getElementById('bonusMinDepositInput').value = '500';
            document.getElementById('bonusAmountInput').value = '50';
            document.getElementById('bonusTypeInput').value = 'fixed';
            document.getElementById('bonusClaimLimitInput').value = '0';
            document.getElementById('bonusSortOrderInput').value = '0';
            document.getElementById('bonusIsActiveInput').checked = true;
            document.getElementById('depositBonusModal').classList.remove('hidden');
        }

        function openEditDepositBonusModal(b) {
            document.getElementById('depositBonusModalTitle').innerText = 'EDIT DEPOSIT REWARD TIER';
            document.getElementById('editBonusId').value = b.id;
            document.getElementById('bonusTitleInput').value = b.title || '';
            document.getElementById('bonusMinDepositInput').value = b.min_deposit;
            document.getElementById('bonusAmountInput').value = b.bonus_amount;
            document.getElementById('bonusTypeInput').value = b.bonus_type || 'fixed';
            document.getElementById('bonusClaimLimitInput').value = b.claim_limit !== undefined ? b.claim_limit : 0;
            document.getElementById('bonusSortOrderInput').value = b.sort_order || 0;
            document.getElementById('bonusIsActiveInput').checked = (parseInt(b.is_active) === 1);
            document.getElementById('depositBonusModal').classList.remove('hidden');
        }

        function setThemePreset(hexColor) {
            document.getElementById('themeColorPicker').value = hexColor;
            document.getElementById('themeColorInput').value = hexColor;
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toastNotification');
            const toastInner = document.getElementById('toastInner');
            document.getElementById('toastMsg').innerText = msg;
            toastInner.className = (type === 'error') 
                ? "bg-white border-2 border-rose-500 shadow-2xl rounded-2xl p-4 flex items-center space-x-3 text-xs font-semibold text-rose-600" 
                : "bg-white border-2 border-emerald-500 shadow-2xl rounded-2xl p-4 flex items-center space-x-3 text-xs font-bold text-slate-800";
            toast.classList.remove('opacity-0', 'translate-y-[-20px]');
            toast.classList.add('opacity-100', 'translate-y-0');
            setTimeout(() => {
                toast.classList.remove('opacity-100', 'translate-y-0');
                toast.classList.add('opacity-0', 'translate-y-[-20px]');
            }, 3000);
        }

        window.addEventListener('DOMContentLoaded', () => {
            const initialTab = '<?= $activeTab ?>' !== 'tab-overview' ? '<?= $activeTab ?>' : (sessionStorage.getItem('admin_active_tab') || 'tab-overview');
            const initialTitle = sessionStorage.getItem('admin_active_title') || 'Dashboard Overview';
            
            document.querySelectorAll('.tab-section').forEach(sec => sec.classList.add('hidden'));
            const targetSection = document.getElementById(initialTab);
            if (targetSection) targetSection.classList.remove('hidden');
            document.getElementById('headerSectionTitle').innerText = initialTitle;

            document.querySelectorAll('.drawer-nav-btn').forEach(btn => btn.classList.remove('active'));
            const activeBtn = document.getElementById('btn-nav-' + initialTab);
            if (activeBtn) activeBtn.classList.add('active');

            if (initialTab === 'tab-discounts') {
                const discSub = '<?= $activeDiscSubTab ?>' || sessionStorage.getItem('disc_active_subtab') || 'disc-view-products';
                switchDiscountSubTab(discSub);
            }

            <?php if ($toast): ?>
                showToast("<?= addslashes($toast['msg']) ?>", "<?= $toast['type'] ?>");
            <?php endif; ?>
        });
    </script>
</body>
</html>