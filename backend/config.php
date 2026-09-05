<?php
// SMS Backend Configuration & Data Access Layer
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

define('DATA_DIR', __DIR__ . '/data/');

// Production MySQL Database Configuration (Hostinger Live DB: u423425124_SMS)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'u423425124_SMS');
define('DB_USER', getenv('DB_USER') ?: 'u423425124_SMS');
define('DB_PASS', getenv('DB_PASS') ?: 'SafdarAdmin#2026!');

// Ensure data directory exists
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0777, true);
}

// Security Headers Helper
function send_security_headers() {
    if (!headers_sent()) {
        header("X-Frame-Options: SAMEORIGIN");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https://fonts.googleapis.com https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://images.unsplash.com https://api.qrserver.com https://maps.google.com https://www.google.com data:; frame-src 'self' https://maps.google.com https://www.google.com https://*.google.com https://*.googleapis.com; img-src 'self' data: https:;");
    }
}
send_security_headers();

// PDO Database Connection Helper with Fast Timeout & Memory Cache
function get_db_connection() {
    static $pdo = null;
    static $connectionFailed = false;

    if ($connectionFailed) {
        return false;
    }

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 1
            ]);
        } catch (Exception $e) {
            // Fallback for local XAMPP default root user with 1s timeout
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $pdo = new PDO($dsn, 'root', '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 1
                ]);
            } catch (Exception $e2) {
                $pdo = false;
                $connectionFailed = true;
            }
        }
    }
    return $pdo;
}

// CSRF Protection Helpers
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// In-Memory Cached JSON Data Access Layer for High Speed
$GLOBALS['JSON_MEM_CACHE'] = [];

function get_json_file($name) {
    if (isset($GLOBALS['JSON_MEM_CACHE'][$name])) {
        return $GLOBALS['JSON_MEM_CACHE'][$name];
    }
    $path = DATA_DIR . $name . '.json';
    if (!file_exists($path)) {
        return null;
    }
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    $GLOBALS['JSON_MEM_CACHE'][$name] = $data;
    return $data;
}

function save_json_file($name, $data) {
    $GLOBALS['JSON_MEM_CACHE'][$name] = $data;
    $path = DATA_DIR . $name . '.json';
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function json_response($status, $message, $data = null) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    }
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'csrf_token' => get_csrf_token()
    ]);
    exit();
}

