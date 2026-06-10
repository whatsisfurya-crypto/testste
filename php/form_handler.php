<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    
    if ($action === 'create') {
        $formType = $_POST['form_type'] ?? '';
        $playerName = trim($_POST['player_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $userId = $_SESSION['user_id'];
        
        $allowedTypes = ['complaint', 'unban', 'question'];
        if (!in_array($formType, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Неверный тип формы']);
            exit();
        }
        
        if (strlen($playerName) < 3) {
            echo json_encode(['success' => false, 'message' => 'Имя игрока должно быть минимум 3 символа']);
            exit();
        }
        
        if (strlen($description) < 20) {
            echo json_encode(['success' => false, 'message' => 'Опишите ситуацию подробнее (минимум 20 символов)']);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO offline_forms (user_id, form_type, player_name, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $formType, $playerName, $description]);
            echo json_encode(['success' => true, 'message' => 'Форма отправлена']);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
        }
        exit();
    }
}
?>