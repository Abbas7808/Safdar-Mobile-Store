<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$user = require_auth();
$method = $_SERVER['REQUEST_METHOD'];

// -------------------------------------------------------------
// 1. GET Customers (All, Search by Query, or by ID with Full 360° History)
// -------------------------------------------------------------
if ($method === 'GET') {
    $customers = get_json_file('customers') ?? [];
    $sales = get_json_file('sales') ?? [];
    $repairs = get_json_file('mobile_repairs') ?? [];
    $cctv = get_json_file('cctv') ?? [];
    $nadra = get_json_file('nadra_kiosk') ?? [];

    // Search by query string (name, phone, email) with summary stats
    if (isset($_GET['q'])) {
        $q = strtolower(trim($_GET['q']));
        if (!empty($q)) {
            $filtered = [];
            foreach ($customers as $c) {
                $matches = strpos(strtolower($c['name'] ?? ''), $q) !== false ||
                           strpos(strtolower($c['phone'] ?? ''), $q) !== false ||
                           strpos(strtolower($c['email'] ?? ''), $q) !== false;
                
                if ($matches) {
                    $custName = strtolower($c['name'] ?? '');
                    $custPhone = strtolower(preg_replace('/[^0-9]/', '', $c['phone'] ?? ''));

                    // Invoices count
                    $invoicesCount = 0;
                    $invoicesSpent = 0;
                    foreach ($sales as $s) {
                        $sName = strtolower($s['customerName'] ?? '');
                        $sPhone = strtolower(preg_replace('/[^0-9]/', '', $s['customerPhone'] ?? ''));
                        if (($sName === $custName && $sName !== 'walk-in customer') || ($custPhone && $sPhone === $custPhone)) {
                            $invoicesCount++;
                            $invoicesSpent += floatval($s['total'] ?? 0);
                        }
                    }

                    // Repair tickets count
                    $repairsCount = 0;
                    foreach ($repairs as $r) {
                        $rName = strtolower($r['customerName'] ?? '');
                        $rPhone = strtolower(preg_replace('/[^0-9]/', '', $r['customerPhone'] ?? ''));
                        if (($rName === $custName) || ($custPhone && $rPhone === $custPhone)) {
                            $repairsCount++;
                        }
                    }

                    // CCTV projects count
                    $cctvCount = 0;
                    foreach ($cctv as $proj) {
                        $pName = strtolower($proj['clientName'] ?? '');
                        $pPhone = strtolower(preg_replace('/[^0-9]/', '', $proj['clientPhone'] ?? ''));
                        if (($pName === $custName) || ($custPhone && $pPhone === $custPhone)) {
                            $cctvCount++;
                        }
                    }

                    $c['calculatedPurchases'] = $invoicesCount;
                    $c['calculatedSpent'] = $invoicesSpent;
                    $c['repairsCount'] = $repairsCount;
                    $c['cctvCount'] = $cctvCount;
                    $filtered[] = $c;
                }
            }
            json_response('success', 'Customers retrieved', $filtered);
        }
    }

    // Get single customer details with purchase history, repairs, and CCTV
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $found = null;
        foreach ($customers as $c) {
            if (($c['id'] ?? '') === $id || strtolower($c['name'] ?? '') === strtolower($id)) {
                $found = $c;
                break;
            }
        }

        if ($found) {
            $custName = strtolower($found['name'] ?? '');
            $custPhone = strtolower(preg_replace('/[^0-9]/', '', $found['phone'] ?? ''));

            // Sales Invoices
            $custSales = [];
            foreach ($sales as $s) {
                $sName = strtolower($s['customerName'] ?? '');
                $sPhone = strtolower(preg_replace('/[^0-9]/', '', $s['customerPhone'] ?? ''));
                if (($sName === $custName && $sName !== 'walk-in customer') || ($custPhone && $sPhone === $custPhone)) {
                    $custSales[] = $s;
                }
            }
            $found['invoices'] = array_reverse($custSales);

            // Repair Tickets
            $custRepairs = [];
            foreach ($repairs as $r) {
                $rName = strtolower($r['customerName'] ?? '');
                $rPhone = strtolower(preg_replace('/[^0-9]/', '', $r['customerPhone'] ?? ''));
                if (($rName === $custName) || ($custPhone && $rPhone === $custPhone)) {
                    $custRepairs[] = $r;
                }
            }
            $found['repairs'] = array_reverse($custRepairs);

            // CCTV Projects
            $custCctv = [];
            foreach ($cctv as $proj) {
                $pName = strtolower($proj['clientName'] ?? '');
                $pPhone = strtolower(preg_replace('/[^0-9]/', '', $proj['clientPhone'] ?? ''));
                if (($pName === $custName) || ($custPhone && $pPhone === $custPhone)) {
                    $custCctv[] = $proj;
                }
            }
            $found['cctv'] = array_reverse($custCctv);

            // NADRA Records
            $custNadra = [];
            foreach ($nadra as $nr) {
                $nName = strtolower($nr['citizenName'] ?? '');
                $nPhone = strtolower(preg_replace('/[^0-9]/', '', $nr['citizenPhone'] ?? ''));
                if (($nName === $custName) || ($custPhone && $nPhone === $custPhone)) {
                    $custNadra[] = $nr;
                }
            }
            $found['nadra'] = array_reverse($custNadra);

            json_response('success', 'Customer 360 profile retrieved', $found);
        } else {
            json_response('error', 'Customer not found');
        }
    }

    json_response('success', 'Customers retrieved', $customers);
}

