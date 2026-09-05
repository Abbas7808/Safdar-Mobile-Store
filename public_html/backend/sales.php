<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// -------------------------------------------------------------
// 1. GET Sales, Returns Log, or Deleted Invoices Archive
// -------------------------------------------------------------
if ($method === 'GET') {
    require_auth();
    $sales = get_json_file('sales') ?? [];
    $returnsLog = get_json_file('sales_returns') ?? [];
    $deletedLog = get_json_file('deleted_invoices') ?? [];

    // Check if requesting returns log
    if (isset($_GET['view']) && $_GET['view'] === 'returns') {
        json_response('success', 'Returns log retrieved', array_reverse($returnsLog));
    }

    // Check if requesting deleted invoices archive
    if (isset($_GET['view']) && $_GET['view'] === 'deleted') {
        json_response('success', 'Deleted invoices archive retrieved', array_reverse($deletedLog));
    }

    // Single invoice lookup
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        foreach ($sales as $s) {
            if ($s['id'] === $id || ($s['invoiceNo'] ?? '') === $id) {
                json_response('success', 'Sale details retrieved', $s);
            }
        }
        // Check in deleted archive
        foreach ($deletedLog as $del) {
            if (($del['invoiceId'] ?? '') === $id || ($del['invoiceNo'] ?? '') === $id) {
                json_response('success', 'Sale details retrieved from deleted archive', $del['saleDataSnapshot'] ?? $del);
            }
        }
        json_response('error', 'Sale invoice not found');
    }

    // Optional Filter by status
    if (isset($_GET['status']) && $_GET['status'] !== 'all') {
        $st = $_GET['status'];
        $sales = array_values(array_filter($sales, function($s) use ($st) {
            return ($s['status'] ?? 'completed') === $st;
        }));
    }

    json_response('success', 'Sales list retrieved', array_reverse($sales));
}

