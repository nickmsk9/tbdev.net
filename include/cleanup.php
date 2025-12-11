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

if(!defined('IN_TRACKER')) {
    die('Попытка взлома!');
}

/**
 * Функция очистки системы
 * Оптимизирована для PHP 8.1+, добавлены комментарии на русском
 */
function docleanup() {
    global $torrent_dir, $signup_timeout, $max_dead_torrent_time, $use_ttl;
    global $autoclean_interval, $points_per_cleanup, $ttl_days, $tracker_lang;
    
    // Устанавливаем параметры выполнения
    @set_time_limit(3600);
    @ignore_user_abort(true);
    
    // Начинаем транзакцию для целостности данных
    sql_query("START TRANSACTION") or sqlerr(__FILE__, __LINE__);
    
    try {
        // 1. ОЧИСТКА ТОРРЕНТ-ФАЙЛОВ
        cleanup_torrent_files($torrent_dir);
        
        // 2. ОЧИСТКА НЕАКТИВНЫХ ПИРОВ
        cleanup_inactive_peers();
        
        // 3. ОБНОВЛЕНИЕ СТАТИСТИКИ ТОРРЕНТОВ
        update_torrent_statistics();
        
        // 4. УДАЛЕНИЕ НЕАКТИВНЫХ ПОЛЬЗОВАТЕЛЕЙ
        cleanup_inactive_users();
        
        // 5. ОБНОВЛЕНИЕ БОНУСНЫХ БАЛЛОВ
        update_bonus_points($points_per_cleanup);
        
        // 6. ОЧИСТКА ИСТЕКШИХ ПРЕДУПРЕЖДЕНИЙ И БАНОВ
        cleanup_expired_warnings();
        cleanup_expired_bans();
        
        // 7. АВТОМАТИЧЕСКОЕ ПОВЫШЕНИЕ/ПОНИЖЕНИЕ КЛАССА
        auto_promote_demote_users($tracker_lang);
        
        // 8. УДАЛЕНИЕ СТАРЫХ ТОРРЕНТОВ ПО TTL
        if ($use_ttl) {
            cleanup_old_torrents($torrent_dir, $ttl_days);
        }
        
        // 9. ОЧИСТКА ВРЕМЕННЫХ ДАННЫХ
        cleanup_temp_data();
        
        // Фиксируем изменения
        sql_query("COMMIT") or sqlerr(__FILE__, __LINE__);
        
        write_log("Очистка системы успешно завершена", "system", "cleanup");
        
    } catch (Exception $e) {
        // Откатываем изменения при ошибке
        sql_query("ROLLBACK") or sqlerr(__FILE__, __LINE__);
        write_log("Ошибка при очистке: " . $e->getMessage(), "system", "cleanup_error");
        throw $e;
    }
}

/**
 * Очистка торрент-файлов
 */
function cleanup_torrent_files($torrent_dir) {
    // Получаем все ID торрентов из БД
    $torrents_db = [];
    $res = sql_query("SELECT id FROM torrents") or sqlerr(__FILE__, __LINE__);
    while ($row = mysqli_fetch_assoc($res)) {
        $torrents_db[(int)$row['id']] = true;
    }
    
    if (empty($torrents_db)) return;
    
    // Получаем список файлов в директории
    $torrents_files = [];
    $dp = @opendir($torrent_dir);
    if (!$dp) return;
    
    while (($file = readdir($dp)) !== false) {
        if (!preg_match('/^(\d+)\.torrent$/', $file, $m)) continue;
        
        $id = (int)$m[1];
        $torrents_files[$id] = true;
        
        // Удаляем файлы, которых нет в БД
        if (!isset($torrents_db[$id])) {
            $filepath = $torrent_dir . '/' . $file;
            @unlink($filepath);
        }
    }
    closedir($dp);
    
    if (empty($torrents_files)) return;
    
    // Удаляем записи торрентов, которых нет в файлах
    $delete_ids = array_keys(array_diff_key($torrents_db, $torrents_files));
    if (!empty($delete_ids)) {
        $ids_str = implode(',', array_map('intval', $delete_ids));
        
        // Удаляем связанные записи
        sql_query("DELETE FROM peers WHERE torrent IN ($ids_str)") or sqlerr(__FILE__, __LINE__);
        sql_query("DELETE FROM files WHERE torrent IN ($ids_str)") or sqlerr(__FILE__, __LINE__);
        sql_query("DELETE FROM torrents WHERE id IN ($ids_str)") or sqlerr(__FILE__, __LINE__);
    }
}

