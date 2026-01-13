<?php
require_once 'includes/auth.php';
require_once 'includes/db_old.php';

if (!is_logged_in() || $_SESSION['user']['role'] !== 'admin') {
    die("Доступ запрещён");
}

if (isset($_POST['player_id']) && is_numeric($_POST['player_id']) && isset($_POST['salary'])) {
    $player_id = intval($_POST['player_id']);
    $salary = floatval($_POST['salary']);

    $update = mysql_query("UPDATE players SET salary = $salary WHERE id = $player_id", $db);

    if ($update) {
        header("Location: player.php?id=" . $player_id);
        exit;
    } else {
        echo "Ошибка обновления зарплаты: " . mysql_error($db);
    }
} else {
    echo "Неверные данные";
}
