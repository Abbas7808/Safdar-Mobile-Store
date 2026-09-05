<?php
$currentPage = 'cctv';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$cctv = get_json_file('cctv') ?? [];
$cctvReturns = get_json_file('cctv_returns') ?? [];
$cctvDeleted = get_json_file('cctv_deleted') ?? [];
$cctvCatalog = get_json_file('cctv_catalog') ?? [];
$currentUser = get_session_user();
$isSuperAdmin = ($currentUser['role'] ?? '') === 'super_admin';

// KPI Metrics Calculation
$totalProjects = count($cctv);
$totalRevenue = 0;
$totalLaborProfit = 0;
$totalCost = 0;
$totalDue = 0;
$totalCreditAmount = 0;
$activeCount = 0;
$inProgressCount = 0;
$returnedCount = count($cctvReturns);
$refundedAmount = 0;

foreach ($cctvReturns as $cr) {
    $refundedAmount += floatval($cr['refundAmount'] ?? 0);
}

// 1. Calculate Wholesale Cost from CCTV Products added in Products Catalog / Inventory
$allProducts = get_json_file('products');
$cctvInventoryCost = 0;
$cctvProductsCount = 0;

if (is_array($allProducts)) {
    foreach ($allProducts as $pr) {
        $cat = strtolower($pr['category'] ?? $pr['categoryId'] ?? '');
        $subCat = strtolower($pr['subCategory'] ?? '');
        $name = strtolower($pr['name'] ?? '');
        $brand = strtolower($pr['brand'] ?? '');
        
        $isCctv = ($cat === 'cctv' || $cat === 'security' || $cat === 'cameras' || 
                   strpos($cat, 'cctv') !== false || strpos($subCat, 'cctv') !== false ||
                   strpos($name, 'cctv') !== false || strpos($name, 'camera') !== false ||
                   strpos($name, 'dvr') !== false || strpos($name, 'nvr') !== false ||
                   in_array($brand, ['hikvision', 'dahua', 'uniview', 'unv', 'cp plus', 'ezviz', 'imou']));
                   
        if ($isCctv) {
            $cost = floatval($pr['costPrice'] ?? $pr['wholesale_price'] ?? $pr['wholesalePrice'] ?? 0);
            $stock = max(1, intval($pr['stock'] ?? 1));
            $cctvInventoryCost += ($cost * $stock);
            $cctvProductsCount++;
        }
    }
}

// 2. Calculate Project Installation Revenue, Equipment Cost, and Credit Amount
$projectEquipmentCost = 0;
foreach ($cctv as $p) {
    $tot = floatval($p['totalBill'] ?? 0);
    $adv = floatval($p['advancePaid'] ?? 0);
    $labor = floatval($p['laborFee'] ?? 0);
    $eq = floatval($p['equipmentCost'] ?? 0);
    $credit = floatval($p['creditAmount'] ?? 0);
    $due = max(0, $tot - $adv);

    if ($credit <= 0 && $due > 0) {
        $credit = $due;
    }

    // Sum custom items cost if present
    if (!empty($p['customItems']) && is_array($p['customItems'])) {
        $customSum = 0;
        foreach ($p['customItems'] as $ci) {
            $customSum += floatval($ci['amount'] ?? ((floatval($ci['qty'] ?? 1)) * (floatval($ci['price'] ?? 0))));
        }
        if ($customSum > 0) {
            $eq = max($eq, $customSum);
        }
    }

    if ($eq <= 0 && $tot > 0) {
        $eq = max(0, $tot - $labor);
    }

    $st = strtolower($p['status'] ?? 'installed');

    if ($st === 'returned' || $st === 'cancelled') {
        continue;
    }

    $totalRevenue += $tot;
    $projectEquipmentCost += $eq;
    $totalLaborProfit += $labor;
    $totalDue += $due;
    $totalCreditAmount += $credit;

    if ($st === 'installed' || $st === 'active') {
        $activeCount++;
    } elseif ($st === 'in_progress' || $st === 'pending') {
        $inProgressCount++;
    }
}

// Cost Amount: CCTV Wholesale Product Cost + Project Equipment Materials Cost
$totalCost = $cctvInventoryCost + $projectEquipmentCost;
if ($totalCost <= 0 && $cctvInventoryCost > 0) {
    $totalCost = $cctvInventoryCost;
} elseif ($totalCost <= 0 && $projectEquipmentCost > 0) {
    $totalCost = $projectEquipmentCost;
}

$totalGrossMargin = max(0, $totalRevenue - $totalCost);
if ($totalGrossMargin <= 0 && $totalLaborProfit > 0) {
    $totalGrossMargin = $totalLaborProfit;
}
$marginPct = ($totalRevenue > 0) ? round(($totalGrossMargin / $totalRevenue) * 100, 1) . '%' : '0%';
?>

<style>
/* Responsive fix to eliminate horizontal scrollbar across entire CCTV page */
html, body {
    overflow-x: hidden !important;
    max-width: 100vw;
}
.pos-main, .pos-content {
    overflow-x: hidden !important;
    max-width: 100%;
}
.data-table-wrap {
    overflow-x: hidden !important;
    max-width: 100%;
}
#cctvTable, .data-table {
    width: 100% !important;
    min-width: 0 !important;
    table-layout: auto;
}
#cctvTable th, #cctvTable td,
.data-table th, .data-table td {
    padding: 8px 7px !important;
    font-size: 0.8rem;
    vertical-align: middle;
}
@media (max-width: 1200px) {
    .data-table-wrap {
        overflow-x: auto !important;
    }
}
</style>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content" style="padding:16px 20px; box-sizing:border-box;">
        
        <!-- Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
            <div>
                <h1 style="font-family:var(--pos-font-heading); font-size:1.5rem; font-weight:900; color:var(--pos-text); margin:0; display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-video" style="color:var(--pos-red);"></i> CCTV Security &amp; Surveillance Systems
                </h1>
                <p style="color:var(--pos-text-muted); font-size:0.82rem; margin:3px 0 0 0;">
                    Complete tracking of camera installations, commercial sites, billing, materials &amp; service logs
                </p>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="button" class="pos-btn pos-btn-primary" onclick="openCctvModal()" style="font-weight:800; font-size:0.85rem; padding:8px 16px;">
                    <i class="fa-solid fa-camera"></i> + Book New CCTV Installation
                </button>
            </div>
        </div>

        <!-- 8 KPI Summary Cards -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:10px; margin-bottom:18px;">
            <!-- 1. Total Sites -->
            <div class="pos-card" style="padding:12px 10px; border-left:4px solid var(--pos-red); display:flex; align-items:center; gap:10px; cursor:pointer;" onclick="switchCctvTab('active')">
                <div style="background:rgba(215,25,32,0.1); width:38px; height:38px; flex-shrink:0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--pos-red); font-size:1.15rem;">
                    <i class="fa-solid fa-video"></i>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:0.68rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Total Sites</div>
                    <div style="font-size:1.18rem; font-weight:900; color:var(--pos-text); line-height:1.2;"><?php echo $totalProjects; ?> Sites</div>
                    <div style="font-size:0.65rem; color:#059669; font-weight:700; white-space:nowrap;"><?php echo $activeCount; ?> Act | <?php echo $inProgressCount; ?> Prog</div>
                </div>
            </div>

            <!-- 2. Total Amount -->
            <div class="pos-card" style="padding:12px 10px; border-left:4px solid #10b981; display:flex; align-items:center; gap:10px;">
                <div style="background:rgba(16,185,129,0.1); width:38px; height:38px; flex-shrink:0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#059669; font-size:1.15rem;">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:0.68rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Total Amount</div>
                    <div style="font-size:1.18rem; font-weight:900; color:#059669; line-height:1.2;">PKR <?php echo number_format($totalRevenue); ?></div>
                    <div style="font-size:0.65rem; color:var(--pos-text-muted); font-weight:700; white-space:nowrap;">Gross Bill Sum</div>
                </div>
            </div>

            <!-- 3. Cost Amount -->
            <div class="pos-card" style="padding:12px 10px; border-left:4px solid #0284c7; display:flex; align-items:center; gap:10px;">
                <div style="background:rgba(2,132,199,0.1); width:38px; height:38px; flex-shrink:0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#0284c7; font-size:1.15rem;">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:0.68rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Cost Amount</div>
                    <div style="font-size:1.18rem; font-weight:900; color:#0284c7; line-height:1.2;">PKR <?php echo number_format($totalCost); ?></div>
                    <div style="font-size:0.65rem; color:var(--pos-text-muted); font-weight:700; white-space:nowrap;"><?php echo ($cctvInventoryCost > 0) ? $cctvProductsCount . ' CCTV Items Wholesale' : 'Wholesale / Materials Cost'; ?></div>
                </div>
            </div>

            <!-- 4. Gross Margin -->
            <div class="pos-card" style="padding:12px 10px; border-left:4px solid #8b5cf6; display:flex; align-items:center; gap:10px;">
                <div style="background:rgba(139,92,246,0.1); width:38px; height:38px; flex-shrink:0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#8b5cf6; font-size:1.15rem;">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:0.68rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Gross Margin</div>
                    <div style="font-size:1.18rem; font-weight:900; color:#8b5cf6; line-height:1.2;">PKR <?php echo number_format($totalGrossMargin); ?></div>
                    <div style="font-size:0.65rem; color:#8b5cf6; font-weight:800; white-space:nowrap;"><?php echo $marginPct; ?> Margin</div>
                </div>
            </div>

            <!-- 5. Credit Amount -->
            <div class="pos-card" style="padding:12px 10px; border-left:4px solid #6366f1; display:flex; align-items:center; gap:10px;">
                <div style="background:rgba(99,102,241,0.1); width:38px; height:38px; flex-shrink:0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#6366f1; font-size:1.15rem;">
                    <i class="fa-solid fa-file-invoice"></i>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:0.68rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Credit Amount</div>
                    <div style="font-size:1.18rem; font-weight:900; color:#6366f1; line-height:1.2;">PKR <?php echo number_format($totalCreditAmount); ?></div>
                    <div style="font-size:0.65rem; color:#4f46e5; font-weight:700; white-space:nowrap;">Udhaar / Credit Terms</div>
                </div>
            </div>

            <!-- 6. Debit / Remaining -->
            <div class="pos-card" style="padding:12px 10px; border-left:4px solid #e11d48; display:flex; align-items:center; gap:10px;">
                <div style="background:rgba(225,29,72,0.1); width:38px; height:38px; flex-shrink:0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#e11d48; font-size:1.15rem;">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:0.68rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Debit / Due</div>
                    <div style="font-size:1.18rem; font-weight:900; color:#e11d48; line-height:1.2;">PKR <?php echo number_format($totalDue); ?></div>
                    <div style="font-size:0.65rem; color:#be123c; font-weight:700; white-space:nowrap;">Khata Receivable</div>
                </div>
            </div>

            <!-- 7. Return / Refunds -->
            <div class="pos-card" style="padding:12px 10px; border-left:4px solid #f59e0b; display:flex; align-items:center; gap:10px; cursor:pointer;" onclick="switchCctvTab('returns')">
                <div style="background:rgba(245,158,11,0.1); width:38px; height:38px; flex-shrink:0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#d97706; font-size:1.15rem;">
                    <i class="fa-solid fa-rotate-left"></i>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:0.68rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Return</div>
                    <div style="font-size:1.18rem; font-weight:900; color:#d97706; line-height:1.2;">PKR <?php echo number_format($refundedAmount); ?></div>
                    <div style="font-size:0.65rem; color:#b45309; font-weight:700; white-space:nowrap;"><?php echo count($cctvReturns); ?> Returns &rarr;</div>
                </div>
            </div>

            <!-- 8. Deleted Archive -->
            <div class="pos-card" style="padding:12px 10px; border-left:4px solid #64748b; display:flex; align-items:center; gap:10px; cursor:pointer;" onclick="switchCctvTab('deleted')">
                <div style="background:rgba(100,116,139,0.1); width:38px; height:38px; flex-shrink:0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#475569; font-size:1.15rem;">
                    <i class="fa-solid fa-trash-can"></i>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:0.68rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Deleted</div>
                    <div style="font-size:1.18rem; font-weight:900; color:#475569; line-height:1.2;"><?php echo count($cctvDeleted); ?> Archived</div>
                    <div style="font-size:0.65rem; color:#64748b; font-weight:700; white-space:nowrap;">Audit Trail &rarr;</div>
                </div>
            </div>
        </div>

        <!-- 3-Tab Navigation Switcher -->
        <div style="display:flex; gap:8px; margin-bottom:16px; border-bottom:2px solid var(--pos-border); padding-bottom:10px; flex-wrap:wrap;">
            <button type="button" id="tabBtnCctvActive" class="pos-btn pos-btn-primary" onclick="switchCctvTab('active')" style="border-radius:20px; padding:6px 16px; font-size:0.82rem; font-weight:800; display:inline-flex; align-items:center; gap:6px;">
                <i class="fa-solid fa-video"></i> 1. Active CCTV Sites (<?php echo $totalProjects - $returnedCount; ?>)
            </button>
            <button type="button" id="tabBtnCctvReturns" class="pos-btn pos-btn-outline" onclick="switchCctvTab('returns')" style="border-radius:20px; padding:6px 16px; font-size:0.82rem; font-weight:800; display:inline-flex; align-items:center; gap:6px; border-color:#d97706; color:#d97706;">
                <i class="fa-solid fa-rotate-left"></i> 2. Returns Log (<?php echo $returnedCount; ?>)
            </button>
            <button type="button" id="tabBtnCctvDeleted" class="pos-btn pos-btn-outline" onclick="switchCctvTab('deleted')" style="border-radius:20px; padding:6px 16px; font-size:0.82rem; font-weight:800; display:inline-flex; align-items:center; gap:6px; border-color:#dc2626; color:#dc2626;">
                <i class="fa-solid fa-trash-can"></i> 3. Deleted Archive (<?php echo count($cctvDeleted); ?>)
            </button>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 1: ACTIVE CCTV INSTALLATIONS TABLE                        -->
        <!-- ============================================================= -->
        <div id="tabContentCctvActive">
            <!-- Filter Bar -->
            <div class="pos-card" style="padding:10px 14px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <input type="text" id="cctvSearchInput" class="form-input" placeholder="Search Client, Project #, Site, Brand..." oninput="filterCctvTable()" style="min-width:220px; font-size:0.82rem; height:35px;">
                    
                    <select id="cctvStatusFilter" class="form-select" onchange="filterCctvTable()" style="font-size:0.82rem; height:35px;">
                        <option value="all">All Job Statuses</option>
                        <option value="installed">Installed &amp; Active</option>
                        <option value="in_progress">In Progress</option>
                        <option value="maintenance">Maintenance</option>
                    </select>

                    <select id="cctvBrandFilter" class="form-select" onchange="filterCctvTable()" style="font-size:0.82rem; height:35px;">
                        <option value="all">All Camera Brands</option>
                        <option value="hikvision">Hikvision</option>
                        <option value="dahua">Dahua</option>
                        <option value="cp plus">CP Plus</option>
                        <option value="ezviz">Ezviz / IMOU</option>
                        <option value="uniview">Uniview</option>
                    </select>
                </div>

                <div>
                    <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="resetCctvFilters()" style="font-size:0.75rem; padding:4px 10px;">
                        <i class="fa-solid fa-arrow-rotate-right"></i> Reset
                    </button>
                </div>
            </div>

            <!-- Projects Table (Zero horizontal scrollbar, 100% responsive) -->
            <div class="data-table-wrap pos-card" style="padding:0;">
                <table class="data-table" id="cctvTable" style="margin:0;">
                    <thead>
                        <tr>
                            <th style="width:105px;">Project #</th>
                            <th>Client &amp; Contact</th>
                            <th>Site &amp; Type</th>
                            <th>Hardware Setup</th>
                            <th>Billing (PKR)</th>
                            <th>Status &amp; Tech</th>
                            <th>Install Date</th>
                            <th style="text-align:right; width:190px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cctv)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:35px; color:var(--pos-text-muted);">
                                    <i class="fa-solid fa-video" style="font-size:2.2rem; color:#cbd5e1; margin-bottom:8px; display:block;"></i>
                                    No CCTV project records found. Click "+ Book New CCTV Installation" above to record client projects.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cctv as $p): 
                                $st = strtolower($p['status'] ?? 'installed');
                                $isRet = ($st === 'returned' || $st === 'cancelled');
                                $tot = floatval($p['totalBill'] ?? 0);
                                $adv = floatval($p['advancePaid'] ?? 0);
                                $due = max(0, $tot - $adv);

                                $stBg = '#f1f5f9'; $stColor = '#475569';
                                if ($st === 'installed' || $st === 'active') { $stBg = '#ecfdf5'; $stColor = '#059669'; }
                                elseif ($st === 'in_progress' || $st === 'pending') { $stBg = '#eff6ff'; $stColor = '#2563eb'; }
                                elseif ($st === 'maintenance') { $stBg = '#fffbeb'; $stColor = '#d97706'; }
                                elseif ($isRet) { $stBg = '#fef2f2'; $stColor = '#dc2626'; }
                            ?>
                                <tr class="cctv-row" 
                                    data-project="<?php echo strtolower($p['projectNo'] ?? ''); ?>"
                                    data-client="<?php echo strtolower($p['clientName'] ?? ''); ?>"
                                    data-phone="<?php echo strtolower($p['clientPhone'] ?? ''); ?>"
                                    data-address="<?php echo strtolower($p['siteAddress'] ?? ''); ?>"
                                    data-brand="<?php echo strtolower($p['cameraBrand'] ?? ''); ?>"
                                    data-status="<?php echo htmlspecialchars($st); ?>"
                                    style="border-bottom:1px solid var(--pos-border); <?php echo $isRet ? 'opacity:0.85; background:#fff5f5;' : ''; ?>">
                                    
                                    <td>
                                        <strong style="color:var(--pos-red); font-family:monospace; font-size:0.8rem; display:block;">
                                            <?php echo htmlspecialchars($p['projectNo'] ?? 'CCTV-000'); ?>
                                        </strong>
                                        <div style="font-size:0.68rem; color:var(--pos-text-muted);">
                                            <?php echo date('M d, Y', strtotime($p['createdAt'] ?? 'now')); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <strong style="font-size:0.85rem; color:var(--pos-text); display:block;">
                                            <?php echo htmlspecialchars($p['clientName']); ?>
                                        </strong>
                                        <div style="font-size:0.72rem; color:#059669; font-weight:700;">
                                            <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($p['clientPhone']); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div style="font-size:0.8rem; font-weight:700; color:var(--pos-text); line-height:1.25;">
                                            <?php echo htmlspecialchars($p['siteAddress'] ?? 'Main Bazar Hangu'); ?>
                                        </div>
                                        <span class="status-badge" style="background:#f1f5f9; font-size:0.65rem; padding:2px 5px; text-transform:capitalize; margin-top:2px; display:inline-block;">
                                            <?php echo htmlspecialchars($p['siteType'] ?? 'Commercial'); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div style="font-size:0.8rem; font-weight:800; color:#1e293b;">
                                            <?php echo htmlspecialchars($p['cameraBrand']); ?> (<?php echo intval($p['cameraCount'] ?? 4); ?> Cams)
                                        </div>
                                        <div style="font-size:0.72rem; color:#0284c7; font-weight:700; margin-top:2px;">
                                            <i class="fa-solid fa-hard-drive"></i> <?php echo htmlspecialchars($p['storageHdd'] ?? 'Surveillance HDD'); ?>
                                        </div>
                                        <div style="font-size:0.68rem; color:var(--pos-text-muted); margin-top:2px;">
                                            <i class="fa-solid fa-server"></i> <?php echo htmlspecialchars(!empty($p['dvrModel']) ? (($p['dvrQty'] ?? 1) > 1 ? ($p['dvrQty'] . 'x ') : '') . $p['dvrModel'] : (intval($p['dvrChannels'] ?? 4) . 'CH DVR')); ?>
                                            &bull; <i class="fa-solid fa-bolt"></i> <?php echo htmlspecialchars(!empty($p['powerSupply']) ? (($p['powerSupplyQty'] ?? 1) > 1 ? ($p['powerSupplyQty'] . 'x ') : '') . $p['powerSupply'] : '12V Central Supply'); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div>Total: <strong style="color:#059669; font-size:0.85rem;">PKR <?php echo number_format($tot); ?></strong></div>
                                        <div style="font-size:0.68rem; color:#64748b;">Adv: PKR <?php echo number_format($adv); ?></div>
                                        <?php if ($isRet): ?>
                                             <div style="font-size:0.7rem; font-weight:800; color:#dc2626;">Refunded</div>
                                        <?php elseif ($due > 0): ?>
                                            <div style="font-size:0.7rem; font-weight:800; color:#dc2626;">Due: PKR <?php echo number_format($due); ?></div>
                                        <?php else: ?>
                                            <div style="font-size:0.68rem; font-weight:800; color:#059669;">Balance Paid</div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="status-badge" style="background:<?php echo $stBg; ?>; color:<?php echo $stColor; ?>; font-size:0.68rem; font-weight:800; text-transform:uppercase; padding:2px 6px;">
                                            <?php echo htmlspecialchars($p['status']); ?>
                                        </span>
                                        <div style="font-size:0.72rem; font-weight:800; color:#1d4ed8; margin-top:3px;">
                                            <i class="fa-solid fa-user-gear"></i> <?php echo htmlspecialchars($p['technician'] ?? 'Safdar'); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div style="font-size:0.75rem; font-weight:800; color:var(--pos-text);">
                                            <?php echo date('M d, Y', strtotime($p['installedDate'] ?? $p['createdAt'] ?? 'now')); ?>
                                        </div>
                                    </td>

                                    <td style="text-align:right; white-space:nowrap;">
                                        <div style="display:inline-flex; gap:3px; align-items:center; justify-content:flex-end;">
                                            <!-- Formal A4 Invoice Button -->
                                            <button type="button" class="pos-btn pos-btn-sm" title="View &amp; Print A4 Formal Invoice" onclick="openCctvInvoiceModal('<?php echo htmlspecialchars($p['id'], ENT_QUOTES); ?>')" style="padding:4px 8px; font-size:0.7rem; background:#0f172a; color:#fff; font-weight:800; border-radius:5px; border:none; cursor:pointer;">
                                                <i class="fa-solid fa-file-invoice"></i> Invoice
                                            </button>

                                            <!-- Thermal Slip -->
                                            <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" title="Print Mini Slip" onclick="printCctvSlip('<?php echo htmlspecialchars($p['id'], ENT_QUOTES); ?>')" style="padding:4px 6px; font-size:0.7rem;">
                                                <i class="fa-solid fa-print"></i>
                                            </button>

                                            <!-- Send WhatsApp -->
                                            <button type="button" class="pos-btn pos-btn-sm" title="Send WhatsApp Confirmation" onclick="window.sendCctvWhatsApp('<?php echo htmlspecialchars($p['id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['clientPhone'] ?? '', ENT_QUOTES); ?>')" style="padding:4px 6px; font-size:0.7rem; background:#ecfdf5; color:#059669; border:1px solid #a7f3d0;">
                                                <i class="fa-brands fa-whatsapp"></i>
                                            </button>

                                            <!-- Return / Cancel Button -->
                                            <?php if (!$isRet): ?>
                                                <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" title="Return / Cancel Project" onclick="openReturnCctvModal('<?php echo htmlspecialchars($p['id'], ENT_QUOTES); ?>')" style="padding:4px 6px; font-size:0.7rem; color:#d97706; border-color:#fed7aa;">
                                                    <i class="fa-solid fa-rotate-left"></i>
                                                </button>
                                            <?php endif; ?>

                                            <!-- Edit Project -->
                                            <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" title="Edit Project" onclick="editCctvProject('<?php echo htmlspecialchars($p['id'], ENT_QUOTES); ?>')" style="padding:4px 6px; font-size:0.7rem; color:#2563eb; border-color:#bfdbfe;">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>

                                            <!-- Delete Project -->
                                            <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" title="Delete Project" onclick="deleteCctvProject('<?php echo htmlspecialchars($p['id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['projectNo'] ?? '', ENT_QUOTES); ?>')" style="padding:4px 6px; font-size:0.7rem; color:#dc2626; border-color:#fecaca;">
                                                <i class="fa-solid fa-trash-can"></i>
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
        <!-- TAB 2: RETURNS & CANCELLATIONS LOG                           -->
        <!-- ============================================================= -->
        <div id="tabContentCctvReturns" style="display:none;">
            <div class="pos-card" style="padding:12px 16px; margin-bottom:14px; background:#fffbeb; border:1px solid #fed7aa;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-weight:800; color:#92400e; font-size:0.9rem;">
                        <i class="fa-solid fa-rotate-left" style="color:#d97706;"></i> CCTV Returns &amp; Cancellations Record Log
                    </div>
                    <div style="font-size:0.78rem; color:#b45309; font-weight:700;">
                        Total Refunds Issued: PKR <?php echo number_format($refundedAmount); ?>
                    </div>
                </div>
            </div>

            <div class="data-table-wrap pos-card" style="padding:0;">
                <table class="data-table" style="margin:0;">
                    <thead>
                        <tr style="background:#fffbeb;">
                            <th style="width:110px;">Project #</th>
                            <th>Client &amp; Site</th>
                            <th>Setup Package</th>
                            <th>Original Bill</th>
                            <th>Refund Issued</th>
                            <th>Equipment Status</th>
                            <th>Return Reason</th>
                            <th>Processed By &amp; Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cctvReturns)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:30px; color:var(--pos-text-muted);">No returned / cancelled CCTV records yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($cctvReturns as $cr): ?>
                                <tr style="border-bottom:1px solid #fed7aa;">
                                    <td>
                                        <strong style="color:var(--pos-red); font-family:monospace;"><?php echo htmlspecialchars($cr['projectNo'] ?? ''); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($cr['clientName'] ?? 'Client'); ?></strong>
                                        <div style="font-size:0.68rem; color:#64748b;"><?php echo htmlspecialchars($cr['siteAddress'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <strong style="font-size:0.8rem;"><?php echo htmlspecialchars($cr['cameraBrand'] ?? 'CCTV'); ?></strong>
                                    </td>
                                    <td>
                                        <strong>PKR <?php echo number_format(floatval($cr['totalBill'] ?? 0)); ?></strong>
                                    </td>
                                    <td>
                                        <strong style="color:#dc2626;">PKR <?php echo number_format(floatval($cr['refundAmount'] ?? 0)); ?></strong>
                                    </td>
                                    <td>
                                        <?php if (!empty($cr['equipmentReturned'])): ?>
                                            <span class="status-badge" style="background:#ecfdf5; color:#059669; font-size:0.68rem;">Returned to Stock</span>
                                        <?php else: ?>
                                            <span class="status-badge" style="background:#fef2f2; color:#dc2626; font-size:0.68rem;">Not Returned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size:0.78rem; color:#78350f; font-weight:600;"><?php echo htmlspecialchars($cr['returnReason'] ?? 'Cancelled'); ?></span>
                                    </td>
                                    <td>
                                        <div style="font-weight:700; font-size:0.75rem;"><?php echo htmlspecialchars($cr['returnedBy'] ?? 'Admin'); ?></div>
                                        <div style="font-size:0.65rem; color:#64748b;"><?php echo date('M d, Y h:i A', strtotime($cr['returnedAt'] ?? 'now')); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 3: DELETED CCTV PROJECTS AUDIT ARCHIVE                   -->
        <!-- ============================================================= -->
        <div id="tabContentCctvDeleted" style="display:none;">
            <div class="pos-card" style="padding:12px 16px; margin-bottom:14px; background:#fef2f2; border:1px solid #fecaca;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-weight:800; color:#991b1b; font-size:0.9rem;">
                        <i class="fa-solid fa-trash-can" style="color:#dc2626;"></i> Permanent CCTV Deletion Audit Trail
                    </div>
                    <div style="font-size:0.78rem; color:#dc2626; font-weight:700;">
                        <?php echo count($cctvDeleted); ?> Archived Deleted Projects
                    </div>
                </div>
            </div>

            <div class="data-table-wrap pos-card" style="padding:0;">
                <table class="data-table" style="margin:0;">
                    <thead>
                        <tr style="background:#fef2f2;">
                            <th style="width:110px;">Project #</th>
                            <th>Client &amp; Site</th>
                            <th>Total Bill</th>
                            <th>Deletion Reason</th>
                            <th>Deleted By</th>
                            <th>Date &amp; Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cctvDeleted)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--pos-text-muted);">No deleted CCTV records in audit archive.</td></tr>
                        <?php else: ?>
                            <?php foreach ($cctvDeleted as $cd): ?>
                                <tr style="border-bottom:1px solid #fecaca;">
                                    <td>
                                        <strong style="color:#dc2626; font-family:monospace;"><?php echo htmlspecialchars($cd['projectNo'] ?? ''); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($cd['clientName'] ?? 'Client'); ?></strong>
                                        <div style="font-size:0.68rem; color:#64748b;"><?php echo htmlspecialchars($cd['siteAddress'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <strong>PKR <?php echo number_format(floatval($cd['totalBill'] ?? 0)); ?></strong>
                                    </td>
                                    <td>
                                        <span style="font-weight:700; color:#991b1b; font-size:0.78rem;"><?php echo htmlspecialchars($cd['reason'] ?? 'Deleted by Super Admin'); ?></span>
                                    </td>
                                    <td>
                                        <span style="font-weight:800; color:#334155; font-size:0.78rem;"><?php echo htmlspecialchars($cd['deletedBy'] ?? 'Admin'); ?></span>
                                    </td>
                                    <td>
                                        <span style="font-size:0.72rem; color:#64748b;"><?php echo date('M d, Y h:i A', strtotime($cd['deletedAt'] ?? 'now')); ?></span>
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
<!-- CCTV INSTALLATION & PROJECT BOOKING MODAL                     -->
<!-- ============================================================= -->
<style>
/* Guarantee all form controls in CCTV modal and tables have full vertical visibility and never clip text */
#cctvModal select.form-select,
#cctvModal input.form-input,
#cctvModal textarea.form-input,
.storage-drive-row select,
.storage-drive-row input,
#cctvCustomItemsTable select,
#cctvCustomItemsTable input {
    height: 40px !important;
    min-height: 40px !important;
    padding: 6px 12px !important;
    line-height: normal !important;
    box-sizing: border-box !important;
    font-family: inherit !important;
    vertical-align: middle !important;
    font-size: 0.88rem !important;
}

#cctvModal select.form-select option {
    padding: 6px 10px;
    font-size: 0.88rem;
}

