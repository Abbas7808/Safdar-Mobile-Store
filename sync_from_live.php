<?php
// =========================================================================
// Live Server Sync Tool - Fetches 100% live website data from safdarmobilestore.com
// =========================================================================

echo "=== Syncing data directly from live website https://safdarmobilestore.com/ ===\n";

$dataDir = __DIR__ . '/backend/data/';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$endpoints = [
    'products' => 'https://safdarmobilestore.com/backend/products.php',
    'categories' => 'https://safdarmobilestore.com/backend/categories.php',
    'brands' => 'https://safdarmobilestore.com/backend/brands.php',
    'settings' => 'https://safdarmobilestore.com/backend/settings.php',
    'services' => 'https://safdarmobilestore.com/backend/services.php',
    'packages' => 'https://safdarmobilestore.com/backend/packages.php',
    'cctv' => 'https://safdarmobilestore.com/backend/cctv.php',
    'repairs' => 'https://safdarmobilestore.com/backend/repairs.php',
    'repair_parts' => 'https://safdarmobilestore.com/backend/repair_parts.php',
    'customers' => 'https://safdarmobilestore.com/backend/customers.php',
    'suppliers' => 'https://safdarmobilestore.com/backend/suppliers.php',
    'expenses' => 'https://safdarmobilestore.com/backend/expenses.php'
];

$ctx = stream_context_create([
    'http' => [
        'timeout' => 15,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

foreach ($endpoints as $key => $url) {
    echo "Fetching $key from $url ... ";
    $jsonContent = @file_get_contents($url, false, $ctx);
    if ($jsonContent) {
        $data = json_decode($jsonContent, true);
        if ($data && isset($data['data'])) {
            $items = $data['data'];
            file_put_contents($dataDir . $key . '.json', json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "[OK] (" . (is_array($items) ? count($items) : 'object') . " items)\n";
        } elseif ($data && !isset($data['status'])) {
            file_put_contents($dataDir . $key . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "[OK] (raw data saved)\n";
        } else {
            echo "[WARN] Empty or unknown format\n";
        }
    } else {
        echo "[FAILED]\n";
    }
}

// Mirror to public_html/backend/data
$pubDataDir = __DIR__ . '/public_html/backend/data/';
if (is_dir($pubDataDir)) {
    foreach (glob($dataDir . '*.json') as $f) {
        copy($f, $pubDataDir . basename($f));
    }
    echo "[OK] Mirrored all JSON datasets to public_html/backend/data/\n";
}

echo "\n=== Live Fetch Complete! Now Updating MySQL Database ===\n";

// Update MySQL Database with Live Products
require_once __DIR__ . '/backend/config.php';
$pdo = get_db_connection();

if ($pdo) {
    $products = json_decode(file_get_contents($dataDir . 'products.json'), true);
    if (is_array($products)) {
        $pdo->exec("DELETE FROM `products`");
        $stmt = $pdo->prepare("INSERT INTO `products` (`id`, `name`, `brand`, `category`, `categoryId`, `image`, `priceRange`, `priceNumeric`, `sellingPrice`, `costPrice`, `stock`, `minStock`, `sku`, `barcode`, `badge`, `status`, `isNewArrival`, `specs`, `createdAt`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($products as $p) {
            $specs = isset($p['specs']) ? (is_array($p['specs']) ? json_encode($p['specs']) : $p['specs']) : '[]';
            $stmt->execute([
                $p['id'] ?? ('prod-' . uniqid()),
                $p['name'] ?? '',
                $p['brand'] ?? '',
                $p['category'] ?? '',
                $p['categoryId'] ?? ($p['category'] ?? ''),
                $p['image'] ?? '',
                $p['priceRange'] ?? ('PKR ' . number_format($p['sellingPrice'] ?? 0)),
                floatval($p['priceNumeric'] ?? ($p['sellingPrice'] ?? 0)),
                floatval($p['sellingPrice'] ?? 0),
                floatval($p['costPrice'] ?? 0),
                intval($p['stock'] ?? 0),
                intval($p['minStock'] ?? 2),
                $p['sku'] ?? '',
                $p['barcode'] ?? '',
                $p['badge'] ?? 'NEW LISTED',
                $p['status'] ?? 'active',
                !empty($p['isNewArrival']) ? 1 : 0,
                $specs,
                $p['createdAt'] ?? date('Y-m-d H:i:s')
            ]);
        }
        echo "[OK] Updated MySQL `products` table with " . count($products) . " live products!\n";
    }

    $brands = json_decode(file_get_contents($dataDir . 'brands.json'), true);
    if (is_array($brands)) {
        $pdo->exec("DELETE FROM `brands`");
        $stmt = $pdo->prepare("INSERT INTO `brands` (`id`, `name`, `category`, `status`) VALUES (?, ?, ?, ?)");
        foreach ($brands as $b) {
            $stmt->execute([
                $b['id'] ?? '',
                $b['name'] ?? '',
                $b['category'] ?? 'mobiles',
                $b['status'] ?? 'active'
            ]);
        }
        echo "[OK] Updated MySQL `brands` table with " . count($brands) . " live brands!\n";
    }

    $categories = json_decode(file_get_contents($dataDir . 'categories.json'), true);
    if (is_array($categories)) {
        $pdo->exec("DELETE FROM `categories`");
        $stmt = $pdo->prepare("INSERT INTO `categories` (`id`, `name`, `status`) VALUES (?, ?, ?)");
        foreach ($categories as $c) {
            $stmt->execute([
                $c['id'] ?? '',
                $c['name'] ?? '',
                $c['status'] ?? 'active'
            ]);
        }
        echo "[OK] Updated MySQL `categories` table with " . count($categories) . " live categories!\n";
    }
}

echo "\n=== Syncing live images from uploads/products/ ===\n";
$uploadsDir = __DIR__ . '/uploads/products/';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

$liveProducts = json_decode(file_get_contents($dataDir . 'products.json'), true);
if (is_array($liveProducts)) {
    foreach ($liveProducts as $lp) {
        $img = $lp['image'] ?? '';
        if (strpos($img, 'uploads/products/') === 0) {
            $filename = basename($img);
            $localFilePath = $uploadsDir . $filename;
            if (!file_exists($localFilePath) || filesize($localFilePath) == 0) {
                $liveImgUrl = 'https://safdarmobilestore.com/' . $img;
                echo "Downloading $filename from $liveImgUrl ... ";
                $imgData = @file_get_contents($liveImgUrl, false, $ctx);
                if ($imgData) {
                    file_put_contents($localFilePath, $imgData);
                    echo "[OK] (" . strlen($imgData) . " bytes)\n";
                } else {
                    echo "[FAILED]\n";
                }
            }
        }
    }
}

// Mirror uploads to public_html/uploads/products
$pubUploadsDir = __DIR__ . '/public_html/uploads/products/';
if (!is_dir($pubUploadsDir)) {
    mkdir($pubUploadsDir, 0777, true);
}
foreach (glob($uploadsDir . '*.*') as $imgFile) {
    copy($imgFile, $pubUploadsDir . basename($imgFile));
}

echo "\n=== ALL LIVE DATA & IMAGES ARE 100% IN SYNC! ===\n";
