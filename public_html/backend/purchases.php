<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$user = require_role('super_admin');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $purchases = get_json_file('purchases') ?? [];
    json_response('success', 'Purchases retrieved', array_reverse($purchases));
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? 'create';

    $purchases = get_json_file('purchases') ?? [];
    $products = get_json_file('products') ?? [];

    // 1. Settle Due Payment
    if ($action === 'settle_due') {
        $poId = $input['poId'] ?? '';
        $amount = floatval($input['amount'] ?? 0);
        $payMethod = $input['method'] ?? 'Cash';
        $payNotes = $input['notes'] ?? 'Installment payment';

        if (!$poId || $amount <= 0) {
            json_response('error', 'Valid PO ID and payment amount required');
        }

        $found = false;
        foreach ($purchases as &$po) {
            if ($po['id'] === $poId) {
                $found = true;
                $po['paidAmount'] = floatval($po['paidAmount'] ?? 0) + $amount;
                if ($po['paidAmount'] > $po['totalAmount']) {
                    $po['paidAmount'] = $po['totalAmount'];
                }
                $po['pendingAmount'] = max(0, round($po['totalAmount'] - $po['paidAmount'], 2));
                $po['paymentStatus'] = $po['pendingAmount'] <= 0 ? 'paid' : ($po['paidAmount'] > 0 ? 'partial' : 'pending');

                if (!isset($po['paymentHistory']) || !is_array($po['paymentHistory'])) {
                    $po['paymentHistory'] = [];
                }
                $po['paymentHistory'][] = [
                    'amount' => $amount,
                    'date' => date('Y-m-d H:i'),
                    'method' => $payMethod,
                    'notes' => $payNotes
                ];
                break;
            }
        }

        if ($found) {
            save_json_file('purchases', $purchases);
            SecurityLogger::logEvent($user['username'], 'super_admin', 'API_PURCHASE_SETTLED', "Recorded installment payment of PKR {$amount} for PO {$poId}");
            json_response('success', 'Payment recorded successfully');
        } else {
            json_response('error', 'Purchase order not found');
        }
    }

    // 2. Delete PO
    elseif ($action === 'delete') {
        $poId = $input['poId'] ?? '';
        $purchases = array_values(array_filter($purchases, function($p) use ($poId) {
            return $p['id'] !== $poId;
        }));
        save_json_file('purchases', $purchases);
        SecurityLogger::logEvent($user['username'], 'super_admin', 'API_PURCHASE_DELETED', "Deleted PO {$poId}");
        json_response('success', 'Purchase order deleted');
    }

    // 3. Create PO
    if (empty($input['supplierName']) || empty($input['totalAmount'])) {
        json_response('error', 'Supplier name and total purchase amount are required');
    }

    $poNo = 'PO-' . date('Ymd') . '-' . sprintf('%03d', count($purchases) + 1);

    // Increase product stock if items passed
    if (!empty($input['items']) && is_array($input['items'])) {
        foreach ($input['items'] as $item) {
            $itemId = $item['productId'] ?? ($item['id'] ?? '');
            foreach ($products as &$p) {
                if ($p['id'] === $itemId) {
                    $p['stock'] = floatval($p['stock'] ?? 0) + floatval($item['qty'] ?? 1);
                    if (!empty($item['costPrice']) && floatval($item['costPrice']) > 0) {
                        $p['costPrice'] = floatval($item['costPrice']);
                    }
                    if (!empty($item['sellingPrice']) && floatval($item['sellingPrice']) > 0) {
                        $p['sellingPrice'] = floatval($item['sellingPrice']);
                    }
                    break;
                }
            }
        }
        save_json_file('products', $products);
    }

    $totalAmount = floatval($input['totalAmount']);
    $paidAmount = isset($input['paidAmount']) ? floatval($input['paidAmount']) : $totalAmount;
    $pendingAmount = isset($input['pendingAmount']) ? floatval($input['pendingAmount']) : max(0, round($totalAmount - $paidAmount, 2));
    
    $paymentStatus = $input['paymentStatus'] ?? ($pendingAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending'));

    $newPO = [
        'id' => 'po-' . time() . '-' . rand(100, 999),
        'poNo' => $poNo,
        'invoiceNo' => trim($input['invoiceNo'] ?? ('INV-' . rand(1000, 9999))),
        'supplierName' => trim($input['supplierName']),
        'supplierPhone' => trim($input['supplierPhone'] ?? ''),
        'totalAmount' => $totalAmount,
        'paidAmount' => $paidAmount,
        'pendingAmount' => $pendingAmount,
        'paymentStatus' => $paymentStatus,
        'paymentMethod' => $input['paymentMethod'] ?? 'Cash',
        'items' => $input['items'] ?? [],
        'notes' => trim($input['notes'] ?? ''),
        'paymentHistory' => [
            [
                'amount' => $paidAmount,
                'date' => $input['date'] ?? date('Y-m-d'),
                'method' => $input['paymentMethod'] ?? 'Cash',
                'notes' => 'Initial payment'
            ]
        ],
        'date' => $input['date'] ?? date('Y-m-d'),
        'createdAt' => date('c')
    ];

    $purchases[] = $newPO;
    save_json_file('purchases', $purchases);
    SecurityLogger::logEvent($user['username'], 'super_admin', 'API_PURCHASE_CREATED', "Recorded PO #{$poNo} (Invoice #{$newPO['invoiceNo']}) for PKR " . number_format($newPO['totalAmount']));

    json_response('success', 'Purchase Order recorded', $newPO);
}

json_response('error', 'Invalid HTTP method');
