<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'buy') {
        $productId = $_POST['product_id'] ?? 0;
        $userId = $_SESSION['user_id'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM shop_products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Товар не найден']);
                exit();
            }
            
            $stmt = $pdo->prepare("INSERT INTO shop_orders (user_id, product_id, amount, status) VALUES (?, ?, ?, 'completed')");
            $stmt->execute([$userId, $productId, $product['price']]);
            
            echo json_encode(['success' => true, 'message' => 'Покупка совершена!']);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
        }
        exit();
    }
}
?>