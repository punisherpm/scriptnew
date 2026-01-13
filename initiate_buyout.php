<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Europe/Moscow');

if (session_id() == '') {
    session_start();
}

require_once 'includes/db_old.php';
require_once 'includes/check_transfer_window.php';

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    die("Пользователь не найден.");
}

$user_id = intval($_SESSION['user']['id']);
$player_id = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;
$bid_step_million = isset($_POST['bid_step_million']) ? intval($_POST['bid_step_million']) : 0;
$now = date('Y-m-d H:i:s');

if ($bid_step_million < 1 || $bid_step_million > 5) {
    die("Шаг ставки должен быть от 1 до 5 млн.");
}

// Проверка активного ТО
$to_query = "SELECT * FROM transfer_windows WHERE NOW() BETWEEN start_time AND end_time LIMIT 1";
$to_result = mysql_query($to_query, $db);
if (!$to_result || mysql_num_rows($to_result) == 0) {
    die("Трансферное окно не активно или не найдено.");
}
$transfer_window = mysql_fetch_assoc($to_result);
$transfer_window_id = intval($transfer_window['id']);
$window_start = strtotime($transfer_window['start_time']);
$buyout_deadline = $window_start + 48 * 3600;
if (time() > $buyout_deadline) {
    die("Срок выкупов (48 часов с начала ТО) истек.");
}

// Получаем игрока
$player_result = mysql_query("SELECT * FROM players WHERE id = $player_id", $db);
if (!$player_result || mysql_num_rows($player_result) == 0) {
    die("Игрок не найден.");
}
$player = mysql_fetch_assoc($player_result);
$salary = floatval($player['salary']);
$club_id = intval($player['club_id']);

// Проверка клуба пользователя
$user_club_res = mysql_query("SELECT club_id FROM users WHERE id = $user_id LIMIT 1", $db);
if (!$user_club_res || mysql_num_rows($user_club_res) == 0) {
    die("Пользователь не найден.");
}
$user_club_id = intval(mysql_result($user_club_res, 0));
if ($user_club_id == $club_id) {
    die("Вы не можете выкупить собственного игрока.");
}

// Получаем seller_id
$seller_res = mysql_query("SELECT id, club_id FROM users WHERE club_id = $club_id LIMIT 1", $db);
if (!$seller_res || mysql_num_rows($seller_res) == 0) {
    die("Не удалось определить владельца игрока.");
}
$seller = mysql_fetch_assoc($seller_res);
$seller_id = intval($seller['id']);
$seller_club_id = intval($seller['club_id']);

// Проверка лимита выкупов
$count_query = "
    SELECT COUNT(*) FROM transfer_listings 
    WHERE seller_id = $seller_id 
    AND is_buyout = 1 
    AND created_at BETWEEN '{$transfer_window['start_time']}' AND '{$transfer_window['end_time']}'
";
$count_result = mysql_query($count_query, $db);
$buyout_count = intval(mysql_result($count_result, 0));
if ($buyout_count >= 3) {
    die("Нельзя выкупить более 3 игроков из одного клуба за трансферное окно.");
}

// Параметры лота
$buyout_price = round($salary * 15, 2);
$bid_step = $bid_step_million * 1000000;
$expires_at = date('Y-m-d H:i:s', time() + 86400);

// Добавление лота
$query = sprintf(
    "INSERT INTO transfer_listings
    (player_id, seller_id, start_price, created_at, active, is_buyout, buyout_initiator_id, bid_step, expires_at, current_bid, current_bidder_id, transfer_window_id)
    VALUES (%d, %d, %f, '%s', 1, 1, %d, %d, '%s', %f, %d, %d)",
    $player_id,
    $seller_id,
    $buyout_price,
    $now,
    $user_id,
    $bid_step,
    $expires_at,
    $buyout_price,
    $user_id,
    $transfer_window_id
);
$result = mysql_query($query, $db);

echo "<meta charset=\"UTF-8\">";
if ($result) {
    $listing_id = mysql_insert_id($db);

    // Добавляем первую ставку
    mysql_query(sprintf(
        "INSERT INTO transfer_bids 
        (listing_id, bidder_id, bid_amount, bid_time, is_protection) 
        VALUES (%d, %d, %f, '%s', 0)",
        $listing_id,
        $user_id,
        $buyout_price,
        $now
    ), $db);

    // Бюджеты до
    $before_buyer_res = mysql_query("SELECT budget FROM clubs WHERE id = $user_club_id", $db);
    $before_seller_res = mysql_query("SELECT budget FROM clubs WHERE id = $seller_club_id", $db);
    $buyer_before = floatval(mysql_result($before_buyer_res, 0));
    $seller_before = floatval(mysql_result($before_seller_res, 0));

    echo "<p>💰 Бюджет покупателя ДО: $buyer_before</p>";
    echo "<p>💰 Бюджет продавца ДО: $seller_before</p>";

    // Обновление бюджета покупателя (можно в минус)
    $update_buyer_query = sprintf(
        "UPDATE clubs SET budget = budget - %f WHERE id = %d",
        $buyout_price,
        $user_club_id
    );
    $update_buyer_result = mysql_query($update_buyer_query, $db);
    if (!$update_buyer_result) {
        die("❌ Ошибка при списании с бюджета покупателя: " . mysql_error($db));
    }

    // Обновление бюджета продавца
    $update_seller_query = sprintf(
        "UPDATE clubs SET budget = budget + %f WHERE id = %d",
        $buyout_price,
        $seller_club_id
    );
    $update_seller_result = mysql_query($update_seller_query, $db);
    if (!$update_seller_result) {
        die("❌ Ошибка при зачислении на бюджет продавца: " . mysql_error($db));
    }

    // Бюджеты после
    $after_buyer_res = mysql_query("SELECT budget FROM clubs WHERE id = $user_club_id", $db);
    $after_seller_res = mysql_query("SELECT budget FROM clubs WHERE id = $seller_club_id", $db);
    $buyer_after = floatval(mysql_result($after_buyer_res, 0));
    $seller_after = floatval(mysql_result($after_seller_res, 0));

    echo "<p>💸 Бюджет покупателя ПОСЛЕ: $buyer_after</p>";
    echo "<p>💸 Бюджет продавца ПОСЛЕ: $seller_after</p>";

    echo "<p>✅ Выкуп успешно инициирован. Игрок выставлен на аукцион.</p>";
    echo '<p><a href="player.php?id=' . $player_id . '">Назад к игроку</a></p>';
} else {
    echo "<p>❌ Ошибка при выкупе: " . mysql_error($db) . "</p>";
}
?>
