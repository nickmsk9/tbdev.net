<?php
declare(strict_types=1);

require_once __DIR__ . '/tscraper.php';

class udptscraper extends tscraper
{
    /**
     * UDP scrape
     *
     * @param string          $url      URL трекера: udp://tracker.tld:port или udp://tracker.tld:port/announce
     * @param string|string[] $infohash  Infohash (40 hex) или массив (макс. 74)
     * @return array<string, array<string,int|string>>
     * @throws ScraperException
     */
    public function scrape(string $url, string|array $infohash): array
    {
        $hashes = is_array($infohash) ? array_values($infohash) : [$infohash];

        if (!$hashes) {
            throw new ScraperException('Список infohash пуст.');
        }

        foreach ($hashes as $hash) {
            if (!preg_match('~^[a-f0-9]{40}$~i', (string)$hash)) {
                throw new ScraperException('Некорректный infohash: ' . $hash . '.');
            }
        }

        if (count($hashes) > 74) {
            throw new ScraperException('Слишком много infohash (максимум 74).');
        }

        // Парсим udp://host:port/...
        if (!preg_match('~^udp://([^:/]+)(?::(\d+))?(?:/.*)?$~i', trim($url), $m)) {
            throw new ScraperException('Некорректный URL трекера.');
        }

        $host = $m[1];
        $port = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 80;
        if ($port < 1 || $port > 65535) {
            throw new ScraperException('Некорректный порт трекера.');
        }

        // Открываем UDP сокет
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen('udp://' . $host, $port, $errno, $errstr);
        if (!$fp) {
            throw new ScraperException('Не удалось открыть UDP-соединение: ' . $errno . ' - ' . $errstr, 0, true);
        }

        stream_set_timeout($fp, $this->timeout);

        // Протокол UDP tracker: connect_id (8 bytes) фиксированный
        $connId = "\x00\x00\x04\x17\x27\x10\x19\x80";

        // ====== 1) CONNECT ======
        $transactionId = random_int(0, 0x7fffffff);

        // action=0 (connect)
        $packet = $connId . pack('N', 0) . pack('N', $transactionId);
        $this->writeAll($fp, $packet);

        // Ответ connect: 16 байт (action(4) + transId(4) + connId(8))
        $ret = $this->readExact($fp, 16, 'ответ на CONNECT');

        $head = unpack('Naction/Ntransid', substr($ret, 0, 8));
        if (!is_array($head) || ($head['action'] ?? -1) !== 0 || ($head['transid'] ?? -1) !== $transactionId) {
            fclose($fp);
            throw new ScraperException('Некорректный ответ CONNECT.');
        }

        $connId = substr($ret, 8, 8);

        // ====== 2) SCRAPE ======
        $transactionId = random_int(0, 0x7fffffff);

        // action=2 (scrape)
        $infohashBin = '';
        foreach ($hashes as $hash) {
            $infohashBin .= pack('H*', (string)$hash);
        }

        $packet = $connId . pack('N', 2) . pack('N', $transactionId) . $infohashBin;
        $this->writeAll($fp, $packet);

        // Ответ scrape: 8 + 12*n (action+transId + (seeders, completed, leechers)*n)
        $readLength = 8 + (12 * count($hashes));
        $ret = $this->readExact($fp, $readLength, 'ответ на SCRAPE');

        $head = unpack('Naction/Ntransid', substr($ret, 0, 8));
        if (!is_array($head) || ($head['transid'] ?? -1) !== $transactionId) {
            fclose($fp);
            throw new ScraperException('Некорректный ответ SCRAPE (transaction id не совпал).');
        }

        // action=3 — ошибка трекера (может идти строка ошибки)
        if (($head['action'] ?? -1) === 3) {
            fclose($fp);
            throw new ScraperException('Трекер вернул ошибку (action=3).', 0, true);
        }

        if (($head['action'] ?? -1) !== 2) {
            fclose($fp);
            throw new ScraperException('Некорректный ответ SCRAPE (action не равен 2).');
        }

        $torrents = [];
        $index = 8;

        foreach ($hashes as $hash) {
            $chunk = substr($ret, $index, 12);
            if (strlen($chunk) !== 12) {
                fclose($fp);
                throw new ScraperException('Ответ SCRAPE слишком короткий.');
            }

            // По UDP-протоколу: seeders, completed, leechers — 32-bit BE
            $d = unpack('Nseeders/Ncompleted/Nleechers', $chunk);
            $torrents[(string)$hash] = [
                'infohash'   => (string)$hash,
                'seeders'    => (int)($d['seeders'] ?? 0),
                'completed'  => (int)($d['completed'] ?? 0),
                'leechers'   => (int)($d['leechers'] ?? 0),
            ];

            $index += 12;
        }

        fclose($fp);
        return $torrents;
    }

    /**
     * Дочитать ровно $length байт из сокета или упасть с понятной ошибкой
     */
    private function readExact($fp, int $length, string $stage): string
    {
        $data = '';
        $remaining = $length;

        while ($remaining > 0 && !feof($fp)) {
            $chunk = fread($fp, $remaining);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        if (strlen($data) < 1) {
            throw new ScraperException('Нет данных: ' . $stage . '.', 0, true);
        }
        if (strlen($data) < $length) {
            throw new ScraperException('Слишком короткий ' . $stage . '.', 0, true);
        }

        return $data;
    }

    /**
     * Надёжная запись в сокет (на случай частичной записи)
     */
    private function writeAll($fp, string $data): void
    {
        $len = strlen($data);
        $written = 0;

        while ($written < $len) {
            $w = fwrite($fp, substr($data, $written));
            if ($w === false || $w === 0) {
                throw new ScraperException('Не удалось отправить пакет по UDP.', 0, true);
            }
            $written += $w;
        }
    }
}
