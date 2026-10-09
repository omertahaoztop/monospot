<?php
/**
 * Yerel test VM: root kullanıcısına "admins" grubunu ekler.
 *
 * Neden: VM, GUI ilk-kurulum sihirbazı atlanarak kurulduğu için root hiçbir
 * gruba üye değil; bu durumda `page-*` ACL'leri eşleşmiyor ve Monospot yönetim
 * paneli 403 veriyor. Üretimde kullanıcı/grup ataması GUI'den yapılır.
 *
 * Kullanım: php /root/vm-enable-admin.php
 */

chdir('/usr/local/etc/inc');
require_once('config.inc');
require_once('util.inc');

global $config;

$users = $config['system']['user'];
if (!is_array($users)) {
    $users = array($users);
}

$changed = false;
foreach ($users as $i => $user) {
    if ($user['name'] !== 'root') {
        continue;
    }
    $groups = isset($user['groupname']) ? $user['groupname'] : array();
    if (!is_array($groups)) {
        $groups = array($groups);
    }
    if (!in_array('admins', $groups, true)) {
        $groups[] = 'admins';
        $users[$i]['groupname'] = $groups;
        $changed = true;
        echo "root -> admins eklendi\n";
    } else {
        echo "root zaten admins üyesi\n";
    }
}
$config['system']['user'] = $users;

if ($changed) {
    write_config('monospot test vm: root to admins group');
    echo "kaydedildi\n";
}
