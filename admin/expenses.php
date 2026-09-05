<?php
$currentPage = 'expenses';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$expenses = get_json_file('expenses') ?? [];
$currentUser = get_session_user();
$isSuperAdmin = ($currentUser['role'] ?? '') === 'super_admin';

// Handle Delete Expense
if (isset($_GET['delete_id'])) {
    $delId = $_GET['delete_id'];
    $filtered = array_values(array_filter($expenses, function($e) use ($delId) {
        return ($e['id'] ?? '') !== $delId;
    }));
    save_json_file('expenses', $filtered);
    SecurityLogger::logEvent($currentUser['username'] ?? 'admin', 'super_admin', 'EXPENSE_DELETED', "Deleted expense ID {$delId}");
    header('Location: expenses.php?deleted=1');
    exit();
}

// Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $vendorShop = trim($_POST['vendor_shop'] ?? '');
    $itemDetails = trim($_POST['item_details'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!empty($category) && $amount > 0) {
        $expenses[] = [
            'id' => 'exp-' . time() . '-' . rand(100, 999),
            'category' => $category,
            'vendor_shop' => $vendorShop,
            'item_details' => $itemDetails,
            'amount' => $amount,
            'date' => $_POST['date'] ?? date('Y-m-d'),
            'notes' => $notes,
            'recordedBy' => $currentUser['name'] ?? $currentUser['username'] ?? 'Admin',
            'createdAt' => date('c')
        ];
        save_json_file('expenses', $expenses);
        
        $vendorMsg = $vendorShop ? " from '{$vendorShop}'" : "";
        $itemMsg = $itemDetails ? " for '{$itemDetails}'" : "";
        SecurityLogger::logEvent($currentUser['username'] ?? 'admin', 'super_admin', 'EXPENSE_RECORDED', "Recorded PKR {$amount} expense for {$category}{$vendorMsg}{$itemMsg}");
        
        header('Location: expenses.php?success=1');
        exit();
    }
}

// Calculate Summary Statistics
$totalAllExpenses = 0;
$currentMonthExpenses = 0;
$otherExpensesTotal = 0;
$currentMonth = date('Y-m');

