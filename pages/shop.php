<?php
define('SITE_ACCESS', true);
require_once '../config/database.php';
require_once '../php/cache.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit(); }

$tab = $_GET['tab'] ?? 'shop';

$pdo->exec("CREATE TABLE IF NOT EXISTS shop_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    product_name VARCHAR(100),
    amount DECIMAL(10,2),
    status VARCHAR(50) DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$message = '';

// Покупка
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy'])) {
    $productName = $_POST['product_name'];
    $productPrice = (float)$_POST['product_price'];
    
    if ($productName && $productPrice > 0) {
        $stmt = $pdo->prepare("INSERT INTO shop_orders (user_id, product_name, amount) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $productName, $productPrice]);
        $message = "✅ Покупка совершена! {$productName} за {$productPrice} ₽";
    }
}

// Товары
$products = [
    ['name' => '👑 VIP Статус', 'desc' => '🌟 Премиум доступ ко всем возможностям сервера. Выделяйся среди игроков!', 'price' => 500, 'color' => '#f59e0b'],
    ['name' => '✏️ Смена ника', 'desc' => '✏️ Измени свой никнейм в любое время. Будь уникальным!', 'price' => 200, 'color' => '#3b82f6'],
    ['name' => '💎 Донат-валюта', 'desc' => '💎 Пополни баланс на 1000 коинов. Трать на что хочешь!', 'price' => 1000, 'color' => '#8b5cf6'],
    ['name' => '🔓 Разбан', 'desc' => '🔓 Получи второй шанс! Снимаем бан с твоего аккаунта.', 'price' => 300, 'color' => '#10b981'],
    ['name' => '🌟 Премиум-подписка', 'desc' => '🌟 Месяц полного премиум доступа. Все функции без ограничений!', 'price' => 800, 'color' => '#ec4899'],
    ['name' => '👕 Эксклюзивный скин', 'desc' => '👕 Уникальный скин, которого нет ни у кого. Стань легендой!', 'price' => 1500, 'color' => '#6366f1'],
];

// История покупок
$stmt = $pdo->prepare("SELECT * FROM shop_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><title>🛒 Магазин — Pharaonic Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #050508; --card: #0e0e18; --text: #e8e8f0; --text2: #8888a0; --border: #1e1e30; --green: #10b981; --pink: #ec4899; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .topbar { background: rgba(14,14,24,0.97); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; }
        .topbar a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        .page-title { text-align: center; margin-bottom: 24px; } .page-title h1 { font-size: 28px; font-weight: 800; } .page-title p { color: var(--text2); }
        .alert { padding: 12px 18px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }
        
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; justify-content: center; }
        .tabs a { padding: 10px 22px; border-radius: 10px; color: var(--text2); text-decoration: none; font-weight: 500; font-size: 13px; border: 1px solid var(--border); transition: all 0.2s; }
        .tabs a:hover, .tabs a.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .product-card { background: var(--card); border: 2px solid var(--border); border-radius: 18px; padding: 24px; transition: all 0.3s; display: flex; flex-direction: column; }
        .product-card:hover { transform: translateY(-3px); border-color: var(--primary); }
        .product-card h3 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .product-card p { color: var(--text2); font-size: 13px; line-height: 1.6; flex: 1; margin-bottom: 16px; }
        .product-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid var(--border); }
        .price { font-size: 22px; font-weight: 800; color: var(--primary); }
        .buy-btn { padding: 10px 22px; background: var(--primary); border: none; border-radius: 10px; color: white; font-weight: 600; cursor: pointer; font-size: 13px; transition: all 0.3s; }
        .buy-btn:hover { background: #7c3aed; }
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--text2); font-size: 11px; text-transform: uppercase; }
        .empty-state { background: var(--card); border: 2px solid var(--border); border-radius: 16px; padding: 30px; text-align: center; color: var(--text2); font-size: 14px; }
        
        @media (max-width: 600px) { .products-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <header class="topbar"><a href="dashboard.php">← Назад</a></header>
    <div class="container">
        <div class="page-title"><h1>🛒 Магазин</h1><p>Товары и услуги сервера</p></div>
        <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
        
        <div class="tabs">
            <a href="?tab=shop" class="<?php echo $tab==='shop'?'active':''; ?>">🛒 Товары</a>
            <a href="?tab=history" class="<?php echo $tab==='history'?'active':''; ?>">📋 История покупок</a>
        </div>
        
        <?php if($tab === 'shop'): ?>
        <div class="products-grid">
            <?php foreach($products as $p): ?>
            <div class="product-card">
                <h3><?php echo $p['name']; ?></h3>
                <p><?php echo $p['desc']; ?></p>
                <div class="product-footer">
                    <span class="price"><?php echo number_format($p['price'], 0, '', ' '); ?> ₽</span>
                    <form method="POST"><input type="hidden" name="product_name" value="<?php echo $p['name']; ?>"><input type="hidden" name="product_price" value="<?php echo $p['price']; ?>"><button type="submit" name="buy" class="buy-btn">🛒 Купить</button></form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php else: ?>
        <h3 style="margin-bottom:16px;">📋 История покупок (<?php echo count($orders); ?>)</h3>
        <?php if(count($orders) > 0): ?>
        <table>
            <thead><tr><th>Дата</th><th>Товар</th><th>Сумма</th><th>Статус</th></tr></thead>
            <tbody>
            <?php foreach($orders as $o): ?>
            <tr>
                <td><?php echo date('d.m.Y H:i', strtotime($o['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($o['product_name']); ?></td>
                <td><strong><?php echo number_format($o['amount'], 0, '', ' '); ?> ₽</strong></td>
                <td><span style="color:var(--green);">✅ Выполнен</span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">📭 У вас пока нет покупок</div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>