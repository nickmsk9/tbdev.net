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
dbconn();
loggedinorreturn();
stdhead("Персонал сайта");

begin_main_frame();
begin_frame("");

$act = $_GET['act'] ?? null;
$pic_base_url = $GLOBALS['pic_base_url'] ?? '';

if (!$act) {
    // Get current datetime for online status check (5 minutes ago)
    $dt = gmtime() - 300;
    $dt_escaped = sqlesc(get_date_time($dt));
    
    // Search User Database for Moderators and above and display in alphabetical order
    $res = sql_query("SELECT * FROM users WHERE class >= " . UC_UPLOADER . " AND status = 'confirmed' ORDER BY username") 
        or sqlerr(__FILE__, __LINE__);

    $staff_table = [];
    $col = [];

    while ($arr = mysqli_fetch_assoc($res)) {
        $class = $arr['class'];
        
        // Initialize arrays if not set
        if (!isset($staff_table[$class])) {
            $staff_table[$class] = '';
            $col[$class] = 0;
        }

        // Determine online status
        $last_access_time = strtotime($arr['last_access']);
        $online_status = ($last_access_time > $dt) 
            ? "<img src=\"{$pic_base_url}/button_online.gif\" border=\"0\" alt=\"online\">"
            : "<img src=\"{$pic_base_url}/button_offline.gif\" border=\"0\" alt=\"offline\">";

        // Get colored username
        $colored_username = get_user_class_color($class, $arr['username']);
        
        // Build staff row
        $staff_table[$class] .= "<td class=\"embedded\">"
            . "<a class=\"altlink\" href=\"userdetails.php?id={$arr['id']}\">"
            . "<b>{$colored_username}</b></a></td>"
            . "<td class=\"embedded\">{$online_status}</td>"
            . "<td class=\"embedded\">"
            . "<a href=\"message.php?action=sendmessage&amp;receiver={$arr['id']}\">"
            . "<img src=\"{$pic_base_url}/button_pm.gif\" border=\"0\"></a></td>";

        // Show 3 staff per row, separated by an empty column
        ++$col[$class];
        if ($col[$class] <= 2) {
            $staff_table[$class] .= "<td class=\"embedded\">&nbsp;</td>";
        } else {
            $staff_table[$class] .= "</tr><tr height=\"15\">";
            $col[$class] = 0;
        }
    }

    // Begin frame for staff display
    begin_frame("Персонал сайта");
?>
<table width="100%" cellspacing="0">
    <tr>
        <td class="embedded" colspan="11">
            Помни, что всё что нужно знать о сайте есть в правилах и FAQ, обязательно ознакомься с этими документами.
        </td>
    </tr>
    <!-- Define table column widths -->
    <tr>
        <td class="embedded" width="125">&nbsp;</td>
        <td class="embedded" width="25">&nbsp;</td>
        <td class="embedded" width="35">&nbsp;</td>
        <td class="embedded" width="85">&nbsp;</td>
        <td class="embedded" width="125">&nbsp;</td>
        <td class="embedded" width="25">&nbsp;</td>
        <td class="embedded" width="35">&nbsp;</td>
        <td class="embedded" width="85">&nbsp;</td>
        <td class="embedded" width="125">&nbsp;</td>
        <td class="embedded" width="25">&nbsp;</td>
        <td class="embedded" width="35">&nbsp;</td>
    </tr>
    <tr>
        <td class="embedded" colspan="11"><b>Высшее руководство</b></td>
    </tr>
    <tr>
        <td class="embedded" colspan="11"><hr color="#4040c0" size="1"></td>
    </tr>
    <tr height="15">
        <?php echo $staff_table[UC_SYSOP] ?? ''; ?>
    </tr>
    <tr>
        <td class="embedded" colspan="11">&nbsp;</td>
    </tr>
    <tr>
        <td class="embedded" colspan="11"><b>Администраторы</b></td>
    </tr>
    <tr>
        <td class="embedded" colspan="11"><hr color="#4040c0" size="1"></td>
    </tr>
    <tr height="15">
        <?php echo $staff_table[UC_ADMINISTRATOR] ?? ''; ?>
    </tr>
    <tr>
        <td class="embedded" colspan="11">&nbsp;</td>
    </tr>
    <tr>
        <td class="embedded" colspan="11"><b>Модераторы</b></td>
    </tr>
    <tr>
        <td class="embedded" colspan="11"><hr color="#4040c0" size="1"></td>
    </tr>
    <tr height="15">
        <?php echo $staff_table[UC_MODERATOR] ?? ''; ?>
    </tr>
    <tr>
        <td class="embedded" colspan="11">&nbsp;</td>
    </tr>
    <tr>
        <td class="embedded" colspan="11"><b>Загрузчики</b></td>
    </tr>
    <tr>
        <td class="embedded" colspan="11"><hr color="#4040c0" size="1"></td>
    </tr>
    <tr height="15">
        <?php echo $staff_table[UC_UPLOADER] ?? ''; ?>
    </tr>
</table>
<?php
    end_frame();
}

