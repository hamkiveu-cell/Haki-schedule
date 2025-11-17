<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Demo - Haki Schedule</title>
    <meta name="description" content="Built with Flatlogic Generator">
    <meta name="keywords" content="timetable app, school scheduling, automated timetable, class schedule, teacher workload, subscription timetable, school administration, education tech, Haki schedule, Built with Flatlogic Generator">
    <meta property="og:title" content="Haki Schedule">
    <meta property="og:description" content="Built with Flatlogic Generator">
    <meta property="og:image" content="">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="">
    <meta name="robots" content="noindex, nofollow">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo time(); ?>">
</head>
<body class="bg-light py-5">

    <div class="container printable-area">
        <div class="timetable-container">
            <div class="timetable-header">
                <div>
                    <h1 class="h3 fw-bold">Class Timetable: Grade 10A</h1>
                    <p class="text-muted">A visual demonstration of a generated schedule.</p>
                </div>
                <div>
                    <a href="/" class="btn btn-outline-secondary me-2">Back to Home</a>
                    <button onclick="window.print();" class="btn btn-primary">Print Timetable</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered timetable-table">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>08:00 - 09:00</th>
                            <th>09:00 - 10:00</th>
                            <th>10:00 - 10:30</th>
                            <th>10:30 - 11:30</th>
                            <th>11:30 - 12:30</th>
                            <th>12:30 - 13:30</th>
                            <th>13:30 - 14:30</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="day-header">Monday</td>
                            <td class="timetable-cell"><strong>Mathematics</strong><span>Mr. Smith</span></td>
                            <td class="timetable-cell"><strong>Physics</strong><span>Ms. Jones</span></td>
                            <td class="break-cell">Break</td>
                            <td class="timetable-cell"><strong>English</strong><span>Mr. Doe</span></td>
                            <td class="timetable-cell"><strong>History</strong><span>Mrs. Dane</span></td>
                            <td class="break-cell">Lunch</td>
                            <td class="timetable-cell"><strong>Biology</strong><span>Ms. Jones</span></td>
                        </tr>
                        <tr>
                            <td class="day-header">Tuesday</td>
                            <td class="timetable-cell"><strong>Chemistry</strong><span>Mr. White</span></td>
                            <td class="timetable-cell"><strong>English</strong><span>Mr. Doe</span></td>
                            <td class="break-cell">Break</td>
                            <td class="timetable-cell"><strong>Mathematics</strong><span>Mr. Smith</span></td>
                            <td class="timetable-cell"><strong>Geography</strong><span>Mr. Green</span></td>
                            <td class="break-cell">Lunch</td>
                            <td class="timetable-cell"><strong>Art</strong><span>Ms. Black</span></td>
                        </tr>
                        <tr>
                            <td class="day-header">Wednesday</td>
                            <td class="timetable-cell"><strong>Physics (Double)</strong><span>Ms. Jones</span></td>
                            <td class="timetable-cell"><strong>Physics (Double)</strong><span>Ms. Jones</span></td>
                            <td class="break-cell">Break</td>
                            <td class="timetable-cell"><strong>History</strong><span>Mrs. Dane</span></td>
                            <td class="timetable-cell"><strong>Mathematics</strong><span>Mr. Smith</span></td>
                            <td class="break-cell">Lunch</td>
                            <td class="timetable-cell"><strong>Music</strong><span>Mr. Brown</span></td>
                        </tr>
                        <tr>
                            <td class="day-header">Thursday</td>
                            <td class="timetable-cell"><strong>English</strong><span>Mr. Doe</span></td>
                            <td class="timetable-cell"><strong>Biology</strong><span>Ms. Jones</span></td>
                            <td class="break-cell">Break</td>
                            <td class="timetable-cell"><strong>Chemistry</strong><span>Mr. White</span></td>
                            <td class="timetable-cell"><strong>Physical Ed.</strong><span>Mr. Blue</span></td>
                            <td class="break-cell">Lunch</td>
                            <td class="timetable-cell"><strong>Mathematics</strong><span>Mr. Smith</span></td>
                        </tr>
                        <tr>
                            <td class="day-header">Friday</td>
                            <td class="timetable-cell"><strong>History</strong><span>Mrs. Dane</span></td>
                            <td class="timetable-cell"><strong>Geography</strong><span>Mr. Green</span></td>
                            <td class="break-cell">Break</td>
                            <td class="timetable-cell"><strong>English</strong><span>Mr. Doe</span></td>
                            <td class="timetable-cell"><strong>Mathematics</strong><span>Mr. Smith</span></td>
                            <td class="break-cell">Lunch</td>
                            <td class="timetable-cell"><strong>Elective</strong><span>Various</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
