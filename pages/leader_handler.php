<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
require_once '../php/roles_system.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

$roles = RolesSystem::getInstance($pdo);
$myId = $_SESSION['user_id'];
$myLevel = $roles->getEffectiveLevel($myId);
$myPos = $roles->getUserPosition($myId);
$deptName = $myPos['department_name'] ?? '';
$posName = $myPos['position_name'] ?? '';

$canManage = ($myLevel >= 7) || 
    ($myLevel >= 5 && $deptName === 'Гос.структуры' && (strpos($posName,'ГС')!==false || strpos($posName,'ЗГС')!==false)) ||
    ($deptName === 'Нелегал' && (strpos($posName,'ГС')!==false || strpos($posName,'ЗГС')!==false));

if (!$canManage) { header('Location: dashboard.php'); exit(); }

$tab = $_GET['tab'] ?? 'list';
$message = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);

$pdo->exec("CREATE TABLE IF NOT EXISTS leader_invites (id INT PRIMARY KEY AUTO_INCREMENT, code VARCHAR(32) UNIQUE, department VARCHAR(50), position VARCHAR(100), created_by INT, is_used BOOLEAN DEFAULT FALSE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE IF NOT EXISTS leader_applications (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT, faction VARCHAR(100), position VARCHAR(100), nickname VARCHAR(50), how_became TEXT, phone_call TEXT, transfer TEXT, discord VARCHAR(100), forum_url VARCHAR(255), vk_url VARCHAR(255), vk_id VARCHAR(50), status ENUM('pending','approved','rejected') DEFAULT 'pending', reviewed_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$departments = $roles->getAllDepartmentsList();

// Создание анкеты
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $code = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO leader_invites (code, department, position, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$code, $_POST['department'], $_POST['position'], $myId]);
    $message = "✅ Анкета создана!<br>🔗 <a href='".SITE_URL."/leader_apply.php?code={$code}' style='color:var(--primary);'>".SITE_URL."/leader_apply.php?code={$code}</a>";
}

// Одобрение/отклонение
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review') {
    $appId = (int)$_POST['app_id'];
    $status = $_POST['status'];
    if (in_array($status, ['approved', 'rejected'])) {
        $stmt = $pdo->prepare("UPDATE leader_applications SET status = ?, reviewed_by = ? WHERE id = ?");
        $stmt->execute([$status, $myId, $appId]);
        $stmt2 = $pdo->prepare("INSERT INTO action_logs (user_id, action, description, ip_address) VALUES (?, 'leader_review', ?, ?)");
        $stmt2->execute([$myId, ($status==='approved'?'Одобрена':'Отклонена')." анкета лидера #{$appId}", $_SERVER['REMOTE_ADDR']]);
        if ($status === 'approved') {
            $stmt = $pdo->prepare("SELECT * FROM leader_applications WHERE id = ?");
            $stmt->execute([$appId]);
            $app = $stmt->fetch();
            if ($app) $roles->assignPosition($app['user_id'], 'leadership', $_POST['position_key'], $myId);
        }
        $_SESSION['msg'] = "✅ Анкета " . ($status === 'approved' ? 'одобрена' : 'отклонена') . "!";
        header('Location: leader_handler.php?tab=applications'); exit();
    }
}

// Снятие
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_leader') {
    $roles->removePosition((int)$_POST['user_id']);
    $_SESSION['msg'] = "✅ Снят!";
    header('Location: leader_handler.php?tab=list'); exit();
}

// Анкеты
$stmt = $pdo->query("SELECT la.*, u.username FROM leader_applications la JOIN users u ON la.user_id = u.id ORDER BY la.created_at DESC");
$applications = $stmt->fetchAll();

// Лидеры
$stmt = $pdo->query("SELECT u.id, u.username, u.avatar, ap.position_key FROM admin_positions ap JOIN users u ON ap.user_id = u.id WHERE ap.department = 'leadership' ORDER BY ap.position_key");
$allLeaders = $stmt->fetchAll();

