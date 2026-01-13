<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
header('Content-Type: text/html; charset=utf-8');
require_once 'includes/db_old.php';

$data = json_decode(file_get_contents('php://input'), true);
if(!$data){
    die("Нет данных для импорта");
}

function clean($str){
    global $db;
    return mysql_real_escape_string(trim($str), $db);
}

function calc_start_price($value){
    return max(500000, round(($value/2)/500000)*500000);
}

function map_specials($specials_array){
    $map = array(
        'Tactical Dribble' => 'tactical_dribble',
        'Passing' => 'playmaking',
        'Middle Shooting' => 'middle_shooting',
        '1-Touch Pass' => 'one_touch_pass',
        'Outside' => 'outside',
        'Dribbling' => 'dribbling',
        'Side' => 'side'
    );
    $result = array();
    foreach($map as $key => $field) $result[$field] = 0;
    foreach($specials_array as $s){
        if(isset($map[$s])) $result[$map[$s]] = 1;
    }
    return $result;
}

// Основные поля
$name = clean(isset($data['commonName'])?$data['commonName']:$data['firstName'].' '.$data['lastName']);
$age = intval($data['age']);
$nationality = clean($data['country']);
$foot = ($data['foot']==1?'L':'R');

// Позиции
$pos_arr = array();
foreach(array('position1','position2','position3','position4') as $p){
    if(isset($data[$p]) && $data[$p]>0) $pos_arr[] = $data[$p];
}
$positions_all = implode(',', $pos_arr);
$position = isset($pos_arr[0])?$pos_arr[0]:'';

// Стартовая цена
$start_price = calc_start_price(isset($data['price'])?$data['price']:1000000);

// Конвертация скиллов
$stats_fields = array(
    'attack'=>'sho','defence'=>'def','balance'=>'balance','stamina'=>'stamina',
    'speed'=>'sprintSpeed','acceleration'=>'acceleration','response'=>'reactions','agility'=>'agility',
    'dribble_accuracy'=>'dribbling','dribble_speed'=>'dribbling','short_pass_accuracy'=>'shortPassing','short_pass_speed'=>'shortPassing',
    'long_pass_accuracy'=>'longPassing','long_pass_speed'=>'longPassing','shot_accuracy'=>'finishing','shot_power'=>'shotPower','shot_technique'=>'curve',
    'free_kick'=>'freeKick','curling'=>'curve','header'=>'heading','jump'=>'jumping','technique'=>'ballControl','aggression'=>'aggression','mentality'=>'composure',
    'gk_ability'=>'gkDiving','teamwork'=>'vision','consistency'=>'growth','condition'=>'growth','weak_foot_acc'=>'weakFoot','weak_foot_freq'=>'weakFoot'
);
$player = array();
foreach($stats_fields as $k=>$v){
    $player[$k] = isset($data[$v])?intval($data[$v]):0;
}

// Спецспособности
$specials = map_specials(isset($data['specialities'])?$data['specialities']:array());

// SQL вставка
$sql = sprintf(
    "INSERT INTO players 
    (name, position, positions_all, age, nationality, foot_side, `side`,
     attack, defence, balance, stamina, speed, acceleration, response, agility,
     dribble_accuracy, dribble_speed, short_pass_accuracy, short_pass_speed,
     long_pass_accuracy, long_pass_speed, shot_accuracy, shot_power, shot_technique,
     free_kick, curling, header, jump, technique, aggression, mentality,
     gk_ability, teamwork, consistency, `condition`, weak_foot_acc, weak_foot_freq,
     tactical_dribble, playmaking, middle_shooting, one_touch_pass, outside, dribbling,
     club_id, salary)
     VALUES
     ('%s','%s','%s',%d,'%s','%s','%s',
     %d,%d,%d,%d,%d,%d,%d,%d,
     %d,%d,%d,%d,
     %d,%d,%d,%d,%d,
     %d,%d,%d,%d,%d,%d,%d,
     %d,%d,%d,%d,%d,%d,
     %d,%d,%d,%d,%d,%d,
     0,%d)",
     clean($name), clean($position), clean($positions_all), $age, clean($nationality), clean($foot), 'B',
     $player['attack'],$player['defence'],$player['balance'],$player['stamina'],$player['speed'],$player['acceleration'],$player['response'],$player['agility'],
     $player['dribble_accuracy'],$player['dribble_speed'],$player['short_pass_accuracy'],$player['short_pass_speed'],
     $player['long_pass_accuracy'],$player['long_pass_speed'],$player['shot_accuracy'],$player['shot_power'],$player['shot_technique'],
     $player['free_kick'],$player['curling'],$player['header'],$player['jump'],$player['technique'],$player['aggression'],$player['mentality'],
     $player['gk_ability'],$player['teamwork'],$player['consistency'],$player['condition'],$player['weak_foot_acc'],$player['weak_foot_freq'],
     $specials['tactical_dribble'],$specials['playmaking'],$specials['middle_shooting'],$specials['one_touch_pass'],$specials['outside'],$specials['dribbling'],
     intval($start_price)
);

$result = mysql_query($sql,$db);
if($result){
    echo "<span style='color:green;'>Игрок '$name' успешно добавлен как свободный агент с ценой $start_price</span>";
} else {
    echo "<span style='color:red;'>Ошибка SQL: ".mysql_error($db)."</span>";
}
