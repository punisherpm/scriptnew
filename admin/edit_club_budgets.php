<?php
require_once '../includes/auth.php';
require_once '../includes/db_old.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Доступ запрещён");
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['budgets'])) {
    foreach ($_POST['budgets'] as $club_id => $budget) {
        $club_id = intval($club_id);
        $budget = intval($budget);
        mysql_query("UPDATE clubs SET budget = $budget WHERE id = $club_id");
    }
    $message = "Бюджеты успешно обновлены.";
}

// Получаем список клубов с лигами
$res = mysql_query("
    SELECT clubs.id, clubs.name, clubs.budget, leagues.name AS league_name
    FROM clubs
    LEFT JOIN leagues ON clubs.league_id = leagues.id
    ORDER BY leagues.name IS NULL, leagues.name ASC, clubs.name ASC
");

// Группируем по лигам
$clubs_by_league = array();
while ($row = mysql_fetch_assoc($res)) {
    $league = $row['league_name'] ? $row['league_name'] : 'Без лиги';
    if (!isset($clubs_by_league[$league])) {
        $clubs_by_league[$league] = array();
    }
    $clubs_by_league[$league][] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Редактирование бюджетов клубов</title>
</head>
<body>
    <h2>Редактирование бюджетов клубов</h2>

    <?php if (isset($message)): ?>
        <p style="color: green;"><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="post">
        <?php foreach ($clubs_by_league as $league_name => $clubs): ?>
            <h3><?php echo htmlspecialchars($league_name); ?></h3>
            <table border="1" cellpadding="6" cellspacing="0">
                <tr>
                    <th>Клуб</th>
                    <th>Бюджет (в млн)</th>
                </tr>
                <?php foreach ($clubs as $club): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($club['name']); ?></td>
                        <td>
                            <input type="number" name="budgets[<?php echo $club['id']; ?>]" value="<?php echo intval($club['budget']); ?>" min="0">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <br>
        <?php endforeach; ?>

        <button type="submit">💾 Сохранить изменения</button>
    </form>

    <br>
    <p><a href="index.php">← Назад в админку</a></p>
</body>
</html>
