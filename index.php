<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Haki Schedule - Automated Timetable Generator</title>
    <meta name="description" content="Haki Schedule is a simple, elegant, and powerful automated timetable generator for educational institutions.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css">
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
                    <li class="nav-item"><a class="nav-link active" href="/">Home</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="manageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Manage
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="manageDropdown">
                                <li><a class="dropdown-item" href="/admin_classes.php">Classes</a></li>
                                <li><a class="dropdown-item" href="/admin_subjects.php">Subjects</a></li>
                                <li><a class="dropdown-item" href="/admin_teachers.php">Teachers</a></li>
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

    <header class="hero-section text-white text-center">
        <div class="container">
            <h1 class="display-4 fw-bold">Intelligent Timetabling, Simplified.</h1>
            <p class="lead my-4">Automate school schedules, eliminate conflicts, and empower your staff. <br>Haki Schedule is the all-in-one solution for modern educational institutions.</p>
            <a href="/demo.php" class="btn btn-light btn-lg">See a Demo</a>
        </div>
    </header>

    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Everything you need to run a seamless school schedule</h2>
                <p class="text-muted">From automated generation to easy access for teachers, we've got you covered.</p>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center p-4">
                        <div class="feature-icon mx-auto mb-3">
                            <i data-feather="cpu"></i>
                        </div>
                        <h5 class="card-title fw-bold">Automated Scheduling</h5>
                        <p class="card-text">Our powerful algorithm generates optimized, conflict-free timetables in minutes, not days. Handle complex workloads and constraints with ease.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center p-4">
                        <div class="feature-icon mx-auto mb-3">
                            <i data-feather="users"></i>
                        </div>
                        <h5 class="card-title fw-bold">Collaborative & Transparent</h5>
                        <p class="card-text">Empower teachers to view their schedules anytime. Admins can delegate workload entry and manage everything from a central dashboard.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center p-4">
                        <div class="feature-icon mx-auto mb-3">
                            <i data-feather="printer"></i>
                        </div>
                        <h5 class="card-title fw-bold">Accessible & Printable</h5>
                        <p class="card-text">View timetables on any device, web or mobile. Export and print beautiful, clean schedules for classes and teachers with a single click.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p>&copy; <?php echo date("Y"); ?> Haki Schedule. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      feather.replace()
    </script>
</body>
</html>