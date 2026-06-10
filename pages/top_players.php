<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

$tab = $_GET['tab'] ?? 'hours';

// Топ по часам
$stmt = $pdo->query("SELECT nickname, level, hours, faction, balance FROM game_players WHERE is_active = TRUE ORDER BY hours DESC LIMIT 20");
$topHours = $stmt->fetchAll();

// Топ по уровню
$stmt = $pdo->query("SELECT nickname, level, hours, faction, balance FROM game_players WHERE is_active = TRUE ORDER BY level DESC LIMIT 20");
$topLevel = $stmt->fetchAll();

// Топ по балансу
$stmt = $pdo->query("SELECT nickname, level, hours, faction, balance FROM game_players WHERE is_active = TRUE ORDER BY balance DESC LIMIT 20");
$topBalance = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>🏆 Топ игроков — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --gold: #f59e0b; --silver: #94a3b8; --bronze: #d97706; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 24px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; justify-content: center; }
        .tabs a { padding: 10px 22px; border-radius: 10px; color: var(--text2); text-decoration: none; font-weight: 500; font-size: 13px; border: 1px solid var(--border); transition: all 0.2s; }
        .tabs a:hover, .tabs a.active { background: var(--primary); color: white; border-color: var(--primary); }
        .player-row { display: flex; align-items: center; padding: 12px 16px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 8px; gap: 12px; }
        .rank { width: 30px; text-align: center; font-weight: 700; font-size: 16px; }
        .r1 { color: #f59e0b; } .r2 { color: #94a3b8; } .r3 { color: #d97706; }
        .name { flex: 1; font-weight: 600; }
        .stat { font-weight: 700; color: var(--primary); }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>🏆 Топ игроков</h1><p>Лучшие игроки сервера</p></div>
        
        <div class="tabs">
            <a href="?tab=hours" class="<?php echo $tab==='hours'?'active':''; ?>">🕐 По часам</a>
            <a href="?tab=level" class="<?php echo $tab==='level'?'active':''; ?>">🎮 По уровню</a>
            <a href="?tab=balance" class="<?php echo $tab==='balance'?'active':''; ?>">💰 По балансу</a>
        </div>
        
        <?php 
        $list = $tab === 'hours' ? $topHours : ($tab === 'level' ? $topLevel : $topBalance);
        foreach($list as $i => $p): 
        ?>
        <div class="player-row">
            <span class="rank <?php echo 'r'.($i+1); ?>">#<?php echo $i+1; ?></span>
            <span class="name"><?php echo htmlspecialchars($p['nickname']); ?></span>
            <span style="color:var(--text2);font-size:12px;">LvL <?php echo $p['level']; ?></span>
            <span class="stat">
                <?php echo $tab==='hours' ? $p['hours'].' ч' : ($tab==='level' ? 'LvL '.$p['level'] : number_format($p['balance'],0,'',' ').' ₽'); ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>