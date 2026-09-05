<?php
$currentPage = 'brands';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? 'mobiles';
    if (!empty($name)) {
        $brands = get_json_file('brands') ?? [];
        $id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        if (empty($id)) $id = 'brand-' . time();
        $brands[] = [
            'id' => $id,
            'name' => $name,
            'category' => $category,
            'status' => 'active'
        ];
        save_json_file('brands', $brands);
        $msg = "Brand '{$name}' created successfully!";
    }
}

$brands = get_json_file('brands') ?? [];
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content">
        <div class="page-header">
            <div>
                <h1><i class="fa-solid fa-copyright" style="color:var(--pos-red); margin-right:10px;"></i> Brand Directory</h1>
                <p class="page-header-sub">Manage brand manufacturers (Samsung, Apple, Hikvision, etc.)</p>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:24px;">
            <div class="pos-card">
                <h3 class="pos-card-title" style="margin-bottom:16px;">Add New Brand</h3>
                <form method="POST" action="brands.php">
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label">Brand Name *</label>
                        <input type="text" name="name" class="form-input" placeholder="e.g. Xiaomi / Vivo" required>
                    </div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="mobiles">Mobile Phones</option>
                            <option value="accessories">Mobile Accessories</option>
                            <option value="cctv">CCTV Security</option>
                        </select>
                    </div>
                    <button type="submit" class="pos-btn pos-btn-primary pos-btn-block">
                        <i class="fa-solid fa-plus"></i> Create Brand
                    </button>
                </form>
            </div>

            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Brand ID</th>
                            <th>Brand Name</th>
                            <th>Category</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($brands as $b): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($b['id']); ?></code></td>
                                <td><strong><?php echo htmlspecialchars($b['name']); ?></strong></td>
                                <td><span class="status-badge status-active"><?php echo strtoupper($b['category'] ?? 'mobiles'); ?></span></td>
                                <td><span class="status-badge status-active"><?php echo strtoupper($b['status'] ?? 'active'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
