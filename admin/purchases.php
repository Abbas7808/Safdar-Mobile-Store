<?php
$currentPage = 'purchases';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$purchases = get_json_file('purchases') ?? [];
$suppliers = get_json_file('suppliers') ?? [];
$products = get_json_file('products') ?? [];
$categories = get_json_file('categories') ?? [];

$msg = '';
$msgType = 'success';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create_po';

    // 1. Create Purchase Order
    if ($action === 'create_po') {
        $supplierName = trim($_POST['supplierName'] ?? '');
        $customSupplier = trim($_POST['customSupplierName'] ?? '');
        $supplierPhone = trim($_POST['supplierPhone'] ?? '');
        $supplierCompany = trim($_POST['supplierCompany'] ?? '');

        if ($supplierName === '__custom__' && !empty($customSupplier)) {
            $supplierName = $customSupplier;
            $supExists = false;
            foreach ($suppliers as $s) {
                if (strcasecmp($s['name'], $supplierName) === 0) {
                    $supExists = true;
                    break;
                }
            }
            if (!$supExists) {
                $newSup = [
                    'id' => 'sup-' . time(),
                    'name' => $supplierName,
                    'company' => !empty($supplierCompany) ? $supplierCompany : $supplierName,
                    'phone' => $supplierPhone,
                    'email' => '',
                    'address' => '',
                    'status' => 'active',
                    'createdAt' => date('c')
                ];
                $suppliers[] = $newSup;
                save_json_file('suppliers', $suppliers);
            }
        }

        $invoiceNo = trim($_POST['invoiceNo'] ?? '');
        if (empty($invoiceNo)) {
            $invoiceNo = 'BILL-' . strtoupper(substr(uniqid(), -6));
        }
        $purchaseDate = $_POST['purchaseDate'] ?? date('Y-m-d');
        $paymentMethod = $_POST['paymentMethod'] ?? 'Cash';
        $updateStock = isset($_POST['updateStock']) && $_POST['updateStock'] === '1';
        $notes = trim($_POST['notes'] ?? '');

        // Items array
        $rawItemsJson = $_POST['items_json'] ?? '';
        $items = [];
        if (!empty($rawItemsJson)) {
            $items = json_decode($rawItemsJson, true) ?? [];
        }

        // Calculate Totals
        $calculatedTotal = 0;
        $processedItems = [];
        foreach ($items as $item) {
            $qty = max(0.1, floatval($item['qty'] ?? 1));
            $price = max(0, floatval($item['costPrice'] ?? 0));
            $sellingPrice = max(0, floatval($item['sellingPrice'] ?? ($price * 1.2)));
            $lineTotal = round($qty * $price, 2);
            $calculatedTotal += $lineTotal;

            $prodId = $item['productId'] ?? '';
            $prodName = trim($item['name'] ?? 'Product');
            $sku = trim($item['sku'] ?? '');
            $unit = trim($item['unit'] ?? 'Pcs');
            $category = trim($item['category'] ?? 'accessories');

            // If brand new product on the fly
            if ($prodId === '__custom_prod__' || empty($prodId) || strpos($prodId, 'custom-') === 0) {
                $newProdId = 'prod-' . time() . '-' . rand(100, 999);
                $skuFinal = !empty($sku) ? $sku : ('SKU-' . rand(1000, 9999));
                
                $newProductEntry = [
                    'id' => $newProdId,
                    'name' => $prodName,
                    'sku' => $skuFinal,
                    'barcode' => $skuFinal,
                    'category' => $category,
                    'costPrice' => $price,
                    'sellingPrice' => $sellingPrice,
                    'stock' => $updateStock ? $qty : 0,
                    'unit' => $unit,
                    'unitLabel' => $unit,
                    'minStock' => 2,
                    'createdAt' => date('c'),
                    'image' => ''
                ];
                $products[] = $newProductEntry;
                $prodId = $newProdId;
                $sku = $skuFinal;
            } elseif ($updateStock) {
                // Update existing product stock & cost price
                foreach ($products as &$p) {
                    if ($p['id'] === $prodId) {
                        $p['stock'] = floatval($p['stock'] ?? 0) + $qty;
                        if ($price > 0) {
                            $p['costPrice'] = $price;
                        }
                        if ($sellingPrice > 0) {
                            $p['sellingPrice'] = $sellingPrice;
                        }
                        if (!empty($unit)) {
                            $p['unit'] = $unit;
                        }
                        break;
                    }
                }
            }

            $processedItems[] = [
                'productId' => $prodId,
                'name' => $prodName,
                'sku' => $sku,
                'category' => $category,
                'costPrice' => $price,
                'sellingPrice' => $sellingPrice,
                'qty' => $qty,
                'unit' => $unit,
                'total' => $lineTotal
            ];
        }

        if ($updateStock) {
            save_json_file('products', $products);
        }

        $totalAmount = floatval($_POST['totalAmount'] ?? $calculatedTotal);
        if ($totalAmount <= 0 && $calculatedTotal > 0) {
            $totalAmount = $calculatedTotal;
        }

        $paidAmount = floatval($_POST['paidAmount'] ?? $totalAmount);
        if ($paidAmount > $totalAmount) {
            $paidAmount = $totalAmount;
        }
        $pendingAmount = max(0, round($totalAmount - $paidAmount, 2));

        // Determine payment status
        if ($pendingAmount <= 0) {
            $paymentStatus = 'paid';
        } elseif ($paidAmount > 0) {
            $paymentStatus = 'partial';
        } else {
            $paymentStatus = 'pending';
        }

        if (!empty($supplierName) && $totalAmount > 0 && !empty($processedItems)) {
            $poNo = 'PO-' . date('Ymd') . '-' . sprintf('%03d', count($purchases) + 1);

            $newPO = [
                'id' => 'po-' . time() . '-' . rand(100, 999),
                'poNo' => $poNo,
                'invoiceNo' => $invoiceNo,
                'supplierName' => $supplierName,
                'supplierPhone' => $supplierPhone,
                'totalAmount' => $totalAmount,
                'paidAmount' => $paidAmount,
                'pendingAmount' => $pendingAmount,
                'paymentStatus' => $paymentStatus,
                'paymentMethod' => $paymentMethod,
                'updateStock' => $updateStock,
                'items' => $processedItems,
                'notes' => $notes,
                'paymentHistory' => [
                    [
                        'amount' => $paidAmount,
                        'date' => $purchaseDate,
                        'method' => $paymentMethod,
                        'notes' => 'Initial payment upon PO creation'
                    ]
                ],
                'date' => $purchaseDate,
                'createdAt' => date('c')
            ];

            $purchases[] = $newPO;
            save_json_file('purchases', $purchases);

            if (class_exists('SecurityLogger')) {
                $sessionUser = get_session_user();
                $uName = $sessionUser['username'] ?? 'admin';
                SecurityLogger::logEvent($uName, 'super_admin', 'PURCHASE_ORDER_CREATED', "Created PO #{$poNo} (Inv #{$invoiceNo}) from {$supplierName} for PKR " . number_format($totalAmount));
            }

            $msg = "Purchase Order #{$poNo} (Invoice #{$invoiceNo}) recorded successfully!" . ($updateStock ? " Inventory stock updated." : "");
            $msgType = 'success';
        } else {
            $msg = "Please fill in all required fields (Supplier Name, at least 1 Product with valid Quantity & Cost Price).";
            $msgType = 'error';
        }
    }

    // 2. Settle Due / Record Payment Installment
    elseif ($action === 'settle_due') {
        $poId = $_POST['poId'] ?? '';
        $paymentAmount = max(0, floatval($_POST['paymentAmount'] ?? 0));
        $payMethod = $_POST['settlePaymentMethod'] ?? 'Cash';
        $payNotes = trim($_POST['settleNotes'] ?? 'Installment payment');
        $payDate = $_POST['settleDate'] ?? date('Y-m-d H:i');

        if (!empty($poId) && $paymentAmount > 0) {
            $found = false;
            foreach ($purchases as &$po) {
                if ($po['id'] === $poId) {
                    $found = true;
                    $po['paidAmount'] = floatval($po['paidAmount'] ?? 0) + $paymentAmount;
                    if ($po['paidAmount'] > $po['totalAmount']) {
                        $po['paidAmount'] = $po['totalAmount'];
                    }
                    $po['pendingAmount'] = max(0, round($po['totalAmount'] - $po['paidAmount'], 2));

                    if ($po['pendingAmount'] <= 0) {
                        $po['paymentStatus'] = 'paid';
                    } elseif ($po['paidAmount'] > 0) {
                        $po['paymentStatus'] = 'partial';
                    } else {
                        $po['paymentStatus'] = 'pending';
                    }

                    if (!isset($po['paymentHistory']) || !is_array($po['paymentHistory'])) {
                        $po['paymentHistory'] = [];
                    }

                    $po['paymentHistory'][] = [
                        'amount' => $paymentAmount,
                        'date' => $payDate,
                        'method' => $payMethod,
                        'notes' => $payNotes
                    ];

                    $msg = "Payment of PKR " . number_format($paymentAmount) . " recorded for PO #{$po['poNo']}. Remaining Balance: PKR " . number_format($po['pendingAmount']);
                    $msgType = 'success';
                    break;
                }
            }

            if ($found) {
                save_json_file('purchases', $purchases);
            }
        }
    }

    // 3. Edit Purchase Order Notes / Invoice #
    elseif ($action === 'edit_po') {
        $poId = $_POST['poId'] ?? '';
        if ($poId) {
            foreach ($purchases as &$po) {
                if ($po['id'] === $poId) {
                    $po['invoiceNo'] = trim($_POST['editInvoiceNo'] ?? $po['invoiceNo']);
                    $po['supplierName'] = trim($_POST['editSupplierName'] ?? $po['supplierName']);
                    $po['date'] = $_POST['editPurchaseDate'] ?? $po['date'];
                    $po['paymentMethod'] = $_POST['editPaymentMethod'] ?? $po['paymentMethod'];
                    $po['notes'] = trim($_POST['editNotes'] ?? $po['notes']);
                    $msg = "Purchase Order {$po['poNo']} updated successfully.";
                    $msgType = 'success';
                    break;
                }
            }
            save_json_file('purchases', $purchases);
        }
    }

    // 4. Delete Purchase Order
    elseif ($action === 'delete_po') {
        $poId = $_POST['poId'] ?? '';
        $rollbackStock = isset($_POST['rollbackStock']) && $_POST['rollbackStock'] === '1';

        if ($poId) {
            $deletedPoNo = '';
            $targetPo = null;
            $newPurchases = [];
            foreach ($purchases as $p) {
                if ($p['id'] === $poId) {
                    $deletedPoNo = $p['poNo'] ?? 'PO';
                    $targetPo = $p;
                } else {
                    $newPurchases[] = $p;
                }
            }
            $purchases = $newPurchases;
            save_json_file('purchases', $purchases);

            // Rollback inventory stock if requested
            if ($rollbackStock && $targetPo && !empty($targetPo['items'])) {
                foreach ($targetPo['items'] as $it) {
                    $pId = $it['productId'] ?? '';
                    $qty = floatval($it['qty'] ?? 0);
                    if ($pId && $qty > 0) {
                        foreach ($products as &$pr) {
                            if ($pr['id'] === $pId) {
                                $pr['stock'] = max(0, floatval($pr['stock'] ?? 0) - $qty);
                                break;
                            }
                        }
                    }
                }
                save_json_file('products', $products);
            }

            $msg = "Purchase Order '{$deletedPoNo}' has been deleted." . ($rollbackStock ? " Stock quantities were rolled back." : "");
            $msgType = 'success';
        }
    }
}

