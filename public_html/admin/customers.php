<?php
$currentPage = 'customers';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$customers = get_json_file('customers') ?? [];
$sales = get_json_file('sales') ?? [];

// Calculate Customer Analytics
$totalCustCount = count($customers);
$totalCustSpent = 0;
$totalCustOrders = 0;
$totalCustDue = 0;

foreach ($customers as $c) {
    $totalCustSpent += floatval($c['totalSpent'] ?? 0);
    $totalCustOrders += intval($c['totalPurchases'] ?? 0);
    $totalCustDue += floatval($c['balance'] ?? 0);
}

// Handle POST actions: Add Customer or Delete Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add_customer';

    if ($action === 'delete_customer') {
        $delId = $_POST['customer_id'] ?? '';
        if ($delId) {
            $customers = array_values(array_filter($customers, function($c) use ($delId) {
                return ($c['id'] ?? '') !== $delId;
            }));
            save_json_file('customers', $customers);
            
            // Delete from MySQL
            try {
                $pdo = get_db_connection();
                if ($pdo) {
                    $stmt = $pdo->prepare("DELETE FROM `customers` WHERE `id` = ?");
                    $stmt->execute([$delId]);
                }
            } catch (Exception $e) {}

            echo "<script>alert('Customer record deleted successfully.'); window.location.href='customers.php';</script>";
            exit();
        }
    } elseif ($action === 'add_customer') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!empty($name)) {
            $newCust = [
                'id' => 'cust-' . time(),
                'name' => $name,
                'phone' => $phone,
                'email' => trim($_POST['email'] ?? ''),
                'totalPurchases' => 0,
                'totalSpent' => 0,
                'balance' => floatval($_POST['balance'] ?? 0),
                'status' => 'active'
            ];
            $customers[] = $newCust;
            save_json_file('customers', $customers);

            // Insert into MySQL
            try {
                $pdo = get_db_connection();
                if ($pdo) {
                    $stmt = $pdo->prepare("INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `totalPurchases`, `totalSpent`, `balance`, `status`) VALUES (?, ?, ?, ?, 0, 0, ?, 'active')");
                    $stmt->execute([$newCust['id'], $newCust['name'], $newCust['phone'], $newCust['email'], $newCust['balance']]);
                }
            } catch (Exception $e) {}

            echo "<script>alert('Customer created successfully.'); window.location.href='customers.php';</script>";
            exit();
        }
    }
}
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content" style="padding:20px; box-sizing:border-box;">
        
        <!-- Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
            <div>
                <h1 style="font-family:var(--pos-font-heading); font-size:1.6rem; font-weight:900; color:var(--pos-text); margin:0; display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-users" style="color:var(--pos-red);"></i> Customer Accounts Directory
                </h1>
                <p style="color:var(--pos-text-muted); font-size:0.85rem; margin:4px 0 0 0;">
                    Search existing customer profiles, view purchase history & invoices, and manage ledger balances
                </p>
            </div>

            <div style="display:flex; gap:8px;">
                <a href="pos.php" class="pos-btn pos-btn-primary" style="font-weight:800; text-decoration:none;">
                    <i class="fa-solid fa-cart-shopping"></i> Open POS Terminal
                </a>
            </div>
        </div>

        <!-- 3 KPI Cards -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:22px;">
            <!-- Card 1 -->
            <div class="pos-card" style="padding:16px; border-left:4px solid var(--pos-red); display:flex; align-items:center; gap:14px;">
                <div style="background:rgba(215,25,32,0.1); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--pos-red); font-size:1.4rem;">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800;">Registered Customers</div>
                    <div style="font-size:1.45rem; font-weight:900; color:var(--pos-text);"><?php echo $totalCustCount; ?></div>
                    <div style="font-size:0.7rem; color:var(--pos-text-muted); font-weight:700;"><?php echo $totalCustOrders; ?> Total Invoices</div>
                </div>
            </div>

            <!-- Card 2 -->
            <div class="pos-card" style="padding:16px; border-left:4px solid #10b981; display:flex; align-items:center; gap:14px;">
                <div style="background:rgba(16,185,129,0.1); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#059669; font-size:1.4rem;">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800;">Customer Total Spent</div>
                    <div style="font-size:1.45rem; font-weight:900; color:#059669;">PKR <?php echo number_format($totalCustSpent); ?></div>
                    <div style="font-size:0.7rem; color:var(--pos-text-muted); font-weight:700;">Customer Lifetime Revenue</div>
                </div>
            </div>

            <!-- Card 3 -->
            <div class="pos-card" style="padding:16px; border-left:4px solid #f59e0b; display:flex; align-items:center; gap:14px;">
                <div style="background:rgba(245,158,11,0.1); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#d97706; font-size:1.4rem;">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--pos-text-muted); text-transform:uppercase; font-weight:800;">Outstanding Credit Balance</div>
                    <div style="font-size:1.45rem; font-weight:900; color:#d97706;">PKR <?php echo number_format($totalCustDue); ?></div>
                    <div style="font-size:0.7rem; color:#d97706; font-weight:700;">Customer Credit Dues</div>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 2.4fr; gap:20px; align-items:start;">
            
            <!-- Column 1: Add New Customer Form -->
            <div class="pos-card" style="padding:18px;">
                <h3 class="pos-card-title" style="margin-bottom:14px; font-size:1.05rem;">
                    <i class="fa-solid fa-user-plus" style="color:var(--pos-red);"></i> Register New Customer
                </h3>
                <form method="POST" action="customers.php">
                    <input type="hidden" name="action" value="add_customer">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label class="form-label" style="font-size:0.78rem; font-weight:800;">Customer Full Name *</label>
                        <input type="text" name="name" class="form-input" placeholder="e.g. Ahmad Khan" required style="font-size:0.82rem;">
                    </div>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label class="form-label" style="font-size:0.78rem; font-weight:800;">Mobile Phone *</label>
                        <input type="text" name="phone" class="form-input" placeholder="e.g. 0300 1234567" required style="font-size:0.82rem;">
                    </div>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label class="form-label" style="font-size:0.78rem; font-weight:800;">Email Address (Optional)</label>
                        <input type="email" name="email" class="form-input" placeholder="e.g. ahmad@example.com" style="font-size:0.82rem;">
                    </div>
                    <div class="form-group" style="margin-bottom:14px;">
                        <label class="form-label" style="font-size:0.78rem; font-weight:800;">Opening Credit Balance (PKR)</label>
                        <input type="number" name="balance" class="form-input" placeholder="0" value="0" style="font-size:0.82rem;">
                    </div>
                    <button type="submit" class="pos-btn pos-btn-primary pos-btn-block" style="font-weight:800;">
                        <i class="fa-solid fa-user-plus"></i> Save Customer Profile
                    </button>
                </form>
            </div>

            <!-- Column 2: Searchable Customer Accounts Table -->
            <div class="pos-card" style="padding:16px;">
                
                <!-- Search & Filter Bar -->
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
                    <div style="position:relative; flex:1; min-width:220px;">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--pos-text-muted); font-size:0.85rem;"></i>
                        <input type="text" id="custSearchInput" class="form-input" placeholder="Search customer by name, phone, or email..." oninput="filterCustomerList()" style="padding-left:30px; margin:0; font-size:0.85rem; width:100%;">
                    </div>

                    <select id="custBalanceFilter" class="form-select" onchange="filterCustomerList()" style="margin:0; font-size:0.82rem; padding:6px 10px; width:150px;">
                        <option value="all">All Balances</option>
                        <option value="due">Has Balance Due</option>
                        <option value="clear">Balance Clear</option>
                    </select>
                </div>

                <div class="data-table-wrap" style="border:1px solid var(--pos-border); border-radius:8px; overflow:hidden;">
                    <table class="data-table" id="customersTable" style="margin:0; font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Balance Due</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): 
                                $bal = floatval($c['balance'] ?? 0);
                            ?>
                                <tr class="cust-row"
                                    data-name="<?php echo strtolower($c['name'] ?? ''); ?>"
                                    data-phone="<?php echo strtolower($c['phone'] ?? ''); ?>"
                                    data-email="<?php echo strtolower($c['email'] ?? ''); ?>"
                                    data-balance="<?php echo $bal > 0 ? 'due' : 'clear'; ?>">
                                    
                                    <td>
                                        <strong style="color:var(--pos-text); font-size:0.88rem;"><?php echo htmlspecialchars($c['name']); ?></strong>
                                        <?php if (!empty($c['email'])): ?>
                                            <div style="font-size:0.7rem; color:var(--pos-text-muted);"><?php echo htmlspecialchars($c['email']); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <strong><?php echo htmlspecialchars($c['phone'] ?: '-'); ?></strong>
                                    </td>

                                    <td>
                                        <span class="status-badge" style="background:#f1f5f9; color:#0284c7; border:1px solid #bae6fd; font-weight:800; font-size:0.72rem;">
                                            <?php echo intval($c['totalPurchases'] ?? 0); ?> Orders
                                        </span>
                                    </td>

                                    <td>
                                        <strong style="color:#059669; font-size:0.9rem;">PKR <?php echo number_format($c['totalSpent'] ?? 0); ?></strong>
                                    </td>

                                    <td>
                                        <?php if ($bal > 0): ?>
                                            <span class="status-badge status-pending" style="font-size:0.7rem; font-weight:800;">
                                                PKR <?php echo number_format($bal); ?> DUE
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-active" style="font-size:0.7rem;">
                                                CLEAR
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td style="text-align:right; white-space:nowrap;">
                                        <div style="display:inline-flex; gap:4px; align-items:center;">
                                            <!-- Start POS Sale Button -->
                                            <a href="pos.php?cust_name=<?php echo urlencode($c['name']); ?>&cust_phone=<?php echo urlencode($c['phone'] ?? ''); ?>" class="pos-btn pos-btn-sm" style="background:#dc2626; color:#fff; padding:3px 7px; font-size:0.7rem; font-weight:800; text-decoration:none;" title="Start POS Sale for this customer">
                                                <i class="fa-solid fa-cart-plus"></i> POS
                                            </a>

                                            <!-- View Invoices History Button -->
                                            <button type="button" class="pos-btn pos-btn-outline pos-btn-sm" onclick="viewCustomerHistory('<?php echo htmlspecialchars($c['id'], ENT_QUOTES); ?>')" style="padding:3px 6px; font-size:0.7rem;" title="View Purchase Invoices History">
                                                <i class="fa-solid fa-receipt"></i>
                                            </button>

                                            <!-- Delete Customer -->
                                            <form method="POST" action="customers.php" onsubmit="return confirm('Are you sure you want to delete customer <?php echo htmlspecialchars(addslashes($c['name'])); ?>?');" style="display:inline; margin:0;">
                                                <input type="hidden" name="action" value="delete_customer">
                                                <input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($c['id']); ?>">
                                                <button type="submit" class="pos-btn pos-btn-outline pos-btn-sm" style="color:#ef4444; border-color:#fecaca; padding:3px 6px; font-size:0.7rem;" title="Delete Customer">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customer Invoices History Modal -->
