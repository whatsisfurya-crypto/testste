<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
require_once '../php/roles_system.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }
$roles = RolesSystem::getInstance($pdo);
$myId = $_SESSION['user_id'];
$myLevel = $roles->getEffectiveLevel($myId);
$roles->requirePermission('settings.view');

$tab = $_GET['tab'] ?? 'server';
$message = '';

// Сохранение SAMP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_samp'])) {
    $configFile = CONFIG_DIR . 'config.php';
    $content = file_get_contents($configFile);
    $content = preg_replace("/define\('SAMP_SERVER_IP',\s*'[^']*'\);/", "define('SAMP_SERVER_IP', '".addslashes($_POST['samp_ip'])."');", $content);
    $content = preg_replace("/define\('SAMP_SERVER_PORT',\s*\d+\);/", "define('SAMP_SERVER_PORT', ".(int)$_POST['samp_port'].");", $content);
    $content = preg_replace("/define\('SAMP_RCON_PASSWORD',\s*'[^']*'\);/", "define('SAMP_RCON_PASSWORD', '".addslashes($_POST['samp_rcon'])."');", $content);
    file_put_contents($configFile, $content);
    $message = "✅ Настройки SAMP сохранены!";
}

// Сохранение VK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vk'])) {
    $configFile = CONFIG_DIR . 'config.php';
    $content = file_get_contents($configFile);
    $content = preg_replace("/define\('VK_CLIENT_ID',\s*'[^']*'\);/", "define('VK_CLIENT_ID', '".addslashes($_POST['vk_id'])."');", $content);
    $content = preg_replace("/define\('VK_CLIENT_SECRET',\s*'[^']*'\);/", "define('VK_CLIENT_SECRET', '".addslashes($_POST['vk_secret'])."');", $content);
    file_put_contents($configFile, $content);
    $message = "✅ Настройки VK сохранены!";
}

// Отделы
$departments = $roles->getAllDepartmentsList();
$stmt = $pdo->query("SELECT id, username FROM users ORDER BY username");
$allUsers = $stmt->fetchAll();

// Назначение в отдел с должностью
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_dept') {
    $roles->assignPosition((int)$_POST['user_id'], $_POST['department'], $_POST['position'], $myId);
    $message = "✅ Сотрудник назначен!";
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_dept') {
    $roles->removePosition((int)$_POST['user_id']);
    $message = "✅ Снят с отдела!";
}

// Роли
$stmt = $pdo->query("SELECT * FROM admin_levels ORDER BY level");
$allRoles = $stmt->fetchAll();

