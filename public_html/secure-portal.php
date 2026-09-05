<?php
// SMS — Secret Admin Portal Access Gateway
require_once __DIR__ . '/backend/config.php';

$pathParam = $_GET['path'] ?? $_GET['access'] ?? $_SERVER['QUERY_STRING'] ?? '';
$secretSlug = SecretAdminUrlManager::getSecretSlug();

// Check if request matches secret slug
if (empty($pathParam) || trim($pathParam, '/?= ') === $secretSlug || strpos($_SERVER['REQUEST_URI'] ?? '', $secretSlug) !== false) {
    SecretAdminUrlManager::grantAccess();
    SecurityLogger::logEvent('GUEST', 'GUEST', 'SECRET_PORTAL_ACCESSED', "Valid secret admin portal entry accessed via slug '{$secretSlug}'");
    header('Location: admin/login.php');
    exit();
}

// Unauthorized / Mismatched Secret URL -> Generic 404 Not Found
http_response_code(404);
SecurityLogger::logEvent('GUEST', 'GUEST', 'SECRET_PORTAL_INVALID_ATTEMPT', "Invalid secret portal access attempt with param '{$pathParam}'", 'DENIED');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>404 Not Found</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #334155; text-align: center; padding: 50px 20px; }
        h1 { font-size: 48px; color: #64748b; margin-bottom: 10px; }
        p { font-size: 18px; color: #94a3b8; }
    </style>
</head>
<body>
    <h1>404 Not Found</h1>
    <p>The requested URL was not found on this server.</p>
</body>
</html>
