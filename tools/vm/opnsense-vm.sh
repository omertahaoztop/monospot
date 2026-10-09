#!/bin/sh
# OPNsense yerel test VM (QEMU/KVM, başsız, SERİ konsol tmux içinde).
#
# Konsol tmux oturumu "opnsensevm" içinde çalışır: `console` ile okuyabilir,
# `send` ile tuş gönderebilir, `waitfor` ile bir kalıbı bekleyebilirsin.
#
# Kullanım: opnsense-vm.sh {create|run|stop|ssh|snapshot|revert|status|console|send|waitfor}
# Not: kurulum lazy-image ile "live mode" başlar; kalıcı kurulum için konsolda
#      'installer' hesabına girip ZFS kurulumu yapılır (bkz. README.md).
set -e

VM_DIR=${VM_DIR:-$HOME/vms/opnsense-test}
IMG_BZ2=$VM_DIR/OPNsense-26.7-serial-amd64.img.bz2
BASE_QCOW2=$VM_DIR/base.qcow2
DISK=$VM_DIR/disk.qcow2
TARGET=$VM_DIR/target.qcow2
LOG=$VM_DIR/console.log
SESSION=opnsensevm
CLIENT_SESSION=alpineclient
CLIENT_LOG=$VM_DIR/client-console.log
ALPINE_ISO=$VM_DIR/alpine-virt.iso
MCAST=${MCAST:-230.0.0.1:12345}
DISK_SIZE=12G
MEM=${MEM:-2048}
CPUS=${CPUS:-2}
GUEST_LAN=192.168.1.1

# Disk yerleşimi:
#   installer imajı (DISK)  -> ada0, canlı ortam; kurulum hedefi DEĞİL
#   target.qcow2 (TARGET)   -> ada1, kurulum hedefi; kurulumdan sonra ada0 olarak boot edilir
# (OPNsense kurucusu canlı medyanın bulunduğu diski hedef olarak görmez.)
qemu_args() {
    mode=${1:-installed}
    if [ "$mode" = "installer" ]; then
        disks="-drive file=$DISK,format=qcow2,if=ide,index=0 -drive file=$TARGET,format=qcow2,if=ide,index=1"
    else
        disks="-drive file=$TARGET,format=qcow2,if=ide,index=0"
    fi
    dev_lan="-device virtio-net-pci,netdev=lan,mac=52:54:00:00:01:01"
    dev_wan="-device virtio-net-pci,netdev=wan,mac=52:54:00:00:01:02"
    if [ "$mode" = "lab" ]; then
        # Lab: LAN gerçek L2 (mcast segmenti, Alpine istemci VM ile paylaşılır),
        # WAN slirp (internet) ve yönetim erişimi WAN tarafından forward edilir.
        netdev_lan="-netdev socket,id=lan,mcast=$MCAST"
        netdev_wan="-netdev user,id=wan,hostfwd=tcp:127.0.0.1:2222-10.0.2.15:22,hostfwd=tcp:127.0.0.1:8443-10.0.2.15:443"
    else
        netdev_lan="-netdev user,id=lan,net=192.168.1.0/24,hostfwd=tcp:127.0.0.1:2222-$GUEST_LAN:22,hostfwd=tcp:127.0.0.1:8443-$GUEST_LAN:443,hostfwd=tcp:127.0.0.1:8000-$GUEST_LAN:8000,hostfwd=tcp:127.0.0.1:8100-$GUEST_LAN:8100,hostfwd=tcp:127.0.0.1:8101-$GUEST_LAN:8101"
        netdev_wan="-netdev user,id=wan"
    fi
    nets="$netdev_lan $dev_lan $netdev_wan $dev_wan"
    echo "-machine q35,accel=kvm -cpu host -smp $CPUS -m $MEM" \
         "$disks" $nets \
         "-nographic -no-reboot"
}

case "$1" in
create)
    [ -f "$IMG_BZ2" ] || { echo "indir: $IMG_BZ2 yok"; exit 1; }
    if [ ! -f "$BASE_QCOW2" ]; then
        echo "-> base.qcow2 üretiliyor"
        bzip2 -dc "$IMG_BZ2" > "$VM_DIR/serial.img"
        qemu-img convert -f raw -O qcow2 "$VM_DIR/serial.img" "$BASE_QCOW2"
        rm -f "$VM_DIR/serial.img"
    fi
    if [ ! -f "$DISK" ]; then
        echo "-> disk.qcow2 (installer imajı, $DISK_SIZE)"
        rm -f "$DISK"
        qemu-img convert -O qcow2 "$BASE_QCOW2" "$DISK"
        qemu-img resize "$DISK" "$DISK_SIZE"
    fi
    if [ ! -f "$TARGET" ]; then
        echo "-> target.qcow2 (kurulum hedefi, $DISK_SIZE)"
        qemu-img create -f qcow2 "$TARGET" "$DISK_SIZE"
    fi
    qemu-img info "$DISK" | head -5
    qemu-img info "$TARGET" | head -5
    ;;
