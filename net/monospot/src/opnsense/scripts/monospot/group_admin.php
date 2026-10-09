#!/usr/local/bin/php
<?php

/**
 * Monospot grup yönetimi köprüsü (hotspot SQLite `group` tablosu).
 *
 * configd tarafından root olarak çalışır (portal veritabanına www yazar).
 *
 *   group_admin.php list:<base64-json>   # {"search":"","limit":50,"offset":0,"sort":"name","dir":"asc"}
 *   group_admin.php get:<id>
 *   group_admin.php save:<base64-json>   # {"uuid":.., "name":.., "macs":"aa:bb:..\n..."}
 *   group_admin.php delete:<id>
 */

const DB_FILE = '/usr/local/opnsense/scripts/monospot/app/db/hotspot.db';
const MAC_PATTERN = '/(([0-9A-Fa-f]{2}[-:]){5}[0-9A-Fa-f]{2})|(([0-9A-Fa-f]{4}\.){2}[0-9A-Fa-f]{4})/';

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

function db()
{
    if (!is_file(DB_FILE)) {
        fail('veritabanı bulunamadı: ' . DB_FILE);
    }
    $db = new SQLite3(DB_FILE);
    $db->busyTimeout(5000);
    return $db;
}

/** MAC listesini normalleştirir (küçük harf, ':' ayracı, satır başına bir MAC). */
function normalize_macs($raw)
{
    $list = [];
    foreach (preg_split('/[\s,;]+/', (string)$raw) as $mac) {
        $mac = trim($mac);
        if ($mac === '') {
            continue;
        }
        if (!preg_match(MAC_PATTERN, $mac)) {
            fail('geçersiz MAC adresi: ' . $mac);
        }
        $list[] = strtolower(str_replace('-', ':', $mac));
    }
    return implode("\n", array_unique($list));
}

function row_out($row)
{
    return [
        'uuid' => (string)$row['id'],
        'name' => (string)$row['name'],
        'macs' => (string)$row['macs'],
        'mac_count' => count(array_filter(preg_split('/[\s,;]+/', (string)$row['macs']))),
    ];
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
        $search = trim((string)($params['search'] ?? ''));
        $sort = in_array(($params['sort'] ?? ''), ['name', 'id'], true) ? $params['sort'] : 'name';
        $dir = strtolower((string)($params['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $where = '';
        $bind = [];
        if ($search !== '') {
            $where = 'WHERE name LIKE :q OR macs LIKE :q';
            $bind[':q'] = '%' . $search . '%';
        }

        $db = db();
        $countStatement = $db->prepare('SELECT COUNT(*) AS total FROM "group" ' . $where);
        foreach ($bind as $key => $value) {
            $countStatement->bindValue($key, $value, SQLITE3_TEXT);
        }
        $total = (int)$countStatement->execute()->fetchArray(SQLITE3_ASSOC)['total'];

        $statement = $db->prepare('SELECT * FROM "group" ' . $where . ' ORDER BY ' . $sort . ' ' . $dir
            . ' LIMIT :limit OFFSET :offset');
        foreach ($bind as $key => $value) {
            $statement->bindValue($key, $value, SQLITE3_TEXT);
        }
        $statement->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $statement->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $result = $statement->execute();
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = row_out($row);
        }

        printf("%s\n", json_encode([
            'status' => 'ok',
            'rows' => $rows,
            'total' => $total,
            'current' => (int)floor($offset / $limit) + 1,
            'rowCount' => $limit,
        ], JSON_UNESCAPED_UNICODE));
        break;

    case 'get':
        $db = db();
        $statement = $db->prepare('SELECT * FROM "group" WHERE id = :id');
        $statement->bindValue(':id', (int)$argument, SQLITE3_INTEGER);
        $row = $statement->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row === false) {
            fail('grup bulunamadı: ' . $argument);
        }
        printf("%s\n", json_encode(['status' => 'ok', 'group' => row_out($row)], JSON_UNESCAPED_UNICODE));
        break;

    case 'save':
        $params = payload($argument);
        $id = (int)($params['uuid'] ?? 0);
        $name = trim((string)($params['name'] ?? ''));
        if ($name === '') {
            fail('grup adı zorunlu');
        }
        $macs = normalize_macs($params['macs'] ?? '');
        if ($macs === '') {
            fail('en az bir MAC adresi gerekli');
        }

        $db = db();
        /* aynı MAC başka grupta olmasın (uygulama paneliyle aynı kural) */
        $statement = $db->prepare('SELECT id, name, macs FROM "group" WHERE id <> :id');
        $statement->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $statement->execute();
        while ($other = $result->fetchArray(SQLITE3_ASSOC)) {
            foreach (explode("\n", $macs) as $mac) {
                if ($mac !== '' && strpos((string)$other['macs'], $mac) !== false) {
                    fail(sprintf('MAC başka grupta: %s (grup: %s)', $mac, $other['name']));
                }
            }
        }

        if ($id > 0) {
            $statement = $db->prepare('UPDATE "group" SET name = :name, macs = :macs WHERE id = :id');
            $statement->bindValue(':id', $id, SQLITE3_INTEGER);
        } else {
            $statement = $db->prepare('INSERT INTO "group" (name, macs) VALUES (:name, :macs)');
        }
        $statement->bindValue(':name', $name, SQLITE3_TEXT);
        $statement->bindValue(':macs', $macs, SQLITE3_TEXT);
        $statement->execute();
        $id = $id > 0 ? $id : (int)$db->lastInsertRowID();

        printf("%s\n", json_encode(['status' => 'ok', 'uuid' => (string)$id], JSON_UNESCAPED_UNICODE));
        break;

    case 'delete':
        $db = db();
        $statement = $db->prepare('DELETE FROM "group" WHERE id = :id');
        $statement->bindValue(':id', (int)$argument, SQLITE3_INTEGER);
        $statement->execute();
        printf("%s\n", json_encode(['status' => 'ok', 'deleted' => (int)$argument], JSON_UNESCAPED_UNICODE));
        break;

    default:
        fail('bilinmeyen komut: ' . $command);
}
