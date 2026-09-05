<?php
// Password & User Updater for Safdar Mobile Store
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/backend/security.php';

echo "=== Updating Admin and Salesman Credentials ===\n";

$adminUsername = 'admin@admin.com';
$adminEmail = 'admin@admin.com';
$adminPass = 'SafdarAdmin@2026!';
$adminHash = PasswordPolicy::hashPassword($adminPass);

$salesmanUsername = 'Salesman@Salesman.com';
$salesmanEmail = 'salesman@salesman.com';
$salesmanPass = 'SalesmanPos#2026!';
$salesmanHash = PasswordPolicy::hashPassword($salesmanPass);

// 1. Update JSON store
$usersData = [
    [
        'id' => 'user-001',
        'username' => $adminUsername,
        'password' => $adminHash,
        'name' => 'Super Admin',
        'role' => 'super_admin',
        'email' => $adminEmail,
        'phone' => '03339688007',
        'status' => 'active',
        'totp_secret' => '',
        'totp_enabled' => false,
        'force_password_change' => false,
        'createdAt' => '2026-08-10 08:00:55'
    ],
    [
        'id' => 'user-002',
        'username' => $salesmanUsername,
        'password' => $salesmanHash,
        'name' => 'Salesman / POS Operator',
        'role' => 'salesman',
        'email' => $salesmanEmail,
        'phone' => '03339688007',
        'status' => 'active',
        'totp_secret' => '',
        'totp_enabled' => false,
        'force_password_change' => false,
        'createdAt' => '2026-08-10 08:35:00'
    ]
];

save_json_file('users', $usersData);
echo "[OK] Updated backend/data/users.json\n";

if (is_dir(__DIR__ . '/public_html/backend/data')) {
    file_put_contents(__DIR__ . '/public_html/backend/data/users.json', json_encode($usersData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "[OK] Updated public_html/backend/data/users.json\n";
}

// 2. Update MySQL database
try {
    $pdo = get_db_connection();
    if ($pdo) {
        // Clear and insert or update users table
        $pdo->exec("DELETE FROM `users` WHERE id IN ('user-001', 'user-002')");
        
        $stmt = $pdo->prepare("INSERT INTO `users` (`id`, `username`, `password`, `name`, `role`, `email`, `phone`, `status`, `createdAt`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'user-001', $adminUsername, $adminHash, 'Super Admin', 'super_admin', $adminEmail, '03339688007', 'active', '2026-08-10 08:00:55'
        ]);
        $stmt->execute([
            'user-002', $salesmanUsername, $salesmanHash, 'Salesman / POS Operator', 'salesman', $salesmanEmail, '03339688007', 'active', '2026-08-10 08:35:00'
        ]);
        echo "[OK] Updated MySQL database `u423425124_SMS` users table\n";
    } else {
        echo "[WARN] Could not connect to MySQL PDO, updated JSON data\n";
    }
} catch (Exception $e) {
    echo "[WARN] MySQL error: " . $e->getMessage() . "\n";
}

// 3. Clear rate limit blocks so immediate login works
save_json_file('login_attempts', []);
if (is_dir(__DIR__ . '/public_html/backend/data')) {
    file_put_contents(__DIR__ . '/public_html/backend/data/login_attempts.json', json_encode([], JSON_PRETTY_PRINT));
}
echo "[OK] Cleared rate limiting login attempts cache\n";

echo "\n=== Credentials Successfully Configured! ===\n";
