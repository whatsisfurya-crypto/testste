<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/roles_system.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

$roles = RolesSystem::getInstance($pdo);
$myId = $_SESSION['user_id'];
$myLevel = $roles->getEffectiveLevel($myId);
$myPos = $roles->getUserPosition($myId);
$deptName = $myPos['department_name'] ?? '';
$posName = $myPos['position_name'] ?? '';

// Кто может управлять
$canManage = ($myLevel >= 7) || 
    ($myLevel >= 5 && $deptName === 'Гос.структуры' && (strpos($posName,'ГС')!==false || strpos($posName,'ЗГС')!==false)) ||
    ($deptName === 'Нелегал' && (strpos($posName,'ГС')!==false || strpos($posName,'ЗГС')!==false));

if (!$canManage) { header('Location: ../pages/dashboard.php'); exit(); }

$tab = $_GET['tab'] ?? 'applications';
$message = '';

$pdo->exec("CREATE TABLE IF NOT EXISTS leader_invites (id INT PRIMARY KEY AUTO_INCREMENT, code VARCHAR(32) UNIQUE, department VARCHAR(50), position VARCHAR(100), created_by INT, is_used BOOLEAN DEFAULT FALSE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE IF NOT EXISTS leader_applications (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT, faction VARCHAR(100), position VARCHAR(100), nickname VARCHAR(50), how_became TEXT, phone_call TEXT, transfer TEXT, discord VARCHAR(100), forum_url VARCHAR(255), vk_url VARCHAR(255), status ENUM('pending','approved','rejected') DEFAULT 'pending', reviewed_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$departments = $roles->getAllDepartmentsList();
$stmt = $pdo->query("SELECT id, username FROM users ORDER BY username");
$allUsers = $stmt->fetchAll();

// ========== СОЗДАНИЕ АНКЕТЫ ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $code = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO leader_invites (code, department, position, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$code, $_POST['department'], $_POST['position'], $myId]);
    $link = SITE_URL . "/leader_apply.php?code=" . $code;
    $message = "✅ Анкета создана!<br>🔗 <a href='{$link}' style='color:var(--primary);'>{$link}</a>";
}

// ========== ОДОБРЕНИЕ/ОТКЛОНЕНИЕ АНКЕТЫ ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review') {
    $appId = (int)$_POST['app_id'];
    $status = $_POST['status'];
    
    if (in_array($status, ['approved', 'rejected'])) {
        $stmt = $pdo->prepare("UPDATE leader_applications SET status = ?, reviewed_by = ? WHERE id = ?");
        $stmt->execute([$status, $myId, $appId]);
        
        if ($status === 'approved') {
            $stmt = $pdo->prepare("SELECT * FROM leader_applications WHERE id = ?");
            $stmt->execute([$appId]);
            $app = $stmt->fetch();
            if ($app) {
                $roles->assignPosition($app['user_id'], $_POST['department'], 'leader', $myId);
            }
        }
        $message = "✅ Анкета " . ($status === 'approved' ? 'одобрена' : 'отклонена') . "!";
    }
}

// ========== НАЗНАЧЕНИЕ ЛИДЕРА/ЗАМА ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'appoint_leader') {
    $userId = (int)$_POST['user_id'];
    $dept = $_POST['department'];
    $pos = $_POST['position'];
    
    if ($userId && $dept && $pos) {
        $roles->assignPosition($userId, $dept, $pos, $myId);
        $message = "✅ Лидер/Заместитель назначен!";
    }
}

// ========== СНЯТИЕ С ДОЛЖНОСТИ ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_leader') {
    $userId = (int)$_POST['user_id'];
    $roles->removePosition($userId);
    $message = "✅ Снят с должности!";
}

// ========== ВСЕ АНКЕТЫ ==========
$stmt = $pdo->query("SELECT la.*, u.username FROM leader_applications la JOIN users u ON la.user_id = u.id ORDER BY la.created_at DESC");
$applications = $stmt->fetchAll();

