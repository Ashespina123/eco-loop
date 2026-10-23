<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$admin = currentUser();
$pageTitle = 'Collection Points | Eco-Loop';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $id = filter_var($_POST['point_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    try {
        if ($action === 'deactivate' && $id > 0) {
            $oldStmt = db()->prepare('SELECT name FROM collection_points WHERE id = ?');
            $oldStmt->bind_param('i', $id); $oldStmt->execute(); $oldPoint = $oldStmt->get_result()->fetch_assoc(); $oldStmt->close();
            $stmt = db()->prepare('UPDATE collection_points SET is_active = 0 WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            logAdminAction((int) $admin['id'], 'Drop-off center deactivated', 'Center #' . $id . ' ' . ($oldPoint['name'] ?? ''));
            setFlash('success', 'Collection point deactivated.');
        } else {
            $name = trim((string) ($_POST['name'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ''));
            $latitude = trim((string) ($_POST['latitude'] ?? ''));
            $longitude = trim((string) ($_POST['longitude'] ?? ''));
            $hours = trim((string) ($_POST['operating_hours'] ?? ''));
            $contact = trim((string) ($_POST['contact_details'] ?? ''));
            $accepted = $_POST['accepted_categories'] ?? [];
            $validCategories = ['Mobile phones', 'Computers and laptops', 'Batteries', 'Chargers and cables', 'Small electronic appliances', 'Other', 'Earphones', 'Charger', 'Phone', 'Battery', 'Motherboard', 'Keyboard', 'Mouse'];
            if (!is_array($accepted) || array_diff($accepted, $validCategories)) throw new RuntimeException('Choose valid accepted e-waste categories.');
            $acceptedText = implode(', ', array_unique($accepted));
            if (mb_strlen($hours) > 255 || mb_strlen($contact) > 150) throw new RuntimeException('Operating hours or contact details are too long.');
            $active = isset($_POST['is_active']) ? 1 : 0;
            if ($name === '' || mb_strlen($name) > 150 || $address === '' || !is_numeric($latitude) || !is_numeric($longitude) || (float) $latitude < -90 || (float) $latitude > 90 || (float) $longitude < -180 || (float) $longitude > 180) throw new RuntimeException('Enter a name, address, and valid map coordinates.');
            $lat = (float) $latitude;
            $lng = (float) $longitude;
            if ($id > 0) {
                $oldStmt = db()->prepare('SELECT name,address,latitude,longitude,is_active FROM collection_points WHERE id = ?');
                $oldStmt->bind_param('i', $id); $oldStmt->execute(); $oldPoint = $oldStmt->get_result()->fetch_assoc(); $oldStmt->close();
                $stmt = db()->prepare('UPDATE collection_points SET name = ?, address = ?, operating_hours = ?, accepted_categories = ?, contact_details = ?, latitude = ?, longitude = ?, is_active = ? WHERE id = ?');
                $stmt->bind_param('sssssddii', $name, $address, $hours, $acceptedText, $contact, $lat, $lng, $active, $id);
            } else {
                $adminId = (int) $admin['id'];
                $stmt = db()->prepare('INSERT INTO collection_points (name, address, operating_hours, accepted_categories, contact_details, latitude, longitude, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sssssddii', $name, $address, $hours, $acceptedText, $contact, $lat, $lng, $active, $adminId);
            }
            $stmt->execute();
            $savedId = $id > 0 ? $id : (int) db()->insert_id;
            $stmt->close();
            $oldDescription = isset($oldPoint) ? 'Previous: ' . $oldPoint['name'] . ' / ' . $oldPoint['address'] . ' (' . $oldPoint['latitude'] . ',' . $oldPoint['longitude'] . '), active=' . $oldPoint['is_active'] . '. ' : '';
            logAdminAction((int) $admin['id'], $id > 0 ? 'Drop-off center updated' : 'Drop-off center added', 'Center #' . $savedId . ': ' . $name . ' / ' . $address . ' (' . $latitude . ',' . $longitude . '). ' . $oldDescription);
            setFlash('success', 'Collection point saved.');
        }
    } catch (RuntimeException $exception) {
        setFlash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('EcoLoop collection point save failed: ' . $exception->getMessage());
        setFlash('error', 'The collection point could not be saved. Please try again.');
    }
    redirect('admin/collection-points.php');
}
$points = db()->query('SELECT * FROM collection_points ORDER BY is_active DESC, name')->fetch_all(MYSQLI_ASSOC);
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header"><div><span class="eyebrow">Admin</span><h1>Collection Points</h1></div></div>
        <section class="content-card"><h2>Add verified drop-off center</h2><p>Enter a real public collection location. Tap its map to set the pin; no sample center is inserted.</p>
            <form method="post" class="request-grid">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="save">
                <div class="field-group"><label>Name</label><input name="name" maxlength="150" required></div>
                <div class="field-group"><label>Address</label><input name="address" required></div>
                <div class="field-group"><label>Operating hours</label><input name="operating_hours" maxlength="255" placeholder="Set verified hours"></div>
                <div class="field-group"><label>Contact details</label><input name="contact_details" maxlength="150"></div>
                <fieldset class="full-span"><legend>Accepted e-waste categories</legend><?php foreach (['Mobile phones', 'Computers and laptops', 'Batteries', 'Chargers and cables', 'Small electronic appliances', 'Other', 'Earphones', 'Charger', 'Phone', 'Battery', 'Motherboard', 'Keyboard', 'Mouse'] as $category): ?><label class="check-item"><input type="checkbox" name="accepted_categories[]" value="<?php echo e($category); ?>"> <?php echo e($category); ?></label><?php endforeach; ?></fieldset>
                <div id="new-center-map" class="modal-map full-span" style="height:300px"></div>
                <div class="field-group"><label>Latitude (or tap map)</label><input id="new-center-lat" type="number" name="latitude" min="-90" max="90" step="any" required></div>
                <div class="field-group"><label>Longitude (or tap map)</label><input id="new-center-lng" type="number" name="longitude" min="-180" max="180" step="any" required></div>
                <label class="check-item"><input type="checkbox" name="is_active" checked> Active on customer map</label>
                <div class="form-actions full-span"><button class="btn btn-primary" type="submit">Add location</button></div>
            </form>
        </section>
        <section class="content-card" style="margin-top:20px"><h2>Saved locations</h2>
            <?php if ($points): ?><div class="workflow-card-grid">
                <?php foreach ($points as $point): $selectedCategories = array_filter(array_map('trim', explode(',', (string) ($point['accepted_categories'] ?? '')))); ?><article class="pickup-card"><form method="post" class="request-grid">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="point_id" value="<?php echo (int) $point['id']; ?>">
                    <div class="field-group"><label>Name</label><input name="name" value="<?php echo e($point['name']); ?>" required></div><div class="field-group"><label>Address</label><input name="address" value="<?php echo e($point['address']); ?>" required></div>
                    <div class="field-group"><label>Operating hours</label><input name="operating_hours" maxlength="255" value="<?php echo e($point['operating_hours'] ?? ''); ?>"></div><div class="field-group"><label>Contact details</label><input name="contact_details" maxlength="150" value="<?php echo e($point['contact_details'] ?? ''); ?>"></div>
                    <fieldset class="full-span"><legend>Accepted categories</legend><?php foreach (['Mobile phones', 'Computers and laptops', 'Batteries', 'Chargers and cables', 'Small electronic appliances', 'Other', 'Earphones', 'Charger', 'Phone', 'Battery', 'Motherboard', 'Keyboard', 'Mouse'] as $category): ?><label class="check-item"><input type="checkbox" name="accepted_categories[]" value="<?php echo e($category); ?>" <?php echo in_array($category, $selectedCategories, true) ? 'checked' : ''; ?>> <?php echo e($category); ?></label><?php endforeach; ?></fieldset>
                    <div id="center-map-<?php echo (int) $point['id']; ?>" class="modal-map full-span" style="height:260px"></div>
                    <div class="field-group"><label>Latitude</label><input id="center-lat-<?php echo (int) $point['id']; ?>" type="number" name="latitude" min="-90" max="90" step="any" value="<?php echo e($point['latitude']); ?>" required></div><div class="field-group"><label>Longitude</label><input id="center-lng-<?php echo (int) $point['id']; ?>" type="number" name="longitude" min="-180" max="180" step="any" value="<?php echo e($point['longitude']); ?>" required></div>
                    <label class="check-item"><input type="checkbox" name="is_active" <?php echo (int) $point['is_active'] ? 'checked' : ''; ?>> Active on customer map</label><div class="pickup-card-actions"><button class="btn btn-secondary" type="submit">Save</button></div>
                </form><form method="post" class="inline-form" onsubmit="return confirm('Deactivate this collection point?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="deactivate"><input type="hidden" name="point_id" value="<?php echo (int) $point['id']; ?>"><button class="btn btn-outline" type="submit">Deactivate</button></form></article><?php endforeach; ?>
            </div><?php else: ?><div class="empty-state"><p>No collection points are configured. No sample locations have been added.</p></div><?php endif; ?>
        </section>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){if(typeof initializePickupMap!=='function')return;
 initializePickupMap({mapId:'new-center-map',defaultLat:10.291998032473089,defaultLng:123.9347075919448,latInput:document.getElementById('new-center-lat'),lngInput:document.getElementById('new-center-lng'),editable:true,allowUnselected:true});
 <?php foreach ($points as $point): ?>initializePickupMap({mapId:'center-map-<?php echo (int) $point['id']; ?>',defaultLat:Number('<?php echo e($point['latitude']); ?>'),defaultLng:Number('<?php echo e($point['longitude']); ?>'),latInput:document.getElementById('center-lat-<?php echo (int) $point['id']; ?>'),lngInput:document.getElementById('center-lng-<?php echo (int) $point['id']; ?>'),editable:true,allowUnselected:false});<?php endforeach; ?>
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
