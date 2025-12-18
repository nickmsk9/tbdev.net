<?php
if (!defined('BLOCK_FILE')) {
    header("Location: ../index.php");
    exit;
}

global $tracker_lang;

$content   = $content ?? '';
$news_flag = 0;

$userClass = (int)get_user_class();
$isAdmin   = ($userClass >= UC_ADMINISTRATOR);

$blocktitle = ($tracker_lang['news'] ?? 'Новости')
    . ($isAdmin
        ? '<font class="small"> - [<a class="altlink" href="news.php"><b>' . ($tracker_lang['create'] ?? 'Создать') . '</b></a>]</font>'
        : ''
    );

// show_hide.js один раз
if (!defined('NEWS_BLOCK_SH_JS')) {
    define('NEWS_BLOCK_SH_JS', 1);
    $content .= '<script type="text/javascript" src="js/show_hide.js"></script>';
}

// Минимальный CSS (один раз на страницу)
if (!defined('NEWS_BLOCK_MINI_CSS')) {
    define('NEWS_BLOCK_MINI_CSS', 1);
    $content .= <<<HTML
<style>
/* Минимальный лёгкий стиль только для блока новостей */
.nw-list{margin:0;padding:0;list-style:none;}
.nw-item{padding:8px 0;border-bottom:1px solid rgba(0,0,0,.08);}
.nw-item:last-child{border-bottom:0;}

.nw-head{display:flex;align-items:center;gap:8px;cursor:pointer;user-select:none;}
.nw-toggle{width:16px;height:16px;flex:0 0 16px;display:inline-flex;align-items:center;justify-content:center}
.nw-title{line-height:1.25}
.nw-date{font-size:11px;opacity:.75;margin-right:6px;white-space:nowrap}
.nw-subj{font-weight:700}

.nw-actions{margin-left:auto;white-space:nowrap;opacity:.8}
.nw-actions a{opacity:.9;text-decoration:none}
.nw-actions a:hover{opacity:1;text-decoration:underline}

.nw-body{margin:6px 0 0 24px;padding:8px 10px;border-left:2px solid rgba(0,0,0,.10);background:rgba(0,0,0,.03);border-radius:6px}
.nw-body .comment{margin:0} /* если format_comment даёт class=comment */
</style>
HTML;
}

$sql = "
    SELECT id, added, subject, body, userid
    FROM news
    WHERE added >= DATE_SUB(NOW(), INTERVAL 45 DAY)
    ORDER BY added DESC
    LIMIT 10
";
$res = sql_query($sql) or sqlerr(__FILE__, __LINE__);

if ($res && mysqli_num_rows($res) > 0) {
    $returnto = urlencode($_SERVER['PHP_SELF'] ?? '');

    // Главный блок (оставляем как был)
    $content .= '<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text">' . "\n";
    $content .= '<ul class="nw-list">';

    while ($row = mysqli_fetch_assoc($res)) {
        $id      = (int)$row['id'];
        $subject = htmlspecialchars_uni((string)$row['subject']);
        $body    = format_comment((string)$row['body']);

        $addedTs = sql_timestamp_to_unix_timestamp((string)$row['added']);
        $dateStr = date('d.m.Y', $addedTs);

        $isOpen  = ($news_flag === 0);
        $icon    = $isOpen ? 'minus' : 'plus';
        $display = $isOpen ? 'block' : 'none';

        // show_hide.js: show_hide('sID') -> переключает ssID + picsID
        $toggleJs = "show_hide('s{$id}')";

        $editLinks = '';
        if ($isAdmin) {
            $editLinks =
                '<span class="nw-actions">'
                . '<font size="-2">[<a class="altlink" href="news.php?action=edit&newsid=' . $id . '&returnto=' . $returnto . '"><b>E</b></a>]</font> '
                . '<font size="-2">[<a class="altlink" href="news.php?action=delete&newsid=' . $id . '&returnto=' . $returnto . '"><b>D</b></a>]</font>'
                . '</span>';
        }

        // Разметка: заголовок как “строка”, тело как мягкий блок слева с полоской
        $content .= '<li class="nw-item">';

        $content .= '<div class="nw-head" onclick="javascript:' . $toggleJs . '">';
        $content .= '<span class="nw-toggle"><img border="0" src="pic/' . $icon . '.gif" id="pics' . $id . '" alt="" /></span>';
        $content .= '<span class="nw-title"><span class="nw-date">' . $dateStr . '</span><span class="nw-subj">' . $subject . '</span></span>';
        $content .= $editLinks;
        $content .= '</div>';

        $content .= '<div id="ss' . $id . '" class="nw-body" style="display:' . $display . ';">' . $body . '</div>';

        $content .= '</li>';

        $news_flag = 1;
    }

    $content .= '</ul></td></tr></table>' . "\n";
} else {
    $content .= '<table class="main" align="center" border="1" cellspacing="0" cellpadding="10"><tr><td class="text">';
    $content .= '<div align="center"><h3>' . ($tracker_lang['no_news'] ?? 'Новостей нет') . '</h3></div>' . "\n";
    $content .= '</td></tr></table>';
}
?>
