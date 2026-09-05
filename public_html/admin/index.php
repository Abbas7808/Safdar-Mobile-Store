<?php
$currentPage = 'dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$currentUser = get_session_user();
$isSuperAdmin = ($currentUser['role'] ?? '') === 'super_admin';

$products = get_json_file('products') ?? [];
$sales = get_json_file('sales') ?? [];
$expenses = get_json_file('expenses') ?? [];
$services = get_json_file('services') ?? [];
$repairs = get_json_file('mobile_repairs') ?? [];
$cctv = get_json_file('cctv') ?? [];
$nadraRecords = get_json_file('nadra_kiosk') ?? [];
$bills = get_json_file('bills') ?? [];
$packages = get_json_file('packages') ?? [];
$simPlans = get_json_file('sim_plans') ?? [];

// Today, Week, Month Date Reference
$todayDate = date('Y-m-d');
$currentMonth = date('Y-m');
$currentYear = date('Y');
$sevenDaysAgo = date('Y-m-d', strtotime('-6 days'));

// -------------------------------------------------------------
// 1. COMPREHENSIVE SECTION-WISE GROSS PROFIT CALCULATION ENGINE
// -------------------------------------------------------------

$sectionProfits = [
    'sales' => [
        'name' => 'POS Retail & Accessories',
        'subtitle' => 'Smartphones, chargers, airbuds & store stock',
        'icon' => 'fa-solid fa-cart-shopping',
        'color' => '#dc2626',
        'bg' => '#fef2f2',
        'link' => 'sales.php',
        'today_rev' => 0, 'today_cost' => 0, 'today_profit' => 0, 'today_count' => 0,
        'month_rev' => 0, 'month_cost' => 0, 'month_profit' => 0, 'month_count' => 0,
        'total_rev' => 0, 'total_cost' => 0, 'total_profit' => 0, 'total_count' => 0,
    ],
    'cctv' => [
        'name' => 'CCTV Security & Surveillance',
        'subtitle' => 'Hikvision/Dahua cameras & setup projects',
        'icon' => 'fa-solid fa-video',
        'color' => '#dc2626',
        'bg' => '#fff1f2',
        'link' => 'cctv.php',
        'today_rev' => 0, 'today_cost' => 0, 'today_profit' => 0, 'today_count' => 0,
        'month_rev' => 0, 'month_cost' => 0, 'month_profit' => 0, 'month_count' => 0,
        'total_rev' => 0, 'total_cost' => 0, 'total_profit' => 0, 'total_count' => 0,
    ],
    'repairs' => [
        'name' => 'Mobile Hardware Repair Lab',
        'subtitle' => 'LCD screens, IC repair & labor services',
        'icon' => 'fa-solid fa-screwdriver-wrench',
        'color' => '#d97706',
        'bg' => '#fffbeb',
        'link' => 'mobile-repairs.php',
        'today_rev' => 0, 'today_cost' => 0, 'today_profit' => 0, 'today_count' => 0,
        'month_rev' => 0, 'month_cost' => 0, 'month_profit' => 0, 'month_count' => 0,
        'total_rev' => 0, 'total_cost' => 0, 'total_profit' => 0, 'total_count' => 0,
    ],
    'bills' => [
        'name' => 'Utility Bills Payment Counter',
        'subtitle' => 'PESCO, SNGPL, PTCL & Water service fees',
        'icon' => 'fa-solid fa-file-invoice-dollar',
        'color' => '#f59e0b',
        'bg' => '#fffdf5',
        'link' => 'bills.php',
        'today_rev' => 0, 'today_cost' => 0, 'today_profit' => 0, 'today_count' => 0,
        'month_rev' => 0, 'month_cost' => 0, 'month_profit' => 0, 'month_count' => 0,
        'total_rev' => 0, 'total_cost' => 0, 'total_profit' => 0, 'total_count' => 0,
    ],
    'nadra' => [
        'name' => 'NADRA E-Sahulat Kiosk Desk',
        'subtitle' => 'CNIC, FRC, Police certificates & Domicile',
        'icon' => 'fa-solid fa-id-card',
        'color' => '#2563eb',
        'bg' => '#eff6ff',
        'link' => 'nadra-kiosk.php',
        'today_rev' => 0, 'today_cost' => 0, 'today_profit' => 0, 'today_count' => 0,
        'month_rev' => 0, 'month_cost' => 0, 'month_profit' => 0, 'month_count' => 0,
        'total_rev' => 0, 'total_cost' => 0, 'total_profit' => 0, 'total_count' => 0,
    ],
    'services' => [
        'name' => 'Easypaisa & JazzCash Services',
        'subtitle' => 'Money transfer, cash-in & cash-out commission',
        'icon' => 'fa-solid fa-money-bill-transfer',
        'color' => '#059669',
        'bg' => '#ecfdf5',
        'link' => 'services.php',
        'today_rev' => 0, 'today_cost' => 0, 'today_profit' => 0, 'today_count' => 0,
        'month_rev' => 0, 'month_cost' => 0, 'month_profit' => 0, 'month_count' => 0,
        'total_rev' => 0, 'total_cost' => 0, 'total_profit' => 0, 'total_count' => 0,
    ],
    'packages' => [
        'name' => 'SIM Cards & Internet Packages',
        'subtitle' => 'Jazz, Zong, Telenor & Ufone bundle margins',
        'icon' => 'fa-solid fa-sim-card',
        'color' => '#e11d48',
        'bg' => '#fff1f2',
        'link' => 'packages.php',
        'today_rev' => 0, 'today_cost' => 0, 'today_profit' => 0, 'today_count' => 0,
        'month_rev' => 0, 'month_cost' => 0, 'month_profit' => 0, 'month_count' => 0,
        'total_rev' => 0, 'total_cost' => 0, 'total_profit' => 0, 'total_count' => 0,
    ],
];

// 1. Process POS Sales
$todayRevenue = 0; $todayCount = 0;
$weeklyRevenue = 0; $weeklyCount = 0;
$monthlyRevenue = 0; $monthlyCount = 0;
$monthlyCogs = 0; $monthlyProfit = 0;

$weekDaysMap = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $dayName = date('D', strtotime($d));
    $weekDaysMap[$d] = ['label' => $dayName . ' (' . date('M d', strtotime($d)) . ')', 'revenue' => 0, 'count' => 0];
}

$yearMonthsMap = [];
for ($m = 1; $m <= 12; $m++) {
    $mKey = $currentYear . '-' . sprintf('%02d', $m);
    $mLabel = date('M', mktime(0, 0, 0, $m, 1, (int)$currentYear));
    $yearMonthsMap[$mKey] = ['label' => $mLabel, 'revenue' => 0, 'cogs' => 0, 'profit' => 0, 'count' => 0];
}

$monthlyPaymentMethods = ['cash' => 0, 'easypaisa' => 0, 'jazzcash' => 0, 'card' => 0, 'other' => 0];

foreach ($sales as $sale) {
    if (($sale['status'] ?? 'completed') === 'refunded') continue;

    $dateStr = substr($sale['createdAt'] ?? '', 0, 10);
    $monthKey = substr($sale['createdAt'] ?? '', 0, 7);
    $saleTotal = floatval($sale['total'] ?? 0);
    $paymentMethod = strtolower($sale['paymentMethod'] ?? 'cash');

    $saleCogs = 0;
    if (isset($sale['items']) && is_array($sale['items'])) {
        foreach ($sale['items'] as $item) {
            $cost = floatval($item['costPrice'] ?? 0);
            $qty = intval($item['qty'] ?? 1);
            $saleCogs += ($cost * $qty);
        }
    }
    $saleProfit = max(0, $saleTotal - $saleCogs);

    // Section Matrix: All-Time
    $sectionProfits['sales']['total_rev'] += $saleTotal;
    $sectionProfits['sales']['total_cost'] += $saleCogs;
    $sectionProfits['sales']['total_profit'] += $saleProfit;
    $sectionProfits['sales']['total_count']++;

    // Section Matrix: Today Check
    if ($dateStr === $todayDate) {
        $todayRevenue += $saleTotal;
        $todayCount++;
        $sectionProfits['sales']['today_rev'] += $saleTotal;
        $sectionProfits['sales']['today_cost'] += $saleCogs;
        $sectionProfits['sales']['today_profit'] += $saleProfit;
        $sectionProfits['sales']['today_count']++;
    }

    // 7-Day Week Check
    if (isset($weekDaysMap[$dateStr])) {
        $weekDaysMap[$dateStr]['revenue'] += $saleTotal;
        $weekDaysMap[$dateStr]['count'] += 1;
        $weeklyRevenue += $saleTotal;
        $weeklyCount++;
    }

    // Current Month Check
    if ($monthKey === $currentMonth) {
        $monthlyRevenue += $saleTotal;
        $monthlyCount++;
        $monthlyCogs += $saleCogs;
        $monthlyProfit += $saleProfit;

        $sectionProfits['sales']['month_rev'] += $saleTotal;
        $sectionProfits['sales']['month_cost'] += $saleCogs;
        $sectionProfits['sales']['month_profit'] += $saleProfit;
        $sectionProfits['sales']['month_count']++;

        if (isset($monthlyPaymentMethods[$paymentMethod])) {
            $monthlyPaymentMethods[$paymentMethod] += $saleTotal;
        } else {
            $monthlyPaymentMethods['other'] += $saleTotal;
        }
    }

    // 12-Month Year Map
    if (isset($yearMonthsMap[$monthKey])) {
        $yearMonthsMap[$monthKey]['revenue'] += $saleTotal;
        $yearMonthsMap[$monthKey]['cogs'] += $saleCogs;
        $yearMonthsMap[$monthKey]['profit'] += $saleProfit;
        $yearMonthsMap[$monthKey]['count'] += 1;
    }
}

// 2. Process CCTV Installations
$dashCctvTotalCount = count($cctv);
$dashCctvTotalRevenue = 0;
$dashCctvTotalLaborProfit = 0;
$dashCctvActiveSites = 0;
$dashCctvInProgress = 0;
$dashCctvTotalDue = 0;

foreach ($cctv as $proj) {
    $cSt = strtolower($proj['status'] ?? 'installed');
    if ($cSt === 'returned' || $cSt === 'cancelled') continue;

    $cDate = substr($proj['createdAt'] ?? $proj['installedDate'] ?? $proj['installationDate'] ?? $proj['date'] ?? '', 0, 10);
    $cMonth = substr($proj['createdAt'] ?? $proj['installedDate'] ?? $proj['installationDate'] ?? $proj['date'] ?? '', 0, 7);
    $cTot = floatval($proj['totalBill'] ?? 0);
    $cAdv = floatval($proj['advancePaid'] ?? 0);
    $cLabor = floatval($proj['laborFee'] ?? 0);
    $cCost = floatval($proj['hardwareCost'] ?? $proj['partsCost'] ?? $proj['equipmentCost'] ?? 0);
    if ($cLabor <= 0 && $cCost > 0) $cLabor = max(0, $cTot - $cCost);
    elseif ($cCost <= 0 && $cLabor > 0) $cCost = max(0, $cTot - $cLabor);

    $dashCctvTotalRevenue += $cTot;
    $dashCctvTotalLaborProfit += $cLabor;
    $dashCctvTotalDue += max(0, $cTot - $cAdv);

    if ($cSt === 'installed' || $cSt === 'active') $dashCctvActiveSites++;
    elseif ($cSt === 'in_progress' || $cSt === 'pending') $dashCctvInProgress++;

    $sectionProfits['cctv']['total_rev'] += $cTot;
    $sectionProfits['cctv']['total_cost'] += $cCost;
    $sectionProfits['cctv']['total_profit'] += $cLabor;
    $sectionProfits['cctv']['total_count']++;

    if ($cMonth === $currentMonth) {
        $sectionProfits['cctv']['month_rev'] += $cTot;
        $sectionProfits['cctv']['month_cost'] += $cCost;
        $sectionProfits['cctv']['month_profit'] += $cLabor;
        $sectionProfits['cctv']['month_count']++;
    }
    if ($cDate === $todayDate) {
        $sectionProfits['cctv']['today_rev'] += $cTot;
        $sectionProfits['cctv']['today_cost'] += $cCost;
        $sectionProfits['cctv']['today_profit'] += $cLabor;
        $sectionProfits['cctv']['today_count']++;
    }
}
$recentCctv = array_slice(array_reverse($cctv), 0, 6);

// 3. Process Mobile Repairs Lab
$dashRepairTotalCount = count($repairs);
$dashRepairTodayCount = 0;
$dashRepairTodayRevenue = 0;
$dashRepairTodayLabor = 0;
$dashRepairMonthRevenue = 0;
$dashRepairTotalRevenue = 0;
$dashRepairTotalLaborProfit = 0;
$dashRepairActiveJobs = 0;
$dashRepairReadyJobs = 0;
$dashRepairDeliveredJobs = 0;
$dashRepairTotalDue = 0;

