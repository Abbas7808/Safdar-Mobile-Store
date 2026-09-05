<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$user = require_auth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $notifications = get_json_file('notifications') ?? [];
    $products = get_json_file('products') ?? [];

    $since = isset($_GET['since']) ? $_GET['since'] : null;

    $unreadCount = 0;
    $newNotifs = [];

    foreach ($notifications as $n) {
        if (!($n['read'] ?? false)) {
            $unreadCount++;
        }
        if ($since) {
            if (strtotime($n['timestamp']) > strtotime($since)) {
                $newNotifs[] = $n;
            }
        } else {
            if (count($newNotifs) < 15) {
                $newNotifs[] = $n;
            }
        }
    }

    $lowStockItems = [];
    foreach ($products as $p) {
        $st = intval($p['stock'] ?? 0);
        $min = intval($p['minStock'] ?? 2);
        if ($st <= $min) {
            $lowStockItems[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'sku' => $p['sku'] ?? '',
                'stock' => $st,
                'minStock' => $min
            ];
        }
    }

    json_response('success', 'Notifications retrieved', [
        'unreadCount' => $unreadCount,
        'notifications' => $newNotifs,
        'allNotifications' => array_slice($notifications, 0, 20),
        'lowStockCount' => count($lowStockItems),
        'lowStockItems' => $lowStockItems,
        'serverTime' => date('c')
    ]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $notifications = get_json_file('notifications') ?? [];

    $action = $_GET['action'] ?? ($input['action'] ?? 'mark_read');

    if ($action === 'mark_read') {
        foreach ($notifications as &$n) {
            $n['read'] = true;
        }
        save_json_file('notifications', $notifications);
        json_response('success', 'All notifications marked as read');
    }
}

json_response('error', 'Invalid HTTP method');
