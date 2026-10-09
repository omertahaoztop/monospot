#!/usr/local/bin/python3
"""
Monospot 5651 günlük arşivi.

Bir günün kimlik/oturum kayıtlarını tek dizinde toplar:

    /var/db/monospot/5651/<yıl>/<ay>/<gün>/
        identity.log      portal giriş kayıtları (uygulama canlı yazar)
        dhcp-leases.log   DHCP kira tablosu anlık görüntüsü (IP ↔ MAC ↔ zaman)
        cp-sessions.log   captive portal oturum listesi (yetkili istemciler)
        manifest.sha256   dosya özetleri (ileride imzalama için temel)

Kaynaklar:
- /var/db/monospot/5651/incoming/portal-<YYYYMMDD>.log  (uygulama yazar)
- /var/dhcpd/var/db/dhcpd.leases
- configctl captiveportal list_clients <zoneid>

Saklama: config'teki `OPNsense/Monospot/collect5651/retention_days` (varsayılan 365)
günden eski arşiv dizinleri silinir. 5651 kapatıksa hiçbir şey yapılmaz.

    collect5651.py                 # bugünü arşivle
    collect5651.py --date 2026-10-09
    collect5651.py --dry-run
"""

import argparse
import datetime
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import xml.etree.ElementTree as ET

ROOT = '/var/db/monospot/5651'
INCOMING = os.path.join(ROOT, 'incoming')
CONFIG = '/conf/config.xml'
LEASES = '/var/dhcpd/var/db/dhcpd.leases'
LIST_CLIENTS = '/usr/local/sbin/configctl'
INCOMING_KEEP_DAYS = 3


def read_config():
    """(enabled, retention_days, zoneid) — okunamazsa güvenli varsayılanlar."""
    enabled, retention, zoneid = True, 365, '0'
    try:
        node = ET.parse(CONFIG).getroot().find('./OPNsense/Monospot')
    except Exception as exc:
        print('config okunamadi: %s' % exc, file=sys.stderr)
        return enabled, retention, zoneid

    def text(path, default):
        if node is None:
            return default
        found = node.find(path)
        return default if found is None or found.text is None else found.text.strip()

    enabled = text('collect5651/enabled', '1') == '1'
    days = text('collect5651/retention_days', '365')
    retention = int(days) if days.isdigit() and int(days) > 0 else 365
    zoneid = text('general/zoneid', '0') or '0'
    return enabled, retention, zoneid


def lease_rows():
    """dhcpd.leases → 'ip  mac  starts  ends' satırları (5651: kimlik ↔ IP eşleşmesi)."""
    rows = []
    try:
        with open(LEASES, 'r', errors='replace') as handle:
            current = None
            for line in handle:
                line = line.strip()
                match = re.match(r'^lease\s+(\S+)\s', line)
                if match:
                    current = {'ip': match.group(1), 'starts': '', 'ends': '', 'mac': ''}
                    rows.append(current)
                    continue
                if current is None:
                    continue
                key = re.match(r'^(starts|ends)\s+\d+\s+(\S+\s+\S+);', line)
                if key:
                    current[key.group(1)] = key.group(2)
                    continue
                mac = re.match(r'^hardware ethernet\s+([0-9a-fA-F:]{17});', line)
                if mac:
                    current['mac'] = mac.group(1).lower()
    except FileNotFoundError:
        return rows
    return [r for r in rows if r['mac']]


def session_rows(zoneid):
    """captive portal oturum listesi → normalize edilmiş satırlar."""
    try:
        out = subprocess.run([LIST_CLIENTS, 'captiveportal', 'list_clients', str(zoneid)],
                             capture_output=True, text=True, timeout=60).stdout
        clients = json.loads(out.strip() or '[]')
    except Exception as exc:
        print('list_clients okunamadi: %s' % exc, file=sys.stderr)
        return []
    rows = []
    for client in clients:
        rows.append({
            'ip': client.get('ipAddress') or '',
            'mac': (client.get('macAddress') or '').lower(),
            'user': client.get('userName') or '',
            'via': client.get('authenticated_via') or '',
            'start': client.get('startTime') or '',
            'session': client.get('sessionId') or '',
        })
    return rows


def write_day(day, zoneid, dry_run):
    """Günün dosyalarını yazar; (hedef dizin, satır sayıları) döner."""
    stamp = day.strftime('%Y%m%d')
    dest = os.path.join(ROOT, day.strftime('%Y'), day.strftime('%m'), day.strftime('%d'))
    written = {}

    identity_src = os.path.join(INCOMING, 'portal-%s.log' % stamp)
    if os.path.exists(identity_src):
        with open(identity_src, 'r', errors='replace') as handle:
            written['identity.log'] = handle.read()

    leases = lease_rows()
    written['dhcp-leases.log'] = ''.join(
        '%s\t%s\t%s\t%s\n' % (r['ip'], r['mac'], r['starts'], r['ends']) for r in leases)

    sessions = session_rows(zoneid)
    written['cp-sessions.log'] = ''.join(
        '%s\t%s\t%s\t%s\t%s\t%s\n' % (r['ip'], r['mac'], r['user'], r['via'],
                                      r['start'], r['session']) for r in sessions)

    manifest = ''.join('%s  %s\n' % (hashlib.sha256(data.encode('utf-8', 'replace')).hexdigest(), name)
                       for name, data in sorted(written.items()))

    if not dry_run:
        os.makedirs(dest, exist_ok=True)
        for name, data in written.items():
            with open(os.path.join(dest, name), 'w') as handle:
                handle.write(data)
        with open(os.path.join(dest, 'manifest.sha256'), 'w') as handle:
            handle.write(manifest)

    return dest, {name: len(data.splitlines()) for name, data in written.items()}


