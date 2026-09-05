<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$sessionUser = get_session_user();

if ($method === 'GET') {
    $products = get_json_file('products') ?? [];

    // Hide costPrice from Salesman role
    if ($sessionUser && ($sessionUser['role'] ?? '') === 'salesman') {
        $products = array_map(function($p) {
            unset($p['costPrice']);
            return $p;
        }, $products);
    }
    
    // Filter by ID if requested
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        foreach ($products as $p) {
            if ($p['id'] === $id) {
                json_response('success', 'Product retrieved', $p);
            }
        }
        json_response('error', 'Product not found', null);
    }

    // Filter by Category
    if (isset($_GET['category']) && $_GET['category'] !== 'all') {
        $cat = $_GET['category'];
        $products = array_values(array_filter($products, function($p) use ($cat) {
            return ($p['category'] === $cat || ($p['categoryId'] ?? '') === $cat);
        }));
    }

    // Filter by Status
    if (isset($_GET['status'])) {
        $st = $_GET['status'];
        $products = array_values(array_filter($products, function($p) use ($st) {
            return ($p['status'] ?? 'active') === $st;
        }));
    }

    json_response('success', 'Products retrieved', $products);
}

if ($method === 'POST') {
    $user = require_role('super_admin');
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['name'])) {
        json_response('error', 'Product name is required');
    }

    $products = get_json_file('products') ?? [];

    // Edit case
    if (!empty($input['id'])) {
        $updated = false;
        foreach ($products as &$p) {
            if ($p['id'] === $input['id']) {
                $p = array_merge($p, $input);
                $updated = true;
                break;
            }
        }
        if ($updated) {
            save_json_file('products', $products);
            SecurityLogger::logEvent($user['username'], 'super_admin', 'API_PRODUCT_UPDATED', "Updated product ID {$input['id']}");
            json_response('success', 'Product updated successfully');
        } else {
            json_response('error', 'Product not found for update');
        }
    }

    // Add case
    $newId = 'prod-' . time();
    $newProduct = array_merge([
        'id' => $newId,
        'brand' => 'generic',
        'category' => 'accessories',
        'categoryId' => 'accessories',
        'sellingPrice' => 0,
        'costPrice' => 0,
        'priceNumeric' => 0,
        'priceRange' => 'PKR 0',
        'stock' => 0,
        'minStock' => 2,
        'sku' => 'SKU-' . time(),
        'barcode' => '' . rand(1000000000, 9999999999),
        'status' => 'active',
        'badge' => 'NEW LISTED',
        'createdAt' => date('c'),
        'isNewArrival' => false,
        'specs' => []
    ], $input);

    array_unshift($products, $newProduct);
    save_json_file('products', $products);
    SecurityLogger::logEvent($user['username'], 'super_admin', 'API_PRODUCT_CREATED', "Created product '{$newProduct['name']}'");
    json_response('success', 'Product created successfully', $newProduct);
}

if ($method === 'DELETE') {
    $user = require_role('super_admin');
    $id = isset($_GET['id']) ? $_GET['id'] : '';
    if (empty($id)) {
        json_response('error', 'Product ID required for deletion');
    }

    $products = get_json_file('products') ?? [];
    $filtered = array_values(array_filter($products, function($p) use ($id) {
        return $p['id'] !== $id;
    }));

    save_json_file('products', $filtered);
    SecurityLogger::logEvent($user['username'], 'super_admin', 'API_PRODUCT_DELETED', "Deleted product ID {$id}");
    json_response('success', 'Product deleted successfully');
}

json_response('error', 'Invalid HTTP method');