// Helper to seed initial data if missing
function init_seed_data() {
    // 0. Security Config
    if (!file_exists(DATA_DIR . 'security_config.json')) {
        $secConfig = [
            'admin_secret_path' => 'secure-management-portal',
            'session_timeout_minutes' => 30,
            'max_login_attempts' => 5,
            'lockout_duration_minutes' => 15,
            'emergency_lockdown' => false,
            '2fa_required_super_admin' => false
        ];
        save_json_file('security_config', $secConfig);
    }

    // 1. Users
    if (!file_exists(DATA_DIR . 'users.json')) {
        $users = [
            [
                'id' => 'user-001',
                'username' => 'user@user.com',
                'password' => PasswordPolicy::hashPassword('SafdarAdmin#2026!'),
                'name' => 'Super Admin',
                'role' => 'super_admin',
                'email' => 'user@user.com',
                'phone' => '03339688007',
                'status' => 'active',
                'totp_secret' => '',
                'totp_enabled' => false,
                'force_password_change' => false,
                'createdAt' => date('c')
            ],
            [
                'id' => 'user-002',
                'username' => 'salesman@salesman.com',
                'password' => PasswordPolicy::hashPassword('SalesmanPos#2026!'),
                'name' => 'Salesman / POS Operator',
                'role' => 'salesman',
                'email' => 'salesman@salesman.com',
                'phone' => '03339688007',
                'status' => 'active',
                'totp_secret' => '',
                'totp_enabled' => false,
                'force_password_change' => false,
                'createdAt' => date('c')
            ]
        ];
        save_json_file('users', $users);
    } else {
        // Upgrade any plaintext passwords in existing users.json
        $users = get_json_file('users') ?? [];
        $modified = false;
        foreach ($users as &$u) {
            if (isset($u['password']) && (strpos($u['password'], '$2y$') !== 0 && strpos($u['password'], '$2a$') !== 0)) {
                $u['password'] = PasswordPolicy::hashPassword($u['password']);
                $modified = true;
            }
        }
        if ($modified) {
            save_json_file('users', $users);
        }
    }

    // 2. Categories
    if (!file_exists(DATA_DIR . 'categories.json')) {
        $categories = [
            ['id' => 'mobiles', 'name' => 'Mobile Phones', 'icon' => 'fa-mobile-screen', 'status' => 'active', 'show_in_menu' => true, 'productCount' => 4],
            ['id' => 'accessories', 'name' => 'Mobile Accessories', 'icon' => 'fa-headphones', 'status' => 'active', 'show_in_menu' => true, 'productCount' => 7],
            ['id' => 'cctv', 'name' => 'CCTV Security', 'icon' => 'fa-video', 'status' => 'active', 'show_in_menu' => true, 'productCount' => 8],
            ['id' => 'computer_accessories', 'name' => 'Computer Accessories', 'icon' => 'fa-computer', 'status' => 'active', 'show_in_menu' => false, 'productCount' => 0],
            ['id' => 'network_accessories', 'name' => 'Network Accessories', 'icon' => 'fa-network-wired', 'status' => 'active', 'show_in_menu' => false, 'productCount' => 0]
        ];
        save_json_file('categories', $categories);
    }

    // 3. Brands
    if (!file_exists(DATA_DIR . 'brands.json')) {
        $brands = [
            ['id' => 'samsung', 'name' => 'Samsung', 'category' => 'mobiles', 'status' => 'active'],
            ['id' => 'apple', 'name' => 'Apple', 'category' => 'mobiles', 'status' => 'active'],
            ['id' => 'infinix', 'name' => 'Infinix', 'category' => 'mobiles', 'status' => 'active'],
            ['id' => 'tecno', 'name' => 'Tecno', 'category' => 'mobiles', 'status' => 'active'],
            ['id' => 'hikvision', 'name' => 'Hikvision', 'category' => 'cctv', 'status' => 'active'],
            ['id' => 'dahua', 'name' => 'Dahua', 'category' => 'cctv', 'status' => 'active']
        ];
        save_json_file('brands', $brands);
    }

    // 4. Products (Clean initial state - ready for shop owner to add new products)
    if (!file_exists(DATA_DIR . 'products.json')) {
        save_json_file('products', []);
    }

    // 5. Suppliers
    if (!file_exists(DATA_DIR . 'suppliers.json')) {
        save_json_file('suppliers', []);
    }

    // 6. Customers (Clean initial state with standard Walk-in Customer)
    if (!file_exists(DATA_DIR . 'customers.json')) {
        $customers = [
            [
                'id' => 'cust-1',
                'name' => 'Walk-in Customer',
                'phone' => '03339688007',
                'email' => '',
                'totalPurchases' => 0,
                'totalSpent' => 0,
                'balance' => 0,
                'status' => 'active'
            ]
        ];
        save_json_file('customers', $customers);
    }

    // 7. Expenses
    if (!file_exists(DATA_DIR . 'expenses.json')) {
        save_json_file('expenses', []);
    }

    // 8. Sales
    if (!file_exists(DATA_DIR . 'sales.json')) {
        save_json_file('sales', []);
    }

    // 9. Services
    if (!file_exists(DATA_DIR . 'services.json')) {
        save_json_file('services', []);
    }

    // 10. Purchases
    if (!file_exists(DATA_DIR . 'purchases.json')) {
        save_json_file('purchases', []);
    }

    // 11. Settings
    if (!file_exists(DATA_DIR . 'settings.json')) {
        $settings = [
            'businessName' => 'Safdar Mobile Store',
            'businessSubtitle' => 'Mobile Phones, Accessories, CCTV & Financial Services',
            'contact' => '03339688007',
            'email' => 'info@safdarmobile.com',
            'address' => 'Main Bazaar Commercial Mobile Market, Hangu, KPK',
            'currency' => 'PKR',
            'receiptWidth' => '80mm',
            'receiptFooter' => 'Thank you for shopping at Safdar Mobile Store! Contact: 03339688007'
        ];
        save_json_file('settings', $settings);
    }

    // 12. Audit
    if (!file_exists(DATA_DIR . 'audit.json')) {
        $audit = [
            [
                'id' => 'aud-1',
                'user' => 'admin',
                'action' => 'SYSTEM_INIT',
                'details' => 'SMS Brand POS system initialized successfully',
                'timestamp' => date('c')
            ]
        ];
        save_json_file('audit', $audit);
    }

    // 13. Customer Reviews
    if (!file_exists(DATA_DIR . 'reviews.json')) {
        save_json_file('reviews', []);
    }

    // 14. NADRA & Citizen Kiosk Records
    if (!file_exists(DATA_DIR . 'nadra_kiosk.json')) {
        save_json_file('nadra_kiosk', []);
    }

    // 15. Utility Bills Payments
    if (!file_exists(DATA_DIR . 'bills.json')) {
        save_json_file('bills', []);
    }

    // 16. Mobile Network Packages & Bundles
    if (!file_exists(DATA_DIR . 'packages.json')) {
        save_json_file('packages', []);
    }
}

