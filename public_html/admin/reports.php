<?php
$currentPage = 'reports';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$sales = get_json_file('sales') ?? [];
$expenses = get_json_file('expenses') ?? [];
$services = get_json_file('services') ?? [];

// Calculate overall financial totals
$totalRevenue = 0;
$cogs = 0;
$salesCount = 0;

// Daily, Weekly, Monthly breakdown
$todayDate = date('Y-m-d');
$currentMonth = date('Y-m');
$currentYear = date('Y');
$sevenDaysAgo = date('Y-m-d', strtotime('-6 days'));

$todayRevenue = 0; $todayCount = 0;
$weeklyRevenue = 0; $weeklyCount = 0;
$monthlyRevenue = 0; $monthlyCount = 0;
$monthlyCogs = 0; $monthlyProfit = 0;

$weekDaysMap = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $dayName = date('D', strtotime($d));
    $weekDaysMap[$d] = [
        'label' => $dayName . ' (' . date('M d', strtotime($d)) . ')',
        'revenue' => 0,
        'count' => 0
    ];
}

// Initialize 12-Month Map
$yearMonthsMap = [];
for ($m = 1; $m <= 12; $m++) {
    $mKey = $currentYear . '-' . sprintf('%02d', $m);
    $mLabel = date('M', mktime(0, 0, 0, $m, 1, (int)$currentYear));
    $yearMonthsMap[$mKey] = [
        'label' => $mLabel,
        'revenue' => 0,
        'cogs' => 0,
        'profit' => 0,
        'count' => 0
    ];
}

foreach ($sales as $s) {
    if (($s['status'] ?? 'completed') !== 'completed') continue;

    $sTotal = floatval($s['total'] ?? 0);
    $sCogs = floatval($s['cogs'] ?? 0);
    $sProfit = floatval($s['profit'] ?? ($sTotal - $sCogs));
    $sDate = substr($s['createdAt'] ?? '', 0, 10);
    $sMonth = substr($s['createdAt'] ?? '', 0, 7);

    $totalRevenue += $sTotal;
    $cogs += $sCogs;
    $salesCount++;

    // Today
    if ($sDate === $todayDate) {
        $todayRevenue += $sTotal;
        $todayCount++;
    }

    // Weekly
    if ($sDate >= $sevenDaysAgo && $sDate <= $todayDate) {
        $weeklyRevenue += $sTotal;
        $weeklyCount++;
        if (isset($weekDaysMap[$sDate])) {
            $weekDaysMap[$sDate]['revenue'] += $sTotal;
            $weekDaysMap[$sDate]['count']++;
        }
    }

    // Monthly
    if ($sMonth === $currentMonth) {
        $monthlyRevenue += $sTotal;
        $monthlyCogs += $sCogs;
        $monthlyProfit += $sProfit;
        $monthlyCount++;
    }

    // 12-Month Map
    if (isset($yearMonthsMap[$sMonth])) {
        $yearMonthsMap[$sMonth]['revenue'] += $sTotal;
        $yearMonthsMap[$sMonth]['cogs'] += $sCogs;
        $yearMonthsMap[$sMonth]['profit'] += $sProfit;
        $yearMonthsMap[$sMonth]['count']++;
    }
}

$grossProfit = $totalRevenue - $cogs;

$serviceCommissions = 0;
foreach ($services as $srv) {
    $serviceCommissions += floatval($srv['commission'] ?? 0);
}

$totalExpenses = 0;
$monthlyExpenses = 0;
foreach ($expenses as $e) {
    $amt = floatval($e['amount'] ?? 0);
    $totalExpenses += $amt;
    $eMonth = substr($e['date'] ?? $e['createdAt'] ?? '', 0, 7);
    if ($eMonth === $currentMonth) {
        $monthlyExpenses += $amt;
    }
}

$netProfit = $grossProfit + $serviceCommissions - $totalExpenses;
$monthlyNetProfit = $monthlyProfit - $monthlyExpenses;

$chartLabels = array_column($weekDaysMap, 'label');
$chartRevenues = array_column($weekDaysMap, 'revenue');

