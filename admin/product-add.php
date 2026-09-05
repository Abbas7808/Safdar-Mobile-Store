<?php
$currentPage = 'product-add';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$products = get_json_file('products') ?? [];
$categories = get_json_file('categories') ?? [];
$brands = get_json_file('brands') ?? [];
$reviews = get_json_file('reviews') ?? [];
$cctvCatalog = get_json_file('cctv_catalog') ?? [];

$editId = $_GET['id'] ?? '';
$editingProduct = null;

if ($editId) {
    foreach ($products as $p) {
        if ($p['id'] === $editId) {
            $editingProduct = $p;
            break;
        }
    }
}

// Fetch reviews for this product if editing
$productReviews = [];
$avgRating = 5.0;
if ($editingProduct) {
    $productReviews = array_values(array_filter($reviews, function($r) use ($editingProduct) {
        return ($r['productId'] ?? '') === $editingProduct['id'];
    }));
    if (count($productReviews) > 0) {
        $avgRating = round(array_sum(array_column($productReviews, 'rating')) / count($productReviews), 1);
    }
}

$message = '';
$messageType = '';

// Handle review deletion by admin
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_review') {
    $delRevId = $_POST['review_id'] ?? '';
    if ($delRevId) {
        $reviews = array_values(array_filter($reviews, function($r) use ($delRevId) {
            return ($r['id'] ?? '') !== $delRevId;
        }));
        save_json_file('reviews', $reviews);
        $message = 'Customer review removed successfully.';
        $messageType = 'success';
        if ($editingProduct) {
            $productReviews = array_values(array_filter($reviews, function($r) use ($editingProduct) {
                return ($r['productId'] ?? '') === $editingProduct['id'];
            }));
        }
    }
}

