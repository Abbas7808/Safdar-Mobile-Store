<?php
// =========================================================================
// Complete Synchronizer & Setup Script for Safdar Mobile Store (SMS)
// Ensures 100% parity between Root, Public_html, MySQL DB & JSON Engine
// =========================================================================

echo "=== 1. Synchronizing files from public_html to root workspace ===\n";

$pub = __DIR__ . '/public_html';
$root = __DIR__;

if (is_dir($pub)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pub, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $copiedCount = 0;
    foreach ($iterator as $item) {
        $relPath = substr($item->getPathname(), strlen($pub) + 1);
        $targetPath = $root . DIRECTORY_SEPARATOR . $relPath;

        if ($item->isDir()) {
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0777, true);
            }
        } else {
            // Keep specific root files if needed
            if ($relPath === 'README.md') {
                continue;
            }
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            copy($item->getPathname(), $targetPath);
            $copiedCount++;
        }
    }
    echo "[OK] Synchronized $copiedCount files from public_html to workspace root.\n";
}

echo "\n=== 2. Exporting MySQL Data to backend/data/ JSON files for dual-engine parity ===\n";

$pdo = null;
$possibleHosts = ['127.0.0.1', 'localhost'];
$possibleUsers = [
    ['user' => 'root', 'pass' => ''],
    ['user' => 'root', 'pass' => 'root'],
    ['user' => 'u423425124_SMS', 'pass' => 'SafdarAdmin#2026!']
];

foreach ($possibleHosts as $host) {
    foreach ($possibleUsers as $cred) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=u423425124_SMS;charset=utf8mb4", $cred['user'], $cred['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            break 2;
        } catch (Exception $e) {
            // Continue
        }
    }
}

if (!$pdo) {
    echo "[WARN] Could not connect to MySQL database `u423425124_SMS` to export tables.\n";
    echo "  (Using existing backend/data/*.json records for offline JSON mode)\n";
} else {
    try {
        $dataDir = __DIR__ . '/backend/data/';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }

        // 2.1 Products
        $stmt = $pdo->query("SELECT * FROM products");
        $products = $stmt->fetchAll();
        foreach ($products as &$p) {
            if (!empty($p['specs']) && is_string($p['specs'])) {
                $decoded = json_decode($p['specs'], true);
                if (is_array($decoded)) {
                    $p['specs'] = $decoded;
                }
            }
            $p['priceNumeric'] = floatval($p['priceNumeric']);
            $p['sellingPrice'] = floatval($p['sellingPrice']);
            $p['costPrice'] = floatval($p['costPrice']);
            $p['stock'] = intval($p['stock']);
            $p['minStock'] = intval($p['minStock']);
            $p['isNewArrival'] = boolval($p['isNewArrival']);
        }
        file_put_contents($dataDir . 'products.json', json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  - Exported " . count($products) . " products to products.json\n";

        // 2.2 Brands
        $stmt = $pdo->query("SELECT * FROM brands");
        $brands = $stmt->fetchAll();
        file_put_contents($dataDir . 'brands.json', json_encode($brands, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  - Exported " . count($brands) . " brands to brands.json\n";

        // 2.3 Categories
        $stmt = $pdo->query("SELECT * FROM categories");
        $categories = $stmt->fetchAll();
        file_put_contents($dataDir . 'categories.json', json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  - Exported " . count($categories) . " categories to categories.json\n";

        // 2.4 Customers
        $stmt = $pdo->query("SELECT * FROM customers");
        $customers = $stmt->fetchAll();
        file_put_contents($dataDir . 'customers.json', json_encode($customers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  - Exported " . count($customers) . " customers to customers.json\n";

        // 2.5 Suppliers
        $stmt = $pdo->query("SELECT * FROM suppliers");
        $suppliers = $stmt->fetchAll();
        file_put_contents($dataDir . 'suppliers.json', json_encode($suppliers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  - Exported " . count($suppliers) . " suppliers to suppliers.json\n";

        // 2.6 Expenses
        $stmt = $pdo->query("SELECT * FROM expenses");
        $expenses = $stmt->fetchAll();
        file_put_contents($dataDir . 'expenses.json', json_encode($expenses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  - Exported " . count($expenses) . " expenses to expenses.json\n";

        // 2.7 Sales
        $stmt = $pdo->query("SELECT * FROM sales");
        $sales = $stmt->fetchAll();
        foreach ($sales as &$s) {
            if (!empty($s['items']) && is_string($s['items'])) {
                $decItems = json_decode($s['items'], true);
                if (is_array($decItems)) {
                    $s['items'] = $decItems;
                }
            }
            $s['subtotal'] = floatval($s['subtotal']);
            $s['discount'] = floatval($s['discount']);
            $s['total'] = floatval($s['total']);
            $s['cogs'] = floatval($s['cogs']);
            $s['profit'] = floatval($s['profit']);
        }
        file_put_contents($dataDir . 'sales.json', json_encode($sales, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  - Exported " . count($sales) . " sales to sales.json\n";

        // 2.8 Settings
        $stmt = $pdo->query("SELECT * FROM settings");
        $settingRows = $stmt->fetchAll();
        $settingsObj = [];
        foreach ($settingRows as $row) {
            $settingsObj[$row['setting_key']] = $row['setting_value'];
        }
        file_put_contents($dataDir . 'settings.json', json_encode($settingsObj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  - Exported " . count($settingsObj) . " settings to settings.json\n";

        // 2.9 Audit Logs
        try {
            $stmt = $pdo->query("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 500");
            $auditLogs = $stmt->fetchAll();
            file_put_contents($dataDir . 'audit.json', json_encode($auditLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "  - Exported " . count($auditLogs) . " audit logs to audit.json\n";
        } catch (Exception $ae) {
            // Ignore if audit_logs table not queried
        }

        // 2.10 Users
        $stmt = $pdo->query("SELECT * FROM users");
        $users = $stmt->fetchAll();
        $existingUsers = file_exists($dataDir . 'users.json') ? json_decode(file_get_contents($dataDir . 'users.json'), true) : [];
        $userMap = [];
        if (is_array($existingUsers)) {
            foreach ($existingUsers as $eu) {
                $userMap[$eu['username']] = $eu;
            }
        }
        foreach ($users as &$u) {
            if (isset($userMap[$u['username']])) {
                $u['totp_secret'] = $userMap[$u['username']]['totp_secret'] ?? '';
                $u['totp_enabled'] = $userMap[$u['username']]['totp_enabled'] ?? false;
                $u['force_password_change'] = $userMap[$u['username']]['force_password_change'] ?? false;
            }
        }
        file_put_contents($dataDir . 'users.json', json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  - Exported " . count($users) . " users to users.json\n";

        // 2.11 Also copy to public_html/backend/data if it exists
        if (is_dir($pub . '/backend/data')) {
            foreach (glob($dataDir . '*.json') as $jFile) {
                copy($jFile, $pub . '/backend/data/' . basename($jFile));
            }
            echo "[OK] Mirrored JSON datasets to public_html/backend/data/\n";
        }

        echo "\n=== Sync & Setup Completed Successfully! ===\n";

    } catch (Exception $e) {
        echo "[ERROR] Sync error: " . $e->getMessage() . "\n";
    }
}
