#!/usr/local/bin/php
<?php

/**
 * Monospot dil dosyaları köprüsü (app/lang/<kod>.inc).
 *
 *   lang_admin.php codes
 *   lang_admin.php get:<kod>
 *   lang_admin.php set:<kod>:<base64-json>
 *
 * Eksik dosya için .sample.inc okunur; yazma daima <kod>.inc dosyasına yapılır
 * ve öncesinde yedek alınır.
 */

const LANG_DIR = '/usr/local/opnsense/scripts/monospot/app/lang';

function fail($message)
{
    printf("%s\n", json_encode(['status' => 'failed', 'error' => $message], JSON_UNESCAPED_UNICODE));
    exit(0);
}

function payload($argument)
{
    $decoded = json_decode((string)base64_decode((string)$argument, true), true);
    return is_array($decoded) ? $decoded : [];
}

function code_check($code)
{
    if (!preg_match('/^[a-z]{2}(_[A-Za-z0-9]+)?$/', (string)$code)) {
        fail('geçersiz dil kodu: ' . (string)$code);
    }
    return (string)$code;
}

function read_lang($code)
{
    foreach ([LANG_DIR . '/' . $code . '.inc', LANG_DIR . '/' . $code . '.sample.inc'] as $file) {
        if (is_file($file)) {
            $data = include $file;
            if (is_array($data)) {
                return $data;
            }
        }
    }
    return [];
}

function codes()
{
    $found = [];
    foreach (glob(LANG_DIR . '/*.inc') as $file) {
        if (preg_match('/\/([a-z]{2}(?:_[A-Za-z0-9]+)?)\.inc$/', $file, $match)) {
            $found[$match[1]] = true;
        }
    }
    foreach (glob(LANG_DIR . '/*.sample.inc') as $file) {
        if (preg_match('/\/([a-z]{2}(?:_[A-Za-z0-9]+)?)\.sample\.inc$/', $file, $match)) {
            $found[$match[1]] = $found[$match[1]] ?? false; /* sample = henüz yazılmamış */
        }
    }
    $rows = [];
    foreach ($found as $code => $written) {
        $rows[] = ['code' => $code, 'written' => $written ? 1 : 0];
    }
    return $rows;
}

$first = isset($argv[1]) ? (string)$argv[1] : '';
$parts = explode(':', $first, 3);
$command = $parts[0];
$code = isset($parts[1]) ? code_check($parts[1]) : '';
$argument = isset($parts[2]) ? $parts[2] : '';

switch ($command) {
    case 'codes':
        printf("%s\n", json_encode(['status' => 'ok', 'codes' => codes()], JSON_UNESCAPED_UNICODE));
        break;

    case 'get':
        if ($code === '') {
            fail('dil kodu gerekli');
        }
        $strings = read_lang($code);
        $keys = [];
        foreach ($strings as $key => $value) {
            $keys[(string)$key] = is_scalar($value) ? (string)$value : '';
        }
        printf("%s\n", json_encode([
            'status' => 'ok',
            'code' => $code,
            'written' => is_file(LANG_DIR . '/' . $code . '.inc') ? 1 : 0,
            'keys' => $keys,
        ], JSON_UNESCAPED_UNICODE));
        break;

    case 'set':
        if ($code === '') {
            fail('dil kodu gerekli');
        }
        $posted = payload($argument);
        if (empty($posted)) {
            fail('yazılacak metin yok');
        }
        $current = read_lang($code);
        $changed = [];
        foreach ($posted as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $current[(string)$key] = (string)$value;
            $changed[] = (string)$key;
        }
        if (empty($changed)) {
            fail('geçerli anahtar yok');
        }

        $file = LANG_DIR . '/' . $code . '.inc';
        if (is_file($file)) {
            copy($file, $file . '.bak-' . date('YmdHis'));
        }
        $body = "<?php\n\nreturn " . var_export($current, true) . ";\n";
        file_put_contents($file . '.new', $body);
        rename($file . '.new', $file);
        @chown($file, 'www');
        @chgrp($file, 'www');
        @chmod($file, 0640);

        printf("%s\n", json_encode([
            'status' => 'ok',
            'code' => $code,
            'changed' => count($changed),
        ], JSON_UNESCAPED_UNICODE));
        break;

    default:
        fail('bilinmeyen komut: ' . $command);
}
