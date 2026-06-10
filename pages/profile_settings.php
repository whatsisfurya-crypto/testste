<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

$userId = $_SESSION['user_id'];
$message = '';
$status = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Загрузка аватара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['avatar'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    
    if (!in_array($ext, $allowed)) {
        $message = '❌ Допустимые форматы: JPG, PNG, GIF, WebP';
        $status = 'error';
    } elseif ($file['size'] > 5242880) {
        $message = '❌ Файл слишком большой (макс. 5MB)';
        $status = 'error';
    } else {
        $dir = __DIR__ . '/../uploads/avatars/';
        if (!file_exists($dir)) mkdir($dir, 0777, true);
        if ($user['avatar'] && file_exists(__DIR__.'/../'.$user['avatar'])) @unlink(__DIR__.'/../'.$user['avatar']);
        $fn = 'avatar_'.$userId.'_'.time().'.'.$ext;
        if (move_uploaded_file($file['tmp_name'], $dir.$fn)) {
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute(['uploads/avatars/'.$fn, $userId]);
            $_SESSION['avatar'] = 'uploads/avatars/'.$fn;
            $user['avatar'] = 'uploads/avatars/'.$fn;
            $message = '✅ Аватар обновлён!';
            $status = 'success';
        }
    }
}

// Обновление профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $newUsername = trim($_POST['username'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');
    $prefix = trim($_POST['prefix'] ?? '');
    
    if (strlen($newUsername) < 3) {
        $message = '❌ Никнейм минимум 3 символа';
        $status = 'error';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $message = '❌ Некорректный email';
        $status = 'error';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$newUsername, $newEmail, $userId]);
        if ($stmt->fetch()) {
            $message = '❌ Никнейм или email заняты';
            $status = 'error';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, prefix = ? WHERE id = ?");
            $stmt->execute([$newUsername, $newEmail, $prefix, $userId]);
            $_SESSION['username'] = $newUsername;
            $user['username'] = $newUsername;
            $user['email'] = $newEmail;
            $user['prefix'] = $prefix;
            $message = '✅ Профиль обновлён!';
            $status = 'success';
        }
    }
}

// Смена пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (!password_verify($current, $user['password'])) {
        $message = '❌ Неверный текущий пароль';
        $status = 'error';
    } elseif (strlen($new) < 6) {
        $message = '❌ Минимум 6 символов';
        $status = 'error';
    } elseif ($new !== $confirm) {
        $message = '❌ Пароли не совпадают';
        $status = 'error';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
        $message = '✅ Пароль изменён!';
        $status = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>⚙️ Настройки — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --red: #ef4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 550px; margin: 40px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 30px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        .alert { padding: 12px 18px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; }
        .alert-success { background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }
        .alert-error { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.3); color: var(--red); }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 18px; padding: 28px; margin-bottom: 18px; }
        .card h3 { font-size: 18px; font-weight: 700; margin-bottom: 20px; }
        .avatar-section { text-align: center; margin-bottom: 24px; }
        .avatar-wrap { position: relative; display: inline-block; cursor: pointer; }
        .avatar-wrap img { width: 100px; height: 100px; border-radius: 50%; border: 4px solid var(--primary); object-fit: cover; transition: all 0.3s; }
        .avatar-wrap:hover img { filter: brightness(0.5); }
        .avatar-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; opacity: 0; transition: opacity 0.3s; pointer-events: none; color: white; }
        .avatar-wrap:hover .avatar-overlay { opacity: 1; }
        .avatar-wrap input { display: none; }
        .avatar-hint { font-size: 12px; color: var(--text2); margin-top: 8px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; margin-bottom: 5px; color: var(--text2); font-size: 13px; }
        .form-group input { width: 100%; padding: 12px 14px; background: rgba(255,255,255,0.03); border: 2px solid var(--border); border-radius: 10px; color: var(--text); font-family: 'Montserrat', sans-serif; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: var(--primary); }
        .save-btn { width: 100%; padding: 13px; background: var(--primary); border: none; border-radius: 10px; color: white; font-weight: 600; font-size: 14px; cursor: pointer; margin-top: 6px; }
        .save-btn:hover { background: #7c3aed; }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>⚙️ Настройки профиля</h1><p>Изменение данных аккаунта</p></div>
        <?php if ($message): ?><div class="alert alert-<?php echo $status; ?>"><?php echo $message; ?></div><?php endif; ?>
        
        <div class="card"><h3>📸 Аватар</h3>
            <div class="avatar-section">
                <form method="POST" enctype="multipart/form-data" id="avForm">
                    <label class="avatar-wrap" title="Нажмите чтобы изменить">
                        <img src="../<?php echo $user['avatar'] ?? 'assets/default-avatar.png'; ?>" alt="Аватар">
                        <div class="avatar-overlay">📷</div>
                        <input type="file" name="avatar" accept="image/*" onchange="document.getElementById('avForm').submit()">
                    </label>
                </form>
                <div class="avatar-hint">Нажмите на аватар чтобы изменить</div>
            </div>
        </div>
        
        <div class="card"><h3>👤 Данные профиля</h3>
            <form method="POST"><input type="hidden" name="action" value="update_profile">
                <div class="form-group"><label>Никнейм</label><input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required minlength="3"></div>
                <div class="form-group"><label>🏷️ Префикс</label><input type="text" name="prefix" value="<?php echo htmlspecialchars($user['prefix']??''); ?>" maxlength="50" placeholder="Отображается рядом с ником"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
                <button type="submit" class="save-btn">💾 Сохранить</button>
            </form>
        </div>
        
        <div class="card"><h3>🔒 Смена пароля</h3>
            <form method="POST"><input type="hidden" name="action" value="change_password">
                <div class="form-group"><label>Текущий пароль</label><input type="password" name="current_password" required></div>
                <div class="form-group"><label>Новый пароль</label><input type="password" name="new_password" required minlength="6"></div>
                <div class="form-group"><label>Подтвердите пароль</label><input type="password" name="confirm_password" required></div>
                <button type="submit" class="save-btn">🔒 Сменить пароль</button>
            </form>
        </div>
    </div>
</body>
</html>