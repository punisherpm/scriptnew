<?php
session_start();
require_once 'includes/db_old.php';

if (!isset($_SESSION['user'])) {
    die("Доступ запрещён. Пожалуйста, войдите в систему.");
}

$user = $_SESSION['user'];
$user_id = intval($user['id']);
$club_id = intval($user['club_id']);

// Фильтр по статусу лота
$status_filter = isset($_GET['filter']) ? $_GET['filter'] : 'active';

$where_clause = "tl.seller_id = $user_id";
if ($status_filter == 'active') {
    $where_clause .= " AND tl.active = 1";
} elseif ($status_filter == 'inactive') {
    $where_clause .= " AND tl.active = 0";
}

// Исправленный запрос: tl.* берём полностью, ставки через подзапросы
$query = "
    SELECT
        tl.*,
        p.name AS player_name,
        p.id AS player_id,
        (SELECT COUNT(*) FROM transfer_bids tb WHERE tb.listing_id = tl.id) AS total_bids,
        (SELECT MAX(tb.bid_time) FROM transfer_bids tb WHERE tb.listing_id = tl.id) AS last_bid_time
    FROM transfer_listings tl
    JOIN players p ON tl.player_id = p.id
    WHERE $where_clause
    ORDER BY tl.created_at DESC
";

$res = mysql_query($query, $db);
if (!$res) {
    die("Ошибка запроса: " . mysql_error());
}

function format_price($value) {
    $value = floatval($value);
    return ($value == intval($value)) ? intval($value).' млн' : round($value,1).' млн';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Мои лоты</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body { font-family:'Segoe UI',Arial,sans-serif; margin:0; padding:0; background:linear-gradient(to bottom right,#e0f7ff,#f0eaff); }
.container { max-width:1200px; margin:30px auto; padding:25px; border-radius:20px; background:rgba(255,255,255,0.25); box-shadow:0 8px 32px rgba(31,38,135,0.37); backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,0.18); }
h1 { text-align:center; color:#222; margin-bottom:20px; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
thead th { background: rgba(255,255,255,0.35); padding:12px; text-align:center; border-bottom:2px solid rgba(0,0,0,0.1); }
tbody tr { background: rgba(255,255,255,0.4); transition: transform 0.2s, background 0.2s; }
tbody tr:hover { transform: translateY(-2px); background: rgba(255,255,255,0.55); }
tbody td { padding:10px; border-bottom:1px solid rgba(0,0,0,0.1); text-align:center; vertical-align:middle; }
a { color:#0066cc; text-decoration:none; }
a:hover { text-decoration:underline; }
.btn { padding:4px 8px; margin:2px; border-radius:6px; text-decoration:none; color:#222; font-weight:bold; cursor:pointer; transition:0.2s; display:inline-block; }
.btn-edit { background:rgba(255,255,255,0.25); box-shadow:0 4px 12px rgba(0,0,0,0.2); }
.btn-remove { background:rgba(255,0,0,0.2); color:#900; }
.btn-reactivate { background:rgba(0,255,0,0.2); color:#060; border:1px solid #090; }
.btn:hover { transform:translateY(-2px); }
.player-photo { width:35px; height:35px; border-radius:50%; object-fit:cover; vertical-align:middle; margin-right:8px; }
.filter { margin-bottom:15px; text-align:center; }
.filter select { padding:5px 8px; border-radius:6px; border:none; background:rgba(255,255,255,0.6); }
</style>
</head>
<body>
<div class="container">
    <h1>Мои лоты</h1>

    <div class="filter">
        <form method="get">
            Фильтр: 
            <select name="filter" onchange="this.form.submit()">
                <option value="active" <?php if ($status_filter=='active') echo 'selected'; ?>>Активные</option>
                <option value="inactive" <?php if ($status_filter=='inactive') echo 'selected'; ?>>Завершённые</option>
            </select>
        </form>
    </div>

    <?php if (mysql_num_rows($res) == 0): ?>
        <div class="no-bids">Лоты не найдены.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Фото</th>
                    <th>Игрок</th>
                    <th>Стартовая цена</th>
                    <th>Шаг ставки</th>
                    <th>Текущая ставка</th>
                    <th>Было ставок</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = mysql_fetch_assoc($res)):
                $photo_path = "img/players/".$row['player_id'].".png";
                if(!file_exists($photo_path)) $photo_path = "img/players/default.png";

                $listing_id = $row['id'];
                $player_name = htmlspecialchars($row['player_name']);
                $player_link = "lot.php?id=$listing_id";

                $start_price = format_price($row['start_price']);
                $bid_step = format_price($row['bid_step']);
                $current_bid = $row['current_bid'] ? format_price($row['current_bid']) : '-';
                $created_at = date('d.m.Y H:i', strtotime($row['created_at']));
                $total_bids = intval($row['total_bids']);
                $active = $row['active'];

                $can_remove = ($total_bids==0);
            ?>
                <tr>
                    <td><img src="<?php echo $photo_path; ?>" class="player-photo" alt="Фото"></td>
                    <td><a href="<?php echo $player_link; ?>" target="_blank"><?php echo $player_name; ?></a></td>
                    <td><?php echo $start_price; ?></td>
                    <td><?php echo format_price($row['bid_step'] / 1000000); ?></td>
                    <td>
<?php 
echo ($row['current_bid'] !== null && $row['current_bid'] > 0) 
    ? format_price($row['current_bid'] / 1000000) 
    : 'нет'; 
?>
</td>
                    <td><?php echo $total_bids; ?></td>
                    <td><?php echo $created_at; ?></td>
                    <td>
                        <?php if ($active && $can_remove): ?>
                            <a class="btn btn-remove" href="remove_listing.php?id=<?php echo $listing_id; ?>">Снять/Изменить</a>
                        <?php elseif (!$active): ?>
                            <a class="btn btn-reactivate" href="reactivate_listing.php?id=<?php echo $listing_id; ?>" onclick="return confirm('Вернуть лот на аукцион?');">Вернуть</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
