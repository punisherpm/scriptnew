<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'includes/db_old.php';

if (!isset($_SESSION['user'])) {
    die("Доступ запрещён.");
}

$user_id = intval($_SESSION['user']['id']);

// Получаем ID лота
$listing_id = 0;
if (!empty($_REQUEST['listing_id'])) {
    $listing_id = intval($_REQUEST['listing_id']);
} elseif (!empty($_REQUEST['id'])) {
    $listing_id = intval($_REQUEST['id']);
}

if ($listing_id <= 0) {
    die("Неверный ID лота.");
}

// Получаем активный лот продавца
$res = mysql_query("
    SELECT * 
    FROM transfer_listings 
    WHERE id = $listing_id 
      AND seller_id = $user_id
      AND active = 1
    LIMIT 1
", $db);

if (!$res || mysql_num_rows($res) == 0) {
    die("Лот не найден, неактивен или вы не продавец.");
}

$listing = mysql_fetch_assoc($res);
$player_id = intval($listing['player_id']);

// ---- Снятие лота ----
if (isset($_POST['action']) && $_POST['action'] === 'remove') {

    // Проверка ставок
    $bids_res = mysql_query("
        SELECT COUNT(*) AS cnt 
        FROM transfer_bids 
        WHERE listing_id = $listing_id
    ", $db);

    $bids = mysql_fetch_assoc($bids_res);
    if ($bids['cnt'] > 0) {
        die("Нельзя снять лот — уже есть ставки.");
    }

    // Проверка 6 часов
    $last_update = $listing['last_price_update']
        ? strtotime($listing['last_price_update'])
        : strtotime($listing['created_at']);

    if (time() - $last_update < 6 * 3600) {
        die("Снять игрока можно не ранее чем через 6 часов после выставления или изменения цены.");
    }

    // Снимаем лот
    mysql_query("
        UPDATE transfer_listings SET
            active = 0,
            removed_start_price = {$listing['start_price']},
            removed_bid_step = {$listing['bid_step']},
            removed_at = NOW()
        WHERE id = $listing_id
    ", $db);

    // ❗ ОБЯЗАТЕЛЬНО: сбрасываем флаги игрока
    mysql_query("
        UPDATE players SET
            is_on_transfer = 0,
            on_transfer = NULL
        WHERE id = $player_id
    ", $db);

    echo "<meta charset='UTF-8'>";
    echo "<h1>Лот снят с трансфера</h1>";
    echo '<p><a href="my_listings.php">Вернуться к списку</a></p>';
    exit;
}

// ---- Редактирование цены ----
if (isset($_POST['action']) && $_POST['action'] === 'edit') {

    $start_price_mln = floatval($_POST['start_price']);
    $bid_step_mln = intval($_POST['bid_step']);

    if ($start_price_mln < 1) {
        die("Стартовая цена должна быть не менее 1 млн.");
    }
    if ($bid_step_mln < 1 || $bid_step_mln > 5) {
        die("Шаг ставки должен быть от 1 до 5 млн.");
    }

    // Проверка ставок
    $bids_res = mysql_query("
        SELECT COUNT(*) AS cnt 
        FROM transfer_bids 
        WHERE listing_id = $listing_id
    ", $db);

    $bids = mysql_fetch_assoc($bids_res);
    if ($bids['cnt'] > 0) {
        die("Нельзя изменить цену — уже есть ставки.");
    }

    // Проверка 6 часов
    if ($listing['last_price_update']) {
        if (time() - strtotime($listing['last_price_update']) < 6 * 3600) {
            die("Изменять цену можно не чаще одного раза в 6 часов.");
        }
    }

    $start_price = $start_price_mln * 1000000;
    $bid_step = $bid_step_mln * 1000000;

    mysql_query("
        UPDATE transfer_listings SET
            start_price = $start_price,
            bid_step = $bid_step,
            last_price_update = NOW()
        WHERE id = $listing_id
    ", $db);

    echo "<meta charset='UTF-8'>";
    echo "<h1>Лот обновлён</h1>";
    echo '<p><a href="my_listings.php">Вернуться к списку</a></p>';
    exit;
}
