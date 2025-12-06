ALTER TABLE `teachers`
ADD COLUMN `can_edit_workload` BOOLEAN NOT NULL DEFAULT 0 COMMENT 'If true, the teacher can edit their own workload';