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

require_once "include/bittorrent.php";

dbconn(false);
loggedinorreturn();

// -------------------- PHP 8.1: normalize CURUSER (no Undefined array key / no null deprecations)
$CURUSER = is_array($CURUSER ?? null) ? $CURUSER : [];

$CURUSER += [
    'id'             => 0,
    'username'       => '',
    'email'          => '',
    'theme'          => '',
    'country'        => 0,
    'language'       => '',
    'acceptpms'      => 'yes',      // yes|friends|no
    'parked'         => 'no',       // yes|no
    'deletepms'      => 'no',       // yes|no
    'savepms'        => 'no',       // yes|no
    'notifs'         => '',
    'avatar'         => '',
    'gender'         => '0',        // 0|1|2
    'birthday'       => '0000-00-00',
    'icq'            => '',
    'aim'            => '',
    'msn'            => '',
    'yahoo'          => '',
    'skype'          => '',
    'mirc'           => '',
    'website'        => '',
    'torrentsperpage'=> 0,
    'topicsperpage'  => 0,
    'postsperpage'   => 0,
    'avatars'        => 'yes',
    'info'           => '',
    'passkey'        => '',
    'passhash'       => '',
    'passkey_ip'     => '',
];

$CURUSER['id']      = (int)($CURUSER['id'] ?? 0);
$CURUSER['country'] = (int)($CURUSER['country'] ?? 0);

foreach ([
    'username','email','theme','language','acceptpms','parked','deletepms','savepms',
    'notifs','avatar','gender','birthday','icq','aim','msn','yahoo','skype','mirc',
    'website','info','passkey','passhash','passkey_ip'
] as $k) {
    $CURUSER[$k] = (string)($CURUSER[$k] ?? '');
}

foreach (['torrentsperpage','topicsperpage','postsperpage'] as $k) {
    $CURUSER[$k] = (int)($CURUSER[$k] ?? 0);
}

stdhead($tracker_lang['my_my']);

if (isset($_GET["edited"])) {
    print("<h1>".$tracker_lang['my_updated']."</h1>\n");
    if (isset($_GET["mailsent"])) {
        print("<h2>".$tracker_lang['my_mail_sent']."</h2>\n");
    }
} elseif (isset($_GET["emailch"])) {
    print("<h1>".$tracker_lang['my_mail_updated']."</h1>\n");
}

?>
<table border="1" cellspacing="0" cellpadding="10" align="center">
<tr>
    <td align="center" width="33%"><a href="logout.php"><b><?= $tracker_lang['logout']; ?></b></a></td>
    <td align="center" width="33%"><a href="mytorrents.php"><b><?= $tracker_lang['my_torrents']; ?></b></a></td>
    <td align="center" width="33%"><a href="friends.php"><b>Мои друзья</b></a></td>
</tr>
<tr>
<td colspan="3">

<form method="post" action="takeprofedit.php">
<table border="1" cellspacing="0" cellpadding="5">

<?php
// Themes
$themes = theme_selector($CURUSER["theme"]);

// Countries
$countries = "<option value=\"0\">---- ".$tracker_lang['my_unset']." ----</option>\n";
$ct_r = sql_query("SELECT id, name FROM countries ORDER BY name ASC") or sqlerr(__FILE__, __LINE__);
while ($ct_a = mysqli_fetch_assoc($ct_r)) {
    $cid = (int)($ct_a['id'] ?? 0);
    $cname = (string)($ct_a['name'] ?? '');
    $countries .= "<option value=\"{$cid}\"" . ($CURUSER["country"] === $cid ? " selected" : "") . ">{$cname}</option>\n";
}

// Languages
$lang = [];
$dir = @opendir('languages');
if ($dir !== false) {
    while (($file = readdir($dir)) !== false) {
        if (preg_match('#^lang_#i', $file) && !is_file('languages/' . $file) && !is_link('languages/' . $file)) {
            $filename = trim(str_replace("lang_", "", $file));
            $displayname = preg_replace("/^(.*?)_(.*)$/", "\\1 [ \\2 ]", $filename);
            $displayname = preg_replace("/\[(.*?)_(.*)\]/", "[ \\1 - \\2 ]", $displayname);
            $lang[$displayname] = $filename;
        }
    }
    closedir($dir);
    asort($lang);
    reset($lang);
}

