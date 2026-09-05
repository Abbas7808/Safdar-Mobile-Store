<?php
$currentPage = 'payments';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$customers = get_json_file('customers') ?? [];
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $custName = trim($_POST['customer_name'] ?? '');
    $payAmount = floatval($_POST['payment_amount'] ?? 0);

    if (!empty($custName) && $payAmount > 0) {
        $updated = false;
        foreach ($customers as &$c) {
            if ($c['name'] === $custName) {
                $oldBal = floatval($c['balance'] ?? 0);
                $c['balance'] = max(0, $oldBal - $payAmount);
                $updated = true;

                // Log audit
                $audit = get_json_file('audit') ?? [];
                $sessionUser = get_session_user();
                $audit[] = [
                    'id' => 'aud-' . time(),
                    'user' => $sessionUser['username'] ?? 'admin',
                    'action' => 'DEBT_REPAYMENT',
                    'details' => "Recorded payment of PKR " . number_format($payAmount) . " for {$custName}. Remaining balance: PKR " . number_format($c['balance']),
                    'timestamp' => date('c')
                ];
                save_json_file('audit', $audit);

                // Add real-time notification
                add_notification(
                    'payment',
                    '💳 Payment Received!',
                    'Received payment of PKR ' . number_format($payAmount) . ' for ' . $custName . '. Remaining balance: PKR ' . number_format($c['balance']),
                    $payAmount,
                    $custName
                );
                break;
            }
        }

        if ($updated) {
            save_json_file('customers', $customers);
            $msg = "Payment of PKR " . number_format($payAmount) . " recorded successfully for '{$custName}'!";
            $msgType = 'success';
        }
    }
}

$debtors = array_filter($customers, function($c) {
    return floatval($c['balance'] ?? 0) > 0;
});
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content">
        <div class="page-header">
            <div>
                <h1><i class="fa-solid fa-money-check-dollar" style="color:var(--pos-red); margin-right:10px;"></i> Debts & Customer Ledgers</h1>
                <p class="page-header-sub">Track pending customer dues, repayments, and outstanding balances</p>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="login-error" style="margin-bottom:20px; background:<?php echo $msgType === 'success' ? '#ecfdf5' : '#fef2f2'; ?>; border:1px solid <?php echo $msgType === 'success' ? '#a7f3d0' : '#fecaca'; ?>; color:<?php echo $msgType === 'success' ? '#065f46' : '#dc2626'; ?>; padding:12px 16px; border-radius:8px; font-weight:700;">
                <i class="fa-solid <?php echo $msgType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="data-table-wrap">
            <div class="data-table-toolbar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <h3 class="pos-card-title"><i class="fa-solid fa-clock" style="color:var(--pos-warning); margin-right:8px;"></i> Outstanding Customer Debts</h3>
                <div style="position:relative; width:260px; max-width:100%;">
                    <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:0.8rem;"></i>
                    <input type="text" id="debtSearchInput" class="form-input" placeholder="Search debtor name or phone..." oninput="searchDebtsTable()" style="padding-left:30px; font-size:0.82rem; height:34px;">
                </div>
            </div>

            <table class="data-table" id="debtsTable">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Phone</th>
                        <th>Outstanding Balance</th>
                        <th>Total Past Orders</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($debtors)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:30px; color:#9ca3af;">Great! No outstanding customer debts pending.</td></tr>
                    <?php else: ?>
                        <?php foreach ($debtors as $d): ?>
                            <tr class="debt-row" data-name="<?php echo strtolower(htmlspecialchars($d['name'])); ?>" data-phone="<?php echo strtolower(htmlspecialchars($d['phone'] ?? '')); ?>">
                                <td><strong><?php echo htmlspecialchars($d['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($d['phone']); ?></td>
                                <td><strong style="color:var(--pos-danger); font-size:1.1rem;">PKR <?php echo number_format($d['balance']); ?></strong></td>
                                <td><?php echo intval($d['totalPurchases'] ?? 0); ?> Orders</td>
                                <td style="text-align:right;">
                                    <button class="pos-btn pos-btn-success pos-btn-sm" onclick="promptDebtPayment('<?php echo htmlspecialchars($d['name']); ?>', <?php echo floatval($d['balance']); ?>)">
                                        <i class="fa-solid fa-hand-holding-dollar"></i> Record Payment
                                    </button>
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
function searchDebtsTable() {
    const q = (document.getElementById('debtSearchInput').value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#debtsTable tbody tr.debt-row');
    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        r.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
}

function promptDebtPayment(custName, currentBal) {
    const amountStr = prompt("Record Repayment for " + custName + "\nCurrent Balance: PKR " + Number(currentBal).toLocaleString() + "\nEnter Amount Received (PKR):");
    const amount = parseFloat(amountStr);
    if (amount && amount > 0) {
        const form = document.createElement("form");
        form.method = "POST";
        form.action = "payments.php";

        const nameInput = document.createElement("input");
        nameInput.type = "hidden";
        nameInput.name = "customer_name";
        nameInput.value = custName;
        form.appendChild(nameInput);

        const amtInput = document.createElement("input");
        amtInput.type = "hidden";
        amtInput.name = "payment_amount";
        amtInput.value = amount;
        form.appendChild(amtInput);

        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-filter debts if URL has ?search=... or ?customer=...
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const search = urlParams.get('customer') || urlParams.get('search') || urlParams.get('q');
    if (search && document.getElementById('debtSearchInput')) {
        document.getElementById('debtSearchInput').value = search;
        searchDebtsTable();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
