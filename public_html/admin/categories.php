<?php
$currentPage = 'categories';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$msg = '';
$msgType = 'success';

$categories = get_json_file('categories') ?? [];
$products = get_json_file('products') ?? [];

// Handle Create / Delete Category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add_category';

    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-tags');
        $showInMenu = isset($_POST['show_in_menu']) && $_POST['show_in_menu'] == '1';
        if (!empty($name)) {
            $id = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', trim($name)));
            $id = trim(preg_replace('/_+/', '_', $id), '_');
            if (empty($id)) $id = 'cat_' . time();
            
            // Check if ID already exists
            $exists = false;
            foreach ($categories as $c) {
                if (($c['id'] ?? '') === $id) {
                    $exists = true;
                    break;
                }
            }

            if ($exists) {
                $msg = "A category with name '{$name}' already exists.";
                $msgType = 'danger';
            } else {
                $categories[] = [
                    'id' => $id,
                    'name' => $name,
                    'icon' => $icon,
                    'status' => 'active',
                    'show_in_menu' => $showInMenu,
                    'createdAt' => date('c')
                ];
                save_json_file('categories', $categories);
                $menuNote = $showInMenu ? "and added to storefront menu bar." : "(hidden from storefront menu bar as configured).";
                $msg = "Category '{$name}' created successfully! It is now available in 'Add New Product' {$menuNote}";
                $msgType = 'success';
            }
        }
    } elseif ($action === 'toggle_menu') {
        $toggleId = $_POST['category_id'] ?? '';
        foreach ($categories as &$c) {
            if (($c['id'] ?? '') === $toggleId) {
                $current = $c['show_in_menu'] ?? true;
                $c['show_in_menu'] = !$current;
                $statusText = $c['show_in_menu'] ? 'visible in storefront menu bar' : 'hidden from storefront menu bar';
                $msg = "Category '{$c['name']}' is now {$statusText}.";
                $msgType = 'success';
                break;
            }
        }
        save_json_file('categories', $categories);
    } elseif ($action === 'delete_category') {
        $delId = $_POST['category_id'] ?? '';
        if ($delId) {
            $categories = array_values(array_filter($categories, function($c) use ($delId) {
                return ($c['id'] ?? '') !== $delId;
            }));
            save_json_file('categories', $categories);
            $msg = "Category deleted successfully.";
            $msgType = 'success';
        }
    }
}

