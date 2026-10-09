#!/bin/sh

# Zone şablon şimini üretir, core captive portal modeline yazar ve zone'a bağlar.
# configd tarafından root olarak çalıştırılır:
#   configctl monospot install_zonetemplate 0 8100 "Monospot Portal"
#
# Çıktı TEK SATIR JSON olmalıdır: configd action'ı `type:script_output` olduğu
# için arayüz bu çıktıyı doğrudan gösterir. Alt araçların stdout'u bu yüzden
# bastırılır, JSON her zaman son satırdan okunur.

ZONEID=""
PORT=""
NAME="Monospot Portal"

for arg in "$@"; do
    case "$arg" in
        --zoneid=*) ZONEID=${arg#--zoneid=} ;;
        --port=*)   PORT=${arg#--port=} ;;
        --name=*)   NAME=${arg#--name=} ;;
    esac
done

PY=/usr/local/bin/python3
PHP=/usr/local/bin/php
SCRIPTDIR=/usr/local/opnsense/scripts/monospot

fail()
{
    # $1: insan okur hata metni
    # Çıkış kodu 0: configd, sıfırdan farklı çıkışta kendi "Execute error"
    # mesajını gösterir ve JSON'umuz arayüze ulaşmaz. Hata bilgisi JSON'da ve
    # stderr'de taşınır.
    printf '%s\n' "$1" >&2
    printf '{"status":"failed","error":"%s"}\n' "$(printf '%s' "$1" | tr -d '"\\' | tr '\n' ' ')"
    exit 0
}

[ -n "$ZONEID" ] || fail "--zoneid ve --port zorunlu"
[ -n "$PORT" ] || fail "--zoneid ve --port zorunlu"

TMPZIP=$(mktemp /tmp/monospot-zonetemplate-XXXXXX)

if ! $PY "$SCRIPTDIR/build_zone_template.py" "$PORT" "$TMPZIP" > /dev/null 2>&1; then
    rm -f "$TMPZIP"
    fail "zone sablonu (zip) uretilemedi"
fi

RESULT=$($PHP "$SCRIPTDIR/save_zone_template.php" \
    --name="$NAME" --zip="$TMPZIP" --zoneid="$ZONEID" 2>&1)
rm -f "$TMPZIP"

# php uyarı basmış olabilir; karar için son satırdaki JSON'a bak
JSON=$(printf '%s\n' "$RESULT" | grep -v '^[[:space:]]*$' | tail -n 1)
case "$JSON" in
    '{"status":"ok"'*)
        ;;
    *)
        fail "${JSON:-$RESULT}"
        ;;
esac

# zone htdocs'u şablondan yeniden üretilir (rc.d overlay_template)
/usr/local/sbin/configctl template reload OPNsense/Captiveportal > /dev/null
/usr/local/sbin/configctl captiveportal restart > /dev/null

printf '%s\n' "$JSON"
