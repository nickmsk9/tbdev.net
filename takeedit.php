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

function bark(string $msg): void
{
    // Важно: заголовок тоже в UTF-8
    stderr("Ошибка", $msg);
}

////////////////////////////////////////////////
function uploadimage(int $x, string $imgname, int $tid): ?string
{
    global $max_image_size;

    $maxfilesize = (int)$max_image_size; // default 1mb

    $allowed_types = [
        "image/gif"   => "gif",
        "image/pjpeg" => "jpg",
        "image/jpeg"  => "jpg",
        "image/jpg"   => "jpg",
        "image/png"   => "png",
    ];

    $key = 'image' . $x;

    if (empty($_FILES[$key]['name'])) {
        return null;
    }

    // удалить старую картинку (если была)
    if ($imgname !== '') {
        $img = "torrents/images/" . $imgname;
        @unlink($img);
    }

    // Тип
    $mime = (string)($_FILES[$key]['type'] ?? '');
    if ($mime === '' || !array_key_exists($mime, $allowed_types)) {
        bark("Неверный тип файла изображения! Image " . ($x + 1) . " (" . htmlspecialchars_uni($mime) . ")");
    }

    // Имя + расширение
    $origName = (string)($_FILES[$key]['name'] ?? '');
    if (!preg_match('/^(.+)\.(jpg|jpeg|png|gif)$/si', $origName)) {
        bark("Неверное имя файла (не jpg/png/gif).");
    }

    // Размер
    $size = (int)($_FILES[$key]['size'] ?? 0);
    if ($size > $maxfilesize) {
        bark("Слишком большой файл! Изображение " . ($x + 1) . " — максимум " . mksize($maxfilesize));
    }

    $uploaddir = "torrents/images/";
    $tmp = (string)($_FILES[$key]['tmp_name'] ?? '');

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        bark("Ошибка загрузки изображения (tmp).");
    }

    // безопасно получаем ext
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
        bark("Неверное расширение изображения.");
    }
    if ($ext === 'jpeg') $ext = 'jpg';

    $ifilename = $tid . ($x + 1) . '.' . $ext;

    if (!@copy($tmp, $uploaddir . $ifilename)) {
        bark("Ошибка при загрузке изображения! Image " . ($x + 1));
    }

    return $ifilename;
}
////////////////////////////////////////////////

function dict_check(array $d, string $s): array
{
    if (($d["type"] ?? '') !== "dictionary") {
        bark("not a dictionary");
    }

    $a = explode(":", $s);
    $dd = $d["value"] ?? [];
    $ret = [];

    foreach ($a as $k) {
        $t = null;

        if (preg_match('/^(.*)\((.*)\)$/', $k, $m)) {
            $k = $m[1];
            $t = $m[2];
        }

        if (!isset($dd[$k])) {
            bark("dictionary is missing key(s)");
        }

        if ($t !== null) {
            if (($dd[$k]["type"] ?? '') !== $t) {
                bark("invalid entry in dictionary");
            }
            $ret[] = $dd[$k]["value"];
        } else {
            $ret[] = $dd[$k];
        }
    }

    return $ret;
}

function dict_get(array $d, string $k, string $t)
{
    if (($d["type"] ?? '') !== "dictionary") {
        bark("not a dictionary");
    }
    $dd = $d["value"] ?? [];
    if (!isset($dd[$k])) {
        return null;
    }
    $v = $dd[$k];
    if (($v["type"] ?? '') !== $t) {
        bark("invalid dictionary entry type");
    }
    return $v["value"];
}

dbconn();
loggedinorreturn();

if (!mkglobal("id:name:descr:type")) {
    bark("missing form data");
}

$id = (int)$id;
if ($id <= 0) {
    die();
}

$res = sql_query("SELECT owner, filename, save_as, image1, image2, image3, image4, image5 FROM torrents WHERE id = $id");
$row = mysqli_fetch_assoc($res);
if (!$row) {
    die();
}

