<?php
$currentPage = 'nadra-kiosk';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$records = get_json_file('nadra_kiosk') ?? [];

$message = '';
$messageType = '';

// Handle POST: Add new record, Update status, or Delete record
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? 'add_record';

    if ($action === 'delete_record') {
        $delId = $_POST['record_id'] ?? '';
        if ($delId) {
            $records = array_values(array_filter($records, function($r) use ($delId) {
                return ($r['id'] ?? '') !== $delId;
            }));
            save_json_file('nadra_kiosk', $records);
            $message = 'Citizen application record deleted successfully.';
            $messageType = 'success';
        }
    } elseif ($action === 'update_status') {
        $recId = $_POST['record_id'] ?? '';
        $newStatus = $_POST['new_status'] ?? 'in_process';
        if ($recId) {
            foreach ($records as &$r) {
                if ($r['id'] === $recId) {
                    $r['status'] = $newStatus;
                    break;
                }
            }
            save_json_file('nadra_kiosk', $records);
            $message = "Application status updated to " . strtoupper(str_replace('_', ' ', $newStatus));
            $messageType = 'success';
        }
    } elseif ($action === 'add_record') {
        $serviceType = trim($_POST['serviceType'] ?? 'nadra_cnic');
        $citizenName = trim($_POST['citizenName'] ?? '');
        $fatherName = trim($_POST['fatherName'] ?? '');
        $citizenCnic = trim($_POST['citizenCnic'] ?? '');
        $contactPhone = trim($_POST['contactPhone'] ?? '');
        $address = trim($_POST['address'] ?? 'Hangu, KPK');
        $urgency = trim($_POST['urgency'] ?? 'normal');
        $govtFee = floatval($_POST['govtFee'] ?? 0);
        $shopFee = floatval($_POST['shopFee'] ?? 0);
        $deliveryDate = trim($_POST['deliveryDate'] ?? date('Y-m-d', strtotime('+7 days')));
        $status = trim($_POST['status'] ?? 'in_process');
        $notes = trim($_POST['notes'] ?? '');
        $trackingNo = trim($_POST['trackingNo'] ?? '');

        // Friendly Service Name
        $serviceName = 'Citizen Facilitation Service';
        if ($serviceType === 'nadra_cnic') $serviceName = 'Smart CNIC (New / Renewal / Modification)';
        elseif ($serviceType === 'nadra_frc') $serviceName = 'Family Registration Certificate (FRC)';
        elseif ($serviceType === 'nadra_crc') $serviceName = 'Child Registration Certificate (CRC / B-Form)';
        elseif ($serviceType === 'police_clearance') $serviceName = 'Police Character Clearance Certificate';
        elseif ($serviceType === 'domicile') $serviceName = 'District Hangu Domicile & PRC Certificate';
        elseif ($serviceType === 'attestation') $serviceName = 'Document Attestation & Legal Affidavit';

        if (empty($citizenName)) {
            $message = 'Citizen full name is required.';
            $messageType = 'danger';
        } else {
            if (empty($trackingNo)) {
                $prefix = 'APP';
                if ($serviceType === 'nadra_cnic') $prefix = 'NADRA';
                elseif ($serviceType === 'nadra_frc') $prefix = 'FRC';
                elseif ($serviceType === 'nadra_crc') $prefix = 'CRC';
                elseif ($serviceType === 'police_clearance') $prefix = 'POL';
                elseif ($serviceType === 'domicile') $prefix = 'DOM';
                $trackingNo = $prefix . '-' . rand(100000, 999999);
            }

            $docs = $_POST['docs'] ?? [];
            $totalFee = $govtFee + $shopFee;

            $newRecord = [
                'id' => 'nk-' . time() . '-' . rand(100, 999),
                'trackingNo' => $trackingNo,
                'serviceType' => $serviceType,
                'serviceName' => $serviceName,
                'urgency' => $urgency,
                'citizenName' => $citizenName,
                'fatherName' => $fatherName,
                'citizenCnic' => $citizenCnic,
                'contactPhone' => $contactPhone,
                'address' => $address,
                'govtFee' => $govtFee,
                'shopFee' => $shopFee,
                'totalFee' => $totalFee,
                'paymentStatus' => 'paid',
                'status' => $status,
                'documentsSubmitted' => is_array($docs) ? array_values($docs) : [],
                'deliveryDate' => $deliveryDate,
                'notes' => $notes,
                'loggedBy' => $user['username'] ?? 'admin',
                'createdAt' => date('c')
            ];

            array_unshift($records, $newRecord);
            save_json_file('nadra_kiosk', $records);

            if (class_exists('SecurityLogger')) {
                SecurityLogger::logEvent($user['username'] ?? 'admin', 'admin', 'CITIZEN_KIOSK_RECORD_ADDED', "Added {$serviceName} application {$trackingNo} for {$citizenName}");
            }

            $message = "Application {$trackingNo} recorded successfully! Shop Facilitation Fee: +PKR " . number_format($shopFee);
            $messageType = 'success';
        }
    }
}

// Calculate Summary Metrics
$totalApps = count($records);
$countInProcess = 0;
$countReady = 0;
$countDelivered = 0;
$totalShopEarnings = 0;
$totalGovtFees = 0;

$nadraVolume = 0;
$nadraEarnings = 0;
$policeVolume = 0;
$policeEarnings = 0;
$domicileVolume = 0;
$domicileEarnings = 0;

