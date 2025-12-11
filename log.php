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
print('<div class="container">');
print('<div class="log-header">');
print('<h1><span class="log-icon">📋</span> Лог событий</h1>');
print('</div>');

print('<div class="log-nav">');
foreach ($allowed_types as $t) {
    $active = ($type == $t) ? ' active' : '';
    $icons = [
        'tracker' => '🔄',
        'bans' => '🚫',
        'release' => '🚀',
        'exchange' => '↔️',
        'torrent' => '📥',
        'error' => '⚠️'
    ];
    $names = [
        'tracker' => 'Трекер',
        'bans' => 'Баны',
        'release' => 'Релизы',
        'exchange' => 'Обмен',
        'torrent' => 'Торренты',
        'error' => 'Ошибки'
    ];
    
    print('<a href="log.php?type=' . $t . '" class="log-tab' . $active . '">');
    print('<span class="tab-icon">' . $icons[$t] . '</span>');
    print('<span class="tab-text">' . $names[$t] . '</span>');
    print('</a>');
}
print('</div>');

// Получение данных
$limit = 1000;
$res = sql_query("SELECT txt, added, color FROM sitelog WHERE type = " . sqlesc($type) . " ORDER BY added DESC LIMIT $limit") or sqlerr(__FILE__, __LINE__);

$row_count = mysqli_num_rows($res);

print('<div class="log-content">');
if ($row_count == 0) {
    print('<div class="empty-log">');
    print('<div class="empty-icon">📭</div>');
    print('<h3>Нет записей в логе</h3>');
    print('<p>Записи в разделе "' . htmlspecialchars($names[$type]) . '" отсутствуют</p>');
    print('</div>');
} else {
    print('<div class="log-info">');
    print('<span class="info-badge">📊 Показано записей: ' . $row_count);
    if ($row_count == $limit) {
        print(' (максимум ' . $limit . ')');
    }
    print('</span>');
    print('</div>');
    
    print('<div class="log-table-container">');
    print('<table class="log-table">');
    print('<thead>');
    print('<tr>');
    print('<th class="date-col"><span class="th-icon">📅</span> Дата</th>');
    print('<th class="time-col"><span class="th-icon">🕒</span> Время</th>');
    print('<th class="event-col"><span class="th-icon">📝</span> Событие</th>');
    print('</tr>');
    print('</thead>');
    print('<tbody>');
    
    $color_map = [
        'red' => 'log-danger',
        'green' => 'log-success',
        'blue' => 'log-info',
        'yellow' => 'log-warning',
        'orange' => 'log-warning',
        '' => ''
    ];
    
    $row_counter = 0;
    while ($arr = mysqli_fetch_assoc($res)) {
        $row_counter++;
        $date = date('d.m.Y', strtotime($arr['added']));
        $time = date('H:i:s', strtotime($arr['added']));
        $color_class = isset($color_map[$arr['color']]) ? $color_map[$arr['color']] : '';
        $row_class = ($row_counter % 2 == 0) ? 'even' : 'odd';
        
        // Определение иконки события
        $icon = 'ℹ️';
        $text = strtolower($arr['txt']);
        
        if (strpos($text, 'ошибка') !== false || strpos($text, 'error') !== false) {
            $icon = '❌';
        } elseif (strpos($text, 'успех') !== false || strpos($text, 'success') !== false) {
            $icon = '✅';
        } elseif (strpos($text, 'предупреждение') !== false || strpos($text, 'warning') !== false) {
            $icon = '⚠️';
        } elseif (strpos($text, 'добавлен') !== false || strpos($text, 'added') !== false) {
            $icon = '➕';
        } elseif (strpos($text, 'удален') !== false || strpos($text, 'deleted') !== false) {
            $icon = '➖';
        } elseif (strpos($text, 'обновлен') !== false || strpos($text, 'updated') !== false) {
            $icon = '🔄';
        } elseif (strpos($text, 'зарегистрирован') !== false || strpos($text, 'registered') !== false) {
            $icon = '👤';
        } elseif (strpos($text, 'загружен') !== false || strpos($text, 'uploaded') !== false) {
            $icon = '📤';
        } elseif (strpos($text, 'скачан') !== false || strpos($text, 'downloaded') !== false) {
            $icon = '📥';
        }
        
        print('<tr class="' . $row_class . ' ' . $color_class . '">');
        print('<td class="date-cell"><span class="date-badge">' . $date . '</span></td>');
        print('<td class="time-cell"><span class="time-badge">' . $time . '</span></td>');
        print('<td class="event-cell">');
        print('<span class="event-icon">' . $icon . '</span>');
        print('<span class="event-text">' . htmlspecialchars($arr['txt']) . '</span>');
        print('</td>');
        print('</tr>');
    }
    
    print('</tbody>');
    print('</table>');
    print('</div>');
}
print('</div>');

