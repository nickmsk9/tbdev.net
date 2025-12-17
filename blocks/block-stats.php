<?php
declare(strict_types=1);

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $tracker_lang, $ss_uri, $maxusers, $pic_base_url;

$blocktitle = 'Статистика трекера';

$nf = static fn($v): string => number_format((int)($v ?? 0));

/**
 * 1 запрос на всё: users + torrents + peers + torrents_scrape
 */
$q = sql_query("
    SELECT
        /* users */
        (SELECT COUNT(*) FROM users) AS users_total,
        (SELECT COUNT(*) FROM users WHERE status='pending') AS users_unverified,
        (SELECT COUNT(*) FROM users WHERE gender='1') AS users_male,
        (SELECT COUNT(*) FROM users WHERE gender='2') AS users_female,
        (SELECT COUNT(*) FROM users WHERE warned='yes') AS users_warned,
        (SELECT COUNT(*) FROM users WHERE enabled='no') AS users_disabled,
        (SELECT COUNT(*) FROM users WHERE class=" . (int)UC_UPLOADER . ") AS users_uploaders,
        (SELECT COUNT(*) FROM users WHERE class=" . (int)UC_VIP . ") AS users_vip,

        /* torrents */
        (SELECT COUNT(*) FROM torrents) AS torrents_total,
        (SELECT COUNT(*) FROM torrents WHERE visible='no') AS torrents_dead,

        /* peers */
        (SELECT COUNT(*) FROM peers WHERE seeder='yes') AS peers_seeders,
        (SELECT COUNT(*) FROM peers WHERE seeder='no') AS peers_leechers,

        /* external scrape */
        (SELECT COALESCE(SUM(seeders),0) FROM torrents_scrape) AS ext_seeders,
        (SELECT COALESCE(SUM(leechers),0) FROM torrents_scrape) AS ext_leechers
");

$st = $q ? mysqli_fetch_assoc($q) : [];
$st = is_array($st) ? $st : [];

/* formatted values */
$registered   = $nf($st['users_total'] ?? 0);
$unverified   = $nf($st['users_unverified'] ?? 0);
$male         = $nf($st['users_male'] ?? 0);
$female       = $nf($st['users_female'] ?? 0);
$warned_users = $nf($st['users_warned'] ?? 0);
$disabled     = $nf($st['users_disabled'] ?? 0);
$uploaders    = $nf($st['users_uploaders'] ?? 0);
$vip          = $nf($st['users_vip'] ?? 0);

$torrents     = $nf($st['torrents_total'] ?? 0);
$dead         = $nf($st['torrents_dead'] ?? 0);

$seeders_i    = (int)($st['peers_seeders'] ?? 0);
$leechers_i   = (int)($st['peers_leechers'] ?? 0);
$peers        = $nf($seeders_i + $leechers_i);
$seeders      = $nf($seeders_i);
$leechers     = $nf($leechers_i);

$ratio        = ($leechers_i > 0) ? (string)round(($seeders_i / $leechers_i) * 100) : '0';

$external_seeders  = $nf($st['ext_seeders'] ?? 0);
$external_leechers = $nf($st['ext_leechers'] ?? 0);

$content = '
<table width="100%" class="main" border="0" cellspacing="0" cellpadding="6">
  <tr>
    <td align="center" style="border:none;">
      <table width="100%" class="main" border="1" cellspacing="0" cellpadding="6">
        <tr>
          <td width="50%" valign="top" style="border:none;">
            <table width="100%" class="main" border="1" cellspacing="0" cellpadding="5">
              <tr>
                <td class="rowhead">' . $tracker_lang['users_registered'] . '</td>
                <td align="right">
                  <img src="' . $pic_base_url . '/male.gif" alt="' . $tracker_lang['stats_male'] . '" /> ' . $male . '
                  &nbsp;
                  <img src="' . $pic_base_url . '/female.gif" alt="' . $tracker_lang['stats_female'] . '" /> ' . $female . '
                  <br />' . $tracker_lang['total'] . ': ' . $registered . '
                </td>
              </tr>
              <tr>
                <td class="rowhead">' . $tracker_lang['stats_maxusers'] . '</td>
                <td align="right">' . $nf($maxusers) . '</td>
              </tr>
              <tr><td class="rowhead">' . $tracker_lang['users_unconfirmed'] . '</td><td align="right">' . $unverified . '</td></tr>
              <tr><td class="rowhead">' . $tracker_lang['users_warned'] . ' <img src="' . $pic_base_url . '/warned.gif" border="0" align="absbottom" /></td><td align="right">' . $warned_users . '</td></tr>
              <tr><td class="rowhead">' . $tracker_lang['users_disabled'] . ' <img src="' . $pic_base_url . '/disabled.gif" border="0" align="absbottom" /></td><td align="right">' . $disabled . '</td></tr>
              <tr><td class="rowhead"><font color="orange">' . $tracker_lang['users_uploaders'] . '</font></td><td align="right">' . $uploaders . '</td></tr>
              <tr><td class="rowhead"><font color="#9C2FE0">' . $tracker_lang['users_vips'] . '</font></td><td align="right">' . $vip . '</td></tr>
            </table>
          </td>

          <td width="50%" valign="top" style="border:none;">
            <table width="100%" class="main" border="1" cellspacing="0" cellpadding="5">
              <tr><td class="rowhead">' . $tracker_lang['tracker_torrents'] . '</td><td align="right">' . $torrents . '</td></tr>
              <tr><td class="rowhead">' . $tracker_lang['tracker_dead_torrents'] . '</td><td align="right">' . $dead . '</td></tr>
              <tr><td class="rowhead">' . $tracker_lang['tracker_peers'] . '</td><td align="right">' . $peers . '</td></tr>
              <tr><td class="rowhead">' . $tracker_lang['tracker_seeders'] . ' <img src="./themes/' . $ss_uri . '/images/arrowup.gif" border="0" align="absbottom" /></td><td align="right">' . $seeders . '</td></tr>
              <tr><td class="rowhead">' . $tracker_lang['tracker_leechers'] . ' <img src="./themes/' . $ss_uri . '/images/arrowdown.gif" border="0" align="absbottom" /></td><td align="right">' . $leechers . '</td></tr>
              <tr><td class="rowhead">' . $tracker_lang['tracker_seed_peer'] . '</td><td align="right">' . $ratio . '</td></tr>
              <tr><td class="rowhead">' . $tracker_lang['external_seeders'] . ' <img src="./themes/' . $ss_uri . '/images/arrowup.gif" border="0" align="absbottom" /></td><td align="right">' . $external_seeders . '</td></tr>
              <tr><td class="rowhead">' . $tracker_lang['external_leechers'] . ' <img src="./themes/' . $ss_uri . '/images/arrowdown.gif" border="0" align="absbottom" /></td><td align="right">' . $external_leechers . '</td></tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>';
?>
