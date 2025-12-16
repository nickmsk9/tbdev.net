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
dbconn();
loggedinorreturn();

stdhead("Форматы");

?>

<div id="format-container" style="max-width: 600px; margin: 50px auto; text-align: center;">
    <h2>Выберите формат для просмотра</h2>
    
    <div style="margin: 30px 0;">
        <select id="format-select" style="padding: 10px; font-size: 16px; min-width: 200px;">
            <option value="">Выберите формат...</option>
            <option value="1">Форматы видео</option>
            <option value="2">Форматы файлов</option>
        </select>
    </div>
    
    <button onclick="loadFormat()" style="padding: 12px 24px; font-size: 16px; cursor: pointer;">
        Посмотреть
    </button>
    
    <div id="format-content" style="margin-top: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; min-height: 200px; display: none;">
        <!-- Сюда будет загружаться контент -->
    </div>
    
    <div id="loading" style="display: none; margin-top: 20px;">
        <p>Загрузка...</p>
    </div>
</div>

<script>
function loadFormat() {
    var select = document.getElementById('format-select');
    var format = select.value;
    
    if (!format) {
        alert('Пожалуйста, выберите формат');
        return;
    }
    
    var contentDiv = document.getElementById('format-content');
    var loadingDiv = document.getElementById('loading');
    
    // Показываем индикатор загрузки
    loadingDiv.style.display = 'block';
    contentDiv.style.display = 'none';
    contentDiv.innerHTML = '';
    
    // Создаем AJAX запрос
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'formatss.php?form=' + (format == '1' ? 'mov' : 'all'), true);
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            loadingDiv.style.display = 'none';
            
            if (xhr.status === 200) {
                contentDiv.innerHTML = xhr.responseText;
                contentDiv.style.display = 'block';
            } else {
                contentDiv.innerHTML = '<p style="color: red;">Ошибка при загрузке данных</p>';
                contentDiv.style.display = 'block';
            }
        }
    };
    
    xhr.send();
}

// Автоматическая загрузка при изменении выбора (опционально)
document.getElementById('format-select').addEventListener('change', function() {
    if (this.value) {
        loadFormat();
    }
});
</script>

<?php

stdfoot();

?>