<?php
$currentPage = 'suppliers';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$suppliers = get_json_file('suppliers') ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!empty($name)) {
        $suppliers[] = [
            'id' => 'sup-' . time(),
            'name' => $name,
            'company' => $company,
            'phone' => $phone,
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'status' => 'active'
        ];
        save_json_file('suppliers', $suppliers);
    }
}
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content">
        <div class="page-header">
            <div>
                <h1><i class="fa-solid fa-building" style="color:var(--pos-red); margin-right:10px;"></i> Suppliers Directory</h1>
                <p class="page-header-sub">Manage wholesale distributors, contacts, and phone numbers</p>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:24px;">
            <div class="pos-card">
                <h3 class="pos-card-title" style="margin-bottom:16px;">Add Supplier</h3>
                <form method="POST" action="suppliers.php">
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label">Contact Person Name *</label>
                        <input type="text" name="name" class="form-input" placeholder="e.g. Tariq Distributor" required>
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label">Company Name *</label>
                        <input type="text" name="company" class="form-input" placeholder="e.g. Samsung Pak M&P" required>
                    </div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label">Phone Number *</label>
                        <input type="text" name="phone" class="form-input" placeholder="0300..." required>
                    </div>
                    <button type="submit" class="pos-btn pos-btn-primary pos-btn-block">
                        <i class="fa-solid fa-plus"></i> Save Supplier
                    </button>
                </form>
            </div>

            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Supplier Name</th>
                            <th>Company</th>
                            <th>Phone</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($s['company']); ?></td>
                                <td><?php echo htmlspecialchars($s['phone']); ?></td>
                                <td><span class="status-badge status-active"><?php echo strtoupper($s['status'] ?? 'active'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