if ((int)$CURUSER["id"] !== (int)$row["owner"] && get_user_class() < UC_MODERATOR) {
    bark("You're not the owner! How did that happen?\n");
}

$updateset = [];

$fname = (string)$row["filename"];
preg_match('/^(.+)\.torrent$/si', $fname, $matches);
$shortfname = $matches[1] ?? '';
$dname = (string)$row["save_as"];

// picturemod (5 картинок)
for ($x = 1; $x <= 5; $x++) {
    $action = (string)($_POST['img' . $x . 'action'] ?? '');
    $_GLOBALS['img' . $x . 'action'] = $action;

    if ($action === 'update') {
        $new = uploadimage($x - 1, (string)($row['image' . $x] ?? ''), $id);
        if ($new !== null) {
            $updateset[] = 'image' . $x . ' = ' . sqlesc($new);
        }
    }

    if ($action === 'delete') {
        $old = (string)($row['image' . $x] ?? '');
        if ($old !== '') {
            @unlink('torrents/images/' . $old);
            $updateset[] = 'image' . $x . ' = ""';
        }
    }
}
// picturemod

// ✅ FIX 1: всегда инициализируем
$update_torrent = false;
if (!empty($_FILES["tfile"]["name"])) {
    $update_torrent = true;
}

if ($update_torrent) {
    $f = $_FILES["tfile"];

    $fname = unesc((string)($f["name"] ?? ''));
    if ($fname === '') {
        bark("Файл не выбран. Выбери .torrent!");
    }
    if (!validfilename($fname)) {
        bark("Неверное имя файла!");
    }
    if (!preg_match('/^(.+)\.torrent$/si', $fname, $matches)) {
        bark("Неверное имя файла (не .torrent).");
    }

    $tmpname = (string)($f["tmp_name"] ?? '');
    if ($tmpname === '' || !is_uploaded_file($tmpname)) {
        bark("eek");
    }
    if (!@filesize($tmpname)) {
        bark("Пустой файл!");
    }

    $dict = bdecode((string)file_get_contents($tmpname));
    if (!isset($dict)) {
        bark("Это не валидный torrent-файл!");
    }

    $info = $dict['info'] ?? null;
    if (!is_array($info)) {
        bark("Torrent без секции info.");
    }

    $dname    = (string)($info['name'] ?? '');
    $plen     = (int)($info['piece length'] ?? 0);
    $pieces   = (string)($info['pieces'] ?? '');
    $totallen = $info['length'] ?? null;

    if ($dname === '' || $plen <= 0 || $pieces === '') {
        bark("Невалидные данные в torrent info.");
    }
    if (strlen($pieces) % 20 !== 0) {
        bark("invalid pieces");
    }

    $filelist = [];
    if ($totallen !== null) {
        $filelist[] = [$dname, (int)$totallen];
        $torrent_type = "single";
        $totallen = (int)$totallen;
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
            $ff = $fn['path'] ?? null;

            if ($ll <= 0 || !is_array($ff)) {
                bark("filename error");
            }

            $ffa = [];
            foreach ($ff as $ffe) {
                $ffa[] = (string)$ffe;
            }
            if (!count($ffa)) {
                bark("filename error");
            }

            $ffe = implode("/", $ffa);

            // защита от Thumbs.db
            if ($ffe === 'Thumbs.db') {
                stderr("Ошибка", "В раздаче найден запрещённый файл Thumbs.db!");
                die;
            }

            $filelist[] = [$ffe, $ll];
            $totallen += $ll;
        }

        $torrent_type = "multi";
    }

    // нормализация announce/private
    $dict['announce'] = $announce_urls[0];
    $dict['info']['private'] = 1;
    $dict['info']['source'] = "[$DEFAULTBASEURL] $SITENAME";

    unset($dict['announce-list'], $dict['nodes'], $dict['azureus_properties']);
    unset($dict['info']['crc32'], $dict['info']['ed2k'], $dict['info']['md5sum'], $dict['info']['sha1'], $dict['info']['tiger']);

    $dict = BDecode(BEncode($dict));
    $dict['comment'] = "Сделано для '$SITENAME'";
    $dict['created by'] = (string)$CURUSER['username'];
    $dict['publisher'] = (string)$CURUSER['username'];
    $dict['publisher.utf-8'] = (string)$CURUSER['username'];
    $dict['publisher-url'] = "$DEFAULTBASEURL/userdetails.php?id=" . (int)$CURUSER['id'];
    $dict['publisher-url.utf-8'] = "$DEFAULTBASEURL/userdetails.php?id=" . (int)$CURUSER['id'];

    $infohash = sha1(BEncode($dict['info']));

    move_uploaded_file($tmpname, "$torrent_dir/$id.torrent");

    $fp = @fopen("$torrent_dir/$id.torrent", "wb");
    if ($fp) {
        $dict_str = BEncode($dict);
        @fwrite($fp, $dict_str);
        fclose($fp);
    }

    $updateset[] = "info_hash = " . sqlesc($infohash);
    $updateset[] = "filename = " . sqlesc($fname);
    $updateset[] = "save_as = " . sqlesc($dname);
    $updateset[] = "size = " . sqlesc((string)$totallen);
    $updateset[] = "type = " . sqlesc($torrent_type);
    $updateset[] = "numfiles = " . count($filelist);

    @sql_query("DELETE FROM files WHERE torrent = $id");
    foreach ($filelist as $file) {
        @sql_query("INSERT INTO files (torrent, filename, size) VALUES ($id, " . sqlesc((string)$file[0]) . ", " . (int)$file[1] . ")");
    }
}

