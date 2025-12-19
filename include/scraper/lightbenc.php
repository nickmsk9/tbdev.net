<?php
declare(strict_types=1);

final class lightbenc
{
    public static function bdecode(string $s, int &$pos = 0): mixed
    {
        $len = strlen($s);
        if ($pos < 0 || $pos >= $len) {
            return null;
        }

        $c = $s[$pos];

        switch ($c) {
            case 'd': { // словарь
                $pos++;
                $retval = [];

                while ($pos < $len && $s[$pos] !== 'e') {
                    $key = self::bdecode($s, $pos);
                    $val = self::bdecode($s, $pos);

                    // ключ и значение обязаны быть валидными
                    if ($key === null || $val === null) {
                        return null;
                    }

                    // ключи в bencode — строки
                    $retval[(string)$key] = $val;
                }

                if ($pos >= $len || $s[$pos] !== 'e') {
                    return null; // нет закрывающего 'e'
                }

                // служебная метка
                $retval['isDct'] = true;

                $pos++; // пропускаем 'e'
                return $retval;
            }

            case 'l': { // список
                $pos++;
                $retval = [];

                while ($pos < $len && $s[$pos] !== 'e') {
                    $val = self::bdecode($s, $pos);
                    if ($val === null) {
                        return null;
                    }
                    $retval[] = $val;
                }

                if ($pos >= $len || $s[$pos] !== 'e') {
                    return null;
                }

                $pos++;
                return $retval;
            }

            case 'i': { // целое число
                $pos++;

                $end = strpos($s, 'e', $pos);
                if ($end === false) {
                    return null;
                }

                $numStr = substr($s, $pos, $end - $pos);
                // пустое / мусор — ошибка
                if ($numStr === '' || !preg_match('~^-?\d+$~', $numStr)) {
                    return null;
                }

                $pos = $end + 1; // за 'e'
                return (int)$numStr;
            }

            default: { // строка: <len>:<data>
                $colon = strpos($s, ':', $pos);
                if ($colon === false) {
                    return null;
                }

                $digits = $colon - $pos;
                // защита от мусора/переполнения: длина числа ограничена
                if ($digits <= 0 || $digits > 20) {
                    return null;
                }

                $lenStr = substr($s, $pos, $digits);
                if ($lenStr === '' || !ctype_digit($lenStr)) {
                    return null;
                }

                $strLen = (int)$lenStr;
                $pos = $colon + 1;

                if ($strLen < 0 || ($pos + $strLen) > $len) {
                    return null;
                }

                $str = substr($s, $pos, $strLen);
                $pos += $strLen;

                return (string)$str;
            }
        }
    }

    /**
     * BEncode (PHP -> bencode)
     *
     * @param mixed $d
     * @return string|null
     */
    public static function bencode(mixed $d): ?string
    {
        if (is_array($d)) {
            $isDict = (isset($d['isDct']) && is_bool($d['isDct']) && $d['isDct'] === true);

            $ret = $isDict ? 'd' : 'l';

            if ($isDict) {
                // По спецификации словари должны быть отсортированы по ключам (битторнадо реально может "подавиться")
                ksort($d, SORT_STRING);
            }

            foreach ($d as $key => $value) {
                if ($isDict) {
                    // пропускаем служебный ключ, только если он действительно наш
                    if ($key === 'isDct' && is_bool($value)) {
                        continue;
                    }
                    $k = (string)$key;
                    $ret .= strlen($k) . ':' . $k;
                }

                if (is_string($value)) {
                    $ret .= strlen($value) . ':' . $value;
                } elseif (is_int($value)) {
                    $ret .= 'i' . $value . 'e';
                } else {
                    $encoded = self::bencode($value);
                    if ($encoded === null) {
                        return null;
                    }
                    $ret .= $encoded;
                }
            }

            return $ret . 'e';
        }

        // fallback: одиночная строка/число
        if (is_string($d)) {
            return strlen($d) . ':' . $d;
        }
        if (is_int($d)) {
            return 'i' . $d . 'e';
        }

        return null;
    }

    /**
     * Декодирование bencode из файла
     *
     * @param string $filename
     * @return mixed|null
     */
    public static function bdecode_file(string $filename): mixed
    {
        $data = @file_get_contents($filename);
        if ($data === false) {
            return null;
        }

        $pos = 0;
        return self::bdecode($data, $pos);
    }
}
