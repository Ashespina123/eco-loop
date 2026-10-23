<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$pageTitle = 'My Profile | Eco-Loop';
$user = currentUser();
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim((string) ($_POST['full_name'] ?? ''));
    $mobile = trim((string) ($_POST['mobile_number'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $lat = trim((string) ($_POST['latitude'] ?? ''));
    $lng = trim((string) ($_POST['longitude'] ?? ''));
    if ($name === '' || mb_strlen($name) > 150 || !isValidMobile($mobile) || $address === '') {
        setFlash('error', 'Enter your name, a valid mobile number, and pickup address.');
        redirect('user/profile.php');
    }
    if (($lat === '') !== ($lng === '') || ($lat !== '' && (!is_numeric($lat) || !is_numeric($lng) || (float) $lat < -90 || (float) $lat > 90 || (float) $lng < -180 || (float) $lng > 180))) {
        setFlash('error', 'Select both valid map coordinates, or clear the pin.');
        redirect('user/profile.php');
    }
    $latitude = $lat === '' ? null : (float) $lat;
    $longitude = $lng === '' ? null : (float) $lng;
    $connection = db();
    try {
        $connection->begin_transaction();
        $update = $connection->prepare('UPDATE users SET full_name = ?, mobile_number = ?, address = ?, latitude = ?, longitude = ? WHERE id = ?');
        $update->bind_param('sssddi', $name, $mobile, $address, $latitude, $longitude, $userId);
        $update->execute();
        $update->close();
        $primary = $connection->prepare('SELECT id FROM user_addresses WHERE user_id = ? AND is_primary = 1 ORDER BY id LIMIT 1');
        $primary->bind_param('i', $userId);
        $primary->execute();
        $primaryAddress = $primary->get_result()->fetch_assoc();
        $primary->close();
        if ($primaryAddress) {
            $addressId = (int) $primaryAddress['id'];
            $addressUpdate = $connection->prepare('UPDATE user_addresses SET address = ?, latitude = ?, longitude = ? WHERE id = ? AND user_id = ?');
            $addressUpdate->bind_param('sddii', $address, $latitude, $longitude, $addressId, $userId);
            $addressUpdate->execute();
            $addressUpdate->close();
        } else {
            $addressInsert = $connection->prepare('INSERT INTO user_addresses (user_id, address, latitude, longitude, is_primary) VALUES (?, ?, ?, ?, 1)');
            $addressInsert->bind_param('isdd', $userId, $address, $latitude, $longitude);
            $addressInsert->execute();
            $addressInsert->close();
        }
        $connection->commit();
        setFlash('success', 'Your account details were updated.');
    } catch (Throwable $exception) {
        $connection->rollback();
        error_log('EcoLoop profile update failed: ' . $exception->getMessage());
        setFlash('error', 'Your account details could not be updated. Please try again.');
    }
    redirect('user/profile.php');
}

$user = currentUser();
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header"><div><span class="eyebrow">Account</span><h1>Profile and Pickup Location</h1></div></div>
        <form method="post" class="content-card request-form">
            <?php echo csrf_field(); ?>
            <div class="request-grid">
                <div class="field-group"><label for="full_name">Full name</label><input id="full_name" name="full_name" maxlength="150" value="<?php echo e($user['full_name']); ?>" required></div>
                <div class="field-group"><label for="email">Email address</label><input id="email" value="<?php echo e($user['email']); ?>" readonly><small>Email changes require account verification.</small></div>
                <div class="field-group"><label for="mobile_number">Contact number</label><input id="mobile_number" name="mobile_number" type="tel" value="<?php echo e($user['mobile_number']); ?>" required></div>
                <div class="field-group full-span"><label for="address">Complete pickup address</label><textarea id="address" name="address" rows="3" required><?php echo e($user['address']); ?></textarea></div>
                <div class="field-group full-span"><label>Pickup map pin (optional)</label><div id="profile-location-map" class="modal-map"></div><div class="map-actions"><button type="button" id="use-my-location" class="btn btn-secondary">Use my location</button><span class="coordinates-text">Pin: <strong id="coord-value">No pin selected</strong></span><button type="button" id="clear-profile-location" class="btn btn-outline">Clear pin</button></div><input type="hidden" id="latitude" name="latitude" value="<?php echo e($user['latitude'] ?? ''); ?>"><input type="hidden" id="longitude" name="longitude" value="<?php echo e($user['longitude'] ?? ''); ?>"></div>
            </div>
            <div class="form-actions"><button class="btn btn-primary" type="submit">Save Account Details</button></div>
        </form>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof initializePickupMap === 'function') initializePickupMap({
        mapId: 'profile-location-map',
        defaultLat: <?php echo $user['latitude'] !== null ? json_encode((float) $user['latitude']) : '14.5995'; ?>,
        defaultLng: <?php echo $user['longitude'] !== null ? json_encode((float) $user['longitude']) : '120.9842'; ?>,
        latInput: document.getElementById('latitude'), lngInput: document.getElementById('longitude'),
        coordValue: document.getElementById('coord-value'), editable: true, allowUnselected: true,
        clearButtonId: 'clear-profile-location'
    });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