def ensure_incoming():
    """Giriş dizin zincirini oluşturur ve portal (www) kullanıcısına hazırlar.

    Portal PHP'si www olarak çalışır; /var/db altındaki ara dizinler root:wheel
    0750 olduğu için www oraya ne bakabilir ne yazabilir. Bu yüzden:
      /var/db/monospot, /var/db/monospot/5651  -> root:www 0750 (geçiş izni)
      incoming                                  -> www:www 0750 (yazma izni)
    Arşiv gün dizinleri root'ta kalır (5651 kayıtlarının bütünlüğü).
    """
    import grp
    import pwd
    try:
        www_uid = pwd.getpwnam('www').pw_uid
        www_gid = grp.getgrnam('www').gr_gid
    except KeyError as exc:
        print('www kullanicisi bulunamadi: %s' % exc, file=sys.stderr)
        www_uid = www_gid = -1

    for path in (os.path.dirname(ROOT), ROOT):
        os.makedirs(path, exist_ok=True)
        if www_gid >= 0:
            os.chown(path, 0, www_gid)
        os.chmod(path, 0o750)

    os.makedirs(INCOMING, exist_ok=True)
    if www_uid >= 0:
        os.chown(INCOMING, www_uid, www_gid)
    os.chmod(INCOMING, 0o750)


def prune(retention_days, dry_run):
    """Saklama süresinden eski gün dizinlerini siler; boşalan üst dizinleri toplar."""
    cutoff = datetime.date.today() - datetime.timedelta(days=retention_days)
    removed = []
    for year in sorted(os.listdir(ROOT)) if os.path.isdir(ROOT) else []:
        if not year.isdigit():
            continue
        for month in sorted(os.listdir(os.path.join(ROOT, year))):
            if not month.isdigit():
                continue
            for day in sorted(os.listdir(os.path.join(ROOT, year, month))):
                if not day.isdigit():
                    continue
                try:
                    when = datetime.date(int(year), int(month), int(day))
                except ValueError:
                    continue
                if when < cutoff:
                    path = os.path.join(ROOT, year, month, day)
                    removed.append('%s-%s-%s' % (year, month, day))
                    if not dry_run:
                        shutil.rmtree(path, ignore_errors=True)
            if not dry_run and os.path.isdir(os.path.join(ROOT, year, month)) \
                    and not os.listdir(os.path.join(ROOT, year, month)):
                os.rmdir(os.path.join(ROOT, year, month))
        if not dry_run and os.path.isdir(os.path.join(ROOT, year)) and not os.listdir(os.path.join(ROOT, year)):
            os.rmdir(os.path.join(ROOT, year))
    return removed


def prune_incoming(dry_run):
    """Uygulamanın canlı yazdığı dosyalardan eskileri temizler."""
    removed = []
    if not os.path.isdir(INCOMING):
        return removed
    limit = datetime.date.today() - datetime.timedelta(days=INCOMING_KEEP_DAYS)
    for name in sorted(os.listdir(INCOMING)):
        match = re.match(r'^portal-(\d{8})\.log$', name)
        if not match:
            continue
        try:
            when = datetime.datetime.strptime(match.group(1), '%Y%m%d').date()
        except ValueError:
            continue
        if when < limit:
            removed.append(name)
            if not dry_run:
                os.remove(os.path.join(INCOMING, name))
    return removed


def main():
    parser = argparse.ArgumentParser(description='Monospot 5651 günlük arşivi')
    parser.add_argument('--date', help='arşivlenecek gün (YYYY-AA-GG), varsayılan bugün')
    parser.add_argument('--dry-run', action='store_true', help='yazmadan raporla')
    args = parser.parse_args()

    try:
        day = datetime.datetime.strptime(args.date, '%Y-%m-%d').date() if args.date \
            else datetime.date.today()
    except ValueError:
        print(json.dumps({'status': 'failed', 'error': 'gecersiz tarih: %s' % args.date}))
        return 1

    enabled, retention, zoneid = read_config()
    if not enabled:
        print(json.dumps({'status': 'skipped', 'reason': 'collect5651 kapali'}))
        return 0

    if not args.dry_run:
        ensure_incoming()

    dest, counts = write_day(day, zoneid, args.dry_run)
    pruned = prune(retention, args.dry_run)
    incoming_pruned = prune_incoming(args.dry_run)

    print(json.dumps({
        'status': 'ok',
        'dry_run': args.dry_run,
        'date': day.strftime('%Y-%m-%d'),
        'path': dest,
        'lines': counts,
        'retention_days': retention,
        'pruned_archives': pruned,
        'pruned_incoming': incoming_pruned,
    }, ensure_ascii=False))
    return 0


if __name__ == '__main__':
    sys.exit(main())
