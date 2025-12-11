<?

/*
// +--------------------------------------------------------------------------+
// | Project:    TBDevYSE - TBDev Yuna Scatari Edition                        |
// +--------------------------------------------------------------------------+
// | This file is part of TBDevYSE. TBDevYSE is based on TBDev,               |
// | originally by RedBeard of TorrentBits, extensively modified by           |
// | Gartenzwerg.                                                             |
// |                                                                          |
// | TBDevYSE is free software; you can redistribute it and/or modify         |
// | it under the terms of the GNU General Public License as published by     |
// | the Free Software Foundation; either version 2 of the License, or        |
// | (at your option) any later version.                                      |
// |                                                                          |
// | TBDevYSE is distributed in the hope that it will be useful,              |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
// | GNU General Public License for more details.                             |
// |                                                                          |
// | You should have received a copy of the GNU General Public License        |
// | along with TBDevYSE; if not, write to the Free Software Foundation,      |
// | Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            |
// +--------------------------------------------------------------------------+
// |                                               Do not remove above lines! |
// +--------------------------------------------------------------------------+
*/

require "include/bittorrent.php";

dbconn(false);
loggedinorreturn();

if (get_user_class() < UC_MODERATOR)
    die("Доступ запрещен");

// Удаление бана
$remove = intval($_GET['remove'] ?? 0);
if (is_valid_id($remove)) {
    $res = sql_query("SELECT first, last FROM bans WHERE id=$remove") or sqlerr(__FILE__, __LINE__);
    if ($res && mysqli_num_rows($res) > 0) {
        $ip = mysqli_fetch_assoc($res);
        $first = long2ip($ip["first"]);
        $last = long2ip($ip["last"]);
        sql_query("DELETE FROM bans WHERE id=$remove") or sqlerr(__FILE__, __LINE__);
        write_log("Блокировка IP #$remove (" . ($first == $last ? $first : "диапазон от $first до $last") . ") была удалена пользователем " . $CURUSER['username'] . ".");
    }
}

// Функция проверки IP-адреса
function is_good_ip($ip_addr) {
    if (filter_var($ip_addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode(".", $ip_addr);
        foreach ($parts as $ip_parts) {
            if (intval($ip_parts) > 255 || intval($ip_parts) < 0)
                return false;
        }
        return true;
    }
    return false;
}

// Добавление нового бана (только для администраторов)
if ($_SERVER["REQUEST_METHOD"] == "POST" && get_user_class() >= UC_ADMINISTRATOR) {
    $first = trim($_POST["first"] ?? '');
    $last = trim($_POST["last"] ?? '');
    $comment = trim($_POST["comment"] ?? '');
    
    if (!$first || !$last || !$comment)
        stderr('Ошибка', 'Не все поля заполнены');
    
    if (!is_good_ip($first) || !is_good_ip($last))
        stderr('Ошибка', 'Некорректный IP-адрес');
    
    $first_long = ip2long($first);
    $last_long = ip2long($last);
    
    if ($first_long === false || $last_long === false)
        stderr('Ошибка', 'Некорректный IP-адрес');
    
    $comment = sqlesc(htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'));
    $added = sqlesc(get_date_time());
    $user_id = (int)$CURUSER['id'];
    
    sql_query("INSERT INTO bans (added, addedby, first, last, comment) VALUES($added, $user_id, $first_long, $last_long, $comment)") or sqlerr(__FILE__, __LINE__);
    write_log("IP-блокировка от " . long2ip($first_long) . " до " . long2ip($last_long) . " была добавлена пользователем " . $CURUSER['username'] . ".");
    header("Location: $DEFAULTBASEURL/bans.php");
    die;
}

// Получение списка банов
$res = sql_query("SELECT bans.*, users.username FROM bans LEFT JOIN users ON bans.addedby = users.id ORDER BY bans.added DESC") or sqlerr(__FILE__, __LINE__);

stdhead('Управление блокировками IP');

// Вывод существующих банов
if (mysqli_num_rows($res) == 0) {
    print("<p align=\"center\"><b>Блокировок не найдено</b></p>\n");
} else {
    begin_table();
    print("<tr><td class=\"colhead\" colspan=\"6\">Список заблокированных IP-адресов</td></tr>\n");
    print("<tr>
            <td class=\"colhead\">Дата</td>
            <td class=\"colhead\" align=\"left\">Начальный IP</td>
            <td class=\"colhead\" align=\"left\">Конечный IP</td>
            <td class=\"colhead\" align=\"left\">Кто добавил</td>
            <td class=\"colhead\" align=\"left\">Комментарий</td>
            <td class=\"colhead\">Действие</td>
           </tr>\n");

    while ($arr = mysqli_fetch_assoc($res)) {
        $first_ip = long2ip($arr["first"]);
        $last_ip = long2ip($arr["last"]);
        $addedby_id = (int)$arr["addedby"];
        $ban_id = (int)$arr["id"];
        
        print("<tr>
                <td class=\"row1\">{$arr['added']}</td>
                <td class=\"row1\" align=\"left\">$first_ip</td>
                <td class=\"row1\" align=\"left\">$last_ip</td>
                <td class=\"row1\" align=\"left\"><a href=\"userdetails.php?id=$addedby_id\">{$arr['username']}</a></td>
                <td class=\"row1\" align=\"left\">" . htmlspecialchars($arr["comment"], ENT_QUOTES, 'UTF-8') . "</td>
                <td class=\"row1\"><a href=\"bans.php?remove=$ban_id\">Удалить</a></td>
               </tr>\n");
    }
    end_table();
}

// Форма добавления нового бана (только для администраторов)
if (get_user_class() >= UC_ADMINISTRATOR) {
    print("<br />\n");
    print("<form method=\"post\" action=\"bans.php\">\n");
    begin_table();
    print("<tr><td class=\"colhead\" colspan=\"2\">Добавить новую блокировку IP</td></tr>");
    print("<tr><td class=\"rowhead\">Начальный IP</td><td class=\"row1\"><input type=\"text\" name=\"first\" size=\"40\" maxlength=\"15\"/></td></tr>\n");
    print("<tr><td class=\"rowhead\">Конечный IP</td><td class=\"row1\"><input type=\"text\" name=\"last\" size=\"40\" maxlength=\"15\"/></td></tr>\n");
    print("<tr><td class=\"rowhead\">Причина блокировки</td><td class=\"row1\"><input type=\"text\" name=\"comment\" size=\"40\" maxlength=\"255\"/></td></tr>\n");
    print("<tr><td class=\"row1\" align=\"center\" colspan=\"2\"><input type=\"submit\" value=\"Добавить блокировку\" class=\"btn\"/></td></tr>\n");
    end_table();
    print("</form>\n");
}

stdfoot();
?>