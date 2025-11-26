<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/db/config.php';

$message = '';
$error = '';
$teacher = null;
$school_id = $_SESSION['school_id'];

if (!isset($_GET['id'])) {
    header("Location: admin_teachers.php");
    exit;
}

$teacher_id = $_GET['id'];
$pdo = db();

// Handle POST request to update teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $can_edit_workload = isset($_POST['can_edit_workload']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("UPDATE teachers SET can_edit_workload = ? WHERE id = ? AND school_id = ?");
        if ($stmt->execute([$can_edit_workload, $teacher_id, $school_id])) {
            $message = 'Teacher updated successfully!';
        } else {
            $error = 'Failed to update teacher.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Fetch teacher data
try {
    $stmt = $pdo->prepare("SELECT t.id, t.name, u.email, t.can_edit_workload FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ? AND t.school_id = ?");
    $stmt->execute([$teacher_id, $school_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        header("Location: admin_teachers.php?error=not_found");
        exit;
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Edit Teacher - Haki Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo time(); ?>">
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">Haki Schedule</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/">Home</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="manageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Manage
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="manageDropdown">
                            <li><a class="dropdown-item" href="/admin_classes.php">Classes</a></li>
                            <li><a class="dropdown-item" href="/admin_subjects.php">Subjects</a></li>
                            <li><a class="dropdown-item active" href="/admin_teachers.php">Teachers</a></li>
                            <li><a class="dropdown-item" href="/admin_workloads.php">Workloads</a></li>
                            <li><a class="dropdown-item" href="/admin_timeslots.php">Timeslots</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="/timetable.php">Class Timetable</a></li>
                    <li class="nav-item"><a class="nav-link" href="/teacher_timetable.php">Teacher Timetable</a></li>
                    <li class="nav-item"><a class="nav-link" href="/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h1 class="h2 fw-bold mb-4">Edit Teacher</h1>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form action="admin_edit_teacher.php?id=<?php echo $teacher['id']; ?>" method="POST">
                            <div class="mb-3">
                                <label for="teacher_name" class="form-label">Teacher Name</label>
                                <input type="text" class="form-control" id="teacher_name" name="teacher_name" value="<?php echo htmlspecialchars($teacher['name']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="teacher_email" class="form-label">Teacher Email</label>
                                <input type="email" class="form-control" id="teacher_email" name="teacher_email" value="<?php echo htmlspecialchars($teacher['email']); ?>" readonly>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="can_edit_workload" name="can_edit_workload" value="1" <?php echo $teacher['can_edit_workload'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="can_edit_workload">Can edit their own workload</label>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Teacher</button>
                            <a href="admin_teachers.php" class="btn btn-secondary">Back to Teachers</a>
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
