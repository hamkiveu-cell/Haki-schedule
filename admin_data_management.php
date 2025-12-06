<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'db/config.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: /");
    exit;
}

$school_id = $_SESSION['school_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($school_id)) {
    $pdo = db();
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
        $pdo->beginTransaction();

        if (isset($_POST['clear_timetable'])) {
            // Clearing timetable data only
            $pdo->prepare("DELETE FROM schedule_teachers WHERE schedule_id IN (SELECT id FROM schedules WHERE school_id = ?)")->execute([$school_id]);
            $pdo->prepare("DELETE FROM schedules WHERE school_id = ?")->execute([$school_id]);
            $message = "Timetable data has been successfully cleared.";

        } elseif (isset($_POST['clear_all_data'])) {
            // Deleting all school data.

            // 1. Junction tables
            $pdo->prepare("DELETE FROM schedule_teachers WHERE schedule_id IN (SELECT id FROM schedules WHERE school_id = ?)")->execute([$school_id]);
            $pdo->prepare("DELETE FROM elective_group_subjects WHERE elective_group_id IN (SELECT id FROM elective_groups WHERE school_id = ?)")->execute([$school_id]);

            // 2. Core data tables
            $pdo->prepare("DELETE FROM schedules WHERE school_id = ?")->execute([$school_id]);
            $pdo->prepare("DELETE FROM workloads WHERE school_id = ?")->execute([$school_id]);
            $pdo->prepare("DELETE FROM subjects WHERE school_id = ?")->execute([$school_id]);
            $pdo->prepare("DELETE FROM teachers WHERE school_id = ?")->execute([$school_id]);
            $pdo->prepare("DELETE FROM classes WHERE school_id = ?")->execute([$school_id]);
            $pdo->prepare("DELETE FROM elective_groups WHERE school_id = ?")->execute([$school_id]);

            // 3. Users (keep the logged-in admin)
            $current_user_id = $_SESSION['user_id'] ?? 0;
            $stmt = $pdo->prepare("DELETE FROM users WHERE school_id = ? AND id != ?");
            $stmt->execute([$school_id, $current_user_id]);

            $message = "All school data has been successfully cleared.";
        }

        $pdo->commit();
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Always re-enable foreign key checks, even on error
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
        $error = "An error occurred: " . $e->getMessage();
    }
}

$page_title = "Data Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Haki Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container mt-4">
        <h1 class="mb-4"><?= htmlspecialchars($page_title) ?></h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card border-warning mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Clear Timetable Data</h5>
            </div>
            <div class="card-body">
                <p class="card-text">This action will delete all generated timetable schedules. This is useful if you want to regenerate the timetable after making changes to classes, subjects, or workloads. This will allow you to delete classes and subjects if they are currently locked by the timetable.</p>
                <p class="text-danger fw-bold">This action cannot be undone.</p>
                <form method="POST" onsubmit="return confirm('Are you sure you want to clear all timetable data?');">
                    <button type="submit" name="clear_timetable" class="btn btn-warning">Clear Timetable Data</button>
                </form>
            </div>
        </div>

        <div class="card border-danger mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Clear All School Data</h5>
            </div>
            <div class="card-body">
                <p class="card-text">This action will permanently delete ALL data associated with your school, including classes, subjects, teachers, workloads, and the timetable. This is for starting completely fresh.</p>
                <p class="text-danger fw-bold">This is a destructive action and cannot be undone.</p>
                <form method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete all of your school's data? This cannot be reversed.');">
                    <button type="submit" name="clear_all_data" class="btn btn-danger">Delete All School Data</button>
                </form>
            </div>
        </div>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>