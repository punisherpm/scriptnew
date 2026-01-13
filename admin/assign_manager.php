<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../includes/auth.php';
require_once '../includes/db_old.php';

// Только для админов
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Доступ запрещён.");
}

$success = '';
$error = '';

// Обработка назначения
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $club_id = intval($_POST['club_id']);
    $manager_id = intval($_POST['manager_id']);

    $res1 = mysql_query("UPDATE clubs SET manager_id = $manager_id WHERE id = $club_id", $db);

    // Обновляем club_id у пользователя
    if ($manager_id > 0) {
        $res2 = mysql_query("UPDATE users SET club_id = $club_id WHERE id = $manager_id", $db);
    } else {
        // Если выбрано "снять тренера", обнуляем club_id у текущего менеджера
        $res2 = mysql_query("UPDATE users SET club_id = NULL WHERE club_id = $club_id", $db);
    }

    if ($res1 && $res2) {
        $success = "Тренер успешно " . ($manager_id ? "назначен." : "снят.");
    } else {
        $error = "Ошибка при обновлении данных: " . mysql_error($db);
    }
}

// Получение клубов с текущим тренером
$clubs_res = mysql_query("
    SELECT clubs.id, clubs.name, clubs.manager_id, users.username
    FROM clubs
    LEFT JOIN users ON clubs.manager_id = users.id
    ORDER BY clubs.name
", $db);

// Получение пользователей
$users_res = mysql_query("SELECT id, username FROM users ORDER BY username", $db);
$users = array();
while ($user = mysql_fetch_assoc($users_res)) {
    $users[] = $user;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Назначение тренера клубу</title>
</head>
<body>
    <h2>Назначение / снятие тренера клубу</h2>

    <?php if ($success): ?>
        <p style="color:green;"><?php echo $success; ?></p>
    <?php elseif ($error): ?>
        <p style="color:red;"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Клуб:</label><br>
        <select name="club_id" required>
            <?php while ($club = mysql_fetch_assoc($clubs_res)): ?>
                <option value="<?php echo $club['id']; ?>">
                    <?php echo htmlspecialchars($club['name']); ?>
                    <?php if ($club['username']): ?>
                        — Тренер: <?php echo htmlspecialchars($club['username']); ?>
                    <?php else: ?>
                        — <span style="color:red;">Без тренера</span>
                    <?php endif; ?>
                </option>
            <?php endwhile; ?>
        </select><br><br>

        <label>Новый тренер:</label><br>
        <select name="manager_id" required>
            <option value="0">— Снять тренера —</option>
            <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <button type="submit">Применить</button>
    </form>
</body>
</html>
