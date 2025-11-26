CREATE TABLE IF NOT EXISTS `elective_groups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `school_id` INT NOT NULL,
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `subjects` ADD COLUMN `elective_group_id` INT DEFAULT NULL,
ADD FOREIGN KEY (`elective_group_id`) REFERENCES `elective_groups`(`id`) ON DELETE SET NULL;