// Футер
print('<div class="log-footer">');
print('<div class="footer-note">🗑️ Записи старше 7 дней автоматически удаляются</div>');
print('</div>');

print('</div>'); // container

// CSS стили
print('<style>
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* Заголовок */
.log-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px 10px 0 0;
    margin-bottom: 20px;
    text-align: center;
}

.log-header h1 {
    margin: 0;
    font-size: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.log-icon {
    font-size: 28px;
}

/* Навигация */
.log-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-bottom: 20px;
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.log-tab {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    background: white;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    text-decoration: none;
    color: #495057;
    font-weight: 500;
    transition: all 0.3s ease;
    flex: 1;
    min-width: 120px;
    justify-content: center;
}

.log-tab:hover {
    border-color: #6c757d;
    background: #f8f9fa;
    transform: translateY(-2px);
}

.log-tab.active {
    background: #007bff;
    border-color: #007bff;
    color: white;
    box-shadow: 0 4px 6px rgba(0,123,255,.2);
}

.tab-icon {
    font-size: 18px;
}

.tab-text {
    font-size: 14px;
}

/* Контент */
.log-content {
    background: white;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    padding: 20px;
    margin-bottom: 20px;
}

.empty-log {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.empty-icon {
    font-size: 48px;
    margin-bottom: 20px;
}

.empty-log h3 {
    margin: 0 0 10px 0;
    color: #495057;
}

.log-info {
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.info-badge {
    background: #e9ecef;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 14px;
    color: #495057;
    display: inline-block;
}

/* Таблица */
.log-table-container {
    overflow-x: auto;
}

.log-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.log-table thead {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.log-table th {
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
}

.th-icon {
    margin-right: 8px;
}

.log-table tbody tr {
    border-bottom: 1px solid #e9ecef;
    transition: background-color 0.2s;
}

.log-table tbody tr:hover {
    background-color: #f8f9fa;
}

.log-table tbody tr.even {
    background-color: #fcfcfc;
}

.log-table td {
    padding: 12px 15px;
    vertical-align: top;
}

.date-col, .time-col {
    width: 15%;
    white-space: nowrap;
}

.event-col {
    width: 70%;
}

.date-cell, .time-cell {
    text-align: center;
}

.date-badge, .time-badge {
    display: inline-block;
    padding: 4px 10px;
    background: #e9ecef;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 500;
    color: #495057;
}

.event-cell {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.event-icon {
    font-size: 16px;
    flex-shrink: 0;
    margin-top: 2px;
}

.event-text {
    line-height: 1.5;
    word-break: break-word;
}

/* Цветовые классы */
.log-danger { background-color: #fff5f5 !important; }
.log-success { background-color: #f0fff4 !important; }
.log-info { background-color: #f0f9ff !important; }
.log-warning { background-color: #fffaf0 !important; }

.log-danger:hover { background-color: #ffe5e5 !important; }
.log-success:hover { background-color: #e6ffed !important; }
.log-info:hover { background-color: #e6f7ff !important; }
.log-warning:hover { background-color: #fff5e6 !important; }

/* Футер */
.log-footer {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 0 0 10px 10px;
    border: 1px solid #e9ecef;
    color: #6c757d;
    font-size: 14px;
}

.footer-note {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

/* Адаптивность */
@media (max-width: 768px) {
    .container {
        padding: 10px;
    }
    
    .log-nav {
        flex-direction: column;
    }
    
    .log-tab {
        min-width: auto;
    }
    
    .date-col, .time-col {
        width: 20%;
    }
    
    .event-col {
        width: 60%;
    }
    
    .date-badge, .time-badge {
        font-size: 12px;
        padding: 3px 8px;
    }
}

@media (max-width: 576px) {
    .log-header h1 {
        font-size: 20px;
    }
    
    .log-table th, .log-table td {
        padding: 8px 10px;
    }
    
    .event-cell {
        flex-direction: column;
        gap: 5px;
    }
}
</style>');

stdfoot();

?>