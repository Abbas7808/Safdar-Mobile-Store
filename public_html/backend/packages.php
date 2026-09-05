<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$user = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'all';

// Initialize files if not exist
if (!file_exists(DATA_DIR . 'sim_plans.json')) {
    save_json_file('sim_plans', []);
}
if (!file_exists(DATA_DIR . 'packages.json')) {
    save_json_file('packages', []);
}

// -------------------------------------------------------------
// 1. GET Requests (Fetch Plans or Activations)
// -------------------------------------------------------------
if ($method === 'GET') {
    if ($action === 'plans') {
        $plans = get_json_file('sim_plans') ?? [];
        
        // Optional network filter
        if (isset($_GET['network']) && $_GET['network'] !== 'all') {
            $net = strtolower(trim($_GET['network']));
            $plans = array_values(array_filter($plans, function($p) use ($net) {
                return strtolower($p['network'] ?? '') === $net;
            }));
        }
        json_response('success', 'SIM package plans retrieved', $plans);
    }

    if ($action === 'activations' || $action === 'all') {
        $packages = get_json_file('packages') ?? [];
        json_response('success', 'Package activations retrieved', $packages);
    }
}

// -------------------------------------------------------------
// 2. POST Requests (Add/Edit/Delete Plan, or Activate for Customer)
// -------------------------------------------------------------
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    // A. ADD NEW SIM PACKAGE PLAN (ADMIN CUSTOM PLAN)
    if ($action === 'add_plan') {
        $network = trim($input['network'] ?? 'jazz');
        $name = trim($input['name'] ?? '');
        $category = trim($input['category'] ?? 'all_in_one');
        $validity = trim($input['validity'] ?? '30 Days');
        $retailPrice = floatval($input['retailPrice'] ?? 0);
        $costPrice = floatval($input['costPrice'] ?? 0);
        $manualProfit = floatval($input['manualProfit'] ?? 0);
        $shopCharges = floatval($input['shopCharges'] ?? 0);
        $additionalCharges = floatval($input['additionalCharges'] ?? 0);

        if ($manualProfit <= 0 && $retailPrice > ($costPrice + $shopCharges + $additionalCharges)) {
            $manualProfit = max(0, $retailPrice - $costPrice - $shopCharges - $additionalCharges);
        }

        $profitMargin = floatval($input['profitMargin'] ?? ($manualProfit + $shopCharges + $additionalCharges));
        if ($profitMargin <= 0 && $retailPrice > $costPrice) {
            $profitMargin = $retailPrice - $costPrice;
        }

        if ($retailPrice <= 0 && $costPrice > 0) {
            $retailPrice = $costPrice + $profitMargin;
        }

        $dataGb = trim($input['dataGb'] ?? '');
        $onNetMins = trim($input['onNetMins'] ?? '');
        $offNetMins = trim($input['offNetMins'] ?? '');
        $smsCount = trim($input['smsCount'] ?? '');
        $ussdCode = trim($input['ussdCode'] ?? '');
        $description = trim($input['description'] ?? '');

        if (empty($name) || ($retailPrice <= 0 && $costPrice <= 0)) {
            json_response('error', 'Package name and retail price greater than 0 are required.');
        }

        $plans = get_json_file('sim_plans') ?? [];
        $newPlan = [
            'id' => 'plan-' . time() . '-' . rand(100, 999),
            'network' => $network,
            'name' => $name,
            'category' => $category,
            'validity' => $validity,
            'retailPrice' => $retailPrice,
            'costPrice' => $costPrice,
            'manualProfit' => $manualProfit,
            'shopCharges' => $shopCharges,
            'additionalCharges' => $additionalCharges,
            'profitMargin' => $profitMargin,
            'dataGb' => $dataGb,
            'onNetMins' => $onNetMins,
            'offNetMins' => $offNetMins,
            'smsCount' => $smsCount,
            'ussdCode' => $ussdCode,
            'description' => $description,
            'status' => 'active',
            'createdAt' => date('c'),
            'createdBy' => $user['username'] ?? 'admin'
        ];

        array_unshift($plans, $newPlan);
        save_json_file('sim_plans', $plans);

        if (class_exists('SecurityLogger')) {
            SecurityLogger::logEvent($user['username'] ?? 'admin', 'admin', 'SIM_PLAN_CREATED', "Added {$network} package: {$name} (PKR {$retailPrice})");
        }

        json_response('success', 'SIM Package plan added successfully', $newPlan);
    }

    // B. UPDATE EXISTING SIM PLAN
    if ($action === 'update_plan') {
        $id = $input['id'] ?? '';
        if (empty($id)) {
            json_response('error', 'Plan ID is required for update');
        }

        $plans = get_json_file('sim_plans') ?? [];
        $updated = false;

        foreach ($plans as &$p) {
            if ($p['id'] === $id) {
                $p['network'] = trim($input['network'] ?? $p['network']);
                $p['name'] = trim($input['name'] ?? $p['name']);
                $p['category'] = trim($input['category'] ?? $p['category']);
                $p['validity'] = trim($input['validity'] ?? $p['validity']);
                $p['retailPrice'] = floatval($input['retailPrice'] ?? $p['retailPrice']);
                $p['costPrice'] = floatval($input['costPrice'] ?? $p['costPrice']);
                $p['manualProfit'] = floatval($input['manualProfit'] ?? ($p['manualProfit'] ?? 0));
                $p['shopCharges'] = floatval($input['shopCharges'] ?? ($p['shopCharges'] ?? 0));
                $p['additionalCharges'] = floatval($input['additionalCharges'] ?? ($p['additionalCharges'] ?? 0));
                $p['profitMargin'] = floatval($input['profitMargin'] ?? ($p['manualProfit'] + $p['shopCharges'] + $p['additionalCharges']));
                $p['dataGb'] = trim($input['dataGb'] ?? $p['dataGb'] ?? '');
                $p['onNetMins'] = trim($input['onNetMins'] ?? $p['onNetMins'] ?? '');
                $p['offNetMins'] = trim($input['offNetMins'] ?? $p['offNetMins'] ?? '');
                $p['smsCount'] = trim($input['smsCount'] ?? $p['smsCount'] ?? '');
                $p['ussdCode'] = trim($input['ussdCode'] ?? $p['ussdCode'] ?? '');
                $p['description'] = trim($input['description'] ?? $p['description'] ?? '');
                if (isset($input['status'])) $p['status'] = $input['status'];
                $updated = true;
                break;
            }
        }

        if ($updated) {
            save_json_file('sim_plans', $plans);
            json_response('success', 'SIM Package plan updated successfully');
        } else {
            json_response('error', 'SIM Plan not found for update');
        }
    }

    // C. DELETE SIM PLAN
    if ($action === 'delete_plan') {
        $id = $input['id'] ?? $_GET['id'] ?? '';
        if (empty($id)) {
            json_response('error', 'Plan ID is required for deletion');
        }

        $plans = get_json_file('sim_plans') ?? [];
        $filtered = array_values(array_filter($plans, function($p) use ($id) {
            return ($p['id'] ?? '') !== $id;
        }));

        if (count($filtered) === count($plans)) {
            json_response('error', 'Plan not found');
        }

        save_json_file('sim_plans', $filtered);
        if (class_exists('SecurityLogger')) {
            SecurityLogger::logEvent($user['username'] ?? 'admin', 'admin', 'SIM_PLAN_DELETED', "Deleted SIM Package Plan ID: {$id}");
        }
        json_response('success', 'SIM Package plan deleted successfully');
    }

    // D. ACTIVATE PACKAGE FOR CUSTOMER
    if ($action === 'activate') {
        $mobileNumber = trim($input['mobileNumber'] ?? '');
        $customerName = trim($input['customerName'] ?? 'Walk-in Customer');
        $network = trim($input['network'] ?? 'jazz');
        $packageName = trim($input['packageName'] ?? 'Mobile Package');
        $retailPrice = floatval($input['retailPrice'] ?? 0);
        $costPrice = floatval($input['costPrice'] ?? 0);
        $manualProfit = floatval($input['manualProfit'] ?? 0);
        $shopCharges = floatval($input['shopCharges'] ?? 0);
        $additionalCharges = floatval($input['additionalCharges'] ?? 0);

        $totalShopProfit = $manualProfit + $shopCharges + $additionalCharges;
        if ($totalShopProfit <= 0 && isset($input['shopFee'])) {
            $totalShopProfit = floatval($input['shopFee']);
        }
        $totalCollected = $retailPrice + $shopCharges + $additionalCharges;

        $activationMethod = trim($input['activationMethod'] ?? 'retailer_load');
        $trxId = trim($input['trxId'] ?? '');
        $notes = trim($input['notes'] ?? '');

        if (empty($mobileNumber) || $retailPrice <= 0) {
            json_response('error', 'Mobile number and package price are required');
        }

        if (empty($trxId)) {
            $prefix = strtoupper(substr($network, 0, 2));
            $trxId = $prefix . '-PKG-' . rand(1000000000, 9999999999);
        }

        $packages = get_json_file('packages') ?? [];
        $newActivation = [
            'id' => 'act-' . time() . '-' . rand(100, 999),
            'mobileNumber' => $mobileNumber,
            'customerName' => $customerName,
            'network' => $network,
            'packageName' => $packageName,
            'retailPrice' => $retailPrice,
            'costPrice' => $costPrice,
            'manualProfit' => $manualProfit,
            'shopCharges' => $shopCharges,
            'additionalCharges' => $additionalCharges,
            'shopFee' => $totalShopProfit,
            'totalCollected' => $totalCollected,
            'activationMethod' => $activationMethod,
            'trxId' => $trxId,
            'status' => 'activated',
            'activatedAt' => date('c'),
            'loggedBy' => $user['username'] ?? 'admin',
            'notes' => $notes
        ];

        array_unshift($packages, $newActivation);
        save_json_file('packages', $packages);

        if (class_exists('SecurityLogger')) {
            SecurityLogger::logEvent($user['username'] ?? 'admin', 'admin', 'PACKAGE_ACTIVATED', "Activated {$packageName} for {$mobileNumber} (PKR {$retailPrice})");
        }

        json_response('success', 'Package activated successfully', $newActivation);
    }

    // E. DELETE ACTIVATION RECORD
    if ($action === 'delete_activation') {
        $id = $input['id'] ?? $_GET['id'] ?? '';
        if (empty($id)) {
            json_response('error', 'Activation ID is required');
        }

        $packages = get_json_file('packages') ?? [];
        $filtered = array_values(array_filter($packages, function($p) use ($id) {
            return ($p['id'] ?? '') !== $id;
        }));

        save_json_file('packages', $filtered);
        json_response('success', 'Activation record deleted successfully');
    }
}

json_response('error', 'Invalid action or request method');
