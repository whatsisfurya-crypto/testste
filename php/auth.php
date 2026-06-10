<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'login') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Заполните все поля']);
        exit();
    }
    
    try {
        // 1. Ищем админа в users
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = TRUE");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Вход как админ
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'] ?? 'admin';
            
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            $stmt = $pdo->prepare("INSERT INTO action_logs (user_id, action, description, ip_address) VALUES (?, 'login', 'Вход в систему', ?)");
            $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);
            
            echo json_encode(['success' => true, 'redirect' => '../pages/dashboard.php']);
            exit();
        }
        
        // 2. Ищем игрока в game_players
        $stmt = $pdo->prepare("SELECT * FROM game_players WHERE nickname = ? AND is_active = TRUE");
        $stmt->execute([$login]);
        $player = $stmt->fetch();
        
        if ($player && password_verify($password, $player['password'])) {
            // Создаём/находим users для игрока
            $stmt = $pdo->prepare("SELECT * FROM users WHERE game_nickname = ?");
            $stmt->execute([$login]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $stmt = $pdo->prepare("INSERT INTO users (username, game_nickname, email, password, admin_level, is_active) VALUES (?, ?, ?, ?, 0, TRUE)");
                $stmt->execute([$login, $login, $login.'@player.local', $player['password']]);
                $uid = $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO admin_stats (user_id) VALUES (?)");
                $stmt->execute([$uid]);
                $user = ['id' => $uid, 'username' => $login, 'role' => 'player', 'is_active' => TRUE];
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = 'player';
            
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            $stmt = $pdo->prepare("INSERT INTO action_logs (user_id, action, description, ip_address) VALUES (?, 'login', 'Вход игрока', ?)");
            $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);
            
            echo json_encode(['success' => true, 'redirect' => '../pages/dashboard.php']);
            exit();
        }
        
        echo json_encode(['success' => false, 'message' => 'Неверный логин или пароль']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
    }
    exit();
}
?>