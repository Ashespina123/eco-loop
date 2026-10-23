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

    if (!$user || $user['role'] !== $role) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
}

function requireAnyRole(array $roles): void
{
    requireAuth();
    $user = currentUser();

    if (!$user || !in_array($user['role'], $roles, true)) {
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
