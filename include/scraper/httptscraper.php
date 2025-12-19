<?php
declare(strict_types=1);

require_once __DIR__ . '/tscraper.php';
require_once __DIR__ . '/lightbenc.php';

class httptscraper extends tscraper
{
    /** Максимальный объём чтения ответа трекера (в байтах) */
    protected int $maxreadsize;

    /**
     * @param int $timeout     Таймаут соединения (сек.)
     * @param int $maxreadsize Максимальный размер ответа
     */
    public function __construct(int $timeout = 2, int $maxreadsize = 4096)
    {
        $this->maxreadsize = max(512, $maxreadsize);
        parent::__construct($timeout);
    }

    /**
     * Скрапинг данных с трекера
     *
     * @param string          $url      URL трекера вида:
     *                                  http(s)://tracker.tld:port/announce
     *                                  или http(s)://tracker.tld:port/scrape
     * @param string|string[] $infohash  Infohash (40 hex символов) или массив
     *
     * @return array Массив с данными по каждому infohash
     * @throws ScraperException
     */
    public function scrape(string $url, string|array $infohash): array
    {
        $hashes = is_array($infohash) ? array_values($infohash) : [$infohash];

        if (!$hashes) {
            throw new ScraperException('Список infohash пуст.');
        }

        // Проверка корректности infohash
        foreach ($hashes as $hash) {
            if (!preg_match('~^[a-f0-9]{40}$~i', (string)$hash)) {
                throw new ScraperException('Некорректный infohash: ' . $hash);
            }
        }

        $url = trim($url);

        // Преобразование announce -> scrape (поддержка http и https)
        if (preg_match('~^(https?://.*?/)(announce)([^/]*)$~i', $url, $m)) {
            $url = $m[1] . 'scrape' . $m[3];
        } elseif (preg_match('~^(https?://.*?/)(scrape)([^/]*)$~i', $url)) {
            // Уже scrape
        } else {
            throw new ScraperException('Некорректный URL трекера.');
        }

        // Формирование URL запроса
        $sep = str_contains($url, '?') ? '&' : '?';
        $requesturl = $url;

        foreach ($hashes as $hash) {
            // hex → бинарные 20 байт → urlencode
            $requesturl .= $sep . 'info_hash=' . rawurlencode(pack('H*', (string)$hash));
            $sep = '&';
        }

        // Таймаут для потоков
        @ini_set('default_socket_timeout', (string)$this->timeout);

        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => $this->timeout,
                'user_agent'    => 'TBDev-MultiTrackerScraper/1.0',
                'header'        => "Accept: */*\r\nConnection: close\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $rh = @fopen($requesturl, 'rb', false, $context);
        if (!$rh) {
            throw new ScraperException('Не удалось установить HTTP-соединение с трекером.', 0, true);
        }

        stream_set_timeout($rh, $this->timeout);

        $response = '';
        $read = 0;

        // Чтение ответа с ограничением по размеру
        while (!feof($rh) && $read < $this->maxreadsize) {
            $chunk = fread($rh, min(1024, $this->maxreadsize - $read));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $response .= $chunk;
            $read += strlen($chunk);
        }

        fclose($rh);

        // Bencode-ответ всегда начинается с "d"
        if ($response === '' || substr($response, 0, 1) !== 'd') {
            throw new ScraperException('Некорректный ответ scrape-запроса.');
        }

        $scrapeData = lightbenc::bdecode($response);

        if (
            !is_array($scrapeData) ||
            !isset($scrapeData['files']) ||
            !is_array($scrapeData['files'])
        ) {
            throw new ScraperException('Неверная структура данных scrape-ответа.');
        }

        $torrents = [];

        // Обработка каждого infohash
        foreach ($hashes as $hash) {
            $ehash = pack('H*', (string)$hash);

            if (isset($scrapeData['files'][$ehash]) && is_array($scrapeData['files'][$ehash])) {
                $file = $scrapeData['files'][$ehash];

                $torrents[$hash] = [
                    'infohash'  => (string)$hash,
                    'seeders'   => (int)($file['complete'] ?? 0),
                    'completed' => (int)($file['downloaded'] ?? 0),
                    'leechers'  => (int)($file['incomplete'] ?? 0),
                ];
            } else {
                // Трекер не вернул данные по данному infohash
                $torrents[$hash] = false;
            }
        }

        return $torrents;
    }
}
