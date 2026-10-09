#!/bin/sh

# Zone şablon şimini üretir, core captive portal modeline yazar ve zone'a bağlar.
# configd tarafından root olarak çalıştırılır:
#   configctl monospot install_zonetemplate --zoneid=0 --port=8100 --name="Monospot Portal"

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

if [ -z "$ZONEID" ] || [ -z "$PORT" ]; then
    echo '{"status":"failed","error":"--zoneid ve --port zorunlu"}'
    exit 1
fi

TMPZIP=$(mktemp /tmp/monospot-zonetemplate-XXXXXX)

/usr/local/bin/python3 /usr/local/opnsense/scripts/monospot/build_zone_template.py "$PORT" "$TMPZIP" || {
    rm -f "$TMPZIP"
    echo '{"status":"failed","error":"zone şablonu üretilemedi"}'
    exit 1
}

RESULT=$(/usr/local/bin/php /usr/local/opnsense/scripts/monospot/save_zone_template.php \
    --name="$NAME" --zip="$TMPZIP" --zoneid="$ZONEID" 2>&1)
STATUS=$?
rm -f "$TMPZIP"

if [ $STATUS -ne 0 ]; then
    echo "{\"status\":\"failed\",\"error\":$(printf '%s' "$RESULT" | /usr/local/bin/python3 -c 'import json,sys;print(json.dumps(sys.stdin.read()))')}"
    exit 1
fi

# zone htdocs'u şablondan yeniden üretilir (rc.d overlay_template)
/usr/local/sbin/configctl template reload OPNsense/Captiveportal > /dev/null
/usr/local/sbin/configctl captiveportal restart > /dev/null

printf '%s\n' "$RESULT"
