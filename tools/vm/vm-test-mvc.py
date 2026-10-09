#!/usr/bin/env python3
"""
Yerel VM: Monospot servis sayfasını (/ui/monospot) gerçek tarayıcıda sınar.

Selenium gerekmez; chromedriver HTTP API'si kullanılır. Kontroller:
  - sayfa yükleniyor, form alanları görünür (base_tabs_content çökmesi yok)
  - "Kaydet" → /api/monospot/settings/set + service/reconfigure, hata dialogu açılırsa metni
  - servis durumu widget'ı dolduruluyor mu (#service_status_container)

Kullanım: vm-test-mvc.py [kullanıcı] [şifre]
"""
import json
import urllib.error
import subprocess
import sys
import time
import urllib.request

user = sys.argv[1] if len(sys.argv) > 1 else 'root'
password = sys.argv[2] if len(sys.argv) > 2 else 'opnsense'
base = 'https://127.0.0.1:8443'
port = 9518
api = 'http://127.0.0.1:%d' % port

proc = subprocess.Popen(['chromedriver', '--port=%d' % port, '--no-sandbox'],
                        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
time.sleep(2)


def call(method, path, payload=None):
    data = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(api + path, data=data, method=method)
    if data:
        req.add_header('Content-Type', 'application/json')
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            return json.loads(resp.read().decode())
    except urllib.error.HTTPError as exc:
        body = exc.read().decode('utf-8', 'replace')
        raise RuntimeError('chromedriver %s %s -> %s %s' % (method, path, exc.code, body[:400]))


session = None
try:
    caps = {'capabilities': {'alwaysMatch': {
        'browserName': 'chrome',
        # acceptInsecureCerts: yalnızca yerel, tek kullanımlık test VM'i (127.0.0.1, kendinden
        # imzalı OPNsense GUI sertifikası) için; üretim/uzaq hedeflere karşı kullanılmaz.
        'acceptInsecureCerts': True,
        'goog:chromeOptions': {'args': ['--headless=new', '--no-sandbox', '--disable-gpu',
                                        '--window-size=1400,1100']},
    }}}
    session = call('POST', '/session', caps)['value']['sessionId']

    def js(script):
        res = call('POST', '/session/%s/execute/sync' % session, {'script': script, 'args': []})
        return res.get('value')

    call('POST', '/session/%s/url' % session, {'url': base + '/'})
    time.sleep(2)
    js("var u=document.getElementById('usernamefld');u.value='%s';"
       "document.getElementById('passwordfld').value='%s';"
       "u.form.querySelector('[name=\"login\"]').click();" % (user, password))
    time.sleep(4)
    print('oturum:', js('return location.pathname;'))

    call('POST', '/session/%s/url' % session, {'url': base + '/ui/monospot'})
    time.sleep(3)
    print('sayfa:', js('return location.pathname + location.search;'))
    print('form var mı:', js("return !!document.getElementById('frm_monospot');"))
    print('alanlar:', js("return Array.from(document.querySelectorAll('#frm_monospot input'))"
                        ".map(function(e){return e.name;}).filter(function(n){return n;}).join(',');"))
    # widget içeriği yalnız ikon/düğme olduğu için textContent boştur; HTML'e bak
    print('servis widget (html):', js("var e=document.getElementById('service_status_container');"
                                      r"return e ? e.innerHTML.replace(/\s+/g, ' ').slice(0,120) : '';"))
    print('widget düğmeleri:', js("return ['restartService','stopService','startService']"
                                 ".filter(function(id){return document.getElementById(id);}).join(',');"))
    js("window.__st='(yanıt yok)'; ajaxCall('/api/monospot/service/status', {}, "
       "function(d, s){ window.__st = s + ' ' + JSON.stringify(d); });")
    time.sleep(3)
    print('status API:', js('return window.__st;'))

    # Kaydet: POST + reconfigure; BootstrapDialog açılırsa içeriğini yakala
    js("window.__dlg=''; $(document).on('shown.bs.modal', function(){ window.__dlg = "
       "$('.modal-body').text(); });")
    js("document.getElementById('save_monospot').click();")
    time.sleep(6)
    print('kaydet sonrası dialog:', js('return window.__dlg;') or '(yok)')
    print('hata mesajı:', js("var e=document.querySelector('.alert-danger, .bootbox-body');"
                            "return e ? e.textContent.trim().slice(0,200) : '';") or '(yok)')
    print('servis widget (sonra):', js("var e=document.getElementById('service_status_container');"
                                       r"return e ? e.innerHTML.replace(/\s+/g, ' ').slice(0,80) : '';"))
finally:
    if session:
        try:
            call('DELETE', '/session/%s' % session)
        except Exception:
            pass
    proc.terminate()
