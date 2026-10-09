{#
 # Monospot > Portal > Uygulama Ayarları: portal (settings.inc) ve marka (hotspot.ini).
 # Standart sekme + Apply düzeni; veri /api/monospot/portal_settings/{get,set}.
 #}
<script>
$(document).ready(function () {
    /* sekmeler ayrı formlardır: değerler her formun kendi alanlarına yazılır */
    {% for tab in portalSettingsForm['tabs'] %}
    mapDataToFormUI({'frm_{{ tab['tab_id'] }}': '/api/monospot/portal_settings/get'}).done(function () {
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });
    {% endfor %}

    /* her sekme kendi formudur (frm_<tab_id>); Apply düğmesi save_<tab_id> */
    {% for tab in portalSettingsForm['tabs'] %}
    $("#save_{{ tab['tab_id'] }}").click(function () {
        saveFormToEndpoint(url = "/api/monospot/portal_settings/set", formid = 'frm_{{ tab['tab_id'] }}', callback_ok = function () {
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_INFO,
                title: '{{ lang._("Portal ayarları") }}',
                message: '{{ lang._("Ayarlar kaydedildi. Değişiklikler portalda bir sonraki istekte geçerli olur.") }}',
                draggable: true
            });
        });
    });
    {% endfor %}
});
</script>
<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':portalSettingsForm]) }}
</ul>
<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':portalSettingsForm]) }}
    <div class="row">
        <div class="col-md-12">
            <hr/>
            <span class="text-muted">{{ lang._("Logo yükleme, KVKK izin metinleri ve kullanıcı grubu ayarları Yönetim Paneli'nde kalır.") }}</span>
        </div>
    </div>
</div>
