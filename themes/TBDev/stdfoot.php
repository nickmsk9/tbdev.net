<?php
declare(strict_types=1);

if (!defined('UC_SYSOP')) {
	http_response_code(403);
	exit('Direct access denied.');
}

// центральные/нижние блоки 
show_blocks('d');
?>
<td valign="top" width="155">
<?php
show_blocks('r');
?>
</td>
<?php

echo "</td></tr></table>\n";
echo "</td></tr></table>\n";


// --- Тайминги (без notice/деления на ноль) ---
$seconds = (float)(timer() - (float)$tstart);
$query_time = (float)($querytime ?? 0.0);
$queries = (int)($queries ?? 0);

if ($seconds <= 0.0) {
	$seconds = 0.000001; // чтобы не делить на ноль
}

$phptime = $seconds - $query_time;
if ($phptime < 0) {
	$phptime = 0.0;
}

$percentphp = number_format(($phptime / $seconds) * 100, 2, '.', '');
$percentsql = number_format(($query_time / $seconds) * 100, 2, '.', '');

$secondsStr = substr((string)$seconds, 0, 8);

// версия
$ver = (string)(TBVERSION ?? '');
if (defined('BETA') && BETA && defined('BETA_NOTICE')) {
	$ver .= (string)BETA_NOTICE;
}

// футер (вид сохраняем)
echo "<table class=\"bottom\" width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr valign=\"top\">\n";
echo "<td width=\"49%\" class=\"bottom\"><div align=\"center\"><br /><b>"
	.$ver
	."<br />"
	.sprintf((string)($tracker_lang['page_generated'] ?? 'Страница сгенерирована за %s сек. (%d запросов) PHP %s%% / SQL %s%%'), $secondsStr, $queries, $percentphp, $percentsql)
	."</b></div></td>\n";
echo "</tr></table>\n";
echo "</body></html>\n";
