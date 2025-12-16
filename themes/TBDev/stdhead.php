<?php
declare(strict_types=1);

if (!defined('UC_SYSOP')) {
	http_response_code(403);
	exit('Direct access denied.');
}

$pageTitle = (string)($title ?? 'Torrentside');
$theme     = (string)($ss_uri ?? 'default');
$baseUrl   = rtrim((string)($DEFAULTBASEURL ?? ''), '/');

// helpers
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$u = static fn($v): string => rawurlencode((string)$v);

// cache-bust (опционально: поставь сюда git hash/версию)
$assetV = (string)($GLOBALS['ASSET_VERSION'] ?? '1');
?>
<!doctype html>
<html lang="ru">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title><?= $h($pageTitle) ?></title>

	<?php if (!empty($keywords)): ?>
		<meta name="keywords" content="<?= $h($keywords) ?>">
	<?php endif; ?>

	<?php if (!empty($description)): ?>
		<meta name="description" content="<?= $h($description) ?>">
	<?php endif; ?>

	<link rel="stylesheet" href="themes/<?= $u($theme) ?>/<?= $u($theme) ?>.css?v=<?= $h($assetV) ?>" type="text/css">

	<link rel="alternate" type="application/rss+xml" title="Последние торренты" href="<?= $h($baseUrl) ?>/rss.php">
	<link rel="icon" href="<?= $h($baseUrl) ?>/favicon.ico" sizes="any">
	<link rel="shortcut icon" href="<?= $h($baseUrl) ?>/favicon.ico" type="image/x-icon">

	<script src="js/jquery.js?v=<?= $h($assetV) ?>" defer></script>
	<script src="js/jquery.migrate.js?v=<?= $h($assetV) ?>" defer></script>
	<script src="js/jquery.cookies.js?v=<?= $h($assetV) ?>" defer></script>

	<script src="js/resizer.js?v=<?= $h($assetV) ?>" defer></script>

	<!-- GreenSock / GSAP -->
	<script src="js/TweenMax.min.js?v=<?= $h($assetV) ?>" defer></script>
	<script src="js/jquery.gsap.min.js?v=<?= $h($assetV) ?>" defer></script>

	<script src="js/blocks.js?v=<?= $h($assetV) ?>" defer></script>
	<script src="js/lightbox.js?v=<?= $h($assetV) ?>" defer></script>

	<script>
		// оставлено для совместимости со старым кодом
		window.ExternalLinks_InNewWindow = 1;

		document.addEventListener('DOMContentLoaded', () => {
			const initSpoilers = (context = document.body) => {
				$('div.spoiler-head', context)
					.off('click.spoiler')
					.on('click.spoiler', function () {
						const $body = $(this).next('div.spoiler-body');
						const code = $body.children('textarea').text();
						if (code) {
							$body.children('textarea').replaceWith(code);
							initSpoilers($body);
						}
						$(this).toggleClass('unfolded');
						$body.slideToggle('fast');
						$body.next().slideToggle('fast');
					});
			};

			initSpoilers(document.body);

			if ($.fn.lightBox) {
				$('a[rel*=lightbox]').lightBox();
			}
		});
	</script>
</head>

<body>

<?php

// $h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
// $u = static fn($v): string => rawurlencode((string)$v);

$theme   = (string)($ss_uri ?? 'default');
$baseUrl = rtrim((string)($DEFAULTBASEURL ?? ''), '/');
$site    = (string)($SITENAME ?? 'Torrentside');

$hasUser   = !empty($CURUSER);
$canUpload = function_exists('get_user_class') && (int)get_user_class() >= (defined('UC_USER') ? (int)UC_USER : 0);

// Хелпер для ссылок (чтобы не писать одно и то же)
$navLink = static function (string $href, string $label) use ($h): string {
	$href = $href === '' ? '#' : $href;
	return '<a class="topnav-link" href="'.$h($href).'">'.$h($label).'</a>';
};


