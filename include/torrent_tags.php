<?php
declare(strict_types=1);

if (!defined('IN_TRACKER')) {
    die('Hacking attempt!');
}

function torrent_tags_normalize(string $input): array
{
    $parts = preg_split('/[,;\r\n]+/u', $input) ?: [];
    $tags = [];
    $seen = [];
    $keywordsLength = 0;

    foreach ($parts as $part) {
        $name = trim((string)preg_replace('/\s+/u', ' ', trim($part)));
        if ($name === '') {
            continue;
        }

        if (function_exists('mb_substr')) {
            $name = mb_substr($name, 0, 50, 'UTF-8');
            $keyName = mb_strtolower($name, 'UTF-8');
        } else {
            $name = substr($name, 0, 50);
            $keyName = strtolower($name);
        }

        $key = sha1($keyName);
        if (isset($seen[$key])) {
            continue;
        }

        $newLength = $keywordsLength + ($tags ? 2 : 0) + strlen($name);
        if ($newLength > 255 || count($tags) >= 20) {
            break;
        }

        $seen[$key] = true;
        $tags[] = ['name' => $name, 'name_key' => $key];
        $keywordsLength = $newLength;
    }

    return $tags;
}

function torrent_tags_keywords(string $input): string
{
    return implode(', ', array_column(torrent_tags_normalize($input), 'name'));
}

function torrent_tags_sync(int $torrentId, string $input): string
{
    global $mysql_link;

    if ($torrentId <= 0) {
        return '';
    }

    $tags = torrent_tags_normalize($input);
    $keywords = implode(', ', array_column($tags, 'name'));

    sql_query("DELETE FROM torrent_tag_map WHERE torrent_id = $torrentId") or sqlerr(__FILE__, __LINE__);

    foreach ($tags as $tag) {
        sql_query(
            "INSERT INTO torrent_tags (name, name_key) VALUES (" .
            sqlesc($tag['name']) . ", " . sqlesc($tag['name_key']) . ")
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name)"
        ) or sqlerr(__FILE__, __LINE__);

        $tagId = (int)mysqli_insert_id($mysql_link);
        if ($tagId <= 0) {
            $tagResult = sql_query(
                "SELECT id FROM torrent_tags WHERE name_key = " . sqlesc($tag['name_key']) . " LIMIT 1"
            ) or sqlerr(__FILE__, __LINE__);
            $tagRow = mysqli_fetch_assoc($tagResult);
            $tagId = (int)($tagRow['id'] ?? 0);
        }

        if ($tagId > 0) {
            sql_query(
                "INSERT IGNORE INTO torrent_tag_map (torrent_id, tag_id) VALUES ($torrentId, $tagId)"
            ) or sqlerr(__FILE__, __LINE__);
        }
    }

    sql_query(
        "UPDATE torrents SET keywords = " . sqlesc($keywords) . " WHERE id = $torrentId"
    ) or sqlerr(__FILE__, __LINE__);

    return $keywords;
}

function torrent_tags_for_torrent(int $torrentId): array
{
    if ($torrentId <= 0) {
        return [];
    }

    $result = sql_query(
        "SELECT tt.id, tt.name
         FROM torrent_tag_map AS tm
         INNER JOIN torrent_tags AS tt ON tt.id = tm.tag_id
         WHERE tm.torrent_id = $torrentId
         ORDER BY tt.name"
    ) or sqlerr(__FILE__, __LINE__);

    $tags = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tags[] = $row;
    }

    return $tags;
}

function torrent_tags_links(int $torrentId): string
{
    $links = [];
    foreach (torrent_tags_for_torrent($torrentId) as $tag) {
        $links[] = '<a href="browse.php?tag=' . (int)$tag['id'] . '">' .
            htmlspecialchars_uni((string)$tag['name']) . '</a>';
    }

    return implode(', ', $links);
}
