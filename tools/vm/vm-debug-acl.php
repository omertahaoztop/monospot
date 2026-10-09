<?php
/**
 * Yerel VM teşhis: root kullanıcısının ACL maskeleri ve sayfa erişimi.
 * Kullanım: php /root/vm-debug-acl.php
 */
chdir('/usr/local/etc/inc');
require_once('config.inc');
require_once('util.inc');

global $config;
foreach ((array)$config['system']['user'] as $user) {
    echo "user={$user['name']} groups=" . json_encode($user['groupname'] ?? null) . "\n";
}

$acl = new \OPNsense\Core\ACL();
echo "root maskeleri:\n";
foreach ($acl->userUrlMasks('root') as $mask) {
    echo "  - $mask\n";
}
var_dump($acl->isPageAccessible('root', '/monospot.php'));
