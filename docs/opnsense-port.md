# MonoSpot OPNsense portu — saha notları

Bu doküman, OPNsense portunda **doğrulanmış** OPNsense iç yapı bilgilerini toplar.
Her madde bir kanıt komutuna bağlıdır; teyit edilmemiş madde "DOĞRULANMADI" olarak işaretlidir.

Doğrulama host'u: `<opnsense-fw>` — OPNsense 26.7.2_2, FreeBSD 15.1, PHP 8.5.8, KVM guest.

## Core Captive Portal

| Bulgu | Kanıt |
|---|---|
| Zone config dosyası `/usr/local/etc/captiveportal.conf`, bölümler `[zone_N]` + `[template_for_zone_N]` | `cat` |
| Zone lighttpd şablonu `OPNsense/Captiveportal/lighttpd-zone.conf` → `/var/etc/lighttpd-cp-zone-N.conf` | template dir |
| Zone portalı portu `zoneid+8000`, docroot **statik** (`/var/captiveportal/zoneN/htdocs`), PHP yok | `/var/etc/lighttpd-cp-zone-0.conf` (`server.document-root = "/htdocs/"`) |
| Zone şablonu ZIP olarak config'e base64 gömülür (`template_for_zone_N.content`) | `captiveportal.conf` |
| Kullanıcı şablonu configd ile: `configctl captiveportal fetch_template` / `strip_template` | `actions_captiveportal.conf` |
| Yetkilendirme: `configctl captiveportal allow --zoneid=%s --username=%s --ip_address=%s --authenticated_via=%s` (MAC'i ARP'ten core çözer) | `actions_captiveportal.conf` |
| Oturum limiti: `configctl captiveportal set.session_restrictions --zoneid --sessionid --session_timeout --accounting_interval` | `actions_captiveportal.conf` |
| Diğer: `list_clients` (`-z %s`), `disconnect` (`sessionId reason`) | `actions_captiveportal.conf` |
| Core hook deseni: `/usr/local/etc/inc/plugins.inc.d/captiveportal.inc` → `captiveportal_services()`, `captiveportal_firewall($fw)` | dosya |
| Hook keşfi: `/usr/local/etc/inc/plugins.inc.d/<isim>.inc` glob'lanır, `<isim>_<hook>` çağrılır | core `plugins.inc` |

## Web GUI (admin yüzeyi)

| Bulgu | Kanıt |
|---|---|
| GUI sunucusu **lighttpd** (nginx değil): `/usr/local/sbin/lighttpd -f /usr/local/etc/lighttpd_webgui/lighttpd.conf` | `ps ax` |
| Docroot `/usr/local/www/`; `alias.url += ("/ui/" => "/usr/local/opnsense/www/", "/api/" => ...)`; `url.rewrite-if-not-file` ile Phalcon | conf |
| Port 9443, SSL zorunlu (`ssl.engine=enable`, `server.port=9443`) | conf |
| PHP: `fastcgi.server` → socket `/var/lib/php/tmp/php-fastcgi.socket`, `bin-path=/usr/local/bin/php-cgi` (lighttpd spawn eder) | conf |
| php-fpm alternatifi de var: `php-fpm.d/www.conf` `user=www`, `listen=127.0.0.1:9000`, **chroot/open_basedir YOK** | conf |
| `url.access-deny = ("~", ".core", ".inc")` — `.inc` GUI'den kapalı, `.ini`/`.db` DEĞİL | conf |
| `include "/usr/local/etc/lighttpd_webgui/conf.d/*.conf"` — kullanıcı/eklenti ek yapılandırma bırakabilir (README bunu açıkça söylüyor; dosya adları sıralama için `01-` gibi numaralanır) | conf + conf.d/README |
| `session.save_path=/var/lib/php/sessions`, `upload_tmp_dir=/var/lib/php/tmp` | `/usr/local/etc/php.ini` |
| WebGUI şablonu yalnız `+TARGETS: etc.php.ini, lib.php.ini` — conf.d'ye yazmaz | `/usr/local/opnsense/service/templates/OPNsense/WebGui/+TARGETS` |
| Legacy PHP sayfası ship etme emsali: `os-isc-dhcp` → `/usr/local/www/services_dhcp.php` | `pkg info -l os-isc-dhcp` |

