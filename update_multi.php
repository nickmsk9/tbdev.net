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

// Dirty hack to prevent ghost Guests on website
$old_us = (int)($use_sessions ?? 0);
$use_sessions = 0;
dbconn();
$use_sessions = $old_us;

$tokenParam = (string)($_GET['token'] ?? '');
if ($tokenParam === '') {
    loggedinorreturn(); // чтобы гости/боты не трогали
}

require_once __DIR__ . '/include/scraper/httptscraper.php';
require_once __DIR__ . '/include/scraper/udptscraper.php';

function normalize_info_hash(string $hash): string
{
    $hash = strtolower(trim($hash));
    if (strlen($hash) !== 40 || !ctype_xdigit($hash)) {
        return '';
    }
    return $hash;
}

function generate_token(int $tid, string $url, string $info_hash): string
{
    $salt = defined('COOKIE_SALT') ? (string)COOKIE_SALT : 'default_salt';
    return md5($tid . $url . $info_hash . $salt);
}

function check_token(string $token, int $tid, string $url, string $info_hash): bool
{
    $expected = generate_token($tid, $url, $info_hash);
    return ($token !== '' && hash_equals($expected, $token));
}

function scrape(int $tid, string $url, string $info_hash): bool
{
    $timeout = 5;
    $udp  = new udptscraper($timeout);
    $http = new httptscraper($timeout);

    try {
        if (stripos($url, 'udp://') === 0) {
            $data = $udp->scrape($url, $info_hash);
        } else {
            $data = $http->scrape($url, $info_hash);
        }

        $row = $data[$info_hash] ?? ['seeders' => 0, 'leechers' => 0];
        $seeders  = (int)($row['seeders'] ?? 0);
        $leechers = (int)($row['leechers'] ?? 0);

        $q = '
            UPDATE torrents_scrape
               SET state="ok",
                   error="",
                   seeders=' . $seeders . ',
                   leechers=' . $leechers . '
             WHERE tid=' . (int)$tid . '
               AND url=' . sqlesc($url) . '
        ';
        $res = sql_query($q);
        if (!$res) {
            sqlerr(__FILE__, __LINE__);
        }

        return true;
    } catch (ScraperException $e) {
        $q = '
            UPDATE torrents_scrape
               SET state="error",
                   error=' . sqlesc($e->getMessage()) . ',
                   seeders=0,
                   leechers=0
             WHERE tid=' . (int)$tid . '
               AND url=' . sqlesc($url) . '
        ';
        $res = sql_query($q);
        if (!$res) {
            sqlerr(__FILE__, __LINE__);
        }

        return false;
    } catch (Throwable $e) {
        $q = '
            UPDATE torrents_scrape
               SET state="error",
                   error=' . sqlesc('Ошибка: ' . $e->getMessage()) . ',
                   seeders=0,
                   leechers=0
             WHERE tid=' . (int)$tid . '
               AND url=' . sqlesc($url) . '
        ';
        $res = sql_query($q);
        if (!$res) {
            sqlerr(__FILE__, __LINE__);
        }

        return false;
    }
}

$tid = (int)($_GET['id'] ?? 0);
if ($tid <= 0) {
    stderr($tracker_lang['error'] ?? 'Ошибка', 'Неверный ID торрента.');
}

/**
 * Внутренний режим (curl_multi) — вернём только 1/0
 */
$urlParam  = (string)($_GET['url'] ?? '');
$hashParam = (string)($_GET['info_hash'] ?? '');

if ($urlParam !== '' && $hashParam !== '') {
    $url = trim($urlParam);
    $info_hash = normalize_info_hash($hashParam);
    $token = (string)($_GET['token'] ?? '');

    if ($info_hash === '') {
        die('Неверный info_hash');
    }
    if (!check_token($token, $tid, $url, $info_hash)) {
        die('Неверный токен');
    }

    echo scrape($tid, $url, $info_hash) ? '1' : '0';
    exit;
}

/**
 * Обычный режим (страница/обновление)
 */
$result = sql_query('SELECT name, visible, multitracker, last_mt_update FROM torrents WHERE id=' . $tid);
if (!$result || mysqli_num_rows($result) === 0) {
    stderr($tracker_lang['error'] ?? 'Ошибка', 'Такого торрента нет.');
}

[$name, $cur_visible, $multitracker, $last_mt_update] = mysqli_fetch_row($result);

if ((string)$name === '' || (string)$multitracker !== 'yes') {
    stderr($tracker_lang['error'] ?? 'Ошибка', 'Такого торрента нет, или он не мультитрекерный.');
}

$lastUpdateTime = strtotime((string)$last_mt_update);
if ($lastUpdateTime === false) {
    $lastUpdateTime = 0;
}

if ($lastUpdateTime > (TIMENOW - 3600)) {
    stderr($tracker_lang['error'] ?? 'Ошибка', 'Вы обновляете мультитрекер слишком часто. Разрешено: не чаще 1 раза в час.');
}

$anns_r = sql_query('SELECT info_hash, url FROM torrents_scrape WHERE tid=' . $tid);
if (!$anns_r) {
    sqlerr(__FILE__, __LINE__);
}

$works = [];
while ($ann = mysqli_fetch_assoc($anns_r)) {
    $u = (string)($ann['url'] ?? '');
    $h = normalize_info_hash((string)($ann['info_hash'] ?? ''));
    if ($u !== '' && $h !== '') {
        $works[] = ['url' => $u, 'info_hash' => $h];
    }
}

