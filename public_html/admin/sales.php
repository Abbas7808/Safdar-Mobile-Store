<?php
$currentPage = 'sales';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$sales = get_json_file('sales') ?? [];
$returnsLog = get_json_file('sales_returns') ?? [];
$deletedLog = get_json_file('deleted_invoices') ?? [];
$products = get_json_file('products') ?? [];
$currentUser = get_session_user();
$isSuperAdmin = ($currentUser['role'] ?? '') === 'super_admin';

// KPI Metrics Calculation
$totalInvoices = count($sales);
$grossRevenue = 0;
$refundedAmount = 0;
$refundedCount = count($returnsLog);
$completedCount = 0;
$cashRevenue = 0;
$onlineRevenue = 0;

foreach ($sales as $s) {
    $tot = floatval($s['total'] ?? 0);
    $st = strtolower($s['status'] ?? 'completed');
    $pm = strtolower($s['paymentMethod'] ?? 'cash');

    if ($st === 'refunded') {
        $refundedAmount += $tot;
    } else {
        $grossRevenue += $tot;
        $completedCount++;
        if ($pm === 'cash') {
            $cashRevenue += $tot;
        } else {
            $onlineRevenue += $tot;
        }
    }
}

// Add refunds logged in returnsLog if not in sales
foreach ($returnsLog as $ret) {
    if (!in_array($ret['invoiceNo'], array_column($sales, 'invoiceNo'))) {
        $refundedAmount += floatval($ret['refundAmount'] ?? 0);
    }
}