// Sysop tools
if (get_user_class() >= UC_SYSOP) { 
    begin_frame("Системные инструменты<font color=\"#FF0000\"> - только для администраторов.</font>"); 
?>
<table width="100%" cellspacing="10" align="center">
    <tr>
        <td class="embedded"><form method="get" action="staffmess.php"><input type="submit" value="Написать всем" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="category.php"><input type="submit" value="Категории" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="delacct.php"><input type="submit" value="Удалить аккаунт" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="bans.php"><input type="submit" value="Бан" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="status.php"><input type="submit" value="Статус трекера" style="height: 20px; width: 100px" disabled></form></td>
    </tr>
</table>
<?php 
    end_frame();
}

// Administrator tools
if (get_user_class() >= UC_ADMINISTRATOR) { 
    begin_frame("Инструменты администратора<font color=\"#009900\"> - только для администраторов.</font>"); 
?>
<table width="100%" cellspacing="10" align="center">
    <tr>
        <td class="embedded"><form method="get" action="unco.php"><input type="submit" value="Неподкл. юзеры" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="delacctadmin.php"><input type="submit" value="Удалить аккаунт" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="agentban.php"><input type="submit" value="Бан клиентов" style="height: 20px; width: 100px" disabled></form></td>
    </tr>
    <tr>
        <td class="embedded"><form method="get" action="topten.php"><input type="submit" value="Top 10" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="findnotconnectable.php"><input type="submit" value="Юзеры без NAT" style="height: 20px; width: 100px"></form></td>
    </tr>
</table>
<?php 
    end_frame();
}