$lang_select = '<select name="language">';
foreach ($lang as $displayname => $filename) {
    $selected = (strtolower($CURUSER["language"]) === strtolower($filename)) ? ' selected="selected"' : '';
    $lang_select .= '<option value="' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' .
        htmlspecialchars(ucwords((string)$displayname), ENT_QUOTES, 'UTF-8') . '</option>';
}
$lang_select .= '</select>';

// helper (оставил как было — вдруг используется дальше)
function format_tz($a)
{
    $h = floor($a);
    $m = ($a - floor($a)) * 60;
    return ($a >= 0 ? "+" : "-") . (strlen((string)abs((int)$h)) > 1 ? "" : "0") . abs((int)$h) .
        ":" . ($m == 0 ? "00" : $m);
}

// PM settings
tr(
    $tracker_lang['my_allow_pm_from'],
    "<input type=\"radio\" name=\"acceptpms\"" . ($CURUSER["acceptpms"] === "yes" ? " checked" : "") . " value=\"yes\">Все (неограниченное общение)
     <br /><input type=\"radio\" name=\"acceptpms\"" . ($CURUSER["acceptpms"] === "friends" ? " checked" : "") . " value=\"friends\">Только друзья
     <br /><input type=\"radio\" name=\"acceptpms\"" . ($CURUSER["acceptpms"] === "no" ? " checked" : "") . " value=\"no\">Запретить общение",
    1
);

tr(
    $tracker_lang['my_parked'],
    "<input type=\"radio\" name=\"parked\"" . ($CURUSER["parked"] === "yes" ? " checked" : "") . " value=\"yes\">".$tracker_lang['yes']."
     <input type=\"radio\" name=\"parked\"" . ($CURUSER["parked"] === "no" ? " checked" : "") . " value=\"no\">".$tracker_lang['no']."
     <br /><font class=\"small_text\">".$tracker_lang['my_you_can_park'].".</font>",
    1
);

tr($tracker_lang['my_delete_after_reply'], "<input type=\"checkbox\" name=\"deletepms\"" . ($CURUSER["deletepms"] === "yes" ? " checked" : "") . ">", 1);
tr($tracker_lang['my_sentbox'], "<input type=\"checkbox\" name=\"savepms\"" . ($CURUSER["savepms"] === "yes" ? " checked" : "") . ">", 1);

// Default browse categories + notif settings
$notifs = $CURUSER['notifs']; // уже строка
$r = genrelist();

$categories = '';
if (is_array($r) && count($r) > 0) {
    $categories = "<table><tr>\n";
    $i = 0;
    foreach ($r as $a) {
        $id = (int)($a['id'] ?? 0);
        $name = (string)($a['name'] ?? '');
        $categories .= ($i && $i % 2 === 0) ? "</tr><tr>" : "";
        $checked = (str_contains($notifs, "[cat{$id}]")) ? " checked" : "";
        $categories .= "<td class=\"bottom\" style=\"padding-right: 5px\"><input name=\"cat{$id}\" type=\"checkbox\"{$checked} value=\"yes\">&nbsp;" .
            htmlspecialchars_uni($name) . "</td>\n";
        ++$i;
    }
    $categories .= "</tr></table>\n";
} else {
    $categories = "<i>".$tracker_lang['my_unset']."</i>";
}

tr(
    $tracker_lang['my_email_notify'],
    "<input type=\"checkbox\" name=\"pmnotif\"" . (str_contains($notifs, "[pm]") ? " checked" : "") . " value=\"yes\"> Уведомлять меня при получении ЛС<br />\n" .
    "<input type=\"checkbox\" name=\"emailnotif\"" . (str_contains($notifs, "[email]") ? " checked" : "") . " value=\"yes\"> Уведомлять меня при появлении новых торрентов <br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; в отслеживаемых разделах.\n",
    1
);

tr($tracker_lang['my_default_browse'], $categories, 1);
tr($tracker_lang['my_style'], $themes, 1);
tr($tracker_lang['my_country'], "<select name=\"country\">\n{$countries}\n</select>", 1);
tr($tracker_lang['my_language'], $lang_select, 1);