// -------------------------------------------------------------
// 2. POST (Create Sale OR Return/Refund Sale OR Online Order)
// -------------------------------------------------------------
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $postAction = $input['action'] ?? $action;

    // --- Action 0: Online Customer Storefront Order (Multi-Item Checkout) ---
    if ($postAction === 'online_order' || ($input['orderType'] ?? '') === 'online') {
        if (empty($input['items']) || !is_array($input['items'])) {
            json_response('error', 'Items list is required for online checkout');
        }

        $customerName = trim($input['customerName'] ?? 'Online Customer');
        $customerPhone = trim($input['customerPhone'] ?? '');
        $customerCity = trim($input['customerCity'] ?? '');
        $customerLandmark = trim($input['customerLandmark'] ?? '');
        $customerAddress = trim($input['customerAddress'] ?? '');
        $paymentMethod = trim($input['paymentMethod'] ?? '');
        $trxId = trim($input['trxId'] ?? '');
        $senderAccount = trim($input['senderAccount'] ?? '');
        $paymentProof = trim($input['paymentProof'] ?? '');

        if (empty($customerName) || empty($customerPhone) || empty($customerCity) || empty($customerAddress)) {
            json_response('error', 'Customer name, WhatsApp contact number, city, and delivery address are required.');
        }

        if (empty($trxId) || empty($paymentMethod)) {
            json_response('error', 'Advance payment is strictly required before submitting an order. Please select a payment channel and enter your valid Transaction ID (TRX).');
        }

        $products = get_json_file('products') ?? [];
        $sales = get_json_file('sales') ?? [];
        $customers = get_json_file('customers') ?? [];
        $notifications = get_json_file('notifications') ?? [];

        $cartItems = $input['items'];
        $subtotal = 0;
        $totalCost = 0;
        $processedItems = [];

        foreach ($cartItems as $item) {
            $pId = $item['id'] ?? '';
            $qty = max(1, intval($item['qty'] ?? 1));
            $name = $item['name'] ?? 'Product';
            $sellingPrice = floatval($item['price'] ?? $item['sellingPrice'] ?? 0);
            $costPrice = floatval($item['costPrice'] ?? 0);

            if ($pId) {
                foreach ($products as &$p) {
                    if ($p['id'] === $pId) {
                        $name = $p['name'];
                        if (!empty($p['hasOnlineOffer']) && !empty($p['onlinePrice']) && $p['onlinePrice'] > 0) {
                            $sellingPrice = floatval($p['onlinePrice']);
                        } else {
                            $sellingPrice = floatval($p['sellingPrice'] ?? $p['priceNumeric'] ?? $sellingPrice);
                        }
                        $costPrice = floatval($p['costPrice'] ?? 0);
                        $p['stock'] = max(0, ($p['stock'] ?? 0) - $qty);
                        break;
                    }
                }
            }

            $lineTotal = $sellingPrice * $qty;
            $subtotal += $lineTotal;
            $totalCost += ($costPrice * $qty);

            $processedItems[] = [
                'id' => $pId,
                'name' => $name,
                'sellingPrice' => $sellingPrice,
                'costPrice' => $costPrice,
                'qty' => $qty,
                'lineTotal' => $lineTotal,
                'isService' => false
            ];
        }

        save_json_file('products', $products);

        $datePrefix = date('Ymd');
        $invoiceNo = 'ONL-' . $datePrefix . '-' . str_pad(count($sales) + 1, 4, '0', STR_PAD_LEFT);
        $saleId = 'order-' . time() . '-' . rand(100, 999);
        $total = $subtotal;

        $newOrder = [
            'id' => $saleId,
            'invoiceNo' => $invoiceNo,
            'items' => $processedItems,
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $total,
            'cost' => $totalCost,
            'profit' => max(0, $total - $totalCost),
            'paymentMethod' => $paymentMethod,
            'trxId' => $trxId,
            'senderAccount' => $senderAccount,
            'paymentProof' => $paymentProof,
            'advancePaid' => true,
            'advanceAmount' => $total,
            'customerName' => $customerName,
            'customerPhone' => $customerPhone,
            'city' => $customerCity,
            'landmark' => $customerLandmark,
            'address' => $customerAddress,
            'orderType' => 'online',
            'status' => 'pending',
            'paymentStatus' => 'advance_verified_pending_dispatch',
            'source' => 'online_storefront',
            'cashier' => 'Online Storefront',
            'createdAt' => date('Y-m-d H:i:s')
        ];

        $sales[] = $newOrder;
        save_json_file('sales', $sales);

        // Update customer records
        $custFound = false;
        foreach ($customers as &$c) {
            if (($c['phone'] ?? '') === $customerPhone || strtolower($c['name'] ?? '') === strtolower($customerName)) {
                $c['totalPurchases'] = ($c['totalPurchases'] ?? 0) + 1;
                $c['totalSpent'] = ($c['totalSpent'] ?? 0) + $total;
                $c['city'] = $customerCity ?: ($c['city'] ?? '');
                $c['address'] = $customerAddress ?: ($c['address'] ?? '');
                $custFound = true;
                break;
            }
        }
        if (!$custFound) {
            $customers[] = [
                'id' => 'cust-' . time() . '-' . rand(10, 99),
                'name' => $customerName,
                'phone' => $customerPhone,
                'city' => $customerCity,
                'address' => $customerAddress,
                'totalPurchases' => 1,
                'totalSpent' => $total,
                'createdAt' => date('Y-m-d H:i:s')
            ];
        }
        save_json_file('customers', $customers);

        // Add Notification for Admin / POS
        $notifications[] = [
            'id' => 'notif-' . uniqid(),
            'title' => 'New Online Order Received (Advance Paid)!',
            'message' => "Order #$invoiceNo from $customerName ($customerCity) - PKR " . number_format($total) . " [TRX: $trxId via $paymentMethod]",
            'type' => 'online_order',
            'link' => 'sales.php?id=' . $saleId,
            'read' => false,
            'createdAt' => date('Y-m-d H:i:s')
        ];
        save_json_file('notifications', $notifications);

        // Record in MySQL Database if available
        try {
            $pdo = get_db_connection();
            if ($pdo) {
                $stmt = $pdo->prepare("INSERT INTO `sales` (`id`, `invoiceNo`, `customerName`, `customerPhone`, `items`, `subtotal`, `discount`, `total`, `cogs`, `profit`, `paymentMethod`, `cashier`, `status`, `createdAt`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $saleId,
                    $invoiceNo,
                    $customerName . ($customerCity ? " ($customerCity)" : '') . ($trxId ? " [TRX: $trxId]" : ''),
                    $customerPhone,
                    json_encode($processedItems, JSON_UNESCAPED_UNICODE),
                    $subtotal,
                    0,
                    $total,
                    $totalCost,
                    max(0, $total - $totalCost),
                    $paymentMethod . ' (Advance Paid)',
                    'Online Storefront',
                    'pending',
                    date('Y-m-d H:i:s')
                ]);
            }
        } catch (Exception $e) {
            // Suppress if DB busy
        }

        // Security Audit Log
        if (class_exists('SecurityLogger')) {
            SecurityLogger::logEvent('customer', 'guest', 'ONLINE_ORDER_PLACED', "[SUCCESS] Online customer order #$invoiceNo placed by $customerName for PKR " . number_format($total) . " with Advance TRX: $trxId via $paymentMethod");
        }

        json_response('success', 'Online order placed successfully', [
            'orderId' => $saleId,
            'invoiceNo' => $invoiceNo,
            'total' => $total,
            'customerName' => $customerName,
            'trxId' => $trxId,
            'paymentMethod' => $paymentMethod
        ]);
    }

    $user = require_auth();

    // --- Action A: Return / Refund an Existing Sale ---
    if ($postAction === 'return_sale' || $postAction === 'refund') {
        $invoiceId = $input['id'] ?? $input['invoiceId'] ?? '';
        $reason = trim($input['reason'] ?? 'Customer Return / Exchange');
        $restockItems = !empty($input['restock']) || ($input['restock'] ?? false) === true;
        
        if (empty($invoiceId)) {
            json_response('error', 'Invoice ID is required for return/refund');
        }

        $sales = get_json_file('sales') ?? [];
        $products = get_json_file('products') ?? [];
        $customers = get_json_file('customers') ?? [];
        $returnsLog = get_json_file('sales_returns') ?? [];
        $foundIndex = -1;

        foreach ($sales as $idx => $s) {
            if ($s['id'] === $invoiceId || ($s['invoiceNo'] ?? '') === $invoiceId) {
                $foundIndex = $idx;
                break;
            }
        }

        if ($foundIndex === -1) {
            json_response('error', 'Sale invoice not found for return');
        }

        $sale = &$sales[$foundIndex];

        if (($sale['status'] ?? '') === 'refunded') {
            json_response('error', 'This invoice has already been returned / refunded.');
        }

        $restockedItemsList = [];

        // Restock physical items if requested
        if ($restockItems && !empty($sale['items']) && is_array($sale['items'])) {
            foreach ($sale['items'] as $item) {
                if (empty($item['isService']) && !empty($item['id'])) {
                    $qty = intval($item['qty'] ?? 1);
                    $restockedItemsList[] = [
                        'id' => $item['id'],
                        'name' => $item['name'] ?? 'Item',
                        'qty' => $qty,
                        'sellingPrice' => $item['sellingPrice'] ?? $item['price'] ?? 0
                    ];
                    foreach ($products as &$p) {
                        if ($p['id'] === $item['id']) {
                            $p['stock'] = ($p['stock'] ?? 0) + $qty;
                            break;
                        }
                    }
                }
            }
            save_json_file('products', $products);

            // Also update MySQL products table if connected
            try {
                $pdo = get_db_connection();
                if ($pdo) {
                    foreach ($sale['items'] as $item) {
                        if (empty($item['isService']) && !empty($item['id'])) {
                            $qty = intval($item['qty'] ?? 1);
                            $stmt = $pdo->prepare("UPDATE `products` SET `stock` = `stock` + ? WHERE `id` = ?");
                            $stmt->execute([$qty, $item['id']]);
                        }
                    }
                }
            } catch (Exception $e) {}
        }

        // Mark sale as refunded
        $sale['status'] = 'refunded';
        $sale['refundReason'] = $reason;
        $sale['refundedBy'] = $user['username'];
        $sale['refundedAt'] = date('c');
        $sale['restocked'] = $restockItems;

        save_json_file('sales', $sales);

        // Record in permanent sales_returns.json log
        $returnRecord = [
            'id' => 'ret-' . time() . '-' . rand(100, 999),
            'returnNo' => 'RET-' . date('Ymd') . '-' . rand(1000, 9999),
            'invoiceId' => $sale['id'],
            'invoiceNo' => $sale['invoiceNo'],
            'customerName' => $sale['customerName'] ?? 'Walk-in Customer',
            'customerPhone' => $sale['customerPhone'] ?? '',
            'refundAmount' => floatval($sale['total'] ?? 0),
            'reason' => $reason,
            'restocked' => $restockItems,
            'restockedItems' => $restockedItemsList,
            'processedBy' => $user['username'],
            'processedByRole' => $user['role'] ?? 'admin',
            'timestamp' => date('c')
        ];
        array_unshift($returnsLog, $returnRecord);
        save_json_file('sales_returns', $returnsLog);

        // Update MySQL sales table
        try {
            $pdo = get_db_connection();
            if ($pdo) {
                $stmt = $pdo->prepare("UPDATE `sales` SET `status` = 'refunded' WHERE `id` = ? OR `invoiceNo` = ?");
                $stmt->execute([$sale['id'], $sale['invoiceNo']]);
            }
        } catch (Exception $e) {}

        // Adjust customer total spent if applicable
        if (!empty($sale['customerName']) && $sale['customerName'] !== 'Walk-in Customer') {
            foreach ($customers as &$c) {
                if ($c['name'] === $sale['customerName']) {
                    $c['totalSpent'] = max(0, ($c['totalSpent'] ?? 0) - floatval($sale['total'] ?? 0));
                    break;
                }
            }
            save_json_file('customers', $customers);
        }

        // Log audit event
        SecurityLogger::logEvent($user['username'], $user['role'], 'SALE_RETURNED', "Processed Return for Invoice #{$sale['invoiceNo']} of PKR {$sale['total']} (Reason: {$reason})");

        // Real-time notification
        add_notification(
            'sale',
            '🔄 Sale Invoice Returned / Refunded',
            "Invoice #{$sale['invoiceNo']} (PKR " . number_format($sale['total']) . ") was refunded by {$user['username']} (Reason: {$reason})",
            $sale['total'],
            $sale['customerName']
        );

        json_response('success', "Invoice #{$sale['invoiceNo']} has been returned and refunded successfully.", [
            'sale' => $sale,
            'returnRecord' => $returnRecord
        ]);
    }

    // --- Action B: Create New Sale Transaction ---
    if (empty($input['items']) || !is_array($input['items'])) {
        json_response('error', 'Items list is required for checkout');
    }

    $products = get_json_file('products') ?? [];
    $sales = get_json_file('sales') ?? [];
    $customers = get_json_file('customers') ?? [];

    $cartItems = $input['items'];
    $discount = floatval($input['discount'] ?? 0);
    $paymentMethod = $input['paymentMethod'] ?? 'cash';
    $trxId = trim($input['trxId'] ?? '');
    $customerName = trim($input['customerName'] ?? 'Walk-in Customer');
    $customerPhone = trim($input['customerPhone'] ?? '');

    $subtotal = 0;
    $totalCost = 0;
    $processedItems = [];

    foreach ($cartItems as $item) {
        $pId = $item['id'] ?? '';
        $qty = intval($item['qty'] ?? 1);
        $isService = !empty($item['isService']);

        $name = $item['name'] ?? 'Product';
        $sellingPrice = floatval($item['sellingPrice'] ?? $item['price'] ?? 0);
        $costPrice = floatval($item['costPrice'] ?? 0);

        $unit = $item['unit'] ?? 'pcs';
        $unitLabel = $item['unitLabel'] ?? 'Piece';
        $brand = $item['brand'] ?? '';
        $sku = $item['sku'] ?? '';
        $barcode = $item['barcode'] ?? '';

        if (!$isService && $pId) {
            foreach ($products as &$p) {
                if ($p['id'] === $pId) {
                    $name = $p['name'];
                    $sellingPrice = floatval($p['sellingPrice'] ?? $p['priceNumeric'] ?? 0);
                    $costPrice = floatval($p['costPrice'] ?? 0);
                    $unit = $p['unit'] ?? $unit;
                    $unitLabel = $p['unitLabel'] ?? $unitLabel;
                    $brand = $p['brand'] ?? $brand;
                    $sku = $p['sku'] ?? $sku;
                    $barcode = $p['barcode'] ?? $barcode;
                    $p['stock'] = max(0, ($p['stock'] ?? 0) - $qty);
                    break;
                }
            }
        }

        $lineTotal = $sellingPrice * $qty;
        $subtotal += $lineTotal;
        $totalCost += ($costPrice * $qty);

        $processedItems[] = [
            'id' => $pId,
            'name' => $name,
            'brand' => $brand,
            'sku' => $sku,
            'barcode' => $barcode,
            'unit' => $unit,
            'unitLabel' => $unitLabel,
            'sellingPrice' => $sellingPrice,
            'costPrice' => $costPrice,
            'qty' => $qty,
            'lineTotal' => $lineTotal,
            'isService' => $isService
        ];
    }

    save_json_file('products', $products);

    $total = max(0, $subtotal - $discount);
    $invoiceNo = 'INV-' . date('Ymd') . '-' . str_pad(count($sales) + 1, 4, '0', STR_PAD_LEFT);
    $saleId = 'sale-' . time() . '-' . rand(100, 999);

    $newSale = [
        'id' => $saleId,
        'invoiceNo' => $invoiceNo,
        'items' => $processedItems,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'total' => $total,
        'cost' => $totalCost,
        'profit' => max(0, $total - $totalCost),
        'paymentMethod' => $paymentMethod,
        'trxId' => $trxId,
        'customerName' => $customerName,
        'customerPhone' => $customerPhone,
        'status' => 'completed',
        'cashier' => $user['username'],
        'createdAt' => date('c')
    ];

    $sales[] = $newSale;
    save_json_file('sales', $sales);

    // Update customer spend
    if ($customerName && $customerName !== 'Walk-in Customer') {
        $custFound = false;
        foreach ($customers as &$c) {
            if ($c['name'] === $customerName) {
                $c['totalPurchases'] = ($c['totalPurchases'] ?? 0) + 1;
                $c['totalSpent'] = ($c['totalSpent'] ?? 0) + $total;
                $custFound = true;
                break;
            }
        }
        if (!$custFound) {
            $customers[] = [
                'id' => 'cust-' . time(),
                'name' => $customerName,
                'phone' => $customerPhone,
                'email' => '',
                'totalPurchases' => 1,
                'totalSpent' => $total,
                'balance' => 0,
                'status' => 'active'
            ];
        }
        save_json_file('customers', $customers);
    }

    // Insert into MySQL
    try {
        $pdo = get_db_connection();
        if ($pdo) {
            $stmt = $pdo->prepare("INSERT INTO `sales` (`id`, `invoiceNo`, `customerName`, `customerPhone`, `subtotal`, `discount`, `total`, `paymentMethod`, `status`, `cashier`, `createdAt`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())");
            $stmt->execute([
                $newSale['id'],
                $newSale['invoiceNo'],
                $newSale['customerName'],
                $newSale['customerPhone'],
                $newSale['subtotal'],
                $newSale['discount'],
                $newSale['total'],
                $newSale['paymentMethod'],
                $newSale['cashier']
            ]);

            foreach ($processedItems as $item) {
                if (empty($item['isService']) && !empty($item['id'])) {
                    $qty = intval($item['qty'] ?? 1);
                    $stmtStock = $pdo->prepare("UPDATE `products` SET `stock` = GREATEST(0, `stock` - ?) WHERE `id` = ?");
                    $stmtStock->execute([$qty, $item['id']]);
                }
            }
        }
    } catch (Exception $e) {}

    SecurityLogger::logEvent($user['username'], $user['role'], 'SALE_CREATED', "Generated Invoice #{$invoiceNo} of PKR {$total} ({$paymentMethod})");

    add_notification(
        'sale',
        '🛒 New POS Sale Completed',
        "Invoice #{$invoiceNo} generated for {$customerName} (PKR " . number_format($total) . ") via " . strtoupper($paymentMethod),
        $total,
        $customerName
    );

    json_response('success', 'Sale created successfully', $newSale);
}