// Фон-картинка (один раз)
$logoBg = 'themes/'.$u($theme).'/images/logobg.gif';
$logo   = 'themes/'.$u($theme).'/images/logo.gif';
?>
<table width="90%" class="clear" align="center" border="0" cellspacing="0" cellpadding="0" style="background: transparent;">
	<tr>
		<td class="embedded" width="50%" background="<?= $h($logoBg) ?>">
			<a href="<?= $h($baseUrl ?: '/') ?>">
				<img
					src="<?= $h($logo) ?>"
					alt="<?= $h($site) ?>"
					title="<?= $h($site) ?>"
					style="border:none"
					loading="eager"
				>
			</a>
		</td>
		<td class="embedded" width="50%" align="right" style="text-align:right" background="<?= $h($logoBg) ?>">
			<!-- правая часть шапки (если надо: баннер/поиск/кнопки) -->
		</td>
	</tr>
</table>

<!-- Top Navigation Menu -->
<table width="90%" align="center" border="0" cellspacing="0" cellpadding="2">
	<tr>
		<td align="center" class="topnav">
			<?php
			$items = [];

			$items[] = $navLink($baseUrl.'/', (string)($tracker_lang['homepage'] ?? 'Главная'));
			$items[] = $navLink('browse.php', (string)($tracker_lang['browse'] ?? 'Торренты'));

			if ($hasUser) {
				$items[] = $navLink('bookmarks.php', (string)($tracker_lang['bookmarks'] ?? 'Закладки'));
			}

			if ($canUpload) {
				$items[] = $navLink('upload.php', (string)($tracker_lang['upload'] ?? 'Загрузить'));
			}

			if ($hasUser) {
				$items[] = $navLink('log.php', (string)($tracker_lang['logs'] ?? 'Логи'));
			}

			$items[] = $navLink('rules.php', (string)($tracker_lang['rules'] ?? 'Правила'));
			$items[] = $navLink('faq.php', (string)($tracker_lang['faq'] ?? 'FAQ'));

			if ($hasUser) {
				$items[] = $navLink('staff.php', (string)($tracker_lang['staff'] ?? 'Персонал'));
			}

			$items[] = $navLink('contactus.php', (string)($tracker_lang['contactus'] ?? 'Контакты'));

			echo '&nbsp;' . implode("\n\t\t\t&nbsp;&#8226;&nbsp;\n\t\t\t", $items);
			?>
		</td>
	</tr>
</table>

<!-- /////// Top Navigation Menu for unregistered-->

<!-- /////// some vars for the statusbar;o) //////// -->

