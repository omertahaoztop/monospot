<?php

/**
 * Monospot 5651 günlük arşivi tarayıcısı.
 *
 * OPNsense WebGUI oturumu + ACL (page-Monospot) arkasında çalışır; arşiv
 * dizinini (/var/db/monospot/5651) listeler, gün dosyalarını görüntüler,
 * indirir ve manifest.sha256 ile bütünlük kontrolü yapar.
 *
 *   https://<fw>/5651/                     gün listesi
 *   https://<fw>/5651/?d=2026-10-09        gün ayrıntısı
 *   https://<fw>/5651/?d=...&f=identity.log&q=...   satır arama
 *   https://<fw>/5651/?d=...&f=...&dl=1    indir
 */

require_once('guiconfig.inc');

$acl = new \OPNsense\Core\ACL();
if (!$acl->isPageAccessible($_SESSION['Username'], $_SERVER['SCRIPT_NAME'])) {
    header('HTTP/1.1 403 Forbidden');
    echo '<html><body><h3>' . gettext('Access Denied') . '</h3><p>'
        . sprintf(gettext('User %s does not have access to the 5651 archive.'), htmlspecialchars($_SESSION['Username']))
        . '</p><p><a href="/">' . gettext('Back to dashboard') . '</a></p></body></html>';
    exit;
}

require_once(__DIR__ . '/archive.php');

const PAGE_LINES = 200;

function human_size($bytes)
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    return number_format($bytes / 1024, 1, ',', '.') . ' KB';
}


/** HTML kaçışı. */
function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_date($date)
{
    [$year, $month, $day] = explode('-', $date);
    return $day . '.' . $month . '.' . $year;
}

$date = valid_date($_GET['d'] ?? '') ? $_GET['d'] : '';
$file = valid_file($_GET['f'] ?? '') ? $_GET['f'] : '';
$query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = isset($_GET['p']) && ctype_digit((string)$_GET['p']) ? max(1, (int)$_GET['p']) : 1;

/* indirme: doğrulanmış yol ve dosya adıyla akıt */
if ($date !== '' && $file !== '' && isset($_GET['dl']) && is_file(day_path($date) . '/' . $file)) {
    $download = day_path($date) . '/' . $file;
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $date . '-' . $file . '"');
    header('Content-Length: ' . filesize($download));
    readfile($download);
    exit;
}

