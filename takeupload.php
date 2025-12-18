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



require_once "include/BDecode.php";
require_once "include/BEncode.php";
require_once "include/bittorrent.php";

ini_set("upload_max_filesize", (string)$max_torrent_size);

function bark(string $msg): void
{
    global $tracker_lang;
    genbark($msg, $tracker_lang['error'] ?? 'Error');
}

dbconn();
loggedinorreturn();
parked();

if (get_user_class() < UC_UPLOADER) {
    die;
}

foreach (explode(":", "descr:type:name") as $v) {
    if (!isset($_POST[$v])) {
        bark("missing form data");
    }
}

if (!isset($_FILES["tfile"])) {
    bark("missing form data");
}

$f = $_FILES["tfile"];
$fname = unesc((string)($f["name"] ?? ""));
if ($fname === '') {
    bark("Файл не загружен. Пустое имя файла!");
}

$descr = unesc((string)($_POST["descr"] ?? ""));
if ($descr === '') {
    bark("Вы должны ввести описание!");
}

$catid = (int)($_POST["type"] ?? 0);
if (!is_valid_id($catid)) {
    bark("Вы должны выбрать категорию, в которую поместить торрент!");
}

if (!validfilename($fname)) {
    bark("Неверное имя файла!");
}

if (!preg_match('/^(.+)\.torrent$/si', $fname, $matches)) {
    bark("Неверное имя файла (не .torrent).");
}

$shortfname = $matches[1];
$torrent = $shortfname;

if (!empty($_POST["name"])) {
    $torrent = unesc((string)$_POST["name"]);
}

$tmpname = (string)($f["tmp_name"] ?? "");
if ($tmpname === '' || !is_uploaded_file($tmpname)) {
    bark("eek");
}

if (!filesize($tmpname)) {
    bark("Пустой файл!");
}

$raw = file_get_contents($tmpname);
if ($raw === false || $raw === '') {
    bark("Пустой файл!");
}

$dict = bdecode($raw);
if (!is_array($dict)) {
    bark("Что за хрень ты загружаешь? Это не бинарно-кодированый файл!");
}

// free
$free = 'no';
if (get_user_class() >= UC_ADMINISTRATOR) {
    $freePost = (string)($_POST['free'] ?? 'no');
    if (in_array($freePost, ['yes', 'silver', 'no'], true)) {
        $free = $freePost;
    }
}

// not sticky
$not_sticky = 'yes';
if (((string)($_POST['not_sticky'] ?? 'yes')) === 'no' && get_user_class() >= UC_ADMINISTRATOR) {
    $not_sticky = 'no';
}

// multi (FIX: no undefined key)
$multi_torrent = (((string)($_POST['multi'] ?? 'no')) === 'yes') ? 'yes' : 'no';

// SEO
$keywords = htmlspecialchars_uni((string)($_POST["keywords"] ?? ''));
$description = htmlspecialchars_uni((string)($_POST["description"] ?? ''));

$info = $dict['info'] ?? [];
if (!is_array($info)) {
    $info = [];
}

$dname  = (string)($info['name'] ?? '');
$plen   = (int)($info['piece length'] ?? 0);
$pieces = (string)($info['pieces'] ?? '');

$totallen = 0;
if (isset($info['length'])) {
    $totallen = (int)$info['length'];
} elseif (!empty($info['files']) && is_array($info['files'])) {
    foreach ($info['files'] as $ff) {
        $totallen += (int)($ff['length'] ?? 0);
    }
}

$ret = sql_query("SHOW TABLE STATUS LIKE 'torrents'");
$row = mysqli_fetch_assoc($ret);
$next_id = (int)($row['Auto_increment'] ?? 0);

if ($next_id <= 0) {
    bark("Ошибка: не удалось получить следующий ID torrents");
}

if ($pieces === '' || (strlen($pieces) % 20 !== 0)) {
    bark("invalid pieces");
}

$filelist = [];
$type = 'single';

