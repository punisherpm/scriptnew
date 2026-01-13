<?php
session_start();
require_once 'includes/db_old.php';

if (!isset($_SESSION['user'])) {
    die("Доступ запрещён. Пожалуйста, войдите в систему.");
}

$user = $_SESSION['user'];
$user_id = intval($user['id']);
$club_id = intval($user['club_id']);
$is_admin_or_moderator = in_array($user['role'], array('admin', 'moderator'));
$is_admin = ($user['role'] === 'admin');

$edit_salaries = isset($_GET['edit_salaries']) && $is_admin_or_moderator;
$edit_budget = isset($_GET['edit_budget']) && $is_admin_or_moderator;

// =====================================
// Новые игроки (needs_salary_update=1)
// =====================================
$new_players_query = mysql_query("
    SELECT * 
    FROM players 
    WHERE club_id=$club_id AND needs_salary_update=1
");
$new_players = array();
while($player = mysql_fetch_assoc($new_players_query)) $new_players[] = $player;

// Обработка формы назначения зарплаты новым игрокам
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_salary'])) {
    foreach ($_POST['set_salary'] as $player_id => $salary) {
        $player_id = intval($player_id);
        $salary = intval($salary);
        if ($salary >= 500000 && $salary % 100000 === 0) {
            $res_old = mysql_query("SELECT salary FROM players WHERE id = $player_id AND club_id = $club_id AND needs_salary_update=1");
            if ($res_old && mysql_num_rows($res_old) > 0) {
                $row_old = mysql_fetch_assoc($res_old);
                $old_salary = intval($row_old['salary']);
                mysql_query("UPDATE players SET salary=$salary, needs_salary_update=0 WHERE id=$player_id");
                mysql_query("INSERT INTO salary_history (player_id, old_salary, new_salary, changed_by, changed_at) VALUES (
                    $player_id, $old_salary, $salary, $user_id, NOW()
                )");
            }
        }
    }
    header("Location: dashboard.php");
    exit;
}

// =====================================
// Сохранение зарплат и бюджета
// =====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin_or_moderator) {
    if (isset($_POST['salary'])) {
        foreach ($_POST['salary'] as $player_id => $salary) {
            $player_id = intval($player_id);
            $salary = intval($salary);
            if ($salary >= 500000 && $salary % 100000 === 0) {
                $res_old = mysql_query("SELECT salary FROM players WHERE id = $player_id AND club_id = $club_id");
                if ($res_old && mysql_num_rows($res_old) > 0) {
                    $row_old = mysql_fetch_assoc($res_old);
                    $old_salary = intval($row_old['salary']);
                    if ($old_salary !== $salary) {
                        mysql_query("UPDATE players SET salary = $salary WHERE id = $player_id");
                        mysql_query("INSERT INTO salary_history (player_id, old_salary, new_salary, changed_by, changed_at) VALUES (
                            $player_id, $old_salary, $salary, $user_id, NOW()
                        )");
                    }
                }
            }
        }
        header("Location: dashboard.php");
        exit;
    } elseif (isset($_POST['budget'])) {
        $new_budget = intval($_POST['budget']) * 1000000;
        if ($new_budget >= 0 && $new_budget % 1000000 === 0) {
            $result = mysql_query("SELECT budget FROM clubs WHERE id = $club_id");
            if ($result && mysql_num_rows($result) > 0) {
                $row = mysql_fetch_assoc($result);
                $old_budget = intval($row['budget']);
                if ($old_budget != $new_budget) {
                    mysql_query("UPDATE clubs SET budget = $new_budget WHERE id = $club_id");
                    mysql_query("INSERT INTO budget_history (club_id, old_budget, new_budget, changed_by, changed_at) VALUES (
                        $club_id, $old_budget, $new_budget, $user_id, NOW()
                    )");
                }
            }
        }
        header("Location: dashboard.php");
        exit;
    }
}

// Данные клуба
$club_query = mysql_query("SELECT name, budget, logo FROM clubs WHERE id = $club_id");
$club = mysql_fetch_assoc($club_query);
if (!$club) die("Клуб не найден.");

// Игроки
$players_query = mysql_query("SELECT * FROM players WHERE club_id = $club_id");
$players = array();
while ($player = mysql_fetch_assoc($players_query)) $players[] = $player;

