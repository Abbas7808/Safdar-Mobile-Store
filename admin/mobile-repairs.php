<?php
$currentPage = 'mobile-repairs';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = get_session_user();
$repairs = get_json_file('mobile_repairs') ?? [];

// Calculate stats
$totalRepairs = count($repairs);
$inProgressCount = 0;
$completedCount = 0;
$totalLaborRevenue = 0;
$totalBilled = 0;
$totalAdvance = 0;
$totalDue = 0;

foreach ($repairs as $r) {
    $st = strtolower($r['jobStatus'] ?? '');
    if ($st === 'in progress' || $st === 'received' || $st === 'diagnosing') {
        $inProgressCount++;
    } elseif ($st === 'completed' || $st === 'ready for pickup') {
        $completedCount++;
    }
    $totalLaborRevenue += floatval($r['laborCharges'] ?? 0);
    $totalBilled += floatval($r['totalBill'] ?? 0);
    $totalAdvance += floatval($r['advancePaid'] ?? 0);
    $totalDue += floatval($r['balanceDue'] ?? 0);
}
?>

<div class="pos-main" style="max-width: calc(100vw - var(--pos-sidebar-w)); overflow-x: hidden;">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="admin-content" style="padding:20px; overflow-x:hidden; box-sizing:border-box;">
        
        <!-- Header & Action Row -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
            <div>
                <h1 style="font-family:var(--pos-font-heading); font-size:1.6rem; font-weight:900; color:var(--pos-text); margin:0; display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-screwdriver-wrench" style="color:var(--pos-red);"></i> Mobile Repair Lab Management
                </h1>
                <p style="color:var(--pos-text-muted); font-size:0.85rem; margin:4px 0 0 0;">
                    Complete smartphone repair ticketing, spare parts tracking, diagnostic issue resolution, and warranty claim management.
                </p>
            </div>
            
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button class="pos-btn pos-btn-outline" onclick="window.printReport()" title="Print Repair Records">
                    <i class="fa-solid fa-print"></i> Print Log
                </button>
                <button class="pos-btn pos-btn-primary" onclick="openRepairModal()" style="font-weight:700;">
                    <i class="fa-solid fa-plus-circle"></i> New Repair Job
                </button>
            </div>
        </div>

        <!-- 4 KPI Summary Cards -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:14px; margin-bottom:22px;">
            <!-- Card 1 -->
            <div class="pos-card" style="padding:16px; border-left:4px solid var(--pos-red); display:flex; align-items:center; gap:14px;">
                <div style="background:rgba(215,25,32,0.1); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--pos-red); font-size:1.4rem;">
                    <i class="fa-solid fa-mobile-screen"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800;">Total Repair Jobs</div>
                    <div style="font-size:1.45rem; font-weight:900; color:var(--pos-text);" id="statTotalRepairs"><?php echo $totalRepairs; ?></div>
                </div>
            </div>

            <!-- Card 2 -->
            <div class="pos-card" style="padding:16px; border-left:4px solid #f59e0b; display:flex; align-items:center; gap:14px;">
                <div style="background:rgba(245,158,11,0.1); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#d97706; font-size:1.4rem;">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800;">In Progress / Lab</div>
                    <div style="font-size:1.45rem; font-weight:900; color:#d97706;" id="statInProgress"><?php echo $inProgressCount; ?></div>
                </div>
            </div>

            <!-- Card 3 -->
            <div class="pos-card" style="padding:16px; border-left:4px solid #10b981; display:flex; align-items:center; gap:14px;">
                <div style="background:rgba(16,185,129,0.1); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#059669; font-size:1.4rem;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800;">Completed / Ready</div>
                    <div style="font-size:1.45rem; font-weight:900; color:#059669;" id="statCompleted"><?php echo $completedCount; ?></div>
                </div>
            </div>

            <!-- Card 4 -->
            <div class="pos-card" style="padding:16px; border-left:4px solid var(--pos-gold); display:flex; align-items:center; gap:14px;">
                <div style="background:rgba(244,196,48,0.15); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--pos-gold-dark); font-size:1.4rem;">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800;">Labor Profit / Revenue</div>
                    <div style="font-size:1.35rem; font-weight:900; color:var(--pos-gold-dark);" id="statLaborRevenue">PKR <?php echo number_format($totalLaborRevenue); ?></div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="pos-card" style="padding:14px; margin-bottom:18px;">
            <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between;">
                
                <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; flex:1; min-width:280px;">
                    <!-- Search Input -->
                    <div style="position:relative; flex:1; min-width:200px;">
                        <i class="fa-solid fa-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:0.85rem;"></i>
                        <input type="text" id="repairSearchInput" class="form-input" style="padding-left:34px; padding-top:7px; padding-bottom:7px; font-size:0.85rem; width:100%;" placeholder="Search ticket #, customer, phone, brand, fault..." oninput="filterRepairTable()">
                    </div>

                    <!-- Status Filter -->
                    <select id="repairStatusFilter" class="form-select" style="width:160px; padding:7px 10px; font-size:0.82rem;" onchange="filterRepairTable()">
                        <option value="all">All Statuses</option>
                        <option value="received">Received</option>
                        <option value="in progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="ready for pickup">Ready for Pickup</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>

                    <!-- Brand Filter -->
                    <select id="repairBrandFilter" class="form-select" style="width:140px; padding:7px 10px; font-size:0.82rem;" onchange="filterRepairTable()">
                        <option value="all">All Brands</option>
                        <option value="apple">Apple iPhone</option>
                        <option value="samsung">Samsung</option>
                        <option value="xiaomi">Xiaomi / Redmi</option>
                        <option value="infinix">Infinix</option>
                        <option value="tecno">Tecno</option>
                        <option value="vivo">Vivo</option>
                        <option value="realme">Realme</option>
                        <option value="oppo">Oppo</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div style="font-size:0.8rem; color:var(--pos-text-muted); font-weight:700;">
                    Showing <span id="filteredCountText" style="color:var(--pos-red);"><?php echo count($repairs); ?></span> tickets
                </div>
            </div>
        </div>

        <!-- REPAIR JOBS TABLE (No Horizontal Scrollbar, Fixed Clamped Layout) -->
        <div class="pos-card" style="padding:0; overflow:hidden; border:1.5px solid var(--pos-border); border-radius:10px;">
            <div style="width:100%; overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.82rem; table-layout:fixed; min-width:850px;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1.5px solid var(--pos-border); color:#475569; font-weight:800; text-transform:uppercase; font-size:0.72rem; letter-spacing:0.5px;">
                            <th style="width:13%; padding:10px 12px;">Ticket / Date</th>
                            <th style="width:16%; padding:10px 8px;">Customer & City</th>
                            <th style="width:16%; padding:10px 8px;">Device & Model</th>
                            <th style="width:20%; padding:10px 8px;">Fault & Work Done</th>
                            <th style="width:13%; padding:10px 8px;">Billing & Due</th>
                            <th style="width:11%; padding:10px 8px; text-align:center;">Job Status</th>
                            <th style="width:11%; padding:10px 8px; text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="repairsTableBody">
                        <?php if (empty($repairs)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:40px; color:#9ca3af;">
                                    <i class="fa-solid fa-screwdriver-wrench" style="font-size:2.2rem; margin-bottom:10px; opacity:0.4;"></i>
                                    <p style="font-weight:700; font-size:0.95rem; margin:0;">No repair job tickets found</p>
                                    <button class="pos-btn pos-btn-primary pos-btn-sm" style="margin-top:10px;" onclick="openRepairModal()">+ Create First Repair Ticket</button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($repairs as $r): ?>
                                <?php
                                    $statusColor = '#f59e0b';
                                    $statusBg = '#fef3c7';
                                    $st = strtolower($r['jobStatus'] ?? '');
                                    if ($st === 'completed' || $st === 'delivered') {
                                        $statusColor = '#059669';
                                        $statusBg = '#ecfdf5';
                                    } elseif ($st === 'ready for pickup') {
                                        $statusColor = '#2563eb';
                                        $statusBg = '#eff6ff';
                                    } elseif ($st === 'cancelled') {
                                        $statusColor = '#dc2626';
                                        $statusBg = '#fef2f2';
                                    }

                                    $balance = floatval($r['balanceDue'] ?? 0);
                                    $payColor = $balance > 0 ? '#dc2626' : '#059669';
                                ?>
                                <tr class="repair-row" data-id="<?php echo htmlspecialchars($r['id']); ?>" data-brand="<?php echo strtolower($r['deviceBrand'] ?? ''); ?>" data-status="<?php echo strtolower($r['jobStatus'] ?? ''); ?>" style="border-bottom:1px solid #f1f5f9; transition:background 0.15s;">
                                    
                                    <!-- Ticket & Date -->
                                    <td style="padding:10px 12px; word-break:break-word;">
                                        <strong style="color:var(--pos-red); font-size:0.85rem; font-family:monospace;"><?php echo htmlspecialchars($r['id']); ?></strong>
                                        <div style="font-size:0.7rem; color:#64748b; margin-top:2px;">
                                            <i class="fa-solid fa-calendar" style="margin-right:3px;"></i> <?php echo substr($r['receivedDate'] ?? '', 0, 10); ?>
                                        </div>
                                    </td>

                                    <!-- Customer -->
                                    <td style="padding:10px 8px; word-break:break-word;">
                                        <div style="font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($r['customerName']); ?></div>
                                        <div style="font-size:0.75rem; color:#64748b; display:flex; align-items:center; gap:4px; margin-top:2px;">
                                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $r['customerPhone']); ?>" target="_blank" style="color:#059669; font-weight:700; text-decoration:none;" title="Chat on WhatsApp">
                                                <i class="fa-brands fa-whatsapp"></i> <?php echo htmlspecialchars($r['customerPhone']); ?>
                                            </a>
                                        </div>
                                        <div style="font-size:0.7rem; color:#94a3b8;"><?php echo htmlspecialchars($r['customerCity'] ?? 'Hangu'); ?></div>
                                    </td>

                                    <!-- Device -->
                                    <td style="padding:10px 8px; word-break:break-word;">
                                        <span style="background:#f1f5f9; color:#334155; font-size:0.68rem; font-weight:800; padding:1px 6px; border-radius:4px; text-transform:uppercase;">
                                            <?php echo htmlspecialchars($r['deviceBrand']); ?>
                                        </span>
                                        <div style="font-weight:700; color:#0f172a; font-size:0.82rem; margin-top:2px;"><?php echo htmlspecialchars($r['deviceModel']); ?></div>
                                        <?php if (!empty($r['deviceImei'])): ?>
                                            <div style="font-size:0.68rem; color:#94a3b8; font-family:monospace;">IMEI: <?php echo htmlspecialchars($r['deviceImei']); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Fault & Work Done -->
                                    <td style="padding:10px 8px; word-break:break-word;">
                                        <div style="color:#b91c1c; font-weight:700; font-size:0.78rem;">
                                            <i class="fa-solid fa-triangle-exclamation" style="margin-right:2px;"></i> <?php echo htmlspecialchars($r['reportedFault']); ?>
                                        </div>
                                        <?php if (!empty($r['issueResolved'])): ?>
                                            <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:4px; padding:3px 6px; margin-top:4px; font-size:0.72rem; color:#065f46; line-height:1.3;">
                                                <strong>Fixed:</strong> <?php echo htmlspecialchars($r['issueResolved']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($r['partsUsed'])): ?>
                                            <div style="font-size:0.7rem; color:#475569; margin-top:2px;">
                                                <i class="fa-solid fa-cubes-stacked" style="color:var(--pos-gold-dark);"></i> Parts: <?php echo htmlspecialchars($r['partsUsed']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Billing & Due -->
                                    <td style="padding:10px 8px; word-break:break-word;">
                                        <div>Total: <strong style="color:#0f172a;">PKR <?php echo number_format($r['totalBill'] ?? 0); ?></strong></div>
                                        <div style="font-size:0.72rem; color:#64748b;">Adv: PKR <?php echo number_format($r['advancePaid'] ?? 0); ?></div>
                                        <div style="font-size:0.75rem; font-weight:800; color:<?php echo $payColor; ?>;">
                                            Due: PKR <?php echo number_format($balance); ?>
                                        </div>
                                    </td>

                                    <!-- Job Status -->
                                    <td style="padding:10px 8px; text-align:center;">
                                        <span style="background:<?php echo $statusBg; ?>; color:<?php echo $statusColor; ?>; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:12px; display:inline-block; white-space:nowrap;">
                                            <?php echo htmlspecialchars($r['jobStatus']); ?>
                                        </span>
                                        <div style="font-size:0.68rem; color:#94a3b8; margin-top:3px;">
                                            Tech: <?php echo htmlspecialchars($r['technician'] ?? 'Munim'); ?>
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td style="padding:10px 8px; text-align:center;">
                                        <div style="display:flex; justify-content:center; gap:4px; flex-wrap:wrap;">
                                            <button class="pos-btn pos-btn-sm" style="padding:2px 5px; font-size:0.7rem; background:#f1f5f9; color:#334155;" onclick="viewRepairDetails('<?php echo htmlspecialchars($r['id']); ?>')" title="View Job Details">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button class="pos-btn pos-btn-sm" style="padding:2px 6px; font-size:0.72rem; background:#ecfdf5; color:#059669; border:1px solid #a7f3d0;" onclick="window.sendRepairWhatsApp('<?php echo htmlspecialchars($r['id']); ?>', '<?php echo htmlspecialchars($r['customerPhone'] ?? ''); ?>')" title="Send WhatsApp Ticket / Update to Customer">
                                                <i class="fa-brands fa-whatsapp"></i>
                                            </button>
                                            <button class="pos-btn pos-btn-sm" style="padding:2px 5px; font-size:0.7rem; background:#f8fafc; color:#2563eb; border:1px solid #bfdbfe;" onclick="editRepair('<?php echo htmlspecialchars($r['id']); ?>')" title="Edit / Update Work Done">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <button class="pos-btn pos-btn-sm" style="padding:2px 5px; font-size:0.7rem; background:#ecfdf5; color:#059669; border:1px solid #a7f3d0;" onclick="printRepairSlip('<?php echo htmlspecialchars($r['id']); ?>')" title="Print Customer Claim Ticket">
                                                <i class="fa-solid fa-print"></i>
                                            </button>
                                            <button class="pos-btn pos-btn-sm" style="padding:2px 5px; font-size:0.7rem; background:#fee2e2; color:#dc2626; border:1px solid #fecaca;" onclick="deleteRepair('<?php echo htmlspecialchars($r['id']); ?>')" title="Delete Ticket">
                                                <i class="fa-solid fa-trash"></i>
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

    </div>
</div>

<!-- =========================================================================
     MODAL 1: ADD / EDIT REPAIR TICKET WITH COMPLETE DETAILS
     ========================================================================= -->
<div class="pos-modal-overlay" id="repairJobModal" style="display: none; z-index:9999;">
    <div class="pos-modal" style="max-width: 780px; max-height: 90vh; overflow-y: auto; padding: 22px;">
        <div class="pos-modal-header" style="border-bottom:1.5px solid var(--pos-border); padding-bottom:12px; margin-bottom:16px;">
            <h3 class="pos-modal-title" style="margin:0; font-size:1.25rem;">
                <i class="fa-solid fa-screwdriver-wrench" style="color:var(--pos-red);"></i> 
                <span id="modalRepairHeading">New Mobile Repair Ticket</span>
            </h3>
            <button class="pos-modal-close" onclick="closeRepairModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="repairJobForm" onsubmit="saveRepairTicket(event)">
            <input type="hidden" id="repairId" name="id">

            <!-- SECTION 1: CUSTOMER & DATES -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:14px;">
                <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:#475569; margin-bottom:8px;">
                    <i class="fa-solid fa-user" style="color:var(--pos-red); margin-right:4px;"></i> 1. Customer & Receiving Information
                </div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">Customer Full Name *</label>
                        <input type="text" id="repCustomerName" class="form-input" style="padding:6px 10px; font-size:0.82rem;" required placeholder="e.g. Muhammad Tariq">
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">Customer Phone / WhatsApp *</label>
                        <input type="text" id="repCustomerPhone" class="form-input" style="padding:6px 10px; font-size:0.82rem;" required placeholder="e.g. 03001234567">
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">City / Area</label>
                        <input type="text" id="repCustomerCity" class="form-input" style="padding:6px 10px; font-size:0.82rem;" placeholder="e.g. Main Bazar Hangu">
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">Received Date & Time</label>
                        <input type="text" id="repReceivedDate" class="form-input" style="padding:6px 10px; font-size:0.82rem;" value="<?php echo date('Y-m-d H:i'); ?>">
                    </div>
                </div>
            </div>

            <!-- SECTION 2: DEVICE DETAILS -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:14px;">
                <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:#475569; margin-bottom:8px;">
                    <i class="fa-solid fa-mobile-screen-button" style="color:#2563eb; margin-right:4px;"></i> 2. Smartphone & Device Specifications
                </div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px;">
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">Device Brand *</label>
                        <select id="repDeviceBrand" class="form-select" style="padding:6px 10px; font-size:0.82rem;" required>
                            <option value="Samsung">Samsung</option>
                            <option value="Apple">Apple iPhone</option>
                            <option value="Xiaomi">Xiaomi / Redmi / Poco</option>
                            <option value="Infinix">Infinix</option>
                            <option value="Tecno">Tecno</option>
                            <option value="Vivo">Vivo</option>
                            <option value="Realme">Realme</option>
                            <option value="Oppo">Oppo</option>
                            <option value="OnePlus">OnePlus</option>
                            <option value="Google Pixel">Google Pixel</option>
                            <option value="Other">Other Brand</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">Exact Model Name *</label>
                        <input type="text" id="repDeviceModel" class="form-input" style="padding:6px 10px; font-size:0.82rem;" required placeholder="e.g. Galaxy A54 5G / iPhone 13">
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">Device Color</label>
                        <input type="text" id="repDeviceColor" class="form-input" style="padding:6px 10px; font-size:0.82rem;" placeholder="e.g. Awesome Graphite">
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">IMEI / Serial (Optional)</label>
                        <input type="text" id="repDeviceImei" class="form-input" style="padding:6px 10px; font-size:0.82rem;" placeholder="e.g. 358741098234561">
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">Passcode / Pattern (Optional)</label>
                        <input type="text" id="repDevicePasscode" class="form-input" style="padding:6px 10px; font-size:0.82rem;" placeholder="PIN or pattern description">
                    </div>
                </div>
            </div>

            <!-- SECTION 3: FAULT DIAGNOSIS & ISSUES RESOLVED (OPTIONAL / COMPLETE DETAILS) -->
            <div style="background:#fffdf5; border:1px solid #fde68a; border-radius:8px; padding:12px; margin-bottom:14px;">
                <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:#92400e; margin-bottom:8px;">
                    <i class="fa-solid fa-microscope" style="color:var(--pos-gold-dark); margin-right:4px;"></i> 3. Fault Diagnosis & Work Done / Issue Resolution
                </div>
                
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <div>
                        <label style="font-size:0.75rem; font-weight:700; color:#b91c1c;">Reported Problem / Customer Fault Description *</label>
                        <input type="text" id="repReportedFault" class="form-input" style="padding:6px 10px; font-size:0.82rem;" required placeholder="e.g. Touch screen broken, battery drain, dropped in water, dead phone">
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <div>
                            <label style="font-size:0.75rem; font-weight:700; color:#047857;">
                                <i class="fa-solid fa-circle-check"></i> Issue Resolved / Work Done (Technician Notes)
                            </label>
                            <textarea id="repIssueResolved" class="form-textarea" style="padding:6px 10px; font-size:0.8rem; min-height:55px;" placeholder="e.g. Replaced AMOLED screen with OCA lamination, repaired power IC micro-soldering, replaced battery cell..."></textarea>
                        </div>
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                <label style="font-size:0.75rem; font-weight:700; color:#0f172a;">
                                    <i class="fa-solid fa-cubes-stacked"></i> Spare Parts Used & Replaced
                                </label>
                                <a href="repair-parts.php" target="_blank" style="font-size:0.68rem; color:#2563eb; font-weight:700; text-decoration:none;">
                                    <i class="fa-solid fa-plus"></i> Parts Catalog
                                </a>
                            </div>
                            <!-- Quick Select Spare Part from Catalog -->
                            <select id="repSparePartPicker" class="form-select" style="padding:4px 6px; font-size:0.78rem; margin-bottom:4px;" onchange="onAdminRepairPartSelected(this)">
                                <option value="">-- Quick Insert Part & Auto-Add Price --</option>
                                <?php
                                    $pData = file_exists(__DIR__ . '/../backend/data/repair_parts.json') ? json_decode(file_get_contents(__DIR__ . '/../backend/data/repair_parts.json'), true) : [];
                                    foreach ($pData as $partItem) {
                                        echo '<option value="' . htmlspecialchars($partItem['id']) . '" data-price="' . htmlspecialchars($partItem['sellingPrice']) . '" data-name="' . htmlspecialchars($partItem['name']) . '" data-model="' . htmlspecialchars($partItem['deviceModel']) . '" data-warranty="' . htmlspecialchars($partItem['warranty']) . '">[' . htmlspecialchars($partItem['deviceBrand']) . '] ' . htmlspecialchars($partItem['deviceModel']) . ' - ' . htmlspecialchars($partItem['name']) . ' (PKR ' . number_format($partItem['sellingPrice']) . ')</option>';
                                    }
                                ?>
                            </select>
                            <textarea id="repPartsUsed" class="form-textarea" style="padding:6px 10px; font-size:0.8rem; min-height:50px;" placeholder="e.g. Original Samsung A54 Display Assembly, 4500mAh Battery, Charging flex..."></textarea>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <div>
                            <label style="font-size:0.75rem; font-weight:700;">Physical Condition on Receiving</label>
                            <input type="text" id="repPhysicalCondition" class="form-input" style="padding:6px 10px; font-size:0.82rem;" placeholder="e.g. Screen cracked, body scratch-free, camera working">
                        </div>
                        <div>
                            <label style="font-size:0.75rem; font-weight:700;">Warranty Coverage</label>
                            <input type="text" id="repWarranty" class="form-input" style="padding:6px 10px; font-size:0.82rem;" placeholder="e.g. 30-Day Screen Warranty / No Water Damage Warranty" value="30-Day Testing Warranty">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: FINANCIALS & BILLING -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:14px;">
                <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:#475569; margin-bottom:8px;">
                    <i class="fa-solid fa-receipt" style="color:#059669; margin-right:4px;"></i> 4. Financials & Billing
                </div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap:10px; align-items:flex-end;">
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">Parts Cost (PKR)</label>
                        <input type="number" id="repPartsCost" class="form-input" style="padding:6px 10px; font-size:0.82rem; text-align:right;" value="0" oninput="calcRepairTotal()">
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">Labor Fee (PKR)</label>
                        <input type="number" id="repLaborCharges" class="form-input" style="padding:6px 10px; font-size:0.82rem; text-align:right;" value="0" oninput="calcRepairTotal()">
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:800; color:var(--pos-red);">Total Bill (PKR)</label>
                        <input type="number" id="repTotalBill" class="form-input" style="padding:6px 10px; font-size:0.85rem; font-weight:900; color:var(--pos-red); text-align:right;" value="0" oninput="calcRepairDue()">
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:700; color:#059669;">Advance Paid (PKR)</label>
                        <input type="number" id="repAdvancePaid" class="form-input" style="padding:6px 10px; font-size:0.82rem; text-align:right;" value="0" oninput="calcRepairDue()">
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:800; color:#b91c1c;">Remaining Due (PKR)</label>
                        <input type="number" id="repBalanceDue" class="form-input" style="padding:6px 10px; font-size:0.85rem; font-weight:900; color:#b91c1c; text-align:right;" value="0" readonly>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:10px;">
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">Payment Method</label>
                        <select id="repPaymentMethod" class="form-select" style="padding:6px 10px; font-size:0.82rem;">
                            <option value="Cash">Cash</option>
                            <option value="Easypaisa">Easypaisa</option>
                            <option value="JazzCash">JazzCash</option>
                            <option value="Zindigi / Bank Transfer">Zindigi / JS Bank / Raast</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">Payment Status</label>
                        <select id="repPaymentStatus" class="form-select" style="padding:6px 10px; font-size:0.82rem;">
                            <option value="Unpaid">Unpaid / Due on Delivery</option>
                            <option value="Partial Advance">Partial Advance Paid</option>
                            <option value="Paid in Full">Paid in Full</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SECTION 5: JOB STATUS & TECHNICIAN -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:16px;">
                <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:#475569; margin-bottom:8px;">
                    <i class="fa-solid fa-list-check" style="color:var(--pos-gold-dark); margin-right:4px;"></i> 5. Job Status & Technician
                </div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px;">
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">Current Job Status *</label>
                        <select id="repJobStatus" class="form-select" style="padding:6px 10px; font-size:0.82rem;" required>
                            <option value="Received">Received / Diagnosing</option>
                            <option value="In Progress">In Progress (Lab Work)</option>
                            <option value="Completed">Completed (Testing Done)</option>
                            <option value="Ready for Pickup">Ready for Customer Pickup</option>
                            <option value="Delivered">Delivered & Closed</option>
                            <option value="Cancelled">Cancelled / Cannot Repair</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">Assigned Technician</label>
                        <input type="text" id="repTechnician" class="form-input" style="padding:6px 10px; font-size:0.82rem;" value="Master Munim">
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:700;">Est. Delivery Date</label>
                        <input type="text" id="repDeliveryDate" class="form-input" style="padding:6px 10px; font-size:0.82rem;" value="<?php echo date('Y-m-d H:i', strtotime('+1 day')); ?>">
                    </div>
                </div>
            </div>

            <div class="form-actions" style="display:flex; justify-content:flex-end; gap:10px; margin:0; padding-top:10px;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="closeRepairModal()">Cancel</button>
                <button type="submit" class="pos-btn pos-btn-primary" id="btnSaveRepair">
                    <i class="fa-solid fa-floppy-disk"></i> Save Repair Ticket
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: VIEW FULL REPAIR JOB SHEET & WHATSAPP NOTIFICATION
     ========================================================================= -->
<div class="pos-modal-overlay" id="viewRepairModal" style="display: none; z-index:9999;">
    <div class="pos-modal" style="max-width: 650px; padding: 22px; max-height:90vh; overflow-y:auto;">
        <div class="pos-modal-header" style="border-bottom:1.5px solid var(--pos-border); padding-bottom:12px; margin-bottom:16px;">
            <h3 class="pos-modal-title" style="margin:0; font-size:1.2rem;">
                <i class="fa-solid fa-receipt" style="color:var(--pos-red);"></i> 
                Repair Ticket Summary: <span id="viewRepId">REP-000</span>
            </h3>
            <button class="pos-modal-close" onclick="document.getElementById('viewRepairModal').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div id="viewRepairContent">
            <!-- Rendered by JS -->
        </div>

        <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:18px; border-top:1px solid #e2e8f0; padding-top:14px; flex-wrap:wrap;">
            <button class="pos-btn pos-btn-outline" onclick="document.getElementById('viewRepairModal').style.display='none'">Close</button>
            <button class="pos-btn" style="background:#059669; color:#fff;" onclick="sendWhatsAppReadyMessage(window.currentActiveRepair)">
                <i class="fa-brands fa-whatsapp"></i> Send WhatsApp Status Update
            </button>
            <button class="pos-btn pos-btn-primary" onclick="printRepairSlip(window.currentActiveRepair.id)">
                <i class="fa-solid fa-print"></i> Print Thermal Receipt
            </button>
        </div>
    </div>
</div>

<script>
let repairsList = <?php echo json_encode($repairs); ?>;
window.currentActiveRepair = null;

function filterRepairTable() {
    const q = document.getElementById('repairSearchInput').value.toLowerCase().trim();
    const status = document.getElementById('repairStatusFilter').value.toLowerCase();
    const brand = document.getElementById('repairBrandFilter').value.toLowerCase();
    
    const rows = document.querySelectorAll('#repairsTableBody tr.repair-row');
    let visibleCount = 0;

    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        const rBrand = (r.getAttribute('data-brand') || '').toLowerCase();
        const rStatus = (r.getAttribute('data-status') || '').toLowerCase();

        const matchQ = !q || text.includes(q);
        const matchStatus = status === 'all' || rStatus === status;
        const matchBrand = brand === 'all' || rBrand === brand;

        if (matchQ && matchStatus && matchBrand) {
            r.style.display = '';
            visibleCount++;
        } else {
            r.style.display = 'none';
        }
    });

    const countEl = document.getElementById('filteredCountText');
    if (countEl) countEl.textContent = visibleCount;
}

