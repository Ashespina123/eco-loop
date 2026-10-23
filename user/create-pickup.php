<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');

header('Content-Type: application/json; charset=utf-8');

function pickupJson(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    pickupJson(405, ['ok' => false, 'message' => 'Use the pickup request form to submit a request.']);
}

if (!verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
    pickupJson(419, ['ok' => false, 'message' => 'This form expired. Reload the page and try again.']);
}

$user = currentUser();
$userId = (int) $user['id'];
$fullName = trim((string) ($_POST['full_name'] ?? ''));
$contactNumber = trim((string) ($_POST['contact_number'] ?? ''));
$service = strtolower(trim((string) ($_POST['service_type'] ?? '')));
$categoriesRaw = $_POST['item_categories'] ?? $_POST['item_category'] ?? [];
$quantitiesRaw = $_POST['quantities'] ?? $_POST['quantity'] ?? [];
if (!is_array($categoriesRaw)) $categoriesRaw = [$categoriesRaw];
if (!is_array($quantitiesRaw)) $quantitiesRaw = [$quantitiesRaw];
$address = trim((string) ($_POST['pickup_address'] ?? ''));
$date = trim((string) ($_POST['preferred_date'] ?? ''));
$time = trim((string) ($_POST['preferred_time'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));
$latitude = trim((string) ($_POST['latitude'] ?? ''));
$longitude = trim((string) ($_POST['longitude'] ?? ''));
$collectionPointRaw = trim((string) ($_POST['collection_point_id'] ?? ''));
$token = strtolower(trim((string) ($_POST['request_token'] ?? '')));

$categories = ['Mobile phones', 'Computers and laptops', 'Batteries', 'Chargers and cables', 'Small electronic appliances', 'Other'];
$errors = [];
if ($fullName === '' || mb_strlen($fullName) > 150) $errors[] = 'Enter your name (up to 150 characters).';
if ($contactNumber === '' || mb_strlen($contactNumber) > 20 || !preg_match('/^\+?[0-9][0-9 .()\-]{6,19}$/', $contactNumber)) $errors[] = 'Enter a valid contact number.';
if (!in_array($service, ['saver', 'priority'], true)) $errors[] = 'Choose a valid collection service.';
$items = [];
$totalQuantity = 0;
$selectedCategories = [];
if (count($categoriesRaw) !== count($quantitiesRaw) || count($categoriesRaw) < 1 || count($categoriesRaw) > count($categories)) {
    $errors[] = 'Choose at least one valid e-waste category.';
} else {
    foreach ($categoriesRaw as $index => $categoryRaw) {
        $category = trim((string) $categoryRaw);
        $quantityRaw = trim((string) ($quantitiesRaw[$index] ?? ''));
        if (!in_array($category, $categories, true)) {
            $errors[] = 'Choose a valid e-waste category for every item.';
            continue;
        }
        if (isset($selectedCategories[$category])) {
            $errors[] = 'Each e-waste category can only be selected once. Combine its quantity in one row.';
            continue;
        }
        if (!ctype_digit($quantityRaw) || (int) $quantityRaw < 1 || (int) $quantityRaw > 10000) {
            $errors[] = 'Enter a quantity between 1 and 10,000 for each category.';
            continue;
        }
        $quantity = (int) $quantityRaw;
        $totalQuantity += $quantity;
        if ($totalQuantity > 10000) {
            $errors[] = 'The total quantity cannot exceed 10,000 items.';
            break;
        }
        $selectedCategories[$category] = true;
        $items[] = ['category' => $category, 'quantity' => $quantity];
    }
}
if ($address === '' || mb_strlen($address) > 2000) $errors[] = 'Enter a pickup address (up to 2,000 characters).';
if ($service === 'saver' && $date === '') $errors[] = 'A preferred date is required for Convenience (Saver).';
if ($date !== '') {
    $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date || $parsedDate < new DateTime('today')) $errors[] = 'Choose a valid date that is not in the past.';
}
if ($time !== '' && (!preg_match('/^\d{2}:\d{2}$/', $time) || $time < '08:00' || $time > '17:00')) $errors[] = 'Choose a time between 8:00 AM and 5:00 PM.';
if ($latitude === '' && $longitude === '') {
    $errors[] = 'Mark your pickup location on the map before submitting.';
} elseif (($latitude === '') !== ($longitude === '')) {
    $errors[] = 'Select both map coordinates, or leave both blank.';
} elseif ($latitude !== '' && (!is_numeric($latitude) || !is_numeric($longitude) || (float) $latitude < -90 || (float) $latitude > 90 || (float) $longitude < -180 || (float) $longitude > 180)) {
    $errors[] = 'The selected pickup location is invalid.';
}
if ($notes !== '' && mb_strlen($notes) > 5000) $errors[] = 'Notes must be 5,000 characters or fewer.';
if ($collectionPointRaw !== '' && (!ctype_digit($collectionPointRaw) || (int) $collectionPointRaw < 1)) $errors[] = 'Choose a valid verified collection point.';
if ($collectionPointRaw === '' && (int) db()->query('SELECT COUNT(*) AS total FROM collection_points WHERE is_active = 1')->fetch_assoc()['total'] > 0) $errors[] = 'Select a verified collection point so we can calculate the distance-based fee.';
if (!preg_match('/^[a-f0-9]{64}$/', $token)) $errors[] = 'Reload the page and try submitting again.';

if ($errors) pickupJson(422, ['ok' => false, 'message' => implode(' ', $errors)]);

