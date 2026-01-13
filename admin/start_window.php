<?php
require_once '../includes/auth.php';
require_once '../includes/db_old.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Доступ запрещён");
}

date_default_timezone_set('Europe/Moscow');

// Обработка создания нового ТО
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['name']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    if (empty($name) || empty($start_time) || empty($end_time)) {
        die("Все поля обязательны для заполнения.");
    }

    if (strtotime($start_time) >= strtotime($end_time)) {
        die("Дата окончания должна быть позже даты начала.");
    }

    $escaped_name = mysql_real_escape_string($name);
    $escaped_start = mysql_real_escape_string($start_time);
    $escaped_end = mysql_real_escape_string($end_time);

    $query = "INSERT INTO transfer_windows (name, start_time, end_time) VALUES ('$escaped_name', '$escaped_start', '$escaped_end')";
    $result = mysql_query($query, $db);

    echo "<meta charset=\"UTF-8\">";
    if ($result) {
        echo "<p>Трансферное окно '$escaped_name' успешно создано.</p>";
    } else {
        echo "<p>Ошибка: " . mysql_error($db) . "</p>";
    }
}

// Обработка ручного закрытия ТО
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close' && isset($_POST['window_id'])) {
    $window_id = intval($_POST['window_id']);
    $now = date('Y-m-d H:i:s');

    mysql_query("UPDATE transfer_windows SET end_time = '$now' WHERE id = $window_id", $db);
    echo "<meta charset=\"UTF-8\">";
    echo "<p>ТО было закрыто вручную.</p>";
}

// Получаем список ТО
$windows = mysql_query("SELECT * FROM transfer_windows ORDER BY start_time DESC", $db);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Создание трансферного окна</title>
</head>
<body>
    <h2>Создание нового трансферного окна</h2>
    <form method="post" action="start_window.php">
        <input type="hidden" name="action" value="create">
        <label for="name">Название ТО:</label><br>
        <input type="text" name="name" id="name" required><br><br>

        <label for="start_time">Начало ТО (МСК):</label><br>
        <input type="datetime-local" name="start_time" id="start_time" required><br><br>

        <label for="end_time">Конец ТО (МСК):</label><br>
        <input type="datetime-local" name="end_time" id="end_time" required><br><br>

        <button type="submit">Создать ТО</button>
    </form>

    <hr>

    <h3>Список трансферных окон</h3>
    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Начало (МСК)</th>
            <th>Окончание (МСК)</th>
            <th>Статус</th>
            <th>Действие</th>
        </tr>
        <?php while ($window = mysql_fetch_assoc($windows)) {
            $now = date('Y-m-d H:i:s');
            $is_active = ($window['start_time'] <= $now && $window['end_time'] > $now);
            ?>
            <tr>
                <td><?php echo $window['id']; ?></td>
                <td><?php echo htmlspecialchars($window['name']); ?></td>
                <td><?php echo $window['start_time']; ?></td>
                <td><?php echo $window['end_time']; ?></td>
                <td><?php echo $is_active ? '<b style="color:green;">Активно</b>' : 'Не активно'; ?></td>
                <td>
                    <?php if ($is_active): ?>
                        <form method="post" action="start_window.php" onsubmit="return confirm('Вы уверены, что хотите закрыть это ТО?');">
                            <input type="hidden" name="action" value="close">
                            <input type="hidden" name="window_id" value="<?php echo $window['id']; ?>">
                            <button type="submit">Закрыть ТО</button>
                        </form>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php } ?>
    </table>

    <p><a href="index.php">← Назад в админ-панель</a></p>
</body>
</html>