function onAdminRepairPartSelected(sel) {
    if (!sel || !sel.value) return;
    const opt = sel.options[sel.selectedIndex];
    const price = parseFloat(opt.getAttribute('data-price')) || 0;
    const name = opt.getAttribute('data-name') || '';
    const model = opt.getAttribute('data-model') || '';
    const warranty = opt.getAttribute('data-warranty') || '';

    const partsUsed = document.getElementById('repPartsUsed');
    if (partsUsed) {
        const cur = partsUsed.value.trim();
        const add = `${model} ${name} [${warranty}]`;
        partsUsed.value = cur ? (cur + '\n' + add) : add;
    }

    const costField = document.getElementById('repPartsCost');
    if (costField) {
        const curCost = parseFloat(costField.value) || 0;
        costField.value = curCost + price;
        calcRepairTotal();
    }

    if (warranty && !document.getElementById('repWarranty').value) {
        document.getElementById('repWarranty').value = warranty;
    }
}

function calcRepairTotal() {
    const parts = parseFloat(document.getElementById('repPartsCost').value) || 0;
    const labor = parseFloat(document.getElementById('repLaborCharges').value) || 0;
    const total = parts + labor;
    document.getElementById('repTotalBill').value = total;
    calcRepairDue();
}

function calcRepairDue() {
    const total = parseFloat(document.getElementById('repTotalBill').value) || 0;
    const adv = parseFloat(document.getElementById('repAdvancePaid').value) || 0;
    const due = Math.max(0, total - adv);
    document.getElementById('repBalanceDue').value = due;

    const statusSel = document.getElementById('repPaymentStatus');
    if (statusSel) {
        if (adv >= total && total > 0) {
            statusSel.value = 'Paid in Full';
        } else if (adv > 0) {
            statusSel.value = 'Partial Advance';
        } else {
            statusSel.value = 'Unpaid';
        }
    }
}

