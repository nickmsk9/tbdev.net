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

require_once('include/bittorrent.php');

// Dirty hack to prevent ghost Guests on website
$old_us = $use_sessions ?? 0;
$use_sessions = 0;
dbconn();
$use_sessions = $old_us;

if (!($_GET['token'] ?? '')) {
    loggedinorreturn(); // А вдруг гугл прийдет на такие страницы? Не надо...
}

require_once('include/scraper/httptscraper.php');
require_once('include/scraper/udptscraper.php');

function scrape($tid, $url, $info_hash) {
    $timeout = 5;
    $udp = new udptscraper($timeout);
    $http = new httptscraper($timeout);

    try {
        if (substr($url, 0, 6) == 'udp://') {
            $data = $udp->scrape($url, $info_hash);
        } else {
            $data = $http->scrape($url, $info_hash);
        }
        
        $data = $data[$info_hash] ?? ['seeders' => 0, 'leechers' => 0];
        
        sql_query('UPDATE torrents_scrape 
                   SET state = "ok", 
                       error = "", 
                       seeders = ' . intval($data['seeders']) . ', 
                       leechers = ' . intval($data['leechers']) . ' 
                   WHERE tid = ' . (int)$tid . ' 
                   AND url = ' . sqlesc($url)) or die(mysqli_error($GLOBALS["___mysqli_ston"]) . "\n");
        return true;
    } catch (ScraperException $e) {
        sql_query('UPDATE torrents_scrape 
                   SET state = "error", 
                       error = ' . sqlesc($e->getMessage()) . ', 
                       seeders = 0, 
                       leechers = 0 
                   WHERE tid = ' . (int)$tid . ' 
                   AND url = ' . sqlesc($url)) or die(mysqli_error($GLOBALS["___mysqli_ston"]) . "\n");
        return false;
    }
}

function generate_token($tid, $url, $info_hash) {
    return md5(implode('', [$tid, $url, $info_hash, COOKIE_SALT ?? 'default_salt']));
}

function check_token($token, $tid, $url, $info_hash) {
    return $token === md5(implode('', [$tid, $url, $info_hash, COOKIE_SALT ?? 'default_salt']));
}

$tid = intval($_GET['id'] ?? 0);

if (!$tid) {
    die('WTF?!');
}

if (($_GET['info_hash'] ?? '') && ($_GET['url'] ?? '')) {
    $token = strval($_GET['token'] ?? '');
    $url = strval($_GET['url'] ?? '');
    $info_hash = strval($_GET['info_hash'] ?? '');
    
    if (strlen($info_hash) != 40) {
        die('Invalid len info_hash supplied');
    }
    
    if (!check_token($token, $tid, $url, $info_hash)) {
        die('Invalid token');
    }
    
    echo scrape($tid, $url, $info_hash) ? '1' : '0';
    exit;
}

// Исправленная строка: добавляем проверку результата
$result = sql_query('SELECT name, visible, multitracker, last_mt_update FROM torrents WHERE id = ' . $tid);
if (!$result || mysqli_num_rows($result) == 0) {
    stderr($tracker_lang['error'] ?? 'Ошибка', "Такого торрента нет.");
}

list($name, $cur_visible, $multitracker, $last_mt_update) = mysqli_fetch_row($result);

if ($name == '' || ($multitracker ?? '') != 'yes') {
    stderr($tracker_lang['error'] ?? 'Ошибка', "Такого торрента нет, или он не мультитрекерный.");
}

// Исправленная проверка даты
$lastUpdateTime = strtotime($last_mt_update);
if ($lastUpdateTime === false) {
    $lastUpdateTime = 0;
}

if ($lastUpdateTime > (TIMENOW - 3600)) {
    stderr($tracker_lang['error'] ?? 'Ошибка', "Вы пытаетесь обновить мультитрекер слишком часто. Разрешено это делать не чаще 1 раза в час.");
}

$anns_r = sql_query('SELECT info_hash, url FROM torrents_scrape WHERE tid = ' . $tid);

$success = 0;
$works = [];

while ($ann = mysqli_fetch_assoc($anns_r)) {
    $works[] = $ann;
}

if (empty($works)) {
    stderr($tracker_lang['error'] ?? 'Ошибка', "Нет трекеров для обновления.");
}

if (function_exists('curl_multi_init')) {
    $multi = curl_multi_init();
    $channels = [];
    
    foreach ($works as $work) {
        $url = $work['url'] ?? '';
        $info_hash = $work['info_hash'] ?? '';
        
        if (empty($url) || empty($info_hash)) {
            continue;
        }
        
        $token = generate_token($tid, $url, $info_hash);
        $scrapeUrl = ($DEFAULTBASEURL ?? '') . '/update_multi.php?id=' . $tid . '&url=' . urlencode($url) . '&info_hash=' . urlencode($info_hash) . '&token=' . $token;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $scrapeUrl);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        curl_multi_add_handle($multi, $ch);
        $channels[] = $ch;
    }
    
    $running = null;
    do {
        $status = curl_multi_exec($multi, $running);
        if ($running) {
            curl_multi_select($multi, 0.1);
        }
    } while ($running && $status == CURLM_OK);
    
    foreach ($channels as $ch) {
        $content = curl_multi_getcontent($ch);
        if ($content === '1') {
            $success++;
        }
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($multi);
} else {
    // Без curl_multi
    foreach ($works as $work) {
        if (scrape($tid, $work['url'] ?? '', $work['info_hash'] ?? '')) {
            $success++;
        }
    }
}

// Исправленный UPDATE запрос - убираем NOW() для last_mt_update если он не поддерживается
$currentTime = date('Y-m-d H:i:s');
sql_query("UPDATE torrents AS t 
           INNER JOIN (
               SELECT ts.tid, 
                      SUM(ts.seeders) AS sum_seeders, 
                      SUM(ts.leechers) AS sum_leechers 
               FROM torrents_scrape AS ts 
               WHERE ts.tid = $tid 
               GROUP BY ts.tid
           ) AS ts ON ts.tid = t.id 
           SET t.remote_seeders = ts.sum_seeders, 
               t.remote_leechers = ts.sum_leechers, 
               t.last_action = '$currentTime', 
               t.last_mt_update = '$currentTime', 
               t.visible = IF(ts.sum_seeders > 0, 'yes', t.visible) 
           WHERE t.id = $tid") or die(mysqli_error($GLOBALS["___mysqli_ston"]));

$ajax = strval($_GET['ajax'] ?? '');

if ($ajax !== 'yes') {
    header('Refresh: 3;url=details.php?id=' . $tid);
    $errors = count($works) - $success;
    stderr($tracker_lang['success'] ?? 'Успех', "Обновление мультитрекера выполнено успешно. Успешно: $success Ошибок: $errors");
} else {
    header("Content-Type: text/html; charset=" . ($tracker_lang['language_charset'] ?? 'utf-8'));
    
    $announces = [];
    $announces_r = sql_query('SELECT url, seeders, leechers, last_update, state, error FROM torrents_scrape WHERE tid = ' . $tid);
    
    if ($announces_r) {
        while ($announce = mysqli_fetch_assoc($announces_r)) {
            if ($announce['state'] == 'ok') {
                $announces[] = '<li><b>' . htmlspecialchars($announce['url']) . '</b> - раздающие: <b>' . (int)$announce['seeders'] . '</b>, качающие: <b>' . (int)$announce['leechers'] . '</b></li>';
            } else {
                $announces[] = '<li><font color="red"><b>' . htmlspecialchars($announce['url']) . '</b></font> - не работает, ошибка: ' . htmlspecialchars($announce['error']) . '</li>';
            }
        }
    }
    
    // Получаем последнее время обновления
    $row = mysqli_fetch_assoc(sql_query('SELECT last_mt_update FROM torrents WHERE id = ' . $tid));
    $update_link = '';
    
    if ($row) {
        $lastUpdate = strtotime($row['last_mt_update']);
        
        if ($lastUpdate < (TIMENOW - 3600) && !empty($CURUSER)) {
            $update_link = '<br />Данные могли устареть. <a href="update_multi.php?id=' . $tid . '" onclick="update_multi(); return false;">Обновить мультитрекер</a>';
        }
        
        if ($row['last_mt_update'] == '0000-00-00 00:00:00' || !$lastUpdate) {
            $update_link .= '<br />Последнее обновление мультитрекера: <b>никогда</b>';
        } else {
            $update_link .= '<br />Последнее обновление мультитрекера: <b>' . get_et($lastUpdate) . '</b> назад';
        }
    }
    
    if (!empty($announces)) {
        echo '<ul style="margin: 0;">' . implode('', $announces) . '</ul>' . $update_link;
    } else {
        echo 'WTF? Multitracker = YES, but no announces';
    }
}