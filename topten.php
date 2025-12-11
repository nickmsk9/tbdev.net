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

dbconn(false);
loggedinorreturn();

/**
 * Оптимизированная функция для отображения таблицы пользователей
 */
function usertable($res, $frame_caption): void
{
    global $CURUSER;
    begin_frame($frame_caption, true);
    begin_table();
?>
<tr>
<td class="colhead">Место</td>
<td class="colhead" align="left">Пользователь</td>
<td class="colhead">Раздач</td>
<td class="colhead" align="left">Скорость раздачи</td>
<td class="colhead">Закачал</td>
<td class="colhead" align="left">Скорость закачки</td>
<td class="colhead" align="right">Рейтинг</td>
<td class="colhead" align="left">Зарегистрирован</td>
</tr>
<?php
    $num = 0;
    $current_user_id = (int)$CURUSER["id"];
    
    while ($a = mysqli_fetch_assoc($res)) {
        ++$num;
        $user_id = (int)$a["userid"];
        $highlight = $current_user_id === $user_id ? ' bgcolor="#BBAF9B"' : '';
        
        // Расчет рейтинга
        if ($a["downloaded"] > 0) {
            $ratio = (float)$a["uploaded"] / (float)$a["downloaded"];
            $ratio_html = number_format($ratio, 2);
            $color = get_ratio_color($ratio);
            if ($color) {
                $ratio_html = '<font color="' . htmlspecialchars($color) . '">' . $ratio_html . '</font>';
            }
        } else {
            $ratio_html = "Inf.";
        }
        
        $added_timestamp = strtotime($a["added"]);
        $added_formatted = $added_timestamp ? date("Y-m-d", $added_timestamp) : 'Неизвестно';
        $elapsed_time = $added_timestamp ? get_elapsed_time($added_timestamp) : '';
        
        echo '<tr' . $highlight . '>
            <td align="center">' . $num . '</td>
            <td align="left"' . $highlight . '>
                <a href="userdetails.php?id=' . $user_id . '">
                    <b>' . htmlspecialchars($a["username"]) . '</b>
                </a>
            </td>
            <td align="right"' . $highlight . '>' . mksize((float)$a["uploaded"]) . '</td>
            <td align="right"' . $highlight . '>' . mksize((float)$a["upspeed"]) . '/s</td>
            <td align="right"' . $highlight . '>' . mksize((float)$a["downloaded"]) . '</td>
            <td align="right"' . $highlight . '>' . mksize((float)$a["downspeed"]) . '/s</td>
            <td align="right"' . $highlight . '>' . $ratio_html . '</td>
            <td align="left">' . $added_formatted . 
                ($elapsed_time ? ' (' . $elapsed_time . ' назад)' : '') . 
            '</td>
        </tr>';
    }
    end_table();
    end_frame();
}

/**
 * Оптимизированная функция для отображения таблицы торрентов
 */
function torrenttable_topten($res, $frame_caption): void
{
    begin_frame($frame_caption, true);
    begin_table();
?>
<tr>
<td class="colhead" align="center">Место</td>
<td class="colhead" align="left">Название</td>
<td class="colhead" align="right">Скачено</td>
<td class="colhead" align="right">Данные</td>
<td class="colhead" align="right">Раздающих</td>
<td class="colhead" align="right">Качающих</td>
<td class="colhead" align="right">Всего</td>
<td class="colhead" align="right">Рейтинг</td>
</tr>
<?php
    $num = 0;
    while ($a = mysqli_fetch_assoc($res)) {
        ++$num;
        $torrent_id = (int)$a["id"];
        
        // Расчет рейтинга сидеров/личеров
        if ($a["leechers"] > 0) {
            $r = (float)$a["seeders"] / (float)$a["leechers"];
            $ratio_html = '<font color="' . htmlspecialchars(get_ratio_color($r)) . '">' . number_format($r, 2) . '</font>';
        } else {
            $ratio_html = "Inf.";
        }
        
        $total_peers = (int)$a["leechers"] + (int)$a["seeders"];
        
        echo '<tr>
            <td align="center">' . $num . '</td>
            <td align="left">
                <a href="details.php?id=' . $torrent_id . '&hit=1">
                    <b>' . htmlspecialchars($a["name"]) . '</b>
                </a>
            </td>
            <td align="right">' . number_format((int)$a["times_completed"]) . '</td>
            <td align="right">' . mksize((float)$a["data"]) . '</td>
            <td align="right">' . number_format((int)$a["seeders"]) . '</td>
            <td align="right">' . number_format((int)$a["leechers"]) . '</td>
            <td align="right">' . $total_peers . '</td>
            <td align="right">' . $ratio_html . '</td>
        </tr>';
    }
    end_table();
    end_frame();
}