// поля формы
$name = html_uni((string)$name);

$descr = unesc((string)($_POST["descr"] ?? ''));
if ($descr === '') {
    bark("Вы должны ввести описание!");
}

$updateset[] = "name = " . sqlesc($name);
$updateset[] = "descr = " . sqlesc($descr);
$updateset[] = "ori_descr = " . sqlesc($descr);

// форматирование описания
sql_query(
    'REPLACE INTO torrents_descr (tid, descr_hash, descr_parsed) VALUES (' .
    implode(', ', array_map('sqlesc', [$id, md5($descr), format_comment($descr)])) .
    ')'
) or sqlerr(__FILE__, __LINE__);

$updateset[] = "category = " . (int)$type;

// ✅ FIX 2: banned / not_sticky / free / visible — безопасно
if (get_user_class() >= UC_ADMINISTRATOR) {
    $isBanned = !empty($_POST["banned"]);

    if ($isBanned) {
        $updateset[] = "banned = 'yes'";
        $_POST["visible"] = 0;
    } else {
        $updateset[] = "banned = 'no'";
    }

    $updateset[] = (($_POST["not_sticky"] ?? '') === "no")
        ? "not_sticky = 'no'"
        : "not_sticky = 'yes'";
}

if (get_user_class() >= UC_ADMINISTRATOR) {
    $free = (string)($_POST['free'] ?? '');
    if (in_array($free, ['yes', 'silver', 'no'], true)) {
        $updateset[] = "free = " . sqlesc($free);
    }
}

$updateset[] = "visible = '" . (!empty($_POST["visible"]) ? "yes" : "no") . "'";
$updateset[] = "moderated = 'yes'";
$updateset[] = "moderatedby = " . sqlesc((string)(int)$CURUSER["id"]);

sql_query("UPDATE torrents SET " . join(", ", $updateset) . " WHERE id = $id") or sqlerr(__FILE__, __LINE__);

// Лог — пишем нормальным UTF-8 текстом (чтобы не ловить Incorrect string value)
write_log("Торрент '$name' был отредактирован пользователем {$CURUSER['username']}", "F25B61", "torrent");

$returl = "details.php?id=$id";
if (isset($_POST["returnto"])) {
    $returl .= "&returnto=" . urlencode((string)$_POST["returnto"]);
}

header("Location: $returl");
exit;
