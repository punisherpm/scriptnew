<?php
require_once 'includes/db_old.php';
session_start();

$club_id = intval($_POST['club_id']);
$new_budget = intval($_POST['budget']);
$user_id = intval($_SESSION['user']['id']);

$old = mysql_fetch_assoc(mysql_query("SELECT budget FROM clubs WHERE id = $club_id"));
$old_budget = intval($old['budget']);

mysql_query("UPDATE clubs SET budget = $new_budget WHERE id = $club_id");
mysql_query("INSERT INTO budget_history (club_id, changed_by, old_budget, new_budget, changed_at)
    VALUES ($club_id, $user_id, $old_budget, $new_budget, NOW())");

header("Location: dashboard.php");
exit;
?>
