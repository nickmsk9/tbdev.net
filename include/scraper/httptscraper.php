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

        $announceUrl = trim($url);
        $url = $this->scrapeUrl($announceUrl);
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
                return $this->announceFallback($announceUrl, $hashes);
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

    private function announceFallback(string $url, array $hashes): array
    {
        if (!preg_match('~^https?://~i', $url)) {
            throw new ScraperException('Invalid announce URL.');
        }

        $torrents = [];
        foreach ($hashes as $hash) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $peerId = '-TBDEV0-' . bin2hex(random_bytes(6));
            $requestUrl = $url . $separator .
                'info_hash=' . rawurlencode(pack('H*', (string)$hash)) .
                '&peer_id=' . rawurlencode($peerId) .
                '&port=1&uploaded=0&downloaded=0&left=0&compact=1&numwant=0&event=stopped';

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

            $response = @file_get_contents($requestUrl, false, $context);
            $headers = $http_response_header ?? [];
            if ($response === false || $response === '') {
                throw new ScraperException('Unable to receive announce response.', 0, true);
            }
            if ($headers && preg_match('~\s(\d{3})\s~', (string)$headers[0], $match) && (int)$match[1] >= 400) {
                throw new ScraperException('HTTP tracker returned status ' . (int)$match[1] . '.', 0, true);
            }

            $data = lightbenc::bdecode($response);
            if (is_array($data) && !empty($data['failure reason'])) {
                throw new ScraperException('Tracker: ' . (string)$data['failure reason']);
            }
            if (!is_array($data) || (!array_key_exists('complete', $data) && !array_key_exists('incomplete', $data))) {
                throw new ScraperException('Announce response does not contain swarm statistics.');
            }

            $torrents[(string)$hash] = [
                'infohash' => (string)$hash,
                'seeders' => max(0, (int)($data['complete'] ?? 0)),
                'completed' => max(0, (int)($data['downloaded'] ?? 0)),
                'leechers' => max(0, (int)($data['incomplete'] ?? 0)),
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
