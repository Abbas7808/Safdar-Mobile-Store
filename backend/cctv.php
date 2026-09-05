<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$user = require_auth();
$method = $_SERVER['REQUEST_METHOD'];

// -------------------------------------------------------------
// 1. GET CCTV Projects (All, Search by Query, Returns, or Deleted Archive)
// -------------------------------------------------------------
if ($method === 'GET') {
    $type = $_GET['type'] ?? 'all';

    if ($type === 'returns') {
        $returns = get_json_file('cctv_returns') ?? [];
        json_response('success', 'CCTV returns retrieved', array_reverse($returns));
    }

    if ($type === 'deleted') {
        $deleted = get_json_file('cctv_deleted') ?? [];
        json_response('success', 'CCTV deleted archive retrieved', array_reverse($deleted));
    }

    $cctv = get_json_file('cctv') ?? [];

    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        foreach ($cctv as $proj) {
            if (($proj['id'] ?? '') === $id || ($proj['projectNo'] ?? '') === $id) {
                json_response('success', 'CCTV project details retrieved', $proj);
            }
        }
        json_response('error', 'CCTV project not found');
    }

    if (isset($_GET['status']) && $_GET['status'] !== 'all') {
        $st = strtolower($_GET['status']);
        $cctv = array_values(array_filter($cctv, function($p) use ($st) {
            return strtolower($p['status'] ?? '') === $st;
        }));
    }

    if (isset($_GET['q'])) {
        $q = strtolower(trim($_GET['q']));
        $cctv = array_values(array_filter($cctv, function($p) use ($q) {
            return strpos(strtolower($p['clientName'] ?? ''), $q) !== false ||
                   strpos(strtolower($p['clientPhone'] ?? ''), $q) !== false ||
                   strpos(strtolower($p['siteAddress'] ?? ''), $q) !== false ||
                   strpos(strtolower($p['projectNo'] ?? ''), $q) !== false ||
                   strpos(strtolower($p['cameraBrand'] ?? ''), $q) !== false;
        }));
    }

    json_response('success', 'CCTV records retrieved', array_reverse($cctv));
}

