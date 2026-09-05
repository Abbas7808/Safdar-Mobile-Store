<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$sessionUser = get_session_user();

if (!$sessionUser) {
    json_response('error', 'Unauthorized. Please log in to admin portal.', null, 401);
}

if ($method !== 'POST') {
    json_response('error', 'Only POST method is accepted.', null, 405);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input || empty($input['packages']) || !is_array($input['packages'])) {
    json_response('error', 'Invalid import payload. An array of packages is required.');
}

$itemsToImport = $input['packages'];
$existingPlans = get_json_file('sim_plans') ?? [];
$importedCount = 0;
$createdPlans = [];

foreach ($itemsToImport as $rawItem) {
    $name = trim($rawItem['name'] ?? '');
    if (empty($name)) {
        continue;
    }

    $network = strtolower(trim($rawItem['network'] ?? 'jazz'));
    if (!in_array($network, ['jazz', 'zong', 'telenor', 'ufone', 'onic'])) {
        $network = 'jazz';
    }

    $category = strtolower(trim($rawItem['category'] ?? 'all_in_one'));
    if (!in_array($category, ['all_in_one', 'data', 'voice', 'social', 'sms', 'special'])) {
        $category = 'all_in_one';
    }

    $validity = trim($rawItem['validity'] ?? '30 Days');
    if (empty($validity)) $validity = '30 Days';

    $retailPrice = floatval($rawItem['retailPrice'] ?? $rawItem['price'] ?? 0);
    $costPrice = floatval($rawItem['costPrice'] ?? $rawItem['cost'] ?? 0);
    $manualProfit = floatval($rawItem['manualProfit'] ?? 0);
    $shopCharges = floatval($rawItem['shopCharges'] ?? 0);
    $additionalCharges = floatval($rawItem['additionalCharges'] ?? 0);

    if ($manualProfit <= 0 && $retailPrice > ($costPrice + $shopCharges + $additionalCharges)) {
        $manualProfit = max(0, $retailPrice - $costPrice - $shopCharges - $additionalCharges);
    }

    $profitMargin = floatval($rawItem['profitMargin'] ?? ($manualProfit + $shopCharges + $additionalCharges));
    if ($profitMargin <= 0 && $retailPrice > $costPrice) {
        $profitMargin = $retailPrice - $costPrice;
    }

    if ($retailPrice <= 0 && $costPrice > 0) {
        $retailPrice = $costPrice + $profitMargin;
    }

    $dataGb = trim($rawItem['dataGb'] ?? $rawItem['data'] ?? '');
    $onNetMins = trim($rawItem['onNetMins'] ?? $rawItem['onNet'] ?? '');
    $offNetMins = trim($rawItem['offNetMins'] ?? $rawItem['offNet'] ?? '');
    $smsCount = trim($rawItem['smsCount'] ?? $rawItem['sms'] ?? '');
    $ussdCode = trim($rawItem['ussdCode'] ?? $rawItem['code'] ?? '');
    $description = trim($rawItem['description'] ?? '');

    $newPlan = [
        'id' => 'plan-' . time() . '-' . rand(100, 999) . '-' . $importedCount,
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
        'importedVia' => $rawItem['importSource'] ?? 'smart_importer',
        'createdAt' => date('c'),
        'createdBy' => $sessionUser['username'] ?? 'admin'
    ];

    array_unshift($existingPlans, $newPlan);
    $createdPlans[] = $newPlan;
    $importedCount++;
}

if ($importedCount === 0) {
    json_response('error', 'No valid package records could be processed from the batch payload.');
}

save_json_file('sim_plans', $existingPlans);

if (class_exists('SecurityLogger')) {
    SecurityLogger::logEvent(
        $sessionUser['username'] ?? 'admin',
        'admin',
        'SIM_PACKAGES_BATCH_IMPORTED',
        "Successfully batch imported {$importedCount} SIM package plans into catalog."
    );
}

json_response('success', "Successfully imported {$importedCount} SIM Package Plans into catalog!", [
    'importedCount' => $importedCount,
    'totalPlans' => count($existingPlans),
    'createdPlans' => $createdPlans
]);
