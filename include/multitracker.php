<?php
declare(strict_types=1);

if (!defined('IN_TRACKER')) {
    die('Hacking attempt!');
}

require_once __DIR__ . '/scraper/httptscraper.php';
require_once __DIR__ . '/scraper/udptscraper.php';

function multitracker_normalize_hash(string $hash): string
{
    $hash = strtolower(trim($hash));
    return strlen($hash) === 40 && ctype_xdigit($hash) ? $hash : '';
}

function multitracker_action_token(int $torrentId, int $userId): string
{
    $salt = defined('COOKIE_SALT') ? (string)COOKIE_SALT : 'tbdev-multitracker';
    return hash_hmac('sha256', $torrentId . ':' . $userId, $salt);
}

function multitracker_error_text(Throwable $exception): string
{
    $message = trim($exception->getMessage());
    if ($message === '') {
        $message = 'Неизвестная ошибка scrape.';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($message, 0, 100, 'UTF-8');
    }

    return substr($message, 0, 100);
}

function multitracker_scrape_one(int $torrentId, string $url, string $infoHash): bool
{
    $infoHash = multitracker_normalize_hash($infoHash);
    if ($infoHash === '') {
        throw new InvalidArgumentException('Некорректный info_hash.');
    }

    try {
        if (stripos($url, 'udp://') === 0) {
            $scraper = new udptscraper(7);
        } elseif (preg_match('#^https?://#i', $url)) {
            $scraper = new httptscraper(7, 65536);
        } else {
            throw new ScraperException('Поддерживаются только HTTP, HTTPS и UDP трекеры.');
        }

        $data = $scraper->scrape($url, $infoHash);
        $stats = $data[$infoHash] ?? false;
        if (!is_array($stats)) {
            throw new ScraperException('Трекер не вернул статистику для этой раздачи.');
        }

        $seeders = max(0, (int)($stats['seeders'] ?? 0));
        $leechers = max(0, (int)($stats['leechers'] ?? 0));
        $completed = max(0, (int)($stats['completed'] ?? 0));

        sql_query(
            "UPDATE torrents_scrape
             SET state = 'ok', error = '', seeders = $seeders,
                 leechers = $leechers, completed = $completed
             WHERE tid = $torrentId AND url = " . sqlesc($url)
        ) or sqlerr(__FILE__, __LINE__);

        return true;
    } catch (Throwable $exception) {
        $error = multitracker_error_text($exception);
        sql_query(
            "UPDATE torrents_scrape
             SET state = 'error', error = " . sqlesc($error) . "
             WHERE tid = $torrentId AND url = " . sqlesc($url)
        ) or sqlerr(__FILE__, __LINE__);

        return false;
    }
}

function multitracker_refresh(int $torrentId): array
{
    $result = sql_query(
        "SELECT info_hash, url FROM torrents_scrape WHERE tid = $torrentId ORDER BY url"
    ) or sqlerr(__FILE__, __LINE__);

    $total = 0;
    $success = 0;
    while ($tracker = mysqli_fetch_assoc($result)) {
        $url = trim((string)($tracker['url'] ?? ''));
        $hash = multitracker_normalize_hash((string)($tracker['info_hash'] ?? ''));
        if ($url === '' || $hash === '') {
            continue;
        }

        $total++;
        if (multitracker_scrape_one($torrentId, $url, $hash)) {
            $success++;
        }
    }

    if ($total === 0) {
        throw new RuntimeException('У раздачи нет внешних трекеров.');
    }

    $statsResult = sql_query(
        "SELECT
            COALESCE(SUM(CASE WHEN state = 'ok' THEN seeders ELSE 0 END), 0) AS seeders,
            COALESCE(SUM(CASE WHEN state = 'ok' THEN leechers ELSE 0 END), 0) AS leechers,
            COALESCE(SUM(CASE WHEN state = 'ok' THEN completed ELSE 0 END), 0) AS completed
         FROM torrents_scrape
         WHERE tid = $torrentId"
    ) or sqlerr(__FILE__, __LINE__);
    $stats = mysqli_fetch_assoc($statsResult) ?: [];

    $seeders = max(0, (int)($stats['seeders'] ?? 0));
    $leechers = max(0, (int)($stats['leechers'] ?? 0));
    $completed = max(0, (int)($stats['completed'] ?? 0));
    $now = get_date_time();

    $set = [
        "last_mt_update = " . sqlesc($now),
    ];

    $set[] = "remote_seeders = $seeders";
    $set[] = "remote_leechers = $leechers";

    if ($success > 0) {
        $set[] = "times_completed = GREATEST(times_completed, $completed)";
        if ($seeders > 0) {
            $set[] = "visible = 'yes'";
            $set[] = "last_action = " . sqlesc($now);
        }
    }

    sql_query(
        "UPDATE torrents SET " . implode(', ', $set) . " WHERE id = $torrentId"
    ) or sqlerr(__FILE__, __LINE__);

    return [
        'total' => $total,
        'success' => $success,
        'errors' => $total - $success,
        'seeders' => $seeders,
        'leechers' => $leechers,
        'completed' => $completed,
        'last_update' => $now,
    ];
}

function multitracker_rows(int $torrentId): array
{
    $result = sql_query(
        "SELECT url, seeders, leechers, completed, last_update, state, error
         FROM torrents_scrape WHERE tid = $torrentId ORDER BY url"
    ) or sqlerr(__FILE__, __LINE__);

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return $rows;
}

function multitracker_status_html(int $torrentId, bool $canForce, int $userId): string
{
    $items = [];
    foreach (multitracker_rows($torrentId) as $tracker) {
        $url = htmlspecialchars_uni((string)$tracker['url']);
        if ((string)$tracker['state'] === 'ok') {
            $items[] = '<li><b>' . $url . '</b> - сиды: <b>' . (int)$tracker['seeders'] .
                '</b>, пиры: <b>' . (int)$tracker['leechers'] .
                '</b>, скачали: <b>' . (int)$tracker['completed'] . '</b></li>';
        } else {
            $items[] = '<li><span style="color:#b00020"><b>' . $url . '</b></span> - ' .
                htmlspecialchars_uni((string)$tracker['error']) . '</li>';
        }
    }

    if (!$items) {
        return 'Мультитрекер включен, но список трекеров пуст.';
    }

    $torrentResult = sql_query(
        "SELECT last_mt_update FROM torrents WHERE id = $torrentId LIMIT 1"
    ) or sqlerr(__FILE__, __LINE__);
    $torrent = mysqli_fetch_assoc($torrentResult) ?: [];
    $last = strtotime((string)($torrent['last_mt_update'] ?? '')) ?: 0;
    $token = multitracker_action_token($torrentId, $userId);

    $html = '<ul style="margin:0;">' . implode('', $items) . '</ul>';
    if ($userId > 0) {
        $force = $canForce ? '1' : '0';
        $label = $canForce ? 'Принудительно обновить статистику' : 'Обновить статистику';
        $html .= '<br><a href="update_multi.php?id=' . $torrentId . '&amp;force=' . $force .
            '&amp;token=' . $token . '" onclick="update_multi(' . $force . ', \'' . $token .
            '\'); return false;">' . $label . '</a>';
    }

    $html .= '<br>Последнее обновление: <b>' .
        ($last > 0 ? date('Y-m-d H:i:s', $last) : 'никогда') . '</b>';

    return $html;
}
