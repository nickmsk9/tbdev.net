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

$content = '';
$content .= '<table cellspacing="0" cellpadding="5" width="100%"><tr><td>';

if ($count <= 0) {
    $content .= 'Новых загрузок нет...';
} else {
    list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, $_SERVER["PHP_SELF"] . "?");

    $content .= $pagertop;
    $content .= "</td></tr>";

    // Берём последние торренты из torrents
    $res = sql_query("
        SELECT
            t.id, t.name, t.added, t.category,
            t.seeders, t.leechers, t.times_completed,
            t.image1,
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

        // Постер: берём из torrents.image1 (если у тебя хранится иначе — поменяй тут)
        $poster = (string)($t['image1'] ?? '');

        $titleAttr = htmlspecialchars_uni($name);

        $content .= "<tr><td>";
        $content .= "<table width=\"100%\" class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">";

        // Заголовок
        $content .= "<tr><td class=\"colhead\" colspan=\"2\" align=\"center\">"
            . "<a class=\"altlink_white\" href=\"details.php?id={$tid}\"><b>{$titleAttr}</b></a>"
            . "</td></tr>";

        // Левая колонка (постер)
        if ($poster !== '') {
            // если в image1 хранится имя файла из torrents/images/
            // если у тебя хранится полный URL — оставь как есть
            $posterSrc = (str_starts_with($poster, 'http://') || str_starts_with($poster, 'https://'))
                ? $poster
                : "torrents/images/" . $poster;

            $posterHtml = "<a href=\"details.php?id={$tid}\" title=\"{$titleAttr}\">"
                . "<img src=\"" . htmlspecialchars_uni($posterSrc) . "\" width=\"160\" border=\"0\" />"
                . "</a>";
        } else {
            $posterHtml = "<a href=\"details.php?id={$tid}\" title=\"{$titleAttr}\">"
                . "<img src=\"pic/noposter.png\" width=\"160\" border=\"0\" />"
                . "</a>";
        }

        $content .= "<tr valign=\"top\">";
        $content .= "<td align=\"center\" width=\"160\">{$posterHtml}</td>";

        // Правая колонка
        $catHtml = $catimage !== ''
            ? "<a href=\"browse.php?cat={$catid}\"><img src=\"pic/cats/" . htmlspecialchars_uni($catimage) . "\" alt=\"" . htmlspecialchars_uni($catname) . "\" title=\"" . htmlspecialchars_uni($catname) . "\" align=\"right\" border=\"0\" /></a>"
            : "<span style=\"float:right;\">" . htmlspecialchars_uni($catname) . "</span>";

        $content .= "<td>";
        $content .= "<div align=\"left\">{$catHtml}</div>";

        // Статы (важно: показываем и “мёртвые” тоже)
        $content .= "<div align=\"left\">";
        $content .= "<b>Сиды:</b> {$seeders} &nbsp; ";
        $content .= "<b>Личи:</b> {$leechers} &nbsp; ";
        $content .= "<b>Скачали:</b> {$completed}";
        $content .= "</div>";

        $content .= "<div align=\"right\">[<a href=\"details.php?id={$tid}\" title=\"{$titleAttr}\"><b>Открыть</b></a>]</div>";
        $content .= "</td>";

        $content .= "</tr>";
        $content .= "</table>";
        $content .= "</td></tr>";
    }

    $content .= "<tr><td>{$pagerbottom}</td></tr>";
}

$content .= "</table>";