/**
 * Оптимизированная функция для отображения таблицы стран
 */
function countriestable($res, $frame_caption, $what): void
{
    begin_frame($frame_caption, true);
    begin_table();
?>
<tr>
<td class="colhead">Место</td>
<td class="colhead" align="left">Страна</td>
<td class="colhead" align="right"><?php echo htmlspecialchars($what); ?></td>
</tr>
<?php
    $num = 0;
    while ($a = mysqli_fetch_assoc($res)) {
        ++$num;
        
        // Форматирование значения
        switch ($what) {
            case "Пользователи":
                $value = number_format((int)$a["num"]);
                break;
            case "Раздача":
                $value = mksize((float)$a["ul"]);
                break;
            case "Среднее":
                $value = mksize((float)$a["ul_avg"]);
                break;
            case "Рейтинг":
                $value = number_format((float)$a["r"], 2);
                break;
            default:
                $value = $a["num"] ?? 0;
        }
        
        $flagpic = htmlspecialchars($a["flagpic"] ?? '');
        $name = htmlspecialchars($a["name"] ?? '');
        
        echo '<tr>
            <td align="center">' . $num . '</td>
            <td align="left">
                <table border="0" class="main" cellspacing="0" cellpadding="0">
                    <tr>
                        <td class="embedded">
                            <img src="pic/flag/' . $flagpic . '" alt="' . $name . '">
                        </td>
                        <td class="embedded" style="padding-left: 5px">
                            <b>' . $name . '</b>
                        </td>
                    </tr>
                </table>
            </td>
            <td align="right">' . $value . '</td>
        </tr>';
    }
    end_table();
    end_frame();
}

/**
 * Оптимизированная функция для отображения таблицы пиров
 */
function peerstable($res, $frame_caption): void
{
    global $CURUSER;
    
    begin_frame($frame_caption, true);
    begin_table();
?>
<tr>
<td class="colhead">Ранг</td>
<td class="colhead">Пользователь</td>
<td class="colhead">Скорость отдачи</td>
<td class="colhead">Скорость закачки</td>
</tr>
<?php
    $n = 1;
    $current_user_id = (int)$CURUSER["id"];
    
    while ($arr = mysqli_fetch_assoc($res)) {
        $user_id = (int)$arr["userid"];
        $highlight = $current_user_id === $user_id ? ' bgcolor="#BBAF9B"' : '';
        
        $username = htmlspecialchars($arr["username"]);
        $uprate = mksize((float)$arr["uprate"]);
        $downrate = mksize((float)$arr["downrate"]);
        
        echo '<tr>
            <td' . $highlight . '>' . $n . '</td>
            <td' . $highlight . '>
                <a href="userdetails.php?id=' . $user_id . '">
                    <b>' . $username . '</b>
                </a>
            </td>
            <td' . $highlight . '>' . $uprate . '/s</td>
            <td' . $highlight . '>' . $downrate . '/s</td>
        </tr>';
        ++$n;
    }
    end_table();
    end_frame();
}

// Основная логика
stdhead("Топ 10");
begin_main_frame();

$type = isset($_GET["type"]) ? (int)$_GET["type"] : 1;
$limit = isset($_GET["lim"]) ? (int)$_GET["lim"] : 0;
$subtype = isset($_GET["subtype"]) ? $_GET["subtype"] : false;

// Валидация входных параметров
$valid_types = [1, 2, 3, 4];
if (!in_array($type, $valid_types)) {
    $type = 1;
}

// Определяем права пользователя
$pu = get_user_class() >= UC_POWER_USER;

// Если не power user, ограничиваем лимит
if (!$pu && $limit > 10) {
    $limit = 10;
}

// Навигация
echo '<p align="center">';
foreach ($valid_types as $t) {
    if ($t > 1) echo ' | ';
    
    $names = [
        1 => 'Пользователи',
        2 => 'Торренты', 
        3 => 'Страны',
        4 => 'Пиры'
    ];
    
    if ($type == $t && !$limit) {
        echo '<b>' . $names[$t] . '</b>';
    } else {
        echo '<a href="topten.php?type=' . $t . '">' . $names[$t] . '</a>';
    }
}
echo '</p>' . PHP_EOL;

// Кэш для запросов пользователей (чтобы не делать несколько одинаковых запросов)
$user_queries_cache = [];

