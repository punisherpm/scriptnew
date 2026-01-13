<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once 'includes/auth.php';
require_once 'includes/db_old.php';

// проверка логина
if (!is_logged_in() || empty($_SESSION['user']['id'])) {
    echo json_encode(array(
        'success' => false,
        'error'   => 'not_logged_in'
    ));
    exit;
}

$user    = $_SESSION['user'];
$user_id = (int)$user['id'];
$club_id = isset($user['club_id']) ? (int)$user['club_id'] : 0;

// читаем JSON
$raw  = file_get_contents('php://input');
// file_put_contents('/tmp/draft_raw.log', date('c')."\n".$raw."\n---\n", FILE_APPEND);

$data = json_decode($raw, true);

if ($raw === '' || $data === null || !is_array($data)) {
    echo json_encode(array('success' => false, 'error' => 'bad_json'));
    exit;
}

// клуб по умолчанию — Free Agent (id = 999)
if (!isset($data['club_id']) || !$data['club_id']) {
    $data['club_id'] = 999;
}

// убрать звёздочку из лучшей позиции
if (isset($data['position'])) {
    $data['position'] = str_replace('*', '', (string)$data['position']);
}

// флаги способностей по умолчанию 0
$boolFields = array('reaction', 'side', 'penalties');
foreach ($boolFields as $bf) {
    if (!isset($data[$bf]) || $data[$bf] === null || $data[$bf] === '') {
        $data[$bf] = 0;
    }
}

// игрок на трансфере по драфту
$data['is_on_transfer'] = 1;
$data['on_transfer']    = 'draft';

// поля таблицы players
$fields = array(
    'name','position','positions_all',
    'salary','club_id','is_on_transfer','on_transfer',
    'age','nationality','height','weight','foot_side',
    'weak_foot_acc','weak_foot_freq','consistency','condition',
    'injury','needs_salary_update',

    'attack','defence','balance','stamina','speed','acceleration',
    'response','agility','dribble_accuracy','dribble_speed',
    'short_pass_accuracy','short_pass_speed','long_pass_accuracy',
    'long_pass_speed','shot_accuracy','shot_power','shot_technique',
    'free_kick','curling','header','jump','technique','aggression',
    'mentality','gk_ability','teamwork',

    'dribbling','tactical_dribble','positioning','reaction',
    'playmaking','passing','scoring','one_on_one','post_player',
    'lines','middle_shooting','side','centre','penalties',
    'one_touch_pass','outside','marking','sliding','covering',
    'd_line_control','penalty_stopper','one_on_one_stopper',
    'long_throw'
);

// шаг аукциона
$step = isset($data['step']) ? (int)$data['step'] : 0;

// стартовая цена драфта из playerValue
$draftStartPrice = isset($data['playerValue']) ? (float)$data['playerValue'] : 0.0;

// INSERT в players
$cols   = array();
$values = array();

foreach ($fields as $f) {
    if (array_key_exists($f, $data)) {
        $cols[] = '`'.$f.'`';

        if ($data[$f] === null || $data[$f] === '') {
            $values[] = 'NULL';
        } else {
            $values[] = "'" . mysql_real_escape_string((string)$data[$f]) . "'";
        }
    }
}

if (!$cols) {
    echo json_encode(array('success' => false, 'error' => 'no_fields'));
    exit;
}

$sql = 'INSERT INTO `players` ('.implode(',', $cols).') VALUES ('.implode(',', $values).')';

$res = mysql_query($sql);
if (!$res) {
    echo json_encode(array(
        'success' => false,
        'error'   => 'insert_failed: ' . mysql_error()
    ));
    exit;
}

$playerId = (int)mysql_insert_id();

// продавец — системный пользователь Free Agent (users.id = 999)
$freeAgentUserId = 999;
$sellerId        = $freeAgentUserId;

// текущий ставящий — реальный тренер
$currentBidderId = $user_id;

// защита от нулевого шага/цены
$bidStep = $step > 0 ? $step : 1;
if ($draftStartPrice <= 0) {
    $draftStartPrice = $bidStep;
}

$startPriceSql = mysql_real_escape_string(number_format($draftStartPrice, 2, '.', ''));
$bidStepSql    = (int)$bidStep;

// ищем активное трансферное окно
$transfer_window_id = 'NULL';
$window_res = mysql_query("
    SELECT id
    FROM transfer_windows
    WHERE NOW() BETWEEN start_time AND end_time
    LIMIT 1
", $db);

if ($window_res && mysql_num_rows($window_res) > 0) {
    $tw = mysql_fetch_assoc($window_res);
    $transfer_window_id = (int)$tw['id'];
}

$now = date('Y-m-d H:i:s');

// INSERT в transfer_listings
$sqlListing = "
    INSERT INTO `transfer_listings`
    (`player_id`, `seller_id`, `start_price`, `created_at`, `active`, `is_buyout`,
     `bid_step`, `current_bid`, `current_bidder_id`, `transfer_type`,
     `original_start_price`, `original_bid_step`, `transfer_window_id`)
    VALUES
    (
        {$playerId},
        {$sellerId},
        '{$startPriceSql}',
        '{$now}',
        1,
        0,
        {$bidStepSql},
        {$startPriceSql},
        {$currentBidderId},
        'draft',
        {$startPriceSql},
        {$bidStepSql},
        {$transfer_window_id}
    )
";

$resListing = mysql_query($sqlListing);
if (!$resListing) {
    echo json_encode(array(
        'success' => false,
        'error'   => 'listing_insert_failed: ' . mysql_error()
    ));
    exit;
}

$listingId = (int)mysql_insert_id();

// первая ставка в transfer_bids по стартовой цене
$sqlBid = "
    INSERT INTO `transfer_bids`
    (`listing_id`, `bidder_id`, `bid_amount`, `is_protection`)
    VALUES
    (
        {$listingId},
        {$currentBidderId},
        '{$startPriceSql}',
        0
    )
";

$resBid = mysql_query($sqlBid);
if (!$resBid) {
    echo json_encode(array(
        'success' => false,
        'error'   => 'bid_insert_failed: ' . mysql_error()
    ));
    exit;
}

// ответ
echo json_encode(array(
    'success'           => true,
    'player_id'         => $playerId,
    'listing_id'        => $listingId,
    'step'              => $step,
    'draft_start_price' => $draftStartPrice
));
exit;
