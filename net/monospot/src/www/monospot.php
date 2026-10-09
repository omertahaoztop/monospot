<?php

/**
 * Monospot yönetim paneli giriş noktası (OPNsense WebGUI).
 *
 * OPNsense oturumu doğrulanır ve ACL (page-monospot) kontrol edilir; ardından
 * plugin içindeki Limonade tabanlı panel include edilir. pfSense'te oturum
 * doğrulaması auth.inc/priv.inc ile yapılıyordu; burada core altyapısı kullanılır.
 */

require_once('guiconfig.inc');

// DİKKAT: ACL maske eşleşmesi URL'in '/' ile başlamasını şart koşar
// (ACL::urlMatch içindeki '@^/{mask}$@' deseni); bu yüzden SCRIPT_NAME verilir.
$a_acl = new \OPNsense\Core\ACL();

if (!$a_acl->isPageAccessible($_SESSION['Username'], $_SERVER['SCRIPT_NAME'])) {
    $username = htmlspecialchars($_SESSION['Username']);
    header('HTTP/1.1 403 Forbidden');
    echo "<html><body><h3>" . gettext('Access Denied') . "</h3>"
        . "<p>" . sprintf(gettext('User %s does not have access to the Monospot page.'), $username) . "</p>"
        . "<p><a href=\"/\">" . gettext('Back to dashboard') . "</a></p></body></html>";
    exit;
}

require '/usr/local/opnsense/scripts/monospot/app/index.php';
