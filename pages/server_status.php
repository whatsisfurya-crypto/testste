<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

// Функция запроса SAMP сервера
function querySampServer($ip, $port = 7777) {
    $socket = @fsockopen('udp://' . $ip, $port, $errno, $errstr, 2);
    if (!$socket) return ['online' => false];
    
    stream_set_timeout($socket, 2);
    $packet = 'SAMP' . pack('V', rand(1, 9999)) . 'i';
    fwrite($socket, $packet);
    
    $response = @fread($socket, 2048);
    fclose($socket);
    
    if (strlen($response) < 11) return ['online' => false];
    
    return [
        'online' => true,
        'players' => ord($response[1]) | (ord($response[2]) << 8),
        'max_players' => ord($response[3]) | (ord($response[4]) << 8),
    ];
}

$serverInfo = querySampServer(SAMP_SERVER_IP, SAMP_SERVER_PORT);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>📊 Мониторинг — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --red: #ef4444; --blue: #3b82f6; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 700px; margin: 40px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 30px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        
        .status-big { text-align: center; margin-bottom: 24px; }
        .status-dot { width: 20px; height: 20px; border-radius: 50%; display: inline-block; margin-right: 10px; vertical-align: middle; }
        .dot-online { background: var(--green); box-shadow: 0 0 15px rgba(16,185,129,0.6); animation: pulse 2s infinite; }
        .dot-offline { background: var(--red); }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }
        .status-big span { font-size: 22px; font-weight: 700; vertical-align: middle; }
        
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 32px; }
        .info-row { display: flex; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--border); font-size: 15px; }
        .info-row:last-child { border-bottom: none; }
        .info-row .label { color: var(--text2); }
        .info-row .value { font-weight: 600; }
        
        .players-bar { margin-top: 20px; background: var(--border); border-radius: 10px; height: 8px; overflow: hidden; }
        .players-fill { height: 100%; background: var(--primary); border-radius: 10px; transition: width 0.5s; }
        .players-text { text-align: center; margin-top: 8px; font-size: 13px; color: var(--text2); }
        
        .auto-refresh { text-align: center; margin-top: 16px; font-size: 11px; color: var(--text2); }
    </style>
    <meta http-equiv="refresh" content="30">
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>📊 Мониторинг сервера</h1><p>Реальные данные с игрового сервера</p></div>
        
        <div class="status-big">
            <?php if ($serverInfo['online']): ?>
                <span class="status-dot dot-online"></span><span style="color:var(--green);">Сервер онлайн</span>
            <?php else: ?>
                <span class="status-dot dot-offline"></span><span style="color:var(--red);">Сервер оффлайн</span>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="info-row"><span class="label">🌐 IP адрес</span><span class="value"><?php echo SAMP_SERVER_IP . ':' . SAMP_SERVER_PORT; ?></span></div>
            <div class="info-row"><span class="label">👥 Игроков онлайн</span><span class="value"><?php echo $serverInfo['online'] ? $serverInfo['players'] . ' / ' . $serverInfo['max_players'] : '—'; ?></span></div>
            <div class="info-row"><span class="label">📦 Версия</span><span class="value">0.3.7-R2</span></div>
            <div class="info-row"><span class="label">⏱️ Аптайм</span><span class="value"><?php echo $serverInfo['online'] ? 'Работает' : '—'; ?></span></div>
            
            <?php if ($serverInfo['online'] && $serverInfo['max_players'] > 0): ?>
            <div class="players-bar">
                <div class="players-fill" style="width: <?php echo ($serverInfo['players'] / $serverInfo['max_players']) * 100; ?>%;"></div>
            </div>
            <div class="players-text">Заполненность: <?php echo round(($serverInfo['players'] / $serverInfo['max_players']) * 100); ?>%</div>
            <?php endif; ?>
        </div>
        
        <div class="auto-refresh">🔄 Автообновление каждые 30 секунд • Последнее обновление: <?php echo date('H:i:s'); ?></div>
    </div>
</body>
</html>