## Servis / paket

| Bulgu | Kanıt |
|---|---|
| Kurulu PHP eklentileri: curl, mbstring, openssl, PDO, **sqlite3**, session, simplexml, dom, filter, pcntl, sockets, xml, zlib, phalcon 5.18.2 | `php -m` |
| **`pdo_sqlite` YOK** → Idiorm/PDO için SQLite3-ext tabanlı adapter gerekir | `php -m` + `pkg search` |
| `soap` YOK — TCKN curl ile ham SOAP XML gönderir, sorun değil | `php -m` |
| OPNsense sürümü 26.7.2_2 (ABI `26.7`) | `opnsense-version` |

## Yerel KVM test ortamı (bu makine)

```
qemu-base (extra repo, 11.1.1) — libvirt gerekmez
OPNsense-26.7-serial-amd64.img.bz2 — mirror.ams1.nl.leaseweb.net/opnsense/releases/26.7/
CPU: AMD-V (svm) mevcut; ~/vms/opnsense-test; tmux oturumu "opnsensevm"
```

| Bulgu | Kanıt / not |
|---|---|
| Kurulum **UFS** ile yapılır; ZFS yolu "Partitioning error" ile abort ediyor | 2 bağımsız deneme; UFS 100% tamamlandı |
| Kurulum medyasının diski hedef olarak seçilemez → ikinci disk şart | ZFS diyalogu ada0'ı listelemedi; UFS listeledi |
| Kurulu sistem: `Root file system: /dev/gpt/rootfs` | boot mesajı |
| NIC rolü: LAN=`vtnet0`, WAN=`vtnet1` (canlı sihirbazda atanır ve kuruluma klonlanır) | `/conf/config.xml`, banner |
| LAN slirp ağı (192.168.1.0/24 + hostfwd) **vtnet0**'a bağlanmalı | aksi halde SSH banner aşamasında zaman aşımı |
| Kurulu sistemde SSH servisi: `openssh` (`/etc/rc.d/openssh`), config `/usr/local/etc/ssh/sshd_config` | `sysrc openssh_enable=YES; service openssh start` |
| `sshd_enable` set edilirse: "openssh and /usr/sbin/sshd use the same port" | `sysrc -x sshd_enable` |
| Konsol diyaloglarında seçili öğe: `tmux capture-pane -p -e` → `^[[4;7m` | düz metin yakalamada görünmez |
| `tmux send-keys Space` seçim yapmaz; `send-keys " "` gönder | ada1 seçimi böyle yapıldı |

## Uçtan uca doğrulama (yerel VM, gerçek L2 istemci) — 2026-10-06

Lab modu: OPNsense (LAN=vtnet0 gerçek L2 mcast, WAN=vtnet1 slirp) + Alpine istemci VM
(vtnet0 aynı mcast segmenti, `192.168.1.50`).

| Adım | Kanıt |
|---|---|
| İstemci CP tarafından yakalanır | `wget http://1.1.1.1/` → `302 → http://192.168.1.1:8000/index.html?redirurl=1.1.1.1/` |
| Zone şimi portalı gösterir | `/var/captiveportal/zone0/htdocs/index.html` = 858 B bizim şim; istemcide görüldü |
| Portal ön-yetkisiz erişilebilir | `wget http://192.168.1.1:8100/` → 200, 9818 B, `X-Limonade` başlığı |
| Kayıt + SMS | `form_id=sms_register&user[gsm]=5551112233` → "Şifreniz cep telefonunuza gönderilmiştir" |
| MAC çözümü | `/tmp/monospot-sms.log`: `mac=52:54:00:00:02:01` (Alpine VM'in gerçek MAC'i) |
| Aynı MAC'ten ikinci numara reddi | "Aynı MAC adresi ile daha önce giriş yapılmış." (`disallow_multiple_logins`) |
| Giriş | POST `form_id=sms_login` → **302** `Location: /` (önceki hâli: "Girişiniz tamamlanamadı") |
| Yetkilendirme | `configctl captiveportal list_clients 0` → `{"authenticated_via":"monospot","userName":"5551112233","ipAddress":"192.168.1.50","macAddress":"52:54:00:00:02:01","bytes_in":9710,...}` |
| İnternet erişimi | İstemcide `wget -S http://1.1.1.1/` → **doğrudan** `301 cloudflare` (CP araya girmiyor) |
| Kimlik kaydı | syslog: `portal LOGIN user=5551112233 mac=52:54:00:00:02:01 ip=192.168.1.50 zone=0` |