if (isset($info['length'])) {
    $filelist[] = [$dname, $totallen];
    $type = "single";
} else {
    $flist = $info['files'] ?? null;
    if (!is_array($flist)) {
        bark("missing both length and files");
    }
    if (!count($flist)) {
        bark("no files");
    }

    $totallen = 0;
    foreach ($flist as $fn) {
        $ll = (int)($fn['length'] ?? 0);
        $ff = $fn['path'] ?? [];

        if (!is_array($ff) || !count($ff)) {
            bark("filename error");
        }

        $ffa = [];
        foreach ($ff as $ffe) {
            $ffa[] = (string)$ffe;
        }

        $path = implode("/", $ffa);
        if ($path === '') {
            bark("filename error");
        }

        if ($path === 'Thumbs.db') {
            stderr("Ошибка", "В торрентах запрещено держать файлы Thumbs.db!");
            die;
        }

        $totallen += $ll;
        $filelist[] = [$path, $ll];
    }

    $type = "multi";
}

// если НЕ мультитрекер — приводим торрент к приватному
if ($multi_torrent === 'no') {
    $dict['announce'] = $announce_urls[0];
    $dict['info']['private'] = 1;
    $dict['info']['source'] = "[$DEFAULTBASEURL] $SITENAME";
    unset($dict['announce-list'], $dict['nodes'], $dict['info']['crc32'], $dict['info']['ed2k'], $dict['info']['md5sum'], $dict['info']['sha1'], $dict['info']['tiger'], $dict['azureus_properties']);
}

$dict = BDecode(BEncode($dict));
$dict['comment'] = "Торрент создан для '$SITENAME'";

$curUsername = (string)($CURUSER['username'] ?? '');
$curId = (int)($CURUSER['id'] ?? 0);

$dict['created by'] = $curUsername;
$dict['publisher'] = $curUsername;
$dict['publisher.utf-8'] = $curUsername;

$dict['publisher-url'] = "$DEFAULTBASEURL/userdetails.php?id=$curId";
$dict['publisher-url.utf-8'] = "$DEFAULTBASEURL/userdetails.php?id=$curId";

$infohash = sha1(BEncode($dict['info']));

// если мультитрекер — разбираем announce-list и пишем scrape
if ($multi_torrent === 'yes') {
    if (empty($dict['announce-list']) && !empty($dict['announce'])) {
        $dict['announce-list'][] = [(string)$dict['announce']];
    }

    if (!empty($dict['announce-list']) && is_array($dict['announce-list'])) {
        $parsed_urls = [];
        foreach ($dict['announce-list'] as $al_url) {
            if (!is_array($al_url) || empty($al_url[0])) continue;

            $u = trim((string)$al_url[0]);
            if ($u === '' || $u === 'http://retracker.local/announce') continue;
            if (!preg_match('#^(udp|http)://#si', $u)) continue;
            if (in_array($u, $parsed_urls, true)) continue;

            $url_array = @parse_url($u);
            $host = (string)($url_array['host'] ?? '');
            if ($host !== '' && substr($host, -6) === '.local') continue;

            $parsed_urls[] = $u;

            sql_query(
                'REPLACE INTO torrents_scrape (tid, info_hash, url) VALUES (' .
                implode(', ', array_map('sqlesc', [(string)$next_id, $infohash, $u])) .
                ')'
            ) or sqlerr(__FILE__, __LINE__);
        }
    } else {
        stderr($tracker_lang['error'] ?? 'Error', "В торрент файле нет announce-list и не указан announce. Такой мультитрекерный торрент использовать нельзя.");
    }
}

//////////////////////////////
// Take Image Uploads
$maxfilesize = (int)$max_image_size;

$allowed_types = [
    "image/gif"   => "gif",
    "image/pjpeg" => "jpg",
    "image/jpeg"  => "jpg",
    "image/jpg"   => "jpg",
    "image/png"   => "png",
];

$uploaddir = "torrents/images/";
$inames = [];