// Calculate product count per category
$categoryCounts = [];
foreach ($products as $p) {
    $catKey = strtolower($p['category'] ?? $p['categoryId'] ?? 'general');
    $categoryCounts[$catKey] = ($categoryCounts[$catKey] ?? 0) + 1;
}
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content">
        <div class="page-header">
            <div>
                <h1 style="display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-tags" style="color:var(--pos-red);"></i> Product Categories
                </h1>
                <p class="page-header-sub">Manage product categories. All categories added here will automatically appear in "Add New Product", POS Terminal, and on the customer website.</p>
            </div>
            <a href="product-add.php" class="pos-btn pos-btn-primary" style="border-radius:30px; font-weight:800;">
                <i class="fa-solid fa-plus"></i> + Add New Product
            </a>
        </div>

        <?php if ($msg): ?>
            <div class="pos-alert pos-alert-<?php echo $msgType; ?>" style="margin-bottom:20px; border-radius:12px; padding:14px 18px; display:flex; align-items:center; gap:10px;">
                <i class="fa-solid <?php echo $msgType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
                <span><?php echo htmlspecialchars($msg); ?></span>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:24px;">
            <!-- Add Form -->
            <div class="pos-card" style="border-radius:16px; padding:22px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; border-bottom:1px solid var(--pos-border); padding-bottom:12px;">
                    <div style="width:36px; height:36px; border-radius:8px; background:rgba(225,29,72,0.1); color:var(--pos-red); display:flex; align-items:center; justify-content:center;">
                        <i class="fa-solid fa-folder-plus"></i>
                    </div>
                    <h3 class="pos-card-title" style="margin:0; font-size:1.15rem;">Add New Category</h3>
                </div>

                <form method="POST" action="categories.php">
                    <input type="hidden" name="action" value="add_category">
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label" style="font-weight:800; font-size:0.82rem;">Category Name *</label>
                        <input type="text" name="name" class="form-input" placeholder="e.g. Smart Watches, Tablets, Laptops..." required style="height:44px; font-weight:700;">
                    </div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label" style="font-weight:800; font-size:0.82rem;">Icon (FontAwesome Class)</label>
                        <input type="text" name="icon" class="form-input" placeholder="e.g. fa-clock, fa-laptop, fa-tablet" value="fa-tags" style="height:40px;">
                    </div>
                    <div class="form-group" style="margin-bottom:20px; background:#f8fafc; padding:12px 14px; border-radius:10px; border:1px solid var(--pos-border);">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:700; font-size:0.85rem; color:#0f172a; margin:0;">
                            <input type="checkbox" name="show_in_menu" value="1" style="width:18px; height:18px; cursor:pointer;" checked>
                            <span>Show in Storefront Menu Bar</span>
                        </label>
                        <div style="font-size:0.75rem; color:#64748b; margin-top:4px; padding-left:28px;">
                            Uncheck to hide this category from top navigation bar (e.g. Computer / Network Accessories).
                        </div>
                    </div>
                    <button type="submit" class="pos-btn pos-btn-primary pos-btn-block" style="height:46px; font-weight:800; border-radius:10px; font-size:0.95rem;">
                        <i class="fa-solid fa-plus"></i> Create &amp; Save Category
                    </button>
                </form>
            </div>

            <!-- List Table -->
            <div class="data-table-wrap pos-card" style="padding:0; overflow-x:auto; border-radius:16px;">
                <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid var(--pos-border);">
                            <th style="padding:12px 16px;">Category Name</th>
                            <th style="padding:12px 16px;">System Slug / ID</th>
                            <th style="padding:12px 16px;">Active Products</th>
                            <th style="padding:12px 16px;">Menu Bar</th>
                            <th style="padding:12px 16px;">Status</th>
                            <th style="padding:12px 16px; text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:35px; color:var(--pos-text-muted);">No product categories registered yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($categories as $c): 
                                $cid = $c['id'];
                                $pCount = $categoryCounts[$cid] ?? 0;
                                $inMenu = $c['show_in_menu'] ?? true;
                            ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:12px 16px;">
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <div style="width:32px; height:32px; border-radius:8px; background:#f1f5f9; color:#475569; display:flex; align-items:center; justify-content:center; font-size:0.85rem;">
                                                <i class="fa-solid <?php echo htmlspecialchars($c['icon'] ?? 'fa-tag'); ?>"></i>
                                            </div>
                                            <strong style="font-size:0.95rem; color:#0f172a;"><?php echo htmlspecialchars($c['name']); ?></strong>
                                        </div>
                                    </td>
                                    <td style="padding:12px 16px;">
                                        <code style="background:#f1f5f9; color:#4f46e5; padding:2px 8px; border-radius:4px; font-weight:700; font-size:0.82rem;"><?php echo htmlspecialchars($cid); ?></code>
                                    </td>
                                    <td style="padding:12px 16px;">
                                        <span class="status-badge" style="background:rgba(16,185,129,0.1); color:#059669; font-weight:800; font-size:0.75rem;">
                                            <?php echo $pCount; ?> Products
                                        </span>
                                    </td>
                                    <td style="padding:12px 16px;">
                                        <?php if ($inMenu): ?>
                                            <span class="status-badge" style="background:#ecfdf5; color:#059669; font-weight:800; font-size:0.75rem;">
                                                <i class="fa-solid fa-eye"></i> Visible
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge" style="background:#fef2f2; color:#dc2626; font-weight:800; font-size:0.75rem;">
                                                <i class="fa-solid fa-eye-slash"></i> Hidden
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:12px 16px;">
                                        <span class="status-badge status-active" style="font-size:0.72rem; font-weight:800;">
                                            <?php echo strtoupper($c['status'] ?? 'ACTIVE'); ?>
                                        </span>
                                    </td>
                                    <td style="padding:12px 16px; text-align:right;">
                                        <div style="display:flex; justify-content:flex-end; gap:6px;">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_menu">
                                                <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($cid); ?>">
                                                <button type="submit" class="pos-btn pos-btn-secondary pos-btn-sm" style="padding:4px 8px; border-radius:6px;" title="<?php echo $inMenu ? 'Hide from Menu Bar' : 'Show in Menu Bar'; ?>">
                                                    <i class="fa-solid <?php echo $inMenu ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this category?');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($cid); ?>">
                                                <button type="submit" class="pos-btn pos-btn-danger pos-btn-sm" style="padding:4px 8px; border-radius:6px;" title="Delete Category">
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
