<?php
declare(strict_types=1);

if (!defined('BLOCK_FILE')) {
    header("Location: ../index.php");
    exit;
}

$blocktitle = "Новые раздачи";

// сколько показывать
$perpage = 5;

// считаем кол-во (для pager)
$countRes = sql_query("SELECT COUNT(*) AS c FROM torrents WHERE visible = 'yes'") or sqlerr(__FILE__, __LINE__);
$countRow = mysqli_fetch_assoc($countRes);
$count = (int)($countRow['c'] ?? 0);

// маленький быстрый “превью” текста (без тяжёлых BBCode-парсеров)
$make_preview = static function (string $text, int $max = 220): string {
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    // Убираем самые частые BBCode-теги (быстро)
    $text = preg_replace('~\[(?:/?)(?:b|i|u|s|quote|code|spoiler|url|img|color|size|center|left|right|align|font|list|\*)[^\]]*\]~i', '', $text) ?? $text;
    $text = preg_replace("~\r\n|\r~", "\n", $text) ?? $text;
    $text = preg_replace("~\n{3,}~", "\n\n", $text) ?? $text;
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    // mbstring обычно включён; если нет — fallback
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') > $max) {
            $text = mb_substr($text, 0, $max, 'UTF-8') . '…';
        }
    } else {
        if (strlen($text) > $max) {
            $text = substr($text, 0, $max) . '...';
        }
    }

    return htmlspecialchars_uni($text);
};

$content = '';
$content .= '<table cellspacing="0" cellpadding="5" width="100%">';

if ($count <= 0) {
    $content .= '<tr><td>Новых загрузок нет...</td></tr>';
} else {
    // ВАЖНО: pager() возвращает <table> с <td>, поэтому мы кладём его в отдельную строку/ячейку
    list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, $_SERVER["PHP_SELF"] . "?");

    $content .= '<tr><td>' . $pagertop . '</td></tr>';

    // Берём последние торренты из torrents
    $res = sql_query("
        SELECT
            t.id, t.name, t.added, t.category,
            t.seeders, t.leechers, t.times_completed,
            t.image1,
            t.descr,
            c.id AS catid, c.name AS catname, c.image AS catimage
        FROM torrents AS t
        LEFT JOIN categories AS c ON t.category = c.id
        WHERE t.visible = 'yes'
        ORDER BY t.id DESC
        $limit
    ") or sqlerr(__FILE__, __LINE__);

    while ($t = mysqli_fetch_assoc($res)) {
        $tid = (int)($t['id'] ?? 0);
        $name = (string)($t['name'] ?? '');

        $seeders = (int)($t['seeders'] ?? 0);
        $leechers = (int)($t['leechers'] ?? 0);
        $completed = (int)($t['times_completed'] ?? 0);

        $catid = (int)($t['catid'] ?? 0);
        $catname = (string)($t['catname'] ?? '');
        $catimage = (string)($t['catimage'] ?? '');

        $poster = (string)($t['image1'] ?? '');
        $descr  = (string)($t['descr'] ?? '');

        $safeTitle = htmlspecialchars_uni($name);

        // Постер
        if ($poster !== '') {
            $posterSrc = (str_starts_with($poster, 'http://') || str_starts_with($poster, 'https://'))
                ? $poster
                : "torrents/images/" . $poster;

            $posterHtml = '<a href="details.php?id=' . $tid . '" title="' . $safeTitle . '">'
                . '<img src="' . htmlspecialchars_uni($posterSrc) . '" width="160" border="0" />'
                . '</a>';
        } else {
            $posterHtml = '<a href="details.php?id=' . $tid . '" title="' . $safeTitle . '">'
                . '<img src="pic/noposter.png" width="160" border="0" />'
                . '</a>';
        }

        // Иконка категории (аккуратно в правой колонке, справа)
        $catHtml = '';
        if ($catimage !== '') {
            $catHtml = '<a href="browse.php?cat=' . $catid . '">'
                . '<img src="pic/cats/' . htmlspecialchars_uni($catimage) . '"'
                . ' alt="' . htmlspecialchars_uni($catname) . '"'
                . ' title="' . htmlspecialchars_uni($catname) . '" border="0" />'
                . '</a>';
        } elseif ($catname !== '') {
            $catHtml = htmlspecialchars_uni($catname);
        }

        $preview = $make_preview($descr, 240);

        $content .= '<tr><td>';

        $content .= '<table width="100%" class="main" border="1" cellspacing="0" cellpadding="5">';

        // Заголовок
        $content .= '<tr><td class="colhead" colspan="2" align="center">'
            . '<a class="altlink_white" href="details.php?id=' . $tid . '"><b>' . $safeTitle . '</b></a>'
            . '</td></tr>';

        $content .= '<tr valign="top">';
        $content .= '<td align="center" width="160">' . $posterHtml . '</td>';

        $content .= '<td>';

        // описание
        if ($preview !== '') {
            $content .= '<br /><div align="left">' . $preview . '</div>';
        }

        // категория справа, статы слева
        $content .= '<table width="100%" cellspacing="0" cellpadding="0" border="0"><tr valign="top">';
        $content .= '<td align="left">';
        $content .= '<b>Сиды:</b> ' . $seeders . ' &nbsp; ';
        $content .= '<b>Личи:</b> ' . $leechers . ' &nbsp; ';
        $content .= '<b>Скачали:</b> ' . $completed;
        $content .= '</td>';
        $content .= '<td align="right">' . $catHtml . '</td>';
        $content .= '</tr></table>';


        $content .= '<br /><div align="right">[<a href="details.php?id=' . $tid . '" title="' . $safeTitle . '"><b>Открыть</b></a>]</div>';

        $content .= '</td>';
        $content .= '</tr>';

        $content .= '</table>';

        $content .= '</td></tr>';
    }

    $content .= '<tr><td>' . $pagerbottom . '</td></tr>';
}

$content .= '</table>';