<div class="pos-modal-overlay" id="customerHistoryModal" style="display:none; z-index:99999;">
    <div class="pos-modal" style="max-width:600px; padding:20px; max-height:90vh; overflow-y:auto;">
        <div class="pos-modal-header" style="border-bottom:1.5px solid var(--pos-border); padding-bottom:10px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
            <h3 class="pos-modal-title" style="margin:0; font-size:1.15rem; color:var(--pos-text); display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-user-clock" style="color:var(--pos-red);"></i> Customer Invoices & Ledger History
            </h3>
            <button class="pos-modal-close" onclick="closeCustomerHistoryModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-size:1.05rem; font-weight:900; color:#0f172a;" id="histCustName">Customer Name</div>
                <div style="font-size:0.78rem; color:#64748b;" id="histCustPhone">Phone</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:0.72rem; color:#64748b; text-transform:uppercase; font-weight:800;">Lifetime Spent</div>
                <div style="font-size:1.1rem; font-weight:900; color:#059669;" id="histCustSpent">PKR 0</div>
            </div>
        </div>

        <h4 style="font-size:0.88rem; font-weight:800; color:var(--pos-text); margin:0 0 8px 0;">
            <i class="fa-solid fa-receipt"></i> Associated Sale Invoices:
        </h4>
        <div id="histInvoicesContainer" style="max-height:280px; overflow-y:auto; border:1px solid var(--pos-border); border-radius:6px; margin-bottom:16px;">
            <!-- Loaded by JS -->
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center;">
            <a href="#" id="histStartPosBtn" class="pos-btn pos-btn-primary pos-btn-sm" style="font-weight:800; text-decoration:none;">
                <i class="fa-solid fa-cart-shopping"></i> Start New POS Sale
            </a>
            <button type="button" class="pos-btn pos-btn-outline" onclick="closeCustomerHistoryModal()">Close</button>
        </div>
    </div>
