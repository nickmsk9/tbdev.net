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

require_once __DIR__ . '/include/bittorrent.php';
dbconn(false);
loggedinorreturn();

$action   = (string)($_GET['action'] ?? '');
$pollid   = (int)($_GET['pollid'] ?? 0);
$returnto = (string)($_GET['returnto'] ?? '');

/* --- delete --- */
if ($action === 'delete') {
    if (get_user_class() < UC_MODERATOR) {
        stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['access_denied'] ?? 'Доступ запрещен.');
    }
    if (!is_valid_id($pollid)) {
        stderr($tracker_lang['error'] ?? 'Ошибка', 'Неверный ID опроса.');
    }

    $sure = (int)($_GET['sure'] ?? 0);
    if (!$sure) {
        stderr(
            'Удаление опроса',
            "Вы уверены что хотите удалить этот опрос?<br /><br />" .
            "<a class='altlink' href='?action=delete&amp;pollid={$pollid}&amp;returnto=" . htmlspecialchars_uni($returnto) . "&amp;sure=1'><b>ДА, удалить</b></a> &nbsp;|&nbsp; " .
            "<a class='altlink' href='polls.php'><b>НЕТ, отмена</b></a>"
        );
    }

    sql_query("DELETE FROM pollanswers WHERE pollid=" . (int)$pollid) or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM polls WHERE id=" . (int)$pollid) or sqlerr(__FILE__, __LINE__);

    if ($returnto === 'main') {
        header("Location: {$DEFAULTBASEURL}");
    } else {
        header("Location: {$DEFAULTBASEURL}/polls.php?deleted=1");
    }
    exit;
}

/* --- count polls --- */
$r = sql_query("SELECT COUNT(*) FROM polls") or sqlerr(__FILE__, __LINE__);
$row = mysqli_fetch_row($r);
$pollcount = (int)($row[0] ?? 0);

if ($pollcount <= 1) {
    stderr('Информация', 'В архиве пока нет опросов (на главной показывается только текущий).');
}

/* --- fetch archive polls (exclude newest) in 1 query --- */
$polls_res = sql_query("SELECT * FROM polls ORDER BY id DESC LIMIT 1, " . (int)($pollcount - 1))
    or sqlerr(__FILE__, __LINE__);

$polls = [];
$pollIds = [];
while ($p = mysqli_fetch_assoc($polls_res)) {
    $id = (int)$p['id'];
    $polls[] = $p;
    $pollIds[] = $id;
}

if (!$pollIds) {
    stderr('Информация', 'В архиве пока нет опросов.');
}

/* --- fetch all votes for these polls in 1 query (aggregated) --- */
$idList = implode(',', array_map('intval', $pollIds));
$votes_res = sql_query("
    SELECT pollid, selection, COUNT(*) AS c
    FROM pollanswers
    WHERE pollid IN ($idList) AND selection < 20
    GROUP BY pollid, selection
") or sqlerr(__FILE__, __LINE__);

/* votes[pollid][selection] = count */
$votes = [];
$totalVotes = [];
while ($v = mysqli_fetch_assoc($votes_res)) {
    $pid = (int)$v['pollid'];
    $sel = (int)$v['selection'];
    $cnt = (int)$v['c'];
    $votes[$pid][$sel] = $cnt;
    $totalVotes[$pid] = ($totalVotes[$pid] ?? 0) + $cnt;
}

stdhead('Архив опросов');

echo "<h1>Архив опросов</h1>";
echo "<p align='center'>
        <a class='altlink' href='{$DEFAULTBASEURL}'><b>На главную</b></a>
        &nbsp;|&nbsp;
        <a class='altlink' href='makepoll.php'><b>Создать новый опрос</b></a>
      </p>";

$h = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$nf = static fn($n): string => number_format((int)$n);

foreach ($polls as $poll) {
    $pid = (int)$poll['id'];

    // options list
    $options = [];
    for ($i = 0; $i < 20; $i++) {
        $key = "option{$i}";
        if (!empty($poll[$key])) {
            $options[$i] = (string)$poll[$key];
        }
    }

    $tv = (int)($totalVotes[$pid] ?? 0);

    // build rows: [votes, text, index]
    $rows = [];
    foreach ($options as $idx => $text) {
        $cnt = (int)($votes[$pid][$idx] ?? 0);
        $rows[] = [$cnt, $text, $idx];
    }

    if (($poll['sort'] ?? 'yes') === 'yes') {
        usort($rows, static fn($a, $b) => $b[0] <=> $a[0]);
    }

    $added_date = date("Y-m-d", strtotime((string)$poll['added'])) . " GMT";
    $time_ago   = get_elapsed_time(sql_timestamp_to_unix_timestamp((string)$poll['added'])) . " назад";

    echo "<table class='main' width='100%' border='1' cellspacing='0' cellpadding='5'>";
    echo "<tr><td class='rowhead' colspan='3'>"
        . $h((string)$poll['question'])
        . " <span class='small'>(" . $h($added_date) . ", " . $h($time_ago) . ")</span>";

    if (get_user_class() >= UC_ADMINISTRATOR) {
        echo " <span class='small'> - "
            . "[<a class='altlink' href='makepoll.php?action=edit&amp;pollid={$pid}'><b>Редактировать</b></a>] "
            . "[<a class='altlink' href='?action=delete&amp;pollid={$pid}' onclick=\"return confirm('Вы уверены что хотите удалить этот опрос?');\"><b>Удалить</b></a>]"
            . "</span>";
    }

    echo "</td></tr>";

    // header
    echo "<tr>
            <td class='rowhead' width='60%'>Вариант</td>
            <td class='rowhead' width='20%' align='center'>%</td>
            <td class='rowhead' width='20%' align='right'>Голоса</td>
          </tr>";

    if (!$rows) {
        echo "<tr><td class='embedded' colspan='3' align='center'>В этом опросе нет вариантов ответов.</td></tr>";
    } else {
        foreach ($rows as $r) {
            [$cnt, $text, $idx] = $r;
            $p = ($tv > 0) ? (int)round(($cnt / $tv) * 100) : 0;

            echo "<tr>
                    <td class='embedded'>" . $h($text) . "</td>
                    <td class='embedded' align='center'><b>{$p}%</b></td>
                    <td class='embedded' align='right'>" . $nf($cnt) . "</td>
                  </tr>";
        }
    }

    echo "<tr><td class='embedded' colspan='3' align='center'><b>Всего проголосовало:</b> " . $nf($tv) . "</td></tr>";
    echo "</table><br />";
}

stdfoot();
