<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $brands = get_json_file('brands') ?? [];
    json_response('success', 'Brands retrieved', $brands);
}

if ($method === 'POST') {
    $user = require_role('super_admin');
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['name'])) {
        json_response('error', 'Brand name required');
    }

    $brands = get_json_file('brands') ?? [];

    if (!empty($input['id'])) {
        foreach ($brands as &$b) {
            if ($b['id'] === $input['id']) {
                $b = array_merge($b, $input);
                break;
            }
        }
    } else {
        $newId = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $input['name']));
        $brands[] = [
            'id' => $newId,
            'name' => $input['name'],
            'category' => $input['category'] ?? 'mobiles',
            'status' => $input['status'] ?? 'active'
        ];
    }

    save_json_file('brands', $brands);
    SecurityLogger::logEvent($user['username'], 'super_admin', 'API_BRAND_SAVED', "Saved brand '{$input['name']}'");
    json_response('success', 'Brand saved successfully');
}

json_response('error', 'Invalid HTTP method');
