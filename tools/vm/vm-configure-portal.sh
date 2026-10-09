#!/bin/sh
# Yerel test VM: portalı tarayıcı testine hazırlar.
#
#  - hotspot.ini: demo_bitis ileri bir tarihe çekilir (demo banner'ı görünmesin)
#  - settings.inc: test kolaylığı için çoklu giriş/aralık limitleri gevşetilir
#    (aynı istemci IP'siyle tekrar tekrar kayıt olunabilsin; üretimde varsayılan kalır)
#  - db/hotspot.db şema örneğinden yeniden üretilir (temiz kullanıcı listesi)
#  - mock SMS sağlayıcısı kurulur (kod /tmp/monospot-sms.log'a yazılır)
#
# Kullanım: sh vm-configure-portal.sh [mock_sms_dosyası]
set -e

APP=/usr/local/opnsense/scripts/monospot/app
SMS_MOCK=${1:-/root/sms.mock.php}

resets=$(cat <<'PHP'
$f = __DIR__ . '/settings.inc';
$s = file_get_contents($f);
$s = preg_replace("/'disallow_multiple_logins' => '1'/", "'disallow_multiple_logins' => '0'", $s);
$s = preg_replace("/'disallow_multiple_logins_for' => '\d+'/", "'disallow_multiple_logins_for' => '0'", $s);
$s = preg_replace("/'min_interval' => '\d+'/", "'min_interval' => '0'", $s);
$s = preg_replace("/'daily_limit' => '\d+'/", "'daily_limit' => '50'", $s);
$s = preg_replace("/'daily_global_limit' => '\d+'/", "'daily_global_limit' => '5000'", $s);
file_put_contents($f, $s);
echo "settings.inc test için gevşetildi\n";
PHP
)

if [ ! -f "$APP/hotspot.ini" ]; then
    cp "$APP/hotspot.sample.ini" "$APP/hotspot.ini"
fi
sed -i '' -e 's/^demo_bitis = .*/demo_bitis = 01.01.2099/' "$APP/hotspot.ini" 2>/dev/null || \
    php -r '$f="'"$APP"'/hotspot.ini"; file_put_contents($f, preg_replace("/^demo_bitis = .*/m", "demo_bitis = 01.01.2099", file_get_contents($f)));'

# temiz veritabanı
cp "$APP/db/hotspot.sample.db" "$APP/db/hotspot.db"

# test limitleri
php -r "chdir('$APP'); $resets"

if [ -f "$SMS_MOCK" ]; then
    cp "$SMS_MOCK" "$APP/sms.php"
    chmod 600 "$APP/sms.php"
fi

chown -R www:www "$APP/db" "$APP/lang" "$APP/media" 2>/dev/null
chown www:www "$APP/hotspot.ini" "$APP/settings.inc" "$APP/db/hotspot.db" "$APP/sms.php" 2>/dev/null
chmod 660 "$APP/hotspot.ini" "$APP/settings.inc" "$APP/db/hotspot.db" 2>/dev/null

/usr/local/etc/rc.d/monospot restart >/dev/null 2>&1 || /usr/local/etc/rc.d/monospot onestart >/dev/null 2>&1 || true
sleep 2
sockstat -4l | grep -q ':8100' && echo "portal dinliyor: :8100" || echo "UYARI: portal :8100 dinlemiyor"
tail -3 "$APP/settings.inc"
