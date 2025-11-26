<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'db/config.php';

// --- Database Fetch ---
function get_workloads($pdo) {
    $stmt = $pdo->query("
        SELECT 
            w.id,
            c.id as class_id,
            c.name as class_name,
            s.id as subject_id,
            s.name as subject_name,
            s.has_double_lesson,
            s.elective_group_id,
            eg.name as elective_group_name,
            t.id as teacher_id,
            t.name as teacher_name,
            w.lessons_per_week
        FROM workloads w
        JOIN classes c ON w.class_id = c.id
        JOIN subjects s ON w.subject_id = s.id
        JOIN teachers t ON w.teacher_id = t.id
        LEFT JOIN elective_groups eg ON s.elective_group_id = eg.id
        ORDER BY c.name, s.name
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_classes($pdo) {
    return $pdo->query("SELECT * FROM classes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

function get_timeslots($pdo) {
    return $pdo->query("SELECT * FROM timeslots ORDER BY start_time")->fetchAll(PDO::FETCH_ASSOC);
}

// --- Helper Functions ---
function get_grade_from_class_name($class_name) {
    if (preg_match('/^(Grade\s+\d+)/i', $class_name, $matches)) {
        return $matches[1];
    }
    return $class_name;
}

// --- Scoring and Placement Logic ---
function find_best_slot_for_lesson($lesson, $is_double, &$class_timetables, &$teacher_timetables, $all_class_ids, $days_of_week, $periods_per_day) {
    $best_slot = null;
    $highest_score = -1000;

    $class_id = $lesson['type'] === 'horizontal_elective' ? null : $lesson['class_id'];
    $teachers_in_lesson = array_unique(array_column($lesson['component_lessons'], 'teacher_id'));
    $class_ids_in_lesson = $lesson['type'] === 'horizontal_elective' ? $lesson['participating_class_ids'] : [$class_id];

    for ($day = 0; $day < count($days_of_week); $day++) {
        for ($period = 0; $period < $periods_per_day; $period++) {
            $current_score = 100; // Base score

            // 1. Check basic availability
            $slot_available = true;
            if ($is_double) {
                if ($period + 1 >= $periods_per_day) continue; // Not enough space for a double
                foreach ($class_ids_in_lesson as $cid) {
                    if (isset($class_timetables[$cid][$day][$period]) || isset($class_timetables[$cid][$day][$period + 1])) {
                        $slot_available = false; break;
                    }
                }
                if (!$slot_available) continue;
                foreach ($teachers_in_lesson as $teacher_id) {
                    if (isset($teacher_timetables[$teacher_id][$day][$period]) || isset($teacher_timetables[$teacher_id][$day][$period + 1])) {
                        $slot_available = false; break;
                    }
                }
            } else {
                foreach ($class_ids_in_lesson as $cid) {
                    if (isset($class_timetables[$cid][$day][$period])) {
                        $slot_available = false; break;
                    }
                }
                if (!$slot_available) continue;
                foreach ($teachers_in_lesson as $teacher_id) {
                    if (isset($teacher_timetables[$teacher_id][$day][$period])) {
                        $slot_available = false; break;
                    }
                }
            }
            if (!$slot_available) continue;

            // 2. Apply scoring rules
            // A. Penalize placing the same subject on the same day
            foreach ($class_ids_in_lesson as $cid) {
                $lessons_on_day = 0;
                for ($p = 0; $p < $periods_per_day; $p++) {
                    if (isset($class_timetables[$cid][$day][$p]) && $class_timetables[$cid][$day][$p]['subject_name'] === $lesson['display_name']) {
                        $lessons_on_day++;
                    }
                }
                $current_score -= $lessons_on_day * 50; // Heavy penalty for each existing lesson of same subject
            }

            // B. Penalize creating gaps for teachers and classes
            foreach ($teachers_in_lesson as $teacher_id) {
                // Check for gap before the lesson
                if ($period > 0 && !isset($teacher_timetables[$teacher_id][$day][$period - 1])) {
                     if (isset($teacher_timetables[$teacher_id][$day][$period - 2])) $current_score -= 25; // Gap of 1
                }
                // Check for gap after the lesson
                $after_period = $is_double ? $period + 2 : $period + 1;
                if ($after_period < $periods_per_day && !isset($teacher_timetables[$teacher_id][$day][$after_period])) {
                    if (isset($teacher_timetables[$teacher_id][$day][$after_period + 1])) $current_score -= 25; // Gap of 1
                }
            }
            foreach ($class_ids_in_lesson as $cid) {
                 if ($period > 0 && !isset($class_timetables[$cid][$day][$period - 1])) {
                     if (isset($class_timetables[$cid][$day][$period - 2])) $current_score -= 10;
                }
                $after_period = $is_double ? $period + 2 : $period + 1;
                if ($after_period < $periods_per_day && !isset($class_timetables[$cid][$day][$after_period])) {
                    if (isset($class_timetables[$cid][$day][$after_period + 1])) $current_score -= 10;
                }
            }

            // C. Penalize placing a double lesson of the same subject in parallel with another class
            if ($is_double) {
                $subject_name_to_check = $lesson['display_name'];
                foreach ($all_class_ids as $cid) {
                    if (in_array($cid, $class_ids_in_lesson)) continue; // Don't check against itself
                    if (isset($class_timetables[$cid][$day][$period]) && $class_timetables[$cid][$day][$period]['is_double'] && $class_timetables[$cid][$day][$period]['subject_name'] === $subject_name_to_check) {
                        $current_score -= 200; // Very high penalty for parallel doubles of same subject
                        break;
                    }
                }
            }

            // D. Prefer ends of day for double lessons
            if ($is_double) {
                if ($period == 0 || $period + 1 == $periods_per_day -1) {
                    $current_score += 10;
                }
            }

            if ($current_score > $highest_score) {
                $highest_score = $current_score;
                $best_slot = ['day' => $day, 'period' => $period];
            }
        }
    }

// --- Main Scheduling Engine ---
function generate_timetable($workloads, $classes, $days_of_week, $periods_per_day) {
    $class_timetables = [];
    foreach ($classes as $class) {
        $class_timetables[$class['id']] = array_fill(0, count($days_of_week), array_fill(0, $periods_per_day, null));
    }
    $teacher_timetables = [];

    // --- Lesson Preparation ---
    $all_lessons = [];
    $workloads_by_grade_and_elective_group = [];

    // 1. Group horizontal electives
    foreach ($workloads as $workload) {
        if (!empty($workload['elective_group_id'])) {
            $grade_name = get_grade_from_class_name($workload['class_name']);
            $workloads_by_grade_and_elective_group[$grade_name][$workload['elective_group_id']][] = $workload;
        } else {
            // This will be handled in the next step
        }
    }

    $processed_workload_ids = [];
    foreach ($workloads_by_grade_and_elective_group as $grade_name => $elective_groups) {
        foreach ($elective_groups as $elective_group_id => $group_workloads) {
            $participating_class_ids = array_unique(array_column($group_workloads, 'class_id'));
            if (count($participating_class_ids) > 1) {
                $first = $group_workloads[0];
                $block = [
                    'type' => 'horizontal_elective',
                    'display_name' => $first['elective_group_name'],
                    'participating_class_ids' => $participating_class_ids,
                    'lessons_per_week' => $first['lessons_per_week'],
                    'has_double_lesson' => $first['has_double_lesson'],
                    'component_lessons' => $group_workloads,
                    'priority' => 4 // Highest priority
                ];
                $all_lessons[] = $block;
                foreach($group_workloads as $w) $processed_workload_ids[] = $w['id'];
            }
        }
    }

    // 2. Group remaining lessons (single, elective, and normal doubles)
    $remaining_workloads = array_filter($workloads, function($w) use ($processed_workload_ids) {
        return !in_array($w['id'], $processed_workload_ids);
    });

    $workloads_by_class = [];
    foreach ($remaining_workloads as $workload) {
        $workloads_by_class[$workload['class_id']][] = $workload;
    }

    foreach ($workloads_by_class as $class_id => $class_workloads) {
        $elective_groups = [];
        $individual_lessons = [];
        foreach ($class_workloads as $workload) {
            if (!empty($workload['elective_group_id'])) {
                $elective_groups[$workload['elective_group_id']][] = $workload;
            } else {
                $individual_lessons[] = $workload;
            }
        }

        foreach ($elective_groups as $group_id => $group_workloads) {
            $first = $group_workloads[0];
            $all_lessons[] = [
                'type' => 'elective', 'class_id' => $class_id, 'display_name' => $first['elective_group_name'],
                'lessons_per_week' => $first['lessons_per_week'], 'has_double_lesson' => $first['has_double_lesson'],
                'component_lessons' => $group_workloads,
                'priority' => 3
            ];
        }

        foreach ($individual_lessons as $workload) {
            $all_lessons[] = [
                'type' => 'single', 'class_id' => $workload['class_id'], 'display_name' => $workload['subject_name'],
                'lessons_per_week' => $workload['lessons_per_week'], 'has_double_lesson' => $workload['has_double_lesson'],
                'component_lessons' => [$workload],
                'priority' => $workload['has_double_lesson'] ? 2 : 1
            ];
        }
    }

    // 3. Explode lessons into single and double instances
    $final_lesson_list = [];
    foreach ($all_lessons as $lesson_block) {
        $num_doubles = 0;
        $num_singles = $lesson_block['lessons_per_week'];
        if ($lesson_block['has_double_lesson'] && $lesson_block['lessons_per_week'] >= 2) {
            $num_doubles = 1;
            $num_singles -= 2;
        }
        for ($i=0; $i < $num_doubles; $i++) {
            $final_lesson_list[] = ['is_double' => true, 'lesson_details' => $lesson_block];
        }
        for ($i=0; $i < $num_singles; $i++) {
            $final_lesson_list[] = ['is_double' => false, 'lesson_details' => $lesson_block];
        }
    }

    // 4. Sort by priority (desc) and then shuffle to vary timetable
    usort($final_lesson_list, function($a, $b) {
        $prio_a = $a['lesson_details']['priority'];
        $prio_b = $b['lesson_details']['priority'];
        if ($prio_a != $prio_b) return $prio_b - $prio_a;
        if ($a['is_double'] != $b['is_double']) return $b['is_double'] - $a['is_double'];
        return rand(-1, 1);
    });

    // --- Placement using Scoring ---
    $all_class_ids = array_column($classes, 'id');

    foreach ($final_lesson_list as $lesson_item) {
        $lesson = $lesson_item['lesson_details'];
        $is_double = $lesson_item['is_double'];
        
        $best_slot = find_best_slot_for_lesson($lesson, $is_double, $class_timetables, $teacher_timetables, $all_class_ids, $days_of_week, $periods_per_day);

        if ($best_slot) {
            $day = $best_slot['day'];
            $period = $best_slot['period'];
            $class_ids_to_place = ($lesson['type'] === 'horizontal_elective') ? $lesson['participating_class_ids'] : [$lesson['class_id']];
            $teachers_to_place = array_unique(array_column($lesson['component_lessons'], 'teacher_id'));

            $subject_id = null;
            $teacher_id = null;
            // For single-teacher, single-subject lessons, store the IDs directly
            if (count($lesson['component_lessons']) === 1) {
                $subject_id = $lesson['component_lessons'][0]['subject_id'];
                $teacher_id = $lesson['component_lessons'][0]['teacher_id'];
            }

            $lesson_info = [
                'subject_id' => $subject_id,
                'teacher_id' => $teacher_id,
                'subject_name' => $lesson['display_name'],
                'teacher_name' => count($teachers_to_place) > 1 ? 'Multiple' : $lesson['component_lessons'][0]['teacher_name'],
                'is_double' => $is_double,
                'is_elective' => $lesson['type'] === 'elective',
                'is_horizontal_elective' => $lesson['type'] === 'horizontal_elective'
            ];

            if ($is_double) {
                foreach ($class_ids_to_place as $cid) {
                    $class_timetables[$cid][$day][$period] = $lesson_info;
                    $class_timetables[$cid][$day][$period + 1] = $lesson_info;
                }
                foreach ($teachers_to_place as $tid) {
                    $teacher_timetables[$tid][$day][$period] = true;
                    $teacher_timetables[$tid][$day][$period + 1] = true;
                }
            } else {
                foreach ($class_ids_to_place as $cid) $class_timetables[$cid][$day][$period] = $lesson_info;
                foreach ($teachers_to_place as $tid) $teacher_timetables[$tid][$day][$period] = true;
            }
        }
    }
    return $class_timetables;
}

// --- Timetable Persistence ---
function save_timetable($pdo, $class_timetables, $timeslots) {
    $pdo->exec('TRUNCATE TABLE schedules');
    $stmt = $pdo->prepare(
        'INSERT INTO schedules (class_id, day_of_week, timeslot_id, subject_id, teacher_id, lesson_display_name, teacher_display_name, is_double, is_elective, is_horizontal_elective) ' .
        'VALUES (:class_id, :day_of_week, :timeslot_id, :subject_id, :teacher_id, :lesson_display_name, :teacher_display_name, :is_double, :is_elective, :is_horizontal_elective)'
    );

    $lesson_periods = array_values(array_filter($timeslots, function($ts) { return !$ts['is_break']; }));

    foreach ($class_timetables as $class_id => $day_schedule) {
        foreach ($day_schedule as $day_idx => $period_schedule) {
            foreach ($period_schedule as $period_idx => $lesson) {
                if ($lesson) {
                    if (!isset($lesson_periods[$period_idx])) continue;
                    $timeslot_id = $lesson_periods[$period_idx]['id'];
                    $stmt->execute([
                        ':class_id' => $class_id,
                        ':day_of_week' => $day_idx,
                        ':timeslot_id' => $timeslot_id,
                        ':subject_id' => $lesson['subject_id'],
                        ':teacher_id' => $lesson['teacher_id'],
                        ':lesson_display_name' => $lesson['subject_name'],
                        ':teacher_display_name' => $lesson['teacher_name'],
                        ':is_double' => (int)$lesson['is_double'],
                        ':is_elective' => (int)$lesson['is_elective'],
                        ':is_horizontal_elective' => (int)$lesson['is_horizontal_elective']
                    ]);
                }
            }
        }
    }
}

function get_timetable_from_db($pdo, $classes, $timeslots) {
    $stmt = $pdo->query('SELECT * FROM schedules ORDER BY id');
    $saved_lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($saved_lessons)) return [];

    $days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $periods = array_filter($timeslots, function($ts) { return !$ts['is_break']; });
    $periods_per_day = count($periods);
    
    $class_timetables = [];
    foreach ($classes as $class) {
        $class_timetables[$class['id']] = array_fill(0, count($days_of_week), array_fill(0, $periods_per_day, null));
    }

    $lesson_periods = array_values($periods);
    $timeslot_id_to_period_idx = [];
    foreach($lesson_periods as $idx => $period) {
        $timeslot_id_to_period_idx[$period['id']] = $idx;
    }

    foreach ($saved_lessons as $lesson) {
        $class_id = $lesson['class_id'];
        $day_idx = $lesson['day_of_week'];
        $timeslot_id = $lesson['timeslot_id'];
        
        if (!isset($timeslot_id_to_period_idx[$timeslot_id]) || !isset($class_timetables[$class_id])) continue;
        $period_idx = $timeslot_id_to_period_idx[$timeslot_id];

        $class_timetables[$class_id][$day_idx][$period_idx] = [
            'id' => $lesson['id'],
            'subject_id' => $lesson['subject_id'],
            'teacher_id' => $lesson['teacher_id'],
            'subject_name' => $lesson['lesson_display_name'],
            'teacher_name' => $lesson['teacher_display_name'],
            'is_double' => (bool)$lesson['is_double'],
            'is_elective' => (bool)$lesson['is_elective'],
            'is_horizontal_elective' => (bool)$lesson['is_horizontal_elective']
        ];
    }
    return $class_timetables;
}

// --- Main Logic ---
$pdoconn = db();
$workloads = get_workloads($pdoconn);
$classes = get_classes($pdoconn);
$timeslots = get_timeslots($pdoconn);

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$periods = array_filter($timeslots, function($timeslot) { return !$timeslot['is_break']; });
$periods_per_day = count($periods);

if (isset($_POST['generate'])) {
    $class_timetables = generate_timetable($workloads, $classes, $days_of_week, $periods_per_day);
    save_timetable($pdoconn, $class_timetables, $timeslots);
} else {
    $class_timetables = get_timetable_from_db($pdoconn, $classes, $timeslots);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable - Haki Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            #timetables-container, #timetables-container * {
                visibility: visible;
            }
            #timetables-container {
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
                        <li class="nav-item"><a class="nav-link active" href="/timetable.php">Class Timetable</a></li>
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

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Class Timetable</h1>
            <div class="d-flex gap-2">
                <form method="POST" action="">
                    <button type="submit" name="generate" class="btn btn-primary">Generate Timetable</button>
                </form>
                <button id="print-btn" class="btn btn-secondary">Print</button>
                <button id="download-btn" class="btn btn-secondary">Download as PDF</button>
            </div>
        </div>

        <div id="timetables-container">
            <?php if (!empty($class_timetables)): ?>
                <?php foreach ($classes as $class): ?>
                    <?php if (!isset($class_timetables[$class['id']])) continue; ?>
                    <div class="timetable-wrapper mb-5">
                        <h3 class="mt-5"><?php echo htmlspecialchars($class['name']); ?></h3>
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
                                        $period_idx = 0;
                                        foreach ($timeslots as $timeslot): 
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($timeslot['name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo date("g:i A", strtotime($timeslot['start_time'])); ?> - <?php echo date("g:i A", strtotime($timeslot['end_time'])); ?></small>
                                                </td>
                                                <?php if ($timeslot['is_break']): ?>
                                                    <td colspan="<?php echo count($days_of_week); ?>" class="text-center table-secondary"><strong>Break</strong></td>
                                                <?php else: ?>
                                                    <?php for ($day_idx = 0; $day_idx < count($days_of_week); $day_idx++): ?>
                                                        <td class="timetable-slot" data-class-id="<?php echo $class['id']; ?>" data-day="<?php echo $day_idx; ?>" data-timeslot-id="<?php echo $timeslot['id']; ?>">
                                                            <?php 
                                                            $lesson = $class_timetables[$class['id']][$day_idx][$period_idx] ?? null;
                                                            if ($lesson):
                                                                $class_str = '';
                                                                if ($lesson['is_horizontal_elective']) $class_str = 'bg-light-purple';
                                                                elseif ($lesson['is_elective']) $class_str = 'bg-light-green';
                                                                elseif ($lesson['is_double']) $class_str = 'bg-light-blue';
                                                            ?>
                                                                <div class="lesson p-1 <?php echo $class_str; ?>" data-lesson-id="<?php echo $lesson['id']; ?>" draggable="true">
                                                                    <strong><?php echo htmlspecialchars($lesson['subject_name']); ?></strong><br>
                                                                    <small><?php echo htmlspecialchars($lesson['teacher_name']); ?></small>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endfor; ?>
                                                    <?php $period_idx++; ?>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <?php if (empty($workloads)): ?>
                        No workloads found. Please add classes, subjects, teachers, and workloads in the "Manage" section first.
                    <?php else: ?>
                        Click the "Generate Timetable" button to create a schedule.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Drag and drop
        const slots = document.querySelectorAll('.timetable-slot');
        slots.forEach(function (slot) {
            new Sortable(slot, {
                group: 'lessons',
                animation: 150,
                onEnd: function (evt) {
                    const itemEl = evt.item;
                    const to = evt.to;
                    
                    const lessonId = itemEl.dataset.lessonId;
                    const toClassId = to.dataset.classId;
                    const toDay = to.dataset.day;
                    const toTimeslotId = to.dataset.timeslotId;

                    fetch('/api/move_lesson.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            lesson_id: lessonId,
                            to_class_id: toClassId,
                            to_day: toDay,
                            to_timeslot_id: toTimeslotId,
                        }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            console.error('Error moving lesson:', data.error);
                            alert('Error moving lesson: ' + data.error);
                            location.reload();
                        }
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                        alert('An unexpected error occurred.');
                        location.reload();
                    });
                }
            });
        });

        // Print and Download
        const { jsPDF } = window.jspdf;

        document.getElementById('print-btn').addEventListener('click', function () {
            window.print();
        });

        document.getElementById('download-btn').addEventListener('click', function () {
            const container = document.getElementById('timetables-container');
            const doc = new jsPDF({
                orientation: 'l',
                unit: 'pt',
                format: 'a4'
            });
            const margin = 20;
            const pageHeight = doc.internal.pageSize.getHeight() - (margin * 2);
            const timetableWrappers = container.querySelectorAll('.timetable-wrapper');
            let yPos = margin;
            let pageNum = 1;

            function addPageContent(element, isFirstPage) {
                return html2canvas(element, { scale: 2 }).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    const imgWidth = doc.internal.pageSize.getWidth() - (margin * 2);
                    const imgHeight = canvas.height * imgWidth / canvas.width;
                    
                    if (!isFirstPage) {
                        doc.addPage();
                    }
                    doc.addImage(imgData, 'PNG', margin, margin, imgWidth, imgHeight);
                });
            }

            let promise = Promise.resolve();
            timetableWrappers.forEach((wrapper, index) => {
                promise = promise.then(() => addPageContent(wrapper, index === 0));
            });

            promise.then(() => {
                doc.save('class-timetables.pdf');
            });
        });
    });
    </script>
</body>
</html>
