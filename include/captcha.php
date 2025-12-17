<?

# IMPORTANT: Do not edit below unless you know what you are doing!
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

function create_captcha(): string {
    global $_COOKIE_SALT;
    
    // Генерация случайной строки для капчи
    $randomstr = (string)rand(10000, 99999);
    $imagehash = md5($randomstr . ($_COOKIE_SALT ?? ''));
    
    // Используем безопасное подключение к базе данных
    sql_query("INSERT INTO captcha SET imagehash = ".sqlesc($imagehash).", 
              imagestring = ".sqlesc($randomstr).", 
              dateline = ".sqlesc(time()));
    
    return $imagehash;
}

function my_strlen(string $string): int {
    // Очистка строки от HTML-сущностей
    $string = preg_replace("#&\#(0-9]+);#", "-", $string);
    
    if(function_exists("mb_strlen")) {
        $string_length = mb_strlen($string);
    } else {
        $string_length = strlen($string);
    }

    return $string_length;
}

function get_extension(string $file): string {
    return strtolower(pathinfo($file, PATHINFO_EXTENSION));
}

function my_substr(string $string, int $start, ?int $length = null): string {
    if(function_exists("mb_substr")) {
        if($length !== null) {
            $cut_string = mb_substr($string, $start, $length);
        } else {
            $cut_string = mb_substr($string, $start);
        }
    } else {
        if($length !== null) {
            $cut_string = substr($string, $start, $length);
        } else {
            $cut_string = substr($string, $start);
        }
    }

    return $cut_string ?: '';
}

?>