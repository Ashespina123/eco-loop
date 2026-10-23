<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user_id'])) {
    redirectByRole();
}

$pageTitle = 'Login | Eco-Loop';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    try {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        setFlash('error', 'Email and password are required.');
    } else {
        $stmt = db()->prepare('SELECT id, full_name, email, password_hash, role, status, failed_login_attempts, locked_until FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            setFlash('error', 'No account matched that email address.');
        } else {
            $lockedUntil = $user['locked_until'] ?: null;
            if ($lockedUntil && new DateTime($lockedUntil) > new DateTime()) {
                setFlash('error', 'This account is temporarily locked. Please try again later.');
            } elseif (password_verify($password, $user['password_hash'])) {
                if ($user['status'] !== 'active') {
                    setFlash('error', 'Your account is not active. Please contact support.');
                } else {
                    $resetStmt = db()->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?');
                    $resetStmt->bind_param('i', $user['id']);
                    $resetStmt->execute();
                    $resetStmt->close();

                    loginUser($user);
                    setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
                    redirectByRole();
                }
            } else {
                $attempts = (int) $user['failed_login_attempts'] + 1;
                $lockedUntil = null;
                if ($attempts >= 5) {
                    $lockedUntil = (new DateTime())->modify('+15 minutes')->format('Y-m-d H:i:s');
                }

                $lockStmt = db()->prepare('UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?');
                $lockStmt->bind_param('isi', $attempts, $lockedUntil, $user['id']);
                $lockStmt->execute();
                $lockStmt->close();

                setFlash('error', 'Incorrect email or password.');
            }
        }
    }
    } catch (Throwable $exception) {
        logEcoLoopException('EcoLoop login error', $exception);
        setFlash('error', 'Login is temporarily unavailable. Please try again shortly.');
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="auth-layout">
    <div class="auth-card">
        <div class="auth-header">
            <span class="eyebrow">Welcome back</span>
            <h1>Login to Eco-Loop</h1>
        </div>

        <form method="POST" action="<?php echo e(APP_ROOT . '/login.php'); ?>" class="auth-form">
            <?php echo csrf_field(); ?>
            <div class="field-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
            </div>
            <div class="field-group">
                <label for="password">Password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                    <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Log in</button>
        </form>

        <p class="muted-text">Don't have an account? <a href="<?php echo e(APP_ROOT . '/register.php'); ?>">Create Account</a></p>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
