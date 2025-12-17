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

// Загружаем необходимые файлы
require_once("include/bittorrent.php");
require_once("include/captcha.php");

// Подключаемся к базе данных
dbconn(false);

// Размеры изображения капчи
$img_width = 201;
$img_height = 61;

// Настройки для TTF шрифтов
$min_size = 20;
$max_size = 32;
$min_angle = -30;
$max_angle = 30;

// Проверяем хэш изображения
if (!isset($_GET['imagehash']) || $_GET['imagehash'] == "test" || strlen($_GET['imagehash']) != 32) {
    $imagestring = "Yuna";
} else {
    $imagehash = $_GET['imagehash'];
    $query = sql_query("SELECT * FROM captcha WHERE imagehash=".sqlesc($imagehash)." LIMIT 1");
    
    if (!$query) {
        die('Произошла ошибка при загрузке капчи...');
    }
    
    $regimage = mysqli_fetch_array($query);
    $imagestring = $regimage['imagestring'] ?? 'ОШИБКА';
    
    // Удаляем использованную капчу из базы
    if ($imagestring && $imagestring !== 'ОШИБКА') {
        sql_query("DELETE FROM captcha WHERE imagehash = ".sqlesc($imagehash));
    }
}

// Инициализируем массив TTF шрифтов
$ttf_fonts = [];

// Проверяем поддержку FreeType для TTF шрифтов
if (function_exists("imagefttext")) {
    // Получаем список файлов в директории шрифтов
    $ttfdir = @opendir("include/captcha_fonts");
    if ($ttfdir) {
        while (($file = readdir($ttfdir)) !== false) {
            // Добавляем только TTF файлы
            $filepath = "include/captcha_fonts/" . $file;
            if (is_file($filepath) && pathinfo($filepath, PATHINFO_EXTENSION) === "ttf") {
                $ttf_fonts[] = $filepath;
            }
        }
        closedir($ttfdir);
    }
}

// Определяем, использовать ли TTF шрифты
$use_ttf = !empty($ttf_fonts);

// Получаем список фоновых изображений
$backgrounds = [];
if ($handle = @opendir('include/captcha_backs/')) {
    while (($filename = readdir($handle)) !== false) {
        if (preg_match('#\.(gif|jpg|jpeg|jpe|png)$#i', $filename)) {
            $backgrounds[] = "include/captcha_backs/" . $filename;
        }
    }
    closedir($handle);
}

// Пытаемся загрузить фоновое изображение
$im = null;
$background_loaded = false;

