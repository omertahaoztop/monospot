#!/usr/local/bin/php
<?php

/**
 * Monospot grup bazlı portal ayarları köprüsü.
 *
 * Grup ayarları app/settings_group<id>.inc dosyasında tutulur ve global
 * settings.inc'i yalnız hotspot.ini'deki `mac_grup_ayarlari` bölümleri için
 * geçersiz kılar (uygulama modeliyle aynı mantık: authentication, general).
 *
 *   group_settings_admin.php groups
 *   group_settings_admin.php get:<groupid>
 *   group_settings_admin.php set:<groupid>:<base64-json>
 */

const APP_DIR = '/usr/local/opnsense/scripts/monospot/app';
const SETTINGS_FILE = APP_DIR . '/settings.inc';
const HOTSPOT_FILE = APP_DIR . '/hotspot.ini';
const DB_FILE = APP_DIR . '/db/hotspot.db';

/* düz anahtar => settings.inc yolu (grup düzenleyebildiği alanlar) */
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
    'disallow_multiple_logins' => ['disallow_multiple_logins'],
    'disallow_multiple_logins_for' => ['disallow_multiple_logins_for'],
    'daily_limit' => ['daily_limit'],
    'weekly_limit' => ['weekly_limit'],
    'monthly_limit' => ['monthly_limit'],
    'yearly_limit' => ['yearly_limit'],
    'min_interval' => ['min_interval'],
];

const BOOL_KEYS = [
    'auth_sms', 'auth_id_number', 'auth_manual_user',
    'sms_simple_screen', 'sms_international', 'sms_always_send_password', 'sms_id_number',
    'id_number_open_extended', 'terms', 'terms_checked', 'disallow_multiple_logins',
];

const NUM_RANGE = [
    'session_timeout' => [1, 10080],
    'valid_for' => [1, 100000],
    'disallow_multiple_logins_for' => [1, 3650],
    'daily_limit' => [0, 10000],
    'weekly_limit' => [0, 10000],
    'monthly_limit' => [0, 10000],
    'yearly_limit' => [0, 10000],
    'min_interval' => [0, 1440],
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

function group_file($groupid)
{
    return APP_DIR . '/settings_group' . (int)$groupid . '.inc';
}

function read_array($file)
{
    if (!is_file($file)) {
        return [];
    }
    $data = include $file;
    return is_array($data) ? $data : [];
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

/** Grup seçilebilen bölümler hotspot.ini'den okunur. */
function group_sections()
{
    if (!is_file(HOTSPOT_FILE)) {
        return [];
    }
    $ini = @parse_ini_file(HOTSPOT_FILE, false);
    return is_array($ini) && isset($ini['mac_grup_ayarlari']) ? (array)$ini['mac_grup_ayarlari'] : [];
}

function groups()
{
    $rows = [];
    if (!is_file(DB_FILE)) {
        return $rows;
    }
    $db = new SQLite3(DB_FILE);
    $result = $db->query('SELECT id, name FROM "group" ORDER BY name');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = ['uuid' => (string)$row['id'], 'name' => (string)$row['name']];
    }
    return $rows;
}

$first = isset($argv[1]) ? (string)$argv[1] : '';
$parts = explode(':', $first, 3);
$command = $parts[0];
$groupid = isset($parts[1]) ? (int)$parts[1] : 0;
$argument = isset($parts[2]) ? $parts[2] : '';

switch ($command) {
    case 'groups':
        printf("%s\n", json_encode([
            'status' => 'ok',
            'groups' => groups(),
            'sections' => group_sections(),
        ], JSON_UNESCAPED_UNICODE));
        break;

    case 'get':
        if ($groupid <= 0) {
            fail('grup kimliği gerekli');
        }
        $global = read_array(SETTINGS_FILE);
        $own = read_array(group_file($groupid));
        /* grup dosyası global'i geçersiz kılar; olmayan anahtar global'den gelir */
        $merged = array_replace_recursive($global, $own);
        $settings = [];
        foreach (FLAT_MAP as $flat => $path) {
            $value = get_path($merged, $path);
            if (in_array($flat, BOOL_KEYS, true)) {
                $settings[$flat] = $value === null ? '' : '1';
            } elseif (isset(NUM_RANGE[$flat])) {
                $settings[$flat] = (string)(int)$value;
            } else {
                $settings[$flat] = (string)$value;
            }
        }
        printf("%s\n", json_encode([
            'status' => 'ok',
            'group' => ['group_id' => (string)$groupid, 'own_keys' => count($own)],
            'settings' => $settings,
        ], JSON_UNESCAPED_UNICODE));
        break;

    case 'set':
        if ($groupid <= 0) {
            fail('grup kimliği gerekli');
        }
        $params = payload($argument);
        if (empty($params)) {
            fail('yazılacak veri yok');
        }
        $own = read_array(group_file($groupid));
        $changed = [];
        foreach (FLAT_MAP as $flat => $path) {
            if (!array_key_exists($flat, $params)) {
                continue;
            }
            $value = $params[$flat];
            if (in_array($flat, BOOL_KEYS, true)) {
                $on = ($value === '1' || $value === 1 || $value === true);
                set_path($own, $path, $on ? '1' : null);
            } elseif (isset(NUM_RANGE[$flat])) {
                [$min, $max] = NUM_RANGE[$flat];
                $number = (int)$value;
                if ($number < $min || $number > $max) {
                    fail(sprintf('%s %d..%d arasında olmalı (verilen: %s)', $flat, $min, $max, (string)$value));
                }
                set_path($own, $path, (string)$number);
            } elseif ($flat === 'valid_for_unit') {
                set_path($own, $path, $value === 'minutes' ? 'minutes' : 'days');
            } else {
                set_path($own, $path, (string)$value);
            }
            $changed[] = $flat;
        }

        $file = group_file($groupid);
        if (is_file($file)) {
            copy($file, $file . '.bak-' . date('YmdHis'));
        }
        $body = "<?php\n\nreturn " . var_export($own, true) . ";\n";
        file_put_contents($file . '.new', $body);
        rename($file . '.new', $file);
        @chown($file, 'www');
        @chgrp($file, 'www');
        @chmod($file, 0640);

        printf("%s\n", json_encode([
            'status' => 'ok',
            'group_id' => (string)$groupid,
            'changed' => $changed,
        ], JSON_UNESCAPED_UNICODE));
        break;

    default:
        fail('bilinmeyen komut: ' . $command);
}
