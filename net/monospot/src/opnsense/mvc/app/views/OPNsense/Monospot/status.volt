{#
 # Monospot > Durum: servis, portal, zone, yetkili istemci, veritabanı ve 5651 özeti.
 # Veri /api/monospot/service/info → configctl monospot info (status.php).
 #}
<script>
$(document).ready(function () {
    function renderMonospotInfo(data) {
        if (!data || data.error) {
            $("#monospot_info").html('<em>' + ((data && data.error) ? data.error : '—') + '</em>');
            return;
        }
        function badge(ok, text) {
            return '<span class="label label-opnsense label-opnsense-xs label-' + (ok ? 'success' : 'danger') + '">' + text + '</span>';
        }
        var rows = [];
        function row(k, v) { rows.push('<tr><th class="col-md-4">' + k + '</th><td>' + v + '</td></tr>'); }
        row('{{ lang._("Portal servisi") }}', badge(data.enabled, data.enabled ? '{{ lang._("etkin") }}' : '{{ lang._("kapalı") }}'));
        row('{{ lang._("Portal adresi") }}', '<a href="http://' + window.location.hostname + ':' + data.portal_port + '/" target="_blank">http://'
            + window.location.hostname + ':' + data.portal_port + '/</a>');
        row('{{ lang._("Zone") }}', data.zoneid
            + (data.zone.description ? ' — ' + data.zone.description : '')
            + (data.zone.interfaces.length ? ' (' + data.zone.interfaces.join(', ') + ')' : ''));
        row('{{ lang._("Zone şablonu") }}', data.zone.template_name === ''
            ? badge(false, '{{ lang._("kurulmamış") }}')
            : badge(true, data.zone.template_name));
        row('{{ lang._("Boşta kalma / azami oturum (dk)") }}', data.zone.idletimeout + ' / ' + data.zone.hardtimeout);
        row('{{ lang._("Veritabanı") }}', data.db.users + ' {{ lang._("kullanıcı") }}, '
            + data.db.sms + ' SMS, ' + data.db.permissions + ' {{ lang._("izin kaydı") }}');
        row('5651', badge(data.collect5651, data.collect5651 ? '{{ lang._("açık") }}' : '{{ lang._("kapalı") }}')
            + ' — ' + data.archive.days + ' {{ lang._("gün arşiv") }}, {{ lang._("saklama") }} '
            + data.retention_days + ' {{ lang._("gün") }}');
        row('{{ lang._("SMS sağlayıcı") }}', data.sms.provider_file
            ? badge(true, '{{ lang._("yapılandırıldı") }}')
            : badge(false, '{{ lang._("sms.php yok") }}'));
        $("#monospot_info").html(
            '<table class="table table-condensed table-hover table-striped table-responsive">'
            + '<thead><tr><th>{{ lang._("Öğe") }}</th><th>{{ lang._("Değer") }}</th></tr></thead>'
            + '<tbody>' + rows.join('') + '</tbody></table>');

        /* yetkili istemciler: gerçek tablo (ilk 10) */
        var clientRows = (data.clients.sample || []).map(function (c) {
            return '<tr><td>' + c.user + '</td><td>' + c.ip + '</td><td>' + c.mac + '</td><td>' + c.start + '</td></tr>';
        });
        if (clientRows.length === 0) {
            $('#monospot_clients').html('<em>{{ lang._("Yetkili istemci yok.") }}</em>');
        } else {
            $('#monospot_clients').html(
                '<table class="table table-condensed table-hover table-striped table-responsive">'
                + '<thead><tr><th>{{ lang._("Kullanıcı") }}</th><th>{{ lang._("IP adresi") }}</th>'
                + '<th>MAC</th><th>{{ lang._("Başlangıç") }}</th></tr></thead>'
                + '<tbody>' + clientRows.join('') + '</tbody></table>');
        }
    }

    $("#monospot_info_refresh").click(function () {
        $("#monospot_info").html('<em>{{ lang._("Yükleniyor...") }}</em>');
        ajaxCall(url = "/api/monospot/service/info", sendData = {}, callback = function (data, status) {
            renderMonospotInfo(data);
        });
    });

    $("#monospot_info_refresh").trigger('click');
});
</script>
<ul class="nav nav-tabs" role="tablist" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#status">{{ lang._("Durum") }}</a></li>
</ul>
<div class="content-box tab-content">
    <div id="status" class="tab-pane fade in active">
        <div class="row">
            <div class="col-md-12 text-right">
                <button class="btn btn-default btn-xs" id="monospot_info_refresh">
                    <i class="fa fa-refresh"></i> {{ lang._("Yenile") }}
                </button>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div id="monospot_info"><em>{{ lang._("Yükleniyor...") }}</em></div>
                <h4>{{ lang._("Yetkili istemciler") }}</h4>
                <div id="monospot_clients"><em>{{ lang._("Yükleniyor...") }}</em></div>
            </div>
        </div>
    </div>
</div>
