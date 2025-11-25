<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/db/config.php';

$message = '';
$error = '';

// Handle POST request to add a new teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacherName = trim($_POST['teacher_name'] ?? '');
    $teacherEmail = trim($_POST['teacher_email'] ?? '');

    if (empty($teacherName) || empty($teacherEmail)) {
        $error = 'Teacher name and email cannot be empty.';
    } elseif (!filter_var($teacherEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("INSERT INTO teachers (name, email) VALUES (?, ?)");
            if ($stmt->execute([$teacherName, $teacherEmail])) {
                $message = 'Teacher "' . htmlspecialchars($teacherName) . '" created successfully!';
            } else {
                $error = 'Failed to create teacher.';
            }
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Duplicate entry
                $error = 'Error: A teacher with the email "' . htmlspecialchars($teacherEmail) . '" already exists.';
            } else {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all teachers to display
$teachers = [];
try {
    $pdo = db();
    $stmt = $pdo->query("SELECT id, name, email, created_at FROM teachers ORDER BY created_at DESC");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Manage Teachers - Haki Schedule</title>
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
                                <li><a class="dropdown-item" href="/admin_subjects.php">Subjects</a></li>
                                <li><a class="dropdown-item active" href="/admin_teachers.php">Teachers</a></li>
                                <li><a class="dropdown-item" href="/admin_workloads.php">Workloads</a></li>
                            </ul>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="/timetable.php">Timetable</a></li>
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
                <h1 class="h2 fw-bold mb-4">Manage Teachers</h1>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Create Teacher Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Create a New Teacher</h5>
                        <form action="admin_teachers.php" method="POST">
                            <div class="mb-3">
                                <label for="teacher_name" class="form-label">Teacher Name</label>
                                <input type="text" class="form-control" id="teacher_name" name="teacher_name" placeholder="e.g., John Doe" required>
                            </div>
                            <div class="mb-3">
                                <label for="teacher_email" class="form-label">Teacher Email</label>
                                <input type="email" class="form-control" id="teacher_email" name="teacher_email" placeholder="e.g., john.doe@example.com" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Teacher</button>
                        </form>
                    </div>
                </div>

                <!-- Existing Teachers List -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Existing Teachers</h5>
                        <?php if (empty($teachers) && !$error): ?>
                            <p class="text-muted">No teachers have been created yet. Use the form above to add the first one.</p>
                        <?php else:
                            if(!empty($teachers)) : ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($teachers as $teacher): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($teacher['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($teacher['email']); ?></small>
                                        </div>
                                        <small class="text-muted">Created: <?php echo date("M j, Y", strtotime($teacher['created_at'])); ?></small>
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
