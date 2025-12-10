<?php

# IMPORTANT: Do not edit below unless you know what you are doing!
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

function render_blocks($blockfile, $blocktitle, $content, $bid, $bposition, $allow_hide) {
    global $showbanners, $allow_block_hide;
    global $foot;
    
    if ($blockfile != "") {
        if (file_exists("blocks/".$blockfile)) {
            if (!defined('BLOCK_FILE'))
                define('BLOCK_FILE', 1);
            
            // Используем буферизацию вывода для захвата содержимого блока
            ob_start();
            include("blocks/".$blockfile);
            $block_content = ob_get_clean();
            
            // Если блок вернул содержимое, используем его
            if (!empty($block_content)) {
                $content = $block_content;
            }
        } else {
            $content = "<center>Существует проблема с этим блоком!</center>";
        }
    }

    if (empty($content)) {
        $content = "<center>Существует проблема с этим блоком!</center>";
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
    global $blockfile, $b_id, $ss_uri;
    static $bl_mass = [];
    
    $func = 'echo';
    $func2 = '';
    
    // Определяем имя блока
    if ($pos == "s" || $pos == "o") {
        $bl_name = empty($blockfile) ? "fly-block-".$b_id : "fly-".str_replace(".php", "", $blockfile);
    } else {
        $bl_name = empty($blockfile) ? "block-".$b_id : str_replace(".php", "", $blockfile);
    }
    
    // Проверяем существование кастомного файла блока
    if (!isset($bl_mass[$bl_name])) {
        $bl_mass[$bl_name]['m'] = file_exists("themes/".$ss_uri."/html/".$bl_name.".html");
    }
    
    // Если есть кастомный файл
    if ($bl_mass[$bl_name]['m'] ?? false) {
        $file_content = file_get_contents("themes/".$ss_uri."/html/".$bl_name.".html");
        
        // Заменяем переменные в шаблоне
        $file_content = str_replace(
            ['$title', '$content', '$ss_uri', '{$title}', '{$content}', '{$ss_uri}'],
            [$title, $content, $ss_uri, $title, $content, $ss_uri],
            $file_content
        );
        
        if ($pos == "o") {
            return $file_content;
        } else {
            echo $file_content;
            return;
        }
    }
    
    // Если кастомного файла нет, используем стандартные блоки
    switch($pos) {
        case 'l': $std_name = "block-left"; break;
        case 'r': $std_name = "block-right"; break;
        case 'c': $std_name = "block-center"; break;
        case 'd': $std_name = "block-down"; break;
        case 's': $std_name = "block-fly"; break;
        case 'o': 
            $func = 'return ';
            $func2 = '';
            $std_name = "block-fly";
            break;
        default: $std_name = "block-all"; break;
    }
    
    // Инициализируем стандартный блок если нужно
    if (!isset($bl_mass[$std_name])) {
        $bl_mass[$std_name]['m'] = file_exists("themes/".$ss_uri."/html/".$std_name.".html");
        
        if ($bl_mass[$std_name]['m']) {
            $f_str = file_get_contents("themes/".$ss_uri."/html/".$std_name.".html");
            
            // Создаем анонимную функцию вместо устаревшей create_function
            $bl_mass[$std_name]['f'] = function($title, $content) use ($f_str, $ss_uri) {
                // Заменяем переменные в шаблоне
                $result = str_replace(
                    ['$title', '$content', '$ss_uri', '{$title}', '{$content}', '{$ss_uri}'],
                    [$title, $content, $ss_uri, $title, $content, $ss_uri],
                    $f_str
                );
                return $result;
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
    if (!isset($bl_mass['block-all'])) {
        $bl_mass['block-all']['m'] = file_exists("themes/".$ss_uri."/html/block-all.html");
        
        if ($bl_mass['block-all']['m']) {
            $f_str = file_get_contents("themes/".$ss_uri."/html/block-all.html");
            
            $bl_mass['block-all']['f'] = function($title, $content) use ($f_str, $ss_uri) {
                // Заменяем переменные в шаблоне
                $result = str_replace(
                    ['$title', '$content', '$ss_uri', '{$title}', '{$content}', '{$ss_uri}'],
                    [$title, $content, $ss_uri, $title, $content, $ss_uri],
                    $f_str
                );
                return $result;
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
    $fallback_html = "<fieldset><legend>".htmlspecialchars($title)."</legend>".$content."</fieldset>";
    
    if ($pos == "o") {
        return $fallback_html;
    } else {
        echo $fallback_html;
    }
}

function show_blocks($position) {
    global $CURUSER, $use_blocks, $already_used, $orbital_blocks;
    static $showed_show_hide = false;

    if (!$use_blocks) {
        return;
    }

    if (!$already_used) {
        $blocks_res = sql_query("SELECT * FROM orbital_blocks WHERE active = 1 ORDER BY weight ASC") or sqlerr(__FILE__, __LINE__);
        
        $orbital_blocks = [];
        while ($blocks_row = mysqli_fetch_array($blocks_res)) {
            $orbital_blocks[] = $blocks_row;
        }
        
        if (empty($orbital_blocks)) {
            $orbital_blocks = [];
        }
        
        $already_used = true;
    }

    if (empty($orbital_blocks)) {
        return;
    }

    foreach ($orbital_blocks as $block) {
        if (!$showed_show_hide) {
            echo '<script language="javascript" type="text/javascript" src="js/show_hide.js"></script>';
            $showed_show_hide = true;
        }
        
        $bid = $block["bid"] ?? 0;
        $content = $block["content"] ?? "";
        $title = $block["title"] ?? "";
        $blockfile = $block["blockfile"] ?? "";
        $bposition = $block["bposition"] ?? "";
        $allow_hide = ($block["allow_hide"] ?? "") == 'yes';
        
        if ($position != $bposition) {
            continue;
        }
        
        $view = $block["view"] ?? 0;
        $which = explode(",", $block["which"] ?? "");
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
            // Устанавливаем глобальные переменные для использования в themesidebox
            global $b_id;
            $b_id = $bid;
            
            render_blocks($blockfile, $title, $content, $bid, $bposition, $allow_hide);
        }
    }
}

?>