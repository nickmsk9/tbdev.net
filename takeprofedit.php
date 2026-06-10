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

function bark($msg) {
	stderr("��������� ������", $msg);
}

dbconn();

loggedinorreturn();

$email = trim(strtolower((string)($_POST['email'] ?? $CURUSER['email'] ?? '')));
$oldpassword = (string)($_POST['oldpassword'] ?? '');
$chpassword = (string)($_POST['chpassword'] ?? '');
$passagain = (string)($_POST['passagain'] ?? '');

// $set = array();

$updateset = array();
$changedemail = 0;

if ($chpassword != "") {
	if (strlen($chpassword) > 40)
		bark("��������, ��� ������ ������� ������� (�������� 40 ��������)");
	if ($chpassword != $passagain)
		bark("������ �� ���������. ���������� ��� ���.");
    if ($CURUSER["passhash"] != md5($CURUSER["secret"] . $oldpassword . $CURUSER["secret"]))
            bark("�� ����� ������������ ������ ������.");

	$sec = mksecret();
	$passhash = md5($sec . $chpassword . $sec);
	$updateset[] = "secret = " . sqlesc($sec);
	$updateset[] = "passhash = " . sqlesc($passhash);
	logincookie($CURUSER["id"], $passhash);
}

if ($email != $CURUSER["email"]) {
	if (!validemail($email))
		bark("��� �� ������ �� ��������� E-Mail.");
  $r = sql_query("SELECT id FROM users WHERE email=" . sqlesc($email)) or sqlerr(__FILE__, __LINE__);
	if (mysqli_num_rows($r) > 0)
		bark("���� e-mail ����� ��� ������������ ����� �� ������������� �������. (<b>$email</b>)");
	$changedemail = 1;
}

$acceptpms = (string)($_POST["acceptpms"] ?? $CURUSER["acceptpms"] ?? 'yes');
if (!in_array($acceptpms, ['yes', 'friends', 'no'], true)) {
    $acceptpms = 'yes';
}
$deletepms = (isset($_POST["deletepms"]) && $_POST["deletepms"] != "") ? "yes" : "no";
$savepms = (isset($_POST["savepms"]) && $_POST["savepms"] != "") ? "yes" : "no";
$pmnotif = isset($_POST["pmnotif"]) ? $_POST["pmnotif"] : '';
$emailnotif = isset($_POST["emailnotif"]) ? $_POST["emailnotif"] : '';
$notifs = ($pmnotif == 'yes' ? "[pm]" : "");
$notifs .= ($emailnotif == 'yes' ? "[email]" : "");
$r = sql_query("SELECT id FROM categories") or sqlerr(__FILE__, __LINE__);
$rows = mysqli_num_rows($r);

for ($i = 0; $i < $rows; ++$i)
{
    $a = mysqli_fetch_assoc($r);
    $cat_key = "cat" . $a['id'];
    
    // Проверяем существование ключа в массиве $_POST
    if (isset($_POST[$cat_key]) && $_POST[$cat_key] == 'yes') {
        $notifs .= "[cat" . $a['id'] . "]";
    }
}
// Безопасное получение аватара с проверкой существования ключа
$avatar = isset($_POST["avatar"]) ? trim($_POST["avatar"]) : '';

// Проверка удаленного аватара
if ($avatar) {
    // ОБРЕЗАЕМ URL до максимальной длины (обычно 255 символов для VARCHAR)
    $max_avatar_length = 200; // Оставляем запас
    if (strlen($avatar) > $max_avatar_length) {
        $avatar = substr($avatar, 0, $max_avatar_length);
    }
    
    // Проверяем формат URL аватара
    if (!preg_match('#^((http|https|ftp)://[a-zA-Z0-9\-]+?\.([a-zA-Z0-9\-]+\.)*[a-zA-Z]+(:[0-9]+)*/.*?\.(gif|jpg|jpeg|png|webp))$#is', $avatar)) {
        stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['avatar_adress_invalid'] ?? 'Неверный адрес аватара');
    }
    
    // Проверяем размеры изображения
    if(!(list($width, $height) = @getimagesize($avatar))) {
        // Добавляем @ чтобы подавить предупреждения
        // Пытаемся получить другими способами или используем cURL
        $image_info = @get_headers($avatar, 1);
        if (strpos($image_info[0] ?? '', '200') === false) {
            stderr($tracker_lang['error'] ?? 'Ошибка', $tracker_lang['avatar_adress_invalid'] ?? 'Неверный адрес аватара');
        }
    }
    
    if (isset($width) && isset($height)) {
        if ($width > ($avatar_max_width ?? 150) || $height > ($avatar_max_height ?? 150)) {
            stderr(
                $tracker_lang['error'] ?? 'Ошибка', 
                sprintf(
                    $tracker_lang['avatar_is_too_big'] ?? 'Аватар слишком большой (макс. %dx%d)', 
                    $avatar_max_width ?? 150, 
                    $avatar_max_height ?? 150
                )
            );
        }
    }
    
    // Если все проверки пройдены, добавляем в updateset
    $updateset[] = "avatar = " . sqlesc($avatar);
} else {
    // Если аватар пустой, можно установить пустую строку
    $updateset[] = "avatar = ''";
}

// Безопасное получение других полей
$avatars = (isset($_POST["avatars"]) && $_POST["avatars"] != "") ? "yes" : "no";
$updateset[] = "avatars = " . sqlesc($avatars);

$parked = (string)($_POST["parked"] ?? $CURUSER["parked"] ?? 'no');
if (!in_array($parked, ['yes', 'no'], true)) {
    $parked = 'no';
}
$updateset[] = "parked = " . sqlesc($parked);