$yearMonthLabels = array_column($yearMonthsMap, 'label');
$yearMonthRevenues = array_column($yearMonthsMap, 'revenue');
$yearMonthProfits = array_column($yearMonthsMap, 'profit');
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content">
        <!-- Print-Only Letterhead Banner for Reports -->
        <div class="print-only-header" style="display:none;">
            <div style="display:flex; align-items:center; gap:16px;">
                <img src="../assets/images/logo.jpg" alt="Safdar Mobile Store Logo" style="width:75px; height:75px; border-radius:50%; border:2px solid #F4C430;">
                <div>
                    <h1 style="font-family:'Outfit', sans-serif; font-size:1.6rem; font-weight:900; color:#D71920; margin:0;">SAFDAR MOBILE STORE</h1>
                    <div style="font-size:0.8rem; font-weight:800; color:#334155;">Mobiles, Accessories, CCTV Security & Digital Services</div>
                    <div style="font-size:0.75rem; color:#64748b;">Opposite Patt Bazar Eidgah Road near Purdil Masjid Syedano Banda Road Main Bazar Hangu | Ph: 03339688007</div>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-family:'Outfit', sans-serif; font-size:1.2rem; font-weight:900; color:#D71920;">OFFICIAL SALES REPORT</div>
                <div style="font-size:0.78rem; color:#475569;">Generated: <?php echo date('M d, Y h:i A'); ?></div>
            </div>
        </div>
        <div class="page-header">
            <div>
                <h1><i class="fa-solid fa-chart-line" style="color:var(--pos-red); margin-right:10px;"></i> Business Intelligence & Sales Reports</h1>
                <p class="page-header-sub">Daily, Weekly, Monthly sales performance charts, Profit & Loss (P&L) statements</p>
            </div>
            <div style="display:flex; gap:10px;">
                <button class="pos-btn pos-btn-outline" onclick="window.exportCSVReport('monthly')">
                    <i class="fa-solid fa-calendar-days"></i> Export Monthly CSV
                </button>
                <button class="pos-btn pos-btn-outline" onclick="window.exportCSVReport('all')">
                    <i class="fa-solid fa-file-csv"></i> Export All CSV
                </button>
                <button class="pos-btn pos-btn-primary" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Print Report
                </button>
            </div>
        </div>

        <!-- Sales Summary Analytics Cards: Daily, Weekly, Monthly -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
            <div class="stat-card">
                <div class="stat-icon red"><i class="fa-solid fa-calendar-day"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Daily Sales (Today)</div>
                    <div class="stat-value">PKR <?php echo number_format($todayRevenue); ?></div>
                    <div style="font-size:0.75rem; color:#6b7280; margin-top:2px; font-weight:600;"><?php echo $todayCount; ?> Sales Today</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-chart-column"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Weekly Sales (7 Days)</div>
                    <div class="stat-value">PKR <?php echo number_format($weeklyRevenue); ?></div>
                    <div style="font-size:0.75rem; color:#059669; margin-top:2px; font-weight:600;"><?php echo $weeklyCount; ?> Sales This Week</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon gold"><i class="fa-solid fa-calendar-check"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Monthly Sales (<?php echo date('F'); ?>)</div>
                    <div class="stat-value">PKR <?php echo number_format($monthlyRevenue); ?></div>
                    <div style="font-size:0.75rem; color:#d97706; margin-top:2px; font-weight:600;"><?php echo $monthlyCount; ?> Sales This Month</div>
                </div>
            </div>
        </div>

        <!-- CHARTS SECTION: WEEKLY & 12-MONTHLY GRAPH -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap:20px; margin-bottom:24px;">
            <!-- WEEKLY SALES BAR GRAPH CHART -->
            <div class="pos-card">
                <div class="pos-card-header">
                    <h3 class="pos-card-title">
                        <i class="fa-solid fa-chart-simple" style="color:var(--pos-red); margin-right:8px;"></i> Weekly Sales Revenue (7 Days)
                    </h3>
                </div>
                <div style="height:260px; position:relative; width:100%; margin-top:16px;">
                    <canvas id="weeklyReportChart"></canvas>
                </div>
            </div>

            <!-- 12-MONTH SALES & PROFIT BAR GRAPH CHART -->
            <div class="pos-card">
                <div class="pos-card-header">
                    <h3 class="pos-card-title">
                        <i class="fa-solid fa-chart-line" style="color:var(--pos-gold-dark); margin-right:8px;"></i> 12-Month Performance Report (<?php echo $currentYear; ?>)
                    </h3>
                </div>
                <div style="height:260px; position:relative; width:100%; margin-top:16px;">
                    <canvas id="monthlyReportChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Profit & Loss Statement (P&L) -->
        <div class="pos-card" style="margin-bottom:24px;">
            <h3 style="font-family:var(--pos-font-heading); font-size:1.2rem; font-weight:900; margin-bottom:20px;">
                <i class="fa-solid fa-calculator" style="color:var(--pos-red); margin-right:8px;"></i> Profit & Loss Statement (P&L) - Overall Summary
            </h3>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
                <div style="background:#f9fafb; padding:16px; border-radius:10px; border:1px solid #e5e7eb;">
                    <div style="font-size:0.78rem; color:#6b7280; font-weight:700; text-transform:uppercase;">Gross Sales Revenue</div>
                    <div style="font-family:var(--pos-font-heading); font-size:1.5rem; font-weight:900; color:#111827; margin-top:4px;">PKR <?php echo number_format($totalRevenue); ?></div>
                    <div style="font-size:0.75rem; color:#6b7280; margin-top:2px;"><?php echo $salesCount; ?> total completed orders</div>
                </div>

                <div style="background:#f9fafb; padding:16px; border-radius:10px; border:1px solid #e5e7eb;">
                    <div style="font-size:0.78rem; color:#6b7280; font-weight:700; text-transform:uppercase;">Cost of Goods Sold (COGS)</div>
                    <div style="font-family:var(--pos-font-heading); font-size:1.5rem; font-weight:900; color:#ef4444; margin-top:4px;">-PKR <?php echo number_format($cogs); ?></div>
                    <div style="font-size:0.75rem; color:#6b7280; margin-top:2px;">Wholesale inventory cost</div>
                </div>

                <div style="background:#f9fafb; padding:16px; border-radius:10px; border:1px solid #e5e7eb;">
                    <div style="font-size:0.78rem; color:#6b7280; font-weight:700; text-transform:uppercase;">Gross Trading Profit</div>
                    <div style="font-family:var(--pos-font-heading); font-size:1.5rem; font-weight:900; color:#10b981; margin-top:4px;">PKR <?php echo number_format($grossProfit); ?></div>
                    <div style="font-size:0.75rem; color:#10b981; font-weight:700; margin-top:2px;"><?php echo $totalRevenue > 0 ? number_format(($grossProfit / $totalRevenue) * 100, 1) . '% Gross Margin' : '0% Margin'; ?></div>
                </div>

                <div style="background:#f9fafb; padding:16px; border-radius:10px; border:1px solid #e5e7eb;">
                    <div style="font-size:0.78rem; color:#6b7280; font-weight:700; text-transform:uppercase;">Services Commission</div>
                    <div style="font-family:var(--pos-font-heading); font-size:1.5rem; font-weight:900; color:#3b82f6; margin-top:4px;">+PKR <?php echo number_format($serviceCommissions); ?></div>
                    <div style="font-size:0.75rem; color:#6b7280; margin-top:2px;">Easypaisa, JazzCash, Load fees</div>
                </div>

                <div style="background:#f9fafb; padding:16px; border-radius:10px; border:1px solid #e5e7eb;">
                    <div style="font-size:0.78rem; color:#6b7280; font-weight:700; text-transform:uppercase;">Operational Expenses</div>
                    <div style="font-family:var(--pos-font-heading); font-size:1.5rem; font-weight:900; color:#ef4444; margin-top:4px;">-PKR <?php echo number_format($totalExpenses); ?></div>
                    <div style="font-size:0.75rem; color:#6b7280; margin-top:2px;">Rent, bills, staff, maintenance</div>
                </div>

                <div style="background:<?php echo $netProfit >= 0 ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)'; ?>; padding:16px; border-radius:10px; border:2px solid <?php echo $netProfit >= 0 ? '#10b981' : '#ef4444'; ?>;">
                    <div style="font-size:0.78rem; color:<?php echo $netProfit >= 0 ? '#059669' : '#dc2626'; ?>; font-weight:800; text-transform:uppercase;">NET PROFIT</div>
                    <div style="font-family:var(--pos-font-heading); font-size:1.7rem; font-weight:900; color:<?php echo $netProfit >= 0 ? '#059669' : '#dc2626'; ?>; margin-top:4px;">PKR <?php echo number_format($netProfit); ?></div>
                    <div style="font-size:0.75rem; font-weight:700; color:<?php echo $netProfit >= 0 ? '#059669' : '#dc2626'; ?>; margin-top:2px;">Final Net Profit After All Costs</div>
                </div>
        <!-- Print-Only Signature Footer -->
        <div class="print-only-signatures" style="display:none; justify-content:space-between; align-items:flex-end; margin-top:40px; padding-top:16px; border-top:1px dashed #cbd5e1; font-size:0.78rem;">
            <div style="text-align:center; width:180px;">
                <div style="height:30px;"></div>
                <div style="border-top:1px solid #94a3b8; padding-top:4px; font-weight:700;">Prepared By</div>
            </div>
            <div style="text-align:center;">
                <div style="font-weight:800; color:#D71920; font-size:0.85rem;">SAFDAR MOBILE STORE</div>
                <div style="font-size:0.7rem; color:#94a3b8;">Official Business Financial Statement</div>
            </div>
            <div style="text-align:center; width:180px;">
                <div style="height:30px;"></div>
                <div style="border-top:1px solid #94a3b8; padding-top:4px; font-weight:700;">Authorized Stamp & Seal</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Weekly Sales Chart
    const ctxWeekly = document.getElementById('weeklyReportChart')?.getContext('2d');
    if (ctxWeekly) {
        new Chart(ctxWeekly, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Revenue (PKR)',
                    data: <?php echo json_encode($chartRevenues); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.85)',
                    borderColor: '#10b981',
                    borderWidth: 2,
                    borderRadius: 8,
                    barThickness: 32
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: PKR ' + Number(context.raw).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'PKR ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. 12-Month Sales & Profit Chart
    const ctxMonthly = document.getElementById('monthlyReportChart')?.getContext('2d');
    if (ctxMonthly) {
        new Chart(ctxMonthly, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($yearMonthLabels); ?>,
                datasets: [
                    {
                        label: 'Revenue (PKR)',
                        data: <?php echo json_encode($yearMonthRevenues); ?>,
                        backgroundColor: 'rgba(244, 196, 48, 0.85)',
                        borderColor: '#d97706',
                        borderWidth: 2,
                        borderRadius: 6
                    },
                    {
                        label: 'Net Profit (PKR)',
                        data: <?php echo json_encode($yearMonthProfits); ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.85)',
                        borderColor: '#059669',
                        borderWidth: 2,
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': PKR ' + Number(context.raw).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'PKR ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
});

window.exportCSVReport = function(type) {
    let csv = "Invoice #,Customer Name,Total Amount,Payment Method,Date\n";
    let currentMonth = "<?php echo date('Y-m'); ?>";
    
    <?php foreach ($sales as $s): ?>
        let sMonth = "<?php echo substr($s['createdAt'] ?? '', 0, 7); ?>";
        if (type === 'all' || (type === 'monthly' && sMonth === currentMonth)) {
            csv += `"<?php echo $s['invoiceNo'] ?? ''; ?>","<?php echo htmlspecialchars($s['customerName'] ?? ''); ?>",<?php echo floatval($s['total'] ?? 0); ?>,"<?php echo $s['paymentMethod'] ?? ''; ?>","<?php echo $s['createdAt'] ?? ''; ?>"\n`;
        }
    <?php endforeach; ?>

    let blob = new Blob([csv], { type: 'text/csv' });
    let url = URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    a.download = 'SMZ_' + (type === 'monthly' ? 'Monthly_' : 'All_') + 'Sales_Report_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
};
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