// Avatar (ВАЖНО: приводим к строке, чтобы не прилетал deprecated в preg_replace внутри htmlspecialchars_uni/init.php)
$avatarVal = htmlspecialchars_uni((string)$CURUSER["avatar"]);
tr(
    $tracker_lang['my_avatar_url'],
    "<input name=\"avatar\" size=\"50\" value=\"{$avatarVal}\"><br />\n" .
    sprintf($tracker_lang['max_avatar_size'], $avatar_max_width, $avatar_max_height),
    1
);

// Gender
tr(
    $tracker_lang['my_gender'],
    "<input type=\"radio\" name=\"gender\"" . ($CURUSER["gender"] === "1" ? " checked" : "") . " value=\"1\">".$tracker_lang['my_gender_male']."
     <input type=\"radio\" name=\"gender\"" . ($CURUSER["gender"] === "2" ? " checked" : "") . " value=\"2\">".$tracker_lang['my_gender_female'],
    1
);

///////////////// BIRTHDAY MOD /////////////////////
$birthdayRaw = $CURUSER['birthday'];
$birthdayRaw = ($birthdayRaw === '' ? '0000-00-00' : $birthdayRaw);

// Без strtotime(null) и без кривых дат
$year1 = '0000'; $month1 = '00'; $day1 = '00';
if ($birthdayRaw !== '0000-00-00') {
    $dt = date_create($birthdayRaw);
    if ($dt instanceof DateTime) {
        $year1  = $dt->format('Y');
        $month1 = $dt->format('m');
        $day1   = $dt->format('d');
    } else {
        $birthdayRaw = '0000-00-00';
    }
}

if ($birthdayRaw === '0000-00-00') {
    $year = "<select name=\"year\"><option value=\"0000\">".$tracker_lang['my_year']."</option>\n";
    $i = 1920;
    $maxYear = (int)date('Y') - 13;
    while ($i <= $maxYear) {
        $year .= "<option value=\"{$i}\">{$i}</option>\n";
        $i++;
    }
    $year .= "</select>\n";

    $birthmonths = [
        "01" => $tracker_lang['my_months_january'],
        "02" => $tracker_lang['my_months_february'],
        "03" => $tracker_lang['my_months_march'],
        "04" => $tracker_lang['my_months_april'],
        "05" => $tracker_lang['my_months_may'],
        "06" => $tracker_lang['my_months_june'],
        "07" => $tracker_lang['my_months_jule'],
        "08" => $tracker_lang['my_months_august'],
        "09" => $tracker_lang['my_months_september'],
        "10" => $tracker_lang['my_months_october'],
        "11" => $tracker_lang['my_months_november'],
        "12" => $tracker_lang['my_months_december'],
    ];

    $month = "<select name=\"month\"><option value=\"00\">".$tracker_lang['my_month']."</option>\n";
    foreach ($birthmonths as $month_no => $show_month) {
        $month .= "<option value=\"{$month_no}\">{$show_month}</option>\n";
    }
    $month .= "</select>\n";

    $day = "<select name=\"day\"><option value=\"00\">".$tracker_lang['my_day']."</option>\n";
    for ($d = 1; $d <= 31; $d++) {
        $dd = str_pad((string)$d, 2, '0', STR_PAD_LEFT);
        $day .= "<option value=\"{$dd}\">{$dd}</option>\n";
    }
    $day .= "</select>\n";

    tr($tracker_lang['my_birthdate'], $year . $month . $day, 1);
} else {
    // показываем и фиксируем в hidden
    $safeY = htmlspecialchars($year1, ENT_QUOTES, 'UTF-8');
    $safeM = htmlspecialchars($month1, ENT_QUOTES, 'UTF-8');
    $safeD = htmlspecialchars($day1, ENT_QUOTES, 'UTF-8');

    tr(
        $tracker_lang['my_birthdate'],
        "<b><input type=\"hidden\" name=\"year\" value=\"{$safeY}\">{$safeY}
        <input type=\"hidden\" name=\"month\" value=\"{$safeM}\">.{$safeM}
        <input type=\"hidden\" name=\"day\" value=\"{$safeD}\">.{$safeD}</b>",
        1
    );
}
///////////////// BIRTHDAY MOD /////////////////////

print("<tr><td class=\"tablecat\" colspan=\"2\" align=\"left\"><b>".$tracker_lang['my_contact']."</b></td></tr>\n");

