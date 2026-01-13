<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
header('Content-Type: text/html; charset=utf-8');
require_once 'includes/db_old.php';

$import_result = '';

if(isset($_POST['player_id']) && trim($_POST['player_id']) != ''){
    $player_id = intval($_POST['player_id']);
    if($player_id <= 0){
        $import_result = "<span style='color:red;'>Неверный player_id</span>";
    } else {
        $api_token = 'Kd97Elh6MPsLcXO2nJ';
        $api_url = "https://api.sofifa.net/player/$player_id";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "X-API-KEY: $api_token",
            "User-Agent: Mozilla/5.0"
        ));
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($httpcode != 200){
            $import_result = "<span style='color:red;'>Не удалось получить данные из Sofifa API. HTTP код: $httpcode.</span>";
        } else {
            $import_result = "<span style='color:green;'>Данные успешно получены! Можно вставлять конвертацию.</span>";
            // здесь будет код конвертации в PES6CN
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Импорт игрока с Sofifa</title>
</head>
<body>
<h2>Импорт игрока с Sofifa</h2>
<form method="post" action="">
<label>Введите player_id игрока:</label><br>
<input type="text" name="player_id" size="20" placeholder="ID игрока Sofifa" required><br><br>
<input type="submit" value="Импортировать">
</form>

<div style="margin-top:20px;">
<?php echo $import_result; ?>
</div>
</body>
</html>
