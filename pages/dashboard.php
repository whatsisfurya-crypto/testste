<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
require_once '../php/roles_system.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

$roles = RolesSystem::getInstance($pdo);
$userId = $_SESSION['user_id'];
$userLevel = $roles->getEffectiveLevel($userId);

$stmt = $pdo->prepare("SELECT u.*, s.* FROM users u LEFT JOIN admin_stats s ON u.id = s.user_id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$position = $roles->getUserPosition($userId);
$deptName = $position['department_name'] ?? '';
$posName = $position['position_name'] ?? '';

$levelNames = [0=>'Пользователь',1=>'Хелпер',2=>'Мл. модератор',3=>'Модератор',4=>'Администратор',5=>'Куратор',6=>'Зам. ГА',7=>'Главный Админ',8=>'Основатель',9=>'Разработчик'];
$levelColors = [0=>'#9CA3AF',1=>'#9CA3AF',2=>'#60A5FA',3=>'#3B82F6',4=>'#10B981',5=>'#F59E0B',6=>'#F97316',7=>'#EF4444',8=>'#8B5CF6',9=>'#EC4899'];
$levelIcons = [0=>'👤',1=>'🛡️',2=>'🔰',3=>'⚖️',4=>'🔱',5=>'👁️',6=>'🎖️',7=>'👑',8=>'⚔️',9=>'⚒️'];

$isAdmin = ($userLevel >= 1);
$isDev = ($userLevel >= 9);
$isLeader = ($deptName === 'Лидерство' || $position['department'] === 'leadership');
$leaderPos = $position['position_key'] ?? '';
$isLeaderGov = ($isLeader && (strpos($leaderPos, 'gov') !== false));
$isLeaderIllegal = ($isLeader && (strpos($leaderPos, 'illegal') !== false));

$canManageServer = ($userLevel >= 5) || ($deptName === 'Технический раздел');
$canManageLeaders = ($userLevel >= 7) || 
    ($userLevel >= 5 && $deptName === 'Гос.структуры' && (strpos($posName,'ГС')!==false || strpos($posName,'ЗГС')!==false)) ||
    ($deptName === 'Нелегал' && (strpos($posName,'ГС')!==false || strpos($posName,'ЗГС')!==false));

$teamButton = ['name' => '👨‍💻 Команда сайта', 'desc' => 'Разработчики проекта', 'color' => '#0891b2', 'link' => 'team.php', 'perm' => 'team.view'];
$inactivityButton = ['name' => '⏰ Неактив', 'desc' => 'Взять неактив', 'color' => '#f59e0b', 'link' => 'inactivity.php', 'perm' => 'inactivity.view_own'];

// Категория Игрок (видна ВСЕМ)
$playerSections = [
    ['name' => '📊 Онлайн сервера', 'desc' => 'Кто сейчас в игре', 'color' => '#10b981', 'link' => 'server_status.php', 'perm' => ''],
    ['name' => '👤 Моя статистика', 'desc' => 'Личная статистика', 'color' => '#3b82f6', 'link' => 'player_stats.php', 'perm' => ''],
    ['name' => '🔍 Поиск игрока', 'desc' => 'Статистика игроков', 'color' => '#f59e0b', 'link' => 'player_search.php', 'perm' => ''],
    ['name' => '🏆 Топ игроков', 'desc' => 'Рейтинг по часам', 'color' => '#8b5cf6', 'link' => 'top_players.php', 'perm' => ''],
    ['name' => '🛒 Магазин', 'desc' => 'Покупка товаров и услуг', 'color' => '#ec4899', 'link' => 'shop.php', 'perm' => ''],
    ['name' => '👨‍💻 Команда сайта', 'desc' => 'Разработчики проекта', 'color' => '#0891b2', 'link' => 'team.php', 'perm' => 'team.view']
];

$adminSections = [];
if ($isAdmin) {
    $adminSections = [
        ['name' => '📋 Оффлайн формы', 'desc' => 'Жалобы, баны, варны, муты, джаилы', 'color' => '#3b82f6', 'link' => 'offline_forms.php', 'perm' => 'forms.view_own'],
        ['name' => '👥 Администрация', 'desc' => 'Список администраторов', 'color' => '#10b981', 'link' => 'admin_list.php', 'perm' => 'admin_list.view'],
        ['name' => '🛒 Админ-магазин', 'desc' => 'Служебные инструменты', 'color' => '#ec4899', 'link' => 'admin_shop.php', 'perm' => ''],
    ];
    if ($canManageServer) $adminSections[] = ['name' => '⚙️ Управление сервером', 'desc' => 'Логи, консоль, мониторинг', 'color' => '#6366f1', 'link' => 'server_logs.php', 'perm' => 'logs.view_server'];
    $adminSections[] = $inactivityButton;
    if ($canManageLeaders) {
        $adminSections[] = ['name' => '👑 Список Лидеров/Заместителей', 'desc' => 'Просмотр, назначение и анкеты', 'color' => '#f59e0b', 'link' => 'leader_handler.php', 'perm' => ''];
    }
    $adminSections[] = $teamButton;
}

$devSections = [];
if ($isDev) {
    $devSections = [
        ['name' => '🔧 Панель разработчика', 'desc' => 'Система, БД, логи, файлы', 'color' => '#ec4899', 'link' => 'developer_panel.php', 'perm' => 'settings.view'],
        ['name' => '⚙️ Настройки панели', 'desc' => 'Сервер, VK, роли, должности', 'color' => '#6366f1', 'link' => 'panel_settings.php', 'perm' => 'settings.view'],
    ];
}

$leaderGovSections = [];
if ($isLeaderGov) {
    $leaderGovSections = [
        ['name' => '🚔 Гос. волна', 'desc' => 'Забить государственную волну', 'color' => '#3b82f6', 'link' => '#', 'perm' => ''],
        ['name' => '👥 Сотрудники фракции', 'desc' => 'Управление составом', 'color' => '#f59e0b', 'link' => '#', 'perm' => ''],
        ['name' => '🛒 Магазин фракции', 'desc' => 'Снять -1 день, выговор, пред', 'color' => '#ec4899', 'link' => '#', 'perm' => ''],
        $inactivityButton, $teamButton
    ];
}

$leaderIllegalSections = [];
if ($isLeaderIllegal) {
    $leaderIllegalSections = [
        ['name' => '🏹 Стрела', 'desc' => 'Забить стрелу', 'color' => '#dc2626', 'link' => '#', 'perm' => ''],
        ['name' => '👥 Сотрудники фракции', 'desc' => 'Управление составом', 'color' => '#f59e0b', 'link' => '#', 'perm' => ''],
        ['name' => '🛒 Магазин фракции', 'desc' => 'Снять -1 день, выговор, пред', 'color' => '#ec4899', 'link' => '#', 'perm' => ''],
        $inactivityButton, $teamButton
    ];
}

$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) $greeting = 'Доброе утро';
elseif ($hour >= 12 && $hour < 17) $greeting = 'Добрый день';
elseif ($hour >= 17 && $hour < 23) $greeting = 'Добрый вечер';
else $greeting = 'Доброй ночи';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharaonic Systems — Панель управления</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --red: #ef4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; font-size: 14px; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .logo { font-weight: 700; font-size: 16px; color: var(--primary); }
        .profile-btn { display: flex; align-items: center; gap: 10px; padding: 8px 16px; background: var(--card); border: 1px solid var(--border); border-radius: 30px; cursor: pointer; transition: all 0.2s; color: var(--text); }
        .profile-btn:hover { border-color: var(--primary); }
        .profile-btn span { font-size: 14px; font-weight: 600; }
        .profile-btn .arrow { font-size: 10px; transition: transform 0.3s; }
        .profile-btn.active .arrow { transform: rotate(180deg); }
        .profile-dropdown { display: none; position: absolute; top: calc(100% + 8px); right: 0; width: 240px; background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 14px; z-index: 200; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
        .profile-dropdown.show { display: block; animation: fadeDown 0.3s ease; }
        @keyframes fadeDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
        .dropdown-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
        .dropdown-header img,.dropdown-header .ph { width: 44px; height: 44px; border-radius: 50%; border: 2px solid var(--primary); object-fit: cover; }
        .dropdown-header .ph { display: inline-flex; align-items: center; justify-content: center; background: #0e0e18; }
        .dropdown-header .name { font-size: 15px; font-weight: 700; }
        .dropdown-links { display: flex; flex-direction: column; gap: 2px; }
        .dropdown-links a { padding: 10px 12px; border-radius: 8px; color: var(--text2); text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 8px; }
        .dropdown-links a:hover { background: rgba(139,92,246,0.08); color: var(--text); }
        .dropdown-links .logout-btn { color: var(--red); margin-top: 4px; padding-top: 10px; border-top: 1px solid var(--border); background: none; border: none; cursor: pointer; font-family: 'Montserrat', sans-serif; font-size: 13px; text-align: left; width: 100%; }
        .dropdown-links .logout-btn:hover { background: rgba(239,68,68,0.08); }
        .main-content { max-width: 1200px; margin: 0 auto; padding: 40px 24px; }
        .main-content h1 { text-align: center; font-size: 32px; font-weight: 800; margin-bottom: 8px; }
        .main-content .sub { text-align: center; color: var(--text2); margin-bottom: 40px; font-size: 15px; }
        .category { margin-bottom: 36px; }
        .category-title { font-size: 16px; font-weight: 700; color: var(--text2); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 2px solid var(--border); display: flex; align-items: center; gap: 8px; }
        .category-title .dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        .sections-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
        .section-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 22px 18px; text-decoration: none; color: var(--text); transition: all 0.3s; display: flex; align-items: center; gap: 14px; }
        .section-card:hover { transform: translateY(-3px); border-color: var(--primary); box-shadow: 0 15px 40px rgba(0,0,0,0.4); }
        .section-card.locked { opacity: 0.4; pointer-events: none; }
        .s-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .s-info h4 { font-size: 14px; font-weight: 600; margin-bottom: 2px; }
        .s-info p { font-size: 11px; color: var(--text2); }
        @media (max-width: 1000px) { .sections-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .sections-grid { grid-template-columns: 1fr; } .main-content h1 { font-size: 24px; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo">Pharaonic Systems</div>
        <div style="position: relative;">
            <div class="profile-btn" id="profileBtn" onclick="toggleProfile()">
                <span><?php echo htmlspecialchars($user['username']); ?></span>
                <span class="arrow">▼</span>
            </div>
            <div class="profile-dropdown" id="profileDropdown">
                <div class="dropdown-header">
                    <?php if(!empty($user['avatar'])): ?><img src="../<?php echo $user['avatar']; ?>"><?php else: ?><div class="ph"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8888a0" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-7 8-7s8 3 8 7"/></svg></div><?php endif; ?>
                    <div><div class="name"><?php echo htmlspecialchars($user['username']); ?></div></div>
                </div>
                <div class="dropdown-links">
                    <a href="admin_profile.php?id=<?php echo $userId; ?>">👤 Мой профиль</a>
                    <a href="profile_settings.php">⚙️ Настройки</a>
                    <form method="POST" action="../php/logout.php"><button type="submit" class="logout-btn">🚪 Выйти</button></form>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <h1><?php echo $greeting; ?>, <span style="color:<?php echo $levelColors[$userLevel]; ?>;"><?php echo htmlspecialchars($user['username']); ?></span>!</h1>
        <p class="sub">Выберите нужный раздел</p>

        <?php
        function renderCategory($title, $color, $sections, $userLevel, $roles, $userId) {
            if (empty($sections)) return;
            echo '<div class="category"><div class="category-title"><span class="dot" style="background:'.$color.'"></span>'.$title.'</div><div class="sections-grid">';
            foreach ($sections as $s) {
                $locked = (!empty($s['perm']) && $userLevel < 8 && !$roles->hasPermission($userId, $s['perm']));
                echo '<a href="'.$s['link'].'" class="section-card'.($locked?' locked':'').'"><div class="s-icon" style="background:'.$s['color'].'15;">'.explode(' ',$s['name'])[0].'</div><div class="s-info"><h4>'.substr($s['name'],strpos($s['name'],' ')+1).'</h4><p>'.$s['desc'].'</p></div></a>';
            }
            echo '</div></div>';
        }

        renderCategory('🎮 Игрок', '#10b981', $playerSections, $userLevel, $roles, $userId);
        if ($isAdmin) renderCategory('🔱 Администратор', '#10b981', $adminSections, $userLevel, $roles, $userId);
        if ($isDev) renderCategory('⚒️ Разработчик', '#EC4899', $devSections, $userLevel, $roles, $userId);
        if ($isLeaderGov) renderCategory('👑 Лидер Гос.Структур', '#3b82f6', $leaderGovSections, $userLevel, $roles, $userId);
        if ($isLeaderIllegal) renderCategory('👑 Лидер Нелегал.Организаций', '#ef4444', $leaderIllegalSections, $userLevel, $roles, $userId);
        ?>
    </main>

    <script>
    function toggleProfile(){const d=document.getElementById('profileDropdown');const b=document.getElementById('profileBtn');d.classList.toggle('show');b.classList.toggle('active');}
    document.addEventListener('click',function(e){const b=document.getElementById('profileBtn');const d=document.getElementById('profileDropdown');if(!b.contains(e.target)&&!d.contains(e.target)){d.classList.remove('show');b.classList.remove('active');}});
    </script>
</body>
</html>