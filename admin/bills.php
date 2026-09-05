<?php
$currentPage = 'bills';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$bills = get_json_file('bills') ?? [];
$billsReturns = get_json_file('bills_returns') ?? [];
$billsDeleted = get_json_file('bills_deleted') ?? [];
$user = get_session_user();
$isSuperAdmin = ($user['role'] ?? '') === 'super_admin';

$message = '';
$messageType = '';

// Handle POST actions: Add, Return, Delete
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. DELETE BILL
    if ($action === 'delete_bill') {
        $delId = $_POST['bill_id'] ?? '';
        $delReason = trim($_POST['deleteReason'] ?? 'Administrative cleanup / mistake entry');

        if ($delId) {
            $targetBill = null;
            foreach ($bills as $b) {
                if (($b['id'] ?? '') === $delId) {
                    $targetBill = $b;
                    break;
                }
            }

            $bills = array_values(array_filter($bills, function($b) use ($delId) {
                return ($b['id'] ?? '') !== $delId;
            }));
            save_json_file('bills', $bills);

            if ($targetBill) {
                $targetBill['deletedBy'] = $user['name'] ?? $user['username'] ?? 'admin';
                $targetBill['deletedAt'] = date('c');
                $targetBill['deletionReason'] = $delReason;
                $billsDeleted[] = $targetBill;
                save_json_file('bills_deleted', $billsDeleted);

                if (class_exists('SecurityLogger')) {
                    SecurityLogger::logEvent($user['username'] ?? 'admin', 'super_admin', 'UTILITY_BILL_DELETED', "Deleted bill {$targetBill['consumerNo']} - Reason: {$delReason}");
                }
            }

            $message = 'Utility bill record permanently deleted & archived to audit trail.';
            $messageType = 'success';
        }
    } 
    // 2. RETURN / REJECT BILL
    elseif ($action === 'return_bill') {
        $retId = $_POST['bill_id'] ?? '';
        $refundAmount = floatval($_POST['refundAmount'] ?? 0);
        $returnReason = trim($_POST['returnReason'] ?? 'Customer refund / transaction rejected');
        $returnType = trim($_POST['returnType'] ?? 'rejected');

        if ($retId) {
            $targetBill = null;
            foreach ($bills as &$b) {
                if (($b['id'] ?? '') === $retId) {
                    $b['paymentStatus'] = 'returned';
                    $b['status'] = 'returned';
                    $b['returnedAt'] = date('c');
                    $b['returnedBy'] = $user['name'] ?? $user['username'] ?? 'admin';
                    $b['returnReason'] = $returnReason;
                    $b['returnType'] = $returnType;
                    $b['refundAmount'] = $refundAmount;
                    $targetBill = $b;
                    break;
                }
            }
            save_json_file('bills', $bills);

            if ($targetBill) {
                $billsReturns[] = [
                    'id' => 'bret-' . time() . '-' . rand(100, 999),
                    'billId' => $targetBill['id'],
                    'consumerNo' => $targetBill['consumerNo'] ?? '',
                    'companyName' => $targetBill['companyName'] ?? '',
                    'customerName' => $targetBill['customerName'] ?? '',
                    'customerPhone' => $targetBill['customerPhone'] ?? '',
                    'billingMonth' => $targetBill['billingMonth'] ?? '',
                    'billAmount' => $targetBill['billAmount'] ?? 0,
                    'shopCharges' => $targetBill['shopCharges'] ?? ($targetBill['shopFee'] ?? 0),
                    'manualProfit' => $targetBill['manualProfit'] ?? 0,
                    'additionalCharges' => $targetBill['additionalCharges'] ?? 0,
                    'shopFee' => $targetBill['shopFee'] ?? 0,
                    'totalCollected' => $targetBill['totalCollected'] ?? 0,
                    'refundAmount' => $refundAmount,
                    'returnReason' => $returnReason,
                    'returnType' => $returnType,
                    'paymentChannel' => $targetBill['paymentChannel'] ?? 'Cash Payment',
                    'channelTrxId' => $targetBill['channelTrxId'] ?? '',
                    'returnedBy' => $user['name'] ?? $user['username'] ?? 'admin',
                    'returnedAt' => date('c')
                ];
                save_json_file('bills_returns', $billsReturns);

                if (class_exists('SecurityLogger')) {
                    SecurityLogger::logEvent($user['username'] ?? 'admin', 'admin', 'UTILITY_BILL_RETURNED', "Processed return/refund of PKR {$refundAmount} for Consumer {$targetBill['consumerNo']} ({$returnReason})");
                }
            }

            $message = "Bill for Consumer {$targetBill['consumerNo']} marked as Returned / Rejected. Refund PKR " . number_format($refundAmount);
            $messageType = 'success';
        }
    }
    // 3. ADD NEW BILL
    elseif ($action === 'add_bill') {
        $billType = trim($_POST['billType'] ?? 'pesco');
        $consumerNo = trim($_POST['consumerNo'] ?? '');
        $customerName = trim($_POST['customerName'] ?? 'Walk-in Consumer');
        $customerPhone = trim($_POST['customerPhone'] ?? '');
        $billingMonth = trim($_POST['billingMonth'] ?? date('F Y'));
        $billAmount = floatval($_POST['billAmount'] ?? 0);
        $lateFee = floatval($_POST['lateFee'] ?? 0);
        $shopCharges = floatval($_POST['shopCharges'] ?? 50);
        $manualProfit = floatval($_POST['manualProfit'] ?? 0);
        $additionalCharges = floatval($_POST['additionalCharges'] ?? 0);

        $totalShopProfit = $shopCharges + $manualProfit + $additionalCharges;
        if ($totalShopProfit <= 0 && isset($_POST['shopFee'])) {
            $totalShopProfit = floatval($_POST['shopFee']);
        }

        $paymentChannel = trim($_POST['paymentChannel'] ?? 'Cash Payment');
        $channelTrxId = trim($_POST['channelTrxId'] ?? '');
        $dueDate = trim($_POST['dueDate'] ?? date('Y-m-d'));
        $notes = trim($_POST['notes'] ?? '');

        // Friendly company name
        $companyName = 'PESCO (Peshawar Electric Supply)';
        if ($billType === 'sngpl') $companyName = 'SNGPL (Sui Northern Gas)';
        elseif ($billType === 'ptcl') $companyName = 'PTCL Flash Fiber & Landline';
        elseif ($billType === 'water') $companyName = 'Town Committee Water Supply Hangu';
        elseif ($billType === 'solar') $companyName = 'Solar / Tube-well Electricity';

        if ($billAmount <= 0 || empty($consumerNo)) {
            $message = 'Please provide a valid consumer number and bill amount greater than 0.';
            $messageType = 'danger';
        } else {
            if (empty($channelTrxId)) {
                $prefix = strtoupper(substr($billType, 0, 3));
                $channelTrxId = $prefix . '-BILL-' . rand(1000000000, 9999999999);
            }

            $totalCollected = $billAmount + $lateFee + $totalShopProfit;

            $newBill = [
                'id' => 'bill-' . time() . '-' . rand(100, 999),
                'billType' => $billType,
                'companyName' => $companyName,
                'consumerNo' => $consumerNo,
                'customerName' => $customerName,
                'customerPhone' => $customerPhone,
                'billingMonth' => $billingMonth,
                'billAmount' => $billAmount,
                'lateFee' => $lateFee,
                'shopCharges' => $shopCharges,
                'manualProfit' => $manualProfit,
                'additionalCharges' => $additionalCharges,
                'shopFee' => $totalShopProfit,
                'totalCollected' => $totalCollected,
                'paymentChannel' => $paymentChannel,
                'channelTrxId' => $channelTrxId,
                'paymentStatus' => 'paid',
                'dueDate' => $dueDate,
                'paidAt' => date('c'),
                'loggedBy' => $user['username'] ?? 'admin',
                'notes' => $notes
            ];

            array_unshift($bills, $newBill);
            save_json_file('bills', $bills);

            if (class_exists('SecurityLogger')) {
                SecurityLogger::logEvent($user['username'] ?? 'admin', 'admin', 'UTILITY_BILL_PAID', "Recorded {$companyName} bill for {$consumerNo} (PKR {$billAmount}, Profit: PKR {$totalShopProfit})");
            }

            $message = "Bill for Consumer {$consumerNo} stamped successfully! Total Collected: PKR " . number_format($totalCollected) . " | Shop Profit: +PKR " . number_format($totalShopProfit);
            $messageType = 'success';
        }
    }
}

