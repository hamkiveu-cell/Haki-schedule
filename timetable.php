<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/db/config.php';

// --- Timetable Generation Logic V3 (with Backtracking) ---

class Scheduler
{
    // Settings
    private const DAYS_OF_WEEK = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    private const PERIODS_PER_DAY = 8;
    private const BREAK_PERIODS = [2, 5];

    // Data
    private $classes;
    private $teachers;
    private $workloads;

    // State
    private $class_timetables;
    private $teacher_timetables;
    private $unplaced_lessons = [];
    private $lessons_to_schedule;

    public function __construct($classes, $teachers, $workloads)
    {
        $this->classes = $classes;
        $this->teachers = $teachers;
        $this->workloads = $workloads;

        // Initialize empty timetables
        foreach ($this->classes as $class) {
            $this->class_timetables[$class['id']] = array_fill(0, count(self::DAYS_OF_WEEK), array_fill(0, self::PERIODS_PER_DAY, null));
        }
        foreach ($this->teachers as $teacher) {
            $this->teacher_timetables[$teacher['id']] = array_fill(0, count(self::DAYS_OF_WEEK), array_fill(0, self::PERIODS_PER_DAY, null));
        }
    }

    public function generate()
    {
        $this->lessons_to_schedule = $this->prepare_lessons();
        $this->solve($this->lessons_to_schedule); // Run the solver

        return [
            // The process always "succeeds" in running. Check unplaced_lessons for timetable completeness.
            'success' => true,
            'class_timetables' => $this->class_timetables,
            'unplaced_lessons' => $this->unplaced_lessons,
        ];
    }

    private function prepare_lessons()
    {
        $lessons = [];
        $electives = [];

        // Group electives
        foreach ($this->workloads as $workload) {
            if ($workload['elective_group']) {
                $electives[$workload['elective_group']][] = $workload;
            }
        }

        // Create elective lesson blocks
        foreach ($electives as $group_name => $group_workloads) {
            for ($i = 0; $i < $group_workloads[0]['lessons_per_week']; $i++) {
                $lessons[] = [
                    'is_elective' => true,
                    'group_name' => $group_name,
                    'workloads' => $group_workloads,
                    'subject_name' => implode(' / ', array_map(fn($w) => $w['subject_name'], $group_workloads)),
                    'teacher_name' => 'Elective Group',
                    'subject_id' => $group_workloads[0]['subject_id'] // For color
                ];
            }
        }

        // Create double and single lessons
        foreach ($this->workloads as $workload) {
            if ($workload['elective_group']) continue;

            $lessons_per_week = $workload['lessons_per_week'];
            if ($workload['has_double_lesson']) {
                if ($lessons_per_week >= 2) {
                    $lessons[] = array_merge($workload, ['is_double' => true, 'duration' => 2]);
                    $lessons_per_week -= 2;
                }
            }
            for ($i = 0; $i < $lessons_per_week; $i++) {
                $lessons[] = array_merge($workload, ['is_double' => false, 'duration' => 1]);
            }
        }
        
        // Sort lessons to prioritize more constrained ones (doubles and electives)
        usort($lessons, function($a, $b) {
            $a_score = ($a['is_elective'] ?? false) * 10 + ($a['is_double'] ?? false) * 5;
            $b_score = ($b['is_elective'] ?? false) * 10 + ($b['is_double'] ?? false) * 5;
            return $b_score <=> $a_score;
        });

        return $lessons;
    }

    private function solve(&$lessons)
    {
        if (empty($lessons)) {
            return true; // Success: all lessons have been scheduled
        }

        $lesson = array_pop($lessons); // Get the next lesson to try placing
        $possible_slots = $this->get_possible_slots($lesson);
        shuffle($possible_slots);

        foreach ($possible_slots as $slot) {
            $this->place_lesson($lesson, $slot);

            if ($this->solve($lessons)) {
                return true; // Found a valid solution for the rest of the lessons
            }

            // Backtrack
            $this->unplace_lesson($lesson, $slot);
        }

        // If we get here, no slot worked for the current lesson.
        $this->unplaced_lessons[] = $lesson; // Record as unplaced
        
        // BUG FIX: The line below caused an infinite recursion and server crash.
        // It has been removed. By returning false, we signal the parent to backtrack.
        return false;
    }

    private function get_possible_slots($lesson)
    {
        $slots = [];
        for ($day = 0; $day < count(self::DAYS_OF_WEEK); $day++) {
            for ($period = 0; $period < self::PERIODS_PER_DAY; $period++) {
                $slot = ['day' => $day, 'period' => $period];
                if ($this->is_slot_valid($lesson, $slot)) {
                    $slots[] = $slot;
                }
            }
        }
        return $slots;
    }