function openRepairModal(id = null) {
    document.getElementById('repairJobForm').reset();
    document.getElementById('repairId').value = '';
    document.getElementById('modalRepairHeading').textContent = 'New Mobile Repair Ticket';
    document.getElementById('repReceivedDate').value = new Date().toISOString().slice(0, 16).replace('T', ' ');
    document.getElementById('repDeliveryDate').value = new Date(Date.now() + 86400000).toISOString().slice(0, 16).replace('T', ' ');

    if (id) {
        const item = repairsList.find(r => r.id === id);
        if (item) {
            document.getElementById('repairId').value = item.id;
            document.getElementById('modalRepairHeading').textContent = 'Edit Repair Ticket: ' + item.id;
            document.getElementById('repCustomerName').value = item.customerName || '';
            document.getElementById('repCustomerPhone').value = item.customerPhone || '';
            document.getElementById('repCustomerCity').value = item.customerCity || '';
            document.getElementById('repReceivedDate').value = item.receivedDate || '';
            document.getElementById('repDeviceBrand').value = item.deviceBrand || 'Samsung';
            document.getElementById('repDeviceModel').value = item.deviceModel || '';
            document.getElementById('repDeviceColor').value = item.deviceColor || '';
            document.getElementById('repDeviceImei').value = item.deviceImei || '';
            document.getElementById('repDevicePasscode').value = item.devicePasscode || '';
            document.getElementById('repReportedFault').value = item.reportedFault || '';
            document.getElementById('repPhysicalCondition').value = item.physicalCondition || '';
            document.getElementById('repIssueResolved').value = item.issueResolved || '';
            document.getElementById('repPartsUsed').value = item.partsUsed || '';
            document.getElementById('repPartsCost').value = item.partsCost || 0;
            document.getElementById('repLaborCharges').value = item.laborCharges || 0;
            document.getElementById('repTotalBill').value = item.totalBill || 0;
            document.getElementById('repAdvancePaid').value = item.advancePaid || 0;
            document.getElementById('repBalanceDue').value = item.balanceDue || 0;
            document.getElementById('repPaymentMethod').value = item.paymentMethod || 'Cash';
            document.getElementById('repPaymentStatus').value = item.paymentStatus || 'Unpaid';
            document.getElementById('repJobStatus').value = item.jobStatus || 'In Progress';
            document.getElementById('repTechnician').value = item.technician || 'Master Munim';
            document.getElementById('repWarranty').value = item.warranty || '30-Day Testing Warranty';
        }
    }
    document.getElementById('repairJobModal').style.display = 'flex';
}

