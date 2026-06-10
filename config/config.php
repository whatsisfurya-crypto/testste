<?php
if (!defined('SITE_ACCESS')) {
    define('SITE_ACCESS', true);
}

// ============================================
// БАЗА ДАННЫХ
// ============================================
define('DB_HOST', '87.228.73.103');
define('DB_PORT', '3306');
define('DB_NAME', 'gs443');
define('DB_USER', 'gs443');
define('DB_PASS', 'mgRDEFZehAY');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// САЙТ
// ============================================
define('SITE_NAME', 'Pharaonic Systems');
define('SITE_URL', 'https://pharaonic-system.ru');
define('SITE_TIMEZONE', 'Europe/Moscow');
date_default_timezone_set('Europe/Moscow');

// ============================================
// SAMP СЕРВЕР
// ============================================
define('SAMP_SERVER_IP', '127.0.0.1');
define('SAMP_SERVER_PORT', 7777);
define('SAMP_RCON_PASSWORD', 'change_me');

// ============================================
// БЕЗОПАСНОСТЬ
// ============================================
define('API_KEY', 'gs443_maze_tech_secret_key_2024');
define('PASSWORD_MIN_LENGTH', 6);
define('LOGIN_ATTEMPTS_MAX', 5);
define('LOGIN_ATTEMPTS_TIMEOUT', 900);
define('SESSION_LIFETIME', 86400);

// ============================================
// VK OAuth
// ============================================
define('VK_CLIENT_ID', '54629944');
define('VK_CLIENT_SECRET', 'ml6Nj2ZY7Y3IXBXgdLFJ');
define('VK_REDIRECT_URI', 'https://pharaonic-system.ru/php/vk_auth.php');

// ============================================
// ПОЧТА
// ============================================
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', '');
define('MAIL_FROM_ADDRESS', 'noreply@pharaonic-system.ru');

// ============================================
// ЗАГРУЗКИ
// ============================================
define('UPLOAD_MAX_SIZE', 5242880);

// ============================================
// ПУТИ
// ============================================
define('ROOT_DIR', dirname(__DIR__) . '/');
define('CONFIG_DIR', ROOT_DIR . 'config/');
define('PAGES_DIR', ROOT_DIR . 'pages/');
define('PHP_DIR', ROOT_DIR . 'php/');
define('CSS_DIR', ROOT_DIR . 'css/');
define('JS_DIR', ROOT_DIR . 'js/');
define('UPLOAD_PATH', ROOT_DIR . 'uploads/');
define('CACHE_DIR', ROOT_DIR . 'cache/');
define('LOG_DIR', ROOT_DIR . 'logs/');
define('BACKUP_DIR', ROOT_DIR . 'backups/');

// ============================================
// ПРОИЗВОДИТЕЛЬНОСТЬ
// ============================================
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 300);
define('GZIP_ENABLED', true);
define('MINIFY_OUTPUT', true);
define('DEBUG_MODE', false);
define('MAINTENANCE_MODE', false);

// ============================================
// ЦВЕТА
// ============================================
define('COLOR_PRIMARY', '#8b5cf6');
define('COLOR_SUCCESS', '#10b981');
define('COLOR_DANGER', '#ef4444');
define('COLOR_WARNING', '#f59e0b');
define('COLOR_INFO', '#3b82f6');
?>