---
version: 1
slug: "app-views-layouts-captiveportal-html-php-2c54a4e9"
primary_target: "net/monospot/src/opnsense/scripts/monospot/app/views/layouts/captiveportal.html.php"
related_targets: ["net/monospot/src/opnsense/scripts/monospot/app/assets/css/captiveportal-style.css","net/monospot/src/opnsense/scripts/monospot/app/assets/js/captiveportal.js"]
---

# Portal (captive portal) — surface brief

Mode: Operate (visitor completes a task: register / sign in)

## Direction contract

THESIS: Sakin bir araç — portal bir eylem sayfasıdır: marka tek satır, adım göstergesi, sekmeler ve tek kolonlu form; hepsi aynı sol çizgiden başlar. Reddettiği kategori varsayılanı: renkli başlık bandı, iç içe kutu, her yerde tek vurgu rengi.

OWN-WORLD: Kâğıt beyazı yüzey, koyu grafit mürekkep, ince gri kurallar; renk yalnız iş yaptığında (birincil eylem, aktif sekme, odak halkası, hata/başarı). Varsayılan vurgu mürekkeptir ve panelden değiştirilebilir; marka kırmızısı yalnız logo dosyasında yaşar. Tipografi tek sistem UI yığını; kod alanı tek monospace kullanımıdır.

STORY: Misafir iki adımı bir bakışta görür, kodun nereye gireceğini bilir, hata olduğunda ne olduğunu ve ne yapacağını okur.

FIRST VIEWPORT: Üstte tek satır marka (güncel logo 26px + mekân adı + soluk wifi işareti), altında kart: adım rayı, sola hizalı sekmeler, etiketli alan, kod alanı (odakta soldan büyüyen 2px kural + altı çentik) ve tam genişlik birincil eylem; alt satırda dil anahtarı.

FORM: Yerleşik dünyanın portal gerçeklemesi (yön kullanıcı tarafından sabitlendi: nötr + tek ince aksan); kompozisyon: marka satırı → kart → tek kolon form.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance.
