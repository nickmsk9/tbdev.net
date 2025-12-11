<?php
if (!defined('BLOCK_FILE')) {
    Header("Location: ../index.php");
    exit;
}

global $CURUSER, $tracker_lang, $ss_uri, $mysql_link;

// Get current poll
$res = sql_query("SELECT * FROM polls ORDER BY added DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
$pollok = mysqli_num_rows($res);
$voted = false;
$pollid = 0;
$question = '';
$o = array();
$arr = array();

if ($pollok) {
    $arr = mysqli_fetch_assoc($res);
    $pollid = $arr["id"];
    $userid = isset($CURUSER["id"]) ? (int)$CURUSER["id"] : 0;
    $question = $arr["question"];
    $o = array(
        $arr["option0"], $arr["option1"], $arr["option2"], $arr["option3"], $arr["option4"],
        $arr["option5"], $arr["option6"], $arr["option7"], $arr["option8"], $arr["option9"],
        $arr["option10"], $arr["option11"], $arr["option12"], $arr["option13"], $arr["option14"],
        $arr["option15"], $arr["option16"], $arr["option17"], $arr["option18"], $arr["option19"]
    );

    // Check if user has already voted
    if ($userid > 0) {
        $res2 = sql_query("SELECT * FROM pollanswers WHERE pollid=$pollid AND userid=$userid") or sqlerr(__FILE__, __LINE__);
        $arr2 = mysqli_fetch_assoc($res2);
        $voted = !empty($arr2);
    }
}

$blocktitle = isset($tracker_lang['poll']) ? $tracker_lang['poll'] : 'Опрос';
if (get_user_class() >= UC_MODERATOR) {
    $blocktitle .= "<font class=\"small\"> - [<a class=\"altlink\" href=\"makepoll.php?returnto=main\"><b>" . (isset($tracker_lang['create']) ? $tracker_lang['create'] : 'Создать') . "</b></a>]";
    if ($pollok) {
        $blocktitle .= " - [<a class=\"altlink\" href=\"makepoll.php?action=edit&pollid=" . $pollid . "&returnto=main\"><b>Редактировать</b></a>] - [<a class=\"altlink\" href=\"polls.php?action=delete&pollid=" . $pollid . "&returnto=main\"><b>Удалить</b></a>]";
    }
    $blocktitle .= "</font>";
}

$content = '';

if ($pollok) {
    $content .= "<p align=\"center\"><b>" . htmlspecialchars($question) . "</b></p>\n";
    
    if ($voted && $userid > 0) {
        // display results
        if (isset($arr["selection"])) {
            $uservote = (int)$arr["selection"];
        } else {
            $uservote = -1;
        }
        
        // we reserve 255 for blank vote.
        $res = sql_query("SELECT selection FROM pollanswers WHERE pollid=$pollid AND selection < 20") or sqlerr(__FILE__, __LINE__);
        $tvotes = mysqli_num_rows($res);
        
        $vs = array(); // array of votes
        $os = array();

        // Count votes
        while ($arr2 = mysqli_fetch_row($res)) {
            $selection = (int)$arr2[0];
            if (!isset($vs[$selection])) {
                $vs[$selection] = 0;
            }
            $vs[$selection] += 1;
        }

        for ($i = 0; $i < count($o); ++$i) {
            if (!empty($o[$i])) {
                $voteCount = isset($vs[$i]) ? $vs[$i] : 0;
                $os[$i] = array($voteCount, $o[$i]);
            }
        }

        function srt($a, $b) {
            if ($a[0] > $b[0]) return -1;
            if ($a[0] < $b[0]) return 1;
            return 0;
        }

        // now os is an array like this: array(array(123, "Option 1"), array(45, "Option 2"))
        if (isset($arr["sort"]) && $arr["sort"] == "yes") {
            usort($os, 'srt');
        }

        $content .= "<table class=\"main\" align=\"center\" width=\"250\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        $i = 0;
        foreach ($os as $index => $a) {
            $optionText = $a[1];
            if ($index == $uservote) {
                $optionText .= "&nbsp;*";
            }
            if ($tvotes == 0) {
                $p = 0;
            } else {
                $p = round($a[0] / $tvotes * 100);
            }
            
            $c = ($i % 2 == 0) ? " bgcolor=\"#eeeeee\"" : "";
            $content .= "<tr><td width=\"1%\" class=\"embedded\"$c><nobr>" . htmlspecialchars($optionText) . "&nbsp;&nbsp;</nobr></td><td width=\"99%\" class=\"embedded\"$c><nobr>" .
                "<img src=\"./themes/$ss_uri/images/bar_left.gif\"><img src=\"./themes/$ss_uri/images/bar.gif\" height=\"12\" width=\"" . ($p * 3) .
                "\"><img src=\"./themes/$ss_uri/images/bar_right.gif\"> $p%</nobr></td></tr>\n";
            ++$i;
        }
        $content .= "</table>\n";
        $tvotes_formatted = number_format($tvotes);
        $content .= "<p align=\"center\">Голосов: $tvotes_formatted</p>\n";
    } else {
        $content .= "<form method=\"post\" action=\"index.php\">\n";
        $i = 0;
        foreach ($o as $option) {
            if (!empty($option)) {
                $content .= "<input type=\"radio\" name=\"choice\" value=\"$i\">" . htmlspecialchars($option) . "<br />\n";
                ++$i;
            }
        }
        $content .= "<br />";
        $content .= "<input type=\"radio\" name=\"choice\" value=\"255\">" . (isset($tracker_lang['blank_vote']) ? $tracker_lang['blank_vote'] : 'Воздержаться') . "<br />\n";
        $content .= "<p align=\"center\"><input type=\"submit\" value=\"" . (isset($tracker_lang['vote']) ? $tracker_lang['vote'] : 'Голосовать') . "!\" class=\"btn\"></p>";
        $content .= "<input type=\"hidden\" name=\"pollid\" value=\"$pollid\">";
        $content .= "</form>";
    }
    
    if ($voted) {
        $content .= "<div align=\"center\"><a href=\"polls.php\">" . (isset($tracker_lang['old_polls']) ? $tracker_lang['old_polls'] : 'Старые опросы') . "</a></div>\n";
    }
} else {
    $content .= "<table class=\"main\" align=\"center\" border=\"1\" cellspacing=\"0\" cellpadding=\"10\"><tr><td class=\"text\">";
    $content .= "<div align=\"center\"><h3>" . (isset($tracker_lang['no_polls']) ? $tracker_lang['no_polls'] : 'Нет активных опросов') . "</h3></div>\n";
    $content .= "</td></tr></table>";
}
?>