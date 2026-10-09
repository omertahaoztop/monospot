{#
 # Monospot > 5651 Günlükleri: arşiv özeti, elle toplama, tarayıcı bağlantısı.
 # Veri /api/monospot/service/info; eylem /api/monospot/service/collect5651.
 #}
<script>
$(document).ready(function () {
    function renderArchive(data) {
        if (!data || data.error) {
            $("#archive_info").html('<em>' + ((data && data.error) ? data.error : '—') + '</em>');
            return;
        }
        var rows = [];
        function row(k, v) { rows.push('<tr><th style="width:32%">' + k + '</th><td>' + v + '</td></tr>'); }
        row('{{ lang._("Durum") }}', data.collect5651
            ? '{{ lang._("açık") }}'
            : '<span class="text-danger">{{ lang._("kapalı — kayıt tutulmuyor") }}</span>');
        row('{{ lang._("Saklama süresi") }}', data.retention_days + ' {{ lang._("gün") }}');
        row('{{ lang._("Arşiv") }}', data.archive.days + ' {{ lang._("gün") }}'
            + (data.archive.oldest ? ' (' + data.archive.oldest + ' → ' + data.archive.newest + ')' : ''));
        row('{{ lang._("Arşiv boyutu") }}', (data.archive.bytes / 1024).toFixed(1) + ' KB');
        row('{{ lang._("Toplama zamanı") }}', '{{ lang._("her gün 23:59 (root crontab)") }}');
        row('{{ lang._("İşlem") }}',
            '<a href="#" id="collect5651">{{ lang._("Arşivi şimdi topla") }}</a> <i id="collect5651_progress"></i>'
            + ' &nbsp;·&nbsp; <a href="/5651/" target="_blank">{{ lang._("Günlük tarayıcısını aç") }}</a>'
            + ' &nbsp;·&nbsp; <a href="/ui/monospot/archive_settings">{{ lang._("5651 ayarları") }}</a>');
        $("#archive_info").html(
            '<table class="table table-condensed table-hover table-striped table-responsive">'
            + '<thead><tr><th>{{ lang._("Öğe") }}</th><th>{{ lang._("Değer") }}</th></tr></thead>'
            + '<tbody>' + rows.join('') + '</tbody></table>');
    }

    function loadArchive() {
        ajaxCall(url = "/api/monospot/service/info", sendData = {}, callback = function (data, status) {
            renderArchive(data);
        });
    }

    loadArchive();

    $(document).on('click', '#collect5651', function (event) {
        event.preventDefault();
        $("#collect5651_progress").addClass("fa fa-spinner fa-pulse");
        ajaxCall(url = "/api/monospot/service/collect5651", sendData = {}, callback = function (data, status) {
            $("#collect5651_progress").removeClass("fa fa-spinner fa-pulse");
            BootstrapDialog.show({
                type: (status == "success" && data['status'] == 'ok') ? BootstrapDialog.TYPE_INFO : BootstrapDialog.TYPE_WARNING,
                title: '{{ lang._("5651 günlük arşivi") }}',
                message: '<pre>' + JSON.stringify(data, null, 2) + '</pre>',
                draggable: true
            });
            loadArchive();
        });
    });
});
</script>
<div class="content-box">
    <div class="row">
        <div class="col-md-12">
            <h2 style="margin-top: 0;">5651</h2>
            <div id="archive_info"><em>{{ lang._("Yükleniyor...") }}</em></div>
        </div>
    </div>
</div>
