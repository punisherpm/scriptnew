<?php
header('Content-Type: text/html; charset=UTF-8');

require_once 'includes/auth.php';
require_once 'includes/db_old.php';
require_once 'includes/check_transfer_window.php';

if (!is_logged_in()) {
    die("Доступ запрещён");
}

if (!isset($_POST['player_id']) || !is_numeric($_POST['player_id'])) {
    die("Неверный запрос");
}

$player_id = intval($_POST['player_id']);
$user_id   = intval($_SESSION['user']['id']);
$club_id   = intval($_SESSION['user']['club_id']);

/* Проверяем, что игрок принадлежит клубу */
$player_res = mysql_query("
    SELECT id FROM players 
    WHERE id = $player_id AND club_id = $club_id
", $db);

if (!$player_res || mysql_num_rows($player_res) === 0) {
    die("Игрок не найден или не принадлежит вашему клубу");
}

/* Проверка: нет ли уже активного лота */
$check_active = mysql_query("
    SELECT id FROM transfer_listings 
    WHERE player_id = $player_id AND active = 1
", $db);

if (mysql_num_rows($check_active) > 0) {
    die("Игрок уже выставлен на трансфер");
}

/* Получаем последний снятый лот */
$res = mysql_query("
    SELECT *
    FROM transfer_listings
    WHERE player_id = $player_id
      AND seller_id = $user_id
      AND active = 0
      AND removed_at IS NOT NULL
    ORDER BY removed_at DESC
    LIMIT 1
", $db);

if (!$res || mysql_num_rows($res) === 0) {
    die("Нет снятого трансфера для повторного выставления");
}

$last = mysql_fetch_assoc($res);

/* Проверка 6 часов */
if (strtotime($last['removed_at']) > time() - 6 * 3600) {
    die("Повторно выставить игрока можно только через 6 часов после снятия");
}

/* Получаем активное трансферное окно */
$window_res = mysql_query("
    SELECT id FROM transfer_windows
    WHERE NOW() BETWEEN start_time AND end_time
    LIMIT 1
", $db);

if (!$window_res || mysql_num_rows($window_res) === 0) {
    die("Нет активного трансферного окна");
}

$window = mysql_fetch_assoc($window_res);
$transfer_window_id = intval($window['id']);

/* Восстанавливаем цену и шаг, сохранённые при снятии */
$start_price = intval($last['removed_start_price']);
$bid_step    = intval($last['removed_bid_step']);

$expires_at = date('Y-m-d H:i:s', time() + 86400);

/* Создаём новый лот */
$insert = mysql_query("
    INSERT INTO transfer_listings
    (
        player_id,
        seller_id,
        start_price,
        bid_step,
        created_at,
        expires_at,
        active,
        is_buyout,
        current_bid,
        current_bidder_id,
        transfer_window_id
    ) VALUES (
        $player_id,
        $user_id,
        $start_price,
        $bid_step,
        NOW(),
        '$expires_at',
        1,
        0,
        0,
        NULL,
        $transfer_window_id
    )
", $db);

if (!$insert) {
    die("Ошибка повторного выставления: " . mysql_error());
}

/* Обновляем статус игрока */
mysql_query("
    UPDATE players SET
        is_on_transfer = 1,
        on_transfer = 1
    WHERE id = $player_id
", $db);

header("Location: player.php?id=$player_id");
exit;
?>
