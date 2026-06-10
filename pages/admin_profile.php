<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

$profileId = $_GET['id'] ?? $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT u.*, s.* FROM users u LEFT JOIN admin_stats s ON u.id = s.user_id WHERE u.id = ?");
$stmt->execute([$profileId]);
$profile = $stmt->fetch();
if (!$profile) { header('Location: dashboard.php'); exit(); }

$levelNames = [0=>'Пользователь',1=>'Хелпер',2=>'Мл. модератор',3=>'Модератор',4=>'Администратор',5=>'Куратор',6=>'Зам. ГА',7=>'Главный Админ',8=>'Основатель',9=>'Разработчик'];
$levelColors = [0=>'#9CA3AF',1=>'#9CA3AF',2=>'#60A5FA',3=>'#3B82F6',4=>'#10B981',5=>'#F59E0B',6=>'#F97316',7=>'#EF4444',8=>'#8B5CF6',9=>'#EC4899'];
$levelIcons = [0=>'👤',1=>'🛡️',2=>'🔰',3=>'⚖️',4=>'🔱',5=>'👁️',6=>'🎖️',7=>'👑',8=>'⚔️',9=>'⚒️'];
$userLevel = $profile['admin_level'] ?? 0;

if ($profileId == $_SESSION['user_id'] && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['avatar'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','gif','webp']) && $file['size'] < 5242880) {
        $dir = __DIR__ . '/../uploads/avatars/';
        if (!file_exists($dir)) mkdir($dir, 0777, true);
        if ($profile['avatar'] && file_exists(__DIR__.'/../'.$profile['avatar'])) @unlink(__DIR__.'/../'.$profile['avatar']);
        $fn = 'avatar_'.$profileId.'_'.time().'.'.$ext;
        if (move_uploaded_file($file['tmp_name'], $dir.$fn)) {
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute(['uploads/avatars/'.$fn, $profileId]);
            $profile['avatar'] = 'uploads/avatars/'.$fn;
            $_SESSION['avatar'] = 'uploads/avatars/'.$fn;
        }
    }
    header('Location: admin_profile.php?id='.$profileId); exit();
}

