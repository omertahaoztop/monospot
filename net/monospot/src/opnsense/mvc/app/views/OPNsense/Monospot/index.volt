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

    function renderMonospotInfo(data) {
        if (!data || data.error) {
            $("#monospot_info").html('<em>' + ((data && data.error) ? data.error : '—') + '</em>');
            return;
        }
        var rows = [];
        function row(k, v) { rows.push('<tr><th style="width:32%">' + k + '</th><td>' + v + '</td></tr>'); }
        row('{{ lang._("Portal servisi") }}', data.enabled ? '{{ lang._("etkin") }}' : '{{ lang._("kapalı") }}');
        row('{{ lang._("Portal adresi") }}', 'http://' + window.location.hostname + ':' + data.portal_port + '/');
        row('{{ lang._("Zone") }}', data.zoneid
            + (data.zone.description ? ' — ' + data.zone.description : '')
            + (data.zone.interfaces.length ? ' (' + data.zone.interfaces.join(', ') + ')' : ''));
        row('{{ lang._("Yetkili istemci") }}', data.clients.authorized
            + (data.clients.sample.length ? '<br><small>' + data.clients.sample.join('<br>') + '</small>' : ''));
        row('{{ lang._("Veritabanı") }}', data.db.users + ' {{ lang._("kullanıcı") }}, '
            + data.db.sms + ' SMS, ' + data.db.permissions + ' {{ lang._("izin kaydı") }}');
        row('5651', (data.collect5651 ? '{{ lang._("açık") }}' : '{{ lang._("kapalı") }}')
            + ' — ' + data.archive.days + ' {{ lang._("gün arşiv") }}, {{ lang._("saklama") }} '
            + data.retention_days + ' {{ lang._("gün") }}');
        row('{{ lang._("SMS sağlayıcı") }}', data.sms.provider_file
            ? '{{ lang._("yapılandırıldı (sms.php)") }}'
            : '{{ lang._("dosya yok — sms.php yerleştirilmeli") }}');
        $("#monospot_info").html('<table class="table table-condensed table-striped">' + rows.join('') + '</table>');
    }

    $("#monospot_info_refresh").click(function () {
        $("#monospot_info").html('<em>{{ lang._("Yükleniyor...") }}</em>');
        ajaxCall(url = "/api/monospot/service/info", sendData = {}, callback = function (data, status) {
            renderMonospotInfo(data);
        });
    });

    $("#monospot_info_refresh").trigger('click');

    $("#collect5651").click(function () {
        $("#collect5651_progress").addClass("fa fa-spinner fa-pulse");
        ajaxCall(url = "/api/monospot/service/collect5651", sendData = {}, callback = function (data, status) {
            $("#collect5651_progress").removeClass("fa fa-spinner fa-pulse");
            BootstrapDialog.show({
                type: (status == "success" && data['status'] == 'ok') ? BootstrapDialog.TYPE_INFO : BootstrapDialog.TYPE_WARNING,
                title: '{{ lang._("5651 günlük arşivi") }}',
                message: '<pre>' + JSON.stringify(data, null, 2) + '</pre>',
                draggable: true
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
            <hr/>
            <h4>
                {{ lang._("Durum") }}
                <button class="btn btn-default btn-xs" id="monospot_info_refresh">{{ lang._("Yenile") }}</button>
            </h4>
            <div id="monospot_info"><em>{{ lang._("Yükleniyor...") }}</em></div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <button class="btn btn-default" id="install_zonetemplate">{{ lang._("Zone şablonunu kur") }}</button>
            <i id="install_zonetemplate_progress"></i>
            <button class="btn btn-default" id="collect5651">{{ lang._("5651 arşivini şimdi topla") }}</button>
            <i id="collect5651_progress"></i>
            <a class="btn btn-default" href="/5651/" target="_blank">{{ lang._("5651 günlük arşivi") }}</a>
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
