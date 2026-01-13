<?php
require_once 'includes/auth.php';
require_once 'includes/db_old.php';

if (!is_logged_in()) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user']['id'];
$club_id = $_SESSION['user']['club_id'];
$now = date('Y-m-d H:i:s');

// Получаем бюджет
$club_query = mysql_query("SELECT budget FROM clubs WHERE id = $club_id", $db);
$club = mysql_fetch_assoc($club_query);

// Получаем список лиг (пока не используется)
$res = mysql_query("SELECT id, name FROM leagues ORDER BY name", $db);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Центр управления клубом</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(to bottom right, #e0f7ff, #f0eaff);
    margin: 0; padding: 0;
}
.container {
    max-width: 960px;
    margin: 30px auto;
    padding: 25px;
    border-radius: 20px;
    background: rgba(255,255,255,0.25);
    box-shadow: 0 8px 32px rgba(31,38,135,0.37);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.18);
}
h2, h3 { text-align: center; color: #222; }
h2 { margin-bottom: 30px; }
h3 { margin-top: 30px; margin-bottom: 15px; }
.budget { text-align: center; font-size: 1.3em; font-weight: bold; margin-bottom: 20px; }
ul { list-style: none; padding-left: 0; }
ul li {
    background: rgba(255,255,255,0.4);
    margin: 8px 0;
    padding: 12px 15px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}
ul li:hover { transform: translateY(-2px); }
ul li i { margin-right: 10px; color: #444; }
.menu {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 30px;
}
.menu a {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 15px;
    background: rgba(255,255,255,0.25);
    border-radius: 15px;
    text-decoration: none;
    font-weight: bold;
    color: #222;
    box-shadow: 0 8px 32px rgba(31,38,135,0.37);
    backdrop-filter: blur(8px);
    transition: transform 0.2s, background 0.2s;
}
.menu a i { margin-right: 8px; }
.menu a:hover { background: rgba(255,255,255,0.4); transform: translateY(-3px); }
.search { text-align: center; margin-top: 30px; }
.search input[type="text"] { padding: 6px; width: 220px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.2); }
.search input[type="submit"] {
    padding: 6px 12px;
    border-radius: 8px;
    border: none;
    background: rgba(0,102,204,0.7);
    color: #fff;
    font-weight: bold;
    cursor: pointer;
}
.search input[type="submit"]:hover { background: rgba(0,102,204,0.9); }
.notifications a,
.events a {
    display: inline;
}
</style>
</head>
<body>
<div class="container">
    <h2>⚽ Центр управления клубом</h2>
    <div class="budget">💰 Ваш бюджет: <?php echo number_format($club['budget']/1000000,2); ?> млн</div>

    <h3>🔔 Уведомления</h3>
    <ul class="notifications">
    <?php
    $found = false;

    $bids = mysql_query("
        SELECT t.id AS listing_id, p.name AS player_name, t.current_bid
        FROM transfer_listings t
        JOIN players p ON p.id = t.player_id
        WHERE t.current_bidder_id = $user_id AND t.active = 1
        ORDER BY t.expires_at ASC
    ", $db);
    while ($b = mysql_fetch_assoc($bids)) {
        $found = true;
        echo "<li><i class='fas fa-gavel'></i> Вы участвуете в ставке на&nbsp;<a href='lot.php?id={$b['listing_id']}'>"
             . htmlspecialchars($b['player_name']) . "</a>&nbsp;(ставка: "
             . number_format($b['current_bid']/1000000,2) . " млн)</li>";
    }

    $incoming_bids = mysql_query("
        SELECT t.id AS listing_id, p.name AS player_name, b.bid_amount
        FROM transfer_bids b
        JOIN transfer_listings t ON b.listing_id = t.id
        JOIN players p ON p.id = t.player_id
        WHERE t.seller_id = $user_id AND t.active = 1
        ORDER BY b.bid_time DESC
        LIMIT 5
    ", $db);
    while ($b = mysql_fetch_assoc($incoming_bids)) {
        $found = true;
        echo "<li><i class='fas fa-hand-holding-dollar'></i> На вашего игрока&nbsp;<a href='lot.php?id={$b['listing_id']}'>"
             . htmlspecialchars($b['player_name']) . "</a>&nbsp;сделана ставка: "
             . number_format($b['bid_amount']/1000000,2) . " млн</li>";
    }

    $sold = mysql_query("
        SELECT t.id AS listing_id, p.name, t.current_bid
        FROM transfer_listings t
        JOIN players p ON p.id = t.player_id
        WHERE t.seller_id = $user_id AND t.active = 0 AND t.current_bidder_id IS NOT NULL
        ORDER BY t.expires_at DESC
        LIMIT 5
    ", $db);
    while ($s = mysql_fetch_assoc($sold)) {
        $found = true;
        echo "<li><i class='fas fa-check-circle'></i> Продан &nbsp;<a href='lot.php?id={$s['listing_id']}'>"
             . htmlspecialchars($s['name']) . "</a>&nbsp;за "
             . number_format($s['current_bid']/1000000,2) . " млн</li>";
    }
$purchased = mysql_query("
    SELECT t.id AS listing_id, p.name, t.current_bid
    FROM transfer_listings t
    JOIN players p ON p.id = t.player_id
    WHERE t.current_bidder_id = $user_id AND t.active = 0
    ORDER BY t.expires_at DESC
    LIMIT 5
", $db);

while ($p = mysql_fetch_assoc($purchased)) {
    $found = true;
    echo "<li><i class='fas fa-cart-arrow-down'></i> Куплен &nbsp;<a href='lot.php?id={$p['listing_id']}'>"
         . htmlspecialchars($p['name']) . "</a>&nbsp;за "
         . number_format($p['current_bid']/1000000,2) . " млн</li>";
}

    if (!$found) {
        echo "<li>Нет новых уведомлений.</li>";
    }
    ?>
    </ul>

    <h3>🕘 Последние события</h3>
    <ul class="events">
    <?php
    $events = mysql_query("
        SELECT 'listing' AS type, t.created_at AS event_time, p.name AS player_name, t.id AS listing_id, NULL AS amount
        FROM transfer_listings t
        JOIN players p ON p.id = t.player_id
        WHERE t.active = 1
        UNION ALL
        SELECT 'bid' AS type, b.bid_time AS event_time, p.name AS player_name, t.id AS listing_id, b.bid_amount AS amount
        FROM transfer_bids b
        JOIN transfer_listings t ON b.listing_id = t.id
        JOIN players p ON p.id = t.player_id
        UNION ALL
        SELECT 'sold' AS type, t.expires_at AS event_time, p.name AS player_name, t.id AS listing_id, t.current_bid AS amount
        FROM transfer_listings t
        JOIN players p ON p.id = t.player_id
        WHERE t.active = 0 AND t.current_bidder_id IS NOT NULL
        ORDER BY event_time DESC
        LIMIT 5
    ", $db) or die(mysql_error());

    if ($events && mysql_num_rows($events) > 0) {
        while ($e = mysql_fetch_assoc($events)) {
            $time = date('d.m H:i', strtotime($e['event_time']));
            $name = "<a href='lot.php?id=" . intval($e['listing_id']) . "'>" . htmlspecialchars($e['player_name']) . "</a>";

            if ($e['type'] == 'listing') {
                echo "<li><i class='fas fa-thumbtack'></i> [$time] &nbsp;" . $name . "&nbsp;выставлен на рынок</li>";
            } elseif ($e['type'] == 'bid') {
                echo "<li><i class='fas fa-hand-holding-dollar'></i> [$time] Сделана ставка на&nbsp;" . $name . "&nbsp;: "
                     . number_format($e['amount']/1000000,2) . " млн</li>";
            } elseif ($e['type'] == 'sold') {
                echo "<li><i class='fas fa-upload'></i> [$time] &nbsp;" . $name . "&nbsp;продан за "
                     . number_format($e['amount']/1000000,2) . " млн</li>";
            }
        }
    } else {
        echo "<li>Нет событий</li>";
    }
    ?>
    </ul>

    <div class="menu">
    <a href="transfer_market.php"><i class="fas fa-exchange-alt"></i>Трансферный рынок</a>
    <a href="my_listings.php"><i class="fas fa-tags"></i>Мои лоты</a>
    <a href="my_bids.php"><i class="fas fa-gavel"></i>Мои ставки</a>
    <a href="dashboard.php"><i class="fas fa-coins"></i>Управление клубом</a>
    <a href="transfer_history.php"><i class="fas fa-history"></i>История сделок</a>
    <a href="leagues.php"><i class="fas fa-users"></i>Другие клубы</a>
    <a href="rules.php"><i class="fas fa-book"></i>Правила ТО</a>

    <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == 'admin'): ?>
        <a href="admin/index.php" style="background: rgba(255,0,0,0.2); color: darkred; font-weight: bold;">
            <i class="fas fa-user-shield"></i>Админка
        </a>
    <?php endif; ?>
</div>


    <div class="search">
        <form method="get" action="home.php">
            <label>🔍 Поиск игрока по фамилии: </label>
            <input type="text" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <input type="submit" value="Найти">
        </form>
    </div>
</div>
</body>
</html>
