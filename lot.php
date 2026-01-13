<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');
require_once 'includes/auth.php';
require_once 'includes/db_old.php';
require_once 'includes/check_transfer_window.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Неверный ID лота");
}

$listing_id = intval($_GET['id']);
$user_id = $_SESSION['user']['id'];

function format_price($value) {
    $value = floatval($value);
    return round($value / 1000000, 1) . ' млн';
}

// Получение информации о лоте
$listing_res = mysql_query("
    SELECT tl.id, tl.player_id, tl.seller_id, tl.start_price, tl.current_bid, tl.bid_step, tl.expires_at, tl.active,
           p.name AS player_name, p.age, p.salary, p.position,
           c.id AS seller_club_id, c.name AS seller_club,
           u.username AS seller_name
    FROM transfer_listings tl
    JOIN players p ON tl.player_id = p.id
    JOIN users u ON tl.seller_id = u.id
    JOIN clubs c ON u.club_id = c.id
    WHERE tl.id = $listing_id
", $db);

if (!$listing_res || mysql_num_rows($listing_res) === 0) {
    die("Лот не найден");
}

$listing = mysql_fetch_assoc($listing_res);

// Получение истории ставок
$bids_res = mysql_query("
    SELECT b.bid_amount, b.bid_time, u.username AS bidder_username, u.id AS bidder_id, 
           c.name AS bidder_club, c.id AS bidder_club_id
    FROM transfer_bids b
    JOIN users u ON b.bidder_id = u.id
    JOIN clubs c ON u.club_id = c.id
    WHERE b.listing_id = $listing_id
    ORDER BY b.bid_time DESC
", $db);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Лот: <?php echo htmlspecialchars($listing['player_name']); ?></title>
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
h1 { font-size: 1.4em; text-align: center; color: #222; }
h2 { font-size: 1.0em; text-align: center; color: #222; margin-top: 20px; }
.player-info div { margin: 8px 0; }
.player-info strong { color: #444; }
ul { list-style: none; padding-left: 0; }
ul li {
    background: rgba(255,255,255,0.4);
    margin: 6px 0;
    padding: 10px 14px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
form { margin-top: 15px; }
input[type="number"], button { padding: 6px 10px; border-radius: 6px; border: 1px solid rgba(0,0,0,0.2); }
button { background: rgba(0,102,204,0.7); color: #fff; cursor: pointer; font-weight: bold; }
button:hover { background: rgba(0,102,204,0.9); }
a { color: #0066cc; text-decoration: none; }
a:hover { text-decoration: underline; }
.notice { color: gray; text-align:center; margin-top:10px; }
</style>
</head>
<body>
<div class="container">
<h1><a href="player.php?id=<?php echo $listing['player_id']; ?>"><?php echo htmlspecialchars($listing['player_name']); ?></a></h1>

<div class="player-info">
    <div><strong>Возраст:</strong> <?php echo intval($listing['age']); ?></div>
    <div><strong>Позиция:</strong> <?php echo htmlspecialchars($listing['position']); ?></div>
    <div><strong>Зарплата:</strong> <?php echo format_price($listing['salary']); ?></div>
    <div><strong>Продавец:</strong> 
        <a href="club.php?club_id=<?php echo $listing['seller_club_id']; ?>"><?php echo htmlspecialchars($listing['seller_club']); ?></a> 
        (<a href="user.php?id=<?php echo $listing['seller_id']; ?>"><?php echo htmlspecialchars($listing['seller_name']); ?></a>)
    </div>
    <div><strong>Стартовая цена:</strong> <?php echo format_price($listing['start_price']); ?></div>
    <div><strong>Текущая ставка:</strong>
        <?php
        if (mysql_num_rows($bids_res) > 0) {
            $last_bid = mysql_fetch_assoc($bids_res);
            echo format_price($listing['current_bid']) . " — <a href='club.php?club_id={$last_bid['bidder_club_id']}'>" 
                 . htmlspecialchars($last_bid['bidder_club']) . "</a> (<a href='user.php?id={$last_bid['bidder_id']}'>" 
                 . htmlspecialchars($last_bid['bidder_username']) . "</a>)";
        } else {
            echo "Ставок нет";
        }
        ?>
    </div>
    <div><strong>Шаг ставки:</strong> <?php echo format_price($listing['bid_step']); ?></div>
    <div><strong>Лот истекает:</strong> <?php echo date('d.m.Y H:i', strtotime($listing['expires_at'])); ?></div>
</div>

<?php
// Кнопка "Снять с трансфера"
$can_withdraw = false;
if ($listing['seller_id'] == $user_id && $listing['active'] == 1 && $listing['current_bid'] == 0) {
    $can_withdraw = true;
}
if ($can_withdraw): ?>
    <form action="withdraw_transfer.php" method="post">
        <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">
        <button type="submit">❌ Снять с трансфера</button>
    </form>
<?php endif; ?>

<?php if ($listing['active']): ?>
<h2>Сделать ставку</h2>
<form method="post" action="transfer_bid.php">
    <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">
    <label>Ваша ставка (в миллионах):</label><br>
    <input type="number" name="bid_million" min="1" step="1" required>
    <button type="submit">Сделать ставку</button>
</form>
<?php else: ?>
<p style="color:red; text-align:center;">Лот завершён</p>
<?php endif; ?>

<h2>История ставок</h2>
<?php if (mysql_num_rows($bids_res) > 0): ?>
    <ul>
        <?php
        mysql_data_seek($bids_res, 0);
        while ($row = mysql_fetch_assoc($bids_res)): ?>
            <li>
                <a href="user.php?id=<?php echo $row['bidder_id']; ?>"><?php echo htmlspecialchars($row['bidder_username']); ?></a> 
                (<a href="club.php?club_id=<?php echo $row['bidder_club_id']; ?>"><?php echo htmlspecialchars($row['bidder_club']); ?></a>):
                <?php echo format_price($row['bid_amount']); ?> — 
                <?php echo date('d.m.Y H:i', strtotime($row['bid_time'])); ?>
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>Пока нет ставок.</p>
<?php endif; ?>

<br>
<a href='transfer_market.php' style='display:inline-block; padding:8px 14px; background:#eee; border:1px solid #ccc; text-decoration:none; border-radius:4px;'>← Назад на трансферный рынок</a>
</div>
</body>
</html>
