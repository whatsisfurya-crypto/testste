<?php
require_once '../config/database.php';

if (empty(VK_CLIENT_ID) || empty(VK_CLIENT_SECRET)) die('VK не настроен.');

if (!isset($_GET['code'])) {
    $authUrl = "https://oauth.vk.com/authorize?" . http_build_query([
        'client_id' => VK_CLIENT_ID, 'redirect_uri' => VK_REDIRECT_URI,
        'display' => 'page', 'scope' => '', 'response_type' => 'code', 'v' => '5.131'
    ]);
    header('Location: ' . $authUrl); exit();
}

$code = $_GET['code'];
$ch = curl_init('https://oauth.vk.com/access_token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id' => VK_CLIENT_ID, 'client_secret' => VK_CLIENT_SECRET,
    'redirect_uri' => VK_REDIRECT_URI, 'code' => $code
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch); curl_close($ch);

$data = json_decode($res, true);
if (!isset($data['access_token'])) die('Ошибка VK: '.($data['error_description']??''));

$vkUserId = $data['user_id'];

// 1. Ищем админа по vk_id
$stmt = $pdo->prepare("SELECT * FROM users WHERE vk_id = ? AND is_active = TRUE");
$stmt->execute([$vkUserId]);
$user = $stmt->fetch();

if ($user) {
    // Вход как админ
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'] ?? 'admin';
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    header('Location: ../pages/dashboard.php'); exit();
}

// 2. Ищем игрока по vk_id
$stmt = $pdo->prepare("SELECT * FROM game_players WHERE vk_id = ? AND is_active = TRUE");
$stmt->execute([$vkUserId]);
$player = $stmt->fetch();

if ($player) {
    // Создаём/находим users для игрока
    $stmt = $pdo->prepare("SELECT * FROM users WHERE game_nickname = ?");
    $stmt->execute([$player['nickname']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $stmt = $pdo->prepare("INSERT INTO users (username, game_nickname, email, password, admin_level, is_active, vk_id) VALUES (?, ?, ?, ?, 0, TRUE, ?)");
        $stmt->execute([$player['nickname'], $player['nickname'], $player['nickname'].'@player.local', $player['password'], $vkUserId]);
        $uid = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO admin_stats (user_id) VALUES (?)");
        $stmt->execute([$uid]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET vk_id = ? WHERE id = ?");
        $stmt->execute([$vkUserId, $user['id']]);
    }
    
    $_SESSION['user_id'] = $user['id'] ?? $uid;
    $_SESSION['username'] = $player['nickname'];
    $_SESSION['role'] = 'player';
    
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    header('Location: ../pages/dashboard.php'); exit();
}

die('❌ Ваш ВК не привязан ни к админке, ни к игровому аккаунту.');