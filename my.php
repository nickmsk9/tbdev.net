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

stdhead($tracker_lang['my_my']);

if (isset($_GET["edited"])) {
	print("<h1>".$tracker_lang['my_updated']."</h1>\n");
	if (isset($_GET["mailsent"]))
		print("<h2>".$tracker_lang['my_mail_sent']."</h2>\n");
} elseif (isset($_GET["emailch"]))
	print("<h1>".$tracker_lang['my_mail_updated']."</h1>\n");
/*else
	print("<h1>����� ����������, <a href=userdetails.php?id=$CURUSER[id]>$CURUSER[username]</a>!</h1>\n");*/

?>
<table border="1" cellspacing="0" cellpadding="10" align="center">
<tr>
<td align="center" width="33%"><a href="logout.php"><b><?=$tracker_lang['logout'];?></b></a></td>
<td align="center" width="33%"><a href="mytorrents.php"><b><?=$tracker_lang['my_torrents'];?></b></a></td>
<td align="center" width="33%"><a href="friends.php"><b>��� ������ �������������</b></a></td>
</tr>
<tr>
<td colspan="3">
<form method="post" action="takeprofedit.php">
<table border="1" cellspacing=0 cellpadding="5">
<?

/***********************

$res = sql_query("SELECT COUNT(*) FROM ratings WHERE user=" . $CURUSER["id"]);
$row = mysqli_fetch_assoc($res);
tr("Ratings submitted", $row[0]);

$res = sql_query("SELECT COUNT(*) FROM comments WHERE user=" . $CURUSER["id"]);
$row = mysqli_fetch_assoc($res);
tr("Written comments", $row[0]);

****************/

$themes = theme_selector($CURUSER["theme"]);

$countries = "<option value=0>---- ".$tracker_lang['my_unset']." ----</option>\n";
$ct_r = sql_query("SELECT id, name FROM countries ORDER BY name ASC") or sqlerr(__FILE__,__LINE__);
while ($ct_a = mysqli_fetch_assoc($ct_r))
  $countries .= "<option value=$ct_a[id]" . ($CURUSER["country"] == $ct_a['id'] ? " selected" : "") . ">$ct_a[name]</option>\n";

$dir = opendir('languages');
        $lang = array();
        while ( $file = readdir($dir) ) {
                if (preg_match('#^lang_#i', $file) && !is_file($dir . '/' . $file) && !is_link($dir . '/' . $file)) {
                        $filename = trim(str_replace("lang_","", $file));
                        $displayname = preg_replace("/^(.*?)_(.*)$/", "\\1 [ \\2 ]", $filename);
                        $displayname = preg_replace("/\[(.*?)_(.*)\]/", "[ \\1 - \\2 ]", $displayname);
                        $lang[$displayname] = $filename;
                }
        }
        closedir($dir);
        @asort($lang);
        @reset($lang);

        $lang_select = '<select name="language">';

foreach ($lang as $displayname => $filename) {
    $selected = (strtolower($CURUSER["language"]) == strtolower($filename)) ? ' selected="selected"' : '';
    $lang_select .= '<option value="' . $filename . '"' . $selected . '>' . ucwords($displayname) . '</option>';
}

$lang_select .= '</select>';

function format_tz($a)
{
	$h = floor($a);
	$m = ($a - floor($a)) * 60;
	return ($a >= 0?"+":"-") . (strlen(abs($h)) > 1?"":"0") . abs($h) .
		":" . ($m==0?"00":$m);
}

tr($tracker_lang['my_allow_pm_from'],
"<input type=radio name=acceptpms" . ($CURUSER["acceptpms"] == "yes" ? " checked" : "") . " value=\"yes\">��� (�������� �������������)
<br /><input type=radio name=acceptpms" .  ($CURUSER["acceptpms"] == "friends" ? " checked" : "") . " value=\"friends\">������ ������
<br /><input type=radio name=acceptpms" .  ($CURUSER["acceptpms"] == "no" ? " checked" : "") . " value=\"no\">������ �������������"
,1);

