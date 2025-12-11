<?php

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

require_once "include/bittorrent.php";
dbconn(false);
loggedinorreturn();

$action = isset($_GET["action"]) ? htmlspecialchars($_GET["action"], ENT_QUOTES, 'UTF-8') : '';
$pollid = isset($_GET["pollid"]) ? (int)$_GET["pollid"] : 0;
$returnto = isset($_GET["returnto"]) ? htmlspecialchars($_GET["returnto"], ENT_QUOTES, 'UTF-8') : '';

// Удаление опроса
if ($action == "delete") {
    if (get_user_class() < UC_MODERATOR) {
        stderr("Ошибка", "Доступ запрещен.");
    }

    if (!is_valid_id($pollid)) {
        stderr("Ошибка", "Неверный ID опроса.");
    }

    $sure = isset($_GET["sure"]) ? (int)$_GET["sure"] : 0;
    if (!$sure) {
        stderr(
            "Удаление опроса",
            "Вы уверены что хотите удалить этот опрос?<br><br>" .
            "<a href='?action=delete&pollid=$pollid&returnto=$returnto&sure=1' class='btn btn-danger'>ДА, удалить</a> " .
            "<a href='polls.php' class='btn btn-secondary'>НЕТ, отмена</a>"
        );
    }

    sql_query("DELETE FROM pollanswers WHERE pollid = $pollid") or sqlerr(__FILE__, __LINE__);
    sql_query("DELETE FROM polls WHERE id = $pollid") or sqlerr(__FILE__, __LINE__);
    
    if ($returnto == "main") {
        header("Location: $DEFAULTBASEURL");
    } else {
        header("Location: $DEFAULTBASEURL/polls.php?deleted=1");
    }
    exit;
}

// Получение количества опросов
$rows = sql_query("SELECT COUNT(*) FROM polls") or sqlerr(__FILE__, __LINE__);
$row = mysqli_fetch_row($rows);
$pollcount = (int)$row[0];

if ($pollcount == 0) {
    stderr("Информация", "Нет доступных опросов!");
}

// Получаем все опросы кроме самого нового (последний показывается на главной)
$polls = sql_query("SELECT * FROM polls ORDER BY id DESC LIMIT 1, " . ($pollcount - 1)) or sqlerr(__FILE__, __LINE__);

stdhead("Архив опросов");

