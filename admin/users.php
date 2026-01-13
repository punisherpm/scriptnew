<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/auth.php';
require_once '../includes/db_old.php'; // mysql_* подключение

if (!is_logged_in() || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// 👉 Обработка обновления сессии администратора
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_session'])) {
    $user_id = intval($_SESSION['user']['id']);
    $res = mysql_query("SELECT * FROM users WHERE id = $user_id", $db);
    if ($row = mysql_fetch_assoc($res)) {
        $_SESSION['user'] = $row;
        header("Location: users.php?session_refreshed=1");
        exit;
    }
}

// 👉 Обработка изменения привязки клуба и роли
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['club'])) {
    foreach ($_POST['club'] as $user_id => $club_id) {
        mysql_query("UPDATE users SET club_id = " . intval($club_id) . " WHERE id = " . intval($user_id), $db);
    }

    // Обработка ролей
    if (isset($_POST['role'])) {
        foreach ($_POST['role'] as $user_id => $role) {
            $role = in_array($role, array('user', 'moderator', 'admin')) ? $role : 'user';
            mysql_query("UPDATE users SET role = '" . mysql_real_escape_string($role) . "' WHERE id = " . intval($user_id), $db);
        }
    }

    header("Location: users.php?updated=1");
    exit;
}

// 👉 Получение пользователей
$users = array();
$result = mysql_query("SELECT * FROM users ORDER BY id ASC", $db);
while ($row = mysql_fetch_assoc($result)) {
    $users[] = $row;
}

// 👉 Получение клубов
$clubs = array();
$res = mysql_query("SELECT * FROM clubs ORDER BY name ASC", $db);
while ($club = mysql_fetch_assoc($res)) {
    $clubs[$club['id']] = $club['name'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Админка: Привязка клубов</title>
</head>
<body>
    <h2>Привязка клубов и ролей к пользователям</h2>

    <?php if (isset($_GET['updated'])): ?>
        <p style="color:green;">Изменения сохранены!</p>
    <?php endif; ?>

    <?php if (isset($_GET['session_refreshed'])): ?>
        <p style="color:green;">Сессия администратора обновлена!</p>
    <?php endif; ?>

    <form method="post">
        <table border="1" cellpadding="5">
            <tr>
                <th>Пользователь</th>
                <th>Клуб</th>
                <th>Роль</th>
            </tr>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td>
                        <select name="club[<?php echo $user['id']; ?>]">
                            <option value="0">— Не привязан —</option>
                            <?php foreach ($clubs as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php if ($user['club_id'] == $id) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <label><input type="radio" name="role[<?php echo $user['id']; ?>]" value="user" <?php if ($user['role'] == 'user') echo 'checked'; ?>> пользователь</label><br>
                        <label><input type="radio" name="role[<?php echo $user['id']; ?>]" value="moderator" <?php if ($user['role'] == 'moderator') echo 'checked'; ?>> модератор</label><br>
                        <label><input type="radio" name="role[<?php echo $user['id']; ?>]" value="admin" <?php if ($user['role'] == 'admin') echo 'checked'; ?>> админ</label>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <br>
        <input type="submit" value="Сохранить изменения">
    </form>

    <!-- Кнопка обновления сессии -->
    <form method="post">
        <input type="submit" name="refresh_session" value="Обновить мою сессию">
    </form>

    <p><a href="../dashboard.php">← Вернуться в личный кабинет</a></p>
</body>
</html>
