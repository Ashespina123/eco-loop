<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('user');
$pageTitle = 'User Dashboard | Eco-Loop';
$user = currentUser();

$pointsBalance = getPointsBalance((int) $user['id']);
$pendingCount = db()->query('SELECT COUNT(*) AS total FROM pickup_requests WHERE user_id = ' . (int) $user['id'] . ' AND status = "Pending"')->fetch_assoc()['total'];
$scheduledCount = db()->query('SELECT COUNT(*) AS total FROM pickup_requests WHERE user_id = ' . (int) $user['id'] . ' AND status = "Scheduled"')->fetch_assoc()['total'];
$completedCount = db()->query('SELECT COUNT(*) AS total FROM pickup_requests WHERE user_id = ' . (int) $user['id'] . ' AND status = "Completed"')->fetch_assoc()['total'];
$nextPickup = db()->query('SELECT * FROM pickup_requests WHERE user_id = ' . (int) $user['id'] . ' AND status IN ("Scheduled","Collector Assigned","On the Way","Collected") ORDER BY preferred_date ASC, preferred_time ASC LIMIT 1')->fetch_assoc();
$activePickup = db()->query('SELECT * FROM pickup_requests WHERE user_id = ' . (int) $user['id'] . ' AND status NOT IN ("Completed","Cancelled") ORDER BY created_at DESC LIMIT 1')->fetch_assoc();
$recentPickups = userRecentPickups((int) $user['id'], 5);

require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div>
                <span class="eyebrow">Welcome</span>
                <h1>Hello, <?php echo e($user['full_name']); ?></h1>
            </div>
            <a href="<?php echo e(APP_ROOT . '/user/request-pickup.php'); ?>" class="btn btn-primary">Request E-Waste Pickup</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span>Available reward points</span>
                <strong><?php echo e($pointsBalance); ?></strong>
            </div>
            <div class="stat-card">
                <span>Pending pickups</span>
                <strong><?php echo e($pendingCount); ?></strong>
            </div>
            <div class="stat-card">
                <span>Scheduled pickups</span>
                <strong><?php echo e($scheduledCount); ?></strong>
            </div>
            <div class="stat-card">
                <span>Completed pickups</span>
                <strong><?php echo e($completedCount); ?></strong>
            </div>
        </div>

        <div class="info-grid">
            <div class="content-card">
                <h3>Next scheduled pickup</h3>
                <?php if ($nextPickup): ?>
                    <p><strong><?php echo e($nextPickup['reference_number']); ?></strong><br>
                    <?php echo e($nextPickup['status']); ?> • <?php echo e($nextPickup['preferred_date'] ?: 'Date pending'); ?> <?php echo e($nextPickup['preferred_time'] ?: ''); ?></p>
                <?php else: ?>
                    <p>No scheduled pickup yet. Request a collection to get started.</p>
                <?php endif; ?>
            </div>
            <div class="content-card">
                <h3>Active pickup status</h3>
                <?php if ($activePickup): ?>
                    <p><strong><?php echo e($activePickup['reference_number']); ?></strong><br>
                    Current status: <?php echo e($activePickup['status']); ?></p>
                <?php else: ?>
                    <p>No active pickup request.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-card">
            <h3>Recent pickup history</h3>
            <?php if ($recentPickups): ?>
                <ul class="timeline-list">
                    <?php foreach ($recentPickups as $pickup): ?>
                        <li>
                            <span><?php echo e($pickup['reference_number']); ?></span>
                            <span><?php echo e($pickup['service_type']); ?></span>
                            <span><?php echo e($pickup['status']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No pickup requests yet.</p>
            <?php endif; ?>
        </div>

        <div class="content-card">
            <h3>How Eco-Loop works</h3>
            <ol class="steps-list">
                <li>Request a pickup for your household e-waste.</li>
                <li>Choose Saver or Priority service based on your schedule.</li>
                <li>Our team confirms your route and collection time.</li>
                <li>After collection, your reward points are updated.</li>
            </ol>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
