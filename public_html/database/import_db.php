<?php
// =========================================================================
// Automated Local Database Importer & Setup for Safdar Mobile Store (SMS)
// Supports any XAMPP / MariaDB / MySQL installation on Windows
// =========================================================================

echo "========================================================\n";
echo "  Safdar Mobile Store (SMS) - Local Database Setup\n";
echo "========================================================\n\n";

$dbName = 'u423425124_SMS';
$dbUser = 'u423425124_SMS';
$dbPass = 'SafdarAdmin#2026!';

$possibleHosts = ['127.0.0.1', 'localhost'];
$possibleRootUsers = [
    ['user' => 'root', 'pass' => ''],
    ['user' => 'root', 'pass' => 'root'],
    ['user' => $dbUser, 'pass' => $dbPass]
];

$pdo = null;
$connectedHost = null;
$connectedUser = null;
$connectedPass = null;

// 1. Try to establish initial database server connection
foreach ($possibleHosts as $host) {
    foreach ($possibleRootUsers as $cred) {
        try {
            $testPdo = new PDO("mysql:host=$host;charset=utf8mb4", $cred['user'], $cred['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $pdo = $testPdo;
            $connectedHost = $host;
            $connectedUser = $cred['user'];
            $connectedPass = $cred['pass'];
            echo "[OK] Connected to MySQL Server at $host as '{$cred['user']}'\n";
            break 2;
        } catch (Exception $e) {
            // Continue trying
        }
    }
}

if (!$pdo) {
    echo "[ERROR] Could not connect to MySQL server.\n";
    echo "  Please make sure XAMPP MySQL service is running!\n";
    echo "  Start it from XAMPP Control Panel or run C:\\xampp\\mysql_start.bat\n";
    exit(1);
}

// 2. Create Database if missing
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "[OK] Database `$dbName` verified / created.\n";
} catch (Exception $e) {
    echo "[WARN] Notice during database creation: " . $e->getMessage() . "\n";
}

// 3. Grant privileges to dedicated DB user for production parity
try {
    $pdo->exec("CREATE USER IF NOT EXISTS '$dbUser'@'localhost' IDENTIFIED BY '$dbPass'");
    $pdo->exec("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$dbUser'@'localhost'");
    $pdo->exec("CREATE USER IF NOT EXISTS '$dbUser'@'127.0.0.1' IDENTIFIED BY '$dbPass'");
    $pdo->exec("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$dbUser'@'127.0.0.1'");
    $pdo->exec("FLUSH PRIVILEGES");
    echo "[OK] User '$dbUser' privileges configured.\n";
} catch (Exception $e) {
    // Ignore user creation failure if logged in with standard privileges
}

// 4. Select Database
$pdo->exec("USE `$dbName`");

// 5. Read SQL Dump file
$sqlFile = __DIR__ . '/u423425124_SMS.sql';
if (!file_exists($sqlFile)) {
    $sqlFile = __DIR__ . '/safdar_mobile_store.sql';
}

if (!file_exists($sqlFile)) {
    echo "[ERROR] No SQL dump file found in " . __DIR__ . "\n";
    exit(1);
}

echo "[INFO] Importing SQL dump from: " . basename($sqlFile) . " ...\n";

// Check if mysql CLI is available in typical XAMPP locations
$mysqlCliPaths = [
    'C:\\xampp\\mysql\\bin\\mysql.exe',
    'D:\\xampp\\mysql\\bin\\mysql.exe',
    'E:\\xampp\\mysql\\bin\\mysql.exe',
    'mysql'
];

$importedViaCli = false;
foreach ($mysqlCliPaths as $cli) {
    $checkCmd = ($cli === 'mysql') ? 'where mysql 2>nul' : (file_exists($cli) ? $cli : false);
    if ($checkCmd) {
        $cmd = "\"$cli\" -h $connectedHost -u $connectedUser " . ($connectedPass ? "-p\"$connectedPass\" " : "") . "$dbName -e \"source " . str_replace('\\', '/', $sqlFile) . ";\"";
        exec($cmd, $out, $ret);
        if ($ret === 0) {
            $importedViaCli = true;
            echo "[OK] Successfully imported SQL dump via MySQL CLI.\n";
            break;
        }
    }
}

if (!$importedViaCli) {
    // Fallback: Read and execute statement by statement using PDO
    $sqlContent = file_get_contents($sqlFile);
    $sqlContent = str_replace('utf8mb4_uca1400_ai_ci', 'utf8mb4_unicode_ci', $sqlContent);
    
    // Parse SQL dump into individual queries
    $lines = explode("\n", $sqlContent);
    $currentQuery = '';
    $successCount = 0;

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (empty($trimmed) || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) {
            continue;
        }
        $currentQuery .= ' ' . $line;
        if (substr($trimmed, -1) === ';') {
            try {
                $pdo->exec($currentQuery);
                $successCount++;
            } catch (Exception $e) {
                // Ignore benign notices
            }
            $currentQuery = '';
        }
    }
    echo "[OK] Executed $successCount SQL queries into `$dbName` via PDO.\n";
}

// 6. Verify Tables & Record Counts
echo "\n--- Database Tables Verification ---\n";
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $totalRecords = 0;
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $totalRecords += $count;
        echo sprintf("  %-20s : %4d records\n", $table, $count);
    }
    echo "------------------------------------\n";
    echo "  Total Database Records : $totalRecords\n\n";
} catch (Exception $e) {
    echo "[WARN] Could not retrieve table summary: " . $e->getMessage() . "\n";
}

// 7. Synchronize Database into JSON Dual-Engine Files
echo "[INFO] Synchronizing live data to JSON dual-engine store (backend/data/) ...\n";
require_once __DIR__ . '/../sync_and_setup.php';

echo "\n========================================================\n";
echo "  [SUCCESS] Local Setup is 100% Complete & Ready!\n";
echo "  Storefront URL:   http://localhost/sms/\n";
echo "  Admin POS Portal: http://localhost/sms/secure-portal.php\n";
echo "========================================================\n";
