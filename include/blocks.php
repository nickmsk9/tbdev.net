<?php

# IMPORTANT: Do not edit below unless you know what you are doing!
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

function render_blocks($blockfile, $blocktitle, $content, $bid, $bposition, $allow_hide) {
    global $showbanners, $allow_block_hide;
    
    if ($blockfile != "") {
        if (file_exists("blocks/" . $blockfile)) {
            if (!defined('BLOCK_FILE'))
                define('BLOCK_FILE', 1);
            require("blocks/" . $blockfile);
        } else {
            $content = "<center>Файл блока не найден!</center>";
        }
    }

    if (!((isset($content) AND !empty($content)))) {
        $content = "<center>Содержимое блока пустое!</center>";
    }

    if ($allow_block_hide && ($allow_hide || get_user_class() >= UC_ADMINISTRATOR)) {
        $hidden_blocks = (isset($_COOKIE['hb']) && !empty($_COOKIE['hb']) ? unserialize($_COOKIE['hb']) : array());
        $display = 'block';
        $picture = 'minus';
        $alt = 'Скрыть';
        if (in_array($bid, $hidden_blocks)) {
            $display = 'none';
            $picture = 'plus';
            $alt = 'Показать';
        }
        $blocktitle = $blocktitle . '&nbsp;<span style="cursor: pointer;" onclick="javascript: block_switch(\''.$bid.'\');"><img border="0" src="pic/'.$picture.'.gif" id="picb'.$bid.'" title="'.$alt.'"></span>';
        $content = '<span id="sb'.$bid.'" style="display: '.$display.';">' . $content . '</span>';
    }

    themesidebox($blocktitle, $content, $bposition);
    return null;
}

function themesidebox($title, $content, $pos) {
    global $blockfile, $b_id, $ss_uri, $tracker_lang;
    static $bl_mass = [];
    
    // Определяем имя блока
    if ($pos == "s" || $pos == "o") {
        $bl_name = empty($blockfile) ? "fly-block-" . $b_id : "fly-" . str_replace(".php", "", $blockfile);
    } else {
        $bl_name = empty($blockfile) ? "block-" . $b_id : str_replace(".php", "", $blockfile);
    }
    
    // Проверяем существование кастомного файла блока
    if (!isset($bl_mass[$bl_name]['m'])) {
        $bl_mass[$bl_name]['m'] = file_exists("themes/" . $ss_uri . "/html/" . $bl_name . ".html");
    }
    
    // Если есть кастомный файл
    if ($bl_mass[$bl_name]['m'] ?? false) {
        $file_content = file_get_contents("themes/" . $ss_uri . "/html/" . $bl_name . ".html");
        
        // Обрабатываем шаблон (eval для совместимости со старыми шаблонами)
        $output = eval_block_template($file_content, $title, $content);
        
        if ($pos == "o") {
            return $output;
        } else {
            echo $output;
            return;
        }
    }
    
    // Если кастомного файла нет, используем стандартные блоки
    switch($pos) {
        case 'l': $std_name = "block-left"; break;
        case 'r': $std_name = "block-right"; break;
        case 'c': $std_name = "block-center"; break;
        case 'd': $std_name = "block-down"; break;
        case 's': 
        case 'o': $std_name = "block-fly"; break;
        default: $std_name = "block-all"; break;
    }
    
    // Инициализируем стандартный блок если нужно
    if (!isset($bl_mass[$std_name]['m'])) {
        $bl_mass[$std_name]['m'] = file_exists("themes/" . $ss_uri . "/html/" . $std_name . ".html");
        
        if ($bl_mass[$std_name]['m']) {
            $f_str = file_get_contents("themes/" . $ss_uri . "/html/" . $std_name . ".html");
            
            $bl_mass[$std_name]['f'] = function($title, $content) use ($f_str) {
                return eval_block_template($f_str, $title, $content);
            };
        }
    }
    
    // Если есть стандартный файл блока
    if (($bl_mass[$std_name]['m'] ?? false) && isset($bl_mass[$std_name]['f'])) {
        $output = $bl_mass[$std_name]['f']($title, $content);
        
        if ($pos == "o") {
            return $output;
        } else {
            echo $output;
            return;
        }
    }
    
    // Проверяем существование block-all как последний вариант
    if (!isset($bl_mass['block-all']['m'])) {
        $bl_mass['block-all']['m'] = file_exists("themes/" . $ss_uri . "/html/block-all.html");
        
        if ($bl_mass['block-all']['m']) {
            $f_str = file_get_contents("themes/" . $ss_uri . "/html/block-all.html");
            
            $bl_mass['block-all']['f'] = function($title, $content) use ($f_str) {
                return eval_block_template($f_str, $title, $content);
            };
        }
    }
    
    // Если есть block-all
    if (($bl_mass['block-all']['m'] ?? false) && isset($bl_mass['block-all']['f'])) {
        $output = $bl_mass['block-all']['f']($title, $content);
        
        if ($pos == "o") {
            return $output;
        } else {
            echo $output;
            return;
        }
    }
    
    // Фолбэк: простой HTML
    $fallback_html = "<fieldset><legend>" . htmlspecialchars($title) . "</legend>" . $content . "</fieldset>";
    
    if ($pos == "o") {
        return $fallback_html;
    } else {
        echo $fallback_html;
    }
}