function resolveAndDownloadImageUrl($url) {
    $uploadDir = __DIR__ . '/../uploads/products/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 7,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ]);

    $response = curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $contentType = strtolower(curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
    curl_close($ch);

    if (!$response) return null;

    if (strpos($contentType, 'image/') !== false) {
        $ext = 'jpg';
        if (strpos($contentType, 'png') !== false) $ext = 'png';
        elseif (strpos($contentType, 'webp') !== false) $ext = 'webp';

        $fileName = 'prod_img_' . time() . '_' . rand(100, 999) . '.' . $ext;
        if (@file_put_contents($uploadDir . $fileName, $response)) {
            return ['localUrl' => 'uploads/products/' . $fileName];
        }
    }

    $html = $response;
    $targetImageUrl = '';

    if (strpos($finalUrl, 'imgurl=') !== false || strpos($url, 'imgurl=') !== false) {
        if (preg_match('/imgurl=([^&]+)/i', $finalUrl, $m) || preg_match('/imgurl=([^&]+)/i', $url, $m)) {
            $targetImageUrl = urldecode($m[1]);
        }
    }

    if (empty($targetImageUrl)) {
        if (preg_match('/<meta[^>]+property=[\'"]og:image[\'"][^>]+content=[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
            $targetImageUrl = html_entity_decode($m[1]);
        } elseif (preg_match('/<meta[^>]+content=[\'"]([^\'"]+)[\'"][^>]+property=[\'"]og:image[\'"]/i', $html, $m)) {
            $targetImageUrl = html_entity_decode($m[1]);
        } elseif (preg_match('/<meta[^>]+name=[\'"]twitter:image[\'"][^>]+content=[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
            $targetImageUrl = html_entity_decode($m[1]);
        }
    }

    if (!empty($targetImageUrl) && filter_var($targetImageUrl, FILTER_VALIDATE_URL)) {
        $ch2 = curl_init();
        curl_setopt_array($ch2, [
            CURLOPT_URL => $targetImageUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        $imgData = curl_exec($ch2);
        $imgContentType = strtolower(curl_getinfo($ch2, CURLINFO_CONTENT_TYPE));
        curl_close($ch2);

        if ($imgData && strlen($imgData) > 500) {
            $ext = 'jpg';
            if (strpos($imgContentType, 'png') !== false) $ext = 'png';
            elseif (strpos($imgContentType, 'webp') !== false) $ext = 'webp';

            $fileName = 'prod_img_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (@file_put_contents($uploadDir . $fileName, $imgData)) {
                return ['localUrl' => 'uploads/products/' . $fileName];
            }
        }
    }

    return null;
}

// Handle Product Save / Update
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'delete_review')) {
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? 'mobiles';
    $subCategory = trim($_POST['subCategory'] ?? $_POST['sub_category'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    
    // Auto-align brand with subcategory if generic/empty
    if (empty($brand)) {
        $brand = !empty($subCategory) ? $subCategory : 'generic';
    }

    $sellingPrice = floatval($_POST['sellingPrice'] ?? 0);
    $costPrice = floatval($_POST['costPrice'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $minStock = intval($_POST['minStock'] ?? 2);
    $sku = trim($_POST['sku'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $imageUrl = trim($_POST['image'] ?? '');
    $specsRaw = trim($_POST['specs'] ?? '');

    // Online Purchase Offer & Discount Fields
    $hasOnlineOffer = isset($_POST['hasOnlineOffer']) && $_POST['hasOnlineOffer'] === '1';
    $offerBadge = trim($_POST['offerBadge'] ?? '10% OFF ONLINE');
    $discountType = $_POST['discountType'] ?? 'percentage';
    $discountValue = floatval($_POST['discountValue'] ?? 10);
    $onlinePrice = floatval($_POST['onlinePrice'] ?? 0);
    $offerTagline = trim($_POST['offerTagline'] ?? 'Pay 100% advance online & get special discount + priority shipping!');
    $offerExpiry = trim($_POST['offerExpiry'] ?? 'Limited Time Online Offer');

    // Auto calculate online price if not manually specified
    if ($hasOnlineOffer) {
        if ($discountType === 'percentage' && $discountValue > 0) {
            $calcOnline = max(0, $sellingPrice - ($sellingPrice * ($discountValue / 100)));
            if ($onlinePrice <= 0 || abs($onlinePrice - $calcOnline) > ($sellingPrice * 0.5)) {
                $onlinePrice = $calcOnline;
            }
        } elseif ($discountType === 'fixed' && $discountValue > 0) {
            $calcOnline = max(0, $sellingPrice - $discountValue);
            if ($onlinePrice <= 0) {
                $onlinePrice = $calcOnline;
            }
        } elseif ($onlinePrice <= 0) {
            $onlinePrice = $sellingPrice;
        }
    } else {
        $onlinePrice = $sellingPrice;
    }

    $specs = array_filter(array_map('trim', explode("\n", $specsRaw)));

    // File Upload Handling for Featured Image
    $imagePath = $editingProduct['image'] ?? $imageUrl;

    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/products/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmpPath = $_FILES['image_file']['tmp_name'];
        $fileName = $_FILES['image_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = 'prod_' . time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $imagePath = 'uploads/products/' . $newFileName;
            }
        }
    } elseif (!empty($imageUrl)) {
        // Try auto-resolving & downloading Google Share / Google Search / Remote URL
        $resolved = resolveAndDownloadImageUrl($imageUrl);
        if ($resolved && !empty($resolved['localUrl'])) {
            $imagePath = $resolved['localUrl'];
        } else {
            $imagePath = $imageUrl;
        }
    }

    if (empty($imagePath)) {
        $imagePath = 'assets/images/logo.jpg';
    }

    $unit = strtolower(trim($_POST['unit'] ?? 'pcs'));
    if (!in_array($unit, ['pcs', 'piece', 'meter', 'yard', 'foot', 'roll', 'box', 'set', 'kg', 'gram'])) {
        $unit = 'pcs';
    }
    // Only CCTV/cable/network categories allow length-based units (meter, yard, foot, gaz)
    $catLower = strtolower($category);
    $isCableCategory = in_array($catLower, ['cctv', 'security', 'cameras', 'cctv_accessories', 'cables', 'wires', 'network_accessories', 'networkaccessories', 'network accessories']);
    if (!$isCableCategory && in_array($unit, ['meter', 'yard', 'foot', 'gaz'])) {
        $unit = 'pcs';
    }
    $unitLabels = [
        'pcs' => 'Piece',
        'piece' => 'Piece',
        'meter' => 'Meter',
        'yard' => 'Yard',
        'foot' => 'Foot',
        'roll' => 'Roll',
        'box' => 'Box',
        'set' => 'Set',
        'kg' => 'KG'
    ];
    $unitLabel = $unitLabels[$unit] ?? ucfirst($unit);
    $priceRangeFormatted = ($unit !== 'pcs' && $unit !== 'piece') 
        ? ('PKR ' . number_format($sellingPrice) . ' / ' . $unitLabel) 
        : ('PKR ' . number_format($sellingPrice));

    if (!empty($name)) {
        if ($editingProduct) {
            foreach ($products as &$p) {
                if ($p['id'] === $editingProduct['id']) {
                    $p['name'] = $name;
                    $p['category'] = $category;
                    $p['categoryId'] = $category;
                    $p['subCategory'] = $subCategory;
                    $p['sub_category'] = $subCategory;
                    $p['brand'] = $brand;
                    $p['unit'] = $unit;
                    $p['unitLabel'] = $unitLabel;
                    $p['sellingPrice'] = $sellingPrice;
                    $p['priceNumeric'] = $sellingPrice;
                    $p['costPrice'] = $costPrice;
                    $p['priceRange'] = $priceRangeFormatted;
                    $p['stock'] = $stock;
                    $p['minStock'] = $minStock;
                    $p['sku'] = $sku;
                    $p['barcode'] = $barcode;
                    $p['image'] = $imagePath;
                    $p['hasOnlineOffer'] = $hasOnlineOffer;
                    $p['offerBadge'] = $offerBadge;
                    $p['discountType'] = $discountType;
                    $p['discountValue'] = $discountValue;
                    $p['onlinePrice'] = $onlinePrice;
                    $p['offerTagline'] = $offerTagline;
                    $p['offerExpiry'] = $offerExpiry;
                    $p['specs'] = array_values($specs);
                    $editingProduct = $p;
                    break;
                }
            }
            save_json_file('products', $products);
            $message = 'Product updated successfully!';
            $messageType = 'success';
        } else {
            $newProduct = [
                'id' => 'prod-' . time(),
                'name' => $name,
                'category' => $category,
                'categoryId' => $category,
                'subCategory' => $subCategory,
                'sub_category' => $subCategory,
                'brand' => $brand,
                'unit' => $unit,
                'unitLabel' => $unitLabel,
                'sellingPrice' => $sellingPrice,
                'priceNumeric' => $sellingPrice,
                'costPrice' => $costPrice,
                'priceRange' => $priceRangeFormatted,
                'stock' => $stock,
                'minStock' => $minStock,
                'sku' => $sku ?: 'SKU-' . rand(1000, 9999),
                'barcode' => $barcode ?: '' . rand(1000000000, 9999999999),
                'image' => $imagePath,
                'status' => 'active',
                'badge' => $hasOnlineOffer ? $offerBadge : 'NEW LISTED',
                'createdAt' => date('c'),
                'isNewArrival' => isset($_POST['isNewArrival']),
                'hasOnlineOffer' => $hasOnlineOffer,
                'offerBadge' => $offerBadge,
                'discountType' => $discountType,
                'discountValue' => $discountValue,
                'onlinePrice' => $onlinePrice,
                'offerTagline' => $offerTagline,
                'offerExpiry' => $offerExpiry,
                'specs' => array_values($specs)
            ];
            array_unshift($products, $newProduct);
            save_json_file('products', $products);
            $message = 'Product created successfully with Online Offer settings!';
            $messageType = 'success';
        }
    } else {
        $message = 'Product name is required!';
        $messageType = 'danger';
    }
}
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content" style="max-width:100%; box-sizing:border-box;">
        <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="font-size:1.5rem; margin:0;"><i class="fa-solid fa-plus" style="color:var(--pos-red); margin-right:8px;"></i> <?php echo $editingProduct ? 'Edit Product & Offers' : 'Add New Product'; ?></h1>
                <p class="page-header-sub" style="margin-top:2px;"><?php echo $editingProduct ? 'Update product pricing, online discounts, customer reviews, and inventory' : 'Create new product entry, configure online purchase discount offer, and upload image'; ?></p>
            </div>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <button type="button" class="pos-btn" onclick="openSmartImporterModal()" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:#ffffff; border:none; box-shadow:0 4px 14px rgba(16,185,129,0.35); font-weight:800; font-size:0.85rem; padding:8px 14px; border-radius:8px; cursor:pointer;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> ⚡ Import Bill / Excel / Receipt
                </button>
                <a href="products.php" class="pos-btn pos-btn-outline pos-btn-sm">
                    <i class="fa-solid fa-arrow-left"></i> Back to Products
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="login-error" style="background:<?php echo $messageType === 'success' ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)'; ?>; border:1px solid <?php echo $messageType === 'success' ? '#a7f3d0' : '#fecaca'; ?>; color:<?php echo $messageType === 'success' ? '#059669' : '#dc2626'; ?>; margin-bottom:20px; padding:12px 16px; border-radius:8px; font-weight:700;">
                <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation'; ?>"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Smart Batch Importer Quick Access Card -->
        <div style="background:linear-gradient(135deg, #064e3b 0%, #047857 100%); border:1.5px solid #34d399; border-radius:12px; padding:12px 16px; margin-bottom:16px; color:#fff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; box-shadow:0 4px 15px rgba(4,120,87,0.25);">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="background:rgba(52,211,153,0.25); width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#a7f3d0; font-size:1.35rem;">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <div>
                    <div style="font-weight:900; font-size:0.95rem; color:#fff; display:flex; align-items:center; gap:8px;">
                        <span>Time-Saving Batch Importer</span>
                        <span style="background:#f59e0b; color:#000; font-size:0.65rem; font-weight:900; padding:1px 6px; border-radius:4px; text-transform:uppercase;">AI OCR &amp; Excel</span>
                    </div>
                    <div style="font-size:0.75rem; color:#d1fae5; margin-top:2px;">
                        Upload supplier bills, receipt photos, PDF invoices, Excel spreadsheets, or paste WhatsApp wholesale lists to add multiple products in seconds!
                    </div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <button type="button" class="pos-btn" onclick="openSmartImporterModal('excel')" style="background:#ffffff; color:#065f46; font-weight:800; font-size:0.78rem; padding:6px 12px; border-radius:6px; border:none; cursor:pointer;">
                    <i class="fa-solid fa-file-excel" style="color:#10b981;"></i> Upload Excel / CSV
                </button>
                <button type="button" class="pos-btn" onclick="openSmartImporterModal('receipt')" style="background:#10b981; color:#ffffff; font-weight:800; font-size:0.78rem; padding:6px 12px; border-radius:6px; border:1px solid #34d399; cursor:pointer;">
                    <i class="fa-solid fa-camera"></i> Scan Bill / PDF Receipt
                </button>
                <button type="button" class="pos-btn" onclick="openSmartImporterModal('text')" style="background:rgba(255,255,255,0.15); color:#ffffff; font-weight:800; font-size:0.78rem; padding:6px 12px; border-radius:6px; border:1px solid rgba(255,255,255,0.3); cursor:pointer;">
                    <i class="fa-brands fa-whatsapp"></i> Paste Text / WhatsApp
                </button>
            </div>
        </div>

        <!-- CCTV Catalog & Dealer Quick Select Banner (Only shown when CCTV category is selected) -->
        <?php 
            $initialCat = $editingProduct['category'] ?? $editingProduct['categoryId'] ?? 'mobiles';
            $isInitialCctv = in_array(strtolower($initialCat), ['cctv', 'security', 'cameras']);
        ?>
        <div id="cctvTopQuickBanner" style="<?php echo $isInitialCctv ? 'display:flex;' : 'display:none;'; ?> background:linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); border:1.5px solid #6366f1; border-radius:12px; padding:12px 16px; margin-bottom:18px; color:#fff; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; box-shadow:0 4px 15px rgba(49,46,129,0.25);">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="background:rgba(99,102,241,0.25); width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#a5b4fc; font-size:1.3rem;">
                    <i class="fa-solid fa-video"></i>
                </div>
                <div>
                    <div style="font-weight:900; font-size:0.95rem; color:#fff; display:flex; align-items:center; gap:8px;">
                        <span>CCTV Hardware Catalog &amp; Dealer Presets</span>
                        <span style="background:#10b981; color:#fff; font-size:0.65rem; font-weight:800; padding:1px 6px; border-radius:4px;">New japan electronic</span>
                    </div>
                    <div style="font-size:0.75rem; color:#c7d2fe; margin-top:2px;">Select from 65+ official CCTV items (UNV, Dahua, Hikvision, Switches, Cables, Hard Disks, Adapters) to auto-fill form</div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <select id="cctvCatalogPresetSelect" class="form-select" style="font-size:0.82rem; font-weight:700; background:#0f172a; color:#f8fafc; border-color:#6366f1; min-width:320px; max-width:400px; padding:6px 12px; border-radius:8px;" onchange="loadCctvPresetIntoForm(this.value)">
                    <option value="">-- Quick Load CCTV Item (65 Official Items) --</option>
                    <?php 
                    $groupedCatalog = [];
                    foreach ($cctvCatalog as $idx => $ci) {
                        $grp = $ci['subCategory'] ?? 'Other';
                        $groupedCatalog[$grp][] = ['idx' => $idx, 'item' => $ci];
                    }
                    foreach ($groupedCatalog as $grpName => $items):
                    ?>
                        <optgroup label="📁 <?php echo htmlspecialchars($grpName); ?>">
                            <?php foreach ($items as $entry): ?>
                                <option value="<?php echo $entry['idx']; ?>">
                                    [<?php echo htmlspecialchars($entry['item']['brand']); ?>] <?php echo htmlspecialchars($entry['item']['name']); ?> (PKR <?php echo number_format($entry['item']['defaultPrice']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <form method="POST" action="" enctype="multipart/form-data" id="productForm">
            <!-- 1. GENERAL PRODUCT DETAILS -->
            <div class="pos-card" style="margin-bottom:20px;">
                <h3 class="pos-card-title" style="margin-bottom:16px; font-size:1.1rem; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-box" style="color:var(--pos-red);"></i> Basic Product Information
                </h3>

                <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px;">
                    <div class="form-group form-full" style="grid-column: 1 / -1;">
                        <label class="form-label">Product Full Name *</label>
                        <input type="text" name="name" id="productNameInput" class="form-input" placeholder="e.g. Samsung Galaxy S24 Ultra / Fast Charger 65W / Cat6 Network Cable 305m" value="<?php echo htmlspecialchars($editingProduct['name'] ?? ''); ?>" required oninput="checkNameForCableKeywords(this.value)">
                    </div>

                    <div class="form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <label class="form-label" style="margin-bottom:0;">Category *</label>
                            <a href="categories.php" target="_blank" style="font-size:0.75rem; color:#4f46e5; font-weight:700; text-decoration:none;">
                                <i class="fa-solid fa-plus"></i> Add Category
                            </a>
                        </div>
                        <select name="category" class="form-select" id="productCategorySelect" required onchange="onCategoryChange(this.value)">
                            <?php 
                            $currentCat = $editingProduct['category'] ?? $editingProduct['categoryId'] ?? 'mobiles';
                            $foundInList = false;
                            foreach ($categories as $cat): 
                                $catId = $cat['id'] ?? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $cat['name']));
                                $catName = $cat['name'];
                                if ($currentCat === $catId) $foundInList = true;
                            ?>
                                <option value="<?php echo htmlspecialchars($catId); ?>" <?php echo ($currentCat === $catId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($catName); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if (!$foundInList && !empty($currentCat)): ?>
                                <option value="<?php echo htmlspecialchars($currentCat); ?>" selected>
                                    <?php echo htmlspecialchars(ucfirst($currentCat)); ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Sub-Category & Website Filter Tab Selection -->
                    <div class="form-group" id="subCategoryGroup" style="background:#f8fafc; padding:10px 12px; border-radius:10px; border:1.5px solid #cbd5e1;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <label class="form-label" style="margin-bottom:0; color:#0f172a; font-weight:800;">
                                <i class="fa-solid fa-tags" style="color:var(--pos-red);"></i> Sub-Category / Filter Tab *
                            </label>
                            <span style="font-size:0.7rem; color:#64748b; font-weight:600;">(Controls website tabs)</span>
                        </div>
                        
                        <select name="subCategory" class="form-select" id="productSubCategorySelect" style="font-weight:700;" onchange="handleSubCategoryChange(this.value)">
                            <!-- Populated dynamically based on category selection -->
                        </select>

                        <!-- Dynamic 1-Click Filter Selector Pills -->
                        <div id="subCategoryPillsContainer" style="display:flex; flex-wrap:wrap; gap:5px; margin-top:8px;">
                            <!-- Populated dynamically based on category selection -->
                        </div>
                    </div>

                    <!-- Interactive CCTV Attributes & Pick-and-Drop Builder (Shows when category is CCTV) -->
                    <div id="cctvInteractiveBuilder" style="grid-column: 1 / -1; background:#f0fdf4; border:1.5px solid #86efac; border-radius:12px; padding:14px; display:none; margin-top:2px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                            <div style="font-weight:900; color:#166534; font-size:0.88rem; display:flex; align-items:center; gap:8px;">
                                <i class="fa-solid fa-wand-magic-sparkles" style="color:#059669;"></i>
                                <span>CCTV Pick &amp; Drop Attribute Builder (UNV / Hikvision / Dahua &amp; Megapixel Selection)</span>
                            </div>
                            <span style="font-size:0.7rem; background:#dcfce7; color:#15803d; font-weight:800; padding:2px 8px; border-radius:10px; border:1px solid #bbf7d0;">
                                1-Click Auto-Fill
                            </span>
                        </div>

                        <!-- 1. Brand Selection -->
                        <div style="margin-bottom:10px;">
                            <div style="font-size:0.72rem; font-weight:800; color:#1e293b; text-transform:uppercase; margin-bottom:4px;">
                                1. Select Brand:
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-brand" onclick="setCctvBrand('UNV', this)" style="background:#0284c7; color:#fff; font-weight:800; padding:4px 10px; font-size:0.75rem; border-radius:6px;">⭐ UNV (Uniview)</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-brand" onclick="setCctvBrand('Hikvision', this)" style="background:#fff; color:#dc2626; border:1px solid #f87171; font-weight:800; padding:4px 10px; font-size:0.75rem; border-radius:6px;">Hikvision</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-brand" onclick="setCctvBrand('Dahua', this)" style="background:#fff; color:#2563eb; border:1px solid #93c5fd; font-weight:800; padding:4px 10px; font-size:0.75rem; border-radius:6px;">Dahua</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-brand" onclick="setCctvBrand('D-Link', this)" style="background:#fff; color:#475569; border:1px solid #cbd5e1; font-weight:800; padding:4px 10px; font-size:0.75rem; border-radius:6px;">D-Link</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-brand" onclick="setCctvBrand('Tenda', this)" style="background:#fff; color:#ea580c; border:1px solid #fdba74; font-weight:800; padding:4px 10px; font-size:0.75rem; border-radius:6px;">Tenda</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-brand" onclick="setCctvBrand('Seagate', this)" style="background:#fff; color:#059669; border:1px solid #86efac; font-weight:800; padding:4px 10px; font-size:0.75rem; border-radius:6px;">Seagate</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-brand" onclick="setCctvBrand('Dell', this)" style="background:#fff; color:#475569; border:1px solid #cbd5e1; font-weight:800; padding:4px 10px; font-size:0.75rem; border-radius:6px;">Dell</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-brand" onclick="setCctvBrand('Topsat', this)" style="background:#fff; color:#475569; border:1px solid #cbd5e1; font-weight:800; padding:4px 10px; font-size:0.75rem; border-radius:6px;">Topsat Cat6</button>
                            </div>
                        </div>

                        <!-- 2. Megapixel / Resolution Selection (As User Requested!) -->
                        <div style="margin-bottom:10px;" id="cctvMegapixelSection">
                            <div style="font-size:0.72rem; font-weight:800; color:#1e293b; text-transform:uppercase; margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                                <i class="fa-solid fa-camera" style="color:#059669;"></i>
                                <span>2. Select Camera Resolution / Megapixel (MP):</span>
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-mp" onclick="setCctvMp('2 MP (1080P Full HD)', this)" style="background:#fff; color:#059669; border:1.5px solid #10b981; font-weight:900; padding:4px 10px; font-size:0.75rem; border-radius:6px;">🟢 2 MP (1080P Full HD)</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-mp" onclick="setCctvMp('3 MP', this)" style="background:#fff; color:#475569; border:1px solid #cbd5e1; font-weight:700; padding:4px 10px; font-size:0.75rem; border-radius:6px;">3 MP</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-mp" onclick="setCctvMp('4 MP (2K QHD)', this)" style="background:#fff; color:#2563eb; border:1.5px solid #60a5fa; font-weight:900; padding:4px 10px; font-size:0.75rem; border-radius:6px;">🔵 4 MP (2K QHD)</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-mp" onclick="setCctvMp('5 MP (Super HD)', this)" style="background:#fff; color:#7c3aed; border:1.5px solid #a78bfa; font-weight:900; padding:4px 10px; font-size:0.75rem; border-radius:6px;">🟣 5 MP (Super HD)</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-mp" onclick="setCctvMp('6 MP', this)" style="background:#fff; color:#475569; border:1px solid #cbd5e1; font-weight:700; padding:4px 10px; font-size:0.75rem; border-radius:6px;">6 MP</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-mp" onclick="setCctvMp('8 MP (4K Ultra HD)', this)" style="background:#fff; color:#dc2626; border:1.5px solid #f87171; font-weight:900; padding:4px 10px; font-size:0.75rem; border-radius:6px;">🔴 8 MP (4K Ultra HD)</button>
                            </div>
                        </div>

                        <!-- 3. Camera / Hardware Type Selection -->
                        <div style="margin-bottom:10px;">
                            <div style="font-size:0.72rem; font-weight:800; color:#1e293b; text-transform:uppercase; margin-bottom:4px;">
                                3. Select Hardware / Camera Type:
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('Analog Camera', this)" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">Analog Camera</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('Dome Camera', this)" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">Dome Camera</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('Bullet Camera', this)" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">Bullet Camera</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('Color Night Camera', this)" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">Color Night Vision</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('Dual Light Camera', this)" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">Dual Light with MIC</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('IP Camera', this)" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">IP Camera</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('DVR 4-Port', this)" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">DVR 4-Port</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('DVR 8-Port', this)" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">DVR 8-Port</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('NVR 4-Port', this)" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">NVR 4-Port</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('NVR 8-Port 4K', this)" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">NVR 8-Port 4K</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('PoE Switch 4-Port', this)" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">PoE Switch 4-Port</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('PoE Switch 8-Port', this)" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">PoE Switch 8-Port</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('Hard Disk 500GB', this)" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">Hard Disk 500GB</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('Cat6 Network Cable (Per Meter)', this, 'meter')" style="background:#fff; color:#059669; border:1.5px solid #10b981; font-weight:800; padding:3px 8px; font-size:0.72rem; border-radius:6px;">📏 Cat6 Cable (Meter)</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('Cat6 Network Cable (Per Yard)', this, 'yard')" style="background:#fff; color:#2563eb; border:1.5px solid #60a5fa; font-weight:800; padding:3px 8px; font-size:0.72rem; border-radius:6px;">📐 Cat6 Cable (Yard)</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('Cat6 Cable Roll (305m)', this, 'roll')" style="background:#fff; color:#d97706; border:1.5px solid #f59e0b; font-weight:800; padding:3px 8px; font-size:0.72rem; border-radius:6px;">📦 Cat6 Roll (305m)</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('RG59 Coaxial Cable (Per Meter)', this, 'meter')" style="background:#fff; color:#059669; border:1.5px solid #10b981; font-weight:800; padding:3px 8px; font-size:0.72rem; border-radius:6px;">📏 RG59 Coaxial (Meter)</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('RG59 Coaxial Cable (Per Yard)', this, 'yard')" style="background:#fff; color:#2563eb; border:1.5px solid #60a5fa; font-weight:800; padding:3px 8px; font-size:0.72rem; border-radius:6px;">📐 RG59 Coaxial (Yard)</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('2-Core Power Cable (Per Meter)', this, 'meter')" style="background:#fff; color:#059669; border:1.5px solid #10b981; font-weight:800; padding:3px 8px; font-size:0.72rem; border-radius:6px;">📏 2-Core Cable (Meter)</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('2-Core Power Cable (Per Yard)', this, 'yard')" style="background:#fff; color:#2563eb; border:1.5px solid #60a5fa; font-weight:800; padding:3px 8px; font-size:0.72rem; border-radius:6px;">📐 2-Core Cable (Yard)</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('BNC Connectors', this, 'pcs')" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">BNC Connectors</button>
                                <button type="button" class="pos-btn pos-btn-sm cctv-pick-type" onclick="setCctvType('12V Power Supply', this, 'pcs')" style="background:#fff; color:#334155; border:1px solid #cbd5e1; font-weight:700; padding:3px 8px; font-size:0.72rem; border-radius:6px;">12V Power Supply</button>
                            </div>
                        </div>

                        <!-- Matching Official Catalog Suggestions -->
                        <div id="cctvMatchingSuggestions" style="background:#ffffff; border:1px solid #86efac; border-radius:8px; padding:8px 10px; margin-top:8px;">
                            <div style="font-size:0.72rem; font-weight:800; color:#15803d; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
                                <span><i class="fa-solid fa-list-check"></i> Matching Official Catalog Items (Dealer: New japan electronic):</span>
                                <span style="font-size:0.68rem; color:#166534; font-weight:700;">Click item to load</span>
                            </div>
                            <div id="cctvCatalogMatchesList" style="display:flex; flex-wrap:wrap; gap:6px;">
                                <!-- Dynamically populated based on chosen brand, megapixel, and type -->
                            </div>
                        </div>
                    </div>

                    <!-- Unit of Measurement / Sale Type (Piece, Meter, Yard, Roll, Foot) - Shown only for CCTV & Cables -->
                    <div class="form-group" id="unitMeasurementGroup" style="grid-column: 1 / -1; background:linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); padding:12px 14px; border-radius:12px; border:1.5px solid #86efac; box-shadow:0 2px 8px rgba(16,185,129,0.06); display:none;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:6px;">
                            <label class="form-label" style="margin-bottom:0; font-weight:900; color:#065f46; font-size:0.88rem;" id="productUnitMainLabel">
                                <i class="fa-solid fa-ruler-combined" style="color:#059669;"></i> Unit of Measurement &amp; Sale Basis *
                            </label>
                            <span id="unitDetectedBadge" style="display:none; background:#dcfce7; color:#15803d; font-size:0.72rem; font-weight:800; padding:2px 8px; border-radius:6px; border:1px solid #86efac;">
                                <i class="fa-solid fa-bolt"></i> Auto-Detected Cable / Length Measurement
                            </span>
                        </div>

                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <div style="flex:1; min-width:240px;">
                                <select name="unit" id="productUnitSelect" class="form-select" style="font-weight:800; font-size:0.88rem; color:#065f46; border-color:#86efac; background:#fff;" onchange="onProductUnitChange(this.value)">
                                    <?php 
                                    $savedUnit = strtolower($editingProduct['unit'] ?? 'pcs');
                                    ?>
                                    <option value="pcs" <?php echo ($savedUnit === 'pcs' || $savedUnit === 'piece') ? 'selected' : ''; ?>>🏷️ Per Piece / Item (Pcs) — Default for Phones, Chargers, Cameras</option>
                                    <option value="meter" <?php echo ($savedUnit === 'meter' || $savedUnit === 'm') ? 'selected' : ''; ?>>📏 Per Meter (m) — CCTV Cables, Cat6, Coaxial RG59, Wire Length</option>
                                    <option value="yard" <?php echo ($savedUnit === 'yard' || $savedUnit === 'yd' || $savedUnit === 'gaz') ? 'selected' : ''; ?>>📐 Per Yard / Gaz (Yards) — Coaxial &amp; Network Cable Measurement</option>
                                    <option value="roll" <?php echo ($savedUnit === 'roll') ? 'selected' : ''; ?>>📦 Per Roll / Bundle — (e.g. 100m / 305m Cable Full Roll)</option>
                                    <option value="foot" <?php echo ($savedUnit === 'foot' || $savedUnit === 'ft') ? 'selected' : ''; ?>>🦶 Per Foot (ft) — Wire / Cable</option>
                                    <option value="box" <?php echo ($savedUnit === 'box') ? 'selected' : ''; ?>>📦 Per Box / Pack</option>
                                    <option value="set" <?php echo ($savedUnit === 'set') ? 'selected' : ''; ?>>🎛️ Per Complete Set / Kit</option>
                                </select>
                            </div>

                            <!-- 1-Click Fast Unit Selectors -->
                            <div style="display:flex; flex-wrap:wrap; gap:6px;" id="unitPillButtons">
                                <button type="button" class="pos-btn pos-btn-sm unit-quick-pill" data-unit="pcs" onclick="setProductUnitQuick('pcs', this)" style="padding:4px 10px; font-size:0.75rem; font-weight:800; border-radius:6px; background:#fff; color:#065f46; border:1.5px solid #a7f3d0;">🏷️ Per Piece</button>
                                <button type="button" class="pos-btn pos-btn-sm unit-quick-pill" data-unit="meter" onclick="setProductUnitQuick('meter', this)" style="padding:4px 10px; font-size:0.75rem; font-weight:800; border-radius:6px; background:#fff; color:#065f46; border:1.5px solid #a7f3d0;">📏 Per Meter (m)</button>
                                <button type="button" class="pos-btn pos-btn-sm unit-quick-pill" data-unit="yard" onclick="setProductUnitQuick('yard', this)" style="padding:4px 10px; font-size:0.75rem; font-weight:800; border-radius:6px; background:#fff; color:#065f46; border:1.5px solid #a7f3d0;">📐 Per Yard (Gaz)</button>
                                <button type="button" class="pos-btn pos-btn-sm unit-quick-pill" data-unit="roll" onclick="setProductUnitQuick('roll', this)" style="padding:4px 10px; font-size:0.75rem; font-weight:800; border-radius:6px; background:#fff; color:#065f46; border:1.5px solid #a7f3d0;">📦 Per Roll (305m)</button>
                                <button type="button" class="pos-btn pos-btn-sm unit-quick-pill" data-unit="foot" onclick="setProductUnitQuick('foot', this)" style="padding:4px 10px; font-size:0.75rem; font-weight:800; border-radius:6px; background:#fff; color:#065f46; border:1.5px solid #a7f3d0;">🦶 Per Foot</button>
                            </div>
                        </div>

                        <div style="font-size:0.72rem; color:#047857; font-weight:600; margin-top:6px;" id="unitHelpText">
                            For CCTV cables (Cat6, RG59 Coaxial, Power Cable), select <strong>Per Yard</strong> or <strong>Per Meter</strong>. The regular price, wholesale cost, and stock will automatically reflect your measurement basis!
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Brand / Manufacturer</label>
                        <input type="text" name="brand" id="productBrandInput" class="form-input" placeholder="e.g. Samsung / Apple / Faster / Audionic / Ronin" value="<?php echo htmlspecialchars($editingProduct['brand'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" id="lblSellingPrice">Retail Regular Price (PKR) *</label>
                        <input type="number" step="any" name="sellingPrice" id="sellingPriceInput" class="form-input" placeholder="385000" oninput="recalculateOnlineDiscount()" value="<?php echo floatval($editingProduct['sellingPrice'] ?? $editingProduct['priceNumeric'] ?? 0); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" id="lblCostPrice">Wholesale Cost Price (PKR)</label>
                        <input type="number" step="any" name="costPrice" id="costPriceInput" class="form-input" placeholder="360000" value="<?php echo floatval($editingProduct['costPrice'] ?? 0); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" id="lblStock">Initial Stock Quantity *</label>
                        <input type="number" step="any" name="stock" id="stockInput" class="form-input" placeholder="5" value="<?php echo floatval($editingProduct['stock'] ?? 5); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" id="lblMinStock">Min Low Stock Limit</label>
                        <input type="number" step="any" name="minStock" id="minStockInput" class="form-input" placeholder="2" value="<?php echo floatval($editingProduct['minStock'] ?? 2); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">SKU Code</label>
                        <input type="text" name="sku" class="form-input" placeholder="SAM-S24U" value="<?php echo htmlspecialchars($editingProduct['sku'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Barcode (Numeric)</label>
                        <input type="text" name="barcode" class="form-input" placeholder="8806095123456" value="<?php echo htmlspecialchars($editingProduct['barcode'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- 2. SPECIAL ONLINE PURCHASE OFFER & DISCOUNT SECTION -->
            <div class="pos-card" style="margin-bottom:20px; border:2px solid rgba(244,196,48,0.5); background:linear-gradient(180deg, #ffffff 0%, #fffdf5 100%);">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border-bottom:1px solid #fde68a; padding-bottom:14px; margin-bottom:16px;">
                    <div>
                        <h3 class="pos-card-title" style="margin:0; font-size:1.15rem; color:#92400e; display:flex; align-items:center; gap:8px;">
                            <i class="fa-solid fa-tags" style="color:var(--pos-red);"></i> Online Purchase Discount & Special Offer
                        </h3>
                        <p style="margin:3px 0 0 0; font-size:0.82rem; color:#78350f;">Create promotional discount for customers ordering online. This offer will be prominently highlighted under the product card and during checkout!</p>
                    </div>

                    <label style="display:inline-flex; align-items:center; gap:8px; background:#fff; padding:6px 14px; border-radius:20px; border:1.5px solid #f59e0b; cursor:pointer; font-weight:800; font-size:0.85rem; color:#92400e;">
                        <input type="checkbox" name="hasOnlineOffer" id="hasOnlineOfferCheckbox" value="1" <?php echo (!empty($editingProduct['hasOnlineOffer'])) ? 'checked' : ''; ?> onchange="toggleOnlineOfferUI(this.checked)" style="width:18px; height:18px; accent-color:var(--pos-red);">
                        <span>Enable Online Purchase Offer</span>
                    </label>
                </div>

                <div id="onlineOfferFieldsWrapper" style="display:<?php echo (!empty($editingProduct['hasOnlineOffer'])) ? 'block' : 'none'; ?>;">
                    <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:16px;">
                        <!-- Offer Badge Text -->
                        <div class="form-group">
                            <label class="form-label">Offer Badge Title *</label>
                            <input type="text" name="offerBadge" id="offerBadgeInput" class="form-input" placeholder="e.g. 10% OFF ONLINE / SPECIAL OFFER" value="<?php echo htmlspecialchars($editingProduct['offerBadge'] ?? '10% OFF ONLINE'); ?>" oninput="updateOfferPreview()">
                            <span style="font-size:0.72rem; color:#6b7280; margin-top:2px;">Displayed as high-visibility badge on the product image</span>
                        </div>

                        <!-- Discount Type -->
                        <div class="form-group">
                            <label class="form-label">Discount Calculation Type</label>
                            <select name="discountType" id="discountTypeSelect" class="form-select" onchange="recalculateOnlineDiscount()">
                                <option value="percentage" <?php echo ($editingProduct['discountType'] ?? '') === 'percentage' ? 'selected' : ''; ?>>Percentage Discount (% Off)</option>
                                <option value="fixed" <?php echo ($editingProduct['discountType'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Flat Amount Discount (PKR Off)</option>
                                <option value="custom" <?php echo ($editingProduct['discountType'] ?? '') === 'custom' ? 'selected' : ''; ?>>Direct Special Online Price (PKR)</option>
                            </select>
                        </div>

                        <!-- Discount Value -->
                        <div class="form-group" id="discountValueGroup">
                            <label class="form-label" id="discountValueLabel">Discount Rate (%) *</label>
                            <input type="number" name="discountValue" id="discountValueInput" class="form-input" placeholder="10" min="0" step="any" value="<?php echo floatval($editingProduct['discountValue'] ?? 10); ?>" oninput="recalculateOnlineDiscount()">
                        </div>

                        <!-- Resulting Online Price -->
                        <div class="form-group">
                            <label class="form-label" style="color:#059669; font-weight:800;">
                                <i class="fa-solid fa-bolt"></i> Discounted Online Price (PKR) *
                            </label>
                            <input type="number" name="onlinePrice" id="onlinePriceInput" class="form-input" placeholder="346500" min="0" step="any" value="<?php echo floatval($editingProduct['onlinePrice'] ?? 0); ?>" oninput="onManualOnlinePriceChange()" style="font-weight:800; font-size:1.05rem; color:#059669; border-color:#10b981;">
                        </div>
                    </div>

                    <!-- Offer Tagline & Expiry -->
                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:14px; margin-bottom:16px;">
                        <div class="form-group">
                            <label class="form-label">Offer Promotion Note / Tagline (Displayed under product)</label>
                            <input type="text" name="offerTagline" id="offerTaglineInput" class="form-input" placeholder="e.g. Instant 10% Discount on 100% advance online payment via Easypaisa / JazzCash / Bank!" value="<?php echo htmlspecialchars($editingProduct['offerTagline'] ?? 'Pay 100% advance online & get special discount + priority shipping!'); ?>" oninput="updateOfferPreview()">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Validity / Timing Tag</label>
                            <input type="text" name="offerExpiry" id="offerExpiryInput" class="form-input" placeholder="e.g. Limited Time Online Offer" value="<?php echo htmlspecialchars($editingProduct['offerExpiry'] ?? 'Limited Time Online Offer'); ?>">
                        </div>
                    </div>

                    <!-- LIVE STOREFRONT OFFER PREVIEW BOX -->
                    <div style="background:#fff; border:1.5px dashed #f59e0b; border-radius:10px; padding:14px 18px; margin-top:10px;">
                        <div style="font-size:0.75rem; font-weight:800; color:#b45309; text-transform:uppercase; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-eye"></i> Storefront Customer View Preview:
                        </div>
                        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                            <div>
                                <span id="previewOfferBadge" style="background:#dc2626; color:#fff; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:4px; text-transform:uppercase;">
                                    10% OFF ONLINE
                                </span>
                                <div style="margin-top:6px; font-size:0.88rem; color:#475569;">
                                    Regular Retail: <del id="previewRegularPrice" style="color:#94a3b8; font-weight:600;">PKR 385,000</del>
                                    <span style="margin-left:8px; font-size:1.15rem; font-weight:900; color:#0f172a;" id="previewOnlinePrice">PKR 346,500</span>
                                    <span id="previewSavingTag" style="background:#ecfdf5; color:#065f46; font-size:0.72rem; font-weight:800; padding:2px 6px; border-radius:4px; border:1px solid #a7f3d0; margin-left:6px;">
                                        SAVE PKR 38,500
                                    </span>
                                </div>
                            </div>
                            <div id="previewOfferBox" style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:8px 12px; font-size:0.8rem; color:#92400e; max-width:380px;">
                                <i class="fa-solid fa-gift" style="color:var(--pos-red); margin-right:4px;"></i>
                                <span id="previewTaglineText">Pay 100% advance online & get special discount + priority shipping!</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. FEATURED IMAGE UPLOAD & SPECS -->
            <div class="pos-card" style="margin-bottom:20px;">
                <h3 class="pos-card-title" style="margin-bottom:16px; font-size:1.1rem; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-image" style="color:var(--pos-gold);"></i> Product Images & Specifications
                </h3>

                <div class="form-grid">
                    <!-- Featured Image Upload -->
                    <div class="form-group form-full" style="background:var(--pos-bg); padding:16px; border-radius:10px; border:2px dashed var(--pos-border);">
                        <label class="form-label" style="font-size:0.9rem; font-weight:800; color:var(--pos-red);">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Upload Product Featured Image (Computer / Phone)
                        </label>
                        <input type="file" name="image_file" accept="image/*" class="form-input" style="padding:8px; background:#fff;" onchange="handleLocalImageUploadPreview(this)">
                        <span style="font-size:0.75rem; color:#6b7280; margin-top:4px; display:block;">
                            Supported formats: JPG, PNG, WEBP, GIF.
                        </span>
                    </div>

                    <!-- Direct Image URL with Live Preview & Google Helper -->
                    <div class="form-group form-full" style="background:#f8fafc; padding:14px; border-radius:10px; border:1px solid #cbd5e1;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:8px;">
                            <label class="form-label" style="margin-bottom:0; font-weight:800; color:#0f172a;">
                                <i class="fa-solid fa-link" style="color:#4f46e5;"></i> Product Image Web URL / Google Photo Link
                            </label>
                            <button type="button" class="pos-btn pos-btn-sm" onclick="autoFindProductImage()" style="font-size:0.72rem; padding:3px 8px; background:#e0e7ff; color:#4338ca; border:1px solid #c7d2fe; font-weight:800;">
                                <i class="fa-solid fa-magnifying-glass"></i> 🔍 Auto-Find Photo
                            </button>
                        </div>
                        <input type="text" name="image" id="productImageUrlInput" class="form-input" placeholder="Paste direct image link: https://.../photo.jpg or Google Image link" value="<?php echo htmlspecialchars($editingProduct['image'] ?? ''); ?>" oninput="onProductImageUrlChange(this.value)">
                        
                        <!-- Live Image Preview Card -->
                        <div id="productLiveImagePreviewContainer" style="margin-top:10px; display:flex; align-items:center; gap:12px; background:#ffffff; padding:10px 14px; border-radius:8px; border:1px solid #e2e8f0;">
                            <img id="productLivePreviewImg" src="<?php 
                                $curImg = $editingProduct['image'] ?? '';
                                if (!empty($curImg)) {
                                    echo (strpos($curImg, 'http') === 0) ? htmlspecialchars($curImg) : '../' . htmlspecialchars($curImg);
                                } else {
                                    echo '../assets/images/logo.jpg';
                                }
                            ?>" alt="Live Image Preview" style="width:70px; height:70px; object-fit:contain; border-radius:8px; border:1.5px solid #cbd5e1; background:#f8fafc;" onerror="onLivePreviewImageError(this)">
                            
                            <div style="flex:1;">
                                <div id="liveImageStatusBadge" style="font-size:0.75rem; font-weight:800; color:#059669; display:flex; align-items:center; gap:6px;">
                                    <i class="fa-solid fa-circle-check"></i> <span>Ready: Image preview active</span>
                                </div>
                                <div style="font-size:0.73rem; color:#64748b; margin-top:2px;" id="liveImageHelpText">
                                    💡 <strong>Tip for Google Images:</strong> Right-click on the image on Google and choose <em>"Copy Image Address"</em> (not the browser search page link).
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group form-full">
                        <label class="form-label">Key Specifications (One per line)</label>
                        <textarea name="specs" class="form-textarea" rows="3" placeholder="12GB RAM | 256GB&#10;200MP Quad Camera&#10;5000 mAh Battery"><?php echo htmlspecialchars(implode("\n", $editingProduct['specs'] ?? [])); ?></textarea>
                    </div>
                </div>

                <div class="form-actions" style="margin-top:16px; border-top:1px solid var(--pos-border); padding-top:16px;">
                    <a href="products.php" class="pos-btn pos-btn-outline">Cancel</a>
                    <button type="submit" class="pos-btn pos-btn-primary pos-btn-lg">
                        <i class="fa-solid fa-check"></i> <?php echo $editingProduct ? 'Update Product & Save Offers' : 'Publish Product with Offers'; ?>
                    </button>
                </div>
            </div>
        </form>

        <!-- 4. CUSTOMER REVIEWS FOR THIS PRODUCT SECTION (WHEN EDITING) -->
        <?php if ($editingProduct): ?>
            <div class="pos-card" style="margin-bottom:28px;">
                <div class="pos-card-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--pos-border); padding-bottom:12px; margin-bottom:16px;">
                    <div>
                        <h3 class="pos-card-title" style="margin:0; font-size:1.15rem; display:flex; align-items:center; gap:8px;">
                            <i class="fa-solid fa-star" style="color:var(--pos-gold);"></i> Customer Reviews & Ratings
                        </h3>
                        <p style="margin:2px 0 0 0; font-size:0.8rem; color:var(--pos-text-sec);">Reviews submitted by customers on the online store for this product</p>
                    </div>

                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:1.1rem; font-weight:900; color:var(--pos-gold-dark);">
                            <i class="fa-solid fa-star"></i> <?php echo $avgRating; ?> / 5.0
                        </span>
                        <span class="status-badge status-completed" style="font-size:0.75rem;">
                            <?php echo count($productReviews); ?> Customer Reviews
                        </span>
                    </div>
                </div>

                <?php if (empty($productReviews)): ?>
                    <div style="text-align:center; padding:30px; color:var(--pos-text-sec); background:var(--pos-bg); border-radius:8px;">
                        <i class="fa-regular fa-comment-dots" style="font-size:2rem; color:#cbd5e1; margin-bottom:8px; display:block;"></i>
                        No customer reviews submitted yet for this product. Customers can submit reviews on the storefront.
                    </div>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php foreach ($productReviews as $rev): ?>
                            <div style="background:var(--pos-bg); border:1px solid var(--pos-border); border-radius:8px; padding:14px; display:flex; justify-content:space-between; align-items:flex-start; gap:16px;">
                                <div style="flex:1;">
                                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
                                        <strong style="color:var(--pos-text); font-size:0.95rem;"><?php echo htmlspecialchars($rev['customerName']); ?></strong>
                                        <span style="font-size:0.75rem; color:var(--pos-text-sec);"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($rev['customerCity'] ?? 'Pakistan'); ?></span>
                                        <span style="font-size:0.72rem; color:#059669; font-weight:700; background:#ecfdf5; padding:1px 6px; border-radius:4px;"><i class="fa-solid fa-circle-check"></i> Verified Buyer</span>
                                    </div>
                                    <div style="color:#eab308; font-size:0.8rem; margin-bottom:6px;">
                                        <?php 
                                            $rVal = intval($rev['rating'] ?? 5);
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= $rVal ? '<i class="fa-solid fa-star"></i> ' : '<i class="fa-regular fa-star"></i> ';
                                            }
                                        ?>
                                        <span style="color:var(--pos-text-sec); font-size:0.75rem; margin-left:6px;"><?php echo date('M d, Y', strtotime($rev['date'] ?? $rev['createdAt'])); ?></span>
                                    </div>
                                    <p style="margin:0; font-size:0.88rem; color:var(--pos-text); line-height:1.4;">
                                        "<?php echo htmlspecialchars($rev['comment']); ?>"
                                    </p>
                                </div>

                                <form method="POST" action="" onsubmit="return confirm('Delete this customer review?');">
                                    <input type="hidden" name="action" value="delete_review">
                                    <input type="hidden" name="review_id" value="<?php echo htmlspecialchars($rev['id']); ?>">
                                    <button type="submit" class="pos-btn pos-btn-outline pos-btn-sm" style="color:#ef4444; border-color:#fecaca;" title="Remove Review">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
let resolveImageDebounceTimer = null;

function onProductImageUrlChange(val) {
    val = (val || '').trim();
    const previewImg = document.getElementById('productLivePreviewImg');
    const badge = document.getElementById('liveImageStatusBadge');
    const help = document.getElementById('liveImageHelpText');
    const urlInput = document.getElementById('productImageUrlInput');

    if (!val) {
        if (previewImg) previewImg.src = '../assets/images/logo.jpg';
        if (badge) {
            badge.style.color = '#64748b';
            badge.innerHTML = '<i class="fa-solid fa-circle-info"></i> <span>No image URL specified</span>';
        }
        return;
    }

    clearTimeout(resolveImageDebounceTimer);

    // 1. Instant 0.001s Client-Side extraction if URL contains imgurl=
    if (val.includes('imgurl=')) {
        const match = val.match(/imgurl=([^&]+)/i);
        if (match && match[1]) {
            const cleanUrl = decodeURIComponent(match[1]);
            if (urlInput) urlInput.value = cleanUrl;
            if (previewImg) previewImg.src = cleanUrl;
            if (badge) {
                badge.style.color = '#059669';
                badge.innerHTML = '<i class="fa-solid fa-circle-check"></i> <span>Instant: Clean photo extracted &amp; loaded!</span>';
            }
            if (help) {
                help.innerHTML = 'Direct Image: <span style="word-break:break-all;">' + cleanUrl.substring(0, 70) + '...</span>';
            }
            return;
        }
    }

    // 2. If Google share redirect link or webpage URL (e.g. share.google, goo.gl, etc.)
    if (val.startsWith('http') && (val.includes('share.google') || val.includes('goo.gl') || val.includes('google.com') || !val.match(/\.(jpg|jpeg|png|webp|gif)($|\?)/i))) {
        if (badge) {
            badge.style.color = '#2563eb';
            badge.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>⚡ Fast extracting photo from Google (0.3s)...</span>';
        }
        if (help) {
            help.innerHTML = '<span style="color:#2563eb;">Resolving redirect &amp; loading picture...</span>';
        }

        resolveImageDebounceTimer = setTimeout(() => {
            const apiUrl = '../backend/resolve_image_url.php';
            fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url: val })
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.status === 'success' && data.data) {
                    const resolved = data.data.localUrl || data.data.imageUrl;
                    if (urlInput) urlInput.value = resolved;
                    if (previewImg) {
                        previewImg.src = (resolved.startsWith('http') || resolved.startsWith('data:')) ? resolved : '../' + resolved;
                    }
                    if (badge) {
                        badge.style.color = '#059669';
                        badge.innerHTML = '<i class="fa-solid fa-circle-check"></i> <span>Photo ready &amp; saved!</span>';
                    }
                    if (help) {
                        help.innerHTML = 'Image Ready: <strong>' + resolved.substring(0, 60) + '...</strong>';
                    }
                    if (window.showToast) {
                        window.showToast('success', 'Extracted & saved product photo!');
                    }
                } else {
                    if (badge) {
                        badge.style.color = '#dc2626';
                        badge.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <span>Could not auto-extract image from this link</span>';
                    }
                    if (help) {
                        help.innerHTML = '<span style="color:#dc2626; font-weight:700;">Please click "Auto-Find Photo"</span> or right-click the image on Google and click "Copy Image Address".';
                    }
                }
            })
            .catch(err => {
                console.error("Resolve error:", err);
                if (badge) {
                    badge.style.color = '#dc2626';
                    badge.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> <span>Connection timeout. Please use Auto-Find Photo.</span>';
                }
            });
        }, 150);
        return;
    }

    if (previewImg) {
        let srcUrl = val;
        if (!srcUrl.startsWith('http') && !srcUrl.startsWith('data:') && !srcUrl.startsWith('../') && !srcUrl.startsWith('uploads/') && !srcUrl.startsWith('assets/')) {
            srcUrl = '../' + srcUrl;
        } else if (srcUrl.startsWith('uploads/') || srcUrl.startsWith('assets/')) {
            srcUrl = '../' + srcUrl;
        }
        previewImg.src = srcUrl;
    }

    if (badge) {
        badge.style.color = '#059669';
        badge.innerHTML = '<i class="fa-solid fa-circle-check"></i> <span>Photo link valid &amp; loaded!</span>';
    }
}

function onLivePreviewImageError(img) {
    const badge = document.getElementById('liveImageStatusBadge');
    const help = document.getElementById('liveImageHelpText');
    if (badge) {
        badge.style.color = '#dc2626';
        badge.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> <span>Image failed to load (Check link or upload file)</span>';
    }
    if (help) {
        help.innerHTML = '<span style="color:#dc2626; font-weight:700;">Unable to load this URL directly.</span> Tip: Right-click the image on Google and click <em>"Save image as"</em>, then use the <strong>Upload File</strong> box above!';
    }
    img.src = '../assets/images/logo.jpg';
}

function handleLocalImageUploadPreview(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('productLivePreviewImg');
            const badge = document.getElementById('liveImageStatusBadge');
            const help = document.getElementById('liveImageHelpText');
            if (previewImg) previewImg.src = e.target.result;
            if (badge) {
                badge.style.color = '#059669';
                badge.innerHTML = '<i class="fa-solid fa-circle-check"></i> <span>Local image selected &amp; ready to upload!</span>';
            }
            if (help) {
                help.innerHTML = 'File: <strong>' + input.files[0].name + '</strong> (' + Math.round(input.files[0].size / 1024) + ' KB)';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function autoFindProductImage() {
    const name = (document.getElementById('productNameInput')?.value || '').trim();
    if (!name) {
        alert('Please enter the Product Full Name first.');
        document.getElementById('productNameInput')?.focus();
        return;
    }

    const n = name.toLowerCase();
    let foundUrl = '';

    if (n.includes('redmi note 13') || n.includes('note 13 pro')) {
        foundUrl = 'assets/images/redmi_note_13_pro.jpg';
    } else if (n.includes('s24') || n.includes('s23') || n.includes('galaxy') || n.includes('samsung')) {
        foundUrl = 'assets/images/mob_sam_flagship.png';
    } else if (n.includes('charger') || n.includes('adapter') || n.includes('65w')) {
        foundUrl = 'assets/images/fast_charger_65w.png';
    } else if (n.includes('airbud') || n.includes('earbud') || n.includes('tws') || n.includes('headphone')) {
        foundUrl = 'assets/images/tws_airbuds_pro.png';
    } else if (n.includes('powerbank') || n.includes('power bank')) {
        foundUrl = 'assets/images/magnetic_powerbank.png';
    } else if (n.includes('smartwatch') || n.includes('watch')) {
        foundUrl = 'assets/images/smartwatch_ultra.png';
    } else if (n.includes('cable') || n.includes('type-c') || n.includes('data cable')) {
        foundUrl = 'assets/images/fast_data_cable.png';
    } else if (n.includes('solar') || n.includes('4g camera')) {
        foundUrl = 'assets/images/cctv_solar_4g_camera.png';
    } else if (n.includes('hikvision') || n.includes('bullet')) {
        foundUrl = 'assets/images/cctv_hikvision_bullet.png';
    } else if (n.includes('dahua') || n.includes('ptz') || n.includes('dome')) {
        foundUrl = 'assets/images/cctv_dahua_ptz_dome.png';
    } else if (n.includes('dvr') || n.includes('nvr') || n.includes('recorder')) {
        foundUrl = 'assets/images/cctv_dvr_recorder_8ch.png';
    } else {
        foundUrl = 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=600&auto=format&fit=crop&q=80';
    }

    const urlInput = document.getElementById('productImageUrlInput');
    if (urlInput) {
        urlInput.value = foundUrl;
        onProductImageUrlChange(foundUrl);
    }
    if (window.showToast) window.showToast('success', 'Assigned HD product photo for ' + name);
}

function toggleOnlineOfferUI(isChecked) {
    const wrap = document.getElementById('onlineOfferFieldsWrapper');
    if (wrap) {
        wrap.style.display = isChecked ? 'block' : 'none';
    }
    recalculateOnlineDiscount();
}

function recalculateOnlineDiscount() {
    const isOffer = document.getElementById('hasOnlineOfferCheckbox').checked;
    const sellingPrice = parseFloat(document.getElementById('sellingPriceInput').value) || 0;
    const discountType = document.getElementById('discountTypeSelect').value;
    const discountValueInput = document.getElementById('discountValueInput');
    const discountVal = parseFloat(discountValueInput.value) || 0;
    const onlinePriceInput = document.getElementById('onlinePriceInput');
    const discountValueLabel = document.getElementById('discountValueLabel');
    const discountValueGroup = document.getElementById('discountValueGroup');

    if (discountType === 'percentage') {
        discountValueGroup.style.display = 'flex';
        discountValueLabel.innerText = 'Discount Rate (%) *';
        discountValueInput.placeholder = '10';
        const discounted = Math.max(0, Math.round(sellingPrice - (sellingPrice * (discountVal / 100))));
        onlinePriceInput.value = discounted;
    } else if (discountType === 'fixed') {
        discountValueGroup.style.display = 'flex';
        discountValueLabel.innerText = 'Flat Discount Amount (PKR) *';
        discountValueInput.placeholder = '3000';
        const discounted = Math.max(0, Math.round(sellingPrice - discountVal));
        onlinePriceInput.value = discounted;
    } else {
        // Direct custom price
        discountValueGroup.style.display = 'none';
        if (!onlinePriceInput.value || parseFloat(onlinePriceInput.value) === 0) {
            onlinePriceInput.value = sellingPrice;
        }
    }

    updateOfferPreview();
}

function onManualOnlinePriceChange() {
    updateOfferPreview();
}

function updateOfferPreview() {
    const sellingPrice = parseFloat(document.getElementById('sellingPriceInput').value) || 0;
    const onlinePrice = parseFloat(document.getElementById('onlinePriceInput').value) || sellingPrice;
    const badgeText = document.getElementById('offerBadgeInput').value.trim() || 'ONLINE DISCOUNT';
    const taglineText = document.getElementById('offerTaglineInput').value.trim() || 'Pay 100% advance online & get special discount!';

    const saving = Math.max(0, sellingPrice - onlinePrice);

    document.getElementById('previewOfferBadge').innerText = badgeText;
    document.getElementById('previewRegularPrice').innerText = 'PKR ' + sellingPrice.toLocaleString();
    document.getElementById('previewOnlinePrice').innerText = 'PKR ' + onlinePrice.toLocaleString();
    document.getElementById('previewSavingTag').innerText = 'SAVE PKR ' + saving.toLocaleString();
    document.getElementById('previewTaglineText').innerText = taglineText;
}

const SUBCATEGORY_MAP = {
    'mobiles': {
        label: 'Smartphone Brand / Model Series',
        options: [
            { val: '', text: '-- Select Smartphone Brand / Type --' },
            { val: 'samsung', text: '📱 Samsung Galaxy Series' },
            { val: 'apple', text: '🍎 Apple iPhone' },
            { val: 'infinix', text: '⚡ Infinix Hot / Note Series' },
            { val: 'tecno', text: '🚀 Tecno Spark / Camon Series' },
            { val: 'xiaomi', text: '💎 Xiaomi / Redmi / Poco' },
            { val: 'vivo', text: '🌟 Vivo V / Y Series' },
            { val: 'oppo', text: '🟢 Oppo Reno / A Series' },
            { val: 'feature_phones', text: '📞 Keypad & Feature Phones' },
            { val: 'other', text: '🏷️ Other Smartphone Brand' }
        ],
        pills: [
            { val: 'samsung', label: 'Samsung' },
            { val: 'apple', label: 'Apple' },
            { val: 'infinix', label: 'Infinix' },
            { val: 'tecno', label: 'Tecno' },
            { val: 'xiaomi', label: 'Xiaomi' },
            { val: 'vivo', label: 'Vivo' },
            { val: 'oppo', label: 'Oppo' }
        ]
    },
    'accessories': {
        label: 'Accessory Sub-Type / Filter Tab',
        options: [
            { val: '', text: '-- Select Accessory Sub-Type / Tab --' },
            { val: 'chargers', text: '⚡ Fast Chargers & Wall Adapters' },
            { val: 'airbuds', text: '🎧 True Wireless Airbuds & Audio' },
            { val: 'vehicle chargers', text: '🚗 Car Chargers & Mounts' },
            { val: 'screen protectors', text: '🛡️ Protectors & 9D/11D Glass' },
            { val: 'powerbanks', text: '🔋 Power Banks & Batteries' },
            { val: 'smartwatches', text: '⌚ Smart Watches & Straps' },
            { val: 'cables', text: '🔌 Fast Data Cables & OTG' },
            { val: 'covers', text: '📱 Back Covers & Cases' },
            { val: 'other', text: '🏷️ Other Accessories' }
        ],
        pills: [
            { val: 'chargers', label: '⚡ Chargers' },
            { val: 'airbuds', label: '🎧 Airbuds' },
            { val: 'vehicle chargers', label: '🚗 Car Chargers' },
            { val: 'screen protectors', label: '🛡️ Protectors' },
            { val: 'powerbanks', label: '🔋 Power Banks' },
            { val: 'smartwatches', label: '⌚ Watches' },
            { val: 'cables', label: '🔌 Cables' }
        ]
    },
    'cctv': {
        label: 'CCTV System & Equipment Type',
        options: [
            { val: '', text: '-- Select CCTV Equipment Type --' },
            { val: 'unv', text: '📹 UNV (Uniview) Cameras & NVR' },
            { val: 'hikvision', text: '📹 Hikvision Cameras & DVR' },
            { val: 'dahua', text: '📷 Dahua Cameras & DVR' },
            { val: 'poe_switches', text: '🌐 Network & PoE Switches' },
            { val: 'dvr_recorders', text: '🎛️ DVR / NVR Recorders' },
            { val: 'storage', text: '💾 Hard Disks & USB Storage' },
            { val: 'solar_cctv', text: '☀️ Solar 4G Wireless CCTV' },
            { val: 'cctv_accessories', text: '🔌 CCTV Cables, Connectors & Power' },
            { val: 'other', text: '🏷️ Other CCTV Equipment' }
        ],
        pills: [
            { val: 'unv', label: '⭐ UNV' },
            { val: 'hikvision', label: 'Hikvision' },
            { val: 'dahua', label: 'Dahua' },
            { val: 'poe_switches', label: 'PoE Switches' },
            { val: 'dvr_recorders', label: 'DVR/NVR' },
            { val: 'storage', label: 'Hard Disks' },
            { val: 'solar_cctv', label: 'Solar 4G' },
            { val: 'cctv_accessories', label: 'Cables & Power' }
        ]
    },
    'computer_accessories': {
        label: 'Computer Accessory Type / Sub-Category',
        options: [
            { val: '', text: '-- Select Computer Accessory Type --' },
            { val: 'keyboards_mice', text: '⌨️ Keyboards & Optical Mice' },
            { val: 'storage', text: '💾 USB Flash Drives, SSD & Hard Disks' },
            { val: 'cables_adapters', text: '🔌 HDMI, VGA, Type-C Hubs & Display Cables' },
            { val: 'headsets_audio', text: '🎧 PC Headsets, Speakers & Microphones' },
            { val: 'laptop_accessories', text: '💻 Laptop Bags, Sleeves & Cooling Pads' },
            { val: 'chargers_power', text: '⚡ Laptop Power Adapters & Power Cables' },
            { val: 'webcams', text: '📹 HD Webcams & Streaming Accessories' },
            { val: 'monitors', text: '🖥️ Monitors & Screen Accessories' },
            { val: 'other', text: '🏷️ Other Computer Accessories' }
        ],
        pills: [
            { val: 'keyboards_mice', label: '⌨️ Keyboards & Mice' },
            { val: 'storage', label: '💾 USB / SSD' },
            { val: 'cables_adapters', label: '🔌 HDMI / VGA' },
            { val: 'headsets_audio', label: '🎧 Headsets' },
            { val: 'laptop_accessories', label: '💻 Laptop Coolers' },
            { val: 'chargers_power', label: '⚡ Laptop Chargers' }
        ]
    },
    'network_accessories': {
        label: 'Network Equipment & Cable Type',
        options: [
            { val: '', text: '-- Select Network Accessory / Equipment --' },
            { val: 'routers_ap', text: '📶 Wi-Fi Routers & Access Points' },
            { val: 'switches', text: '🔀 Network Switches & Gigabit PoE' },
            { val: 'lan_cables', text: '🌐 Cat6 / Cat5e Ethernet LAN Cables' },
            { val: 'connectors_patch', text: '🔌 RJ45 Connectors, Boots & Patch Cords' },
            { val: 'tools_testers', text: '🛠️ Crimping Tools, Punch Downs & Cable Testers' },
            { val: 'fiber_optic', text: '⚡ Fiber Optic Patch Cords & Media Converters' },
            { val: 'wifi_adapters', text: '📡 USB Wi-Fi Dongles & Wireless Cards' },
            { val: 'poe_injectors', text: '⚡ PoE Injectors & Power Splitters' },
            { val: 'server_racks', text: '🗄️ Server Racks, Cable Organizers & Faceplates' },
            { val: 'other', text: '🏷️ Other Network Accessories' }
        ],
        pills: [
            { val: 'routers_ap', label: '📶 Wi-Fi Routers' },
            { val: 'switches', label: '🔀 Switches' },
            { val: 'lan_cables', label: '🌐 Cat6 Cables' },
            { val: 'connectors_patch', label: '🔌 RJ45 Connectors' },
            { val: 'tools_testers', label: '🛠️ Crimping Tools' },
            { val: 'wifi_adapters', label: '📡 Wi-Fi Adapters' }
        ]
    }
};

SUBCATEGORY_MAP['computeraccessories'] = SUBCATEGORY_MAP['computer_accessories'];
SUBCATEGORY_MAP['networkaccessories'] = SUBCATEGORY_MAP['network_accessories'];

const currentSavedSub = <?php echo json_encode(strtolower($editingProduct['subCategory'] ?? $editingProduct['sub_category'] ?? $editingProduct['brand'] ?? '')); ?>;

function onCategoryChange(catVal, initialLoad = false) {
    const subSel = document.getElementById('productSubCategorySelect');
    const pillsContainer = document.getElementById('subCategoryPillsContainer');
    const builder = document.getElementById('cctvInteractiveBuilder');
    const unitGroup = document.getElementById('unitMeasurementGroup');
    const unitSel = document.getElementById('productUnitSelect');
    if (!subSel || !pillsContainer) return;

    const catKey = (catVal || 'mobiles').toLowerCase().replace(/[\s-]/g, '_');
    const isCctvMode = (catKey === 'cctv' || catKey === 'security' || catKey === 'cameras' || catKey === 'cctv_accessories' || catKey === 'cables');
    const isCableOrNetwork = isCctvMode || catKey.includes('network');
    
    // Toggle CCTV Top Quick Select Banner
    const cctvTopBanner = document.getElementById('cctvTopQuickBanner');
    if (cctvTopBanner) {
        cctvTopBanner.style.display = isCctvMode ? 'flex' : 'none';
    }

    // Toggle CCTV Interactive Pick & Drop Builder
    if (builder) {
        builder.style.display = isCctvMode ? 'block' : 'none';
        if (isCctvMode) updateCctvCatalogMatches();
    }

    // Toggle Unit of Measurement Group (Show for CCTV, CCTV Accessories & Network Cables)
    if (unitGroup) {
        if (isCableOrNetwork) {
            unitGroup.style.display = 'block';
            if (unitSel) {
                onProductUnitChange(unitSel.value);
            }
        } else {
            // For Mobile Phones, Computer Accessories, etc: Hide unit box and force standard 'pcs'
            unitGroup.style.display = 'none';
            if (unitSel) {
                unitSel.value = 'pcs';
            }
            onProductUnitChange('pcs');
        }
    }

    const config = SUBCATEGORY_MAP[catKey] || {
        label: 'Sub-Category / Brand Tag',
        options: [
            { val: '', text: '-- Select Sub-Category / Type --' },
            { val: 'general', text: 'General Product' },
            { val: 'other', text: 'Other' }
        ],
        pills: []
    };

    // Render options
    let optHtml = '';
    config.options.forEach(opt => {
        const isSel = (initialLoad && currentSavedSub && (currentSavedSub === opt.val || (opt.val && currentSavedSub.includes(opt.val))));
        optHtml += `<option value="${opt.val}" ${isSel ? 'selected' : ''}>${opt.text}</option>`;
    });
    subSel.innerHTML = optHtml;

    // Render pills
    let pillHtml = '';
    config.pills.forEach(p => {
        pillHtml += `<button type="button" class="pos-btn pos-btn-sm" style="background:#ffffff; color:#0f172a; border:1px solid #cbd5e1; padding:3px 8px; font-size:0.72rem; border-radius:6px; font-weight:700;" onclick="setSubCategoryQuick('${p.val}')">${p.label}</button>`;
    });
    pillsContainer.innerHTML = pillHtml;
}

function setSubCategoryQuick(subVal) {
    const sel = document.getElementById('productSubCategorySelect');
    if (sel) {
        sel.value = subVal;
    }
    handleSubCategoryChange(subVal);
}

function handleSubCategoryChange(subVal) {
    const brandInput = document.getElementById('productBrandInput');
    if (brandInput && (!brandInput.value || brandInput.value === 'generic')) {
        if (subVal && subVal !== 'other' && subVal !== 'general') {
            brandInput.value = subVal.charAt(0).toUpperCase() + subVal.slice(1);
        }
    }
}

// -------------------------------------------------------------
// CCTV INTERACTIVE ATTRIBUTE BUILDER (PICK & DROP SYSTEM)
// -------------------------------------------------------------
window.cctvCatalogData = <?php echo json_encode($cctvCatalog); ?> || [];
let currentCctvState = {
    brand: 'UNV',
    mp: '2 MP (1080P Full HD)',
    type: 'Bullet Camera'
};

function setCctvBrand(brand, btn) {
    currentCctvState.brand = brand;
    document.querySelectorAll('.cctv-pick-brand').forEach(b => {
        b.style.background = '#fff';
        b.style.color = '#334155';
    });
    if (btn) {
        btn.style.background = '#0284c7';
        btn.style.color = '#fff';
    }
    document.getElementById('productBrandInput').value = brand;
    composeCctvProduct();
}

function setCctvMp(mp, btn) {
    currentCctvState.mp = mp;
    document.querySelectorAll('.cctv-pick-mp').forEach(b => {
        b.style.background = '#fff';
    });
    if (btn) {
        btn.style.background = '#dcfce7';
    }
    composeCctvProduct();
}

function setCctvType(type, btn, unit = null) {
    currentCctvState.type = type;
    document.querySelectorAll('.cctv-pick-type').forEach(b => {
        b.style.background = '#fff';
        b.style.color = '#334155';
    });
    if (btn) {
        btn.style.background = '#10b981';
        btn.style.color = '#fff';
    }
    if (unit) {
        setProductUnitQuick(unit);
    }
    composeCctvProduct();
}

function onProductUnitChange(unitVal) {
    const unit = (unitVal || 'pcs').toLowerCase();
    
    // Update active pill button style
    document.querySelectorAll('.unit-quick-pill').forEach(btn => {
        if (btn.getAttribute('data-unit') === unit) {
            btn.style.background = '#059669';
            btn.style.color = '#fff';
            btn.style.borderColor = '#047857';
        } else {
            btn.style.background = '#fff';
            btn.style.color = '#065f46';
            btn.style.borderColor = '#a7f3d0';
        }
    });

    const lblSell = document.getElementById('lblSellingPrice');
    const lblCost = document.getElementById('lblCostPrice');
    const lblStock = document.getElementById('lblStock');
    const lblMin = document.getElementById('lblMinStock');

    const inSell = document.getElementById('sellingPriceInput');
    const inCost = document.getElementById('costPriceInput');
    const inStock = document.getElementById('stockInput');
    const inMin = document.getElementById('minStockInput');
    const unitHelp = document.getElementById('unitHelpText');

    if (unit === 'meter' || unit === 'm') {
        if (lblSell) lblSell.innerHTML = '<i class="fa-solid fa-ruler" style="color:#059669;"></i> Retail Regular Price (PKR / Meter) *';
        if (lblCost) lblCost.innerHTML = '<i class="fa-solid fa-tags" style="color:#64748b;"></i> Wholesale Cost Price (PKR / Meter)';
        if (lblStock) lblStock.innerHTML = '<i class="fa-solid fa-boxes-stacked" style="color:#2563eb;"></i> Initial Stock Quantity (Total Meters in Stock) *';
        if (lblMin) lblMin.innerHTML = 'Min Low Stock Limit (Meters)';
        if (inSell) inSell.placeholder = 'e.g. 45 / meter';
        if (inCost) inCost.placeholder = 'e.g. 35 / meter';
        if (inStock) inStock.placeholder = 'e.g. 305 meters';
        if (inMin) inMin.placeholder = 'e.g. 20 meters';
        if (unitHelp) unitHelp.innerHTML = '📏 <strong>Per Meter Selected:</strong> Price entered is per meter. Initial stock is total cable length in meters (e.g. 305 meters roll = 305 stock).';
    } else if (unit === 'yard' || unit === 'yd' || unit === 'gaz') {
        if (lblSell) lblSell.innerHTML = '<i class="fa-solid fa-ruler-horizontal" style="color:#2563eb;"></i> Retail Regular Price (PKR / Yard / Gaz) *';
        if (lblCost) lblCost.innerHTML = '<i class="fa-solid fa-tags" style="color:#64748b;"></i> Wholesale Cost Price (PKR / Yard / Gaz)';
        if (lblStock) lblStock.innerHTML = '<i class="fa-solid fa-boxes-stacked" style="color:#2563eb;"></i> Initial Stock Quantity (Total Yards / Gaz in Stock) *';
        if (lblMin) lblMin.innerHTML = 'Min Low Stock Limit (Yards)';
        if (inSell) inSell.placeholder = 'e.g. 50 / yard';
        if (inCost) inCost.placeholder = 'e.g. 40 / yard';
        if (inStock) inStock.placeholder = 'e.g. 100 yards';
        if (inMin) inMin.placeholder = 'e.g. 15 yards';
        if (unitHelp) unitHelp.innerHTML = '📐 <strong>Per Yard (Gaz) Selected:</strong> Price entered is per yard. Initial stock is total cable length in yards/gaz (e.g. 100 yards = 100 stock).';
    } else if (unit === 'roll') {
        if (lblSell) lblSell.innerHTML = '<i class="fa-solid fa-box" style="color:#d97706;"></i> Retail Regular Price (PKR / Full Roll) *';
        if (lblCost) lblCost.innerHTML = '<i class="fa-solid fa-tags" style="color:#64748b;"></i> Wholesale Cost Price (PKR / Full Roll)';
        if (lblStock) lblStock.innerHTML = '<i class="fa-solid fa-boxes-stacked" style="color:#2563eb;"></i> Initial Stock Quantity (Total Rolls in Stock) *';
        if (lblMin) lblMin.innerHTML = 'Min Low Stock Limit (Rolls)';
        if (inSell) inSell.placeholder = 'e.g. 4500 / roll (305m)';
        if (inCost) inCost.placeholder = 'e.g. 3800 / roll';
        if (inStock) inStock.placeholder = 'e.g. 5 rolls';
        if (inMin) inMin.placeholder = 'e.g. 2 rolls';
        if (unitHelp) unitHelp.innerHTML = '📦 <strong>Per Roll Selected:</strong> Price entered is for full bundle/roll (e.g. 1 roll of 305m Cat6). Initial stock is number of rolls.';
    } else if (unit === 'foot' || unit === 'ft') {
        if (lblSell) lblSell.innerHTML = '<i class="fa-solid fa-ruler-combined" style="color:#7c3aed;"></i> Retail Regular Price (PKR / Foot) *';
        if (lblCost) lblCost.innerHTML = '<i class="fa-solid fa-tags" style="color:#64748b;"></i> Wholesale Cost Price (PKR / Foot)';
        if (lblStock) lblStock.innerHTML = '<i class="fa-solid fa-boxes-stacked" style="color:#2563eb;"></i> Initial Stock Quantity (Total Feet in Stock) *';
        if (lblMin) lblMin.innerHTML = 'Min Low Stock Limit (Feet)';
        if (inSell) inSell.placeholder = 'e.g. 15 / foot';
        if (inCost) inCost.placeholder = 'e.g. 10 / foot';
        if (inStock) inStock.placeholder = 'e.g. 500 feet';
        if (inMin) inMin.placeholder = 'e.g. 50 feet';
        if (unitHelp) unitHelp.innerHTML = '🦶 <strong>Per Foot Selected:</strong> Price entered is per foot.';
    } else {
        // Pcs / Piece
        if (lblSell) lblSell.innerHTML = 'Retail Regular Price (PKR) *';
        if (lblCost) lblCost.innerHTML = 'Wholesale Cost Price (PKR)';
        if (lblStock) lblStock.innerHTML = 'Initial Stock Quantity *';
        if (lblMin) lblMin.innerHTML = 'Min Low Stock Limit';
        if (inSell) inSell.placeholder = '385000';
        if (inCost) inCost.placeholder = '360000';
        if (inStock) inStock.placeholder = '5';
        if (inMin) inMin.placeholder = '2';
        if (unitHelp) unitHelp.innerHTML = '🏷️ <strong>Per Piece Selected:</strong> Default for smartphones, chargers, cameras, airbuds, and standard boxed products.';
    }
}

function setProductUnitQuick(unit, btn) {
    const sel = document.getElementById('productUnitSelect');
    if (sel) {
        sel.value = unit;
    }
    onProductUnitChange(unit);
}

// Auto-detect Cable / Wire from Name input
function checkNameForCableKeywords(name) {
    const catSel = document.getElementById('productCategorySelect');
    const catKey = catSel ? (catSel.value || '').toLowerCase() : 'mobiles';
    const isCctvMode = (catKey === 'cctv' || catKey === 'security' || catKey === 'cameras' || catKey === 'cctv_accessories' || catKey === 'cables' || catKey === 'wires');
    const n = (name || '').toLowerCase();
    const isCable = n.includes('cable') || n.includes('wire') || n.includes('cat6') || n.includes('cat5') || n.includes('rg59') || n.includes('coaxial') || n.includes('patch cord') || n.includes('drop wire') || n.includes('fiber optic') || n.includes('cctv wire') || n.includes('targa');
    const badge = document.getElementById('unitDetectedBadge');
    const unitGroup = document.getElementById('unitMeasurementGroup');
    
    if (isCable && isCctvMode) {
        if (badge) badge.style.display = 'inline-flex';
        if (unitGroup) unitGroup.style.display = 'block';
        const sel = document.getElementById('productUnitSelect');
        // If current unit is piece and cable is detected, auto-switch to yard or meter
        if (sel && (sel.value === 'pcs' || sel.value === 'piece')) {
            if (n.includes('yard') || n.includes('gaz')) {
                sel.value = 'yard';
                onProductUnitChange('yard');
            } else if (n.includes('roll')) {
                sel.value = 'roll';
                onProductUnitChange('roll');
            } else {
                // Default cable measurement to Meter
                sel.value = 'meter';
                onProductUnitChange('meter');
            }
        }
    } else if (!isCctvMode) {
        if (badge) badge.style.display = 'none';
        if (unitGroup) unitGroup.style.display = 'none';
        const sel = document.getElementById('productUnitSelect');
        if (sel && sel.value !== 'pcs') {
            sel.value = 'pcs';
            onProductUnitChange('pcs');
        }
    } else {
        if (badge) badge.style.display = 'none';
    }
}

function composeCctvProduct() {
    const isCamera = currentCctvState.type.toLowerCase().includes('camera');
    let generatedName = '';
    
    if (isCamera) {
        generatedName = `${currentCctvState.brand} ${currentCctvState.type} ${currentCctvState.mp.split(' ')[0]} MP`;
    } else {
        generatedName = `${currentCctvState.brand} ${currentCctvState.type}`;
    }
    
    document.getElementById('productNameInput').value = generatedName;
    
    const skuInput = document.querySelector('input[name="sku"]');
    if (skuInput && !skuInput.value) {
        skuInput.value = 'CCTV-' + currentCctvState.brand.toUpperCase() + '-' + Math.floor(100 + Math.random() * 900);
    }
    
    updateCctvCatalogMatches();
}

function updateCctvCatalogMatches() {
    const listContainer = document.getElementById('cctvCatalogMatchesList');
    if (!listContainer) return;
    
    const brand = currentCctvState.brand.toLowerCase();
    const type = currentCctvState.type.toLowerCase();
    
    const matches = window.cctvCatalogData.filter(item => {
        const itemBrand = (item.brand || '').toLowerCase();
        const itemName = (item.name || '').toLowerCase();
        const itemSub = (item.subCategory || '').toLowerCase();
        
        return itemBrand.includes(brand) || itemName.includes(type) || itemSub.includes(type);
    });
    
    if (matches.length === 0) {
        listContainer.innerHTML = '<span style="font-size:0.72rem; color:#64748b;">No direct catalog item matches found for current filter.</span>';
        return;
    }
    
    let html = '';
    matches.slice(0, 6).forEach(m => {
        html += `
            <button type="button" class="pos-btn pos-btn-sm" onclick="loadCctvCatalogItem(${m.id})" style="background:#f0fdf4; border:1px solid #86efac; color:#15803d; font-size:0.72rem; font-weight:800; padding:4px 8px; border-radius:6px;">
                <i class="fa-solid fa-circle-check"></i> ${escapeHtml(m.name)} (PKR ${Number(m.defaultPrice).toLocaleString()})
            </button>
        `;
    });
    listContainer.innerHTML = html;
}

function loadCctvCatalogItem(id) {
    const item = window.cctvCatalogData.find(x => x.id === id);
    if (!item) return;
    
    document.getElementById('productNameInput').value = item.name;
    
    // Set Category to CCTV
    const catSel = document.getElementById('productCategorySelect');
    if (catSel) {
        catSel.value = 'cctv';
        onCategoryChange('cctv');
    }
    
    // Set Brand
    const brandInput = document.getElementById('productBrandInput');
    if (brandInput) {
        brandInput.value = item.brand;
    }
    
    // Set Selling Price & Wholesale Cost Price
    const priceInput = document.getElementById('sellingPriceInput');
    if (priceInput) {
        priceInput.value = item.defaultPrice;
    }
    const costInput = document.querySelector('input[name="costPrice"]');
    if (costInput) {
        costInput.value = Math.round(item.defaultPrice * 0.85);
    }

    // Set Unit if present in catalog
    if (item.unit) {
        const u = item.unit.toLowerCase();
        if (u.includes('meter')) setProductUnitQuick('meter');
        else if (u.includes('yard') || u.includes('gaz')) setProductUnitQuick('yard');
        else if (u.includes('roll')) setProductUnitQuick('roll');
        else if (u.includes('foot')) setProductUnitQuick('foot');
        else setProductUnitQuick('pcs');
    } else {
        checkNameForCableKeywords(item.name);
    }
    
    // Auto-fill SKU
    const skuInput = document.querySelector('input[name="sku"]');
    if (skuInput && !skuInput.value) {
        skuInput.value = 'CCTV-' + item.brand.toUpperCase().substring(0, 3) + '-' + Math.floor(100 + Math.random() * 900);
    }
    
    // Auto-fill Specs
    const specsArea = document.querySelector('textarea[name="specs"]');
    if (specsArea) {
        specsArea.value = "Dealer: " + (item.dealer || 'New japan electronic') + "\nCategory: " + item.subCategory + "\nBrand: " + item.brand + "\nUnit: " + item.unit + "\nAuthentic New Japan Electronic CCTV Distribution";
    }
    
    recalculateOnlineDiscount();
    
    if (window.showToast) {
        window.showToast('success', 'Loaded item: ' + item.name, 'Dealer: ' + item.dealer);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', () => {
    recalculateOnlineDiscount();
    const catSel = document.getElementById('productCategorySelect');
    if (catSel) {
        onCategoryChange(catSel.value, true);
    }
    const nameInput = document.getElementById('productNameInput');
    if (nameInput && nameInput.value) {
        checkNameForCableKeywords(nameInput.value);
    }
});
</script>

<?php 
require_once __DIR__ . '/includes/smart_importer_modal.php';
require_once __DIR__ . '/includes/footer.php'; 
?>
