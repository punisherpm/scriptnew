<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');
require_once 'includes/auth.php';
require_once 'includes/db_old.php';
require_once 'includes/check_transfer_window.php';

if (!is_logged_in()) {
    die("Доступ запрещён.");
}

$bidder_id = $_SESSION['user']['id'];
$club_id = intval($_SESSION['user']['club_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['listing_id']) || !isset($_POST['bid_million'])) {
        die("Некорректные данные. Пожалуйста, заполните форму заново.");
    }

    $listing_id = intval($_POST['listing_id']);
    $bid_million = intval($_POST['bid_million']);

    if ($bid_million < 1) {
        die("Сумма ставки должна быть не менее 1 млн.");
    }

    $bid_amount = $bid_million * 1000000;

    // Получаем лот
    $res = mysql_query("SELECT * FROM transfer_listings WHERE id = $listing_id AND active = 1", $db);
    if (!$res) {
        die("Ошибка при запросе лота: " . mysql_error());
    }
    if (mysql_num_rows($res) == 0) {
        die("Лот не найден или неактивен.");
    }

    $lot = mysql_fetch_assoc($res);

    // Проверка защиты выкупа
    $is_protection = 0;
    $protection_cost = 0;
    if ($lot['seller_id'] == $bidder_id && $lot['is_buyout'] == 1) {
        $is_protection = 1;
        $player_id = intval($lot['player_id']);
        $res_salary = mysql_query("SELECT salary FROM players WHERE id = $player_id", $db);
        if (!$res_salary || mysql_num_rows($res_salary) == 0) {
            die("Не удалось получить зарплату игрока.");
        }
        $salary = intval(mysql_result($res_salary, 0));
        $protection_cost = $bid_amount - ($salary * 13);
        if ($protection_cost < 0) $protection_cost = 0;
    } elseif ($lot['seller_id'] == $bidder_id) {
        die("Нельзя ставить на собственного игрока.");
    }

    // Проверка минимальной ставки
    $current_bid = intval($lot['current_bid']);
    $bid_step = intval($lot['bid_step']);
    $start_price = intval($lot['start_price']);
    $min_required = ($current_bid > 0) ? $current_bid + $bid_step : $start_price;
    if ($bid_amount < $min_required) {
        die("Ваша ставка должна быть не менее " . number_format($min_required, 0, ',', ' ') . " у.е.");
    }

    // Вернуть деньги предыдущему участнику
    if (!empty($lot['current_bidder_id'])) {
        $prev_bidder_id = intval($lot['current_bidder_id']);
        $prev_bid_amount = intval($lot['current_bid']);

        $prev_club_res = mysql_query("SELECT club_id FROM users WHERE id = $prev_bidder_id", $db);
        if ($prev_club_res && mysql_num_rows($prev_club_res) > 0) {
            $prev_club_id = mysql_result($prev_club_res, 0);
            $r = mysql_query("UPDATE clubs SET budget = budget + $prev_bid_amount WHERE id = $prev_club_id", $db);
            if (!$r) die("Ошибка при возврате бюджета предыдущему клубу: " . mysql_error());
        }
    }

    // Списываем деньги у нового участника
    $user_club_id = intval($_SESSION['user']['club_id']);
    $cost_to_deduct = $is_protection ? $protection_cost : $bid_amount;
    $r = mysql_query("UPDATE clubs SET budget = budget - $cost_to_deduct WHERE id = $user_club_id", $db);
    if (!$r) die("Ошибка при списании бюджета у нового клуба: " . mysql_error());

    // Начисляем продавцу
    $seller_id = intval($lot['seller_id']);
    $seller_club_res = mysql_query("SELECT club_id FROM users WHERE id = $seller_id", $db);
    if ($seller_club_res && mysql_num_rows($seller_club_res) > 0) {
        $seller_club_id = mysql_result($seller_club_res, 0);
        $amount_to_add = $is_protection ? $protection_cost : $bid_amount;
        $r = mysql_query("UPDATE clubs SET budget = budget + $amount_to_add WHERE id = $seller_club_id", $db);
        if (!$r) die("Ошибка при начислении бюджета продавцу: " . mysql_error());
    }

    // Обновляем лот
    $now = date('Y-m-d H:i:s');
    $expires_at = date('Y-m-d H:i:s', time() + 86400);
    $r = mysql_query("
        UPDATE transfer_listings
        SET current_bid = $bid_amount,
            current_bidder_id = $bidder_id,
            expires_at = '$expires_at'
        WHERE id = $listing_id
    ", $db);
    if (!$r) die("Ошибка при обновлении лота: " . mysql_error());

    // История ставок
    $q = "INSERT INTO transfer_bids (listing_id, bidder_id, bid_amount, bid_time, is_protection)
          VALUES ($listing_id, $bidder_id, $bid_amount, '$now', $is_protection)";
    $r = mysql_query($q, $db);
    if (!$r) die("Ошибка при сохранении истории ставки: " . mysql_error() . "<br>Запрос: $q");

    echo "<meta charset=\"UTF-8\">";
    echo "<p>✅ Ставка успешно принята!</p>";
    echo '<p><a href="lot.php?id=' . $listing_id . '">Вернуться к лоту</a></p>';

} else {
    echo "<meta charset=\"UTF-8\">";
    echo "Неверный метод запроса.";
}
?>
