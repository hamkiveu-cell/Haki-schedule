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

    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

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