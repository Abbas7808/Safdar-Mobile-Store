<?php
$currentPage = 'audit';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$audit = get_json_file('audit') ?? [];
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="pos-content">
        <div class="page-header">
            <div>
                <h1><i class="fa-solid fa-clock-rotate-left" style="color:var(--pos-red); margin-right:10px;"></i> Audit Trail & Activity Logs</h1>
                <p class="page-header-sub">Immutable security logs tracking all admin and salesman operations</p>
            </div>
        </div>

        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details & Context</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($audit)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:30px;">No audit activity recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach (array_reverse($audit) as $a): ?>
                            <tr>
                                <td><?php echo date('M d, Y h:i A', strtotime($a['timestamp'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($a['user']); ?></strong></td>
                                <td><span class="status-badge status-active"><?php echo htmlspecialchars($a['action']); ?></span></td>
                                <td><?php echo htmlspecialchars($a['details']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
