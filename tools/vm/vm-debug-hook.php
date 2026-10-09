<?php
/**
 * Yerel VM teşhis: monospot hook'u çalışıyor mu, portal kuralı üretiliyor mu?
 * Kullanım: php /root/vm-debug-hook.php
 */
chdir('/usr/local/etc/inc');
require_once('config.inc');
require_once('util.inc');
require_once('filter.inc');
require_once('plugins.inc.d/monospot.inc');

echo "--- monospot_general()\n";
print_r(monospot_general());

echo "--- monospot_zone_interfaces('0')\n";
var_dump(monospot_zone_interfaces('0'));

echo "--- monospot_services()\n";
print_r(monospot_services());

echo "--- hook keşfi (plugins.inc)\n";
echo "monospot.inc yolu: " . (file_exists('/usr/local/etc/inc/plugins.inc.d/monospot.inc') ? 'var' : 'yok') . "\n";

echo "--- fw nesnesiyle kural üretimi\n";
$fw = new \OPNsense\Firewall\Plugin();
monospot_firewall($fw);
print_r($fw->getFilterRules());