// -------------------------------------------------------------
// 2. POST (Add, Update, Return / Cancel, or Delete CCTV Project)
// -------------------------------------------------------------
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $action = $input['action'] ?? 'save';

    // ---------------------------------------------------------
    // ACTION A: RETURN / CANCEL CCTV PROJECT
    // ---------------------------------------------------------
    if ($action === 'return') {
        $id = $input['id'] ?? '';
        $reason = trim($input['reason'] ?? 'Client requested cancellation / return');
        $refundAmount = floatval($input['refundAmount'] ?? 0);
        $equipmentReturned = !empty($input['equipmentReturned']);

        if (empty($id)) {
            json_response('error', 'CCTV Project ID or Project # is required for return');
        }

        $cctv = get_json_file('cctv') ?? [];
        $returns = get_json_file('cctv_returns') ?? [];
        $found = null;

        foreach ($cctv as &$p) {
            if (($p['id'] ?? '') === $id || ($p['projectNo'] ?? '') === $id) {
                if (strtolower($p['status'] ?? '') === 'returned' || strtolower($p['status'] ?? '') === 'cancelled') {
                    json_response('error', 'This CCTV project has already been returned or cancelled');
                }

                $p['status'] = 'returned';
                $p['returnReason'] = $reason;
                $p['refundAmount'] = $refundAmount;
                $p['equipmentReturned'] = $equipmentReturned;
                $p['returnedAt'] = date('c');
                $p['returnedBy'] = $user['name'] ?? $user['username'] ?? 'Admin';
                $found = $p;
                break;
            }
        }

        if (!$found) {
            json_response('error', 'CCTV project record not found');
        }

        // Record permanent return log
        $returnRecord = [
            'id' => 'cctv-ret-' . time() . '-' . rand(100, 999),
            'projectId' => $found['id'],
            'projectNo' => $found['projectNo'],
            'clientName' => $found['clientName'],
            'clientPhone' => $found['clientPhone'] ?? '',
            'siteAddress' => $found['siteAddress'] ?? '',
            'cameraBrand' => $found['cameraBrand'] ?? '',
            'systemPackage' => $found['systemPackage'] ?? '',
            'originalTotalBill' => floatval($found['totalBill'] ?? 0),
            'originalAdvancePaid' => floatval($found['advancePaid'] ?? 0),
            'refundAmount' => $refundAmount,
            'reason' => $reason,
            'equipmentReturned' => $equipmentReturned,
            'returnedBy' => $user['username'] ?? $user['name'] ?? 'Admin',
            'returnedAt' => date('c')
        ];

        array_unshift($returns, $returnRecord);
        save_json_file('cctv_returns', $returns);
        save_json_file('cctv', $cctv);

        SecurityLogger::logEvent(
            $user['username'],
            $user['role'],
            'CCTV_PROJECT_RETURNED',
            "Returned/Cancelled CCTV Project #{$found['projectNo']} ({$found['clientName']}) - Refund PKR {$refundAmount}, Reason: {$reason}"
        );

        add_notification(
            'service',
            '🔄 CCTV Project Return / Cancellation',
            "CCTV #{$found['projectNo']} for {$found['clientName']} was returned by {$user['username']} (Refund: PKR " . number_format($refundAmount) . ", Reason: {$reason})",
            $refundAmount,
            $found['clientName']
        );

        json_response('success', "CCTV Project #{$found['projectNo']} has been marked as returned / refunded successfully.", [
            'project' => $found,
            'returnRecord' => $returnRecord
        ]);
    }

    // ---------------------------------------------------------
    // ACTION B: PERMANENT DELETE (Super Admin with Audit Archive)
    // ---------------------------------------------------------
    if ($action === 'delete') {
        $user = require_role('super_admin');
        $id = $input['id'] ?? '';
        $reason = trim($input['reason'] ?? 'Deleted by Super Admin');

        if (empty($id)) {
            json_response('error', 'CCTV project ID required for deletion');
        }

        $cctv = get_json_file('cctv') ?? [];
        $deletedArchive = get_json_file('cctv_deleted') ?? [];
        $found = null;

        $filtered = array_values(array_filter($cctv, function($p) use ($id, &$found) {
            if (($p['id'] ?? '') === $id || ($p['projectNo'] ?? '') === $id) {
                $found = $p;
                return false;
            }
            return true;
        }));

        if (!$found) {
            json_response('error', 'CCTV project record not found');
        }

        $deleteRecord = [
            'id' => 'cctv-del-' . time() . '-' . rand(100, 999),
            'projectId' => $found['id'] ?? '',
            'projectNo' => $found['projectNo'] ?? '',
            'clientName' => $found['clientName'] ?? '',
            'clientPhone' => $found['clientPhone'] ?? '',
            'siteAddress' => $found['siteAddress'] ?? '',
            'cameraBrand' => $found['cameraBrand'] ?? '',
            'totalBill' => floatval($found['totalBill'] ?? 0),
            'advancePaid' => floatval($found['advancePaid'] ?? 0),
            'status' => $found['status'] ?? '',
            'reason' => $reason,
            'deletedBy' => $user['username'] ?? $user['name'] ?? 'Super Admin',
            'deletedAt' => date('c'),
            'originalRecord' => $found
        ];

        array_unshift($deletedArchive, $deleteRecord);
        save_json_file('cctv_deleted', $deletedArchive);
        save_json_file('cctv', $filtered);

        SecurityLogger::logEvent(
            $user['username'],
            'super_admin',
            'CCTV_PROJECT_DELETED',
            "Permanently deleted CCTV Project #{$found['projectNo']} ({$found['clientName']}) - Reason: {$reason}"
        );

        add_notification(
            'service',
            '🗑️ CCTV Project Deleted',
            "CCTV #{$found['projectNo']} for {$found['clientName']} was deleted by Super Admin {$user['username']} (Reason: {$reason})",
            floatval($found['totalBill'] ?? 0),
            $found['clientName']
        );

        json_response('success', "CCTV project {$found['projectNo']} deleted and archived successfully");
    }

    // ---------------------------------------------------------
    // ACTION C: SAVE / UPDATE CCTV INSTALLATION
    // ---------------------------------------------------------
    $cctv = get_json_file('cctv') ?? [];
    $id = $input['id'] ?? '';

    $clientName = trim($input['clientName'] ?? '');
    $clientPhone = trim($input['clientPhone'] ?? '');
    $clientCnic = trim($input['clientCnic'] ?? '');
    $siteType = trim($input['siteType'] ?? 'commercial');
    $siteAddress = trim($input['siteAddress'] ?? 'Main Bazar Hangu');
    $systemPackage = trim($input['systemPackage'] ?? '4-Channel HD Security Package');
    $cameraBrand = trim($input['cameraBrand'] ?? 'Hikvision');
    $cameraMegapixel = trim($input['cameraMegapixel'] ?? $input['cameraResolution'] ?? '5MP Super HD');
    $cameraCount = intval($input['cameraCount'] ?? 4);
    $cameraPrice = floatval($input['cameraPrice'] ?? 3500);
    $cameraDiscount = floatval($input['cameraDiscount'] ?? 0);
    $cameraAmount = floatval($input['cameraAmount'] ?? max(0, ($cameraCount * $cameraPrice) - $cameraDiscount));

    $dvrChannels = intval($input['dvrChannels'] ?? 4);
    $dvrModel = trim($input['dvrModel'] ?? '');
    $dvrQty = isset($input['dvrQty']) ? intval($input['dvrQty']) : ($dvrChannels > 0 ? 1 : 0);
    $dvrPrice = floatval($input['dvrPrice'] ?? 6500);
    $dvrDiscount = floatval($input['dvrDiscount'] ?? 0);
    $dvrAmount = floatval($input['dvrAmount'] ?? max(0, ($dvrQty * $dvrPrice) - $dvrDiscount));
    if (empty($dvrModel)) {
        $dvrModel = $dvrChannels > 0 ? "{$dvrChannels}-Channel DVR / XVR Unit" : "Wi-Fi / Standalone Setup";
    }

    // Storage Drives Multi-row
    $rawStorageDrives = $input['storageDrives'] ?? [];
    $storageDrives = [];
    if (is_array($rawStorageDrives)) {
        foreach ($rawStorageDrives as $sd) {
            $stype = trim($sd['type'] ?? '1TB Surveillance HDD');
            $sqty = intval($sd['qty'] ?? 1);
            if ($sqty < 1) $sqty = 1;
            $sprice = floatval($sd['price'] ?? 0);
            $sdisc = floatval($sd['discount'] ?? 0);
            $samt = floatval($sd['amount'] ?? max(0, ($sqty * $sprice) - $sdisc));
            $storageDrives[] = [
                'type' => $stype,
                'qty' => $sqty,
                'price' => $sprice,
                'discount' => $sdisc,
                'amount' => $samt
            ];
        }
    }
    $storageHdd = trim($input['storageHdd'] ?? '');
    if (empty($storageHdd) && !empty($storageDrives)) {
        $hddParts = [];
        foreach ($storageDrives as $sd) {
            $hddParts[] = ($sd['qty'] > 1 ? "{$sd['qty']}x " : "") . $sd['type'];
        }
        $storageHdd = implode(', ', $hddParts);
    }
    if (empty($storageHdd)) $storageHdd = '1TB Surveillance HDD';

    // Power Supplies Multi-row
    $rawPowerSupplies = $input['powerSupplies'] ?? [];
    $powerSupplies = [];
    if (is_array($rawPowerSupplies)) {
        foreach ($rawPowerSupplies as $ps) {
            $ptype = trim($ps['type'] ?? '12V 5A Central Supply');
            $pqty = intval($ps['qty'] ?? 1);
            if ($pqty < 1) $pqty = 1;
            $pprice = floatval($ps['price'] ?? 0);
            $pdisc = floatval($ps['discount'] ?? 0);
            $pamt = floatval($ps['amount'] ?? max(0, ($pqty * $pprice) - $pdisc));
            $powerSupplies[] = [
                'type' => $ptype,
                'qty' => $pqty,
                'price' => $pprice,
                'discount' => $pdisc,
                'amount' => $pamt
            ];
        }
    }
    $powerSupply = trim($input['powerSupply'] ?? '');
    if (empty($powerSupply) && !empty($powerSupplies)) {
        $psuParts = [];
        foreach ($powerSupplies as $ps) {
            $psuParts[] = ($ps['qty'] > 1 ? "{$ps['qty']}x " : "") . $ps['type'];
        }
        $powerSupply = implode(', ', $psuParts);
    }
    if (empty($powerSupply)) $powerSupply = '12V 5A Central Supply';
    $powerSupplyAmp = trim($input['powerSupplyAmp'] ?? (!empty($powerSupplies) ? $powerSupplies[0]['type'] : '12V 5A Central Supply'));
    $powerSupplyQty = intval($input['powerSupplyQty'] ?? (!empty($powerSupplies) ? count($powerSupplies) : 1));
    if ($powerSupplyQty < 1) $powerSupplyQty = 1;
    $powerSupplyPrice = floatval($input['powerSupplyPrice'] ?? (!empty($powerSupplies) ? $powerSupplies[0]['price'] : 2500));
    $powerSupplyDiscount = floatval($input['powerSupplyDiscount'] ?? 0);
    $powerSupplyAmount = floatval($input['powerSupplyAmount'] ?? (!empty($powerSupplies) ? array_sum(array_column($powerSupplies, 'amount')) : 2500));

    $cablesFootage = trim($input['cablesFootage'] ?? '150m RG59');
    $accessories = trim($input['accessories'] ?? 'BNC & DC Jacks');
    
    // Custom manual items
    $customItems = $input['customItems'] ?? [];
    if (!is_array($customItems)) $customItems = [];
    $sanitizedCustomItems = [];
    foreach ($customItems as $cItem) {
        $cName = trim($cItem['name'] ?? '');
        if (!empty($cName)) {
            $cSubCategory = trim($cItem['subCategory'] ?? 'General Materials');
            $cQty = floatval($cItem['qty'] ?? 1);
            if ($cQty <= 0) $cQty = 1;
            $cUnit = trim($cItem['unit'] ?? 'Pcs');
            $cPrice = floatval($cItem['price'] ?? 0);
            $cDisc = floatval($cItem['discount'] ?? 0);
            $cAmount = floatval($cItem['amount'] ?? max(0, ($cQty * $cPrice) - $cDisc));
            $sanitizedCustomItems[] = [
                'subCategory' => $cSubCategory,
                'name' => $cName,
                'qty' => $cQty,
                'unit' => !empty($cUnit) ? $cUnit : 'Pcs',
                'price' => $cPrice,
                'discount' => $cDisc,
                'amount' => $cAmount
            ];
        }
    }

    $equipmentGross = floatval($input['equipmentGross'] ?? 0);
    $itemsDiscountTotal = floatval($input['itemsDiscountTotal'] ?? 0);
    $equipmentCost = floatval($input['equipmentCost'] ?? 0);
    $discount = floatval($input['discount'] ?? 0);
    $laborFee = floatval($input['laborFee'] ?? 0);
    $totalBill = floatval($input['totalBill'] ?? max(0, $equipmentCost - $discount + $laborFee));
    $advancePaid = floatval($input['advancePaid'] ?? 0);
    $remainingPayment = floatval($input['remainingPayment'] ?? max(0, $totalBill - $advancePaid));
    $creditAmount = floatval($input['creditAmount'] ?? $remainingPayment);
    $paymentMethod = trim($input['paymentMethod'] ?? 'Cash');
    $creditNote = trim($input['creditNote'] ?? '');
    $creditDueDate = trim($input['creditDueDate'] ?? '');
    $status = trim($input['status'] ?? 'installed');
    $technician = trim($input['technician'] ?? $user['name'] ?? 'Safdar');
    $installedDate = trim($input['installedDate'] ?? date('Y-m-d'));
    $warrantyExpiry = trim($input['warrantyExpiry'] ?? date('Y-m-d', strtotime('+1 year')));
    $notes = trim($input['notes'] ?? '');

    if (empty($clientName)) {
        json_response('error', 'Client Name is required');
    }

    if (!empty($id)) {
        // Update existing record
        $found = false;
        foreach ($cctv as &$proj) {
            if ($proj['id'] === $id) {
                $proj['clientName'] = $clientName;
                $proj['clientPhone'] = $clientPhone;
                $proj['clientCnic'] = $clientCnic;
                $proj['siteType'] = $siteType;
                $proj['siteAddress'] = $siteAddress;
                $proj['systemPackage'] = $systemPackage;
                $proj['cameraBrand'] = $cameraBrand;
                $proj['cameraMegapixel'] = $cameraMegapixel;
                $proj['cameraCount'] = $cameraCount;
                $proj['cameraPrice'] = $cameraPrice;
                $proj['cameraDiscount'] = $cameraDiscount;
                $proj['cameraAmount'] = $cameraAmount;
                $proj['dvrChannels'] = $dvrChannels;
                $proj['dvrModel'] = $dvrModel;
                $proj['dvrQty'] = $dvrQty;
                $proj['dvrPrice'] = $dvrPrice;
                $proj['dvrDiscount'] = $dvrDiscount;
                $proj['dvrAmount'] = $dvrAmount;
                $proj['storageHdd'] = $storageHdd;
                $proj['storageDrives'] = $storageDrives;
                $proj['powerSupplies'] = $powerSupplies;
                $proj['powerSupply'] = $powerSupply;
                $proj['powerSupplyAmp'] = $powerSupplyAmp;
                $proj['powerSupplyQty'] = $powerSupplyQty;
                $proj['powerSupplyPrice'] = $powerSupplyPrice;
                $proj['powerSupplyDiscount'] = $powerSupplyDiscount;
                $proj['powerSupplyAmount'] = $powerSupplyAmount;
                $proj['cablesFootage'] = $cablesFootage;
                $proj['accessories'] = $accessories;
                $proj['customItems'] = $sanitizedCustomItems;
                $proj['equipmentGross'] = $equipmentGross;
                $proj['itemsDiscountTotal'] = $itemsDiscountTotal;
                $proj['equipmentCost'] = $equipmentCost;
                $proj['discount'] = $discount;
                $proj['laborFee'] = $laborFee;
                $proj['totalBill'] = $totalBill;
                $proj['advancePaid'] = $advancePaid;
                $proj['remainingPayment'] = $remainingPayment;
                $proj['creditAmount'] = $creditAmount;
                $proj['paymentMethod'] = $paymentMethod;
                $proj['creditNote'] = $creditNote;
                $proj['creditDueDate'] = $creditDueDate;
                $proj['status'] = $status;
                $proj['technician'] = $technician;
                $proj['installedDate'] = $installedDate;
                $proj['warrantyExpiry'] = $warrantyExpiry;
                $proj['notes'] = $notes;
                $proj['updatedAt'] = date('c');
                $found = true;
                break;
            }
        }

        if (!$found) {
            json_response('error', 'CCTV project record not found for update');
        }

        save_json_file('cctv', $cctv);
        SecurityLogger::logEvent($user['username'], $user['role'], 'CCTV_PROJECT_UPDATED', "Updated CCTV project ID {$id} for {$clientName}");
        json_response('success', 'CCTV project details updated successfully');
    } else {
        // Create new project record
        $projectNo = 'CCTV-' . date('Ymd') . '-' . str_pad(count($cctv) + 1, 3, '0', STR_PAD_LEFT);
        $newProject = [
            'id' => 'cctv-' . time() . '-' . rand(100, 999),
            'projectNo' => $projectNo,
            'clientName' => $clientName,
            'clientPhone' => $clientPhone,
            'clientCnic' => $clientCnic,
            'siteType' => $siteType,
            'siteAddress' => $siteAddress,
            'systemPackage' => $systemPackage,
            'cameraBrand' => $cameraBrand,
            'cameraMegapixel' => $cameraMegapixel,
            'cameraCount' => $cameraCount,
            'cameraPrice' => $cameraPrice,
            'cameraDiscount' => $cameraDiscount,
            'cameraAmount' => $cameraAmount,
            'dvrChannels' => $dvrChannels,
            'dvrModel' => $dvrModel,
            'dvrQty' => $dvrQty,
            'dvrPrice' => $dvrPrice,
            'dvrDiscount' => $dvrDiscount,
            'dvrAmount' => $dvrAmount,
            'storageHdd' => $storageHdd,
            'storageDrives' => $storageDrives,
            'powerSupplies' => $powerSupplies,
            'powerSupply' => $powerSupply,
            'powerSupplyAmp' => $powerSupplyAmp,
            'powerSupplyQty' => $powerSupplyQty,
            'powerSupplyPrice' => $powerSupplyPrice,
            'powerSupplyDiscount' => $powerSupplyDiscount,
            'powerSupplyAmount' => $powerSupplyAmount,
            'cablesFootage' => $cablesFootage,
            'accessories' => $accessories,
            'customItems' => $sanitizedCustomItems,
            'equipmentGross' => $equipmentGross,
            'itemsDiscountTotal' => $itemsDiscountTotal,
            'equipmentCost' => $equipmentCost,
            'discount' => $discount,
            'laborFee' => $laborFee,
            'totalBill' => $totalBill,
            'advancePaid' => $advancePaid,
            'remainingPayment' => $remainingPayment,
            'creditAmount' => $creditAmount,
            'paymentMethod' => $paymentMethod,
            'creditNote' => $creditNote,
            'creditDueDate' => $creditDueDate,
            'status' => $status,
            'technician' => $technician,
            'installedDate' => $installedDate,
            'warrantyExpiry' => $warrantyExpiry,
            'notes' => $notes,
            'createdAt' => date('c')
        ];

        array_unshift($cctv, $newProject);
        save_json_file('cctv', $cctv);

        SecurityLogger::logEvent($user['username'], $user['role'], 'CCTV_PROJECT_RECORDED', "Recorded CCTV project #{$projectNo} of PKR {$totalBill} for {$clientName}");

        add_notification(
            'service',
            '📹 New CCTV Project Recorded',
            "Project #{$projectNo} recorded for {$clientName} ({$cameraBrand} {$cameraCount} Cams, PKR " . number_format($totalBill) . ")",
            $totalBill,
            $clientName
        );

        json_response('success', "CCTV project #{$projectNo} saved successfully", $newProject);
    }
}

