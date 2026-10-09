#!/bin/sh
# OPNsense live medyadan diske ZFS kurulumu (tmux konsol otomasyonu).
# Ön koşul: tools/vm/opnsense-vm.sh run (VM çalışıyor olmalı).
set -e
V=$(dirname "$0")/opnsense-vm.sh

say() { printf '== %s\n' "$1"; }
step() { say "$1"; }
w() { "$V" waitfor "$1" "${2:-180}" > /dev/null; }
s() { "$V" send "$@" > /dev/null; sleep 2; }

step "import scripti atla"
w "configuration importer|Press any key" 300; s Enter
if "$V" waitfor "Select device to import" 30 > /dev/null 2>&1; then
    say "importer çıktı (boş bırak)"
    s Enter
fi

step "arayüz atama sihirbazı"
w "configure LAGGs" 300; s "n" Enter
w "configure VLANs" 60; s "n" Enter
w "WAN interface name" 60; s "vtnet0" Enter
w "LAN interface name" 60; s "vtnet1" Enter
w "Optional interface 1" 60; s Enter
w "Do you want to proceed" 60; s "y" Enter

step "installer hesabına gir"
w "login:" 300; s "installer" Enter
w "Password" 120; s "opnsense" Enter

step "klavye seçimi (varsayılan)"
w "Select.*Cancel|Canadian Bilingual" 180; s Enter

step "Install (ZFS)"
w "Install \(ZFS\)" 180; s Enter

step "RAM uyarısı (proceed anyway)"
if "$V" waitfor "Proceed anyway" 60 > /dev/null 2>&1; then s Enter; fi

step "ZFS havuz tipi (stripe varsayılan)"
w "stripe|mirror|raid10|Pool Type|ZFS Configuration" 180; s Enter

step "disk seçimi"
w "ada0|Disk Selection|Select Disk" 180; s Enter

step "onay"
w "OK.*Cancel|Confirmation|This will destroy|are you sure" 120; s Enter

step "kurulum sürüyor"
w "Installation complete|completed|Reboot|Exit" 1800
say "kurulum bitti (konsolu kontrol et)"
"$V" console 30
