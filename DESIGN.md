---
name: Monospot Hotspot Portal
description: OPNsense captive-portal eklentisi — misafir SMS girişi portalı ve operatör yönetim paneli
colors:
  ink: "#111417"
  ink-2: "#59616e"
  paper: "#ffffff"
  shell: "#eff1f4"
  rule: "#dfe3e8"
  rule-strong: "#c3cad2"
  rule-hover: "#a9b2bc"
  row-hover: "#f7f9fb"
  disabled-surface: "#b9bfc6"
  disabled-ink: "#6b7280"
  danger-field: "#fffafa"
  scrim: "rgba(0, 0, 0, 0.5)"
  brand: "#111417"
  focus: "#2f6feb"
  success: "#14532d"
  success-surface: "#eef7f1"
  success-rule: "#c6e4d1"
  danger: "#7f1d1d"
  danger-surface: "#fdeceb"
  danger-rule: "#f0ccc7"
  selection: "#d8e3f8"
  logo-red: "#fa002a"
typography:
  body:
    fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif'
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
  label:
    fontSize: "13px"
    fontWeight: 600
    lineHeight: 1.45
    letterSpacing: "0.01em"
  title:
    fontSize: "16px"
    fontWeight: 600
    lineHeight: 1.35
    letterSpacing: "-0.01em"
  tab:
    fontSize: "15px"
    fontWeight: 600
    lineHeight: 1.4
  code:
    fontFamily: 'ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace'
    fontSize: "21px"
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: "0.32em"
  code-compact:
    fontFamily: 'ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace'
    fontSize: "19px"
    fontWeight: 600
    letterSpacing: "0.26em"
  small:
    fontSize: "12px"
    fontWeight: 700
    letterSpacing: "0.06em"
  meta:
    fontSize: "14px"
    fontWeight: 500
  body-strong:
    fontSize: "15px"
    fontWeight: 600
  heading:
    fontSize: "17px"
    fontWeight: 650
    letterSpacing: "-0.005em"
  title-large:
    fontSize: "20px"
    fontWeight: 650
    letterSpacing: "-0.01em"
  section-label:
    fontSize: "13px"
    fontWeight: 700
    letterSpacing: "0.06em"
rounded:
  xs: "6px"
  sm: "10px"
  md: "14px"
spacing:
  xs: "6px"
  sm: "8px"
  md: "16px"
  lg: "20px"
  xl: "26px"
components:
  button-primary:
    backgroundColor: "{colors.brand}"
    textColor: "{colors.paper}"
    rounded: "{rounded.sm}"
    padding: "12px 18px"
    height: "48px"
    width: "100%"
  button-primary-hover:
    backgroundColor: "{colors.ink-2}"
    textColor: "{colors.paper}"
    rounded: "{rounded.sm}"
    padding: "12px 18px"
    height: "48px"
    width: "100%"
  button-quiet:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    typography: "{typography.tab}"
    rounded: "{rounded.sm}"
    padding: "9px 14px"
  input-text:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "11px 13px"
    height: "46px"
    width: "100%"
  input-code:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    typography: "{typography.code}"
    rounded: "{rounded.sm}"
    padding: "11px 13px"
    height: "46px"
    width: "100%"
  tab:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink-2}"
    typography: "{typography.tab}"
    padding: "6px 2px"
  tab-active:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    typography: "{typography.tab}"
    padding: "6px 2px"
  nav-item-current:
    backgroundColor: "{colors.shell}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "7px 12px"
  notice-danger:
    backgroundColor: "{colors.danger-surface}"
    textColor: "{colors.danger}"
    rounded: "{rounded.sm}"
    padding: "10px 13px"
  message-success:
    backgroundColor: "{colors.success-surface}"
    textColor: "{colors.success}"
    rounded: "{rounded.sm}"
    padding: "12px 14px"
---

# Monospot Hotspot Portal

## Overview

**Creative North Star: "Sakin Araç" (The Quiet Instrument).**

İki yüzey, tek bir sakin araç dili konuşur. Misafir portalı bir eylem sayfasıdır: marka üstte tek
satır, altında adım göstergesi, sekmeler ve tek kolonlu form — hepsi aynı sol çizgiden başlar.
Yönetim paneli aynı dilde bir defterdir: bölümler ince kurallarla açılır, alt seçenekler tek bir
girinti rayında durur, iç içe kutu yoktur.

Renk iş yapmadığında görünmez. Varsayılan vurgu mürekkeptir (`--brand: #111417`) ve kullanıcı
panelden tek ayarla değiştirebilir; odak halkası ayrı bir token'dır (`--focus`). Marka kırmızısı
yalnız logo varlığının içinde yaşar, arayüz yüzeyine taşınmaz.

