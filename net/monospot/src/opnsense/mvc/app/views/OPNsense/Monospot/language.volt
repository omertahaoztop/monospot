{#
 # Monospot > Dil: portal metinleri (app/lang/<kod>.inc).
 # Alanlar dinamik olduğu için satırlar JS ile, OPNsense alan işaretlemesiyle çizilir.
 #}
<script>
$(document).ready(function () {
    let current = 'tr';

    function field_row(key, value) {
        let label = $('<td/>').css('width', '35%').append(
            $('<span/>').addClass('text-muted').append($('<i/>').addClass('fa fa-info-circle'))
        ).append(' ').append($('<b/>').text(key));
        let input = $('<input/>').attr('type', 'text').addClass('form-control').attr('data-key', key).val(value);
        return $('<tr/>').append(label).append($('<td/>').append(input));
    }

    function render(keys, written) {
        let body = $('#lang_rows').empty();
        Object.keys(keys).forEach(function (key) {
            body.append(field_row(key, keys[key]));
        });
        $('#lang_note').text(Object.keys(keys).length + ' {{ lang._("metin") }}'
            + (written ? '' : ' — {{ lang._("dosya henüz yazılmamış, kaydedince oluşturulur") }}'));
        filter_rows();
    }

    function filter_rows() {
        let needle = $('#lang_filter').val().toLowerCase();
        $('#lang_rows tr').each(function () {
            $(this).toggle($(this).find('b').text().toLowerCase().indexOf(needle) !== -1);
        });
    }

    function load(code) {
        current = code;
        $('#lang_rows').html('<tr><td colspan="2"><em>{{ lang._("Yükleniyor...") }}</em></td></tr>');
        ajaxCall('/api/monospot/language/get/' + code, {}, function (data, status) {
            if (!data || data.error || !data.keys) {
                $('#lang_rows').html('<tr><td colspan="2"><em>' + ((data && data.error) ? data.error : '{{ lang._("Metinler okunamadı.") }}') + '</em></td></tr>');
                return;
            }
            render(data.keys, parseInt(data.written));
        });
    }

    ajaxCall('/api/monospot/language/codes', {}, function (data, status) {
        let select = $('#lang_code').empty();
        (data && data.codes ? data.codes : []).forEach(function (entry) {
            select.append($('<option/>').val(entry.code)
                .text(entry.code.toUpperCase() + (parseInt(entry.written) ? '' : ' (sample)')));
        });
        if (select.find('option').length) {
            select.selectpicker('refresh');
            load(select.val());
        }
    });

    $('#lang_code').change(function () { load($(this).val()); });
    $('#lang_filter').on('keyup', filter_rows);

    $('#save_lang').click(function () {
        let payload = {};
        $('#lang_rows input[data-key]').each(function () {
            payload[$(this).attr('data-key')] = $(this).val();
        });
        ajaxCall('/api/monospot/language/set/' + current, {lang: payload}, function (data, status) {
            BootstrapDialog.show({
                type: (status == 'success' && data['result'] == 'saved') ? BootstrapDialog.TYPE_INFO : BootstrapDialog.TYPE_WARNING,
                title: '{{ lang._("Dil metinleri") }}',
                message: (status == 'success' && data['result'] == 'saved')
                    ? '{{ lang._("Kaydedildi.") }}' : JSON.stringify(data),
                draggable: true
            });
            load(current);
        });
    });
});
</script>
<div class="content-box">
    <div class="row">
        <div class="col-md-12">
            <div class="form-inline" style="margin-bottom: 10px;">
                <label for="lang_code">{{ lang._("Dil") }}</label>
                <select id="lang_code" class="selectpicker" data-width="160px"></select>
                <label for="lang_filter" style="margin-left: 15px;">{{ lang._("Ara") }}</label>
                <input type="text" class="form-control" id="lang_filter" placeholder="{{ lang._('anahtar') }}">
                <span class="text-muted" id="lang_note" style="margin-left: 10px;"></span>
            </div>
        </div>
    </div>
    <table class="table table-condensed table-hover table-striped">
        <tbody id="lang_rows"></tbody>
    </table>
    <div class="row">
        <div class="col-md-12">
            <button class="btn btn-primary" id="save_lang">{{ lang._("Apply") }}</button>
        </div>
    </div>
</div>
