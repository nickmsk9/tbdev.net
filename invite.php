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

// Используем null-коалесцирующий оператор для безопасного доступа
$id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;
$type = isset($_GET["type"]) ? unesc($_GET["type"]) : '';
$invite = isset($_GET["invite"]) ? $_GET["invite"] : '';

stdhead("Приглашения");

function bark($msg) {
    stdmsg("Ошибка", $msg);
    stdfoot();
}

if ($id == 0 && isset($CURUSER["id"])) {
    $id = $CURUSER["id"];
}

// Проверяем, что ID валиден
if (!$id || !is_valid_id($id)) {
    bark("Неверный ID пользователя.");
    die();
}

$res = sql_query("SELECT invites FROM users WHERE id = $id") or sqlerr(__FILE__, __LINE__);

// Используем mysqli_fetch_assoc вместо mysql_fetch_assoc
$inv = mysqli_fetch_assoc($res);

if (!$inv) {
    bark("Пользователь не найден.");
    die();
}

if ($inv["invites"] != 1) {
    $_s = "ний";
} else {
    $_s = "ие";
}

if ($type == 'new') {
    print("<form method=get action=takeinvite.php>".
    "<input type=hidden name=id value=$id />".
    "<table border=1 width=100% cellspacing=0 cellpadding=5>".
    "<tr class=tabletitle><td colspan=2><b>Создать пригласительный код (осталось $inv[invites] приглаше$_s)</b></td></tr>".
    "<tr class=tableb><td align=center colspan=2><input type=submit value=\"Создать\"></td></tr>".
    "</form></table>");
} elseif ($type == 'del' && !empty($invite)) {
    $ret = sql_query("SELECT * FROM invites WHERE invite = ".sqlesc($invite)) or sqlerr(__FILE__, __LINE__);
    $num = mysqli_fetch_assoc($ret);
    if ($num && $num['inviter'] == $id) {
        sql_query("DELETE FROM invites WHERE invite = ".sqlesc($invite)) or sqlerr(__FILE__, __LINE__);
        sql_query("UPDATE users SET invites = invites + 1 WHERE id = " . $CURUSER['id']) or sqlerr(__FILE__, __LINE__);
        stdmsg("Успешно", "Приглашение удалено. Сейчас мы вас переадресуем на страницу приглашений...");
    } else {
        stdmsg("Ошибка", "Вам не разрешено удалять приглашения.");
    }
    header("Refresh: 3; url=invite.php?id=$id");
} else {
    if (get_user_class() <= UC_UPLOADER && !($id == $CURUSER["id"])) {
        bark("У вас нет права видеть приглашения этого пользователя.");
        die();
    }

    $rel = sql_query("SELECT COUNT(*) FROM users WHERE invitedby = $id") or sqlerr(__FILE__, __LINE__);
    $arro = mysqli_fetch_row($rel);
    $number = $arro[0] ?? 0;

    $ret = sql_query("SELECT id, username, class, email, uploaded, downloaded, status, warned, enabled, donor, email FROM users WHERE invitedby = $id") or sqlerr(__FILE__, __LINE__);
    $num = mysqli_num_rows($ret);

    print("<form method=post action=takeconfirm.php?id=$id><table border=1 width=100% cellspacing=0 cellpadding=5>".
    "<tr class=tabletitle><td colspan=7><b>Статус приглашенных вами</b> ($number)</td></tr>");

    if(!$num) {
        print("<tr class=tableb><td colspan=7>Еще никто вами не приглашен.</tr>");
    } else {
        print("<tr class=tableb><td><b>Пользователь</b></td><td><b>Email</b></td><td><b>Раздал</b></td><td><b>Скачал</b></td><td><b>Рейтинг</b></td><td><b>Статус</b></td>");
        if ((isset($CURUSER['id']) && $CURUSER['id'] == $id) || get_user_class() >= UC_SYSOP) {
            print("<td align=center><b>Подтвердить</b></td>");
        }
        print("</tr>");
        for ($i = 0; $i < $num; ++$i) {
            $arr = mysqli_fetch_assoc($ret);
            if ($arr['status'] == 'pending') {
                $user = "<td align=left>$arr[username]</td>";
            } else {
                $user = "<td align=left><a href=userdetails.php?id=$arr[id]>" . get_user_class_color($arr["class"], "$arr[username]") . "</a>" . 
                        (isset($arr["warned"]) && $arr["warned"] == "yes" ? "&nbsp;<img src=pic/warned.gif border=0 alt='Warned'>" : "") . 
                        (isset($arr["enabled"]) && $arr["enabled"] == "no" ? "&nbsp;<img src=pic/disabled.gif border=0 alt='Disabled'>" : "") . 
                        (isset($arr["donor"]) && $arr["donor"] == "yes" ? "&nbsp;<img src=pic/star.gif border=0 alt='Donor'>" : "") . "</td>";
            }
            
            if (isset($arr["downloaded"]) && $arr["downloaded"] > 0) {
                $ratio = number_format($arr["uploaded"] / $arr["downloaded"], 3);
                $ratio = "<font color=" . get_ratio_color($ratio) . ">$ratio</font>";
            } else {
                if (isset($arr["uploaded"]) && $arr["uploaded"] > 0) {
                    $ratio = "Inf.";
                } else {
                    $ratio = "---";
                }
            }
            
            if (isset($arr["status"]) && $arr["status"] == 'confirmed') {
                $status = "<a href=userdetails.php?id=$arr[id]><font color=green>Подтвержден</font></a>";
            } else {
                $status = "<font color=red>Не подтвержден</font>";
            }

            print("<tr class=tableb>$user<td>".(isset($arr['email']) ? htmlspecialchars($arr['email']) : '')."</td><td>" . 
                  (isset($arr['uploaded']) ? mksize($arr['uploaded']) : '0') . "</td><td>" . 
                  (isset($arr['downloaded']) ? mksize($arr['downloaded']) : '0') . "</td><td>$ratio</td><td>$status</td>");

            if ((isset($CURUSER['id']) && $CURUSER['id'] == $id) || get_user_class() >= UC_SYSOP) {
                print("<td align=center>");
                if (isset($arr['status']) && $arr['status'] == 'pending') {
                    print("<input type=\"checkbox\" name=\"conusr[]\" value=\"" . $arr['id'] . "\" />");
                }
                print("</td>");
            }
            print("</tr>");
        }
    }
    
    if ((isset($CURUSER['id']) && $CURUSER['id'] == $id) || get_user_class() >= UC_SYSOP) {
        print("<input type=hidden name=email value=\"" . (isset($arr['email']) ? htmlspecialchars($arr['email']) : '') . "\">");
        print("<tr class=tableb><td colspan=7 align=right><input type=submit value=\"Подтвердить пользователей\"></form></td></tr>");
    }
    print("</table><br>");

    $rul = sql_query("SELECT COUNT(*) FROM invites WHERE inviter = $id") or sqlerr(__FILE__, __LINE__);
    $arre = mysqli_fetch_row($rul);
    $number1 = $arre[0] ?? 0;
    
    $rer = sql_query("SELECT inviteid, invite, time_invited FROM invites WHERE inviter = $id AND confirmed='no'") or sqlerr(__FILE__, __LINE__);
    $num1 = mysqli_num_rows($rer);

    print("<table border=1 width=100% cellspacing=0 cellpadding=5>".
    "<tr class=tabletitle><td colspan=6><b>Статус созданых приглашений</b> ($number1)</td></tr>");

    if(!$num1) {
        print("<tr class=tableb><td colspan=6>На данный момент вами не создано ниодного приглашения.</tr>");
    } else {
        print("<tr class=tableb><td><b>Код приглашения</b></td><td><b>Дата создания</b></td><td></td></tr>");
        for ($i = 0; $i < $num1; ++$i) {
            $arr1 = mysqli_fetch_assoc($rer);
            if ($arr1) {
                print("<tr class=tableb><td>".(isset($arr1['invite']) ? htmlspecialchars($arr1['invite']) : '')."</td><td>".(isset($arr1['time_invited']) ? htmlspecialchars($arr1['time_invited']) : '')."</td>");
                print("<td><a href=\"invite.php?invite=".(isset($arr1['invite']) ? urlencode($arr1['invite']) : '')."&type=del\">Удалить приглашение</a></td></tr>");
            }
        }
    }

    print("<tr class=tableb><td colspan=7 align=center><form method=get action=invite.php><input type='hidden' name='id' value='$id' /><input type='hidden' name='type' value='new' /><input type=submit value=\"Создать приглашение\"></form></td></tr>");
    print("</table>");
}
stdfoot();

?>