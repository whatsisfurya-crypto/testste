<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
require_once '../php/roles_system.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

$roles = RolesSystem::getInstance($pdo);
$myId = $_SESSION['user_id'];
$myLevel = $roles->getEffectiveLevel($myId);
$myPosData = $roles->getUserPosition($myId);
$myDept = $myPosData['department_name'] ?? '';
$myPosName = $myPosData['position_name'] ?? '';
$message = '';
$tab = $_GET['tab'] ?? 'list';

$pdo->exec("CREATE TABLE IF NOT EXISTS admin_invites (id INT PRIMARY KEY AUTO_INCREMENT, code VARCHAR(32) UNIQUE NOT NULL, level INT DEFAULT 1, department VARCHAR(50), position_key VARCHAR(50), created_by INT, is_used BOOLEAN DEFAULT FALSE, status ENUM('pending','approved','rejected') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_punishments (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT, type VARCHAR(50), reason TEXT, given_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$canSeeApplications = ($myLevel >= 5) || ($myDept === 'Следящие за хелперами' && (strpos($myPosName, 'ЗГС')!==false || strpos($myPosName, 'ГС')!==false));
$canInvite = $canSeeApplications;
$canApprove = ($myLevel >= 6);

// Одобрение анкеты с назначением должности
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_admin' && $canApprove) {
    $uid = (int)$_POST['user_id'];
    $stmt = $pdo->prepare("UPDATE users SET is_active = TRUE WHERE id = ? AND is_active = FALSE");
    $stmt->execute([$uid]);
    if ($stmt->rowCount() > 0) {
        $message = "✅ Администратор активирован!";
        $stmt2 = $pdo->prepare("SELECT * FROM admin_invites WHERE is_used = TRUE ORDER BY created_at DESC LIMIT 1");
        $stmt2->execute();
        $inv = $stmt2->fetch();
        if ($inv && $inv['department'] && $inv['position_key']) {
            $roles->assignPosition($uid, $inv['department'], $inv['position_key'], $myId);
        }
        $stmt2 = $pdo->prepare("INSERT INTO action_logs (user_id, action, description, ip_address) VALUES (?, 'approve_admin', ?, ?)");
        $stmt2->execute([$myId, "Активирован администратор #{$uid}", $_SERVER['REMOTE_ADDR']]);
    }
}

// Приглашение
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'invite' && $canInvite) {
    $level = (int)$_POST['level'];
    $posData = explode(':', $_POST['position'] ?? '');
    $dept = $posData[0] ?? ''; $pos = $posData[1] ?? '';
    if ($level >= 1 && $level < $myLevel) {
        $code = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("INSERT INTO admin_invites (code, level, department, position_key, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$code, $level, $dept, $pos, $myId]);
        $message = "✅ Приглашение создано!<br>🔗 <a href='".SITE_URL."/register.php?code={$code}' style='color:var(--primary);'>".SITE_URL."/register.php?code={$code}</a>";
    } else { $message = "❌ Нельзя выдать уровень выше своего!"; }
}

// Наказание
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'punish') {
    $userId = (int)$_POST['user_id']; $type = $_POST['punish_type']; $reason = trim($_POST['reason']);
    if ($userId && $type && $reason && $myLevel >= 5) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_punishments WHERE user_id = ? AND type = ?");
        $stmt->execute([$userId, $type]); $count = $stmt->fetchColumn();
        if (($type === 'warning' || $type === 'reprimand') && $count >= 3) { $message = "❌ Уже 3!"; }
        else {
            $stmt = $pdo->prepare("INSERT INTO admin_punishments (user_id, type, reason, given_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $type, $reason, $myId]);
            if ($type === 'warning') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_punishments WHERE user_id = ? AND type = 'warning'");
                $stmt->execute([$userId]);
                if ($stmt->fetchColumn() >= 3) {
                    $stmt = $pdo->prepare("DELETE FROM admin_punishments WHERE user_id = ? AND type = 'warning' LIMIT 3");
                    $stmt->execute([$userId]);
                    $stmt = $pdo->prepare("INSERT INTO admin_punishments (user_id, type, reason, given_by) VALUES (?, 'reprimand', 'Авто: 3 пред → 1 выговор', ?)");
                    $stmt->execute([$userId, $myId]);
                    $message = "✅ 3 предупреждения → 1 выговор!";
                } else { $message = "✅ Предупреждение выдано!"; }
            } else { $message = "✅ Выговор выдан!"; }
        }
    }
}

