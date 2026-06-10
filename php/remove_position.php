<?php
require_once '../config/database.php';
require_once '../php/roles_system.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/positions_management.php');
    exit();
}

$roles = RolesSystem::getInstance($pdo);
$roles->requirePermission('users.change_roles');

$department = $_POST['department'] ?? '';
$positionKey = $_POST['position'] ?? '';

if (empty($department) || empty($positionKey)) {
    $_SESSION['error'] = 'Не указан отдел или должность';
    header('Location: ../pages/positions_management.php');
    exit();
}

$stmt = $pdo->prepare("SELECT user_id FROM admin_positions WHERE department = ? AND position_key = ?");
$stmt->execute([$department, $positionKey]);
$user = $stmt->fetch();

if ($user) {
    $roles->removePosition($user['user_id']);
    $_SESSION['message'] = 'Сотрудник снят с должности';
} else {
    $_SESSION['error'] = 'Должность не занята';
}

header('Location: ../pages/positions_management.php');
exit();
?>