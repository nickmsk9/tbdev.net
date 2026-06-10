<?php
declare(strict_types=1);

require_once __DIR__ . '/include/bittorrent.php';
require_once __DIR__ . '/include/captcha.php';

error_reporting(error_reporting() & ~E_DEPRECATED);

$captchaId = (string)($_GET['imagehash'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $captchaId)) {
    http_response_code(400);
    exit;
}

captcha_instance($captchaId)->outputAudioFile();
