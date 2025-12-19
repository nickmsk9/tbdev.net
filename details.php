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
if (empty($allow_guests_details)) {
    loggedinorreturn();
}

/**
 * Определение клиента по User-Agent / peer_id.
 * Актуализировано: убраны мёртвые/экзотические ветки, добавлены популярные libtorrent/Transmission/qBittorrent/Deluge/WebTorrent.
 * Возвращает короткую строку типа "qBittorrent/4.6.5".
 */
function getagent(string $httpagent, string $peer_id = ''): string
{
    $ua = trim($httpagent);

    if ($ua === '' && $peer_id === '') {
        return 'Unknown';
    }

    // Частые современные клиенты (по UA)
    $patterns = [
        '~qBittorrent/([0-9]+(?:\.[0-9]+){1,3})~i'         => 'qBittorrent/$1',
        '~Transmission/([0-9]+(?:\.[0-9]+){1,2})~i'        => 'Transmission/$1',
        '~Deluge/([0-9]+(?:\.[0-9]+){1,2})~i'              => 'Deluge/$1',
        '~libtorrent/([0-9]+(?:\.[0-9]+){1,3})~i'          => 'libtorrent/$1',
        '~rTorrent/([0-9]+(?:\.[0-9]+){1,3})~i'            => 'rTorrent/$1',
        '~uTorrent/([0-9]+)([0-9]+)([0-9]+)([0-9A-Z]+)~'   => "µTorrent/$1.$2.$3.$4",
        '~BitTorrent/([0-9]+(?:\.[0-9]+){1,3})~i'          => 'BitTorrent/$1',
        '~WebTorrent/([0-9]+(?:\.[0-9]+){1,3})~i'          => 'WebTorrent/$1',
        '~aria2/([0-9]+(?:\.[0-9]+){1,3})~i'               => 'aria2/$1',
    ];
    foreach ($patterns as $re => $fmt) {
        if (preg_match($re, $ua, $m)) {
            // $1.. берём из regex
            return preg_replace_callback('~\$(\d+)~', static function ($mm) use ($m) {
                $idx = (int)$mm[1];
                return $m[$idx] ?? '';
            }, $fmt);
        }
    }

    // Legacy / совместимость (часть из старого списка, но без мусора)
    if (preg_match('~^Azureus ([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)~', $ua, $m)) {
        return 'Azureus/' . $m[1];
    }
    if (preg_match('~^BitTornado/([^\s]+)~', $ua, $m)) {
        return 'BitTornado/' . $m[1];
    }
    if (preg_match('~^RAZA (.+)$~', $ua, $m)) {
        return 'Shareaza/' . $m[1];
    }

    // По peer_id (минимальный набор)
    if ($peer_id !== '') {
        if (preg_match('~^KT(\d)(\d)(\d)(\d)~', $peer_id, $m)) {
            return "KTorrent/{$m[1]}.{$m[2]}.{$m[3]}.{$m[4]}";
        }
        if (preg_match('~^CT(\d)(\d)(\d)(\d)~', $peer_id, $m)) {
            return "cTorrent/{$m[1]}.{$m[2]}.{$m[3]}.{$m[4]}";
        }
        if (substr($peer_id, 0, 12) === 'd0c') {
            return 'Mainline';
        }
    }

    return 'Unknown';
}

