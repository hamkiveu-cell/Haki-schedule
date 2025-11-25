CREATE TABLE IF NOT EXISTS workloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    lessons_per_week INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY (class_id, subject_id, teacher_id)
);
