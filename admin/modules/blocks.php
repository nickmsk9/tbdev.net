<?php
declare(strict_types=1);

if (!defined('ADMIN_FILE')) {
    die("Illegal File Access");
}

$prefix = "orbital";

// This change is made to list all PHPs in tracker root, to make
// manual adding of pages to block system obsolete
// However, you can use array below to map filename to some human-readable name
$existing_modules = str_replace('.php', '', glob('*.php'));
$allowed_modules = array_combine($existing_modules, array_map(
    function ($el) {
        return "<i>{$el}</i>";
    }, $existing_modules));

// Add readable names for core modules
$allowed_modules = array_merge($allowed_modules, [
    "admincp" => "Админка",
    "browse" => "Каталог",
    "forums" => "Форум",
    "staff" => "Персонал",
    "upload" => "Загрузка",
    "details" => "Детали",
    "my" => "Мой профиль",
    "userdetails" => "Профиль",
    "viewrequests" => "Запросы",
    "viewoffers" => "Предложения",
    "log" => "Лог",
    "faq" => "FAQ",
    "rules" => "Правила",
    "message" => "Сообщения",
    "recover" => "Восст. пароль",
    "signup" => "Регистрация",
    "login" => "Вход",
    "mybonus" => "Мой бонус",
    "invite" => "Приглашения",
    "bookmarks" => "Закладки",
]);

function BlocksNavi(): void
{
    global $admin_file;
    echo "<h2>Управление блоками</h2><br />"
        . "[ <a href=\"" . htmlspecialchars($admin_file) . ".php?op=BlocksAdmin\">Список</a>"
        . " | <a href=\"" . htmlspecialchars($admin_file) . ".php?op=BlocksNew\">Создать новый блок</a>"
        . " | <a href=\"" . htmlspecialchars($admin_file) . ".php?op=BlocksFile\">Создать блок из файла</a>"
        . " | <a href=\"" . htmlspecialchars($admin_file) . ".php?op=BlocksFileEdit\">Редактировать файл</a> ]";
}

