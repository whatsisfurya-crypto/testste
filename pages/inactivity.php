<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

$pdo->prepare("DELETE FROM inactivity_records WHERE status IN ('approved','rejected') AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->execute();

$myId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT admin_level FROM users WHERE id = ?");
$stmt->execute([$myId]);
$myLevel = $stmt->fetchColumn() ?? 1;

$notify = $_SESSION['notify'] ?? '';
unset($_SESSION['notify']);

// Одобрение/отклонение
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $myLevel >= 5) {
    $recordId = (int)$_POST['record_id'];
    $action = $_POST['action'];
    $stmt = $pdo->prepare("SELECT user_id FROM inactivity_records WHERE id = ?");
    $stmt->execute([$recordId]);
    $record = $stmt->fetch();
    if ($record && ($record['user_id'] != $myId || $myLevel >= 9)) {
        if (in_array($action, ['approve', 'reject'])) {
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = $pdo->prepare("UPDATE inactivity_records SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $recordId]);
            
            // Логирование
            $stmt2 = $pdo->prepare("INSERT INTO action_logs (user_id, action, description, ip_address) VALUES (?, 'inactivity_review', ?, ?)");
            $stmt2->execute([$myId, ($action==='approve'?'Одобрен':'Отклонен')." неактив #{$recordId}", $_SERVER['REMOTE_ADDR']]);
        }
    }
    header('Location: inactivity.php'); exit();
}

// Создание
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_date']) && !isset($_POST['action'])) {
    $stmt = $pdo->prepare("INSERT INTO inactivity_records (user_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)");
    $stmt->execute([$myId, $_POST['start_date'], $_POST['end_date'], trim($_POST['reason'])]);
    
    // Логирование
    $stmt2 = $pdo->prepare("INSERT INTO action_logs (user_id, action, description, ip_address) VALUES (?, 'inactivity_create', ?, ?)");
    $stmt2->execute([$myId, "Создан неактив с ".$_POST['start_date']." по ".$_POST['end_date'], $_SERVER['REMOTE_ADDR']]);
    
    $_SESSION['notify'] = '✅ Неактив создан и отправлен на рассмотрение!';
    header('Location: inactivity.php'); exit();
}

$stmt = $pdo->prepare("SELECT i.*, u.username as creator_name FROM inactivity_records i JOIN users u ON i.user_id = u.id WHERE i.user_id = ? ORDER BY i.created_at DESC");
$stmt->execute([$myId]);
$inactivities = $stmt->fetchAll();

