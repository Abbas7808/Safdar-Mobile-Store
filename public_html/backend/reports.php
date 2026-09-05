<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

// Strict Super Admin Restricted Data
$user = require_role('super_admin');

$sales = get_json_file('sales') ?? [];
$expenses = get_json_file('expenses') ?? [];
$services = get_json_file('services') ?? [];

$totalRevenue = 0;
$cogs = 0;
$salesCount = count($sales);

$currentMonth = date('Y-m');
$monthlyRevenue = 0;
$monthlyCogs = 0;
$monthlyCount = 0;

foreach ($sales as $s) {
    if (($s['status'] ?? 'completed') === 'completed') {
        $tot = floatval($s['total'] ?? 0);
        $cg = floatval($s['cogs'] ?? 0);
        $totalRevenue += $tot;
        $cogs += $cg;
        
        $sMonth = substr($s['createdAt'] ?? '', 0, 7);
        if ($sMonth === $currentMonth) {
            $monthlyRevenue += $tot;
            $monthlyCogs += $cg;
            $monthlyCount++;
        }
    }
}

$grossProfit = $totalRevenue - $cogs;
$monthlyGrossProfit = $monthlyRevenue - $monthlyCogs;

$serviceCommissions = 0;
foreach ($services as $srv) {
    $serviceCommissions += floatval($srv['commission'] ?? 0);
}

$totalExpenses = 0;
$monthlyExpenses = 0;
foreach ($expenses as $e) {
    $amt = floatval($e['amount'] ?? 0);
    $totalExpenses += $amt;
    $eMonth = substr($e['date'] ?? $e['createdAt'] ?? '', 0, 7);
    if ($eMonth === $currentMonth) {
        $monthlyExpenses += $amt;
    }
}

$netProfit = $grossProfit + $serviceCommissions - $totalExpenses;
$monthlyNetProfit = $monthlyGrossProfit - $monthlyExpenses;

json_response('success', 'P&L Statement compiled', [
    'totalRevenue' => $totalRevenue,
    'cogs' => $cogs,
    'grossProfit' => $grossProfit,
    'serviceCommissions' => $serviceCommissions,
    'totalExpenses' => $totalExpenses,
    'netProfit' => $netProfit,
    'salesCount' => $salesCount,
    'monthlyReport' => [
        'month' => $currentMonth,
        'monthlyRevenue' => $monthlyRevenue,
        'monthlyCogs' => $monthlyCogs,
        'monthlyExpenses' => $monthlyExpenses,
        'monthlyNetProfit' => $monthlyNetProfit,
        'monthlyCount' => $monthlyCount
    ],
    'recentSales' => array_slice(array_reverse($sales), 0, 10)
]);