// ========== ВСЕ ЛИДЕРЫ ==========
$stmt = $pdo->query("SELECT u.id, u.username, u.avatar, u.admin_level, ap.department, ap.position_key FROM admin_positions ap JOIN users u ON ap.user_id = u.id WHERE ap.position_key IN ('leader','deputy') ORDER BY ap.department");
$allLeaders = $stmt->fetchAll();
$byDept = [];
foreach ($allLeaders as $l) { $byDept[$l['department']][] = $l; }
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
        
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; justify-content: center; flex-wrap: wrap; }
        .tabs a { padding: 10px 22px; border-radius: 10px; color: var(--text2); text-decoration: none; font-weight: 500; font-size: 13px; border: 1px solid var(--border); transition: all 0.2s; }
        .tabs a:hover, .tabs a.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px; margin-bottom: 12px; }
        .card h4 { margin-bottom: 10px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .badge { padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 600; }
        .badge-pending { background: rgba(245,158,11,0.1); color: var(--yellow); }
        .badge-approved { background: rgba(16,185,129,0.1); color: var(--green); }
        .badge-rejected { background: rgba(239,68,68,0.1); color: var(--red); }
        .info { font-size: 13px; color: var(--text2); line-height: 1.6; }
        .info strong { color: var(--text); }
        
        .btn { padding: 10px 20px; border: none; border-radius: 10px; color: white; font-weight: 600; cursor: pointer; font-size: 13px; }
        .btn-green { background: var(--green); }
        .btn-primary { background: var(--primary); }
        .btn-sm { padding: 6px 14px; font-size: 12px; border-radius: 8px; }
        
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 4px; color: var(--text2); font-size: 13px; }
        .form-group select, .form-group input { width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 2px solid var(--border); border-radius: 10px; color: var(--text); font-family: 'Montserrat', sans-serif; font-size: 13px; outline: none; }
        .save-btn { width: 100%; padding: 12px; background: var(--primary); border: none; border-radius: 10px; color: white; font-weight: 600; cursor: pointer; font-size: 14px; margin-top: 8px; }
        
        .leader-chip { padding: 5px 12px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 20px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; margin: 3px; }
        .leader-chip img { width: 22px; height: 22px; border-radius: 50%; }
        .leader-chip .pos-tag { font-size: 10px; padding: 2px 6px; border-radius: 4px; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; width: 100%; max-width: 450px; }
        .close-btn { background: none; border: none; color: var(--text2); font-size: 22px; cursor: pointer; float: right; }
    </style>
