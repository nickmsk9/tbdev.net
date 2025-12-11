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
    $title = $error ? 'Ошибка' : 'Закладка добавлена';
    stdhead($title);
    $msg_title = $error ? 'Ошибка' : 'Успешно';
    $msg_type = $error ? 'error' : 'success';
    stdmsg($msg_title, $msg, $msg_type);
    stdfoot();
    exit;
}

$id = (int) ($_GET["torrent"] ?? 0);
if (!is_valid_id($id)) {
    bark('Торрент не выбран');
}

// Получаем информацию о торренте
$res = sql_query("SELECT name FROM torrents WHERE id = $id") or sqlerr(__FILE__, __LINE__);
if (!$res || mysqli_num_rows($res) == 0) {
    bark('Торрент не найден');
}

$arr = mysqli_fetch_assoc($res);
$torrent_name = htmlspecialchars($arr['name'], ENT_QUOTES, 'UTF-8');

// Проверяем, не добавлен ли уже торрент в закладки
$bookmark_check = sql_query("SELECT COUNT(*) FROM bookmarks WHERE userid = {$CURUSER['id']} AND torrentid = $id") or sqlerr(__FILE__, __LINE__);
$bookmark_row = mysqli_fetch_row($bookmark_check);
$bookmark_count = (int) $bookmark_row[0];

if ($bookmark_count > 0) {
    bark("Торрент \"$torrent_name\" уже добавлен в закладки");
}

// Добавляем торрент в закладки
$user_id = (int) $CURUSER['id'];
sql_query("INSERT INTO bookmarks (userid, torrentid) VALUES ($user_id, $id)") or sqlerr(__FILE__, __LINE__);

// Перенаправляем с сообщением об успехе
header("Refresh: 3; url=browse.php");
bark("Торрент \"$torrent_name\" добавлен в закладки", false);

?>