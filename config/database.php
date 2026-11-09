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
    $credentials = [];
    $credentialsFile = __DIR__ . '/database.credentials.php';
    if (is_file($credentialsFile)) {
        try {
            $loaded = require $credentialsFile;
            if (is_array($loaded)) {
                $credentials = $loaded;
            }
        } catch (Throwable $ignored) {
            // Continue without exposing config details.
        }
    }

    $redactions = array_filter([
        (string) ($credentials['host'] ?? ''),
        (string) ($credentials['username'] ?? ''),
        (string) ($credentials['password'] ?? ''),
        (string) ($credentials['database'] ?? ''),
        (string) (getenv('ECOLOOP_DB_HOST') ?: ''),
        (string) (getenv('ECOLOOP_DB_USER') ?: ''),
        (string) (getenv('ECOLOOP_DB_PASSWORD') ?: ''),
        (string) (getenv('ECOLOOP_DB_NAME') ?: ''),
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

    // Prefer a local .env file for real credentials. Keep the protected
    // credentials file as a fallback only.
    $projectRoot = dirname(__DIR__);
    $envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';

    if (is_file($envPath)) {
        foreach ((array) @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $trimmed, 2), 2, '');
            $key = trim((string) $key);
            $value = trim((string) $value);

            if ($key === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    $credentialsFile = __DIR__ . '/database.credentials.php';
    $credentials = [];

    if (is_file($credentialsFile)) {
        ob_start();
        try {
            $loaded = require $credentialsFile;
        } catch (Throwable $configurationException) {
            ob_end_clean();
            logEcoLoopException('EcoLoop database configuration error', $configurationException);
            throw new RuntimeException(
                'EcoLoop is temporarily unable to reach its database. Please try again later.',
                0,
                $configurationException
            );
        }

        $bufferedOutput = ob_get_clean();
        if ($bufferedOutput !== '') {
            $configurationError = new RuntimeException('Database credentials file must not emit output.');
            logEcoLoopException('EcoLoop database configuration error', $configurationError);
            throw new RuntimeException(
                'EcoLoop is temporarily unable to reach its database. Please try again later.',
                0,
                $configurationError
            );
        }

        $credentials = is_array($loaded) ? $loaded : [];
    }

    $host = getenv('ECOLOOP_DB_HOST') ?: ($credentials['host'] ?? 'localhost');
    $username = getenv('ECOLOOP_DB_USER') ?: ($credentials['username'] ?? 'root');
    $password = getenv('ECOLOOP_DB_PASSWORD') ?: ($credentials['password'] ?? '');
    $database = getenv('ECOLOOP_DB_NAME') ?: ($credentials['database'] ?? 'eco_loop_db');

    if ($database === '') {
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
}

function ensurePartnerLocationsTable(): void
{
    db()->query(
        'CREATE TABLE IF NOT EXISTS partner_locations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            address VARCHAR(255) NOT NULL,
            latitude DECIMAL(10,7) NOT NULL,
            longitude DECIMAL(11,7) NOT NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_partner_locations_name (name),
            KEY idx_partner_locations_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}