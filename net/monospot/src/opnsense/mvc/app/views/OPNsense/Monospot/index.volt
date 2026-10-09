{#
 # Monospot Ayarları: servis (config.xml) + portal uygulama ayarları (settings.inc/hotspot.ini).
 # Sekmeler ayrı formlardır; servis sekmesi /api/monospot/settings, kalanı /api/monospot/portal_settings.
 #}
<script>
$(document).ready(function () {
    /* sekme -> uç eşlemesi: servis sekmesi config'e (settings.*), kalanı uygulama dosyalarına yazılır */
    function loadTab(tab) {
        var request = {};
        request['frm_' + tab] = (tab === 'monospot') ? '/api/monospot/settings/get' : '/api/monospot/portal_settings/get';
        mapDataToFormUI(request).done(function () {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });
    }

    /* zone listesi core captive portal modelinden gelir; açılır liste JS ile doldurulur */
    ajaxCall(url = "/api/monospot/service/zones", sendData = {}, callback = function (data, status) {
        var select = document.getElementById('monospot.general.zoneid');
        var zones = (data && data.zones) ? data.zones : [];
        if (select) {
            zones.forEach(function (zone) {
                var option = document.createElement('option');
                option.value = zone.zoneid;
                option.text = zone.zoneid + ' — ' + (zone.description === '' ? '{{ lang._("açıklamasız") }}' : zone.description)
                    + (zone.interfaces === '' ? '' : ' (' + zone.interfaces + ')');
                select.appendChild(option);
            });
            $('.selectpicker').selectpicker('refresh');
        }
        loadTab('monospot');
    });

    updateServiceControlUI('monospot');

    {% for tab in generalForm['tabs'] %}
    loadTab('{{ tab['tab_id'] }}');
    $("#save_{{ tab['tab_id'] }}").click(function () {
        var tab = '{{ tab['tab_id'] }}';
        if (tab === 'monospot') {
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
        } else {
            saveFormToEndpoint(url = "/api/monospot/portal_settings/set", formid = 'frm_' + tab, callback_ok = function () {
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_INFO,
                    title: '{{ lang._("Portal ayarları") }}',
                    message: '{{ lang._("Ayarlar kaydedildi. Değişiklikler portalda bir sonraki istekte geçerli olur.") }}',
                    draggable: true
                });
                loadTab(tab);
            });
        }
    });
    {% endfor %}

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
