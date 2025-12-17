<?php

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
require_once('include/bittorrent.php');
dbconn();

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка капчи если включена
    if ($use_captcha) {
        $imagehash = $_POST['imagehash'] ?? '';
        $imagestring = $_POST['imagestring'] ?? '';
        
        if (empty($imagehash) || empty($imagestring)) {
            stderr($tracker_lang['error'] ?? 'Ошибка', 'Пожалуйста, заполните код безопасности.');
        }
        
        $b = get_row_count('captcha', 
            'WHERE imagehash = ' . sqlesc($imagehash) . 
            ' AND imagestring = ' . sqlesc($imagestring));
        
        sql_query('DELETE FROM captcha WHERE imagehash = ' . sqlesc($imagehash));
        
        if ($b == 0) {
            stderr($tracker_lang['error'] ?? 'Ошибка', 'Вы ввели неправильный код подтверждения.');
        }
    }
    
    // Проверка обязательных полей
    $required_fields = ['useremail', 'subject', 'message'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field] ?? '')) {
            stderr($tracker_lang['error'] ?? 'Ошибка', 
                'Вы не заполнили все поля формы! Вернитесь назад и попробуйте еще раз.');
        }
    }
    
    // Присваиваем переменные с фильтрацией
    $useremail = trim($_POST['useremail']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    // Валидация email
    if (!validemail($useremail)) {
        stderr($tracker_lang['error'] ?? 'Ошибка', 
            'Это не похоже на реальный email адрес.');
    }
    
    // Подготовка данных отправителя
    $ip = getip();
    $username = $CURUSER['username'] ?? 'unregged';
    $userid = $CURUSER['id'] ?? 0;
    
    // Подготовка текста письма
    $body = <<<EOD
Сообщение через обратную связь на {$website_name}:

--------------------------------

{$message}

--------------------------------

IP Адрес: {$ip}
Имя пользователя: {$username}
Код пользователя: {$userid}
EOD;
    
    // Отправка письма
    stdhead();
    $email_subject = 'Обратная связь на ' . $website_name . ' - ' . $subject;
    
    if (sent_mail($admin_email, $useremail, $useremail, $email_subject, $body, false)) {
        stdmsg('Успешно', 'Ваше сообщение отправлено администрации.', 'success');
    } else {
        stdmsg('Ошибка', 
            'Ваше сообщение <b>НЕ</b> было отправлено администрации, из-за непредвиденной ошибки сервера.', 
            'error');
    }
    
    stdfoot();
    
} else {
    // Показать форму
    stdhead('Связаться с нами');
    
    $hash = '';
    if ($use_captcha) {
        include_once("include/captcha.php");
        $hash = create_captcha();
    }
    
    // Защита от XSS для вывода hash
    $hash_escaped = htmlspecialchars((string)$hash, ENT_QUOTES, 'UTF-8');
?>
<form method="post" name="contactus" action="contactus.php" 
      onsubmit="document.contactus.cbutton.value='Пожалуйста подождите ...';
                document.contactus.cbutton.disabled=true; return true;">
<input type="hidden" name="do" value="process">
<table class="main" border="1" cellspacing="0" cellpadding="5" width="100%">
    <tr>
        <td align="left" class="colhead" colspan="2">
            Связаться с нами
        </td>
    </tr>
    <tr>
        <td align="right" width="20%" valign="top">
            <b>Ваш Email:</b>
        </td>
        <td align="left" width="80%" valign="top">
            <input type="email" name="useremail" value="" size="30" required>
        </td>
    </tr>
    <tr>
        <td align="right" width="20%" valign="top">
            <b>Тема:</b>
        </td>
        <td align="left" width="80%" valign="top">
            <input type="text" name="subject" value="" size="30" required maxlength="100">
        </td>
    </tr>    
    <tr>
        <td align="right" width="20%" valign="top">
            <b>Сообщение:</b>
        </td>
        <td align="left" width="80%" valign="top">
            <textarea name="message" cols="100" rows="10" required></textarea>
        </td>
    </tr>
<?php if ($use_captcha) { ?>
    <tr>
        <td align="right" width="20%" valign="top">
            <b>Код безопасности:</b>
        </td>
        <td align="left" width="80%" valign="top">
            <input type="text" name="imagestring" value="" size="30" required autocomplete="off">
            <p>Пожалуйста, введите текст изображенный на картинке внизу.<br />
            Этот процесс предотвращает автоматическую регистрацию.</p>
            <img id="captcha" src="captcha.php?imagehash=<?=$hash_escaped; ?>" alt="Captcha" 
                 ondblclick="this.src='captcha.php?imagehash=<?=$hash_escaped; ?>&amp;' + Math.random();" /><br />
            <span style="color: red;">Код чувствителен к регистру</span><br />
            Кликните два раза на картинке, чтобы обновить картинку.
            <input type="hidden" name="imagehash" value="<?=$hash_escaped; ?>" />
        </td>
    </tr>
<?php } ?>
    <tr>
        <td colspan="2" align="center">
            <input type="submit" value="Отправить" name="cbutton">
            <input type="reset" value="Сбросить">
        </td>
    </tr>
</table>
</form>
<?php
    stdfoot();
}