<?php
// SMS — Safdar Mobile Store Core Security Engine & Cryptographic Utilities
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// =========================================================================
// 1. STRENGTHENED PASSWORD POLICY VALIDATOR
// =========================================================================
class PasswordPolicy {
    public static function validatePassword($password, $username = '', $name = '') {
        $errors = [];

        if (strlen($password) < 14) {
            $errors[] = 'Password must be at least 14 characters long.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter (A-Z).';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter (a-z).';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number (0-9).';
        }
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?]/', $password)) {
            $errors[] = 'Password must contain at least one special character (e.g. !@#$%^&*).';
        }

        // Common weak blacklist (exact weak patterns)
        $blacklist = [
            '123456', '12345678', '123456789', 'password', 'admin123',
            'safdar123', 'sms123', 'smz1234', 'sale1234', 'qwerty', 'welcome',
            'pass1234', 'administrator', 'safdarmobile'
        ];

        $lowerPass = strtolower($password);
        foreach ($blacklist as $b) {
            if ($lowerPass === $b || strpos($lowerPass, $b) !== false) {
                $errors[] = "Password contains forbidden common weak phrase ('{$b}').";
                break;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword($password, $hash) {
        if (empty($hash) || empty($password)) return false;
        
        // Support bcrypt / argon2 hash verification
        if (password_verify($password, $hash)) {
            return true;
        }

        // Fallback for initial legacy plaintext passwords during seamless migration
        if ($password === $hash) {
            return true;
        }

        return false;
    }
}

// =========================================================================
// 2. NATIVE TOTP (2FA) ENGINE (RFC 6238)
// =========================================================================
class TotpEngine {
    private static $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret($length = 16) {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[random_int(0, 31)];
        }
        return $secret;
    }

    public static function base32Decode($base32) {
        $base32 = strtoupper($base32);
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $val = strpos(self::$base32Chars, $base32[$i]);
            if ($val === false) continue;

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $output;
    }

    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;

        $hashPart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashPart)[1] & 0x7FFFFFFF;

        $code = str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
        return $code;
    }

    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);
        $code = trim((string)$code);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        return false;
    }

    public static function getOtpAuthUrl($secret, $accountName = 'SuperAdmin', $issuer = 'Safdar Mobile Store') {
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($accountName);
        return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
    }

    public static function getQrCodeApiUrl($secret, $accountName = 'SuperAdmin', $issuer = 'Safdar Mobile Store') {
        $otpUrl = self::getOtpAuthUrl($secret, $accountName, $issuer);
        return "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($otpUrl);
    }
}

// =========================================================================
// 3. RATE LIMITER & BRUTE-FORCE MONITORING
// =========================================================================
class SecurityRateLimiter {
    private static function getFilePath() {
        return DATA_DIR . 'login_attempts.json';
    }

    private static function getAttempts() {
        if (!file_exists(self::getFilePath())) {
            return [];
        }
        $data = json_decode(file_get_contents(self::getFilePath()), true);
        return is_array($data) ? $data : [];
    }

    private static function saveAttempts($data) {
        file_put_contents(self::getFilePath(), json_encode($data, JSON_PRETTY_PRINT));
    }

    public static function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public static function checkRateLimit($username = '') {
        $ip = self::getClientIp();
        $attempts = self::getAttempts();
        $now = time();

        $ipKey = 'ip_' . md5($ip);
        $userKey = !empty($username) ? 'user_' . strtolower(trim($username)) : '';

        $maxAttempts = 5;
        $lockoutSeconds = 15 * 60; // 15 minutes

        foreach ([$ipKey, $userKey] as $key) {
            if (empty($key) || !isset($attempts[$key])) continue;

            $record = $attempts[$key];
            if ($record['count'] >= $maxAttempts) {
                $timePassed = $now - $record['lastAttempt'];
                if ($timePassed < $lockoutSeconds) {
                    $remaining = ceil(($lockoutSeconds - $timePassed) / 60);
                    return [
                        'allowed' => false,
                        'remainingMinutes' => $remaining,
                        'message' => "Too many failed attempts. Account / IP temporarily locked for {$remaining} more minutes."
                    ];
                }
            }
        }

        return ['allowed' => true, 'remainingMinutes' => 0, 'message' => ''];
    }

    public static function recordFailedAttempt($username = '') {
        $ip = self::getClientIp();
        $attempts = self::getAttempts();
        $now = time();

        $ipKey = 'ip_' . md5($ip);
        $userKey = !empty($username) ? 'user_' . strtolower(trim($username)) : '';

        foreach ([$ipKey, $userKey] as $key) {
            if (empty($key)) continue;

            if (!isset($attempts[$key]) || ($now - $attempts[$key]['lastAttempt'] > 30 * 60)) {
                $attempts[$key] = ['count' => 1, 'lastAttempt' => $now, 'ip' => $ip, 'username' => $username];
            } else {
                $attempts[$key]['count'] += 1;
                $attempts[$key]['lastAttempt'] = $now;
            }
        }

        self::saveAttempts($attempts);
        SecurityLogger::logEvent($username ?: 'UNKNOWN', 'GUEST', 'LOGIN_FAILED', "Failed login attempt from IP {$ip}", 'FAILED');
    }

