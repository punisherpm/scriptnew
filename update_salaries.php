<?php
session_start();
require_once 'includes/db_old.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], array('admin', 'moderator'))) {
    die("Доступ запрещён.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salary'])) {
    foreach ($_POST['salary'] as $player_id => $salary) {
        $player_id = intval($player_id);
        $salary = intval($salary);

        // Валидация
        if ($salary < 500000 || $salary % 100000 !== 0) {
            continue; // Пропустить, если не проходит валидацию
        }

        mysql_query("UPDATE players SET salary = $salary WHERE id = $player_id");
    }

    header("Location: dashboard.php?updated=1");
    exit;
} else {
    die("Некорректный запрос.");
}
