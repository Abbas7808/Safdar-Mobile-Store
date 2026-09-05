<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$user = require_role('super_admin');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $suppliers = get_json_file('suppliers') ?? [];
    json_response('success', 'Suppliers retrieved', $suppliers);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['name'])) {
        json_response('error', 'Supplier name required');
    }

    $suppliers = get_json_file('suppliers') ?? [];

    if (!empty($input['id'])) {
        foreach ($suppliers as &$s) {
            if ($s['id'] === $input['id']) {
                $s = array_merge($s, $input);
                break;
            }
        }
    } else {
        $suppliers[] = [
            'id' => 'sup-' . time(),
            'name' => trim($input['name']),
            'company' => trim($input['company'] ?? ''),
            'phone' => trim($input['phone'] ?? ''),
            'email' => trim($input['email'] ?? ''),
            'address' => trim($input['address'] ?? ''),
            'status' => 'active'
        ];
    }

    save_json_file('suppliers', $suppliers);
    SecurityLogger::logEvent($user['username'], 'super_admin', 'API_SUPPLIER_SAVED', "Saved supplier details for '{$input['name']}'");
    json_response('success', 'Supplier saved successfully');
}

json_response('error', 'Invalid HTTP method');