// Репутация
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reputation') {
    $userId = (int)$_POST['user_id']; $points = (int)$_POST['points'];
    if ($userId && $points != 0 && $myLevel >= 5) {
        $stmt = $pdo->prepare("INSERT INTO reputation (from_user_id, to_user_id, points, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$myId, $userId, $points, trim($_POST['comment'] ?? '')]);
        $message = "✅ Репутация изменена!";
    }
}

// Префикс
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'prefix') {
    $userId = (int)$_POST['user_id']; $clear = isset($_POST['clear_prefix']);
    $newPrefix = $clear ? '' : trim($_POST['prefix'] ?? '');
    if ($userId && $myLevel >= 5) {
        $stmt = $pdo->prepare("UPDATE users SET prefix = ? WHERE id = ?");
        $stmt->execute([$newPrefix, $userId]);
        $message = $clear ? "✅ Префикс удалён!" : "✅ Префикс обновлён!";
    }
}

// Активные админы
$stmt = $pdo->query("SELECT u.*, s.bans_count, s.kicks_count, s.warns_count, s.online_hours, COALESCE(SUM(r.points),0) as rep, (SELECT COUNT(*) FROM admin_punishments WHERE user_id=u.id AND type='warning') as warns, (SELECT COUNT(*) FROM admin_punishments WHERE user_id=u.id AND type='reprimand') as reprs FROM users u LEFT JOIN admin_stats s ON u.id=s.user_id LEFT JOIN reputation r ON u.id=r.to_user_id WHERE u.is_active = TRUE GROUP BY u.id ORDER BY u.admin_level DESC");
$admins = $stmt->fetchAll();

// Неактивные
$stmt = $pdo->query("SELECT * FROM users WHERE is_active = FALSE ORDER BY created_at DESC");
$pendingAdmins = $stmt->fetchAll();

