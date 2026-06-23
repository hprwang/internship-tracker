<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$user = requireAuth();

$db = Database::getConnection();
$userId = (int)$user['id'];

// Get POST data
$data = [
    'internship_id' => !empty($_POST['internship_id']) ? (int)$_POST['internship_id'] : null,
    'performance_score' => (int)($_POST['performance_score'] ?? 0),
    'professionalism_score' => (int)($_POST['professionalism_score'] ?? 0),
    'learning_score' => (int)($_POST['learning_score'] ?? 0),
    'comments' => trim($_POST['comments'] ?? '')
];

if ($data['performance_score'] < 1 || $data['performance_score'] > 5) {
    echo json_encode(['success' => false, 'error' => 'Invalid ratings']);
    exit;
}

try {
    // Insert evaluation
    $stmt = $db->prepare("
        INSERT INTO evaluations (student_id, internship_id, performance_score, professionalism_score, learning_score, comments, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'submitted', NOW())
    ");
    $stmt->execute([
        $userId,
        $data['internship_id'],
        $data['performance_score'],
        $data['professionalism_score'],
        $data['learning_score'],
        $data['comments']
    ]);

    // Update grades
    updateStudentGrades($db, $userId);

    echo json_encode(['success' => true, 'message' => 'Evaluation submitted']);
} catch (Exception $e) {
    error_log("Evaluation submit error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to submit evaluation']);
}

function updateStudentGrades($db, $studentId) {
    // Get all evaluations
    $stmt = $db->prepare("
        SELECT AVG(performance_score) as p_avg, AVG(professionalism_score) as pr_avg, AVG(learning_score) as l_avg,
               COUNT(*) as total
        FROM evaluations WHERE student_id = ?
    ");
    $stmt->execute([$studentId]);
    $eval = $stmt->fetch();

    if (!$eval || $eval['total'] == 0) return;

    $avgScore = ($eval['p_avg'] + $eval['pr_avg'] + $eval['l_avg']) / 3;
    $overallGrade = ($avgScore / 5) * 100;

    $gradeLetter = 'F';
    if ($overallGrade >= 90) $gradeLetter = 'A';
    elseif ($overallGrade >= 80) $gradeLetter = 'B';
    elseif ($overallGrade >= 70) $gradeLetter = 'C';
    elseif ($overallGrade >= 60) $gradeLetter = 'D';

    // Get reports count
    $stmt = $db->prepare("SELECT COUNT(*) FROM progress_logs WHERE student_id = ? AND status = 'approved'");
    $stmt->execute([$studentId]);
    $reportsCount = (int)$stmt->fetchColumn();

    // Update or insert grades
    $stmt = $db->prepare("
        INSERT INTO student_grades (student_id, overall_grade, grade_letter, average_score, performance_avg, professionalism_avg, learning_avg, total_evaluations, reports_submitted, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE overall_grade = ?, grade_letter = ?, average_score = ?, performance_avg = ?, professionalism_avg = ?, learning_avg = ?, total_evaluations = ?, reports_submitted = ?
    ");
    $stmt->execute([
        $studentId,
        $overallGrade, $gradeLetter, $avgScore,
        $eval['p_avg'], $eval['pr_avg'], $eval['l_avg'],
        $eval['total'], $reportsCount,
        $overallGrade, $gradeLetter, $avgScore,
        $eval['p_avg'], $eval['pr_avg'], $eval['l_avg'],
        $eval['total'], $reportsCount
    ]);
}