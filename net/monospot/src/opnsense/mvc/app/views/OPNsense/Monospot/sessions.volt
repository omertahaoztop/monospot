{#
 # Monospot > Oturumlar: captive portal zone oturumları (yetkili istemciler) + kesme.
 # Satır aksiyonu core service.volt kalıbıyla (loaded.rs.jquery.bootgrid + dynamic buton).
 #}
<script>
    'use strict';

    $( document ).ready(function () {
        function grid_reload() {
            $('#grid-sessions').bootgrid('reload');
        }

        let grid_sessions = $("#grid-sessions").UIBootgrid({
            search: '/api/monospot/sessions/search/',
            options: {
                multiSelect: false,
                selection: false,
                formatters: {
                    commands: function (column, row) {
                        return '<button type="button" class="btn btn-xs btn-default command-disconnect" data-toggle="tooltip"'
                            + ' title="{{ lang._('Oturumu kes') }}" data-row-id="' + row.id + '">'
                            + '<span class="fa fa-ban fa-fw"></span></button>';
                    }
                }
            }
        });

        grid_sessions.on('loaded.rs.jquery.bootgrid', function () {
            $('[data-toggle="tooltip"]').tooltip({container: 'body', trigger: 'hover'});
            $('.command-disconnect').click(function () {
                let $button = $(this);
                let row = $button.data('row-id');
                let user = $button.closest('tr').find('td').eq(1).text();
                $button.closest('tr').addClass('bg-info');
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: '{{ lang._("Oturumu kes") }}',
                    message: '{{ lang._("Bu istemcinin internet erişimi kesilecek:") }} <b>' + user + '</b>',
                    draggable: true,
                    buttons: [{
                        label: '{{ lang._("Vazgeç") }}',
                        action: function (dialog) { dialog.close(); }
                    }, {
                        label: '{{ lang._("Kes") }}',
                        cssClass: 'btn-danger',
                        action: function (dialog) {
                            ajaxCall('/api/monospot/sessions/disconnect/' + encodeURIComponent(row), {}, function (data, status) {
                                if (status != 'success' || data['status'] != 'ok') {
                                    BootstrapDialog.show({
                                        type: BootstrapDialog.TYPE_WARNING,
                                        title: '{{ lang._("Oturum") }}',
                                        message: JSON.stringify(data),
                                        draggable: true
                                    });
                                }
                                grid_reload();
                                dialog.close();
                            });
                        }
                    }]
                });
            });
        });

        /* sayfa başlığına zone bilgisini yaz */
        ajaxCall('/api/monospot/service/info', {}, function (data, status) {
            if (data && !data.error) {
                $('#sessions_zone').text(data.zoneid
                    + (data.zone.description ? ' — ' + data.zone.description : '')
                    + (data.zone.interfaces.length ? ' (' + data.zone.interfaces.join(', ') + ')' : ''));
            }
        });
    });

</script>

<div class="tab-content content-box __mb">
    <div class="row">
        <div class="col-md-12">
            <h2 style="margin-top: 0;">{{ lang._("Yetkili oturumlar") }} <small id="sessions_zone"></small></h2>
        </div>
    </div>
    <table id="grid-sessions" class="table table-condensed table-hover table-striped table-responsive">
        <thead>
          <tr>
            <th data-column-id="id" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
            <th data-column-id="user" data-type="string">{{ lang._('Kullanıcı') }}</th>
            <th data-column-id="ip" data-type="string">{{ lang._('IP adresi') }}</th>
            <th data-column-id="mac" data-type="string">MAC</th>
            <th data-column-id="via" data-type="string">{{ lang._('Kimlik') }}</th>
            <th data-column-id="start" data-type="string">{{ lang._('Başlangıç') }}</th>
            <th data-column-id="last" data-type="string">{{ lang._('Son erişim') }}</th>
            <th data-column-id="traffic" data-type="string">{{ lang._('Trafik') }}</th>
            <th data-column-id="commands" data-width="60" data-formatter="commands" data-sortable="false"></th>
          </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
