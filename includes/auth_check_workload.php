<?php
session_start();

require_once __DIR__ . '/db/config.php';

// If user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if user is a teacher and can edit their workload
if ($_SESSION['role'] !== 'teacher') {
    header("Location: dashboard.php");
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("SELECT can_edit_workload FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher || !$teacher['can_edit_workload']) {
    header("Location: dashboard.php?error=workload_not_editable");
    exit;
}
