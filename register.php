<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user_id'])) {
    redirectByRole();
}

$pageTitle = 'Register | Eco-Loop';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $mobile = trim($_POST['mobile_number'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    $errors = [];

    if ($fullName === '') $errors[] = 'Full name is required.';
    if ($email === '' || !isValidEmail($email)) $errors[] = 'Please provide a valid email address.';
    if ($mobile === '' || !isValidMobile($mobile)) $errors[] = 'Please provide a valid mobile number.';
    if ($password === '' || strlen($password) < 8) $errors[] = 'Password must be at least 8 characters long.';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';
    if ($address === '' || $barangay === '' || $city === '') $errors[] = 'Complete address, barangay, and city or municipality are required.';
    $hasLatitude = $latitude !== '' && $latitude !== null;
    $hasLongitude = $longitude !== '' && $longitude !== null;
    if ($hasLatitude !== $hasLongitude) $errors[] = 'Please select both map coordinates, or leave both blank to use your written address.';
    if ($hasLatitude && (!is_numeric($latitude) || !is_numeric($longitude) || (float) $latitude < -90 || (float) $latitude > 90 || (float) $longitude < -180 || (float) $longitude > 180)) {
        $errors[] = 'The selected map coordinates are invalid.';
    }
    $address = trim($address . ', Barangay ' . $barangay . ', ' . $city);

    if (empty($errors)) {
        try {
            $checkStmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();
            if ($existing) $errors[] = 'This email address is already registered.';
        } catch (Throwable $exception) {
            logEcoLoopException('EcoLoop registration lookup error', $exception);
            $errors[] = 'EcoLoop account setup is not ready yet. Please contact the site administrator.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $connection = null;
        $transactionStarted = false;
        try {
            $connection = db();
            $connection->begin_transaction();
            $transactionStarted = true;
            $stmt = $connection->prepare('INSERT INTO users (full_name, mobile_number, email, password_hash, role, address, latitude, longitude, status, failed_login_attempts, locked_until) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL)');
            $role = 'user';
            $status = 'active';
            $lat = $hasLatitude ? (float) $latitude : null;
            $lng = $hasLongitude ? (float) $longitude : null;
            $stmt->bind_param('ssssssdds', $fullName, $mobile, $email, $hash, $role, $address, $lat, $lng, $status);
            $stmt->execute();
            $userId = $stmt->insert_id;
            $stmt->close();

            $addressStmt = $connection->prepare('INSERT INTO user_addresses (user_id, address, latitude, longitude, is_primary) VALUES (?, ?, ?, ?, 1)');
            $addressStmt->bind_param('isdd', $userId, $address, $lat, $lng);
            $addressStmt->execute();
            $addressStmt->close();
            $connection->commit();
            $transactionStarted = false;

            loginUser(['id' => $userId, 'role' => 'user', 'full_name' => $fullName]);
            setFlash('success', 'Welcome to Eco-Loop, ' . $fullName . '! Your account is ready.');
            redirect('user/dashboard.php');
        } catch (mysqli_sql_exception $exception) {
            if ($transactionStarted) $connection->rollback();
            if ((int) $exception->getCode() === 1062) {
                setFlash('error', 'This email address is already registered.');
            } else {
                logEcoLoopException('EcoLoop registration database error', $exception);
                setFlash('error', 'We could not create your account right now. Please try again.');
            }
        } catch (Throwable $exception) {
            if ($transactionStarted) $connection->rollback();
            logEcoLoopException('EcoLoop registration error', $exception);
            setFlash('error', 'EcoLoop account setup is not ready yet. Please contact the site administrator.');
        }
    }

    foreach ($errors as $error) {
        setFlash('error', $error);
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="auth-layout">
    <div class="auth-card auth-card-wide">
        <div class="auth-header">
            <span class="eyebrow">Create account</span>
            <h1>Join Eco-Loop</h1>
        </div>

        <form method="POST" action="<?php echo e(APP_ROOT . '/register.php'); ?>" class="auth-form grid-two">
            <?php echo csrf_field(); ?>

            <div class="field-group">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo e($_POST['full_name'] ?? ''); ?>" required>
            </div>

            <div class="field-group">
                <label for="mobile_number">Mobile number</label>
                <input type="tel" id="mobile_number" name="mobile_number" value="<?php echo e($_POST['mobile_number'] ?? ''); ?>" required>
            </div>

            <div class="field-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
            </div>

            <div class="field-group">
                <label for="password">Password</label>
                <div class="password-field"><input type="password" id="password" name="password" autocomplete="new-password" required><button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button></div>
            </div>

            <div class="field-group">
                <label for="confirm_password">Confirm password</label>
                <div class="password-field"><input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required><button type="button" class="password-toggle" data-password-toggle="confirm_password" aria-label="Show password" aria-pressed="false">Show</button></div>
            </div>

            <div class="field-group full-span">
                <label for="address">Complete residential address</label>
                <textarea id="address" name="address" rows="3" required><?php echo e($_POST['address'] ?? ''); ?></textarea>
            </div>

            <div class="field-group">
                <label for="barangay">Barangay</label>
                <input type="text" id="barangay" name="barangay" value="<?php echo e($_POST['barangay'] ?? ''); ?>" required>
            </div>

            <div class="field-group">
                <label for="city">City or municipality</label>
                <input type="text" id="city" name="city" value="<?php echo e($_POST['city'] ?? ''); ?>" required>
            </div>

            <div class="field-group full-span">
                <label>Optional pickup map pin <small>(you can register using your written address only)</small></label>
                <div id="registration-location-map" style="height: 260px; border-radius: 16px; overflow: hidden;"></div>
            </div>
            <div class="field-group">
                <label for="latitude">Latitude (optional)</label>
                <input type="text" id="latitude" name="latitude" value="<?php echo e($_POST['latitude'] ?? ''); ?>" placeholder="Select a point on the map">
            </div>
            <div class="field-group">
                <label for="longitude">Longitude</label>
                <input type="text" id="longitude" name="longitude" value="<?php echo e($_POST['longitude'] ?? ''); ?>" placeholder="Optional">
            </div>

            <div class="field-group full-span">
                <button type="submit" class="btn btn-primary btn-block">Create account</button>
            </div>
        </form>

        <p class="muted-text">Already registered? <a href="<?php echo e(APP_ROOT . '/login.php'); ?>">Login here</a></p>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
