<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/db/config.php';



$message = '';
$error = '';
$pdo = db();
$edit_workload = null;
$school_id = $_SESSION['school_id'];

// Handle Delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try {
        // First, remove teacher from schedule

        $stmt = $pdo->prepare("DELETE FROM workloads WHERE id = ? AND school_id = ?");
        if ($stmt->execute([$_POST['delete_id'], $school_id])) {
            $message = 'Workload deleted successfully!';
        } else {
            $error = 'Failed to delete workload.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
// Handle POST request to add or update a workload
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $workload_id = $_POST['workload_id'] ?? null;
    $class_id = $_POST['class_id'] ?? null;
    $subject_id = $_POST['subject_id'] ?? null;
    $teacher_id = $_POST['teacher_id'] ?? null;
    $lessons_per_week = $_POST['lessons_per_week'] ?? null;

    if (empty($class_id) || empty($subject_id) || empty($teacher_id) || empty($lessons_per_week)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($lessons_per_week, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        $error = 'Lessons per week must be a positive number.';
    } else {
        try {
            if ($workload_id) { // Update
                $stmt = $pdo->prepare("UPDATE workloads SET class_id = ?, subject_id = ?, teacher_id = ?, lessons_per_week = ? WHERE id = ? AND school_id = ?");
                if ($stmt->execute([$class_id, $subject_id, $teacher_id, $lessons_per_week, $workload_id, $school_id])) {
                    $message = 'Workload updated successfully!';
                } else {
                    $error = 'Failed to update workload.';
                }
            } else { // Insert
                $stmt = $pdo->prepare("INSERT INTO workloads (class_id, subject_id, teacher_id, lessons_per_week, school_id) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$class_id, $subject_id, $teacher_id, $lessons_per_week, $school_id])) {
                    $new_workload_id = $pdo->lastInsertId();
                    $message = 'Workload created successfully!';
                } else {
                    $error = 'Failed to create workload.';
                }
            }
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = 'Error: This workload assignment (Class, Subject, Teacher) already exists.';
            } else {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Handle Edit request (fetch workload to edit)
if (isset($_GET['edit_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM workloads WHERE id = ? AND school_id = ?");
        $stmt->execute([$_GET['edit_id'], $school_id]);
        $edit_workload = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Fetch related data for dropdowns
try {
    $classes_stmt = $pdo->prepare("SELECT id, name FROM classes WHERE school_id = ? ORDER BY name");
    $classes_stmt->execute([$school_id]);
    $classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

    $subjects_stmt = $pdo->prepare("SELECT id, name FROM subjects WHERE school_id = ? ORDER BY name");
    $subjects_stmt->execute([$school_id]);
    $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

    $teachers_stmt = $pdo->prepare("SELECT id, name FROM teachers WHERE school_id = ? ORDER BY name");
    $teachers_stmt->execute([$school_id]);
    $teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error while fetching data: ' . $e->getMessage();
}

// Fetch all workloads to display
$workloads = [];
try {
    $workloads_stmt = $pdo->prepare("
        SELECT w.id, c.name as class_name, s.name as subject_name, t.name as teacher_name, w.lessons_per_week, w.class_id, w.subject_id, w.teacher_id
        FROM workloads w
        JOIN classes c ON w.class_id = c.id
        JOIN subjects s ON w.subject_id = s.id
        JOIN teachers t ON w.teacher_id = t.id
        WHERE w.school_id = ?
        ORDER BY c.name, s.name, t.name
    ");
    $workloads_stmt->execute([$school_id]);
    $workloads = $workloads_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error while fetching workloads: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Manage Workloads - Haki Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css">
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
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle active" href="#" id="manageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Manage
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="manageDropdown">
                                <li><a class="dropdown-item" href="/admin_classes.php">Classes</a></li>
                                <li><a class="dropdown-item" href="/admin_subjects.php">Subjects</a></li>
                                <li><a class="dropdown-item" href="/admin_teachers.php">Teachers</a></li>
                                <li><a class="dropdown-item active" href="/admin_workloads.php">Workloads</a></li>
                                <li><a class="dropdown-item" href="/admin_timeslots.php">Timeslots</a></li>
                            </ul>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="/timetable.php">Class Timetable</a></li>
                        <li class="nav-item"><a class="nav-link" href="/teacher_timetable.php">Teacher Timetable</a></li>
                        <li class="nav-item"><a class="nav-link" href="/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="/demo.php">Demo</a></li>
                        <li class="nav-item"><a class="nav-link" href="/login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="/register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <h1 class="h2 fw-bold mb-4">Manage Workloads</h1>

                <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $edit_workload ? 'Edit Workload' : 'Assign a New Workload'; ?></h5>
                        <form action="admin_workloads.php" method="POST">
                            <input type="hidden" name="workload_id" value="<?php echo $edit_workload['id'] ?? ''; ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="class_id" class="form-label">Class</label>
                                    <select class="form-select" id="class_id" name="class_id" required>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo (isset($edit_workload) && $edit_workload['class_id'] == $class['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($class['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="subject_id" class="form-label">Subject</label>
                                    <select class="form-select" id="subject_id" name="subject_id" required>
                                        <?php foreach ($subjects as $subject): ?>
                                            <option value="<?php echo $subject['id']; ?>" <?php echo (isset($edit_workload) && $edit_workload['subject_id'] == $subject['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($subject['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="teacher_id" class="form-label">Teacher</label>
                                    <select class="form-select" id="teacher_id" name="teacher_id" required>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?php echo $teacher['id']; ?>" <?php echo (isset($edit_workload) && $edit_workload['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($teacher['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="lessons_per_week" class="form-label">Lessons Per Week</label>
                                    <input type="number" class="form-control" id="lessons_per_week" name="lessons_per_week" min="1" value="<?php echo $edit_workload['lessons_per_week'] ?? ''; ?>" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><?php echo $edit_workload ? 'Update Workload' : 'Create Workload'; ?></button>
                            <?php if ($edit_workload): ?>
                                <a href="admin_workloads.php" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Existing Workloads</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead><tr><th>Class</th><th>Subject</th><th>Teacher</th><th class="text-center">Lessons/Week</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($workloads as $workload): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($workload['class_name']); ?></td>
                                            <td><?php echo htmlspecialchars($workload['subject_name']); ?></td>
                                            <td><?php echo htmlspecialchars($workload['teacher_name']); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($workload['lessons_per_week']); ?></td>
                                            <td>
                                                <a href="?edit_id=<?php echo $workload['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-fill"></i></a>
                                                <form action="admin_workloads.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this workload?');">
                                                    <input type="hidden" name="delete_id" value="<?php echo $workload['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-dark text-white py-4 mt-5"><div class="container text-center"><p>&copy; <?php echo date("Y"); ?> Haki Schedule. All Rights Reserved.</p></div></footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
