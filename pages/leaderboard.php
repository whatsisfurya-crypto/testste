<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/reputation.php';
require_once '../php/cache.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }
$rep = new Reputation($pdo);
$top = $rep->getTopUsers(20);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>🏆 Лидеры — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --gold: #f59e0b; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 30px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        .top3 { display: flex; justify-content: center; align-items: flex-end; gap: 24px; margin-bottom: 40px; }
        .tc { text-align: center; padding: 28px 20px; border-radius: 20px; }
        .tc.first { background: linear-gradient(180deg, rgba(245,158,11,0.2) 0%, transparent 100%); border: 1px solid rgba(245,158,11,0.3); padding-top: 48px; min-width: 180px; }
        .tc.second { background: rgba(148,163,184,0.08); border: 1px solid rgba(148,163,184,0.2); min-width: 150px; }
        .tc.third { background: rgba(217,119,6,0.08); border: 1px solid rgba(217,119,6,0.2); min-width: 150px; }
        .tc img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 12px; }
        .tc.first img { width: 100px; height: 100px; border: 4px solid #f59e0b; }
        .medal { font-size: 40px; margin-bottom: 8px; }
        .tc h3 { font-size: 18px; font-weight: 700; }
        .tc .score { font-size: 24px; font-weight: 800; } .tc.first .score { color: #f59e0b; }
        .item { display: flex; align-items: center; gap: 16px; padding: 16px 20px; background: var(--card); border: 1px solid var(--border); border-radius: 14px; margin-bottom: 10px; }
        .item:hover { border-color: var(--primary); }
        .item .rank { width: 36px; text-align: center; font-weight: 700; font-size: 16px; }
        .item img { width: 48px; height: 48px; border-radius: 50%; }
        .item .name { flex: 1; font-weight: 600; }
        .item .rep { font-weight: 700; color: var(--primary); font-size: 18px; }
        @media(max-width:600px){.top3{flex-direction:column;align-items:center}}
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>🏆 Таблица лидеров</h1><p>Рейтинг администраторов по репутации</p></div>
        <?php if(count($top)>=3): ?>
        <div class="top3">
            <div class="tc second"><div class="medal">🥈</div><img src="../<?php echo $top[1]['avatar']??'assets/default-avatar.png'; ?>"><h3><?php echo htmlspecialchars($top[1]['username']); ?></h3><div class="score">⭐ <?php echo $top[1]['reputation']; ?></div></div>
            <div class="tc first"><div class="medal">👑</div><img src="../<?php echo $top[0]['avatar']??'assets/default-avatar.png'; ?>"><h3><?php echo htmlspecialchars($top[0]['username']); ?></h3><div class="score">⭐ <?php echo $top[0]['reputation']; ?></div></div>
            <div class="tc third"><div class="medal">🥉</div><img src="../<?php echo $top[2]['avatar']??'assets/default-avatar.png'; ?>"><h3><?php echo htmlspecialchars($top[2]['username']); ?></h3><div class="score">⭐ <?php echo $top[2]['reputation']; ?></div></div>
        </div>
        <?php endif; ?>
        <?php foreach($top as $i=>$u): ?>
        <div class="item"><span class="rank">#<?php echo $i+1; ?></span><img src="../<?php echo $u['avatar']??'assets/default-avatar.png'; ?>"><span class="name"><?php echo htmlspecialchars($u['username']); ?></span><span class="rep">⭐ <?php echo $u['reputation']; ?></span></div>
        <?php endforeach; ?>
    </div>
</body>
</html>