run)
    tmux has-session -t "$SESSION" 2>/dev/null && { echo "zaten çalışıyor"; exit 0; }
    # shellcheck disable=SC2046
    tmux new-session -d -s "$SESSION" -x 200 -y 50 \
        "qemu-system-x86_64 $(qemu_args installed) 2>&1 | tee $LOG"
    sleep 3
    tmux has-session -t "$SESSION" && echo "başlatıldı (kurulu sistem, tmux: $SESSION)"
    ;;
run-lab)
    tmux has-session -t "$SESSION" 2>/dev/null && { echo "zaten çalışıyor"; exit 0; }
    # shellcheck disable=SC2046
    tmux new-session -d -s "$SESSION" -x 200 -y 50 \
        "qemu-system-x86_64 $(qemu_args lab) 2>&1 | tee $LOG"
    sleep 3
    tmux has-session -t "$SESSION" && echo "başlatıldı (lab: LAN mcast, yönetim WAN'dan)"
    ;;
run-installer)
    tmux has-session -t "$SESSION" 2>/dev/null && { echo "zaten çalışıyor"; exit 0; }
    # shellcheck disable=SC2046
    tmux new-session -d -s "$SESSION" -x 200 -y 50 \
        "qemu-system-x86_64 $(qemu_args installer) 2>&1 | tee $LOG"
    sleep 3
    tmux has-session -t "$SESSION" && echo "başlatıldı (kurulum medyası, tmux: $SESSION)"
    ;;
client-run)
    tmux has-session -t "$CLIENT_SESSION" 2>/dev/null && { echo "istemci zaten çalışıyor"; exit 0; }
    [ -f "$ALPINE_ISO" ] || { echo "indir: $ALPINE_ISO yok"; exit 1; }
    tmux new-session -d -s "$CLIENT_SESSION" -x 200 -y 50 \
        "qemu-system-x86_64 -machine q35,accel=kvm -cpu host -smp 1 -m 512 \
            -cdrom $ALPINE_ISO \
            -netdev socket,id=lan,mcast=$MCAST -device virtio-net-pci,netdev=lan,mac=52:54:00:00:02:01 \
            -netdev user,id=mgmt -device virtio-net-pci,netdev=mgmt,mac=52:54:00:00:02:02 \
            -nographic -no-reboot 2>&1 | tee $CLIENT_LOG"
    sleep 3
    tmux has-session -t "$CLIENT_SESSION" && echo "istemci başlatıldı (tmux: $CLIENT_SESSION)"
    ;;
client-stop)
    tmux has-session -t "$CLIENT_SESSION" 2>/dev/null && tmux kill-session -t "$CLIENT_SESSION"
    echo "istemci durduruldu"
    ;;
client-console)
    tmux capture-pane -p -t "$CLIENT_SESSION" | sed -e 's/[[:space:]]*$//' | grep -v '^$' | tail -"${2:-40}"
    ;;
client-send)
    shift
    tmux send-keys -t "$CLIENT_SESSION" "$@"
    ;;
lab-up)
    "$0" stop >/dev/null 2>&1 || true
    "$0" run-lab
    "$0" client-run
    ;;
stop)
    tmux has-session -t "$SESSION" 2>/dev/null && tmux kill-session -t "$SESSION"
    echo "durduruldu"
    ;;
ssh)
    shift
    exec ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p 2222 root@127.0.0.1 "$@"
    ;;
console)
    tmux capture-pane -p -t "$SESSION" | sed -e 's/[[:space:]]*$//' | grep -v '^$' | tail -"${2:-40}"
    ;;
send)
    shift
    tmux send-keys -t "$SESSION" "$@"
    ;;
waitfor)
    pattern=$2
    timeout=${3:-120}
    waited=0
    while [ "$waited" -lt "$timeout" ]; do
        if tmux capture-pane -p -t "$SESSION" | grep -qE "$pattern"; then
            echo "görüldü: $pattern"; exit 0
        fi
        sleep 2
        waited=$((waited + 2))
    done
    echo "zaman aşımı: $pattern"; exit 1
    ;;
snapshot)
    "$0" stop
    qemu-img snapshot -c "${2:-pristine}" "$TARGET"
    qemu-img snapshot -l "$TARGET"
    ;;
revert)
    "$0" stop
    qemu-img snapshot -a "${2:-pristine}" "$TARGET"
    echo "geri alındı: ${2:-pristine}"
    ;;
status)
    tmux has-session -t "$SESSION" 2>/dev/null && echo "çalışıyor" || echo "kapalı"
    ;;
*)
    echo "kullanım: $0 {create|run-installer|run|run-lab|lab-up|client-run|client-stop|client-console|client-send|stop|ssh|snapshot|revert|status|console|send|waitfor}"; exit 1
    ;;
esac
