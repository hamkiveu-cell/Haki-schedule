<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db/config.php';

$role = $_SESSION['role'] ?? 'teacher';
$user_id = $_SESSION['user_id'];
$can_edit_workload = false;

if ($role === 'teacher') {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT can_edit_workload FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($teacher && $teacher['can_edit_workload']) {
        $can_edit_workload = true;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Haki Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo time(); ?>">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">Haki Schedule</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if ($role === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="admin_classes.php">Classes</a></li>
                        <li class="nav-item"><a class="nav-link" href="admin_subjects.php">Subjects</a></li>
                        <li class="nav-item"><a class="nav-link" href="admin_teachers.php">Teachers</a></li>
                        <li class="nav-item"><a class="nav-link" href="admin_workloads.php">Workloads</a></li>
                        <li class="nav-item"><a class="nav-link" href="admin_timeslots.php">Timeslots</a></li>
                        <li class="nav-item"><a class="nav-link" href="timetable.php">Class Timetable</a></li>
                        <li class="nav-item"><a class="nav-link" href="teacher_timetable.php">Teacher Timetable</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="teacher_timetable.php">My Timetable</a></li>
                        <?php if ($can_edit_workload): ?>
                            <li class="nav-item"><a class="nav-link" href="teacher_workload.php">My Workload</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <h1 class="h3 fw-bold">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <p>You are logged in as a <?php echo htmlspecialchars($role); ?>.</p>
        
        <?php if ($role === 'admin'): ?>
            <p>You can manage the school's data using the links in the navigation.</p>
        <?php else: ?>
            <p>You can view your timetable using the link in the navigation.</p>
            <?php if ($can_edit_workload): ?>
                <p>You can also <a href="teacher_workload.php">manage your workload</a>.</p>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="bg-dark text-white py-4 mt-5"><div class="container text-center"><p>&copy; <?php echo date("Y"); ?> Haki Schedule. All Rights Reserved.</p></div></footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>