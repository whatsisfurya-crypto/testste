<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

$userId = $_SESSION['user_id'];

// Получаем game_nickname пользователя
$stmt = $pdo->prepare("SELECT game_nickname FROM users WHERE id = ?");
$stmt->execute([$userId]);
$gameNickname = $stmt->fetchColumn();

if (!$gameNickname) {
    die('❌ У вас нет привязанного игрового аккаунта.');
}

// Ищем игрока
$stmt = $pdo->prepare("SELECT * FROM game_players WHERE nickname = ?");
$stmt->execute([$gameNickname]);
$player = $stmt->fetch();

if (!$player) {
    die('❌ Игрок не найден в базе.');
}

// Имущество
$stmt = $pdo->prepare("SELECT * FROM player_properties WHERE player_id = ?");
$stmt->execute([$player['id']]);
$properties = $stmt->fetchAll();

// Наказания (из общей таблицы если есть)
$stmt = $pdo->prepare("SELECT COUNT(*) as bans FROM admin_punishments WHERE user_id = ? AND type = 'ban'");
$stmt->execute([$userId]);
$bans = $stmt->fetchColumn();

$levelNames = [0=>'Пользователь',1=>'Хелпер',2=>'Мл. модератор',3=>'Модератор',4=>'Администратор',5=>'Куратор',6=>'Зам. ГА',7=>'Главный Админ',8=>'Основатель',9=>'Разработчик'];
$levelColors = [0=>'#9CA3AF',1=>'#9CA3AF',2=>'#60A5FA',3=>'#3B82F6',4=>'#10B981',5=>'#F59E0B',6=>'#F97316',7=>'#EF4444',8=>'#8B5CF6',9=>'#EC4899'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>👤 Моя статистика — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --red: #ef4444; --yellow: #f59e0b; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 30px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        .card { background: var(--card); border: 2px solid var(--border); border-radius: 16px; padding: 24px; margin-bottom: 16px; }
        .card h3 { font-size: 18px; margin-bottom: 16px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 14px; }
        .info-row .lbl { color: var(--text2); }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .stat-box { text-align: center; padding: 14px; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 12px; }
        .stat-box .val { font-size: 22px; font-weight: 800; }
        .stat-box .lbl { font-size: 11px; color: var(--text2); margin-top: 4px; }
        .prop-chip { padding: 6px 12px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 10px; font-size: 13px; display: inline-block; margin: 4px; }
        @media (max-width: 600px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>👤 Моя статистика</h1><p><?php echo htmlspecialchars($player['nickname']); ?></p></div>
        
        <div class="card">
            <h3>📋 Основная информация</h3>
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
            <h3>📊 Наказания</h3>
            <div class="stats-grid">
                <div class="stat-box"><div class="val">🚫</div><div class="lbl">Бан</div></div>
                <div class="stat-box"><div class="val">⚡</div><div class="lbl">Кик</div></div>
                <div class="stat-box"><div class="val">⚠️</div><div class="lbl">Варн</div></div>
                <div class="stat-box"><div class="val">🔇</div><div class="lbl">Мут</div></div>
            </div>
        </div>
        
        <div class="card">
            <h3>🏠 Имущество (<?php echo count($properties); ?>)</h3>
            <?php if(count($properties) > 0): ?>
                <?php foreach($properties as $prop): ?>
                <span class="prop-chip">
                    <?php echo $prop['type']==='house'?'🏠':($prop['type']==='business'?'🏪':'🚗'); ?>
                    <?php echo htmlspecialchars($prop['name']); ?>
                    <?php if($prop['location']): ?>— <?php echo htmlspecialchars($prop['location']); ?><?php endif; ?>
                    <?php if($prop['price']): ?>(<?php echo number_format($prop['price'],0,'',' '); ?> ₽)<?php endif; ?>
                </span>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:var(--text2);">Нет имущества</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>