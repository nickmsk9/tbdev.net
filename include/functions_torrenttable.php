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

function torrenttable($res, $variant = "index")
{
    global $pic_base_url, $CURUSER, $use_wait, $use_ttl, $ttl_days, $tracker_lang;

    // --- Normalize CURUSER (avoid undefined keys)
    $CURUSER = is_array($CURUSER ?? null) ? $CURUSER : [];
    $CURUSER += ['id' => 0, 'class' => 0, 'uploaded' => 0, 'downloaded' => 0];

    $userId    = (int)$CURUSER['id'];
    $userClass = (int)$CURUSER['class'];
    $isMod     = (get_user_class() >= UC_MODERATOR);

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

    // --- Column count for colspan
    $colCount = 2; // Type + Name
    if ($wait) $colCount++;
    if ($variant === "mytorrents") $colCount++;

    // New right block columns: Комм, Размер, Сидов, Пиров, Залит(added), Раздает(uploader)
    $colCount += 6;

    // Mod/bookmarks extra columns
    if ($isMod && $variant === 'index') $colCount += 2; // Изменен + delete
    if ($variant === 'bookmarks') $colCount += 1;       // delete bookmark

    $out = '';

    // ===================== HEADER =====================
    $out .= "<tr>\n";
    $out .= '<td class="colhead" align="center">' . $tracker_lang['type'] . "</td>\n";
    $out .= '<td class="colhead" align="left">'
        . '<a href="' . $script . '?' . $oldlink . 'sort=1&type=' . $link1 . '" class="altlink_white">' . $tracker_lang['name'] . '</a>'
        . "</td>\n";

    if ($wait) {
        $out .= '<td class="colhead" align="center">' . $tracker_lang['wait'] . "</td>\n";
    }
    if ($variant === "mytorrents") {
        $out .= '<td class="colhead" align="center">' . $tracker_lang['visible'] . "</td>\n";
    }

    // Right columns you requested
    $out .= '<td class="colhead" align="center"><a href="' . $script . '?' . $oldlink . 'sort=3&type=' . $link3 . '" class="altlink_white">' . $tracker_lang['comments'] . "</a></td>\n";
    $out .= '<td class="colhead" align="center"><a href="' . $script . '?' . $oldlink . 'sort=5&type=' . $link5 . '" class="altlink_white">' . $tracker_lang['size'] . "</a></td>\n";
    $out .= '<td class="colhead" align="center"><a href="' . $script . '?' . $oldlink . 'sort=7&type=' . $link7 . '" class="altlink_white">' . $tracker_lang['seeds'] . "</a></td>\n";
    $out .= '<td class="colhead" align="center"><a href="' . $script . '?' . $oldlink . 'sort=8&type=' . $link8 . '" class="altlink_white">' . $tracker_lang['leechers'] . "</a></td>\n";

    // Залит = дата/время added (сортировка по sort=4)
    $out .= '<td class="colhead" align="center"><a href="' . $script . '?' . $oldlink . 'sort=4&type=' . $link4 . '" class="altlink_white">Залит</a></td>' . "\n";

    // Раздает = кто залил (uploader) (сортировка по sort=9)
    $out .= '<td class="colhead" align="center"><a href="' . $script . '?' . $oldlink . 'sort=9&type=' . $link9 . '" class="altlink_white">Раздает</a></td>' . "\n";

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

        $sticky = ((string)($row['not_sticky'] ?? 'yes') === 'no');
        $out .= '<tr' . ($sticky ? ' class="highlight"' : '') . ">\n";

        // ---- TYPE (category)
        $out .= '<td align="center" style="padding: 0px">';
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

        // ---- NAME + icons (same behaviour, just safer)
        $dispname = (string)($row['name'] ?? '');

        $freepic = '';
        $free = (string)($row['free'] ?? 'no');
        if ($free === 'yes') {
            $freepic = '<img src="' . $pic_base_url . '/freedownload.gif" title="' . htmlspecialchars($tracker_lang['golden'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($tracker_lang['golden'], ENT_QUOTES, 'UTF-8') . '">';
        } elseif ($free === 'silver') {
            $freepic = '<img src="' . $pic_base_url . '/silverdownload.gif" title="' . htmlspecialchars($tracker_lang['silver'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($tracker_lang['silver'], ENT_QUOTES, 'UTF-8') . '">';
        }

        $out .= '<td align="left">';
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
            $allow_update = ($lastUpd !== '' && strtotime($lastUpd) !== false && strtotime($lastUpd) < (TIMENOW - 3600));
            $suffix = $allow_update ? '_update' : '';

            $multi_image = '<img src="' . $pic_base_url . '/multitracker.png" border="0" alt="' . htmlspecialchars($tracker_lang['external_torrent' . $suffix], ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($tracker_lang['external_torrent' . $suffix], ENT_QUOTES, 'UTF-8') . '" />';
            if ($allow_update) {
                $multi_image = '<a href="update_multi.php?id=' . $id . '">' . $multi_image . '</a>';
            }
            $out .= $multi_image . "\n";
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

        // ---- Комм.
        $comments = (int)($row['comments'] ?? 0);
        if ($comments > 0) {
            $href = ($variant === 'index')
                ? 'details.php?id=' . $id . '&amp;hit=1&amp;tocomm=1'
                : 'details.php?id=' . $id . '&amp;page=0#startcomments';
            $out .= '<td align="center"><b><a href="' . $href . '">' . $comments . "</a></b></td>\n";
        } else {
            $out .= '<td align="center">0</td>' . "\n";
        }

        // ---- Размер
        $out .= '<td align="center">' . str_replace(" ", "<br />", mksize((int)($row['size'] ?? 0))) . "</td>\n";

        // ---- Сидов
        $seeders = (int)($row['seeders'] ?? 0);
        if ($seeders > 0) {
            if ($variant === "index") {
                $leechers = (int)($row['leechers'] ?? 0);
                $slr = ($leechers > 0) ? ($seeders / $leechers) : 1;
                $out .= '<td align="center"><b><a href="details.php?id=' . $id . '&amp;hit=1&amp;toseeders=1"><font color='
                    . get_slr_color($slr) . '>' . $seeders . "</font></a></b></td>\n";
            } else {
                $out .= '<td align="center"><b><a class="' . linkcolor($seeders) . '" href="details.php?id=' . $id . '&amp;dllist=1#seeders">' . $seeders . "</a></b></td>\n";
            }
        } else {
            $out .= '<td align="center"><span class="' . linkcolor(0) . '">0</span></td>' . "\n";
        }

        // ---- Пиров
        $leechers = (int)($row['leechers'] ?? 0);
        if ($leechers > 0) {
            if ($variant === "index") {
                $out .= '<td align="center"><b><a href="details.php?id=' . $id . '&amp;hit=1&amp;todlers=1">' . number_format($leechers) . "</a></b></td>\n";
            } else {
                $out .= '<td align="center"><b><a class="' . linkcolor($leechers) . '" href="details.php?id=' . $id . '&amp;dllist=1#leechers">' . $leechers . "</a></b></td>\n";
            }
        } else {
            $out .= '<td align="center">0</td>' . "\n";
        }

        // ---- Залит = дата/время added
        $added = (string)($row['added'] ?? '');
        // если хочешь перенос строки как раньше — можно str_replace(" ", "<br />", ...)
        $out .= '<td align="center"><nobr>' . htmlspecialchars($added, ENT_QUOTES, 'UTF-8') . "</nobr></td>\n";

        // ---- Раздает = uploader (owner -> users)
        $uploaderCell = "<i>(unknown)</i>";
        if (!empty($row['username'])) {
            $uname  = htmlspecialchars_uni((string)$row['username']);
            $uclass = (int)($row['class'] ?? 0);
            $uploaderCell = '<a href="userdetails.php?id=' . $ownerId . '"><b>' . get_user_class_color($uclass, $uname) . '</b></a>';
        }
        $out .= '<td align="center">' . $uploaderCell . "</td>\n";

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
    }

    $out .= "</tbody>\n";

    // ===================== FOOTER ROWS =====================
    if ($variant === "index" && $userId > 0) {
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

    echo $out;
    return $out;
}
?>
