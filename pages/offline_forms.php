<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

$pdo->prepare("DELETE FROM offline_forms WHERE status IN ('processed','rejected') AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->execute();

$myId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT admin_level FROM users WHERE id = ?");
$stmt->execute([$myId]);
$myLevel = $stmt->fetchColumn() ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['player_name'])) {
    $playerName = trim($_POST['player_name']);
    $formType = $_POST['form_type'];
    $description = trim($_POST['description']);
    $proof = trim($_POST['proof'] ?? '');
    $errors = [];
    if (strlen($playerName) < 3 || strlen($playerName) > 24) $errors[] = 'Никнейм 3-24 символа';
    if (!preg_match('/^[a-zA-Z0-9_\[\]\(\)]+$/', $playerName)) $errors[] = 'Недопустимые символы';
    if (!empty($proof) && !filter_var($proof, FILTER_VALIDATE_URL)) $errors[] = 'Некорректная ссылка';
    if (strlen($description) < 10) $errors[] = 'Причина минимум 10 символов';
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO offline_forms (user_id, form_type, player_name, description, proof) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$myId, $formType, $playerName, $description, $proof]);
        $success = 'Форма успешно отправлена!';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['form_id'])) {
    $formId = (int)$_POST['form_id']; $action = $_POST['action'];
    $stmt = $pdo->prepare("SELECT form_type FROM offline_forms WHERE id = ?");
    $stmt->execute([$formId]); $form = $stmt->fetch();
    if ($form) {
        $canProcess = false;
        if (in_array($form['form_type'], ['complaint', 'unban']) && $myLevel >= 4) $canProcess = true;
        if (in_array($form['form_type'], ['jail', 'question']) && $myLevel >= 3) $canProcess = true;
        if ($myLevel >= 9) $canProcess = true;
        if ($canProcess && in_array($action, ['approve', 'reject'])) {
            $newStatus = $action === 'approve' ? 'processed' : 'rejected';
            $stmt = $pdo->prepare("UPDATE offline_forms SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $formId]);
            $stmt2 = $pdo->prepare("INSERT INTO action_logs (user_id, action, description, ip_address) VALUES (?, 'form_review', ?, ?)");
            $stmt2->execute([$myId, ($action==='approve'?'Одобрена':'Отклонена')." форма #{$formId}", $_SERVER['REMOTE_ADDR']]);
        }
    }
    header('Location: offline_forms.php'); exit();
}

$stmt = $pdo->prepare("SELECT * FROM offline_forms WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$myId]); $forms = $stmt->fetchAll();

$stmt = $pdo->query("SELECT f.*, u.username as creator_name FROM offline_forms f JOIN users u ON f.user_id = u.id WHERE f.status = 'pending' ORDER BY f.created_at DESC LIMIT 50");
$allForms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>📋 Оффлайн формы — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --red: #ef4444; --yellow: #f59e0b; --blue: #3b82f6; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 30px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        .alert { padding: 12px 18px; border-radius: 12px; margin-bottom: 16px; font-size: 14px; }
        .alert-success { background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }
        .alert-error { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.3); color: var(--red); }
        
        .form-card { background: var(--card); border: 2px solid var(--border); border-radius: 18px; padding: 28px; margin-bottom: 24px; }
        .form-card h3 { font-size: 18px; margin-bottom: 20px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; margin-bottom: 5px; color: var(--text2); font-size: 13px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px 14px; background: rgba(255,255,255,0.03); border: 2px solid var(--border); border-radius: 10px; color: var(--text); font-family: 'Montserrat', sans-serif; font-size: 13px; outline: none; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--primary); }
        .submit-btn { width: 100%; padding: 14px; background: var(--primary); border: none; border-radius: 12px; color: white; font-weight: 600; font-size: 15px; cursor: pointer; }
        
        .history-card { background: var(--card); border: 2px solid var(--border); border-radius: 16px; padding: 18px; margin-bottom: 10px; }
        .history-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; flex-wrap: wrap; gap: 8px; }
        .badge { padding: 5px 12px; border-radius: 10px; font-size: 12px; font-weight: 600; }
        .badge-ban { background: rgba(239,68,68,0.1); color: var(--red); }
        .badge-warn { background: rgba(245,158,11,0.1); color: var(--yellow); }
        .badge-mute { background: rgba(59,130,246,0.1); color: var(--blue); }
        .badge-jail { background: rgba(139,92,246,0.1); color: var(--primary); }
        .badge-wait { background: rgba(245,158,11,0.1); color: var(--yellow); }
        .badge-done { background: rgba(16,185,129,0.1); color: var(--green); }
        .badge-no { background: rgba(239,68,68,0.1); color: var(--red); }
        
        .history-card .player { font-weight: 600; font-size: 15px; margin-bottom: 6px; }
        .history-card .desc { font-size: 13px; color: var(--text2); margin-bottom: 8px; }
        .history-card .meta { font-size: 12px; color: var(--text2); display: flex; gap: 14px; flex-wrap: wrap; }
        .history-card .meta a { color: var(--primary); text-decoration: none; }
        
        .action-btns { display: flex; gap: 6px; }
        .approve-btn, .reject-btn { padding: 7px 16px; border: none; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .approve-btn { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.3); }
        .approve-btn:hover { background: var(--green); color: white; }
        .reject-btn { background: rgba(239,68,68,0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.3); }
        .reject-btn:hover { background: var(--red); color: white; }
        
        .section-title { font-size: 18px; font-weight: 700; margin-bottom: 14px; margin-top: 32px; }
        .empty-state { background: var(--card); border: 2px solid var(--border); border-radius: 16px; padding: 30px; text-align: center; color: var(--text2); font-size: 14px; }
        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>📋 Оффлайн формы</h1><p>Подача жалоб и заявок на наказания</p></div>
        
        <?php if(isset($success)): ?><div class="alert alert-success">✅ <?php echo $success; ?></div><?php endif; ?>
        <?php if(!empty($errors)): ?><div class="alert alert-error"><ul><?php foreach($errors as $e): ?><li><?php echo $e; ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        
        <div class="form-card">
            <h3>📝 Новая форма</h3>
            <form method="POST" onsubmit="return validateForm()">
                <div class="form-row">
                    <div class="form-group"><label>Никнейм нарушителя *</label><input type="text" name="player_name" id="playerName" placeholder="Player_Name" required></div>
                    <div class="form-group"><label>Тип наказания *</label><select name="form_type" required><option value="">— Выбрать —</option><option value="complaint">🚫 Бан</option><option value="unban">⚠️ Варн</option><option value="question">🔇 Мут</option><option value="jail">🔒 Джаил</option></select></div>
                </div>
                <div class="form-group"><label>Причина * (мин. 10 символов)</label><textarea name="description" rows="3" required></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label>Ссылка на доказательства</label><input type="url" name="proof" id="proofLink" placeholder="https://..."></div>
                    <div class="form-group"><label>Комментарий</label><input type="text" name="comment" placeholder="Доп. информация"></div>
                </div>
                <button type="submit" class="submit-btn">📤 Отправить форму</button>
            </form>
        </div>
        
        <h3 class="section-title">📋 Мои формы</h3>
        <?php if(count($forms)>0): ?>
            <?php foreach($forms as $f): 
                $tb=['complaint'=>'badge-ban','unban'=>'badge-warn','question'=>'badge-mute','jail'=>'badge-jail'];
                $tn=['complaint'=>'🚫 Бан','unban'=>'⚠️ Варн','question'=>'🔇 Мут','jail'=>'🔒 Джаил'];
                $sb=['pending'=>'badge-wait','processed'=>'badge-done','rejected'=>'badge-no'];
                $sn=['pending'=>'⏳ На рассмотрении','processed'=>'✅ Одобрена','rejected'=>'❌ Отклонена'];
            ?>
            <div class="history-card">
                <div class="history-header"><span class="badge <?php echo $tb[$f['form_type']]??''; ?>"><?php echo $tn[$f['form_type']]??$f['form_type']; ?></span><span class="badge <?php echo $sb[$f['status']]??''; ?>"><?php echo $sn[$f['status']]??$f['status']; ?></span></div>
                <div class="player">👤 <?php echo htmlspecialchars($f['player_name']); ?></div>
                <div class="desc"><?php echo htmlspecialchars(substr($f['description'],0,200)); ?>...</div>
                <div class="meta"><span>🕐 <?php echo date('d.m.Y H:i',strtotime($f['created_at'])); ?></span><?php if($f['proof']): ?><span>🔗 <a href="<?php echo htmlspecialchars($f['proof']); ?>" target="_blank">Доказательство</a></span><?php endif; ?></div>
            </div>
            <?php endforeach; ?>
        <?php else: ?><div class="empty-state">📭 У вас нет поданных форм</div><?php endif; ?>
        
        <h3 class="section-title">📋 Все формы</h3>
        <?php if(count($allForms)>0): ?>
            <?php foreach($allForms as $f): 
                $tb=['complaint'=>'badge-ban','unban'=>'badge-warn','question'=>'badge-mute','jail'=>'badge-jail'];
                $tn=['complaint'=>'🚫 Бан','unban'=>'⚠️ Варн','question'=>'🔇 Мут','jail'=>'🔒 Джаил'];
                $canProcess = false;
                if(in_array($f['form_type'],['complaint','unban']) && $myLevel>=4) $canProcess=true;
                if(in_array($f['form_type'],['jail','question']) && $myLevel>=3) $canProcess=true;
                if($myLevel>=9) $canProcess=true;
            ?>
            <div class="history-card">
                <div class="history-header">
                    <span class="badge <?php echo $tb[$f['form_type']]??''; ?>"><?php echo $tn[$f['form_type']]??$f['form_type']; ?></span>
                    <span class="badge badge-wait">⏳ На рассмотрении</span>
                    <?php if($canProcess): ?>
                    <div class="action-btns">
                        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="approve"><input type="hidden" name="form_id" value="<?php echo $f['id']; ?>"><button type="submit" class="approve-btn">✅ Одобрить</button></form>
                        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="reject"><input type="hidden" name="form_id" value="<?php echo $f['id']; ?>"><button type="submit" class="reject-btn">❌ Отклонить</button></form>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="player">👤 <?php echo htmlspecialchars($f['player_name']); ?></div>
                <div class="desc"><?php echo htmlspecialchars(substr($f['description'],0,200)); ?>...</div>
                <div class="meta"><span>👤 От: <strong><?php echo htmlspecialchars($f['creator_name']); ?></strong></span><span>🕐 <?php echo date('d.m.Y H:i',strtotime($f['created_at'])); ?></span><?php if($f['proof']): ?><span>🔗 <a href="<?php echo htmlspecialchars($f['proof']); ?>" target="_blank">Доказательство</a></span><?php endif; ?></div>
            </div>
            <?php endforeach; ?>
        <?php else: ?><div class="empty-state">📭 Нет активных форм</div><?php endif; ?>
    </div>
    
    <script>
    function validateForm(){
        var n=document.getElementById('playerName'),p=document.getElementById('proofLink'),r=/^[a-zA-Z0-9_\[\]\(\)]+$/,v=true;
        if(n.value.length<3||n.value.length>24){n.classList.add('error');alert('Никнейм 3-24 символа');v=false;}
        else if(!r.test(n.value)){n.classList.add('error');alert('Недопустимые символы!');v=false;}
        else n.classList.remove('error');
        if(p.value&&!isValidUrl(p.value)){p.classList.add('error');alert('Некорректная ссылка');v=false;}
        else p.classList.remove('error');
        return v;
    }
    function isValidUrl(s){try{new URL(s);return true;}catch(e){return false;}}
    </script>
</body>
</html>