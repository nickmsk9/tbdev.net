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

require_once "include/bittorrent.php";
dbconn();
loggedinorreturn();

// Инициализация переменных
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$class = $_GET['class'] ?? '';
$letter = $_GET['letter'] ?? '';
$page = (int)($_GET['page'] ?? 1);

// Валидация класса
if ($class == '-' || !is_valid_user_class($class)) {
    $class = '';
}

// Построение запроса
$where_parts = ["status='confirmed'"];
$params = [];
$query_params = [];

// Обработка поиска
if ($search != '') {
    $where_parts[] = "username LIKE ?";
    $params[] = '%' . $search . '%';
    $query_params['search'] = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');
}

// Обработка фильтра по букве
if ($search == '' && !$class && $letter != '') {
    // Безопасная проверка буквы
    $letter = mb_strtolower($letter, 'UTF-8');
    $valid_letters = 'abcdefghijklmnopqrstuvwxyzабвгдеёжзийклмнопрстуфхцчшщъыьэюя';
    
    if (mb_strlen($letter, 'UTF-8') == 1 && mb_strpos($valid_letters, $letter, 0, 'UTF-8') !== false) {
        $where_parts[] = "username LIKE ?";
        $params[] = $letter . '%';
        $query_params['letter'] = htmlspecialchars($letter, ENT_QUOTES, 'UTF-8');
    } else {
        $letter = '';
    }
}

// Добавление фильтра по классу
if (is_valid_user_class($class)) {
    $where_parts[] = "class = ?";
    $params[] = $class;
    $query_params['class'] = $class;
}

// Сборка WHERE-условия
$where = implode(' AND ', $where_parts);

// Подготовка query string для ссылок
$q = '';
foreach ($query_params as $key => $value) {
    $q .= ($q ? '&amp;' : '') . $key . '=' . $value;
}

// Пагинация
$perpage = 100;

// Получение общего количества записей
$count_sql = "SELECT COUNT(*) FROM users WHERE $where";
$count_res = sql_query($count_sql, $params) or sqlerr(__FILE__, __LINE__);
$count_arr = mysqli_fetch_row($count_res);
$total = (int)$count_arr[0];
$pages = (int)ceil($total / $perpage);

// Корректировка номера страницы
$page = max(1, min($page, $pages));

// Вывод HTML
stdhead("Пользователи");

print("<h1>Пользователи</h1>\n");

// Форма поиска
print("<form method=\"get\" action=\"users.php\">\n");
print("Поиск: <input type=\"text\" size=\"30\" name=\"search\" value=\"" . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . "\">\n");
print("<select name=\"class\">\n");
print("<option value=\"-\">(Все уровни)</option>\n");

for ($i = 0; ; ++$i) {
    if ($c = get_user_class_name($i)) {
        $selected = (is_valid_user_class($class) && $class == $i) ? " selected" : "";
        print("<option value=\"$i\"$selected>" . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . "</option>\n");
    } else {
        break;
    }
}

print("</select>\n");
print("<input type=\"submit\" value=\"Вперед\">\n");
print("</form>\n");

// Буквы латинского алфавита
print("<p>\n");
for ($i = 97; $i < 123; ++$i) {
    $l = chr($i);
    $L = chr($i - 32);
    $class_active = ($l == $letter) ? ' active' : '';
    
    if ($l == $letter) {
        print("<b>$L</b>\n");
    } else {
        $url = "users.php?letter=" . urlencode($l);
        if ($class) $url .= "&amp;class=" . urlencode($class);
        if ($search) $url .= "&amp;search=" . urlencode($search);
        print("<a href=\"$url\"><b>$L</b></a>\n");
    }
}
print("</p>\n");

// Буквы русского алфавита
print("<p>\n");
$russian_letters = "абвгдеёжзийклмнопрстуфхцчшщъыьэюя";
$russian_uppercase_letters = "АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ";

for ($i = 0; $i < mb_strlen($russian_letters, 'UTF-8'); $i++) {
    $l = mb_substr($russian_letters, $i, 1, 'UTF-8');
    $L = mb_substr($russian_uppercase_letters, $i, 1, 'UTF-8');
    
    if ($l == $letter) {
        print("<b>$L</b>\n");
    } else {
        $url = "users.php?letter=" . urlencode($l);
        if ($class) $url .= "&amp;class=" . urlencode($class);
        if ($search) $url .= "&amp;search=" . urlencode($search);
        print("<a href=\"$url\"><b>$L</b></a>\n");
    }
}
print("</p>\n");

// Пагинация вверху
$pagemenu = '';
$browsemenu = '';

// Номера страниц
for ($i = 1; $i <= $pages; ++$i) {
    if ($i == $page) {
        $pagemenu .= "<b>$i</b>\n";
    } else {
        $url = "users.php?page=$i";
        if ($q) $url .= '&amp;' . $q;
        $pagemenu .= "<a href=\"$url\"><b>$i</b></a>\n";
    }
}

