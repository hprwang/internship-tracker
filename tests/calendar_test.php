<?php
require_once __DIR__ . '/../php/config.php';
$db = Database::getConnection();
$db->beginTransaction();
try {
    $cname = 'AC_' . uniqid();
    $db->exec("INSERT INTO companies (name) VALUES ('$cname')");
    $cid = (int)$db->lastInsertId();
    $uname = 'tester_cal_' . uniqid();
    $email = 'tcal_' . uniqid() . '@test.local';
    $db->exec("INSERT INTO users (username,email,password_hash,role,full_name) VALUES
        ('$uname', '$email', 'x', 'student', 'T')");
    $uid = (int)$db->lastInsertId();
    $start = '2026-09-01'; $end = '2026-12-01'; $logDate = '2026-10-15';
    $db->exec("INSERT INTO internships (student_id, company_id, title, start_date, end_date, status, stipend, created_at)
        VALUES ($uid, $cid, 'Cal One', '$start', '$end', 'ongoing', 5000, '2026-08-20 10:00:00')");
    $iid = (int)$db->lastInsertId();
    $db->exec("INSERT INTO progress_logs (internship_id, week_number, log_date, hours_worked, rating)
        VALUES ($iid, 6, '$logDate', 6, 5)");
    $events = calendarEvents($uid);
    $dates = array_column($events, 'date');
    $types = array_column($events, 'type');
    assert(in_array($start, $dates, true), 'start event');
    assert(in_array($end, $dates, true), 'end event');
    assert(in_array($logDate, $dates, true), 'progress date');
    assert(in_array('internship_start', $types, true), 'start type');
    assert(in_array('progress', $types, true), 'progress type');
    echo "PASS\n";
    $db->rollBack();
} catch (Throwable $e) {
    $db->rollBack();
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