tr($tracker_lang['my_parked'],
"<input type=\"radio\" name=\"parked\"" . ($CURUSER["parked"] == "yes" ? " checked" : "") . " value=\"yes\">".$tracker_lang['yes']."
<input type=\"radio\" name=\"parked\"" . ($CURUSER["parked"] == "no" ? " checked" : "") . " value=\"no\">".$tracker_lang['no']."
<br /><font class=\"small_text\">".$tracker_lang['my_you_can_park'].".</font>"
,1);

tr($tracker_lang['my_delete_after_reply'], "<input type=checkbox name=deletepms" . ($CURUSER["deletepms"] == "yes" ? " checked" : "") . ">",1);
tr($tracker_lang['my_sentbox'], "<input type=checkbox name=savepms" . ($CURUSER["savepms"] == "yes" ? " checked" : "") . ">",1);

$r = genrelist();
//$categories = "Default browsing categories:<br />\n";
if (count($r) > 0)
{
	$categories = "<table><tr>\n";
	$i = 0;
	foreach ($r as $a)
	{
	  $categories .=  ($i && $i % 2 == 0) ? "</tr><tr>" : "";
	  $categories .= "<td class=bottom style='padding-right: 5px'><input name=cat$a[id] type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[cat$a[id]]") !== false ? " checked" : "") . " value='yes'>&nbsp;" . htmlspecialchars_uni($a["name"]) . "</td>\n";
	  ++$i;
	}
	$categories .= "</tr></table>\n";
}

tr($tracker_lang['my_email_notify'], "<input type=checkbox name=pmnotif" . (strpos($CURUSER['notifs'], "[pm]") !== false ? " checked" : "") . " value=yes> ��������� ���� ��� ��������� ��<br />\n" .
	 "<input type=checkbox name=emailnotif" . (strpos($CURUSER['notifs'], "[email]") !== false ? " checked" : "") . " value=yes> ��������� ���� ��� ���������� �������� � ����� <br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; �� ��������� �������������� ���������.\n"
   , 1);
tr($tracker_lang['my_default_browse'],$categories,1);
tr($tracker_lang['my_style'], "$themes",1);
tr($tracker_lang['my_country'], "<select name=country>\n$countries\n</select>",1);
tr($tracker_lang['my_language'], $lang_select ,1);
tr($tracker_lang['my_avatar_url'], "<input name=avatar size=50 value=\"" . htmlspecialchars_uni($CURUSER["avatar"]) .
  "\"><br />\n".sprintf($tracker_lang['max_avatar_size'], $avatar_max_width, $avatar_max_height),1);
tr($tracker_lang['my_gender'],
"<input type=radio name=gender" . ($CURUSER["gender"] == "1" ? " checked" : "") . " value=1>".$tracker_lang['my_gender_male']."
<input type=radio name=gender" .  ($CURUSER["gender"] == "2" ? " checked" : "") . " value=2>".$tracker_lang['my_gender_female']
,1);

///////////////// BIRTHDAY MOD /////////////////////
$birthday = $CURUSER['birthday'];
$birthday = date('Y-m-d', strtotime($birthday));
list($year1, $month1, $day1) = explode('-', $birthday);
if ($CURUSER['birthday'] == '0000-00-00') {
        $year .= "<select name=year><option value=\"0000\">".$tracker_lang['my_year']."</option>\n";
        $i = "1920";
        while($i <= (date('Y',time())-13)) {
                $year .= "<option value=" .$i. ">".$i."</option>\n";
                $i++;
        }
        $year .= "</select>\n";
        $birthmonths = array(
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
        );
        $month = "<select name=\"month\"><option value=\"00\">".$tracker_lang['my_month']."</option>\n";
        foreach ($birthmonths as $month_no => $show_month)
        {
                $month .= "<option value=$month_no>$show_month</option>\n";
        }
        $month .= "</select>\n";
        $day .= "<select name=day><option value=\"00\">".$tracker_lang['my_day']."</option>\n";
        $i = 1;
        while ($i <= 31) {
                if($i < 10) {
                        $day .= "<option value=0".$i. ">0".$i."</option>\n";
                } else {
                        $day .= "<option value=".$i.">".$i."</option>\n";
                }
                $i++;
        }
        $day .="</select>\n";
        tr($tracker_lang['my_birthdate'], $year . $month . $day ,1);
}
if ($CURUSER['birthday'] != "0000-00-00") {
	tr($tracker_lang['my_birthdate'],"<b><input type=hidden name=year value=$year1>$year1<input type=hidden name=month value=$month1>.$month1<input type=hidden name=day value=$day1>.$day1</b>",1);
}
///////////////// BIRTHDAY MOD /////////////////////

