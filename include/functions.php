<?php

/*
// +----------------------------------------------------------------------------+
// | Project:    TBDevYSE - TBDev Yuna Scatari Edition							|
// +----------------------------------------------------------------------------+
// | This file is part of TBDevYSE. TBDevYSE is based on TBDev,					|
// | originally by RedBeard of TorrentBits, extensively modified by				|
// | Gartenzwerg.																|
// |									 										|
// | TBDevYSE is free software; you can redistribute it and/or modify			|
// | it under the terms of the GNU General Public License as published by		|
// | the Free Software Foundation; either version 2 of the License, or			|
// | (at your option) any later version.										|
// |																			|
// | TBDevYSE is distributed in the hope that it will be useful,				|
// | but WITHOUT ANY WARRANTY; without even the implied warranty of				|
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the				|
// | GNU General Public License for more details.								|
// |																			|
// | You should have received a copy of the GNU General Public License			|
// | along with TBDevYSE; if not, write to the Free Software Foundation,		|
// | Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA				|
// +----------------------------------------------------------------------------+
// |					       Do not remove above lines!						|
// +----------------------------------------------------------------------------+
*/

# IMPORTANT: Do not edit below unless you know what you are doing!
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

require_once($rootpath . 'include/functions_global.php');
require_once($rootpath . 'include/functions_torrenttable.php');
require_once($rootpath . 'include/functions_commenttable.php');

///////////////////////////////////////////////////////////////////////////////
// Check open port, requires --enable-sockets
function check_port($host, $port, $timeout, $force_fsock = false) {
	if (function_exists('socket_create') && !$force_fsock) {
		// Create a TCP/IP socket.
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if ($socket == false) {
			return false;
		}
		//
		if (socket_set_nonblock($socket) == false) {
			socket_close($socket);
			return false;
		}
		//
		@socket_connect($socket, $host, $port); // will return FALSE as it's async, so no check
		//
		if (socket_set_block($socket) == false) {
			socket_close($socket);
			return false;
		}

		switch(socket_select($r = array($socket), $w = array($socket), $f = array($socket), $timeout)) {
			case 2:
			// Refused
				$result = false;
				break;
			case 1:
				$result = true;
				break;
			case 0:
				// Timeout
				$result = false;
				break;
		}

		// cleanup
		socket_close($socket);
	} else {
		$socket = @fsockopen($host, $port, $errno, $errstr, 5);
		if (!$socket)
			$result = false;
		else {
			$result = true;
			@fclose($socket);
		}
	}

	return $result;
}

function is_theme($theme = "") {
	global $rootpath;
	return file_exists($rootpath . "themes/$theme/stdhead.php") && file_exists($rootpath . "themes/$theme/stdfoot.php") && file_exists($rootpath . "themes/$theme/template.php");
}

function get_themes() {
	global $rootpath;
	$handle = opendir($rootpath . "themes");
	$themelist = array();
	while ($file = readdir($handle)) {
		if (is_theme($file) && $file != "." && $file != "..") {
			$themelist[] = $file;
		}
	}
	closedir($handle);
	sort($themelist);
	return $themelist;
}

function theme_selector($sel_theme = "", $use_fsw = false) {
	global $DEFAULTBASEURL;
	$themes = get_themes();
	$content = "<select name=\"theme\"".($use_fsw ? " onchange=\"window.location='$DEFAULTBASEURL/changetheme.php?theme='+this.options[this.selectedIndex].value\"" : "").">\n";
	foreach ($themes as $theme)
		$content .= "<option value=\"$theme\"".($theme == $sel_theme ? " selected" : "").">$theme</option>\n";
	$content .= "</select>";
	return $content;
}

function select_theme(): string
{
    global $CURUSER, $default_theme;

    $theme = $default_theme;

    if (is_array($CURUSER) && !empty($CURUSER)) {
        $theme = (string)($CURUSER['theme'] ?? $default_theme);
    }

    if ($theme === '' || !is_theme($theme)) {
        $theme = (string)$default_theme;
    }

    return $theme;
}


function decode_to_utf8($int = 0) {
	$t = '';
	if ( $int < 0 ) {
		return chr(0);
	} else if ( $int <= 0x007f ) {
		$t .= chr($int);
	} else if ( $int <= 0x07ff ) {
		$t .= chr(0xc0 | ($int >> 6));
		$t .= chr(0x80 | ($int & 0x003f));
	} else if ( $int <= 0xffff ) {
		$t .= chr(0xe0 | ($int  >> 12));
		$t .= chr(0x80 | (($int >> 6) & 0x003f));
		$t .= chr(0x80 | ($int  & 0x003f));
	} else if ( $int <= 0x10ffff ) {
		$t .= chr(0xf0 | ($int  >> 18));
		$t .= chr(0x80 | (($int >> 12) & 0x3f));
		$t .= chr(0x80 | (($int >> 6) & 0x3f));
		$t .= chr(0x80 | ($int  &  0x3f));
	} else {
		return chr(0);
	}
	return $t;
}

function convert_unicode(string $t, string $to = 'windows-1251'): string 
{
    $to = strtolower($to);
    
    // Удаляем устаревший модификатор /e из preg_replace
    if ($to == 'utf-8') {
        $t = preg_replace_callback(
            '#%u([0-9A-F]{1,4})#i', 
            function($matches) {
                $hex = hexdec($matches[1]);
                if ($hex < 0x80) {
                    return chr($hex);
                } elseif ($hex < 0x800) {
                    return chr(0xC0 | ($hex >> 6)) . chr(0x80 | ($hex & 0x3F));
                } else {
                    return chr(0xE0 | ($hex >> 12)) . chr(0x80 | (($hex >> 6) & 0x3F)) . chr(0x80 | ($hex & 0x3F));
                }
            }, 
            $t
        );
        $t = urldecode($t);
    } else {
        $t = preg_replace_callback(
            '#%u([0-9A-F]{1,4})#i',
            function($matches) {
                return '&#' . hexdec($matches[1]) . ';';
            },
            $t
        );
        $t = urldecode($t);
        $t = html_entity_decode($t, ENT_NOQUOTES, $to);
    }
    
    return $t;
}

function strip_magic_quotes($arr) {
	foreach ($arr as $k => $v) {
		if (is_array($v)) {
			$arr[$k] = strip_magic_quotes($v);
			} else {
			$arr[$k] = stripslashes($v);
			}
	}
	return $arr;
}

function local_user() {
	return $_SERVER["SERVER_ADDR"] == $_SERVER["REMOTE_ADDR"];
}

