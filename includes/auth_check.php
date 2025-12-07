<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if the user's account is active
require_once __DIR__ . '/../db/config.php';
try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $is_active = $stmt->fetchColumn();

    if (!$is_active) {
        // User is not active, log them out and redirect to subscription page
        $_SESSION['user_id_for_activation'] = $_SESSION['user_id']; // Preserve user ID for activation
        
        // Unset all other session variables
        unset($_SESSION['user_id']);
        unset($_SESSION['username']);
        unset($_SESSION['role']);
        unset($_SESSION['school_id']);
        if (isset($_SESSION['can_edit_workload'])) {
            unset($_SESSION['can_edit_workload']);
        }

        header("Location: /subscription.php?reason=inactive");
        exit;
    }
} catch (PDOException $e) {
    // On DB error, log out user for safety
    session_destroy();
    header("Location: /login.php?error=db_error");
    exit;
}

// Role-based access check (existing logic)
$allowed_roles = ['admin', 'teacher'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: /login.php?error=unauthorized");
    exit;
}
