<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'db/config.php';

// --- Database Fetch ---
function get_all_data($pdo) {
    $data = [];
    $data['classes'] = $pdo->query("SELECT * FROM classes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $data['subjects'] = $pdo->query("SELECT id, name FROM subjects")->fetchAll(PDO::FETCH_KEY_PAIR);
    $data['teachers'] = $pdo->query("SELECT id, name FROM teachers")->fetchAll(PDO::FETCH_KEY_PAIR);
    $data['timeslots'] = $pdo->query("SELECT * FROM timeslots ORDER BY start_time")->fetchAll(PDO::FETCH_ASSOC);
    
    $workloads_stmt = $pdo->query("
        SELECT 
            w.class_id, w.subject_id, w.teacher_id, w.lessons_per_week,
            s.name as subject_name, s.has_double_lesson, s.elective_group_id,
            c.name as class_name,
            t.name as teacher_name,
            eg.name as elective_group_name
        FROM workloads w
        JOIN subjects s ON w.subject_id = s.id
        JOIN classes c ON w.class_id = c.id
        JOIN teachers t ON w.teacher_id = t.id
        LEFT JOIN elective_groups eg ON s.elective_group_id = eg.id
    ");
    $data['workloads'] = $workloads_stmt->fetchAll(PDO::FETCH_ASSOC);

    return $data;
}


// --- Main Scheduling Engine ---
function generate_timetable($data, $days_of_week) {
    $periods = array_values(array_filter($data['timeslots'], function($ts) { return !$ts['is_break']; }));
    $periods_per_day = count($periods);

    // 1. Initialize Timetables
    $class_timetables = [];
    $teacher_timetables = [];
    foreach ($data['classes'] as $class) {
        $class_timetables[$class['id']] = array_fill(0, count($days_of_week), array_fill(0, $periods_per_day, null));
    }
    foreach ($data['teachers'] as $teacher_id => $teacher_name) {
        $teacher_timetables[$teacher_id] = array_fill(0, count($days_of_week), array_fill(0, $periods_per_day, null));
    }

    // 2. Prepare Lessons
    $lessons_to_schedule = [];
    $electives_by_group_grade = [];

    foreach ($data['workloads'] as $workload) {
        if (empty($workload['elective_group_id'])) { // Regular lesson
            for ($i = 0; $i < $workload['lessons_per_week']; $i++) {
                $is_double = ($i == 0 && $workload['has_double_lesson'] && $workload['lessons_per_week'] >= 2);
                $lessons_to_schedule[] = [
                    'type' => 'single',
                    'class_id' => $workload['class_id'],
                    'subject_id' => $workload['subject_id'],
                    'teacher_ids' => [$workload['teacher_id']],
                    'display_name' => $workload['subject_name'],
                    'teacher_name' => $workload['teacher_name'],
                    'is_double' => $is_double,
                    'is_elective' => false
                ];
                if ($is_double) $i++;
            }
        } else { // Elective lesson part
            $grade = preg_replace('/[^0-9]/', '', $workload['class_name']);
            $key = $workload['elective_group_id'] . '_grade_' . $grade;
            if (!isset($electives_by_group_grade[$key])) {
                $electives_by_group_grade[$key] = [
                    'type' => 'elective_group',
                    'display_name' => $workload['elective_group_name'] . " (Form " . $grade . ")",
                    'lessons_per_week' => $workload['lessons_per_week'],
                    'has_double_lesson' => $workload['has_double_lesson'],
                    'is_elective' => true,
                    'component_lessons' => []
                ];
            }
            $electives_by_group_grade[$key]['component_lessons'][] = $workload;
        }
    }

    foreach ($electives_by_group_grade as $group) {
        for ($i = 0; $i < $group['lessons_per_week']; $i++) {
            $is_double = ($i == 0 && $group['has_double_lesson'] && $group['lessons_per_week'] >= 2);
            $class_ids = array_unique(array_column($group['component_lessons'], 'class_id'));
            $teacher_ids = array_unique(array_column($group['component_lessons'], 'teacher_id'));
            
            $lessons_to_schedule[] = [
                'type' => 'elective_group',
                'class_id' => $class_ids, // Now an array of classes
                'subject_id' => null, // Grouped subject
                'teacher_ids' => $teacher_ids,
                'display_name' => $group['display_name'],
                'is_double' => $is_double,
                'is_elective' => true,
                'component_lessons' => $group['component_lessons']
            ];
            if ($is_double) $i++;
        }
    }
    
    // 3. Sort lessons (place doubles and electives first)
    usort($lessons_to_schedule, function($a, $b) {
        if ($b['is_double'] != $a['is_double']) return $b['is_double'] <=> $a['is_double'];
        $a_count = is_array($a['class_id']) ? count($a['class_id']) : 1;
        $b_count = is_array($b['class_id']) ? count($b['class_id']) : 1;
        return $b_count <=> $a_count;
    });

    // 4. Placement
    foreach ($lessons_to_schedule as $lesson) {
        $best_slot = find_best_slot_for_lesson($lesson, $class_timetables, $teacher_timetables, $days_of_week, $periods_per_day);

        if ($best_slot) {
            $day = $best_slot['day'];
            $period = $best_slot['period'];
            
            if ($lesson['type'] === 'single') {
                $class_id = $lesson['class_id'];
                $teacher_id = $lesson['teacher_ids'][0];
                
                $lesson_data = [
                    'subject_name' => $lesson['display_name'],
                    'teacher_name' => $lesson['teacher_name'],
                    'subject_id' => $lesson['subject_id'],
                    'teacher_ids' => $lesson['teacher_ids'],
                    'is_double' => $lesson['is_double'],
                    'is_elective' => false,
                ];

                $class_timetables[$class_id][$day][$period] = $lesson_data;
                $teacher_timetables[$teacher_id][$day][$period] = true;
                if ($lesson['is_double']) {
                    $class_timetables[$class_id][$day][$period + 1] = $lesson_data;
                    $teacher_timetables[$teacher_id][$day][$period + 1] = true;
                }
            } else { // Elective Group
                foreach($lesson['component_lessons'] as $comp_lesson) {
                    $class_id = $comp_lesson['class_id'];
                    $teacher_id = $comp_lesson['teacher_id'];

                    $lesson_data = [
                        'subject_name' => $comp_lesson['subject_name'],
                        'teacher_name' => $comp_lesson['teacher_name'],
                        'subject_id' => $comp_lesson['subject_id'],
                        'teacher_ids' => [$teacher_id], // Specific teacher for this part
                        'is_double' => $lesson['is_double'],
                        'is_elective' => true,
                        'group_name' => $lesson['display_name']
                    ];

                    $class_timetables[$class_id][$day][$period] = $lesson_data;
                    $teacher_timetables[$teacher_id][$day][$period] = true;
                    if ($lesson['is_double']) {
                        $class_timetables[$class_id][$day][$period + 1] = $lesson_data;
                        $teacher_timetables[$teacher_id][$day][$period + 1] = true;
                    }
                }
            }
        }
    }

    return $class_timetables;
}

function find_best_slot_for_lesson($lesson, &$class_timetables, &$teacher_timetables, $days_of_week, $periods_per_day) {
    $is_double = $lesson['is_double'];
    $class_ids = is_array($lesson['class_id']) ? $lesson['class_id'] : [$lesson['class_id']];
    $teacher_ids = $lesson['teacher_ids'];

    for ($day = 0; $day < count($days_of_week); $day++) {
        for ($period = 0; $period < $periods_per_day; $period++) {
            if ($is_double && $period + 1 >= $periods_per_day) continue;

            // Check availability for all classes and teachers
            $slot_available = true;
            foreach ($class_ids as $cid) {
                if ($class_timetables[$cid][$day][$period] !== null || ($is_double && $class_timetables[$cid][$day][$period + 1] !== null)) {
                    $slot_available = false; break;
                }
            }
            if (!$slot_available) continue;

            foreach ($teacher_ids as $tid) {
                if ($teacher_timetables[$tid][$day][$period] !== null || ($is_double && $teacher_timetables[$tid][$day][$period + 1] !== null)) {
                    $slot_available = false; break;
                }
            }
            
            if ($slot_available) {
                return ['day' => $day, 'period' => $period]; // Return first available slot (simplistic)
            }
        }
    }
    return null; // No slot found
}


// --- Timetable Persistence ---
function save_timetable($pdo, $class_timetables, $timeslots) {
    try {
        $pdo->beginTransaction();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('TRUNCATE TABLE schedule_teachers');
        $pdo->exec('TRUNCATE TABLE schedules');
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        $stmt = $pdo->prepare(
            'INSERT INTO schedules (class_id, day_of_week, timeslot_id, subject_id, lesson_display_name, teacher_display_name, is_double, is_elective) ' .
            'VALUES (:class_id, :day_of_week, :timeslot_id, :subject_id, :lesson_display_name, :teacher_display_name, :is_double, :is_elective)'
        );
        $teacher_stmt = $pdo->prepare(
            'INSERT INTO schedule_teachers (schedule_id, teacher_id) VALUES (:schedule_id, :teacher_id)'
        );

        $lesson_periods = array_values(array_filter($timeslots, function($ts) { return !$ts['is_break']; }));

        foreach ($class_timetables as $class_id => $day_schedule) {
            foreach ($day_schedule as $day_idx => $period_schedule) {
                $processed_periods = [];
                foreach ($period_schedule as $period_idx => $lesson) {
                    if ($lesson && !in_array($period_idx, $processed_periods)) {
                        $timeslot_id = $lesson_periods[$period_idx]['id'];
                        
                        $display_name = $lesson['is_elective'] ? ($lesson['group_name'] . ' / ' . $lesson['subject_name']) : $lesson['subject_name'];

                        $stmt->execute([
                            ':class_id' => $class_id,
                            ':day_of_week' => $day_idx,
                            ':timeslot_id' => $timeslot_id,
                            ':subject_id' => $lesson['subject_id'],
                            ':lesson_display_name' => $display_name,
                            ':teacher_display_name' => $lesson['teacher_name'],
                            ':is_double' => (int)$lesson['is_double'],
                            ':is_elective' => (int)$lesson['is_elective']
                        ]);

                        $schedule_id = $pdo->lastInsertId();

                        foreach ($lesson['teacher_ids'] as $teacher_id) {
                            $teacher_stmt->execute([':schedule_id' => $schedule_id, ':teacher_id' => $teacher_id]);
                        }

                        $processed_periods[] = $period_idx;
                        if ($lesson['is_double']) {
                            $processed_periods[] = $period_idx + 1;
                        }
                    }
                }
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Timetable save failed: " . $e->getMessage());
    }
}

function get_timetable_from_db($pdo, $classes, $timeslots) {
    $stmt = $pdo->query('SELECT * FROM schedules ORDER BY id');
    $saved_lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($saved_lessons)) return [];

    $days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $periods = array_values(array_filter($timeslots, function($ts) { return !$ts['is_break']; }));
    $periods_per_day = count($periods);

    $class_timetables = [];
    foreach ($classes as $class) {
        $class_timetables[$class['id']] = array_fill(0, count($days_of_week), array_fill(0, $periods_per_day, null));
    }

    $timeslot_id_to_period_idx = [];
    foreach($periods as $idx => $period) {
        $timeslot_id_to_period_idx[$period['id']] = $idx;
    }

    foreach ($saved_lessons as $lesson) {
        $class_id = $lesson['class_id'];
        $day_idx = $lesson['day_of_week'];
        $timeslot_id = $lesson['timeslot_id'];

        if (!isset($timeslot_id_to_period_idx[$timeslot_id]) || !isset($class_timetables[$class_id])) continue;
        
        $period_idx = $timeslot_id_to_period_idx[$timeslot_id];

        $lesson_data = [
            'id' => $lesson['id'],
            'subject_name' => $lesson['lesson_display_name'],
            'teacher_name' => $lesson['teacher_display_name'],
            'is_double' => (bool)$lesson['is_double'],
            'is_elective' => (bool)$lesson['is_elective'],
        ];

        $class_timetables[$class_id][$day_idx][$period_idx] = $lesson_data;
        if ($lesson_data['is_double'] && isset($class_timetables[$class_id][$day_idx][$period_idx + 1])) {
            $class_timetables[$class_id][$day_idx][$period_idx + 1] = $lesson_data;
        }
    }
    return $class_timetables;
}

// --- Main Logic ---
$pdoconn = db();
$all_data = get_all_data($pdoconn);
$classes = $all_data['classes'];
$timeslots = $all_data['timeslots'];
$workloads = $all_data['workloads'];

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$class_timetables = [];

if (isset($_POST['generate'])) {
    if (!empty($workloads)) {
        $class_timetables = generate_timetable($all_data, $days_of_week);
        save_timetable($pdoconn, $class_timetables, $timeslots);
    }
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
    <style>
        .lesson.is-elective { background-color: #e0f7fa; border-left: 3px solid #00bcd4; }
        .lesson.is-double { background-color: #fce4ec; }
        @media print {
            body * { visibility: hidden; }
            #timetables-container, #timetables-container * { visibility: visible; }
            #timetables-container { position: absolute; left: 0; top: 0; width: 100%; }
            .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Class Timetable</h1>
            <div class="d-flex gap-2">
                <form method="POST" action="">
                    <button type="submit" name="generate" class="btn btn-primary" <?php echo empty($workloads) ? 'disabled' : ''; ?>>Generate Timetable</button>
                </form>
                <button id="print-btn" class="btn btn-secondary">Print</button>
            </div>
        </div>

        <div id="timetables-container">
            <?php if (!empty($class_timetables)) : ?>
                <?php foreach ($classes as $class) : ?>
                    <?php if (!isset($class_timetables[$class['id']])) continue; ?>
                    <div class="timetable-wrapper mb-5">
                        <h3 class="mt-5"><?php echo htmlspecialchars($class['name']); ?></h3>
                        <div class="card">
                            <div class="card-body">
                                <table class="table table-bordered text-center">
                                    <thead>
                                        <tr>
                                            <th style="width: 12%;">Time</th>
                                            <?php foreach ($days_of_week as $day) : ?>
                                                <th><?php echo $day; ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $period_idx = 0;
                                        foreach ($timeslots as $timeslot) :
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($timeslot['name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo date("g:i A", strtotime($timeslot['start_time'])); ?> - <?php echo date("g:i A", strtotime($timeslot['end_time'])); ?></small>
                                                </td>
                                                <?php if ($timeslot['is_break']) : ?>
                                                    <td colspan="<?php echo count($days_of_week); ?>" class="text-center table-secondary"><strong>Break</strong></td>
                                                <?php else : ?>
                                                    <?php for ($day_idx = 0; $day_idx < count($days_of_week); $day_idx++) : ?>
                                                        <td class="timetable-slot">
                                                            <?php
                                                            $lesson = $class_timetables[$class['id']][$day_idx][$period_idx] ?? null;
                                                            if ($lesson) :
                                                                $css_class = 'lesson p-1';
                                                                if ($lesson['is_elective']) $css_class .= ' is-elective';
                                                                if ($lesson['is_double']) $css_class .= ' is-double';
                                                            ?>
                                                                <div class="<?php echo $css_class; ?>" data-lesson-id="<?php echo $lesson['id'] ?? ''; ?>">
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
            <?php else : ?>
                <div class="alert alert-info">
                    <?php if (empty($workloads)) : ?>
                        No workloads found. Please add classes, subjects, teachers, and workloads in the "Manage" section first. The "Generate Timetable" button is disabled.
                    <?php else : ?>
                        Click the "Generate Timetable" button to create a schedule.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('print-btn').addEventListener('click', function () {
            window.print();
        });
    });
    </script>
</body>
</html>