/**
 * Очистка неактивных пиров
 */
function cleanup_inactive_peers() {
    global $max_dead_torrent_time;
    
    $deadtime = deadtime();
    
    // Удаляем неактивных пиров
    sql_query("DELETE FROM peers WHERE last_action < FROM_UNIXTIME($deadtime)") or sqlerr(__FILE__, __LINE__);
    
    // Обновляем статус сидеров в истории скачиваний
    sql_query("UPDATE snatched SET seeder = 'no' WHERE seeder = 'yes' AND last_action < FROM_UNIXTIME($deadtime)") or sqlerr(__FILE__, __LINE__);
    
    // Скрываем старые торренты
    $deadtime_torrent = $deadtime - $max_dead_torrent_time;
    sql_query("UPDATE torrents SET visible = 'no' WHERE visible = 'yes' 
               AND last_action < FROM_UNIXTIME($deadtime_torrent) 
               AND multitracker = 'no'") or sqlerr(__FILE__, __LINE__);
}

/**
 * Обновление статистики торрентов
 */
function update_torrent_statistics() {
    $torrents_stats = [];
    
    // Получаем статистику по пирам
    $res = sql_query("SELECT torrent, seeder, COUNT(*) as c FROM peers GROUP BY torrent, seeder") or sqlerr(__FILE__, __LINE__);
    while ($row = mysqli_fetch_assoc($res)) {
        $torrent_id = (int)$row['torrent'];
        $key = ($row['seeder'] == 'yes') ? 'seeders' : 'leechers';
        $torrents_stats[$torrent_id][$key] = (int)$row['c'];
    }
    
    // Получаем количество комментариев
    $res = sql_query("SELECT torrent, COUNT(*) as c FROM comments GROUP BY torrent") or sqlerr(__FILE__, __LINE__);
    while ($row = mysqli_fetch_assoc($res)) {
        $torrents_stats[(int)$row['torrent']]['comments'] = (int)$row['c'];
    }
    
    // Обновляем статистику в таблице торрентов
    $fields = ['comments', 'leechers', 'seeders'];
    $res = sql_query("SELECT id, seeders, leechers, comments FROM torrents") or sqlerr(__FILE__, __LINE__);
    
    while ($row = mysqli_fetch_assoc($res)) {
        $torrent_id = (int)$row['id'];
        $stats = $torrents_stats[$torrent_id] ?? [];
        
        $updates = [];
        foreach ($fields as $field) {
            $current_value = (int)$row[$field];
            $new_value = (int)($stats[$field] ?? 0);
            
            if ($current_value != $new_value) {
                $updates[] = "$field = $new_value";
            }
        }
        
        if (!empty($updates)) {
            sql_query("UPDATE torrents SET " . implode(', ', $updates) . " WHERE id = $torrent_id") or sqlerr(__FILE__, __LINE__);
        }
    }
}

/**
 * Удаление неактивных пользователей
 */
function cleanup_inactive_users() {
    global $signup_timeout;
    
    // ID таблиц, связанных с пользователями
    $user_tables = [
        'messages' => ['receiver', 'sender'],
        'friends' => ['userid', 'friendid'],
        'blocks' => ['userid', 'blockid'],
        'bookmarks' => ['userid'],
        'invites' => ['inviter'],
        'peers' => ['userid'],
        'readtorrents' => ['userid'],
        'simpaty' => ['fromuserid'],
        'checkcomm' => ['userid']
    ];
    
    // 1. Удаляем неподтвержденных пользователей
    $deadtime = TIMENOW - $signup_timeout;
    $res = sql_query("SELECT id FROM users WHERE status = 'pending' 
                      AND added < FROM_UNIXTIME($deadtime) 
                      AND last_login < FROM_UNIXTIME($deadtime) 
                      AND last_access < FROM_UNIXTIME($deadtime)") or sqlerr(__FILE__, __LINE__);
    
    while ($row = mysqli_fetch_assoc($res)) {
        delete_user_data((int)$row['id'], $user_tables);
    }
    
    // 2. Удаляем неактивных обычных пользователей (31 день)
    $max_class = UC_POWER_USER;
    $inactive_days = 31;
    $dt = sqlesc(get_date_time(gmtime() - ($inactive_days * 86400)));
    
    $res = sql_query("SELECT id FROM users WHERE parked = 'no' 
                      AND status = 'confirmed' 
                      AND class <= $max_class 
                      AND last_access < $dt 
                      AND last_access != '0000-00-00 00:00:00'") or sqlerr(__FILE__, __LINE__);
    
    while ($row = mysqli_fetch_assoc($res)) {
        delete_user_data((int)$row['id'], $user_tables);
    }
    
    // 3. Удаляем неактивных запаркованных пользователей (175 дней)
    $parked_days = 175;
    $dt_parked = sqlesc(get_date_time(gmtime() - ($parked_days * 86400)));
    
    $res = sql_query("SELECT id FROM users WHERE parked = 'yes' 
                      AND status = 'confirmed' 
                      AND class <= $max_class 
                      AND last_access < $dt_parked") or sqlerr(__FILE__, __LINE__);
    
    while ($row = mysqli_fetch_assoc($res)) {
        delete_user_data((int)$row['id'], $user_tables);
    }
}

/**
 * Удаление данных пользователя
 */
function delete_user_data($user_id, $tables) {
    // Удаляем из основной таблицы
    sql_query("DELETE FROM users WHERE id = " . sqlesc($user_id)) or sqlerr(__FILE__, __LINE__);
    
    // Удаляем из связанных таблиц
    foreach ($tables as $table => $columns) {
        foreach ((array)$columns as $column) {
            sql_query("DELETE FROM $table WHERE $column = " . sqlesc($user_id)) or sqlerr(__FILE__, __LINE__);
        }
    }
    
    write_log("Удален неактивный пользователь ID: $user_id", "system", "user_cleanup");
}

/**
 * Обновление бонусных баллов
 */
function update_bonus_points($points_per_cleanup) {
    sql_query("UPDATE users SET bonus = bonus + $points_per_cleanup 
               WHERE id IN (SELECT DISTINCT userid FROM peers WHERE seeder = 'yes')") or sqlerr(__FILE__, __LINE__);
}

/**
 * Очистка истекших предупреждений
 */
function cleanup_expired_warnings() {
    global $tracker_lang;
    
    $now = sqlesc(get_date_time());
    $modcomment = sqlesc(date("Y-m-d") . " - Автоматическое снятие предупреждения по истечению срока.\n");
    $msg = sqlesc("Ваше предупреждение истекло. Пожалуйста, соблюдайте правила в дальнейшем.\n");
    
    // Отправляем уведомление
    sql_query("INSERT INTO messages (sender, receiver, added, msg, poster) 
               SELECT 0, id, $now, $msg, 0 
               FROM users 
               WHERE warned = 'yes' 
               AND warneduntil < NOW() 
               AND warneduntil != '0000-00-00 00:00:00'") or sqlerr(__FILE__, __LINE__);
    
    // Снимаем предупреждение
    sql_query("UPDATE users 
               SET warned = 'no', 
                   warneduntil = '0000-00-00 00:00:00', 
                   modcomment = CONCAT($modcomment, modcomment) 
               WHERE warned = 'yes' 
               AND warneduntil < NOW() 
               AND warneduntil != '0000-00-00 00:00:00'") or sqlerr(__FILE__, __LINE__);
}

/**
 * Очистка истекших банов
 */
function cleanup_expired_bans() {
    $modcomment = sqlesc(date("Y-m-d") . " - Автоматическое снятие бана по истечению срока.\n");
    
    sql_query("UPDATE users 
               SET enabled = 'yes', 
                   modcomment = CONCAT($modcomment, modcomment) 
               WHERE id IN (SELECT userid FROM users_ban 
                           WHERE disuntil < NOW() 
                           AND disuntil != '0000-00-00 00:00:00')") or sqlerr(__FILE__, __LINE__);
    
    sql_query("DELETE FROM users_ban 
               WHERE disuntil < NOW() 
               AND disuntil != '0000-00-00 00:00:00'") or sqlerr(__FILE__, __LINE__);
}

/**
 * Автоматическое повышение/понижение класса пользователей
 */
function auto_promote_demote_users($tracker_lang) {
    $now = sqlesc(get_date_time());
    
    // ПОВЫШЕНИЕ до Power User
    $limit = 25 * 1024 * 1024 * 1024; // 25 GB
    $min_ratio = 1.05;
    $min_age_days = 28;
    $maxdt = sqlesc(get_date_time(gmtime() - (86400 * $min_age_days)));
    
    $msg = sqlesc("Поздравляем, вы были повышены в классе до [b]Power User[/b].");
    $subject = sqlesc("Вы были повышены");
    $modcomment = sqlesc(date("Y-m-d") . " - Повышен до класса \"" . $tracker_lang["class_power_user"] . "\" автоматически.\n");
    
    sql_query("INSERT INTO messages (sender, receiver, added, msg, poster, subject) 
               SELECT 0, id, $now, $msg, 0, $subject 
               FROM users 
               WHERE class = " . UC_USER . " 
               AND uploaded >= $limit 
               AND uploaded / downloaded >= $min_ratio 
               AND added < $maxdt") or sqlerr(__FILE__, __LINE__);
    
    sql_query("UPDATE users 
               SET class = " . UC_POWER_USER . ", 
                   modcomment = CONCAT($modcomment, modcomment) 
               WHERE class = " . UC_USER . " 
               AND uploaded >= $limit 
               AND uploaded / downloaded >= $min_ratio 
               AND added < $maxdt") or sqlerr(__FILE__, __LINE__);
    
    // ПОНИЖЕНИЕ с Power User
    $demote_ratio = 0.95;
    $demote_msg = sqlesc("Вы были понижены с класса [b]Power User[/b] до [b]User[/b] так как ваш рейтинг упал ниже [$demote_ratio].");
    $demote_subject = sqlesc("Вы были понижены");
    $demote_modcomment = sqlesc(date("Y-m-d") . " - Понижен до класса \"" . $tracker_lang["class_user"] . "\" автоматически.\n");
    
    sql_query("INSERT INTO messages (sender, receiver, added, msg, poster, subject) 
               SELECT 0, id, $now, $demote_msg, 0, $demote_subject 
               FROM users 
               WHERE class = " . UC_POWER_USER . " 
               AND downloaded > 0 
               AND uploaded / downloaded < $demote_ratio") or sqlerr(__FILE__, __LINE__);
    
    sql_query("UPDATE users 
               SET class = " . UC_USER . ", 
                   modcomment = CONCAT($demote_modcomment, modcomment) 
               WHERE class = " . UC_POWER_USER . " 
               AND downloaded > 0 
               AND uploaded / downloaded < $demote_ratio") or sqlerr(__FILE__, __LINE__);
}

/**
 * Очистка старых торрентов по TTL
 */
function cleanup_old_torrents($torrent_dir, $ttl_days) {
    $dt = sqlesc(get_date_time(gmtime() - ($ttl_days * 86400)));
    
    $res = sql_query("SELECT id, name, image1, image2, image3, image4, image5 
                      FROM torrents 
                      WHERE added < $dt") or sqlerr(__FILE__, __LINE__);
    
    while ($arr = mysqli_fetch_assoc($res)) {
        $torrent_id = (int)$arr['id'];
        
        // Удаляем торрент-файл
        @unlink("$torrent_dir/$torrent_id.torrent");
        
        // Удаляем изображения
        for ($x = 1; $x <= 5; $x++) {
            if (!empty($arr['image' . $x])) {
                @unlink('torrents/images/' . $arr['image' . $x]);
            }
        }
        
        // Удаляем связанные записи
        $related_tables = [
            'snatched' => 'torrent',
            'peers' => 'torrent',
            'comments' => 'torrent',
            'files' => 'torrent',
            'ratings' => 'torrent',
            'bookmarks' => 'torrentid'
        ];
        
        foreach ($related_tables as $table => $column) {
            sql_query("DELETE FROM $table WHERE $column = $torrent_id") or sqlerr(__FILE__, __LINE__);
        }
        
        // Удаляем проверки комментариев
        sql_query("DELETE FROM checkcomm WHERE checkid = $torrent_id AND torrent = 1") or sqlerr(__FILE__, __LINE__);
        
        // Удаляем основной торрент
        sql_query("DELETE FROM torrents WHERE id = $torrent_id") or sqlerr(__FILE__, __LINE__);
        
        write_log("Торрент $torrent_id ({$arr['name']}) удалён по сроку жизни ($ttl_days дней)", "", "torrent");
    }
}

/**
 * Очистка временных данных
 */
function cleanup_temp_data() {
    // Удаляем старые CAPTCHA коды (1 день)
    $captcha_time = time() - (1 * 86400);
    sql_query("DELETE FROM captcha WHERE dateline < $captcha_time") or sqlerr(__FILE__, __LINE__);
    
    // Удаляем старые сессии (1 час)
    $session_time = time() - (1 * 3600);
    sql_query("DELETE FROM sessions WHERE time < $session_time") or sqlerr(__FILE__, __LINE__);
}

/**
 * Функция для получения времени смерти пира
 * (нужно будет определить или предоставить)
 */
function deadtime() {
    // TODO: Определить реализацию этой функции
    // Возвращает timestamp, после которого пир считается мертвым
    return time() - (60 * 60); // Пример: 1 час
}


?>