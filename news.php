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

require "include/bittorrent.php";

dbconn();
loggedinorreturn();

if (get_user_class() < UC_ADMINISTRATOR)
	stderr($tracker_lang['error'], '������ �������.');

$action = (string)$_GET["action"];

///////// �������� �������� /////////

if ($action == 'delete')
{
	$newsid = (int)$_GET["newsid"];

    if (!is_valid_id($newsid))
  	    stderr($tracker_lang['error'], '�������� �������������.');

    $sure = $_GET["sure"];

    if (!$sure)
        stderr('������� �������', '�� ������������� ������ ������� ��� �������? ������� <a href="?action=delete&newsid='.$newsid.'&sure=1">����</a> ���� �� �������.');

    sql_query("DELETE FROM news WHERE id=$newsid") or sqlerr(__FILE__, __LINE__);

	$warning = '������� <b>�������</b> �������';
}

///////// ���������� �������� /////////

if ($action == 'add')
{
	$subject = $_POST["subject"];
	if (!$subject)
		stderr($tracker_lang['error'], '���� ������� �� ����� ���� ������!');

	$body = $_POST["body"];
	if (!$body)
		stderr($tracker_lang['error'], '���� ������� �� ����� ���� ������!');

    sql_query("INSERT INTO news (userid, added, body, subject) VALUES (" . $CURUSER['id'] . ", NOW(), " . sqlesc($body) . ", " . sqlesc($subject) . ")") or sqlerr(__FILE__, __LINE__);

    if (mysql_affected_rows() == 1)
		$warning = '������� <b>������� ���������</b>';
	else
		stderr($tracker_lang['error'], '������ �������.');


}

///////// �������������� �������� /////////

if ($action == 'edit')
{
	$newsid = (int)$_GET["newsid"];

    if (!is_valid_id($newsid))
  	    stderr($tracker_lang['error'], '�������� �������������.');

    $res = sql_query("SELECT * FROM news WHERE id=$newsid") or sqlerr(__FILE__, __LINE__);

	if (mysql_num_rows($res) != 1)
	  stderr($tracker_lang['error'], "������� �� �������.");

	$arr = mysqli_fetch_assoc($res);

    if ($_SERVER['REQUEST_METHOD'] == 'POST')
    {
  	    $body = $_POST['body'];
  	    $subject = $_POST['subject'];

        if ($subject == '')
		    stderr($tracker_lang['error'], '���� ������� �� ����� ���� ������!');

        if ($body == '')
    	    stderr($tracker_lang['error'], '���� ������� �� ����� ���� ������!');

        $body = sqlesc($body);
        $subject = sqlesc($subject);

        sql_query("UPDATE news SET body=$body, subject=$subject WHERE id=$newsid") or sqlerr(__FILE__, __LINE__);

		$warning = '������� <b>�������</b> ���������������';

    } else {

 	    $returnto = htmlentities($_GET['returnto']);

	    stdhead("�������������� �������");
	        echo '<form name="news" method=post action=?action=edit&newsid='.$newsid.'>';
	            echo '<table border=1 cellspacing=0 cellpadding=5>';
	                echo '<tr><td class=colhead>�������������� �������</td></tr>';
	                echo '<tr><td>����: <input type=text name=subject maxlength=70 size=50 value="' . htmlspecialchars_uni($arr["subject"]) . '"/></td></tr>';
                    echo '<tr><td>';
                        echo textbbcode("news", "body", htmlspecialchars_uni($arr["body"]));
                    echo '</td></tr>';
                    echo '<input type=hidden name=returnto value='.$returnto.'>';
	                echo '<tr><td align=center><input type=submit value="���������������"></td></tr>';
	            echo '</table>';
	        echo '</form>';
	    stdfoot();
	  die;
  }
}

stdhead("�������");

    if ($warning)
	    echo '<p><font size=-3>('.$warning.')</font></p>';

    echo '<form name="news" method="post" action="?action=add">';
        echo '<table border=1 cellspacing=0 cellpadding=5>';
            echo '<tr><td class=colhead>�������� �������</td></tr>';
            echo '<tr><td>����: <input type=text name=subject maxlength=40 size=50 value="' . htmlspecialchars_uni($arr["subject"]) . '"/></td></tr>';
            echo '<tr><td>';
                echo textbbcode("news", "body");
            echo '</td></tr>';
            echo '<tr><td align=center><input type="submit" value="��������" class="btn"></td></tr>';
        echo '</table>';
    echo '</form><br /><br />';

$query = sql_query("SELECT news.*, users.username FROM news LEFT JOIN users ON news.userid = users.id ORDER BY news.added DESC") or sqlerr(__FILE__, __LINE__);

if (mysql_num_rows($query) > 0)
{
 	begin_main_frame();
	begin_frame();

	while ($result = mysqli_fetch_assoc($query))
	{
	    $newsid = $result["id"];
		$body = $result["body"];
		$subject = $result["subject"];
	    $userid = $result["userid"];
	    $added = $result["added"] . ' GMT (' . (get_elapsed_time(sql_timestamp_to_unix_timestamp($result["added"]))) . ' �����)';

        $username = $result["username"];

        if ($username == "")
    	    $by = '���������� ['.$userid.']';
        else
    	    $by = '<a href="userdetails.php?id='.$userid.'"><b>'.$username.'</b></a>';

	    echo '<p class=sub><table border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>';
            echo '��������� '.$added.'&nbsp;-&nbsp;'.$by;
            echo ' - [<a href="?action=edit&newsid='.$newsid.'"><b>�������������</b></a>]';
            echo ' - [<a href="?action=delete&newsid='.$newsid.'"><b>�������</b></a>]';
        echo '</td></tr></table></p>';

	    begin_table(true);
            echo '<tr valign=top><td><b>'.htmlspecialchars_uni($subject).'</b></td></tr>';
	        echo '<tr valign=top><td class=comment>'.format_comment($body).'</td></tr>';
	    end_table();
	}
	end_frame();
	end_main_frame();
}
else
  stdmsg('��������', '�������� ���!');
stdfoot();
