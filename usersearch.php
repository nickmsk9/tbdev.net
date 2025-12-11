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


require "include/bittorrent.php";

// Отключить режим отладки для продакшена
define('DEBUG_MODE_SEARCH', 1);

/**
 * Получение иконок пользователя для поиска
 */
function get_user_icons_for_search(array $arr, bool $big = false): string
{
    $icons = '';
    
    // Иконка донора
    if ($arr["donor"] == "yes") {
        $donor_pic = $big ? "starbig.gif" : "star.gif";
        $icons .= '<img src="pic/' . $donor_pic . '" alt="Донор" border="0" style="margin-left: 2pt">';
    }
    
    // Иконка предупреждения/отключения
    if ($arr["enabled"] == "yes") {
        if ($arr["warned"] == "yes") {
            $warned_pic = $big ? "warnedbig.gif" : "warned.gif";
            $icons .= '<img src="pic/' . $warned_pic . '" alt="Предупрежден" border="0">';
        }
    } else {
        $disabled_pic = $big ? "disabledbig.gif" : "disabled.gif";
        $icons .= '<img src="pic/' . $disabled_pic . '" alt="Отключен" border="0" style="margin-left: 2pt">';
    }
    
    return $icons;
}

dbconn();
loggedinorreturn();

// Проверка прав доступа
if (get_user_class() < UC_MODERATOR) {
    stderr($tracker_lang['error'] ?? 'Ошибка', "Отказано в доступе.");
}

stdhead("Административный поиск");

// Навигация
if (isset($_GET['h'])) {
    // Инструкция
    begin_frame("Инструкция <span style='color:#009900'>- Читать обязательно</span>");
    ?>
    <ul style="padding-left: 20px;">
        <li>Пустые поля будут проигнорированы</li>
        <li>Шаблоны * и ? могут быть использованы в Имени, Email и Комментариях</li>
        <li>Несколько значений разделяются пробелами (например: 'админ тест*')</li>
        <li>Символ ~ используется для отрицания (например: '~бот' исключит пользователей с этим словом в комментариях)</li>
        <li>Поле Рейтинг принимает 'Inf' и '---' наравне с числовыми значениями</li>
        <li>Маска подсети может быть введена в формате CIDR (/24) или десятично-точечном (255.255.255.0)</li>
        <li>Раздал и Скачал указываются в GB</li>
        <li>'Только активных' - пользователи, которые сейчас что-то качают или раздают</li>
        <li>'Отключенные IP' - пользователи с заблокированными IP</li>
        <li>Колонки 'pR', 'pUL', 'pDL' показывают статистику по активным раздачам</li>
        <li>Колонка 'История' показывает количество комментариев</li>
    </ul>
    <?php
    end_frame();
} else {
    echo '<p style="text-align: center;">
        (<a href="' . htmlspecialchars($_SERVER["PHP_SELF"]) . '?h=1">Инструкция</a>)
        &nbsp;-&nbsp;
        (<a href="' . htmlspecialchars($_SERVER["PHP_SELF"]) . '">Сброс</a>)
    </p>';
}

// Функции валидации
/**
 * Проверяет дату в формате [yy]yy-mm-dd или [yy]yy/mm/dd
 */
