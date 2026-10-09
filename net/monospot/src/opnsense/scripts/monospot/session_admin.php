#!/usr/local/bin/php
<?php

/**
 * Monospot oturum köprüsü: captive portal zone oturumlarını listeler ve keser.
 *
 *   session_admin.php list:<base64-json>   # {"search":"","limit":50,"offset":0,"sort":"start","dir":"desc"}
 *   session_admin.php disconnect:<sessionId>
 *
 * Zone, Monospot modelinden gelir (core captive portal zone).
 */

require_once('config.inc');
require_once('util.inc');

const DISCONNECT_REASON = 'monospot-ui';

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

function zoneid()
{
    $mdl = new \OPNsense\Monospot\Monospot();
    return $mdl->getZoneId();
}

function cli($command)
{
    return trim((string)shell_exec('/usr/local/sbin/configctl ' . escapeshellarg('captiveportal') . ' '
        . trim($command) . ' 2>&1'));
}

function human_bytes($bytes)
{
    $bytes = (float)$bytes;
    foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
        if ($bytes < 1024 || $unit === 'GB') {
            return number_format($bytes, $unit === 'B' ? 0 : 1, ',', '.') . ' ' . $unit;
        }
        $bytes /= 1024;
    }
    return '0 B';
}

$first = isset($argv[1]) ? (string)$argv[1] : '';
$parts = explode(':', $first, 2);
$command = $parts[0];
$argument = isset($parts[1]) ? $parts[1] : '';

switch ($command) {
    case 'list':
        $params = payload($argument);
        $limit = max(1, min(500, (int)($params['limit'] ?? 50)));
        $offset = max(0, (int)($params['offset'] ?? 0));
        $search = strtolower(trim((string)($params['search'] ?? '')));
        $sort = (string)($params['sort'] ?? 'start');
        $dir = strtolower((string)($params['dir'] ?? 'desc')) === 'asc' ? 1 : -1;

        $raw = cli('list_clients ' . zoneid());
        $sessions = json_decode($raw, true);
        if (!is_array($sessions)) {
            fail('oturum listesi okunamadı: ' . $raw);
        }

        $rows = [];
        foreach ($sessions as $session) {
            $started = (float)($session['startTime'] ?? 0);
            $last = (int)($session['last_accessed'] ?? 0);
            $row = [
                'id' => (string)($session['sessionId'] ?? ''),
                'user' => (string)($session['userName'] ?? ''),
                'ip' => (string)($session['ipAddress'] ?? ''),
                'mac' => (string)($session['macAddress'] ?? ''),
                'start' => $started > 0 ? date('Y-m-d H:i', (int)$started) : '',
                'start_ts' => $started,
                'last' => $last > 0 ? date('Y-m-d H:i', $last) : '',
                'last_ts' => $last,
                'via' => (string)($session['authenticated_via'] ?? ''),
                'traffic' => human_bytes((float)($session['bytes_in'] ?? 0) + (float)($session['bytes_out'] ?? 0)),
                'bytes' => (float)($session['bytes_in'] ?? 0) + (float)($session['bytes_out'] ?? 0),
                'packets' => (int)($session['packets_in'] ?? 0) + (int)($session['packets_out'] ?? 0),
            ];
            if ($search !== '') {
                $haystack = strtolower($row['user'] . ' ' . $row['ip'] . ' ' . $row['mac'] . ' ' . $row['via']);
                if (strpos($haystack, $search) === false) {
                    continue;
                }
            }
            $rows[] = $row;
        }

        $sortKeys = ['start' => 'start_ts', 'last' => 'last_ts', 'traffic' => 'bytes', 'user' => 'user'];
        $key = $sortKeys[$sort] ?? 'start_ts';
        usort($rows, function ($a, $b) use ($key, $dir) {
            return $dir * (is_string($a[$key]) ? strcmp($a[$key], $b[$key]) : ($a[$key] <=> $b[$key]));
        });

        $total = count($rows);
        $rows = array_slice($rows, $offset, $limit);

        printf("%s\n", json_encode([
            'status' => 'ok',
            'zoneid' => zoneid(),
            'rows' => $rows,
            'total' => $total,
            'current' => (int)floor($offset / $limit) + 1,
            'rowCount' => $limit,
        ], JSON_UNESCAPED_UNICODE));
        break;

    case 'disconnect':
        $sessionid = trim($argument);
        if ($sessionid === '') {
            fail('oturum kimliği boş');
        }
        $result = cli('disconnect ' . escapeshellarg($sessionid) . ' ' . escapeshellarg(DISCONNECT_REASON));
        printf("%s\n", json_encode([
            'status' => 'ok',
            'session' => $sessionid,
            'output' => $result,
        ], JSON_UNESCAPED_UNICODE));
        break;

    default:
        fail('bilinmeyen komut: ' . $command);
}
