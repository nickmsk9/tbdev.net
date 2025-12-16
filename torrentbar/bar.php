<?
//
// Author: TracKer
// E-Mail: tracker2k@gmail.com
// Version 0.1.7 (Major.Minor.Revision)
// Homepage: http://tracker.ho.com.ua/torrentbar/
//
// Copyright (C) 2006  TracKer
// 
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
// 
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// 
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
//
// See doc/copying.txt for details
//
//===========================================================================
// Info
//===========================================================================
//
// [IN]
//   userid - ID of user in TorrentPier forum
//
// [OUT]
//   PNG file
//
// [Example]
//   http://127.0.0.1/bar.php/USERID.png
//
//===========================================================================
// Presets
//===========================================================================

// Ported from phpBB 2 + TorrentPier to TBDev: StirolXXX a.k.a Yuna Scatari
// E-Mail: stirol@gmail.com
// Copyright (C) 2006  StirolXXX

// Database Presets
$config_path = "../include/secrets.php";
$configlocal_path = "../include/secrets.local.php";

$template_file = "./template.png";

$rating_x = 37;
$rating_y = 7;

$upload_x = 104;
$upload_y = 7;

$download_x = 198;
$download_y = 7;

$digits_template = "./digits.png";
$digits_config = "./digits.ini";

//===========================================================================
// Functions
//===========================================================================

function getParam() {
    // Получаем ID из GET параметра
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        return intval($_GET['id']);
    }
    
    // Или из REQUEST_URI, если есть формат /bar.php?id=123
    if (isset($_SERVER['REQUEST_URI']) && preg_match('/id=(\d+)/', $_SERVER['REQUEST_URI'], $matches)) {
        return intval($matches[1]);
    }
    
    // Если нет ID, возвращаем 0 или показываем ошибку
    die("Invalid user_id. Use format: bar.php?id=123");
}

function mysql_init() {
    global $mysql_host, $mysql_db, $mysql_user, $mysql_pass;
    
    // Используем mysqli вместо устаревшего mysql
    if ($mysql_pass != '') {
        $link = @mysqli_connect($mysql_host, $mysql_user, $mysql_pass, $mysql_db) or die("Cannot connect to database!");
    } else {
        $link = @mysqli_connect($mysql_host, $mysql_user, '', $mysql_db) or die("Cannot connect to database!");
    }
    
    mysqli_set_charset($link, 'utf8');
    return $link;
}

function ifthen($ifcondition, $iftrue, $iffalse) {
    return $ifcondition ? $iftrue : $iffalse;
}

function getPostfix($val) {
    $postfix = "b";
    if ($val >= 1024)             { $postfix = "kb"; }
    if ($val >= 1048576)          { $postfix = "mb"; }
    if ($val >= 1073741824)       { $postfix = "gb"; }
    if ($val >= 1099511627776)    { $postfix = "tb"; }
    if ($val >= 1125899906842624) { $postfix = "pb"; }
    if ($val >= 1152921504606846976)       { $postfix = "eb"; }
    if ($val >= 1180591620717411303424)    { $postfix = "zb"; }
    if ($val >= 1208925819614629174706176) { $postfix = "yb"; }
    
    return $postfix;
}

function roundCounter($value, $postfix) {
    $val = $value;
    switch ($postfix) {
        case "kb": 
            $val = $val / 1024;
            break;
        case "mb": 
            $val = $val / 1048576;
            break;
        case "gb": 
            $val = $val / 1073741824;
            break;
        case "tb": 
            $val = $val / 1099511627776;
            break;
        case "pb": 
            $val = $val / 1125899906842624;
            break;
        case "eb": 
            $val = $val / 1152921504606846976;
            break;
        case "zb": 
            $val = $val / 1180591620717411303424;
            break;
        case "yb": 
            $val = $val / 1208925819614629174706176;
            break;
        default:
            break;
    }
    return $val;
}

//===========================================================================
// Main body
//===========================================================================

// Digits initialization - begin
$digits_ini = @parse_ini_file($digits_config) or die("Cannot load Digits Configuration file!");
$digits_img = @imagecreatefrompng($digits_template) or die("Cannot Initialize new GD image stream!");
// Digits initialization - end

$download_counter = 0;
$upload_counter = 0;
$rating_counter = 0;

$img = @imagecreatefrompng($template_file) or die("Cannot Initialize new GD image stream!");

