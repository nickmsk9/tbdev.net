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

<?php if (!empty($CURUSER)) : ?>
<?php
// quick aliases
$uid = (int)$CURUSER['id'];

// sizes
$uped   = mksize((float)$CURUSER['uploaded']);
$downed = mksize((float)$CURUSER['downloaded']);

// ratio (как у тебя, но аккуратнее)
if (!empty($CURUSER['downloaded']) && (float)$CURUSER['downloaded'] > 0) {
	$ratioNum = (float)$CURUSER['uploaded'] / (float)$CURUSER['downloaded'];
	$ratioNum = (float)number_format($ratioNum, 3, '.', '');
	$color = get_ratio_color($ratioNum);
	$ratio = $color ? "<font color=\"$color\">{$ratioNum}</font>" : (string)$ratioNum;
} elseif (!empty($CURUSER['uploaded']) && (float)$CURUSER['uploaded'] > 0) {
	$ratio = "Inf.";
} else {
	$ratio = "---";
}

// donor / warn icons
$medaldon = (!empty($CURUSER['donor']) && $CURUSER['donor'] === 'yes')
	? "<img src=\"{$pic_base_url}/star.gif\" alt=\"Донатер\" title=\"Донатер\">"
	: "";

$warn = (!empty($CURUSER['warned']) && $CURUSER['warned'] === 'yes')
	? "<img src=\"{$pic_base_url}/warned.gif\" alt=\"Предупреждение\" title=\"Предупреждение\">"
	: "";

// ---------- DB: 2 запроса вместо 4 ----------
// 1) сообщения (входящие / непрочитанные / исходящие-сохранённые) одним запросом
$messages = 0;
$unread = 0;
$outmessages = 0;