Bu dünya kategori varsayılanını reddeder: mavi/gri "kurumsal portal" şablonu, renkli bir başlık
bandı, iç içe kutu ağacı ve her yerde tek vurgu rengi. Onun yerine: ölçü, kural ve hizalama.

## Colors

- **ink / ink-2** — metin ve ikincil metin; ikincil metin kâğıt üzerinde 6.0:1 verir.
- **paper / shell** — yüzey ve arka plan; kartın sınırı renkle değil 1px kuralla çizilir.
- **rule / rule-strong** — ayırıcı ve form kenarı.
- **brand** — birincil eylem, aktif sekme, seçim ve odak; varsayılanı nötr mürek kep, ayarlardan değişir.
- **focus** — klavye odak halkası; `brand`'dan bağımsız kalır ki aksan rengi değişse de odak her zaman görünsün.
- **success / danger (+ yüzey ve kural tonları)** — durum mesajları; durum asla yalnız renkle anlatılmaz, metin de taşır.
- **selection** — metin seçimi arka planı; tarayıcı varsayılanı yerine sistemden gelen token.
- **rule-hover / row-hover / disabled-surface / disabled-ink / danger-field / scrim** — etkileşim ve durum tonları; hover, devre dışı, hatalı alan ve mobil ülke seçici örtüsü.
- **logo-red** — yalnız logo varlığında bulunan marka rengi; arayüz tokeni değildir.

## Typography

Tek bir sistem UI yığını her şeyi taşır (misafir portalı, panel tabloları, etiketler). Ölçek sabit
rem basamaklarıdır, akışkan clamp yok: 12px bölüm etiketi, 13px etiket, 14px yardımcı, 15px gövde,
16px portal başlığı, 17/20px panel başlıkları, 19/21px kod.
Kod alanı tek monospace kullanımıdır ve hak eder: oradaki içerik sayıdır, ölçümdür. Sayısal hücreler
`font-variant-numeric: tabular-nums` alır; tablolar sağa hizalı sayı sütunlarında okunur.

## Layout

Tek kolon, `min(100%, 640px)` kapsayıcı, 16–26px arası boşluk ritmi. Misafir portalı mobil
önceliklidir ama yalnız telefonda değil: 390, 651 ve 1280 piksel genişliklerde aynı sol çizgi
korunur. Panelde içerik 1180px'e kadar genişler; tablolar kendi yatay kaydırma kabında yaşar,
sayfayı taşırmaz. Kırılma noktaları: 560px (portal yoğunluk), 700px (panel başlık ve girintiler).

## Elevation & Depth

İki seviye: kart (`0 1px 2px rgba(17,20,23,.05), 0 12px 28px -18px rgba(17,20,23,.25)`) ve düz yüzey.
Gölge her zaman eğimli ve yumuşaktır; sıfır ofsetli renkli halo yok. Panelde derinlik kutuyla değil
kuralla anlatılır — bölüm kuralı ve girinti rayı.

## Shapes

Köşe yarıçapı üç basamak: `xs: 6px` (odak halkası), `sm: 10px` (girdi, düğme, mesaj, dil) ve `md: 14px` (kart, çubuk).
Kutular yerine çizgiler: bölümü bir üst kural açar, alt öğeler 1px dikey ray üzerinde girintilenir.
Form dili nötr ve tanıdıktır — özel kontrol yok, tarayıcının kendi onay kutusu `accent-color` ile boyanır.

## Components

- **button-primary** — tam genişlik, 48px, `brand` zemin; hover `brand-hover`; aktifken 1px aşağı; `disabled` gri ve imleç `default`.
- **input-text / input-code** — 46px, 1px `rule-strong` kenar; odakta halka; hata durumunda kenar `danger-rule` ve zemin `#fffafa`. Kod alanı mono + `letter-spacing` + altı çentik gösterge.
- **tab / tab-active** — düz metin sekme; aktif olan 2px `brand` alt çizgi taşır, etiket sola hizalıdır.
- **nav-item-current** — panel üst çubuğunda `shell` zemin + 2px iç alt çizgi (`aria-current="page"`).
- **notice-danger / message-success / message-error** — yüzey + 1px kural + metin; kalın renkli sol kenar kullanılmaz, çoklu hata satırı `pre-line` ile korunur.
- **code-field** — imza bileşeni: odaklandığında soldan büyüyen 2px kural ve altı çentik ölçeği.

## Motion

Tek yazılmış an: kartın 300ms'lik girişi (`translateY(6px)` + opaklık, `cubic-bezier(.2,.8,.2,1)`),
ve kod alanının odak kuralı (220ms, soldan büyür). Diğer tüm geçişler 160ms ve yalnız durum
bildirir: hover, odak, aktif sekme. `prefers-reduced-motion` hepsini durdurur.