// -------------------------------------------------------------
// 3. DELETE (Delete CCTV Record — Super Admin via DELETE method)
// -------------------------------------------------------------
if ($method === 'DELETE') {
    $user = require_role('super_admin');
    $id = $_GET['id'] ?? '';
    $reason = trim($_GET['reason'] ?? 'Deleted via API request');

    if (empty($id)) {
        json_response('error', 'CCTV project ID required for deletion');
    }

    $cctv = get_json_file('cctv') ?? [];
    $deletedArchive = get_json_file('cctv_deleted') ?? [];
    $found = null;

    $filtered = array_values(array_filter($cctv, function($p) use ($id, &$found) {
        if (($p['id'] ?? '') === $id || ($p['projectNo'] ?? '') === $id) {
            $found = $p;
            return false;
        }
        return true;
    }));

    if (!$found) {
        json_response('error', 'CCTV project record not found');
    }

    $deleteRecord = [
        'id' => 'cctv-del-' . time() . '-' . rand(100, 999),
        'projectId' => $found['id'] ?? '',
        'projectNo' => $found['projectNo'] ?? '',
        'clientName' => $found['clientName'] ?? '',
        'clientPhone' => $found['clientPhone'] ?? '',
        'siteAddress' => $found['siteAddress'] ?? '',
        'cameraBrand' => $found['cameraBrand'] ?? '',
        'totalBill' => floatval($found['totalBill'] ?? 0),
        'advancePaid' => floatval($found['advancePaid'] ?? 0),
        'status' => $found['status'] ?? '',
        'reason' => $reason,
        'deletedBy' => $user['username'] ?? $user['name'] ?? 'Super Admin',
        'deletedAt' => date('c'),
        'originalRecord' => $found
    ];

    array_unshift($deletedArchive, $deleteRecord);
    save_json_file('cctv_deleted', $deletedArchive);
    save_json_file('cctv', $filtered);

    SecurityLogger::logEvent($user['username'], 'super_admin', 'CCTV_PROJECT_DELETED', "Deleted CCTV Project #{$found['projectNo']} ({$found['clientName']}) - Reason: {$reason}");

    add_notification(
        'service',
        '🗑️ CCTV Project Deleted',
        "CCTV #{$found['projectNo']} for {$found['clientName']} was deleted by Super Admin {$user['username']} (Reason: {$reason})",
        floatval($found['totalBill'] ?? 0),
        $found['clientName']
    );

    json_response('success', "CCTV project {$found['projectNo']} deleted and archived successfully");
}

json_response('error', 'Invalid HTTP method');
