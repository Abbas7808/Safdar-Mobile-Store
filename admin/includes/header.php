<?php
// SMS Admin Header Include with Hardened RBAC & Secret Access Control
require_once __DIR__ . '/../../backend/config.php';

// Check Secret Admin Portal Access Gateway
if (!SecretAdminUrlManager::isAccessGranted() && !get_session_user() && !defined('ALLOW_ANONYMOUS')) {
    http_response_code(404);
    SecurityLogger::logEvent('GUEST', 'GUEST', 'DIRECT_ADMIN_PAGE_BLOCKED', "Direct access to " . $_SERVER['PHP_SELF'] . " attempted without secret portal token", 'DENIED');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>404 Not Found</title><style>body { font-family: sans-serif; background: #f8fafc; color: #334155; text-align: center; padding: 50px 20px; } h1 { font-size: 48px; color: #64748b; margin-bottom: 10px; } p { font-size: 18px; color: #94a3b8; }</style></head><body><h1>404 Not Found</h1><p>The requested URL was not found on this server.</p></body></html>';
    exit();
}

$sessionUser = get_session_user();

if (!$sessionUser && !defined('ALLOW_ANONYMOUS')) {
    header('Location: login.php');
    exit();
}

// Strict Role Enforcement per page
if ($sessionUser && !defined('ALLOW_ANONYMOUS')) {
    $role = $sessionUser['role'] ?? 'salesman';
    $currentPageName = basename($_SERVER['PHP_SELF']);
    
    // Pages allowed for Salesman role: pos.php, sales.php, customers.php, login.php
    $salesmanAllowedPages = ['pos.php', 'sales.php', 'customers.php', 'login.php'];
    
    if ($role === 'salesman' && !in_array($currentPageName, $salesmanAllowedPages)) {
        SecurityLogger::logEvent($sessionUser['username'], $role, 'SALESMAN_UNAUTHORIZED_PAGE_ATTEMPT', "Salesman attempted to open admin page '{$currentPageName}'", 'DENIED');
        http_response_code(403);
        header('Location: pos.php?error=access_denied');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo get_csrf_token(); ?>">
    <title>Safdar POS & Management System</title>
    
    <script>
        (function() {
            var theme = localStorage.getItem('smz_theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>

    <!-- High Performance DNS Pre-connects & Fonts -->
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <?php 
    $curFile = basename($_SERVER['PHP_SELF']);
    if ($curFile === 'index.php' || $curFile === 'reports.php'): 
    ?>
    <!-- Chart.js for Reports & Dashboard (Deferred) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <?php endif; ?>

    <!-- Admin Stylesheet -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo @filemtime(__DIR__ . '/../../assets/css/admin.css') ?: time(); ?>">
</head>
<body class="admin-root">