</div>

<script>
function filterCustomerList() {
    const q = document.getElementById('custSearchInput').value.toLowerCase().trim();
    const balFilter = document.getElementById('custBalanceFilter').value;
    const rows = document.querySelectorAll('.cust-row');

    rows.forEach(r => {
        const name = r.getAttribute('data-name') || '';
        const phone = r.getAttribute('data-phone') || '';
        const email = r.getAttribute('data-email') || '';
        const bal = r.getAttribute('data-balance') || '';

        const matchesQ = !q || name.includes(q) || phone.includes(q) || email.includes(q);
        const matchesBal = (balFilter === 'all') || (bal === balFilter);

        if (matchesQ && matchesBal) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}

window.allCustomersList = <?php echo json_encode($customers); ?> || [];

function viewCustomerHistory(cust) {
    if (typeof cust === 'string') {
        cust = (window.allCustomersList || []).find(c => String(c.id) === String(cust) || String(c.name) === String(cust)) || { id: cust, name: cust };
    }
    if (!cust) return;
    const modal = document.getElementById('customerHistoryModal');
    if (!modal) return;

    document.getElementById('histCustName').textContent = cust.name || 'Customer';
    document.getElementById('histCustPhone').textContent = '📞 ' + (cust.phone || 'No phone recorded');
    document.getElementById('histCustSpent').textContent = 'PKR ' + Number(cust.totalSpent || 0).toLocaleString();
    document.getElementById('histStartPosBtn').href = `pos.php?cust_name=${encodeURIComponent(cust.name)}&cust_phone=${encodeURIComponent(cust.phone || '')}`;

    const container = document.getElementById('histInvoicesContainer');
    container.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;"><i class="fa-solid fa-spinner fa-spin"></i> Loading invoice history...</div>';
    modal.style.display = 'flex';

    fetch(`../backend/customers.php?id=${encodeURIComponent(cust.id || cust.name)}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                const invoices = res.data.invoices || [];
                if (invoices.length === 0) {
                    container.innerHTML = '<div style="text-align:center; padding:20px; color:#94a3b8; font-size:0.85rem;">No invoices found for this customer yet.</div>';
                    return;
                }

                let html = '<table style="width:100%; border-collapse:collapse; font-size:0.8rem;">';
                html += '<tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; font-weight:800; font-size:0.72rem; color:#475569;"><th style="padding:6px 8px; text-align:left;">Invoice #</th><th style="padding:6px 8px; text-align:left;">Date</th><th style="padding:6px 8px; text-align:right;">Amount</th><th style="padding:6px 8px; text-align:center;">Action</th></tr>';
                
                invoices.forEach(inv => {
                    html += `
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:6px 8px; font-weight:800; color:var(--pos-red); font-family:monospace;">${inv.invoiceNo}</td>
                            <td style="padding:6px 8px; color:#64748b;">${new Date(inv.createdAt).toLocaleDateString()}</td>
                            <td style="padding:6px 8px; text-align:right; font-weight:800; color:#059669;">PKR ${Number(inv.total).toLocaleString()}</td>
                            <td style="padding:6px 8px; text-align:center;">
                                <a href="sales.php" class="pos-btn pos-btn-sm pos-btn-outline" style="padding:2px 5px; font-size:0.68rem; text-decoration:none;">View in Sales</a>
                            </td>
                        </tr>
                    `;
                });
                html += '</table>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div style="padding:15px; color:red; text-align:center;">Error loading history.</div>';
            }
        })
        .catch(() => {
            container.innerHTML = '<div style="padding:15px; color:red; text-align:center;">Network error loading history.</div>';
        });
}

function closeCustomerHistoryModal() {
    const modal = document.getElementById('customerHistoryModal');
    if (modal) modal.style.display = 'none';
}

// Auto search if ?search= is in URL
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const s = params.get('search');
    if (s) {
        document.getElementById('custSearchInput').value = s;
        filterCustomerList();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