if (!empty($backgrounds)) {
    shuffle($backgrounds); // Перемешиваем массив для случайного выбора
    
    foreach ($backgrounds as $background) {
        if (!file_exists($background)) {
            continue;
        }
        
        $extension = strtolower(pathinfo($background, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
            case 'jpe':
                if (function_exists('imagecreatefromjpeg')) {
                    $im = @imagecreatefromjpeg($background);
                    if ($im !== false) {
                        $background_loaded = true;
                        break 2;
                    }
                }
                break;
                
            case 'gif':
                if (function_exists('imagecreatefromgif')) {
                    $im = @imagecreatefromgif($background);
                    if ($im !== false) {
                        $background_loaded = true;
                        break 2;
                    }
                }
                break;
                
            case 'png':
                if (function_exists('imagecreatefrompng')) {
                    $im = @imagecreatefrompng($background);
                    if ($im !== false) {
                        $background_loaded = true;
                        break 2;
                    }
                }
                break;
        }
    }
}

// Если фон не загружен, создаем пустое изображение
if (!$background_loaded) {
    if (function_exists('imagecreatetruecolor') && gd_version() >= 2) {
        $im = imagecreatetruecolor($img_width, $img_height);
    } else {
        $im = imagecreate($img_width, $img_height);
    }
    
    if (!$im) {
        die("GD библиотека не поддерживается на сервере.");
    }
    
    // Заполняем фон белым цветом
    $bg_color = imagecolorallocate($im, 255, 255, 255);
    imagefill($im, 0, 0, $bg_color);
}

// Поворачиваем изображение для дополнительной безопасности
$current_time = TIMENOW ?? time();
if (($current_time & 2) && function_exists('imagerotate')) {
    $rotated = imagerotate($im, 180, 0);
    if ($rotated !== false) {
        imagedestroy($im);
        $im = $rotated;
    }
}

// Рисуем случайные элементы для защиты
$to_draw = rand(0, 2);
switch ($to_draw) {
    case 1:
        рисовать_круги($im);
        break;
    case 2:
        рисовать_квадраты($im);
        break;
    default:
        рисовать_линии($im);
        break;
}

// Добавляем точки
рисовать_точки($im);

// Добавляем текст капчи
рисовать_текст($im, $imagestring);

// Добавляем рамку
$border_color = imagecolorallocate($im, 0, 0, 0);
imagerectangle($im, 0, 0, $img_width - 1, $img_height - 1, $border_color);

// Отправляем заголовки и выводим изображение
header("Content-Type: image/png");
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Disposition: inline');
header('Content-Transfer-Encoding: binary');

// Выводим изображение в формате PNG
imagepng($im);
imagedestroy($im);
exit;

/**
 * Получает расширение файла
 */
function расширение_файла(string $filename): string {
    return pathinfo($filename, PATHINFO_EXTENSION);
}

/**
 * Рисует случайные линии на изображении
 */
function рисовать_линии(GdImage $im): void {
    global $img_width, $img_height;

    // Вертикальные линии
    for ($i = 10; $i < $img_width; $i += 10) {
        $color = imagecolorallocate($im, rand(150, 255), rand(150, 255), rand(150, 255));
        imageline($im, $i, 0, $i, $img_height, $color);
    }
    
    // Горизонтальные линии
    for ($i = 10; $i < $img_height; $i += 10) {
        $color = imagecolorallocate($im, rand(150, 255), rand(150, 255), rand(150, 255));
        imageline($im, 0, $i, $img_width, $i, $color);
    }
}

/**
 * Рисует случайные круги на изображении
 */
function рисовать_круги(GdImage $im): void {
    global $img_width, $img_height;
    
    $circles = (int)($img_width * $img_height / 100);
    for ($i = 0; $i <= $circles; $i++) {
        $color = imagecolorallocate($im, rand(180, 255), rand(180, 255), rand(180, 255));
        $pos_x = rand(1, $img_width);
        $pos_y = rand(1, $img_height);
        $circ_width = (int)ceil(rand(1, $img_width) / 2);
        $circ_height = rand(1, $img_height);
        imagearc($im, $pos_x, $pos_y, $circ_width, $circ_height, 0, rand(200, 360), $color);
    }
}

/**
 * Рисует случайные точки на изображении
 */
function рисовать_точки(GdImage $im): void {
    global $img_width, $img_height;
    
    $dot_count = (int)($img_width * $img_height / 5);
    for ($i = 0; $i <= $dot_count; $i++) {
        $color = imagecolorallocate($im, rand(200, 255), rand(200, 255), rand(200, 255));
        imagesetpixel($im, rand(0, $img_width), rand(0, $img_height), $color);
    }
}

/**
 * Рисует случайные квадраты на изображении
 */
function рисовать_квадраты(GdImage $im): void {
    global $img_width, $img_height;
    
    $square_count = 30;
    for ($i = 0; $i <= $square_count; $i++) {
        $color = imagecolorallocate($im, rand(150, 255), rand(150, 255), rand(150, 255));
        $pos_x = rand(1, $img_width);
        $pos_y = rand(1, $img_height);
        $sq_width = $sq_height = rand(10, 20);
        $pos_x2 = $pos_x + $sq_height;
        $pos_y2 = $pos_y + $sq_width;
        imagefilledrectangle($im, $pos_x, $pos_y, $pos_x2, $pos_y2, $color);
    }
}

/**
 * Рисует текст капчи на изображении
 */
function рисовать_текст(GdImage $im, string $string): void {
    global $use_ttf, $min_size, $max_size, $min_angle, $max_angle, $ttf_fonts, $img_height, $img_width;
    
    $spacing = $img_width / strlen($string);
    $string_length = strlen($string);
    
    for ($i = 0; $i < $string_length; $i++) {
        // Используем TTF шрифты если доступны
        if ($use_ttf && !empty($ttf_fonts)) {
            // Выбираем случайный размер шрифта
            $font_size = rand($min_size, $max_size);
            
            // Выбираем случайный шрифт
            $font_index = array_rand($ttf_fonts);
            $font = $ttf_fonts[$font_index];
    
            // Выбираем случайный угол наклона
            $rotation = rand($min_angle, $max_angle);
            
            // Устанавливаем цвет
            $r = rand(0, 200);
            $g = rand(0, 200);
            $b = rand(0, 200);
            $color = imagecolorallocate($im, $r, $g, $b);
            
            // Получаем размеры символа
            $dimensions = @imageftbbox($font_size, $rotation, $font, $string[$i], []);
            if ($dimensions) {
                $string_width = $dimensions[2] - $dimensions[0];
                $string_height = $dimensions[3] - $dimensions[5];

                // Рассчитываем позицию символа
                $pos_x = (int)($spacing / 4 + $i * $spacing);
                $pos_y = (int)ceil(($img_height - $string_height / 2));
                
                if ($pos_x + $string_width > $img_width) {
                    $pos_x = (int)($pos_x - ($pos_x - $string_width));
                }

                // Рисуем тень
                $shadow_x = rand(-3, 3) + $pos_x;
                $shadow_y = rand(-3, 3) + $pos_y;
                $shadow_color = imagecolorallocate($im, $r + 20, $g + 20, $b + 20);
                @imagefttext($im, $font_size, $rotation, $shadow_x, $shadow_y, $shadow_color, $font, $string[$i], []);
                
                // Рисуем основной текст
                @imagefttext($im, $font_size, $rotation, $pos_x, $pos_y, $color, $font, $string[$i], []);
            }
        } else {
            // Используем встроенные шрифты GD
            $string_width = imagefontwidth(5);
            $string_height = imagefontheight(5);

            // Рассчитываем позицию символа
            $pos_x = (int)($spacing / 4 + $i * $spacing);
            $pos_y = (int)($img_height / 2 - $string_height - 10 + rand(-3, 3));
            
            // Создаем временное изображение для символа
            if (function_exists('imagecreatetruecolor') && gd_version() >= 2) {
                $temp_im = imagecreatetruecolor(15, 20);
            } else {
                $temp_im = imagecreate(15, 20);
            }
            
            if ($temp_im) {
                $bg_color = imagecolorallocate($temp_im, 255, 255, 255);
                imagefill($temp_im, 0, 0, $bg_color);
                imagecolortransparent($temp_im, $bg_color);

                // Устанавливаем цвет
                $r = rand(0, 200);
                $g = rand(0, 200);
                $b = rand(0, 200);
                $color = imagecolorallocate($temp_im, $r, $g, $b);
                
                // Рисуем тень
                $shadow_x = rand(-1, 1);
                $shadow_y = rand(-1, 1);
                $shadow_color = imagecolorallocate($temp_im, $r + 50, $g + 50, $b + 50);
                imagestring($temp_im, 5, 1 + $shadow_x, 1 + $shadow_y, $string[$i], $shadow_color);
                
                // Рисуем основной текст
                imagestring($temp_im, 5, 1, 1, $string[$i], $color);
                
                // Копируем на основное изображение
                imagecopyresized($im, $temp_im, $pos_x, $pos_y, 0, 0, 40, 55, 15, 20);
                imagedestroy($temp_im);
            }
        }
    }
}

/**
 * Получает версию GD библиотеки
 */
function gd_version(): float {
    static $gd_version = null;
    
    if ($gd_version !== null) {
        return $gd_version;
    }
    
    if (!extension_loaded('gd')) {
        $gd_version = 0.0;
        return $gd_version;
    }
    
    // Для PHP 8.1+ используем gd_info()
    if (function_exists('gd_info')) {
        $gd_info = gd_info();
        if (preg_match('/\d+\.\d+(?:\.\d+)?/', $gd_info['GD Version'], $matches)) {
            $gd_version = (float)$matches[0];
            return $gd_version;
        }
    }
    
    $gd_version = 1.0; // Версия по умолчанию
    return $gd_version;
}

?>