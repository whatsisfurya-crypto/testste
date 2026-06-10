<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/roles_system.php';
require_once '../php/cache.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

$roles = RolesSystem::getInstance($pdo);
$roles->requirePermission('users.change_roles');

// Все права для чекбоксов
$allPermissionsList = [
    'Главная' => ['dashboard.view', 'dashboard.full_access'],
    'Администрация' => ['admin_list.view', 'admin_list.view_details', 'admin_list.manage'],
    'Неактивы' => ['inactivity.view_own', 'inactivity.create', 'inactivity.view_all', 'inactivity.approve'],
    'Оффлайн формы' => ['forms.view_own', 'forms.create', 'forms.view_all', 'forms.process', 'forms.delete'],
    'Магазин' => ['shop.view', 'shop.buy', 'shop.manage_products', 'shop.view_orders', 'shop.manage_orders'],
    'Консоль сервера' => ['console.view', 'console.execute_commands', 'console.restart_server', 'console.ban_players'],
    'Логи' => ['logs.view', 'logs.view_server', 'logs.view_admin', 'logs.export', 'logs.delete'],
    'Мониторинг' => ['monitor.view', 'monitor.search_accounts', 'monitor.view_links', 'monitor.manage_flags'],
    'Пользователи' => ['users.view', 'users.create', 'users.edit', 'users.delete', 'users.change_roles'],
    'Статистика' => ['stats.view', 'stats.view_all', 'stats.export', 'stats.compare'],
    'Репутация' => ['reputation.vote', 'reputation.view', 'reputation.manage'],
    'Достижения' => ['achievements.view', 'achievements.create', 'achievements.grant'],
    'Настройки' => ['settings.view', 'settings.edit', 'settings.backup', 'settings.api'],
];

// Сохранение прав
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    $level = (int)$_POST['level'];
    $selectedPerms = $_POST['perms'] ?? [];
    
    try {
        $stmt = $pdo->prepare("UPDATE admin_levels SET permissions = ? WHERE level = ?");
        $stmt->execute([json_encode(array_values($selectedPerms)), $level]);
        $message = "✅ Права для уровня {$level} успешно обновлены!";
    } catch(PDOException $e) {
        $error = "❌ Ошибка: " . $e->getMessage();
    }
}

// Получаем все роли
$stmt = $pdo->query("SELECT * FROM admin_levels ORDER BY level");
$allRoles = $stmt->fetchAll();

