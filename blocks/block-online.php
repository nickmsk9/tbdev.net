<?php

declare(strict_types=1);

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $CURUSER, $use_sessions;

$blocktitle = 'Кто в онлайне';

$onlineTimeout = 300;
$onlineFrom = time() - $onlineTimeout;

function block_online_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Последний зарегистрированный пользователь
$latestUser = 'Нет пользователей';

$resLatest = sql_query("
    SELECT id, username
    FROM users
    WHERE status = 'confirmed'
    ORDER BY id DESC
    LIMIT 1
") or sqlerr(__FILE__, __LINE__);

if ($latest = mysqli_fetch_assoc($resLatest)) {
    $latestId = (int)$latest['id'];
    $latestName = (string)$latest['username'];

    if ($latestId > 0 && $latestName !== '') {
        $latestNameHtml = block_online_h($latestName);

        $latestUser = !empty($CURUSER)
            ? '<a class="online" href="userdetails.php?id=' . $latestId . '">' . $latestNameHtml . '</a>'
            : $latestNameHtml;
    }
}

mysqli_free_result($resLatest);

// Онлайн
$onlineUsers = [];
$seenIds = [];

$stats = [
    'staff' => 0,
    'users' => 0,
    'guests' => 0,
    'total' => 0,
];

if (!empty($use_sessions)) {
    $sql = "
        SELECT uid AS id, username, class
        FROM sessions
        WHERE time > " . sqlesc($onlineFrom) . "
        ORDER BY class DESC, username ASC
    ";
} else {
    $sql = "
        SELECT id, username, class
        FROM users
        WHERE last_access > " . sqlesc($onlineFrom) . "
        ORDER BY class DESC, username ASC
    ";
}

$resOnline = sql_query($sql) or sqlerr(__FILE__, __LINE__);

while ($row = mysqli_fetch_assoc($resOnline)) {
    $uid = (int)($row['id'] ?? 0);
    $username = trim((string)($row['username'] ?? ''));
    $class = (int)($row['class'] ?? 0);

    // Гостей в списке пользователей не показываем
    if ($uid <= 0 || $username === '') {
        $stats['guests']++;
        $stats['total']++;
        continue;
    }

    // Защита от дублей в sessions
    if (isset($seenIds[$uid])) {
        continue;
    }

    $seenIds[$uid] = true;

    $usernameHtml = block_online_h($username);
    $coloredUsername = get_user_class_color($class, $usernameHtml);

    $onlineUsers[] = '<a class="online" href="userdetails.php?id=' . $uid . '">' . $coloredUsername . '</a>';

    if (defined('UC_MODERATOR') && $class >= UC_MODERATOR) {
        $stats['staff']++;
    } else {
        $stats['users']++;
    }

    $stats['total']++;
}

mysqli_free_result($resOnline);

$whoOnline = $onlineUsers
    ? implode(', ', $onlineUsers)
    : '<span class="small">Нет пользователей онлайн за последние 5 минут.</span>';

$content = '
<table width="100%" cellspacing="0" cellpadding="3">
    <tr>
        <td class="embedded">
            <b>Последний зарегистрированный:</b><br>
            ' . $latestUser . '
        </td>
    </tr>

    <tr>
        <td class="embedded"><hr></td>
    </tr>

    <tr>
        <td class="embedded">
            <b>Кто здесь:</b><br>
            ' . $whoOnline . '
        </td>
    </tr>

    <tr>
        <td class="embedded"><hr></td>
    </tr>

    <tr>
        <td class="embedded">
            <b>В онлайне:</b>
        </td>
    </tr>

    <tr>
        <td class="embedded">
            <table width="100%" cellspacing="0" cellpadding="3">
                <tr>
                    <td class="embedded" width="1%">
                        <img src="pic/info/admin.gif" alt="Админы">
                    </td>
                    <td class="embedded" width="99%">
                        Админы: ' . (int)$stats['staff'] . '
                    </td>
                </tr>

                <tr>
                    <td class="embedded" width="1%">
                        <img src="pic/info/member.gif" alt="Пользователи">
                    </td>
                    <td class="embedded" width="99%">
                        Пользователи: ' . (int)$stats['users'] . '
                    </td>
                </tr>

                <tr>
                    <td class="embedded" width="1%">
                        <img src="pic/info/guest.gif" alt="Гости">
                    </td>
                    <td class="embedded" width="99%">
                        Гости: ' . (int)$stats['guests'] . '
                    </td>
                </tr>

                <tr>
                    <td class="embedded" width="1%">
                        <img src="pic/info/group.gif" alt="Всего">
                    </td>
                    <td class="embedded" width="99%">
                        <b>Всего: ' . (int)$stats['total'] . '</b>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
';
?>