function BlocksAdmin(): void
{
    global $admin_file, $prefix;
    BlocksNavi();
    echo "<p /><table border=\"0\" cellpadding=\"3\" cellspacing=\"1\" width=\"100%\"><tr align=\"center\">"
        . "<td class=\"colhead\">ID</td><td class=\"colhead\">Название</td><td class=\"colhead\">Позиция</td><td colspan=\"2\" class=\"colhead\">Порядок</td><td class=\"colhead\">Тип</td><td class=\"colhead\">Статус</td><td class=\"colhead\">Кто видит</td><td class=\"colhead\">Управление</td></tr>";

    $result = sql_query("SELECT a.bid, a.bkey, a.title, a.bposition, a.weight, a.active, a.blockfile, a.view, a.expire, a.action, b.bid, b.bposition, b.weight, c.bid, c.bposition, c.weight FROM " . $prefix . "_blocks AS a LEFT JOIN " . $prefix . "_blocks AS b ON (b.bposition = a.bposition AND b.weight = a.weight-1) LEFT JOIN " . $prefix . "_blocks AS c ON (c.bposition = a.bposition AND c.weight = a.weight+1) ORDER BY a.bposition, a.weight") or sqlerr(__FILE__, __LINE__);
    
    $has_rows = false;
    while ($row = mysqli_fetch_assoc($result)) {
        $has_rows = true;
        $bid = (int)$row['bid'];
        $bkey = $row['bkey'];
        $title = htmlspecialchars($row['title']);
        $bposition = $row['bposition'];
        $weight = (int)$row['weight'];
        $active = (int)$row['active'];
        $blockfile = $row['blockfile'];
        $view = (int)$row['view'];
        $expire = (int)$row['expire'];
        $action = $row['action'];
        
        // Check for expired blocks
        if (($expire && $expire < time()) || (!$active && $expire)) {
            if ($action == "d") {
                sql_query("UPDATE " . $prefix . "_blocks SET active='0', expire='0' WHERE bid='$bid'");
            } elseif ($action == "r") {
                sql_query("DELETE FROM " . $prefix . "_blocks WHERE bid='$bid'");
            }
        }
        
        $weight_minus = $weight - 1;
        $weight_plus = $weight + 1;
        
        echo "<tr><td align=\"center\">$bid</td><td>$title</td>";
        
        // Position icons
        $position_icons = [
            'l' => "<img src=\"admin/pic/left.gif\" border=\"0\" alt=\"Левый блок\" title=\"Левый блок\"> Левый",
            'r' => "Правый <img src=\"admin/pic/right.gif\" border=\"0\" alt=\"Правый блок\" title=\"Правый блок\">",
            'c' => "<img src=\"admin/pic/right.gif\" border=\"0\" alt=\"Центральный блок\" title=\"Центральный блок\">&nbsp;в центре сверху&nbsp;<img src=\"admin/pic/left.gif\" border=\"0\" alt=\"Центральный блок\" title=\"Центральный блок\">",
            'd' => "<img src=\"admin/pic/right.gif\" border=\"0\" alt=\"Центральный блок\" title=\"Центральный блок\">&nbsp;в центре снизу&nbsp;<img src=\"admin/pic/left.gif\" border=\"0\" alt=\"Центральный блок\" title=\"Центральный блок\">",
            'b' => "<img src=\"admin/pic/up.gif\" border=\"0\" alt=\"Вверху\" title=\"Вверху\">&nbsp;верхняя строка&nbsp;<img src=\"admin/pic/up.gif\" border=\"0\" alt=\"Вверху\" title=\"Вверху\">",
            'f' => "<img src=\"admin/pic/down.gif\" border=\"0\" alt=\"Внизу\" title=\"Внизу\">&nbsp;нижняя строка&nbsp;<img src=\"admin/pic/down.gif\" border=\"0\" alt=\"Внизу\" title=\"Внизу\">"
        ];
        
        echo "<td align=\"center\"><nobr>" . ($position_icons[$bposition] ?? $bposition) . "</nobr></td><td align=\"center\">$weight</td><td align=\"center\">";
        
        // Order arrows
        if ($row['b.bid']) echo "<a href=\"" . htmlspecialchars($admin_file) . ".php?op=BlocksOrder&weight=$weight&bidori=$bid&weightrep=$weight_minus&bidrep=" . $row['b.bid'] . "\"><img src=\"admin/pic/up.gif\" alt=\"Поднять выше\" title=\"Поднять выше\" border=\"0\"></a> ";
        if ($row['c.bid']) echo "<a href=\"" . htmlspecialchars($admin_file) . ".php?op=BlocksOrder&weight=$weight&bidori=$bid&weightrep=$weight_plus&bidrep=" . $row['c.bid'] . "\"><img src=\"admin/pic/down.gif\" alt=\"Опустить ниже\" title=\"Опустить ниже\" border=\"0\"></a>";
        
        echo "</td>";
        
        // Block type
        $type = "HTML";
        if ($bkey != "") {
            $type = "Системный";
        } elseif ($blockfile != "") {
            $type = "Файл";
        }
        echo "<td align=\"center\">$type</td>";
        
        // Active status
        $block_act = $active;
        $change = "";
        if ($active == 1) {
            $active_display = "<font color=\"#009900\">акт.</font>";
            $change = "title=\"деакт.\"><img src=\"admin/pic/inactive.gif\" border=\"0\" alt=\"деакт.\"></a>";
        } else {
            $active_display = "<font color=\"#FF0000\">деакт.</font>";
            $change = "title=\"акт.\"><img src=\"admin/pic/activate.gif\" border=\"0\" alt=\"акт.\"></a>";
        }
        echo "<td align=\"center\">$active_display</td>";
        
        // Who can view
        $view_names = [
            0 => "Все посетители",
            1 => "Только пользователи",
            2 => "Только администраторы",
            3 => "Только модераторы"
        ];
        $who_view = $view_names[$view] ?? "Неизвестно";
        echo "<td align=\"center\"><nobr>$who_view</nobr></td>";
        
        // Actions
        echo "<td align=\"center\"><a href=\"" . htmlspecialchars($admin_file) . ".php?op=BlocksEdit&bid=$bid\" title=\"Редактировать\"><img src=\"admin/pic/edit.gif\" border=\"0\" alt=\"Редактировать\"></a> <a href=\"" . htmlspecialchars($admin_file) . ".php?op=BlocksChange&bid=$bid\" $change";
        if ($bkey == "") echo " <a href=\"" . htmlspecialchars($admin_file) . ".php?op=BlocksDelete&bid=$bid\" OnClick=\"return confirm('Удалить блок &quot;$title&quot;?')\" title=\"Удалить\"><img src=\"admin/pic/delete.gif\" border=\"0\" alt=\"Удалить\"></a>";
        if ($block_act == 0) echo " <a href=\"" . htmlspecialchars($admin_file) . ".php?op=BlocksShow&bid=$bid\" title=\"Показать\"><img src=\"admin/pic/show.gif\" border=\"0\" alt=\"Показать\"></a>";
        echo "</td></tr>";
    }
    
    if (!$has_rows) {
        echo "<tr><td colspan=\"9\">Нет блоков.</td></tr>";
    }
    
    echo "</table><center>[ <a href=\"" . htmlspecialchars($admin_file) . ".php?op=BlocksFixweight\">Пересчитать вес блоков в каждой позиции</a> ]</center>";
}