$li = [1=>'🛡️',2=>'🔰',3=>'⚖️',4=>'🔱',5=>'👁️',6=>'🎖️',7=>'👑',8=>'⚔️',9=>'⚒️'];
$lc = [1=>'#9CA3AF',2=>'#60A5FA',3=>'#3B82F6',4=>'#10B981',5=>'#F59E0B',6=>'#F97316',7=>'#EF4444',8=>'#8B5CF6',9=>'#EC4899'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>🔐 Управление ролями — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --red: #ef4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 30px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }
        .alert-error { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.3); color: var(--red); }
        
        .roles-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
        .role-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 22px; cursor: pointer; transition: all 0.3s; }
        .role-card:hover { border-color: var(--primary); transform: translateY(-2px); }
        .role-card.selected { border-color: var(--primary); box-shadow: 0 0 20px rgba(139,92,246,0.2); }
        .role-header { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
        .role-icon { font-size: 28px; }
        .role-info h3 { font-size: 17px; font-weight: 700; }
        .role-info span { font-size: 12px; color: var(--text2); }
        
        .perms-preview { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 10px; }
        .perm-chip { padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 500; }
        
        .edit-panel { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; margin-top: 20px; display: none; }
        .edit-panel.active { display: block; }
        .edit-panel h3 { font-size: 20px; margin-bottom: 8px; }
        .edit-panel .sub { color: var(--text2); font-size: 13px; margin-bottom: 20px; }
        
        .perm-sections { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .perm-section { background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 12px; padding: 16px; }
        .perm-section h4 { font-size: 13px; font-weight: 600; margin-bottom: 10px; color: var(--primary); }
        .perm-checkboxes { display: flex; flex-wrap: wrap; gap: 6px; }
        .perm-checkboxes label { 
            font-size: 11px; padding: 6px 10px; border-radius: 8px; 
            border: 1px solid var(--border); cursor: pointer; 
            display: flex; align-items: center; gap: 5px;
            transition: all 0.2s;
        }
        .perm-checkboxes label:hover { border-color: var(--primary); background: rgba(139,92,246,0.05); }
        .perm-checkboxes input[type="checkbox"] { accent-color: var(--primary); width: 14px; height: 14px; }
        
        .save-panel-btn { 
            width: 100%; padding: 14px; background: var(--green); border: none; 
            border-radius: 12px; color: white; font-weight: 700; font-size: 16px; 
            cursor: pointer; transition: all 0.3s;
        }
        .save-panel-btn:hover { background: #059669; }
        .cancel-btn { 
            padding: 10px 20px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); 
            border-radius: 8px; color: var(--red); cursor: pointer; font-weight: 600; margin-right: 10px;
        }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    
    <div class="container">
        <div class="page-title"><h1>🔐 Управление ролями</h1><p>Настройка прав доступа для каждого уровня</p></div>
        
        <?php if (isset($message)): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if (isset($error)): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
        
        <div class="roles-grid">
            <?php foreach ($allRoles as $role): 
                $perms = json_decode($role['permissions'] ?? '[]', true);
                $previewPerms = array_slice($perms, 0, 6);
            ?>
            <div class="role-card" onclick="selectRole(<?php echo $role['level']; ?>, this)" id="card-<?php echo $role['level']; ?>">
                <div class="role-header">
                    <span class="role-icon"><?php echo $li[$role['level']]; ?></span>
                    <div class="role-info">
                        <h3 style="color:<?php echo $role['color']; ?>;"><?php echo $role['name']; ?></h3>
                        <span>Уровень <?php echo $role['level']; ?> • <?php echo count($perms); ?> прав</span>
                    </div>
                </div>
                <div class="perms-preview">
                    <?php foreach ($previewPerms as $p): ?>
                    <span class="perm-chip" style="background:<?php echo $role['color']; ?>15;color:<?php echo $role['color']; ?>;"><?php echo $p; ?></span>
                    <?php endforeach; ?>
                    <?php if (count($perms) > 6): ?>
                    <span class="perm-chip" style="color:var(--text2);">+<?php echo count($perms)-6; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Панель редактирования -->
        <div class="edit-panel" id="editPanel">
            <h3 id="editTitle">Редактирование прав</h3>
            <p class="sub" id="editSub"></p>
            <form method="POST" id="permForm">
                <input type="hidden" name="level" id="editLevel">
                <input type="hidden" name="save_permissions" value="1">
                
                <div class="perm-sections" id="permSections"></div>
                
                <div style="display:flex;gap:10px;margin-top:10px;">
                    <button type="button" class="cancel-btn" onclick="closeEdit()">Отмена</button>
                    <button type="submit" class="save-panel-btn">💾 Сохранить права</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    const allPerms = <?php echo json_encode($allPermissionsList); ?>;
    const rolesData = <?php echo json_encode($allRoles); ?>;
    let selectedLevel = null;
    
    function selectRole(level, card) {
        // Снимаем выделение со всех
        document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        selectedLevel = level;
        
        // Находим роль
        const role = rolesData.find(r => r.level == level);
        if (!role) return;
        
        const currentPerms = JSON.parse(role.permissions || '[]');
        
        // Обновляем панель
        document.getElementById('editLevel').value = level;
        document.getElementById('editTitle').textContent = '✏️ ' + role.name + ' (Уровень ' + level + ')';
        document.getElementById('editSub').textContent = 'Отметьте права, которые будут доступны этой роли';
        
        // Строим чекбоксы
        let html = '';
        for (const [section, perms] of Object.entries(allPerms)) {
            html += '<div class="perm-section">';
            html += '<h4>' + section + '</h4>';
            html += '<div class="perm-checkboxes">';
            perms.forEach(p => {
                const checked = currentPerms.includes(p) ? 'checked' : '';
                html += '<label><input type="checkbox" name="perms[]" value="' + p + '" ' + checked + '> ' + p.split('.').pop() + '</label>';
            });
            html += '</div></div>';
        }
        
        document.getElementById('permSections').innerHTML = html;
        document.getElementById('editPanel').classList.add('active');
        document.getElementById('editPanel').scrollIntoView({ behavior: 'smooth' });
    }
    
    function closeEdit() {
        document.getElementById('editPanel').classList.remove('active');
        document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
        selectedLevel = null;
    }
    </script>
</body>
</html>