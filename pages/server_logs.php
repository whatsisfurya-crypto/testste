<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
require_once '../php/roles_system.php';
require_once '../php/account_monitor.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }
$roles = RolesSystem::getInstance($pdo);
$myId = $_SESSION['user_id'];
$roles->requirePermission('logs.view_server');

$tab = $_GET['tab'] ?? 'logs';

// RCON консоль
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rcon_command'])) {
    header('Content-Type: application/json');
    require_once '../php/rcon.php';
    $rcon = new SampRcon(SAMP_SERVER_IP, SAMP_SERVER_PORT, SAMP_RCON_PASSWORD);
    $conn = $rcon->connect();
    if ($conn['success']) { $result = $rcon->sendCommand($_POST['rcon_command']); $rcon->disconnect(); echo json_encode(['success'=>true,'data'=>$result]); }
    else { echo json_encode(['success'=>false,'message'=>$conn['message']]); }
    exit();
}

// Логи
$currentLog = 'server_log.txt';
$logPath = __DIR__ . '/../logs/' . $currentLog;
$lines = array();
if (file_exists($logPath)) { $allLines = file($logPath); $allLines = array_reverse($allLines); $lines = array_slice($allLines, 0, 100); }
$search = $_GET['search'] ?? '';
$filterType = $_GET['type'] ?? 'all';
if ($search) { $lines = array_filter($lines, function($l) use ($search) { return stripos($l, $search) !== false; }); }
if ($filterType !== 'all') { $lines = array_filter($lines, function($l) use ($filterType) { return stripos($l, strtoupper($filterType)) !== false; }); }

// Мониторинг
function querySampServer($ip, $port = 7777) {
    $socket = @fsockopen('udp://' . $ip, $port, $errno, $errstr, 2);
    if (!$socket) return array('online' => false);
    stream_set_timeout($socket, 2);
    $packet = 'SAMP' . pack('V', rand(1, 9999)) . 'i';
    fwrite($socket, $packet);
    $response = @fread($socket, 2048);
    fclose($socket);
    if (strlen($response) < 11) return array('online' => false);
    return array('online' => true, 'players' => ord($response[1]) | (ord($response[2]) << 8), 'max_players' => ord($response[3]) | (ord($response[4]) << 8));
}
$serverInfo = querySampServer(SAMP_SERVER_IP, SAMP_SERVER_PORT);

