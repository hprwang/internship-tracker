<?php
require_once __DIR__ . '/../config.php';
$user = requireAuth();
$companyId = $user['company_id'] ?? null;
$db = Database::getConnection();

$type = $_GET['type'] ?? 'all';
$internshipId = $_GET['id'] ?? null;
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

function clean($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Get company name
$companyName = 'N/A';
if ($companyId) {
    try {
        $stmtCo = $db->prepare("SELECT name FROM companies WHERE id = ?");
        $stmtCo->execute([$companyId]);
        $company = $stmtCo->fetch();
        if ($company) {
            $companyName = $company['name'];
        }
    } catch (Exception $e) {
        error_log("Export reports: Failed to fetch company name: " . $e->getMessage());
    }
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="internship_report_' . date('Y-m-d') . '.csv"');

$output = fopen('php://memory', 'w');
fputcsv($output, ['Internship Reports - Generated ' . date('Y-m-d H:i:s')]);
fputcsv($output, ['Company: ' . $companyName]);
fputcsv($output, ['Period: ' . $dateFrom . ' to ' . $dateTo]);
fputcsv($output, []);

if ($type === 'internship' && $internshipId) {
    $stmt = $db->prepare("SELECT * FROM company_internships WHERE id = ? AND company_id = ?");
    $stmt->execute([$internshipId, $companyId]);
    $internship = $stmt->fetch();

    if ($internship) {
        $stmtApps = $db->prepare("
            SELECT a.*, u.full_name, u.email
            FROM applications a
            LEFT JOIN users u ON a.student_id = u.id
            WHERE a.company_internship_id = ?
            ORDER BY a.applied_at DESC
        ");
        $stmtApps->execute([$internshipId]);
        $apps = $stmtApps->fetchAll();

        fputcsv($output, ['Internship: ' . clean($internship['title'])]);
        fputcsv($output, []);
        fputcsv($output, ['Student Name', 'Email', 'Status', 'Applied Date']);
        foreach ($apps as $app) {
            fputcsv($output, [
                clean($app['full_name'] ?? 'Unknown'),
                clean($app['email'] ?? '-'),
                clean($app['status']),
                date('Y-m-d H:i', strtotime($app['applied_at']))
            ]);
        }
    }
} else {
    $stmtInternships = $db->prepare("
        SELECT ci.*,
            (SELECT COUNT(*) FROM applications a WHERE a.company_internship_id = ci.id) as total_applications,
            (SELECT COUNT(*) FROM applications a WHERE a.company_internship_id = ci.id AND a.status = 'accepted') as accepted,
            (SELECT COUNT(*) FROM applications a WHERE a.company_internship_id = ci.id AND a.status = 'rejected') as rejected,
            (SELECT COUNT(*) FROM applications a WHERE a.company_internship_id = ci.id AND a.status = 'pending') as pending
        FROM company_internships ci
        WHERE ci.company_id = ? AND ci.created_at BETWEEN ? AND ?
        ORDER BY ci.created_at DESC
    ");
    $stmtInternships->execute([$companyId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
    $internships = $stmtInternships->fetchAll();

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

    $stmtAppsAll = $db->prepare("
        SELECT a.*, ci.title as intern_title, u.full_name, u.email
        FROM applications a
        JOIN company_internships ci ON a.company_internship_id = ci.id
        LEFT JOIN users u ON a.student_id = u.id
        WHERE ci.company_id = ? AND a.applied_at BETWEEN ? AND ?
        ORDER BY a.applied_at DESC
    ");
    $stmtAppsAll->execute([$companyId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
    $apps = $stmtAppsAll->fetchAll();

    foreach ($apps as $app) {
        fputcsv($output, [
            clean($app['full_name'] ?? 'Unknown'),
            clean($app['email'] ?? '-'),
            clean($app['intern_title']),
            clean($app['status']),
            date('Y-m-d', strtotime($app['applied_at']))
        ]);
    }
}

fseek($output, 0);
echo stream_get_contents($output);
fclose($output);
