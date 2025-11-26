-- Add school_id and role to users table
ALTER TABLE `users`
ADD COLUMN `school_id` INT(11) NULL,
ADD COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'teacher';

-- Add workload_editable to users table
ALTER TABLE `users`
ADD COLUMN `workload_editable` BOOLEAN NOT NULL DEFAULT FALSE;

-- Add school_id to other tables
ALTER TABLE `classes` ADD COLUMN `school_id` INT(11) NULL;
ALTER TABLE `subjects` ADD COLUMN `school_id` INT(11) NULL;
ALTER TABLE `teachers` ADD COLUMN `school_id` INT(11) NULL;
ALTER TABLE `workloads` ADD COLUMN `school_id` INT(11) NULL;
ALTER TABLE `schedules` ADD COLUMN `school_id` INT(11) NULL;

-- Add foreign key constraints
ALTER TABLE `users` ADD CONSTRAINT `fk_users_school_id` FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `classes` ADD CONSTRAINT `fk_classes_school_id` FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `subjects` ADD CONSTRAINT `fk_subjects_school_id` FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `teachers` ADD CONSTRAINT `fk_teachers_school_id` FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `workloads` ADD CONSTRAINT `fk_workloads_school_id` FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `schedules` ADD CONSTRAINT `fk_schedules_school_id` FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
