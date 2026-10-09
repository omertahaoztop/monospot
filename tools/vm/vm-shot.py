#!/usr/bin/env python3
"""
Yerel VM: OPNsense GUI oturumu açıp verilen sayfaların ekran görüntüsünü alır
(chromedriver; Selenium gerekmez).

Kullanım: vm-shot.py <çıktı-dizini> <genişlik> <yol> [yol ...]
Örnek:    vm-shot.py docs/screenshots 1280 '/monospot.php?users' '/monospot.php?settings'
"""
import base64
import http.cookiejar
import json
import re
import ssl
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

outdir = sys.argv[1]
width = int(sys.argv[2])
paths = sys.argv[3:]
base = 'https://127.0.0.1:8443'
port = 9515
api = 'http://127.0.0.1:%d' % port

proc = subprocess.Popen(['chromedriver', '--port=%d' % port, '--no-sandbox'],
                        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
time.sleep(2)


def call(method, path, payload=None):
    data = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(api + path, data=data, method=method)
    if data:
        req.add_header('Content-Type', 'application/json')
    with urllib.request.urlopen(req, timeout=40) as resp:
        return json.loads(resp.read().decode())


def gui_login(user='root', password='opnsense'):
    """GUI oturumu açar ve PHPSESSID çerezini döndürür (chromedriver'a aktarılır)."""
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),
                                        urllib.request.HTTPSHandler(context=ctx))
    page = opener.open(base + '/', timeout=20).read().decode('utf-8', 'replace')
    hidden = re.search(r'<input[^>]*type="hidden"[^>]*name="([^"]+)"[^>]*value="([^"]*)"', page, re.I)
    payload = urllib.parse.urlencode({
        'usernamefld': user,
        'passwordfld': password,
        hidden.group(1): hidden.group(2),
        'login': 'Login',
    }).encode()
    opener.open(urllib.request.Request(base + '/', data=payload), timeout=20).read()
    for cookie in jar:
        if cookie.name == 'PHPSESSID':
            return cookie.value
    raise RuntimeError('PHPSESSID alınamadı')


try:
    caps = {'capabilities': {'alwaysMatch': {
        'browserName': 'chrome',
        'acceptInsecureCerts': True,
        'goog:chromeOptions': {'args': ['--headless=new', '--no-sandbox', '--disable-gpu',
                                        '--window-size=%d,1000' % width]},
    }}}
    session = call('POST', '/session', caps)['value']['sessionId']
    call('POST', '/session/%s/window/rect' % session, {'width': width, 'height': 1000})

    def js(script):
        res = call('POST', '/session/%s/execute/sync' % session, {'script': script, 'args': []})
        return res.get('value')

    def shot(name):
        data = call('GET', '/session/%s/screenshot' % session)['value']
        path = '%s/%s' % (outdir, name)
        with open(path, 'wb') as fh:
            fh.write(base64.b64decode(data))
        print('yazıldı:', path)

    call('POST', '/session/%s/url' % session, {'url': base + '/'})
    time.sleep(1.5)
    print('GUI login formu gönderiliyor')
    js("""
        (function () {
            var user = document.getElementById('usernamefld');
            user.value = 'root';
            document.getElementById('passwordfld').value = 'opnsense';
            // form.submit() 'login' düğmesinin değerini göndermez; gerçek tıklama gerekir
            user.form.querySelector('[name="login"]').click();
        })();
    """)
    time.sleep(4)
    print('oturum sonrası adres:', js('return location.href;'))

    for path in paths:
        call('POST', '/session/%s/url' % session, {'url': base + path})
        time.sleep(2)
        title = js("return document.querySelector('#nav') ? 'panel' : 'panel-yok';")
        errors = js("return Array.from(document.querySelectorAll('.message, .error')).map("
                    "function (e) { return e.textContent.trim(); }).slice(0, 3).join(' | ');")
        print('%s -> %s; mesajlar: %s' % (path, title, errors))
        name = 'admin-%s-%d.png' % (path.split('?')[-1].replace('/', '-'), width)
        shot(name)
finally:
    try:
        call('DELETE', '/session/%s' % session)
    except Exception:
        pass
    proc.terminate()
