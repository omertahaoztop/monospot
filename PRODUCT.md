# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Misafir (birincil):** işletmenin Wi-Fi'sine bağlanmak isteyen kişi; ayakta, elinde telefon,
  ortam ışığı yüksek, birkaç saniye toleransı var. İşi: kaydolup şifreyi SMS'ten alıp internete çıkmak.
- **Operatör / işletme yöneticisi:** kullanıcıları, grupları, izin kaydını (KVKK), SMS raporunu ve
  limitleri yöneten kişi; masaüstü tarayıcıda, iş akışının içinde.
- **Sistem yöneticisi:** eklentiyi OPNsense'e kurup servisini, zone eşlemesini ve şablonu yöneten
  kişi (bu depo/oturumun sahibi); OPNsense GUI'sini kullanır.

## Product Purpose

Bir mekânda misafir Wi-Fi erişimini SMS ile tek kullanımlık şifre göndererek açar ve bu sırada
kişinin iletişim izinlerini (KVKK) kanıtlanabilir şekilde kaydeder. Başarı: misafirin **saniyeler
içinde** çevrimiçi olması; operatörün erişimi ve izin kaydını denetleyebilmesi. [çıkarım]

## Positioning

Aynı ürünü komşu bir çözüm kopyalayamaz çünkü tüm zincir **güvenlik duvarının içinde** çalışır:
SMS OTP ile kimlik doğrulama, MAC bazlı kalıcılık, core captive-portal yetkilendirmesi ve izin/limit
defteri tek cihazda (OPNsense eklentisi) tutulur; bulut ya da ek portal cihazı gerekmez. [çıkarım]

## Operating Context

- Captive portal akışı: istemci ağa bağlanır, core captive portal onu yakalar, zone şablonu onu
  Monospot portalına yönlendirir; giriş sonrası yetkilendirme core'a devredilir.
- Türk operatör SMS sağlayıcıları (`app/sms/sms.<sağlayıcı>.php`), sağlayıcı dosyası operatör tarafından
  yerleştirilir ve API parolası taşır.
- Veri yerel: SQLite (`db/hotspot.db`); kayıtlar syslog'a (ident `monospot`) yazılır.
- Portal kendi lighttpd örneğinde (varsayılan 8100) çalışır; yönetim paneli OPNsense web GUI
  oturumu + `page-Monospot` ACL'i arkasındadır.

## Capabilities and Constraints

- Çalışan işlevler: SMS ile kayıt/giriş, tek kullanımlık şifre süresi, MAC başına tekrar-giriş kuralı,
  günlük/haftalık/aylık limitler, kullanıcı-grup-MAC politikaları (`mac_grup_ayarlari`), izin raporu,
  SMS raporu, TCKN doğrulaması (isteğe bağlı), TR/EN dil seçimi, marka/logo/renk ayarı, demo bitiş tarihi.
- Kapsam dışı (kullanıcı kararı): e-posta ile sponsor onayı akışı; pfSense tarafı değişmez.
- Teknik sınırlar: portal sayfaları jQuery 1.9 ile çalışır ve **çerçevesiz** (framework yok);
  OPNsense lighttpd + php-cgi; PHP 8.5; yeni bağımlılık/gömülü font/CDN eklenmez (misafir ağı yerelde çalışır).
- Ölçek: bir mekânda onlarca–yüzlerce misafir; panel verisi sayfalanır (`items_per_page`).
- Terminoloji: "şifre" = SMS ile gelen tek kullanımlık kod; "izin" = KVKK iletişim izni.

## Brand Commitments

- Ürün adı **MonoSpot**; şemsiye marka **Mono Bilişim** (`hotspot.ini` → `firma`, `marka`).
- Güncel marka varlığı: https://r2.mono.tr/logo/Mono-Logo.png (mono wordmark; marka kırmızısı
  **#FA002A** yalnız logo dosyasının içinde yaşar).
- Kullanıcı kararı (09.10.2026): arayüzde **kırmızı ağırlıklı renk kullanılmaz**; görsel dünya
  "sakin nötr + tek ince aksan". Varsayılan aksan mürekkeptir (`#111417`), odak halkası ayrı
  token'dır (`#2f6feb`) ve vurgu rengi ayarlardan değiştirilebilir kalmalı
  (`settings.inc` → `color`).
- Arayüz dili Türkçe öncelikli; EN çevirisi korunur.

## Evidence on Hand

- Logo: `net/monospot/src/opnsense/scripts/monospot/app/assets/img/captiveportal-logo.png`
  (267×124; kırmızı #E00E19, griler #9C9A9A/#C0BFBF, zemin #FAFAFA).
- Wi-Fi işareti: `assets/img/captiveportal-wifi.png` (1838 B).
- Portalın kendi metinleri (`lang/tr.sample.inc`, `lang/en.sample.inc`) ve KVKK/izin başlıkları
  `views/layouts/captiveportal.html.php` içinde; metinler gerçek ürün dilidir, değiştirilmeden korunur.
- Müşteri referansı, basın, benchmark, fiyat bilgisi **yok**; uydurulmaz.
- Gerçek kurulum: <opnsense-fw> (OPNsense 26.7) + yerel KVM test ortamı; SMS testte mock'lanır.

## Product Principles

1. Misafir portalı fark etmesin: en az adım, tek elle, saniyeler içinde; hata metni sorunu ve çıkış yolunu söyler.
2. Kayıt kanıttır: izin/limit bilgisi operatöre görünür ve denetlenebilir olmalı.
3. Cihazın içinde çalışır: dış servise bağımlılık yok; ağ kesikken de portal ayakta.
4. Tanıdık olan güvenilirdir: form kontrolleri, odak, hata, boş durum, yükleniyor — hepsi standart ve tam.
5. Panel bilgi yoğun ama okunur olmalı: öbekleme, hizalama ve ölçü kararı içeriğe göre verilir.

## Accessibility & Inclusion

- Birincil kullanım telefon, tek elle ve parlak ışıkta: kontrast ≥ 4.5:1, hedefler ≥ 44px,
  klavye odakları görünür, durum yalnız renkle anlatılmaz. [çıkarım]
- Yaşlı/az gören misafir olasılığı: metin ölçeklenebilir (rem), form `autocomplete`/`inputmode` doğru.
- TR/EN dil değiştirici korunur; tarih/sayı biçimi yerel.
