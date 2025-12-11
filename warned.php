<?php

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

// Проверка прав доступа
if (get_user_class() < UC_MODERATOR) {
    stderr($tracker_lang['error'] ?? 'Ошибка', "Отказано в доступе.");
}

// Заголовок
stdhead("Предупрежденные пользователи");

// Подсчет предупрежденных пользователей
$warned_count = number_format(get_row_count("users", "WHERE warned='yes'"));
begin_frame("Предупрежденные пользователи: ($warned_count)", true);

// Получение данных
$res = sql_query("SELECT * FROM users WHERE warned='yes' AND enabled='yes' ORDER BY (uploaded/downloaded)") or sqlerr(__FILE__, __LINE__);
$num = mysqli_num_rows($res);

// Форма для действий
print('<table border="1" width="675" cellspacing="0" cellpadding="2"><form action="nowarn.php" method="post">' . PHP_EOL);
print('<tr align="center">
    <td class="colhead" width="90">Пользователь</td>
    <td class="colhead" width="70">Зарегистрирован</td>
    <td class="colhead" width="75">Последний визит</td>
    <td class="colhead" width="75">Класс</td>
    <td class="colhead" width="70">Закачал</td>
    <td class="colhead" width="70">Раздал</td>
    <td class="colhead" width="45">Рейтинг</td>
    <td class="colhead" width="125">Окончание</td>
    <td class="colhead" width="65">Снять</td>
    <td class="colhead" width="65">Отключить</td>
</tr>' . PHP_EOL);

// Обработка результатов
for ($i = 0; $i < $num; $i++) {
    $arr = mysqli_fetch_assoc($res);
    
    // Обработка дат
    $added = ($arr['added'] == '0000-00-00 00:00:00') ? '-' : substr($arr['added'], 0, 10);
    $last_access = ($arr['last_access'] == '0000-00-00 00:00:00') ? '-' : substr($arr['last_access'], 0, 10);
    $warned_until = htmlspecialchars($arr['warneduntil'] ?? '-');
    
    // Расчет рейтинга
    if ((float)$arr["downloaded"] != 0) {
        $ratio = (float)$arr["uploaded"] / (float)$arr["downloaded"];
        $ratio_formatted = number_format($ratio, 3);
        $ratio_html = '<font color="' . get_ratio_color($ratio) . '">' . $ratio_formatted . '</font>';
    } else {
        $ratio_html = '---';
    }
    
    // Форматирование размеров
    $uploaded = mksize((float)$arr["uploaded"]);
    $downloaded = mksize((float)$arr["downloaded"]);
    $uploaded_formatted = str_replace(" ", "<br />", $uploaded);
    $downloaded_formatted = str_replace(" ", "<br />", $downloaded);
    
    // Класс пользователя
    $class = get_user_class_name($arr["class"]);
    
    // Дополнительные метки (донор и т.д.)
    $user_extra = '';
    if (isset($arr["donor"]) && $arr["donor"] == "yes") {
        $user_extra = '<img src="pic/star.gif" border="0" alt="Donor">';
    }
    
    $user_id = (int)$arr['id'];
    $username = htmlspecialchars($arr['username']);
    
    // Вывод строки таблицы
    print('<tr>
        <td align="left">
            <a href="userdetails.php?id=' . $user_id . '"><b>' . $username . '</b></a>' . $user_extra . '
        </td>
        <td align="center">' . $added . '</td>
        <td align="center">' . $last_access . '</td>
        <td align="center">' . htmlspecialchars($class) . '</td>
        <td align="center">' . $downloaded_formatted . '</td>
        <td align="center">' . $uploaded_formatted . '</td>
        <td align="center">' . $ratio_html . '</td>
        <td align="center">' . $warned_until . '</td>
        <td bgcolor="#008000" align="center">
            <input type="checkbox" name="usernw[]" value="' . $user_id . '">
        </td>
        <td bgcolor="#FF0000" align="center">
            <input type="checkbox" name="desact[]" value="' . $user_id . '">
        </td>
    </tr>' . PHP_EOL);
}

// Кнопка применения для администраторов
if (get_user_class() >= UC_ADMINISTRATOR) {
    print('<tr>
        <td colspan="10" align="right">
            <input type="submit" name="submit" value="Применить">
        </td>
    </tr>' . PHP_EOL);
    print('<input type="hidden" name="nowarned" value="nowarned">' . PHP_EOL);
}

// Закрываем форму и таблицу
print('</form></table>' . PHP_EOL);

// Пагинация (если есть)
if (isset($pagemenu) || isset($browsemenu)) {
    print('<p>');
    if (isset($pagemenu)) {
        print($pagemenu);
    }
    if (isset($browsemenu)) {
        print('<br />' . $browsemenu);
    }
    print('</p>' . PHP_EOL);
}

end_frame();
stdfoot();
?>