<?php

if (!defined('IN_TRACKER')) {
    die('Hacking attempt!');
}

require_once __DIR__ . '/../vendor/autoload.php';

function create_captcha(): string
{
    return bin2hex(random_bytes(16));
}

function captcha_instance(string $captchaId): Securimage
{
    return new Securimage([
        'captchaId' => $captchaId,
        'image_width' => 220,
        'image_height' => 70,
        'code_length' => 5,
        'case_sensitive' => false,
        'num_lines' => 5,
        'noise_level' => 3,
        'perturbation' => 0.75,
    ]);
}

function captcha_validate(string $captchaId, string $value): bool
{
    if (!preg_match('/^[a-f0-9]{32}$/', $captchaId) || trim($value) === '') {
        return false;
    }

    return captcha_instance($captchaId)->check(trim($value), $captchaId, true);
}
