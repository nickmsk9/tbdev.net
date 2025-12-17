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

require "include/bittorrent.php";
dbconn();
loggedinorreturn();

$return = $_SERVER['HTTP_REFERER'];
$valid_actions = array('add', 'addforum','delete');
$action = ( in_array($_GET['action'], $valid_actions) ? $_GET['action'] : '');

// action: add -------------------------------------------------------------
if ($action == 'add') {
        if ($CURUSER["warned"] == 'yes') {
                stderr($tracker_lang['error'], "� ��� �������������� � �� �� ������ ������� ����� ��������.");
        }
        $current_time = get_date_time();
        $targetid = intval($_GET['targetid']);
        $resp_type = (isset($_GET['good'])?1:0);
        $type = $_GET['type'];

        if (!is_valid_id($targetid)) {
                stderr($tracker_lang['error'], "������������ ID $targetid.");
        }
        if (get_row_count("users", "WHERE id = $targetid") == 0)
        		stderr($tracker_lang['error'],"������ ������������ �� ����������!");
        if ($CURUSER["id"] == $targetid) {
                stderr($tracker_lang['error'],"�� �� ������ ������ ������� ��� ����������� ����.");
        }

        $r = sql_query('SELECT id FROM simpaty WHERE touserid=' . $targetid . ' AND type = ' . sqlesc($type) . ' AND fromuserid = ' . $CURUSER['id']) or sqlerr(__FILE__, __LINE__);
        if (mysqli_num_rows($r) == 1) {
                stderr ($tracker_lang['error'],"�� ��� ������ ������� �� ��� �������� ����� ������������.");
        }

        if (isset($_POST["description"]) && trim($_POST["description"]) == '') {
                stderr($tracker_lang['error'], "����������� �� ����� ���� ������.");
        }
        if (!isset($_POST["description"])) {
        stderr("","<p>�������� �������, �� ������� �� ������� " . ($resp_type == 1?"�������":"�����������") . " ������������:</p>
        <form action=\"" . $_SERVER["PHP_SELF"] . "?action=add&amp;" . ($resp_type == 1?'good':'bad') . "&amp;type=".htmlspecialchars_uni($type)."&amp;targetid=$targetid\" method=\"post\">
        <input type=text name=description maxlength=300 size=100></textarea>
		".(isset($_GET["returnto"]) ? "<input type=\"hidden\" name=\"returnto\" value=\"" . htmlspecialchars_uni($_GET["returnto"]) . "\" />\n" : "").
        "<input type=submit value=".($resp_type == 1?"�������":"�����������").">
        </form>");
        }
        sql_query ('INSERT INTO simpaty VALUES (0, ' . $targetid . ', ' . $CURUSER['id'] . ', ' . sqlesc($CURUSER['username']) . ', ' . ($resp_type==0?1:0) . ', ' . ($resp_type==1?1:0) . ', ' . sqlesc($type) . ', ' . sqlesc($current_time) . ', ' . sqlesc(htmlspecialchars_uni($_POST["description"])) . ')') or sqlerr(__FILE__, __LINE__);
        if ($resp_type == 1) {
                sql_query('UPDATE users SET simpaty = simpaty + 1 WHERE id = ' . $targetid) or sqlerr(__FILE__, __LINE__);
        } else {
                sql_query('UPDATE users SET simpaty = IF(simpaty > 0, simpaty - 1, 0) WHERE id = ' . $targetid) or sqlerr(__FILE__, __LINE__);
        }
        // mod by StirolXXX (Yuna Scatari)
		$msg = "������������ [url=userdetails.php?id=" . $CURUSER['id'] ."]" . $CURUSER['username'] . "[/url] �������� ��� " . ($resp_type == 1?'�������':'�����������') . " � ��������� �� ��������� ����������: \n[quote]" . htmlspecialchars_uni($_POST["description"]) . "[/quote]";
		$subject = "����������� �� ��������� ���������";
		send_pm(0, $targetid, get_date_time(), $subject, $msg);
		//sql_query("INSERT INTO messages (sender, receiver, added, msg, subject) VALUES (0, $targetid, NOW(), $msg, \"����������� �� ��������� ���������\")");
        // mod by StirolXXX (Yuna Scatari)
		if (isset($_POST["returnto"])) {
			$returl = $_POST["returnto"];
			header("Refresh: 2; url=$returl");
		}
        stdhead(($resp_type == 1?"�������":"�����������") . " ��������");
        stdmsg($tracker_lang['success'],"<p>������������ ������� ������� " . ($resp_type == 1?"�������":"�����������") . " �� ���.</p>".(isset($_POST["returnto"]) ? "������ �� ������ �������������� �� ��������, ������ �� ������." : ""));
        if (isset($_POST["returnto"])) {
        	print("<p><a href=\"".htmlspecialchars_uni($_POST["returnto"])."\">������� ����, ���� �� �� ���� ��������������</a></p>");
        }
}

if ($action == 'delete') {
        if(get_user_class() < UC_SYSOP) {
                stderr($tracker_lang['error'], "� ��� ��� ���� �� �������� ���������.");
        }
        $respect_id = intval($_GET['respect_id']);
        $respect_type = $_GET['respect_type'];
        $touserid = intval($_GET['touserid']);
        sql_query ('DELETE FROM simpaty WHERE id = ' . $respect_id) or sqlerr(__LINE__,__FILE__);
        if ($respect_type == 'bad')
        	sql_query ('UPDATE users SET simpaty = IF(simpaty > 0, simpaty - 1, 0) WHERE id = ' . $touserid) or sqlerr(__LINE__,__FILE__);
        else
			sql_query ('UPDATE users SET simpaty = simpaty + 1 WHERE id = ' . $touserid) or sqlerr(__LINE__,__FILE__);
        /*if (mysql_affected_rows != 1) {
        	stderr($tracker_lang['error'], "�� ���� ������� ".($respect_type == 'good'?"�������":"�����������").".");
        }*/
        if (isset($_GET["returnto"])) {
        	$returl = $_GET["returnto"];
			header("Refresh: 2; url=$returl");
        };
        stdhead();
        stdmsg($tracker_lang['success'], "<p>".($respect_type == 'good' ? "�������" : "�����������")." ������ �������.</p>".(isset($_GET["returnto"]) ? "������ �� ������ �������������� �� ��������, ������ �� ������." : ""));
        if (isset($_GET["returnto"])) {
        	print("<p><a href=\"".htmlspecialchars_uni($_GET["returnto"])."\">������� ����, ���� �� �� ���� ��������������</a></p>");
        }
        stdfoot();
        die();
}
?>