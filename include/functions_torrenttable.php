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


# IMPORTANT: Do not edit below unless you know what you are doing!
if (!defined('IN_TRACKER')) {
    die('Hacking attempt!');
}

require_once __DIR__ . '/multitracker.php';

function torrenttable($res, $variant = "index")
{
    global $pic_base_url, $CURUSER, $use_wait, $use_ttl, $ttl_days, $tracker_lang;

    // --- Normalize CURUSER (avoid undefined keys)
    $CURUSER = is_array($CURUSER ?? null) ? $CURUSER : [];
    $CURUSER += [
        'id' => 0,
        'class' => 0,
        'uploaded' => 0,
        'downloaded' => 0,
        'torrent_columns' => 'category,size,comments,seeders,leechers,uploader,added',
    ];

    $userId    = (int)$CURUSER['id'];
    $userClass = (int)$CURUSER['class'];
    $isMod     = (get_user_class() >= UC_MODERATOR);
    $selectedColumns = array_filter(explode(',', (string)$CURUSER['torrent_columns']));
    $showColumn = static fn(string $column): bool => in_array($column, $selectedColumns, true);

    // --- WAIT logic (safe)
    $wait = 0;
    if (!empty($use_wait) && $userId > 0 && $userClass < UC_VIP) {
        $uploaded   = (float)$CURUSER['uploaded'];
        $downloaded = (float)$CURUSER['downloaded'];

        $gigs  = $uploaded / (1024 * 1024 * 1024);
        $ratio = ($downloaded > 0) ? ($uploaded / $downloaded) : 0.0;

        if ($ratio < 0.5 || $gigs < 5) $wait = 48;
        elseif ($ratio < 0.65 || $gigs < 6.5) $wait = 24;
        elseif ($ratio < 0.8 || $gigs < 8) $wait = 12;
        elseif ($ratio < 0.95 || $gigs < 9.5) $wait = 6;
        else $wait = 0;
    }

    // --- Script by variant
    $script = "browse.php";
    if ($variant === "mytorrents") $script = "mytorrents.php";
    elseif ($variant === "bookmarks") $script = "bookmarks.php";

    // --- Preserve other GET params safely
    $params = $_GET ?? [];
    unset($params['sort'], $params['type']);
    $oldlink = $params ? (http_build_query($params, '', '&', PHP_QUERY_RFC3986) . '&') : '';

    $sort = (string)($_GET['sort'] ?? '');
    $type = strtolower((string)($_GET['type'] ?? ''));
    $type = ($type === 'asc' || $type === 'desc') ? $type : 'desc';

    $toggle = static function (string $currentSort, string $currentType, string $col): string {
        if ($currentSort === $col) {
            return ($currentType === 'desc') ? 'asc' : 'desc';
        }
        return ($col === '1') ? 'asc' : 'desc'; // default: name asc, others desc
    };

    $link1  = $toggle($sort, $type, '1');   // name
    $link3  = $toggle($sort, $type, '3');   // comments
    $link5  = $toggle($sort, $type, '5');   // size
    $link7  = $toggle($sort, $type, '7');   // seeders
    $link8  = $toggle($sort, $type, '8');   // leechers
    $link9  = $toggle($sort, $type, '9');   // uploader
    $link4  = $toggle($sort, $type, '4');   // added
    $link10 = $toggle($sort, $type, '10');  // moderated/changed
    $link11 = $toggle($sort, $type, '11');  // category
    $link12 = $toggle($sort, $type, '12');  // views
    $link13 = $toggle($sort, $type, '13');  // hits
    $link2  = $toggle($sort, $type, '2');   // files

    // --- Column count for colspan
    $colCount = 1; // Name
    foreach (['category', 'tags', 'size', 'numfiles', 'views', 'hits', 'comments', 'seeders', 'leechers', 'uploader', 'added'] as $column) {
        if ($showColumn($column)) $colCount++;
    }
    if ($wait) $colCount++;
    if ($variant === "mytorrents") $colCount++;

    // Mod/bookmarks extra columns
    if ($isMod && $variant === 'index') $colCount += 2; // Изменен + delete
    if ($variant === 'bookmarks') $colCount += 1;       // delete bookmark

    $out = '';
    $previousDate = '';
    $newCount = 0;
    $totals = [
        'torrents' => 0,
        'size' => 0,
        'seeders' => 0,
        'leechers' => 0,
        'comments' => 0,
        'files' => 0,
    ];
    $sortedCellClass = static fn(string $column) => $sort === $column ? ' class="row2 torrenttable-sorted"' : '';

    // ===================== HEADER =====================
    $out .= "<tr>\n";
    if ($showColumn('category')) {
        $out .= '<td class="colhead" align="center"><a rel="nofollow" title="Сортировать по категории" href="' .
            $script . '?' . $oldlink . 'sort=11&type=' . $link11 . '" class="altlink_white">' .
            $tracker_lang['type'] . "</a></td>\n";
    }
    $out .= '<td class="colhead" align="left">'
        . '<a href="' . $script . '?' . $oldlink . 'sort=1&type=' . $link1 . '" class="altlink_white">' . $tracker_lang['name'] . '</a>'
        . "</td>\n";

    if ($wait) {
        $out .= '<td class="colhead" align="center">' . $tracker_lang['wait'] . "</td>\n";
    }
    if ($variant === "mytorrents") {
        $out .= '<td class="colhead" align="center">' . $tracker_lang['visible'] . "</td>\n";
    }

    if ($showColumn('tags')) $out .= '<td class="colhead" align="center">Теги</td>' . "\n";
    if ($showColumn('comments')) $out .= '<td class="colhead" align="center"><a href="' . $script . '?' . $oldlink . 'sort=3&type=' . $link3 . '" class="altlink_white">' . $tracker_lang['comments'] . "</a></td>\n";
    if ($showColumn('size')) $out .= '<td class="colhead" align="center"><a href="' . $script . '?' . $oldlink . 'sort=5&type=' . $link5 . '" class="altlink_white">' . $tracker_lang['size'] . "</a></td>\n";
    if ($showColumn('numfiles')) $out .= '<td class="colhead" align="center"><a rel="nofollow" href="' . $script . '?' . $oldlink . 'sort=2&type=' . $link2 . '" class="altlink_white">Файлы</a></td>' . "\n";
    if ($showColumn('views')) $out .= '<td class="colhead" align="center"><a rel="nofollow" href="' . $script . '?' . $oldlink . 'sort=12&type=' . $link12 . '" class="altlink_white">Просмотры</a></td>' . "\n";
    if ($showColumn('hits')) $out .= '<td class="colhead" align="center"><a rel="nofollow" href="' . $script . '?' . $oldlink . 'sort=13&type=' . $link13 . '" class="altlink_white">Взяли</a></td>' . "\n";
    if ($showColumn('seeders')) $out .= '<td class="colhead" align="center"><a href="' . $script . '?' . $oldlink . 'sort=7&type=' . $link7 . '" class="altlink_white">' . $tracker_lang['seeds'] . "</a></td>\n";
    if ($showColumn('leechers')) $out .= '<td class="colhead" align="center"><a href="' . $script . '?' . $oldlink . 'sort=8&type=' . $link8 . '" class="altlink_white">' . $tracker_lang['leechers'] . "</a></td>\n";
    if ($showColumn('added')) $out .= '<td class="colhead" align="center"><a href="' . $script . '?' . $oldlink . 'sort=4&type=' . $link4 . '" class="altlink_white">Добавлен</a></td>' . "\n";
    if ($showColumn('uploader')) $out .= '<td class="colhead" align="center"><a href="' . $script . '?' . $oldlink . 'sort=9&type=' . $link9 . '" class="altlink_white">Раздает</a></td>' . "\n";

    if ($isMod && $variant === "index") {
        $out .= '<td class="colhead" align="center"><a href="' . $script . '?' . $oldlink . 'sort=10&type=' . $link10 . '" class="altlink_white">Изменен</a></td>' . "\n";
        $out .= '<td class="colhead" align="center">' . $tracker_lang['delete'] . "</td>\n";
    }

    if ($variant === "bookmarks") {
        $out .= '<td class="colhead" align="center">' . $tracker_lang['delete'] . "</td>\n";
    }

    $out .= "</tr>\n";
    $out .= "<tbody id=\"highlighted\">\n";

    // ===================== FORMS =====================
    if ($isMod && $variant === "index") {
        $out .= "<form method=\"post\" action=\"deltorrent.php?mode=delete\">\n";
    } elseif ($variant === "bookmarks") {
        $out .= "<form method=\"post\" action=\"takedelbookmark.php\">\n";
    }

    // ===================== ROWS =====================
    while ($row = mysqli_fetch_assoc($res)) {
        $id = (int)($row['id'] ?? 0);
        $added = (string)($row['added'] ?? '');
        $addedTimestamp = strtotime($added) ?: 0;
        $addedDate = $addedTimestamp > 0 ? date('Y-m-d', $addedTimestamp) : '';

        if ($variant === 'index' && $sort === '' && $addedDate !== '' && $addedDate !== $previousDate) {
            $out .= '<tr class="torrenttable-day"><td colspan="' . $colCount . '"><b>' .
                htmlspecialchars(date('d.m.Y', $addedTimestamp), ENT_QUOTES, 'UTF-8') .
                '</b></td></tr>' . "\n";
            $previousDate = $addedDate;
        }

        $totals['torrents']++;
        $totals['size'] += (int)($row['size'] ?? 0);
        $totals['seeders'] += (int)($row['seeders'] ?? 0);
        $totals['leechers'] += (int)($row['leechers'] ?? 0);
        $totals['comments'] += (int)($row['comments'] ?? 0);
        $totals['files'] += (int)($row['numfiles'] ?? 0);

        $sticky = ((string)($row['not_sticky'] ?? 'yes') === 'no');
        $out .= '<tr id="torrent-row-' . $id . '"' . ($sticky ? ' class="highlight"' : '') . ">\n";

        // ---- TYPE (category)
        if ($showColumn('category')) {
        $out .= '<td align="center" style="padding:0"' . $sortedCellClass('11') . '>';
        if (!empty($row['cat_name'])) {
            $catId   = (int)($row['category'] ?? 0);
            $catName = (string)($row['cat_name'] ?? '');
            $out .= '<a href="browse.php?cat=' . $catId . '">';
            if (!empty($row['cat_pic'])) {
                $out .= '<img border="0" src="' . $pic_base_url . '/cats/' . htmlspecialchars((string)$row['cat_pic'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') . '" />';
            } else {
                $out .= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8');
            }
            $out .= '</a>';
        } else {
            $out .= '-';
        }
        $out .= "</td>\n";
        }

        // ---- NAME + icons (same behaviour, just safer)
        $dispname = (string)($row['name'] ?? '');

        $freepic = '';
        $free = (string)($row['free'] ?? 'no');
        if ($free === 'yes') {
            $freepic = '<img src="' . $pic_base_url . '/freedownload.gif" title="' . htmlspecialchars($tracker_lang['golden'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($tracker_lang['golden'], ENT_QUOTES, 'UTF-8') . '">';
        } elseif ($free === 'silver') {
            $freepic = '<img src="' . $pic_base_url . '/silverdownload.gif" title="' . htmlspecialchars($tracker_lang['silver'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($tracker_lang['silver'], ENT_QUOTES, 'UTF-8') . '">';
        }

        $out .= '<td align="left"' . $sortedCellClass('1') . '>';
        $out .= '<button type="button" class="torrent-details-toggle" aria-expanded="false" aria-controls="torrent-details-' .
            $id . '" onclick="return toggleTorrentDetails(' . $id . ',this)" title="Показать подробности">+</button> ';
        if ($sticky) $out .= 'Важный: ';

        $out .= '<a href="details.php?';
        if ($variant === "mytorrents") {
            $out .= 'returnto=' . urlencode((string)($_SERVER['REQUEST_URI'] ?? '')) . '&amp;';
        }
        $out .= 'id=' . $id;
        if ($variant === "index" || $variant === "bookmarks") {
            $out .= '&amp;hit=1';
        }
        $out .= '"><b>' . htmlspecialchars_uni($dispname) . '</b></a> ' . $freepic . "\n";

        if ($variant !== "bookmarks" && $userId > 0) {
            $out .= '<a href="bookmark.php?torrent=' . $id . '"><img border="0" src="' . $pic_base_url . '/bookmark.gif" alt="' . htmlspecialchars($tracker_lang['bookmark_this'], ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($tracker_lang['bookmark_this'], ENT_QUOTES, 'UTF-8') . '" /></a>' . "\n";
        }

        $out .= '<a href="download.php?id=' . $id . '"><img src="' . $pic_base_url . '/download.gif" border="0" alt="' . htmlspecialchars($tracker_lang['download'], ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($tracker_lang['download'], ENT_QUOTES, 'UTF-8') . '"></a>' . "\n";

        // multitracker/magnet (no undefined $suffix)
        if ((string)($row['multitracker'] ?? 'no') === 'yes') {
            $out .= '<a href="' . htmlspecialchars((string)magnet(true, (string)($row['info_hash'] ?? ''), (string)($row['filename'] ?? ''), (int)($row['size'] ?? 0)), ENT_QUOTES, 'UTF-8') . '"><img src="' . $pic_base_url . '/magnet.png" border="0" alt="' . htmlspecialchars($tracker_lang['magnet'], ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($tracker_lang['magnet'], ENT_QUOTES, 'UTF-8') . '"></a>' . "\n";

            $lastUpd = (string)($row['last_mt_update'] ?? '');
            $lastUpdTs = strtotime($lastUpd) ?: 0;
            $allow_update = ($lastUpdTs < (TIMENOW - 3600));
            $suffix = $allow_update ? '_update' : '';

            $multi_image = '<img src="' . $pic_base_url . '/multitracker.png" border="0" alt="' . htmlspecialchars($tracker_lang['external_torrent' . $suffix], ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($tracker_lang['external_torrent' . $suffix], ENT_QUOTES, 'UTF-8') . '" />';
            $out .= $multi_image . "\n";

            $rowOwnerId = (int)($row['owner'] ?? 0);
            $canForce = $userId > 0 && ($userId === $rowOwnerId || $isMod);
            if ($userId > 0 && ($allow_update || $canForce)) {
                $force = $canForce ? 1 : 0;
                $token = multitracker_action_token($id, $userId);
                $out .= '<button type="button" class="btn" style="font-size:10px;padding:1px 4px;margin-left:3px" ' .
                    'onclick="return refreshMultitracker(' . $id . ',' . $force . ',\'' . $token . '\',this)" ' .
                    'title="Принудительно обновить статистику">Обновить</button>';
            }
        }

        // edit icon if owner/mod
        $ownerId = (int)($row['owner'] ?? 0);
        $owned = ($userId > 0 && ($userId === $ownerId || $isMod));
        if ($owned) {
            $out .= '<a href="edit.php?id=' . $id . '"><img border="0" src="' . $pic_base_url . '/pen.gif" alt="' . htmlspecialchars($tracker_lang['edit'], ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($tracker_lang['edit'], ENT_QUOTES, 'UTF-8') . '" /></a>' . "\n";
        }

        // "new" flag
        if ((int)($row['readtorrent'] ?? 1) === 0 && $variant === "index") {
            $out .= '<b><font color="red" size="1">[новый]</font></b>';
            $newCount++;
        }

        $out .= "</td>\n";

        // ---- WAIT column
        if ($wait) {
            $addedStr = (string)($row['added'] ?? '');
            $addedTs = ($addedStr !== '' ? strtotime($addedStr) : false);
            $elapsed = ($addedTs !== false) ? (int)floor((gmtime() - $addedTs) / 3600) : 999999;

            if ($elapsed < $wait) {
                $color = dechex((int)(floor(127 * ($wait - $elapsed) / 48 + 128) * 65536));
                $out .= '<td align="center"><nobr><a href="faq.php#dl8"><font color="' . $color . '">' . number_format($wait - $elapsed) . ' h</font></a></nobr></td>' . "\n";
            } else {
                $out .= '<td align="center"><nobr>' . $tracker_lang['no'] . '</nobr></td>' . "\n";
            }
        }

        // ---- VISIBLE (mytorrents)
        if ($variant === "mytorrents") {
            $out .= '<td align="right">';
            if ((string)($row['visible'] ?? 'yes') === 'no') {
                $out .= '<font color="red"><b>' . $tracker_lang['no'] . '</b></font>';
            } else {
                $out .= '<font color="green">' . $tracker_lang['yes'] . '</font>';
            }
            $out .= "</td>\n";
        }

        if ($showColumn('tags')) {
            $tagLinks = [];
            foreach (array_filter(array_map('trim', explode(',', (string)($row['keywords'] ?? '')))) as $tag) {
                $tagLinks[] = '<a rel="nofollow" href="browse.php?search=' . rawurlencode($tag) . '">' .
                    htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</a>';
            }
            $out .= '<td align="center">' . ($tagLinks ? implode(', ', $tagLinks) : '-') . "</td>\n";
        }

        // ---- Комм.
        if ($showColumn('comments')) {
        $comments = (int)($row['comments'] ?? 0);
        if ($comments > 0) {
            $href = ($variant === 'index')
                ? 'details.php?id=' . $id . '&amp;hit=1&amp;tocomm=1'
                : 'details.php?id=' . $id . '&amp;page=0#startcomments';
            $out .= '<td align="center"' . $sortedCellClass('3') . '><b><a href="' . $href . '">' . $comments . "</a></b></td>\n";
        } else {
            $out .= '<td align="center"' . $sortedCellClass('3') . '>0</td>' . "\n";
        }
        }

        // ---- Размер
        if ($showColumn('size')) $out .= '<td align="center"' . $sortedCellClass('5') . '>' . str_replace(" ", "<br />", mksize((int)($row['size'] ?? 0))) . "</td>\n";
        if ($showColumn('numfiles')) $out .= '<td align="center"' . $sortedCellClass('2') . '>' . (int)($row['numfiles'] ?? 0) . "</td>\n";
        if ($showColumn('views')) $out .= '<td align="center"' . $sortedCellClass('12') . '>' . (int)($row['views'] ?? 0) . "</td>\n";
        if ($showColumn('hits')) $out .= '<td align="center"' . $sortedCellClass('13') . '>' . (int)($row['hits'] ?? 0) . "</td>\n";

        // ---- Сидов
        if ($showColumn('seeders')) {
        $seeders = (int)($row['seeders'] ?? 0);
        if ($seeders > 0) {
            if ($variant === "index") {
                $leechers = (int)($row['leechers'] ?? 0);
                $slr = ($leechers > 0) ? ($seeders / $leechers) : 1;
                $out .= '<td align="center" id="torrent-seeders-' . $id . '"' . $sortedCellClass('7') . '><b><a href="details.php?id=' . $id . '&amp;hit=1&amp;toseeders=1"><font color='
                    . get_slr_color($slr) . '>' . $seeders . "</font></a></b></td>\n";
            } else {
                $out .= '<td align="center" id="torrent-seeders-' . $id . '"' . $sortedCellClass('7') . '><b><a class="' . linkcolor($seeders) . '" href="details.php?id=' . $id . '&amp;dllist=1#seeders">' . $seeders . "</a></b></td>\n";
            }
        } else {
            $out .= '<td align="center" id="torrent-seeders-' . $id . '"' . $sortedCellClass('7') . '><span class="' . linkcolor(0) . '">0</span></td>' . "\n";
        }
        }

        // ---- Пиров
        if ($showColumn('leechers')) {
        $leechers = (int)($row['leechers'] ?? 0);
        if ($leechers > 0) {
            if ($variant === "index") {
                $out .= '<td align="center" id="torrent-leechers-' . $id . '"' . $sortedCellClass('8') . '><b><a href="details.php?id=' . $id . '&amp;hit=1&amp;todlers=1">' . number_format($leechers) . "</a></b></td>\n";
            } else {
                $out .= '<td align="center" id="torrent-leechers-' . $id . '"' . $sortedCellClass('8') . '><b><a class="' . linkcolor($leechers) . '" href="details.php?id=' . $id . '&amp;dllist=1#leechers">' . $leechers . "</a></b></td>\n";
            }
        } else {
            $out .= '<td align="center" id="torrent-leechers-' . $id . '"' . $sortedCellClass('8') . '>0</td>' . "\n";
        }
        }

        // ---- Залит = дата/время added
        if ($showColumn('added')) {
            $elapsed = $addedTimestamp > 0 ? get_elapsed_time($addedTimestamp) . ' назад' : '-';
            $out .= '<td align="center"' . $sortedCellClass('4') . '><span title="' .
                htmlspecialchars($added, ENT_QUOTES, 'UTF-8') . '">' .
                htmlspecialchars($elapsed, ENT_QUOTES, 'UTF-8') . '</span></td>' . "\n";
        }

        // ---- Раздает = uploader (owner -> users)
        $uploaderCell = "<i>(unknown)</i>";
        if (!empty($row['username'])) {
            $uname  = htmlspecialchars_uni((string)$row['username']);
            $uclass = (int)($row['class'] ?? 0);
            $uploaderCell = '<a href="userdetails.php?id=' . $ownerId . '"><b>' . get_user_class_color($uclass, $uname) . '</b></a>';
        }
        if ($showColumn('uploader')) $out .= '<td align="center"' . $sortedCellClass('9') . '>' . $uploaderCell . "</td>\n";

        // ---- bookmarks checkbox
        if ($variant === "bookmarks") {
            $bid = (int)($row['bookmarkid'] ?? 0);
            $out .= '<td align="center"><input type="checkbox" name="delbookmark[]" value="' . $bid . "\" /></td>\n";
        }

        // ---- MOD columns
        if ($isMod && $variant === "index") {
            if ((string)($row["moderated"] ?? 'no') === "no") {
                $out .= '<td align="center"><font color="red"><b>Нет</b></font></td>' . "\n";
            } else {
                $mb = (int)($row['moderatedby'] ?? 0);
                $out .= '<td align="center"><a href="userdetails.php?id=' . $mb . '"><font color="green"><b>Да</b></font></a></td>' . "\n";
            }
            $out .= '<td align="center"><input type="checkbox" name="delete[]" value="' . $id . "\" /></td>\n";
        }

        $out .= "</tr>\n";

        $details = [];
        $details[] = '<b>Добавлен:</b> ' . htmlspecialchars($added ?: '-', ENT_QUOTES, 'UTF-8');
        $details[] = '<b>Размер:</b> ' . mksize((int)($row['size'] ?? 0));
        $details[] = '<b>Файлов:</b> ' . (int)($row['numfiles'] ?? 0);
        $details[] = '<b>Просмотров:</b> ' . (int)($row['views'] ?? 0);
        $details[] = '<b>Скачиваний:</b> ' . (int)($row['hits'] ?? 0);
        $details[] = '<b>Сиды / пиры:</b> ' . (int)($row['seeders'] ?? 0) . ' / ' . (int)($row['leechers'] ?? 0);
        if (!empty($row['keywords'])) {
            $details[] = '<b>Теги:</b> ' . htmlspecialchars((string)$row['keywords'], ENT_QUOTES, 'UTF-8');
        }
        $out .= '<tr id="torrent-details-' . $id . '" class="torrent-details-row" hidden><td colspan="' .
            $colCount . '"><div>' . implode(' &nbsp; | &nbsp; ', $details) . '</div></td></tr>' . "\n";
    }

    $out .= "</tbody>\n";

    // ===================== FOOTER ROWS =====================
    if ($totals['torrents'] > 0) {
        $out .= '<tr class="torrenttable-summary"><td colspan="' . $colCount . '" align="center">' .
            '<b>Раздач:</b> ' . number_format($totals['torrents']) .
            ' &nbsp; <b>Размер:</b> ' . mksize($totals['size']) .
            ' &nbsp; <b>Файлов:</b> ' . number_format($totals['files']) .
            ' &nbsp; <b>Комментариев:</b> ' . number_format($totals['comments']) .
            ' &nbsp; <b>Сиды / пиры:</b> ' . number_format($totals['seeders']) . ' / ' .
            number_format($totals['leechers']) . '</td></tr>' . "\n";
    }

    if ($variant === "index" && $userId > 0 && $newCount > 0) {
        $out .= '<tr><td class="colhead" colspan="' . $colCount . '" align="center"><a href="markread.php" class="altlink_white">Все торренты прочитаны</a></td></tr>' . "\n";
    }

    if ($variant === "index" && $isMod) {
        $out .= '<tr><td align="right" colspan="' . $colCount . '"><input type="submit" value="Удалить"></td></tr>' . "\n";
    }

    if ($variant === "bookmarks") {
        $out .= '<tr><td colspan="' . $colCount . '" align="right"><input type="submit" value="' . htmlspecialchars($tracker_lang['delete'], ENT_QUOTES, 'UTF-8') . '"></td></tr>' . "\n";
    }

    if (($isMod && $variant === "index") || $variant === "bookmarks") {
        $out .= "</form>\n";
    }

    $out .= <<<'HTML'
<style>
.torrent-details-toggle{width:20px;height:20px;padding:0;line-height:16px;cursor:pointer}
.torrent-details-row td{padding:8px 12px;background:#f7f7f7;text-align:left}
.torrenttable-day td{padding:5px 10px;background:#e9e9e9;text-align:left}
.torrenttable-summary td{padding:7px;background:#f2f2f2}
.torrenttable-sorted{box-shadow:inset 0 0 0 9999px rgba(255,196,92,.12)}
</style>
<script>
function toggleTorrentDetails(id, button) {
    var row = document.getElementById('torrent-details-' + id);
    if (!row) return false;
    var opening = row.hidden;
    row.hidden = !opening;
    button.textContent = opening ? '−' : '+';
    button.setAttribute('aria-expanded', opening ? 'true' : 'false');
    button.title = opening ? 'Скрыть подробности' : 'Показать подробности';
    return false;
}
</script>
HTML;

    if ($variant === 'index' && $userId > 0) {
        $out .= <<<'HTML'
<script>
function refreshMultitracker(id, force, token, button) {
    var oldText = button.textContent;
    button.disabled = true;
    button.textContent = '...';
    fetch('update_multi.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: new URLSearchParams({
            id: id,
            force: force,
            token: token,
            ajax: 'yes',
            format: 'json'
        })
    })
    .then(function (response) {
        return response.json().then(function (data) {
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Ошибка обновления');
            }
            return data;
        });
    })
    .then(function (data) {
        var seeders = document.getElementById('torrent-seeders-' + id);
        var leechers = document.getElementById('torrent-leechers-' + id);
        if (seeders) seeders.textContent = data.seeders;
        if (leechers) leechers.textContent = data.leechers;
        button.textContent = 'Готово';
        window.setTimeout(function () {
            button.textContent = oldText;
            button.disabled = false;
        }, 1500);
    })
    .catch(function (error) {
        button.textContent = oldText;
        button.disabled = false;
        alert(error.message);
    });
    return false;
}
</script>
HTML;
    }

    echo $out;
    return $out;
}
?>
