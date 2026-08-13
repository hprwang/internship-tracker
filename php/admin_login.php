<?php
// Deprecated: Admin login is now unified with student login at index.php
// Redirect all traffic to the unified login page
session_start();

// If already logged in as admin, redirect to admin dashboard
if (!empty($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
} elseif (!empty($_SESSION['user'])) {
    // If logged in as student, redirect to student dashboard
    header('Location: ../dashboard.php');
    exit;
}

// Otherwise redirect to unified login page
header('Location: ../index.php');
exit;
