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
dbconn();

// Получение и валидация passkey
$passkey = isset($_GET["passkey"]) ? trim((string)$_GET["passkey"]) : '';
$user = null;

if ($passkey) {
    $res = sql_query("SELECT id, class FROM users WHERE passkey = " . sqlesc($passkey) . " LIMIT 1");
    if ($res) {
        $user = mysqli_fetch_assoc($res);
    }
    if (!$user) {
        header('HTTP/1.1 403 Forbidden');
        exit('Invalid passkey');
    }
} else {
    loggedinorreturn();
    $user = $CURUSER;
}

// Определение типа фида
$feed = isset($_GET["feed"]) ? trim($_GET["feed"]) : '';
$is_download_feed = ($feed === "dl");

// Получение списка категорий
$categories = [];
$res = sql_query("SELECT id, name FROM categories ORDER BY name");
if ($res) {
    while ($cat = mysqli_fetch_assoc($res)) {
        $categories[(int)$cat['id']] = htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8');
    }
}

// Определение категорий для фильтрации
$where_conditions = ["visible = 'yes'"];
$cats = [];

if (isset($_GET['cat']) && $_GET['cat']) {
    $cat_input = trim($_GET["cat"]);
    $cat_ids = array_filter(explode(",", $cat_input), 'is_numeric');
    $cat_ids = array_map('intval', $cat_ids);
    
    if (!empty($cat_ids)) {
        $valid_cat_ids = array_intersect($cat_ids, array_keys($categories));
        if (!empty($valid_cat_ids)) {
            $cats = $valid_cat_ids;
            $escaped_cats = array_map('sqlesc', $valid_cat_ids);
            $where_conditions[] = "category IN (" . implode(", ", $escaped_cats) . ")";
        }
    }
}

// Проверка доступа к мертвым торрентам
if (get_user_class() < UC_VIP && (!isset($user['class']) || $user['class'] < UC_VIP)) {
    $where_conditions[] = "seeders > 0";
}

$where_clause = implode(" AND ", $where_conditions);

// Заголовки для RSS
header("Content-Type: application/xml; charset=utf-8");
header("X-Robots-Tag: noindex, nofollow");

// Начало RSS-канала
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
echo '<channel>' . "\n";

// Метаданные канала
echo '<title>' . htmlspecialchars($SITENAME, ENT_XML1, 'UTF-8') . '</title>' . "\n";
echo '<link>' . htmlspecialchars($DEFAULTBASEURL, ENT_XML1, 'UTF-8') . '</link>' . "\n";
echo '<description>' . htmlspecialchars("RSS-лента торрентов с " . $SITENAME, ENT_XML1, 'UTF-8') . '</description>' . "\n";
echo '<language>ru-ru</language>' . "\n";
echo '<copyright>Copyright © ' . date('Y') . ' ' . htmlspecialchars($SITENAME, ENT_XML1, 'UTF-8') . '</copyright>' . "\n";
echo '<webMaster>' . htmlspecialchars($SITEEMAIL, ENT_XML1, 'UTF-8') . ' (' . htmlspecialchars($SITENAME, ENT_XML1, 'UTF-8') . ')</webMaster>' . "\n";
echo '<generator>TBDev RSS Generator</generator>' . "\n";
echo '<lastBuildDate>' . date(DATE_RSS) . '</lastBuildDate>' . "\n";
echo '<ttl>60</ttl>' . "\n";

// Atom self-link
echo '<atom:link href="' . htmlspecialchars($DEFAULTBASEURL . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET), ENT_XML1, 'UTF-8') . '" rel="self" type="application/rss+xml" />' . "\n";

// Изображение канала
echo '<image>' . "\n";
echo '  <title>' . htmlspecialchars($SITENAME, ENT_XML1, 'UTF-8') . '</title>' . "\n";
echo '  <url>' . htmlspecialchars($DEFAULTBASEURL . '/favicon.ico', ENT_XML1, 'UTF-8') . '</url>' . "\n";
echo '  <link>' . htmlspecialchars($DEFAULTBASEURL, ENT_XML1, 'UTF-8') . '</link>' . "\n";
echo '  <width>32</width>' . "\n";
echo '  <height>32</height>' . "\n";
echo '</image>' . "\n";

// Получение торрентов
$query = "SELECT 
            t.id, 
            t.name, 
            t.descr, 
            t.filename, 
            t.size, 
            t.category, 
            t.seeders, 
            t.leechers, 
            t.added,
            t.times_completed,
            COALESCE(c.name, 'Без категории') as cat_name
          FROM torrents AS t
          LEFT JOIN categories AS c ON t.category = c.id
          WHERE $where_clause
          ORDER BY t.added DESC 
          LIMIT 50";

$res = sql_query($query) or sqlerr(__FILE__, __LINE__);