$stmt = $pdo->query("SELECT i.*, u.username as creator_name FROM inactivity_records i JOIN users u ON i.user_id = u.id WHERE i.status = 'active' ORDER BY i.created_at DESC LIMIT 50");
$allInactivities = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>⏰ Неактивы — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --red: #ef4444; --yellow: #f59e0b; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 850px; margin: 40px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 32px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        .alert { padding: 12px 18px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }
        .create-btn { padding: 12px 24px; background: var(--primary); border: none; border-radius: 12px; color: white; font-size: 14px; font-weight: 600; cursor: pointer; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; width: 100%; max-width: 450px; }
        .modal-content h3 { margin-bottom: 16px; }
        .close-btn { background: none; border: none; color: var(--text2); font-size: 22px; cursor: pointer; float: right; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; margin-bottom: 4px; color: var(--text2); font-size: 13px; }
        .form-group input, .form-group textarea { width: 100%; padding: 11px 14px; background: rgba(255,255,255,0.03); border: 2px solid var(--border); border-radius: 10px; color: var(--text); font-family: 'Montserrat', sans-serif; font-size: 14px; outline: none; }
        .submit-btn { width: 100%; padding: 12px; background: var(--primary); border: none; border-radius: 10px; color: white; font-weight: 600; font-size: 14px; cursor: pointer; }
        
        .card { background: var(--card); border: 2px solid var(--border); border-radius: 16px; padding: 20px 22px; margin-bottom: 12px; }
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; flex-wrap: wrap; gap: 8px; }
        .status { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-active { background: rgba(245,158,11,0.1); color: var(--yellow); }
        .status-approved { background: rgba(16,185,129,0.1); color: var(--green); }
        .status-rejected { background: rgba(239,68,68,0.1); color: var(--red); }
        .card .dates { font-weight: 600; margin-bottom: 6px; }
        .card .reason { color: var(--text2); font-size: 13px; }
        .card .creator { font-size: 12px; color: var(--text2); margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border); }
        .action-btns { display: flex; gap: 6px; }
        .approve-btn, .reject-btn { padding: 6px 14px; border: none; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .approve-btn { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.3); }
        .approve-btn:hover { background: var(--green); color: white; }
        .reject-btn { background: rgba(239,68,68,0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.3); }
        .reject-btn:hover { background: var(--red); color: white; }
        .section-title { margin-bottom: 14px; margin-top: 30px; font-size: 18px; }
        .empty-state { background: var(--card); border: 2px solid var(--border); border-radius: 16px; padding: 30px; text-align: center; color: var(--text2); font-size: 14px; }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>⏰ Система неактивов</h1><p>Управление отпусками и отгулами</p></div>
        <?php if($notify): ?><div class="alert"><?php echo $notify; ?></div><?php endif; ?>
        
        <div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
            <button class="create-btn" onclick="document.getElementById('modal').classList.add('active')" style="margin:0;">+ Создать неактив</button>
        </div>
        
        <h3 class="section-title">📋 Мои неактивы</h3>
        <?php if(count($inactivities)>0): ?>
            <?php foreach($inactivities as $item): ?>
            <div class="card">
                <div class="card-header"><span class="status status-<?php echo $item['status']; ?>"><?php echo ['active'=>'⏳ На рассмотрении','approved'=>'✅ Одобрен','rejected'=>'❌ Отклонен'][$item['status']]; ?></span></div>
                <div class="dates">📅 <?php echo date('d.m.Y',strtotime($item['start_date'])); ?> — <?php echo date('d.m.Y',strtotime($item['end_date'])); ?></div>
                <div class="reason">📝 <?php echo htmlspecialchars($item['reason']); ?></div>
                <div class="creator">👤 Запросил: <strong><?php echo htmlspecialchars($item['creator_name']); ?></strong></div>
            </div>
            <?php endforeach; ?>
        <?php else: ?><div class="empty-state">📭 У вас нет активных неактивов</div><?php endif; ?>
        
        <h3 class="section-title">📋 Все заявки</h3>
        <?php if(count($allInactivities)>0): ?>
            <?php foreach($allInactivities as $item): $isMine=($item['user_id']==$myId); $canManage=($myLevel>=5 && !$isMine)||($myLevel>=9); ?>
            <div class="card">
                <div class="card-header">
                    <span class="status status-active">⏳ На рассмотрении</span>
                    <?php if($canManage): ?>
                    <div class="action-btns">
                        <form method="POST"><input type="hidden" name="action" value="approve"><input type="hidden" name="record_id" value="<?php echo $item['id']; ?>"><button class="approve-btn">✅ Одобрить</button></form>
                        <form method="POST"><input type="hidden" name="action" value="reject"><input type="hidden" name="record_id" value="<?php echo $item['id']; ?>"><button class="reject-btn">❌ Отклонить</button></form>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="dates">📅 <?php echo date('d.m.Y',strtotime($item['start_date'])); ?> — <?php echo date('d.m.Y',strtotime($item['end_date'])); ?></div>
                <div class="reason">📝 <?php echo htmlspecialchars($item['reason']); ?></div>
                <div class="creator">👤 Запросил: <strong><?php echo htmlspecialchars($item['creator_name']); ?></strong></div>
            </div>
            <?php endforeach; ?>
        <?php else: ?><div class="empty-state">📭 Заявок пока нет</div><?php endif; ?>
    </div>
    
    <div class="modal" id="modal"><div class="modal-content">
        <button class="close-btn" onclick="document.getElementById('modal').classList.remove('active')">&times;</button>
        <h3>📝 Новый неактив</h3>
        <form method="POST"><div class="form-group"><label>📅 Дата начала</label><input type="date" name="start_date" required></div><div class="form-group"><label>📅 Дата окончания</label><input type="date" name="end_date" required></div><div class="form-group"><label>📝 Причина</label><textarea name="reason" rows="3" required></textarea></div><button type="submit" class="submit-btn">✅ Отправить</button></form>
    </div></div>
</body>
</html>