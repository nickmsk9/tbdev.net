<?php
function end_chmod(string $dir, string $chm): string
{
    if (!file_exists($dir)) {
        return "Ошибка: директория '{$dir}' не существует.";
    }
    
    if (!is_numeric($chm) || !preg_match('/^[0-7]{3}$/', $chm)) {
        return "Ошибка: некорректные права доступа '{$chm}'. Используйте формат из трех цифр (например, 755).";
    }
    
    // Получаем текущие права доступа в восьмеричном формате
    $current_perms = fileperms($dir);
    
    if ($current_perms === false) {
        return "Ошибка: не удалось получить права доступа для '{$dir}'.";
    }
    
    // Конвертируем в восьмеричную строку и получаем последние 3 цифры
    $current_perms_octal = substr(decoct($current_perms), -3);
    
    // Применяем CHMOD (закомментировано, как в оригинале)
    // chmod($dir, octdec($chm));
    
    if ($current_perms_octal !== $chm) {
        return "Директория '{$dir}' не имеет требуемых прав доступа.<br />"
             . "Текущие права: {$current_perms_octal}<br />"
             . "Требуемые права: {$chm}<br />"
             . "Исправьте права с помощью команды: <code>chmod {$chm} {$dir}</code>";
    }
    
    return "";
}
?>