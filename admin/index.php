<?php
require_once '../includes/auth.php';
require_once '../includes/db_old.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Доступ запрещён");
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
</head>
<body>
    <h2>Админ-панель</h2>
<ul>
    <li><a href="add_club.php">Добавить клуб</a></li>
    <li><a href="assign_manager.php">Назначить тренера</a></li>
    <li><a href="assign_club_to_league.php">Назначить клуб в лигу</a></li>
    <li><a href="edit_club_budgets.php">Редактировать бюджеты клубов</a></li>
    <li><a href="start_window.php">Создать трансферное окно</a></li>
</ul>
    <p><a href="../dashboard.php">← Назад в личный кабинет</a></p>
</body>
</html>
