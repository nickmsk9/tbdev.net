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

define('IN_ANNOUNCE', true);
require_once __DIR__ . '/include/core_announce.php';

gzip();

// --------------------
// Быстрые хелперы ввода
// --------------------
$g = static function (string $k, string $default = ''): string {
    return isset($_GET[$k]) ? (string)$_GET[$k] : $default;
};
$gi = static function (string $k, int $default = 0): int {
    return isset($_GET[$k]) ? (int)$_GET[$k] : $default;
};

// --------------------
// Параметры announce
// --------------------
$passkey    = $g('passkey');
$info_hash  = $g('info_hash'); // бинарные 20 байт (в строке PHP)
$peer_id    = $g('peer_id');   // бинарные 20 байт (в строке PHP)
$event      = $g('event', '');
$port       = $gi('port');
$downloaded = $gi('downloaded');
$uploaded   = $gi('uploaded');
$left       = $gi('left');

$ip      = getip(); // один раз
$localip = $g('localip'); // оставляем как раньше (может использоваться в core_announce)
$agent   = $_SERVER['HTTP_USER_AGENT'] ?? '';

// numwant / num want / num_want
$rsize = 50;
foreach (['num want', 'numwant', 'num_want'] as $k) {
    if (isset($_GET[$k])) {
        $rsize = max(0, (int)$_GET[$k]);
        break;
    }
}

// compact/no_peer_id
$compact    = ($gi('compact') === 1);
$no_peer_id = ($gi('no_peer_id') === 1);

// --------------------
// Валидация ключей
// --------------------
if ($passkey === '' || $info_hash === '' || $peer_id === '') {
    err('Отсутствуют обязательные параметры announce (passkey/info_hash/peer_id).');
}
if ($port <= 0 || $port > 0xFFFF) {
    err('Некорректный порт.');
}
if (strlen($info_hash) !== 20) {
    err('Некорректный info_hash (' . strlen($info_hash) . ').');
}
if (strlen($peer_id) !== 20) {
    err('Некорректный peer_id (' . strlen($peer_id) . ').');
}
if (strlen($passkey) !== 32) {
    err('Некорректный passkey (' . strlen($passkey) . ').');
}

$seeder = ($left === 0) ? 'yes' : 'no';

// --------------------
// Античит по заголовкам
// --------------------
if (function_exists('getallheaders')) {
    $headers = getallheaders();
} else {
    $headers = emu_getallheaders();
}
$h = [];
foreach ((array)$headers as $k => $v) {
    $h[strtolower((string)$k)] = $v;
}
if (isset($h['cookie']) || isset($h['accept-language']) || isset($h['accept-charset'])) {
    err('Античит: запрещены Cookie/Accept-Language/Accept-Charset в announce.');
}

// --------------------
// Блокировка клиентов (логика как была, просто компактнее)
// --------------------
$pid = $peer_id;

if (substr($pid, 0, 6) === "exbc\08") err("BitComet 0.56 запрещён. Обновитесь.");
if (substr($pid, 0, 4) === "FUTB")    err("FUTB запрещён.");
if (substr($pid, 1, 2) === 'BC') {
    $ver = (int)substr($pid, 5, 2);
    if ($ver >= 59 && $ver !== 70 && $ver !== 63 && $ver !== 77) {
        err("BitComet {$ver} запрещён. Используйте 0.70 или перейдите на uTorrent.");
    }
}
if (substr($pid, 1, 2) === 'UT') {
    $uver = (int)substr($pid, 3, 3);
    if ($uver >= 170 && $uver <= 174) {
        err("uTorrent {$uver} запрещён. Используйте 1.6.1 или 1.7.5+.");
    }
}
if (substr($pid, 0, 3) === "-TS")      err("TorrentStorm запрещён.");
if (substr($pid, 0, 5) === "Mbrst")    err("Burst! запрещён.");
if (substr($pid, 0, 3) === "-BB")      err("BitBuddy запрещён.");
if (substr($pid, 0, 3) === "-SZ")      err("Shareaza запрещён.");
if (substr($pid, 0, 5) === "turbo")    err("TurboBT запрещён.");
if (substr($pid, 0, 4) === "T03A")     err("Обновите BitTornado.");
if (substr($pid, 0, 4) === "T03B")     err("Обновите BitTornado.");
if (substr($pid, 0, 3) === "FRS")      err("Rufus запрещён.");
if (substr($pid, 0, 2) === "eX")       err("eXeem запрещён.");
if (substr($pid, 0, 8) === "-TR0005-") err("Transmission/0.5 запрещён.");
if (substr($pid, 0, 8) === "-TR0006-") err("Transmission/0.6 запрещён.");
if (substr($pid, 0, 8) === "-XX0025-") err("Transmission/0.6 запрещён.");
if (substr($pid, 0, 1) === ",")        err("RAZA запрещён.");
if (substr($pid, 0, 3) === "-AG")      err("Запрещённый клиент. Рекомендуем uTorrent или Azureus.");
if (substr($pid, 0, 3) === "R34")      err("BTuga/Revolution 3.4 не допускается. Смотрите FAQ.");
if (substr($pid, 0, 4) === "exbc")     err("Эта версия BitComet запрещена!");
if (substr($pid, 0, 3) === "-FG")      err("FlashGet запрещён.");

