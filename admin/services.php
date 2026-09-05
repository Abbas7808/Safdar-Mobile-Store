<?php
$currentPage = 'services';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$services = get_json_file('services') ?? [];

$message = '';
$messageType = '';

// Handle POST: Add new transaction or Delete transaction
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? 'add_transaction';

    if ($action === 'delete_transaction') {
        $delId = $_POST['tx_id'] ?? '';
        if ($delId) {
            $services = array_values(array_filter($services, function($t) use ($delId) {
                return ($t['id'] ?? '') !== $delId;
            }));
            save_json_file('services', $services);
            $message = 'Financial transaction record deleted successfully.';
            $messageType = 'success';
        }
    } elseif ($action === 'reverse_transaction') {
        $revId = $_POST['tx_id'] ?? '';
        if ($revId) {
            foreach ($services as &$t) {
                if (($t['id'] ?? '') === $revId || ($t['trxId'] ?? '') === $revId) {
                    $t['status'] = 'reversed';
                    $t['reversedAt'] = date('c');
                    $t['reversedBy'] = $user['username'] ?? 'admin';
                    break;
                }
            }
            save_json_file('services', $services);
            $message = 'Service transaction reversed / returned successfully.';
            $messageType = 'warning';
        }
    } elseif ($action === 'add_transaction') {
        $serviceProvider = trim($_POST['serviceProvider'] ?? 'easypaisa');
        $txType = trim($_POST['txType'] ?? 'cash_in'); // cash_in (Send), cash_out (Withdrawal), bill_payment, easyload, bank_transfer
        $amount = floatval($_POST['amount'] ?? 0);
        $commission = floatval($_POST['commission'] ?? 0);
        $customerName = trim($_POST['customerName'] ?? 'Walk-in Customer');
        $customerPhone = trim($_POST['customerPhone'] ?? '');
        $customerCnic = trim($_POST['customerCnic'] ?? '');
        $receiverName = trim($_POST['receiverName'] ?? '');
        $receiverAccount = trim($_POST['receiverAccount'] ?? '');
        $trxId = trim($_POST['trxId'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $status = $_POST['status'] ?? 'completed';

        if ($amount <= 0) {
            $message = 'Please enter a valid transaction amount greater than 0.';
            $messageType = 'danger';
        } else {
            if (empty($trxId)) {
                $prefix = strtoupper(substr($serviceProvider, 0, 2));
                $trxId = $prefix . '-' . rand(1000000000, 9999999999);
            }

            // Net Handled calculation
            $netHandled = ($txType === 'cash_out') ? ($amount - $commission) : ($amount + $commission);

            $newTx = [
                'id' => 'tx-' . time() . '-' . rand(100, 999),
                'trxId' => $trxId,
                'serviceProvider' => $serviceProvider,
                'txType' => $txType,
                'amount' => $amount,
                'commission' => $commission,
                'netHandled' => $netHandled,
                'customerName' => $customerName,
                'customerPhone' => $customerPhone,
                'customerCnic' => $customerCnic,
                'receiverName' => $receiverName ?: ($txType === 'cash_out' ? 'Self (Cash Out)' : 'Beneficiary'),
                'receiverAccount' => $receiverAccount ?: $customerPhone,
                'status' => $status,
                'notes' => $notes,
                'loggedBy' => $user['username'] ?? 'admin',
                'createdAt' => date('c'),
                'timestamp' => date('c')
            ];

            array_unshift($services, $newTx);
            save_json_file('services', $services);

            if (class_exists('SecurityLogger')) {
                SecurityLogger::logEvent($user['username'] ?? 'admin', 'admin', 'FINANCIAL_TX_RECORDED', "Recorded {$txType} of PKR {$amount} via {$serviceProvider} with PKR {$commission} commission");
            }

            $message = "Transaction {$trxId} recorded successfully! Shop Commission: +PKR " . number_format($commission);
            $messageType = 'success';
        }
    }
}

// Calculate Financial Ledger Summary Analytics
$totalCashInSend = 0;   // Payment Send by Customer
$totalCashOut = 0;      // Payment Out to Customer (Withdrawals)
$totalCommission = 0;   // Shop Owner Commission Earned
$totalBillPayments = 0;
$totalEasyloads = 0;

$countCashIn = 0;
$countCashOut = 0;
$countBills = 0;
$countLoads = 0;

$easypaisaVolume = 0;
$easypaisaCommission = 0;
$jazzcashVolume = 0;
$jazzcashCommission = 0;
$otherVolume = 0;
$otherCommission = 0;

foreach ($services as $tx) {
    if (($tx['status'] ?? 'completed') === 'cancelled') continue;

    $amt = floatval($tx['amount'] ?? 0);
    $comm = floatval($tx['commission'] ?? 0);
    $type = $tx['txType'] ?? 'cash_in';
    $provider = strtolower($tx['serviceProvider'] ?? 'easypaisa');

    $totalCommission += $comm;

    if ($type === 'cash_in' || $type === 'send_money') {
        $totalCashInSend += $amt;
        $countCashIn++;
    } elseif ($type === 'cash_out' || $type === 'withdrawal') {
        $totalCashOut += $amt;
        $countCashOut++;
    } elseif ($type === 'bill_payment' || $type === 'bill') {
        $totalBillPayments += $amt;
        $countBills++;
    } elseif ($type === 'easyload' || $type === 'load') {
        $totalEasyloads += $amt;
        $countLoads++;
    } else {
        $totalCashInSend += $amt;
        $countCashIn++;
    }

    if ($provider === 'easypaisa') {
        $easypaisaVolume += $amt;
        $easypaisaCommission += $comm;
    } elseif ($provider === 'jazzcash') {
        $jazzcashVolume += $amt;
        $jazzcashCommission += $comm;
    } else {
        $otherVolume += $amt;
        $otherCommission += $comm;
    }
}

// Net Cash Impact in Drawer: Total Cash-In Collected + Total Commission - Total Cash-Out Paid
$netDrawerCashFlow = ($totalCashInSend + $totalBillPayments + $totalEasyloads + $totalCommission) - $totalCashOut;
?>

<div class="pos-main" style="min-width:0; max-width:calc(100vw - var(--pos-sidebar-w)); overflow-x:hidden; box-sizing:border-box;">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content" style="max-width:100%; width:100%; box-sizing:border-box; overflow-x:hidden; padding:20px 24px;">
        <!-- Page Header -->
        <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
            <div>
                <h1 style="font-size:1.45rem; margin:0;">
                    <i class="fa-solid fa-wallet" style="color:var(--pos-red); margin-right:8px;"></i> Easypaisa & JazzCash Financial Ledger
                </h1>
                <p class="page-header-sub" style="margin-top:2px;">
                    Track payments sent by customers, cash withdrawals out, and shop owner commission earnings
                </p>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="pos-btn pos-btn-primary" onclick="openNewTransactionModal()">
                    <i class="fa-solid fa-circle-plus"></i> New Transaction
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="login-error" style="background:<?php echo $messageType === 'success' ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)'; ?>; border:1px solid <?php echo $messageType === 'success' ? '#a7f3d0' : '#fecaca'; ?>; color:<?php echo $messageType === 'success' ? '#059669' : '#dc2626'; ?>; margin-bottom:18px; padding:12px 16px; border-radius:8px; font-weight:700;">
                <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation'; ?>"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- 1. CORE FINANCIAL LEDGER STATS CARDS -->
        <div class="stats-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:12px; margin-bottom:18px; width:100%; box-sizing:border-box;">
            
            <!-- 1. Payment Send by Customer (Cash-In) -->
            <div class="stat-card" style="border-left:4px solid #10b981; background:linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%); padding:14px;">
                <div class="stat-icon green" style="background:rgba(16,185,129,0.15); color:#059669; width:40px; height:40px; font-size:1.1rem;">
                    <i class="fa-solid fa-arrow-down-long"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label" style="color:#065f46; font-weight:800; font-size:0.72rem;">PAYMENT SEND BY CUSTOMER (CASH IN)</div>
                    <div class="stat-value" style="color:#059669; font-size:1.3rem;">
                        PKR <?php echo number_format($totalCashInSend); ?>
                    </div>
                    <div style="font-size:0.72rem; color:#6b7280; margin-top:2px;">
                        <strong><?php echo $countCashIn; ?></strong> Send Transfers Recorded
                    </div>
                </div>
            </div>

            <!-- 2. Payment Out by Customer (Cash-Out / Withdrawals) -->
            <div class="stat-card" style="border-left:4px solid #ef4444; background:linear-gradient(180deg, #ffffff 0%, #fef2f2 100%); padding:14px;">
                <div class="stat-icon red" style="background:rgba(239,68,68,0.15); color:#dc2626; width:40px; height:40px; font-size:1.1rem;">
                    <i class="fa-solid fa-arrow-up-long"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label" style="color:#991b1b; font-weight:800; font-size:0.72rem;">PAYMENT OUT TO CUSTOMER (CASH OUT)</div>
                    <div class="stat-value" style="color:#dc2626; font-size:1.3rem;">
                        PKR <?php echo number_format($totalCashOut); ?>
                    </div>
                    <div style="font-size:0.72rem; color:#6b7280; margin-top:2px;">
                        <strong><?php echo $countCashOut; ?></strong> Cash Withdrawals Paid
                    </div>
                </div>
            </div>

            <!-- 3. Shop Owner Commission (Profit) -->
            <div class="stat-card" style="border-left:4px solid var(--pos-gold); background:linear-gradient(180deg, #ffffff 0%, #fffdf5 100%); padding:14px;">
                <div class="stat-icon gold" style="background:rgba(244,196,48,0.2); color:#b45309; width:40px; height:40px; font-size:1.1rem;">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label" style="color:#92400e; font-weight:800; font-size:0.72rem;">SHOP OWNER COMMISSION (PROFIT)</div>
                    <div class="stat-value" style="color:#b45309; font-size:1.3rem;">
                        +PKR <?php echo number_format($totalCommission); ?>
                    </div>
                    <div style="font-size:0.72rem; color:#6b7280; margin-top:2px;">
                        Total fee earnings on all transfers
                    </div>
                </div>
            </div>

            <!-- 4. Net Drawer Cash Flow -->
            <div class="stat-card" style="border-left:4px solid #3b82f6; background:linear-gradient(180deg, #ffffff 0%, #eff6ff 100%); padding:14px;">
                <div class="stat-icon blue" style="background:rgba(59,130,246,0.15); color:#2563eb; width:40px; height:40px; font-size:1.1rem;">
                    <i class="fa-solid fa-cash-register"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label" style="color:#1e40af; font-weight:800; font-size:0.72rem;">NET CASH FLOW (DRAWER)</div>
                    <div class="stat-value" style="color:#2563eb; font-size:1.3rem;">
                        PKR <?php echo number_format($netDrawerCashFlow); ?>
                    </div>
                    <div style="font-size:0.72rem; color:#6b7280; margin-top:2px;">
                        Net cash in drawer from digital services
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. SERVICE PROVIDER VOLUME BREAKDOWN -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:12px; margin-bottom:18px; width:100%; box-sizing:border-box;">
            <!-- Easypaisa Card -->
            <div class="pos-card" style="background:#fff; border:1px solid #d1fae5; padding:14px; margin:0;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="background:#00a859; color:#fff; width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.85rem;">
                            <i class="fa-solid fa-mobile-retro"></i>
                        </span>
                        <div>
                            <strong style="font-size:0.92rem; color:#065f46;">Easypaisa Kiosk</strong>
                            <div style="font-size:0.68rem; color:#6b7280;">03339688007</div>
                        </div>
                    </div>
                    <span style="background:#ecfdf5; color:#059669; font-weight:800; font-size:0.7rem; padding:2px 6px; border-radius:10px;">ACTIVE</span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:flex-end; border-top:1px dashed #e2e8f0; padding-top:6px;">
                    <div>
                        <div style="font-size:0.68rem; color:#64748b;">Volume:</div>
                        <div style="font-weight:800; font-size:1rem; color:#0f172a;">PKR <?php echo number_format($easypaisaVolume); ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:0.68rem; color:#64748b;">Commission:</div>
                        <div style="font-weight:900; font-size:1rem; color:#059669;">+PKR <?php echo number_format($easypaisaCommission); ?></div>
                    </div>
                </div>
            </div>

            <!-- JazzCash Card -->
            <div class="pos-card" style="background:#fff; border:1px solid #fee2e2; padding:14px; margin:0;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="background:#ef4444; color:#fff; width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.85rem;">
                            <i class="fa-solid fa-wallet"></i>
                        </span>
                        <div>
                            <strong style="font-size:0.92rem; color:#991b1b;">JazzCash Kiosk</strong>
                            <div style="font-size:0.68rem; color:#6b7280;">03339688007</div>
                        </div>
                    </div>
                    <span style="background:#fef2f2; color:#dc2626; font-weight:800; font-size:0.7rem; padding:2px 6px; border-radius:10px;">ACTIVE</span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:flex-end; border-top:1px dashed #e2e8f0; padding-top:6px;">
                    <div>
                        <div style="font-size:0.68rem; color:#64748b;">Volume:</div>
                        <div style="font-weight:800; font-size:1rem; color:#0f172a;">PKR <?php echo number_format($jazzcashVolume); ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:0.68rem; color:#64748b;">Commission:</div>
                        <div style="font-weight:900; font-size:1rem; color:#059669;">+PKR <?php echo number_format($jazzcashCommission); ?></div>
                    </div>
                </div>
            </div>

            <!-- Utility Bills & Easyload -->
            <div class="pos-card" style="background:#fff; border:1px solid #fef3c7; padding:14px; margin:0;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="background:#f59e0b; color:#fff; width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.85rem;">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </span>
                        <div>
                            <strong style="font-size:0.92rem; color:#92400e;">Bills & Mobile Load</strong>
                            <div style="font-size:0.68rem; color:#6b7280;">PESCO / SNGPL / Jazz / Zong</div>
                        </div>
                    </div>
                    <span style="background:#fffbeb; color:#b45309; font-weight:800; font-size:0.7rem; padding:2px 6px; border-radius:10px;">ACTIVE</span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:flex-end; border-top:1px dashed #e2e8f0; padding-top:6px;">
                    <div>
                        <div style="font-size:0.68rem; color:#64748b;">Amount:</div>
                        <div style="font-weight:800; font-size:1rem; color:#0f172a;">PKR <?php echo number_format($totalBillPayments + $totalEasyloads); ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:0.68rem; color:#64748b;">Commission:</div>
                        <div style="font-weight:900; font-size:1rem; color:#059669;">+PKR <?php echo number_format($otherCommission); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. TRANSACTIONS TABLE SECTION WITH FILTERS -->
        <div class="pos-card" style="padding:0; overflow:hidden; width:100%; max-width:100%; box-sizing:border-box;">
            <!-- Filter Bar & Search -->
            <div style="padding:12px 16px; border-bottom:1px solid var(--pos-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; background:#fafafa; width:100%; box-sizing:border-box;">
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" class="pos-btn pos-btn-sm tx-filter-btn active" onclick="filterTransactionsTable('all', this)" style="padding:4px 10px; font-size:0.78rem;">
                        All (<?php echo count($services); ?>)
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm tx-filter-btn" style="color:#059669; padding:4px 10px; font-size:0.78rem;" onclick="filterTransactionsTable('cash_in', this)">
                        <i class="fa-solid fa-arrow-down-long"></i> Cash In (<?php echo $countCashIn; ?>)
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm tx-filter-btn" style="color:#dc2626; padding:4px 10px; font-size:0.78rem;" onclick="filterTransactionsTable('cash_out', this)">
                        <i class="fa-solid fa-arrow-up-long"></i> Cash Out (<?php echo $countCashOut; ?>)
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm tx-filter-btn" style="color:#0284c7; padding:4px 10px; font-size:0.78rem;" onclick="filterTransactionsTable('bill_payment', this)">
                        <i class="fa-solid fa-receipt"></i> Bills (<?php echo $countBills; ?>)
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm tx-filter-btn" style="color:#d97706; padding:4px 10px; font-size:0.78rem;" onclick="filterTransactionsTable('easyload', this)">
                        <i class="fa-solid fa-mobile-screen-button"></i> Easyload (<?php echo $countLoads; ?>)
                    </button>
                </div>

                <div style="display:flex; align-items:center; gap:8px;">
                    <div class="data-table-search" style="min-width:200px; padding:6px 10px;">
                        <i class="fa-solid fa-magnifying-glass" style="font-size:0.8rem;"></i>
                        <input type="text" id="txSearchInput" placeholder="Search TRX ID, Name, Phone..." oninput="searchTransactionsTable()" style="font-size:0.82rem;">
                    </div>
                </div>
            </div>

            <!-- Table Wrapper -->
            <div class="data-table-wrap" style="border:none; margin:0; width:100%; max-width:100%; overflow-x:auto; box-sizing:border-box;">
                <table class="data-table" id="servicesTable" style="margin:0; width:100%; min-width:100%; table-layout:fixed; border-collapse:collapse;">
                    <thead>
                        <tr style="font-size:0.75rem;">
                            <th style="width:16%; padding:10px 10px;">TRX ID & Date</th>
                            <th style="width:12%; padding:10px 8px;">Provider</th>
                            <th style="width:14%; padding:10px 8px;">Type</th>
                            <th style="width:16%; padding:10px 8px;">Customer (Sender)</th>
                            <th style="width:16%; padding:10px 8px;">Receiver / Account</th>
                            <th style="width:12%; padding:10px 8px;">Principal</th>
                            <th style="width:12%; padding:10px 8px;">Commission</th>
                            <th style="width:12%; padding:10px 8px; text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($services)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:35px; color:var(--pos-text-sec);">
                                    <i class="fa-solid fa-receipt" style="font-size:2rem; color:#cbd5e1; margin-bottom:8px; display:block;"></i>
                                    No financial transactions logged yet. Click <strong>"New Transaction"</strong> above to record entries.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($services as $tx): 
                                $type = $tx['txType'] ?? 'cash_in';
                                $provider = strtolower($tx['serviceProvider'] ?? 'easypaisa');
                                $amt = floatval($tx['amount'] ?? 0);
                                $comm = floatval($tx['commission'] ?? 0);
                                $status = $tx['status'] ?? 'completed';

                                // Color tags
                                if ($type === 'cash_in') {
                                    $typeBadge = '<span class="status-badge" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:2px 6px; font-size:0.68rem;"><i class="fa-solid fa-arrow-down-long"></i> CASH IN</span>';
                                } elseif ($type === 'cash_out') {
                                    $typeBadge = '<span class="status-badge" style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:2px 6px; font-size:0.68rem;"><i class="fa-solid fa-arrow-up-long"></i> CASH OUT</span>';
                                } elseif ($type === 'bill_payment') {
                                    $typeBadge = '<span class="status-badge" style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; padding:2px 6px; font-size:0.68rem;"><i class="fa-solid fa-file-invoice"></i> BILL</span>';
                                } elseif ($type === 'easyload') {
                                    $typeBadge = '<span class="status-badge" style="background:#fffbeb; color:#92400e; border:1px solid #fde68a; padding:2px 6px; font-size:0.68rem;"><i class="fa-solid fa-bolt"></i> LOAD</span>';
                                } else {
                                    $typeBadge = '<span class="status-badge status-active" style="padding:2px 6px; font-size:0.68rem;">' . strtoupper($type) . '</span>';
                                }

                                if ($provider === 'easypaisa') {
                                    $provBadge = '<span style="color:#00a859; font-weight:800; font-size:0.8rem; display:inline-flex; align-items:center; gap:4px;"><i class="fa-solid fa-mobile-retro"></i> Easypaisa</span>';
                                } elseif ($provider === 'jazzcash') {
                                    $provBadge = '<span style="color:#ef4444; font-weight:800; font-size:0.8rem; display:inline-flex; align-items:center; gap:4px;"><i class="fa-solid fa-wallet"></i> JazzCash</span>';
                                } elseif ($provider === 'bank') {
                                    $provBadge = '<span style="color:#2563eb; font-weight:800; font-size:0.8rem; display:inline-flex; align-items:center; gap:4px;"><i class="fa-solid fa-building-columns"></i> Bank</span>';
                                } else {
                                    $provBadge = '<span style="color:#64748b; font-weight:800; font-size:0.8rem;">' . strtoupper($provider) . '</span>';
                                }
                            ?>
                                <tr data-type="<?php echo htmlspecialchars($type); ?>" data-provider="<?php echo htmlspecialchars($provider); ?>" style="font-size:0.82rem;">
                                    <td style="padding:8px 10px; word-break:break-all;">
                                        <div style="font-weight:800; font-family:monospace; color:var(--pos-text); font-size:0.8rem;"><?php echo htmlspecialchars($tx['trxId'] ?? 'N/A'); ?></div>
                                        <div style="font-size:0.68rem; color:var(--pos-text-muted);"><?php echo date('M d, h:i A', strtotime($tx['timestamp'])); ?></div>
                                    </td>
                                    <td style="padding:8px 8px;"><?php echo $provBadge; ?></td>
                                    <td style="padding:8px 8px;"><?php echo $typeBadge; ?></td>
                                    <td style="padding:8px 8px; word-break:break-word;">
                                        <strong style="color:var(--pos-text);"><?php echo htmlspecialchars($tx['customerName'] ?? 'Walk-in'); ?></strong>
                                        <?php if (!empty($tx['customerPhone'])): ?>
                                            <div style="font-size:0.68rem; color:var(--pos-text-muted);"><?php echo htmlspecialchars($tx['customerPhone']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:8px 8px; word-break:break-word;">
                                        <strong style="color:var(--pos-text);"><?php echo htmlspecialchars($tx['receiverName'] ?? 'Beneficiary'); ?></strong>
                                        <div style="font-size:0.68rem; color:var(--pos-text-muted);"><?php echo htmlspecialchars($tx['receiverAccount'] ?? ''); ?></div>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <strong style="font-size:0.9rem; color:<?php echo ($type === 'cash_out') ? '#dc2626' : '#059669'; ?>;">
                                            PKR <?php echo number_format($amt); ?>
                                        </strong>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <strong style="color:#b45309; font-size:0.88rem; background:#fef3c7; padding:2px 6px; border-radius:4px;">
                                            +PKR <?php echo number_format($comm); ?>
                                        </strong>
                                    </td>
                                    <td style="text-align:right; padding:8px 8px;">
                                        <div style="display:inline-flex; gap:4px; align-items:center; justify-content:flex-end;">
                                            <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" style="padding:3px 6px; font-size:0.72rem;" onclick="printTxReceipt('<?php echo htmlspecialchars($tx['id'], ENT_QUOTES); ?>')" title="Print Receipt Slip">
                                                <i class="fa-solid fa-print"></i>
                                            </button>
                                            
                                            <?php if (($tx['status'] ?? 'completed') !== 'reversed'): ?>
                                                <form method="POST" action="" onsubmit="return confirm('Are you sure you want to REVERSE / RETURN this service transaction?');" style="display:inline; margin:0;">
                                                    <input type="hidden" name="action" value="reverse_transaction">
                                                    <input type="hidden" name="tx_id" value="<?php echo htmlspecialchars($tx['id']); ?>">
                                                    <button type="submit" class="pos-btn pos-btn-outline pos-btn-sm" style="color:#d97706; border-color:#fde68a; background:#fffbeb; padding:3px 6px; font-size:0.72rem;" title="Reverse / Return Transaction">
                                                        <i class="fa-solid fa-rotate-left"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="status-badge" style="background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; font-size:0.68rem; padding:2px 4px;" title="Reversed / Returned">
                                                    Reversed
                                                </span>
                                            <?php endif; ?>

                                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to permanently delete this transaction record?');" style="display:inline; margin:0;">
                                                <input type="hidden" name="action" value="delete_transaction">
                                                <input type="hidden" name="tx_id" value="<?php echo htmlspecialchars($tx['id']); ?>">
                                                <button type="submit" class="pos-btn pos-btn-outline pos-btn-sm" style="color:#ef4444; border-color:#fecaca; padding:3px 6px; font-size:0.72rem;" title="Delete Record">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
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
</div>

<!-- =========================================================================
     MODAL 1: NEW TRANSACTION LOGGING FORM MODAL
     ========================================================================= -->
<div class="modal" id="newTxModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; max-width:100vw; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; padding:15px; box-sizing:border-box; overflow-x:hidden;">
    <div style="background:#fff; border-radius:12px; max-width:620px; width:100%; max-height:92vh; overflow-y:auto; padding:24px; box-shadow:0 20px 40px rgba(0,0,0,0.3); position:relative; box-sizing:border-box;">
        <button type="button" onclick="closeNewTransactionModal()" style="position:absolute; top:16px; right:16px; background:none; border:none; font-size:1.3rem; color:#9ca3af; cursor:pointer;">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div style="margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
            <span style="background:var(--pos-red); color:#fff; font-size:0.72rem; font-weight:800; padding:2px 8px; border-radius:4px; text-transform:uppercase;">
                FINANCIAL SERVICES ENGINE
            </span>
            <h3 style="margin:6px 0 0 0; font-size:1.3rem; color:#0f172a;">Record Financial Transaction</h3>
            <p style="margin:2px 0 0 0; font-size:0.8rem; color:#64748b;">Log Easypaisa, JazzCash, Bill payment or Mobile load with automatic commission tracking</p>
        </div>

        <form method="POST" action="services.php" id="txForm">
            <input type="hidden" name="action" value="add_transaction">

            <!-- Transaction Type Selection Tabs -->
            <div style="margin-bottom:14px;">
                <label class="form-label" style="font-weight:800; font-size:0.85rem;">Transaction Flow / Nature *</label>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                    <label style="border:2px solid #10b981; border-radius:8px; padding:10px 12px; cursor:pointer; display:flex; align-items:center; gap:8px; background:#f0fdf4;">
                        <input type="radio" name="txType" value="cash_in" checked onchange="onTxTypeChange(this.value)" style="accent-color:#10b981; width:16px; height:16px;">
                        <div>
                            <strong style="color:#065f46; font-size:0.88rem; display:block;">🟢 Payment Send (Cash In)</strong>
                            <span style="font-size:0.72rem; color:#047857;">Customer sends money online</span>
                        </div>
                    </label>

                    <label style="border:2px solid #ef4444; border-radius:8px; padding:10px 12px; cursor:pointer; display:flex; align-items:center; gap:8px; background:#fef2f2;">
                        <input type="radio" name="txType" value="cash_out" onchange="onTxTypeChange(this.value)" style="accent-color:#ef4444; width:16px; height:16px;">
                        <div>
                            <strong style="color:#991b1b; font-size:0.88rem; display:block;">🔴 Cash Withdrawal (Cash Out)</strong>
                            <span style="font-size:0.72rem; color:#b91c1c;">Customer takes cash from shop</span>
                        </div>
                    </label>

                    <label style="border:1.5px solid #cbd5e1; border-radius:8px; padding:8px 10px; cursor:pointer; display:flex; align-items:center; gap:8px; background:#f8fafc;">
                        <input type="radio" name="txType" value="bill_payment" onchange="onTxTypeChange(this.value)" style="accent-color:#3b82f6;">
                        <div style="font-size:0.8rem; font-weight:700; color:#1e40af;">🔵 Utility Bill Payment</div>
                    </label>

                    <label style="border:1.5px solid #cbd5e1; border-radius:8px; padding:8px 10px; cursor:pointer; display:flex; align-items:center; gap:8px; background:#f8fafc;">
                        <input type="radio" name="txType" value="easyload" onchange="onTxTypeChange(this.value)" style="accent-color:#f59e0b;">
                        <div style="font-size:0.8rem; font-weight:700; color:#b45309;">🟡 Easyload / Card Recharge</div>
                    </label>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                <!-- Service Provider -->
                <div class="form-group">
                    <label class="form-label">Service Account / Provider *</label>
                    <select name="serviceProvider" id="serviceProviderSelect" class="form-select" required>
                        <option value="easypaisa">Easypaisa (03339688007)</option>
                        <option value="jazzcash">JazzCash (03339688007)</option>
                        <option value="bank">Bank Transfer (1Link / UBL / Meezan)</option>
                        <option value="sadapay">SadaPay / NayaPay</option>
                        <option value="upaisa">UPaisa</option>
                    </select>
                </div>

                <!-- TRX ID -->
                <div class="form-group">
                    <label class="form-label">TRX ID / SMS Reference No.</label>
                    <input type="text" name="trxId" id="modalTrxIdInput" class="form-input" placeholder="e.g. EP-9876543210 (Auto-generated if empty)">
                </div>
            </div>

            <!-- Amount & Commission with Presets -->
            <div style="background:#fffbeb; border:1.5px solid #fde68a; border-radius:10px; padding:14px; margin-bottom:14px;">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight:800; color:#92400e;">Transaction Principal Amount (PKR) *</label>
                        <input type="number" name="amount" id="modalAmountInput" class="form-input" placeholder="10000" min="1" step="any" required oninput="calculateCommissionLive()" style="font-size:1.1rem; font-weight:800;">
                        <!-- Quick Amount Presets -->
                        <div style="display:flex; gap:4px; margin-top:6px; flex-wrap:wrap;">
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:2px 6px; font-size:0.72rem;" onclick="setAmountPreset(1000)">1K</button>
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:2px 6px; font-size:0.72rem;" onclick="setAmountPreset(5000)">5K</button>
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:2px 6px; font-size:0.72rem;" onclick="setAmountPreset(10000)">10K</button>
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:2px 6px; font-size:0.72rem;" onclick="setAmountPreset(25000)">25K</button>
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:2px 6px; font-size:0.72rem;" onclick="setAmountPreset(50000)">50K</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight:800; color:#059669;">
                            <i class="fa-solid fa-sack-dollar"></i> Shop Commission (Profit PKR)
                        </label>
                        <input type="number" name="commission" id="modalCommissionInput" class="form-input" placeholder="100" min="0" step="any" value="50" oninput="updateNetCalculationLive()" style="font-size:1.1rem; font-weight:800; color:#059669; border-color:#10b981;">
                        <!-- Quick Commission Presets -->
                        <div style="display:flex; gap:4px; margin-top:6px; flex-wrap:wrap;">
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:2px 6px; font-size:0.72rem;" onclick="setCommissionPreset(20)">20</button>
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:2px 6px; font-size:0.72rem;" onclick="setCommissionPreset(50)">50</button>
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:2px 6px; font-size:0.72rem;" onclick="setCommissionPreset(100)">100</button>
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:2px 6px; font-size:0.72rem;" onclick="setCommissionPreset(200)">200</button>
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:2px 6px; font-size:0.72rem;" onclick="calcOnePercentCommission()">1%</button>
                        </div>
                    </div>
                </div>

                <!-- Real-time Net Handled Calculation Strip -->
                <div id="netCalculationStrip" style="margin-top:10px; background:#fff; border:1px solid #fde68a; border-radius:6px; padding:8px 12px; font-size:0.85rem; display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:#78350f;">Cash to Collect from Customer:</span>
                    <strong style="color:#065f46; font-size:1.05rem;" id="netHandledDisplay">PKR 0</strong>
                </div>
            </div>

            <!-- Customer & Receiver Information Grid -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label class="form-label">Customer / Sender Name *</label>
                    <input type="text" name="customerName" class="form-input" placeholder="e.g. Ahmad Khan" required>
                </div>
                <div>
                    <label class="form-label">Customer Phone No.</label>
                    <input type="text" name="customerPhone" class="form-input" placeholder="0333...">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label class="form-label" id="receiverNameLabel">Receiver Name / Bill Title</label>
                    <input type="text" name="receiverName" class="form-input" placeholder="e.g. Beneficiary Name">
                </div>
                <div>
                    <label class="form-label" id="receiverAccountLabel">Receiver Account / Mobile / Ref No. *</label>
                    <input type="text" name="receiverAccount" class="form-input" placeholder="e.g. 0345..." required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Optional Note / Reference</label>
                <input type="text" name="notes" class="form-input" placeholder="e.g. Emergency family medical fund transfer">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #e2e8f0; padding-top:14px;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="closeNewTransactionModal()">Cancel</button>
                <button type="submit" class="pos-btn pos-btn-primary pos-btn-lg">
                    <i class="fa-solid fa-check"></i> Save & Record Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: PRINTABLE RECEIPT SLIP VOUCHER MODAL
     ========================================================================= -->
<div class="modal" id="receiptModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; max-width:100vw; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; padding:15px; box-sizing:border-box; overflow-x:hidden;">
    <div style="background:#fff; border-radius:10px; max-width:400px; width:100%; padding:20px; box-shadow:0 20px 40px rgba(0,0,0,0.3); position:relative; box-sizing:border-box;">
        <button type="button" onclick="closeReceiptModal()" style="position:absolute; top:12px; right:12px; background:none; border:none; font-size:1.2rem; color:#9ca3af; cursor:pointer;">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <!-- Printable Voucher Area -->
        <div id="printableSlipArea" style="font-family:monospace; color:#000; font-size:0.85rem; border:1px dashed #94a3b8; padding:16px; border-radius:8px; background:#fff;">
            <div style="text-align:center; border-bottom:1px dashed #000; padding-bottom:10px; margin-bottom:10px;">
                <h3 style="margin:0; font-size:1.15rem; font-weight:900;">SAFDAR MOBILE STORE</h3>
                <div style="font-size:0.75rem; margin-top:2px;">EASYPAISA & JAZZCASH COUNTER</div>
                <div style="font-size:0.7rem; color:#475569;">Opp. Patt Bazar Eidgah Road near Purdil Masjid, Hangu</div>
                <div style="font-size:0.75rem; font-weight:700; margin-top:2px;">Helpline: 03339688007</div>
            </div>

            <div style="margin-bottom:10px; line-height:1.4;">
                <div><strong>TRX ID:</strong> <span id="slipTrxId"></span></div>
                <div><strong>Date:</strong> <span id="slipDate"></span></div>
                <div><strong>Service:</strong> <span id="slipProvider"></span></div>
                <div><strong>Type:</strong> <span id="slipType"></span></div>
            </div>

            <div style="border-top:1px dashed #000; border-bottom:1px dashed #000; padding:8px 0; margin-bottom:10px; line-height:1.4;">
                <div><strong>Customer:</strong> <span id="slipCustomer"></span></div>
                <div><strong>Sender Phone:</strong> <span id="slipPhone"></span></div>
                <div><strong>Receiver / Ref:</strong> <span id="slipReceiver"></span></div>
                <div><strong>Account:</strong> <span id="slipAccount"></span></div>
            </div>

            <div style="line-height:1.5; margin-bottom:10px;">
                <div style="display:flex; justify-content:space-between;">
                    <span>Principal Amount:</span>
                    <strong id="slipAmount">PKR 0</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Service Fee / Comm:</span>
                    <strong id="slipCommission">PKR 0</strong>
                </div>
                <div style="display:flex; justify-content:space-between; border-top:1px solid #000; padding-top:4px; font-size:1rem; font-weight:900;">
                    <span>Total Amount:</span>
                    <span id="slipTotal">PKR 0</span>
                </div>
            </div>

            <div style="text-align:center; font-size:0.72rem; border-top:1px dashed #000; padding-top:8px; color:#475569;">
                Thank you for using Safdar Mobile Store Financial Services!<br>
                For inquiries call: 03339688007
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:16px;">
            <button type="button" class="pos-btn pos-btn-outline pos-btn-block" onclick="closeReceiptModal()">Close</button>
            <button type="button" class="pos-btn pos-btn-primary pos-btn-block" onclick="printReceiptVoucher()">
                <i class="fa-solid fa-print"></i> Print Slip
            </button>
        </div>
    </div>
</div>

<script>
let currentTxType = 'cash_in';

function openNewTransactionModal() {
    document.getElementById('newTxModal').style.display = 'flex';
    document.getElementById('modalAmountInput').focus();
    calculateCommissionLive();
}

function closeNewTransactionModal() {
    document.getElementById('newTxModal').style.display = 'none';
}

function onTxTypeChange(type) {
    currentTxType = type;
    const strip = document.getElementById('netCalculationStrip');
    const recNameLabel = document.getElementById('receiverNameLabel');
    const recAccLabel = document.getElementById('receiverAccountLabel');

    if (type === 'cash_in') {
        recNameLabel.innerText = 'Receiver Beneficiary Name *';
        recAccLabel.innerText = 'Receiver Mobile / Account No. *';
    } else if (type === 'cash_out') {
        recNameLabel.innerText = 'Withdrawal Purpose / CNIC';
        recAccLabel.innerText = 'Customer Easypaisa/JazzCash Account *';
    } else if (type === 'bill_payment') {
        recNameLabel.innerText = 'Bill Company (e.g. PESCO Electricity)';
        recAccLabel.innerText = 'Bill Consumer / Reference No. *';
    } else if (type === 'easyload') {
        recNameLabel.innerText = 'Network (Jazz, Zong, Telenor, Ufone)';
        recAccLabel.innerText = 'Mobile Number for Easyload *';
    }

    updateNetCalculationLive();
}

function setAmountPreset(amt) {
    document.getElementById('modalAmountInput').value = amt;
    calculateCommissionLive();
}

function setCommissionPreset(comm) {
    document.getElementById('modalCommissionInput').value = comm;
    updateNetCalculationLive();
}

function calcOnePercentCommission() {
    const amt = parseFloat(document.getElementById('modalAmountInput').value) || 0;
    const comm = Math.max(20, Math.round(amt * 0.01));
    document.getElementById('modalCommissionInput').value = comm;
    updateNetCalculationLive();
}

function calculateCommissionLive() {
    const amt = parseFloat(document.getElementById('modalAmountInput').value) || 0;
    let comm = 50;

    if (amt <= 1000) comm = 20;
    else if (amt <= 3000) comm = 30;
    else if (amt <= 5000) comm = 50;
    else if (amt <= 10000) comm = 100;
    else if (amt <= 20000) comm = 150;
    else if (amt <= 50000) comm = 250;
    else comm = Math.round(amt * 0.007);

    document.getElementById('modalCommissionInput').value = comm;
    updateNetCalculationLive();
}

function updateNetCalculationLive() {
    const amt = parseFloat(document.getElementById('modalAmountInput').value) || 0;
    const comm = parseFloat(document.getElementById('modalCommissionInput').value) || 0;
    const displayEl = document.getElementById('netHandledDisplay');

    if (currentTxType === 'cash_out') {
        const net = Math.max(0, amt - comm);
        displayEl.innerText = 'PKR ' + net.toLocaleString() + ' (Pay Cash Out to Customer)';
    } else {
        const net = amt + comm;
        displayEl.innerText = 'PKR ' + net.toLocaleString() + ' (Collect Cash from Customer)';
    }
}

// Table Filtering
function filterTransactionsTable(filterType, btn) {
    document.querySelectorAll('.tx-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const rows = document.querySelectorAll('#servicesTable tbody tr');
    rows.forEach(r => {
        const type = r.getAttribute('data-type');
        if (filterType === 'all' || type === filterType) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}

function searchTransactionsTable() {
    const query = document.getElementById('txSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#servicesTable tbody tr');
    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        r.style.display = text.includes(query) ? '' : 'none';
    });
}

window.allServicesList = <?php echo json_encode($services); ?> || [];

// Receipt Slip Modal
function printTxReceipt(tx) {
    if (typeof tx === 'string') {
        tx = (window.allServicesList || []).find(t => String(t.id) === String(tx) || String(t.trxId) === String(tx)) || null;
    }
    if (!tx) return;
    document.getElementById('slipTrxId').innerText = tx.trxId || 'N/A';
    document.getElementById('slipDate').innerText = new Date(tx.timestamp).toLocaleString();
    document.getElementById('slipProvider').innerText = (tx.serviceProvider || 'EASYPAISA').toUpperCase();
    document.getElementById('slipType').innerText = (tx.txType || 'CASH IN').toUpperCase().replace('_', ' ');
    document.getElementById('slipCustomer').innerText = tx.customerName || 'Walk-in';
    document.getElementById('slipPhone').innerText = tx.customerPhone || 'N/A';
    document.getElementById('slipReceiver').innerText = tx.receiverName || 'Beneficiary';
    document.getElementById('slipAccount').innerText = tx.receiverAccount || 'N/A';
    
    const amt = parseFloat(tx.amount) || 0;
    const comm = parseFloat(tx.commission) || 0;
    const total = (tx.txType === 'cash_out') ? (amt - comm) : (amt + comm);

    document.getElementById('slipAmount').innerText = 'PKR ' + amt.toLocaleString();
    document.getElementById('slipCommission').innerText = 'PKR ' + comm.toLocaleString();
    document.getElementById('slipTotal').innerText = 'PKR ' + total.toLocaleString();

    document.getElementById('receiptModal').style.display = 'flex';
}

function closeReceiptModal() {
    document.getElementById('receiptModal').style.display = 'none';
}

function printReceiptVoucher() {
    const slipContent = document.getElementById('printableSlipArea').innerHTML;
    const win = window.open('', '', 'height=600,width=450');
    win.document.write('<html><head><title>Receipt Voucher - Safdar Mobile Store</title>');
    win.document.write('<style>body{font-family:monospace; margin:20px;} strong{font-weight:bold;}</style>');
    win.document.write('</head><body>');
    win.document.write(slipContent);
    win.document.write('</body></html>');
    win.document.close();
    win.focus();
    setTimeout(() => { win.print(); win.close(); }, 250);
}

// Auto-open receipt slip modal or filter if URL has ?trx=... or ?search=...
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const trx = urlParams.get('trx') || urlParams.get('id') || urlParams.get('receipt');
    const search = urlParams.get('search') || urlParams.get('q');

    if (search && document.getElementById('txSearchInput')) {
        document.getElementById('txSearchInput').value = search;
        searchTransactionsTable();
    }

    if (trx) {
        const servicesList = <?php echo json_encode($services); ?> || [];
        const match = servicesList.find(t => t.id === trx || t.trxId === trx || (t.trxId && t.trxId.toLowerCase() === trx.toLowerCase()));
        if (match) {
            printTxReceipt(match);
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
