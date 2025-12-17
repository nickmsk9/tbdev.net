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


declare(strict_types=1);

require_once __DIR__ . "/include/bittorrent.php";

dbconn(false);
loggedinorreturn();

if (get_user_class() < UC_UPLOADER) {
	stderr($tracker_lang['error'], $tracker_lang['access_denied']);
}

$action = isset($_GET['action']) ? (string)$_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

//
// POST: массовая отправка
//
if ($method === 'POST') {
	$msg = trim((string)($_POST['msg'] ?? ''));
	if ($msg === '') {
		stderr($tracker_lang['error'], "Введите текст сообщения");
	}

	$subject = sqlesc("Трекер определил вас несоединябельного");
	$now     = get_date_time();
	$dt      = sqlesc($now);

	// Берём уникальных пользователей (один запрос)
	$q = sql_query("SELECT DISTINCT userid FROM peers WHERE connectable = 'no'") or sqlerr(__FILE__, __LINE__);

	while ($row = mysqli_fetch_assoc($q)) {
		$uid = (int)$row['userid'];
		if ($uid > 0) {
			send_pm(0, $uid, $now, $subject, $msg);
		}
	}
	mysqli_free_result($q);

	// Лог отправки
	sql_query("INSERT INTO notconnectablepmlog (`user`, `date`) VALUES (" . (int)$CURUSER['id'] . ", $dt)") or sqlerr(__FILE__, __LINE__);

	header("Location: findnotconnectable.php");
	exit;
}

