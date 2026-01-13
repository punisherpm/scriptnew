<?php
date_default_timezone_set('Europe/Moscow');

// Совместимость с PHP 5.3 — проверка, запущена ли сессия
if (!isset($_SESSION)) {
    session_start();
}

require_once 'db.php';

/**
 * Проверка, вошёл ли пользователь
 */
function is_logged_in() {
    global $pdo;

    // Если пользователь уже в сессии
    if (isset($_SESSION['user'])) {
        return true;
    }

    // Проверка cookie (автовход)
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ?");
        $stmt->execute(array($token));
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user'] = array(
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => isset($user['role']) ? $user['role'] : null,
                'club_id' => isset($user['club_id']) ? $user['club_id'] : null
            );
            return true;
        }
    }

    return false;
}

/**
 * Авторизация пользователя
 */
function login($username, $password_input, $remember = false) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(array($username));
    $user = $stmt->fetch();

    if ($user) {
        // Авторизация в стиле vBulletin
        $input_md5 = md5($password_input);
        $vb_hash = md5($input_md5 . $user['salt']);

        if ($vb_hash === $user['password']) {
            $_SESSION['user'] = array(
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => isset($user['role']) ? $user['role'] : null,
                'club_id' => isset($user['club_id']) ? $user['club_id'] : null
            );

            // Если пользователь выбрал "Запомнить меня"
            if ($remember) {
                $token = md5($user['id'] . time() . rand());
                $update = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $update->execute(array($token, $user['id']));
                setcookie("remember_token", $token, time() + (86400 * 30), "/"); // 30 дней
            }

            return true;
        }
    }

    return false;
}

/**
 * Выход из системы
 */
function logout() {
    global $pdo;

    if (isset($_SESSION['user'])) {
        $user_id = $_SESSION['user']['id'];
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $stmt->execute(array($user_id));
    }

    setcookie("remember_token", "", time() - 3600, "/");
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
