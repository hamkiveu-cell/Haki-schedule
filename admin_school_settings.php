<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/db/config.php';

if (!isset($_SESSION['school_id']) || $_SESSION['role'] !== 'admin') {
    // Redirect non-admins or users without a school_id
    header('Location: /dashboard.php');
    exit;
}

$message = '';
$error = '';
$school_id = $_SESSION['school_id'];
$pdo = db();

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        $working_days = isset($_POST['working_days']) ? implode(',', $_POST['working_days']) : '';
        
        try {
            $stmt = $pdo->prepare("UPDATE schools SET working_days = ? WHERE id = ?");
            if ($stmt->execute([$working_days, $school_id])) {
                $message = 'Settings updated successfully!';
            } else {
                $error = 'Failed to update settings.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch school settings
$school_settings = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
    $stmt->execute([$school_id]);
    $school_settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

$current_working_days = $school_settings ? explode(',', $school_settings['working_days']) : [];
$all_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: School Settings - Haki Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h1 class="h2 fw-bold mb-4">School Settings</h1>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Working Days</h5>
                        <p class="card-text">Select the days your school operates. This will affect timetable generation.</p>
                        <form action="admin_school_settings.php" method="POST">
                            <div class="mb-3">
                                <?php foreach ($all_days as $day): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="working_days[]" value="<?php echo $day; ?>" id="day_<?php echo $day; ?>" <?php echo in_array($day, $current_working_days) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="day_<?php echo $day; ?>">
                                            <?php echo $day; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p>&copy; <?php echo date("Y"); ?> Haki Schedule. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
