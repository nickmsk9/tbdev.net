<?php

/*
// +----------------------------------------------------------------------------+
// | Project:    TBDevYSE - TBDev Yuna Scatari Edition							|
// +----------------------------------------------------------------------------+
// | This file is part of TBDevYSE. TBDevYSE is based on TBDev,					|
// | originally by RedBeard of TorrentBits, extensively modified by				|
// | Gartenzwerg.																|
// |									 										|
// | TBDevYSE is free software; you can redistribute it and/or modify			|
// | it under the terms of the GNU General Public License as published by		|
// | the Free Software Foundation; either version 2 of the License, or			|
// | (at your option) any later version.										|
// |																			|
// | TBDevYSE is distributed in the hope that it will be useful,				|
// | but WITHOUT ANY WARRANTY; without even the implied warranty of				|
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the				|
// | GNU General Public License for more details.								|
// |																			|
// | You should have received a copy of the GNU General Public License			|
// | along with TBDevYSE; if not, write to the Free Software Foundation,		|
// | Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA				|
// +----------------------------------------------------------------------------+
// |					       Do not remove above lines!						|
// +----------------------------------------------------------------------------+
*/

# IMPORTANT: Do not edit below unless you know what you are doing!
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

function commenttable(array $rows, string $redaktor = "comment"): void
{
    global $CURUSER, $avatar_max_width;
    
    $count = 0;
    foreach ($rows as $row) {
        // Расчет рейтинга
        if ($row["downloaded"] > 0) {
            $ratio = $row['uploaded'] / $row['downloaded'];
            $ratio = number_format($ratio, 2);
        } elseif ($row["uploaded"] > 0) {
            $ratio = "Беск.";
        } else {
            $ratio = "---";
        }
        
        // Проверка онлайн-статуса
        if (strtotime($row["last_access"]) > time() - 600) {
            $online = "online";
            $online_text = "В сети";
        } else {
            $online = "offline";
            $online_text = "Не в сети";
        }
        
        // Начало таблицы
        echo "<table class='maibaugrand' style='width:100%; border:1px; border-collapse:collapse; cellspacing:0; cellpadding:3'>";
        echo "<tr><td class='colhead' style='text-align:left; colspan:2; height:24px;'>";
        
        // Блок информации о пользователе
        if (isset($row["username"])) {
            $title = $row["title"];
            if (empty($title)) {
                $title = get_user_class_name($row["class"]);
            } else {
                $title = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            
            $username = htmlspecialchars($row["username"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $userLink = "userdetails.php?id=" . (int)$row["user"];
            $userDisplay = get_user_class_color($row["class"], $username);
            
            echo ":: <img src='pic/buttons/button_{$online}.gif' alt='{$online_text}' title='{$online_text}' style='position:relative; top:2px; border:0; height:14px;'>"
               . " <a name='comm{$row["id"]}' href='{$userLink}' class='altlink_white'><b>{$userDisplay}</b></a> ::"
               . ($row["donor"] == "yes" ? "<img src='pic/star.gif' alt='Донор'>" : "")
               . ($row["warned"] == "yes" ? "<img src='/pic/warned.gif' alt='Предупрежден'>" : "")
               . " {$title} ::"
               . " <img src='pic/upl.gif' alt='загружено' border='0' width='12' height='12'> " . mksize($row["uploaded"])
               . " :: <img src='pic/down.gif' alt='скачано' border='0' width='12' height='12'> " . mksize($row["downloaded"])
               . " :: <span style='color:" . get_ratio_color($ratio) . ";'>{$ratio}</span> ::";
        } else {
            echo "<a name='comm{$row["id"]}'><i>[Анонимный]</i></a>";
        }
        
        echo "</td></tr>";
        
        // Аватар и текст комментария
        echo "<tr valign='top'>";
        
        // Аватар
        $avatar = ($CURUSER["avatars"] == "yes" && !empty($row["avatar"])) 
            ? htmlspecialchars($row["avatar"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : "pic/default_avatar.gif";
        
        echo "<td style='padding:0; width:5%; text-align:center;'><img src='{$avatar}' style='max-width:{$avatar_max_width}px;'></td>";
        
        // Текст комментария
        echo "<td style='width:100%;' class='text'>";
        
        // Обработка текста комментария
        if (md5($row['text']) == $row['text_hash']) {
            $text = $row['text_parsed'];
        } else {
            $text = format_comment($row['text']);
            // Сохраняем распарсенный текст
            $cid = (int)$row['id'];
            $text_hash = md5($row['text']);
            $text_parsed = $text;
            
            sql_query("INSERT INTO comments_parsed (cid, text_hash, text_parsed) 
                      VALUES ($cid, '" . sqlesc($text_hash) . "', '" . sqlesc($text_parsed) . "')") 
                      or sqlerr(__FILE__, __LINE__);
        }
        
        // Добавляем информацию о редактировании
        if (!empty($row["editedby"])) {
            $editedBy = (int)$row["editedby"];
            $editedName = htmlspecialchars($row["editedbyname"] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $editedAt = htmlspecialchars($row["editedat"] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            
            $text .= "<p><small>Последнее редактирование: <a href='userdetails.php?id={$editedBy}'><b>{$editedName}</b></a> в {$editedAt}</small></p>";
        }
        
        echo $text;
        echo "</td></tr>";
        
        // Нижняя панель с действиями
        echo "<tr><td class='colhead' style='text-align:center; colspan:2;'>";
        
        // Левая часть - действия
        echo "<div style='float:left;'>";
        if (!empty($CURUSER)) {
            echo "[<a href='{$redaktor}.php?action=quote&amp;cid={$row["id"]}' class='altlink_white'>Цитировать</a>]";
        }
        
        if (($row["user"] == ($CURUSER["id"] ?? 0)) || (get_user_class() >= UC_MODERATOR)) {
            echo "[<a href='{$redaktor}.php?action=edit&amp;cid={$row["id"]}' class='altlink_white'>Редактировать</a>]";
        }
        
        if (get_user_class() >= UC_MODERATOR) {
            echo "[<a href='{$redaktor}.php?action=delete&amp;cid={$row["id"]}' class='altlink_white'>Удалить</a>]";
        }
        
        if (!empty($row["editedby"]) && get_user_class() >= UC_MODERATOR) {
            echo "[<a href='{$redaktor}.php?action=vieworiginal&amp;cid={$row["id"]}' class='altlink_white'>Оригинал</a>]";
        }
        
        if (get_user_class() >= UC_MODERATOR && !empty($row["ip"])) {
            $ip = htmlspecialchars($row["ip"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo " IP: <a href='usersearch.php?ip={$ip}' class='altlink_white'>{$ip}</a>";
        } elseif (get_user_class() >= UC_MODERATOR) {
            echo " IP: Неизвестен";
        }
        echo "</div>";
        
        // Правая часть - дата добавления
        $added = htmlspecialchars($row["added"] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo "<div style='text-align:right;'>Дата добавления: {$added} GMT</div>";
        
        echo "</td></tr>";
        echo "</table><br>";
        
        $count++;
    }
}

?>