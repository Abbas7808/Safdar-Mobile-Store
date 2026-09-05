<?php
$currentPage = 'packages';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$plans = get_json_file('sim_plans') ?? [];
$activations = get_json_file('packages') ?? [];

$message = '';
$messageType = '';

// Handle POST actions for plans or activations
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. ADD NEW SIM PLAN
    if ($action === 'add_plan') {
        $network = trim($_POST['network'] ?? 'jazz');
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'all_in_one');
        $validity = trim($_POST['validity'] ?? '30 Days');
        $retailPrice = floatval($_POST['retailPrice'] ?? 0);
        $costPrice = floatval($_POST['costPrice'] ?? 0);
        $manualProfit = floatval($_POST['manualProfit'] ?? 0);
        $shopCharges = floatval($_POST['shopCharges'] ?? 0);
        $additionalCharges = floatval($_POST['additionalCharges'] ?? 0);

        if ($manualProfit <= 0 && $retailPrice > ($costPrice + $shopCharges + $additionalCharges)) {
            $manualProfit = max(0, $retailPrice - $costPrice - $shopCharges - $additionalCharges);
        }

        $profitMargin = floatval($_POST['profitMargin'] ?? ($manualProfit + $shopCharges + $additionalCharges));
        if ($profitMargin <= 0 && $retailPrice > $costPrice) {
            $profitMargin = $retailPrice - $costPrice;
        }

        if ($retailPrice <= 0 && $costPrice > 0) {
            $retailPrice = $costPrice + $profitMargin;
        }

        $dataGb = trim($_POST['dataGb'] ?? '');
        $onNetMins = trim($_POST['onNetMins'] ?? '');
        $offNetMins = trim($_POST['offNetMins'] ?? '');
        $smsCount = trim($_POST['smsCount'] ?? '');
        $ussdCode = trim($_POST['ussdCode'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name) || ($retailPrice <= 0 && $costPrice <= 0)) {
            $message = 'Package name and a retail/cost price are required.';
            $messageType = 'danger';
        } else {
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
                SecurityLogger::logEvent($user['username'] ?? 'admin', 'admin', 'SIM_PLAN_CREATED', "Added {$network} package: {$name} (PKR {$retailPrice}, Profit: PKR {$profitMargin})");
            }

            $message = "SIM Package '{$name}' for " . strtoupper($network) . " added successfully! Profit: +PKR " . number_format($profitMargin);
            $messageType = 'success';
        }
    }

    // 2. DELETE SIM PLAN
    elseif ($action === 'delete_plan') {
        $delId = $_POST['plan_id'] ?? '';
        if ($delId) {
            $plans = array_values(array_filter($plans, function($p) use ($delId) {
                return ($p['id'] ?? '') !== $delId;
            }));
            save_json_file('sim_plans', $plans);
            $message = 'SIM Package Plan deleted from catalog successfully.';
            $messageType = 'success';
        }
    }

    // 3. ACTIVATE PACKAGE FOR CUSTOMER
    elseif ($action === 'activate_package') {
        $mobileNumber = trim($_POST['mobileNumber'] ?? '');
        $customerName = trim($_POST['customerName'] ?? 'Walk-in Customer');
        $network = trim($_POST['network'] ?? 'jazz');
        $packageName = trim($_POST['packageName'] ?? 'Mobile Package');
        $retailPrice = floatval($_POST['retailPrice'] ?? 0);
        $costPrice = floatval($_POST['costPrice'] ?? 0);
        $manualProfit = floatval($_POST['manualProfit'] ?? 0);
        $shopCharges = floatval($_POST['shopCharges'] ?? 0);
        $additionalCharges = floatval($_POST['additionalCharges'] ?? 0);

        $totalShopProfit = $manualProfit + $shopCharges + $additionalCharges;
        if ($totalShopProfit <= 0 && isset($_POST['shopFee'])) {
            $totalShopProfit = floatval($_POST['shopFee']);
        }
        $totalCollected = $retailPrice + $shopCharges + $additionalCharges;

        $activationMethod = trim($_POST['activationMethod'] ?? 'retailer_load');
        $trxId = trim($_POST['trxId'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($retailPrice <= 0 || empty($mobileNumber)) {
            $message = 'Please provide a valid customer mobile number and price.';
            $messageType = 'danger';
        } else {
            if (empty($trxId)) {
                $prefix = strtoupper(substr($network, 0, 2));
                $trxId = $prefix . '-PKG-' . rand(1000000000, 9999999999);
            }

            $newAct = [
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

            array_unshift($activations, $newAct);
            save_json_file('packages', $activations);

            if (class_exists('SecurityLogger')) {
                SecurityLogger::logEvent($user['username'] ?? 'admin', 'admin', 'PACKAGE_ACTIVATED', "Activated {$packageName} for {$mobileNumber} (PKR {$totalCollected}, Profit: PKR {$totalShopProfit})");
            }

            $message = "Package '{$packageName}' activated for {$mobileNumber}! Total Collected: PKR " . number_format($totalCollected) . " | Shop Profit: +PKR " . number_format($totalShopProfit);
            $messageType = 'success';
        }
    }

    // 4. DELETE ACTIVATION LOG
    elseif ($action === 'delete_activation') {
        $delId = $_POST['activation_id'] ?? '';
        if ($delId) {
            $activations = array_values(array_filter($activations, function($a) use ($delId) {
                return ($a['id'] ?? '') !== $delId;
            }));
            save_json_file('packages', $activations);
            $message = 'Package activation record deleted successfully.';
            $messageType = 'success';
        }
    }
}

// Calculate Analytics
$totalPlansCount = count($plans);
$totalActivationsCount = count($activations);
$totalVolume = 0;
$totalShopProfit = 0;

foreach ($activations as $act) {
    $totalVolume += floatval($act['totalCollected'] ?? $act['retailPrice'] ?? 0);
    $totalShopProfit += floatval($act['shopFee'] ?? 0);
}
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title" style="display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-mobile-screen" style="color:var(--pos-red);"></i>
                    All SIM Packages &amp; Easyload Hub
                </h1>
                <p class="page-header-sub">
                    Configure custom SIM package plans with manual profit, shop charges &amp; additional charges for Jazz, Zong, Telenor, Ufone &amp; Onic
                </p>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <button type="button" class="pos-btn" onclick="openSmartPackagesImporterModal('excel')" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:#fff; font-weight:800; border-radius:30px; box-shadow:0 4px 14px rgba(16,185,129,0.35); display:inline-flex; align-items:center; gap:8px; padding:10px 18px; border:none; cursor:pointer;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> ⚡ Smart Batch Importer (Excel / PDF / OCR)
                </button>
                <button type="button" class="pos-btn" onclick="openAddPlanModal()" style="background:linear-gradient(135deg, #e11d48 0%, #be123c 100%); color:#fff; font-weight:800; border-radius:30px; box-shadow:0 4px 14px rgba(225,29,72,0.3); display:inline-flex; align-items:center; gap:8px; padding:10px 18px; border:none; cursor:pointer;">
                    <i class="fa-solid fa-circle-plus"></i> + Add SIM Package Plan
                </button>
                <button type="button" class="pos-btn pos-btn-outline" onclick="openActivatePackageModal()" style="border-color:#d97706; color:#d97706; font-weight:800; border-radius:30px; padding:10px 18px;">
                    <i class="fa-solid fa-bolt"></i> ⚡ Quick Activate for Customer
                </button>
                <a href="pos.php" class="pos-btn pos-btn-primary" style="border-radius:30px; font-weight:800;">
                    <i class="fa-solid fa-cash-register"></i> Open POS Terminal
                </a>
            </div>
        </div>

        <!-- Time-Saving Package Batch Importer Banner -->
        <div class="pos-card" style="margin-bottom:20px; border-radius:16px; padding:18px 22px; background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color:#ffffff; border:1.5px solid #334155; box-shadow:0 6px 25px rgba(15,23,42,0.12);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                <div style="display:flex; align-items:center; gap:16px;">
                    <div style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg, #10b981 0%, #059669 100%); display:flex; align-items:center; justify-content:center; font-size:1.4rem; color:#fff; box-shadow:0 4px 15px rgba(16,185,129,0.4); flex-shrink:0;">
                        <i class="fa-solid fa-file-import"></i>
                    </div>
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:3px;">
                            <span style="background:#10b981; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:6px; letter-spacing:0.5px; text-transform:uppercase;">
                                ⚡ FAST BATCH IMPORTER
                            </span>
                            <span style="background:rgba(255,255,255,0.15); color:#f8fafc; font-size:0.68rem; font-weight:700; padding:2px 8px; border-radius:6px;">
                                Save Time &amp; Effort
                            </span>
                        </div>
                        <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:#f8fafc;">
                            Add Packages in Bulk from Excel, PDF, Rate Card Photos, or WhatsApp
                        </h3>
                        <p style="margin:2px 0 0 0; font-size:0.78rem; color:#94a3b8;">
                            No need to add packages one by one. Upload a spreadsheet, snap a picture of dealer commission sheets, or paste WhatsApp forwards.
                        </p>
                    </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="pos-btn pos-btn-sm" onclick="openSmartPackagesImporterModal('excel')" style="background:#16a34a; color:#fff; font-weight:800; font-size:0.78rem; padding:8px 14px; border-radius:8px; display:inline-flex; align-items:center; gap:6px; border:none; cursor:pointer;">
                        <i class="fa-solid fa-file-excel"></i> Upload Excel / CSV
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm" onclick="openSmartPackagesImporterModal('ocr')" style="background:#e11d48; color:#fff; font-weight:800; font-size:0.78rem; padding:8px 14px; border-radius:8px; display:inline-flex; align-items:center; gap:6px; border:none; cursor:pointer;">
                        <i class="fa-solid fa-camera"></i> Scan Photo / PDF (AI OCR)
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm" onclick="openSmartPackagesImporterModal('text')" style="background:#25d366; color:#0f172a; font-weight:800; font-size:0.78rem; padding:8px 14px; border-radius:8px; display:inline-flex; align-items:center; gap:6px; border:none; cursor:pointer;">
                        <i class="fa-brands fa-whatsapp"></i> Paste WhatsApp Text
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm" onclick="openSmartPackagesImporterModal('presets')" style="background:#f59e0b; color:#0f172a; font-weight:800; font-size:0.78rem; padding:8px 14px; border-radius:8px; display:inline-flex; align-items:center; gap:6px; border:none; cursor:pointer;">
                        <i class="fa-solid fa-bolt"></i> 1-Click Telco Presets (2026)
                    </button>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="pos-alert pos-alert-<?php echo $messageType; ?>" style="margin-bottom:20px; border-radius:12px; padding:14px 18px; display:flex; align-items:center; gap:10px;">
                <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <!-- KPI Metrics Grid -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom:24px;">
            <div class="stat-card" style="border-left:4px solid #e11d48;">
                <div class="stat-icon" style="background:rgba(225,29,72,0.1); color:#e11d48;"><i class="fa-solid fa-sim-card"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Active Catalog Plans</div>
                    <div class="stat-value" style="color:#e11d48;"><?php echo $totalPlansCount; ?> Plans</div>
                    <div style="font-size:0.75rem; color:#6b7280; margin-top:2px;">Custom Admin Plans</div>
                </div>
            </div>

            <div class="stat-card" style="border-left:4px solid #3b82f6;">
                <div class="stat-icon blue"><i class="fa-solid fa-chart-line"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Customer Activations</div>
                    <div class="stat-value"><?php echo $totalActivationsCount; ?> Sold</div>
                    <div style="font-size:0.75rem; color:#6b7280; margin-top:2px;">Processed Records</div>
                </div>
            </div>

            <div class="stat-card" style="border-left:4px solid #f59e0b;">
                <div class="stat-icon gold"><i class="fa-solid fa-money-bill-wave"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Total Load &amp; PKG Sold</div>
                    <div class="stat-value" style="color:#d97706;">PKR <?php echo number_format($totalVolume); ?></div>
                    <div style="font-size:0.75rem; color:#6b7280; margin-top:2px;">Total Retail Volume</div>
                </div>
            </div>

            <div class="stat-card" style="border-left:4px solid #10b981;">
                <div class="stat-icon green"><i class="fa-solid fa-sack-dollar"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Shop Gross Profit</div>
                    <div class="stat-value" style="color:#059669;">+PKR <?php echo number_format($totalShopProfit); ?></div>
                    <div style="font-size:0.75rem; color:#059669; font-weight:700; margin-top:2px;">Manual Profit + Shop Charges</div>
                </div>
            </div>
        </div>

        <!-- 2-Tab Navigation Switcher -->
        <div style="display:flex; gap:10px; margin-bottom:20px; border-bottom:2px solid var(--pos-border); padding-bottom:12px; flex-wrap:wrap;">
            <button type="button" id="tabBtnPlans" class="pos-btn pos-btn-primary" onclick="switchPackageTab('plans')" style="border-radius:24px; padding:8px 20px; font-weight:800; display:inline-flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-layer-group"></i> 1. All SIM Package Catalog (<?php echo $totalPlansCount; ?>)
            </button>
            <button type="button" id="tabBtnActivations" class="pos-btn pos-btn-outline" onclick="switchPackageTab('activations')" style="border-radius:24px; padding:8px 20px; font-weight:800; display:inline-flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-clock-rotate-left"></i> 2. Customer Subscriptions &amp; Activations Log (<?php echo $totalActivationsCount; ?>)
            </button>
        </div>

        <!-- =========================================================================
             TAB 1: SIM PACKAGE PLANS CATALOG (ADMIN CUSTOM PLANS)
             ========================================================================= -->
        <div id="tabContentPlans">
            <!-- Filter Bar -->
            <div class="pos-card" style="padding:14px 18px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <span style="font-size:0.8rem; font-weight:800; color:#64748b; text-transform:uppercase; margin-right:4px;">Filter Network:</span>
                    <button type="button" class="pos-btn pos-btn-sm" onclick="filterPlansByNetwork('all', this)" style="background:#1e293b; color:#fff; font-weight:800; border-radius:18px; padding:4px 12px;">All (<?php echo $totalPlansCount; ?>)</button>
                    <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="filterPlansByNetwork('jazz', this)" style="border-color:#e11d48; color:#e11d48; font-weight:800; border-radius:18px; padding:4px 12px;">Jazz / Warid</button>
                    <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="filterPlansByNetwork('zong', this)" style="border-color:#16a34a; color:#16a34a; font-weight:800; border-radius:18px; padding:4px 12px;">Zong 4G</button>
                    <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="filterPlansByNetwork('telenor', this)" style="border-color:#0284c7; color:#0284c7; font-weight:800; border-radius:18px; padding:4px 12px;">Telenor 4G</button>
                    <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="filterPlansByNetwork('ufone', this)" style="border-color:#ea580c; color:#ea580c; font-weight:800; border-radius:18px; padding:4px 12px;">Ufone 4G</button>
                    <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="filterPlansByNetwork('onic', this)" style="border-color:#7c3aed; color:#7c3aed; font-weight:800; border-radius:18px; padding:4px 12px;">Onic</button>
                </div>
                <div>
                    <input type="text" id="catalogSearchInput" class="form-input" placeholder="Search package name, code, GBs..." oninput="searchPlansCatalog(this.value)" style="height:36px; font-size:0.85rem; width:220px; border-radius:8px;">
                </div>
            </div>

            <!-- Plans Table -->
            <div class="data-table-wrap pos-card" style="padding:0; overflow-x:auto; -webkit-overflow-scrolling:touch; width:100%; border:1px solid var(--pos-border); border-radius:12px; margin-bottom:24px;">
                <table class="data-table" id="plansCatalogTable" style="margin:0; width:100%; min-width:980px; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid var(--pos-border);">
                            <th style="padding:12px 14px;">Network</th>
                            <th style="padding:12px 14px;">Package Name &amp; Validity</th>
                            <th style="padding:12px 14px;">Data Internet</th>
                            <th style="padding:12px 14px;">Calling Minutes</th>
                            <th style="padding:12px 14px;">SMS</th>
                            <th style="padding:12px 14px;">Dial Code</th>
                            <th style="padding:12px 14px;">Retail Price</th>
                            <th style="padding:12px 14px;">Profit &amp; Charges Breakdown</th>
                            <th style="padding:12px 14px; text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($plans)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding:40px; color:var(--pos-text-muted);">
                                    <i class="fa-solid fa-sim-card" style="font-size:2.5rem; color:#cbd5e1; margin-bottom:10px; display:block;"></i>
                                    <strong style="font-size:1.05rem; color:#334155; display:block;">No SIM Package Plans in Catalog Yet</strong>
                                    <p style="margin:4px 0 14px 0; font-size:0.82rem; color:#64748b;">Click "+ Add SIM Package Plan" above to create your custom packages with manual profit and shop charges.</p>
                                    <button type="button" class="pos-btn pos-btn-primary pos-btn-sm" onclick="openAddPlanModal()" style="font-weight:800; border-radius:20px;">
                                        <i class="fa-solid fa-plus"></i> + Add First SIM Plan
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($plans as $p): 
                                $net = strtolower($p['network'] ?? 'jazz');
                                $netBadgeStyles = [
                                    'jazz' => 'background:#fef2f2; color:#e11d48; border:1px solid #fecdd3;',
                                    'zong' => 'background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0;',
                                    'telenor' => 'background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd;',
                                    'ufone' => 'background:#fff7ed; color:#ea580c; border:1px solid #ffedd5;',
                                    'onic' => 'background:#faf5ff; color:#7c3aed; border:1px solid #e9d5ff;'
                                ];
                                $badgeStyle = $netBadgeStyles[$net] ?? 'background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;';
                                $mProfit = floatval($p['manualProfit'] ?? 0);
                                $sCharge = floatval($p['shopCharges'] ?? 0);
                                $aCharge = floatval($p['additionalCharges'] ?? 0);
                                $totProf = floatval($p['profitMargin'] ?? ($mProfit + $sCharge + $aCharge));
                            ?>
                                <tr class="plan-row-item" data-network="<?php echo htmlspecialchars($net); ?>" style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:12px 14px;">
                                        <span class="status-badge" style="<?php echo $badgeStyle; ?> font-size:0.75rem; font-weight:900; text-transform:uppercase; padding:4px 10px; border-radius:6px;">
                                            <i class="fa-solid fa-signal"></i> <?php echo strtoupper($p['network'] ?? 'SIM'); ?>
                                        </span>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <strong style="font-size:0.95rem; color:#0f172a; display:block;"><?php echo htmlspecialchars($p['name']); ?></strong>
                                        <span style="font-size:0.72rem; color:#64748b; background:#f1f5f9; padding:2px 6px; border-radius:4px; font-weight:700;">
                                            <i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars($p['validity'] ?? '30 Days'); ?>
                                        </span>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <?php if (!empty($p['dataGb'])): ?>
                                            <span style="color:#0284c7; font-weight:800; font-size:0.85rem;"><i class="fa-solid fa-wifi"></i> <?php echo htmlspecialchars($p['dataGb']); ?></span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8; font-size:0.75rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <?php if (!empty($p['onNetMins']) || !empty($p['offNetMins'])): ?>
                                            <div style="font-size:0.8rem; font-weight:700; color:#334155;">
                                                <?php if (!empty($p['onNetMins'])) echo "<span>On-Net: " . htmlspecialchars($p['onNetMins']) . "</span>"; ?>
                                                <?php if (!empty($p['offNetMins'])) echo "<span style='color:#64748b; display:block; font-size:0.72rem;'>Off-Net: " . htmlspecialchars($p['offNetMins']) . "</span>"; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:#94a3b8; font-size:0.75rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <?php if (!empty($p['smsCount'])): ?>
                                            <span style="font-size:0.8rem; font-weight:700; color:#475569;"><i class="fa-regular fa-message"></i> <?php echo htmlspecialchars($p['smsCount']); ?></span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8; font-size:0.75rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <?php if (!empty($p['ussdCode'])): ?>
                                            <code style="background:#f1f5f9; color:#e11d48; font-weight:900; padding:3px 8px; border-radius:6px; font-size:0.82rem; border:1px solid #e2e8f0;"><?php echo htmlspecialchars($p['ussdCode']); ?></code>
                                        <?php else: ?>
                                            <span style="color:#94a3b8; font-size:0.75rem;">Retailer Direct</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <strong style="color:#0f172a; font-size:0.95rem;">PKR <?php echo number_format($p['retailPrice'] ?? 0); ?></strong>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <strong style="color:#059669; font-size:0.95rem;">+PKR <?php echo number_format($totProf); ?></strong>
                                        <div style="font-size:0.68rem; color:#64748b; margin-top:2px;">
                                            Profit: PKR <?php echo number_format($mProfit); ?> | Shop: <?php echo number_format($sCharge); ?> | Extra: <?php echo number_format($aCharge); ?>
                                        </div>
                                    </td>
                                    <td style="padding:12px 14px; text-align:right;">
                                        <div style="display:flex; justify-content:flex-end; gap:6px;">
                                            <button type="button" class="pos-btn pos-btn-sm" onclick="quickActivatePlan(<?php echo htmlspecialchars(json_encode($p)); ?>)" title="Activate for Customer" style="background:#059669; color:#fff; font-weight:800; padding:5px 10px; border-radius:6px; border:none; cursor:pointer;">
                                                <i class="fa-solid fa-bolt"></i> Activate
                                            </button>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this SIM package plan from catalog?');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_plan">
                                                <input type="hidden" name="plan_id" value="<?php echo htmlspecialchars($p['id']); ?>">
                                                <button type="submit" class="pos-btn pos-btn-danger pos-btn-sm" style="padding:5px 9px; border-radius:6px;" title="Delete Plan">
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

        <!-- =========================================================================
             TAB 2: CUSTOMER ACTIVATIONS LOG
             ========================================================================= -->
        <div id="tabContentActivations" style="display:none;">
            <div class="data-table-wrap pos-card" style="padding:0; overflow-x:auto; -webkit-overflow-scrolling:touch; width:100%; border:1px solid var(--pos-border); border-radius:12px; margin-bottom:24px;">
                <table class="data-table" style="margin:0; width:100%; min-width:980px; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid var(--pos-border);">
                            <th style="padding:12px 14px;">Trx ID</th>
                            <th style="padding:12px 14px;">Customer &amp; Mobile</th>
                            <th style="padding:12px 14px;">Network &amp; Package</th>
                            <th style="padding:12px 14px;">Collected Amount</th>
                            <th style="padding:12px 14px;">Shop Charges &amp; Net Profit</th>
                            <th style="padding:12px 14px;">Processed By</th>
                            <th style="padding:12px 14px;">Date &amp; Time</th>
                            <th style="padding:12px 14px; text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activations)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:40px; color:var(--pos-text-muted);">
                                    <i class="fa-solid fa-clock-rotate-left" style="font-size:2rem; color:#cbd5e1; margin-bottom:8px; display:block;"></i>
                                    No customer package activations recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($activations as $act): 
                                $mP = floatval($act['manualProfit'] ?? 0);
                                $sC = floatval($act['shopCharges'] ?? 0);
                                $aC = floatval($act['additionalCharges'] ?? 0);
                                $tP = floatval($act['shopFee'] ?? ($mP + $sC + $aC));
                                $tC = floatval($act['totalCollected'] ?? ($act['retailPrice'] ?? 0));
                            ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:12px 14px; font-family:monospace; font-weight:800; color:#e11d48; font-size:0.85rem;">
                                        <?php echo htmlspecialchars($act['trxId'] ?? $act['id']); ?>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <strong style="color:#0f172a;"><?php echo htmlspecialchars($act['customerName'] ?? 'Walk-in'); ?></strong>
                                        <div style="font-size:0.8rem; color:#059669; font-weight:800;">
                                            <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($act['mobileNumber'] ?? ''); ?>
                                        </div>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <strong style="color:#1e293b;"><?php echo htmlspecialchars($act['packageName'] ?? 'Package'); ?></strong>
                                        <span class="status-badge" style="background:#f1f5f9; font-size:0.68rem; font-weight:800; text-transform:uppercase;">
                                            <?php echo strtoupper($act['network'] ?? ''); ?>
                                        </span>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <strong style="color:#0f172a; font-size:0.95rem;">PKR <?php echo number_format($tC); ?></strong>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <strong style="color:#059669; font-size:0.9rem;">+PKR <?php echo number_format($tP); ?></strong>
                                        <div style="font-size:0.68rem; color:#64748b;">
                                            Manual: <?php echo number_format($mP); ?> | Shop: <?php echo number_format($sC); ?> | Extra: <?php echo number_format($aC); ?>
                                        </div>
                                    </td>
                                    <td style="padding:12px 14px; font-size:0.8rem;">
                                        <?php echo htmlspecialchars($act['loggedBy'] ?? 'admin'); ?>
                                    </td>
                                    <td style="padding:12px 14px; font-size:0.8rem; color:#64748b;">
                                        <?php echo date('M d, Y h:i A', strtotime($act['activatedAt'] ?? 'now')); ?>
                                    </td>
                                    <td style="padding:12px 14px; text-align:right;">
                                        <form method="POST" onsubmit="return confirm('Delete this activation log record?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_activation">
                                            <input type="hidden" name="activation_id" value="<?php echo htmlspecialchars($act['id']); ?>">
                                            <button type="submit" class="pos-btn pos-btn-danger pos-btn-sm" style="padding:4px 8px; border-radius:6px;">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
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
     MODAL 1: ADD NEW SIM PACKAGE PLAN (ADMIN CUSTOM CATALOG)
     ========================================================================= -->
<div class="pos-modal-overlay" id="addPlanModal" style="display:none; z-index:99999; backdrop-filter:blur(5px); background:rgba(15,23,42,0.65);">
    <div class="pos-modal" style="max-width:680px; width:95%; padding:24px; max-height:92vh; overflow-y:auto; border-radius:18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid var(--pos-border); padding-bottom:12px; margin-bottom:16px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:40px; height:40px; border-radius:10px; background:#e11d48; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                    <i class="fa-solid fa-sim-card"></i>
                </div>
                <div>
                    <h3 style="margin:0; font-size:1.2rem; font-weight:900;">Add New SIM Package Plan</h3>
                    <p style="margin:2px 0 0 0; font-size:0.75rem; color:var(--pos-text-muted);">Configure package price, manual profit, shop charges &amp; additional charges</p>
                </div>
            </div>
            <button class="pos-modal-close" onclick="closeAddPlanModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="add_plan">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label class="form-label" style="font-weight:800; font-size:0.82rem;">Select SIM Network *</label>
                    <select name="network" class="form-input" required style="height:42px; font-weight:700;">
                        <option value="jazz">🔴 Jazz / Warid 4G</option>
                        <option value="zong">🟢 Zong 4G</option>
                        <option value="telenor">🔵 Telenor 4G</option>
                        <option value="ufone">🟠 Ufone 4G</option>
                        <option value="onic">🟣 Onic Digital</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-weight:800; font-size:0.82rem;">Package Validity / Duration *</label>
                    <select name="validity" class="form-input" required style="height:42px; font-weight:700;">
                        <option value="30 Days (Monthly)" selected>30 Days (Monthly)</option>
                        <option value="7 Days (Weekly)">7 Days (Weekly)</option>
                        <option value="15 Days">15 Days</option>
                        <option value="3 Days">3 Days</option>
                        <option value="1 Day (Daily)">1 Day (Daily)</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label class="form-label" style="font-weight:800; font-size:0.82rem;">Package Name / Title *</label>
                <input type="text" name="name" class="form-input" placeholder="e.g. Monthly Super Duper, Weekly Mega Plus..." required style="height:42px; font-weight:700;">
            </div>

            <!-- Resource Details Grid -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:16px; box-sizing:border-box;">
                <strong style="font-size:0.82rem; color:#334155; display:block; margin-bottom:10px; text-transform:uppercase; font-weight:800;">
                    <i class="fa-solid fa-list-check" style="color:#64748b; margin-right:4px;"></i> Package Resources &amp; Specifications:
                </strong>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div style="min-width:0;">
                        <label class="form-label" style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:4px;">Internet Data (GB/MB)</label>
                        <input type="text" name="dataGb" class="form-input" placeholder="e.g. 30 GB (15 GB Night)" style="height:38px; font-size:0.85rem; width:100%; box-sizing:border-box;">
                    </div>
                    <div style="min-width:0;">
                        <label class="form-label" style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:4px;">On-Net Calling Mins</label>
                        <input type="text" name="onNetMins" class="form-input" placeholder="e.g. 5,000 Jazz Mins" style="height:38px; font-size:0.85rem; width:100%; box-sizing:border-box;">
                    </div>
                    <div style="min-width:0;">
                        <label class="form-label" style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:4px;">Off-Net Calling Mins</label>
                        <input type="text" name="offNetMins" class="form-input" placeholder="e.g. 500 Other Network Mins" style="height:38px; font-size:0.85rem; width:100%; box-sizing:border-box;">
                    </div>
                    <div style="min-width:0;">
                        <label class="form-label" style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:4px;">SMS Count</label>
                        <input type="text" name="smsCount" class="form-input" placeholder="e.g. 5,000 SMS" style="height:38px; font-size:0.85rem; width:100%; box-sizing:border-box;">
                    </div>
                </div>
                <div style="margin-top:12px; min-width:0;">
                    <label class="form-label" style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:4px;">USSD Subscription Dial Code (Optional)</label>
                    <input type="text" name="ussdCode" class="form-input" placeholder="e.g. *706# or *6464#" style="height:38px; font-size:0.85rem; width:100%; box-sizing:border-box;">
                </div>
            </div>

            <!-- Dynamic Pricing, Charges & Profit Breakdown Grid -->
            <div style="background:#fffbeb; border:1.5px solid #fed7aa; border-radius:12px; padding:16px; margin-bottom:16px; box-sizing:border-box;">
                <strong style="font-size:0.85rem; color:#92400e; display:block; margin-bottom:12px; text-transform:uppercase; font-weight:900;">
                    <i class="fa-solid fa-coins" style="margin-right:4px;"></i> Package Pricing, Charges &amp; Profit Engine:
                </strong>

                <!-- 4 Pricing Columns Grid -->
                <div style="display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:10px; margin-bottom:14px;">
                    <div style="min-width:0;">
                        <label class="form-label" style="display:block; font-weight:800; font-size:0.72rem; color:#475569; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="Base Cost (PKR)">Base Cost (PKR)</label>
                        <input type="number" step="any" min="0" name="costPrice" id="planCostPrice" class="form-input" placeholder="e.g. 1200" oninput="calcPlanPricing('cost')" style="height:40px; font-weight:800; width:100%; box-sizing:border-box; min-width:0; padding:6px 8px; font-size:0.9rem;">
                    </div>
                    <div style="min-width:0;">
                        <label class="form-label" style="display:block; font-weight:800; font-size:0.72rem; color:#059669; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="Manual Profit (PKR)">Manual Profit</label>
                        <input type="number" step="any" min="0" name="manualProfit" id="planManualProfit" class="form-input" placeholder="e.g. 50" value="50" oninput="calcPlanPricing('manual')" style="height:40px; font-weight:800; color:#059669; border-color:#10b981; width:100%; box-sizing:border-box; min-width:0; padding:6px 8px; font-size:0.9rem;">
                    </div>
                    <div style="min-width:0;">
                        <label class="form-label" style="display:block; font-weight:800; font-size:0.72rem; color:#d97706; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="Shop Charges (PKR)">Shop Charges</label>
                        <input type="number" step="any" min="0" name="shopCharges" id="planShopCharges" class="form-input" placeholder="0" value="0" oninput="calcPlanPricing('charges')" style="height:40px; font-weight:800; color:#d97706; border-color:#f59e0b; width:100%; box-sizing:border-box; min-width:0; padding:6px 8px; font-size:0.9rem;">
                    </div>
                    <div style="min-width:0;">
                        <label class="form-label" style="display:block; font-weight:800; font-size:0.72rem; color:#2563eb; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="Additional Charges (PKR)">Addl Charges</label>
                        <input type="number" step="any" min="0" name="additionalCharges" id="planAdditionalCharges" class="form-input" placeholder="0" value="0" oninput="calcPlanPricing('additional')" style="height:40px; font-weight:800; color:#2563eb; border-color:#93c5fd; width:100%; box-sizing:border-box; min-width:0; padding:6px 8px; font-size:0.9rem;">
                    </div>
                </div>

                <!-- Bottom Row: Retail Price & Total Profit -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; border-top:1px dashed #fed7aa; padding-top:12px;">
                    <div style="min-width:0;">
                        <label class="form-label" style="display:block; font-weight:900; font-size:0.82rem; color:#0f172a; margin-bottom:4px;">Retail Customer Price (PKR) *</label>
                        <input type="number" step="any" min="1" name="retailPrice" id="planRetailPrice" class="form-input" placeholder="e.g. 1250" oninput="calcPlanPricing('retail')" required style="height:42px; font-weight:900; font-size:1.1rem; color:#0f172a; background:#ffffff; width:100%; box-sizing:border-box; min-width:0; padding:6px 10px;">
                    </div>
                    <div style="min-width:0;">
                        <label class="form-label" style="display:block; font-weight:900; font-size:0.82rem; color:#059669; margin-bottom:4px;">Total Net Shop Profit (PKR)</label>
                        <input type="number" step="any" name="profitMargin" id="planProfitMargin" class="form-input" placeholder="50" readonly style="height:42px; font-weight:900; font-size:1.1rem; color:#059669; background:#ecfdf5; border-color:#10b981; width:100%; box-sizing:border-box; min-width:0; padding:6px 10px;">
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="closeAddPlanModal()">Cancel</button>
                <button type="submit" class="pos-btn" style="background:#e11d48; color:#fff; font-weight:800; padding:10px 24px; border-radius:8px; border:none; cursor:pointer;">
                    <i class="fa-solid fa-check"></i> Save SIM Package Plan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: ACTIVATE PACKAGE FOR CUSTOMER
     ========================================================================= -->
<div class="pos-modal-overlay" id="activateModal" style="display:none; z-index:99999; backdrop-filter:blur(5px); background:rgba(15,23,42,0.65);">
    <div class="pos-modal" style="max-width:580px; width:95%; padding:24px; border-radius:18px; box-sizing:border-box;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid var(--pos-border); padding-bottom:12px; margin-bottom:16px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:40px; height:40px; border-radius:10px; background:#059669; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div>
                    <h3 style="margin:0; font-size:1.2rem; font-weight:900;">Activate SIM Package</h3>
                    <p style="margin:2px 0 0 0; font-size:0.75rem; color:var(--pos-text-muted);">Record package activation with manual profit &amp; shop charges</p>
                </div>
            </div>
            <button class="pos-modal-close" onclick="closeActivatePackageModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="activate_package">

            <div style="margin-bottom:12px;">
                <label class="form-label" style="font-weight:800; font-size:0.82rem;">Customer Mobile Number *</label>
                <input type="text" name="mobileNumber" id="actMobile" class="form-input" placeholder="0333 1234567" required style="height:42px; font-weight:800; font-size:1.05rem; letter-spacing:1px; width:100%; box-sizing:border-box;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div style="min-width:0;">
                    <label class="form-label" style="font-weight:800; font-size:0.82rem;">Customer Name</label>
                    <input type="text" name="customerName" id="actCustomerName" class="form-input" placeholder="Walk-in Customer" style="height:40px; width:100%; box-sizing:border-box;">
                </div>
                <div style="min-width:0;">
                    <label class="form-label" style="font-weight:800; font-size:0.82rem;">Network *</label>
                    <select name="network" id="actNetwork" class="form-input" required style="height:40px; font-weight:700; width:100%; box-sizing:border-box;">
                        <option value="jazz">Jazz / Warid</option>
                        <option value="zong">Zong 4G</option>
                        <option value="telenor">Telenor 4G</option>
                        <option value="ufone">Ufone 4G</option>
                        <option value="onic">Onic</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label class="form-label" style="font-weight:800; font-size:0.82rem;">Package Name *</label>
                <input type="text" name="packageName" id="actPackageName" class="form-input" placeholder="Package name" required style="height:40px; font-weight:700; width:100%; box-sizing:border-box;">
            </div>

            <!-- Activation Charges & Profit Grid -->
            <div style="background:#f0fdf4; border:1.5px solid #a7f3d0; border-radius:12px; padding:14px; margin-bottom:16px; box-sizing:border-box;">
                <div style="display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:10px; margin-bottom:10px;">
                    <div style="min-width:0;">
                        <label class="form-label" style="display:block; font-weight:800; font-size:0.72rem; color:#0f172a; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="Base Price (PKR)">Base Price (PKR) *</label>
                        <input type="number" step="any" min="0" name="retailPrice" id="actRetailPrice" class="form-input" required oninput="calcActPricing()" style="height:40px; font-weight:900; color:#0f172a; width:100%; box-sizing:border-box; min-width:0; padding:6px 8px; font-size:0.9rem;">
                    </div>
                    <div style="min-width:0;">
                        <label class="form-label" style="display:block; font-weight:800; font-size:0.72rem; color:#059669; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="Manual Profit (PKR)">Manual Profit</label>
                        <input type="number" step="any" min="0" name="manualProfit" id="actManualProfit" class="form-input" placeholder="0" value="0" oninput="calcActPricing()" style="height:40px; font-weight:800; color:#059669; border-color:#10b981; width:100%; box-sizing:border-box; min-width:0; padding:6px 8px; font-size:0.9rem;">
                    </div>
                    <div style="min-width:0;">
                        <label class="form-label" style="display:block; font-weight:800; font-size:0.72rem; color:#d97706; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="Shop Charges (PKR)">Shop Charges</label>
                        <input type="number" step="any" min="0" name="shopCharges" id="actShopCharges" class="form-input" placeholder="30" value="30" oninput="calcActPricing()" style="height:40px; font-weight:800; color:#d97706; border-color:#f59e0b; width:100%; box-sizing:border-box; min-width:0; padding:6px 8px; font-size:0.9rem;">
                    </div>
                    <div style="min-width:0;">
                        <label class="form-label" style="display:block; font-weight:800; font-size:0.72rem; color:#2563eb; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="Additional Charges">Addl Charges</label>
                        <input type="number" step="any" min="0" name="additionalCharges" id="actAdditionalCharges" class="form-input" placeholder="0" value="0" oninput="calcActPricing()" style="height:40px; font-weight:800; color:#2563eb; border-color:#93c5fd; width:100%; box-sizing:border-box; min-width:0; padding:6px 8px; font-size:0.9rem;">
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1px solid #a7f3d0; border-radius:8px; padding:10px 14px; margin-top:8px;">
                    <div>
                        <span style="font-size:0.72rem; color:#64748b; display:block; font-weight:700;">Total Cash to Collect:</span>
                        <strong style="font-size:1.2rem; color:#0f172a; font-weight:900;" id="actTotalCollectedDisplay">PKR 0</strong>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:0.72rem; color:#065f46; display:block; font-weight:700;">Total Shop Profit:</span>
                        <strong style="font-size:1.2rem; color:#059669; font-weight:900;" id="actTotalProfitDisplay">+PKR 0</strong>
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="closeActivatePackageModal()">Cancel</button>
                <button type="submit" class="pos-btn" style="background:#059669; color:#fff; font-weight:800; padding:10px 24px; border-radius:8px; border:none; cursor:pointer;">
                    <i class="fa-solid fa-bolt"></i> Complete Activation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function switchPackageTab(tab) {
    const plansDiv = document.getElementById('tabContentPlans');
    const actDiv = document.getElementById('tabContentActivations');
    const btnPlans = document.getElementById('tabBtnPlans');
    const btnAct = document.getElementById('tabBtnActivations');

    if (tab === 'plans') {
        plansDiv.style.display = 'block';
        actDiv.style.display = 'none';
        btnPlans.className = 'pos-btn pos-btn-primary';
        btnAct.className = 'pos-btn pos-btn-outline';
    } else {
        plansDiv.style.display = 'none';
        actDiv.style.display = 'block';
        btnPlans.className = 'pos-btn pos-btn-outline';
        btnAct.className = 'pos-btn pos-btn-primary';
    }
}

function openAddPlanModal() {
    document.getElementById('addPlanModal').style.display = 'flex';
}
function closeAddPlanModal() {
    document.getElementById('addPlanModal').style.display = 'none';
}

function openActivatePackageModal() {
    document.getElementById('activateModal').style.display = 'flex';
    calcActPricing();
}
function closeActivatePackageModal() {
    document.getElementById('activateModal').style.display = 'none';
}

function calcPlanPricing(trigger) {
    const cost = parseFloat(document.getElementById('planCostPrice').value) || 0;
    let manual = parseFloat(document.getElementById('planManualProfit').value) || 0;
    const shop = parseFloat(document.getElementById('planShopCharges').value) || 0;
    const extra = parseFloat(document.getElementById('planAdditionalCharges').value) || 0;
    let retail = parseFloat(document.getElementById('planRetailPrice').value) || 0;

    if (trigger === 'retail') {
        if (cost > 0) {
            manual = Math.max(0, retail - cost - shop - extra);
            document.getElementById('planManualProfit').value = manual;
        }
    } else {
        if (cost > 0 || manual > 0 || shop > 0 || extra > 0) {
            retail = cost + manual + shop + extra;
            document.getElementById('planRetailPrice').value = retail;
        }
    }

    const totalProfit = manual + shop + extra;
    document.getElementById('planProfitMargin').value = totalProfit >= 0 ? totalProfit : 0;
}

function calcActPricing() {
    const base = parseFloat(document.getElementById('actRetailPrice').value) || 0;
    const manual = parseFloat(document.getElementById('actManualProfit').value) || 0;
    const shop = parseFloat(document.getElementById('actShopCharges').value) || 0;
    const extra = parseFloat(document.getElementById('actAdditionalCharges').value) || 0;

    const totalCollected = base + shop + extra;
    const totalProfit = manual + shop + extra;

    document.getElementById('actTotalCollectedDisplay').innerText = 'PKR ' + totalCollected.toLocaleString();
    document.getElementById('actTotalProfitDisplay').innerText = '+PKR ' + totalProfit.toLocaleString();
}

function quickActivatePlan(plan) {
    document.getElementById('actNetwork').value = plan.network || 'jazz';
    document.getElementById('actPackageName').value = plan.name || '';
    document.getElementById('actRetailPrice').value = plan.retailPrice || 0;
    document.getElementById('actManualProfit').value = plan.manualProfit || 0;
    document.getElementById('actShopCharges').value = plan.shopCharges !== undefined ? plan.shopCharges : 30;
    document.getElementById('actAdditionalCharges').value = plan.additionalCharges || 0;
    openActivatePackageModal();
}

function filterPlansByNetwork(network, btn) {
    const rows = document.querySelectorAll('.plan-row-item');
    rows.forEach(r => {
        if (network === 'all' || r.getAttribute('data-network') === network) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });

    const parent = btn.parentElement;
    parent.querySelectorAll('button').forEach(b => {
        b.className = 'pos-btn pos-btn-outline pos-btn-sm';
        b.style.background = '';
        b.style.color = '';
    });
    btn.className = 'pos-btn pos-btn-sm';
    btn.style.background = '#1e293b';
    btn.style.color = '#fff';
}

function searchPlansCatalog(query) {
    const q = query.toLowerCase().trim();
    const rows = document.querySelectorAll('.plan-row-item');
    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        r.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
}

// Auto-filter packages if URL has ?search=... or ?q=...
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const search = urlParams.get('search') || urlParams.get('q');
    if (search && document.getElementById('catalogSearchInput')) {
        document.getElementById('catalogSearchInput').value = search;
        searchPlansCatalog(search);
    }
});
</script>

<?php 
require_once __DIR__ . '/includes/smart_packages_importer_modal.php';
require_once __DIR__ . '/includes/footer.php'; 
?>
