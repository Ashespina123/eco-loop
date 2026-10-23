<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$pageTitle = 'Admin Dashboard | Eco-Loop';
$admin = currentUser();
$connection = db();
$stats = [
    ['Total registered customers', 'SELECT COUNT(*) total FROM users WHERE role="user" AND status="active"', 'users.php'],
    ['New pickup requests', 'SELECT COUNT(*) total FROM pickup_requests WHERE created_at >= NOW() - INTERVAL 1 DAY', 'pickups.php?status=Pending'],
    ['Pending pickup requests', 'SELECT COUNT(*) total FROM pickup_requests WHERE status="Pending"', 'pickups.php?status=Pending'],
    ['Accepted requests', 'SELECT COUNT(*) total FROM pickup_requests WHERE status IN ("Approved","Scheduled")', 'pickups.php?status=Approved'],
    ['Pickups in progress', 'SELECT COUNT(*) total FROM pickup_requests WHERE status IN ("Collector Assigned","On the Way","Collected")', 'pickups.php?status=Collector%20Assigned'],
    ['Completed pickups', 'SELECT COUNT(*) total FROM pickup_requests WHERE status="Completed"', 'pickups.php?status=Completed'],
    ['Reward points awarded', 'SELECT COALESCE(SUM(points),0) total FROM points_transactions WHERE transaction_type="earned"', 'reports.php'],
    ['Pending redemptions', 'SELECT COUNT(*) total FROM reward_redemptions WHERE status="Pending"', 'redemptions.php?status=Pending'],
    ['Active drop-off centers', 'SELECT COUNT(*) total FROM collection_points WHERE is_active=1', 'collection-points.php'],
];
foreach ($stats as &$stat) $stat[] = $connection->query($stat[1])->fetch_assoc()['total'];
unset($stat);
$recentPickups = $connection->query('SELECT p.id,p.reference_number,p.status,p.created_at,u.full_name FROM pickup_requests p JOIN users u ON u.id=p.user_id ORDER BY p.created_at DESC,p.id DESC LIMIT 6')->fetch_all(MYSQLI_ASSOC);
$recentCompleted = $connection->query('SELECT p.id,p.reference_number,p.updated_at,u.full_name FROM pickup_requests p JOIN users u ON u.id=p.user_id WHERE p.status="Completed" ORDER BY p.updated_at DESC LIMIT 5')->fetch_all(MYSQLI_ASSOC);
$recentRedemptions = $connection->query('SELECT rr.id,rr.status,rr.created_at,r.name AS reward_name,u.full_name FROM reward_redemptions rr JOIN rewards r ON r.id=rr.reward_id JOIN users u ON u.id=rr.user_id ORDER BY rr.created_at DESC LIMIT 5')->fetch_all(MYSQLI_ASSOC);
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell"><?php require __DIR__ . '/../includes/navigation.php'; ?><main class="main-content">
    <div class="page-header"><div><span class="eyebrow">Administration</span><h1>EcoLoop Admin Dashboard</h1><p class="muted-text">Operations overview for <?php echo e($admin['full_name']); ?>.</p></div></div>
    <div class="stats-grid"><?php foreach ($stats as [$label, $sql, $url, $value]): ?><a class="stat-card" href="<?php echo e(APP_ROOT . '/admin/' . $url); ?>"><span><?php echo e($label); ?></span><strong><?php echo e(number_format((float) $value)); ?></strong></a><?php endforeach; ?></div>
    <div class="info-grid">
        <section class="content-card"><div class="section-heading"><h2>Latest pickup requests</h2><a class="btn btn-secondary" href="<?php echo e(APP_ROOT . '/admin/pickups.php'); ?>">Manage</a></div><?php if ($recentPickups): ?><ul class="timeline-list"><?php foreach ($recentPickups as $row): ?><li><a href="<?php echo e(APP_ROOT . '/admin/pickups.php?search=' . rawurlencode($row['reference_number'])); ?>"><strong><?php echo e($row['reference_number']); ?></strong> · <?php echo e($row['full_name']); ?></a><span class="status-pill"><?php echo e($row['status'] === 'Approved' ? 'Accepted' : $row['status']); ?></span></li><?php endforeach; ?></ul><?php else: ?><p>No pickup requests yet.</p><?php endif; ?></section>
        <section class="content-card"><div class="section-heading"><h2>Recently completed</h2><a href="<?php echo e(APP_ROOT . '/admin/reports.php'); ?>">Reports</a></div><?php if ($recentCompleted): ?><ul class="timeline-list"><?php foreach ($recentCompleted as $row): ?><li><span><strong><?php echo e($row['reference_number']); ?></strong> · <?php echo e($row['full_name']); ?></span><small><?php echo e(date('M j, Y g:i A', strtotime($row['updated_at']))); ?></small></li><?php endforeach; ?></ul><?php else: ?><p>No completed collections yet.</p><?php endif; ?></section>
        <section class="content-card"><div class="section-heading"><h2>Latest redemptions</h2><a class="btn btn-secondary" href="<?php echo e(APP_ROOT . '/admin/redemptions.php'); ?>">Review</a></div><?php if ($recentRedemptions): ?><ul class="timeline-list"><?php foreach ($recentRedemptions as $row): ?><li><span><strong><?php echo e($row['reward_name']); ?></strong> · <?php echo e($row['full_name']); ?></span><span class="status-pill"><?php echo e($row['status']); ?></span></li><?php endforeach; ?></ul><?php else: ?><p>No redemption activity yet.</p><?php endif; ?></section>
    </div>
</main></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
