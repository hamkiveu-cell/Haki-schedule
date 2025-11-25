<?php
// Simple migration runner
require_once __DIR__ . '/config.php';

try {
    $pdo = db();
    // Check if PDO connection is valid
    if (!$pdo) {
        throw new Exception("PDO connection is not valid. Check db/config.php");
    }

    $migrationsDir = __DIR__ . '/migrations';
    if (!is_dir($migrationsDir)) {
        echo "No migrations directory found. Nothing to do.\n";
        exit(0);
    }

    $files = glob($migrationsDir . '/*.sql');
    sort($files);

    if (empty($files)) {
        echo "No migration files found. Nothing to do.\n";
        exit(0);
    }

    foreach ($files as $file) {
        echo "Running migration: " . basename($file) . "\n";
        $sql = file_get_contents($file);
        $pdo->exec($sql);
    }

    echo "Migrations completed successfully.\n";

} catch (PDOException $e) {
    http_response_code(500);
    die("Migration failed: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    http_response_code(500);
    die("An error occurred: " . $e->getMessage() . "\n");
}

