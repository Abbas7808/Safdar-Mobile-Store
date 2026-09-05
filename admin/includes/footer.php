<?php
// SMZ Admin Footer Include with Lazy Sales Analytics & Fast Link Prefetcher
$currentPageName = basename($_SERVER['PHP_SELF']);
?>

<!-- Live Sales Analytics Modal -->
<div class="pos-modal-overlay" id="salesAnalyticsModal" style="display: none; z-index: 9999;">
    <div class="pos-modal" style="max-width: 800px; width: 95%;">
        <div class="pos-modal-header" style="border-bottom: 1px solid #e5e7eb; padding-bottom: 12px;">
            <h3 class="pos-modal-title">
                <i class="fa-solid fa-chart-column" style="color:var(--pos-red); margin-right:8px;"></i> Sales Analytics & Reports
            </h3>
            <button class="pos-modal-close" onclick="closeSalesAnalyticsModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- 3 Quick Cards: Daily, Weekly, Monthly -->
        <div id="analyticsCardsArea" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin: 16px 0;">
            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:14px;">
                <div style="font-size:0.75rem; font-weight:700; color:#991b1b; text-transform:uppercase;">Daily Sales (Today)</div>
                <div id="anaTodayRev" style="font-family:var(--pos-font-heading); font-size:1.4rem; font-weight:900; color:#ef4444; margin-top:2px;">PKR 0</div>
                <div id="anaTodayCount" style="font-size:0.72rem; color:#7f1d1d; margin-top:2px; font-weight:600;">0 Sales Today</div>
            </div>

            <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:10px; padding:14px;">
                <div style="font-size:0.75rem; font-weight:700; color:#065f46; text-transform:uppercase;">Weekly Sales (7 Days)</div>
                <div id="anaWeeklyRev" style="font-family:var(--pos-font-heading); font-size:1.4rem; font-weight:900; color:#10b981; margin-top:2px;">PKR 0</div>
                <div id="anaWeeklyCount" style="font-size:0.72rem; color:#064e3b; margin-top:2px; font-weight:600;">0 Sales This Week</div>
            </div>

            <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:14px;">
                <div style="font-size:0.75rem; font-weight:700; color:#92400e; text-transform:uppercase;">Monthly Sales</div>
                <div id="anaMonthlyRev" style="font-family:var(--pos-font-heading); font-size:1.4rem; font-weight:900; color:#d97706; margin-top:2px;">PKR 0</div>
                <div id="anaMonthlyCount" style="font-size:0.72rem; color:#78350f; margin-top:2px; font-weight:600;">0 Sales This Month</div>
            </div>
        </div>

        <!-- Weekly Bar Chart Canvas -->
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; margin-bottom:16px;">
            <h4 style="font-size:0.95rem; font-weight:800; color:#111827; margin-bottom:12px;">
                <i class="fa-solid fa-chart-simple" style="color:var(--pos-red); margin-right:6px;"></i> Weekly Sales Revenue Bar Chart
            </h4>
            <div style="height:250px; position:relative; width:100%;">
                <canvas id="modalWeeklyChart"></canvas>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center;">
            <a href="reports.php" class="pos-btn pos-btn-outline pos-btn-sm"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open Full P&L Report</a>
            <button class="pos-btn pos-btn-primary pos-btn-sm" onclick="closeSalesAnalyticsModal()">Close Window</button>
        </div>
    </div>
</div>

<script>
let modalChartInstance = null;

// Instant 0ms Sidebar Navigation Prefetcher (Speculative Pre-load on Hover)
document.addEventListener('DOMContentLoaded', function() {
    const prefetchedUrls = new Set();
    document.querySelectorAll('.sidebar-nav a.sidebar-link, .pos-cat-btn').forEach(function(el) {
        el.addEventListener('mouseenter', function() {
            const href = this.getAttribute('href');
            if (href && !href.startsWith('#') && !href.includes('logout') && !prefetchedUrls.has(href)) {
                prefetchedUrls.add(href);
                const prefetchTag = document.createElement('link');
                prefetchTag.rel = 'prefetch';
                prefetchTag.href = href;
                document.head.appendChild(prefetchTag);
            }
        }, { passive: true });
    });
});

