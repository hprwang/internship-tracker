<?php
require_once __DIR__ . '/../php/config.php';
$db = Database::getConnection();
$db->beginTransaction();
try {
    $db->exec("INSERT INTO users (username,email,password_hash,role,full_name) VALUES
        ('tester_n_'.uniqid(), 'tn_'.uniqid().'@test.local', 'x', 'student', 'T')");
    $uid = (int)$db->lastInsertId();
    notify($uid, 'Hi', 'Hello');
    $unread = getUnreadNotifications($uid);
    assert(count($unread) === 1 && $unread[0]['title'] === 'Hi');
    markNotificationRead((int)$unread[0]['id'], $uid);
    assert(count(getUnreadNotifications($uid)) === 0);
    echo "PASS\n";
    $db->rollBack();
} catch (Throwable $e) {
    $db->rollBack();
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
