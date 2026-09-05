<?php
$currentPage = 'security';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Require Super Admin Role
$user = require_role('super_admin');

$msg = '';
$msgType = 'success';

$users = get_json_file('users') ?? [];
$currentUserObj = null;
foreach ($users as &$u) {
    if ($u['id'] === $user['userId']) {
        $currentUserObj = &$u;
        break;
    }
}

// Generate new 2FA secret if user doesn't have one saved
if (empty($currentUserObj['totp_secret'])) {
    $currentUserObj['totp_secret'] = TotpEngine::generateSecret();
    save_json_file('users', $users);
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!validate_csrf_token($csrfToken)) {
        $msg = 'Security token validation failed (CSRF mismatch).';
        $msgType = 'danger';
    } else {
        // 1. Enable / Disable 2FA
        if ($action === 'toggle_2fa') {
            $totpCode = trim($_POST['totp_code'] ?? '');
            $enableState = isset($_POST['enable_2fa']);

            if ($enableState) {
                if (empty($totpCode)) {
                    $msg = 'Please enter a valid 6-digit code from your Authenticator app to enable 2FA.';
                    $msgType = 'danger';
                } elseif (TotpEngine::verifyCode($currentUserObj['totp_secret'], $totpCode)) {
                    $currentUserObj['totp_enabled'] = true;
                    save_json_file('users', $users);
                    SecurityLogger::logEvent($user['username'], 'super_admin', '2FA_ENABLED', 'Enabled Two-Factor Authentication (TOTP)');
                    $msg = 'Two-Factor Authentication (2FA) enabled successfully for Super Admin!';
                    $msgType = 'success';
                } else {
                    $msg = 'Invalid 6-digit code. Verification failed. 2FA was not enabled.';
                    $msgType = 'danger';
                }
            } else {
                // Disable 2FA requires verifying current password
                $reauthPass = trim($_POST['reauth_password'] ?? '');
                if (!PasswordPolicy::verifyPassword($reauthPass, $currentUserObj['password'])) {
                    $msg = 'Incorrect admin password. Re-authentication failed.';
                    $msgType = 'danger';
                } else {
                    $currentUserObj['totp_enabled'] = false;
                    save_json_file('users', $users);
                    SecurityLogger::logEvent($user['username'], 'super_admin', '2FA_DISABLED', 'Disabled Two-Factor Authentication');
                    $msg = 'Two-Factor Authentication has been disabled.';
                    $msgType = 'warning';
                }
            }
        }

        // 2. Change Secret Admin Portal URL Slug
        if ($action === 'change_secret_url') {
            $newSlug = trim($_POST['new_secret_slug'] ?? '');
            $reauthPass = trim($_POST['reauth_password'] ?? '');

            if (!PasswordPolicy::verifyPassword($reauthPass, $currentUserObj['password'])) {
                $msg = 'Incorrect admin password. Re-authentication failed.';
                $msgType = 'danger';
            } else {
                $updatedSlug = SecretAdminUrlManager::setSecretSlug($newSlug);
                if ($updatedSlug) {
                    SecretAdminUrlManager::grantAccess();
                    SecurityLogger::logEvent($user['username'], 'super_admin', 'SECRET_URL_CHANGED', "Updated secret admin portal path to '/{$updatedSlug}'");
                    $msg = "Secret Admin Portal URL updated successfully to: '/{$updatedSlug}'";
                    $msgType = 'success';
                } else {
                    $msg = 'Invalid secret slug. Must be at least 6 alphanumeric characters (letters, numbers, hyphens).';
                    $msgType = 'danger';
                }
            }
        }

        // 3. Toggle Emergency Lockdown
        if ($action === 'toggle_emergency_lockdown') {
            $reauthPass = trim($_POST['reauth_password'] ?? '');
            if (!PasswordPolicy::verifyPassword($reauthPass, $currentUserObj['password'])) {
                $msg = 'Incorrect admin password. Re-authentication failed.';
                $msgType = 'danger';
            } else {
                $currentLockdown = ActiveSessionManager::isEmergencyLockdownActive();
                ActiveSessionManager::setEmergencyLockdown(!$currentLockdown);
                $newState = !$currentLockdown ? 'ENABLED' : 'DISABLED';
                SecurityLogger::logEvent($user['username'], 'super_admin', 'EMERGENCY_LOCKDOWN_TOGGLED', "Emergency non-admin lockdown set to {$newState}");
                $msg = "Emergency Non-Admin Lockdown is now " . ($newState === 'ENABLED' ? 'ACTIVE (All non-admin accounts disabled)' : 'DEACTIVATED');
                $msgType = $newState === 'ENABLED' ? 'danger' : 'success';
            }
        }

        // 4. Logout All Other Sessions
        if ($action === 'logout_other_sessions') {
            ActiveSessionManager::logoutAllOtherSessions($user['userId']);
            SecurityLogger::logEvent($user['username'], 'super_admin', 'SESSIONS_TERMINATED', 'Logged out all other active sessions');
            $msg = 'All other active sessions have been terminated.';
            $msgType = 'success';
        }

        // 5. Change Password (Strong Password Policy Enforced)
        if ($action === 'change_password') {
            $currentPass = trim($_POST['current_password'] ?? '');
            $newPass = trim($_POST['new_password'] ?? '');
            $confirmPass = trim($_POST['confirm_password'] ?? '');

            if (!PasswordPolicy::verifyPassword($currentPass, $currentUserObj['password'])) {
                $msg = 'Current password is incorrect.';
                $msgType = 'danger';
            } elseif ($newPass !== $confirmPass) {
                $msg = 'New password and confirmation password do not match.';
                $msgType = 'danger';
            } else {
                $valRes = PasswordPolicy::validatePassword($newPass, $user['username'], $user['name']);
                if (!$valRes['valid']) {
                    $msg = 'Password requirements failed: ' . implode(' ', $valRes['errors']);
                    $msgType = 'danger';
                } else {
                    $currentUserObj['password'] = PasswordPolicy::hashPassword($newPass);
                    $currentUserObj['password_changed_at'] = date('c');
                    save_json_file('users', $users);

                    SecurityLogger::logEvent($user['username'], 'super_admin', 'PASSWORD_CHANGED', 'Super Admin password changed successfully');
                    $msg = 'Super Admin password updated successfully adhering to strong security policy!';
                    $msgType = 'success';
                }
            }
        }
    }
}

