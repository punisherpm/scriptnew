<?php
require_once 'includes/auth.php';
require_once 'includes/db_old.php';
require_once 'includes/check_transfer_window.php';


if (!isset($_POST['listing_id']) || !is_numeric($_POST['listing_id'])) {
    die("Неверный запрос");
}

$listing_id = intval($_POST['listing_id']);
$user_id = $_SESSION['user']['id'];

// Проверка, что лот существует, активен и принадлежит текущему пользователю
$res = mysql_query("
    SELECT * FROM transfer_listings
    WHERE id = $listing_id AND seller_id = $user_id AND active = 1
", $db);

if (!$res || mysql_num_rows($res) === 0) {
    die("Лот не найден или вы не являетесь его владельцем");
}

$lot = mysql_fetch_assoc($res);

// Проверка: прошло ли 6 часов с момента выставления или последнего изменения
$created_at_ts = strtotime($lot['created_at']);
$can_withdraw_time = $created_at_ts + 6 * 3600;

if (time() < $can_withdraw_time) {
    $minutes_left = round(($can_withdraw_time - time()) / 60);
    die("Снять игрока с трансфера можно только через 6 часов после выставления или изменения. Осталось $minutes_left мин.");
}

// Проверка: нет ли ставок
if ($lot['current_bid'] > 0) {
    die("Нельзя снять игрока с трансфера — уже есть ставки.");
}

// Снятие лота
mysql_query("
    UPDATE transfer_listings
    SET active = 0,
        removed_at = NOW(),
        original_start_price = {$lot['start_price']},
        original_bid_step = {$lot['bid_step']}
    WHERE id = $listing_id
", $db);

header("Location: player.php?id={$lot['player_id']}");
exit;
?>