<? if ($CURUSER) { ?>

<?

$uped = mksize($CURUSER['uploaded']);
$downed = mksize($CURUSER['downloaded']);

if ($CURUSER["downloaded"] > 0) {
	$ratio = $CURUSER['uploaded'] / $CURUSER['downloaded'];
	$ratio = number_format($ratio, 3);
	$color = get_ratio_color($ratio);
	if ($color)
		$ratio = "<font color=$color>$ratio</font>";
} elseif ($CURUSER["uploaded"] > 0)
	$ratio = "Inf.";
else
	$ratio = "---";

$medaldon = $warn = '';

if ($CURUSER['donor'] == "yes")
	$medaldon = "<img src=\"{$pic_base_url}/star.gif\" alt=\"Донатер\" title=\"Донатер\">";
if ($CURUSER['warned'] == "yes")
	$warn = "<img src=\"{$pic_base_url}/warned.gif\" alt=\"Предупреждение\" title=\"Предупреждение\">";

//// check for messages ////////////////// 
$res1 = sql_query("SELECT COUNT(*) FROM messages WHERE receiver=" . $CURUSER["id"] . " AND location=1") or print(mysqli_error($mysql_link)); 
$arr1 = mysqli_fetch_row($res1);
$messages = $arr1[0];
/*$res1 = sql_query("SELECT COUNT(*) FROM messages WHERE receiver=" . $CURUSER["id"] . " AND location=1 AND unread='yes'") or print(mysqli_error($mysql_link)); 
$arr1 = mysqli_fetch_row($res1);
$unread = $arr1[0];*/
$res1 = sql_query("SELECT COUNT(*) FROM messages WHERE sender=" . $CURUSER["id"] . " AND saved='yes'") or print(mysqli_error($mysql_link)); 
$arr1 = mysqli_fetch_row($res1);
$outmessages = $arr1[0];

if ($unread)
	$inboxpic = "<img height=\"16px\" style=\"border:none\" alt=\"inbox\" title=\"У вас есть непрочитанные\" src=\"{$pic_base_url}/pn_inboxnew.gif\">";
else
	$inboxpic = "<img height=\"16px\" style=\"border:none\" alt=\"inbox\" title=\"Нет непрочитанных\" src=\"{$pic_base_url}/pn_inbox.gif\">";

$res2 = sql_query("SELECT COUNT(*) FROM peers WHERE userid = {$CURUSER["id"]} AND seeder='yes'") or print(mysqli_error($mysql_link));
$row = mysqli_fetch_row($res2);
$activeseed = $row[0];

$res2 = sql_query("SELECT COUNT(*) FROM peers WHERE userid = {$CURUSER["id"]} AND seeder='no'") or print(mysql_error());
$row = mysqli_fetch_row($res2);
$activeleech = $row[0];

//// end

?>

<!-- //////// start the statusbar ///////////// -->

</table>

<p>

<table align="center" cellpadding="4" cellspacing="0" border="0" style="width:90%">
	<tr>
		<td class="tablea">
			<table align="center" style="width:100%" cellspacing="0" cellpadding="0" border="0">
				<tr>
				<td class="bottom" align="left"><span class="smallfont"><?= $tracker_lang['welcome_back']; ?><b><a
									href="userdetails.php?id=<?= $CURUSER['id'] ?>"><?= get_user_class_color($CURUSER['class'], $CURUSER['username']) ?></a></b><?= $medaldon ?><?= $warn ?>
							&nbsp; [<a href="bookmarks.php">Закладки</a>] [<a href="mybonus.php">Мой бонус</a>] [<a
								href="logout.php">Выйти</a>]<br/>
<font color=1900D1><?= $tracker_lang['ratio']; ?>:</font> <?= $ratio ?>&nbsp;&nbsp;<font
								color=green><?= $tracker_lang['uploaded']; ?>:</font> <font
								color=black><?= $uped ?></font>&nbsp;&nbsp;<font
								color=darkred><?= $tracker_lang['downloaded']; ?>:</font> <font
								color=black><?= $downed ?></font>&nbsp;&nbsp;<font
								color=darkblue><?= $tracker_lang['bonus']; ?>:</font> <a href="mybonus.php"
																						 class="online"><font
									color=black><?= $CURUSER["bonus"] ?></font></a>&nbsp;&nbsp;<font color="1900D1"><?=$tracker_lang['torrents'];?>:&nbsp;</font></span>
						<img alt="<?=$tracker_lang['seeding'];?>" title="<?=$tracker_lang['seeding'];?>" src="./themes/<?= $ss_uri; ?>/images/arrowup.gif">&nbsp;<font
							color="black"><span class="smallfont"><?= $activeseed ?></span></font>&nbsp;&nbsp;<img
							alt="<?=$tracker_lang['leeching'];?>" title="<?=$tracker_lang['leeching'];?>" src="./themes/<?= $ss_uri; ?>/images/arrowdown.gif">&nbsp;<font
							color="black"><span class="smallfont"><?= $activeleech ?></span></font></td>
					<td class="bottom" align="right"><span class="smallfont"><?=$tracker_lang['clock'];?>: <span
								id="clock"><?=$tracker_lang['loading'];?>...</span>

<!-- clock hack -->
<script type="text/javascript">
function refrClock() {
	var d=new Date();
	var s=d.getSeconds();
	var m=d.getMinutes();
	var h=d.getHours();
	var day=d.getDay();
	var date=d.getDate();
	var month=d.getMonth();
	var year=d.getFullYear();
	var am_pm;
	if (s < 10) {s="0" + s}
	if (m < 10) {m="0" + m}
	if (h <= 12) {
		am_pm = "AM"
	} else {
		h -= 12;
		am_pm = "PM"
	}
	if (h < 10) {h="0" + h}
	document.getElementById("clock").innerHTML=h + ":" + m + ":" + s + " " + am_pm;
	setTimeout("refrClock()",1000);
}
refrClock();
</script>
<!-- / clock hack -->

<?
if ($messages) {
	print("<span class=smallfont><a href=message.php>$inboxpic</a> $messages ($unread �����)</span>");
	if ($outmessages)
		print("<span class=smallfont>&nbsp;&nbsp;<a href=message.php?action=viewmailbox&box=-1><img height=16px style=border:none alt=����������� title=����������� src={$pic_base_url}/pn_sentbox.gif></a> $outmessages</span>");
	else
		print("<span class=smallfont>&nbsp;&nbsp;<a href=message.php?action=viewmailbox&box=-1><img height=16px style=border:none alt=����������� title=����������� src={$pic_base_url}/pn_sentbox.gif></a> 0</span>");
} else {
	print("<span class=smallfont><a href=message.php><img height=16px style=border:none alt=���������� title=���������� src={$pic_base_url}/pn_inbox.gif></a> 0</span>");
	if ($outmessages)
		print("<span class=smallfont>&nbsp;&nbsp;<a href=message.php?action=viewmailbox&box=-1><img height=16px style=border:none alt=����������� title=����������� src={$pic_base_url}/pn_sentbox.gif></a> $outmessages</span>");
	else
		print("<span class=smallfont>&nbsp;&nbsp;<a href=message.php?action=viewmailbox&box=-1><img height=16px style=border:none alt=����������� title=����������� src={$pic_base_url}/pn_sentbox.gif></a> 0</span>");
}
print("&nbsp;<a href=friends.php><img style=border:none alt=������ title=������ src={$pic_base_url}/buddylist.gif></a>");
print("&nbsp;<a href=getrss.php><img style=border:none alt=RSS title=RSS src={$pic_base_url}/rss.gif></a>");
?>
</span></td>

</tr>
</table></table>
<p>

<? } else {?>

<br />

<? } ?>
<!-- /////////// here we go, with the menu //////////// -->

