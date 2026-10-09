#!/bin/sh
# Yerel test VM: portal ayar dosyalarını hazırla (hotspot.ini + mock SMS).
# Üretimde bu dosyalar operatör tarafından konur (bkz. net/monospot/README.md).
set -e

APP=/usr/local/opnsense/scripts/monospot/app
SMS_MOCK=${SMS_MOCK:-/root/sms.mock.php}

if [ ! -f "$APP/hotspot.ini" ]; then
    cp "$APP/hotspot.sample.ini" "$APP/hotspot.ini"
fi
sed -i '' -e 's/^demo_bitis = .*/demo_bitis = 01.01.2099/' "$APP/hotspot.ini"

if [ -f "$SMS_MOCK" ]; then
    cp "$SMS_MOCK" "$APP/sms.php"
    chown www:www "$APP/sms.php"
    chmod 600 "$APP/sms.php"
fi

chown www:www "$APP/hotspot.ini"
chmod 660 "$APP/hotspot.ini"

echo "--- hotspot.ini"
cat "$APP/hotspot.ini"
/usr/local/etc/rc.d/monospot restart
sleep 2
sockstat -4l | grep ':8100' || echo "8100 dinlenmiyor"
