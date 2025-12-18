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

declare(strict_types=1);

require_once "include/bittorrent.php";

dbconn();
loggedinorreturn();

if (get_user_class() < UC_ADMINISTRATOR) {
    stderr($tracker_lang['error'] ?? 'Ошибка', 'Доступ запрещён.');
}

// Без Notice, если action не передали
$action  = isset($_GET['action']) ? (string)$_GET['action'] : '';
$warning = '';
$arr     = ['subject' => '', 'body' => '']; // чтобы форма "добавить" не сыпала undefined

///////// Удаление новости /////////
if ($action === 'delete') {
    $newsid = isset($_GET['newsid']) ? (int)$_GET['newsid'] : 0;

    if (!is_valid_id($newsid)) {
        stderr($tracker_lang['error'] ?? 'Ошибка', 'Неверный идентификатор.');
    }

    $sure = isset($_GET['sure']) ? (int)$_GET['sure'] : 0;

    if (!$sure) {
        stderr(
            'Удаление новости',
            'Вы действительно хотите удалить эту новость? Нажмите <a href="?action=delete&newsid=' . $newsid . '&sure=1">ДА</a>, если уверены.'
        );
    }

    sql_query("DELETE FROM news WHERE id=$newsid") or sqlerr(__FILE__, __LINE__);
    $warning = 'Новость <b>удалена</b>.';
}

///////// Добавление новости /////////
if ($action === 'add') {
    // Без Notice и с тримом
    $subject = isset($_POST['subject']) ? trim((string)$_POST['subject']) : '';
    $body    = isset($_POST['body']) ? trim((string)$_POST['body']) : '';

    if ($subject === '') {
        stderr($tracker_lang['error'] ?? 'Ошибка', 'Тема новости не может быть пустой!');
    }

    if ($body === '') {
        stderr($tracker_lang['error'] ?? 'Ошибка', 'Текст новости не может быть пустым!');
    }

    $uid = (int)$CURUSER['id'];

    sql_query(
        "INSERT INTO news (userid, added, body, subject)
         VALUES ($uid, NOW(), " . sqlesc($body) . ", " . sqlesc($subject) . ")"
    ) or sqlerr(__FILE__, __LINE__);

    // mysql_affected_rows() в mysqli-проекте часто ломается — безопаснее так:
    // если есть твой враппер для affected rows — подставь его.
    if (function_exists('mysqli_affected_rows')) {
        // Ничего не делаем: прямого mysqli link тут может не быть
    }
    $warning = 'Новость <b>добавлена</b>.';
}

///////// Редактирование новости /////////
if ($action === 'edit') {
    $newsid = isset($_GET['newsid']) ? (int)$_GET['newsid'] : 0;

    if (!is_valid_id($newsid)) {
        stderr($tracker_lang['error'] ?? 'Ошибка', 'Неверный идентификатор.');
    }

    $res = sql_query("SELECT id, body, subject FROM news WHERE id=$newsid LIMIT 1") or sqlerr(__FILE__, __LINE__);
    if (!$res || mysqli_num_rows($res) !== 1) {
        stderr($tracker_lang['error'] ?? 'Ошибка', 'Новость не найдена.');
    }

    $arr = mysqli_fetch_assoc($res);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $subject = isset($_POST['subject']) ? trim((string)$_POST['subject']) : '';
        $body    = isset($_POST['body']) ? trim((string)$_POST['body']) : '';

        if ($subject === '') {
            stderr($tracker_lang['error'] ?? 'Ошибка', 'Тема новости не может быть пустой!');
        }
        if ($body === '') {
            stderr($tracker_lang['error'] ?? 'Ошибка', 'Текст новости не может быть пустым!');
        }

        sql_query(
            "UPDATE news
             SET body=" . sqlesc($body) . ", subject=" . sqlesc($subject) . "
             WHERE id=$newsid"
        ) or sqlerr(__FILE__, __LINE__);

        $warning = 'Новость <b>обновлена</b>.';
    } else {
        $returnto = isset($_GET['returnto']) ? (string)$_GET['returnto'] : '';
        $returnto = htmlentities($returnto, ENT_QUOTES, 'UTF-8');

       stdhead("Редактирование новости");

echo '<form name="news" method="post" action="?action=edit&newsid=' . (int)$newsid . '">';
echo '<input type="hidden" name="returnto" value="' . $returnto . '">';

echo '<table width="100%" border="0" cellspacing="0" cellpadding="0">';
echo '<tr><td align="center">';

echo '<table width="80%" border="1" cellspacing="0" cellpadding="5">';

echo '<tr><td class="colhead" align="center">Редактирование новости</td></tr>';

// Центрируем и подпись, и поле
echo '<tr><td align="center">'
    . 'Тема:&nbsp;'
    . '<input type="text" name="subject" maxlength="70" size="70" value="'
    . htmlspecialchars_uni($arr['subject'] ?? '')
    . '"/>'
    . '</td></tr>';

// Центрируем редактор
echo '<tr><td align="center">';
echo textbbcode("news", "body", htmlspecialchars_uni($arr['body'] ?? ''));
echo '</td></tr>';

echo '<tr><td align="center"><input type="submit" value="Сохранить"></td></tr>';

echo '</table>';

echo '</td></tr></table>';
echo '</form>';

stdfoot();
die;



    }
}

