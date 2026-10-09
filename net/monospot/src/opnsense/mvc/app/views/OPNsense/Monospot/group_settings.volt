{#
 # Monospot > Gruplar > Grup Portal Ayarları
 # Grup seçilir; o grubun settings_group<id>.inc dosyası yazılır, boş alanlar global değeri alır.
 #}
<script>
$(document).ready(function () {
    let selected = '{{ selectedGroupId }}';

    function fillGroupSelect(groups) {
        let select = $('#group_picker');
        select.empty();
        groups.forEach(function (group) {
            select.append($('<option/>').val(group.uuid).text(group.name + ' (' + group.uuid + ')'));
        });
        if (selected === '' && groups.length) {
            selected = groups[0].uuid;
        }
        select.val(selected);
        select.selectpicker('refresh');
    }

    function loadSettings(groupId) {
        if (!groupId) {
            $('#group_settings_note').text('{{ lang._("Önce bir grup tanımlayın (Gruplar sayfası).") }}');
            return;
        }
        $('#group_settings_note').text('{{ lang._("Yükleniyor...") }}');
        ajaxCall('/api/monospot/group_settings/get/' + groupId, {}, function (data, status) {
            if (!data || data.error || !data.settings) {
                $('#group_settings_note').text((data && data.error) ? data.error : '{{ lang._("Ayarlar okunamadı.") }}');
                return;
            }
            // form alanlarını doldur: id'ler settings.<anahtar>
            Object.keys(data.settings).forEach(function (key) {
                let field = document.getElementById('settings.' + key);
                if (!field) {
                    return;
                }
                if (field.type === 'checkbox') {
                    field.checked = (data.settings[key] === '1');
                } else if (field.tagName === 'SELECT') {
                    $(field).selectpicker('val', data.settings[key]);
                } else {
                    field.value = data.settings[key];
                }
            });
            let own = (data.group && parseInt(data.group.own_keys)) || 0;
            $('#group_settings_note').text(own > 0
                ? '{{ lang._("Bu grubun kendi ayar dosyası var; kaydedince güncellenir.") }}'
                : '{{ lang._("Bu grubun kendi ayarı yok; alanlar global değerleri gösteriyor, kaydedince grup için sabitlenir.") }}');
        });
    }

    ajaxCall('/api/monospot/group_settings/groups', {}, function (data, status) {
        if (data && data.groups) {
            fillGroupSelect(data.groups);
            loadSettings(selected);
        }
    });

    $('#group_picker').change(function () {
        selected = $(this).val();
        loadSettings(selected);
    });

    /* sekmeler ayrı formlardır; hepsi seçili gruba kaydeder */
    {% for tab in groupSettingsForm['tabs'] %}
    $("#save_{{ tab['tab_id'] }}").click(function () {
        if (!selected) {
            return;
        }
        saveFormToEndpoint(url = "/api/monospot/group_settings/set/" + selected, formid = 'frm_{{ tab['tab_id'] }}',
            callback_ok = function () {
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_INFO,
                    title: '{{ lang._("Grup ayarları") }}',
                    message: '{{ lang._("Kaydedildi.") }}',
                    draggable: true
                });
                loadSettings(selected);
            });
    });
    {% endfor %}
});
</script>
<div class="content-box">
    <div class="row">
        <div class="col-md-12">
            <div class="form-inline" style="margin-bottom: 15px;">
                <label for="group_picker">{{ lang._("Grup") }}</label>
                <select id="group_picker" class="selectpicker" data-width="240px" data-live-search="true"></select>
                <span class="text-muted" id="group_settings_note"></span>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':groupSettingsForm]) }}
</ul>
<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':groupSettingsForm]) }}
    <div class="row">
        <div class="col-md-12">
            <hr/>
            <a href="/ui/monospot/groups">{{ lang._("Grup listesine dön") }}</a>
        </div>
    </div>
</div>
