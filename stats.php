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


declare(strict_types=1);

require_once "include/bittorrent.php";
dbconn(false);
loggedinorreturn();

// Проверка прав доступа
if (get_user_class() < UC_MODERATOR) {
    stderr("Ошибка", "Доступ запрещен.");
}

stdhead("Статистика");

// CSS стили
?>
<style type="text/css" media="screen">
    a.colheadlink:link, a.colheadlink:visited {
        font-weight: bold;
        color: #FFFFFF;
        text-decoration: none;
    }
    a.colheadlink:hover {
        text-decoration: underline;
    }
</style>
<?php

begin_main_frame();

// Общая статистика по торрентам
$res = sql_query("SELECT COUNT(*) FROM torrents") or sqlerr(__FILE__, __LINE__);
$row = mysqli_fetch_row($res);
$n_tor = (int)$row[0];

// Общая статистика по пирам
$res = sql_query("SELECT COUNT(*) FROM peers") or sqlerr(__FILE__, __LINE__);
$row = mysqli_fetch_row($res);
$n_peers = (int)$row[0];

// Определение порядка сортировки для загрузчиков
$uporder = isset($_GET['uporder']) ? htmlspecialchars($_GET['uporder'], ENT_QUOTES, 'UTF-8') : '';
$catorder = isset($_GET['catorder']) ? htmlspecialchars($_GET['catorder'], ENT_QUOTES, 'UTF-8') : '';

$orderby = match ($uporder) {
    'lastul' => 'last DESC, name',
    'torrents' => 'n_t DESC, name',
    'peers' => 'n_p DESC, name',
    default => 'name',
};

// Получение статистики по загрузчикам
$query = "
    SELECT u.id, u.username AS name, 
           MAX(t.added) AS last, 
           COUNT(DISTINCT t.id) AS n_t, 
           COUNT(p.id) AS n_p
    FROM users AS u 
    LEFT JOIN torrents AS t ON u.id = t.owner 
    LEFT JOIN peers AS p ON t.id = p.torrent 
    WHERE u.class >= " . UC_UPLOADER . "
    GROUP BY u.id 
    ORDER BY $orderby
";

$res = sql_query($query) or sqlerr(__FILE__, __LINE__);

