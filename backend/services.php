<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$user = require_auth();
$method = $_SERVER['REQUEST_METHOD'];

// -------------------------------------------------------------
// 1. GET Services List
// -------------------------------------------------------------
if ($method === 'GET') {
    $services = get_json_file('services') ?? [];

    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        foreach ($services as $s) {
            if (($s['id'] ?? '') === $id || ($s['trxId'] ?? '') === $id) {
                json_response('success', 'Transaction details retrieved', $s);
            }
        }
        json_response('error', 'Transaction not found');
    }

    if (isset($_GET['provider']) && $_GET['provider'] !== 'all') {
        $provider = strtolower($_GET['provider']);
        $services = array_values(array_filter($services, function($t) use ($provider) {
            return strtolower($t['serviceProvider'] ?? '') === $provider;
        }));
    }

    json_response('success', 'Financial services transactions retrieved', array_reverse($services));
}

// -------------------------------------------------------------
// 2. POST (Add Service Transaction OR Reverse/Return Service)
// -------------------------------------------------------------
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $services = get_json_file('services') ?? [];
    $postAction = $input['action'] ?? 'add_transaction';

    // Reverse / Return a Financial Service Transaction
    if ($postAction === 'reverse_transaction' || $postAction === 'return_service') {
        $txId = $input['id'] ?? $input['tx_id'] ?? '';
        $reason = trim($input['reason'] ?? 'Customer cancellation / reversal');

        if (empty($txId)) {
            json_response('error', 'Transaction ID required for reversal');
        }

        $found = false;
        foreach ($services as &$t) {
            if (($t['id'] ?? '') === $txId || ($t['trxId'] ?? '') === $txId) {
                $t['status'] = 'reversed';
                $t['reversalReason'] = $reason;
                $t['reversedBy'] = $user['username'];
                $t['reversedAt'] = date('c');
                $found = true;
                break;
            }
        }

        if (!$found) {
            json_response('error', 'Transaction record not found');
        }

        save_json_file('services', $services);
        SecurityLogger::logEvent($user['username'], $user['role'], 'SERVICE_TX_REVERSED', "Reversed transaction ID {$txId} (Reason: {$reason})");

        json_response('success', 'Service transaction reversed successfully');
    }

    // Add New Financial Service Transaction
    $serviceProvider = trim($input['serviceProvider'] ?? $input['provider'] ?? 'easypaisa');
    $txType = trim($input['txType'] ?? 'cash_in');
    $amount = floatval($input['amount'] ?? 0);
    $commission = floatval($input['commission'] ?? 0);
    $customerName = trim($input['customerName'] ?? 'Walk-in Customer');
    $customerPhone = trim($input['customerPhone'] ?? '');
    $customerCnic = trim($input['customerCnic'] ?? '');
    $receiverName = trim($input['receiverName'] ?? '');
    $receiverAccount = trim($input['receiverAccount'] ?? '');
    $trxId = trim($input['trxId'] ?? '');
    $notes = trim($input['notes'] ?? '');
    $status = $input['status'] ?? 'completed';

    if ($amount <= 0) {
        json_response('error', 'Valid transaction amount greater than 0 is required');
    }

    if (empty($trxId)) {
        $prefix = strtoupper(substr($serviceProvider, 0, 2));
        $trxId = $prefix . '-' . rand(1000000000, 9999999999);
    }

    $netHandled = ($txType === 'cash_out') ? ($amount - $commission) : ($amount + $commission);

    $newTx = [
        'id' => 'tx-' . time() . '-' . rand(100, 999),
        'trxId' => $trxId,
        'serviceProvider' => $serviceProvider,
        'txType' => $txType,
        'amount' => $amount,
        'commission' => $commission,
        'netHandled' => $netHandled,
        'customerName' => $customerName,
        'customerPhone' => $customerPhone,
        'customerCnic' => $customerCnic,
        'receiverName' => $receiverName ?: ($txType === 'cash_out' ? 'Self (Cash Out)' : 'Beneficiary'),
        'receiverAccount' => $receiverAccount ?: $customerPhone,
        'status' => $status,
        'notes' => $notes,
        'loggedBy' => $user['username'],
        'createdAt' => date('c'),
        'timestamp' => date('c')
    ];

    array_unshift($services, $newTx);
    save_json_file('services', $services);

    SecurityLogger::logEvent($user['username'], $user['role'], 'FINANCIAL_TX_RECORDED', "Recorded {$txType} of PKR {$amount} via {$serviceProvider} with PKR {$commission} commission");

    // Real-time Notification
    add_notification(
        'payment',
        '💳 Financial Service Recorded',
        "{$serviceProvider} {$txType} of PKR " . number_format($amount) . " for {$customerName} (Commission: +PKR " . number_format($commission) . ")",
        $amount,
        $customerName
    );

    json_response('success', "Transaction {$trxId} recorded successfully", $newTx);
}

// -------------------------------------------------------------
// 3. DELETE (Delete Service Transaction — Super Admin Only)
// -------------------------------------------------------------
if ($method === 'DELETE') {
    $user = require_role('super_admin');
    $id = $_GET['id'] ?? '';

    if (empty($id)) {
        json_response('error', 'Transaction ID required for deletion');
    }

    $services = get_json_file('services') ?? [];
    $found = null;

    $filtered = array_values(array_filter($services, function($t) use ($id, &$found) {
        if (($t['id'] ?? '') === $id || ($t['trxId'] ?? '') === $id) {
            $found = $t;
            return false;
        }
        return true;
    }));

    if (!$found) {
        json_response('error', 'Transaction record not found');
    }

    save_json_file('services', $filtered);
    SecurityLogger::logEvent($user['username'], 'super_admin', 'API_SERVICE_TX_DELETED', "Deleted transaction ID {$id} (" . ($found['serviceProvider'] ?? 'Service') . ")");

    json_response('success', "Transaction {$id} deleted successfully");
}

json_response('error', 'Invalid HTTP method');