foreach ($repairs as $r) {
    $rStatus = strtolower(trim($r['jobStatus'] ?? 'received'));
    if ($rStatus === 'cancelled') continue;

    $rDate = substr($r['createdAt'] ?? $r['dateReceived'] ?? $r['receivedDate'] ?? $r['date'] ?? '', 0, 10);
    $rMonth = substr($r['createdAt'] ?? $r['dateReceived'] ?? $r['receivedDate'] ?? $r['date'] ?? '', 0, 7);
    $rTot = floatval($r['totalBill'] ?? 0);
    $rAdv = floatval($r['advancePaid'] ?? 0);
    $rLabor = floatval($r['laborFee'] ?? $r['laborCharges'] ?? 0);
    $rParts = floatval($r['partsCost'] ?? 0);
    if ($rLabor <= 0 && $rParts > 0) $rLabor = max(0, $rTot - $rParts);
    elseif ($rParts <= 0 && $rLabor > 0) $rParts = max(0, $rTot - $rLabor);

    $dashRepairTotalRevenue += $rTot;
    $dashRepairTotalLaborProfit += $rLabor;
    $dashRepairTotalDue += max(0, $rTot - $rAdv);

    if ($rDate === $todayDate) {
        $dashRepairTodayCount++;
        $dashRepairTodayRevenue += $rTot;
        $dashRepairTodayLabor += $rLabor;
    }
    if (strpos($rDate, $currentMonth) === 0) {
        $dashRepairMonthRevenue += $rTot;
    }

    if ($rStatus === 'received' || $rStatus === 'in progress' || $rStatus === 'in_progress') $dashRepairActiveJobs++;
    elseif ($rStatus === 'ready for pickup' || $rStatus === 'ready_for_pickup' || $rStatus === 'completed') $dashRepairReadyJobs++;
    elseif ($rStatus === 'delivered') $dashRepairDeliveredJobs++;

    $sectionProfits['repairs']['total_rev'] += $rTot;
    $sectionProfits['repairs']['total_cost'] += $rParts;
    $sectionProfits['repairs']['total_profit'] += $rLabor;
    $sectionProfits['repairs']['total_count']++;

    if ($rMonth === $currentMonth) {
        $sectionProfits['repairs']['month_rev'] += $rTot;
        $sectionProfits['repairs']['month_cost'] += $rParts;
        $sectionProfits['repairs']['month_profit'] += $rLabor;
        $sectionProfits['repairs']['month_count']++;
    }
    if ($rDate === $todayDate) {
        $sectionProfits['repairs']['today_rev'] += $rTot;
        $sectionProfits['repairs']['today_cost'] += $rParts;
        $sectionProfits['repairs']['today_profit'] += $rLabor;
        $sectionProfits['repairs']['today_count']++;
    }
}
$recentRepairs = array_slice(array_reverse($repairs), 0, 5);

// 4. Process Utility Bills Payment Counter
$dashBillsCount = count($bills);
$dashBillsVolume = 0;
$dashBillsEarnings = 0;

foreach ($bills as $b) {
    $st = strtolower($b['paymentStatus'] ?? $b['status'] ?? 'paid');
    if ($st === 'returned' || $st === 'rejected' || $st === 'cancelled') continue;

    $bDate = substr($b['paidAt'] ?? $b['createdAt'] ?? $b['date'] ?? $b['timestamp'] ?? '', 0, 10);
    $bMonth = substr($b['paidAt'] ?? $b['createdAt'] ?? $b['date'] ?? $b['timestamp'] ?? '', 0, 7);
    $amt = floatval($b['billAmount'] ?? 0);
    $fee = floatval($b['shopFee'] ?? ($b['shopCharges'] ?? 0) + ($b['manualProfit'] ?? 0) + ($b['additionalCharges'] ?? 0));
    $tot = floatval($b['totalCollected'] ?? ($amt + $fee));

    $dashBillsVolume += $amt;
    $dashBillsEarnings += $fee;

    $sectionProfits['bills']['total_rev'] += $tot;
    $sectionProfits['bills']['total_cost'] += $amt;
    $sectionProfits['bills']['total_profit'] += $fee;
    $sectionProfits['bills']['total_count']++;

    if ($bMonth === $currentMonth) {
        $sectionProfits['bills']['month_rev'] += $tot;
        $sectionProfits['bills']['month_cost'] += $amt;
        $sectionProfits['bills']['month_profit'] += $fee;
        $sectionProfits['bills']['month_count']++;
    }
    if ($bDate === $todayDate) {
        $sectionProfits['bills']['today_rev'] += $tot;
        $sectionProfits['bills']['today_cost'] += $amt;
        $sectionProfits['bills']['today_profit'] += $fee;
        $sectionProfits['bills']['today_count']++;
    }
}

// 5. Process NADRA Kiosk
$dashNadraCount = count($nadraRecords);
$dashNadraEarnings = 0;
$dashNadraPending = 0;
$dashNadraReady = 0;

foreach ($nadraRecords as $nr) {
    if (($nr['status'] ?? '') === 'cancelled') continue;
    $nDate = substr($nr['createdAt'] ?? $nr['date'] ?? $nr['timestamp'] ?? '', 0, 10);
    $nMonth = substr($nr['createdAt'] ?? $nr['date'] ?? $nr['timestamp'] ?? '', 0, 7);
    $fee = floatval($nr['shopFee'] ?? ($nr['totalFee'] ?? 0) - ($nr['govtFee'] ?? 0));

    $dashNadraEarnings += $fee;
    $nst = $nr['status'] ?? 'in_process';
    if ($nst === 'in_process' || $nst === 'document_submitted') $dashNadraPending++;
    elseif ($nst === 'ready' || $nst === 'approved') $dashNadraReady++;

    $sectionProfits['nadra']['total_rev'] += $fee;
    $sectionProfits['nadra']['total_cost'] += 0;
    $sectionProfits['nadra']['total_profit'] += $fee;
    $sectionProfits['nadra']['total_count']++;

    if ($nMonth === $currentMonth) {
        $sectionProfits['nadra']['month_rev'] += $fee;
        $sectionProfits['nadra']['month_cost'] += 0;
        $sectionProfits['nadra']['month_profit'] += $fee;
        $sectionProfits['nadra']['month_count']++;
    }
    if ($nDate === $todayDate) {
        $sectionProfits['nadra']['today_rev'] += $fee;
        $sectionProfits['nadra']['today_cost'] += 0;
        $sectionProfits['nadra']['today_profit'] += $fee;
        $sectionProfits['nadra']['today_count']++;
    }
}

// 6. Process Easypaisa & JazzCash Services
$dashCashIn = 0;
$dashCashOut = 0;
$dashCommission = 0;
$dashBillPayments = 0;
$dashEasyloads = 0;

$dashCountCashIn = 0;
$dashCountCashOut = 0;
$dashCountBills = 0;
$dashCountLoads = 0;

$dashEasypaisaVolume = 0;
$dashEasypaisaCommission = 0;
$dashJazzcashVolume = 0;
$dashJazzcashCommission = 0;
$dashOtherVolume = 0;
$dashOtherCommission = 0;

foreach ($services as $tx) {
    if (($tx['status'] ?? 'completed') === 'cancelled' || ($tx['status'] ?? '') === 'reversed') continue;
    $sDate = substr($tx['timestamp'] ?? $tx['createdAt'] ?? $tx['date'] ?? '', 0, 10);
    $sMonth = substr($tx['timestamp'] ?? $tx['createdAt'] ?? $tx['date'] ?? '', 0, 7);
    $amt = floatval($tx['amount'] ?? 0);
    $comm = floatval($tx['commission'] ?? 0);
    $type = $tx['txType'] ?? 'cash_in';
    $provider = strtolower($tx['serviceProvider'] ?? 'easypaisa');

    $dashCommission += $comm;

    if ($type === 'cash_in' || $type === 'send_money') {
        $dashCashIn += $amt;
        $dashCountCashIn++;
    } elseif ($type === 'cash_out' || $type === 'withdrawal') {
        $dashCashOut += $amt;
        $dashCountCashOut++;
    } elseif ($type === 'bill_payment' || $type === 'bill') {
        $dashBillPayments += $amt;
        $dashCountBills++;
    } elseif ($type === 'easyload' || $type === 'load') {
        $dashEasyloads += $amt;
        $dashCountLoads++;
    } else {
        $dashCashIn += $amt;
        $dashCountCashIn++;
    }

    if ($provider === 'easypaisa') {
        $dashEasypaisaVolume += $amt;
        $dashEasypaisaCommission += $comm;
    } elseif ($provider === 'jazzcash') {
        $dashJazzcashVolume += $amt;
        $dashJazzcashCommission += $comm;
    } else {
        $dashOtherVolume += $amt;
        $dashOtherCommission += $comm;
    }

    $sectionProfits['services']['total_rev'] += $amt;
    $sectionProfits['services']['total_cost'] += 0;
    $sectionProfits['services']['total_profit'] += $comm;
    $sectionProfits['services']['total_count']++;

    if ($sMonth === $currentMonth) {
        $sectionProfits['services']['month_rev'] += $amt;
        $sectionProfits['services']['month_cost'] += 0;
        $sectionProfits['services']['month_profit'] += $comm;
        $sectionProfits['services']['month_count']++;
    }
    if ($sDate === $todayDate) {
        $sectionProfits['services']['today_rev'] += $amt;
        $sectionProfits['services']['today_cost'] += 0;
        $sectionProfits['services']['today_profit'] += $comm;
        $sectionProfits['services']['today_count']++;
    }
}

$dashTxCount = count($services);
// Net Cash Impact in Drawer: Total Cash-In Collected + Total Commission - Total Cash-Out Paid
$dashNetDrawerCashFlow = ($dashCashIn + $dashBillPayments + $dashEasyloads + $dashCommission) - $dashCashOut;

// 7. Process SIM Packages
$dashPkgCount = count($packages);
$dashPkgVolume = 0;
$dashPkgEarnings = 0;

foreach ($packages as $p) {
    $pDate = substr($p['activatedAt'] ?? $p['createdAt'] ?? $p['date'] ?? $p['timestamp'] ?? '', 0, 10);
    $pMonth = substr($p['activatedAt'] ?? $p['createdAt'] ?? $p['date'] ?? $p['timestamp'] ?? '', 0, 7);
    $retail = floatval($p['totalCollected'] ?? ($p['retailPrice'] ?? 0));
    $fee = floatval($p['shopFee'] ?? ($p['manualProfit'] ?? 0) + ($p['shopCharges'] ?? 0) + ($p['additionalCharges'] ?? 0));
    $cost = floatval($p['costPrice'] ?? max(0, $retail - $fee));

    $dashPkgVolume += $retail;
    $dashPkgEarnings += $fee;

    $sectionProfits['packages']['total_rev'] += $retail;
    $sectionProfits['packages']['total_cost'] += $cost;
    $sectionProfits['packages']['total_profit'] += $fee;
    $sectionProfits['packages']['total_count']++;

    if ($pMonth === $currentMonth) {
        $sectionProfits['packages']['month_rev'] += $retail;
        $sectionProfits['packages']['month_cost'] += $cost;
        $sectionProfits['packages']['month_profit'] += $fee;
        $sectionProfits['packages']['month_count']++;
    }
    if ($pDate === $todayDate) {
        $sectionProfits['packages']['today_rev'] += $retail;
        $sectionProfits['packages']['today_cost'] += $cost;
        $sectionProfits['packages']['today_profit'] += $fee;
        $sectionProfits['packages']['today_count']++;
    }
}

// 8. Process Expenses
$todayExpenses = 0;
$monthlyExpenses = 0;
$totalExpenses = 0;

foreach ($expenses as $exp) {
    $eDate = substr($exp['date'] ?? $exp['createdAt'] ?? $exp['timestamp'] ?? '', 0, 10);
    $eMonth = substr($exp['date'] ?? $exp['createdAt'] ?? $exp['timestamp'] ?? '', 0, 7);
    $amt = floatval($exp['amount'] ?? 0);

    $totalExpenses += $amt;
    if ($eMonth === $currentMonth) $monthlyExpenses += $amt;
    if ($eDate === $todayDate) $todayExpenses += $amt;
}

// 9. Store-Wide Combined Aggregates
$storeTotals = [
    'today' => ['rev' => 0, 'cost' => 0, 'gross_profit' => 0, 'expenses' => $todayExpenses, 'net_profit' => 0, 'count' => 0],
    'month' => ['rev' => 0, 'cost' => 0, 'gross_profit' => 0, 'expenses' => $monthlyExpenses, 'net_profit' => 0, 'count' => 0],
    'total' => ['rev' => 0, 'cost' => 0, 'gross_profit' => 0, 'expenses' => $totalExpenses, 'net_profit' => 0, 'count' => 0],
];

foreach ($sectionProfits as $secKey => $sec) {
    $storeTotals['today']['rev'] += $sec['today_rev'];
    $storeTotals['today']['cost'] += $sec['today_cost'];
    $storeTotals['today']['gross_profit'] += $sec['today_profit'];
    $storeTotals['today']['count'] += $sec['today_count'];

    $storeTotals['month']['rev'] += $sec['month_rev'];
    $storeTotals['month']['cost'] += $sec['month_cost'];
    $storeTotals['month']['gross_profit'] += $sec['month_profit'];
    $storeTotals['month']['count'] += $sec['month_count'];

    $storeTotals['total']['rev'] += $sec['total_rev'];
    $storeTotals['total']['cost'] += $sec['total_cost'];
    $storeTotals['total']['gross_profit'] += $sec['total_profit'];
    $storeTotals['total']['count'] += $sec['total_count'];
}

$storeTotals['today']['net_profit'] = $storeTotals['today']['gross_profit'] - $storeTotals['today']['expenses'];
$storeTotals['month']['net_profit'] = $storeTotals['month']['gross_profit'] - $storeTotals['month']['expenses'];
$storeTotals['total']['net_profit'] = $storeTotals['total']['gross_profit'] - $storeTotals['total']['expenses'];

// Inventory alerts
$totalProducts = count($products);
$lowStock = 0;
$outOfStock = 0;
foreach ($products as $p) {
    $st = intval($p['stock'] ?? 0);
    $min = intval($p['minStock'] ?? 2);
    if ($st <= 0) $outOfStock++;
    else if ($st <= $min) $lowStock++;
}

$monthlyNetProfit = $storeTotals['month']['net_profit'];
$monthlyMarginPct = $monthlyRevenue > 0 ? round(($monthlyNetProfit / $monthlyRevenue) * 100, 1) : 0;
$monthlyAOV = $monthlyCount > 0 ? round($monthlyRevenue / $monthlyCount) : 0;

$chartLabels = array_column($weekDaysMap, 'label');
$chartRevenues = array_column($weekDaysMap, 'revenue');

