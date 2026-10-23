<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('user');
$pageTitle = 'User Dashboard | Eco-Loop';
$user = currentUser();

$pointsBalance = getPointsBalance((int) $user['id']);
$completedCount = db()->query('SELECT COUNT(*) AS total FROM pickup_requests WHERE user_id = ' . (int) $user['id'] . ' AND status = "Completed"')->fetch_assoc()['total'];
$activeCount = db()->query('SELECT COUNT(*) AS total FROM pickup_requests WHERE user_id = ' . (int) $user['id'] . ' AND status NOT IN ("Completed", "Cancelled", "Rejected")')->fetch_assoc()['total'];
$latestService = db()->query('SELECT service_type FROM pickup_requests WHERE user_id = ' . (int) $user['id'] . ' ORDER BY created_at DESC LIMIT 1')->fetch_assoc();
$nextPickup = db()->query('SELECT * FROM pickup_requests WHERE user_id = ' . (int) $user['id'] . ' AND status IN ("Scheduled","Collector Assigned","On the Way","Collected") ORDER BY preferred_date ASC, preferred_time ASC LIMIT 1')->fetch_assoc();
$activePickup = db()->query('SELECT * FROM pickup_requests WHERE user_id = ' . (int) $user['id'] . ' AND status NOT IN ("Completed","Cancelled") ORDER BY created_at DESC LIMIT 1')->fetch_assoc();
$recentPickups = userRecentPickups((int) $user['id'], 5);
$locationResult = db()->query('SELECT * FROM collection_points WHERE is_active = 1 ORDER BY name ASC');
$collectionPoints = $locationResult->fetch_all(MYSQLI_ASSOC);
$collectionMapPoints = [];
foreach ($collectionPoints as &$point) {
    $point['lat'] = (float) $point['latitude'];
    $point['lng'] = (float) $point['longitude'];
    $point['reference_number'] = $point['name'];
    $point['status'] = 'Collection point';
    if ($user['latitude'] !== null && $user['longitude'] !== null) {
        $earthRadius = 6371;
        $latDelta = deg2rad($point['lat'] - (float) $user['latitude']);
        $lngDelta = deg2rad($point['lng'] - (float) $user['longitude']);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad((float) $user['latitude'])) * cos(deg2rad($point['lat'])) * sin($lngDelta / 2) ** 2;
        $point['distance_km'] = $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    } else {
        $point['distance_km'] = null;
    }
    $collectionMapPoints[] = ['lat' => $point['lat'], 'lng' => $point['lng'], 'reference_number' => $point['name'], 'address' => $point['address'], 'status' => 'Verified drop-off center', 'accepted_categories' => $point['accepted_categories'], 'operating_hours' => $point['operating_hours'], 'contact_details' => $point['contact_details']];
}
unset($point);
usort($collectionPoints, static fn($a, $b) => ($a['distance_km'] ?? PHP_FLOAT_MAX) <=> ($b['distance_km'] ?? PHP_FLOAT_MAX));
$saverFee = pickupServiceFee('saver');
$priorityFee = pickupServiceFee('priority');
$saverMiddleRate = (float) getAppSetting('saver_rate_3_8', '12');
$saverOuterRate = (float) getAppSetting('saver_rate_over_8', '20');
$priorityMiddleRate = (float) getAppSetting('priority_rate_3_8', '25');
$priorityOuterRate = (float) getAppSetting('priority_rate_over_8', '40');
$pickupToken = bin2hex(random_bytes(32));

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
            <button type="button" class="btn btn-primary" data-open-pickup>Request Pickup</button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span>Available reward points</span>
                <strong><?php echo e($pointsBalance); ?></strong>
            </div>
            <div class="stat-card">
                <span>Active Pickups</span>
                <strong><?php echo e($activeCount); ?></strong>
            </div>
            <div class="stat-card">
                <span>Selected collection service</span>
                <strong><?php echo e($latestService ? ($latestService['service_type'] === 'priority' ? 'Priority Collection' : 'Convenience (Saver)') : 'Not selected'); ?></strong>
            </div>
            <div class="stat-card">
                <span>Total Pickups (completed)</span>
                <strong><?php echo e($completedCount); ?></strong>
            </div>
        </div>

        <section class="content-card dashboard-map-card">
            <div class="section-heading">
                <div><span class="eyebrow">Collection map</span><h2>Nearby collection points</h2></div>
                <a href="<?php echo e(APP_ROOT . '/user/pickup-map.php'); ?>" class="btn btn-secondary">View map</a>
            </div>
            <div class="collection-map-layout">
                <div id="customer-dashboard-map" class="dashboard-map"></div>
                <div class="collection-point-list">
                    <?php if ($collectionPoints): ?>
                        <?php foreach ($collectionPoints as $point): ?>
                            <article class="collection-point-item">
                                <strong><?php echo e($point['name']); ?></strong>
                                <p><?php echo e($point['address']); ?></p>
                                <?php if (!empty($point['accepted_categories'])): ?><small>Accepts: <?php echo e($point['accepted_categories']); ?></small><br><?php endif; ?>
                                <?php if (!empty($point['operating_hours'])): ?><small>Hours: <?php echo e($point['operating_hours']); ?></small><br><?php endif; ?>
                                <?php if (!empty($point['contact_details'])): ?><small>Contact: <?php echo e($point['contact_details']); ?></small><br><?php endif; ?>
                                <?php if ($point['distance_km'] !== null): ?><small><?php echo e(number_format($point['distance_km'], 1)); ?> km away (straight-line estimate)</small><?php endif; ?>
                                <p><a class="btn btn-secondary" href="https://www.google.com/maps/dir/?api=1&destination=<?php echo rawurlencode($point['latitude'] . ',' . $point['longitude']); ?>" target="_blank" rel="noopener">Get directions</a></p>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state"><strong>No collection points yet</strong><p>An administrator can add verified collection locations.</p></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="map-overview">
                <h3>Google Maps regional overview</h3>
                <p class="muted-text">Regional map reference. Your saved pickup pin and verified collection points are shown on the interactive map above.</p>
                <iframe
                    title="EcoLoop regional map overview"
                    src="https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d57393.79087873349!2d123.9347075919448!3d10.291998032473089!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sen!2sph!4v1792650775604!5m2!1sen!2sph"
                    width="600" height="450" style="border:0; width:100%; max-width:100%; border-radius:16px;"
                    allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
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
<dialog id="pickup-request-modal" class="workflow-modal" aria-labelledby="pickup-information-title">
    <form id="pickup-information-form" class="modal-card" method="dialog" novalidate>
        <div class="modal-heading">
            <div><span class="eyebrow">Step 1 of 2 - Pickup details</span><h2 id="pickup-information-title">Request a Pickup</h2></div>
            <button type="button" class="modal-close" data-cancel-pickup aria-label="Close">&times;</button>
        </div>
        <div id="pickup-information-message" class="alert alert-error" role="alert" hidden></div>
        <div class="request-grid modal-fields">
            <div class="field-group"><label for="pickup_full_name">Full Name</label><input type="text" id="pickup_full_name" name="full_name" maxlength="150" autocomplete="name" value="<?php echo e($user['full_name'] ?? ''); ?>" required></div>
            <div class="field-group"><label for="pickup_contact_number">Contact Number</label><input type="tel" id="pickup_contact_number" name="contact_number" maxlength="20" autocomplete="tel" pattern="\+?[0-9][0-9 .()\-]{6,19}" value="<?php echo e($user['mobile_number'] ?? ''); ?>" required></div>
            <div class="field-group full-span"><label for="pickup_address">Pickup Address</label><input type="text" id="pickup_address" name="pickup_address" maxlength="2000" autocomplete="street-address" value="<?php echo e($user['address'] ?? ''); ?>" required></div>
        </div>
        <div class="field-group">
            <label for="service_type">Collection service</label>
            <select id="service_type" name="service_type" required data-saver-fee="<?php echo e($saverFee); ?>" data-priority-fee="<?php echo e($priorityFee); ?>" data-saver-middle-rate="<?php echo e($saverMiddleRate); ?>" data-saver-outer-rate="<?php echo e($saverOuterRate); ?>" data-priority-middle-rate="<?php echo e($priorityMiddleRate); ?>" data-priority-outer-rate="<?php echo e($priorityOuterRate); ?>">
                <option value="saver">Convenience (Saver) - &#8369;<?php echo e(number_format($saverFee, 2)); ?></option>
                <option value="priority">Priority Collection - &#8369;<?php echo e(number_format($priorityFee, 2)); ?></option>
            </select>
            <p id="service-description" class="muted-text">Regular collection between 8:00 AM and 5:00 PM. Requests follow the regular schedule and may not be immediate.</p>
            <div class="field-group full-span"><label for="pickup-destination">Destination collection point (used for distance estimate)</label><select id="pickup-destination" name="collection_point_id" <?php echo $collectionPoints ? 'required' : ''; ?>><option value=""><?php echo $collectionPoints ? 'Choose a verified center for distance pricing' : 'No verified center available — base fee only'; ?></option><?php foreach ($collectionPoints as $point): ?><option value="<?php echo (int) $point['id']; ?>" data-lat="<?php echo e($point['latitude']); ?>" data-lng="<?php echo e($point['longitude']); ?>"><?php echo e($point['name']); ?> — <?php echo e($point['address']); ?></option><?php endforeach; ?></select></div>
            <div id="pickup-price-breakdown" class="content-card"><p><strong>Distance:</strong> <span id="pickup-distance">Choose a verified collection point</span></p><p><strong>Base fee:</strong> <span id="pickup-base-fee">&#8369;<?php echo e(number_format($saverFee, 2)); ?></span></p><p><strong>Distance charges:</strong> <span id="pickup-distance-fee">&#8369;0.00</span></p><div class="summary-row"><span>Estimated total</span><strong id="pickup-fee">&#8369;<?php echo e(number_format($saverFee, 2)); ?></strong></div><small>Distance pricing uses a straight-line estimate to the selected verified center, not road distance. Road routing is not configured. Final fee is recalculated by the server.</small></div>
        </div>
        <div class="modal-fields">
            <div id="pickup-item-rows" class="pickup-item-rows">
                <div class="request-grid pickup-item-row">
                    <div class="field-group"><label for="pickup-category-0">E-waste category</label><select id="pickup-category-0" name="item_category[]" required><option value="">Choose a category</option><?php foreach (['Mobile phones', 'Computers and laptops', 'Batteries', 'Chargers and cables', 'Small electronic appliances', 'Other'] as $category): ?><option value="<?php echo e($category); ?>"><?php echo e($category); ?></option><?php endforeach; ?></select></div>
                    <div class="field-group"><label for="pickup-quantity-0">Quantity (items)</label><input type="number" id="pickup-quantity-0" name="quantity[]" min="1" max="10000" step="1" value="1" required></div>
                    <div class="field-group full-span pickup-item-actions"><button type="button" class="btn btn-outline" data-remove-pickup-item hidden>Remove category</button></div>
                </div>
            </div>
            <button type="button" class="btn btn-secondary" id="add-pickup-item">Add another e-waste category</button>
        </div>
        <div class="request-grid modal-fields">
            <div class="field-group"><label for="preferred_date">Preferred date <span>(required for Saver)</span></label><input type="date" id="preferred_date" name="preferred_date" min="<?php echo e(date('Y-m-d')); ?>"></div>
            <div class="field-group"><label for="preferred_time">Preferred time (8:00 AM-5:00 PM)</label><input type="time" id="preferred_time" name="preferred_time" min="08:00" max="17:00"></div>
            <div class="field-group full-span"><label for="pickup_notes">Additional notes</label><textarea id="pickup_notes" name="notes" rows="2" maxlength="5000" placeholder="Item details or pickup instructions (optional)"></textarea></div>
        </div>
        <div class="modal-actions"><button type="button" class="btn btn-outline" data-cancel-pickup>Cancel</button><button type="submit" class="btn btn-primary">Next: Choose location</button></div>
    </form>
