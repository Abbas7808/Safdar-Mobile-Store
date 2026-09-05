<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$user = require_auth();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $repairs = get_json_file('mobile_repairs') ?? [];
    
    // Optional status filter
    if (isset($_GET['status']) && $_GET['status'] !== 'all') {
        $status = strtolower(trim($_GET['status']));
        $repairs = array_values(array_filter($repairs, function($r) use ($status) {
            return strtolower($r['jobStatus']) === $status;
        }));
    }
    
    // Optional search filter
    if (isset($_GET['search']) && trim($_GET['search']) !== '') {
        $q = strtolower(trim($_GET['search']));
        $repairs = array_values(array_filter($repairs, function($r) use ($q) {
            return strpos(strtolower($r['id'] ?? ''), $q) !== false ||
                   strpos(strtolower($r['customerName'] ?? ''), $q) !== false ||
                   strpos(strtolower($r['customerPhone'] ?? ''), $q) !== false ||
                   strpos(strtolower($r['deviceBrand'] ?? ''), $q) !== false ||
                   strpos(strtolower($r['deviceModel'] ?? ''), $q) !== false ||
                   strpos(strtolower($r['deviceImei'] ?? ''), $q) !== false ||
                   strpos(strtolower($r['reportedFault'] ?? ''), $q) !== false;
        }));
    }

    json_response('success', 'Mobile repair jobs retrieved', $repairs);
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!$input) {
        $input = $_POST;
    }

    $action = $input['action'] ?? 'save';
    $repairs = get_json_file('mobile_repairs') ?? [];

    if ($action === 'delete') {
        if ($user['role'] !== 'super_admin' && $user['role'] !== 'admin') {
            json_response('error', 'Unauthorized. Only admin can delete repair tickets.');
        }

        $id = $input['id'] ?? '';
        $initialCount = count($repairs);
        $repairs = array_values(array_filter($repairs, function($r) use ($id) {
            return $r['id'] !== $id;
        }));

        if (count($repairs) < $initialCount) {
            save_json_file('mobile_repairs', $repairs);
            SecurityLogger::logEvent($user['username'], $user['role'], 'REPAIR_JOB_DELETED', "Deleted repair ticket {$id}");
            json_response('success', "Repair ticket {$id} deleted successfully");
        } else {
            json_response('error', "Repair ticket not found");
        }
    }

    if ($action === 'update_status') {
        $id = $input['id'] ?? '';
        $newStatus = $input['status'] ?? '';
        $found = false;

        foreach ($repairs as &$r) {
            if ($r['id'] === $id) {
                $r['jobStatus'] = $newStatus;
                $r['updatedAt'] = date('Y-m-d H:i:s');
                if (isset($input['issueResolved']) && trim($input['issueResolved']) !== '') {
                    $r['issueResolved'] = trim($input['issueResolved']);
                }
                if (isset($input['partsUsed']) && trim($input['partsUsed']) !== '') {
                    $r['partsUsed'] = trim($input['partsUsed']);
                }
                $found = true;
                break;
            }
        }

        if ($found) {
            save_json_file('mobile_repairs', $repairs);
            SecurityLogger::logEvent($user['username'], $user['role'], 'REPAIR_STATUS_UPDATED', "Updated repair ticket {$id} status to {$newStatus}");
            json_response('success', "Repair status updated to {$newStatus}");
        } else {
            json_response('error', "Repair ticket not found");
        }
    }

    if ($action === 'save') {
        $id = trim($input['id'] ?? '');
        $isNew = empty($id);

        if ($isNew) {
            // Generate ticket ID e.g. REP-2026-004
            $year = date('Y');
            $count = count($repairs) + 1;
            $id = sprintf("REP-%s-%03d", $year, $count);
            // Ensure unique
            $existingIds = array_column($repairs, 'id');
            while (in_array($id, $existingIds)) {
                $count++;
                $id = sprintf("REP-%s-%03d", $year, $count);
            }
        }

        $partsCost = floatval($input['partsCost'] ?? 0);
        $laborCharges = floatval($input['laborCharges'] ?? 0);
        $totalBill = floatval($input['totalBill'] ?? ($partsCost + $laborCharges));
        if ($totalBill <= 0) {
            $totalBill = $partsCost + $laborCharges;
        }

        $advancePaid = floatval($input['advancePaid'] ?? 0);
        $balanceDue = max(0, $totalBill - $advancePaid);

        $paymentStatus = 'Unpaid';
        if ($advancePaid >= $totalBill && $totalBill > 0) {
            $paymentStatus = 'Paid in Full';
        } elseif ($advancePaid > 0) {
            $paymentStatus = 'Partial Advance';
        }

        $ticketData = [
            'id' => $id,
            'receivedDate' => !empty($input['receivedDate']) ? $input['receivedDate'] : date('Y-m-d H:i'),
            'deliveryDate' => !empty($input['deliveryDate']) ? $input['deliveryDate'] : date('Y-m-d H:i', strtotime('+1 day')),
            'customerName' => trim($input['customerName'] ?? 'Walk-in Customer'),
            'customerPhone' => trim($input['customerPhone'] ?? ''),
            'customerCity' => trim($input['customerCity'] ?? 'Hangu'),
            'deviceBrand' => trim($input['deviceBrand'] ?? 'Other'),
            'deviceModel' => trim($input['deviceModel'] ?? ''),
            'deviceColor' => trim($input['deviceColor'] ?? ''),
            'deviceImei' => trim($input['deviceImei'] ?? ''),
            'devicePasscode' => trim($input['devicePasscode'] ?? ''),
            'reportedFault' => trim($input['reportedFault'] ?? ''),
            'physicalCondition' => trim($input['physicalCondition'] ?? ''),
            'issueResolved' => trim($input['issueResolved'] ?? ''),
            'partsUsed' => trim($input['partsUsed'] ?? ''),
            'partsCost' => $partsCost,
            'laborCharges' => $laborCharges,
            'totalBill' => $totalBill,
            'advancePaid' => $advancePaid,
            'balanceDue' => $balanceDue,
            'paymentStatus' => $input['paymentStatus'] ?? $paymentStatus,
            'paymentMethod' => $input['paymentMethod'] ?? 'Cash',
            'jobStatus' => $input['jobStatus'] ?? 'In Progress',
            'technician' => trim($input['technician'] ?? 'Master Munim'),
            'warranty' => trim($input['warranty'] ?? 'No Warranty'),
            'notes' => trim($input['notes'] ?? ''),
            'updatedAt' => date('Y-m-d H:i:s')
        ];

        if ($isNew) {
            $ticketData['createdAt'] = date('Y-m-d H:i:s');
            array_unshift($repairs, $ticketData);
            SecurityLogger::logEvent($user['username'], $user['role'], 'REPAIR_JOB_CREATED', "Created new repair ticket {$id} for {$ticketData['customerName']} ({$ticketData['deviceModel']})");
        } else {
            $updated = false;
            foreach ($repairs as &$r) {
                if ($r['id'] === $id) {
                    $ticketData['createdAt'] = $r['createdAt'] ?? date('Y-m-d H:i:s');
                    $r = $ticketData;
                    $updated = true;
                    break;
                }
            }
            if (!$updated) {
                $ticketData['createdAt'] = date('Y-m-d H:i:s');
                array_unshift($repairs, $ticketData);
            }
            SecurityLogger::logEvent($user['username'], $user['role'], 'REPAIR_JOB_UPDATED', "Updated repair ticket {$id}");
        }

        save_json_file('mobile_repairs', $repairs);
        json_response('success', $isNew ? "Repair ticket {$id} created successfully" : "Repair ticket {$id} updated successfully", $ticketData);
    }

    json_response('error', 'Invalid action');
}