$departments = $roles->getAllDepartmentsList();
$li = [1=>'🛡️',2=>'🔰',3=>'⚖️',4=>'🔱',5=>'👁️',6=>'🎖️',7=>'👑',8=>'⚔️',9=>'⚒️'];
$ln = [1=>'Хелпер',2=>'Мл. модератор',3=>'Модератор',4=>'Администратор',5=>'Куратор',6=>'Зам. ГА',7=>'Главный Админ',8=>'Основатель',9=>'Разработчик'];
$lc = [1=>'#9CA3AF',2=>'#60A5FA',3=>'#3B82F6',4=>'#10B981',5=>'#F59E0B',6=>'#F97316',7=>'#EF4444',8=>'#8B5CF6',9=>'#EC4899'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>👥 Администрация — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --red: #ef4444; --yellow: #f59e0b; --blue: #3b82f6; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 20px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        .alert { padding: 12px 18px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }
        .alert a { color: var(--primary); }
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; justify-content: center; }
        .tabs a { padding: 10px 22px; border-radius: 10px; color: var(--text2); text-decoration: none; font-weight: 500; font-size: 13px; border: 1px solid var(--border); transition: all 0.2s; }
        .tabs a:hover, .tabs a.active { background: var(--primary); color: white; border-color: var(--primary); }
        .top-actions { display: flex; justify-content: flex-end; margin-bottom: 16px; }
        .add-btn { padding: 10px 20px; background: var(--green); border: none; border-radius: 10px; color: white; font-weight: 600; cursor: pointer; font-size: 14px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 20px; text-align: center; position: relative; }
        .card img { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; margin-bottom: 10px; border: 2px solid var(--border); }
        .card h4 { font-size: 16px; font-weight: 700; }
        .card .role { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .card .pos { display: block; font-size: 11px; color: #f59e0b; margin-bottom: 6px; }
        .card .prefix { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 11px; font-weight: 600; background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid var(--green); }
        .card .stats-row { display: flex; justify-content: center; gap: 10px; margin: 8px 0; font-size: 11px; flex-wrap: wrap; }
        .warn-badge { background: rgba(245,158,11,0.1); color: var(--yellow); }
        .repr-badge { background: rgba(239,68,68,0.1); color: var(--red); }
        .rep-badge { background: rgba(139,92,246,0.1); color: var(--primary); }
        .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 4px; }
        .on { background: var(--green); } .off { background: #6b7280; }
        .edit-btn { position: absolute; top: 12px; right: 12px; width: 32px; height: 32px; border-radius: 8px; background: var(--card); border: 1px solid var(--border); color: var(--text2); font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 1; }
        .edit-btn:hover { border-color: var(--primary); color: var(--primary); }
        .view-btn { position: absolute; top: 48px; right: 12px; width: 32px; height: 32px; border-radius: 8px; background: var(--card); border: 1px solid var(--border); color: var(--text2); font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 1; }
        .view-btn:hover { border-color: var(--blue); color: var(--blue); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; width: 100%; max-width: 500px; max-height: 80vh; overflow-y: auto; }
        .modal-content h3 { margin-bottom: 16px; }
        .close-btn { background: none; border: none; color: var(--text2); font-size: 22px; cursor: pointer; float: right; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 4px; color: var(--text2); font-size: 13px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 2px solid var(--border); border-radius: 10px; color: var(--text); font-family: 'Montserrat', sans-serif; font-size: 13px; outline: none; }
        .save-btn { width: 100%; padding: 12px; background: var(--primary); border: none; border-radius: 10px; color: white; font-weight: 600; cursor: pointer; font-size: 14px; margin-top: 8px; }
        .btn-green { background: var(--green); } .btn-sm { padding: 6px 14px; font-size: 12px; border-radius: 8px; }
        .badge { padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 600; }
        .badge-pending { background: rgba(245,158,11,0.1); color: var(--yellow); }
        .info { font-size: 13px; color: var(--text2); line-height: 1.6; } .info strong { color: var(--text); }
        .app-card { background: var(--card); border: 2px solid var(--border); border-radius: 16px; padding: 20px; margin-bottom: 12px; text-align: left; }
        .empty-state { background: var(--card); border: 2px solid var(--border); border-radius: 16px; padding: 30px; text-align: center; color: var(--text2); font-size: 14px; }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>👥 Администрация сервера</h1><p>Список всех администраторов</p></div>
        <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
        
        <div class="tabs">
            <a href="?tab=list" class="<?php echo $tab==='list'?'active':''; ?>">👥 Администраторы</a>
            <?php if($canSeeApplications): ?>
            <a href="?tab=applications" class="<?php echo $tab==='applications'?'active':''; ?>">📝 Анкеты на рассмотрение</a>
            <?php endif; ?>
        </div>
        
        <?php if($tab === 'list'): ?>
        <div class="grid">
            <?php foreach($admins as $a): $lvl=$a['admin_level']??1; $on=$a['last_login']&&strtotime($a['last_login'])>time()-300; $pos=$roles->getUserPosition($a['id']);
                $canEdit = ($myLevel >= 6) && ($a['id'] != $myId || $myLevel >= 9) && ($lvl < $myLevel || $myLevel >= 9);
                if ($myLevel >= 7 && $a['id'] != $myId && $lvl < $myLevel) $canEdit = true;
                if ($myLevel >= 9) $canEdit = true;
                $canView = ($myLevel >= 5) || ($myDept === 'Следящие за тех.разделом');
            ?>
            <div class="card">
                <?php if($canEdit): ?><button class="edit-btn" onclick="openEdit(<?php echo $a['id']; ?>,'<?php echo htmlspecialchars($a['username']); ?>')">✏️</button><?php endif; ?>
                <?php if($canView): ?><button class="view-btn" onclick="openStats(<?php echo $a['id']; ?>,'<?php echo htmlspecialchars($a['username']); ?>')">👁️</button><?php endif; ?>
                <a href="admin_profile.php?id=<?php echo $a['id']; ?>" style="text-decoration:none;color:var(--text);"><img src="../<?php echo $a['avatar']??'assets/default-avatar.png'; ?>" alt=""><h4><?php echo htmlspecialchars($a['username']); ?></h4></a>
                <?php if(!empty($a['prefix'])): ?><span class="prefix">//<?php echo htmlspecialchars($a['prefix']); ?></span><?php endif; ?>
                <span class="role" style="background:<?php echo $lc[$lvl]; ?>20;color:<?php echo $lc[$lvl]; ?>;"><?php echo $li[$lvl]; ?> <?php echo $ln[$lvl]; ?></span>
                <?php if($pos): ?><span class="pos">📌 <?php echo htmlspecialchars($pos['position_name']??$pos['department_name']??''); ?></span><?php endif; ?>
                <div class="stats-row">
                    <span style="color:var(--text2);">🚫 <?php echo $a['bans_count']??0; ?></span>
                    <span style="color:var(--text2);">⚡ <?php echo $a['kicks_count']??0; ?></span>
                    <span style="color:var(--text2);">⚠️ <?php echo $a['warns_count']??0; ?></span>
                    <span style="color:var(--text2);">🕐 <?php echo $a['online_hours']??0; ?>ч</span>
                </div>
                <div class="stats-row">
                    <span class="warn-badge">⚠️ <?php echo $a['warns']; ?>/3</span>
                    <span class="repr-badge">📋 <?php echo $a['reprs']; ?>/3</span>
                    <span class="rep-badge">⭐ <?php echo $a['rep']; ?></span>
                </div>
                <div style="margin-top:8px;font-size:12px;color:var(--text2);"><span class="dot <?php echo $on?'on':'off'; ?>"></span><?php echo $on?'В сети':'Оффлайн'; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php elseif($canSeeApplications): ?>
        <?php if($canInvite): ?><div class="top-actions"><button class="add-btn" onclick="document.getElementById('inviteModal').classList.add('active')">+ Назначить администратора</button></div><?php endif; ?>
        <h3 style="margin-bottom:16px;">📝 Анкеты на рассмотрение (<?php echo count($pendingAdmins); ?>)</h3>
        <?php if(count($pendingAdmins) > 0): ?>
            <?php foreach($pendingAdmins as $pa): ?>
            <div class="app-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <strong style="font-size:16px;"><?php echo htmlspecialchars($pa['username']); ?></strong>
                    <span class="badge badge-pending">⏳ Ожидает</span>
                </div>
                <div class="info"><strong>Уровень:</strong> <?php echo $li[$pa['admin_level']]; ?> <?php echo $ln[$pa['admin_level']]; ?> (<?php echo $pa['admin_level']; ?>)<br><strong>Дата:</strong> <?php echo date('d.m.Y H:i', strtotime($pa['created_at'])); ?></div>
                <?php if($canApprove): ?>
                <form method="POST" style="margin-top:12px;"><input type="hidden" name="action" value="approve_admin"><input type="hidden" name="user_id" value="<?php echo $pa['id']; ?>"><button type="submit" class="save-btn btn-green" style="width:auto;padding:8px 20px;">✅ Активировать</button></form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?><div class="empty-state">📭 Нет анкет на рассмотрение</div><?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php if($canInvite): ?>
    <div class="modal" id="inviteModal"><div class="modal-content">
        <button class="close-btn" onclick="document.getElementById('inviteModal').classList.remove('active')">&times;</button>
        <h3>📨 Назначить администратора</h3>
        <form method="POST"><input type="hidden" name="action" value="invite">
            <div class="form-group"><label>Уровень</label><select name="level" required><option value="">— Выбрать —</option><?php for($i=1;$i<$myLevel;$i++): ?><option value="<?php echo $i; ?>"><?php echo $li[$i]; ?> <?php echo $ln[$i]; ?> (<?php echo $i; ?>)</option><?php endfor; ?></select></div>
            <div class="form-group"><label>Должность (необязательно)</label><select name="position"><option value="">— Без должности —</option><?php foreach($departments as $dk=>$dv): ?><optgroup label="<?php echo $dv['name']; ?>"><?php foreach($dv['positions'] as $pk=>$pv): ?><option value="<?php echo $dk.':'.$pk; ?>"><?php echo $pv['name']; ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div>
            <button type="submit" class="save-btn" style="background:var(--green);">🔗 Создать ссылку</button>
        </form>
    </div></div>
    <?php endif; ?>
    
    <div class="modal" id="editModal"><div class="modal-content">
        <button class="close-btn" onclick="document.getElementById('editModal').classList.remove('active')">&times;</button>
        <h3 id="editTitle">Управление</h3>
        <div style="display:flex;gap:6px;margin-bottom:16px;">
            <button class="save-btn" style="flex:1;background:rgba(245,158,11,0.1);color:var(--yellow);border:1px solid rgba(245,158,11,0.3);font-size:12px;" onclick="switchTab('punish')">📋</button>
            <button class="save-btn" style="flex:1;background:rgba(139,92,246,0.1);color:var(--primary);border:1px solid rgba(139,92,246,0.3);font-size:12px;" onclick="switchTab('rep')">⭐</button>
            <button class="save-b