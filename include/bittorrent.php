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

// ВАЖНО: Не редактируйте ниже, если не знаете, что делаете!

/*
 * Защита от повторного включения ядра
 * Protection from double including the core
*/

if (!defined('IN_TRACKER')) {
    // ОПРЕДЕЛЕНИЕ ВАЖНЫХ КОНСТАНТ
    define('IN_TRACKER', true);

    // ЗАЩИТА ОТ ПЕРЕДАЧИ GLOBALS ЧЕРЕЗ ЗАПРОС
    if (isset($_REQUEST['GLOBALS']) OR isset($_FILES['GLOBALS'])) {
        die('Обнаружена попытка подмены глобальных переменных.');
    }

    // НАСТРОЙКА PHP ОКРУЖЕНИЯ
    @error_reporting(E_ALL & ~E_NOTICE);
    @ini_set('error_reporting', E_ALL & ~E_NOTICE);
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '0');
    @ini_set('ignore_repeated_errors', '1');
    @ignore_user_abort(1);
    @set_time_limit(0);
    @session_start();
    define('ROOT_PATH', dirname(dirname(__FILE__)) . "/");

    // СПИСОК РАЗРЕШЕННЫХ REFERRER'ОВ (пустой по умолчанию)
    $allowed_referrers = <<<REF

REF;

    // ПРОВЕРКА REFERRER ДЛЯ POST-ЗАПРОСОВ
    // Предотвращает отправку форм с чужих доменов
    if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST' AND !defined('SKIP_REFERRER_CHECK')) {
        // Получаем имя хоста
        if ($_SERVER['HTTP_HOST'] OR $_ENV['HTTP_HOST']) {
            $http_host = ($_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_HOST'] : $_ENV['HTTP_HOST']);
        } else if ($_SERVER['SERVER_NAME'] OR $_ENV['SERVER_NAME']) {
            $http_host = ($_SERVER['SERVER_NAME'] ? $_SERVER['SERVER_NAME'] : $_ENV['SERVER_NAME']);
        }

        // Проверяем referrer
        if ($http_host AND $_SERVER['HTTP_REFERER']) {
            $http_host = preg_replace('#:80$#', '', trim($http_host));
            $referrer_parts = @parse_url($_SERVER['HTTP_REFERER']);
            
            // Получаем порт из referrer
            if (isset($referrer_parts['port'])) {
                $ref_port = intval($referrer_parts['port']);
            } else {
                $ref_port = 80;
            }
            
            $ref_host = $referrer_parts['host'] . ((!empty($ref_port) AND $ref_port != '80') ? ":$ref_port" : '');

            // Формируем список разрешенных хостов
            $allowed = preg_split('#\s+#', $allowed_referrers, -1, PREG_SPLIT_NO_EMPTY);
            $allowed[] = preg_replace('#^www\.#i', '', $http_host);
            $allowed[] = '.paypal.com'; // Для PayPal платежей

            // Проверяем соответствие
            $pass_ref_check = false;
            foreach ($allowed AS $host) {
                if (preg_match('#' . preg_quote($host, '#') . '$#siU', $ref_host)) {
                    $pass_ref_check = true;
                    break;
                }
            }
            unset($allowed);

            // Если referrer не разрешен
            if ($pass_ref_check == false) {
                die('Для принятия POST-запросов с этого домена администратор должен добавить его в белый список.');
            }
        }
    }

    // ФУНКЦИЯ ДЛЯ ИЗМЕРЕНИЯ ВРЕМЕНИ ВЫПОЛНЕНИЯ
    function timer() {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    // ПРОВЕРКА ТРЕБОВАНИЙ ДЛЯ РАБОТЫ СИСТЕМЫ
    
    // Проверка версии PHP
    if (version_compare(PHP_VERSION, '5.2.0', '<')) {
        die('Ошибка: Ваш сервер использует PHP версии ниже 5.2. Обновите PHP.');
    }
    
    // Проверка наличия SPL
    if (!interface_exists('ArrayAccess')) {
        die('На вашем сервере не установлено расширение PHP SPL (Standard PHP Library). Это обязательное требование для работы системы.');
    }

    // ДОПОЛНИТЕЛЬНЫЕ МЕРЫ БЕЗОПАСНОСТИ
    
    // Предупреждение о наличии папки install
    /*if (file_exists('install')) {
        die('В целях безопасности удалите папку install после установки.');
    }*/
    
    // Проверка register_globals
    if (ini_get('register_globals') == '1' || strtolower(ini_get('register_globals')) == 'on') {
        die('Отключите register_globals в php.ini/.htaccess (опасная опция)');
    }
    
    // Проверка short_open_tag
    if ((int) ini_get('short_open_tag') == '0') {
        die('Включите short_open_tag в php.ini/.htaccess (обязательное требование)');
    }

    // ПРОВЕРКА НАЛИЧИЯ КОНФИГУРАЦИОННЫХ ФАЙЛОВ
    
    if (!file_exists('include/secrets.local.php')) {
        die('Создайте файл include/secrets.local.php и настройте его на основе include/secrets.php (конфиденциальные данные)');
    }

    if (!file_exists('include/config.local.php')) {
        die('Создайте файл include/config.local.php и настройте его на основе include/config.php (настройки системы)');
    }

    // ЗАПИСЬ ВРЕМЕНИ НАЧАЛА ВЫПОЛНЕНИЯ
    $tstart = timer(); // Время начала

    // ПОДКЛЮЧЕНИЕ ЯДРА СИСТЕМЫ
    if (empty($rootpath)) {
        $rootpath = ROOT_PATH;
    }

    require_once($rootpath . 'include/core.php');
}

?>