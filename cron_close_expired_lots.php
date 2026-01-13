<?php
header('Content-Type: text/html; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db_old.php';

// 1. Получаем активное трансферное окно
$res_window = mysql_query("
    SELECT * 
    FROM transfer_windows 
    WHERE NOW() BETWEEN start_time AND end_time 
    LIMIT 1
", $db);

if (!$res_window || mysql_num_rows($res_window) == 0) {
    die("❌ Нет активного трансферного окна.\n");
}

$window = mysql_fetch_assoc($res_window);
$window_id = intval($window['id']);

echo "Текущее ТО: id={$window_id}\n\n";

// 2. Получаем все истёкшие активные лоты текущего ТО
$res_listings = mysql_query("
    SELECT 
        tl.*,
        p.id AS player_id,
        p.name AS player_name,
        p.club_id AS old_club_id,
        p.salary,
        u.id AS seller_user_id,
        u.club_id AS seller_club_id
    FROM transfer_listings tl
    JOIN players p ON tl.player_id = p.id
    JOIN users u ON tl.seller_id = u.id
    WHERE 
        tl.active = 1
        AND tl.expires_at <= NOW()
        AND tl.transfer_window_id = $window_id
", $db);

if (!$res_listings || mysql_num_rows($res_listings) == 0) {
    die("✅ Нет лотов для закрытия.\n");
}

$closed = 0;

// 3. Обрабатываем каждый лот
while ($lot = mysql_fetch_assoc($res_listings)) {

    $lot_id        = intval($lot['id']);
    $player_id     = intval($lot['player_id']);
    $player_name   = mysql_real_escape_string($lot['player_name']);
    $seller_club   = intval($lot['seller_club_id']);
    $seller_user   = intval($lot['seller_user_id']);
    $buyer_user    = intval($lot['current_bidder_id']);
    $price         = intval($lot['current_bid']);
    $old_club_id   = intval($lot['old_club_id']);

    // 3.1 Лот без ставок
    if ($buyer_user == 0 || $price == 0) {

        mysql_query("
            UPDATE transfer_listings 
            SET active = 0 
            WHERE id = $lot_id
        ", $db);

        mysql_query("
            UPDATE players 
            SET is_on_transfer = 0, on_transfer = NULL
            WHERE id = $player_id
        ", $db);

        echo "⏹ Лот #{$lot_id} — {$player_name}: ставок нет, лот снят.\n";
        $closed++;
        continue;
    }

    // 3.2 Получаем покупателя
    $res_buyer = mysql_query("
        SELECT id, username, club_id 
        FROM users 
        WHERE id = $buyer_user 
        LIMIT 1
    ", $db);

    if (!$res_buyer || mysql_num_rows($res_buyer) == 0) {
        echo "❌ Лот #{$lot_id}: покупатель не найден.\n";
        continue;
    }

    $buyer = mysql_fetch_assoc($res_buyer);
    $buyer_club = intval($buyer['club_id']);

    // 3.3 Обновляем бюджеты
    $res_seller_club = mysql_query("SELECT budget FROM clubs WHERE id = $seller_club LIMIT 1", $db);
    $res_buyer_club  = mysql_query("SELECT budget FROM clubs WHERE id = $buyer_club LIMIT 1", $db);

    if (!$res_seller_club || !$res_buyer_club) {
        echo "❌ Лот #{$lot_id}: ошибка получения бюджетов.\n";
        continue;
    }

    $seller_budget = mysql_fetch_assoc($res_seller_club);
    $buyer_budget  = mysql_fetch_assoc($res_buyer_club);

    $new_seller_budget = $seller_budget['budget'] + $price;
    $new_buyer_budget  = $buyer_budget['budget'] - $price;

    mysql_query("UPDATE clubs SET budget = $new_seller_budget WHERE id = $seller_club", $db);
    mysql_query("UPDATE clubs SET budget = $new_buyer_budget WHERE id = $buyer_club", $db);

    // 3.4 Переводим игрока
    $update_player = mysql_query("
        UPDATE players 
        SET 
            club_id = $buyer_club,
            salary = 0,
            needs_salary_update = 1,
            is_on_transfer = 0,
            on_transfer = NULL
        WHERE id = $player_id
    ", $db);

    if (!$update_player) {
        echo "❌ Лот #{$lot_id}: ошибка перевода игрока ({$player_name}).\n";
        continue;
    }

    // 3.5 Закрываем лот
    mysql_query("
        UPDATE transfer_listings 
        SET active = 0
        WHERE id = $lot_id
    ", $db);

    echo "✅ Лот #{$lot_id} — {$player_name} перешёл из клуба #{$old_club_id} в #{$buyer_club} за "
        . number_format($price, 0, ',', ' ') . "\n";

    $closed++;
}

echo "\n✅ Завершено лотов текущего ТО: {$closed}\n";
?>