// Include Security Engine
require_once __DIR__ . '/security.php';

// Auto-run seed check
init_seed_data();

// Auth & Role-Based Access Control (RBAC) helpers with Inactivity Timeout & Emergency Lockdown
function get_session_user() {
    if (!isset($_SESSION['user'])) {
        return null;
    }

    // Configurable Inactivity Timeout Check (Default: 30 minutes)
    $secConfig = get_json_file('security_config') ?? [];
    $timeoutMinutes = intval($secConfig['session_timeout_minutes'] ?? 30);
    $timeoutSeconds = $timeoutMinutes * 60;
    
    $lastActivity = $_SESSION['last_activity'] ?? time();
    if ((time() - $lastActivity) > $timeoutSeconds) {
        // Session expired due to inactivity
        unset($_SESSION['user']);
        unset($_SESSION['admin_access_granted']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        return null;
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
    $user = $_SESSION['user'];

    // Check Emergency Lockdown status for non-admin accounts
    if (($user['role'] ?? '') !== 'super_admin' && ActiveSessionManager::isEmergencyLockdownActive()) {
        unset($_SESSION['user']);
        return null;
    }

    return $user;
}

function has_role($allowedRoles = []) {
    $user = get_session_user();
    if (!$user) return false;
    if (is_string($allowedRoles)) $allowedRoles = [$allowedRoles];
    return in_array($user['role'], $allowedRoles);
}

function require_auth() {
    $user = get_session_user();
    if (!$user) {
        json_response('error', 'Unauthorized access. Session expired or unauthenticated.', null);
    }
    return $user;
}

function require_role($allowedRoles = []) {
    $user = get_session_user();
    if (!$user) {
        if (defined('IS_API_ROUTE')) {
            json_response('error', 'Unauthorized access. Session expired or unauthenticated.', null);
        }
        header('Location: login.php?expired=1');
        exit();
    }
    if (!has_role($allowedRoles)) {
        SecurityLogger::logEvent($user['username'] ?? 'UNKNOWN', $user['role'] ?? 'GUEST', 'UNAUTHORIZED_ACCESS_ATTEMPT', "Attempted unauthorized page/API access", 'DENIED');
        if (defined('IS_API_ROUTE')) {
            json_response('error', 'Access denied. Your role is not authorized for this action.', null);
        }
        header('Location: pos.php?error=access_denied');
        exit();
    }
    return $user;
}

// Notification System Helper
function add_notification($type, $title, $message, $amount = 0, $customer = '') {
    $notifications = get_json_file('notifications') ?? [];
    $newNotif = [
        'id' => 'notif-' . time() . '-' . rand(100, 999),
        'type' => $type, // 'sale', 'payment', 'low_stock'
        'title' => $title,
        'message' => $message,
        'amount' => floatval($amount),
        'customer' => $customer,
        'timestamp' => date('c'),
        'read' => false
    ];
    array_unshift($notifications, $newNotif);
    if (count($notifications) > 100) {
        $notifications = array_slice($notifications, 0, 100);
    }
    save_json_file('notifications', $notifications);
    return $newNotif;
}

