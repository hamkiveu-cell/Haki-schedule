<?php
session_start();

// If user is not logged in or is not an admin, redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher')) {
    header("Location: login.php");
    exit;
}