</dialog>
<dialog id="pickup-map-modal" class="workflow-modal pickup-map-dialog" aria-labelledby="pickup-map-title">
    <form id="pickup-request-form" class="modal-card" method="post" action="<?php echo e(APP_ROOT . '/user/create-pickup.php'); ?>">
        <div class="modal-heading"><div><span class="eyebrow">Step 2 of 2 - Confirm pickup point</span><h2 id="pickup-map-title">Mark Your Pickup Location</h2></div><button type="button" class="modal-close" data-cancel-pickup aria-label="Close">&times;</button></div>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="request_token" value="<?php echo e($pickupToken); ?>">
        <input type="hidden" name="full_name" id="request_full_name"><input type="hidden" name="contact_number" id="request_contact_number">
        <input type="hidden" name="service_type" id="request_service_type"><div id="request-pickup-items"></div>
        <input type="hidden" name="collection_point_id" id="request_collection_point_id">
        <input type="hidden" name="pickup_address" id="request_pickup_address">
        <input type="hidden" name="preferred_date" id="request_preferred_date"><input type="hidden" name="preferred_time" id="request_preferred_time">
        <input type="hidden" name="notes" id="request_notes">
        <input type="hidden" name="latitude" id="latitude" value="<?php echo e($user['latitude'] ?? ''); ?>"><input type="hidden" name="longitude" id="longitude" value="<?php echo e($user['longitude'] ?? ''); ?>">
        <div id="pickup-form-message" class="alert alert-error" role="alert" hidden></div>
        <p id="pickup-map-instruction" class="map-instruction">Tap or click the map to mark your exact pickup location. No pin is selected until you place or confirm one.</p>
        <div id="pickup-modal-map" class="modal-map pickup-selection-map" role="application" aria-label="Interactive pickup location map"></div>
        <div class="map-actions"><button type="button" id="use-my-location" class="btn btn-secondary">Use My Current Location</button><span class="coordinates-text">Selected coordinates: <strong id="coord-value">No location selected</strong></span></div>
        <p id="pickup-location-message" class="muted-text" role="status" aria-live="polite"></p>
        <section id="pickup-final-confirmation" class="pickup-confirmation" hidden>
            <h3>Confirm pickup details</h3>
            <dl class="pickup-confirmation-list"><div><dt>Full name</dt><dd id="confirm-pickup-name"></dd></div><div><dt>Contact number</dt><dd id="confirm-pickup-phone"></dd></div><div><dt>Pickup address</dt><dd id="confirm-pickup-address"></dd></div><div><dt>E-waste categories and quantities</dt><dd id="confirm-pickup-items"></dd></div><div><dt>Collection service</dt><dd id="confirm-pickup-service"></dd></div><div><dt>Distance and method</dt><dd id="confirm-pickup-distance"></dd></div><div><dt>Base / distance charges</dt><dd id="confirm-pickup-charges"></dd></div><div><dt>Map coordinates</dt><dd id="confirm-pickup-coordinates"></dd></div></dl>
        </section>
        <div class="modal-actions pickup-map-actions"><button type="button" class="btn btn-outline" id="pickup-map-back">Back</button><button type="button" class="btn btn-secondary" id="confirm-pickup-location">Confirm Location</button><button type="button" class="btn btn-outline" data-cancel-pickup>Cancel</button><button type="submit" class="btn btn-primary" id="submit-pickup-request" hidden>Submit Pickup Request</button></div>
    </form>
</dialog><script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof initializePickupMap === 'function') initializePickupMap({
        mapId: 'customer-dashboard-map',
        defaultLat: <?php echo $user['latitude'] !== null ? json_encode((float) $user['latitude']) : '10.291998032473089'; ?>,
        defaultLng: <?php echo $user['longitude'] !== null ? json_encode((float) $user['longitude']) : '123.9347075919448'; ?>,
        latInput: { value: <?php echo $user['latitude'] !== null ? json_encode((string) $user['latitude']) : '""'; ?> },
        lngInput: { value: <?php echo $user['longitude'] !== null ? json_encode((string) $user['longitude']) : '""'; ?> },
        markers: <?php echo json_encode($collectionMapPoints, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
        editable: false,
        allowUnselected: true
    });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
