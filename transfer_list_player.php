<?php
header('Content-Type: text/html; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';
require_once 'includes/db_old.php';
require_once 'includes/check_transfer_window.php';

if (!is_logged_in()) {
    die("Доступ запрещён");
}

$user_id = intval($_SESSION['user']['id']);
$club_id = intval($_SESSION['user']['club_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Неверный метод запроса.");
}

$player_id = intval($_POST['player_id']);

// --- Стартовая цена (млн → абсолют)
$start_price_million = floatval($_POST['start_price']);
$start_price = intval($start_price_million * 1000000);

// --- Шаг ставки (млн → абсолют)
$bid_step_million = intval($_POST['bid_step_million']);
if ($bid_step_million < 1 || $bid_step_million > 5) {
    die("Шаг ставки должен быть от 1 до 5 млн.");
}
$bid_step = $bid_step_million * 1000000;

// --- Проверка игрока и принадлежности клубу
$res = mysql_query("
    SELECT *
    FROM players
    WHERE id = $player_id
      AND club_id = $club_id
      AND is_on_transfer = 0
    LIMIT 1
", $db);

if (!$res || mysql_num_rows($res) === 0) {
    die("Игрок не найден, не принадлежит вашему клубу или уже участвует в трансфере.");
}

$player = mysql_fetch_assoc($res);
$max_price = $player['salary'] * 15;

// --- Проверка стартовой цены
if ($start_price > $max_price || $start_price < 1000000) {
    die("Стартовая цена превышает лимит или некорректна.");
}

// --- Проверка активного трансферного окна
$window_res = mysql_query("
    SELECT id
    FROM transfer_windows
    WHERE NOW() BETWEEN start_time AND end_time
    LIMIT 1
", $db);

if (!$window_res || mysql_num_rows($window_res) === 0) {
    die("Нет активного трансферного окна.");
}

$transfer_window = mysql_fetch_assoc($window_res);
$transfer_window_id = intval($transfer_window['id']);

// --- Проверка на дублирующий лот
$check = mysql_query("
    SELECT id
    FROM transfer_listings
    WHERE player_id = $player_id
      AND active = 1
    LIMIT 1
", $db);

if (mysql_num_rows($check) > 0) {
    die("Этот игрок уже выставлен на трансфер.");
}

$now = date('Y-m-d H:i:s');
$expires_at = date('Y-m-d H:i:s', time() + 86400); // 24 часа

// --- Создание лота
$query = "
    INSERT INTO transfer_listings (
        player_id,
        seller_id,
        start_price,
        created_at,
        active,
        is_buyout,
        bid_step,
        expires_at,
        current_bid,
        current_bidder_id,
        transfer_window_id
    ) VALUES (
        $player_id,
        $user_id,
        $start_price,
        '$now',
        1,
        0,
        $bid_step,
        '$expires_at',
        0,
        NULL,
        $transfer_window_id
    )
";

$result = mysql_query($query, $db);

echo "<meta charset=\"UTF-8\">";

if ($result) {

    // 🔥 КЛЮЧЕВОЙ ФИКС: обновляем состояние игрока
    mysql_query("
        UPDATE players
        SET
            is_on_transfer = 1,
            on_transfer = 'auction'
        WHERE id = $player_id
        LIMIT 1
    ", $db);

    echo "<p><strong>Игрок успешно выставлен на трансфер!</strong></p>";
    echo '<p><a href="player.php?id=' . $player_id . '">Назад к игроку</a></p>';
    echo '<p><a href="transfer_market.php">Перейти на трансферный рынок</a></p>';

} else {
    echo "<p>Ошибка при добавлении в трансферный список:</p>";
    echo "<pre>" . mysql_error($db) . "</pre>";
}
