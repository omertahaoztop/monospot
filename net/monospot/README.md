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
- **Giriş sonrası yönlendirme:** istemci bir siteye gitmeye çalıştıysa zone şimi `redirurl`
  taşır ve giriş sonrası doğrudan o adrese dönülür. `redirurl` yoksa (portala doğrudan
  gelindiyse) `settings.inc` içindeki `after_login_url` adresine yönlendirilir; bu ayar boşsa
  portalda kalınır ve yalnız başarı mesajı gösterilir.
- **Yetkili istemci portala giremez:** açık oturumu olan istemci (zone tablosunda
  `__captiveportal_zone_<id>`) portal portuna **erişemez**; geçiş kuralı
  `from !<zone tablosu>` iledir ve yetkililer için `block` kuralı önce gelir
  (`monospot.inc`). Böylece internete çıkan istemci portalı tekrar göremez.
  Uygulama tarafında da açık oturum `configctl captiveportal list_clients` ile tespit
  edilir ve form çizilmez (kural etkin değilse "internet erişiminiz açık" ekranı).
  Oturumu bitirmek: `configctl captiveportal disconnect "<sessionId>" "<sebep>"`.
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
   `configctl monospot install_zonetemplate 0 8100 "Monospot Portal"`)
   zone'un statik sayfasını istemciyi portala yönlendiren şimle değiştirir.
   Bu adım core captive portal servisini yeniden başlatır.
   (configd action'ı yer tutucuları sırayla doldurur: argümanlar sade değer
   olarak verilir, `--zoneid=` biçimi kullanılmaz. Zone devre dışıysa captive
   portal yeniden başlamaz; zone htdocs'u ancak zone etkinken yenilenir.)
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

## Sayfalar ve menü

Eklenti OPNsense sol menüsünde **Services → Monospot** altında ayrı sayfalar olarak durur:

| Menü | Sayfa | İş |
|---|---|---|
| Servis Ayarları | `/ui/monospot` | servis/zone/port ayarları, **Durum paneli**, zone şablonu, 5651 düğmesi |
| Captive Portal | `/ui/monospot/captiveportal` | core zone ayarları (arayüzler, timeout'lar, roaming, açıklama) |
| Durum | `/ui/monospot/status` | servis/portal/zone/istemci/veritabanı/5651 özeti |
| Kullanıcılar | `/ui/monospot/users` | **native** liste/ekle/düzenle/sil (hotspot veritabanı) |
| Gruplar / SMS Raporu / İzin Raporu | `/monospot.php?u=...` | henüz uygulama paneli rotaları |
| 5651 Günlükleri | `/5651/` | günlük arşiv tarayıcısı |

- **Durum paneli** `api/monospot/service/info` → `status.php` çıktısını gösterir: portal adresi, zone ve
  arayüzleri, yetkili istemci sayısı (+ilk 10 kayıt), uygulama veritabanı sayaçları, 5651 arşiv özeti,
  SMS sağlayıcı dosyası.
- **Kullanıcılar sayfası** (native): UIBootgrid + dialog form (`forms/dialogUser.xml`); veri `api/monospot/users/search|get|add|set|del`
  → `configctl monospot user_admin` → `user_admin.php` (root) SQLite CRUD. Yazma root'tan yapılır çünkü portal
  veritabanına `www` olarak yazar ve sahiplik değiştirilmez. **Şifre hiçbir zaman geri dönmez** (formda boş = değişmez).
- **Captive Portal sayfası** veriyi **core modelde** tutar (Services → Captive Portal ile aynı kayıt):
  okuma `api/monospot/captive_portal/get`, yazma `api/monospot/captive_portal/set` →
  `configctl monospot set_captiveportal` → `set_captiveportal.php` core zone'a yazar, şablonu yeniler
  ve captive portal'ı yeniden başlatır.
- **API rota adı** çok kelimeli controller'da alt çizgi ister: controller dosyası `CaptivePortalController.php`
  ise URL `captive_portal` olur (`parsePath` → `ucwords`). `captiveportal` yazılırsa "Endpoint not found".
- **configd action betikleri** doğrudan çalıştığı için hem `chmod +x` hem `#!/usr/local/bin/php`
  satırı gerekir; aksi hâlde configd "Execute error" (exit 126) verir.
- pfSense'teki *Pre-authentication redirect URL* / *After authentication Redirection URL* alanlarının
  OPNsense zone modelinde karşılığı yoktur: istemci portalı görmeden önceki yönlendirmeyi zone şimi,
  giriş sonrası yönlendirmeyi `settings.inc` içindeki `after_login_url` yapar.


## 5651 günlük arşivi

Portal giriş kayıtları, DHCP kira tablosu ve captive portal oturum listesi gün bazında arşivlenir
(5651 kimlik kaydı için kanıt defteri). Varsayılan olarak **açıktır**; `Services > Monospot`
sayfasındaki *5651 günlükleri* sekmesinden kapatılır ve saklama süresi değiştirilir.

```
/var/db/monospot/5651/
  incoming/portal-<YYYYMMDD>.log     # portal girişleri (www yazar, canlı)
  <yıl>/<ay>/<gün>/
    identity.log      # o günün giriş kayıtları (ISO8601, kullanıcı, MAC, IP, zone)
    dhcp-leases.log   # DHCP kira anlık görüntüsü (IP ↔ MAC ↔ başlangıç/bitiş)
    cp-sessions.log   # captive portal oturum listesi
    manifest.sha256   # dosya özetleri (imzalama için temel)
```

- Arşivleme `collect5651.py` ile yapılır; OPNsense'in kendi cron üretimine kaydedilir
  (`monospot_cron()` → root crontab, her gün **23:59**). `configctl cron restart` crontab'ı yeniler.
- Elle çalıştırma: `configctl monospot collect5651` (veya sayfadaki *5651 arşivini şimdi topla*
  düğmesi). Saklama, `OPNsense/Monospot/collect5651/retention_days` (varsayılan 365 gün) ile uygulanır.
- **Dizin izinleri:** portal PHP'si `www` olarak çalışır; `/var/db/monospot` ve `.../5651`
  `root:www 0750`, `incoming` `www:www 0750`. Arşiv gün dizinleri `root` sahipliğinde kalır.
  Toplayıcı bu zinciri her çalışmada düzeltir.
- **Neden dosyaya yazılıyor:** bu sistemde portalın `syslog(LOG_AUTH)` kayıtları kalıcı bir dosyaya
  düşmüyor (syslog-ng yönlendirmesi); kimlik kaydının güvenilir yeri 5651 dosyasıdır.
- İmzalama (RFC3161 TSA / `logsigner` karşılığı) sonraki turdadır; `manifest.sha256` bunun temelidir.
- **Tarayıcı:** `https://<fw>/5651/` (Services → Monospot sayfasındaki *5651 günlük arşivi* bağlantısı).
  Gün listesi, dosya görüntüleme/satır arama, indirme ve `manifest.sha256` ile bütünlük kontrolü.
  OPNsense WebGUI oturumu + `page-Monospot` ACL'i arkasındadır (`5651/*` deseni). Veri katmanı
  `www/5651/archive.php`; HTTP'den bağımsız olduğu için CLI ile de doğrulanabilir:
  `php -r 'require "/usr/local/www/5651/archive.php"; print_r(archive_days());'`.

## Bilinen sınırlar

- **Portal portu değişirse** zone şablonu yeniden kurulmalıdır (şim, portu
  içerir): `configctl monospot install_zonetemplate <id> <yeni> "Monospot Portal"`.
- **5651 günlük arşivinin imzalanması** (RFC3161 TSA, `logsigner` karşılığı) ve
  günlük tarayıcı arayüzü (`/5651`) bu turda yoktur; kayıt + saklama hazırdır
  (bkz. *5651 günlük arşivi*).
- `settings.inc`, `hotspot.ini`, `db/`, `lang/`, `media/` dosyaları `www`
  sahipliğindedir (yönetim paneli ve portal aynı kullanıcıyla çalışır).
- Zone şiminden sonra portal, zone'un arayüzleri üzerinden erişilebilir;
  başka arayüzlerde portal portu kapalıdır (kural yalnız zone arayüzlerine
  yazılır).
