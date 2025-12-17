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


declare(strict_types=1);

if (!defined('IN_TRACKER')) {
    die('Попытка взлома!');
}

/**
 * ВАЖНО:
 * deadtime() УЖЕ объявлена в include/functions.php (строка ~877).
 * В cleanup.php НЕЛЬЗЯ объявлять deadtime() повторно.
 */

if (!function_exists('docleanup')) {

    function docleanup(): void
    {
        global $torrent_dir, $signup_timeout, $max_dead_torrent_time, $use_ttl;
        global $points_per_cleanup, $ttl_days, $tracker_lang;

        @set_time_limit(3600);
        @ignore_user_abort(true);

        // НИКАКИХ "START TRANSACTION" на всю уборку:
        // тут и файлы, и много таблиц — можно повесить локи надолго.

        try {
            cleanup_torrent_files((string)$torrent_dir);
            cleanup_inactive_peers((int)$max_dead_torrent_time);

            update_torrent_statistics();

            cleanup_inactive_users((int)$signup_timeout);

            update_bonus_points((int)$points_per_cleanup);

            cleanup_expired_warnings((array)$tracker_lang);
            cleanup_expired_bans();

            auto_promote_demote_users((array)$tracker_lang);

            if (!empty($use_ttl)) {
                cleanup_old_torrents((string)$torrent_dir, (int)$ttl_days);
            }

            cleanup_temp_data();

            write_log("Очистка системы успешно завершена", "system", "cleanup");
        } catch (Throwable $e) {
            write_log("Ошибка при очистке: " . $e->getMessage(), "system", "cleanup_error");
            // Если хочешь, можешь не бросать дальше:
            // return;
            throw $e;
        }
    }

    function cleanup_torrent_files(string $torrent_dir): void
    {
        $torrents_db = [];
        $res = sql_query("SELECT id FROM torrents") or sqlerr(__FILE__, __LINE__);
        while ($row = mysqli_fetch_assoc($res)) {
            $torrents_db[(int)$row['id']] = true;
        }
        if (!$torrents_db) {
            return;
        }

        $torrents_files = [];
        $dp = @opendir($torrent_dir);
        if (!$dp) {
            return;
        }

        while (($file = readdir($dp)) !== false) {
            if (!preg_match('/^(\d+)\.torrent$/', $file, $m)) {
                continue;
            }

            $id = (int)$m[1];
            $torrents_files[$id] = true;

            if (!isset($torrents_db[$id])) {
                @unlink($torrent_dir . '/' . $file);
            }
        }
        closedir($dp);

        // если в БД есть торрент, а файла нет — чистим записи
        $delete_ids = array_keys(array_diff_key($torrents_db, $torrents_files));
        if ($delete_ids) {
            $ids_str = implode(',', array_map('intval', $delete_ids));

            sql_query("DELETE FROM peers WHERE torrent IN ($ids_str)") or sqlerr(__FILE__, __LINE__);
            sql_query("DELETE FROM files WHERE torrent IN ($ids_str)") or sqlerr(__FILE__, __LINE__);
            sql_query("DELETE FROM torrents WHERE id IN ($ids_str)") or sqlerr(__FILE__, __LINE__);
        }
    }

    function cleanup_inactive_peers(int $max_dead_torrent_time): void
    {
        // ВАЖНО: deadtime() берём из include/functions.php
        $dead = (int)deadtime();

        // peers.last_action в TBDev часто хранится как INT timestamp.
        // Если у тебя DATETIME — смотри ниже альтернативу.
        sql_query("DELETE FROM peers WHERE last_action < $dead") or sqlerr(__FILE__, __LINE__);

        // snatched.last_action тоже обычно INT
        sql_query("UPDATE snatched SET seeder = 'no'
                   WHERE seeder = 'yes' AND last_action < $dead") or sqlerr(__FILE__, __LINE__);

        $dead_torrent = $dead - $max_dead_torrent_time;

        // torrents.last_action в старых сборках тоже INT
        sql_query("UPDATE torrents SET visible = 'no'
                   WHERE visible = 'yes'
                     AND last_action < $dead_torrent
                     AND multitracker = 'no'") or sqlerr(__FILE__, __LINE__);

        /*
        // ЕСЛИ last_action у тебя DATETIME, то используй ВМЕСТО запросов выше:
        // sql_query("DELETE FROM peers WHERE last_action < FROM_UNIXTIME($dead)")...
        */
    }

    function update_torrent_statistics(): void
    {
        $stats = [];

        $res = sql_query("SELECT torrent, seeder, COUNT(*) AS c
                          FROM peers
                          GROUP BY torrent, seeder") or sqlerr(__FILE__, __LINE__);
        while ($row = mysqli_fetch_assoc($res)) {
            $tid = (int)$row['torrent'];
            $key = ($row['seeder'] === 'yes') ? 'seeders' : 'leechers';
            $stats[$tid][$key] = (int)$row['c'];
        }

        $res = sql_query("SELECT torrent, COUNT(*) AS c
                          FROM comments
                          GROUP BY torrent") or sqlerr(__FILE__, __LINE__);
        while ($row = mysqli_fetch_assoc($res)) {
            $stats[(int)$row['torrent']]['comments'] = (int)$row['c'];
        }

        $res = sql_query("SELECT id, seeders, leechers, comments FROM torrents") or sqlerr(__FILE__, __LINE__);
        while ($row = mysqli_fetch_assoc($res)) {
            $tid = (int)$row['id'];

            $newSeed = (int)($stats[$tid]['seeders'] ?? 0);
            $newLeech = (int)($stats[$tid]['leechers'] ?? 0);
            $newComm = (int)($stats[$tid]['comments'] ?? 0);

            if ($newSeed !== (int)$row['seeders'] || $newLeech !== (int)$row['leechers'] || $newComm !== (int)$row['comments']) {
                sql_query("UPDATE torrents
                           SET seeders = $newSeed, leechers = $newLeech, comments = $newComm
                           WHERE id = $tid") or sqlerr(__FILE__, __LINE__);
            }
        }
    }

    function cleanup_inactive_users(int $signup_timeout): void
    {
        $user_tables = [
            'messages'     => ['receiver', 'sender'],
            'friends'      => ['userid', 'friendid'],
            'blocks'       => ['userid', 'blockid'],
            'bookmarks'    => ['userid'],
            'invites'      => ['inviter'],
            'peers'        => ['userid'],
            'readtorrents' => ['userid'],
            'simpaty'      => ['fromuserid'],
            'checkcomm'    => ['userid'],
        ];

        // 1) pending + старые
        $dead = TIMENOW - $signup_timeout;

        $res = sql_query("SELECT id FROM users
                          WHERE status = 'pending'
                            AND added < FROM_UNIXTIME($dead)
                            AND last_login < FROM_UNIXTIME($dead)
                            AND last_access < FROM_UNIXTIME($dead)") or sqlerr(__FILE__, __LINE__);
        while ($row = mysqli_fetch_assoc($res)) {
            delete_user_data((int)$row['id'], $user_tables);
        }

        // 2) обычные 31 день
        $max_class = (int)UC_POWER_USER;
        $dt = sqlesc(get_date_time(gmtime() - (31 * 86400)));

        $res = sql_query("SELECT id FROM users
                          WHERE parked = 'no'
                            AND status = 'confirmed'
                            AND class <= $max_class
                            AND last_access < $dt
                            AND last_access != '0000-00-00 00:00:00'") or sqlerr(__FILE__, __LINE__);
        while ($row = mysqli_fetch_assoc($res)) {
            delete_user_data((int)$row['id'], $user_tables);
        }

        // 3) parked 175 дней
        $dtp = sqlesc(get_date_time(gmtime() - (175 * 86400)));

        $res = sql_query("SELECT id FROM users
                          WHERE parked = 'yes'
                            AND status = 'confirmed'
                            AND class <= $max_class
                            AND last_access < $dtp") or sqlerr(__FILE__, __LINE__);
        while ($row = mysqli_fetch_assoc($res)) {
            delete_user_data((int)$row['id'], $user_tables);
        }
    }

    function delete_user_data(int $user_id, array $tables): void
    {
        $uid = (int)$user_id;

        sql_query("DELETE FROM users WHERE id = $uid") or sqlerr(__FILE__, __LINE__);

        foreach ($tables as $table => $columns) {
            foreach ((array)$columns as $column) {
                // table/column тут из белого списка выше
                sql_query("DELETE FROM $table WHERE $column = $uid") or sqlerr(__FILE__, __LINE__);
            }
        }

        write_log("Удален неактивный пользователь ID: $uid", "system", "user_cleanup");
    }

    function update_bonus_points(int $points_per_cleanup): void
    {
        $p = (int)$points_per_cleanup;

        // Чтобы не словить "You can't specify target table ..." — делаем через EXISTS
        sql_query("UPDATE users u
                   SET u.bonus = u.bonus + $p
                   WHERE EXISTS (
                       SELECT 1 FROM peers p
                       WHERE p.userid = u.id AND p.seeder = 'yes'
                   )") or sqlerr(__FILE__, __LINE__);
    }

    function cleanup_expired_warnings(array $tracker_lang): void
    {
        $now = sqlesc(get_date_time());
        $modcomment = sqlesc(date("Y-m-d") . " - Автоматическое снятие предупреждения по истечению срока.\n");
        $msg = sqlesc("Ваше предупреждение истекло. Пожалуйста, соблюдайте правила в дальнейшем.\n");

        sql_query("INSERT INTO messages (sender, receiver, added, msg, poster)
                   SELECT 0, id, $now, $msg, 0
                   FROM users
                   WHERE warned = 'yes'
                     AND warneduntil < NOW()
                     AND warneduntil != '0000-00-00 00:00:00'") or sqlerr(__FILE__, __LINE__);

        sql_query("UPDATE users
                   SET warned = 'no',
                       warneduntil = '0000-00-00 00:00:00',
                       modcomment = CONCAT($modcomment, modcomment)
                   WHERE warned = 'yes'
                     AND warneduntil < NOW()
                     AND warneduntil != '0000-00-00 00:00:00'") or sqlerr(__FILE__, __LINE__);
    }

    function cleanup_expired_bans(): void
    {
        $modcomment = sqlesc(date("Y-m-d") . " - Автоматическое снятие бана по истечению срока.\n");

        // Включаем пользователей, у кого бан истёк
        sql_query("UPDATE users u
                   JOIN users_ban b ON b.userid = u.id
                   SET u.enabled = 'yes',
                       u.modcomment = CONCAT($modcomment, u.modcomment)
                   WHERE b.disuntil < NOW()
                     AND b.disuntil != '0000-00-00 00:00:00'") or sqlerr(__FILE__, __LINE__);

        // Чистим записи банов
        sql_query("DELETE FROM users_ban
                   WHERE disuntil < NOW()
                     AND disuntil != '0000-00-00 00:00:00'") or sqlerr(__FILE__, __LINE__);
    }

    function auto_promote_demote_users(array $tracker_lang): void
    {
        $now = sqlesc(get_date_time());

        // PROMOTE -> Power User
        $limit = 25 * 1024 * 1024 * 1024; // 25 GB
        $min_ratio = 1.05;
        $min_age_days = 28;
        $maxdt = sqlesc(get_date_time(gmtime() - (86400 * $min_age_days)));

        $msg = sqlesc("Поздравляем, вы были повышены в классе до [b]Power User[/b].");
        $subject = sqlesc("Вы были повышены");
        $modcomment = sqlesc(date("Y-m-d") . " - Повышен до класса \"" . ($tracker_lang["class_power_user"] ?? "Power User") . "\" автоматически.\n");

        sql_query("INSERT INTO messages (sender, receiver, added, msg, poster, subject)
                   SELECT 0, id, $now, $msg, 0, $subject
                   FROM users
                   WHERE class = " . (int)UC_USER . "
                     AND uploaded >= $limit
                     AND downloaded > 0
                     AND (uploaded / downloaded) >= $min_ratio
                     AND added < $maxdt") or sqlerr(__FILE__, __LINE__);

        sql_query("UPDATE users
                   SET class = " . (int)UC_POWER_USER . ",
                       modcomment = CONCAT($modcomment, modcomment)
                   WHERE class = " . (int)UC_USER . "
                     AND uploaded >= $limit
                     AND downloaded > 0
                     AND (uploaded / downloaded) >= $min_ratio
                     AND added < $maxdt") or sqlerr(__FILE__, __LINE__);

        // DEMOTE -> User
        $demote_ratio = 0.95;

        $demote_msg = sqlesc("Вы были понижены с класса [b]Power User[/b] до [b]User[/b], так как ваш рейтинг упал ниже {$demote_ratio}.");
        $demote_subject = sqlesc("Вы были понижены");
        $demote_modcomment = sqlesc(date("Y-m-d") . " - Понижен до класса \"" . ($tracker_lang["class_user"] ?? "User") . "\" автоматически.\n");

        sql_query("INSERT INTO messages (sender, receiver, added, msg, poster, subject)
                   SELECT 0, id, $now, $demote_msg, 0, $demote_subject
                   FROM users
                   WHERE class = " . (int)UC_POWER_USER . "
                     AND downloaded > 0
                     AND (uploaded / downloaded) < $demote_ratio") or sqlerr(__FILE__, __LINE__);

        sql_query("UPDATE users
                   SET class = " . (int)UC_USER . ",
                       modcomment = CONCAT($demote_modcomment, modcomment)
                   WHERE class = " . (int)UC_POWER_USER . "
                     AND downloaded > 0
                     AND (uploaded / downloaded) < $demote_ratio") or sqlerr(__FILE__, __LINE__);
    }

    function cleanup_old_torrents(string $torrent_dir, int $ttl_days): void
    {
        $dt = sqlesc(get_date_time(gmtime() - ($ttl_days * 86400)));

        $res = sql_query("SELECT id, name, image1, image2, image3, image4, image5
                          FROM torrents
                          WHERE added < $dt") or sqlerr(__FILE__, __LINE__);

        while ($arr = mysqli_fetch_assoc($res)) {
            $tid = (int)$arr['id'];

            @unlink($torrent_dir . '/' . $tid . '.torrent');

            for ($x = 1; $x <= 5; $x++) {
                $img = (string)($arr['image' . $x] ?? '');
                if ($img !== '') {
                    @unlink('torrents/images/' . $img);
                }
            }

            $related = [
                'snatched'  => 'torrent',
                'peers'     => 'torrent',
                'comments'  => 'torrent',
                'files'     => 'torrent',
                'ratings'   => 'torrent',
                'bookmarks' => 'torrentid',
            ];

            foreach ($related as $table => $column) {
                sql_query("DELETE FROM $table WHERE $column = $tid") or sqlerr(__FILE__, __LINE__);
            }

            sql_query("DELETE FROM checkcomm WHERE checkid = $tid AND torrent = 1") or sqlerr(__FILE__, __LINE__);
            sql_query("DELETE FROM torrents WHERE id = $tid") or sqlerr(__FILE__, __LINE__);

            write_log("Торрент $tid (" . ($arr['name'] ?? '') . ") удалён по сроку жизни ($ttl_days дней)", "", "torrent");
        }
    }

    function cleanup_temp_data(): void
    {
        $captcha_time = time() - 86400;
        sql_query("DELETE FROM captcha WHERE dateline < $captcha_time") or sqlerr(__FILE__, __LINE__);

        $session_time = time() - 3600;
        sql_query("DELETE FROM sessions WHERE time < $session_time") or sqlerr(__FILE__, __LINE__);
    }
}
