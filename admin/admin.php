<?php
loggedinorreturn();

if (get_user_class() < UC_ADMINISTRATOR) {
    stderr($tracker_lang['error'] ?? 'Ошибка', "У вас нет прав доступа!");
}

require_once("admin/core.php");

function BuildMenu($url, $title, $image = ''): void
{
    static $counter = 0;
    
    $image_link = "admin/pic/$image";
    echo "<td align=\"center\" valign=\"top\" width=\"15%\" style=\"border: none;\">"
        . "<a href=\"" . htmlspecialchars($url) . "\" title=\"" . htmlspecialchars($title) . "\">"
        . ($image != '' ? "<img src=\"" . htmlspecialchars($image_link) . "\" border=\"0\" alt=\"" . htmlspecialchars($title) . "\" title=\"" . htmlspecialchars($title) . "\">" : "")
        . "<br><b>" . htmlspecialchars($title) . "</b></a></td>";
    
    if ($counter == 5) {
        echo "</tr><tr>";
        $counter = 0;
    } else {
        $counter++;
    }
}

$op = $_GET['op'] ?? 'Main';

switch ($op) {
    case "Main":
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"3\">"
            . "<tr><td class=\"colhead\" colspan=\"6\">Панель управления</td></tr><tr>";
        
        // Используем glob для более безопасного и читаемого перебора файлов
        $links_files = glob("admin/links/*.php");
        if ($links_files) {
            foreach ($links_files as $file) {
                $filename = basename($file);
                if ($filename !== '.' && $filename !== '..') {
                    require_once($file);
                }
            }
        }
        
        echo "<tr><td align=\"center\" class=\"colhead\" width=\"100%\" colspan=\"6\">&nbsp;</td></tr>"
            . "</table>";
        break;

    default:
        $modules_files = glob("admin/modules/*.php");
        if ($modules_files) {
            foreach ($modules_files as $file) {
                $filename = basename($file);
                if ($filename !== '.' && $filename !== '..') {
                    require_once($file);
                }
            }
        }
        break;
}
?>