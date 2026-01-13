<?php
header('Content-Type: text/html; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'includes/auth.php';
require_once 'includes/db_old.php';
require_once 'includes/check_transfer_window.php';

// Получаем текущее трансферное окно
$window = null;
$window_id = null;
$no_window_msg = null;

$res_window = mysql_query("SELECT * FROM transfer_windows WHERE NOW() BETWEEN start_time AND end_time LIMIT 1", $db);
if ($res_window && mysql_num_rows($res_window) > 0) {
    $window = mysql_fetch_assoc($res_window);
    $window_id = intval($window['id']);
}

function format_price($value) {
    $value = floatval($value);
    $mln = $value / 1000000;

    // Если меньше 1 млн — всё равно показываем с точкой, например 0.5 млн
    if ($mln < 1) {
        return round($mln, 1) . ' млн';
    }

    // Если ровно число — без лишних нулей
    if (round($mln, 1) == intval($mln)) {
        return intval($mln) . ' млн';
    }

    return round($mln, 1) . ' млн';
}

// Загружаем активные лоты
if ($window_id) {
    $listings_res = mysql_query("
        SELECT tl.*, p.name AS player_name, p.position, p.salary, c.name AS club_name
        FROM transfer_listings tl
        JOIN players p ON tl.player_id = p.id
        JOIN users u ON tl.seller_id = u.id
        JOIN clubs c ON u.club_id = c.id
        WHERE tl.active = 1 AND tl.transfer_window_id = $window_id
        ORDER BY tl.expires_at ASC
    ", $db);
} else {
    $listings_res = false;
    $no_window_msg = "Сейчас трансферное окно не активно. Действия с игроками недоступны.";
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Трансферный рынок</title>
<style>
body {
    font-family: 'Segoe UI', Tahoma, sans-serif;
    background: linear-gradient(135deg, rgba(240,248,255,1), rgba(230,240,255,0.9));
    margin: 0;
    padding: 20px;
    color: #333;
}
.glass-container {
    background: rgba(255, 255, 255, 0.65);
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.2);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 20px;
    margin: auto;
    max-width: 1100px;
}
h1, h2 { text-align: center; color: #1f2a48; margin-bottom: 10px; }
p { text-align: center; margin-top: 0; }
table { border-collapse: collapse; width: 100%; background: rgba(255, 255, 255, 0.8); border-radius: 12px; overflow: hidden; }
th, td { padding: 12px; text-align: center; border-bottom: 1px solid rgba(0,0,0,0.1); }
th { background: rgba(255,255,255,0.4); font-weight: bold; }
tr:hover { background: rgba(173, 216, 230, 0.3); }
.highlight { background-color: rgba(200, 250, 204, 0.6) !important; }
a { color: #1f5faa; text-decoration: none; }
a:hover { text-decoration: underline; }
.btn { display: inline-block; padding: 10px 16px; margin: 10px 5px 0; background: rgba(255, 255, 255, 0.4); border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.5); box-shadow: 0 4px 6px rgba(0,0,0,0.1); backdrop-filter: blur(6px); color: #1f2a48; font-weight: bold; text-decoration: none; transition: 0.3s; }
.btn:hover { background: rgba(255,255,255,0.6); transform: translateY(-2px); }
.countdown.inactive { color: #777; font-style: italic; }
</style>
</head>
<body>

<div class="glass-container">
<h1>Трансферный рынок</h1>
<?php if ($window): ?>
    <div style="text-align:center; margin-bottom:20px;">
        <a href="draft.php" class="btn">Сделать драфт свободного агента</a>
    </div>
<?php endif; ?>
<?php if ($window): ?>
    <h2><?php echo htmlspecialchars($window['name']); ?></h2>
    <p><strong>Период проведения:</strong>
        <?php 
            date_default_timezone_set('Europe/Moscow');
            echo date('d.m.Y H:i', strtotime($window['start_time'])) . ' — ' . date('d.m.Y H:i', strtotime($window['end_time'])); 
        ?> (МСК)
    </p>
<?php endif; ?>

<?php if ($no_window_msg): ?>
    <p><strong><?php echo htmlspecialchars($no_window_msg); ?></strong></p>
<?php endif; ?>

<?php if ($listings_res && mysql_num_rows($listings_res) > 0): ?>
    <table>
        <tr>
            <th>Игрок</th>
            <th>Позиция</th>
            <th>Клуб</th>
            <th>Зарплата</th>
            <th>Стартовая цена</th>
            <th>Текущая ставка</th>
            <th>До завершения</th>
            <th>Вид трансфера</th>
        </tr>

        <?php while ($row = mysql_fetch_assoc($listings_res)): ?>
            <?php
                $listing_id = $row['id'];
                $expires_at_ts = strtotime($row['expires_at']);

                $current_bid = '';
                $highlight = '';

                // Проверяем, есть ли ставки
                $bids_check = mysql_query("SELECT COUNT(*) AS cnt FROM transfer_bids WHERE listing_id = " . intval($listing_id), $db);
                $bids = mysql_fetch_assoc($bids_check);
                $has_bids = ($bids['cnt'] > 0);

                if ($has_bids && $row['current_bid'] > 0) {
                    $bidder_res = mysql_query("
                        SELECT u.username, c.name AS club_name
                        FROM users u
                        JOIN clubs c ON u.club_id = c.id
                        WHERE u.id = " . intval($row['current_bidder_id']) . "
                        LIMIT 1
                    ", $db);

                    if ($bidder_res && mysql_num_rows($bidder_res) > 0) {
                        $bidder = mysql_fetch_assoc($bidder_res);
                        if (isset($_SESSION['user']['id']) && $row['current_bidder_id'] == $_SESSION['user']['id']) {
                            $highlight = 'highlight';
                        }
                        $current_bid = format_price($row['current_bid'], false) . " (" . htmlspecialchars($bidder['club_name']) . ")";
                    } else {
                        $current_bid = format_price($row['current_bid'], false);
                    }
                } else {
                    $current_bid = "ставок нет";
                }
            ?>
            <tr>
                <td><a href="lot.php?id=<?php echo $listing_id; ?>"><?php echo htmlspecialchars($row['player_name']); ?></a></td>
                <td><?php echo htmlspecialchars($row['position']); ?></td>
                <td><?php echo htmlspecialchars($row['club_name']); ?></td>
                <td><?php echo format_price($row['salary'], false); ?></td>
                <td><?php echo format_price($row['start_price'], true); ?></td>
                <td class="<?php echo $highlight; ?>"><?php echo $current_bid; ?></td>

                <td class="countdown<?php echo !$has_bids ? ' inactive' : ''; ?>" 
                    <?php if ($has_bids) echo 'data-expires="' . $expires_at_ts . '"'; ?>>
                    <?php
                        if (!$has_bids) {
                            echo date('d.m.Y H:i', strtotime($window['end_time']));
                        }
                    ?>
                </td>

                <td><?php echo ($row['is_buyout'] ? 'Выкуп' : 'Аукцион'); ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
<?php endif; ?>

<div style="text-align:center;">
    <a href="index.php" class="btn">← На главную</a>
    <a href="transfer_market.php" class="btn">↻ Обновить рынок</a>
</div>
</div>

<script>
function updateCountdowns() {
    var elements = document.querySelectorAll('.countdown[data-expires]');
    var now = Math.floor(Date.now() / 1000);

    elements.forEach(function(el) {
        var expires = parseInt(el.getAttribute('data-expires'));
        var diff = expires - now;

        if (diff < 0) {
            el.textContent = '00:00:00';
        } else {
            var hours = Math.floor(diff / 3600);
            var minutes = Math.floor((diff % 3600) / 60);
            var seconds = diff % 60;
            el.textContent = 
                String(hours).padStart(2, '0') + ':' + 
                String(minutes).padStart(2, '0') + ':' + 
                String(seconds).padStart(2, '0');
        }
    });
}

updateCountdowns();
setInterval(updateCountdowns, 1000);
</script>
<!-- DRAFT MODAL -->
<div id="draftModal" 
     style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
            background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:9999;">
    
    <div style="background:white; width:420px; margin:120px auto; padding:20px; 
                border-radius:12px; text-align:center; box-shadow:0 6px 22px rgba(0,0,0,0.2);">

        <h2>Создание драфта</h2>
        <p>Вставьте ссылку на игрока из SoFIFA:</p>

        <form method="GET" action="draft_preview.php">
            <input type="text" name="url" placeholder="https://sofifa.com/player/..." 
                   style="width:90%; padding:10px; border-radius:8px; border:1px solid #bbb;"
                   required>

            <div style="margin-top:15px;">
                <button type="submit"
                        style="padding:10px 18px; border-radius:8px; border:0; 
                               background:#1f5faa; color:white; cursor:pointer;">
                    Загрузить данные игрока
                </button>

                <button type="button" onclick="closeDraftModal()"
                        style="padding:10px 18px; border-radius:8px; border:0; margin-left:10px;
                               background:#888; color:white; cursor:pointer;">
                    Отмена
                </button>
            </div>
        </form>

    </div>
</div>
<script>
</script>
</body>
</html>
