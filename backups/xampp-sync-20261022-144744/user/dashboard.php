<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('user');
$pageTitle = 'User Dashboard | Eco-Loop';
$user = currentUser();
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
                <strong>0</strong>
            </div>
            <div class="stat-card">
                <span>Pending pickups</span>
                <strong>0</strong>
            </div>
            <div class="stat-card">
                <span>Scheduled pickups</span>
                <strong>0</strong>
            </div>
            <div class="stat-card">
                <span>Completed pickups</span>
                <strong>0</strong>
            </div>
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
