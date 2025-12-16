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

require_once 'include/bittorrent.php';
dbconn(false);
loggedinorreturn();

// Удаление записей старше недели
$secs = 7 * 86400;
$current_time = gmtime();

// Очистка старых записей
sql_query("DELETE FROM sitelog WHERE $current_time - UNIX_TIMESTAMP(added) > $secs") or sqlerr(__FILE__, __LINE__);

// Определение типа лога
$type = isset($_GET['type']) ? htmlspecialchars_uni((string)$_GET['type']) : 'tracker';
$allowed_types = ['tracker', 'bans', 'release', 'exchange', 'torrent', 'error'];

if (!in_array($type, $allowed_types)) {
    $type = 'tracker';
}

// Проверка прав доступа
if (($type == 'error') && get_user_class() < 4) {
    stdmsg("Ошибка доступа", "Доступ к этому разделу ограничен.");
    stdfoot();
    exit;
}

stdhead("Лог событий");

// Навигация по типам логов
print('<div>');
print('<h1>Лог событий</h1>');

print('<div>');
foreach ($allowed_types as $t) {
    $active = ($type == $t) ? ' style="font-weight: bold;"' : '';
    $names = [
        'tracker' => 'Трекер',
        'bans' => 'Баны',
        'release' => 'Релизы',
        'exchange' => 'Обмен',
        'torrent' => 'Торренты',
        'error' => 'Ошибки'
    ];
    
    print('<a href="log.php?type=' . $t . '"' . $active . '>' . $names[$t] . '</a> | ');
}
print('</div>');

// Получение данных
$limit = 1000;
$res = sql_query("SELECT txt, added, color FROM sitelog WHERE type = " . sqlesc($type) . " ORDER BY added DESC LIMIT $limit") or sqlerr(__FILE__, __LINE__);

$row_count = mysqli_num_rows($res);

if ($row_count == 0) {
    print('<p>Нет записей в логе</p>');
} else {
    print('<p>Показано записей: ' . $row_count);
    if ($row_count == $limit) {
        print(' (максимум ' . $limit . ')');
    }
    print('</p>');
    
    print('<table border="1" cellpadding="5" cellspacing="0" width="100%">');
    print('<tr>');
    print('<th width="15%">Дата</th>');
    print('<th width="15%">Время</th>');
    print('<th>Событие</th>');
    print('</tr>');
    
    while ($arr = mysqli_fetch_assoc($res)) {
        $date = date('d.m.Y', strtotime($arr['added']));
        $time = date('H:i:s', strtotime($arr['added']));
        
        // Простая цветовая логика
        $color_style = '';
        if ($arr['color'] == 'red') {
            $color_style = ' style="background-color: #ffe6e6;"';
        } elseif ($arr['color'] == 'green') {
            $color_style = ' style="background-color: #e6ffe6;"';
        } elseif ($arr['color'] == 'blue') {
            $color_style = ' style="background-color: #e6f3ff;"';
        } elseif ($arr['color'] == 'yellow' || $arr['color'] == 'orange') {
            $color_style = ' style="background-color: #fff9e6;"';
        }
        
        print('<tr' . $color_style . '>');
        print('<td>' . $date . '</td>');
        print('<td>' . $time . '</td>');
        print('<td>' . htmlspecialchars($arr['txt']) . '</td>');
        print('</tr>');
    }
    
    print('</table>');
}

// Футер
print('<p><small>Записи старше 7 дней автоматически удаляются</small></p>');

print('</div>');

stdfoot();

?>