function closeRepairModal() {
    document.getElementById('repairJobModal').style.display = 'none';
}

function editRepair(id) {
    openRepairModal(id);
}

function saveRepairTicket(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveRepair');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving Ticket...';

    const payload = {
        action: 'save',
        id: document.getElementById('repairId').value,
        customerName: document.getElementById('repCustomerName').value,
        customerPhone: document.getElementById('repCustomerPhone').value,
        customerCity: document.getElementById('repCustomerCity').value,
        receivedDate: document.getElementById('repReceivedDate').value,
        deviceBrand: document.getElementById('repDeviceBrand').value,
        deviceModel: document.getElementById('repDeviceModel').value,
        deviceColor: document.getElementById('repDeviceColor').value,
        deviceImei: document.getElementById('repDeviceImei').value,
        devicePasscode: document.getElementById('repDevicePasscode').value,
        reportedFault: document.getElementById('repReportedFault').value,
        physicalCondition: document.getElementById('repPhysicalCondition').value,
        issueResolved: document.getElementById('repIssueResolved').value,
        partsUsed: document.getElementById('repPartsUsed').value,
        partsCost: parseFloat(document.getElementById('repPartsCost').value) || 0,
        laborCharges: parseFloat(document.getElementById('repLaborCharges').value) || 0,
        totalBill: parseFloat(document.getElementById('repTotalBill').value) || 0,
        advancePaid: parseFloat(document.getElementById('repAdvancePaid').value) || 0,
        balanceDue: parseFloat(document.getElementById('repBalanceDue').value) || 0,
        paymentMethod: document.getElementById('repPaymentMethod').value,
        paymentStatus: document.getElementById('repPaymentStatus').value,
        jobStatus: document.getElementById('repJobStatus').value,
        technician: document.getElementById('repTechnician').value,
        warranty: document.getElementById('repWarranty').value,
        deliveryDate: document.getElementById('repDeliveryDate').value
    };

    fetch('../backend/repairs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Repair Ticket';
        if (data.status === 'success') {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Could not save repair ticket.'));
        }
    })
    .catch(err => {
        console.error(err);
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Repair Ticket';
        alert('Network error communicating with server.');
    });
}

