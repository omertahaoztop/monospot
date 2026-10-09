#!/usr/local/bin/php
<?php

/**
 * Monospot kullanıcı yönetimi köprüsü (5651/hotspot SQLite veritabanı).
 *
 * configd tarafından root olarak çalışır: portal (www) veritabanına yazabildiği
 * için yönetim yazımları root üzerinden yapılır, sahiplik değiştirilmez.
 *
 *   user_admin.php list <base64-json>   # {"search":"","limit":50,"offset":0,"sort":"username","dir":"asc"}
 *   user_admin.php get <id>
 *   user_admin.php save <base64-json>   # {"id":.., ...alanlar}
 *   user_admin.php delete <id>
 *
 * Çıktı tek satır JSON'dur.
 */

const DB_FILE = '/usr/local/opnsense/scripts/monospot/app/db/hotspot.db';

/* Formda gösterilen/yazılan alanlar (uydurma alan yazılmaz) */
const FIELDS = [
    'username', 'gsm', 'password', 'id_number', 'name', 'surname', 'email',
    'group_id', 'daily_limit', 'weekly_limit', 'monthly_limit', 'yearly_limit',
    'expires', 'gsm_permission', 'email_permission',
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

function db()
{
    if (!is_file(DB_FILE)) {
        fail('veritabanı bulunamadı: ' . DB_FILE);
    }
    $db = new SQLite3(DB_FILE);
    $db->busyTimeout(5000);
    return $db;
}

/** Kayıt satırını form beklentisine çevirir (uuid = id). */
function row_out($row)
{
    $out = ['uuid' => (string)$row['id']];
    foreach (FIELDS as $field) {
        $value = $row[$field] ?? '';
        if ($field === 'expires') {
            $value = (int)$value > 0 ? date('Y-m-d', (int)$value) : '';
        } elseif ($field === 'password') {
            /* şifre tarayıcıya dönmez; formda boş = değişmeyecek */
            $value = '';
        }
        $out[$field] = (string)$value;
    }
    $out['last_login'] = (int)($row['last_login'] ?? 0) > 0 ? date('Y-m-d H:i', (int)$row['last_login']) : '';
    $out['last_mac'] = (string)($row['last_mac'] ?? '');
    $out['group_name'] = (string)($row['group_name'] ?? '');
    return $out;
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
        $sort = in_array(($params['sort'] ?? ''), ['username', 'gsm', 'id', 'expires', 'last_login'], true)
            ? $params['sort'] : 'id';
        $dir = strtolower((string)($params['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $where = '';
        $bind = [];
        if ($search !== '') {
            $where = 'WHERE u.username LIKE :q OR u.gsm LIKE :q OR u.name LIKE :q OR u.surname LIKE :q'
                . ' OR u.email LIKE :q OR u.id_number LIKE :q';
            $bind[':q'] = '%' . $search . '%';
        }

        $db = db();
        $countStatement = $db->prepare('SELECT COUNT(*) AS total FROM user u ' . $where);
        foreach ($bind as $key => $value) {
            $countStatement->bindValue($key, $value, SQLITE3_TEXT);
        }
        $total = (int)$countStatement->execute()->fetchArray(SQLITE3_ASSOC)['total'];
        $statement = $db->prepare(
            'SELECT u.*, g.name AS group_name FROM user u LEFT JOIN "group" g ON g.id = u.group_id '
            . $where . ' ORDER BY u.' . $sort . ' ' . $dir . ' LIMIT :limit OFFSET :offset'
        );
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
            'total' => (int)$total,
            'current' => (int)floor($offset / $limit) + 1,
            'rowCount' => $limit,
        ], JSON_UNESCAPED_UNICODE));
        break;

    case 'get':
        $db = db();
        $statement = $db->prepare(
            'SELECT u.*, g.name AS group_name FROM user u LEFT JOIN "group" g ON g.id = u.group_id WHERE u.id = :id'
        );
        $statement->bindValue(':id', (int)$argument, SQLITE3_INTEGER);
        $row = $statement->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row === false) {
            fail('kullanıcı bulunamadı: ' . $argument);
        }
        printf("%s\n", json_encode(['status' => 'ok', 'user' => row_out($row)], JSON_UNESCAPED_UNICODE));
        break;

    case 'save':
        $params = payload($argument);
        $id = (int)($params['uuid'] ?? 0);
        $values = [];
        foreach (FIELDS as $field) {
            if (!array_key_exists($field, $params)) {
                continue;
            }
            $value = $params[$field];
            if ($field === 'password' && (string)$value === '') {
                /* boş şifre = mevcut şifre korunur (form alanı boş gösterilir) */
                continue;
            }
            if (in_array($field, ['daily_limit', 'weekly_limit', 'monthly_limit', 'yearly_limit'], true)) {
                $values[$field] = (int)$value;
            } elseif ($field === 'expires') {
                $values[$field] = $value === '' ? 0 : (int)strtotime((string)$value);
            } elseif (in_array($field, ['gsm_permission', 'email_permission'], true)) {
                $values[$field] = $value === '1' || $value === 1 || $value === true ? 1 : 0;
            } elseif ($field === 'group_id') {
                $values[$field] = $value === '' ? null : (int)$value;
            } else {
                $values[$field] = (string)$value;
            }
        }
        if (empty($values)) {
            fail('yazılacak alan yok');
        }
        if (isset($values['username']) && trim($values['username']) === '') {
            fail('kullanıcı adı zorunlu');
        }
        if (isset($values['gsm']) && $values['gsm'] !== '' && !preg_match('/^[0-9]{10}$/', $values['gsm'])) {
            fail('GSM numarası 10 haneli olmalı (başında 0 olmadan)');
        }

        $db = db();
        if (isset($values['username'])) {
            $check = $db->prepare('SELECT id FROM user WHERE username = :u AND id <> :id');
            $check->bindValue(':u', $values['username'], SQLITE3_TEXT);
            $check->bindValue(':id', $id, SQLITE3_INTEGER);
            if ($check->execute()->fetchArray() !== false) {
                fail('aynı kullanıcı adına sahip bir kayıt zaten var');
            }
        }

        if ($id > 0) {
            $sets = [];
            foreach (array_keys($values) as $field) {
                $sets[] = '"' . $field . '" = :' . $field;
            }
            $statement = $db->prepare('UPDATE user SET ' . implode(', ', $sets) . ' WHERE id = :id');
            foreach ($values as $field => $value) {
                $statement->bindValue(':' . $field, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
            }
            $statement->bindValue(':id', $id, SQLITE3_INTEGER);
            $statement->execute();
        } else {
            $columns = array_keys($values);
            $statement = $db->prepare(
                'INSERT INTO user ("' . implode('","', $columns) . '") VALUES (:' . implode(', :', $columns) . ')'
            );
            foreach ($values as $field => $value) {
                $statement->bindValue(':' . $field, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
            }
            $statement->execute();
            $id = (int)$db->lastInsertRowID();
        }

        printf("%s\n", json_encode(['status' => 'ok', 'uuid' => (string)$id], JSON_UNESCAPED_UNICODE));
        break;

    case 'delete':
        $db = db();
        $statement = $db->prepare('DELETE FROM user WHERE id = :id');
        $statement->bindValue(':id', (int)$argument, SQLITE3_INTEGER);
        $statement->execute();
        printf("%s\n", json_encode(['status' => 'ok', 'deleted' => (int)$argument], JSON_UNESCAPED_UNICODE));
        break;

    default:
        fail('bilinmeyen komut: ' . $command);
}
