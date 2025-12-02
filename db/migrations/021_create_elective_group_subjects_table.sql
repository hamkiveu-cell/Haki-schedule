CREATE TABLE IF NOT EXISTS elective_group_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    elective_group_id INT NOT NULL,
    subject_id INT NOT NULL,
    FOREIGN KEY (elective_group_id) REFERENCES elective_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY (elective_group_id, subject_id)
);