function sql_query(string $query) 
{
    global $queries, $query_stat, $querytime, $mysql_link;
    
    // Инициализация переменных если они не существуют
    $queries = $queries ?? 0;
    $query_stat = $query_stat ?? [];
    $querytime = $querytime ?? 0;
    
    $queries++;
    $query_start_time = microtime(true);
    
    // Выполняем запрос
    $result = mysqli_query($mysql_link, $query);
    
    $query_end_time = microtime(true);
    $query_time = ($query_end_time - $query_start_time);
    $querytime += $query_time;
    
    // Логирование запроса
    $query_stat[] = [
        "seconds" => round($query_time, 6),
        "query" => $query,
        "error" => $result ? '' : mysqli_error($mysql_link)
    ];
    
    if (!$result) {
        $error = mysqli_error($mysql_link);
        $errno = mysqli_errno($mysql_link);
        
        // Создаем лог ошибок
        error_log("SQL Error [$errno]: $error - Query: $query");
        
        // В режиме отладки показываем ошибку
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            die("SQL Error [$errno]: $error<br>Query: $query");
        }
        
        return false;
    }
    
    return $result;
}

function dbconn(bool $autoclean = false, bool $lightmode = false): void
{
    global $mysql_host, $mysql_user, $mysql_pass, $mysql_db, $mysql_charset;
    
    // Используем mysqli вместо устаревшего mysql
    $mysqli = mysqli_init();
    
    if (!$mysqli) {
        die("dbconn: Не удалось инициализировать MySQLi");
    }
    
    // Устанавливаем кодировку подключения
    if (!mysqli_options($mysqli, MYSQLI_SET_CHARSET_NAME, $mysql_charset)) {
        die("dbconn: Не удалось установить кодировку");
    }
    
    // Устанавливаем подключение
    if (!mysqli_real_connect($mysqli, $mysql_host, $mysql_user, $mysql_pass, $mysql_db)) {
        die("[" . mysqli_connect_errno() . "] dbconn: mysqli_connect: " . mysqli_connect_error());
    }
    
    // Сохраняем соединение в глобальной переменной для обратной совместимости
    global $mysql_link;
    $mysql_link = $mysqli;
    
    // Устанавливаем кодировку
    mysqli_set_charset($mysqli, $mysql_charset);
    
    userlogin($lightmode);
    
    if (basename($_SERVER['SCRIPT_FILENAME']) == 'index.php') {
        register_shutdown_function("autoclean");
    }
    
    // Функция закрытия соединения при завершении скрипта
    register_shutdown_function(function() use ($mysqli) {
        if ($mysqli) {
            mysqli_close($mysqli);
        }
    });
}

function userlogin(bool $lightmode = false): void
{
    global $SITE_ONLINE, $default_language, $tracker_lang, $use_lang, $use_ipbans, $_COOKIE_SALT;

    unset($GLOBALS['CURUSER']);

    // --- Security checks ---
    if ($_COOKIE_SALT === 'default'
        && ($_SERVER['SERVER_ADDR'] ?? '') !== '127.0.0.1'
        && ($_SERVER['SERVER_ADDR'] ?? '') !== ($_SERVER['REMOTE_ADDR'] ?? '')
    ) {
        die('Ошибка безопасности! Пожалуйста, измените значение $_COOKIE_SALT в файле include/config.local.php на уникальное.');
    }

    if (empty($_COOKIE_SALT)) {
        die('Ошибка в настройках <a href="http://www.php.net">PHP</a>... Пожалуйста, убедитесь что все настройки корректны!');
    }

    $ip  = getip();
    $nip = ip2long($ip);
    if ($nip === false) {
        // если вдруг кривой IP — считаем гостем
        goto guest;
    }

    // --- IP bans: 1 быстрый запрос, только нужное поле, LIMIT 1 ---
    if (!empty($use_ipbans) && !$lightmode) {
        $res = sql_query("SELECT comment FROM bans WHERE $nip >= first AND $nip <= last LIMIT 1") or sqlerr(__FILE__, __LINE__);
        if (mysqli_num_rows($res) > 0) {
            $ban = mysqli_fetch_assoc($res);
            $comment = $ban['comment'] ?? 'Без комментария';

            header('HTTP/1.0 403 Forbidden');
            print("<html><body><h1>403 Запрещено</h1>Ваш IP адрес заблокирован.<br>Причина: " . htmlspecialchars($comment) . "</body></html>\n");
            die;
        }
    }

    $c_uid  = $_COOKIE[COOKIE_UID] ?? '';
    $c_pass = $_COOKIE[COOKIE_PASSHASH] ?? '';

    // Если сайт выключен или нет кук — гость
    if (empty($SITE_ONLINE) || $c_uid === '' || $c_pass === '') {
        goto guest;
    }

    $id = (int)$c_uid;
    // TBDev обычно хранит md5 = 32 hex. Проверим строго.
    if ($id <= 0 || strlen($c_pass) !== 32 || !ctype_xdigit($c_pass)) {
        goto guest;
    }

    // --- Load user: НЕ SELECT * (меньше данных, быстрее) ---
    $sql = "
        SELECT id, username, class, override_class, ip, passhash, language, enabled
        FROM users
        WHERE id = $id
        LIMIT 1
    ";
    $res = sql_query($sql);
    if (!$res) {
        goto guest;
    }

    $row = mysqli_fetch_assoc($res);
    if (!$row) {
        goto guest;
    }

    // --- subnet быстрее чем explode/implode ---
    // было: A.B.C.D -> A.B.0.0
    $p2 = strrpos($ip, '.');
    if ($p2 === false) goto guest;
    $p1 = strrpos($ip, '.', - (strlen($ip) - $p2 + 1)); // точка перед последней
    if ($p1 === false) goto guest;
    $subnet = substr($ip, 0, $p1) . '.0.0';

    $expected_hash = md5($row['passhash'] . COOKIE_SALT . $subnet);
    if (!hash_equals($expected_hash, $c_pass)) {
        goto guest;
    }

    // --- Update user only if needed ---
    $updates = [];
    if ($ip !== ($row['ip'] ?? '')) {
        $updates[] = 'ip = ' . sqlesc($ip);
        $row['ip'] = $ip;
    }
    // сохраняем твою логику: last_access обновлять всегда
    $updates[] = 'last_access = ' . sqlesc(get_date_time());

    if ($updates) {
        sql_query('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ' . (int)$row['id']) or sqlerr(__FILE__, __LINE__);
    }

    // --- override class ---
    if (isset($row['override_class'], $row['class']) && (int)$row['override_class'] < (int)$row['class']) {
        $row['class'] = $row['override_class'];
    }

    $GLOBALS['CURUSER'] = $row;

    // --- language include ---
    if (!empty($use_lang)) {
        $lang = $row['language'] ?? $default_language;
        include_once('languages/lang_' . $lang . '/lang_main.php');
    }

    // --- blocked user check (доп. запрос только если надо) ---
    if (($row['enabled'] ?? 'yes') === 'no') {
        $GLOBALS['use_blocks'] = 0;

        $ban_res = sql_query('SELECT reason, disuntil FROM users_ban WHERE userid = ' . (int)$row['id'] . ' LIMIT 1');
        if ($ban_res && mysqli_num_rows($ban_res) > 0) {
            $ban_info = mysqli_fetch_assoc($ban_res);
            $reason   = $ban_info['reason']   ?? 'Не указана';
            $disuntil = $ban_info['disuntil'] ?? '0000-00-00 00:00:00';
        } else {
            $reason = 'Не указана';
            $disuntil = '0000-00-00 00:00:00';
        }

        $message = 'Вы заблокированы.';
        if ($disuntil !== '0000-00-00 00:00:00') {
            $message .= '<br />Дата разблокировки: ' . htmlspecialchars($disuntil);
        } else {
            $message .= '<br />Дата разблокировки: бессрочно';
        }
        $message .= '<br />Причина: ' . htmlspecialchars($reason);

        stderr($tracker_lang['error'] ?? 'Ошибка', $message);
    }

    if (!$lightmode) {
        user_session();
    }
    return;

guest:
    if (!empty($use_lang)) {
        include_once('languages/lang_' . $default_language . '/lang_main.php');
    }
    user_session();
}

