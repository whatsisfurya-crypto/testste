<?php
define('SITE_ACCESS', true);
require_once 'config/database.php';
require_once 'php/cache.php';

$code = $_GET['code'] ?? '';
$error = '';
$success = '';

if (empty($code)) die('❌ Не указан код приглашения.');

$stmt = $pdo->prepare("SELECT * FROM admin_invites WHERE code = ? AND is_used = FALSE");
$stmt->execute([$code]);
$invite = $stmt->fetch();

if (!$invite) die('❌ Недействительное или уже использованное приглашение.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nickname = trim($_POST['nickname']);
    $prefix = trim($_POST['prefix'] ?? '');
    $forum = trim($_POST['forum']);
    $discord = trim($_POST['discord']);
    $vkId = trim($_POST['vk_id'] ?? '');
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $botCheck = $_POST['bot_check'] ?? 0;
    
    if ((time() - (int)$botCheck) < 3) {
        $error = '❌ Подозрительная активность. Попробуйте снова.';
    } elseif (strlen($nickname) < 3 || strlen($nickname) > 24) {
        $error = 'Никнейм должен быть от 3 до 24 символов';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $nickname)) {
        $error = 'Только латиница, цифры и _';
    } elseif (!empty($prefix) && strlen($prefix) > 50) {
        $error = 'Префикс слишком длинный';
    } elseif (!empty($vkId) && !preg_match('/^\d+$/', $vkId)) {
        $error = 'VK ID должен состоять только из цифр';
    } elseif (!filter_var($forum, FILTER_VALIDATE_URL)) {
        $error = 'Некорректная ссылка на форум';
    } elseif (strlen($discord) < 4) {
        $error = 'Укажите Discord';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль минимум 6 символов';
    } elseif ($password !== $confirm) {
        $error = 'Пароли не совпадают';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$nickname]);
        if ($stmt->fetch()) {
            $error = 'Этот никнейм уже занят';
        } elseif (!empty($vkId)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE vk_id = ?");
            $stmt->execute([$vkId]);
            if ($stmt->fetch()) {
                $error = 'Этот VK ID уже привязан к другому аккаунту';
            }
        }
        
        if (!$error) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, admin_level, prefix, is_active, vk_id) VALUES (?, ?, ?, ?, ?, 0, ?)");
            $stmt->execute([$nickname, $nickname.'@user.local', $hash, $invite['level'], $prefix, $vkId ?: null]);
            $userId = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("INSERT INTO admin_stats (user_id) VALUES (?)");
            $stmt->execute([$userId]);
            
            $stmt = $pdo->prepare("UPDATE admin_invites SET is_used = TRUE WHERE id = ?");
            $stmt->execute([$invite['id']]);
            
            $success = '✅ Анкета отправлена на проверку! Ожидайте одобрения администратора.';
        }
    }
}

$li = [1=>'🛡️',2=>'🔰',3=>'⚖️',4=>'🔱',5=>'👁️',6=>'🎖️',7=>'👑',8=>'⚔️',9=>'⚒️'];
$ln = [1=>'Хелпер',2=>'Мл. модератор',3=>'Модератор',4=>'Администратор',5=>'Куратор',6=>'Зам. ГА',7=>'Главный Админ',8=>'Основатель',9=>'Разработчик'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>📝 Регистрация — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #06060b; --card: #0d0d15; --input: #13131f; --text: #e8e8f0; --text2: #8888a0; --border: #1f1f35; --green: #10b981; --red: #ef4444; --yellow: #f59e0b; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .box { background: var(--card); border-radius: 24px; padding: 40px; width: 100%; max-width: 500px; border: 1px solid var(--border); }
        .box::before { content: ''; display: block; height: 3px; background: linear-gradient(90deg, var(--primary), #ec4899, var(--primary)); margin: -40px -40px 30px; border-radius: 24px 24px 0 0; }
        h1 { text-align: center; font-size: 24px; font-weight: 700; margin-bottom: 6px; }
        .sub { text-align: center; color: var(--text2); font-size: 13px; margin-bottom: 24px; }
        .level-info { text-align: center; margin-bottom: 20px; padding: 10px; background: rgba(139,92,246,0.1); border-radius: 10px; font-size: 14px; color: var(--primary); }
        .alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; }
        .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: var(--red); }
        .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 4px; color: var(--text2); font-size: 12px; }
        .form-group input { width: 100%; padding: 12px 14px; background: var(--input); border: 2px solid var(--border); border-radius: 10px; color: var(--text); font-family: 'Montserrat', sans-serif; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: var(--primary); }
        .btn { width: 100%; padding: 14px; background: var(--primary); border: none; border-radius: 12px; color: white; font-weight: 600; font-size: 15px; cursor: pointer; margin-top: 8px; }
        .btn:hover { background: #7c3aed; }
        .notice { text-align: center; font-size: 12px; color: var(--yellow); margin-top: 12px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>📝 Регистрация администратора</h1>
        <p class="sub">Заполните анкету для доступа к панели управления</p>
        <div class="level-info">🔰 Ваш уровень: <strong><?php echo $li[$invite['level']]; ?> <?php echo $ln[$invite['level']]; ?> (<?php echo $invite['level']; ?>)</strong></div>
        
        <?php if ($error): ?><div class="alert alert-error">❌ <?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <p class="notice">После одобрения вы сможете войти на <a href="index.php" style="color:var(--primary);">странице входа</a></p>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="bot_check" value="<?php echo time(); ?>">
            <div class="form-group"><label>👤 Nick_Name (латиница, цифры, _)</label><input type="text" name="nickname" required minlength="3" maxlength="24" pattern="[a-zA-Z0-9_]+"></div>
            <div class="form-group"><label>🏷️ Префикс</label><input type="text" name="prefix" placeholder="Отображается рядом с ником" maxlength="50"></div>
            <div class="form-group"><label>🔵 VK ID (цифры)</label><input type="text" name="vk_id" placeholder="123456789" pattern="\d*"></div>
            <div class="form-group"><label>🔗 Ссылка на форумный профиль</label><input type="url" name="forum" required placeholder="https://forum...."></div>
            <div class="form-group"><label>💬 Discord</label><input type="text" name="discord" required></div>
            <div class="form-group"><label>🔒 Пароль (мин. 6 символов)</label><input type="password" name="password" required minlength="6"></div>
            <div class="form-group"><label>🔒 Подтвердите пароль</label><input type="password" name="confirm_password" required minlength="6"></div>
            <button type="submit" class="btn">✅ Отправить анкету</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>