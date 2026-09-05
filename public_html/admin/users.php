<?php
$currentPage = 'users';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Strict Super Admin Access
$sessionUser = require_role('super_admin');

$users = get_json_file('users') ?? [];
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add_user';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!validate_csrf_token($csrfToken)) {
        $msg = 'CSRF validation failed. Please refresh and try again.';
        $msgType = 'danger';
    } else {
        // 1. Add New User
        if ($action === 'add_user') {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $role = $_POST['role'] ?? 'salesman';

            if (empty($username) || empty($password) || empty($name)) {
                $msg = 'Please fill out all required fields.';
                $msgType = 'danger';
            } else {
                $valRes = PasswordPolicy::validatePassword($password, $username, $name);
                if (!$valRes['valid']) {
                    $msg = 'Password strength failed: ' . implode(' ', $valRes['errors']);
                    $msgType = 'danger';
                } else {
                    $exists = false;
                    foreach ($users as $u) {
                        if (strtolower($u['username']) === strtolower($username)) {
                            $exists = true; break;
                        }
                    }
                    if ($exists) {
                        $msg = 'Username already exists! Please choose another.';
                        $msgType = 'danger';
                    } else {
                        $newUser = [
                            'id' => 'user-' . time(),
                            'username' => $username,
                            'password' => PasswordPolicy::hashPassword($password),
                            'name' => $name,
                            'role' => $role,
                            'phone' => trim($_POST['phone'] ?? ''),
                            'status' => 'active',
                            'totp_secret' => '',
                            'totp_enabled' => false,
                            'force_password_change' => false,
                            'createdAt' => date('c')
                        ];
                        $users[] = $newUser;
                        save_json_file('users', $users);

                        SecurityLogger::logEvent($sessionUser['username'], 'super_admin', 'USER_CREATED', "Created account '{$username}' with role '{$role}'");
                        $msg = "User account '{$name}' created successfully with strong password!";
                        $msgType = 'success';
                    }
                }
            }
        }

        // 2. Delete User / Remove Salesman
        if ($action === 'delete_user') {
            $deleteId = $_POST['user_id'] ?? '';
            if ($deleteId) {
                if ($sessionUser && ($sessionUser['userId'] ?? '') === $deleteId) {
                    $msg = 'You cannot delete your own active logged-in account!';
                    $msgType = 'danger';
                } else {
                    $targetName = '';
                    $targetUser = '';
                    $users = array_values(array_filter($users, function($u) use ($deleteId, &$targetName, &$targetUser) {
                        if ($u['id'] === $deleteId) {
                            $targetName = $u['name'];
                            $targetUser = $u['username'];
                            return false;
                        }
                        return true;
                    }));
                    save_json_file('users', $users);

                    SecurityLogger::logEvent($sessionUser['username'], 'super_admin', 'USER_DELETED', "Deleted account '{$targetUser}' ({$targetName})");
                    $msg = "Account '{$targetName}' deleted successfully!";
                    $msgType = 'success';
                }
            }
        }

        // 3. Reset User Password (Super Admin resetting salesman password)
        if ($action === 'reset_user_password') {
            $resetId = $_POST['user_id'] ?? '';
            $resetPass = trim($_POST['new_user_password'] ?? '');

            if (empty($resetId) || empty($resetPass)) {
                $msg = 'User ID and new password are required.';
                $msgType = 'danger';
            } else {
                $valRes = PasswordPolicy::validatePassword($resetPass);
                if (!$valRes['valid']) {
                    $msg = 'Password strength failed: ' . implode(' ', $valRes['errors']);
                    $msgType = 'danger';
                } else {
                    $resetName = '';
                    $resetUsername = '';
                    foreach ($users as &$u) {
                        if ($u['id'] === $resetId) {
                            $u['password'] = PasswordPolicy::hashPassword($resetPass);
                            $u['force_password_change'] = true;
                            $resetName = $u['name'];
                            $resetUsername = $u['username'];
                            break;
                        }
                    }
                    save_json_file('users', $users);

                    SecurityLogger::logEvent($sessionUser['username'], 'super_admin', 'USER_PASSWORD_RESET', "Reset password for user '{$resetUsername}'");
                    $msg = "Password for '{$resetName}' updated successfully!";
                    $msgType = 'success';
                }
            }
        }
    }
}
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content">
        <div class="page-header">
            <div>
                <h1><i class="fa-solid fa-user-shield" style="color:var(--pos-red); margin-right:10px;"></i> User Accounts & Roles</h1>
                <p class="page-header-sub">Manage salesman accounts, system roles, delete users, and enforce password security</p>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="login-error" style="margin-bottom:20px; background:<?php echo $msgType === 'success' ? '#ecfdf5' : '#fef2f2'; ?>; border:1px solid <?php echo $msgType === 'success' ? '#a7f3d0' : '#fecaca'; ?>; color:<?php echo $msgType === 'success' ? '#065f46' : '#dc2626'; ?>; padding:12px 16px; border-radius:8px; font-weight:700;">
                <i class="fa-solid <?php echo $msgType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:24px;">
            <div style="display:flex; flex-direction:column; gap:20px;">
                <!-- Add New User Form -->
                <div class="pos-card">
                    <h3 class="pos-card-title" style="margin-bottom:16px;">
                        <i class="fa-solid fa-user-plus" style="color:var(--pos-gold); margin-right:8px;"></i> Add New User Account
                    </h3>
                    <form method="POST" action="users.php">
                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                        <input type="hidden" name="action" value="add_user">

                        <div class="form-group" style="margin-bottom:12px;">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-input" placeholder="e.g. Salesman Ahmad" required>
                        </div>

                        <div class="form-group" style="margin-bottom:12px;">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-input" placeholder="ahmad1" required>
                        </div>

                        <div class="form-group" style="margin-bottom:12px;">
                            <label class="form-label">Strong Password * (Min 14 chars, A-Z, a-z, 0-9, !@#$)</label>
                            <input type="password" name="password" class="form-input" placeholder="e.g. SalesmanAhmad#2026!" required>
                        </div>

                        <div class="form-group" style="margin-bottom:16px;">
                            <label class="form-label">Role *</label>
                            <select name="role" class="form-select">
                                <option value="salesman">Salesman / POS Operator</option>
                                <option value="cashier">Cashier</option>
                                <option value="manager">Store Manager</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>

                        <button type="submit" class="pos-btn pos-btn-primary pos-btn-block">
                            <i class="fa-solid fa-user-plus"></i> Create Secure Account
                        </button>
                    </form>
                </div>
            </div>

            <!-- Users List Table with Delete & Password Actions -->
            <div class="data-table-wrap">
                <div class="data-table-toolbar">
                    <h3 class="pos-card-title"><i class="fa-solid fa-users" style="color:var(--pos-red); margin-right:8px;"></i> System User Directory</h3>
                    <span class="status-badge status-active"><?php echo count($users); ?> Total Accounts</span>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>2FA Status</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($u['name']); ?></strong></td>
                                <td><strong style="color:var(--pos-red);"><?php echo htmlspecialchars($u['username']); ?></strong></td>
                                <td>
                                    <span class="status-badge <?php echo $u['role'] === 'super_admin' ? 'status-active' : 'status-completed'; ?>">
                                        <?php echo strtoupper(str_replace('_', ' ', $u['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo !empty($u['totp_enabled']) ? 'status-active' : 'status-pending'; ?>">
                                        <?php echo !empty($u['totp_enabled']) ? '2FA ON' : 'OFF'; ?>
                                    </span>
                                </td>
                                <td><span class="status-badge status-active"><?php echo strtoupper($u['status'] ?? 'active'); ?></span></td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex; gap:6px;">
                                        <!-- Reset User Password Button -->
                                        <button type="button" onclick="promptResetPassword('<?php echo $u['id']; ?>', '<?php echo htmlspecialchars($u['name']); ?>')" class="pos-btn pos-btn-outline pos-btn-sm" style="color:var(--pos-gold-dark); border-color:var(--pos-gold-dark);" title="Reset User Password">
                                            <i class="fa-solid fa-lock-open"></i> Reset Pass
                                        </button>

                                        <!-- Delete User Account (Super Admin) -->
                                        <?php if (($sessionUser['userId'] ?? '') !== $u['id']): ?>
                                            <form method="POST" action="users.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete account \'<?php echo htmlspecialchars($u['name']); ?>\'? This action cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" class="pos-btn pos-btn-sm" style="background:#ef4444; color:#fff;" title="Delete User Account">
                                                    <i class="fa-solid fa-trash-can"></i> Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="font-size:0.75rem; color:#9ca3af; font-weight:700; padding:4px 8px;">(Current)</span>
                                        <?php endif; ?>
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

<!-- Reset Password Modal Script -->
<script>
function promptResetPassword(userId, userName) {
    const newPass = prompt("Enter new strong password for " + userName + " (Min 14 chars, A-Z, a-z, 0-9, !@#$):");
    if (newPass && newPass.trim() !== "") {
        const form = document.createElement("form");
        form.method = "POST";
        form.action = "users.php";

        const csrfInput = document.createElement("input");
        csrfInput.type = "hidden";
        csrfInput.name = "csrf_token";
        csrfInput.value = "<?php echo get_csrf_token(); ?>";
        form.appendChild(csrfInput);

        const actionInput = document.createElement("input");
        actionInput.type = "hidden";
        actionInput.name = "action";
        actionInput.value = "reset_user_password";
        form.appendChild(actionInput);

        const idInput = document.createElement("input");
        idInput.type = "hidden";
        idInput.name = "user_id";
        idInput.value = userId;
        form.appendChild(idInput);

        const passInput = document.createElement("input");
        passInput.type = "hidden";
        passInput.name = "new_user_password";
        passInput.value = newPass.trim();
        form.appendChild(passInput);

        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
