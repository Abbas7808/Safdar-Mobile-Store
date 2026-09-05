<?php
$currentPage = 'settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Require Super Admin Role
$user = require_role('super_admin');

$settings = get_json_file('settings') ?? [];
$message = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrfToken)) {
        $message = 'CSRF validation failed. Please refresh and try again.';
        $msgType = 'danger';
    } else {
        $settings['businessName'] = trim($_POST['businessName'] ?? '');
        $settings['businessSubtitle'] = trim($_POST['businessSubtitle'] ?? '');
        $settings['contact'] = trim($_POST['contact'] ?? '');
        $settings['address'] = trim($_POST['address'] ?? '');
        $settings['currency'] = trim($_POST['currency'] ?? 'PKR');
        $settings['receiptWidth'] = $_POST['receiptWidth'] ?? '80mm';
        $settings['receiptFooter'] = trim($_POST['receiptFooter'] ?? '');

        save_json_file('settings', $settings);
        SecurityLogger::logEvent($user['username'], 'super_admin', 'SETTINGS_UPDATED', 'Updated store identity and receipt settings');
        $message = 'Settings saved successfully!';
        $msgType = 'success';
    }
}
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content">
        <div class="page-header">
            <div>
                <h1><i class="fa-solid fa-gear" style="color:var(--pos-red); margin-right:10px;"></i> System Settings</h1>
                <p class="page-header-sub">Configure store brand identity, thermal receipts, and POS defaults</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="login-error" style="background:rgba(16,185,129,0.1); color:#059669; margin-bottom:20px;">
                <i class="fa-solid fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="settings.php">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <div class="pos-card" style="margin-bottom:20px;">
                <h3 style="font-family:var(--pos-font-heading); font-weight:800; margin-bottom:16px;">Store Identity & Contact Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Business Name</label>
                        <input type="text" name="businessName" class="form-input" value="<?php echo htmlspecialchars($settings['businessName'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Subtitle / Tagline</label>
                        <input type="text" name="businessSubtitle" class="form-input" value="<?php echo htmlspecialchars($settings['businessSubtitle'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Primary Contact Number</label>
                        <input type="text" name="contact" class="form-input" value="<?php echo htmlspecialchars($settings['contact'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Default Currency</label>
                        <input type="text" name="currency" class="form-input" value="<?php echo htmlspecialchars($settings['currency'] ?? 'PKR'); ?>">
                    </div>

                    <div class="form-group form-full">
                        <label class="form-label">Store Location Address</label>
                        <input type="text" name="address" class="form-input" value="<?php echo htmlspecialchars($settings['address'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="pos-card">
                <h3 style="font-family:var(--pos-font-heading); font-weight:800; margin-bottom:16px;">Thermal Receipt Customization</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Printer Paper Width</label>
                        <select name="receiptWidth" class="form-select">
                            <option value="80mm" <?php echo ($settings['receiptWidth'] ?? '') === '80mm' ? 'selected' : ''; ?>>80mm Standard Thermal</option>
                            <option value="58mm" <?php echo ($settings['receiptWidth'] ?? '') === '58mm' ? 'selected' : ''; ?>>58mm Compact Thermal</option>
                        </select>
                    </div>

                    <div class="form-group form-full">
                        <label class="form-label">Receipt Footer Message</label>
                        <textarea name="receiptFooter" class="form-textarea" rows="3"><?php echo htmlspecialchars($settings['receiptFooter'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="pos-btn pos-btn-primary">
                        <i class="fa-solid fa-check"></i> Save Store Settings
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