### Bulunan ve düzeltilen hatalar

| Hata | Kök neden | Düzeltme |
|---|---|---|
| `configctl captiveportal allow` → "Execute error" | configd, action tanımındaki `%s` yer tutucularını **sırayla** doldurur; `--zoneid=...` biçimi argüman sayısını bozar | `monospot_configdp()` çağrıları sade değerlerle (`MONOSPOT_ZONEID, $username, $ip, 'monospot'`) |
| Zone şablonu üretilemedi (`KeyError: ' font-family'`) | `str.format` CSS süslü parantezlerini yer tutucu sandı | `{PORT}` + `str.replace` |
| Şablon kaydı reddedildi (isim doğrulaması) | core template ad maskesi `-` karakterini kabul etmiyor | ad: `Monospot Portal` |

### Lab modu kısıtları (bilinerek kabul edildi)

- CP zone etkinken **zone arayüzünde yalnız portal portu açıktır** → yönetim (GUI/SSH) lab modunda
  erişilemez. Bu yüzden: yönetim paneli + görsel işler için `run` modu (CP zone devre dışı,
  LAN user-mode + hostfwd), CP akışı için `lab-up`.
- Lab modunda dosya aktarımı yalnız konsol üzerinden (`tools/vm/console-push.sh`).

## Konsol dosya aktarımı (tcsh tuzakları)

| Tuzak | Ölçüm / çözüm |
|---|---|
| `tmux send-keys -l` satır sonundaki `;` karakterini düşürür | `printf 'a;b\nc;\n'` push'u → `c;` satırı `c` oldu; md5 tutmadı |
| Yapıştırılan heredoc içinde boş satırlar kaybolur | biçimsel fark (PHP yine geçerli) |
| Hızlı yapıştırmada satır kaybı (21-26 satır) | içerik `tmux load-buffer` + `paste-buffer` ile **satır satır** gönderilir, minik gecikmeyle |
| Doğrulama | `md5 -q` (FreeBSD) ile yerel `md5sum` karşılaştırılır — birebir eşleşme sağlandı |

## Bulunan tuzaklar (yerel VM'de ölçüldü, düzeltildi)

