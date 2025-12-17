<?php
declare(strict_types=1);

if (!defined('ADMIN_FILE')) {
	die('Illegal File Access');
}

/**
 * ВАЖНО:
 * Этот модуль должен выполняться ТОЛЬКО при op=iUsers
 */
$op = (string)($_GET['op'] ?? $_POST['op'] ?? '');
if ($op !== 'iUsers') {
	return;
}

function ih(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


function iUsers(): void
{
	global $CURUSER;

	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

	if ($method === 'POST') {
		$iname = trim((string)($_POST['iname'] ?? ''));
		$ipass = trim((string)($_POST['ipass'] ?? ''));
		$imail = trim((string)($_POST['imail'] ?? ''));

		if ($iname === '') {
			stdmsg("Ошибка", "Укажите пользователя!", "error");
			return;
		}

		$res = sql_query("SELECT id, class FROM users WHERE username=" . sqlesc($iname) . " LIMIT 1")
			or sqlerr(__FILE__, __LINE__);

		if (mysqli_num_rows($res) === 0) {
			stdmsg("Ошибка", "Пользователь не найден!", "error");
			return;
		}

		$row = mysqli_fetch_assoc($res);
		$uid = (int)$row['id'];
		$iclass = (int)$row['class'];

		if (get_user_class() <= $iclass) {
			stdmsg(
				"Ошибка",
				"Вы не можете редактировать этого пользователя! Вы должны иметь класс выше редактируемого пользователя.",
				"error"
			);
			write_log('Администратор ' . $CURUSER['username'] . ' попытался изменить данные пользователя ' . $iname . ' выше своего класса!', 'red', 'error');
			return;
		}

		$updates = [];

		// пароль
		if ($ipass !== '') {
			$secret = mksecret();
			$hash   = md5($secret . $ipass . $secret);

			$updates[] = "secret=" . sqlesc($secret);
			$updates[] = "passhash=" . sqlesc($hash);
		}

		// email
		if ($imail !== '') {
			if (!validemail($imail)) {
				stdmsg("Ошибка", "Email некорректный!", "error");
				return;
			}
			$updates[] = "email=" . sqlesc($imail);
		}

		if (!$updates) {
			stdmsg("Предупреждение", "Не указаны данные для изменения!", "warning");
			return;
		}

		sql_query("UPDATE users SET " . implode(', ', $updates) . " WHERE id=" . (int)$uid . " LIMIT 1")
			or sqlerr(__FILE__, __LINE__);

		$message = "Данные пользователя успешно обновлены<br />";
		$message .= "Логин пользователя: " . h($iname) . "<br />";

		if ($ipass !== '') {
			$message .= "Новый пароль: " . h($ipass) . "<br />";
		}
		if ($imail !== '') {
			$message .= "Новый email: " . h($imail);
		}

		stdmsg("Успешное обновление данных пользователя", $message);
		write_log('Администратор ' . $CURUSER['username'] . ' изменил данные пользователя ' . $iname, 'green', 'admin');

		return;
	}

	// GET: форма
	echo "<form method=\"post\" action=\"admincp.php?op=iUsers\">"
		. "<table border=\"0\" cellspacing=\"0\" cellpadding=\"3\">"
		. "<tr><td class=\"colhead\" colspan=\"2\">Изменение пароля</td></tr>"
		. "<tr><td><b>Пользователь</b></td><td><input name=\"iname\" type=\"text\" required></td></tr>"
		. "<tr><td><b>Новый пароль</b></td><td><input name=\"ipass\" type=\"password\" autocomplete=\"new-password\"></td></tr>"
		. "<tr><td><b>Новый email</b></td><td><input name=\"imail\" type=\"email\"></td></tr>"
		. "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"Изменить\"></td></tr>"
		. "</table>"
		. "</form>";
}

iUsers();
