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

# IMPORTANT: Do not edit below unless you know what you are doing!
if (!defined('IN_TRACKER'))
	die('Hacking attempt!');

function get_user_class_color($class, $username) {
	global $tracker_lang;
	switch ($class) {
		case UC_SYSOP:
			return "<span style=\"color:#0F6CEE\" title=\"" . $tracker_lang['class_sysop'] . "\">" . $username . "</span>";
			break;
		case UC_ADMINISTRATOR:
			return "<span style=\"color:green\" title=\"" . $tracker_lang['class_administrator'] . "\">" . $username . "</span>";
			break;
		case UC_MODERATOR:
			return "<span style=\"color:red\" title=\"" . $tracker_lang['class_moderator'] . "\">" . $username . "</span>";
			break;
		case UC_UPLOADER:
			return "<span style=\"color:orange\" title=\"" . $tracker_lang['class_uploader'] . "\">" . $username . "</span>";
			break;
		case UC_VIP:
			return "<span style=\"color:#9C2FE0\" title=\"" . $tracker_lang['class_vip'] . "\">" . $username . "</span>";
			break;
		case UC_POWER_USER:
			return "<span style=\"color:#D21E36\" title=\"" . $tracker_lang['class_power_user'] . "\">" . $username . "</span>";
			break;
		case UC_USER:
			return "<span title=\"" . $tracker_lang['class_user'] . "\">" . $username . "</span>";
			break;
	}
	return "$username";
}

function display_date_time($timestamp = 0, $tzoffset = 0) {
	return date("Y-m-d H:i:s", $timestamp + ($tzoffset * 60));
}

function cut_text($txt, $car) {
	while (strlen($txt) > $car) {
		return substr($txt, 0, $car) . "...";
	}
	return $txt;
}