for ($x = 0; $x < 5; $x++) {
    $key = 'image' . $x;
    if (empty($_FILES[$key]['name'])) {
        continue;
    }

    $y = $x + 1;

    $mime = (string)($_FILES[$key]['type'] ?? '');
    if (!array_key_exists($mime, $allowed_types)) {
        bark("Invalid file type! Image $y (" . htmlspecialchars_uni($mime) . ")");
    }

    $imgName = (string)($_FILES[$key]['name'] ?? '');
    if (!preg_match('/^(.+)\.(jpg|jpeg|png|gif)$/si', $imgName)) {
        bark("Неверное имя файла (не картинка).");
    }

    $size = (int)($_FILES[$key]['size'] ?? 0);
    if ($size > $maxfilesize) {
        bark("Превышен размер файла! Картинка $y - Должна быть меньше " . mksize($maxfilesize));
    }

    $ifile = (string)($_FILES[$key]['tmp_name'] ?? '');
    if ($ifile === '' || !is_uploaded_file($ifile)) {
        bark("Error occured uploading image! - Image $y");
    }

    $ext = strtolower((string)pathinfo($imgName, PATHINFO_EXTENSION));
    $ifilename = $next_id . $x . '.' . $ext;

    if (!@copy($ifile, $uploaddir . $ifilename)) {
        bark("Error occured uploading image! - Image $y");
    }

    $inames[] = $ifilename;
}

//////////////////////////////

$torrent = htmlspecialchars_uni(str_replace("_", " ", (string)$torrent));

$image_fields = array_fill(0, 5, 'NULL');
for ($i = 0; $i < min(5, count($inames)); $i++) {
    if (!empty($inames[$i])) {
        $image_fields[$i] = sqlesc($inames[$i]);
    }
}

// FIX для last_mt_update: ставим нормальную дату или NULL
$now = get_date_time();
$last_mt_update_sql = ($multi_torrent === 'yes') ? sqlesc($now) : "NULL";

$sql = "INSERT INTO torrents
    (filename, owner, visible, not_sticky, info_hash, name, keywords, description,
     size, numfiles, type, descr, ori_descr, free,
     image1, image2, image3, image4, image5,
     category, save_as, added, last_action, multitracker, last_mt_update)
    VALUES ("
    . implode(",", array_map("sqlesc", [
        $fname,
        (string)$curId,
        "no",
        $not_sticky,
        $infohash,
        $torrent,
        $keywords,
        $description,
        (string)$totallen,
        (string)count($filelist),
        $type,
        $descr,
        $descr,
        $free
    ]))
    . ","
    . implode(",", $image_fields)
    . ","
    . sqlesc((string)$catid)
    . ","
    . sqlesc($dname)
    . ","
    . sqlesc($now)
    . ","
    . sqlesc($now)
    . ","
    . sqlesc($multi_torrent)
    . ","
    . $last_mt_update_sql
    . ")";

$ret = sql_query($sql);

if (!$ret) {
    if (mysqli_errno($GLOBALS["___mysqli_ston"]) == 1062) {
        bark("Торрент уже загружен!");
    }
    bark("Ошибка MySQL: " . mysqli_error($GLOBALS["___mysqli_ston"]));
}

$id = (int)($GLOBALS['mysqli']->insert_id ?? 0);

if ($id <= 0) {
    // запасной вариант: запросом (на всякий)
    $r = sql_query("SELECT LAST_INSERT_ID() AS id");
    $rr = $r ? mysqli_fetch_assoc($r) : null;
    $id = (int)($rr['id'] ?? 0);
}

if ($id <= 0) {
    bark("Ошибка: не удалось получить ID добавленного торрента");
}


sql_query(
    'INSERT INTO torrents_descr (tid, descr_hash, descr_parsed) VALUES (' .
    implode(', ', array_map('sqlesc', [(string)$id, md5($descr), format_comment($descr)])) .
    ')'
) or sqlerr(__FILE__, __LINE__);

sql_query("INSERT INTO checkcomm (checkid, userid, torrent) VALUES ($id, $curId, 1)") or sqlerr(__FILE__, __LINE__);
sql_query("DELETE FROM files WHERE torrent = $id");

foreach ($filelist as $file) {
    sql_query("INSERT INTO files (torrent, filename, size) VALUES ($id, " . sqlesc((string)$file[0]) . ", " . (int)$file[1] . ")");
}

move_uploaded_file($tmpname, "$torrent_dir/$id.torrent");

$fp = fopen("$torrent_dir/$id.torrent", "wb");
if ($fp) {
    $dict_str = BEncode($dict);
    fwrite($fp, $dict_str);
    fclose($fp);
}

write_log("Торрент номер $id ($torrent) был залит пользователем " . $curUsername, "5DDB6E", "torrent");

header("Location: $DEFAULTBASEURL/details.php?id=$id");
exit;