function dltable(string $name, array $arr, array $torrent): string
{
    global $tracker_lang;

    $count = count($arr);
    $s = "<b>{$count} {$name}</b>\n";
    if ($count === 0) {
        return $s;
    }

    $s .= "<table width=\"100%\" class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n";
    $s .= "<tr>
        <td class=\"colhead\">{$tracker_lang['user']}</td>
        <td class=\"colhead\" align=\"center\">{$tracker_lang['port_open']}</td>
        <td class=\"colhead\" align=\"right\">{$tracker_lang['uploaded']}</td>
        <td class=\"colhead\" align=\"right\">{$tracker_lang['ul_speed']}</td>
        <td class=\"colhead\" align=\"right\">{$tracker_lang['downloaded']}</td>
        <td class=\"colhead\" align=\"right\">{$tracker_lang['dl_speed']}</td>
        <td class=\"colhead\" align=\"right\">{$tracker_lang['ratio']}</td>
        <td class=\"colhead\" align=\"right\">{$tracker_lang['completed']}</td>
        <td class=\"colhead\" align=\"right\">{$tracker_lang['connected']}</td>
        <td class=\"colhead\" align=\"right\">{$tracker_lang['idle']}</td>
        <td class=\"colhead\" align=\"left\">{$tracker_lang['client']}</td>
    </tr>\n";

    $now = time();
    $mod = (get_user_class() >= UC_MODERATOR);
    $size = (int)($torrent['size'] ?? 0);

    foreach ($arr as $e) {
        $s .= "<tr>\n";

        $username = (string)($e['username'] ?? '');
        $ip = (string)($e['ip'] ?? '');
        $userid = (int)($e['userid'] ?? 0);

        if ($username !== '') {
            $s .= "<td><a href=\"userdetails.php?id={$userid}\"><b>" .
                get_user_class_color((int)($e['class'] ?? 0), $username) .
                "</b></a>" .
                ($mod ? "&nbsp;[<span title=\"" . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . "\" style=\"cursor:pointer\">IP</span>]" : "") .
                "</td>\n";
        } else {
            $shown = $mod ? $ip : preg_replace('/\.\d+$/', '.xxx', $ip);
            $s .= "<td>" . htmlspecialchars((string)$shown, ENT_QUOTES, 'UTF-8') . "</td>\n";
        }

        $la = (int)($e['la'] ?? 0);
        $pa = (int)($e['pa'] ?? 0);
        $secs = max(10, $la - $pa);

        $connectable = (string)($e['connectable'] ?? 'no');
        $s .= "<td align=\"center\">" .
            ($connectable === 'yes'
                ? "<span style=\"color:green;cursor:help\" title=\"{$tracker_lang['peertable_port_open']}\">{$tracker_lang['yes']}</span>"
                : "<span style=\"color:red;cursor:help\" title=\"{$tracker_lang['peertable_port_closed']}\">{$tracker_lang['no']}</span>"
            ) .
            "</td>\n";

        $uploaded = (int)($e['uploaded'] ?? 0);
        $downloaded = (int)($e['downloaded'] ?? 0);
        $uploadoffset = (int)($e['uploadoffset'] ?? 0);
        $downloadoffset = (int)($e['downloadoffset'] ?? 0);
        $to_go = (int)($e['to_go'] ?? 0);
        $st = (int)($e['st'] ?? $now);

        $s .= "<td align=\"right\"><nobr>" . mksize($uploaded) . "</nobr></td>\n";
        $s .= "<td align=\"right\"><nobr>" . mksize((int)($uploadoffset / $secs)) . "/s</nobr></td>\n";
        $s .= "<td align=\"right\"><nobr>" . mksize($downloaded) . "</nobr></td>\n";
        $s .= "<td align=\"right\"><nobr>" . mksize((int)($downloadoffset / $secs)) . "/s</nobr></td>\n";

        if ($downloaded > 0) {
            $ratio = floor(($uploaded / $downloaded) * 1000) / 1000;
            $s .= "<td align=\"right\"><font color=\"" . get_ratio_color($ratio) . "\">" . number_format($ratio, 3) . "</font></td>\n";
        } elseif ($uploaded > 0) {
            $s .= "<td align=\"right\">Inf.</td>\n";
        } else {
            $s .= "<td align=\"right\">---</td>\n";
        }

        $completed = ($size > 0) ? sprintf('%.2f%%', 100 * (1 - ($to_go / $size))) : '—';
        $s .= "<td align=\"right\">{$completed}</td>\n";
        $s .= "<td align=\"right\">" . mkprettytime($now - $st) . "</td>\n";
        $s .= "<td align=\"right\">" . mkprettytime($now - $la) . "</td>\n";

        $agent = htmlspecialchars_uni(getagent((string)($e['agent'] ?? ''), (string)($e['peer_id'] ?? '')));
        $s .= "<td align=\"left\">{$agent}</td>\n";

        $s .= "</tr>\n";
    }

    $s .= "</table>\n";
    return $s;
}

// ---- входные данные ----
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die();
}

