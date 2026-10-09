<?php
/**
 * Yerel test VM: WAN üzerinden yönetim erişimi (SSH/HTTPS) için kural ekler.
 *
 * Neden: CP zone etkinken zone arayüzünde (LAN) yalnız portal portu açıktır;
 * lab modunda LAN gerçek L2 segmentine taşındığı için yönetim WAN tarafından
 * yapılır (qemu slirp hostfwd: 2222→22, 8443→443).
 *
 * Config, legacy dizi yerine SimpleXML üzerinden düzenlenir (OPNsense 26.7).
 * Kullanım: php /root/vm-enable-wanmgmt.php && configctl filter reload
 */

chdir('/usr/local/etc/inc');
require_once('config.inc');
require_once('util.inc');

$descr = 'monospot test vm management';
$cnf = \OPNsense\Core\Config::getInstance()->object();

foreach ($cnf->filter->rule as $existing) {
    if ((string)$existing->descr === $descr) {
        echo "kural zaten var\n";
        exit(0);
    }
}

$rule = $cnf->filter->rule->addChild('rule');
$rule->addChild('interface', 'wan');
$rule->addChild('type', 'pass');
$rule->addChild('ipprotocol', 'inet');
$rule->addChild('protocol', 'tcp');
$rule->addChild('source')->addChild('any');
$dest = $rule->addChild('destination');
$dest->addChild('network', '(self)');
$dest->addChild('port', '22,443');
$rule->addChild('descr', $descr);

\OPNsense\Core\Config::getInstance()->save();
echo "WAN yönetim kuralı kaydedildi (tcp 22,443)\n";
