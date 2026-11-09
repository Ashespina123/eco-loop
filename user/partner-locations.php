<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$pageTitle = 'Partners Location | Eco-Loop';
$user = currentUser();

ensurePartnerLocationsTable();
$partnerLocations = db()->query('SELECT name, address, latitude, longitude FROM partner_locations ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$markers = [];
foreach ($partnerLocations as $location) {
    $markers[] = [
        'name' => $location['name'],
        'reference_number' => $location['name'],
        'address' => $location['address'],
        'lat' => (float) $location['latitude'],
        'lng' => (float) $location['longitude'],
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
                <h1>Partners Location</h1>
            </div>
        </div>

        <div class="content-card">
            <div class="map-legend" aria-label="Map legend">
                <span class="map-legend-item"><span class="map-legend-swatch map-legend-swatch-org"></span>Organization Drop-off Locations</span>
                <span class="map-legend-item"><span class="map-legend-swatch map-legend-swatch-customer"></span>Customer Pickup Locations</span>
            </div>
            <div id="user-partner-locations-map" data-markers='<?php echo json_encode($markers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>' style="height: 420px; width: 100%; border-radius: 16px; overflow: hidden;"></div>
        </div>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof L === 'undefined') return;
    const pickupMap = document.getElementById('user-partner-locations-map');
    if (!pickupMap) return;
    const map = L.map('user-partner-locations-map', { zoomControl: true }).setView([10.291998032473089, 123.9347075919448], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
    const centerIcon = L.divIcon({ className: 'dropoff-center-marker', html: '<span aria-hidden="true">♻</span>', iconSize: [34, 34], iconAnchor: [17, 17] });
    const organizationIcon = function (label) {
        return L.divIcon({
            className: 'dropoff-org-marker',
            html: '<div class="dropoff-org-marker-wrap"><span class="dropoff-org-marker-pin" aria-hidden="true"></span><span class="dropoff-org-marker-label">' + label + '</span></div>',
            iconSize: [28, 28],
            iconAnchor: [14, 28],
            popupAnchor: [14, -18]
        });
    };

    const markers = pickupMap.dataset.markers ? JSON.parse(pickupMap.dataset.markers) : [];
    markers.forEach(function (markerData) {
        const name = markerData.name || markerData.reference_number || 'Partner Location';
        const marker = L.marker([Number(markerData.lat), Number(markerData.lng)], {
            icon: organizationIcon(name)
        }).addTo(map);
        const popup = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = name;
        popup.appendChild(title);
        popup.appendChild(document.createElement('br'));
        popup.appendChild(document.createTextNode('Address: ' + (markerData.address || '')));
        popup.appendChild(document.createElement('br'));
        popup.appendChild(document.createTextNode('Operating hours: To be confirmed'));
        popup.appendChild(document.createElement('br'));
        popup.appendChild(document.createTextNode('Accepted e-waste items: To be confirmed'));
        popup.appendChild(document.createElement('br'));
        popup.appendChild(document.createTextNode('Verification status: Potential Drop-off Organization – Pending Verification'));
        marker.bindPopup(popup);
    });

    if (markers.length > 0) {
        const bounds = markers.map(function (markerData) { return [Number(markerData.lat), Number(markerData.lng)]; });
        const featureGroup = L.featureGroup(bounds.map(function (point) { return L.marker(point); }));
        map.fitBounds(featureGroup.getBounds(), { padding: [20, 20] });
    }
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