// -------------------------------------------------------------
// 2. POST (Create or Update Customer)
// -------------------------------------------------------------
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    if (empty($input['name'])) {
        json_response('error', 'Customer name is required');
    }

    $customers = get_json_file('customers') ?? [];
    $id = $input['id'] ?? '';

    if (!empty($id)) {
        // Update case
        $updated = false;
        foreach ($customers as &$c) {
            if ($c['id'] === $id) {
                $c['name'] = trim($input['name']);
                $c['phone'] = trim($input['phone'] ?? $c['phone'] ?? '');
                $c['email'] = trim($input['email'] ?? $c['email'] ?? '');
                $c['balance'] = floatval($input['balance'] ?? $c['balance'] ?? 0);
                if (isset($input['status'])) $c['status'] = $input['status'];
                $updated = true;
                break;
            }
        }
        if ($updated) {
            save_json_file('customers', $customers);
            SecurityLogger::logEvent($user['username'], $user['role'], 'CUSTOMER_UPDATED', "Updated customer ID {$id}");
            json_response('success', 'Customer record updated successfully');
        } else {
            json_response('error', 'Customer not found for update');
        }
    } else {
        // Create case
        $newCustomer = [
            'id' => 'cust-' . time() . '-' . rand(100, 999),
            'name' => trim($input['name']),
            'phone' => trim($input['phone'] ?? ''),
            'email' => trim($input['email'] ?? ''),
            'totalPurchases' => 0,
            'totalSpent' => 0,
            'balance' => floatval($input['balance'] ?? 0),
            'status' => 'active',
            'createdAt' => date('c')
        ];

        array_unshift($customers, $newCustomer);
        save_json_file('customers', $customers);

        SecurityLogger::logEvent($user['username'], $user['role'], 'CUSTOMER_CREATED', "Added new customer: {$newCustomer['name']}");
        json_response('success', 'Customer created successfully', $newCustomer);
    }
}

// -------------------------------------------------------------
// 3. DELETE (Delete Customer — Super Admin Only)
// -------------------------------------------------------------
if ($method === 'DELETE') {
    $user = require_role('super_admin');
    $id = $_GET['id'] ?? '';

    if (empty($id)) {
        json_response('error', 'Customer ID is required');
    }

    $customers = get_json_file('customers') ?? [];
    $filtered = array_values(array_filter($customers, function($c) use ($id) {
        return ($c['id'] ?? '') !== $id;
    }));

    if (count($filtered) === count($customers)) {
        json_response('error', 'Customer record not found');
    }

    save_json_file('customers', $filtered);
    SecurityLogger::logEvent($user['username'], 'super_admin', 'CUSTOMER_DELETED', "Deleted customer ID {$id}");

    json_response('success', 'Customer record deleted successfully');
}

json_response('error', 'Invalid HTTP method');
