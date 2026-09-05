<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    require_role('super_admin');
    $audit = get_json_file('audit') ?? [];
    json_response('success', 'Audit trail retrieved', array_reverse($audit));
}

json_response('error', 'Invalid HTTP method');
