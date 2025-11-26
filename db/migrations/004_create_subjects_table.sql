CREATE TABLE IF NOT EXISTS `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `is_elective` tinyint(1) DEFAULT '0',
  `has_double_lesson` tinyint(1) DEFAULT '0',
  `school_id` INT,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_subjects_school_id` FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
