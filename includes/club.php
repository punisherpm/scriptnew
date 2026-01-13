<?php
require_once 'db.php';

function get_user_club($club_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM clubs WHERE id = ?");
    $stmt->execute(array($club_id));
    return $stmt->fetch();
}

function get_club_players($club_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM players WHERE club_id = ?");
    $stmt->execute(array($club_id));
    return $stmt->fetchAll();
}