window.openSalesAnalyticsModal = function() {
    const modal = document.getElementById('salesAnalyticsModal');
    if (modal) modal.style.display = 'flex';

    fetch('../backend/sales.php')
        .then(r => r.json())
        .then(res => {
            const sales = (res && res.data) ? res.data : [];
            const today = new Date().toISOString().split('T')[0];
            const curMonth = today.substring(0, 7);
            
            let dRev = 0, dCount = 0;
            let wRev = 0, wCount = 0;
            let mRev = 0, mCount = 0;

            const daysMap = {};
            for (let i = 6; i >= 0; i--) {
                const d = new Date();
                d.setDate(d.getDate() - i);
                const ds = d.toISOString().split('T')[0];
                const dName = d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
                daysMap[ds] = { label: dName, revenue: 0 };
            }

            sales.forEach(s => {
                if (s.status !== 'completed') return;
                const tot = parseFloat(s.total) || 0;
                const sDate = (s.createdAt || '').substring(0, 10);
                const sM = (s.createdAt || '').substring(0, 7);

                if (sDate === today) { dRev += tot; dCount++; }
                if (daysMap[sDate]) { wRev += tot; wCount++; daysMap[sDate].revenue += tot; }
                if (sM === curMonth) { mRev += tot; mCount++; }
            });

            const tEl = document.getElementById('anaTodayRev'); if (tEl) tEl.textContent = 'PKR ' + dRev.toLocaleString();
            const tcEl = document.getElementById('anaTodayCount'); if (tcEl) tcEl.textContent = dCount + ' Sales Today';
            const wEl = document.getElementById('anaWeeklyRev'); if (wEl) wEl.textContent = 'PKR ' + wRev.toLocaleString();
            const wcEl = document.getElementById('anaWeeklyCount'); if (wcEl) wcEl.textContent = wCount + ' Sales This Week';
            const mEl = document.getElementById('anaMonthlyRev'); if (mEl) mEl.textContent = 'PKR ' + mRev.toLocaleString();
            const mcEl = document.getElementById('anaMonthlyCount'); if (mcEl) mcEl.textContent = mCount + ' Sales This Month';

            const labels = Object.values(daysMap).map(x => x.label);
            const data = Object.values(daysMap).map(x => x.revenue);

            if (typeof Chart !== 'undefined') {
                const ctx = document.getElementById('modalWeeklyChart')?.getContext('2d');
                if (ctx) {
                    if (modalChartInstance) modalChartInstance.destroy();
                    modalChartInstance = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Revenue (PKR)',
                                data: data,
                                backgroundColor: 'rgba(239, 68, 68, 0.85)',
                                borderColor: '#dc2626',
                                borderWidth: 2,
                                borderRadius: 8,
                                barThickness: 28
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true } }
                        }
                    });
                }
            }
        })
        .catch(err => console.error('Analytics load error:', err));
};

window.closeSalesAnalyticsModal = function() {
    const modal = document.getElementById('salesAnalyticsModal');
    if (modal) modal.style.display = 'none';
};

// Admin Theme Switcher
window.toggleAdminTheme = function() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('smz_theme', next);
    updateAdminThemeBtnUI(next);
};

function updateAdminThemeBtnUI(theme) {
    const icon = document.getElementById('adminThemeToggleIcon');
    const text = document.getElementById('adminThemeToggleText');
    if (icon) {
        icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }
    if (text) {
        text.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('smz_theme') || 'light';
    updateAdminThemeBtnUI(savedTheme);
});

/* ==========================================================================
   SMS POS — Global Professional Toast & Notification System
   ========================================================================== */
