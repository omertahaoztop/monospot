{#
 # Monospot servis ayarları sayfası.
 #}
<script>
$(document).ready(function () {
    mapDataToFormUI({'frm_monospot': '/api/monospot/settings/get'}).done(function () {
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });
    updateServiceControlUI('monospot');

    $("#save_monospot").click(function () {
        saveFormToEndpoint(url = "/api/monospot/settings/set", formid = 'frm_monospot', callback_ok = function () {
            $("#frm_monospot_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url = "/api/monospot/service/reconfigure", sendData = {}, callback = function (data, status) {
                $("#frm_monospot_progress").removeClass("fa fa-spinner fa-pulse");
                if (status != "success" || data['status'] != 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: '{{ lang._("Monospot") }}',
                        message: JSON.stringify(data),
                        draggable: true
                    });
                } else {
                    updateServiceControlUI('monospot');
                }
            });
        });
    });

    $("#install_zonetemplate").click(function () {
        stdDialogConfirm(
            '{{ lang._("Zone şablonu") }}',
            '{{ lang._("Seçili zone bu portalı gösterecek şekilde güncellenecek ve captive portal yeniden başlatılacak. Devam edilsin mi?") }}',
            '{{ lang._("Evet") }}', '{{ lang._("İptal") }}', function () {
                $("#install_zonetemplate_progress").addClass("fa fa-spinner fa-pulse");
                ajaxCall(url = "/api/monospot/service/installzonetemplate", sendData = {}, callback = function (data, status) {
                    $("#install_zonetemplate_progress").removeClass("fa fa-spinner fa-pulse");
                    if (status != "success" || data['status'] != 'ok') {
                        BootstrapDialog.show({
                            type: BootstrapDialog.TYPE_WARNING,
                            title: '{{ lang._("Zone şablonu") }}',
                            message: JSON.stringify(data),
                            draggable: true
                        });
                    }
                });
            });
    });
});
</script>
<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':generalForm]) }}
</ul>
<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':generalForm]) }}
    <div class="row">
        <div class="col-md-12">
            <button class="btn btn-default" id="install_zonetemplate">{{ lang._("Zone şablonunu kur") }}</button>
            <i id="install_zonetemplate_progress"></i>
            <a class="btn btn-default" href="/monospot.php">{{ lang._("Monospot yönetim paneli") }}</a>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <hr/>
            <div id="service_status_container"></div>
        </div>
    </div>
</div>
