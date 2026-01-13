<?php
session_start();
require_once 'includes/db_old.php';

if (!isset($_SESSION['user'])) {
    die("Доступ запрещён. Пожалуйста, войдите в систему.");
}

$user = $_SESSION['user'];
$user_id = intval($user['id']);

// Определяем ID текущего трансферного окна
$tw_query = "SELECT id 
             FROM transfer_windows 
             WHERE NOW() BETWEEN start_time AND end_time 
             LIMIT 1";
$tw_res = mysql_query($tw_query, $db);
if (!$tw_res) {
    die('Ошибка определения трансферного окна: ' . mysql_error());
}
$current_tw = mysql_fetch_assoc($tw_res);
if (!$current_tw) {
    die("Текущее трансферное окно не найдено.");
}
$current_tw_id = intval($current_tw['id']);

// Запрашиваем только ставки из текущего ТО
$query = "
    SELECT
        tl.*,
        p.name AS player_name,
        c.name AS seller_club_name,
        cb.name AS current_bidder_club_name,
        tb.bid_amount AS your_bid,
        tb.bid_time AS bid_time
    FROM transfer_listings tl
    JOIN players p ON tl.player_id = p.id
    LEFT JOIN users u ON tl.seller_id = u.id
    LEFT JOIN clubs c ON u.club_id = c.id
    LEFT JOIN clubs cb ON tl.current_bidder_id = cb.id
    LEFT JOIN (
        SELECT b1.listing_id, b1.bid_amount, b1.bid_time
        FROM transfer_bids b1
        INNER JOIN (
            SELECT listing_id, MAX(bid_time) AS max_time
            FROM transfer_bids
            WHERE bidder_id = $user_id
            GROUP BY listing_id
        ) b2 ON b1.listing_id = b2.listing_id AND b1.bid_time = b2.max_time
        WHERE b1.bidder_id = $user_id
    ) tb ON tb.listing_id = tl.id
    WHERE 
        (tl.current_bidder_id = $user_id OR tb.listing_id IS NOT NULL)
        AND tl.transfer_window_id = $current_tw_id
    ORDER BY tl.created_at DESC
";

$result = mysql_query($query, $db);
if (!$result) {
    die('Ошибка запроса: ' . mysql_error());
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Мои ставки</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    margin: 0;
    padding: 0;
    background: linear-gradient(to bottom right, #e0f7ff, #f0eaff);
}
.container {
    max-width: 1200px;
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
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
thead th {
    background: rgba(255,255,255,0.35);
    padding: 12px;
    text-align: left;
    border-bottom: 2px solid rgba(0,0,0,0.1);
}
tbody tr {
    background: rgba(255,255,255,0.4);
    transition: transform 0.2s, background 0.2s;
}
tbody tr:hover {
    transform: translateY(-2px);
    background: rgba(255,255,255,0.55);
}
tbody td {
    padding: 10px;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}
a {
    color: #0066cc;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
.status-active {
    color: green;
    font-weight: bold;
}
.status-ended {
    color: red;
    font-weight: bold;
}
.no-bids {
    margin-top: 20px;
    font-size: 16px;
    text-align: center;
    color: #333;
}
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
    <a href="transfer_market.php" class="btn-back"><i class="fas fa-arrow-left"></i> Назад на трансферный рынок</a>
    <h1>Мои ставки</h1>

    <?php if (mysql_num_rows($result) == 0): ?>
        <div class="no-bids">Вы ещё не делали ставок в текущем трансферном окне.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Игрок</th>
                    <th>Клуб</th>
                    <th>Ваша ставка</th>
                    <th>Текущая ставка</th>
                    <th>Стартовая цена</th>
                    <th>Сделана</th>
                    <th>Завершение</th>
                    <th>Статус лота</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = mysql_fetch_assoc($result)): ?>
                <tr>
                    <td><a href="lot.php?id=<?php echo intval($row['id']); ?>" target="_blank"><?php echo htmlspecialchars($row['player_name']); ?></a></td>
                    <td><?php echo htmlspecialchars($row['seller_club_name']); ?></td>
                    <td>
                        <?php echo $row['your_bid'] !== null ? number_format($row['your_bid'],0,',',' ') . ' млн' : '-'; ?>
                    </td>
                    <td>
                        <?php echo $row['current_bid'] !== null ? number_format($row['current_bid'],0,',',' ') . ' млн' : '-'; ?>
                    </td>
                    <td><?php echo number_format($row['start_price'],0,',',' ') . ' млн'; ?></td>
                    <td><?php echo $row['bid_time'] ? date('d.m.Y H:i', strtotime($row['bid_time'])) : '-'; ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($row['expires_at'])); ?></td>
                    <td>
                        <?php if ($row['active']): ?>
                            <span class="status-active">Активен</span>
                        <?php else: ?>
                            <span class="status-ended">Завершён</span>
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
