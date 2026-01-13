<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/auth.php';
require_once '../includes/db_old.php';

if (!is_logged_in() || $_SESSION['user']['role'] !== 'admin') {
    die('Доступ запрещён');
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $league_id = isset($_POST['league_id']) ? intval($_POST['league_id']) : 0;
    $club_name = isset($_POST['club_name']) ? trim($_POST['club_name']) : '';

    if ($league_id > 0 && $club_name != '') {
        $club_name = mysql_real_escape_string($club_name);
        $res = mysql_query("INSERT INTO clubs (name, league_id) VALUES ('$club_name', $league_id)", $db);
        if ($res) {
            $success = "Клуб \"$club_name\" успешно добавлен.";
        } else {
            $error = "Ошибка при добавлении клуба: " . mysql_error();
        }
    } else {
        $error = "Пожалуйста, заполните все поля.";
    }
}

// Получаем список лиг
$leagues_res = mysql_query("SELECT id, name FROM leagues ORDER BY name", $db);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Добавить клуб</title>
</head>
<body>
    <h2>Добавить клуб в лигу</h2>

    <?php if ($success): ?>
        <p style="color:green;"><?php echo $success; ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p style="color:red;"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="post">
        <label for="league_id">Лига:</label><br>
        <select name="league_id" id="league_id" required>
            <option value="">-- Выберите лигу --</option>
            <?php while ($league = mysql_fetch_assoc($leagues_res)): ?>
                <option value="<?php echo $league['id']; ?>">
                    <?php echo htmlspecialchars($league['name']); ?>
                </option>
            <?php endwhile; ?>
        </select><br><br>

        <label for="club_name">Название клуба:</label><br>
        <input type="text" name="club_name" id="club_name" required><br><br>

        <button type="submit">Добавить клуб</button>
    </form>
</body>
</html>