print("<tr><td class=\"tablecat\" colspan=\"2\" align=left><b>".$tracker_lang['my_contact']."</b></td></tr>\n");

tr(" ", "    <table cellspacing=\"3\" cellpadding=\"0\" width=\"100%\" border=\"0\">
            <tr>
        <td style=\"font-size: 11px; font-style: normal; font-variant: normal; font-weight: normal; font-family: verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif\" colspan=2>
        ".$tracker_lang['my_contact_descr']."</td>
      </tr>
      <tr>
        <td style=\"font-size: 11px; font-style: normal; font-variant: normal; font-weight: normal; font-family: verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif\">
        ".$tracker_lang['my_contact_icq']."<br />
        <img alt src=pic/contact/icq.gif width=\"17\" height=\"17\">
        <input maxLength=\"30\" size=\"25\" name=\"icq\" value=\"" . $CURUSER["icq"] . "\" ></td>
        <td style=\"font-size: 11px; font-style: normal; font-variant: normal; font-weight: normal; font-family: verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif\">
        ".$tracker_lang['my_contact_aim']."<br />
        <img alt src=pic/contact/aim.gif width=\"17\" height=\"17\">
        <input maxLength=\"30\" size=\"25\" name=\"aim\" value=\"" . $CURUSER["aim"] . "\" ></td>
      </tr>
      <tr>
        <td style=\"font-size: 11px; font-style: normal; font-variant: normal; font-weight: normal; font-family: verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif\">
        ".$tracker_lang['my_contact_msn']."<br />
        <img alt src=pic/contact/msn.gif width=\"17\" height=\"17\">
        <input maxLength=\"50\" size=\"25\" name=\"msn\" value=\"" . $CURUSER["msn"] . "\" ></td>
        <td style=\"font-size: 11px; font-style: normal; font-variant: normal; font-weight: normal; font-family: verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif\">
        ".$tracker_lang['my_contact_yahoo']."<br />
        <img alt src=pic/contact/yahoo.gif width=\"17\" height=\"17\">
        <input maxLength=\"30\" size=\"25\" name=\"yahoo\" value=\"" . $CURUSER["yahoo"] . "\" ></td>
      </tr>
      <tr>
        <td style=\"font-size: 11px; font-style: normal; font-variant: normal; font-weight: normal; font-family: verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif\">
        ".$tracker_lang['my_contact_skype']."<br />
        <img alt src=pic/contact/skype.gif width=\"17\" height=\"17\">
        <input maxLength=\"32\" size=\"25\" name=\"skype\" value=\"" . $CURUSER["skype"] . "\" ></td>
        <td style=\"font-size: 11px; font-style: normal; font-variant: normal; font-weight: normal; font-family: verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif\">
        ".$tracker_lang['my_contact_mirc']."<br />
        <img alt src=pic/contact/mirc.gif width=\"17\" height=\"17\">
        <input maxLength=\"30\" size=\"25\" name=\"mirc\" value=\"" . $CURUSER["mirc"] . "\" ></td>
      </tr>
    </table>",1);
tr($tracker_lang['my_website'], "<input type=\"text\" name=\"website\" size=50 value=\"" . htmlspecialchars_uni($CURUSER["website"]) . "\" /> ", 1);
tr($tracker_lang['my_torrents_per_page'], "<input type=text size=10 name=torrentsperpage value=$CURUSER[torrentsperpage]> (0 = ��������� �� ���������)",1);
tr($tracker_lang['my_topics_per_page'], "<input type=text size=10 name=topicsperpage value=$CURUSER[topicsperpage]> (0 = ��������� �� ���������)",1);
tr($tracker_lang['my_messages_per_page'], "<input type=text size=10 name=postsperpage value=$CURUSER[postsperpage]> (0 = ��������� �� ���������)",1);
tr($tracker_lang['my_show_avatars'], "<input type=checkbox name=avatars" . ($CURUSER["avatars"] == "yes" ? " checked" : "") . "> (������������ � ���������� �������� ����� ��������� ��� �����)",1);
tr($tracker_lang['my_info'], "<textarea name=info cols=50 rows=4>" . $CURUSER["info"] . "</textarea><br />������������ �� ����� ��������� ��������. ����� ��������� <a href=tags.php target=_new>BB ����</a>.", 1);
tr($tracker_lang['my_userbar'], "<img src=\"torrentbar/bar.php/".$CURUSER["id"].".png\" border=\"0\"><br />".$tracker_lang['my_userbar_descr'].":<br /><input type=\"text\" size=65 value=\"[url=$DEFAULTBASEURL][img]$DEFAULTBASEURL/torrentbar/bar.php/".$CURUSER["id"].".png[/img][/url]\" readonly />",1);
tr($tracker_lang['my_mail'], "<input type=\"text\" name=\"email\" size=50 value=\"" . htmlspecialchars_uni($CURUSER["email"]) . "\" />", 1);
print("<tr><td colspan=\"2\" align=left><b>����������:</b> ���� �� ������� ��� Email �����, �� ��� ������ ������ � ������������� �� ��� ����� Email-�����. ���� �� �� ����������� ������, �� Email ����� �� ����� �������.</td></tr>\n");
tr("������� �������","<input type=checkbox name=resetpasskey value=1 /> (�� ������ ���������� ��� �������� �������� ����� ����� �������)", 1);

if (strlen($CURUSER['passkey']) != 32) {
	$CURUSER['passkey'] = md5($CURUSER['username'].get_date_time().$CURUSER['passhash']);
	sql_query("UPDATE users SET passkey='$CURUSER[passkey]' WHERE id=$CURUSER[id]");
}
tr("��� �������","<b>$CURUSER[passkey]</b>", 1);
tr("��������� IP � �������", "<input type=checkbox name=passkey_ip" . ($CURUSER["passkey_ip"] != "" ? " checked" : "") . "> ������� ��� ����� �� ������ �������� ���� �� ���������������� ��������� �� ������ ������� �������� ��� � IP. ���� ��� IP ������������ - �� ��������� ��� �����.<br />�� ������ ������ ��� IP: <b>".getip()."</b>", 1);
tr("������ ������", "<input type=\"password\" name=\"oldpassword\" size=\"50\" />", 1);
tr("������� ������", "<input type=\"password\" name=\"chpassword\" size=\"50\" />", 1);
tr("������ ��� ���", "<input type=\"password\" name=\"passagain\" size=\"50\" />", 1);

function priv($name, $descr) {
	global $CURUSER;
	if ($CURUSER["privacy"] == $name)
		return "<input type=\"radio\" name=\"privacy\" value=\"$name\" checked=\"checked\" /> $descr";
	return "<input type=\"radio\" name=\"privacy\" value=\"$name\" /> $descr";
}

/* tr("Privacy level",  priv("normal", "Normal") . " " . priv("low", "Low (email address will be shown)") . " " . priv("strong", "Strong (no info will be made available)"), 1); */

?>
<tr><td colspan="2" align="center"><input type="submit" value="�������� �������" style='height: 25px'> <input type="reset" value="�������� ���������" style='height: 25px'></td></tr>
</table>
</form>
</td>
</tr>
</table>
<?
print("<p><a href=users.php><b>����� ������������/������ �������������</b></a></p>");
stdfoot();

?>