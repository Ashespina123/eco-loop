<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$pageTitle = 'Admin Activity Logs | Eco-Loop';
$logs = db()->query('SELECT l.id,l.action,l.details,l.created_at,u.full_name,u.email FROM admin_logs l LEFT JOIN users u ON u.id=l.admin_id ORDER BY l.created_at DESC,l.id DESC LIMIT 300')->fetch_all(MYSQLI_ASSOC);
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell"><?php require __DIR__ . '/../includes/navigation.php'; ?><main class="main-content">
<div class="page-header"><div><span class="eyebrow">Audit</span><h1>Admin Activity Logs</h1></div></div>
<section class="content-card"><p>Recent status, verification, redemption, settings, customer, and collection point changes appear here with the administrator and time.</p><?php if ($logs): ?><div class="table-wrap"><table><thead><tr><th>When</th><th>Administrator</th><th>Action</th><th>Record / reason</th></tr></thead><tbody><?php foreach ($logs as $log): ?><tr><td><?php echo e(date('M j, Y g:i A', strtotime($log['created_at']))); ?></td><td><?php echo e($log['full_name'] ?: 'Former admin'); ?><br><small><?php echo e($log['email'] ?: ''); ?></small></td><td><?php echo e($log['action']); ?></td><td><?php echo nl2br(e($log['details'] ?: '—')); ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state"><p>No administrator activity has been recorded yet.</p></div><?php endif; ?></section>
</main></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