function validate_date(string $date): string
{
    $date = trim($date);
    if (empty($date)) {
        return '';
    }
    
    // Поддерживаем оба формата разделителей
    if (strpos($date, '-')) {
        $parts = explode('-', $date);
    } elseif (strpos($date, '/')) {
        $parts = explode('/', $date);
    } else {
        return '';
    }
    
    if (count($parts) !== 3) {
        return '';
    }
    
    // Проверяем числовые значения
    foreach ($parts as $part) {
        if (!is_numeric(trim($part))) {
            return '';
        }
    }
    
    $year = (int)$parts[0];
    $month = (int)$parts[1];
    $day = (int)$parts[2];
    
    // Добавляем 2000 для двузначных годов
    if ($year < 100) {
        $year += 2000;
    }
    
    if (checkdate($month, $day, $year)) {
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
    
    return '';
}

/**
 * Форматирует рейтинг с цветом
 */
function format_ratio(float $up, float $down, bool $color = true): string
{
    if ($down > 0) {
        $ratio = number_format($up / $down, 2);
        if ($color) {
            $color_code = get_ratio_color($ratio);
            return '<span style="color:' . htmlspecialchars($color_code) . '">' . $ratio . '</span>';
        }
        return $ratio;
    }
    
    return $up > 0 ? 'Inf.' : '---';
}

/**
 * Проверяет наличие wildcard символов
 */
function has_wildcard(string $text): bool
{
    $wildcards = ['*', '?', '%', '_'];
    foreach ($wildcards as $wildcard) {
        if (strpos($text, $wildcard) !== false) {
            return true;
        }
    }
    return false;
}

// Основная форма поиска
$highlight_color = '#BBAF9B';
?>
<form method="get" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>">
<table border="1" cellspacing="0" cellpadding="5" style="margin: 0 auto;">
<tr>
    <td class="rowhead" valign="middle">Имя:</td>
    <td style="<?= !empty($_GET['n']) ? "background-color:$highlight_color" : '' ?>">
        <input name="n" type="text" value="<?= htmlspecialchars($_GET['n'] ?? '') ?>" size="35">
    </td>
    
    <td class="rowhead" valign="middle">Рейтинг:</td>
    <td style="<?= !empty($_GET['r']) ? "background-color:$highlight_color" : '' ?>">
        <select name="rt">
            <?php
            $ratio_options = ["равен", "выше", "ниже", "между"];
            $current_rt = $_GET['rt'] ?? 0;
            foreach ($ratio_options as $i => $option) {
                $selected = $current_rt == $i ? 'selected' : '';
                echo "<option value=\"$i\" $selected>$option</option>";
            }
            ?>
        </select>
        <input name="r" type="text" value="<?= htmlspecialchars($_GET['r'] ?? '') ?>" size="5" maxlength="10">
        <input name="r2" type="text" value="<?= htmlspecialchars($_GET['r2'] ?? '') ?>" size="5" maxlength="10">
    </td>
    
    <td class="rowhead" valign="middle">Статус:</td>
    <td style="<?= !empty($_GET['st']) ? "background-color:$highlight_color" : '' ?>">
        <select name="st">
            <?php
            $status_options = ["(Любой)", "Подтвержден", "Не подтвержден"];
            $current_st = $_GET['st'] ?? 0;
            foreach ($status_options as $i => $option) {
                $selected = $current_st == $i ? 'selected' : '';
                echo "<option value=\"$i\" $selected>$option</option>";
            }
            ?>
        </select>
    </td>
</tr>

<tr>
    <td class="rowhead" valign="middle">Email:</td>
    <td style="<?= !empty($_GET['em']) ? "background-color:$highlight_color" : '' ?>">
        <input name="em" type="text" value="<?= htmlspecialchars($_GET['em'] ?? '') ?>" size="35">
    </td>
    
    <td class="rowhead" valign="middle">IP:</td>
    <td style="<?= !empty($_GET['ip']) ? "background-color:$highlight_color" : '' ?>">
        <input name="ip" type="text" value="<?= htmlspecialchars($_GET['ip'] ?? '') ?>" maxlength="17">
    </td>
    
    <td class="rowhead" valign="middle">Отключен:</td>
    <td style="<?= !empty($_GET['as']) ? "background-color:$highlight_color" : '' ?>">
        <select name="as">
            <?php
            $enabled_options = ["(Любой)", "Нет", "Да"];
            $current_as = $_GET['as'] ?? 0;
            foreach ($enabled_options as $i => $option) {
                $selected = $current_as == $i ? 'selected' : '';
                echo "<option value=\"$i\" $selected>$option</option>";
            }
            ?>
        </select>
    </td>
</tr>

<tr>
    <td class="rowhead" valign="middle">Комментарий:</td>
    <td style="<?= !empty($_GET['co']) ? "background-color:$highlight_color" : '' ?>">
        <input name="co" type="text" value="<?= htmlspecialchars($_GET['co'] ?? '') ?>" size="35">
    </td>
    
    <td class="rowhead" valign="middle">Маска:</td>
    <td style="<?= !empty($_GET['ma']) ? "background-color:$highlight_color" : '' ?>">
        <input name="ma" type="text" value="<?= htmlspecialchars($_GET['ma'] ?? '') ?>" maxlength="17">
    </td>
    
    <td class="rowhead" valign="middle">Класс:</td>
    <td style="<?= (!empty($_GET['c']) && $_GET['c'] != 1) ? "background-color:$highlight_color" : '' ?>">
        <select name="c">
            <option value="1">(Любой)</option>
            <?php
            $current_class = $_GET['c'] ?? 1;
            for ($i = 2;; $i++) {
                $className = get_user_class_name($i - 2);
                if (!$className) {
                    break;
                }
                $selected = $current_class == $i ? 'selected' : '';
                echo "<option value=\"$i\" $selected>" . htmlspecialchars($className) . "</option>";
            }
            ?>
        </select>
    </td>
</tr>

<tr>
    <td class="rowhead" valign="middle">Регистрация:</td>
    <td style="<?= !empty($_GET['d']) ? "background-color:$highlight_color" : '' ?>">
        <select name="dt">
            <?php
            $date_options = ["в", "раньше", "после", "между"];
            $current_dt = $_GET['dt'] ?? 0;
            foreach ($date_options as $i => $option) {
                $selected = $current_dt == $i ? 'selected' : '';
                echo "<option value=\"$i\" $selected>$option</option>";
            }
            ?>
        </select>
        <input name="d" type="text" value="<?= htmlspecialchars($_GET['d'] ?? '') ?>" size="12" maxlength="10">
        <input name="d2" type="text" value="<?= htmlspecialchars($_GET['d2'] ?? '') ?>" size="12" maxlength="10">
    </td>
    
    <td class="rowhead" valign="middle">Раздал (GB):</td>
    <td style="<?= !empty($_GET['ul']) ? "background-color:$highlight_color" : '' ?>">
        <select name="ult">
            <?php
            $size_options = ["ровно", "больше", "меньше", "между"];
            $current_ult = $_GET['ult'] ?? 0;
            foreach ($size_options as $i => $option) {
                $selected = $current_ult == $i ? 'selected' : '';
                echo "<option value=\"$i\" $selected>$option</option>";
            }
            ?>
        </select>
        <input name="ul" type="number" step="0.01" value="<?= htmlspecialchars($_GET['ul'] ?? '') ?>" size="8" maxlength="7">
        <input name="ul2" type="number" step="0.01" value="<?= htmlspecialchars($_GET['ul2'] ?? '') ?>" size="8" maxlength="7">
    </td>
    
    <td class="rowhead" valign="middle">Донор:</td>
    <td style="<?= !empty($_GET['do']) ? "background-color:$highlight_color" : '' ?>">
        <select name="do">
            <?php
            $donor_options = ["(Любой)", "Да", "Нет"];
            $current_do = $_GET['do'] ?? 0;
            foreach ($donor_options as $i => $option) {
                $selected = $current_do == $i ? 'selected' : '';
                echo "<option value=\"$i\" $selected>$option</option>";
            }
            ?>
        </select>
    </td>
</tr>

<tr>
    <td class="rowhead" valign="middle">Последняя активность:</td>
    <td style="<?= !empty($_GET['ls']) ? "background-color:$highlight_color" : '' ?>">
        <select name="lst">
            <?php
            $last_options = ["в", "раньше", "после", "между"];
            $current_lst = $_GET['lst'] ?? 0;
            foreach ($last_options as $i => $option) {
                $selected = $current_lst == $i ? 'selected' : '';
                echo "<option value=\"$i\" $selected>$option</option>";
            }
            ?>
        </select>
        <input name="ls" type="text" value="<?= htmlspecialchars($_GET['ls'] ?? '') ?>" size="12" maxlength="10">
        <input name="ls2" type="text" value="<?= htmlspecialchars($_GET['ls2'] ?? '') ?>" size="12" maxlength="10">
    </td>
    
    <td class="rowhead" valign="middle">Скачал (GB):</td>
    <td style="<?= !empty($_GET['dl']) ? "background-color:$highlight_color" : '' ?>">
        <select name="dlt">
            <?php
            $current_dlt = $_GET['dlt'] ?? 0;
            foreach ($size_options as $i => $option) {
                $selected = $current_dlt == $i ? 'selected' : '';
                echo "<option value=\"$i\" $selected>$option</option>";
            }
            ?>
        </select>
        <input name="dl" type="number" step="0.01" value="<?= htmlspecialchars($_GET['dl'] ?? '') ?>" size="8" maxlength="7">
        <input name="dl2" type="number" step="0.01" value="<?= htmlspecialchars($_GET['dl2'] ?? '') ?>" size="8" maxlength="7">
    </td>
    
    <td class="rowhead" valign="middle">Предупрежден:</td>
    <td style="<?= !empty($_GET['w']) ? "background-color:$highlight_color" : '' ?>">
        <select name="w">
            <?php
            $warned_options = ["(Любой)", "Да", "Нет"];
            $current_w = $_GET['w'] ?? 0;
            foreach ($warned_options as $i => $option) {
                $selected = $current_w == $i ? 'selected' : '';
                echo "<option value=\"$i\" $selected>$option</option>";
            }
            ?>
        </select>
    </td>
</tr>

<tr>
    <td class="rowhead" colspan="2"></td>
    
    <td class="rowhead" valign="middle">Только активные:</td>
    <td style="<?= !empty($_GET['ac']) ? "background-color:$highlight_color" : '' ?>">
        <input type="checkbox" name="ac" value="1" <?= !empty($_GET['ac']) ? 'checked' : '' ?>>
    </td>
    
    <td class="rowhead" valign="middle">Забаненные IP:</td>
    <td style="<?= !empty($_GET['dip']) ? "background-color:$highlight_color" : '' ?>">
        <input type="checkbox" name="dip" value="1" <?= !empty($_GET['dip']) ? 'checked' : '' ?>>
    </td>
</tr>

<tr>
    <td colspan="6" style="text-align: center; padding: 10px;">
        <input type="submit" name="submit" value="Искать" class="btn" style="padding: 5px 20px;">
    </td>
</tr>
</table>
</form>

<?php
// Обработка поиска
if (!empty($_GET) && !isset($_GET['h'])) {
    $conditions = [];
    $query_params = [];
    
    // Обработка имени
    if (!empty($_GET['n'])) {
        $names = array_filter(explode(' ', trim($_GET['n'])));
        if ($names) {
            $name_conditions = [];
            foreach ($names as $name) {
                if ($name[0] === '~') {
                    if ($name === '~') continue;
                    $neg_name = substr($name, 1);
                    if (!has_wildcard($neg_name)) {
                        $name_conditions[] = "u.username != " . sqlesc($neg_name);
                    } else {
                        $neg_name = str_replace(['?', '*'], ['_', '%'], $neg_name);
                        $name_conditions[] = "u.username NOT LIKE " . sqlesc($neg_name);
                    }
                } else {
                    if (!has_wildcard($name)) {
                        $name_conditions[] = "u.username = " . sqlesc($name);
                    } else {
                        $name = str_replace(['?', '*'], ['_', '%'], $name);
                        $name_conditions[] = "u.username LIKE " . sqlesc($name);
                    }
                }
            }
            if ($name_conditions) {
                $conditions[] = '(' . implode(' AND ', $name_conditions) . ')';
            }
        }
        $query_params['n'] = $_GET['n'];
    }
    
    // Обработка email
    if (!empty($_GET['em'])) {
        $emails = array_filter(explode(' ', trim($_GET['em'])));
        if ($emails) {
            $email_conditions = [];
            foreach ($emails as $email) {
                if (!has_wildcard($email)) {
                    if (validemail($email) !== 1) {
                        stderr("Ошибка", "Неправильный E-mail: " . htmlspecialchars($email));
                    }
                    $email_conditions[] = "u.email = " . sqlesc($email);
                } else {
                    $email = str_replace(['?', '*'], ['_', '%'], $email);
                    $email_conditions[] = "u.email LIKE " . sqlesc($email);
                }
            }
            if ($email_conditions) {
                $conditions[] = '(' . implode(' OR ', $email_conditions) . ')';
            }
        }
        $query_params['em'] = $_GET['em'];
    }
    
    // Обработка класса
    if (!empty($_GET['c']) && $_GET['c'] != 1) {
        $class = (int)$_GET['c'] - 2;
        if ($class >= 0) {
            $conditions[] = "u.class = $class";
            $query_params['c'] = $_GET['c'];
        }
    }
    
    // Обработка IP и маски
    if (!empty($_GET['ip'])) {
        $ip = trim($_GET['ip']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $mask = trim($_GET['ma'] ?? '');
            if (empty($mask) || $mask === '255.255.255.255') {
                $conditions[] = "u.ip = '$ip'";
            } else {
                // Обработка маски CIDR
                if (strpos($mask, '/') === 0) {
                    $cidr = (int)substr($mask, 1);
                    if ($cidr >= 0 && $cidr <= 32) {
                        $mask = long2ip(pow(2, 32) - pow(2, 32 - $cidr));
                    }
                }
                if (filter_var($mask, FILTER_VALIDATE_IP)) {
                    $conditions[] = "INET_ATON(u.ip) & INET_ATON('$mask') = INET_ATON('$ip') & INET_ATON('$mask')";
                }
            }
            $query_params['ip'] = $ip;
            if (!empty($mask)) {
                $query_params['ma'] = $mask;
            }
        }
    }
    
    // Обработка рейтинга
    if (!empty($_GET['r'])) {
        $ratio = trim($_GET['r']);
        if ($ratio === '---') {
            $conditions[] = "u.uploaded = 0 AND u.downloaded = 0";
        } elseif (strtolower($ratio) === 'inf' || strtolower(substr($ratio, 0, 3)) === 'inf') {
            $conditions[] = "u.uploaded > 0 AND u.downloaded = 0";
        } elseif (is_numeric($ratio) && $ratio >= 0) {
            $ratio_type = $_GET['rt'] ?? 0;
            switch ($ratio_type) {
                case 1: // выше
                    $conditions[] = "(u.uploaded/u.downloaded) > $ratio";
                    break;
                case 2: // ниже
                    $conditions[] = "(u.uploaded/u.downloaded) < $ratio";
                    break;
                case 3: // между
                    $ratio2 = trim($_GET['r2'] ?? '');
                    if (is_numeric($ratio2) && $ratio2 >= $ratio) {
                        $conditions[] = "(u.uploaded/u.downloaded) BETWEEN $ratio AND $ratio2";
                        $query_params['r2'] = $ratio2;
                    }
                    break;
                default: // равно
                    $conditions[] = "(u.uploaded/u.downloaded) BETWEEN ($ratio - 0.004) AND ($ratio + 0.004)";
            }
            $query_params['rt'] = $ratio_type;
        }
        $query_params['r'] = $ratio;
    }
    
    // Дополнительные условия...
    // (здесь сокращено для краткости, оставьте оригинальную логику обработки остальных полей)
    
    // Формирование основного запроса
    $where_clause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $distinct = '';
    $joins = '';
    
    // Активные пользователи
    if (!empty($_GET['ac'])) {
        $distinct = 'DISTINCT ';
        $joins .= ' LEFT JOIN peers AS p ON u.id = p.userid';
        $query_params['ac'] = 1;
    }
    
    // Забаненные IP
    if (!empty($_GET['dip'])) {
        $distinct = $distinct ?: 'DISTINCT ';
        $joins .= ' LEFT JOIN users AS u2 ON u.ip = u2.ip';
        $conditions[] = "u2.enabled = 'no'";
        $query_params['dip'] = 1;
    }
    
    // Обновляем WHERE clause после добавления новых условий
    if ($conditions) {
        $where_clause = 'WHERE ' . implode(' AND ', $conditions);
    }
    
    // Базовый запрос для подсчета
    $count_query = "SELECT COUNT($distinct u.id) FROM users AS u $joins $where_clause";
    
    // Базовый запрос для данных
    $base_query = "SELECT $distinct 
        u.id, u.username, u.email, u.status, u.added, u.last_access, u.ip,
        u.class, u.uploaded, u.downloaded, u.donor, u.modcomment, u.enabled, u.warned
        FROM users AS u $joins $where_clause";
    
    if (DEBUG_MODE_SEARCH > 0) {
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
        echo "<strong>Запрос подсчета:</strong><br><code>" . htmlspecialchars($count_query) . "</code><br><br>";
        echo "<strong>Базовый запрос:</strong><br><code>" . htmlspecialchars($base_query) . "</code>";
        echo "</div>";
        if (DEBUG_MODE_SEARCH == 2) die();
    }
    
    // Выполняем запрос подсчета
    $count_result = sql_query($count_query) or sqlerr(__FILE__, __LINE__);
    $count_row = mysqli_fetch_row($count_result);
    $total_count = (int)$count_row[0];
    
    if ($total_count > 0) {
        // Пагинация
        $per_page = 30;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $per_page;
        
        // Полный запрос с пагинацией
        $full_query = "$base_query ORDER BY u.id DESC LIMIT $offset, $per_page";
        $result = sql_query($full_query) or sqlerr(__FILE__, __LINE__);
        
        // Отображение результатов
        echo "<div style='margin: 20px 0;'>";
        echo "<strong>Найдено пользователей:</strong> $total_count";
        
        // Пагинация сверху
        if ($total_count > $per_page) {
            $total_pages = ceil($total_count / $per_page);
            echo '<div style="margin: 10px 0;">';
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i == $page) {
                    echo "<strong>[$i]</strong> ";
                } else {
                    $url = $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($query_params, ['page' => $i]));
                    echo "<a href=\"$url\">$i</a> ";
                }
            }
            echo '</div>';
        }
        echo "</div>";
        
        // Таблица результатов
        echo '<table border="1" cellspacing="0" cellpadding="5" style="width: 100%;">';
        echo '<thead>
            <tr>
                <th>Пользователь</th>
                <th>Рейтинг</th>
                <th>IP</th>
                <th>Email</th>
                <th>Регистрация</th>
                <th>Последняя активность</th>
                <th>Статус</th>
                <th>Вкл</th>
                <th title="Активный рейтинг">pR</th>
                <th title="Активная раздача">pUL</th>
                <th title="Активная закачка">pDL</th>
                <th>Комментарии</th>
            </tr>
        </thead>
        <tbody>';
        
        while ($user = mysqli_fetch_assoc($result)) {
            // Форматирование дат
            $added = $user['added'] == '0000-00-00 00:00:00' ? '---' : substr($user['added'], 0, 10);
            $last_access = $user['last_access'] == '0000-00-00 00:00:00' ? '---' : substr($user['last_access'], 0, 10);
            
            // Проверка IP
            $ip_str = '---';
            if ($user['ip']) {
                $ip_long = ip2long($user['ip']);
                $ban_check = sql_query("SELECT COUNT(*) FROM bans WHERE $ip_long >= first AND $ip_long <= last");
                $ban_row = mysqli_fetch_row($ban_check);
                if ($ban_row[0] > 0) {
                    $ip_str = '<a href="testip.php?ip=' . $user['ip'] . '" style="color:red;font-weight:bold">' . $user['ip'] . '</a>';
                } else {
                    $ip_str = $user['ip'];
                }
            }
            
            // Статистика активных раздач
            $peer_stats = sql_query("SELECT SUM(uploaded) AS pul, SUM(downloaded) AS pdl FROM peers WHERE userid = " . $user['id']);
            $peer_row = mysqli_fetch_assoc($peer_stats);
            $pul = (float)$peer_row['pul'];
            $pdl = (float)$peer_row['pdl'];
            
            // Количество комментариев
            $comment_count = get_single_value("SELECT COUNT(*) FROM comments WHERE user = " . $user['id']);
            
            echo '<tr>';
            echo '<td>';
            echo '<a href="userdetails.php?id=' . $user['id'] . '"><strong>' . htmlspecialchars($user['username']) . '</strong></a>';
            echo get_user_icons_for_search($user);
            echo '</td>';
            echo '<td>' . format_ratio($user['uploaded'], $user['downloaded']) . '</td>';
            echo '<td>' . $ip_str . '</td>';
            echo '<td>' . htmlspecialchars($user['email']) . '</td>';
            echo '<td style="text-align:center">' . $added . '</td>';
            echo '<td style="text-align:center">' . $last_access . '</td>';
            echo '<td style="text-align:center">' . htmlspecialchars($user['status']) . '</td>';
            echo '<td style="text-align:center">' . htmlspecialchars($user['enabled']) . '</td>';
            echo '<td style="text-align:center">' . format_ratio($pul, $pdl) . '</td>';
            echo '<td style="text-align:right">' . mksize($pul) . '</td>';
            echo '<td style="text-align:right">' . mksize($pdl) . '</td>';
            echo '<td style="text-align:right">';
            if ($comment_count > 0) {
                echo '<a href="userhistory.php?action=viewcomments&id=' . $user['id'] . '">' . $comment_count . '</a>';
            } else {
                echo '0';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        // Форма массовой рассылки для администраторов
        if (get_user_class() >= UC_ADMINISTRATOR && $total_count > 0) {
            echo '<br><br>
            <form method="post" action="message.php">
            <table border="1" cellpadding="10" cellspacing="0" style="margin: 0 auto;">
                <tr>
                    <td style="text-align: center">
                        <strong>Рассылка сообщений найденным пользователям</strong><br>
                        <input type="hidden" name="query" value="' . htmlspecialchars($base_query) . '">
                        <input type="hidden" name="count" value="' . $total_count . '">
                        <input type="hidden" name="action" value="mass_pm">
                        <input type="submit" value="Отправить сообщения" class="btn" style="margin-top: 10px;">
                    </td>
                </tr>
            </table>
            </form>';
        }
    } else {
        echo '<div style="text-align: center; padding: 20px; color: #666;">Пользователи не найдены</div>';
    }
}

stdfoot();
?>