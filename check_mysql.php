<?php
// Check if MySQL extension is loaded
if (extension_loaded('pdo_mysql')) {
    echo "PDO MySQL extension is loaded.\n";
} else {
    echo "PDO MySQL extension is NOT loaded. This is required for Laravel to connect to MySQL.\n";
}

// Check MySQL connection
try {
    // Get database configuration from .env file
    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    $dbPort = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_DATABASE') ?: 'srahmel_d3_brainswarming';
    $dbUser = getenv('DB_USERNAME') ?: 'root';
    $dbPass = getenv('DB_PASSWORD') ?: '3ncuriolinux4dmin!';

    // Try to connect
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Successfully connected to MySQL database.\n";

    // Check if migrations table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'migrations'");
    if ($stmt->rowCount() > 0) {
        echo "Migrations table exists.\n";
    } else {
        echo "Migrations table does not exist. You may need to run migrations.\n";
    }

} catch (PDOException $e) {
    echo "Failed to connect to MySQL: " . $e->getMessage() . "\n";
}

// Check available PHP extensions
echo "\nLoaded PHP extensions:\n";
$extensions = get_loaded_extensions();
sort($extensions);
foreach ($extensions as $extension) {
    echo "- $extension\n";
}
