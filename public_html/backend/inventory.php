<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$user = require_role('super_admin');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $products = get_json_file('products') ?? [];
    
    $totalProducts = count($products);
    $totalStock = 0;
    $outOfStock = 0;
    $lowStock = 0;
    $totalValuation = 0;

    foreach ($products as $p) {
        $st = intval($p['stock'] ?? 0);
        $min = intval($p['minStock'] ?? 2);
        $cost = floatval($p['costPrice'] ?? $p['sellingPrice'] ?? 0);

        $totalStock += $st;
        $totalValuation += ($st * $cost);

        if ($st <= 0) {
            $outOfStock++;
        } else if ($st <= $min) {
            $lowStock++;
        }
    }

    json_response('success', 'Inventory stats retrieved', [
        'totalProducts' => $totalProducts,
        'totalStock' => $totalStock,
        'outOfStock' => $outOfStock,
        'lowStock' => $lowStock,
        'totalValuation' => $totalValuation,
        'products' => $products
    ]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id']) || !isset($input['adjustment'])) {
        json_response('error', 'Product ID and stock adjustment value required');
    }

    $products = get_json_file('products') ?? [];
    $updated = false;

    foreach ($products as &$p) {
        if ($p['id'] === $input['id']) {
            $adj = intval($input['adjustment']);
            $p['stock'] = max(0, intval($p['stock']) + $adj);
            $updated = true;
            break;
        }
    }

    if ($updated) {
        save_json_file('products', $products);
        SecurityLogger::logEvent($user['username'], 'super_admin', 'API_STOCK_ADJUSTED', "Adjusted stock for product ID {$input['id']}");
        json_response('success', 'Stock level adjusted successfully');
    } else {
        json_response('error', 'Product not found');
    }
}

json_response('error', 'Invalid HTTP method');
