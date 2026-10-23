<?php
$user = currentUser();
$base = APP_ROOT;
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <strong>Eco-Loop</strong>
        <span><?php echo e($user['role'] ?? 'Guest'); ?></span>
    </div>
    <nav class="sidebar-nav">
        <?php if (($user['role'] ?? null) === 'admin'): ?>
            <a href="<?php echo e($base . '/admin/dashboard.php'); ?>">Dashboard</a>
            <a href="<?php echo e($base . '/admin/pickups.php'); ?>">Pickup Management</a>
            <a href="<?php echo e($base . '/admin/pickup-map.php'); ?>">Pickup Map</a>
            <a href="<?php echo e($base . '/admin/users.php'); ?>">User Management</a>
            <a href="<?php echo e($base . '/admin/collectors.php'); ?>">Collector Management</a>
            <a href="<?php echo e($base . '/admin/rewards.php'); ?>">Rewards</a>
            <a href="<?php echo e($base . '/admin/reports.php'); ?>">Reports</a>
            <a href="<?php echo e($base . '/admin/activity-logs.php'); ?>">Activity Logs</a>
            <a href="<?php echo e($base . '/logout.php'); ?>">Logout</a>
        <?php elseif (($user['role'] ?? null) === 'collector'): ?>
            <a href="<?php echo e($base . '/collector/dashboard.php'); ?>">Dashboard</a>
            <a href="<?php echo e($base . '/logout.php'); ?>">Logout</a>
        <?php else: ?>
            <a href="<?php echo e($base . '/user/dashboard.php'); ?>">Dashboard</a>
            <a href="<?php echo e($base . '/user/request-pickup.php'); ?>">Request Pickup</a>
            <a href="<?php echo e($base . '/user/my-pickups.php'); ?>">My Pickups</a>
            <a href="<?php echo e($base . '/user/pickup-map.php'); ?>">Pickup Map</a>
            <a href="<?php echo e($base . '/user/rewards.php'); ?>">My Rewards</a>
            <a href="<?php echo e($base . '/user/redemptions.php'); ?>">Redemption History</a>
            <a href="<?php echo e($base . '/user/notifications.php'); ?>">Notifications</a>
            <a href="<?php echo e($base . '/user/profile.php'); ?>">My Profile</a>
            <a href="<?php echo e($base . '/logout.php'); ?>">Logout</a>
        <?php endif; ?>
    </nav>
</aside>