if ($type == 1) {
    // ОПТИМИЗАЦИЯ: Общий базовый запрос
    $base_user_query = "SELECT id as userid, username, added, uploaded, downloaded, 
                        uploaded / (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(added)) AS upspeed, 
                        downloaded / (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(added)) AS downspeed 
                        FROM users WHERE enabled = 'yes'";
    
    if ($limit == 0 || $limit > 250) {
        $limit = 10;
    }
    
    $limits_config = [
        'ul' => ['order' => 'uploaded DESC', 'title' => 'заливающих'],
        'dl' => ['order' => 'downloaded DESC', 'title' => 'качающих'],
        'uls' => ['order' => 'upspeed DESC', 'title' => 'быстрейших заливающих'],
        'dls' => ['order' => 'downspeed DESC', 'title' => 'быстрейших качающих'],
        'bsh' => [
            'order' => 'uploaded / downloaded DESC', 
            'where' => ' AND downloaded > 1073741824',
            'title' => 'лучших раздающих'
        ],
        'wsh' => [
            'order' => 'uploaded / downloaded ASC, downloaded DESC',
            'where' => ' AND downloaded > 1073741824',
            'title' => 'худших раздающих'
        ]
    ];
    
    foreach ($limits_config as $key => $config) {
        if ($limit == 10 || $subtype == $key) {
            $where = isset($config['where']) ? $config['where'] : '';
            $order = $config['order'];
            
            $query = $base_user_query . $where . " ORDER BY " . $order . " LIMIT " . $limit;
            $res = sql_query($query) or sqlerr(__FILE__, __LINE__);
            
            $extra_html = '';
            if ($limit == 10 && $pu) {
                $extra_html = ' <font class="small"> - [<a href="topten.php?type=1&amp;lim=100&amp;subtype=' . $key . '">Top 100</a>] - [<a href="topten.php?type=1&amp;lim=250&amp;subtype=' . $key . '">Top 250</a>]</font>';
            }
            
            $caption = "Top $limit " . $config['title'];
            if (in_array($key, ['uls', 'dls'])) {
                $caption .= ' <font class="small">(среднее, включая период неактивности)</font>';
            }
            if (in_array($key, ['bsh', 'wsh'])) {
                $caption .= ' <font class="small">(минимум 1 GB скачано)</font>';
            }
            
            usertable($res, $caption . $extra_html);
        }
    }
} elseif ($type == 2) {
    // Торренты
    if ($limit == 0 || $limit > 50) {
        $limit = 10;
    }
    
    // ОПТИМИЗАЦИЯ: Общий запрос для всех типов торрентов
    $base_torrent_query = "SELECT t.*, 
                          (t.size * t.times_completed + COALESCE(SUM(p.downloaded), 0)) AS data 
                          FROM torrents AS t 
                          LEFT JOIN peers AS p ON t.id = p.torrent AND p.seeder = 'no'";
    
    $torrent_configs = [
        'act' => [
            'group' => 't.id',
            'order' => 'seeders + leechers DESC, seeders DESC, added ASC',
            'title' => 'Most Active Torrents'
        ],
        'sna' => [
            'group' => 't.id',
            'order' => 'times_completed DESC',
            'title' => 'Most Snatched Torrents'
        ],
        'mdt' => [
            'group' => 't.id',
            'where' => 'leechers >= 5 AND times_completed > 0',
            'order' => 'data DESC, added ASC',
            'title' => 'Most Data Transferred Torrents'
        ],
        'bse' => [
            'group' => 't.id',
            'where' => 'seeders >= 5',
            'order' => 'seeders / leechers DESC, seeders DESC, added ASC',
            'title' => 'Best Seeded Torrents <font class="small">(with minimum 5 seeders)</font>'
        ],
        'wse' => [
            'group' => 't.id',
            'where' => 'leechers >= 5 AND times_completed > 0',
            'order' => 'seeders / leechers ASC, leechers DESC',
            'title' => 'Worst Seeded Torrents <font class="small">(with minimum 5 leechers, excluding unsnatched torrents)</font>'
        ]
    ];
    
    foreach ($torrent_configs as $key => $config) {
        if ($limit == 10 || $subtype == $key) {
            $query = $base_torrent_query;
            
            if (!empty($config['where'])) {
                $query .= " WHERE " . $config['where'];
            }
            
            $query .= " GROUP BY " . $config['group'];
            $query .= " ORDER BY " . $config['order'];
            $query .= " LIMIT " . $limit;
            
            $res = sql_query($query) or sqlerr(__FILE__, __LINE__);
            
            $extra_html = '';
            if ($limit == 10 && $pu) {
                $extra_html = ' <font class="small"> - [<a href="topten.php?type=2&amp;lim=25&amp;subtype=' . $key . '">Top 25</a>] - [<a href="topten.php?type=2&amp;lim=50&amp;subtype=' . $key . '">Top 50</a>]</font>';
            }
            
            torrenttable_topten($res, "Top $limit " . $config['title'] . $extra_html);
        }
    }
} elseif ($type == 3) {
    // Страны
    if ($limit == 0 || $limit > 25) {
        $limit = 10;
    }
    
    $countries_configs = [
        'us' => [
            'query' => "SELECT name, flagpic, COUNT(users.country) as num 
                       FROM countries 
                       LEFT JOIN users ON users.country = countries.id 
                       GROUP BY name 
                       ORDER BY num DESC",
            'title' => 'Countries<font class="small"> (users)</font>',
            'what' => 'Пользователи'
        ],
        'ul' => [
            'query' => "SELECT c.name, c.flagpic, SUM(u.uploaded) AS ul 
                       FROM users AS u 
                       LEFT JOIN countries AS c ON u.country = c.id 
                       WHERE u.enabled = 'yes' 
                       GROUP BY c.name 
                       ORDER BY ul DESC",
            'title' => 'Countries<font class="small"> (total uploaded)</font>',
            'what' => 'Раздача'
        ],
        'avg' => [
            'query' => "SELECT c.name, c.flagpic, SUM(u.uploaded)/COUNT(u.id) AS ul_avg 
                       FROM users AS u 
                       LEFT JOIN countries AS c ON u.country = c.id 
                       WHERE u.enabled = 'yes' 
                       GROUP BY c.name 
                       HAVING SUM(u.uploaded) > 1099511627776 AND COUNT(u.id) >= 100 
                       ORDER BY ul_avg DESC",
            'title' => 'Countries<font class="small"> (average total uploaded per user, with minimum 1TB uploaded and 100 users)</font>',
            'what' => 'Среднее'
        ],
        'r' => [
            'query' => "SELECT c.name, c.flagpic, SUM(u.uploaded)/SUM(u.downloaded) AS r 
                       FROM users AS u 
                       LEFT JOIN countries AS c ON u.country = c.id 
                       WHERE u.enabled = 'yes' 
                       GROUP BY c.name 
                       HAVING SUM(u.uploaded) > 1099511627776 AND SUM(u.downloaded) > 1099511627776 AND COUNT(u.id) >= 100 
                       ORDER BY r DESC",
            'title' => 'Countries<font class="small"> (ratio, with minimum 1TB uploaded, 1TB downloaded and 100 users)</font>',
            'what' => 'Рейтинг'
        ]
    ];
    
    foreach ($countries_configs as $key => $config) {
        if ($limit == 10 || $subtype == $key) {
            $query = $config['query'] . " LIMIT " . $limit;
            $res = sql_query($query) or sqlerr(__FILE__, __LINE__);
            
            $extra_html = '';
            if ($limit == 10 && $pu) {
                $extra_html = ' <font class="small"> - [<a href="topten.php?type=3&amp;lim=25&amp;subtype=' . $key . '">Top 25</a>]</font>';
            }
            
            countriestable($res, "Top $limit " . $config['title'] . $extra_html, $config['what']);
        }
    }
} elseif ($type == 4) {
    // Пиры
    if ($limit == 0 || $limit > 250) {
        $limit = 10;
    }
    
    $peer_configs = [
        'ul' => [
            'query' => "SELECT users.id AS userid, username, 
                       (peers.uploaded - peers.uploadoffset) / (UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started)) AS uprate, 
                       IF(seeder = 'yes',
                          (peers.downloaded - peers.downloadoffset) / (finishedat - UNIX_TIMESTAMP(started)),
                          (peers.downloaded - peers.downloadoffset) / (UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started))
                       ) AS downrate 
                       FROM peers 
                       LEFT JOIN users ON peers.userid = users.id 
                       ORDER BY uprate DESC",
            'title' => 'Fastest Uploaders'
        ],
        'dl' => [
            'query' => "SELECT users.id AS userid, username, 
                       (peers.uploaded - peers.uploadoffset) / (UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started)) AS uprate, 
                       IF(seeder = 'yes',
                          (peers.downloaded - peers.downloadoffset) / (finishedat - UNIX_TIMESTAMP(started)),
                          (peers.downloaded - peers.downloadoffset) / (UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started))
                       ) AS downrate 
                       FROM peers 
                       LEFT JOIN users ON peers.userid = users.id 
                       ORDER BY downrate DESC",
            'title' => 'Fastest Downloaders'
        ]
    ];
    
    foreach ($peer_configs as $key => $config) {
        if ($limit == 10 || $subtype == $key) {
            $query = $config['query'] . " LIMIT " . $limit;
            $res = sql_query($query) or sqlerr(__FILE__, __LINE__);
            
            $extra_html = '';
            if ($limit == 10 && $pu) {
                $extra_html = ' <font class="small"> - [<a href="topten.php?type=4&amp;lim=100&amp;subtype=' . $key . '">Top 100</a>] - [<a href="topten.php?type=4&amp;lim=250&amp;subtype=' . $key . '">Top 250</a>]</font>';
            }
            
            peerstable($res, "Top $limit " . $config['title'] . $extra_html);
        }
    }
}

end_main_frame();
stdfoot();
?>