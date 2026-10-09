<?php

/**
 * Monospot 5651 arşiv erişim katmanı.
 *
 * Tarayıcı (index.php) ve tanı/CLI araçları aynı işlevleri kullanır; bu dosya
 * HTTP'ye bağlı değildir (guiconfig/session içermez).
 */

const ARCHIVE_ROOT = '/var/db/monospot/5651';
const DAY_FILES = ['identity.log', 'dhcp-leases.log', 'cp-sessions.log', 'manifest.sha256'];

/** Arşivdeki günler (en yeni önce). */
function archive_days()
{
    $days = [];
    if (!is_dir(ARCHIVE_ROOT)) {
        return $days;
    }
    foreach (scandir(ARCHIVE_ROOT) as $year) {
        if (!preg_match('/^\d{4}$/', $year)) {
            continue;
        }
        foreach (scandir(ARCHIVE_ROOT . '/' . $year) as $month) {
            if (!preg_match('/^\d{2}$/', $month)) {
                continue;
            }
            foreach (scandir(ARCHIVE_ROOT . '/' . $year . '/' . $month) as $day) {
                if (!preg_match('/^\d{2}$/', $day)) {
                    continue;
                }
                $days[] = $year . '-' . $month . '-' . $day;
            }
        }
    }
    rsort($days);
    return $days;
}

function day_path($date)
{
    return ARCHIVE_ROOT . '/' . str_replace('-', '/', $date);
}

/** Gün dizinindeki dosyalar: ad => [boyut, satır]. */
function day_files($date)
{
    $result = [];
    $path = day_path($date);
    if (!is_dir($path)) {
        return $result;
    }
    foreach (DAY_FILES as $name) {
        $file = $path . '/' . $name;
        if (!is_file($file)) {
            continue;
        }
        $lines = 0;
        if ($name !== 'manifest.sha256') {
            $handle = fopen($file, 'r');
            if ($handle !== false) {
                while (!feof($handle)) {
                    $lines += substr_count((string)fgets($handle), "\n");
                }
                fclose($handle);
            }
        }
        $result[$name] = [filesize($file), $lines];
    }
    return $result;
}

/** manifest.sha256 ile dosya özetlerini karşılaştırır: ad => bool. */
function integrity_check($date)
{
    $result = [];
    $path = day_path($date);
    $manifest = $path . '/manifest.sha256';
    if (!is_file($manifest)) {
        return $result;
    }
    foreach (file($manifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (!preg_match('/^([0-9a-f]{64})\s+(.+)$/', $line, $match)) {
            continue;
        }
        $file = $path . '/' . $match[2];
        $result[$match[2]] = is_file($file) && hash_file('sha256', $file) === $match[1];
    }
    return $result;
}

/** @return array{lines: string[], total: int, matched: int} */
function read_lines($date, $file, $query)
{
    $lines = [];
    $total = 0;
    $handle = fopen(day_path($date) . '/' . $file, 'r');
    if ($handle === false) {
        return ['lines' => $lines, 'total' => 0, 'matched' => 0];
    }
    while (($line = fgets($handle)) !== false) {
        $total++;
        if ($query !== '' && stripos($line, $query) === false) {
            continue;
        }
        $lines[] = rtrim($line, "\n");
    }
    fclose($handle);
    return ['lines' => $lines, 'total' => $total, 'matched' => count($lines)];
}

/** İndirilebilir/okunabilir gün dosyası adı mı? */
function valid_file($file)
{
    return in_array($file, DAY_FILES, true);
}

/** Geçerli arşiv günü mü (YYYY-AA-GG)? */
function valid_date($date)
{
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date);
}