// Moderator tools
if (get_user_class() >= UC_MODERATOR) { 
    begin_frame("Инструменты модератора - <font color=\"#004E98\">только для модераторов.</font>"); 
?>
<table width="100%" cellspacing="3">
    <tr>
        <td class="embedded"><a class="altlink" href="staff.php?act=users">Пользователи с соотношением 0.20</a></td>
        <td class="embedded">Просмотр списка пользователей с соотношением ниже 0.20</td>
    </tr>
    <tr>
        <td class="embedded"><a class="altlink" href="staff.php?act=banned">Заблокированные пользователи</a></td>
        <td class="embedded">Просмотр списка заблокированных пользователей</td>
    </tr>
    <tr>
        <td class="embedded"><a class="altlink" href="staff.php?act=last">Последние пользователи</a></td>
        <td class="embedded">100 последних зарегистрированных пользователей</td>
    </tr>
    <tr>
        <td class="embedded"><a class="altlink" href="log.php">Журнал событий</a></td>
        <td class="embedded">Просмотр лога регистраций/входов/выходов</td>
    </tr>
</table>
<?php 
    end_frame(); 
?>
<br />
<?php 
    begin_frame("Управление и статистика - <font color=\"#004E98\">только для модераторов.</font>"); 
?>
<br />
<table width="100%" cellspacing="10" align="center">
    <tr>
        <td class="embedded"><form method="get" action="warned.php"><input type="submit" value="Предупр. юзеры" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="adduser.php"><input type="submit" value="Добавить юзера" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="makepoll.php"><input type="submit" value="Создать опрос" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="recover.php"><input type="submit" value="Восст. логин" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="uploaders.php"><input type="submit" value="Загрузчики" style="height: 20px; width: 100px"></form></td>
    </tr>
    <tr>
        <td class="embedded"><form method="get" action="polloverview.php"><input type="submit" value="Обзор опросов" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="users.php"><input type="submit" value="Поиск юзера" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="tags.php"><input type="submit" value="Теги" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="smilies.php"><input type="submit" value="Смайлы" style="height: 20px; width: 100px"></form></td>
    </tr>
    <tr>
        <td class="embedded"><form method="get" action="stats.php"><input type="submit" value="Статистика" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="testip.php"><input type="submit" value="Проверить IP" style="height: 20px; width: 100px"></form></td>
        <td class="embedded"><form method="get" action="reports.php"><input type="submit" value="Жалобы" style="height: 20px; width: 100px" disabled></form></td>
        <td class="embedded"><form method="get" action="ipcheck.php"><input type="submit" value="Проверить IP" style="height: 20px; width: 100px"></form></td>
    </tr>
</table>
<br />
<?php 
    end_frame(); 
?>

<?php 
    begin_frame("Поиск пользователей - <font color=\"#004E98\">только для модераторов.</font>"); 
?>
<table width="100%" cellspacing="3">
    <tr>
        <td class="embedded">
            <form method="get" action="users.php">
                Логин: <input type="text" size="30" name="search">
                <select name="class">
                    <option value='-'>(любой)</option>
                    <option value="0">Пользователь</option>
                    <option value="1">Зарегистрированный пользователь</option>
                    <option value="2">VIP</option>
                    <option value="3">Загрузчик</option>
                    <option value="4">Модератор</option>
                    <option value="5">Администратор</option>
                    <option value="6">Системный администратор</option>
                </select>
                <input type="submit" value="Искать">
            </form>
        </td>
    </tr>
    <tr><td class="embedded"><li><a href="usersearch.php">Расширенный поиск</a></li></td></tr>
</table>
<?php 
    end_frame(); 
?>
<br />
<?php 
    // Users with ratio <= 0.20
    if ($act == "users") {
        begin_frame("Пользователи с соотношением 0.20");
        
        echo '<table width="100%" border="0" align="center" cellpadding="2" cellspacing="0">';
        echo "<tr><td class=\"colhead\" align=\"left\">Пользователь</td><td class=\"colhead\">Соотношение</td><td class=\"colhead\">IP</td><td class=\"colhead\">Зарегистрирован</td><td class=\"colhead\">Последний визит</td><td class=\"colhead\">Скачано</td><td class=\"colhead\">Загружено</td></tr>";
        
        $result = sql_query("SELECT * FROM users WHERE uploaded / downloaded <= 0.20 AND enabled = 'yes' ORDER BY downloaded DESC");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                if ($row["uploaded"] == "0" || $row["downloaded"] == "0") {
                    $ratio = "inf";
                } else {
                    $ratio = number_format($row["uploaded"] / $row["downloaded"], 3);
                }
                $ratio_color = get_ratio_color($ratio);
                $ratio_display = ($ratio == "inf") ? $ratio : "<font color=\"{$ratio_color}\">{$ratio}</font>";
                
                echo "<tr><td><a href=\"userdetails.php?id={$row['id']}\"><b>{$row['username']}</b></a></td>"
                    . "<td><strong>{$ratio_display}</strong></td>"
                    . "<td>{$row['ip']}</td>"
                    . "<td>{$row['added']}</td>"
                    . "<td>{$row['last_access']}</td>"
                    . "<td>" . mksize($row['downloaded']) . "</td>"
                    . "<td>" . mksize($row['uploaded']) . "</td></tr>";
            }
        } else {
            echo "<tr><td colspan=\"7\">Извините, записи не найдены!</td></tr>";
        }
        echo "</table>";
        end_frame();
    }
    
    // Last registered users
    if ($act == "last") {
        begin_frame("Последние пользователи");
        
        echo '<table width="100%" border="0" align="center" cellpadding="2" cellspacing="0">';
        echo "<tr><td class=\"colhead\" align=\"left\">Пользователь</td><td class=\"colhead\">Соотношение</td><td class=\"colhead\">IP</td><td class=\"colhead\">Зарегистрирован</td><td class=\"colhead\">Последний визит</td><td class=\"colhead\">Скачано</td><td class=\"colhead\">Загружено</td></tr>";
        
        $result = sql_query("SELECT * FROM users WHERE enabled = 'yes' AND status = 'confirmed' ORDER BY added DESC LIMIT 100");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                if ($row["uploaded"] == "0" || $row["downloaded"] == "0") {
                    $ratio = "inf";
                } else {
                    $ratio = number_format($row["uploaded"] / $row["downloaded"], 3);
                }
                $ratio_color = get_ratio_color($ratio);
                $ratio_display = ($ratio == "inf") ? $ratio : "<font color=\"{$ratio_color}\">{$ratio}</font>";
                
                echo "<tr><td><a href=\"userdetails.php?id={$row['id']}\"><b>{$row['username']}</b></a></td>"
                    . "<td><strong>{$ratio_display}</strong></td>"
                    . "<td>{$row['ip']}</td>"
                    . "<td>{$row['added']}</td>"
                    . "<td>{$row['last_access']}</td>"
                    . "<td>" . mksize($row['downloaded']) . "</td>"
                    . "<td>" . mksize($row['uploaded']) . "</td></tr>";
            }
        } else {
            echo "<tr><td colspan=\"7\">Извините, записи не найдены!</td></tr>";
        }
        echo "</table>";
        end_frame();
    }
    
    // Banned users
    if ($act == "banned") {
        begin_frame("Заблокированные пользователи");
        
        echo '<table width="100%" border="0" align="center" cellpadding="2" cellspacing="0">';
        echo "<tr><td class=\"colhead\" align=\"left\">Пользователь</td><td class=\"colhead\">Соотношение</td><td class=\"colhead\">IP</td><td class=\"colhead\">Зарегистрирован</td><td class=\"colhead\">Последний визит</td><td class=\"colhead\">Скачано</td><td class=\"colhead\">Загружено</td></tr>";
        
        $result = sql_query("SELECT * FROM users WHERE enabled = 'no' ORDER BY last_access DESC");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                if ($row["uploaded"] == "0" || $row["downloaded"] == "0") {
                    $ratio = "inf";
                } else {
                    $ratio = number_format($row["uploaded"] / $row["downloaded"], 3);
                }
                $ratio_color = get_ratio_color($ratio);
                $ratio_display = ($ratio == "inf") ? $ratio : "<font color=\"{$ratio_color}\">{$ratio}</font>";
                
                echo "<tr><td><a href=\"userdetails.php?id={$row['id']}\"><b>{$row['username']}</b></a></td>"
                    . "<td><strong>{$ratio_display}</strong></td>"
                    . "<td>{$row['ip']}</td>"
                    . "<td>{$row['added']}</td>"
                    . "<td>{$row['last_access']}</td>"
                    . "<td>" . mksize($row['downloaded']) . "</td>"
                    . "<td>" . mksize($row['uploaded']) . "</td></tr>";
            }
        } else {
            echo "<tr><td colspan=\"7\">Извините, записи не найдены!</td></tr>";
        }
        echo "</table>";
        end_frame();
    }
}

