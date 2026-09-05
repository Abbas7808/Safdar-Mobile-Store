<?php
// SMS Admin Sidebar Include with Dynamic Role-Based Access Control (RBAC)
$currentScript = basename($_SERVER['PHP_SELF'], '.php');
if ($currentScript === 'index' || $currentScript === 'admin') {
    $currentScript = 'dashboard';
}
$activePage = $currentPage ?? $currentScript;

$user = get_session_user();
$userRole = $user['role'] ?? 'salesman';
$isSuperAdmin = ($userRole !== 'salesman');
?>
<aside class="pos-sidebar" id="adminSidebar">
    <button class="sidebar-close-btn" onclick="window.closeMobileSidebar()" title="Close Navigation Sidebar">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <div class="sidebar-brand" style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:18px 10px 10px 10px;">
        <img src="../assets/images/logo.jpg" alt="Safdar Mobile Store Logo" style="height:55px; width:auto; border-radius:50%; box-shadow:0 0 10px rgba(244,196,48,0.4); margin-bottom:6px;">
        <div style="text-align:center;">
            <div style="font-size:0.75rem; font-weight:800; color:var(--pos-gold); letter-spacing:0.5px; text-transform:uppercase;"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></div>
            <div style="font-size:0.65rem; color:#9ca3af; text-transform:uppercase; font-weight:700;"><?php echo str_replace('_', ' ', $userRole); ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php if ($isSuperAdmin): ?>
            <a href="index.php" class="sidebar-link <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge"></i> Dashboard
            </a>
        <?php endif; ?>

        <a href="pos.php" class="sidebar-link gold-link <?php echo $activePage === 'pos' ? 'active' : ''; ?>">
            <i class="fa-solid fa-cash-register"></i> POS Terminal
        </a>

        <?php if ($isSuperAdmin): ?>
            <div class="nav-section-label">Inventory &amp; Products</div>

            <a href="products.php" class="sidebar-link <?php echo $activePage === 'products' ? 'active' : ''; ?>">
                <i class="fa-solid fa-boxes-stacked"></i> All Products
            </a>
            <a href="product-add.php" class="sidebar-link <?php echo $activePage === 'product-add' ? 'active' : ''; ?>">
                <i class="fa-solid fa-plus"></i> Add New Product
            </a>
            <a href="categories.php" class="sidebar-link <?php echo $activePage === 'categories' ? 'active' : ''; ?>">
                <i class="fa-solid fa-tags"></i> Categories
            </a>
            <a href="brands.php" class="sidebar-link <?php echo $activePage === 'brands' ? 'active' : ''; ?>">
                <i class="fa-solid fa-copyright"></i> Brands
            </a>
            <a href="inventory.php" class="sidebar-link <?php echo $activePage === 'inventory' ? 'active' : ''; ?>">
                <i class="fa-solid fa-warehouse"></i> Stock Control
            </a>

            <div class="nav-section-label">Purchases &amp; Suppliers</div>

            <a href="purchases.php" class="sidebar-link <?php echo $activePage === 'purchases' ? 'active' : ''; ?>">
                <i class="fa-solid fa-truck-ramp-box"></i> Purchase Orders
            </a>
            <a href="suppliers.php" class="sidebar-link <?php echo $activePage === 'suppliers' ? 'active' : ''; ?>">
                <i class="fa-solid fa-building"></i> Suppliers Directory
            </a>
        <?php endif; ?>

        <div class="nav-section-label">Sales &amp; Finance</div>

        <a href="sales.php" class="sidebar-link <?php echo $activePage === 'sales' ? 'active' : ''; ?>">
            <i class="fa-solid fa-receipt"></i> Sales &amp; Invoices
        </a>
        <a href="customers.php" class="sidebar-link <?php echo $activePage === 'customers' ? 'active' : ''; ?>">
            <i class="fa-solid fa-users"></i> Customers
        </a>

        <?php if ($isSuperAdmin): ?>
            <a href="payments.php" class="sidebar-link <?php echo $activePage === 'payments' ? 'active' : ''; ?>">
                <i class="fa-solid fa-money-check-dollar"></i> Debts &amp; Ledgers
            </a>
            <a href="mobile-repairs.php" class="sidebar-link gold-link <?php echo $activePage === 'mobile-repairs' ? 'active' : ''; ?>">
                <i class="fa-solid fa-screwdriver-wrench"></i> Mobile Repairs Lab
            </a>
            <a href="cctv.php" class="sidebar-link <?php echo $activePage === 'cctv' ? 'active' : ''; ?>" style="border-left:2px solid #ef4444;">
                <i class="fa-solid fa-video" style="color:#ef4444;"></i> CCTV Surveillance
            </a>
            <a href="repair-parts.php" class="sidebar-link <?php echo $activePage === 'repair-parts' ? 'active' : ''; ?>">
                <i class="fa-solid fa-microchip"></i> Spare Parts &amp; Pricing
            </a>
            <a href="services.php" class="sidebar-link <?php echo $activePage === 'services' ? 'active' : ''; ?>">
                <i class="fa-solid fa-wallet"></i> Easypaisa / JazzCash
            </a>
            <a href="bills.php" class="sidebar-link <?php echo $activePage === 'bills' ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-invoice-dollar"></i> Utility Bills Payment
            </a>
            <a href="packages.php" class="sidebar-link <?php echo $activePage === 'packages' ? 'active' : ''; ?>">
                <i class="fa-solid fa-mobile-screen-button"></i> Mobile Packages &amp; Load
            </a>
            <a href="nadra-kiosk.php" class="sidebar-link <?php echo $activePage === 'nadra-kiosk' ? 'active' : ''; ?>">
                <i class="fa-solid fa-id-card"></i> NADRA &amp; Citizen Kiosk
            </a>
            <a href="expenses.php" class="sidebar-link <?php echo $activePage === 'expenses' ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-invoice-dollar"></i> Operating Expenses
            </a>
            <a href="reports.php" class="sidebar-link <?php echo $activePage === 'reports' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line"></i> P&amp;L Reports
            </a>

            <div class="nav-section-label">System &amp; Security</div>

            <a href="security.php" class="sidebar-link gold-link <?php echo $activePage === 'security' ? 'active' : ''; ?>">
                <i class="fa-solid fa-shield-halved"></i> Security &amp; 2FA
            </a>
            <a href="users.php" class="sidebar-link <?php echo $activePage === 'users' ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-shield"></i> User Accounts &amp; Roles
            </a>
            <a href="audit.php" class="sidebar-link <?php echo $activePage === 'audit' ? 'active' : ''; ?>">
                <i class="fa-solid fa-clock-rotate-left"></i> Audit Trail
            </a>
            <a href="settings.php" class="sidebar-link <?php echo $activePage === 'settings' ? 'active' : ''; ?>">
                <i class="fa-solid fa-gear"></i> Store Settings
            </a>
        <?php endif; ?>

        <a href="login.php?action=logout" class="sidebar-link" style="color:#ef4444; margin-top:15px; border-top:1px solid rgba(255,255,255,0.08); padding-top:12px;">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </nav>
</aside>
