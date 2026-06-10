CREATE DATABASE IF NOT EXISTS samp_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE samp_admin;

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    vk_id VARCHAR(50),
    avatar VARCHAR(255),
    role VARCHAR(50) DEFAULT 'admin',
    admin_level INT DEFAULT 1,
    department VARCHAR(50),
    position VARCHAR(50),
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    bans_count INT DEFAULT 0,
    kicks_count INT DEFAULT 0,
    warns_count INT DEFAULT 0,
    reports_processed INT DEFAULT 0,
    online_hours INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inactivity_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    start_date DATE,
    end_date DATE,
    reason TEXT,
    status ENUM('active', 'approved', 'rejected') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS offline_forms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    form_type ENUM('complaint', 'unban', 'question') NOT NULL,
    player_name VARCHAR(50),
    description TEXT,
    proof TEXT,
    admin_response TEXT,
    status ENUM('pending', 'processed', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(50),
    icon VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    product_id INT,
    amount DECIMAL(10,2),
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_balance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    balance DECIMAL(10,2) DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS action_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(50),
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45),
    login_used VARCHAR(100),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_key VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    level INT DEFAULT 1,
    color VARCHAR(7) DEFAULT '#6B7280',
    icon VARCHAR(50) DEFAULT 'shield',
    badge VARCHAR(10),
    permissions TEXT,
    is_custom BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_levels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    level INT UNIQUE NOT NULL,
    key_name VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7),
    icon VARCHAR(50),
    badge VARCHAR(10),
    permissions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    department VARCHAR(50),
    position_key VARCHAR(50),
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    permission_key VARCHAR(100),
    is_granted BOOLEAN DEFAULT TRUE,
    granted_by INT,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_permission (user_id, permission_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_settings (
    user_id INT PRIMARY KEY,
    profile_settings TEXT,
    notification_settings TEXT,
    privacy_settings TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    achievement_key VARCHAR(50),
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_achievement (user_id, achievement_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reputation (
    id INT PRIMARY KEY AUTO_INCREMENT,
    from_user_id INT,
    to_user_id INT,
    points INT DEFAULT 1,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS account_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    linked_user_id INT,
    link_type ENUM('ip', 'device', 'payment', 'manual'),
    evidence TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (linked_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_ips (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    ip_address VARCHAR(45),
    first_seen DATETIME,
    last_seen DATETIME,
    times_used INT DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS suspicious_activity (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    activity_type VARCHAR(50),
    description TEXT,
    severity ENUM('low', 'medium', 'high') DEFAULT 'low',
    is_resolved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plugins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    version VARCHAR(20),
    author VARCHAR(100),
    description TEXT,
    filename VARCHAR(255),
    is_active BOOLEAN DEFAULT FALSE,
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Вставка стандартных админ-уровней
INSERT INTO admin_levels (level, key_name, name, color, icon, badge, permissions) VALUES
(1, 'helper', 'Хелпер', '#9CA3AF', 'help-circle', 'H', '["dashboard.view","admin_list.view","inactivity.view_own","forms.view_own","profile.edit_own"]'),
(2, 'junior_moderator', 'Младший модератор', '#60A5FA', 'shield', 'JM', '["dashboard.view","admin_list.view","inactivity.view_own","inactivity.create","forms.view_own","forms.create","shop.view","profile.edit_own"]'),
(3, 'moderator', 'Модератор', '#3B82F6', 'shield-check', 'M', '["dashboard.view","admin_list.view","inactivity.view_own","inactivity.create","forms.view_own","forms.create","forms.view_all","forms.process","shop.view","shop.buy","logs.view","profile.edit_own","stats.view"]'),
(4, 'administrator', 'Администратор', '#10B981', 'shield-star', 'A', '["dashboard.view","admin_list.view","admin_list.view_details","inactivity.view_own","inactivity.create","inactivity.view_all","inactivity.approve","forms.view_own","forms.create","forms.view_all","forms.process","shop.view","shop.buy","shop.view_orders","console.view","console.execute_commands","logs.view","logs.view_admin","monitor.view","profile.edit_own","stats.view","stats.view_all","reputation.vote","users.view"]'),
(5, 'curator', 'Куратор', '#F59E0B', 'eye', 'К', '["dashboard.view","admin_list.view","admin_list.view_details","admin_list.manage","inactivity.view_own","inactivity.create","inactivity.view_all","inactivity.approve","forms.view_own","forms.create","forms.view_all","forms.process","forms.delete","shop.view","shop.buy","shop.view_orders","console.view","console.execute_commands","logs.view","logs.view_admin","monitor.view","monitor.search_accounts","users.view","users.create","profile.edit_own","profile.edit_others","stats.view","stats.view_all","reputation.vote","reputation.view","settings.view"]'),
(6, 'deputy_chief', 'Заместитель ГА', '#F97316', 'star', 'ЗГА', '["dashboard.view","admin_list.view","admin_list.view_details","admin_list.manage","inactivity.view_own","inactivity.create","inactivity.view_all","inactivity.approve","forms.view_own","forms.create","forms.view_all","forms.process","forms.delete","shop.view","shop.buy","shop.view_orders","console.view","console.execute_commands","console.restart_server","logs.view","logs.view_admin","logs.view_server","monitor.view","monitor.search_accounts","monitor.view_links","users.view","users.create","users.edit","users.change_roles","profile.edit_own","profile.edit_others","stats.view","stats.view_all","stats.export","reputation.vote","reputation.view","reputation.manage","achievements.view","settings.view","settings.edit"]'),
(7, 'chief_admin', 'Главный Администратор', '#EF4444', 'crown', 'ГА', '["dashboard.view","admin_list.view","admin_list.view_details","admin_list.manage","inactivity.view_own","inactivity.create","inactivity.view_all","inactivity.approve","forms.view_own","forms.create","forms.view_all","forms.process","forms.delete","shop.view","shop.buy","shop.manage_products","shop.view_orders","shop.manage_orders","console.view","console.execute_commands","console.restart_server","console.ban_players","logs.view","logs.view_admin","logs.view_server","logs.export","monitor.view","monitor.search_accounts","monitor.view_links","monitor.manage_flags","users.view","users.create","users.edit","users.delete","users.change_roles","profile.edit_own","profile.edit_others","stats.view","stats.view_all","stats.export","stats.compare","reputation.vote","reputation.view","reputation.manage","achievements.view","achievements.grant","settings.view","settings.edit","settings.backup","special.bypass_limits"]'),
(8, 'founder', 'Основатель', '#8B5CF6', 'star', 'ОС', '["*"]'),
(9, 'developer', 'Разработчик', '#EC4899', 'code', 'DEV', '["*"]');

-- Вставка тестового администратора (пароль: admin123)
INSERT INTO users (username, email, password, role, admin_level) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'developer', 9);