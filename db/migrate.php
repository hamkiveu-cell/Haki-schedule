<?php
// Simple migration runner
require_once __DIR__ . '/config.php';

try {
    $pdo = db();
    if (!$pdo) {
        throw new Exception("PDO connection is not valid. Check db/config.php");
    }

    // Create migrations table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS `migrations` (
        `migration` VARCHAR(255) NOT NULL,
        PRIMARY KEY (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Get all migration files
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

    // Get already run migrations
    $stmt = $pdo->query("SELECT `migration` FROM `migrations`");
    $runMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($files as $file) {
        $migrationName = basename($file);
        if (in_array($migrationName, $runMigrations)) {
            continue; // Skip already run migration
        }

        echo "Running migration: " . $migrationName . "\n";
        $sql = file_get_contents($file);
        $pdo->exec($sql);

        // Record the migration
        $stmt = $pdo->prepare("INSERT INTO `migrations` (`migration`) VALUES (?)");
        $stmt->execute([$migrationName]);
    }

    echo "Migrations completed successfully.\n";

} catch (PDOException $e) {
    http_response_code(500);
    die("Migration failed: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    http_response_code(500);
    die("An error occurred: " . $e->getMessage() . "\n");
}