<?php
// db_connect.php
// MySQLi connection helper for XAMPP (MySQL / MariaDB)
// Place this file in your project and include/require where needed.

declare(strict_types=1);

/**
 * Configuration - adjust these values for your environment.
 * For XAMPP default MySQL: host = '127.0.0.1' or 'localhost', user = 'root', password = ''.
 */
const DB_HOST = '127.0.0.1';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'studentport';
const DB_PORT = 3307; // default MySQL port

/**
 * Returns a connected mysqli instance.
 *
 * Usage:
 *   require_once __DIR__ . '/db_connect.php';
 *   $mysqli = db_connect();
 *   // use $mysqli->prepare(...), etc.
 *
 * The function throws a RuntimeException on failure.
 *
 * @return mysqli
 * @throws RuntimeException
 */
function db_connect(): mysqli
{
    // Enable mysqli exceptions for easier error handling
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        // Set recommended options
        $mysqli->set_charset('utf8mb4');
        // Optional: set SQL mode or session variables if needed
        // $mysqli->query("SET SESSION sql_mode='STRICT_TRANS_TABLES'");
        return $mysqli;
    } catch (mysqli_sql_exception $e) {
        // Do not expose DB details in production; log the error and throw a generic message
        error_log('Database connection error: ' . $e->getMessage());
        throw new RuntimeException('Database connection failed.');
    }
}

/**
 * Convenience function to close connection (optional).
 *
 * @param mysqli|null $mysqli
 * @return void
 */
function db_close(?mysqli $mysqli): void
{
    if ($mysqli instanceof mysqli) {
        $mysqli->close();
    }
}
