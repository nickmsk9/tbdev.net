<?php
require_once("include/bittorrent.php");

dbconn(false);
loggedinorreturn();

header('Content-Type: application/json');

// Проверка CSRF-токена
$token = $_POST['token'] ?? '';
$expected_token = md5($CURUSER['id'] . $CURUSER['secret']);

if ($token !== $expected_token) {
    echo json_encode(['success' => false, 'message' => 'Неверный токен безопасности']);
    exit;
}

$bookmark_id = (int)($_POST['id'] ?? 0);

if (!is_valid_id($bookmark_id)) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID закладки']);
    exit;
}

// Проверяем, принадлежит ли закладка пользователю
$res = sql_query("
    SELECT bookmarks.id, torrents.name 
    FROM bookmarks 
    INNER JOIN torrents ON bookmarks.torrentid = torrents.id 
    WHERE bookmarks.id = $bookmark_id AND bookmarks.userid = " . sqlesc($CURUSER['id'])
);

if (mysqli_num_rows($res) == 0) {
    echo json_encode(['success' => false, 'message' => 'Закладка не найдена или не принадлежит вам']);
    exit;
}

$row = mysqli_fetch_assoc($res);
$torrent_name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');

// Удаляем закладку
sql_query("DELETE FROM bookmarks WHERE id = $bookmark_id AND userid = " . sqlesc($CURUSER['id'])) or sqlerr(__FILE__, __LINE__);

// Логируем действие
write_log("Закладка на торрент \"$torrent_name\" удалена пользователем " . $CURUSER['username']);

echo json_encode([
    'success' => true, 
    'message' => "Торрент \"$torrent_name\" удален из закладок"
]);

exit;
?>