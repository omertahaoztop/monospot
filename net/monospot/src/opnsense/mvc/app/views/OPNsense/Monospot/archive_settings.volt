{#
 # Monospot > 5651 Günlükleri > Ayarlar: arşivleme ve saklama süresi.
 # Standart sekme + Apply düzeni (servis yeniden yapılandırması cron girdisini üretir).
 #}
<script>
$(document).ready(function () {
    mapDataToFormUI({'frm_archive': '/api/monospot/settings/get'}).done(function () {
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    $("#save_archive").click(function () {
        saveFormToEndpoint(url = "/api/monospot/settings/set", formid = 'frm_archive', callback_ok = function () {
            $("#frm_archive_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url = "/api/monospot/service/reconfigure", sendData = {}, callback = function (data, status) {
                $("#frm_archive_progress").removeClass("fa fa-spinner fa-pulse");
                if (status != "success" || data['status'] != 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: '{{ lang._("5651 günlükleri") }}',
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
    {{ partial("layout_partials/base_tabs_header",['formData':archiveForm]) }}
</ul>
<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':archiveForm]) }}
    <div class="row">
        <div class="col-md-12">
            <hr/>
            <a href="/ui/monospot/archive">{{ lang._("Arşiv durumu ve toplama") }}</a>
        </div>
    </div>
</div>
