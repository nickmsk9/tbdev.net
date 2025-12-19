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

require_once __DIR__ . '/include/bittorrent.php';

dbconn(false);
loggedinorreturn();
parked();

/**
 * FIX кодировки/кракозябр:
 * 1) Убедись, что MySQL соединение в dbconn() делает mysqli_set_charset('utf8mb4').
 * 2) Здесь ставим заголовок на случай, если где-то не выставлен.
 */
header('Content-Type: text/html; charset=' . ($tracker_lang['language_charset'] ?? 'utf-8'));


$action = (string)($_GET['action'] ?? '');

/** маленькая утилита: быстро получить имя торрента одной функцией */
function fetch_torrent_name(int $tid): string
{
    $r = sql_query("SELECT name FROM torrents WHERE id=" . (int)$tid) or sqlerr(__FILE__, __LINE__);
    $row = $r ? mysqli_fetch_assoc($r) : null;
    if (!$row) {
        stderr($GLOBALS['tracker_lang']['error'] ?? 'Ошибка', $GLOBALS['tracker_lang']['no_torrent_with_such_id'] ?? 'Такого торрента нет.');
    }
    return (string)$row['name'];
}

/** получаем одну запись комментария + tid/name/username для quote/edit/vieworiginal */
function fetch_comment_bundle(int $cid, bool $need_user = false): array
{
    $joinUser = $need_user ? " LEFT JOIN users AS u ON c.user = u.id " : "";
    $selUser  = $need_user ? ", u.username AS c_username " : "";
    $q = "
        SELECT c.*, t.name AS t_name, t.id AS tid
        $selUser
        FROM comments AS c
        LEFT JOIN torrents AS t ON c.torrent = t.id
        $joinUser
        WHERE c.id=" . (int)$cid . "
        LIMIT 1
    ";
    $r = sql_query($q) or sqlerr(__FILE__, __LINE__);
    $row = $r ? mysqli_fetch_assoc($r) : null;
    if (!$row) {
        stderr($GLOBALS['tracker_lang']['error'] ?? 'Ошибка', $GLOBALS['tracker_lang']['invalid_id'] ?? 'Неверный ID.');
    }
    return $row;
}