// Контакты (все value делаем безопасными строками)
$icq   = htmlspecialchars_uni($CURUSER["icq"]);
$aim   = htmlspecialchars_uni($CURUSER["aim"]);
$msn   = htmlspecialchars_uni($CURUSER["msn"]);
$yahoo = htmlspecialchars_uni($CURUSER["yahoo"]);
$skype = htmlspecialchars_uni($CURUSER["skype"]);
$mirc  = htmlspecialchars_uni($CURUSER["mirc"]);

tr(
    " ",
    "<table cellspacing=\"3\" cellpadding=\"0\" width=\"100%\" border=\"0\">
        <tr>
            <td style=\"font-size: 11px; font-family: verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif\" colspan=\"2\">
                ".$tracker_lang['my_contact_descr']."
            </td>
        </tr>
        <tr>
            <td style=\"font-size: 11px; font-family: verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif\">
                ".$tracker_lang['my_contact_icq']."<br />
                <img alt=\"\" src=\"pic/contact/icq.gif\" width=\"17\" height=\"17\">
                <input maxlength=\"30\" size=\"25\" name=\"icq\" value=\"{$icq}\">
            </td>
            <td style=\"font-size: 11px; font-family: verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif\">
                ".$tracker_lang['my_contact_aim']."<br />
                <img alt=\"\" src=\"pic/contact/aim.gif\" width=\"17\" height=\"17\">
                <input maxlength=\"30\" size=\"25\" name=\"aim\" value=\"{$aim}\">
            </td>
        </tr>
        <tr>
            <td style=\"font-size: 11px; font-family: verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif\">
                ".$tracker_lang['my_contact_msn']."<br />
                <img alt=\"\" src=\"pic/contact/msn.gif\" width=\"17\" height=\"17\">
                <input maxlength=\"50\" size=\"25\" name=\"msn\" value=\"{$msn}\">
            </td>
            <td style=\"font-size: 11px; font-family: verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif\">
                ".$tracker_lang['my_contact_yahoo']."<br />
                <img alt=\"\" src=\"pic/contact/yahoo.gif\" width=\"17\" height=\"17\">
                <input maxlength=\"30\" size=\"25\" name=\"yahoo\" value=\"{$yahoo}\">
            </td>
        </tr>
        <tr>
            <td style=\"font-size: 11px; font-family: verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif\">
                ".$tracker_lang['my_contact_skype']."<br />
                <img alt=\"\" src=\"pic/contact/skype.gif\" width=\"17\" height=\"17\">
                <input maxlength=\"32\" size=\"25\" name=\"skype\" value=\"{$skype}\">
            </td>
            <td style=\"font-size: 11px; font-family: verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif\">
                ".$tracker_lang['my_contact_mirc']."<br />
                <img alt=\"\" src=\"pic/contact/mirc.gif\" width=\"17\" height=\"17\">
                <input maxlength=\"30\" size=\"25\" name=\"mirc\" value=\"{$mirc}\">
            </td>
        </tr>
    </table>",
    1
);

// Веб-сайт
tr($tracker_lang['my_website'], "<input type=\"text\" name=\"website\" size=\"50\" value=\"" . htmlspecialchars_uni($CURUSER["website"]) . "\" />", 1);

// Торренты/темы/сообщения на странице
tr($tracker_lang['my_torrents_per_page'], "<input type=\"text\" size=\"10\" name=\"torrentsperpage\" value=\"" . (int)$CURUSER['torrentsperpage'] . "\"> (0 = использовать настройки по умолчанию)", 1);
tr($tracker_lang['my_topics_per_page'], "<input type=\"text\" size=\"10\" name=\"topicsperpage\" value=\"" . (int)$CURUSER['topicsperpage'] . "\"> (0 = использовать настройки по умолчанию)", 1);
tr($tracker_lang['my_messages_per_page'], "<input type=\"text\" size=\"10\" name=\"postsperpage\" value=\"" . (int)$CURUSER['postsperpage'] . "\"> (0 = использовать настройки по умолчанию)", 1);

// Показывать аватары
tr(
    $tracker_lang['my_show_avatars'],
    "<input type=\"checkbox\" name=\"avatars\"" . (($CURUSER["avatars"] ?? '') === "yes" ? " checked" : "") . "> (отображаются в профиле пользователя и в комментариях под аватаркой)",
    1
);

