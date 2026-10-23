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
$collectionPoint = null;
if (!empty($pickup['collection_point_id'])) {
    $pointStmt = db()->prepare('SELECT name, address FROM collection_points WHERE id = ? LIMIT 1');
    $pointStmt->bind_param('i', $pickup['collection_point_id']); $pointStmt->execute();
    $collectionPoint = $pointStmt->get_result()->fetch_assoc(); $pointStmt->close();
}
$verificationStmt = db()->prepare('SELECT id, total_points, phone_value_total, created_at FROM pickup_verifications WHERE pickup_request_id = ? LIMIT 1');
$verificationStmt->bind_param('i', $pickup['id']); $verificationStmt->execute();
$verification = $verificationStmt->get_result()->fetch_assoc(); $verificationStmt->close();
$verifiedItems = [];
if ($verification) {
    $verifiedItemStmt = db()->prepare('SELECT item_category, quantity, points_per_piece, subtotal_points, monetary_value_per_piece, condition_notes FROM pickup_verified_items WHERE verification_id = ? ORDER BY id');
    $verifiedItemStmt->bind_param('i', $verification['id']); $verifiedItemStmt->execute();
    $verifiedItems = $verifiedItemStmt->get_result()->fetch_all(MYSQLI_ASSOC); $verifiedItemStmt->close();
}

$historyStmt = db()->prepare('SELECT * FROM pickup_status_history WHERE pickup_request_id = ? ORDER BY created_at ASC');
$historyStmt->bind_param('i', $pickup['id']);
$historyStmt->execute();
$history = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$historyStmt->close();

$collectorStmt = db()->prepare('SELECT c.full_name, c.mobile_number, c.email, pa.status, pa.assigned_at FROM pickup_assignments pa JOIN collectors c ON c.id = pa.collector_id WHERE pa.pickup_request_id = ? ORDER BY pa.assigned_at DESC LIMIT 1');
$collectorStmt->bind_param('i', $pickup['id']);
$collectorStmt->execute();
$collector = $collectorStmt->get_result()->fetch_assoc();
$collectorStmt->close();

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
            <?php if (!empty($pickup['customer_name']) || !empty($pickup['contact_number'])): ?>
                <div class="content-card"><h2>Pickup contact</h2><p><?php echo e($pickup['customer_name'] ?: $user['full_name']); ?><br><?php echo e($pickup['contact_number'] ?: $user['mobile_number']); ?></p></div>
            <?php endif; ?>
            <div class="content-card">
                <h3>Pickup information</h3>
                <p><strong>Pickup request ID:</strong> <?php echo e($pickup['id']); ?></p>
                <p><strong>Service:</strong> <?php echo e($pickup['service_type'] === 'priority' ? 'Priority Collection' : 'Convenience (Saver)'); ?></p>
                <p><strong>Fee:</strong> <?php echo e(formatCurrency((float) $pickup['service_fee'])); ?></p>
                <?php if ($pickup['base_fee'] !== null): ?><p><strong>Base fee:</strong> <?php echo e(formatCurrency((float) $pickup['base_fee'])); ?></p><p><strong>Distance charge:</strong> <?php echo e(formatCurrency((float) $pickup['distance_charge'])); ?></p><?php endif; ?>
                <?php if ($pickup['distance_km'] !== null): ?><p><strong>Distance:</strong> <?php echo e(number_format((float) $pickup['distance_km'], 2)); ?> km · <?php echo e($pickup['distance_method'] ?: ''); ?></p><?php endif; ?>
                <?php if ($collectionPoint): ?><p><strong>Selected collection point:</strong> <?php echo e($collectionPoint['name']); ?> · <?php echo e($collectionPoint['address']); ?></p><?php endif; ?>
                <p><strong>Status:</strong> <?php echo e($pickup['status']); ?></p>
                <p><strong>Pickup address:</strong> <?php echo e($pickup['pickup_address']); ?></p>
                <?php if ($pickup['latitude'] !== null && $pickup['longitude'] !== null): ?><p><strong>Confirmed map location:</strong> <?php echo e(number_format((float) $pickup['latitude'], 6)); ?>, <?php echo e(number_format((float) $pickup['longitude'], 6)); ?></p><?php endif; ?>
                <p><strong>Preferred date:</strong> <?php echo e($pickup['preferred_date'] ?: 'Not specified'); ?></p>
                <p><strong>Preferred time:</strong> <?php echo e($pickup['preferred_time'] ?: 'Not specified'); ?></p>
                <p><strong>Submitted:</strong> <?php echo e(date('M j, Y g:i A', strtotime($pickup['created_at']))); ?></p>
                <?php if ($pickup['description']): ?><p><strong>Notes:</strong><br><?php echo nl2br(e($pickup['description'])); ?></p><?php endif; ?>
            </div>

            <div class="content-card">
                <h3>Item summary</h3>
                <?php if ($items): ?>
                    <ul>
                        <?php foreach ($items as $item): ?>
                            <li><?php echo e($item['item_category']); ?><?php echo $item['quantity'] ? ' (' . e($item['quantity']) . ' items)' : ''; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No items recorded.</p>
                <?php endif; ?>
                <p><strong>Estimated quantity:</strong> <?php echo e($pickup['estimated_quantity']); ?></p>
                <p><strong>Hazardous waste:</strong> <?php echo e($pickup['hazardous'] ? 'Yes' : 'No'); ?></p>
            </div>
            <?php if ($collector): ?><div class="content-card"><h3>Assigned collector</h3><p><strong><?php echo e($collector['full_name']); ?></strong></p><p><?php echo e($collector['mobile_number']); ?></p><p><?php echo e($collector['email']); ?></p><p>Assignment status: <?php echo e($collector['status']); ?></p></div><?php endif; ?>
        </div>

        <?php if ($verification): ?><section class="content-card"><h2>Verified received items</h2><p><?php echo (int) $verification['total_points']; ?> eligible points<?php echo $pickup['status'] === 'Completed' ? ' awarded' : ' when the pickup is completed'; ?> · Phone valuation ₱<?php echo e(number_format((float) $verification['phone_value_total'], 2)); ?> (separate from points)</p><ul><?php foreach ($verifiedItems as $item): ?><li><?php echo e($item['item_category']); ?> × <?php echo (int) $item['quantity']; ?> · <?php echo (int) $item['points_per_piece']; ?> points each · <?php echo (int) $item['subtotal_points']; ?> points<?php if ($item['item_category'] === 'Phone'): ?> · ₱<?php echo e(number_format((float) $item['monetary_value_per_piece'], 2)); ?> per phone<?php endif; ?><?php if (!empty($item['condition_notes'])): ?> · <?php echo e($item['condition_notes']); ?><?php endif; ?></li><?php endforeach; ?></ul></section><?php endif; ?>

        <div class="content-card">
            <h3>Pickup map</h3>
            <?php if ($pickup['latitude'] && $pickup['longitude']): ?>
                <div id="user-pickup-map" style="height: 340px; width: 100%; border-radius: 16px; overflow: hidden;" data-markers="<?php echo e(json_encode([['lat' => (float) $pickup['latitude'], 'lng' => (float) $pickup['longitude'], 'reference_number' => $pickup['reference_number'], 'address' => $pickup['pickup_address'], 'status' => $pickup['status']]], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>"></div>
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
                            <span><strong><?php echo e($entry['new_status'] === 'Approved' ? 'Accepted' : $entry['new_status']); ?></strong><?php if (!empty($entry['note'])): ?><br><?php echo e($entry['note']); ?><?php endif; ?></span>
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
