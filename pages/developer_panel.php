<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
require_once '../php/roles_system.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }
$roles = RolesSystem::getInstance($pdo);
$myLevel = $roles->getEffectiveLevel($_SESSION['user_id']);
if ($myLevel < 9) { header('Location: dashboard.php'); exit(); }

$tab = $_GET['tab'] ?? 'system';
$message = '';

// Системная информация
$phpVersion = phpversion();
$serverInfo = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$dbSize = 0; $tables = [];
$stmt = $pdo->query("SHOW TABLE STATUS");
while ($row = $stmt->fetch()) { $dbSize += $row['Data_length'] + $row['Index_length']; $tables[] = $row; }
$freeSpace = disk_free_space(ROOT_DIR);
$totalSpace = disk_total_space(ROOT_DIR);
$errorLog = ''; $errorLogPath = ini_get('error_log');
if ($errorLogPath && file_exists($errorLogPath)) { $errorLog = file_get_contents($errorLogPath); $errorLog = substr($errorLog, -50000); }
$stmt = $pdo->query("SELECT u.username, l.* FROM action_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 100");
$logs = $stmt->fetchAll();
$stmt = $pdo->query("SELECT * FROM login_attempts ORDER BY attempted_at DESC LIMIT 50");
$loginAttempts = $stmt->fetchAll();
$sessionPath = session_save_path() ?: sys_get_temp_dir();
$sessions = glob($sessionPath . '/sess_*'); $activeSessions = count($sessions);
$stmt = $pdo->query("SELECT u.username, u.last_login FROM users u WHERE u.last_login > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$onlineUsers = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_sessions'])) {
    foreach ($sessions as $s) @unlink($s); $message = "✅ Сессии очищены!"; $activeSessions = 0;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cache'])) {
    $cf = glob(ROOT_DIR.'cache/*.cache'); foreach ($cf as $f) @unlink($f);
    if (function_exists('opcache_reset')) opcache_reset(); $message = "✅ Кэш очищен!";
}
if ($tab === 'backup' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_backup'])) {
    require_once '../php/backup.php'; $backup = new Backup($pdo);
    $result = $backup->createBackup(); $message = $result['success'] ? "✅ Бэкап создан!" : "❌ Ошибка";
}

// SQL консоль
$sqlResult = null; $sqlError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql_query'])) {
    $sql = trim($_POST['sql_query']);
    $dangerous = ['DROP', 'DELETE', 'TRUNCATE', 'ALTER']; $isDangerous = false;
    foreach ($dangerous as $w) { if (stripos($sql, $w) !== false) { $isDangerous = true; break; } }
    if (!$isDangerous) { try { $stmt = $pdo->query($sql); $sqlResult = $stmt->fetchAll(); } catch(Exception $e) { $sqlError = $e->getMessage(); } }
    else { $sqlError = '⚠️ DROP/DELETE/TRUNCATE/ALTER запрещены'; }
}

// Пинг
if ($tab === 'system') {
    $s = microtime(true); $sc = @fsockopen($_SERVER['HTTP_HOST'], 80, $e1, $e2, 5);
    $pingSite = $sc ? round((microtime(true)-$s)*1000) : null; if ($sc) fclose($sc);
    $s = microtime(true);
    try { $pdo->query("SELECT 1"); $pingDb = round((microtime(true)-$s)*1000); $dbOk = true; } catch(Exception $e) { $pingDb = null; $dbOk = false; }
    $s = microtime(true); $samp = @fsockopen('udp://'.SAMP_SERVER_IP, SAMP_SERVER_PORT, $e1, $e2, 3);
    $pingSamp = $samp ? round((microtime(true)-$s)*1000) : null; if ($samp) fclose($samp);
    $folders = ['uploads','cache','logs','backups']; $folderChecks = [];
    foreach ($folders as $f) { $p = ROOT_DIR.$f; $folderChecks[$f] = ['exists'=>file_exists($p),'writable'=>is_writable($p)]; }
}