function deleteRepair(id) {
    if (!confirm('Are you sure you want to delete repair ticket ' + id + '? This action cannot be undone.')) return;

    fetch('../backend/repairs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete ticket.'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error deleting repair ticket.');
    });
}

function viewRepairDetails(id) {
    const item = repairsList.find(r => r.id === id);
    if (!item) return;

    window.currentActiveRepair = item;
    document.getElementById('viewRepId').textContent = item.id;

    const html = `
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px; font-size:0.85rem;">
            <div style="background:#f8fafc; padding:10px 12px; border-radius:8px;">
                <div style="font-size:0.75rem; color:#64748b; font-weight:800; text-transform:uppercase;">Customer Details</div>
                <div style="font-weight:900; color:#0f172a; font-size:1rem; margin-top:2px;">${item.customerName}</div>
                <div style="color:#059669; font-weight:700;"><i class="fa-brands fa-whatsapp"></i> ${item.customerPhone}</div>
                <div style="color:#64748b; font-size:0.78rem;">${item.customerCity || 'Hangu'}</div>
            </div>
            <div style="background:#f8fafc; padding:10px 12px; border-radius:8px;">
                <div style="font-size:0.75rem; color:#64748b; font-weight:800; text-transform:uppercase;">Device & Model</div>
                <div style="font-weight:900; color:#0f172a; font-size:1rem; margin-top:2px;">${item.deviceBrand} ${item.deviceModel}</div>
                <div style="color:#64748b; font-size:0.78rem;">Color: <strong>${item.deviceColor || 'N/A'}</strong> | Passcode: <strong>${item.devicePasscode || 'None'}</strong></div>
                <div style="color:#94a3b8; font-size:0.72rem; font-family:monospace;">IMEI: ${item.deviceImei || 'N/A'}</div>
            </div>
        </div>

        <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px 12px; margin-bottom:12px;">
            <div style="font-size:0.75rem; color:#991b1b; font-weight:800; text-transform:uppercase;"><i class="fa-solid fa-triangle-exclamation"></i> Reported Problem</div>
            <div style="color:#b91c1c; font-size:0.9rem; font-weight:700; margin-top:3px;">${item.reportedFault}</div>
        </div>

        ${item.issueResolved ? `
            <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:10px 12px; margin-bottom:12px;">
                <div style="font-size:0.75rem; color:#047857; font-weight:800; text-transform:uppercase;"><i class="fa-solid fa-circle-check"></i> Issue Resolved & Work Done</div>
                <div style="color:#065f46; font-size:0.88rem; margin-top:3px; line-height:1.4;">${item.issueResolved}</div>
            </div>
        ` : ''}

        ${item.partsUsed ? `
            <div style="background:#fffdf5; border:1px solid #fde68a; border-radius:8px; padding:10px 12px; margin-bottom:12px;">
                <div style="font-size:0.75rem; color:#92400e; font-weight:800; text-transform:uppercase;"><i class="fa-solid fa-cubes-stacked"></i> Spare Parts Used</div>
                <div style="color:#78350f; font-size:0.85rem; margin-top:2px;">${item.partsUsed}</div>
            </div>
        ` : ''}

        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:12px;">
            <div style="font-size:0.75rem; color:#475569; font-weight:800; text-transform:uppercase; margin-bottom:6px;">Financial Breakdown</div>
            <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:0.85rem;">
                <span>Parts Replacement Cost:</span>
                <strong>PKR ${(item.partsCost || 0).toLocaleString()}</strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:0.85rem;">
                <span>Technician Labor & Service:</span>
                <strong>PKR ${(item.laborCharges || 0).toLocaleString()}</strong>
            </div>
            <div style="display:flex; justify-content:space-between; border-top:1px solid #cbd5e1; padding-top:6px; font-size:1rem; font-weight:900; color:var(--pos-red);">
                <span>Total Bill Amount:</span>
                <span>PKR ${(item.totalBill || 0).toLocaleString()}</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-top:4px; font-size:0.85rem; color:#059669;">
                <span>Advance Paid:</span>
                <strong>PKR ${(item.advancePaid || 0).toLocaleString()}</strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-top:2px; font-size:0.9rem; font-weight:900; color:#dc2626;">
                <span>Remaining Balance Due:</span>
                <span>PKR ${(item.balanceDue || 0).toLocaleString()}</span>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; font-size:0.8rem; color:#64748b;">
            <div>Status: <strong style="color:#0f172a;">${item.jobStatus}</strong></div>
            <div>Warranty: <strong style="color:#059669;">${item.warranty || 'None'}</strong></div>
            <div>Tech: <strong style="color:#0f172a;">${item.technician || 'Master Munim'}</strong></div>
        </div>
    `;

    document.getElementById('viewRepairContent').innerHTML = html;
    document.getElementById('viewRepairModal').style.display = 'flex';
}

