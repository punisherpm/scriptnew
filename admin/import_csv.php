<?php
header('Content-Type: text/html; charset=utf-8');
echo "✅ Старт импорта<br>";

$host = 'localhost';
$db   = 'a0663035_script';
$user = 'a0663035_ips';
$pass = '56WQi36v';
$charset = 'utf8';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
);

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("❌ Ошибка подключения к БД: " . $e->getMessage());
}

$filename = 'final_converted.csv'; // путь к файлу
if (!file_exists($filename)) {
    die("❌ CSV файл не найден");
}

$handle = fopen($filename, 'r');
if (!$handle) {
    die("❌ Не удалось открыть CSV файл");
}

$header = fgetcsv($handle, 1000, ';');

// Удаляем BOM, если есть
if (substr($header[0], 0, 3) === "\xEF\xBB\xBF") {
    $header[0] = substr($header[0], 3);
}
$header = array_map('trim', $header);

$added = 0;
$updated = 0;
$skipped = 0;

while (($data = fgetcsv($handle, 1000, ';')) !== false) {
    $row = array_combine($header, $data);

    $name = trim($row['NAME']);
    $club_name = trim($row['CLUB TEAM']);

    if ($name === '') {
        echo "❌ Пропущен игрок: нет имени<br>";
        $skipped++;
        continue;
    }

    if ($club_name === '') {
        $club_name = 'Без клуба';
    }

    $stmt = $pdo->prepare("SELECT id FROM clubs WHERE name = ?");
    $stmt->execute(array($club_name));
    $club = $stmt->fetch();

    if (!$club) {
        echo "❌ Пропущен игрок $name: клуб \"$club_name\" не найден<br>";
        $skipped++;
        continue;
    }

    $club_id = $club['id'];

    $stmt = $pdo->prepare("SELECT id FROM players WHERE name = ? AND club_id = ?");
    $stmt->execute(array($name, $club_id));
    $player = $stmt->fetch();

    $position_codes = array(
        'GK' => 0, 'CWP' => 2, 'CBT' => 3, 'SB' => 4, 'DMF' => 5, 'WB' => 6,
        'CMF' => 7, 'SMF' => 8, 'AMF' => 9, 'WF' => 10, 'SS' => 11, 'CF' => 12
    );
    $code_to_position = array_flip($position_codes);

    $raw_pos = strtoupper(trim(str_replace(' ', '', $row['REGISTERED POSITION'])));

    if (isset($position_codes[$raw_pos])) {
        $position = $raw_pos;
    } elseif (is_numeric($raw_pos)) {
        if (isset($code_to_position[$raw_pos])) {
            $position = $code_to_position[$raw_pos];
        } else {
            echo "⚠️ Неизвестная числовая позиция '{$raw_pos}' у игрока $name<br>";
            $position = null;
        }
    } else {
        echo "⚠️ Неизвестная позиция '{$row['REGISTERED POSITION']}' у игрока $name<br>";
        $position = null;
    }

    $positions_all = array();
    foreach ($position_codes as $pos => $code) {
        if (!empty($row[$pos]) && $row[$pos] == '1') {
            $positions_all[] = $pos;
        }
    }
    $positions_all_str = implode(',', $positions_all);

    $attack = isset($row['ATTACK']) ? $row['ATTACK'] : null;
    $defence = isset($row['DEFENSE']) ? $row['DEFENSE'] : null;
    $speed = isset($row['TOP SPEED']) ? $row['TOP SPEED'] : null;
    $curling = isset($row['CURLING']) ? $row['CURLING'] : null;
    $header_skill = isset($row['HEADING']) ? $row['HEADING'] : null;
    $gk_ability = isset($row['GOAL KEEPING']) ? $row['GOAL KEEPING'] : null;
    $tactical_dribble = isset($row['TACTICAL DRIBBLE']) ? (int)$row['TACTICAL DRIBBLE'] : 0;
    $outside = isset($row['OUTSIDE CURVE']) ? (int)$row['OUTSIDE CURVE'] : 0;
    $one_on_one_stopper = isset($row['1-1 STOPPER']) ? (int)$row['1-1 STOPPER'] : 0;


    if ($player) {
    echo "🔁 Обновляю игрока: $name ($club_name)<br>";
    $sql = "UPDATE players SET position = ?, positions_all = ?, attack = ?, defence = ?, speed = ?, curling = ?, header = ?, gk_ability = ?, tactical_dribble = ?, outside = ?, one_on_one_stopper = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute(array($position, $positions_all_str, $attack, $defence, $speed, $curling, $header_skill, $gk_ability, $tactical_dribble, $outside, $one_on_one_stopper, $player['id']));
        $updated++;
    } catch (PDOException $e) {
        echo "❌ Ошибка при обновлении $name: " . $e->getMessage() . "<br>";
    }
} else {
    echo "➕ Добавляю нового игрока: $name ($club_name)<br>";
    $sql = "INSERT INTO players (name, club_id, position, positions_all, attack, defence, speed, curling, header, gk_ability, tactical_dribble, outside, one_on_one_stopper) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute(array($name, $club_id, $position, $positions_all_str, $attack, $defence, $speed, $curling, $header_skill, $gk_ability, $tactical_dribble, $outside, $one_on_one_stopper));
        $added++;
    } catch (PDOException $e) {
        echo "❌ Ошибка при добавлении $name: " . $e->getMessage() . "<br>";
    }
}
}

fclose($handle);

echo "<br>✅ Импорт завершён<br>";
echo "➕ Добавлено: $added<br>";
echo "🔁 Обновлено: $updated<br>";
echo "❌ Пропущено: $skipped<br>";
?>
