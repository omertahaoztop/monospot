{#
 # Monospot > Captive Portal: core captive portal zone ayarlarının yüzü.
 # Veri core modelde tutulur; form yalnız okuma/yazma köprüsüdür.
 #}
<script>
$(document).ready(function () {
    function loadCaptivePortal() {
        mapDataToFormUI({'frm_captiveportal': '/api/monospot/captive_portal/get'}).done(function () {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });
    }

    loadCaptivePortal();

    $("#save_captiveportal").click(function () {
        $("#save_captiveportal_progress").addClass("fa fa-spinner fa-pulse");
        saveFormToEndpoint(url = "/api/monospot/captive_portal/set", formid = 'frm_captiveportal',
            callback_ok = function () {
                $("#save_captiveportal_progress").removeClass("fa fa-spinner fa-pulse");
                loadCaptivePortal();
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_INFO,
                    title: '{{ lang._("Captive Portal") }}',
                    message: '{{ lang._("Ayarlar core captive portal zone kaydına yazıldı; captive portal yeniden başlatıldı.") }}',
                    draggable: true
                });
            });
    });
});
</script>
<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':captivePortalForm]) }}
</ul>
<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':captivePortalForm]) }}
</div>