function sendWhatsAppReadyMessage(item) {
    if (!item) return;
    const phone = item.customerPhone.replace(/[^0-9]/g, '');
    const cleanPhone = phone.startsWith('0') ? '92' + phone.substring(1) : phone;

    const msg = `*SAFDAR MOBILE STORE - REPAIR UPDATE*\n` +
        `-----------------------------------------\n` +
        `Dear *${item.customerName}*,\n` +
        `Your device repair job has been updated:\n\n` +
        `📱 *Device:* ${item.deviceBrand} ${item.deviceModel}\n` +
        `🔖 *Ticket #:* ${item.id}\n` +
        `🔧 *Fault:* ${item.reportedFault}\n` +
        `✅ *Work Done:* ${item.issueResolved || 'Completed & Tested'}\n` +
        `💰 *Total Bill:* PKR ${(item.totalBill || 0).toLocaleString()}\n` +
        `💵 *Balance Due:* PKR ${(item.balanceDue || 0).toLocaleString()}\n` +
        `⚡ *Status:* *${item.jobStatus}*\n` +
        `🛡️ *Warranty:* ${item.warranty || '30 Days'}\n` +
        `-----------------------------------------\n` +
        `📍 *Safdar Mobile Store*, Main Bazar Hangu\n` +
        `📞 Helpline / WhatsApp: 03339688007`;

    window.open(`https://wa.me/${cleanPhone}?text=${encodeURIComponent(msg)}`, '_blank');
}

