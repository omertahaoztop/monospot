<?php
/**
 * Yerel test VM: captive portal zone'unu devre dışı bırakır (CP akışı test
 * edilirken zone'un arayüzü kilitlediği durumu geri almak için).
 *
 * Kullanım: php /root/vm-disable-zone.php
 */

chdir('/usr/local/etc/inc');
require_once('config.inc');
require_once('util.inc');
require_once('filter.inc');

$cp = new \OPNsense\CaptivePortal\CaptivePortal();
$found = 0;
foreach ($cp->zones->zone->iterateItems() as $uuid => $zone) {
    $live = $cp->getByZoneID((string)$zone->zoneid);
    if ($live !== null) {
        $live->enabled = '0';
        echo "zone {$zone->zoneid} devre dışı\n";
        $found++;
    }
}
if ($found === 0) {
    echo "zone bulunamadı\n";
} else {
    $cp->serializeToConfig();
    \OPNsense\Core\Config::getInstance()->save();
}
echo "şimdi: configctl captiveportal restart; configctl filter reload\n";
