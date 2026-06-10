<?php
declare(strict_types=1);

require_once __DIR__ . '/tscraper.php';
require_once __DIR__ . '/lightbenc.php';

class httptscraper extends tscraper
{
    protected int $maxreadsize;

    public function __construct(int $timeout = 2, int $maxreadsize = 4096)
    {
        $this->maxreadsize = max(512, $maxreadsize);
        parent::__construct($timeout);
    }

    public function scrape(string $url, string|array $infohash): array
    {
        $hashes = is_array($infohash) ? array_values($infohash) : [$infohash];
        if (!$hashes) {
            throw new ScraperException('Список info_hash пуст.');
        }

        foreach ($hashes as $hash) {
            if (!preg_match('~^[a-f0-9]{40}$~i', (string)$hash)) {
                throw new ScraperException('Некорректный info_hash: ' . $hash);
            }
        }

        $url = $this->scrapeUrl(trim($url));
        $separator = str_contains($url, '?') ? '&' : '?';
        $requestUrl = $url;

        foreach ($hashes as $hash) {
            $requestUrl .= $separator . 'info_hash=' . rawurlencode(pack('H*', (string)$hash));
            $separator = '&';
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'user_agent' => 'TBDev-MultiTrackerScraper/2.0',
                'header' => "Accept: */*\r\nConnection: close\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $stream = @fopen($requestUrl, 'rb', false, $context);
        if (!$stream) {
            throw new ScraperException('Не удалось подключиться к HTTP-трекеру.', 0, true);
        }

        $responseHeaders = $http_response_header ?? [];
        if ($responseHeaders && preg_match('~\s(\d{3})\s~', (string)$responseHeaders[0], $match)) {
            $status = (int)$match[1];
            if ($status >= 400) {
                fclose($stream);
                throw new ScraperException('HTTP-трекер вернул статус ' . $status . '.', 0, true);
            }
        }

        stream_set_timeout($stream, $this->timeout);
        $response = '';
        while (!feof($stream) && strlen($response) < $this->maxreadsize) {
            $chunk = fread($stream, min(4096, $this->maxreadsize - strlen($response)));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $response .= $chunk;
        }
        $metadata = stream_get_meta_data($stream);
        fclose($stream);

        if (!empty($metadata['timed_out'])) {
            throw new ScraperException('HTTP-трекер не ответил за отведенное время.', 0, true);
        }
        if ($response === '' || $response[0] !== 'd') {
            throw new ScraperException('Некорректный ответ scrape-запроса.');
        }

        $scrapeData = lightbenc::bdecode($response);
        if (is_array($scrapeData) && !empty($scrapeData['failure reason'])) {
            throw new ScraperException('Трекер: ' . (string)$scrapeData['failure reason']);
        }
        if (!is_array($scrapeData) || !isset($scrapeData['files']) || !is_array($scrapeData['files'])) {
            throw new ScraperException('Неверная структура scrape-ответа.');
        }

        $torrents = [];
        foreach ($hashes as $hash) {
            $binaryHash = pack('H*', (string)$hash);
            $file = $scrapeData['files'][$binaryHash] ?? null;
            if (!is_array($file)) {
                $torrents[(string)$hash] = false;
                continue;
            }

            $torrents[(string)$hash] = [
                'infohash' => (string)$hash,
                'seeders' => max(0, (int)($file['complete'] ?? 0)),
                'completed' => max(0, (int)($file['downloaded'] ?? 0)),
                'leechers' => max(0, (int)($file['incomplete'] ?? 0)),
            ];
        }

        return $torrents;
    }

    private function scrapeUrl(string $url): string
    {
        if (!preg_match('~^https?://~i', $url)) {
            throw new ScraperException('Некорректный URL HTTP-трекера.');
        }
        if (preg_match('~/(?:scrape)(?=[/?]|$)~i', $url)) {
            return $url;
        }

        $converted = preg_replace('~/(?:announce)(?=[/?]|$)~i', '/scrape', $url, 1, $count);
        if (!$count) {
            $converted = preg_replace('~/ann(?=[/?]|$)~i', '/scrape', $url, 1, $count);
        }
        if (!$count || !is_string($converted)) {
            throw new ScraperException('Трекер не публикует стандартный scrape URL.');
        }

        return $converted;
    }
}