$securityLogs = get_json_file('security_logs') ?? [];
$activeSessions = ActiveSessionManager::getActiveSessions();
$currentSecretSlug = SecretAdminUrlManager::getSecretSlug();
$isLockdownActive = ActiveSessionManager::isEmergencyLockdownActive();
$totpQrUrl = TotpEngine::getQrCodeApiUrl($currentUserObj['totp_secret'], $user['username']);
$totpSecret = $currentUserObj['totp_secret'];
$is2faEnabled = !empty($currentUserObj['totp_enabled']);
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content">
        <div class="page-header">
            <div>
                <h1><i class="fa-solid fa-shield-halved" style="color:var(--pos-gold); margin-right:10px;"></i> Security Control & 2FA Center</h1>
                <p class="page-header-sub">Manage 2FA authenticator, secret admin URL, session security, password policies, and brute-force audit trails</p>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="login-error" style="margin-bottom:20px; background:<?php echo $msgType === 'success' ? '#ecfdf5' : ($msgType === 'warning' ? '#fffbeb' : '#fef2f2'); ?>; border:1px solid <?php echo $msgType === 'success' ? '#a7f3d0' : ($msgType === 'warning' ? '#fef3c7' : '#fecaca'); ?>; color:<?php echo $msgType === 'success' ? '#065f46' : ($msgType === 'warning' ? '#b45309' : '#dc2626'); ?>; padding:12px 16px; border-radius:8px; font-weight:700;">
                <i class="fa-solid <?php echo $msgType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!-- Key Metrics Cards -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px; margin-bottom:24px;">
            <!-- Card 1: 2FA Status -->
            <div class="pos-card" style="border-top:4px solid <?php echo $is2faEnabled ? '#10b981' : '#f59e0b'; ?>;">
                <div style="font-size:0.75rem; color:#9ca3af; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Two-Factor Authentication</div>
                <div style="font-size:1.4rem; font-weight:900; color:<?php echo $is2faEnabled ? '#10b981' : '#f59e0b'; ?>;">
                    <i class="fa-solid <?php echo $is2faEnabled ? 'fa-shield-check' : 'fa-triangle-exclamation'; ?>"></i>
                    <?php echo $is2faEnabled ? '2FA ENABLED' : '2FA DISABLED'; ?>
                </div>
                <div style="font-size:0.75rem; color:#6b7280; margin-top:6px;">TOTP Authenticator App (RFC 6238)</div>
            </div>

            <!-- Card 2: Secret Admin URL -->
            <div class="pos-card" style="border-top:4px solid var(--pos-gold);">
                <div style="font-size:0.75rem; color:#9ca3af; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Secret Admin Portal URL</div>
                <div style="font-size:1.1rem; font-weight:800; color:var(--pos-gold-dark); word-break:break-all;">
                    /<?php echo htmlspecialchars($currentSecretSlug); ?>/
                </div>
                <div style="font-size:0.75rem; color:#6b7280; margin-top:6px;">Direct /admin access gives 404 Not Found</div>
            </div>

            <!-- Card 3: Active Sessions -->
            <div class="pos-card" style="border-top:4px solid #3b82f6;">
                <div style="font-size:0.75rem; color:#9ca3af; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Active Admin Sessions</div>
                <div style="font-size:1.4rem; font-weight:900; color:#3b82f6;">
                    <i class="fa-solid fa-users-viewfinder"></i> <?php echo count($activeSessions); ?> Active Session<?php echo count($activeSessions) !== 1 ? 's' : ''; ?>
                </div>
                <div style="font-size:0.75rem; color:#6b7280; margin-top:6px;">Tracked by IP & Session Fingerprint</div>
            </div>

            <!-- Card 4: Emergency Lockdown -->
            <div class="pos-card" style="border-top:4px solid <?php echo $isLockdownActive ? '#ef4444' : '#10b981'; ?>;">
                <div style="font-size:0.75rem; color:#9ca3af; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Emergency Control</div>
                <div style="font-size:1.2rem; font-weight:900; color:<?php echo $isLockdownActive ? '#ef4444' : '#10b981'; ?>;">
                    <i class="fa-solid <?php echo $isLockdownActive ? 'fa-lock' : 'fa-lock-open'; ?>"></i>
                    <?php echo $isLockdownActive ? 'LOCKDOWN ACTIVE' : 'SYSTEM NORMAL'; ?>
                </div>
                <div style="font-size:0.75rem; color:#6b7280; margin-top:6px;"><?php echo $isLockdownActive ? 'Non-admin accounts BLOCKED' : 'Non-admin accounts ACTIVE'; ?></div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; margin-bottom:24px;">
            
            <!-- 1. TWO-FACTOR AUTHENTICATION (TOTP 2FA) SETUP -->
            <div class="pos-card">
                <h3 class="pos-card-title" style="margin-bottom:16px;">
                    <i class="fa-solid fa-mobile-retro" style="color:var(--pos-gold); margin-right:8px;"></i> Two-Factor Authentication (2FA)
                </h3>

                <?php if ($is2faEnabled): ?>
                    <div style="background:#ecfdf5; border:1px solid #a7f3d0; padding:16px; border-radius:10px; margin-bottom:20px;">
                        <h4 style="color:#065f46; margin:0 0 6px 0;"><i class="fa-solid fa-circle-check"></i> 2FA Protection Active</h4>
                        <p style="color:#047857; font-size:0.85rem; margin:0;">Your Super Admin account is secured with 2FA TOTP code verification upon login.</p>
                    </div>

                    <form method="POST" action="security.php" onsubmit="return confirm('Are you sure you want to disable 2FA protection?');">
                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                        <input type="hidden" name="action" value="toggle_2fa">

                        <div class="form-group" style="margin-bottom:14px;">
                            <label class="form-label">Re-enter Admin Password to Disable 2FA *</label>
                            <input type="password" name="reauth_password" class="form-input" placeholder="Current admin password" required>
                        </div>

                        <button type="submit" class="pos-btn pos-btn-block" style="background:#ef4444; color:#fff; font-weight:700;">
                            <i class="fa-solid fa-shield-slash"></i> Disable Two-Factor Authentication
                        </button>
                    </form>
                <?php else: ?>
                    <p style="font-size:0.85rem; color:#475569; margin-bottom:16px;">
                        Scan the QR code below with any Authenticator app (Google Authenticator, Authy, Microsoft Authenticator) or copy the secret key.
                    </p>

                    <div style="display:flex; gap:16px; align-items:center; background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:16px;">
                        <img src="<?php echo htmlspecialchars($totpQrUrl); ?>" alt="2FA QR Code" style="width:140px; height:140px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; padding:6px;">
                        <div>
                            <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">Manual Secret Key:</div>
                            <div style="font-family:monospace; font-size:1.1rem; font-weight:800; color:var(--pos-red); background:#fff; padding:6px 10px; border-radius:6px; border:1px solid #cbd5e1; margin:6px 0;">
                                <?php echo htmlspecialchars($totpSecret); ?>
                            </div>
                            <div style="font-size:0.75rem; color:#64748b;">Algorithm: SHA1 | Period: 30s</div>
                        </div>
                    </div>

                    <form method="POST" action="security.php">
                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                        <input type="hidden" name="action" value="toggle_2fa">
                        <input type="hidden" name="enable_2fa" value="1">

                        <div class="form-group" style="margin-bottom:14px;">
                            <label class="form-label">Enter 6-Digit Authenticator Code to Confirm *</label>
                            <input type="text" name="totp_code" class="form-input" style="font-size:1.2rem; font-weight:800; letter-spacing:4px; text-align:center;" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autocomplete="off">
                        </div>

                        <button type="submit" class="pos-btn pos-btn-primary pos-btn-block">
                            <i class="fa-solid fa-shield-check"></i> Verify & Enable 2FA Protection
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- 2. SECRET ADMIN URL & EMERGENCY LOCKDOWN CONTROL -->
            <div style="display:flex; flex-direction:column; gap:20px;">
                
                <!-- Secret URL Configurator -->
                <div class="pos-card">
                    <h3 class="pos-card-title" style="margin-bottom:12px;">
                        <i class="fa-solid fa-link-slash" style="color:var(--pos-gold); margin-right:8px;"></i> Secret Admin Portal Access Path
                    </h3>
                    <p style="font-size:0.82rem; color:#64748b; margin-bottom:14px;">
                        Configure the non-obvious URL slug used to access the admin portal gateway (e.g. <code>/secure-management-portal/</code>).
                    </p>

                    <form method="POST" action="security.php">
                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                        <input type="hidden" name="action" value="change_secret_url">

                        <div class="form-group" style="margin-bottom:12px;">
                            <label class="form-label">New Secret URL Path Slug *</label>
                            <input type="text" name="new_secret_slug" class="form-input" value="<?php echo htmlspecialchars($currentSecretSlug); ?>" required placeholder="e.g. secure-management-portal">
                        </div>

                        <div class="form-group" style="margin-bottom:14px;">
                            <label class="form-label">Super Admin Password Verification *</label>
                            <input type="password" name="reauth_password" class="form-input" placeholder="Current admin password" required>
                        </div>

                        <button type="submit" class="pos-btn pos-btn-outline pos-btn-block" style="border-color:var(--pos-gold-dark); color:var(--pos-gold-dark);">
                            <i class="fa-solid fa-pen-to-square"></i> Update Secret Access Path
                        </button>
                    </form>
                </div>

                <!-- Emergency Lockdown & Session Termination -->
                <div class="pos-card" style="border:1px solid rgba(239,68,68,0.3); background:#fffdfd;">
                    <h3 class="pos-card-title" style="margin-bottom:12px; color:var(--pos-red);">
                        <i class="fa-solid fa-triangle-exclamation" style="margin-right:8px;"></i> Emergency Security Controls
                    </h3>

                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <!-- Lockdown Toggle -->
                        <form method="POST" action="security.php" onsubmit="return confirm('Toggling Emergency Lockdown will immediately block or unblock all Salesmen. Continue?');">
                            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                            <input type="hidden" name="action" value="toggle_emergency_lockdown">

                            <div style="margin-bottom:10px;">
                                <label class="form-label">Super Admin Password Verification *</label>
                                <input type="password" name="reauth_password" class="form-input" placeholder="Current admin password" required>
                            </div>

                            <button type="submit" class="pos-btn pos-btn-block" style="background:<?php echo $isLockdownActive ? '#10b981' : '#ef4444'; ?>; color:#fff; font-weight:800;">
                                <i class="fa-solid <?php echo $isLockdownActive ? 'fa-lock-open' : 'fa-user-lock'; ?>"></i>
                                <?php echo $isLockdownActive ? 'DEACTIVATE EMERGENCY LOCKDOWN' : 'ENABLE EMERGENCY NON-ADMIN LOCKDOWN'; ?>
                            </button>
                        </form>

                        <!-- Logout All Other Sessions -->
                        <form method="POST" action="security.php">
                            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                            <input type="hidden" name="action" value="logout_other_sessions">
                            <button type="submit" class="pos-btn pos-btn-outline pos-btn-block" style="color:#64748b; border-color:#cbd5e1;">
                                <i class="fa-solid fa-right-from-bracket"></i> Terminate All Other Active Sessions
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        <!-- 3. STRONG PASSWORD CHANGE SECTION -->
        <div class="pos-card" style="margin-bottom:24px;">
            <h3 class="pos-card-title" style="margin-bottom:16px;">
                <i class="fa-solid fa-key" style="color:var(--pos-red); margin-right:8px;"></i> Change Super Admin Password (Strong Policy Enforced)
            </h3>
            <p style="font-size:0.82rem; color:#64748b; margin-bottom:16px;">
                Requires min 14 characters, uppercase, lowercase, numbers, and special characters. Common phrases, store name, or usernames are rejected.
            </p>

            <form method="POST" action="security.php">
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                <input type="hidden" name="action" value="change_password">

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:16px;">
                    <div class="form-group">
                        <label class="form-label">Current Password *</label>
                        <input type="password" name="current_password" class="form-input" placeholder="Current password" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">New Strong Password *</label>
                        <input type="password" name="new_password" class="form-input" placeholder="Min 14 chars (e.g. Pass#2026!Safdar)" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm New Password *</label>
                        <input type="password" name="confirm_password" class="form-input" placeholder="Confirm new password" required>
                    </div>
                </div>

                <button type="submit" class="pos-btn pos-btn-primary" style="padding:10px 24px; font-weight:800;">
                    <i class="fa-solid fa-floppy-disk"></i> Update Super Admin Password
                </button>
            </form>
        </div>

        <!-- 4. BRUTE-FORCE & SECURITY AUDIT TRAIL TABLE -->
        <div class="data-table-wrap">
            <div class="data-table-toolbar">
                <h3 class="pos-card-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--pos-gold-dark); margin-right:8px;"></i> Security Activity & Brute-Force Log</h3>
                <span class="status-badge status-active"><?php echo count($securityLogs); ?> Total Log Entries</span>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Status</th>
                        <th>IP Address</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($securityLogs)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; color:#9ca3af; padding:20px;">No security logs recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach (array_slice($securityLogs, 0, 50) as $log): ?>
                            <tr>
                                <td style="font-size:0.8rem; color:#64748b; font-weight:600;"><?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($log['user']); ?></strong></td>
                                <td><span class="status-badge status-completed"><?php echo strtoupper($log['role']); ?></span></td>
                                <td><strong style="color:var(--pos-gold-dark);"><?php echo htmlspecialchars($log['action']); ?></strong></td>
                                <td>
                                    <span class="status-badge <?php echo ($log['status'] ?? 'SUCCESS') === 'SUCCESS' ? 'status-active' : 'status-danger'; ?>">
                                        <?php echo htmlspecialchars($log['status'] ?? 'SUCCESS'); ?>
                                    </span>
                                </td>
                                <td><code style="font-size:0.8rem; color:#3b82f6;"><?php echo htmlspecialchars($log['ip']); ?></code></td>
                                <td style="font-size:0.82rem; color:#475569;"><?php echo htmlspecialchars($log['details']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
