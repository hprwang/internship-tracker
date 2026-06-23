<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$user = requireAuth();
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$requestId = (int)($data['request_id'] ?? 0);
$action = $data['action'] ?? '';

if (!$requestId || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$db = Database::getConnection();

try {
    // Get request details
    $stmt = $db->prepare("SELECT * FROM supervisor_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        exit;
    }

    if ($action === 'approve') {
        // Create assignment
        $stmt = $db->prepare("INSERT INTO supervisor_assignments (student_id, supervisor_id, assigned_at) VALUES (?, ?, NOW())");
        $stmt->execute([$request['student_id'], $request['supervisor_id']]);

        // Update request status
        $stmt = $db->prepare("UPDATE supervisor_requests SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$requestId]);
    } else {
        // Reject request
        $stmt = $db->prepare("UPDATE supervisor_requests SET status = 'rejected', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$requestId]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Admin supervisor request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to process request']);
}