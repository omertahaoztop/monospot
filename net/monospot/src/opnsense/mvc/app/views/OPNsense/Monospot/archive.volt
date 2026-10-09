{#
 # Monospot > 5651 Günlükleri: arşiv özeti, elle toplama, tarayıcı bağlantısı.
 # Veri /api/monospot/service/info; eylem /api/monospot/service/collect5651.
 #}
<script>
$(document).ready(function () {
    function renderArchive(data) {
        function badge(ok, text) {
            return '<span class="label label-opnsense label-opnsense-xs label-' + (ok ? 'success' : 'danger') + '">' + text + '</span>';
        }
        function setInfo(id, value) {
            var el = document.getElementById(id);
            if (el) {
                el.innerHTML = value;
            }
        }
        if (!data || data.error) {
            setInfo('archive_status.durum', (data && data.error) ? data.error : '—');
            return;
        }
        setInfo('archive_status.durum', badge(data.collect5651, data.collect5651
            ? '{{ lang._("açık") }}'
            : '{{ lang._("kapalı — kayıt tutulmuyor") }}'));
        setInfo('archive_status.saklama', data.retention_days + ' {{ lang._("gün") }}');
        setInfo('archive_status.arsiv', data.archive.days + ' {{ lang._("gün") }}'
            + (data.archive.oldest ? ' (' + data.archive.oldest + ' → ' + data.archive.newest + ')' : ''));
        setInfo('archive_status.boyut', (data.archive.bytes / 1024).toFixed(1) + ' KB');
        setInfo('archive_status.zamanlama', '{{ lang._("her gün 23:59 (root crontab)") }}');
    }

    function loadArchive() {
        ajaxCall(url = "/api/monospot/service/info", sendData = {}, callback = function (data, status) {
            renderArchive(data);
        });
    }

    loadArchive();

    /* Apply düğmesi bu sayfada arşivi hemen toplar */
    $('#save_archive_status').text('{{ lang._("Arşivi şimdi topla") }}').off('click').click(function (event) {
        event.preventDefault();
        ajaxCall(url = "/api/monospot/service/collect5651", sendData = {}, callback = function (data, status) {
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
<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':archiveStatusForm]) }}
</ul>
<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':archiveStatusForm]) }}
</div>
