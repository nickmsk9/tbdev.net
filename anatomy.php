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


require "include/bittorrent.php";

dbconn(false);
stdhead("Работа BitTorrent-трекера");
?>
<table class=main width=750 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
<table width=100% border=1 cellspacing=0 cellpadding=5>
<tr><td class=colhead>Как работает обновление статистики</td></tr>
<tr><td class=text>

<em>(Обновлено в связи с изменениями в работе трекера. 14-04-2004)</em>

<br /><br />
Существует много недопонимания относительно того, как работает обновление статистики. Ниже представлен полный
сеанс работы, чтобы показать, что происходит «за кулисами». Клиент начинает с отправки HTTP GET-запроса. Пример запроса:<br />
<br />
<code>GET /announce.php?<b>passkey</b>=a092924c51e9cac0d76b51457de93c9e&<b>info_hash</b>=c%97%91%C5jG%951%BE%C7M%F9%BFa%03%F2%2C%ED%EE%0F& <b>peer_id</b>=S588-----gqQ8TqDeqaY&<b>port</b>=6882&<b>uploaded</b>=0&<b>downloaded</b>=0&<b>left</b>=753690875&<b>event</b>=started</code><br />
<br />
Разбор параметров:<br />
<br />
• <b>passkey</b> — уникальный ключ пользователя, который идентифицирует его на трекере<br />
• <b>info_hash</b> — хэш раздачи (торрента)<br />
• <b>peer_id</b> — идентификатор клиента (префикс s588 означает Shad0w's 5.8.8, далее следует случайная строка)<br />
• <b>port</b> — порт, который клиент использует для входящих соединений<br />
• <b>uploaded</b>=0 — объём данных, отданных на данный момент<br />
• <b>downloaded</b>=0 — объём данных, скачанных на данный момент<br />
• <b>left</b>=753690875 — оставшийся объём для скачивания<br />
• <b>event=started</b> — событие, означающее, что клиент только начал скачивание<br />
<br />
Обратите внимание, что IP-адрес не передаётся в запросе (он будет определён из заголовков или переменных сервера).
Задача трекера — определить его и связать с user_id.<br />
(На некоторых конфигурациях возможно, что IP-адрес будет передан в отдельном поле.)<br />
На этом этапе в профиле пользователя данный торрент будет отображаться как скачиваемый.<br />
<br />
&raquo; Через некоторое время клиент отправляет следующий GET-запрос на трекер. Его параметры могут выглядеть так:
<br />
<br />
<code> GET /announce.php?<b>passkey</b>=a092924c51e9cac0d76b51457de93c9e&<b>info_hash</b>=c%97%91%C5jG%951%BE%C7M%F9%BFa%03%F2%2C%ED%EE%0F& <b>peer_id</b>=S588-----gqQ8TqDeqaY&<b>port</b>=6882&<b>uploaded</b>=67960832&<b>downloaded</b>=40828928& <b>left</b>=715417851&<b>numwant</b>=0</code><br />
<br />
("numwant" указывает, сколько новых пиров хочет получить клиент; в данном случае — 0.)
<br />
<br />
Как видно, на этом этапе пользователь отдал примерно 68 МБ и скачал примерно 40 МБ. При получении
таких GET-запросов трекер обновляет как статистику в разделе «Сейчас качаю/раздаю», так и общую статистику отданного/скачанного. Такие промежуточные запросы отправляются либо периодически (примерно каждые 15 минут,
зависит от клиента и трекера), либо при принудительном анонсе из клиента.
<br />
<br />
Наконец, когда раздача полностью завершена, клиент отправляет трекеру следующий запрос:
<br />
<br />
<code> GET /announce.php?<b>passkey</b>=a092924c51e9cac0d76b51457de93c9e&<b>info_hash</b>=c%97%91%C5jG%951%BE%C7M%F9%BFa%03%F2%2C%ED%EE%0F& <b>peer_id</b>=S588-----gqQ8TqDeqaY&<b>port</b>=6882&<b>uploaded</b>=754384896&<b>downloaded</b>=754215163 &<b>left</b>=0&numwant</b>=0&<b>event</b>=completed</code><br />
<br />
Обратите внимание на ключевой параметр "event=completed". На этом этапе торрент будет удалён из списка загрузок в профиле пользователя.
Если по какой-то причине (трекер недоступен, разрыв соединения, сбой клиента и т.д.) этот последний запрос не дойдёт
до трекера, торрент останется в профиле до тех пор, пока не сработает таймаут на стороне трекера. Важно подчеркнуть, что это сообщение отправляется только при
корректном завершении работы клиента, а не в момент окончания загрузки. (Трекер начнёт показывать
торрент как «сейчас раздаю» после получения запроса с left=0). <br />
<br />
Есть ещё одно сообщение, которое приводит к удалению торрента из профиля — 
"event=stopped". Оно обычно отправляется
при остановке загрузки в процессе, например, при нажатии «Cancel» в Shad0w's. <br />
<br />
Важное замечание: трекер обновляет статистику только на основе полученных запросов. Он <b>не</b> сохраняет промежуточные значения между анонсами.
На практике это означает, что если клиент отправит некорректные данные, статистика будет искажена. (Проверено на Shad0w's 5.8.11 и ABC 2.6.5.)
<br />
</td></tr></table>
</td></tr></table>
<br />
<?php
stdfoot();
?>