// ---- основная инфа по торренту ----
$res = sql_query("
    SELECT
        td.descr_hash, td.descr_parsed,
        t.multitracker, t.last_mt_update,
        t.keywords, t.description, t.free, t.seeders, t.banned, t.leechers,
        t.info_hash, t.filename,
        UNIX_TIMESTAMP() - UNIX_TIMESTAMP(t.last_action) AS lastseed,
        t.numratings, t.name,
        IF(t.numratings < $minvotes, NULL, ROUND(t.ratingsum / t.numratings, 1)) AS rating,
        t.owner, t.save_as, t.descr, t.visible, t.size, t.added, t.views, t.hits,
        t.times_completed, t.id, t.type, t.numfiles,
        t.image1, t.image2, t.image3, t.image4, t.image5,
        c.name AS cat_name,
        u.username
    FROM torrents AS t
    LEFT JOIN categories AS c ON t.category = c.id
    LEFT JOIN users AS u ON t.owner = u.id
    LEFT JOIN torrents_descr AS td ON td.tid = t.id
    WHERE t.id = $id
") or sqlerr(__FILE__, __LINE__);

$row = $res ? mysqli_fetch_assoc($res) : null;

$moderator = (get_user_class() >= UC_MODERATOR);
$owned = 0;

if (!$row || (($row['banned'] ?? 'no') === 'yes' && !$moderator)) {
    stderr($tracker_lang['error'], $tracker_lang['no_torrent_with_such_id']);
}

if ($moderator || (!empty($CURUSER) && (int)$CURUSER['id'] === (int)$row['owner'])) {
    $owned = 1;
}

// отметка "прочитан"
if (!empty($CURUSER['id'])) {
    sql_query("INSERT IGNORE INTO readtorrents (userid, torrentid) VALUES (" . (int)$CURUSER['id'] . ", $id)");
}

// hit/redirect
if (isset($_GET['hit'])) {
    sql_query("UPDATE torrents SET views = views + 1 WHERE id = $id");

    if (isset($_GET['tocomm'])) {
        header("Location: $DEFAULTBASEURL/details.php?id=$id&page=0#startcomments");
    } elseif (isset($_GET['filelist'])) {
        header("Location: $DEFAULTBASEURL/details.php?id=$id&filelist=1#filelist");
    } elseif (isset($_GET['toseeders'])) {
        header("Location: $DEFAULTBASEURL/details.php?id=$id&dllist=1#seeders");
    } elseif (isset($_GET['todlers'])) {
        header("Location: $DEFAULTBASEURL/details.php?id=$id&dllist=1#leechers");
    } else {
        header("Location: $DEFAULTBASEURL/details.php?id=$id");
    }
    exit;
}

$keepget = '';

// ---------- РЕНДЕР СТРАНИЦЫ ----------
if (!isset($_GET['page'])) {
    stdhead($tracker_lang['torrent_details'] . ' "' . htmlspecialchars_decode((string)$row['name']) . '"');

    // ---- multitracker announces ----
    $announces_a = [];
    $announces_urls = [];
    if (($row['multitracker'] ?? 'no') === 'yes') {
        $announces_r = sql_query("SELECT url, seeders, leechers, last_update, state, error FROM torrents_scrape WHERE tid = $id");
        if ($announces_r) {
            while ($announce = mysqli_fetch_assoc($announces_r)) {
                $announces_a[] = $announce;
                if (!empty($announce['url'])) {
                    $announces_urls[] = (string)$announce['url'];
                }
            }
        }
    }

    $spacer = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
    $s = '';

    print("<table width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");
    print("<tr><td class=\"colhead\" colspan=\"2\"><div style=\"float:left;width:auto;\">:: {$tracker_lang['torrent_details']}</div><div align=\"right\"><a href=\"bookmark.php?torrent={$row['id']}\"><b>{$tracker_lang['bookmark']}</b></a></div></td></tr>");

    $url = "edit.php?id=" . (int)$row['id'];
    if (isset($_GET['returnto'])) {
        $addthis = "&amp;returnto=" . urlencode((string)$_GET['returnto']);
        $url .= $addthis;
        $keepget .= $addthis;
    }
    $editlink = "a href=\"$url\" class=\"sublink\"";

    // правые иконки
    $right_links = [];
    $right_links[] = "<a href=\"download.php?id={$id}\"><img src=\"$pic_base_url/download.gif\" border=\"0\" alt=\"{$tracker_lang['download']}\" title=\"{$tracker_lang['download']}\"></a>";
    if (($row['multitracker'] ?? 'no') === 'yes') {
        $right_links[] = "<a href=\"" . magnet(true, (string)$row['info_hash'], (string)$row['filename'], (int)$row['size'], $announces_urls) . "\"><img src=\"$pic_base_url/magnet.png\" border=\"0\" alt=\"{$tracker_lang['magnet']}\" title=\"{$tracker_lang['magnet']}\"></a>";
    }
    $right_links[] = "<a href=\"bookmark.php?torrent={$id}\"><img src=\"$pic_base_url/bookmark.gif\" border=\"0\" alt=\"{$tracker_lang['bookmark']}\" title=\"{$tracker_lang['bookmark']}\"></a>";

    if ($right_links) {
        $s .= '<span style="float:right;">' . implode('&nbsp;', $right_links) . '</span>';
    }

    $s .= "<a class=\"index\" href=\"download.php?id=$id\"><b>" . htmlspecialchars_uni((string)$row['name']) . "</b></a>";
    if ($owned) {
        $s .= " $spacer<$editlink>[{$tracker_lang['edit']}]</a>";
    }

    // free icon
    $freepic = '';
    switch ((string)($row['free'] ?? 'no')) {
        case 'yes':
            $freepic = "<img src=\"$pic_base_url/freedownload.gif\" title=\"{$tracker_lang['golden']}\" alt=\"{$tracker_lang['golden']}\">&nbsp;";
            break;
        case 'silver':
            $freepic = "<img src=\"$pic_base_url/silverdownload.gif\" title=\"{$tracker_lang['silver']}\" alt=\"{$tracker_lang['silver']}\">&nbsp;";
            break;
    }

    tr("<nobr>" . htmlspecialchars_uni((string)$row['cat_name']) . "</nobr>", $freepic . $s, 1, 1, "10%");

    tr($tracker_lang['info_hash'], htmlspecialchars_uni((string)$row['info_hash']));

    // постер
    if (!empty($row['image1'])) {
        $img1 = "<a href=\"viewimage.php?pic=" . rawurlencode((string)$row['image1']) . "\"><img border=\"0\" src=\"thumbnail.php?" . htmlspecialchars((string)$row['image1'], ENT_QUOTES, 'UTF-8') . "\" /></a>";
        tr($tracker_lang['details_poster'], $img1, 1);
    }

    // описание (с кешем parsed)
    if (!empty($row['descr'])) {
        $descr_hash = (string)($row['descr_hash'] ?? '');
        $descr_parsed = (string)($row['descr_parsed'] ?? '');

        if ($descr_hash !== '' && md5((string)$row['descr']) === $descr_hash && $descr_parsed !== '') {
            $descr = $descr_parsed;
        } else {
            $descr = format_comment((string)$row['descr']);
            // upsert вместо тупого INSERT, чтобы не плодить дубликаты
            $qh = sqlesc(md5((string)$row['descr']));
            $qp = sqlesc($descr);
            sql_query("INSERT INTO torrents_descr (tid, descr_hash, descr_parsed) VALUES ($id, $qh, $qp)
                       ON DUPLICATE KEY UPDATE descr_hash=VALUES(descr_hash), descr_parsed=VALUES(descr_parsed)")
                or sqlerr(__FILE__, __LINE__);
        }

        tr($tracker_lang['description'], $descr, 1, 1);
    }

    // скриншоты
    $images = [];
    for ($i = 2; $i <= 5; $i++) {
        $k = 'image' . $i;
        if (!empty($row[$k])) {
            $num = $i - 1;
            $file = (string)$row[$k];
            $images[] =
                '<a href="torrents/images/' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '" rel="lightbox" title="' . $tracker_lang['details_screenshot'] . ' №' . $num . '">' .
                '<img title="' . $tracker_lang['details_screenshot'] . ' №' . $num . '" border="0" src="screenshot.php?' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '" /></a>';
        }
    }
    if ($images) {
        tr($tracker_lang['images'], implode('&nbsp; ', $images), 1);
    }

    // видимость / бан
    if (($row['visible'] ?? 'yes') === 'no') {
        tr($tracker_lang['visible'], "<b>{$tracker_lang['no']}</b> ({$tracker_lang['dead']})", 1);
    }
    if ($moderator) {
        tr($tracker_lang['banned'], ((string)$row['banned'] === 'no' ? $tracker_lang['no'] : $tracker_lang['yes']));
    }

    tr($tracker_lang['type'], !empty($row['cat_name']) ? htmlspecialchars_uni((string)$row['cat_name']) : "({$tracker_lang['no_choose']})");

    tr($tracker_lang['seeder'], "{$tracker_lang['seeder_last_seen']} " . mkprettytime((int)$row['lastseed']) . " {$tracker_lang['ago']}");
    tr($tracker_lang['size'], mksize((int)$row['size']) . " (" . number_format((int)$row['size']) . " {$tracker_lang['bytes']})");

    // ---- рейтинг ----
    if (!empty($CURUSER)) {
        ?>
        <script>
        $(document).ready(function(){
            $('span.star').click(function (e) {
                e.stopPropagation();
                var tid = $('#ratingtid').val();
                var rate_value = $(e.target).data('value');
                $.ajax({
                    url: 'takerate.php',
                    type: 'post',
                    data: {rating: rate_value, id: tid},
                    beforeSend: function () {
                        $('div#rating_selector').html('<img src="pic/loading.gif" />');
                    },
                    success: function (result) {
                        $('div#rating_selector').html(result);
                    }
                });
            });
        });
        </script>
        <?php

        $stars = ''; // FIX: раньше был undefined

        $rating_selector = <<<SELECTOR
<input type="hidden" id="ratingtid" value="{$id}" />
<div id="rating_selector">
    <span class="rating star" title="{$tracker_lang['vote_1']}" data-value="1">
    <span class="rating star" title="{$tracker_lang['vote_2']}" data-value="2">
    <span class="rating star" title="{$tracker_lang['vote_3']}" data-value="3">
    <span class="rating star" title="{$tracker_lang['vote_4']}" data-value="4">
    <span class="rating star" title="{$tracker_lang['vote_5']}" data-value="5">
    </span></span></span></span></span>
</div>
SELECTOR;

        $rres = sql_query('SELECT rating FROM ratings WHERE torrent=' . (int)$id . ' AND user=' . (int)$CURUSER['id']);
        if (!$rres) {
            sqlerr(__FILE__, __LINE__);
        }
        $is_voted = $rres ? mysqli_fetch_assoc($rres) : null;

        if ($is_voted) {
            $stars .= ratingpic((float)($row['rating'] ?? 0)) .
                '(' . (string)($row['rating'] ?? 0) . ' ' . $tracker_lang['from'] . ' 5 ' . $tracker_lang['with'] . ' ' .
                (int)($row['numratings'] ?? 0) . ' ' . getWord((int)($row['numratings'] ?? 0), [$tracker_lang['votes_1'], $tracker_lang['votes_2'], $tracker_lang['votes_3']]) .
                ') ' .
                'Ваша оценка <b>' . (int)$is_voted['rating'] . '</b> - <b>' . $tracker_lang['vote_' . (int)$is_voted['rating']] . '</b>';
        } else {
            $stars .= $rating_selector;
        }
        tr($tracker_lang['rating'], $stars, 1);
    }

    tr($tracker_lang['added'], htmlspecialchars_uni((string)$row['added']));
    tr($tracker_lang['views'], (string)(int)$row['views']);
    tr($tracker_lang['hits'], (string)(int)$row['hits']);
    tr($tracker_lang['snatched'], (string)(int)$row['times_completed'] . " " . $tracker_lang['times']);

    // uploader
    $uprow = !empty($row['username'])
        ? '<a href="userdetails.php?id=' . (int)$row['owner'] . '">' . htmlspecialchars_uni((string)$row['username']) . '</a>'
        : "<i>{$tracker_lang['details_anonymous']}</i>";

    tr(
        $tracker_lang['uploaded'],
        $uprow .
        '&nbsp;<a href="simpaty.php?action=add&amp;good&amp;targetid=' . (int)$row['owner'] . '&amp;type=torrent' . $id . '&amp;returnto=' . urlencode((string)($_SERVER['REQUEST_URI'] ?? '')) . '" title="' . $tracker_lang['respect'] . '">' .
        '<img src="' . $pic_base_url . '/thum_good.gif" border="0" alt="' . $tracker_lang['respect'] . '" title="' . $tracker_lang['respect'] . '" /></a>' .
        '&nbsp;&nbsp;<a href="simpaty.php?action=add&amp;bad&amp;targetid=' . (int)$row['owner'] . '&amp;type=torrent' . $id . '&amp;returnto=' . urlencode((string)($_SERVER['REQUEST_URI'] ?? '')) . '" title="' . $tracker_lang['antirespect'] . '">' .
        '<img src="' . $pic_base_url . '/thum_bad.gif" border="0" alt="' . $tracker_lang['antirespect'] . '" title="' . $tracker_lang['antirespect'] . '" /></a>',
        1
    );

    // filelist (multi)
    if (($row['type'] ?? 'single') === 'multi') {
        if (empty($_GET['filelist'])) {
            tr(
                $tracker_lang['files'] . "<br /><a href=\"details.php?id=$id&amp;filelist=1$keepget#filelist\" class=\"sublink\">[{$tracker_lang['open_list']}]</a>",
                (string)(int)$row['numfiles'] . " " . $tracker_lang['files_l'],
                1
            );
        } else {
            tr($tracker_lang['files'], (string)(int)$row['numfiles'] . " " . $tracker_lang['files_l'], 1);

            $s2 = "<table class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n";
            $subres = sql_query("SELECT filename, size FROM files WHERE torrent = $id ORDER BY id") or sqlerr(__FILE__, __LINE__);
            $s2 .= "<tr><td class=\"colhead\">{$tracker_lang['path']}</td><td class=\"colhead\" align=\"right\">{$tracker_lang['size']}</td></tr>\n";

            while ($subrow = mysqli_fetch_assoc($subres)) {
                // FIX: iconv(...) был сломан. Просто выводим как есть + экранирование.
                $fname = htmlspecialchars_uni((string)($subrow['filename'] ?? ''));
                $fsize = (int)($subrow['size'] ?? 0);
                $s2 .= "<tr><td>{$fname}</td><td align=\"right\">" . mksize($fsize) . "</td></tr>\n";
            }

            $s2 .= "</table>\n";
            tr("<a name=\"filelist\">{$tracker_lang['file_list']}</a><br /><a href=\"details.php?id=$id$keepget\" class=\"sublink\">[{$tracker_lang['close_list']}]</a>", $s2, 1);
        }
    }

    // peers list
    if (!isset($_GET['dllist'])) {
        tr(
            $tracker_lang['downloading'] . "<br /><a href=\"details.php?id=$id&amp;dllist=1$keepget#seeders\" class=\"sublink\">[{$tracker_lang['open_list']}]</a>",
            (int)$row['seeders'] . " {$tracker_lang['seeders_l']}, " . (int)$row['leechers'] . " {$tracker_lang['leechers_l']} = " . ((int)$row['seeders'] + (int)$row['leechers']) . " {$tracker_lang['peers_l']}",
            1
        );
    } else {
        $downloaders = [];
        $seeders = [];

        $subres = sql_query("
            SELECT
                seeder, finishedat, downloadoffset, uploadoffset,
                peers.ip, port, peers.uploaded, peers.downloaded, to_go,
                UNIX_TIMESTAMP(started) AS st,
                connectable, agent, peer_id,
                UNIX_TIMESTAMP(last_action) AS la,
                UNIX_TIMESTAMP(prev_action) AS pa,
                userid, users.username, users.class
            FROM peers
            INNER JOIN users ON peers.userid = users.id
            WHERE torrent = $id
        ") or sqlerr(__FILE__, __LINE__);

        while ($subrow = mysqli_fetch_assoc($subres)) {
            if (($subrow['seeder'] ?? 'no') === 'yes') {
                $seeders[] = $subrow;
            } else {
                $downloaders[] = $subrow;
            }
        }

        $seed_sort = static function ($a, $b): int {
            $x = (int)($a['uploaded'] ?? 0);
            $y = (int)($b['uploaded'] ?? 0);
            return $y <=> $x;
        };
        $leech_sort = static function ($a, $b) use ($seed_sort): int {
            if (isset($_GET['usort'])) {
                return $seed_sort($a, $b);
            }
            $x = (int)($a['to_go'] ?? 0);
            $y = (int)($b['to_go'] ?? 0);
            return $x <=> $y;
        };

        usort($seeders, $seed_sort);
        usort($downloaders, $leech_sort);

        tr("<a name=\"seeders\">{$tracker_lang['details_seeding']}</a><br /><a href=\"details.php?id=$id$keepget\" class=\"sublink\">[{$tracker_lang['close_list']}]</a>", dltable($tracker_lang['details_seeding'], $seeders, $row), 1);
        tr("<a name=\"leechers\">{$tracker_lang['details_leeching']}</a><br /><a href=\"details.php?id=$id$keepget\" class=\"sublink\">[{$tracker_lang['close_list']}]</a>", dltable($tracker_lang['details_leeching'], $downloaders, $row), 1);
    }

    // ---- МУЛЬТИТРЕКЕР (FIX) ----
    if (($row['multitracker'] ?? 'no') === 'yes') {
        if (!empty($announces_a)) {
            $anns = [];
            foreach ($announces_a as $announce) {
                $aUrl = htmlspecialchars_uni((string)($announce['url'] ?? ''));
                $state = (string)($announce['state'] ?? '');
                if ($state === 'ok') {
                    $anns[] = '<li><b>' . $aUrl . '</b> — раздают: <b>' . (int)($announce['seeders'] ?? 0) . '</b>, качают: <b>' . (int)($announce['leechers'] ?? 0) . '</b></li>';
                } else {
                    $err = htmlspecialchars_uni((string)($announce['error'] ?? ''));
                    $anns[] = '<li><span style="color:red"><b>' . $aUrl . '</b></span> — ошибка: ' . $err . '</li>';
                }
            }

            $update_link = '';
            $last_mt_update = (string)($row['last_mt_update'] ?? '0000-00-00 00:00:00');
            $lastTs = strtotime($last_mt_update);
            if ($lastTs === false) {
                $lastTs = 0;
            }

            if ($lastTs < (TIMENOW - 3600) && !empty($CURUSER)) {
                $update_link .= '<br />Данные могли устареть. <a href="update_multi.php?id=' . $id . '" onclick="update_multi(); return false;">' . $tracker_lang['details_update_multitracker'] . '</a>';
            }

            if ($last_mt_update === '0000-00-00 00:00:00' || $lastTs <= 0) {
                $update_link .= '<br />' . $tracker_lang['details_update_last_mt_update'] . ' <b>' . $tracker_lang['never'] . '</b>';
            } else {
                $update_link .= '<br />' . $tracker_lang['details_update_last_mt_update'] . ' <b>' . get_et($lastTs) . '</b> ' . $tracker_lang['ago'];
            }

            // FIX: implode должен быть по '' а не implode($anns)
            tr($tracker_lang['details_multitracker'], '<div id="update_multi"><ul style="margin:0;">' . implode('', $anns) . '</ul>' . $update_link . '</div>', 1);
        } else {
            tr($tracker_lang['details_multitracker'], 'Мультитрекер включён, но список трекеров пуст.', 1);
        }
    }

    // ---- snatched ----
    if ((int)$row['times_completed'] > 0) {
        $limited = 10;
        // (pager/limit для snatched тут не был корректно определён в исходнике; оставляем без лимита, чтобы не ломать)
        $res2 = sql_query("
            SELECT
                users.id, users.username, users.title, users.uploaded, users.downloaded, users.donor, users.enabled,
                users.warned, users.last_access, users.class,
                snatched.startdat, snatched.last_action, snatched.completedat, snatched.seeder, snatched.userid,
                snatched.uploaded AS sn_up, snatched.downloaded AS sn_dn
            FROM snatched
            INNER JOIN users ON snatched.userid = users.id
            WHERE snatched.finished='yes' AND snatched.torrent = " . (int)$id . "
            ORDER BY users.class DESC
        ") or sqlerr(__FILE__, __LINE__);

        $snatched_full = "<table width=\"100%\" class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n";
        $snatched_full .= "<tr>
            <td class=\"colhead\">Юзер</td>
            <td class=\"colhead\">Раздал</td>
            <td class=\"colhead\">Скачал</td>
            <td class=\"colhead\">Рейтинг</td>
            <td class=\"colhead\" align=\"center\">Начал / Закончил</td>
            <td class=\"colhead\" align=\"center\">Действие</td>
            <td class=\"colhead\" align=\"center\">Сидирует</td>
            <td class=\"colhead\" align=\"center\">ЛС</td>
        </tr>";

        $snatched_small = [];
        while ($arr = mysqli_fetch_assoc($res2)) {
            $ratio = '---';
            if ((int)$arr['downloaded'] > 0) {
                $ratio = number_format((float)$arr['uploaded'] / (float)$arr['downloaded'], 2);
            } elseif ((int)$arr['uploaded'] > 0) {
                $ratio = 'Inf.';
            }

            $ratio2 = '---';
            if ((int)$arr['sn_dn'] > 0) {
                $ratio2 = number_format((float)$arr['sn_up'] / (float)$arr['sn_dn'], 2);
                $ratio2 = "<font color=\"" . get_ratio_color((float)$ratio2) . "\">$ratio2</font>";
            } elseif ((int)$arr['sn_up'] > 0) {
                $ratio2 = 'Inf.';
            }

            $uploaded = mksize((int)$arr['uploaded']);
            $downloaded = mksize((int)$arr['downloaded']);
            $uploaded2 = mksize((int)$arr['sn_up']);
            $downloaded2 = mksize((int)$arr['sn_dn']);

            $snatched_small[] =
                "<a href=\"userdetails.php?id=" . (int)$arr['userid'] . "\">" .
                get_user_class_color((int)$arr['class'], (string)$arr['username']) .
                " (<font color=\"" . get_ratio_color((float)$ratio) . "\">$ratio</font>)</a>";

            $snatched_full .= "<tr>
                <td><a href=\"userdetails.php?id=" . (int)$arr['userid'] . "\">" . get_user_class_color((int)$arr['class'], (string)$arr['username']) . "</a>" . get_user_icons($arr) . "</td>
                <td><nobr>$uploaded&nbsp;Общего<br>$uploaded2&nbsp;Торрент</nobr></td>
                <td><nobr>$downloaded&nbsp;Общего<br>$downloaded2&nbsp;Торрент</nobr></td>
                <td><nobr>$ratio&nbsp;Общего<br>$ratio2&nbsp;Торрент</nobr></td>
                <td align=\"center\"><nobr>" . htmlspecialchars_uni((string)$arr['startdat']) . "<br />" . htmlspecialchars_uni((string)$arr['completedat']) . "</nobr></td>
                <td align=\"center\"><nobr>" . htmlspecialchars_uni((string)$arr['last_action']) . "</nobr></td>
                <td align=\"center\">" . (($arr['seeder'] ?? 'no') === 'yes' ? "<b><font color=\"green\">Да</font></b>" : "<b><font color=\"red\">Нет</font></b>") . "</td>
                <td align=\"center\"><a href=\"message.php?action=sendmessage&amp;receiver=" . (int)$arr['userid'] . "\"><img src=\"$pic_base_url/button_pm.gif\" border=\"0\" alt=\"PM\"></a></td>
            </tr>\n";
        }
        $snatched_full .= "</table>\n";

        ?>
        <script language="javascript" type="text/javascript" src="js/show_hide.js"></script>
        <?php

        $reseed_button = '';
        if ((int)$row['seeders'] === 0 || ((int)$row['seeders'] > 0 && ((int)$row['leechers'] / (int)$row['seeders'] >= 2))) {
            $reseed_button = "<form action=\"takereseed.php\"><input type=\"hidden\" name=\"torrent\" value=\"$id\" /><input type=\"submit\" value=\"Позвать скачавших\" /></form>";
        }

        if (empty($_GET['snatched'])) {
            tr(
                "Скачавшие<br /><a href=\"details.php?id=$id&amp;snatched=1#snatched\" class=\"sublink\">[{$tracker_lang['open_list']}]</a>",
                '<a href="javascript: show_hide(\'s1\')"><img border="0" src="' . $pic_base_url . '/plus.gif" id="pics1"></a>' .
                '<div id="ss1" style="display:none;">' . implode(", ", $snatched_small) . $reseed_button . '</div>',
                1
            );
        } else {
            tr("Скачавшие<br /><a href=\"details.php?id=$id\" class=\"sublink\" name=\"snatched\">[{$tracker_lang['close_list']}]</a>", $snatched_full, 1);
        }
    }

    tr($tracker_lang['torrent_info'], "<a href=\"torrent_info.php?id={$id}\">{$tracker_lang['show_data']}</a>", 1);

    // ---- thanks ----
    $torrentid = $id;

    $thanked_sql = sql_query("SELECT thanks.userid, users.username, users.class FROM thanks INNER JOIN users ON thanks.userid = users.id WHERE thanks.torrentid = $torrentid");
    $countThanks = $thanked_sql ? mysqli_num_rows($thanked_sql) : 0;

    $can_not_thanks = false;
    if ($countThanks === 0) {
        $thanks_list = $tracker_lang['none_yet'];
    } else {
        $thanksby = [];
        while ($thanked_row = mysqli_fetch_assoc($thanked_sql)) {
            if (!empty($CURUSER) && (int)$thanked_row['userid'] === (int)$CURUSER['id']) {
                $can_not_thanks = true;
            }
            $thanksby[] = "<a href=\"userdetails.php?id=" . (int)$thanked_row['userid'] . "\">" .
                get_user_class_color((int)$thanked_row['class'], (string)$thanked_row['username']) .
                "</a>";
        }
        $thanks_list = $thanksby ? implode(', ', $thanksby) : $tracker_lang['none_yet'];
    }

    if (empty($CURUSER) || (int)$row['owner'] === (int)$CURUSER['id']) {
        $can_not_thanks = true;
    }

    $thanks_form = "<div id=\"ajax\"><form action=\"thanks.php\" method=\"post\">
        <input type=\"submit\" name=\"submit\" onclick=\"send(); return false;\" value=\"{$tracker_lang['thanks']}\"" . ($can_not_thanks ? " disabled" : "") . ">
        <input type=\"hidden\" name=\"torrentid\" value=\"{$torrentid}\"> {$thanks_list}
    </form></div>";

    ?>
    <script language="javascript" type="text/javascript" src="js/ajax.js"></script>
    <script type="text/javascript">
    function send() {
        var ajax = new tbdev_ajax('thanks.php');
        ajax.onShow('');
        ajax.setVar("torrentid", <?= (int)$torrentid ?>);
        ajax.setVar("ajax", "yes");
        ajax.method = 'POST';
        ajax.element = 'ajax';
        ajax.sendAJAX("");
    }

    function update_multi() {
        var ajax = new tbdev_ajax('update_multi.php');
        ajax.onShow('');
        ajax.setVar("id", <?= (int)$torrentid ?>);
        ajax.setVar("ajax", "yes");
        ajax.method = 'GET';
        ajax.element = 'update_multi';
        ajax.sendAJAX("");
    }
    </script>
    <div id="loading-layer" style="display:none;font-family:Verdana;font-size:11px;width:200px;height:50px;background:#FFF;padding:10px;text-align:center;border:1px solid #000">
        <div style="font-weight:bold" id="loading-layer-text"><?= htmlspecialchars_uni((string)$tracker_lang['ajax_loading']) ?></div><br />
        <img src="<?= $pic_base_url ?>/loading.gif" border="0" alt="loading" />
    </div>
    <?php

    tr($tracker_lang['said_thanks'], $thanks_form, 1);

    print("</table></p>\n");
} else {
    stdhead($tracker_lang['comments_for'] . ' "' . htmlspecialchars_decode((string)$row['name']) . '"');
    print("<h1>{$tracker_lang['comments_for']} <a href=\"details.php?id={$id}\">" . htmlspecialchars_uni((string)$row['name']) . "</a></h1>\n");
}

print("<p><a name=\"startcomments\"></a></p>\n");

// ---- comments count (FIX) ----
$subres = sql_query("SELECT COUNT(*) AS c FROM comments WHERE torrent = $id") or sqlerr(__FILE__, __LINE__);
$subrow = $subres ? mysqli_fetch_assoc($subres) : ['c' => 0];
$count = (int)($subrow['c'] ?? 0);

$limited = 10;

if ($count === 0) {
    print("<table style=\"margin-top:2px;\" cellpadding=\"5\" width=\"100%\">");
    print("<tr><td class=\"colhead\" align=\"left\" colspan=\"2\">");
    print("<div style=\"float:left;width:auto;\" align=\"left\"> :: {$tracker_lang['comments_list']}</div>");
    if (!empty($CURUSER)) {
        print("<div align=\"right\"><a href=\"#comments\" class=\"altlink_white\">{$tracker_lang['comments_add']}</a></div>");
    }
    print("</td></tr><tr><td align=\"center\">");
    print("Комментариев нет." . (!empty($CURUSER) ? " <a href=\"#comments\">Желаете добавить?</a>" : ""));
    print("</td></tr></table><br>");

    if (!empty($CURUSER)) {
        print("<table style=\"margin-top:2px;\" cellpadding=\"5\" width=\"100%\">");
        print("<tr><td class=\"colhead\" align=\"left\" colspan=\"2\"> <a name=\"comments\">&nbsp;</a><b>:: Без комментариев</b></td></tr>");
        print("<tr><td align=\"center\">");
        print("<form name=\"comment\" method=\"post\" action=\"comment.php?action=add\">");
        print("<div>");
        textbbcode("comment", "text", "");
        print("</div>");
        print("</td></tr><tr><td align=\"center\" colspan=\"2\">");
        print("<input type=\"hidden\" name=\"tid\" value=\"$id\"/>");
        print("<input type=\"submit\" class=\"btn\" value=\"Разместить комментарий\" />");
        print("</td></tr></form></table>");
    }
} else {
    list($pagertop, $pagerbottom, $limit) = pager($limited, $count, "details.php?id=$id&", ['lastpagedefault' => 1]);

    $subres = sql_query("
        SELECT
            cp.text_hash, cp.text_parsed,
            c.id, c.ip, c.text, c.user, c.added, c.editedby, c.editedat,
            u.avatar, u.warned, u.username, u.title, u.class, u.donor, u.downloaded, u.uploaded, u.gender, u.last_access,
            e.username AS editedbyname
        FROM comments AS c
        LEFT JOIN users AS u ON c.user = u.id
        LEFT JOIN users AS e ON c.editedby = e.id
        LEFT JOIN comments_parsed AS cp ON cp.cid = c.id
        WHERE c.torrent = $id
        ORDER BY c.id $limit
    ") or sqlerr(__FILE__, __LINE__);

    $allrows = [];
    while ($subrow = mysqli_fetch_assoc($subres)) {
        $allrows[] = $subrow;
    }

    print("<table class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"100%\">");
    print("<tr><td class=\"colhead\" align=\"center\">");
    print("<div style=\"float:left;width:auto;\" align=\"left\"> :: {$tracker_lang['comments_list']}</div>");
    if (!empty($CURUSER)) {
        print("<div align=\"right\"><a href=\"#comments\" class=\"altlink_white\">{$tracker_lang['comments_add']}</a></div>");
    }
    print("</td></tr>");

    print("<tr><td>$pagertop</td></tr>");
    print("<tr><td>");
    commenttable($allrows);
    print("</td></tr>");
    print("<tr><td>$pagerbottom</td></tr>");
    print("</table>");

    if (!empty($CURUSER)) {
        print("<table style=\"margin-top:2px;\" cellpadding=\"5\" width=\"100%\">");
        print("<tr><td class=\"colhead\" align=\"left\" colspan=\"2\"> <a name=\"comments\">&nbsp;</a><b>:: Добавить комментарий к торренту</b></td></tr>");
        print("<tr><td width=\"100%\" align=\"center\">");
        print("<form name=\"comment\" method=\"post\" action=\"comment.php?action=add\">");
        print("<center><table border=\"0\"><tr><td class=\"clear\">");
        print("<div align=\"center\">" . textbbcode("comment", "text", "", 1) . "</div>");
        print("</td></tr></table></center>");
        print("</td></tr><tr><td align=\"center\" colspan=\"2\">");
        print("<input type=\"hidden\" name=\"tid\" value=\"$id\"/>");
        print("<input type=\"submit\" class=\"btn\" value=\"Разместить комментарий\" />");
        print("</td></tr></form></table>");
    }
}

stdfoot();
