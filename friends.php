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
dbconn(false);
loggedinorreturn();

// Используем null-коалесцирующий оператор для PHP 8.0+
$userid = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? strval($_GET['action']) : '';

// Если ID не указан, используем ID текущего пользователя
if (!$userid && isset($CURUSER['id']))
    $userid = (int)$CURUSER['id'];

if (!$userid || !is_valid_id($userid))
    stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['invalid_id'] ?? 'Неверный ID');

// Проверяем, что пользователь имеет доступ только к своим спискам
if (!isset($CURUSER['id']) || $userid != $CURUSER["id"])
    stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['access_denied'] ?? 'Доступ запрещен');

$res = sql_query("SELECT * FROM users WHERE id=$userid") or sqlerr(__FILE__, __LINE__);
$user = mysqli_fetch_assoc($res) or stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['invalid_id'] ?? 'Неверный ID');

// action: add -------------------------------------------------------------

if ($action == 'add')
{
    $targetid = isset($_GET['targetid']) ? intval($_GET['targetid']) : 0;
    $type = isset($_GET['type']) ? strval($_GET['type']) : '';

    if (!$targetid || !is_valid_id($targetid))
        stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['invalid_id'] ?? 'Неверный ID');

    if ($type == 'friend') {
        $table_is = $frag = 'friends';
        $field_is = 'friendid';
    } elseif ($type == 'block') {
        $table_is = $frag = 'blocks';
        $field_is = 'blockid';
    } else {
        stderr($tracker_lang['error'] ?? 'Ошибка', "Unknown type.");
    }

    $r = sql_query("SELECT id FROM $table_is WHERE userid=$userid AND $field_is=$targetid") or sqlerr(__FILE__, __LINE__);
    if (mysqli_num_rows($r) == 1)
        stderr($tracker_lang['error'] ?? 'Ошибка', "User ID is already in your ".htmlspecialchars($table_is)." list.");

    sql_query("INSERT INTO $table_is VALUES (0,$userid, $targetid)") or sqlerr(__FILE__, __LINE__);
    
    // Используем существующую переменную или значение по умолчанию
    $base_url = $DEFAULTBASEURL ?? '/';
    header("Location: {$base_url}friends.php?id=$userid#$frag");
    die();
}

// action: delete ----------------------------------------------------------

if ($action == 'delete')
{
    $targetid = isset($_GET['targetid']) ? intval($_GET['targetid']) : 0;
    $sure = isset($_GET['sure']) ? htmlspecialchars($_GET['sure']) : '';
    $type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : '';

    if (!$targetid || !is_valid_id($targetid))
        stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['invalid_id'] ?? 'Неверный ID');

    //if ($type == 'friend')

    if (!$sure) {
        $item_type = ($type == 'friend') 
            ? ($tracker_lang['friend'] ?? 'друг') 
            : ($tracker_lang['block'] ?? 'заблокированный пользователь');
        
        $delete_message = sprintf(
            $tracker_lang['you_want_to_delete_x_click_here'] ?? 'Вы хотите удалить %s? Нажмите <a href="%s">здесь</a> для подтверждения.',
            $item_type,
            "?id=$userid&action=delete&type=$type&targetid=$targetid&sure=1"
        );
        
        stderr(
            ($tracker_lang['delete'] ?? 'Удалить') . " " . $item_type,
            $delete_message
        );
    }

    if ($type == 'friend')
    {
        sql_query("DELETE FROM friends WHERE userid=$userid AND friendid=$targetid") or sqlerr(__FILE__, __LINE__);
        if (mysqli_affected_rows() == 0)
            stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['invalid_id'] ?? 'Неверный ID');
        $frag = "friends";
    }
    elseif ($type == 'block')
    {
        sql_query("DELETE FROM blocks WHERE userid=$userid AND blockid=$targetid") or sqlerr(__FILE__, __LINE__);
        if (mysqli_affected_rows() == 0)
            stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['invalid_id'] ?? 'Неверный ID');
        $frag = "blocks";
    }
    else {
        stderr($tracker_lang['error'] ?? 'Ошибка', "Unknown type.");
    }

    // Используем существующую переменную или значение по умолчанию
    $base_url = $DEFAULTBASEURL ?? '/';
    header("Location: {$base_url}friends.php?id=$userid#$frag");
    die();
}

// main body  -----------------------------------------------------------------

$page_title = isset($tracker_lang['my_user_lists']) 
    ? $tracker_lang['my_user_lists'] 
    : "Мои списки пользователей";
    
stdhead($page_title);

print("<table class=main width=100% border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>");

print("<table width=100% border=1 cellspacing=0 cellpadding=5>");
print("<tr><td class=\"colhead\"><a name=\"friends\">".($tracker_lang['friends_list'] ?? 'Список друзей')."</a></td></tr>");
print("<tr><td>");
$i = 0;
$friends = "";

