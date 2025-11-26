<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/db/config.php';

$message = '';
$error = '';
$editing_timeslot = null;

$pdo = db();

// Handle Edit request
if (isset($_GET['edit_id'])) {
    try {
        $edit_id = $_GET['edit_id'];
        $stmt = $pdo->prepare("SELECT * FROM timeslots WHERE id = ?");
        $stmt->execute([$edit_id]);
        $editing_timeslot = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching timeslot: " . $e->getMessage();
    }
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("DELETE FROM timeslots WHERE id = ?");
            if ($stmt->execute([$_POST['delete_id']])) {
                $message = 'Timeslot deleted successfully!';
            } else {
                $error = 'Failed to delete timeslot.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
    if (isset($_POST['add_timeslot'])) {
        $name = trim($_POST['name']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $is_break = isset($_POST['is_break']) ? 1 : 0;
        $timeslot_id = $_POST['timeslot_id'] ?? null;

        if (empty($name) || empty($start_time) || empty($end_time)) {
            $error = 'All fields are required.';
        } else {
            try {
                if ($timeslot_id) {
                    // Update existing timeslot
                    $stmt = $pdo->prepare("UPDATE timeslots SET name = ?, start_time = ?, end_time = ?, is_break = ? WHERE id = ?");
                    $stmt->execute([$name, $start_time, $end_time, $is_break, $timeslot_id]);
                    $message = "Timeslot updated successfully!";
                } else {
                    // Insert new timeslot
                    $stmt = $pdo->prepare("INSERT INTO timeslots (name, start_time, end_time, is_break) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $start_time, $end_time, $is_break]);
                    $message = "Timeslot created successfully!";
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all timeslots
$timeslots = [];
try {
    $pdo = db();
    $stmt = $pdo->query("SELECT * FROM timeslots ORDER BY start_time");
    $timeslots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Manage Timeslots - Haki Schedule</title>
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
                                <li><a class="dropdown-item" href="/admin_teachers.php">Teachers</a></li>
                                <li><a class="dropdown-item" href="/admin_workloads.php">Workloads</a></li>
                                <li><a class="dropdown-item active" href="/admin_timeslots.php">Timeslots</a></li>
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
                <h1 class="h2 fw-bold mb-4">Manage Timeslots</h1>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Create Timeslot Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $editing_timeslot ? 'Edit Timeslot' : 'Create a New Timeslot'; ?></h5>
                        <form action="admin_timeslots.php" method="POST">
                            <input type="hidden" name="timeslot_id" value="<?php echo $editing_timeslot['id'] ?? ''; ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Period Name</label>
                                    <input type="text" class="form-control" id="name" name="name" placeholder="e.g., Period 1, Lunch" value="<?php echo htmlspecialchars($editing_timeslot['name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="start_time" class="form-label">Start Time</label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" value="<?php echo htmlspecialchars($editing_timeslot['start_time'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="end_time" class="form-label">End Time</label>
                                    <input type="time" class_="form-control" id="end_time" name="end_time" value="<?php echo htmlspecialchars($editing_timeslot['end_time'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_break" name="is_break" value="1" <?php echo !empty($editing_timeslot['is_break']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_break">This is a break</label>
                            </div>
                            <button type="submit" name="add_timeslot" class="btn btn-primary"><?php echo $editing_timeslot ? 'Update Timeslot' : 'Create Timeslot'; ?></button>
                            <?php if ($editing_timeslot): ?>
                                <a href="admin_timeslots.php" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Existing Timeslots List -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Existing Timeslots</h5>
                        <?php if (empty($timeslots)): ?>
                            <p class="text-muted">No timeslots have been created yet.</p>
                        <?php else: ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($timeslots as $timeslot): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($timeslot['name']); ?></td>
                                            <td><?php echo date("g:i A", strtotime($timeslot['start_time'])); ?></td>
                                            <td><?php echo date("g:i A", strtotime($timeslot['end_time'])); ?></td>
                                            <td><?php echo $timeslot['is_break'] ? 'Break' : 'Lesson'; ?></td>
                                            <td>
                                                <a href="?edit_id=<?php echo $timeslot['id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                                <form action="admin_timeslots.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this timeslot?');">
                                                    <input type="hidden" name="delete_id" value="<?php echo $timeslot['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
