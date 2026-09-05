<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$sessionUser = get_session_user();

if (!$sessionUser) {
    json_response('error', 'Unauthorized. Please log in to admin portal.', null, 401);
}

if ($method !== 'POST') {
    json_response('error', 'Only POST method is accepted.', null, 405);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input || empty($input['items']) || !isArrayValid($input['items'])) {
    json_response('error', 'Invalid import payload. An array of items is required.');
}

function isArrayValid($arr) {
    return is_array($arr) && count($arr) > 0;
}

$itemsToImport = $input['items'];
$existingProducts = get_json_file('products') ?? [];
$importedCount = 0;
$createdProducts = [];

// Connect to PDO if configured
$pdo = get_db_connection();

foreach ($itemsToImport as $idx => $rawItem) {
    $name = trim($rawItem['name'] ?? '');
    if (empty($name)) {
        continue;
    }

    $category = trim($rawItem['category'] ?? 'accessories');
    if (empty($category)) $category = 'accessories';
    
    $subCategory = trim($rawItem['subCategory'] ?? $rawItem['sub_category'] ?? '');
    $brand = trim($rawItem['brand'] ?? '');
    if (empty($brand)) {
        $brand = !empty($subCategory) ? $subCategory : 'generic';
    }

    $costPrice = floatval($rawItem['costPrice'] ?? $rawItem['cost'] ?? 0);
    $sellingPrice = floatval($rawItem['sellingPrice'] ?? $rawItem['price'] ?? 0);
    if ($sellingPrice <= 0 && $costPrice > 0) {
        $sellingPrice = round($costPrice * 1.15); // Default 15% markup if not set
    }

    $stock = intval($rawItem['stock'] ?? $rawItem['qty'] ?? $rawItem['quantity'] ?? 1);
    $minStock = intval($rawItem['minStock'] ?? 2);

    $skuClean = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 4));
    if (empty($skuClean)) $skuClean = 'PROD';
    $sku = trim($rawItem['sku'] ?? ('SKU-' . $skuClean . '-' . rand(100, 999)));

    $barcode = trim($rawItem['barcode'] ?? ('' . rand(1000000000, 9999999999)));
    $image = trim($rawItem['image'] ?? 'assets/images/logo.jpg');

    $specs = [];
    if (!empty($rawItem['specs'])) {
        if (is_array($rawItem['specs'])) {
            $specs = array_values(array_filter($rawItem['specs']));
        } else {
            $specs = array_filter(array_map('trim', explode("\n", strval($rawItem['specs']))));
        }
    }

    $hasOnlineOffer = !empty($rawItem['hasOnlineOffer']) ? true : false;
    $onlinePrice = floatval($rawItem['onlinePrice'] ?? $sellingPrice);
    $offerBadge = trim($rawItem['offerBadge'] ?? '10% OFF ONLINE');
    $offerTagline = trim($rawItem['offerTagline'] ?? 'Pay 100% advance online & get special discount + priority shipping!');

    $unit = strtolower(trim($rawItem['unit'] ?? 'pcs'));
    if (empty($unit) || !in_array($unit, ['pcs', 'piece', 'meter', 'yard', 'foot', 'roll', 'box', 'set', 'kg'])) {
        $nameLower = strtolower($name);
        if (strpos($nameLower, 'meter') !== false || strpos($nameLower, 'cat6') !== false || strpos($nameLower, 'rg59') !== false) {
            $unit = 'meter';
        } elseif (strpos($nameLower, 'yard') !== false || strpos($nameLower, 'gaz') !== false) {
            $unit = 'yard';
        } elseif (strpos($nameLower, 'roll') !== false) {
            $unit = 'roll';
        } else {
            $unit = 'pcs';
        }
    }
    $unitLabels = [
        'pcs' => 'Piece',
        'piece' => 'Piece',
        'meter' => 'Meter',
        'yard' => 'Yard',
        'foot' => 'Foot',
        'roll' => 'Roll',
        'box' => 'Box',
        'set' => 'Set',
        'kg' => 'KG'
    ];
    $unitLabel = $unitLabels[$unit] ?? ucfirst($unit);
    $priceRange = ($unit !== 'pcs' && $unit !== 'piece') ? ('PKR ' . number_format($sellingPrice) . ' / ' . $unitLabel) : ('PKR ' . number_format($sellingPrice));

    $uniqueId = 'prod-' . time() . '-' . rand(100, 999) . '-' . $idx;

    $newProd = [
        'id' => $uniqueId,
        'name' => $name,
        'category' => $category,
        'categoryId' => $category,
        'subCategory' => $subCategory,
        'brand' => $brand,
        'unit' => $unit,
        'unitLabel' => $unitLabel,
        'sellingPrice' => $sellingPrice,
        'costPrice' => $costPrice,
        'priceNumeric' => $sellingPrice,
        'priceRange' => $priceRange,
        'stock' => $stock,
        'minStock' => $minStock,
        'sku' => $sku,
        'barcode' => $barcode,
        'image' => $image,
        'status' => 'active',
        'badge' => 'NEW LISTED',
        'isNewArrival' => true,
        'hasOnlineOffer' => $hasOnlineOffer,
        'discountType' => 'percentage',
        'discountValue' => 10,
        'onlinePrice' => $onlinePrice,
        'offerBadge' => $offerBadge,
        'offerTagline' => $offerTagline,
        'offerExpiry' => 'Limited Time Online Offer',
        'specs' => array_values($specs),
        'createdAt' => date('c'),
        'importedVia' => $rawItem['importSource'] ?? 'smart_importer'
    ];

    // Prepend to products array
    array_unshift($existingProducts, $newProd);
    $createdProducts[] = $newProd;
    $importedCount++;

    // Synchronize to MySQL database if table exists
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO products 
                (id, name, category, brand, selling_price, cost_price, stock, min_stock, sku, barcode, image, specs_json, status, created_at)
                VALUES 
                (:id, :name, :category, :brand, :selling_price, :cost_price, :stock, :min_stock, :sku, :barcode, :image, :specs_json, :status, NOW())
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name),
                selling_price = VALUES(selling_price),
                cost_price = VALUES(cost_price),
                stock = stock + VALUES(stock)
            ");
            $stmt->execute([
                ':id' => $uniqueId,
                ':name' => $name,
                ':category' => $category,
                ':brand' => $brand,
                ':selling_price' => $sellingPrice,
                ':cost_price' => $costPrice,
                ':stock' => $stock,
                ':min_stock' => $minStock,
                ':sku' => $sku,
                ':barcode' => $barcode,
                ':image' => $image,
                ':specs_json' => json_encode($specs),
                ':status' => 'active'
            ]);
        } catch (Exception $e) {
            // Non-blocking fallback to JSON
            error_log("Database product import notice: " . $e->getMessage());
        }
    }
}

if ($importedCount > 0) {
    save_json_file('products', $existingProducts);

    $adminName = $sessionUser['username'] ?? 'admin';
    SecurityLogger::logEvent($adminName, $sessionUser['role'] ?? 'admin', 'BATCH_PRODUCTS_IMPORTED', "Imported {$importedCount} new products via Smart Bill/Excel Importer.");

    json_response('success', "Successfully imported {$importedCount} products into inventory!", [
        'count' => $importedCount,
        'products' => $createdProducts
    ]);
} else {
    json_response('error', 'No valid products were found in the import payload.');
}
