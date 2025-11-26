CREATE TABLE IF NOT EXISTS `schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT NOT NULL,
    `day_of_week` INT NOT NULL,
    `timeslot_id` INT NOT NULL,
    `subject_id` INT,
    `teacher_id` INT,
    `lesson_display_name` VARCHAR(255) NOT NULL,
    `teacher_display_name` VARCHAR(255) NOT NULL,
    `is_double` BOOLEAN DEFAULT FALSE,
    `is_elective` BOOLEAN DEFAULT FALSE,
    `is_horizontal_elective` BOOLEAN DEFAULT FALSE,
    UNIQUE KEY `unique_schedule_entry` (`class_id`, `day_of_week`, `timeslot_id`),
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;