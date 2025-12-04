<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/db/config.php';

$message = '';
$error = '';
$editing_teacher = null;
$school_id = $_SESSION['school_id'];

$pdo = db();

// Handle Delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try {
        $delete_id = $_POST['delete_id'];
        // Also delete the associated user account
        $stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ? AND school_id = ?");
        $stmt->execute([$delete_id, $school_id]);
        $user_id = $stmt->fetchColumn();

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM teachers WHERE id = ?");
        $stmt->execute([$delete_id]);
        if ($user_id) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
        }
        $pdo->commit();
        $message = "Teacher deleted successfully.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == '23000') { // Integrity constraint violation
            $error = "Cannot delete this teacher because they are assigned to workloads or schedules. Please remove those associations before deleting.";
        } else {
            $error = "Error deleting teacher: " . $e->getMessage();
        }
    }
}

// Handle POST request to add or update a teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacher_name'])) {
    $teacherName = trim($_POST['teacher_name']);
    $teacherEmail = trim($_POST['teacher_email']);
    $password = $_POST['password'] ?? null;
    $teacher_id = $_POST['teacher_id'] ?? null;

    if (empty($teacherName) || empty($teacherEmail)) {
        $error = 'Teacher name and email are required.';
    } elseif (!filter_var($teacherEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (!$teacher_id && empty($password)) {
        $error = 'Password is required for new teachers.';
    } else {
        try {
            $pdo->beginTransaction();

            // Check for duplicate teacher name
            $stmt = $pdo->prepare("SELECT id FROM teachers WHERE name = ? AND school_id = ? AND id != ?");
            $stmt->execute([$teacherName, $school_id, $teacher_id ?? 0]);
            if ($stmt->fetch()) {
                throw new Exception("A teacher with this name already exists.");
            }

            if ($teacher_id) {
                // Update existing teacher
                $stmt = $pdo->prepare("UPDATE teachers SET name = ? WHERE id = ? AND school_id = ?");
                $stmt->execute([$teacherName, $teacher_id, $school_id]);

                // Also update user email
                $stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
                $stmt->execute([$teacher_id]);
                $user_id = $stmt->fetchColumn();

                if ($user_id) {
                    $sql = "UPDATE users SET email = ?, username = ?";
                    $params = [$teacherEmail, $teacherEmail];
                    if (!empty($password)) {
                        $sql .= ", password = ?";
                        $params[] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $sql .= " WHERE id = ?";
                    $params[] = $user_id;

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }

                $message = "Teacher updated successfully!";
            } else {
                // Check for duplicate email in users table
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$teacherEmail]);
                if ($stmt->fetch()) {
                    throw new Exception("A user with this email already exists.");
                }

                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (school_id, username, email, password, role) VALUES (?, ?, ?, ?, 'teacher')");
                $stmt->execute([$school_id, $teacherEmail, $teacherEmail, $hashed_password]);
                $user_id = $pdo->lastInsertId();

                // Insert new teacher
                $stmt = $pdo->prepare("INSERT INTO teachers (name, school_id, user_id) VALUES (?, ?, ?)");
                $stmt->execute([$teacherName, $school_id, $user_id]);
                $message = "Teacher created successfully!";
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

// Handle Edit request
if (isset($_GET['edit_id'])) {
    try {
        $edit_id = $_GET['edit_id'];
        $stmt = $pdo->prepare("SELECT t.*, u.email FROM teachers t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ? AND t.school_id = ?");
        $stmt->execute([$edit_id, $school_id]);
        $editing_teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching teacher: " . $e->getMessage();
    }
}

// Fetch all teachers to display
$teachers = [];
try {
    $teachers_stmt = $pdo->prepare("SELECT t.*, u.email FROM teachers t LEFT JOIN users u ON t.user_id = u.id WHERE t.school_id = ? ORDER BY t.name ASC");
    $teachers_stmt->execute([$school_id]);
    $teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Check for teachers with no user account linked
$unlinked_teachers = [];
foreach ($teachers as $teacher) {
    if (empty($teacher['user_id'])) {
        $unlinked_teachers[] = $teacher['name'];
    }
}
if (!empty($unlinked_teachers)) {
    $unlinked_list = '<ul>';
    foreach ($unlinked_teachers as $name) {
        $unlinked_list .= '<li>' . htmlspecialchars($name) . '</li>';
    }
    $unlinked_list .= '</ul>';
    $error .= '<div class="alert alert-warning mt-3"><strong>Data Inconsistency Found:</strong> The following teachers are not linked to a user account and will not be able to log in or see their timetables: ' . $unlinked_list . ' To fix this, please delete these teachers and create them again. This will create a linked user account for them.</div>';
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
                                <li><a class="dropdown-item" href="/admin_elective_groups.php">Elective Groups</a></li>
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

                <!-- Create/Edit Teacher Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $editing_teacher ? 'Edit Teacher' : 'Create a New Teacher'; ?></h5>
                        <form action="admin_teachers.php" method="POST">
                            <input type="hidden" name="teacher_id" value="<?php echo $editing_teacher['id'] ?? ''; ?>">
                            <div class="mb-3">
                                <label for="teacher_name" class="form-label">Teacher Name</label>
                                <input type="text" class="form-control" id="teacher_name" name="teacher_name" value="<?php echo htmlspecialchars($editing_teacher['name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="teacher_email" class="form-label">Teacher Email</label>
                                <input type="email" class="form-control" id="teacher_email" name="teacher_email" value="<?php echo htmlspecialchars($editing_teacher['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" <?php echo $editing_teacher ? '' : 'required'; ?>>
                                <?php if ($editing_teacher): ?>
                                    <small class="form-text text-muted">Leave blank to keep the current password.</small>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="btn btn-primary"><?php echo $editing_teacher ? 'Update Teacher' : 'Create Teacher'; ?></button>
                            <?php if ($editing_teacher): ?>
                                <a href="admin_teachers.php" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Existing Teachers List -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Existing Teachers</h5>
                        <?php if (empty($teachers)): ?>
                            <p class="text-muted">No teachers have been created yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($teacher['name']); ?>
                                                    <?php if (empty($teacher['user_id'])): ?>
                                                        <span class="badge bg-danger" title="This teacher is not linked to a user account.">!</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                                <td>
                                                    <a href="?edit_id=<?php echo $teacher['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                    <form action="admin_teachers.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this teacher?');">
                                                        <input type="hidden" name="delete_id" value="<?php echo $teacher['id']; ?>">
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