if ($action === 'add') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $torrentid = (int)($_POST['tid'] ?? 0);
        if (!is_valid_id($torrentid)) {
            stderr($tracker_lang['error'], $tracker_lang['invalid_id']);
        }

        // 1 запрос вместо “select + fetch_array(0)”
        $tname = fetch_torrent_name($torrentid);

        $text = trim((string)($_POST['text'] ?? ''));
        if ($text === '') {
            stderr($tracker_lang['error'], $tracker_lang['comment_cant_be_empty']);
        }

        $added = get_date_time();
        $ip = getip();

        // INSERT комментария
        sql_query(
            "INSERT INTO comments (user, torrent, added, text, ori_text, ip)
             VALUES (" . (int)$CURUSER['id'] . ", $torrentid, " . sqlesc($added) . ", " . sqlesc($text) . ", " . sqlesc($text) . ", " . sqlesc($ip) . ")"
        ) or sqlerr(__FILE__, __LINE__);

        $newid = (int)mysqli_insert_id();

        // parsed cache (upsert)
        $hash = md5($text);
        $parsed = format_comment($text);
        sql_query(
            "INSERT INTO comments_parsed (cid, text_hash, text_parsed)
             VALUES (" . (int)$newid . ", " . sqlesc($hash) . ", " . sqlesc($parsed) . ")
             ON DUPLICATE KEY UPDATE text_hash=VALUES(text_hash), text_parsed=VALUES(text_parsed)"
        ) or sqlerr(__FILE__, __LINE__);

        // Снижаем запросы: вместо UPDATE torrents SET comments = comments + 1
        // можно считать комментарии лениво, но если поле нужно — оставляем 1 апдейт.
        sql_query("UPDATE torrents SET comments = comments + 1 WHERE id=$torrentid") or sqlerr(__FILE__, __LINE__);

        header("Location: details.php?id=$torrentid&viewcomm=$newid#comm$newid");
        exit;
    }

    // GET форма добавления
    $torrentid = (int)($_GET['tid'] ?? 0);
    if (!is_valid_id($torrentid)) {
        stderr($tracker_lang['error'], $tracker_lang['invalid_id']);
    }

    // 1 запрос: имя торрента
    $tname = fetch_torrent_name($torrentid);

    stdhead('Добавить комментарий к "' . htmlspecialchars_uni($tname) . '"');

    print("<p><form name=\"comment\" method=\"post\" action=\"comment.php?action=add\">\n");
    print("<input type=\"hidden\" name=\"tid\" value=\"$torrentid\"/>\n");
    ?>
    <table class="main" border="0" cellspacing="0" cellpadding="3">
        <tr>
            <td class="colhead">
                <?= htmlspecialchars_uni($tracker_lang['add_comment'] ?? 'Добавить комментарий') ?>
                к "<?= htmlspecialchars_uni($tname) ?>"
            </td>
        </tr>
        <tr><td><?php textbbcode("comment", "text", ""); ?></td></tr>
    </table>
    <?php
    print("<p><input type=\"submit\" value=\"Отправить\" /></p></form>\n");

    // 1 запрос: последние 5 комментариев (оставляем, это реально полезно)
    $res = sql_query("
        SELECT
            c.id, c.text, c.ip, c.added,
            u.username, u.title, u.class, u.id AS user,
            u.avatar, u.donor, u.enabled, u.warned, u.parked
        FROM comments AS c
        LEFT JOIN users AS u ON c.user = u.id
        WHERE c.torrent = $torrentid
        ORDER BY c.id DESC
        LIMIT 5
    ") or sqlerr(__FILE__, __LINE__);

    $allrows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $allrows[] = $r;
    }

    if ($allrows) {
        print("<h2>Последние комментарии (сначала новые)</h2>\n");
        commenttable($allrows);
    }

    stdfoot();
    exit;
}

if ($action === 'quote') {
    $commentid = (int)($_GET['cid'] ?? 0);
    if (!is_valid_id($commentid)) {
        stderr($tracker_lang['error'], $tracker_lang['invalid_id']);
    }

    // 1 запрос: comment + torrent + username
    $arr = fetch_comment_bundle($commentid, true);

    stdhead('Цитирование комментария к "' . htmlspecialchars_uni((string)$arr['t_name']) . '"');

    $username = (string)($arr['c_username'] ?? 'user');
    $qtext = "[quote={$username}]" . (string)($arr['text'] ?? '') . "[/quote]\n";

    print("<form method=\"post\" name=\"comment\" action=\"comment.php?action=add\">\n");
    print("<input type=\"hidden\" name=\"tid\" value=\"" . (int)$arr['tid'] . "\" />\n");
    ?>
    <table class="main" border="0" cellspacing="0" cellpadding="3">
        <tr><td class="colhead">Цитирование комментария к "<?= htmlspecialchars_uni((string)$arr['t_name']) ?>"</td></tr>
        <tr><td><?php textbbcode("comment", "text", htmlspecialchars_uni($qtext)); ?></td></tr>
    </table>
    <?php
    print("<p><input type=\"submit\" value=\"Отправить\" /></p></form>\n");
    stdfoot();
    exit;
}

