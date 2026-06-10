<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/roles_system.php';

if (!isset($_SESSION['user_id'])) { die('Access denied'); }
$roles = RolesSystem::getInstance($pdo);
$myLevel = $roles->getEffectiveLevel($_SESSION['user_id']);
$myPos = $roles->getUserPosition($_SESSION['user_id']);
$myDept = $myPos['department_name'] ?? '';
$canView = ($myLevel >= 5) || ($myDept === 'Следящие за тех.разделом');
if (!$canView) { die('Access denied'); }

$userId = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT u.*, s.* FROM users u LEFT JOIN admin_stats s ON u.id = s.user_id WHERE u.id = ?");
$stmt->execute([$userId]);
$admin = $stmt->fetch();
if (!$admin) { die('Пользователь не найден'); }

$pos = $roles->getUserPosition($userId);

// IP адреса
$stmt = $pdo->prepare("SELECT ip_address, last_seen FROM user_ips WHERE user_id = ? ORDER BY last_seen DESC LIMIT 5");
$stmt->execute([$userId]);
$ips = $stmt->fetchAll();

// Логи панели
$stmt = $pdo->prepare("SELECT * FROM action_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$panelLogs = $stmt->fetchAll();

// Наказания
$stmt = $pdo->prepare("SELECT * FROM admin_punishments WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$userId]);
$punishments = $stmt->fetchAll();

$li = [1=>'🛡️',2=>'🔰',3=>'⚖️',4=>'🔱',5=>'👁️',6=>'🎖️',7=>'👑',8=>'⚔️',9=>'⚒️'];
$ln = [1=>'Хелпер',2=>'Мл. модератор',3=>'Модератор',4=>'Администратор',5=>'Куратор',6=>'Зам. ГА',7=>'Главный Админ',8=>'Основатель',9=>'Разработчик'];
$lvl = $admin['admin_level'] ?? 1;
?>
<style>
.stats-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
.stats-table td { padding: 6px 10px; border-bottom: 1px solid var(--border); font-size: 12px; }
.stats-table td:first-child { color: var(--text2); width: 110px; }
.log-item { padding: 5px 0; border-bottom: 1px solid var(--border); font-size: 11px; display: flex; gap: 8px; }
.log-item .time { color: var(--text2); white-space: nowrap; }
.log-item .text { color: var(--text); }
.punish-item { padding: 5px 0; border-bottom: 1px solid var(--border); font-size: 11px; }
.punish-item .type { font-weight: 600; }
.punish-item .reason { color: var(--text2); }
.section-title { font-size: 13px; font-weight: 600; color: var(--text2); margin: 14px 0 8px; text-transform: uppercase; }
.prefix-badge { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 12px; font-weight: 600; background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid #10b981; }
</style>

<table class="stats-table">
    <tr><td>👤 Имя</td><td><strong><?php echo htmlspecialchars($admin['username']); ?></strong></td></tr>
    <?php if(!empty($admin['prefix'])): ?><tr><td>🏷️ Префикс</td><td><span class="prefix-badge">//<?php echo htmlspecialchars($admin['prefix']); ?></span></td></tr><?php endif; ?>
    <tr><td>⭐ Уровень</td><td><?php echo $li[$lvl]; ?> <?php echo $ln[$lvl]; ?> (<?php echo $lvl; ?>)</td></tr>
    <?php if($pos): ?><tr><td>📌 Должность</td><td><?php echo htmlspecialchars($pos['position_name'] ?? $pos['department_name'] ?? ''); ?></td></tr><?php endif; ?>
    <tr><td>📧 Email</td><td><?php echo htmlspecialchars($admin['email'] ?? '—'); ?></td></tr>
    <tr><td>🕐 В команде с</td><td><?php echo date('d.m.Y', strtotime($admin['created_at'])); ?></td></tr>
    <tr><td>🚫 Банов</td><td><?php echo $admin['bans_count']??0; ?></td></tr>
    <tr><td>⚡ Киков</td><td><?php echo $admin['kicks_count']??0; ?></td></tr>
    <tr><td>⚠️ Варнов</td><td><?php echo $admin['warns_count']??0; ?></td></tr>
    <tr><td>🕐 Часов в сети</td><td><?php echo $admin['online_hours']??0; ?>ч</td></tr>
</table>

<?php if(count($ips) > 0): ?>
<div class="section-title">🌐 Последние IP</div>
<table class="stats-table">
    <?php foreach($ips as $ip): ?>
    <tr><td><?php echo htmlspecialchars($ip['ip_address']); ?></td><td style="color:var(--text2);"><?php echo $ip['last_seen'] ? date('d.m.Y H:i', strtotime($ip['last_seen'])) : ''; ?></td></tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if(count($punishments) > 0): ?>
<div class="section-title">📋 Наказания</div>
<?php foreach($punishments as $p): ?>
<div class="punish-item">
    <span class="type"><?php echo $p['type']==='warning'?'⚠️ Предупреждение':'📋 Выговор'; ?></span>
    <div class="reason"><?php echo htmlspecialchars($p['reason']); ?></div>
    <div style="font-size:10px;color:var(--text2);"><?php echo date('d.m.Y', strtotime($p['created_at'])); ?></div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<div class="section-title">📜 Логи панели</div>
<?php if(count($panelLogs) > 0): ?>
    <?php foreach($panelLogs as $log): ?>
    <div class="log-item"><span class="time"><?php echo date('d.m H:i', strtotime($log['created_at'])); ?></span><span class="text"><?php echo htmlspecialchars($log['action']); ?>: <?php echo htmlspecialchars(substr($log['description'],0,100)); ?></span></div>
    <?php endforeach; ?>
<?php else: ?>
    <div style="color:var(--text2);font-size:12px;padding:8px 0;">Нет записей</div>
<?php endif; ?>