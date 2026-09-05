<?php
$currentPage = 'inventory';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prodId = $_POST['productId'] ?? '';
    $adj = intval($_POST['adjustment'] ?? 0);

    if (!empty($prodId) && $adj !== 0) {
        $products = get_json_file('products') ?? [];
        $adjName = '';
        $newStockVal = 0;
        foreach ($products as &$p) {
            if ($p['id'] === $prodId) {
                $p['stock'] = max(0, intval($p['stock']) + $adj);
                $adjName = $p['name'];
                $newStockVal = $p['stock'];
                break;
            }
        }
        save_json_file('products', $products);
        $msg = "Stock for '{$adjName}' adjusted by " . ($adj > 0 ? "+$adj" : "$adj") . " (New Stock: {$newStockVal})";
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
                <h1><i class="fa-solid fa-warehouse" style="color:var(--pos-red); margin-right:10px;"></i> Inventory Stock Control</h1>
                <p class="page-header-sub">Stock valuation, stock adjustments, and low inventory warnings</p>
            </div>
        </div>

        <!-- Stock Adjust Modal Form -->
        <div class="pos-card" style="margin-bottom:24px;">
            <h3 class="pos-card-title" style="margin-bottom:16px;"><i class="fa-solid fa-boxes-packing" style="color:var(--pos-gold); margin-right:8px;"></i> Quick Stock Adjustment</h3>
            <form method="POST" action="inventory.php" style="display:flex; gap:12px; align-items:flex-end;">
                <div class="form-group" style="flex:2;">
                    <label class="form-label">Select Product</label>
                    <select name="productId" class="form-select" required>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?> (Current Stock: <?php echo $p['stock']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Stock Change (+ / -)</label>
                    <input type="number" name="adjustment" class="form-input" placeholder="+10 or -2" required>
                </div>
                <button type="submit" class="pos-btn pos-btn-primary" style="height:44px;">
                    <i class="fa-solid fa-check"></i> Adjust Stock
                </button>
            </form>
        </div>

        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Wholesale Cost</th>
                        <th>Selling Price</th>
                        <th>Current Stock</th>
                        <th>Stock Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                            <td><code><?php echo htmlspecialchars($p['sku'] ?? 'N/A'); ?></code></td>
                            <td>PKR <?php echo number_format($p['costPrice'] ?? 0); ?></td>
                            <td>PKR <?php echo number_format($p['sellingPrice'] ?? $p['priceNumeric'] ?? 0); ?></td>
                            <td><strong style="font-size:1.1rem;"><?php echo intval($p['stock'] ?? 0); ?></strong></td>
                            <td>
                                <?php 
                                $st = intval($p['stock'] ?? 0);
                                if ($st <= 0) echo '<span class="status-badge status-out-of-stock">OUT OF STOCK</span>';
                                else if ($st <= intval($p['minStock'] ?? 2)) echo '<span class="status-badge status-low-stock">LOW STOCK</span>';
                                else echo '<span class="status-badge status-in-stock">HEALTHY</span>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