// Calculate Summary Analytics (excluding returned/rejected bills)
$totalBillsCount = 0;
$totalBillVolume = 0;
$totalShopEarnings = 0;
$totalCashCollected = 0;

$pescoVolume = 0;
$pescoCount = 0;
$sngplVolume = 0;
$sngplCount = 0;
$ptclVolume = 0;
$ptclCount = 0;
$waterVolume = 0;
$waterCount = 0;
$totalRefundedAmount = 0;

foreach ($billsReturns as $br) {
    $totalRefundedAmount += floatval($br['refundAmount'] ?? 0);
}

foreach ($bills as $b) {
    $st = strtolower($b['paymentStatus'] ?? $b['status'] ?? 'paid');
    if ($st === 'returned' || $st === 'rejected' || $st === 'refunded' || $st === 'cancelled') {
        continue;
    }

    $amt = floatval($b['billAmount'] ?? 0);
    $fee = floatval($b['shopFee'] ?? 0);
    $tot = floatval($b['totalCollected'] ?? ($amt + $fee));
    $type = $b['billType'] ?? 'pesco';

    $totalBillsCount++;
    $totalBillVolume += $amt;
    $totalShopEarnings += $fee;
    $totalCashCollected += $tot;

    if ($type === 'pesco') {
        $pescoVolume += $amt;
        $pescoCount++;
    } elseif ($type === 'sngpl') {
        $sngplVolume += $amt;
        $sngplCount++;
    } elseif ($type === 'ptcl') {
        $ptclVolume += $amt;
        $ptclCount++;
    } else {
        $waterVolume += $amt;
        $waterCount++;
    }
}
?>

