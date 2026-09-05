<?php
$currentPage = 'products';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleteId = $_POST['delete_id'] ?? '';
    if ($deleteId) {
        $products = get_json_file('products') ?? [];
        $delName = '';
        $filtered = array_values(array_filter($products, function($p) use ($deleteId, &$delName) {
            if ($p['id'] === $deleteId) {
                $delName = $p['name'];
                return false;
            }
            return true;
        }));
        save_json_file('products', $filtered);
        $msg = "Product '{$delName}' has been deleted successfully.";
        $msgType = 'success';
    }
}

$products = get_json_file('products') ?? [];
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content">
        <div class="page-header">
            <div>
                <h1><i class="fa-solid fa-boxes-stacked" style="color:var(--pos-red); margin-right:10px;"></i> Products Directory</h1>
                <p class="page-header-sub">Manage inventory catalog, pricing, SKU barcodes and stock levels</p>
            </div>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <button type="button" class="pos-btn" onclick="openSmartImporterModal()" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:#ffffff; border:none; box-shadow:0 4px 14px rgba(16,185,129,0.35); font-weight:800; font-size:0.85rem; padding:8px 14px; border-radius:8px; cursor:pointer;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> ⚡ Import Bill / Excel / Receipt
                </button>
                <a href="product-add.php" class="pos-btn pos-btn-primary">
                    <i class="fa-solid fa-plus"></i> Add New Product
                </a>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="login-error" style="margin-bottom:20px; background:<?php echo $msgType === 'success' ? '#ecfdf5' : '#fef2f2'; ?>; border:1px solid <?php echo $msgType === 'success' ? '#a7f3d0' : '#fecaca'; ?>; color:<?php echo $msgType === 'success' ? '#065f46' : '#dc2626'; ?>; padding:12px 16px; border-radius:8px; font-weight:700;">
                <i class="fa-solid <?php echo $msgType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="data-table-wrap">
            <div class="data-table-toolbar">
                <div class="data-table-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="productSearchInput" placeholder="Search product name, SKU, or barcode...">
                </div>
                <div style="font-size:0.88rem; font-weight:700; color:var(--pos-text-sec);">
                    Total: <strong><?php echo count($products); ?> Products</strong>
                </div>
            </div>

            <table class="data-table" id="productsTable">
                <thead>
                    <tr>
                        <th>Product Details</th>
                        <th>Category</th>
                        <th>Selling Price</th>
                        <th>Cost Price</th>
                        <th>Stock Level</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:30px;">No products found in database.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): 
                            $imgSrc = $p['image'] ?? '';
                            if ($imgSrc && strpos($imgSrc, 'http') !== 0 && strpos($imgSrc, '/') !== 0 && strpos($imgSrc, '../') !== 0) {
                                $imgSrc = '../' . $imgSrc;
                            }
                        ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" style="width:40px; height:40px; object-fit:cover; border-radius:6px; background:#f3f4f6;" onerror="this.src='https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=600&auto=format&fit=crop&q=80'">
                                        <div>
                                            <div style="font-weight:700; color:var(--pos-text);"><?php echo htmlspecialchars($p['name']); ?></div>
                                            <div style="font-size:0.75rem; color:var(--pos-text-muted);">SKU: <?php echo htmlspecialchars($p['sku'] ?? 'N/A'); ?> | Barcode: <?php echo htmlspecialchars($p['barcode'] ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status-badge status-active"><?php echo strtoupper($p['category'] ?? $p['categoryId'] ?? 'General'); ?></span></td>
                                <td>
                                    <strong style="color:var(--pos-text);">PKR <?php echo number_format($p['sellingPrice'] ?? $p['priceNumeric'] ?? 0); ?><?php if(!empty($p['unit']) && $p['unit'] !== 'pcs' && $p['unit'] !== 'piece') echo ' <span style="font-size:0.75rem; color:#64748b; font-weight:600;">/ ' . htmlspecialchars($p['unitLabel'] ?? ucfirst($p['unit'])) . '</span>'; ?></strong>
                                    <?php if (!empty($p['hasOnlineOffer'])): ?>
                                        <div style="margin-top:3px;">
                                            <span style="background:#fee2e2; color:#b91c1c; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:4px; border:1px solid #fecaca;">
                                                <i class="fa-solid fa-tags"></i> <?php echo htmlspecialchars($p['offerBadge'] ?? 'ONLINE OFFER'); ?> (PKR <?php echo number_format($p['onlinePrice'] ?? 0); ?><?php if(!empty($p['unit']) && $p['unit'] !== 'pcs' && $p['unit'] !== 'piece') echo ' / ' . htmlspecialchars($p['unitLabel'] ?? ucfirst($p['unit'])); ?>)
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>PKR <?php echo number_format($p['costPrice'] ?? 0); ?><?php if(!empty($p['unit']) && $p['unit'] !== 'pcs' && $p['unit'] !== 'piece') echo ' <span style="font-size:0.72rem; color:#64748b;">/ ' . htmlspecialchars($p['unitLabel'] ?? ucfirst($p['unit'])) . '</span>'; ?></td>
                                <td>
                                    <?php 
                                    $st = floatval($p['stock'] ?? 0);
                                    $unitSuffix = (!empty($p['unit']) && $p['unit'] !== 'pcs' && $p['unit'] !== 'piece') ? (' ' . htmlspecialchars($p['unitLabel'] ?? ucfirst($p['unit']))) : '';
                                    if ($st <= 0) echo '<span class="status-badge status-out-of-stock">OUT OF STOCK (0)</span>';
                                    else if ($st <= floatval($p['minStock'] ?? 2)) echo '<span class="status-badge status-low-stock">LOW STOCK (' . $st . $unitSuffix . ')</span>';
                                    else echo '<span class="status-badge status-in-stock">IN STOCK (' . $st . $unitSuffix . ')</span>';
                                    ?>
                                </td>
                                <td><span class="status-badge status-active"><?php echo strtoupper($p['status'] ?? 'active'); ?></span></td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex; gap:6px; align-items:center;">
                                        <a href="product-add.php?id=<?php echo $p['id']; ?>" class="pos-btn pos-btn-outline pos-btn-sm">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </a>
                                        <form method="POST" action="products.php" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars(addslashes($p['name'])); ?>?');" style="display:inline;">
                                            <input type="hidden" name="delete_id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="pos-btn pos-btn-outline pos-btn-sm" style="color:#ef4444; border-color:#ef4444;" title="Delete Product">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('productSearchInput');
    if (input) {
        input.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            const rows = document.querySelectorAll('#productsTable tbody tr');
            rows.forEach(function(r) {
                const text = r.innerText.toLowerCase();
                r.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }
});
</script>

<?php 
require_once __DIR__ . '/includes/smart_importer_modal.php';
require_once __DIR__ . '/includes/footer.php'; 
?>

