{#
 # Monospot > Durum: Servis Ayarları sayfasıyla aynı çerçeve (sekme + bilgi satırları).
 # Değerler /api/monospot/service/info'dan doldurulur; alttaki düğme "Yenile" işlevi görür.
 #}
<script>
$(document).ready(function () {
    function badge(ok, text) {
        return '<span class="label label-opnsense label-opnsense-xs label-' + (ok ? 'success' : 'danger') + '">' + text + '</span>';
    }
    function setInfo(id, value) {
        var el = document.getElementById(id);
        if (el) {
            el.innerHTML = value;
        }
    }
    function render(data) {
        if (!data || data.error) {
            setInfo('status.portal_servisi', (data && data.error) ? data.error : '—');
            return;
        }
        var port = 'http://' + window.location.hostname + ':' + data.portal_port + '/';
        setInfo('status.portal_servisi', badge(data.enabled, data.enabled ? '{{ lang._("etkin") }}' : '{{ lang._("kapalı") }}'));
        setInfo('status.portal_adresi', '<a href="' + port + '" target="_blank">' + port + '</a>');
        setInfo('status.zone', data.zoneid
            + (data.zone.description ? ' — ' + data.zone.description : '')
            + (data.zone.interfaces.length ? ' (' + data.zone.interfaces.join(', ') + ')' : ''));
        setInfo('status.zone_sablonu', data.zone.template_name === ''
            ? badge(false, '{{ lang._("kurulmamış") }}')
            : badge(true, data.zone.template_name));
        setInfo('status.timeouts', data.zone.idletimeout + ' / ' + data.zone.hardtimeout + ' {{ lang._("dakika") }}');
        setInfo('status.yetkili_istemci', data.clients.authorized
            + ' — <a href="/ui/monospot/sessions">{{ lang._("Oturumlar") }}</a>');
        setInfo('status.veritabani', data.db.users + ' {{ lang._("kullanıcı") }}, ' + data.db.sms + ' SMS, '
            + data.db.permissions + ' {{ lang._("izin kaydı") }}');
        setInfo('status.arsiv', badge(data.collect5651, data.collect5651 ? '{{ lang._("açık") }}' : '{{ lang._("kapalı") }}')
            + ' — ' + data.archive.days + ' {{ lang._("gün") }}, {{ lang._("saklama") }} ' + data.retention_days + ' {{ lang._("gün") }}');
        setInfo('status.sms', data.sms.provider_file
            ? badge(true, '{{ lang._("yapılandırıldı") }}')
            : badge(false, '{{ lang._("sms.php yok") }}'));
    }
    function load() {
        ajaxCall('/api/monospot/service/info', {}, function (data, status) { render(data); });
    }

    /* Apply düğmesi bu sayfada "Yenile" işlevi görür (salt-okunur sayfa) */
    $('#save_status').text('{{ lang._("Yenile") }}').off('click').click(function (event) {
        event.preventDefault();
        load();
    });

    load();
});
</script>
<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':statusForm]) }}
</ul>
<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':statusForm]) }}
</div>
