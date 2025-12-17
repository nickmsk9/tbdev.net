<?php
declare(strict_types=1);

/**
 * Blocks Admin module (TBDev / PHP 8.1+)
 * ВАЖНО: этот файл должен выполняться ТОЛЬКО для op=Blocks*
 */

if (!defined('ADMIN_FILE')) {
	die('Illegal File Access');
}

/** @var string $admin_file */
global $admin_file, $CURUSER;

$prefix = 'orbital';
const BLOCKS_DIR = 'blocks'; // папка с block-*.php относительно текущего файла

// ------------------------------------------------------------
// Guard: НЕ выполняем модуль, если op не Blocks*
// ------------------------------------------------------------
$op = (string)($_GET['op'] ?? '');
if ($op === '' || strncmp($op, 'Blocks', 6) !== 0) {
	return; // важно: чтобы при op=iUsers и т.п. ничего не рисовалось
}

// ------------------------------------------------------------
// Разрешённые модули (страницы) для привязки блока
// ------------------------------------------------------------
$existing_modules = [];
$glob = glob('*.php');
if (is_array($glob)) {
	foreach ($glob as $f) {
		$existing_modules[] = pathinfo($f, PATHINFO_FILENAME);
	}
}
$allowed_modules = array_combine(
	$existing_modules,
	array_map(fn($el) => "<i>" . htmlspecialchars((string)$el, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</i>", $existing_modules)
) ?: [];

$allowed_modules = array_merge($allowed_modules, [
	"admincp"      => "Админка",
	"browse"       => "Каталог",
	"forums"       => "Форум",
	"staff"        => "Персонал",
	"upload"       => "Загрузка",
	"details"      => "Детали",
	"my"           => "Мой профиль",
	"userdetails"  => "Профиль",
	"viewrequests" => "Запросы",
	"viewoffers"   => "Предложения",
	"log"          => "Лог",
	"faq"          => "FAQ",
	"rules"        => "Правила",
	"message"      => "Сообщения",
	"recover"      => "Восст. пароль",
	"signup"       => "Регистрация",
	"login"        => "Вход",
	"mybonus"      => "Мой бонус",
	"invite"       => "Приглашения",
	"bookmarks"    => "Закладки",
]);

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function h(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_url(string $op, array $qs = []): string {
	global $admin_file;
	$qs = array_merge(['op' => $op], $qs);
	return h($admin_file) . '.php?' . http_build_query($qs);
}

function blocks_positions(): array {
	return [
		'l' => 'Левый',
		'r' => 'Правый',
		'c' => 'В центре сверху',
		'd' => 'В центре снизу',
		'b' => 'Верхняя строка',
		'f' => 'Нижняя строка',
	];
}

function blocks_view_names(): array {
	return [
		0 => "Все посетители",
		1 => "Только пользователи",
		2 => "Только администраторы",
		3 => "Только модераторы",
	];
}

function blocks_position_icon(string $pos): string {
	$icons = [
		'l' => "<img src=\"admin/pic/left.gif\"  border=\"0\" alt=\"Левый\" title=\"Левый\"> Левый",
		'r' => "Правый <img src=\"admin/pic/right.gif\" border=\"0\" alt=\"Правый\" title=\"Правый\">",
		'c' => "<img src=\"admin/pic/right.gif\" border=\"0\" alt=\"Центр\" title=\"Центр\">&nbsp;в центре сверху&nbsp;<img src=\"admin/pic/left.gif\" border=\"0\" alt=\"Центр\" title=\"Центр\">",
		'd' => "<img src=\"admin/pic/right.gif\" border=\"0\" alt=\"Центр\" title=\"Центр\">&nbsp;в центре снизу&nbsp;<img src=\"admin/pic/left.gif\" border=\"0\" alt=\"Центр\" title=\"Центр\">",
		'b' => "<img src=\"admin/pic/up.gif\"    border=\"0\" alt=\"Верх\"  title=\"Верх\">&nbsp;верхняя строка&nbsp;<img src=\"admin/pic/up.gif\" border=\"0\" alt=\"Верх\" title=\"Верх\">",
		'f' => "<img src=\"admin/pic/down.gif\"  border=\"0\" alt=\"Низ\"   title=\"Низ\">&nbsp;нижняя строка&nbsp;<img src=\"admin/pic/down.gif\" border=\"0\" alt=\"Низ\" title=\"Низ\">",
	];
	return $icons[$pos] ?? h($pos);
}

/**
 * Нормализует список страниц из POST blockwhere[]
 * - all -> "all"
 * - home -> "home"
 * - иначе -> "a,b,c"
 */
function blocks_normalize_where(array $arr): string {
	$arr = array_values(array_filter(array_map('strval', $arr)));
	if (!$arr) return '';
	if (in_array('all', $arr, true)) return 'all';
	if (in_array('home', $arr, true)) return 'home';
	return implode(',', $arr);
}

function blocks_parse_where(string $which): array {
	$which = trim($which);
	if ($which === '') return [];
	return array_values(array_filter(array_map('trim', explode(',', $which))));
}

function blocks_redirect_list(): never {
	header('Location: ' . admin_url('BlocksAdmin'));
	exit;
}

// ------------------------------------------------------------
// UI
// ------------------------------------------------------------
function BlocksNavi(): void {
	echo "<h2>Управление блоками</h2><br />"
		. "[ <a href=\"" . admin_url('BlocksAdmin') . "\">Список</a>"
		. " | <a href=\"" . admin_url('BlocksNew') . "\">Создать новый блок</a>"
		. " | <a href=\"" . admin_url('BlocksFile') . "\">Создать блок из файла</a>"
		. " | <a href=\"" . admin_url('BlocksFileEdit') . "\">Редактировать файл</a> ]";
}

// ------------------------------------------------------------
// Actions
// ------------------------------------------------------------
function BlocksAdmin(): void {
	global $prefix;

	BlocksNavi();

	echo "<p /><table border=\"0\" cellpadding=\"3\" cellspacing=\"1\" width=\"100%\">"
		. "<tr align=\"center\">"
		. "<td class=\"colhead\">ID</td>"
		. "<td class=\"colhead\">Название</td>"
		. "<td class=\"colhead\">Позиция</td>"
		. "<td class=\"colhead\">Вес</td>"
		. "<td class=\"colhead\">Порядок</td>"
		. "<td class=\"colhead\">Тип</td>"
		. "<td class=\"colhead\">Статус</td>"
		. "<td class=\"colhead\">Кто видит</td>"
		. "<td class=\"colhead\">Управление</td>"
		. "</tr>";

	// ВАЖНО: алиасы prev_bid / next_bid — никаких b.bid / c.bid
	$res = sql_query("
		SELECT
			a.bid,
			a.bkey,
			a.title,
			a.bposition,
			a.weight,
			a.active,
			a.blockfile,
			a.view,
			a.expire,
			a.action,

			b.bid AS prev_bid,
			c.bid AS next_bid
		FROM {$prefix}_blocks AS a
		LEFT JOIN {$prefix}_blocks AS b
			ON (b.bposition = a.bposition AND b.weight = a.weight - 1)
		LEFT JOIN {$prefix}_blocks AS c
			ON (c.bposition = a.bposition AND c.weight = a.weight + 1)
		ORDER BY a.bposition, a.weight
	") or sqlerr(__FILE__, __LINE__);

	$has = false;
	while ($row = mysqli_fetch_assoc($res)) {
		$has = true;

		$bid      = (int)$row['bid'];
		$bkey     = (string)($row['bkey'] ?? '');
		$title    = (string)($row['title'] ?? '');
		$pos      = (string)($row['bposition'] ?? '');
		$weight   = (int)($row['weight'] ?? 0);
		$active   = (int)($row['active'] ?? 0);
		$blockfile= (string)($row['blockfile'] ?? '');
		$view     = (int)($row['view'] ?? 0);
		$expire   = (int)($row['expire'] ?? 0);
		$action   = (string)($row['action'] ?? 'd');

		$prevBid  = (int)($row['prev_bid'] ?? 0);
		$nextBid  = (int)($row['next_bid'] ?? 0);

		// Авто-обработка истёкших
		if ($expire > 0 && $expire < time()) {
			if ($action === 'd') {
				sql_query("UPDATE {$prefix}_blocks SET active='0', expire='0' WHERE bid=" . (int)$bid) or sqlerr(__FILE__, __LINE__);
				$active = 0;
				$expire = 0;
			} elseif ($action === 'r') {
				sql_query("DELETE FROM {$prefix}_blocks WHERE bid=" . (int)$bid) or sqlerr(__FILE__, __LINE__);
				continue; // уже удалён — не рисуем строку
			}
		}

		$type = 'HTML';
		if ($bkey !== '') $type = 'Системный';
		elseif ($blockfile !== '') $type = 'Файл';

		$active_display = $active === 1
			? "<font color=\"#009900\">акт.</font>"
			: "<font color=\"#FF0000\">деакт.</font>";

		$view_names = blocks_view_names();
		$who_view = $view_names[$view] ?? 'Неизвестно';

		echo "<tr>";
		echo "<td align=\"center\">{$bid}</td>";
		echo "<td>" . h($title) . "</td>";
		echo "<td align=\"center\"><nobr>" . blocks_position_icon($pos) . "</nobr></td>";
		echo "<td align=\"center\">" . (int)$weight . "</td>";

		// стрелки порядка
		echo "<td align=\"center\">";
		if ($prevBid > 0) {
			echo "<a href=\"" . admin_url('BlocksOrder', [
				'weight'    => $weight,
				'bidori'    => $bid,
				'weightrep' => $weight - 1,
				'bidrep'    => $prevBid,
			]) . "\"><img src=\"admin/pic/up.gif\" alt=\"Поднять\" title=\"Поднять\" border=\"0\"></a> ";
		}
		if ($nextBid > 0) {
			echo "<a href=\"" . admin_url('BlocksOrder', [
				'weight'    => $weight,
				'bidori'    => $bid,
				'weightrep' => $weight + 1,
				'bidrep'    => $nextBid,
			]) . "\"><img src=\"admin/pic/down.gif\" alt=\"Опустить\" title=\"Опустить\" border=\"0\"></a>";
		}
		echo "</td>";

		echo "<td align=\"center\">" . h($type) . "</td>";
		echo "<td align=\"center\">" . $active_display . "</td>";
		echo "<td align=\"center\"><nobr>" . h($who_view) . "</nobr></td>";

		echo "<td align=\"center\">"
			. "<a href=\"" . admin_url('BlocksEdit', ['bid' => $bid]) . "\" title=\"Редактировать\"><img src=\"admin/pic/edit.gif\" border=\"0\" alt=\"Редактировать\"></a> "
			. "<a href=\"" . admin_url('BlocksChange', ['bid' => $bid]) . "\" title=\"" . ($active ? "Деактивировать" : "Активировать") . "\">"
			. "<img src=\"admin/pic/" . ($active ? "inactive" : "activate") . ".gif\" border=\"0\" alt=\"Toggle\"></a>";

		// удаление только не системных
		if ($bkey === '') {
			echo " <a href=\"" . admin_url('BlocksDelete', ['bid' => $bid]) . "\" onclick=\"return confirm('Удалить блок &quot;" . h($title) . "&quot;?')\" title=\"Удалить\">"
				. "<img src=\"admin/pic/delete.gif\" border=\"0\" alt=\"Удалить\"></a>";
		}

		// показать
		echo " <a href=\"" . admin_url('BlocksShow', ['bid' => $bid]) . "\" title=\"Показать\">"
			. "<img src=\"admin/pic/show.gif\" border=\"0\" alt=\"Показать\"></a>";

		echo "</td>";
		echo "</tr>";
	}

	if (!$has) {
		echo "<tr><td colspan=\"9\" align=\"center\">Нет блоков.</td></tr>";
	}
	echo "</table>";

	echo "<center>[ <a href=\"" . admin_url('BlocksFixweight') . "\">Пересчитать вес блоков в каждой позиции</a> ]</center>";
}

function BlocksNew(): void {
	global $allowed_modules;

	BlocksNavi();

	echo "<h2>Создать новый блок</h2>";
	echo "<form action=\"" . admin_url('BlocksAdd') . "\" method=\"post\">";
	echo "<table border=\"0\" align=\"center\">";

	echo "<tr><td>Название:</td><td><input type=\"text\" name=\"title\" maxlength=\"60\" style=\"width:400px\"></td></tr>";

	// Файлы block-*.php
	echo "<tr><td>Из файла:</td><td><select name=\"blockfile\" style=\"width:400px\">";
	echo "<option value=\"\" selected>Нет</option>";

	$files = [];
	$dir = @opendir(BLOCKS_DIR);
	if ($dir) {
		while (($file = readdir($dir)) !== false) {
			if (preg_match('/^block\-(.+)\.php$/', $file)) {
				$files[] = $file;
			}
		}
		closedir($dir);
	}
	sort($files);

	foreach ($files as $file) {
		echo "<option value=\"" . h($file) . "\">" . h($file) . "</option>";
	}

	echo "</select></td></tr>";

	echo "<tr><td>Содержимое:</td><td><textarea name=\"content\" cols=\"65\" rows=\"15\" style=\"width:400px\"></textarea></td></tr>";

	// Позиции
	echo "<tr><td>Позиция:</td><td><select name=\"bposition\" style=\"width:400px\">";
	foreach (blocks_positions() as $k => $v) {
		echo "<option value=\"" . h($k) . "\">" . h($v) . "</option>";
	}
	echo "</select></td></tr>";

	// Где показывать
	echo "<tr><td>Отображать блок на страницах:</td><td align=\"center\">";
	echo "<table border=\"0\" cellpadding=\"3\" cellspacing=\"0\" align=\"center\" style=\"width:400px\"><tr>";
	echo "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"ihome\"></td><td>Главная</td>";

	$a = 1;
	foreach ($allowed_modules as $name => $title) {
		$display = str_replace("_", " ", strip_tags((string)$title));
		echo "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"" . h((string)$name) . "\"></td><td>" . h($display) . "</td>";
		if ($a === 2) { echo "</tr><tr>"; $a = 0; }
		$a++;
	}

	echo "</tr><tr>"
		. "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"all\"></td><td><b>Все страницы</b></td>"
		. "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"home\"></td><td><b>Только главная</b></td>"
		. "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"infly\"></td><td><b>Всплывающий блок</b></td>"
		. "</tr></table></td></tr>";

	echo "<tr><td>Скрываемый?</td><td>"
		. "<label><input type=\"radio\" name=\"hide\" value=\"yes\" checked> Да</label>&nbsp;&nbsp;"
		. "<label><input type=\"radio\" name=\"hide\" value=\"no\"> Нет</label>"
		. "</td></tr>";

	echo "<tr><td>Активный?</td><td>"
		. "<label><input type=\"radio\" name=\"active\" value=\"1\" checked> Да</label>&nbsp;&nbsp;"
		. "<label><input type=\"radio\" name=\"active\" value=\"0\"> Нет</label>"
		. "</td></tr>";

	echo "<tr><td>Срок жизни, в днях:</td><td><input type=\"number\" name=\"expire\" min=\"0\" max=\"999\" value=\"0\" style=\"width:400px\"></td></tr>";

	echo "<tr><td>Действие по истечении:</td><td><select name=\"action\" style=\"width:400px\">"
		. "<option value=\"d\">Деакт.</option>"
		. "<option value=\"r\">Удалить</option>"
		. "</select></td></tr>";

	echo "<tr><td>Кто может видеть блок?</td><td><select name=\"view\" style=\"width:400px\">"
		. "<option value=\"0\">Все посетители</option>"
		. "<option value=\"1\">Только пользователи</option>"
		. "<option value=\"2\">Только администраторы</option>"
		. "<option value=\"3\">Только модераторы</option>"
		. "</select></td></tr>";

	echo "<tr><td colspan=\"2\" align=\"center\"><br />"
		. "<input type=\"submit\" value=\"Создать блок\">"
		. "</td></tr>";

	echo "</table></form>";
}

function BlocksAdd(): void {
	global $prefix;

	$title     = trim((string)($_POST['title'] ?? ''));
	$content   = (string)($_POST['content'] ?? '');
	$bposition = (string)($_POST['bposition'] ?? 'l');
	$active    = (int)($_POST['active'] ?? 1);
	$hide      = (string)($_POST['hide'] ?? 'yes');
	$blockfile = trim((string)($_POST['blockfile'] ?? ''));
	$view      = (int)($_POST['view'] ?? 0);
	$expireDays= (int)($_POST['expire'] ?? 0);
	$action    = (string)($_POST['action'] ?? 'd');

	$blockwhere = $_POST['blockwhere'] ?? [];
	if (!is_array($blockwhere)) $blockwhere = [];
	$which = blocks_normalize_where($blockwhere);

	if ($blockfile !== '' && $title === '') {
		$title = str_replace(['block-', '.php', '_'], ['', '', ' '], $blockfile);
	}

	if ($content === '' && $blockfile === '') {
		stdmsg('Ошибка', 'Блок не может быть пустым!', 'error');
		return;
	}

	// вес (следующий в позиции)
	$r = sql_query("SELECT COALESCE(MAX(weight),0) AS mw FROM {$prefix}_blocks WHERE bposition=" . sqlesc($bposition)) or sqlerr(__FILE__, __LINE__);
	$row = mysqli_fetch_assoc($r);
	mysqli_free_result($r);
	$weight = (int)($row['mw'] ?? 0) + 1;

	$expire = 0;
	if ($expireDays > 0) {
		$expire = time() + ($expireDays * 86400);
	}

	// bkey/btime — пустые (как у тебя)
	$bkey  = '';
	$btime = '';

	// ВАЖНО: явные колонки
	sql_query("
		INSERT INTO {$prefix}_blocks
			(bkey, title, content, bposition, weight, active, btime, blockfile, view, expire, action, `which`, allow_hide)
		VALUES
			(" . sqlesc($bkey) . ",
			 " . sqlesc($title) . ",
			 " . sqlesc($content) . ",
			 " . sqlesc($bposition) . ",
			 " . (int)$weight . ",
			 " . (int)$active . ",
			 " . sqlesc($btime) . ",
			 " . sqlesc($blockfile) . ",
			 " . (int)$view . ",
			 " . (int)$expire . ",
			 " . sqlesc($action) . ",
			 " . sqlesc($which) . ",
			 " . sqlesc($hide) . "
			)
	") or sqlerr(__FILE__, __LINE__);

	blocks_redirect_list();
}

function BlocksEdit(): void {
	global $prefix, $allowed_modules;

	$bid = (int)($_GET['bid'] ?? 0);
	if ($bid <= 0) {
		stdmsg('Ошибка', 'Неверный ID блока', 'error');
		return;
	}

	BlocksNavi();

	$res = sql_query("
		SELECT bid, bkey, title, content, bposition, weight, active, allow_hide, blockfile, view, expire, action, `which`
		FROM {$prefix}_blocks
		WHERE bid=" . (int)$bid . "
		LIMIT 1
	") or sqlerr(__FILE__, __LINE__);

	$row = mysqli_fetch_assoc($res);
	mysqli_free_result($res);

	if (!$row) {
		stdmsg('Ошибка', 'Блок не найден!', 'error');
		return;
	}

	$title     = (string)$row['title'];
	$content   = (string)$row['content'];
	$bposition = (string)$row['bposition'];
	$weight    = (int)$row['weight'];
	$active    = (int)$row['active'];
	$allow_hide= (string)$row['allow_hide'];
	$blockfile = (string)$row['blockfile'];
	$view      = (int)$row['view'];
	$expire    = (int)$row['expire'];
	$action    = (string)$row['action'];
	$which     = (string)$row['which'];
	$bkey      = (string)$row['bkey'];

	$where_mas = blocks_parse_where($which);

	echo "<h2>Блок: " . h($title) . "</h2>";
	echo "<form action=\"" . admin_url('BlocksEditSave') . "\" method=\"post\">";
	echo "<table border=\"0\" align=\"center\">";

	echo "<tr><td>Название:</td><td><input type=\"text\" name=\"title\" maxlength=\"60\" style=\"width:400px\" value=\"" . h($title) . "\"></td></tr>";

	// если это файл-блок — даём выбрать файл, иначе textarea
	if ($blockfile !== '') {
		echo "<tr><td>Из файла:</td><td><input type=\"text\" name=\"blockfile\" style=\"width:400px\" value=\"" . h($blockfile) . "\"></td></tr>";
		echo "<tr><td>Содержимое:</td><td><i>Редактирование содержимого отключено для file-block. Меняй файл в BlocksFileEdit.</i></td></tr>";
	} else {
		echo "<tr><td>Содержимое:</td><td><textarea name=\"content\" cols=\"65\" rows=\"15\" style=\"width:400px\">" . h($content) . "</textarea></td></tr>";
	}

	echo "<tr><td>Позиция:</td><td><select name=\"bposition\" style=\"width:400px\">";
	foreach (blocks_positions() as $k => $v) {
		$sel = ($k === $bposition) ? " selected" : "";
		echo "<option value=\"" . h($k) . "\"{$sel}>" . h($v) . "</option>";
	}
	echo "</select></td></tr>";

	// Где показывать
	echo "<tr><td>Отображать блок на страницах:</td><td align=\"center\">";
	echo "<table border=\"0\" cellpadding=\"3\" cellspacing=\"0\" align=\"center\" style=\"width:400px\"><tr>";

	$chk = in_array('ihome', $where_mas, true) ? " checked" : "";
	echo "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"ihome\"{$chk}></td><td>Главная</td>";

	$a = 1;
	foreach ($allowed_modules as $name => $t) {
		$chk = in_array((string)$name, $where_mas, true) ? " checked" : "";
		$display = str_replace("_", " ", strip_tags((string)$t));
		echo "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"" . h((string)$name) . "\"{$chk}></td><td>" . h($display) . "</td>";
		if ($a === 2) { echo "</tr><tr>"; $a = 0; }
		$a++;
	}

	$chkAll   = in_array('all', $where_mas, true) ? " checked" : "";
	$chkHome  = in_array('home', $where_mas, true) ? " checked" : "";
	$chkInfly = in_array('infly', $where_mas, true) ? " checked" : "";

	echo "</tr><tr>"
		. "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"all\"{$chkAll}></td><td><b>Все страницы</b></td>"
		. "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"home\"{$chkHome}></td><td><b>Только главная</b></td>"
		. "<td><input type=\"checkbox\" name=\"blockwhere[]\" value=\"infly\"{$chkInfly}></td><td><b>Всплывающий блок</b></td>"
		. "</tr></table></td></tr>";

	// hide
	$h1 = ($allow_hide === 'yes') ? " checked" : "";
	$h2 = ($allow_hide === 'no') ? " checked" : "";
	echo "<tr><td>Скрываемый?</td><td>"
		. "<label><input type=\"radio\" name=\"hide\" value=\"yes\"{$h1}> Да</label>&nbsp;&nbsp;"
		. "<label><input type=\"radio\" name=\"hide\" value=\"no\"{$h2}> Нет</label>"
		. "</td></tr>";

	// active
	$a1 = ($active === 1) ? " checked" : "";
	$a2 = ($active === 0) ? " checked" : "";
	echo "<tr><td>Активный?</td><td>"
		. "<label><input type=\"radio\" name=\"active\" value=\"1\"{$a1}> Да</label>&nbsp;&nbsp;"
		. "<label><input type=\"radio\" name=\"active\" value=\"0\"{$a2}> Нет</label>"
		. "</td></tr>";

	// expire
	$expireDaysLeft = 0;
	if ($expire > 0) {
		$expireDaysLeft = (int)floor(max(0, $expire - time()) / 86400);
	}
	echo "<tr><td>Срок жизни, в днях:</td><td><input type=\"number\" name=\"expire\" min=\"0\" max=\"999\" value=\"" . (int)$expireDaysLeft . "\" style=\"width:400px\"></td></tr>";

	// action
	$selD = ($action === 'd') ? " selected" : "";
	$selR = ($action === 'r') ? " selected" : "";
	echo "<tr><td>Действие по истечении:</td><td><select name=\"action\" style=\"width:400px\">"
		. "<option value=\"d\"{$selD}>Деакт.</option>"
		. "<option value=\"r\"{$selR}>Удалить</option>"
		. "</select></td></tr>";

	// view
	$view_names = blocks_view_names();
	echo "<tr><td>Кто может видеть блок?</td><td><select name=\"view\" style=\"width:400px\">";
	foreach ($view_names as $k => $v) {
		$sel = ($view === (int)$k) ? " selected" : "";
		echo "<option value=\"" . (int)$k . "\"{$sel}>" . h($v) . "</option>";
	}
	echo "</select></td></tr>";

	echo "</table><br>";

	echo "<center>"
		. "<input type=\"hidden\" name=\"bid\" value=\"" . (int)$bid . "\">"
		. "<input type=\"hidden\" name=\"weight\" value=\"" . (int)$weight . "\">"
		. "<input type=\"hidden\" name=\"bkey\" value=\"" . h($bkey) . "\">"
		. "<input type=\"submit\" value=\"Сохранить\">"
		. "</center>";

	echo "</form>";
}

function BlocksEditSave(): void {
	global $prefix;

	$bid      = (int)($_POST['bid'] ?? 0);
	if ($bid <= 0) {
		stdmsg('Ошибка', 'Неверный ID блока', 'error');
		return;
	}

	$title     = trim((string)($_POST['title'] ?? ''));
	$content   = (string)($_POST['content'] ?? '');
	$bposition = (string)($_POST['bposition'] ?? 'l');
	$active    = (int)($_POST['active'] ?? 1);
	$hide      = (string)($_POST['hide'] ?? 'yes');
	$blockfile = trim((string)($_POST['blockfile'] ?? ''));
	$view      = (int)($_POST['view'] ?? 0);
	$expireDays= (int)($_POST['expire'] ?? 0);
	$action    = (string)($_POST['action'] ?? 'd');

	$blockwhere = $_POST['blockwhere'] ?? [];
	if (!is_array($blockwhere)) $blockwhere = [];
	$which = blocks_normalize_where($blockwhere);

	if ($title === '') {
		stdmsg('Ошибка', 'Название не может быть пустым', 'error');
		return;
	}

	$expire = 0;
	if ($expireDays > 0) {
		$expire = time() + ($expireDays * 86400);
	}

	// content обновляем только если не file-block
	$res = sql_query("SELECT blockfile FROM {$prefix}_blocks WHERE bid=" . (int)$bid . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
	$row = mysqli_fetch_assoc($res);
	mysqli_free_result($res);
	$isFile = !empty($row['blockfile']);

	$sql = "UPDATE {$prefix}_blocks SET "
		. "title=" . sqlesc($title) . ", "
		. "bposition=" . sqlesc($bposition) . ", "
		. "active=" . (int)$active . ", "
		. "allow_hide=" . sqlesc($hide) . ", "
		. "view=" . (int)$view . ", "
		. "expire=" . (int)$expire . ", "
		. "action=" . sqlesc($action) . ", "
		. "`which`=" . sqlesc($which);

	if (!$isFile) {
		$sql .= ", content=" . sqlesc($content);
	} else {
		// если это file-block — разрешим менять имя файла
		$sql .= ", blockfile=" . sqlesc($blockfile);
	}

	$sql .= " WHERE bid=" . (int)$bid;

	sql_query($sql) or sqlerr(__FILE__, __LINE__);

	blocks_redirect_list();
}

function BlocksChange(): void {
	global $prefix;

	$bid = (int)($_GET['bid'] ?? 0);
	if ($bid <= 0) {
		blocks_redirect_list();
	}

	$res = sql_query("SELECT active FROM {$prefix}_blocks WHERE bid=" . (int)$bid . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
	$row = mysqli_fetch_assoc($res);
	mysqli_free_result($res);

	$active = (int)($row['active'] ?? 0);
	$new = $active ? 0 : 1;

	sql_query("UPDATE {$prefix}_blocks SET active=" . (int)$new . " WHERE bid=" . (int)$bid) or sqlerr(__FILE__, __LINE__);

	blocks_redirect_list();
}

function BlocksDelete(): void {
	global $prefix;

	$bid = (int)($_GET['bid'] ?? 0);
	if ($bid <= 0) {
		blocks_redirect_list();
	}

	// не удаляем системные
	$res = sql_query("SELECT bkey FROM {$prefix}_blocks WHERE bid=" . (int)$bid . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
	$row = mysqli_fetch_assoc($res);
	mysqli_free_result($res);

	if (!empty($row['bkey'])) {
		stdmsg('Ошибка', 'Системный блок нельзя удалить', 'error');
		return;
	}

	sql_query("DELETE FROM {$prefix}_blocks WHERE bid=" . (int)$bid) or sqlerr(__FILE__, __LINE__);
	blocks_redirect_list();
}

function BlocksShow(): void {
	global $prefix;

	$bid = (int)($_GET['bid'] ?? 0);
	if ($bid <= 0) {
		stdmsg('Ошибка', 'Неверный ID блока', 'error');
		return;
	}

	BlocksNavi();

	$res = sql_query("SELECT title, content, blockfile FROM {$prefix}_blocks WHERE bid=" . (int)$bid . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
	$row = mysqli_fetch_assoc($res);
	mysqli_free_result($res);

	if (!$row) {
		stdmsg('Ошибка', 'Блок не найден', 'error');
		return;
	}

	echo "<h2>Просмотр: " . h((string)$row['title']) . "</h2>";
	echo "<div style=\"border:1px solid #666;padding:10px;\">";

	$blockfile = (string)($row['blockfile'] ?? '');
	if ($blockfile !== '') {
		echo "<b>Файл:</b> " . h($blockfile) . "<br><br>";
		$path = rtrim(BLOCKS_DIR, '/\\') . '/' . $blockfile;
		if (is_file($path)) {
			echo "<pre style=\"white-space:pre-wrap;\">" . h((string)file_get_contents($path)) . "</pre>";
		} else {
			echo "<font color=\"red\">Файл не найден: " . h($path) . "</font>";
		}
	} else {
		echo (string)$row['content']; // это HTML блок (как в старом движке)
	}

	echo "</div>";
}

function BlocksOrder(): void {
	global $prefix;

	$weightrep = (int)($_GET['weightrep'] ?? 0);
	$weight    = (int)($_GET['weight'] ?? 0);
	$bidrep    = (int)($_GET['bidrep'] ?? 0);
	$bidori    = (int)($_GET['bidori'] ?? 0);

	if ($bidrep <= 0 || $bidori <= 0) {
		blocks_redirect_list();
	}

	sql_query("UPDATE {$prefix}_blocks SET weight=" . (int)$weight . " WHERE bid=" . (int)$bidrep) or sqlerr(__FILE__, __LINE__);
	sql_query("UPDATE {$prefix}_blocks SET weight=" . (int)$weightrep . " WHERE bid=" . (int)$bidori) or sqlerr(__FILE__, __LINE__);

	blocks_redirect_list();
}

function BlocksFixweight(): void {
	global $prefix;

	$positions = array_keys(blocks_positions());

	foreach ($positions as $pos) {
		$res = sql_query("SELECT bid FROM {$prefix}_blocks WHERE bposition=" . sqlesc($pos) . " ORDER BY weight ASC") or sqlerr(__FILE__, __LINE__);
		$w = 0;
		while ($row = mysqli_fetch_assoc($res)) {
			$w++;
			$bid = (int)$row['bid'];
			sql_query("UPDATE {$prefix}_blocks SET weight=" . (int)$w . " WHERE bid=" . (int)$bid) or sqlerr(__FILE__, __LINE__);
		}
		mysqli_free_result($res);
	}

	blocks_redirect_list();
}

/**
 * Создать "файл блока" (block-*.php) — простая форма
 */
function BlocksFile(): void {
	BlocksNavi();

	echo "<h2>Создать блок из текстового файла</h2>";
	echo "<form action=\"" . admin_url('BlocksbfEdit') . "\" method=\"post\">";
	echo "<table border=\"0\" align=\"center\">";
	echo "<tr><td>Имя файла:</td><td><input type=\"text\" name=\"bf\" style=\"width:400px\" maxlength=\"200\" placeholder=\"block-myblock.php\"></td></tr>";
	echo "<tr><td>Тип:</td><td>"
		. "<label><input type=\"radio\" name=\"flag\" value=\"php\" checked> PHP</label>&nbsp;&nbsp;"
		. "<label><input type=\"radio\" name=\"flag\" value=\"html\"> HTML</label>"
		. "</td></tr>";
	echo "<tr><td colspan=\"2\" align=\"center\"><br /><input type=\"submit\" value=\"Создать / Редактировать файл\"></td></tr>";
	echo "</table></form>";
}

/**
 * Создание/редактирование содержимого файла блока
 */
function BlocksbfEdit(): void {
	BlocksNavi();

	$bf = trim((string)($_POST['bf'] ?? ''));
	$flag = (string)($_POST['flag'] ?? 'php');

	if ($bf === '') {
		stdmsg('Ошибка', 'Укажи имя файла', 'error');
		return;
	}

	// Нормализуем имя
	$bf = basename($bf);
	if (!preg_match('/^block\-[a-z0-9\_\-]+\.php$/i', $bf)) {
		stdmsg('Ошибка', 'Имя файла должно быть вида block-name.php (латиница/цифры/_/-)', 'error');
		return;
	}

	$path = rtrim(BLOCKS_DIR, '/\\') . '/' . $bf;

	$tpl = '';
	if (is_file($path)) {
		$tpl = (string)file_get_contents($path);
	} else {
		$tpl = ($flag === 'html')
			? "<div>HTML block</div>"
			: "<?php\n// PHP block\n// echo 'Hello';\n";
	}

	echo "<h2>Редактор файла: " . h($bf) . "</h2>";
	echo "<form action=\"" . admin_url('BlocksFileSave') . "\" method=\"post\">";
	echo "<input type=\"hidden\" name=\"bf\" value=\"" . h($bf) . "\">";
	echo "<textarea name=\"filecontent\" cols=\"120\" rows=\"25\" style=\"width:95%;\">" . h($tpl) . "</textarea><br><br>";
	echo "<input type=\"submit\" value=\"Сохранить файл\" class=\"btn\">";
	echo "</form>";
}

function BlocksFileSave(): void {
	$bf = trim((string)($_POST['bf'] ?? ''));
	$content = (string)($_POST['filecontent'] ?? '');

	$bf = basename($bf);
	if ($bf === '' || !preg_match('/^block\-[a-z0-9\_\-]+\.php$/i', $bf)) {
		stdmsg('Ошибка', 'Неверное имя файла', 'error');
		return;
	}

	$dir = rtrim(BLOCKS_DIR, '/\\');
	if (!is_dir($dir)) {
		stdmsg('Ошибка', 'Папка blocks не найдена: ' . h($dir), 'error');
		return;
	}

	$path = $dir . '/' . $bf;

	// Пишем атомарно
	$tmp = $path . '.tmp.' . time();
	if (file_put_contents($tmp, $content) === false) {
		stdmsg('Ошибка', 'Не удалось записать временный файл', 'error');
		return;
	}
	@chmod($tmp, 0644);
	if (!@rename($tmp, $path)) {
		@unlink($tmp);
		stdmsg('Ошибка', 'Не удалось заменить файл', 'error');
		return;
	}

	header('Location: ' . admin_url('BlocksFileEdit', ['bf' => $bf]));
	exit;
}

/**
 * Список файлов + переход в редактор
 */
function BlocksFileEdit(): void {
	BlocksNavi();

	$bf = trim((string)($_GET['bf'] ?? ''));

	echo "<h2>Редактирование файлов блоков</h2>";

	$files = [];
	$dir = @opendir(BLOCKS_DIR);
	if ($dir) {
		while (($file = readdir($dir)) !== false) {
			if (preg_match('/^block\-(.+)\.php$/', $file)) $files[] = $file;
		}
		closedir($dir);
	}
	sort($files);

	echo "<table border=\"0\" cellpadding=\"3\" cellspacing=\"1\" width=\"100%\">";
	echo "<tr><td class=\"colhead\">Файл</td><td class=\"colhead\">Действие</td></tr>";

	foreach ($files as $f) {
		echo "<tr>";
		echo "<td>" . h($f) . "</td>";
		echo "<td><a href=\"" . admin_url('BlocksFileEdit', ['bf' => $f, 'edit' => 1]) . "\">Открыть</a></td>";
		echo "</tr>";
	}

	echo "</table>";

	if ($bf !== '' && !empty($_GET['edit'])) {
		$path = rtrim(BLOCKS_DIR, '/\\') . '/' . basename($bf);
		if (!is_file($path)) {
			echo "<br><font color=\"red\">Файл не найден: " . h($path) . "</font>";
			return;
		}

		$txt = (string)file_get_contents($path);

		echo "<hr>";
		echo "<h3>Редактор: " . h(basename($bf)) . "</h3>";
		echo "<form action=\"" . admin_url('BlocksFileSave') . "\" method=\"post\">";
		echo "<input type=\"hidden\" name=\"bf\" value=\"" . h(basename($bf)) . "\">";
		echo "<textarea name=\"filecontent\" cols=\"120\" rows=\"25\" style=\"width:95%;\">" . h($txt) . "</textarea><br><br>";
		echo "<input type=\"submit\" value=\"Сохранить файл\" class=\"btn\">";
		echo "</form>";
	}
}

// ------------------------------------------------------------
// Router
// ------------------------------------------------------------
switch ($op) {
	case 'BlocksAdmin':
		BlocksAdmin();
		break;

	case 'BlocksNew':
		BlocksNew();
		break;

	case 'BlocksAdd':
		BlocksAdd();
		break;

	case 'BlocksEdit':
		BlocksEdit();
		break;

	case 'BlocksEditSave':
		BlocksEditSave();
		break;

	case 'BlocksChange':
		BlocksChange();
		break;

	case 'BlocksDelete':
		BlocksDelete();
		break;

	case 'BlocksShow':
		BlocksShow();
		break;

	case 'BlocksOrder':
		BlocksOrder();
		break;

	case 'BlocksFixweight':
		BlocksFixweight();
		break;

	case 'BlocksFile':
		BlocksFile();
		break;

	case 'BlocksbfEdit':
		BlocksbfEdit();
		break;

	case 'BlocksFileEdit':
		BlocksFileEdit();
		break;

	case 'BlocksFileSave':
		BlocksFileSave();
		break;

	default:
		BlocksAdmin();
		break;
}
