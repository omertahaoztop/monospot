<?php
/**
 * Test SMS sağlayıcısı (yerel VM / geliştirme).
 *
 * Portal akışını gerçek bir SMS operatörü olmadan doğrulamak için kullanılır:
 * gönderilen şifreyi /tmp/monospot-sms.log dosyasına yazar. Üretimde operatörün
 * gerçek sms.php dosyası bu dizine konur (bkz. app/sms/*.php).
 */

function send_sms($user, $password, $mac = null)
{
    $gsm = isset($user->gsm) ? $user->gsm : '?';
    $line = sprintf(
        "%s\tgsm=%s\tmac=%s\tpassword=%s\n",
        date('Y-m-d H:i:s'),
        $gsm,
        $mac === null ? '-' : $mac,
        $password
    );
    file_put_contents('/tmp/monospot-sms.log', $line, FILE_APPEND);
    return true;
}
