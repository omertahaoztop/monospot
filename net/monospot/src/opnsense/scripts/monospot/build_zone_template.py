#!/usr/local/bin/python3
"""
Zone şablon şimini üretir: captive portal zone'unun statik sayfası yerine
istemciyi Monospot portalına (http://<host>:<port>/?redirurl=...) yönlendiren
tek dosyalık index.html'i ZIP olarak yazar.

Kullanım: build_zone_template.py <port> <çıktı.zip>
"""
import sys
import zipfile

INDEX_TEMPLATE = """<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Wi-Fi Erisim</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
         background: #f5f6f8; color: #222; margin: 0; padding: 2rem; text-align: center; }
  a { color: #0b5ed7; }
</style>
</head>
<body>
  <p>Wi-Fi erisim sayfasina yonlendiriliyorsunuz...</p>
  <p><a id="manual" href="/">Devam etmek icin dokunun</a></p>
<script>
(function () {
  /* yönlendirme hedefi: zone sayfasının sunulduğu host (fw), monospot portu */
  var params = new URLSearchParams(window.location.search);
  var redir = params.get('redirurl') || params.get('redirect') || '';
  var target = window.location.protocol + '//' + window.location.hostname + ':{PORT}/' +
               (redir ? '?redirurl=' + encodeURIComponent(redir) : '');
  var manual = document.getElementById('manual');
  if (manual) {
    manual.setAttribute('href', target);
  }
  window.location.replace(target);
})();
</script>
</body>
</html>
"""


def main():
    port = sys.argv[1]
    outfile = sys.argv[2]
    with zipfile.ZipFile(outfile, mode='w', compression=zipfile.ZIP_DEFLATED) as zf:
        # str.format kullanılmaz: CSS süslü parantezleri yer tutucu sanılır
        zf.writestr('index.html', INDEX_TEMPLATE.replace('{PORT}', port))
    print('wrote %s (port %s)' % (outfile, port))


if __name__ == '__main__':
    main()
