<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Haki Schedule</title>
    <meta name="description" content="Built with Flatlogic Generator">
    <meta name="keywords" content="timetable app, school scheduling, automated timetable, class schedule, teacher workload, subscription timetable, school administration, education tech, Haki schedule, Built with Flatlogic Generator">
    <meta property="og:title" content="Haki Schedule">
    <meta property="og:description" content="Built with Flatlogic Generator">
    <meta property="og:image" content="">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo time(); ?>">
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">Haki Schedule</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/demo.php">Demo</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-lg-3">
                    <li class="nav-item">
                        <a class="btn btn-outline-primary" href="#">Login</a>
                    </li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn btn-primary" href="#">Sign Up</a>
                    </li>
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