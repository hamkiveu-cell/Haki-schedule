<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/db/config.php';

$message = '';
$error = '';
$editing_group = null;
$school_id = $_SESSION['school_id'];

$pdo = db();

// Handle Delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try {
        $delete_id = $_POST['delete_id'];
        $stmt = $pdo->prepare("DELETE FROM elective_groups WHERE id = ? AND school_id = ?");
        $stmt->execute([$delete_id, $school_id]);
        $message = "Elective group deleted successfully.";
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') { // Integrity constraint violation
            $error = "Cannot delete this elective group because it has subjects associated with it. Please remove those associations before deleting.";
        } else {
            $error = "Error deleting group: " . $e->getMessage();
        }
    }
}

// Handle POST request to add or update a group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_name'])) {
    $groupName = trim($_POST['group_name']);
    $group_id = $_POST['group_id'] ?? null;

    if (empty($groupName)) {
        $error = 'Group name cannot be empty.';
    } else {
        // Check for duplicates before inserting
        $stmt = $pdo->prepare("SELECT id FROM elective_groups WHERE name = ? AND school_id = ? AND id != ?");
        $stmt->execute([$groupName, $school_id, $group_id ?? 0]);
        if ($stmt->fetch()) {
            $error = "Error: Elective group '" . htmlspecialchars($groupName) . "' already exists.";
        } else {
            try {
                if ($group_id) {
                    // Update existing group
                    $stmt = $pdo->prepare("UPDATE elective_groups SET name = ? WHERE id = ? AND school_id = ?");
                    $stmt->execute([$groupName, $group_id, $school_id]);
                    $message = "Elective group updated successfully!";
                } else {
                    // Insert new group
                    $stmt = $pdo->prepare("INSERT INTO elective_groups (name, school_id) VALUES (?, ?)");
                    $stmt->execute([$groupName, $school_id]);
                    $message = "Elective group created successfully!";
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Handle Edit request
if (isset($_GET['edit_id'])) {
    try {
        $edit_id = $_GET['edit_id'];
        $stmt = $pdo->prepare("SELECT * FROM elective_groups WHERE id = ? AND school_id = ?");
        $stmt->execute([$edit_id, $school_id]);
        $editing_group = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching group: " . $e->getMessage();
    }
}

// Fetch all groups to display
$groups = [];
try {
    $groups_stmt = $pdo->prepare("SELECT * FROM elective_groups WHERE school_id = ? ORDER BY name ASC");
    $groups_stmt->execute([$school_id]);
    $groups = $groups_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Manage Elective Groups - Haki Schedule</title>
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
                                <li><a class="dropdown-item active" href="/admin_elective_groups.php">Elective Groups</a></li>
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
                <h1 class="h2 fw-bold mb-4">Manage Elective Groups</h1>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Create/Edit Group Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $editing_group ? 'Edit Group' : 'Create a New Group'; ?></h5>
                        <form action="admin_elective_groups.php" method="POST">
                            <input type="hidden" name="group_id" value="<?php echo $editing_group['id'] ?? ''; ?>">
                            <div class="mb-3">
                                <label for="group_name" class="form-label">Group Name</label>
                                <input type="text" class="form-control" id="group_name" name="group_name" value="<?php echo htmlspecialchars($editing_group['name'] ?? ''); ?>" placeholder="e.g., Humanities" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><?php echo $editing_group ? 'Update Group' : 'Create Group'; ?></button>
                            <?php if ($editing_group): ?>
                                <a href="admin_elective_groups.php" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Existing Groups List -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Existing Elective Groups</h5>
                        <?php if (empty($groups)): ?>
                            <p class="text-muted">No elective groups have been created yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($groups as $group): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($group['name']); ?></td>
                                                <td>
                                                    <a href="?edit_id=<?php echo $group['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                    <form action="admin_elective_groups.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this group?');">
                                                        <input type="hidden" name="delete_id" value="<?php echo $group['id']; ?>">
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