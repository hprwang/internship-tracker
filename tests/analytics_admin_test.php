<?php
require_once __DIR__ . '/../php/config.php';
$db = Database::getConnection();
$db->beginTransaction();
try {
    $cname = 'AC_' . uniqid();
    $db->exec("INSERT INTO companies (name) VALUES ('$cname')");
    $cid = (int)$db->lastInsertId();
    $uname = 'tester_adm_' . uniqid();
    $email = 'tad_' . uniqid() . '@test.local';
    $db->exec("INSERT INTO users (username,email,password_hash,role,full_name) VALUES
        ('$uname', '$email', 'x', 'student', 'T')");
    $uid = (int)$db->lastInsertId();
    $db->exec("INSERT INTO internships (student_id, company_id, title, start_date, end_date, status, stipend)
        VALUES ($uid, $cid, 'One', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 MONTH), 'ongoing', 5000)");
    $data = adminAnalyticsData();
    assert($data['kpis']['students'] >= 1, 'students kpi');
    assert(is_array($data['topCompanies']) && count($data['topCompanies']) > 0, 'topCompanies');
    $statuses = array_column($data['statusDist'], 'status');
    assert(in_array('ongoing', $statuses, true), 'statusDist has ongoing');
    echo "PASS\n";
    $db->rollBack();
} catch (Throwable $e) {
    $db->rollBack();
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
