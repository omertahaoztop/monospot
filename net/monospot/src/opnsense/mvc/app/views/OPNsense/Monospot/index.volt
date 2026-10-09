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

});
</script>
<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':generalForm]) }}
</ul>
<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':generalForm]) }}
    <div class="row">
        <div class="col-md-12">
            <hr/>
            <div id="service_status_container"></div>
        </div>
    </div>
</div>