if (mysqli_num_rows($res) === 0) {
    stdmsg("Информация", "Нет загрузчиков.");
} else {
    begin_frame("Статистика загрузчиков", true);
    begin_table();
    
    print("
        <tr>
            <td class='colhead'>
                <a href='" . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . "?uporder=uploader&amp;catorder=$catorder' class='colheadlink'>
                    Загрузчик
                </a>
            </td>
            <td class='colhead'>
                <a href='" . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . "?uporder=lastul&amp;catorder=$catorder' class='colheadlink'>
                    Последний раздача
                </a>
            </td>
            <td class='colhead'>
                <a href='" . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . "?uporder=torrents&amp;catorder=$catorder' class='colheadlink'>
                    Раздачи
                </a>
            </td>
            <td class='colhead'>Процент</td>
            <td class='colhead'>
                <a href='" . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . "?uporder=peers&amp;catorder=$catorder' class='colheadlink'>
                    Пиры
                </a>
            </td>
            <td class='colhead'>Процент</td>
        </tr>
    ");
    
    while ($uploader = mysqli_fetch_assoc($res)) {
        // Форматирование даты последней раздачи
        $last_upload = $uploader['last'] ?? '';
        $last_upload_display = '';
        
        if ($last_upload && $last_upload !== '0000-00-00 00:00:00') {
            $time_ago = get_elapsed_time(sql_timestamp_to_unix_timestamp($last_upload));
            $last_upload_display = $last_upload . " ($time_ago назад)";
        } else {
            $last_upload_display = "<span style='text-align:center;'>---</span>";
        }
        
        // Расчет процентов
        $torrents_percent = ($n_tor > 0) ? number_format(100 * $uploader['n_t'] / $n_tor, 1) . "%" : "---";
        $peers_percent = ($n_peers > 0) ? number_format(100 * $uploader['n_p'] / $n_peers, 1) . "%" : "---";
        
        print("
            <tr>
                <td>
                    <a href='userdetails.php?id=" . (int)$uploader['id'] . "'>
                        <b>" . htmlspecialchars($uploader['name'], ENT_QUOTES, 'UTF-8') . "</b>
                    </a>
                </td>
                <td>$last_upload_display</td>
                <td align='right'>" . (int)$uploader['n_t'] . "</td>
                <td align='right'>$torrents_percent</td>
                <td align='right'>" . (int)$uploader['n_p'] . "</td>
                <td align='right'>$peers_percent</td>
            </tr>
        ");
    }
    
    end_table();
    end_frame();
}

// Статистика по категориям
if ($n_tor === 0) {
    stdmsg("Информация", "На трекере нет раздач!");
} else {
    // Определение порядка сортировки для категорий
    $cat_orderby = match ($catorder) {
        'lastul' => 'last DESC, c.name',
        'torrents' => 'n_t DESC, c.name',
        'peers' => 'n_p DESC, c.name',
        default => 'c.name',
    };
    
    $query = "
        SELECT c.id, c.name, 
               MAX(t.added) AS last, 
               COUNT(DISTINCT t.id) AS n_t, 
               COUNT(p.id) AS n_p
        FROM categories AS c 
        LEFT JOIN torrents AS t ON t.category = c.id 
        LEFT JOIN peers AS p ON t.id = p.torrent 
        GROUP BY c.id 
        ORDER BY $cat_orderby
    ";
    
    $res = sql_query($query) or sqlerr(__FILE__, __LINE__);
    
    begin_frame("Статистика категорий", true);
    begin_table();
    
    print("
        <tr>
            <td class='colhead'>
                <a href='" . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . "?uporder=$uporder&amp;catorder=category' class='colheadlink'>
                    Категория
                </a>
            </td>
            <td class='colhead'>
                <a href='" . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . "?uporder=$uporder&amp;catorder=lastul' class='colheadlink'>
                    Последняя раздача
                </a>
            </td>
            <td class='colhead'>
                <a href='" . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . "?uporder=$uporder&amp;catorder=torrents' class='colheadlink'>
                    Раздачи
                </a>
            </td>
            <td class='colhead'>Процент</td>
            <td class='colhead'>
                <a href='" . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . "?uporder=$uporder&amp;catorder=peers' class='colheadlink'>
                    Пиры
                </a>
            </td>
            <td class='colhead'>Процент</td>
        </tr>
    ");
    
    while ($category = mysqli_fetch_assoc($res)) {
        // Форматирование даты последней раздачи
        $last_upload = $category['last'] ?? '';
        $last_upload_display = '';
        
        if ($last_upload && $last_upload !== '0000-00-00 00:00:00') {
            $time_ago = get_elapsed_time(sql_timestamp_to_unix_timestamp($last_upload));
            $last_upload_display = $last_upload . " ($time_ago назад)";
        } else {
            $last_upload_display = "<span style='text-align:center;'>---</span>";
        }
        
        // Расчет процентов
        $torrents_percent = number_format(100 * $category['n_t'] / $n_tor, 1) . "%";
        $peers_percent = ($n_peers > 0) ? number_format(100 * $category['n_p'] / $n_peers, 1) . "%" : "---";
        
        print("
            <tr>
                <td class='rowhead'>" . htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>$last_upload_display</td>
                <td align='right'>" . (int)$category['n_t'] . "</td>
                <td align='right'>$torrents_percent</td>
                <td align='right'>" . (int)$category['n_p'] . "</td>
                <td align='right'>$peers_percent</td>
            </tr>
        ");
    }
    
    end_table();
    end_frame();
}

end_main_frame();
stdfoot();
die;