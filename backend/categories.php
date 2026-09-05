<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $categories = get_json_file('categories') ?? [];
    json_response('success', 'Categories retrieved', $categories);
}

if ($method === 'POST') {
    $user = require_role('super_admin');
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['name'])) {
        json_response('error', 'Category name required');
    }

    $categories = get_json_file('categories') ?? [];

    if (!empty($input['id'])) {
        foreach ($categories as &$c) {
            if ($c['id'] === $input['id']) {
                $c = array_merge($c, $input);
                break;
            }
        }
    } else {
        $newId = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $input['name']));
        $categories[] = [
            'id' => $newId,
            'name' => $input['name'],
            'status' => $input['status'] ?? 'active',
            'productCount' => 0
        ];
    }

    save_json_file('categories', $categories);
    SecurityLogger::logEvent($user['username'], 'super_admin', 'API_CATEGORY_SAVED', "Saved category '{$input['name']}'");
    json_response('success', 'Category saved successfully');
}

json_response('error', 'Invalid HTTP method');
