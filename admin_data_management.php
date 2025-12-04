<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'db/config.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$school_id = $_SESSION['school_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    
    if (isset($_POST['clear_timetable'])) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("DELETE FROM schedule_teachers WHERE schedule_id IN (SELECT id FROM schedules WHERE school_id = ?)");
            $stmt->execute([$school_id]);
            
            $stmt = $pdo->prepare("DELETE FROM schedules WHERE school_id = ?");
            $stmt->execute([$school_id]);
            
            $pdo->commit();
            $message = "Timetable data has been successfully cleared.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "An error occurred while clearing timetable data: " . $e->getMessage();
        }
    }

    if (isset($_POST['clear_all_data'])) {
        if (isset($_POST['confirm_delete'])) {
            try {
                $pdo->beginTransaction();

                // Order of deletion is important to avoid foreign key constraint errors
                $tables_to_clear = [
                    'schedule_teachers', 
                    'schedules', 
                    'workloads', 
                    'elective_group_subjects',
                    'teachers', // Teachers might be referenced by workloads
                    'subjects', // Subjects are referenced by many tables
                    'elective_groups',
                    'classes'
                ];

                // First, delete from linking tables based on schedule_id
                $stmt = $pdo->prepare("DELETE FROM schedule_teachers WHERE schedule_id IN (SELECT id FROM schedules WHERE school_id = ?)");
                $stmt->execute([$school_id]);

                // Now delete from the main tables with a school_id
                $stmt = $pdo->prepare("DELETE FROM schedules WHERE school_id = ?");
                $stmt->execute([$school_id]);
                $stmt = $pdo->prepare("DELETE FROM workloads WHERE school_id = ?");
                $stmt->execute([$school_id]);
                
                // elective_group_subjects links subjects and elective_groups
                $stmt = $pdo->prepare("DELETE egs FROM elective_group_subjects egs JOIN subjects s ON egs.subject_id = s.id WHERE s.school_id = ?");
                $stmt->execute([$school_id]);

                $stmt = $pdo->prepare("DELETE FROM teachers WHERE school_id = ?");
                $stmt->execute([$school_id]);
                $stmt = $pdo->prepare("DELETE FROM subjects WHERE school_id = ?");
                $stmt->execute([$school_id]);
                $stmt = $pdo->prepare("DELETE FROM elective_groups WHERE class_id IN (SELECT id FROM classes WHERE school_id = ?)");
                $stmt->execute([$school_id]);
                $stmt = $pdo->prepare("DELETE FROM classes WHERE school_id = ?");
                $stmt->execute([$school_id]);

                $pdo->commit();
                $message = "All school data has been successfully cleared.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "An error occurred while clearing all data: " . $e->getMessage();
            }
        } else {
            $error = "Please check the confirmation box to proceed with deleting all data.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-5">
        <h1 class="mb-4">Data Management</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card border-warning mb-4">
            <div class="card-header">
                <h5 class="mb-0">Clear Timetable Data</h5>
            </div>
            <div class="card-body">
                <p class="card-text">This action will delete all generated timetable entries (`schedules` and `schedule_teachers`) for your school. This is useful if you want to regenerate the timetable from scratch or if you are unable to delete other items like classes or subjects due to database constraints.</p>
                <p class="card-text">This will not delete your classes, subjects, teachers, or workloads.</p>
                <form method="POST" onsubmit="return confirm('Are you sure you want to clear all timetable data? This action cannot be undone.');">
                    <button type="submit" name="clear_timetable" class="btn btn-warning">Clear Timetable Data</button>
                </form>
            </div>
        </div>

        <div class="card border-danger">
            <div class="card-header">
                <h5 class="mb-0">Clear All School Data</h5>
            </div>
            <div class="card-body">
                <p class="card-text text-danger fw-bold"><strong><i class="bi bi-exclamation-triangle-fill"></i> WARNING: This is a destructive action.</strong></p>
                <p class="card-text">This action will permanently delete all data associated with your school, including:</p>
                <ul>
                    <li>All Timetable Data</li>
                    <li>All Workloads</li>
                    <li>All Teachers</li>
                    <li>All Subjects</li>
                    <li>All Classes</li>
                    <li>All Elective Groups</li>
                </ul>
                <p class="card-text">Your user account and school registration will not be affected. Use this option only if you want to start over completely.</p>
                <form method="POST" onsubmit="return document.getElementById('confirm_delete_checkbox').checked;">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="" id="confirm_delete_checkbox" name="confirm_delete">
                        <label class="form-check-label" for="confirm_delete_checkbox">
                            I understand that this action is irreversible and I want to delete all my school's data.
                        </label>
                    </div>
                    <button type="submit" name="clear_all_data" class="btn btn-danger">Delete All School Data</button>
                </form>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
