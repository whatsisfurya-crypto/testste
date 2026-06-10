<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

$dev = null;
$stmt = $pdo->query("SELECT * FROM users WHERE admin_level = 9 ORDER BY id LIMIT 1");
$dev = $stmt->fetch();
if (!$dev) { $stmt = $pdo->query("SELECT * FROM users WHERE admin_level = 8 ORDER BY id LIMIT 1"); $dev = $stmt->fetch(); }
if (!$dev) { $stmt = $pdo->query("SELECT * FROM users ORDER BY admin_level DESC LIMIT 1"); $dev = $stmt->fetch(); }

$li = [1=>'🛡️',2=>'🔰',3=>'⚖️',4=>'🔱',5=>'👁️',6=>'🎖️',7=>'👑',8=>'⚔️',9=>'⚒️'];
$lvl = $dev['admin_level'] ?? 9;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>👨‍💻 Команда — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --pink: #ec4899; --gold: #f59e0b; --blue: #3b82f6; --green: #10b981; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 900px; margin: 0 auto; padding: 60px 20px; text-align: center; }
        .header-badge { display: inline-block; padding: 8px 22px; background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.3); border-radius: 30px; color: var(--primary); font-size: 13px; font-weight: 600; letter-spacing: 2px; margin-bottom: 24px; animation: fadeUp 1s ease; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
        .main-title { font-size: 48px; font-weight: 900; margin-bottom: 12px; animation: fadeUp 1s ease 0.2s both; }
        .main-title .grad { background: linear-gradient(135deg, var(--text) 0%, var(--primary) 50%, var(--pink) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .main-sub { color: var(--text2); font-size: 17px; margin-bottom: 60px; animation: fadeUp 1s ease 0.4s both; }
        .dev-card { background: var(--card); border: 1px solid var(--border); border-radius: 28px; padding: 56px 48px; position: relative; overflow: hidden; animation: fadeUp 1s ease 0.6s both; max-width: 650px; margin: 0 auto; }
        .dev-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), var(--pink), var(--gold), var(--blue), var(--primary)); background-size: 400% 100%; animation: grad 5s ease infinite; }
        @keyframes grad { 0%,100%{background-position:0% 50%} 50%{background-position:100% 50%} }
        .avatar-frame { position: relative; display: inline-block; margin: 16px 0 20px; }
        .avatar-frame img { width: 140px; height: 140px; border-radius: 50%; border: 5px solid var(--primary); object-fit: cover; box-shadow: 0 0 60px rgba(139,92,246,0.35); }
        .avatar-frame .ring { position: absolute; top: -8px; left: -8px; right: -8px; bottom: -8px; border-radius: 50%; border: 2px dashed rgba(139,92,246,0.3); animation: spin 20s linear infinite; }
        @keyframes spin { to{transform:rotate(360deg)} }
        .dev-name { font-size: 34px; font-weight: 900; }
        .dev-role { display: inline-block; padding: 7px 20px; background: rgba(236,72,153,0.08); border: 1px solid rgba(236,72,153,0.3); border-radius: 25px; color: var(--pink); font-size: 13px; font-weight: 600; margin-bottom: 20px; }
        .dev-bio { color: var(--text2); font-size: 15px; line-height: 1.8; margin-bottom: 28px; max-width: 480px; margin-left: auto; margin-right: auto; }
        .status-row { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-bottom: 28px; }
        .status-chip { padding: 8px 18px; border-radius: 25px; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .chip-online { background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }
        .chip-role { background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.3); color: var(--primary); }
        .chip-since { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.3); color: var(--gold); }
        .dot-pulse { width: 8px; height: 8px; border-radius: 50%; background: var(--green); animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }
        .skills-title { font-size: 12px; color: var(--text2); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 14px; }
        .skills-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        .skill { padding: 9px 18px; border-radius: 25px; font-size: 13px; font-weight: 600; transition: all 0.3s; cursor: default; }
        .skill:hover { transform: translateY(-2px); }
        .s-php { background: rgba(139,92,246,0.1); color: #8b5cf6; border: 1px solid rgba(139,92,246,0.3); }
        .s-js { background: rgba(245,158,11,0.1); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); }
        .s-mysql { background: rgba(59,130,246,0.1); color: #3b82f6; border: 1px solid rgba(59,130,246,0.3); }
        .s-python { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
        .s-lua { background: rgba(99,102,241,0.1); color: #6366f1; border: 1px solid rgba(99,102,241,0.3); }
        .s-css { background: rgba(236,72,153,0.1); color: #ec4899; border: 1px solid rgba(236,72,153,0.3); }
        .s-git { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .s-api { background: rgba(168,85,247,0.1); color: #a855f7; border: 1px solid rgba(168,85,247,0.3); }
        .footer { margin-top: 48px; padding-top: 24px; border-top: 1px solid var(--border); }
        .footer p { color: var(--text2); font-size: 13px; }
        .footer .year { display: inline-block; padding: 6px 16px; background: rgba(139,92,246,0.06); border: 1px solid rgba(139,92,246,0.2); border-radius: 20px; color: var(--primary); font-size: 12px; font-weight: 600; margin-top: 8px; }
        .heart { color: var(--pink); animation: heartbeat 1.5s infinite; }
        @keyframes heartbeat { 0%,100%{transform:scale(1)} 50%{transform:scale(1.2)} }
        @media (max-width: 700px) { .main-title { font-size: 32px; } .dev-card { padding: 36px 20px; } .dev-name { font-size: 26px; } }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="header-badge">💎 КОМАНДА ПРОЕКТА</div>
        <h1 class="main-title"><span class="grad">Создатели Pharaonic Systems</span></h1>
        <p class="main-sub">Люди, которые создали и поддерживают этот проект</p>
        <div class="dev-card">
            <div class="avatar-frame"><img src="../<?php echo $dev['avatar'] ?? 'assets/default-avatar.png'; ?>" alt="<?php echo htmlspecialchars($dev['username']); ?>"><div class="ring"></div></div>
            <div class="dev-name"><?php echo htmlspecialchars($dev['username']); ?></div>
            <div class="dev-role"><?php echo $li[$lvl]; ?> ГЛАВНЫЙ РАЗРАБОТЧИК</div>
            <p class="dev-bio">Основатель и главный архитектор проекта. Создал всю экосистему с нуля — от серверной архитектуры до пользовательского интерфейса.</p>
            <div class="status-row">
                <span class="status-chip chip-online"><span class="dot-pulse"></span> Активен</span>
                <span class="status-chip chip-role">⚒️ Full-Stack Developer</span>
                <span class="status-chip chip-since">📅 С 2020 года</span>
            </div>
            <div class="skills-title">⚡ Технологический стек</div>
            <div class="skills-grid">
                <span class="skill s-php">🐘 PHP 8</span><span class="skill s-js">📜 JavaScript</span><span class="skill s-mysql">🗄️ MySQL</span><span class="skill s-python">🐍 Python</span>
                <span class="skill s-lua">🌙 Lua (SAMP)</span><span class="skill s-css">🎨 CSS3</span><span class="skill s-git">📦 Git</span><span class="skill s-api">🔌 REST API</span>
            </div>
        </div>
        <div class="footer">
            <p>© 2020 — <?php echo date('Y'); ?> Pharaonic Systems</p>
            <span class="year">⚡ Работаем с 2020 года</span>
            <p style="margin-top:8px;">Made with <span class="heart">❤️</span> by <?php echo htmlspecialchars($dev['username']); ?></p>
        </div>
    </div>
</body>
</html>