<?php
require_once 'includes/db_old.php';
header('Content-Type: text/html; charset=utf-8');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Неверная лига");
}

$league_id = intval($_GET['id']);

// Проверяем, есть ли такая лига
$league_res = mysql_query("SELECT name FROM leagues WHERE id = $league_id", $db);
if (!$league_res || mysql_num_rows($league_res) == 0) {
    die("Неверная лига");
}
$league = mysql_fetch_assoc($league_res);

// Загружаем клубы этой лиги
$res = mysql_query("SELECT id, name FROM clubs WHERE league_id = $league_id ORDER BY name", $db);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Клубы лиги <?php echo htmlspecialchars($league['name']); ?></title>
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
ul.club-list {
    list-style: none;
    padding-left: 0;
}
ul.club-list li {
    background: rgba(255,255,255,0.4);
    margin: 8px 0;
    padding: 12px 15px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}
ul.club-list li:hover { transform: translateY(-2px); }
ul.club-list li a {
    color: #222;
    font-weight: bold;
    text-decoration: none;
}
ul.club-list li a:hover { text-decoration: underline; }
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
    <a href="leagues.php" class="btn-back">← Назад к списку лиг</a>
    <h1>Клубы лиги: <?php echo htmlspecialchars($league['name']); ?></h1>
    <ul class="club-list">
        <?php while ($row = mysql_fetch_assoc($res)): ?>
            <li>
                <a href="club.php?club_id=<?php echo $row['id']; ?>">
                    <?php echo htmlspecialchars($row['name']); ?>
                </a>
            </li>
        <?php endwhile; ?>
    </ul>
</div>
</body>
</html>
