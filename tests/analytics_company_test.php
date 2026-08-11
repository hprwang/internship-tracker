<?php
require_once __DIR__ . '/../php/config.php';
$db = Database::getConnection();
$db->beginTransaction();
try {
    $db->exec("INSERT INTO companies (name) VALUES ('AC_' . uniqid())");
    $cid = (int)$db->lastInsertId();
    $db->exec("INSERT INTO company_internships (company_id, title, description, location, duration, stipend, status)
        VALUES ($cid, 'Posting', 'desc', 'loc', 3, 5000, 'active')");
    $pid = (int)$db->lastInsertId();
    $sids = [];
    for ($i = 0; $i < 2; $i++) {
        $db->exec("INSERT INTO users (username,email,password_hash,role,full_name) VALUES
            ('tester_s_'.uniqid(), 'ts_'.uniqid().'@test.local', 'x', 'student', 'S')");
        $sids[] = (int)$db->lastInsertId();
    }
    $db->exec("INSERT INTO applications (company_internship_id, student_id, cover_letter, resume, status, applied_at)
        VALUES ($pid, $sids[0], 'c', '', 'pending', NOW())");
    $db->exec("INSERT INTO applications (company_internship_id, student_id, cover_letter, resume, status, applied_at)
        VALUES ($pid, $sids[1], 'c', '', 'accepted', NOW())");
    $data = companyAnalyticsData($cid);
    assert(count($data['perPosting']) === 1 && (int)$data['perPosting'][0]['n'] === 2, 'perPosting n=2');
    $statuses = array_column($data['statusDist'], 'status');
    assert(in_array('pending', $statuses, true) && in_array('accepted', $statuses, true), 'statusDist statuses');
    assert(is_array($data['timeline']) && count($data['timeline']) > 0, 'timeline');
    echo "PASS\n";
    $db->rollBack();
} catch (Throwable $e) {
    $db->rollBack();
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
