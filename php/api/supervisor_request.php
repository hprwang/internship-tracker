<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$user = requireAuth();
$csrf = generateCSRF();

// Validate CSRF
if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

$db = Database::getConnection();
$userId = (int)$user['id'];
$supervisorId = (int)($_POST['supervisor_id'] ?? 0);

if (!$supervisorId) {
    echo json_encode(['success' => false, 'error' => 'Please select a supervisor']);
    exit;
}

try {
    // Check if already has a request
    $stmt = $db->prepare("SELECT id FROM supervisor_requests WHERE student_id = ? AND status = 'pending'");
    $stmt->execute([$userId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'You already have a pending request']);
        exit;
    }

    // Create request
    $stmt = $db->prepare("INSERT INTO supervisor_requests (student_id, supervisor_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
    $stmt->execute([$userId, $supervisorId]);

    echo json_encode(['success' => true, 'message' => 'Request submitted']);
} catch (Exception $e) {
    error_log("Supervisor request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to submit request']);
}