foreach ($expenses as $e) {
    $amt = floatval($e['amount'] ?? 0);
    $totalAllExpenses += $amt;
    $d = substr($e['date'] ?? '', 0, 7);
    if ($d === $currentMonth) {
        $currentMonthExpenses += $amt;
    }
    if (strtolower($e['category'] ?? '') === 'other') {
        $otherExpensesTotal += $amt;
    }
}
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content">
        <div class="page-header" style="margin-bottom:20px;">
            <div>
                <h1><i class="fa-solid fa-file-invoice-dollar" style="color:var(--pos-red); margin-right:10px;"></i> Operational Expenses Tracker</h1>
                <p class="page-header-sub">Log store rent, electricity bills, staff salaries, shop purchases &amp; miscellaneous expenses</p>
            </div>
        </div>

        <!-- 3 Summary Metric Cards -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:22px;">
            <div class="pos-card" style="padding:16px; border-left:4px solid var(--pos-red); display:flex; align-items:center; gap:14px;">
                <div style="background:rgba(215,25,32,0.1); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--pos-red); font-size:1.4rem;">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800;">This Month (<?php echo date('M Y'); ?>)</div>
                    <div style="font-size:1.45rem; font-weight:900; color:var(--pos-red);">PKR <?php echo number_format($currentMonthExpenses); ?></div>
                    <div style="font-size:0.7rem; color:var(--pos-text-muted); font-weight:700;">Active Month Overheads</div>
                </div>
            </div>

            <div class="pos-card" style="padding:16px; border-left:4px solid #f59e0b; display:flex; align-items:center; gap:14px;">
                <div style="background:rgba(245,158,11,0.1); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#d97706; font-size:1.4rem;">
                    <i class="fa-solid fa-store"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800;">Other / Misc Purchases</div>
                    <div style="font-size:1.45rem; font-weight:900; color:#d97706;">PKR <?php echo number_format($otherExpensesTotal); ?></div>
                    <div style="font-size:0.7rem; color:#d97706; font-weight:700;">Shop items &amp; hardware supplies</div>
                </div>
            </div>

            <div class="pos-card" style="padding:16px; border-left:4px solid #3b82f6; display:flex; align-items:center; gap:14px;">
                <div style="background:rgba(59,130,246,0.1); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#2563eb; font-size:1.4rem;">
                    <i class="fa-solid fa-coins"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800;">All-Time Expenses</div>
                    <div style="font-size:1.45rem; font-weight:900; color:var(--pos-text);">PKR <?php echo number_format($totalAllExpenses); ?></div>
                    <div style="font-size:0.7rem; color:var(--pos-text-muted); font-weight:700;"><?php echo count($expenses); ?> Total Recorded Entries</div>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:24px; align-items:start;">
            
            <!-- Log New Expense Form Card -->
            <div class="pos-card" style="padding:20px; border-radius:14px;">
                <h3 class="pos-card-title" style="margin-bottom:14px; font-size:1.1rem; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-receipt" style="color:var(--pos-red);"></i> Log New Expense
                </h3>

                <form method="POST" action="expenses.php" id="expenseForm">
                    <!-- Category Selection -->
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label" style="font-size:0.75rem; font-weight:800;">Expense Category *</label>
                        <select name="category" id="expenseCategorySelect" class="form-select" required onchange="handleCategoryChange(this.value)" style="font-weight:700;">
                            <option value="Shop Rent">Shop Rent</option>
                            <option value="Electricity Bill">Electricity Bill</option>
                            <option value="Staff Salaries">Staff Salaries</option>
                            <option value="Inventory Stock Purchase">Inventory Stock Purchase</option>
                            <option value="Maintenance & Repairs">Maintenance & Repairs</option>
                            <option value="Tea & Refreshments">Tea & Refreshments</option>
                            <option value="Other">Other / Miscellaneous (Shop Items, Supplies, etc.)</option>
                        </select>
                    </div>

                    <!-- Dynamic 'Other' / Shop & Item Details Box -->
                    <div id="otherExpenseFields" style="display:none; background:#fffbeb; border:1.5px solid #fed7aa; border-radius:10px; padding:12px; margin-bottom:14px; animation:fadeIn 0.25s ease;">
                        <div style="font-size:0.72rem; font-weight:800; color:#92400e; margin-bottom:8px; text-transform:uppercase; display:flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-store" style="color:#d97706;"></i> Shop / Vendor &amp; Item Details:
                        </div>
                        
                        <div style="margin-bottom:8px;">
                            <label class="form-label" style="font-size:0.72rem; font-weight:800; color:#78350f;">Name of Shop / Vendor / Person *</label>
                            <input type="text" name="vendor_shop" id="vendorShopInput" class="form-input" placeholder="e.g. Al-Madina Hardware, Electric Center, Stationers" style="font-size:0.85rem; background:#ffffff;">
                        </div>

                        <div>
                            <label class="form-label" style="font-size:0.72rem; font-weight:800; color:#78350f;">What Thing / Item(s) Brought *</label>
                            <input type="text" name="item_details" id="itemDetailsInput" class="form-input" placeholder="e.g. Extension boards, light bulbs, thermal receipt rolls, cleaning mops" style="font-size:0.85rem; background:#ffffff;">
                        </div>
                    </div>

                    <!-- Amount -->
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label" style="font-size:0.75rem; font-weight:800;">Amount (PKR) *</label>
                        <input type="number" name="amount" class="form-input" placeholder="e.g. 15000" min="1" step="1" required style="font-size:1rem; font-weight:800; color:#dc2626;">
                    </div>

                    <!-- Expense Date -->
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label" style="font-size:0.75rem; font-weight:800;">Expense Date</label>
                        <input type="date" name="date" class="form-input" value="<?php echo date('Y-m-d'); ?>" style="font-size:0.85rem;">
                    </div>

                    <!-- Notes / Description -->
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label" style="font-size:0.75rem; font-weight:800;">Additional Notes / Description</label>
                        <input type="text" name="notes" class="form-input" placeholder="e.g. Paid via Cash by Safdar" style="font-size:0.85rem;">
                    </div>

                    <button type="submit" class="pos-btn pos-btn-primary pos-btn-block" style="padding:12px; font-weight:800; font-size:0.95rem;">
                        <i class="fa-solid fa-plus-circle"></i> + Record Expense
                    </button>
                </form>
            </div>

            <!-- Expenses Data Table Card -->
            <div class="data-table-wrap pos-card" style="padding:0; overflow:hidden; border-radius:14px;">
                <div style="padding:14px 18px; border-bottom:1px solid var(--pos-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <strong style="font-size:0.95rem; color:var(--pos-text);">
                        <i class="fa-solid fa-list-check" style="color:var(--pos-red);"></i> Expense Records History (<?php echo count($expenses); ?>)
                    </strong>
                    <input type="text" id="expenseSearchInput" class="form-input" placeholder="Filter by category, shop, item..." onkeyup="filterExpensesTable()" style="margin:0; width:220px; font-size:0.8rem; padding:6px 10px;">
                </div>

                <div style="overflow-x:auto;">
                    <table class="data-table" id="expensesTable" style="margin:0; width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8fafc; border-bottom:1px solid var(--pos-border);">
                                <th style="padding:10px 12px;">Category</th>
                                <th style="padding:10px 12px;">Shop / Vendor</th>
                                <th style="padding:10px 12px;">Item Details &amp; Notes</th>
                                <th style="padding:10px 12px;">Amount</th>
                                <th style="padding:10px 12px;">Date</th>
                                <th style="padding:10px 12px; text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expenses)): ?>
                                <tr id="emptyExpenseRow"><td colspan="6" style="text-align:center; padding:35px; color:var(--pos-text-muted);">No expenses recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach (array_reverse($expenses) as $e): 
                                    $cat = $e['category'] ?? 'Other';
                                    $isOther = (strtolower($cat) === 'other');
                                    $vendor = $e['vendor_shop'] ?? '';
                                    $items = $e['item_details'] ?? '';
                                    $notes = $e['notes'] ?? '';
                                ?>
                                    <tr class="expense-row" 
                                        data-cat="<?php echo strtolower($cat); ?>"
                                        data-vendor="<?php echo strtolower($vendor); ?>"
                                        data-item="<?php echo strtolower($items); ?>"
                                        data-notes="<?php echo strtolower($notes); ?>"
                                        style="border-bottom:1px solid #f1f5f9;">
                                        
                                        <td style="padding:10px 12px;">
                                            <?php if ($isOther): ?>
                                                <span class="status-badge" style="background:#fef3c7; color:#b45309; font-weight:800; font-size:0.72rem;">
                                                    <i class="fa-solid fa-store"></i> OTHER / MISC
                                                </span>
                                            <?php else: ?>
                                                <strong style="color:var(--pos-text); font-size:0.85rem;"><?php echo htmlspecialchars($cat); ?></strong>
                                            <?php endif; ?>
                                        </td>

                                        <td style="padding:10px 12px;">
                                            <?php if (!empty($vendor)): ?>
                                                <strong style="color:#0f172a; font-size:0.85rem; display:flex; align-items:center; gap:5px;">
                                                    <i class="fa-solid fa-shop" style="color:#d97706; font-size:0.75rem;"></i>
                                                    <?php echo htmlspecialchars($vendor); ?>
                                                </strong>
                                            <?php else: ?>
                                                <span style="color:#94a3b8; font-size:0.75rem;">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td style="padding:10px 12px;">
                                            <?php if (!empty($items)): ?>
                                                <div style="font-weight:700; color:#1e293b; font-size:0.82rem;">
                                                    <i class="fa-solid fa-bag-shopping" style="color:var(--pos-red); font-size:0.75rem;"></i>
                                                    <?php echo htmlspecialchars($items); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($notes)): ?>
                                                <div style="font-size:0.72rem; color:#64748b; margin-top:2px;">
                                                    <?php echo htmlspecialchars($notes); ?>
                                                </div>
                                            <?php elseif (empty($items)): ?>
                                                <span style="color:#94a3b8; font-size:0.75rem;">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td style="padding:10px 12px;">
                                            <strong style="color:var(--pos-danger); font-size:0.92rem; font-family:monospace;">
                                                PKR <?php echo number_format($e['amount'] ?? 0); ?>
                                            </strong>
                                        </td>

                                        <td style="padding:10px 12px; font-size:0.8rem; color:#475569;">
                                            <?php echo date('M d, Y', strtotime($e['date'] ?? 'now')); ?>
                                        </td>

                                        <td style="padding:10px 12px; text-align:right;">
                                            <a href="expenses.php?delete_id=<?php echo urlencode($e['id'] ?? ''); ?>" 
                                               onclick="return confirm('Are you sure you want to delete this expense record?');" 
                                               class="pos-btn pos-btn-outline pos-btn-sm" 
                                               title="Delete Expense Record" 
                                               style="padding:3px 7px; font-size:0.7rem; color:#dc2626; border-color:#fecaca;">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
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
</div>

