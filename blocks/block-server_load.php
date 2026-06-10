<?php

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $tracker_lang, $pic_base_url;

$blocktitle = 'Нагрузка сервера';

/**
 * Активные пользователи в peers.
 * Так быстрее и правильнее, чем SELECT userid ... GROUP BY + mysqli_num_rows().
 */
$res = sql_query("SELECT COUNT(DISTINCT userid) AS connected FROM peers");
$row = mysqli_fetch_assoc($res);
$connected = (int)($row['connected'] ?? 0);

$avgload = (float)get_server_load();

/**
 * Для Unix LA обычно пересчитывали в проценты через *4.
 * На Windows оставляем как есть.
 */
if (strtolower(substr(PHP_OS, 0, 3)) !== 'win') {
    $percent = $avgload * 4;
} else {
    $percent = $avgload;
}

$percent = (int)round($percent);

/**
 * Чтобы полоска не вылезала за таблицу.
 */
$barPercent = max(0, min(100, $percent));

if ($barPercent <= 50) {
    $pic = 'loadbargreen.gif';
} elseif ($barPercent <= 70) {
    $pic = 'loadbaryellow.gif';
} else {
    $pic = 'loadbarred.gif';
}

$title = 'Загрузка: ' . $percent . '%, нагрузка (LA): ' . htmlspecialchars((string)$avgload, ENT_QUOTES, 'UTF-8');

$content .= "
<table width='100%' border='0' cellspacing='1' cellpadding='3' class='blok'>
    <tr>
        <td class='rowhead' align='left' width='35%'>
            Активных подключений
        </td>
        <td class='row1' align='left'>
            <b>" . number_format($connected) . "</b> пользователей
        </td>
    </tr>
    <tr>
        <td class='rowhead' align='left'>
            Нагрузка сервера
        </td>
        <td class='row1' align='left' title='{$title}'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td width='45' align='center'>
                        <b>{$percent}%</b>
                    </td>
                    <td align='left'>
                        <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                            <tr>
                                <td style='padding:0; height:15px; background:#fdefce; border:1px solid #e0d1bc;'>
                                    <img
                                        src='pic/{$pic}'
                                        width='{$barPercent}%'
                                        height='15'
                                        alt='{$title}'
                                        title='{$title}'
                                        style='display:block;'
                                    >
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>";
?>