function get_server_load() {
    global $tracker_lang, $phpver;
    
    if (strtolower(substr(PHP_OS, 0, 3)) === 'win') {
        return 0;
    }
    
    $returnload = $tracker_lang['unknown'] ?? 'Unknown'; // Используем значение по умолчанию
    
    if (@file_exists("/proc/loadavg")) {
        $load = @file_get_contents("/proc/loadavg");
        if ($load !== false) {
            $serverload = explode(" ", $load);
            if (isset($serverload[0])) {
                $returnload = round($serverload[0], 4);
            }
        }
        
        // Если не удалось получить через /proc/loadavg, пробуем uptime
        if ($returnload === ($tracker_lang['unknown'] ?? 'Unknown')) {
            $load = @exec("uptime");
            if (!empty($load)) {
                // Используем explode вместо устаревшего split()
                $load_parts = explode("load average:", $load);
                if (isset($load_parts[1])) {
                    $serverload = explode(",", trim($load_parts[1]));
                    if (isset($serverload[0])) {
                        $returnload = trim($serverload[0]);
                    }
                }
            }
        }
    } else {
        // Для систем без /proc/loadavg
        $load = @exec("uptime");
        if (!empty($load)) {
            // Используем explode вместо устаревшего split()
            $load_parts = explode("load average:", $load);
            if (isset($load_parts[1])) {
                $serverload = explode(",", trim($load_parts[1]));
                if (isset($serverload[0])) {
                    $returnload = trim($serverload[0]);
                }
            }
        }
    }
    
    return $returnload;
}

function user_session(): void
{
    global $CURUSER, $use_sessions;

    if (empty($use_sessions)) {
        return;
    }

    $ip  = getip();
    $url = $_SERVER['REQUEST_URI'] ?? (getenv('REQUEST_URI') ?: '');
    $sid = session_id();

    if (!empty($CURUSER)) {
        $uid      = (int)($CURUSER['id'] ?? 0);
        $username = (string)($CURUSER['username'] ?? '');
        $class    = (int)($CURUSER['class'] ?? 0);
    } else {
        $uid      = 0;      // важно: 0, а не -1
        $username = '';
        $class    = 0;
    }

    $ctime = time();
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Быстрее освобождаем lock сессии (если она реально активна)
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // Корректный WHERE:
    // 1) sid если есть
    // 2) иначе uid только если это реальный юзер (>0)
    // 3) иначе ip (гость)
    if ($sid !== '') {
        $where = 'sid = ' . sqlesc($sid);
    } elseif ($uid > 0) {
        $where = 'uid = ' . $uid;
    } else {
        $where = 'ip = ' . sqlesc($ip);
    }

    // Один набор значений — без массивов/implode/array_map
    $sqlUpdate = "
        UPDATE sessions SET
            sid = " . sqlesc($sid) . ",
            uid = " . $uid . ",
            username = " . sqlesc($username) . ",
            class = " . $class . ",
            ip = " . sqlesc($ip) . ",
            time = " . $ctime . ",
            url = " . sqlesc($url) . ",
            useragent = " . sqlesc($agent) . "
        WHERE $where
    ";
    sql_query($sqlUpdate) or sqlerr(__FILE__, __LINE__);

    // Если UPDATE ничего не затронул — вставляем
    if (mysql_modified_rows() < 1) {
        $sqlInsert = "
            INSERT INTO sessions (sid, uid, username, class, ip, time, url, useragent)
            VALUES (
                " . sqlesc($sid) . ",
                " . $uid . ",
                " . sqlesc($username) . ",
                " . $class . ",
                " . sqlesc($ip) . ",
                " . $ctime . ",
                " . sqlesc($url) . ",
                " . sqlesc($agent) . "
            )
        ";
        sql_query($sqlInsert) or sqlerr(__FILE__, __LINE__);
    }
}

function unesc($x) {
	return $x;
}

function gzip(): void
{
    global $use_gzip;
    static $already_loaded = false;
    
    if ($already_loaded || !$use_gzip) {
        return;
    }
    
    $already_loaded = true;
    
    // Проверяем, не сжато ли уже
    if (headers_sent() || ob_get_length() > 0) {
        return;
    }
    
    // Проверяем поддержку браузером
    $accept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    if (strpos($accept_encoding, 'gzip') === false) {
        return;
    }
    
    // Проверяем расширение zlib
    if (!extension_loaded('zlib') || !function_exists('ob_gzhandler')) {
        return;
    }
    
    // Проверяем, не включено ли уже сжатие на уровне сервера
    if (ini_get('zlib.output_compression') == '1') {
        return;
    }
    
    // Устанавливаем правильные заголовки
    if (!headers_sent()) {
        header('Content-Encoding: gzip');
        header('Vary: Accept-Encoding');
    }
    
    // Включаем буферизацию с проверкой
    try {
        if (ob_start('ob_gzhandler')) {
            return;
        }
    } catch (Exception $e) {
        // В случае ошибки - обычная буферизация
    }
    
    // Фолбэк
    ob_start();
}
// IP Validation
function validip($ip) {
	if (!empty($ip) && $ip == long2ip(ip2long($ip)))
	{
		// reserved IANA IPv4 addresses
		// http://www.iana.org/assignments/ipv4-address-space
		$reserved_ips = array (
				array('0.0.0.0','2.255.255.255'),
				array('10.0.0.0','10.255.255.255'),
				array('127.0.0.0','127.255.255.255'),
				array('169.254.0.0','169.254.255.255'),
				array('172.16.0.0','172.31.255.255'),
				array('192.0.2.0','192.0.2.255'),
				array('192.168.0.0','192.168.255.255'),
				array('255.255.255.0','255.255.255.255')
		);

		foreach ($reserved_ips as $r) {
				$min = ip2long($r[0]);
				$max = ip2long($r[1]);
				if ((ip2long($ip) >= $min) && (ip2long($ip) <= $max)) return false;
		}
		return true;
	}
	else return false;
}

