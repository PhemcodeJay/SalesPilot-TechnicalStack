<?php
// Database Configuration
$hostname = "localhost";
$username = "root";
$password = "1234";
$database = "dbs13455438";

// Data Source Name (DSN) for PDO
$dsn = "mysql:host=$hostname;dbname=$database;charset=utf8";

// PDO Options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Establish Database Connection
    $connection = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Handle Connection Error
    exit("Database connection failed: " . $e->getMessage());
}

// Tenant Database Configuration (as an array)
$tenantConfig = [
    'driver'    => 'mysql',
    'host'      => getenv('TENANT_DB_HOST') ?: '127.0.0.1',
    'database'  => null, // Default to null; dynamic tenant database setup
    'username'  => getenv('TENANT_DB_USERNAME') ?: 'root',
    'password'  => getenv('TENANT_DB_PASSWORD') ?: '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
    'strict'    => true,
];

// Example Usage of Tenant Configuration
function getTenantConnection($tenantDatabase)
{
    global $tenantConfig;

    $tenantConfig['database'] = $tenantDatabase; // Dynamically assign the tenant database name
    $dsn = "mysql:host={$tenantConfig['host']};dbname={$tenantDatabase};charset={$tenantConfig['charset']}";

    try {
        return new PDO($dsn, $tenantConfig['username'], $tenantConfig['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        exit("Tenant database connection failed: " . $e->getMessage());
    }
}
?>