<style>
/* Responsive fix to prevent any horizontal scrollbar */
html, body {
    overflow-x: hidden !important;
    max-width: 100vw;
}
.pos-main, .pos-content {
    overflow-x: hidden !important;
    max-width: 100%;
}
.modal, .modal > div {
    overflow-x: hidden !important;
    box-sizing: border-box;
}
.modal form input, .modal form select, .modal form textarea {
    max-width: 100%;
    box-sizing: border-box;
}
</style>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content">
        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Utility Bills Payment Counter</h1>
                <p class="page-header-sub">Collect and stamp Electricity, Gas, PTCL &amp; Water utility bills with custom manual profit &amp; shop charges</p>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="button" class="pos-btn pos-btn-primary pos-btn-lg" onclick="openNewBillModal()">
                    <i class="fa-solid fa-plus-circle"></i> Pay New Bill
                </button>
                <a href="nadra-kiosk.php" class="pos-btn pos-btn-outline" style="border-color:#2563eb; color:#2563eb; font-weight:800;">
                    <i class="fa-solid fa-id-card"></i> NADRA Kiosk
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="pos-alert pos-alert-<?php echo $messageType; ?>" style="margin-bottom:20px;">
                <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <!-- KPI Metric Cards Grid -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom:24px;">
            <!-- 1. Total Stamped Bills -->
            <div class="stat-card" style="border-left:4px solid #f59e0b;">
                <div class="stat-icon gold"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Total Bills Paid</div>
                    <div class="stat-value"><?php echo $totalBillsCount; ?> Bills</div>
                    <div style="font-size:0.75rem; color:#d97706; margin-top:2px; font-weight:700;">Active Stamped Receipts</div>
                </div>
            </div>

            <!-- 2. Total Principal Volume -->
            <div class="stat-card" style="border-left:4px solid #2563eb;">
                <div class="stat-icon blue"><i class="fa-solid fa-receipt"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Bills Volume (PKR)</div>
                    <div class="stat-value" style="color:#2563eb;">PKR <?php echo number_format($totalBillVolume); ?></div>
                    <div style="font-size:0.75rem; color:#6b7280; margin-top:2px;">Paid to Utility Companies</div>
                </div>
            </div>

            <!-- 3. Total Shop Earnings -->
            <div class="stat-card" style="border-left:4px solid #10b981;">
                <div class="stat-icon green"><i class="fa-solid fa-sack-dollar"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Shop Gross Profit</div>
                    <div class="stat-value" style="color:#059669;">+PKR <?php echo number_format($totalShopEarnings); ?></div>
                    <div style="font-size:0.75rem; color:#059669; font-weight:700; margin-top:2px;">Shop Charges + Manual Profit</div>
                </div>
            </div>

            <!-- 4. Total Cash Collected -->
            <div class="stat-card" style="border-left:4px solid #dc2626;">
                <div class="stat-icon red"><i class="fa-solid fa-vault"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Total Cash Collected</div>
                    <div class="stat-value" style="color:#dc2626;">PKR <?php echo number_format($totalCashCollected); ?></div>
                    <div style="font-size:0.75rem; color:#6b7280; margin-top:2px;">Principal + Shop Profit</div>
                </div>
            </div>
        </div>

        <!-- 3-Tab Navigation Switcher -->
        <div style="display:flex; gap:10px; margin-bottom:20px; border-bottom:2px solid var(--pos-border); padding-bottom:12px; flex-wrap:wrap;">
            <button type="button" id="tabBtnBillsActive" class="pos-btn pos-btn-primary" onclick="switchBillsTab('active')" style="border-radius:24px; padding:8px 20px; font-weight:800; display:inline-flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-file-invoice"></i> 1. Stamped Utility Bills (<?php echo $totalBillsCount; ?>)
            </button>
            <button type="button" id="tabBtnBillsReturns" class="pos-btn pos-btn-outline" onclick="switchBillsTab('returns')" style="border-radius:24px; padding:8px 20px; font-weight:800; display:inline-flex; align-items:center; gap:8px; border-color:#d97706; color:#d97706;">
                <i class="fa-solid fa-rotate-left"></i> 2. Returns &amp; Rejected Log (<?php echo count($billsReturns); ?>)
            </button>
            <button type="button" id="tabBtnBillsDeleted" class="pos-btn pos-btn-outline" onclick="switchBillsTab('deleted')" style="border-radius:24px; padding:8px 20px; font-weight:800; display:inline-flex; align-items:center; gap:8px; border-color:#dc2626; color:#dc2626;">
                <i class="fa-solid fa-trash-can"></i> 3. Deleted Bills Archive (<?php echo count($billsDeleted); ?>)
            </button>
        </div>

        <!-- =========================================================================
             TAB 1: ACTIVE STAMPED BILLS TABLE
             ========================================================================= -->
        <div id="tabContentBillsActive">
            <!-- Breakdown Quick Cards by Utility Company -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap:14px; margin-bottom:20px;">
                <div class="dash-sub-box" style="background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:14px 16px;">
                    <div style="font-size:0.75rem; font-weight:800; color:#92400e; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-bolt" style="color:#f59e0b;"></i> PESCO ELECTRIC
                    </div>
                    <div style="font-size:1.35rem; font-weight:900; color:#78350f; margin:4px 0 2px 0;">PKR <?php echo number_format($pescoVolume); ?></div>
                    <div style="font-size:0.72rem; color:#92400e; font-weight:700;"><?php echo $pescoCount; ?> Bills Paid</div>
                </div>

                <div class="dash-sub-box" style="background:#fef2f2; border:1px solid #fecaca; border-radius:12px; padding:14px 16px;">
                    <div style="font-size:0.75rem; font-weight:800; color:#991b1b; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-fire-flame-simple" style="color:#dc2626;"></i> SNGPL GAS
                    </div>
                    <div style="font-size:1.35rem; font-weight:900; color:#991b1b; margin:4px 0 2px 0;">PKR <?php echo number_format($sngplVolume); ?></div>
                    <div style="font-size:0.72rem; color:#991b1b; font-weight:700;"><?php echo $sngplCount; ?> Bills Paid</div>
                </div>

                <div class="dash-sub-box" style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:12px; padding:14px 16px;">
                    <div style="font-size:0.75rem; font-weight:800; color:#1e40af; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-wifi" style="color:#2563eb;"></i> PTCL FIBER
                    </div>
                    <div style="font-size:1.35rem; font-weight:900; color:#1e40af; margin:4px 0 2px 0;">PKR <?php echo number_format($ptclVolume); ?></div>
                    <div style="font-size:0.72rem; color:#1e40af; font-weight:700;"><?php echo $ptclCount; ?> Bills Paid</div>
                </div>

                <div class="dash-sub-box" style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:12px; padding:14px 16px;">
                    <div style="font-size:0.75rem; font-weight:800; color:#065f46; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-droplet" style="color:#059669;"></i> WATER SUPPLY
                    </div>
                    <div style="font-size:1.35rem; font-weight:900; color:#065f46; margin:4px 0 2px 0;">PKR <?php echo number_format($waterVolume); ?></div>
                    <div style="font-size:0.72rem; color:#065f46; font-weight:700;"><?php echo $waterCount; ?> Bills Paid</div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="pos-card" style="padding:0; overflow:hidden;">
                <!-- Filter and Search Toolbar -->
                <div class="data-table-toolbar" style="padding:14px 18px; border-bottom:1px solid var(--pos-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                        <button type="button" class="pos-btn pos-btn-sm bill-filter-btn active" style="padding:4px 12px; font-size:0.78rem;" onclick="filterBillsTable('all', this)">
                            All (<?php echo count($bills); ?>)
                        </button>
                        <button type="button" class="pos-btn pos-btn-sm bill-filter-btn" style="color:#d97706; padding:4px 10px; font-size:0.78rem;" onclick="filterBillsTable('pesco', this)">
                            <i class="fa-solid fa-bolt"></i> PESCO Electric (<?php echo $pescoCount; ?>)
                        </button>
                        <button type="button" class="pos-btn pos-btn-sm bill-filter-btn" style="color:#dc2626; padding:4px 10px; font-size:0.78rem;" onclick="filterBillsTable('sngpl', this)">
                            <i class="fa-solid fa-fire-flame-simple"></i> SNGPL Gas (<?php echo $sngplCount; ?>)
                        </button>
                        <button type="button" class="pos-btn pos-btn-sm bill-filter-btn" style="color:#2563eb; padding:4px 10px; font-size:0.78rem;" onclick="filterBillsTable('ptcl', this)">
                            <i class="fa-solid fa-wifi"></i> PTCL Fiber (<?php echo $ptclCount; ?>)
                        </button>
                        <button type="button" class="pos-btn pos-btn-sm bill-filter-btn" style="color:#059669; padding:4px 10px; font-size:0.78rem;" onclick="filterBillsTable('water', this)">
                            <i class="fa-solid fa-droplet"></i> Water Supply (<?php echo $waterCount; ?>)
                        </button>
                    </div>

                    <div style="display:flex; align-items:center; gap:8px;">
                        <div class="data-table-search" style="min-width:200px; padding:6px 10px;">
                            <i class="fa-solid fa-magnifying-glass" style="font-size:0.8rem;"></i>
                            <input type="text" id="billSearchInput" placeholder="Search Consumer No, Name, Phone..." oninput="searchBillsTable()" style="font-size:0.82rem;">
                        </div>
                    </div>
                </div>

                <!-- Table Wrapper -->
                <div class="data-table-wrap" style="border:none; margin:0; width:100%; max-width:100%; overflow-x:auto; box-sizing:border-box;">
                    <table class="data-table" id="billsTable" style="margin:0; width:100%; min-width:100%; table-layout:fixed; border-collapse:collapse;">
                        <thead>
                            <tr style="font-size:0.75rem;">
                                <th style="width:16%; padding:10px 10px;">Consumer No &amp; Month</th>
                                <th style="width:14%; padding:10px 8px;">Company / Type</th>
                                <th style="width:16%; padding:10px 8px;">Customer / Consumer</th>
                                <th style="width:14%; padding:10px 8px;">Channel &amp; TRX ID</th>
                                <th style="width:13%; padding:10px 8px;">Bill Amount</th>
                                <th style="width:13%; padding:10px 8px;">Shop Profit &amp; Charges</th>
                                <th style="width:14%; padding:10px 8px; text-align:right;">Actions (Return / Del)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bills)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:35px; color:var(--pos-text-sec);">
                                        <i class="fa-solid fa-file-invoice-dollar" style="font-size:2rem; color:#cbd5e1; margin-bottom:8px; display:block;"></i>
                                        No utility bills recorded yet. Click <strong>"Pay New Bill"</strong> above to record bill payments.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bills as $b): 
                                    $type = $b['billType'] ?? 'pesco';
                                    $amt = floatval($b['billAmount'] ?? 0);
                                    $sC = floatval($b['shopCharges'] ?? ($b['shopFee'] ?? 0));
                                    $mP = floatval($b['manualProfit'] ?? 0);
                                    $aC = floatval($b['additionalCharges'] ?? 0);
                                    $fee = floatval($b['shopFee'] ?? ($sC + $mP + $aC));
                                    $tot = floatval($b['totalCollected'] ?? ($amt + $fee));
                                    $st = strtolower($b['paymentStatus'] ?? $b['status'] ?? 'paid');
                                    $isRet = ($st === 'returned' || $st === 'rejected' || $st === 'refunded' || $st === 'cancelled');

                                    if ($type === 'pesco') {
                                        $compBadge = '<span class="status-badge" style="background:#fffbeb; color:#92400e; border:1px solid #fde68a; padding:2px 6px; font-size:0.68rem;"><i class="fa-solid fa-bolt"></i> PESCO Electric</span>';
                                    } elseif ($type === 'sngpl') {
                                        $compBadge = '<span class="status-badge" style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:2px 6px; font-size:0.68rem;"><i class="fa-solid fa-fire-flame-simple"></i> SNGPL Gas</span>';
                                    } elseif ($type === 'ptcl') {
                                        $compBadge = '<span class="status-badge" style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; padding:2px 6px; font-size:0.68rem;"><i class="fa-solid fa-wifi"></i> PTCL Fiber</span>';
                                    } else {
                                        $compBadge = '<span class="status-badge" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:2px 6px; font-size:0.68rem;"><i class="fa-solid fa-droplet"></i> Water Supply</span>';
                                    }
                                ?>
                                    <tr data-type="<?php echo htmlspecialchars($type); ?>" style="font-size:0.82rem; <?php echo $isRet ? 'background:#fff5f5; opacity:0.88;' : ''; ?>">
                                        <td style="padding:8px 10px; word-break:break-all;">
                                            <div style="font-weight:800; font-family:monospace; color:var(--pos-text); font-size:0.82rem;"><?php echo htmlspecialchars($b['consumerNo'] ?? 'N/A'); ?></div>
                                            <div style="font-size:0.68rem; color:var(--pos-text-muted);"><?php echo htmlspecialchars($b['billingMonth'] ?? 'Current'); ?> • <?php echo date('M d, h:i A', strtotime($b['paidAt'])); ?></div>
                                            <?php if ($isRet): ?>
                                                <div style="font-size:0.62rem; color:#dc2626; font-weight:800; text-transform:uppercase;">
                                                    <i class="fa-solid fa-rotate-left"></i> <?php echo htmlspecialchars($b['returnReason'] ?? 'RETURNED / REJECTED'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:8px 8px;"><?php echo $compBadge; ?></td>
                                        <td style="padding:8px 8px; word-break:break-word;">
                                            <strong style="color:var(--pos-text); font-size:0.85rem;"><?php echo htmlspecialchars($b['customerName'] ?? 'Walk-in'); ?></strong>
                                            <?php if (!empty($b['customerPhone'])): ?>
                                                <div style="font-size:0.68rem; color:var(--pos-text-muted);"><?php echo htmlspecialchars($b['customerPhone']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:8px 8px; word-break:break-all;">
                                            <div style="font-family:monospace; font-size:0.75rem; font-weight:700; color:#334155;"><?php echo htmlspecialchars($b['channelTrxId'] ?? 'N/A'); ?></div>
                                            <div style="font-size:0.68rem; color:var(--pos-text-muted); font-weight:700;"><?php echo htmlspecialchars($b['paymentChannel'] ?? 'Cash Payment'); ?></div>
                                        </td>
                                        <td style="padding:8px 8px;">
                                            <strong style="font-size:0.92rem; color:#111827;">PKR <?php echo number_format($amt); ?></strong>
                                            <?php if ($isRet): ?>
                                                <div style="font-size:0.68rem; color:#dc2626; font-weight:800;"><i class="fa-solid fa-ban"></i> REFUNDED / REJECTED</div>
                                            <?php else: ?>
                                                <div style="font-size:0.68rem; color:#059669; font-weight:700;"><i class="fa-solid fa-circle-check"></i> STAMPED &amp; PAID</div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:8px 8px;">
                                            <?php if ($isRet): ?>
                                                <span style="color:#94a3b8; font-size:0.75rem;">—</span>
                                            <?php else: ?>
                                                <strong style="color:#059669; font-size:0.88rem; background:#ecfdf5; border:1px solid #a7f3d0; padding:2px 6px; border-radius:4px; display:inline-block;">
                                                    +PKR <?php echo number_format($fee); ?>
                                                </strong>
                                                <div style="font-size:0.65rem; color:#64748b; margin-top:2px;">
                                                    Shop: <?php echo number_format($sC); ?> | Profit: <?php echo number_format($mP); ?> | Extra: <?php echo number_format($aC); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right; padding:8px 8px; white-space:nowrap;">
                                            <div style="display:inline-flex; gap:4px; align-items:center; justify-content:flex-end;">
                                                <!-- Print Stamp Slip -->
                                                <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" style="padding:3px 6px; font-size:0.72rem;" onclick="printBillSlip('<?php echo htmlspecialchars($b['id'], ENT_QUOTES); ?>')" title="Print Bill Payment Stamp Slip">
                                                    <i class="fa-solid fa-print"></i> Stamp
                                                </button>

                                                <!-- Return / Reject Button -->
                                                <?php if (!$isRet): ?>
                                                    <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" style="color:#d97706; border-color:#fed7aa; padding:3px 6px; font-size:0.72rem;" onclick="openReturnBillModal('<?php echo htmlspecialchars($b['id'], ENT_QUOTES); ?>')" title="Return / Reject / Refund Bill Payment">
                                                        <i class="fa-solid fa-rotate-left"></i> Return
                                                    </button>
                                                <?php endif; ?>

                                                <!-- Super Admin Delete Button -->
                                                <?php if ($isSuperAdmin): ?>
                                                    <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" style="color:#dc2626; border-color:#fecaca; padding:3px 6px; font-size:0.72rem;" onclick="promptDeleteBill('<?php echo $b['id']; ?>', '<?php echo htmlspecialchars($b['consumerNo'] ?? ''); ?>')" title="Delete &amp; Archive Bill Record">
                                                        <i class="fa-solid fa-trash-can"></i> Del
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
             TAB 2: RETURNED / REJECTED BILLS AUDIT LOG
             ========================================================================= -->
        <div id="tabContentBillsReturns" style="display:none;">
            <div class="pos-card" style="padding:14px 18px; margin-bottom:16px; background:#fffbeb; border:1px solid #fed7aa;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div style="font-weight:800; color:#92400e; font-size:0.95rem;">
                        <i class="fa-solid fa-rotate-left" style="color:#d97706;"></i> Returns, Rejected &amp; Refunded Bills Log
                    </div>
                    <div style="font-size:0.82rem; color:#b45309; font-weight:800;">
                        Total Customer Refunds: PKR <?php echo number_format($totalRefundedAmount); ?> (<?php echo count($billsReturns); ?> Records)
                    </div>
                </div>
            </div>

            <div class="data-table-wrap pos-card" style="padding:0; overflow:hidden; border:none;">
                <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#fffbeb; font-size:0.75rem;">
                            <th style="padding:10px 10px;">Consumer #</th>
                            <th style="padding:10px 8px;">Company</th>
                            <th style="padding:10px 8px;">Customer</th>
                            <th style="padding:10px 8px;">Bill Amount</th>
                            <th style="padding:10px 8px;">Refunded Amount</th>
                            <th style="padding:10px 8px;">Return Type</th>
                            <th style="padding:10px 8px;">Return Reason</th>
                            <th style="padding:10px 8px;">Processed By &amp; Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($billsReturns)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:35px; color:var(--pos-text-muted);">No returned or rejected bills logged yet.</td></tr>
                        <?php else: ?>
                            <?php foreach (array_reverse($billsReturns) as $br): ?>
                                <tr style="border-bottom:1px solid #fed7aa; font-size:0.82rem;">
                                    <td style="padding:8px 10px;">
                                        <strong style="color:var(--pos-red); font-family:monospace;"><?php echo htmlspecialchars($br['consumerNo'] ?? ''); ?></strong>
                                        <div style="font-size:0.68rem; color:#64748b;"><?php echo htmlspecialchars($br['billingMonth'] ?? ''); ?></div>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <strong style="color:#0f172a;"><?php echo htmlspecialchars($br['companyName'] ?? ''); ?></strong>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <div><strong><?php echo htmlspecialchars($br['customerName'] ?? 'Walk-in'); ?></strong></div>
                                        <div style="font-size:0.68rem; color:#64748b;"><?php echo htmlspecialchars($br['customerPhone'] ?? ''); ?></div>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <strong>PKR <?php echo number_format(floatval($br['billAmount'] ?? 0)); ?></strong>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <strong style="color:#dc2626; font-size:0.92rem;">PKR <?php echo number_format(floatval($br['refundAmount'] ?? 0)); ?></strong>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <span class="status-badge" style="background:#fee2e2; color:#dc2626; font-weight:800; font-size:0.7rem; text-transform:uppercase;">
                                            <?php echo htmlspecialchars($br['returnType'] ?? 'Rejected'); ?>
                                        </span>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <div style="font-size:0.8rem; color:#78350f; font-weight:600;"><?php echo htmlspecialchars($br['returnReason'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <div style="font-weight:700; font-size:0.78rem;"><?php echo htmlspecialchars($br['returnedBy'] ?? 'Admin'); ?></div>
                                        <div style="font-size:0.68rem; color:#64748b;"><?php echo date('M d, Y h:i A', strtotime($br['returnedAt'] ?? 'now')); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- =========================================================================
             TAB 3: DELETED BILLS AUDIT ARCHIVE
             ========================================================================= -->
        <div id="tabContentBillsDeleted" style="display:none;">
            <div class="pos-card" style="padding:14px 18px; margin-bottom:16px; background:#fef2f2; border:1px solid #fecaca;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div style="font-weight:800; color:#991b1b; font-size:0.95rem;">
                        <i class="fa-solid fa-trash-can" style="color:#dc2626;"></i> Permanent Utility Bills Deletion Audit Trail
                    </div>
                    <div style="font-size:0.82rem; color:#dc2626; font-weight:800;">
                        <?php echo count($billsDeleted); ?> Archived Deleted Records
                    </div>
                </div>
            </div>

            <div class="data-table-wrap pos-card" style="padding:0; overflow:hidden; border:none;">
                <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#fef2f2; font-size:0.75rem;">
                            <th style="padding:10px 10px;">Consumer #</th>
                            <th style="padding:10px 8px;">Company &amp; Customer</th>
                            <th style="padding:10px 8px;">Bill Amount</th>
                            <th style="padding:10px 8px;">Deletion Reason</th>
                            <th style="padding:10px 8px;">Deleted By</th>
                            <th style="padding:10px 8px;">Deleted Date &amp; Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($billsDeleted)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:35px; color:var(--pos-text-muted);">No deleted utility bill records in audit archive.</td></tr>
                        <?php else: ?>
                            <?php foreach (array_reverse($billsDeleted) as $bd): ?>
                                <tr style="border-bottom:1px solid #fecaca; font-size:0.82rem;">
                                    <td style="padding:8px 10px;">
                                        <strong style="color:#dc2626; font-family:monospace;"><?php echo htmlspecialchars($bd['consumerNo'] ?? ''); ?></strong>
                                        <div style="font-size:0.68rem; color:#64748b;"><?php echo htmlspecialchars($bd['billingMonth'] ?? ''); ?></div>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <strong><?php echo htmlspecialchars($bd['companyName'] ?? ''); ?></strong>
                                        <div style="font-size:0.68rem; color:#64748b;"><?php echo htmlspecialchars($bd['customerName'] ?? 'Walk-in'); ?></div>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <strong>PKR <?php echo number_format(floatval($bd['billAmount'] ?? 0)); ?></strong>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <span style="font-weight:700; color:#991b1b; font-size:0.82rem;"><?php echo htmlspecialchars($bd['deletionReason'] ?? 'Mistake / test entry deleted'); ?></span>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <span style="font-weight:800; color:#334155; font-size:0.8rem;"><?php echo htmlspecialchars($bd['deletedBy'] ?? 'Admin'); ?></span>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <span style="font-size:0.75rem; color:#64748b;"><?php echo date('M d, Y h:i A', strtotime($bd['deletedAt'] ?? 'now')); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- =========================================================================
     MODAL 1: RECORD NEW BILL PAYMENT MODAL
     ========================================================================= -->
<div class="modal" id="newBillModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; max-width:100vw; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; padding:12px; box-sizing:border-box; overflow:hidden;">
    <div style="background:#fff; border-radius:14px; max-width:620px; width:100%; max-height:92vh; overflow-y:auto; overflow-x:hidden; padding:22px; box-shadow:0 20px 40px rgba(0,0,0,0.3); position:relative; box-sizing:border-box;">
        <button type="button" onclick="closeNewBillModal()" style="position:absolute; top:16px; right:16px; background:none; border:none; font-size:1.3rem; color:#9ca3af; cursor:pointer;">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div style="margin-bottom:14px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
            <span style="background:var(--pos-gold-dark); color:#fff; font-size:0.72rem; font-weight:800; padding:2px 8px; border-radius:4px; text-transform:uppercase;">
                UTILITY BILLS ENGINE
            </span>
            <h3 style="margin:6px 0 0 0; font-size:1.25rem; color:#0f172a;">Pay &amp; Stamp Utility Bill</h3>
            <p style="margin:2px 0 0 0; font-size:0.78rem; color:#64748b;">Record customer electricity, gas, PTCL or water bill with manual profit, shop charges &amp; extra fees</p>
        </div>

        <form method="POST" action="bills.php" id="billForm" style="margin:0; width:100%; max-width:100%; box-sizing:border-box; overflow-x:hidden;">
            <input type="hidden" name="action" value="add_bill">

            <!-- Company Selection -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-bottom:10px; width:100%; box-sizing:border-box;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-size:0.75rem;">Billing Utility Company *</label>
                    <select name="billType" id="modalBillTypeSelect" class="form-select" required style="font-size:0.85rem;">
                        <option value="pesco">PESCO Electricity (KPK)</option>
                        <option value="sngpl">SNGPL Sui Gas</option>
                        <option value="ptcl">PTCL Flash Fiber / Landline</option>
                        <option value="water">Town Committee Water Supply Hangu</option>
                        <option value="solar">Solar / Tube-Well Electricity</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-size:0.75rem;">Consumer / Reference No. *</label>
                    <input type="text" name="consumerNo" class="form-input" placeholder="e.g. 14261230987654U" required style="font-family:monospace; font-weight:700; font-size:0.85rem;">
                </div>
            </div>

            <!-- Customer Details -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-bottom:10px; width:100%; box-sizing:border-box;">
                <div style="margin:0;">
                    <label class="form-label" style="font-size:0.75rem;">Customer / Consumer Name *</label>
                    <input type="text" name="customerName" class="form-input" placeholder="e.g. Haji Gul Rehman" required style="font-size:0.85rem;">
                </div>
                <div style="margin:0;">
                    <label class="form-label" style="font-size:0.75rem;">Customer Mobile Phone</label>
                    <input type="text" name="customerPhone" class="form-input" placeholder="0333..." style="font-size:0.85rem;">
                </div>
            </div>

            <!-- Billing Month & Due Date -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-bottom:10px; width:100%; box-sizing:border-box;">
                <div style="margin:0;">
                    <label class="form-label" style="font-size:0.75rem;">Billing Month</label>
                    <input type="text" name="billingMonth" class="form-input" value="<?php echo date('F Y'); ?>" style="font-size:0.85rem;">
                </div>
                <div style="margin:0;">
                    <label class="form-label" style="font-size:0.75rem;">Bill Due Date</label>
                    <input type="date" name="dueDate" class="form-input" value="<?php echo date('Y-m-d', strtotime('+5 days')); ?>" style="font-size:0.85rem;">
                </div>
            </div>

            <!-- Bill Financials, Profit & Charges Breakdown -->
            <div style="background:#fffbeb; border:1.5px solid #fde68a; border-radius:10px; padding:14px; margin-bottom:12px; width:100%; box-sizing:border-box;">
                <div style="font-size:0.78rem; font-weight:800; color:#92400e; margin-bottom:8px; text-transform:uppercase;">
                    <i class="fa-solid fa-coins"></i> Bill Principal, Charges &amp; Manual Profit:
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap:8px; width:100%; box-sizing:border-box; margin-bottom:8px;">
                    <div>
                        <label class="form-label" style="font-weight:800; color:#0f172a; font-size:0.72rem; margin-bottom:3px;">Bill Principal *</label>
                        <input type="number" name="billAmount" id="modalBillAmountInput" class="form-input" placeholder="5000" min="1" step="any" required oninput="recalcBillTotal()" style="font-size:1rem; font-weight:900; color:#0f172a;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:800; color:#dc2626; font-size:0.72rem; margin-bottom:3px;">Late Surcharge</label>
                        <input type="number" name="lateFee" id="modalLateFeeInput" class="form-input" placeholder="0" value="0" min="0" step="any" oninput="recalcBillTotal()" style="font-weight:700; font-size:0.9rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:800; color:#059669; font-size:0.72rem; margin-bottom:3px;">Shop Charges *</label>
                        <input type="number" name="shopCharges" id="modalShopChargesInput" class="form-input" placeholder="50" value="50" min="0" step="any" oninput="recalcBillTotal()" style="font-weight:800; color:#059669; border-color:#10b981; font-size:0.9rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:800; color:#d97706; font-size:0.72rem; margin-bottom:3px;">Manual Profit</label>
                        <input type="number" name="manualProfit" id="modalManualProfitInput" class="form-input" placeholder="0" value="0" min="0" step="any" oninput="recalcBillTotal()" style="font-weight:800; color:#d97706; border-color:#f59e0b; font-size:0.9rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:800; color:#2563eb; font-size:0.72rem; margin-bottom:3px;">Additional Charges</label>
                        <input type="number" name="additionalCharges" id="modalAdditionalChargesInput" class="form-input" placeholder="0" value="0" min="0" step="any" oninput="recalcBillTotal()" style="font-weight:800; color:#2563eb; border-color:#93c5fd; font-size:0.9rem;">
                    </div>
                </div>

                <div id="billTotalStrip" style="margin-top:8px; background:#fff; border:1px solid #fde68a; border-radius:8px; padding:8px 12px; font-size:0.82rem; display:flex; justify-content:space-between; align-items:center; width:100%; box-sizing:border-box;">
                    <div>
                        <span style="color:#78350f; font-size:0.72rem; display:block;">Total Cash to Collect:</span>
                        <strong style="color:#065f46; font-size:1.15rem;" id="modalBillTotalDisplay">PKR 0</strong>
                    </div>
                    <div style="text-align:right;">
                        <span style="color:#059669; font-size:0.72rem; display:block;">Total Shop Profit:</span>
                        <strong style="color:#059669; font-size:1.15rem;" id="modalBillProfitDisplay">+PKR 50</strong>
                    </div>
                </div>
            </div>

            <!-- Payment Channel & Channel TRX ID -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-bottom:10px; width:100%; box-sizing:border-box;">
                <div style="margin:0;">
                    <label class="form-label" style="font-size:0.75rem;">Payment Account / Channel *</label>
                    <select name="paymentChannel" class="form-select" style="font-weight:700; font-size:0.85rem;">
                        <option value="Cash Payment">💵 Cash Payment</option>
                        <option value="Paid by NADRA Kiosk">🏛️ Paid by NADRA Kiosk</option>
                    </select>
                </div>
                <div style="margin:0;">
                    <label class="form-label" style="font-size:0.75rem;">Bank / Operator Stamped TRX ID</label>
                    <input type="text" name="channelTrxId" class="form-input" placeholder="Auto-generated if left empty" style="font-size:0.85rem;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:14px; width:100%; box-sizing:border-box;">
                <label class="form-label" style="font-size:0.75rem;">Remarks / Notes</label>
                <input type="text" name="notes" class="form-input" placeholder="e.g. Paid online, customer collected receipt" style="font-size:0.85rem;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px; border-top:1px solid #e2e8f0; padding-top:12px; width:100%; box-sizing:border-box;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="closeNewBillModal()">Cancel</button>
                <button type="submit" class="pos-btn pos-btn-primary pos-btn-lg">
                    <i class="fa-solid fa-check"></i> Stamp &amp; Save Bill Payment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: RETURN / REJECT / REFUND BILL PAYMENT MODAL
     ========================================================================= -->
<div class="modal" id="billReturnModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; max-width:100vw; background:rgba(0,0,0,0.6); z-index:99999; align-items:center; justify-content:center; padding:15px; box-sizing:border-box; overflow-x:hidden;">
    <div style="background:#fff; border-radius:12px; max-width:500px; width:100%; padding:22px; box-shadow:0 20px 40px rgba(0,0,0,0.3); position:relative; box-sizing:border-box;">
        <button type="button" onclick="closeReturnBillModal()" style="position:absolute; top:14px; right:14px; background:none; border:none; font-size:1.3rem; color:#9ca3af; cursor:pointer;">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div style="border-bottom:1.5px solid var(--pos-border); padding-bottom:10px; margin-bottom:14px;">
            <h3 style="margin:0; font-size:1.15rem; color:#d97706; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-rotate-left"></i> Return / Reject Bill Payment &amp; Refund
            </h3>
            <p style="margin:2px 0 0 0; font-size:0.75rem; color:#64748b;">Record rejected utility bill payment or mistake entry with customer refund</p>
        </div>

        <form method="POST" action="bills.php" id="billReturnForm">
            <input type="hidden" name="action" value="return_bill">
            <input type="hidden" name="bill_id" id="returnBillId" value="">

            <div style="background:#fffbeb; border:1px solid #fed7aa; border-radius:10px; padding:12px; margin-bottom:14px; font-size:0.85rem;">
                <div style="font-weight:800; color:#92400e; font-family:monospace;" id="returnBillConsumerNo">CONSUMER-000</div>
                <div style="font-size:0.9rem; font-weight:700; color:#78350f;" id="returnBillCompanyCustomer">PESCO Electric - Haji Gul Rehman</div>
                <div style="font-size:0.75rem; color:#b45309; margin-top:4px;" id="returnBillMonth">Month: August 2026</div>
                <div style="display:flex; justify-content:space-between; margin-top:8px; border-top:1px dashed #fed7aa; padding-top:6px; font-weight:800;">
                    <span>Bill Amount: <strong id="returnBillPrincipalAmount" style="color:#0f172a;">PKR 0</strong></span>
                    <span>Collected: <strong id="returnBillTotalCollected" style="color:#059669;">PKR 0</strong></span>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label" style="font-size:0.75rem; font-weight:800;">Refund Amount to Customer (PKR) *</label>
                <input type="number" name="refundAmount" id="returnBillRefundAmount" class="form-input" min="0" step="1" required style="font-size:0.95rem; font-weight:800; color:#dc2626;">
                <small style="font-size:0.7rem; color:var(--pos-text-muted);">Amount returned/refunded to customer in cash</small>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label" style="font-size:0.75rem; font-weight:800;">Reversal / Action Type</label>
                <select name="returnType" id="returnBillTypeSelect" class="form-select" style="font-size:0.85rem; font-weight:700;">
                    <option value="rejected">Kiosk / Bank Payment Rejected</option>
                    <option value="mistake_entry">Mistake / Wrong Consumer Number Entry</option>
                    <option value="duplicate">Duplicate Entry Cancelled</option>
                    <option value="customer_cancelled">Customer Cancelled &amp; Refunded</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" style="font-size:0.75rem; font-weight:800;">Reason for Return / Rejection *</label>
                <textarea name="returnReason" id="returnBillReason" class="form-input" rows="2" required placeholder="e.g. Kiosk rejected bill after deadline, wrong consumer ID..." style="font-size:0.85rem;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="closeReturnBillModal()">Cancel</button>
                <button type="submit" class="pos-btn" style="background:#d97706; color:#fff; font-weight:800;">
                    <i class="fa-solid fa-check"></i> Process Return &amp; Refund
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: PRINTABLE UTILITY BILL PAYMENT STAMP SLIP
     ========================================================================= -->
<div class="modal" id="billSlipModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; max-width:100vw; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; padding:15px; box-sizing:border-box; overflow-x:hidden;">
    <div style="background:#fff; border-radius:10px; max-width:400px; width:100%; padding:20px; box-shadow:0 20px 40px rgba(0,0,0,0.3); position:relative; box-sizing:border-box;">
        <button type="button" onclick="closeBillSlipModal()" style="position:absolute; top:12px; right:12px; background:none; border:none; font-size:1.2rem; color:#9ca3af; cursor:pointer;">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <!-- Printable Voucher Area -->
        <div id="printableBillSlipArea" style="font-family:monospace; color:#000; font-size:0.85rem; border:1px dashed #94a3b8; padding:16px; border-radius:8px; background:#fff;">
            <div style="text-align:center; border-bottom:1px dashed #000; padding-bottom:10px; margin-bottom:10px;">
                <h3 style="margin:0; font-size:1.15rem; font-weight:900;">SAFDAR MOBILE STORE</h3>
                <div style="font-size:0.75rem; margin-top:2px; font-weight:800;">UTILITY BILLS PAYMENT COUNTER</div>
                <div style="font-size:0.7rem; color:#475569;">Opp. Patt Bazar Eidgah Road near Purdil Masjid, Hangu</div>
                <div style="font-size:0.75rem; font-weight:700; margin-top:2px;">Helpline: 03339688007</div>
            </div>

            <!-- Simulated Official PAID Stamp Box -->
            <div id="slipStampBox" style="border:2px solid #059669; border-radius:6px; padding:6px; margin-bottom:10px; text-align:center; background:#ecfdf5;">
                <div id="slipStampTitle" style="font-size:1.1rem; font-weight:900; color:#059669; letter-spacing:2px;">★ BILL PAID &amp; STAMPED ★</div>
                <div id="slipStampSubtitle" style="font-size:0.7rem; color:#065f46;">VERIFIED AGENT TRANSACTION</div>
            </div>

            <div style="margin-bottom:8px; line-height:1.4;">
                <div><strong>COMPANY:</strong> <span id="slipBillCompany"></span></div>
                <div><strong>CONSUMER NO:</strong> <span id="slipConsumerNo" style="font-weight:900;"></span></div>
                <div><strong>MONTH:</strong> <span id="slipBillMonth"></span></div>
                <div><strong>PAYMENT DATE:</strong> <span id="slipBillDate"></span></div>
                <div><strong>TRX ID:</strong> <span id="slipBillTrxId"></span></div>
            </div>

            <div style="border-top:1px dashed #000; border-bottom:1px dashed #000; padding:8px 0; margin-bottom:8px; line-height:1.4;">
                <div><strong>Consumer Name:</strong> <span id="slipConsumerName"></span></div>
                <div><strong>Contact:</strong> <span id="slipConsumerPhone"></span></div>
                <div><strong>Channel:</strong> <span id="slipChannel"></span></div>
            </div>

            <div style="line-height:1.5; margin-bottom:8px;">
                <div style="display:flex; justify-content:space-between;">
                    <span>Principal Bill Amount:</span>
                    <strong id="slipBillAmount">PKR 0</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Late Surcharge:</span>
                    <span id="slipLateFee">PKR 0</span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Shop Charges:</span>
                    <span id="slipShopCharges">PKR 0</span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Additional / Extra Fee:</span>
                    <span id="slipAdditionalCharges">PKR 0</span>
                </div>
                <div style="display:flex; justify-content:space-between; border-top:1px solid #000; padding-top:4px; font-size:0.95rem; font-weight:900;">
                    <span>Total Amount Paid:</span>
                    <span id="slipBillTotal">PKR 0</span>
                </div>
            </div>

            <div style="text-align:center; font-size:0.7rem; border-top:1px dashed #000; padding-top:6px; color:#475569;">
                Thank you for paying through Safdar Mobile Store!<br>
                Please keep this receipt as proof of payment.
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:16px;">
            <button type="button" class="pos-btn pos-btn-outline pos-btn-block" onclick="closeBillSlipModal()">Close</button>
            <button type="button" class="pos-btn pos-btn-primary pos-btn-block" onclick="printBillSlipVoucher()">
                <i class="fa-solid fa-print"></i> Print Stamped Slip
            </button>
        </div>
    </div>
</div>

<script>
function switchBillsTab(tab) {
    document.getElementById('tabContentBillsActive').style.display = tab === 'active' ? 'block' : 'none';
    document.getElementById('tabContentBillsReturns').style.display = tab === 'returns' ? 'block' : 'none';
    document.getElementById('tabContentBillsDeleted').style.display = tab === 'deleted' ? 'block' : 'none';

    document.getElementById('tabBtnBillsActive').className = tab === 'active' ? 'pos-btn pos-btn-primary' : 'pos-btn pos-btn-outline';
    document.getElementById('tabBtnBillsReturns').className = tab === 'returns' ? 'pos-btn pos-btn-primary' : 'pos-btn pos-btn-outline';
    document.getElementById('tabBtnBillsDeleted').className = tab === 'deleted' ? 'pos-btn pos-btn-primary' : 'pos-btn pos-btn-outline';
}

function openNewBillModal() {
    document.getElementById('newBillModal').style.display = 'flex';
    document.getElementById('modalBillAmountInput').focus();
    recalcBillTotal();
}

function closeNewBillModal() {
    document.getElementById('newBillModal').style.display = 'none';
}

function recalcBillTotal() {
    const amt = parseFloat(document.getElementById('modalBillAmountInput').value) || 0;
    const late = parseFloat(document.getElementById('modalLateFeeInput').value) || 0;
    const shop = parseFloat(document.getElementById('modalShopChargesInput').value) || 0;
    const manual = parseFloat(document.getElementById('modalManualProfitInput').value) || 0;
    const extra = parseFloat(document.getElementById('modalAdditionalChargesInput').value) || 0;

    const totalProfit = shop + manual + extra;
    const totalCollected = amt + late + totalProfit;

    document.getElementById('modalBillTotalDisplay').innerText = 'PKR ' + totalCollected.toLocaleString();
    document.getElementById('modalBillProfitDisplay').innerText = '+PKR ' + totalProfit.toLocaleString();
}

window.allBillsList = <?php echo json_encode($bills); ?> || [];

function getBillItem(b) {
    if (!b) return null;
    if (typeof b === 'object') return b;
    if (typeof b === 'string') {
        return (window.allBillsList || []).find(x => String(x.id) === String(b) || String(x.consumerNo) === String(b)) || null;
    }
    return null;
}

// Return / Reject Modal Handlers
function openReturnBillModal(b) {
    b = getBillItem(b);
    if (!b) return;
    document.getElementById('returnBillId').value = b.id || '';
    document.getElementById('returnBillConsumerNo').textContent = 'CONSUMER #: ' + (b.consumerNo || 'N/A');
    document.getElementById('returnBillCompanyCustomer').textContent = (b.companyName || 'Utility Bill') + ' - ' + (b.customerName || 'Walk-in');
    document.getElementById('returnBillMonth').textContent = 'Billing Month: ' + (b.billingMonth || 'Current');
    
    const amt = parseFloat(b.billAmount) || 0;
    const fee = parseFloat(b.shopFee) || 0;
    const tot = parseFloat(b.totalCollected) || (amt + fee);

    document.getElementById('returnBillPrincipalAmount').textContent = 'PKR ' + amt.toLocaleString();
    document.getElementById('returnBillTotalCollected').textContent = 'PKR ' + tot.toLocaleString();
    document.getElementById('returnBillRefundAmount').value = tot;
    document.getElementById('returnBillReason').value = '';

    document.getElementById('billReturnModal').style.display = 'flex';
}

function closeReturnBillModal() {
    document.getElementById('billReturnModal').style.display = 'none';
}

function promptDeleteBill(id, consumerNo) {
    const reason = prompt(`Are you sure you want to PERMANENTLY DELETE Bill Payment for Consumer #${consumerNo}?\n\nPlease enter the reason for deletion:`, 'Administrative cleanup / mistake entry');
    if (reason === null) return;
    if (!reason.trim()) {
        alert('Deletion reason is required for audit trail.');
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'bills.php';

    const actInp = document.createElement('input');
    actInp.type = 'hidden';
    actInp.name = 'action';
    actInp.value = 'delete_bill';
    form.appendChild(actInp);

    const idInp = document.createElement('input');
    idInp.type = 'hidden';
    idInp.name = 'bill_id';
    idInp.value = id;
    form.appendChild(idInp);

    const reasInp = document.createElement('input');
    reasInp.type = 'hidden';
    reasInp.name = 'deleteReason';
    reasInp.value = reason;
    form.appendChild(reasInp);

    document.body.appendChild(form);
    form.submit();
}

// Table Filtering
function filterBillsTable(filterType, btn) {
    document.querySelectorAll('.bill-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const rows = document.querySelectorAll('#billsTable tbody tr');
    rows.forEach(r => {
        const type = r.getAttribute('data-type') || '';
        if (filterType === 'all' || type === filterType) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}

function searchBillsTable() {
    const query = document.getElementById('billSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#billsTable tbody tr');
    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        r.style.display = text.includes(query) ? '' : 'none';
    });
}

// Bill Slip Modal
function printBillSlip(b) {
    b = getBillItem(b);
    if (!b) return;
    document.getElementById('slipBillCompany').innerText = b.companyName || 'UTILITY BILL';
    document.getElementById('slipConsumerNo').innerText = b.consumerNo || 'N/A';
    document.getElementById('slipBillMonth').innerText = b.billingMonth || 'N/A';
    document.getElementById('slipBillDate').innerText = new Date(b.paidAt).toLocaleString();
    document.getElementById('slipBillTrxId').innerText = b.channelTrxId || 'N/A';
    document.getElementById('slipConsumerName').innerText = b.customerName || 'Walk-in';
    document.getElementById('slipConsumerPhone').innerText = b.customerPhone || 'N/A';
    document.getElementById('slipChannel').innerText = b.paymentChannel || 'Cash Payment';

    const isRet = (b.paymentStatus === 'returned' || b.status === 'returned');
    const stampBox = document.getElementById('slipStampBox');
    const stampTitle = document.getElementById('slipStampTitle');
    const stampSub = document.getElementById('slipStampSubtitle');

    if (isRet) {
        stampBox.style.borderColor = '#dc2626';
        stampBox.style.background = '#fef2f2';
        stampTitle.style.color = '#dc2626';
        stampTitle.innerText = '★ BILL RETURNED / CANCELLED ★';
        stampSub.style.color = '#991b1b';
        stampSub.innerText = 'REFUND PROCESSED: ' + (b.returnReason || 'PAYMENT REJECTED');
    } else {
        stampBox.style.borderColor = '#059669';
        stampBox.style.background = '#ecfdf5';
        stampTitle.style.color = '#059669';
        stampTitle.innerText = '★ BILL PAID & STAMPED ★';
        stampSub.style.color = '#065f46';
        stampSub.innerText = 'VERIFIED AGENT TRANSACTION';
    }

    const amt = parseFloat(b.billAmount) || 0;
    const late = parseFloat(b.lateFee) || 0;
    const sC = parseFloat(b.shopCharges) || (parseFloat(b.shopFee) || 0);
    const mP = parseFloat(b.manualProfit) || 0;
    const aC = parseFloat(b.additionalCharges) || 0;
    const fee = parseFloat(b.shopFee) || (sC + mP + aC);
    const tot = parseFloat(b.totalCollected) || (amt + late + fee);

    document.getElementById('slipBillAmount').innerText = 'PKR ' + amt.toLocaleString();
    document.getElementById('slipLateFee').innerText = 'PKR ' + late.toLocaleString();
    document.getElementById('slipShopCharges').innerText = 'PKR ' + (sC + mP).toLocaleString();
    document.getElementById('slipAdditionalCharges').innerText = 'PKR ' + aC.toLocaleString();
    document.getElementById('slipBillTotal').innerText = 'PKR ' + tot.toLocaleString();

    document.getElementById('billSlipModal').style.display = 'flex';
}

function closeBillSlipModal() {
    document.getElementById('billSlipModal').style.display = 'none';
}

function printBillSlipVoucher() {
    const slipContent = document.getElementById('printableBillSlipArea').innerHTML;
    const win = window.open('', '', 'height=600,width=450');
    win.document.write('<html><head><title>Bill Payment Receipt - Safdar Mobile Store</title>');
    win.document.write('<style>body{font-family:monospace; margin:20px;} strong{font-weight:bold;}</style>');
    win.document.write('</head><body>');
    win.document.write(slipContent);
    win.document.write('</body></html>');
    win.document.close();
    win.focus();
    setTimeout(() => { win.print(); win.close(); }, 250);
}

// Auto-open utility bill slip modal or filter if URL has ?consumer=... or ?bill_id=... or ?search=...
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const consumer = urlParams.get('consumer') || urlParams.get('bill_id') || urlParams.get('id') || urlParams.get('trx');
    const search = urlParams.get('search') || urlParams.get('q');

    if ((search || consumer) && document.getElementById('billSearchInput')) {
        document.getElementById('billSearchInput').value = search || consumer;
        searchBillsTable();
    }

    if (consumer) {
        const billsList = <?php echo json_encode($bills); ?> || [];
        const match = billsList.find(b => 
            b.id === consumer || 
            b.consumerNo === consumer || 
            (b.consumerNo && b.consumerNo.toLowerCase() === consumer.toLowerCase()) ||
            (b.channelTrxId && b.channelTrxId.toLowerCase() === consumer.toLowerCase())
        );
        if (match) {
            printBillSlip(match);
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
