<?php
declare(strict_types=1);

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $CURUSER, $tracker_lang, $ss_uri;

$blocktitle = $tracker_lang['poll'] ?? 'Опрос';

/* --- helpers --- */
$h  = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$nf = static fn($n): string => number_format((int)$n);

/* --- 1) current poll --- */
$res = sql_query("SELECT * FROM polls ORDER BY added DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
$pollok = ($res && mysqli_num_rows($res) > 0);

$pollid = 0;
$userid = isset($CURUSER['id']) ? (int)$CURUSER['id'] : 0;
$voted  = false;
$arr    = [];
$o      = [];

if ($pollok) {
    $arr = (array)mysqli_fetch_assoc($res);
    $pollid = (int)($arr['id'] ?? 0);

    // options 0..19
    for ($i = 0; $i < 20; $i++) {
        $o[$i] = (string)($arr["option{$i}"] ?? '');
    }

    /* --- 2) voted? (cheap) --- */
    if ($userid > 0 && $pollid > 0) {
        $r2 = sql_query("SELECT 1 FROM pollanswers WHERE pollid=".(int)$pollid." AND userid=".(int)$userid." LIMIT 1")
            or sqlerr(__FILE__, __LINE__);
        $voted = ($r2 && mysqli_num_rows($r2) > 0);
    }
}

if (get_user_class() >= UC_MODERATOR) {
    $blocktitle .= '<span class="small"> - [<a class="altlink" href="makepoll.php?returnto=main"><b>' . ($tracker_lang['create'] ?? 'Создать') . '</b></a>]';
    if ($pollok) {
        $blocktitle .= ' - [<a class="altlink" href="makepoll.php?action=edit&pollid=' . (int)$pollid . '&returnto=main"><b>Редактировать</b></a>]';
        $blocktitle .= ' - [<a class="altlink" href="polls.php?action=delete&pollid=' . (int)$pollid . '&returnto=main"><b>Удалить</b></a>]';
    }
    $blocktitle .= '</span>';
}

$content = '';

if (!$pollok) {
    $content .= '<div align="center" class="text"><b>' . ($tracker_lang['no_polls'] ?? 'Нет активных опросов') . '</b></div>';
    return;
}

/* --- header (clean) --- */
$question = (string)($arr['question'] ?? '');
$content .= '
<div style="padding:6px 8px; text-align:center;">
  <div style="font-weight:bold; line-height:1.25;">' . $h($question) . '</div>
</div>
';

if ($voted && $userid > 0) {
    /* --- 3) results in one aggregated query --- */
    $r3 = sql_query("
        SELECT selection, COUNT(*) AS c
        FROM pollanswers
        WHERE pollid=".(int)$pollid." AND selection < 20
        GROUP BY selection
    ") or sqlerr(__FILE__, __LINE__);

    $vs = array_fill(0, 20, 0);
    $tvotes = 0;

    while ($row = mysqli_fetch_assoc($r3)) {
        $sel = (int)$row['selection'];
        $cnt = (int)$row['c'];
        if ($sel >= 0 && $sel < 20) {
            $vs[$sel] = $cnt;
            $tvotes += $cnt;
        }
    }

    $os = [];
    for ($i = 0; $i < 20; $i++) {
        if ($o[$i] !== '') {
            $os[] = [$i, $vs[$i], $o[$i]];
        }
    }

    if (($arr['sort'] ?? 'yes') === 'yes') {
        usort($os, static function($a, $b) {
            // sort by votes desc
            return $b[1] <=> $a[1];
        });
    }

    
    $uservote = isset($arr['selection']) ? (int)$arr['selection'] : -1;

    $content .= '
    <table class="main" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin:0 auto;">
      <tr><td style="padding:4px 6px;">
        <table width="100%" border="0" cellspacing="0" cellpadding="4">
    ';

    foreach ($os as $idx => $it) {
        [$optIndex, $cnt, $text] = $it;

        $p = ($tvotes > 0) ? (int)round(($cnt / $tvotes) * 100) : 0;
        if ($p < 0) $p = 0;
        if ($p > 100) $p = 100;

        $mark = ($optIndex === $uservote) ? ' <span style="font-weight:bold;">•</span>' : '';
        $rowBg = ($idx % 2 === 0) ? ' style="background:#f6f6f6;"' : '';

        
        $content .= '
<tr' . $rowBg . '>
  <td class="embedded" style="padding:6px 6px;">
    <div style="display:flex; align-items:center; justify-content:center; gap:10px;">
      <div style="max-width:240px; text-align:right;">
        <span>' . $h($text) . '</span>' . $mark . '
      </div>

      <div style="width:170px; height:14px; background:#e9e9e9; border-radius:999px; overflow:hidden;">
        <div style="height:14px; width:' . $p . '%; background:#4f7fd9; border-radius:999px;"></div>
      </div>

      <div style="min-width:62px; text-align:left; white-space:nowrap;">
        <b>' . $p . '%</b>
        <span class="small" style="opacity:.85;">(' . $nf($cnt) . ')</span>
      </div>
    </div>
  </td>
</tr>';

    }

    $content .= '
        </table>
      </td></tr>
    </table>
    <div align="center" style="padding:6px 0;" class="small">
      Голосов: <b>' . $nf($tvotes) . '</b>
    </div>
    <div align="center"><a href="polls.php">' . ($tracker_lang['old_polls'] ?? 'Старые опросы') . '</a></div>
    ';

} else {
    // Vote form (cleaner layout, still light)
    $content .= '<form method="post" action="index.php" style="margin:0;">
    <table width="100%" border="0" cellspacing="0" cellpadding="4" class="main" style="margin:0 auto;">
    ';

    $shown = 0;
    for ($i = 0; $i < 20; $i++) {
        if ($o[$i] === '') continue;
        $shown++;
        $content .= '
        <tr>
          <td class="embedded" style="padding:6px 6px;">
            <label style="cursor:pointer; display:block;">
              <input type="radio" name="choice" value="' . (int)$i . '" />
              <span style="margin-left:6px;">' . $h($o[$i]) . '</span>
            </label>
          </td>
        </tr>';
    }

    // blank vote
    $content .= '
        <tr>
          <td class="embedded" style="padding:6px 6px; background:#f6f6f6;">
            <label style="cursor:pointer; display:block;">
              <input type="radio" name="choice" value="255" />
              <span style="margin-left:6px;">' . ($tracker_lang['blank_vote'] ?? 'Воздержаться') . '</span>
            </label>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:8px 6px;">
            <input type="submit" value="' . ($tracker_lang['vote'] ?? 'Голосовать') . '!" class="btn" />
            <input type="hidden" name="pollid" value="' . (int)$pollid . '" />
          </td>
        </tr>
    </table>
    </form>';
}
?>
