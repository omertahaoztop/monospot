#!/usr/local/bin/php
<?php

/**
 * Monospot portal uygulama ayarları köprüsü.
 *
 * Panelin yönettiği iki dosyayı okur/yazar:
 *   - app/settings.inc  (authentication/terms/genel/SMS/erişim ekranı/yönetim ekranı)
 *   - app/hotspot.ini   (firma, marka)
 *
 * Yönetilmeyen anahtarlar ve dosyadaki diğer satırlar korunur; yazma öncesi
 * yedeğe kopyalanır. Form düz anahtar kullanır (portal.*), burada iç içe
 * yapıya çevrilir.
 *
 *   portal_settings.php get
 *   portal_settings.php set:<base64-json>
 */

const APP_DIR = '/usr/local/opnsense/scripts/monospot/app';
const SETTINGS_FILE = APP_DIR . '/settings.inc';
const HOTSPOT_FILE = APP_DIR . '/hotspot.ini';

/* düz anahtar => settings.inc içindeki yol */
const FLAT_MAP = [
    'auth_sms' => ['authentication', 'sms'],
    'auth_id_number' => ['authentication', 'id_number'],
    'auth_manual_user' => ['authentication', 'manual_user'],
    'sms_simple_screen' => ['sms', 'simple_screen'],
    'sms_international' => ['sms', 'international'],
    'sms_always_send_password' => ['sms', 'always_send_password'],
    'sms_id_number' => ['sms', 'id_number'],
    'id_number_open_extended' => ['id_number', 'open_extended'],
    'terms' => ['terms'],
    'terms_checked' => ['terms_checked'],
    'session_timeout' => ['session_timeout'],
    'valid_for' => ['valid_for'],
    'valid_for_unit' => ['valid_for_unit'],
    'after_login_url' => ['after_login_url'],
    'disallow_multiple_logins' => ['disallow_multiple_logins'],
    'disallow_multiple_logins_for' => ['disallow_multiple_logins_for'],
    'daily_global_limit' => ['daily_global_limit'],
    'daily_limit' => ['daily_limit'],
    'weekly_limit' => ['weekly_limit'],
    'monthly_limit' => ['monthly_limit'],
    'yearly_limit' => ['yearly_limit'],
    'min_interval' => ['min_interval'],
    'name' => ['name'],
    'color' => ['color'],
    'items_per_page' => ['items_per_page'],
    'custom_fields' => ['custom_fields'],
];

/* onay kutusu (değer '1' ise anahtar eklenir, değilse kaldırılır) */
const BOOL_KEYS = [
    'auth_sms', 'auth_id_number', 'auth_manual_user',
    'sms_simple_screen', 'sms_international', 'sms_always_send_password', 'sms_id_number',
    'id_number_open_extended', 'terms', 'terms_checked', 'disallow_multiple_logins',
];

/* sayısal alanlar (üst sınırlar dahil) */
const NUM_RANGE = [
    'session_timeout' => [1, 10080],
    'valid_for' => [1, 100000],
    'disallow_multiple_logins_for' => [1, 3650],
    'daily_global_limit' => [0, 100000],
    'daily_limit' => [0, 10000],
    'weekly_limit' => [0, 10000],
    'monthly_limit' => [0, 10000],
    'yearly_limit' => [0, 10000],
    'min_interval' => [0, 1440],
    'items_per_page' => [1, 1000],
];

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

function read_settings()
{
    if (!is_file(SETTINGS_FILE)) {
        fail('settings.inc bulunamadı: ' . SETTINGS_FILE);
    }
    $settings = include SETTINGS_FILE;
    return is_array($settings) ? $settings : [];
}

function read_brand()
{
    $brand = [];
    if (is_file(HOTSPOT_FILE)) {
        $ini = @parse_ini_file(HOTSPOT_FILE, false);
        if (is_array($ini)) {
            $brand = $ini;
        }
    }
    return [
        'firma' => (string)($brand['firma'] ?? ''),
        'marka' => (string)($brand['marka'] ?? ''),
    ];
}

function get_path(array $settings, array $path)
{
    $node = $settings;
    foreach ($path as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return null;
        }
        $node = $node[$key];
    }
    return $node;
}

function set_path(array &$settings, array $path, $value)
{
    $node = &$settings;
    $last = array_pop($path);
    foreach ($path as $key) {
        if (!isset($node[$key]) || !is_array($node[$key])) {
            $node[$key] = [];
        }
        $node = &$node[$key];
    }
    if ($value === null) {
        unset($node[$last]);
    } else {
        $node[$last] = $value;
    }
}