    private function is_slot_valid($lesson, $slot)
    {
        $day = $slot['day'];
        $start_period = $slot['period'];
        $duration = $lesson['duration'] ?? 1;

        // Check if slot is a break
        for ($p = 0; $p < $duration; $p++) {
            $current_period = $start_period + $p;
            if ($current_period >= self::PERIODS_PER_DAY || in_array($current_period, self::BREAK_PERIODS)) {
                return false; // Slot is out of bounds or a break
            }
        }

        if ($lesson['is_elective']) {
            // Check all classes and teachers in the elective group
            foreach ($lesson['workloads'] as $workload) {
                if (!$this->check_resource_availability($workload['class_id'], $workload['teacher_id'], $day, $start_period, $duration)) {
                    return false;
                }
            }
        } else {
            // Check for single/double lesson
            if (!$this->check_resource_availability($lesson['class_id'], $lesson['teacher_id'], $day, $start_period, $duration)) {
                return false;
            }
            // ** STRICT DISTRIBUTION RULE **
            $lessons_on_day = 0;
            foreach ($this->class_timetables[$lesson['class_id']][$day] as $p) {
                if ($p && $p['subject_id'] === $lesson['subject_id']) {
                    $lessons_on_day++;
                }
            }
            $max_per_day = ($lesson['lessons_per_week'] > count(self::DAYS_OF_WEEK)) ? 2 : 1;
            if ($lessons_on_day >= $max_per_day) {
                return false;
            }
        }
        return true;
    }

    private function check_resource_availability($class_id, $teacher_id, $day, $start_period, $duration)
    {
        for ($p = 0; $p < $duration; $p++) {
            $period = $start_period + $p;
            if (!empty($this->class_timetables[$class_id][$day][$period]) || !empty($this->teacher_timetables[$teacher_id][$day][$period])) {
                return false; // Conflict found
            }
        }
        return true;
    }

    private function place_lesson($lesson, $slot)
    {
        $day = $slot['day'];
        $start_period = $slot['period'];
        $duration = $lesson['duration'] ?? 1;

        $lesson_info = [
            'subject' => $lesson['subject_name'],
            'teacher' => $lesson['teacher_name'],
            'subject_id' => $lesson['subject_id'],
        ];

        if ($lesson['is_elective']) {
            foreach ($lesson['workloads'] as $workload) {
                for ($p = 0; $p < $duration; $p++) {
                    $this->class_timetables[$workload['class_id']][$day][$start_period + $p] = $lesson_info;
                    $this->teacher_timetables[$workload['teacher_id']][$day][$start_period + $p] = $workload['class_id'];
                }
            }
        } else {
            for ($p = 0; $p < $duration; $p++) {
                $this->class_timetables[$lesson['class_id']][$day][$start_period + $p] = $lesson_info;
                $this->teacher_timetables[$lesson['teacher_id']][$day][$start_period + $p] = $lesson['class_id'];
            }
        }
    }

    private function unplace_lesson($lesson, $slot)
    {
        $day = $slot['day'];
        $start_period = $slot['period'];
        $duration = $lesson['duration'] ?? 1;

        if ($lesson['is_elective']) {
            foreach ($lesson['workloads'] as $workload) {
                for ($p = 0; $p < $duration; $p++) {
                    $this->class_timetables[$workload['class_id']][$day][$start_period + $p] = null;
                    $this->teacher_timetables[$workload['teacher_id']][$day][$start_period + $p] = null;
                }
            }
        } else {
            for ($p = 0; $p < $duration; $p++) {
                $this->class_timetables[$lesson['class_id']][$day][$start_period + $p] = null;
                $this->teacher_timetables[$lesson['teacher_id']][$day][$start_period + $p] = null;
            }
        }
    }
}