#cctvCustomItemsTable select.custom-item-subcat,
#cctvCustomItemsTable select.custom-item-unit,
#cctvCustomItemsTable input.custom-item-name,
#cctvCustomItemsTable input.custom-item-qty,
#cctvCustomItemsTable input.custom-item-price,
#cctvCustomItemsTable input.custom-item-amount {
    height: 38px !important;
    min-height: 38px !important;
    padding: 4px 8px !important;
    font-size: 0.85rem !important;
}
</style>
<div class="pos-modal-overlay" id="cctvModal" style="display:none; z-index:99999;">
    <div class="pos-modal" style="width:96vw; max-width:1120px; padding:24px 28px; max-height:94vh; overflow-y:auto; border-radius:16px; box-shadow:0 25px 60px rgba(0,0,0,0.35);">
        <div class="pos-modal-header" style="border-bottom:1.5px solid var(--pos-border); padding-bottom:12px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h3 class="pos-modal-title" style="margin:0; font-size:1.35rem; color:var(--pos-text); font-weight:900; display:flex; align-items:center; gap:10px;">
                    <span style="background:rgba(220,38,38,0.12); color:var(--pos-red); width:36px; height:36px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; font-size:1.1rem;">
                        <i class="fa-solid fa-video"></i>
                    </span>
                    <span id="cctvModalTitle">Book New CCTV Installation Project</span>
                </h3>
                <p style="margin:4px 0 0 46px; font-size:0.78rem; color:var(--pos-text-muted); font-weight:500;">
                    Complete site booking with multi-HDD storage, custom DVR channels, power supply loads, manual materials table &amp; warranty management
                </p>
            </div>
            <button type="button" class="pos-modal-close" onclick="closeCctvModal()" style="font-size:1.2rem; width:34px; height:34px; border-radius:8px;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="cctvForm" onsubmit="saveCctvProject(event)">
            <input type="hidden" id="cctvId" value="">

            <!-- 1. Client Info Grid -->
            <div style="background:#ffffff; border:1.5px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:14px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                <div style="font-size:0.78rem; font-weight:900; color:#1e293b; margin-bottom:12px; text-transform:uppercase; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-user-tie" style="color:#2563eb;"></i> 1. Client &amp; Installation Location Details</span>
                    <span style="font-size:0.7rem; color:#64748b; font-weight:600; text-transform:none;">Client contact info and physical installation address</span>
                </div>
                <div style="display:grid; grid-template-columns: 1.2fr 1fr 1fr 1.8fr; gap:12px;">
                    <div>
                        <label style="font-size:0.75rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:4px;">Client / Owner Name *</label>
                        <input type="text" id="cctvClientName" class="form-input" required placeholder="e.g. Haji Gulzar Khan" style="font-size:0.88rem; padding:8px 12px; height:40px;">
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:4px;">Contact Phone / WhatsApp *</label>
                        <input type="text" id="cctvClientPhone" class="form-input" required placeholder="e.g. 0333 9123456" style="font-size:0.88rem; padding:8px 12px; height:40px;">
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:4px;">Site Property Type</label>
                        <select id="cctvSiteType" class="form-select" style="font-size:0.88rem; padding:8px 12px; height:40px;">
                            <option value="commercial">Commercial Plaza / Market</option>
                            <option value="residential">Home / Residential Villa</option>
                            <option value="industrial">Factory / Warehouse</option>
                            <option value="school">School / College Campus</option>
                            <option value="hospital">Hospital / Clinic</option>
                            <option value="petrol_pump">Petrol Pump / Station</option>
                            <option value="office">Corporate Office / Bank</option>
                            <option value="other">Other Site Location</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:4px;">Site Address / Location *</label>
                        <input type="text" id="cctvSiteAddress" class="form-input" required placeholder="e.g. Main Bazar Hangu near Purdil Masjid" style="font-size:0.88rem; padding:8px 12px; height:40px;">
                    </div>
                </div>
            </div>

            <!-- 2. Hardware Specifications & Assigned Technician Grid -->
            <div style="background:#ffffff; border:1.5px solid #cbd5e1; border-radius:12px; padding:16px; margin-bottom:14px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                <div style="font-size:0.78rem; font-weight:900; color:#1e293b; margin-bottom:12px; text-transform:uppercase; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-microchip" style="color:var(--pos-red);"></i> 2. System Hardware, Recorders &amp; Power Setup</span>
                    <span style="font-size:0.7rem; font-weight:600; color:#64748b; text-transform:none;">Specify camera brand, megapixel, brand DVR/NVR recorders &amp; power loads</span>
                </div>

                <!-- 1. Security Cameras Specification, Manual Unit Price & Item Discount -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label style="font-size:0.75rem; font-weight:800; color:#1e293b; display:flex; align-items:center; gap:6px; margin:0;">
                            <i class="fa-solid fa-camera" style="color:var(--pos-red);"></i> 1. Security Cameras Specification, Unit Price &amp; Discount:
                        </label>
                        <span style="font-size:0.68rem; color:#64748b; font-weight:600;">Set manual price &amp; product discount</span>
                    </div>

                    <div style="display:grid; grid-template-columns: 1.1fr 1.3fr 1.6fr; gap:10px; margin-bottom:8px;">
                        <div>
                            <label style="font-size:0.7rem; font-weight:700; color:#475569; display:block; margin-bottom:2px;">Camera Brand *</label>
                            <select id="cctvBrand" class="form-select" style="font-size:0.88rem; height:40px;" onchange="onCameraBrandChange()">
                                <option value="Hikvision">Hikvision</option>
                                <option value="Dahua">Dahua</option>
                                <option value="UNV">UNV (Uniview)</option>
                                <option value="CP Plus">CP Plus</option>
                                <option value="Ezviz / IMOU">Ezviz / IMOU (Wi-Fi)</option>
                                <option value="Tiandy">Tiandy</option>
                                <option value="Custom / Other">Custom / Other Brand</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.7rem; font-weight:700; color:#475569; display:block; margin-bottom:2px;">Megapixel / Resolution *</label>
                            <select id="cctvMegapixel" class="form-select" style="font-size:0.88rem; height:40px;" onchange="onCameraMegapixelChange()">
                                <option value="2MP (1080p Full HD)">2MP (1080p Full HD)</option>
                                <option value="3MP HD">3MP HD Clarity</option>
                                <option value="4MP 2K Quad HD">4MP 2K Quad HD</option>
                                <option value="5MP Super HD" selected>5MP Super HD (ColorVu / Full Color)</option>
                                <option value="6MP HD">6MP Ultra Clarity</option>
                                <option value="8MP 4K Ultra HD">8MP 4K Ultra HD</option>
                                <option value="12MP Enterprise 4K">12MP Enterprise 4K</option>
                                <option value="custom">+ Type Manual Megapixel...</option>
                            </select>
                            <input type="text" id="cctvMegapixelCustom" class="form-input" placeholder="e.g. 5MP ColorVu Audio or 4K IP" style="font-size:0.82rem; height:34px; margin-top:4px; display:none;" oninput="onCameraBrandChange()">
                        </div>
                        <div>
                            <label style="font-size:0.7rem; font-weight:700; color:#475569; display:block; margin-bottom:2px;">System Package Name / Model Description</label>
                            <input type="text" id="cctvSystemPackage" class="form-input" placeholder="e.g. Hikvision 4-Channel 5MP Super HD Night Vision Setup" style="font-size:0.88rem; height:40px;">
                        </div>
                    </div>

                    <!-- Camera Quantity, Unit Price & Item Discount -->
                    <div style="display:grid; grid-template-columns: 0.8fr 1.5fr 1.5fr; gap:12px; background:#ffffff; border:1px dashed #cbd5e1; border-radius:8px; padding:8px 10px; align-items:center;">
                        <div>
                            <label style="font-size:0.7rem; font-weight:800; color:#1e293b; display:block; margin-bottom:2px;">Camera Qty</label>
                            <input type="number" id="cctvCameraCount" class="form-input" value="4" min="1" max="128" style="font-size:0.9rem; height:38px; font-weight:800; text-align:center;" oninput="onCameraCountChange()">
                        </div>
                        <div>
                            <label style="font-size:0.7rem; font-weight:800; color:#059669; display:block; margin-bottom:2px;">
                                <i class="fa-solid fa-tag"></i> Price / Cam (PKR)
                            </label>
                            <input type="number" id="cctvCameraPrice" class="form-input" value="3500" min="0" step="any" placeholder="0" style="font-size:0.95rem; height:38px; font-weight:800; color:#059669; text-align:right;" oninput="calcCctvTotal()">
                        </div>
                        <div>
                            <label style="font-size:0.7rem; font-weight:800; color:#e11d48; display:block; margin-bottom:2px;">
                                <i class="fa-solid fa-tags"></i> Discount (PKR)
                            </label>
                            <input type="number" id="cctvCameraDiscount" class="form-input" value="0" min="0" step="any" placeholder="0" style="font-size:0.95rem; height:38px; font-weight:800; color:#e11d48; text-align:right; border-color:#fecdd3; background:#fff1f2;" oninput="calcCctvTotal()" title="Discount on cameras">
                        </div>
                        <input type="hidden" id="cctvCameraAmount" value="14000">
                    </div>
                </div>

                <!-- 2. DVR/NVR Unit (With Manual Unit Price & Discount) -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label style="font-size:0.75rem; font-weight:800; color:#1e293b; display:flex; align-items:center; gap:6px; margin:0;">
                            <i class="fa-solid fa-server" style="color:var(--pos-red);"></i> 2. DVR / NVR Video Recorder Unit Specification &amp; Price:
                        </label>
                        <span style="font-size:0.68rem; color:#64748b; font-weight:600;">Set manual price &amp; product discount</span>
                    </div>
                    <div style="display:grid; grid-template-columns: 1.3fr 1.5fr 0.5fr 0.5fr; gap:10px; margin-bottom:8px;">
                        <div>
                            <label style="font-size:0.7rem; font-weight:700; color:#475569; display:block; margin-bottom:2px;">Select Recorder Preset (by Brand)</label>
                            <select id="cctvDvrPreset" class="form-select" style="font-size:0.88rem; height:40px;" onchange="onDvrPresetChange()">
                                <optgroup label="Hikvision Recorders">
                                    <option value="Hikvision 4-Channel Turbo HD DVR">Hikvision 4-Channel Turbo HD DVR</option>
                                    <option value="Hikvision 8-Channel Turbo HD DVR">Hikvision 8-Channel Turbo HD DVR</option>
                                    <option value="Hikvision 16-Channel Turbo HD DVR">Hikvision 16-Channel Turbo HD DVR</option>
                                    <option value="Hikvision 32-Channel Turbo HD DVR">Hikvision 32-Channel Turbo HD DVR</option>
                                    <option value="Hikvision 4-Channel 4K AcuSense NVR">Hikvision 4-Channel 4K AcuSense NVR</option>
                                    <option value="Hikvision 8-Channel 4K AcuSense NVR">Hikvision 8-Channel 4K AcuSense NVR</option>
                                    <option value="Hikvision 16-Channel 4K AcuSense NVR">Hikvision 16-Channel 4K AcuSense NVR</option>
                                    <option value="Hikvision 32-Channel Enterprise NVR">Hikvision 32-Channel Enterprise NVR</option>
                                </optgroup>
                                <optgroup label="Dahua Recorders">
                                    <option value="Dahua 4-Channel WizSense XVR">Dahua 4-Channel WizSense XVR</option>
                                    <option value="Dahua 8-Channel WizSense XVR">Dahua 8-Channel WizSense XVR</option>
                                    <option value="Dahua 16-Channel WizSense XVR">Dahua 16-Channel WizSense XVR</option>
                                    <option value="Dahua 32-Channel WizSense XVR">Dahua 32-Channel WizSense XVR</option>
                                    <option value="Dahua 4-Channel 4K AI NVR">Dahua 4-Channel 4K AI NVR</option>
                                    <option value="Dahua 8-Channel 4K AI NVR">Dahua 8-Channel 4K AI NVR</option>
                                    <option value="Dahua 16-Channel 4K AI NVR">Dahua 16-Channel 4K AI NVR</option>
                                    <option value="Dahua 32-Channel Enterprise NVR">Dahua 32-Channel Enterprise NVR</option>
                                </optgroup>
                                <optgroup label="UNV (Uniview) Recorders">
                                    <option value="UNV 4-Channel Ultra 265 NVR">UNV 4-Channel Ultra 265 NVR</option>
                                    <option value="UNV 8-Channel Ultra 265 NVR">UNV 8-Channel Ultra 265 NVR</option>
                                    <option value="UNV 16-Channel 4K NVR">UNV 16-Channel 4K NVR</option>
                                    <option value="UNV 32-Channel Enterprise NVR">UNV 32-Channel Enterprise NVR</option>
                                </optgroup>
                                <optgroup label="CP Plus &amp; Wireless">
                                    <option value="CP Plus 4-Channel HD DVR">CP Plus 4-Channel HD DVR</option>
                                    <option value="CP Plus 8-Channel HD DVR">CP Plus 8-Channel HD DVR</option>
                                    <option value="CP Plus 16-Channel HD DVR">CP Plus 16-Channel HD DVR</option>
                                    <option value="Ezviz / IMOU 4/8-Channel Wireless NVR">Ezviz / IMOU 4/8-Channel Wireless NVR</option>
                                    <option value="No DVR / Cloud / Standalone">No DVR / Cloud / Standalone</option>
                                </optgroup>
                                <option value="custom">+ Type Custom / Manual DVR Model &rarr;</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.7rem; font-weight:700; color:#475569; display:block; margin-bottom:2px;">DVR/NVR Model / Manual Entry *</label>
                            <input type="text" id="cctvDvrModel" class="form-input" placeholder="e.g. Hikvision 4-Channel Turbo HD DVR (7204HGHI)" value="Hikvision 4-Channel Turbo HD DVR" style="font-size:0.88rem; height:40px; font-weight:700;">
                        </div>
                        <div>
                            <label style="font-size:0.7rem; font-weight:700; color:#475569; display:block; margin-bottom:2px;" title="Set quantity of DVR/NVR units">DVR Qty</label>
                            <input type="number" id="cctvDvrQty" class="form-input" value="1" min="0" max="20" style="font-size:0.88rem; height:40px; font-weight:800; text-align:center;" oninput="calcCctvTotal()">
                        </div>
                        <div>
                            <label style="font-size:0.7rem; font-weight:700; color:#475569; display:block; margin-bottom:2px;">Channels</label>
                            <input type="number" id="cctvDvrChannels" class="form-input" value="4" min="0" max="128" style="font-size:0.88rem; height:40px; font-weight:700; text-align:center;">
                        </div>
                    </div>

                    <!-- DVR Pricing Row with Discount -->
                    <div style="display:grid; grid-template-columns: 0.9fr 1.5fr 1.5fr; gap:12px; background:#ffffff; border:1px dashed #cbd5e1; border-radius:8px; padding:8px 10px; align-items:center;">
                        <div style="font-size:0.75rem; color:#475569; font-weight:700;">
                            <i class="fa-solid fa-money-bill-wave" style="color:#059669; margin-right:4px;"></i> DVR Pricing:
                        </div>
                        <div>
                            <label style="font-size:0.7rem; font-weight:800; color:#059669; display:block; margin-bottom:2px;">
                                <i class="fa-solid fa-tag"></i> Price / DVR (PKR) *
                            </label>
                            <input type="number" id="cctvDvrPrice" class="form-input" value="6500" min="0" step="any" placeholder="0" style="font-size:0.95rem; height:38px; font-weight:800; color:#059669; text-align:right;" oninput="calcCctvTotal()">
                        </div>
                        <div>
                            <label style="font-size:0.7rem; font-weight:800; color:#e11d48; display:block; margin-bottom:2px;">
                                <i class="fa-solid fa-tags"></i> Discount (PKR)
                            </label>
                            <input type="number" id="cctvDvrDiscount" class="form-input" value="0" min="0" step="any" placeholder="0" style="font-size:0.95rem; height:38px; font-weight:800; color:#e11d48; text-align:right; border-color:#fecdd3; background:#fff1f2;" oninput="calcCctvTotal()" title="Discount on DVR">
                        </div>
                        <input type="hidden" id="cctvDvrAmount" value="6500">
                    </div>
                </div>

                <!-- 3. Multi-Hard Drive / Storage Device Selector with Manual Prices & Discounts -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:6px;">
                        <label style="font-size:0.75rem; font-weight:800; color:#1e293b; display:flex; align-items:center; gap:6px; margin:0;">
                            <i class="fa-solid fa-hard-drive" style="color:#0284c7;"></i> 3. Surveillance Storage / Hard Drives (Multiple Drives + Manual Price + Discount):
                        </label>
                        <span id="cctvStorageTotalBadge" style="background:#e0f2fe; color:#0369a1; font-size:0.72rem; font-weight:800; padding:3px 10px; border-radius:12px; border:1px solid #bae6fd;">
                            1 Drive: 1TB Surveillance HDD (PKR 5,500)
                        </span>
                    </div>

                    <!-- Header Row for Drives -->
                    <div style="display:grid; grid-template-columns: 1.8fr 1.2fr 0.6fr 1fr 1fr 36px; gap:8px; padding:4px 8px; font-size:0.68rem; font-weight:800; color:#475569; background:#f1f5f9; border-radius:6px; margin-bottom:6px;">
                        <div>Drive Type / Preset</div>
                        <div>Custom Specification</div>
                        <div style="text-align:center;">Qty</div>
                        <div style="text-align:right;">Price / Drive</div>
                        <div style="text-align:right; color:#e11d48;"><i class="fa-solid fa-tag"></i> Discount</div>
                        <div style="text-align:center;">Del</div>
                    </div>

                    <!-- Dynamic Storage Rows Container -->
                    <div id="cctvStorageRowsContainer" style="display:flex; flex-direction:column; gap:8px; margin-bottom:10px;">
                        <!-- Rows injected dynamically via JS -->
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="addStorageRow()" style="font-size:0.75rem; padding:6px 14px; color:#0284c7; border-color:#bae6fd; font-weight:700; background:#fff;">
                            <i class="fa-solid fa-plus"></i> + Add Another Hard Drive / Storage Device
                        </button>
                        <input type="hidden" id="cctvStorageHdd" value="1TB Surveillance HDD">
                    </div>
                </div>

                <!-- 4. Power Supply Units & PoE Switches Multi-Row Selector with Manual Prices & Discounts -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:6px;">
                        <label style="font-size:0.75rem; font-weight:800; color:#1e293b; display:flex; align-items:center; gap:6px; margin:0;">
                            <i class="fa-solid fa-bolt" style="color:#eab308;"></i> 4. Power Supply Units, Central Boxes &amp; PoE Switches (Add Multiple Units + Discounts):
                        </label>
                        <span id="cctvPowerSupplyTotalBadge" style="background:#fef9c3; color:#854d0e; font-size:0.72rem; font-weight:800; padding:3px 10px; border-radius:12px; border:1px solid #fef08a;">
                            1 Power Unit: 12V 5A Central Supply (PKR 2,500)
                        </span>
                    </div>

                    <!-- Header Row for Power Supplies -->
                    <div style="display:grid; grid-template-columns: 1.8fr 1.2fr 0.6fr 1fr 1fr 36px; gap:8px; padding:4px 8px; font-size:0.68rem; font-weight:800; color:#475569; background:#f1f5f9; border-radius:6px; margin-bottom:6px;">
                        <div>Power Rating / PoE Model</div>
                        <div>Custom Specification</div>
                        <div style="text-align:center;">Qty</div>
                        <div style="text-align:right;">Price / Unit</div>
                        <div style="text-align:right; color:#e11d48;"><i class="fa-solid fa-tag"></i> Discount</div>
                        <div style="text-align:center;">Del</div>
                    </div>

                    <!-- Dynamic Power Supply Rows Container -->
                    <div id="cctvPowerSupplyRowsContainer" style="display:flex; flex-direction:column; gap:8px; margin-bottom:10px;">
                        <!-- Rows injected dynamically via JS -->
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="addPowerSupplyRow()" style="font-size:0.75rem; padding:6px 14px; color:#b45309; border-color:#fde68a; font-weight:700; background:#fff;">
                            <i class="fa-solid fa-plus"></i> + Add Another Power Supply / PoE Switch Unit
                        </button>
                        <input type="hidden" id="cctvPowerSupply" value="12V 5A Central Supply">
                        <input type="hidden" id="cctvPowerSupplyQty" value="1">
                        <input type="hidden" id="cctvPowerSupplyPrice" value="2500">
                        <input type="hidden" id="cctvPowerSupplyDiscount" value="0">
                        <input type="hidden" id="cctvPowerSupplyAmount" value="2500">

                        <!-- Assigned Technician -->
                        <div style="display:flex; align-items:center; gap:6px;">
                            <label style="font-size:0.72rem; font-weight:900; color:#1d4ed8; white-space:nowrap; margin:0;">
                                <i class="fa-solid fa-user-gear"></i> Assigned Technician:
                            </label>
                            <input type="text" id="cctvTechnician" class="form-input" required placeholder="e.g. Safdar Khan / Munim..." value="Safdar &amp; Munim" style="font-size:0.82rem; height:36px; font-weight:800; color:#1e293b; border-color:#3b82f6; width:180px;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Other / Custom Manual Items Table with Discount Column -->
            <div style="background:#ffffff; border:1.5px solid #86efac; border-radius:12px; padding:16px; margin-bottom:14px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <input type="checkbox" id="cctvEnableCustomItems" style="width:18px; height:18px; cursor:pointer; accent-color:#059669;" onchange="toggleCustomItemsSection()">
                        <label for="cctvEnableCustomItems" style="font-size:0.82rem; font-weight:900; color:#065f46; cursor:pointer; margin:0; text-transform:uppercase; display:flex; align-items:center; gap:8px;">
                            <i class="fa-solid fa-list-check" style="color:#059669; font-size:0.95rem;"></i> 5. Additional Accessories &amp; Manual Materials (Cables, Connectors, Cabinets)
                        </label>
                    </div>
                    <span id="cctvCustomItemsTotalBadge" style="background:#dcfce7; color:#15803d; font-size:0.75rem; font-weight:800; padding:4px 12px; border-radius:14px; border:1px solid #86efac;">
                        0 Custom Items: PKR 0
                    </span>
                </div>

                <div id="cctvCustomItemsContainer" style="display:none; margin-top:10px;">
                    <div style="font-size:0.75rem; color:#166534; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">
                        <span>Add custom accessories, conduit pipes, rack cabinets, HDMI cables, wall brackets, or specific manual items:</span>
                    </div>

                    <!-- Custom Items Table with Discount column -->
                    <div style="overflow-x:auto; background:#ffffff; border:1.5px solid #cbd5e1; border-radius:10px; margin-bottom:8px;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.8rem; min-width:800px;" id="cctvCustomItemsTable">
                            <thead>
                                <tr style="background:#f8fafc; border-bottom:2px solid #cbd5e1; color:#334155; text-align:left;">
                                    <th style="padding:9px 8px; width:35px; text-align:center; font-weight:800;">#</th>
                                    <th style="padding:9px 8px; width:190px; font-weight:800;"><i class="fa-solid fa-layer-group" style="color:#059669;"></i> Sub-Category</th>
                                    <th style="padding:9px 8px; font-weight:800;"><i class="fa-solid fa-pen-to-square" style="color:#059669;"></i> Item Name / Manual Description</th>
                                    <th style="padding:9px 8px; width:75px; text-align:center; font-weight:800;">Qty</th>
                                    <th style="padding:9px 8px; width:80px; text-align:center; font-weight:800;">Unit</th>
                                    <th style="padding:9px 8px; width:105px; text-align:right; font-weight:800;">Price / Unit</th>
                                    <th style="padding:9px 8px; width:100px; text-align:right; font-weight:800; color:#e11d48;"><i class="fa-solid fa-tag"></i> Discount</th>
                                    <th style="padding:9px 8px; width:115px; text-align:right; font-weight:800; color:#15803d;">Amount (PKR)</th>
                                    <th style="padding:9px 8px; width:40px; text-align:center; font-weight:800;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="cctvCustomItemsTbody">
                                <!-- Dynamic rows injected via JS -->
                            </tbody>
                            <tfoot>
                                <tr style="background:#f1f5f9; border-top:2px solid #cbd5e1; font-weight:800;">
                                    <td colspan="6" style="padding:9px 10px; text-align:left; color:#1e293b;">
                                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                            <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="addCustomItemRow()" style="font-size:0.75rem; padding:5px 14px; color:#059669; border-color:#86efac; background:#fff; font-weight:800;">
                                                <i class="fa-solid fa-plus"></i> + Add Item Row
                                            </button>
                                            <select id="cctvQuickPresetSelect" class="form-select" style="font-size:0.75rem; padding:4px 8px; height:32px; width:auto; font-weight:700; max-width:340px;" onchange="onQuickPresetSelected(this)">
                                                <option value="">+ Quick Add CCTV Catalog Item (65 Official Items)...</option>
                                                <?php 
                                                $groupedCctvCat = [];
                                                foreach ($cctvCatalog as $catItem) {
                                                    $grp = $catItem['subCategory'] ?? 'General Materials';
                                                    $groupedCctvCat[$grp][] = $catItem;
                                                }
                                                foreach ($groupedCctvCat as $grpName => $cList):
                                                ?>
                                                    <optgroup label="📁 <?php echo htmlspecialchars($grpName); ?>">
                                                        <?php foreach ($cList as $ci): 
                                                            $presetVal = ($ci['subCategory'] ?? 'General Materials') . '|' . $ci['name'] . '|1|' . ($ci['unit'] ?? 'Pcs') . '|' . floatval($ci['defaultPrice'] ?? 0);
                                                        ?>
                                                            <option value="<?php echo htmlspecialchars($presetVal); ?>">
                                                                [<?php echo htmlspecialchars($ci['brand']); ?>] <?php echo htmlspecialchars($ci['name']); ?> (PKR <?php echo number_format($ci['defaultPrice']); ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </td>
                                    <td style="padding:9px 10px; text-align:right; color:#475569; font-size:0.82rem;">Total Net:</td>
                                    <td style="padding:9px 10px; text-align:right; color:#059669; font-size:0.98rem; font-weight:900;" id="cctvCustomItemsTableTotal">PKR 0</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 6. Billing, Financials & Shop Owner Total Breakdown -->
            <div style="background:#ffffff; border:1.5px solid #fed7aa; border-radius:12px; padding:16px; margin-bottom:14px; box-shadow:0 1px 3px rgba(0,0,0,0.03); box-sizing:border-box; width:100%; overflow:hidden;">
                <div style="font-size:0.78rem; font-weight:900; color:#92400e; margin-bottom:12px; text-transform:uppercase; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <span><i class="fa-solid fa-coins" style="color:#d97706;"></i> 6. Project Billing, Product Discounts &amp; Payment Breakdown</span>
                    <span style="font-size:0.7rem; font-weight:600; color:#b45309;">Equipment subtotal, individual product discounts, extra discount, labor &amp; balance</span>
                </div>

                <!-- Row 1: Gross Hardware, Total Product Discounts, Extra Discount, Labor Fee, Net Total Bill -->
                <div style="display:grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap:10px; width:100%; box-sizing:border-box; margin-bottom:12px;">
                    <!-- 1. Equipment Subtotal Gross -->
                    <div style="background:#f8fafc; border:1.5px solid #cbd5e1; border-radius:10px; padding:8px 10px; box-sizing:border-box; width:100%; min-width:0;">
                        <label style="font-size:0.68rem; font-weight:800; color:#334155; display:block; margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <i class="fa-solid fa-microchip"></i> Gross Equipment
                        </label>
                        <input type="number" id="cctvEquipmentGross" class="form-input" value="0" readonly style="font-size:0.95rem; font-weight:900; padding:4px 8px; height:36px; color:#1e293b; background:#f1f5f9; width:100%; box-sizing:border-box;">
                        <input type="hidden" id="cctvEquipmentCost" value="0">
                    </div>

                    <!-- 2. Product Discounts Sum -->
                    <div style="background:#fff1f2; border:1.5px solid #fecdd3; border-radius:10px; padding:8px 10px; box-sizing:border-box; width:100%; min-width:0;">
                        <label style="font-size:0.68rem; font-weight:900; color:#be123c; display:block; margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <i class="fa-solid fa-tags"></i> Items Discount
                        </label>
                        <input type="number" id="cctvItemsDiscountTotal" class="form-input" value="0" readonly style="font-size:0.95rem; font-weight:900; color:#e11d48; background:#fff5f5; padding:4px 8px; height:36px; width:100%; box-sizing:border-box;" title="Sum of all individual product discounts">
                    </div>

                    <!-- 3. Extra Special Project Discount -->
                    <div style="background:#fff1f2; border:1.5px solid #f43f5e; border-radius:10px; padding:8px 10px; box-sizing:border-box; width:100%; min-width:0;">
                        <label style="font-size:0.68rem; font-weight:900; color:#9f1239; display:block; margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <i class="fa-solid fa-percent"></i> Extra Discount
                        </label>
                        <input type="number" id="cctvDiscount" class="form-input" value="0" min="0" step="any" oninput="calcCctvTotal()" placeholder="0" style="font-size:0.98rem; font-weight:900; color:#e11d48; background:#ffffff; padding:4px 8px; height:36px; width:100%; box-sizing:border-box; border-color:#f43f5e;">
                    </div>

                    <!-- 4. Labor / Service Fee -->
                    <div style="background:#fffbeb; border:1.5px solid #fed7aa; border-radius:10px; padding:8px 10px; box-sizing:border-box; width:100%; min-width:0;">
                        <label style="font-size:0.68rem; font-weight:800; color:#78350f; display:block; margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <i class="fa-solid fa-screwdriver-wrench"></i> Labor Fee (PKR)
                        </label>
                        <input type="number" id="cctvLaborFee" class="form-input" value="0" min="0" step="any" oninput="calcCctvTotal()" placeholder="0" style="font-size:0.98rem; font-weight:900; padding:4px 8px; height:36px; color:#d97706; width:100%; box-sizing:border-box;">
                    </div>

                    <!-- 5. Net Total Bill -->
                    <div style="background:#f0fdf4; border:1.5px solid #86efac; border-radius:10px; padding:8px 10px; box-sizing:border-box; width:100%; min-width:0;">
                        <label style="font-size:0.68rem; font-weight:900; color:#166534; display:block; margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <i class="fa-solid fa-calculator"></i> Net Total Bill (PKR)
                        </label>
                        <input type="number" id="cctvTotalBill" class="form-input" value="0" readonly style="font-size:1.05rem; font-weight:900; color:#15803d; background:#ecfdf5; padding:4px 8px; height:36px; width:100%; box-sizing:border-box;">
                    </div>
                </div>

                <!-- Row 2: Advance Payment, Remaining Payment, Credit Payment, Payment Method -->
                <div style="display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:12px; width:100%; box-sizing:border-box;">
                    <!-- 1. Advance Payment -->
                    <div style="background:#eff6ff; border:1.5px solid #bfdbfe; border-radius:10px; padding:10px 12px; box-sizing:border-box; width:100%; min-width:0;">
                        <label style="font-size:0.72rem; font-weight:900; color:#1e40af; display:block; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <i class="fa-solid fa-hand-holding-dollar"></i> Advance Payment (PKR)
                        </label>
                        <input type="number" id="cctvAdvancePaid" class="form-input" value="0" min="0" step="any" oninput="calcCctvRemaining()" placeholder="0" style="font-size:1.02rem; font-weight:900; color:#2563eb; background:#ffffff; padding:6px 10px; height:38px; width:100%; box-sizing:border-box;">
                    </div>

                    <!-- 2. Remaining Payment / Balance -->
                    <div style="background:#fef2f2; border:1.5px solid #fecaca; border-radius:10px; padding:10px 12px; box-sizing:border-box; width:100%; min-width:0;">
                        <label style="font-size:0.72rem; font-weight:900; color:#991b1b; display:block; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <i class="fa-solid fa-scale-balanced"></i> Remaining Payment (PKR)
                        </label>
                        <input type="number" id="cctvRemainingPayment" class="form-input" value="0" readonly style="font-size:1.02rem; font-weight:900; color:#dc2626; background:#fff5f5; padding:6px 10px; height:38px; width:100%; box-sizing:border-box; cursor:not-allowed;">
                    </div>

                    <!-- 3. Credit Payment (Khata) -->
                    <div style="background:#faf5ff; border:1.5px solid #e9d5ff; border-radius:10px; padding:10px 12px; box-sizing:border-box; width:100%; min-width:0;">
                        <label style="font-size:0.72rem; font-weight:900; color:#6b21a8; display:block; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <i class="fa-solid fa-book-bookmark"></i> Credit Payment (PKR)
                        </label>
                        <input type="number" id="cctvCreditAmount" class="form-input" value="0" min="0" step="any" placeholder="0" style="font-size:1.02rem; font-weight:900; color:#7c3aed; background:#ffffff; padding:6px 10px; height:38px; width:100%; box-sizing:border-box;">
                    </div>

                    <!-- 4. Payment Terms / Mode -->
                    <div style="background:#f8fafc; border:1.5px solid #cbd5e1; border-radius:10px; padding:10px 12px; box-sizing:border-box; width:100%; min-width:0;">
                        <label style="font-size:0.72rem; font-weight:800; color:#334155; display:block; margin-bottom:4px;">Payment Terms / Mode</label>
                        <select id="cctvPaymentMethod" class="form-select" style="font-size:0.85rem; font-weight:700; height:38px; padding:4px 8px; width:100%;" onchange="onPaymentMethodChange()">
                            <option value="Cash">Cash (Full / Settled)</option>
                            <option value="Credit">Credit / Udhaar (Khata)</option>
                            <option value="Partial Credit">Partial Advance + Credit</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="JazzCash / EasyPaisa">JazzCash / EasyPaisa</option>
                        </select>
                    </div>
                </div>

                <!-- Payment Terms / Remarks Sub-Row -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; box-sizing:border-box; width:100%;">
                    <div>
                        <label style="font-size:0.72rem; font-weight:800; color:#334155; display:block; margin-bottom:3px;">Credit / Khata Remarks</label>
                        <input type="text" id="cctvCreditNote" class="form-input" placeholder="e.g. Promised balance within 15 days" style="font-size:0.82rem; height:36px; padding:4px 8px;">
                    </div>
                    <div>
                        <label style="font-size:0.72rem; font-weight:800; color:#334155; display:block; margin-bottom:3px;">Credit Due Date</label>
                        <input type="date" id="cctvCreditDueDate" class="form-input" style="font-size:0.82rem; height:36px; padding:4px 8px;">
                    </div>
                </div>
            </div>

            <!-- 5. Job Status & Date Grid -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label style="font-size:0.75rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:4px;">Project Job Status</label>
                    <select id="cctvStatus" class="form-select" style="font-size:0.85rem; padding:8px 12px; height:38px;">
                        <option value="installed">Installed &amp; Active (Completed)</option>
                        <option value="in_progress">In Progress (Installation ongoing)</option>
                        <option value="maintenance">Maintenance / Servicing</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:4px;">Installation Date</label>
                    <input type="date" id="cctvInstalledDate" class="form-input" style="font-size:0.85rem; padding:8px 12px; height:38px;">
                    <input type="hidden" id="cctvWarrantyExpiry" value="">
                </div>
            </div>

            <!-- Remarks -->
            <div style="margin-bottom:16px;">
                <label style="font-size:0.75rem; font-weight:800; color:var(--pos-text); display:block; margin-bottom:4px;">Remarks</label>
                <textarea id="cctvNotes" class="form-input" rows="2" placeholder="e.g. Client requested 2 bullet cams outdoors & 2 dome indoors, mobile app configured, port forwarding done..." style="font-size:0.85rem; padding:8px 12px;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1.5px solid var(--pos-border); padding-top:14px;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="closeCctvModal()" style="padding:10px 22px; font-size:0.88rem;">Cancel</button>
                <button type="submit" class="pos-btn pos-btn-primary" id="btnSaveCctv" style="font-weight:900; font-size:0.9rem; padding:10px 26px; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-check-circle"></i> Save CCTV Project Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================= -->
<!-- CCTV RETURN / CANCELLATION MODAL                             -->
<!-- ============================================================= -->
<div class="pos-modal-overlay" id="cctvReturnModal" style="display:none; z-index:999999;">
    <div class="pos-modal" style="max-width:480px; padding:22px;">
        <div class="pos-modal-header" style="border-bottom:1.5px solid var(--pos-border); padding-bottom:10px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
            <h3 class="pos-modal-title" style="margin:0; font-size:1.15rem; color:#d97706; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-rotate-left"></i> CCTV Return / Cancellation Log
            </h3>
            <button class="pos-modal-close" onclick="closeReturnCctvModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="cctvReturnForm" onsubmit="submitReturnCctv(event)">
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
                <small style="font-size:0.7rem; color:var(--pos-text-muted);">Enter amount returned/refunded to customer</small>
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

<!-- ============================================================= -->
<!-- CCTV OFFICIAL A4 INVOICE MODAL & PREVIEW                     -->
<!-- ============================================================= -->
<div class="pos-modal-overlay" id="cctvInvoiceModal" style="display:none; z-index:999999; backdrop-filter:blur(5px); background:rgba(15,23,42,0.75);">
    <div class="pos-modal" style="max-width:860px; width:95%; max-height:94vh; overflow-y:auto; padding:24px; border-radius:14px; background:#fff;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid var(--pos-border); padding-bottom:10px; margin-bottom:16px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:38px; height:38px; border-radius:8px; background:#e11d48; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.1rem;">
                    <i class="fa-solid fa-file-invoice"></i>
                </div>
                <div>
                    <h3 style="margin:0; font-size:1.15rem; font-weight:900; color:#0f172a;">Official CCTV Project Invoice</h3>
                    <p style="margin:2px 0 0 0; font-size:0.75rem; color:var(--pos-text-muted);">Standard A4 Printable Invoice with dynamic line calculations &amp; store logo</p>
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="pos-btn pos-btn-primary" onclick="triggerCctvA4Print()" style="font-weight:800; border-radius:8px; padding:6px 16px;">
                    <i class="fa-solid fa-print"></i> Print Formal Invoice (A4)
                </button>
                <button class="pos-modal-close" onclick="closeCctvInvoiceModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>

        <!-- Renderable / Printable A4 Invoice Container -->
        <div id="cctvA4PrintableArea" style="background:#ffffff; color:#000000; font-family:Arial, Helvetica, sans-serif; border:1.5px solid #333333; padding:20px; box-sizing:border-box; font-size:12px;">
            <!-- INVOICE CENTRED HEADING -->
            <div style="text-align:center; font-size:18px; font-weight:900; text-transform:uppercase; letter-spacing:3px; margin-bottom:12px; border-bottom:2px solid #000; padding-bottom:6px;">
                Invoice
            </div>

            <!-- TOP 2-COLUMN HEADER BLOCK -->
            <div style="display:grid; grid-template-columns: 1.4fr 1fr; border:1px solid #000; margin-bottom:14px;">
                <!-- Left: Shop Info & Bill To -->
                <div style="border-right:1px solid #000; padding:10px 12px;">
                    <!-- Shop Branding with Exact Website Logo -->
                    <div style="display:flex; align-items:center; gap:12px; border-bottom:1px solid #ccc; padding-bottom:8px; margin-bottom:8px;">
                        <div style="width:58px; height:58px; flex-shrink:0;">
                            <img src="../assets/images/logo.jpg" alt="Safdar Mobile Store Logo" style="width:58px; height:58px; border-radius:50%; object-fit:contain; border:1.5px solid #d97706; display:block; background:#fff;">
                        </div>
                        <div>
                            <div style="font-size:15px; font-weight:900; text-transform:uppercase; color:#000; letter-spacing:0.5px;">SAFDAR MOBILE STORE</div>
                            <div style="font-size:10px; color:#333; font-weight:700;">CCTV Security Cameras &amp; Surveillance Solutions</div>
                            <div style="font-size:9.5px; color:#444;">Opp. Patt Bazar Eidgah Road near Purdil Masjid, Hangu</div>
                            <div style="font-size:9.5px; font-weight:800; color:#000;">Helpline / WhatsApp: 0333-9688007</div>
                        </div>
                    </div>

                    <!-- Bill To Details -->
                    <div style="font-size:11px; line-height:1.45;">
                        <strong style="font-size:11.5px; text-transform:uppercase; display:block; margin-bottom:2px; color:#000; text-decoration:underline;">Bill To:</strong>
                        <div><strong>Customer / Client:</strong> <span id="invClientName" style="font-size:12px; font-weight:bold;">Haji Gulzar Khan</span></div>
                        <div><strong>Contact / Phone:</strong> <span id="invClientPhone">0333 9123456</span></div>
                        <div><strong>Site Address:</strong> <span id="invSiteAddress">Al-Madina Shopping Plaza, Main Road Hangu</span></div>
                        <div><strong>Site Category:</strong> <span id="invSiteType" style="text-transform:capitalize;">Commercial Site</span></div>
                    </div>
                </div>

                <!-- Right: Invoice Metadata Box -->
                <div style="padding:10px 12px; display:flex; flex-direction:column; justify-content:space-between;">
                    <table style="width:100%; border-collapse:collapse; font-size:11px; line-height:1.6;">
                        <tr style="border-bottom:1px solid #ccc;">
                            <td style="padding:4px 0; font-weight:bold;">Invoice No:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:900; font-family:monospace; font-size:12px;" id="invProjectNo">CCTV-20260820-004</td>
                        </tr>
                        <tr style="border-bottom:1px solid #ccc;">
                            <td style="padding:4px 0; font-weight:bold;">Date:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:700;" id="invDate">20 Aug, 2026</td>
                        </tr>
                        <tr style="border-bottom:1px solid #ccc;">
                            <td style="padding:4px 0; font-weight:bold;">Camera Brand:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:700;" id="invBrand">Hikvision</td>
                        </tr>
                        <tr style="border-bottom:1px solid #ccc;">
                            <td style="padding:4px 0; font-weight:bold;">Resolution:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:700; color:#059669;" id="invResolution">5MP Super HD</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 0; font-weight:bold;">Assigned Technician:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:800; color:#1d4ed8;" id="invTechnician">Safdar &amp; Munim</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- MAIN ITEMS GRID TABLE -->
            <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-size:11px; margin-bottom:0;" id="invItemsTable">
                <thead>
                    <tr style="background:#f1f5f9; border-bottom:1px solid #000; text-align:center; font-weight:900; font-size:11px;">
                        <th style="border-right:1px solid #000; padding:6px 4px; width:5%;">#</th>
                        <th style="border-right:1px solid #000; padding:6px 8px; width:45%; text-align:left;">Item name</th>
                        <th style="border-right:1px solid #000; padding:6px 4px; width:10%;">Quantity</th>
                        <th style="border-right:1px solid #000; padding:6px 4px; width:10%;">Unit</th>
                        <th style="border-right:1px solid #000; padding:6px 6px; width:14%; text-align:right;">Price/ Unit</th>
                        <th style="padding:6px 8px; width:16%; text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody id="invItemsTbody">
                    <!-- Dynamic Rows Injected Here -->
                </tbody>
                <tfoot>
                    <tr style="border-top:1px solid #000; border-bottom:1px solid #000; font-weight:900; background:#f8fafc;">
                        <td style="border-right:1px solid #000; padding:6px 4px; text-align:center;"></td>
                        <td style="border-right:1px solid #000; padding:6px 8px; text-transform:uppercase;">Total</td>
                        <td style="border-right:1px solid #000; padding:6px 4px; text-align:center;" id="invTotalQty">16</td>
                        <td style="border-right:1px solid #000; padding:6px 4px; text-align:center;">Pcs</td>
                        <td style="border-right:1px solid #000; padding:6px 6px; text-align:right;">—</td>
                        <td style="padding:6px 8px; text-align:right; font-size:12px; font-weight:900;" id="invTableTotalAmount">PKR 0</td>
                    </tr>
                </tfoot>
            </table>

            <!-- BOTTOM SECTION: WORDS, REMARKS, SIGNATURES & AMOUNTS -->
            <div style="display:grid; grid-template-columns: 1.4fr 1fr; border:1px solid #000; border-top:none; margin-top:0;">
                <!-- Left: Words, Remarks & Signatures -->
                <div style="border-right:1px solid #000; padding:10px 12px; display:flex; flex-direction:column; justify-content:space-between;">
                    <div>
                        <div style="font-size:11px; margin-bottom:8px;">
                            <strong>Invoice Amount In Words:</strong><br>
                            <span id="invAmountInWords" style="font-weight:bold; font-style:italic; color:#111; font-size:11.5px;">Rupees Zero Only</span>
                        </div>

                        <div id="invRemarksBox" style="font-size:10px; color:#222; border-top:1px dashed #ccc; padding-top:6px; margin-bottom:12px; line-height:1.4;">
                            <strong>Remarks:</strong> <span id="invRemarksText">Installation completed and tested successfully.</span>
                        </div>
                    </div>

                    <!-- Signatures Box -->
                    <div style="display:flex; justify-content:space-between; align-items:flex-end; border-top:1px solid #ccc; padding-top:16px; margin-top:10px;">
                        <div style="text-align:center; font-size:10px; font-weight:bold;">
                            <div style="border-top:1px solid #000; width:130px; margin-bottom:3px;"></div>
                            Customer Signature
                        </div>
                        <div style="text-align:center; font-size:10px; font-weight:bold;">
                            <div style="border-top:1px solid #000; width:150px; margin-bottom:3px;"></div>
                            Authorized Stamp &amp; Signature
                        </div>
                    </div>
                </div>

                <!-- Right: Amounts Summary Box -->
                <div style="padding:0;">
                    <div style="background:#f1f5f9; padding:6px 10px; font-weight:900; font-size:11px; text-transform:uppercase; border-bottom:1px solid #000;">
                        Amounts:
                    </div>
                    <table style="width:100%; border-collapse:collapse; font-size:11px;">
                        <tr style="border-bottom:1px solid #000;">
                            <td style="padding:6px 10px; font-weight:bold; border-right:1px solid #000;">Sub Total</td>
                            <td style="padding:6px 10px; text-align:right; font-weight:700;" id="invSubTotal">PKR 0</td>
                        </tr>
                        <tr style="border-bottom:1px solid #000; background:#f8fafc;">
                            <td style="padding:6px 10px; font-weight:900; border-right:1px solid #000;">Total</td>
                            <td style="padding:6px 10px; text-align:right; font-weight:900; font-size:12px; color:#000;" id="invGrandTotal">PKR 0</td>
                        </tr>
                        <tr style="border-bottom:1px solid #000;">
                            <td style="padding:6px 10px; font-weight:bold; border-right:1px solid #000;">Advance Received</td>
                            <td style="padding:6px 10px; text-align:right; font-weight:700; color:#059669;" id="invReceived">PKR 0</td>
                        </tr>
                        <tr style="background:#fef2f2; font-weight:900;">
                            <td style="padding:6px 10px; font-weight:900; border-right:1px solid #000; color:#991b1b;">Balance Due</td>
                            <td style="padding:6px 10px; text-align:right; font-weight:900; font-size:12.5px; color:#dc2626;" id="invBalance">PKR 0</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchCctvTab(tab) {
    document.getElementById('tabContentCctvActive').style.display = tab === 'active' ? 'block' : 'none';
    document.getElementById('tabContentCctvReturns').style.display = tab === 'returns' ? 'block' : 'none';
    document.getElementById('tabContentCctvDeleted').style.display = tab === 'deleted' ? 'block' : 'none';

    document.getElementById('tabBtnCctvActive').className = tab === 'active' ? 'pos-btn pos-btn-primary' : 'pos-btn pos-btn-outline';
    document.getElementById('tabBtnCctvReturns').className = tab === 'returns' ? 'pos-btn pos-btn-primary' : 'pos-btn pos-btn-outline';
    document.getElementById('tabBtnCctvDeleted').className = tab === 'deleted' ? 'pos-btn pos-btn-primary' : 'pos-btn pos-btn-outline';
}

function filterCctvTable() {
    const q = document.getElementById('cctvSearchInput').value.toLowerCase().trim();
    const st = document.getElementById('cctvStatusFilter').value;
    const br = document.getElementById('cctvBrandFilter').value.toLowerCase();
    const rows = document.querySelectorAll('.cctv-row');
    let visible = 0;

    rows.forEach(r => {
        const proj = r.getAttribute('data-project') || '';
        const client = r.getAttribute('data-client') || '';
        const phone = r.getAttribute('data-phone') || '';
        const addr = r.getAttribute('data-address') || '';
        const brand = r.getAttribute('data-brand') || '';
        const status = r.getAttribute('data-status') || '';

        const matchesQ = !q || proj.includes(q) || client.includes(q) || phone.includes(q) || addr.includes(q) || brand.includes(q);
        const matchesSt = (st === 'all') || (status === st);
        const matchesBr = (br === 'all') || (brand.includes(br));

        if (matchesQ && matchesSt && matchesBr) {
            r.style.display = '';
            visible++;
        } else {
            r.style.display = 'none';
        }
    });

    const empty = document.getElementById('emptyCctvRow');
    if (empty) empty.style.display = visible === 0 ? '' : 'none';
}

function resetCctvFilters() {
    document.getElementById('cctvSearchInput').value = '';
    document.getElementById('cctvStatusFilter').value = 'all';
    document.getElementById('cctvBrandFilter').value = 'all';
    filterCctvTable();
}

// Dynamic Storage Manager for 1 or More Hard Drives at Same Site
const storageOptions = [
    { value: '1TB Surveillance HDD', label: '1TB Surveillance HDD (WD Purple / SkyHawk)', defaultPrice: 5500 },
    { value: '2TB Surveillance HDD', label: '2TB Surveillance HDD (WD Purple / SkyHawk)', defaultPrice: 9000 },
    { value: '4TB Surveillance HDD', label: '4TB Surveillance HDD (WD Purple / SkyHawk)', defaultPrice: 16500 },
    { value: '6TB Surveillance HDD', label: '6TB Surveillance HDD (WD Purple / SkyHawk)', defaultPrice: 24000 },
    { value: '8TB Enterprise Surveillance HDD', label: '8TB Enterprise Surveillance HDD', defaultPrice: 32000 },
    { value: '10TB Enterprise Surveillance HDD', label: '10TB Enterprise Surveillance HDD', defaultPrice: 40000 },
    { value: '12TB Enterprise Surveillance HDD', label: '12TB Enterprise Surveillance HDD', defaultPrice: 52000 },
    { value: '16TB Enterprise Surveillance HDD', label: '16TB Enterprise Surveillance HDD', defaultPrice: 70000 },
    { value: '500GB HDD', label: '500GB Surveillance HDD', defaultPrice: 3000 },
    { value: '128GB High Endurance MicroSD', label: '128GB High Endurance MicroSD Card', defaultPrice: 2500 },
    { value: '256GB High Endurance MicroSD', label: '256GB High Endurance MicroSD Card', defaultPrice: 4500 },
    { value: '512GB SSD', label: '512GB Solid State Drive (SSD)', defaultPrice: 7000 },
    { value: '1TB NVMe / SATA SSD', label: '1TB NVMe / SATA High Speed SSD', defaultPrice: 11000 },
    { value: 'No HDD / Existing Storage', label: 'No HDD / Client Existing Drive', defaultPrice: 0 },
    { value: 'custom', label: 'Custom / Manual Storage Entry...', defaultPrice: 0 }
];

function getSuggestedHddPrice(type) {
    if (!type) return 5500;
    const found = storageOptions.find(o => o.value === type);
    if (found && found.defaultPrice !== undefined) return found.defaultPrice;
    const t = type.toLowerCase();
    if (t.includes('500gb')) return 3000;
    if (t.includes('1tb')) return 5500;
    if (t.includes('2tb')) return 9000;
    if (t.includes('4tb')) return 16500;
    if (t.includes('6tb')) return 24000;
    if (t.includes('8tb')) return 32000;
    if (t.includes('10tb')) return 40000;
    if (t.includes('12tb')) return 52000;
    if (t.includes('16tb')) return 70000;
    if (t.includes('no hdd')) return 0;
    return 5500;
}

function createStorageRowHtml(type = '1TB Surveillance HDD', qty = 1, customText = '', price = null, discount = 0, amount = null) {
    const isCustom = !storageOptions.some(o => o.value === type && o.value !== 'custom') || type === 'custom';
    const selectedVal = isCustom ? 'custom' : type;
    const manualVal = isCustom ? (customText || type) : '';
    const unitPrice = (price !== null && price !== undefined && !isNaN(price)) ? price : getSuggestedHddPrice(type);
    const disc = (discount !== null && discount !== undefined && !isNaN(discount)) ? parseFloat(discount) : 0;
    const lineAmount = (amount !== null && amount !== undefined && !isNaN(amount) && amount > 0) ? amount : Math.max(0, (qty * unitPrice) - disc);

    let optionsHtml = '';
    storageOptions.forEach(opt => {
        optionsHtml += `<option value="${opt.value}" ${opt.value === selectedVal ? 'selected' : ''}>${opt.label}</option>`;
    });

    return `
        <div class="storage-drive-row" style="display:grid; grid-template-columns: 1.8fr 1.2fr 0.6fr 1fr 1fr 36px; gap:8px; align-items:center; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:6px 8px;">
            <div>
                <select class="form-select storage-type-select" style="font-size:0.82rem; padding:4px 6px; height:36px;" onchange="onStorageRowTypeChange(this)">
                    ${optionsHtml}
                </select>
            </div>
            <div>
                <input type="text" class="form-input storage-custom-input" placeholder="Custom Spec..." value="${manualVal.replace(/"/g, '&quot;')}" style="font-size:0.82rem; padding:4px 6px; height:36px; display:${isCustom ? 'block' : 'none'};" oninput="updateStorageSummary()">
            </div>
            <div>
                <input type="number" class="form-input storage-qty-input" value="${qty || 1}" min="1" max="20" style="font-size:0.85rem; font-weight:800; text-align:center; padding:4px 2px; height:36px;" oninput="onStorageInputChange(this)" title="Storage Quantity">
            </div>
            <div>
                <input type="number" class="form-input storage-price-input" value="${unitPrice}" min="0" step="any" placeholder="Price" style="font-size:0.85rem; font-weight:800; color:#059669; text-align:right; padding:4px 6px; height:36px;" oninput="onStorageInputChange(this)" title="Price per Drive">
            </div>
            <div>
                <input type="number" class="form-input storage-discount-input" value="${disc}" min="0" step="any" placeholder="0" style="font-size:0.85rem; font-weight:800; color:#e11d48; text-align:right; padding:4px 6px; height:36px; background:#fff1f2; border-color:#fecdd3;" oninput="onStorageInputChange(this)" title="Discount on this drive">
            </div>
            <input type="hidden" class="storage-amount-input" value="${lineAmount}">
            <div style="text-align:center;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="removeStorageRow(this)" style="padding:4px 6px; color:#ef4444; border-color:#fecaca; font-size:0.8rem; border-radius:6px; height:36px; width:36px; display:inline-flex; align-items:center; justify-content:center;" title="Remove this hard drive">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </div>
        </div>
    `;
}

function onStorageInputChange(inputElem) {
    const row = inputElem.closest('.storage-drive-row');
    if (!row) return;
    const qty = parseInt(row.querySelector('.storage-qty-input').value) || 0;
    const price = parseFloat(row.querySelector('.storage-price-input').value) || 0;
    const disc = parseFloat(row.querySelector('.storage-discount-input').value) || 0;
    const amtInput = row.querySelector('.storage-amount-input');
    if (amtInput) amtInput.value = Math.max(0, Math.round((qty * price) - disc));
    updateStorageSummary();
    calcCctvTotal();
}

function onStorageRowTypeChange(selectElem) {
    const row = selectElem.closest('.storage-drive-row');
    const customInput = row.querySelector('.storage-custom-input');
    const priceInput = row.querySelector('.storage-price-input');
    if (selectElem.value === 'custom') {
        customInput.style.display = 'block';
        customInput.focus();
    } else {
        customInput.style.display = 'none';
        customInput.value = '';
        if (priceInput) {
            priceInput.value = getSuggestedHddPrice(selectElem.value);
        }
    }
    onStorageInputChange(selectElem);
}

function addStorageRow(type = '2TB Surveillance HDD', qty = 1, customText = '', price = null, discount = 0, amount = null) {
    const container = document.getElementById('cctvStorageRowsContainer');
    const div = document.createElement('div');
    div.innerHTML = createStorageRowHtml(type, qty, customText, price, discount, amount);
    container.appendChild(div.firstElementChild);
    updateStorageSummary();
    calcCctvTotal();
}

function removeStorageRow(btn) {
    const rows = document.querySelectorAll('.storage-drive-row');
    if (rows.length <= 1) {
        alert('At least one storage device row is required.');
        return;
    }
    const row = btn.closest('.storage-drive-row');
    if (row) {
        row.remove();
        updateStorageSummary();
        calcCctvTotal();
    }
}

function getStorageDrivesData() {
    const rows = document.querySelectorAll('.storage-drive-row');
    const drives = [];
    rows.forEach(r => {
        const select = r.querySelector('.storage-type-select');
        const custom = r.querySelector('.storage-custom-input');
        const qty = parseInt(r.querySelector('.storage-qty-input').value) || 1;
        const price = parseFloat(r.querySelector('.storage-price-input').value) || 0;
        const discount = parseFloat(r.querySelector('.storage-discount-input').value) || 0;
        const amount = parseFloat(r.querySelector('.storage-amount-input').value) || Math.max(0, (qty * price) - discount);
        let type = select ? select.value : 'Surveillance Drive';
        if (type === 'custom') {
            type = custom.value.trim() || 'Surveillance Drive';
        }
        drives.push({ type: type, qty: qty, price: price, discount: discount, amount: amount });
    });
    return drives;
}

function updateStorageSummary() {
    const drives = getStorageDrivesData();
    const parts = [];
    let driveCount = 0;
    let driveTotal = 0;
    
    drives.forEach(d => {
        driveCount += d.qty;
        driveTotal += (d.amount || Math.max(0, (d.qty * d.price) - (d.discount || 0)));
        parts.push((d.qty > 1 ? `${d.qty}x ` : '') + d.type);
    });

    const summaryText = parts.length > 0 ? parts.join(', ') : '1TB Surveillance HDD';
    const hddInput = document.getElementById('cctvStorageHdd');
    if (hddInput) hddInput.value = summaryText;

    const badge = document.getElementById('cctvStorageTotalBadge');
    if (badge) {
        badge.textContent = `${driveCount} Drive${driveCount > 1 ? 's' : ''}: ${summaryText} (PKR ${driveTotal.toLocaleString()})`;
    }
}

function renderStorageRows(drives) {
    const container = document.getElementById('cctvStorageRowsContainer');
    container.innerHTML = '';
    if (!drives || drives.length === 0) {
        addStorageRow('1TB Surveillance HDD', 1, '', 5500, 0, 5500);
        return;
    }
    drives.forEach(d => {
        addStorageRow(d.type, d.qty, d.customText || '', d.price, d.discount || 0, d.amount);
    });
}

// -------------------------------------------------------------
// DYNAMIC POWER SUPPLY / POE SWITCH MULTI-ROW MANAGER
// -------------------------------------------------------------
const powerSupplyOptions = [
    { value: '12V 5A Central Supply', label: '12V 5 Ampere (Central Box - 4 Cams)', defaultPrice: 2500 },
    { value: '12V 10A Central Supply', label: '12V 10 Ampere (Central Box - 8 Cams)', defaultPrice: 3500 },
    { value: '12V 15A Heavy Duty Box', label: '12V 15 Ampere (Heavy Duty Box - 12-16 Cams)', defaultPrice: 5000 },
    { value: '12V 20A Metal Box w/ Fan', label: '12V 20 Ampere (Metal Box w/ Fan - 16-24 Cams)', defaultPrice: 7500 },
    { value: '12V 30A Industrial Supply', label: '12V 30 Ampere (Industrial Box - 32 Cams)', defaultPrice: 11000 },
    { value: '12V 2A Individual Adapter', label: '12V 2 Ampere (Individual Adapter / Wi-Fi)', defaultPrice: 750 },
    { value: 'PoE Switch 4-Port (48V / 65W)', label: 'PoE Switch 4-Port (48V / 65W PoE+)', defaultPrice: 5500 },
    { value: 'PoE Switch 8-Port (48V / 120W)', label: 'PoE Switch 8-Port (48V / 120W PoE+)', defaultPrice: 9500 },
    { value: 'PoE Switch 16-Port (48V / 250W)', label: 'PoE Switch 16-Port (48V / 250W PoE+)', defaultPrice: 18000 },
    { value: 'PoE Switch 24-Port (48V / 370W)', label: 'PoE Switch 24-Port (48V / 370W PoE+)', defaultPrice: 28000 },
    { value: 'Solar / DC UPS Battery Unit', label: 'Solar / DC UPS Backup (12V 20Ah / 30Ah)', defaultPrice: 15000 },
    { value: 'No Power Supply / Existing', label: 'No Power Supply / Client Existing Unit', defaultPrice: 0 },
    { value: 'custom', label: 'Custom / Manual Power Supply...', defaultPrice: 0 }
];

function getSuggestedPsuPrice(type) {
    if (!type) return 2500;
    const found = powerSupplyOptions.find(o => o.value === type);
    if (found && found.defaultPrice !== undefined) return found.defaultPrice;
    const t = type.toLowerCase();
    if (t.includes('10a')) return 3500;
    if (t.includes('15a')) return 5000;
    if (t.includes('20a')) return 7500;
    if (t.includes('30a')) return 11000;
    if (t.includes('poe switch 4')) return 5500;
    if (t.includes('poe switch 8')) return 9500;
    if (t.includes('poe switch 16')) return 18000;
    if (t.includes('poe switch 24')) return 28000;
    if (t.includes('solar')) return 15000;
    if (t.includes('2a')) return 750;
    if (t.includes('no power')) return 0;
    return 2500;
}

function createPowerSupplyRowHtml(type = '12V 5A Central Supply', qty = 1, customText = '', price = null, discount = 0, amount = null) {
    const isCustom = !powerSupplyOptions.some(o => o.value === type && o.value !== 'custom') || type === 'custom';
    const selectedVal = isCustom ? 'custom' : type;
    const manualVal = isCustom ? (customText || type) : '';
    const unitPrice = (price !== null && price !== undefined && !isNaN(price)) ? price : getSuggestedPsuPrice(type);
    const disc = (discount !== null && discount !== undefined && !isNaN(discount)) ? parseFloat(discount) : 0;
    const lineAmount = (amount !== null && amount !== undefined && !isNaN(amount) && amount > 0) ? amount : Math.max(0, (qty * unitPrice) - disc);

    let optionsHtml = '';
    powerSupplyOptions.forEach(opt => {
        optionsHtml += `<option value="${opt.value}" ${opt.value === selectedVal ? 'selected' : ''}>${opt.label}</option>`;
    });

    return `
        <div class="psu-unit-row" style="display:grid; grid-template-columns: 1.8fr 1.2fr 0.6fr 1fr 1fr 36px; gap:8px; align-items:center; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:6px 8px;">
            <div>
                <select class="form-select psu-type-select" style="font-size:0.82rem; padding:4px 6px; height:36px;" onchange="onPowerSupplyRowTypeChange(this)">
                    ${optionsHtml}
                </select>
            </div>
            <div>
                <input type="text" class="form-input psu-custom-input" placeholder="Custom Spec..." value="${manualVal.replace(/"/g, '&quot;')}" style="font-size:0.82rem; padding:4px 6px; height:36px; display:${isCustom ? 'block' : 'none'};" oninput="updatePowerSupplySummary()">
            </div>
            <div>
                <input type="number" class="form-input psu-qty-input" value="${qty || 1}" min="1" max="20" style="font-size:0.85rem; font-weight:800; text-align:center; padding:4px 2px; height:36px;" oninput="onPowerSupplyInputChange(this)" title="Power Supply Qty">
            </div>
            <div>
                <input type="number" class="form-input psu-price-input" value="${unitPrice}" min="0" step="any" placeholder="Price" style="font-size:0.85rem; font-weight:800; color:#059669; text-align:right; padding:4px 6px; height:36px;" oninput="onPowerSupplyInputChange(this)" title="Price per Unit">
            </div>
            <div>
                <input type="number" class="form-input psu-discount-input" value="${disc}" min="0" step="any" placeholder="0" style="font-size:0.85rem; font-weight:800; color:#e11d48; text-align:right; padding:4px 6px; height:36px; background:#fff1f2; border-color:#fecdd3;" oninput="onPowerSupplyInputChange(this)" title="Discount on this power supply">
            </div>
            <input type="hidden" class="psu-amount-input" value="${lineAmount}">
            <div style="text-align:center;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="removePowerSupplyRow(this)" style="padding:4px 6px; color:#ef4444; border-color:#fecaca; font-size:0.8rem; border-radius:6px; height:36px; width:36px; display:inline-flex; align-items:center; justify-content:center;" title="Remove this power unit">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </div>
        </div>
    `;
}

function onPowerSupplyInputChange(inputElem) {
    const row = inputElem.closest('.psu-unit-row');
    if (!row) return;
    const qty = parseInt(row.querySelector('.psu-qty-input').value) || 0;
    const price = parseFloat(row.querySelector('.psu-price-input').value) || 0;
    const disc = parseFloat(row.querySelector('.psu-discount-input').value) || 0;
    const amtInput = row.querySelector('.psu-amount-input');
    if (amtInput) amtInput.value = Math.max(0, Math.round((qty * price) - disc));
    updatePowerSupplySummary();
    calcCctvTotal();
}

function onPowerSupplyRowTypeChange(selectElem) {
    const row = selectElem.closest('.psu-unit-row');
    const customInput = row.querySelector('.psu-custom-input');
    const priceInput = row.querySelector('.psu-price-input');
    if (selectElem.value === 'custom') {
        customInput.style.display = 'block';
        customInput.focus();
    } else {
        customInput.style.display = 'none';
        customInput.value = '';
        if (priceInput) {
            priceInput.value = getSuggestedPsuPrice(selectElem.value);
        }
    }
    onPowerSupplyInputChange(selectElem);
}

function addPowerSupplyRow(type = '12V 5A Central Supply', qty = 1, customText = '', price = null, discount = 0, amount = null) {
    const container = document.getElementById('cctvPowerSupplyRowsContainer');
    const div = document.createElement('div');
    div.innerHTML = createPowerSupplyRowHtml(type, qty, customText, price, discount, amount);
    container.appendChild(div.firstElementChild);
    updatePowerSupplySummary();
    calcCctvTotal();
}

function removePowerSupplyRow(btn) {
    const rows = document.querySelectorAll('.psu-unit-row');
    if (rows.length <= 1) {
        alert('At least one power supply row is required.');
        return;
    }
    const row = btn.closest('.psu-unit-row');
    if (row) {
        row.remove();
        updatePowerSupplySummary();
        calcCctvTotal();
    }
}

function getPowerSuppliesData() {
    const rows = document.querySelectorAll('.psu-unit-row');
    const supplies = [];
    rows.forEach(r => {
        const select = r.querySelector('.psu-type-select');
        const custom = r.querySelector('.psu-custom-input');
        const qty = parseInt(r.querySelector('.psu-qty-input').value) || 1;
        const price = parseFloat(r.querySelector('.psu-price-input').value) || 0;
        const discount = parseFloat(r.querySelector('.psu-discount-input').value) || 0;
        const amount = parseFloat(r.querySelector('.psu-amount-input').value) || Math.max(0, (qty * price) - discount);
        let type = select ? select.value : '12V Central Supply';
        if (type === 'custom') {
            type = custom.value.trim() || '12V Central Supply';
        }
        supplies.push({ type: type, qty: qty, price: price, discount: discount, amount: amount });
    });
    return supplies;
}

function updatePowerSupplySummary() {
    const supplies = getPowerSuppliesData();
    const parts = [];
    let psuCount = 0;
    let psuTotal = 0;
    
    supplies.forEach(s => {
        psuCount += s.qty;
        psuTotal += (s.amount || Math.max(0, (s.qty * s.price) - (s.discount || 0)));
        parts.push((s.qty > 1 ? `${s.qty}x ` : '') + s.type);
    });

    const summaryText = parts.length > 0 ? parts.join(', ') : '12V 5A Central Supply';
    const psuInput = document.getElementById('cctvPowerSupply');
    if (psuInput) psuInput.value = summaryText;

    const badge = document.getElementById('cctvPowerSupplyTotalBadge');
    if (badge) {
        badge.textContent = `${psuCount} Power Unit${psuCount > 1 ? 's' : ''}: ${summaryText} (PKR ${psuTotal.toLocaleString()})`;
    }
}

function renderPowerSupplyRows(supplies) {
    const container = document.getElementById('cctvPowerSupplyRowsContainer');
    container.innerHTML = '';
    if (!supplies || supplies.length === 0) {
        addPowerSupplyRow('12V 5A Central Supply', 1, '', 2500, 0, 2500);
        return;
    }
    supplies.forEach(s => {
        addPowerSupplyRow(s.type, s.qty, s.customText || '', s.price, s.discount || 0, s.amount);
    });
}

function getEffectiveMegapixel() {
    const sel = document.getElementById('cctvMegapixel').value;
    if (sel === 'custom') {
        const customVal = document.getElementById('cctvMegapixelCustom').value.trim();
        return customVal || '5MP HD';
    }
    return sel || '5MP Super HD';
}

function onCameraMegapixelChange() {
    const sel = document.getElementById('cctvMegapixel').value;
    const customInput = document.getElementById('cctvMegapixelCustom');
    if (sel === 'custom') {
        customInput.style.display = 'block';
        customInput.focus();
    } else {
        customInput.style.display = 'none';
    }
    onCameraBrandChange();
}

function onCameraBrandChange() {
    const brand = document.getElementById('cctvBrand').value;
    const count = parseInt(document.getElementById('cctvCameraCount').value) || 4;
    const mp = getEffectiveMegapixel();
    const pkg = document.getElementById('cctvSystemPackage');
    
    // Format package description
    pkg.value = `${brand} ${count}-Channel ${mp} Night Vision Setup`;

    // Auto-update DVR preset according to brand & camera count
    autoSelectDvrPreset(brand, count);
}

function autoSelectDvrPreset(brand, count) {
    const preset = document.getElementById('cctvDvrPreset');
    const chanInput = document.getElementById('cctvDvrChannels');
    const model = document.getElementById('cctvDvrModel');
    const dvrPriceInput = document.getElementById('cctvDvrPrice');
    
    let targetCh = 4;
    let suggestedPrice = 6500;
    if (count > 16) { targetCh = 32; suggestedPrice = 35000; }
    else if (count > 8) { targetCh = 16; suggestedPrice = 16500; }
    else if (count > 4) { targetCh = 8; suggestedPrice = 9500; }
    else { targetCh = 4; suggestedPrice = 6500; }

    chanInput.value = targetCh;

    let dvrName = '';
    const bLower = (brand || '').toLowerCase();
    if (bLower.includes('dahua')) {
        dvrName = `Dahua ${targetCh}-Channel WizSense XVR`;
    } else if (bLower.includes('unv') || bLower.includes('uniview')) {
        dvrName = `UNV ${targetCh}-Channel Ultra 265 NVR`;
    } else if (bLower.includes('cp plus')) {
        dvrName = `CP Plus ${targetCh}-Channel HD DVR`;
    } else if (bLower.includes('ezviz') || bLower.includes('imou')) {
        dvrName = `Ezviz / IMOU 4/8-Channel Wireless NVR`;
    } else {
        dvrName = `Hikvision ${targetCh}-Channel Turbo HD DVR`;
    }

    let found = false;
    for (let opt of preset.options) {
        if (opt.value === dvrName) {
            preset.value = dvrName;
            model.value = dvrName;
            found = true;
            break;
        }
    }
    if (!found) {
        model.value = dvrName;
    }
    if (dvrPriceInput && (!parseFloat(dvrPriceInput.value) || parseFloat(dvrPriceInput.value) === 6500)) {
        dvrPriceInput.value = suggestedPrice;
    }
    calcCctvTotal();
}

function onCameraCountChange() {
    const count = parseInt(document.getElementById('cctvCameraCount').value) || 4;
    const psuRows = document.querySelectorAll('.psu-unit-row');
    if (psuRows.length === 1) {
        const firstPsuType = psuRows[0].querySelector('.psu-type-select');
        const firstPsuPrice = psuRows[0].querySelector('.psu-price-input');
        if (firstPsuType && firstPsuPrice) {
            let suggestedPsu = '12V 5A Central Supply';
            let suggestedPsuPrice = 2500;
            if (count <= 4) {
                suggestedPsu = '12V 5A Central Supply';
                suggestedPsuPrice = 2500;
            } else if (count <= 8) {
                suggestedPsu = '12V 10A Central Supply';
                suggestedPsuPrice = 3500;
            } else if (count <= 16) {
                suggestedPsu = '12V 15A Heavy Duty Box';
                suggestedPsuPrice = 5000;
            } else if (count <= 24) {
                suggestedPsu = '12V 20A Metal Box w/ Fan';
                suggestedPsuPrice = 7500;
            } else {
                suggestedPsu = '12V 30A Industrial Supply';
                suggestedPsuPrice = 11000;
            }
            firstPsuType.value = suggestedPsu;
            firstPsuPrice.value = suggestedPsuPrice;
            onPowerSupplyInputChange(firstPsuPrice);
        }
    }
    onCameraBrandChange();
}

function onDvrPresetChange() {
    const preset = document.getElementById('cctvDvrPreset').value;
    const modelInput = document.getElementById('cctvDvrModel');
    const chanInput = document.getElementById('cctvDvrChannels');
    const qtyInput = document.getElementById('cctvDvrQty');
    const dvrPriceInput = document.getElementById('cctvDvrPrice');

    if (preset === 'custom') {
        modelInput.focus();
        return;
    }
    if (preset === 'No DVR / Cloud / Standalone') {
        modelInput.value = 'Wi-Fi / Standalone Setup';
        chanInput.value = 0;
        qtyInput.value = 0;
        if (dvrPriceInput) dvrPriceInput.value = 0;
        calcCctvTotal();
        return;
    }

    modelInput.value = preset;
    let suggestedPrice = 6500;
    if (preset.includes('4-Channel') || preset.includes('4-Port')) { chanInput.value = 4; suggestedPrice = 6500; }
    else if (preset.includes('8-Channel') || preset.includes('8-Port')) { chanInput.value = 8; suggestedPrice = 9500; }
    else if (preset.includes('16-Channel') || preset.includes('16-Port')) { chanInput.value = 16; suggestedPrice = 16500; }
    else if (preset.includes('32-Channel') || preset.includes('32-Port')) { chanInput.value = 32; suggestedPrice = 35000; }
    else if (preset.includes('64-Channel')) { chanInput.value = 64; suggestedPrice = 65000; }

    if (parseInt(qtyInput.value) <= 0) {
        qtyInput.value = 1;
    }
    if (dvrPriceInput && (!parseFloat(dvrPriceInput.value) || parseFloat(dvrPriceInput.value) === 6500)) {
        dvrPriceInput.value = suggestedPrice;
    }
    calcCctvTotal();
}

// Dynamic Custom / Other Manual Items Manager with Discount Column
function toggleCustomItemsSection() {
    const isEnabled = document.getElementById('cctvEnableCustomItems').checked;
    const container = document.getElementById('cctvCustomItemsContainer');
    container.style.display = isEnabled ? 'block' : 'none';
    if (isEnabled) {
        const tbody = document.getElementById('cctvCustomItemsTbody');
        if (tbody.querySelectorAll('tr.custom-item-row').length === 0) {
            addCustomItemRow({ subCategory: 'General Materials', name: '', qty: 1, unit: 'Pcs', price: 0, discount: 0, amount: 0 });
        }
    }
}

function createCustomItemRowElement(index, item = {}) {
    const tr = document.createElement('tr');
    tr.className = 'custom-item-row';
    tr.style.borderBottom = '1px solid #e2e8f0';

    const safeName = (item.name || '').replace(/"/g, '&quot;');
    const subCat = item.subCategory || 'General Materials';
    const qty = item.qty !== undefined ? item.qty : 1;
    const unit = item.unit || 'Pcs';
    const price = item.price || 0;
    const discount = item.discount || 0;
    const amount = item.amount !== undefined ? item.amount : Math.max(0, (qty * price) - discount);

    const subCats = [
        'Cabling & Transmission',
        'Conduit Pipes & Ducting',
        'Rack Cabinets & Enclosures',
        'Junction Boxes & Waterproofing',
        'Connectors & Hardware',
        'Mounts, Poles & Brackets',
        'HDMI / VGA / Display Cables',
        'Power Supply & Battery Backup',
        'PoE Switches & Networking',
        'Fiber Optics & Media Converters',
        'Wireless Bridges & CPE',
        'General Materials'
    ];

    let subCatHtml = '';
    let isPresetSubCat = false;
    subCats.forEach(sc => {
        const sel = (sc.toLowerCase() === subCat.toLowerCase());
        if (sel) isPresetSubCat = true;
        subCatHtml += `<option value="${sc}" ${sel ? 'selected' : ''}>${sc}</option>`;
    });
    if (!isPresetSubCat && subCat) {
        subCatHtml += `<option value="${subCat.replace(/"/g, '&quot;')}" selected>${subCat}</option>`;
    }
    subCatHtml += `<option value="__custom__">+ Custom Sub-Category...</option>`;

    const units = ['Pcs', 'Meters', 'Roll', 'Box', 'Set', 'Unit', 'Pkt', 'Pair', 'Job', 'Feet', 'Kg', 'Coil'];
    let unitHtml = '';
    units.forEach(u => {
        unitHtml += `<option value="${u}" ${u.toLowerCase() === unit.toLowerCase() ? 'selected' : ''}>${u}</option>`;
    });

    const isCustomSubCatActive = (!isPresetSubCat && subCat && !subCats.includes(subCat));

    tr.innerHTML = `
        <td style="padding:8px 8px; text-align:center; font-weight:800; color:#64748b;" class="custom-item-idx">${index}</td>
        <td style="padding:8px 8px;">
            <select class="form-select custom-item-subcat" style="font-size:0.78rem; padding:4px 8px; height:34px; font-weight:700; color:#1e293b; border-color:#94a3b8;" onchange="onCustomItemSubCatChange(this)">
                ${subCatHtml}
            </select>
            <input type="text" class="form-input custom-item-subcat-custom" placeholder="Type custom sub-category..." value="${isCustomSubCatActive ? subCat.replace(/"/g, '&quot;') : ''}" style="font-size:0.75rem; padding:3px 8px; height:28px; margin-top:4px; display:${isCustomSubCatActive ? 'block' : 'none'};">
        </td>
        <td style="padding:8px 8px;">
            <input type="text" class="form-input custom-item-name" list="cctvCatalogDatalist" placeholder="e.g. Cat6 Cable Roll / HDMI 10m / BNC Jacks" value="${safeName}" style="font-size:0.82rem; padding:6px 10px; height:34px; font-weight:600;" oninput="onCustomItemNameChange(this)">
        </td>
        <td style="padding:8px 8px;">
            <input type="number" class="form-input custom-item-qty" value="${qty}" min="0.1" step="any" style="font-size:0.82rem; padding:4px 6px; height:34px; text-align:center; font-weight:800;" oninput="onCustomItemInputChange(this)">
        </td>
        <td style="padding:8px 8px;">
            <select class="form-select custom-item-unit" style="font-size:0.78rem; padding:4px 6px; height:34px;" onchange="onCustomItemInputChange(this)">
                ${unitHtml}
            </select>
        </td>
        <td style="padding:8px 8px;">
            <input type="number" class="form-input custom-item-price" value="${price}" min="0" step="any" placeholder="0" style="font-size:0.82rem; padding:4px 8px; height:34px; text-align:right; font-weight:700; color:#059669;" oninput="onCustomItemInputChange(this)" title="Price / Unit">
        </td>
        <td style="padding:8px 8px;">
            <input type="number" class="form-input custom-item-discount" value="${discount}" min="0" step="any" placeholder="0" style="font-size:0.82rem; padding:4px 8px; height:34px; text-align:right; font-weight:800; color:#e11d48; background:#fff1f2; border-color:#fecdd3;" oninput="onCustomItemInputChange(this)" title="Discount on item">
        </td>
        <td style="padding:8px 8px;">
            <input type="number" class="form-input custom-item-amount" value="${amount}" readonly style="font-size:0.88rem; padding:4px 8px; height:34px; text-align:right; font-weight:900; background:#f8fafc; color:#059669;" title="Calculated as (Qty x Price) - Discount">
        </td>
        <td style="padding:8px 8px; text-align:center;">
            <button type="button" class="pos-btn pos-btn-outline" onclick="removeCustomItemRow(this)" style="padding:4px 8px; color:#ef4444; border-color:#fecaca; font-size:0.8rem; border-radius:6px; height:32px; width:32px; display:inline-flex; align-items:center; justify-content:center;" title="Delete this custom item">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    `;
    return tr;
}

window.cctvCatalogItems = <?php echo json_encode($cctvCatalog); ?> || [];

function onCustomItemNameChange(inputElem) {
    const val = inputElem.value.trim();
    if (!val) {
        onCustomItemInputChange(inputElem);
        return;
    }
    
    const match = window.cctvCatalogItems.find(it => it.name.toLowerCase() === val.toLowerCase());
    if (match) {
        const row = inputElem.closest('tr.custom-item-row');
        if (row) {
            const subCatSelect = row.querySelector('.custom-item-subcat');
            if (subCatSelect) {
                let optExists = Array.from(subCatSelect.options).some(o => o.value === match.subCategory);
                if (optExists) {
                    subCatSelect.value = match.subCategory;
                } else {
                    subCatSelect.value = '__custom__';
                    const customSubInput = row.querySelector('.custom-item-subcat-custom');
                    if (customSubInput) {
                        customSubInput.value = match.subCategory;
                        customSubInput.style.display = 'block';
                    }
                }
            }
            const unitSelect = row.querySelector('.custom-item-unit');
            if (unitSelect && match.unit) {
                unitSelect.value = match.unit;
            }
            const priceInput = row.querySelector('.custom-item-price');
            if (priceInput && match.defaultPrice) {
                priceInput.value = match.defaultPrice;
            }
            onCustomItemInputChange(priceInput || inputElem);
            return;
        }
    }
    onCustomItemInputChange(inputElem);
}

function addCustomItemRow(item = { subCategory: 'General Materials', name: '', qty: 1, unit: 'Pcs', price: 0, discount: 0, amount: 0 }) {
    const tbody = document.getElementById('cctvCustomItemsTbody');
    const currentIndex = tbody.querySelectorAll('tr.custom-item-row').length + 1;
    const trElem = createCustomItemRowElement(currentIndex, item);
    tbody.appendChild(trElem);
    updateCustomItemRowIndices();
    calcCctvTotal();
}

function onCustomItemSubCatChange(selectElem) {
    const customInput = selectElem.nextElementSibling;
    if (selectElem.value === '__custom__') {
        customInput.style.display = 'block';
        customInput.focus();
    } else {
        customInput.style.display = 'none';
    }
}

function removeCustomItemRow(btn) {
    const row = btn.closest('tr.custom-item-row');
    if (row) {
        row.remove();
        updateCustomItemRowIndices();
        calcCctvTotal();
    }
}

function updateCustomItemRowIndices() {
    const rows = document.querySelectorAll('#cctvCustomItemsTbody .custom-item-row');
    rows.forEach((r, idx) => {
        const idxCell = r.querySelector('.custom-item-idx');
        if (idxCell) idxCell.textContent = idx + 1;
    });
}

function onQuickPresetSelected(selectElem) {
    const val = selectElem.value;
    if (!val) return;
    const parts = val.split('|');
    if (parts.length >= 5) {
        document.getElementById('cctvEnableCustomItems').checked = true;
        toggleCustomItemsSection();
        addCustomItemRow({
            subCategory: parts[0],
            name: parts[1],
            qty: parseFloat(parts[2]) || 1,
            unit: parts[3] || 'Pcs',
            price: parseFloat(parts[4]) || 0,
            discount: 0,
            amount: (parseFloat(parts[2]) || 1) * (parseFloat(parts[4]) || 0)
        });
    } else if (parts.length >= 4) {
        document.getElementById('cctvEnableCustomItems').checked = true;
        toggleCustomItemsSection();
        addCustomItemRow({
            subCategory: 'General Materials',
            name: parts[0],
            qty: parseFloat(parts[1]) || 1,
            unit: parts[2] || 'Pcs',
            price: parseFloat(parts[3]) || 0,
            discount: 0,
            amount: (parseFloat(parts[1]) || 1) * (parseFloat(parts[3]) || 0)
        });
    }
    selectElem.value = '';
}

function onCustomItemInputChange(inputElem) {
    const row = inputElem.closest('tr.custom-item-row');
    if (!row) return;
    const qty = parseFloat(row.querySelector('.custom-item-qty').value) || 0;
    const price = parseFloat(row.querySelector('.custom-item-price').value) || 0;
    const disc = parseFloat(row.querySelector('.custom-item-discount').value) || 0;
    const amountInput = row.querySelector('.custom-item-amount');
    const amount = Math.max(0, Math.round((qty * price) - disc));
    if (amountInput) {
        amountInput.value = amount;
    }
    calcCctvTotal();
}

function getCustomItemsData() {
    const isEnabled = document.getElementById('cctvEnableCustomItems').checked;
    if (!isEnabled) return [];
    
    const rows = document.querySelectorAll('#cctvCustomItemsTbody .custom-item-row');
    const items = [];
    rows.forEach(r => {
        const subCatSelect = r.querySelector('.custom-item-subcat');
        const customSubCatInput = r.querySelector('.custom-item-subcat-custom');
        let subCategory = subCatSelect ? subCatSelect.value : 'General Materials';
        if (subCategory === '__custom__' && customSubCatInput && customSubCatInput.value.trim()) {
            subCategory = customSubCatInput.value.trim();
        }

        const name = (r.querySelector('.custom-item-name').value || '').trim();
        const qty = parseFloat(r.querySelector('.custom-item-qty').value) || 1;
        const unit = r.querySelector('.custom-item-unit').value || 'Pcs';
        const price = parseFloat(r.querySelector('.custom-item-price').value) || 0;
        const discount = parseFloat(r.querySelector('.custom-item-discount').value) || 0;
        const amount = parseFloat(r.querySelector('.custom-item-amount').value) || Math.max(0, (qty * price) - discount);
        if (name) {
            items.push({
                subCategory: subCategory,
                name: name,
                qty: qty,
                unit: unit,
                price: price,
                discount: discount,
                amount: amount
            });
        }
    });
    return items;
}

function renderCustomItemsRows(items) {
    const tbody = document.getElementById('cctvCustomItemsTbody');
    tbody.innerHTML = '';
    const checkbox = document.getElementById('cctvEnableCustomItems');
    if (Array.isArray(items) && items.length > 0) {
        checkbox.checked = true;
        items.forEach(it => {
            addCustomItemRow(it);
        });
    } else {
        checkbox.checked = false;
    }
    toggleCustomItemsSection();
    calcCctvTotal();
}

function onPaymentMethodChange() {
    const method = document.getElementById('cctvPaymentMethod').value;
    const creditRow = document.getElementById('cctvCreditDetailsRow');
    if (creditRow) {
        if (method === 'Credit' || method === 'Partial Credit') {
            creditRow.style.display = 'grid';
        } else {
            creditRow.style.display = 'none';
        }
    }
}

// -------------------------------------------------------------
// LIVE CALCULATION CONTROLLER (With Per-Item Discounts & Extra Discount)
// -------------------------------------------------------------
function calcCctvTotal() {
    // 1. Cameras
    const camCount = parseInt(document.getElementById('cctvCameraCount').value) || 0;
    const camPrice = parseFloat(document.getElementById('cctvCameraPrice').value) || 0;
    const camDiscount = parseFloat(document.getElementById('cctvCameraDiscount').value) || 0;
    const camGross = camCount * camPrice;
    const camAmount = Math.max(0, Math.round(camGross - camDiscount));
    const camAmountInput = document.getElementById('cctvCameraAmount');
    if (camAmountInput) camAmountInput.value = camAmount;

    // 2. DVR
    const dvrQty = parseInt(document.getElementById('cctvDvrQty').value) || 0;
    const dvrPrice = parseFloat(document.getElementById('cctvDvrPrice').value) || 0;
    const dvrDiscount = parseFloat(document.getElementById('cctvDvrDiscount').value) || 0;
    const dvrGross = dvrQty * dvrPrice;
    const dvrAmount = Math.max(0, Math.round(dvrGross - dvrDiscount));
    const dvrAmountInput = document.getElementById('cctvDvrAmount');
    if (dvrAmountInput) dvrAmountInput.value = dvrAmount;

    // 3. Storage Drives
    let storageGrossTotal = 0;
    let storageDiscountTotal = 0;
    let storageNetTotal = 0;
    const storageRows = document.querySelectorAll('.storage-drive-row');
    storageRows.forEach(r => {
        const sqty = parseInt(r.querySelector('.storage-qty-input').value) || 0;
        const sprice = parseFloat(r.querySelector('.storage-price-input').value) || 0;
        const sdisc = parseFloat(r.querySelector('.storage-discount-input').value) || 0;
        const sgross = sqty * sprice;
        const samt = Math.max(0, Math.round(sgross - sdisc));
        const samtInput = r.querySelector('.storage-amount-input');
        if (samtInput) samtInput.value = samt;
        storageGrossTotal += sgross;
        storageDiscountTotal += sdisc;
        storageNetTotal += samt;
    });

    // 4. Power Supply Units (Multi-row)
    let psuGrossTotal = 0;
    let psuDiscountTotal = 0;
    let psuNetTotal = 0;
    const psuRows = document.querySelectorAll('.psu-unit-row');
    psuRows.forEach(r => {
        const pqty = parseInt(r.querySelector('.psu-qty-input').value) || 0;
        const pprice = parseFloat(r.querySelector('.psu-price-input').value) || 0;
        const pdisc = parseFloat(r.querySelector('.psu-discount-input').value) || 0;
        const pgross = pqty * pprice;
        const pamt = Math.max(0, Math.round(pgross - pdisc));
        const pamtInput = r.querySelector('.psu-amount-input');
        if (pamtInput) pamtInput.value = pamt;
        psuGrossTotal += pgross;
        psuDiscountTotal += pdisc;
        psuNetTotal += pamt;
    });

    // Hidden inputs for legacy single power compatibility
    const psuQtyHidden = document.getElementById('cctvPowerSupplyQty');
    if (psuQtyHidden) psuQtyHidden.value = psuRows.length;
    const psuAmountHidden = document.getElementById('cctvPowerSupplyAmount');
    if (psuAmountHidden) psuAmountHidden.value = psuNetTotal;

    // 5. Custom / Additional Items
    let customGrossTotal = 0;
    let customDiscountTotal = 0;
    let customNetTotal = 0;
    const customRows = document.querySelectorAll('#cctvCustomItemsTbody .custom-item-row');
    customRows.forEach(r => {
        const cqty = parseFloat(r.querySelector('.custom-item-qty').value) || 0;
        const cprice = parseFloat(r.querySelector('.custom-item-price').value) || 0;
        const cdisc = parseFloat(r.querySelector('.custom-item-discount').value) || 0;
        const cgross = cqty * cprice;
        const camt = Math.max(0, Math.round(cgross - cdisc));
        const camtInput = r.querySelector('.custom-item-amount');
        if (camtInput) camtInput.value = camt;
        customGrossTotal += cgross;
        customDiscountTotal += cdisc;
        customNetTotal += camt;
    });

    const tableTotalElem = document.getElementById('cctvCustomItemsTableTotal');
    if (tableTotalElem) tableTotalElem.textContent = 'PKR ' + customNetTotal.toLocaleString();

    const customBadge = document.getElementById('cctvCustomItemsTotalBadge');
    if (customBadge) {
        const validCount = Array.from(customRows).filter(r => (r.querySelector('.custom-item-name').value || '').trim()).length;
        customBadge.textContent = `${validCount} Custom Item${validCount === 1 ? '' : 's'}: PKR ${customNetTotal.toLocaleString()}`;
    }

    // Gross Hardware Total
    const grossHardware = camGross + dvrGross + storageGrossTotal + psuGrossTotal + customGrossTotal;
    const grossInput = document.getElementById('cctvEquipmentGross');
    if (grossInput) grossInput.value = grossHardware;

    // Total Product Discounts
    const productDiscountsSum = camDiscount + dvrDiscount + storageDiscountTotal + psuDiscountTotal + customDiscountTotal;
    const itemsDiscInput = document.getElementById('cctvItemsDiscountTotal');
    if (itemsDiscInput) itemsDiscInput.value = productDiscountsSum;

    // Net Equipment Cost
    const netEquipment = camAmount + dvrAmount + storageNetTotal + psuNetTotal + customNetTotal;
    const eqInput = document.getElementById('cctvEquipmentCost');
    if (eqInput) eqInput.value = netEquipment;

    // Extra Special Discount & Labor Fee
    const extraDiscount = parseFloat(document.getElementById('cctvDiscount').value) || 0;
    const labor = parseFloat(document.getElementById('cctvLaborFee').value) || 0;
    const netTotal = Math.max(0, netEquipment - extraDiscount + labor);

    const totalBillInput = document.getElementById('cctvTotalBill');
    if (totalBillInput) totalBillInput.value = netTotal;

    calcCctvRemaining();
}

function calcCctvRemaining() {
    const total = parseFloat(document.getElementById('cctvTotalBill').value) || 0;
    const adv = parseFloat(document.getElementById('cctvAdvancePaid').value) || 0;
    const remaining = Math.max(0, total - adv);
    document.getElementById('cctvRemainingPayment').value = remaining;
    document.getElementById('cctvCreditAmount').value = remaining;
    
    // Auto sync payment method suggestion if remaining balance exists
    const methodSelect = document.getElementById('cctvPaymentMethod');
    if (remaining > 0 && adv > 0) {
        methodSelect.value = 'Partial Credit';
    } else if (remaining > 0 && adv === 0) {
        methodSelect.value = 'Credit';
    } else if (remaining === 0) {
        methodSelect.value = 'Cash';
    }
    onPaymentMethodChange();
}

function openCctvModal() {
    document.getElementById('cctvId').value = '';
    document.getElementById('cctvModalTitle').textContent = 'Book New CCTV Project';
    document.getElementById('cctvForm').reset();
    document.getElementById('cctvBrand').value = 'Hikvision';
    document.getElementById('cctvMegapixel').value = '5MP Super HD';
    document.getElementById('cctvMegapixelCustom').style.display = 'none';
    document.getElementById('cctvMegapixelCustom').value = '';
    document.getElementById('cctvCameraCount').value = 4;
    document.getElementById('cctvCameraPrice').value = 3500;
    document.getElementById('cctvCameraDiscount').value = 0;
    document.getElementById('cctvCameraAmount').value = 14000;
    
    document.getElementById('cctvDvrPreset').value = 'Hikvision 4-Channel Turbo HD DVR';
    document.getElementById('cctvDvrModel').value = 'Hikvision 4-Channel Turbo HD DVR';
    document.getElementById('cctvDvrQty').value = 1;
    document.getElementById('cctvDvrChannels').value = 4;
    document.getElementById('cctvDvrPrice').value = 6500;
    document.getElementById('cctvDvrDiscount').value = 0;
    document.getElementById('cctvDvrAmount').value = 6500;

    renderPowerSupplyRows([{ type: '12V 5A Central Supply', qty: 1, customText: '', price: 2500, discount: 0, amount: 2500 }]);
    document.getElementById('cctvTechnician').value = 'Safdar & Munim';
    
    document.getElementById('cctvDiscount').value = 0;
    document.getElementById('cctvEquipmentGross').value = 0;
    document.getElementById('cctvItemsDiscountTotal').value = 0;
    document.getElementById('cctvEquipmentCost').value = 0;
    document.getElementById('cctvLaborFee').value = 0;
    document.getElementById('cctvTotalBill').value = 0;
    document.getElementById('cctvAdvancePaid').value = 0;
    document.getElementById('cctvRemainingPayment').value = 0;
    document.getElementById('cctvCreditAmount').value = 0;
    document.getElementById('cctvPaymentMethod').value = 'Cash';
    document.getElementById('cctvCreditNote').value = '';
    document.getElementById('cctvCreditDueDate').value = '';

    renderStorageRows([{ type: '1TB Surveillance HDD', qty: 1, customText: '', price: 5500, discount: 0, amount: 5500 }]);
    renderCustomItemsRows([]);
    
    document.getElementById('cctvInstalledDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('cctvWarrantyExpiry').value = '';
    
    onCameraBrandChange();
    calcCctvTotal();
    
    document.getElementById('cctvModal').style.display = 'flex';
    document.getElementById('cctvClientName').focus();
}

window.allCctvProjects = <?php echo json_encode($cctv); ?> || [];

function getCctvProject(p) {
    if (!p) return null;
    if (typeof p === 'object') return p;
    if (typeof p === 'string') {
        const found = (window.allCctvProjects || []).find(x => String(x.id) === String(p) || String(x.projectNo) === String(p));
        return found || null;
    }
    return null;
}

window.sendCctvWhatsApp = window.sendCctvWhatsApp || function (projectId, clientPhone) {
    let phone = (clientPhone || '').replace(/[^0-9]/g, '');
    if (!phone) {
        phone = prompt('Enter client WhatsApp mobile number (e.g. 03339688007):');
        if (!phone) return;
    }

    fetch(`../backend/whatsapp.php?type=cctv&id=${encodeURIComponent(projectId)}&phone=${encodeURIComponent(phone)}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data && res.data.whatsappUrl) {
                window.open(res.data.whatsappUrl, '_blank');
            } else {
                alert(res.message || 'Failed to generate WhatsApp booking receipt');
            }
        })
        .catch(err => {
            alert('Error connecting to WhatsApp dispatcher: ' + err.message);
        });
};

function closeCctvModal() {
    document.getElementById('cctvModal').style.display = 'none';
}

function editCctvProject(p) {
    p = getCctvProject(p);
    if (!p) return;
    document.getElementById('cctvId').value = p.id;
    document.getElementById('cctvModalTitle').textContent = 'Edit CCTV Project - ' + p.projectNo;
    document.getElementById('cctvClientName').value = p.clientName || '';
    document.getElementById('cctvClientPhone').value = p.clientPhone || '';
    document.getElementById('cctvSiteType').value = p.siteType || 'commercial';
    document.getElementById('cctvSiteAddress').value = p.siteAddress || '';
    document.getElementById('cctvBrand').value = p.cameraBrand || 'Hikvision';

    // Megapixel / Resolution
    const mp = p.cameraMegapixel || '5MP Super HD';
    let isStandardMp = false;
    const mpSelect = document.getElementById('cctvMegapixel');
    for (let opt of mpSelect.options) {
        if (opt.value === mp) {
            mpSelect.value = mp;
            isStandardMp = true;
            break;
        }
    }
    if (!isStandardMp && mp) {
        mpSelect.value = 'custom';
        document.getElementById('cctvMegapixelCustom').style.display = 'block';
        document.getElementById('cctvMegapixelCustom').value = mp;
    } else {
        document.getElementById('cctvMegapixelCustom').style.display = 'none';
        document.getElementById('cctvMegapixelCustom').value = '';
    }

    document.getElementById('cctvSystemPackage').value = p.systemPackage || '';
    const camCount = p.cameraCount || 4;
    document.getElementById('cctvCameraCount').value = camCount;
    const camPrice = (p.cameraPrice !== undefined && p.cameraPrice !== null) ? parseFloat(p.cameraPrice) : 3500;
    const camDiscount = (p.cameraDiscount !== undefined && p.cameraDiscount !== null) ? parseFloat(p.cameraDiscount) : 0;
    document.getElementById('cctvCameraPrice').value = camPrice;
    document.getElementById('cctvCameraDiscount').value = camDiscount;
    document.getElementById('cctvCameraAmount').value = Math.max(0, Math.round((camCount * camPrice) - camDiscount));
    
    // DVR
    document.getElementById('cctvDvrChannels').value = p.dvrChannels !== undefined ? p.dvrChannels : 4;
    const dvrQty = p.dvrQty !== undefined ? p.dvrQty : (p.dvrChannels > 0 ? 1 : 0);
    document.getElementById('cctvDvrQty').value = dvrQty;
    document.getElementById('cctvDvrModel').value = p.dvrModel || `${p.dvrChannels || 4}-Channel DVR / XVR`;
    const dvrPrice = (p.dvrPrice !== undefined && p.dvrPrice !== null) ? parseFloat(p.dvrPrice) : 6500;
    const dvrDiscount = (p.dvrDiscount !== undefined && p.dvrDiscount !== null) ? parseFloat(p.dvrDiscount) : 0;
    document.getElementById('cctvDvrPrice').value = dvrPrice;
    document.getElementById('cctvDvrDiscount').value = dvrDiscount;
    document.getElementById('cctvDvrAmount').value = Math.max(0, Math.round((dvrQty * dvrPrice) - dvrDiscount));
    
    // Check preset match
    const dvrPreset = document.getElementById('cctvDvrPreset');
    let foundPreset = false;
    for (let opt of dvrPreset.options) {
        if (opt.value === p.dvrModel) {
            dvrPreset.value = opt.value;
            foundPreset = true;
            break;
        }
    }
    if (!foundPreset && p.dvrModel) {
        dvrPreset.value = 'custom';
    }

    // Power Supplies (Multi-row)
    let powerSupplies = [];
    if (Array.isArray(p.powerSupplies) && p.powerSupplies.length > 0) {
        powerSupplies = p.powerSupplies;
    } else if (p.powerSupply) {
        const parts = p.powerSupply.split(',').map(s => s.trim()).filter(Boolean);
        parts.forEach(pt => {
            const m = pt.match(/^(\d+)x\s*(.*)$/i);
            if (m) {
                powerSupplies.push({ type: m[2], qty: parseInt(m[1]) || 1, price: getSuggestedPsuPrice(m[2]), discount: 0, amount: (parseInt(m[1]) || 1) * getSuggestedPsuPrice(m[2]) });
            } else {
                powerSupplies.push({ type: pt, qty: parseInt(p.powerSupplyQty) || 1, price: parseFloat(p.powerSupplyPrice) || getSuggestedPsuPrice(pt), discount: parseFloat(p.powerSupplyDiscount) || 0, amount: parseFloat(p.powerSupplyAmount) || getSuggestedPsuPrice(pt) });
            }
        });
    }
    if (powerSupplies.length === 0) {
        powerSupplies.push({ type: '12V 5A Central Supply', qty: 1, price: 2500, discount: 0, amount: 2500 });
    }
    renderPowerSupplyRows(powerSupplies);

    // Storage Drives (Multi-row)
    let drives = [];
    if (Array.isArray(p.storageDrives) && p.storageDrives.length > 0) {
        drives = p.storageDrives;
    } else if (p.storageHdd) {
        const parts = p.storageHdd.split(',').map(s => s.trim()).filter(Boolean);
        parts.forEach(pt => {
            const m = pt.match(/^(\d+)x\s*(.*)$/i);
            if (m) {
                drives.push({ type: m[2], qty: parseInt(m[1]) || 1, price: getSuggestedHddPrice(m[2]), discount: 0, amount: (parseInt(m[1]) || 1) * getSuggestedHddPrice(m[2]) });
            } else {
                drives.push({ type: pt, qty: 1, price: getSuggestedHddPrice(pt), discount: 0, amount: getSuggestedHddPrice(pt) });
            }
        });
    }
    if (drives.length === 0) {
        drives.push({ type: p.storageHdd || '1TB Surveillance HDD', qty: 1, price: 5500, discount: 0, amount: 5500 });
    }
    renderStorageRows(drives);

    // Custom manual items
    renderCustomItemsRows(p.customItems || []);

    document.getElementById('cctvDiscount').value = p.discount || 0;
    document.getElementById('cctvEquipmentGross').value = p.equipmentGross || 0;
    document.getElementById('cctvItemsDiscountTotal').value = p.itemsDiscountTotal || 0;
    document.getElementById('cctvEquipmentCost').value = p.equipmentCost || 0;
    document.getElementById('cctvLaborFee').value = p.laborFee || 0;
    document.getElementById('cctvTotalBill').value = p.totalBill || 0;
    document.getElementById('cctvAdvancePaid').value = p.advancePaid || 0;
    const rem = p.remainingPayment !== undefined ? p.remainingPayment : Math.max(0, (p.totalBill || 0) - (p.advancePaid || 0));
    document.getElementById('cctvRemainingPayment').value = rem;
    document.getElementById('cctvCreditAmount').value = p.creditAmount !== undefined ? p.creditAmount : rem;
    document.getElementById('cctvPaymentMethod').value = p.paymentMethod || (rem > 0 ? (p.advancePaid > 0 ? 'Partial Credit' : 'Credit') : 'Cash');
    document.getElementById('cctvCreditNote').value = p.creditNote || '';
    document.getElementById('cctvCreditDueDate').value = p.creditDueDate || '';
    onPaymentMethodChange();

    document.getElementById('cctvStatus').value = p.status || 'installed';
    document.getElementById('cctvTechnician').value = p.technician || 'Safdar & Munim';
    document.getElementById('cctvInstalledDate').value = p.installedDate || (p.createdAt ? p.createdAt.split('T')[0] : '');
    document.getElementById('cctvWarrantyExpiry').value = '';
    document.getElementById('cctvNotes').value = p.notes || '';

    calcCctvTotal();
    document.getElementById('cctvModal').style.display = 'flex';
}

function saveCctvProject(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveCctv');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    const storageDrives = getStorageDrivesData();
    const storageHdd = document.getElementById('cctvStorageHdd').value;
    const powerSupplies = getPowerSuppliesData();
    const powerSupply = document.getElementById('cctvPowerSupply').value;
    const customItems = getCustomItemsData();

    const payload = {
        action: 'save',
        id: document.getElementById('cctvId').value,
        clientName: document.getElementById('cctvClientName').value.trim(),
        clientPhone: document.getElementById('cctvClientPhone').value.trim(),
        siteType: document.getElementById('cctvSiteType').value,
        siteAddress: document.getElementById('cctvSiteAddress').value.trim(),
        cameraBrand: document.getElementById('cctvBrand').value,
        cameraMegapixel: getEffectiveMegapixel(),
        systemPackage: document.getElementById('cctvSystemPackage').value.trim(),
        cameraCount: parseInt(document.getElementById('cctvCameraCount').value) || 4,
        cameraPrice: parseFloat(document.getElementById('cctvCameraPrice').value) || 0,
        cameraDiscount: parseFloat(document.getElementById('cctvCameraDiscount').value) || 0,
        cameraAmount: parseFloat(document.getElementById('cctvCameraAmount').value) || 0,
        dvrChannels: parseInt(document.getElementById('cctvDvrChannels').value) || 4,
        dvrModel: document.getElementById('cctvDvrModel').value.trim(),
        dvrQty: parseInt(document.getElementById('cctvDvrQty').value) !== undefined ? parseInt(document.getElementById('cctvDvrQty').value) : 1,
        dvrPrice: parseFloat(document.getElementById('cctvDvrPrice').value) || 0,
        dvrDiscount: parseFloat(document.getElementById('cctvDvrDiscount').value) || 0,
        dvrAmount: parseFloat(document.getElementById('cctvDvrAmount').value) || 0,
        storageDrives: storageDrives,
        storageHdd: storageHdd,
        powerSupplies: powerSupplies,
        powerSupply: powerSupply,
        powerSupplyAmp: powerSupplies.length > 0 ? powerSupplies[0].type : '12V 5A Central Supply',
        powerSupplyQty: powerSupplies.length,
        powerSupplyPrice: powerSupplies.length > 0 ? powerSupplies[0].price : 2500,
        powerSupplyDiscount: powerSupplies.reduce((acc, s) => acc + (s.discount || 0), 0),
        powerSupplyAmount: parseFloat(document.getElementById('cctvPowerSupplyAmount').value) || 0,
        customItems: customItems,
        equipmentGross: parseFloat(document.getElementById('cctvEquipmentGross').value) || 0,
        itemsDiscountTotal: parseFloat(document.getElementById('cctvItemsDiscountTotal').value) || 0,
        equipmentCost: parseFloat(document.getElementById('cctvEquipmentCost').value) || 0,
        discount: parseFloat(document.getElementById('cctvDiscount').value) || 0,
        laborFee: parseFloat(document.getElementById('cctvLaborFee').value) || 0,
        totalBill: parseFloat(document.getElementById('cctvTotalBill').value) || 0,
        advancePaid: parseFloat(document.getElementById('cctvAdvancePaid').value) || 0,
        remainingPayment: parseFloat(document.getElementById('cctvRemainingPayment').value) || 0,
        creditAmount: parseFloat(document.getElementById('cctvCreditAmount').value) || 0,
        paymentMethod: document.getElementById('cctvPaymentMethod').value,
        creditNote: document.getElementById('cctvCreditNote').value.trim(),
        creditDueDate: document.getElementById('cctvCreditDueDate').value,
        status: document.getElementById('cctvStatus').value,
        technician: document.getElementById('cctvTechnician').value.trim() || 'Safdar & Munim',
        installedDate: document.getElementById('cctvInstalledDate').value || new Date().toISOString().split('T')[0],
        warrantyExpiry: '',
        notes: document.getElementById('cctvNotes').value.trim()
    };

    fetch('../backend/cctv.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check-circle"></i> Save CCTV Project Record';
        if (res.status === 'success') {
            closeCctvModal();
            showToastAndReload(res.message || 'CCTV Project record saved successfully!', 'success', 600);
        } else {
            showToast(res.message || 'Could not save record', 'error', 'Action Failed');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check-circle"></i> Save CCTV Project Record';
        showToast('Network error saving CCTV record: ' + err.message, 'error', 'Network Error');
    });
}

function openReturnCctvModal(p) {
    p = getCctvProject(p);
    if (!p) return;
    document.getElementById('returnCctvId').value = p.id || '';
    document.getElementById('returnCctvProjectNo').textContent = p.projectNo || '';
    document.getElementById('returnCctvClientName').textContent = p.clientName || 'Client';
    document.getElementById('returnCctvPackage').textContent = (p.cameraBrand || 'CCTV') + ' - ' + (p.systemPackage || '');
    document.getElementById('returnCctvTotalBill').textContent = 'PKR ' + Number(p.totalBill || 0).toLocaleString();
    document.getElementById('returnCctvAdvancePaid').textContent = 'PKR ' + Number(p.advancePaid || 0).toLocaleString();
    document.getElementById('returnCctvRefundAmount').value = p.advancePaid || 0;
    document.getElementById('returnCctvReason').value = '';
    document.getElementById('returnCctvEquipment').checked = true;

    document.getElementById('cctvReturnModal').style.display = 'flex';
    document.getElementById('returnCctvRefundAmount').focus();
}

function closeReturnCctvModal() {
    document.getElementById('cctvReturnModal').style.display = 'none';
}

function submitReturnCctv(e) {
    e.preventDefault();
    const id = document.getElementById('returnCctvId').value;
    const refundAmount = parseFloat(document.getElementById('returnCctvRefundAmount').value) || 0;
    const reason = document.getElementById('returnCctvReason').value.trim();
    const equipmentReturned = document.getElementById('returnCctvEquipment').checked;

    if (!confirm(`Are you sure you want to log return/cancellation for this CCTV project?\nRefund to Client: PKR ${refundAmount.toLocaleString()}`)) {
        return;
    }

    fetch('../backend/cctv.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'return',
            id: id,
            refundAmount: refundAmount,
            reason: reason,
            equipmentReturned: equipmentReturned
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            closeReturnCctvModal();
            showToastAndReload(res.message || 'CCTV project return recorded successfully!', 'success', 600);
        } else {
            showToast(res.message || 'Failed to process return', 'error');
        }
    })
    .catch(err => showToast('Network error: ' + err.message, 'error'));
}

function deleteCctvProject(id, projNo) {
    const reason = prompt(`Are you sure you want to PERMANENTLY DELETE CCTV Project ${projNo}?\n\nPlease enter the reason for deletion:`, 'Administrative cleanup');
    if (reason === null) return;
    if (!reason.trim()) {
        showToast('Deletion reason is required for audit trail.', 'warning');
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
            showToastAndReload('CCTV Project deleted and archived successfully.', 'success', 600);
        } else {
            showToast(res.message || 'Could not delete project', 'error');
        }
    })
    .catch(err => showToast('Network error: ' + err.message, 'error'));
}

// Convert Number to Words (PKR Rupees)
function numberToWordsPKR(num) {
    num = Math.round(Number(num) || 0);
    if (num <= 0) return 'Rupees Zero Only';

    const a = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    function inWords(n) {
        if (n < 20) return a[n];
        if (n < 100) return b[Math.floor(n / 10)] + (n % 10 !== 0 ? ' ' + a[n % 10] : '');
        if (n < 1000) return a[Math.floor(n / 100)] + ' Hundred' + (n % 100 !== 0 ? ' ' + inWords(n % 100) : '');
        if (n < 100000) return inWords(Math.floor(n / 1000)) + ' Thousand' + (n % 1000 !== 0 ? ' ' + inWords(n % 1000) : '');
        if (n < 10000000) return inWords(Math.floor(n / 100000)) + ' Lakh' + (n % 100000 !== 0 ? ' ' + inWords(n % 100000) : '');
        return inWords(Math.floor(n / 10000000)) + ' Crore' + (n % 10000000 !== 0 ? ' ' + inWords(n % 10000000) : '');
    }

    return 'Rupees ' + inWords(num) + ' Only';
}

// Open and populate Formal A4 Invoice Modal with exact manual prices
function openCctvInvoiceModal(p) {
    p = getCctvProject(p);
    if (!p) return;

    function setSafeText(id, text) {
        const el = document.getElementById(id);
        if (el) el.innerText = text;
    }

    setSafeText('invProjectNo', p.projectNo || 'CCTV-000');
    setSafeText('invDate', p.installedDate ? new Date(p.installedDate).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : new Date().toLocaleDateString('en-GB'));
    setSafeText('invClientName', p.clientName || 'Valued Client');
    setSafeText('invClientPhone', p.clientPhone || 'N/A');
    setSafeText('invSiteAddress', p.siteAddress || 'Main Road Hangu');
    setSafeText('invSiteType', (p.siteType || 'Commercial') + ' Site');
    setSafeText('invBrand', p.cameraBrand || 'Hikvision');
    setSafeText('invResolution', p.cameraMegapixel || '5MP Super HD');
    setSafeText('invTechnician', p.technician || 'Safdar & Munim');
    setSafeText('invRemarksText', (p.notes && p.notes.trim()) ? p.notes.trim() : 'Installation completed and tested successfully.');

    // Build Itemized Rows dynamically from project data
    const brand = p.cameraBrand || 'Hikvision';
    const mp = p.cameraMegapixel || '5MP Super HD';
    const count = parseInt(p.cameraCount) || 4;
    const dvrChannels = parseInt(p.dvrChannels) || (count <= 4 ? 4 : (count <= 8 ? 8 : 16));
    const dvrQty = p.dvrQty !== undefined ? parseInt(p.dvrQty) : (dvrChannels > 0 ? 1 : 0);
    const dvrModel = p.dvrModel || `${dvrChannels}-Channel DVR / XVR Recorder Unit`;

    // Exact Manual Unit Prices
    const camRate = (p.cameraPrice !== undefined && p.cameraPrice !== null) ? parseFloat(p.cameraPrice) : 3500;
    const dvrRate = (p.dvrPrice !== undefined && p.dvrPrice !== null) ? parseFloat(p.dvrPrice) : ((dvrChannels > 4) ? 9500 : 6500);
    const psuRate = (p.powerSupplyPrice !== undefined && p.powerSupplyPrice !== null) ? parseFloat(p.powerSupplyPrice) : 2500;
    const psu = p.powerSupply || '12V Central Supply';
    const psuQty = parseInt(p.powerSupplyQty) || 1;
    const pkg = p.systemPackage || `${brand} HD Setup`;

    // Power supplies (multi-unit support)
    let powerSupplies = [];
    if (Array.isArray(p.powerSupplies) && p.powerSupplies.length > 0) {
        powerSupplies = p.powerSupplies;
    } else if (p.powerSupply) {
        const parts = p.powerSupply.split(',').map(s => s.trim()).filter(Boolean);
        parts.forEach(pt => {
            const m = pt.match(/^(\d+)x\s*(.*)$/i);
            if (m) {
                powerSupplies.push({ type: m[2], qty: parseInt(m[1]) || 1, price: getSuggestedPsuPrice(m[2]), amount: (parseInt(m[1]) || 1) * getSuggestedPsuPrice(m[2]) });
            } else {
                powerSupplies.push({ type: pt, qty: parseInt(p.powerSupplyQty) || 1, price: parseFloat(p.powerSupplyPrice) || getSuggestedPsuPrice(pt), amount: parseFloat(p.powerSupplyAmount) || getSuggestedPsuPrice(pt) });
            }
        });
    }
    if (powerSupplies.length === 0) {
        powerSupplies.push({ type: '12V 5A Central Supply', qty: 1, price: 2500, amount: 2500 });
    }

    // Storage drives (multi-unit support)
    let storageDrives = [];
    if (Array.isArray(p.storageDrives) && p.storageDrives.length > 0) {
        storageDrives = p.storageDrives;
    } else if (p.storageHdd) {
        const parts = p.storageHdd.split(',').map(s => s.trim()).filter(Boolean);
        parts.forEach(pt => {
            const m = pt.match(/^(\d+)x\s*(.*)$/i);
            if (m) {
                storageDrives.push({ type: m[2], qty: parseInt(m[1]) || 1, price: getSuggestedHddPrice(m[2]), amount: (parseInt(m[1]) || 1) * getSuggestedHddPrice(m[2]) });
            } else {
                storageDrives.push({ type: pt, qty: 1, price: getSuggestedHddPrice(pt), amount: getSuggestedHddPrice(pt) });
            }
        });
    }
    if (storageDrives.length === 0) {
        storageDrives.push({ type: '1TB Surveillance HDD', qty: 1, price: 5500, amount: 5500 });
    }

    const labor = parseFloat(p.laborFee) || 0;
    const discount = parseFloat(p.discount) || 0;

    const camDisc = parseFloat(p.cameraDiscount) || 0;
    const camLineAmt = (p.cameraAmount !== undefined && p.cameraAmount !== null) ? parseFloat(p.cameraAmount) : Math.max(0, (camRate * count) - camDisc);

    const lineItems = [
        { 
            name: `${brand} ${count}x ${mp} Night Vision Bullet/Dome Cameras (${pkg})` + (camDisc > 0 ? ` [Disc: -PKR ${camDisc.toLocaleString()}]` : ''), 
            qty: count, 
            unit: 'Pcs', 
            price: camRate, 
            amount: camLineAmt 
        }
    ];

    if (dvrQty > 0) {
        const dvrDisc = parseFloat(p.dvrDiscount) || 0;
        const dvrLineAmt = (p.dvrAmount !== undefined && p.dvrAmount !== null) ? parseFloat(p.dvrAmount) : Math.max(0, (dvrRate * dvrQty) - dvrDisc);
        lineItems.push({
            name: `${dvrModel}` + (dvrDisc > 0 ? ` [Disc: -PKR ${dvrDisc.toLocaleString()}]` : ''),
            qty: dvrQty,
            unit: 'Unit',
            price: dvrRate,
            amount: dvrLineAmt
        });
    }

    storageDrives.forEach(d => {
        const sqty = parseInt(d.qty) || 1;
        const srate = (d.price !== undefined && d.price !== null) ? parseFloat(d.price) : getSuggestedHddPrice(d.type);
        const sdisc = parseFloat(d.discount) || 0;
        const samt = (d.amount !== undefined && d.amount !== null) ? parseFloat(d.amount) : Math.max(0, (srate * sqty) - sdisc);
        lineItems.push({
            name: `${d.type} Dedicated 24/7 Surveillance Storage` + (sdisc > 0 ? ` [Disc: -PKR ${sdisc.toLocaleString()}]` : ''),
            qty: sqty,
            unit: 'Pcs',
            price: srate,
            amount: samt
        });
    });

    powerSupplies.forEach(ps => {
        const pqty = parseInt(ps.qty) || 1;
        const prate = (ps.price !== undefined && ps.price !== null) ? parseFloat(ps.price) : getSuggestedPsuPrice(ps.type);
        const pdisc = parseFloat(ps.discount) || 0;
        const pamt = (ps.amount !== undefined && ps.amount !== null) ? parseFloat(ps.amount) : Math.max(0, (prate * pqty) - pdisc);
        lineItems.push({
            name: `${ps.type} CCTV Power Supply / PoE Unit` + (pdisc > 0 ? ` [Disc: -PKR ${pdisc.toLocaleString()}]` : ''),
            qty: pqty,
            unit: 'Pcs',
            price: prate,
            amount: pamt
        });
    });

    // Append Custom Manual Items to invoice
    if (Array.isArray(p.customItems) && p.customItems.length > 0) {
        p.customItems.forEach(ci => {
            if (ci.name && ci.name.trim()) {
                const cqty = parseFloat(ci.qty) || 1;
                const cunit = ci.unit || 'Pcs';
                const cprice = parseFloat(ci.price) || 0;
                const cdisc = parseFloat(ci.discount) || 0;
                const camount = parseFloat(ci.amount) !== undefined ? parseFloat(ci.amount) : Math.max(0, (cqty * cprice) - cdisc);
                const subCatPrefix = ci.subCategory && ci.subCategory !== 'General Materials' ? `[${ci.subCategory}] ` : '';
                lineItems.push({
                    name: subCatPrefix + ci.name.trim() + (cdisc > 0 ? ` [Disc: -PKR ${cdisc.toLocaleString()}]` : ''),
                    qty: cqty,
                    unit: cunit,
                    price: cprice,
                    amount: camount
                });
            }
        });
    }

    if (labor > 0) {
        lineItems.push({
            name: `Professional Installation, Wiring, Alignment & Mobile App Setup`,
            qty: 1,
            unit: 'Job',
            price: labor,
            amount: labor
        });
    }

    let tbodyHtml = '';
    let totalQuantity = 0;
    let calculatedSubtotal = 0;

    lineItems.forEach((item, idx) => {
        totalQuantity += item.qty;
        calculatedSubtotal += item.amount;
        tbodyHtml += `
            <tr style="border-bottom:1px solid #ccc; font-size:11px;">
                <td style="border-right:1px solid #000; padding:5px 4px; text-align:center; font-weight:bold;">${idx + 1}</td>
                <td style="border-right:1px solid #000; padding:5px 8px;"><strong>${item.name}</strong></td>
                <td style="border-right:1px solid #000; padding:5px 4px; text-align:center; font-weight:bold;">${item.qty}</td>
                <td style="border-right:1px solid #000; padding:5px 4px; text-align:center; color:#444;">${item.unit}</td>
                <td style="border-right:1px solid #000; padding:5px 6px; text-align:right;">${item.price.toLocaleString()}</td>
                <td style="padding:5px 8px; text-align:right; font-weight:bold;">${item.amount.toLocaleString()}</td>
            </tr>
        `;
    });

    // Pad with empty pre-printed rows matching the physical invoice sheet pattern (up to 12 total rows)
    const targetRows = Math.max(12, lineItems.length);
    for (let i = lineItems.length + 1; i <= targetRows; i++) {
        tbodyHtml += `
            <tr style="border-bottom:1px solid #e2e8f0; height:24px; font-size:11px;">
                <td style="border-right:1px solid #000; padding:4px; text-align:center; color:#94a3b8;">${i}</td>
                <td style="border-right:1px solid #000; padding:4px;"></td>
                <td style="border-right:1px solid #000; padding:4px; text-align:center; color:#cbd5e1;">—</td>
                <td style="border-right:1px solid #000; padding:4px; text-align:center; color:#cbd5e1;">Pcs</td>
                <td style="border-right:1px solid #000; padding:4px;"></td>
                <td style="padding:4px;"></td>
            </tr>
        `;
    }

    const tbody = document.getElementById('invItemsTbody');
    if (tbody) tbody.innerHTML = tbodyHtml;
    
    setSafeText('invTotalQty', totalQuantity);
    
    // Dynamic Totals calculation
    const finalGrandTotal = Math.max(0, calculatedSubtotal - discount);
    const adv = parseFloat(p.advancePaid) || 0;
    const balance = Math.max(0, finalGrandTotal - adv);

    setSafeText('invTableTotalAmount', 'PKR ' + calculatedSubtotal.toLocaleString());
    setSafeText('invSubTotal', 'PKR ' + calculatedSubtotal.toLocaleString());
    
    const discRow = document.getElementById('invDiscountRow');
    if (discRow) {
        if (discount > 0) {
            discRow.style.display = '';
            setSafeText('invDiscount', '- PKR ' + discount.toLocaleString());
        } else {
            discRow.style.display = 'none';
        }
    }

    setSafeText('invGrandTotal', 'PKR ' + finalGrandTotal.toLocaleString());
    setSafeText('invReceived', 'PKR ' + adv.toLocaleString());
    setSafeText('invBalance', 'PKR ' + balance.toLocaleString());
    setSafeText('invAmountInWords', numberToWordsPKR(finalGrandTotal));

    const modal = document.getElementById('cctvInvoiceModal');
    if (modal) modal.style.display = 'flex';
}

function closeCctvInvoiceModal() {
    const modal = document.getElementById('cctvInvoiceModal');
    if (modal) modal.style.display = 'none';
}

// Trigger Formal A4 Invoice Print
function triggerCctvA4Print() {
    const printArea = document.getElementById('cctvA4PrintableArea').innerHTML;
    const printWin = window.open('', '_blank', 'width=850,height=1000');
    if (!printWin) {
        alert('Please allow popups to print invoice.');
        return;
    }

    printWin.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>CCTV Invoice - Safdar Mobile Store</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: Arial, Helvetica, sans-serif;
                    font-size: 11px;
                    color: #000;
                    background: #fff;
                    padding: 10px;
                }
                @media print {
                    @page { size: A4 portrait; margin: 8mm; }
                    body { padding: 0; }
                }
            </style>
        </head>
        <body onload="window.print();">
            ${printArea}
        </body>
        </html>
    `);
    printWin.document.close();
}

// Thermal Mini Slip (for 80mm POS Thermal Printers)
function printCctvSlip(p) {
    p = getCctvProject(p);
    if (!p) return;
    const printWin = window.open('', '_blank', 'width=420,height=600');
    if (!printWin) {
        alert('Please allow popups to print ticket.');
        return;
    }

    const tot = Number(p.totalBill || 0).toLocaleString();
    const adv = Number(p.advancePaid || 0).toLocaleString();
    const due = Number(Math.max(0, (p.totalBill || 0) - (p.advancePaid || 0))).toLocaleString();

    let customSlipRows = '';
    if (Array.isArray(p.customItems) && p.customItems.length > 0) {
        p.customItems.forEach(ci => {
            if (ci.name && ci.name.trim()) {
                const camt = Number(ci.amount || (ci.qty * ci.price)).toLocaleString();
                const prefix = ci.subCategory && ci.subCategory !== 'General Materials' ? `[${ci.subCategory}] ` : '';
                customSlipRows += `<tr><td style="color:#555;">+ ${prefix}${ci.name}:</td><td style="text-align:right;">${ci.qty} ${ci.unit} (PKR ${camt})</td></tr>`;
            }
        });
    }

    const camDesc = `${p.cameraBrand || 'CCTV'} (${p.cameraCount || 4} Cams${p.cameraMegapixel ? ' - ' + p.cameraMegapixel : ''})`;
    const remarksRow = (p.notes && p.notes.trim()) ? `<tr><td style="color:#555;">Remarks:</td><td style="text-align:right; font-weight:bold;">${p.notes.trim()}</td></tr>` : '';
    const payMethodRow = (p.paymentMethod && p.paymentMethod !== 'Cash') ? `<tr><td style="color:#555;">Payment Mode:</td><td style="text-align:right; font-weight:bold; color:#7c3aed;">${p.paymentMethod}</td></tr>` : '';
    const creditNoteRow = (p.creditNote && p.creditNote.trim()) ? `<tr><td style="color:#555;">Credit / Khata:</td><td style="text-align:right; color:#dc2626;">${p.creditNote.trim()}</td></tr>` : '';

    printWin.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>CCTV Ticket - ${p.projectNo}</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: 'Courier New', Courier, monospace; font-size: 11px; line-height: 1.35; color: #000; padding: 6px 4px; width: 72mm; margin: 0 auto; }
            </style>
        </head>
        <body>
            <div style="text-align:center; font-weight:bold; font-size:14px;">SAFDAR MOBILE STORE</div>
            <div style="text-align:center; font-size:10px;">Opp. Patt Bazar Eidgah Road near Purdil Masjid, Hangu</div>
            <div style="text-align:center; font-size:10px; font-weight:bold;">0333 9688007</div>
            <div style="border-top:1px dashed #000; margin:6px 0;"></div>
            
            <div style="text-align:center; font-weight:bold; font-size:12px;">CCTV INSTALLATION &amp; SALES INVOICE</div>
            <div style="text-align:center; font-weight:bold; font-size:13px; margin:2px 0;">${p.projectNo}</div>
            <div style="border-top:1px dashed #000; margin:6px 0;"></div>

            <table style="width:100%; font-size:10.5px; border-collapse:collapse;">
                <tr><td style="color:#555;">Client:</td><td style="text-align:right; font-weight:bold;">${p.clientName}</td></tr>
                <tr><td style="color:#555;">Phone:</td><td style="text-align:right;">${p.clientPhone}</td></tr>
                <tr><td style="color:#555;">Site:</td><td style="text-align:right;">${p.siteAddress}</td></tr>
                <tr><td style="color:#555;">Cameras:</td><td style="text-align:right; font-weight:bold;">${camDesc}</td></tr>
                <tr><td style="color:#555;">Recorder:</td><td style="text-align:right;">${(p.dvrQty && p.dvrQty > 1) ? p.dvrQty + 'x ' : ''}${p.dvrModel || (p.dvrChannels + 'CH DVR')}</td></tr>
                <tr><td style="color:#555;">Storage:</td><td style="text-align:right; font-weight:bold;">${p.storageHdd || 'Surveillance HDD'}</td></tr>
                <tr><td style="color:#555;">Power:</td><td style="text-align:right;">${(p.powerSupplyQty && p.powerSupplyQty > 1) ? p.powerSupplyQty + 'x ' : ''}${p.powerSupply || '12V Supply'}</td></tr>
                ${customSlipRows}
                <tr><td style="color:#555;">Technician:</td><td style="text-align:right; font-weight:bold; color:#1d4ed8;">${p.technician || 'Safdar & Munim'}</td></tr>
                ${payMethodRow}
                ${creditNoteRow}
                ${remarksRow}
            </table>

            <div style="border-top:1px dashed #000; margin:6px 0;"></div>

            <table style="width:100%; font-size:11px;">
                <tr><td>Total Project Bill:</td><td style="text-align:right; font-weight:bold;">PKR ${tot}</td></tr>
                <tr><td>Advance Paid:</td><td style="text-align:right;">PKR ${adv}</td></tr>
                <tr style="border-top:1px solid #000; font-weight:bold;"><td>Balance Due:</td><td style="text-align:right; color:#dc2626;">PKR ${due}</td></tr>
            </table>

            <div style="border-top:1px dashed #000; margin:6px 0;"></div>
            <div style="text-align:center; font-size:9.5px;">
                Thank you for choosing Safdar Mobile Store!<br>
                Security &amp; Surveillance Solutions
            </div>
        </body>
        </html>
    `);
    printWin.document.close();
    printWin.focus();
    setTimeout(() => { printWin.print(); }, 300);
}

// Auto-open modal if URL has ?action=new or open specific project / receipt
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const invoiceQuery = urlParams.get('invoice') || urlParams.get('receipt') || urlParams.get('project');
    const editId = urlParams.get('id');

    if (urlParams.get('action') === 'new') {
        openCctvModal();
    } else if (invoiceQuery) {
        // Direct open receipt modal
        const localProjects = <?php echo json_encode($cctv); ?> || [];
        const match = localProjects.find(p => p.id === invoiceQuery || p.projectNo === invoiceQuery || (p.projectNo && p.projectNo.toLowerCase() === invoiceQuery.toLowerCase()));
        if (match) {
            openCctvInvoiceModal(match);
        } else {
            fetch(`../backend/cctv.php?id=${encodeURIComponent(invoiceQuery)}`)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success' && res.data) {
                        openCctvInvoiceModal(res.data);
                    }
                })
                .catch(() => {});
        }
    } else if (editId) {
        fetch(`../backend/cctv.php?id=${encodeURIComponent(editId)}`)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success' && res.data) {
                    editCctvProject(res.data);
                }
            })
            .catch(() => {});
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