</head>
<body>
    <header class="topbar"><a href="../pages/dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>👑 Управление лидерами</h1><p>Анкеты, список, назначение</p></div>
        <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
        
        <div class="tabs">
            <a href="?tab=applications" class="<?php echo $tab==='applications'?'active':''; ?>">📝 Анкеты</a>
            <a href="?tab=list" class="<?php echo $tab==='list'?'active':''; ?>">👥 Список лидеров</a>
            <a href="?tab=appoint" class="<?php echo $tab==='appoint'?'active':''; ?>">📌 Назначить</a>
        </div>
        
        <?php if($tab === 'applications'): ?>
        <!-- АНКЕТЫ -->
        <button class="btn btn-green" onclick="document.getElementById('createModal').classList.add('active')" style="margin-bottom:16px;">+ Создать анкету</button>
        <?php foreach($applications as $app): ?>
        <div class="card">
            <div class="card-header">
                <strong><?php echo htmlspecialchars($app['username']); ?></strong>
                <span class="badge badge-<?php echo $app['status']; ?>"><?php echo ['pending'=>'⏳ На проверке','approved'=>'✅ Одобрено','rejected'=>'❌ Отклонено'][$app['status']]; ?></span>
            </div>
            <div class="info">
                <strong>Фракция:</strong> <?php echo htmlspecialchars($app['faction']); ?><br>
                <strong>Должность:</strong> <?php echo htmlspecialchars($app['position']); ?><br>
                <strong>Discord:</strong> <?php echo htmlspecialchars($app['discord']); ?><br>
                <?php if($app['status']==='pending'): ?>
                <div style="margin-top:10px;display:flex;gap:8px;">
                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="review"><input type="hidden" name="app_id" value="<?php echo $app['id']; ?>"><input type="hidden" name="status" value="approved"><input type="hidden" name="department" value="<?php echo $app['faction']; ?>"><button type="submit" class="btn btn-green btn-sm">✅ Одобрить</button></form>
                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="review"><input type="hidden" name="app_id" value="<?php echo $app['id']; ?>"><input type="hidden" name="status" value="rejected"><button type="submit" class="btn-sm" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:var(--red);border-radius:8px;cursor:pointer;">❌ Отклонить</button></form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php elseif($tab === 'list'): ?>
        <!-- СПИСОК ЛИДЕРОВ -->
        <h3 style="margin-bottom:16px;">👥 Список лидеров и заместителей</h3>
        <?php if(empty($byDept)): ?><p style="color:var(--text2);text-align:center;">Нет назначенных лидеров</p><?php endif; ?>
        <?php foreach($byDept as $dept => $members): $di = $departments[$dept] ?? ['name'=>$dept,'color'=>'#888']; ?>
        <div class="card" style="border-left:4px solid <?php echo $di['color']; ?>;">
            <h4><?php echo $di['name']; ?> (<?php echo count($members); ?>)</h4>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                <?php foreach($members as $m): ?>
                <span class="leader-chip">
                    <img src="../<?php echo $m['avatar']??'assets/default-avatar.png'; ?>" alt="">
                    <?php echo htmlspecialchars($m['username']); ?>
                    <span class="pos-tag" style="background:<?php echo $di['color']; ?>20;color:<?php echo $di['color']; ?>;"><?php echo $m['position_key']==='leader'?'👑 Лидер':'🎖️ Зам'; ?></span>
                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="remove_leader"><input type="hidden" name="user_id" value="<?php echo $m['id']; ?>"><button type="submit" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:12px;">×</button></form>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php else: ?>
        <!-- НАЗНАЧИТЬ -->
        <h3 style="margin-bottom:16px;">📌 Назначить лидера или заместителя</h3>
        <form method="POST" class="card">
            <input type="hidden" name="action" value="appoint_leader">
            <div class="form-group"><label>Сотрудник</label><select name="user_id" required><option value="">— Выбрать —</option><?php foreach($allUsers as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Отдел</label><select name="department" required><option value="">— Выбрать —</option><?php foreach($departments as $dk=>$dv): ?><option value="<?php echo $dk; ?>"><?php echo $dv['name']; ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Должность</label><select name="position" required><option value="">— Выбрать —</option><option value="leader">👑 Лидер</option><option value="deputy">🎖️ Заместитель</option></select></div>
            <button type="submit" class="save-btn" style="background:var(--green);">📌 Назначить</button>
        </form>
        <?php endif; ?>
    </div>
    
    <!-- Модалка создания анкеты -->
    <div class="modal" id="createModal"><div class="modal-content">
        <button class="close-btn" onclick="document.getElementById('createModal').classList.remove('active')">&times;</button>
        <h3>📝 Создать анкету лидера</h3>
        <form method="POST"><input type="hidden" name="action" value="create">
            <div class="form-group"><label>Отдел</label><select name="department" required><option value="">— Выбрать —</option><?php foreach($departments as $dk=>$dv): ?><option value="<?php echo $dk; ?>"><?php echo $dv['name']; ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Должность</label><input type="text" name="position" placeholder="Например: Лидер LSPD" required></div>
            <button type="submit" class="save-btn" style="background:var(--green);">🔗 Создать ссылку</button>
        </form>
    </div></div>
</body>
</html>