// -------------------------------------------------------------
// 3. DELETE (Delete Sale Invoice with Full Permanent Audit Record)
// -------------------------------------------------------------
if ($method === 'DELETE') {
    $user = require_role('super_admin');
    $id = $_GET['id'] ?? '';
    $deleteReason = trim($_GET['reason'] ?? 'Super Admin Invoice Removal');
    $restock = isset($_GET['restock']) ? ($_GET['restock'] === '1' || $_GET['restock'] === 'true') : true;

    if (empty($id)) {
        json_response('error', 'Sale invoice ID is required for deletion');
    }

    $sales = get_json_file('sales') ?? [];
    $products = get_json_file('products') ?? [];
    $deletedLog = get_json_file('deleted_invoices') ?? [];
    $foundSale = null;

    $filtered = array_values(array_filter($sales, function($s) use ($id, &$foundSale) {
        if ($s['id'] === $id || ($s['invoiceNo'] ?? '') === $id) {
            $foundSale = $s;
            return false;
        }
        return true;
    }));

    if (!$foundSale) {
        json_response('error', 'Sale record not found');
    }

    $restockedItemsList = [];

    // Restore stock if requested and sale was not already refunded
    if ($restock && ($foundSale['status'] ?? '') !== 'refunded' && !empty($foundSale['items']) && is_array($foundSale['items'])) {
        foreach ($foundSale['items'] as $item) {
            if (empty($item['isService']) && !empty($item['id'])) {
                $qty = intval($item['qty'] ?? 1);
                $restockedItemsList[] = [
                    'id' => $item['id'],
                    'name' => $item['name'] ?? 'Item',
                    'qty' => $qty
                ];
                foreach ($products as &$p) {
                    if ($p['id'] === $item['id']) {
                        $p['stock'] = ($p['stock'] ?? 0) + $qty;
                        break;
                    }
                }
            }
        }
        save_json_file('products', $products);
    }

    save_json_file('sales', $filtered);

    // Save permanent record into deleted_invoices.json
    $deleteRecord = [
        'id' => 'del-' . time() . '-' . rand(100, 999),
        'deleteNo' => 'DEL-' . date('Ymd') . '-' . rand(1000, 9999),
        'invoiceId' => $foundSale['id'],
        'invoiceNo' => $foundSale['invoiceNo'],
        'customerName' => $foundSale['customerName'] ?? 'Walk-in Customer',
        'customerPhone' => $foundSale['customerPhone'] ?? '',
        'invoiceTotal' => floatval($foundSale['total'] ?? 0),
        'paymentMethod' => $foundSale['paymentMethod'] ?? 'cash',
        'originalCreatedAt' => $foundSale['createdAt'] ?? '',
        'deletedBy' => $user['username'],
        'deleteReason' => $deleteReason,
        'restocked' => $restock,
        'restockedItems' => $restockedItemsList,
        'saleDataSnapshot' => $foundSale,
        'deletedAt' => date('c')
    ];
    array_unshift($deletedLog, $deleteRecord);
    save_json_file('deleted_invoices', $deletedLog);

    // Delete from MySQL database if connected
    try {
        $pdo = get_db_connection();
        if ($pdo) {
            $stmt = $pdo->prepare("DELETE FROM `sales` WHERE `id` = ? OR `invoiceNo` = ?");
            $stmt->execute([$foundSale['id'], $foundSale['invoiceNo']]);

            if ($restock && ($foundSale['status'] ?? '') !== 'refunded' && !empty($foundSale['items'])) {
                foreach ($foundSale['items'] as $item) {
                    if (empty($item['isService']) && !empty($item['id'])) {
                        $qty = intval($item['qty'] ?? 1);
                        $stmtStock = $pdo->prepare("UPDATE `products` SET `stock` = `stock` + ? WHERE `id` = ?");
                        $stmtStock->execute([$qty, $item['id']]);
                    }
                }
            }
        }
    } catch (Exception $e) {}

    SecurityLogger::logEvent($user['username'], 'super_admin', 'SALE_DELETED', "Deleted Invoice #{$foundSale['invoiceNo']} (PKR {$foundSale['total']}). Reason: {$deleteReason}");

    json_response('success', "Sale invoice {$foundSale['invoiceNo']} deleted and archived in deletion logs successfully.", $deleteRecord);
}

json_response('error', 'Invalid HTTP method');
