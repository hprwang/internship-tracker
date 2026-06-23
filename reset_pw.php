<?php
$pdo = new PDO('mysql:host=localhost;dbname=internship_tracker1;charset=utf8mb4', 'jojomama', 'MukJoe777#$%');

// Reset jojo1233 password
$hash = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE username = 'jojo1233'");
$stmt->execute([$hash]);
echo "Password reset for jojo1233\n";

// Reset rijesh12 password
$hash = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE username = 'rijesh12'");
$stmt->execute([$hash]);
echo "Password reset for rijesh12\n";