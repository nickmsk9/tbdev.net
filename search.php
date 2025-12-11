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

require_once "include/bittorrent.php";
dbconn();
loggedinorreturn();

stdhead("Поиск раздач");

// Получение значения поиска из GET-параметра
$searchstr = isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8') : '';
$selected_cat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$incldead = isset($_GET['incldead']) && $_GET['incldead'] == 1;
?>

<div style="max-width: 750px; margin: 0 auto; padding: 20px;">
    <h2 style="text-align: center; margin-bottom: 20px;">Поиск раздач</h2>
    
    <form method="get" action="browse.php" style="background-color: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
        <div style="display: flex; flex-direction: column; gap: 15px;">
            <!-- Поле поиска -->
            <div>
                <label for="search" style="display: block; margin-bottom: 5px; font-weight: bold;">Ключевые слова:</label>
                <input type="text" 
                       id="search" 
                       name="search" 
                       size="40" 
                       value="<?php echo $searchstr; ?>" 
                       placeholder="Введите название, описание или другие ключевые слова"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
            
            <!-- Выбор категории -->
            <div>
                <label for="cat" style="display: block; margin-bottom: 5px; font-weight: bold;">Категория:</label>
                <select name="cat" id="cat" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    <option value="0">(Все категории)</option>
                    <?php
                    $cats = genrelist();
                    foreach ($cats as $cat) {
                        $selected = ($cat["id"] == $selected_cat) ? ' selected="selected"' : '';
                        echo '<option value="' . (int)$cat["id"] . '"' . $selected . '>' 
                             . htmlspecialchars($cat["name"], ENT_QUOTES, 'UTF-8') . '</option>' . "\n";
                    }
                    ?>
                </select>
            </div>
            
            <!-- Чекбокс для включения мертвых раздач -->
            <div>
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" 
                           name="incldead" 
                           value="1" 
                           <?php echo $incldead ? 'checked="checked"' : ''; ?>
                           style="margin-right: 8px;">
                    <span>Включая мертвые раздачи</span>
                </label>
                <small style="display: block; margin-top: 5px; color: #666;">
                    Мертвые раздачи - это торренты без активных раздающих (сидов)
                </small>
            </div>
            
            <!-- Кнопка отправки -->
            <div style="text-align: center; margin-top: 10px;">
                <input type="submit" 
                       value="Найти!" 
                       style="padding: 12px 30px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold;">
                <input type="reset" 
                       value="Очистить" 
                       style="margin-left: 10px; padding: 12px 20px; background-color: #f0f0f0; color: #333; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; font-size: 14px;">
            </div>
        </div>
    </form>
    
    <!-- Информация о поиске -->
    <div style="margin-top: 20px; padding: 15px; background-color: #e7f3fe; border: 1px solid #b3d9ff; border-radius: 5px;">
        <h4 style="margin-top: 0; color: #0066cc;">Советы по поиску:</h4>
        <ul style="margin-bottom: 0; padding-left: 20px;">
            <li>Используйте ключевые слова на русском или английском языке</li>
            <li>Для более точного поиска выберите конкретную категорию</li>
            <li>По умолчанию в результатах не показываются мертвые раздачи</li>
            <li>Вы можете искать по названию, описанию или имени загрузчика</li>
        </ul>
    </div>
</div>

<?php
stdfoot();
exit;