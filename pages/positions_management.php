<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
require_once '../php/roles_system.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }
$roles = RolesSystem::getInstance($pdo);
$myId = $_SESSION['user_id'];
$myLevel = $roles->getEffectiveLevel($myId);
$roles->requirePermission('users.change_roles');

$stmt = $pdo->query("SELECT id, username, admin_level FROM users ORDER BY admin_level DESC");
$allUsers = $stmt->fetchAll();
$departments = $roles->getAllDepartmentsList();

// Назначение в отдел
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_dept') {
    $userId = (int)$_POST['user_id'];
    $dept = $_POST['department'];
    if ($userId && $dept) {
        $roles->assignPosition($userId, $dept, 'staff', $myId);
        $message = "✅ Сотрудник назначен в отдел!";
    }
}

// Убрать из отдела
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_dept') {
    $userId = (int)$_POST['user_id'];
    $roles->removePosition($userId);
    $message = "✅ Сотрудник убран из отдела!";
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>📌 Отделы — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --red: #ef4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 30px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        .alert { padding: 12px 18px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }
        
        .dept-grid { display: grid; gap: 20px; }
        .dept-card { background: var(--card); border: 1px solid var(--border); border-radius: 18px; overflow: hidden; }
        .dept-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .dept-header h3 { font-size: 18px; font-weight: 700; }
        .dept-header p { font-size: 12px; color: var(--text2); }
        .dept-body { padding: 16px 24px; }
        .staff-list { display: flex; flex-wrap: wrap; gap: 8px; }
        .staff-chip { display: flex; align-items: center; gap: 6px; padding: 6px 12px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 20px; font-size: 13px; }
        .staff-chip form { display: inline; }
        .staff-chip button { background: none; border: none; color: var(--red); cursor: pointer; font-size: 14px; margin-left: 4px; }
        .add-btn { padding: 8px 16px; background: var(--primary); border: none; border-radius: 8px; color: white; font-size: 12px; font-weight: 600; cursor: pointer; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; width: 100%; max-width: 400px; }
        .close-btn { background: none; border: none; color: var(--text2); font-size: 22px; cursor: pointer; float: right; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; margin-bottom: 4px; color: var(--text2); font-size: 13px; }
        .form-group select { width: 100%; padding: 12px; background: rgba(255,255,255,0.03); border: 2px solid var(--border); border-radius: 10px; color: var(--text); font-family: 'Montserrat', sans-serif; font-size: 14px; outline: none; }
        .save-btn { width: 100%; padding: 12px; background: var(--primary); border: none; border-radius: 10px; color: white; font-weight: 600; cursor: pointer; font-size: 14px; }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>📌 Отделы</h1><p>Управление отделами сервера</p></div>
        <?php if (isset($message)): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
        
        <div class="dept-grid">
            <?php foreach ($departments as $dk => $dv): 
                $staff = $roles->getDepartmentStaff($dk);
            ?>
            <div class="dept-card">
                <div class="dept-header" style="border-left: 4px solid <?php echo $dv['color']; ?>;">
                    <div><h3><?php echo $dv['name']; ?></h3><p><?php echo $dv['description']; ?></p></div>
                    <button class="add-btn" onclick="openModal('<?php echo $dk; ?>')">+ Добавить</button>
                </div>
                <div class="dept-body">
                    <?php if (count($staff) > 0): ?>
                    <div class="staff-list">
                        <?php foreach ($staff as $s): ?>
                        <span class="staff-chip">
                            <?php echo htmlspecialchars($s['username']); ?>
                            <form method="POST" style="display:inline;"><input type="hidden" name="action" value="remove_dept"><input type="hidden" name="user_id" value="<?php echo $s['user_id']; ?>"><button type="submit">×</button></form>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p style="color:var(--text2);font-size:13px;">Нет сотрудников</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="modal" id="assignModal"><div class="modal-content">
        <button class="close-btn" onclick="document.getElementById('assignModal').classList.remove('active')">&times;</button>
        <h3>Добавить в отдел</h3>
        <form method="POST"><input type="hidden" name="action" value="assign_dept"><input type="hidden" name="department" id="deptInput">
            <div class="form-group"><label>Сотрудник</label><select name="user_id" required><option value="">— Выбрать —</option><?php foreach($allUsers as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option><?php endforeach; ?></select></div>
            <button type="submit" class="save-btn">✅ Добавить</button>
        </form>
    </div></div>
    
    <script>function openModal(d){ document.getElementById('deptInput').value=d; document.getElementById('assignModal').classList.add('active'); }</script>
</body>
</html>