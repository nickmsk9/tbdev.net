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

if (!defined("IN_TRACKER"))
  die("Попытка взлома!");

// ПОДКЛЮЧЕНИЕ БЭКЕНД-СКРИПТОВ
require_once($rootpath . 'include/init.php');
require_once($rootpath . 'include/global.php');
require_once($rootpath . 'include/config.php');
require_once($rootpath . 'include/config.local.php');
require_once($rootpath . 'include/functions.php');
require_once($rootpath . 'include/blocks.php');
require_once($rootpath . 'include/secrets.php');
require_once($rootpath . 'include/secrets.local.php');

// ПОДКЛЮЧЕНИЕ СИСТЕМЫ БЕЗОПАСНОСТИ
if ($ctracker) require_once($rootpath . 'include/ctracker.php');

// ВКЛЮЧЕНИЕ GZIP-СЖАТИЯ И БУФЕРИЗАЦИИ ВЫВОДА
if ($use_gzip) gzip();

// ВАЖНЫЕ КОНСТАНТЫ
define("BETA", 0); // Установите 0, чтобы убрать пометку *BETA*
define("BETA_NOTICE", "\n<br />Внимание! Это тестовая версия, не предназначенная для промышленного использования!");
define("DEBUG_MODE", 0); // Показывает SQL-запросы внизу страницы для отладки

// ОБЕСПЕЧЕНИЕ ОБРАТНОЙ СОВМЕСТИМОСТИ КОДА
// Для старых скриптов, использующих устаревшие переменные
if (!isset($HTTP_POST_VARS) && isset($_POST)) {
    $HTTP_POST_VARS = $_POST;      // POST-данные формы
    $HTTP_GET_VARS = $_GET;        // GET-параметры URL
    $HTTP_SERVER_VARS = $_SERVER;  // Серверные переменные
    $HTTP_COOKIE_VARS = $_COOKIE;  // Куки пользователя
    $HTTP_ENV_VARS = $_ENV;        // Переменные окружения
    $HTTP_POST_FILES = $_FILES;    // Загруженные файлы
}


// ДОБАВЛЕНИЕ ESCAPED-СЛЭШЕЙ ДЛЯ БЕЗОПАСНОСТИ SQL-ЗАПРОСОВ
    // Обработка GET-параметров
    if (is_array($HTTP_GET_VARS)) {
        foreach ($HTTP_GET_VARS as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    $HTTP_GET_VARS[$k][$k2] = addslashes($v2);
                }
            } else {
                $HTTP_GET_VARS[$k] = addslashes($v);
            }
        }
    }

    // Обработка POST-данных
    if (is_array($HTTP_POST_VARS)) {
        foreach ($HTTP_POST_VARS as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    $HTTP_POST_VARS[$k][$k2] = addslashes($v2);
                }
            } else {
                $HTTP_POST_VARS[$k] = addslashes($v);
            }
        }
    }

    // Обработка COOKIE
    if (is_array($HTTP_COOKIE_VARS)) {
        foreach ($HTTP_COOKIE_VARS as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    $HTTP_COOKIE_VARS[$k][$k2] = addslashes($v2);
                }
            } else {
                $HTTP_COOKIE_VARS[$k] = addslashes($v);
            }
        }
    }

?>