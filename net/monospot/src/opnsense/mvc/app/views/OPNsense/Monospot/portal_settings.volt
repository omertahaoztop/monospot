{#
 # Monospot > Portal > Uygulama Ayarları: portal (settings.inc) ve marka (hotspot.ini).
 # Standart sekme + Apply düzeni; veri /api/monospot/portal_settings/{get,set}.
 #}
<script>
$(document).ready(function () {
    mapDataToFormUI({'frm_portal': '/api/monospot/portal_settings/get'}).done(function () {
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    $("#save_portal").click(function () {
        saveFormToEndpoint(url = "/api/monospot/portal_settings/set", formid = 'frm_portal', callback_ok = function () {
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_INFO,
                title: '{{ lang._("Portal ayarları") }}',
                message: '{{ lang._("Ayarlar kaydedildi. Değişiklikler portalda bir sonraki istekte geçerli olur.") }}',
                draggable: true
            });
        });
    });
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
