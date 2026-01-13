<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Доступ запрещен");
}

// Получаем ID игрока из POST или GET
if (isset($_POST['player_id'])) {
    $player_id = intval($_POST['player_id']);
} elseif (isset($_GET['id'])) {
    $player_id = intval($_GET['id']);
} else {
    die("Не указан ID игрока");
}

$upload_dir = '../img/players/';
if (!is_dir($upload_dir)) {
    // Попытка создать папку, если ее нет
    if (!mkdir($upload_dir, 0755, true)) {
        die("Не удалось создать директорию для загрузки фото");
    }
}

$target_file = $upload_dir . $player_id . '.png';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $type = mime_content_type($_FILES['photo']['tmp_name']);
        if ($type === 'image/png') {
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                echo "<p style='color:green;'>Фото успешно загружено.</p>";
            } else {
                echo "<p style='color:red;'>Ошибка при сохранении файла.</p>";
            }
        } else {
            echo "<p style='color:red;'>Только PNG-файлы допустимы.</p>";
        }
    } else {
        echo "<p style='color:red;'>Файл не выбран или ошибка при загрузке.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Загрузка фото игрока</title>
</head>
<body>
    <h2>Загрузить фото игрока ID: <?php echo htmlspecialchars($player_id); ?></h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="player_id" value="<?php echo htmlspecialchars($player_id); ?>">
        <input type="file" name="photo" accept="image/png" required>
        <button type="submit">Загрузить</button>
    </form>
    <p><a href="../player.php?id=<?php echo htmlspecialchars($player_id); ?>">Назад к игроку</a></p>
</body>
</html>
