<?php

if (!defined('BLOCK_FILE')) {
    Header("Location: ../index.php");
    exit;
}

global $tracker_lang, $ss_uri, $maxusers, $pic_base_url;

// Вспомогательная функция для безопасного форматирования
function safe_number_format($value) {
    return number_format($value ?? 0);
}

// Получаем все данные пользователей одним запросом
$users_query = sql_query("
    SELECT 
        COUNT(*) as total,
        COALESCE(SUM(status='pending'), 0) as unverified,
        COALESCE(SUM(gender='1'), 0) as male,
        COALESCE(SUM(gender='2'), 0) as female,
        COALESCE(SUM(warned='yes'), 0) as warned_users,
        COALESCE(SUM(enabled='no'), 0) as disabled,
        COALESCE(SUM(class=" . UC_UPLOADER . "), 0) as uploaders,
        COALESCE(SUM(class=" . UC_VIP . "), 0) as vip
    FROM users
");
$users_stats = mysqli_fetch_assoc($users_query);

// Получаем все данные торрентов одним запросом
$torrents_query = sql_query("
    SELECT 
        COALESCE(COUNT(*), 0) as total_torrents,
        COALESCE(SUM(visible='no'), 0) as dead
    FROM torrents
");
$torrents_stats = mysqli_fetch_assoc($torrents_query);

// Получаем все данные пиров одним запросом
$peers_query = sql_query("
    SELECT 
        COALESCE(SUM(seeder='yes'), 0) as seeders,
        COALESCE(SUM(seeder='no'), 0) as leechers
    FROM peers
");
$peers_stats = mysqli_fetch_assoc($peers_query);

// Внешние сидеры/личеры
$row = mysqli_fetch_row(sql_query('SELECT COALESCE(SUM(seeders), 0), COALESCE(SUM(leechers), 0) FROM torrents_scrape'));
list($external_seeders, $external_leechers) = array_map(function($value) {
    return number_format($value ?? 0);
}, $row);

// Форматируем данные
$registered = safe_number_format($users_stats['total']);
$unverified = safe_number_format($users_stats['unverified']);
$male = safe_number_format($users_stats['male']);
$female = safe_number_format($users_stats['female']);
$torrents = safe_number_format($torrents_stats['total_torrents']);
$dead = safe_number_format($torrents_stats['dead']);
$seeders = (int)$peers_stats['seeders'];
$leechers = (int)$peers_stats['leechers'];
$warned_users = safe_number_format($users_stats['warned_users']);
$disabled = safe_number_format($users_stats['disabled']);
$uploaders = safe_number_format($users_stats['uploaders']);
$vip = safe_number_format($users_stats['vip']);

// Рассчитываем соотношение
$ratio = $leechers == 0 ? 0 : round($seeders / $leechers * 100);
$peers = safe_number_format($seeders + $leechers);
$seeders_formatted = safe_number_format($seeders);
$leechers_formatted = safe_number_format($leechers);

// Генерация HTML
$content .= "<table width=\"100%\" class=\"main\" border=\"0\" cellspacing=\"0\" cellpadding=\"2\"><td align=\"center\">
<table class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">

<table width=\"100%\" class=\"main\" border=\"0\" cellspacing=\"0\" cellpadding=\"10\">
  <tr>
    <td width=\"50%\" align=\"center\" style=\"border: none;\"><table class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">
<tr><td class=\"rowhead\">{$tracker_lang['users_registered']}</td><td align=right><img src=\"$pic_base_url/male.gif\" alt=\"{$tracker_lang['stats_male']}\">$male<img src=\"$pic_base_url/female.gif\" alt=\"{$tracker_lang['stats_female']}\">$female<br />{$tracker_lang['total']}: {$registered}</td></tr>
<tr><td colspan=\"2\" class=\"rowhead\"><table width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\"><tr><td style=\"text-align: right; font-weight: bold; vertical-align: top;\">{$tracker_lang['stats_maxusers']}</td><td align=\"right\">{$maxusers}</td></tr></table></td></tr>
<tr><td class=\"rowhead\">{$tracker_lang['users_unconfirmed']}</td><td align=\"right\">{$unverified}</td></tr>
<tr><td class=\"rowhead\">{$tracker_lang['users_warned']}&nbsp;<img src=\"$pic_base_url/warned.gif\" border=\"0\" align=\"absbottom\"></td><td align=\"right\">{$warned_users}</td></tr>
<tr><td class=\"rowhead\">{$tracker_lang['users_disabled']}&nbsp;<img src=\"$pic_base_url/disabled.gif\" border=\"0\" align=\"absbottom\"></td><td align=\"right\">{$disabled}</td></tr>
<tr><td class=\"rowhead\"><font color=\"orange\">{$tracker_lang['users_uploaders']}</font></td><td align=\"right\">{$uploaders}</td></tr>
<tr><td class=\"rowhead\"><font color=\"#9C2FE0\">{$tracker_lang['users_vips']}</font></td><td align=\"right\">{$vip}</td></tr>

</table></td>
<td width=\"50%\" align=\"center\" style=\"border: none;\"><table class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">
<tr><td class=\"rowhead\">{$tracker_lang['tracker_torrents']}</td><td align=\"right\">{$torrents}</td></tr>
<tr><td class=\"rowhead\">{$tracker_lang['tracker_dead_torrents']}</td><td align=\"right\">{$dead}</td></tr>
<tr><td class=\"rowhead\">{$tracker_lang['tracker_peers']}</td><td align=\"right\">{$peers}</td></tr>";
if (isset($peers)) {
$content .= "<tr><td class=\"rowhead\">{$tracker_lang['tracker_seeders']}&nbsp;&nbsp;<img src=\"./themes/$ss_uri/images/arrowup.gif\" border=\"0\" align=\"absbottom\"></td><td align=\"right\">{$seeders_formatted}</td></tr>
<tr><td class=\"rowhead\">{$tracker_lang['tracker_leechers']}&nbsp;&nbsp;<img src=\"./themes/$ss_uri/images/arrowdown.gif\" border=\"0\" align=\"absbottom\"></td><td align=\"right\">{$leechers_formatted}</td></tr>
<tr><td class=\"rowhead\">{$tracker_lang['tracker_seed_peer']}</td><td align=\"right\">{$ratio}</td></tr>";
}

$content .= "<tr><td class=\"rowhead\">{$tracker_lang['external_seeders']}&nbsp;&nbsp;<img src=\"./themes/$ss_uri/images/arrowup.gif\" border=\"0\" align=\"absbottom\"></td><td align=\"right\">{$external_seeders}</td></tr>
<tr><td class=\"rowhead\">{$tracker_lang['external_leechers']}&nbsp;&nbsp;<img src=\"./themes/$ss_uri/images/arrowdown.gif\" border=\"0\" align=\"absbottom\"></td><td align=\"right\">{$external_leechers}</td></tr>";

$content .= "</table></td>

</table>
</td></tr></table>";

?>