$gender = (string)($_POST["gender"] ?? $CURUSER["gender"] ?? '1');
if (!in_array($gender, ['1', '2', '3'], true)) {
    $gender = in_array((string)($CURUSER["gender"] ?? ''), ['1', '2', '3'], true)
        ? (string)$CURUSER["gender"]
        : '1';
}
$updateset[] = "gender = " . sqlesc($gender);

///////////////// BIRTHDAY MOD /////////////////////
$year = (int)($_POST["year"] ?? 0);
$month = (int)($_POST["month"] ?? 0);
$day = (int)($_POST["day"] ?? 0);
$birthday = (string)($CURUSER['birthday'] ?? '');
if ($year > 0 || $month > 0 || $day > 0) {
    if ($year < 1920 || !checkdate($month, $day, $year)) {
        bark("Invalid birthday.");
    }
    $birthday = sprintf('%04d-%02d-%02d', $year, $month, $day);
}
///////////////// BIRTHDAY MOD /////////////////////
$updateset[] = "birthday = " . ($birthday === '' ? 'NULL' : sqlesc($birthday));

if (isset($_POST['resetpasskey']) && $_POST['resetpasskey']) {
    $updateset[] = "passkey=''";
}

// Проверяем наличие ключа 'passkey_ip' в массиве $_POST
if (isset($_POST["passkey_ip"])) {
    $updateset[] = "passkey_ip = " . ($_POST["passkey_ip"] != "" ? sqlesc(getip()) : "''");
} else {
    // Если ключ не существует, устанавливаем пустое значение или что-то другое по умолчанию
    $updateset[] = "passkey_ip = ''";
}

// $ircnick = $_POST["ircnick"];
// $ircpass = $_POST["ircpass"];
$info = (string)($_POST["info"] ?? $CURUSER['info'] ?? '');
$theme = (string)($_POST["theme"] ?? $CURUSER['theme'] ?? '');
$country = (int)($_POST["country"] ?? $CURUSER['country'] ?? 0);
$language = (string)($_POST["language"] ?? $CURUSER['language'] ?? '');
if (!file_exists('./languages/lang_'.$language.'/lang_main.php')) {
    bark('��������� ���� � ������� �����������!');
}
$updateset[] = "language = " . sqlesc($language);
//$timezone = 0 + $_POST["timezone"];
//$dst = ($_POST["dst"] != "" ? "yes" : "no");

/*
if ($privacy != "normal" && $privacy != "low" && $privacy != "strong")
	bark("whoops");

$updateset[] = "privacy = '$privacy'";
*/

$website = unesc((string)($_POST["website"] ?? $CURUSER['website'] ?? ''));
$updateset[] = "website = " . sqlesc(htmlspecialchars_uni($website));

$updateset[] = "torrentsperpage = " . max(0, min(100, intval($_POST["torrentsperpage"] ?? $CURUSER['torrentsperpage'] ?? 0)));
$updateset[] = "topicsperpage = " . max(0, min(100, intval($_POST["topicsperpage"] ?? $CURUSER['topicsperpage'] ?? 0)));
$updateset[] = "postsperpage = " . max(0, min(100, intval($_POST["postsperpage"] ?? $CURUSER['postsperpage'] ?? 0)));

if (is_theme($theme))
	$updateset[] = "theme = ".sqlesc($theme);
if ($country === 0 || get_row_count('countries', 'WHERE id = ' . $country) === 1)
	$updateset[] = "country = $country";

//$updateset[] = "timezone = $timezone";
//$updateset[] = "dst = '$dst'";
$updateset[] = "info = " . sqlesc($info);
$updateset[] = "acceptpms = " . sqlesc($acceptpms);
$updateset[] = "deletepms = '$deletepms'";
$updateset[] = "savepms = '$savepms'";
$updateset[] = "notifs = '$notifs'";
$updateset[] = "avatar = " . sqlesc($avatar);
$updateset[] = "avatars = '$avatars'";

/* ****** */

$urladd = "";

if ($changedemail) {
	$sec = mksecret();
	$hash = md5($sec . $email . $sec);
	$obemail = urlencode($email);
	$updateset[] = "editsecret = " . sqlesc($sec);
	$thishost = $_SERVER["HTTP_HOST"];
	$thisdomain = preg_replace('/^www\./is', "", $thishost);
	$body = <<<EOD
You have requested that your user profile (username {$CURUSER["username"]})
on $thisdomain should be updated with this email address ($email) as
user contact.

If you did not do this, please ignore this email. The person who entered your
email address had the IP address {$_SERVER["REMOTE_ADDR"]}. Please do not reply.

To complete the update of your user profile, please follow this link:

https://$thishost/confirmemail.php?id={$CURUSER["id"]}&hash=$hash&email=$obemail

If you have AOL browser, please click the following link:
<a href="https://$thishost/confirmemail.php?id={$CURUSER["id"]}&amp;hash=$hash&amp;email=$obemail">https://$thishost/confirmemail.php?id={$CURUSER["id"]}&amp;hash=$hash&amp;email=$obemail</a>

Your new email address will appear in your profile after you do this. Otherwise
your profile will remain unchanged.
EOD;

	sent_mail($email, $SITENAME, $SITEEMAIL, "��������� �������� ������� �� $thisdomain", $body, false);
//	mail($email, "$thisdomain profile change confirmation", $body, "From: $SITEEMAIL");
	$urladd .= "&mailsent=1";
}

sql_query("UPDATE users SET " . implode(",", $updateset) . " WHERE id = " . $CURUSER["id"]) or sqlerr(__FILE__,__LINE__);

header("Location: $DEFAULTBASEURL/my.php?edited=1" . $urladd);

?>
