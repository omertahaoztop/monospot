<?php
/**
 * Yerel test VM hazırlığı: captive portal zone 0'ı (lan) etkinleştirir ve
 * Monospot modelini açar. Üretimde bu adımlar GUI'den yapılır; bu betik
 * yalnızca yerel doğrulama döngüsünü hızlandırmak için vardır.
 *
 * Kullanım (OPNsense içinde): php /root/vm-enable-monospot.php [portal_port]
 */

require_once('config.inc');
require_once('util.inc');

$port = isset($argv[1]) ? $argv[1] : '8100';

// 1) captive portal zone 0 (lan) yok ise oluştur, varsa etkinleştir
$cp = new \OPNsense\CaptivePortal\CaptivePortal();
$zone = $cp->getByZoneID('0');
if ($zone === null) {
    $zone = $cp->zones->zone->Add();
    $zone->zoneid = '0';
    $zone->description = 'monospot-test';
}
$zone->enabled = '1';
$zone->interfaces = 'lan';
$zone->authservers = '';
$errors = [];
foreach ($cp->performValidation() as $field => $message) {
    $errors[] = $field . ': ' . $message;
}
if (!empty($errors)) {
    fwrite(STDERR, "captive portal doğrulama hatası:\n" . implode("\n", $errors) . "\n");
    exit(1);
}
$cp->serializeToConfig();

// 2) monospot servis ayarları
$mdl = new \OPNsense\Monospot\Monospot();
$mdl->general->enabled = '1';
$mdl->general->zoneid = '0';
$mdl->general->portal_port = $port;
$errors = [];
foreach ($mdl->performValidation() as $field => $message) {
    $errors[] = $field . ': ' . $message;
}
if (!empty($errors)) {
    fwrite(STDERR, "monospot doğrulama hatası:\n" . implode("\n", $errors) . "\n");
    exit(1);
}
$mdl->serializeToConfig();

\OPNsense\Core\Config::getInstance()->save();
echo "config yazıldı: zone 0 + monospot (port $port)\n";
