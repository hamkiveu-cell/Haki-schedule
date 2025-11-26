ALTER TABLE `elective_groups` ADD UNIQUE `unique_school_name`(`school_id`, `name`);
ALTER TABLE `subjects` ADD UNIQUE `unique_school_name`(`school_id`, `name`);
ALTER TABLE `classes` ADD UNIQUE `unique_school_name`(`school_id`, `name`);
ALTER TABLE `teachers` ADD UNIQUE `unique_school_name`(`school_id`, `name`);
ALTER TABLE `teachers` ADD UNIQUE `unique_school_teacher`(`school_id`, `name`);