#!/usr/bin/env python3
"""
Yerel VM: OPNsense web GUI üzerinden Monospot yönetim panelini test eder.

Akış: login sayfasını al (oturum + rastgele adlı CSRF alanı) → oturum aç →
/monospot.php iste → yanıtı ve ACL davranışını raporla.

Kullanım: vm-test-admin.py [kullanıcı] [şifre] [taban_url]
"""
import http.cookiejar
import re
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request

user = sys.argv[1] if len(sys.argv) > 1 else 'root'
password = sys.argv[2] if len(sys.argv) > 2 else 'opnsense'
base = sys.argv[3] if len(sys.argv) > 3 else 'https://127.0.0.1:8443'

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(jar),
    urllib.request.HTTPSHandler(context=ctx),
)

HIDDEN_RE = re.compile(r'<input[^>]*type="hidden"[^>]*name="([^"]+)"[^>]*value="([^"]*)"', re.I)

print('--- login sayfası')
login_page = opener.open(base + '/', timeout=20).read().decode('utf-8', 'replace')
hidden = HIDDEN_RE.search(login_page)
if not hidden:
    print('CSRF alanı bulunamadı')
    sys.exit(1)
print('csrf alanı:', hidden.group(1))

payload = {
    'usernamefld': user,
    'passwordfld': password,
    hidden.group(1): hidden.group(2),
    'login': 'Login',
}
data = urllib.parse.urlencode(payload).encode()

print('--- oturum aç')
try:
    resp = opener.open(urllib.request.Request(base + '/', data=data), timeout=20)
    print('post=%s final_url=%s' % (resp.status, resp.url))
except urllib.error.HTTPError as exc:
    print('post hatası: %s' % exc.code)
    sys.exit(1)

print('--- panel')
try:
    resp = opener.open(base + '/monospot.php', timeout=20)
    body = resp.read().decode('utf-8', 'replace')
    print('monospot.php=%s bytes=%d' % (resp.status, len(body)))
except urllib.error.HTTPError as exc:
    body = exc.read().decode('utf-8', 'replace')
    print('monospot.php hata=%s bytes=%d' % (exc.code, len(body)))

for marker in ('Yönetim Paneli', 'Kullanıcılar', 'Gruplar', 'Ayarlar', 'SMS',
               'Access Denied', 'login', 'phpLiteAdmin'):
    count = body.count(marker)
    if count:
        print('  %-16s x%d' % (marker, count))

with open('/tmp/monospot-admin.html', 'w', encoding='utf-8') as fh:
    fh.write(body)
print('gövde: /tmp/monospot-admin.html')