/**
 * Обработка шаблона блока через eval для совместимости
 */
function eval_block_template(string $template, string $title, string $content): string {
    global $ss_uri, $tracker_lang;
    
    // Экранируем кавычки для безопасного использования в eval
    $escaped_template = str_replace(['"', '$'], ['\"', '\$'], $template);
    
    // Создаем строку для eval с подставленными переменными
    $eval_code = 'return "' . $escaped_template . '";';
    
    // Выполняем eval с локальными переменными
    return eval($eval_code);
}

/**
 * Альтернативная функция с шаблонными заменами (если не работает eval)
 */
function simple_block_template(string $template, string $title, string $content): string {
    global $ss_uri;
    
    // Базовые замены
    $replacements = [
        '{$title}' => $title,
        '{$content}' => $content,
        '{$ss_uri}' => $ss_uri,
    ];
    
    // Добавляем замены для HTML-сущностей
    $replacements['{$title|html}'] = htmlspecialchars($title);
    $replacements['{$content|html}'] = htmlspecialchars($content);
    
    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

function show_blocks($position) {
    global $CURUSER, $use_blocks, $already_used, $orbital_blocks;
    static $showed_show_hide;

    if ($use_blocks) {
        if (!$already_used) {
            $blocks_res = sql_query("SELECT * FROM orbital_blocks WHERE active = 1 ORDER BY weight ASC") or sqlerr(__FILE__, __LINE__);
            $orbital_blocks = [];
            while ($blocks_row = mysqli_fetch_array($blocks_res)) {
                $orbital_blocks[] = $blocks_row;
            }
            if (!$orbital_blocks) {
                $orbital_blocks = [];
            }
            $already_used = true;
        }

        foreach ($orbital_blocks as $block) {
            if (!$showed_show_hide) {
                echo '<script language="javascript" type="text/javascript" src="js/show_hide.js"></script>';
            }
            $showed_show_hide = true;
            
            $bid = $block["bid"];
            $content = $block["content"];
            $title = $block["title"];
            $blockfile = $block["blockfile"];
            $bposition = $block["bposition"];
            $allow_hide = $block["allow_hide"] == 'yes';
            
            if ($position != $bposition) {
                continue;
            }
            
            $view = $block["view"];
            $which = explode(",", $block["which"]);
            $module_name = str_replace(".php", "", basename($_SERVER["PHP_SELF"]));
            
            if (!(in_array($module_name, $which) || in_array("all", $which) || (in_array("ihome", $which) && $module_name == "index"))) {
                continue;
            }
            
            $show_block = false;
            
            switch($view) {
                case 0: $show_block = true; break;
                case 1: $show_block = isset($CURUSER); break;
                case 2: $show_block = (get_user_class() >= UC_MODERATOR); break;
                case 3: $show_block = (!isset($CURUSER) || get_user_class() >= UC_MODERATOR); break;
            }
            
            if ($show_block) {
                render_blocks($blockfile, $title, $content, $bid, $bposition, $allow_hide);
            }
        }
    }
}

?>