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

require_once("include/bittorrent.php");
dbconn(false);
loggedinorreturn();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header("Content-Type: text/html; charset=" . ($tracker_lang['language_charset'] ?? 'utf-8'));
    
    if (empty($_POST["bonus_id"])) {
        stdmsg('Ошибка', "Не выбрано никакое действие!");
        die();
    }
    
    $id = (int)$_POST["bonus_id"];
    if (!is_valid_id($id)) {
        stdmsg('Ошибка', 'Доступ запрещен!');
        die();
    }
    
    $res = sql_query("SELECT * FROM bonus WHERE id = $id") or sqlerr(__FILE__, __LINE__);
    $arr = mysqli_fetch_assoc($res);
    
    if (!$arr) {
        stdmsg('Ошибка', 'Бонус не найден!');
        die();
    }
    
    $points = (int)$arr["points"];
    $type = $arr["type"];
    $bonus_name = htmlspecialchars($arr["name"]);
    
    if ($CURUSER["bonus"] < $points) {
        stdmsg('Ошибка', "У вас недостаточно бонусных очков!");
        die();
    }
    
    switch ($type) {
        case "traffic":
            $traffic = (int)$arr["quanity"];
            if (!sql_query("UPDATE users SET bonus = bonus - $points, uploaded = uploaded + $traffic WHERE id = " . sqlesc($CURUSER["id"]))) {
                stdmsg('Ошибка', "Не удалось выполнить запрос!");
                die();
            }
            $traffic_mb = ceil($traffic / 1048576);
            stdmsg('Успешно', "Вы успешно обменяли {$points} бонусных очков на {$traffic_mb} МБ трафика!");
            break;
            
        case "invite":
            $invites = (int)$arr["quanity"];
            if (!sql_query("UPDATE users SET bonus = bonus - $points, invites = invites + $invites WHERE id = " . sqlesc($CURUSER["id"]))) {
                stdmsg('Ошибка', "Не удалось выполнить запрос!");
                die();
            }
            stdmsg('Успешно', "Вы успешно обменяли {$points} бонусных очков на {$invites} приглашение(й)!");
            break;
            
        default:
            stdmsg('Ошибка', "Неизвестный тип бонуса!");
    }
    
} else {
    // Основная страница
    $page_title = 'Мой бонусный счет';
    stdhead($page_title);
?>
<script language="javascript" type="text/javascript">
function sendBonusRequest() {
    var frm = document.mybonus;
    var bonus_id = '';
    var radio_selected = false;
    
    // Ищем выбранный радиобаттон
    var radios = document.getElementsByName('bonus_id');
    for (var i = 0; i < radios.length; i++) {
        if (radios[i].checked && !radios[i].disabled) {
            bonus_id = radios[i].value;
            radio_selected = true;
            break;
        }
    }
    
    if (!radio_selected) {
        alert('Пожалуйста, выберите бонус для обмена!');
        return false;
    }
    
    if (!bonus_id) {
        alert('Пожалуйста, выберите бонус для обмена!');
        return false;
    }
    
    // Скрываем сообщение об успехе/ошибке если оно было
    var ajaxDiv = document.getElementById('ajax');
    if (ajaxDiv) {
        ajaxDiv.innerHTML = '<div style="text-align:center;padding:20px;"><img src="pic/loading.gif" alt="Загрузка..." /></div>';
    }
    
    // Создаем AJAX запрос
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'mybonus.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                document.getElementById('ajax').innerHTML = xhr.responseText;
                // После успешного обмена показываем кнопку возврата
                var returnBtn = '<div style="text-align:center;margin:20px 0;">' +
                               '<a href="mybonus.php" style="font-weight:bold;padding:8px 20px;background:#369;color:white;text-decoration:none;">' +
                               'Вернуться к списку бонусов</a></div>';
                document.getElementById('ajax').innerHTML += returnBtn;
            } else {
                document.getElementById('ajax').innerHTML = '<div class="alert">Ошибка при выполнении запроса</div>';
            }
        }
    };
    
    // Отправляем данные
    var params = 'bonus_id=' + encodeURIComponent(bonus_id);
    xhr.send(params);
    
    return false;
}

// Функция для отображения/скрытия описания
function toggleDescription(id) {
    var desc = document.getElementById('desc-' + id);
    if (desc.style.display == 'none' || desc.style.display == '') {
        desc.style.display = 'block';
    } else {
        desc.style.display = 'none';
    }
}
</script>

