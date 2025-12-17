<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------------------------------------
// Begins a main frame

function begin_main_frame(): void
{
    print(
        "<table class=\"main\" width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">" .
        "<tr><td class=\"embedded\">\n"
    );
}

// Ends a main frame
function end_main_frame(): void
{
    print("</td></tr></table>\n");
}

// ---------------------------------------------------------------------------------------------------------

function begin_table(bool $fullwidth = false, int $padding = 5): void
{
    $width = $fullwidth ? ' width="100%"' : '';
    print("<table class=\"main\"{$width} border=\"1\" cellspacing=\"0\" cellpadding=\"{$padding}\">\n");
}

// ВАЖНО: end_table() у тебя закрывает </td></tr> — значит begin_table() должен открываться внутри <tr><td>
// Я НЕ меняю структуру, чтобы не ломать внешний вид.
function end_table(): void
{
    print("</td></tr></table>\n");
}

// ---------------------------------------------------------------------------------------------------------

function begin_frame(string $caption = "", bool $center = false, int $padding = 10): void
{
    $tdextra = "";

    if ($caption !== '') {
        print("<h2>{$caption}</h2>\n");
    }

    if ($center) {
        $tdextra .= " align=\"center\"";
    }

    print("<table width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"{$padding}\"><tr><td{$tdextra}>\n");
}

function attach_frame(int $padding = 10): void
{
    // $padding оставил для совместимости с вызовами, но он тут не используется — как и было.
    print("</td></tr><tr><td style=\"border-top: 0px\">\n");
}

function end_frame(): void
{
    print("</td></tr></table>\n");
}

// ---------------------------------------------------------------------------------------------------------
// Inserts a smilies frame (move to globals)

function insert_smilies_frame(): void
{
    global $smilies, $DEFAULTBASEURL;

    begin_frame("Смайлики", true);

    begin_table(false, 5);

    print("<tr><td class=\"colhead\">Код</td><td class=\"colhead\">Смайл</td></tr>\n");

    // each() удалён в PHP 8 — используем foreach
    if (is_array($smilies)) {
        foreach ($smilies as $code => $url) {
            $code = (string)$code;
            $url  = (string)$url;
            print("<tr><td>{$code}</td><td><img src=\"{$DEFAULTBASEURL}/pic/smilies/{$url}\" alt=\"\"></td></tr>\n");
        }
    }

    end_table();

    end_frame();
}

// ---------------------------------------------------------------------------------------------------------
// Block menu function: Print out menu block!

function blok_menu(string $title, string $content, string $width = "155"): void
{
    global $ss_uri;

    $path = 'themes/' . $ss_uri . '/html/block-left.html';
    $tpl  = @file_get_contents($path);

    if ($tpl === false) {
        echo '';
        return;
    }

    // Подставляем ровно те переменные, которые реально есть в шаблоне
    $tpl = str_replace(
        ['$title', '$content', '$ss_uri', '$width'],
        [$title,  $content,  $ss_uri,  $width],
        $tpl
    );

    echo $tpl;
}



?>
