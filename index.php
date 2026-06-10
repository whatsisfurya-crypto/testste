<?php
error_reporting(0);
ini_set('display_errors', 0);
define('SITE_ACCESS', true);

if (!file_exists('config/config.php')) { die('Config not found'); }
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharaonic Systems — Вход</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #06060b; --card: #0d0d15; --input: #13131f; --text: #e8e8f0; --text2: #8888a0; --border: #1f1f35; --vk: #0077FF; --red: #ef4444; --green: #10b981; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; display: flex;
            align-items: center; justify-content: center;
            overflow: hidden; position: relative;
        }
        .orb {
            position: absolute; border-radius: 50%;
            filter: blur(120px); opacity: 0.10;
            animation: float 20s infinite ease-in-out;
        }
        .orb:nth-child(1) { width: 500px; height: 500px; background: var(--primary); top: -150px; left: -150px; }
        .orb:nth-child(2) { width: 400px; height: 400px; background: #ec4899; bottom: -100px; right: -100px; animation-delay: -7s; }
        .orb:nth-child(3) { width: 300px; height: 300px; background: #6366f1; top: 50%; left: 50%; transform: translate(-50%,-50%); animation-delay: -14s; }
        @keyframes float { 0%,100%{transform:translate(0,0) scale(1)} 25%{transform:translate(50px,-50px) scale(1.1)} 50%{transform:translate(-30px,30px) scale(0.9)} 75%{transform:translate(-50px,-20px) scale(1.05)} }
        
        .login-box {
            position: relative; z-index: 1;
            background: var(--card); border-radius: 28px;
            padding: 48px 40px; width: 100%; max-width: 430px;
            border: 1px solid var(--border);
            box-shadow: 0 30px 70px rgba(0,0,0,0.6);
        }
        .login-box::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--primary), #ec4899, #6366f1, var(--primary));
            background-size: 300% 100%; animation: grad 4s ease infinite;
            border-radius: 28px 28px 0 0;
        }
        @keyframes grad { 0%,100%{background-position:0% 50%} 50%{background-position:100% 50%} }
        .logo-section { text-align: center; margin-bottom: 32px; }
        .logo-hex {
            width: 72px; height: 72px;
            background: linear-gradient(135deg, var(--primary), #ec4899);
            border-radius: 18px; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px; font-size: 22px; font-weight: 900; color: white;
            box-shadow: 0 12px 30px rgba(139,92,246,0.35);
        }
        .login-box h1 { font-size: 24px; font-weight: 700; margin-bottom: 4px; }
        .login-box .brand { font-size: 12px; color: var(--text2); letter-spacing: 2px; text-transform: uppercase; }
        .input-group { margin-bottom: 14px; position: relative; }
        .input-group .icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 16px; }
        .input-group input {
            width: 100%; padding: 14px 16px 14px 44px;
            background: var(--input); border: 2px solid var(--border);
            border-radius: 12px; color: var(--text);
            font-family: 'Montserrat', sans-serif; font-size: 14px;
            outline: none; transition: all 0.3s;
        }
        .input-group input:focus { border-color: var(--primary); }
        .login-btn {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            border: none; border-radius: 12px; color: white;
            font-family: 'Montserrat', sans-serif; font-size: 15px; font-weight: 600;
            cursor: pointer; margin-top: 6px; transition: all 0.3s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .login-btn:hover { transform: translateY(-2px); }
        .login-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .divider { display: flex; align-items: center; margin: 24px 0; color: var(--text2); font-size: 12px; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
        .divider span { padding: 0 14px; }
        .vk-btn {
            width: 100%; padding: 13px; background: var(--vk); border: none; border-radius: 12px;
            color: white; font-family: 'Montserrat', sans-serif; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .vk-btn:hover { background: #0066DD; transform: translateY(-2px); }
        .vk-icon { width: 24px; height: 24px; background: white; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 13px; color: var(--vk); }
        .notify { padding: 10px 14px; border-radius: 10px; margin-bottom: 14px; font-size: 12px; font-weight: 500; display: none; }
        .notify.error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: var(--red); }
        .notify.success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: var(--green); }
        .footer-text { text-align: center; margin-top: 20px; font-size: 11px; color: var(--text2); }
        .spinner { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-top: 2px solid white; border-radius: 50%; animation: spin 0.8s linear infinite; display: none; }
        @keyframes spin { to{transform:rotate(360deg)} }
    </style>
</head>
<body>
    <div class="orb"></div><div class="orb"></div><div class="orb"></div>
    
    <div class="login-box">
        <div class="logo-section">
            <div class="logo-hex">PS</div>
            <h1>Pharaonic Systems</h1>
            <span class="brand">Панель управления</span>
        </div>
        
        <div class="notify error" id="errorMsg"></div>
        <div class="notify success" id="successMsg"></div>
        
        <form id="loginForm">
            <div class="input-group">
                <span class="icon">👤</span>
                <input type="text" name="login" placeholder="Никнейм или Email" required autocomplete="off">
            </div>
            <div class="input-group">
                <span class="icon">🔒</span>
                <input type="password" name="password" placeholder="Пароль" required>
            </div>
            <button type="submit" class="login-btn">
                <span id="btnText">🚀 Войти</span>
                <div class="spinner" id="spinner"></div>
            </button>
        </form>
        
        <div class="divider"><span>или</span></div>
        
        <button class="vk-btn" onclick="window.location.href='php/vk_auth.php'">
            <div class="vk-icon">VK</div>
            Войти через ВКонтакте
        </button>
        
        <div class="footer-text">© 2020 — <?php echo date('Y'); ?> Pharaonic Systems</div>
    </div>
    
    <script>
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const err = document.getElementById('errorMsg');
        const suc = document.getElementById('successMsg');
        const btn = document.getElementById('btnText');
        const spin = document.getElementById('spinner');
        err.style.display = 'none'; suc.style.display = 'none';
        
        const fd = new FormData(this); fd.append('action', 'login');
        btn.textContent = '⏳ Вход...'; spin.style.display = 'inline-block';
        this.querySelector('.login-btn').disabled = true;
        
        try {
            const r = await fetch('php/auth.php', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.success) {
                suc.textContent = '✅ Вход выполнен!';
                suc.style.display = 'block';
                setTimeout(() => location.href = d.redirect, 500);
            } else {
                err.textContent = '❌ ' + (d.message || 'Неверный логин или пароль');
                err.style.display = 'block';
                reset();
            }
        } catch(ex) {
            err.textContent = '❌ Ошибка соединения с сервером';
            err.style.display = 'block';
            reset();
        }
        function reset() { btn.textContent = '🚀 Войти'; spin.style.display = 'none'; document.querySelector('.login-btn').disabled = false; }
    });
    </script>
</body>
</html>