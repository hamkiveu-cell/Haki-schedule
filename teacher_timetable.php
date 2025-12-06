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

function get_teacher_schedule($pdo, $teacher_id) {
    $sql = "
    SELECT
        s.id,
        s.day_of_week,
        s.timeslot_id,
        s.lesson_display_name,
        c.name as class_name,
        sub.name as subject_name, -- The specific subject for the teacher
        s.is_double,
        s.is_elective
    FROM schedules s
    JOIN schedule_teachers st ON s.id = st.schedule_id
    LEFT JOIN classes c ON s.class_id = c.id
    -- Find the specific subject this teacher teaches to this class from workloads
    LEFT JOIN workloads w ON st.teacher_id = w.teacher_id AND s.class_id = w.class_id
    LEFT JOIN subjects sub ON w.subject_id = sub.id
    WHERE st.teacher_id = :teacher_id
    ORDER BY s.day_of_week, s.timeslot_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':teacher_id' => $teacher_id]);
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
    $teacher_schedule_raw = get_teacher_schedule($pdoconn, $selected_teacher_id);
    
    // Gemini: Log the data for debugging
    error_log("--- Teacher Timetable Debug ---");
    error_log("Selected Teacher ID: " . print_r($selected_teacher_id, true));
    error_log("Raw schedule data from DB: " . print_r($teacher_schedule_raw, true));
}

// Organize schedule for easy display
$non_break_periods = array_values(array_filter($timeslots, function($ts) { return !$ts['is_break']; }));
$timeslot_id_to_period_idx = [];
foreach($non_break_periods as $idx => $period) {
    $timeslot_id_to_period_idx[$period['id']] = $idx;
}

$teacher_timetable_by_period = [];
// Initialize the timetable array
foreach ($days_of_week as $day_idx => $day) {
    $teacher_timetable_by_period[$day_idx] = array_fill(0, count($non_break_periods), null);
}

foreach ($teacher_schedule_raw as $lesson) {
    $day_idx = $lesson['day_of_week'];
    if (isset($timeslot_id_to_period_idx[$lesson['timeslot_id']])) {
        $period_idx = $timeslot_id_to_period_idx[$lesson['timeslot_id']];
        
        if (isset($teacher_timetable_by_period[$day_idx][$period_idx])) {
            // This slot is already filled, potentially by a multi-class elective.
            // Create an array if it's not already one.
            if (!is_array($teacher_timetable_by_period[$day_idx][$period_idx])) {
                $teacher_timetable_by_period[$day_idx][$period_idx] = [$teacher_timetable_by_period[$day_idx][$period_idx]];
            }
            $teacher_timetable_by_period[$day_idx][$period_idx][] = $lesson;
        } else {
            $teacher_timetable_by_period[$day_idx][$period_idx] = $lesson;
        }

        if (!empty($lesson['is_double']) && isset($teacher_timetable_by_period[$day_idx][$period_idx + 1])) {
            if (isset($teacher_timetable_by_period[$day_idx][$period_idx + 1])) {
                 if (!is_array($teacher_timetable_by_period[$day_idx][$period_idx + 1])) {
                    $teacher_timetable_by_period[$day_idx][$period_idx + 1] = [$teacher_timetable_by_period[$day_idx][$period_idx + 1]];
                }
                $teacher_timetable_by_period[$day_idx][$period_idx + 1][] = $lesson;
            } else {
                 $teacher_timetable_by_period[$day_idx][$period_idx + 1] = $lesson;
            }
        }
    }
}