$yearMonthLabels = array_column($yearMonthsMap, 'label');
$yearMonthRevenues = array_column($yearMonthsMap, 'revenue');
$yearMonthProfits = array_column($yearMonthsMap, 'profit');

$recentSales = array_slice(array_reverse($sales), 0, 5);
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Dashboard Overview</h1>
                <p class="page-header-sub">Safdar Mobile Store business performance, CCTV surveillance, repair lab, section-wise gross profit &amp; stock alerts</p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <a href="cctv.php" class="pos-btn pos-btn-outline" style="border-color:#dc2626; color:#dc2626; font-weight:800; text-decoration:none;">
                    <i class="fa-solid fa-video"></i> CCTV Surveillance
                </a>
                <a href="mobile-repairs.php" class="pos-btn pos-btn-outline" style="border-color:#d97706; color:#d97706; font-weight:800; text-decoration:none;">
                    <i class="fa-solid fa-screwdriver-wrench"></i> Repair Lab
                </a>
                <a href="pos.php" class="pos-btn pos-btn-primary pos-btn-lg">
                    <i class="fa-solid fa-cash-register"></i> Open POS Terminal
                </a>
            </div>
        </div>

        <!-- Quick Shortcuts on Top of Dashboard -->
        <div class="pos-card" style="margin-bottom:20px; border-radius:16px; padding:18px 20px; box-shadow:0 4px 20px rgba(0,0,0,0.04); border-left:5px solid var(--pos-gold); background:#ffffff;">
            <div class="pos-card-header" style="margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
                <h3 class="pos-card-title" style="font-size:1.05rem; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-bolt" style="color:var(--pos-gold);"></i> Quick Shortcuts
                </h3>
                <span style="font-size:0.75rem; color:#64748b; font-weight:600;">1-Click Instant Access Across All Store Operations</span>
            </div>
            <div class="quick-actions-grid">
                <a href="pos.php" class="quick-action-btn">
                    <i class="fa-solid fa-cash-register"></i>
                    <span>POS Checkout</span>
                </a>
                <a href="cctv.php" class="quick-action-btn" style="border-color:#fecaca;">
                    <i class="fa-solid fa-video" style="color:#dc2626;"></i>
                    <span>CCTV Surveillance</span>
                </a>
                <a href="mobile-repairs.php" class="quick-action-btn" style="border-color:#fed7aa;">
                    <i class="fa-solid fa-screwdriver-wrench" style="color:#d97706;"></i>
                    <span>Mobile Repair Lab</span>
                </a>
                <a href="packages.php" class="quick-action-btn" style="border-color:#fecdd3;">
                    <i class="fa-solid fa-sim-card" style="color:#e11d48;"></i>
                    <span>SIM Packages &amp; Load</span>
                </a>
                <a href="services.php" class="quick-action-btn" style="border-color:#a7f3d0;">
                    <i class="fa-solid fa-money-bill-transfer" style="color:#059669;"></i>
                    <span>Easypaisa / JazzCash</span>
                </a>
                <a href="bills.php" class="quick-action-btn" style="border-color:#fde68a;">
                    <i class="fa-solid fa-file-invoice-dollar" style="color:#f59e0b;"></i>
                    <span>Utility Bills</span>
                </a>
                <a href="nadra-kiosk.php" class="quick-action-btn" style="border-color:#bfdbfe;">
                    <i class="fa-solid fa-id-card" style="color:#2563eb;"></i>
                    <span>NADRA Kiosk</span>
                </a>
                <a href="product-add.php" class="quick-action-btn">
                    <i class="fa-solid fa-plus"></i>
                    <span>Add New Product</span>
                </a>
                <a href="purchases.php" class="quick-action-btn">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                    <span>New Purchase</span>
                </a>
                <a href="expenses.php" class="quick-action-btn">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>Log Expense</span>
                </a>
                <a href="reports.php" class="quick-action-btn">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>P&amp;L Reports</span>
                </a>
            </div>
        </div>

        <!-- Primary Stat Cards Grid (All-Time Historical + Real-Time Live Performance) -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom:20px;">
            <!-- 1. All-Time Store Gross Profit (All Historical Data) -->
            <div class="stat-card" style="border-left:4px solid #059669; background:linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%);">
                <div class="stat-icon green"><i class="fa-solid fa-sack-dollar"></i></div>
                <div class="stat-info">
                    <div class="stat-label" style="color:#065f46; font-weight:800;">ALL-TIME GROSS PROFIT</div>
                    <div class="stat-value" style="color:#059669;">+PKR <?php echo number_format($storeTotals['total']['gross_profit']); ?></div>
                    <div style="font-size:0.75rem; color:#059669; margin-top:2px; font-weight:700;">
                        This Month: +PKR <?php echo number_format($storeTotals['month']['gross_profit']); ?> | Today: +PKR <?php echo number_format($storeTotals['today']['gross_profit']); ?>
                    </div>
                </div>
            </div>

            <!-- 2. Total Store Turnover (All Time) -->
            <div class="stat-card" style="border-left:4px solid #0f172a;">
                <div class="stat-icon" style="background:rgba(15,23,42,0.08); color:#0f172a;"><i class="fa-solid fa-cash-register"></i></div>
                <div class="stat-info">
                    <div class="stat-label" style="color:#0f172a; font-weight:800;">TOTAL STORE TURNOVER</div>
                    <div class="stat-value" style="color:#0f172a;">PKR <?php echo number_format($storeTotals['total']['rev']); ?></div>
                    <div style="font-size:0.75rem; color:#475569; margin-top:2px; font-weight:600;">
                        <?php echo $storeTotals['total']['count']; ?> Total Completed Jobs &amp; Sales (All History)
                    </div>
                </div>
            </div>

            <!-- 3. POS Retail & Mobile Sales (All Time) -->
            <div class="stat-card" style="border-left:4px solid #2563eb;">
                <div class="stat-icon" style="background:rgba(37,99,235,0.1); color:#2563eb;"><i class="fa-solid fa-cart-shopping"></i></div>
                <div class="stat-info">
                    <div class="stat-label" style="color:#1e40af; font-weight:800;">POS RETAIL &amp; MOBILES</div>
                    <div class="stat-value" style="color:#2563eb;">PKR <?php echo number_format($sectionProfits['sales']['total_rev']); ?></div>
                    <div style="font-size:0.75rem; color:#2563eb; margin-top:2px; font-weight:700;">
                        +PKR <?php echo number_format($sectionProfits['sales']['total_profit']); ?> Gross Profit (<?php echo $sectionProfits['sales']['total_count']; ?> Invoices)
                    </div>
                </div>
            </div>

            <!-- 4. CCTV Security Installations (All Time) -->
            <div class="stat-card" style="border-left:4px solid #dc2626;">
                <div class="stat-icon" style="background:rgba(220,38,38,0.1); color:#dc2626;"><i class="fa-solid fa-video"></i></div>
                <div class="stat-info">
                    <div class="stat-label" style="color:#991b1b; font-weight:800;">CCTV SECURITY PROFIT</div>
                    <div class="stat-value" style="color:#dc2626;">+PKR <?php echo number_format($dashCctvTotalLaborProfit); ?></div>
                    <div style="font-size:0.75rem; color:#dc2626; margin-top:2px; font-weight:700;">
                        PKR <?php echo number_format($dashCctvTotalRevenue); ?> Turnover (<?php echo $dashCctvTotalCount; ?> Sites Installed)
                    </div>
                </div>
            </div>

            <!-- 5. Mobile Hardware Repair Lab (All Time) -->
            <div class="stat-card" style="border-left:4px solid #f59e0b;">
                <div class="stat-icon gold"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                <div class="stat-info">
                    <div class="stat-label" style="color:#92400e; font-weight:800;">MOBILE REPAIR LAB PROFIT</div>
                    <div class="stat-value" style="color:#d97706;">+PKR <?php echo number_format($dashRepairTotalLaborProfit); ?></div>
                    <div style="font-size:0.75rem; color:#d97706; margin-top:2px; font-weight:700;">
                        PKR <?php echo number_format($dashRepairTotalRevenue); ?> Turnover (<?php echo $dashRepairTotalCount; ?> Repair Jobs)
                    </div>
                </div>
            </div>

            <!-- 6. Digital Services, Bills & Load Profit (All Time) -->
            <div class="stat-card" style="border-left:4px solid #059669;">
                <div class="stat-icon green"><i class="fa-solid fa-wallet"></i></div>
                <div class="stat-info">
                    <?php 
                    $digiProfit = $sectionProfits['services']['total_profit'] + $sectionProfits['bills']['total_profit'] + $sectionProfits['nadra']['total_profit'] + $sectionProfits['packages']['total_profit'];
                    $digiRev = $sectionProfits['services']['total_rev'] + $sectionProfits['bills']['total_rev'] + $sectionProfits['nadra']['total_rev'] + $sectionProfits['packages']['total_rev'];
                    $digiCount = $sectionProfits['services']['total_count'] + $sectionProfits['bills']['total_count'] + $sectionProfits['nadra']['total_count'] + $sectionProfits['packages']['total_count'];
                    ?>
                    <div class="stat-label" style="color:#065f46; font-weight:800;">SERVICES &amp; BILLS PROFIT</div>
                    <div class="stat-value" style="color:#059669;">+PKR <?php echo number_format($digiProfit); ?></div>
                    <div style="font-size:0.75rem; color:#065f46; margin-top:2px; font-weight:700;">
                        PKR <?php echo number_format($digiRev); ?> Volume (<?php echo $digiCount; ?> Transfers/Bills/Plans)
                    </div>
                </div>
            </div>
        </div>

        <!-- =========================================================================
             SECTION-WISE GROSS PROFIT & EARNINGS PERFORMANCE MATRIX
             ========================================================================= -->
        <div class="pos-card section-profit-matrix-widget" style="margin-bottom:24px; border-radius:18px; padding:24px; box-shadow:0 6px 30px rgba(5,150,105,0.09); border-left:6px solid #059669; background:#ffffff;">
            <div class="widget-header" style="display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:18px; border-bottom:1px solid #d1fae5; padding-bottom:16px; flex-wrap:wrap;">
                <div class="widget-header-left" style="display:flex; align-items:center; gap:14px; min-width:0;">
                    <div class="widget-icon" style="width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg, #059669 0%, #047857 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 8px 20px rgba(5,150,105,0.35); flex-shrink:0;">
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                            <span style="background:#059669; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:6px; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:4px;">
                                <i class="fa-solid fa-sack-dollar"></i> MULTI-DEPARTMENT PROFIT MATRIX
                            </span>
                            <span style="background:#b45309; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:6px; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:4px;">
                                <i class="fa-solid fa-receipt"></i> 7 BUSINESS SECTIONS
                            </span>
                        </div>
                        <h3 class="widget-title" style="margin:0; font-size:1.3rem; font-weight:800; color:#065f46; font-family:var(--pos-font-heading); letter-spacing:-0.01em;">
                            Section-Wise Gross Profit &amp; Department Performance
                        </h3>
                        <p class="widget-sub" style="margin:3px 0 0 0; font-size:0.82rem; color:#047857; font-weight:500;">
                            Live gross profit, COGS cost, and profit margin analysis across CCTV, Mobile Repair Lab, POS Retail, Bills &amp; Services
                        </p>
                    </div>
                </div>

                <!-- Timeframe Switcher Tabs -->
                <div style="display:flex; gap:6px; background:#f0fdf4; padding:4px; border-radius:30px; border:1.5px solid #a7f3d0; flex-wrap:wrap;">
                    <button type="button" class="pos-btn profit-tab-btn active" id="profitTabBtnTotal" onclick="switchProfitTimeframe('total')" style="padding:7px 16px; font-size:0.8rem; font-weight:800; border-radius:20px; background:#059669; color:#fff; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 6px rgba(5,150,105,0.25);">
                        <i class="fa-solid fa-chart-line"></i> All-Time Total (All Historical &amp; Old Data)
                    </button>
                    <button type="button" class="pos-btn profit-tab-btn" id="profitTabBtnMonth" onclick="switchProfitTimeframe('month')" style="padding:7px 16px; font-size:0.8rem; font-weight:800; border-radius:20px; background:transparent; color:#065f46; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-calendar-days"></i> This Month (<?php echo date('M Y'); ?>)
                    </button>
                    <button type="button" class="pos-btn profit-tab-btn" id="profitTabBtnToday" onclick="switchProfitTimeframe('today')" style="padding:7px 16px; font-size:0.8rem; font-weight:800; border-radius:20px; background:transparent; color:#065f46; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-bolt"></i> Today (Daily Live)
                    </button>
                </div>
            </div>

            <?php foreach (['total', 'month', 'today'] as $tf): 
                $tfTotals = $storeTotals[$tf];
                $totGrossProf = $tfTotals['gross_profit'];
                $totRev = $tfTotals['rev'];
                $totCost = $tfTotals['cost'];
                $totExp = $tfTotals['expenses'];
                $totNet = $tfTotals['net_profit'];
                $overallMargin = $totRev > 0 ? round(($totGrossProf / $totRev) * 100, 1) : 0;
            ?>
                <div id="profitContent_<?php echo $tf; ?>" class="profit-timeframe-content" style="<?php echo $tf === 'total' ? 'display:block;' : 'display:none;'; ?>">
                    <!-- 4 Top Scorecard Metrics for this timeframe -->
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:14px; margin-bottom:20px;">
                        <!-- 1. Combined Gross Profit -->
                        <div style="background:linear-gradient(135deg, #059669 0%, #047857 100%); color:#fff; border-radius:14px; padding:16px 20px; box-shadow:0 4px 15px rgba(5,150,105,0.25); display:flex; flex-direction:column; justify-content:space-between;">
                            <div style="font-size:0.72rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9;">
                                <i class="fa-solid fa-sack-dollar"></i> COMBINED GROSS PROFIT
                            </div>
                            <div style="font-size:1.85rem; font-weight:900; font-family:var(--pos-font-heading); margin:6px 0 2px 0;">
                                +PKR <?php echo number_format($totGrossProf); ?>
                            </div>
                            <div style="font-size:0.75rem; font-weight:700; opacity:0.95;">
                                <i class="fa-solid fa-percent"></i> <?php echo $overallMargin; ?>% Overall Gross Margin Across All 7 Sections
                            </div>
                        </div>

                        <!-- 2. Gross Store Turnover -->
                        <div style="background:#ffffff; border:1.5px solid #cbd5e1; border-radius:14px; padding:16px 20px; box-shadow:0 2px 10px rgba(0,0,0,0.03); display:flex; flex-direction:column; justify-content:space-between;">
                            <div style="font-size:0.72rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">
                                <i class="fa-solid fa-cash-register" style="color:#2563eb;"></i> TOTAL STORE TURNOVER
                            </div>
                            <div style="font-size:1.65rem; font-weight:900; color:#0f172a; font-family:var(--pos-font-heading); margin:6px 0 2px 0;">
                                PKR <?php echo number_format($totRev); ?>
                            </div>
                            <div style="font-size:0.75rem; color:#64748b; font-weight:600;">
                                <?php echo $tfTotals['count']; ?> Total Combined Transactions / Jobs
                            </div>
                        </div>

                        <!-- 3. Direct Stock / Hardware Costs (COGS) -->
                        <div style="background:#ffffff; border:1.5px solid #fecaca; border-radius:14px; padding:16px 20px; box-shadow:0 2px 10px rgba(220,38,38,0.04); display:flex; flex-direction:column; justify-content:space-between;">
                            <div style="font-size:0.72rem; font-weight:800; color:#991b1b; text-transform:uppercase; letter-spacing:0.5px;">
                                <i class="fa-solid fa-boxes-packing" style="color:#dc2626;"></i> DIRECT COSTS / COGS
                            </div>
                            <div style="font-size:1.65rem; font-weight:900; color:#dc2626; font-family:var(--pos-font-heading); margin:6px 0 2px 0;">
                                -PKR <?php echo number_format($totCost); ?>
                            </div>
                            <div style="font-size:0.75rem; color:#991b1b; font-weight:600;">
                                Stock Purchase, Spare Parts &amp; Bills Cost
                            </div>
                        </div>

                        <!-- 4. Net Take-Home Shop Profit -->
                        <div style="background:<?php echo $totNet >= 0 ? '#ecfdf5' : '#fef2f2'; ?>; border:1.5px solid <?php echo $totNet >= 0 ? '#a7f3d0' : '#fecaca'; ?>; border-radius:14px; padding:16px 20px; box-shadow:0 2px 10px rgba(0,0,0,0.03); display:flex; flex-direction:column; justify-content:space-between;">
                            <div style="font-size:0.72rem; font-weight:800; color:<?php echo $totNet >= 0 ? '#065f46' : '#991b1b'; ?>; text-transform:uppercase; letter-spacing:0.5px;">
                                <i class="fa-solid fa-vault"></i> NET PROFIT (AFTER EXPENSES)
                            </div>
                            <div style="font-size:1.65rem; font-weight:900; color:<?php echo $totNet >= 0 ? '#059669' : '#dc2626'; ?>; font-family:var(--pos-font-heading); margin:6px 0 2px 0;">
                                PKR <?php echo number_format($totNet); ?>
                            </div>
                            <div style="font-size:0.75rem; color:<?php echo $totNet >= 0 ? '#065f46' : '#991b1b'; ?>; font-weight:700;">
                                After -PKR <?php echo number_format($totExp); ?> Shop Expenses
                            </div>
                        </div>
                    </div>

                    <!-- Department Gross Profit Breakdown Matrix Table -->
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.03);">
                        <div style="padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                            <strong style="font-size:0.88rem; color:#0f172a; display:flex; align-items:center; gap:8px;">
                                <i class="fa-solid fa-table-list" style="color:#059669;"></i> Section-Wise Profitability Breakdown Matrix
                            </strong>
                            <span style="font-size:0.75rem; color:#64748b; font-weight:700;">
                                Timeframe: <strong><?php echo $tf === 'month' ? date('F Y') : ($tf === 'today' ? 'Today (' . date('M d, Y') . ')' : 'All Recorded History'); ?></strong>
                            </span>
                        </div>

                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse; font-size:0.82rem; text-align:left;">
                                <thead>
                                    <tr style="background:#f1f5f9; border-bottom:1px solid #cbd5e1; color:#475569; font-size:0.72rem; text-transform:uppercase;">
                                        <th style="padding:10px 14px;">Business Section / Department</th>
                                        <th style="padding:10px 12px;">Turnover (Revenue)</th>
                                        <th style="padding:10px 12px;">Direct Cost / COGS</th>
                                        <th style="padding:10px 12px;">Gross Profit (PKR)</th>
                                        <th style="padding:10px 12px; text-align:center;">Margin %</th>
                                        <th style="padding:10px 12px;">Profit Contribution Share</th>
                                        <th style="padding:10px 12px; text-align:center;">Volume</th>
                                        <th style="padding:10px 14px; text-align:right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ($sectionProfits as $secKey => $sec): 
                                        $sRev = $sec[$tf . '_rev'];
                                        $sCost = $sec[$tf . '_cost'];
                                        $sProfit = $sec[$tf . '_profit'];
                                        $sCount = $sec[$tf . '_count'];
                                        $sMargin = $sRev > 0 ? round(($sProfit / $sRev) * 100, 1) : 0;
                                        $sharePct = $totGrossProf > 0 ? round(($sProfit / $totGrossProf) * 100, 1) : 0;
                                    ?>
                                        <tr style="border-bottom:1px solid #f1f5f9; transition:background 0.15s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                                            <!-- Department -->
                                            <td style="padding:10px 14px;">
                                                <div style="display:flex; align-items:center; gap:10px;">
                                                    <span style="width:34px; height:34px; border-radius:8px; background:<?php echo $sec['bg']; ?>; color:<?php echo $sec['color']; ?>; display:inline-flex; align-items:center; justify-content:center; font-size:0.95rem; flex-shrink:0;">
                                                        <i class="<?php echo $sec['icon']; ?>"></i>
                                                    </span>
                                                    <div>
                                                        <strong style="font-size:0.86rem; color:#0f172a;"><?php echo $sec['name']; ?></strong>
                                                        <div style="font-size:0.68rem; color:#64748b;"><?php echo $sec['subtitle']; ?></div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Turnover -->
                                            <td style="padding:10px 12px; font-weight:700; color:#1e293b;">
                                                PKR <?php echo number_format($sRev); ?>
                                            </td>

                                            <!-- Direct Cost -->
                                            <td style="padding:10px 12px; color:#64748b; font-weight:600;">
                                                <?php echo $sCost > 0 ? 'PKR ' . number_format($sCost) : '<span style="color:#94a3b8;">PKR 0 (Service)</span>'; ?>
                                            </td>

                                            <!-- Gross Profit -->
                                            <td style="padding:10px 12px;">
                                                <strong style="color:#059669; font-size:0.95rem; font-family:var(--pos-font-heading);">
                                                    +PKR <?php echo number_format($sProfit); ?>
                                                </strong>
                                            </td>

                                            <!-- Margin % -->
                                            <td style="padding:10px 12px; text-align:center;">
                                                <span class="status-badge" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; font-weight:800; font-size:0.72rem; padding:2px 7px;">
                                                    <?php echo $sMargin; ?>%
                                                </span>
                                            </td>

                                            <!-- Profit Contribution Share Progress Bar -->
                                            <td style="padding:10px 12px; min-width:140px;">
                                                <div style="display:flex; justify-content:space-between; font-size:0.7rem; font-weight:700; color:#475569; margin-bottom:3px;">
                                                    <span><?php echo $sharePct; ?>% of Total</span>
                                                </div>
                                                <div style="width:100%; height:6px; background:#e2e8f0; border-radius:6px; overflow:hidden;">
                                                    <div style="width:<?php echo min(100, $sharePct); ?>%; height:100%; background:<?php echo $sec['color']; ?>; border-radius:6px;"></div>
                                                </div>
                                            </td>

                                            <!-- Volume / Tickets -->
                                            <td style="padding:10px 12px; text-align:center; font-weight:700; color:#334155;">
                                                <?php echo $sCount; ?>
                                            </td>

                                            <!-- Quick Action Link -->
                                            <td style="padding:10px 14px; text-align:right; white-space:nowrap;">
                                                <a href="<?php echo $sec['link']; ?>" class="pos-btn pos-btn-outline pos-btn-sm" style="padding:3px 8px; font-size:0.72rem; font-weight:700; text-decoration:none;">
                                                    Open &rarr;
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- =========================================================================
             CCTV SECURITY & SURVEILLANCE PERFORMANCE SUMMARY WIDGET
             ========================================================================= -->
        <div class="pos-card cctv-summary-widget" style="margin-bottom:24px; border-radius:18px; padding:24px; box-shadow:0 4px 25px rgba(220,38,38,0.09); border-left:5px solid #dc2626;">
            <div class="widget-header" style="display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:18px; border-bottom:1px solid #fecaca; padding-bottom:16px; flex-wrap:wrap;">
                <div class="widget-header-left" style="display:flex; align-items:center; gap:16px; min-width:0;">
                    <div class="widget-icon" style="width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 8px 20px rgba(220,38,38,0.35); flex-shrink:0;">
                        <i class="fa-solid fa-video"></i>
                    </div>
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                            <span style="background:#dc2626; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:6px; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:4px;">
                                <i class="fa-solid fa-shield-halved"></i> HIKVISION &amp; DAHUA
                            </span>
                            <span style="background:#059669; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:6px; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:4px;">
                                <i class="fa-solid fa-building-shield"></i> COMMERCIAL &amp; RESIDENTIAL
                            </span>
                        </div>
                        <h3 class="widget-title" style="margin:0; font-size:1.25rem; font-weight:800; color:#991b1b; font-family:var(--pos-font-heading); letter-spacing:-0.01em;">
                            CCTV Security &amp; Surveillance Performance Summary
                        </h3>
                        <p class="widget-sub" style="margin:3px 0 0 0; font-size:0.82rem; color:#7f1d1d; font-weight:500;">
                            Live records of camera installations, commercial security sites, client billing &amp; warranty status
                        </p>
                    </div>
                </div>
                <div class="widget-header-right" style="flex-shrink:0; display:flex; gap:8px; flex-wrap:wrap;">
                    <a href="cctv.php?action=new" class="pos-btn" style="background:#059669; color:#fff; padding:10px 18px; border-radius:30px; font-weight:800; font-size:0.85rem; box-shadow:0 4px 15px rgba(5,150,105,0.3); text-decoration:none; display:inline-flex; align-items:center; gap:6px; white-space:nowrap;">
                        <i class="fa-solid fa-camera"></i> + Book CCTV Installation
                    </a>
                    <a href="cctv.php" class="pos-btn" style="background:linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color:#fff; padding:10px 20px; border-radius:30px; font-weight:800; font-size:0.85rem; box-shadow:0 4px 15px rgba(220,38,38,0.3); text-decoration:none; display:inline-flex; align-items:center; gap:8px; white-space:nowrap;">
                        <i class="fa-solid fa-video"></i> CCTV Management (<?php echo $dashCctvTotalCount; ?>) <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- 4 Metric Cards Grid for CCTV -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:18px;">
                <!-- 1. Total CCTV Projects -->
                <div class="dash-sub-box box-red" style="background:#ffffff; border:1.5px solid #fecaca; border-radius:14px; padding:18px 20px; box-shadow:0 2px 10px rgba(220,38,38,0.06); display:flex; flex-direction:column; justify-content:space-between; gap:6px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label text-red" style="background:rgba(220,38,38,0.12); color:#991b1b; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-video" style="color:#dc2626;"></i> SECURITY SITES
                        </div>
                    </div>
                    <div class="dash-box-val val-red" style="font-size:1.65rem; font-weight:900; color:#dc2626; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        <?php echo $dashCctvTotalCount; ?> Projects
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#991b1b; font-weight:700; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-check-circle" style="color:#059669;"></i> <?php echo $dashCctvActiveSites; ?> Installed &amp; Active Sites
                    </div>
                </div>

                <!-- 2. Total CCTV Revenue -->
                <div class="dash-sub-box box-green" style="background:#ffffff; border:1.5px solid #a7f3d0; border-radius:14px; padding:18px 20px; box-shadow:0 2px 10px rgba(16,185,129,0.06); display:flex; flex-direction:column; justify-content:space-between; gap:6px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label text-green" style="background:rgba(16,185,129,0.12); color:#065f46; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-wallet" style="color:#10b981;"></i> TOTAL CCTV REVENUE
                        </div>
                    </div>
                    <div class="dash-box-val val-green" style="font-size:1.65rem; font-weight:900; color:#059669; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        PKR <?php echo number_format($dashCctvTotalRevenue); ?>
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#065f46; font-weight:600; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-shield" style="color:#10b981;"></i> Hardware &amp; Setup Packages
                    </div>
                </div>

                <!-- 3. Total Labor Profit -->
                <div class="dash-sub-box box-gold" style="background:linear-gradient(145deg, #fffdf5 0%, #fffbeb 100%); border:2px solid #f59e0b; border-radius:14px; padding:18px 20px; box-shadow:0 4px 15px rgba(245,158,11,0.12); display:flex; flex-direction:column; justify-content:space-between; gap:6px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label text-gold" style="background:rgba(245,158,11,0.18); color:#92400e; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-sack-dollar" style="color:#f59e0b;"></i> INSTALLATION PROFIT
                        </div>
                    </div>
                    <div class="dash-box-val val-gold" style="font-size:1.65rem; font-weight:900; color:#d97706; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        +PKR <?php echo number_format($dashCctvTotalLaborProfit); ?>
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#92400e; font-weight:700; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-circle-check" style="color:#059669;"></i> 100% Service Labor Net Profit
                    </div>
                </div>

                <!-- 4. Outstanding Balance Due -->
                <div class="dash-sub-box" style="background:#ffffff; border:1.5px solid #fed7aa; border-radius:14px; padding:18px 20px; box-shadow:0 2px 10px rgba(245,158,11,0.06); display:flex; flex-direction:column; justify-content:space-between; gap:6px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label" style="background:rgba(217,119,6,0.12); color:#b45309; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-hand-holding-dollar" style="color:#d97706;"></i> RECEIVABLES DUE
                        </div>
                    </div>
                    <div class="dash-box-val" style="font-size:1.65rem; font-weight:900; color:#b45309; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        PKR <?php echo number_format($dashCctvTotalDue); ?>
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#b45309; font-weight:600; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-file-invoice" style="color:#d97706;"></i> Uncollected Client Balances
                    </div>
                </div>
            </div>

            <!-- Recent CCTV Installations Table on Dashboard -->
            <div style="background:#ffffff; border:1px solid #fecaca; border-radius:12px; overflow:hidden;">
                <div style="padding:10px 14px; background:#fff1f2; border-bottom:1px solid #fecaca; display:flex; justify-content:space-between; align-items:center;">
                    <strong style="font-size:0.85rem; color:#991b1b; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-video"></i> Recent CCTV Security Installations &amp; Sites
                    </strong>
                    <a href="cctv.php" style="font-size:0.75rem; color:#dc2626; font-weight:800; text-decoration:none;">View All CCTV Projects &rarr;</a>
                </div>

                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:0.8rem; text-align:left;">
                        <thead>
                            <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#64748b; font-size:0.72rem; text-transform:uppercase;">
                                <th style="padding:8px 10px;">Project #</th>
                                <th style="padding:8px 10px;">Client &amp; Site Address</th>
                                <th style="padding:8px 10px;">Camera System</th>
                                <th style="padding:8px 10px;">Bill / Due</th>
                                <th style="padding:8px 10px; text-align:center;">Status</th>
                                <th style="padding:8px 10px; text-align:right; width:200px;">Actions (Return / Delete)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentCctv)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:25px; color:#94a3b8;">No CCTV projects logged yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentCctv as $pj): 
                                    $st = strtolower($pj['status'] ?? 'installed');
                                    $isRet = ($st === 'returned' || $st === 'cancelled');
                                    $stBg = $isRet ? '#fee2e2' : (($st === 'installed' || $st === 'active') ? '#ecfdf5' : '#eff6ff');
                                    $stColor = $isRet ? '#dc2626' : (($st === 'installed' || $st === 'active') ? '#059669' : '#2563eb');
                                    $due = max(0, floatval($pj['totalBill'] ?? 0) - floatval($pj['advancePaid'] ?? 0));
                                ?>
                                    <tr style="border-bottom:1px solid #f1f5f9; <?php echo $isRet ? 'background:#fff5f5; opacity:0.85;' : ''; ?>">
                                        <td style="padding:8px 10px; font-weight:800; font-family:monospace; color:var(--pos-red);">
                                            <?php echo htmlspecialchars($pj['projectNo'] ?? 'CCTV-000'); ?>
                                            <?php if ($isRet): ?>
                                                <div style="font-size:0.62rem; color:#dc2626; font-weight:800;">CANCELLED / RETURNED</div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:8px 10px;">
                                            <strong><?php echo htmlspecialchars($pj['clientName'] ?? 'Client'); ?></strong>
                                            <div style="font-size:0.68rem; color:#64748b;"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($pj['siteAddress'] ?? 'Hangu'); ?></div>
                                        </td>
                                        <td style="padding:8px 10px;">
                                            <span style="font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($pj['cameraBrand'] ?? 'CCTV'); ?></span>
                                            <div style="font-size:0.68rem; color:#64748b;"><?php echo htmlspecialchars($pj['systemPackage'] ?? ''); ?></div>
                                        </td>
                                        <td style="padding:8px 10px;">
                                            <strong>PKR <?php echo number_format($pj['totalBill'] ?? 0); ?></strong>
                                            <?php if ($isRet): ?>
                                                <div style="font-size:0.68rem; color:#dc2626; font-weight:700;">Refunded</div>
                                            <?php elseif ($due > 0): ?>
                                                <div style="font-size:0.68rem; color:#dc2626; font-weight:700;">Due: PKR <?php echo number_format($due); ?></div>
                                            <?php else: ?>
                                                <div style="font-size:0.68rem; color:#059669; font-weight:700;">Paid</div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:8px 10px; text-align:center;">
                                            <span class="status-badge" style="background:<?php echo $stBg; ?>; color:<?php echo $stColor; ?>; font-size:0.7rem; font-weight:800; text-transform:uppercase;">
                                                <?php echo htmlspecialchars($pj['status'] ?? 'Installed'); ?>
                                            </span>
                                        </td>
                                        <td style="padding:8px 10px; text-align:right; white-space:nowrap;">
                                            <div style="display:inline-flex; gap:4px; align-items:center; justify-content:flex-end;">
                                                <a href="cctv.php?id=<?php echo urlencode($pj['id']); ?>" class="pos-btn pos-btn-outline pos-btn-sm" style="padding:3px 7px; font-size:0.7rem; text-decoration:none;" title="Open CCTV Panel">
                                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open
                                                </a>
                                                <?php if (!$isRet): ?>
                                                    <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" title="Return / Cancel CCTV Project" onclick="openReturnCctvModal('<?php echo htmlspecialchars($pj['id'], ENT_QUOTES); ?>')" style="padding:3px 7px; font-size:0.7rem; color:#d97706; border-color:#fed7aa;">
                                                        <i class="fa-solid fa-rotate-left"></i> Return
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($isSuperAdmin): ?>
                                                    <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" title="Delete CCTV Record" onclick="deleteCctvFromDashboard('<?php echo $pj['id']; ?>', '<?php echo htmlspecialchars($pj['projectNo']); ?>')" style="padding:3px 7px; font-size:0.7rem; color:#dc2626; border-color:#fecaca;">
                                                        <i class="fa-solid fa-trash-can"></i> Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- =========================================================================
             MOBILE REPAIRS LAB & HARDWARE SERVICE LIVE SUMMARY WIDGET
             ========================================================================= -->
        <div class="pos-card repair-summary-widget" style="margin-bottom:24px; border-radius:18px; padding:24px; box-shadow:0 4px 25px rgba(245,158,11,0.09); border-left:5px solid #f59e0b;">
            <div class="widget-header" style="display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:18px; border-bottom:1px solid #fed7aa; padding-bottom:16px; flex-wrap:wrap;">
                <div class="widget-header-left" style="display:flex; align-items:center; gap:16px; min-width:0;">
                    <div class="widget-icon" style="width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg, #d97706 0%, #b45309 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 8px 20px rgba(217,119,6,0.35); flex-shrink:0;">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                    </div>
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                            <span style="background:#d97706; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:6px; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:4px;">
                                <i class="fa-solid fa-mobile-screen"></i> HARDWARE LAB
                            </span>
                            <span style="background:#059669; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:6px; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:4px;">
                                <i class="fa-solid fa-microchip"></i> LCD &amp; IC REPLACEMENTS
                            </span>
                        </div>
                        <h3 class="widget-title" style="margin:0; font-size:1.25rem; font-weight:800; color:#78350f; font-family:var(--pos-font-heading); letter-spacing:-0.01em;">
                            Mobile Repairs &amp; Hardware Lab Performance
                        </h3>
                        <p class="widget-sub" style="margin:3px 0 0 0; font-size:0.82rem; color:#92400e; font-weight:500;">
                            Daily customer devices intake, live job progress, repair labor profit, and claim status
                        </p>
                    </div>
                </div>
                <div class="widget-header-right" style="flex-shrink:0; display:flex; gap:8px;">
                    <a href="mobile-repairs.php" class="pos-btn" style="background:linear-gradient(135deg, #d97706 0%, #b45309 100%); color:#fff; padding:10px 22px; border-radius:30px; font-weight:800; font-size:0.85rem; box-shadow:0 4px 15px rgba(217,119,6,0.3); text-decoration:none; display:inline-flex; align-items:center; gap:8px; white-space:nowrap;">
                        <i class="fa-solid fa-list-check"></i> Open Repair Lab (<?php echo $dashRepairTotalCount; ?> Tickets) <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- 4 Metric Cards Grid for Mobile Repairs -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:18px;">
                <!-- 1. Daily Repair Revenue (Today) -->
                <div class="dash-sub-box box-gold" style="background:#ffffff; border:1.5px solid #fed7aa; border-radius:14px; padding:18px 20px; box-shadow:0 2px 10px rgba(245,158,11,0.06); display:flex; flex-direction:column; justify-content:space-between; gap:6px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label text-gold" style="background:rgba(245,158,11,0.14); color:#92400e; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-calendar-day" style="color:#d97706;"></i> REPAIRS (TODAY)
                        </div>
                    </div>
                    <div class="dash-box-val val-gold" style="font-size:1.65rem; font-weight:900; color:#b45309; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        PKR <?php echo number_format($dashRepairTodayRevenue); ?>
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#92400e; font-weight:700; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-bolt" style="color:#d97706;"></i> <?php echo $dashRepairTodayCount; ?> Device Tickets Logged Today
                    </div>
                </div>

                <!-- 2. Active Devices in Lab -->
                <div class="dash-sub-box" style="background:#ffffff; border:1.5px solid #bae6fd; border-radius:14px; padding:18px 20px; box-shadow:0 2px 10px rgba(56,189,248,0.06); display:flex; flex-direction:column; justify-content:space-between; gap:6px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label" style="background:rgba(2,132,199,0.12); color:#0369a1; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-wrench" style="color:#0284c7;"></i> IN-LAB ACTIVE JOBS
                        </div>
                    </div>
                    <div class="dash-box-val" style="font-size:1.65rem; font-weight:900; color:#0284c7; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        <?php echo $dashRepairActiveJobs; ?> Phones
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#0369a1; font-weight:600; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-clock" style="color:#0284c7;"></i> Received &amp; In-Progress Fixing
                    </div>
                </div>

                <!-- 3. Ready for Customer Pickup -->
                <div class="dash-sub-box box-green" style="background:#ffffff; border:1.5px solid #a7f3d0; border-radius:14px; padding:18px 20px; box-shadow:0 2px 10px rgba(16,185,129,0.06); display:flex; flex-direction:column; justify-content:space-between; gap:6px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label text-green" style="background:rgba(16,185,129,0.12); color:#065f46; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-circle-check" style="color:#10b981;"></i> READY FOR PICKUP
                        </div>
                    </div>
                    <div class="dash-box-val val-green" style="font-size:1.65rem; font-weight:900; color:#059669; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        <?php echo $dashRepairReadyJobs; ?> Phones
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#065f46; font-weight:600; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-box-archive" style="color:#10b981;"></i> Fixed &amp; Waiting for Handover
                    </div>
                </div>

                <!-- 4. Total Shop Labor Profit -->
                <div class="dash-sub-box box-gold" style="background:linear-gradient(145deg, #fffdf5 0%, #fffbeb 100%); border:2px solid #f59e0b; border-radius:14px; padding:18px 20px; box-shadow:0 4px 15px rgba(245,158,11,0.12); display:flex; flex-direction:column; justify-content:space-between; gap:6px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label text-gold" style="background:rgba(245,158,11,0.18); color:#92400e; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-sack-dollar" style="color:#f59e0b;"></i> TOTAL LABOR PROFIT
                        </div>
                    </div>
                    <div class="dash-box-val val-gold" style="font-size:1.65rem; font-weight:900; color:#d97706; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        +PKR <?php echo number_format($dashRepairTotalLaborProfit); ?>
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#92400e; font-weight:700; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-circle-dollar-to-slot" style="color:#059669;"></i> 100% Net Labor Service Profit
                    </div>
                </div>
            </div>

            <!-- Recent Mobile Repair Jobs Table on Dashboard -->
            <div style="background:#ffffff; border:1px solid #fed7aa; border-radius:12px; overflow:hidden;">
                <div style="padding:10px 14px; background:#fffbeb; border-bottom:1px solid #fed7aa; display:flex; justify-content:space-between; align-items:center;">
                    <strong style="font-size:0.85rem; color:#92400e; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-clock-rotate-left"></i> Recent Mobile Repair Jobs (Live Lab Intake)
                    </strong>
                    <a href="mobile-repairs.php" style="font-size:0.75rem; color:#b45309; font-weight:800; text-decoration:none;">View All In Lab &rarr;</a>
                </div>

                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:0.8rem; text-align:left;">
                        <thead>
                            <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#64748b; font-size:0.72rem; text-transform:uppercase;">
                                <th style="padding:8px 10px;">Ticket #</th>
                                <th style="padding:8px 10px;">Customer</th>
                                <th style="padding:8px 10px;">Device &amp; Model</th>
                                <th style="padding:8px 10px;">Reported Fault</th>
                                <th style="padding:8px 10px;">Bill / Due</th>
                                <th style="padding:8px 10px; text-align:center;">Job Status</th>
                                <th style="padding:8px 10px; text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentRepairs)): ?>
                                <tr><td colspan="7" style="text-align:center; padding:25px; color:#94a3b8;">No repair tickets logged yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentRepairs as $rj): 
                                    $st = strtolower($rj['jobStatus'] ?? 'received');
                                    $stBg = ($st === 'completed' || $st === 'delivered' || $st === 'ready for pickup') ? '#ecfdf5' : '#fef3c7';
                                    $stColor = ($st === 'completed' || $st === 'delivered' || $st === 'ready for pickup') ? '#059669' : '#d97706';
                                    $due = max(0, floatval($rj['totalBill'] ?? 0) - floatval($rj['advancePaid'] ?? 0));
                                ?>
                                    <tr style="border-bottom:1px solid #f1f5f9;">
                                        <td style="padding:8px 10px; font-weight:800; font-family:monospace; color:var(--pos-red);">
                                            <?php echo htmlspecialchars($rj['ticketNo'] ?? $rj['id'] ?? 'TK-000'); ?>
                                        </td>
                                        <td style="padding:8px 10px;">
                                            <strong><?php echo htmlspecialchars($rj['customerName'] ?? 'Walk-in'); ?></strong>
                                            <?php if (!empty($rj['customerPhone'])): ?>
                                                <div style="font-size:0.68rem; color:#64748b;"><?php echo htmlspecialchars($rj['customerPhone']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:8px 10px;">
                                            <span style="font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($rj['deviceBrand'] ?? ''); ?> <?php echo htmlspecialchars($rj['deviceModel'] ?? ''); ?></span>
                                        </td>
                                        <td style="padding:8px 10px; color:#dc2626; font-size:0.75rem;">
                                            <?php echo htmlspecialchars($rj['reportedFault'] ?? 'General Issue'); ?>
                                        </td>
                                        <td style="padding:8px 10px;">
                                            <strong>PKR <?php echo number_format($rj['totalBill'] ?? 0); ?></strong>
                                            <?php if ($due > 0): ?>
                                                <div style="font-size:0.68rem; color:#dc2626; font-weight:700;">Due: PKR <?php echo number_format($due); ?></div>
                                            <?php else: ?>
                                                <div style="font-size:0.68rem; color:#059669; font-weight:700;">Paid</div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:8px 10px; text-align:center;">
                                            <span class="status-badge" style="background:<?php echo $stBg; ?>; color:<?php echo $stColor; ?>; font-size:0.7rem; font-weight:800; text-transform:uppercase;">
                                                <?php echo htmlspecialchars($rj['jobStatus'] ?? 'Received'); ?>
                                            </span>
                                        </td>
                                        <td style="padding:8px 10px; text-align:right;">
                                            <a href="mobile-repairs.php" class="pos-btn pos-btn-outline pos-btn-sm" style="padding:3px 7px; font-size:0.7rem; text-decoration:none;">
                                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- =========================================================================
             EASYPAISA & JAZZCASH FINANCIAL SERVICES LIVE SUMMARY WIDGET
             ========================================================================= -->
        <div class="pos-card fin-summary-widget" style="margin-bottom:24px; border-radius:18px; padding:24px; box-shadow:0 4px 25px rgba(16,185,129,0.08);">
            <div class="widget-header" style="display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:18px; border-bottom:1px solid #d1fae5; padding-bottom:16px; flex-wrap:wrap;">
                <div class="widget-header-left" style="display:flex; align-items:center; gap:16px; min-width:0;">
                    <div class="widget-icon" style="width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg, #00a859 0%, #047857 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 8px 20px rgba(0,168,89,0.35); flex-shrink:0;">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                    </div>
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                            <span style="background:#00a859; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:6px; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:4px;">
                                <i class="fa-solid fa-wallet"></i> EASYPAISA
                            </span>
                            <span style="background:#dc2626; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:6px; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:4px;">
                                <i class="fa-solid fa-bolt"></i> JAZZCASH
                            </span>
                        </div>
                        <h3 class="widget-title" style="margin:0; font-size:1.25rem; font-weight:800; color:#065f46; font-family:var(--pos-font-heading); letter-spacing:-0.01em;">
                            Easypaisa &amp; JazzCash Financial Services Summary
                        </h3>
                        <p class="widget-sub" style="margin:3px 0 0 0; font-size:0.82rem; color:#047857; font-weight:500;">
                            Live records of customer payments sent, withdrawals out, and shop owner commission
                        </p>
                    </div>
                </div>
                <div class="widget-header-right" style="flex-shrink:0;">
                    <a href="services.php" class="pos-btn" style="background:linear-gradient(135deg, #00a859 0%, #047857 100%); color:#fff; padding:10px 22px; border-radius:30px; font-weight:800; font-size:0.85rem; box-shadow:0 4px 15px rgba(0,168,89,0.3); text-decoration:none; display:inline-flex; align-items:center; gap:8px; white-space:nowrap;">
                        <i class="fa-solid fa-book-bookmark"></i> Open Financial Ledger (<?php echo $dashTxCount; ?> Records) <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- 1. Top 4 Financial Metric Cards Grid -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:18px;">
                <!-- 1. Send Money (Cash In) -->
                <div class="dash-sub-box box-green" style="background:linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%); border:1.5px solid #a7f3d0; border-left:4px solid #10b981; border-radius:14px; padding:16px 18px; box-shadow:0 2px 10px rgba(16,185,129,0.06); display:flex; flex-direction:column; justify-content:space-between; gap:8px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label text-green" style="background:rgba(16,185,129,0.12); color:#065f46; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-circle-arrow-down" style="color:#10b981;"></i> PAYMENT SEND BY CUSTOMER (CASH IN)
                        </div>
                    </div>
                    <div class="dash-box-val val-green" style="font-size:1.75rem; font-weight:900; color:#059669; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        PKR <?php echo number_format($dashCashIn); ?>
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#6b7280; font-weight:600; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-arrow-right-arrow-left" style="color:#10b981;"></i> <strong><?php echo $dashCountCashIn; ?></strong> Send Transfers Recorded
                    </div>
                </div>

                <!-- 2. Cash Out (Withdrawals) -->
                <div class="dash-sub-box box-red" style="background:linear-gradient(180deg, #ffffff 0%, #fef2f2 100%); border:1.5px solid #fecaca; border-left:4px solid #ef4444; border-radius:14px; padding:16px 18px; box-shadow:0 2px 10px rgba(239,68,68,0.06); display:flex; flex-direction:column; justify-content:space-between; gap:8px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label text-red" style="background:rgba(239,68,68,0.12); color:#991b1b; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-circle-arrow-up" style="color:#ef4444;"></i> PAYMENT OUT TO CUSTOMER (CASH OUT)
                        </div>
                    </div>
                    <div class="dash-box-val val-red" style="font-size:1.75rem; font-weight:900; color:#dc2626; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        PKR <?php echo number_format($dashCashOut); ?>
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#6b7280; font-weight:600; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-hand-holding-dollar" style="color:#ef4444;"></i> <strong><?php echo $dashCountCashOut; ?></strong> Cash Withdrawals Paid
                    </div>
                </div>

                <!-- 3. Shop Owner Commission -->
                <div class="dash-sub-box box-gold" style="background:linear-gradient(145deg, #ffffff 0%, #fffdf5 100%); border:1.5px solid #fde68a; border-left:4px solid var(--pos-gold); border-radius:14px; padding:16px 18px; box-shadow:0 4px 15px rgba(245,158,11,0.1); display:flex; flex-direction:column; justify-content:space-between; gap:8px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label text-gold" style="background:rgba(245,158,11,0.18); color:#92400e; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-sack-dollar" style="color:#f59e0b;"></i> SHOP OWNER COMMISSION (PROFIT)
                        </div>
                    </div>
                    <div class="dash-box-val val-gold" style="font-size:1.85rem; font-weight:900; color:#d97706; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        +PKR <?php echo number_format($dashCommission); ?>
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#92400e; font-weight:700; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-circle-check" style="color:#059669;"></i> Total fee earnings on all transfers
                    </div>
                </div>

                <!-- 4. Net Drawer Cash Flow -->
                <div class="dash-sub-box box-blue" style="background:linear-gradient(180deg, #ffffff 0%, #eff6ff 100%); border:1.5px solid #bfdbfe; border-left:4px solid #3b82f6; border-radius:14px; padding:16px 18px; box-shadow:0 2px 10px rgba(59,130,246,0.06); display:flex; flex-direction:column; justify-content:space-between; gap:8px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label text-blue" style="background:rgba(59,130,246,0.12); color:#1e40af; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-cash-register" style="color:#2563eb;"></i> NET CASH FLOW (DRAWER)
                        </div>
                    </div>
                    <div class="dash-box-val val-blue" style="font-size:1.75rem; font-weight:900; color:<?php echo $dashNetDrawerCashFlow < 0 ? '#dc2626' : '#2563eb'; ?>; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        PKR <?php echo number_format($dashNetDrawerCashFlow); ?>
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#6b7280; font-weight:600; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-vault" style="color:#2563eb;"></i> Net cash in drawer from digital services
                    </div>
                </div>
            </div>

            <!-- 2. Bottom Row: 3 Kiosk Breakdown Cards -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:14px;">
                <!-- 1. Easypaisa Kiosk -->
                <div class="pos-card" style="background:#ffffff; border:1.5px solid #d1fae5; border-radius:12px; padding:14px 16px; margin:0; box-shadow:0 2px 8px rgba(16,185,129,0.04);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="background:#00a859; color:#fff; width:32px; height:32px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:0.95rem; box-shadow:0 2px 6px rgba(0,168,89,0.3);">
                                <i class="fa-solid fa-mobile-retro"></i>
                            </span>
                            <div>
                                <strong style="font-size:0.92rem; color:#065f46; display:block;">Easypaisa Kiosk</strong>
                                <div style="font-size:0.7rem; color:#6b7280; font-weight:600;">03339688007</div>
                            </div>
                        </div>
                        <span style="background:#ecfdf5; color:#059669; font-weight:800; font-size:0.68rem; padding:2px 8px; border-radius:12px; border:1px solid #a7f3d0; letter-spacing:0.5px;">ACTIVE</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:flex-end; border-top:1px dashed #e2e8f0; padding-top:8px;">
                        <div>
                            <div style="font-size:0.7rem; color:#64748b; font-weight:600;">Volume:</div>
                            <div style="font-weight:900; font-size:1.05rem; color:#0f172a;">PKR <?php echo number_format($dashEasypaisaVolume); ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.7rem; color:#64748b; font-weight:600;">Commission:</div>
                            <div style="font-weight:900; font-size:1.05rem; color:#059669;">+PKR <?php echo number_format($dashEasypaisaCommission); ?></div>
                        </div>
                    </div>
                </div>

                <!-- 2. JazzCash Kiosk -->
                <div class="pos-card" style="background:#ffffff; border:1.5px solid #fee2e2; border-radius:12px; padding:14px 16px; margin:0; box-shadow:0 2px 8px rgba(239,68,68,0.04);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="background:#ef4444; color:#fff; width:32px; height:32px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:0.95rem; box-shadow:0 2px 6px rgba(239,68,68,0.3);">
                                <i class="fa-solid fa-wallet"></i>
                            </span>
                            <div>
                                <strong style="font-size:0.92rem; color:#991b1b; display:block;">JazzCash Kiosk</strong>
                                <div style="font-size:0.7rem; color:#6b7280; font-weight:600;">03339688007</div>
                            </div>
                        </div>
                        <span style="background:#fef2f2; color:#dc2626; font-weight:800; font-size:0.68rem; padding:2px 8px; border-radius:12px; border:1px solid #fca5a5; letter-spacing:0.5px;">ACTIVE</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:flex-end; border-top:1px dashed #e2e8f0; padding-top:8px;">
                        <div>
                            <div style="font-size:0.7rem; color:#64748b; font-weight:600;">Volume:</div>
                            <div style="font-weight:900; font-size:1.05rem; color:#0f172a;">PKR <?php echo number_format($dashJazzcashVolume); ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.7rem; color:#64748b; font-weight:600;">Commission:</div>
                            <div style="font-weight:900; font-size:1.05rem; color:#059669;">+PKR <?php echo number_format($dashJazzcashCommission); ?></div>
                        </div>
                    </div>
                </div>

                <!-- 3. Bills & Mobile Load -->
                <div class="pos-card" style="background:#ffffff; border:1.5px solid #fef3c7; border-radius:12px; padding:14px 16px; margin:0; box-shadow:0 2px 8px rgba(245,158,11,0.04);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="background:#f59e0b; color:#fff; width:32px; height:32px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:0.95rem; box-shadow:0 2px 6px rgba(245,158,11,0.3);">
                                <i class="fa-solid fa-file-invoice-dollar"></i>
                            </span>
                            <div>
                                <strong style="font-size:0.92rem; color:#92400e; display:block;">Bills &amp; Mobile Load</strong>
                                <div style="font-size:0.7rem; color:#6b7280; font-weight:600;">PESCO / SNGPL / Jazz / Zong</div>
                            </div>
                        </div>
                        <span style="background:#fffbeb; color:#d97706; font-weight:800; font-size:0.68rem; padding:2px 8px; border-radius:12px; border:1px solid #fde68a; letter-spacing:0.5px;">ACTIVE</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:flex-end; border-top:1px dashed #e2e8f0; padding-top:8px;">
                        <div>
                            <div style="font-size:0.7rem; color:#64748b; font-weight:600;">Amount:</div>
                            <div style="font-weight:900; font-size:1.05rem; color:#0f172a;">PKR <?php echo number_format($dashBillPayments + $dashEasyloads + ($dashBillsVolume ?? 0)); ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.7rem; color:#64748b; font-weight:600;">Commission:</div>
                            <div style="font-weight:900; font-size:1.05rem; color:#059669;">+PKR <?php echo number_format($dashOtherCommission + ($dashBillsEarnings ?? 0)); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =========================================================================
             NADRA & CITIZEN FACILITATION KIOSK LIVE SUMMARY WIDGET
             ========================================================================= -->
        <div class="pos-card nadra-summary-widget" style="margin-bottom:24px; border-radius:18px; padding:24px; box-shadow:0 4px 25px rgba(59,130,246,0.08);">
            <div class="widget-header" style="display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:18px; border-bottom:1px solid #bfdbfe; padding-bottom:16px; flex-wrap:wrap;">
                <div class="widget-header-left" style="display:flex; align-items:center; gap:16px; min-width:0;">
                    <div class="widget-icon" style="width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 8px 20px rgba(37,99,235,0.35); flex-shrink:0;">
                        <i class="fa-solid fa-id-card-clip"></i>
                    </div>
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                            <span style="background:#2563eb; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:6px; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:4px;">
                                <i class="fa-solid fa-id-card"></i> NADRA CNIC
                            </span>
                            <span style="background:#059669; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:6px; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:4px;">
                                <i class="fa-solid fa-shield"></i> POLICE CLEARANCE
                            </span>
                            <span style="background:#7c3aed; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:6px; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:4px;">
                                <i class="fa-solid fa-award"></i> HANGU DOMICILE
                            </span>
                        </div>
                        <h3 class="widget-title" style="margin:0; font-size:1.25rem; font-weight:800; color:#1e40af; font-family:var(--pos-font-heading); letter-spacing:-0.01em;">
                            NADRA &amp; Citizen Facilitation Kiosk Summary
                        </h3>
                        <p class="widget-sub" style="margin:3px 0 0 0; font-size:0.82rem; color:#1d4ed8; font-weight:500;">
                            Live status of CNIC renewals, FRC, Police character certificates &amp; Domicile applications
                        </p>
                    </div>
                </div>
                <div class="widget-header-right" style="flex-shrink:0;">
                    <a href="nadra-kiosk.php" class="pos-btn" style="background:linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color:#fff; padding:10px 22px; border-radius:30px; font-weight:800; font-size:0.85rem; box-shadow:0 4px 15px rgba(37,99,235,0.3); text-decoration:none; display:inline-flex; align-items:center; gap:8px; white-space:nowrap;">
                        <i class="fa-solid fa-passport"></i> Open Kiosk Desk (<?php echo $dashNadraCount; ?> Records) <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- 3 Metric Cards Grid -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:16px;">
                <div class="dash-sub-box" style="background:#ffffff; border:1.5px solid #bfdbfe; border-radius:14px; padding:18px 20px; box-shadow:0 2px 10px rgba(37,99,235,0.06); display:flex; flex-direction:column; justify-content:space-between; gap:8px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label" style="background:rgba(37,99,235,0.12); color:#1e40af; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-hourglass-half" style="color:#2563eb;"></i> IN PROCESS APPLICATIONS
                        </div>
                    </div>
                    <div class="dash-box-val" style="font-size:1.75rem; font-weight:900; color:#2563eb; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        <?php echo $dashNadraPending; ?> Applications
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#6b7280; font-weight:600; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-spinner fa-spin" style="color:#2563eb;"></i> Active In-Progress Processing
                    </div>
                </div>

                <div class="dash-sub-box box-green" style="background:#ffffff; border:1.5px solid #a7f3d0; border-radius:14px; padding:18px 20px; box-shadow:0 2px 10px rgba(16,185,129,0.06); display:flex; flex-direction:column; justify-content:space-between; gap:8px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label text-green" style="background:rgba(16,185,129,0.12); color:#065f46; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-certificate" style="color:#10b981;"></i> READY FOR PICKUP
                        </div>
                    </div>
                    <div class="dash-box-val val-green" style="font-size:1.75rem; font-weight:900; color:#059669; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        <?php echo $dashNadraReady; ?> Ready
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#6b7280; font-weight:600; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-box-archive" style="color:#10b981;"></i> Documents Ready to Handover
                    </div>
                </div>

                <div class="dash-sub-box box-gold" style="background:linear-gradient(145deg, #fffdf5 0%, #fffbeb 100%); border:2px solid #f59e0b; border-radius:14px; padding:18px 20px; box-shadow:0 4px 15px rgba(245,158,11,0.12); display:flex; flex-direction:column; justify-content:space-between; gap:8px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div class="dash-box-label text-gold" style="background:rgba(245,158,11,0.18); color:#92400e; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-coins" style="color:#f59e0b;"></i> TOTAL KIOSK EARNINGS
                        </div>
                    </div>
                    <div class="dash-box-val val-gold" style="font-size:1.85rem; font-weight:900; color:#d97706; font-family:var(--pos-font-heading); line-height:1.2; margin:4px 0 2px 0;">
                        +PKR <?php echo number_format($dashNadraEarnings); ?>
                    </div>
                    <div class="dash-box-hint" style="font-size:0.75rem; color:#92400e; font-weight:700; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-circle-check" style="color:#059669;"></i> 100% Net Facilitation Fees Profit
                    </div>
                </div>
            </div>
        </div>

        <!-- 2-Column Split: Utility Bills & Mobile Packages -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">
            <!-- Utility Bills Counter Widget -->
            <div class="pos-card bills-summary-widget" style="border-radius:18px; padding:22px; box-shadow:0 4px 20px rgba(245,158,11,0.08);">
                <div class="widget-header" style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:16px; border-bottom:1px solid #fde68a; padding-bottom:14px; flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div class="widget-icon" style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.35rem; box-shadow:0 6px 16px rgba(245,158,11,0.3); flex-shrink:0;">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </div>
                        <div>
                            <div style="display:flex; align-items:center; gap:4px; margin-bottom:3px; flex-wrap:wrap;">
                                <span style="background:#f59e0b; color:#fff; font-size:0.65rem; font-weight:800; padding:1px 6px; border-radius:4px;">PESCO</span>
                                <span style="background:#0284c7; color:#fff; font-size:0.65rem; font-weight:800; padding:1px 6px; border-radius:4px;">SNGPL</span>
                                <span style="background:#059669; color:#fff; font-size:0.65rem; font-weight:800; padding:1px 6px; border-radius:4px;">PTCL</span>
                                <span style="background:#2563eb; color:#fff; font-size:0.65rem; font-weight:800; padding:1px 6px; border-radius:4px;">WATER</span>
                            </div>
                            <h4 class="widget-title" style="margin:0; font-size:1.15rem; font-weight:800; color:#92400e; font-family:var(--pos-font-heading);">
                                Utility Bills Counter
                            </h4>
                        </div>
                    </div>
                    <a href="bills.php" class="pos-btn" style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color:#fff; font-size:0.8rem; font-weight:800; padding:8px 16px; border-radius:24px; box-shadow:0 3px 10px rgba(245,158,11,0.3); text-decoration:none; display:inline-flex; align-items:center; gap:6px; white-space:nowrap;">
                        Bills Counter (<?php echo $dashBillsCount; ?>) <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="dash-sub-box" style="background:#ffffff; border:1.5px solid #fde68a; border-radius:12px; padding:14px 16px;">
                        <div class="dash-box-label text-neutral" style="background:rgba(107,114,128,0.1); color:#4b5563; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:4px; text-transform:uppercase;">
                            <i class="fa-solid fa-receipt"></i> BILLS VOLUME
                        </div>
                        <div class="dash-box-val val-dark" style="font-size:1.35rem; font-weight:900; color:#111827; font-family:var(--pos-font-heading); margin-top:4px;">
                            PKR <?php echo number_format($dashBillsVolume); ?>
                        </div>
                        <div style="font-size:0.72rem; color:#6b7280; margin-top:2px;">Total Collected</div>
                    </div>
                    <div class="dash-sub-box box-green" style="background:#ffffff; border:1.5px solid #a7f3d0; border-radius:12px; padding:14px 16px;">
                        <div class="dash-box-label text-green" style="background:rgba(16,185,129,0.12); color:#065f46; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:4px; text-transform:uppercase;">
                            <i class="fa-solid fa-sack-dollar"></i> COMMISSION PROFIT
                        </div>
                        <div class="dash-box-val val-green" style="font-size:1.35rem; font-weight:900; color:#059669; font-family:var(--pos-font-heading); margin-top:4px;">
                            +PKR <?php echo number_format($dashBillsEarnings); ?>
                        </div>
                        <div style="font-size:0.72rem; color:#059669; font-weight:700; margin-top:2px;">Shop Net Profit</div>
                    </div>
                </div>
            </div>

            <!-- All SIM Packages & Easyload Hub Widget -->
            <div class="pos-card packages-summary-widget" style="border-radius:18px; padding:22px; box-shadow:0 4px 20px rgba(225,29,72,0.08); border-left:4px solid #e11d48;">
                <div class="widget-header" style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:16px; border-bottom:1px solid #fecdd3; padding-bottom:14px; flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div class="widget-icon" style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg, #e11d48 0%, #be123c 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.35rem; box-shadow:0 6px 16px rgba(225,29,72,0.3); flex-shrink:0;">
                            <i class="fa-solid fa-sim-card"></i>
                        </div>
                        <div>
                            <div style="display:flex; align-items:center; gap:4px; margin-bottom:3px; flex-wrap:wrap;">
                                <span style="background:#e11d48; color:#fff; font-size:0.65rem; font-weight:800; padding:1px 6px; border-radius:4px;">JAZZ</span>
                                <span style="background:#16a34a; color:#fff; font-size:0.65rem; font-weight:800; padding:1px 6px; border-radius:4px;">ZONG</span>
                                <span style="background:#0284c7; color:#fff; font-size:0.65rem; font-weight:800; padding:1px 6px; border-radius:4px;">TELENOR</span>
                                <span style="background:#ea580c; color:#fff; font-size:0.65rem; font-weight:800; padding:1px 6px; border-radius:4px;">UFONE</span>
                                <span style="background:#7c3aed; color:#fff; font-size:0.65rem; font-weight:800; padding:1px 6px; border-radius:4px;">ONIC</span>
                            </div>
                            <h4 class="widget-title" style="margin:0; font-size:1.15rem; font-weight:800; color:#9f1239; font-family:var(--pos-font-heading);">
                                All SIM Packages &amp; Load Hub
                            </h4>
                        </div>
                    </div>
                    <a href="packages.php" class="pos-btn" style="background:linear-gradient(135deg, #e11d48 0%, #be123c 100%); color:#fff; font-size:0.8rem; font-weight:800; padding:8px 16px; border-radius:24px; box-shadow:0 3px 10px rgba(225,29,72,0.3); text-decoration:none; display:inline-flex; align-items:center; gap:6px; white-space:nowrap;">
                        Manage Packages (<?php echo count($simPlans); ?> Plans) <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="dash-sub-box" style="background:#ffffff; border:1.5px solid #fecdd3; border-radius:12px; padding:14px 16px;">
                        <div class="dash-box-label text-neutral" style="background:rgba(225,29,72,0.1); color:#9f1239; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:4px; text-transform:uppercase;">
                            <i class="fa-solid fa-layer-group"></i> CATALOG PLANS
                        </div>
                        <div class="dash-box-val val-dark" style="font-size:1.35rem; font-weight:900; color:#111827; font-family:var(--pos-font-heading); margin-top:4px;">
                            <?php echo count($simPlans); ?> Plans
                        </div>
                        <div style="font-size:0.72rem; color:#6b7280; margin-top:2px;"><?php echo $dashPkgCount; ?> Sold to Customers</div>
                    </div>
                    <div class="dash-sub-box box-green" style="background:#ffffff; border:1.5px solid #a7f3d0; border-radius:12px; padding:14px 16px;">
                        <div class="dash-box-label text-green" style="background:rgba(16,185,129,0.12); color:#065f46; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:4px; text-transform:uppercase;">
                            <i class="fa-solid fa-sack-dollar"></i> PACKAGE MARGIN
                        </div>
                        <div class="dash-box-val val-green" style="font-size:1.35rem; font-weight:900; color:#059669; font-family:var(--pos-font-heading); margin-top:4px;">
                            +PKR <?php echo number_format($dashPkgEarnings); ?>
                        </div>
                        <div style="font-size:0.72rem; color:#059669; font-weight:700; margin-top:2px;">Net Commission Earned</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- WEEKLY SALES BAR GRAPH CHART -->
        <div class="pos-card" style="margin-bottom:24px;">
            <div class="pos-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <h3 class="pos-card-title">
                    <i class="fa-solid fa-chart-simple" style="color:var(--pos-red); margin-right:8px;"></i> Weekly Sales Revenue Report (Bar Graph)
                </h3>
                <span class="status-badge status-active">LIVE POS ANALYTICS</span>
            </div>
            <div style="height:280px; position:relative; width:100%; margin-top:16px;">
                <canvas id="weeklySalesChart"></canvas>
            </div>
        </div>

        <!-- MONTHLY SALES & FINANCIAL REPORT PANEL -->
        <div class="pos-card monthly-report-widget">
            <div class="pos-card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-bottom:1px solid rgba(244,196,48,0.25); padding-bottom:14px; margin-bottom:18px;">
                <div>
                    <h3 class="pos-card-title" style="font-size:1.25rem;">
                        <i class="fa-solid fa-calendar-days" style="color:var(--pos-gold); margin-right:8px;"></i> Monthly Sales &amp; Financial Report (<?php echo date('F Y'); ?>)
                    </h3>
                    <p class="report-sub">Detailed breakdown of monthly revenue, profit margins, cost of goods sold, and 12-month analytics</p>
                </div>
                <div style="display:flex; gap:8px;">
                    <a href="reports.php" class="pos-btn pos-btn-primary pos-btn-sm" style="border-radius:20px; font-weight:700; padding:8px 16px;">
                        <i class="fa-solid fa-chart-line"></i> Full P&amp;L Report
                    </a>
                </div>
            </div>

            <!-- Monthly Financial Breakdown Metrics Grid -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:20px;">
                <div class="dash-sub-box">
                    <div class="dash-box-label text-neutral">Gross Monthly Sales</div>
                    <div class="dash-box-val val-dark">PKR <?php echo number_format($monthlyRevenue); ?></div>
                    <div class="dash-box-hint"><?php echo $monthlyCount; ?> Invoices Issued</div>
                </div>

                <div class="dash-sub-box box-red">
                    <div class="dash-box-label text-red">Monthly COGS (Stock Cost)</div>
                    <div class="dash-box-val val-red">-PKR <?php echo number_format($monthlyCogs); ?></div>
                    <div class="dash-box-hint">Wholesale Inventory</div>
                </div>

                <div class="dash-sub-box box-green">
                    <div class="dash-box-label text-green">Gross Monthly Profit</div>
                    <div class="dash-box-val val-green">+PKR <?php echo number_format($monthlyProfit); ?></div>
                    <div class="dash-box-hint" style="color:#059669; font-weight:700;"><?php echo $monthlyRevenue > 0 ? round(($monthlyProfit / $monthlyRevenue) * 100, 1) : 0; ?>% POS Gross Margin</div>
                </div>

                <div class="dash-sub-box box-gold">
                    <div class="dash-box-label text-gold">Monthly Expenses</div>
                    <div class="dash-box-val val-gold">-PKR <?php echo number_format($monthlyExpenses); ?></div>
                    <div class="dash-box-hint">Rent, Bills &amp; Utility</div>
                </div>

                <div class="dash-sub-box <?php echo $monthlyNetProfit >= 0 ? 'box-green' : 'box-red'; ?>">
                    <div class="dash-box-label <?php echo $monthlyNetProfit >= 0 ? 'text-green' : 'text-red'; ?>">Net Monthly Profit</div>
                    <div class="dash-box-val <?php echo $monthlyNetProfit >= 0 ? 'val-green' : 'val-red'; ?>">PKR <?php echo number_format($monthlyNetProfit); ?></div>
                    <div class="dash-box-hint" style="font-weight:700; color:<?php echo $monthlyNetProfit >= 0 ? '#10b981' : '#ef4444'; ?>;"><?php echo $monthlyMarginPct; ?>% Net Margin</div>
                </div>

                <div class="dash-sub-box box-blue">
                    <div class="dash-box-label text-blue">Avg Order Value (AOV)</div>
                    <div class="dash-box-val val-blue">PKR <?php echo number_format($monthlyAOV); ?></div>
                    <div class="dash-box-hint">Per Transaction Avg</div>
                </div>
            </div>

            <!-- Payment Method Breakdown Badges -->
            <div class="dash-sub-box" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:12px 16px; margin-bottom:16px;">
                <span style="font-size:0.78rem; font-weight:800; text-transform:uppercase; margin-right:6px;">
                    <i class="fa-solid fa-wallet" style="color:var(--pos-gold);"></i> Monthly Payment Collection:
                </span>
                <span class="status-badge status-active" style="background:rgba(99,102,241,0.15); color:#818cf8; border:1px solid rgba(99,102,241,0.3); font-weight:700;">Cash: PKR <?php echo number_format($monthlyPaymentMethods['cash']); ?></span>
                <span class="status-badge status-active" style="background:rgba(16,185,129,0.15); color:#34d399; border:1px solid rgba(16,185,129,0.3); font-weight:700;">Easypaisa: PKR <?php echo number_format($monthlyPaymentMethods['easypaisa']); ?></span>
                <span class="status-badge status-active" style="background:rgba(245,158,11,0.15); color:#fbbf24; border:1px solid rgba(245,158,11,0.3); font-weight:700;">JazzCash: PKR <?php echo number_format($monthlyPaymentMethods['jazzcash']); ?></span>
                <span class="status-badge status-active" style="background:rgba(59,130,246,0.15); color:#60a5fa; border:1px solid rgba(59,130,246,0.3); font-weight:700;">Card: PKR <?php echo number_format($monthlyPaymentMethods['card']); ?></span>
            </div>

            <!-- 12-Month Sales Revenue & Profit Comparison Chart -->
            <div style="margin-top:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <h4 style="font-size:0.95rem; font-weight:800; margin:0;">
                        <i class="fa-solid fa-chart-line" style="color:var(--pos-gold); margin-right:6px;"></i> 12-Month Sales &amp; Profit Trend (<?php echo $currentYear; ?>)
                    </h4>
                </div>
                <div style="height:260px; position:relative; width:100%;">
                    <canvas id="monthlyOverviewChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Sales Table -->
        <div class="data-table-wrap">
            <div class="data-table-toolbar">
                <h3 class="pos-card-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--pos-red); margin-right:8px;"></i> Recent POS Transactions</h3>
                <a href="sales.php" class="pos-btn pos-btn-outline pos-btn-sm">View All Invoices</a>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Date &amp; Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentSales)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:30px; color:#9ca3af;">No recent sales recorded yet. Open POS Terminal to start billing!</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><strong style="color:var(--pos-red);"><?php echo htmlspecialchars($sale['invoiceNo']); ?></strong></td>
                                <td><?php echo htmlspecialchars($sale['customerName']); ?></td>
                                <td><strong>PKR <?php echo number_format($sale['total']); ?></strong></td>
                                <td><span class="status-badge status-active"><?php echo strtoupper($sale['paymentMethod']); ?></span></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($sale['createdAt'])); ?></td>
                                <td><span class="status-badge status-active"><?php echo strtoupper($sale['status'] ?? 'COMPLETED'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- JAVASCRIPT CHARTS & MODAL INTERACTIONS                       -->
<!-- ============================================================= -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// Timeframe Switcher for Section Gross Profit Matrix
function switchProfitTimeframe(tf) {
    document.querySelectorAll('.profit-timeframe-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.profit-tab-btn').forEach(btn => {
        btn.style.background = 'transparent';
        btn.style.color = '#065f46';
        btn.classList.remove('active');
    });

    const activeContent = document.getElementById('profitContent_' + tf);
    if (activeContent) activeContent.style.display = 'block';

    const activeBtn = document.getElementById('profitTabBtn' + tf.charAt(0).toUpperCase() + tf.slice(1));
    if (activeBtn) {
        activeBtn.style.background = '#059669';
        activeBtn.style.color = '#ffffff';
        activeBtn.classList.add('active');
    }
}

// 1. Weekly Sales Bar Chart
const ctxWeekly = document.getElementById('weeklySalesChart');
if (ctxWeekly) {
    new Chart(ctxWeekly, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Revenue (PKR)',
                data: <?php echo json_encode($chartRevenues); ?>,
                backgroundColor: 'rgba(220, 38, 38, 0.75)',
                borderColor: 'rgba(220, 38, 38, 1)',
                borderWidth: 1.5,
                borderRadius: 6,
                hoverBackgroundColor: 'rgba(220, 38, 38, 0.95)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' PKR ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(val) {
                            return 'PKR ' + val.toLocaleString();
                        }
                    },
                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
}

// 2. 12-Month Sales vs Profit Multi-Line/Bar Comparison Chart
const ctxMonthly = document.getElementById('monthlyOverviewChart');
if (ctxMonthly) {
    new Chart(ctxMonthly, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($yearMonthLabels); ?>,
            datasets: [
                {
                    label: 'Gross Sales Revenue (PKR)',
                    data: <?php echo json_encode($yearMonthRevenues); ?>,
                    backgroundColor: 'rgba(37, 99, 235, 0.65)',
                    borderColor: '#2563eb',
                    borderWidth: 1,
                    borderRadius: 4
                },
                {
                    label: 'Gross Profit (PKR)',
                    data: <?php echo json_encode($yearMonthProfits); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.75)',
                    borderColor: '#10b981',
                    borderWidth: 1,
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { font: { weight: 'bold', size: 11 } }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' ' + context.dataset.label + ': PKR ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(val) {
                            return 'PKR ' + val.toLocaleString();
                        }
                    },
                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
}

window.allDashboardCctv = <?php echo json_encode($cctv ?? []); ?> || [];

// CCTV Modal Helpers
function openReturnCctvModal(pj) {
    if (typeof pj === 'string') {
        pj = (window.allDashboardCctv || []).find(x => String(x.id) === String(pj) || String(x.projectNo) === String(pj)) || { id: pj };
    }
    if (!pj) return;
    document.getElementById('returnCctvId').value = pj.id;
    document.getElementById('returnCctvProjectNo').innerText = pj.projectNo || 'CCTV-000';
    document.getElementById('returnCctvClientName').innerText = pj.clientName || 'Client';
    document.getElementById('returnCctvPackage').innerText = (pj.cameraBrand || 'CCTV') + ' - ' + (pj.systemPackage || 'Security System');
    document.getElementById('returnCctvTotalBill').innerText = 'PKR ' + parseFloat(pj.totalBill || 0).toLocaleString();
    document.getElementById('returnCctvAdvancePaid').innerText = 'PKR ' + parseFloat(pj.advancePaid || 0).toLocaleString();
    document.getElementById('returnCctvRefundAmount').value = pj.advancePaid || pj.totalBill || 0;
    document.getElementById('returnCctvReason').value = '';
    document.getElementById('dashboardCctvReturnModal').style.display = 'flex';
}

function closeReturnCctvModal() {
    document.getElementById('dashboardCctvReturnModal').style.display = 'none';
}

function submitReturnCctv(e) {
    e.preventDefault();
    const id = document.getElementById('returnCctvId').value;
    const refund = parseFloat(document.getElementById('returnCctvRefundAmount').value) || 0;
    const reason = document.getElementById('returnCctvReason').value.trim();
    const returnEq = document.getElementById('returnCctvEquipment').checked;

    if (!reason) {
        alert('Please enter a return reason');
        return;
    }

    fetch('../backend/cctv.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'return_project',
            id: id,
            refund_amount: refund,
            return_reason: reason,
            return_equipment: returnEq
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            alert(res.message || 'CCTV project return recorded successfully!');
            window.location.reload();
        } else {
            alert(res.message || 'Failed to process return');
        }
    })
    .catch(err => alert('Network error: ' + err.message));
}

