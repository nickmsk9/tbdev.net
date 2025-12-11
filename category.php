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



ob_start();

require_once 'include/bittorrent.php';
dbconn(false);
loggedinorreturn();

if (get_user_class() < UC_SYSOP) {
    die($tracker_lang['access_denied']);
}

stdhead('Управление категориями');

print('<h1>Управление категориями</h1>');
print('<br>');
print('<table width="70%" border="1" cellspacing="0" cellpadding="2"><tr><td align="center">');

///////////////////// УДАЛЕНИЕ КАТЕГОРИИ //////////////////////////

if (isset($_GET['sure']) && $_GET['sure'] === 'yes') {
    $delid = (int) ($_GET['delid'] ?? 0);
    
    if ($delid > 0) {
        $query = "DELETE FROM categories WHERE id = " . sqlesc($delid) . " LIMIT 1";
        $sql = sql_query($query);
        print('Категория успешно удалена! [ <a href="category.php">Назад</a> ]');
        end_frame();
        stdfoot();
        exit;
    }
}

$delid = (int) ($_GET['delid'] ?? 0);
$name = htmlspecialchars_uni($_GET['cat'] ?? '');

if ($delid > 0 && !empty($name)) {
    print('Вы уверены, что хотите удалить эту категорию? (' . $name . ')');
    print(' ( <strong><a href="' . htmlspecialchars($_SERVER['PHP_SELF']) . '?delid=' . $delid . '&cat=' . urlencode($name) . '&sure=yes">Да</a></strong>');
    print(' / <strong><a href="' . htmlspecialchars($_SERVER['PHP_SELF']) . '">Нет</a></strong> )');
    end_frame();
    stdfoot();
    exit;
}

///////////////////// РЕДАКТИРОВАНИЕ КАТЕГОРИИ //////////////////////////

if (isset($_GET['edited']) && $_GET['edited'] == '1') {
    $id = (int) ($_GET['id'] ?? 0);
    $cat_name = htmlspecialchars_uni($_GET['cat_name'] ?? '');
    $cat_img = htmlspecialchars_uni($_GET['cat_img'] ?? '');
    $cat_sort = (int) ($_GET['cat_sort'] ?? 0);
    
    if ($id > 0 && !empty($cat_name)) {
        $query = "UPDATE categories SET 
                  name = " . sqlesc($cat_name) . ",
                  image = " . sqlesc($cat_img) . ",
                  sort = " . sqlesc($cat_sort) . " 
                  WHERE id = " . sqlesc($id);
        $sql = sql_query($query);
        
        if ($sql) {
            print('<table class="main" cellspacing="0" cellpadding="5" width="50%">');
            print('<tr><td><div align="center">Категория успешно отредактирована! [ <a href="category.php">Назад</a> ]</div></td></tr>');
            print('</table>');
            end_frame();
            stdfoot();
            exit;
        }
    }
}

$editid = (int) ($_GET['editid'] ?? 0);
$name = htmlspecialchars_uni($_GET['name'] ?? '');
$img = htmlspecialchars_uni($_GET['img'] ?? '');
$sort = (int) ($_GET['sort'] ?? 0);

if ($editid > 0) {
    print('<form name="form1" method="get" action="' . htmlspecialchars($_SERVER['PHP_SELF']) . '">');
    print('<table class="main" cellspacing="0" cellpadding="5" width="50%">');
    print('<tr><td colspan="2"><div align="center">Редактирование категории <strong>&quot;' . $name . '&quot;</strong></div></td></tr>');
    print('<tr><td colspan="2"><br></td></tr>');
    print('<input type="hidden" name="edited" value="1">');
    print('<input type="hidden" name="id" value="' . $editid . '">');
    print('<tr><td>Название: </td><td align="right"><input type="text" size="50" name="cat_name" value="' . $name . '"></td></tr>');
    print('<tr><td>Изображение: </td><td align="right"><input type="text" size="50" name="cat_img" value="' . $img . '"></td></tr>');
    print('<tr><td>Сортировка: </td><td align="right"><input type="text" size="50" name="cat_sort" value="' . $sort . '"></td></tr>');
    print('<tr><td></td><td><div align="right"><input type="submit" value="Редактировать"></div></td></tr>');
    print('</table></form>');
    end_frame();
    stdfoot();
    exit;
}

