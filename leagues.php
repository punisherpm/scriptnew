<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once 'includes/auth.php';
require_once 'includes/db_old.php';

// Загружаем все лиги
$query = "SELECT id, name FROM leagues ORDER BY name ASC";
$result = mysql_query($query);

if (!$result) {
    die("Ошибка запроса: " . mysql_error());
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Список лиг</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(to bottom right, #e0f7ff, #f0eaff);
    margin: 0; padding: 0;
}
.container {
    max-width: 800px;
    margin: 30px auto;
    padding: 25px;
    border-radius: 20px;
    background: rgba(255,255,255,0.25);
    box-shadow: 0 8px 32px rgba(31,38,135,0.37);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.18);
}
h1 {
    text-align: center;
    color: #222;
    margin-bottom: 20px;
}
ul.league-list {
    list-style: none;
    padding-left: 0;
}
ul.league-list li {
    background: rgba(255,255,255,0.4);
    margin: 8px 0;
    padding: 12px 15px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}
ul.league-list li:hover { transform: translateY(-2px); }
ul.league-list li a {
    color: #222;
    font-weight: bold;
    text-decoration: none;
}
ul.league-list li a:hover { text-decoration: underline; }
.btn-back {
    display: inline-block;
    margin-bottom: 20px;
    padding: 8px 14px;
    background: rgba(255,255,255,0.25);
    border-radius: 6px;
    text-decoration: none;
    color: #222;
    font-weight: bold;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    transition: background 0.2s, transform 0.2s;
}
.btn-back:hover { 
    background: rgba(255,255,255,0.35);
    transform: translateY(-2px);
}
</style>
</head>
<body>
<div class="container">
    <a href="home.php" class="btn-back">⬅ Назад</a>
    <h1>Список лиг</h1>
    <ul class="league-list">
        <?php while ($league = mysql_fetch_assoc($result)) { ?>
            <li>
                <a href="league.php?id=<?php echo $league['id']; ?>">
                    <?php echo htmlspecialchars($league['name']); ?>
                </a>
            </li>
        <?php } ?>
    </ul>
</div>
</body>
</html>
