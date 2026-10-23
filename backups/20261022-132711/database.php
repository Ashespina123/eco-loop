<?php

define('APP_ROOT', '/eco-loop');

function db(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'eco_loop_db';

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $connection = new mysqli($host, $username, $password, $database);
        $connection->set_charset('utf8mb4');
        return $connection;
    } catch (mysqli_sql_exception $e) {
        $message = 'Database connection failed. Please import the database schema and ensure MySQL is running in XAMPP.';
        throw new RuntimeException($message, 0, $e);
    }
}

function db_configured(): bool
{
    try {
        db()->ping();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
