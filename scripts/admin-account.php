<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/database.php';

function ask(string $label): string
{
    fwrite(STDOUT, $label);
    return trim((string) fgets(STDIN));
}

$mode = strtolower($argv[1] ?? '');
if (!in_array($mode, ['create', 'add', 'reset'], true)) {
    fwrite(STDERR, "Usage: php scripts/admin-account.php create|add|reset\nSet ECOLOOP_ADMIN_PASSWORD or let create/add generate a one-time password.\n");
    exit(2);
}
$email = strtolower(ask('Admin email: '));
$password = (string) (getenv('ECOLOOP_ADMIN_PASSWORD') ?: '');
$generatedPassword = false;
if ($password === '' && in_array($mode, ['create', 'add'], true)) {
    $password = bin2hex(random_bytes(18));
    $generatedPassword = true;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Use a valid email and an ECOLOOP_ADMIN_PASSWORD of at least 12 characters, or let create/add generate one.\n");
    exit(2);
}
$connection = db();
if (in_array($mode, ['create', 'add'], true)) {
    $existingAdmins = (int) $connection->query('SELECT COUNT(*) total FROM users WHERE role="admin"')->fetch_assoc()['total'];
    if ($mode === 'create' && $existingAdmins > 0) {
        fwrite(STDERR, "An administrator already exists; no account was created. Use reset for a known administrator account.\n");
        exit(1);
    }
    $existsStmt = $connection->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
    $existsStmt->bind_param('s', $email); $existsStmt->execute(); $emailExists = (bool) $existsStmt->get_result()->fetch_assoc(); $existsStmt->close();
    if ($emailExists) { fwrite(STDERR, "That email is already in use; no account was created.\n"); exit(1); }
    $name = ask('Admin full name: ');
    $phone = ask('Admin contact number: ');
    if ($name === '' || strlen($name) > 150 || $phone === '' || strlen($phone) > 20) {
        fwrite(STDERR, "Enter a name (1-150 characters) and contact number (1-20 characters).\n");
        exit(2);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'admin'; $status = 'active';
    $connection->begin_transaction();
    try {
        $stmt = $connection->prepare('INSERT INTO users (full_name,mobile_number,email,password_hash,role,status) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('ssssss', $name, $phone, $email, $hash, $role, $status);
        $stmt->execute(); $adminId = (int) $stmt->insert_id; $stmt->close();
        $log = $connection->prepare('INSERT INTO admin_logs (admin_id,action,details) VALUES (?,"Administrator account created",?)');
        $details = 'Created through CLI setup (' . $mode . ').';
        $log->bind_param('is', $adminId, $details); $log->execute(); $log->close();
        $connection->commit();
    } catch (Throwable $exception) { $connection->rollback(); fwrite(STDERR, "Administrator account creation failed; no changes were committed.\n"); exit(1); }
    fwrite(STDOUT, "Administrator account created.\nEmail: {$email}\nPassword: {$password}\nSave this password now; it will not be shown again.\n");
    exit(0);
}
$stmt = $connection->prepare('SELECT id FROM users WHERE email=? AND role="admin" LIMIT 1');
$stmt->bind_param('s', $email); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$row) {
    fwrite(STDERR, "No administrator with that email exists; no account was changed.\n");
    exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT); $adminId = (int) $row['id'];
$connection->begin_transaction();
try {
    $update = $connection->prepare('UPDATE users SET password_hash=?,failed_login_attempts=0,locked_until=NULL WHERE id=? AND role="admin"');
    $update->bind_param('si', $hash, $adminId); $update->execute(); $update->close();
    $log = $connection->prepare('INSERT INTO admin_logs (admin_id,action,details) VALUES (?,"Administrator password rotated","Password reset through local CLI.")');
    $log->bind_param('i', $adminId); $log->execute(); $log->close();
    $connection->commit();
    fwrite(STDOUT, "Administrator password updated. Remove ECOLOOP_ADMIN_PASSWORD from the environment now.\n");
} catch (Throwable $exception) {
    $connection->rollback();
    fwrite(STDERR, "Password reset failed; no changes were committed.\n");
    exit(1);
}