$res = sql_query("SELECT f.friendid as id, u.username AS name, u.class, u.avatar, u.title, u.donor, u.warned, u.enabled, u.last_access FROM friends AS f LEFT JOIN users as u ON f.friendid = u.id WHERE userid=$userid ORDER BY name") or sqlerr(__FILE__, __LINE__);
if(mysqli_num_rows($res) == 0) {
    $friends = "<em>".($tracker_lang['no_friends'] ?? 'Нет друзей').".</em>";
} else {
    while ($friend = mysqli_fetch_assoc($res))
    {
        $title = $friend["title"] ?? '';
        if (!$title) {
            $title = get_user_class_name($friend["class"]);
        }
        
        $body1 = "<a href=\"userdetails.php?id=" . $friend['id'] . "\"><b>" . get_user_class_color($friend["class"], $friend['name']) . "</b></a>" .
            get_user_icons($friend) . " ($title)<br /><br />" . ($tracker_lang['last_seen'] ?? 'Последний вход') . ": " . ($friend['last_access'] ?? '') .
            "<br />(" . get_et(sql_ts_to_ut($friend['last_access'] ?? 0)) . " ".($tracker_lang['ago'] ?? 'назад').")";
        
        $body2 = "<br /><a href=\"friends.php?id=$userid&action=delete&type=friend&targetid=" . $friend['id'] . "\">".($tracker_lang['delete'] ?? 'Удалить')."</a>" .
            "<br /><br /><a href=\"message.php?action=sendmessage&amp;receiver=" . $friend['id'] . "\">".($tracker_lang['pm'] ?? 'ЛС')."</a>";
        
        $avatar = (isset($CURUSER["avatars"]) && $CURUSER["avatars"] == "yes" && !empty($friend["avatar"])) 
            ? htmlspecialchars($friend["avatar"]) 
            : "";
            
        if (!$avatar) {
            $avatar = "pic/default_avatar.gif";
        }
        
        if ($i % 2 == 0) {
            print("<table width=100% style='padding: 0px'><tr><td class=bottom style='padding: 5px' width=50% align=center>");
        } else {
            print("<td class=bottom style='padding: 5px' width=50% align=center>");
        }
        
        print("<table class=main width=100% height=100px>");
        print("<tr valign=top><td width=100 align=center style='padding: 0px'>" .
            ($avatar ? "<div style='width:100px;height:100px;overflow: hidden'><img width=\"100\" src=\"$avatar\" alt=\"Аватар\" /></div>" : ""). "</td><td>\n");
        print("<table class=main>");
        print("<tr><td class=embedded style='padding: 5px' width=80%>$body1</td>\n");
        print("<td class=embedded style='padding: 5px' width=20%>$body2</td></tr>\n");
        print("</table>");
        print("</td></tr>");
        print("</td></tr></table>\n");
        
        if ($i % 2 == 1) {
            print("</td></tr></table>\n");
        } else {
            print("</td>\n");
        }
        $i++;
    }
}

if ($i % 2 == 1) {
    print("<td class=bottom width=50%>&nbsp;</td></tr></table>\n");
}

print($friends);
print("</td></tr></table>\n");

$blocks = "";
$res = sql_query("SELECT b.blockid AS id, u.username AS name, u.class, u.donor, u.warned, u.enabled, u.last_access FROM blocks AS b LEFT JOIN users AS u ON b.blockid = u.id WHERE userid = $userid ORDER BY name") or sqlerr(__FILE__, __LINE__);
if(mysqli_num_rows($res) == 0) {
    $blocks = "<em>".($tracker_lang['no_blocked'] ?? 'Нет заблокированных пользователей').".</em>";
} else {
    $i = 0;
    $blocks = "<table width=100% cellspacing=0 cellpadding=0>";
    while ($block = mysqli_fetch_assoc($res))
    {
        if ($i % 6 == 0) {
            $blocks .= "<tr>";
        }
        
        $blocks .= "<td style='border: none; padding: 4px; spacing: 0px;'>[<font class=small><a href=friends.php?id=$userid&action=delete&type=block&targetid=" .
            $block['id'] . ">D</a></font>] <a href=userdetails.php?id=" . $block['id'] . "><b>" . get_user_class_color($block['class'], $block['name']) . "</b></a>" .
            get_user_icons($block) . "</td>";
        
        if ($i % 6 == 5) {
            $blocks .= "</tr>";
        }
        $i++;
    }
    
    // Если количество не кратно 6, закрываем строку
    if ($i % 6 != 0) {
        for ($j = $i % 6; $j < 6; $j++) {
            $blocks .= "<td style='border: none; padding: 4px; spacing: 0px;'>&nbsp;</td>";
        }
        $blocks .= "</tr>";
    }
    
    $blocks .= "</table>\n";
}

print("<br />");
print("<table class=main width=100% border=0 cellspacing=0 cellpadding=5>");
print("<tr><td class=\"colhead\"><a name=\"blocks\">".($tracker_lang['blocked_list'] ?? 'Список заблокированных пользователей')."</a></td></tr>");
print("<tr><td style='padding: 5px;background-color: #ECE9D8'>");
print("$blocks\n");
print("</td></tr></table>\n");
print("</td></tr></table>\n");
print("<p><a href=users.php><b>Найти пользователя/Список пользователей</b></a></p>");
stdfoot();
?>