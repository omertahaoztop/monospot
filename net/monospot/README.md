# os-monospot — MonoSpot hotspot (OPNsense eklentisi)

MonoSpot'u (SMS OTP ile misafir Wi-Fi erişimi) OPNsense üzerinde çalıştıran
eklenti. pfSense sürümünden farkı: core dosyalarına dokunulmaz, oturum
yetkilendirmesi OPNsense captive portal servisine devredilir, yönetim paneli
OPNsense web arayüzünün oturum ve ACL denetimi altında çalışır.

## Mimari

```
istemci --(pf rdr)--> core CP zone lighttpd (statik) --(zone şablon şimi 302)-->
                       monospot portal lighttpd :8100 (php-cgi, www)
                             |
                             +-- giriş --> configctl captiveportal allow --zoneid --username --ip_address
                             +-- süre  --> configctl captiveportal set.session_restrictions

yönetici --(https://fw/monospot.php)--> core WebGUI (guiconfig.inc + ACL page-Monospot)
                                          --> Limonade yönetim paneli (app/index.php)
```

- **Portal** kendi lighttpd örneğinde kendi portunda (varsayılan 8100) sunulur.
  Core zone lighttpd'si statiktir (PHP çalıştırmaz); WebGUI lighttpd'si ise
  yetkisiz istemciye açılamaz.
- **Yetkilendirme** pfSense'teki `portal_allow()` yerine `configctl captiveportal
  allow` çağrısıyla yapılır; pf tablosu/ipfw sayaçları core'un işidir.
- **Veri** SQLite'ta tutulur (kullanıcı, SMS, izin kayıtları). OPNsense
  depolarında `pdo_sqlite` olmadığı için `app/lib/monospot_db.php` SQLite3
  uzantısı üzerinden PDO uyumlu bir katman sağlar.
- **Ayarlar** iki katmanlıdır: portal ayarları uygulama dosyalarında
  (`settings.inc`, `hotspot.ini`, `db/hotspot.db`, `lang/*.inc`), servis
  parametreleri (etkinlik/zone/port) config.xml içinde `OPNsense/Monospot`
  düğümünde.

## Kurulum

```sh
# geliştirme/derleme (OPNsense üzerinde veya FreeBSD build ortamında)
cd net/monospot && make package        # work/pkg/os-monospot-*.pkg
pkg add work/pkg/os-monospot-0.1.0.pkg
```

Kurulumdan sonra:

1. **Servis ayarları:** `Services > Monospot` sayfasında portalı etkinleştirin,
   captive portal zone numarasını ve portal portunu doğrulayın, kaydedin.
2. **Zone şablonu:** aynı sayfadaki *Zone şablonunu kur* düğmesi (veya
   `configctl monospot install_zonetemplate --zoneid=0 --port=8100 --name=monospot-portal`)
   zone'un statik sayfasını istemciyi portala yönlendiren şimle değiştirir.
   Bu adım core captive portal servisini yeniden başlatır.
3. **SMS sağlayıcı:** `sms/sms.<sağlayıcı>.php` dosyalarından birini
   `/usr/local/opnsense/scripts/monospot/app/sms.php` olarak kopyalayıp API
   bilgilerini doldurun ve dosyayı `chmod 600` yapın (paket bu dosyayı
   içermez; içinde API parolası tutar).
   Not: `sms.markamsms.php` ayrıca `nusoap/lib/nusoap.php` bekler.
4. **İsteğe bağlı** `custom_functions.php` ve `captiveportal_custom.php`
   (operatör uyarlamaları) aynı dizine konabilir; yoklukları portalı bozmaz.
5. **Yönetim paneli:** `https://<fw>/monospot.php` (menüde: *Services →
   Monospot Management*). Erişim, `page-Monospot` ACL iznine bağlıdır
   (Sistem > Erişim > Kullanıcılar/Gruplar). Panelden kullanıcı/grup/izin
   kayıtları, SMS raporu, limitler, logo ve tema rengi yönetilir. Eklentinin
   servis ayarları ayrı sayfadadır: *Services → Monospot* (`/ui/monospot`:
   etkinleştirme, zone, port, servis durumu, zone şablonu kurulumu).

## Loglar ve servis

```sh
configctl monospot status          # portal servisi
configctl monospot configtest      # lighttpd yapılandırma testi
configctl monospot restart
tail -f /var/log/monospot-error.log
/var/log/monospot-access.log
# portal kimlik doğrulama kayıtları: syslog (auth), ident 'monospot'
```

## Bilinen sınırlar

- **Portal portu değişirse** zone şablonu yeniden kurulmalıdır (şim, portu
  içerir): `configctl monospot install_zonetemplate --zoneid=<id> --port=<yeni> --name=monospot-portal`.
- **5651/logsigner** paketi bu sürümde yok; portal giriş/çıkış kayıtları
  syslog'a yazılır.
- `settings.inc`, `hotspot.ini`, `db/`, `lang/`, `media/` dosyaları `www`
  sahipliğindedir (yönetim paneli ve portal aynı kullanıcıyla çalışır).
- Zone şiminden sonra portal, zone'un arayüzleri üzerinden erişilebilir;
  başka arayüzlerde portal portu kapalıdır (kural yalnız zone arayüzlerine
  yazılır).
