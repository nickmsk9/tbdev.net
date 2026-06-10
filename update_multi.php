<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';
require_once __DIR__ . '/include/multitracker.php';

dbconn(false);
loggedinorreturn();
parked();

$request = array_merge($_GET, $_POST);
$torrentId = (int)($request['id'] ?? 0);
$ajax = (string)($request['ajax'] ?? '') === 'yes';
$json = (string)($request['format'] ?? '') === 'json';
$force = (string)($request['force'] ?? '') === '1';
$token = (string)($request['token'] ?? '');

$respondError = static function (string $message, int $status = 400) use ($ajax, $json): void {
    if ($json) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($ajax) {
        http_response_code($status);
        echo '<span style="color:#b00020">' . htmlspecialchars_uni($message) . '</span>';
        exit;
    }
    stderr('Ошибка', $message);
};

if ($torrentId <= 0) {
    $respondError('Некорректный ID раздачи.');
}

$torrentResult = sql_query(
    "SELECT id, owner, multitracker, last_mt_update
     FROM torrents WHERE id = $torrentId LIMIT 1"
) or sqlerr(__FILE__, __LINE__);
$torrent = mysqli_fetch_assoc($torrentResult);

if (!$torrent || (string)$torrent['multitracker'] !== 'yes') {
    $respondError('Раздача не найдена или мультитрекер для нее отключен.', 404);
}

$userId = (int)($CURUSER['id'] ?? 0);
$canForce = $userId === (int)$torrent['owner'] || get_user_class() >= UC_MODERATOR;
$expectedToken = multitracker_action_token($torrentId, $userId);
if ($token === '' || !hash_equals($expectedToken, $token)) {
    $respondError('Срок действия ссылки обновления истек. Перезагрузите страницу.', 403);
}
if ($force && !$canForce) {
    $respondError('Принудительное обновление доступно автору раздачи и модераторам.', 403);
}

$lastUpdate = strtotime((string)$torrent['last_mt_update']) ?: 0;
if (!$force && $lastUpdate > TIMENOW - 3600) {
    $respondError('Статистика уже обновлялась в течение последнего часа.', 429);
}

try {
    $stats = multitracker_refresh($torrentId);
} catch (Throwable $exception) {
    $respondError($exception->getMessage());
}

$totalsResult = sql_query(
    "SELECT seeders + remote_seeders AS seeders,
            leechers + remote_leechers AS leechers
     FROM torrents WHERE id = $torrentId"
) or sqlerr(__FILE__, __LINE__);
$totals = mysqli_fetch_assoc($totalsResult) ?: ['seeders' => 0, 'leechers' => 0];
$html = multitracker_status_html($torrentId, $canForce, $userId);

if ($json) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'message' => 'Статистика мультитрекера обновлена.',
        'success' => (int)$stats['success'],
        'errors' => (int)$stats['errors'],
        'seeders' => (int)$totals['seeders'],
        'leechers' => (int)$totals['leechers'],
        'html' => $html,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($ajax) {
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

header('Refresh: 2;url=details.php?id=' . $torrentId);
stderr(
    'Успех',
    'Статистика обновлена. Успешных трекеров: ' . (int)$stats['success'] .
    ', ошибок: ' . (int)$stats['errors'] . '.'
);