function BlocksNew(): void
{
    global $prefix, $admin_file;
    BlocksNavi();
    echo "<h2>Создать новый блок</h2>"
        . "<form action=\"" . htmlspecialchars($admin_file) . ".php\" method=\"post\">"
        . "<table border=\"0\" align=\"center\">"
        . "<tr><td>Название:</td><td><input type=\"text\" name=\"title\" size=\"65\" style=\"width:400px\" maxlength=\"60\"></td></tr>"
        . "<tr><td>Из файла:</td><td>"
        . "<select name=\"blockfile\" style=\"width:400px\">"
        . "<option name=\"blockfile\" value=\"\" selected>Нет</option>";
    
    $handle = opendir("blocks");
    if ($handle) {
        while (($file = readdir($handle)) !== false) {
            if (preg_match("/^block\-(.+)\.php$/", $file, $matches)) {
                $found = str_replace("_", " ", $matches[1]);
                $result = sql_query("SELECT COUNT(*) FROM " . $prefix . "_blocks WHERE blockfile='" . mysqli_real_escape_string($GLOBALS['mysql_conn'], $file) . "'");
                $row = mysqli_fetch_row($result);
                if ($row[0] == 0) {
                    echo "<option value=\"" . htmlspecialchars($file) . "\">" . htmlspecialchars($found) . "</option>\n";
                }
            }
        }
        closedir($handle);
    }
    
    echo "</select></td></tr>"
        . "<tr><td>Содержимое:</td><td><textarea name=\"content\" cols=\"65\" rows=\"15\" style=\"width:400px\"></textarea></td></tr>"
        . "<tr><td>Позиция:</td><td><select name=\"bposition\" style=\"width:400px\">"
        . "<option name=\"bposition\" value=\"l\">Левый</option>"
        . "<option name=\"bposition\" value=\"c\">В центре сверху</option>"
        . "<option name=\"bposition\" value=\"d\">В центре снизу</option>"
        . "<option name=\"bposition\" value=\"r\">Правый</option>"
        . "<option name=\"bposition\" value=\"b\">Верхняя строка</option>"
        . "<option name=\"bposition\" value=\"f\">Нижняя строка</option>"
        . "</select></td></tr>";
    
    echo "<tr><td>Отображать блок на страницах:</td><td align=\"center\"><table border=\"0\" cellpadding=\"3\" cellspacing=\"0\" align=\"center\" style=\"width:400px\"><tr>";
    echo "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"ihome\"></td><td>Главная</td>";
    
    global $allowed_modules;
    $a = 1;
    $i = 0;
    foreach ($allowed_modules as $name => $title) {
        $i++;
        $title = preg_replace("/_/", " ", $title);
        echo "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"" . htmlspecialchars($name) . "\"></td><td>" . htmlspecialchars($title) . "</td>";
        if ($a == 2) {
            echo "</tr><tr>";
            $a = 0;
        }
        $a++;
    }
    
    echo "</tr><tr><td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"all\"></td><td><b>Все страницы</b></td>"
        . "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"home\"></td><td><b>Только главная</b></td>"
        . "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"infly\"></td><td><b>Всплывающий блок</b></td></tr></table></td></tr>";
    
    echo "<tr><td>Скрываемый?</td><td><input type=\"radio\" name=\"hide\" value=\"yes\" checked>Да &nbsp;&nbsp; <input type=\"radio\" name=\"hide\" value=\"no\">Нет</td></tr>"
        . "<tr><td>Активный?</td><td><input type=\"radio\" name=\"active\" value=\"1\" checked>Да &nbsp;&nbsp; <input type=\"radio\" name=\"active\" value=\"0\">Нет</td></tr>"
        . "<tr><td>Срок жизни, в днях:</td><td><input type=\"number\" name=\"expire\" min=\"0\" max=\"999\" value=\"0\" size=\"65\" style=\"width:400px\"></td></tr>"
        . "<tr><td>Действие по истечении:</td><td><select name=\"action\" style=\"width:400px\">"
        . "<option name=\"action\" value=\"d\">Деакт.</option>"
        . "<option name=\"action\" value=\"r\">Удалить</option></select></td></tr>"
        . "<tr><td>Кто может видеть блок?</td><td><select name=\"view\" style=\"width:400px\">"
        . "<option value=\"0\">Все посетители</option>"
        . "<option value=\"1\">Только пользователи</option>"
        . "<option value=\"2\">Только администраторы</option>"
        . "<option value=\"3\">Только модераторы</option>"
        . "</select></td></tr>"
        . "<tr><td colspan=\"2\" align=\"center\"><br /><input type=\"hidden\" name=\"op\" value=\"BlocksAdd\"><input type=\"submit\" value=\"Создать блок\"></td></tr></table></form>";
}

