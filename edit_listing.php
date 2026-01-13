<?php
session_start();
require_once 'includes/db_old.php';

if (!isset($_SESSION['user'])) {
    die("Доступ запрещён.");
}

$user_id = intval($_SESSION['user']['id']);
$listing_id = intval($_POST['listing_id']);
$new_price = floatval($_POST['start_price']);
$new_step = floatval($_POST['bid_step']);

$res = mysql_query("SELECT * FROM transfer_listings WHERE id = $listing_id AND seller_id = $user_id", $db);
if (!$res || mysql_num_rows($res) == 0) {
    die("Лот не найден.");
}

$row = mysql_fetch_assoc($res);

$created = strtotime($row['created_at']);
$now = time();

if ($created + 6 * 3600 > $now) {
    die("Изменение доступно только спустя 6 часов после выставления.");
}

$bid_count_res = mysql_query("SELECT COUNT(*) as cnt FROM transfer_bids WHERE listing_id = $listing_id", $db);
$bid_data = mysql_fetch_assoc($bid_count_res);
if ($bid_data['cnt'] > 0) {
    die("Невозможно изменить цену — уже есть ставки.");
}

mysql_query("UPDATE transfer_listings SET start_price = $new_price, bid_step = $new_step WHERE id = $listing_id", $db);
header("Location: my_listings.php");
exit;