// Gemini: Log the final structure
error_log("Final teacher_timetable_by_period structure: " . print_r($teacher_timetable_by_period, true));

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
    <?php require_once 'includes/navbar.php'; ?>

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
                            <option value="<?php echo $teacher['id']; ?>" <?php echo ($selected_teacher_id == $teacher['id']) ? 'selected' : ''; ?> >
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
                                <?php
                                $period_indices = array_keys($non_break_periods);
                                foreach ($period_indices as $period_idx) {
                                    $current_timeslot_id = $non_break_periods[$period_idx]['id'];
                                    // Find the full timeslot info, including breaks
                                    $timeslot_info = null;
                                    foreach ($timeslots as $ts) {
                                        if ($ts['id'] === $current_timeslot_id) {
                                            $timeslot_info = $ts;
                                            break;
                                        }
                                    }

                                    // Find if there is a break before this timeslot
                                    $break_html = '';
                                    $last_period_end_time = null;
                                    if ($period_idx > 0) {
                                        $prev_period_id = $non_break_periods[$period_idx - 1]['id'];
                                        foreach($timeslots as $ts) {
                                            if ($ts['id'] === $prev_period_id) {
                                                $last_period_end_time = $ts['end_time'];
                                                break;
                                            }
                                        }
                                    }

                                    foreach ($timeslots as $ts) {
                                        if ($ts['is_break'] && $ts['start_time'] >= $last_period_end_time && $ts['start_time'] < $timeslot_info['start_time']) {
                                            $break_html .= '<tr>';
                                            $break_html .= '<td><strong>' . htmlspecialchars($ts['name']) . '</strong><br><small class="text-muted">' . date("g:i A", strtotime($ts['start_time'])) . ' - ' . date("g:i A", strtotime($ts['end_time'])) . '</small></td>';
                                            $break_html .= '<td colspan="' . count($days_of_week) . '" class="text-center table-secondary"><strong>Break</strong></td>';
                                            $break_html .= '</tr>';
                                        }
                                    }
                                    echo $break_html;
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($timeslot_info['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo date("g:i A", strtotime($timeslot_info['start_time'])); ?> - <?php echo date("g:i A", strtotime($timeslot_info['end_time'])); ?></small>
                                        </td>
                                        <?php foreach ($days_of_week as $day_idx => $day): ?>
                                            <td class="timetable-slot align-middle">
                                                <?php
                                                $lesson = $teacher_timetable_by_period[$day_idx][$period_idx] ?? null;
                                                if ($lesson) {
                                                    // If it's an array of lessons (co-teaching), display them all
                                                    $lessons_to_display = is_array($lesson) && !isset($lesson['id']) ? $lesson : [$lesson];
                                                    foreach ($lessons_to_display as $single_lesson) {
                                                        if ($single_lesson) { // Check not null
                                                            $display_name = $single_lesson['lesson_display_name'];
                                                            // For electives, the teacher should see their specific subject.
                                                            if (!empty($single_lesson['is_elective']) && !empty($single_lesson['subject_name'])) {
                                                                $display_name = $single_lesson['subject_name'];
                                                            }
                                                            echo '<div class="lesson p-1 mb-1">';
                                                            echo '<strong>' . htmlspecialchars($display_name) . '</strong><br>';
                                                            echo '<small>' . htmlspecialchars($single_lesson['class_name']) . '</small>';
                                                            echo '</div>';
                                                        }
                                                    }
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php
                                }
                                // Check for any breaks at the very end of the day
                                $last_timeslot_end_time = end($non_break_periods)['end_time'];
                                $final_break_html = '';
                                foreach ($timeslots as $ts) {
                                    if ($ts['is_break'] && $ts['start_time'] >= $last_timeslot_end_time) {
                                        $final_break_html .= '<tr>';
                                        $final_break_html .= '<td><strong>' . htmlspecialchars($ts['name']) . '</strong><br><small class="text-muted">' . date("g:i A", strtotime($ts['start_time'])) . ' - ' . date("g:i A", strtotime($ts['end_time'])) . '</small></td>';
                                        $final_break_html .= '<td colspan="' . count($days_of_week) . '" class="text-center table-secondary"><strong>Break</strong></td>';
                                        $final_break_html .= '</tr>';
                                    }
                                }
                                echo $final_break_html;
                                ?>
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