function printRepairSlip(id) {
    const item = repairsList.find(r => r.id === id);
    if (!item) return;

    const printWindow = window.open('', '_blank', 'width=450,height=700');
    const slipHtml = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Repair Ticket - ${item.id}</title>
            <style>
                @page {
                    size: 80mm auto;
                    margin: 0mm;
                }
                * {
                    box-sizing: border-box;
                    margin: 0;
                    padding: 0;
                }
                body {
                    font-family: 'Courier New', Courier, monospace, sans-serif;
                    font-size: 11px;
                    line-height: 1.35;
                    color: #000;
                    background: #fff;
                    width: 72mm;
                    max-width: 72mm;
                    margin: 0 auto;
                    padding: 6px 3px;
                }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .bold { font-weight: bold; }
                .divider {
                    border-top: 1px dashed #000;
                    margin: 5px 0;
                }
                .double-divider {
                    border-top: 2px solid #000;
                    margin: 6px 0;
                }
                table.info-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 11px;
                    table-layout: fixed;
                }
                table.info-table td {
                    padding: 1.5px 0;
                    vertical-align: top;
                }
                .label-col {
                    width: 40%;
                    font-weight: bold;
                    color: #000;
                }
                .val-col {
                    width: 60%;
                    text-align: right;
                    word-break: break-word;
                }
                .financial-box {
                    border: 1.5px solid #000;
                    padding: 5px 6px;
                    margin: 6px 0;
                    background: #fff;
                }
                .financial-row {
                    display: flex;
                    justify-content: space-between;
                    font-size: 11px;
                    padding: 1px 0;
                }
                .due-row {
                    display: flex;
                    justify-content: space-between;
                    font-size: 13px;
                    font-weight: 900;
                    border-top: 1px dashed #000;
                    margin-top: 3px;
                    padding-top: 3px;
                }
                .barcode-box {
                    text-align: center;
                    margin: 4px 0;
                    font-size: 13px;
                    letter-spacing: 4px;
                    font-weight: bold;
                }
                .terms-text {
                    font-size: 9.5px;
                    line-height: 1.25;
                    margin-top: 4px;
                }
                @media print {
                    html, body {
                        width: 72mm !important;
                        max-width: 72mm !important;
                        margin: 0 auto !important;
                        padding: 3px 2px !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="text-center bold" style="font-size:15px; letter-spacing:0.5px;">SAFDAR MOBILE STORE</div>
            <div class="text-center" style="font-size:10px;">Opp. Patt Bazar, Eidgah Road, Hangu</div>
            <div class="text-center bold" style="font-size:11px;">Helpline / WhatsApp: 03339688007</div>
            
            <div class="divider"></div>
            <div class="text-center bold" style="font-size:12px; letter-spacing:1px;">*** REPAIR CLAIM TICKET ***</div>
            <div class="barcode-box">*${item.id}*</div>
            <div class="divider"></div>

            <table class="info-table">
                <tr>
                    <td class="label-col">Ticket No:</td>
                    <td class="val-col bold" style="font-size:12px;">${item.id}</td>
                </tr>
                <tr>
                    <td class="label-col">Received:</td>
                    <td class="val-col">${item.receivedDate}</td>
                </tr>
                ${item.deliveryDate ? `
                <tr>
                    <td class="label-col">Est. Delivery:</td>
                    <td class="val-col">${item.deliveryDate}</td>
                </tr>` : ''}
                <tr>
                    <td class="label-col">Customer:</td>
                    <td class="val-col bold">${item.customerName}</td>
                </tr>
                <tr>
                    <td class="label-col">Contact:</td>
                    <td class="val-col">${item.customerPhone}</td>
                </tr>
                ${item.customerCity ? `
                <tr>
                    <td class="label-col">City / Area:</td>
                    <td class="val-col">${item.customerCity}</td>
                </tr>` : ''}
            </table>

            <div class="divider"></div>

            <table class="info-table">
                <tr>
                    <td class="label-col">Device:</td>
                    <td class="val-col bold">${item.deviceBrand} ${item.deviceModel}</td>
                </tr>
                ${item.deviceColor ? `
                <tr>
                    <td class="label-col">Color:</td>
                    <td class="val-col">${item.deviceColor}</td>
                </tr>` : ''}
                ${item.deviceImei ? `
                <tr>
                    <td class="label-col">IMEI / S/N:</td>
                    <td class="val-col" style="font-size:10px;">${item.deviceImei}</td>
                </tr>` : ''}
                ${item.devicePasscode ? `
                <tr>
                    <td class="label-col">Passcode:</td>
                    <td class="val-col">${item.devicePasscode}</td>
                </tr>` : ''}
            </table>

            <div class="divider"></div>

            <div style="margin:3px 0;">
                <div class="bold" style="font-size:10.5px;">REPORTED FAULT:</div>
                <div style="font-size:10.5px; padding-left:2px; word-break:break-word;">${item.reportedFault}</div>
            </div>

            ${item.partsUsed ? `
            <div style="margin:3px 0;">
                <div class="bold" style="font-size:10.5px;">PARTS REPLACED:</div>
                <div style="font-size:10.5px; padding-left:2px; word-break:break-word;">${item.partsUsed}</div>
            </div>` : ''}

            ${item.issueResolved ? `
            <div style="margin:3px 0;">
                <div class="bold" style="font-size:10.5px;">WORK DONE:</div>
                <div style="font-size:10.5px; padding-left:2px; word-break:break-word;">${item.issueResolved}</div>
            </div>` : ''}

            <!-- FINANCIAL BREAKDOWN -->
            <div class="financial-box">
                <div class="financial-row">
                    <span>Total Repair Bill:</span>
                    <span class="bold">PKR ${(item.totalBill || 0).toLocaleString()}</span>
                </div>
                <div class="financial-row">
                    <span>Advance Received:</span>
                    <span>PKR ${(item.advancePaid || 0).toLocaleString()}</span>
                </div>
                <div class="due-row">
                    <span>BALANCE DUE:</span>
                    <span>PKR ${(item.balanceDue || 0).toLocaleString()}</span>
                </div>
            </div>

            <table class="info-table" style="font-size:10px;">
                <tr>
                    <td class="label-col">Job Status:</td>
                    <td class="val-col bold">${item.jobStatus}</td>
                </tr>
                <tr>
                    <td class="label-col">Warranty:</td>
                    <td class="val-col">${item.warranty || 'Testing Warranty'}</td>
                </tr>
                <tr>
                    <td class="label-col">Technician:</td>
                    <td class="val-col">${item.technician || 'Master Munim'}</td>
                </tr>
            </table>

            <div class="divider"></div>

            <div class="terms-text">
                <strong>TERMS & CONDITIONS:</strong><br>
                1. Please present this ticket when claiming device.<br>
                2. Devices uncollected after 30 days are not store liability.<br>
                3. No warranty on water damage or physical breakage.<br>
                4. Check device thoroughly before leaving counter.
            </div>

            <div class="double-divider"></div>
            <div class="text-center bold" style="font-size:11px; margin-top:3px;">
                THANK YOU FOR YOUR TRUST!
            </div>
            <div class="text-center" style="font-size:9px; color:#555; margin-top:2px;">
                Safdar Mobile Store POS System
            </div>
        </body>
        </html>
    `;

    printWindow.document.open();
    printWindow.document.write(slipHtml);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 400);
}

// Auto-open repair ticket modal or filter if URL has ?ticket=... or ?id=... or ?search=...
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const ticket = urlParams.get('ticket') || urlParams.get('id') || urlParams.get('receipt');
    const search = urlParams.get('search') || urlParams.get('q');

    if ((search || ticket) && document.getElementById('repairSearchInput')) {
        document.getElementById('repairSearchInput').value = search || ticket;
        filterRepairTable();
    }

    if (ticket && typeof repairsList !== 'undefined' && Array.isArray(repairsList)) {
        const match = repairsList.find(r => 
            r.id === ticket || 
            (r.ticketNo && r.ticketNo === ticket) || 
            (r.ticketNo && r.ticketNo.toLowerCase() === ticket.toLowerCase())
        );
        if (match) {
            viewRepairTicket(match.id);
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
