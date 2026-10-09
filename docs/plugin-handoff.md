# Eklenti geliştirme notları (OPNsense portu)

Bu dosya, eklentiyi bir OPNsense kutusunda geliştirirken/denetlerken **bedeli ödenmiş** dersleri toplar.
Lab'a özel adresler, kimlik bilgileri ve müşteri verisi bilinçli olarak **yer almaz**; o bilgiler bu deponun
dışında tutulur.

## Derleme ve kurulum

Eklenti, OPNsense kutusunun kendisinde derlenebilir (harici build ortamı gerekmez):

```sh
# 1) kaynağı kutuya kopyala (Mk + Scripts + Templates + net/monospot)
tar czf /tmp/monospot-src.tgz Mk Scripts Templates net
scp /tmp/monospot-src.tgz root@<fw>:/tmp/
ssh root@<fw> 'rm -rf /tmp/msrc && mkdir -p /tmp/msrc && tar xzf /tmp/monospot-src.tgz -C /tmp/msrc'

# 2) derle (.git yoksa hash'i elle ver)
cd /tmp/msrc/net/monospot
make PLUGIN_COMMIT='<ver> <rev> <hash>' package      # -> work/pkg/os-monospot-*.pkg

# 3) kur (+POST_INSTALL: configd restart, migration, rc.configure_plugins, template reload)
pkg add -f work/pkg/os-monospot-*.pkg
configctl monospot restart
```

Tuzaklar:
- `Scripts/version.sh` `git describe` kullanır; ağaçta `.git` yoksa `product_hash` bozulur → `PLUGIN_COMMIT` ile ver.
- **`pkg add -f` kaldırılan dosyaları silmez.** Bir dosyayı yeniden adlandırdıysan/sildiysen kurulu ağaçta
  kalır; elle silinmesi gerekir (kurulu ağacı depoyla karşılaştır: controller/form/view listeleri).
- `@sample` işaretli çalışma zamanı dosyaları (`settings.inc`, `hotspot.ini`, `db/*.db`, `sms.php`, `lang/*.inc`)
  kurulumda **korunur**.

## OPNsense forma motoru kuralları

| Kural | Ayrıntı |
|---|---|
| **select seçenekleri API'den gelir** | `setFormData` bir `<select>` için `[{key, value, selected}, …]` bekler ve `<option>`'ları **bu veriden** üretir. Düz değer dizisi verilirse select boşalır; `tokenize` widget uydurma bir etiket (`0`) gösterir. Örn. arayüz listesi: `$field->getNodeData()` → `{key, value, selected}`. |
| **Çok sekmeli form = sekme başına form** | `base_tabs_content` her sekmeyi ayrı form olarak çizer: id `frm_<tab_id>`, Apply düğmesi `save_<tab_id>`. `mapDataToFormUI` ve `saveFormToEndpoint` **sekme başına** çağrılır (Volt: `{% for tab in form['tabs'] %}`). Tek `<sayfa>` id'si yoktur; yanlış id ile kaydetme **sessizce** boş gider. |
| **Salt-okunur sayfa da form olabilir** | `type=info` alanları etiket/değer satırı çizer; değerler JS ile `span`'lara yazılır (`setFormData` span'ları da doldurur). Alttaki Apply düğmesi sayfanın eylemine dönüştürülebilir (Yenile / şablonu kur / arşivi topla). |
| **UI sayfası = ayrı controller dosyası** | `/ui/<plugin>/<name>` → `<Name>Controller.php`; çok kelimeli ad **alt çizgiyle** (`/ui/x/captive_portal` → `CaptivePortalController.php`). `IndexController`'a action eklemek 404 verir. |
| **API yolu da alt çizgili** | `<CaptivePortal>Controller.php` → `/api/<plugin>/captive_portal/<action>`. |
| **`<help>` içine HTML** | CDATA kullan (`<help><![CDATA[… <code>x</code> …]]></help>`), yoksa XML ayrıştırması bozulur. |
| **Menü önbelleği** | Menu.xml değişince `rc.configure_plugins` (veya `MenuSystem::invalidateCache()`) gerekir; önbellek `/var/lib/php/tmp/opnsense_menu_cache.xml`. |

## configd köprüsü (root gerektiren işler)

- Eylem dosyası: `src/opnsense/service/conf/actions.d/actions_<plugin>.conf`; çağrı: `configctl <plugin> <action> <param>`.
- Betikler **doğrudan çalıştırılabilir** olmalı: `chmod +x` **ve** shebang (`#!/usr/local/bin/php`), yoksa
  `Execute error` (exit 126).
- Parametreler **sade değer** olarak verilir (`actions_<plugin>.conf` içinde `parameters:%s`, çağrıda tek argüman).
- Örnek kullanım: portal uygulamasının veritabanına yazan yönetim işlemleri (kullanıcı/grup CRUD, dil dosyaları,
  `settings.inc` yazımı) configd üzerinden root olarak yapılır; portal PHP'si `www` olarak çalışır ve
  `/var/db` altına yazamaz (ara dizinler `root:wheel 0750`) → yazma yolları bu yüzden köprüden geçer.

