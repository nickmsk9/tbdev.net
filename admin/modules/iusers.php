<?php
declare(strict_types=1);

if (!defined("ADMIN_FILE")) die("Illegal File Access");

function iUsers(string $iname = '', string $ipass = '', string $imail = ''): void {
    global $admin_file, $CURUSER;
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Проверяем существование пользователя и его класс
        $res = sql_query('SELECT class FROM users WHERE username = ' . sqlesc($iname));
        if (!$res || mysqli_num_rows($res) == 0) {
            stdmsg("Ошибка", "Пользователь не найден!", "error");
            return;
        }
        
        $row = mysqli_fetch_row($res);
        $iclass = $row[0] ?? 0;
        
        // Проверяем права администратора
        if (get_user_class() <= (int)$iclass) {
            stdmsg("Ошибка", "Вы не можете редактировать этого пользователя! Вы должны иметь класс выше редактируемого пользователя. Измените свой класс в настройках.", "error");
            write_log('Администратор '.$CURUSER['username'].' попытался изменить данные пользователя '.$iname.' выше своего класса!', 'red', 'error');
            return;
        }
        
        $updateset = array();
        if (!empty($ipass)) {
            // Генерация нового пароля
            $secret = mksecret();
            $hash = md5($secret . $ipass . $secret);
            $updateset[] = "secret = " . sqlesc($secret);
            $updateset[] = "passhash = " . sqlesc($hash);
        }
        
        if (!empty($imail) && validemail($imail)) {
            $updateset[] = "email = " . sqlesc($imail);
        }
        
        if (count($updateset) > 0) {
            $query = "UPDATE users SET " . implode(", ", $updateset) . " WHERE username = " . sqlesc($iname);
            $res = sql_query($query);
            
            if (!$res) {
                stdmsg("Ошибка", "Не удалось обновить данные пользователя! Произошла ошибка при выполнении запроса.", "error");
                return;
            }
            
            if (mysqli_affected_rows($GLOBALS['mysql_link']) < 1) {
                stdmsg("Ошибка", "Не удалось обновить данные пользователя! Возможно данные не изменились или пользователь не найден.", "error");
            } else {
                $message = "Данные пользователя успешно обновлены<br />";
                $message .= "Логин пользователя: " . htmlspecialchars($iname) . "<br />";
                
                if (!empty($ipass)) {
                    $message .= "Новый пароль: " . htmlspecialchars($ipass) . "<br />";
                }
                
                if (!empty($imail)) {
                    $message .= "Новый email: " . htmlspecialchars($imail);
                }
                
                stdmsg("Успешное обновление данных пользователя", $message);
                
                // Логируем действие
                write_log('Администратор ' . $CURUSER['username'] . ' изменил данные пользователя ' . $iname, 'green', 'admin');
            }
        } else {
            stdmsg("Предупреждение", "Не указаны данные для изменения!", "warning");
        }
    } else {
        // Показываем форму
        echo "<form method=\"post\" action=\"" . htmlspecialchars($admin_file) . ".php?op=iUsers\">"
            . "<table border=\"0\" cellspacing=\"0\" cellpadding=\"3\">"
            . "<tr><td class=\"colhead\" colspan=\"2\">Изменение пароля</td></tr>"
            . "<tr>"
            . "<td><b>Пользователь</b></td>"
            . "<td><input name=\"iname\" type=\"text\" required></td>"
            . "</tr>"
            . "<tr>"
            . "<td><b>Новый пароль</b></td>"
            . "<td><input name=\"ipass\" type=\"password\" autocomplete=\"new-password\"></td>"
            . "</tr>"
            . "<tr>"
            . "<td><b>Новый email</b></td>"
            . "<td><input name=\"imail\" type=\"email\"></td>"
            . "</tr>"
            . "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"isub\" value=\"Изменить\"></td></tr>"
            . "</table>"
            . "<input type=\"hidden\" name=\"op\" value=\"iUsers\" />"
            . "</form>";
    }
}

// Обработка параметров
$iname = $_POST['iname'] ?? $_GET['iname'] ?? '';
$ipass = $_POST['ipass'] ?? '';
$imail = $_POST['imail'] ?? '';
$op = $_GET['op'] ?? $_POST['op'] ?? '';

// Безопасная обработка входных данных
$iname = trim($iname);
$ipass = trim($ipass);
$imail = trim($imail);
$op = trim($op);

// Обработка операции
switch ($op) {
    case "iUsers":
        iUsers($iname, $ipass, $imail);
        break;
        
    default:
        // Если операция не указана, показываем форму
        iUsers();
        break;
}

?>