<script>
function handleCategoryChange(val) {
    const otherBox = document.getElementById('otherExpenseFields');
    const vendorInput = document.getElementById('vendorShopInput');
    const itemInput = document.getElementById('itemDetailsInput');

    if (val === 'Other') {
        otherBox.style.display = 'block';
        if (vendorInput) vendorInput.focus();
    } else {
        otherBox.style.display = 'none';
    }
}

function filterExpensesTable() {
    const q = document.getElementById('expenseSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.expense-row');
    let visible = 0;

    rows.forEach(r => {
        const cat = r.getAttribute('data-cat') || '';
        const vendor = r.getAttribute('data-vendor') || '';
        const item = r.getAttribute('data-item') || '';
        const notes = r.getAttribute('data-notes') || '';

        const match = !q || cat.includes(q) || vendor.includes(q) || item.includes(q) || notes.includes(q);

        if (match) {
            r.style.display = '';
            visible++;
        } else {
            r.style.display = 'none';
        }
    });

    const empty = document.getElementById('emptyExpenseRow');
    if (empty) empty.style.display = visible === 0 ? '' : 'none';
}

// Auto-filter expenses if URL has ?search=... or ?q=...
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const search = urlParams.get('search') || urlParams.get('q');
    if (search && document.getElementById('expenseSearchInput')) {
        document.getElementById('expenseSearchInput').value = search;
        filterExpensesTable();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
