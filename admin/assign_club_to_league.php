<?php
require_once '../includes/auth.php';
require_once '../includes/db_old.php';

if (!is_logged_in() || $_SESSION['user']['role'] !== 'admin') {
    die("Доступ запрещён");
}

// Фильтр: only_null = показать только клубы без лиги
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filter_sql = ($filter === 'no_league') ? "WHERE league_id IS NULL" : "";

$clubs_query = "SELECT id, name FROM clubs $filter_sql ORDER BY name";
$result_clubs = mysql_query($clubs_query, $db);
if (!$result_clubs) {
    die("Ошибка запроса clubs: " . mysql_error());
}

$leagues_query = "SELECT id, name FROM leagues ORDER BY name";
$result_leagues = mysql_query($leagues_query, $db);
if (!$result_leagues) {
    die("Ошибка запроса leagues: " . mysql_error());
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $club_id = isset($_POST['club_id']) ? intval($_POST['club_id']) : 0;
    $league_id = isset($_POST['league_id']) ? intval($_POST['league_id']) : 0;

    if ($club_id > 0 && $league_id > 0) {
        $update = mysql_query("UPDATE clubs SET league_id = $league_id WHERE id = $club_id", $db);
        if ($update) {
            echo "<p style='color:green'>Клуб успешно назначен в лигу.</p>";
        } else {
            echo "<p style='color:red'>Ошибка при обновлении: " . mysql_error() . "</p>";
        }
    } else {
        echo "<p style='color:red'>Пожалуйста, выбери клуб и лигу.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Назначить клуб в лигу</title>
</head>
<body>
    <h2>Назначить клуб в лигу</h2>

    <form method="get">
        <label>Показать клубы:</label>
        <select name="filter" onchange="this.form.submit()">
            <option value="all" <?php if ($filter === 'all') echo 'selected'; ?>>Все клубы</option>
            <option value="no_league" <?php if ($filter === 'no_league') echo 'selected'; ?>>Без лиги</option>
        </select>
    </form>

    <form method="post">
        <p>
            <label>Клуб:</label><br>
            <select name="club_id" required>
                <option value="">-- Выберите клуб --</option>
                <?php while ($club = mysql_fetch_assoc($result_clubs)): ?>
                    <option value="<?php echo $club['id']; ?>"><?php echo htmlspecialchars($club['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </p>

        <p>
            <label>Лига:</label><br>
            <select name="league_id" required>
                <option value="">-- Выберите лигу --</option>
                <?php while ($league = mysql_fetch_assoc($result_leagues)): ?>
                    <option value="<?php echo $league['id']; ?>"><?php echo htmlspecialchars($league['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </p>

        <button type="submit">Назначить</button>
    </form>

    <form action="index.php">
        <button type="submit">← Назад в админку</button>
    </form>
</body>
</html>