require_once '../php/roles_system.php';
$roles = RolesSystem::getInstance($pdo);
$position = $roles->getUserPosition($profileId);
$isOwner = ($profileId == $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👤 Профиль — <?php echo htmlspecialchars($profile['username']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --yellow: #f59e0b; --pink: #ec4899; --blue: #3b82f6; --green: #10b981; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { width: 100%; max-width: 800px; padding: 40px 20px; }
        .back-link { display: inline-block; color: var(--primary); text-decoration: none; font-weight: 500; font-size: 15px; margin-bottom: 24px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 32px; padding: 80px 64px; text-align: center; position: relative; overflow: hidden; }
        .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, var(--primary), var(--pink), var(--blue), var(--primary)); background-size: 300% 100%; animation: grad 4s ease infinite; }
        @keyframes grad { 0%,100%{background-position:0% 50%} 50%{background-position:100% 50%} }
        .avatar-wrap { position: relative; display: inline-block; margin-bottom: 32px; }
        .avatar-wrap img, .avatar-wrap .placeholder { width: 170px; height: 170px; border-radius: 50%; border: 5px solid var(--primary); object-fit: cover; box-shadow: 0 0 60px rgba(139,92,246,0.3); transition: all 0.4s ease; }
        .avatar-wrap .placeholder { display: inline-flex; align-items: center; justify-content: center; background: #0e0e18; }
        .avatar-wrap.clickable { cursor: pointer; }
        .avatar-wrap.clickable:hover img, .avatar-wrap.clickable:hover .placeholder { filter: brightness(0.5); transform: scale(1.03); }
        .avatar-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 50px; opacity: 0; transition: opacity 0.4s ease; pointer-events: none; color: white; }
        .avatar-wrap.clickable:hover .avatar-overlay { opacity: 1; }
        .avatar-wrap input { display: none; }
        .name-row { display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
        .name { font-size: 38px; font-weight: 900; }
        .prefix-tag { display: inline-block; padding: 6px 16px; border-radius: 22px; font-size: 16px; font-weight: 600; background: rgba(16,185,129,0.1); color: var(--green); border: 2px solid var(--green); vertical-align: middle; }
        .role { display: inline-flex; align-items: center; gap: 8px; padding: 8px 22px; border-radius: 30px; font-size: 16px; font-weight: 600; }
        .pos { display: inline-block; padding: 8px 20px; border-radius: 24px; font-size: 15px; font-weight: 600; background: rgba(245,158,11,0.1); color: var(--yellow); border: 1px solid rgba(245,158,11,0.3); margin-bottom: 8px; }
        .date { color: var(--text2); font-size: 16px; margin-bottom: 48px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
        .stat { background: rgba(255,255,255,0.015); border: 1px solid var(--border); border-radius: 22px; padding: 34px 18px; transition: all 0.3s; }
        .stat:hover { border-color: var(--primary); transform: translateY(-4px); }
        .stat .icon { font-size: 34px; margin-bottom: 12px; }
        .stat .val { font-size: 36px; font-weight: 800; }
        .stat .lbl { font-size: 13px; color: var(--text2); margin-top: 6px; text-transform: uppercase; letter-spacing: 1px; }
        @media (max-width: 700px) { .card { padding: 48px 24px; } .name { font-size: 28px; } .stats { grid-template-columns: repeat(2, 1fr); } .avatar-wrap img, .avatar-wrap .placeholder { width: 130px; height: 130px; } }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Назад</a>
        <div class="card">
            <?php if ($isOwner): ?>
            <form method="POST" enctype="multipart/form-data" id="avForm">
                <label class="avatar-wrap clickable" title="Нажмите чтобы изменить аватар">
                    <?php if(!empty($profile['avatar'])): ?><img src="../<?php echo $profile['avatar']; ?>" alt="Аватар"><?php else: ?><div class="placeholder"><svg width="70" height="70" viewBox="0 0 24 24" fill="none" stroke="#8888a0" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-7 8-7s8 3 8 7"/></svg></div><?php endif; ?>
                    <div class="avatar-overlay">📷</div>
                    <input type="file" name="avatar" accept="image/*" onchange="document.getElementById('avForm').submit()">
                </label>
            </form>
            <?php else: ?>
            <div class="avatar-wrap">
                <?php if(!empty($profile['avatar'])): ?><img src="../<?php echo $profile['avatar']; ?>" alt="Аватар"><?php else: ?><div class="placeholder"><svg width="70" height="70" viewBox="0 0 24 24" fill="none" stroke="#8888a0" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-7 8-7s8 3 8 7"/></svg></div><?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="name-row">
                <span class="name"><?php echo htmlspecialchars($profile['username']); ?></span>
                <?php if(!empty($profile['prefix'])): ?><span class="prefix-tag">//<?php echo htmlspecialchars($profile['prefix']); ?></span><?php endif; ?>
                <span class="role" style="background:<?php echo $levelColors[$userLevel]; ?>20;color:<?php echo $levelColors[$userLevel]; ?>;"><?php echo $levelIcons[$userLevel]; ?> <?php echo $levelNames[$userLevel]; ?></span>
            </div>
            <?php if ($position): ?><div class="pos">📌 <?php echo htmlspecialchars($position['position_name'] ?? $position['department_name'] ?? ''); ?></div><?php endif; ?>
            <div class="date">🕐 В команде с <?php echo date('d.m.Y', strtotime($profile['created_at'])); ?></div>
            
            <div class="stats">
                <div class="stat"><div class="icon">🚫</div><div class="val"><?php echo number_format($profile['bans_count']??0); ?></div><div class="lbl">Банов</div></div>
                <div class="stat"><div class="icon">⚡</div><div class="val"><?php echo number_format($profile['kicks_count']??0); ?></div><div class="lbl">Киков</div></div>
                <div class="stat"><div class="icon">⚠️</div><div class="val"><?php echo number_format($profile['warns_count']??0); ?></div><div class="lbl">Варнов</div></div>
                <div class="stat"><div class="icon">🕐</div><div class="val"><?php echo ($profile['online_hours']??0); ?>ч</div><div class="lbl">В сети</div></div>
            </div>
        </div>
    </div>
</body>
</html>