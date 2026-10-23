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
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    $errors = [];

    if ($fullName === '') $errors[] = 'Full name is required.';
    if ($email === '' || !isValidEmail($email)) $errors[] = 'Please provide a valid email address.';
    if ($mobile === '' || !isValidMobile($mobile)) $errors[] = 'Please provide a valid mobile number.';
    if ($password === '' || strlen($password) < 8) $errors[] = 'Password must be at least 8 characters long.';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';
    if ($address === '') $errors[] = 'Residential address is required.';

    if (empty($errors)) {
        $checkStmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $checkStmt->bind_param('s', $email);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            $errors[] = 'This email address is already registered.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare('INSERT INTO users (full_name, mobile_number, email, password_hash, role, address, latitude, longitude, status, failed_login_attempts, locked_until) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL)');
        $role = 'user';
        $status = 'active';
        $lat = $latitude !== '' && $latitude !== null ? (float) $latitude : null;
        $lng = $longitude !== '' && $longitude !== null ? (float) $longitude : null;

        $stmt->bind_param('ssssssdds', $fullName, $mobile, $email, $hash, $role, $address, $lat, $lng, $status);
        $stmt->execute();
        $userId = $stmt->insert_id;
        $stmt->close();

        $addressStmt = db()->prepare('INSERT INTO user_addresses (user_id, address, latitude, longitude, is_primary) VALUES (?, ?, ?, ?, 1)');
        $addressStmt->bind_param('isdd', $userId, $address, $lat, $lng);
        $addressStmt->execute();
        $addressStmt->close();

        setFlash('success', 'Registration successful. You can log in now.');
        redirect('login.php');
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
                <input type="password" id="password" name="password" required>
            </div>

            <div class="field-group">
                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="field-group">
                <label for="latitude">Latitude</label>
                <input type="text" id="latitude" name="latitude" value="<?php echo e($_POST['latitude'] ?? ''); ?>" placeholder="Optional">
            </div>

            <div class="field-group full-span">
                <label for="address">Complete residential address</label>
                <textarea id="address" name="address" rows="3" required><?php echo e($_POST['address'] ?? ''); ?></textarea>
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