if ($action === 'edit') {
    $commentid = (int)($_GET['cid'] ?? 0);
    if (!is_valid_id($commentid)) {
        stderr($tracker_lang['error'], $tracker_lang['invalid_id']);
    }

    // 1 запрос: comment + torrent
    $arr = fetch_comment_bundle($commentid, false);

    if ((int)$arr['user'] !== (int)$CURUSER['id'] && get_user_class() < UC_MODERATOR) {
        stderr($tracker_lang['error'], $tracker_lang['access_denied']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $text = (string)($_POST['text'] ?? '');
        $returnto = (string)($_POST['returnto'] ?? '');

        $text = trim($text);
        if ($text === '') {
            stderr($tracker_lang['error'], $tracker_lang['comment_cant_be_empty']);
        }

        $editedat = get_date_time(); // НИКОГДА не 0000-00-00...
        $editedby = (int)$CURUSER['id'];

        sql_query(
            "UPDATE comments
             SET text=" . sqlesc($text) . ",
                 editedat=" . sqlesc($editedat) . ",
                 editedby=$editedby
             WHERE id=$commentid"
        ) or sqlerr(__FILE__, __LINE__);

        // parsed cache (upsert)
        $hash = md5($text);
        $parsed = format_comment($text);
        sql_query(
            "INSERT INTO comments_parsed (cid, text_hash, text_parsed)
             VALUES ($commentid, " . sqlesc($hash) . ", " . sqlesc($parsed) . ")
             ON DUPLICATE KEY UPDATE text_hash=VALUES(text_hash), text_parsed=VALUES(text_parsed)"
        ) or sqlerr(__FILE__, __LINE__);

        if ($returnto !== '') {
            header("Location: $returnto");
        } else {
            header("Location: $DEFAULTBASEURL/");
        }
        exit;
    }

    stdhead('Редактирование комментария к "' . htmlspecialchars_uni((string)$arr['t_name']) . '"');

    $returnto = "details.php?id=" . (int)$arr['tid'] . "&viewcomm=$commentid#comm$commentid";

    print("<form method=\"post\" name=\"comment\" action=\"comment.php?action=edit&amp;cid=$commentid\">\n");
    print("<input type=\"hidden\" name=\"returnto\" value=\"" . htmlspecialchars($returnto, ENT_QUOTES, 'UTF-8') . "\" />\n");
    print("<input type=\"hidden\" name=\"cid\" value=\"$commentid\" />\n");
    ?>
    <table class="main" border="0" cellspacing="0" cellpadding="3">
        <tr><td class="colhead">Редактирование комментария к "<?= htmlspecialchars_uni((string)$arr['t_name']) ?>"</td></tr>
        <tr><td><?php textbbcode("comment", "text", htmlspecialchars_uni((string)($arr['text'] ?? ''))); ?></td></tr>
    </table>
    <?php
    print("<p><input type=\"submit\" value=\"Сохранить\" /></p></form>\n");
    stdfoot();
    exit;
}

// ---------- Подписка/отписка на комментарии ----------
if ($action === 'check' || $action === 'checkoff') {
    $tid = (int)($_GET['tid'] ?? 0);
    if (!is_valid_id($tid)) {
        stderr($tracker_lang['error'], "Неверный ID: $tid.");
    }

    $uid = (int)$CURUSER['id'];

    // 1 запрос вместо fetch_array(select count(*))
    $r = sql_query("SELECT 1 FROM checkcomm WHERE checkid=$tid AND userid=$uid AND torrent=1 LIMIT 1") or sqlerr(__FILE__, __LINE__);
    $exists = ($r && mysqli_num_rows($r) > 0);

    if ($action === 'check') {
        if ($exists) {
            stderr($tracker_lang['error'], "<p>Вы уже подписаны на этот раздел.</p><a href=\"details.php?id=$tid#startcomments\">Назад</a>");
        }
        sql_query("INSERT INTO checkcomm (checkid, userid, torrent) VALUES ($tid, $uid, 1)") or sqlerr(__FILE__, __LINE__);
        stderr($tracker_lang['success'], "<p>Вы подписались на уведомления о новых комментариях.</p><a href=\"details.php?id=$tid#startcomments\">Назад</a>");
    } else {
        if ($exists) {
            sql_query("DELETE FROM checkcomm WHERE checkid=$tid AND userid=$uid AND torrent=1") or sqlerr(__FILE__, __LINE__);
        }
        stderr($tracker_lang['success'], "<p>Вы отписались от уведомлений.</p><a href=\"details.php?id=$tid#startcomments\">Назад</a>");
    }
}

// ---------- Удаление ----------
if ($action === 'delete') {
    if (get_user_class() < UC_MODERATOR) {
        stderr($tracker_lang['error'], $tracker_lang['access_denied']);
    }

    $commentid = (int)($_GET['cid'] ?? 0);
    if (!is_valid_id($commentid)) {
        stderr($tracker_lang['error'], $tracker_lang['invalid_id']);
    }

    $sure = (int)($_GET['sure'] ?? 0);
    if ($sure !== 1) {
        stderr(
            ($tracker_lang['delete'] ?? 'Удалить') . ' ' . ($tracker_lang['comment'] ?? 'комментарий'),
            sprintf($tracker_lang['you_want_to_delete_x_click_here'], $tracker_lang['comment'], "?action=delete&cid=$commentid&sure=1")
        );
    }

    // 1 запрос: узнаём torrent id
    $res = sql_query("SELECT torrent FROM comments WHERE id=$commentid LIMIT 1") or sqlerr(__FILE__, __LINE__);
    $arr = $res ? mysqli_fetch_assoc($res) : null;
    if (!$arr) {
        stderr($tracker_lang['error'], $tracker_lang['invalid_id']);
    }
    $torrentid = (int)$arr['torrent'];

    // удаляем коммент
    sql_query("DELETE FROM comments WHERE id=$commentid") or sqlerr(__FILE__, __LINE__);
    $affected = (int)mysqli_affected_rows();

    if ($torrentid > 0 && $affected > 0) {
        sql_query("UPDATE torrents SET comments = GREATEST(comments - 1, 0) WHERE id=$torrentid") or sqlerr(__FILE__, __LINE__);
        // подчистим parsed кэш
        sql_query("DELETE FROM comments_parsed WHERE cid=$commentid") or sqlerr(__FILE__, __LINE__);
    }

    // 1 запрос: последний коммент чтобы вернуться (или на #startcomments если нет)
    $r2 = sql_query("SELECT id FROM comments WHERE torrent=$torrentid ORDER BY added DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
    $last = $r2 ? mysqli_fetch_assoc($r2) : null;

    if ($last && !empty($last['id'])) {
        $lastId = (int)$last['id'];
        header("Location: details.php?id=$torrentid&viewcomm=$lastId#comm$lastId");
    } else {
        header("Location: details.php?id=$torrentid#startcomments");
    }
    exit;
}

// ---------- Просмотр оригинала ----------
if ($action === 'vieworiginal') {
    if (get_user_class() < UC_MODERATOR) {
        stderr($tracker_lang['error'], $tracker_lang['access_denied']);
    }

    $commentid = (int)($_GET['cid'] ?? 0);
    if (!is_valid_id($commentid)) {
        stderr($tracker_lang['error'], $tracker_lang['invalid_id']);
    }

    $arr = fetch_comment_bundle($commentid, false);

    stdhead('Оригинальный текст');
    print("<h1>Оригинальный текст комментария #$commentid</h1><p>\n");
    print("<table width=\"500\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">");
    print("<tr><td class=\"comment\">");
    echo htmlspecialchars_uni((string)($arr['ori_text'] ?? ''));
    print("</td></tr></table>\n");

    $returnto = "details.php?id=" . (int)$arr['tid'] . "&viewcomm=$commentid#comm$commentid";
    print("<p><font size=\"small\"><a href=\"" . htmlspecialchars($returnto, ENT_QUOTES, 'UTF-8') . "\">Назад</a></font></p>\n");

    stdfoot();
    exit;
}

stderr($tracker_lang['error'], 'Unknown action');
