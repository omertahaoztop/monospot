#!/bin/sh

# Copyright (C) 2026 Monobilisim
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice,
#    this list of conditions and the following disclaimer in the documentation
#    and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
# AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
# OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
# INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
# CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.

# Monospot çalışma zamanı hazırlığı: dizinler, izinler, ayar dosyaları.
# Portal lighttpd'si ve yönetim paneli aynı kullanıcıyla (www) çalışır; bu
# yüzden tüm yazılabilir yüzeyler www sahipliğinde tutulur.

APP=/usr/local/opnsense/scripts/monospot/app
CONF=/var/etc/lighttpd-monospot.conf

case "$1" in
deploy)
    # lighttpd /var/log altında dosya oluşturamaz: önceden hazırla
    for lf in /var/log/monospot-error.log /var/log/monospot-access.log; do
        [ -f "$lf" ] || : > "$lf"
        chown www:www "$lf"
        chmod 640 "$lf"
    done

    # php-cgi oturumları: core /var/lib/php/sessions wwwonly:750 (www kullanamaz)
    mkdir -p /var/lib/php/monospot-sessions
    chown www:www /var/lib/php/monospot-sessions
    chmod 750 /var/lib/php/monospot-sessions

    # fastcgi soket dizini (www yazabilmeli)
    mkdir -p /var/run/monospot
    chown www:www /var/run/monospot
    chmod 750 /var/run/monospot

    # uygulama veri dizinleri
    mkdir -p "$APP/db" "$APP/media"
    for pair in "settings.inc:settings.sample.inc" "hotspot.ini:hotspot.sample.ini" \
                "db/hotspot.db:db/hotspot.sample.db" "lang/tr.inc:lang/tr.sample.inc" \
                "lang/en.inc:lang/en.sample.inc"; do
        dst="$APP/${pair%%:*}"
        src="$APP/${pair##*:}"
        if [ ! -f "$dst" ] && [ -f "$src" ]; then
            cp "$src" "$dst"
            echo "Created $dst from sample"
        fi
    done

    chown -R www:www "$APP/db" "$APP/media" "$APP/lang"
    chown www:www "$APP/settings.inc" "$APP/hotspot.ini" "$APP/db/hotspot.db" 2> /dev/null
    chmod 770 "$APP/db" "$APP/lang" "$APP/media"
    chmod 660 "$APP/settings.inc" "$APP/hotspot.ini" "$APP/db/hotspot.db" 2> /dev/null
    chmod 660 "$APP/lang"/*.inc 2> /dev/null

    # portal docroot'u: lighttpd worker (www) dizinleri gezebilmeli
    chmod 555 "$APP"

    # panel varlıkları core WebGUI'nin /ui/ alias'ından sunulur; portal ise
    # kendi docroot'undan (app/assets) sunar — tek kopya, iki yüzey.
    ln -sfn "$APP/assets" /usr/local/opnsense/www/monospot-assets

    if [ -f "$CONF" ]; then
        /usr/local/sbin/lighttpd -tt -f "$CONF"
    fi
    ;;
*)
    echo "usage: $0 deploy"
    exit 1
    ;;
esac