// Кнопки "Назад/Вперед"
if ($page == 1) {
    $browsemenu .= "<b>&lt;&lt; Пред</b>";
} else {
    $prev = $page - 1;
    $url = "users.php?page=$prev";
    if ($q) $url .= '&amp;' . $q;
    $browsemenu .= "<a href=\"$url\"><b>&lt;&lt; Пред</b></a>";
}

$browsemenu .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

if ($page == $pages || ($page * $perpage) >= $total) {
    $browsemenu .= "<b>След &gt;&gt;</b>";
} else {
    $next = $page + 1;
    $url = "users.php?page=$next";
    if ($q) $url .= '&amp;' . $q;
    $browsemenu .= "<a href=\"$url\"><b>След &gt;&gt;</b></a>";
}

print("<p>$browsemenu<br />$pagemenu</p>");

// Получение данных
$offset = ($page - 1) * $perpage;
$limit_sql = "LIMIT $offset, $perpage";
$data_sql = "SELECT u.*, c.name, c.flagpic 
             FROM users AS u 
             LEFT JOIN countries AS c ON c.id = u.country 
             WHERE $where 
             ORDER BY username 
             $limit_sql";

$res = sql_query($data_sql, $params) or sqlerr(__FILE__, __LINE__);
$num = mysqli_num_rows($res);

// Таблица с пользователями
if ($num > 0) {
    print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");
    print("<tr>
            <td class=\"colhead\" align=\"left\">Имя</td>
            <td class=\"colhead\">Зарегистрирован</td>
            <td class=\"colhead\">Последний вход</td>
            <td class=\"colhead\">Рейтинг</td>
            <td class=\"colhead\">Пол</td>
            <td class=\"colhead\" align=\"left\">Уровень</td>
            <td class=\"colhead\">Страна</td>
          </tr>\n");

    while ($arr = mysqli_fetch_assoc($res)) {
        // Обработка дат
        $added = ($arr['added'] == '0000-00-00 00:00:00') ? '-' : htmlspecialchars($arr['added'], ENT_QUOTES, 'UTF-8');
        $last_access = ($arr['last_access'] == '0000-00-00 00:00:00') ? '-' : htmlspecialchars($arr['last_access'], ENT_QUOTES, 'UTF-8');
        
        // Расчет рейтинга
        $ratio = "---";
        if ($arr["downloaded"] > 0) {
            $ratio_num = $arr["uploaded"] / $arr["downloaded"];
            $ratio = number_format(min($ratio_num, 100), 2);
            if ($ratio_num > 100) {
                $ratio = "100+";
            }
            $ratio = "<font color=\"" . get_ratio_color($ratio) . "\">$ratio</font>";
        } elseif ($arr["uploaded"] > 0) {
            $ratio = "∞";
        }
        
        // Пол
        $gender = "";
        if ($arr["gender"] == "1") {
            $gender = "<img src=\"" . $pic_base_url . "/male.gif\" alt=\"Мужской\" title=\"Мужской\">";
        } elseif ($arr["gender"] == "2") {
            $gender = "<img src=\"" . $pic_base_url . "/female.gif\" alt=\"Женский\" title=\"Женский\">";
        }
        
        // Страна
        $country = "<td align=\"center\">---</td>";
        if ($arr['country'] > 0 && !empty($arr['flagpic'])) {
            $country_name = htmlspecialchars($arr['name'] ?? '', ENT_QUOTES, 'UTF-8');
            $country = "<td style=\"padding: 0px\" align=\"center\">
                        <img src=\"pic/flag/{$arr['flagpic']}\" alt=\"$country_name\" title=\"$country_name\">
                        </td>";
        }
        
		
        // Имя пользователя с цветом класса
        $username = get_user_class_color($arr["class"], htmlspecialchars($arr["username"], ENT_QUOTES, 'UTF-8'));
		$donated = isset($arr["donated"]) ? (int)$arr["donated"] : 0;
		$donor_icon = ($donated > 0) ? "<img src=\"pic/star.gif\" border=\"0\" alt=\"Донатер\" title=\"Донатер\">" : "";

        // Класс
        $user_class = get_user_class_name($arr["class"]);
        $user_class_display = htmlspecialchars($user_class, ENT_QUOTES, 'UTF-8');
        
        print("<tr>
                <td align=\"left\">
                    <a href=\"userdetails.php?id={$arr['id']}\"><b>$username</b></a>$donor_icon
                </td>
                <td>$added</td>
                <td>$last_access</td>
                <td>$ratio</td>
                <td>$gender</td>
                <td align=\"left\">$user_class_display</td>
                $country
              </tr>\n");
    }
    print("</table>\n");
} else {
    print("<p>Пользователи не найдены.</p>\n");
}

// Пагинация внизу
print("<p>$pagemenu<br />$browsemenu</p>");

stdfoot();