// Сортировка
$position_order = array(
    'GK' => 1, 'CWP' => 2, 'CBT' => 3, 'SB' => 4, 'DMF' => 5,
    'WB' => 6, 'CMF' => 7, 'SMF' => 8, 'AMF' => 9,
    'WF' => 10, 'SS' => 11, 'CF' => 12
);
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = (isset($_GET['order']) && $_GET['order'] === 'desc') ? 'desc' : 'asc';
usort($players, function($a,$b) use($sort_by,$order,$position_order){
    if($sort_by==='position'){
        $a_val=isset($position_order[$a['position']])?$position_order[$a['position']]:99;
        $b_val=isset($position_order[$b['position']])?$position_order[$b['position']]:99;
    } elseif($sort_by==='buyout'){
        $a_val=$a['salary']*15;
        $b_val=$b['salary']*15;
    } else {
        $a_val=$a[$sort_by];
        $b_val=$b[$sort_by];
    }
    if($a_val==$b_val) return 0;
    return ($order==='asc')?($a_val<$b_val?-1:1):($a_val>$b_val?-1:1);
});

// Сумма зарплат
$total_salaries = 0;
foreach($players as $p) $total_salaries += $p['salary'];

// Истории
$budget_history_query = mysql_query("
    SELECT bh.*, u.username
    FROM budget_history bh
    LEFT JOIN users u ON bh.changed_by=u.id
    WHERE bh.club_id=$club_id
    ORDER BY bh.changed_at DESC
");
$budget_history = array(); while($row=mysql_fetch_assoc($budget_history_query)) $budget_history[]=$row;

$salary_history_query = mysql_query("
    SELECT sh.*, p.name as player_name, u.username
    FROM salary_history sh
    LEFT JOIN players p ON sh.player_id=p.id
    LEFT JOIN users u ON sh.changed_by=u.id
    WHERE p.club_id=$club_id
    ORDER BY sh.changed_at DESC
");
$salary_history = array(); while($row=mysql_fetch_assoc($salary_history_query)) $salary_history[]=$row;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Центр управления клубом</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(to bottom right, #e0f7ff, #f0eaff);
    margin:0; padding:0; color:#222;
}
.container {
    max-width: 960px;
    margin: 30px auto;
    padding: 25px;
    border-radius: 20px;
    background: rgba(255,255,255,0.25);
    box-shadow: 0 8px 32px rgba(31,38,135,0.37);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.18);
}
h2,h3{text-align:center;}
h2{margin-bottom:30px;}
h3{margin-top:30px;margin-bottom:15px;}
.budget{text-align:center;font-size:1.3em;font-weight:bold;margin-bottom:20px;}
table{
    width:100%; border-collapse:collapse; margin-bottom:20px; background: rgba(255,255,255,0.2); border-radius:12px; overflow:hidden;
}
th,td{padding:10px;text-align:center;}
th{background: rgba(255,255,255,0.3);}
tr:nth-child(even){background: rgba(255,255,255,0.15);}
tr:nth-child(odd){background: rgba(255,255,255,0.1);}
a{color:#0066cc;text-decoration:none;}
a:hover{text-decoration:underline;}
button{
    background: rgba(255,255,255,0.3);
    border:none; padding:8px 15px;
    border-radius:12px; cursor:pointer;
    color:#222; font-weight:bold; margin:3px;
    transition: 0.3s;
}
button:hover{background: rgba(255,255,255,0.5);}
input[type=number]{padding:5px 8px;border-radius:8px;border:1px solid rgba(0,0,0,0.2);width:80px;text-align:center;}
.toggle-header{
    font-weight:bold; padding:10px; background: rgba(255,255,255,0.3);
    border-radius:12px; cursor:pointer; margin-bottom:5px;
}
.toggle-content{display:none; padding:10px; background: rgba(255,255,255,0.15); border-radius:12px;}
</style>
<script>
function toggleBox(id){
    var el=document.getElementById(id);
    el.style.display=(el.style.display==='none')?'block':'none';
}
</script>
</head>
<body>
<div class="container">
<h2>Управление клубом</h2>
<?php
$logo_path = 'uploads/logos/' . $club_id . '.png';
if (file_exists($logo_path)) {
    echo '<div style="text-align:center;margin:20px 0;">
            <img src="' . htmlspecialchars($logo_path) . '" width="120" style="border-radius:12px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
          </div>';
}
?>
<div class="budget">💰 Бюджет: <?php echo number_format($club['budget']/1000000,2); ?> млн | 📊 Сумма зарплат: <?php echo number_format($total_salaries/1000000,2); ?> млн</div>

<!-- Новые игроки -->
<?php if(count($new_players) > 0): ?>
<h3>🆕 Назначение зарплаты новым игрокам</h3>
<form method="post">
<table>
<thead><tr><th>Фото</th><th>Имя</th><th>Возраст</th><th>Позиция</th><th>Назначить зарплату</th></tr></thead>
<tbody>
<?php foreach($new_players as $player): ?>
<tr>
<td><img src="img/players/<?php echo file_exists("img/players/{$player['id']}.png")?$player['id']:'default'; ?>.png" width="40"></td>
<td><a href="player.php?id=<?php echo $player['id']; ?>"><?php echo htmlspecialchars($player['name']); ?></a></td>
<td><?php echo $player['age']; ?></td>
<td><?php echo $player['position']; ?></td>
<td><input type="number" name="set_salary[<?php echo $player['id']; ?>]" value="<?php echo $player['salary']; ?>" step="100000" min="500000"></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<button type="submit">Сохранить зарплаты новых игроков</button>
</form>
<?php endif; ?>

<!-- Основная таблица игроков -->
<?php if($players): ?>
<form method="post">
<table>
<thead>
<tr>
<th>Фото</th>
<th><a href="?sort=name&order=<?php echo $sort_by=='name' && $order=='asc' ? 'desc' : 'asc'; ?>">Имя</a></th>
<th><a href="?sort=age&order=<?php echo $sort_by=='age' && $order=='asc' ? 'desc' : 'asc'; ?>">Возраст</a></th>
<th><a href="?sort=position&order=<?php echo $sort_by=='position' && $order=='asc' ? 'desc' : 'asc'; ?>">Позиция</a></th>
<th><a href="?sort=salary&order=<?php echo $sort_by=='salary' && $order=='asc' ? 'desc' : 'asc'; ?>">Зарплата</a></th>
<th><a href="?sort=buyout&order=<?php echo $sort_by=='buyout' && $order=='asc' ? 'desc' : 'asc'; ?>">Выкуп</a></th>
</tr>
</thead>
<tbody>
<?php foreach($players as $player): ?>
<tr>
<td><img src="img/players/<?php echo file_exists("img/players/{$player['id']}.png")?$player['id']:'default'; ?>.png" width="40"></td>
<td><a href="player.php?id=<?php echo $player['id']; ?>"><?php echo htmlspecialchars($player['name']); ?></a></td>
<td><?php echo $player['age']; ?></td>
<td><?php echo $player['position']; ?></td>
<td>
<?php if($edit_salaries): ?>
<input type="number" name="salary[<?php echo $player['id']; ?>]" value="<?php echo $player['salary']; ?>" step="100000" min="500000">
<?php else: ?>
<?php echo number_format($player['salary']/1000000,2); ?> млн
<?php endif; ?>
</td>
<td><?php echo number_format(($player['salary']*15)/1000000,2); ?> млн</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php if($edit_salaries): ?><button type="submit">Сохранить зарплаты</button><?php endif; ?>
</form>
<?php endif; ?>

<div style="text-align:center;">
<?php if(!$edit_salaries): ?><a href="?edit_salaries=1"><button>Редактировать зарплаты</button></a><?php else: ?><a href="dashboard.php"><button>Отмена</button></a><?php endif; ?>
<?php if(!$edit_budget): ?><a href="?edit_budget=1"><button>Редактировать бюджет</button></a><?php else: ?><a href="dashboard.php"><button>Отмена</button></a><?php endif; ?>
<a href="home.php"><button>На главную</button></a>
</div>

<?php if($edit_budget): ?>
<div style="margin-top:20px;">
<form method="post">
<label>Новый бюджет (млн): <input type="number" name="budget" value="<?php echo $club['budget']/1000000; ?>" step="0.1" min="0" required></label>
<button type="submit">Сохранить</button>
</form>
</div>
<?php endif; ?>

<?php if($is_admin_or_moderator): ?>
<div class="toggle-header" onclick="toggleBox('budget-history')">📊 История бюджета</div>
<div class="toggle-content" id="budget-history">
<table>
<thead><tr><th>ID</th><th>Старый</th><th>Новый</th><th>Изменил</th><th>Дата</th></tr></thead>
<tbody>
<?php foreach($budget_history as $bh): ?>
<tr>
<td><?php echo $bh['id']; ?></td>
<td><?php echo number_format($bh['old_budget']/1000000,2); ?></td>
<td><?php echo number_format($bh['new_budget']/1000000,2); ?></td>
<td><?php echo !empty($bh['username'])?htmlspecialchars($bh['username']):'неизвестно'; ?></td>
<td><?php echo htmlspecialchars($bh['changed_at']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div class="toggle-header" onclick="toggleBox('salary-history')">💼 История зарплат</div>
<div class="toggle-content" id="salary-history">
<table>
<thead><tr><th>ID</th><th>Игрок</th><th>Старая</th><th>Новая</th><th>Изменил</th><th>Дата</th></tr></thead>
<tbody>
<?php foreach($salary_history as $sh): ?>
<tr>
<td><?php echo $sh['id']; ?></td>
<td><?php echo htmlspecialchars($sh['player_name']); ?></td>
<td><?php echo number_format($sh['old_salary']/1000000,2); ?></td>
<td><?php echo number_format($sh['new_salary']/1000000,2); ?></td>
<td><?php echo !empty($sh['username'])?htmlspecialchars($sh['username']):'неизвестно'; ?></td>
<td><?php echo htmlspecialchars($sh['changed_at']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
</body>
</html>
