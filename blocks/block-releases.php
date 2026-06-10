<?php

declare(strict_types=1);

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $content;

$content = $content ?? '';

$blocktitle = 'Новые раздачи';
$perpage = 5;

$h = static function (?string $value): string {
    $value = (string)$value;

    if (function_exists('htmlspecialchars_uni')) {
        return htmlspecialchars_uni($value);
    }

    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$num = static function (int $value): string {
    return number_format($value, 0, '.', ' ');
};

$startsWithHttp = static function (string $value): bool {
    return strncmp($value, 'http://', 7) === 0 || strncmp($value, 'https://', 8) === 0;
};

$makePreview = static function (string $text, int $max = 900) use ($h): string {
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    // Быстрая очистка BBCode без тяжёлого парсера.
    $text = preg_replace(
        '~\[(?:/?)(?:b|i|u|s|quote|code|spoiler|url|img|color|size|center|left|right|align|font|list|\*)[^\]]*\]~i',
        '',
        $text
    ) ?? $text;

    $text = preg_replace('~\s+~u', ' ', $text) ?? $text;
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') > $max) {
            $text = mb_substr($text, 0, $max, 'UTF-8') . '...';
        }
    } elseif (strlen($text) > $max) {
        $text = substr($text, 0, $max) . '...';
    }

    return nl2br($h($text));
};

$sql = "
    SELECT
        t.id,
        t.name,
        t.added,
        COALESCE(t.seeders, 0) + COALESCE(t.remote_seeders, 0) AS seeders,
        COALESCE(t.leechers, 0) + COALESCE(t.remote_leechers, 0) AS leechers,
        COALESCE(t.times_completed, 0) AS times_completed,
        t.image1,
        LEFT(COALESCE(t.descr, ''), 1600) AS descr,
        c.id AS catid,
        c.name AS catname
    FROM torrents AS t
    LEFT JOIN categories AS c ON c.id = t.category
    WHERE t.visible = 'yes'
      AND t.banned = 'no'
    ORDER BY t.id DESC
    LIMIT " . (int)$perpage;

$res = sql_query($sql) or sqlerr(__FILE__, __LINE__);

$content .= '
<table width="100%" cellspacing="0" cellpadding="0">
';

$hasRows = false;

while ($t = mysqli_fetch_assoc($res)) {
    $tid = (int)($t['id'] ?? 0);
    $name = trim((string)($t['name'] ?? ''));

    if ($tid <= 0 || $name === '') {
        continue;
    }

    $hasRows = true;

    $safeTitle = $h($name);

    $seeders = (int)($t['seeders'] ?? 0);
    $leechers = (int)($t['leechers'] ?? 0);
    $completed = (int)($t['times_completed'] ?? 0);

    $poster = trim((string)($t['image1'] ?? ''));
    $descr = (string)($t['descr'] ?? '');

    $catid = (int)($t['catid'] ?? 0);
    $catname = trim((string)($t['catname'] ?? ''));

    $added = (string)($t['added'] ?? '');

    if ($poster !== '') {
        $posterSrc = $startsWithHttp($poster)
            ? $poster
            : 'torrents/images/' . basename($poster);
    } else {
        $posterSrc = '/images/default_torrent.png';
    }

    $categoryHtml = '';

    if ($catid > 0 && $catname !== '') {
        $categoryHtml = '<div style="float: right;">'
            . '<a title="' . $h($catname) . '" href="browse.php?cat=' . $catid . '">' . $h($catname) . '</a>'
            . '</div>';
    }

    $preview = $makePreview($descr, 900);

    if ($preview === '') {
        $preview = '<span class="small">Описание отсутствует.</span>';
    }

    $addedText = '';

    if ($added !== '') {
        $addedText = '<span class="small">Добавлен: ' . $h($added) . '</span>';
    }

    $content .= '
<tr>
    <td style="padding: 5px 0 5px 0;">
        <table width="100%" class="main" border="0" cellspacing="0" cellpadding="5">
            <tr>
                <td class="colhead" colspan="2" style="text-align: left;">
                    ' . $categoryHtml . '
                    <a class="altlink_white" href="details.php?id=' . $tid . '" title="' . $safeTitle . '">' . $safeTitle . '</a>
                </td>
            </tr>

            <tr valign="top">
                <td align="center" width="190">
                    <a href="details.php?id=' . $tid . '" title="' . $safeTitle . '">
                        <picture>
                            <img
                                class="avatars"
                                height="250"
                                width="180"
                                loading="lazy"
                                alt="' . $safeTitle . '"
                                border="0"
                                onerror="this.src=\'/images/default_torrent.png\';"
                                src="' . $h($posterSrc) . '">
                        </picture>
                    </a>
                    <br>
                    <span class="green small">Торрент доступен</span>
                </td>

                <td class="text">
                    ' . $preview . '
                    <br><br>
                    <a class="alink" href="details.php?id=' . $tid . '" title="' . $safeTitle . '">Открыть раздачу</a>
                </td>
            </tr>

            <tr>
                <td colspan="2" class="embedded">
                    <table width="100%" class="main" border="0" cellspacing="0" cellpadding="5">
                        <tr>
                            <td align="left" width="33%" class="embedded">
                                Раздают:
                                <b><span class="green">' . $num($seeders) . '</span></b>,
                                Качают:
                                <b><span class="red">' . $num($leechers) . '</span></b>
                            </td>

                            <td align="center" width="33%" class="embedded">
                                ' . $addedText . '
                            </td>

                            <td align="right" width="33%" class="embedded">
                                Завершено:
                                <b><span class="green">' . $num($completed) . '</span></b>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </td>
</tr>
';
}

mysqli_free_result($res);

if (!$hasRows) {
    $content .= '
<tr>
    <td class="text">
        Новых загрузок нет...
    </td>
</tr>
';
}

$content .= '
</table>
';

?>