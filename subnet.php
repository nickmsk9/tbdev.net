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

require "include/bittorrent.php";
dbconn();
loggedinorreturn();

/**
 * Форматирование ратио
 */
function formatRatio($up, $down, $color = true) {
    if ($down > 0) {
        $r = number_format($up / $down, 2);
        if ($color) {
            $r = "<font color=" . get_ratio_color($r) . ">$r</font>";
        }
    } elseif ($up > 0) {
        $r = "Inf.";
    } else {
        $r = "---";
    }
    return $r;
}

/**
 * Получение маски подсети из конфигурации или параметра
 */
$mask = $_GET['mask'] ?? "255.255.255.0";

// Поддержка различных форматов маски
if (substr($mask, 0, 1) == "/") {
    // CIDR нотация
    $n = (int)substr($mask, 1);
    if ($n < 0 || $n > 32) {
        stdmsg("Ошибка", "Некорректная маска подсети.");
        stdfoot();
        die();
    }
    $mask = long2ip(~((1 << (32 - $n)) - 1) & 0xFFFFFFFF);
} elseif (!filter_var($mask, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    // Проверка формата маски
    stdmsg("Ошибка", "Некорректная маска подсети.");
    stdfoot();
    die();
}

// Определение подсети пользователя
$user_ip = $CURUSER["ip"];
$ip_parts = explode(".", $user_ip);

// Для определения подсети используем битовую операцию
$user_ip_long = ip2long($user_ip);
$mask_long = ip2long($mask);
$network_long = $user_ip_long & $mask_long;
$network_ip = long2ip($network_long);

// Формат отображения IP (скрываем последний октет)
$display_ip_pattern = $ip_parts[0] . "." . $ip_parts[1] . "." . $ip_parts[2] . ".*";

// Если маска меньше /24, показываем соответствующий паттерн
if ($mask_long < ip2long("255.255.255.0")) {
    $mask_parts = explode(".", $mask);
    $display_ip_pattern = "";
    for ($i = 0; $i < 4; $i++) {
        if ($mask_parts[$i] == "255") {
            $display_ip_pattern .= $ip_parts[$i] . ".";
        } else {
            $display_ip_pattern .= "*.";
        }
    }
    $display_ip_pattern = rtrim($display_ip_pattern, ".");
}

// Отладочная информация
// print("<div style='background:#f0f0f0;padding:10px;'>Отладка: user_ip=$user_ip, mask=$mask, network_ip=$network_ip</div>");

// Расширенный запрос с правильным экранированием
$userId = (int)$CURUSER['id'];

// Используем существующую функцию экранирования из TBDev
$mask_escaped = sqlesc($mask);
$network_ip_escaped = sqlesc($network_ip);

$query = "SELECT 
            id, username, class, last_access, added, 
            uploaded, downloaded, ip 
          FROM users 
          WHERE enabled = 'yes' 
            AND status = 'confirmed' 
            AND id <> $userId 
            AND (INET_ATON(ip) & INET_ATON($mask_escaped)) = INET_ATON($network_ip_escaped)
          ORDER BY last_access DESC";

$res = sql_query($query) or sqlerr(__FILE__, __LINE__);

$neighbors_count = mysqli_num_rows($res);

// Всегда показываем заголовок, даже если нет соседей
stdhead("Соседи по подсети ($mask)");

print("<h1>Соседи по подсети</h1>");

// Информация о текущей подсети
print("<div style='background:#f0f0f0; padding:10px; margin:10px 0; border:1px solid #ccc;'>
        <b>Ваш IP:</b> $user_ip<br>
        <b>Маска подсети:</b> $mask<br>
        <b>Сеть:</b> $network_ip<br>
        <b>Диапазон:</b> $display_ip_pattern<br>
        <b>Найдено соседей:</b> $neighbors_count
       </div>");

// Быстрые ссылки для изменения маски
print("<div style='margin:10px 0; padding:10px; background:#e8e8e8; border:1px solid #ddd;'>
        <b>Изменить диапазон поиска:</b><br>
        <a href='?mask=/24'>/24 (255.255.255.0) - одна подсеть</a><br>
        <a href='?mask=/16'>/16 (255.255.0.0) - 256 подсетей</a><br>
        <a href='?mask=/8'>/8 (255.0.0.0) - 65536 подсетей</a><br>
        <a href='?mask=0.0.0.0'>0.0.0.0 - все пользователи</a>
       </div>");

if ($neighbors_count > 0) {
    print("<table border=1 cellspacing=0 cellpadding=5 width='100%'>\n");
    
    print("<tr>
           <td class=colhead align=left>Пользователь</td>
           <td class=colhead>Загружено</td>
           <td class=colhead>Скачано</td>
           <td class=colhead>Ратио</td>
           <td class=colhead>Регистрация</td>
           <td class=colhead>Последний вход</td>
           <td class=colhead align=left>Класс</td>
           <td class=colhead>IP паттерн</td>
           </tr>\n");
    
    while ($arr = mysqli_fetch_assoc($res)) {
        // Определяем паттерн IP для отображения
        $neighbor_ip_parts = explode(".", $arr["ip"]);
        $neighbor_display_ip = "";
        
        for ($i = 0; $i < 4; $i++) {
            if (explode(".", $mask)[$i] == "255") {
                $neighbor_display_ip .= $neighbor_ip_parts[$i] . ".";
            } else {
                $neighbor_display_ip .= "*.";
            }
        }
        $neighbor_display_ip = rtrim($neighbor_display_ip, ".");
        
        print("<tr>
               <td align=left><b><a href=userdetails.php?id=" . (int)$arr['id'] . ">" . 
               get_user_class_color($arr["class"], $arr["username"]) . "</a></b></td>
               <td>" . mksize($arr["uploaded"]) . "</td>
               <td>" . mksize($arr["downloaded"]) . "</td>
               <td>" . formatRatio($arr["uploaded"], $arr["downloaded"]) . "</td>
               <td>" . htmlspecialchars($arr["added"]) . "</td>
               <td>" . htmlspecialchars($arr["last_access"]) . "</td>
               <td align=left>" . get_user_class_name($arr["class"]) . "</td>
               <td>" . htmlspecialchars($neighbor_display_ip) . "</td>
               </tr>\n");
    }
    
    print("</table>");
    
} else {
    // Если соседей не найдено, показываем информационное сообщение
    print("<div style='background:#fff0f0; padding:15px; margin:15px 0; border:2px solid #ffcccc; text-align:center;'>
            <h3>Соседи не найдены</h3>
            <p>В вашей подсети <b>$display_ip_pattern</b> не найдено других пользователей.</p>
            <p>Попробуйте расширить диапазон поиска, выбрав одну из ссылок выше.</p>
            <p>Текущий запрос: <code>$query</code></p>
           </div>");
}

stdfoot();
?>