// Сохранение прав
$allPermissionsList = [
    'Главная' => ['dashboard.view','dashboard.full_access'],
    'Администрация' => ['admin_list.view','admin_list.manage'],
    'Неактивы' => ['inactivity.view_own','inactivity.create','inactivity.view_all','inactivity.approve'],
    'Формы' => ['forms.view_own','forms.create','forms.view_all','forms.process'],
    'Магазин' => ['shop.view','shop.buy'],
    'Консоль' => ['console.view','console.execute_commands'],
    'Логи' => ['logs.view','logs.view_server'],
    'Мониторинг' => ['monitor.view','monitor.search_accounts'],
    'Пользователи' => ['users.view','users.edit','users.change_roles'],
    'Настройки' => ['settings.view','settings.edit'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    $level = (int)$_POST['level'];
    $perms = $_POST['perms'] ?? [];
    $stmt = $pdo->prepare("UPDATE admin_levels SET permissions = ? WHERE level = ?");
    $stmt->execute([json_encode(array_values($perms)), $level]);
    $message = "✅ Права обновлены!";
}

$li = [1=>'🛡️',2=>'🔰',3=>'⚖️',4=>'🔱',5=>'👁️',6=>'🎖️',7=>'👑',8=>'⚔️',9=>'⚒️'];
$ln = [1=>'Хелпер',2=>'Мл. модератор',3=>'Модератор',4=>'Администратор',5=>'Куратор',6=>'Зам. ГА',7=>'Главный Админ',8=>'Основатель',9=>'Разработчик'];
$lc = [1=>'#9CA3AF',2=>'#60A5FA',3=>'#3B82F6',4=>'#10B981',5=>'#F59E0B',6=>'#F97316',7=>'#EF4444',8=>'#8B5CF6',9=>'#EC4899'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>⚙️ Настройки панели — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --red: #ef4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 24px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        .alert { padding: 12px 18px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; justify-content: center; flex-wrap: wrap; }
        .tabs a { padding: 10px 22px; border-radius: 10px; color: var(--text2); text-decoration: none; font-weight: 500; font-size: 13px; border: 1px solid var(--border); transition: all 0.2s; }
        .tabs a:hover, .tabs a.active { background: var(--primary); color: white; border-color: var(--primary); }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; margin-bottom: 16px; }
        .card h3 { font-size: 18px; margin-bottom: 16px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 4px; color: var(--text2); font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 2px solid var(--border); border-radius: 10px; color: var(--text); font-family: 'Montserrat', sans-serif; font-size: 13px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: var(--primary); }
        .save-btn { padding: 10px 24px; background: var(--green); border: none; border-radius: 10px; color: white; font-weight: 600; cursor: pointer; font-size: 13px; margin-top: 8px; }
        .dept-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 16px; margin-bottom: 12px; }
        .dept-card h4 { margin-bottom: 8px; }
        .staff-list { display: flex; flex-wrap: wrap; gap: 6px; }
        .staff-chip { padding: 4px 10px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 20px; font-size: 12px; display: flex; align-items: center; gap: 6px; }
        .staff-chip button { background: none; border: none; color: var(--red); cursor: pointer; }
        .add-btn { padding: 6px 14px; background: var(--primary); border: none; border-radius: 8px; color: white; font-size: 12px; cursor: pointer; }
        .role-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border); }
        .role-row .name { font-weight: 600; } .role-row .count { font-size: 12px; color: var(--text2); }
        .edit-btn { padding: 6px 14px; background: var(--primary); border: none; border-radius: 8px; color: white; font-size: 12px; cursor: pointer; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; width: 100%; max-width: 550px; max-height: 80vh; overflow-y: auto; }
        .close-btn { background: none; border: none; color: var(--text2); font-size: 22px; cursor: pointer; float: right; }
        .perm-sections { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .perm-section h4 { font-size: 12px; color: var(--primary); margin-bottom: 6px; }
        .perm-checkboxes { display: flex; flex-wrap: wrap; gap: 4px; }
        .perm-checkboxes label { font-size: 11px; padding: 4px 8px; border-radius: 6px; border: 1px solid var(--border); cursor: pointer; display: flex; align-items: center; gap: 4px; }
        .perm-checkboxes input { accent-color: var(--primary); }
        @media (max-width: 600px) { .perm-sections { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>⚙️ Настройки панели</h1><p>Сервер SAMP, VK, отделы и роли</p></div>
        <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
        
        <div class="tabs">
            <a href="?tab=server" class="<?php echo $tab==='server'?'active':''; ?>">🖥️ SAMP</a>
            <a href="?tab=vk" class="<?php echo $tab==='vk'?'active':''; ?>">🔵 VK</a>
            <a href="?tab=departments" class="<?php echo $tab==='departments'?'active':''; ?>">📌 Отделы</a>
            <a href="?tab=roles" class="<?php echo $tab==='roles'?'active':''; ?>">🔐 Роли</a>
        </div>
        
        <?php if($tab==='server'): ?>
        <div class="card"><h3>🖥️ Настройки SAMP сервера</h3>
            <form method="POST"><input type="hidden" name="save_samp" value="1">
                <div class="form-group"><label>IP сервера</label><input type="text" name="samp_ip" value="<?php echo SAMP_SERVER_IP; ?>"></div>
                <div class="form-group"><label>Порт</label><input type="number" name="samp_port" value="<?php echo SAMP_SERVER_PORT; ?>"></div>
                <div class="form-group"><label>RCON пароль</label><input type="password" name="samp_rcon" value="<?php echo SAMP_RCON_PASSWORD; ?>"></div>
                <button type="submit" class="save-btn">💾 Сохранить</button>
            </form>
        </div>
        
        <?php elseif($tab==='vk'): ?>
        <div class="card"><h3>🔵 Настройки VK</h3>
            <form method="POST"><input type="hidden" name="save_vk" value="1">
                <div class="form-group"><label>VK Client ID</label><input type="text" name="vk_id" value="<?php echo VK_CLIENT_ID; ?>"></div>
                <div class="form-group"><label>VK Client Secret</label><input type="text" name="vk_secret" value="<?php echo VK_CLIENT_SECRET; ?>"></div>
                <button type="submit" class="save-btn">💾 Сохранить</button>
            </form>
        </div>
        
        <?php elseif($tab==='departments'): ?>
        <?php foreach($departments as $dk=>$dv): $staff=$roles->getDepartmentStaff($dk); ?>
        <div class="dept-card" style="border-left:4px solid <?php echo $dv['color']; ?>;">
            <h4><?php echo $dv['name']; ?> (<?php echo count($staff); ?>)</h4>
            <div class="staff-list" style="margin-bottom:8px;">
                <?php foreach($staff as $s): ?>
                <span class="staff-chip"><?php echo htmlspecialchars($s['username']); ?> <span style="color:<?php echo $s['position_color']??'#f59e0b'; ?>;font-size:10px;"><?php echo $s['position_badge']??''; ?></span>
                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="remove_dept"><input type="hidden" name="user_id" value="<?php echo $s['user_id']; ?>"><button type="submit">×</button></form>
                </span>
                <?php endforeach; ?>
                <?php if(count($staff)==0): ?><span style="color:var(--text2);font-size:12px;">Пусто</span><?php endif; ?>
            </div>
            <button class="add-btn" onclick="openDeptModal('<?php echo $dk; ?>')">+ Добавить</button>
        </div>
        <?php endforeach; ?>
        
        <?php else: ?>
        <?php foreach($allRoles as $role): $perms=json_decode($role['permissions']??'[]',true); ?>
        <div class="role-row">
            <div><span class="name" style="color:<?php echo $role['color']; ?>;"><?php echo $li[$role['level']]; ?> <?php echo $role['name']; ?></span> <span class="count">Ур. <?php echo $role['level']; ?> • <?php echo count($perms); ?> прав</span></div>
            <button class="edit-btn" onclick="openRoleModal(<?php echo $role['level']; ?>,'<?php echo $role['name']; ?>',<?php echo json_encode($perms); ?>)">✏️</button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="modal" id="deptModal"><div class="modal-content">
        <button class="close-btn" onclick="document.getElementById('deptModal').classList.remove('active')">&times;</button>
        <h3>Добавить в отдел</h3>
        <form method="POST"><input type="hidden" name="action" value="assign_dept"><input type="hidden" name="department" id="deptInput">
            <div class="form-group"><label>Сотрудник</label><select name="user_id" required><option value="">— Выбрать —</option><?php foreach($allUsers as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Должность</label><select name="position" id="posSelect" required><option value="">— Выбрать —</option></select></div>
            <button type="submit" class="save-btn" style="width:100%;">✅ Добавить</button>
        </form>
    </div></div>
    
    <div class="modal" id="roleModal"><div class="modal-content">
        <button class="close-btn" onclick="document.getElementById('roleModal').classList.remove('active')">&times;</button>
        <h3 id="roleTitle">Настройка прав</h3>
        <form method="POST"><input type="hidden" name="save_permissions" value="1"><input type="hidden" name="level" id="roleLevel">
            <div class="perm-sections" id="permContainer"></div>
            <button type="submit" class="save-btn" style="width:100%;margin-top:16px;">💾 Сохранить права</button>
        </form>
    </div></div>
    
    <script>
    const depts = <?php echo json_encode($departments); ?>;
    const allPerms = <?php echo json_encode($allPermissionsList); ?>;
    
    function openDeptModal(d) {
        document.getElementById('deptInput').value = d;
        var sel = document.getElementById('posSelect');
        sel.innerHTML = '<option value="">— Выбрать —</option>';
        if (depts[d] && depts[d].positions) {
            Object.entries(depts[d].positions).forEach(function(e) {
                sel.innerHTML += '<option value="'+e[0]+'">'+e[1].badge+' '+e[1].name+'</option>';
            });
        }
        document.getElementById('deptModal').classList.add('active');
    }
    
    function openRoleModal(l, name, perms) {
        document.getElementById('roleLevel').value = l;
        document.getElementById('roleTitle').textContent = 'Права: ' + name + ' (Уровень ' + l + ')';
        var h = '';
        for (const [s, pr] of Object.entries(allPerms)) {
            h += '<div class="perm-section"><h4>' + s + '</h4><div class="perm-checkboxes">';
            pr.forEach(function(p) {
                h += '<label><input type="checkbox" name="perms[]" value="' + p + '" ' + (perms.includes(p) ? 'checked' : '') + '> ' + p.split('.').pop() + '</label>';
            });
            h += '</div></div>';
        }
        document.getElementById('permContainer').innerHTML = h;
        document.getElementById('roleModal').classList.add('active');
    }
    </script>
</body>
</html>