<?php

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    if (preg_match('/^(https?:)?\/\//i', $path)) {
        header('Location: ' . $path);
        exit;
    }

    $base = defined('APP_ROOT') ? APP_ROOT : '';
    $location = ltrim($path, '/');
    $target = $base !== '' ? $base . '/' . $location : '/' . $location;

    header('Location: ' . $target);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function renderFlash(): void
{
    $flash = getFlash();
    if (!$flash) {
        return;
    }

    $type = e($flash['type']);
    $message = e($flash['message']);
    echo '<div class="alert alert-' . $type . '" role="alert">' . $message . '</div>';
}

function formatCurrency(float $amount): string
{
    return '₱' . number_format($amount, 2, '.', ',');
}

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

function requireAuth(): void
{
    if (empty($_SESSION['user_id'])) {
        setFlash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

function requireRole(string $role): void
{
    requireAuth();
    $user = currentUser();

    if (!$user || $user['status'] !== 'active') {
        logoutUser();
        redirect('login.php');
    }

    if ($user['role'] !== $role) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
}

function requireAnyRole(array $roles): void
{
    requireAuth();
    $user = currentUser();

    if (!$user || $user['status'] !== 'active') {
        logoutUser();
        redirect('login.php');
    }

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
}

function activeUser(): ?array
{
    $user = currentUser();
    if (!$user || $user['status'] !== 'active') {
        return null;
    }

    return $user;
}

function pickupServiceFee(string $service): int
{
    return strtolower($service) === 'priority' ? 90 : 40;
}

function generatePickupReference(): string
{
    do {
        $reference = 'PR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = db()->prepare('SELECT id FROM pickup_requests WHERE reference_number = ? LIMIT 1');
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $reference;
}

function createPickupStatusEntry(int $pickupId, string $previousStatus, string $newStatus, int $changedBy, ?string $note = null): void
{
    $stmt = db()->prepare('INSERT INTO pickup_status_history (pickup_request_id, previous_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)');
    $noteText = $note ?: null;
    $stmt->bind_param('issis', $pickupId, $previousStatus, $newStatus, $changedBy, $noteText);
    $stmt->execute();
    $stmt->close();
}

function createNotification(int $userId, string $title, string $message, string $type = 'info', ?int $pickupId = null, ?int $redemptionId = null): void
{
    $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, related_pickup_id, related_redemption_id) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isssii', $userId, $title, $message, $type, $pickupId, $redemptionId);
    $stmt->execute();
    $stmt->close();
}

function getPointsBalance(int $userId): int
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(points), 0) AS total FROM points_transactions WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($result['total'] ?? 0);
}

function userRecentPickups(int $userId, int $limit = 5): array
{
    $stmt = db()->prepare('SELECT * FROM pickup_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function isValidImageUpload(array $file): array
{
    $errors = [];
    if (!isset($file['tmp_name']) || $file['tmp_name'] === '') {
        return ['valid' => true, 'path' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'The uploaded photo could not be processed.';
        return ['valid' => false, 'errors' => $errors];
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowed, true)) {
        $errors[] = 'Only JPG, PNG, or WEBP image files are allowed.';
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = 'Image uploads must be 2MB or smaller.';
    }

    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $targetName = uniqid('pickup_', true) . '.' . $extension;
    $targetDir = __DIR__ . '/../uploads/pickups';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $destination = $targetDir . '/' . $targetName;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['valid' => false, 'errors' => ['The image could not be saved securely.']];
    }

    return ['valid' => true, 'path' => 'uploads/pickups/' . $targetName];
}

function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidMobile(string $mobile): bool
{
    $clean = preg_replace('/[^0-9+]/', '', $mobile);
    return preg_match('/^(\+?63|0)[0-9]{9,10}$/', $clean) === 1;
}

function getBaseUrl(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $base = dirname($script);
    return $base === '/' ? '' : $base;
}

function asset(string $path): string
{
    $base = getBaseUrl();
    return $base . '/assets/' . ltrim($path, '/');
}
