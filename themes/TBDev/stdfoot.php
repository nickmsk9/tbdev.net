<?php

if (!defined('UC_SYSOP'))
	die('Direct access denied.');

	show_blocks('d');
?>
<td valign="top" width="155">
<?
	show_blocks('r');
?>
</td>
<?
	print("</td></tr></table>\n");

// Variables for End Time
$seconds = (timer() - $tstart);

$phptime = 		$seconds - $querytime;
$query_time = 	$querytime;
$percentphp = 	number_format(($phptime/$seconds) * 100, 2);
$percentsql = 	number_format(($query_time/$seconds) * 100, 2);
$seconds = 		substr($seconds, 0, 8);
//КОПИРАЙТ
	print("</td></tr></table>\n");
	print("<table class=\"bottom\" width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr valign=\"top\">\n");
	print("<td width=\"49%\" class=\"bottom\"><div align=\"center\"><br /><b>".TBVERSION.(BETA?BETA_NOTICE:"")."<br />".sprintf($tracker_lang["page_generated"], $seconds, $queries, $percentphp, $percentsql)."</b></div></td>\n");
	print("</tr></table>\n");
	print("</body></html>\n");
?>