<div id="ajax">
<table class="embedded" width="95%" border="1" cellspacing="0" cellpadding="8" align="center" style="border-collapse: collapse; margin-top: 10px;">
<tr>
    <td class="colhead" colspan="4" align="center" style="font-size: 14px; padding: 10px;">
        <b>БОНУСНАЯ СИСТЕМА</b>
    </td>
</tr>

<tr>
    <td class="colhead" colspan="4" style="padding: 8px; font-size: 12px; background: #f0f0f0;">
        <b>Ваш баланс: <?=number_format($CURUSER["bonus"], 0, '', ' ');?> очков</b>
    </td>
</tr>

<tr>
    <td class="colhead" width="50%" style="padding: 8px;"><b>Бонус</b></td>
    <td class="colhead" width="20%" style="padding: 8px;"><b>Стоимость</b></td>
    <td class="colhead" width="15%" style="padding: 8px;"><b>Статус</b></td>
    <td class="colhead" width="15%" style="padding: 8px;"><b>Выбор</b></td>
</tr>

<?php
$my_points = $CURUSER["bonus"];
$res = sql_query("SELECT * FROM bonus ORDER BY points ASC, type ASC") or sqlerr(__FILE__, __LINE__);

if (mysqli_num_rows($res) == 0) {
    echo '<tr><td colspan="4" align="center" style="padding: 20px;"><i>В системе пока нет доступных бонусов</i></td></tr>';
} else {
    while ($arr = mysqli_fetch_assoc($res)) {
        $id = (int)$arr["id"];
        $bonus = htmlspecialchars($arr["name"]);
        $points = (int)$arr["points"];
        $descr = htmlspecialchars($arr["description"]);
        $type = $arr["type"];
        $quantity = (int)$arr["quanity"];
        
        // Определяем доступность
        $is_available = $my_points >= $points;
        $color = $is_available ? '#090' : '#C00';
        $status_text = $is_available ? 'Доступно' : 'Недостаточно';
        
        // Форматируем количество
        $quantity_display = '';
        if ($type == "traffic") {
            $traffic_mb = ceil($quantity / 1048576);
            $quantity_display = " ({$traffic_mb} МБ)";
        } elseif ($type == "invite") {
            $quantity_display = " ({$quantity} шт.)";
        }
        
        echo "<tr>
            <td style='padding: 8px; vertical-align: top;'>
                <b>{$bonus}{$quantity_display}</b>
                <div style='font-size: 10px; color: #666; margin-top: 3px;'>
                    <a href='javascript:void(0)' onclick=\"toggleDescription({$id})\" style='text-decoration: none; color: #369;'>
                        [подробнее]
                    </a>
                </div>
                <div id='desc-{$id}' style='display: none; font-size: 10px; color: #666; margin-top: 5px; padding: 5px; background: #f9f9f9; border: 1px dashed #ddd;'>
                    {$descr}
                </div>
            </td>
            <td align='center' style='padding: 8px; vertical-align: middle;'>
                <b>{$points}</b>
            </td>
            <td align='center' style='padding: 8px; vertical-align: middle;'>
                <font style='color: {$color}; font-weight: bold;'>{$status_text}</font>
            </td>
            <td align='center' style='padding: 8px; vertical-align: middle;'>
                <input type='radio' name='bonus_id' value='{$id}'" . ($is_available ? '' : ' disabled') . " />
            </td>
        </tr>\n";
    }
?>
<tr>
    <td colspan="4" style="padding: 12px; background: #f8f8f8;" align="center">
        <form action="mybonus.php" name="mybonus" method="post" onsubmit="return sendBonusRequest();">
            <input type="submit" value="ОБМЕНЯТЬ" style="font-weight: bold; padding: 6px 15px; cursor: pointer;" />
        </form>
    </td>
</tr>

<tr>
    <td colspan="4" style="padding: 8px; font-size: 11px; color: #666; background: #f9f9f9; border-top: 1px solid #ddd;">
        <b>Как получить бонусные очки?</b><br>
        • Сидите на раздачах<br>
        • Загружайте новые торренты<br>
        • Поддерживайте раздачи долгое время
    </td>
</tr>
<?php } ?>
</table>

<div style="text-align: center; margin: 15px 0; font-size: 11px; color: #666;">
    <a href="index.php">На главную</a> | 
    <a href="my.php">Мой профиль</a>
</div>
</div>

<?php
    stdfoot();
}
?>