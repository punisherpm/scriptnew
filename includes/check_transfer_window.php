<?php
require_once 'db_old.php';

function get_active_transfer_window_id() {
    $res = mysql_query("SELECT id FROM transfer_windows WHERE NOW() BETWEEN start_time AND end_time LIMIT 1");
    if ($res && mysql_num_rows($res) > 0) {
        $row = mysql_fetch_assoc($res);
        return intval($row['id']);
    }
    return null;
}

$window_id = get_active_transfer_window_id();

if (!$window_id) {
    die("❌ Сейчас трансферное окно не активно. Действия с игроками недоступны.");
}
