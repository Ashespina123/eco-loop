<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$pageTitle = 'Request Pickup | Eco-Loop';
$user = currentUser();

$allowedCategories = [
    'Mobile phones',
    'Chargers and cables',
    'Small electronic devices',
    'Computers and laptops',
    'Keyboards and computer accessories',
    'Televisions and monitors',
    'Printers and scanners',
    'Batteries',
    'Other electronic waste'
];
$selectedService = 'saver';
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $selectedService = strtolower(trim($_POST['service_type'] ?? 'saver'));
    if (!in_array($selectedService, ['saver', 'priority'], true)) {
        $formErrors[] = 'Please choose a valid pickup service.';
    }

    $selectedCategories = array_values(array_filter(array_map('trim', $_POST['item_categories'] ?? []), static fn($value) => $value !== ''));
    $estimatedQuantity = trim($_POST['estimated_quantity'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $pickupAddress = trim($_POST['pickup_address'] ?? '');
    $latitude = trim((string) ($_POST['latitude'] ?? ''));
    $longitude = trim((string) ($_POST['longitude'] ?? ''));
    $preferredDate = trim($_POST['preferred_date'] ?? '');
    $preferredTime = trim($_POST['preferred_time'] ?? '');
    $photoFile = $_FILES['photo'] ?? null;

    if (empty($selectedCategories)) {
        $formErrors[] = 'Please select at least one e-waste category.';
    }

    if ($estimatedQuantity === '') {
        $formErrors[] = 'Estimated quantity is required.';
    }

    if ($pickupAddress === '') {
        $formErrors[] = 'Pickup address is required.';
    }

    if ($selectedService === 'saver' && $preferredDate === '') {
        $formErrors[] = 'Choose a preferred date for Convenience (Saver) collection.';
    }
    if ($preferredDate !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $preferredDate);
        if (!$date || $date->format('Y-m-d') !== $preferredDate || $date < new DateTime('today')) {
            $formErrors[] = 'Choose a valid pickup date that is not in the past.';
        }
    }
    if ($preferredTime !== '' && ($preferredTime < '08:00' || $preferredTime > '17:00')) {
        $formErrors[] = 'Collection time must be between 8:00 AM and 5:00 PM.';
    }

    if (($latitude === '') !== ($longitude === '')) {
        $formErrors[] = 'Choose both map coordinates, or leave both blank to use your written address.';
    } elseif ($latitude !== '' && (!is_numeric($latitude) || !is_numeric($longitude) || (float) $latitude < -90 || (float) $latitude > 90 || (float) $longitude < -180 || (float) $longitude > 180)) {
        $formErrors[] = 'The selected map coordinates are invalid.';
    }

    $photoPath = null;
    if ($photoFile && $photoFile['name'] !== '') {
        $photoResult = isValidImageUpload($photoFile);
        if (!$photoResult['valid']) {
            foreach ($photoResult['errors'] as $error) {
                $formErrors[] = $error;
            }
        } else {
            $photoPath = $photoResult['path'];
        }
    }

    if (empty($formErrors)) {
        $fee = pickupServiceFee($selectedService);
        $hazardous = in_array('Batteries', $selectedCategories, true) || in_array('Other electronic waste', $selectedCategories, true) ? 1 : 0;
        $referenceNumber = generatePickupReference();

        $stmt = db()->prepare('INSERT INTO pickup_requests (user_id, reference_number, service_type, service_fee, status, estimated_quantity, description, pickup_address, latitude, longitude, preferred_date, preferred_time, hazardous, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $status = 'Pending';
        $serviceFee = (float) $fee;
        $latValue = $latitude === '' ? null : (float) $latitude;
        $lngValue = $longitude === '' ? null : (float) $longitude;
        $preferredDateValue = $preferredDate === '' ? null : $preferredDate;
        $preferredTimeValue = $preferredTime === '' ? null : $preferredTime;

        $customerId = (int) $user['id'];
        $stmt->bind_param('issdssssddssis', $customerId, $referenceNumber, $selectedService, $serviceFee, $status, $estimatedQuantity, $description, $pickupAddress, $latValue, $lngValue, $preferredDateValue, $preferredTimeValue, $hazardous, $photoPath);
        $stmt->execute();
        $pickupId = $stmt->insert_id;
        $stmt->close();

        foreach ($selectedCategories as $category) {
            $itemStmt = db()->prepare('INSERT INTO pickup_items (pickup_request_id, item_category, quantity) VALUES (?, ?, 1)');
            $itemStmt->bind_param('is', $pickupId, $category);
            $itemStmt->execute();
            $itemStmt->close();
        }

        createPickupStatusEntry((int) $pickupId, 'New', 'Pending', (int) $user['id'], 'Pickup request submitted by the customer.');
        createNotification((int) $user['id'], 'Pickup request submitted', 'Your pickup request ' . $referenceNumber . ' has been received and is waiting for review.', 'info', (int) $pickupId);

        setFlash('success', 'Your pickup request has been submitted successfully. Reference: ' . $referenceNumber);
        redirect('user/my-pickups.php');
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div>
                <span class="eyebrow">Pickup request</span>
                <h1>Request E-Waste Pickup</h1>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" class="request-form" action="<?php echo e(APP_ROOT . '/user/request-pickup.php'); ?>">
            <?php echo csrf_field(); ?>
            <div class="content-card">
                <h3>Select service</h3>
                <div class="service-grid">
                    <label class="service-option">
                        <input type="radio" name="service_type" value="saver" <?php echo $selectedService === 'saver' ? 'checked' : ''; ?>>
                        <span>Convenience (Saver)</span>
                        <small>₱40 · Regular collection, 8:00 AM–5:00 PM. Collection follows the regular schedule and may not be immediate.</small>
                    </label>
                    <label class="service-option">
                        <input type="radio" name="service_type" value="priority" <?php echo $selectedService === 'priority' ? 'checked' : ''; ?>>
                        <span>Priority Collection</span>
                        <small>₱90 · Urgent handling as soon as possible, subject to collector availability and location.</small>
                    </label>
                </div>
            </div>

            <div class="content-card">
                <h3>E-waste categories</h3>
                <div class="checkbox-grid">
                    <?php foreach ($allowedCategories as $category): ?>
                        <label class="check-item">
                            <input type="checkbox" name="item_categories[]" value="<?php echo e($category); ?>" <?php echo in_array($category, $_POST['item_categories'] ?? [], true) ? 'checked' : ''; ?>>
                            <?php echo e($category); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="content-card request-grid">
                <div class="field-group">
                    <label for="estimated_quantity">Estimated quantity</label>
                    <input type="text" id="estimated_quantity" name="estimated_quantity" value="<?php echo e($_POST['estimated_quantity'] ?? ''); ?>" placeholder="e.g. 3 boxes" required>
                </div>

                <div class="field-group">
                    <label for="preferred_date">Preferred pickup date</label>
                    <input type="date" id="preferred_date" name="preferred_date" value="<?php echo e($_POST['preferred_date'] ?? ''); ?>">
                </div>

                <div class="field-group">
                    <label for="preferred_time">Preferred pickup time</label>
                    <input type="time" id="preferred_time" name="preferred_time" value="<?php echo e($_POST['preferred_time'] ?? ''); ?>">
                </div>

                <div class="field-group">
                    <label for="photo">Optional item photo</label>
                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                </div>

                <div class="field-group full-span">
                    <label for="pickup_address">Pickup address</label>
                    <textarea id="pickup_address" name="pickup_address" rows="3" required><?php echo e($_POST['pickup_address'] ?? $user['address'] ?? ''); ?></textarea>
                </div>

                <div class="field-group full-span">
                    <label for="description">Additional description</label>
                    <textarea id="description" name="description" rows="4" placeholder="Include any especially hazardous items, access notes, or item details."><?php echo e($_POST['description'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="content-card">
                <h3>Map location</h3>
                <div id="pickup-location-map" style="height: 360px; border-radius: 16px; overflow: hidden; margin-bottom: 14px;"></div>
                <div class="map-actions">
                    <button type="button" id="use-my-location" class="btn btn-secondary">Use my current location</button>
                    <span class="coordinates-text">Selected coordinates: <strong id="coord-value">Not selected</strong></span>
                </div>
                <input type="hidden" id="latitude" name="latitude" value="<?php echo e($_POST['latitude'] ?? ''); ?>">
                <input type="hidden" id="longitude" name="longitude" value="<?php echo e($_POST['longitude'] ?? ''); ?>">
            </div>

            <div class="content-card">
                <h3>Confirmation summary</h3>
                <div class="summary-row">
                    <span>Service</span>
                    <strong><?php echo e(strtoupper($selectedService)); ?> pickup</strong>
                </div>
                <div class="summary-row">
                    <span>Proposed fee</span>
                    <strong><?php echo e(formatCurrency((float) pickupServiceFee($selectedService))); ?></strong>
                </div>
                <div class="summary-row">
                    <span>Hazardous waste</span>
                    <strong><?php echo in_array('Batteries', $_POST['item_categories'] ?? [], true) || in_array('Other electronic waste', $_POST['item_categories'] ?? [], true) ? 'Yes' : 'No'; ?></strong>
                </div>
            </div>

            <?php if (!empty($formErrors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($formErrors as $error): ?>
                        <div><?php echo e($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit pickup request</button>
            </div>
        </form>
    </main>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof initializePickupMap === 'function') {
            initializePickupMap({
                mapId: 'pickup-location-map',
                defaultLat: 14.5995,
                defaultLng: 121.0244,
                latInput: document.getElementById('latitude'),
                lngInput: document.getElementById('longitude'),
                coordValue: document.getElementById('coord-value'),
                editable: true,
                allowUnselected: true
            });
        }
    });
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