function deleteCctvFromDashboard(id, projectNo) {
    const reason = prompt(`Are you sure you want to PERMANENTLY DELETE CCTV Project #${projectNo}?\n\nPlease enter the reason for deletion:`, 'Administrative cleanup');
    if (reason === null) return;
    if (!reason.trim()) {
        alert('Deletion reason is required for audit trail.');
        return;
    }

    fetch('../backend/cctv.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'delete',
            id: id,
            reason: reason
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            alert(res.message || 'CCTV Project deleted successfully.');
            window.location.reload();
        } else {
            alert(res.message || 'Failed to delete CCTV project');
        }
    })
    .catch(err => alert('Network error: ' + err.message));
}

// Section-Wise Profit Timeframe Switcher
function switchProfitTimeframe(tf) {
    document.querySelectorAll('.profit-timeframe-content').forEach(el => {
        el.style.display = 'none';
    });
    const target = document.getElementById('profitContent_' + tf);
    if (target) {
        target.style.display = 'block';
    }

    document.querySelectorAll('.profit-tab-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.style.background = 'transparent';
        btn.style.color = '#065f46';
        btn.style.boxShadow = 'none';
    });

    const activeBtn = document.getElementById('profitTabBtn' + tf.charAt(0).toUpperCase() + tf.slice(1));
    if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.style.background = '#059669';
        activeBtn.style.color = '#ffffff';
        activeBtn.style.boxShadow = '0 2px 6px rgba(5,150,105,0.25)';
    }
}
</script>