// Calculate Summary Metrics
$totalWholesalePurchases = 0;
$totalWholesalePaid = 0;
$totalWholesalePending = 0;
$totalPoCount = count($purchases);
$paidPoCount = 0;
$partialPoCount = 0;
$pendingPoCount = 0;

foreach ($purchases as $po) {
    $tot = floatval($po['totalAmount'] ?? 0);
    $paid = floatval($po['paidAmount'] ?? ($po['paymentStatus'] === 'paid' ? $tot : 0));
    $pending = floatval($po['pendingAmount'] ?? max(0, $tot - $paid));

    $totalWholesalePurchases += $tot;
    $totalWholesalePaid += $paid;
    $totalWholesalePending += $pending;

    $st = $po['paymentStatus'] ?? ($pending <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'pending'));
    if ($st === 'paid') $paidPoCount++;
    elseif ($st === 'partial') $partialPoCount++;
    else $pendingPoCount++;
}
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content" style="max-width:100%; box-sizing:border-box; padding:20px;">
        <!-- Page Header -->
        <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom:20px;">
            <div>
                <h1 style="font-size:1.5rem; margin:0; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-truck-ramp-box" style="color:var(--pos-red);"></i> Stock Purchases &amp; Supplier Invoices
                </h1>
                <p class="page-header-sub" style="margin-top:4px; font-size:0.85rem; color:var(--pos-text-sec);">
                    Seamless inward stock receiving, wholesale invoices, auto-inventory updates, and supplier debt management
                </p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a href="suppliers.php" class="pos-btn pos-btn-outline pos-btn-sm" style="font-size:0.82rem;">
                    <i class="fa-solid fa-building"></i> Suppliers Directory
                </a>
                <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="exportPurchasesCsv()" style="font-size:0.82rem;">
                    <i class="fa-solid fa-file-csv"></i> Export CSV
                </button>
                <button type="button" class="pos-btn pos-btn-primary pos-btn-sm" onclick="document.getElementById('supplierSelect').focus(); window.scrollTo({top:200, behavior:'smooth'});" style="font-size:0.82rem;">
                    <i class="fa-solid fa-plus"></i> New Purchase Order
                </button>
            </div>
        </div>

        <!-- Alert Notification Message -->
        <?php if (!empty($msg)): ?>
            <div class="login-error" style="margin-bottom:18px; background:<?php echo $msgType === 'success' ? '#ecfdf5' : '#fef2f2'; ?>; border:1px solid <?php echo $msgType === 'success' ? '#a7f3d0' : '#fecaca'; ?>; color:<?php echo $msgType === 'success' ? '#065f46' : '#dc2626'; ?>; padding:12px 16px; border-radius:10px; font-weight:700; display:flex; align-items:center; gap:10px; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                <i class="fa-solid <?php echo $msgType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>" style="font-size:1.1rem;"></i>
                <span><?php echo htmlspecialchars($msg); ?></span>
            </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:22px;">
            <div class="stat-card" style="padding:16px; border-radius:12px;">
                <div class="stat-icon red"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <div class="stat-info">
                    <div class="stat-label" style="font-size:0.75rem; font-weight:700; text-transform:uppercase;">Total Inward Purchases</div>
                    <div class="stat-value" style="font-size:1.4rem; font-weight:900;">PKR <?php echo number_format($totalWholesalePurchases); ?></div>
                    <div style="font-size:0.72rem; color:#6b7280; margin-top:2px; font-weight:600;"><?php echo $totalPoCount; ?> Total PO Orders Recorded</div>
                </div>
            </div>

            <div class="stat-card" style="padding:16px; border-radius:12px;">
                <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
                <div class="stat-info">
                    <div class="stat-label" style="font-size:0.75rem; font-weight:700; text-transform:uppercase;">Total Paid to Suppliers</div>
                    <div class="stat-value" style="font-size:1.4rem; font-weight:900; color:#059669;">PKR <?php echo number_format($totalWholesalePaid); ?></div>
                    <div style="font-size:0.72rem; color:#059669; margin-top:2px; font-weight:600;"><?php echo $paidPoCount; ?> Invoices Fully Settled</div>
                </div>
            </div>

            <div class="stat-card" style="padding:16px; border-radius:12px; <?php echo $totalWholesalePending > 0 ? 'border:1.5px solid #fca5a5; background:#fffaf0;' : ''; ?>">
                <div class="stat-icon danger" style="<?php echo $totalWholesalePending > 0 ? 'background:#ef4444; color:#fff;' : ''; ?>"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div class="stat-info">
                    <div class="stat-label" style="font-size:0.75rem; font-weight:700; text-transform:uppercase;">Pending Supplier Debt</div>
                    <div class="stat-value" style="font-size:1.4rem; font-weight:900; color:<?php echo $totalWholesalePending > 0 ? '#dc2626' : '#10b981'; ?>;">
                        PKR <?php echo number_format($totalWholesalePending); ?>
                    </div>
                    <div style="font-size:0.72rem; color:<?php echo $totalWholesalePending > 0 ? '#dc2626' : '#6b7280'; ?>; margin-top:2px; font-weight:700;">
                        <?php echo $totalWholesalePending > 0 ? ($partialPoCount + $pendingPoCount) . ' Invoices with Remaining Balance' : 'All Supplier Dues Cleared'; ?>
                    </div>
                </div>
            </div>

            <div class="stat-card" style="padding:16px; border-radius:12px;">
                <div class="stat-icon gold"><i class="fa-solid fa-boxes-packing"></i></div>
                <div class="stat-info">
                    <div class="stat-label" style="font-size:0.75rem; font-weight:700; text-transform:uppercase;">Active Inventory Catalog</div>
                    <div class="stat-value" style="font-size:1.4rem; font-weight:900;"><?php echo count($products); ?> Products</div>
                    <div style="font-size:0.72rem; color:#d97706; margin-top:2px; font-weight:600;"><?php echo count($suppliers); ?> Registered Suppliers</div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 1. RECORD NEW PURCHASE ORDER FORM CARD -->
        <!-- ========================================================================= -->
        <div class="pos-card" style="margin-bottom:26px; padding:22px; border-radius:14px; border:1px solid #e2e8f0; box-shadow:0 4px 15px rgba(0,0,0,0.03); max-width:100%; box-sizing:border-box;">
            <div class="pos-card-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid var(--pos-border); padding-bottom:12px; margin-bottom:18px;">
                <h3 class="pos-card-title" style="font-size:1.15rem; margin:0; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-cart-plus" style="color:var(--pos-red);"></i> Record Inward Stock Purchase
                </h3>
                <span style="font-size:0.75rem; font-weight:700; color:var(--pos-text-sec); background:var(--pos-bg); padding:4px 12px; border-radius:20px; border:1px solid var(--pos-border);">
                    <i class="fa-solid fa-shield-halved" style="color:var(--pos-gold);"></i> Real-time Stock Sync Enabled
                </span>
            </div>

            <form id="purchaseOrderForm" method="POST" action="purchases.php" style="width:100%; box-sizing:border-box;">
                <input type="hidden" name="action" value="create_po">
                <input type="hidden" name="items_json" id="itemsJsonInput" value="">
                <input type="hidden" name="totalAmount" id="hiddenTotalAmount" value="0">

                <!-- Row 1: Supplier & Invoice Metadata -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:14px; margin-bottom:18px;">
                    <!-- Supplier Name -->
                    <div class="form-group">
                        <label class="form-label" style="font-size:0.8rem; font-weight:700;">
                            <i class="fa-solid fa-building" style="color:var(--pos-red); margin-right:4px;"></i> Supplier / Distributor *
                        </label>
                        <select name="supplierName" id="supplierSelect" class="form-select" onchange="onSupplierChange(this)" required style="width:100%; font-size:0.85rem; padding:8px 10px; font-weight:600;">
                            <option value="">-- Choose Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['name']); ?>" data-phone="<?php echo htmlspecialchars($s['phone'] ?? ''); ?>" data-company="<?php echo htmlspecialchars($s['company'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($s['name']); ?> <?php echo !empty($s['company']) ? '(' . htmlspecialchars($s['company']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__custom__" style="color:var(--pos-red); font-weight:800;">+ Add New Supplier...</option>
                        </select>

                        <!-- Custom Supplier In-line Fields -->
                        <div id="customSupplierWrapper" style="display:none; margin-top:8px; padding:10px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px;">
                            <input type="text" name="customSupplierName" id="customSupplierInput" class="form-input" placeholder="Supplier / Vendor Name *" style="font-size:0.82rem; padding:6px 10px; margin-bottom:6px;">
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                                <input type="text" name="supplierPhone" class="form-input" placeholder="Phone (e.g. 0333 1234567)" style="font-size:0.8rem; padding:5px 8px;">
                                <input type="text" name="supplierCompany" class="form-input" placeholder="Company / Market" style="font-size:0.8rem; padding:5px 8px;">
                            </div>
                        </div>
                    </div>

                    <!-- Invoice No. -->
                    <div class="form-group">
                        <label class="form-label" style="font-size:0.8rem; font-weight:700;">
                            <i class="fa-solid fa-file-invoice" style="color:var(--pos-gold); margin-right:4px;"></i> Supplier Bill / Invoice No. *
                        </label>
                        <input type="text" name="invoiceNo" id="invoiceNoInput" class="form-input" placeholder="e.g. INV-98421 or BILL-772" required style="font-size:0.88rem; font-weight:700; padding:8px 10px;">
                    </div>

                    <!-- Purchase Date -->
                    <div class="form-group">
                        <label class="form-label" style="font-size:0.8rem; font-weight:700;">
                            <i class="fa-solid fa-calendar" style="color:var(--pos-text-sec); margin-right:4px;"></i> Inward Date *
                        </label>
                        <input type="date" name="purchaseDate" class="form-input" value="<?php echo date('Y-m-d'); ?>" required style="font-size:0.88rem; padding:8px 10px;">
                    </div>

                    <!-- Payment Method -->
                    <div class="form-group">
                        <label class="form-label" style="font-size:0.8rem; font-weight:700;">
                            <i class="fa-solid fa-money-bill-wave" style="color:var(--pos-success); margin-right:4px;"></i> Payment Method
                        </label>
                        <select name="paymentMethod" class="form-select" style="font-size:0.88rem; padding:8px 10px; font-weight:600;">
                            <option value="Cash">Cash in Hand</option>
                            <option value="Bank Transfer">Bank Transfer / Online</option>
                            <option value="Easypaisa / JazzCash">Easypaisa / JazzCash</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Credit / Pay Later">Full Credit (Pay Later)</option>
                        </select>
                    </div>
                </div>

                <!-- Row 2: Product Line Items Section -->
                <div style="background:var(--pos-bg); border:1.5px solid var(--pos-border); border-radius:12px; padding:16px; margin-bottom:20px; width:100%; box-sizing:border-box;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                        <div>
                            <h4 style="margin:0; font-size:1rem; font-weight:900; color:var(--pos-text); display:flex; align-items:center; gap:6px;">
                                <i class="fa-solid fa-cart-flatbed" style="color:var(--pos-red);"></i> Purchased Items Breakdown
                            </h4>
                            <p style="margin:2px 0 0 0; font-size:0.78rem; color:var(--pos-text-sec);">Search existing catalog items or add new items on the fly with live pricing</p>
                        </div>

                        <button type="button" class="pos-btn pos-btn-primary pos-btn-sm" onclick="addNewProductRow()" style="padding:6px 14px; font-size:0.82rem; font-weight:700;">
                            <i class="fa-solid fa-plus"></i> Add Product Line
                        </button>
                    </div>

                    <!-- Products Table Container -->
                    <div style="width:100%; overflow-x:auto; box-sizing:border-box;">
                        <table class="data-table" id="poProductsTable" style="background:#fff; border-radius:8px; width:100%; min-width:680px; table-layout:fixed; border:1px solid var(--pos-border);">
                            <thead>
                                <tr style="background:#f8fafc; font-size:0.75rem;">
                                    <th style="width:34%; padding:8px 10px;">Product Name *</th>
                                    <th style="width:14%; padding:8px 10px;">SKU / Code</th>
                                    <th style="width:10%; padding:8px 10px; text-align:center;">Unit</th>
                                    <th style="width:14%; padding:8px 10px; text-align:right;">Cost Price (PKR) *</th>
                                    <th style="width:10%; padding:8px 10px; text-align:center;">Qty *</th>
                                    <th style="width:12%; padding:8px 10px; text-align:right;">Line Total</th>
                                    <th style="width:6%; text-align:center; padding:8px 4px;">Del</th>
                                </tr>
                            </thead>
                            <tbody id="poProductsTbody">
                                <!-- Dynamic Rows injected here -->
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; flex-wrap:wrap; gap:10px;">
                        <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="addNewProductRow()" style="border-style:dashed; font-weight:700; font-size:0.82rem;">
                            <i class="fa-solid fa-plus"></i> + Add Another Product
                        </button>

                        <label style="display:flex; align-items:center; gap:8px; font-size:0.82rem; font-weight:700; color:var(--pos-text); cursor:pointer; background:#fff; padding:6px 12px; border-radius:8px; border:1px solid var(--pos-border);">
                            <input type="checkbox" name="updateStock" value="1" checked style="width:16px; height:16px; accent-color:var(--pos-red); cursor:pointer;">
                            <span>Automatically update inventory stock &amp; cost price</span>
                        </label>
                    </div>
                </div>

                <!-- Row 3: Financials, Paid Amount & Pending Balances -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap:14px; background:#fff; border:1.5px solid var(--pos-border); border-radius:12px; padding:18px; margin-bottom:20px; box-sizing:border-box;">
                    <!-- Total Invoice Box -->
                    <div style="display:flex; flex-direction:column; justify-content:center; background:var(--pos-bg); padding:14px 16px; border-radius:10px; border:1px solid var(--pos-border);">
                        <span style="font-size:0.75rem; font-weight:800; color:var(--pos-text-sec); text-transform:uppercase; letter-spacing:0.5px;">
                            <i class="fa-solid fa-calculator" style="color:var(--pos-gold); margin-right:4px;"></i> Total Inward Bill
                        </span>
                        <div id="displayTotalInvoice" style="font-size:1.6rem; font-weight:900; color:var(--pos-text); margin:4px 0;">
                            PKR 0
                        </div>
                        <div style="font-size:0.72rem; color:var(--pos-text-muted);">
                            Sum of all item unit costs × quantities
                        </div>
                    </div>

                    <!-- Amount Paid Box -->
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label class="form-label" style="display:flex; justify-content:space-between; align-items:center; font-size:0.78rem; font-weight:700;">
                            <span><i class="fa-solid fa-hand-holding-dollar" style="color:var(--pos-success); margin-right:4px;"></i> Amount Paid (PKR) *</span>
                            <span id="paymentStatusBadge" class="status-badge status-completed" style="font-size:0.65rem; padding:2px 8px; border-radius:10px;">PAID IN FULL</span>
                        </label>
                        <input type="number" name="paidAmount" id="paidAmountInput" class="form-input" min="0" step="any" placeholder="0" oninput="recalculateBalances()" required style="font-size:1.15rem; font-weight:900; padding:8px 10px; color:#059669;">
                        
                        <!-- Quick Payment Presets -->
                        <div style="display:flex; gap:4px; margin-top:2px;">
                            <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" style="flex:1; padding:3px 6px; font-size:0.72rem; font-weight:700;" onclick="setPaymentPreset('full')">
                                100% Full
                            </button>
                            <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" style="flex:1; padding:3px 6px; font-size:0.72rem; font-weight:700;" onclick="setPaymentPreset('half')">
                                50% Half
                            </button>
                            <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" style="flex:1; padding:3px 6px; font-size:0.72rem; font-weight:700; color:#dc2626;" onclick="setPaymentPreset('zero')">
                                0 / Credit
                            </button>
                        </div>
                    </div>

                    <!-- Pending Supplier Due Box -->
                    <div style="display:flex; flex-direction:column; justify-content:center; background:#fef2f2; padding:14px 16px; border-radius:10px; border:1.5px solid #fecaca;">
                        <span style="font-size:0.75rem; font-weight:800; color:#991b1b; text-transform:uppercase; letter-spacing:0.5px;">
                            <i class="fa-solid fa-clock-rotate-left" style="color:#dc2626; margin-right:4px;"></i> Remaining Pending Due
                        </span>
                        <div id="displayPendingAmount" style="font-size:1.6rem; font-weight:900; color:#dc2626; margin:4px 0;">
                            PKR 0
                        </div>
                        <div id="pendingStatusHelp" style="font-size:0.72rem; color:#b91c1c; font-weight:700;">
                            No remaining balance (Fully Settled)
                        </div>
                    </div>
                </div>

                <!-- Notes & Submit Action -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:14px; align-items:flex-end;">
                    <div class="form-group">
                        <label class="form-label" style="font-size:0.8rem; font-weight:700;">Order Notes / Transport / Warranty Remarks (Optional)</label>
                        <input type="text" name="notes" class="form-input" placeholder="e.g. Inward via TCS cargo / 1-year official distributor warranty" style="font-size:0.85rem; padding:9px 12px;">
                    </div>

                    <div style="display:flex; justify-content:flex-end;">
                        <button type="submit" class="pos-btn pos-btn-primary pos-btn-block" style="padding:12px 24px; font-size:0.95rem; font-weight:900; box-shadow:0 4px 12px rgba(215,25,32,0.25);">
                            <i class="fa-solid fa-check"></i> Save &amp; Record Stock Purchase
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ========================================================================= -->
        <!-- 2. PURCHASE ORDERS DIRECTORY & HISTORY TABLE -->
        <!-- ========================================================================= -->
        <div class="data-table-wrap" style="box-sizing:border-box; width:100%; max-width:100%; border-radius:14px; box-shadow:0 4px 15px rgba(0,0,0,0.03); border:1px solid var(--pos-border);">
            <!-- Toolbar -->
            <div class="data-table-toolbar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding:14px 18px; border-bottom:1px solid var(--pos-border); background:#f8fafc;">
                <!-- Filter Tabs & Search -->
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <!-- Live Search -->
                    <div class="data-table-search" style="min-width:240px; padding:6px 12px; background:#fff; border-radius:8px; border:1px solid #cbd5e1;">
                        <i class="fa-solid fa-magnifying-glass" style="font-size:0.85rem; color:#64748b;"></i>
                        <input type="text" id="poSearchInput" placeholder="Search supplier, bill #, PO #, item..." onkeyup="filterPoTable()" style="font-size:0.85rem;">
                    </div>

                    <!-- Payment Status Filter -->
                    <select id="poStatusFilter" class="form-select" onchange="filterPoTable()" style="width:150px; padding:6px 10px; font-size:0.82rem; font-weight:700;">
                        <option value="all">All Statuses (<?php echo $totalPoCount; ?>)</option>
                        <option value="paid">Paid in Full (<?php echo $paidPoCount; ?>)</option>
                        <option value="partial">Partial Dues (<?php echo $partialPoCount; ?>)</option>
                        <option value="pending">Credit / Unpaid (<?php echo $pendingPoCount; ?>)</option>
                    </select>

                    <!-- Supplier Filter -->
                    <select id="poSupplierFilter" class="form-select" onchange="filterPoTable()" style="width:160px; padding:6px 10px; font-size:0.82rem;">
                        <option value="all">All Suppliers</option>
                        <?php foreach ($suppliers as $sp): ?>
                            <option value="<?php echo htmlspecialchars(strtolower($sp['name'])); ?>">
                                <?php echo htmlspecialchars($sp['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="font-size:0.85rem; font-weight:800; color:var(--pos-text-sec);">
                    Showing: <span id="poVisibleCount"><?php echo count($purchases); ?></span> / <?php echo count($purchases); ?> Purchase Orders
                </div>
            </div>

            <table class="data-table" id="poHistoryTable" style="width:100%;">
                <thead>
                    <tr style="font-size:0.75rem; background:#f1f5f9;">
                        <th style="padding:10px 12px; width:14%;">PO # &amp; Date</th>
                        <th style="padding:10px 12px; width:18%;">Supplier / Vendor</th>
                        <th style="padding:10px 12px; width:13%;">Supplier Bill #</th>
                        <th style="padding:10px 12px; width:18%;">Purchased Items</th>
                        <th style="padding:10px 12px; width:12%; text-align:right;">Total Bill</th>
                        <th style="padding:10px 12px; width:11%; text-align:right;">Paid</th>
                        <th style="padding:10px 12px; width:11%; text-align:right;">Pending Due</th>
                        <th style="padding:10px 12px; width:9%; text-align:center;">Status</th>
                        <th style="text-align:right; padding:10px 12px; width:15%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center; padding:45px 20px; color:var(--pos-text-sec);">
                                <i class="fa-solid fa-box-open" style="font-size:2.5rem; color:#cbd5e1; margin-bottom:10px; display:block;"></i>
                                <strong style="font-size:1rem; color:var(--pos-text);">No Purchase Orders Recorded Yet</strong>
                                <p style="margin:4px 0 0 0; font-size:0.85rem;">Use the form above to record wholesale stock purchases and track supplier invoices easily.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach (array_reverse($purchases) as $po): 
                            $poTot = floatval($po['totalAmount'] ?? 0);
                            $poPaid = floatval($po['paidAmount'] ?? ($po['paymentStatus'] === 'paid' ? $poTot : 0));
                            $poPending = floatval($po['pendingAmount'] ?? max(0, $poTot - $poPaid));
                            $poItems = $po['items'] ?? [];
                            $itemCount = count($poItems);
                            $status = $po['paymentStatus'] ?? ($poPending <= 0 ? 'paid' : ($poPaid > 0 ? 'partial' : 'pending'));
                            $supPhone = $po['supplierPhone'] ?? '';
                            $itemNamesStr = implode(' ', array_map(function($i) { return ($i['name'] ?? '') . ' ' . ($i['sku'] ?? ''); }, $poItems));
                            $searchBlob = strtolower(($po['poNo'] ?? '') . ' ' . ($po['supplierName'] ?? '') . ' ' . ($po['invoiceNo'] ?? '') . ' ' . $itemNamesStr . ' ' . ($po['paymentMethod'] ?? ''));
                        ?>
                            <tr data-po-row data-status="<?php echo htmlspecialchars($status); ?>" data-supplier="<?php echo htmlspecialchars(strtolower($po['supplierName'] ?? '')); ?>" data-search="<?php echo htmlspecialchars($searchBlob); ?>" style="font-size:0.85rem;">
                                <td style="padding:10px 12px;">
                                    <strong style="color:var(--pos-red); font-family:var(--pos-font-heading); font-size:0.88rem;"><?php echo htmlspecialchars($po['poNo']); ?></strong>
                                    <div style="font-size:0.72rem; color:var(--pos-text-sec); margin-top:2px;">
                                        <i class="fa-regular fa-calendar"></i> <?php echo date('M d, Y', strtotime($po['date'] ?? $po['createdAt'])); ?>
                                    </div>
                                </td>
                                <td style="padding:10px 12px;">
                                    <strong style="color:var(--pos-text); display:block; font-size:0.88rem;"><?php echo htmlspecialchars($po['supplierName']); ?></strong>
                                    <?php if (!empty($supPhone)): ?>
                                        <span style="font-size:0.72rem; color:#059669; font-weight:600;"><i class="fa-solid fa-phone" style="font-size:0.65rem;"></i> <?php echo htmlspecialchars($supPhone); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px 12px;">
                                    <span style="font-family:monospace; background:#f1f5f9; border:1px solid #cbd5e1; padding:3px 8px; border-radius:6px; font-weight:800; color:#0f172a; font-size:0.82rem;">
                                        <?php echo htmlspecialchars($po['invoiceNo'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td style="padding:10px 12px;">
                                    <strong style="font-size:0.82rem; color:#1e293b;"><?php echo $itemCount; ?> Item<?php echo $itemCount > 1 ? 's' : ''; ?></strong>
                                    <?php if ($itemCount > 0): 
                                        $previewNames = array_slice(array_map(function($i) { return ($i['name'] ?? 'Product') . ' (x' . ($i['qty'] ?? 1) . ')'; }, $poItems), 0, 2);
                                    ?>
                                        <div style="font-size:0.72rem; color:var(--pos-text-sec); max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">
                                            <?php echo htmlspecialchars(implode(', ', $previewNames) . ($itemCount > 2 ? '...' : '')); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px 12px; text-align:right;">
                                    <strong style="color:var(--pos-text); font-size:0.9rem;">PKR <?php echo number_format($poTot); ?></strong>
                                </td>
                                <td style="padding:10px 12px; text-align:right;">
                                    <span style="color:#059669; font-weight:800;">PKR <?php echo number_format($poPaid); ?></span>
                                </td>
                                <td style="padding:10px 12px; text-align:right;">
                                    <?php if ($poPending > 0): ?>
                                        <strong style="color:#dc2626; font-weight:900;">PKR <?php echo number_format($poPending); ?></strong>
                                    <?php else: ?>
                                        <span style="color:#10b981; font-weight:700; font-size:0.8rem;"><i class="fa-solid fa-check"></i> Cleared</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px 12px; text-align:center;">
                                    <?php if ($status === 'paid'): ?>
                                        <span class="status-badge status-completed" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:3px 8px; font-size:0.7rem; font-weight:800; border-radius:12px;">PAID</span>
                                    <?php elseif ($status === 'partial'): ?>
                                        <span class="status-badge" style="background:#fffbeb; color:#b45309; border:1px solid #fde68a; padding:3px 8px; font-size:0.7rem; font-weight:800; border-radius:12px;">PARTIAL</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending" style="background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:3px 8px; font-size:0.7rem; font-weight:800; border-radius:12px;">CREDIT</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right; white-space:nowrap; padding:10px 12px;">
                                    <!-- View & Print Details Modal -->
                                    <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="openPoDetailsModal(<?php echo htmlspecialchars(json_encode($po), ENT_QUOTES, 'UTF-8'); ?>)" title="View Detailed Invoice & Print" style="padding:5px 9px; font-size:0.78rem;">
                                        <i class="fa-solid fa-file-invoice"></i> View
                                    </button>

                                    <!-- Settle Due Button if Pending > 0 -->
                                    <?php if ($poPending > 0): ?>
                                        <button type="button" class="pos-btn pos-btn-gold pos-btn-sm" onclick="openSettlePaymentModal(<?php echo htmlspecialchars(json_encode($po), ENT_QUOTES, 'UTF-8'); ?>)" title="Record Due Payment" style="margin-left:3px; padding:5px 9px; font-size:0.78rem;">
                                            <i class="fa-solid fa-hand-holding-dollar"></i> Pay Due
                                        </button>
                                    <?php endif; ?>

                                    <!-- Edit Button -->
                                    <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="openEditPoModal(<?php echo htmlspecialchars(json_encode($po), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit PO Remarks & Bill #" style="margin-left:3px; padding:5px 8px; font-size:0.78rem; color:#475569;">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <!-- Delete PO Button -->
                                    <form method="POST" action="purchases.php" onsubmit="return confirm('Are you sure you want to delete Purchase Order <?php echo htmlspecialchars($po['poNo']); ?>?');" style="display:inline; margin-left:3px;">
                                        <input type="hidden" name="action" value="delete_po">
                                        <input type="hidden" name="poId" value="<?php echo htmlspecialchars($po['id']); ?>">
                                        <button type="submit" class="pos-btn pos-btn-outline pos-btn-sm" style="color:#ef4444; border-color:#fecaca; padding:5px 8px; font-size:0.78rem;" title="Delete PO Record">
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

<!-- ========================================================================= -->
<!-- 1. PO INVOICE DETAILS & PRINT MODAL -->
<!-- ========================================================================= -->
<div id="poDetailsModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.65); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">
    <div class="pos-card" style="width:100%; max-width:780px; max-height:90vh; overflow-y:auto; padding:24px; border-radius:14px; box-shadow:0 25px 60px rgba(0,0,0,0.4); background:#fff; box-sizing:border-box;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid var(--pos-border); padding-bottom:14px; margin-bottom:18px;">
            <div>
                <div style="font-size:1.3rem; font-weight:900; font-family:var(--pos-font-heading); color:var(--pos-black); display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-truck-ramp-box" style="color:var(--pos-red);"></i> Wholesale Purchase Bill &amp; GRN Voucher
                </div>
                <div style="font-size:0.82rem; color:var(--pos-text-sec); margin-top:2px;">Safdar Mobile Store &amp; Electronics — Official Inward Receiving Voucher</div>
            </div>
            <button type="button" onclick="closePoDetailsModal()" style="background:none; border:none; font-size:1.3rem; color:var(--pos-text-muted); cursor:pointer; padding:4px;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div id="poDetailsModalContent">
            <!-- Dynamic content -->
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px; border-top:1px solid var(--pos-border); padding-top:14px; flex-wrap:wrap; gap:10px;">
            <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="closePoDetailsModal()">Close</button>
            <div style="display:flex; gap:8px;">
                <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="printPoThermalSlip()">
                    <i class="fa-solid fa-receipt"></i> Thermal Slip (80mm)
                </button>
                <button type="button" class="pos-btn pos-btn-primary pos-btn-sm" onclick="printPoInvoiceModal()">
                    <i class="fa-solid fa-print"></i> Print Full Voucher (A4)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 2. SETTLE PAYMENT / PAY DUE MODAL -->
<!-- ========================================================================= -->
<div id="settlePaymentModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.65); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">
    <div class="pos-card" style="width:100%; max-width:460px; padding:22px; border-radius:14px; box-shadow:0 25px 60px rgba(0,0,0,0.4); background:#fff; box-sizing:border-box;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid var(--pos-border); padding-bottom:10px; margin-bottom:16px;">
            <h3 class="pos-card-title" style="margin:0; font-size:1.1rem; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-hand-holding-dollar" style="color:var(--pos-gold);"></i> Settle Supplier Due / Installment
            </h3>
            <button type="button" onclick="closeSettlePaymentModal()" style="background:none; border:none; font-size:1.2rem; color:var(--pos-text-muted); cursor:pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="purchases.php">
            <input type="hidden" name="action" value="settle_due">
            <input type="hidden" name="poId" id="settlePoId" value="">

            <div style="background:var(--pos-bg); padding:12px 14px; border-radius:10px; margin-bottom:14px; border:1px solid var(--pos-border);">
                <div style="font-size:0.82rem; color:var(--pos-text-sec);">PO Number: <strong id="settlePoNo" style="color:var(--pos-red);"></strong></div>
                <div style="font-size:0.82rem; color:var(--pos-text-sec); margin-top:2px;">Supplier: <strong id="settleSupplierName" style="color:var(--pos-text);"></strong></div>
                <div style="font-size:0.82rem; color:var(--pos-text-sec); margin-top:2px;">Supplier Bill: <strong id="settleInvoiceNo" style="color:var(--pos-black);"></strong></div>
                <div style="font-size:1rem; font-weight:900; color:#dc2626; margin-top:6px; border-top:1px dashed #cbd5e1; padding-top:6px;">
                    Current Pending Due: <span id="settleCurrentPending">PKR 0</span>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label" style="font-size:0.8rem; font-weight:700;">Payment Amount (PKR) *</label>
                <input type="number" name="paymentAmount" id="settlePaymentAmount" class="form-input" min="1" step="any" required style="font-size:1.15rem; font-weight:900; padding:8px 10px; color:#059669;">
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label" style="font-size:0.8rem; font-weight:700;">Payment Method</label>
                <select name="settlePaymentMethod" class="form-select" style="font-size:0.85rem; padding:8px 10px; font-weight:600;">
                    <option value="Cash">Cash in Hand</option>
                    <option value="Bank Transfer">Bank Transfer / Online</option>
                    <option value="Easypaisa / JazzCash">Easypaisa / JazzCash</option>
                    <option value="Cheque">Cheque</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label" style="font-size:0.8rem; font-weight:700;">Payment Date</label>
                <input type="date" name="settleDate" class="form-input" value="<?php echo date('Y-m-d'); ?>" style="font-size:0.85rem; padding:8px 10px;">
            </div>

            <div class="form-group" style="margin-bottom:18px;">
                <label class="form-label" style="font-size:0.8rem; font-weight:700;">Payment Notes</label>
                <input type="text" name="settleNotes" class="form-input" placeholder="e.g. Settled via bank online transfer" style="font-size:0.85rem; padding:8px 10px;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="closeSettlePaymentModal()">Cancel</button>
                <button type="submit" class="pos-btn pos-btn-success pos-btn-sm" style="padding:8px 18px; font-weight:800;">
                    <i class="fa-solid fa-check"></i> Submit Payment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 3. EDIT PO DETAILS MODAL -->
<!-- ========================================================================= -->
<div id="editPoModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.65); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">
    <div class="pos-card" style="width:100%; max-width:480px; padding:22px; border-radius:14px; box-shadow:0 25px 60px rgba(0,0,0,0.4); background:#fff; box-sizing:border-box;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid var(--pos-border); padding-bottom:10px; margin-bottom:16px;">
            <h3 class="pos-card-title" style="margin:0; font-size:1.1rem; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-pen-to-square" style="color:var(--pos-red);"></i> Edit Purchase Order Details
            </h3>
            <button type="button" onclick="closeEditPoModal()" style="background:none; border:none; font-size:1.2rem; color:var(--pos-text-muted); cursor:pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="purchases.php">
            <input type="hidden" name="action" value="edit_po">
            <input type="hidden" name="poId" id="editPoId" value="">

            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label" style="font-size:0.8rem; font-weight:700;">Supplier Name</label>
                <input type="text" name="editSupplierName" id="editSupplierName" class="form-input" required style="font-size:0.88rem; padding:8px 10px;">
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label" style="font-size:0.8rem; font-weight:700;">Supplier Bill / Invoice #</label>
                <input type="text" name="editInvoiceNo" id="editInvoiceNo" class="form-input" required style="font-size:0.88rem; padding:8px 10px;">
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label" style="font-size:0.8rem; font-weight:700;">Purchase Date</label>
                <input type="date" name="editPurchaseDate" id="editPurchaseDate" class="form-input" style="font-size:0.88rem; padding:8px 10px;">
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label" style="font-size:0.8rem; font-weight:700;">Payment Method</label>
                <select name="editPaymentMethod" id="editPaymentMethod" class="form-select" style="font-size:0.88rem; padding:8px 10px;">
                    <option value="Cash">Cash in Hand</option>
                    <option value="Bank Transfer">Bank Transfer / Online</option>
                    <option value="Easypaisa / JazzCash">Easypaisa / JazzCash</option>
                    <option value="Cheque">Cheque</option>
                    <option value="Credit / Pay Later">Credit / Pay Later</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:18px;">
                <label class="form-label" style="font-size:0.8rem; font-weight:700;">Remarks / Notes</label>
                <input type="text" name="editNotes" id="editNotes" class="form-input" style="font-size:0.88rem; padding:8px 10px;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="closeEditPoModal()">Cancel</button>
                <button type="submit" class="pos-btn pos-btn-primary pos-btn-sm" style="padding:8px 18px; font-weight:800;">
                    <i class="fa-solid fa-check"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT LOGIC: FAST DYNAMIC PRODUCTS, LIVE BALANCES & MODALS -->
<!-- ========================================================================= -->
<script>
// Catalog products & categories from PHP
const catalogProducts = <?php echo json_encode(array_values($products), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const catalogCategories = <?php echo json_encode(array_values($categories), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

let rowCounter = 0;

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    addNewProductRow();
});

// Custom supplier toggle
function onSupplierChange(selectElem) {
    const customWrap = document.getElementById('customSupplierWrapper');
    const customInput = document.getElementById('customSupplierInput');
    if (selectElem.value === '__custom__') {
        customWrap.style.display = 'block';
        customInput.required = true;
        customInput.focus();
    } else {
        customWrap.style.display = 'none';
        customInput.required = false;
    }
}

// Add New Dynamic Product Row
function addNewProductRow() {
    rowCounter++;
    const rowId = `po-row-${rowCounter}`;
    const tbody = document.getElementById('poProductsTbody');

    let optionsHtml = `<option value="">-- Search / Select Product --</option>`;
    catalogProducts.forEach(p => {
        const cost = p.costPrice || p.sellingPrice || p.priceNumeric || 0;
        const sell = p.sellingPrice || p.priceNumeric || (cost * 1.25);
        const sku = p.sku || '';
        const unit = p.unit || p.unitLabel || 'Pcs';
        const cat = p.category || 'accessories';
        optionsHtml += `<option value="${p.id}" data-name="${escapeHtml(p.name)}" data-sku="${escapeHtml(sku)}" data-cost="${cost}" data-sell="${sell}" data-stock="${p.stock || 0}" data-unit="${escapeHtml(unit)}" data-cat="${escapeHtml(cat)}">
            ${escapeHtml(p.name)} [SKU: ${escapeHtml(sku || 'N/A')} | Stock: ${p.stock || 0} ${escapeHtml(unit)}]
        </option>`;
    });
    optionsHtml += `<option value="__custom_prod__" style="color:var(--pos-red); font-weight:800;">+ Type / Add New Product...</option>`;

    let unitsList = ['Pcs', 'Meters', 'Roll', 'Box', 'Set', 'Unit', 'Pkt', 'Pair', 'Job', 'Feet', 'Kg', 'Coil'];
    let unitOptionsHtml = unitsList.map(u => `<option value="${u}">${u}</option>`).join('');

    let catOptionsHtml = catalogCategories.map(c => `<option value="${escapeHtml(c.id || c.name)}">${escapeHtml(c.name)}</option>`).join('');
    if (!catOptionsHtml) {
        catOptionsHtml = `<option value="mobiles">Mobiles</option><option value="accessories">Accessories</option><option value="cctv">CCTV</option><option value="laptops">Laptops</option>`;
    }

    const tr = document.createElement('tr');
    tr.id = rowId;
    tr.className = 'po-item-row';
    tr.innerHTML = `
        <td style="padding:6px 8px;">
            <select class="form-select po-prod-select" onchange="onRowProductChange(this, '${rowId}')" style="width:100%; font-size:0.82rem; padding:6px 8px; font-weight:600;" required>
                ${optionsHtml}
            </select>
            <div class="custom-prod-input-wrap" style="display:none; margin-top:6px; background:#f8fafc; padding:8px; border:1px dashed #cbd5e1; border-radius:6px;">
                <input type="text" class="form-input po-custom-name" placeholder="Type new product name..." style="font-size:0.82rem; padding:5px 8px; width:100%; box-sizing:border-box; margin-bottom:4px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:4px;">
                    <select class="form-select po-custom-cat" style="font-size:0.75rem; padding:3px 6px;">
                        ${catOptionsHtml}
                    </select>
                    <input type="number" class="form-input po-custom-sell" placeholder="Sell Price (PKR)" style="font-size:0.75rem; padding:3px 6px;">
                </div>
            </div>
        </td>
        <td style="padding:6px 8px;">
            <input type="text" class="form-input po-prod-sku" placeholder="SKU / Barcode" style="font-size:0.82rem; padding:6px 8px; width:100%; box-sizing:border-box; font-family:monospace;">
        </td>
        <td style="padding:6px 8px; text-align:center;">
            <select class="form-select po-prod-unit" style="font-size:0.8rem; padding:5px 6px; width:100%; box-sizing:border-box;">
                ${unitOptionsHtml}
            </select>
        </td>
        <td style="padding:6px 8px;">
            <input type="number" class="form-input po-prod-price" min="0" step="any" placeholder="0" oninput="recalculateRow('${rowId}')" style="font-size:0.88rem; font-weight:800; padding:6px 8px; width:100%; box-sizing:border-box; text-align:right; color:#059669;" required>
        </td>
        <td style="padding:6px 8px;">
            <input type="number" class="form-input po-prod-qty" min="0.1" step="any" value="1" oninput="recalculateRow('${rowId}')" style="font-size:0.88rem; font-weight:900; padding:6px 8px; width:100%; box-sizing:border-box; text-align:center;" required>
        </td>
        <td style="padding:6px 8px; text-align:right;">
            <strong class="po-prod-line-total" style="font-size:0.88rem; color:#0f172a; display:block; word-break:break-all;">PKR 0</strong>
        </td>
        <td style="text-align:center; padding:6px 4px;">
            <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="removeProductRow('${rowId}')" style="color:#ef4444; border-color:#fecaca; padding:4px 8px; font-size:0.75rem;" title="Remove row">
                <i class="fa-solid fa-trash"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);
    recalculateBalances();
}

// When a product is selected in a row
function onRowProductChange(selectElem, rowId) {
    const row = document.getElementById(rowId);
    if (!row) return;

    const customWrap = row.querySelector('.custom-prod-input-wrap');
    const customInput = row.querySelector('.po-custom-name');
    const skuInput = row.querySelector('.po-prod-sku');
    const unitSelect = row.querySelector('.po-prod-unit');
    const priceInput = row.querySelector('.po-prod-price');

    if (selectElem.value === '__custom_prod__') {
        customWrap.style.display = 'block';
        customInput.required = true;
        skuInput.value = '';
        priceInput.value = '';
        customInput.focus();
    } else if (selectElem.value) {
        customWrap.style.display = 'none';
        customInput.required = false;
        const selectedOpt = selectElem.options[selectElem.selectedIndex];
        skuInput.value = selectedOpt.getAttribute('data-sku') || '';
        priceInput.value = selectedOpt.getAttribute('data-cost') || '0';
        const unit = selectedOpt.getAttribute('data-unit') || 'Pcs';
        if (unitSelect) unitSelect.value = unit;
    } else {
        customWrap.style.display = 'none';
        customInput.required = false;
        skuInput.value = '';
        priceInput.value = '';
    }

    recalculateRow(rowId);
}

// Recalculate Single Row
function recalculateRow(rowId) {
    const row = document.getElementById(rowId);
    if (!row) return;

    const price = parseFloat(row.querySelector('.po-prod-price').value) || 0;
    const qty = parseFloat(row.querySelector('.po-prod-qty').value) || 0;
    const total = Math.round(price * qty * 100) / 100;

    row.querySelector('.po-prod-line-total').innerText = 'PKR ' + total.toLocaleString();
    recalculateBalances();
}

// Remove Product Row
function removeProductRow(rowId) {
    const tbody = document.getElementById('poProductsTbody');
    const row = document.getElementById(rowId);
    if (row && tbody.children.length > 1) {
        row.remove();
        recalculateBalances();
    } else if (row) {
        alert('Purchase Order must have at least 1 product item.');
    }
}

// Recalculate Grand Totals & Pending Balances
function recalculateBalances() {
    const rows = document.querySelectorAll('.po-item-row');
    let grandTotal = 0;
    const items = [];

    rows.forEach(row => {
        const select = row.querySelector('.po-prod-select');
        const customNameInput = row.querySelector('.po-custom-name');
        const customCatInput = row.querySelector('.po-custom-cat');
        const customSellInput = row.querySelector('.po-custom-sell');
        const skuInput = row.querySelector('.po-prod-sku');
        const unitSelect = row.querySelector('.po-prod-unit');
        const priceInput = row.querySelector('.po-prod-price');
        const qtyInput = row.querySelector('.po-prod-qty');

        let prodId = select.value;
        let prodName = '';
        let category = 'accessories';
        let sellingPrice = 0;

        if (prodId === '__custom_prod__') {
            prodName = customNameInput.value.trim() || 'Custom Product';
            category = customCatInput ? customCatInput.value : 'accessories';
            sellingPrice = customSellInput ? (parseFloat(customSellInput.value) || 0) : 0;
            prodId = 'custom-' + Date.now();
        } else if (prodId) {
            prodName = select.options[select.selectedIndex].getAttribute('data-name') || select.options[select.selectedIndex].text;
            category = select.options[select.selectedIndex].getAttribute('data-cat') || 'accessories';
            sellingPrice = parseFloat(select.options[select.selectedIndex].getAttribute('data-sell')) || 0;
        }

        const sku = skuInput.value.trim();
        const unit = unitSelect ? unitSelect.value : 'Pcs';
        const costPrice = parseFloat(priceInput.value) || 0;
        const qty = parseFloat(qtyInput.value) || 0;
        const lineTotal = Math.round(costPrice * qty * 100) / 100;

        grandTotal += lineTotal;

        if (prodId || prodName) {
            items.push({
                productId: prodId,
                name: prodName,
                sku: sku,
                category: category,
                unit: unit,
                costPrice: costPrice,
                sellingPrice: sellingPrice > 0 ? sellingPrice : (costPrice * 1.25),
                qty: qty,
                total: lineTotal
            });
        }
    });

    grandTotal = Math.round(grandTotal * 100) / 100;

    // Update hidden inputs
    document.getElementById('hiddenTotalAmount').value = grandTotal;
    document.getElementById('itemsJsonInput').value = JSON.stringify(items);
    document.getElementById('displayTotalInvoice').innerText = 'PKR ' + grandTotal.toLocaleString();

    // Paid & Pending Amount Calculation
    const paidInput = document.getElementById('paidAmountInput');
    if (!paidInput.dataset.touched) {
        paidInput.value = grandTotal;
    }

    let paidVal = parseFloat(paidInput.value);
    if (isNaN(paidVal) || paidVal < 0) paidVal = 0;
    if (paidVal > grandTotal) paidVal = grandTotal;

    const pendingVal = Math.max(0, Math.round((grandTotal - paidVal) * 100) / 100);

    document.getElementById('displayPendingAmount').innerText = 'PKR ' + pendingVal.toLocaleString();

    // Status Badge & Help Text
    const badge = document.getElementById('paymentStatusBadge');
    const help = document.getElementById('pendingStatusHelp');

    if (pendingVal <= 0 && grandTotal > 0) {
        badge.className = 'status-badge status-completed';
        badge.innerText = 'PAID IN FULL';
        badge.style.background = '#ecfdf5';
        badge.style.color = '#065f46';
        help.innerText = 'No remaining balance (Fully Paid)';
        help.style.color = '#059669';
    } else if (paidVal > 0) {
        badge.className = 'status-badge';
        badge.innerText = 'PARTIALLY PAID';
        badge.style.background = '#fffbeb';
        badge.style.color = '#b45309';
        help.innerText = 'Remaining Supplier Debt: PKR ' + pendingVal.toLocaleString();
        help.style.color = '#b45309';
    } else {
        badge.className = 'status-badge status-pending';
        badge.innerText = 'UNPAID / CREDIT';
        badge.style.background = '#fef2f2';
        badge.style.color = '#b91c1c';
        help.innerText = 'Full bill pending as supplier credit payable';
        help.style.color = '#dc2626';
    }
}

// Preset Paid Amount Buttons
function setPaymentPreset(type) {
    const paidInput = document.getElementById('paidAmountInput');
    const total = parseFloat(document.getElementById('hiddenTotalAmount').value) || 0;
    paidInput.dataset.touched = 'true';

    if (type === 'full') {
        paidInput.value = total;
    } else if (type === 'half') {
        paidInput.value = Math.round(total / 2);
    } else if (type === 'zero') {
        paidInput.value = 0;
    }

    recalculateBalances();
}

// Form validation
document.getElementById('purchaseOrderForm').addEventListener('submit', function(e) {
    recalculateBalances();
    const items = JSON.parse(document.getElementById('itemsJsonInput').value || '[]');
    if (items.length === 0 || !items[0].name) {
        e.preventDefault();
        alert('Please add at least one product with valid quantity and purchase cost.');
        return false;
    }
    const tot = parseFloat(document.getElementById('hiddenTotalAmount').value) || 0;
    if (tot <= 0) {
        e.preventDefault();
        alert('Total invoice amount must be greater than 0 PKR.');
        return false;
    }
});

// Search & Filter PO Table
function filterPoTable() {
    const q = document.getElementById('poSearchInput').value.toLowerCase().trim();
    const status = document.getElementById('poStatusFilter').value;
    const supplier = document.getElementById('poSupplierFilter').value;
    const rows = document.querySelectorAll('#poHistoryTable tbody tr[data-po-row]');

    let visibleCount = 0;
    rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        const rStatus = r.getAttribute('data-status') || '';
        const rSupplier = r.getAttribute('data-supplier') || '';

        const matchesQuery = !q || text.includes(q);
        const matchesStatus = status === 'all' || rStatus === status;
        const matchesSupplier = supplier === 'all' || rSupplier === supplier;

        if (matchesQuery && matchesStatus && matchesSupplier) {
            r.style.display = '';
            visibleCount++;
        } else {
            r.style.display = 'none';
        }
    });

    const countEl = document.getElementById('poVisibleCount');
    if (countEl) countEl.innerText = visibleCount;
}

// -----------------------------------------------------------------------------
// PO DETAILS MODAL
// -----------------------------------------------------------------------------
let activePoDetails = null;

function openPoDetailsModal(po) {
    activePoDetails = po;
    const modal = document.getElementById('poDetailsModal');
    const content = document.getElementById('poDetailsModalContent');
    const items = po.items || [];

    let itemsHtml = '';
    items.forEach((item, idx) => {
        const qty = item.qty || 1;
        const unit = item.unit || 'Pcs';
        const price = item.costPrice || 0;
        const tot = item.total || (qty * price);
        itemsHtml += `
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:9px 10px; font-weight:700;">${idx + 1}. ${escapeHtml(item.name || 'Product')}</td>
                <td style="padding:9px 10px; font-family:monospace; color:#64748b; font-size:0.8rem;">${escapeHtml(item.sku || 'N/A')}</td>
                <td style="padding:9px 10px; text-align:right; font-weight:700; color:#059669;">PKR ${price.toLocaleString()}</td>
                <td style="padding:9px 10px; text-align:center; font-weight:800;">${qty} ${escapeHtml(unit)}</td>
                <td style="padding:9px 10px; text-align:right; font-weight:900; color:#0f172a;">PKR ${tot.toLocaleString()}</td>
            </tr>
        `;
    });

    const totalAmt = parseFloat(po.totalAmount) || 0;
    const paidAmt = parseFloat(po.paidAmount) || 0;
    const pendingAmt = parseFloat(po.pendingAmount) || 0;

    // Payment History breakdown
    let historyHtml = '';
    if (po.paymentHistory && po.paymentHistory.length > 0) {
        historyHtml = `
            <div style="margin-top:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
                <h5 style="margin:0 0 6px 0; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:800;">Payment Installments History</h5>
                ${po.paymentHistory.map(h => `
                    <div style="display:flex; justify-content:space-between; font-size:0.78rem; padding:3px 0; border-bottom:1px dashed #e2e8f0;">
                        <span><i class="fa-solid fa-check" style="color:#10b981;"></i> ${escapeHtml(h.date || '')} (${escapeHtml(h.method || 'Cash')}) - <em>${escapeHtml(h.notes || '')}</em></span>
                        <strong style="color:#059669;">PKR ${Number(h.amount || 0).toLocaleString()}</strong>
                    </div>
                `).join('')}
            </div>
        `;
    }

    content.innerHTML = `
        <div id="printablePoVoucher" style="font-family:'Inter', sans-serif;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; background:#f8fafc; padding:14px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:16px;">
                <div>
                    <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">Supplier &amp; Vendor</div>
                    <div style="font-size:1.05rem; font-weight:900; color:#0f172a; margin-top:2px;">${escapeHtml(po.supplierName)}</div>
                    ${po.supplierPhone ? `<div style="font-size:0.8rem; color:#059669; font-weight:700; margin-top:2px;"><i class="fa-solid fa-phone"></i> ${escapeHtml(po.supplierPhone)}</div>` : ''}
                    <div style="font-size:0.8rem; color:#475569; margin-top:2px;">Payment Method: <strong>${escapeHtml(po.paymentMethod || 'Cash')}</strong></div>
                    ${po.notes ? `<div style="font-size:0.75rem; color:#64748b; margin-top:4px;">Notes: <em>${escapeHtml(po.notes)}</em></div>` : ''}
                </div>
                <div style="text-align:right;">
                    <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">Receiving Reference</div>
                    <div style="font-size:1.1rem; font-weight:900; color:var(--pos-red); font-family:var(--pos-font-heading);">${escapeHtml(po.poNo)}</div>
                    <div style="font-size:0.85rem; font-weight:700; color:#0f172a; margin-top:2px;">Supplier Bill: <span style="font-family:monospace; background:#e2e8f0; padding:2px 8px; border-radius:4px;">${escapeHtml(po.invoiceNo || 'N/A')}</span></div>
                    <div style="font-size:0.8rem; color:#64748b; margin-top:2px;">Date: ${po.date || 'N/A'}</div>
                </div>
            </div>

            <h4 style="margin:0 0 8px 0; font-size:0.92rem; font-weight:900; color:#1e293b;">Purchased Items Breakdown</h4>
            <table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; font-size:0.85rem; margin-bottom:16px;">
                <thead>
                    <tr style="background:#f1f5f9; color:#475569; font-weight:800; text-align:left; border-bottom:1.5px solid #cbd5e1;">
                        <th style="padding:9px 10px;">Product Name</th>
                        <th style="padding:9px 10px;">SKU / Code</th>
                        <th style="padding:9px 10px; text-align:right;">Unit Cost</th>
                        <th style="padding:9px 10px; text-align:center;">Qty</th>
                        <th style="padding:9px 10px; text-align:right;">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml || '<tr><td colspan="5" style="text-align:center; padding:12px;">No item details</td></tr>'}
                </tbody>
            </table>

            <div style="display:flex; justify-content:flex-end;">
                <div style="width:300px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:0.88rem; font-weight:700;">
                        <span>Total Inward Bill:</span>
                        <span>PKR ${totalAmt.toLocaleString()}</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:0.88rem; font-weight:700; color:#059669;">
                        <span>Amount Paid:</span>
                        <span>PKR ${paidAmt.toLocaleString()}</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; border-top:2px solid #cbd5e1; padding-top:6px; font-size:1rem; font-weight:900; color:${pendingAmt > 0 ? '#dc2626' : '#10b981'};">
                        <span>Pending Balance:</span>
                        <span>PKR ${pendingAmt.toLocaleString()}</span>
                    </div>
                </div>
            </div>

            ${historyHtml}
        </div>
    `;

    modal.style.display = 'flex';
}

function closePoDetailsModal() {
    document.getElementById('poDetailsModal').style.display = 'none';
}

// Print Full A4 Voucher
function printPoInvoiceModal() {
    const printContent = document.getElementById('poDetailsModalContent').innerHTML;
    const printWindow = window.open('', '', 'height=750,width=880');
    printWindow.document.write(`
        <html>
            <head>
                <title>Stock Purchase Voucher - Safdar Mobile Store</title>
                <style>
                    body { font-family: 'Inter', sans-serif; padding: 25px; color: #111; font-size: 13px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                    th, td { border: 1px solid #ddd; padding: 8px 12px; }
                    th { background-color: #f4f4f4; text-align: left; font-weight: bold; }
                </style>
            </head>
            <body>
                <div style="text-align:center; margin-bottom:20px; border-bottom:2px solid #D71920; padding-bottom:12px;">
                    <h2 style="margin:0; color:#D71920; font-size:22px; font-weight:900;">SAFDAR MOBILE STORE &amp; ELECTRONICS</h2>
                    <p style="margin:4px 0; font-size:13px; color:#555;">Main Bazar, Hangu, Khyber Pakhtunkhwa | Phone: 0333 9688007</p>
                    <h3 style="margin:8px 0 0 0; font-size:16px; color:#111;">OFFICIAL STOCK PURCHASE &amp; INWARD RECEIVING VOUCHER</h3>
                </div>
                ${printContent}
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 300);
}

// Print 80mm Thermal Slip
function printPoThermalSlip() {
    if (!activePoDetails) return;
    const po = activePoDetails;
    const items = po.items || [];
    
    let itemsRows = '';
    items.forEach((it, idx) => {
        itemsRows += `
            <tr>
                <td style="padding:3px 0; font-size:11px;">${idx + 1}. ${escapeHtml(it.name)}</td>
                <td style="padding:3px 0; font-size:11px; text-align:center;">${it.qty}</td>
                <td style="padding:3px 0; font-size:11px; text-align:right;">${Number(it.total || 0).toLocaleString()}</td>
            </tr>
        `;
    });

    const slipWindow = window.open('', '', 'height=600,width=400');
    slipWindow.document.write(`
        <html>
            <head>
                <title>PO Thermal Slip - ${po.poNo}</title>
                <style>
                    body { font-family: monospace; width: 280px; margin: 0 auto; padding: 10px; font-size: 12px; }
                    .center { text-align: center; }
                    .bold { font-weight: bold; }
                    .dashed { border-top: 1px dashed #000; margin: 6px 0; }
                    table { width: 100%; border-collapse: collapse; }
                </style>
            </head>
            <body>
                <div class="center bold" style="font-size:14px;">SAFDAR MOBILE STORE</div>
                <div class="center" style="font-size:10px;">STOCK INWARD SLIP</div>
                <div class="dashed"></div>
                <div>PO #: <strong>${po.poNo}</strong></div>
                <div>Supplier: <strong>${po.supplierName}</strong></div>
                <div>Bill #: <strong>${po.invoiceNo || 'N/A'}</strong></div>
                <div>Date: ${po.date || ''}</div>
                <div class="dashed"></div>
                <table>
                    <thead>
                        <tr style="border-bottom:1px solid #000;">
                            <th style="text-align:left; font-size:11px;">Item</th>
                            <th style="text-align:center; font-size:11px;">Qty</th>
                            <th style="text-align:right; font-size:11px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>${itemsRows}</tbody>
                </table>
                <div class="dashed"></div>
                <div style="display:flex; justify-content:space-between;"><span>Total Bill:</span><strong>PKR ${Number(po.totalAmount || 0).toLocaleString()}</strong></div>
                <div style="display:flex; justify-content:space-between;"><span>Amount Paid:</span><strong>PKR ${Number(po.paidAmount || 0).toLocaleString()}</strong></div>
                <div style="display:flex; justify-content:space-between;"><span>Pending Due:</span><strong>PKR ${Number(po.pendingAmount || 0).toLocaleString()}</strong></div>
                <div class="dashed"></div>
                <div class="center" style="font-size:10px;">Printed on: ${new Date().toLocaleString()}</div>
            </body>
        </html>
    `);
    slipWindow.document.close();
    slipWindow.focus();
    setTimeout(() => {
        slipWindow.print();
        slipWindow.close();
    }, 300);
}

// -----------------------------------------------------------------------------
// SETTLE PAYMENT MODAL
// -----------------------------------------------------------------------------
function openSettlePaymentModal(po) {
    document.getElementById('settlePoId').value = po.id;
    document.getElementById('settlePoNo').innerText = po.poNo;
    document.getElementById('settleSupplierName').innerText = po.supplierName;
    document.getElementById('settleInvoiceNo').innerText = po.invoiceNo || 'N/A';
    document.getElementById('settleCurrentPending').innerText = 'PKR ' + Number(po.pendingAmount || 0).toLocaleString();
    document.getElementById('settlePaymentAmount').value = po.pendingAmount || 0;
    document.getElementById('settlePaymentModal').style.display = 'flex';
}

function closeSettlePaymentModal() {
    document.getElementById('settlePaymentModal').style.display = 'none';
}

// -----------------------------------------------------------------------------
// EDIT PO MODAL
// -----------------------------------------------------------------------------
function openEditPoModal(po) {
    document.getElementById('editPoId').value = po.id;
    document.getElementById('editSupplierName').value = po.supplierName || '';
    document.getElementById('editInvoiceNo').value = po.invoiceNo || '';
    document.getElementById('editPurchaseDate').value = po.date || '';
    document.getElementById('editPaymentMethod').value = po.paymentMethod || 'Cash';
    document.getElementById('editNotes').value = po.notes || '';
    document.getElementById('editPoModal').style.display = 'flex';
}

function closeEditPoModal() {
    document.getElementById('editPoModal').style.display = 'none';
}

// -----------------------------------------------------------------------------
// EXPORT TO CSV
// -----------------------------------------------------------------------------
function exportPurchasesCsv() {
    const rows = <?php echo json_encode($purchases); ?>;
    if (!rows || rows.length === 0) {
        alert('No purchase records to export.');
        return;
    }

    let csvContent = 'data:text/csv;charset=utf-8,';
    csvContent += 'PO Number,Supplier Name,Supplier Phone,Invoice Number,Date,Payment Method,Total Amount,Paid Amount,Pending Amount,Status,Notes\n';

    rows.forEach(r => {
        const poNo = '"' + (r.poNo || '').replace(/"/g, '""') + '"';
        const sup = '"' + (r.supplierName || '').replace(/"/g, '""') + '"';
        const phone = '"' + (r.supplierPhone || '').replace(/"/g, '""') + '"';
        const inv = '"' + (r.invoiceNo || '').replace(/"/g, '""') + '"';
        const dt = '"' + (r.date || '').replace(/"/g, '""') + '"';
        const method = '"' + (r.paymentMethod || '').replace(/"/g, '""') + '"';
        const tot = r.totalAmount || 0;
        const paid = r.paidAmount || 0;
        const pending = r.pendingAmount || 0;
        const status = '"' + (r.paymentStatus || '').replace(/"/g, '""') + '"';
        const notes = '"' + (r.notes || '').replace(/"/g, '""') + '"';

        csvContent += [poNo, sup, phone, inv, dt, method, tot, paid, pending, status, notes].join(',') + '\n';
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `SMS_Purchases_Report_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
