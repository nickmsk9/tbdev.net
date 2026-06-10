<?php

declare(strict_types=1);

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $tracker_lang;

$blocktitle = $tracker_lang['help_seed'] ?? 'Помогите раздачам';

$title = htmlspecialchars($blocktitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$content .= '
<table width="100%" cellspacing="0" cellpadding="5">
    <tr>
        <td class="colhead" align="center">' . $title . '</td>
    </tr>
    <tr>
        <td class="text">
';

$sql = "
    SELECT id, name, seeders, leechers
    FROM torrents
    WHERE leechers > 0
      AND (
          seeders = 0
          OR leechers >= seeders * 4
      )
    ORDER BY leechers DESC
    LIMIT 20
";

$res = sql_query($sql) or sqlerr(__FILE__, __LINE__);

if (mysqli_num_rows($res) > 0) {
    while ($row = mysqli_fetch_assoc($res)) {
        $id = (int)$row['id'];
        $name = (string)$row['name'];
        $seeders = (int)$row['seeders'];
        $leechers = (int)$row['leechers'];

        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $shortName = mb_strlen($name, 'UTF-8') > 55
            ? mb_substr($name, 0, 55, 'UTF-8') . '...'
            : $name;

        $safeShortName = htmlspecialchars($shortName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $content .= '
            <div>
                <a class="alink" href="details.php?id=' . $id . '&amp;hit=1" title="' . $safeName . '">' . $safeShortName . '</a>
                <span class="small">
                    <b>
                        <span class="green">сидов: ' . number_format($seeders) . '</span>,
                        <span class="red">личеров: ' . number_format($leechers) . '</span>
                    </b>
                </span>
            </div>
        ';
    }
} else {
    $noNeedSeeding = htmlspecialchars(
        $tracker_lang['no_need_seeding'] ?? 'Сейчас нет раздач, которым нужна помощь.',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $content .= '<b>' . $noNeedSeeding . '</b>';
}

$content .= '
        </td>
    </tr>
</table>
';
?>