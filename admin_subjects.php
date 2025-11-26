<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/db/config.php';

$message = '';
$error = '';
$editing_subject = null;

$pdo = db();

// Handle Delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try {
        $delete_id = $_POST['delete_id'];
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$delete_id]);
        $message = "Subject deleted successfully.";
    } catch (PDOException $e) {
        $error = "Error deleting subject: " . $e->getMessage();
    }
}

// Handle POST request to add or update a subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject_name'])) {
    $subjectName = trim($_POST['subject_name']);
    $has_double_lesson = isset($_POST['has_double_lesson']) ? 1 : 0;
    $elective_group = !empty($_POST['elective_group']) ? trim($_POST['elective_group']) : null;
    $subject_id = $_POST['subject_id'] ?? null;

    if (empty($subjectName)) {
        $error = 'Subject name cannot be empty.';
    } else {
        try {
            if ($subject_id) {
                // Update existing subject
                $stmt = $pdo->prepare("UPDATE subjects SET name = ?, has_double_lesson = ?, elective_group = ? WHERE id = ?");
                $stmt->execute([$subjectName, $has_double_lesson, $elective_group, $subject_id]);
                $message = "Subject updated successfully!";
            } else {
                // Insert new subject
                $stmt = $pdo->prepare("INSERT INTO subjects (name, has_double_lesson, elective_group) VALUES (?, ?, ?)");
                $stmt->execute([$subjectName, $has_double_lesson, $elective_group]);
                $message = "Subject created successfully!";
            }
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Duplicate entry
                $error = "Error: Subject '" . htmlspecialchars($subjectName) . "' already exists.";
            } else {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Handle Edit request
if (isset($_GET['edit_id'])) {
    try {
        $edit_id = $_GET['edit_id'];
        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
        $stmt->execute([$edit_id]);
        $editing_subject = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching subject: " . $e->getMessage();
    }
}

// Fetch all subjects to display
$subjects = [];
try {
    $subjects_stmt = $pdo->query("SELECT * FROM subjects ORDER BY created_at DESC");
    $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Manage Subjects - Haki Schedule</title>
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
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle active" href="#" id="manageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Manage
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="manageDropdown">
                                <li><a class="dropdown-item" href="/admin_classes.php">Classes</a></li>
                                <li><a class="dropdown-item active" href="/admin_subjects.php">Subjects</a></li>
                                <li><a class="dropdown-item" href="/admin_teachers.php">Teachers</a></li>
                                <li><a class="dropdown-item" href="/admin_workloads.php">Workloads</a></li>
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
                <h1 class="h2 fw-bold mb-4">Manage Subjects</h1>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Create/Edit Subject Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $editing_subject ? 'Edit Subject' : 'Create a New Subject'; ?></h5>
                        <form action="admin_subjects.php" method="POST">
                            <input type="hidden" name="subject_id" value="<?php echo $editing_subject['id'] ?? ''; ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="subject_name" class="form-label">Subject Name</label>
                                    <input type="text" class="form-control" id="subject_name" name="subject_name" value="<?php echo htmlspecialchars($editing_subject['name'] ?? ''); ?>" placeholder="e.g., Mathematics" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="elective_group" class="form-label">Elective Group (Optional)</label>
                                    <input type="text" class="form-control" id="elective_group" name="elective_group" value="<?php echo htmlspecialchars($editing_subject['elective_group'] ?? ''); ?>" placeholder="e.g., Languages">
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="has_double_lesson" name="has_double_lesson" value="1" <?php echo !empty($editing_subject['has_double_lesson']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="has_double_lesson">Has one double lesson per week</label>
                            </div>
                            <button type="submit" class="btn btn-primary"><?php echo $editing_subject ? 'Update Subject' : 'Create Subject'; ?></button>
                            <?php if ($editing_subject): ?>
                                <a href="admin_subjects.php" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Existing Subjects List -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Existing Subjects</h5>
                        <?php if (empty($subjects)): ?>
                            <p class="text-muted">No subjects have been created yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Double Lesson</th>
                                            <th>Elective Group</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subjects as $subject): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                                <td><?php echo $subject['has_double_lesson'] ? 'Yes' : 'No'; ?></td>
                                                <td><?php echo htmlspecialchars($subject['elective_group'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <a href="?edit_id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                    <form action="admin_subjects.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this subject?');">
                                                        <input type="hidden" name="delete_id" value="<?php echo $subject['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
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