// Информация о себе
tr(
    $tracker_lang['my_info'],
    "<textarea name=\"info\" cols=\"50\" rows=\"4\">" . htmlspecialchars_uni($CURUSER["info"]) . "</textarea><br />Разрешено использовать только следующие теги. Полный список <a href=\"tags.php\" target=\"_new\">BB кодов</a>.",
    1
);

// Пользовательская панель
$user_id = (int)($CURUSER["id"] ?? 0);
$image_url = "torrentbar/bar.php?id=" . $user_id;

tr(
    $tracker_lang['my_userbar'],
    "<img src=\"" . htmlspecialchars($image_url, ENT_QUOTES, 'UTF-8') . "\" alt=\"Userbar\" border=\"0\" /><br />" .
    $tracker_lang['my_userbar_descr'] . ":<br />" .
    "<input type=\"text\" size=\"65\" value=\"[url=".$DEFAULTBASEURL."][img]".$DEFAULTBASEURL."/" . $image_url . "[/img][/url]\" readonly />",
    1
);

// Email
tr($tracker_lang['my_mail'], "<input type=\"text\" name=\"email\" size=\"50\" value=\"" . htmlspecialchars_uni($CURUSER["email"]) . "\" />", 1);

print("<tr><td colspan=\"2\" align=\"left\"><b>Внимание:</b> если вы измените ваш Email адрес, то вы должны будете подтвердить свой аккаунт снова через Email-письмо. Если вы не получите письмо, то Email адрес не будет изменён.</td></tr>\n");

// Сброс пасскей
tr("Сбросить пасскей", "<input type=\"checkbox\" name=\"resetpasskey\" value=\"1\" /> (не забудьте обновить все свои раздачи если сбросите пасскей!)", 1);

// Генерация пасскея если его нет/битый (32 символа)
if (strlen($CURUSER['passkey']) !== 32) {
    $CURUSER['passkey'] = md5(($CURUSER['username'] ?? '') . get_date_time() . ($CURUSER['passhash'] ?? ''));
    $userId = (int)($CURUSER['id'] ?? 0);

    if ($userId > 0) {
        sql_query("UPDATE users SET passkey=" . sqlesc($CURUSER['passkey']) . " WHERE id={$userId}") or sqlerr(__FILE__, __LINE__);
    }
}


// Пасскей пользователя
tr("Ваш пасскей", "<b>" . htmlspecialchars_uni($CURUSER['passkey']) . "</b>", 1);

// Привязка IP к пасскею
$ip_checked = ($CURUSER["passkey_ip"] !== "") ? " checked" : "";
tr(
    "Привязка IP к пасскею",
    "<input type=\"checkbox\" name=\"passkey_ip\"{$ip_checked}> Привязка вашего пасскея к одному или нескольким разрешённым IP адресам. Если ваш IP изменится - то скачивание будет запрещено.<br />Ваш текущий IP адрес: <b>" . htmlspecialchars(getip(), ENT_QUOTES, 'UTF-8') . "</b>",
    1
);

// Пароли
tr("Старый пароль", "<input type=\"password\" name=\"oldpassword\" size=\"50\" />", 1);
tr("Новый пароль", "<input type=\"password\" name=\"chpassword\" size=\"50\" />", 1);
tr("Повторите пароль", "<input type=\"password\" name=\"passagain\" size=\"50\" />", 1);

// Privacy (оставлено как было, но безопасно)
function priv($name, $descr) {
    global $CURUSER;
    $checked = ((string)($CURUSER["privacy"] ?? '') === (string)$name) ? " checked=\"checked\"" : "";
    return "<input type=\"radio\" name=\"privacy\" value=\"" . htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8') . "\"{$checked} /> " .
        htmlspecialchars((string)$descr, ENT_QUOTES, 'UTF-8');
}
// tr("Privacy level",  priv("normal", "Normal") . " " . priv("low", "Low (email address will be shown)") . " " . priv("strong", "Strong (no info will be made available)"), 1);
?>

<tr>
    <td colspan="2" align="center">
        <input type="submit" value="Сохранить изменения" style="height: 25px">
        <input type="reset" value="Сбросить изменения" style="height: 25px">
    </td>
</tr>

</table>
</form>

</td>
</tr>
</table>

<?php
print("<p><a href='users.php'><b>Поиск пользователей/список участников</b></a></p>");
stdfoot();
