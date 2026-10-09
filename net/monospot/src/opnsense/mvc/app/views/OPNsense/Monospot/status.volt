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
        var rows = [];
        function row(k, v) { rows.push('<tr><th style="width:32%">' + k + '</th><td>' + v + '</td></tr>'); }
        row('{{ lang._("Portal servisi") }}', data.enabled ? '{{ lang._("etkin") }}' : '{{ lang._("kapalı") }}');
        row('{{ lang._("Portal adresi") }}', 'http://' + window.location.hostname + ':' + data.portal_port + '/');
        row('{{ lang._("Zone") }}', data.zoneid
            + (data.zone.description ? ' — ' + data.zone.description : '')
            + (data.zone.interfaces.length ? ' (' + data.zone.interfaces.join(', ') + ')' : ''));
        row('{{ lang._("Boşta kalma / azami oturum (dk)") }}', data.zone.idletimeout + ' / ' + data.zone.hardtimeout);
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
});
</script>
<div class="content-box">
    <div class="row">
        <div class="col-md-12">
            <h2>
                {{ lang._("Durum") }}
                <button class="btn btn-default btn-xs" id="monospot_info_refresh">{{ lang._("Yenile") }}</button>
            </h2>
            <div id="monospot_info"><em>{{ lang._("Yükleniyor...") }}</em></div>
            <hr/>
            <a class="btn btn-default" href="/ui/monospot">{{ lang._("Servis ayarları") }}</a>
            <a class="btn btn-default" href="/ui/monospot/captive_portal">{{ lang._("Captive Portal") }}</a>
            <a class="btn btn-default" href="/5651/" target="_blank">{{ lang._("5651 günlük arşivi") }}</a>
            <a class="btn btn-default" href="/monospot.php">{{ lang._("Monospot yönetim paneli") }}</a>
        </div>
    </div>
</div>
