<?php
declare(strict_types=1);

if (!defined('ADMIN_FILE')) {
    die('Illegal File Access');
}

$dbname = $mysql_db ?? '';

function StatusDB(): void
{
    global $admin_file, $dbname;

    $e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // --- Получаем список таблиц текущей БД
    $res = sql_query("SHOW TABLES FROM `{$dbname}`");
    if (!$res) {
        stdmsg('Ошибка', 'Не удалось получить список таблиц.', 'error');
        return;
    }

    $allTables = [];
    while ($row = mysqli_fetch_row($res)) {
        if (!empty($row[0])) {
            $allTables[] = $row[0];
        }
    }

    if (!$allTables) {
        stdmsg('Ошибка', 'В базе данных нет таблиц.', 'error');
        return;
    }

    // --- POST параметры
    $isPost  = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    $type    = $isPost ? (string)($_POST['type'] ?? '') : '';
    $picked  = $isPost ? (array)($_POST['datatable'] ?? []) : [];

    // Валидируем выбранные таблицы: только те, что реально существуют
    $picked = array_values(array_unique(array_filter(array_map('strval', $picked))));
    $picked = array_values(array_intersect($picked, $allTables));

    // Если ничего не выбрано — работаем со всеми
    $targetTables = $picked ?: $allTables;

    // --- Рисуем форму
    $options = '';
    $selectedMap = array_flip($targetTables);

    foreach ($allTables as $t) {
        $sel = isset($selectedMap[$t]) ? ' selected' : '';
        $options .= '<option value="' . $e($t) . '"' . $sel . '>' . $e($t) . '</option>';
    }

    echo '
    <table border="0" cellspacing="0" cellpadding="3" align="center">
      <form method="post" action="' . $e($admin_file) . '.php?op=StatusDB">
        <tr>
          <td>
            <select name="datatable[]" size="12" multiple="multiple" style="width:400px">
              ' . $options . '
            </select>
            <div class="small" style="margin-top:6px;">
              Подсказка: если ничего не выбрать — будет обработана вся база.
            </div>
          </td>
          <td valign="top">
            <table border="0" cellspacing="0" cellpadding="3">
              <tr>
                <td valign="top"><input type="radio" name="type" value="Optimize" ' . ($type !== 'Repair' ? 'checked' : '') . '></td>
                <td>
                  Оптимизация базы данных<br />
                  <font class="small">
                    Выполняет OPTIMIZE TABLE для выбранных таблиц. Полезно после массовых удалений/обновлений.
                  </font>
                </td>
              </tr>
              <tr>
                <td valign="top"><input type="radio" name="type" value="Repair" ' . ($type === 'Repair' ? 'checked' : '') . '></td>
                <td>
                  Восстановление таблиц<br />
                  <font class="small">
                    Выполняет REPAIR TABLE для выбранных таблиц. Используется при проблемах с таблицами (в основном MyISAM).
                  </font>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <input type="hidden" name="op" value="StatusDB">
        <tr>
          <td colspan="2" align="center">
            <input type="submit" value="Запустить">
          </td>
        </tr>
      </form>
    </table>
    ';

    // --- Если это не POST — только форма
    if (!$isPost || ($type !== 'Optimize' && $type !== 'Repair')) {
        return;
    }

    // --- Получаем статусы всех таблиц и фильтруем по выбранным
    $statusRes = sql_query("SHOW TABLE STATUS FROM `{$dbname}`");
    if (!$statusRes) {
        stdmsg('Ошибка', 'Не удалось получить статус таблиц.', 'error');
        return;
    }

    $statusByName = [];
    while ($r = mysqli_fetch_assoc($statusRes)) {
        if (!empty($r['Name'])) {
            $statusByName[$r['Name']] = $r;
        }
    }

    $i = 0;
    $totaltotal = 0;
    $totalfree  = 0;

    if ($type === 'Optimize') {
        $rowsHtml = '';

        // Собираем валидный список таблиц (с бэктиками)
        $tablesSql = [];
        foreach ($targetTables as $t) {
            if (!isset($statusByName[$t])) {
                continue;
            }

            $row = $statusByName[$t];
            $total = (int)($row['Data_length'] ?? 0) + (int)($row['Index_length'] ?? 0);
            $free  = (int)($row['Data_free'] ?? 0);

            $totaltotal += $total;
            $totalfree  += $free;
            $i++;

            $state = ($free > 0)
                ? '<font color="#009900">можно оптимизировать</font>'
                : '<font color="#666666">ок</font>';

            $rowsHtml .= '<tr class="bgcolor1">
                <td align="center">' . $i . '</td>
                <td>' . $e($t) . '</td>
                <td>' . mksize($total) . '</td>
                <td align="center">' . $state . '</td>
                <td align="center">' . mksize($free) . '</td>
            </tr>';

            $tablesSql[] = '`' . str_replace('`', '``', $t) . '`';
        }

        if ($tablesSql) {
            sql_query('OPTIMIZE TABLE ' . implode(', ', $tablesSql));
        }

        echo '
        <center>
          <font class="option">
            Оптимизация базы данных: ' . $e($dbname) . '<br />
            Размер выбранных таблиц: ' . mksize($totaltotal) . '<br />
            Свободное место (Data_free): ' . mksize($totalfree) . '<br /><br />
          </font>
          <table border="0" cellpadding="3" cellspacing="1" width="100%">
            <tr>
              <td class="colhead" align="center">№</td>
              <td class="colhead">Таблица</td>
              <td class="colhead">Размер</td>
              <td class="colhead" align="center">Статус</td>
              <td class="colhead" align="center">Data_free</td>
            </tr>
            ' . $rowsHtml . '
          </table>
        </center>';
        return;
    }

    // --- Repair
    if ($type === 'Repair') {
        $rowsHtml = '';

        foreach ($targetTables as $t) {
            if (!isset($statusByName[$t])) {
                continue;
            }

            $row = $statusByName[$t];
            $total = (int)($row['Data_length'] ?? 0) + (int)($row['Index_length'] ?? 0);
            $totaltotal += $total;
            $i++;

            $safeTable = '`' . str_replace('`', '``', $t) . '`';
            $rres = sql_query("REPAIR TABLE {$safeTable}");

            $ok = false;
            if ($rres) {
                // MySQL обычно возвращает таблицу результатов; если запрос выполнился — считаем ок
                $ok = true;
            }

            $state = $ok
                ? '<font color="#009900">OK</font>'
                : '<font color="#FF0000">Ошибка</font>';

            $rowsHtml .= '<tr class="bgcolor1">
                <td align="center">' . $i . '</td>
                <td>' . $e($t) . '</td>
                <td>' . mksize($total) . '</td>
                <td align="center">' . $state . '</td>
            </tr>';
        }

        echo '
        <center>
          <font class="option">
            Восстановление таблиц: ' . $e($dbname) . '<br />
            Размер выбранных таблиц: ' . mksize($totaltotal) . '<br /><br />
          </font>
          <table border="0" cellpadding="3" cellspacing="1" width="100%">
            <tr>
              <td class="colhead" align="center">№</td>
              <td class="colhead">Таблица</td>
              <td class="colhead">Размер</td>
              <td class="colhead" align="center">Статус</td>
            </tr>
            ' . $rowsHtml . '
          </table>
        </center>';
        return;
    }
}

$op = (string)($_REQUEST['op'] ?? ($op ?? ''));

switch ($op) {
    case 'StatusDB':
        StatusDB();
        break;
}
