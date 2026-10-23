<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$pageTitle = 'My Pickups | Eco-Loop';
$user = currentUser();
$pickups = db()->query('SELECT * FROM pickup_requests WHERE user_id = ' . (int) $user['id'] . ' ORDER BY created_at DESC');
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div>
                <span class="eyebrow">Pickup history</span>
                <h1>My Pickups</h1>
            </div>
            <a href="<?php echo e(APP_ROOT . '/user/request-pickup.php'); ?>" class="btn btn-primary">New request</a>
        </div>

        <div class="content-card">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Fee</th>
                            <th>Preferred Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pickups && $pickups->num_rows > 0): ?>
                            <?php while ($pickup = $pickups->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo e($pickup['reference_number']); ?></td>
                                    <td><?php echo e(ucfirst($pickup['service_type'])); ?></td>
                                    <td><span class="status-pill status-<?php echo e(strtolower(str_replace(' ', '-', $pickup['status']))); ?>"><?php echo e($pickup['status']); ?></span></td>
                                    <td><?php echo e(formatCurrency((float) $pickup['service_fee'])); ?></td>
                                    <td><?php echo e($pickup['preferred_date'] ?: 'Not set'); ?></td>
                                    <td><a href="<?php echo e(APP_ROOT . '/user/pickup-details.php?id=' . (int) $pickup['id']); ?>">View details</a></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">No pickup requests have been made yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