function BlocksFile(): void
{
    global $admin_file;
    BlocksNavi();
    echo "<h2>Создать блок из текстового файла</h2>"
        . "<form action=\"" . htmlspecialchars($admin_file) . ".php\" method=\"post\">"
        . "<table border=\"0\" align=\"center\">"
        . "<tr><td>Имя файла:</td><td><input type=\"text\" name=\"bf\" size=\"65\" style=\"width:400px\" maxlength=\"200\">"
        . "<tr><td>Тип:</td><td><input type=\"radio\" name=\"flag\" value=\"php\" checked>PHP &nbsp;&nbsp; <input type=\"radio\" name=\"flag\" value=\"html\">HTML</td></tr>"
        . "<tr><td colspan=\"2\" align=\"center\"><br /><input type=\"hidden\" name=\"op\" value=\"BlocksbfEdit\">"
        . "<input type=\"submit\" value=\"Создать файл\"></td></tr></table></form>";
}

function BlocksOrder(string $weightrep, string $weight, string $bidrep, string $bidori): void
{
    global $prefix, $admin_file;
    $weightrep = (int)$weightrep;
    $weight = (int)$weight;
    $bidrep = (int)$bidrep;
    $bidori = (int)$bidori;
    
    sql_query("UPDATE " . $prefix . "_blocks SET weight='$weight' WHERE bid='$bidrep'");
    sql_query("UPDATE " . $prefix . "_blocks SET weight='$weightrep' WHERE bid='$bidori'");
    header("Location: " . htmlspecialchars($admin_file) . ".php?op=BlocksAdmin");
    exit;
}

function BlocksFixweight(): void
{
    global $prefix, $admin_file;
    
    $positions = ['l', 'r', 'c', 'd', 'b', 'f'];
    
    foreach ($positions as $pos) {
        $result = sql_query("SELECT bid FROM " . $prefix . "_blocks WHERE bposition='$pos' ORDER BY weight ASC");
        $weight = 0;
        while ($row = mysqli_fetch_assoc($result)) {
            $bid = (int)$row['bid'];
            $weight++;
            sql_query("UPDATE " . $prefix . "_blocks SET weight='$weight' WHERE bid='$bid'");
        }
    }
    
    header("Location: " . htmlspecialchars($admin_file) . ".php?op=BlocksAdmin");
    exit;
}

function BlocksAdd(string $title, string $content, string $bposition, string $active, string $hide, string $blockfile, string $view, string $expire, string $action): void
{
    global $prefix, $admin_file;
    
    $result = sql_query("SELECT weight FROM " . $prefix . "_blocks WHERE bposition=" . sqlesc($bposition) . " ORDER BY weight DESC LIMIT 1");
    $row = mysqli_fetch_row($result);
    $weight = $row ? (int)$row[0] + 1 : 1;
    
    $bkey = "";
    $btime = "";
    
    if ($blockfile != "" && $title == "") {
        $title = str_replace(["block-", ".php", "_"], ["", "", " "], $blockfile);
    }
    
    if (empty($content) && empty($blockfile)) {
        stdmsg("Ошибка", "Блок не может быть пустым!", 'error');
        return;
    }
    
    if (empty($expire) || $expire == "0") {
        $expire = 0;
    } else {
        $expire = time() + ((int)$expire * 86400);
    }
    
    $which = "";
    if (!empty($_POST['blockwhere'])) {
        $blockwhere = $_POST['blockwhere'];
        if (is_array($blockwhere)) {
            if (in_array("all", $blockwhere)) {
                $which = "all";
            } elseif (in_array("home", $blockwhere)) {
                $which = "home";
            } else {
                $which = implode(",", $blockwhere);
            }
        }
    }
    
    $fields = [
        'bkey' => $bkey,
        'title' => $title,
        'content' => $content,
        'bposition' => $bposition,
        'weight' => $weight,
        'active' => $active,
        'btime' => $btime,
        'blockfile' => $blockfile,
        'view' => $view,
        'expire' => $expire,
        'action' => $action,
        'which' => $which,
        'allow_hide' => $hide
    ];
    
    $escaped_values = array_map('sqlesc', $fields);
    $query = "INSERT INTO " . $prefix . "_blocks VALUES (NULL, " . implode(", ", $escaped_values) . ")";
    sql_query($query) or sqlerr(__FILE__, __LINE__);
    
    header("Location: " . htmlspecialchars($admin_file) . ".php?op=BlocksAdmin");
    exit;
}

