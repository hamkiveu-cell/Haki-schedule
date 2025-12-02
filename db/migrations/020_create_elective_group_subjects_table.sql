CREATE TABLE IF NOT EXISTS `elective_group_subjects` (
  `elective_group_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  PRIMARY KEY (`elective_group_id`, `subject_id`),
  FOREIGN KEY (`elective_group_id`) REFERENCES `elective_groups`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;