<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$user = currentUser();
$userId = (int) $user['id'];
$pageTitle = 'My Pickups | Eco-Loop';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $pickupId = filter_input(INPUT_POST, 'pickup_id', FILTER_VALIDATE_INT) ?: 0;
    $connection = db();
    try {
        $connection->begin_transaction();
        $lock = $connection->prepare('SELECT id, status, reference_number FROM pickup_requests WHERE id = ? AND user_id = ? FOR UPDATE');
        $lock->bind_param('ii', $pickupId, $userId);
        $lock->execute();
        $pickup = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$pickup || $pickup['status'] !== 'Pending') {
            $connection->rollback();
            setFlash('error', 'Only pending pickup requests can be cancelled.');
            redirect('user/my-pickups.php');
        }
        $newStatus = 'Cancelled';
        $reason = 'Cancelled by customer.';
        $update = $connection->prepare('UPDATE pickup_requests SET status = ?, cancellation_reason = ? WHERE id = ? AND user_id = ? AND status = "Pending"');
        $update->bind_param('ssii', $newStatus, $reason, $pickupId, $userId);
        $update->execute();
        $changed = $update->affected_rows;
        $update->close();
        if ($changed !== 1) throw new RuntimeException('Pickup status changed before cancellation.');
        createPickupStatusEntry($pickupId, 'Pending', 'Cancelled', $userId, $reason);
        createNotification($userId, 'Pickup request cancelled', 'Pickup request ' . $pickup['reference_number'] . ' was cancelled.', 'info', $pickupId);
        $connection->commit();
        setFlash('success', 'Pickup request cancelled.');
    } catch (Throwable $exception) {
        if ($connection->errno === 0) $connection->rollback();
        error_log('EcoLoop pickup cancellation failed: ' . $exception->getMessage());
        setFlash('error', 'We could not cancel that request. Please try again.');
    }
    redirect('user/my-pickups.php');
}

$stmt = db()->prepare('SELECT p.*, (SELECT GROUP_CONCAT(CONCAT(i.item_category, " × ", i.quantity) ORDER BY i.id SEPARATOR ", ") FROM pickup_items i WHERE i.pickup_request_id = p.id) AS items FROM pickup_requests p WHERE p.user_id = ? ORDER BY p.created_at DESC, p.id DESC');
$stmt->bind_param('i', $userId);
$stmt->execute();
$pickups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div><span class="eyebrow">Pickup history</span><h1>My Pickups</h1></div>
            <a href="<?php echo e(APP_ROOT . '/user/dashboard.php?openPickup=1'); ?>" class="btn btn-primary">Request Pickup</a>
        </div>
        <?php if ($pickups): ?>
            <div class="workflow-card-grid">
                <?php foreach ($pickups as $pickup): ?>
                    <article class="pickup-card">
                        <div class="pickup-card-header">
                            <div><h3>Pickup #<?php echo e($pickup['id']); ?> · <?php echo e($pickup['reference_number']); ?></h3><p><?php echo e($pickup['service_type'] === 'priority' ? 'Priority Collection' : 'Convenience (Saver)'); ?> · <?php echo e(formatCurrency((float) $pickup['service_fee'])); ?></p></div>
                            <span class="status-pill status-<?php echo e(strtolower(str_replace(' ', '-', $pickup['status']))); ?>"><?php echo e($pickup['status'] === 'Approved' ? 'Accepted' : $pickup['status']); ?></span>
                        </div>
                        <p><strong>E-waste:</strong> <?php echo e($pickup['items'] ?: $pickup['estimated_quantity']); ?></p>
                        <?php if (!empty($pickup['customer_name']) || !empty($pickup['contact_number'])): ?><p><strong>Contact:</strong> <?php echo e($pickup['customer_name'] ?: $user['full_name']); ?> · <?php echo e($pickup['contact_number'] ?: $user['mobile_number']); ?></p><?php endif; ?>
                        <p><strong>Address:</strong> <?php echo e($pickup['pickup_address']); ?></p>
                        <p><strong>Preferred schedule:</strong> <?php echo e($pickup['preferred_date'] ?: 'As soon as available'); ?><?php echo $pickup['preferred_time'] ? ' at ' . e(substr($pickup['preferred_time'], 0, 5)) : ''; ?></p>
                        <p><strong>Submitted:</strong> <?php echo e(date('M j, Y g:i A', strtotime($pickup['created_at']))); ?></p>
                        <?php if ($pickup['description']): ?><p><strong>Notes:</strong> <?php echo nl2br(e($pickup['description'])); ?></p><?php endif; ?>
                        <div class="pickup-card-actions">
                            <a class="btn btn-secondary" href="<?php echo e(APP_ROOT . '/user/pickup-details.php?id=' . (int) $pickup['id']); ?>">View Details</a>
                            <?php if ($pickup['status'] === 'Pending'): ?>
                                <form method="post" class="inline-form" onsubmit="return confirm('Cancel this pending pickup request?');">
                                    <?php echo csrf_field(); ?><input type="hidden" name="pickup_id" value="<?php echo (int) $pickup['id']; ?>">
                                    <button type="submit" class="btn btn-outline">Cancel Request</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="content-card empty-state"><h2>No pickups yet</h2><p>Your pickup history will appear here after you submit a collection request.</p><a href="<?php echo e(APP_ROOT . '/user/dashboard.php?openPickup=1'); ?>" class="btn btn-primary">Request your first pickup</a></div>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
