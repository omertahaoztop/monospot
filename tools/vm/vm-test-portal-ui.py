#!/usr/bin/env python3
"""
Yerel VM portal davranış testi (chromedriver, Selenium'sız).

Kontroller:
  1. Sayfa yükleniyor, sekme (tab) düğmesi aktif formu açıyor.
  2. Geçersiz GSM ile submit → satır içi hata kutusu (#js-errors) doluyor,
     alert() kullanılmıyor, ilgili alan aria-invalid alıyor.
  3. Dil bağlantıları ve erişilebilirlik işaretleri yerinde.

Kullanım: vm-test-portal-ui.py [url] [chromedriver_port]
"""
import json
import subprocess
import sys
import time
import urllib.request

url = sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8100/'
port = int(sys.argv[2]) if len(sys.argv) > 2 else 9515
base = 'http://127.0.0.1:%d' % port

proc = subprocess.Popen(['chromedriver', '--port=%d' % port, '--no-sandbox'],
                        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
time.sleep(2)


def call(method, path, payload=None):
    data = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(base + path, data=data, method=method)
    if data:
        req.add_header('Content-Type', 'application/json')
    with urllib.request.urlopen(req, timeout=30) as resp:
        return json.loads(resp.read().decode())


def run_js(session, script, args=None):
    res = call('POST', '/session/%s/execute/sync' % session,
               {'script': script, 'args': args or []})
    value = res.get('value')
    if isinstance(value, dict) and value.get('error'):
        raise RuntimeError(value)
    return value


try:
    session = call('POST', '/session', {'capabilities': {'alwaysMatch': {
        'browserName': 'chrome',
        'goog:chromeOptions': {'args': ['--headless=new', '--no-sandbox', '--disable-gpu']},
    }}})['value']['sessionId']

    call('POST', '/session/%s/url' % session, {'url': url})
    time.sleep(1)

    print('başlık:', run_js(session, 'return document.title;'))
    print('sekme aktif mi:', run_js(
        session, "return document.querySelector('#sms_register').classList.contains('active');"))
    print('css değişkeni --brand:', run_js(
        session, "return getComputedStyle(document.documentElement).getPropertyValue('--brand').trim();"))
    print('hata kutusu başlangıçta gizli:', run_js(
        session, "return document.getElementById('js-errors').hidden;"))

    # alert() çağrısını yakala ve geçersiz GSM ile submit et
    run_js(session, "window.__alerted = false; window.alert = function () { window.__alerted = true; };")
    run_js(session, """
        var f = document.getElementById('form_sms_register').querySelector('form');
        var gsm = f.querySelector('input[name="user[gsm]"]');
        gsm.value = '123';
        f.querySelector('input[type="submit"]').click();
    """)
    time.sleep(0.5)

    print('alert çağrıldı mı:', run_js(session, 'return window.__alerted;'))
    print('hata kutusu görünür:', run_js(
        session, "return !document.getElementById('js-errors').hidden;"))
    print('hata metni:', run_js(
        session, "return document.getElementById('js-errors').textContent;").strip())
    print('aria-invalid:', run_js(
        session, "return document.querySelector('#form_sms_register input[name=\"user[gsm]\"]').getAttribute('aria-invalid');"))
    print('sayfa adresi değişti mi:', run_js(session, 'return location.href;'))

    print('dil seçici:', run_js(
        session, "return Array.from(document.querySelectorAll('#lang input[type=submit]'))"
                 ".map(function (e) { return e.value + ':' + e.getAttribute('aria-current'); }).join(',');"))
finally:
    try:
        call('DELETE', '/session/%s' % session)
    except Exception:
        pass
    proc.terminate()
