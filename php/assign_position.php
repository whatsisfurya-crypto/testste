<?php
require_once '../config/database.php';
require_once '../php/roles_system.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../pages/dashboard.php'); exit(); }

$roles = RolesSystem::getInstance($pdo);
$myId = $_SESSION['user_id'];

$userId = (int)$_POST['user_id'];
$dept = $_POST['department'] ?? '';
$pos = $_POST['position'] ?? 'staff';

if ($userId && $dept) {
    $roles->assignPosition($userId, $dept, $pos, $myId);
    
    // Логирование
    $stmt = $pdo->prepare("INSERT INTO action_logs (user_id, action, description, ip_address) VALUES (?, 'assign_position', ?, ?)");
    $stmt->execute([$myId, "Назначен в отдел {$dept}", $_SERVER['REMOTE_ADDR']]);
    
    $_SESSION['message'] = '✅ Сотрудник назначен!';
}

header('Location: ../pages/positions_management.php');
exit();