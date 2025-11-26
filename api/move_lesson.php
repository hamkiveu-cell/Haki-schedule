<?php
header('Content-Type: application/json');
require_once '../db/config.php';
require_once '../includes/auth_check.php';

$response = ['success' => false, 'error' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $lesson_id = $data['lesson_id'] ?? null;
    $to_class_id = $data['to_class_id'] ?? null;
    $to_day = $data['to_day'] ?? null;
    $to_timeslot_id = $data['to_timeslot_id'] ?? null;

    if ($lesson_id && $to_class_id && isset($to_day) && $to_timeslot_id) {
        try {
            $pdo = db();

            // TODO: Add validation logic here to check for conflicts

            $stmt = $pdo->prepare(
                'UPDATE schedules SET class_id = :class_id, day_of_week = :day, timeslot_id = :timeslot_id WHERE id = :lesson_id'
            );

            $stmt->execute([
                ':class_id' => $to_class_id,
                ':day' => $to_day,
                ':timeslot_id' => $to_timeslot_id,
                ':lesson_id' => $lesson_id
            ]);

            if ($stmt->rowCount() > 0) {
                $response = ['success' => true];
            } else {
                $response['error'] = 'Failed to move lesson. No rows were updated.';
            }

        } catch (PDOException $e) {
            // Check for unique constraint violation
            if ($e->getCode() == 23000) { // SQLSTATE for integrity constraint violation
                $response['error'] = 'This time slot is already occupied.';
            } else {
                $response['error'] = 'Database error: ' . $e->getMessage();
            }
        }
    } else {
        $response['error'] = 'Missing required data.';
    }
} 

echo json_encode($response);
