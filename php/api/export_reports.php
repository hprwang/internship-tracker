<?php
require_once __DIR__ . '/../config.php';
$user = requireAuth();
$companyId = $user['company_id'] ?? null;
$db = Database::getCompanyConnection();

$type = $_GET['type'] ?? 'all';
$internshipId = $_GET['id'] ?? null;
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

function clean($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="internship_report_' . date('Y-m-d') . '.csv"');

$output = fopen('php://memory', 'w');
fputcsv($output, ['Internship Reports - Generated ' . date('Y-m-d H:i:s')]);
fputcsv($output, ['Company: ' . ($company['name'] ?? 'N/A')]);
fputcsv($output, ['Period: ' . $dateFrom . ' to ' . $dateTo]);
fputcsv($output, []);

if ($type === 'internship' && $internshipId) {
    $internship = $db->prepare("SELECT * FROM internships WHERE id = ? AND company_id = ?")->execute([$internshipId, $companyId])->fetch();
    if ($internship) {
        $apps = $db->prepare("
            SELECT a.*, u.full_name, u.email, u.phone
            FROM applications a
            LEFT JOIN users u ON a.student_id = u.id
            WHERE a.internship_id = ?
            ORDER BY a.created_at DESC
        ")->execute([$internshipId])->fetchAll();

        fputcsv($output, ['Internship: ' . clean($internship['title'])]);
        fputcsv($output, []);
        fputcsv($output, ['Student Name', 'Email', 'Phone', 'Status', 'Applied Date']);
        foreach ($apps as $app) {
            fputcsv($output, [
                clean($app['full_name'] ?? 'Unknown'),
                clean($app['email'] ?? '-'),
                clean($app['phone'] ?? '-'),
                clean($app['status']),
                date('Y-m-d H:i', strtotime($app['created_at']))
            ]);
        }
    }
} else {
    $internships = $db->prepare("
        SELECT i.*,
            (SELECT COUNT(*) FROM applications a WHERE a.internship_id = i.id) as total_applications,
            (SELECT COUNT(*) FROM applications a WHERE a.internship_id = i.id AND a.status = 'accepted') as accepted,
            (SELECT COUNT(*) FROM applications a WHERE a.internship_id = i.id AND a.status = 'rejected') as rejected,
            (SELECT COUNT(*) FROM applications a WHERE a.internship_id = i.id AND a.status = 'pending') as pending
        FROM internships i
        WHERE i.company_id = ? AND i.created_at BETWEEN ? AND ?
        ORDER BY i.created_at DESC
    ")->execute([$companyId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetchAll();

    fputcsv($output, ['Internship Title', 'Location', 'Total Applications', 'Accepted', 'Rejected', 'Pending', 'Created Date']);
    foreach ($internships as $intern) {
        fputcsv($output, [
            clean($intern['title']),
            clean($intern['location']),
            $intern['total_applications'],
            $intern['accepted'],
            $intern['rejected'],
            $intern['pending'],
            date('Y-m-d', strtotime($intern['created_at']))
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, ['Application Details']);
    fputcsv($output, ['Student', 'Email', 'Internship', 'Status', 'Date']);

    $apps = $db->prepare("
        SELECT a.*, i.title as intern_title, u.full_name, u.email
        FROM applications a
        JOIN internships i ON a.internship_id = i.id
        LEFT JOIN users u ON a.student_id = u.id
        WHERE i.company_id = ? AND a.created_at BETWEEN ? AND ?
        ORDER BY a.created_at DESC
    ")->execute([$companyId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetchAll();

    foreach ($apps as $app) {
        fputcsv($output, [
            clean($app['full_name'] ?? 'Unknown'),
            clean($app['email'] ?? '-'),
            clean($app['intern_title']),
            clean($app['status']),
            date('Y-m-d', strtotime($app['created_at']))
        ]);
    }
}

fseek($output, 0);
echo stream_get_contents($output);
fclose($output);