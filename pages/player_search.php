<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

$searchQuery = $_GET['q'] ?? '';
$player = null;
$properties = [];

if ($searchQuery) {
    $stmt = $pdo->prepare("SELECT * FROM game_players WHERE nickname LIKE ?");
    $stmt->execute(["%{$searchQuery}%"]);
    $player = $stmt->fetch();
    
    if ($player) {
        $stmt = $pdo->prepare("SELECT * FROM player_properties WHERE player_id = ?");
        $stmt->execute([$player['id']]);
        $properties = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>🔍 Поиск игрока — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 30px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        .search-form { display: flex; gap: 10px; margin-bottom: 24px; }
        .search-form input { flex: 1; padding: 14px 18px; background: var(--card); border: 2px solid var(--border); border-radius: 12px; color: var(--text); font-family: 'Montserrat', sans-serif; font-size: 14px; outline: none; }
        .search-form input:focus { border-color: var(--primary); }
        .search-form button { padding: 14px 28px; background: var(--primary); border: none; border-radius: 12px; color: white; font-weight: 600; cursor: pointer; font-size: 14px; }
        .card { background: var(--card); border: 2px solid var(--border); border-radius: 16px; padding: 24px; margin-bottom: 16px; }
        .card h3 { font-size: 18px; margin-bottom: 16px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 14px; }
        .info-row .lbl { color: var(--text2); }
        .prop-chip { padding: 6px 12px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 10px; font-size: 13px; display: inline-block; margin: 4px; }
        .empty-state { text-align: center; color: var(--text2); padding: 30px; }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>🔍 Поиск игрока</h1><p>Поиск по никнейму</p></div>
        
        <form class="search-form" method="GET">
            <input type="text" name="q" placeholder="Введите никнейм игрока..." value="<?php echo htmlspecialchars($searchQuery); ?>" required>
            <button type="submit">🔍 Найти</button>
        </form>
        
        <?php if($searchQuery && $player): ?>
        <div class="card">
            <h3>📋 Информация</h3>
            <div class="info-row"><span class="lbl">👤 Никнейм</span><strong><?php echo htmlspecialchars($player['nickname']); ?></strong></div>
            <div class="info-row"><span class="lbl">🎮 Уровень</span><strong><?php echo $player['level']; ?></strong></div>
            <div class="info-row"><span class="lbl">🕐 Часов</span><strong><?php echo $player['hours']; ?> ч</strong></div>
            <div class="info-row"><span class="lbl">📅 Регистрация</span><strong><?php echo date('d.m.Y', strtotime($player['register_date'] ?? $player['created_at'])); ?></strong></div>
            <div class="info-row"><span class="lbl">💰 Баланс</span><strong><?php echo number_format($player['balance'], 0, '', ' '); ?> ₽</strong></div>
            <div class="info-row"><span class="lbl">🕐 Последний вход</span><strong><?php echo $player['last_login'] ? date('d.m.Y H:i', strtotime($player['last_login'])) : '—'; ?></strong></div>
        </div>
        
        <?php if($player['faction']): ?>
        <div class="card">
            <h3>🏢 Фракция</h3>
            <div class="info-row"><span class="lbl">Название</span><strong><?php echo htmlspecialchars($player['faction']); ?></strong></div>
            <div class="info-row"><span class="lbl">Ранг</span><strong><?php echo htmlspecialchars($player['faction_rank'] ?? '—'); ?></strong></div>
            <div class="info-row"><span class="lbl">Лидер</span><strong><?php echo htmlspecialchars($player['faction_leader'] ?? '—'); ?></strong></div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h3>🏠 Имущество (<?php echo count($properties); ?>)</h3>
            <?php if(count($properties) > 0): ?>
                <?php foreach($properties as $prop): ?>
                <span class="prop-chip">
                    <?php echo $prop['type']==='house'?'🏠':($prop['type']==='business'?'🏪':'🚗'); ?>
                    <?php echo htmlspecialchars($prop['name']); ?>
                    <?php if($prop['location']): ?>— <?php echo htmlspecialchars($prop['location']); ?><?php endif; ?>
                </span>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:var(--text2);">Нет имущества</p>
            <?php endif; ?>
        </div>
        
        <?php elseif($searchQuery): ?>
        <div class="empty-state">🔍 Игрок не найден</div>
        <?php endif; ?>
    </div>
</body>
</html>