$netRevenue = max(0, $grossRevenue);
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content" style="padding:20px; box-sizing:border-box;">
        
        <!-- Page Header & Action Buttons -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
            <div>
                <h1 style="font-family:var(--pos-font-heading); font-size:1.6rem; font-weight:900; color:var(--pos-text); margin:0; display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-receipt" style="color:var(--pos-red);"></i> Sales, Returns &amp; Audit Logs
                </h1>
                <p style="color:var(--pos-text-muted); font-size:0.85rem; margin:4px 0 0 0;">
                    Complete record of POS invoices, customer returns &amp; refunds, and permanent deletion audit archives
                </p>
            </div>

            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="window.openSalesAnalyticsModal()">
                    <i class="fa-solid fa-chart-column" style="color:var(--pos-red);"></i> Analytics
                </button>
                <button type="button" class="pos-btn pos-btn-outline" onclick="openManualSaleModal()" style="border-color:#2563eb; color:#2563eb; font-weight:700;">
                    <i class="fa-solid fa-file-circle-plus"></i> + Add Manual Sale
                </button>
                <a href="pos.php" class="pos-btn pos-btn-primary" style="font-weight:800; text-decoration:none;">
                    <i class="fa-solid fa-cash-register"></i> + Open POS Terminal
                </a>
            </div>
        </div>

        <!-- 4 KPI Summary Cards -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:20px;">
            <!-- Card 1: Total Invoices -->
            <div class="pos-card" style="padding:16px; border-left:4px solid var(--pos-red); display:flex; align-items:center; gap:14px;">
                <div style="background:rgba(215,25,32,0.1); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--pos-red); font-size:1.4rem;">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800;">Active Invoices</div>
                    <div style="font-size:1.45rem; font-weight:900; color:var(--pos-text);"><?php echo $completedCount; ?></div>
                    <div style="font-size:0.7rem; color:#059669; font-weight:700;"><?php echo $totalInvoices; ?> Total Recorded</div>
                </div>
            </div>

            <!-- Card 2: Net Sales Revenue -->
            <div class="pos-card" style="padding:16px; border-left:4px solid #10b981; display:flex; align-items:center; gap:14px;">
                <div style="background:rgba(16,185,129,0.1); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#059669; font-size:1.4rem;">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800;">Net Sales Revenue</div>
                    <div style="font-size:1.45rem; font-weight:900; color:#059669;">PKR <?php echo number_format($netRevenue); ?></div>
                    <div style="font-size:0.7rem; color:var(--pos-text-muted); font-weight:700;">Cash: PKR <?php echo number_format($cashRevenue); ?></div>
                </div>
            </div>

            <!-- Card 3: Returns & Refunds Logged -->
            <div class="pos-card" style="padding:16px; border-left:4px solid #d97706; display:flex; align-items:center; gap:14px; cursor:pointer;" onclick="switchSalesTab('returns')">
                <div style="background:rgba(217,119,6,0.1); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#d97706; font-size:1.4rem;">
                    <i class="fa-solid fa-rotate-left"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800;">Returns &amp; Refunds</div>
                    <div style="font-size:1.45rem; font-weight:900; color:#d97706;">PKR <?php echo number_format($refundedAmount); ?></div>
                    <div style="font-size:0.7rem; color:#d97706; font-weight:700;"><?php echo count($returnsLog); ?> Recorded Return Logs &rarr;</div>
                </div>
            </div>

            <!-- Card 4: Deleted Invoices Archive -->
            <div class="pos-card" style="padding:16px; border-left:4px solid #dc2626; display:flex; align-items:center; gap:14px; cursor:pointer;" onclick="switchSalesTab('deleted')">
                <div style="background:rgba(220,38,38,0.1); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#dc2626; font-size:1.4rem;">
                    <i class="fa-solid fa-trash-can"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800;">Deleted Invoices Log</div>
                    <div style="font-size:1.45rem; font-weight:900; color:#dc2626;"><?php echo count($deletedLog); ?> Archived</div>
                    <div style="font-size:0.7rem; color:#dc2626; font-weight:700;">Permanent Audit Trail &rarr;</div>
                </div>
            </div>
        </div>

        <!-- 3 Primary Navigation Tabs -->
        <div style="display:flex; gap:8px; margin-bottom:16px; border-bottom:2px solid var(--pos-border); padding-bottom:8px; flex-wrap:wrap;">
            <button type="button" class="pos-btn pos-btn-primary" id="tabBtnSales" onclick="switchSalesTab('sales')" style="border-radius:20px; font-weight:800; font-size:0.85rem; padding:7px 16px;">
                <i class="fa-solid fa-receipt"></i> 1. All Sales Invoices (<?php echo $totalInvoices; ?>)
            </button>
            <button type="button" class="pos-btn pos-btn-outline" id="tabBtnReturns" onclick="switchSalesTab('returns')" style="border-radius:20px; font-weight:800; font-size:0.85rem; padding:7px 16px;">
                <i class="fa-solid fa-rotate-left"></i> 2. Returns &amp; Refunds Record Log (<?php echo count($returnsLog); ?>)
            </button>
            <button type="button" class="pos-btn pos-btn-outline" id="tabBtnDeleted" onclick="switchSalesTab('deleted')" style="border-radius:20px; font-weight:800; font-size:0.85rem; padding:7px 16px; color:#dc2626; border-color:#fecaca;">
                <i class="fa-solid fa-trash-can"></i> 3. Deleted Invoices Audit Archive (<?php echo count($deletedLog); ?>)
            </button>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 1: ALL SALES INVOICES (Main POS Invoices Table)           -->
        <!-- ============================================================= -->
        <div id="tabContentSales">
            <!-- Filter & Search Controls -->
            <div class="pos-card" style="padding:12px 16px; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div style="display:flex; align-items:center; gap:10px; flex:1; min-width:260px;">
                        <i class="fa-solid fa-magnifying-glass" style="color:var(--pos-text-muted);"></i>
                        <input type="text" id="salesSearchInput" class="form-input" placeholder="Search by Invoice #, Customer Name, Phone, or Cashier..." onkeyup="filterSalesTable()" style="margin:0; width:100%; font-size:0.85rem;">
                    </div>

                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <select id="salesStatusFilter" class="form-select" onchange="filterSalesTable()" style="margin:0; font-size:0.85rem; padding:6px 10px;">
                            <option value="all">All Statuses</option>
                            <option value="completed">Active / Completed</option>
                            <option value="refunded">Returned / Refunded</option>
                        </select>

                        <select id="salesPaymentFilter" class="form-select" onchange="filterSalesTable()" style="margin:0; font-size:0.85rem; padding:6px 10px;">
                            <option value="all">All Payments</option>
                            <option value="cash">Cash</option>
                            <option value="easypaisa">Easypaisa</option>
                            <option value="jazzcash">JazzCash</option>
                            <option value="bank">Bank Transfer</option>
                        </select>

                        <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="resetSalesFilters()">
                            <i class="fa-solid fa-arrows-rotate"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Invoices Data Table -->
            <div class="data-table-wrap pos-card" style="padding:0; overflow-x:auto; -webkit-overflow-scrolling:touch; width:100%; border:1px solid var(--pos-border); border-radius:12px; margin-bottom:20px;">
                <table class="data-table" id="salesTable" style="margin:0; width:100%; min-width:850px; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid var(--pos-border);">
                            <th style="width:130px; padding:10px 12px;">Invoice #</th>
                            <th style="padding:10px 12px;">Customer</th>
                            <th style="padding:10px 12px;">Items</th>
                            <th style="padding:10px 12px;">Total Paid</th>
                            <th style="padding:10px 12px;">Payment</th>
                            <th style="padding:10px 12px;">Status</th>
                            <th style="padding:10px 12px;">Date &amp; Cashier</th>
                            <th style="text-align:right; width:190px; padding:10px 12px; position:sticky; right:0; background:#f8fafc; z-index:2; box-shadow:-3px 0 6px rgba(0,0,0,0.05);">
                                Actions (Return / Delete)
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales)): ?>
                            <tr id="emptySalesRow"><td colspan="8" style="text-align:center; padding:35px; color:var(--pos-text-muted);">No sales transactions recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach (array_reverse($sales) as $s): 
                                $isRefunded = ($s['status'] ?? 'completed') === 'refunded';
                                $itemsList = is_array($s['items'] ?? null) ? $s['items'] : [];
                                $itemCount = count($itemsList);
                                $itemsSummary = [];
                                foreach ($itemsList as $it) {
                                    $itemsSummary[] = ($it['qty'] ?? 1) . 'x ' . ($it['name'] ?? 'Item');
                                }
                                $itemsTooltip = implode(', ', $itemsSummary);
                            ?>
                                <tr class="sale-row" 
                                    data-invoice="<?php echo strtolower($s['invoiceNo'] ?? ''); ?>"
                                    data-customer="<?php echo strtolower($s['customerName'] ?? ''); ?>"
                                    data-phone="<?php echo strtolower($s['customerPhone'] ?? ''); ?>"
                                    data-cashier="<?php echo strtolower($s['cashier'] ?? ''); ?>"
                                    data-status="<?php echo strtolower($s['status'] ?? 'completed'); ?>"
                                    data-payment="<?php echo strtolower($s['paymentMethod'] ?? 'cash'); ?>"
                                    data-items="<?php echo strtolower(htmlspecialchars($itemsTooltip)); ?>"
                                    style="border-bottom:1px solid #f1f5f9;">
                                    
                                    <td style="padding:10px 12px;">
                                        <strong style="color:var(--pos-red); cursor:pointer; font-family:monospace; font-size:0.9rem;" onclick="openInvoiceDetailsModal('<?php echo $s['id']; ?>')">
                                            <?php echo htmlspecialchars($s['invoiceNo']); ?>
                                        </strong>
                                    </td>
                                    
                                    <td style="padding:10px 12px;">
                                        <strong style="font-size:0.88rem;"><?php echo htmlspecialchars($s['customerName']); ?></strong>
                                        <?php if (!empty($s['customerPhone'])): ?>
                                            <div style="font-size:0.75rem; color:var(--pos-text-muted);"><?php echo htmlspecialchars($s['customerPhone']); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <td style="padding:10px 12px;">
                                        <span class="status-badge" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; font-size:0.72rem; cursor:pointer;" title="<?php echo htmlspecialchars($itemsTooltip); ?>">
                                            <i class="fa-solid fa-box-open"></i> <?php echo $itemCount; ?> item(s)
                                        </span>
                                    </td>

                                    <td style="padding:10px 12px;">
                                        <?php if ($isRefunded): ?>
                                            <strong style="font-size:0.95rem; color:#dc2626; text-decoration:line-through;">PKR <?php echo number_format($s['total']); ?></strong>
                                            <div style="font-size:0.68rem; color:#dc2626; font-weight:800;">REFUNDED</div>
                                        <?php else: ?>
                                            <strong style="font-size:1.05rem; color:#059669;">PKR <?php echo number_format($s['total']); ?></strong>
                                            <?php if (!empty($s['discount']) && floatval($s['discount']) > 0): ?>
                                                <div style="font-size:0.68rem; color:#dc2626; font-weight:700;">Disc: -PKR <?php echo number_format($s['discount']); ?></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>

                                    <td style="padding:10px 12px;">
                                        <?php 
                                            $pm = strtoupper($s['paymentMethod'] ?? 'CASH');
                                            $pmColor = ($pm === 'CASH') ? '#059669' : (($pm === 'EASYPAISA') ? '#10b981' : '#2563eb');
                                        ?>
                                        <span class="status-badge" style="background:rgba(0,0,0,0.04); color:<?php echo $pmColor; ?>; border:1px solid currentColor; font-size:0.72rem; font-weight:800;">
                                            <?php echo $pm; ?>
                                        </span>
                                    </td>

                                    <td style="padding:10px 12px;">
                                        <?php if ($isRefunded): ?>
                                            <span class="status-badge" style="background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; font-size:0.72rem; font-weight:800;">
                                                <i class="fa-solid fa-rotate-left"></i> Returned
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; font-size:0.72rem; font-weight:800;">
                                                <i class="fa-solid fa-check"></i> Completed
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td style="padding:10px 12px;">
                                        <div style="font-size:0.78rem; color:var(--pos-text); font-weight:600;"><?php echo date('M d, Y h:i A', strtotime($s['createdAt'])); ?></div>
                                        <div style="font-size:0.7rem; color:var(--pos-text-muted);">By: <?php echo htmlspecialchars($s['cashier'] ?? 'admin'); ?></div>
                                    </td>

                                    <td style="text-align:right; white-space:nowrap; padding:10px 12px; position:sticky; right:0; background:#ffffff; z-index:2; box-shadow:-3px 0 6px rgba(0,0,0,0.05);">
                                        <div style="display:inline-flex; gap:6px; align-items:center; justify-content:flex-end;">
                                            <!-- Action 1: Print / View Invoice Receipt -->
                                            <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" title="View &amp; Print Receipt" onclick="openInvoiceDetailsModal('<?php echo $s['id']; ?>')" style="padding:5px 9px; font-size:0.78rem; font-weight:700;">
                                                <i class="fa-solid fa-print"></i>
                                            </button>

                                            <!-- Action 1b: WhatsApp Direct Receipt -->
                                            <button type="button" class="pos-btn pos-btn-sm" title="Send WhatsApp Receipt to Customer" onclick="window.sendInvoiceWhatsApp('<?php echo $s['id']; ?>', '<?php echo htmlspecialchars($s['customerPhone'] ?? ''); ?>')" style="padding:5px 9px; font-size:0.78rem; font-weight:700; background:#ecfdf5; color:#059669; border:1.5px solid #a7f3d0; cursor:pointer;">
                                                <i class="fa-brands fa-whatsapp"></i>
                                            </button>

                                            <!-- Action 2: Return / Refund Sale Option -->
                                            <?php if (!$isRefunded): ?>
                                                <button type="button" class="pos-btn pos-btn-sm" title="Return / Refund this Sale" onclick="openReturnSaleModal('<?php echo $s['id']; ?>', '<?php echo htmlspecialchars($s['invoiceNo']); ?>', <?php echo floatval($s['total']); ?>)" style="padding:5px 10px; font-size:0.78rem; font-weight:800; background:#fffbeb; color:#b45309; border:1.5px solid #fde68a; cursor:pointer;">
                                                    <i class="fa-solid fa-rotate-left"></i> Return
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="pos-btn pos-btn-sm" disabled style="padding:5px 8px; font-size:0.72rem; opacity:0.6; background:#f1f5f9; color:#94a3b8; border:1px solid #cbd5e1;">
                                                    <i class="fa-solid fa-ban"></i> Refunded
                                                </button>
                                            <?php endif; ?>

                                            <!-- Action 3: Delete Sale Option -->
                                            <button type="button" class="pos-btn pos-btn-sm" title="Delete Sale Invoice" onclick="openDeleteSaleModal('<?php echo $s['id']; ?>', '<?php echo htmlspecialchars($s['invoiceNo']); ?>', <?php echo floatval($s['total']); ?>)" style="padding:5px 10px; font-size:0.78rem; font-weight:800; background:#fef2f2; color:#dc2626; border:1.5px solid #fecaca; cursor:pointer;">
                                                <i class="fa-solid fa-trash-can"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 2: RETURNS & REFUNDS AUDIT RECORD LOG                     -->
        <!-- ============================================================= -->
        <div id="tabContentReturns" style="display:none;">
            <div class="pos-card" style="padding:14px 18px; margin-bottom:16px; background:#fffbeb; border:1.5px solid #fde68a;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <strong style="color:#92400e; font-size:1rem; display:flex; align-items:center; gap:8px;">
                            <i class="fa-solid fa-rotate-left"></i> Customer Returns &amp; Refund Audit Ledger
                        </strong>
                        <p style="margin:2px 0 0 0; font-size:0.8rem; color:#78350f;">
                            Permanent audit trail of all items returned, refund amounts disbursed, restock history, and cashier accountability.
                        </p>
                    </div>
                    <div>
                        <span class="status-badge" style="background:#b45309; color:#fff; font-size:0.8rem; font-weight:800;">
                            Total Returns: <?php echo count($returnsLog); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="data-table-wrap pos-card" style="padding:0; overflow-x:auto; -webkit-overflow-scrolling:touch; width:100%; border:1px solid var(--pos-border); border-radius:12px; margin-bottom:20px;">
                <table class="data-table" style="margin:0; width:100%; min-width:850px; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid var(--pos-border);">
                            <th style="padding:10px 12px;">Return Ref #</th>
                            <th style="padding:10px 12px;">Original Invoice</th>
                            <th style="padding:10px 12px;">Customer</th>
                            <th style="padding:10px 12px;">Refund Amount</th>
                            <th style="padding:10px 12px;">Return Reason</th>
                            <th style="padding:10px 12px;">Restocked Items</th>
                            <th style="padding:10px 12px;">Processed By</th>
                            <th style="padding:10px 12px;">Date &amp; Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($returnsLog)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:35px; color:var(--pos-text-muted);">No customer returns recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($returnsLog as $ret): ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:10px 12px;">
                                        <strong style="color:#b45309; font-family:monospace; font-size:0.85rem;">
                                            <?php echo htmlspecialchars($ret['returnNo'] ?? $ret['id']); ?>
                                        </strong>
                                    </td>
                                    <td style="padding:10px 12px;">
                                        <strong style="color:var(--pos-red); font-family:monospace; font-size:0.85rem; cursor:pointer;" onclick="openInvoiceDetailsModal('<?php echo htmlspecialchars($ret['invoiceId'] ?? $ret['invoiceNo']); ?>')">
                                            <?php echo htmlspecialchars($ret['invoiceNo']); ?>
                                        </strong>
                                    </td>
                                    <td style="padding:10px 12px;">
                                        <strong><?php echo htmlspecialchars($ret['customerName'] ?? 'Walk-in'); ?></strong>
                                        <?php if (!empty($ret['customerPhone'])): ?>
                                            <div style="font-size:0.7rem; color:#64748b;"><?php echo htmlspecialchars($ret['customerPhone']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 12px;">
                                        <strong style="color:#dc2626; font-size:0.95rem;">-PKR <?php echo number_format($ret['refundAmount'] ?? 0); ?></strong>
                                    </td>
                                    <td style="padding:10px 12px;">
                                        <span style="font-size:0.8rem; color:#92400e; background:#fef3c7; padding:2px 8px; border-radius:4px; font-weight:700;">
                                            <?php echo htmlspecialchars($ret['reason'] ?? 'Returned'); ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 12px;">
                                        <?php if (!empty($ret['restocked'])): ?>
                                            <span class="status-badge" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; font-size:0.7rem; font-weight:800;">
                                                <i class="fa-solid fa-boxes-stacked"></i> Restocked to Stock
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge" style="background:#f1f5f9; color:#64748b; font-size:0.7rem;">
                                                No Restock
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 12px;">
                                        <span style="font-size:0.8rem; font-weight:700;"><?php echo htmlspecialchars($ret['processedBy'] ?? 'admin'); ?></span>
                                    </td>
                                    <td style="padding:10px 12px; font-size:0.8rem; color:var(--pos-text-muted);">
                                        <?php echo date('M d, Y h:i A', strtotime($ret['timestamp'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 3: DELETED INVOICES AUDIT ARCHIVE                         -->
        <!-- ============================================================= -->
        <div id="tabContentDeleted" style="display:none;">
            <div class="pos-card" style="padding:14px 18px; margin-bottom:16px; background:#fef2f2; border:1.5px solid #fecaca;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <strong style="color:#991b1b; font-size:1rem; display:flex; align-items:center; gap:8px;">
                            <i class="fa-solid fa-trash-can"></i> Deleted Invoices Audit Archive (Permanent Log)
                        </strong>
                        <p style="margin:2px 0 0 0; font-size:0.8rem; color:#7f1d1d;">
                            Complete permanent history of all deleted invoices, reasons, original snapshots, and staff who executed the deletion.
                        </p>
                    </div>
                    <div>
                        <span class="status-badge" style="background:#dc2626; color:#fff; font-size:0.8rem; font-weight:800;">
                            Deleted Logs: <?php echo count($deletedLog); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="data-table-wrap pos-card" style="padding:0; overflow-x:auto; -webkit-overflow-scrolling:touch; width:100%; border:1px solid var(--pos-border); border-radius:12px; margin-bottom:20px;">
                <table class="data-table" style="margin:0; width:100%; min-width:850px; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid var(--pos-border);">
                            <th style="padding:10px 12px;">Delete ID</th>
                            <th style="padding:10px 12px;">Deleted Invoice #</th>
                            <th style="padding:10px 12px;">Customer</th>
                            <th style="padding:10px 12px;">Invoice Amount</th>
                            <th style="padding:10px 12px;">Payment</th>
                            <th style="padding:10px 12px;">Deletion Reason</th>
                            <th style="padding:10px 12px;">Stock Restored</th>
                            <th style="padding:10px 12px;">Deleted By</th>
                            <th style="padding:10px 12px;">Deleted Date &amp; Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($deletedLog)): ?>
                            <tr><td colspan="9" style="text-align:center; padding:35px; color:var(--pos-text-muted);">No deleted invoices recorded. All invoices are intact!</td></tr>
                        <?php else: ?>
                            <?php foreach ($deletedLog as $del): ?>
                                <tr>
                                    <td>
                                        <strong style="color:#dc2626; font-family:monospace; font-size:0.85rem;">
                                            <?php echo htmlspecialchars($del['deleteNo'] ?? $del['id']); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <strong style="color:#64748b; font-family:monospace; font-size:0.85rem; text-decoration:line-through;">
                                            <?php echo htmlspecialchars($del['invoiceNo']); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($del['customerName'] ?? 'Walk-in'); ?></strong>
                                    </td>
                                    <td>
                                        <strong>PKR <?php echo number_format($del['invoiceTotal'] ?? 0); ?></strong>
                                    </td>
                                    <td>
                                        <span class="status-badge" style="background:#f1f5f9; color:#475569; font-size:0.7rem; font-weight:800;">
                                            <?php echo strtoupper($del['paymentMethod'] ?? 'CASH'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size:0.78rem; color:#991b1b; background:#fee2e2; padding:2px 8px; border-radius:4px; font-weight:700;">
                                            <?php echo htmlspecialchars($del['deleteReason'] ?? 'Admin Deletion'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($del['restocked'])): ?>
                                            <span class="status-badge" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; font-size:0.7rem; font-weight:800;">
                                                <i class="fa-solid fa-check"></i> Stock Restored
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge" style="background:#fee2e2; color:#dc2626; font-size:0.7rem;">
                                                No Restock
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size:0.8rem; font-weight:700;"><?php echo htmlspecialchars($del['deletedBy'] ?? 'super_admin'); ?></span>
                                    </td>
                                    <td style="font-size:0.8rem; color:var(--pos-text-muted);">
                                        <?php echo date('M d, Y h:i A', strtotime($del['deletedAt'])); ?>
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

<!-- ============================================================= -->
<!-- MODAL 1: VIEW & PRINT INVOICE RECEIPT (80mm & A4)            -->
<!-- ============================================================= -->
<div class="pos-modal-overlay" id="invoiceDetailsModal" style="display:none; z-index:99999;">
    <div class="pos-modal" style="max-width:440px; padding:20px; max-height:92vh; overflow-y:auto;">
        <div class="pos-modal-header" style="border-bottom:1.5px solid var(--pos-border); padding-bottom:10px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
            <h3 class="pos-modal-title" style="margin:0; font-size:1.15rem; color:var(--pos-text);">
                <i class="fa-solid fa-receipt" style="color:var(--pos-red);"></i> Sale Invoice Receipt
            </h3>
            <button class="pos-modal-close" onclick="closeInvoiceDetailsModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div id="invoicePrintArea" style="background:#fff; color:#000; padding:12px 10px; font-family:'Courier New', Courier, monospace; font-size:12px; line-height:1.4; border:1px solid #e2e8f0; border-radius:6px;">
            <div style="text-align:center; padding:20px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading invoice...</div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-top:16px;">
            <button type="button" class="pos-btn pos-btn-outline" onclick="closeInvoiceDetailsModal()">Close</button>
            <div style="display:flex; gap:6px;">
                <button type="button" class="pos-btn" id="invoiceDetailsWhatsAppBtn" onclick="sendActiveInvoiceWhatsAppModal()" style="background:#059669; color:#fff; font-weight:800; border:none; padding:7px 12px;">
                    <i class="fa-brands fa-whatsapp"></i> Send WhatsApp Receipt
                </button>
                <button type="button" class="pos-btn pos-btn-primary" onclick="printCurrentInvoiceDirect()" style="font-weight:800;">
                    <i class="fa-solid fa-print"></i> Print 80mm Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 2: RETURN & REFUND SALE INVOICE                         -->
<!-- ============================================================= -->
<div class="pos-modal-overlay" id="returnSaleModal" style="display:none; z-index:99999;">
    <div class="pos-modal" style="max-width:460px; padding:20px;">
        <div class="pos-modal-header" style="border-bottom:1.5px solid var(--pos-border); padding-bottom:10px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
            <h3 class="pos-modal-title" style="margin:0; font-size:1.15rem; color:#d97706; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-rotate-left"></i> Return &amp; Refund Sale Invoice
            </h3>
            <button class="pos-modal-close" onclick="closeReturnSaleModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="returnSaleForm" onsubmit="submitSaleReturn(event)">
            <input type="hidden" id="returnSaleId" value="">

            <div style="background:#fffbeb; border:1.5px solid #fde68a; border-radius:8px; padding:10px 14px; margin-bottom:14px;">
                <div style="font-size:0.75rem; color:#92400e; font-weight:800; text-transform:uppercase;">INVOICE TO RETURN:</div>
                <div style="font-size:1.2rem; font-weight:900; color:#b45309; font-family:monospace;" id="returnInvoiceNoDisplay">INV-0000</div>
                <div style="font-size:0.9rem; color:#78350f; font-weight:800; margin-top:2px;">
                    Total Paid to Refund: <strong id="returnInvoiceTotalDisplay" style="color:#b45309;">PKR 0</strong>
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:0.78rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:4px;">
                    Reason for Return / Refund *
                </label>
                <select id="returnReasonSelect" class="form-select" style="margin-bottom:6px; font-size:0.82rem;" onchange="if(this.value==='Other'){document.getElementById('returnReasonCustom').style.display='block';}else{document.getElementById('returnReasonCustom').style.display='none';}">
                    <option value="Customer returned item / Change of mind">Customer returned item / Change of mind</option>
                    <option value="Faulty or defective product">Faulty or defective product</option>
                    <option value="Item exchanged for different model">Item exchanged for different model</option>
                    <option value="Billing / Cashier input error">Billing / Cashier input error</option>
                    <option value="Other">Other Reason (Type below)</option>
                </select>
                <input type="text" id="returnReasonCustom" class="form-input" placeholder="Type custom return reason..." style="display:none; font-size:0.82rem;">
            </div>

            <div style="background:#f8fafc; border:1.5px solid #cbd5e1; border-radius:8px; padding:10px 12px; margin-bottom:16px;">
                <label style="font-size:0.8rem; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" id="returnRestockCheckbox" checked style="width:16px; height:16px; accent-color:#d97706;">
                    <span>Automatically restore physical products back into inventory stock</span>
                </label>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="closeReturnSaleModal()">Cancel</button>
                <button type="submit" class="pos-btn pos-btn-primary" style="background:#d97706; font-weight:800;" id="btnSubmitReturn">
                    <i class="fa-solid fa-rotate-left"></i> Confirm Return &amp; Refund
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 3: DELETE SALE INVOICE CONFIRMATION                     -->
<!-- ============================================================= -->
<div class="pos-modal-overlay" id="deleteSaleModal" style="display:none; z-index:99999;">
    <div class="pos-modal" style="max-width:440px; padding:22px; text-align:center;">
        <div style="width:56px; height:56px; border-radius:50%; background:#fee2e2; color:#dc2626; display:flex; align-items:center; justify-content:center; font-size:1.7rem; margin:0 auto 12px auto;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>

        <h3 style="margin:0 0 8px 0; font-size:1.25rem; color:var(--pos-text); font-weight:900;">Delete Sale Invoice?</h3>
        <p style="font-size:0.85rem; color:var(--pos-text-muted); margin:0 0 14px 0;">
            Are you sure you want to delete invoice <strong id="deleteInvoiceNoDisplay" style="color:var(--pos-red);">#INV-000</strong>? A permanent record will be saved in the <strong style="color:#991b1b;">Deleted Invoices Archive</strong>.
        </p>

        <div style="text-align:left; margin-bottom:12px;">
            <label style="font-size:0.75rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:4px;">
                Reason for Deletion *
            </label>
            <input type="text" id="deleteReasonInput" class="form-input" placeholder="e.g. Test transaction / Cashier wrong entry" value="Test / Erroneous transaction removal" style="font-size:0.82rem;">
        </div>

        <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; margin-bottom:18px;">
            <label style="font-size:0.8rem; font-weight:700; color:#334155; display:flex; align-items:center; gap:8px; cursor:pointer;">
                <input type="checkbox" id="deleteRestockCheckbox" checked style="width:16px; height:16px; accent-color:#dc2626;">
                <span>Return items back to inventory stock before deleting</span>
            </label>
        </div>

        <input type="hidden" id="deleteSaleId" value="">

        <div style="display:flex; justify-content:center; gap:10px;">
            <button type="button" class="pos-btn pos-btn-outline" onclick="closeDeleteSaleModal()">Cancel</button>
            <button type="button" class="pos-btn pos-btn-primary" style="background:#dc2626; font-weight:800;" onclick="confirmDeleteSale()" id="btnConfirmDelete">
                <i class="fa-solid fa-trash-can"></i> Yes, Delete &amp; Archive
            </button>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 4: ADD MANUAL SALE / INVOICE MODAL                      -->
<!-- ============================================================= -->
<div class="pos-modal-overlay" id="addManualSaleModal" style="display:none; z-index:99999;">
    <div class="pos-modal" style="max-width:540px; padding:20px; max-height:92vh; overflow-y:auto;">
        <div class="pos-modal-header" style="border-bottom:1.5px solid var(--pos-border); padding-bottom:10px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
            <h3 class="pos-modal-title" style="margin:0; font-size:1.15rem; color:var(--pos-text); display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-file-circle-plus" style="color:#2563eb;"></i> Add Manual Sale Invoice
            </h3>
            <button class="pos-modal-close" onclick="closeManualSaleModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="manualSaleForm" onsubmit="submitManualSale(event)">
            <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:10px; margin-bottom:10px;">
                <div>
                    <label style="font-size:0.75rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:3px;">
                        Customer Name *
                    </label>
                    <input type="text" id="manualCustName" class="form-input" required placeholder="e.g. Muhammad Ali / Walk-in" value="Walk-in Customer" style="font-size:0.82rem;">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:3px;">
                        Customer Phone
                    </label>
                    <input type="text" id="manualCustPhone" class="form-input" placeholder="e.g. 0333 9688007" style="font-size:0.82rem;">
                </div>
            </div>

            <!-- Item Type Selection -->
            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:10px 12px; margin-bottom:12px;">
                <div style="font-size:0.75rem; font-weight:800; color:#334155; margin-bottom:6px;">Select Product or Custom Service:</div>
                
                <div style="margin-bottom:8px;">
                    <label style="font-size:0.72rem; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Choose Existing Product (Auto-fills price &amp; deducts stock)</label>
                    <select id="manualProductSelect" class="form-select" style="font-size:0.82rem;" onchange="onManualProductSelect()">
                        <option value="">-- Or Select Existing Product --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>" data-price="<?php echo floatval($p['sellingPrice'] ?? $p['priceNumeric'] ?? 0); ?>" data-cost="<?php echo floatval($p['costPrice'] ?? 0); ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>">
                                <?php echo htmlspecialchars($p['name']); ?> (Stock: <?php echo $p['stock']; ?> | PKR <?php echo number_format($p['sellingPrice'] ?? $p['priceNumeric'] ?? 0); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="font-size:0.72rem; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Item / Service Description *</label>
                    <input type="text" id="manualItemName" class="form-input" required placeholder="e.g. Samsung Fast Charger 25W / Screen Replacement" style="font-size:0.82rem;">
                </div>
            </div>

            <!-- Price & Quantity Breakdown -->
            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:8px; margin-bottom:12px;">
                <div>
                    <label style="font-size:0.72rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:2px;">Unit Price (PKR) *</label>
                    <input type="number" id="manualUnitPrice" class="form-input" required placeholder="0" oninput="calcManualSaleTotal()" style="font-size:0.85rem; font-weight:800; text-align:right;">
                </div>
                <div>
                    <label style="font-size:0.72rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:2px;">Quantity *</label>
                    <input type="number" id="manualQuantity" class="form-input" required min="1" value="1" oninput="calcManualSaleTotal()" style="font-size:0.85rem; font-weight:800; text-align:center;">
                </div>
                <div>
                    <label style="font-size:0.72rem; font-weight:800; color:#dc2626; display:block; margin-bottom:2px;">Discount (PKR)</label>
                    <input type="number" id="manualDiscount" class="form-input" placeholder="0" value="0" oninput="calcManualSaleTotal()" style="font-size:0.85rem; font-weight:800; text-align:right; color:#dc2626;">
                </div>
            </div>

            <!-- Payment Method -->
            <div style="display:grid; grid-template-columns: 1fr 1.2fr; gap:10px; margin-bottom:14px;">
                <div>
                    <label style="font-size:0.75rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:3px;">Payment Method</label>
                    <select id="manualPaymentMethod" class="form-select" style="font-size:0.82rem;">
                        <option value="cash">Cash in Hand</option>
                        <option value="easypaisa">Easypaisa</option>
                        <option value="jazzcash">JazzCash</option>
                        <option value="bank">Bank Transfer / Raast</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:3px;">Transaction ID / Ref (Optional)</label>
                    <input type="text" id="manualTrxId" class="form-input" placeholder="e.g. EP-984210" style="font-size:0.82rem;">
                </div>
            </div>

            <!-- Total Banner -->
            <div style="background:#f1f5f9; border:1.5px solid #cbd5e1; border-radius:8px; padding:10px 14px; display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <span style="font-size:0.82rem; font-weight:800; color:#334155;">NET TOTAL TO PAY:</span>
                <span id="manualTotalDisplay" style="font-size:1.3rem; font-weight:900; color:#059669;">PKR 0</span>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="closeManualSaleModal()">Cancel</button>
                <button type="submit" class="pos-btn pos-btn-primary" style="font-weight:800; background:#2563eb;" id="btnSubmitManualSale">
                    <i class="fa-solid fa-check-circle"></i> Create Sale &amp; Generate Invoice
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Tab Switcher Controller
function switchSalesTab(tabName) {
    const tabSales = document.getElementById('tabContentSales');
    const tabReturns = document.getElementById('tabContentReturns');
    const tabDeleted = document.getElementById('tabContentDeleted');

    const btnSales = document.getElementById('tabBtnSales');
    const btnReturns = document.getElementById('tabBtnReturns');
    const btnDeleted = document.getElementById('tabBtnDeleted');

    // Hide all
    if (tabSales) tabSales.style.display = 'none';
    if (tabReturns) tabReturns.style.display = 'none';
    if (tabDeleted) tabDeleted.style.display = 'none';

    // Reset buttons
    [btnSales, btnReturns, btnDeleted].forEach(b => {
        if (b) {
            b.className = 'pos-btn pos-btn-outline';
        }
    });

    if (tabName === 'returns') {
        if (tabReturns) tabReturns.style.display = 'block';
        if (btnReturns) btnReturns.className = 'pos-btn pos-btn-primary';
    } else if (tabName === 'deleted') {
        if (tabDeleted) tabDeleted.style.display = 'block';
        if (btnDeleted) btnDeleted.className = 'pos-btn pos-btn-primary';
    } else {
        if (tabSales) tabSales.style.display = 'block';
        if (btnSales) btnSales.className = 'pos-btn pos-btn-primary';
    }
}

// Sales Page Logic Controller
let currentViewingInvoice = null;

// Filter Sales Table Function
function filterSalesTable() {
    const q = document.getElementById('salesSearchInput').value.toLowerCase().trim();
    const st = document.getElementById('salesStatusFilter').value;
    const pm = document.getElementById('salesPaymentFilter').value;
    const rows = document.querySelectorAll('.sale-row');
    let visibleCount = 0;

    rows.forEach(r => {
        const inv = r.getAttribute('data-invoice') || '';
        const cust = r.getAttribute('data-customer') || '';
        const phone = r.getAttribute('data-phone') || '';
        const cashier = r.getAttribute('data-cashier') || '';
        const items = r.getAttribute('data-items') || '';
        const rowStatus = r.getAttribute('data-status') || '';
        const rowPayment = r.getAttribute('data-payment') || '';

        const matchesQuery = !q || inv.includes(q) || cust.includes(q) || phone.includes(q) || cashier.includes(q) || items.includes(q);
        const matchesStatus = (st === 'all') || (rowStatus === st);
        const matchesPayment = (pm === 'all') || (rowPayment === pm);

        if (matchesQuery && matchesStatus && matchesPayment) {
            r.style.display = '';
            visibleCount++;
        } else {
            r.style.display = 'none';
        }
    });

    const emptyRow = document.getElementById('emptySalesRow');
    if (emptyRow) {
        emptyRow.style.display = visibleCount === 0 ? '' : 'none';
    }
}

function resetSalesFilters() {
    document.getElementById('salesSearchInput').value = '';
    document.getElementById('salesStatusFilter').value = 'all';
    document.getElementById('salesPaymentFilter').value = 'all';
    filterSalesTable();
}

// -------------------------------------------------------------
// 1. INVOICE RECEIPT MODAL & PRINTING
// -------------------------------------------------------------
window.openInvoiceDetailsModal = function(invoiceId) {
    const modal = document.getElementById('invoiceDetailsModal');
    const printArea = document.getElementById('invoicePrintArea');
    if (!modal) return;

    printArea.innerHTML = '<div style="text-align:center; padding:25px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading invoice details...</div>';
    modal.style.display = 'flex';

    fetch(`../backend/sales.php?id=${encodeURIComponent(invoiceId)}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                currentViewingInvoice = res.data;
                window.currentActiveInvoiceId = res.data.id;
                window.currentActiveInvoicePhone = res.data.customerPhone || '';
                renderThermalReceiptHtml(res.data);
            } else {
                printArea.innerHTML = '<div style="color:red; text-align:center; padding:20px;">Could not load invoice details.</div>';
            }
        })
        .catch(err => {
            printArea.innerHTML = '<div style="color:red; text-align:center; padding:20px;">Network error loading invoice.</div>';
        });
};

window.viewInvoice = window.openInvoiceDetailsModal;

function closeInvoiceDetailsModal() {
    const modal = document.getElementById('invoiceDetailsModal');
    if (modal) modal.style.display = 'none';
}

function renderThermalReceiptHtml(data) {
    const printArea = document.getElementById('invoicePrintArea');
    if (!printArea) return;

    const isRefunded = (data.status || '').toLowerCase() === 'refunded';
    const items = data.items || [];
    const createdDate = data.createdAt ? new Date(data.createdAt).toLocaleString() : new Date().toLocaleString();

    let itemsHtml = '';
    items.forEach(it => {
        const qty = it.qty || 1;
        const price = Number(it.sellingPrice || it.price || 0);
        const lineTotal = Number(it.lineTotal || (qty * price));
        itemsHtml += `
            <tr>
                <td style="padding:2px 0; vertical-align:top; font-size:11px;">
                    <strong>${escapeHtml(it.name || 'Item')}</strong>
                    <div style="font-size:10px; color:#444;">${qty} x PKR ${price.toLocaleString()}</div>
                </td>
                <td style="padding:2px 0; vertical-align:top; text-align:right; font-weight:bold; font-size:11px;">
                    PKR ${lineTotal.toLocaleString()}
                </td>
            </tr>
        `;
    });

    printArea.innerHTML = `
        <div style="text-align:center; font-weight:bold; font-size:14px; letter-spacing:0.5px;">SAFDAR MOBILE STORE</div>
        <div style="text-align:center; font-size:10px;">Opp. Patt Bazar, Eidgah Road, Main Bazar Hangu</div>
        <div style="text-align:center; font-size:10px; font-weight:bold;">WhatsApp / Helpline: 0333 9688007</div>
        <div style="border-top:1px dashed #000; margin:6px 0;"></div>
        
        <div style="text-align:center; font-weight:bold; font-size:12px; letter-spacing:1px;">
            ${isRefunded ? '*** RETURN / REFUND RECEIPT ***' : '*** SALE INVOICE ***'}
        </div>
        <div style="text-align:center; font-weight:bold; font-size:13px; letter-spacing:1px; margin:2px 0;">
            ${data.invoiceNo || 'INV-0000'}
        </div>
        <div style="border-top:1px dashed #000; margin:6px 0;"></div>

        <table style="width:100%; font-size:10.5px; border-collapse:collapse;">
            <tr><td style="color:#555;">Date/Time:</td><td style="text-align:right; font-weight:bold;">${createdDate}</td></tr>
            <tr><td style="color:#555;">Customer:</td><td style="text-align:right; font-weight:bold;">${escapeHtml(data.customerName || 'Walk-in Customer')}</td></tr>
            ${data.customerPhone ? `<tr><td style="color:#555;">Mobile No:</td><td style="text-align:right;">${escapeHtml(data.customerPhone)}</td></tr>` : ''}
            <tr><td style="color:#555;">Cashier:</td><td style="text-align:right;">${escapeHtml(data.cashier || 'admin')}</td></tr>
            <tr><td style="color:#555;">Payment Mode:</td><td style="text-align:right; font-weight:bold;">${(data.paymentMethod || 'CASH').toUpperCase()}</td></tr>
            ${isRefunded ? `<tr><td style="color:#dc2626; font-weight:bold;">Status:</td><td style="text-align:right; font-weight:bold; color:#dc2626;">REFUNDED</td></tr>` : ''}
        </table>

        <div style="border-top:1px dashed #000; margin:6px 0;"></div>

        <table style="width:100%; border-collapse:collapse; margin-bottom:6px;">
            <thead>
                <tr style="border-bottom:1px solid #000; font-size:10px;">
                    <th style="text-align:left; padding-bottom:3px;">ITEM DESCRIPTION</th>
                    <th style="text-align:right; padding-bottom:3px;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                ${itemsHtml}
            </tbody>
        </table>

        <div style="border-top:1px dashed #000; margin:4px 0;"></div>

        <table style="width:100%; font-size:11px; border-collapse:collapse;">
            <tr><td>Subtotal:</td><td style="text-align:right;">PKR ${Number(data.subtotal || data.total).toLocaleString()}</td></tr>
            ${Number(data.discount) > 0 ? `<tr><td>Discount:</td><td style="text-align:right; color:#dc2626;">-PKR ${Number(data.discount).toLocaleString()}</td></tr>` : ''}
            <tr style="font-size:13px; font-weight:bold; border-top:1px solid #000; border-bottom:1px solid #000;">
                <td style="padding:3px 0;">TOTAL PAID:</td>
                <td style="text-align:right; padding:3px 0;">PKR ${Number(data.total).toLocaleString()}</td>
            </tr>
        </table>

        ${isRefunded && data.refundReason ? `
            <div style="background:#fee2e2; border:1px solid #fca5a5; padding:4px 6px; font-size:10px; margin-top:6px; color:#991b1b;">
                <strong>Refund Reason:</strong> ${escapeHtml(data.refundReason)}<br>
                <strong>Processed by:</strong> ${escapeHtml(data.refundedBy || 'admin')}
            </div>
        ` : ''}

        <div style="border-top:1px dashed #000; margin:6px 0;"></div>
        <div style="text-align:center; font-size:9.5px; line-height:1.3;">
            Thank you for shopping at Safdar Mobile Store!<br>
            Please check items before leaving the shop.<br>
            *** Powered by Safdar POS System ***
        </div>
    `;
}

function printCurrentInvoiceDirect() {
    if (!currentViewingInvoice) return;
    const printWin = window.open('', '_blank', 'width=420,height=600');
    if (!printWin) {
        alert('Please allow popups to print receipt.');
        return;
    }

    const printHtml = document.getElementById('invoicePrintArea').innerHTML;
    printWin.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Invoice - ${currentViewingInvoice.invoiceNo}</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: 'Courier New', Courier, monospace; font-size: 11px; line-height: 1.35; color: #000; background: #fff; padding: 6px 4px; width: 72mm; max-width: 72mm; margin: 0 auto; }
                @media print { html, body { width: 72mm !important; max-width: 72mm !important; margin: 0 auto !important; padding: 2px 2px !important; } }
            </style>
        </head>
        <body>
            ${printHtml}
        </body>
        </html>
    `);
    printWin.document.close();
    printWin.focus();
    setTimeout(() => { printWin.print(); }, 300);
}

// -------------------------------------------------------------
// 2. SALE RETURN & REFUND MODAL
// -------------------------------------------------------------
function openReturnSaleModal(saleId, invoiceNo, total) {
    document.getElementById('returnSaleId').value = saleId;
    document.getElementById('returnInvoiceNoDisplay').textContent = invoiceNo;
    document.getElementById('returnInvoiceTotalDisplay').textContent = 'PKR ' + Number(total).toLocaleString();
    document.getElementById('returnReasonSelect').value = 'Customer returned item / Change of mind';
    document.getElementById('returnReasonCustom').style.display = 'none';
    document.getElementById('returnReasonCustom').value = '';
    document.getElementById('returnRestockCheckbox').checked = true;

    const modal = document.getElementById('returnSaleModal');
    if (modal) modal.style.display = 'flex';
}

function closeReturnSaleModal() {
    const modal = document.getElementById('returnSaleModal');
    if (modal) modal.style.display = 'none';
}

function submitSaleReturn(e) {
    e.preventDefault();
    const saleId = document.getElementById('returnSaleId').value;
    const selectReason = document.getElementById('returnReasonSelect').value;
    const customReason = document.getElementById('returnReasonCustom').value.trim();
    const reason = (selectReason === 'Other' && customReason) ? customReason : selectReason;
    const restock = document.getElementById('returnRestockCheckbox').checked;

    const btn = document.getElementById('btnSubmitReturn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing Return &amp; Saving Audit Log...';

    fetch('../backend/sales.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'return_sale',
            id: saleId,
            reason: reason,
            restock: restock
        })
    })
    .then(res => res.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Confirm Return &amp; Refund';

        if (res.status === 'success') {
            alert('Sale invoice returned and refunded successfully! A permanent audit record has been saved.');
            closeReturnSaleModal();
            location.reload();
        } else {
            alert('Error: ' + (res.message || 'Could not process sale return'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Confirm Return &amp; Refund';
        alert('Network error while processing sale return.');
    });
}

// -------------------------------------------------------------
// 3. DELETE SALE INVOICE MODAL
// -------------------------------------------------------------
function openDeleteSaleModal(saleId, invoiceNo, total) {
    document.getElementById('deleteSaleId').value = saleId;
    document.getElementById('deleteInvoiceNoDisplay').textContent = '#' + invoiceNo;
    document.getElementById('deleteReasonInput').value = 'Test / Erroneous transaction removal';
    document.getElementById('deleteRestockCheckbox').checked = true;

    const modal = document.getElementById('deleteSaleModal');
    if (modal) modal.style.display = 'flex';
}

function closeDeleteSaleModal() {
    const modal = document.getElementById('deleteSaleModal');
    if (modal) modal.style.display = 'none';
}

function confirmDeleteSale() {
    const saleId = document.getElementById('deleteSaleId').value;
    const reason = document.getElementById('deleteReasonInput').value.trim();
    const restock = document.getElementById('deleteRestockCheckbox').checked;

    const btn = document.getElementById('btnConfirmDelete');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting &amp; Archiving...';

    fetch(`../backend/sales.php?id=${encodeURIComponent(saleId)}&restock=${restock ? 1 : 0}&reason=${encodeURIComponent(reason)}`, {
        method: 'DELETE'
    })
    .then(res => res.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-trash-can"></i> Yes, Delete &amp; Archive';

        if (res.status === 'success') {
            alert('Sale invoice deleted successfully! Full audit record archived in Deleted Invoices Log.');
            closeDeleteSaleModal();
            location.reload();
        } else {
            alert('Error: ' + (res.message || 'Could not delete invoice'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-trash-can"></i> Yes, Delete &amp; Archive';
        alert('Network error while deleting invoice.');
    });
}

// -------------------------------------------------------------
// 4. ADD MANUAL SALE MODAL
// -------------------------------------------------------------
function openManualSaleModal() {
    const modal = document.getElementById('addManualSaleModal');
    const form = document.getElementById('manualSaleForm');
    if (!modal) return;

    form.reset();
    document.getElementById('manualCustName').value = 'Walk-in Customer';
    document.getElementById('manualQuantity').value = '1';
    document.getElementById('manualDiscount').value = '0';
    calcManualSaleTotal();
    modal.style.display = 'flex';
}

function closeManualSaleModal() {
    const modal = document.getElementById('addManualSaleModal');
    if (modal) modal.style.display = 'none';
}

function onManualProductSelect() {
    const sel = document.getElementById('manualProductSelect');
    if (!sel || !sel.value) return;

    const opt = sel.options[sel.selectedIndex];
    const name = opt.getAttribute('data-name');
    const price = parseFloat(opt.getAttribute('data-price')) || 0;

    document.getElementById('manualItemName').value = name;
    document.getElementById('manualUnitPrice').value = price;
    calcManualSaleTotal();
}

function calcManualSaleTotal() {
    const price = parseFloat(document.getElementById('manualUnitPrice').value) || 0;
    const qty = parseInt(document.getElementById('manualQuantity').value) || 1;
    const disc = parseFloat(document.getElementById('manualDiscount').value) || 0;

    const subtotal = price * qty;
    const total = Math.max(0, subtotal - disc);

    document.getElementById('manualTotalDisplay').textContent = 'PKR ' + total.toLocaleString();
}

function submitManualSale(e) {
    e.preventDefault();
    const custName = document.getElementById('manualCustName').value.trim();
    const custPhone = document.getElementById('manualCustPhone').value.trim();
    const prodId = document.getElementById('manualProductSelect').value;
    const itemName = document.getElementById('manualItemName').value.trim();
    const unitPrice = parseFloat(document.getElementById('manualUnitPrice').value) || 0;
    const qty = parseInt(document.getElementById('manualQuantity').value) || 1;
    const discount = parseFloat(document.getElementById('manualDiscount').value) || 0;
    const paymentMethod = document.getElementById('manualPaymentMethod').value;
    const trxId = document.getElementById('manualTrxId').value.trim();

    if (!itemName || unitPrice <= 0) {
        alert('Please enter a valid item name and price.');
        return;
    }

    const items = [{
        id: prodId || ('srv-manual-' + Date.now()),
        name: itemName,
        sellingPrice: unitPrice,
        costPrice: 0,
        qty: qty,
        isService: !prodId
    }];

    const btn = document.getElementById('btnSubmitManualSale');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating Sale...';

    fetch('../backend/sales.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            customerName: custName,
            customerPhone: custPhone,
            items: items,
            discount: discount,
            paymentMethod: paymentMethod,
            trxId: trxId
        })
    })
    .then(res => res.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check-circle"></i> Create Sale &amp; Generate Invoice';

        if (res.status === 'success') {
            alert('Sale invoice created successfully!');
            closeManualSaleModal();
            location.reload();
        } else {
            alert('Error: ' + (res.message || 'Could not create sale'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check-circle"></i> Create Sale &amp; Generate Invoice';
        alert('Network error creating sale invoice.');
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Auto-open invoice modal if URL has ?invoice=... or ?id=...
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const inv = urlParams.get('invoice') || urlParams.get('id') || urlParams.get('open');
    if (inv) {
        window.openInvoiceDetailsModal(inv);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
