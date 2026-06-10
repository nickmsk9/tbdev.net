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

$secondsStr = number_format($seconds, 4, '.', '');
$queryTimeStr = number_format($query_time, 4, '.', '');
$phpTimeStr = number_format($phptime, 4, '.', '');

// Футер
$currentYear = date('Y');
$siteName = htmlspecialchars(
	(string)($SITENAME ?? 'TBDev v.Core'),
	ENT_QUOTES | ENT_SUBSTITUTE,
	'UTF-8'
);
$gzipStatus = !empty($use_gzip) ? 'gzip on' : 'gzip off';
$copyrightTitle = htmlspecialchars(
	"Движок сайта: TBDev v.Core 2k26 © 2008-{$currentYear}.",
	ENT_QUOTES | ENT_SUBSTITUTE,
	'UTF-8'
);

echo "<table class=\"bottom site-footer\" width=\"90%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
echo "<tr><td colspan=\"3\" class=\"is_foot\">";
echo "<b>.:{$siteName} <noindex><a href=\"?copyright\" class=\"copyright\" title=\"{$copyrightTitle}\" rel=\"nofollow\">©</a></noindex> "
	. "{$currentYear} TBDev v.Core:.</b><br />";
echo "Страничка сгенерирована за {$secondsStr} секунд ({$gzipStatus})<br />";
echo "<b>{$queries}</b>, <b>{$percentsql}%</b> (queries, {$queryTimeStr} -&gt; sql) - "
	. "<b>{$percentphp}%</b> ({$phpTimeStr} -&gt; php)";
echo "</td></tr></table>\n";
echo "</body></html>\n";