///////////////////// ДОБАВЛЕНИЕ НОВОЙ КАТЕГОРИИ //////////////////////////

$success = null;

if (isset($_GET['add']) && $_GET['add'] === 'true') {
    $cat_name = htmlspecialchars_uni($_GET['cat_name'] ?? '');
    $cat_img = htmlspecialchars_uni($_GET['cat_img'] ?? '');
    $cat_sort = (int) ($_GET['cat_sort'] ?? 0);
    
    if (!empty($cat_name)) {
        $query = "INSERT INTO categories SET 
                  name = " . sqlesc($cat_name) . ",
                  image = " . sqlesc($cat_img) . ",
                  sort = " . sqlesc($cat_sort);
        $sql = sql_query($query);
        $success = (bool) $sql;
    } else {
        $success = false;
    }
}

print('<strong>Добавить новую категорию</strong>');
print('<br>');
print('<br>');
print('<form name="form1" method="get" action="' . htmlspecialchars($_SERVER['PHP_SELF']) . '">');
print('<table class="main" cellspacing="0" cellpadding="5" width="50%">');
print('<tr><td>Название: </td><td align="right"><input type="text" size="50" name="cat_name" required></td></tr>');
print('<tr><td>Изображение: </td><td align="right"><input type="text" size="50" name="cat_img"></td></tr>');
print('<tr><td>Сортировка: </td><td align="right"><input type="number" size="10" name="cat_sort" value="0"></td></tr>');
print('<input type="hidden" name="add" value="true">');
print('<tr><td></td><td><div align="right"><input type="submit" value="Добавить категорию"></div></td></tr>');
print('</table>');

if ($success === true) {
    print('<br><strong>Категория успешно добавлена!</strong>');
} elseif ($success === false) {
    print('<br><strong>Ошибка при добавлении категории!</strong>');
}

print('<br>');
print('</form>');

///////////////////// СУЩЕСТВУЮЩИЕ КАТЕГОРИИ //////////////////////////

print('<strong>Существующие категории:</strong>');
print('<br>');
print('<br>');
print('<table class="main" cellspacing="0" cellpadding="5">');
print('<tr><td>ID</td><td>Сортировка</td><td>Название</td><td>Изображение</td><td>Просмотр</td><td>Редактировать</td><td>Удалить</td></tr>');

$query = "SELECT * FROM categories ORDER BY sort";
$sql = sql_query($query) or sqlerr(__FILE__, __LINE__);

while ($row = $sql->fetch_assoc()) {
    $id = (int) $row['id'];
    $sort = (int) $row['sort'];
    $name = htmlspecialchars($row['name']);
    $img = htmlspecialchars($row['image']);
    $img_url = $DEFAULTBASEURL . '/pic/cats/' . $img;
    
    print('<tr>');
    print('<td><strong>' . $id . '</strong></td>');
    print('<td><strong>' . $sort . '</strong></td>');
    print('<td><strong>' . $name . '</strong></td>');
    print('<td><img src="' . $img_url . '" border="0" alt="' . $name . '" /></td>');
    print('<td><div align="center"><a href="browse.php?cat=' . $id . '"><img src="' . $DEFAULTBASEURL . '/pic/viewnfo.gif" border="0" class="special" alt="Просмотр" /></a></div></td>');
    print('<td><div align="center"><a href="category.php?editid=' . $id . '&name=' . urlencode($row['name']) . '&img=' . urlencode($row['image']) . '&sort=' . $sort . '"><img src="' . $DEFAULTBASEURL . '/pic/multipage.gif" border="0" class="special" alt="Редактировать" /></a></div></td>');
    print('<td><div align="center"><a href="category.php?delid=' . $id . '&cat=' . urlencode($row['name']) . '"><img src="' . $DEFAULTBASEURL . '/pic/warned2.gif" border="0" class="special" alt="Удалить" /></a></div></td>');
    print('</tr>');
}

print('</table>');

end_frame();
stdfoot();

?>