function write_settings(array $settings)
{
    $backup = SETTINGS_FILE . '.bak-' . date('YmdHis');
    if (!copy(SETTINGS_FILE, $backup)) {
        fail('yedek alınamadı: ' . $backup);
    }
    $body = "<?php\n\nreturn " . var_export($settings, true) . ";\n";
    if (file_put_contents(SETTINGS_FILE . '.new', $body) === false) {
        fail('geçici dosya yazılamadı');
    }
    if (!rename(SETTINGS_FILE . '.new', SETTINGS_FILE)) {
        fail('settings.inc yerine konulamadı');
    }
    @chown(SETTINGS_FILE, 'www');
    @chgrp(SETTINGS_FILE, 'www');
    @chmod(SETTINGS_FILE, 0640);
    return $backup;
}

function write_brand(array $brand)
{
    if (!is_file(HOTSPOT_FILE)) {
        return null;
    }
    $backup = HOTSPOT_FILE . '.bak-' . date('YmdHis');
    copy(HOTSPOT_FILE, $backup);
    $lines = file(HOTSPOT_FILE, FILE_IGNORE_NEW_LINES);
    $seen = [];
    foreach ($lines as $index => $line) {
        foreach (['firma', 'marka'] as $key) {
            if (preg_match('/^\s*' . $key . '\s*=/', $line)) {
                $lines[$index] = $key . ' = ' . $brand[$key];
                $seen[$key] = true;
            }
        }
    }
    foreach (['firma', 'marka'] as $key) {
        if (!isset($seen[$key])) {
            $lines[] = $key . ' = ' . $brand[$key];
        }
    }
    file_put_contents(HOTSPOT_FILE . '.new', implode("\n", $lines) . "\n");
    rename(HOTSPOT_FILE . '.new', HOTSPOT_FILE);
    @chown(HOTSPOT_FILE, 'www');
    @chgrp(HOTSPOT_FILE, 'www');
    @chmod(HOTSPOT_FILE, 0640);
    return $backup;
}

$first = isset($argv[1]) ? (string)$argv[1] : '';
$parts = explode(':', $first, 2);
$command = $parts[0];
$argument = isset($parts[1]) ? $parts[1] : '';

switch ($command) {
    case 'get':
        $settings = read_settings();
        $portal = [];
        foreach (FLAT_MAP as $flat => $path) {
            $value = get_path($settings, $path);
            if (in_array($flat, BOOL_KEYS, true)) {
                $portal[$flat] = $value === null ? '' : '1';
            } elseif (isset(NUM_RANGE[$flat])) {
                $portal[$flat] = (string)(int)$value;
            } else {
                $portal[$flat] = (string)$value;
            }
        }
        $portal += read_brand();
        printf("%s\n", json_encode(['status' => 'ok', 'portal' => $portal], JSON_UNESCAPED_UNICODE));
        break;

    case 'set':
        $params = payload($argument);
        if (empty($params)) {
            fail('yazılacak veri yok');
        }
        $settings = read_settings();
        $changed = [];
        foreach (FLAT_MAP as $flat => $path) {
            if (!array_key_exists($flat, $params)) {
                continue;
            }
            $value = $params[$flat];
            if (in_array($flat, BOOL_KEYS, true)) {
                $on = ($value === '1' || $value === 1 || $value === true);
                set_path($settings, $path, $on ? '1' : null);
            } elseif (isset(NUM_RANGE[$flat])) {
                [$min, $max] = NUM_RANGE[$flat];
                $number = (int)$value;
                if ($number < $min || $number > $max) {
                    fail(sprintf('%s %d..%d arasında olmalı (verilen: %s)', $flat, $min, $max, (string)$value));
                }
                set_path($settings, $path, (string)$number);
            } elseif ($flat === 'valid_for_unit') {
                set_path($settings, $path, $value === 'minutes' ? 'minutes' : 'days');
            } elseif ($flat === 'color') {
                set_path($settings, $path, preg_match('/^#[0-9a-fA-F]{6}$/', (string)$value) ? (string)$value : '#111417');
            } else {
                set_path($settings, $path, (string)$value);
            }
            $changed[] = $flat;
        }

        $brand = [];
        foreach (['firma', 'marka'] as $key) {
            if (array_key_exists($key, $params)) {
                $brand[$key] = trim((string)$params[$key]);
                $changed[] = $key;
            }
        }

        $backup = write_settings($settings);
        $brandBackup = $brand === [] ? null : write_brand($brand + read_brand());

        printf("%s\n", json_encode([
            'status' => 'ok',
            'changed' => $changed,
            'backup' => basename($backup),
            'brand_backup' => $brandBackup === null ? '' : basename($brandBackup),
        ], JSON_UNESCAPED_UNICODE));
        break;

    default:
        fail('bilinmeyen komut: ' . $command);
}