$govLeaders = array_filter($allLeaders, function($l) { return strpos($l['position_key'], 'gov') !== false && strpos($l['position_key'], 'leader') !== false; });
$govDeputies = array_filter($allLeaders, function($l) { return strpos($l['position_key'], 'gov') !== false && strpos($l['position_key'], 'deputy') !== false; });
$illegalLeaders = array_filter($allLeaders, function($l) { return strpos($l['position_key'], 'illegal') !== false && strpos($l['position_key'], 'leader') !== false; });
$illegalDeputies = array_filter($allLeaders, function($l) { return strpos($l['position_key'], 'illegal') !== false && strpos($l['position_key'], 'deputy') !== false; });
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>👑 Управление лидерами — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --red: #ef4444; --yellow: #f59e0b; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 20px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        .alert { padding: 12px 18px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }
        .alert a { color: var(--primary); word-break: break-all; }
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; justify-content: center; }
        .tabs a { padding: 10px 22px; border-radius: 10px; color: var(--text2); text-decoration: none; font-weight: 500; font-size: 13px; border: 1px solid var(--border); transition: all 0.2s; }
        .tabs a:hover, .tabs a.active { background: var(--primary); color: white; border-color: var(--primary); }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px; margin-bottom: 12px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .badge { padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 600; }
        .badge-pending { background: rgba(245,158,11,0.1); color: var(--yellow); }
        .badge-approved { background: rgba(16,185,129,0.1); color: var(--green); }
        .badge-rejected { background: rgba(239,68,68,0.1); color: var(--red); }
        .info { font-size: 13px; color: var(--text2); line-height: 1.6; }
        .info strong { color: var(--text); }
        .btn { padding: 10px 20px; border: none; border-radius: 10px; color: white; font-weight: 600; cursor: pointer; font-size: 13px; }
        .btn-green { background: var(--green); }
        .btn-sm { padding: 6px 14px; font-size: 12px; border-radius: 8px; }
        .btn-red { background: var(--red); }
        .save-btn { width: 100%; padding: 12px; background: var(--primary); border: none; border-radius: 10px; color: white; font-weight: 600; cursor: pointer; font-size: 14px; margin-top: 8px; }
        .leader-chip { padding: 5px 12px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 20px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; margin: 3px; }
        .leader-chip img { width: 22px; height: 22px; border-radius: 50%; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; width: 100%; max-width: 450px; }
        .close-btn { background: none; border: none; color: var(--text2); font-size: 22px; cursor: pointer; float: right; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 4px; color: var(--text2); font-size: 13px; }
        .form-group select { width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 2px solid var(--border); border-radius: 10px; color: var(--text); font-family: 'Montserrat', sans-serif; font-size: 13px; outline: none; }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>👑 Управление лидерами</h1><p>Список лидеров и анкеты на рассмотрение</p></div>
        <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
        
        <div class="tabs">
            <a href="?tab=list" class="<?php echo $tab==='list'?'active':''; ?>">👥 Список Лидеров/Заместителей</a>
            <a href="?tab=applications" class="<?php echo $tab==='applications'?'active':''; ?>">📝 Анкеты на рассмотрение</a>
        </div>
        
        <?php if($tab === 'list'): ?>
        <h3 style="margin-bottom:16px;">👥 Список Лидеров/Заместителей</h3>
        <?php 
        $categories = [
            ['title' => '👑 Лидерство Гос.Структур', 'color' => '#3b82f6', 'items' => $govLeaders],
            ['title' => '🎖️ Заместительство Гос.Структур', 'color' => '#60a5fa', 'items' => $govDeputies],
            ['title' => '👑 Лидерство Нелегал.Организаций', 'color' => '#ef4444', 'items' => $illegalLeaders],
            ['title' => '🎖️ Заместительство Нелегал.Организаций', 'color' => '#f87171', 'items' => $illegalDeputies],
        ];
        foreach ($categories as $cat): ?>
        <div class="card" style="border-left:4px solid <?php echo $cat['color']; ?>;margin-bottom:10px;">
            <h4><?php echo $cat['title']; ?> (<?php echo count($cat['items']); ?>)</h4>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                <?php if(count($cat['items']) > 0): ?>
                    <?php foreach($cat['items'] as $m): ?>
                    <span class="leader-chip">
                        <img src="../<?php echo $m['avatar']??'assets/default-avatar.png'; ?>" alt="">
                        <?php echo htmlspecialchars($m['username']); ?>
                        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="remove_leader"><input type="hidden" name="user_id" value="<?php echo $m['id']; ?>"><button type="submit" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:12px;">×</button></form>
                    </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span style="color:var(--text2);font-size:12px;">Нет назначенных</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php else: ?>
        <button class="btn btn-green" onclick="document.getElementById('createModal').classList.add('active')" style="margin-bottom:16px;">+ Создать анкету</button>
        <?php foreach($applications as $app): ?>
        <div class="card">
            <div class="card-header">
                <strong><?php echo htmlspecialchars($app['username']); ?></strong>
                <span class="badge badge-<?php echo $app['status']; ?>"><?php echo ['pending'=>'⏳ На рассмотрении','approved'=>'✅ Одобрено','rejected'=>'❌ Отклонено'][$app['status']]; ?></span>
            </div>
            <div class="info">
                <strong>Фракция:</strong> <?php echo htmlspecialchars($app['faction']); ?><br>
                <strong>Должность:</strong> <?php echo htmlspecialchars($app['position']); ?><br>
                <strong>Discord:</strong> <?php echo htmlspecialchars($app['discord']); ?><br>
                <?php if($app['status']==='pending'): ?>
                <div style="margin-top:10px;display:flex;gap:8px;">
                    <form method="POST"><input type="hidden" name="action" value="review"><input type="hidden" name="app_id" value="<?php echo $app['id']; ?>"><input type="hidden" name="status" value="approved"><input type="hidden" name="position_key" value="<?php echo $app['position']; ?>"><button class="btn btn-green btn-sm">✅ Одобрить</button></form>
                    <form method="POST"><input type="hidden" name="action" value="review"><input type="hidden" name="app_id" value="<?php echo $app['id']; ?>"><input type="hidden" name="status" value="rejected"><button class="btn-sm btn-red">❌ Отклонить</button></form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="modal" id="createModal"><div class="modal-content">
        <button class="close-btn" onclick="document.getElementById('createModal').classList.remove('active')">&times;</button>
        <h3>📝 Создать анкету лидера</h3>
        <form method="POST"><input type="hidden" name="action" value="create">
            <div class="form-group"><label>Должность</label><select name="position" required>
                <option value="">— Выбрать —</option>
                <option value="leader_gov">👑 Лидер Гос.Структур</option>
                <option value="deputy_gov">🎖️ Заместитель Гос.Структур</option>
                <option value="leader_illegal">👑 Лидер Нелегал.Организаций</option>
                <option value="deputy_illegal">🎖️ Заместитель Нелегал.Организаций</option>
            </select></div>
            <input type="hidden" name="department" value="leadership">
            <button type="submit" class="save-btn" style="background:var(--green);">🔗 Создать ссылку</button>
        </form>
    </div></div>
</body>
</html>