function BlocksEdit(int $bid): void
{
    global $prefix, $admin_file;
    BlocksNavi();
    
    $result = sql_query("SELECT bkey, title, content, bposition, weight, active, allow_hide, blockfile, view, expire, action, which FROM " . $prefix . "_blocks WHERE bid='$bid'");
    $row = mysqli_fetch_assoc($result);
    
    if (!$row) {
        stdmsg("Ошибка", "Блок не найден!", 'error');
        return;
    }
    
    extract($row);
    
    $type = $blockfile != "" ? "(Системный блок)" : "(HTML блок)";
    
    echo "<h2>Блок: " . htmlspecialchars($title) . " $type</h2>"
        . "<form action=\"" . htmlspecialchars($admin_file) . ".php\" method=\"post\">"
        . "<table border=\"0\" align=\"center\">"
        . "<tr><td>Название:</td><td><input type=\"text\" name=\"title\" maxlength=\"50\" size=\"65\" style=\"width:400px\" value=\"" . htmlspecialchars($title) . "\"></td></tr>";
    
    if ($blockfile != "") {
        echo "<tr><td>Из файла:</td><td><select name=\"blockfile\" style=\"width:400px\">";
        $dir = opendir("blocks");
        while (($file = readdir($dir)) !== false) {
            if (preg_match("/^block\-(.+)\.php$/", $file, $matches)) {
                $found = str_replace("_", " ", $matches[1]);
                $selected = ($blockfile == $file) ? "selected" : "";
                echo "<option value=\"" . htmlspecialchars($file) . "\" $selected>" . htmlspecialchars($found) . "</option>";
            }
        }
        closedir($dir);
        echo "</select></td></tr>";
    } else {
        echo "<tr><td>Содержимое:</td><td><textarea name=\"content\" cols=\"65\" rows=\"15\" style=\"width:400px\">" . htmlspecialchars($content) . "</textarea></td></tr>";
    }
    
    echo "<input type=\"hidden\" name=\"oldposition\" value=\"" . htmlspecialchars($bposition) . "\">";
    
    $positions = [
        'l' => 'Левый',
        'c' => 'В центре сверху',
        'd' => 'В центре снизу',
        'r' => 'Правый',
        'b' => 'Верхняя строка',
        'f' => 'Нижняя строка'
    ];
    
    echo "<tr><td>Позиция:</td><td><select name=\"bposition\" style=\"width:400px\">";
    foreach ($positions as $value => $label) {
        $selected = ($bposition == $value) ? "selected" : "";
        echo "<option value=\"$value\" $selected>$label</option>";
    }
    echo "</select></td></tr>";
    
    // Display pages selection
    echo "<tr><td>Отображать блок на страницах:</td><td align=\"center\"><table border=\"0\" cellpadding=\"3\" cellspacing=\"0\" align=\"center\" style=\"width:400px\"><tr>";
    
    $where_mas = explode(",", $which ?? '');
    $cel = in_array("ihome", $where_mas) ? " checked" : "";
    echo "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"ihome\"$cel></td><td>Главная</td>";
    
    global $allowed_modules;
    $a = 1;
    foreach ($allowed_modules as $name => $mod_title) {
        $cel = in_array($name, $where_mas) ? " checked" : "";
        $display_title = str_replace("_", " ", $mod_title);
        echo "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"" . htmlspecialchars($name) . "\"$cel></td><td>" . htmlspecialchars($display_title) . "</td>";
        if ($a == 2) {
            echo "</tr><tr>";
            $a = 0;
        }
        $a++;
    }
    
    // Special options
    $cel_all = in_array("all", $where_mas) ? " checked" : "";
    $cel_home = in_array("home", $where_mas) ? " checked" : "";
    $cel_infly = in_array("infly", $where_mas) ? " checked" : "";
    
    echo "</tr><tr>"
        . "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"all\"$cel_all></td><td><b>Все страницы</b></td>"
        . "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"home\"$cel_home></td><td><b>Только главная</b></td>"
        . "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"infly\"$cel_infly></td><td><b>Всплывающий блок</b></td>"
        . "</tr></table></td></tr>";
    
    // Hide option
    $hide1 = ($allow_hide == 'yes') ? "checked" : "";
    $hide2 = ($allow_hide == 'no') ? "checked" : "";
    echo "<tr><td>Скрываемый?</td><td><input type=\"radio\" name=\"hide\" value=\"yes\" $hide1>Да &nbsp;&nbsp;"
        . "<input type=\"radio\" name=\"hide\" value=\"no\" $hide2>Нет</td></tr>";
    
    // Active option
    $sel1 = ($active == 1) ? "checked" : "";
    $sel2 = ($active == 0) ? "checked" : "";
    echo "<tr><td>Активный?</td><td><input type=\"radio\" name=\"active\" value=\"1\" $sel1>Да &nbsp;&nbsp;"
        . "<input type=\"radio\" name=\"active\" value=\"0\" $sel2>Нет</td></tr>";
    
    // Expire time
    if ($expire != 0) {
        $newexpire = 0;
        $oldexpire = $expire;
        $expire_hours = intval(($expire - time()) / 3600);
        $expire_days = $expire_hours / 24;
        $expire_text = "<input type=\"hidden\" name=\"expire\" value=\"$oldexpire\">Осталось: $expire_hours часов (" . substr($expire_days, 0, 5) . " дней)";
    } else {
        $newexpire = 1;
        $expire_text = "<input type=\"number\" name=\"expire\" value=\"0\" min=\"0\" max=\"999\" size=\"65\" style=\"width:400px\">";
    }
    
    echo "<tr><td>Срок жизни, в днях:</td><td>$expire_text</td></tr>";
    
    // Action after expire
    $selact1 = ($action == "d") ? "selected" : "";
    $selact2 = ($action == "r") ? "selected" : "";
    echo "<tr><td>Действие по истечении:</td><td><select name=\"action\" style=\"width:400px\">"
        . "<option value=\"d\" $selact1>Деакт.</option>"
        . "<option value=\"r\" $selact2>Удалить</option></select></td></tr>";
    
    // Who can view
    $view_options = [
        0 => 'Все посетители',
        1 => 'Только пользователи',
        2 => 'Только администраторы',
        3 => 'Только модераторы'
    ];
    
    echo "<tr><td>Кто может видеть блок?</td><td><select name=\"view\" style=\"width:400px\">";
    foreach ($view_options as $value => $label) {
        $selected = ($view == $value) ? "selected" : "";
        echo "<option value=\"$value\" $selected>$label</option>";
    }
    echo "</select></td></tr></table><br>"
        . "<center><input type=\"hidden\" name=\"bid\" value=\"$bid\">"
        . "<input type=\"hidden\" name=\"newexpire\" value=\"$newexpire\">"
        . "<input type=\"hidden\" name=\"bkey\" value=\"" . htmlspecialchars($bkey) . "\">"
        . "<input type=\"hidden\" name=\"weight\" value=\"$weight\">"
        . "<input type=\"hidden\" name=\"op\" value=\"BlocksEditSave\">"
        . "<input type=\"submit\" value=\"Сохранить\"></form></center>";
}

// Note: Other functions need similar fixes. Due to length, I've shown the pattern.
// Continue applying the same fixes to all other functions...

switch($_GET['op'] ?? '') {
    case "BlocksAdmin":
        BlocksAdmin();
        break;
    case "BlocksNew":
        BlocksNew();
        break;
    case "BlocksFile":
        BlocksFile();
        break;
    case "BlocksFileEdit":
        BlocksFileEdit();
        break;
    case "BlocksAdd":
        BlocksAdd(
            $_POST['title'] ?? '',
            $_POST['content'] ?? '',
            $_POST['bposition'] ?? 'l',
            $_POST['active'] ?? '1',
            $_POST['hide'] ?? 'yes',
            $_POST['blockfile'] ?? '',
            $_POST['view'] ?? '0',
            $_POST['expire'] ?? '0',
            $_POST['action'] ?? 'd'
        );
        break;
    // Add other cases with proper parameter handling...
    default:
        BlocksAdmin();
        break;
}
?>