// Firstline supporters for regular users
if (get_user_class() >= UC_USER && !$act) {
    $dt = gmtime() - 180;
    $dt_escaped = sqlesc(get_date_time($dt));
    
    // LIST ALL FIRSTLINE SUPPORTERS
    $firstline = '';
    $res = sql_query("SELECT * FROM users WHERE support='yes' AND status='confirmed' ORDER BY username LIMIT 10") 
        or sqlerr(__FILE__, __LINE__);
    
    while ($arr = mysqli_fetch_assoc($res)) {
        $land = sql_query("SELECT name, flagpic FROM countries WHERE id={$arr['country']}") 
            or sqlerr(__FILE__, __LINE__);
        $arr2 = mysqli_fetch_assoc($land);
        
        $last_access_time = strtotime($arr['last_access']);
        $online_status = ($last_access_time > $dt) 
            ? "<img src=\"{$pic_base_url}/button_online.gif\" border=\"0\" alt=\"online\">"
            : "<img src=\"{$pic_base_url}/button_offline.gif\" border=\"0\" alt=\"offline\">";
        
        $firstline .= "<tr height=\"15\">"
            . "<td class=\"embedded\"><a class=\"altlink\" href=\"userdetails.php?id={$arr['id']}\">{$arr['username']}</a></td>"
            . "<td class=\"embedded\">{$online_status}</td>"
            . "<td class=\"embedded\"><a href=\"message.php?action=sendmessage&amp;receiver={$arr['id']}\">"
            . "<img src=\"{$pic_base_url}/button_pm.gif\" border=\"0\"></a></td>"
            . "<td class=\"embedded\"><img src=\"{$pic_base_url}/flag/{$arr2['flagpic']}\" title=\"{$arr2['name']}\" border=\"0\" width=\"19\" height=\"12\"></td>"
            . "<td class=\"embedded\">{$arr['supportfor']}</td></tr>\n";
    }

    begin_frame("Линия первой поддержки");
?>
<table width="100%" cellspacing="0">
    <tr>
        <td class="embedded" colspan="5">
            Здесь находится список лиц первой линии поддержки. Если у вас возникли проблемы, сначала посмотрите FAQ и правила.
            Только потом пишите им.<br /><br /><br />
        </td>
    </tr>
    <tr>
        <td class="embedded" width="30"><b>Пользователь&nbsp;</b></td>
        <td class="embedded" width="5"><b>Статус&nbsp;</b></td>
        <td class="embedded" width="5"><b>Сообщение&nbsp;</b></td>
        <td class="embedded" width="85"><b>Страна&nbsp;</b></td>
        <td class="embedded" width="200"><b>Поддерживает&nbsp;</b></td>
    </tr>
    <tr>
        <td class="embedded" colspan="5"><hr color="#4040c0" size="1"></td>
    </tr>
    <?php echo $firstline; ?>
</table>
<?php
    end_frame();
}

end_frame();
end_main_frame();
stdfoot();
?>