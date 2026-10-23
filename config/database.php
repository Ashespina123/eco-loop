```php
<?php

if (!defined('APP_ROOT')) {
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $projectRoot = realpath(__DIR__ . '/..');

    $relativeRoot = ($documentRoot && $projectRoot
        && str_starts_with(strtolower($projectRoot), strtolower($documentRoot)))
        ? substr($projectRoot, strlen($documentRoot))
        : '';

    $normalizedRoot = trim(str_replace('\\', '/', $relativeRoot), '/');
    define('APP_ROOT', $normalizedRoot === '' ? '' : '/' . $normalizedRoot);
}

/**
 * Return exception details with configured secrets removed.
 */
function safeEcoLoopExceptionDetails(Throwable $exception): array
{
    $redactions = array_filter([
        'if0_42620998',
        'uloL5OpGj6 ',
        'if0_42620998_XXX',
        (string) ($_POST['password'] ?? ''),
        (string) ($_POST['confirm_password'] ?? ''),
        (string) ($_POST['diagnostic_key'] ?? ''),
        isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '',
        session_id(),
    ], static fn(string $value): bool => $value !== '');

    $details = [];

    for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
        $message = str_replace($redactions, '[redacted]', $current->getMessage());

        $message = preg_replace(
            '/\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}|\$argon2(?:id|i|d)\$[^\s]+/',
            '[redacted-password-hash]',
            $message
        ) ?? '[exception message unavailable]';

        $details[] = [
            'class' => get_class($current),
            'code' => (string) $current->getCode(),
            'message' => $message,
            'file' => $current->getFile(),
            'line' => $current->getLine(),
        ];
    }

    return $details;
}

/**
 * Log database errors without displaying sensitive details to users.
 */
function logEcoLoopException(string $context, Throwable $exception): void
{
    foreach (safeEcoLoopExceptionDetails($exception) as $detail) {
        error_log(
            $context . ': ' . $detail['class'] . ' (' . $detail['code'] . '): '
            . $detail['message'] . ' in ' . $detail['file']
            . ' on line ' . $detail['line']
        );
    }
}

/**
 * Connect to the InfinityFree database.
 */
function db(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    // Replace these placeholders with your actual InfinityFree credentials.
    $host = 'sql208.infinityfree.com';
    $username = 'if0_42620998';
    $password = 'uloL5OpGj6';
    $database = 'if0_42620998_XXX';

    if (
        $username === 'if0_42620998'
        || $password === 'uloL5OpGj6'
        || $database === 'if0_42620998_XXX'
    ) {
        throw new RuntimeException('Database credentials have not been configured.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $connection = new mysqli($host, $username, $password, $database);
        $connection->set_charset('utf8mb4');

        return $connection;
    } catch (mysqli_sql_exception $exception) {
        logEcoLoopException('EcoLoop MySQL connection failed', $exception);

        throw new RuntimeException(
            'EcoLoop is temporarily unable to reach its database. Please try again later.',
            0,
            $exception
        );
    }
}

/**
 * Check whether the database connection is working.
 */
function db_configured(): bool
{
    try {
        db()->ping();
        return true;
    } catch (Throwable $exception) {
        return false;
    }