<?php 
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/db/config.php';

$message = '';
$error = '';

// Handle POST request to add a new class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['class_name'])) {
    $className = trim($_POST['class_name']);
    if (empty($className)) {
        $error = 'Class name cannot be empty.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("INSERT INTO classes (name) VALUES (?)");
            if ($stmt->execute([$className])) {
                $message = "Class '" . htmlspecialchars($className) . "' created successfully!";
            } else {
                $error = 'Failed to create class.';
            }
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Duplicate entry
                $error = "Error: Class '" . htmlspecialchars($className) . "' already exists.";
            } else {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all classes to display
$classes = [];
try {
    $pdo = db();
    $classes_stmt = $pdo->query("SELECT id, name, created_at FROM classes ORDER BY created_at DESC");
    $classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Manage Classes - Haki Schedule</title>
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
                                <li><a class="dropdown-item active" href="/admin_classes.php">Classes</a></li>
                                <li><a class="dropdown-item" href="/admin_subjects.php">Subjects</a></li>
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
            <div class="col-lg-8">
                <h1 class="h2 fw-bold mb-4">Manage Classes</h1>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Create Class Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Create a New Class</h5>
                        <form action="admin_classes.php" method="POST">
                            <div class="mb-3">
                                <label for="class_name" class="form-label">Class Name</label>
                                <input type="text" class="form-control" id="class_name" name="class_name" placeholder="e.g., Form 1 North" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Class</button>
                        </form>
                    </div>
                </div>

                <!-- Existing Classes List -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Existing Classes</h5>
                        <?php if (empty($classes) && !$error): ?>
                            <p class="text-muted">No classes have been created yet. Use the form above to add the first one.</p>
                        <?php else:
                            if(!empty($classes)) : ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($classes as $class):
                                    // Corrected: removed unnecessary escaping around $class['name']
                                    // Corrected: removed unnecessary escaping around $class['created_at']
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($class['name']); ?>
                                        <small class="text-muted">Created: <?php echo date("M j, Y", strtotime($class['created_at'])); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; endif; ?>
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