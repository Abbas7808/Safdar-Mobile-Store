<?php
// SMS Admin Topbar Include
$user = get_session_user();
$allProds = get_json_file('products') ?? [];
$lowStockProds = [];
foreach ($allProds as $p) {
    $st = intval($p['stock'] ?? 0);
    $min = intval($p['minStock'] ?? 2);
    if ($st <= $min) {
        $lowStockProds[] = $p;
    }
}
$notifBadgeCount = count($lowStockProds);
?>
<header class="pos-topbar">
    <div class="topbar-left" style="display:flex; align-items:center; gap:10px;">
        <button class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="window.toggleMobileSidebar()" aria-label="Toggle Navigation Sidebar" title="Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar-search" style="position:relative; display:flex; align-items:center;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:12px; color:var(--pos-text-muted); pointer-events:none;"></i>
            <input type="text" id="topbarSearchInput" placeholder="Search customer, orders, packages, Easypaisa, Jazzcash, CCTV..." autocomplete="off" oninput="window.handleGlobalSearchInput(this.value)" onfocus="window.handleGlobalSearchInput(this.value)" onkeydown="if(event.key==='Enter'){ window.handleGlobalSearchInput(this.value); }" style="padding-left:36px; padding-right:42px;">
            <button type="button" class="topbar-search-btn" onclick="window.handleGlobalSearchInput(document.getElementById('topbarSearchInput').value)" title="Search POS Records, Packages, Easypaisa, JazzCash" style="position:absolute; right:5px; top:50%; transform:translateY(-50%); background:var(--pos-gold); color:#000; border:none; border-radius:7px; width:30px; height:30px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:0.85rem; font-weight:bold; box-shadow:0 2px 5px rgba(0,0,0,0.2);">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            
            <!-- Live Global Autocomplete Search Dropdown -->
            <div id="topbarSearchResults" style="display:none; position:absolute; top:calc(100% + 8px); left:0; width:540px; max-width:94vw; background:#1e293b; border:1.5px solid #475569; border-radius:14px; box-shadow:0 25px 60px rgba(0,0,0,0.6); z-index:999999; padding:14px; color:#f8fafc; max-height:540px; overflow-y:auto; backdrop-filter:blur(10px);">
                <div id="topbarSearchResultsContent"></div>
            </div>
        </div>
    </div>

    <!-- Mobile Sidebar Drawer Backdrop Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="window.closeMobileSidebar()"></div>

    <div class="topbar-actions" style="display:flex; align-items:center; gap:10px; position:relative;">
        <!-- Notification Bell Dropdown -->
        <div class="notif-wrapper" style="position:relative;">
            <button id="notifBellBtn" class="pos-btn pos-btn-outline pos-btn-sm" onclick="window.toggleNotifDropdown()" title="Stock Alert Notifications" style="color:var(--pos-gold); border-color:var(--pos-gold); position:relative; padding:8px 12px;">
                <i class="fa-solid fa-bell"></i>
                <?php if ($notifBadgeCount > 0): ?>
                    <span id="topbarNotifBadge" style="position:absolute; top:-5px; right:-5px; background:#ef4444; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:10px; border:2px solid #1e293b;">
                        <?php echo $notifBadgeCount; ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- Dropdown Menu -->
            <div id="notifDropdownMenu" style="display:none; position:absolute; right:0; top:42px; width:330px; background:#1e293b; border:1px solid #334155; border-radius:12px; box-shadow:0 15px 35px rgba(0,0,0,0.5); z-index:9999; padding:12px; color:#f8fafc;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #334155; padding-bottom:8px; margin-bottom:10px;">
                    <strong style="font-size:0.9rem; color:#fff;"><i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;"></i> Stock Alerts</strong>
                    <span style="font-size:0.75rem; background:#334155; color:#94a3b8; padding:2px 8px; border-radius:12px;"><?php echo $notifBadgeCount; ?> Low / Out</span>
                </div>

                <div id="notifItemsList" style="max-height:260px; overflow-y:auto;">
                    <?php if (empty($lowStockProds)): ?>
                        <div style="text-align:center; padding:20px; color:#94a3b8; font-size:0.82rem;">
                            <i class="fa-solid fa-check-circle" style="color:#10b981; font-size:1.5rem; margin-bottom:6px; display:block;"></i>
                            All products are well stocked!
                        </div>
                    <?php else: ?>
                        <?php foreach ($lowStockProds as $lp): 
                            $st = intval($lp['stock'] ?? 0);
                            $isOut = $st <= 0;
                        ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; border:1px solid #334155; padding:8px 10px; border-radius:8px; margin-bottom:6px; font-size:0.8rem;">
                                <div>
                                    <div style="font-weight:700; color:#fff; line-height:1.2;"><?php echo htmlspecialchars($lp['name']); ?></div>
                                    <div style="font-size:0.72rem; color:#94a3b8;">SKU: <?php echo htmlspecialchars($lp['sku'] ?? 'N/A'); ?></div>
                                </div>
                                <div style="text-align:right;">
                                    <span style="display:inline-block; font-size:0.7rem; font-weight:800; padding:2px 6px; border-radius:4px; <?php echo $isOut ? 'background:#fee2e2; color:#dc2626;' : 'background:#fef3c7; color:#d97706;'; ?>">
                                        <?php echo $isOut ? 'OUT OF STOCK' : 'LOW: ' . $st; ?>
                                    </span>
                                    <a href="inventory.php?search=<?php echo urlencode($lp['name']); ?>" style="display:block; font-size:0.7rem; color:var(--pos-gold); text-decoration:none; margin-top:2px;">Reorder &rarr;</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div style="border-top:1px solid #334155; padding-top:8px; margin-top:6px; text-align:center;">
                    <a href="inventory.php" style="font-size:0.78rem; color:var(--pos-gold); text-decoration:none; font-weight:700;">View All Inventory & Stock Control &rarr;</a>
                </div>
            </div>
        </div>

        <button id="adminThemeToggleBtn" class="pos-btn pos-btn-outline pos-btn-sm" onclick="window.toggleAdminTheme()" title="Toggle Dark/Light Mode" style="color:var(--pos-gold); border-color:var(--pos-gold);">
            <i id="adminThemeToggleIcon" class="fa-solid fa-moon"></i>
            <span id="adminThemeToggleText">Dark Mode</span>
        </button>

        <button class="pos-btn pos-btn-outline pos-btn-sm" onclick="window.openSalesAnalyticsModal()" style="color:var(--pos-gold); border-color:var(--pos-gold);" title="View Daily, Weekly & Monthly Sales Analytics">
            <i class="fa-solid fa-chart-column"></i> Sales Reports
        </button>

        <a href="../index.php" target="_blank" class="pos-btn pos-btn-outline pos-btn-sm" title="View Customer Storefront">
            <i class="fa-solid fa-globe"></i> View Website
        </a>

        <div class="topbar-user">
            <div class="topbar-avatar"><?php echo strtoupper(substr($user['name'] ?? 'A', 0, 1)); ?></div>
            <div class="topbar-user-info">
                <span class="topbar-user-name"><?php echo htmlspecialchars($user['name'] ?? 'Super Admin'); ?></span>
                <span class="topbar-user-role"><?php echo strtoupper(str_replace('_', ' ', $user['role'] ?? 'super_admin')); ?></span>
            </div>
        </div>
    </div>
</header>

