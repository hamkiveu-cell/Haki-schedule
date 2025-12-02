<?php
session_start();
require_once 'includes/auth_check_teacher.php';
require_once 'db/config.php';

// --- Database Fetch Functions ---
function get_teachers($pdo, $school_id) {
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE school_id = ? ORDER BY name");
    $stmt->execute([$school_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_timeslots($pdo) {
    return $pdo->query("SELECT * FROM timeslots ORDER BY start_time")->fetchAll(PDO::FETCH_ASSOC);
}

function get_teacher_schedule($pdo, $teacher_id, $school_id) {
    $stmt = $pdo->prepare("
        SELECT 
            s.day_of_week,
            s.timeslot_id,
            s.lesson_display_name,
            c.name as class_name,
            s.is_double,
            s.is_elective,
            s.is_horizontal_elective
        FROM schedules s
        JOIN classes c ON s.class_id = c.id
        JOIN schedule_teachers st ON s.id = st.schedule_id
        WHERE st.teacher_id = :teacher_id AND c.school_id = :school_id
    ");
    $stmt->execute([':teacher_id' => $teacher_id, ':school_id' => $school_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Main Logic ---
$pdoconn = db();
$school_id = $_SESSION['school_id'];
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$teachers = [];
if ($role === 'admin') {
    $stmt = $pdoconn->prepare("SELECT * FROM teachers WHERE school_id = ? ORDER BY name");
    $stmt->execute([$school_id]);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else { // Teacher
    $stmt = $pdoconn->prepare("SELECT * FROM teachers WHERE user_id = ? AND school_id = ?");
    $stmt->execute([$user_id, $school_id]);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$timeslots = get_timeslots($pdoconn);
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

$selected_teacher_id = null;
if ($role === 'admin') {
    $selected_teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : null;
} else { // Teacher
    if (!empty($teachers)) {
        $selected_teacher_id = $teachers[0]['id'];
    }
}

$selected_teacher_name = '';
$teacher_schedule_raw = [];
if ($selected_teacher_id) {
    foreach ($teachers as $teacher) {
        if ($teacher['id'] == $selected_teacher_id) {
            $selected_teacher_name = $teacher['name'];
            break;
        }
    }
    $teacher_schedule_raw = get_teacher_schedule($pdoconn, $selected_teacher_id, $school_id);
}

// Organize schedule for easy display
$teacher_timetable = array_fill(0, count($days_of_week), []);
foreach ($timeslots as $timeslot) {
    if (!$timeslot['is_break']) {
        foreach ($days_of_week as $day_idx => $day) {
            $teacher_timetable[$day_idx][$timeslot['id']] = null;
        }
    }
}

foreach ($teacher_schedule_raw as $lesson) {
    $day_idx = $lesson['day_of_week'];
    $timeslot_id = $lesson['timeslot_id'];
    if (isset($teacher_timetable[$day_idx][$timeslot_id])) {
        $teacher_timetable[$day_idx][$timeslot_id] = $lesson;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Timetable - Haki Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo time(); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            #timetable-container, #timetable-container * {
                visibility: visible;
            }
            #timetable-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .card {
                border: 1px solid #dee2e6 !important;
                box-shadow: none !important;
            }
        }
    </style>
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
                        <?php if ($role === 'admin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="manageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Manage
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="manageDropdown">
                                <li><a class="dropdown-item" href="/admin_classes.php">Classes</a></li>
                                <li><a class="dropdown-item" href="/admin_subjects.php">Subjects</a></li>
                                <li><a class="dropdown-item" href="/admin_teachers.php">Teachers</a></li>
                                <li><a class="dropdown-item" href="/admin_workloads.php">Workloads</a></li>
                                <li><a class="dropdown-item" href="/admin_timeslots.php">Timeslots</a></li>
                            </ul>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="/timetable.php">Class Timetable</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link active" href="/teacher_timetable.php">Teacher Timetable</a></li>
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

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Teacher Timetable</h1>
            <?php if ($selected_teacher_id): ?>
            <div class="d-flex gap-2">
                <button id="print-btn" class="btn btn-secondary">Print</button>
                <button id="download-btn" class="btn btn-secondary">Download as PDF</button>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($role === 'admin'): ?>
        <form method="GET" action="" class="mb-4">
            <div class="row">
                <div class="col-md-4">
                    <label for="teacher_id" class="form-label">Select Teacher</label>
                    <select name="teacher_id" id="teacher_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Select a Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>" <?php echo ($selected_teacher_id == $teacher['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <div id="timetable-container">
            <?php if ($selected_teacher_id && !empty($teacher_schedule_raw)): ?>
                <h3 class="mt-4">Timetable for <?php echo htmlspecialchars($selected_teacher_name); ?></h3>
                <div class="card">
                    <div class="card-body">
                        <table class="table table-bordered text-center">
                            <thead>
                                <tr>
                                    <th style="width: 12%;">Time</th>
                                    <?php foreach ($days_of_week as $day): ?>
                                        <th><?php echo $day; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($timeslots as $timeslot): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($timeslot['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo date("g:i A", strtotime($timeslot['start_time'])); ?> - <?php echo date("g:i A", strtotime($timeslot['end_time'])); ?></small>
                                        </td>
                                        <?php if ($timeslot['is_break']): ?>
                                            <td colspan="<?php echo count($days_of_week); ?>" class="text-center table-secondary"><strong>Break</strong></td>
                                        <?php else: ?>
                                            <?php foreach ($days_of_week as $day_idx => $day): ?>
                                                <td class="timetable-slot">
                                                    <?php 
                                                    $lesson = $teacher_timetable[$day_idx][$timeslot['id']] ?? null;
                                                    if ($lesson):
                                                        $class_str = '';
                                                        if ($lesson['is_horizontal_elective']) $class_str = 'bg-light-purple';
                                                        elseif ($lesson['is_elective']) $class_str = 'bg-light-green';
                                                        elseif ($lesson['is_double']) $class_str = 'bg-light-blue';
                                                    ?>
                                                        <div class="lesson p-1 <?php echo $class_str; ?>">
                                                            <strong><?php echo htmlspecialchars($lesson['lesson_display_name']); ?></strong><br>
                                                            <small><?php echo htmlspecialchars($lesson['class_name']); ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($selected_teacher_id): ?>
                <div class="alert alert-info">No lessons scheduled for this teacher.</div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        <?php if ($selected_teacher_id): ?>
        const { jsPDF } = window.jspdf;

        document.getElementById('print-btn').addEventListener('click', function () {
            window.print();
        });

        document.getElementById('download-btn').addEventListener('click', function () {
            const element = document.getElementById('timetable-container');
            const teacherName = "<?php echo htmlspecialchars($selected_teacher_name, ENT_QUOTES, 'UTF-8'); ?>";
            const fileName = `timetable-${teacherName.replace(/\s+/g, '-').toLowerCase()}.pdf`;

            html2canvas(element, { scale: 2 }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const doc = new jsPDF({
                    orientation: 'l',
                    unit: 'pt',
                    format: 'a4'
                });
                const imgWidth = doc.internal.pageSize.getWidth() - 40;
                const imgHeight = canvas.height * imgWidth / canvas.width;
                doc.addImage(imgData, 'PNG', 20, 20, imgWidth, imgHeight);
                doc.save(fileName);
            });
        });
        <?php endif; ?>
    });
    </script>
</body>
</html>