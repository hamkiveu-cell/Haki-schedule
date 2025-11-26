<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'db/config.php';

// --- Configuration ---
define('DAYS_OF_WEEK', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']);
define('PERIODS_PER_DAY', 8);

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
            s.elective_group,
            t.id as teacher_id,
            t.name as teacher_name,
            w.lessons_per_week
        FROM workloads w
        JOIN classes c ON w.class_id = c.id
        JOIN subjects s ON w.subject_id = s.id
        JOIN teachers t ON w.teacher_id = t.id
        ORDER BY c.name, s.name
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_classes($pdo) {
    return $pdo->query("SELECT * FROM classes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

// --- Helper Functions ---
function get_grade_from_class_name($class_name) {
    if (preg_match('/^(Grade\s+\d+)/i', $class_name, $matches)) {
        return $matches[1];
    }
    return $class_name;
}

// --- Scoring and Placement Logic ---
function find_best_slot_for_lesson($lesson, $is_double, &$class_timetables, &$teacher_timetables, $all_class_ids) {
    $best_slot = null;
    $highest_score = -1;

    $class_id = $lesson['type'] === 'horizontal_elective' ? null : $lesson['class_id'];
    $teachers_in_lesson = array_unique(array_column($lesson['component_lessons'], 'teacher_id'));
    $class_ids_in_lesson = $lesson['type'] === 'horizontal_elective' ? $lesson['participating_class_ids'] : [$class_id];

    for ($day = 0; $day < count(DAYS_OF_WEEK); $day++) {
        for ($period = 0; $period < PERIODS_PER_DAY; $period++) {
            $current_score = 100; // Base score for a valid slot

            // 1. Check basic availability
            $slot_available = true;
            if ($is_double) {
                if ($period + 1 >= PERIODS_PER_DAY) continue; // Not enough space for a double
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

            // 2. Apply scoring rules for even distribution
            foreach ($class_ids_in_lesson as $cid) {
                // Penalty for same subject on the same day
                for ($p = 0; $p < PERIODS_PER_DAY; $p++) {
                    if (isset($class_timetables[$cid][$day][$p]) && $class_timetables[$cid][$day][$p]['subject_name'] === $lesson['display_name']) {
                        $current_score -= 50;
                    }
                }
            }

            // Penalty for teacher back-to-back lessons
            foreach ($teachers_in_lesson as $teacher_id) {
                // Check period before
                if ($period > 0 && isset($teacher_timetables[$teacher_id][$day][$period - 1])) {
                    $current_score -= 15;
                }
                // Check period after
                $after_period = $is_double ? $period + 2 : $period + 1;
                if ($after_period < PERIODS_PER_DAY && isset($teacher_timetables[$teacher_id][$day][$after_period])) {
                    $current_score -= 15;
                }
            }

            // 3. New Penalty: Avoid scheduling double lessons of the same subject at the same time (resource conflict)
            if ($is_double) {
                $subject_name_to_check = $lesson['display_name'];
                foreach ($all_class_ids as $cid) {
                    // Skip the classes that are part of the current lesson being placed
                    if (in_array($cid, $class_ids_in_lesson)) {
                        continue;
                    }

                    if (isset($class_timetables[$cid][$day][$period])) {
                        $conflicting_lesson = $class_timetables[$cid][$day][$period];
                        // Check if it's a double lesson of the same subject.
                        if ($conflicting_lesson['is_double'] && $conflicting_lesson['subject_name'] === $subject_name_to_check) {
                            $current_score -= 500; // High penalty for resource conflict
                            break; // Found a conflict, no need to check other classes
                        }
                    }
                }
            }

            // 4. Compare with the highest score
            if ($current_score > $highest_score) {
                $highest_score = $current_score;
                $best_slot = ['day' => $day, 'period' => $period];
            }
        }
    }
    return $best_slot;
}


// --- Main Scheduling Engine ---
function generate_timetable($workloads, $classes) {
    $class_timetables = [];
    foreach ($classes as $class) {
        $class_timetables[$class['id']] = array_fill(0, count(DAYS_OF_WEEK), array_fill(0, PERIODS_PER_DAY, null));
    }
    $teacher_timetables = [];

    // --- Lesson Preparation ---
    $classes_by_grade = [];
    foreach ($classes as $class) {
        $grade_name = get_grade_from_class_name($class['name']);
        $classes_by_grade[$grade_name][] = $class;
    }

    // Step 1: Identify horizontal electives and separate them from other workloads
    $horizontal_elective_doubles = [];
    $horizontal_elective_singles = [];
    $other_workloads = [];
    $workloads_by_grade_and_elective_group = [];

    foreach ($workloads as $workload) {
        if (!empty($workload['elective_group'])) {
            $grade_name = get_grade_from_class_name($workload['class_name']);
            $workloads_by_grade_and_elective_group[$grade_name][$workload['elective_group']][] = $workload;
        } else {
            $other_workloads[] = $workload;
        }
    }

    foreach ($workloads_by_grade_and_elective_group as $grade_name => $elective_groups) {
        foreach ($elective_groups as $elective_group_name => $group_workloads) {
            $participating_class_ids = array_unique(array_column($group_workloads, 'class_id'));
            // A horizontal elective involves more than one class
            if (count($participating_class_ids) > 1) {
                $first = $group_workloads[0];
                $block = [
                    'type' => 'horizontal_elective',
                    'display_name' => $elective_group_name,
                    'participating_class_ids' => $participating_class_ids,
                    'lessons_per_week' => $first['lessons_per_week'],
                    'has_double_lesson' => $first['has_double_lesson'],
                    'component_lessons' => $group_workloads
                ];

                if ($block['has_double_lesson'] && $block['lessons_per_week'] >= 2) {
                    $horizontal_elective_doubles[] = $block; // Create one double lesson
                    // Create remaining as single lessons
                    for ($i = 0; $i < $block['lessons_per_week'] - 2; $i++) {
                        $horizontal_elective_singles[] = $block;
                    }
                } else {
                    // Create all as single lessons
                    for ($i = 0; $i < $block['lessons_per_week']; $i++) {
                        $horizontal_elective_singles[] = $block;
                    }
                }
            } else {
                // Not a horizontal elective, add back to the pool of other workloads
                foreach($group_workloads as $workload) {
                    $other_workloads[] = $workload;
                }
            }
        }
    }
    
    // Step 2: Process remaining workloads (regular subjects and single-class electives)
    $double_lessons = [];
    $single_lessons = [];
    $workloads_by_class = [];
    foreach ($other_workloads as $workload) {
        $workloads_by_class[$workload['class_id']][] = $workload;
    }

    foreach ($workloads_by_class as $class_id => $class_workloads) {
        $elective_groups = [];
        $individual_lessons = [];
        // Separate single-class electives from individual subjects
        foreach ($class_workloads as $workload) {
            if (!empty($workload['elective_group'])) {
                $elective_groups[$workload['elective_group']][] = $workload;
            } else {
                $individual_lessons[] = $workload;
            }
        }

        // Process single-class elective groups
        foreach ($elective_groups as $group_name => $group_workloads) {
            $first = $group_workloads[0];
            $block = [
                'type' => 'elective',
                'class_id' => $class_id,
                'display_name' => $group_name,
                'lessons_per_week' => $first['lessons_per_week'],
                'has_double_lesson' => $first['has_double_lesson'],
                'component_lessons' => $group_workloads
            ];
            
            if ($block['has_double_lesson'] && $block['lessons_per_week'] >= 2) {
                $double_lessons[] = $block; // One double lesson
                // Remaining are single lessons
                for ($i = 0; $i < $block['lessons_per_week'] - 2; $i++) {
                    $single_lessons[] = $block;
                }
            } else {
                // All are single lessons
                for ($i = 0; $i < $block['lessons_per_week']; $i++) {
                    $single_lessons[] = $block;
                }
            }
        }

        // Process individual subjects
        foreach ($individual_lessons as $workload) {
            $lesson = [
                'type' => 'single',
                'class_id' => $workload['class_id'],
                'display_name' => $workload['subject_name'],
                'lessons_per_week' => $workload['lessons_per_week'],
                'has_double_lesson' => $workload['has_double_lesson'],
                'component_lessons' => [$workload]
            ];

            if ($lesson['has_double_lesson'] && $lesson['lessons_per_week'] >= 2) {
                $double_lessons[] = $lesson; // One double lesson
                // Remaining are single lessons
                for ($i = 0; $i < $lesson['lessons_per_week'] - 2; $i++) {
                    $single_lessons[] = $lesson;
                }
            } else {
                // All are single lessons
                for ($i = 0; $i < $lesson['lessons_per_week']; $i++) {
                    $single_lessons[] = $lesson;
                }
            }
        }
    }

    // --- Placement using Scoring ---
    $all_class_ids = array_column($classes, 'id'); // Get all class IDs for the conflict check

    // The order determines priority: most constrained lessons are scheduled first.
    $all_lessons_in_order = [
        'horizontal_doubles' => $horizontal_elective_doubles,
        'doubles' => $double_lessons,
        'horizontal_singles' => $horizontal_elective_singles,
        'singles' => $single_lessons
    ];

    foreach ($all_lessons_in_order as $type => $lessons) {
        shuffle($lessons); // Randomize order within the same type to avoid bias
        foreach ($lessons as $lesson) {
            $is_double = ($type === 'doubles' || $type === 'horizontal_doubles');
            
            $best_slot = find_best_slot_for_lesson($lesson, $is_double, $class_timetables, $teacher_timetables, $all_class_ids);

            if ($best_slot) {
                $day = $best_slot['day'];
                $period = $best_slot['period'];
                $class_ids_to_place = ($lesson['type'] === 'horizontal_elective') ? $lesson['participating_class_ids'] : [$lesson['class_id']];
                $teachers_to_place = array_unique(array_column($lesson['component_lessons'], 'teacher_id'));

                $lesson_info = [
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
                    foreach ($class_ids_to_place as $cid) {
                        $class_timetables[$cid][$day][$period] = $lesson_info;
                    }
                    foreach ($teachers_to_place as $tid) {
                        $teacher_timetables[$tid][$day][$period] = true;
                    }
                }
            }
        }
    }

    return $class_timetables;
}

$pdoconn = db();
$workloads = get_workloads($pdoconn);
$classes = get_classes($pdoconn);
$class_timetables = [];

if (isset($_POST['generate'])) {
    $class_timetables = generate_timetable($workloads, $classes);
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
                            </ul>
                        </li>
                        <li class="nav-item"><a class="nav-link active" href="/timetable.php">Timetable</a></li>
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
            <h1>Timetable</h1>
            <form method="POST" action="">
                <button type="submit" name="generate" class="btn btn-primary">Generate Timetable</button>
            </form>
        </div>

        <?php if (isset($_POST['generate'])): ?>
            <?php if (empty($workloads)): ?>
                 <div class="alert alert-warning">
                    No workloads found. Please add classes, subjects, teachers, and workloads in the "Manage" section first.
                </div>
            <?php else: ?>
                <?php foreach ($classes as $class): ?>
                    <h3 class="mt-5"><?php echo htmlspecialchars($class['name']); ?></h3>
                    <div class="card">
                        <div class="card-body">
                            <table class="table table-bordered text-center">
                                <thead>
                                    <tr>
                                        <th style="width: 12%;">Time</th>
                                        <?php foreach (DAYS_OF_WEEK as $day):
                                            ?><th><?php echo $day; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($period = 0; $period < PERIODS_PER_DAY; $period++):
                                        ?><tr>
                                            <td>Period <?php echo $period + 1; ?></td>
                                            <?php for ($day_idx = 0; $day_idx < count(DAYS_OF_WEEK); $day_idx++):
                                                ?><td>
                                                    <?php 
                                                    $lesson = $class_timetables[$class['id']][$day_idx][$period];
                                                    if ($lesson):
                                                        $class_str = '';
                                                        if ($lesson['is_horizontal_elective']) {
                                                            $class_str = 'bg-light-purple';
                                                        } elseif ($lesson['is_elective']) {
                                                            $class_str = 'bg-light-green';
                                                        } elseif ($lesson['is_double']) {
                                                            $class_str = 'bg-light-blue';
                                                        }
                                                    ?>
                                                        <div class="p-1 <?php echo $class_str; ?>">
                                                            <strong><?php echo htmlspecialchars($lesson['subject_name']); ?></strong><br>
                                                            <small><?php echo htmlspecialchars($lesson['teacher_name']); ?></small>
                                                        </div>
                                                    <?php else:
                                                        ?>&nbsp;
                                                    <?php endif; ?>
                                                </td>
                                            <?php endfor; ?>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else:
            ?><div class="alert alert-info">
                Click the "Generate Timetable" button to create a schedule based on the current workloads.
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
