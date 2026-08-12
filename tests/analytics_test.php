<?php
require_once __DIR__ . '/../php/config.php';
$db = Database::getConnection();
$db->beginTransaction();
try {
    // Temp company + student
    $cname = 'AC_' . uniqid();
    $db->exec("INSERT INTO companies (name) VALUES ('$cname')");
    $cid = (int)$db->lastInsertId();
    $uname = 'tester_a_' . uniqid();
    $email = 'ta_' . uniqid() . '@test.local';
    $db->exec("INSERT INTO users (username,email,password_hash,role,full_name) VALUES
        ('$uname', '$email', 'x', 'student', 'T')");
    $uid = (int)$db->lastInsertId();
    // Two internships with stipend > 0
    $db->exec("INSERT INTO internships (student_id, company_id, title, start_date, end_date, status, stipend)
        VALUES ($uid, $cid, 'One', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 MONTH), 'ongoing', 5000)");
    $db->exec("INSERT INTO internships (student_id, company_id, title, start_date, end_date, status, stipend)
        VALUES ($uid, $cid, 'Two', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 MONTH), 'completed', 8000)");
    $data = studentAnalyticsData($uid);
    assert(is_array($data['status']) && count($data['status']) >= 2, 'status dist');
    assert(is_array($data['timeline']) && count($data['timeline']) > 0, 'timeline');
    assert(is_array($data['stipend']) && count($data['stipend']) === 2, 'stipend series length 2');
    assert(is_array($data['hours']), 'hours');
    echo "PASS\n";
    $db->rollBack();
} catch (Throwable $e) {
    $db->rollBack();
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
