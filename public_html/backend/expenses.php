<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$user = require_role('super_admin');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $expenses = get_json_file('expenses') ?? [];
    json_response('success', 'Expenses retrieved', array_reverse($expenses));
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['category']) || empty($input['amount'])) {
        json_response('error', 'Expense category and amount are required');
    }

    $expenses = get_json_file('expenses') ?? [];

    $newExpense = [
        'id' => 'exp-' . time() . '-' . rand(100, 999),
        'category' => trim($input['category']),
        'vendor_shop' => trim($input['vendor_shop'] ?? ''),
        'item_details' => trim($input['item_details'] ?? ''),
        'amount' => floatval($input['amount']),
        'date' => !empty($input['date']) ? $input['date'] : date('Y-m-d'),
        'notes' => trim($input['notes'] ?? ''),
        'recordedBy' => $user['name'] ?? $user['username'] ?? 'Admin',
        'createdAt' => date('c')
    ];

    $expenses[] = $newExpense;
    save_json_file('expenses', $expenses);
    SecurityLogger::logEvent($user['username'], 'super_admin', 'API_EXPENSE_CREATED', "Recorded expense of PKR {$newExpense['amount']} for '{$newExpense['category']}'");

    json_response('success', 'Expense recorded', $newExpense);
}

if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? $_GET['id'] : '';
    $expenses = get_json_file('expenses') ?? [];
    $filtered = array_values(array_filter($expenses, function($e) use ($id) {
        return $e['id'] !== $id;
    }));
    save_json_file('expenses', $filtered);
    SecurityLogger::logEvent($user['username'], 'super_admin', 'API_EXPENSE_DELETED', "Deleted expense record ID {$id}");
    json_response('success', 'Expense deleted');
}

json_response('error', 'Invalid HTTP method');
