<?php
declare(strict_types=1);

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

$result = sql_query(
    "SELECT tt.id, tt.name, COUNT(DISTINCT tm.torrent_id) AS usage_count
     FROM torrent_tags AS tt
     INNER JOIN torrent_tag_map AS tm ON tm.tag_id = tt.id
     INNER JOIN torrents AS t ON t.id = tm.torrent_id
     WHERE t.visible = 'yes' AND t.banned = 'no'
     GROUP BY tt.id, tt.name
     ORDER BY tt.name"
) or sqlerr(__FILE__, __LINE__);

$tags = [];
$minCount = PHP_INT_MAX;
$maxCount = 0;
while ($tag = mysqli_fetch_assoc($result)) {
    $count = max(1, (int)$tag['usage_count']);
    $tag['usage_count'] = $count;
    $tags[] = $tag;
    $minCount = min($minCount, $count);
    $maxCount = max($maxCount, $count);
}

$palette = ['#306a8c', '#8a4f9e', '#247f81', '#2852a3', '#6d7780', '#b15c93'];
$content = '<style>
.torrent-tag-cloud{padding:10px 8px 14px;line-height:2.05;text-align:left;background:#fff}
.torrent-tag-cloud a{text-decoration:none;margin-right:9px;white-space:nowrap}
.torrent-tag-cloud a:hover{text-decoration:underline}
.torrent-tag-cloud small{font-size:11px;color:#607078}
</style><div class="torrent-tag-cloud">';

if (!$tags) {
    $content .= 'Теги появятся после добавления их к раздачам.';
} else {
    foreach ($tags as $tag) {
        $count = (int)$tag['usage_count'];
        $ratio = $maxCount > $minCount
            ? (log($count) - log($minCount)) / (log($maxCount) - log($minCount))
            : 0.35;
        $fontSize = (int)round(12 + (18 * $ratio));
        $color = $palette[abs(crc32((string)$tag['name'])) % count($palette)];

        $content .= '<a href="browse.php?tag=' . (int)$tag['id'] . '" style="font-size:' .
            $fontSize . 'px;color:' . $color . '">' . htmlspecialchars_uni((string)$tag['name']) .
            ' <small>(' . $count . ')</small></a> ';
    }
}

$content .= '</div>';
