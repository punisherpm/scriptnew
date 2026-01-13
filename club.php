<?php
require_once 'includes/auth.php';
require_once 'includes/db_old.php';

if (!isset($_GET['club_id']) || !is_numeric($_GET['club_id'])) {
    die("Неверный ID клуба");
}

$club_id = intval($_GET['club_id']);

// Определяем сортировку
$sortable_columns = array('name','nationality','age','position','salary','salary_buyout'); // добавим зарплату*15 как buyout
$sort = isset($_GET['sort']) && in_array($_GET['sort'],$sortable_columns) ? $_GET['sort'] : 'name';
$order = (isset($_GET['order']) && $_GET['order'] === 'desc') ? 'DESC' : 'ASC';
$next_order = $order === 'ASC' ? 'desc' : 'asc';

// Получение данных клуба
$res = mysql_query("SELECT * FROM clubs WHERE id = $club_id", $db);
if (!$res || mysql_num_rows($res) == 0) {
    die("Клуб не найден");
}
$club = mysql_fetch_assoc($res);

// Получаем игроков клуба с сортировкой
$players_res = mysql_query("
    SELECT *, salary*15 as salary_buyout 
    FROM players 
    WHERE club_id = $club_id 
    ORDER BY $sort $order
", $db);

function format_price($value) {
    $value = intval($value);
    return ($value % 1000000 === 0) ? ($value/1000000).' млн' : round($value/1000000,1).' млн';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($club['name']); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body { font-family:'Segoe UI',Arial,sans-serif; background:linear-gradient(to bottom right,#e0f7ff,#f0eaff); margin:0; padding:0; }
.container { max-width:1000px; margin:30px auto; padding:25px; border-radius:20px; background:rgba(255,255,255,0.25); box-shadow:0 8px 32px rgba(31,38,135,0.37); backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,0.18); }
h1 { text-align:center; color:#222; margin-bottom:20px; }
.budget { font-weight:bold; margin-bottom:15px; text-align:center; }
table { width:100%; border-collapse:collapse; }
thead th { background:rgba(255,255,255,0.35); padding:10px; text-align:left; border-bottom:2px solid rgba(0,0,0,0.1); cursor:pointer; }
tbody tr { background:rgba(255,255,255,0.4); transition: transform 0.2s; }
tbody tr:hover { transform:translateY(-2px); }
tbody td { padding:10px; border-bottom:1px solid rgba(0,0,0,0.1); vertical-align:middle; }
img.player-photo { width:40px; height:40px; border-radius:50%; object-fit:cover; }
a { color:#0066cc; text-decoration:none; }
a:hover { text-decoration:underline; }
.btn-back { display:inline-block; margin-bottom:20px; padding:8px 14px; background:rgba(255,255,255,0.25); border-radius:6px; text-decoration:none; color:#222; font-weight:bold; box-shadow:0 4px 12px rgba(0,0,0,0.2); transition:background 0.2s, transform 0.2s; }
.btn-back:hover { background:rgba(255,255,255,0.35); transform:translateY(-2px); }
.sort-arrow { margin-left:5px; font-size:0.8em; }
</style>
</head>
<body>
<div class="container">
    <a href="league.php?id=<?php echo $club['league_id']; ?>" class="btn-back">← Назад к лиге</a>
    <h1><?php 
    $logo_path = "uploads/logos/".$club['id'].".png";
    if(file_exists($logo_path)) {
        echo '<img src="'.$logo_path.'" alt="Эмблема" style="height:50px; vertical-align:middle; margin-right:10px; border-radius:8px;">';
    } else {
        echo '<img src="uploads/logos/default.png" alt="Эмблема" style="height:50px; vertical-align:middle; margin-right:10px; border-radius:8px;">';
    }
    ?>
    <?php echo htmlspecialchars($club['name']); ?></h1>

    <div class="budget">Бюджет клуба: <?php echo number_format($club['budget'],0,',',' '); ?> млн</div>

    <table>
        <thead>
            <tr>
                <th>Фото</th>
                <th><a href="?club_id=<?php echo $club_id; ?>&sort=name&order=<?php echo $next_order; ?>">Имя
                    <?php if($sort=='name') echo $order=='ASC' ? '▲' : '▼'; ?></a></th>
                <th><a href="?club_id=<?php echo $club_id; ?>&sort=nationality&order=<?php echo $next_order; ?>">Национальность
                    <?php if($sort=='nationality') echo $order=='ASC' ? '▲' : '▼'; ?></a></th>
                <th><a href="?club_id=<?php echo $club_id; ?>&sort=age&order=<?php echo $next_order; ?>">Возраст
                    <?php if($sort=='age') echo $order=='ASC' ? '▲' : '▼'; ?></a></th>
                <th><a href="?club_id=<?php echo $club_id; ?>&sort=position&order=<?php echo $next_order; ?>">Позиция
                    <?php if($sort=='position') echo $order=='ASC' ? '▲' : '▼'; ?></a></th>
                <th><a href="?club_id=<?php echo $club_id; ?>&sort=salary&order=<?php echo $next_order; ?>">Зарплата
                    <?php if($sort=='salary') echo $order=='ASC' ? '▲' : '▼'; ?></a></th>
                <th><a href="?club_id=<?php echo $club_id; ?>&sort=salary_buyout&order=<?php echo $next_order; ?>">Выкуп
                    <?php if($sort=='salary_buyout') echo $order=='ASC' ? '▲' : '▼'; ?></a></th>
            </tr>
        </thead>
        <tbody>
        <?php while($player = mysql_fetch_assoc($players_res)): ?>
            <tr>
                <td>
                    <?php 
                    $photo_path = "img/players/".$player['id'].".png";
                    if(file_exists($photo_path)) {
                        echo '<img src="'.$photo_path.'" alt="Фото" class="player-photo">';
                    } else {
                        echo '<img src="img/players/default.png" alt="Фото" class="player-photo">';
                    }
                    ?>
                </td>
                <td><a href="player.php?id=<?php echo $player['id']; ?>"><?php echo htmlspecialchars($player['name']); ?></a></td>
                <td><?php echo htmlspecialchars($player['nationality']); ?></td>
                <td><?php echo intval($player['age']); ?></td>
                <td><?php echo htmlspecialchars($player['position']); ?></td>
                <td><?php echo format_price($player['salary']); ?></td>
                <td><?php echo format_price($player['salary']*15); ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
