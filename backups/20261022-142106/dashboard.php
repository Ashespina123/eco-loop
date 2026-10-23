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
$activeCount = db()->query('SELECT COUNT(*) AS total FROM pickup_requests WHERE user_id = ' . (int) $user['id'] . ' AND status NOT IN ("Completed", "Cancelled")')->fetch_assoc()['total'];
$latestService = db()->query('SELECT service_type FROM pickup_requests WHERE user_id = ' . (int) $user['id'] . ' ORDER BY created_at DESC LIMIT 1')->fetch_assoc();
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
                <span class="eyebrow">Welcome to EcoLoop</span>
                <h1>Hello, <?php echo e($user['full_name']); ?>! Let's make your community cleaner.</h1>
                <p class="muted-text">Pickup location: <?php echo e($user['address'] ?: 'Add your pickup address in Account/Profile.'); ?></p>
            </div>
            <a href="<?php echo e(APP_ROOT . '/user/request-pickup.php'); ?>" class="btn btn-primary">Request E-Waste Pickup</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span>Available reward points</span>
                <strong><?php echo e($pointsBalance); ?></strong>
            </div>
            <div class="stat-card">
                <span>Active pickup requests</span>
                <strong><?php echo e($activeCount); ?></strong>
            </div>
            <div class="stat-card">
                <span>Selected collection service</span>
                <strong><?php echo e($latestService ? ($latestService['service_type'] === 'priority' ? 'Priority Collection' : 'Convenience (Saver)') : 'Not selected'); ?></strong>
            </div>
            <div class="stat-card">
                <span>Completed pickups</span>
                <strong><?php echo e($completedCount); ?></strong>
            </div>
        </div>

        <section class="content-card dashboard-map-card">
            <div class="section-heading">
                <div><span class="eyebrow">Collection map</span><h2>Nearby collection points</h2></div>
                <a href="<?php echo e(APP_ROOT . '/user/pickup-map.php'); ?>" class="btn btn-secondary">View map</a>
            </div>
            <div id="customer-dashboard-map" class="dashboard-map"></div>
            <p class="muted-text">No collection points have been configured yet. An administrator can add verified locations.</p>
        </section>

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
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof initializePickupMap === 'function') initializePickupMap({
        mapId: 'customer-dashboard-map',
        defaultLat: <?php echo $user['latitude'] !== null ? json_encode((float) $user['latitude']) : '14.5995'; ?>,
        defaultLng: <?php echo $user['longitude'] !== null ? json_encode((float) $user['longitude']) : '120.9842'; ?>,
        latInput: { value: <?php echo $user['latitude'] !== null ? json_encode((string) $user['latitude']) : '""'; ?> },
        lngInput: { value: <?php echo $user['longitude'] !== null ? json_encode((string) $user['longitude']) : '""'; ?> },
        editable: false,
        allowUnselected: true
    });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
