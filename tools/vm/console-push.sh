#!/bin/sh
# Yerel bir dosyayı tmux konsolu üzerinden VM'e yazar (lab modunda SSH yokken).
# Kullanım: console-push.sh <yerel dosya> <uzak yol> [satır-aralığı-saniye]
#
# Neden satır satır: OPNsense konsolunda tcsh var ve heredoc içeriği hızlı
# yapıştırıldığında satır kaybı oluyor (ölçüldü: 21-26 satır). Bu yüzden içerik
# satır satır, kısa bir gecikmeyle gönderilir ve sonunda satır sayısı doğrulanır.
#
# Not: heredoc ayracı TIRNAKLI yazılmalı (`<<'PUSHEOF'`), aksi halde tcsh
#      ayracı kapatmaz.
set -e
V=$(dirname "$0")/opnsense-vm.sh
SESSION=${SESSION:-opnsensevm}
LOCAL=$1
REMOTE=$2
DELAY=${3:-0.02}

[ -f "$LOCAL" ] || { echo "yerel dosya yok: $LOCAL"; exit 1; }
LOCAL_LINES=$(wc -l < "$LOCAL")

LINEBUF=$(mktemp /tmp/console-line-XXXXXX)
trap 'rm -f "$LINEBUF"' EXIT

"$V" send "cat > $REMOTE <<'PUSHEOF'" Enter
sleep 1

# Satırlar tmux *buffer* ile yapıştırılır: `send-keys -l` satır sonundaki ';'
# karakterini düşürüyor (ölçüldü), paste-buffer içeriği birebir gönderir.
while IFS= read -r line || [ -n "$line" ]; do
    printf '%s\n' "$line" > "$LINEBUF"
    tmux load-buffer -b linebuf "$LINEBUF"
    tmux paste-buffer -b linebuf -t "$SESSION"
    sleep "$DELAY"
done < "$LOCAL"

sleep 1
"$V" send "'PUSHEOF'" Enter
sleep 2
"$V" send "wc -l $REMOTE" Enter
sleep 3

echo "yerel satır sayısı: $LOCAL_LINES (uzak değer konsolda; eşit olmalı)"
"$V" console 6