$connection = db();
$existingStmt = $connection->prepare('SELECT id, reference_number, status FROM pickup_requests WHERE request_token = ? AND user_id = ? LIMIT 1');
$existingStmt->bind_param('si', $token, $userId);
$existingStmt->execute();
$existing = $existingStmt->get_result()->fetch_assoc();
$existingStmt->close();
if ($existing) {
    setFlash('success', 'Pickup request ' . $existing['reference_number'] . ' is already saved as ' . $existing['status'] . '.');
    pickupJson(200, ['ok' => true, 'duplicate' => true, 'id' => (int) $existing['id'], 'reference' => $existing['reference_number'], 'status' => $existing['status']]);
}

try {
    $connection->begin_transaction();
    $collectionPointId = $collectionPointRaw === '' ? null : (int) $collectionPointRaw;
    $distanceKm = null;
    if ($collectionPointId !== null) {
        $pointStmt = $connection->prepare('SELECT id, latitude, longitude FROM collection_points WHERE id = ? AND is_active = 1 LIMIT 1 FOR UPDATE');
        $pointStmt->bind_param('i', $collectionPointId);
        $pointStmt->execute();
        $point = $pointStmt->get_result()->fetch_assoc();
        $pointStmt->close();
        if (!$point) throw new RuntimeException('That collection point is no longer available. Choose another destination.');
        $latDelta = deg2rad((float) $point['latitude'] - (float) $latitude);
        $lngDelta = deg2rad((float) $point['longitude'] - (float) $longitude);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad((float) $latitude)) * cos(deg2rad((float) $point['latitude'])) * sin($lngDelta / 2) ** 2;
        $distanceKm = 6371.0088 * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
    }
    $pricing = pickupPriceBreakdown($service, $distanceKm);
    $fee = (float) $pricing['total_fee'];
    $baseFee = (float) $pricing['base_fee'];
    $distanceCharge = (float) $pricing['distance_charge'];
    $distanceKmValue = $pricing['distance_km'];
    $distanceMethod = (string) $pricing['method'];
    $status = 'Pending';
    $estimatedQuantity = $totalQuantity . ($totalQuantity === 1 ? ' item' : ' items');
    $reference = generatePickupReference();
    $description = $notes === '' ? null : $notes;
    $latValue = $latitude === '' ? null : (float) $latitude;
    $lngValue = $longitude === '' ? null : (float) $longitude;
    $dateValue = $date === '' ? null : $date;
    $timeValue = $time === '' ? null : $time . ':00';
    $hazardous = isset($selectedCategories['Batteries']) ? 1 : 0;

    $stmt = $connection->prepare('INSERT INTO pickup_requests (user_id, customer_name, contact_number, request_token, reference_number, service_type, service_fee, base_fee, distance_charge, distance_km, distance_method, status, estimated_quantity, description, pickup_address, latitude, longitude, collection_point_id, preferred_date, preferred_time, hazardous) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isssssddddsssssddissi', $userId, $fullName, $contactNumber, $token, $reference, $service, $fee, $baseFee, $distanceCharge, $distanceKmValue, $distanceMethod, $status, $estimatedQuantity, $description, $address, $latValue, $lngValue, $collectionPointId, $dateValue, $timeValue, $hazardous);
    $stmt->execute();
    $pickupId = (int) $stmt->insert_id;
    $stmt->close();

    $itemStmt = $connection->prepare('INSERT INTO pickup_items (pickup_request_id, item_category, quantity, notes) VALUES (?, ?, ?, ?)');
    foreach ($items as $item) {
        $category = $item['category'];
        $quantity = $item['quantity'];
        $itemStmt->bind_param('isis', $pickupId, $category, $quantity, $description);
        $itemStmt->execute();
    }
    $itemStmt->close();

    createPickupStatusEntry($pickupId, 'New', $status, $userId, 'Pickup request submitted by the customer.');
    createNotification($userId, 'Pickup request submitted', 'Your pickup request ' . $reference . ' is Pending.', 'info', $pickupId);
    $connection->commit();

    setFlash('success', 'Pickup request ' . $reference . ' has been saved as Pending.');
    pickupJson(201, ['ok' => true, 'id' => $pickupId, 'reference' => $reference, 'status' => $status]);
} catch (RuntimeException $exception) {
    $connection->rollback();
    pickupJson(422, ['ok' => false, 'message' => $exception->getMessage()]);
} catch (mysqli_sql_exception $exception) {
    $connection->rollback();
    if ((int) $exception->getCode() === 1062) {
        $existingStmt = $connection->prepare('SELECT id, reference_number, status FROM pickup_requests WHERE request_token = ? AND user_id = ? LIMIT 1');
        $existingStmt->bind_param('si', $token, $userId);
        $existingStmt->execute();
        $existing = $existingStmt->get_result()->fetch_assoc();
        $existingStmt->close();
        if ($existing) {
            setFlash('success', 'Pickup request ' . $existing['reference_number'] . ' is already saved as ' . $existing['status'] . '.');
            pickupJson(200, ['ok' => true, 'duplicate' => true, 'id' => (int) $existing['id'], 'reference' => $existing['reference_number'], 'status' => $existing['status']]);
        }
    }
    error_log('EcoLoop pickup request failed: ' . $exception->getMessage());
    pickupJson(500, ['ok' => false, 'message' => 'We could not save the pickup request. Please try again.']);
} catch (Throwable $exception) {
    $connection->rollback();
    error_log('EcoLoop pickup request failed: ' . $exception->getMessage());
    pickupJson(500, ['ok' => false, 'message' => 'We could not save the pickup request. Please try again.']);
}