## Servis görünürlüğü (dashboard / Services listesi)

OPNsense servis durumunu `util.inc:service_message()` ile üretir:

```php
if (!empty($service['nocheck']))    $status = true;
elseif (isset($service['pidfile']))  $status = isvalidpid($service['pidfile']);
else                                 $status = is_process_running($service['name']);   // süreç adı!
```

- **`pidfile` bildir.** Bildirilmezse süreç adı servis adıyla aranır; süreç adı farklıysa (ör. `monospot`
  servisi `lighttpd` süreciyle çalışır) servis "not running" görünür ve dashboard kırmızı olur.
- Kırmızı görünen bir servis, çalışmıyor demek **değildir**; önce `configctl <plugin> status` ve süreç adına bak.

## Portal zone şimi (istemciyi portala yönlendirme)

Zone sayfası (`:8000`) iki işi yapar: `redirurl`'ü taşır ve istemciyi portal portuna (`:8100`) yönlendirir.

- Şim HTML'inde `<script>` **body'nin sonunda** olmalı: `<head>` içinde çalıştırılırsa `<body>`'deki öğeye
  erişemez (`null.setAttribute` → TypeError) ve ne otomatik yönlendirme ne tıklama çalışır.
- `redirurl` yoksa `settings.inc` içindeki `after_login_url` kullanılır; o da boşsa portalda kalınır ve başarı
  mesajı gösterilir.
- Zone şimini kurmak: `configctl monospot install_zonetemplate <zoneid> <port> <ad>` (`{"status":"ok"}` döner).
- Zone arayüzü/kural üretimi `plugins.inc.d`'den beslenir: portal portu için **yalnız hizmet verilen zone
  arayüzlerinden** geçiş, yetkili istemciler için **block** (zone tablosu `__captiveportal_zone_<id>`).

## Captive portal oturumları

- Liste: `configctl captiveportal list_clients <zoneid>` (JSON).
- Kesme: `configctl captiveportal disconnect "<sessionId>" "<sebep>"` — **parametre sırası kritik**
  (`sessionid` önce, sebep sonra).

## 5651 günlük arşivi

- Toplayıcı: `scripts/monospot/collect5651.py` → `/var/db/monospot/5651/<yıl>/<ay>/<gün>/` içine
  `identity.log` (portal giriş kayıtları), `dhcp-leases.log`, `cp-sessions.log`, `manifest.sha256` yazar;
  `retention_days` (varsayılan 365) geçen gün dizinlerini siler.
- Zamanlama **OPNsense cron üretimiyle** yapılır: `plugins.inc.d` içinde `*_cron()` kancası
  `[komut, dakika, saat]` listesini döndürür → root crontab'a yazılır (`/var/cron/tabs/root`).
  OPNsense'in Cron **modeline** yazmak işi `nobody` olarak çalıştırır; arşiv dizinleri root sahipli olduğu için
  kanca tercih edilir.
- Kimlik kaydı portal tarafından canlı yazılır: `/var/db/monospot/5651/incoming/portal-<YYYYMMDD>.log`
  (yazan süreç `www`'dir; giriş dizini `www:www 0750`, arşiv dizinleri `root`).

## Dashboard widget'ı

- Modern widget'lar **ES modülü**: `src/opnsense/www/js/widgets/<Ad>.js` (taban sınıflar `BaseWidget`,
  `BaseTableWidget` `export` içermeyen klasik script'lerdir → globaldir, import gerekmez).
- Keşif: `src/opnsense/www/js/widgets/Metadata/<Ad>.xml` — `<metadata><ad><filename>…</filename><link>…</link>
  <endpoints><endpoint>/api/…</endpoint></endpoints><translations><title>…</title></translations></ad></metadata>`.
  `DashboardController::getMetadata()` bu dizini tarar; kök düğüm adı mutlaka `metadata` olmalı.
- Widget içeriğini `getMarkup()` üretir, verisini `onMarkupRendered()` doldurur; seçiciler core widget'larda
  olduğu gibi **global id** ile yapılır (`$('#...')`). Hata durumunu görünür kıl (sessiz kalmasın).

## Denetim (cheap checks)

```sh
# derleme sonrası: kurulu ağaç = depo ağacı mı
for d in controllers/OPNsense/<P> controllers/OPNsense/<P>/forms controllers/OPNsense/<P>/Api views/OPNsense/<P>; do
  ls /usr/local/opnsense/mvc/app/$d | tr '\n' ' '; echo; done

# JS sözdizimi (Volt ifadeleri nötrleştirilerek; ESM için .mjs kopyası)
node --check /tmp/view.mjs

# XML geçerliliği
php -r 'simplexml_load_file("…xml") or print("HATA");'

# servis durumu / oturumlar / köprüler
configctl service list | grep -A2 <plugin>
configctl captiveportal list_clients <zone>
configctl <plugin> info
```

## İlgili dosyalar

- `net/monospot/README.md` — eklentinin kurulumu, sayfaları ve köprüleri.
- `docs/opnsense-port.md` — pfSense → OPNsense port kararları.