if ($res && mysqli_num_rows($res) > 0) {
    while ($row = mysqli_fetch_assoc($res)) {
        $id = (int)$row['id'];
        $name = htmlspecialchars($row['name'], ENT_XML1, 'UTF-8');
        $description = format_comment($row['descr']);
        $filename = htmlspecialchars($row['filename'], ENT_XML1, 'UTF-8');
        $size = (float)$row['size'];
        $category_id = (int)$row['category'];
        $seeders = (int)$row['seeders'];
        $leechers = (int)$row['leechers'];
        $added = $row['added'];
        $times_completed = (int)$row['times_completed'];
        $cat_name = htmlspecialchars($row['cat_name'], ENT_XML1, 'UTF-8');
        
        // Форматирование статуса раздачи
        $seeders_text = $seeders . " " . get_num_suffix($seeders, ['раздающий', 'раздающих', 'раздающих']);
        $leechers_text = $leechers . " " . get_num_suffix($leechers, ['качающий', 'качающих', 'качающих']);
        
        // Генерация ссылки
        if ($is_download_feed) {
            $link_params = [
                'id' => $id,
                'name' => $filename
            ];
            if ($passkey) {
                $link_params['passkey'] = $passkey;
            }
            $link = $DEFAULTBASEURL . "/download.php?" . http_build_query($link_params);
        } else {
            $link = $DEFAULTBASEURL . "/details.php?id=$id&amp;hit=1";
        }
        
        // Расчет общей скорости (опционально, можно закомментировать если вызывает ошибки)
        $total_speed = "нет данных";
        if ($seeders >= 1 && $leechers >= 1) {
            try {
                $speed_query = "SELECT 
                                  (t.size * t.times_completed + SUM(p.downloaded)) / 
                                  GREATEST(1, UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(t.added)) AS totalspeed 
                                FROM torrents AS t 
                                LEFT JOIN peers AS p ON t.id = p.torrent 
                                WHERE p.seeder = 'no' 
                                  AND p.torrent = $id 
                                  AND t.id = $id";
                
                $speed_res = sql_query($speed_query);
                if ($speed_res && $speed_row = mysqli_fetch_assoc($speed_res)) {
                    $total_speed_value = (float)$speed_row['totalspeed'];
                    $total_speed = mksize($total_speed_value) . "/сек";
                }
            } catch (Exception $e) {
                // Игнорируем ошибки расчета скорости
                $total_speed = "данные недоступны";
            }
        }
        
        // GUID (уникальный идентификатор для RSS)
        $guid = $DEFAULTBASEURL . "/details.php?id=$id";
        
        // Формирование описания
        $item_description = "Категория: $cat_name\n";
        $item_description .= "Размер: " . mksize($size) . "\n";
        $item_description .= "Сиды: $seeders_text\n";
        $item_description .= "Личи: $leechers_text\n";
        $item_description .= "Скачано: " . number_format($times_completed, 0, '', ' ') . " раз\n";
        $item_description .= "Общая скорость: $total_speed\n";
        $item_description .= "Добавлен: $added\n";
        if (!empty($description)) {
            $item_description .= "Описание:\n$description";
        }
        
        // Вывод элемента RSS
        echo '<item>' . "\n";
        echo '  <title>' . $name . '</title>' . "\n";
        echo '  <link>' . htmlspecialchars($link, ENT_XML1, 'UTF-8') . '</link>' . "\n";
        echo '  <guid isPermaLink="true">' . htmlspecialchars($guid, ENT_XML1, 'UTF-8') . '</guid>' . "\n";
        echo '  <pubDate>' . date(DATE_RSS, strtotime($added)) . '</pubDate>' . "\n";
        echo '  <description><![CDATA[' . $item_description . ']]></description>' . "\n";
        
        // Дополнительные поля
        echo '  <category>' . $cat_name . '</category>' . "\n";
        echo '  <enclosure url="' . htmlspecialchars($link, ENT_XML1, 'UTF-8') . '" length="' . $size . '" type="application/x-bittorrent" />' . "\n";
        
        echo '</item>' . "\n";
    }
} else {
    // Если нет торрентов
    echo '<item>' . "\n";
    echo '  <title>Нет доступных раздач</title>' . "\n";
    echo '  <link>' . htmlspecialchars($DEFAULTBASEURL, ENT_XML1, 'UTF-8') . '</link>' . "\n";
    echo '  <description><![CDATA[В данный момент нет доступных раздач.]]></description>' . "\n";
    echo '</item>' . "\n";
}

// Завершение RSS-канала
echo '</channel>' . "\n";
echo '</rss>' . "\n";

exit;

/**
 * Функция для правильного склонения числительных
 */
function get_num_suffix(int $number, array $suffixes): string {
    $keys = [2, 0, 1, 1, 1, 2];
    $mod = $number % 100;
    $suffix_key = ($mod > 7 && $mod < 20) ? 2 : $keys[min($mod % 10, 5)];
    return $suffixes[$suffix_key];
}

?>