// Файловый менеджер
$currentDir = $_GET['dir'] ?? ''; $baseDir = ROOT_DIR;
$fullPath = realpath($baseDir . $currentDir);
if (!$fullPath || strpos($fullPath, realpath($baseDir)) !== 0) { $fullPath = realpath($baseDir); $currentDir = ''; }
if ($tab === 'files') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
        move_uploaded_file($_FILES['upload_file']['tmp_name'], $fullPath.'/'.basename($_FILES['upload_file']['name'])); $message = "✅ Загружено!";
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_folder']) && trim($_POST['new_folder'])) {
        $fn = trim($_POST['new_folder']); if (!file_exists($fullPath.'/'.$fn)) { mkdir($fullPath.'/'.$fn, 0755); $message = "✅ Папка создана!"; }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_file']) && trim($_POST['new_file'])) {
        $fn = trim($_POST['new_file']); if (!file_exists($fullPath.'/'.$fn)) { file_put_contents($fullPath.'/'.$fn, ''); $message = "✅ Файл создан!"; }
    }
    if (isset($_GET['delete'])) {
        $dp = $fullPath.'/'.basename($_GET['delete']);
        if (file_exists($dp) && strpos(realpath($dp), realpath($baseDir)) === 0) { is_dir($dp) ? @rmdir($dp) : @unlink($dp); $message = "🗑️ Удалено!"; }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_file'])) {
        $sp = $fullPath.'/'.basename($_POST['file_name']);
        if (strpos(realpath(dirname($sp)), realpath($baseDir)) === 0) { file_put_contents($sp, $_POST['file_content']); $message = "✅ Сохранено!"; }
    }
}
$items = [];
if ($tab === 'files' && is_dir($fullPath)) {
    foreach (scandir($fullPath) as $item) { if ($item==='.'||$item==='..') continue; $ip = $fullPath.'/'.$item; $items[] = ['name'=>$item,'is_dir'=>is_dir($ip),'size'=>is_file($ip)?filesize($ip):0,'modified'=>filemtime($ip),'perms'=>substr(sprintf('%o',fileperms($ip)),-3)]; }
    usort($items, function($a,$b){ return $a['is_dir']!==$b['is_dir']?($a['is_dir']?-1:1):strcasecmp($a['name'],$b['name']); });
}
$editFile = $_GET['edit'] ?? ''; $fileContent = '';
if ($tab === 'files' && $editFile) { $ep = $fullPath.'/'.basename($editFile); if (file_exists($ep) && is_file($ep)) { $ext = strtolower(pathinfo($ep, PATHINFO_EXTENSION)); if (in_array($ext, ['php','html','css','js','txt','json','xml','htaccess','sql','md','log','ini','conf'])) $fileContent = file_get_contents($ep); } }

