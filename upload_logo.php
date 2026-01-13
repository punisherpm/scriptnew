<?php
require_once 'includes/db_old.php';

$club_id = intval($_POST['club_id']);
if (!empty($_FILES['logo']['tmp_name'])) {
    $filename = 'uploads/logos/' . $club_id . '.png';
    move_uploaded_file($_FILES['logo']['tmp_name'], $filename);
    mysql_query("UPDATE clubs SET logo = '$filename' WHERE id = $club_id");
}
header("Location: dashboard.php");
exit;
?>
