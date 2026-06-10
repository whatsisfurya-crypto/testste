<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';

if ($apiKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API key']);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_admin_stats':
            $username = $_GET['username'] ?? '';
            $stmt = $pdo->prepare("SELECT u.*, s.* FROM users u LEFT JOIN admin_stats s ON u.id = s.user_id WHERE u.username = ?");
            $stmt->execute([$username]);
            $data = $stmt->fetch();
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'server_info':
            echo json_encode([
                'success' => true,
                'data' => [
                    'players_online' => 0,
                    'max_players' => 500,
                    'uptime' => '24/7',
                    'version' => '0.3.7-R2'
                ]
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>