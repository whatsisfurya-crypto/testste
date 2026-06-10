<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/roles_system.php';
require_once '../php/cache.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }
$roles = RolesSystem::getInstance($pdo);
$roles->requirePermission('console.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
    header('Content-Type: application/json');
    require_once '../php/rcon.php';
    $rcon = new SampRcon(SAMP_SERVER_IP, SAMP_SERVER_PORT, SAMP_RCON_PASSWORD);
    $conn = $rcon->connect();
    if ($conn['success']) { $result = $rcon->sendCommand($_POST['command']); $rcon->disconnect(); echo json_encode(['success'=>true,'data'=>$result]); }
    else { echo json_encode(['success'=>false,'message'=>$conn['message']]); }
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>💻 Консоль — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --red: #ef4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 24px; }
        .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        .terminal { background: #050510; border: 1px solid var(--border); border-radius: 14px; overflow: hidden; margin-bottom: 20px; }
        .terminal-output { height: 400px; overflow-y: auto; padding: 14px; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.7; }
        .line { margin-bottom: 2px; } .line.system { color: #6366f1; } .line.input { color: var(--green); } .line.output { color: var(--text); } .line.error { color: var(--red); }
        .terminal-input { display: flex; padding: 10px 14px; background: #0a0a15; border-top: 1px solid var(--border); gap: 10px; }
        .terminal-input input { flex: 1; background: transparent; border: none; color: var(--text); font-family: 'Courier New', monospace; font-size: 13px; outline: none; }
        .send-btn { padding: 8px 18px; background: var(--primary); border: none; border-radius: 8px; color: white; cursor: pointer; font-weight: 600; font-size: 12px; }
        .quick-cmds { display: flex; gap: 8px; flex-wrap: wrap; }
        .quick-cmds button { padding: 10px 18px; background: var(--card); border: 1px solid var(--border); border-radius: 10px; color: var(--text); font-family: 'Montserrat', sans-serif; font-size: 13px; cursor: pointer; }
        .quick-cmds button:hover { border-color: var(--primary); }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>💻 Консоль сервера</h1><p>Удалённое управление сервером через RCON</p></div>
        <div class="terminal">
            <div class="terminal-output" id="output"><div class="line system">SAMP Server Console v2.0</div></div>
            <div class="terminal-input">
                <span style="color:var(--green);font-family:'Courier New';">samp&gt;</span>
                <input type="text" id="cmdInput" placeholder="Команда..." onkeydown="if(event.key==='Enter')sendCmd()">
                <button class="send-btn" onclick="sendCmd()">Отправить</button>
            </div>
        </div>
        <div class="quick-cmds">
            <button onclick="quickCmd('players')">👥 Игроки</button>
            <button onclick="quickCmd('info')">ℹ️ Инфо</button>
            <button onclick="quickCmd('gmx')">🔄 Перезагрузка</button>
            <button onclick="quickCmd('say Привет!')">💬 Сообщение</button>
        </div>
    </div>
    <script>
    function addLine(t,ty='output'){const o=document.getElementById('output');o.innerHTML+=`<div class="line ${ty}">[${new Date().toLocaleTimeString()}] ${t}</div>`;o.scrollTop=o.scrollHeight;}
    async function sendCmd(){const i=document.getElementById('cmdInput');const c=i.value.trim();if(!c)return;addLine('> '+c,'input');i.value='';try{const r=await fetch('server_console.php',{method:'POST',body:new URLSearchParams({command:c})});const d=await r.json();if(d.success){if(Array.isArray(d.data))d.data.forEach(l=>addLine(l));else addLine(d.data);}else addLine('Ошибка: '+d.message,'error');}catch(e){addLine('Ошибка соединения','error');}}
    function quickCmd(c){document.getElementById('cmdInput').value=c;sendCmd();}
    </script>
</body>
</html>