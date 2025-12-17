<?php
declare(strict_types=1);

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $CURUSER, $use_sessions;

$blocktitle = 'Кто в онлайне';
$dt = time() - 300; // 5 минут

// Последний зарегистрированный
$latestuser = 'Нет пользователей';
$resLatest = sql_query("SELECT id, username FROM users WHERE status='confirmed' ORDER BY id DESC LIMIT 1");
if ($resLatest && mysqli_num_rows($resLatest) > 0) {
    $lu = mysqli_fetch_assoc($resLatest);
    $lid = (int)($lu['id'] ?? 0);
    $lname = (string)($lu['username'] ?? '');
    $lnameHtml = htmlspecialchars($lname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    if ($lid > 0 && $lname !== '') {
        $latestuser = $CURUSER
            ? '<a href="userdetails.php?id=' . $lid . '" class="online">' . $lnameHtml . '</a>'
            : $lnameHtml;
    }
}
if ($resLatest) {
    mysqli_free_result($resLatest);
}

// Онлайн-лист и счётчики
$title_who = [];
$users = 0;
$guests = 0;
$staff = 0;
$total = 0;

$sql = $use_sessions
    ? "SELECT s.uid AS id, s.username, s.class FROM sessions AS s WHERE s.time > " . sqlesc($dt) . " ORDER BY s.class DESC"
    : "SELECT u.id AS id, u.username, u.class FROM users AS u WHERE u.last_access > " . sqlesc($dt) . " ORDER BY u.class DESC";

$result = sql_query($sql);

$seenNames = [];
$seenIds = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $uid   = (int)($row['id'] ?? 0);
        $uname = (string)($row['username'] ?? '');
        $class = (int)($row['class'] ?? 0);

        // "Кто здесь" (уникально по username)
        if ($uname !== '' && !isset($seenNames[$uname])) {
            $seenNames[$uname] = true;
            $unameHtml = htmlspecialchars($uname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $title_who[] = '<a href="userdetails.php?id=' . $uid . '" class="online">' . get_user_class_color($class, $unameHtml) . '</a>';
        }

        // Статистика (уникально по id)
        if ($uid > 0 && !isset($seenIds[$uid])) {
            $seenIds[$uid] = true;

            if ($class >= UC_MODERATOR) {
                $staff++;
            } elseif ($uname === '') {
                $guests++;
            } else {
                $users++;
            }

            $total++;
        }
    }
    mysqli_free_result($result);
}

// --- ВЫВОД ---

$content  = '<table border="0" width="100%" cellspacing="0" cellpadding="3">';
$content .= '<tr><td class="embedded"><b>Последний зарегистрированный:</b> ' . $latestuser . '</td></tr>';
$content .= '<tr><td class="embedded"><hr></td></tr>';

$content .= '<tr><td class="embedded"><b>Кто здесь:</b></td></tr>';
$content .= '<tr><td class="embedded">';
if (!empty($title_who)) {
    $content .= implode(', ', $title_who);
} else {
    $content .= 'Нет пользователей онлайн за последние 5 минут.';
}
$content .= '</td></tr>';
$content .= '<tr><td class="embedded"><hr></td></tr>';

$content .= '<tr><td class="embedded"><b>В онлайн:</b></td></tr>';
$content .= '</table>';

$content .= '<table border="0" width="100%" cellspacing="0" cellpadding="3">';
$content .= '<tr>'
    . '<td class="embedded" width="1%"><img src="pic/info/admin.gif" alt="Админ"></td>'
    . '<td class="embedded" width="99%">Админы: ' . (int)$staff . '</td>'
    . '</tr>';
$content .= '<tr>'
    . '<td class="embedded" width="1%"><img src="pic/info/member.gif" alt="Пользователь"></td>'
    . '<td class="embedded" width="99%">Пользователи: ' . (int)$users . '</td>'
    . '</tr>';
$content .= '<tr>'
    . '<td class="embedded" width="1%"><img src="pic/info/guest.gif" alt="Гость"></td>'
    . '<td class="embedded" width="99%">Гости: ' . (int)$guests . '</td>'
    . '</tr>';
$content .= '<tr>'
    . '<td class="embedded" width="1%"><img src="pic/info/group.gif" alt="Всего"></td>'
    . '<td class="embedded" width="99%"><b>Всего: ' . (int)$total . '</b></td>'
    . '</tr>';
$content .= '</table>';
?>