// --------------------
// БД
// --------------------
dbconn();

// ВАЖНО: в PHP 8.1 mysql_* не существует.
// В core_announce.php у тебя уже должны быть mysqli-обёртки типа sql_query()/sqlerr()/sqlesc().
// Здесь используем sql_query + mysqli_* единообразно.

$res = sql_query("SELECT id FROM users WHERE passkey = " . sqlesc($passkey) . " LIMIT 1")
    or err('Ошибка пользователей (passkey).');

if (mysqli_num_rows($res) === 0) {
    err('Неверный passkey! Перекачайте .torrent с ' . $DEFAULTBASEURL);
}

// torrent по info_hash (hex)
$hash = bin2hex($info_hash);

$res = sql_query(
    'SELECT id, visible, banned, free, (seeders + leechers) AS numpeers, UNIX_TIMESTAMP(added) AS ts
     FROM torrents
     WHERE info_hash = ' . sqlesc($hash) . '
     LIMIT 1'
) or err('Ошибка torrents (select).');

$torrent = mysqli_fetch_assoc($res);
if (!$torrent) {
    err('Торрент не зарегистрирован на этом трекере.');
}

$torrentid = (int)$torrent['id'];
$numpeers  = (int)$torrent['numpeers'];

// --------------------
// Ответ peers (оптимизация: без ORDER BY RAND())
// --------------------
$fields = "seeder, peer_id, ip, port, uploaded, downloaded, userid, last_action,
           UNIX_TIMESTAMP(NOW()) AS nowts, UNIX_TIMESTAMP(prev_action) AS prevts";

// Быстрый “случайный” offset вместо RAND()
$limitSql = '';
if ($rsize > 0) {
    if ($numpeers > $rsize) {
        $maxOffset = max(0, $numpeers - $rsize);
        $offset = ($maxOffset > 0) ? random_int(0, $maxOffset) : 0;
        $limitSql = " LIMIT $offset, $rsize";
    } else {
        $limitSql = " LIMIT $rsize";
    }
}

$res = sql_query("SELECT $fields FROM peers WHERE torrent = $torrentid $limitSql")
    or err('Ошибка peers (select).');

$resp = 'd'
    . benc_str('interval') . 'i' . $announce_interval . 'e'
    . benc_str('peers') . ($compact ? '' : 'l');

$plist = '';
$self = null;
$userid = 0;

// Ищем self в выборке и параллельно формируем peers
while ($row = mysqli_fetch_assoc($res)) {
    if (($row['peer_id'] ?? '') === $peer_id) {
        $userid = (int)$row['userid'];
        $self = $row;
        continue;
    }

    if ($compact) {
        $parts = explode('.', (string)$row['ip']);
        if (count($parts) === 4) {
            $plist .= pack('C*', (int)$parts[0], (int)$parts[1], (int)$parts[2], (int)$parts[3])
                . pack('n*', (int)$row['port']);
        }
    } else {
        $resp .= 'd'
            . benc_str('ip') . benc_str((string)$row['ip'])
            . (!$no_peer_id ? benc_str('peer id') . benc_str((string)$row['peer_id']) : '')
            . benc_str('port') . 'i' . (int)$row['port'] . 'e'
            . 'e';
    }
}

// Закрытие peers + приватность для -BC0* (как было)
$resp .= ($compact ? benc_str($plist) : '')
    . (substr($peer_id, 0, 4) === '-BC0' ? 'e7:privatei1ee' : 'ee');

// --------------------
// Если self не нашли в срезе — добираем 1 запросом
// --------------------
$selfwhere = 'torrent = ' . $torrentid . ' AND peer_id = ' . sqlesc($peer_id);

if ($self === null) {
    $res = sql_query("SELECT $fields FROM peers WHERE $selfwhere LIMIT 1")
        or err('Ошибка peers (select self).');

    $row = mysqli_fetch_assoc($res);
    if ($row) {
        $userid = (int)$row['userid'];
        $self = $row;
    }
}