function textbbcode($form, $name, $content = false, $id_area = 'area')
{
    global $tracker_lang;

    $script = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    $isEdit = ($script === 'edit.php' || $script === 'uploadnext.php');
    $isDetails = ($script === 'details.php');

    if (stripos($script, 'upload') !== false) {
        $rows = 18;
    } elseif (stripos($script, 'edit') !== false) {
        $rows = 38;
    } else {
        $rows = 11;
    }

    $content = ($content === false || $content === null) ? '' : (string)$content;

    if (!defined('TEXTBBCODE_ASSETS')) {
        define('TEXTBBCODE_ASSETS', 1);

        echo '<script type="text/javascript" src="js/ajax.js"></script>';
        echo '<script type="text/javascript" src="js/bbcode.js"></script>';

        echo '<style>
            .editbutton{cursor:pointer;padding:2px 1px 0 5px}
            div.grippie{background:#EEE url("/pic/grippie.png") no-repeat scroll center 2px;border:0;border-left:1px solid #DDD;border-right:1px solid #DDD;border-bottom:1px solid #DDD;cursor:s-resize;height:9px;overflow:hidden}
        </style>';

        $loading = $tracker_lang['loading'] ?? 'Загрузка...';
        echo '<div id="loading-layer" style="display:none;font-family:Verdana;font-size:11px;width:200px;height:50px;background:#FFF;padding:10px;text-align:center;border:1px solid #000">'
            . '<div style="font-weight:bold" id="loading-layer-text">' . $loading . '</div><br />'
            . '<img src="../pic/loading.gif" border="0" alt="loading" />'
            . '</div>';
    }

    // кнопки/подсказки
    $bcode = array_map('trim', explode(', ', (string)($tracker_lang['bb_bcode'] ?? 'HR, BR, Спойлер, B, I, U, S, BB, PRE, HT, MG, Quote, Img, QuoteSel, HIDE, Url, Url=, PHP, Flash, YT, MM, LI, LG1, LG2, HIG, UDesk, Smiles')));
    $brepl = array_map('trim', explode(', ', (string)($tracker_lang['bb_brepl'] ?? '')));

    $valign = array_map('trim', explode(', ', (string)($tracker_lang['bb_valign'] ?? 'Слева, Справа, По центру, По ширине')));

    $quoteSelDisabled = $isEdit ? '' : 'disabled="disabled"';
    $hideDisabled     = $isEdit ? '' : 'disabled="disabled"';

    // русские подписи селектов (если нет в языке — дефолт)
    $bbFonts = $tracker_lang['bb_fonts']     ?? 'Шрифт';
    $bbColor = $tracker_lang['bb_colorfont'] ?? 'Цвет';
    $bbSize  = $tracker_lang['bb_sizefont']  ?? 'Размер';
    $bbAlign = $tracker_lang['bb_align']     ?? 'Выравнивание';

    // дефолтные русские tooltip’ы (если bb_brepl пустой)
    $ttlDef = [
        0=>'Горизонтальная линия', 1=>'Перенос строки', 2=>'Спойлер',
        3=>'Жирный', 4=>'Курсив', 5=>'Подчёркнутый', 6=>'Зачёркнутый',
        7=>'BBCode', 8=>'Предформатированный текст', 11=>'Цитата', 12=>'Картинка',
        13=>'Цитировать выделенное', 14=>'Скрытый текст',
        15=>'Ссылка', 16=>'Ссылка с текстом',
        17=>'PHP-код', 18=>'Flash', 19=>'Видео', 20=>'MM', 21=>'Элемент списка'
    ];

    $btn = static function($idx, $fallback) use ($bcode) {
        return $bcode[$idx] ?? $fallback;
    };
    $ttl = static function($idx, $fallback) use ($brepl, $ttlDef) {
        if (!empty($brepl[$idx])) return $brepl[$idx];
        return $ttlDef[$idx] ?? $fallback;
    };

    echo '<table cellpadding="0" cellspacing="0" align="center">';
    echo '<tr><td class="b"><div>';

    echo '<div align="center">';

    $fonts = [
        'Verdana' => 'Verdana', 'Courier' => 'Courier', 'Courier New' => 'Courier New',
        'monospace' => 'monospace', 'Fixedsys' => 'Fixedsys', 'Arial' => 'Arial',
        'Comic Sans MS' => 'Comic Sans', 'Georgia' => 'Georgia', 'Tahoma' => 'Tahoma',
        'Times New Roman' => 'Times', 'serif' => 'serif', 'sans-serif' => 'sans-serif',
        'cursive' => 'cursive', 'fantasy' => 'fantasy', 'Book Antiqua' => 'Antiqua',
        'Century Gothic' => 'Century Gothic', 'Franklin Gothic Medium' => 'Franklin',
        'Garamond' => 'Garamond', 'Impact' => 'Impact', 'Lucida Console' => 'Lucida',
        'Palatino Linotype' => 'Palatino', 'Trebuchet MS' => 'Trebuchet',
    ];

    echo '<select name="fontFace" class="editbutton">';
    echo '<option style="font-family:Verdana;" value="" selected="selected">' . htmlspecialchars($bbFonts, ENT_QUOTES, 'UTF-8') . ':</option>';
    foreach ($fonts as $val => $label) {
        echo '<option style="font-family:' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . ';" value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '">&nbsp;' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select>&nbsp;';

    echo '<select name="codeColor" class="editbutton">';
    echo '<option style="color:black;background:#fff" value="" selected="selected">' . htmlspecialchars($bbColor, ENT_QUOTES, 'UTF-8') . ':</option>';
    $colors = [
        'Black'=>'Чёрный','Sienna'=>'Охра','Beige'=>'Бежевый','DarkOliveGreen'=>'Оливковый (тёмн.)',
        'DarkGreen'=>'Тёмно-зелёный','Cornflower'=>'Васильковый','Navy'=>'Тёмно-синий','DarkRed'=>'Тёмно-красный',
        'DarkOrange'=>'Тёмно-оранжевый','Olive'=>'Оливковый','Green'=>'Зелёный','Teal'=>'Морская волна',
        'Blue'=>'Синий','Gray'=>'Серый','Red'=>'Красный','Orange'=>'Оранжевый','Yellow'=>'Жёлтый',
        'Gold'=>'Золотой','Silver'=>'Серебристый','Pink'=>'Розовый','White'=>'Белый'
    ];
    foreach ($colors as $val => $label) {
        echo '<option style="color:' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . ';" value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '">&nbsp;' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select>&nbsp;';

    echo '<select name="codeSize" class="editbutton">';
    echo '<option value="" selected="selected">' . htmlspecialchars($bbSize, ENT_QUOTES, 'UTF-8') . ':</option>';
    foreach ([9,10,11,12,14,16,18,20,22,24] as $s) {
        echo '<option value="' . (int)$s . '">&nbsp;' . (int)$s . '</option>';
    }
    echo '</select>&nbsp;';

    echo '<select name="codeAlign" class="editbutton">';
    echo '<option value="" selected="selected">' . htmlspecialchars($bbAlign, ENT_QUOTES, 'UTF-8') . ':</option>';
    echo '<option style="text-align:left" value="left">&nbsp;' . htmlspecialchars($valign[0] ?? 'Слева', ENT_QUOTES, 'UTF-8') . '</option>';
    echo '<option style="text-align:right" value="right">&nbsp;' . htmlspecialchars($valign[1] ?? 'Справа', ENT_QUOTES, 'UTF-8') . '</option>';
    echo '<option style="text-align:center" value="center">&nbsp;' . htmlspecialchars($valign[2] ?? 'По центру', ENT_QUOTES, 'UTF-8') . '</option>';
    echo '<option style="text-align:justify" value="justify">&nbsp;' . htmlspecialchars($valign[3] ?? 'По ширине', ENT_QUOTES, 'UTF-8') . '</option>';
    echo '</select>';

    echo '</div>';

    echo '<div align="center">';

    echo '<input class="btn" type="button" value="' . $btn(0,'HR') . '" name="codeHR" title="' . $ttl(0,'HR') . ' (Ctrl+8)" style="font-weight:bold;width:26px" /> ';
    echo '<input class="btn" type="button" value="' . $btn(1,'BR') . '" name="codeBR" title="' . $ttl(1,'BR') . '" style="width:26px" /> ';
    echo '<input class="btn" type="button" value="' . $btn(2,'Спойлер') . '" name="codeSpoiler" title="' . $ttl(2,'Спойлер') . ' (Ctrl+S)" style="width:70px" /> ';

    echo '<input class="btn" type="button" value=" ' . $btn(3,'B') . ' " name="codeB" title="' . $ttl(3,'B') . ' (Ctrl+B)" style="font-weight:bold;width:30px" /> ';
    echo '<input class="btn" type="button" value=" ' . $btn(4,'I') . ' " name="codeI" title="' . $ttl(4,'I') . ' (Ctrl+I)" style="width:30px;font-style:italic" /> ';
    echo '<input class="btn" type="button" value=" ' . $btn(5,'U') . ' " name="codeU" title="' . $ttl(5,'U') . ' (Ctrl+U)" style="width:30px;text-decoration:underline" /> ';
    echo '<input class="btn" type="button" value=" ' . $btn(6,'S') . ' " name="codeS" title="' . $ttl(6,'S') . '" style="width:30px;text-decoration:line-through" /> ';

    echo '<input class="btn" type="button" value=" ' . $btn(7,'BB') . ' " name="codeBB" title="' . $ttl(7,'BB') . ' (Ctrl+N)" style="font-weight:bold;width:30px" /> ';
    echo '<input class="btn" type="button" value=" ' . $btn(8,'PRE') . ' " name="codePRE" title="' . $ttl(8,'PRE') . ' (Ctrl+P)" style="width:40px" /> ';
    echo '<input class="btn" type="button" value=" ' . $btn(11,'Quote') . ' " name="codeQuote" title="' . $ttl(11,'Quote') . ' (Ctrl+Q)" style="width:60px" /> ';
    echo '<input class="btn" type="button" value="' . $btn(12,'Img') . '" name="codeImg" title="' . $ttl(12,'Img') . ' (Ctrl+R)" style="width:40px" /> ';

    echo '<input class="btn" type="button" ' . $quoteSelDisabled . ' value="' . $btn(13,'QuoteSel') . '" name="quoteselected" title="' . $ttl(13,'QuoteSel') . '" style="width:165px" onmouseout="bbcode.refreshSelection(false);" onmouseover="bbcode.refreshSelection(true);" onclick="bbcode.onclickQuoteSel();" /> ';
    echo '<input class="btn" type="button" ' . $hideDisabled . ' value="' . $btn(14,'HIDE') . '" name="codeHIDE" title="' . $ttl(14,'HIDE') . '" style="width:70px" /> ';

    echo '<input class="btn" type="button" value="' . $btn(15,'Url') . '" name="codeUri" title="' . $ttl(15,'Url') . '" style="width:40px;text-decoration:underline" /> ';
    echo '<input class="btn" type="button" value="' . $btn(16,'Url=') . '" name="codeUr" title="' . $ttl(16,'Url=') . '" style="width:40px;text-decoration:underline" /> ';

    echo '<input class="btn" type="button" value="' . $btn(17,'PHP') . '" name="codeCode" title="' . $ttl(17,'PHP') . ' (Ctrl+K)" style="width:46px" /> ';
    echo '<input class="btn" type="button" value="' . $btn(18,'Flash') . '" name="codeFlash" title="' . $ttl(18,'Flash') . ' (Ctrl+F)" style="width:50px" /> ';
    echo '<input class="btn" type="button" value="' . $btn(19,'YT') . '" name="codeYT" title="' . $ttl(19,'YT') . '" style="width:50px" /> ';
    echo '<input class="btn" type="button" value="' . $btn(20,'MM') . '" name="codeMM" title="' . $ttl(20,'MM') . '" style="width:50px" /> ';
    echo '<input class="btn" type="button" value="' . $btn(21,'LI') . '" name="codeOpt" title="' . $ttl(21,'LI') . ' (Ctrl+0)" style="width:30px" /> ';

    if ($isEdit) {
        echo '<input class="btn" type="button" value="' . $btn(25,'UDesk') . '" onclick="textbb_udesck(\'' . htmlspecialchars($id_area, ENT_QUOTES, 'UTF-8') . '\')" title="' . $ttl(25,'UDesk') . '" style="width:60px" /> ';
    }

    $smilesTitle = $tracker_lang['smilies'] ?? 'Смайлы';
    echo '<input class="btn" type="button" value="' . $btn(26,$smilesTitle) . '" name="Smailes" title="' . $ttl(26,$smilesTitle) . '" style="width:60px" onclick="window.open(\'smilies.php?form=' . rawurlencode($form) . '&text=' . rawurlencode($name) . '\', \'height=500,width=450,resizable=no,scrollbars=yes\'); return false;" /> ';

    echo '</div>';

    echo '<textarea class="resizable" id="' . htmlspecialchars($id_area, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" style="width:100%;" rows="' . (int)$rows . '" onfocus="storeCaret(this);" onselect="storeCaret(this);" onclick="storeCaret(this);" onkeyup="storeCaret(this);">'
        . $content
        . '</textarea>';

    echo '<script type="text/javascript">';
    echo 'var bbcode = new BBCode(document.' . $form . '.' . $name . ');';
    echo 'var ctrl = "ctrl";';
    echo 'bbcode.addTag("codeB","b",null,"B",ctrl);';
    echo 'bbcode.addTag("codeBB","bb",null,"N",ctrl);';
    echo 'bbcode.addTag("codePRE","pre",null,"P",ctrl);';
    echo 'bbcode.addTag("codeHIDE","hide",null,"",ctrl);';
    echo 'bbcode.addTag("codeI","i",null,"I",ctrl);';
    echo 'bbcode.addTag("codeU","u",null,"U",ctrl);';
    echo 'bbcode.addTag("codeS","s",null,"",ctrl);';
    echo 'bbcode.addTag("codeQuote","quote",null,"Q",ctrl);';
    echo 'bbcode.addTag("codeImg","img",null,"R",ctrl);';
    echo 'bbcode.addTag("codeUri","url","/url","",ctrl);';
    echo 'bbcode.addTag("codeUr","url=","/url","",ctrl);';
    echo 'bbcode.addTag("codeCode","php",null,"K",ctrl);';
    echo 'bbcode.addTag("codeFlash","flash",null,"F",ctrl);';
    echo 'bbcode.addTag("codeOpt","li","","0",ctrl);';
    echo 'bbcode.addTag("codeHR","hr","","8",ctrl);';
    echo 'bbcode.addTag("codeYT","video=","","",ctrl);';
    echo 'bbcode.addTag("codeBR","br","","",ctrl);';
    echo 'bbcode.addTag("codeSpoiler","spoiler",null,"S",ctrl);';
    echo 'bbcode.addTag("codeMM","[mcom=#529EDC:#F5F5F5]","/mcom","",ctrl);';

    // ВАЖНО: для селектов мы используем change в bbcode.js,
    // а тут добавим защиту от пустого значения, чтобы не схлопывать/не вставлять мусор
    echo 'bbcode.addTag("fontFace",function(e){var v=e.value; if(!v){return ""} e.selectedIndex=0; return "font="+v}, "/font");';
    echo 'bbcode.addTag("codeColor",function(e){var v=e.value; if(!v){return ""} e.selectedIndex=0; return "color="+v}, "/color");';
    echo 'bbcode.addTag("codeSize",function(e){var v=e.value; if(!v){return ""} e.selectedIndex=0; return "size="+v}, "/size");';
    echo 'bbcode.addTag("codeAlign",function(e){var v=e.value; if(!v){return ""} e.selectedIndex=0; return "align="+v}, "/align");';
    echo '</script>';

    echo '</div><div id="prevsmalie" align="center" name="' . htmlspecialchars($form, ENT_QUOTES, 'UTF-8') . ':' . htmlspecialchars($id_area, ENT_QUOTES, 'UTF-8') . '"></div>';
    echo '</td></tr>';

    if (!$isDetails) {
        $preview = $tracker_lang['preview'] ?? 'Предпросмотр';
        $reset   = $tracker_lang['reset'] ?? 'Сброс';
        echo '<tr><td style="margin:0;padding:0" align="center" class="b">'
            . '<input type="button" name="preview" class="btn" title="ALT+ENTER ' . htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') . '" onclick="javascript:ajaxpreview(\'' . addslashes($id_area) . '\');" /> '
            . '<input type="reset" class="btn" value="' . htmlspecialchars($reset, ENT_QUOTES, 'UTF-8') . '" />'
            . '</td></tr>'
            . '<tr><td id="preview" style="margin:0;padding:0" class="a"></td></tr>';
    }

    echo '</table>';
}


function get_row_count($table, $suffix = "") {
	if ($suffix)
		$suffix = " $suffix";
	($r = sql_query("SELECT COUNT(*) FROM $table$suffix")) or die(mysql_error());
	($a = mysqli_fetch_row($r)) or die(mysql_error());
	return $a[0];
}

/*function stdmsg($heading = '', $text = '') {
	print("<table class=\"main\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\"><tr><td class=\"embedded\">\n");
	if ($heading)
		print("<h2>$heading</h2>\n");
	print("<table width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"10\"><tr><td class=\"text\">\n");
	print($text . "</td></tr></table></td></tr></table>\n");
}*/

function stdmsg($heading = '', $text = '', $div = 'success', $htmlstrip = false) {
	if ($htmlstrip) {
		$heading = htmlspecialchars_uni(trim($heading));
		$text = htmlspecialchars_uni(trim($text));
	}
	print("<table class=\"main\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\"><tr><td class=\"embedded\">\n");
	print("<div class=\"$div\">" . ($heading ? "<b>$heading</b><br />" : "") . "$text</div></td></tr></table>\n");
}

function stderr($heading = '', $text = '') {
	stdhead();
	stdmsg($heading, $text, 'error');
	stdfoot();
	die;
}

function newerr($heading = '', $text = '', $head = true, $foot = true, $die = true, $div = 'error', $htmlstrip = true) {
	if ($head)
		stdhead($heading);

	newmsg($heading, $text, $div, $htmlstrip);

	if ($foot)
		stdfoot();

	if ($die)
		die;
}

function sqlerr($file = '', $line = '') {
	global $queries;
	print("<table border=\"0\" bgcolor=\"blue\" align=\"left\" cellspacing=\"0\" cellpadding=\"10\" style=\"background: blue\">" .
		"<tr><td class=\"embedded\"><font color=\"white\"><h1>������ � SQL</h1>\n" .
		"<b>����� �� ������� MySQL: " . htmlspecialchars_uni(mysql_error()) . ($file != '' && $line != '' ? "<p>� $file, ����� $line</p>" : "") . "<p>������ ����� $queries.</p></b></font></td></tr></table>");
	die;
}

// Returns the current time in GMT in MySQL compatible format.
function get_date_time($timestamp = 0) {
	if ($timestamp)
		return date("Y-m-d H:i:s", $timestamp);
	else
		return date("Y-m-d H:i:s");
}

function encodehtml($s, $linebreaks = true) {
	$s = str_replace("<", "&lt;", str_replace("&", "&amp;", $s));
	if ($linebreaks)
		$s = nl2br($s);
	return $s;
}

function get_dt_num() {
	return date("YmdHis");
}

function format_urls($s) {
	return preg_replace(
		"/(\A|[^=\]'\"a-zA-Z0-9])((http|ftp|https|ftps|irc):\/\/[^()<>\s]+)/i",
		"\\1<a href=\"\\2\">\\2</a>", $s);
}

/*

// Removed this fn, I've decided we should drop the redir script...
// it's pretty useless since ppl can still link to pics...
// -Rb

function format_local_urls($s)
{
	return preg_replace(
    "/(<a href=redir\.php\?url=)((http|ftp|https|ftps|irc):\/\/(www\.)?torrentbits\.(net|org|com)(:8[0-3])?([^<>\s]*))>([^<]+)<\/a>/i",
    "<a href=\\2>\\8</a>", $s);
}
*/

//Finds last occurrence of needle in haystack
//in PHP5 use strripos() instead of this
function _strlastpos($haystack, $needle, $offset = 0) {
	$addLen = strlen($needle);
	$endPos = $offset - $addLen;
	while (true) {
		if (($newPos = strpos($haystack, $needle, $endPos + $addLen)) === false) break;
		$endPos = $newPos;
	}
	return ($endPos >= 0) ? $endPos : false;
}

function format_quotes($s) {
	while ($old_s != $s) {
		$old_s = $s;

		//find first occurrence of [/quote]
		$close = strpos($s, "[/quote]");
		if ($close === false)
			return $s;

		//find last [quote] before first [/quote]
		//note that there is no check for correct syntax
		$open = _strlastpos(substr($s, 0, $close), "[quote");
		if ($open === false)
			return $s;

		$quote = substr($s, $open, $close - $open + 8);

		//[quote]Text[/quote]
		$quote = preg_replace(
			"/\[quote\]\s*((\s|.)+?)\s*\[\/quote\]\s*/i",
			"<p class=sub><b>Quote:</b></p><table class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"10\"><tr><td style=\"border: 1px black dotted\">\\1</td></tr></table><br />",
			$quote);

		//[quote=Author]Text[/quote]
		$quote = preg_replace(
			"/\[quote=(.+?)\]\s*((\s|.)+?)\s*\[\/quote\]\s*/i",
			"<p class=sub><b>\\1 wrote:</b></p><table class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"10\"><tr><td style=\"border: 1px black dotted\">\\2</td></tr></table><br />",
			$quote);

		$s = substr($s, 0, $open) . $quote . substr($s, $close + 8);
	}

	return $s;
}

// Format quote
function encode_quote($text) {
	$start_html = "<div align=\"center\"><div style=\"width: 85%; overflow: auto\">"
		. "<table width=\"100%\" cellspacing=\"1\" cellpadding=\"3\" border=\"0\" align=\"center\" class=\"bgcolor4\">"
		. "<tr bgcolor=\"FFE5E0\"><td><font class=\"block-title\">������</font></td></tr><tr class=\"bgcolor1\"><td>";
	$end_html = "</td></tr></table></div></div>";
	$text = preg_replace("#\[quote\](.*?)\[/quote\]#si", "" . $start_html . "\\1" . $end_html . "", $text);
	return $text;
}

// Format quote from
function encode_quote_from($text) {
	$start_html = "<div align=\"center\"><div style=\"width: 85%; overflow: auto\">"
		. "<table width=\"100%\" cellspacing=\"1\" cellpadding=\"3\" border=\"0\" align=\"center\" class=\"bgcolor4\">"
		. "<tr bgcolor=\"FFE5E0\"><td><font class=\"block-title\">\\1 �����</font></td></tr><tr class=\"bgcolor1\"><td>";
	$end_html = "</td></tr></table></div></div>";
	$text = preg_replace("#\[quote=(.+?)\](.*?)\[/quote\]#si", "" . $start_html . "\\2" . $end_html . "", $text);
	return $text;
}

// Format spoiler
/*function encode_spoiler($text) {
	$replace = "<div class=\"spoiler-wrap\"><div class=\"spoiler-head folded clickable\">������� �����</div><div class=\"spoiler-body\"><textarea>\\1</textarea></div></div>";
	$text = preg_replace("#\[hide\](.*?)\[/hide\]#si", $replace, $text);
	return $text;
}

// Format spoiler from
function encode_spoiler_from($text) {
	$replace = "<div class=\"spoiler-wrap\"><div class=\"spoiler-head folded clickable\">\\1</div><div class=\"spoiler-body\"><textarea>\\2</textarea></div></div>";
	$text = preg_replace("#\[hide=(.+?)\](.*?)\[/hide\]#si", "".$replace, $text);
	return $text;
}*/

// Thanks to Leonid Evstigneev from TorrentsZona for figuring this shit out...
// Format spoiler
function encode_spoiler($text) {
	$text = preg_replace_callback("#\[hide\](.*?)\[/hide\]#si", 'escape1', $text);
	return $text;
}

// Format spoiler from
function encode_spoiler_from($text) {
	$text = preg_replace_callback("#\[hide=(.+?)\](.*?)\[/hide\]#si", 'escape2', $text);
	return $text;
}

// Format spoiler
function escape1($matches) {
	return "<div class=\"spoiler-wrap\"><div class=\"spoiler-head folded clickable\">������� �����</div><div class=\"spoiler-body\"><textarea>" . htmlspecialchars_uni($matches[1]) . "</textarea></div></div>";
}

// Format spoiler from
function escape2($matches) {
	return "<div class=\"spoiler-wrap\"><div class=\"spoiler-head folded clickable\">" . $matches[1] . "</div><div class=\"spoiler-body\"><textarea>" . htmlspecialchars_uni($matches[2]) . "</textarea></div></div>";
}

// Format code
function encode_code($text) {
	$start_html = "<div align=\"center\"><div style=\"width: 85%; overflow: auto\">"
		. "<table width=\"100%\" cellspacing=\"1\" cellpadding=\"3\" border=\"0\" align=\"center\" class=\"bgcolor4\">"
		. "<tr bgcolor=\"E5EFFF\"><td colspan=\"2\"><font class=\"block-title\">���</font></td></tr>"
		. "<tr class=\"bgcolor1\"><td align=\"right\" class=\"code\" style=\"width: 5px; border-right: none\">{ZEILEN}</td><td class=\"code\">";
	$end_html = "</td></tr></table></div></div>";
	$match_count = preg_match_all("#\[code\](.*?)\[/code\]#si", $text, $matches);
	for ($mout = 0; $mout < $match_count; ++$mout) {
		$before_replace = $matches[1][$mout];
		$after_replace = $matches[1][$mout];
		$after_replace = trim($after_replace);
		$zeilen_array = explode("<br />", $after_replace);
		$j = 1;
		$zeilen = "";
		foreach ($zeilen_array as $str) {
			$zeilen .= "" . $j . "<br />";
			++$j;
		}
		$after_replace = str_replace("", "", $after_replace);
		$after_replace = str_replace("&amp;", "&", $after_replace);
		$after_replace = str_replace("", "&nbsp; ", $after_replace);
		$after_replace = str_replace("", " &nbsp;", $after_replace);
		$after_replace = str_replace("", "&nbsp; &nbsp;", $after_replace);
		$after_replace = preg_replace("/^ {1}/m", "&nbsp;", $after_replace);
		$str_to_match = "[code]" . $before_replace . "[/code]";
		$replace = str_replace("{ZEILEN}", $zeilen, $start_html);
		$replace .= $after_replace;
		$replace .= $end_html;
		$text = str_replace($str_to_match, $replace, $text);
	}

	$text = str_replace("[code]", $start_html, $text);
	$text = str_replace("[/code]", $end_html, $text);
	return $text;
}

function encode_php($text) {
	$start_html = "<div align=\"center\"><div style=\"width: 85%; overflow: auto\">"
		. "<table width=\"100%\" cellspacing=\"1\" cellpadding=\"3\" border=\"0\" align=\"center\" class=\"bgcolor4\">"
		. "<tr bgcolor=\"F3E8FF\"><td colspan=\"2\"><font class=\"block-title\">PHP - ���</font></td></tr>"
		. "<tr class=\"bgcolor1\"><td align=\"right\" class=\"code\" style=\"width: 5px; border-right: none\">{ZEILEN}</td><td>";
	$end_html = "</td></tr></table></div></div>";
	$match_count = preg_match_all("#\[php\](.*?)\[/php\]#si", $text, $matches);
	for ($mout = 0; $mout < $match_count; ++$mout) {
		$before_replace = $matches[1][$mout];
		$after_replace = $matches[1][$mout];
		$after_replace = trim($after_replace);
		$after_replace = str_replace("&lt;", "<", $after_replace);
		$after_replace = str_replace("&gt;", ">", $after_replace);
		$after_replace = str_replace("&quot;", '"', $after_replace);
		$after_replace = preg_replace("/<br.*/i", "", $after_replace);
		$after_replace = (substr($after_replace, 0,
								 5) != "<?php") ? "<?php\n" . $after_replace . "" : "" . $after_replace . "";
		$after_replace = (substr($after_replace, -2) != "?>") ? "" . $after_replace . "\n?>" : "" . $after_replace . "";
		ob_start();
		highlight_string($after_replace);
		$after_replace = ob_get_contents();
		ob_end_clean();
		$zeilen_array = explode("<br />", $after_replace);
		$j = 1;
		$zeilen = "";
		foreach ($zeilen_array as $str) {
			$zeilen .= "" . $j . "<br />";
			++$j;
		}
		$after_replace = str_replace("\n", "", $after_replace);
		$after_replace = str_replace("&amp;", "&", $after_replace);
		$after_replace = str_replace("  ", "&nbsp; ", $after_replace);
		$after_replace = str_replace("  ", " &nbsp;", $after_replace);
		$after_replace = str_replace("\t", "&nbsp; &nbsp;", $after_replace);
		$after_replace = preg_replace("/^ {1}/m", "&nbsp;", $after_replace);
		$str_to_match = "[php]" . $before_replace . "[/php]";
		$replace = str_replace("{ZEILEN}", $zeilen, $start_html);
		$replace .= $after_replace;
		$replace .= $end_html;
		$text = str_replace($str_to_match, $replace, $text);
	}
	$text = str_replace("[php]", $start_html, $text);
	$text = str_replace("[/php]", $end_html, $text);
	return $text;
}

function code_nobb($matches) {
	$code = $matches[1];
	$code = str_replace("[", "&#91;", $code);
	$code = str_replace("]", "&#93;", $code);
	return '[code]' . $code . '[/code]';
}



function format_comment(string $text, bool $strip_html = true): string
{
    global $smilies, $privatesmilies, $pic_base_url;

    $s = $text;

    // legacy wink
    $s = str_replace(";)", ":wink:", $s);

    // [code]...[/code] — вырезаем до экранирования (как у тебя было)
    $s = preg_replace_callback("#\[code\](.*?)\[/code\]#si", "code_nobb", $s);

    // экранирование HTML
    if ($strip_html) {
        $s = htmlspecialchars_uni($s);
    }

    // Доп. псевдо-теги (из второго кода)
    $s = str_replace(
        "[pi]",
        "<div align=\"center\" style=\"font-size:25px;width:auto;position:relative;\">&#8604; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &#9986; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &ndash; &#8605;</div>",
        $s
    );
    $s = str_replace(
        "[me]",
        "<script type=\"text/javascript\" src=\"/js/blink.js\"></script><blink><font color=\"red\">IMHO</font></blink>&nbsp;",
        $s
    );

    // Безопасная сборка URL для img/audio/flash
    $safeUrl = static function (string $url): string {
        $url = trim($url);
        if ($url === '') return '';
        // режем очевидные опасности
        if (preg_match('#^(?:javascript|data|vbscript):#i', $url)) return '';
        // допускаем http/https и относительные пути
        if (preg_match('#^(https?://|/|\.{1,2}/)#i', $url)) return $url;
        return $url; // оставляем как было (TBDev-совместимость), но без js/data схем
    };

    $bb = [];
    $html = [];

    // --- IMG (чуть актуальнее: https тоже) ---
    $bb[]   = "#\[img\]((?!javascript:|data:|vbscript:)[^\[]+?)\[/img\]#i";
    $html[] = "<img class=\"linked-image\" src=\"\\1\" border=\"0\" alt=\"\\1\" title=\"\\1\" />";

    $bb[]   = "#\[img=([a-zA-Z]+)\]((?!javascript:|data:|vbscript:)[^\[]+?)\[/img\]#is";
    $html[] = "<img class=\"linked-image\" src=\"\\2\" align=\"\\1\" border=\"0\" alt=\"\\2\" title=\"\\2\" />";

    $bb[]   = "#\[img\ alt=([a-zA-ZА-Яа-я0-9_\-\. ]+)\]((?!javascript:|data:|vbscript:)[^\[]+?)\[/img\]#is";
    $html[] = "<img class=\"linked-image\" src=\"\\2\" align=\"\\1\" border=\"0\" alt=\"\\1\" title=\"\\1\" />";

    $bb[]   = "#\[img=([a-zA-Z]+)\ alt=([a-zA-ZА-Яа-я0-9_\-\. ]+)\]((?!javascript:|data:|vbscript:)[^\[]+?)\[/img\]#is";
    $html[] = "<img class=\"linked-image\" src=\"\\3\" align=\"\\1\" border=\"0\" alt=\"\\2\" title=\"\\2\" />";

    // --- KP ---
    $bb[]   = "#\[kp=([0-9]+)\]#is";
    $html[] = "<a href=\"http://www.kinopoisk.ru/level/1/film/\\1/\" rel=\"nofollow\"><img src=\"http://www.kinopoisk.ru/rating/\\1.gif/\" alt=\"Рейтинг\" title=\"Рейтинг\" border=\"0\" /></a>";

    // --- URL / MAIL (как было) ---
    $bb[]   = "#\[url\]([\w]+?://([\w\#$%&~/.\-;:=,?@\]+]+|\[(?!url=))*?)\[/url\]#is";
    $html[] = "<a href=\"\\1\" title=\"\\1\">\\1</a>";

    $bb[]   = "#\[url\]((www|ftp)\.([\w\#$%&~/.\-;:=,?@\]+]+|\[(?!url=))*?)\[/url\]#is";
    $html[] = "<a href=\"http://\\1\" title=\"\\1\">\\1</a>";

    $bb[]   = "#\[url=([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*?)\]([^?\n\r\t].*?)\[/url\]#is";
    $html[] = "<a href=\"\\1\" title=\"\\1\">\\2</a>";

    $bb[]   = "#\[url=((www|ftp)\.[\w\#$%&~/.\-;:=,?@\[\]+]*?)\]([^?\n\r\t].*?)\[/url\]#is";
    $html[] = "<a href=\"http://\\1\" title=\"\\1\">\\3</a>";

    $bb[]   = "/\[url=([^()<>\s]+?)\]((\s|.)+?)\[\/url\]/i";
    $html[] = "<a href=\"\\1\">\\2</a>";

    $bb[]   = "/\[url\]([^()<>\s]+?)\[\/url\]/i";
    $html[] = "<a href=\"\\1\">\\1</a>";

    $bb[]   = "#\[mail\](\S+?)\[/mail\]#i";
    $html[] = "<a href=\"mailto:\\1\">\\1</a>";

    $bb[]   = "#\[mail\s*=\s*([\.\w\-]+\@[\.\w\-]+\.[\w\-]+)\s*\](.*?)\[\/mail\]#i";
    $html[] = "<a href=\"mailto:\\1\">\\2</a>";

    // --- COLOR / FONT / SIZE / ALIGN ---
    $bb[]   = "#\[color=(\#[0-9A-F]{6}|[a-z]+)\](.*?)\[/color\]#si";
    $html[] = "<span style=\"color: \\1\">\\2</span>";

    $bb[]   = "#\[(font|family)=([A-Za-z ]+)\](.*?)\[/\\1\]#si";
    $html[] = "<span style=\"font-family: \\2\">\\3</span>";

    // из второго кода: [font=Arial]...[/font]
    $bb[]   = "#\[font=([a-zA-Z ,]+)\](.*?)\[/font\]#si";
    $html[] = "<span style=\"font-family: \\1\">\\2</span>";

    $bb[]   = "#\[size=([0-9]+)\](.*?)\[/size\]#si";
    $html[] = "<span style=\"font-size: \\1\">\\2</span>";

    // из второго кода: [align=center]...[/align]
    $bb[]   = "#\[align=(left|right|center|justify)\](.*?)\[/align\]#is";
    $html[] = "<div align=\"\\1\">\\2</div>";

    // --- BASIC ---
    $bb[]   = "#\[(left|right|center|justify)\](.*?)\[/\\1\]#is";
    $html[] = "<div align=\"\\1\">\\2</div>";

    $bb[]   = "#\[b\](.*?)\[/b\]#si";
    $html[] = "<strong>\\1</strong>";

    $bb[]   = "#\[i\](.*?)\[/i\]#si";
    $html[] = "<i>\\1</i>";

    $bb[]   = "#\[h\](.*?)\[/h\]#si";
    $html[] = "<h3>\\1</h3>";

    $bb[]   = "#\[u\](.*?)\[/u\]#si";
    $html[] = "<u>\\1</u>";

    $bb[]   = "#\[s\](.*?)\[/s\]#si";
    $html[] = "<s>\\1</s>";

    $bb[]   = "#\[li\]#si";
    $html[] = "<li>";

    $bb[]   = "#\[hr\]#si";
    $html[] = "<hr>";

    $bb[]   = "#\[br\]#si";
    $html[] = "<br />";

    // --- PRE / HIGHLIGHT / LEGEND / MARQUEE ---
    $bb[]   = "#\[pre\](.*?)\[/pre\]#si";
    $html[] = "<pre>\\1</pre>";

    $bb[]   = "#\[highlight\](.*?)\[/highlight\]#si";
    $html[] = "<table border=\"0\" cellspacing=\"0\" cellpadding=\"1\"><tr><td bgcolor=\"white\"><b>\\1</b></td></tr></table>";

    $bb[]   = "#\[legend=(.*?)\](.*?)\[/legend\]#si";
    $html[] = "<fieldset><legend>\\1</legend>\\2</fieldset>";

    $bb[]   = "#\[legend\](.*?)\[/legend\]#si";
    $html[] = "<fieldset>\\1</fieldset>";

    $bb[]   = "#\[marquee\](.*?)\[/marquee\]#si";
    $html[] = "<marquee behavior=\"alternate\">\\1</marquee>";

    // --- AUDIO ---
    $bb[]   = "#\[audio\]([^\[]+?)\[/audio\]#si";
    $html[] = "<embed autostart=\"false\" loop=\"false\" controller=\"true\" width=\"220\" height=\"42\" src=\"\\1\"></embed>";

    // --- FLASH (оба варианта из второго кода) ---
    $bb[]   = "#\[flash=(\d{1,4}):(\d{1,4})\]((?:https?://|www\.)[^\s\[]+?\.swf)\[/flash\]#si";
    $html[] = "<param name=\"movie\" value=\"\\3\" /><embed width=\"\\1\" height=\"\\2\" src=\"\\3\"></embed>";

    $bb[]   = "#\[flash\]((?:https?://|www\.)[^\s\[]+?\.swf)\[/flash\]#si";
    $html[] = "<param name=\"movie\" value=\"\\1\" /><embed width=\"470\" height=\"310\" src=\"\\1\"></embed>";

    // --- MCOM ---
    $bb[]   = "#\[mcom=(\#[a-f0-9]{6}):(\#[a-f0-9]{6})\](.*?)\[/mcom\]#si";
    $html[] = "<div style=\"background-color: \\1; color: \\2; font-weight: bold; font-size: small;\">\\3</div>";

    // --- YOUTUBE (как было) ---
    $bb[]   = "#\[youtube=([[:alnum:]]+)\]#si";
    $html[] = "<iframe width=\"640\" height=\"360\" src=\"//www.youtube.com/embed/\\1?rel=0\" frameborder=\"0\" allowfullscreen></iframe>";

    // --- VIDEO=YouTube (из второго кода; поддержка youtu.be / youtube.com / nocookie) ---
    $bb[]   = "#\[video=(https?://(?:[a-z\\d-]+\\.)?youtu(?:be(?:-nocookie)?\\.com/.*?(?:v=|/embed/|/v/)|\\.be/)([-\\w]{11}).*?)\]#si";
    $html[] = "<iframe width=\"560\" height=\"315\" src=\"https://www.youtube.com/embed/\\2\" frameborder=\"0\" allowfullscreen></iframe>";

    $s = preg_replace($bb, $html, $s);

    // Минимальная зачистка (как у тебя было)
    $s = str_replace(["javascript", "alert", "<body", "<html"], "", $s);

    // Linebreaks
    $s = nl2br($s);

    // URLs (твоя функция)
    $s = format_urls($s);

    // Smilies
    foreach ((array)$smilies as $code => $url) {
        $s = str_replace($code, "<img border=\"0\" src=\"{$pic_base_url}/smilies/{$url}\">", $s);
    }
    foreach ((array)$privatesmilies as $code => $url) {
        $s = str_replace($code, "<img border=\"0\" src=\"{$pic_base_url}/smilies/{$url}\">", $s);
    }

    // Quotes / hide / spoiler (как у тебя было; не трогаю логику encode_*)
    while (preg_match("#\[quote\](.*?)\[/quote\]#si", $s)) {
        $s = encode_quote($s);
    }
    while (preg_match("#\[quote=(.+?)\](.*?)\[/quote\]#si", $s)) {
        $s = encode_quote_from($s);
    }
    while (preg_match("#\[hide\](.*?)\[/hide\]#si", $s)) {
        $s = encode_spoiler($s);
    }
    while (preg_match("#\[hide=(.+?)\](.*?)\[/hide\]#si", $s)) {
        $s = encode_spoiler_from($s);
    }

    if (preg_match("#\[code\](.*?)\[/code\]#si", $s)) $s = encode_code($s);
    if (preg_match("#\[php\](.*?)\[/php\]#si", $s))   $s = encode_php($s);

    // Лёгкая страховка для embed’ов (применяем к уже собранным тегам)
    // (если URL опасный — выкидываем src)
    $s = preg_replace_callback('#(<(?:img|embed)\b[^>]*\bsrc=")([^"]+)(")#i', static function ($m) use ($safeUrl) {
        $u = $safeUrl($m[2]);
        return $u === '' ? '' : $m[1] . $u . $m[3];
    }, $s);

    return $s;
}

function get_user_class(): int 
{
    global $CURUSER;
    
    // Проверяем, существует ли CURUSER и является ли он массивом
    if (!isset($CURUSER) || !is_array($CURUSER)) {
        return 0; // Класс для гостя
    }
    
    // Возвращаем класс пользователя или 0 если не установлен
    return $CURUSER["class"] ?? 0;
}

function get_user_class_name($class) {
	global $tracker_lang;
	switch ($class) {
		case UC_USER:
			return $tracker_lang['class_user'];

		case UC_POWER_USER:
			return $tracker_lang['class_power_user'];

		case UC_VIP:
			return $tracker_lang['class_vip'];

		case UC_UPLOADER:
			return $tracker_lang['class_uploader'];

		case UC_MODERATOR:
			return $tracker_lang['class_moderator'];

		case UC_ADMINISTRATOR:
			return $tracker_lang['class_administrator'];

		case UC_SYSOP:
			return $tracker_lang['class_sysop'];
	}
	return "";
}

function is_valid_user_class($class) {
	return is_numeric($class) && floor($class) == $class && $class >= UC_USER && $class <= UC_SYSOP;
}

//----------------------------------
//---- Security function v0.1 by xam
//----------------------------------
function int_check($value, $stdhead = false, $stdfood = true, $die = true, $log = true) {
	global $CURUSER;
	$msg = "Invalid ID Attempt: Username: " . $CURUSER["username"] . " - UserID: " . $CURUSER["id"] . " - UserIP : " . getip();
	if (is_array($value)) {
		foreach ($value as $val) int_check($val);
	} else {
		if (!is_valid_id($value)) {
			if ($stdhead) {
				if ($log)
					write_log($msg);
				stderr("ERROR", "Invalid ID! For security reason, we have been logged this action.");
			} else {
				Print ("<h2>Error</h2><table width=100% border=1 cellspacing=0 cellpadding=10><tr><td class=text>");
				Print ("Invalid ID! For security reason, we have been logged this action.</td></tr></table>");
				if ($log)
					write_log($msg);
			}

			if ($stdfood)
				stdfoot();
			if ($die)
				die;
		} else
			return true;
	}
}

//----------------------------------
//---- Security function v0.1 by xam
//----------------------------------

function is_valid_id($id) {
	return is_numeric($id) && ($id > 0) && (floor($id) == $id);
}

function sql_ts_to_ut($s) {
	return sql_timestamp_to_unix_timestamp($s);
}

function sql_timestamp_to_unix_timestamp($s) {
	return mktime(substr($s, 11, 2), substr($s, 14, 2), substr($s, 17, 2), substr($s, 5, 2), substr($s, 8, 2),
				  substr($s, 0, 4));
}

function get_ratio_color($ratio) {
	if ($ratio < 0.1) return "#ff0000";
	if ($ratio < 0.2) return "#ee0000";
	if ($ratio < 0.3) return "#dd0000";
	if ($ratio < 0.4) return "#cc0000";
	if ($ratio < 0.5) return "#bb0000";
	if ($ratio < 0.6) return "#aa0000";
	if ($ratio < 0.7) return "#990000";
	if ($ratio < 0.8) return "#880000";
	if ($ratio < 0.9) return "#770000";
	if ($ratio < 1) return "#660000";
	return "#000000";
}

function get_slr_color($ratio) {
	if ($ratio < 0.025) return "#ff0000";
	if ($ratio < 0.05) return "#ee0000";
	if ($ratio < 0.075) return "#dd0000";
	if ($ratio < 0.1) return "#cc0000";
	if ($ratio < 0.125) return "#bb0000";
	if ($ratio < 0.15) return "#aa0000";
	if ($ratio < 0.175) return "#990000";
	if ($ratio < 0.2) return "#880000";
	if ($ratio < 0.225) return "#770000";
	if ($ratio < 0.25) return "#660000";
	if ($ratio < 0.275) return "#550000";
	if ($ratio < 0.3) return "#440000";
	if ($ratio < 0.325) return "#330000";
	if ($ratio < 0.35) return "#220000";
	if ($ratio < 0.375) return "#110000";
	return "#000000";
}

function write_log($text, $color = "transparent", $type = "tracker") {
	$type = sqlesc($type);
	$color = sqlesc($color);
	$text = sqlesc($text);
	$added = sqlesc(get_date_time());
	sql_query("INSERT INTO sitelog (added, color, txt, type) VALUES($added, $color, $text, $type)");
}

function getWord($number, $suffix) {
	$keys = array(2, 0, 1, 1, 1, 2);
	$mod = $number % 100;
	$suffix_key = ($mod > 7 && $mod < 20) ? 2: $keys[min($mod % 10, 5)];
	return $suffix[$suffix_key];
}

function get_et($ts) {
	return get_elapsed_time_plural($ts);
}

function get_lt($ts) {
	return get_left_time_plural($ts);
}

function get_left_time_plural($time_end, $decimals = 0) {
    $seconds_left = $time_end - TIMENOW;
    
    if ($seconds_left <= 0) {
        return "0 минут";
    }
    
    $divider = [
        'years'   => 31536000, // 60 * 60 * 24 * 365
        'months'  => 2592000,  // 60 * 60 * 24 * 30
        'weeks'   => 604800,   // 60 * 60 * 24 * 7
        'days'    => 86400,    // 60 * 60 * 24
        'hours'   => 3600,     // 60 * 60
        'minutes' => 60
    ];
    
    $langs = [
        'years'   => ["год", "года", "лет"],
        'months'  => ["месяц", "месяца", "месяцев"],
        'weeks'   => ["неделя", "недели", "недель"],
        'days'    => ["день", "дня", "дней"],
        'hours'   => ["час", "часа", "часов"],
        'minutes' => ["минута", "минуты", "минут"]
    ];
    
    $value = 0;
    $unit = 'minutes';
    
    foreach ($divider as $unit_name => $div) {
        if ($seconds_left >= $div) {
            $value = floor($seconds_left / $div);
            $unit = $unit_name;
            break;
        }
    }
    
    // Если прошло меньше минуты, показываем секунды
    if ($value === 0 && $seconds_left > 0) {
        $value = $seconds_left;
        $langs['seconds'] = ["секунда", "секунды", "секунд"];
        $unit = 'seconds';
    }
    
    return $value . ' ' . getWord($value, $langs[$unit]);
}

function get_elapsed_time_plural($time_start, $decimals = 0) {
    $divider['years']   = (60 * 60 * 24 * 365);
    $divider['months']  = (60 * 60 * 24 * 365 / 12);
    $divider['weeks']   = (60 * 60 * 24 * 7);
    $divider['days']    = (60 * 60 * 24);
    $divider['hours']   = (60 * 60);
    $divider['minutes'] = (60);

    $langs['years']     = array("год", "года", "лет");
    $langs['months']    = array("месяц", "месяца", "месяцев");
    $langs['weeks']     = array("неделя", "недели", "недель");
    $langs['days']      = array("день", "дня", "дней");
    $langs['hours']     = array("час", "часа", "часов");
    $langs['minutes']   = array("минута", "минуты", "минут");

    foreach ($divider as $unit => $div) {
        ${'elapsed_time_'.$unit} = floor(((TIMENOW - $time_start) / $div));
        if (${'elapsed_time_'.$unit} >= 1)
            break;
    }
    $elapsed_time = ${'elapsed_time_'.$unit} . ' ' . getWord(${'elapsed_time_'.$unit}, $langs[$unit]);

    return $elapsed_time;
}

function get_elapsed_time($ts) {
    $seconds = time() - $ts;
    
    if ($seconds < 60) {
        return "< 1 минуты";
    }
    
    $mins = floor($seconds / 60);
    $hours = floor($mins / 60);
    $mins -= $hours * 60;
    $days = floor($hours / 24);
    $hours -= $days * 24;
    $weeks = floor($days / 7);
    $days -= $weeks * 7;
    
    if ($weeks > 0) {
        $ending = match(true) {
            $weeks % 10 == 1 && $weeks % 100 != 11 => "а",
            ($weeks % 10 >= 2 && $weeks % 10 <= 4) && ($weeks % 100 < 10 || $weeks % 100 >= 20) => "и",
            default => ""
        };
        return "$weeks недел$ending";
    }
    
    if ($days > 0) {
        $ending = match($days) {
            1 => "ь",
            2, 3, 4 => "я",
            default => "ей"
        };
        return "$days дн$ending";
    }
    
    if ($hours > 0) {
        $ending = match($hours % 10) {
            1 => "",
            2, 3, 4 => "а",
            default => "ов"
        };
        return "$hours час$ending";
    }
    
    if ($mins > 0) {
        $ending = match($mins % 10) {
            1 => "а",
            2, 3, 4 => "ы",
            default => ""
        };
        return "$mins минут$ending";
    }
    
    return "< 1 минуты";
}

?>