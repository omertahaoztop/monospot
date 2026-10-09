#!/usr/bin/env python3
"""
Yerel VM: OPNsense GUI oturumu açıp verilen yolları (yönlendirmeleri takip
etmeden) getirir; durum kodu, Location başlığı ve sayfa işaretlerini yazar.

Kullanım: vm-probe.py [kullanıcı] [şifre] [yol ...]
Örnek:    vm-probe.py root opnsense /monospot.php '/monospot.php?/users'
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
paths = sys.argv[3:] or ['/monospot.php']
base = 'https://127.0.0.1:8443'

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
jar = http.cookiejar.CookieJar()


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def make_opener(no_redirect=False):
    handlers = [urllib.request.HTTPCookieProcessor(jar),
                urllib.request.HTTPSHandler(context=ctx)]
    if no_redirect:
        handlers.append(NoRedirect)
    return urllib.request.build_opener(*handlers)


def fetch(opener, path, data=None):
    req = urllib.request.Request(base + path, data=data)
    try:
        resp = opener.open(req, timeout=20)
        return resp.status, resp.headers.get('Location'), resp.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as exc:
        return exc.code, exc.headers.get('Location'), exc.read().decode('utf-8', 'replace')


login_opener = make_opener()
status, _, page = fetch(login_opener, '/')
hidden = re.search(r'<input[^>]*type="hidden"[^>]*name="([^"]+)"[^>]*value="([^"]*)"', page, re.I)
print('login sayfası: %s, csrf alanı: %s' % (status, hidden.group(1) if hidden else '-'))

payload = urllib.parse.urlencode({
    'usernamefld': user,
    'passwordfld': password,
    hidden.group(1): hidden.group(2),
    'login': 'Login',
}).encode()
status, location, _ = fetch(login_opener, '/', payload)
print('oturum aç: %s -> %s' % (status, location))

probe = make_opener(no_redirect=True)
for path in paths:
    status, location, body = fetch(probe, path)
    print('\n%s -> %s%s' % (path, status, ' Location: ' + location if location else ''))
    if body:
        markers = [m for m in ('Yönetim Paneli', 'Kullanıcılar', 'Gruplar', 'Ayarlar', 'SMS',
                               'İzin', 'Access Denied', 'Logout') if m in body]
        print('  uzunluk=%d işaretler=%s' % (len(body), markers))
        if not markers:
            print('  ilk 300 karakter:', body[:300].replace('\n', ' '))
