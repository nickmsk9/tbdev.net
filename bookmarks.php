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

require_once("include/bittorrent.php");
dbconn();
loggedinorreturn();

function bark($msg, $error = true) {
    global $tracker_lang;
    stdhead(($error ? ($tracker_lang['error'] ?? 'Ошибка') : ($tracker_lang['torrent'] ?? 'Торрент') . " " . ($tracker_lang['bookmarked'] ?? 'в закладках')));
    stdmsg(($error ? ($tracker_lang['error'] ?? 'Ошибка') : ($tracker_lang['success'] ?? 'Успешно')), $msg, ($error ? 'error' : 'success'));
    stdfoot();
    exit;
}

// Проверяем наличие GET-параметра torrent
$id = isset($_GET["torrent"]) ? (int) $_GET["torrent"] : 0;

// Если параметр не передан, показываем страницу закладок
if ($id == 0) {
    showBookmarksPage();
    exit;
}

if (!is_valid_id($id)) {
    bark($tracker_lang['torrent_not_selected'] ?? 'Торрент не был выбран');
}

$res = sql_query("SELECT name FROM torrents WHERE id = $id") or sqlerr(__FILE__, __LINE__);
$arr = mysqli_fetch_array($res);

if (!$arr) {
    bark($tracker_lang['torrent_not_found'] ?? 'Торрент не найден');
}

if ((get_row_count("bookmarks", "WHERE userid = $CURUSER[id] AND torrentid = $id")) > 0) {
    bark(($tracker_lang['torrent'] ?? 'Торрент') . " \"" . $arr['name'] . "\" " . ($tracker_lang['already_bookmarked'] ?? 'уже в закладках'));
}

sql_query("INSERT INTO bookmarks (userid, torrentid) VALUES ($CURUSER[id], $id)") or sqlerr(__FILE__, __LINE__);

header("Refresh: 3; url=browse.php");
bark(($tracker_lang['torrent'] ?? 'Торрент') . " \"" . $arr['name'] . "\" " . ($tracker_lang['bookmarked'] ?? 'добавлен в закладки'), false);

// Функция для отображения страницы закладок
function showBookmarksPage() {
    global $CURUSER, $tracker_lang;
    
    stdhead(($tracker_lang['my_bookmarks'] ?? 'Мои закладки'));
    
    echo "<h2>" . ($tracker_lang['my_bookmarks'] ?? 'Мои закладки') . "</h2>\n";
    
    // Получаем закладки пользователя
    $res = sql_query("SELECT b.torrentid, t.name, t.seeders, t.leechers, t.times_completed, t.size, t.added, t.category 
                      FROM bookmarks AS b 
                      LEFT JOIN torrents AS t ON b.torrentid = t.id 
                      WHERE b.userid = " . $CURUSER['id'] . " 
                      ORDER BY t.name") or sqlerr(__FILE__, __LINE__);
    
    $bookmarks_count = mysqli_num_rows($res);
    
    if ($bookmarks_count == 0) {
        echo "<p><i>" . ($tracker_lang['no_bookmarks'] ?? 'У вас пока нет закладок') . "</i></p>\n";
        echo "<p><a href='browse.php'>" . ($tracker_lang['browse_torrents'] ?? 'Перейти к поиску торрентов') . "</a></p>\n";
        stdfoot();
        return;
    }
    
    echo "<p>У вас " . $bookmarks_count . " закладок</p>\n";
    
    echo "<table border='1' cellspacing='0' cellpadding='5' width='100%'>\n";
    echo "<tr class='colhead'>\n";
    echo "<td><b>" . ($tracker_lang['torrent'] ?? 'Торрент') . "</b></td>\n";
    echo "<td><b>" . ($tracker_lang['size'] ?? 'Размер') . "</b></td>\n";
    echo "<td><b>" . ($tracker_lang['added'] ?? 'Добавлен') . "</b></td>\n";
    echo "<td><b>S/L</b></td>\n";
    echo "<td><b>" . ($tracker_lang['times_completed'] ?? 'Завершено') . "</b></td>\n";
    echo "<td><b>" . ($tracker_lang['action'] ?? 'Действие') . "</b></td>\n";
    echo "</tr>\n";
    
    while ($arr = mysqli_fetch_assoc($res)) {
        $torrent_id = $arr['torrentid'];
        $name = htmlspecialchars($arr['name']);
        $size = mksize($arr['size']);
        $added = $arr['added'];
        $seeders = $arr['seeders'];
        $leechers = $arr['leechers'];
        $times_completed = $arr['times_completed'];
        
        echo "<tr>\n";
        echo "<td><a href='details.php?id=$torrent_id'>$name</a></td>\n";
        echo "<td>$size</td>\n";
        echo "<td>$added</td>\n";
        echo "<td>$seeders / $leechers</td>\n";
        echo "<td>$times_completed</td>\n";
        echo "<td><a href='bookmarks.php?torrent=$torrent_id&remove=1'>" . ($tracker_lang['remove'] ?? 'Удалить') . "</a></td>\n";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
    echo "<p><a href='browse.php'>" . ($tracker_lang['browse_torrents'] ?? 'Перейти к поиску торрентов') . "</a></p>\n";
    
    stdfoot();
}
?>