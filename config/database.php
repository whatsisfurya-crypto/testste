<?php
require_once __DIR__ . '/config.php';

// Кэш подключения
static $pdoInstance = null;

if ($pdoInstance !== null) {
    $pdo = $pdoInstance;
} else {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
            PDO::ATTR_PERSISTENT => true // Постоянное соединение
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdoInstance = $pdo;
    } catch(PDOException $e) {
        die("DB Error");
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// GZIP сжатие
if (GZIP_ENABLED && !ob_get_level()) {
    ob_start('ob_gzhandler');
}
?>