//
// GET actions
//
switch ($action) {

	// -------------------------
	// LIST: список несоединябельных пиров
	// -------------------------
	case 'list': {
		stdhead("Peers that are unconnectable");

		print("<a href=\"findnotconnectable.php?action=sendpm\"><h3>Послать всем несоединябельным пирам массовое ПМ</h3></a>");
		print("<a href=\"findnotconnectable.php\"><h3>Просмотреть лог (Проверьте это прежде чем отправлять ЛС пользователям)</h3></a>");
		print("<h1>Пиры с которыми нельзя соединиться</h1>");
		print("Это только те пользователи которые сейчас активны на торрентах.");
		print("<br /><font color=\"red\">*</font> означает что пользователь сидирует.<p>");

		// 1) количество уникальных userid (один запрос)
		$cq = sql_query("SELECT COUNT(DISTINCT userid) AS cnt FROM peers WHERE connectable = 'no'") or sqlerr(__FILE__, __LINE__);
		$cr = mysqli_fetch_assoc($cq);
		mysqli_free_result($cq);
		$count = (int)($cr['cnt'] ?? 0);

		print($count . " уникальных пиров с которыми нельзя соединиться.");

		// 2) список пиров + username одним JOIN (убираем N+1)
		$res = sql_query("
			SELECT p.userid, p.seeder, p.torrent, p.agent, u.username
			FROM peers AS p
			LEFT JOIN users AS u ON u.id = p.userid
			WHERE p.connectable = 'no'
			ORDER BY p.userid DESC
		") or sqlerr(__FILE__, __LINE__);

		if (mysqli_num_rows($res) === 0) {
			print("<p align=\"center\"><b>Со всеми пирами можно соединится!</b></p>\n");
			mysqli_free_result($res);
			stdfoot();
			exit;
		}

		print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");
		print("<tr>
				<td class=\"colhead\">Пользователь</td>
				<td class=\"colhead\">Торрент</td>
				<td class=\"colhead\">Клиент</td>
			  </tr>\n");

		while ($row = mysqli_fetch_assoc($res)) {
			$uid      = (int)$row['userid'];
			$tid      = (int)$row['torrent'];
			$username = (string)($row['username'] ?? ('ID: ' . $uid));
			$agent    = (string)($row['agent'] ?? '');
			$seeder   = (string)($row['seeder'] ?? 'no');

			$userHtml = htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			$agentHtml = htmlspecialchars($agent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

			print("<tr>");
			print("<td><a href=\"userdetails.php?id=$uid\">$userHtml</a></td>");

			print("<td align=\"left\"><a href=\"details.php?id=$tid&dllist=1#seeders\">$tid");
			if ($seeder === 'yes') {
				print("<font color=\"red\">*</font>");
			}
			print("</a></td>");

			print("<td align=\"left\">$agentHtml</td>");
			print("</tr>\n");
		}
		mysqli_free_result($res);

		print("</table>\n");
		stdfoot();
		exit;
	}

	// -------------------------
	// SENDPM: форма отправки
	// -------------------------
	case 'sendpm': {
		stdhead("Пиры с которыми нельзя соединиться");

		// default message
	$body = "Уважаемый пользователь,\n\n"
      . "Система трекера обнаружила, что ваш клиент находится за файрволом или использует NAT, что препятствует установке входящих соединений.\n\n"
      . "Последствия данной проблемы:\n"
      . "• Другие участники раздачи не могут подключиться к вашему клиенту\n"
      . "• Вы можете подключаться только к ним (односторонние соединения)\n"
      . "• Если два пользователя имеют одинаковую проблему, они вообще не смогут обмениваться данными\n"
      . "• Это значительно снижает общую скорость раздачи и вашу эффективность\n\n"
      . "Решение проблемы:\n"
      . "1. Настройте проброс портов в вашем роутере/файрволе\n"
      . "2. Убедитесь, что порт, указанный в клиенте, открыт для входящих соединений\n"
      . "3. Включите UPnP в настройках клиента (если поддерживается)\n"
      . "4. Используйте порт в диапазоне 49152-65535\n"
      . "5. Настройте исключение в антивирусе/файрволе\n\n"
      . "Рекомендации:\n"
      . "• Проверьте документацию к вашему роутеру\n"
      . "• Посетите наш форум в разделе технической поддержки\n"
      . "• Обратитесь к администрации за помощью\n"
      . "• Используйте сайт portforward.com для инструкций по конкретным моделям роутеров\n\n"
      . "Для поддержания здоровой сети важно, чтобы как можно больше пользователей были доступны для входящих соединений.\n\n"
      . "С уважением,\n"
      . "Администрация трекера";

		$returnto = '';
		if (!empty($_GET['returnto'])) {
			$returnto = (string)$_GET['returnto'];
		} elseif (!empty($_SERVER['HTTP_REFERER'])) {
			$returnto = (string)$_SERVER['HTTP_REFERER'];
		}
		$returntoHtml = htmlspecialchars($returnto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$bodyHtml     = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		?>
		<table class="main" width="750" border="0" cellspacing="0" cellpadding="0">
			<tr><td class="embedded">
				<div align="center">
					<h1>Общее сообщение для пользователей с которыми нельзя соединиться</h1>

					<form method="post" action="findnotconnectable.php">
						<?php if ($returnto !== ''): ?>
							<input type="hidden" name="returnto" value="<?=$returntoHtml?>">
						<?php endif; ?>

						<table cellspacing="0" cellpadding="5">
							<tr>
								<td>
									Отправить массовое сообщение всем недоступным пользователям<br />
									<table style="border: 0" width="100%" cellpadding="0" cellspacing="0">
										<tr><td style="border: 0">&nbsp;</td><td style="border: 0">&nbsp;</td></tr>
									</table>
								</td>
							</tr>

							<tr>
								<td><textarea name="msg" cols="120" rows="15"><?=$bodyHtml?></textarea></td>
							</tr>

							<tr>
								<td align="center"><input type="submit" value="Отправить" class="btn"></td>
							</tr>
						</table>
					</form>
				</div>
			</td></tr>
		</table>
		<?php
		stdfoot();
		exit;
	}

	// -------------------------
	// DEFAULT: лог отправок
	// -------------------------
	default: {
		stdhead("Лог общих сообщений для файрволеных");

		// JOIN чтобы не делать запрос username на каждую строку
		$getlog = sql_query("
			SELECT l.`user`, l.`date`, u.username
			FROM notconnectablepmlog AS l
			LEFT JOIN users AS u ON u.id = l.`user`
			ORDER BY l.`date` DESC
			LIMIT 10
		") or sqlerr(__FILE__, __LINE__);

		print("<h1>Лог общих сообщений для файрволеных</h1>");
		print("<a href=\"findnotconnectable.php?action=sendpm\"><h3>Послать общее сообщение для пользователей с которыми нельзя соединиться</h3></a>");
		print("<a href=\"findnotconnectable.php?action=list\"><h3>Показать пользователей с которыми нельзя соединиться</h3></a>");
		print("<br />Пожалуста не отправляйте ЛС слишком часто. Мы не хотим спамить пользователей, только дадим знать что с ними нельзя соединиться.<p>");
		print("<br />Каждую неделю будет нормально.<p>");

		print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");
		print("<tr><td class=\"colhead\">Пользователь</td><td class=\"colhead\">Дата</td><td class=\"colhead\">Прошло</td></tr>");

		while ($row = mysqli_fetch_assoc($getlog)) {
			$uid = (int)$row['user'];
			$date = (string)$row['date'];
			$username = (string)($row['username'] ?? ('ID: ' . $uid));

			$elapsed = get_elapsed_time(sql_timestamp_to_unix_timestamp($date));

			$userHtml = htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			$dateHtml = htmlspecialchars($date, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

			print("<tr>");
			print("<td class=\"colhead\"><a href=\"userdetails.php?id=$uid\">$userHtml</a></td>");
			print("<td class=\"colhead\">$dateHtml</td>");
			print("<td>$elapsed назад</td>");
			print("</tr>\n");
		}
		mysqli_free_result($getlog);

		print("</table>");

		stdfoot();
		exit;
	}
}
