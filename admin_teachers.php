<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/db/config.php';

$message = '';
$error = '';
$school_id = $_SESSION['school_id'];

// Handle POST request to add a new teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacherName = trim($_POST['teacher_name'] ?? '');
    $teacherEmail = trim($_POST['teacher_email'] ?? '');
    $can_edit_workload = isset($_POST['can_edit_workload']) ? 1 : 0;

    if (empty($teacherName) || empty($teacherEmail)) {
        $error = 'Teacher name and email cannot be empty.';
    } elseif (!filter_var($teacherEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            // Check if user with this email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$teacherEmail]);
            if ($stmt->fetch()) {
                $error = 'A user with this email already exists.';
                $pdo->rollBack();
            } else {
                // Generate a random password
                $password = bin2hex(random_bytes(8));
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Create a user account for the teacher
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, school_id, role) VALUES (?, ?, ?, ?, 'teacher')");
                $stmt->execute([$teacherEmail, $hashed_password, $teacherEmail, $school_id]);
                $user_id = $pdo->lastInsertId();

                // Create the teacher
                $stmt = $pdo->prepare("INSERT INTO teachers (name, user_id, school_id, can_edit_workload) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$teacherName, $user_id, $school_id, $can_edit_workload])) {
                    $pdo->commit();
                    $message = 'Teacher "' . htmlspecialchars($teacherName) . '" created successfully! Their password is: ' . $password;
                } else {
                    $error = 'Failed to create teacher.';
                    $pdo->rollBack();
                }
            }
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Duplicate entry
                $error = 'Error: A teacher with this email already exists.';
            } else {
                $error = 'Database error: ' . $e->getMessage();
            }
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
}

// Fetch all teachers to display
$teachers = [];
try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT t.id, t.name, u.email, t.can_edit_workload, t.created_at FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.school_id = ? ORDER BY t.created_at DESC");
    $stmt->execute([$school_id]);
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
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="can_edit_workload" name="can_edit_workload" value="1">
                                <label class="form-check-label" for="can_edit_workload">Can edit their own workload</label>
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
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Can Edit Workload</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                                <td><?php echo $teacher['can_edit_workload'] ? 'Yes' : 'No'; ?></td>
                                                <td><?php echo date("M j, Y", strtotime($teacher['created_at'])); ?></td>
                                                <td><a href="admin_edit_teacher.php?id=<?php echo $teacher['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
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