// --- Data Fetching ---
$pdo = db();
$classes = $pdo->query("SELECT * FROM classes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $pdo->query("SELECT * FROM teachers")->fetchAll(PDO::FETCH_ASSOC);
$workloads = $pdo->query("
    SELECT 
        w.class_id, c.name as class_name, 
        w.subject_id, s.name as subject_name, s.has_double_lesson, s.elective_group,
        w.teacher_id, t.name as teacher_name, 
        w.lessons_per_week
    FROM workloads w
    JOIN classes c ON w.class_id = c.id
    JOIN subjects s ON w.subject_id = s.id
    JOIN teachers t ON w.teacher_id = t.id
")->fetchAll(PDO::FETCH_ASSOC);

// --- Run Scheduler ---
$scheduler = new Scheduler($classes, $teachers, $workloads);
$result = $scheduler->generate();
$class_timetables = $result['class_timetables'];
$raw_unplaced_lessons = $result['unplaced_lessons'];

// Process unplaced lessons for display
$unplaced_lessons_summary = [];
foreach($raw_unplaced_lessons as $lesson) {
    $key = $lesson['is_elective'] ? $lesson['group_name'] : $lesson['class_name'] . '-' . $lesson['subject_name'];
    if (!isset($unplaced_lessons_summary[$key])) {
        $unplaced_lessons_summary[$key] = [
            'class' => $lesson['is_elective'] ? 'All Classes' : $lesson['class_name'],
            'subject' => $lesson['is_elective'] ? "Elective: " . $lesson['group_name'] : $lesson['subject_name'],
            'unplaced' => 0,
            'total' => $lesson['is_elective'] ? $lesson['workloads'][0]['lessons_per_week'] : $lesson['lessons_per_week']
        ];
    }
    $unplaced_lessons_summary[$key]['unplaced']++;
}

// --- Color Helper ---
$subject_colors = [];
$color_palette = ['#FFADAD', '#FFD6A5', '#FDFFB6', '#CAFFBF', '#9BF6FF', '#A0C4FF', '#BDB2FF', '#FFC6FF', '#FFC8DD', '#D4A5A5'];
function get_subject_color($subject_name, &$subject_colors, $palette) {
    if (!isset($subject_colors[$subject_name])) {
        $subject_colors[$subject_name] = $palette[count($subject_colors) % count($palette)];
    }
    return $subject_colors[$subject_name];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generated Timetable - Haki Schedule</title>
    <meta name="description" content="View the automatically generated class schedule with a modern, color-coded design.">
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

    <main class="container py-5">
        <div class="printable-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold">Generated Timetable</h1>
                    <p class="text-muted">Automatically generated, color-coded class schedules.</p>
                </div>
                <button onclick="window.print();" class="btn btn-primary d-print-none"><i class="bi bi-printer-fill me-2"></i>Print Timetable</button>
            </div>

            <?php if (!empty($unplaced_lessons_summary)): ?>
            <div class="alert alert-warning">
                <h5 class="alert-heading">Scheduling Conflict</h5>
                <p>The system could not place all lessons. This is likely due to too many constraints (e.g., not enough available slots for a teacher). Please review workloads and constraints.</p>
                <ul>
                    <?php foreach ($unplaced_lessons_summary as $item): ?>
                        <li>Could not place <?php echo $item['unplaced']; ?> of <?php echo $item['total']; ?> lessons for <strong><?php echo htmlspecialchars($item['subject']); ?></strong> in class <strong><?php echo htmlspecialchars($item['class']); ?></strong>.</li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (empty($classes)): ?>
                <div class="alert alert-info">Please <a href="admin_classes.php">create a class</a> and <a href="admin_workloads.php">assign workloads</a> to generate a timetable.</div>
            <?php else: ?>
                <ul class="nav nav-tabs nav-fill mb-4 d-print-none" id="classTabs" role="tablist">
                    <?php foreach ($classes as $index => $class): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $index === 0 ? 'active' : ''; ?>" id="tab-<?php echo $class['id']; ?>" data-bs-toggle="tab" data-bs-target="#content-<?php echo $class['id']; ?>" type="button" role="tab"><?php echo htmlspecialchars($class['name']); ?></button>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="tab-content" id="classTabsContent">
                    <?php foreach ($classes as $index => $class): ?>
                        <div class="tab-pane fade <?php echo $index === 0 ? 'show active' : ''; ?>" id="content-<?php echo $class['id']; ?>" role="tabpanel">
                            <h4 class="d-none d-print-block mb-3 text-center">Timetable for <?php echo htmlspecialchars($class['name']); ?></h4>
                            <div class="table-responsive">
                                <table class="table table-bordered timetable-table text-center">
                                    <thead>
                                        <tr class="table-light">
                                            <th class="time-col">Time</th>
                                            <?php foreach (self::DAYS_OF_WEEK as $day): ?>
                                                <th><?php echo $day; ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php for ($period = 0; $period < self::PERIODS_PER_DAY; $period++): ?>
                                            <?php if (in_array($period, self::BREAK_PERIODS)): ?>
                                                <tr>
                                                    <td class="time-col table-light"><strong><?php echo $period === 2 ? 'Break' : 'Lunch'; ?></strong></td>
                                                    <td class="break-cell" colspan="<?php echo count(self::DAYS_OF_WEEK); ?>"></td>
                                                </tr>
                                            <?php else: ?>
                                                <tr>
                                                    <td class="time-col table-light"><?php echo sprintf('%02d:00 - %02d:00', 8 + $period + ($period > 2 ? 1 : 0) - ($period > 5 ? 1 : 0), 9 + $period + ($period > 2 ? 1 : 0) - ($period > 5 ? 1 : 0)); ?></td>
                                                    <?php for ($day = 0; $day < count(self::DAYS_OF_WEEK); $day++): ?>
                                                        <td class="timetable-cell-container">
                                                            <?php if (!empty($class_timetables[$class['id']][$day][$period])): 
                                                                $lesson = $class_timetables[$class['id']][$day][$period];
                                                                $color = get_subject_color($lesson['subject'], $subject_colors, $color_palette);
                                                            ?>
                                                                <div class="timetable-cell" style="background-color: <?php echo $color; ?>;">
                                                                    <div class="subject-name"><?php echo htmlspecialchars($lesson['subject']); ?></div>
                                                                    <div class="teacher-name"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($lesson['teacher']); ?></div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endfor; ?>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-dark text-white py-4 mt-auto">
        <div class="container text-center">
            <p>&copy; <?php echo date("Y"); ?> Haki Schedule. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