// --------------------
// Дальше — бизнес-логика обновлений (оставлена как была),
// но: меньше повторов, нормальные даты, без mysql_*
// --------------------
$announce_wait = 10;
$dt = sqlesc(date('Y-m-d H:i:s'));
$updateset = [];
$snatch_updateset = [];

if ($self !== null) {
    $nowts  = (int)($self['nowts'] ?? 0);
    $prevts = (int)($self['prevts'] ?? 0);
    if ($prevts > ($nowts - $announce_wait)) {
        err('Минимальный интервал announce: ' . $announce_wait . ' секунд.');
    }
}

if ($self === null) {
    // Новый пир: берём пользователя по passkey
    $rz = sql_query(
        'SELECT id, uploaded, downloaded, enabled, parked, class, passkey_ip
         FROM users
         WHERE passkey = ' . sqlesc($passkey) . '
         ORDER BY last_access DESC
         LIMIT 1'
    ) or err('Ошибка users (select).');

    if (mysqli_num_rows($rz) === 0) {
        err('Неизвестный passkey. Перекачайте торрент с ' . $BASEURL . ' и прочитайте FAQ.');
    }

    $az = mysqli_fetch_assoc($rz);

    if (($az['enabled'] ?? 'no') === 'no') err('Аккаунт отключён.');
    if (($az['parked'] ?? 'no') === 'yes') err('Ошибка: аккаунт припаркован.');

    $userid = (int)$az['id'];

    // wait-system (как было)
    if ((int)($az['class'] ?? 0) < UC_VIP && !empty($use_wait)) {
        $gigs = ((float)$az['uploaded']) / (1024 * 1024 * 1024);
        $elapsed = (int)floor((time() - (int)$torrent['ts']) / 3600);
        $ratio = ((int)$az['downloaded'] > 0) ? ((float)$az['uploaded'] / (float)$az['downloaded']) : 1.0;

        if ($ratio < 0.5 || $gigs < 5) $wait = 48;
        elseif ($ratio < 0.65 || $gigs < 6.5) $wait = 24;
        elseif ($ratio < 0.8 || $gigs < 8) $wait = 12;
        elseif ($ratio < 0.95 || $gigs < 9.5) $wait = 6;
        else $wait = 0;

        if ($elapsed < $wait) {
            err('Нет доступа (' . ($wait - $elapsed) . 'ч) — прочитайте FAQ.');
        }
    }

    $passkey_ip = (string)($az['passkey_ip'] ?? '');
    if ($passkey_ip !== '' && $ip !== $passkey_ip) {
        err('Для этого passkey разрешён другой IP адрес!');
    }

    if (portblacklisted($port)) {
        err('Порт ' . $port . ' в чёрном списке.');
    }

    // проверка connectable (как было)
    $connectable = 'no';
    $sockres = check_port($ip, $port, 5);
    if ($sockres) {
        $connectable = 'yes';
        @fclose($sockres);
    } else {
        if (isset($nc) && $nc === 'yes') {
            err('Клиент не коннектабелен! Проверьте проброс порта/настройки.');
        }
    }

    // snatched insert если нет
    $res = sql_query("SELECT torrent, userid FROM snatched WHERE torrent = $torrentid AND userid = $userid LIMIT 1")
        or err('Ошибка snatched (select).');
    $check = mysqli_fetch_assoc($res);

    if (!$check) {
        sql_query("INSERT LOW_PRIORITY INTO snatched (torrent, userid, port, startdat, last_action)
                   VALUES ($torrentid, $userid, $port, $dt, $dt)")
            or err('Ошибка snatched (insert).');
    }

    // peers insert
    $ret = sql_query(
        "INSERT LOW_PRIORITY INTO peers
            (connectable, torrent, peer_id, ip, port, uploaded, downloaded, to_go, started, last_action, seeder, userid, agent, uploadoffset, downloadoffset, passkey)
         VALUES
            ('$connectable', $torrentid, " . sqlesc($peer_id) . ", " . sqlesc($ip) . ", $port, $uploaded, $downloaded, $left, NOW(), NOW(), '$seeder', $userid, " . sqlesc($agent) . ", $uploaded, $downloaded, " . sqlesc($passkey) . ")"
    ) or err('Ошибка peers (insert).');

    if ($ret) {
        if ($seeder === 'yes') $updateset[] = 'seeders = seeders + 1';
        else $updateset[] = 'leechers = leechers + 1';
    }
} else {
    // Обновление существующего пира
    $selfUploaded   = (int)($self['uploaded'] ?? 0);
    $selfDownloaded = (int)($self['downloaded'] ?? 0);

    $upthis   = max(0, $uploaded - $selfUploaded);
    $downthis = max(0, $downloaded - $selfDownloaded);

    // free/silver логика как была
    switch ((string)($torrent['free'] ?? 'no')) {
        case 'yes':
            $downthis = 0;
            break;
        case 'silver':
            $downthis = (int)round($downthis / 2);
            break;
        case 'no':
        default:
            break;
    }

    if ($upthis > 0 || $downthis > 0) {
        sql_query("UPDATE LOW_PRIORITY users
                   SET uploaded = uploaded + $upthis, downloaded = downloaded + $downthis
                   WHERE id = $userid")
            or err('Ошибка users (update).');
    }

    $downloaded2 = max(0, $downloaded - $selfDownloaded);
    $uploaded2   = max(0, $uploaded - $selfUploaded);

    if ($downloaded2 > 0 || $uploaded2 > 0) {
        $snatch_updateset[] = "uploaded = uploaded + $uploaded2";
        $snatch_updateset[] = "downloaded = downloaded + $downloaded2";
        $snatch_updateset[] = "to_go = $left";
    }

    $snatch_updateset[] = "port = $port";
    $snatch_updateset[] = "last_action = $dt";
    $snatch_updateset[] = "seeder = '$seeder'";

    $prev_action = (string)($self['last_action'] ?? '');

    sql_query(
        "UPDATE LOW_PRIORITY peers
         SET uploaded = $uploaded,
             downloaded = $downloaded,
             uploadoffset = $uploaded2,
             downloadoffset = $downloaded2,
             to_go = $left,
             last_action = NOW(),
             prev_action = " . sqlesc($prev_action) . ",
             seeder = '$seeder'"
            . ($seeder === 'yes' && ($self['seeder'] ?? 'no') !== $seeder ? ", finishedat = " . time() : "")
            . ", agent = " . sqlesc($agent) . "
         WHERE $selfwhere"
    ) or err('Ошибка peers (update).');

    if (mysqli_affected_rows($GLOBALS['___mysqli_ston'] ?? null) && (($self['seeder'] ?? 'no') !== $seeder)) {
        if ($seeder === 'yes') {
            $updateset[] = 'seeders = seeders + 1';
            $updateset[] = 'leechers = IF(leechers > 0, leechers - 1, 0)';
        } else {
            $updateset[] = 'leechers = leechers + 1';
            $updateset[] = 'seeders = IF(seeders > 0, seeders - 1, 0)';
        }
    }

    if ($event === 'stopped') {
        sql_query("UPDATE LOW_PRIORITY snatched
                   SET seeder = 'no', connectable = 'no'
                   WHERE torrent = $torrentid AND userid = $userid")
            or err('Ошибка snatched (update stopped).');

        sql_query("DELETE FROM peers WHERE $selfwhere")
            or err('Ошибка peers (delete stopped).');

        // affected rows считаем от DELETE
        if (mysqli_affected_rows($GLOBALS['___mysqli_ston'] ?? null)) {
            if (($self['seeder'] ?? 'no') === 'yes') {
                $updateset[] = 'seeders = IF(seeders > 0, seeders - 1, 0)';
            } else {
                $updateset[] = 'leechers = IF(leechers > 0, leechers - 1, 0)';
            }
        }
    }
}

// completed
if ($event === 'completed') {
    $snatch_updateset[] = "finished = 'yes'";
    $snatch_updateset[] = "completedat = $dt";
    $snatch_updateset[] = "seeder = 'yes'";
    $updateset[] = 'times_completed = times_completed + 1';
}

// visible/last_action при сидировании
if ($seeder === 'yes') {
    if (($torrent['banned'] ?? 'no') !== 'yes' && ($torrent['visible'] ?? 'no') !== 'yes') {
        $updateset[] = "visible = 'yes'";
    }
    $updateset[] = 'last_action = NOW()';
}

// апдейты torrents
if ($updateset) {
    sql_query('UPDATE LOW_PRIORITY torrents SET ' . implode(', ', $updateset) . ' WHERE id = ' . $torrentid)
        or err('Ошибка torrents (update).');
}

// апдейты snatched
if ($snatch_updateset) {
    sql_query('UPDATE LOW_PRIORITY snatched SET ' . implode(', ', $snatch_updateset) . " WHERE torrent = $torrentid AND userid = $userid")
        or err('Ошибка snatched (update).');
}

benc_resp_raw($resp);
