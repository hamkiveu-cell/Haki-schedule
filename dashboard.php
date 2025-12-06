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
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

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