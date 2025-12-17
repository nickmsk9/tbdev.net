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

require_once __DIR__ . '/include/bittorrent.php';

dbconn();
loggedinorreturn();

if (get_user_class() < UC_MODERATOR) {
    stderr($tracker_lang['error'], $tracker_lang['access_denied']);
}

$action = (string)($_REQUEST['action'] ?? '');
$pollid = (int)($_REQUEST['pollid'] ?? 0);
$returnto = (string)($_REQUEST['returnto'] ?? 'main');

$poll = [
    'id' => 0,
    'question' => '',
    'sort' => 'yes',
];
for ($i = 0; $i < 20; $i++) {
    $poll["option{$i}"] = '';
}

if ($action === 'edit') {
    if (!is_valid_id($pollid)) {
        stderr($tracker_lang['error'], $tracker_lang['invalid_id']);
    }
    $res = sql_query("SELECT * FROM polls WHERE id=" . (int)$pollid) or sqlerr(__FILE__, __LINE__);
    if (mysqli_num_rows($res) === 0) {
        stderr($tracker_lang['error'], 'Опрос с таким ID не найден.');
    }
    $poll = array_merge($poll, (array)mysqli_fetch_assoc($res));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'edit' && !is_valid_id($pollid)) {
        stderr($tracker_lang['error'], $tracker_lang['invalid_id']);
    }

    $question = htmlspecialchars_uni((string)($_POST['question'] ?? ''));
    $sort = ((string)($_POST['sort'] ?? 'yes') === 'no') ? 'no' : 'yes';

    $options = [];
    for ($i = 0; $i < 20; $i++) {
        $options[$i] = htmlspecialchars_uni((string)($_POST["option{$i}"] ?? ''));
    }

    if ($question === '' || $options[0] === '' || $options[1] === '') {
        stderr($tracker_lang['error'], 'Заполните вопрос и минимум два варианта (1 и 2).');
    }

    if (is_valid_id($pollid)) {
        $sets = [
            "question=" . sqlesc($question),
            "sort=" . sqlesc($sort),
        ];
        for ($i = 0; $i < 20; $i++) {
            $sets[] = "option{$i}=" . sqlesc($options[$i]);
        }

        sql_query("UPDATE polls SET " . implode(', ', $sets) . " WHERE id=" . (int)$pollid)
            or sqlerr(__FILE__, __LINE__);
    } else {
        $fields = ['added', 'question'];
        $values = [sqlesc(get_date_time()), sqlesc($question)];

        for ($i = 0; $i < 20; $i++) {
            $fields[] = "option{$i}";
            $values[] = sqlesc($options[$i]);
        }

        $fields[] = 'sort';
        $values[] = sqlesc($sort);

        sql_query("INSERT INTO polls (" . implode(',', $fields) . ") VALUES (" . implode(',', $values) . ")")
            or sqlerr(__FILE__, __LINE__);
        $pollid = (int)mysqli_insert_id($GLOBALS['GLOBALS']['___mysqli_ston'] ?? $GLOBALS['mysqli'] ?? null);
        // если у тебя есть функция/хелпер для last insert id — лучше использовать её
    }

    if ($returnto === 'main') {
        header("Location: {$DEFAULTBASEURL}");
    } elseif (is_valid_id($pollid)) {
        header("Location: {$DEFAULTBASEURL}/polls.php#{$pollid}");
    } else {
        header("Location: {$DEFAULTBASEURL}");
    }
    exit;
}

stdhead();

if (is_valid_id($pollid)) {
    print('<h1>Редактирование опроса</h1>');
} else {
    // предупреждение если прошлый опрос свежее 3 дней — 1 запрос
    $res = sql_query("SELECT question, added FROM polls ORDER BY added DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
    if ($res && ($arr = mysqli_fetch_assoc($res))) {
        $hours = (int)floor((gmtime() - sql_timestamp_to_unix_timestamp($arr['added'])) / 3600);
        $days  = (int)floor($hours / 24);

        if ($days < 3) {
            $hours -= $days * 24;
            $t = $days ? ($days . ' дн.') : ($hours . ' ч.');
            print('<p><font color="red"><b>Внимание: текущий опрос (<i>' . htmlspecialchars_uni($arr['question']) . '</i>) создан ' . $t . ' назад.</b></font></p>');
        }
    }
    print('<h1>Создание опроса</h1>');
}

$action_for_form = is_valid_id($pollid) ? 'edit' : 'create';
$form_action = 'makepoll.php';

print('
<form method="post" action="' . $form_action . '">
<table border="1" cellspacing="0" cellpadding="5" class="main" width="100%">

<tr>
  <td class="rowhead">Вопрос <font color="red">*</font></td>
  <td align="left"><input name="question" size="80" maxlength="255" value="' . htmlspecialchars_uni((string)$poll['question']) . '" /></td>
</tr>
');

for ($i = 0; $i < 20; $i++) {
    $n = $i + 1;
    $req = ($i < 2) ? ' <font color="red">*</font>' : '';
    print('
<tr>
  <td class="rowhead">Вариант ' . $n . $req . '</td>
  <td align="left"><input name="option' . $i . '" size="80" maxlength="40" value="' . htmlspecialchars_uni((string)($poll["option{$i}"] ?? '')) . '" /></td>
</tr>');
}

$sort_yes = (($poll['sort'] ?? 'yes') !== 'no') ? ' checked' : '';
$sort_no  = (($poll['sort'] ?? 'yes') === 'no') ? ' checked' : '';

print('
<tr>
  <td class="rowhead">Сортировать</td>
  <td>
    <label><input type="radio" name="sort" value="yes"' . $sort_yes . ' /> Да</label>
    &nbsp;&nbsp;
    <label><input type="radio" name="sort" value="no"' . $sort_no . ' /> Нет</label>
  </td>
</tr>

<tr>
  <td colspan="2" align="center">
    <input type="submit" value="' . (is_valid_id($pollid) ? 'Сохранить' : 'Создать') . '" style="height:20pt" />
  </td>
</tr>

</table>

<p><font color="red">*</font> обязательно</p>

<input type="hidden" name="pollid" value="' . (int)($poll['id'] ?? 0) . '" />
<input type="hidden" name="action" value="' . $action_for_form . '" />
<input type="hidden" name="returnto" value="' . htmlspecialchars_uni((string)($_GET['returnto'] ?? 'main')) . '" />
</form>
');

stdfoot();
