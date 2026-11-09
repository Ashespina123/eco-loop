<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$admin = currentUser();
$pageTitle = 'Partner Locations | Eco-Loop';

ensurePartnerLocationsTable();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $id = filter_var($_POST['location_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;

    try {
        if ($action === 'delete' && $id > 0) {
            $stmt = db()->prepare('DELETE FROM partner_locations WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            logAdminAction((int) $admin['id'], 'Partner location removed', 'Location ID #' . $id);
            setFlash('success', 'Partner location deleted.');
        } else {
            $name = trim((string) ($_POST['name'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ''));
            $latitude = trim((string) ($_POST['latitude'] ?? ''));
            $longitude = trim((string) ($_POST['longitude'] ?? ''));

            if ($name === '' || mb_strlen($name) > 150 || $address === '' || $latitude === '' || $longitude === '') {
                throw new RuntimeException('Enter a partner name, address, and map coordinates.');
            }

            if (!is_numeric($latitude) || !is_numeric($longitude)) {
                throw new RuntimeException('Latitude and longitude must be valid numbers.');
            }

            $lat = (float) $latitude;
            $lng = (float) $longitude;

            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                throw new RuntimeException('Map coordinates are outside the valid range.');
            }

            if ($id > 0) {
                $stmt = db()->prepare('UPDATE partner_locations SET name = ?, address = ?, latitude = ?, longitude = ? WHERE id = ?');
                $stmt->bind_param('ssddi', $name, $address, $lat, $lng, $id);
            } else {
                $stmt = db()->prepare('INSERT INTO partner_locations (name, address, latitude, longitude, created_by) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('ssdsi', $name, $address, $lat, $lng, $admin['id']);
            }

            $stmt->execute();
            $stmt->close();
            logAdminAction((int) $admin['id'], $id > 0 ? 'Partner location updated' : 'Partner location added', $name . ' / ' . $address . ' (' . $latitude . ', ' . $longitude . ')');
            setFlash('success', 'Partner location saved.');
        }
    } catch (RuntimeException $exception) {
        setFlash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('EcoLoop partner location save failed: ' . $exception->getMessage());
        setFlash('error', 'The partner location could not be saved. Please try again.');
    }

    redirect('admin/partner-locations.php');
}

$partnerLocations = db()->query('SELECT * FROM partner_locations ORDER BY name')->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div>
                <span class="eyebrow">Admin</span>
                <h1>Partner Locations</h1>
            </div>
        </div>

        <section class="content-card">
            <h2>Add partner location</h2>
            <p>Place a red partner marker on the map. It will be visible to users as a drop-off organization location.</p>
            <form method="post" class="request-grid">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save">
                <div class="field-group">
                    <label>Partner name</label>
                    <input name="name" maxlength="150" required>
                </div>
                <div class="field-group">
                    <label>Address</label>
                    <input name="address" required>
                </div>
                <div id="partner-location-map" class="modal-map full-span" style="height: 300px;"></div>
                <div class="field-group">
                    <label>Latitude</label>
                    <input id="partner-lat" type="number" name="latitude" min="-90" max="90" step="any" required>
                </div>
                <div class="field-group">
                    <label>Longitude</label>
                    <input id="partner-lng" type="number" name="longitude" min="-180" max="180" step="any" required>
                </div>
                <div class="form-actions full-span">
                    <button class="btn btn-primary" type="submit">Add partner location</button>
                </div>
            </form>
        </section>

        <section class="content-card" style="margin-top:20px;">
            <h2>Saved partner locations</h2>
            <?php if ($partnerLocations): ?>
                <div class="workflow-card-grid">
                    <?php foreach ($partnerLocations as $location): ?>
                        <article class="pickup-card">
                            <form method="post" class="request-grid">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="location_id" value="<?php echo (int) $location['id']; ?>">
                                <div class="field-group">
                                    <label>Name</label>
                                    <input name="name" value="<?php echo e($location['name']); ?>" required>
                                </div>
                                <div class="field-group">
                                    <label>Address</label>
                                    <input name="address" value="<?php echo e($location['address']); ?>" required>
                                </div>
                                <div id="partner-map-<?php echo (int) $location['id']; ?>" class="modal-map full-span" style="height:220px;"></div>
                                <div class="field-group">
                                    <label>Latitude</label>
                                    <input id="partner-lat-<?php echo (int) $location['id']; ?>" type="number" name="latitude" min="-90" max="90" step="any" value="<?php echo e($location['latitude']); ?>" required>
                                </div>
                                <div class="field-group">
                                    <label>Longitude</label>
                                    <input id="partner-lng-<?php echo (int) $location['id']; ?>" type="number" name="longitude" min="-180" max="180" step="any" value="<?php echo e($location['longitude']); ?>" required>
                                </div>
                                <div class="pickup-card-actions">
                                    <button class="btn btn-secondary" type="submit">Save</button>
                                </div>
                            </form>
                            <form method="post" class="inline-form" onsubmit="return confirm('Delete this partner location?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="location_id" value="<?php echo (int) $location['id']; ?>">
                                <button class="btn btn-outline" type="submit">Delete</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No partner locations have been added yet.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof initializePickupMap !== 'function') return;

    initializePickupMap({
        mapId: 'partner-location-map',
        defaultLat: 10.291998032473089,
        defaultLng: 123.9347075919448,
        latInput: document.getElementById('partner-lat'),
        lngInput: document.getElementById('partner-lng'),
        editable: true,
        allowUnselected: true
    });

    <?php foreach ($partnerLocations as $location): ?>
    initializePickupMap({
        mapId: 'partner-map-<?php echo (int) $location['id']; ?>',
        defaultLat: Number('<?php echo e($location['latitude']); ?>'),
        defaultLng: Number('<?php echo e($location['longitude']); ?>'),
        latInput: document.getElementById('partner-lat-<?php echo (int) $location['id']; ?>'),
        lngInput: document.getElementById('partner-lng-<?php echo (int) $location['id']; ?>'),
        editable: true,
        allowUnselected: false
    });
    <?php endforeach; ?>
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
