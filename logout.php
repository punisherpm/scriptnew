<?php
require_once 'includes/auth.php';

// Удаляем cookie "Запомнить меня", если она есть
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/', '', false, true);
}

// Вызываем стандартную функцию выхода
logout();
?>
