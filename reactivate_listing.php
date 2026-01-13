<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'includes/db_old.php';

if (!isset($_SESSION['user'])) {
    die("Доступ запрещён.");
}

$user_id = intval($_SESSION['user']['id']);
$listing_id = 0;

if (!empty($_REQUEST['id'])) {
    $listing_id = intval($_REQUEST['id']);
} elseif (!empty($_REQUEST['listing_id'])) {
    $listing_id = intval($_REQUEST['listing_id']);
}

if ($listing_id === 0) {
    die("Неверный ID лота.");
}

// Получаем лот и проверяем владельца
$query = "SELECT * FROM transfer_listings WHERE id = $listing_id AND seller_id = $user_id";
$res = mysql_query($query, $db);
if (!$res) {
    die("Ошибка запроса: " . mysql_error());
}

if (mysql_num_rows($res) == 0) {
    die("Лот не найден или вы не являетесь продавцом.");
}

$listing = mysql_fetch_assoc($res);

if ($listing['active'] == 1) {
    die("Лот уже активен.");
}

// Проверяем, есть ли данные для восстановления цены и шага ставки
if ($listing['removed_start_price'] === null || $listing['removed_bid_step'] === null) {
    die("Отсутствуют данные для восстановления цены или шага ставки.");
}

// Восстанавливаем лот
$start_price_sql = floatval($listing['removed_start_price']);
$bid_step_sql = intval($listing['removed_bid_step']);
$now_sql = date('Y-m-d H:i:s');

$update_query = "
    UPDATE transfer_listings SET
        active = 1,
        start_price = $start_price_sql,
        bid_step = $bid_step_sql,
        removed_at = NULL,
        last_price_update = '$now_sql'
    WHERE id = $listing_id
";

$update_res = mysql_query($update_query, $db);
if (!$update_res) {
    die("Ошибка обновления лота: " . mysql_error());
}

header("Location: my_listings.php");
exit;