(function() {
    function getToastContainer() {
        let container = document.getElementById('posToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'posToastContainer';
            document.body.appendChild(container);
        }
        return container;
    }

    function escapeToastHtml(str) {
        if (typeof str !== 'string') return String(str);
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    window.showToast = function(message, type = 'success', title = '', duration = 3500) {
        if (!message) return;
        const container = getToastContainer();

        type = String(type).toLowerCase();
        if (type === 'danger') type = 'error';

        let iconHtml = '<i class="fa-solid fa-circle-check"></i>';
        let defaultTitle = 'Success';

        if (type === 'error') {
            iconHtml = '<i class="fa-solid fa-circle-xmark"></i>';
            defaultTitle = 'Action Failed';
        } else if (type === 'warning') {
            iconHtml = '<i class="fa-solid fa-triangle-exclamation"></i>';
            defaultTitle = 'Attention Required';
        } else if (type === 'info') {
            iconHtml = '<i class="fa-solid fa-circle-info"></i>';
            defaultTitle = 'Notification';
        }

        const toastTitle = title || defaultTitle;

        const toast = document.createElement('div');
        toast.className = `pos-toast pos-toast-${type}`;
        toast.innerHTML = `
            <div class="pos-toast-icon">${iconHtml}</div>
            <div class="pos-toast-content">
                <div class="pos-toast-title">${escapeToastHtml(toastTitle)}</div>
                <div class="pos-toast-message">${escapeToastHtml(message)}</div>
            </div>
            <button type="button" class="pos-toast-close" title="Close"><i class="fa-solid fa-xmark"></i></button>
            <div class="pos-toast-progress" style="animation-duration: ${duration}ms;"></div>
        `;

        container.appendChild(toast);

        let isClosed = false;
        function closeToast() {
            if (isClosed) return;
            isClosed = true;
            toast.classList.add('toast-hiding');
            setTimeout(() => {
                if (toast && toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 320);
        }

        toast.querySelector('.pos-toast-close').addEventListener('click', closeToast);
        let timer = setTimeout(closeToast, duration);

        toast.addEventListener('mouseenter', () => {
            clearTimeout(timer);
        });
        toast.addEventListener('mouseleave', () => {
            timer = setTimeout(closeToast, 1500);
        });
    };

    window.setPendingToast = function(message, type = 'success', title = '') {
        try {
            sessionStorage.setItem('smz_pending_toast', JSON.stringify({ message, type, title }));
        } catch(e) {}
    };

    window.showToastAndReload = function(message, type = 'success', delayMs = 600) {
        window.setPendingToast(message, type);
        window.showToast(message, type);
        setTimeout(() => {
            window.location.reload();
        }, delayMs);
    };

    function checkPendingToast() {
        try {
            const raw = sessionStorage.getItem('smz_pending_toast');
            if (raw) {
                sessionStorage.removeItem('smz_pending_toast');
                const data = JSON.parse(raw);
                if (data && data.message) {
                    setTimeout(() => {
                        window.showToast(data.message, data.type || 'success', data.title || '', 4000);
                    }, 200);
                }
            }
        } catch(e) {}
    }

    // Global override for native window.alert to automatically use Toast
    window.alert = function(msg) {
        if (!msg) return;
        const lower = String(msg).toLowerCase();
        let type = 'info';
        let title = '';

        if (lower.includes('error') || lower.includes('failed') || lower.includes('denied') || lower.includes('invalid') || lower.includes('not found')) {
            type = 'error';
            title = 'Action Failed';
        } else if (lower.includes('warning') || lower.includes('required') || lower.includes('please') || lower.includes('must') || lower.includes('allow popups')) {
            type = 'warning';
            title = 'Attention Required';
        } else if (lower.includes('success') || lower.includes('saved') || lower.includes('deleted') || lower.includes('created') || lower.includes('updated') || lower.includes('returned') || lower.includes('refunded') || lower.includes('recorded')) {
            type = 'success';
            title = 'Operation Successful';
        }

        window.showToast(String(msg), type, title, 3800);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkPendingToast);
    } else {
        checkPendingToast();
    }
})();
</script>

    <!-- Admin JavaScript Controller with Auto Cache Buster -->
    <script src="../assets/js/admin.js?v=<?php echo @filemtime(__DIR__ . '/../../assets/js/admin.js') ?: time(); ?>"></script>
</body>
</html>
