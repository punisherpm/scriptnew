<?php
// player.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');
session_start();

require_once 'includes/auth.php';
require_once 'includes/db_old.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Неверный ID игрока");
}

$player_id = intval($_GET['id']);
$club_id = intval($_SESSION['user']['club_id']);

// Получаем данные игрока
$res = mysql_query("SELECT * FROM players WHERE id=$player_id", $db);
if (!$res) die("Ошибка запроса: ".mysql_error($db));
$player = mysql_fetch_assoc($res);
if (!$player) die("Игрок не найден");

// Проверяем: игрок уже на аукционе?
$lot_res = mysql_query("SELECT * FROM transfer_listings WHERE player_id=$player_id AND active=1 LIMIT 1", $db);
$player_on_auction = ($lot_res && mysql_num_rows($lot_res) > 0);
$lot_data = $player_on_auction ? mysql_fetch_assoc($lot_res) : null;

// Проверяем трансферное окно
$window_res = mysql_query("SELECT * FROM transfer_windows WHERE NOW() BETWEEN start_time AND end_time LIMIT 1", $db);
$window = ($window_res && mysql_num_rows($window_res)>0) ? mysql_fetch_assoc($window_res) : null;
$window_id = $window ? $window['id'] : 0;

// Вычисляем сумму выкупа
$buyout_price = ceil($player['salary'] * 15);

// Проверка возможности выкупа
$can_buyout = false;
$buyout_reason = '';
if ($player['club_id'] != $club_id) {
    if ($player_on_auction) {
        $buyout_reason = 'Игрок уже выставлен на аукцион другим клубом.';
    } else {
        $can_buyout = $window_id>0;
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($player['name']); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body { font-family: Arial, sans-serif; background:#f5f5f5; color:#333; margin:0; padding:20px;}
.player-container { display:flex; gap:30px; flex-wrap:wrap; max-width:900px; margin:0 auto;}
.player-left { flex:0 0 300px; display:flex; flex-direction:column; gap:15px; }
.player-photo, .club-logo { height:140px; width:auto; object-fit:cover; border-radius:8px; }
.liquid-glass { background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3); border-radius: 12px; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);}
.player-info h2 { font-size:20px; margin:0 0 10px 0; }
.player-info p { font-size:14px; margin:4px 0; }
.abilities-inline-list { display:flex; flex-wrap:wrap; gap:6px; list-style:none; padding:0; margin:0; }
.abilities-inline-list li { background:#ffd700; padding:4px 8px; border-radius:5px; font-weight:600; color:black; display:flex; align-items:center; font-size:13px; }
.abilities-inline-list li span { margin-right:4px; display:inline-flex; align-items:center; }
.skills-list { list-style:none; padding:0; margin:0; }
.skills-list li { display:flex; justify-content:space-between; padding:8px 12px; background:#828282; border-radius:6px; margin-bottom:6px; font-size:14px; color:white; }
.skill-label { font-weight:600; }
.skill-red { color:red; }
.skill-orange { color:orange; }
.skill-yellow { color:yellow; }
.skill-green { color:lightgreen; }
.player-right { flex:1; min-width:300px; }
.back-button button, .transfer-listing-box button, .buyout-button button { padding:8px 15px; font-size:14px; cursor:pointer; margin-top:10px; border-radius:4px; border:1px solid #ccc; background:#eee; }
.transfer-listing-box, .buyout-box { margin-top:20px; }
.buyout-button button { background:#28a745; color:#fff; border:none; }
.buyout-button button:hover { background:#218838; }
</style>
</head>
<body>

<?php include 'player_block.php'; ?>

<div class="back-button">
    <form action="dashboard.php" method="get">
        <button type="submit">← Назад к составу</button>
    </form>
</div>

<!-- Блок выкупа -->
<div class="buyout-box liquid-glass">
    <?php if ($can_buyout): ?>
        <form action="initiate_buyout.php" method="post">
            <input type="hidden" name="player_id" value="<?php echo $player_id; ?>">
            <div style="margin-bottom: 8px;">
                <strong>Сумма выкупа:</strong> <?php echo number_format($buyout_price/1000000,1,',',' '); ?> млн €
            </div>
            <div style="margin-bottom: 8px;">
                <label>Шаг ставки (1–5 млн): 
                    <input type="number" name="bid_step_million" value="1" min="1" max="5" step="1" required>
                </label>
            </div>
            <div class="buyout-button">
                <button type="submit">Выкупить игрока</button>
            </div>
        </form>
    <?php else: ?>
        <p style="color: gray; font-style: italic;"><?php echo htmlspecialchars($buyout_reason ? $buyout_reason : 'Выкуп невозможен — трансферное окно закрыто.'); ?></p>
    <?php endif; ?>
</div>

<!-- Блок аукциона (если владелец) -->
<?php if ($player['club_id'] == $club_id): ?>
<div class="transfer-listing-box liquid-glass">
    <?php if (!$player_on_auction): ?>
        <h3>Выставить на аукцион</h3>
        <form method="post" action="transfer_list_player.php">
            <input type="hidden" name="player_id" value="<?php echo $player_id; ?>">
            <label>Стартовая цена (млн): 
                <input type="number" name="start_price" value="<?php echo ceil($player['salary']*15/1000000); ?>" step="1" min="1" required>
            </label><br>
            <label>Шаг ставки (1–5 млн): 
                <input type="number" name="bid_step_million" value="1" step="1" min="1" max="5" required>
            </label><br>
            <button type="submit">Выставить игрока</button>
        </form>
    <?php else: ?>
        <p>Игрок уже выставлен на аукцион.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

</body>
</html>
