<?php
require_once __DIR__ . '/../config/database.php';

$authorized = $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['diagnostic_key'])
    && is_string($_POST['diagnostic_key'])
    && strlen($_POST['diagnostic_key']) <= 128
    && hash_equals('a1fab42322586f3440622801b29f15c3c4ca3513149bc2515656f20f9f1569a3', hash('sha256', $_POST['diagnostic_key']));

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }

if (!$authorized) {
    http_response_code($_SERVER['REQUEST_METHOD'] === 'POST' ? 403 : 200);
    ?>
    <!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EcoLoop temporary database check</title>
    <body style="font:16px system-ui;max-width:560px;margin:10vh auto;padding:24px;color:#173b2b">
    <h1>Temporary database check</h1><p>Enter the temporary diagnostic key supplied by the site owner.</p>
    <form method="post"><label>Diagnostic key <input name="diagnostic_key" type="password" required autocomplete="off" style="display:block;width:100%;padding:12px;margin:8px 0"></label><button>Run read-only check</button></form>
    </body></html>
    <?php
    exit;
}

$checks = [
    'PHP version' => PHP_VERSION,
    'MySQLi extension' => extension_loaded('mysqli') ? 'available' : 'missing',
    'MySQLi client' => function_exists('mysqli_get_client_info') ? mysqli_get_client_info() : 'unavailable',
];
$ok = false;
$errorMessage = '';
$exceptionDetails = [];
try {
    $connection = db();
    $server = $connection->query('SELECT VERSION() AS version')->fetch_assoc();
    $checks['MySQL server'] = (string) ($server['version'] ?? 'connected');
    foreach (['users' => ['id','full_name','email','password_hash','role','status','failed_login_attempts','locked_until'], 'user_addresses' => ['user_id','address','latitude','longitude','is_primary']] as $table => $required) {
        $result = $connection->query('SHOW COLUMNS FROM `' . $table . '`');
        $actual = array_column($result->fetch_all(MYSQLI_ASSOC), 'Field');
        $missing = array_values(array_diff($required, $actual));
        $checks[$table . ' table'] = $missing ? 'present; missing columns: ' . implode(', ', $missing) : 'present; required columns OK';
    }
    $ok = true;
} catch (Throwable $exception) {
    logEcoLoopException('EcoLoop temporary database diagnostic', $exception);
    $errorMessage = 'Connection or schema check failed.';
    $exceptionDetails = safeEcoLoopExceptionDetails($exception);
}
?>
<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>EcoLoop database check</title><body style="font:16px system-ui;max-width:720px;margin:8vh auto;padding:24px;color:#173b2b">
<h1>EcoLoop database check</h1><p><?php echo $ok ? 'Connection and required table checks completed.' : h($errorMessage); ?></p>
<dl><?php foreach ($checks as $label => $value): ?><dt style="font-weight:700;margin-top:12px"><?php echo h($label); ?></dt><dd><?php echo h($value); ?></dd><?php endforeach; ?></dl>
<?php if ($exceptionDetails): ?><h2>Redacted diagnostic details</h2><ul><?php foreach ($exceptionDetails as $detail): ?><li><?php echo h($detail['class'] . ' (' . $detail['code'] . '): ' . $detail['message'] . ' — ' . basename($detail['file']) . ':' . $detail['line']); ?></li><?php endforeach; ?></ul><?php endif; ?>
<p>This read-only page does not display account data or change the database. Delete <code>diagnostics/db-check.php</code> after use.</p>
</body></html>
