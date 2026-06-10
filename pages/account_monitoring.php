<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/roles_system.php';
require_once '../php/account_monitor.php';
require_once '../php/cache.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }
$roles = RolesSystem::getInstance($pdo);
$roles->requirePermission('monitor.search_accounts');
$monitor = new AccountMonitor($pdo);
$q = $_GET['search'] ?? '';
$results = [];
if ($q) { $stmt = $pdo->prepare("SELECT * FROM users WHERE username LIKE ? OR id = ?"); $stmt->execute(["%$q%", $q]); $results = $stmt->fetchAll(); foreach ($results as &$u) { $u['linked'] = $monitor->findMultiAccounts($u['id']); $u['sec'] = $monitor->getSecurityReport($u['id']); } }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>🔍 Проверка — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --red: #ef4444; --yellow: #f59e0b; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 28px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        .search { display: flex; gap: 10px; margin-bottom: 28px; }
        .search input { flex: 1; padding: 14px 18px; background: var(--card); border: 2px solid var(--border); border-radius: 14px; color: var(--text); font-family: 'Montserrat', sans-serif; font-size: 14px; outline: none; }
        .search input:focus { border-color: var(--primary); }
        .search button { padding: 14px 28px; background: var(--primary); border: none; border-radius: 14px; color: white; font-weight: 600; cursor: pointer; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; margin-bottom: 16px; }
        .risk { display: inline-block; padding: 5px 14px; border-radius: 10px; font-size: 12px; font-weight: 600; }
        .risk-low { background: rgba(16,185,129,0.1); color: var(--green); } .risk-medium { background: rgba(245,158,11,0.1); color: var(--yellow); } .risk-high { background: rgba(239,68,68,0.1); color: var(--red); }
        .linked { padding: 10px; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 10px; margin-top: 6px; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>🔍 Проверка аккаунтов</h1><p>Поиск мультиаккаунтов и связей</p></div>
        <form class="search"><input type="text" name="search" placeholder="Никнейм или ID..." value="<?php echo htmlspecialchars($q); ?>"><button>🔍 Искать</button></form>
        <?php if($q&&$results): foreach($results as $u): ?>
        <div class="card"><h3><?php echo htmlspecialchars($u['username']); ?> <span class="risk risk-<?php echo $u['sec']['risk_level']; ?>">Риск: <?php echo $u['sec']['risk_score']; ?>%</span></h3>
        <?php if(!empty($u['linked'])): ?><p style="font-size:13px;color:var(--text2);">🔗 Связи:</p><?php foreach($u['linked'] as $l): ?><div class="linked"><span><?php echo htmlspecialchars($l['user']['username']); ?></span><span style="color:var(--text2);"><?php echo $l['link_type']; ?></span><strong><?php echo $l['confidence']; ?>%</strong></div><?php endforeach; endif; ?></div>
        <?php endforeach; elseif($q): ?><p style="text-align:center;color:var(--text2);">Ничего не найдено</p><?php endif; ?>
    </div>
</body>
</html>