<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';
require_once 'includes/auth.php';

if (is_logged_in()) {
    header("Location: home.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = isset($_POST['remember']); // чекбокс "Запомнить меня"

    if (login($username, $password, $remember)) {
        header("Location: home.php");
        exit;
    } else {
        $error = "Неверный логин или пароль";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Вход в систему</title>
<style>
body {
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(to bottom right, #e0f7ff, #f0eaff);
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100vh;
}
.login-container {
    width: 360px;
    padding: 30px;
    border-radius: 20px;
    background: rgba(255,255,255,0.25);
    box-shadow: 0 8px 32px rgba(31,38,135,0.37);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.18);
    text-align: center;
}
h2 {
    color: #222;
    margin-bottom: 20px;
}
input[type="text"], input[type="password"] {
    width: 90%;
    padding: 10px;
    margin: 8px 0;
    border: none;
    border-radius: 8px;
    background: rgba(255,255,255,0.6);
    font-size: 15px;
}
input:focus {
    outline: none;
    background: rgba(255,255,255,0.8);
}
button {
    width: 95%;
    padding: 10px;
    margin-top: 15px;
    background: rgba(255,255,255,0.25);
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: bold;
    color: #222;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    cursor: pointer;
    transition: background 0.2s, transform 0.2s;
}
button:hover {
    background: rgba(255,255,255,0.35);
    transform: translateY(-2px);
}
.error {
    color: red;
    margin-bottom: 10px;
    font-weight: bold;
}
.footer {
    margin-top: 20px;
    font-size: 13px;
    color: #555;
}
.remember {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 10px;
    font-size: 14px;
    color: #333;
}
.remember input {
    width: auto;
    accent-color: #0066cc;
}
</style>
</head>
<body>
<div class="login-container">
    <h2>Вход в систему</h2>
    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Логин" required><br>
        <input type="password" name="password" placeholder="Пароль" required><br>

        <div class="remember">
            <input type="checkbox" name="remember" id="remember">
            <label for="remember">Запомнить меня</label>
        </div>

        <button type="submit">Войти</button>
    </form>
    <div class="footer">
        © pm5online.ru
    </div>
</div>
</body>
</html>