<!-- ============================================================= -->
<!-- DASHBOARD CCTV RETURN / CANCELLATION MODAL                   -->
<!-- ============================================================= -->
<div class="pos-modal-overlay" id="dashboardCctvReturnModal" style="display:none; z-index:999999;">
    <div class="pos-modal" style="max-width:480px; padding:22px;">
        <div class="pos-modal-header" style="border-bottom:1.5px solid var(--pos-border); padding-bottom:10px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
            <h3 class="pos-modal-title" style="margin:0; font-size:1.15rem; color:#d97706; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-rotate-left"></i> CCTV Return / Cancellation Log
            </h3>
            <button class="pos-modal-close" onclick="closeReturnCctvModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="dashboardCctvReturnForm" onsubmit="submitReturnCctv(event)">
            <input type="hidden" id="returnCctvId" value="">

            <div style="background:#fffbeb; border:1px solid #fed7aa; border-radius:10px; padding:12px; margin-bottom:14px; font-size:0.85rem;">
                <div style="font-weight:800; color:#92400e; font-family:monospace;" id="returnCctvProjectNo">CCTV-000</div>
                <div style="font-size:0.9rem; font-weight:700; color:#78350f;" id="returnCctvClientName">Client Name</div>
                <div style="font-size:0.75rem; color:#b45309; margin-top:4px;" id="returnCctvPackage">Hikvision 4-Channel System</div>
                <div style="display:flex; justify-content:space-between; margin-top:8px; border-top:1px dashed #fed7aa; padding-top:6px; font-weight:800;">
                    <span>Total Bill: <strong id="returnCctvTotalBill" style="color:#059669;">PKR 0</strong></span>
                    <span>Advance: <strong id="returnCctvAdvancePaid" style="color:#d97706;">PKR 0</strong></span>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label" style="font-size:0.75rem; font-weight:800;">Refund Amount to Client (PKR)</label>
                <input type="number" id="returnCctvRefundAmount" class="form-input" min="0" step="1" required style="font-size:0.9rem; font-weight:800;">
                <small style="font-size:0.7rem; color:var(--pos-text-muted);">Enter amount returned/refunded to customer (e.g. advance paid or full amount)</small>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label" style="font-size:0.75rem; font-weight:800;">Reason for Return / Cancellation *</label>
                <textarea id="returnCctvReason" class="form-input" rows="2" required placeholder="e.g. Client cancelled contract, site postponed, hardware returned..." style="font-size:0.85rem; resize:vertical;"></textarea>
            </div>

            <div style="margin-bottom:16px; background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                <label style="display:flex; align-items:center; gap:8px; font-size:0.8rem; font-weight:700; cursor:pointer; margin:0;">
                    <input type="checkbox" id="returnCctvEquipment" checked style="width:16px; height:16px; accent-color:var(--pos-red);">
                    <span>Camera equipment / hardware recovered to stock</span>
                </label>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="closeReturnCctvModal()">Cancel</button>
                <button type="submit" class="pos-btn" style="background:#d97706; color:#fff; font-weight:800;">
                    <i class="fa-solid fa-check"></i> Process Return &amp; Refund
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
