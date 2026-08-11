<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
$user = requireAuth();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
switch ($action) {
    case 'list':
        jsonResponse(true, '', ['notifications' => getUnreadNotifications((int)$user['id'])]);
    case 'mark_read':
        markNotificationRead((int)($_GET['id'] ?? 0), (int)$user['id']);
        jsonResponse(true, '');
    case 'unread_count':
        $n = Database::getConnection()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $n->execute([(int)$user['id']]);
        jsonResponse(true, '', ['count' => (int)$n->fetchColumn()]);
    default:
        jsonResponse(false, 'Invalid action.');
}
