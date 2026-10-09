#!/bin/sh
# Yerel test VM: monospot config'ini yaz, şablonları tazele, portalı başlat.
set -e
PORT=${1:-8100}

php /root/vm-enable-monospot.php "$PORT"
configctl template reload OPNsense/Monospot
echo "--- rc.conf.d/monospot"
cat /etc/rc.conf.d/monospot
echo "--- start"
/usr/local/etc/rc.d/monospot start
sleep 3
echo "--- listen"
sockstat -4l | grep -E ':8100' || echo "8100 dinlenmiyor"
echo "--- error log"
tail -20 /var/log/monospot-error.log 2>/dev/null || echo "error log yok"