if (!$works) {
    stderr($tracker_lang['error'] ?? 'Ошибка', 'Нет трекеров для обновления.');
}

$success = 0;

if (function_exists('curl_multi_init')) {
    $multi = curl_multi_init();
    $channels = [];

    $base = (string)($DEFAULTBASEURL ?? '');
    if ($base === '') {
        // если у тебя DEFAULTBASEURL где-то иначе — лучше задать, но без фатала
        $base = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://')
              . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    foreach ($works as $work) {
        $url = $work['url'];
        $info_hash = $work['info_hash'];
        $token = generate_token($tid, $url, $info_hash);

        $scrapeUrl = $base . '/update_multi.php?id=' . $tid
            . '&url=' . rawurlencode($url)
            . '&info_hash=' . rawurlencode($info_hash)
            . '&token=' . rawurlencode($token);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $scrapeUrl,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        curl_multi_add_handle($multi, $ch);
        $channels[] = $ch;
    }

    $running = 0;
    do {
        $status = curl_multi_exec($multi, $running);
        if ($running > 0) {
            curl_multi_select($multi, 0.2);
        }
    } while ($running > 0 && $status === CURLM_OK);

    foreach ($channels as $ch) {
        $content = (string)curl_multi_getcontent($ch);
        if (trim($content) === '1') {
            $success++;
        }
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }

    curl_multi_close($multi);
} else {
    foreach ($works as $work) {
        if (scrape($tid, $work['url'], $work['info_hash'])) {
            $success++;
        }
    }
}

// обновляем суммы и время
$currentTime = date('Y-m-d H:i:s');

$upd = "
    UPDATE torrents AS t
    INNER JOIN (
        SELECT ts.tid,
               SUM(ts.seeders)  AS sum_seeders,
               SUM(ts.leechers) AS sum_leechers
        FROM torrents_scrape AS ts
        WHERE ts.tid = $tid
        GROUP BY ts.tid
    ) AS ts ON ts.tid = t.id
    SET t.remote_seeders   = ts.sum_seeders,
        t.remote_leechers  = ts.sum_leechers,
        t.last_action      = " . sqlesc($currentTime) . ",
        t.last_mt_update   = " . sqlesc($currentTime) . ",
        t.visible          = IF(ts.sum_seeders > 0, 'yes', t.visible)
    WHERE t.id = $tid
";
$r = sql_query($upd);
if (!$r) {
    sqlerr(__FILE__, __LINE__);
}

$ajax = (string)($_GET['ajax'] ?? '');

if ($ajax !== 'yes') {
    header('Refresh: 3;url=details.php?id=' . $tid);

    $errors = count($works) - $success;
    stderr(
        $tracker_lang['success'] ?? 'Успех',
        'Обновление мультитрекера выполнено. Успешно: ' . (int)$success . ', ошибок: ' . (int)$errors
    );
    exit;
}

// AJAX-вывод
header('Content-Type: text/html; charset=' . ($tracker_lang['language_charset'] ?? 'utf-8'));

$announces = [];
$announces_r = sql_query('SELECT url, seeders, leechers, last_update, state, error FROM torrents_scrape WHERE tid=' . $tid);
if (!$announces_r) {
    sqlerr(__FILE__, __LINE__);
}

while ($announce = mysqli_fetch_assoc($announces_r)) {
    $aUrl = htmlspecialchars((string)($announce['url'] ?? ''), ENT_QUOTES, 'UTF-8');
    $state = (string)($announce['state'] ?? '');
    if ($state === 'ok') {
        $announces[] =
            '<li><b>' . $aUrl . '</b> — раздают: <b>' . (int)$announce['seeders'] .
            '</b>, качают: <b>' . (int)$announce['leechers'] . '</b></li>';
    } else {
        $err = htmlspecialchars((string)($announce['error'] ?? ''), ENT_QUOTES, 'UTF-8');
        $announces[] =
            '<li><span style="color:red"><b>' . $aUrl . '</b></span> — ошибка: ' . $err . '</li>';
    }
}

$rowRes = sql_query('SELECT last_mt_update FROM torrents WHERE id=' . $tid);
$row = $rowRes ? mysqli_fetch_assoc($rowRes) : null;

$update_link = '';
if ($row) {
    $lastUpdate = strtotime((string)$row['last_mt_update']);
    if ($lastUpdate === false) {
        $lastUpdate = 0;
    }

    if ($lastUpdate < (TIMENOW - 3600) && !empty($CURUSER)) {
        $update_link = '<br />Данные могли устареть. <a href="update_multi.php?id=' . $tid . '" onclick="update_multi(); return false;">Обновить мультитрекер</a>';
    }

    if ((string)$row['last_mt_update'] === '0000-00-00 00:00:00' || $lastUpdate <= 0) {
        $update_link .= '<br />Последнее обновление мультитрекера: <b>никогда</b>';
    } else {
        $update_link .= '<br />Последнее обновление мультитрекера: <b>' . get_et($lastUpdate) . '</b> назад';
    }
}

if ($announces) {
    echo '<ul style="margin:0;">' . implode('', $announces) . '</ul>' . $update_link;
} else {
    echo 'Multitracker = YES, но список трекеров пуст.';
}
