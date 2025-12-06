<?php
error_log("Navbar role check: " . ($_SESSION['role'] ?? 'not set'));
// Note: This file assumes session_start() has been called by the including file.
$current_page = basename($_SERVER['SCRIPT_NAME']);
$role = $_SESSION['role'] ?? '';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/">Haki Schedule</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="/">Home</a></li>
                <?php if (isset($_SESSION['user_id'])) : ?>
                    <?php if ($role === 'admin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo (strpos($current_page, 'admin_') === 0) ? 'active' : ''; ?>" href="#" id="manageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Manage
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="manageDropdown">
                                <li><a class="dropdown-item" href="/admin_classes.php">Classes</a></li>
                                <li><a class="dropdown-item" href="/admin_subjects.php">Subjects</a></li>
                                <li><a class="dropdown-item" href="/admin_teachers.php">Teachers</a></li>
                                <li><a class="dropdown-item" href="/admin_workloads.php">Workloads</a></li>
                                <li><a class="dropdown-item" href="/admin_timeslots.php">Timeslots</a></li>
                                <li><a class="dropdown-item" href="/admin_elective_groups.php">Elective Groups</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/admin_data_management.php">Data Management</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php if ($role === 'admin' || $role === 'teacher'): ?>
                        <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'timetable.php') ? 'active' : ''; ?>" href="/timetable.php">Class Timetable</a></li>
                    <?php endif; ?>

                    <?php if ($role === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'teacher_timetable.php') ? 'active' : ''; ?>" href="/teacher_timetable.php">Teacher Timetable</a></li>
                    <?php endif; ?>

                    <?php if ($role === 'teacher'): ?>
                        <?php if (!empty($_SESSION['can_edit_workload'])): ?>
                            <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'teacher_workload.php') ? 'active' : ''; ?>" href="/teacher_workload.php">My Workload</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'teacher_timetable.php') ? 'active' : ''; ?>" href="/teacher_timetable.php">My Timetable</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="/logout.php">Logout</a></li>
                <?php else : ?>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'demo.php') ? 'active' : ''; ?>" href="/demo.php">Demo</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'login.php') ? 'active' : ''; ?>" href="/login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'register.php') ? 'active' : ''; ?>" href="/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>