// Проверка аккаунтов
$monitor = new AccountMonitor($pdo);
$checkQuery = $_GET['check'] ?? '';
$checkResults = array();
if ($checkQuery) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username LIKE ? OR id = ?");
    $stmt->execute(array("%$checkQuery%", $checkQuery));
    $checkResults = $stmt->fetchAll();
    foreach ($checkResults as &$u) { $u['linked'] = $monitor->findMultiAccounts($u['id']); $u['sec'] = $monitor->getSecurityReport($u['id']); }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>⚙️ Управление сервером — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --red: #ef4444; --yellow: #f59e0b; --blue: #3b82f6; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 24px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; justify-content: center; flex-wrap: wrap; }
        .tabs a { padding: 10px 20px; border-radius: 10px; color: var(--text2); text-decoration: none; font-weight: 500; font-size: 13px; border: 1px solid var(--border); transition: all 0.2s; }
        .tabs a:hover, .tabs a.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; }
        .btn { padding: 10px 20px; background: var(--primary); border: none; border-radius: 10px; color: white; font-weight: 600; cursor: pointer; font-size: 13px; }
        .btn:hover { background: #7c3aed; }
        
        /* Логи */
        .toolbar { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 12px 16px; margin-bottom: 12px; display: flex; gap: 10px; flex-wrap: wrap; }
        .toolbar input, .toolbar select { padding: 9px 14px; background: rgba(255,255,255,0.03); border: 2px solid var(--border); border-radius: 8px; color: var(--text); font-family: 'Montserrat', sans-serif; font-size: 13px; outline: none; }
        .toolbar input:focus, .toolbar select:focus { border-color: var(--primary); }
        .log-content { background: #050510; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .log-body { max-height: 500px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.8; }
        .log-line { padding: 3px 14px; display: flex; gap: 10px; } .log-line:hover { background: rgba(139,92,246,0.05); }
        .line-num { color: var(--text2); min-width: 35px; text-align: right; }
        .log-line.error .line-text { color: var(--red); } .log-line.warning .line-text { color: var(--yellow); }
        
        /* Консоль */
        .terminal { background: #050510; border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
        .terminal-output { height: 380px; overflow-y: auto; padding: 14px; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.7; }
        .t-line { margin-bottom: 2px; } .t-line.system { color: #6366f1; } .t-line.input { color: var(--green); } .t-line.output { color: var(--text); } .t-line.error { color: var(--red); }
        .terminal-input { display: flex; padding: 10px 14px; background: #0a0a15; border-top: 1px solid var(--border); gap: 10px; }
        .terminal-input input { flex: 1; background: transparent; border: none; color: var(--text); font-family: 'Courier New', monospace; font-size: 13px; outline: none; }
        
        /* Мониторинг */
        .status-dot { width: 16px; height: 16px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        .dot-online { background: var(--green); box-shadow: 0 0 12px rgba(16,185,129,0.5); }
        .dot-offline { background: var(--red); }
        .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border); }
        .info-row .lbl { color: var(--text2); }
        .bar { background: var(--border); border-radius: 8px; height: 6px; margin-top: 16px; overflow: hidden; }
        .bar-fill { height: 100%; background: var(--primary); border-radius: 8px; }
        
        /* Проверка */
        .linked-item { padding: 10px; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 8px; margin-top: 6px; display: flex; justify-content: space-between; }
        .risk { display: inline-block; padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 600; }
        .risk-low { background: rgba(16,185,129,0.1); color: var(--green); } .risk-medium { background: rgba(245,158,11,0.1); color: var(--yellow); } .risk-high { background: rgba(239,68,68,0.1); color: var(--red); }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>⚙️ Управление сервером</h1><p>Логи • Консоль • Мониторинг • Проверка аккаунтов</p></div>
        
        <div class="tabs">
            <a href="?tab=logs" class="<?php echo $tab==='logs'?'active':''; ?>">📜 Логи</a>
            <a href="?tab=console" class="<?php echo $tab==='console'?'active':''; ?>">💻 Консоль</a>
            <a href="?tab=check" class="<?php echo $tab==='check'?'active':''; ?>">🔍 Проверка</a>
        </div>
        
        <?php if ($tab === 'logs'): ?>
        <form class="toolbar" method="GET"><input type="hidden" name="tab" value="logs"><input type="text" name="search" placeholder="🔍 Поиск..." value="<?php echo htmlspecialchars($search); ?>"><select name="type"><option value="all">Все</option><option value="info">INFO</option><option value="warning">WARNING</option><option value="error">ERROR</option></select><button type="submit" class="btn">🔍</button></form>
        <div class="log-content"><div class="log-body"><?php foreach($lines as $i=>$l): $c='info'; if(stripos($l,'error')!==false)$c='error'; elseif(stripos($l,'warn')!==false)$c='warning'; ?><div class="log-line <?php echo $c; ?>"><span class="line-num"><?php echo $i+1; ?></span><span class="line-text"><?php echo htmlspecialchars(trim($l)); ?></span></div><?php endforeach; ?></div></div>
        
        <?php elseif ($tab === 'console'): ?>
        <div class="terminal">
            <div class="terminal-output" id="output"><div class="t-line system">SAMP Server Console v2.0</div></div>
            <div class="terminal-input"><span style="color:var(--green);font-family:'Courier New';">samp&gt;</span><input type="text" id="cmdInput" placeholder="Команда..." onkeydown="if(event.key==='Enter')sendCmd()"><button class="btn" onclick="sendCmd()" style="padding:8px 16px;">Отправить</button></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;">
            <button onclick="quickCmd('players')" style="padding:8px 16px;background:var(--card);border:1px solid var(--border);border-radius:8px;color:var(--text);cursor:pointer;">👥 Игроки</button>
            <button onclick="quickCmd('info')" style="padding:8px 16px;background:var(--card);border:1px solid var(--border);border-radius:8px;color:var(--text);cursor:pointer;">ℹ️ Инфо</button>
            <button onclick="quickCmd('gmx')" style="padding:8px 16px;background:var(--card);border:1px solid var(--border);border-radius:8px;color:var(--text);cursor:pointer;">🔄 Перезагрузка</button>
        </div>
        
        <?php elseif ($tab === 'monitor'): ?>
        <div class="card" style="text-align:center;">
            <h2 style="margin-bottom:20px;"><?php if($serverInfo['online']): ?><span class="status-dot dot-online"></span><span style="color:var(--green);">Сервер онлайн</span><?php else: ?><span class="status-dot dot-offline"></span><span style="color:var(--red);">Сервер оффлайн</span><?php endif; ?></h2>
            <div class="info-row"><span class="lbl">🌐 IP</span><span><?php echo SAMP_SERVER_IP.':'.SAMP_SERVER_PORT; ?></span></div>
            <div class="info-row"><span class="lbl">👥 Игроков</span><span><?php echo $serverInfo['online'] ? $serverInfo['players'].' / '.$serverInfo['max_players'] : '—'; ?></span></div>
            <div class="info-row"><span class="lbl">📦 Версия</span><span>0.3.7-R2</span></div>
            <?php if($serverInfo['online'] && $serverInfo['max_players']>0): ?><div class="bar"><div class="bar-fill" style="width:<?php echo ($serverInfo['players']/$serverInfo['max_players'])*100; ?>%;"></div></div><?php endif; ?>
        </div>
        
        <?php else: ?>
        <form class="card" method="GET" style="margin-bottom:20px;"><input type="hidden" name="tab" value="check"><div style="display:flex;gap:10px;"><input type="text" name="check" placeholder="Никнейм или ID..." value="<?php echo htmlspecialchars($checkQuery); ?>" style="flex:1;padding:10px 14px;background:rgba(255,255,255,0.03);border:2px solid var(--border);border-radius:10px;color:var(--text);font-family:'Montserrat',sans-serif;outline:none;"><button type="submit" class="btn">🔍 Искать</button></div></form>
        <?php if($checkQuery && $checkResults): foreach($checkResults as $u): ?>
        <div class="card" style="margin-bottom:12px;"><h3 style="margin-bottom:8px;"><?php echo htmlspecialchars($u['username']); ?> <span class="risk risk-<?php echo $u['sec']['risk_level']; ?>">Риск: <?php echo $u['sec']['risk_score']; ?>%</span></h3>
        <?php if(!empty($u['linked'])): ?><p style="font-size:13px;color:var(--text2);">🔗 Связи:</p><?php foreach($u['linked'] as $l): ?><div class="linked-item"><span><?php echo htmlspecialchars($l['user']['username']); ?></span><span style="color:var(--text2);"><?php echo $l['link_type']; ?></span><strong><?php echo $l['confidence']; ?>%</strong></div><?php endforeach; endif; ?></div>
        <?php endforeach; elseif($checkQuery): ?><p style="text-align:center;color:var(--text2);">Ничего не найдено</p><?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php if ($tab === 'console'): ?>
    <script>
    function addLine(t,ty){ ty=ty||'output'; var o=document.getElementById('output'); o.innerHTML+='<div class="t-line '+ty+'">['+new Date().toLocaleTimeString()+'] '+t+'</div>'; o.scrollTop=o.scrollHeight; }
    async function sendCmd(){ var i=document.getElementById('cmdInput'); var c=i.value.trim(); if(!c)return; addLine('> '+c,'input'); i.value=''; try{ var r=await fetch('server_logs.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'rcon_command='+encodeURIComponent(c)}); var d=await r.json(); if(d.success){ if(Array.isArray(d.data)) d.data.forEach(function(l){addLine(l);}); else addLine(d.data); } else addLine('Ошибка: '+d.message,'error'); }catch(e){ addLine('Ошибка соединения','error'); } }
    function quickCmd(c){ document.getElementById('cmdInput').value=c; sendCmd(); }
    </script>
    <?php endif; ?>
</body>
</html>