$userid = getParam();
if ($userid != "") {
    include($config_path);
    include($configlocal_path);
    $link = mysql_init();
    
    $query = "SELECT COUNT(id) as count FROM users WHERE id = '" . mysqli_real_escape_string($link, $userid) . "'";
    $result = @mysqli_query($link, $query) or die("Could not select data!");
    $row = mysqli_fetch_assoc($result);
    $counter = $row['count'];
    mysqli_free_result($result);
    
    if ($counter > 0) {
        $query = "SELECT uploaded, downloaded FROM users WHERE id = " . mysqli_real_escape_string($link, $userid);
        $result = mysqli_query($link, $query) or die("Could not select data!");
        
        while ($data = mysqli_fetch_array($result)) {
            $upload_counter = $data['uploaded'];
            $download_counter = $data['downloaded'];
            if ($download_counter > 0) {
                $rating_counter = $upload_counter / $download_counter;
            }
        }
        mysqli_free_result($result);
    }
    mysqli_close($link);
}

// Обработка рейтинга
$dot_pos = strpos((string)$rating_counter, ".");
if ($dot_pos > 0) {
    $rating_counter = (string)round(substr((string)$rating_counter, 0, $dot_pos + 1 + 2), 2);
} else {
    $rating_counter = (string)$rating_counter;
}
$counter_x = $rating_x;
for ($i = 0; $i < strlen($rating_counter); $i++) {
    $d_x = $digits_ini[ifthen($rating_counter[$i] == ".", "dot", $rating_counter[$i]) . "_x"];
    $d_w = $digits_ini[ifthen($rating_counter[$i] == ".", "dot", $rating_counter[$i]) . "_w"];
    imagecopy($img, $digits_img, $counter_x, $rating_y, $d_x, 0, $d_w, imagesy($digits_img));
    $counter_x = $counter_x + $d_w - 1;
}

// Обработка аплоада
$postfix = getPostfix($upload_counter);
$upload_counter = roundCounter($upload_counter, $postfix);
$dot_pos = strpos((string)$upload_counter, ".");
if ($dot_pos > 0) {
    $upload_counter = (string)round(substr((string)$upload_counter, 0, $dot_pos + 1 + 2), 2);
} else {
    $upload_counter = (string)$upload_counter;
}
$counter_x = $upload_x;
for ($i = 0; $i < strlen($upload_counter); $i++) {
    $d_x = $digits_ini[ifthen($upload_counter[$i] == ".", "dot", $upload_counter[$i]) . "_x"];
    $d_w = $digits_ini[ifthen($upload_counter[$i] == ".", "dot", $upload_counter[$i]) . "_w"];
    imagecopy($img, $digits_img, $counter_x, $upload_y, $d_x, 0, $d_w, imagesy($digits_img));
    $counter_x = $counter_x + $d_w - 1;
}
$counter_x += 3;
$d_x = $digits_ini[$postfix . "_x"];
$d_w = $digits_ini[$postfix . "_w"];
imagecopy($img, $digits_img, $counter_x, $upload_y, $d_x, 0, $d_w, imagesy($digits_img));

// Обработка скачивания
$postfix = getPostfix($download_counter);
$download_counter = roundCounter($download_counter, $postfix);
$dot_pos = strpos((string)$download_counter, ".");
if ($dot_pos > 0) {
    $download_counter = (string)round(substr((string)$download_counter, 0, $dot_pos + 1 + 2), 2);
} else {
    $download_counter = (string)$download_counter;
}
$counter_x = $download_x;
for ($i = 0; $i < strlen($download_counter); $i++) {
    $d_x = $digits_ini[ifthen($download_counter[$i] == ".", "dot", $download_counter[$i]) . "_x"];
    $d_w = $digits_ini[ifthen($download_counter[$i] == ".", "dot", $download_counter[$i]) . "_w"];
    imagecopy($img, $digits_img, $counter_x, $download_y, $d_x, 0, $d_w, imagesy($digits_img));
    $counter_x = $counter_x + $d_w - 1;
}
$counter_x += 3;
$d_x = $digits_ini[$postfix . "_x"];
$d_w = $digits_ini[$postfix . "_w"];
imagecopy($img, $digits_img, $counter_x, $download_y, $d_x, 0, $d_w, imagesy($digits_img));

header("Content-type: image/png");
imagepng($img);
imagedestroy($img);
imagedestroy($digits_img);

?>