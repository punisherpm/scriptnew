<?php
require_once '../includes/auth.php';
require_once '../includes/db_old.php';
require_once 'includes/check_transfer_window.php';


if (!isset($_SESSION['user'])) {
    die("Доступ запрещён");
}

$user_id = intval($_SESSION['user']['id']);

if (!isset($_POST['player_id']) || !isset($_POST['buyout_price']) || !isset($_POST['bid_step'])) {
    die("Неверные данные.");
}

$player_id = intval($_POST['player_id']);
$buyout_price = intval($_POST['buyout_price']);
$bid_step_mln = intval($_POST['bid_step']);

// Проверка шага ставки
if ($bid_step_mln < 1 || $bid_step_mln > 5) {
    die("Шаг ставки должен быть от 1 до 5 млн.");
}

$bid_step = $bid_step_mln * 1000000;
$current_time = time();
$expires_at = $current_time + 24 * 3600; // 24 часа

// Получаем клуб тренера
$club_result = mysql_query("SELECT id FROM clubs WHERE manager_id = $user_id LIMIT 1");
if (!$club_result || mysql_num_rows($club_result) == 0) {
    die("Ваш клуб не найден.");
}
$buyer_club_id = mysql_result($club_result, 0);

// Получаем клуб игрока
$player = mysql_fetch_assoc(mysql_query("SELECT club_id FROM players WHERE id = $player_id"));
if (!$player) {
    die("Игрок не найден.");
}
$seller_club_id = intval($player['club_id']);

// Проверка — игрок не должен быть уже на трансфере
$check = mysql_query("SELECT id FROM transfer_listings WHERE player_id = $player_id AND active = 1");
if (mysql_num_rows($check) > 0) {
    die("Игрок уже выставлен на трансфер.");
}

// Создание лота с выкупом
$query = "INSERT INTO transfer_listings (
    player_id, seller_id, start_price, created_at, active, is_buyout,
    buyout_initiator_id, bid_step, expires_at, current_bid, current_bidder_id
) VALUES (
    $player_id, $seller_club_id, $buyout_price, NOW(), 1, 1,
    $user_id, $bid_step, FROM_UNIXTIME($expires_at), $buyout_price, $buyer_club_id
)";
$result = mysql_query($query);

if ($result) {
    echo "Выкуп успешно оформлен. Не забудьте уведомить второго тренера в течение 2 часов.";
    echo "<br><a href='../player.php?id=$player_id'>← Назад к игроку</a>";
} else {
    echo "Ошибка при оформлении выкупа: " . mysql_error();
}
?>