stdhead("Новости");

if ($warning !== '') {
    echo '<p><font size="-3">(' . $warning . ')</font></p>';
}

// Форма добавления (оставляем тот же вид)
echo '<form name="news" method="post" action="?action=add">';
echo '<table border="1" cellspacing="0" cellpadding="5">';
echo '<tr><td class="colhead">Добавить новость</td></tr>';
echo '<tr><td>Тема: <input type="text" name="subject" maxlength="40" size="50" value="' . htmlspecialchars_uni($arr['subject'] ?? '') . '"/></td></tr>';

// ВАЖНО: textbbcode выводим без обёртки <tr><td>...</td></tr>, как в оригинале TBDev
echo '<tr><td>';
echo textbbcode("news", "body");
echo '</td></tr>';

echo '<tr><td align="center"><input type="submit" value="Добавить" class="btn"></td></tr>';
echo '</table>';
echo '</form><br /><br />';

// 1 запрос на весь список (оптимально)
$query = sql_query(
    "SELECT n.id, n.userid, n.added, n.body, n.subject, u.username
     FROM news AS n
     LEFT JOIN users AS u ON u.id = n.userid
     ORDER BY n.added DESC"
) or sqlerr(__FILE__, __LINE__);

if ($query && mysqli_num_rows($query) > 0) {
    begin_main_frame();
    begin_frame();

    while ($result = mysqli_fetch_assoc($query)) {
        $newsid  = (int)$result['id'];
        $body    = (string)$result['body'];
        $subject = (string)$result['subject'];
        $userid  = (int)$result['userid'];

        $added = $result['added'] . ' GMT (' . get_elapsed_time(sql_timestamp_to_unix_timestamp($result['added'])) . ' назад)';

        $username = (string)($result['username'] ?? '');

        if ($username === '') {
            $by = 'Неизвестно [' . $userid . ']';
        } else {
            $by = '<a href="userdetails.php?id=' . $userid . '"><b>' . htmlspecialchars_uni($username) . '</b></a>';
        }

        echo '<p class="sub"><table border="0" cellspacing="0" cellpadding="0"><tr><td class="embedded">';
        echo 'Добавлено ' . $added . '&nbsp;-&nbsp;' . $by;
        echo ' - [<a href="?action=edit&newsid=' . $newsid . '"><b>Редактировать</b></a>]';
        echo ' - [<a href="?action=delete&newsid=' . $newsid . '"><b>Удалить</b></a>]';
        echo '</td></tr></table></p>';

        begin_table(true);
        echo '<tr valign="top"><td><b>' . htmlspecialchars_uni($subject) . '</b></td></tr>';
        echo '<tr valign="top"><td class="comment">' . format_comment($body) . '</td></tr>';
        end_table();
    }

    end_frame();
    end_main_frame();
} else {
    stdmsg('Новости', 'Новостей нет!');
}

stdfoot();
