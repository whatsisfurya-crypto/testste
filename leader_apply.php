<?php
define('SITE_ACCESS', true);
require_once 'config/database.php';
require_once 'php/cache.php';

$code = $_GET['code'] ?? '';
if (empty($code)) die('❌ Не указан код');

$pdo->exec("CREATE TABLE IF NOT EXISTS leader_invites (id INT PRIMARY KEY AUTO_INCREMENT, code VARCHAR(32) UNIQUE, department VARCHAR(50), position VARCHAR(100), created_by INT, is_used BOOLEAN DEFAULT FALSE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE IF NOT EXISTS leader_applications (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT, faction VARCHAR(100), position VARCHAR(100), nickname VARCHAR(50), how_became TEXT, phone_call TEXT, transfer TEXT, discord VARCHAR(100), forum_url VARCHAR(255), vk_url VARCHAR(255), vk_id VARCHAR(50), status ENUM('pending','approved','rejected') DEFAULT 'pending', reviewed_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$stmt = $pdo->prepare("SELECT * FROM leader_invites WHERE code = ? AND is_used = FALSE");
$stmt->execute([$code]);
$invite = $stmt->fetch();
if (!$invite) die('❌ Недействительная анкета');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $botCheck = $_POST['bot_check'] ?? 0;
    
    if ((time() - (int)$botCheck) < 3) {
        $error = '❌ Подозрительная активность. Попробуйте снова.';
    } else {
        $nickname = trim($_POST['nickname']);
        $faction = trim($_POST['faction']);
        $how = trim($_POST['how_became']);
        $phone = trim($_POST['phone_call']);
        $transfer = trim($_POST['transfer']);
        $discord = trim($_POST['discord']);
        $forum = trim($_POST['forum_url']);
        $vk = trim($_POST['vk_url']);
        $vkId = trim($_POST['vk_id'] ?? '');
        
        if (strlen($nickname) < 3) $error = 'Никнейм минимум 3 символа';
        elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $nickname)) $error = 'Только латиница, цифры и _';
        elseif (!empty($vkId) && !preg_match('/^\d+$/', $vkId)) $error = 'VK ID должен состоять только из цифр';
        elseif (strlen($faction) < 3) $error = 'Укажите фракцию';
        elseif (strlen($how) < 10) $error = 'Опишите как встали (мин. 10 символов)';
        elseif (strlen($discord) < 4) $error = 'Укажите Discord';
        else {
            $stmt = $pdo->prepare("INSERT INTO leader_applications (user_id, faction, position, nickname, how_became, phone_call, transfer, discord, forum_url, vk_url, vk_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'] ?? 0, $faction, $invite['position'], $nickname, $how, $phone, $transfer, $discord, $forum, $vk, $vkId ?: null]);
            $stmt = $pdo->prepare("UPDATE leader_invites SET is_used = TRUE WHERE id = ?");
            $stmt->execute([$invite['id']]);
            $success = '✅ Анкета отправлена на проверку!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>📝 Анкета лидера</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #06060b; --card: #0d0d15; --input: #13131f; --text: #e8e8f0; --text2: #8888a0; --border: #1f1f35; --green: #10b981; --red: #ef4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .box { background: var(--card); border-radius: 24px; padding: 40px; width: 100%; max-width: 500px; border: 1px solid var(--border); }
        .box::before { content: ''; display: block; height: 3px; background: linear-gradient(90deg, var(--primary), #ec4899, var(--primary)); margin: -40px -40px 30px; border-radius: 24px 24px 0 0; }
        h1 { text-align: center; font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .sub { text-align: center; color: var(--text2); font-size: 13px; margin-bottom: 20px; }
        .alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; }
        .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: var(--red); }
        .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 4px; color: var(--text2); font-size: 12px; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px 14px; background: var(--input); border: 2px solid var(--border); border-radius: 10px; color: var(--text); font-family: 'Montserrat', sans-serif; font-size: 14px; outline: none; }
        .form-group input:focus, .form-group textarea:focus { border-color: var(--primary); }
        textarea { resize: vertical; min-height: 60px; }
        .btn { width: 100%; padding: 14px; background: var(--primary); border: none; border-radius: 12px; color: white; font-weight: 600; font-size: 15px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="box">
        <h1>📝 Анкета лидера</h1>
        <p class="sub">Должность: <strong><?php echo htmlspecialchars($invite['position']); ?></strong></p>
        <?php if($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
        <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?><br><a href="index.php" style="color:var(--primary);">🚀 Войти</a></div><?php else: ?>
        <form method="POST">
            <input type="hidden" name="bot_check" value="<?php echo time(); ?>">
            <div class="form-group"><label>👤 Nick_Name (латиница, цифры, _)</label><input type="text" name="nickname" required pattern="[a-zA-Z0-9_]+"></div>
            <div class="form-group"><label>🏢 Фракция</label><input type="text" name="faction" required></div>
            <div class="form-group"><label>📝 Каким образом встали (мин. 10 символов)</label><textarea name="how_became" required></textarea></div>
            <div class="form-group"><label>📞 Обзвон</label><input type="text" name="phone_call"></div>
            <div class="form-group"><label>🔄 Передача</label><textarea name="transfer"></textarea></div>
            <div class="form-group"><label>💬 Discord</label><input type="text" name="discord" required></div>
            <div class="form-group"><label>🔵 VK ID (цифры)</label><input type="text" name="vk_id" placeholder="123456789" pattern="\d*"></div>
            <div class="form-group"><label>🔗 Форумный профиль</label><input type="url" name="forum_url"></div>
            <div class="form-group"><label>🔵 ВК (ссылка)</label><input type="url" name="vk_url"></div>
            <button type="submit" class="btn">✅ Отправить анкету</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>