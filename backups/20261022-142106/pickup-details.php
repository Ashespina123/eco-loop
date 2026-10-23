<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$pageTitle = 'Pickup Details | Eco-Loop';
$user = currentUser();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$pickup = null;
if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM pickup_requests WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $id, $user['id']);
    $stmt->execute();
    $pickup = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$pickup) {
    setFlash('error', 'Pickup request not found.');
    redirect('user/my-pickups.php');
}

$itemStmt = db()->prepare('SELECT item_category, quantity FROM pickup_items WHERE pickup_request_id = ?');
$itemStmt->bind_param('i', $pickup['id']);
$itemStmt->execute();
$items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

$historyStmt = db()->prepare('SELECT * FROM pickup_status_history WHERE pickup_request_id = ? ORDER BY created_at ASC');
$historyStmt->bind_param('i', $pickup['id']);
$historyStmt->execute();
$history = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$historyStmt->close();

$statusOrder = ['Pending', 'Approved', 'Scheduled', 'Collector Assigned', 'On the Way', 'Collected', 'Completed'];
$currentStep = array_search($pickup['status'], $statusOrder, true);
$currentStep = $currentStep === false ? 0 : $currentStep;

require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div>
                <span class="eyebrow">Pickup details</span>
                <h1><?php echo e($pickup['reference_number']); ?></h1>
            </div>
        </div>

        <div class="content-card">
            <div class="status-progress">
                <?php foreach ($statusOrder as $index => $status): ?>
                    <span class="progress-step <?php echo $index <= $currentStep ? 'active' : ''; ?>"><?php echo e($status); ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="info-grid">
            <div class="content-card">
                <h3>Pickup information</h3>
                <p><strong>Service:</strong> <?php echo e(ucfirst($pickup['service_type'])); ?></p>
                <p><strong>Fee:</strong> <?php echo e(formatCurrency((float) $pickup['service_fee'])); ?></p>
                <p><strong>Status:</strong> <?php echo e($pickup['status']); ?></p>
                <p><strong>Pickup address:</strong> <?php echo e($pickup['pickup_address']); ?></p>
                <p><strong>Preferred date:</strong> <?php echo e($pickup['preferred_date'] ?: 'Not specified'); ?></p>
                <p><strong>Preferred time:</strong> <?php echo e($pickup['preferred_time'] ?: 'Not specified'); ?></p>
            </div>

            <div class="content-card">
                <h3>Item summary</h3>
                <?php if ($items): ?>
                    <ul>
                        <?php foreach ($items as $item): ?>
                            <li><?php echo e($item['item_category']); ?> <?php echo e($item['quantity'] ? '(' . $item['quantity'] . ')' : ''); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No items recorded.</p>
                <?php endif; ?>
                <p><strong>Estimated quantity:</strong> <?php echo e($pickup['estimated_quantity']); ?></p>
                <p><strong>Hazardous waste:</strong> <?php echo e($pickup['hazardous'] ? 'Yes' : 'No'); ?></p>
            </div>
        </div>

        <div class="content-card">
            <h3>Pickup map</h3>
            <?php if ($pickup['latitude'] && $pickup['longitude']): ?>
                <div id="pickup-detail-map" style="height: 340px; width: 100%; border-radius: 16px; overflow: hidden;" data-lat="<?php echo e($pickup['latitude']); ?>" data-lng="<?php echo e($pickup['longitude']); ?>" data-reference="<?php echo e($pickup['reference_number']); ?>" data-address="<?php echo e($pickup['pickup_address']); ?>" data-status="<?php echo e($pickup['status']); ?>"></div>
            <?php else: ?>
                <p>No map coordinates saved for this request yet.</p>
            <?php endif; ?>
        </div>

        <div class="content-card">
            <h3>Status history</h3>
            <?php if ($history): ?>
                <ul class="timeline-list">
                    <?php foreach ($history as $entry): ?>
                        <li>
                            <span><?php echo e($entry['new_status']); ?></span>
                            <span><?php echo e(date('M d, Y H:i', strtotime($entry['created_at']))); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No status changes recorded yet.</p>
            <?php endif; ?>
        </div>
    </main>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mapElement = document.getElementById('pickup-detail-map');
        if (!mapElement || typeof initializePickupMap !== 'function' || !mapElement.dataset.lat || !mapElement.dataset.lng) {
            return;
        }

        initializePickupMap({
            mapId: 'pickup-detail-map',
            defaultLat: Number(mapElement.dataset.lat),
            defaultLng: Number(mapElement.dataset.lng),
            editable: false,
            markers: [{
                lat: Number(mapElement.dataset.lat),
                lng: Number(mapElement.dataset.lng),
                reference_number: mapElement.dataset.reference,
                address: mapElement.dataset.address,
                status: mapElement.dataset.status
            }]
        });
    });
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
