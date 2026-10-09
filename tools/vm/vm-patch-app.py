#!/usr/local/bin/python3
"""
Yerel VM yaması: captiveportal.php içindeki configd çağrılarını konumsal
argümanlara çevirir (configd %s yer tutucularını sırayla doldurur; --flag=değer
biçimi "Execute error" verir).

Kullanım: python3 /root/patch-app.py [dosya]
"""
import sys

path = sys.argv[1] if len(sys.argv) > 1 else '/usr/local/opnsense/scripts/monospot/app/captiveportal.php'

with open(path, encoding='utf-8') as fh:
    src = fh.read()

replacements = [
    (
        """    return monospot_configdp('captiveportal allow', array(
        '--zoneid=' . MONOSPOT_ZONEID,
        '--username=' . $username,
        '--ip_address=' . $ip,
        '--authenticated_via=monospot',
    ));""",
        """    return monospot_configdp('captiveportal allow', array(
        MONOSPOT_ZONEID,
        $username,
        $ip,
        'monospot',
    ));""",
    ),
    (
        "$clients = monospot_configdp('captiveportal list_clients', array('-z', MONOSPOT_ZONEID));",
        "$clients = monospot_configdp('captiveportal list_clients', array(MONOSPOT_ZONEID));",
    ),
    (
        """        monospot_configdp('captiveportal set.session_restrictions', array(
            '--zoneid=' . MONOSPOT_ZONEID,
            '--sessionid=' . $result['sessionId'],
            '--session_timeout=' . max(1, $terminate - time()),
            '--accounting_interval=' . (isset($settings['session_timeout']) ? $settings['session_timeout'] : 60),
        ));""",
        """        monospot_configdp('captiveportal set.session_restrictions', array(
            MONOSPOT_ZONEID,
            $result['sessionId'],
            max(1, $terminate - time()),
            isset($settings['session_timeout']) ? $settings['session_timeout'] : 60,
        ));""",
    ),
]

for old, new in replacements:
    if old not in src:
        print('ATLANDI (bulunamadı): %s...' % old.strip().splitlines()[0][:60])
        continue
    src = src.replace(old, new)
    print('YAMALANDI: %s...' % old.strip().splitlines()[0][:60])

with open(path, 'w', encoding='utf-8') as fh:
    fh.write(src)

print('bitti:', path)
