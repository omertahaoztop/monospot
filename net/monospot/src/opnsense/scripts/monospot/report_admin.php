#!/usr/local/bin/php
<?php

/**
 * Monospot rapor köprüsü (salt-okunur): SMS gönderim kayıtları ve KVKK izin kayıtları.
 *
 *   report_admin.php sms:<base64-json>
 *   report_admin.php permission:<base64-json>
 *
 * Girdi JSON: {"search":"","limit":50,"offset":0}
 */

const DB_FILE = '/usr/local/opnsense/scripts/monospot/app/db/hotspot.db';

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

function params($argument)
{
    $values = payload($argument);
    return [
        max(1, min(500, (int)($values['limit'] ?? 50))),
        max(0, (int)($values['offset'] ?? 0)),
        trim((string)($values['search'] ?? '')),
    ];
}

function db()
{
    if (!is_file(DB_FILE)) {
        fail('veritabanı bulunamadı: ' . DB_FILE);
    }
    $db = new SQLite3(DB_FILE);
    $db->busyTimeout(5000);
    return $db;
}

function output($rows, $total, $limit, $offset)
{
    printf("%s\n", json_encode([
        'status' => 'ok',
        'rows' => $rows,
        'total' => $total,
        'current' => (int)floor($offset / $limit) + 1,
        'rowCount' => $limit,
    ], JSON_UNESCAPED_UNICODE));
}

$first = isset($argv[1]) ? (string)$argv[1] : '';
$parts = explode(':', $first, 2);
$command = $parts[0];
$argument = isset($parts[1]) ? $parts[1] : '';

switch ($command) {
    case 'sms':
        [$limit, $offset, $search] = params($argument);
        $where = '';
        $bind = [];
        if ($search !== '') {
            $where = 'WHERE s.mac LIKE :q OR u.gsm LIKE :q OR u.username LIKE :q';
            $bind[':q'] = '%' . $search . '%';
        }
        $db = db();
        $countStatement = $db->prepare(
            'SELECT COUNT(*) AS total FROM sms s LEFT JOIN user u ON u.id = s.user_id ' . $where
        );
        foreach ($bind as $key => $value) {
            $countStatement->bindValue($key, $value, SQLITE3_TEXT);
        }
        $total = (int)$countStatement->execute()->fetchArray(SQLITE3_ASSOC)['total'];

        $statement = $db->prepare(
            'SELECT s.id, s.mac, s.timestamp, u.gsm, u.username FROM sms s '
            . 'LEFT JOIN user u ON u.id = s.user_id ' . $where
            . ' ORDER BY s.timestamp DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($bind as $key => $value) {
            $statement->bindValue($key, $value, SQLITE3_TEXT);
        }
        $statement->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $statement->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $result = $statement->execute();

        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = [
                'uuid' => (string)$row['id'],
                'time' => (int)$row['timestamp'] > 0 ? date('Y-m-d H:i:s', (int)$row['timestamp']) : '',
                'gsm' => (string)($row['gsm'] ?? ''),
                'username' => (string)($row['username'] ?? ''),
                'mac' => (string)$row['mac'],
            ];
        }
        output($rows, $total, $limit, $offset);
        break;

    case 'permission':
        [$limit, $offset, $search] = params($argument);
        $where = '';
        $bind = [];
        if ($search !== '') {
            $where = 'WHERE p.mac LIKE :q OR p.ip LIKE :q OR p.gsm LIKE :q OR p.email LIKE :q';
            $bind[':q'] = '%' . $search . '%';
        }
        $db = db();
        $countStatement = $db->prepare('SELECT COUNT(*) AS total FROM permission p ' . $where);
        foreach ($bind as $key => $value) {
            $countStatement->bindValue($key, $value, SQLITE3_TEXT);
        }
        $total = (int)$countStatement->execute()->fetchArray(SQLITE3_ASSOC)['total'];

        $statement = $db->prepare(
            'SELECT p.*, u.username FROM permission p LEFT JOIN user u ON u.id = p.user_id ' . $where
            . ' ORDER BY p.timestamp DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($bind as $key => $value) {
            $statement->bindValue($key, $value, SQLITE3_TEXT);
        }
        $statement->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $statement->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $result = $statement->execute();

        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = [
                'uuid' => (string)$row['id'],
                'time' => (int)$row['timestamp'] > 0 ? date('Y-m-d H:i:s', (int)$row['timestamp']) : '',
                'user_id' => (string)$row['user_id'],
                'username' => (string)($row['username'] ?? ''),
                'gsm' => (string)$row['gsm'],
                'email' => (string)$row['email'],
                'mac' => (string)$row['mac'],
                'ip' => (string)$row['ip'],
            ];
        }
        output($rows, $total, $limit, $offset);
        break;

    default:
        fail('bilinmeyen komut: ' . $command);
}