// Генератор паролей
$genPass = '';
if ($tab === 'tools' && isset($_POST['gen_password'])) { $len = min(64, max(8, (int)($_POST['pass_length']??16))); $c = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()'; $genPass = substr(str_shuffle(str_repeat($c,5)), 0, $len); }

function breadcrumbs($d) { if(empty($d)) return '<a href="?tab=files">📁 /</a>'; $p=explode('/',trim($d,'/')); $path=''; $h='<a href="?tab=files">📁 /</a> '; foreach($p as $v){ $path.='/'.$v; $h.='› <a href="?tab=files&dir='.urlencode(ltrim($path,'/')).'">'.htmlspecialchars($v).'</a> '; } return $h; }
function formatSize($b) { if($b<1024) return $b.' B'; if($b<1048576) return round($b/1024,1).' KB'; if($b<1073741824) return round($b/1048576,1).' MB'; return round($b/1073741824,1).' GB'; }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>🔧 Панель разработчика — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --red: #ef4444; --yellow: #f59e0b; --blue: #3b82f6; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 1300px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 20px; } .page-title h1 { font-size: 28px; font-weight: 800; }
        .alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }
        .tabs { display: flex; gap: 4px; margin-bottom: 24px; justify-content: center; flex-wrap: wrap; }
        .tabs a { padding: 10px 18px; border-radius: 10px; color: var(--text2); text-decoration: none; font-weight: 500; font-size: 12px; border: 1px solid var(--border); transition: all 0.2s; }
        .tabs a:hover, .tabs a.active { background: var(--primary); color: white; border-color: var(--primary); }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px; margin-bottom: 16px; }
        .card h3 { font-size: 16px; margin-bottom: 14px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
        .stat-item { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 18px; text-align: center; }
        .stat-item .val { font-size: 24px; font-weight: 800; } .stat-item .lbl { font-size: 11px; color: var(--text2); }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 13px; } .info-row .lbl { color: var(--text2); }
        table { width: 100%; border-collapse: collapse; font-size: 12px; } th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid var(--border); } th { color: var(--text2); font-size: 11px; text-transform: uppercase; }
        textarea, input { width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 2px solid var(--border); border-radius: 10px; color: var(--text); font-family: 'Courier New', monospace; font-size: 13px; outline: none; }
        textarea:focus, input:focus { border-color: var(--primary); }
        .btn { padding: 10px 20px; background: var(--primary); border: none; border-radius: 10px; color: white; font-weight: 600; cursor: pointer; font-size: 13px; } .btn-red { background: var(--red); } .btn-green { background: var(--green); } .btn-sm { padding: 6px 12px; font-size: 11px; border-radius: 6px; } .btn:hover { opacity: 0.85; }
        .chart-bar { height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; } .chart-fill { height: 100%; background: var(--primary); border-radius: 3px; }
        .breadcrumbs { margin-bottom: 16px; font-size: 14px; } .breadcrumbs a { color: var(--primary); }
        .file-actions { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; } .file-actions form { display: flex; gap: 6px; }
        .file-row { display: flex; align-items: center; padding: 10px 14px; border-bottom: 1px solid var(--border); } .file-row:hover { background: rgba(139,92,246,0.03); }
        .file-icon { width: 24px; text-align: center; margin-right: 10px; } .file-name { flex: 1; font-size: 13px; } .file-name a { color: var(--text); text-decoration: none; }
        .file-size { width: 80px; text-align: right; font-size: 11px; color: var(--text2); } .file-date { width: 140px; text-align: right; font-size: 11px; color: var(--text2); }
        .file-perms { width: 50px; text-align: right; font-size: 11px; color: var(--text2); } .file-actions-btns { width: 70px; text-align: right; }
        .file-actions-btns a { color: var(--text2); margin-left: 4px; } .file-actions-btns .del:hover { color: var(--red); }
        @media (max-width: 800px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .file-date, .file-perms { display: none; } }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>🔧 Панель разработчика</h1></div>
        <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
        
        <div class="tabs">
            <a href="?tab=system" class="<?php echo $tab==='system'?'active':''; ?>">⚙️ Система</a>
            <a href="?tab=database" class="<?php echo $tab==='database'?'active':''; ?>">🗄️ БД</a>
            <a href="?tab=logs" class="<?php echo $tab==='logs'?'active':''; ?>">📝 Логи</a>
            <a href="?tab=security" class="<?php echo $tab==='security'?'active':''; ?>">🔒 Безопасность</a>
            <a href="?tab=monitor" class="<?php echo $tab==='monitor'?'active':''; ?>">📊 Мониторинг</a>
            <a href="?tab=files" class="<?php echo $tab==='files'?'active':''; ?>">📁 Файлы</a>
            <a href="?tab=tools" class="<?php echo $tab==='tools'?'active':''; ?>">🔧 Инстр.</a>
            <a href="?tab=backup" class="<?php echo $tab==='backup'?'active':''; ?>">💾 Бэкап</a>
            <a href="?tab=docs" class="<?php echo $tab==='docs'?'active':''; ?>">📚 Доки</a>
            <a href="?tab=stats" class="<?php echo $tab==='stats'?'active':''; ?>">📈 Стат.</a>
        </div>
        
        <?php if($tab === 'system'): ?>
        <div class="stats-grid">
            <div class="stat-item"><div class="val"><?php echo $phpVersion; ?></div><div class="lbl">PHP</div></div>
            <div class="stat-item"><div class="val"><?php echo round($freeSpace/1073741824,1); ?> GB</div><div class="lbl">Свободно</div></div>
            <div class="stat-item"><div class="val"><?php echo $activeSessions; ?></div><div class="lbl">Сессий</div></div>
            <div class="stat-item"><div class="val"><?php echo count($onlineUsers); ?></div><div class="lbl">Онлайн</div></div>
        </div>
        <div class="card"><h3>📡 Пинг</h3>
            <div class="stats-grid">
                <div class="stat-item"><div class="val" style="color:<?php echo $pingSite?'var(--green)':'var(--red)'; ?>;"><?php echo $pingSite?$pingSite.'ms':'❌'; ?></div><div class="lbl">Сайт</div></div>
                <div class="stat-item"><div class="val" style="color:<?php echo $dbOk?'var(--green)':'var(--red)'; ?>;"><?php echo $pingDb?$pingDb.'ms':'❌'; ?></div><div class="lbl">БД</div></div>
                <div class="stat-item"><div class="val" style="color:<?php echo $pingSamp?'var(--green)':'var(--red)'; ?>;"><?php echo $pingSamp?$pingSamp.'ms':'❌'; ?></div><div class="lbl">SAMP</div></div>
            </div>
        </div>
        <div class="card"><h3>⚙️ Сервер</h3>
            <div class="info-row"><span class="lbl">Сервер</span><span><?php echo $serverInfo; ?></span></div>
            <div class="info-row"><span class="lbl">Диск</span><span><?php echo round($totalSpace/1073741824,1); ?> GB / <?php echo round($freeSpace/1073741824,1); ?> GB</span></div>
            <div class="chart-bar"><div class="chart-fill" style="width:<?php echo round(100-($freeSpace/$totalSpace)*100); ?>%;"></div></div>
            <div class="info-row"><span class="lbl">Модули</span><span><?php echo count(get_loaded_extensions()); ?></span></div>
        </div>
        <div class="card"><h3>👥 Онлайн</h3><?php foreach($onlineUsers as $u): ?><div class="info-row"><span><?php echo htmlspecialchars($u['username']); ?></span><span style="color:var(--text2);"><?php echo date('H:i',strtotime($u['last_login'])); ?></span></div><?php endforeach; ?></div>
        <div class="card"><h3>📋 Ошибки PHP</h3><div style="max-height:500px;overflow-y:auto;font-size:12px;color:var(--red);font-family:'Courier New',monospace;line-height:1.6;"><?php if($errorLog): echo nl2br(htmlspecialchars($errorLog)); else: ?><div style="text-align:center;color:var(--text2);padding:30px;">✅ Нет ошибок</div><?php endif; ?></div></div>
        
        <?php elseif($tab === 'database'): ?>
        <div class="stats-grid">
            <div class="stat-item"><div class="val"><?php echo count($tables); ?></div><div class="lbl">Таблиц</div></div>
            <div class="stat-item"><div class="val"><?php echo round($dbSize/1048576,1); ?> MB</div><div class="lbl">Размер</div></div>
            <div class="stat-item"><div class="val"><?php echo DB_HOST; ?></div><div class="lbl">Хост</div></div>
            <div class="stat-item"><div class="val"><?php echo DB_NAME; ?></div><div class="lbl">БД</div></div>
        </div>
        <div class="card"><h3>🗄️ Таблицы</h3><table><thead><tr><th>Таблица</th><th>Записей</th><th>Размер</th></tr></thead><tbody><?php foreach($tables as $t): ?><tr><td><?php echo $t['Name']; ?></td><td><?php echo $t['Rows']; ?></td><td><?php echo round(($t['Data_length']+$t['Index_length'])/1024,1); ?> KB</td></tr><?php endforeach; ?></tbody></table></div>
        <div class="card"><h3>💻 SQL Консоль</h3>
            <?php if(isset($sqlError)): ?><div class="alert" style="color:var(--red);background:rgba(239,68,68,0.1);"><?php echo $sqlError; ?></div><?php endif; ?>
            <?php if(isset($sqlResult)): ?><div class="alert">✅ Строк: <?php echo count($sqlResult); ?></div><?php endif; ?>
            <form method="POST"><textarea name="sql_query" rows="3"><?php echo htmlspecialchars($_POST['sql_query']??''); ?></textarea><button class="btn" style="margin-top:8px;">▶️ Выполнить</button></form>
        </div>
        
        <?php elseif($tab === 'logs'): ?>
        <div class="card"><h3>📝 Действия (100)</h3><table><thead><tr><th>Время</th><th>Кто</th><th>Действие</th><th>IP</th></tr></thead><tbody><?php foreach($logs as $l): ?><tr><td><?php echo date('d.m H:i',strtotime($l['created_at'])); ?></td><td><?php echo htmlspecialchars($l['username']??'—'); ?></td><td><?php echo htmlspecialchars($l['action']); ?></td><td><?php echo $l['ip_address']; ?></td></tr><?php endforeach; ?></tbody></table></div>
        
        <?php elseif($tab === 'security'): ?>
        <div class="card"><h3>🔒 Попытки входа</h3><table><thead><tr><th>Время</th><th>IP</th><th>Логин</th></tr></thead><tbody><?php foreach($loginAttempts as $la): ?><tr><td><?php echo date('d.m H:i',strtotime($la['attempted_at'])); ?></td><td><?php echo $la['ip_address']; ?></td><td><?php echo htmlspecialchars($la['login_used']); ?></td></tr><?php endforeach; ?></tbody></table></div>
        <div class="card"><h3>👥 Сессии: <?php echo $activeSessions; ?></h3><form method="POST"><input type="hidden" name="clear_sessions" value="1"><button class="btn btn-red">🗑️ Очистить все</button></form></div>
        
        <?php elseif($tab === 'monitor'): ?>
        <div class="stats-grid">
            <div class="stat-item"><div class="val"><?php echo round(memory_get_usage()/1048576,1); ?> MB</div><div class="lbl">Память</div></div>
            <div class="stat-item"><div class="val"><?php echo count($tables); ?></div><div class="lbl">Таблиц</div></div>
            <div class="stat-item"><div class="val"><?php echo round($dbSize/1048576,1); ?> MB</div><div class="lbl">БД</div></div>
            <div class="stat-item"><div class="val"><?php echo count($onlineUsers); ?></div><div class="lbl">Онлайн</div></div>
        </div>
        <div class="card"><h3>📊 Целостность</h3>
            <?php $cf=['config/config.php','config/database.php','index.php','pages/dashboard.php']; foreach($cf as $f): $ex=file_exists(ROOT_DIR.$f); ?>
            <div class="info-row"><span><?php echo $f; ?></span><span style="color:<?php echo $ex?'var(--green)':'var(--red)'; ?>;"><?php echo $ex?'✅':'❌'; ?></span></div>
            <?php endforeach; ?>
        </div>
        <div class="card"><h3>🗑️ Очистка</h3><form method="POST"><input type="hidden" name="clear_cache" value="1"><button class="btn btn-red">🗑️ Очистить кэш</button></form></div>
        
        <?php elseif($tab === 'tools'): ?>
        <div class="card"><h3>🔑 Генератор паролей</h3>
            <form method="POST"><input type="number" name="pass_length" value="16" min="8" max="64" style="width:auto;margin-bottom:12px;"><br><button name="gen_password" class="btn btn-sm">🔑 Сгенерировать</button></form>
            <?php if($genPass): ?><div style="margin-top:10px;background:#050510;padding:12px;border-radius:8px;font-size:18px;text-align:center;letter-spacing:2px;"><?php echo $genPass; ?></div><?php endif; ?>
        </div>
        
        <?php elseif($tab === 'backup'): ?>
        <div class="card"><h3>💾 Резервное копирование</h3>
            <form method="POST"><input type="hidden" name="do_backup" value="1"><button class="btn">💾 Создать бэкап</button></form>
            <?php $bfs=glob(ROOT_DIR.'backups/*.sql*'); if(!empty($bfs)): ?>
            <div style="margin-top:16px;"><h4>📦 Бэкапы:</h4><?php foreach(array_reverse($bfs) as $bf): ?><div class="info-row"><span><?php echo basename($bf); ?></span><span style="color:var(--text2);"><?php echo formatSize(filesize($bf)); ?></span></div><?php endforeach; ?></div><?php endif; ?>
        </div>
        
        <?php elseif($tab === 'docs'): ?>
        <div class="stats-grid">
            <div class="stat-item"><div class="val"><?php echo count($tables); ?></div><div class="lbl">Таблиц</div></div>
            <div class="stat-item"><div class="val"><?php echo count(get_loaded_extensions()); ?></div><div class="lbl">Модулей</div></div>
            <div class="stat-item"><div class="val"><?php echo count(glob(ROOT_DIR.'pages/*.php')); ?></div><div class="lbl">Страниц</div></div>
            <div class="stat-item"><div class="val">2.0</div><div class="lbl">Версия</div></div>
        </div>
        
        <?php elseif($tab === 'stats'): ?>
        <div class="stats-grid">
            <div class="stat-item"><div class="val"><?php $stmt=$pdo->query("SELECT COUNT(*) FROM users"); echo $stmt->fetchColumn(); ?></div><div class="lbl">Пользователей</div></div>
            <div class="stat-item"><div class="val"><?php $stmt=$pdo->query("SELECT COUNT(*) FROM offline_forms"); echo $stmt->fetchColumn(); ?></div><div class="lbl">Форм</div></div>
            <div class="stat-item"><div class="val"><?php $stmt=$pdo->query("SELECT COUNT(*) FROM inactivity_records"); echo $stmt->fetchColumn(); ?></div><div class="lbl">Неактивов</div></div>
            <div class="stat-item"><div class="val"><?php $stmt=$pdo->query("SELECT COUNT(*) FROM reputation"); echo $stmt->fetchColumn(); ?></div><div class="lbl">Репутации</div></div>
        </div>
        
        <?php else: ?>
        <div class="breadcrumbs"><?php echo breadcrumbs($currentDir); ?></div>
        <div class="file-actions">
            <form method="POST" enctype="multipart/form-data"><input type="file" name="upload_file" style="width:auto;flex:1;"><button class="btn btn-sm">📤 Загрузить</button></form>
            <form method="POST"><input type="text" name="new_folder" placeholder="Новая папка" style="width:180px;"><button class="btn btn-sm" style="background:var(--yellow);">📁 Папка</button></form>
            <form method="POST"><input type="text" name="new_file" placeholder="Новый файл" style="width:180px;"><button class="btn btn-sm" style="background:var(--blue);">📄 Файл</button></form>
        </div>
        <?php if($editFile && $fileContent): ?>
        <div class="card"><h3>✏️ <?php echo htmlspecialchars($editFile); ?></h3>
            <form method="POST"><input type="hidden" name="file_name" value="<?php echo htmlspecialchars($editFile); ?>"><textarea name="file_content" rows="20" style="min-height:400px;"><?php echo htmlspecialchars($fileContent); ?></textarea>
                <div style="display:flex;gap:8px;margin-top:10px;"><button name="save_file" class="btn">💾 Сохранить</button><a href="?tab=files&dir=<?php echo urlencode($currentDir); ?>" class="btn" style="background:var(--text2);">Отмена</a></div>
            </form>
        </div>
        <?php else: ?>
        <div class="card" style="padding:0;">
            <?php foreach($items as $item): ?>
            <div class="file-row">
                <span class="file-icon"><?php echo $item['is_dir']?'📁':'📄'; ?></span>
                <span class="file-name"><?php if($item['is_dir']): ?><a href="?tab=files&dir=<?php echo urlencode(ltrim($currentDir.'/'.$item['name'],'/')); ?>"><?php echo htmlspecialchars($item['name']); ?>/</a><?php else: echo htmlspecialchars($item['name']); endif; ?></span>
                <span class="file-size"><?php echo $item['is_dir']?'—':formatSize($item['size']); ?></span>
                <span class="file-date"><?php echo date('d.m.Y H:i', $item['modified']); ?></span>
                <span class="file-perms"><?php echo $item['perms']; ?></span>
                <span class="file-actions-btns">
                    <?php if(!$item['is_dir']): $ext=strtolower(pathinfo($item['name'],PATHINFO_EXTENSION)); if(in_array($ext,['php','html','css','js','txt','json','xml','htaccess','sql','md','log'])): ?><a href="?tab=files&dir=<?php echo urlencode($currentDir); ?>&edit=<?php echo urlencode($item['name']); ?>">✏️</a><?php endif; endif; ?>
                    <a href="?tab=files&dir=<?php echo urlencode($currentDir); ?>&delete=<?php echo urlencode($item['name']); ?>" class="del" onclick="return confirm('Удалить?')">🗑️</a>
                </span>
            </div>
            <?php endforeach; ?>
            <?php if(empty($items)): ?><div style="padding:20px;text-align:center;color:var(--text2);">Папка пуста</div><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>