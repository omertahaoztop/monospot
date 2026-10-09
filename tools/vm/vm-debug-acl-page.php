<?php
/**
 * Yerel VM teşhis: /monospot.php ACL kontrolünün HTTP bağlamındaki sonucu.
 * Kullanım: oturum açmış bir istemciyle https://<fw>/monospot-debug.php
 */
require_once('guiconfig.inc');

header('Content-Type: text/plain; charset=utf-8');

$a_acl = new \OPNsense\Core\ACL();

echo 'session user: ' . var_export($_SESSION['Username'] ?? null, true) . "\n";
echo 'should change pw: ' . var_export($_SESSION['user_shouldChangePassword'] ?? null, true) . "\n";
echo 'script name: ' . ($_SERVER['SCRIPT_NAME'] ?? '-') . "\n";
echo 'masks: ' . "\n";
foreach ($a_acl->userUrlMasks($_SESSION['Username'] ?? '') as $mask) {
    echo '  - ' . json_encode($mask) . "\n";
}
echo 'accessible(monospot.php): ' . var_export($a_acl->isPageAccessible($_SESSION['Username'] ?? '', 'monospot.php'), true) . "\n";
echo 'accessible(/monospot.php): ' . var_export($a_acl->isPageAccessible($_SESSION['Username'] ?? '', '/monospot.php'), true) . "\n";
