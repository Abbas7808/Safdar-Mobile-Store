<?php
define('ALLOW_ANONYMOUS', true);
define('IS_ADMIN_LOGIN', true);
require_once __DIR__ . '/../backend/config.php';

// Check Secret Admin Portal Access Gateway
if (!SecretAdminUrlManager::isAccessGranted() && !get_session_user()) {
    http_response_code(404);
    SecurityLogger::logEvent('GUEST', 'GUEST', 'DIRECT_ADMIN_URL_BLOCKED', "Direct access to /admin/login.php attempted without secret URL token", 'DENIED');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>404 Not Found</title><style>body { font-family: sans-serif; background: #f8fafc; color: #334155; text-align: center; padding: 50px 20px; } h1 { font-size: 48px; color: #64748b; margin-bottom: 10px; } p { font-size: 18px; color: #94a3b8; }</style></head><body><h1>404 Not Found</h1><p>The requested URL was not found on this server.</p></body></html>';
    exit();
}

$error = '';
$step = isset($_SESSION['pending_2fa_user']) ? 2 : 1;

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $user = get_session_user();
    if ($user) {
        SecurityLogger::logEvent($user['username'], $user['role'], 'LOGOUT', 'User logged out of system');
    }
    unset($_SESSION['user']);
    unset($_SESSION['pending_2fa_user']);
    unset($_SESSION['admin_access_granted']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: login.php');
    exit();
}

// Cancel 2FA step back to step 1
if (isset($_GET['cancel_2fa'])) {
    unset($_SESSION['pending_2fa_user']);
    header('Location: login.php');
    exit();
}

// Redirect if already authenticated
if (get_session_user()) {
    $user = get_session_user();
    if ($user['role'] === 'salesman') {
        header('Location: pos.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

// Process POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrfToken)) {
        $error = 'Security validation failed (CSRF token invalid). Please refresh and try again.';
        SecurityLogger::logEvent('GUEST', 'GUEST', 'CSRF_VALIDATION_FAILED', 'CSRF token mismatch on admin login', 'DENIED');
    } else {
        // STEP 2: TOTP Verification Process
        if ($step === 2 && isset($_SESSION['pending_2fa_user'])) {
            $pendingUser = $_SESSION['pending_2fa_user'];
            $totpCode = trim($_POST['totp_code'] ?? '');

            if (empty($totpCode)) {
                $error = 'Please enter your 6-digit Authenticator app code.';
            } else {
                $secret = $pendingUser['totp_secret'] ?? '';
                if (TotpEngine::verifyCode($secret, $totpCode)) {
                    // Successful 2FA Auth
                    session_regenerate_id(true);
                    $sessionData = [
                        'userId' => $pendingUser['id'],
                        'username' => $pendingUser['username'],
                        'name' => $pendingUser['name'],
                        'role' => $pendingUser['role'],
                        'loginAt' => date('c'),
                        '2fa_verified' => true
                    ];
                    $_SESSION['user'] = $sessionData;
                    $_SESSION['last_activity'] = time();
                    unset($_SESSION['pending_2fa_user']);

                    SecurityRateLimiter::clearFailedAttempts($pendingUser['username']);
                    ActiveSessionManager::registerSession($pendingUser['id'], $pendingUser['username'], $pendingUser['role']);
                    SecurityLogger::logEvent($pendingUser['username'], $pendingUser['role'], 'LOGIN_SUCCESS', 'User authenticated successfully with 2FA');

                    if ($pendingUser['role'] === 'salesman') {
                        header('Location: pos.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit();
                } else {
                    $error = 'Invalid 6-digit Authenticator code. Please try again.';
                    SecurityRateLimiter::recordFailedAttempt($pendingUser['username']);
                    SecurityLogger::logEvent($pendingUser['username'], $pendingUser['role'], '2FA_FAILED', 'Invalid TOTP code entered during login', 'FAILED');
                }
            }
        } 
        // STEP 1: Username & Password Verification
        else {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');

            // Rate Limit Check
            $rateCheck = SecurityRateLimiter::checkRateLimit($username);
            if (!$rateCheck['allowed']) {
                $error = $rateCheck['message'];
                SecurityLogger::logEvent($username ?: 'UNKNOWN', 'GUEST', 'RATE_LIMIT_BLOCKED', "Login blocked due to rate limit ($username)", 'DENIED');
            } else {
                $users = get_json_file('users') ?? [];
                $found = null;

                foreach ($users as $u) {
                    $uUsername = strtolower($u['username'] ?? '');
                    $uEmail = strtolower($u['email'] ?? '');
                    $inputName = strtolower($username);

                    $matches = ($uUsername === $inputName) || 
                               ($uEmail === $inputName) || 
                               ($inputName === 'admin' && ($u['role'] ?? '') === 'super_admin') ||
                               ($inputName === 'salesman' && ($u['role'] ?? '') === 'salesman');

                    if ($matches && ($u['status'] ?? 'active') === 'active') {
                        if (PasswordPolicy::verifyPassword($password, $u['password'])) {
                            $found = $u;
                            break;
                        }
                    }
                }

                if ($found) {
                    // Auto-rehash password if stored as plaintext or old algorithm
                    if (strpos($found['password'], '$2y$') !== 0) {
                        foreach ($users as &$usr) {
                            if ($usr['id'] === $found['id']) {
                                $usr['password'] = PasswordPolicy::hashPassword($password);
                                break;
                            }
                        }
                        save_json_file('users', $users);
                    }

                    // Check if 2FA is enabled for this user
                    if (!empty($found['totp_enabled']) && !empty($found['totp_secret'])) {
                        $_SESSION['pending_2fa_user'] = $found;
                        $step = 2;
                    } else {
                        // Complete Single-Factor Auth
                        session_regenerate_id(true);
                        $sessionData = [
                            'userId' => $found['id'],
                            'username' => $found['username'],
                            'name' => $found['name'],
                            'role' => $found['role'],
                            'loginAt' => date('c'),
                            '2fa_verified' => false
                        ];
                        $_SESSION['user'] = $sessionData;
                        $_SESSION['last_activity'] = time();

                        SecurityRateLimiter::clearFailedAttempts($found['username']);
                        ActiveSessionManager::registerSession($found['id'], $found['username'], $found['role']);
                        SecurityLogger::logEvent($found['username'], $found['role'], 'LOGIN_SUCCESS', 'User authenticated successfully');

                        if ($found['role'] === 'salesman') {
                            header('Location: pos.php');
                        } else {
                            header('Location: index.php');
                        }
                        exit();
                    }
                } else {
                    SecurityRateLimiter::recordFailedAttempt($username);
                    $error = 'Invalid username or password.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Admin Login | Safdar POS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Outfit:wght@700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-root">
    <div class="login-page" style="width:100%; display:flex; align-items:center; justify-content:center; min-height:100vh; background:#0f172a; padding:20px;">
        <div class="login-card" style="text-align:center; max-width:440px; width:100%; background:#1e293b; padding:36px; border-radius:16px; border:1px solid #334155; box-shadow:0 25px 50px -12px rgba(0,0,0,0.7);">
            
            <img src="../assets/images/logo.jpg" alt="Safdar Mobile Store Logo" style="width:90px; height:90px; object-fit:cover; border-radius:50%; margin:0 auto 16px auto; box-shadow:0 0 25px rgba(244,196,48,0.4); border:2px solid var(--pos-gold);">
            <h2 style="font-family:var(--pos-font-heading); font-size:1.5rem; font-weight:900; color:#fff; margin-bottom:4px;">Safdar Mobile Store</h2>
            <p class="login-subtitle" style="margin-bottom:24px; color:#94a3b8; font-size:0.85rem; font-weight:600;">
                <i class="fa-solid fa-shield-halved" style="color:var(--pos-gold); margin-right:4px;"></i> Secure Administrator Authentication
            </p>

            <?php if (isset($_GET['expired'])): ?>
                <div class="login-error" style="margin-bottom:18px; background:#fffbeb; border:1px solid #fef3c7; color:#b45309; padding:10px 14px; border-radius:8px; font-size:0.85rem; text-align:left;">
                    <i class="fa-solid fa-clock-rotate-left"></i> Session expired due to inactivity. Please log in again.
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="login-error" style="margin-bottom:18px; background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:12px 14px; border-radius:8px; font-size:0.85rem; text-align:left; line-height:1.4;">
                    <i class="fa-solid fa-triangle-exclamation" style="margin-right:6px;"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($step === 2 && isset($_SESSION['pending_2fa_user'])): ?>
                <!-- STEP 2: 2FA TOTP Code Prompt -->
                <form method="POST" action="login.php" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                    
                    <div style="background:#0f172a; padding:16px; border-radius:10px; border:1px solid #334155; margin-bottom:20px; text-align:center;">
                        <i class="fa-solid fa-mobile-screen-button" style="font-size:2rem; color:var(--pos-gold); margin-bottom:10px;"></i>
                        <h4 style="color:#fff; margin-bottom:6px; font-size:1rem;">Two-Factor Authentication</h4>
                        <p style="color:#94a3b8; font-size:0.8rem; margin:0;">Enter the 6-digit verification code from your Authenticator app for account <strong><?php echo htmlspecialchars($_SESSION['pending_2fa_user']['username']); ?></strong>.</p>
                    </div>

                    <div class="form-group" style="text-align:left; margin-bottom:20px;">
                        <label class="form-label" style="color:#e2e8f0; font-size:0.85rem;">6-Digit Authenticator Code</label>
                        <input type="text" id="totpCode" name="totp_code" class="form-input" style="background:#0f172a; border-color:var(--pos-gold); color:#fff; font-size:1.4rem; letter-spacing:6px; text-align:center; font-weight:800;" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus autocomplete="off">
                    </div>

                    <button type="submit" class="login-btn" style="background:var(--pos-gold); color:#000; font-weight:800; width:100%; padding:12px; border-radius:8px; border:none; cursor:pointer; font-size:0.95rem; margin-bottom:12px;">
                        <i class="fa-solid fa-lock"></i> Verify 2FA & Access Admin
                    </button>

                    <a href="login.php?cancel_2fa=1" style="color:#94a3b8; font-size:0.8rem; text-decoration:none;">
                        <i class="fa-solid fa-arrow-left"></i> Back to Login
                    </a>
                </form>
            <?php else: ?>
                <!-- STEP 1: Username & Password -->
                <form method="POST" action="login.php" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

                    <div class="form-group" style="text-align:left; margin-bottom:16px;">
                        <label class="form-label" style="color:#e2e8f0; font-size:0.85rem; font-weight:600;">Admin / Salesman Username</label>
                        <div style="position:relative;">
                            <input type="text" id="loginUsername" name="username" class="form-input" style="background:#0f172a; border-color:#475569; color:#fff; padding-left:38px;" placeholder="Enter your username" required autofocus autocomplete="username">
                            <i class="fa-solid fa-user" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#64748b;"></i>
                        </div>
                    </div>

                    <div class="form-group" style="text-align:left; margin-bottom:24px;">
                        <label class="form-label" style="color:#e2e8f0; font-size:0.85rem; font-weight:600;">Password</label>
                        <div style="position:relative;">
                            <input type="password" id="loginPassword" name="password" class="form-input" style="background:#0f172a; border-color:#475569; color:#fff; padding-left:38px;" placeholder="••••••••••••" required autocomplete="current-password">
                            <i class="fa-solid fa-lock" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#64748b;"></i>
                        </div>
                    </div>

                    <button type="submit" class="login-btn" style="background:var(--pos-red); color:#fff; font-weight:800; width:100%; padding:13px; border-radius:8px; border:none; cursor:pointer; font-size:0.95rem; box-shadow:0 4px 12px rgba(220,38,38,0.3);">
                        <i class="fa-solid fa-right-to-bracket"></i> Secure System Sign In
                    </button>
                </form>
            <?php endif; ?>

            <div style="margin-top:28px; padding-top:16px; border-top:1px solid #334155; text-align:center;">
                <p style="font-size:0.75rem; color:#64748b; margin:0;">
                    <i class="fa-solid fa-shield-cat"></i> SMS Security Engine Active | Protected by HTTPS & Rate Limiting
                </p>
            </div>
        </div>
    </div>
</body>
</html>