    public static function clearFailedAttempts($username = '') {
        $ip = self::getClientIp();
        $attempts = self::getAttempts();
        $ipKey = 'ip_' . md5($ip);
        $userKey = !empty($username) ? 'user_' . strtolower(trim($username)) : '';

        unset($attempts[$ipKey]);
        if (!empty($userKey)) unset($attempts[$userKey]);

        self::saveAttempts($attempts);
    }
}

// =========================================================================
// 4. AUDIT & SECURITY LOGGER
// =========================================================================
class SecurityLogger {
    public static function logEvent($user, $role, $action, $details, $status = 'SUCCESS') {
        $ip = SecurityRateLimiter::getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $logEntry = [
            'id' => 'sec-' . time() . '-' . rand(100, 999),
            'user' => $user,
            'role' => $role,
            'action' => $action,
            'details' => $details,
            'status' => $status,
            'ip' => $ip,
            'userAgent' => substr($userAgent, 0, 150),
            'timestamp' => date('c')
        ];

        // 1. JSON storage
        $logs = get_json_file('security_logs') ?? [];
        array_unshift($logs, $logEntry);
        if (count($logs) > 500) {
            $logs = array_slice($logs, 0, 500);
        }
        save_json_file('security_logs', $logs);

        // 2. Also populate standard audit log
        $audit = get_json_file('audit') ?? [];
        array_unshift($audit, [
            'id' => 'aud-' . time() . '-' . rand(10, 99),
            'user' => $user,
            'action' => $action,
            'details' => "[$status] $details (IP: $ip)",
            'timestamp' => date('c')
        ]);
        if (count($audit) > 500) {
            $audit = array_slice($audit, 0, 500);
        }
        save_json_file('audit', $audit);

        // 3. MySQL PDO Attempt if connected
        try {
            $pdo = get_db_connection();
            if ($pdo) {
                $stmt = $pdo->prepare("INSERT INTO audit_logs (id, user, action, details, timestamp) VALUES (:id, :user, :action, :details, :ts)");
                $stmt->execute([
                    ':id' => $logEntry['id'],
                    ':user' => $user,
                    ':action' => $action,
                    ':details' => "[$status] $details (IP: $ip)",
                    ':ts' => date('Y-m-d H:i:s')
                ]);
            }
        } catch (Exception $e) {
            // Ignore DB error fallback to JSON
        }
    }
}

// =========================================================================
// 5. SECRET ADMIN URL & ACCESS MANAGER
// =========================================================================
class SecretAdminUrlManager {
    public static function getSecretSlug() {
        $secConfig = get_json_file('security_config') ?? [];
        return $secConfig['admin_secret_path'] ?? 'secure-management-portal';
    }

    public static function setSecretSlug($newSlug) {
        $newSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(trim($newSlug)));
        if (strlen($newSlug) < 6) return false;

        $secConfig = get_json_file('security_config') ?? [];
        $secConfig['admin_secret_path'] = $newSlug;
        save_json_file('security_config', $secConfig);
        return $newSlug;
    }

    public static function grantAccess() {
        $_SESSION['admin_access_granted'] = true;
        $_SESSION['admin_access_token'] = md5(self::getSecretSlug() . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    public static function isAccessGranted() {
        if (!isset($_SESSION['admin_access_granted']) || $_SESSION['admin_access_granted'] !== true) {
            return false;
        }
        $expectedToken = md5(self::getSecretSlug() . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        return hash_equals($_SESSION['admin_access_token'] ?? '', $expectedToken);
    }
}

// =========================================================================
// 6. ACTIVE SESSION & EMERGENCY LOCKDOWN MANAGER
// =========================================================================
class ActiveSessionManager {
    public static function isEmergencyLockdownActive() {
        $secConfig = get_json_file('security_config') ?? [];
        return !empty($secConfig['emergency_lockdown']);
    }

    public static function setEmergencyLockdown($status) {
        $secConfig = get_json_file('security_config') ?? [];
        $secConfig['emergency_lockdown'] = (bool)$status;
        save_json_file('security_config', $secConfig);
    }

    public static function registerSession($userId, $username, $role) {
        $sessionId = session_id();
        $sessions = get_json_file('active_sessions') ?? [];

        $sessions[$sessionId] = [
            'sessionId' => $sessionId,
            'userId' => $userId,
            'username' => $username,
            'role' => $role,
            'ip' => SecurityRateLimiter::getClientIp(),
            'userAgent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100),
            'loginAt' => date('c'),
            'lastActive' => time()
        ];

        save_json_file('active_sessions', $sessions);
    }

    public static function logoutAllOtherSessions($currentUserId) {
        $currentSessionId = session_id();
        $sessions = get_json_file('active_sessions') ?? [];

        $remaining = [];
        foreach ($sessions as $sid => $sess) {
            if ($sess['userId'] === $currentUserId && $sid !== $currentSessionId) {
                // Remove other session
                continue;
            }
            $remaining[$sid] = $sess;
        }

        save_json_file('active_sessions', $remaining);
    }

    public static function getActiveSessions() {
        return get_json_file('active_sessions') ?? [];
    }
}
