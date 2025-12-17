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

stdhead("Дубликаты IP адресов");
begin_frame("Дубликаты IP адресов:", true);
begin_table();

if (get_user_class() >= UC_MODERATOR) {
    $res = sql_query("
        SELECT COUNT(*) AS duplicates, ip 
        FROM users 
        WHERE enabled = 'yes' 
          AND ip <> '' 
          AND ip <> '127.0.0.0' 
        GROUP BY ip 
        HAVING duplicates > 1 
        ORDER BY duplicates DESC, ip
    ") or sqlerr(__FILE__, __LINE__);
    
    print("<tr align=center>
        <td class=colhead width=90>Пользователь</td>
        <td class=colhead width=70>Email</td>
        <td class=colhead width=70>Регистрация</td>
        <td class=colhead width=75>Посл. активность</td>
        <td class=colhead width=70>Скачано</td>
        <td class=colhead width=70>Отдано</td>
        <td class=colhead width=45>Ратио</td>
        <td class=colhead width=125>IP адрес</td>
        <td class=colhead width=40>В сети</td>
    </tr>\n");
    
    $row_color = 0;
    
    while ($ipData = mysqli_fetch_assoc($res)) {
        $ros = sql_query("
            SELECT 
                u.id, 
                u.username, 
                u.class, 
                u.email, 
                u.added, 
                u.last_access, 
                u.downloaded, 
                u.uploaded, 
                u.ip, 
                u.warned, 
                u.donor, 
                u.enabled,
                (SELECT COUNT(*) FROM peers p WHERE p.ip = u.ip AND p.userid = u.id) AS peer_count
            FROM users u
            WHERE u.ip = '" . mysqli_real_escape_string($GLOBALS["___mysqli_ston"], $ipData['ip']) . "'
            ORDER BY u.id
        ") or sqlerr(__FILE__, __LINE__);
        
        while ($user = mysqli_fetch_assoc($ros)) {
            // Форматирование дат
            $registered = ($user['added'] == '0000-00-00 00:00:00') 
                ? '-' 
                : substr($user['added'], 0, 10);
            
            $lastSeen = ($user['last_access'] == '0000-00-00 00:00:00') 
                ? '-' 
                : substr($user['last_access'], 0, 10);
            
            // Расчет ратио
            if ($user["downloaded"] != 0) {
                $ratio = number_format($user["uploaded"] / $user["downloaded"], 3);
            } else {
                $ratio = "---";
            }
            
            // Цвет ратио
            $ratioColor = get_ratio_color($ratio);
            $ratioDisplay = "<font color=\"$ratioColor\">$ratio</font>";
            
            // Размеры трафика
            $downloaded = mksize($user["downloaded"]);
            $uploaded = mksize($user["uploaded"]);
            
            // Чередование цветов строк
            $bgColor = ($row_color % 2 == 0) ? "" : " bgcolor=\"ECE9D8\"";
            
            // Иконки пользователя
            $userIcons = get_user_icons($user);
            $userName = get_user_class_color($user['class'], $user['username']);
            
            // Статус онлайн
            $onlineStatus = ($user['peer_count'] > 0) 
                ? "<span style=\"color: red; font-weight: bold;\">Да</span>" 
                : "<span style=\"color: green; font-weight: bold;\">Нет</span>";
            
            print("<tr$bgColor>
                <td align=left>
                    <b><a href='userdetails.php?id={$user['id']}'>$userName</a></b>$userIcons
                </td>
                <td align=center>{$user['email']}</td>
                <td align=center>$registered</td>
                <td align=center>$lastSeen</td>
                <td align=center>$downloaded</td>
                <td align=center>$uploaded</td>
                <td align=center>$ratioDisplay</td>
                <td align=center><span style=\"font-weight: bold;\">{$user['ip']}</span></td>
                <td align=center>$onlineStatus</td>
            </tr>\n");
            
            $row_color++;
        }
    }
    
    if ($row_color == 0) {
        print("<tr><td colspan='9' align='center'><b>Дубликаты IP адресов не найдены</b></td></tr>\n");
    }
} else {
    print("<br /><table width=60% border=1 cellspacing=0 cellpadding=9>
        <tr><td align=center>
            <h2>Извините, доступ запрещен</h2>
        </td></tr>
    </table>");
}

end_frame();
end_table();
stdfoot();

?>