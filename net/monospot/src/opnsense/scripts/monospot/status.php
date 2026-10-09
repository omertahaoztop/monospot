#!/usr/local/bin/php
<?php

/**
 * Monospot durum özeti (Services > Monospot sayfasındaki Durum paneli).
 *
 * configd tarafından root olarak çalışır; tek satır JSON döner:
 * servis/zone bilgisi, yetkili istemci sayısı, uygulama veritabanı ve 5651
 * arşivi özetleri, SMS sağlayıcı durumu.
 */

require_once('config.inc');
require_once('util.inc');

const APP_DIR = '/usr/local/opnsense/scripts/monospot/app';
const ARCHIVE_ROOT = '/var/db/monospot/5651';

$mdl = new \OPNsense\Monospot\Monospot();
$zoneid = $mdl->getZoneId();
$port = $mdl->getPortalPort();

$result = [
    'enabled' => (string)$mdl->general->enabled === '1',
    'zoneid' => $zoneid,
    'portal_port' => $port,
    'zone' => ['description' => '', 'interfaces' => [], 'hardtimeout' => 0, 'idletimeout' => 0],
    'clients' => ['authorized' => 0, 'sample' => []],
    'db' => ['users' => 0, 'sms' => 0, 'permissions' => 0, 'bytes' => 0],
    'archive' => ['days' => 0, 'bytes' => 0, 'newest' => '', 'oldest' => ''],
    'sms' => ['provider_file' => false, 'mock_log' => false],
    'collect5651' => (string)$mdl->collect5651->enabled === '1',
    'retention_days' => (int)((string)$mdl->collect5651->retention_days ?: 365),
];

/* zone: core captive portal modelinden */
$cp = new \OPNsense\CaptivePortal\CaptivePortal();
$zone = $cp->getByZoneID($zoneid);
$result['zone']['template_id'] = '';
$result['zone']['template_name'] = '';
if ($zone !== null) {
    $result['zone']['description'] = (string)$zone->description;
    $result['zone']['interfaces'] = $zone->interfaces->getValues();
    $result['zone']['hardtimeout'] = (int)(string)$zone->hardtimeout;
    $result['zone']['idletimeout'] = (int)(string)$zone->idletimeout;
    $result['zone']['template_id'] = (string)$zone->template;
    /* zone.template bir ModelRelationField: getNodeData() uuid => ad eşlemesi verir */
    $templates = $zone->template->getNodeData();
    if (is_array($templates) && isset($templates[$result['zone']['template_id']])) {
        $entry = $templates[$result['zone']['template_id']];
        /* ModelRelationField seçenekleri dizi olabilir (name/value/selected) */
        if (is_array($entry)) {
            $entry = $entry['name'] ?? ($entry['value'] ?? reset($entry));
        }
        $result['zone']['template_name'] = (string)$entry;
    }
}

/* yetkili istemciler */
$raw = shell_exec('/usr/local/sbin/configctl captiveportal list_clients ' . escapeshellarg($zoneid) . ' 2>/dev/null');
$clients = json_decode(trim((string)$raw), true);
if (is_array($clients)) {
    $result['clients']['authorized'] = count($clients);
    foreach (array_slice($clients, 0, 10) as $client) {
        $result['clients']['sample'][] = sprintf(
            '%s (%s)',
            $client['userName'] ?? '?',
            $client['ipAddress'] ?? '?'
        );
    }
}

/* uygulama veritabanı */
$dbFile = APP_DIR . '/db/hotspot.db';
if (is_file($dbFile)) {
    $result['db']['bytes'] = filesize($dbFile);
    try {
        $db = new SQLite3($dbFile, SQLITE3_OPEN_READONLY);
        foreach (['user' => 'users', 'sms' => 'sms', 'permission' => 'permissions'] as $table => $key) {
            $row = $db->querySingle('SELECT COUNT(*) FROM ' . $table);
            $result['db'][$key] = (int)$row;
        }
        $db->close();
    } catch (Exception $exc) {
        $result['db']['error'] = $exc->getMessage();
    }
}

/* 5651 arşivi */
if (is_dir(ARCHIVE_ROOT)) {
    $days = [];
    foreach (scandir(ARCHIVE_ROOT) as $year) {
        if (!preg_match('/^\d{4}$/', $year)) {
            continue;
        }
        foreach (scandir(ARCHIVE_ROOT . '/' . $year) as $month) {
            if (!preg_match('/^\d{2}$/', $month)) {
                continue;
            }
            foreach (scandir(ARCHIVE_ROOT . '/' . $year . '/' . $month) as $day) {
                if (preg_match('/^\d{2}$/', $day)) {
                    $days[] = $year . '-' . $month . '-' . $day;
                }
            }
        }
    }
    sort($days);
    $result['archive']['days'] = count($days);
    $result['archive']['oldest'] = $days[0] ?? '';
    $result['archive']['newest'] = end($days) ?: '';
    $bytes = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(ARCHIVE_ROOT, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $bytes += $file->getSize();
        }
    }
    $result['archive']['bytes'] = $bytes;
}

/* SMS sağlayıcı */
$result['sms']['provider_file'] = is_file(APP_DIR . '/sms.php');
$result['sms']['mock_log'] = is_file('/tmp/monospot-sms.log');

echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
