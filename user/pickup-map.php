<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$pageTitle = 'Pickup Map | Eco-Loop';
$user = currentUser();
$pickups = db()->query('SELECT id, reference_number, pickup_address, latitude, longitude, status, service_type FROM pickup_requests WHERE user_id = ' . (int) $user['id'] . ' ORDER BY created_at DESC');
$markers = [];
while ($pickup = $pickups->fetch_assoc()) {
    if ($pickup['latitude'] && $pickup['longitude']) {
        $markers[] = [
            'id' => (int) $pickup['id'],
            'reference_number' => $pickup['reference_number'],
            'address' => $pickup['pickup_address'],
            'lat' => (float) $pickup['latitude'],
            'lng' => (float) $pickup['longitude'],
            'status' => $pickup['status'],
            'service_type' => $pickup['service_type']
        ];
    }
}
$centers = db()->query('SELECT name, address, latitude, longitude, accepted_categories, operating_hours, contact_details FROM collection_points WHERE is_active = 1 ORDER BY name')->fetch_all(MYSQLI_ASSOC);
foreach ($centers as $center) {
    $markers[] = [
        'reference_number' => $center['name'], 'address' => $center['address'],
        'lat' => (float) $center['latitude'], 'lng' => (float) $center['longitude'],
        'status' => 'Verified drop-off center', 'accepted_categories' => $center['accepted_categories'],
        'operating_hours' => $center['operating_hours'], 'contact_details' => $center['contact_details']
    ];
}
ensurePartnerLocationsTable();
$partnerLocations = db()->query('SELECT name, address, latitude, longitude FROM partner_locations ORDER BY name')->fetch_all(MYSQLI_ASSOC);
foreach ($partnerLocations as $partnerLocation) {
    $markers[] = [
        'name' => $partnerLocation['name'],
        'reference_number' => $partnerLocation['name'],
        'address' => $partnerLocation['address'],
        'lat' => (float) $partnerLocation['latitude'],
        'lng' => (float) $partnerLocation['longitude'],
        'status' => 'Potential Drop-off Organization – Pending Verification',
        'accepted_categories' => 'To be confirmed',
        'operating_hours' => 'To be confirmed',
    ];
}
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div>
                <span class="eyebrow">Map</span>
                <h1>Pickup Map</h1>
            </div>
        </div>

        <div class="content-card">
            <div class="map-legend" aria-label="Map legend">
                <span class="map-legend-item"><span class="map-legend-swatch map-legend-swatch-org"></span>Organization Drop-off Locations</span>
                <span class="map-legend-item"><span class="map-legend-swatch map-legend-swatch-customer"></span>Customer Pickup Locations</span>
            </div>
            <div id="user-pickup-map" data-markers='<?php echo json_encode($markers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>' style="height: 420px; width: 100%; border-radius: 16px; overflow: hidden;"></div>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