// CSS стили для улучшенного отображения
?>
<style>
    .poll-container {
        max-width: 800px;
        margin: 0 auto 20px auto;
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .poll-header {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        border-left: 4px solid #007bff;
    }
    .poll-question {
        font-size: 1.4em;
        font-weight: bold;
        color: #333;
        margin-bottom: 20px;
        text-align: center;
        padding: 10px;
        background-color: #f0f8ff;
        border-radius: 5px;
    }
    .poll-result-row {
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }
    .poll-result-row:nth-child(even) {
        background-color: #f9f9f9;
    }
    .poll-option {
        font-weight: 500;
        color: #555;
    }
    .poll-bar-container {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .poll-bar {
        flex-grow: 1;
        height: 20px;
        background-color: #e9ecef;
        border-radius: 10px;
        overflow: hidden;
        position: relative;
    }
    .poll-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #4CAF50, #45a049);
        border-radius: 10px;
        transition: width 0.5s ease-in-out;
    }
    .poll-percentage {
        font-weight: bold;
        color: #2c3e50;
        min-width: 60px;
        text-align: right;
    }
    .poll-stats {
        text-align: center;
        margin-top: 20px;
        padding: 10px;
        background-color: #e8f4fd;
        border-radius: 5px;
        font-size: 1.1em;
    }
    .poll-date {
        color: #6c757d;
        font-size: 0.9em;
    }
    .poll-actions {
        margin-top: 10px;
        text-align: right;
    }
    .btn {
        padding: 5px 15px;
        border-radius: 4px;
        text-decoration: none;
        font-size: 0.9em;
        margin-left: 10px;
        display: inline-block;
    }
    .btn-edit {
        background-color: #17a2b8;
        color: white;
        border: 1px solid #138496;
    }
    .btn-delete {
        background-color: #dc3545;
        color: white;
        border: 1px solid #c82333;
    }
    .btn-edit:hover {
        background-color: #138496;
    }
    .btn-delete:hover {
        background-color: #c82333;
    }
</style>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <h1 style="text-align: center; margin-bottom: 30px; color: #2c3e50;">Архив опросов</h1>
    
    <div style="text-align: center; margin-bottom: 30px;">
        <a href="<?php echo $DEFAULTBASEURL; ?>" class="btn" style="background-color: #6c757d; color: white;">На главную</a>
        <a href="makepoll.php" class="btn" style="background-color: #28a745; color: white;">Создать новый опрос</a>
    </div>

<?php

// Функция для сортировки результатов
function sortResults($a, $b): int {
    if ($a[0] > $b[0]) return -1;
    if ($a[0] < $b[0]) return 1;
    return 0;
}

// Показываем все опросы
$poll_counter = 0;
while ($poll = mysqli_fetch_assoc($polls)) {
    $poll_counter++;
    
    // Собираем все варианты ответов
    $options = [];
    for ($i = 0; $i < 20; $i++) {
        $option_key = "option{$i}";
        if (!empty($poll[$option_key])) {
            $options[$i] = $poll[$option_key];
        }
    }
    
    // Получаем голоса
    $pollanswers = sql_query("SELECT selection FROM pollanswers WHERE pollid = " . (int)$poll["id"] . " AND selection < 20") or sqlerr(__FILE__, __LINE__);
    
    $total_votes = mysqli_num_rows($pollanswers);
    $votes_count = array_fill(0, 20, 0);
    $vote_data = [];
    
    // Подсчитываем голоса
    while ($pollanswer = mysqli_fetch_row($pollanswers)) {
        $selection = (int)$pollanswer[0];
        if (isset($options[$selection])) {
            $votes_count[$selection]++;
        }
    }
    
    // Формируем данные для отображения
    foreach ($options as $index => $option_text) {
        if (!empty($option_text)) {
            $vote_data[] = [$votes_count[$index], $option_text, $index];
        }
    }
    
    // Сортируем если нужно
    if ($poll["sort"] == "yes") {
        usort($vote_data, 'sortResults');
    }
    
    // Форматируем дату
    $added_date = date("Y-m-d", strtotime($poll['added'])) . " GMT";
    $time_ago = get_elapsed_time(sql_timestamp_to_unix_timestamp($poll["added"])) . " назад";
    
    ?>
    
    <div class="poll-container" id="poll-<?php echo $poll['id']; ?>">
        <div class="poll-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div class="poll-date">
                    Опубликован: <?php echo $added_date; ?> (<?php echo $time_ago; ?>)
                </div>
                <?php if (get_user_class() >= UC_ADMINISTRATOR): ?>
                <div class="poll-actions">
                    <a href="makepoll.php?action=edit&amp;pollid=<?php echo $poll['id']; ?>" class="btn btn-edit">Редактировать</a>
                    <a href="?action=delete&amp;pollid=<?php echo $poll['id']; ?>" class="btn btn-delete" 
                       onclick="return confirm('Вы уверены что хотите удалить этот опрос?');">Удалить</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="poll-question">
            <?php echo htmlspecialchars($poll["question"], ENT_QUOTES, 'UTF-8'); ?>
        </div>
        
        <div style="padding: 10px;">
            <?php if (!empty($vote_data)): ?>
                <?php foreach ($vote_data as $index => $data): 
                    list($votes, $option_text, $option_index) = $data;
                    $percentage = ($total_votes > 0) ? round(($votes / $total_votes) * 100, 1) : 0;
                    $bar_width = min($percentage * 3, 100); // Ограничиваем ширину для визуализации
                ?>
                <div class="poll-result-row">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div class="poll-option" style="flex: 0 0 50%;">
                            <?php echo htmlspecialchars($option_text, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="poll-bar-container" style="flex: 0 0 50%;">
                            <div class="poll-bar">
                                <div class="poll-bar-fill" style="width: <?php echo $percentage; ?>%;"></div>
                            </div>
                            <div class="poll-percentage">
                                <?php echo $percentage; ?>% (<?php echo $votes; ?>)
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 20px; color: #666;">
                    В этом опросе нет вариантов ответов.
                </div>
            <?php endif; ?>
        </div>
        
        <div class="poll-stats">
            Всего проголосовало: <strong><?php echo number_format($total_votes); ?></strong> пользователей
        </div>
    </div>
    
    <?php
}

if ($poll_counter == 0) {
    echo '<div style="text-align: center; padding: 40px; background-color: #f8f9fa; border-radius: 8px;">
            <h3 style="color: #6c757d;">В архиве пока нет опросов</h3>
            <p>Все текущие и недавние опросы отображаются на главной странице.</p>
          </div>';
}

// Пагинация (если нужно)
if ($pollcount > 10) {
    echo '<div style="text-align: center; margin-top: 30px;">
            <div style="display: inline-block; padding: 10px 20px; background-color: #f8f9fa; border-radius: 5px;">
              Показано ' . $poll_counter . ' из ' . $pollcount . ' опросов
            </div>
          </div>';
}

echo '</div>'; // Закрываем основной контейнер

stdfoot();
exit;