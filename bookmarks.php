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

require_once("include/bittorrent.php");

dbconn(false);
loggedinorreturn();

// Проверяем, это AJAX-запрос?
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    // Только для AJAX - выводим только таблицу
    ajax_content();
    exit;
}

// Обычный запрос - выводим полную страницу
stdhead('Мои закладки');
?>

<div id="bookmarks-container">
    <h2>Мои закладки</h2>
    <div id="bookmarks-loading" style="text-align: center; padding: 20px;">
        <img src="pic/loading.gif" alt="Загрузка..." /><br />
        Загрузка закладок...
    </div>
    <div id="bookmarks-content"></div>
</div>

<script type="text/javascript">
$(document).ready(function() {
    loadBookmarks(1);
});

function loadBookmarks(page) {
    $('#bookmarks-loading').show();
    $('#bookmarks-content').hide();
    
    $.get('bookmarks.php?ajax=1&page=' + page, function(data) {
        $('#bookmarks-content').html(data).show();
        $('#bookmarks-loading').hide();
        
        // Вешаем обработчики на новые элементы
        $('#bookmarks-content .pager-link').click(function(e) {
            e.preventDefault();
            var page = $(this).data('page');
            loadBookmarks(page);
        });
        
        $('#bookmarks-content .remove-bookmark').click(function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var name = $(this).data('name');
            if (confirm('Удалить "' + name + '" из закладок?')) {
                removeBookmark(id);
            }
        });
    });
}

function removeBookmark(id) {
    $.post('remove_bookmark.php', {
        id: id,
        token: '<?php echo md5($CURUSER["id"] . $CURUSER["secret"]); ?>'
    }, function(response) {
        if (response.success) {
            alert(response.message);
            // Перезагружаем текущую страницу
            var currentPage = $('#bookmarks-content .pager-link.current').data('page') || 1;
            loadBookmarks(currentPage);
        } else {
            alert('Ошибка: ' + response.message);
        }
    }, 'json');
}
</script>

<?php
stdfoot();

// Функция для AJAX-контента
function ajax_content() {
    global $CURUSER, $minvotes;
    
    $page = (int)($_GET['page'] ?? 1);
    $perpage = 25;
    
    // Получаем количество закладок
    $res = sql_query("SELECT COUNT(id) FROM bookmarks WHERE userid = " . sqlesc($CURUSER["id"]));
    $row = mysqli_fetch_array($res);
    $count = $row[0];
    
    if ($count == 0) {
        echo '<div style="text-align: center; padding: 20px;">У вас нет закладок</div>';
        return;
    }
    
    // Пагинация
    list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, "bookmarks.php?page=");
    
    // Запрос закладок
    $res = sql_query("
        SELECT bookmarks.id AS bookmarkid, torrents.*, 
               categories.name AS cat_name, categories.image AS cat_pic,
               users.username
        FROM bookmarks 
        INNER JOIN torrents ON bookmarks.torrentid = torrents.id 
        LEFT JOIN categories ON torrents.category = categories.id 
        LEFT JOIN users ON torrents.owner = users.id 
        WHERE bookmarks.userid = " . sqlesc($CURUSER["id"]) . " 
        ORDER BY torrents.id DESC 
        $limit
    ");
    
    // Выводим таблицу
    echo '<table class="embedded" width="100%" cellspacing="0" cellpadding="5">';
    echo '<tr><td class="colhead" colspan="10">Мои закладки</td></tr>';
    
    if ($count > $perpage) {
        echo '<tr><td class="index" colspan="10">' . 
             str_replace('href="bookmarks.php?page=', 'class="pager-link" data-page="', $pagertop) . 
             '</td></tr>';
    }
    
    // Заголовок таблицы
    echo '<tr>
        <td class="colhead">Торрент</td>
        <td class="colhead">Категория</td>
        <td class="colhead">Размер</td>
        <td class="colhead">Сиды</td>
        <td class="colhead">Личи</td>
        <td class="colhead">Загрузок</td>
        <td class="colhead">Добавлен</td>
        <td class="colhead">Автор</td>
        <td class="colhead">Рейтинг</td>
        <td class="colhead">Действие</td>
    </tr>';
    
    while ($row = mysqli_fetch_assoc($res)) {
        echo '<tr>';
        echo '<td><a href="details.php?id=' . $row['id'] . '">' . htmlspecialchars($row['name']) . '</a></td>';
        echo '<td>' . htmlspecialchars($row['cat_name']) . '</td>';
        echo '<td>' . mksize($row['size']) . '</td>';
        echo '<td>' . ($row['seeders'] + $row['remote_seeders']) . '</td>';
        echo '<td>' . ($row['leechers'] + $row['remote_leechers']) . '</td>';
        echo '<td>' . $row['times_completed'] . '</td>';
        echo '<td>' . get_elapsed_time(sql_timestamp_to_unix_timestamp($row['added'])) . '</td>';
        echo '<td><a href="userdetails.php?id=' . $row['owner'] . '">' . htmlspecialchars($row['username']) . '</a></td>';
        echo '<td>' . ($row['rating'] ? sprintf("%.1f", $row['rating']) : '—') . '</td>';
        echo '<td><a href="#" class="remove-bookmark" data-id="' . $row['bookmarkid'] . '" data-name="' . htmlspecialchars($row['name']) . '">Удалить</a></td>';
        echo '</tr>';
    }
    
    if ($count > $perpage) {
        echo '<tr><td class="index" colspan="10">' . 
             str_replace('href="bookmarks.php?page=', 'class="pager-link" data-page="', $pagerbottom) . 
             '</td></tr>';
    }
    
    echo '</table>';
}
?>