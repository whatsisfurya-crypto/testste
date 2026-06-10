<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        $userId = $_SESSION['user_id'];
        
        if (strtotime($startDate) < time()) {
            echo json_encode(['success' => false, 'message' => 'Дата начала не может быть в прошлом']);
            exit();
        }
        
        if (strtotime($endDate) <= strtotime($startDate)) {
            echo json_encode(['success' => false, 'message' => 'Дата окончания должна быть позже даты начала']);
            exit();
        }
        
        if (strlen($reason) < 10) {
            echo json_encode(['success' => false, 'message' => 'Опишите причину подробнее (минимум 10 символов)']);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("SELECT id FROM inactivity_records WHERE user_id = ? AND status = 'active'");
            $stmt->execute([$userId]);
            
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'У вас уже есть активная заявка']);
                exit();
            }
            
            $stmt = $pdo->prepare("INSERT INTO inactivity_records (user_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $startDate, $endDate, $reason]);
            
            echo json_encode(['success' => true, 'message' => 'Заявка на неактив создана']);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
        }
        exit();
    }
    
    if (($action === 'approve' || $action === 'reject') && isset($_SESSION['role'])) {
        if ($_SESSION['role'] !== 'chief_admin' && $_SESSION['role'] !== 'senior_admin' && $_SESSION['role'] !== 'developer') {
            echo json_encode(['success' => false, 'message' => 'Недостаточно прав']);
            exit();
        }
        
        $recordId = $_POST['record_id'] ?? 0;
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        try {
            $stmt = $this->pdo->prepare("UPDATE inactivity_records SET status = ? WHERE id = ?");
            $stmt->execute([$status, $recordId]);
            echo json_encode(['success' => true, 'message' => $action === 'approve' ? 'Неактив одобрен' : 'Неактив отклонен']);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
        }
        exit();
    }
}
?>