<?php

$w = "width=\"90%\"";
//if ($_SERVER["REMOTE_ADDR"] == $_SERVER["SERVER_ADDR"]) $w = "width=984";

?>
<table class="mainouter" align="center" <?=$w; ?> border="1" cellspacing="0" cellpadding="5">

<!------------- MENU ------------------------------------------------------------------------>

<? $fn = substr($_SERVER['PHP_SELF'], strrpos($_SERVER['PHP_SELF'], "/") + 1); ?>

<td valign="top" width="155">
<?

show_blocks("l");

$messages = $messages ?? 0;
$outmessages = $outmessages ?? 0;
$unread = $unread ?? 0;
$inboxpic = $inboxpic ?? '';
$pic_base_url = $pic_base_url ?? '';

if ($messages) {
                $message_in = "<span class=\"smallfont\">&nbsp;<a href=\"message.php\">$inboxpic</a> $messages " . sprintf($tracker_lang["new_pm"], $unread) . "</span>";
                if ($outmessages)
                        $message_out = "<span class=\"smallfont\">&nbsp;<a href=\"message.php?action=viewmailbox&box=-1\"><img height=\"16px\" style=\"border:none\" alt=\"" . $tracker_lang['outbox'] . "\" title=\"" . $tracker_lang['outbox'] . "\" src=\"{$pic_base_url}/pn_sentbox.gif\"></a> $outmessages</span>";
                else
                        $message_out = "<span class=\"smallfont\">&nbsp;<a href=\"message.php?action=viewmailbox&box=-1\"><img height=\"16px\" style=\"border:none\" alt=\"" . $tracker_lang['outbox'] . "\" title=\"" . $tracker_lang['outbox'] . "\" src=\"{$pic_base_url}/pn_sentbox.gif\"></a> 0</span>";
        }
        else {
                $message_in = "<span class=\"smallfont\">&nbsp;<a href=\"message.php\"><img height=\"16px\" style=\"border:none\" alt=\"{$tracker_lang['inbox']}\" title=\"{$tracker_lang['inbox']}\" src=\"{$pic_base_url}/pn_inbox.gif\"></a> 0</span>";
                if ($outmessages)
                        $message_out = "<span class=\"smallfont\">&nbsp;<a href=\"message.php?action=viewmailbox&box=-1\"><img height=\"16px\" style=\"border:none\" alt=\"" . $tracker_lang['outbox'] . "\" title=\"" . $tracker_lang['outbox'] . "\" src=\"{$pic_base_url}/pn_sentbox.gif\"></a> $outmessages</span>";
                else
                        $message_out = "<span class=\"smallfont\">&nbsp;<a href=\"message.php?action=viewmailbox&box=-1\"><img height=\"16px\" style=\"border:none\" alt=\"" . $tracker_lang['outbox'] . "\" title=\"" . $tracker_lang['outbox'] . "\" src=\"{$pic_base_url}/pn_sentbox.gif\"></a> 0</span>";
        }

