<?php
// player_block.php
// Ожидается, что $player, $club_id, $db уже определены

// Фото игрока
$photo_path = file_exists("img/players/{$player['id']}.png")
    ? "img/players/{$player['id']}.png"
    : "img/players/default.png";

// Клуб игрока
$club_res = mysql_query("SELECT id, name FROM clubs WHERE id=" . intval($player['club_id']), $db);
$player_club = ($club_res && mysql_num_rows($club_res) > 0) ? mysql_fetch_assoc($club_res) : null;

// Логотип клуба
$club_logo_path = ($player_club && file_exists("uploads/logos/{$player_club['id']}.png"))
    ? "uploads/logos/{$player_club['id']}.png"
    : "uploads/logos/default.png";

// Позиции
function parse_positions_all($raw) {
    $parts = explode(',', $raw);
    $positions = array();
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p != '') $positions[] = $p;
    }
    return $positions;
}

$main_position = $player['position'];
$additional_positions = array();
if (!empty($player['positions_all'])) {
    $all_positions = parse_positions_all($player['positions_all']);
    $additional_positions = array_diff($all_positions, array($main_position));
}

function position_class($position) {
    $colors = array(
        'GK'=>'color:orange;','CWP'=>'color:blue;','CBT'=>'color:blue;','SB'=>'color:blue;',
        'DMF'=>'color:green;','WB'=>'color:green;','CMF'=>'color:green;','SMF'=>'color:green;',
        'AMF'=>'color:green;','WF'=>'color:red;','SS'=>'color:red;','CF'=>'color:red;'
    );
    return isset($colors[$position]) ? $colors[$position] : 'color:black;';
}

// Скиллы и способности
$skills = array(
    'attack'=>'Attack','defence'=>'Defence','balance'=>'Balance','stamina'=>'Stamina',
    'speed'=>'Speed','acceleration'=>'Acceleration','response'=>'Response','agility'=>'Agility',
    'dribble_accuracy'=>'Dribble Accuracy','dribble_speed'=>'Dribble Speed',
    'short_pass_accuracy'=>'Short Pass Accuracy','short_pass_speed'=>'Short Pass Speed',
    'long_pass_accuracy'=>'Long Pass Accuracy','long_pass_speed'=>'Long Pass Speed',
    'shot_accuracy'=>'Shot Accuracy','shot_power'=>'Shot Power','shot_technique'=>'Shot Technique',
    'free_kick'=>'Free Kick Accuracy','curling'=>'Curling','header'=>'Header','jump'=>'Jump',
    'technique'=>'Technique','aggression'=>'Aggression','mentality'=>'Mentality',
    'gk_ability'=>'GK Ability','teamwork'=>'Teamwork'
);

$abilities = array(
    'dribbling'=>'Dribbling','tactical_dribble'=>'Tactical Dribble','positioning'=>'Positioning',
    'reaction'=>'Reaction','playmaking'=>'Playmaking','passing'=>'Passing','scoring'=>'Scoring',
    'one_on_one'=>'1-on-1 Scoring','post_player'=>'Post Player','lines'=>'Lines',
    'middle_shooting'=>'Middle Shooting','side'=>'Side','centre'=>'Centre',
    'penalties'=>'Penalties','one_touch_pass'=>'One-touch Pass','outside'=>'Outside',
    'marking'=>'Marking','sliding'=>'Sliding','covering'=>'Covering',
    'd_line_control'=>'D-Line Control','penalty_stopper'=>'Penalty Stopper',
    'one_on_one_stopper'=>'1-on-1 Stopper','long_throw'=>'Long Throw'
);

function skill_class($value){
    if($value>=95) return 'skill-red';
    if($value>=90) return 'skill-orange';
    if($value>=80) return 'skill-yellow';
    return 'skill-green';
}

// Проверка: на аукционе ли игрок
$player_on_transfer = false;
$lot_res = mysql_query("SELECT * FROM transfer_listings WHERE player_id={$player['id']} AND active=1 LIMIT 1");
if($lot_res && mysql_num_rows($lot_res) > 0){
    $player_on_transfer = true;
    $lot_data = mysql_fetch_assoc($lot_res);
}
?>

<div class="player-container">
    <div class="player-left">
        <div style="display:flex; align-items:center; gap:15px;">
            <img class="player-photo" src="<?php echo htmlspecialchars($photo_path); ?>" alt="Фото игрока">
            <?php if($player_club): ?>
                <img class="club-logo" src="<?php echo htmlspecialchars($club_logo_path); ?>" alt="Логотип клуба">
            <?php endif; ?>
        </div>

        <div class="player-info liquid-glass">
            <h2><?php echo htmlspecialchars($player['name']); ?></h2>
            <?php if($player_club): ?>
                <p><strong>Клуб:</strong> <a href="club.php?club_id=<?php echo $player_club['id']; ?>"><?php echo htmlspecialchars($player_club['name']); ?></a></p>
            <?php endif; ?>
            <p><strong>Возраст:</strong> <?php echo $player['age']; ?></p>
            <p><strong>Рост / Вес:</strong> <?php echo $player['height']; ?> см / <?php echo $player['weight']; ?> кг</p>
            <p><strong>Рабочая нога:</strong> <?php echo htmlspecialchars($player['foot_side']); ?></p>
            <p><strong>Травма:</strong> <?php echo htmlspecialchars($player['injury']); ?></p>
            <p><strong>Позиции:</strong>
                <?php 
                    $all_positions = array_merge(array($main_position), $additional_positions);
                    $positions_output = array();
                    foreach($all_positions as $pos){
                        $positions_output[] = '<span style="'.position_class($pos).'">'.htmlspecialchars($pos).'</span>';
                    }
                    echo implode(', ',$positions_output);
                ?>
            </p>
            <p><strong>Зарплата:</strong> <?php echo number_format($player['salary']/1000000,1,',',' '); ?> млн</p>
            <p><strong>Выкуп:</strong> <?php echo number_format(($player['salary']*15)/1000000,1,',',' '); ?> млн</p>
        </div>

        <ul class="abilities-inline-list">
            <?php foreach($abilities as $key => $label): ?>
                <?php if (!empty($player[$key])): ?>
                    <li><span>★</span> <?php echo htmlspecialchars($label); ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="player-right">
        <ul class="skills-list">
            <?php foreach ($skills as $key => $label): ?>
                <?php if (isset($player[$key])): ?>
                    <li>
                        <span class="skill-label"><?php echo htmlspecialchars($label); ?></span>
                        <span class="skill-value <?php echo skill_class($player[$key]); ?>"><?php echo $player[$key]; ?></span>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
