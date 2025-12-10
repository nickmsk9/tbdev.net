<?php
if (!defined('BLOCK_FILE')) {
    Header("Location: ../index.php");
    exit;
}

global $CURUSER, $use_sessions;

$blocktitle = "Кто в онлайне";

// Получаем последнего зарегистрированного пользователя
$a = sql_query("SELECT id, username FROM users WHERE status='confirmed' ORDER BY id DESC LIMIT 1");
if ($a && mysqli_num_rows($a) > 0) {
    $latest_user_row = mysqli_fetch_assoc($a);
    if ($CURUSER) {
        $latestuser = "<a href=\"userdetails.php?id=" . $latest_user_row["id"] . "\" class=\"online\">" . htmlspecialchars($latest_user_row["username"]) . "</a>";
    } else {
        $latestuser = htmlspecialchars($latest_user_row['username']);
    }
} else {
    $latestuser = "Нет пользователей";
}

// Инициализируем переменные
$title_who = [];
$users = $guests = $staff = $total = 0;

$dt = time() - 300;

if ($use_sessions) {
    // Используем сессии для отслеживания онлайн
    $result = sql_query("SELECT s.uid, s.username, s.class FROM sessions AS s WHERE s.time > " . sqlesc($dt) . " ORDER BY s.class DESC");
} else {
    // Используем last_access в таблице users
    $result = sql_query("SELECT u.id, u.username, u.class FROM users AS u WHERE u.last_access > " . sqlesc($dt) . " ORDER BY u.class DESC");
}

if ($result) {
    $parsed = [];
    $parsed_id = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $uid = (int)$row['uid'] ?? (int)$row['id'] ?? 0;
        $uname = $row['username'] ?? '';
        $class = (int)$row['class'] ?? 0;

        // Добавляем пользователя в список "Кто здесь"
        if (!empty($uname) && !in_array($uname, $parsed, true)) {
            $parsed[] = $uname;
            $title_who[] = "<a href=\"userdetails.php?id=" . $uid . "\" class=\"online\">" . get_user_class_color($class, htmlspecialchars($uname)) . "</a>";
        }

        // Считаем статистику
        if (!in_array($uid, $parsed_id, true)) {
            if ($class >= UC_MODERATOR) {
                $staff++;
            } elseif (empty($uname)) {
                $guests++;
            } elseif ($class < UC_MODERATOR) {
                $users++;
            }
            $parsed_id[] = $uid;
            $total++;
        }
    }
    
    mysqli_free_result($result);
}

// Формируем контент блока
$content = "<table border=\"0\" width=\"100%\"><tr valign=\"middle\"><td align=\"left\" class=\"embedded\"><b>Последний зарегистрированный: </b> $latestuser<hr></td></tr></table>\n";

if (count($title_who) > 0) {
    $content .= "<table border=\"0\" width=\"100%\"><tr valign=\"middle\"><td align=\"left\" class=\"embedded\"><b>Кто здесь: </b><hr></td></tr><tr><td class=\"embedded\">" . implode(", ", $title_who) . "<hr></td></tr></table>\n";
} else {
    $content .= "<table border=\"0\" width=\"100%\"><tr valign=\"middle\"><td align=\"left\" class=\"embedded\"><b>Кто здесь: </b>Нет пользователей онлайн за последние 5 минут.<hr></td></tr></table>\n";
}

$content .= "<table border=\"0\" width=\"100%\"><tr valign=\"middle\"><td colspan=\"2\" align=\"left\" class=\"embedded\"><b>В онлайн: </b></td></tr>\n";
$content .= "<tr><td class=\"embedded\"><img src=\"pic/info/admin.gif\" alt=\"Админ\"></td><td width=\"90%\" class=\"embedded\">Админы: $staff</td></tr>\n";
$content .= "<tr><td class=\"embedded\"><img src=\"pic/info/member.gif\" alt=\"Пользователь\"></td><td width=\"90%\" class=\"embedded\">Пользователи: $users</td></tr>\n";
$content .= "<tr><td class=\"embedded\"><img src=\"pic/info/guest.gif\" alt=\"Гость\"></td><td width=\"90%\" class=\"embedded\">Гости: $guests</td></tr>\n";
$content .= "<tr><td class=\"embedded\"><img src=\"pic/info/group.gif\" alt=\"Всего\"></td><td width=\"90%\" class=\"embedded\">Всего: $total</td></tr></table>\n";

?>