$resMsg = sql_query("
	SELECT
		SUM(CASE WHEN receiver = {$uid} AND location = 1 THEN 1 ELSE 0 END) AS inbox_total,
		SUM(CASE WHEN receiver = {$uid} AND location = 1 AND unread = 'yes' THEN 1 ELSE 0 END) AS inbox_unread,
		SUM(CASE WHEN sender  = {$uid} AND saved = 'yes' THEN 1 ELSE 0 END) AS out_saved
	FROM messages
") or print(mysqli_error($mysql_link));

if ($resMsg) {
	$rowMsg = mysqli_fetch_assoc($resMsg);
	$messages    = (int)($rowMsg['inbox_total'] ?? 0);
	$unread      = (int)($rowMsg['inbox_unread'] ?? 0);
	$outmessages = (int)($rowMsg['out_saved'] ?? 0);
}

// inbox icon
if ($unread > 0) {
	$inboxpic = "<img height=\"16\" style=\"border:none\" alt=\"Входящие\" title=\"Есть непрочитанные\" src=\"{$pic_base_url}/pn_inboxnew.gif\">";
} else {
	$inboxpic = "<img height=\"16\" style=\"border:none\" alt=\"Входящие\" title=\"Нет непрочитанных\" src=\"{$pic_base_url}/pn_inbox.gif\">";
}

// 2) сид/лич одним запросом (условная агрегация)
$activeseed = 0;
$activeleech = 0;

$resPeers = sql_query("
	SELECT
		SUM(CASE WHEN seeder = 'yes' THEN 1 ELSE 0 END) AS seed_cnt,
		SUM(CASE WHEN seeder = 'no'  THEN 1 ELSE 0 END) AS leech_cnt
	FROM peers
	WHERE userid = {$uid}
") or print(mysqli_error($mysql_link));

if ($resPeers) {
	$rowP = mysqli_fetch_assoc($resPeers);
	$activeseed  = (int)($rowP['seed_cnt'] ?? 0);
	$activeleech = (int)($rowP['leech_cnt'] ?? 0);
}

// sent icon (один раз, без дублирования)
$sentIcon = "<img height=\"16\" style=\"border:none\" alt=\"Исходящие\" title=\"Исходящие\" src=\"{$pic_base_url}/pn_sentbox.gif\">";
?>

<!-- //////// start the statusbar ///////////// -->

</table>

<p>

<table align="center" cellpadding="4" cellspacing="0" border="0" style="width:90%">
	<tr>
		<td class="tablea">
			<table align="center" style="width:100%" cellspacing="0" cellpadding="0" border="0">
				<tr>
					<td class="bottom" align="left">
						<span class="smallfont">
							<?= $tracker_lang['welcome_back']; ?>
							<b>
								<a href="userdetails.php?id=<?= (int)$CURUSER['id'] ?>">
									<?= get_user_class_color((int)$CURUSER['class'], (string)$CURUSER['username']) ?>
								</a>
							</b>
							<?= $medaldon ?><?= $warn ?>
							&nbsp; [<a href="bookmarks.php">Закладки</a>] [<a href="mybonus.php">Мой бонус</a>] [<a href="logout.php">Выйти</a>]
							<br/>

							<font color="1900D1"><?= $tracker_lang['ratio']; ?>:</font> <?= $ratio ?>&nbsp;&nbsp;
							<font color="green"><?= $tracker_lang['uploaded']; ?>:</font> <font color="black"><?= $uped ?></font>&nbsp;&nbsp;
							<font color="darkred"><?= $tracker_lang['downloaded']; ?>:</font> <font color="black"><?= $downed ?></font>&nbsp;&nbsp;
							<font color="darkblue"><?= $tracker_lang['bonus']; ?>:</font>
							<a href="mybonus.php" class="online"><font color="black"><?= (int)$CURUSER['bonus'] ?></font></a>&nbsp;&nbsp;
							<font color="1900D1"><?= $tracker_lang['torrents']; ?>:&nbsp;</font>

							<img alt="<?= $tracker_lang['seeding']; ?>" title="<?= $tracker_lang['seeding']; ?>" src="./themes/<?= htmlspecialchars($ss_uri, ENT_QUOTES, 'UTF-8'); ?>/images/arrowup.gif">
							&nbsp;<font color="black"><span class="smallfont"><?= $activeseed ?></span></font>&nbsp;&nbsp;

							<img alt="<?= $tracker_lang['leeching']; ?>" title="<?= $tracker_lang['leeching']; ?>" src="./themes/<?= htmlspecialchars($ss_uri, ENT_QUOTES, 'UTF-8'); ?>/images/arrowdown.gif">
							&nbsp;<font color="black"><span class="smallfont"><?= $activeleech ?></span></font>
						</span>
					</td>

					<td class="bottom" align="right">
						<span class="smallfont">
							<?= $tracker_lang['clock']; ?>:
							<span id="clock"><?= $tracker_lang['loading']; ?>...</span>

							<script type="text/javascript">
							(function () {
								const el = document.getElementById('clock');
								if (!el) return;

								function pad(n){ return (n < 10 ? '0' : '') + n; }
								function tick(){
									const d = new Date();
									let h = d.getHours();
									const m = pad(d.getMinutes());
									const s = pad(d.getSeconds());
									const ampm = (h >= 12) ? 'PM' : 'AM';
									h = h % 12; if (h === 0) h = 12;
									el.textContent = pad(h) + ':' + m + ':' + s + ' ' + ampm;
								}
								tick();
								setInterval(tick, 1000);
							})();
							</script>

							<?php
							// сообщения (без дублирования веток)
							echo "<span class=\"smallfont\"><a href=\"message.php\">{$inboxpic}</a> {$messages}";
							if ($unread > 0) {
								echo " ({$unread} новых)";
							}
							echo "</span>";

							echo "<span class=\"smallfont\">&nbsp;&nbsp;<a href=\"message.php?action=viewmailbox&amp;box=-1\">{$sentIcon}</a> {$outmessages}</span>";

							echo "&nbsp;<a href=\"friends.php\"><img style=\"border:none\" alt=\"Друзья\" title=\"Друзья\" src=\"{$pic_base_url}/buddylist.gif\"></a>";
							echo "&nbsp;<a href=\"getrss.php\"><img style=\"border:none\" alt=\"RSS\" title=\"RSS\" src=\"{$pic_base_url}/rss.gif\"></a>";
							?>
						</span>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<p>

<?php else: ?>

<br />

<?php endif; ?>
<!-- /////////// here we go, with the menu //////////// -->


<?php

$w = "width=\"90%\"";
//if ($_SERVER["REMOTE_ADDR"] == $_SERVER["SERVER_ADDR"]) $w = "width=984";

?>
<table class="mainouter" align="center" <?=$w; ?> border="1" cellspacing="0" cellpadding="5">

<!------------- MENU ------------------------------------------------------------------------>

<? $fn = substr($_SERVER['PHP_SELF'], strrpos($_SERVER['PHP_SELF'], "/") + 1); ?>

<td valign="top" width="155">
<?php
show_blocks('l');

$messages    = (int)($messages ?? 0);
$outmessages = (int)($outmessages ?? 0);
$unread      = (int)($unread ?? 0);
$inboxpic    = (string)($inboxpic ?? '');
$pic_base_url = (string)($pic_base_url ?? '');
$ss_uri      = (string)($ss_uri ?? 'default');
$DEFAULTBASEURL = (string)($DEFAULTBASEURL ?? '');

$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$u = static fn($v): string => rawurlencode((string)$v);

// ---------- PM inline (без дублирования веток) ----------
$inboxIcon = $inboxpic !== ''
	? $inboxpic
	: '<img height="16" style="border:none" alt="Входящие" title="Входящие" src="'.$h($pic_base_url).'/pn_inbox.gif">';

$message_in = '<span class="smallfont">&nbsp;<a href="message.php">'.$inboxIcon.'</a> '.$messages;
if ($unread > 0) {
	// если в lang нет шаблона — покажем “(N новых)”
	$tpl = $tracker_lang['new_pm'] ?? '(%d новых)';
	$message_in .= ' '.sprintf((string)$tpl, $unread);
}
$message_in .= '</span>';

$sentIcon = '<img height="16" style="border:none" alt="Исходящие" title="Исходящие" src="'.$h($pic_base_url).'/pn_sentbox.gif">';
$message_out = '<span class="smallfont">&nbsp;<a href="message.php?action=viewmailbox&amp;box=-1">'.$sentIcon.'</a> '.$outmessages.'</span>';

// ---------- Userbar ----------
if (!empty($CURUSER) && is_array($CURUSER)) {

	$avatar = !empty($CURUSER['avatar'])
		? (string)$CURUSER['avatar']
		: './themes/'.$u($ss_uri).'/images/default_avatar.gif';

	$bonus = (int)($CURUSER['bonus'] ?? 0);
	$ip = $_SERVER['REMOTE_ADDR'] ?? '';

	$userbar =
		'<center><a href="my.php"><img src="'.$h($avatar).'" width="100" alt="Аватар" title="Аватар" border="0"></a></center>
		<br>
		<font color="1900D1">'.($tracker_lang['ratio'] ?? 'Ратио').':</font>&nbsp;'.($ratio ?? '---').'<br>
		<font color="green">'.($tracker_lang['uploaded'] ?? 'Отдано').':</font>&nbsp;'.($uped ?? '0').'<br>
		<font color="red">'.($tracker_lang['downloaded'] ?? 'Скачано').':</font>&nbsp;'.($downed ?? '0').'<br>
		<font color="darkblue">'.($tracker_lang['bonus'] ?? 'Бонус').':</font>&nbsp;<a href="mybonus.php" class="online"><font color="black">'.$bonus.'</font></a><br>
		<font color="blue">'.($tracker_lang['pm'] ?? 'Сообщения').':</font>&nbsp;'.$message_in.' '.$message_out.'<br>
		'.($tracker_lang['torrents'] ?? 'Торренты').':&nbsp;
		<img alt="'.($tracker_lang['seeding'] ?? 'Раздаю').'" title="'.($tracker_lang['seeding'] ?? 'Раздаю').'" src="./themes/'.$h($ss_uri).'/images/arrowup.gif">&nbsp;<font color="green"><span class="smallfont">'.(int)($activeseed ?? 0).'</span></font>&nbsp;
		<img alt="'.($tracker_lang['leeching'] ?? 'Качаю').'" title="'.($tracker_lang['leeching'] ?? 'Качаю').'" src="./themes/'.$h($ss_uri).'/images/arrowdown.gif">&nbsp;<font color="red"><span class="smallfont">'.(int)($activeleech ?? 0).'</span></font><br>
		'.($tracker_lang['clock'] ?? 'Время').':&nbsp;<span id="clock2">'.($tracker_lang['loading'] ?? 'Загрузка').'...</span>

		<script>
		(function () {
			const el = document.getElementById("clock2");
			if (!el) return;
			const pad = (n) => (n < 10 ? "0" : "") + n;
			function tick() {
				const d = new Date();
				let h = d.getHours();
				const ampm = h >= 12 ? "PM" : "AM";
				h = h % 12; if (h === 0) h = 12;
				el.textContent = pad(h) + ":" + pad(d.getMinutes()) + ":" + pad(d.getSeconds()) + " " + ampm;
			}
			tick();
			setInterval(tick, 1000);
		})();
		</script>
		<br>
		<font color="#FF6600">'.($tracker_lang['your_ip'] ?? 'Ваш IP').': '.$h($ip).'</font><br><br>
		<center><img src="'.$h($pic_base_url).'/disabled.gif" border="0">&nbsp;[<a href="logout.php">'.($tracker_lang['logout'] ?? 'Выйти').'</a>]</center>';

} else {
	$userbar =
		'<center><form method="post" action="takelogin.php">
			<br>
			'.($tracker_lang['username'] ?? 'Логин').':<br>
			<input type="text" size="20" name="username"><br>
			'.($tracker_lang['password'] ?? 'Пароль').':<br>
			<input type="password" size="20" name="password"><br>
			<input type="submit" value="'.($tracker_lang['login'] ?? 'Войти').'!" class="btn"><br><br>
		</form></center>
		<a class="menu" href="signup.php"><center>'.($tracker_lang['signup'] ?? 'Регистрация').'</center></a>';
}

// ---------- Заголовок блока (приветствие) ----------
$medaldon = (string)($medaldon ?? '');
$warn     = (string)($warn ?? '');

$welcome_text = ($tracker_lang['welcome_back'] ?? 'Добро пожаловать, ');

if (!empty($CURUSER) && is_array($CURUSER)) {
	$user_id = (int)($CURUSER['id'] ?? 0);
	$username = (string)($CURUSER['username'] ?? 'Пользователь');
	$welcome_text .= '<a href="'.$h($DEFAULTBASEURL).'/userdetails.php?id='.$user_id.'">'.$h($username).'</a>&nbsp;';
} else {
	$welcome_text .= 'Гость';
}

blok_menu($welcome_text.$medaldon.$warn, $userbar, "155");

// ---------- Меню ----------
$mainmenu =
	'<a class="menu" href="index.php">&nbsp;'.($tracker_lang['homepage'] ?? 'Главная').'</a>'.
	'<a class="menu" href="browse.php">&nbsp;'.($tracker_lang['browse'] ?? 'Торренты').'</a>'.
	'<a class="menu" href="log.php">&nbsp;'.($tracker_lang['log'] ?? 'Журнал').'</a>'.
	'<a class="menu" href="rules.php">&nbsp;'.($tracker_lang['rules'] ?? 'Правила').'</a>'.
	'<a class="menu" href="faq.php">&nbsp;'.($tracker_lang['faq'] ?? 'ЧаВо').'</a>'.
	'<a class="menu" href="topten.php">&nbsp;'.($tracker_lang['topten'] ?? 'Топ').'</a>'.
	'<a class="menu" href="formats.php">&nbsp;'.($tracker_lang['formats'] ?? 'Форматы').'</a>';

blok_menu($tracker_lang['main_menu'] ?? 'Главное меню', $mainmenu, "155");

if (!empty($CURUSER)) {

	$usermenu =
		'<a class="menu" href="my.php">&nbsp;'.($tracker_lang['my'] ?? 'Моё').'</a>'.
		'<a class="menu" href="userdetails.php?id='.(int)$CURUSER['id'].'">&nbsp;'.($tracker_lang['profile'] ?? 'Профиль').'</a>'.
		'<a class="menu" href="bookmarks.php">&nbsp;'.($tracker_lang['bookmarks'] ?? 'Закладки').'</a>'.
		'<a class="menu" href="mybonus.php">&nbsp;'.($tracker_lang['my_bonus'] ?? 'Мой бонус').'</a>'.
		'<a class="menu" href="invite.php">&nbsp;'.($tracker_lang['invite'] ?? 'Инвайты').'</a>'.
		'<a class="menu" href="users.php">&nbsp;'.($tracker_lang['users'] ?? 'Пользователи').'</a>'.
		'<a class="menu" href="friends.php">&nbsp;'.($tracker_lang['personal_lists'] ?? 'Списки').'</a>'.
		'<a class="menu" href="subnet.php">&nbsp;'.($tracker_lang['neighbours'] ?? 'Соседи').'</a>'.
		'<a class="menu" href="mytorrents.php">&nbsp;'.($tracker_lang['my_torrents'] ?? 'Мои торренты').'</a>'.
		'<a class="menu" href="logout.php">&nbsp;'.($tracker_lang['logout'] ?? 'Выйти').'!</a>';

	blok_menu($tracker_lang['user_menu'] ?? 'Меню пользователя', $usermenu, "155");

	$msgMenu =
		'<a class="menu" href="message.php">&nbsp;'.($tracker_lang['inbox'] ?? 'Входящие').'</a>'.
		'<a class="menu" href="message.php?action=viewmailbox&amp;box=-1">&nbsp;'.($tracker_lang['outbox'] ?? 'Исходящие').'</a>';

	blok_menu($tracker_lang['messages'] ?? 'Сообщения', $msgMenu, "155");
}

$bt_clients = '&nbsp;&nbsp;<a href="https://www.bittorrent.com/downloads/" target="_blank"><font class=small color=green>'.$tracker_lang['official'].'</font></a><br />'
  			.'&nbsp;&nbsp;<a href="https://www.qbittorrent.org/download" target="_blank"><font class=small color=green>qBittorrent</font></a><br />'
  			.'&nbsp;&nbsp;<a href="https://transmissionbt.com/download" target="_blank"><font class=small color=green>Transmission</font></a><br />'
  			.'&nbsp;&nbsp;<a href="https://deluge-torrent.org/download/" target="_blank"><font class=small color=green>Deluge</font></a><br />'
  			.'&nbsp;&nbsp;<a href="https://www.biglybt.com/download/" target="_blank"><font class=small color=green>BiglyBT</font></a><br />'
  			.'&nbsp;&nbsp;<a href="https://www.bitcomet.com/en" target="_blank"><font class=small color=green>BitComet</font></a><br />'
  			.'<hr width=100% color=#ffc58c size=1>'
			.'<font class=small color=red>&nbsp;&nbsp;'.$tracker_lang['clients_recomened_by_us'].'</font>';

blok_menu($tracker_lang['torrent_clients'], $bt_clients, "155");

?>
</td>


<td align="center" valign="top" class="outer" style="padding-top: 5px; padding-bottom: 5px">
<?php

// 1) Уведомление о новых ЛС (без лишних print, безопаснее, быстрее)
if (!empty($CURUSER) && !empty($unread)) {
	$unread = (int)$unread;
	echo '<p><table border="0" cellspacing="0" cellpadding="10" bgcolor="red"><tr><td style="padding:10px;background:red">';
	echo '<b><a href="message.php"><font color="white">'.sprintf((string)$tracker_lang['new_pms'], $unread).'</font></a></b>';
	echo '</td></tr></table></p>'."\n";
}

// 2) Предупреждение про COOKIE_SALT (не создаём "default" константу — просто проверяем)
if (!defined('COOKIE_SALT') || COOKIE_SALT === '' || COOKIE_SALT === 'default') {
	echo "<p><table border='0' cellspacing='0' cellpadding='10' style='background: orange; margin: 10px auto;'>
		<tr><td style='padding: 10px; background: orange; color: white; text-align: center;'>
			<b>ВНИМАНИЕ: БЕЗОПАСНОСТЬ</b><br>
			Задайте уникальный COOKIE_SALT в файле include/init.php,<br>
			иначе куки могут быть подделаны.
		</td></tr></table></p>\n";
}

// 3) Напоминание “вернуть класс” (проверки без notice)
if (!empty($CURUSER) && is_array($CURUSER)) {
	$override = isset($CURUSER['override_class']) ? (int)$CURUSER['override_class'] : 255;
	if ($override !== 255) {
		$base = rtrim((string)($DEFAULTBASEURL ?? ''), '/').'/';
		echo "<p><table border='0' cellspacing='0' cellpadding='10' style='background: green; margin: 10px auto;'>
			<tr><td style='padding: 10px; background: green; text-align: center;'>
				<b><a href=\"{$base}restoreclass.php\" style='color: white;'>".($tracker_lang['lower_class'] ?? 'Вернуть основной класс')."</a></b>
			</td></tr></table></p>\n";
	}
}

show_blocks('c');
