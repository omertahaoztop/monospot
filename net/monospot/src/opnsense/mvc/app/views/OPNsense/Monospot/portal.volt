{#
 # Monospot > Portal: portal durumu, zone şablonu kurulumu.
 # Veri /api/monospot/service/info (portal + zone + şablon), eylem installzonetemplate.
 #}
<script>
$(document).ready(function () {
    function renderPortalInfo(data) {
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
            setInfo('portal_status.portal_servisi', (data && data.error) ? data.error : '—');
            return;
        }
        var url = 'http://' + window.location.hostname + ':' + data.portal_port + '/';
        setInfo('portal_status.portal_servisi', badge(data.enabled, data.enabled ? '{{ lang._("etkin") }}' : '{{ lang._("kapalı") }}'));
        setInfo('portal_status.portal_adresi', '<a href="' + url + '" target="_blank">' + url + '</a>');
        /* canlı portal önizlemesi */
        var frame = document.getElementById('portal_preview');
        if (frame && frame.getAttribute('src') !== url) {
            frame.setAttribute('src', url);
            frame.style.display = 'block';
            var empty = document.getElementById('portal_preview_empty');
            if (empty) { empty.style.display = 'none'; }
        }
        setInfo('portal_status.zone', data.zoneid
            + (data.zone.description ? ' — ' + data.zone.description : '')
            + (data.zone.interfaces.length ? ' (' + data.zone.interfaces.join(', ') + ')' : ''));
        setInfo('portal_status.zone_sablonu', data.zone.template_name === ''
            ? badge(false, '{{ lang._("kurulmamış — istemci portalı görmez") }}')
            : badge(true, data.zone.template_name));
    }

    function loadPortalInfo() {
        ajaxCall(url = "/api/monospot/service/info", sendData = {}, callback = function (data, status) {
            renderPortalInfo(data);
        });
    }

    loadPortalInfo();

    /* Apply düğmesi bu sayfada zone şablonunu kurar */
    $('#save_portal_status').text('{{ lang._("Zone şablonunu kur") }}').off('click').click(function (event) {
        event.preventDefault();
        stdDialogConfirm(
            '{{ lang._("Zone şablonu") }}',
            '{{ lang._("Seçili zone bu portalı gösterecek şekilde güncellenecek ve captive portal yeniden başlatılacak. Devam edilsin mi?") }}',
            '{{ lang._("Evet") }}', '{{ lang._("İptal") }}', function () {
                ajaxCall(url = "/api/monospot/service/installzonetemplate", sendData = {}, callback = function (data, status) {
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
<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':portalStatusForm]) }}
</ul>
<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':portalStatusForm]) }}
    <div class="row">
        <div class="col-md-12">
            <hr/>
            <h4>{{ lang._("Portal önizleme") }}</h4>
            <p class="text-muted">
                {{ lang._("Canlı portal sayfası (giriş ekranı). Logo ve renk Ayarlar → Erişim ekranı sekmesinden değiştirilir:") }}
                <a href="/ui/monospot">{{ lang._("Ayarlar") }}</a>
            </p>
            <div id="portal_preview_empty" class="text-muted">{{ lang._("Yükleniyor...") }}</div>
            <iframe id="portal_preview" style="display:none; width:100%; max-width:420px; height:560px;
                    border:1px solid #dfe3e8; background:#fff;"
                    referrerpolicy="no-referrer"></iframe>
        </div>
    </div>
</div>