$days = archive_days();
$files = $date !== '' ? day_files($date) : [];
$integrity = $date !== '' ? integrity_check($date) : [];
$view = ($date !== '' && $file !== '' && $file !== 'manifest.sha256') ? read_lines($date, $file, $query) : null;
$pageCount = $view === null ? 1 : max(1, (int)ceil($view['matched'] / PAGE_LINES));
$page = min($page, $pageCount);
$offset = ($page - 1) * PAGE_LINES;
$viewLines = $view === null ? [] : array_slice($view['lines'], $offset, PAGE_LINES);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Monospot 5651</title>
<style>
:root { --ink:#111417; --ink-2:#59616e; --paper:#fff; --shell:#eff1f4; --rule:#dfe3e8; --rule-strong:#c3cad2; --row:#f7f9fb; }
* { box-sizing: border-box; }
body { margin:0; background:var(--shell); color:var(--ink);
       font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif; }
a { color:var(--ink); }
header { background:var(--paper); border-bottom:1px solid var(--rule); padding:14px 20px;
         display:flex; align-items:baseline; gap:16px; flex-wrap:wrap; }
header h1 { font-size:16px; font-weight:600; margin:0; letter-spacing:-.01em; }
header nav { margin-left:auto; display:flex; gap:14px; font-size:14px; }
main { padding:20px; max-width:1100px; }
h2 { font-size:15px; font-weight:600; margin:22px 0 8px; }
h2:first-child { margin-top:0; }
table { width:100%; border-collapse:collapse; background:var(--paper);
        border:1px solid var(--rule); font-size:14px; }
th, td { text-align:left; padding:7px 10px; border-bottom:1px solid var(--rule); }
th { font-size:12px; letter-spacing:.06em; text-transform:uppercase; color:var(--ink-2); font-weight:600; }
tbody tr:nth-child(even) { background:var(--row); }
td.num { text-align:right; font-variant-numeric:tabular-nums; }
td.day { font-variant-numeric:tabular-nums; }
.ok { color:#14532d; font-weight:600; }
.bad { color:#7f1d1d; font-weight:600; }
pre { background:var(--paper); border:1px solid var(--rule); padding:12px; margin:0;
      overflow-x:auto; font:13px/1.55 ui-monospace,SFMono-Regular,"SF Mono",Menlo,Consolas,monospace; }
form.filters { display:flex; gap:8px; align-items:center; margin:0 0 12px; flex-wrap:wrap; }
input[type=text] { padding:7px 9px; border:1px solid var(--rule-strong); background:var(--paper);
                   font:inherit; font-size:14px; min-width:240px; }
button { padding:7px 12px; border:1px solid var(--ink); background:var(--ink); color:var(--paper);
         font:inherit; font-size:14px; cursor:pointer; }
.pager { margin:10px 0 0; font-size:14px; display:flex; gap:12px; align-items:center; }
.meta { color:var(--ink-2); font-size:13px; }
.empty { background:var(--paper); border:1px solid var(--rule); padding:16px; color:var(--ink-2); }
</style>
</head>
<body>
<header>
    <h1>Monospot 5651 Günlük Arşivi</h1>
    <nav>
        <a href="/ui/monospot">Servis ayarları</a>
        <a href="/monospot.php">Yönetim paneli</a>
        <a href="/">OPNsense</a>
    </nav>
</header>
<main>
<?php if ($date === ''): ?>
    <h2>Arşivlenen günler (<?= count($days) ?>)</h2>
    <?php if (empty($days)): ?>
        <p class="empty">Arşiv boş. Günlük toplama <code><?= h(ARCHIVE_ROOT) ?></code> altına yazar;
           elle çalıştırmak için <code>configctl monospot collect5651</code> kullanılır.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Gün</th><th>Kayıt</th><th>DHCP kirası</th><th>Oturum</th><th>Bütünlük</th></tr></thead>
        <tbody>
        <?php foreach ($days as $day): ?>
            <?php $dayFiles = day_files($day); $check = integrity_check($day); ?>
            <?php $bad = count(array_filter($check, fn($ok) => !$ok)); ?>
            <tr>
                <td class="day"><a href="?d=<?= h($day) ?>"><?= h(format_date($day)) ?></a></td>
                <?php foreach (['identity.log', 'dhcp-leases.log', 'cp-sessions.log'] as $name): ?>
                    <td class="num"><?= isset($dayFiles[$name]) ? (int)$dayFiles[$name][1] . ' satır' : '—' ?></td>
                <?php endforeach; ?>
                <td><?php if (empty($check)): ?><span class="meta">manifest yok</span>
                    <?php elseif ($bad === 0): ?><span class="ok">✓ doğrulandı</span>
                    <?php else: ?><span class="bad">✗ <?= (int)$bad ?> dosya uyuşmuyor</span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
<?php else: ?>
    <h2>Gün: <?= h(format_date($date)) ?></h2>
    <?php if (empty($files)): ?>
        <p class="empty">Bu gün için arşiv dosyası bulunamadı. <a href="?">Gün listesine dön</a></p>
    <?php else: ?>
    <table>
        <thead><tr><th>Dosya</th><th>Boyut</th><th>Satır</th><th>Bütünlük</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($files as $name => [$size, $lines]): ?>
            <tr>
                <td><?= h($name) ?></td>
                <td class="num"><?= h(human_size($size)) ?></td>
                <td class="num"><?= $name === 'manifest.sha256' ? '—' : (int)$lines ?></td>
                <td>
                    <?php if (!isset($integrity[$name])): ?><span class="meta">—</span>
                    <?php elseif ($integrity[$name]): ?><span class="ok">✓</span>
                    <?php else: ?><span class="bad">✗</span><?php endif; ?>
                </td>
                <td>
                    <?php if ($name !== 'manifest.sha256'): ?>
                        <a href="?d=<?= h($date) ?>&amp;f=<?= h($name) ?>">görüntüle</a>
                    <?php endif; ?>
                    <a href="?d=<?= h($date) ?>&amp;f=<?= h($name) ?>&amp;dl=1">indir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="meta"><a href="?">← Gün listesi</a></p>

    <?php if ($view !== null): ?>
        <h2><?= h($file) ?><?= $query !== '' ? ' — "' . h($query) . '" (' . (int)$view['matched'] . ' eşleşme)' : '' ?></h2>
        <form class="filters" method="get" action="">
            <input type="hidden" name="d" value="<?= h($date) ?>">
            <input type="hidden" name="f" value="<?= h($file) ?>">
            <input type="text" name="q" value="<?= h($query) ?>" placeholder="satırlarda ara (MAC, IP, kullanıcı)">
            <button type="submit">Ara</button>
            <?php if ($query !== ''): ?><a href="?d=<?= h($date) ?>&amp;f=<?= h($file) ?>">aramayı temizle</a><?php endif; ?>
        </form>
        <?php if (empty($viewLines)): ?>
            <p class="empty">Kayıt yok.</p>
        <?php else: ?>
            <pre><?php foreach ($viewLines as $line) { echo h($line) . "\n"; } ?></pre>
            <?php if ($pageCount > 1): ?>
            <div class="pager">
                <?php if ($page > 1): ?><a href="?d=<?= h($date) ?>&amp;f=<?= h($file) ?>&amp;q=<?= urlencode($query) ?>&amp;p=<?= $page - 1 ?>">← önceki</a><?php endif; ?>
                <span class="meta">sayfa <?= $page ?> / <?= $pageCount ?> (<?= (int)$view['matched'] ?> satır)</span>
                <?php if ($page < $pageCount): ?><a href="?d=<?= h($date) ?>&amp;f=<?= h($file) ?>&amp;q=<?= urlencode($query) ?>&amp;p=<?= $page + 1 ?>">sonraki →</a><?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
