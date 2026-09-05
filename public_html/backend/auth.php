<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'login' || empty($action))) {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = isset($input['username']) ? trim($input['username']) : trim($_POST['username'] ?? '');
    $password = isset($input['password']) ? trim($input['password']) : trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        json_response('error', 'Username and password are required');
    }

    $rateCheck = SecurityRateLimiter::checkRateLimit($username);
    if (!$rateCheck['allowed']) {
        SecurityLogger::logEvent($username, 'GUEST', 'API_RATE_LIMIT_BLOCKED', 'Login attempt blocked by rate limiter', 'DENIED');
        json_response('error', $rateCheck['message']);
    }

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
        // Auto-rehash password if stored as plaintext or legacy
        if (strpos($found['password'], '$2y$') !== 0) {
            foreach ($users as &$usr) {
                if ($usr['id'] === $found['id']) {
                    $usr['password'] = PasswordPolicy::hashPassword($password);
                    break;
                }
            }
            save_json_file('users', $users);
        }

        // Check if 2FA TOTP code is required
        if (!empty($found['totp_enabled']) && !empty($found['totp_secret'])) {
            $totpCode = isset($input['totp_code']) ? trim($input['totp_code']) : trim($_POST['totp_code'] ?? '');
            if (empty($totpCode) || !TotpEngine::verifyCode($found['totp_secret'], $totpCode)) {
                SecurityRateLimiter::recordFailedAttempt($username);
                json_response('error', 'Two-Factor Authentication (2FA) required or invalid code', ['requires_2fa' => true]);
            }
        }

        session_regenerate_id(true);
        $sessionData = [
            'userId' => $found['id'],
            'username' => $found['username'],
            'name' => $found['name'],
            'role' => $found['role'],
            'loginAt' => date('c')
        ];
        $_SESSION['user'] = $sessionData;
        $_SESSION['last_activity'] = time();

        SecurityRateLimiter::clearFailedAttempts($found['username']);
        ActiveSessionManager::registerSession($found['id'], $found['username'], $found['role']);
        SecurityLogger::logEvent($found['username'], $found['role'], 'API_LOGIN_SUCCESS', 'User logged in via API');

        json_response('success', 'Login successful', $sessionData);
    } else {
        SecurityRateLimiter::recordFailedAttempt($username);
        json_response('error', 'Invalid username or password');
    }
}

if ($action === 'logout') {
    $user = get_session_user();
    if ($user) {
        SecurityLogger::logEvent($user['username'], $user['role'], 'API_LOGOUT', 'User logged out via API');
    }
    unset($_SESSION['user']);
    unset($_SESSION['admin_access_granted']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    json_response('success', 'Logged out successfully');
}

if ($action === 'check') {
    $user = get_session_user();
    if ($user) {
        json_response('success', 'Authenticated', $user);
    } else {
        json_response('error', 'Not authenticated', null);
    }
}

json_response('error', 'Invalid auth action');