function getip() {

	// Code commented due to possible hackers/banned users to fake their ip with http headers

	/*if (isset($_SERVER)) {
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && validip($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif (isset($_SERVER['HTTP_CLIENT_IP']) && validip($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
	} else {
		if (getenv('HTTP_X_FORWARDED_FOR') && validip(getenv('HTTP_X_FORWARDED_FOR'))) {
			$ip = getenv('HTTP_X_FORWARDED_FOR');
		} elseif (getenv('HTTP_CLIENT_IP') && validip(getenv('HTTP_CLIENT_IP'))) {
			$ip = getenv('HTTP_CLIENT_IP');
		} else {
			$ip = getenv('REMOTE_ADDR');
		 }
	}*/

	$ip = getenv('REMOTE_ADDR');

	return $ip;
}


/**
 * Функция автоматической очистки системы
 * Оптимизирована для PHP 8.1+ с улучшенной обработкой ошибок и логированием
 */
function autoclean(): void 
{
    global $autoclean_interval, $rootpath, $mysql_link;

    // Начинаем транзакцию для атомарности операций
    sql_query("START TRANSACTION") or sqlerr(__FILE__, __LINE__);
    
    try {
        $now = time();
        $last_clean_time = null;
        
        // Получаем время последней очистки
        $res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime' FOR UPDATE");
        
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            $last_clean_time = (int)$row['value_u'];
            
            // Проверяем, не нужно ли выполнить очистку
            if (($last_clean_time + $autoclean_interval) > $now) {
                sql_query("COMMIT");
                return; // Еще рано для очистки
            }
            
            // Проверяем, не установлено ли время в будущем (ошибка)
            if ($last_clean_time > $now) {
                // Корректируем время
                sql_query("UPDATE avps SET value_u = $now WHERE arg = 'lastcleantime'");
                sql_query("COMMIT");
                write_log("Исправлено некорректное время последней очистки: $last_clean_time -> $now", "system", "autoclean_fix");
                return;
            }
            
            // Обновляем время последней очистки
            sql_query("UPDATE avps SET value_u = $now WHERE arg = 'lastcleantime'");
        } else {
            // Первый запуск - создаем запись
            $now_escaped = (int)$now;
            sql_query("INSERT INTO avps (arg, value_u, value_s) VALUES ('lastcleantime', $now_escaped, '')");
        }
        
        // Проверяем, была ли обновлена запись
        if (mysqli_affected_rows($mysql_link) === 0 && !is_null($last_clean_time)) {
            // Кто-то другой уже обновил время - откатываем
            sql_query("ROLLBACK");
            return;
        }
        
        // Фиксируем изменения
        sql_query("COMMIT") or sqlerr(__FILE__, __LINE__);
        
        // Выполняем очистку
        if (file_exists($rootpath . 'include/cleanup.php')) {
            require_once($rootpath . 'include/cleanup.php');
            
            // Логируем начало очистки
            write_log("Запуск автоматической очистки системы", "system", "autoclean_start");
            
            // Выполняем очистку
            docleanup();
            
            // Логируем завершение
            write_log("Автоматическая очистка завершена успешно", "system", "autoclean_complete");
            
            // Обновляем статистику выполнения
            update_cleanup_stats();
        } else {
            write_log("Ошибка: файл cleanup.php не найден по пути: $rootpath" . 'include/cleanup.php', "system", "autoclean_error");
        }
        
    } catch (Exception $e) {
        // Откатываем транзакцию при ошибке
        sql_query("ROLLBACK");
        
        // Логируем ошибку
        write_log("Ошибка при выполнении autoclean: " . $e->getMessage(), "system", "autoclean_error");
        
        // Можно добавить уведомление администратору
        // notify_admin("Ошибка autoclean", $e->getMessage());
        
        throw $e; // Пробрасываем исключение дальше
    }
}

/**
 * Обновление статистики выполнения очистки
 */
