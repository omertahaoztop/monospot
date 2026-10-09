{#
 # Monospot > Portal: portal durumu, zone şablonu kurulumu.
 # Veri /api/monospot/service/info (portal + zone + şablon), eylem installzonetemplate.
 #}
<script>
$(document).ready(function () {
    function renderPortalInfo(data) {
        if (!data || data.error) {
            $("#portal_info").html('<em>' + ((data && data.error) ? data.error : '—') + '</em>');
            return;
        }
        function badge(ok, text) {
            return '<span class="label label-opnsense label-opnsense-xs label-' + (ok ? 'success' : 'danger') + '">' + text + '</span>';
        }
        var rows = [];
        function row(k, v) { rows.push('<tr><th class="col-md-4">' + k + '</th><td>' + v + '</td></tr>'); }
        var url = 'http://' + window.location.hostname + ':' + data.portal_port + '/';
        row('{{ lang._("Portal adresi") }}', '<a href="' + url + '" target="_blank">' + url + '</a>');
        row('{{ lang._("Zone") }}', data.zoneid
            + (data.zone.description ? ' — ' + data.zone.description : '')
            + (data.zone.interfaces.length ? ' (' + data.zone.interfaces.join(', ') + ')' : ''));
        row('{{ lang._("Zone şablonu") }}', data.zone.template_name === ''
            ? badge(false, '{{ lang._("kurulmamış — istemci portalı görmez") }}')
            : badge(true, data.zone.template_name));
        row('{{ lang._("Servis") }}', badge(data.enabled, data.enabled ? '{{ lang._("etkin") }}' : '{{ lang._("kapalı") }}'));
        $("#portal_info").html(
            '<table class="table table-condensed table-hover table-striped table-responsive">'
            + '<thead><tr><th>{{ lang._("Öğe") }}</th><th>{{ lang._("Değer") }}</th></tr></thead>'
            + '<tbody>' + rows.join('') + '</tbody></table>');
    }

    function loadPortalInfo() {
        ajaxCall(url = "/api/monospot/service/info", sendData = {}, callback = function (data, status) {
            renderPortalInfo(data);
        });
    }

    loadPortalInfo();

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
                    } else {
                        loadPortalInfo();
                    }
                });
            });
    });
});
</script>
<div class="content-box">
    <div class="row">
        <div class="col-md-12">
            <h2>{{ lang._("Portal") }}</h2>
            <div id="portal_info"><em>{{ lang._("Yükleniyor...") }}</em></div>
            <hr/>
            <button class="btn btn-default" id="install_zonetemplate">{{ lang._("Zone şablonunu kur") }}</button>
            <i id="install_zonetemplate_progress"></i>
        </div>
    </div>
</div>
