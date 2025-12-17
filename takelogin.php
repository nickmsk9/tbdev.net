<?

/*
// +--------------------------------------------------------------------------+
// | Project:    TBDevYSE - TBDev Yuna Scatari Edition                        |
// +--------------------------------------------------------------------------+
// | This file is part of TBDevYSE. TBDevYSE is based on TBDev,               |
// | originally by RedBeard of TorrentBits, extensively modified by           |
// | Gartenzwerg.                                                             |
// |                                                                          |
// | TBDevYSE is free software; you can redistribute it and/or modify         |
// | it under the terms of the GNU General Public License as published by     |
// | the Free Software Foundation; either version 2 of the License, or        |
// | (at your option) any later version.                                      |
// |                                                                          |
// | TBDevYSE is distributed in the hope that it will be useful,              |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
// | GNU General Public License for more details.                             |
// |                                                                          |
// | You should have received a copy of the GNU General Public License        |
// | along with TBDevYSE; if not, write to the Free Software Foundation,      |
// | Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            |
// +--------------------------------------------------------------------------+
// |                                               Do not remove above lines! |
// +--------------------------------------------------------------------------+
*/


declare(strict_types=1);

require_once __DIR__ . "/include/bittorrent.php";

if (!mkglobal("username:password")) {
    die();
}

dbconn(false);

function bark(string $text = "Неверное имя пользователя или пароль."): void
{
    stderr("Ошибка входа", $text);
    exit;
}

function is_password_correct(string $password, string $secret, string $hash): bool
{
    // TBDev-стиль: md5(secret . pass . secret)
    return $hash === md5($secret . $password . $secret)
        || $hash === md5($secret . trim($password) . $secret);
}

$username = (string)($username ?? '');
$password = (string)($password ?? '');

if ($username === '' || $password === '') {
    bark("Заполните логин и пароль.");
}

$res = sql_query(
    "SELECT id, passhash, secret, enabled, status, ip
     FROM users
     WHERE username = " . sqlesc($username) . " LIMIT 1"
);

$row = $res ? mysqli_fetch_assoc($res) : false;
if (!$row) {
    bark("Неверное имя пользователя или пароль.");
}

if (($row['status'] ?? '') === 'pending') {
    bark("Ваш аккаунт ещё не активирован. Проверьте письмо и завершите активацию.");
}

if (!is_password_correct($password, (string)$row['secret'], (string)$row['passhash'])) {
    bark(); // дефолтное сообщение
}

if (($row['enabled'] ?? '') === 'no') {
    bark("Ваш аккаунт отключён.");
}

$uid = (int)$row['id'];

$peersRes = sql_query("SELECT COUNT(id) AS c FROM peers WHERE userid = $uid");
$peersRow = $peersRes ? mysqli_fetch_assoc($peersRes) : ['c' => 0];
$numPeers = (int)($peersRow['c'] ?? 0);

$ip = getip();
$storedIp = (string)($row['ip'] ?? '');

if ($numPeers > 0 && $storedIp !== '' && $storedIp !== $ip) {
    bark("Этот аккаунт уже используется на другом IP. Доступ запрещён.");
}

logincookie($uid, (string)$row['passhash']);

$returnTo = (string)($_POST['returnto'] ?? '');
if ($returnTo !== '') {
    // простая защита от редиректа “куда угодно”
    $returnTo = ltrim($returnTo, "/");
    header("Location: {$DEFAULTBASEURL}/{$returnTo}");
    exit;
}

header("Location: {$DEFAULTBASEURL}/");
exit;