| Tuzak | Kök neden | Çözüm |
|---|---|---|
| Panel 403 "Access Denied" | `ACL::isPageAccessible()` maskeyi `@^/{mask}$@` ile eşler; scriptsiz `monospot.php` maskesi maskeleri günler | Denetim `$_SERVER['SCRIPT_NAME']` (başında `/`) ile yapılır |
| Panel sınırsız yönlendirme döngüsü | `url_for()` `?users` üretir, Limonade ise yalnız `?/route` ya da `u=` okur → `/` rotasına düşer, `/` → `users` yönlendirir | `index.php` sorgu dizesini `u=` parametresine çevirir (ek GET parametreleri korunur) |
| Panel CSS/JS 404 | Varlıklar app dizininde; GUI docroot'u `/usr/local/www` | `setup.sh` `/usr/local/opnsense/www/monospot-assets` sembolik bağını kurar; panel `/ui/monospot-assets` kullanır, portal kendi docroot'undan `assets/` sunar |
| Panel'de root 403 (kendi kurulumu) | VM, GUI ilk-kurulum sihirbazı atlanarak kurulduğu için root `admins` grubunda değil | `page-*` ACL'i gruba bağlıdır; üretimde kullanıcı/grup GUI'den atanır (test VM'de `tools/vm/vm-enable-admin.php`) |
| `configctl captiveportal allow` → "Execute error" | Action tanımındaki `%s` yer tutucuları argümanlarla sırayla doldurulur; `--flag=değer` biçimi sayıyı bozar | Çağrılar sade değerlerle: `array(ZONEID, $user, $ip, 'monospot')` |
| Zone şablonu üretilemedi | Python `str.format` CSS süslü parantezlerini yer tutucu sandı | `{PORT}` + `str.replace` |
| Şablon kaydı reddedildi | Core template ad maskesi `-` kabul etmiyor | Ad: `Monospot Portal` |
| Kullanıcı `settings.inc`/`hotspot.ini` yok | Uygulama dizini root sahipliğinde; artık kopyalanmıyor | `setup.sh deploy` ihtiyaç duyulan dosyaları örneklerden üretir, `www` sahipliğine alır |
| Konsol heredoc aktarımında içerik bozuldu | tsh `;` kaybı ve hızlı yapıştırmada satır kaybı | `tools/vm/console-push.sh`: satır satır `tmux paste-buffer` + `md5` doğrulaması |
| `/ui/monospot` 500: `count(): Argument #1 must be of type Countable\|array, null given` (base_form) | Core `base_tabs_content`, `subtab` dalında `tab['subtabs']` üzerinde dönerken parse sonucunun `sections/advanced/help` anahtarlarını da dolaşıyor ve `fields`'e null veriyor; hiçbir core form `<subtab>` kullanmıyor | Form XML'i düz `<tab>` ile yazıldı; tab id = form id (`frm_monospot`) olacak şekilde `activetab` ayarlandı |
| Servis durumu widget'ı boş kalıyor | `ApiMutableServiceControllerBase::statusAction()` `configctl <servis> status` çıktısında İngilizce `is running` / `not running` arıyor | `rc.d/monospot status` metinleri bu kalıba uygun (`monospot is running (pid N)`), çıktı başında servis adı var |
| Panelde "Zone şablonunu kur" → `{"status":"failed","error":"wrote /tmp/... (port 8100)\n{\"status\":\"ok\"...}"}` | Zip üreticisinin stdout'u (`wrote ...`) PHP'nin JSON çıktısına karıştı; script yanıtı `json_decode` edilemedi | Üreticinin stdout'u bastırılır; karar **son satırdaki** JSON'a göre verilir; hata da JSON olarak (ve stderr'e) döner, çıkış kodu 0 (configd sıfırdan farklı çıkışta kendi "Execute error" metnini gösteriyor) |
| "Zone şablonunu kur" → "Template name should be a string between 1 and 255" | Bayraklı argümanlar (`--name=Monospot Portal`) ayrıştırıldıktan sonra konumsal yedek satırı `NAME=$3` ile üzerine yazıyordu (`--name=Monospot Portal` ad olarak PHP'ye gidiyordu) | Konumsal yedek kaldırıldı; configd zaten `%s` yer tutucularına sade değerleri yerleştirir |
| Ayarlar sayfasında Kaydet sessizce başarısız | API POST'ları `X-CSRFToken` ister; GUI oturumu yoksa 403 | Tarayıcıdan test: `tools/vm/vm-test-mvc.py` (gerçek oturumla form yükleniyor, widget doluyor, Kaydet → `/api/monospot/settings/set` + `service/reconfigure`, `config.xml` revizyonu yazılıyor) |

## <opnsense-fw> kurulumu (2026-10-09)

```
opnsense-version   26.7.2_2 (amd64)
pkg info           os-monospot-0.1.0   (128 dosya)
/conf/config.xml   //OPNsense/Monospot/general → enabled=0, zoneid=0, portal_port=8100
                   (eski eklentiden kalan admin_port anahtarı ilk kayıtta düşecek)
```

Yapılanlar:

1. Eski paket ve artıklar temizlendi: `pkg unlock os-monospot` → `pkg add -f` (yeni paket),
   `/root/opnsense-plugin` (28 Ağu build ağacı) silindi, yeni build ağacı `/root/monospot-build`.
2. `net/monospot` içinde `make package` → `os-monospot-0.1.0.pkg`; kurulumda üretilen betikler
   çalıştı: `configd restart`, `template reload OPNsense/Monospot`, `+POST_INSTALL`.
3. `sh /usr/local/opnsense/scripts/monospot/setup.sh deploy`: örnek dosyalar üretildi
   (`db/hotspot.db`, `lang/tr.inc`, `lang/en.inc`, `settings.inc`, `hotspot.ini`), `www` sahipliği,
   `/usr/local/opnsense/www/monospot-assets` sembolik bağı.
4. Doğrulama: `configctl monospot configtest` temiz; `rc.d/monospot onestart` → `*:8100` dinliyor;
   `curl http://127.0.0.1:8100/` → **200**, 10922 bayt, `sms_register` formu var; `onestop` sonrası kapalı.
5. `pfctl -sr | grep monospot` → **0 kural** (`enabled=0` → fail-closed davranış doğrulandı).

Açık kalan: fw'nin web GUI'si haproxy arkasında (`<guest-net>30:443`) — `/monospot.php` sayfası
bu oturumdan doğrulanamadı (aynı yol yerel VM'de GUI oturumuyla doğrulandı). Üretimde GUI'den
bir kez açılıp `page-Monospot` izninin verildiği kullanıcı görülmelidir.

## Görsel tur — doğrulama kanıtı (yerel VM)

| Kontrol | Sonuç |
|---|---|
| Portal CSS yükleniyor | `GET /assets/css/captiveportal-style.css` → 200, 5639 bayt, 349 satır, `--brand` tanımlı |
| Portal DOM | `#form-selection` (nav), `#js-errors` (`role="alert"`), `aria-current="true"` dil işareti, `--brand: #333` |
| Doğrulama hatası alert yerine satır içi kutu | chromedriver: `window.alert` **çağrılmadı**, `#js-errors` görünür, metin "• Geçersiz bir telefon numarası girdiniz.", alanda `aria-invalid="true"`, sayfa yeniden yüklenmedi |
| Panel teması | `#nav` → `display:flex`, `table th` → `rgb(51,51,51)`, `.content` → `border-radius:12px`, `body` → `system-ui` yığını |
| Panel varlıkları | `/ui/monospot-assets/css/style.css` → 200 |
| Mobil | Portal 390×844, panel 390 genişlikte ekran görüntüsü; yatay kaydırma yok |
| Ekran görüntüleri | `docs/screenshots/{portal-desktop,portal-mobile,admin-users-1280,admin-settings-1280,admin-users-390}.png` |

Araçlar (hepsi `tools/vm/`): `vm-test-portal-ui.py` (portal davranışı), `vm-probe.py` (GUI oturumu +
rota/ACL), `vm-shot.py` (panel görüntüsü), `console-push.sh` (konsoldan dosya aktarımı).

## Açık kalan doğrulamalar

- [x] `allow` çıktısı ve pf/sayaç etkisi (`list_clients` → `authenticated_via=monospot`)
- [x] www kullanıcısının configd action yetkisi (portal süreci `www` olarak `allow` çağırdı)
- [x] Yönetim paneli: `/monospot.php` + GUI oturumu + `page-Monospot` ACL (403 → 200)
- [x] Görsel iyileştirmelerin masaüstü + mobil doğrulaması
- [ ] `set.session_restrictions` çağrısı — login akışında `session_timeout < global` koşulu oluşmadı
- [ ] `.ini`/`.db` deny kuralı portal docroot'unda (portal zaten yalnız `assets/` sunar; kural sınandı)
- [ ] Zone şiminin gerçek istemci tarayıcısında 302 ile portala düşmesi (VM'de `index.html` şimi
      istemciye servis edildi; yönlendirme tarayıcı JS'i ile çalışır, curl ile sınanamaz)
- [ ] Gerçek SMS sağlayıcısı ile uçtan uca (yerel doğrulama `sms.sample.php` mock'u ile yapıldı)

