<?php

if (!defined('BLOCK_FILE')) {
header("Location: ../index.php");
exit;
}

global $tracker_lang;
$con = sql_query("SELECT userid FROM peers GROUP by userid");
$connected = mysqli_num_rows($con);
$blocktitle = "Нагрузка сервера";
$avgload = get_server_load();
if (strtolower(substr(PHP_OS, 0, 3)) != 'win')
	$percent = $avgload * 4;
else
	$percent = $avgload;
if ($percent <= 50) $pic = "loadbargreen.gif";
elseif ($percent <= 70) $pic = "loadbaryellow.gif";
else $pic = "loadbarred.gif";
	$width = $percent * 4;
$content .= "<center>
<table class=\"main\" border=\"0\" width=\"402\"><tr><td style=\"padding: 0px; background-repeat: repeat-x\" title=\"Загрузка: $percent%, нагрузка (LA): $avgload\">"
."<img height=\"15\" width=\"$width\" src=\"pic/$pic\" alt=\"Загрузка: $percent%, нагрузка (LA): $avgload\" title=\"Загрузка: $percent%, нагрузка (LA): $avgload\">"
."</td></tr></table>"
."<b>На данный момент подключено $connected пользователей.</b></center>";
?>