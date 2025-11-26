CREATE TABLE IF NOT EXISTS `schedules` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `class_id` INT NOT NULL,
  `timeslot_id` INT NOT NULL,
  `teacher_id` INT,
  `subject_id` INT,
  `subject_name_override` VARCHAR(255),
  `school_id` INT,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`),
  FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots`(`id`),
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`),
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`),
  CONSTRAINT `fk_schedules_school_id` FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