if ($CURUSER) {

	$userbar = "<center><a href=\"my.php\"><img src=\"" . ( $CURUSER["avatar"] ? $CURUSER["avatar"] : "./themes/$ss_uri/images/default_avatar.gif" ) . "\" width=\"100\" alt=\"{$tracker_lang['avatar']}\" title=\"{$tracker_lang['avatar']}\" border=\"0\" /></a></center>
	<br />
	<font color=\"1900D1\">{$tracker_lang['ratio']}:</font>&nbsp;{$ratio}<br />
	<font color=\"green\">{$tracker_lang['uploaded']}:</font>&nbsp;{$uped}<br />
	<font color=\"red\">{$tracker_lang['downloaded']}:</font>&nbsp;{$downed}<br />
	<font color=\"darkblue\">{$tracker_lang['bonus']}:</font>&nbsp;<a href=\"mybonus.php\" class=\"online\"><font color=black>$CURUSER[bonus]</font></a><br />
	<font color=\"blue\">{$tracker_lang['pm']}:</font>&nbsp;{$message_in} {$message_out}<br />
	{$tracker_lang['torrents']}:&nbsp;
	<img alt=\"{$tracker_lang['seeding']}\" title=\"{$tracker_lang['seeding']}\" src=\"./themes/$ss_uri/images/arrowup.gif\">&nbsp;<font color=green><span class=\"smallfont\">{$activeseed}</span></font>&nbsp;
	<img alt=\"{$tracker_lang['leeching']}\" title=\"{$tracker_lang['leeching']}\" src=\"./themes/$ss_uri/images/arrowdown.gif\">&nbsp;<font color=red><span class=\"smallfont\">{$activeleech}</span></font><br />
	{$tracker_lang['clock']}:&nbsp;<span id=\"clock2\">{$tracker_lang['loading']}...</span>

<!-- clock hack -->
<script type=\"text/javascript\">
function refrClock2()
{
var d=new Date();
var s=d.getSeconds();
var m=d.getMinutes();
var h=d.getHours();
var day=d.getDay();
var date=d.getDate();
var month=d.getMonth();
var year=d.getFullYear();
var am_pm;
if (s<10) {s=\"0\" + s}
if (m<10) {m=\"0\" + m}
if (h>12) {h-=12;am_pm = \"PM\"}
else {am_pm=\"AM\"}
if (h<10) {h=\"0\" + h}
document.getElementById(\"clock2\").innerHTML=h + \":\" + m + \":\" + s + \" \" + am_pm;
setTimeout(\"refrClock2()\",1000);
}
refrClock2();
</script>
<!-- / clock hack --><br />
	<font color=\"#FF6600\">" . $tracker_lang['your_ip'] . ": " . $_SERVER["REMOTE_ADDR"] . "</font><br />
	<br />
	<center><img src=\"{$pic_base_url}/disabled.gif\" border=\"0\" />&nbsp;[<a href=\"logout.php\">{$tracker_lang['logout']}</a>]</center>
	";
} else {
	$userbar = '<center><form method="post" action="takelogin.php">
<br />
'.$tracker_lang['username'].': <br />
<input type="text" size=20 name="username" /><br />
'.$tracker_lang['password'].': <br />

<input type="password" size=20 name="password" /><br />
<input type="submit" value="'.$tracker_lang['login'].'!" class=\"btn\"><br /><br />
</form></center>
<a class="menu" href="signup.php"><center>'.$tracker_lang['signup'].'</center></a>';
}

// Инициализируем переменные
$medaldon = $medaldon ?? '';
$warn = $warn ?? '';
$usrclass = '';

// Сначала определим значения по умолчанию для констант если они не определены
if (!defined('UC_PEASANT')) define('UC_PEASANT', 1);
if (!defined('UC_MODERATOR')) define('UC_MODERATOR', 4);

// Проверяем существование CURUSER и его элементов
if (isset($CURUSER) && is_array($CURUSER)) {
    $usrclass = '';
    
    $override_class = isset($CURUSER['override_class']) ? (int)$CURUSER['override_class'] : 255;
    $user_class = isset($CURUSER['class']) ? (int)$CURUSER['class'] : UC_PEASANT;
    
    if ($override_class != 255) {
        $class_name = get_user_class_name($user_class);
        $usrclass = "&nbsp;<img src=\"{$pic_base_url}/warning.gif\" title=\"" . 
                   htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8') . 
                   "\" alt=\"" . 
                   htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8') . 
                   "\">&nbsp;";
    } elseif (get_user_class() >= UC_MODERATOR) {
        $class_name = get_user_class_name($user_class);
        $usrclass = "&nbsp;<a href=\"setclass.php\"><img src=\"{$pic_base_url}/warning.gif\" title=\"" . 
                   htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8') . 
                   "\" alt=\"" . 
                   htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8') . 
                   "\" border=\"0\"></a>&nbsp;";
    }
    
    $user_id = isset($CURUSER["id"]) ? (int)$CURUSER["id"] : 0;
    $username = isset($CURUSER["username"]) ? $CURUSER["username"] : 'Гость';
    
    $welcome_text = (isset($tracker_lang['welcome_back']) ? $tracker_lang['welcome_back'] : 'Добро пожаловать, ') . 
                   "<a href=\"$DEFAULTBASEURL/userdetails.php?id=" . $user_id . "\">" . 
                   htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . 
                   "</a>&nbsp;" . $usrclass . "&nbsp;";
} else {
    $welcome_text = (isset($tracker_lang['welcome_back']) ? $tracker_lang['welcome_back'] : 'Добро пожаловать, ') . "Гость";
}
blok_menu($welcome_text . $medaldon . $warn, $userbar, "155");

	$mainmenu = "<a class=\"menu\" href=\"index.php\">&nbsp;{$tracker_lang['homepage']}</a>"
           ."<a class=\"menu\" href=\"browse.php\">&nbsp;{$tracker_lang['browse']}</a>"
           ."<a class=\"menu\" href=\"log.php\">&nbsp;{$tracker_lang['log']}</a>"
           ."<a class=\"menu\" href=\"rules.php\">&nbsp;{$tracker_lang['rules']}</a>"
           ."<a class=\"menu\" href=\"faq.php\">&nbsp;{$tracker_lang['faq']}</a>"
           ."<a class=\"menu\" href=\"topten.php\">&nbsp;{$tracker_lang['topten']}</a>"
           ."<a class=\"menu\" href=\"formats.php\">&nbsp;{$tracker_lang['formats']}</a>";

	blok_menu($tracker_lang['main_menu'], $mainmenu , "155");

if ($CURUSER) {

	$usermenu = "<a class=\"menu\" href=\"my.php\">&nbsp;{$tracker_lang['my']}</a>"
           ."<a class=\"menu\" href=\"userdetails.php?id={$CURUSER['id']}\">&nbsp;{$tracker_lang['profile']}</a>"
           ."<a class=\"menu\" href=\"bookmarks.php\">&nbsp;{$tracker_lang['bookmarks']}</a>"
           ."<a class=\"menu\" href=\"mybonus.php\">&nbsp;{$tracker_lang['my_bonus']}</a>"
           ."<a class=\"menu\" href=\"invite.php\">&nbsp;{$tracker_lang['invite']}</a>"
           ."<a class=\"menu\" href=\"users.php\">&nbsp;{$tracker_lang['users']}</a>"
           ."<a class=\"menu\" href=\"friends.php\">&nbsp;{$tracker_lang['personal_lists']}</a>"
           ."<a class=\"menu\" href=\"subnet.php\">&nbsp;{$tracker_lang['neighbours']}</a>"
           ."<a class=\"menu\" href=\"mytorrents.php\">&nbsp;{$tracker_lang['my_torrents']}</a>"
           ."<a class=\"menu\" href=\"logout.php\">&nbsp;{$tracker_lang['logout']}!</a>";

	blok_menu($tracker_lang['user_menu'], $usermenu , "155");

	$messages = "<a class=\"menu\" href=\"message.php\">&nbsp;{$tracker_lang['inbox']}</a>"
           ."<a class=\"menu\" href=\"message.php?action=viewmailbox&box=-1\">&nbsp;{$tracker_lang['outbox']}</a>";

	blok_menu($tracker_lang['messages'], $messages , "155");

}

	$bt_clients = '&nbsp;&nbsp;<a href="http://bitconjurer.org/BitTorrent/download.html" target="_blank"><font class=small color=green>'.$tracker_lang['official'].'</font></a><br />'
  			.'&nbsp;&nbsp;<a href="http://azureus.sourceforge.net/" target="_blank"><font class=small color=green>Azureus (Java)</font></a><br />'
  			.'&nbsp;&nbsp;<a href="http://www.bittornado.com/" target="_blank"><font class=small color=green>BitTornado</font></a><br />'
  			.'&nbsp;&nbsp;<a href="http://www.bitcomet.com/" target="_blank"><font class=small color=green>BitComet</font></a><br />'
  			.'&nbsp;&nbsp;<a href="http://www.bitlord.com/" target="_blank"><font class=small color=green>BitLord</font></a><br />'
  			.'&nbsp;&nbsp;<a href="http://www.macupdate.com/info.php/id/7170" target="_blank"><font class="small" color=green>Acquisition (Mac)</font></a><br />'
  			.'&nbsp;&nbsp;<a href="http://www.167bt.com/intl/" target="_blank"><font class=small color=green>BitSpirit</font></a><br />'
  			.'<hr width=100% color=#ffc58c size=1>'
			.'<font class=small color=red>&nbsp;&nbsp;'.$tracker_lang['clients_recomened_by_us'].'</font>';

	blok_menu($tracker_lang['torrent_clients'], $bt_clients , "155");

?>
</td>

<td align="center" valign="top" class="outer" style="padding-top: 5px; padding-bottom: 5px">
<?

if ($CURUSER) {
	if ($unread) {
		print("<p><table border=0 cellspacing=0 cellpadding=10 bgcolor=red><tr><td style='padding: 10px; background: red'>\n");
		print("<b><a href=\"message.php\"><font color=white>".sprintf($tracker_lang['new_pms'],$unread)."</font></a></b>");
		print("</td></tr></table></p>\n");
	}
}

// Проверяем, определена ли константа COOKIE_SALT
if (!defined('COOKIE_SALT')) {
    // Если не определена, создаем с дефолтным значением для отображения ошибки
    define('COOKIE_SALT', 'default');
}

if (COOKIE_SALT === 'default') {
    echo "<p><table border='0' cellspacing='0' cellpadding='10' style='background: orange; margin: 10px auto;'>
        <tr><td style='padding: 10px; background: orange; color: white; text-align: center;'>
            <b>ВНИМАНИЕ БЕЗОПАСНОСТИ!</b><br>
            Пожалуйста, измените значение COOKIE_SALT в файле include/init.php на уникальное,<br>
            чтобы защитить сайт от взлома через куки!
        </td></tr></table></p>\n";
}

// Проверяем сначала существование $CURUSER, затем доступ к элементу массива
if (isset($CURUSER) && is_array($CURUSER) && isset($CURUSER['override_class'])) {
    if ($CURUSER['override_class'] != 255) {
        print("<p><table border='0' cellspacing='0' cellpadding='10' style='background: green; margin: 10px auto;'><tr><td style='padding: 10px; background: green; text-align: center;'>\n");
        print("<b><a href=\"{$DEFAULTBASEURL}restoreclass.php\" style='color: white;'>{$tracker_lang['lower_class']}</a></b>");
        print("</td></tr></table></p>\n");
    }
}

show_blocks('c');