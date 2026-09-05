<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $settings = get_json_file('settings') ?? [];
    json_response('success', 'Settings retrieved', $settings);
}

if ($method === 'POST') {
    $user = require_role('super_admin');
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        json_response('error', 'Invalid settings data');
    }

    $current = get_json_file('settings') ?? [];
    $updated = array_merge($current, $input);
    save_json_file('settings', $updated);
    SecurityLogger::logEvent($user['username'], 'super_admin', 'API_SETTINGS_UPDATED', 'Updated store configuration settings via API');

    json_response('success', 'Store settings updated successfully', $updated);
}

json_response('error', 'Invalid HTTP method');