function update_cleanup_stats(): void
{
    global $mysql_link;
    
    $now = time();
    $today = date('Y-m-d');
    
    // Проверяем существование записи за сегодня
    $res = sql_query("SELECT id, count FROM cleanup_stats WHERE date = '$today'");
    
    if ($res && mysqli_num_rows($res) > 0) {
        // Обновляем существующую запись
        $row = mysqli_fetch_assoc($res);
        $new_count = (int)$row['count'] + 1;
        $last_run = date('Y-m-d H:i:s', $now);
        
        sql_query("UPDATE cleanup_stats 
                   SET count = $new_count, last_run = '$last_run', updated_at = NOW() 
                   WHERE id = " . (int)$row['id']);
    } else {
        // Создаем новую запись
        $last_run = date('Y-m-d H:i:s', $now);
        sql_query("INSERT INTO cleanup_stats (date, count, last_run, created_at) 
                   VALUES ('$today', 1, '$last_run', NOW())");
    }
}

/**
 * Функция для создания таблицы статистики очистки
 * (выполнить один раз вручную или при установке)
 */
function create_cleanup_stats_table(): void
{
    $sql = "CREATE TABLE IF NOT EXISTS cleanup_stats (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL UNIQUE,
        count INT UNSIGNED NOT NULL DEFAULT 0,
        last_run DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_date (date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    sql_query($sql);
}

/**
 * Получение статистики очистки за период
 */
function get_cleanup_stats(string $start_date, string $end_date): array
{
    $stats = [
        'total_runs' => 0,
        'avg_runs_per_day' => 0,
        'last_run' => null,
        'daily_stats' => []
    ];
    
    $start_date_esc = mysqli_real_escape_string($mysql_link, $start_date);
    $end_date_esc = mysqli_real_escape_string($mysql_link, $end_date);
    
    $res = sql_query("SELECT date, count, last_run 
                      FROM cleanup_stats 
                      WHERE date BETWEEN '$start_date_esc' AND '$end_date_esc' 
                      ORDER BY date DESC");
    
    if ($res) {
        $total_days = 0;
        while ($row = mysqli_fetch_assoc($res)) {
            $stats['daily_stats'][] = $row;
            $stats['total_runs'] += (int)$row['count'];
            $total_days++;
            
            if ($row['last_run'] && (is_null($stats['last_run']) || $row['last_run'] > $stats['last_run'])) {
                $stats['last_run'] = $row['last_run'];
            }
        }
        
        if ($total_days > 0) {
            $stats['avg_runs_per_day'] = round($stats['total_runs'] / $total_days, 2);
        }
    }
    
    return $stats;
}

/**
 * Уведомление администратора о проблемах
 * (опциональная функция)
 */
function notify_admin(string $subject, string $message): void
{
    // Пример реализации отправки email
    /*
    $admin_email = 'admin@example.com';
    $headers = "From: tracker@example.com\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
    
    @mail($admin_email, $subject, $message, $headers);
    */
    
    // Или запись в специальный лог для мониторинга
    $log_message = date('[Y-m-d H:i:s]') . " $subject: $message\n";
    @file_put_contents('/var/log/tracker/autoclean_errors.log', $log_message, FILE_APPEND);
}


function mksize($bytes) {
	if ($bytes < 1000 * 1024)
		return number_format($bytes / 1024, 2) . " kB";
	elseif ($bytes < 1000 * 1048576)
		return number_format($bytes / 1048576, 2) . " MB";
	elseif ($bytes < 1000 * 1073741824)
		return number_format($bytes / 1073741824, 2) . " GB";
	else
		return number_format($bytes / 1099511627776, 2) . " TB";
}

function mksizeint($bytes) {
		$bytes = max(0, $bytes);
		if ($bytes < 1000)
				return floor($bytes) . " B";
		elseif ($bytes < 1000 * 1024)
				return floor($bytes / 1024) . " kB";
		elseif ($bytes < 1000 * 1048576)
				return floor($bytes / 1048576) . " MB";
		elseif ($bytes < 1000 * 1073741824)
				return floor($bytes / 1073741824) . " GB";
		else
				return floor($bytes / 1099511627776) . " TB";
}

function deadtime() {
	global $announce_interval;
	return time() - floor($announce_interval * 1.3);
}

function mkprettytime($s) {
    if ($s < 0)
	$s = 0;
    $t = array();
    foreach (array("60:sec","60:min","24:hour","0:day") as $x) {
		$y = explode(":", $x);
		if ($y[0] > 1) {
		    $v = $s % $y[0];
		    $s = floor($s / $y[0]);
		} else
		    $v = $s;
	$t[$y[1]] = $v;
    }

    if ($t["day"])
	return $t["day"] . "d " . sprintf("%02d:%02d:%02d", $t["hour"], $t["min"], $t["sec"]);
    if ($t["hour"])
	return sprintf("%d:%02d:%02d", $t["hour"], $t["min"], $t["sec"]);
	return sprintf("%d:%02d", $t["min"], $t["sec"]);
}

function mkglobal($vars) {
	if (!is_array($vars))
		$vars = explode(":", $vars);
	foreach ($vars as $v) {
		if (isset($_GET[$v]))
			$GLOBALS[$v] = unesc($_GET[$v]);
		elseif (isset($_POST[$v]))
			$GLOBALS[$v] = unesc($_POST[$v]);
		else
			return 0;
	}
	return 1;
}

function tr($x, $y, $noesc=0, $prints = true, $width = "", $relation = '') {
	if ($noesc)
		$a = $y;
	else {
		$a = htmlspecialchars_uni($y);
		$a = str_replace("\n", "<br />\n", $a);
	}
	if ($prints) {
	  $print = "<td width=\"". $width ."\" class=\"heading\" valign=\"top\" align=\"right\">$x</td>";
	  $colpan = "align=\"left\"";
	} else {
		$colpan = "colspan=\"2\"";
	}

	print("<tr".( $relation ? " relation=\"$relation\"" : "").">$print<td valign=\"top\" $colpan>$a</td></tr>\n");
}

function validfilename($name) {
	return preg_match('/^[^\0-\x1f:\\\\\/?*\xff#<>|]+$/si', $name);
}

function validemail($email) {
	return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function mail_possible($email) {
	list(, $domain) = explode('@', $email);
	if (function_exists('checkdnsrr'))
		return checkdnsrr($domain, 'MX');
	else
		return true;
}

function send_pm($sender, $receiver, $added, $subject, $msg) {
	sql_query('INSERT INTO messages (sender, receiver, added, subject, msg) VALUES ('.implode(', ', array_map('sqlesc', array($sender, $receiver, $added, $subject, $msg))).')') or sqlerr(__FILE__,__LINE__);
}

function sent_mail($to, $fromname, $fromemail, $subject, $body, $multiple = false, $multiplemail = '') {
    global $SITENAME, $SITEEMAIL, $smtptype, $smtp, $smtp_host, $smtp_port, $smtp_from, $smtpaddress, $accountname, $accountpassword, $rootpath;
    
    $result = true;
    
    if ($smtptype == 'default') {
        $headers = "From: $SITEEMAIL\r\n";
        $headers .= "Content-type: text/plain; charset=utf-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        if (!@mail($to, $subject, $body, $headers)) {
            $result = false;
        }
    } 
    elseif ($smtptype == 'advanced') {
        $eol = "\r\n"; // Всегда используем CRLF для email
        $mid = md5(getip() . $fromname);
        $name = $_SERVER["SERVER_NAME"] ?? 'localhost';
        
        $headers = "From: $fromname <$fromemail>" . $eol;
        $headers .= "Reply-To: $fromname <$fromemail>" . $eol;
        $headers .= "Return-Path: $fromname <$fromemail>" . $eol;
        $headers .= "Message-ID: <$mid.thesystem@$name>" . $eol;
        $headers .= "X-Mailer: PHP v" . phpversion() . $eol;
        $headers .= "MIME-Version: 1.0" . $eol;
        $headers .= "Content-type: text/plain; charset=utf-8" . $eol; // Используем utf-8 вместо windows-1251
        $headers .= "X-Sender: PHP" . $eol;
        
        if ($multiple && !empty($multiplemail)) {
            $headers .= "Bcc: $multiplemail" . $eol;
        }
        
        // Исправляем условие для Windows
        $windows = (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN');
        
        if ($smtp == "yes") {
            ini_set('SMTP', $smtp_host);
            ini_set('smtp_port', $smtp_port);
            if ($windows) {
                ini_set('sendmail_from', $smtp_from);
            }
        }
        
        if (!@mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $body, $headers)) {
            $result = false;
        }
        
        // Восстанавливаем настройки
        if ($smtp == "yes") {
            ini_restore('SMTP');
            ini_restore('smtp_port');
            if ($windows) {
                ini_restore('sendmail_from');
            }
        }
    } 
    elseif ($smtptype == 'external') {
        // Проверяем существование файла
        $smtp_file = $rootpath . 'include/smtp/smtp.lib.php';
        if (!file_exists($smtp_file)) {
            return false;
        }
        
        require_once($smtp_file);
        
        if (!class_exists('smtp')) {
            return false;
        }
        
        try {
            $mail = new smtp();
            $mail->debug(false);
            $mail->open($smtp_host, $smtp_port);
            
            if (!empty($accountname) && !empty($accountpassword)) {
                $mail->auth($accountname, $accountpassword);
            }
            
            $mail->from($SITEEMAIL);
            $mail->to($to);
            $mail->subject($subject);
            $mail->body($body);
            
            $result = $mail->send();
            $mail->close();
        } catch (Exception $e) {
            $result = false;
        }
    } 
    else {
        $result = false;
    }
    
    return $result;
}

function sqlesc($value, bool $force = false): string 
{
    global $mysql_link;
    
    // Если значение null, возвращаем NULL без кавычек
    if ($value === null) {
        return 'NULL';
    }
    
    // Если значение - булево, преобразуем в число
    if (is_bool($value)) {
        $value = (int)$value;
    }
    
    // Проверяем, является ли значение числовым
    $is_numeric = is_numeric($value) && !$force;
    
    // Для чисел не используем кавычки (если не принудительно)
    if ($is_numeric) {
        // Проверяем, что это действительно число, а не строка, начинающаяся с нуля
        if (is_string($value) && $value[0] === '0' && strlen($value) > 1) {
            // Строки, начинающиеся с нуля (например, '0123') обрабатываем как строки
            $is_numeric = false;
        } else {
            return (string)$value;
        }
    }
    
    // Экранируем строку
    $escaped = mysqli_real_escape_string($mysql_link, (string)$value);
    
    // Обрамляем кавычками
    return "'" . $escaped . "'";
}

function sqlwildcardesc($x) {
    global $mysql_link; // или как называется ваше подключение к БД
    
    if (!isset($mysql_link)) {
        return $x; // или выбросить исключение
    }
    
    // Экранируем базовые опасные символы для SQL
    $escaped = mysqli_real_escape_string($mysql_link, $x);
    
    // Экранируем специальные символы для оператора LIKE: % и _
    $escaped = str_replace(array("%", "_"), array("\\%", "\\_"), $escaped);
    
    return $escaped;
}

function urlparse($m) {
	$t = $m[0];
	if (preg_match(',^\w+://,', $t))
		return "<a href=\"$t\">$t</a>";
	return "<a href=\"http://$t\">$t</a>";
}

function parsedescr($d, $html) {
	if (!$html) {
	  $d = htmlspecialchars_uni($d);
	  $d = str_replace("\n", "\n<br>", $d);
	}
	return $d;
}

function stdhead($title = "", $msgalert = true) {
    global $CURUSER, $SITE_ONLINE, $FUNDS, $SITENAME, $DEFAULTBASEURL, $ss_uri, $tracker_lang, $default_theme, $keywords, $description, $pic_base_url;

    if (!$SITE_ONLINE)
        die('Site is down for maintenance, please check back again later... thanks<br />');

    header('Content-Type: text/html; charset=' . $tracker_lang['language_charset']);
    header('X-Powered-by: TBDev nickmsk9 Edition - https://github.com/nickmsk9/tbdev.net');
    header('X-Chocolate-to: github nickmsk9');
    header('Cache-Control: no-cache');
    header('Pragma: no-cache');
    
    // Формируем заголовок страницы для тега <title>
    if ($title == '') {
        $title = $SITENAME . (isset($_GET['yuna']) ? ' ('.TBVERSION.')' : '');
    } else {
        $title = $SITENAME . (isset($_GET['yuna']) ? ' ('.TBVERSION.')' : ''). ' :: ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    }

    $ss_uri = select_theme();

    $unread = 0;
    if ($msgalert && $CURUSER) {
        $user_id = (int)$CURUSER['id'];
        $res = sql_query('SELECT COUNT(*) FROM messages WHERE receiver = ' . $user_id . ' AND unread="yes"') or die('OopppsY!');
        $arr = mysqli_fetch_row($res);
        if ($arr) {
            $unread = (int)$arr[0];
        }
    }

    // Передаем переменные в шаблон
    $GLOBALS['title'] = $title;  // Для тега <title>
    $GLOBALS['unread'] = $unread;
    $GLOBALS['SITENAME'] = $SITENAME; // На всякий случай
    
    // Подключаем файлы темы
    require_once('themes/' . $ss_uri . '/template.php');
    require_once('themes/' . $ss_uri . '/stdhead.php');

} // stdhead

function stdfoot() {
	global $CURUSER, $ss_uri, $tracker_lang, $queries, $tstart, $query_stat, $querytime;

	if (!is_theme($ss_uri) || empty($ss_uri))
		$ss_uri = select_theme();

	require_once('themes/' . $ss_uri . '/template.php');
	require_once('themes/' . $ss_uri . '/stdfoot.php');
	if ((DEBUG_MODE || isset($_GET['yuna'])) && count($query_stat)) {
		foreach ($query_stat as $key => $value) {
			print('<div>['.($key+1).'] => <b>'.($value['seconds'] > 0.01 ? '<font color="red" title="������������� �������������� ������. ����� ���������� ��������� �����.">'.$value['seconds'].'</font>' : '<font color="green" title="������ �� ��������� � �����������. ����� ���������� ����������.">'.$value['seconds'].'</font>' ).'</b> ['.htmlspecialchars_uni($value['query']).']</div>'."\n");
		}
		print('<br />');
	}
}

function genbark($x,$y) {
	stdhead($y);
	print('<h2>' . htmlspecialchars_uni($y) . '</h2>');
	print('<p>' . htmlspecialchars_uni($x) . '</p>');
	stdfoot();
	exit();
}

function mksecret($length = 20) {
    $set = array('a','A','b','B','c','C','d','D','e','E','f','F','g','G','h','H','i','I','j','J','k','K','l','L','m','M','n','N','o','O','p','P','q','Q','r','R','s','S','t','T','u','U','v','V','w','W','x','X','y','Y','z','Z','1','2','3','4','5','6','7','8','9');
    $str = ''; // Инициализируем переменную пустой строкой
    
    for($i = 1; $i <= $length; $i++) {
        $ch = rand(0, count($set)-1);
        $str .= $set[$ch];
    }
    return $str;
}

function httperr($code = 404) {
	$sapi_name = php_sapi_name();
	if ($sapi_name == 'cgi' OR $sapi_name == 'cgi-fcgi') {
		header('Status: 404 Not Found');
	} else {
		header('HTTP/1.1 404 Not Found');
	}
	exit;
}

function gmtime() {
	return strtotime(get_date_time());
}

function logincookie($id, $passhash, $updatedb = 1, $expires = 0x7fffffff) {

	$subnet = explode('.', getip());
	$subnet[2] = $subnet[3] = 0;
	$subnet = implode('.', $subnet); // 255.255.0.0

	setcookie(COOKIE_UID, $id, $expires, '/');
	setcookie(COOKIE_PASSHASH, md5($passhash.COOKIE_SALT.$subnet), $expires, '/');

	if ($updatedb)
		sql_query('UPDATE users SET last_login = NOW() WHERE id = '.$id);
}

function logoutcookie() {
//	setcookie(COOKIE_UID, '', 0x7fffffff, '/'); // �� ����� ������� ��������������� �.� ������� �������� ������� ����-������� ����
	setcookie(COOKIE_PASSHASH, '', 0x7fffffff, '/');
}

function loggedinorreturn($nowarn = false) {
	global $CURUSER, $DEFAULTBASEURL;
	if (!$CURUSER) {
		header('Location: '.$DEFAULTBASEURL.'/login.php?returnto=' . urlencode(basename($_SERVER['REQUEST_URI'])).($nowarn ? '&nowarn=1' : ''));
		exit();
	}
}

function deletetorrent($id) {
	global $torrent_dir;
	$images = mysqli_fetch_assoc(sql_query('SELECT image1, image2, image3, image4, image5 FROM torrents WHERE id = '.$id));
	if ($images) { for ($x=1; $x <= 5; $x++) {
			if ($images['image' . $x] != '' && file_exists('torrents/images/' . $images['image' . $x]))
				unlink('torrents/images/' . $images['image' . $x]);
		}
	}
	sql_query('DELETE FROM torrents WHERE id = '.$id);
	sql_query('DELETE FROM snatched WHERE torrent = '.$id);
	sql_query('DELETE FROM bookmarks WHERE torrentid = '.$id);
	sql_query('DELETE FROM readtorrents WHERE torrentid = '.$id);
	foreach(explode('.','peers.files.comments.ratings') as $x)
		sql_query('DELETE FROM '.$x.' WHERE torrent = '.$id);
	sql_query('DELETE FROM torrents_scrape WHERE tid = '.$id);
	sql_query('DELETE FROM torrents_descr WHERE tid = '.$id);
	unlink($torrent_dir.'/'.$id.'.torrent');
}

function pager($rpp, $count, $href, $opts = array())
{
    $rpp   = (int)$rpp;
    $count = (int)$count;

    if ($rpp <= 0) $rpp = 1;
    if ($count < 0) $count = 0;

    // Если нечего листать — не показываем "Страницы:" вообще
    if ($count === 0) {
        return array('', '', "LIMIT 0,$rpp");
    }

    $pages = (int)ceil($count / $rpp);

    // Если всего одна страница — тоже не показываем пагинацию
    if ($pages <= 1) {
        return array('', '', "LIMIT 0,$rpp");
    }

    // page default
    if (!empty($opts['lastpagedefault'])) {
        $pagedefault = (int)floor(($count - 1) / $rpp);
        if ($pagedefault < 0) $pagedefault = 0;
    } else {
        $pagedefault = 0;
    }

    // current page
    $page = $pagedefault;
    if (isset($_GET['page'])) {
        $p = (int)$_GET['page'];
        $page = ($p >= 0) ? $p : $pagedefault;
    }

    $mp = $pages - 1;
    if ($page > $mp) $page = $mp;
    if ($page < 0)   $page = 0;

    // UI pieces (same structure/classes)
    $cellSpacer = '<td class="pagebr">&nbsp;</td>';
    $pagerLeft  = '<td class="pager">Страницы:</td>' . $cellSpacer;
    $pagerRight = '';

    // prev / next
    if ($page >= 1) {
        $pagerLeft .= '<td class="pager">'
            . '<a href="' . $href . 'page=' . ($page - 1) . '" style="text-decoration: none;"><b>«</b></a>'
            . '</td>' . $cellSpacer;
    }

    if ($page < $mp) {
        $pagerRight .= '<td class="pager">'
            . '<a href="' . $href . 'page=' . ($page + 1) . '" style="text-decoration: none;"><b>»</b></a>'
            . '</td>';
    }

    // Build list of page indexes to show (fast, no full loop)
    $dotspace = 3;
    $show = array();

    // first 3
    for ($i = 0; $i < 3 && $i < $pages; $i++) $show[$i] = true;

    // around current
    $from = max(0, $page - $dotspace);
    $to   = min($mp, $page + $dotspace);
    for ($i = $from; $i <= $to; $i++) $show[$i] = true;

    // last 3
    for ($i = max(0, $pages - 3); $i < $pages; $i++) $show[$i] = true;

    $idx = array_keys($show);
    sort($idx, SORT_NUMERIC);

    $pagerarr = array();
    $prev = null;

    foreach ($idx as $i) {
        if ($prev !== null && $i > $prev + 1) {
            $pagerarr[] = '<td class="pager">...</td>' . $cellSpacer;
        }

        $startRange = $i * $rpp + 1;
        $endRange   = min($count, $startRange + $rpp - 1);
        $text       = (string)($i + 1);

        if ($i !== $page) {
            $pagerarr[] =
                '<td class="pager">'
                . '<a title="' . $startRange . '&nbsp;-&nbsp;' . $endRange . '" href="' . $href . 'page=' . $i . '" style="text-decoration: none;"><b>' . $text . '</b></a>'
                . '</td>' . $cellSpacer;
        } else {
            $pagerarr[] = '<td class="highlight"><b>' . $text . '</b></td>' . $cellSpacer;
        }

        $prev = $i;
    }

    $pagerstr = implode('', $pagerarr);

    $pagertop =
        '<table class="main"><tr>'
        . $pagerLeft . $pagerstr . $pagerRight
        . '</tr></table>' . "\n";

    $pagerbottom =
        'Всего ' . $count . ' в ' . $pages . ' ' . ($pages === 1 ? 'странице' : 'страницах')
        . ' по ' . $rpp . ' ' . ($rpp === 1 ? 'записи' : 'записей')
        . ' на каждой странице.<br /><br />'
        . '<table class="main"><tr>'
        . $pagerLeft . $pagerstr . $pagerRight
        . '</tr></table>' . "\n";

    $start = $page * $rpp;
    if ($start < 0) $start = 0;

    return array($pagertop, $pagerbottom, "LIMIT $start,$rpp");
}

function downloaderdata($res) {
    $rows = array();
    $ids = array();
    $peerdata = array();
    
    // Заменяем mysqli_fetch_assoc на mysqli_fetch_assoc
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
        $id = $row["id"];
        $ids[] = $id;
        // Исправляем синтаксис массива - строковые ключи должны быть в кавычках
        $peerdata[$id] = array('downloaders' => 0, 'seeders' => 0, 'comments' => 0);
    }

    if (count($ids)) {
        $allids = implode(",", $ids);
        $res = sql_query("SELECT COUNT(*) AS c, torrent, seeder FROM peers WHERE torrent IN ($allids) GROUP BY torrent, seeder");
        while ($row = mysqli_fetch_assoc($res)) {
            // Исправляем ключи массива - должны быть в кавычках
            if ($row["seeder"] == "yes")
                $key = "seeders";
            else
                $key = "downloaders";
            $peerdata[$row["torrent"]][$key] = $row["c"];
        }
        
        $res = sql_query("SELECT COUNT(*) AS c, torrent FROM comments WHERE torrent IN ($allids) GROUP BY torrent");
        while ($row = mysqli_fetch_assoc($res)) {
            $peerdata[$row["torrent"]]["comments"] = $row["c"];
        }
    }

    return array($rows, $peerdata);
}
function genrelist() {
	$ret = array();
	$res = sql_query('SELECT id, name FROM categories ORDER BY sort ASC');
	while ($row = mysqli_fetch_assoc($res))
		$ret[] = $row;
	return $ret;
}

function linkcolor($num) {
	if (!$num)
		return 'red';
//	if ($num == 1)
//		return 'yellow';
	return 'green';
}

function ratingpic($num) {
	global $pic_base_url, $tracker_lang, $ss_uri;
	$r = round($num);
	if ($r < 1 || $r > 5)
		return;
	return "<img src=\"themes/$ss_uri/images/rating/$r.gif\" border=\"0\" alt=\"".$tracker_lang['rating'].": $num / 5\" />";
}

function writecomment($userid, $comment) {
    $userid = intval($userid);
    if (!$userid)
        throw new Exception('User ID cannot be 0 or null');
    
    // Очищаем и экранируем комментарий
    $clean_comment = mysqli_real_escape_string($GLOBALS['mysql_link'], $comment);
    $date = date('d-m-Y');
    $modcomment = $date . ' - ' . $clean_comment;
    
    // Используем CONCAT_WS для добавления нового комментария в начало
    $query = "UPDATE users SET modcomment = CONCAT_WS('\n', '" . mysqli_real_escape_string($GLOBALS['mysql_link'], $modcomment) . "', modcomment) WHERE id = $userid";
    
    return sql_query($query) or sqlerr(__FILE__, __LINE__);
}

function hash_pad($hash) {
	return str_pad($hash, 20);
}

function get_user_icons($arr, $big = false) {
		if ($big) {
				$donorpic = "starbig.gif";
				$warnedpic = "warnedbig.gif";
				$disabledpic = "disabledbig.gif";
				$style = "style='margin-left: 4pt'";
		} else {
				$donorpic = "star.gif";
				$warnedpic = "warned.gif";
				$disabledpic = "disabled.gif";
				$parkedpic = "parked.gif";
				$style = "style=\"margin-left: 2pt\"";
		}
		$pics = $arr["donor"] == "yes" ? "<img src=\"pic/$donorpic\" alt='Donor' border=\"0\" $style>" : "";
		if ($arr["enabled"] == "yes")
				$pics .= $arr["warned"] == "yes" ? "<img src=pic/$warnedpic alt=\"Warned\" border=0 $style>" : "";
		else
				$pics .= "<img src=\"pic/$disabledpic\" alt=\"Disabled\" border=\"0\" $style>\n";
		$pics .= $arr["parked"] == "yes" ? "<img src=pic/$parkedpic alt=\"Parked\" border=\"0\" $style>" : "";
		return $pics;
}

function parked() {
    global $CURUSER, $tracker_lang;
    
    // Проверяем, что $CURUSER существует и является массивом
    if (!isset($CURUSER) || !is_array($CURUSER)) {
        return; // Выходим, если пользователь не авторизован
    }
    
    // Проверяем, существует ли ключ 'parked' в массиве
    if (isset($CURUSER['parked']) && $CURUSER['parked'] == 'yes') {
        stderr($tracker_lang['error'] ?? 'Ошибка', 'Ваш аккаунт приостановлен.');
    }
}

function magnet(bool $html, string $info_hash, string $name, int $size, array $announces = []): string
{
    $ampersand = $html ? '&amp;' : '&';
    
    // Кодируем имя для URL
    $encoded_name = urlencode($name);
    
    // Формируем часть с трекерами
    $trackers_part = '';
    if (!empty($announces)) {
        foreach ($announces as $tracker) {
            if (!empty($tracker)) {
                $trackers_part .= $ampersand . 'tr=' . urlencode($tracker);
            }
        }
    }
    
    // Основная часть magnet-ссылки
    $magnet = sprintf(
        'magnet:?xt=urn:btih:%s%sdn=%s%sxl=%d',
        $info_hash,
        $ampersand,
        $encoded_name,
        $ampersand,
        $size
    );
    
    // Добавляем трекеры, если есть
    if ($trackers_part !== '') {
        $magnet .= $trackers_part;
    }
    
    return $magnet;
}

// � ���� ������ ����� ��������. ��� ��� �������� ������ ����������� ������� �������� ;) � ������ ������ - ������ ������� ���� �� �� ������� ������������ ������.
define ('VERSION', '');
define ('NUM_VERSION', '2.1.18');
define ('TBVERSION', 'Powered by <a href="http://www.tbdev.net" target="_blank" style="cursor: help;" title="���������� OpenSource ����" class="copyright">TBDev</a> v'.NUM_VERSION.' <a href="http://bit-torrent.kiev.ua" target="_blank" style="cursor: help;" title="���� ������������ ������" class="copyright">Yuna Scatari Edition</a> '.VERSION.' Copyright &copy; 2001-'.date('Y'));

function mysql_modified_rows(): int 
{
    global $mysql_link;
    
    // Получаем информацию о последнем запросе
    $info_str = mysqli_info($mysql_link);
    $affected_rows = mysqli_affected_rows($mysql_link);
    
    // Если нет информации или строка пуста
    if (empty($info_str)) {
        return $affected_rows > 0 ? $affected_rows : 0;
    }
    
    // Ищем количество совпавших строк в информации
    preg_match("/Rows matched: (\d+)/", $info_str, $matches);
    
    if (isset($matches[1])) {
        $matched_rows = (int)$matches[1];
        
        // Возвращаем количество совпавших строк, если affected_rows меньше 1
        if ($affected_rows < 1) {
            return $matched_rows;
        }
    }
    
    // В остальных случаях возвращаем количество фактически измененных строк
    return $affected_rows > 0 ? $affected_rows : 0;
}

?>