foreach ($records as $r) {
    $st = $r['status'] ?? 'in_process';
    $shopF = floatval($r['shopFee'] ?? 0);
    $govtF = floatval($r['govtFee'] ?? 0);
    $type = $r['serviceType'] ?? 'nadra_cnic';

    $totalShopEarnings += $shopF;
    $totalGovtFees += $govtF;

    if ($st === 'in_process' || $st === 'document_submitted') {
        $countInProcess++;
    } elseif ($st === 'ready' || $st === 'approved') {
        $countReady++;
    } elseif ($st === 'delivered') {
        $countDelivered++;
    }

    if (strpos($type, 'nadra') === 0) {
        $nadraVolume++;
        $nadraEarnings += $shopF;
    } elseif ($type === 'police_clearance') {
        $policeVolume++;
        $policeEarnings += $shopF;
    } elseif ($type === 'domicile') {
        $domicileVolume++;
        $domicileEarnings += $shopF;
    }
}
?>

<div class="pos-main" style="min-width:0; max-width:calc(100vw - var(--pos-sidebar-w)); overflow-x:hidden; box-sizing:border-box;">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content" style="max-width:100%; width:100%; box-sizing:border-box; overflow-x:hidden; padding:20px 24px;">
        <!-- Page Header -->
        <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
            <div>
                <h1 style="font-size:1.45rem; margin:0;">
                    <i class="fa-solid fa-id-card" style="color:var(--pos-red); margin-right:8px;"></i> NADRA & Citizen Facilitation Kiosk
                </h1>
                <p class="page-header-sub" style="margin-top:2px;">
                    Smart CNIC, FRC, Police Character Clearance, and District Hangu Domicile application management
                </p>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="pos-btn pos-btn-primary" onclick="openNewCitizenModal()">
                    <i class="fa-solid fa-circle-plus"></i> New Application
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="login-error" style="background:<?php echo $messageType === 'success' ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)'; ?>; border:1px solid <?php echo $messageType === 'success' ? '#a7f3d0' : '#fecaca'; ?>; color:<?php echo $messageType === 'success' ? '#059669' : '#dc2626'; ?>; margin-bottom:18px; padding:12px 16px; border-radius:8px; font-weight:700;">
                <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation'; ?>"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- 1. CORE STATS CARDS -->
        <div class="stats-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:18px; width:100%; box-sizing:border-box;">
            
            <!-- 1. Total Applications -->
            <div class="stat-card" style="border-left:4px solid #3b82f6; background:linear-gradient(180deg, #ffffff 0%, #eff6ff 100%); padding:14px;">
                <div class="stat-icon blue" style="background:rgba(59,130,246,0.15); color:#2563eb; width:40px; height:40px; font-size:1.1rem;">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label" style="color:#1e40af; font-weight:800; font-size:0.72rem;">TOTAL CITIZEN APPLICATIONS</div>
                    <div class="stat-value" style="color:#2563eb; font-size:1.3rem;">
                        <?php echo $totalApps; ?> Cases
                    </div>
                    <div style="font-size:0.72rem; color:#6b7280; margin-top:2px;">
                        All Kiosk Service Requests
                    </div>
                </div>
            </div>

            <!-- 2. In Process -->
            <div class="stat-card" style="border-left:4px solid #f59e0b; background:linear-gradient(180deg, #ffffff 0%, #fffbeb 100%); padding:14px;">
                <div class="stat-icon gold" style="background:rgba(245,158,11,0.15); color:#d97706; width:40px; height:40px; font-size:1.1rem;">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label" style="color:#92400e; font-weight:800; font-size:0.72rem;">IN PROCESS / SUBMITTED</div>
                    <div class="stat-value" style="color:#d97706; font-size:1.3rem;">
                        <?php echo $countInProcess; ?> Pending
                    </div>
                    <div style="font-size:0.72rem; color:#6b7280; margin-top:2px;">
                        Under Government Processing
                    </div>
                </div>
            </div>

            <!-- 3. Ready / Approved -->
            <div class="stat-card" style="border-left:4px solid #10b981; background:linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%); padding:14px;">
                <div class="stat-icon green" style="background:rgba(16,185,129,0.15); color:#059669; width:40px; height:40px; font-size:1.1rem;">
                    <i class="fa-solid fa-certificate"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label" style="color:#065f46; font-weight:800; font-size:0.72rem;">READY FOR CITIZEN PICKUP</div>
                    <div class="stat-value" style="color:#059669; font-size:1.3rem;">
                        <?php echo $countReady; ?> Ready
                    </div>
                    <div style="font-size:0.72rem; color:#6b7280; margin-top:2px;">
                        Approved Certificates at Store
                    </div>
                </div>
            </div>

            <!-- 4. Shop Facilitation Earnings -->
            <div class="stat-card" style="border-left:4px solid var(--pos-gold); background:linear-gradient(180deg, #ffffff 0%, #fffdf5 100%); padding:14px;">
                <div class="stat-icon gold" style="background:rgba(244,196,48,0.2); color:#b45309; width:40px; height:40px; font-size:1.1rem;">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label" style="color:#92400e; font-weight:800; font-size:0.72rem;">SHOP FACILITATION PROFIT</div>
                    <div class="stat-value" style="color:#b45309; font-size:1.3rem;">
                        +PKR <?php echo number_format($totalShopEarnings); ?>
                    </div>
                    <div style="font-size:0.72rem; color:#6b7280; margin-top:2px;">
                        Net Service Fee Earnings
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. SERVICE BREAKDOWN QUICK TILES -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; margin-bottom:18px; width:100%; box-sizing:border-box;">
            <!-- NADRA Tile -->
            <div class="pos-card" style="background:#fff; border:1px solid #d1fae5; padding:14px; margin:0;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="background:#00a859; color:#fff; width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.85rem;">
                            <i class="fa-solid fa-id-card"></i>
                        </span>
                        <div>
                            <strong style="font-size:0.92rem; color:#065f46;">NADRA CNIC & FRC</strong>
                            <div style="font-size:0.68rem; color:#6b7280;">Smart CNIC, FRC & B-Forms</div>
                        </div>
                    </div>
                    <span style="background:#ecfdf5; color:#059669; font-weight:800; font-size:0.7rem; padding:2px 6px; border-radius:10px;"><?php echo $nadraVolume; ?> Cases</span>
                </div>
                <div style="display:flex; justify-content:space-between; border-top:1px dashed #e2e8f0; padding-top:6px; font-size:0.75rem;">
                    <span style="color:#64748b;">Shop Profit:</span>
                    <strong style="color:#059669; font-weight:900;">+PKR <?php echo number_format($nadraEarnings); ?></strong>
                </div>
            </div>

            <!-- Police Clearance Tile -->
            <div class="pos-card" style="background:#fff; border:1px solid #cbd5e1; padding:14px; margin:0;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="background:#0f172a; color:var(--pos-gold); width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.85rem;">
                            <i class="fa-solid fa-shield"></i>
                        </span>
                        <div>
                            <strong style="font-size:0.92rem; color:#0f172a;">Police Clearance</strong>
                            <div style="font-size:0.68rem; color:#6b7280;">Character Verification</div>
                        </div>
                    </div>
                    <span style="background:#f1f5f9; color:#334155; font-weight:800; font-size:0.7rem; padding:2px 6px; border-radius:10px;"><?php echo $policeVolume; ?> Cases</span>
                </div>
                <div style="display:flex; justify-content:space-between; border-top:1px dashed #e2e8f0; padding-top:6px; font-size:0.75rem;">
                    <span style="color:#64748b;">Shop Profit:</span>
                    <strong style="color:#059669; font-weight:900;">+PKR <?php echo number_format($policeEarnings); ?></strong>
                </div>
            </div>

            <!-- Domicile Tile -->
            <div class="pos-card" style="background:#fff; border:1px solid #fde68a; padding:14px; margin:0;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="background:#d97706; color:#fff; width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.85rem;">
                            <i class="fa-solid fa-landmark"></i>
                        </span>
                        <div>
                            <strong style="font-size:0.92rem; color:#92400e;">District Hangu Domicile</strong>
                            <div style="font-size:0.68rem; color:#6b7280;">Domicile & PRC Certificates</div>
                        </div>
                    </div>
                    <span style="background:#fffbeb; color:#b45309; font-weight:800; font-size:0.7rem; padding:2px 6px; border-radius:10px;"><?php echo $domicileVolume; ?> Cases</span>
                </div>
                <div style="display:flex; justify-content:space-between; border-top:1px dashed #e2e8f0; padding-top:6px; font-size:0.75rem;">
                    <span style="color:#64748b;">Shop Profit:</span>
                    <strong style="color:#059669; font-weight:900;">+PKR <?php echo number_format($domicileEarnings); ?></strong>
                </div>
            </div>
        </div>

        <!-- 3. APPLICATIONS TABLE SECTION -->
        <div class="pos-card" style="padding:0; overflow:hidden; width:100%; max-width:100%; box-sizing:border-box;">
            <!-- Filter Bar & Search -->
            <div style="padding:12px 16px; border-bottom:1px solid var(--pos-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; background:#fafafa; width:100%; box-sizing:border-box;">
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" class="pos-btn pos-btn-sm nk-filter-btn active" onclick="filterCitizenTable('all', this)" style="padding:4px 8px; font-size:0.75rem;">
                        All (<?php echo count($records); ?>)
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm nk-filter-btn" style="color:#059669; padding:4px 8px; font-size:0.75rem;" onclick="filterCitizenTable('nadra', this)">
                        <i class="fa-solid fa-id-card"></i> NADRA (<?php echo $nadraVolume; ?>)
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm nk-filter-btn" style="color:#334155; padding:4px 8px; font-size:0.75rem;" onclick="filterCitizenTable('police_clearance', this)">
                        <i class="fa-solid fa-shield"></i> Police (<?php echo $policeVolume; ?>)
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm nk-filter-btn" style="color:#b45309; padding:4px 8px; font-size:0.75rem;" onclick="filterCitizenTable('domicile', this)">
                        <i class="fa-solid fa-landmark"></i> Domicile (<?php echo $domicileVolume; ?>)
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm nk-filter-btn" style="color:#d97706; padding:4px 8px; font-size:0.75rem;" onclick="filterCitizenTable('in_process', this)">
                        <i class="fa-solid fa-clock"></i> In Process (<?php echo $countInProcess; ?>)
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm nk-filter-btn" style="color:#10b981; padding:4px 8px; font-size:0.75rem;" onclick="filterCitizenTable('ready', this)">
                        <i class="fa-solid fa-circle-check"></i> Ready (<?php echo $countReady + $countDelivered; ?>)
                    </button>
                </div>

                <div style="display:flex; align-items:center; gap:8px;">
                    <div class="data-table-search" style="min-width:180px; padding:6px 10px;">
                        <i class="fa-solid fa-magnifying-glass" style="font-size:0.8rem;"></i>
                        <input type="text" id="nkSearchInput" placeholder="Search Tracking, CNIC, Phone..." oninput="searchCitizenTable()" style="font-size:0.8rem;">
                    </div>
                </div>
            </div>

            <!-- Table Wrapper -->
            <div class="data-table-wrap" style="border:none; margin:0; width:100%; max-width:100%; overflow-x:hidden; box-sizing:border-box;">
                <table class="data-table" id="citizenTable" style="margin:0; width:100%; min-width:100%; table-layout:fixed; border-collapse:collapse;">
                    <thead>
                        <tr style="font-size:0.75rem;">
                            <th style="width:15%; padding:10px 8px;">Tracking ID & Date</th>
                            <th style="width:15%; padding:10px 8px;">Service Category</th>
                            <th style="width:20%; padding:10px 8px;">Citizen & CNIC</th>
                            <th style="width:12%; padding:10px 8px;">Address / Area</th>
                            <th style="width:13%; padding:10px 8px;">Fees (Govt + Shop)</th>
                            <th style="width:12%; padding:10px 8px;">Status & Due</th>
                            <th style="width:13%; padding:10px 8px; text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:35px; color:var(--pos-text-sec);">
                                    <i class="fa-solid fa-id-card" style="font-size:2rem; color:#cbd5e1; margin-bottom:8px; display:block;"></i>
                                    No citizen applications recorded yet. Click <strong>"New Application"</strong> above to record entries.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): 
                                $type = $r['serviceType'] ?? 'nadra_cnic';
                                $st = $r['status'] ?? 'in_process';
                                $totFee = floatval($r['totalFee'] ?? 0);
                                $shopF = floatval($r['shopFee'] ?? 0);
                                $govtF = floatval($r['govtFee'] ?? 0);

                                // Badges
                                if (strpos($type, 'nadra') === 0) {
                                    $catBadge = '<span class="status-badge" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:2px 4px; font-size:0.65rem;"><i class="fa-solid fa-id-card"></i> ' . htmlspecialchars($r['serviceName']) . '</span>';
                                } elseif ($type === 'police_clearance') {
                                    $catBadge = '<span class="status-badge" style="background:#f1f5f9; color:#1e293b; border:1px solid #cbd5e1; padding:2px 4px; font-size:0.65rem;"><i class="fa-solid fa-shield"></i> POLICE CLEARANCE</span>';
                                } elseif ($type === 'domicile') {
                                    $catBadge = '<span class="status-badge" style="background:#fffbeb; color:#92400e; border:1px solid #fde68a; padding:2px 4px; font-size:0.65rem;"><i class="fa-solid fa-landmark"></i> DOMICILE HANGU</span>';
                                } else {
                                    $catBadge = '<span class="status-badge status-active" style="padding:2px 4px; font-size:0.65rem;">' . strtoupper($type) . '</span>';
                                }

                                if ($st === 'in_process' || $st === 'document_submitted') {
                                    $statusBadge = '<span class="status-badge" style="background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; padding:2px 4px; font-size:0.65rem;"><i class="fa-solid fa-clock"></i> IN PROCESS</span>';
                                } elseif ($st === 'ready' || $st === 'approved') {
                                    $statusBadge = '<span class="status-badge" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; padding:2px 4px; font-size:0.65rem;"><i class="fa-solid fa-certificate"></i> READY</span>';
                                } elseif ($st === 'delivered') {
                                    $statusBadge = '<span class="status-badge" style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; padding:2px 4px; font-size:0.65rem;"><i class="fa-solid fa-check-double"></i> DELIVERED</span>';
                                } else {
                                    $statusBadge = '<span class="status-badge status-inactive" style="padding:2px 4px; font-size:0.65rem;">' . strtoupper($st) . '</span>';
                                }
                            ?>
                                <tr data-type="<?php echo htmlspecialchars($type); ?>" data-status="<?php echo htmlspecialchars($st); ?>" style="font-size:0.8rem;">
                                    <td style="padding:8px 8px; word-break:break-all;">
                                        <div style="font-weight:800; font-family:monospace; color:var(--pos-red); font-size:0.8rem;"><?php echo htmlspecialchars($r['trackingNo'] ?? 'N/A'); ?></div>
                                        <div style="font-size:0.68rem; color:var(--pos-text-muted);"><?php echo date('M d, Y', strtotime($r['createdAt'])); ?></div>
                                    </td>
                                    <td style="padding:8px 8px; word-break:break-word;">
                                        <?php echo $catBadge; ?>
                                        <div style="font-size:0.68rem; color:var(--pos-text-muted); margin-top:2px;">
                                            Urgency: <strong style="text-transform:capitalize;"><?php echo htmlspecialchars($r['urgency'] ?? 'Normal'); ?></strong>
                                        </div>
                                    </td>
                                    <td style="padding:8px 8px; word-break:break-word;">
                                        <strong style="color:var(--pos-text); font-size:0.82rem;"><?php echo htmlspecialchars($r['citizenName']); ?></strong>
                                        <?php if (!empty($r['fatherName'])): ?>
                                            <div style="font-size:0.68rem; color:var(--pos-text-sec);">S/O: <?php echo htmlspecialchars($r['fatherName']); ?></div>
                                        <?php endif; ?>
                                        <div style="font-family:monospace; font-weight:700; color:#334155; font-size:0.72rem;"><?php echo htmlspecialchars($r['citizenCnic'] ?: 'CNIC Pending'); ?></div>
                                        <?php if (!empty($r['contactPhone'])): ?>
                                            <div style="font-size:0.68rem; color:var(--pos-text-muted);"><?php echo htmlspecialchars($r['contactPhone']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:8px 8px; word-break:break-word;">
                                        <span style="font-size:0.72rem; color:var(--pos-text);"><?php echo htmlspecialchars($r['address'] ?? 'Hangu'); ?></span>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <strong style="font-size:0.85rem; color:var(--pos-text);">PKR <?php echo number_format($totFee); ?></strong>
                                        <div style="font-size:0.68rem; color:#059669; font-weight:700;">
                                            Fee: +PKR <?php echo number_format($shopF); ?>
                                        </div>
                                    </td>
                                    <td style="padding:8px 8px;">
                                        <?php echo $statusBadge; ?>
                                        <div style="font-size:0.68rem; color:var(--pos-text-muted); margin-top:3px;">
                                            Due: <strong><?php echo date('M d', strtotime($r['deliveryDate'] ?? $r['createdAt'])); ?></strong>
                                        </div>
                                    </td>
                                    <td style="text-align:right; padding:8px 6px;">
                                        <div style="display:inline-flex; gap:3px; align-items:center; justify-content:flex-end; flex-wrap:nowrap;">
                                            <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" style="padding:2px 5px; font-size:0.7rem;" onclick="printCitizenSlip('<?php echo htmlspecialchars($r['id'], ENT_QUOTES); ?>')" title="Print Token Slip">
                                                <i class="fa-solid fa-print"></i>
                                            </button>

                                            <!-- Send WhatsApp Service Confirmation -->
                                            <button type="button" class="pos-btn pos-btn-sm" style="padding:2px 6px; font-size:0.7rem; background:#ecfdf5; color:#059669; border:1px solid #a7f3d0;" onclick="window.sendCitizenWhatsApp('<?php echo htmlspecialchars($r['id']); ?>', '<?php echo htmlspecialchars($r['contactPhone'] ?? ''); ?>')" title="Send WhatsApp Receipt to Citizen">
                                                <i class="fa-brands fa-whatsapp"></i>
                                            </button>

                                            <?php if ($st === 'in_process'): ?>
                                                <form method="POST" action="" style="display:inline; margin:0;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="record_id" value="<?php echo htmlspecialchars($r['id']); ?>">
                                                    <input type="hidden" name="new_status" value="ready">
                                                    <button type="submit" class="pos-btn pos-btn-sm" style="background:#10b981; color:#fff; padding:2px 5px; font-size:0.7rem;" title="Mark Ready for Pickup">
                                                        <i class="fa-solid fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php elseif ($st === 'ready'): ?>
                                                <form method="POST" action="" style="display:inline; margin:0;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="record_id" value="<?php echo htmlspecialchars($r['id']); ?>">
                                                    <input type="hidden" name="new_status" value="delivered">
                                                    <button type="submit" class="pos-btn pos-btn-sm" style="background:#3b82f6; color:#fff; padding:2px 5px; font-size:0.7rem;" title="Mark Delivered">
                                                        <i class="fa-solid fa-box"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this citizen application record?');" style="display:inline; margin:0;">
                                                <input type="hidden" name="action" value="delete_record">
                                                <input type="hidden" name="record_id" value="<?php echo htmlspecialchars($r['id']); ?>">
                                                <button type="submit" class="pos-btn pos-btn-outline pos-btn-sm" style="color:#ef4444; border-color:#fecaca; padding:2px 5px; font-size:0.7rem;" title="Delete Record">
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
     MODAL 1: NEW CITIZEN APPLICATION MODAL
     ========================================================================= -->
<div class="modal" id="newCitizenModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; max-width:100vw; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; padding:15px; box-sizing:border-box; overflow-x:hidden;">
    <div style="background:#fff; border-radius:12px; max-width:640px; width:100%; max-height:92vh; overflow-y:auto; padding:24px; box-shadow:0 20px 40px rgba(0,0,0,0.3); position:relative; box-sizing:border-box;">
        <button type="button" onclick="closeNewCitizenModal()" style="position:absolute; top:16px; right:16px; background:none; border:none; font-size:1.3rem; color:#9ca3af; cursor:pointer;">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div style="margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
            <span style="background:#00a859; color:#fff; font-size:0.72rem; font-weight:800; padding:2px 8px; border-radius:4px; text-transform:uppercase;">
                CITIZEN FACILITATION KIOSK
            </span>
            <h3 style="margin:6px 0 0 0; font-size:1.3rem; color:#0f172a;">New Citizen Application Record</h3>
            <p style="margin:2px 0 0 0; font-size:0.8rem; color:#64748b;">Record Smart CNIC, FRC, Police Character Clearance, or District Hangu Domicile</p>
        </div>

        <form method="POST" action="nadra-kiosk.php" id="citizenForm">
            <input type="hidden" name="action" value="add_record">

            <!-- Service Category Selection -->
            <div style="margin-bottom:14px;">
                <label class="form-label" style="font-weight:800; font-size:0.85rem;">Select Service Requested *</label>
                <select name="serviceType" id="modalServiceTypeSelect" class="form-select" onchange="onServiceTypeChange(this.value)" required style="font-size:0.95rem; font-weight:700;">
                    <option value="nadra_cnic">NADRA Smart CNIC (New / Renewal / Modification)</option>
                    <option value="nadra_frc">NADRA Family Registration Certificate (FRC)</option>
                    <option value="nadra_crc">NADRA Child Registration (CRC / B-Form)</option>
                    <option value="police_clearance">Police Character Clearance Certificate</option>
                    <option value="domicile">District Hangu Domicile & PRC Certificate</option>
                    <option value="attestation">Document Attestation & Legal Affidavit</option>
                </select>
            </div>

            <!-- Citizen & Father Information -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label class="form-label">Citizen Full Name *</label>
                    <input type="text" name="citizenName" class="form-input" placeholder="e.g. Ahmad Khan" required>
                </div>
                <div>
                    <label class="form-label">Father / Guardian Name</label>
                    <input type="text" name="fatherName" class="form-input" placeholder="e.g. Gulzar Khan">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label class="form-label">Citizen CNIC / B-Form No. *</label>
                    <input type="text" name="citizenCnic" class="form-input" placeholder="14202-1234567-1" required>
                </div>
                <div>
                    <label class="form-label">Citizen Mobile / Phone *</label>
                    <input type="text" name="contactPhone" class="form-input" placeholder="0333..." required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label class="form-label">Village / Mohallah / Residence Address</label>
                    <input type="text" name="address" class="form-input" placeholder="e.g. Purdil Masjid area, Hangu" value="Hangu, KPK">
                </div>
                <div>
                    <label class="form-label">Urgency Tier</label>
                    <select name="urgency" class="form-select">
                        <option value="normal">Normal Processing</option>
                        <option value="urgent">Urgent</option>
                        <option value="executive">Executive Fast-Track</option>
                    </select>
                </div>
            </div>

            <!-- Fees & Commission Calculator Box -->
            <div style="background:#f0fdf4; border:1.5px solid #a7f3d0; border-radius:10px; padding:14px; margin-bottom:14px;">
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px;">
                    <div>
                        <label class="form-label" style="font-weight:800; color:#065f46; font-size:0.75rem;">Official Govt Fee (PKR)</label>
                        <input type="number" name="govtFee" id="modalGovtFeeInput" class="form-input" placeholder="2500" value="2500" oninput="recalcCitizenTotalFee()" style="font-weight:700;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:800; color:#b45309; font-size:0.75rem;">Shop Facilitation Fee</label>
                        <input type="number" name="shopFee" id="modalShopFeeInput" class="form-input" placeholder="500" value="500" oninput="recalcCitizenTotalFee()" style="font-weight:700; color:#b45309; border-color:#f59e0b;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:800; color:#059669; font-size:0.75rem;">Total Fee to Collect</label>
                        <div id="modalTotalFeeDisplay" style="font-size:1.2rem; font-weight:900; color:#059669; padding-top:6px;">
                            PKR 3,000
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery Date & Status -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label class="form-label">Expected Ready / Delivery Date *</label>
                    <input type="date" name="deliveryDate" class="form-input" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                </div>
                <div>
                    <label class="form-label">Initial Status</label>
                    <select name="status" class="form-select">
                        <option value="in_process">In Process (Documents Submitted)</option>
                        <option value="ready">Ready for Pickup</option>
                        <option value="delivered">Delivered to Citizen</option>
                    </select>
                </div>
            </div>

            <!-- Submitted Documents Checklist -->
            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:10px 14px; margin-bottom:12px;">
                <label class="form-label" style="font-size:0.75rem; font-weight:800; margin-bottom:6px; display:block;">
                    <i class="fa-solid fa-list-check" style="color:var(--pos-red);"></i> Attached Documents Checklist:
                </label>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:6px; font-size:0.78rem;">
                    <label><input type="checkbox" name="docs[]" value="Original CNIC Copy" checked> Original CNIC Copy</label>
                    <label><input type="checkbox" name="docs[]" value="Passport Size Photos (2)" checked> Passport Photos (2)</label>
                    <label><input type="checkbox" name="docs[]" value="Father CNIC Copy" checked> Father CNIC Copy</label>
                    <label><input type="checkbox" name="docs[]" value="Electricity / Gas Bill Copy"> Utility Bill Copy</label>
                    <label><input type="checkbox" name="docs[]" value="Matric Sanad Copy"> Matric Sanad / Certificate</label>
                    <label><input type="checkbox" name="docs[]" value="Affidavit / Form Verified"> Verified Form / Affidavit</label>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Optional Case Notes / Reference Details</label>
                <input type="text" name="notes" class="form-input" placeholder="e.g. Urgent application for job appointment letter verification">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #e2e8f0; padding-top:14px;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="closeNewCitizenModal()">Cancel</button>
                <button type="submit" class="pos-btn pos-btn-primary pos-btn-lg">
                    <i class="fa-solid fa-check"></i> Save Application & Generate Token
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: PRINTABLE CITIZEN TOKEN SLIP VOUCHER
     ========================================================================= -->
<div class="modal" id="citizenSlipModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; max-width:100vw; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; padding:15px; box-sizing:border-box; overflow-x:hidden;">
    <div style="background:#fff; border-radius:10px; max-width:420px; width:100%; padding:20px; box-shadow:0 20px 40px rgba(0,0,0,0.3); position:relative; box-sizing:border-box;">
        <button type="button" onclick="closeCitizenSlipModal()" style="position:absolute; top:12px; right:12px; background:none; border:none; font-size:1.2rem; color:#9ca3af; cursor:pointer;">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <!-- Printable Voucher Area -->
        <div id="printableCitizenSlipArea" style="font-family:monospace; color:#000; font-size:0.85rem; border:1px dashed #94a3b8; padding:16px; border-radius:8px; background:#fff;">
            <div style="text-align:center; border-bottom:1px dashed #000; padding-bottom:10px; margin-bottom:10px;">
                <h3 style="margin:0; font-size:1.15rem; font-weight:900;">SAFDAR MOBILE STORE</h3>
                <div style="font-size:0.75rem; margin-top:2px; font-weight:800;">NADRA, POLICE CLEARANCE & DOMICILE KIOSK</div>
                <div style="font-size:0.7rem; color:#475569;">Opp. Patt Bazar Eidgah Road near Purdil Masjid, Hangu</div>
                <div style="font-size:0.75rem; font-weight:700; margin-top:2px;">Helpline: 03339688007</div>
            </div>

            <div style="margin-bottom:8px; line-height:1.4;">
                <div><strong>TRACKING NO:</strong> <span id="slipTrackingNo" style="font-size:0.95rem; font-weight:900;"></span></div>
                <div><strong>Date Applied:</strong> <span id="slipApplyDate"></span></div>
                <div><strong>Service:</strong> <span id="slipServiceName"></span></div>
                <div><strong>Urgency:</strong> <span id="slipUrgency"></span></div>
            </div>

            <div style="border-top:1px dashed #000; border-bottom:1px dashed #000; padding:8px 0; margin-bottom:8px; line-height:1.4;">
                <div><strong>Citizen Name:</strong> <span id="slipCitizenName"></span></div>
                <div><strong>Father Name:</strong> <span id="slipFatherName"></span></div>
                <div><strong>CNIC / B-Form:</strong> <span id="slipCnic"></span></div>
                <div><strong>Contact:</strong> <span id="slipPhone"></span></div>
            </div>

            <div style="line-height:1.5; margin-bottom:8px;">
                <div style="display:flex; justify-content:space-between;">
                    <span>Official Govt Fee:</span>
                    <strong id="slipGovtFee">PKR 0</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Shop Facilitation Fee:</span>
                    <strong id="slipShopFee">PKR 0</strong>
                </div>
                <div style="display:flex; justify-content:space-between; border-top:1px solid #000; padding-top:4px; font-size:0.95rem; font-weight:900;">
                    <span>Total Fee Paid:</span>
                    <span id="slipTotalFee">PKR 0</span>
                </div>
            </div>

            <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; margin-bottom:8px; text-align:center; font-size:0.8rem; font-weight:800;">
                <span>Expected Collection Date: </span>
                <span id="slipDeliveryDate" style="color:#059669;"></span>
            </div>

            <div style="text-align:center; font-size:0.7rem; border-top:1px dashed #000; padding-top:6px; color:#475569;">
                Please bring this token slip for certificate collection.<br>
                For status check WhatsApp: <strong>03339688007</strong>
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:16px;">
            <button type="button" class="pos-btn pos-btn-outline pos-btn-block" onclick="closeCitizenSlipModal()">Close</button>
            <button type="button" class="pos-btn pos-btn-primary pos-btn-block" onclick="printCitizenSlipVoucher()">
                <i class="fa-solid fa-print"></i> Print Token Slip
            </button>
        </div>
    </div>
</div>

<script>
function openNewCitizenModal() {
    document.getElementById('newCitizenModal').style.display = 'flex';
    recalcCitizenTotalFee();
}

function closeNewCitizenModal() {
    document.getElementById('newCitizenModal').style.display = 'none';
}

function onServiceTypeChange(type) {
    const govtInput = document.getElementById('modalGovtFeeInput');
    const shopInput = document.getElementById('modalShopFeeInput');

    if (type === 'nadra_cnic') {
        govtInput.value = 2500;
        shopInput.value = 500;
    } else if (type === 'nadra_frc') {
        govtInput.value = 1000;
        shopInput.value = 300;
    } else if (type === 'nadra_crc') {
        govtInput.value = 500;
        shopInput.value = 300;
    } else if (type === 'police_clearance') {
        govtInput.value = 500;
        shopInput.value = 400;
    } else if (type === 'domicile') {
        govtInput.value = 300;
        shopInput.value = 500;
    } else if (type === 'attestation') {
        govtInput.value = 200;
        shopInput.value = 300;
    }

    recalcCitizenTotalFee();
}

function recalcCitizenTotalFee() {
    const govt = parseFloat(document.getElementById('modalGovtFeeInput').value) || 0;
    const shop = parseFloat(document.getElementById('modalShopFeeInput').value) || 0;
    const tot = govt + shop;
    document.getElementById('modalTotalFeeDisplay').innerText = 'PKR ' + tot.toLocaleString();
}

// Table Filtering
function filterCitizenTable(filterType, btn) {
    document.querySelectorAll('.nk-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const rows = document.querySelectorAll('#citizenTable tbody tr');
    rows.forEach(r => {
        const type = r.getAttribute('data-type') || '';
        const status = r.getAttribute('data-status') || '';

        if (filterType === 'all') {
            r.style.display = '';
        } else if (filterType === 'nadra' && type.startsWith('nadra')) {
            r.style.display = '';
        } else if (filterType === type) {
            r.style.display = '';
        } else if (filterType === status) {
            r.style.display = '';
        } else if (filterType === 'ready' && (status === 'ready' || status === 'delivered' || status === 'approved')) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}

function searchCitizenTable() {
    const query = document.getElementById('nkSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#citizenTable tbody tr');
    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        r.style.display = text.includes(query) ? '' : 'none';
    });
}

window.allNadraRecords = <?php echo json_encode($records); ?> || [];

window.sendCitizenWhatsApp = window.sendCitizenWhatsApp || function (serviceId, citizenPhone) {
    let phone = (citizenPhone || '').replace(/[^0-9]/g, '');
    if (!phone) {
        phone = prompt('Enter citizen WhatsApp mobile number (e.g. 03339688007):');
        if (!phone) return;
    }

    fetch(`../backend/whatsapp.php?type=citizen&id=${encodeURIComponent(serviceId)}&phone=${encodeURIComponent(phone)}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data && res.data.whatsappUrl) {
                window.open(res.data.whatsappUrl, '_blank');
            } else {
                alert(res.message || 'Failed to generate WhatsApp token');
            }
        })
        .catch(err => {
            alert('Error connecting to WhatsApp dispatcher: ' + err.message);
        });
};

// Token Slip Modal
function printCitizenSlip(rec) {
    if (typeof rec === 'string') {
        rec = (window.allNadraRecords || []).find(r => String(r.id) === String(rec) || String(r.trackingNo) === String(rec)) || null;
    }
    if (!rec) return;
    document.getElementById('slipTrackingNo').innerText = rec.trackingNo || 'N/A';
    document.getElementById('slipApplyDate').innerText = new Date(rec.createdAt).toLocaleDateString();
    document.getElementById('slipServiceName').innerText = rec.serviceName || 'Citizen Facilitation';
    document.getElementById('slipUrgency').innerText = (rec.urgency || 'Normal').toUpperCase();
    document.getElementById('slipCitizenName').innerText = rec.citizenName || 'Citizen';
    document.getElementById('slipFatherName').innerText = rec.fatherName || 'N/A';
    document.getElementById('slipCnic').innerText = rec.citizenCnic || 'N/A';
    document.getElementById('slipPhone').innerText = rec.contactPhone || 'N/A';

    const govtF = parseFloat(rec.govtFee) || 0;
    const shopF = parseFloat(rec.shopFee) || 0;
    const totF = parseFloat(rec.totalFee) || (govtF + shopF);

    document.getElementById('slipGovtFee').innerText = 'PKR ' + govtF.toLocaleString();
    document.getElementById('slipShopFee').innerText = 'PKR ' + shopF.toLocaleString();
    document.getElementById('slipTotalFee').innerText = 'PKR ' + totF.toLocaleString();
    document.getElementById('slipDeliveryDate').innerText = new Date(rec.deliveryDate || rec.createdAt).toLocaleDateString();

    document.getElementById('citizenSlipModal').style.display = 'flex';
}

function closeCitizenSlipModal() {
    document.getElementById('citizenSlipModal').style.display = 'none';
}

function printCitizenSlipVoucher() {
    const slipContent = document.getElementById('printableCitizenSlipArea').innerHTML;
    const win = window.open('', '', 'height=600,width=450');
    win.document.write('<html><head><title>Citizen Token Slip - Safdar Mobile Store</title>');
    win.document.write('<style>body{font-family:monospace; margin:20px;} strong{font-weight:bold;}</style>');
    win.document.write('</head><body>');
    win.document.write(slipContent);
    win.document.write('</body></html>');
    win.document.close();
    win.focus();
    setTimeout(() => { win.print(); win.close(); }, 250);
}

// Auto-open citizen token slip modal or filter if URL has ?tracking=... or ?id=... or ?search=...
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tracking = urlParams.get('tracking') || urlParams.get('id') || urlParams.get('token');
    const search = urlParams.get('search') || urlParams.get('q');

    if ((search || tracking) && document.getElementById('nkSearchInput')) {
        document.getElementById('nkSearchInput').value = search || tracking;
        searchCitizenTable();
    }

    if (tracking) {
        const recordsList = <?php echo json_encode($records); ?> || [];
        const match = recordsList.find(r => 
            r.id === tracking || 
            r.trackingNo === tracking || 
            (r.trackingNo && r.trackingNo.toLowerCase() === tracking.toLowerCase()) ||
            (r.citizenCnic && r.citizenCnic.toLowerCase() === tracking.toLowerCase())
        );
        if (match) {
            printCitizenSlip(match);
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
