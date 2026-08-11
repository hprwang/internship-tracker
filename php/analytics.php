<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
$user = requireAuth();
$scope = $_GET['scope'] ?? '';
switch ($scope) {
    case 'student':
        jsonResponse(true, '', studentAnalyticsData((int)$user['id']));
    case 'admin':
        if ($user['role'] !== 'admin') { http_response_code(403); jsonResponse(false, 'Denied.'); }
        jsonResponse(true, '', adminAnalyticsData());
    case 'company':
        if ($user['role'] !== 'company') { http_response_code(403); jsonResponse(false, 'Denied.'); }
        jsonResponse(true, '', companyAnalyticsData((int)($user['company_id'] ?? 0)));
    default:
        jsonResponse(false, 'Invalid scope.');
}
